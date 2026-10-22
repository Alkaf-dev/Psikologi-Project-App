<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Repositories\UserRepository;
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
            ->assertSee('images/psychology/mascot/greeting.png', false)
            ->assertDontSee('images/psychology/mascot/melambai.gif', false);
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

    public function test_other_sheet_pages_do_not_render_the_launcher(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->get('/admin/student-page')
            ->assertOk()
            ->assertDontSee('class="psychology-launcher"', false);
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
