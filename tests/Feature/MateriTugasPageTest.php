<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Portal\Pages\TugasPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class MateriTugasPageTest extends TestCase
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

    public function test_portal_page_uses_materi_dan_tugas_label_and_requested_columns(): void
    {
        $this->actingAsStudent('ayu@psikologi.test');

        $taskOne = $this->taskByTitle('Refleksi Psikologi Umum');
        $taskTwo = $this->taskByTitle('Laporan Observasi Perkembangan Anak');

        $component = Livewire::test(TugasPage::class)
            ->assertSee('Materi dan Tugas')
            ->assertSee('Pertemuan')
            ->assertSee('Tugas')
            ->assertSee('Materi')
            ->assertSee('Jenis')
            ->assertSee('Kelompok')
            ->assertSee('Mulai')
            ->assertSee('Deadline')
            ->assertSee('Status')
            ->assertSee('Dikumpulkan')
            ->assertDontSee('Nilai')
            ->assertTableColumnStateSet('type_label', 'Individu', (string) $taskOne['id'])
            ->assertTableColumnStateSet('portal_group_name', null, (string) $taskOne['id'])
            ->assertTableColumnStateSet('submission_label', 'Sudah dikumpulkan', (string) $taskOne['id'])
            ->assertTableColumnStateSet('submission_label', 'Belum dikumpulkan', (string) $taskTwo['id']);

        $this->assertSame([
            'meeting_number',
            'title',
            'material_url',
            'type_label',
            'portal_group_name',
            'start_at',
            'deadline',
            'status_label',
            'submission_label',
        ], array_keys($component->instance()->getTable()->getColumns()));
    }

    public function test_portal_task_table_uses_the_compact_scrollable_layout(): void
    {
        $this->actingAsStudent('ayu@psikologi.test');

        $table = Livewire::test(TugasPage::class)->instance()->getTable();
        $tableClass = (string) ($table->getExtraAttributes()['class'] ?? '');

        $this->assertStringContainsString('materi-tugas-table', $tableClass);
        $this->assertStringContainsString('task-table-compact', $tableClass);

        $widths = [
            'meeting_number' => '8rem',
            'title' => '16rem',
            'material_url' => '6.5rem',
            'type_label' => '6.5rem',
            'portal_group_name' => '10rem',
            'start_at' => '10rem',
            'deadline' => '14.5rem',
            'status_label' => '7.5rem',
            'submission_label' => '12rem',
        ];

        foreach ($widths as $columnName => $width) {
            $this->assertSame(
                $width,
                $table->getColumn($columnName)?->getWidth(),
                "Portal task column [{$columnName}] has unexpected width.",
            );
        }
    }

    public function test_course_chips_follow_accessible_tasks_and_filter_search_results(): void
    {
        $this->actingAsStudent('ayu@psikologi.test');

        $courseOne = $this->courseByCode('PSI101');
        $courseTwo = $this->courseByCode('PSI201');
        $taskOne = $this->taskByTitle('Refleksi Psikologi Umum');
        $taskTwo = $this->taskByTitle('Laporan Observasi Perkembangan Anak');

        Livewire::test(TugasPage::class)
            ->assertSee('Semua')
            ->assertSee('Psikologi Umum')
            ->assertSee('Psikologi Perkembangan')
            ->assertDontSee('Psikologi Sosial')
            ->assertDontSee('Metodologi Penelitian')
            ->assertCanSeeTableRecords([(string) $taskOne['id'], (string) $taskTwo['id']])
            ->call('selectCourse', (string) $courseOne['id'])
            ->assertSet('selectedCourseId', (string) $courseOne['id'])
            ->assertCanSeeTableRecords([(string) $taskOne['id']])
            ->assertCanNotSeeTableRecords([(string) $taskTwo['id']])
            ->call('selectAllCourses')
            ->assertSet('selectedCourseId', null)
            ->assertCanSeeTableRecords([(string) $taskOne['id'], (string) $taskTwo['id']])
            ->call('selectCourse', (string) $courseTwo['id'])
            ->searchTable('Laporan')
            ->assertCanSeeTableRecords([(string) $taskTwo['id']])
            ->call('selectCourse', (string) $courseOne['id'])
            ->assertSet('tableSearch', 'Laporan')
            ->assertCountTableRecords(0)
            ->searchTable('Refleksi')
            ->assertCanSeeTableRecords([(string) $taskOne['id']]);
    }

    public function test_selecting_a_course_resets_pagination_to_the_first_page(): void
    {
        $this->actingAsStudent('ayu@psikologi.test');

        $courseOne = $this->courseByCode('PSI101');

        $component = Livewire::test(TugasPage::class)
            ->set('tableRecordsPerPage', 1)
            ->call('setPage', 2)
            ->assertSet('paginators.page', 2);

        $this->assertSame(2, $component->instance()->getTablePage());

        $component
            ->call('selectCourse', (string) $courseOne['id'])
            ->assertSet('paginators.page', 1);

        $this->assertSame(1, $component->instance()->getTablePage());
    }

    public function test_course_filter_rejects_inaccessible_or_tampered_course_ids(): void
    {
        $this->actingAsStudent('ayu@psikologi.test');

        $otherCourse = $this->courseByCode('PSI102');
        $taskOne = $this->taskByTitle('Refleksi Psikologi Umum');
        $taskTwo = $this->taskByTitle('Laporan Observasi Perkembangan Anak');

        Livewire::test(TugasPage::class)
            ->call('selectCourse', (string) $otherCourse['id'])
            ->assertSet('selectedCourseId', null)
            ->assertCanSeeTableRecords([(string) $taskOne['id'], (string) $taskTwo['id']]);

        Livewire::test(TugasPage::class)
            ->set('selectedCourseId', (string) $otherCourse['id'])
            ->assertCanSeeTableRecords([(string) $taskOne['id'], (string) $taskTwo['id']]);
    }

    public function test_portal_group_column_uses_the_students_group_and_dash_for_individual_tasks(): void
    {
        $student = $this->actingAsStudent('ayu@psikologi.test');
        $course = $this->courseByCode('PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($group);

        $individualTask = $this->taskByTitle('Laporan Observasi Perkembangan Anak');

        $groupTask = app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => (string) $group['id'],
            'group_ids' => (string) $group['id'],
            'meeting_number' => '6',
            'title' => 'Tugas Kelompok Portal',
            'description' => 'Tugas kelompok untuk pengujian kolom kelompok.',
            'type' => 'kelompok',
            'material_url' => '',
            'collection_link' => '',
            'start_at' => now()->subHour()->toIso8601String(),
            'deadline' => now()->addDay()->toIso8601String(),
            'deadline_note' => '',
            'status' => 'active',
            'created_by' => (string) $student['id'],
        ]);

        Livewire::test(TugasPage::class)
            ->assertTableColumnStateSet(
                'portal_group_name',
                'Kelompok 1 - Observasi',
                (string) $groupTask['id'],
            )
            ->assertTableColumnStateSet('type_label', 'Kelompok', (string) $groupTask['id'])
            ->assertTableColumnStateSet('portal_group_name', null, (string) $individualTask['id'])
            ->assertTableColumnStateSet('type_label', 'Individu', (string) $individualTask['id']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskByTitle(string $title): array
    {
        $task = app(TaskRepository::class)->findBy('title', $title);

        $this->assertNotNull($task);

        return $task;
    }

    /**
     * @return array<string, mixed>
     */
    protected function courseByCode(string $code): array
    {
        $course = app(CourseRepository::class)->findBy('code', $code);

        $this->assertNotNull($course);

        return $course;
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
