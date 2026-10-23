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

        $this->get('/app')->assertOk()->assertSee('Dashboard Admin');

        foreach ([
            '/app/student-page' => 'Pendataan Mahasiswa',
            '/app/user-page' => 'Manajemen User',
            '/app/study-program-page' => 'Program Studi',
            '/app/mata-kuliah-page' => 'Mata Kuliah',
            '/app/jadwal-page' => 'Jadwal Mata Kuliah',
            '/app/kelompok-page' => 'Kelompok Mahasiswa',
            '/app/tugas-page' => 'Materi dan Tugas',
            '/app/pengumpulan-page' => 'Pengumpulan',
            '/app/pengumuman-page' => 'Pengumuman',
            '/app/notifikasi-page' => 'Notifikasi',
            '/app/pengaturan-page' => 'Pengaturan Aplikasi',
        ] as $url => $text) {
            $this->get($url)->assertOk()->assertSee($text);
        }
    }

    public function test_navigation_groups_are_ordered_from_most_to_least_important(): void
    {
        $this->assertSame(
            ['Akademik', 'Administrasi', 'Konten', 'Informasi', 'Akun', 'Sistem'],
            $this->navigationGroupLabels('app'),
        );
    }

    public function test_student_page_shows_prodi_name_and_nim_columns(): void
    {
        $this->actingAsAdmin();

        $this->get('/app/student-page')
            ->assertOk()
            ->assertSee('Prodi')
            ->assertSee('Nama Mahasiswa')
            ->assertSee('NIM');
    }

    public function test_admin_can_access_user_management_page(): void
    {
        $pjmk = app(UserRepository::class)->findActiveByLogin('pjmk@psikologi.test');

        $this->assertNotNull($pjmk);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $pjmk['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);

        $this->get('/app/user-page')
            ->assertOk()
            ->assertSee('Manajemen User');
    }

    public function test_student_pages_render(): void
    {
        $this->actingAsStudent();

        $this->get('/app')->assertOk()->assertSee('Portal Mahasiswa');

        foreach ([
            '/app/jadwal-saya' => 'Jadwal Kuliah',
            '/app/tugas-saya' => 'Materi dan Tugas',
            '/app/kelompok-saya' => 'Kelompok Saya',
            '/app/pengumuman-saya' => 'Pengumuman',
            '/app/notifikasi-page' => 'Notifikasi',
            '/app/profil-saya' => 'Profil Saya',
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
