<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\JadwalPage;
use App\Filament\Pages\KelompokPage;
use App\Filament\Pages\MataKuliahPage;
use App\Filament\Pages\PengumpulanPage;
use App\Filament\Pages\PengumumanPage;
use App\Filament\Pages\StudentPage;
use App\Filament\Pages\StudyProgramPage;
use App\Filament\Pages\TugasPage;
use App\Filament\Pages\UserPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AdminActionMountTest extends TestCase
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

    public function test_admin_record_and_header_actions_can_be_mounted(): void
    {
        $student = app(UserRepository::class)->students()->firstOrFail();
        $program = app(StudyProgramRepository::class)->all()->firstOrFail();
        $course = app(CourseRepository::class)->all()->firstOrFail();
        $schedule = app(ScheduleRepository::class)->all()->firstOrFail();
        $task = app(TaskRepository::class)->all()->firstOrFail();
        $submission = app(SubmissionRepository::class)->all()->firstOrFail();
        $announcement = app(AnnouncementRepository::class)->all()->firstOrFail();

        $cases = [
            [StudentPage::class, 'create', null],
            [StudentPage::class, 'edit', (string) $student['id']],
            [StudentPage::class, 'resetPassword', (string) $student['id']],
            [StudentPage::class, 'delete', (string) $student['id']],
            [UserPage::class, 'create', null],
            [UserPage::class, 'edit', (string) $student['id']],
            [UserPage::class, 'resetPassword', (string) $student['id']],
            [UserPage::class, 'delete', (string) $student['id']],
            [StudyProgramPage::class, 'create', null],
            [StudyProgramPage::class, 'edit', (string) $program['id']],
            [StudyProgramPage::class, 'delete', (string) $program['id']],
            [MataKuliahPage::class, 'create', null],
            [MataKuliahPage::class, 'edit', (string) $course['id']],
            [MataKuliahPage::class, 'delete', (string) $course['id']],
            [JadwalPage::class, 'create', null],
            [JadwalPage::class, 'edit', (string) $schedule['id']],
            [JadwalPage::class, 'delete', (string) $schedule['id']],
            [TugasPage::class, 'create', null],
            [TugasPage::class, 'detail', (string) $task['id']],
            [TugasPage::class, 'edit', (string) $task['id']],
            [TugasPage::class, 'delete', (string) $task['id']],
            [PengumpulanPage::class, 'create', null],
            [PengumpulanPage::class, 'detail', (string) $submission['id']],
            [PengumpulanPage::class, 'edit', (string) $submission['id']],
            [PengumpulanPage::class, 'delete', (string) $submission['id']],
            [PengumumanPage::class, 'create', null],
            [PengumumanPage::class, 'read', (string) $announcement['id']],
            [PengumumanPage::class, 'edit', (string) $announcement['id']],
            [PengumumanPage::class, 'delete', (string) $announcement['id']],
        ];

        foreach ($cases as [$page, $action, $record]) {
            Livewire::test($page)->mountTableAction($action, $record);
        }

        $this->assertTrue(true);
    }

    public function test_admin_group_page_actions_can_be_mounted(): void
    {
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($group);

        Livewire::test(KelompokPage::class)
            ->mountAction('create');

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $group['course_id'])
            ->call('selectGroup', (string) $group['id'])
            ->mountAction('edit');

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $group['course_id'])
            ->call('selectGroup', (string) $group['id'])
            ->mountAction('delete');

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $group['course_id'])
            ->call('selectGroup', (string) $group['id'])
            ->mountAction('addMember');
    }

    public function test_admin_task_detail_modal_shows_full_instructions(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        $description = 'Instruksi lengkap untuk admin. '.str_repeat('Detail tugas yang harus dibaca sampai selesai. ', 3).'Langkah terakhir.';
        $task = app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => '',
            'group_ids' => '',
            'meeting_number' => '2',
            'title' => 'Tugas Instruksi Panjang Admin',
            'description' => $description,
            'type' => 'individu',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'active',
            'created_by' => '',
        ]);

        Livewire::test(TugasPage::class)
            ->mountTableAction('detail', (string) $task['id'])
            ->assertMountedActionModalSee('Instruksi tugas')
            ->assertMountedActionModalSee($description)
            ->assertMountedActionModalDontSee('Pengumpulan');
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
