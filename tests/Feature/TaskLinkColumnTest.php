<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\PengumpulanPage;
use App\Filament\Pages\TugasPage as AdminTugasPage;
use App\Filament\Portal\Pages\TugasPage as PortalTugasPage;
use App\Repositories\CourseRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class TaskLinkColumnTest extends TestCase
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

    public function test_admin_task_table_renders_clickable_collection_link(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->createLinkedTask();

        Livewire::test(AdminTugasPage::class)
            ->assertSee('Link Pengumpulan')
            ->assertSee('Buka link')
            ->assertSeeHtml('href="https://example.test/folder-pengumpulan"')
            ->assertSeeHtml('task-table-compact');
    }

    public function test_admin_task_table_loads_compact_density_styles(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->get('/admin/tugas-page')
            ->assertOk()
            ->assertSee('task-table-compact', false)
            ->assertSee('padding-block: 0.4rem', false);
    }

    public function test_portal_task_table_renders_clickable_material_and_collection_links(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent('ayu@psikologi.test');

        $this->createLinkedTask();

        Livewire::test(PortalTugasPage::class)
            ->assertSee('Materi')
            ->assertSee('Pengumpulan')
            ->assertSeeHtml('href="https://example.test/materi-tugas"')
            ->assertSeeHtml('href="https://example.test/folder-pengumpulan"');
    }

    public function test_pengumpulan_table_renders_submission_link_as_kirim(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $url = 'https://docs.google.com/document/d/contoh-refleksi-ayu';

        Livewire::test(PengumpulanPage::class)
            ->searchTable('Refleksi Psikologi Umum')
            ->assertSeeText('Kirim')
            ->assertDontSeeText($url)
            ->assertSeeHtml('href="'.$url.'"')
            ->assertSeeHtml('target="_blank"');
    }

    public function test_pengumpulan_table_shows_placeholder_when_submission_link_is_missing(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->createSubmissionForTask('Studi Kasus Psikologi Sosial', '');

        Livewire::test(PengumpulanPage::class)
            ->searchTable('Studi Kasus Psikologi Sosial')
            ->assertDontSeeText('Kirim')
            ->assertSeeText('-');
    }

    public function test_pengumpulan_table_rejects_unsafe_submission_links(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->createSubmissionForTask('Studi Kasus Psikologi Sosial', 'javascript:alert(1)');

        Livewire::test(PengumpulanPage::class)
            ->searchTable('Studi Kasus Psikologi Sosial')
            ->assertDontSeeText('Kirim')
            ->assertDontSeeHtml('javascript:alert');
    }

    protected function createSubmissionForTask(string $taskTitle, string $link): void
    {
        $task = app(TaskRepository::class)->findBy('title', $taskTitle);
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($task);
        $this->assertNotNull($student);

        app(SubmissionRepository::class)->create([
            'task_id' => (string) $task['id'],
            'user_id' => (string) $student['id'],
            'group_id' => '',
            'status' => 'submitted',
            'link' => $link,
            'notes' => '',
            'grade' => '',
            'feedback' => '',
            'submitted_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createLinkedTask(): array
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        return app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => '',
            'group_ids' => '',
            'meeting_number' => '9',
            'title' => 'Tugas Tautan Uji',
            'description' => 'Tugas untuk pengujian tautan.',
            'type' => 'individu',
            'material_url' => 'https://example.test/materi-tugas',
            'collection_link' => 'https://example.test/folder-pengumpulan',
            'start_at' => now()->subHour()->toIso8601String(),
            'deadline' => now()->addDay()->toIso8601String(),
            'deadline_note' => '',
            'status' => 'active',
            'created_by' => '',
        ]);
    }

    protected function actingAsAdmin(): void
    {
        $admin = app(UserRepository::class)->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $admin['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }

    protected function actingAsStudent(string $login): void
    {
        $student = app(UserRepository::class)->findActiveByLogin($login);

        $this->assertNotNull($student);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $student['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }
}
