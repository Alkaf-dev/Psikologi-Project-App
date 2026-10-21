<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Pages\JadwalPage;
use App\Filament\Pages\KelompokPage;
use App\Filament\Pages\MataKuliahPage;
use App\Filament\Pages\PengumpulanPage;
use App\Filament\Pages\PengumumanPage;
use App\Filament\Pages\TugasPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\ProdiAssignments;
use App\Support\TaskGroups;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AdminCreateActionTest extends TestCase
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

    public function test_admin_can_create_course_schedule_group_task_announcement_and_submission(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI101');
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($course);
        $this->assertNotNull($student);
        $this->assertNotNull($program);

        Livewire::test(MataKuliahPage::class)
            ->callTableAction('create', null, [
                'code' => 'PSI999',
                'name' => 'Mata Kuliah Pengujian',
                'credits' => 3,
                'lecturer' => 'Dosen Pengujian',
                'semester' => 'Ganjil',
                'class_name' => 'Psikologi A',
                'prodi_ids' => [(string) $program['id']],
                'description' => 'Deskripsi pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull(app(CourseRepository::class)->findBy('code', 'PSI999'));

        Livewire::test(JadwalPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'day' => 'Sabtu',
                'start_time' => '16:00',
                'end_time' => '17:30',
                'mode' => 'offline',
                'room' => 'Ruang Uji',
                'class_name' => 'Psikologi A',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull(app(ScheduleRepository::class)->findBy('room', 'Ruang Uji'));

        Livewire::test(KelompokPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'name' => 'Kelompok Pengujian',
                'leader_id' => (string) $student['id'],
                'member_ids' => [(string) $student['id']],
                'description' => 'Kelompok untuk pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $group = app(GroupRepository::class)->findBy('name', 'Kelompok Pengujian');

        $this->assertNotNull($group);
        $this->assertCount(1, app(GroupMemberRepository::class)->forGroup((string) $group['id']));

        Livewire::test(TugasPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'type' => 'individu',
                'title' => 'Tugas Pengujian',
                'deadline' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'description' => 'Instruksi pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $task = app(TaskRepository::class)->findBy('title', 'Tugas Pengujian');

        $this->assertNotNull($task);

        Livewire::test(PengumumanPage::class)
            ->callTableAction('create', null, [
                'title' => 'Pengumuman Pengujian',
                'audience' => 'mahasiswa',
                'prodi_ids' => [(string) $program['id']],
                'published_at' => now()->format('Y-m-d H:i:s'),
                'body' => 'Isi pengumuman pengujian.',
            ])
            ->assertHasNoTableActionErrors();

        $announcement = app(AnnouncementRepository::class)->findBy('title', 'Pengumuman Pengujian');

        $this->assertNotNull($announcement);
        $this->assertSame((string) $program['id'], $announcement['prodi_ids']);

        Livewire::test(PengumpulanPage::class)
            ->callTableAction('create', null, [
                'task_id' => (string) $task['id'],
                'user_id' => (string) $student['id'],
                'group_id' => '',
                'status' => 'submitted',
                'link' => 'https://example.com/pengumpulan-baru',
                'notes' => '',
                'feedback' => '',
            ])
            ->assertHasNoTableActionErrors();

        $submission = app(SubmissionRepository::class)
            ->forTaskAndUser((string) $task['id'], (string) $student['id']);

        $this->assertNotNull($submission);
        $this->assertSame('submitted', $submission['status']);
    }

    public function test_admin_can_create_group_task_with_sheet_fields_and_multiple_groups(): void
    {
        $course = app(CourseRepository::class)->findBy('code', 'PSI201');
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');
        $groupOne = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($course);
        $this->assertNotNull($student);
        $this->assertNotNull($groupOne);

        $groupTwo = app(GroupRepository::class)->create([
            'course_id' => (string) $course['id'],
            'name' => 'Kelompok Pengujian Tugas',
            'leader_id' => (string) $student['id'],
            'description' => '',
        ]);

        app(GroupMemberRepository::class)->syncForGroup(
            (string) $groupTwo['id'],
            [(string) $student['id']],
            (string) $student['id'],
        );

        Livewire::test(TugasPage::class)
            ->callTableAction('create', null, [
                'course_id' => (string) $course['id'],
                'type' => 'kelompok',
                'meeting_number' => '7',
                'status' => 'active',
                'title' => 'Tugas dengan Kolom Sheet',
                'description' => 'Instruksi tugas dari spreadsheet.',
                'material_url' => 'https://example.com/materi',
                'collection_link' => 'https://drive.google.com/drive/folders/contoh',
                'start_at' => now()->format('Y-m-d H:i:s'),
                'deadline' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'deadline_note' => '',
                'group_ids' => [(string) $groupOne['id'], (string) $groupTwo['id']],
            ])
            ->assertHasNoTableActionErrors();

        $task = app(TaskRepository::class)->findBy('title', 'Tugas dengan Kolom Sheet');

        $this->assertNotNull($task);
        $this->assertSame('7', (string) $task['meeting_number']);
        $this->assertSame('https://example.com/materi', $task['material_url']);
        $this->assertSame('https://drive.google.com/drive/folders/contoh', $task['collection_link']);
        $this->assertSame('active', $task['status']);
        $this->assertSame(
            TaskGroups::csv([(string) $groupOne['id'], (string) $groupTwo['id']]),
            $task['group_ids'],
        );
    }

    public function test_admin_can_create_one_submission_for_a_group_representative(): void
    {
        $group = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');
        $representative = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($group);
        $this->assertNotNull($representative);

        $task = app(TaskRepository::class)->create([
            'course_id' => (string) $group['course_id'],
            'group_id' => (string) $group['id'],
            'group_ids' => (string) $group['id'],
            'title' => 'Tugas Pengumpulan Kelompok Admin',
            'description' => '',
            'type' => 'kelompok',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'active',
            'created_by' => '',
        ]);

        Livewire::test(PengumpulanPage::class)
            ->callTableAction('create', null, [
                'task_id' => (string) $task['id'],
                'group_id' => (string) $group['id'],
                'user_id' => (string) $representative['id'],
                'status' => 'submitted',
                'link' => 'https://example.com/pengumpulan-kelompok',
                'notes' => '',
                'feedback' => '',
            ])
            ->assertHasNoTableActionErrors();

        $submission = app(SubmissionRepository::class)
            ->forTaskAndGroup((string) $task['id'], (string) $group['id']);

        $this->assertNotNull($submission);
        $this->assertSame((string) $representative['id'], (string) $submission['user_id']);
        $this->assertSame('https://example.com/pengumpulan-kelompok', $submission['link']);
    }

    public function test_admin_can_assign_a_course_to_multiple_prodi(): void
    {
        $psikologi = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($psikologi);

        $bimbingan = app(StudyProgramRepository::class)->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);

        Livewire::test(MataKuliahPage::class)
            ->callTableAction('create', null, [
                'code' => 'PSI888',
                'name' => 'Mata Kuliah Gabungan',
                'credits' => 3,
                'lecturer' => 'Dosen Gabungan',
                'semester' => 'Ganjil',
                'class_name' => 'Psikologi A',
                'prodi_ids' => [(string) $psikologi['id'], (string) $bimbingan['id']],
                'description' => 'Mata kuliah lintas Prodi.',
            ])
            ->assertHasNoTableActionErrors();

        $course = app(CourseRepository::class)->findBy('code', 'PSI888');

        $this->assertNotNull($course);
        $this->assertEqualsCanonicalizing(
            [(string) $psikologi['id'], (string) $bimbingan['id']],
            ProdiAssignments::ids($course['prodi_ids']),
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
