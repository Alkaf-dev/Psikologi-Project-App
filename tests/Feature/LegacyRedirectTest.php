<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class LegacyRedirectTest extends TestCase
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

    public function test_legacy_login_and_panel_urls_redirect_to_the_unified_app(): void
    {
        $this->get('/login')->assertRedirect('/app/login');
        $this->get('/admin')->assertRedirect('/app');
        $this->get('/admin/login')->assertRedirect('/app/login');
        $this->get('/portal')->assertRedirect('/app');
        $this->get('/portal/login')->assertRedirect('/app/login');
    }

    public function test_legacy_staff_page_urls_redirect_inside_the_app_panel(): void
    {
        $this->get('/admin/tugas-page')->assertRedirect('/app/tugas-page');
        $this->get('/admin/notifikasi-page?filter=unread')
            ->assertRedirect('/app/notifikasi-page?filter=unread');
    }

    public function test_legacy_student_page_urls_redirect_to_student_slugs(): void
    {
        $this->get('/portal/jadwal-page')->assertRedirect('/app/jadwal-saya');
        $this->get('/portal/tugas-page')->assertRedirect('/app/tugas-saya');
        $this->get('/portal/kelompok-page')->assertRedirect('/app/kelompok-saya');
        $this->get('/portal/pengumuman-page')->assertRedirect('/app/pengumuman-saya');
        $this->get('/portal/notifikasi-page')->assertRedirect('/app/notifikasi-page');
        $this->get('/portal/profil-page')->assertRedirect('/app/profil-saya');
    }
}
