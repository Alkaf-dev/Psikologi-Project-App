<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\PengumumanPage as AdminPengumumanPage;
use App\Filament\Portal\Pages\PengumumanPage as PortalPengumumanPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AnnouncementCourseFilterTest extends TestCase
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

    public function test_admin_can_filter_announcements_by_course_and_public(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');
        $this->assertNotNull($course);

        // Create a public announcement (empty course_id)
        $publicAnnounce = app(AnnouncementRepository::class)->create([
            'title' => 'Pengumuman Publik Test',
            'body' => 'Isi pengumuman publik',
            'audience' => 'all',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'created_by' => 'admin-1',
            'course_id' => '',
        ]);

        // Create a course-specific announcement
        $courseAnnounce = app(AnnouncementRepository::class)->create([
            'title' => 'Pengumuman Matkul PSI101',
            'body' => 'Isi pengumuman khusus PSI101',
            'audience' => 'all',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'created_by' => 'admin-1',
            'course_id' => (string) $course['id'],
        ]);

        $test = Livewire::test(AdminPengumumanPage::class);

        // Initially 'all' filter is active, both should be present
        $test->assertSee('Semua')
            ->assertSee('Publik')
            ->assertSee($course['name'])
            ->assertSee('Pengumuman Publik Test')
            ->assertSee('Pengumuman Matkul PSI101');

        // Select 'public' filter
        $test->call('selectFilter', 'public')
            ->assertSet('selectedFilter', 'public')
            ->assertSee('Pengumuman Publik Test')
            ->assertDontSee('Pengumuman Matkul PSI101');

        // Select course filter
        $test->call('selectFilter', (string) $course['id'])
            ->assertSet('selectedFilter', (string) $course['id'])
            ->assertSee('Pengumuman Matkul PSI101')
            ->assertDontSee('Pengumuman Publik Test');

        // Select 'all' filter again
        $test->call('selectFilter', 'all')
            ->assertSet('selectedFilter', 'all')
            ->assertSee('Pengumuman Publik Test')
            ->assertSee('Pengumuman Matkul PSI101');
    }

    public function test_admin_can_create_announcement_with_course_id(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');
        $this->assertNotNull($course);

        Livewire::test(AdminPengumumanPage::class)
            ->callTableAction('create', null, [
                'title' => 'Pengumuman Course Baru',
                'audience' => 'all',
                'course_id' => (string) $course['id'],
                'published_at' => now()->format('Y-m-d H:i:s'),
                'body' => 'Isi pengumuman dengan course_id.',
            ])
            ->assertHasNoTableActionErrors();

        $created = app(AnnouncementRepository::class)->findBy('title', 'Pengumuman Course Baru');
        $this->assertNotNull($created);
        $this->assertSame((string) $course['id'], $created['course_id']);
    }

    public function test_student_in_portal_can_filter_announcements_by_course_and_public(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        $course = app(CourseRepository::class)->findBy('code', 'PSI101');
        $this->assertNotNull($course);

        // Create a public announcement
        app(AnnouncementRepository::class)->create([
            'title' => 'Portal Publik Pengumuman',
            'body' => 'Pengumuman publik mahasiswa',
            'audience' => 'mahasiswa',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'created_by' => 'admin-1',
            'course_id' => '',
        ]);

        // Create a course announcement
        app(AnnouncementRepository::class)->create([
            'title' => 'Portal PSI101 Pengumuman',
            'body' => 'Pengumuman matkul PSI101 mahasiswa',
            'audience' => 'mahasiswa',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'created_by' => 'admin-1',
            'course_id' => (string) $course['id'],
        ]);

        $test = Livewire::test(PortalPengumumanPage::class);

        // Initially 'all' filter is active
        $test->assertSee('Semua')
            ->assertSee('Publik')
            ->assertSee('Portal Publik Pengumuman')
            ->assertSee('Portal PSI101 Pengumuman');

        // Select 'public' filter
        $test->call('selectFilter', 'public')
            ->assertSet('selectedFilter', 'public')
            ->assertSee('Portal Publik Pengumuman')
            ->assertDontSee('Portal PSI101 Pengumuman');

        // Select course filter
        $test->call('selectFilter', (string) $course['id'])
            ->assertSet('selectedFilter', (string) $course['id'])
            ->assertSee('Portal PSI101 Pengumuman')
            ->assertDontSee('Portal Publik Pengumuman');
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
