<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\StudyProgramPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class StudyProgramPageTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        Filament::setCurrentPanel('app');
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_admin_can_create_update_and_delete_an_unused_program(): void
    {
        Livewire::test(StudyProgramPage::class)
            ->callTableAction('create', null, [
                'code' => 'Bimbingan-Konseling',
                'name' => 'Bimbingan dan Konseling',
                'degree' => 'S1',
                'active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $programs = app(StudyProgramRepository::class);
        $program = $programs->findByCode('bimbingan-konseling');

        $this->assertNotNull($program);
        $this->assertSame('Bimbingan dan Konseling', $program['name']);

        Livewire::test(StudyProgramPage::class)
            ->callTableAction('edit', (string) $program['id'], [
                'name' => 'Bimbingan Konseling',
                'active' => false,
            ])
            ->assertHasNoTableActionErrors();

        $updated = $programs->find((string) $program['id']);

        $this->assertNotNull($updated);
        $this->assertSame('Bimbingan Konseling', $updated['name']);
        $this->assertSame('0', $updated['active']);

        Livewire::test(StudyProgramPage::class)
            ->callTableAction('delete', (string) $updated['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNull($programs->find((string) $program['id']));
    }

    public function test_program_in_use_cannot_be_deleted(): void
    {
        $programs = app(StudyProgramRepository::class);
        $program = $programs->findByCode('psikologi');

        $this->assertNotNull($program);

        Livewire::test(StudyProgramPage::class)
            ->callTableAction('delete', (string) $program['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($programs->find((string) $program['id']));
    }

    public function test_program_used_by_a_course_cannot_be_deleted(): void
    {
        $programs = app(StudyProgramRepository::class);
        $program = $programs->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);

        app(CourseRepository::class)->create([
            'code' => 'BK101',
            'name' => 'Dasar Bimbingan Konseling',
            'credits' => '3',
            'lecturer' => 'Dosen BK',
            'semester' => 'Ganjil',
            'class_name' => 'BK A',
            'description' => '',
            'prodi_ids' => (string) $program['id'],
        ]);

        Livewire::test(StudyProgramPage::class)
            ->callTableAction('delete', (string) $program['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($programs->find((string) $program['id']));
    }

    public function test_program_targeted_by_an_announcement_cannot_be_deleted(): void
    {
        $programs = app(StudyProgramRepository::class);
        $program = $programs->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);

        app(AnnouncementRepository::class)->create([
            'title' => 'Pengumuman BK',
            'body' => 'Khusus mahasiswa BK.',
            'audience' => 'mahasiswa',
            'prodi_ids' => (string) $program['id'],
            'published_at' => now()->toIso8601String(),
            'created_by' => '',
        ]);

        Livewire::test(StudyProgramPage::class)
            ->callTableAction('delete', (string) $program['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($programs->find((string) $program['id']));
    }

    public function test_inactive_program_is_not_available_for_login(): void
    {
        $programs = app(StudyProgramRepository::class);
        $program = $programs->findByCode('psikologi');

        $this->assertNotNull($program);

        $programs->update((string) $program['id'], ['active' => '0']);

        $this->assertArrayNotHasKey(
            (string) $program['id'],
            $programs->activeOptions(),
        );
    }

    public function test_assign_prodi_command_can_set_an_existing_user(): void
    {
        $users = app(UserRepository::class);
        $programs = app(StudyProgramRepository::class);
        $student = $users->findActiveByLogin('ayu@psikologi.test');
        $program = $programs->findByCode('psikologi');

        $this->assertNotNull($student);
        $this->assertNotNull($program);

        $users->update((string) $student['id'], ['prodi_id' => '']);

        $this->artisan('sheets:assign-prodi', [
            'login' => 'ayu@psikologi.test',
            '--prodi' => 'psikologi',
        ])->assertSuccessful();

        $updated = $users->find((string) $student['id']);

        $this->assertNotNull($updated);
        $this->assertSame((string) $program['id'], $updated['prodi_id']);
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
