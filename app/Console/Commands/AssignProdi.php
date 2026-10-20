<?php

namespace App\Console\Commands;

use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Illuminate\Console\Command;

class AssignProdi extends Command
{
    protected $signature = 'sheets:assign-prodi
                            {login? : Email atau NIM pengguna}
                            {--prodi= : Kode Prodi tujuan}';

    protected $description = 'Mengisi atau mengubah Prodi milik akun pengguna';

    public function handle(): int
    {
        $login = (string) ($this->argument('login') ?: $this->ask('Email atau NIM pengguna'));

        if (trim($login) === '') {
            $this->components->error('Email atau NIM wajib diisi.');

            return self::FAILURE;
        }

        $users = app(UserRepository::class);
        $login = mb_strtolower(trim($login));

        $user = $users->all(true)->first(function (array $user) use ($login): bool {
            return mb_strtolower(trim((string) ($user['email'] ?? ''))) === $login
                || mb_strtolower(trim((string) ($user['npm'] ?? ''))) === $login;
        });

        if (! $user) {
            $this->components->error('Pengguna tidak ditemukan.');

            return self::FAILURE;
        }

        $programs = app(StudyProgramRepository::class);
        $code = trim((string) $this->option('prodi'));
        $program = $code !== '' ? $programs->findByCode($code) : null;

        if ($code !== '' && ! $program) {
            $this->components->error("Prodi dengan kode [{$code}] tidak ditemukan.");

            return self::FAILURE;
        }

        if (! $program) {
            $options = $programs->allOptions();

            if ($options === []) {
                $this->components->error('Belum ada Prodi. Jalankan php artisan sheets:setup terlebih dahulu.');

                return self::FAILURE;
            }

            $defaultProgram = array_values($options)[0];
            $selectedProgram = (string) $this->choice('Pilih Prodi', array_values($options), $defaultProgram);
            $programId = array_search($selectedProgram, $options, true);

            if ($programId === false) {
                $this->components->error('Prodi tidak valid.');

                return self::FAILURE;
            }

            $program = $programs->find((string) $programId);
        }

        if (! $program) {
            $this->components->error('Prodi tidak ditemukan.');

            return self::FAILURE;
        }

        $users->update((string) $user['id'], ['prodi_id' => (string) $program['id']]);

        $this->components->info(
            'Prodi '.($user['email'] ?? $user['name']).' diatur ke '.$program['name'].'.',
        );

        if (! $programs->isActive($program)) {
            $this->components->warn('Prodi tersebut nonaktif; pengguna belum bisa login sampai Prodi diaktifkan.');
        }

        if (trim((string) ($user['password_hash'] ?? '')) === '') {
            $this->components->warn('Akun belum memiliki password.');
        }

        return self::SUCCESS;
    }
}
