<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Portal\Pages\TugasPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class PortalSubmissionActionTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        Filament::setCurrentPanel('portal');
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_student_can_submit_an_individual_task(): void
    {
        $student = $this->actingAsStudent('ayu@psikologi.test');
        $task = app(TaskRepository::class)
            ->forStudent($student)
            ->first(fn (array $task): bool => $task['title'] === 'Laporan Observasi Perkembangan Anak');

        $this->assertNotNull($task);

        Livewire::test(TugasPage::class)
            ->callTableAction('submit', (string) $task['id'], ['link' => 'https://example.com/tugas-individu'])
            ->assertHasNoTableActionErrors()
            ->assertDispatched(
                'psychology-mascot:celebrate',
                state: 'success',
                message: 'Tugas berhasil dikumpulkan. Kerja bagus!',
            );

        $submission = app(SubmissionRepository::class)
            ->forTaskAndUser((string) $task['id'], (string) $student['id']);

        $this->assertNotNull($submission);
        $this->assertSame('', (string) $submission['group_id']);
        $this->assertSame('submitted', $submission['status']);
        $this->assertSame('https://example.com/tugas-individu', $submission['link']);
    }

    public function test_group_task_submission_uses_the_task_group(): void
    {
        $student = $this->actingAsStudent('ayu@psikologi.test');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($group);

        $task = app(TaskRepository::class)->create([
            'course_id' => $group['course_id'],
            'group_id' => $group['id'],
            'title' => 'Tugas Kelompok Pengujian',
            'description' => '',
            'type' => 'kelompok',
            'deadline' => now()->addDay()->toIso8601String(),
            'created_by' => '',
        ]);

        Livewire::test(TugasPage::class)
            ->callTableAction('submit', (string) $task['id'], ['link' => 'https://example.com/tugas-kelompok'])
            ->assertHasNoTableActionErrors();

        $submission = app(SubmissionRepository::class)
            ->forTaskAndUser((string) $task['id'], (string) $student['id']);

        $this->assertNotNull($submission);
        $this->assertSame((string) $group['id'], (string) $submission['group_id']);
    }

    public function test_group_task_submission_is_shared_with_the_whole_group(): void
    {
        $ayu = $this->actingAsStudent('ayu@psikologi.test');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($group);

        $task = app(TaskRepository::class)->create([
            'course_id' => $group['course_id'],
            'group_id' => $group['id'],
            'group_ids' => $group['id'],
            'title' => 'Tugas Kelompok Berbagi',
            'description' => '',
            'type' => 'kelompok',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'active',
            'created_by' => '',
        ]);

        Livewire::test(TugasPage::class)
            ->callTableAction('submit', (string) $task['id'], ['link' => 'https://example.com/awal'])
            ->assertHasNoTableActionErrors();

        $submission = app(SubmissionRepository::class)
            ->forTaskAndGroup((string) $task['id'], (string) $group['id']);

        $this->assertNotNull($submission);
        $this->assertSame((string) $ayu['id'], (string) $submission['user_id']);

        $this->actingAsStudent('bima@psikologi.test');

        Livewire::test(TugasPage::class)
            ->assertCanSeeTableRecords([(string) $task['id']])
            ->assertTableActionHidden('submit', (string) $task['id'])
            ->assertSee('Sudah dikumpulkan');

        $this->actingAsStudent('ayu@psikologi.test');

        Livewire::test(TugasPage::class)
            ->callTableAction('submit', (string) $task['id'], ['link' => 'https://example.com/revisi'])
            ->assertHasNoTableActionErrors();

        $updated = app(SubmissionRepository::class)
            ->forTaskAndGroup((string) $task['id'], (string) $group['id']);

        $this->assertNotNull($updated);
        $this->assertSame((string) $submission['id'], (string) $updated['id']);
        $this->assertSame('https://example.com/revisi', $updated['link']);
        $this->assertCount(1, app(SubmissionRepository::class)
            ->all()
            ->filter(fn (array $submission): bool => (string) ($submission['task_id'] ?? '') === (string) $task['id']));
    }

    public function test_group_submission_cannot_be_updated_after_the_deadline(): void
    {
        $ayu = $this->actingAsStudent('ayu@psikologi.test');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($group);

        $task = app(TaskRepository::class)->create([
            'course_id' => $group['course_id'],
            'group_id' => $group['id'],
            'group_ids' => $group['id'],
            'title' => 'Tugas Kelompok Lewat Deadline',
            'description' => '',
            'type' => 'kelompok',
            'deadline' => now()->subDay()->toIso8601String(),
            'status' => 'active',
            'created_by' => '',
        ]);

        app(SubmissionRepository::class)->create([
            'task_id' => (string) $task['id'],
            'user_id' => (string) $ayu['id'],
            'group_id' => (string) $group['id'],
            'status' => 'late',
            'link' => 'https://example.com/terlambat',
            'notes' => '',
            'submitted_at' => now()->subDay()->toIso8601String(),
        ]);

        Livewire::test(TugasPage::class)
            ->assertTableActionHidden('submit', (string) $task['id']);

        $this->actingAsStudent('bima@psikologi.test');

        Livewire::test(TugasPage::class)
            ->assertTableActionHidden('submit', (string) $task['id']);
    }

    public function test_task_detail_modal_renders_long_multiline_instructions(): void
    {
        $this->actingAsStudent('ayu@psikologi.test');

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        $task = app(TaskRepository::class)->create([
            'course_id' => $course['id'],
            'group_id' => '',
            'group_ids' => '',
            'meeting_number' => '1',
            'title' => 'Tugas Instruksi Panjang',
            'description' => "1. Langkah pertama yang harus dikerjakan.\n2. Langkah kedua dengan penjelasan tambahan.\n\n3. Langkah ketiga sebagai penutup.",
            'type' => 'individu',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'active',
            'created_by' => '',
        ]);

        Livewire::test(TugasPage::class)
            ->mountTableAction('detail', (string) $task['id'])
            ->assertMountedActionModalSee('Instruksi tugas')
            ->assertMountedActionModalSee('Langkah pertama yang harus dikerjakan.')
            ->assertMountedActionModalSee('Langkah ketiga sebagai penutup.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function actingAsStudent(string $login): array
    {
        $student = app(UserRepository::class)->findActiveByLogin($login);

        $this->assertNotNull($student);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $student['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);

        return $student;
    }
}
