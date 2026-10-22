<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\StudentPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
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

    public function test_admin_can_create_student_with_group_assignments(): void
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($program);
        $this->assertNotNull($course);
        $this->assertNotNull($group);

        Livewire::test(StudentPage::class)
            ->callTableAction('create', null, [
                'prodi_id' => (string) $program['id'],
                'name' => 'Mahasiswa Berkelompok',
                'npm' => '9990003',
                'email' => 'mahasiswa.berkelompok@psikologi.test',
                'password' => 'password123',
                'class_name' => 'Psikologi A',
                'cohort' => '2026',
                'phone' => '',
                'active' => true,
                'group_assignments' => [
                    [
                        'course_id' => (string) $course['id'],
                        'group_id' => (string) $group['id'],
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $student = app(UserRepository::class)->findActiveByLogin('mahasiswa.berkelompok@psikologi.test');

        $this->assertNotNull($student);

        $membership = app(GroupMemberRepository::class)
            ->membershipForCourse((string) $student['id'], (string) $course['id']);

        $this->assertNotNull($membership);
        $this->assertSame((string) $group['id'], (string) $membership['group_id']);
        $this->assertSame('anggota', $membership['role']);
    }

    public function test_student_group_assignment_uses_prodi_not_class(): void
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');
        $course = app(CourseRepository::class)->findBy('code', 'PSI102');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok B1');

        $this->assertNotNull($program);
        $this->assertNotNull($course);
        $this->assertNotNull($group);

        Livewire::test(StudentPage::class)
            ->callTableAction('create', null, [
                'prodi_id' => (string) $program['id'],
                'name' => 'Mahasiswa Lintas Kelas',
                'npm' => '9990004',
                'email' => 'mahasiswa.lintas-kelas@psikologi.test',
                'password' => 'password123',
                'class_name' => 'Psikologi A',
                'cohort' => '2026',
                'phone' => '',
                'active' => true,
                'group_assignments' => [
                    [
                        'course_id' => (string) $course['id'],
                        'group_id' => (string) $group['id'],
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $student = app(UserRepository::class)->findActiveByLogin('mahasiswa.lintas-kelas@psikologi.test');

        $this->assertNotNull($student);
        $this->assertNotNull(
            app(GroupMemberRepository::class)
                ->membershipForCourse((string) $student['id'], (string) $course['id']),
        );
    }

    public function test_student_group_assignment_rejects_a_group_from_another_course(): void
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $otherGroup = app(GroupRepository::class)->findBy('name', 'Kelompok B1');

        $this->assertNotNull($program);
        $this->assertNotNull($course);
        $this->assertNotNull($otherGroup);

        Livewire::test(StudentPage::class)
            ->callTableAction('create', null, [
                'prodi_id' => (string) $program['id'],
                'name' => 'Mahasiswa Salah Kelompok',
                'npm' => '9990005',
                'email' => 'mahasiswa.salah-kelompok@psikologi.test',
                'password' => 'password123',
                'class_name' => 'Psikologi A',
                'cohort' => '2026',
                'phone' => '',
                'active' => true,
                'group_assignments' => [
                    [
                        'course_id' => (string) $course['id'],
                        'group_id' => (string) $otherGroup['id'],
                    ],
                ],
            ])
            ->assertHasErrors();

        $this->assertNull(
            app(UserRepository::class)->findActiveByLogin('mahasiswa.salah-kelompok@psikologi.test'),
        );
    }

    public function test_admin_can_move_and_clear_student_group_assignment(): void
    {
        $student = app(UserRepository::class)->findActiveByLogin('bima@psikologi.test');
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $oldGroup = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($student);
        $this->assertNotNull($course);
        $this->assertNotNull($oldGroup);

        $newGroup = app(GroupRepository::class)->create([
            'course_id' => (string) $course['id'],
            'name' => 'Kelompok Uji Pindah',
            'leader_id' => '',
            'description' => '',
        ]);

        Livewire::test(StudentPage::class)
            ->callTableAction('edit', (string) $student['id'], [
                'group_assignments' => [
                    [
                        'course_id' => (string) $course['id'],
                        'group_id' => (string) $newGroup['id'],
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $members = app(GroupMemberRepository::class);
        $membership = $members->membershipForCourse((string) $student['id'], (string) $course['id']);

        $this->assertNotNull($membership);
        $this->assertSame((string) $newGroup['id'], (string) $membership['group_id']);
        $this->assertNull(
            $members->forGroup((string) $oldGroup['id'])
                ->first(fn (array $member): bool => (string) $member['user_id'] === (string) $student['id']),
        );

        Livewire::test(StudentPage::class)
            ->callTableAction('edit', (string) $student['id'], [
                'group_assignments' => [
                    [
                        'course_id' => (string) $course['id'],
                        'group_id' => '',
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNull(
            $members->membershipForCourse((string) $student['id'], (string) $course['id']),
        );

        $this->assertNotNull(
            $members->membershipForCourse(
                (string) $student['id'],
                (string) app(CourseRepository::class)->findBy('code', 'PSI301')['id'],
            ),
        );
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
