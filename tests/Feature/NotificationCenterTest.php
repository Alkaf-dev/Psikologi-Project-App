<?php

namespace Tests\Feature;

use App\Repositories\CourseRepository;
use App\Repositories\NotificationReadRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\NotificationCenter;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00'));
        $this->setUpSheets();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_student_only_receives_notifications_for_their_courses_classes_and_groups(): void
    {
        $notifications = app(NotificationCenter::class)->forUser($this->student('ayu@psikologi.test'));
        $taskTitles = $notifications->where('type', 'task')->pluck('title');
        $scheduleTitles = $notifications->where('type', 'schedule')->pluck('title');

        $this->assertTrue($taskTitles->contains('Refleksi Psikologi Umum'));
        $this->assertTrue($taskTitles->contains('Laporan Observasi Perkembangan Anak'));
        $this->assertFalse($taskTitles->contains('Proposal Penelitian Kelompok'));
        $this->assertFalse($taskTitles->contains('Studi Kasus Psikologi Sosial'));

        $this->assertTrue($scheduleTitles->contains('Jadwal hari ini: Metodologi Penelitian'));
        $this->assertFalse($scheduleTitles->contains('Jadwal besok: Psikologi Sosial'));
    }

    public function test_admin_receives_all_active_tasks_and_schedules_today_and_tomorrow(): void
    {
        $notifications = app(NotificationCenter::class)->forUser($this->admin('admin@psikologi.test'));
        $taskTitles = $notifications->where('type', 'task')->pluck('title');
        $scheduleTitles = $notifications->where('type', 'schedule')->pluck('title');

        $this->assertTrue($taskTitles->contains('Proposal Penelitian Kelompok'));
        $this->assertTrue($taskTitles->contains('Studi Kasus Psikologi Sosial'));
        $this->assertTrue($scheduleTitles->contains('Jadwal hari ini: Metodologi Penelitian'));
        $this->assertTrue($scheduleTitles->contains('Jadwal besok: Psikologi Sosial'));
    }

    public function test_tasks_are_filtered_by_active_status_and_current_week_deadline(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'title' => 'Tugas Pekan Lalu',
            'deadline' => now()->copy()->startOfWeek()->subDay()->setTime(23, 59)->toIso8601String(),
            'status' => 'active',
        ]);
        app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'title' => 'Tugas Selesai',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'completed',
        ]);
        app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'title' => 'Tugas Draf',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'draft',
        ]);
        app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'title' => 'Tugas Tanpa Deadline',
            'deadline' => '',
            'status' => 'active',
        ]);
        app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'title' => 'Tugas Lewat Pekan Ini',
            'deadline' => now()->copy()->startOfWeek()->setTime(8, 0)->toIso8601String(),
            'status' => 'active',
        ]);

        $notifications = app(NotificationCenter::class)->forUser($this->student('ayu@psikologi.test'));
        $taskTitles = $notifications->where('type', 'task')->pluck('title');

        $this->assertFalse($taskTitles->contains('Tugas Pekan Lalu'));
        $this->assertFalse($taskTitles->contains('Tugas Selesai'));
        $this->assertFalse($taskTitles->contains('Tugas Draf'));
        $this->assertFalse($taskTitles->contains('Tugas Tanpa Deadline'));
        $this->assertTrue($taskTitles->contains('Tugas Lewat Pekan Ini'));

        $overdue = $notifications->firstWhere('title', 'Tugas Lewat Pekan Ini');

        $this->assertNotNull($overdue);
        $this->assertSame('Lewat deadline', $overdue['badge']);
        $this->assertSame('danger', $overdue['color']);
    }

    public function test_schedule_notification_links_to_online_meeting_or_schedule_page(): void
    {
        $student = $this->student('ayu@psikologi.test');
        $notifications = app(NotificationCenter::class)->forUser($student);

        $online = $notifications->firstWhere('title', 'Jadwal hari ini: Metodologi Penelitian');

        $this->assertNotNull($online);
        $this->assertSame('https://zoom.us/j/1234567890', $online['url']);
        $this->assertTrue($online['external']);
        $this->assertSame('Gabung rapat', $online['action_label']);

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        app(ScheduleRepository::class)->create([
            'course_id' => (string) $course['id'],
            'day' => 'Jumat',
            'start_time' => '08:00',
            'end_time' => '09:40',
            'room' => 'Ruang A201',
            'class_name' => 'Psikologi A',
            'mode' => 'offline',
        ]);

        $offline = app(NotificationCenter::class)
            ->forUser($student)
            ->firstWhere('title', 'Jadwal besok: Psikologi Umum');

        $this->assertNotNull($offline);
        $this->assertFalse($offline['external']);
        $this->assertSame('Lihat jadwal', $offline['action_label']);
        $this->assertStringContainsString('/portal/jadwal-page', $offline['url']);
    }

    public function test_deadline_change_creates_a_new_notification_key(): void
    {
        $student = $this->student('ayu@psikologi.test');
        $task = app(TaskRepository::class)->findBy('title', 'Refleksi Psikologi Umum');

        $this->assertNotNull($task);

        $before = app(NotificationCenter::class)
            ->forUser($student)
            ->firstWhere('title', 'Refleksi Psikologi Umum');

        $this->assertNotNull($before);

        app(TaskRepository::class)->update((string) $task['id'], [
            'deadline' => now()->addDays(10)->setTime(23, 59)->toIso8601String(),
        ]);

        $after = app(NotificationCenter::class)
            ->forUser($student)
            ->firstWhere('title', 'Refleksi Psikologi Umum');

        $this->assertNotNull($after);
        $this->assertNotSame($before['key'], $after['key']);
    }

    public function test_read_state_is_per_user_and_mark_all_is_idempotent(): void
    {
        $center = app(NotificationCenter::class);
        $reads = app(NotificationReadRepository::class);
        $ayu = $this->student('ayu@psikologi.test');
        $bima = $this->student('bima@psikologi.test');

        $notifications = $center->forUser($ayu);
        $initialUnread = $center->unreadCount($ayu);
        $first = $notifications->first();

        $this->assertNotNull($first);

        $center->markAsRead($ayu, $first['key']);

        $this->assertTrue($center->forUser($ayu)->firstWhere('key', $first['key'])['read']);
        $this->assertSame($initialUnread - 1, $center->unreadCount($ayu));

        $center->markAllAsRead($ayu);

        $this->assertSame(0, $center->unreadCount($ayu));
        $this->assertSame($notifications->count(), $reads->forUser((string) $ayu['id'])->count());

        $center->markAllAsRead($ayu);

        $this->assertSame($notifications->count(), $reads->forUser((string) $ayu['id'])->count());

        $this->assertTrue(
            $center->forUser($bima)->every(fn (array $notification): bool => $notification['read'] === false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function student(string $email): array
    {
        $student = app(UserRepository::class)->findActiveByLogin($email);

        $this->assertNotNull($student);

        return $student;
    }

    /**
     * @return array<string, mixed>
     */
    protected function admin(string $email): array
    {
        $admin = app(UserRepository::class)->findActiveByLogin($email);

        $this->assertNotNull($admin);

        return $admin;
    }
}
