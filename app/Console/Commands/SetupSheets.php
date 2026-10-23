<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Sheets\SpreadsheetGateway;
use App\Support\DemoData;
use App\Support\ProdiAssignments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Throwable;

class SetupSheets extends Command
{
    protected $signature = 'sheets:setup
                            {--demo : Isi spreadsheet dengan data contoh}';

    protected $description = 'Membuat tab spreadsheet dan akun admin awal';

    public function handle(SpreadsheetGateway $gateway): int
    {
        try {
            foreach ((array) config('sheets.tabs', []) as $tab => $headers) {
                $created = $gateway->ensureSheet($tab, $headers);

                $this->ensureHeaders($gateway, $tab, $headers);

                $this->line(($created ? 'Dibuat: ' : 'OK: ').$tab);
            }
        } catch (Throwable $exception) {
            $this->components->error('Gagal menyiapkan spreadsheet: '.$exception->getMessage());

            return self::FAILURE;
        }

        $defaultProgram = $this->ensureDefaultProgram();

        $users = app(UserRepository::class);

        $migrated = $this->migrateLegacyRoles($users);

        if ($migrated > 0) {
            $this->line('Role dimigrasikan: '.$migrated.' akun admin menjadi Super Admin.');
        }

        $migratedCourses = $this->migrateCoursePrograms(
            app(CourseRepository::class),
            (string) $defaultProgram['id'],
        );

        if ($migratedCourses > 0) {
            $this->line('Mata kuliah dimigrasikan ke Prodi default: '.$migratedCourses.' matkul.');
        }

        if ($this->option('demo')) {
            $counts = app(DemoData::class)->seed();

            foreach ($counts as $label => $count) {
                $this->line(ucfirst($label).': '.$count);
            }

            $this->newLine();
            $this->components->info('Data demo siap.');
            $this->line('Super Admin: admin@psikologi.test / password');
            $this->line('Admin      : pjmk@psikologi.test / password');
            $this->line('Mahasiswa  : ayu@psikologi.test / password');
        } elseif ($users->admins()->isEmpty()) {
            $admin = $this->createAdmin();

            if ($admin === null) {
                return self::FAILURE;
            }

            $this->components->info('Akun Super Admin dibuat.');
        } else {
            $this->components->info('Akun admin sudah tersedia.');
            $this->warnAdminsWithoutProdi($users);
        }

        $this->newLine();
        $this->call('sheets:check');

        return self::SUCCESS;
    }

    protected function ensureHeaders(SpreadsheetGateway $gateway, string $tab, array $headers): void
    {
        $rows = $gateway->read($tab);

        if ($rows === []) {
            return;
        }

        $current = array_values(array_filter(
            array_map(fn (mixed $header): string => trim((string) $header), (array) $rows[0]),
            fn (string $header): bool => $header !== '',
        ));

        $missing = array_values(array_diff($headers, $current));

        if ($missing === []) {
            return;
        }

        $gateway->updateRow($tab, 1, array_merge($current, $missing));

        Cache::forget("sheet:{$tab}");

        $this->line('Header ditambahkan: '.$tab.' ('.implode(', ', $missing).')');
    }

    /**
     * @return array<string, mixed>
     */
    protected function ensureDefaultProgram(): array
    {
        $programs = app(StudyProgramRepository::class);
        $existing = $programs->findByCode('psikologi');

        if ($existing) {
            return $existing;
        }

        $program = $programs->create([
            'code' => 'psikologi',
            'name' => 'Psikologi',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $this->line('Dibuat Prodi: S1 Psikologi');

        return $program;
    }

    protected function migrateCoursePrograms(CourseRepository $courses, string $defaultProgramId): int
    {
        $migrated = 0;

        foreach ($courses->all(true) as $course) {
            if (ProdiAssignments::ids($course['prodi_ids'] ?? '') !== []) {
                continue;
            }

            $courses->update((string) $course['id'], ['prodi_ids' => $defaultProgramId]);

            $migrated++;
        }

        return $migrated;
    }

    protected function migrateLegacyRoles(UserRepository $users): int
    {
        $legacyAdmins = $users->all(true)->filter(
            fn (array $user): bool => mb_strtolower(trim((string) ($user['role'] ?? ''))) === 'admin',
        );

        foreach ($legacyAdmins as $user) {
            $users->update((string) $user['id'], ['role' => UserRole::SuperAdmin->value]);
        }

        return $legacyAdmins->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function createAdmin(): ?array
    {
        $programs = app(StudyProgramRepository::class)->activeOptions();

        if ($programs === []) {
            $this->components->error('Belum ada Prodi aktif. Tambahkan Prodi terlebih dahulu.');

            return null;
        }

        $name = (string) $this->ask('Nama Super Admin', 'Admin Program Studi');
        $email = (string) $this->ask('Email Super Admin', 'admin@psikologi.test');
        $npm = (string) $this->ask('NIM Super Admin', 'ADMIN001');
        $password = (string) ($this->secret('Password Super Admin (minimal 8 karakter)') ?: 'password');

        $defaultProgram = array_values($programs)[0];
        $selectedProgram = (string) $this->choice('Prodi Super Admin', array_values($programs), $defaultProgram);
        $prodiId = array_search($selectedProgram, $programs, true);

        if ($prodiId === false) {
            $this->components->error('Prodi Super Admin tidak valid.');

            return null;
        }

        if (mb_strlen($password) < 8) {
            $this->components->error('Password minimal 8 karakter.');

            return null;
        }

        $users = app(UserRepository::class);

        if ($users->findActiveByLogin($email) || $users->findActiveByLogin($npm)) {
            $this->components->error('Email atau NIM sudah digunakan.');

            return null;
        }

        return $users->create([
            'name' => $name,
            'email' => $email,
            'npm' => $npm,
            'password_hash' => Hash::make($password),
            'role' => UserRole::SuperAdmin->value,
            'prodi_id' => (string) $prodiId,
            'class_name' => '',
            'cohort' => '',
            'phone' => '',
            'active' => '1',
        ]);
    }

    protected function warnAdminsWithoutProdi(UserRepository $users): void
    {
        $missing = $users->admins()
            ->filter(fn (array $admin): bool => trim((string) ($admin['prodi_id'] ?? '')) === '');

        if ($missing->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->components->warn('Sebagian admin belum memiliki Prodi dan belum bisa login:');

        foreach ($missing as $admin) {
            $this->line('- '.($admin['email'] ?? $admin['name'] ?? '-'));
        }

        $this->line('Jalankan: php artisan sheets:assign-prodi <email-admin> --prodi=psikologi');
    }
}
