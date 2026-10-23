<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\MataKuliahPage;
use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class CourseOnlineLinkTest extends TestCase
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

    public function test_admin_can_create_course_with_online_meeting_link(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAsAdmin();

        $program = app(StudyProgramRepository::class)->findByCode('psikologi');
        $this->assertNotNull($program);

        Livewire::test(MataKuliahPage::class)
            ->callTableAction('create', null, [
                'code' => 'PSI777',
                'name' => 'Psikologi Daring',
                'credits' => 3,
                'lecturer' => 'Dr. Maya Daring',
                'semester' => 'Ganjil',
                'class_name' => 'Psikologi A',
                'prodi_ids' => [(string) $program['id']],
                'meeting_link' => 'https://meet.google.com/psi-777-online',
                'description' => 'Perkuliahan daring via Google Meet.',
            ])
            ->assertHasNoTableActionErrors();

        $course = app(CourseRepository::class)->findBy('code', 'PSI777');
        $this->assertNotNull($course);
        $this->assertSame('https://meet.google.com/psi-777-online', $course['meeting_link']);

        Livewire::test(MataKuliahPage::class)
            ->assertCanSeeTableRecords([(string) $course['id']])
            ->assertSee('Masuk')
            ->assertSee('https://meet.google.com/psi-777-online', false);
    }

    public function test_admin_can_update_course_online_meeting_link(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAsAdmin();

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');
        $this->assertNotNull($course);

        Livewire::test(MataKuliahPage::class)
            ->callTableAction('edit', (string) $course['id'], [
                'code' => (string) $course['code'],
                'name' => (string) $course['name'],
                'credits' => (int) $course['credits'],
                'lecturer' => (string) $course['lecturer'],
                'semester' => (string) $course['semester'],
                'class_name' => (string) $course['class_name'],
                'prodi_ids' => explode(',', (string) $course['prodi_ids']),
                'meeting_link' => 'https://zoom.us/j/555666777',
            ])
            ->assertHasNoTableActionErrors();

        $updated = app(CourseRepository::class)->find((string) $course['id']);
        $this->assertNotNull($updated);
        $this->assertSame('https://zoom.us/j/555666777', $updated['meeting_link']);

        Livewire::test(MataKuliahPage::class)
            ->assertCanSeeTableRecords([(string) $course['id']])
            ->assertSee('Masuk')
            ->assertSee('https://zoom.us/j/555666777', false);
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
