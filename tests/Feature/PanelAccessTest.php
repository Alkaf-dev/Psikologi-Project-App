<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\NotifikasiPage;
use App\Filament\Pages\StudentPage;
use App\Filament\Portal\Pages\ProfilPage;
use App\Filament\Portal\Pages\TugasPage;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class PanelAccessTest extends TestCase
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

    public function test_student_cannot_open_staff_pages(): void
    {
        $this->actingAsUser('ayu@psikologi.test');

        foreach ([
            '/app/student-page',
            '/app/user-page',
            '/app/study-program-page',
            '/app/mata-kuliah-page',
            '/app/jadwal-page',
            '/app/kelompok-page',
            '/app/tugas-page',
            '/app/pengumpulan-page',
            '/app/pengumuman-page',
            '/app/pengaturan-page',
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->assertFalse(StudentPage::canAccess());
        $this->assertTrue(TugasPage::canAccess());
        $this->assertTrue(ProfilPage::canAccess());
        $this->assertTrue(NotifikasiPage::canAccess());
    }

    public function test_staff_cannot_open_student_pages(): void
    {
        $this->actingAsUser('admin@psikologi.test');

        foreach ([
            '/app/jadwal-saya',
            '/app/tugas-saya',
            '/app/kelompok-saya',
            '/app/pengumuman-saya',
            '/app/profil-saya',
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->assertTrue(StudentPage::canAccess());
        $this->assertFalse(TugasPage::canAccess());
        $this->assertFalse(ProfilPage::canAccess());
        $this->assertTrue(NotifikasiPage::canAccess());
    }

    public function test_admin_role_has_the_same_staff_access_as_super_admin(): void
    {
        $this->actingAsUser('pjmk@psikologi.test');

        foreach ([
            '/app',
            '/app/user-page',
            '/app/pengaturan-page',
            '/app/tugas-page',
            '/app/notifikasi-page',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_student_navigation_only_shows_personal_pages(): void
    {
        $this->actingAsUser('ayu@psikologi.test');

        $html = (string) $this->get('/app')->assertOk()->getContent();

        $this->assertStringContainsString('/app/tugas-saya', $html);
        $this->assertStringNotContainsString('/app/student-page', $html);
        $this->assertStringNotContainsString('/app/user-page', $html);
    }

    public function test_staff_navigation_only_shows_staff_pages(): void
    {
        $this->actingAsUser('admin@psikologi.test');

        $html = (string) $this->get('/app')->assertOk()->getContent();

        $this->assertStringContainsString('/app/student-page', $html);
        $this->assertStringContainsString('/app/tugas-page', $html);
        $this->assertStringNotContainsString('/app/tugas-saya', $html);
    }

    public function test_unknown_role_cannot_open_the_panel(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($student);

        $users->update((string) $student['id'], ['role' => 'unknown']);

        $this->actingAsUser('ayu@psikologi.test');

        $this->get('/app')->assertForbidden();
    }

    protected function actingAsUser(string $email): void
    {
        $data = app(UserRepository::class)->findActiveByLogin($email);

        $this->assertNotNull($data);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $data['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }
}
