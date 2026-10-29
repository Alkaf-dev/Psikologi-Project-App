<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportLocalData extends Command
{
    protected $signature = 'sheets:import-local-data
                            {--export-source= : Export data koneksi default ke file JSON lalu keluar}
                            {--source= : File JSON sumber hasil export}
                            {--inspect : Tampilkan schema dan jumlah data target lalu keluar}
                            {--dry-run : Bandingkan tanpa menulis data}
                            {--execute : Jalankan import dalam satu transaksi}
                            {--connection= : Nama koneksi database target}
                            {--backup-dir= : Direktori backup snapshot target}
                            {--backup-confirmed : Konfirmasi backup penuh database target sudah dibuat}
                            {--skip-conflicts : Lanjutkan import data yang tidak konflik}
                            {--json : Cetak ringkasan dalam JSON}';

    protected $description = 'Import data lokal users, courses, schedules, dan settings ke database target secara idempotent';

    /**
     * @var array<string, string>
     */
    protected const PRODI_MAP = [
        '01M60CY00TAFA0HV21NX7JFNDF' => '01M5V8C4V913P5WE4PQ8TAGQNW',
        '01M60M042VB05KZ8D99P9D6MJE' => '01M641HHGBEDB4ABM7ANGBF93B',
        '01M60M043GTP41WPV6GWJ61FHH' => '01M641HX8KX4NWVF0RZ9607VFN',
        '01M60M043QTPAWJT4PQJ4HQWE5' => '01M641JD9ZTCHG1GB5MPQXKBQW',
    ];

    /**
     * @var array<string, string>
     */
    protected const PRODI_CODES = [
        '01M5V8C4V913P5WE4PQ8TAGQNW' => 'psi',
        '01M641HHGBEDB4ABM7ANGBF93B' => 'hki',
        '01M641HX8KX4NWVF0RZ9607VFN' => 'es',
        '01M641JD9ZTCHG1GB5MPQXKBQW' => 'pgmi',
    ];

    /**
     * @var array<int, string>
     */
    protected const TABLES = ['users', 'courses', 'schedules', 'settings'];

    /**
     * @var array<string, array<int, string>>
     */
    protected const REQUIRED_COLUMNS = [
        'users' => [
            'id', 'name', 'email', 'npm', 'password_hash', 'role', 'class_name',
            'cohort', 'phone', 'active', 'created_at', 'updated_at', 'deleted_at', 'prodi_id',
        ],
        'courses' => [
            'id', 'code', 'name', 'credits', 'lecturer', 'semester', 'class_name',
            'description', 'created_at', 'updated_at', 'deleted_at', 'prodi_ids', 'meeting_link',
        ],
        'schedules' => [
            'id', 'course_id', 'day', 'start_time', 'end_time', 'room', 'class_name',
            'created_at', 'updated_at', 'deleted_at', 'mode', 'platform', 'meeting_link',
        ],
        'settings' => ['key', 'value', 'updated_at'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    protected const OPTIONAL_COLUMNS = [
        'courses' => ['attendance_link'],
        'schedules' => ['attendance_link'],
    ];

    public function handle(): int
    {
        try {
            if ($this->option('export-source')) {
                return $this->exportSource((string) $this->option('export-source'));
            }

            $connection = $this->resolveConnection((string) ($this->option('connection') ?? ''));

            if ((bool) $this->option('inspect')) {
                return $this->inspect($connection);
            }

            $sourcePath = (string) ($this->option('source') ?? '');

            if ($sourcePath === '') {
                throw new RuntimeException('Gunakan --source=file.json untuk dry-run atau --execute.');
            }

            $dryRun = (bool) $this->option('dry-run');
            $execute = (bool) $this->option('execute');

            if ($dryRun === $execute) {
                throw new RuntimeException('Gunakan tepat satu dari --dry-run atau --execute.');
            }

            if ($execute && ! $this->option('backup-confirmed')) {
                throw new RuntimeException('Import sebenarnya memerlukan --backup-confirmed setelah backup penuh database target dibuat.');
            }

            $source = $this->loadSource($sourcePath);
            $this->assertTarget($connection);
            $columns = $this->targetColumns($connection);
            $plan = $this->plan($source, $connection, $columns);

            $this->render($plan, $dryRun);

            $conflicts = $this->conflictCount($plan);

            if ($conflicts > 0 && ! $this->option('skip-conflicts')) {
                $this->components->error('Ditemukan '.$conflicts.' konflik. Import dibatalkan. Review dry-run atau gunakan --skip-conflicts setelah disetujui.');

                return self::FAILURE;
            }

            if ($dryRun) {
                if (! $this->option('json')) {
                    $this->components->info('Dry-run: tidak ada perubahan data.');
                }

                return self::SUCCESS;
            }

            $backupPath = $this->backupTarget($connection);

            if ($backupPath === null) {
                return self::FAILURE;
            }

            $this->components->info('Backup snapshot target: '.$backupPath);

            DB::connection($connection)->transaction(function () use ($plan, $connection): void {
                $this->apply($plan, $connection);
            });

            $this->renderTargetCounts($connection);
            $this->components->info('Import selesai dalam transaksi. Jalankan php artisan cache:clear di backend target.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveConnection(string $name): string
    {
        $name = trim($name) !== '' ? trim($name) : (string) config('database.default');
        $config = config('database.connections.'.$name);

        if (! is_array($config)) {
            throw new RuntimeException('Koneksi database ['.$name.'] tidak ditemukan.');
        }

        if ($name === 'public') {
            foreach (['host', 'database', 'username'] as $key) {
                if (trim((string) ($config[$key] ?? '')) === '') {
                    throw new RuntimeException('Koneksi public belum lengkap. Set PUBLIC_DB_HOST, PUBLIC_DB_DATABASE, PUBLIC_DB_USERNAME, dan PUBLIC_DB_PASSWORD.');
                }
            }
        }

        return $name;
    }

    protected function exportSource(string $path): int
    {
        $connection = (string) config('database.default');
        $data = [
            'meta' => [
                'exported_at' => now()->toIso8601String(),
                'source_connection' => $connection,
                'source_database' => DB::connection($connection)->getDatabaseName(),
                'source_driver' => DB::connection($connection)->getDriverName(),
                'counts' => [],
            ],
            'prodi_mapping' => self::PRODI_MAP,
        ];

        foreach (self::TABLES as $table) {
            $rows = DB::connection($connection)
                ->table($this->sheetTable($table))
                ->orderBy('_row_id')
                ->get()
                ->map(function (object $row): array {
                    $item = (array) $row;
                    unset($item['_row_id']);

                    return $item;
                })
                ->all();

            $data[$table] = $rows;
            $data['meta']['counts'][$table] = count($rows);
        }

        foreach (['groups', 'tasks', 'submissions', 'announcements', 'api_tokens'] as $table) {
            $name = $this->sheetTable($table);
            $data['meta']['counts'][$table] = Schema::connection($connection)->hasTable($name)
                ? DB::connection($connection)->table($name)->count()
                : 0;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($path, 0600);
        }

        $this->components->info('Export lokal: '.$path);
        $this->table(
            ['Tabel', 'Jumlah'],
            collect($data['meta']['counts'])
                ->map(fn (int $count, string $table): array => [$table, $count])
                ->values()
                ->all(),
        );
        $this->components->warn('File ini berisi password_hash. Simpan di luar repo dan jangan commit.');

        return self::SUCCESS;
    }

    protected function inspect(string $connection): int
    {
        $db = DB::connection($connection);
        $this->components->info(sprintf(
            'Target: %s (%s), database: %s',
            $connection,
            $db->getDriverName(),
            $db->getDatabaseName(),
        ));

        $rows = [];

        foreach (array_merge(self::TABLES, ['study_programs']) as $table) {
            $name = $this->sheetTable($table);

            if (! Schema::connection($connection)->hasTable($name)) {
                $rows[] = [$name, 'MISSING', '-', '-'];

                continue;
            }

            $columns = Schema::connection($connection)->getColumnListing($name);
            $rows[] = [
                $name,
                DB::connection($connection)->table($name)->count(),
                count($columns),
                implode(', ', $columns),
            ];
        }

        $this->table(['Tabel', 'Jumlah', 'Kolom', 'Daftar kolom'], $rows);
        $this->renderTargetPrograms($connection);

        return self::SUCCESS;
    }

    protected function renderTargetPrograms(string $connection): void
    {
        $rows = DB::connection($connection)
            ->table($this->sheetTable('study_programs'))
            ->orderBy('code')
            ->get()
            ->map(fn (object $row): array => [
                (string) ($row->id ?? ''),
                (string) ($row->code ?? ''),
                (string) ($row->name ?? ''),
                (string) ($row->degree ?? ''),
                $this->isActive($row->active ?? null) ? 'aktif' : 'nonaktif',
            ])
            ->all();

        $this->table(['ID', 'Kode', 'Nama', 'Jenjang', 'Status'], $rows);
    }

    protected function assertTarget(string $connection): void
    {
        foreach (self::TABLES as $table) {
            $name = $this->sheetTable($table);

            if (! Schema::connection($connection)->hasTable($name)) {
                throw new RuntimeException('Tabel target ['.$name.'] tidak ditemukan.');
            }
        }

        $programsTable = $this->sheetTable('study_programs');

        if (! Schema::connection($connection)->hasTable($programsTable)) {
            throw new RuntimeException('Tabel target ['.$programsTable.'] tidak ditemukan.');
        }

        $programs = DB::connection($connection)->table($programsTable)->get();
        $byId = $programs->keyBy(fn (object $row): string => (string) ($row->id ?? ''));

        foreach (self::PRODI_MAP as $sourceId => $targetId) {
            $program = $byId->get($targetId);

            if ($program === null) {
                throw new RuntimeException('Prodi target ['.$targetId.'] untuk sumber ['.$sourceId.'] tidak ditemukan.');
            }

            if (! $this->isActive($program->active ?? null)) {
                throw new RuntimeException('Prodi target ['.$targetId.'] tidak aktif.');
            }

            $expectedCode = self::PRODI_CODES[$targetId] ?? null;
            $actualCode = strtolower(trim((string) ($program->code ?? '')));

            if ($expectedCode !== null && $actualCode !== $expectedCode) {
                throw new RuntimeException('Kode Prodi target ['.$targetId.'] tidak sesuai: '.$actualCode.' (expected '.$expectedCode.').');
            }
        }

        $active = $programs->filter(fn (object $row): bool => $this->isActive($row->active ?? null));

        if ($active->count() !== count(self::PRODI_MAP)) {
            throw new RuntimeException('Target harus memiliki tepat 4 Prodi aktif. Ditemukan '.$active->count().'.');
        }

        $extra = $active->reject(
            fn (object $row): bool => in_array((string) ($row->id ?? ''), array_values(self::PRODI_MAP), true),
        );

        if ($extra->isNotEmpty()) {
            throw new RuntimeException('Ditemukan Prodi aktif di luar mapping: '.$extra
                ->map(fn (object $row): string => (string) ($row->code ?? ''))
                ->implode(', '));
        }
    }

    /**
     * @return array<string, array{columns: array<int, string>, optional: array<string, bool>}>
     */
    protected function targetColumns(string $connection): array
    {
        $result = [];

        foreach (self::TABLES as $table) {
            $columns = Schema::connection($connection)->getColumnListing($this->sheetTable($table));
            $missing = array_values(array_diff(self::REQUIRED_COLUMNS[$table], $columns));

            if ($missing !== []) {
                throw new RuntimeException('Tabel target ['.$this->sheetTable($table).'] tidak memiliki kolom wajib: '.implode(', ', $missing));
            }

            $optional = [];

            foreach (self::OPTIONAL_COLUMNS[$table] ?? [] as $column) {
                $optional[$column] = in_array($column, $columns, true);
            }

            $result[$table] = ['columns' => $columns, 'optional' => $optional];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadSource(string $path): array
    {
        if (! File::exists($path)) {
            throw new RuntimeException('File sumber ['.$path.'] tidak ditemukan.');
        }

        $json = json_decode(File::get($path), true);

        if (! is_array($json)) {
            throw new RuntimeException('File sumber bukan JSON object yang valid.');
        }

        foreach (self::TABLES as $table) {
            if (! isset($json[$table]) || ! is_array($json[$table])) {
                throw new RuntimeException('File sumber tidak memiliki array ['.$table.'].');
            }
        }

        $mapping = $json['prodi_mapping'] ?? null;

        if (! is_array($mapping) || count($mapping) !== count(self::PRODI_MAP)) {
            throw new RuntimeException('prodi_mapping di file sumber tidak sesuai mapping yang disetujui.');
        }

        foreach (self::PRODI_MAP as $sourceId => $targetId) {
            if (($mapping[$sourceId] ?? null) !== $targetId) {
                throw new RuntimeException('Mapping Prodi ['.$sourceId.'] di file sumber tidak sesuai.');
            }
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, array{columns: array<int, string>, optional: array<string, bool>}>  $columns
     * @return array<string, mixed>
     */
    protected function plan(array $source, string $connection, array $columns): array
    {
        $plan = [
            'operations' => [],
            'warnings' => [],
            'skipped' => [],
            'prodi_mapping' => self::PRODI_MAP,
        ];

        foreach (['api_tokens', 'groups', 'tasks', 'submissions', 'announcements'] as $table) {
            $plan['skipped'][$table] = (int) ($source['meta']['counts'][$table] ?? 0);
        }

        $targetUsers = $this->rows($connection, 'users');
        $targetCourses = $this->rows($connection, 'courses');
        $targetSchedules = $this->rows($connection, 'schedules');
        $targetSettings = $this->rows($connection, 'settings');

        $userByEmail = $this->indexBy($targetUsers, fn (array $row): string => $this->normalizeEmail($row['email'] ?? ''));
        $userByNpm = $this->indexBy($targetUsers, fn (array $row): string => $this->normalizeNpm($row['npm'] ?? ''));
        $courseById = $this->indexBy($targetCourses, fn (array $row): string => (string) ($row['id'] ?? ''));
        $courseByKey = $this->indexBy($targetCourses, fn (array $row): string => $this->courseKey($row));
        $courseByCode = $this->indexBy($targetCourses, fn (array $row): string => strtolower(trim((string) ($row['code'] ?? ''))));
        $scheduleById = $this->indexBy($targetSchedules, fn (array $row): string => (string) ($row['id'] ?? ''));
        $scheduleByKey = $this->indexBy($targetSchedules, fn (array $row): string => $this->scheduleKey($row));
        $settingByKey = $this->indexBy($targetSettings, fn (array $row): string => trim((string) ($row['key'] ?? '')));

        $usedUserIds = $this->usedIds($targetUsers);
        $usedCourseIds = $this->usedIds($targetCourses);
        $usedScheduleIds = $this->usedIds($targetSchedules);
        $courseIdMap = [];
        $superAdminSkipped = 0;

        foreach ($source['users'] as $row) {
            $row = (array) $row;
            $sourceId = (string) ($row['id'] ?? '');
            $email = trim((string) ($row['email'] ?? ''));
            $npm = trim((string) ($row['npm'] ?? ''));
            $role = trim((string) ($row['role'] ?? ''));
            $key = $email !== '' ? $email : ($npm !== '' ? $npm : $sourceId);

            $sourceDeleted = $this->isDeleted($row);

            if (in_array($role, ['super_admin', 'admin'], true)) {
                $superAdminSkipped++;
                $this->add($plan, 'users', 'skip', $key, ['reason' => 'super_admin/admin lokal tidak dimigrasikan']);

                continue;
            }

            if ($email === '' && $npm === '') {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'email dan NIM kosong']);

                continue;
            }

            $sourceProdiId = trim((string) ($row['prodi_id'] ?? ''));

            if (! isset(self::PRODI_MAP[$sourceProdiId])) {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'prodi_id tidak ada di mapping: '.$sourceProdiId]);

                continue;
            }

            $mappedProdiId = self::PRODI_MAP[$sourceProdiId];
            $candidates = [];

            if ($email !== '') {
                $matches = $userByEmail[$this->normalizeEmail($email)] ?? [];

                if (count($matches) > 1) {
                    $this->add($plan, 'users', 'conflict', $key, ['reason' => 'email duplikat di target']);

                    continue;
                }

                if (count($matches) === 1) {
                    $candidates[(string) ($matches[0]['id'] ?? '')] = $matches[0];
                }
            }

            if ($npm !== '') {
                $matches = $userByNpm[$this->normalizeNpm($npm)] ?? [];

                if (count($matches) > 1) {
                    $this->add($plan, 'users', 'conflict', $key, ['reason' => 'NIM duplikat di target']);

                    continue;
                }

                if (count($matches) === 1) {
                    $candidates[(string) ($matches[0]['id'] ?? '')] = $matches[0];
                }
            }

            if (count($candidates) > 1) {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'email dan NIM cocok ke user target berbeda']);

                continue;
            }

            $existing = $candidates === [] ? null : reset($candidates);

            if ($existing === null) {
                $passwordHash = (string) ($row['password_hash'] ?? '');

                if ($passwordHash === '') {
                    $this->add($plan, 'users', 'conflict', $key, ['reason' => 'password_hash kosong']);

                    continue;
                }

                $id = $this->availableId($sourceId, $usedUserIds);
                $usedUserIds[$id] = true;

                if ($id !== $sourceId && $sourceId !== '') {
                    $plan['warnings'][] = 'ID user ['.$sourceId.'] sudah dipakai di target; dibuat ID baru ['.$id.'].';
                }

                $this->add($plan, 'users', 'create', $key, [
                    'id' => $id,
                    'source_id' => $sourceId,
                    'payload' => [
                        'id' => $id,
                        'name' => (string) ($row['name'] ?? ''),
                        'email' => $email,
                        'npm' => $npm,
                        'password_hash' => $passwordHash,
                        'role' => $role,
                        'class_name' => (string) ($row['class_name'] ?? ''),
                        'cohort' => (string) ($row['cohort'] ?? ''),
                        'phone' => (string) ($row['phone'] ?? ''),
                        'active' => $this->normalizeActive($row['active'] ?? '1'),
                        'created_at' => (string) ($row['created_at'] ?? now()->toIso8601String()),
                        'updated_at' => (string) ($row['updated_at'] ?? now()->toIso8601String()),
                        'deleted_at' => $sourceDeleted ? (string) ($row['deleted_at'] ?? '') : '',
                        'prodi_id' => $mappedProdiId,
                    ],
                ]);

                continue;
            }

            $existingId = (string) ($existing['id'] ?? '');

            $targetDeleted = $this->isDeleted($existing);

            if ($targetDeleted !== $sourceDeleted) {
                $this->add($plan, 'users', 'conflict', $key, [
                    'reason' => $sourceDeleted
                        ? 'user target aktif tetapi sumber soft-deleted'
                        : 'user target soft-deleted tetapi sumber aktif',
                ]);

                continue;
            }

            $existingRole = strtolower(trim((string) ($existing['role'] ?? '')));

            if (in_array($existingRole, ['super_admin', 'admin'], true)) {
                $this->add($plan, 'users', 'skip', $key, ['reason' => 'admin target tidak ditimpa']);

                continue;
            }

            if (trim((string) ($existing['prodi_id'] ?? '')) !== $mappedProdiId) {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'prodi user target berbeda: '.(string) ($existing['prodi_id'] ?? '')]);

                continue;
            }

            $existingEmail = trim((string) ($existing['email'] ?? ''));
            $existingNpm = trim((string) ($existing['npm'] ?? ''));

            if ($existingEmail !== '' && $email !== '' && $this->normalizeEmail($existingEmail) !== $this->normalizeEmail($email)) {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'email target berbeda']);

                continue;
            }

            if ($existingNpm !== '' && $npm !== '' && $this->normalizeNpm($existingNpm) !== $this->normalizeNpm($npm)) {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'NIM target berbeda']);

                continue;
            }

            if ($existingEmail === '' && $email !== '' && isset($userByEmail[$this->normalizeEmail($email)])) {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'email akan bentrok dengan user target lain']);

                continue;
            }

            if ($existingNpm === '' && $npm !== '' && isset($userByNpm[$this->normalizeNpm($npm)])) {
                $this->add($plan, 'users', 'conflict', $key, ['reason' => 'NIM akan bentrok dengan user target lain']);

                continue;
            }

            $payload = [];
            $changes = [];

            foreach (['name', 'role', 'class_name', 'cohort', 'phone'] as $field) {
                $new = (string) ($row[$field] ?? '');
                $old = (string) ($existing[$field] ?? '');

                if ($old !== $new) {
                    $payload[$field] = $new;
                    $changes[$field] = [$old, $new];
                }
            }

            $newActive = $this->normalizeActive($row['active'] ?? '1');
            $oldActive = $this->normalizeActive($existing['active'] ?? '1');

            if ($oldActive !== $newActive) {
                $payload['active'] = $newActive;
                $changes['active'] = [$oldActive, $newActive];
            }

            if ($existingEmail === '' && $email !== '') {
                $payload['email'] = $email;
                $changes['email'] = ['', $email];
            }

            if ($existingNpm === '' && $npm !== '') {
                $payload['npm'] = $npm;
                $changes['npm'] = ['', $npm];
            }

            if ($payload === []) {
                $this->add($plan, 'users', 'skip', $key, ['reason' => 'sudah sama', 'id' => $existingId]);

                continue;
            }

            $payload['updated_at'] = now()->toIso8601String();

            $this->add($plan, 'users', 'update', $key, [
                'id' => $existingId,
                'source_id' => $sourceId,
                'where' => ['id' => $existingId],
                'payload' => $payload,
                'changes' => $changes,
            ]);
        }

        $plan['skipped']['super_admin'] = $superAdminSkipped;

        if (! ($columns['courses']['optional']['attendance_link'] ?? false)) {
            $count = collect($source['courses'])
                ->filter(fn (array $row): bool => trim((string) ($row['attendance_link'] ?? '')) !== '')
                ->count();

            if ($count > 0) {
                $plan['warnings'][] = 'Target tidak memiliki kolom attendance_link; '.$count.' course melewatkan attendance_link.';
            }
        }

        if (! ($columns['schedules']['optional']['attendance_link'] ?? false)) {
            $count = collect($source['schedules'])
                ->filter(fn (array $row): bool => trim((string) ($row['attendance_link'] ?? '')) !== '')
                ->count();

            if ($count > 0) {
                $plan['warnings'][] = 'Target tidak memiliki kolom attendance_link; '.$count.' schedule melewatkan attendance_link.';
            }
        }

        foreach ($source['courses'] as $row) {
            $row = (array) $row;
            $sourceId = (string) ($row['id'] ?? '');
            $code = trim((string) ($row['code'] ?? ''));

            $sourceDeleted = $this->isDeleted($row);

            if ($code === '') {
                $this->add($plan, 'courses', 'conflict', $sourceId, ['reason' => 'code kosong']);

                continue;
            }

            $sourceProdiIds = $this->splitIds((string) ($row['prodi_ids'] ?? ''));
            $mappedProdiIds = [];
            $unknown = null;

            foreach ($sourceProdiIds as $sourceProdiId) {
                if (! isset(self::PRODI_MAP[$sourceProdiId])) {
                    $unknown = $sourceProdiId;
                    break;
                }

                $mappedProdiIds[] = self::PRODI_MAP[$sourceProdiId];
            }

            if ($unknown !== null) {
                $this->add($plan, 'courses', 'conflict', $code, ['reason' => 'prodi_ids tidak ada di mapping: '.$unknown]);

                continue;
            }

            if ($mappedProdiIds === []) {
                $this->add($plan, 'courses', 'conflict', $code, ['reason' => 'prodi_ids kosong']);

                continue;
            }

            $key = $this->courseKeyFrom($code, $mappedProdiIds);
            $existing = null;
            $byId = $courseById[$sourceId] ?? [];

            if (count($byId) > 1) {
                $this->add($plan, 'courses', 'conflict', $code, ['reason' => 'ID course duplikat di target']);

                continue;
            }

            if (count($byId) === 1) {
                $candidate = $byId[0];

                if ($this->courseKey($candidate) !== $key) {
                    $this->add($plan, 'courses', 'conflict', $code, ['reason' => 'ID course ['.$sourceId.'] bentrok dengan course target lain']);

                    continue;
                }

                $existing = $candidate;
            }

            if ($existing === null) {
                $byKey = $courseByKey[$key] ?? [];

                if (count($byKey) > 1) {
                    $this->add($plan, 'courses', 'conflict', $code, ['reason' => 'beberapa course target memiliki kode dan Prodi yang sama']);

                    continue;
                }

                if (count($byKey) === 1) {
                    $existing = $byKey[0];
                }
            }

            if ($existing === null) {
                $overlap = collect($courseByCode[strtolower($code)] ?? [])
                    ->contains(function (array $candidate) use ($mappedProdiIds): bool {
                        $candidateProdiIds = $this->splitIds((string) ($candidate['prodi_ids'] ?? ''));

                        return $candidateProdiIds !== []
                            && array_intersect($mappedProdiIds, $candidateProdiIds) !== []
                            && $this->courseKey($candidate) !== $this->courseKeyFrom((string) ($candidate['code'] ?? ''), $mappedProdiIds);
                    });

                if ($overlap) {
                    $this->add($plan, 'courses', 'conflict', $code, ['reason' => 'kode sama dengan course target yang Prodinya overlap tetapi set Prodi berbeda']);

                    continue;
                }

                if (($courseByCode[strtolower($code)] ?? []) !== []) {
                    $plan['warnings'][] = 'Kode course ['.$code.'] juga dipakai course target lain dengan Prodi berbeda; dibuat record terpisah.';
                }

                $id = $this->availableId($sourceId, $usedCourseIds);
                $usedCourseIds[$id] = true;

                if ($id !== $sourceId && $sourceId !== '') {
                    $plan['warnings'][] = 'ID course ['.$sourceId.'] sudah dipakai di target; dibuat ID baru ['.$id.'].';
                }

                $payload = [
                    'id' => $id,
                    'code' => $code,
                    'name' => (string) ($row['name'] ?? ''),
                    'credits' => (string) ($row['credits'] ?? ''),
                    'lecturer' => (string) ($row['lecturer'] ?? ''),
                    'semester' => (string) ($row['semester'] ?? ''),
                    'class_name' => (string) ($row['class_name'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? now()->toIso8601String()),
                    'updated_at' => (string) ($row['updated_at'] ?? now()->toIso8601String()),
                    'deleted_at' => $sourceDeleted ? (string) ($row['deleted_at'] ?? '') : '',
                    'prodi_ids' => implode(',', $mappedProdiIds),
                    'meeting_link' => (string) ($row['meeting_link'] ?? ''),
                ];

                if ($columns['courses']['optional']['attendance_link'] ?? false) {
                    $payload['attendance_link'] = (string) ($row['attendance_link'] ?? '');
                }

                $this->add($plan, 'courses', 'create', $code, [
                    'id' => $id,
                    'source_id' => $sourceId,
                    'payload' => $payload,
                ]);

                $courseIdMap[$sourceId] = $id;

                continue;
            }

            $existingId = (string) ($existing['id'] ?? '');

            $targetDeleted = $this->isDeleted($existing);

            if ($targetDeleted !== $sourceDeleted) {
                $this->add($plan, 'courses', 'conflict', $code, [
                    'reason' => $sourceDeleted
                        ? 'course target aktif tetapi sumber soft-deleted'
                        : 'course target soft-deleted tetapi sumber aktif',
                ]);

                continue;
            }

            $payload = [];
            $changes = [];
            $fields = [
                'name' => (string) ($row['name'] ?? ''),
                'credits' => (string) ($row['credits'] ?? ''),
                'lecturer' => (string) ($row['lecturer'] ?? ''),
                'semester' => (string) ($row['semester'] ?? ''),
                'class_name' => (string) ($row['class_name'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'prodi_ids' => implode(',', $mappedProdiIds),
                'meeting_link' => (string) ($row['meeting_link'] ?? ''),
            ];

            if ($columns['courses']['optional']['attendance_link'] ?? false) {
                $fields['attendance_link'] = (string) ($row['attendance_link'] ?? '');
            }

            foreach ($fields as $field => $new) {
                $old = (string) ($existing[$field] ?? '');

                if ($old !== $new) {
                    $payload[$field] = $new;
                    $changes[$field] = [$old, $new];
                }
            }

            if ($payload === []) {
                $this->add($plan, 'courses', 'skip', $code, ['reason' => 'sudah sama', 'id' => $existingId]);

                $courseIdMap[$sourceId] = $existingId;

                continue;
            }

            $payload['updated_at'] = now()->toIso8601String();

            $this->add($plan, 'courses', 'update', $code, [
                'id' => $existingId,
                'source_id' => $sourceId,
                'where' => ['id' => $existingId],
                'payload' => $payload,
                'changes' => $changes,
            ]);

            $courseIdMap[$sourceId] = $existingId;
        }

        foreach ($source['schedules'] as $row) {
            $row = (array) $row;
            $sourceId = (string) ($row['id'] ?? '');
            $sourceCourseId = trim((string) ($row['course_id'] ?? ''));
            $label = $this->scheduleLabel($row);

            $sourceDeleted = $this->isDeleted($row);

            if (! isset($courseIdMap[$sourceCourseId])) {
                $this->add($plan, 'schedules', 'conflict', $label, ['reason' => 'course_id tidak termap: '.$sourceCourseId]);

                continue;
            }

            $mappedCourseId = $courseIdMap[$sourceCourseId];
            $key = $this->scheduleKeyFrom($mappedCourseId, $row);
            $existing = null;
            $byId = $scheduleById[$sourceId] ?? [];

            if (count($byId) > 1) {
                $this->add($plan, 'schedules', 'conflict', $label, ['reason' => 'ID schedule duplikat di target']);

                continue;
            }

            if (count($byId) === 1) {
                $candidate = $byId[0];

                if (trim((string) ($candidate['course_id'] ?? '')) !== $mappedCourseId) {
                    $this->add($plan, 'schedules', 'conflict', $label, ['reason' => 'ID schedule ['.$sourceId.'] bentrok dengan course target lain']);

                    continue;
                }

                $existing = $candidate;
            }

            if ($existing === null) {
                $byKey = $scheduleByKey[$key] ?? [];

                if (count($byKey) > 1) {
                    $this->add($plan, 'schedules', 'conflict', $label, ['reason' => 'beberapa schedule target memiliki course, hari, jam, dan kelas yang sama']);

                    continue;
                }

                if (count($byKey) === 1) {
                    $existing = $byKey[0];
                }
            }

            if ($existing === null) {
                $id = $this->availableId($sourceId, $usedScheduleIds);
                $usedScheduleIds[$id] = true;

                if ($id !== $sourceId && $sourceId !== '') {
                    $plan['warnings'][] = 'ID schedule ['.$sourceId.'] sudah dipakai di target; dibuat ID baru ['.$id.'].';
                }

                $payload = [
                    'id' => $id,
                    'course_id' => $mappedCourseId,
                    'day' => (string) ($row['day'] ?? ''),
                    'start_time' => (string) ($row['start_time'] ?? ''),
                    'end_time' => (string) ($row['end_time'] ?? ''),
                    'room' => (string) ($row['room'] ?? ''),
                    'class_name' => (string) ($row['class_name'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? now()->toIso8601String()),
                    'updated_at' => (string) ($row['updated_at'] ?? now()->toIso8601String()),
                    'deleted_at' => $sourceDeleted ? (string) ($row['deleted_at'] ?? '') : '',
                    'mode' => (string) ($row['mode'] ?? ''),
                    'platform' => (string) ($row['platform'] ?? ''),
                    'meeting_link' => (string) ($row['meeting_link'] ?? ''),
                ];

                if ($columns['schedules']['optional']['attendance_link'] ?? false) {
                    $payload['attendance_link'] = (string) ($row['attendance_link'] ?? '');
                }

                $this->add($plan, 'schedules', 'create', $label, [
                    'id' => $id,
                    'source_id' => $sourceId,
                    'payload' => $payload,
                ]);

                $this->scheduleWarning($plan, $row, $label);

                continue;
            }

            $existingId = (string) ($existing['id'] ?? '');

            $targetDeleted = $this->isDeleted($existing);

            if ($targetDeleted !== $sourceDeleted) {
                $this->add($plan, 'schedules', 'conflict', $label, [
                    'reason' => $sourceDeleted
                        ? 'schedule target aktif tetapi sumber soft-deleted'
                        : 'schedule target soft-deleted tetapi sumber aktif',
                ]);

                continue;
            }

            $payload = [];
            $changes = [];
            $fields = [
                'day' => (string) ($row['day'] ?? ''),
                'start_time' => (string) ($row['start_time'] ?? ''),
                'end_time' => (string) ($row['end_time'] ?? ''),
                'room' => (string) ($row['room'] ?? ''),
                'class_name' => (string) ($row['class_name'] ?? ''),
                'mode' => (string) ($row['mode'] ?? ''),
                'platform' => (string) ($row['platform'] ?? ''),
                'meeting_link' => (string) ($row['meeting_link'] ?? ''),
            ];

            if ($columns['schedules']['optional']['attendance_link'] ?? false) {
                $fields['attendance_link'] = (string) ($row['attendance_link'] ?? '');
            }

            foreach ($fields as $field => $new) {
                $old = (string) ($existing[$field] ?? '');

                if ($old !== $new) {
                    $payload[$field] = $new;
                    $changes[$field] = [$old, $new];
                }
            }

            if ($payload === []) {
                $this->add($plan, 'schedules', 'skip', $label, ['reason' => 'sudah sama', 'id' => $existingId]);

                continue;
            }

            $payload['updated_at'] = now()->toIso8601String();

            $this->add($plan, 'schedules', 'update', $label, [
                'id' => $existingId,
                'source_id' => $sourceId,
                'where' => ['id' => $existingId],
                'payload' => $payload,
                'changes' => $changes,
            ]);

            $this->scheduleWarning($plan, $row, $label);
        }

        $seenSettingKeys = [];

        foreach ($source['settings'] as $row) {
            $row = (array) $row;
            $key = trim((string) ($row['key'] ?? ''));

            if ($key === '') {
                $this->add($plan, 'settings', 'conflict', '(kosong)', ['reason' => 'key kosong']);

                continue;
            }

            if (isset($seenSettingKeys[$key])) {
                $this->add($plan, 'settings', 'conflict', $key, ['reason' => 'key duplikat di sumber']);

                continue;
            }

            $seenSettingKeys[$key] = true;
            $matches = $settingByKey[$key] ?? [];

            if (count($matches) > 1) {
                $this->add($plan, 'settings', 'conflict', $key, ['reason' => 'key duplikat di target']);

                continue;
            }

            $value = (string) ($row['value'] ?? '');
            $updatedAt = (string) ($row['updated_at'] ?? now()->toIso8601String());

            if ($matches === []) {
                $this->add($plan, 'settings', 'create', $key, [
                    'payload' => ['key' => $key, 'value' => $value, 'updated_at' => $updatedAt],
                ]);

                continue;
            }

            $existing = $matches[0];
            $old = (string) ($existing['value'] ?? '');

            if ($old === $value) {
                $this->add($plan, 'settings', 'skip', $key, ['reason' => 'sudah sama']);

                continue;
            }

            $this->add($plan, 'settings', 'update', $key, [
                'where' => ['key' => $key],
                'payload' => ['value' => $value, 'updated_at' => now()->toIso8601String()],
                'changes' => ['value' => [$old, $value]],
            ]);
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function render(array $plan, bool $dryRun): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($this->report($plan, $dryRun), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $summary = [];

        foreach (self::TABLES as $entity) {
            $counts = ['create' => 0, 'update' => 0, 'skip' => 0, 'conflict' => 0];

            foreach ($plan['operations'] as $operation) {
                if (($operation['entity'] ?? '') === $entity && isset($counts[$operation['action']])) {
                    $counts[$operation['action']]++;
                }
            }

            $summary[] = [$entity, $counts['create'], $counts['update'], $counts['skip'], $counts['conflict']];
        }

        $this->table(['Entity', 'Create', 'Update', 'Skip', 'Conflict'], $summary);

        $conflicts = array_values(array_filter(
            $plan['operations'],
            fn (array $operation): bool => ($operation['action'] ?? '') === 'conflict',
        ));

        if ($conflicts !== []) {
            $this->newLine();
            $this->components->warn('Konflik');
            $this->table(
                ['Entity', 'Key', 'Alasan'],
                array_map(fn (array $operation): array => [
                    (string) ($operation['entity'] ?? ''),
                    (string) ($operation['key'] ?? ''),
                    (string) ($operation['reason'] ?? ''),
                ], $conflicts),
            );
        }

        if (($plan['warnings'] ?? []) !== []) {
            $this->newLine();
            $this->components->warn('Peringatan');

            foreach ($plan['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        foreach (['users', 'courses', 'schedules'] as $entity) {
            $operations = array_values(array_filter(
                $plan['operations'],
                fn (array $operation): bool => ($operation['entity'] ?? '') === $entity
                    && in_array($operation['action'] ?? '', ['create', 'update'], true),
            ));

            if ($operations === []) {
                continue;
            }

            $this->newLine();
            $this->line(strtoupper($entity).' create/update: '.count($operations));

            $limit = 20;
            $rows = array_map(function (array $operation): array {
                $changes = [];

                foreach (($operation['changes'] ?? []) as $field => $values) {
                    $changes[] = $field.': '.$this->short($values[0] ?? '').' -> '.$this->short($values[1] ?? '');
                }

                return [
                    (string) ($operation['action'] ?? ''),
                    (string) ($operation['key'] ?? ''),
                    (string) ($operation['id'] ?? '-'),
                    implode('; ', $changes),
                ];
            }, array_slice($operations, 0, $limit));

            $this->table(['Aksi', 'Key', 'ID Target', 'Perubahan'], $rows);

            if (count($operations) > $limit) {
                $this->line('... dan '.(count($operations) - $limit).' lainnya. Gunakan --json untuk daftar lengkap.');
            }
        }

        $settingChanges = array_values(array_filter(
            $plan['operations'],
            fn (array $operation): bool => ($operation['entity'] ?? '') === 'settings'
                && ($operation['action'] ?? '') === 'update',
        ));

        if ($settingChanges !== []) {
            $this->newLine();
            $this->line('SETTINGS update: '.count($settingChanges));
            $this->table(
                ['Key', 'Nilai Lama', 'Nilai Baru'],
                array_map(fn (array $operation): array => [
                    (string) ($operation['key'] ?? ''),
                    $this->short((string) ($operation['changes']['value'][0] ?? '')),
                    $this->short((string) ($operation['changes']['value'][1] ?? '')),
                ], $settingChanges),
            );
        }

        $this->newLine();
        $this->components->info('Mapping Prodi');
        $this->table(
            ['Kode', 'ID Lokal', 'ID Publik'],
            collect(self::PRODI_MAP)
                ->map(fn (string $targetId, string $sourceId): array => [
                    self::PRODI_CODES[$targetId] ?? '-',
                    $sourceId,
                    $targetId,
                ])
                ->values()
                ->all(),
        );

        $this->newLine();
        $this->components->info('Data yang tidak dimigrasikan');
        $this->table(
            ['Tabel', 'Jumlah'],
            collect($plan['skipped'])
                ->map(fn (int $count, string $table): array => [$table, $count])
                ->values()
                ->all(),
        );

    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    protected function report(array $plan, bool $dryRun): array
    {
        $summary = [];

        foreach (self::TABLES as $entity) {
            $counts = ['create' => 0, 'update' => 0, 'skip' => 0, 'conflict' => 0];

            foreach ($plan['operations'] as $operation) {
                if (($operation['entity'] ?? '') === $entity && isset($counts[$operation['action']])) {
                    $counts[$operation['action']]++;
                }
            }

            $summary[$entity] = $counts;
        }

        $operations = array_map(function (array $operation): array {
            $item = [
                'entity' => (string) ($operation['entity'] ?? ''),
                'action' => (string) ($operation['action'] ?? ''),
                'key' => (string) ($operation['key'] ?? ''),
                'id' => (string) ($operation['id'] ?? ''),
                'reason' => (string) ($operation['reason'] ?? ''),
                'changes' => $operation['changes'] ?? [],
            ];

            return $item;
        }, $plan['operations']);

        return [
            'dry_run' => $dryRun,
            'summary' => $summary,
            'conflicts' => array_values(array_filter(
                $operations,
                fn (array $operation): bool => $operation['action'] === 'conflict',
            )),
            'warnings' => $plan['warnings'],
            'skipped' => $plan['skipped'],
            'prodi_mapping' => $plan['prodi_mapping'],
            'operations' => $operations,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function conflictCount(array $plan): int
    {
        return count(array_filter(
            $plan['operations'],
            fn (array $operation): bool => ($operation['action'] ?? '') === 'conflict',
        ));
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function apply(array $plan, string $connection): void
    {
        foreach ($plan['operations'] as $operation) {
            if (! in_array($operation['action'] ?? '', ['create', 'update'], true)) {
                continue;
            }

            $table = $this->sheetTable((string) $operation['entity']);
            $payload = $operation['payload'] ?? [];

            if (($operation['action'] ?? '') === 'create') {
                DB::connection($connection)->table($table)->insert($payload);

                continue;
            }

            DB::connection($connection)
                ->table($table)
                ->where($operation['where'] ?? ['id' => $operation['id'] ?? ''])
                ->update($payload);
        }
    }

    protected function backupTarget(string $connection): ?string
    {
        try {
            $directory = (string) ($this->option('backup-dir') ?: storage_path('app/backups'));
            File::ensureDirectoryExists($directory);

            $data = [
                'meta' => [
                    'backed_up_at' => now()->toIso8601String(),
                    'connection' => $connection,
                    'database' => DB::connection($connection)->getDatabaseName(),
                ],
                'prodi_mapping' => self::PRODI_MAP,
            ];

            foreach (array_merge(self::TABLES, ['study_programs']) as $table) {
                $data[$table] = DB::connection($connection)
                    ->table($this->sheetTable($table))
                    ->get()
                    ->map(function (object $row): array {
                        $item = (array) $row;
                        unset($item['_row_id']);

                        return $item;
                    })
                    ->all();
            }

            $path = $directory.DIRECTORY_SEPARATOR
                .'local-import-target-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.json';

            File::put(
                $path,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
            );

            if (DIRECTORY_SEPARATOR !== '\\') {
                @chmod($path, 0600);
            }

            $this->components->warn('Snapshot target berisi password_hash. Jangan commit file ini.');

            return $path;
        } catch (Throwable $exception) {
            $this->components->error('Backup snapshot target gagal: '.$exception->getMessage());

            return null;
        }
    }

    protected function renderTargetCounts(string $connection): void
    {
        $rows = [];

        foreach (array_merge(self::TABLES, ['study_programs']) as $table) {
            $rows[] = [$this->sheetTable($table), DB::connection($connection)->table($this->sheetTable($table))->count()];
        }

        $this->table(['Tabel', 'Jumlah setelah import'], $rows);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $row
     */
    protected function scheduleWarning(array &$plan, array $row, string $label): void
    {
        if ($this->isDeleted($row)) {
            return;
        }

        $mode = strtolower(trim((string) ($row['mode'] ?? '')));

        if ($mode === 'online' && trim((string) ($row['meeting_link'] ?? '')) === '') {
            $plan['warnings'][] = 'Jadwal ['.$label.'] mode online tanpa meeting_link.';
        }

        if ($mode === 'offline' && trim((string) ($row['room'] ?? '')) === '') {
            $plan['warnings'][] = 'Jadwal ['.$label.'] mode offline tanpa room.';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(string $connection, string $table): array
    {
        return DB::connection($connection)
            ->table($this->sheetTable($table))
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function indexBy(array $rows, callable $key): array
    {
        $index = [];

        foreach ($rows as $row) {
            $value = (string) $key($row);

            if ($value === '') {
                continue;
            }

            $index[$value][] = $row;
        }

        return $index;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, bool>
     */
    protected function usedIds(array $rows): array
    {
        $used = [];

        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');

            if ($id !== '') {
                $used[$id] = true;
            }
        }

        return $used;
    }

    /**
     * @param  array<string, bool>  $used
     */
    protected function availableId(string $preferred, array $used): string
    {
        if ($preferred !== '' && ! isset($used[$preferred])) {
            return $preferred;
        }

        do {
            $id = (string) Str::ulid();
        } while (isset($used[$id]));

        return $id;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $data
     */
    protected function add(array &$plan, string $entity, string $action, string $key, array $data = []): void
    {
        $plan['operations'][] = array_merge([
            'entity' => $entity,
            'action' => $action,
            'key' => $key,
        ], $data);
    }

    protected function sheetTable(string $name): string
    {
        return (string) config('sheets.mysql.table_prefix', 'sheet_').$name;
    }

    /**
     * @return array<int, string>
     */
    protected function splitIds(string $value): array
    {
        $ids = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn (string $id): bool => $id !== '',
        ));

        return array_values(array_unique($ids));
    }

    protected function normalizeEmail(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function normalizeNpm(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function normalizeActive(mixed $value): string
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    protected function isActive(mixed $value): bool
    {
        return $this->normalizeActive($value) === '1';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isDeleted(array $row): bool
    {
        return trim((string) ($row['deleted_at'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function courseKey(array $row): string
    {
        return $this->courseKeyFrom(
            (string) ($row['code'] ?? ''),
            $this->splitIds((string) ($row['prodi_ids'] ?? '')),
        );
    }

    /**
     * @param  array<int, string>  $prodiIds
     */
    protected function courseKeyFrom(string $code, array $prodiIds): string
    {
        sort($prodiIds, SORT_STRING);

        return strtolower(trim($code)).'|'.implode(',', $prodiIds);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function scheduleKey(array $row): string
    {
        return $this->scheduleKeyFrom((string) ($row['course_id'] ?? ''), $row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function scheduleKeyFrom(string $courseId, array $row): string
    {
        return implode('|', [
            trim($courseId),
            strtolower(trim((string) ($row['day'] ?? ''))),
            $this->normalizeTime((string) ($row['start_time'] ?? '')),
            $this->normalizeTime((string) ($row['end_time'] ?? '')),
            strtolower(trim((string) ($row['class_name'] ?? ''))),
        ]);
    }

    protected function normalizeTime(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches) === 1) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function scheduleLabel(array $row): string
    {
        return implode(' ', [
            trim((string) ($row['day'] ?? '')),
            trim((string) ($row['start_time'] ?? '')).'-'.trim((string) ($row['end_time'] ?? '')),
            trim((string) ($row['class_name'] ?? '')),
        ]);
    }

    protected function short(mixed $value): string
    {
        return Str::limit(str_replace(["\r", "\n"], ' ', (string) $value), 60);
    }
}
