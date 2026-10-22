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
        Filament::setCurrentPanel('admin');
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
            ->assertSee('Link Pengumpulan')
            ->instance()
            ->getTable();

        $this->assertSame('8rem', $table->getColumn('meeting_number')?->getWidth());
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
