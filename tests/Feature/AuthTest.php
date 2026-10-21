<?php

namespace Tests\Feature;

use App\Auth\SheetUser;
use App\Auth\SheetUserProvider;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AuthTest extends TestCase
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

    public function test_login_pages_render_with_login_and_prodi_fields(): void
    {
        $this->get('/portal/login')
            ->assertOk()
            ->assertSee('Email atau NIM')
            ->assertSee('Prodi')
            ->assertSee('S1 Psikologi');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Email atau NIM')
            ->assertSee('Prodi')
            ->assertSee('S1 Psikologi');
    }

    public function test_forwarded_https_requests_generate_secure_asset_urls(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/portal/login')
            ->assertOk()
            ->assertSee('https://localhost:8000/css/filament/filament/app.css', false);
    }

    public function test_login_route_redirects_to_portal_login(): void
    {
        $this->get('/login')
            ->assertRedirect(route('filament.portal.auth.login'));
    }

    public function test_user_can_be_retrieved_and_validated_by_email_or_npm(): void
    {
        $provider = new SheetUserProvider(app(UserRepository::class));

        foreach (['ayu@psikologi.test', '2024001'] as $login) {
            $user = $provider->retrieveByCredentials([
                'login' => $login,
                'password' => 'password',
                'prodi_id' => $this->prodiId(),
            ]);

            $this->assertInstanceOf(SheetUser::class, $user);
            $this->assertTrue($provider->validateCredentials($user, ['password' => 'password']));
            $this->assertFalse($provider->validateCredentials($user, ['password' => 'salah']));
        }
    }

    public function test_user_requires_a_matching_prodi(): void
    {
        $provider = new SheetUserProvider(app(UserRepository::class));
        $program = app(StudyProgramRepository::class)->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $this->assertNull($provider->retrieveByCredentials([
            'login' => 'ayu@psikologi.test',
            'prodi_id' => (string) $program['id'],
        ]));

        $this->assertNull($provider->retrieveByCredentials([
            'login' => 'ayu@psikologi.test',
        ]));
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $users = app(UserRepository::class);
        $ayu = $users->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($ayu);

        $users->update((string) $ayu['id'], ['active' => '0']);

        $provider = new SheetUserProvider($users);

        $this->assertNull($provider->retrieveByCredentials([
            'login' => 'ayu@psikologi.test',
            'password' => 'password',
            'prodi_id' => $this->prodiId(),
        ]));
    }

    public function test_roles_are_limited_to_their_panel(): void
    {
        $provider = new SheetUserProvider(app(UserRepository::class));
        $admin = $provider->retrieveByCredentials([
            'login' => 'admin@psikologi.test',
            'prodi_id' => $this->prodiId(),
        ]);
        $pjmk = $provider->retrieveByCredentials([
            'login' => 'pjmk@psikologi.test',
            'prodi_id' => $this->prodiId(),
        ]);
        $student = $provider->retrieveByCredentials([
            'login' => 'ayu@psikologi.test',
            'prodi_id' => $this->prodiId(),
        ]);

        $this->assertNotNull($admin);
        $this->assertNotNull($pjmk);
        $this->assertNotNull($student);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('portal')));
        $this->assertTrue($pjmk->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($pjmk->canAccessPanel(Filament::getPanel('portal')));
        $this->assertTrue($student->canAccessPanel(Filament::getPanel('portal')));
        $this->assertFalse($student->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_legacy_admin_role_keeps_admin_access(): void
    {
        $users = app(UserRepository::class);
        $admin = $users->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        $users->update((string) $admin['id'], ['role' => 'admin']);

        $user = (new SheetUserProvider($users))->retrieveById((string) $admin['id']);

        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_unknown_role_has_no_panel_access(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($student);

        $users->update((string) $student['id'], ['role' => 'unknown']);

        $user = (new SheetUserProvider($users))->retrieveById((string) $student['id']);

        $this->assertNotNull($user);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isStudent());
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('portal')));
    }

    protected function prodiId(): string
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($program);

        return (string) $program['id'];
    }
}
