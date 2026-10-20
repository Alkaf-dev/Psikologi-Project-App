<?php

namespace Tests\Feature;

use App\Repositories\AnnouncementRepository;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class PublicPagesTest extends TestCase
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

    public function test_home_page_renders_public_information(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Psikologi Connect')
            ->assertSee('STIT Muhammadiyah Kediri')
            ->assertSee('Selamat datang di Psikologi Connect');
    }

    public function test_announcement_index_renders(): void
    {
        $this->get('/pengumuman')
            ->assertOk()
            ->assertSee('Pengumuman');
    }

    public function test_announcement_detail_renders(): void
    {
        $announcement = app(AnnouncementRepository::class)->published('all')->first();

        $this->assertNotNull($announcement);

        $this->get('/pengumuman/'.$announcement['id'])
            ->assertOk()
            ->assertSee($announcement['title']);
    }

    public function test_public_pages_hide_student_and_admin_announcements(): void
    {
        $announcement = app(AnnouncementRepository::class)->findBy('title', 'Pengisian KRS Semester Ganjil');

        $this->assertNotNull($announcement);

        $this->get('/pengumuman')
            ->assertOk()
            ->assertDontSee($announcement['title']);

        $this->get('/pengumuman/'.$announcement['id'])->assertNotFound();
    }

    public function test_unknown_announcement_returns_404(): void
    {
        $this->get('/pengumuman/tidak-ada')->assertNotFound();
    }
}
