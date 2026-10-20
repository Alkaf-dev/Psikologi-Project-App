<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class FilamentPagesTest extends TestCase
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

    public function test_admin_pages_render(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin')->assertOk()->assertSee('Dashboard Admin');

        foreach ([
            '/admin/student-page' => 'Pendataan Mahasiswa',
            '/admin/user-page' => 'Manajemen User',
            '/admin/study-program-page' => 'Program Studi',
            '/admin/mata-kuliah-page' => 'Mata Kuliah',
            '/admin/jadwal-page' => 'Jadwal Mata Kuliah',
            '/admin/kelompok-page' => 'Kelompok Mahasiswa',
            '/admin/tugas-page' => 'Manajemen Tugas',
            '/admin/pengumpulan-page' => 'Pengumpulan & Penilaian',
            '/admin/pengumuman-page' => 'Pengumuman',
            '/admin/pengaturan-page' => 'Pengaturan Aplikasi',
        ] as $url => $text) {
            $this->get($url)->assertOk()->assertSee($text);
        }
    }

    public function test_navigation_groups_are_ordered_from_most_to_least_important(): void
    {
        $this->assertSame(
            ['Akademik', 'Administrasi', 'Konten', 'Akun', 'Sistem'],
            $this->navigationGroupLabels('admin'),
        );
        $this->assertSame(
            ['Akademik', 'Informasi', 'Akun'],
            $this->navigationGroupLabels('portal'),
        );
    }

    public function test_student_page_shows_prodi_name_and_nim_columns(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/student-page')
            ->assertOk()
            ->assertSee('Prodi')
            ->assertSee('Nama Mahasiswa')
            ->assertSee('NIM');
    }

    public function test_pjmk_can_access_user_management_page(): void
    {
        $pjmk = app(UserRepository::class)->findActiveByLogin('pjmk@psikologi.test');

        $this->assertNotNull($pjmk);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $pjmk['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);

        $this->get('/admin/user-page')
            ->assertOk()
            ->assertSee('Manajemen User');
    }

    public function test_portal_pages_render(): void
    {
        $this->actingAsStudent();

        $this->get('/portal')->assertOk()->assertSee('Portal Mahasiswa');

        foreach ([
            '/portal/jadwal-page' => 'Jadwal Kuliah',
            '/portal/tugas-page' => 'Tugas Saya',
            '/portal/kelompok-page' => 'Kelompok Saya',
            '/portal/pengumuman-page' => 'Pengumuman',
            '/portal/profil-page' => 'Profil Saya',
        ] as $url => $text) {
            $this->get($url)->assertOk()->assertSee($text);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function navigationGroupLabels(string $panel): array
    {
        return collect(Filament::getPanel($panel)->getNavigationGroups())
            ->map(fn ($group): string => (string) $group->getLabel())
            ->values()
            ->all();
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
