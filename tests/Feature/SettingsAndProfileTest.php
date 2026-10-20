<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\PengaturanPage;
use App\Filament\Portal\Pages\ProfilPage;
use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class SettingsAndProfileTest extends TestCase
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

    public function test_admin_can_save_settings(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        Livewire::test(PengaturanPage::class)
            ->fillForm([
                'program_name' => 'Program Studi Psikologi Baru',
                'academic_year' => '2027/2028',
                'semester' => 'Genap',
                'contact_email' => 'baru@example.ac.id',
                'contact_phone' => '081200000001',
                'public_tagline' => 'Tagline baru',
                'public_about' => 'Tentang program baru.',
                'portal_note' => 'Catatan portal baru.',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SettingsRepository::class);

        $this->assertSame('Program Studi Psikologi Baru', $settings->get('program_name'));
        $this->assertSame('2027/2028', $settings->get('academic_year'));
    }

    public function test_student_can_update_profile_and_password(): void
    {
        Filament::setCurrentPanel('portal');
        $student = $this->actingAsStudent();

        Livewire::test(ProfilPage::class)
            ->fillForm([
                'name' => 'Ayu Diubah',
                'phone' => '081299999999',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $updated = app(UserRepository::class)->find((string) $student['id']);

        $this->assertSame('Ayu Diubah', $updated['name']);
        $this->assertSame('081299999999', $updated['phone']);

        Livewire::test(ProfilPage::class)
            ->fillForm([
                'current_password' => 'password-salah',
                'new_password' => 'password-baru',
                'new_password_confirmation' => 'password-baru',
            ])
            ->call('save')
            ->assertHasErrors('data.current_password');

        Livewire::test(ProfilPage::class)
            ->fillForm([
                'current_password' => 'password',
                'new_password' => 'password-baru',
                'new_password_confirmation' => 'password-baru',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $updated = app(UserRepository::class)->find((string) $student['id']);

        $this->assertTrue(Hash::check('password-baru', (string) $updated['password_hash']));
    }

    protected function actingAsAdmin(): void
    {
        $admin = app(UserRepository::class)->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $admin['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function actingAsStudent(): array
    {
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($student);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $student['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);

        return $student;
    }
}
