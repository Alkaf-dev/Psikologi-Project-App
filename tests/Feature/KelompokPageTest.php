<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\KelompokPage;
use App\Filament\Portal\Pages\KelompokPage as PortalKelompokPage;
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

class KelompokPageTest extends TestCase
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

    public function test_admin_sees_course_cards_chips_and_member_table(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($course);
        $this->assertNotNull($group);

        $memberIds = app(GroupMemberRepository::class)
            ->forGroup((string) $group['id'])
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        Livewire::test(KelompokPage::class)
            ->assertSee('Psikologi Umum')
            ->assertSee('Psikologi Perkembangan')
            ->assertDontSee('Tambah Anggota')
            ->assertDontSee('Tambah Kelompok')
            ->call('selectCourse', (string) $course['id'])
            ->assertSee('Kelompok 1 - Observasi')
            ->assertSee('Tambah Kelompok')
            ->assertSee('Tambah Anggota')
            ->assertDontSee('Kelompok B1')
            ->call('selectGroup', (string) $group['id'])
            ->assertSee('Nama Mahasiswa/i')
            ->assertSee('Mata Kuliah')
            ->assertSee('Jumlah Anggota')
            ->assertCanSeeTableRecords($memberIds);
    }

    public function test_admin_can_add_a_member_and_duplicates_are_rejected(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');
        $newMember = app(UserRepository::class)->findActiveByLogin('dimas@psikologi.test');
        $existingMember = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($course);
        $this->assertNotNull($group);
        $this->assertNotNull($newMember);
        $this->assertNotNull($existingMember);

        $members = app(GroupMemberRepository::class);

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $course['id'])
            ->call('selectGroup', (string) $group['id'])
            ->callAction('addMember', [
                'user_ids' => [(string) $newMember['id']],
            ])
            ->assertHasNoActionErrors();

        $this->assertNotNull(
            $members->membershipForCourse((string) $newMember['id'], (string) $course['id']),
        );

        $countAfterAdd = $members->forGroup((string) $group['id'])->count();

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $course['id'])
            ->call('selectGroup', (string) $group['id'])
            ->callAction('addMember', [
                'user_ids' => [(string) $existingMember['id']],
            ])
            ->assertHasErrors();

        $this->assertSame($countAfterAdd, $members->forGroup((string) $group['id'])->count());
    }

    public function test_admin_cannot_add_a_student_from_another_prodi(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');
        $program = app(StudyProgramRepository::class)->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $this->assertNotNull($course);
        $this->assertNotNull($group);

        $student = app(UserRepository::class)->create([
            'name' => 'Mahasiswa BK',
            'email' => 'bk.kelompok@psikologi.test',
            'npm' => 'BK2024002',
            'password_hash' => Hash::make('password'),
            'role' => 'mahasiswa',
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '',
            'active' => '1',
            'prodi_id' => (string) $program['id'],
        ]);

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $course['id'])
            ->call('selectGroup', (string) $group['id'])
            ->callAction('addMember', [
                'user_ids' => [(string) $student['id']],
            ])
            ->assertHasErrors();

        $this->assertNull(
            app(GroupMemberRepository::class)
                ->membershipForCourse((string) $student['id'], (string) $course['id']),
        );
    }

    public function test_admin_can_remove_a_member_but_not_the_group_leader(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');
        $leader = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($course);
        $this->assertNotNull($group);
        $this->assertNotNull($leader);

        $members = app(GroupMemberRepository::class);
        $leaderMember = $members->forGroup((string) $group['id'])
            ->first(fn (array $member): bool => (string) $member['user_id'] === (string) $leader['id']);
        $regularMember = $members->forGroup((string) $group['id'])
            ->first(fn (array $member): bool => (string) $member['user_id'] !== (string) $leader['id']);

        $this->assertNotNull($leaderMember);
        $this->assertNotNull($regularMember);

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $course['id'])
            ->call('selectGroup', (string) $group['id'])
            ->callTableAction('removeMember', (string) $regularMember['id'])
            ->assertHasNoTableActionErrors();

        $this->assertNull(
            $members->membershipForCourse((string) $regularMember['user_id'], (string) $course['id']),
        );

        Livewire::test(KelompokPage::class)
            ->call('selectCourse', (string) $course['id'])
            ->call('selectGroup', (string) $group['id'])
            ->callTableAction('removeMember', (string) $leaderMember['id']);

        $this->assertNotNull(
            $members->membershipForCourse((string) $leader['id'], (string) $course['id']),
        );
    }

    public function test_portal_group_page_is_read_only_and_scoped_to_the_student(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAsStudent();

        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($course);
        $this->assertNotNull($group);

        Livewire::test(PortalKelompokPage::class)
            ->assertSee('Psikologi Perkembangan')
            ->assertDontSee('Psikologi Umum')
            ->assertDontSee('Tambah Anggota')
            ->call('selectCourse', (string) $course['id'])
            ->assertSee('Kelompok 1 - Observasi')
            ->assertDontSee('Tambah Anggota')
            ->assertDontSee('Ubah Kelompok')
            ->assertDontSee('Hapus Kelompok');

        Livewire::test(PortalKelompokPage::class)
            ->call('selectCourse', (string) $course['id'])
            ->call('selectGroup', (string) $group['id'])
            ->assertCanSeeTableRecords(
                app(GroupMemberRepository::class)->forGroup((string) $group['id'])->pluck('id')->all(),
            );
    }

    public function test_portal_group_page_cannot_open_a_group_the_student_does_not_belong_to(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAsStudent();

        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $ownGroup = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');
        $otherStudent = app(UserRepository::class)->findActiveByLogin('dimas@psikologi.test');

        $this->assertNotNull($course);
        $this->assertNotNull($ownGroup);
        $this->assertNotNull($otherStudent);

        $otherGroup = app(GroupRepository::class)->create([
            'course_id' => (string) $course['id'],
            'name' => 'Kelompok Rahasia',
            'leader_id' => (string) $otherStudent['id'],
            'description' => '',
        ]);

        app(GroupMemberRepository::class)->syncForGroup(
            (string) $otherGroup['id'],
            [(string) $otherStudent['id']],
            (string) $otherStudent['id'],
        );

        Livewire::test(PortalKelompokPage::class)
            ->call('selectCourse', (string) $course['id'])
            ->assertSet('selectedGroupId', (string) $ownGroup['id'])
            ->assertDontSee('Kelompok Rahasia')
            ->call('selectGroup', (string) $otherGroup['id'])
            ->assertSet('selectedGroupId', (string) $ownGroup['id'])
            ->assertDontSee('Kelompok Rahasia')
            ->assertDontSee('Dimas');
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
