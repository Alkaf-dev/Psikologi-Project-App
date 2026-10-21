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
                'meeting_number' => Alignment::Center,
                'type_label' => Alignment::Center,
                'start_at' => Alignment::Center,
                'deadline' => Alignment::Center,
                'status_label' => Alignment::Center,
                'submission_count' => Alignment::Center,
            ],
            JadwalPage::class => [
                'course_name' => null,
                'day' => Alignment::Center,
                'start_time' => Alignment::Center,
                'end_time' => Alignment::Center,
            ],
            KelompokPage::class => [
                'name' => null,
                'members_count' => Alignment::Center,
            ],
            MataKuliahPage::class => [
                'name' => null,
                'credits' => Alignment::End,
                'semester' => Alignment::End,
                'prodi_names' => Alignment::Center,
            ],
            PengumpulanPage::class => [
                'task_title' => null,
                'status' => Alignment::Center,
                'grade' => Alignment::Center,
                'submitted_at' => Alignment::Center,
            ],
            PengumumanPage::class => [
                'title' => null,
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
                'meeting_number' => Alignment::Center,
                'start_at' => Alignment::Center,
                'deadline' => Alignment::Center,
                'status_label' => Alignment::Center,
                'submission_label' => Alignment::Center,
                'submission_grade' => Alignment::Center,
            ],
            PortalJadwalPage::class => [
                'course_name' => null,
                'day' => Alignment::Center,
                'start_time' => Alignment::Center,
                'end_time' => Alignment::Center,
            ],
            PortalKelompokPage::class => [
                'name' => null,
                'my_role' => Alignment::Center,
                'members_count' => Alignment::Center,
            ],
            PortalPengumumanPage::class => [
                'title' => null,
                'published_at' => Alignment::Center,
            ],
        ]);
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
