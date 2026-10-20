<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Portal\Pages\TugasPage;
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
            ->assertHasNoTableActionErrors();

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
