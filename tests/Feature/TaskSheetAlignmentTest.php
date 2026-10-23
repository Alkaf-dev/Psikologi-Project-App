<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Portal\Pages\TugasPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class TaskSheetAlignmentTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        Filament::setCurrentPanel('app');
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_multi_group_task_is_visible_to_members_of_each_assigned_group(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');
        $groupOne = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');
        $ayu = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($course);
        $this->assertNotNull($program);
        $this->assertNotNull($groupOne);
        $this->assertNotNull($ayu);

        $eka = app(UserRepository::class)->create([
            'name' => 'Eka Mahasiswa',
            'email' => 'eka@psikologi.test',
            'npm' => '2024999',
            'password_hash' => Hash::make('password'),
            'role' => 'mahasiswa',
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '',
            'active' => '1',
            'prodi_id' => (string) $program['id'],
        ]);

        $groupTwo = app(GroupRepository::class)->create([
            'course_id' => (string) $course['id'],
            'name' => 'Kelompok 2 - Tugas Sheet',
            'leader_id' => (string) $eka['id'],
            'description' => '',
        ]);

        app(GroupMemberRepository::class)->syncForGroup(
            (string) $groupTwo['id'],
            [(string) $eka['id']],
            (string) $eka['id'],
        );

        $task = app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => (string) $groupOne['id'],
            'group_ids' => [(string) $groupOne['id'], (string) $groupTwo['id']],
            'meeting_number' => '5',
            'title' => 'Tugas Multi Kelompok',
            'description' => 'Instruksi multi kelompok.',
            'type' => 'kelompok',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'active',
            'created_by' => '',
        ]);

        $this->assertTrue(app(TaskRepository::class)->forStudent($ayu)->contains('id', (string) $task['id']));
        $this->assertTrue(app(TaskRepository::class)->forStudent($eka)->contains('id', (string) $task['id']));

        $details = app(TaskRepository::class)->withDetails(collect([$task]))->firstOrFail();

        $this->assertStringContainsString('Kelompok 1 - Observasi', (string) $details['group_name']);
        $this->assertStringContainsString('Kelompok 2 - Tugas Sheet', (string) $details['group_name']);
    }

    public function test_draft_task_is_hidden_and_completed_task_is_read_only(): void
    {
        $student = $this->actingAsStudent('ayu@psikologi.test');
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($course);
        $this->assertNotNull($group);

        $draft = app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => '',
            'group_ids' => '',
            'title' => 'Tugas Draf',
            'description' => '',
            'type' => 'individu',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'draft',
            'created_by' => '',
        ]);

        $completed = app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => (string) $group['id'],
            'group_ids' => (string) $group['id'],
            'title' => 'Tugas Selesai',
            'description' => '',
            'type' => 'kelompok',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'completed',
            'created_by' => '',
        ]);

        $visible = app(TaskRepository::class)->forStudent($student);

        $this->assertFalse($visible->contains('id', (string) $draft['id']));
        $this->assertTrue($visible->contains('id', (string) $completed['id']));

        Livewire::test(TugasPage::class)
            ->assertCanNotSeeTableRecords([(string) $draft['id']])
            ->assertCanSeeTableRecords([(string) $completed['id']])
            ->assertTableActionHidden('submit', (string) $completed['id']);
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
