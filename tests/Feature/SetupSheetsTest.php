<?php

namespace Tests\Feature;

use App\Repositories\CourseRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Sheets\LocalSheetGateway;
use App\Sheets\SpreadsheetGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class SetupSheetsTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets(false);
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_setup_adds_new_headers_without_moving_existing_user_data(): void
    {
        $legacyHeaders = [
            'id', 'name', 'email', 'npm', 'password_hash', 'role', 'class_name',
            'cohort', 'phone', 'active', 'created_at', 'updated_at', 'deleted_at',
        ];

        $legacyAdmin = [
            'ADMIN-LAMA',
            'Admin Lama',
            'admin.lama@example.test',
            'ADMIN-LAMA',
            Hash::make('password'),
            'admin',
            '',
            '',
            '',
            '1',
            now()->toIso8601String(),
            now()->toIso8601String(),
            '',
        ];

        $gateway = app(SpreadsheetGateway::class);

        $this->assertInstanceOf(LocalSheetGateway::class, $gateway);

        $gateway->seed('users', [$legacyHeaders, $legacyAdmin]);
        Cache::flush();

        $this->artisan('sheets:setup')->assertSuccessful();

        $rows = $gateway->read('users');

        $this->assertSame('prodi_id', $rows[0][13]);
        $this->assertSame('Admin Lama', $rows[1][1]);

        $admin = app(UserRepository::class)->find('ADMIN-LAMA');

        $this->assertNotNull($admin);
        $this->assertSame('super_admin', $admin['role']);
        $this->assertSame('', $admin['prodi_id']);
        $this->assertNotNull(app(StudyProgramRepository::class)->findByCode('psikologi'));
    }

    public function test_setup_assigns_legacy_courses_to_the_default_program(): void
    {
        $legacyHeaders = [
            'id', 'code', 'name', 'credits', 'lecturer', 'semester', 'class_name',
            'description', 'created_at', 'updated_at', 'deleted_at',
        ];

        $legacyCourse = [
            'COURSE-LAMA',
            'OLD101',
            'Mata Kuliah Lama',
            '3',
            'Dosen Lama',
            'Ganjil',
            'Psikologi A',
            'Data sebelum multi-Prodi.',
            now()->toIso8601String(),
            now()->toIso8601String(),
            '',
        ];

        $gateway = app(SpreadsheetGateway::class);

        $this->assertInstanceOf(LocalSheetGateway::class, $gateway);

        $gateway->seed('courses', [$legacyHeaders, $legacyCourse]);
        Cache::flush();

        $this->artisan('sheets:setup', ['--demo' => true])->assertSuccessful();

        $rows = $gateway->read('courses');

        $this->assertSame('prodi_ids', $rows[0][11]);

        $program = app(StudyProgramRepository::class)->findByCode('psikologi');
        $course = app(CourseRepository::class)->find('COURSE-LAMA');

        $this->assertNotNull($program);
        $this->assertNotNull($course);
        $this->assertSame((string) $program['id'], $course['prodi_ids']);
    }

    public function test_setup_adds_online_schedule_headers_without_moving_room_data(): void
    {
        $legacyHeaders = [
            'id', 'course_id', 'day', 'start_time', 'end_time', 'room', 'class_name',
            'created_at', 'updated_at', 'deleted_at',
        ];

        $legacySchedule = [
            'SCHEDULE-LAMA',
            'COURSE-LAMA',
            'Senin',
            '08:00',
            '09:40',
            'Ruang Lama',
            'Psikologi A',
            now()->toIso8601String(),
            now()->toIso8601String(),
            '',
        ];

        $gateway = app(SpreadsheetGateway::class);

        $this->assertInstanceOf(LocalSheetGateway::class, $gateway);

        $gateway->seed('schedules', [$legacyHeaders, $legacySchedule]);
        Cache::flush();

        $this->artisan('sheets:setup', ['--demo' => true])->assertSuccessful();

        $rows = $gateway->read('schedules');

        $this->assertSame('mode', $rows[0][10]);
        $this->assertSame('platform', $rows[0][11]);
        $this->assertSame('meeting_link', $rows[0][12]);
        $this->assertSame('Ruang Lama', $rows[1][5]);

        $schedule = app(ScheduleRepository::class)->find('SCHEDULE-LAMA');

        $this->assertNotNull($schedule);

        $details = app(ScheduleRepository::class)
            ->withCourse(collect([$schedule]))
            ->first();

        $this->assertNotNull($details);
        $this->assertSame('offline', $details['mode']);
        $this->assertSame('Ruang Lama', $details['location_label']);
    }

    public function test_setup_creates_notification_reads_tab(): void
    {
        $this->artisan('sheets:setup', ['--demo' => true])->assertSuccessful();

        $gateway = app(SpreadsheetGateway::class);

        $this->assertInstanceOf(LocalSheetGateway::class, $gateway);

        $rows = $gateway->read('notification_reads');

        $this->assertSame(
            (array) config('sheets.tabs.notification_reads'),
            array_values($rows[0]),
        );
    }

    public function test_setup_demo_creates_super_admin_pjmk_and_student_accounts(): void
    {
        $this->artisan('sheets:setup', ['--demo' => true])->assertSuccessful();

        $users = app(UserRepository::class);

        $this->assertSame('super_admin', $users->findActiveByLogin('admin@psikologi.test')['role']);
        $this->assertSame('pjmk', $users->findActiveByLogin('pjmk@psikologi.test')['role']);
        $this->assertSame('mahasiswa', $users->findActiveByLogin('ayu@psikologi.test')['role']);
    }
}
