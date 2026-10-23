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
use App\Filament\Portal\Pages\JadwalPage as PortalJadwalPage;
use App\Filament\Portal\Pages\KelompokPage as PortalKelompokPage;
use App\Filament\Portal\Pages\PengumumanPage as PortalPengumumanPage;
use App\Filament\Portal\Pages\TugasPage as PortalTugasPage;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class TableColumnAlignmentTest extends TestCase
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

    public function test_admin_tables_use_semantic_column_alignment(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->assertColumnAlignments([
            TugasPage::class => [
                'title' => null,
                'material_url' => Alignment::Center,
                'collection_link' => null,
                'meeting_number' => Alignment::Center,
                'type_label' => Alignment::Center,
                'start_at' => Alignment::Center,
                'deadline' => Alignment::Center,
                'status_label' => Alignment::Center,
                'submission_count' => Alignment::Center,
            ],
            JadwalPage::class => [
                'course_name' => null,
                'location_label' => null,
                'day' => Alignment::Center,
                'start_time' => Alignment::Center,
                'end_time' => Alignment::Center,
                'mode_label' => Alignment::Center,
                'meeting_link' => Alignment::Center,
            ],
            KelompokPage::class => [
                'index' => Alignment::Center,
                'user_name' => null,
                'course_name' => null,
                'members_count' => Alignment::Center,
            ],
            MataKuliahPage::class => [
                'name' => null,
                'credits' => Alignment::End,
                'semester' => Alignment::End,
                'prodi_names' => Alignment::Center,
                'meeting_link' => Alignment::Center,
            ],
            PengumpulanPage::class => [
                'task_title' => null,
                'student_name' => null,
                'group_name' => null,
                'status' => Alignment::Center,
                'link' => Alignment::Center,
                'submitted_at' => Alignment::Center,
            ],
            PengumumanPage::class => [
                'title' => null,
                'course_name' => Alignment::Center,
                'audience_label' => Alignment::Center,
                'prodi_names' => Alignment::Center,
                'published_at' => Alignment::Center,
            ],
            StudentPage::class => [
                'name' => null,
                'cohort' => Alignment::End,
                'active' => Alignment::Center,
            ],
            UserPage::class => [
                'name' => null,
                'role' => Alignment::Center,
                'cohort' => Alignment::End,
                'active' => Alignment::Center,
            ],
            StudyProgramPage::class => [
                'name' => null,
                'active' => Alignment::Center,
            ],
        ]);
    }

    public function test_portal_tables_use_semantic_column_alignment(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        $this->assertColumnAlignments([
            PortalTugasPage::class => [
                'title' => null,
                'material_url' => Alignment::Center,
                'type_label' => Alignment::Center,
                'portal_group_name' => null,
                'meeting_number' => Alignment::Center,
                'start_at' => Alignment::Center,
                'deadline' => Alignment::Center,
                'status_label' => Alignment::Center,
                'submission_label' => Alignment::Center,
            ],
            PortalJadwalPage::class => [
                'course_name' => null,
                'location_label' => null,
                'day' => Alignment::Center,
                'start_time' => Alignment::Center,
                'end_time' => Alignment::Center,
                'mode_label' => Alignment::Center,
                'meeting_link' => Alignment::Center,
            ],
            PortalKelompokPage::class => [
                'index' => Alignment::Center,
                'user_name' => null,
                'course_name' => null,
                'members_count' => Alignment::Center,
            ],
            PortalPengumumanPage::class => [
                'title' => null,
                'course_name' => Alignment::Center,
                'published_at' => Alignment::Center,
            ],
        ]);
    }

    public function test_pengumpulan_table_uses_the_compact_spreadsheet_layout(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $table = Livewire::test(PengumpulanPage::class)->instance()->getTable();
        $tableClass = (string) ($table->getExtraAttributes()['class'] ?? '');

        $this->assertStringContainsString('submission-table', $tableClass);
        $this->assertStringContainsString('sheet-responsive-table', $tableClass);
        $this->assertStringContainsString('task-table-compact', $tableClass);

        $widths = [
            'task_title' => '19%',
            'student_name' => '17%',
            'group_name' => '13%',
            'status' => '12%',
            'link' => '5.5%',
            'submitted_at' => '9.5%',
        ];

        foreach ($widths as $columnName => $width) {
            $this->assertSame(
                $width,
                $table->getColumn($columnName)?->getWidth(),
                "Pengumpulan column [{$columnName}] has unexpected width.",
            );
        }

        foreach (array_keys($widths) as $columnName) {
            $style = (string) ($table->getColumn($columnName)->getExtraCellAttributes()['style'] ?? '');

            $this->assertStringContainsString(
                '--sheet-table-border',
                $style,
                "Pengumpulan column [{$columnName}] does not use the shared border color.",
            );
        }

        foreach (['task_title', 'student_name', 'group_name'] as $columnName) {
            $this->assertNull(
                $table->getColumn($columnName)?->getCharacterLimit(),
                "Pengumpulan column [{$columnName}] must rely on CSS truncation so the full text stays available.",
            );
        }

        foreach (['task_title', 'student_name', 'group_name', 'status', 'submitted_at'] as $clickableColumn) {
            $this->assertNotNull(
                $table->getColumn($clickableColumn)?->getAction(),
                "Pengumpulan column [{$clickableColumn}] should be clickable to view details.",
            );
        }
    }

    public function test_record_action_buttons_are_centered_and_persist_across_pages(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->get('/admin/pengumpulan-page')
            ->assertOk()
            ->assertSee('.fi-ta-actions', false)
            ->assertSee('justify-content: center', false)
            ->assertSee('min-width: 68rem', false)
            ->assertSee('width: var(--sheet-action-column-width)', false);

        $this->get('/admin/tugas-page')
            ->assertOk()
            ->assertSee('justify-content: center', false);
    }

    /**
     * @param  array<class-string, array<string, Alignment|null>>  $expectedColumns
     */
    protected function assertColumnAlignments(array $expectedColumns): void
    {
        foreach ($expectedColumns as $pageClass => $columns) {
            $table = Livewire::test($pageClass)->instance()->getTable();

            foreach ($columns as $columnName => $expectedAlignment) {
                $column = $table->getColumn($columnName);

                $this->assertNotNull($column, "{$pageClass} column [{$columnName}] was not found.");
                $this->assertSame(
                    $expectedAlignment,
                    $column->getAlignment(),
                    "{$pageClass} column [{$columnName}] has unexpected alignment.",
                );
            }
        }
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
