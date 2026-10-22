<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Repositories\CourseRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\NotificationCenter;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class PsychologyMascotTest extends TestCase
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

    public function test_all_fallback_assets_are_available(): void
    {
        foreach ([
            'idle', 'greeting', 'studying', 'thinking', 'success', 'supportive', 'sleepy',
        ] as $state) {
            $this->assertFileExists(public_path("images/psychology/mascot/{$state}.png"));
        }
    }

    public function test_launcher_gif_assets_are_compact_and_available(): void
    {
        $toggle = public_path('images/psychology/mascot/berjalan-toggle.gif');
        $poster = public_path('images/psychology/mascot/berjalan-poster.png');

        $this->assertFileExists($toggle);
        $this->assertFileExists($poster);

        $image = getimagesize($toggle);
        $this->assertIsArray($image);
        $this->assertSame('image/gif', $image['mime']);
        $this->assertSame(56, $image[0]);
        $this->assertSame(100, $image[1]);
        $this->assertLessThan(400_000, filesize($toggle));
        $this->assertSame(8000, $this->gifTotalDurationMs($toggle));

        $posterImage = getimagesize($poster);
        $this->assertIsArray($posterImage);
        $this->assertSame('image/png', $posterImage['mime']);
        $this->assertSame(56, $posterImage[0]);
        $this->assertSame(100, $posterImage[1]);
        $this->assertLessThan(50_000, filesize($poster));

        foreach (['melambai', 'laptop', 'memeluk-hati', 'berpikir', 'tertidur'] as $gif) {
            $this->assertFileExists(public_path("images/psychology/mascot/{$gif}.gif"));
        }
    }

    public function test_mascot_component_renders_a_whitelisted_state_and_fallback_asset(): void
    {
        $html = Blade::render('<x-psychology-mascot animation="greeting" size="96px" label="Maskot uji" />');

        $this->assertStringContainsString('data-psychology-mascot', $html);
        $this->assertStringContainsString('data-mascot-state="greeting"', $html);
        $this->assertStringContainsString('images/psychology/mascot/greeting.png', $html);
        $this->assertStringContainsString('--psychology-mascot-size: 96px', $html);
        $this->assertStringContainsString('aria-label="Maskot uji"', $html);
    }

    public function test_mascot_component_rejects_unknown_states_and_invalid_sizes(): void
    {
        $html = Blade::render('<x-psychology-mascot animation="../../etc/passwd" size="expression(alert(1))" />');

        $this->assertStringContainsString('data-mascot-state="idle"', $html);
        $this->assertStringContainsString('--psychology-mascot-size: 120px', $html);
        $this->assertStringNotContainsString('expression', $html);
        $this->assertStringNotContainsString('/etc/passwd', $html);
    }

    public function test_mascot_component_accepts_numeric_sizes(): void
    {
        $html = Blade::render('<x-psychology-mascot :size="88" />');

        $this->assertStringContainsString('--psychology-mascot-size: 88px', $html);
    }

    public function test_contextual_gif_assets_are_available_and_correctly_sized(): void
    {
        $config = config('psychology-mascots');

        $this->assertIsArray($config);
        $this->assertCount(11, $config);

        foreach ($config as $key => $mascot) {
            $path = public_path((string) $mascot['file']);

            $this->assertFileExists($path, "Aset {$key} tidak ditemukan.");

            $image = getimagesize($path);
            $size = filesize($path);

            $this->assertIsArray($image);
            $this->assertSame('image/gif', $image['mime']);
            $this->assertSame(
                in_array($key, ['laptop', 'tertidur'], true) ? 180 : 112,
                $image[0],
                "Lebar {$key} tidak sesuai.",
            );
            $this->assertSame(
                in_array($key, ['laptop', 'tertidur'], true) ? 101 : 200,
                $image[1],
                "Tinggi {$key} tidak sesuai.",
            );
            $this->assertIsInt($size);
            $this->assertLessThan(2_000_000, $size, "Ukuran {$key} terlalu besar.");
            $this->assertSame(
                $key === 'menunjuk' ? 7200 : 8000,
                $this->gifTotalDurationMs($path),
                "Durasi {$key} tidak sesuai.",
            );
        }
    }

    public function test_contextual_gif_component_renders_the_configured_copy(): void
    {
        $html = Blade::render('<x-psychology-mascot-gif mascot="membaca" />');

        $this->assertStringContainsString('data-mascot-gif="membaca"', $html);
        $this->assertStringContainsString('psychology-mascot-gif--banner', $html);
        $this->assertStringContainsString('images/psychology/mascot/membaca.gif', $html);
        $this->assertStringContainsString('Baca tugas dengan saksama.', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('width="112"', $html);
        $this->assertStringContainsString('height="200"', $html);
    }

    public function test_contextual_gif_component_keeps_landscape_dimensions_and_empty_variant(): void
    {
        $html = Blade::render('<x-psychology-mascot-gif mascot="laptop" variant="empty" title="Judul uji" message="Pesan uji" />');

        $this->assertStringContainsString('psychology-mascot-gif--empty', $html);
        $this->assertStringContainsString('images/psychology/mascot/laptop.gif', $html);
        $this->assertStringContainsString('width="180"', $html);
        $this->assertStringContainsString('height="101"', $html);
        $this->assertStringContainsString('Judul uji', $html);
        $this->assertStringContainsString('Pesan uji', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_contextual_gif_component_rejects_unknown_mascots(): void
    {
        $html = Blade::render('<x-psychology-mascot-gif mascot="../../etc/passwd" />');

        $this->assertStringNotContainsString('data-mascot-gif', $html);
        $this->assertStringNotContainsString('/etc/passwd', $html);
    }

    public function test_admin_sheet_pages_render_one_expected_contextual_gif(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $pages = [
            '/admin/mata-kuliah-page' => 'memegang-buku.gif',
            '/admin/student-page' => 'memegang-buku.gif',
            '/admin/tugas-page' => 'membaca.gif',
            '/admin/jadwal-page' => 'berjalan.gif',
            '/admin/kelompok-page' => 'semangat.gif',
            '/admin/pengumpulan-page' => 'menunjuk.gif',
            '/admin/pengumuman-page' => 'menunjuk.gif',
            '/admin/study-program-page' => 'berpikir.gif',
        ];

        foreach ($pages as $route => $gif) {
            $response = $this->get($route)->assertOk();

            $response->assertSee($gif, false);
            $this->assertSame(
                1,
                substr_count((string) $response->getContent(), 'data-mascot-gif="'),
                "Jumlah GIF pada {$route} tidak sesuai.",
            );
        }
    }

    public function test_portal_pages_render_one_expected_contextual_gif(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        $pages = [
            '/portal/tugas-page' => 'membaca.gif',
            '/portal/jadwal-page' => 'berjalan.gif',
            '/portal/pengumuman-page' => 'menunjuk.gif',
            '/portal/kelompok-page' => 'semangat.gif',
        ];

        foreach ($pages as $route => $gif) {
            $response = $this->get($route)->assertOk();

            $response->assertSee($gif, false);
            $this->assertSame(
                1,
                substr_count((string) $response->getContent(), 'data-mascot-gif="'),
                "Jumlah GIF pada {$route} tidak sesuai.",
            );
        }
    }

    public function test_portal_dashboard_hides_minum_note_at_three_pending_tasks(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();
        $this->createPendingTask('Tugas Tambahan Batas Tiga');
        $this->createPendingTask('Tugas Tambahan Batas Tiga Lainnya');

        $this->get('/portal')
            ->assertOk()
            ->assertDontSee('data-mascot-gif="minum"', false)
            ->assertDontSee('minum.gif', false)
            ->assertDontSee('Ngopi dulu, santai sebentar.', false);
    }

    public function test_portal_dashboard_shows_minum_note_above_three_pending_tasks(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();
        $this->createPendingTask('Tugas Tambahan Batas Empat');
        $this->createPendingTask('Tugas Tambahan Batas Empat Lainnya');
        $this->createPendingTask('Tugas Tambahan Batas Empat Ketiga');

        $response = $this->get('/portal')->assertOk();
        $html = (string) $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-mascot-gif="minum"'));
        $this->assertStringContainsString('data-mascot-gif="minum"', $html);
        $this->assertStringContainsString('images/psychology/mascot/minum.gif', $html);
        $this->assertStringContainsString('Ngopi dulu, santai sebentar.', $html);
        $this->assertStringContainsString(
            'Banyak tugas menanti, tetapi semuanya bisa dikerjakan satu per satu. Isi energi dulu, ya.',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<img[^>]+src="[^"]*minum\.gif"[^>]+loading="lazy"/',
            $html,
        );
    }

    public function test_notification_empty_state_uses_the_heart_gif(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $admin = app(UserRepository::class)->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        app(NotificationCenter::class)->markAllAsRead($admin);

        $this->get('/admin/notifikasi-page?filter=unread')
            ->assertOk()
            ->assertSee('images/psychology/mascot/memeluk-hati.gif', false)
            ->assertSee('psychology-mascot-gif--empty', false)
            ->assertSee('Semua notifikasi sudah dibaca', false);
    }

    public function test_both_login_pages_render_the_laptop_gif(): void
    {
        Filament::setCurrentPanel('admin');
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('data-mascot-gif="laptop"', false)
            ->assertSee('images/psychology/mascot/laptop.gif', false);

        Filament::setCurrentPanel('portal');
        $this->get('/portal/login')
            ->assertOk()
            ->assertSee('data-mascot-gif="laptop"', false)
            ->assertSee('images/psychology/mascot/laptop.gif', false);
    }

    public function test_admin_dashboard_renders_the_greeting_with_the_waving_gif(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->get('/admin')
            ->assertOk()
            ->assertSee('data-mascot-greeting', false)
            ->assertSee('data-mascot-greeting-key="admin-dashboard"', false)
            ->assertSee('images/psychology/mascot/melambai.gif', false)
            ->assertSee('Maskot Psikologi Connect melambai', false)
            ->assertSee('psychology-mascot:celebrate', false)
            ->assertSee('.psychology-mascot', false);
    }

    public function test_admin_greeting_gif_asset_is_available_and_lightweight(): void
    {
        $path = public_path('images/psychology/mascot/melambai.gif');

        $this->assertFileExists($path);

        $image = getimagesize($path);
        $size = filesize($path);

        $this->assertIsArray($image);
        $this->assertSame(112, $image[0]);
        $this->assertSame(200, $image[1]);
        $this->assertSame('image/gif', $image['mime']);
        $this->assertIsInt($size);
        $this->assertLessThan(2_000_000, $size);
    }

    public function test_portal_dashboard_renders_the_greeting(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        $this->get('/portal')
            ->assertOk()
            ->assertSee('data-mascot-greeting', false)
            ->assertSee('data-mascot-greeting-key="portal-dashboard"', false)
            ->assertSee('images/psychology/mascot/melambai.gif', false)
            ->assertSee('Maskot Psikologi Connect melambai', false)
            ->assertDontSee('images/psychology/mascot/greeting.png', false);
    }

    public function test_admin_task_page_renders_the_launcher(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->get('/admin/tugas-page')
            ->assertOk()
            ->assertSee('data-mascot-launcher', false)
            ->assertSee('data-mascot-context="admin-tasks"', false)
            ->assertSee('Buka bantuan maskot', false);
    }

    public function test_portal_task_page_renders_the_launcher(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        $this->get('/portal/tugas-page')
            ->assertOk()
            ->assertSee('data-mascot-launcher', false)
            ->assertSee('data-mascot-context="portal-tasks"', false)
            ->assertSee('Buka bantuan maskot', false);
    }

    public function test_launcher_defers_panel_gifs_until_interaction(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $response = $this->get('/admin/tugas-page')->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('berjalan-toggle.gif', $html);
        $this->assertStringContainsString('berjalan-poster.png', $html);
        $this->assertStringContainsString('melambai.gif', $html);
        $this->assertStringContainsString('laptop.gif', $html);
        $this->assertStringContainsString('memeluk-hati.gif', $html);
        $this->assertStringContainsString('berpikir.gif', $html);
        $this->assertStringContainsString('tertidur.gif', $html);
        $this->assertStringContainsString(
            'src="'.asset('images/psychology/mascot/berjalan-poster.png').'"',
            $html,
        );

        foreach (['melambai', 'laptop', 'memeluk-hati', 'berpikir', 'tertidur'] as $gif) {
            $this->assertStringNotContainsString(
                'src="'.asset("images/psychology/mascot/{$gif}.gif").'"',
                $html,
                "GIF {$gif} tidak boleh dimuat sebelum interaksi.",
            );
        }
    }

    public function test_other_sheet_pages_do_not_render_the_launcher(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->get('/admin/student-page')
            ->assertOk()
            ->assertDontSee('class="psychology-launcher"', false);
    }

    private function gifTotalDurationMs(string $path): int
    {
        $data = file_get_contents($path);

        $this->assertIsString($data);

        $length = strlen($data);
        $position = 13;
        $packed = ord($data[10]);

        if (($packed & 0x80) !== 0) {
            $position += 3 * (2 ** (($packed & 0x07) + 1));
        }

        $total = 0;

        while ($position < $length) {
            $block = ord($data[$position]);

            if ($block === 0x3B) {
                break;
            }

            if ($block === 0x21) {
                $label = ord($data[$position + 1]);
                $position += 2;

                if ($label === 0xF9) {
                    $size = ord($data[$position]);
                    $delay = unpack('v', substr($data, $position + 2, 2));

                    $this->assertIsArray($delay);

                    $total += $delay[1] * 10;
                    $position += $size + 2;

                    continue;
                }

                $position = $this->skipGifSubBlocks($data, $position);

                continue;
            }

            $this->assertSame(0x2C, $block);
            $position += 10;
            $packed = ord($data[$position - 1]);

            if (($packed & 0x80) !== 0) {
                $position += 3 * (2 ** (($packed & 0x07) + 1));
            }

            $position++;
            $position = $this->skipGifSubBlocks($data, $position);
        }

        return $total;
    }

    private function skipGifSubBlocks(string $data, int $position): int
    {
        $length = strlen($data);

        while ($position < $length) {
            $size = ord($data[$position]);
            $position++;

            if ($size === 0) {
                break;
            }

            $position += $size;
        }

        return $position;
    }

    /**
     * @return array<string, mixed>
     */
    private function createPendingTask(string $title): array
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI101');

        $this->assertNotNull($course);

        return app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => '',
            'group_ids' => '',
            'meeting_number' => '1',
            'title' => $title,
            'description' => '',
            'type' => 'individu',
            'start_at' => now()->toIso8601String(),
            'deadline' => now()->addDays(2)->toIso8601String(),
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

    protected function actingAsStudent(): void
    {
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($student);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $student['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }
}
