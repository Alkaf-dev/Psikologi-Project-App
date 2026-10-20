<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\JadwalPage;
use App\Filament\Pages\KelompokPage;
use App\Filament\Pages\MataKuliahPage;
use App\Filament\Pages\PengumpulanPage;
use App\Filament\Pages\PengumumanPage;
use App\Filament\Pages\TugasPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AdminCreateActionTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_admin_can_create_course_schedule_group_task_announcement_and_submission(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI101');
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($course);
        $this->assertNotNull($student);

        Livewire::test(MataKuliahPage::class)
            ->callTableAction('create', null, [
                'code' => 'PSI999',
                'name' => 'Mata Kuliah Pengujian',
                'credits' => 3,
                'lecturer' => 'Dosen Pengujian',
                'semester' => 'Ganjil',
                'class_name' => 'Psikologi A',
                'description' => 'Deskripsi pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull(app(CourseRepository::class)->findBy('code', 'PSI999'));

        Livewire::test(JadwalPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'day' => 'Sabtu',
                'start_time' => '16:00',
                'end_time' => '17:30',
                'room' => 'Ruang Uji',
                'class_name' => 'Psikologi A',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull(app(ScheduleRepository::class)->findBy('room', 'Ruang Uji'));

        Livewire::test(KelompokPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'name' => 'Kelompok Pengujian',
                'leader_id' => (string) $student['id'],
                'member_ids' => [(string) $student['id']],
                'description' => 'Kelompok untuk pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $group = app(GroupRepository::class)->findBy('name', 'Kelompok Pengujian');

        $this->assertNotNull($group);
        $this->assertCount(1, app(GroupMemberRepository::class)->forGroup((string) $group['id']));

        Livewire::test(TugasPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'type' => 'individu',
                'title' => 'Tugas Pengujian',
                'deadline' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'description' => 'Instruksi pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $task = app(TaskRepository::class)->findBy('title', 'Tugas Pengujian');

        $this->assertNotNull($task);

        Livewire::test(PengumumanPage::class)
            ->callTableAction('create', null, [
                'title' => 'Pengumuman Pengujian',
                'audience' => 'all',
                'published_at' => now()->format('Y-m-d H:i:s'),
                'body' => 'Isi pengumuman pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull(app(AnnouncementRepository::class)->findBy('title', 'Pengumuman Pengujian'));

        Livewire::test(PengumpulanPage::class)
            ->callTableAction('create', null, [
                'task_id' => (string) $task['id'],
                'user_id' => (string) $student['id'],
                'group_id' => '',
                'status' => 'submitted',
                'link' => 'https://example.com/pengumpulan-baru',
                'notes' => '',
                'feedback' => '',
            ])
            ->assertHasNoTableActionErrors();

        $submission = app(SubmissionRepository::class)
            ->forTaskAndUser((string) $task['id'], (string) $student['id']);

        $this->assertNotNull($submission);
        $this->assertSame('submitted', $submission['status']);
    }

    protected function actingAsAdmin(): void
    {
        $admin = app(UserRepository::class)->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $admin['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }
}
