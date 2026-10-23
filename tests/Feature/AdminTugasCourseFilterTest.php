<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\TugasPage as AdminTugasPage;
use App\Repositories\CourseRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AdminTugasCourseFilterTest extends TestCase
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

    public function test_admin_task_page_renders_course_filter_chips_and_expected_columns(): void
    {
        $this->actingAsAdmin();

        $table = Livewire::test(AdminTugasPage::class)
            ->assertSee('Semua')
            ->assertSee('Pertemuan')
            ->assertSee('Materi')
            ->assertSee('Link Pengumpulan')
            ->assertSee('Tambah Tugas')
            ->instance()
            ->getTable();

        $this->assertSame('4.75rem', $table->getColumn('meeting_number')?->getWidth());
        $this->assertSame('17.5rem', $table->getColumn('title')?->getWidth());
        $this->assertSame('7.25rem', $table->getColumn('material_url')?->getWidth());
        $this->assertSame('7.75rem', $table->getColumn('collection_link')?->getWidth());
        $this->assertSame('9.75rem', $table->getColumn('deadline')?->getWidth());
        $this->assertNull($table->getColumn('title')?->getCharacterLimit());
    }

    public function test_admin_task_table_loads_non_overlapping_action_styles(): void
    {
        $this->actingAsAdmin();

        $table = Livewire::test(AdminTugasPage::class)->instance()->getTable();
        $tableClass = (string) ($table->getExtraAttributes()['class'] ?? '');

        $this->assertStringContainsString('materi-tugas-admin-table', $tableClass);
        $this->assertStringContainsString('sheet-responsive-table', $tableClass);
        $this->assertTrue($table->getColumn('meeting_number')?->canHeaderWrap());

        $this->get('/app/tugas-page')
            ->assertOk()
            ->assertSee('min-width: 107.5rem', false)
            ->assertSee('--sheet-action-column-width: 14rem', false)
            ->assertSee('width: var(--sheet-action-column-width)', false)
            ->assertDontSee('min-width: 136rem', false)
            ->assertSee('Detail')
            ->assertSee('Ubah')
            ->assertSee('Hapus');
    }

    public function test_admin_course_chips_filter_task_table_records(): void
    {
        $this->actingAsAdmin();

        $courseOne = $this->courseByCode('PSI101');
        $courseTwo = $this->courseByCode('PSI201');

        $taskOne = $this->taskByTitle('Refleksi Psikologi Umum');
        $taskTwo = $this->taskByTitle('Laporan Observasi Perkembangan Anak');

        Livewire::test(AdminTugasPage::class)
            ->assertSee('Semua')
            ->assertSee('Psikologi Umum')
            ->assertSee('Psikologi Perkembangan')
            ->assertCanSeeTableRecords([(string) $taskOne['id'], (string) $taskTwo['id']])
            ->call('selectCourse', (string) $courseOne['id'])
            ->assertSet('selectedCourseId', (string) $courseOne['id'])
            ->assertCanSeeTableRecords([(string) $taskOne['id']])
            ->assertCanNotSeeTableRecords([(string) $taskTwo['id']])
            ->call('selectAllCourses')
            ->assertSet('selectedCourseId', null)
            ->assertCanSeeTableRecords([(string) $taskOne['id'], (string) $taskTwo['id']])
            ->call('selectCourse', (string) $courseTwo['id'])
            ->assertCanSeeTableRecords([(string) $taskTwo['id']])
            ->assertCanNotSeeTableRecords([(string) $taskOne['id']]);
    }

    public function test_admin_selecting_course_resets_pagination_to_page_one(): void
    {
        $this->actingAsAdmin();

        $courseOne = $this->courseByCode('PSI101');

        $component = Livewire::test(AdminTugasPage::class)
            ->set('tableRecordsPerPage', 1)
            ->call('setPage', 2)
            ->assertSet('paginators.page', 2);

        $this->assertSame(2, $component->instance()->getTablePage());

        $component
            ->call('selectCourse', (string) $courseOne['id'])
            ->assertSet('paginators.page', 1);

        $this->assertSame(1, $component->instance()->getTablePage());
    }

    public function test_admin_course_filter_rejects_unknown_course_ids(): void
    {
        $this->actingAsAdmin();

        Livewire::test(AdminTugasPage::class)
            ->call('selectCourse', 'unknown-id')
            ->assertSet('selectedCourseId', null);
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

    protected function actingAsAdmin(): void
    {
        $admin = app(UserRepository::class)->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $admin['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }
}
