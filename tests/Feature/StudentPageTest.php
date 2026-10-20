<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\StudentPage;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class StudentPageTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_student_page_only_lists_students(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('ayu@psikologi.test');
        $admin = $users->findActiveByLogin('admin@psikologi.test');

        $this->assertNotNull($student);
        $this->assertNotNull($admin);

        Livewire::test(StudentPage::class)
            ->assertCanSeeTableRecords([(string) $student['id']])
            ->assertCanNotSeeTableRecords([(string) $admin['id']]);
    }

    public function test_admin_can_create_student_with_full_login_account(): void
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($program);

        Livewire::test(StudentPage::class)
            ->callTableAction('create', null, [
                'prodi_id' => (string) $program['id'],
                'name' => 'Mahasiswa Pendataan',
                'npm' => '9990002',
                'email' => 'mahasiswa.pendataan@psikologi.test',
                'password' => 'password123',
                'class_name' => 'Psikologi A',
                'cohort' => '2026',
                'phone' => '081200000001',
                'active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $student = app(UserRepository::class)->findActiveByLogin('mahasiswa.pendataan@psikologi.test');

        $this->assertNotNull($student);
        $this->assertSame('Mahasiswa Pendataan', $student['name']);
        $this->assertSame('mahasiswa', $student['role']);
        $this->assertSame((string) $program['id'], $student['prodi_id']);
        $this->assertSame('9990002', $student['npm']);
        $this->assertTrue(Hash::check('password123', (string) $student['password_hash']));

        $authUser = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $student['id']);

        $this->assertNotNull($authUser);
        $this->assertTrue($authUser->canAccessPanel(Filament::getPanel('portal')));
        $this->assertFalse($authUser->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_admin_can_edit_reset_password_and_delete_student(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('bima@psikologi.test');

        $this->assertNotNull($student);

        Livewire::test(StudentPage::class)
            ->callTableAction('edit', (string) $student['id'], [
                'name' => 'Bima Pendataan',
                'npm' => '2024002',
                'class_name' => 'Psikologi B',
                'cohort' => '2024',
                'active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $updated = $users->find((string) $student['id']);

        $this->assertNotNull($updated);
        $this->assertSame('Bima Pendataan', $updated['name']);
        $this->assertSame('mahasiswa', $updated['role']);

        Livewire::test(StudentPage::class)
            ->callTableAction('resetPassword', (string) $student['id'], ['password' => 'password-baru'])
            ->assertHasNoTableActionErrors();

        $updated = $users->find((string) $student['id']);

        $this->assertNotNull($updated);
        $this->assertTrue(Hash::check('password-baru', (string) $updated['password_hash']));

        Livewire::test(StudentPage::class)
            ->callTableAction('delete', (string) $student['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNull($users->find((string) $student['id']));
        $this->assertNotNull($users->find((string) $student['id'], true));
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
