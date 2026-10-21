<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\JadwalPage;
use App\Filament\Portal\Pages\JadwalPage as PortalJadwalPage;
use App\Repositories\CourseRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class OnlineScheduleTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_admin_can_create_and_update_online_schedule(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        Livewire::test(JadwalPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'day' => 'Rabu',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'mode' => 'online',
                'platform' => 'zoom',
                'meeting_link' => 'https://zoom.us/j/9998887776',
                'class_name' => 'Psikologi A',
            ])
            ->assertHasNoTableActionErrors();

        $schedule = app(ScheduleRepository::class)->findBy('meeting_link', 'https://zoom.us/j/9998887776');

        $this->assertNotNull($schedule);
        $this->assertSame('online', $schedule['mode']);
        $this->assertSame('zoom', $schedule['platform']);
        $this->assertSame('', $schedule['room']);

        Livewire::test(JadwalPage::class)
            ->callTableAction('edit', (string) $schedule['id'], [
                'course_id' => (string) $course['id'],
                'day' => 'Rabu',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'mode' => 'online',
                'platform' => 'google_meet',
                'meeting_link' => 'https://meet.google.com/online-schedule-test',
                'class_name' => 'Psikologi A',
            ])
            ->assertHasNoTableActionErrors();

        $updated = app(ScheduleRepository::class)->find((string) $schedule['id']);

        $this->assertNotNull($updated);
        $this->assertSame('google_meet', $updated['platform']);
        $this->assertSame('https://meet.google.com/online-schedule-test', $updated['meeting_link']);

        Livewire::test(JadwalPage::class)
            ->callTableAction('edit', (string) $schedule['id'], [
                'course_id' => (string) $course['id'],
                'day' => 'Rabu',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'mode' => 'offline',
                'room' => 'Ruang Uji Offline',
                'class_name' => 'Psikologi A',
            ])
            ->assertHasNoTableActionErrors();

        $offline = app(ScheduleRepository::class)->find((string) $schedule['id']);

        $this->assertNotNull($offline);
        $this->assertSame('offline', $offline['mode']);
        $this->assertSame('Ruang Uji Offline', $offline['room']);
        $this->assertSame('', $offline['platform']);
        $this->assertSame('', $offline['meeting_link']);
    }

    public function test_portal_displays_online_schedule_link(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        $schedule = app(ScheduleRepository::class)->create([
            'course_id' => (string) $course['id'],
            'day' => 'Rabu',
            'start_time' => '13:00',
            'end_time' => '14:30',
            'room' => '',
            'class_name' => 'Psikologi A',
            'mode' => 'online',
            'platform' => 'google_meet',
            'meeting_link' => 'https://meet.google.com/portal-online-test',
        ]);

        Livewire::test(PortalJadwalPage::class)
            ->assertCanSeeTableRecords([(string) $schedule['id']])
            ->assertSee('Google Meet')
            ->assertSee('https://meet.google.com/portal-online-test', false);
    }

    public function test_schedule_without_mode_is_treated_as_offline(): void
    {
        $schedule = app(ScheduleRepository::class)->create([
            'course_id' => '',
            'day' => 'Senin',
            'start_time' => '07:00',
            'end_time' => '08:30',
            'room' => 'Ruang Lama',
            'class_name' => 'Psikologi A',
        ]);

        $details = app(ScheduleRepository::class)
            ->withCourse(collect([$schedule]))
            ->first();

        $this->assertNotNull($details);
        $this->assertSame('offline', $details['mode']);
        $this->assertSame('Offline', $details['mode_label']);
        $this->assertSame('Ruang Lama', $details['location_label']);
    }

    protected function actingAsAdmin(): void
    {
        $admin = app(UserRepository::class)->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $admin['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }

    protected function actingAsStudent(): void
    {
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($student);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $student['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }
}
