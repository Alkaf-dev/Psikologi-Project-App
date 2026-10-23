<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\UserPage;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AdminCrudActionTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        Filament::setCurrentPanel('app');
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_admin_can_create_a_student(): void
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($program);

        Livewire::test(UserPage::class)
            ->callTableAction('create', null, [
                'name' => 'Mahasiswa Baru',
                'npm' => '9990001',
                'email' => 'mahasiswa.baru@psikologi.test',
                'role' => 'mahasiswa',
                'prodi_id' => (string) $program['id'],
                'password' => 'password123',
                'class_name' => 'Psikologi A',
                'cohort' => '2026',
                'phone' => '081200000000',
                'active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $student = app(UserRepository::class)->findActiveByLogin('mahasiswa.baru@psikologi.test');

        $this->assertNotNull($student);
        $this->assertSame('Mahasiswa Baru', $student['name']);
        $this->assertSame('mahasiswa', $student['role']);
        $this->assertSame((string) $program['id'], $student['prodi_id']);
        $this->assertTrue(Hash::check('password123', (string) $student['password_hash']));
    }

    public function test_admin_can_edit_reset_password_and_delete_a_student(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('bima@psikologi.test');

        $this->assertNotNull($student);

        Livewire::test(UserPage::class)
            ->callTableAction('edit', (string) $student['id'], ['name' => 'Bima Diubah'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Bima Diubah', $users->find((string) $student['id'])['name']);

        Livewire::test(UserPage::class)
            ->callTableAction('resetPassword', (string) $student['id'], ['password' => 'password-baru'])
            ->assertHasNoTableActionErrors();

        $updated = $users->find((string) $student['id']);

        $this->assertTrue(Hash::check('password-baru', (string) $updated['password_hash']));

        Livewire::test(UserPage::class)
            ->callTableAction('delete', (string) $updated['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNull($users->find((string) $student['id']));
        $this->assertNotNull($users->find((string) $student['id'], true));
    }

    public function test_admin_can_create_super_admin_and_admin_accounts(): void
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($program);

        $users = app(UserRepository::class);
        $provider = new SheetUserProvider($users);

        foreach ([
            ['role' => 'super_admin', 'name' => 'Super Admin Baru', 'npm' => 'ADMIN002', 'email' => 'super.admin@psikologi.test'],
            ['role' => 'pjmk', 'name' => 'Admin Baru', 'npm' => 'PJMK002', 'email' => 'pjmk.baru@psikologi.test'],
        ] as $account) {
            Livewire::test(UserPage::class)
                ->callTableAction('create', null, [
                    'name' => $account['name'],
                    'npm' => $account['npm'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                    'prodi_id' => (string) $program['id'],
                    'password' => 'password123',
                    'class_name' => 'Psikologi A',
                    'cohort' => '2026',
                    'phone' => '',
                    'active' => true,
                ])
                ->assertHasNoTableActionErrors();

            $created = $users->findActiveByLogin($account['email']);

            $this->assertNotNull($created);
            $this->assertSame($account['role'], $created['role']);
            $this->assertSame('', $created['class_name']);
            $this->assertSame('', $created['cohort']);

            $authUser = $provider->retrieveById((string) $created['id']);

            $this->assertNotNull($authUser);
            $this->assertTrue($authUser->isAdmin());
            $this->assertTrue($authUser->canAccessPanel(Filament::getPanel('app')));

            if ($account['role'] === 'pjmk') {
                $this->assertTrue($authUser->isAdministrator());
            } else {
                $this->assertTrue($authUser->isSuperAdmin());
            }
        }
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $users = app(UserRepository::class);
        $pjmk = $users->findActiveByLogin('pjmk@psikologi.test');
        $admin = $users->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($pjmk);
        $this->assertNotNull($admin);

        Livewire::test(UserPage::class)
            ->callTableAction('delete', (string) $pjmk['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNull($users->find((string) $pjmk['id']));

        Livewire::test(UserPage::class)
            ->callTableAction('edit', (string) $admin['id'], [
                'role' => 'mahasiswa',
                'class_name' => 'Psikologi A',
            ])
            ->assertHasErrors();

        $this->assertSame('super_admin', $users->find((string) $admin['id'])['role']);

        Livewire::test(UserPage::class)
            ->callTableAction('edit', (string) $admin['id'], [
                'role' => 'super_admin',
                'active' => false,
            ])
            ->assertHasErrors();

        $this->assertTrue($users->isActive($users->find((string) $admin['id'])));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $users = app(UserRepository::class);
        $admin = $users->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        Livewire::test(UserPage::class)
            ->callTableAction('delete', (string) $admin['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($users->find((string) $admin['id']));
    }

    protected function actingAsAdmin(): void
    {
        $admin = app(UserRepository::class)->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($admin);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $admin['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);
    }
}
