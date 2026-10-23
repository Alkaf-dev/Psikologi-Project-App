<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class LoginFlowTest extends TestCase
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

    public function test_student_can_log_in_with_email(): void
    {
        $this->assertLoginWorks('ayu@psikologi.test');
    }

    public function test_student_can_log_in_with_npm(): void
    {
        $this->assertLoginWorks('2024001');
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'ayu@psikologi.test',
                'password' => 'password-salah',
                'prodi_id' => $this->prodiId(),
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_prodi_is_required_to_log_in(): void
    {
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'ayu@psikologi.test',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['prodi_id']);

        $this->assertGuest();
    }

    public function test_user_cannot_log_in_with_the_wrong_prodi(): void
    {
        $program = app(StudyProgramRepository::class)->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);

        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'ayu@psikologi.test',
                'password' => 'password',
                'prodi_id' => (string) $program['id'],
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_user_without_prodi_cannot_log_in(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($student);

        $users->update((string) $student['id'], ['prodi_id' => '']);

        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'ayu@psikologi.test',
                'password' => 'password',
                'prodi_id' => $this->prodiId(),
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_admin_can_log_in_to_the_app_panel_with_prodi(): void
    {
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'admin@psikologi.test',
                'password' => 'password',
                'prodi_id' => $this->prodiId(),
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isAdmin());
    }

    public function test_pjmk_can_log_in_as_an_admin_with_prodi(): void
    {
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'pjmk@psikologi.test',
                'password' => 'password',
                'prodi_id' => $this->prodiId(),
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isAdministrator());
        $this->assertTrue(auth()->user()->isAdmin());
    }

    public function test_unknown_role_cannot_log_in(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($student);

        $users->update((string) $student['id'], ['role' => 'unknown']);

        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'ayu@psikologi.test',
                'password' => 'password',
                'prodi_id' => $this->prodiId(),
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    protected function assertLoginWorks(string $login): void
    {
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => $login,
                'password' => 'password',
                'prodi_id' => $this->prodiId(),
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    protected function prodiId(): string
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($program);

        return (string) $program['id'];
    }
}
