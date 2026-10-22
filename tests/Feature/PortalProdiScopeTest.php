<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Portal\Pages\JadwalPage;
use App\Filament\Portal\Pages\KelompokPage;
use App\Filament\Portal\Pages\PengumumanPage;
use App\Filament\Portal\Pages\TugasPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\ProdiAssignments;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class PortalProdiScopeTest extends TestCase
{
    use InteractsWithSheets;

    /**
     * @var array<string, mixed>
     */
    protected array $psikologi;

    /**
     * @var array<string, mixed>
     */
    protected array $bimbingan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        Filament::setCurrentPanel('portal');

        $this->psikologi = app(StudyProgramRepository::class)->findByCode('psikologi');
        $this->bimbingan = app(StudyProgramRepository::class)->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_portal_only_shows_data_from_the_students_prodi(): void
    {
        $student = $this->createStudent('Mahasiswa BK', 'bk@psikologi.test', 'BK2024001');

        $course = app(CourseRepository::class)->create([
            'code' => 'BK101',
            'name' => 'Pengantar BK',
            'credits' => '3',
            'lecturer' => 'Dosen BK',
            'semester' => 'Ganjil',
            'class_name' => 'Psikologi A',
            'description' => '',
            'prodi_ids' => (string) $this->bimbingan['id'],
        ]);

        $schedule = app(ScheduleRepository::class)->create([
            'course_id' => (string) $course['id'],
            'day' => 'Rabu',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Ruang BK',
            'class_name' => 'Psikologi A',
        ]);

        $task = app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => '',
            'title' => 'Tugas BK',
            'description' => 'Tugas khusus mahasiswa BK.',
            'type' => 'individu',
            'deadline' => now()->addDay()->toIso8601String(),
            'created_by' => '',
        ]);

        $group = app(GroupRepository::class)->create([
            'course_id' => (string) $course['id'],
            'name' => 'Kelompok BK',
            'leader_id' => (string) $student['id'],
            'description' => '',
        ]);

        $members = app(GroupMemberRepository::class);
        $members->syncForGroup((string) $group['id'], [(string) $student['id']], (string) $student['id']);

        $psikologiCourse = app(CourseRepository::class)->findBy('code', 'PSI101');
        $psikologiSchedule = app(ScheduleRepository::class)->findBy('room', 'Ruang A201');
        $psikologiTask = app(TaskRepository::class)->findBy('title', 'Refleksi Psikologi Umum');
        $psikologiGroup = app(GroupRepository::class)->findBy('name', 'Kelompok 1 - Observasi');

        $this->assertNotNull($psikologiCourse);
        $this->assertNotNull($psikologiSchedule);
        $this->assertNotNull($psikologiTask);
        $this->assertNotNull($psikologiGroup);

        $psikologiGroupMembers = $members->forGroup((string) $psikologiGroup['id'])
            ->pluck('user_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
        $psikologiGroupMembers[] = (string) $student['id'];
        $members->syncForGroup(
            (string) $psikologiGroup['id'],
            array_values(array_unique($psikologiGroupMembers)),
            (string) $psikologiGroup['leader_id'],
        );

        $announcements = app(AnnouncementRepository::class);
        $psikologiAnnouncement = $announcements->create([
            'title' => 'Info Psikologi',
            'body' => 'Hanya untuk mahasiswa Psikologi.',
            'audience' => 'mahasiswa',
            'prodi_ids' => (string) $this->psikologi['id'],
            'published_at' => now()->toIso8601String(),
            'created_by' => '',
        ]);
        $bimbinganAnnouncement = $announcements->create([
            'title' => 'Info BK',
            'body' => 'Hanya untuk mahasiswa BK.',
            'audience' => 'mahasiswa',
            'prodi_ids' => (string) $this->bimbingan['id'],
            'published_at' => now()->subMinute()->toIso8601String(),
            'created_by' => '',
        ]);
        $allProdiAnnouncement = $announcements->create([
            'title' => 'Info Semua Prodi',
            'body' => 'Untuk semua mahasiswa.',
            'audience' => 'mahasiswa',
            'prodi_ids' => '',
            'published_at' => now()->subMinutes(2)->toIso8601String(),
            'created_by' => '',
        ]);

        $this->actingAsStudent($student);

        Livewire::test(JadwalPage::class)
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([(string) $schedule['id']])
            ->assertCanNotSeeTableRecords([(string) $psikologiSchedule['id']])
            ->assertSee('Pengantar BK');

        Livewire::test(TugasPage::class)
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([(string) $task['id']])
            ->assertCanNotSeeTableRecords([(string) $psikologiTask['id']])
            ->assertSee('Tugas BK');

        $bimbinganMemberIds = $members->forGroup((string) $group['id'])
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        Livewire::test(KelompokPage::class)
            ->assertSee('Pengantar BK')
            ->assertDontSee('Kelompok 1 - Observasi')
            ->call('selectCourse', (string) $course['id'])
            ->assertSee('Kelompok BK')
            ->assertDontSee('Kelompok 1 - Observasi')
            ->call('selectGroup', (string) $group['id'])
            ->assertCanSeeTableRecords($bimbinganMemberIds)
            ->assertSee('Mahasiswa BK');

        Livewire::test(PengumumanPage::class)
            ->assertCountTableRecords(3)
            ->assertCanSeeTableRecords([(string) $bimbinganAnnouncement['id']])
            ->assertCanSeeTableRecords([(string) $allProdiAnnouncement['id']])
            ->assertCanNotSeeTableRecords([(string) $psikologiAnnouncement['id']])
            ->assertSee('Info BK');

        $this->get('/portal')
            ->assertOk()
            ->assertSee('Bimbingan dan Konseling')
            ->assertSee('Pengantar BK')
            ->assertSee('Tugas BK')
            ->assertDontSee('Refleksi Psikologi Umum');
    }

    public function test_combined_course_is_visible_to_students_from_each_selected_prodi(): void
    {
        $psikologiStudent = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');
        $bimbinganStudent = $this->createStudent('Mahasiswa BK', 'bk@psikologi.test', 'BK2024001');

        $this->assertNotNull($psikologiStudent);

        $course = app(CourseRepository::class)->create([
            'code' => 'GAB101',
            'name' => 'Mata Kuliah Gabungan',
            'credits' => '3',
            'lecturer' => 'Dosen Gabungan',
            'semester' => 'Ganjil',
            'class_name' => 'Psikologi A',
            'description' => '',
            'prodi_ids' => ProdiAssignments::csv([
                (string) $this->psikologi['id'],
                (string) $this->bimbingan['id'],
            ]),
        ]);

        app(ScheduleRepository::class)->create([
            'course_id' => (string) $course['id'],
            'day' => 'Jumat',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'room' => 'Ruang Gabungan',
            'class_name' => 'Psikologi A',
        ]);

        app(TaskRepository::class)->create([
            'course_id' => (string) $course['id'],
            'group_id' => '',
            'title' => 'Tugas Gabungan',
            'description' => '',
            'type' => 'individu',
            'deadline' => now()->addDays(2)->toIso8601String(),
            'created_by' => '',
        ]);

        $this->assertTrue(
            app(CourseRepository::class)->forStudent($psikologiStudent)->contains('id', (string) $course['id']),
        );
        $this->assertTrue(
            app(CourseRepository::class)->forStudent($bimbinganStudent)->contains('id', (string) $course['id']),
        );

        $this->actingAsStudent($bimbinganStudent);

        Livewire::test(JadwalPage::class)
            ->assertSee('Mata Kuliah Gabungan')
            ->assertSee('Ruang Gabungan');

        Livewire::test(TugasPage::class)
            ->assertSee('Tugas Gabungan');
    }

    /**
     * @return array<string, mixed>
     */
    protected function createStudent(string $name, string $email, string $npm): array
    {
        return app(UserRepository::class)->create([
            'name' => $name,
            'email' => $email,
            'npm' => $npm,
            'password_hash' => Hash::make('password'),
            'role' => 'mahasiswa',
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '',
            'active' => '1',
            'prodi_id' => (string) $this->bimbingan['id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $student
     */
    protected function actingAsStudent(array $student): void
    {
        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $student['id']);

        $this->assertNotNull($user);
        $this->actingAs($user);
    }
}
