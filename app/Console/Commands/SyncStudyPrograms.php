<?php

namespace App\Console\Commands;

use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class SyncStudyPrograms extends Command
{
    protected $signature = 'sheets:sync-study-programs
                            {--dry-run : Tampilkan rencana tanpa mengubah data}
                            {--backup-dir= : Direktori penyimpanan backup JSON}';

    protected $description = 'Sinkronkan empat program studi target tanpa menghapus data lain';

    /**
     * @var array<int, array<string, mixed>>
     */
    protected const TARGETS = [
        [
            'code' => 'psi',
            'name' => 'Psikologi Islam',
            'degree' => 'S1',
            'reference_id' => '01M60CY00TAFA0HV21NX7JFNDF',
            'legacy_codes' => ['psikologi'],
            'legacy_names' => ['psikologi'],
        ],
        [
            'code' => 'hki',
            'name' => 'Hukum Keluarga Islam',
            'degree' => 'S1',
            'reference_id' => '01M60M042VB05KZ8D99P9D6MJE',
        ],
        [
            'code' => 'es',
            'name' => "Ekonomi Syari'ah",
            'degree' => 'S1',
            'reference_id' => '01M60M043GTP41WPV6GWJ61FHH',
        ],
        [
            'code' => 'pgmi',
            'name' => 'Pendidikan Guru Madrasah Ibtidaiyah',
            'degree' => 'S1',
            'reference_id' => '01M60M043QTPAWJT4PQJ4HQWE5',
        ],
    ];

    public function handle(
        StudyProgramRepository $programs,
        UserRepository $users,
        CourseRepository $courses,
        AnnouncementRepository $announcements,
    ): int {
        $programs->flushCache();
        $users->flushCache();
        $courses->flushCache();
        $announcements->flushCache();

        $existing = $programs->all(true);
        $resolution = $this->resolve($existing);

        if ($resolution['errors'] !== []) {
            foreach ($resolution['errors'] as $error) {
                $this->components->error($error);
            }

            $this->components->warn('Tidak ada perubahan yang dilakukan.');

            return self::FAILURE;
        }

        $targets = $resolution['targets'];
        $resolvedIds = array_map(fn (array $item): string => $item['id'], $targets);

        $extraActive = $programs->active()->reject(
            fn (array $program): bool => in_array((string) ($program['id'] ?? ''), $resolvedIds, true),
        );

        if ($extraActive->isNotEmpty()) {
            $this->components->error('Ditemukan Prodi aktif di luar target:');
            $this->table(
                ['Kode', 'Nama', 'ID'],
                $extraActive->map(fn (array $program): array => [
                    (string) ($program['code'] ?? ''),
                    (string) ($program['name'] ?? ''),
                    (string) ($program['id'] ?? ''),
                ])->all(),
            );
            $this->components->warn('Nonaktifkan Prodi tersebut secara manual atau tambahkan ke target. Tidak ada perubahan yang dilakukan.');

            return self::FAILURE;
        }

        $plans = [];

        foreach ($targets as $item) {
            $plans[] = array_merge($item, $this->plan($programs, $item));
        }

        if ((bool) $this->option('dry-run')) {
            $this->render($plans, $users, $courses, $announcements);
            $this->renderMapping($plans);
            $this->newLine();
            $this->components->info('Dry-run: tidak ada perubahan data. Backup akan dibuat sebelum perubahan sebenarnya.');

            return self::SUCCESS;
        }

        $backupPath = $this->backup($existing);

        if ($backupPath === null) {
            return self::FAILURE;
        }

        $this->components->info('Backup tabel study_programs: '.$backupPath);

        try {
            foreach ($plans as $index => $plan) {
                $data = [
                    'code' => $plan['target']['code'],
                    'name' => $plan['target']['name'],
                    'degree' => $plan['target']['degree'],
                    'active' => '1',
                ];

                if ($plan['existing'] === null) {
                    $programs->create(array_merge($data, ['id' => $plan['id']]));
                    $plans[$index]['action'] = 'dibuat';
                } else {
                    if ($plan['restore']) {
                        $data['deleted_at'] = '';
                    }

                    if ($plan['restore'] || $plan['changes'] !== []) {
                        $programs->update($plan['id'], $data);
                    }

                    $plans[$index]['action'] = $plan['action'];
                }

                $plans[$index]['record'] = $programs->find($plan['id'], true);
            }
        } catch (Throwable $exception) {
            $this->components->error('Sinkronisasi gagal: '.$exception->getMessage());
            $this->components->warn('Backup tersedia di '.$backupPath.'. Periksa data sebelum mengulang.');

            return self::FAILURE;
        }

        $this->render($plans, $users, $courses, $announcements);
        $this->renderMapping($plans);

        if (! $this->verify($programs)) {
            return self::FAILURE;
        }

        $this->components->info('Sinkronisasi 4 Prodi selesai. Jalankan php artisan cache:clear bila endpoint publik masih menampilkan data lama.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $programs
     * @return array{targets: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    protected function resolve(Collection $programs): array
    {
        $targets = [];
        $errors = [];

        foreach (self::TARGETS as $target) {
            $candidates = $this->candidates($programs, $target);
            $unique = $candidates->keyBy(fn (array $program): string => (string) ($program['id'] ?? ''))->values();

            if ($unique->count() > 1) {
                $errors[] = sprintf(
                    'Konflik Prodi [%s]: ditemukan beberapa record (%s).',
                    $target['code'],
                    $unique->map(fn (array $program): string => sprintf(
                        '%s / %s [%s]',
                        (string) ($program['code'] ?? '-'),
                        (string) ($program['name'] ?? '-'),
                        (string) ($program['id'] ?? '-'),
                    ))->implode('; '),
                );

                continue;
            }

            $existing = $unique->first();
            $id = $existing !== null
                ? (string) $existing['id']
                : $this->availableId($programs, (string) $target['reference_id']);

            $targets[] = [
                'target' => $target,
                'existing' => $existing,
                'id' => $id,
                'reference_id' => (string) $target['reference_id'],
            ];
        }

        $used = [];

        foreach ($targets as $item) {
            $id = $item['id'];

            if (isset($used[$id])) {
                $errors[] = sprintf(
                    'Record [%s] dipakai untuk lebih dari satu Prodi target (%s dan %s).',
                    $id,
                    $used[$id],
                    $item['target']['code'],
                );

                continue;
            }

            $used[$id] = $item['target']['code'];
        }

        return ['targets' => $targets, 'errors' => $errors];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $programs
     * @param  array<string, mixed>  $target
     * @return Collection<int, array<string, mixed>>
     */
    protected function candidates(Collection $programs, array $target): Collection
    {
        $code = $this->normalize((string) $target['code']);
        $name = $this->normalize((string) $target['name']);
        $legacyCodes = array_map(fn (string $value): string => $this->normalize($value), (array) ($target['legacy_codes'] ?? []));
        $legacyNames = array_map(fn (string $value): string => $this->normalize($value), (array) ($target['legacy_names'] ?? []));
        $referenceId = (string) $target['reference_id'];

        return $programs->filter(function (array $program) use ($code, $name, $legacyCodes, $legacyNames, $referenceId): bool {
            $programCode = $this->normalize((string) ($program['code'] ?? ''));
            $programName = $this->normalize((string) ($program['name'] ?? ''));
            $programId = (string) ($program['id'] ?? '');

            if ($programCode === $code || $programName === $name) {
                return true;
            }

            if (in_array($programCode, $legacyCodes, true) || in_array($programName, $legacyNames, true)) {
                return true;
            }

            if ($programId === $referenceId) {
                return $programCode === '' && $programName === '';
            }

            return false;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $programs
     */
    protected function availableId(Collection $programs, string $referenceId): string
    {
        if (
            $referenceId !== ''
            && ! $programs->contains(fn (array $program): bool => (string) ($program['id'] ?? '') === $referenceId)
        ) {
            return $referenceId;
        }

        do {
            $id = (string) Str::ulid();
        } while ($programs->contains(fn (array $program): bool => (string) ($program['id'] ?? '') === $id));

        return $id;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{action: string, changes: array<string, string>, restore: bool}
     */
    protected function plan(StudyProgramRepository $programs, array $item): array
    {
        $target = $item['target'];
        $existing = $item['existing'];

        if ($existing === null) {
            return ['action' => 'dibuat', 'changes' => [], 'restore' => false];
        }

        $changes = [];

        if ($this->normalize((string) ($existing['code'] ?? '')) !== $this->normalize((string) $target['code'])) {
            $changes['code'] = (string) $target['code'];
        }

        if (trim((string) ($existing['name'] ?? '')) !== trim((string) $target['name'])) {
            $changes['name'] = (string) $target['name'];
        }

        if (trim((string) ($existing['degree'] ?? '')) !== trim((string) $target['degree'])) {
            $changes['degree'] = (string) $target['degree'];
        }

        if (! $programs->isActive($existing)) {
            $changes['active'] = '1';
        }

        $restore = trim((string) ($existing['deleted_at'] ?? '')) !== '';
        $action = $restore ? 'dipulihkan' : ($changes === [] ? 'tetap' : 'diperbarui');

        return ['action' => $action, 'changes' => $changes, 'restore' => $restore];
    }

    /**
     * @param  array<int, array<string, mixed>>  $plans
     */
    protected function render(
        array $plans,
        UserRepository $users,
        CourseRepository $courses,
        AnnouncementRepository $announcements,
    ): void {
        $rows = [];

        foreach ($plans as $plan) {
            $id = (string) $plan['id'];
            $target = $plan['target'];

            $rows[] = [
                $target['code'],
                $target['name'],
                $target['degree'],
                $plan['action'],
                $id,
                $plan['reference_id'],
                $users->countByProdi($id, true),
                $courses->countByProdi($id, true),
                $announcements->countByProdi($id, true),
            ];
        }

        $this->table(
            ['Kode', 'Nama', 'Jenjang', 'Aksi', 'ID Publik', 'ID Referensi', 'Akun', 'Matkul', 'Pengumuman'],
            $rows,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $plans
     */
    protected function renderMapping(array $plans): void
    {
        $this->newLine();
        $this->line('Mapping ID referensi lokal ke ID publik:');
        $this->table(
            ['Kode', 'ID Referensi Lokal', 'ID Publik', 'Status'],
            array_map(fn (array $plan): array => [
                $plan['target']['code'],
                $plan['reference_id'],
                $plan['id'],
                $plan['action'],
            ], $plans),
        );
        $this->line('Untuk Prodi baru, ID publik memakai ID referensi lokal bila belum dipakai. Jika berbeda, ID referensi sudah dipakai record lain atau record existing dengan kode yang sama dipulihkan.');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $programs
     */
    protected function backup(Collection $programs): ?string
    {
        $directory = trim((string) $this->option('backup-dir'));

        if ($directory === '') {
            $directory = storage_path('app/backups');
        }

        $directory = rtrim($directory, '/\\');

        try {
            File::ensureDirectoryExists($directory);

            $path = $directory.DIRECTORY_SEPARATOR.'study-programs-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.json';

            $payload = [
                'exported_at' => now()->toIso8601String(),
                'driver' => (string) config('sheets.driver'),
                'database' => $this->databaseName(),
                'count' => $programs->count(),
                'programs' => $programs
                    ->map(fn (array $program): array => Arr::except($program, ['_row']))
                    ->values()
                    ->all(),
            ];

            $written = File::put(
                $path,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
            );

            if ($written === false) {
                $this->components->error('Gagal menulis backup ke '.$path);

                return null;
            }

            return $path;
        } catch (Throwable $exception) {
            $this->components->error('Gagal membuat backup: '.$exception->getMessage());

            return null;
        }
    }

    protected function databaseName(): ?string
    {
        if (config('sheets.driver') !== 'mysql') {
            return null;
        }

        try {
            return DB::connection(config('sheets.mysql.connection'))->getDatabaseName();
        } catch (Throwable) {
            return null;
        }
    }

    protected function verify(StudyProgramRepository $programs): bool
    {
        $active = $programs->active();
        $codes = $active
            ->map(fn (array $program): string => $this->normalize((string) ($program['code'] ?? '')))
            ->sort()
            ->values()
            ->all();
        $expected = collect(self::TARGETS)
            ->map(fn (array $target): string => $this->normalize((string) $target['code']))
            ->sort()
            ->values()
            ->all();

        if ($codes !== $expected) {
            $this->components->error(
                'Verifikasi gagal: Prodi aktif harus tepat '.implode(', ', $expected)
                .'. Ditemukan: '.($codes === [] ? '(kosong)' : implode(', ', $codes)).'.',
            );

            return false;
        }

        $valid = true;

        foreach (self::TARGETS as $target) {
            $program = $programs->findByCode((string) $target['code']);

            if ($program === null) {
                $this->components->error("Prodi [{$target['code']}] tidak ditemukan setelah sinkronisasi.");
                $valid = false;

                continue;
            }

            if (
                trim((string) ($program['name'] ?? '')) !== (string) $target['name']
                || trim((string) ($program['degree'] ?? '')) !== (string) $target['degree']
                || ! $programs->isActive($program)
            ) {
                $label = trim($target['degree'].' '.$target['name']);
                $this->components->error("Data Prodi [{$target['code']}] tidak sesuai target (label: {$label}).");
                $valid = false;
            }
        }

        if ($valid) {
            $this->components->info('Verifikasi OK: '.$active->count().' Prodi aktif dengan kode '.implode(', ', $codes).'.');
        }

        return $valid;
    }

    protected function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
