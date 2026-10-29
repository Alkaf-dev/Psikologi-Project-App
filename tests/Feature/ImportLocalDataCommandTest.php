<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportLocalDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PRODI_MAPPING = [
        '01M60CY00TAFA0HV21NX7JFNDF' => '01M5V8C4V913P5WE4PQ8TAGQNW',
        '01M60M042VB05KZ8D99P9D6MJE' => '01M641HHGBEDB4ABM7ANGBF93B',
        '01M60M043GTP41WPV6GWJ61FHH' => '01M641HX8KX4NWVF0RZ9607VFN',
        '01M60M043QTPAWJT4PQJ4HQWE5' => '01M641JD9ZTCHG1GB5MPQXKBQW',
    ];

    private const PSI = '01M5V8C4V913P5WE4PQ8TAGQNW';

    private const HKI = '01M641HHGBEDB4ABM7ANGBF93B';

    private const ES = '01M641HX8KX4NWVF0RZ9607VFN';

    private const PGMI = '01M641JD9ZTCHG1GB5MPQXKBQW';

    /** @var array<int, string> */
    private array $sourceFiles = [];

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPrograms();
        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'psikologi-import-backup-'.uniqid();
    }

    protected function tearDown(): void
    {
        foreach ($this->sourceFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        if (File::isDirectory($this->backupDir)) {
            File::deleteDirectory($this->backupDir);
        }

        parent::tearDown();
    }

    public function test_dry_run_reports_counts_without_writing_data(): void
    {
        $source = $this->sourceFile($this->baseSource());

        $this->assertSame(0, Artisan::call('sheets:import-local-data', [
            '--source' => $source,
            '--dry-run' => true,
            '--json' => true,
        ]));

        $report = json_decode(Artisan::output(), true);

        $this->assertIsArray($report);
        $this->assertTrue($report['dry_run']);
        $this->assertSame(2, $report['summary']['users']['create']);
        $this->assertSame(1, $report['summary']['users']['skip']);
        $this->assertSame(0, $report['summary']['users']['conflict']);
        $this->assertSame(2, $report['summary']['courses']['create']);
        $this->assertSame(2, $report['summary']['schedules']['create']);
        $this->assertSame(2, $report['summary']['settings']['create']);
        $this->assertSame(7, $report['skipped']['api_tokens']);
        $this->assertSame(1, $report['skipped']['super_admin']);
        $this->assertSame([], $report['warnings']);

        $this->assertSame(0, DB::table('sheet_users')->count());
        $this->assertSame(0, DB::table('sheet_courses')->count());
        $this->assertSame(0, DB::table('sheet_schedules')->count());
        $this->assertSame(0, DB::table('sheet_settings')->count());
        $this->assertFalse(File::isDirectory($this->backupDir));
    }

    public function test_execute_imports_mapped_data_preserves_passwords_and_is_idempotent(): void
    {
        $source = $this->sourceFile($this->baseSource());

        $this->artisan('sheets:import-local-data', [
            '--source' => $source,
            '--execute' => true,
            '--backup-confirmed' => true,
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $this->assertSame(2, DB::table('sheet_users')->count());
        $this->assertSame(2, DB::table('sheet_courses')->count());
        $this->assertSame(2, DB::table('sheet_schedules')->count());
        $this->assertSame(2, DB::table('sheet_settings')->count());
        $this->assertSame(4, DB::table('sheet_study_programs')->count());

        $student = DB::table('sheet_users')->where('email', 'ayu@test.local')->first();
        $this->assertNotNull($student);
        $this->assertSame('hash-ayu', (string) $student->password_hash);
        $this->assertSame(self::PSI, (string) $student->prodi_id);
        $this->assertSame('mahasiswa', (string) $student->role);

        $pjmk = DB::table('sheet_users')->where('email', 'budi@test.local')->first();
        $this->assertNotNull($pjmk);
        $this->assertSame(self::HKI, (string) $pjmk->prodi_id);
        $this->assertSame('pjmk', (string) $pjmk->role);

        $this->assertSame(0, DB::table('sheet_users')->where('role', 'super_admin')->count());

        $course = DB::table('sheet_courses')->where('code', 'AIK101')->first();
        $this->assertNotNull($course);
        $this->assertSame(self::PSI.','.self::HKI.','.self::ES, (string) $course->prodi_ids);
        $this->assertSame('', (string) $course->deleted_at);

        $deletedCourse = DB::table('sheet_courses')->where('code', 'HKI101')->first();
        $this->assertNotNull($deletedCourse);
        $this->assertNotSame('', (string) $deletedCourse->deleted_at);

        $schedule = DB::table('sheet_schedules')->where('course_id', (string) $course->id)->first();
        $this->assertNotNull($schedule);
        $this->assertSame('', (string) $schedule->deleted_at);

        $deletedSchedule = DB::table('sheet_schedules')->where('course_id', (string) $deletedCourse->id)->first();
        $this->assertNotNull($deletedSchedule);
        $this->assertNotSame('', (string) $deletedSchedule->deleted_at);

        $this->assertSame('Psikologi Test', (string) DB::table('sheet_settings')->where('key', 'site_name')->value('value'));

        $files = File::files($this->backupDir);
        $this->assertCount(1, $files);

        $backup = json_decode(File::get($files[0]->getPathname()), true);
        $this->assertSame(0, count($backup['users']));
        $this->assertSame(4, count($backup['study_programs']));
        $this->assertArrayNotHasKey('_row_id', $backup['users'][0] ?? []);

        $this->assertSame(0, Artisan::call('sheets:import-local-data', [
            '--source' => $source,
            '--dry-run' => true,
            '--json' => true,
        ]));

        $report = json_decode(Artisan::output(), true);
        $this->assertSame(0, $report['summary']['users']['create']);
        $this->assertSame(0, $report['summary']['users']['update']);
        $this->assertSame(3, $report['summary']['users']['skip']);
        $this->assertSame(0, $report['summary']['courses']['create']);
        $this->assertSame(0, $report['summary']['courses']['update']);
        $this->assertSame(0, $report['summary']['schedules']['create']);
        $this->assertSame(0, $report['summary']['schedules']['update']);
        $this->assertSame(0, $report['summary']['settings']['create']);
        $this->assertSame(0, $report['summary']['settings']['update']);
        $this->assertSame([], $report['conflicts']);
    }

    public function test_existing_target_password_and_admin_are_not_overwritten(): void
    {
        DB::table('sheet_users')->insert([
            $this->targetUser('target-ayu', 'Ayu Lama', 'ayu@test.local', '2026001', 'hash-target-ayu', 'mahasiswa', self::PSI),
            $this->targetUser('target-admin', 'Admin Publik', 'admin@test.local', '', 'hash-target-admin', 'super_admin', self::PSI),
        ]);

        $source = $this->sourceFile($this->baseSource());

        $this->artisan('sheets:import-local-data', [
            '--source' => $source,
            '--execute' => true,
            '--backup-confirmed' => true,
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $ayu = DB::table('sheet_users')->where('id', 'target-ayu')->first();
        $this->assertSame('hash-target-ayu', (string) $ayu->password_hash);
        $this->assertSame('Ayu', (string) $ayu->name);
        $this->assertSame(self::PSI, (string) $ayu->prodi_id);

        $admin = DB::table('sheet_users')->where('id', 'target-admin')->first();
        $this->assertSame('hash-target-admin', (string) $admin->password_hash);
        $this->assertSame('Admin Publik', (string) $admin->name);
        $this->assertSame('super_admin', (string) $admin->role);

        $this->assertSame(3, DB::table('sheet_users')->count());
    }

    public function test_conflicts_abort_without_writing_and_skip_conflicts_imports_the_rest(): void
    {
        DB::table('sheet_users')->insert([
            $this->targetUser('target-ayu', 'Ayu Lama', 'ayu@test.local', '9999999', 'hash-target-ayu', 'mahasiswa', self::PSI),
        ]);
        DB::table('sheet_courses')->insert([
            $this->targetCourse('target-aik', 'AIK101', 'AIK Target', self::PSI),
        ]);

        $source = $this->sourceFile($this->baseSource());

        $this->artisan('sheets:import-local-data', [
            '--source' => $source,
            '--execute' => true,
            '--backup-confirmed' => true,
            '--backup-dir' => $this->backupDir,
        ])->assertExitCode(1);

        $this->assertSame(1, DB::table('sheet_users')->count());
        $this->assertSame(1, DB::table('sheet_courses')->count());
        $this->assertSame(0, DB::table('sheet_schedules')->count());
        $this->assertSame(0, DB::table('sheet_settings')->count());
        $this->assertFalse(File::isDirectory($this->backupDir));

        $this->artisan('sheets:import-local-data', [
            '--source' => $source,
            '--execute' => true,
            '--backup-confirmed' => true,
            '--skip-conflicts' => true,
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $this->assertSame(2, DB::table('sheet_users')->count());
        $this->assertSame(2, DB::table('sheet_courses')->count());
        $this->assertSame(1, DB::table('sheet_schedules')->count());
        $this->assertSame(2, DB::table('sheet_settings')->count());

        $ayu = DB::table('sheet_users')->where('id', 'target-ayu')->first();
        $this->assertSame('9999999', (string) $ayu->npm);
        $this->assertSame('hash-target-ayu', (string) $ayu->password_hash);

        $aik = DB::table('sheet_courses')->where('id', 'target-aik')->first();
        $this->assertSame('AIK Target', (string) $aik->name);
        $this->assertSame(self::PSI, (string) $aik->prodi_ids);

        $this->assertNotNull(DB::table('sheet_users')->where('email', 'budi@test.local')->first());
        $this->assertNotNull(DB::table('sheet_courses')->where('code', 'HKI101')->first());
        $this->assertCount(1, File::files($this->backupDir));
    }

    public function test_execute_requires_backup_confirmation(): void
    {
        $source = $this->sourceFile($this->baseSource());

        $this->artisan('sheets:import-local-data', [
            '--source' => $source,
            '--execute' => true,
            '--backup-dir' => $this->backupDir,
        ])->assertExitCode(1);

        $this->assertSame(0, DB::table('sheet_users')->count());
        $this->assertFalse(File::isDirectory($this->backupDir));
    }

    public function test_online_schedule_without_meeting_link_produces_a_warning(): void
    {
        $source = $this->baseSource();
        $source['schedules'][] = [
            'id' => 'schedule-online',
            'course_id' => 'course-aik',
            'day' => 'Rabu',
            'start_time' => '13:00',
            'end_time' => '14:40',
            'room' => 'R3',
            'class_name' => 'A',
            'created_at' => '2026-10-01T00:00:00+07:00',
            'updated_at' => '2026-10-01T00:00:00+07:00',
            'deleted_at' => '',
            'mode' => 'online',
            'platform' => 'Zoom',
            'meeting_link' => '',
            'attendance_link' => '',
        ];

        $path = $this->sourceFile($source);

        $this->assertSame(0, Artisan::call('sheets:import-local-data', [
            '--source' => $path,
            '--dry-run' => true,
            '--json' => true,
        ]));

        $report = json_decode(Artisan::output(), true);

        $this->assertCount(1, $report['warnings']);
        $this->assertStringContainsString('meeting_link', $report['warnings'][0]);
    }

    private function seedPrograms(): void
    {
        DB::table('sheet_study_programs')->insert([
            $this->program('psi', 'Psikologi Islam', self::PSI),
            $this->program('hki', 'Hukum Keluarga Islam', self::HKI),
            $this->program('es', 'Ekonomi Syariah', self::ES),
            $this->program('pgmi', 'Pendidikan Guru Madrasah Ibtidaiyah', self::PGMI),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function program(string $code, string $name, string $id): array
    {
        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'degree' => 'S1',
            'active' => '1',
            'created_at' => '2026-10-29T21:38:00+07:00',
            'updated_at' => '2026-10-29T21:38:00+07:00',
            'deleted_at' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function targetUser(string $id, string $name, string $email, string $npm, string $hash, string $role, string $prodiId): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'npm' => $npm,
            'password_hash' => $hash,
            'role' => $role,
            'class_name' => '',
            'cohort' => '',
            'phone' => '',
            'active' => '1',
            'created_at' => '2026-10-01T00:00:00+07:00',
            'updated_at' => '2026-10-01T00:00:00+07:00',
            'deleted_at' => '',
            'prodi_id' => $prodiId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function targetCourse(string $id, string $code, string $name, string $prodiIds): array
    {
        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'credits' => '2',
            'lecturer' => 'Dosen',
            'semester' => '1',
            'class_name' => 'A',
            'description' => '',
            'created_at' => '2026-10-01T00:00:00+07:00',
            'updated_at' => '2026-10-01T00:00:00+07:00',
            'deleted_at' => '',
            'prodi_ids' => $prodiIds,
            'meeting_link' => '',
            'attendance_link' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSource(): array
    {
        return [
            'meta' => [
                'counts' => [
                    'api_tokens' => 7,
                    'groups' => 0,
                    'tasks' => 0,
                    'submissions' => 0,
                    'announcements' => 0,
                ],
            ],
            'prodi_mapping' => self::PRODI_MAPPING,
            'users' => [
                [
                    'id' => 'user-ayu',
                    'name' => 'Ayu',
                    'email' => 'ayu@test.local',
                    'npm' => '2026001',
                    'password_hash' => 'hash-ayu',
                    'role' => 'mahasiswa',
                    'class_name' => 'PSI-A',
                    'cohort' => '2026',
                    'phone' => '0811',
                    'active' => '1',
                    'created_at' => '2026-10-01T00:00:00+07:00',
                    'updated_at' => '2026-10-01T00:00:00+07:00',
                    'deleted_at' => '',
                    'prodi_id' => '01M60CY00TAFA0HV21NX7JFNDF',
                ],
                [
                    'id' => 'user-budi',
                    'name' => 'Budi',
                    'email' => 'budi@test.local',
                    'npm' => '1987001',
                    'password_hash' => 'hash-budi',
                    'role' => 'pjmk',
                    'class_name' => '',
                    'cohort' => '',
                    'phone' => '',
                    'active' => '1',
                    'created_at' => '2026-10-01T00:00:00+07:00',
                    'updated_at' => '2026-10-01T00:00:00+07:00',
                    'deleted_at' => '',
                    'prodi_id' => '01M60M042VB05KZ8D99P9D6MJE',
                ],
                [
                    'id' => 'user-admin',
                    'name' => 'Admin Lokal',
                    'email' => 'admin@test.local',
                    'npm' => '',
                    'password_hash' => 'hash-admin',
                    'role' => 'super_admin',
                    'class_name' => '',
                    'cohort' => '',
                    'phone' => '',
                    'active' => '1',
                    'created_at' => '2026-10-01T00:00:00+07:00',
                    'updated_at' => '2026-10-01T00:00:00+07:00',
                    'deleted_at' => '',
                    'prodi_id' => '01M60CY00TAFA0HV21NX7JFNDF',
                ],
            ],
            'courses' => [
                [
                    'id' => 'course-aik',
                    'code' => 'AIK101',
                    'name' => 'Aqidah',
                    'credits' => '2',
                    'lecturer' => 'Dosen',
                    'semester' => '1',
                    'class_name' => 'A',
                    'description' => '',
                    'created_at' => '2026-10-01T00:00:00+07:00',
                    'updated_at' => '2026-10-01T00:00:00+07:00',
                    'deleted_at' => '',
                    'prodi_ids' => '01M60CY00TAFA0HV21NX7JFNDF,01M60M042VB05KZ8D99P9D6MJE,01M60M043GTP41WPV6GWJ61FHH',
                    'meeting_link' => 'https://meet.example/aik',
                    'attendance_link' => '',
                ],
                [
                    'id' => 'course-hki-old',
                    'code' => 'HKI101',
                    'name' => 'HKI Lama',
                    'credits' => '2',
                    'lecturer' => 'Dosen',
                    'semester' => '1',
                    'class_name' => 'B',
                    'description' => '',
                    'created_at' => '2026-10-01T00:00:00+07:00',
                    'updated_at' => '2026-10-01T00:00:00+07:00',
                    'deleted_at' => '2026-10-28T14:42:14+07:00',
                    'prodi_ids' => '01M60M042VB05KZ8D99P9D6MJE',
                    'meeting_link' => '',
                    'attendance_link' => '',
                ],
            ],
            'schedules' => [
                [
                    'id' => 'schedule-aik',
                    'course_id' => 'course-aik',
                    'day' => 'Senin',
                    'start_time' => '08:00',
                    'end_time' => '09:40',
                    'room' => 'R1',
                    'class_name' => 'A',
                    'created_at' => '2026-10-01T00:00:00+07:00',
                    'updated_at' => '2026-10-01T00:00:00+07:00',
                    'deleted_at' => '',
                    'mode' => 'online',
                    'platform' => 'Zoom',
                    'meeting_link' => 'https://zoom.example/aik',
                    'attendance_link' => '',
                ],
                [
                    'id' => 'schedule-hki-old',
                    'course_id' => 'course-hki-old',
                    'day' => 'Selasa',
                    'start_time' => '10:00',
                    'end_time' => '11:40',
                    'room' => 'R2',
                    'class_name' => 'B',
                    'created_at' => '2026-10-01T00:00:00+07:00',
                    'updated_at' => '2026-10-01T00:00:00+07:00',
                    'deleted_at' => '2026-10-28T14:42:15+07:00',
                    'mode' => 'offline',
                    'platform' => '',
                    'meeting_link' => '',
                    'attendance_link' => '',
                ],
            ],
            'settings' => [
                ['key' => 'site_name', 'value' => 'Psikologi Test', 'updated_at' => '2026-10-01T00:00:00+07:00'],
                ['key' => 'contact_email', 'value' => 'test@example.test', 'updated_at' => '2026-10-01T00:00:00+07:00'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function sourceFile(array $source): string
    {
        $path = tempnam(sys_get_temp_dir(), 'psikologi-import-source-');

        if ($path === false) {
            $this->fail('Gagal membuat file sumber sementara.');
        }

        file_put_contents($path, json_encode($source, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->sourceFiles[] = $path;

        return $path;
    }
}
