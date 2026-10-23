<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

class DemoData
{
    public function __construct(
        protected UserRepository $users,
        protected CourseRepository $courses,
        protected ScheduleRepository $schedules,
        protected GroupRepository $groups,
        protected GroupMemberRepository $members,
        protected TaskRepository $tasks,
        protected SubmissionRepository $submissions,
        protected AnnouncementRepository $announcements,
        protected SettingsRepository $settings,
        protected StudyProgramRepository $studyPrograms,
    ) {}

    /**
     * @return array<string, int>
     */
    public function seed(): array
    {
        $prodi = $this->ensureStudyProgram([
            'code' => 'psikologi',
            'name' => 'Psikologi',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $admin = $this->ensureUser([
            'name' => 'Admin Program Studi',
            'email' => 'admin@psikologi.test',
            'npm' => 'ADMIN001',
            'role' => UserRole::SuperAdmin->value,
            'prodi_id' => $prodi['id'],
            'class_name' => '',
            'cohort' => '',
            'phone' => '',
        ]);

        $this->ensureUser([
            'name' => 'Admin Prodi',
            'email' => 'pjmk@psikologi.test',
            'npm' => 'PJMK001',
            'role' => UserRole::Admin->value,
            'prodi_id' => $prodi['id'],
            'class_name' => '',
            'cohort' => '',
            'phone' => '',
        ]);

        $ayu = $this->ensureUser([
            'name' => 'Ayu Lestari',
            'email' => 'ayu@psikologi.test',
            'npm' => '2024001',
            'role' => 'mahasiswa',
            'prodi_id' => $prodi['id'],
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '0812-0000-0001',
        ]);

        $bima = $this->ensureUser([
            'name' => 'Bima Pratama',
            'email' => 'bima@psikologi.test',
            'npm' => '2024002',
            'role' => 'mahasiswa',
            'prodi_id' => $prodi['id'],
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '0812-0000-0002',
        ]);

        $citra = $this->ensureUser([
            'name' => 'Citra Dewi',
            'email' => 'citra@psikologi.test',
            'npm' => '2024003',
            'role' => 'mahasiswa',
            'prodi_id' => $prodi['id'],
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '0812-0000-0003',
        ]);

        $dimas = $this->ensureUser([
            'name' => 'Dimas Nugraha',
            'email' => 'dimas@psikologi.test',
            'npm' => '2024004',
            'role' => 'mahasiswa',
            'prodi_id' => $prodi['id'],
            'class_name' => 'Psikologi B',
            'cohort' => '2024',
            'phone' => '0812-0000-0004',
        ]);

        $psi101 = $this->ensureCourse([
            'code' => 'PSI101',
            'name' => 'Psikologi Umum',
            'credits' => 3,
            'lecturer' => 'Dr. Rani Kusuma',
            'semester' => 'Ganjil',
            'class_name' => 'Psikologi A',
            'description' => 'Pengantar konsep dasar psikologi dan penerapannya.',
            'prodi_ids' => $prodi['id'],
        ]);

        $psi201 = $this->ensureCourse([
            'code' => 'PSI201',
            'name' => 'Psikologi Perkembangan',
            'credits' => 3,
            'lecturer' => 'Dr. Sinta Maharani',
            'semester' => 'Ganjil',
            'class_name' => 'Psikologi A',
            'description' => 'Perkembangan manusia dari masa kanak hingga dewasa.',
            'prodi_ids' => $prodi['id'],
        ]);

        $psi301 = $this->ensureCourse([
            'code' => 'PSI301',
            'name' => 'Metodologi Penelitian',
            'credits' => 2,
            'lecturer' => 'Dr. Bagus Saputra',
            'semester' => 'Ganjil',
            'class_name' => 'Psikologi A',
            'description' => 'Perancangan penelitian kuantitatif dan kualitatif.',
            'prodi_ids' => $prodi['id'],
        ]);

        $psi102 = $this->ensureCourse([
            'code' => 'PSI102',
            'name' => 'Psikologi Sosial',
            'credits' => 3,
            'lecturer' => 'Dr. Maya Puspita',
            'semester' => 'Ganjil',
            'class_name' => 'Psikologi B',
            'description' => 'Perilaku individu dalam konteks sosial.',
            'prodi_ids' => $prodi['id'],
        ]);

        if ($this->schedules->count() === 0) {
            $this->schedules->create([
                'course_id' => $psi101['id'],
                'day' => 'Senin',
                'start_time' => '08:00',
                'end_time' => '10:30',
                'room' => 'Ruang A201',
                'class_name' => 'Psikologi A',
                'mode' => 'offline',
                'platform' => '',
                'meeting_link' => '',
            ]);
            $this->schedules->create([
                'course_id' => $psi201['id'],
                'day' => 'Selasa',
                'start_time' => '10:00',
                'end_time' => '12:30',
                'room' => 'Ruang A203',
                'class_name' => 'Psikologi A',
                'mode' => 'offline',
                'platform' => '',
                'meeting_link' => '',
            ]);
            $this->schedules->create([
                'course_id' => $psi301['id'],
                'day' => 'Kamis',
                'start_time' => '13:00',
                'end_time' => '15:00',
                'room' => '',
                'class_name' => 'Psikologi A',
                'mode' => 'online',
                'platform' => 'zoom',
                'meeting_link' => 'https://zoom.us/j/1234567890',
            ]);
            $this->schedules->create([
                'course_id' => $psi102['id'],
                'day' => 'Jumat',
                'start_time' => '08:00',
                'end_time' => '10:30',
                'room' => '',
                'class_name' => 'Psikologi B',
                'mode' => 'online',
                'platform' => 'google_meet',
                'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            ]);
        }

        $groupOne = $this->ensureGroup([
            'course_id' => $psi201['id'],
            'name' => 'Kelompok 1 - Observasi',
            'leader_id' => $ayu['id'],
            'description' => 'Observasi perkembangan anak usia sekolah.',
        ], [$ayu['id'], $bima['id'], $citra['id']], (string) $ayu['id']);

        $groupTwo = $this->ensureGroup([
            'course_id' => $psi301['id'],
            'name' => 'Kelompok 2 - Survei',
            'leader_id' => $bima['id'],
            'description' => 'Survei persepsi mahasiswa terhadap layanan kampus.',
        ], [$bima['id'], $citra['id']], (string) $bima['id']);

        $groupThree = $this->ensureGroup([
            'course_id' => $psi102['id'],
            'name' => 'Kelompok B1',
            'leader_id' => $dimas['id'],
            'description' => 'Studi kasus perilaku sosial di lingkungan kampus.',
        ], [$dimas['id']], (string) $dimas['id']);

        $taskOne = $this->ensureTask([
            'course_id' => $psi101['id'],
            'group_id' => '',
            'group_ids' => '',
            'meeting_number' => '1',
            'title' => 'Refleksi Psikologi Umum',
            'description' => 'Tuliskan refleksi 500 kata tentang konsep psikologi yang paling relevan dengan pengalaman Anda.',
            'type' => 'individu',
            'material_url' => 'https://www.bookcreator.com/',
            'start_at' => now()->subDays(2)->setTime(8, 0)->toIso8601String(),
            'deadline' => now()->addDays(3)->setTime(23, 59)->toIso8601String(),
            'deadline_note' => '',
            'collection_link' => '',
            'status' => 'active',
            'created_by' => $admin['id'],
        ]);

        $taskTwo = $this->ensureTask([
            'course_id' => $psi201['id'],
            'group_id' => '',
            'group_ids' => '',
            'meeting_number' => '2',
            'title' => 'Laporan Observasi Perkembangan Anak',
            'description' => 'Laporan observasi kelompok dalam format PDF. Cantumkan metode, temuan, dan refleksi.',
            'type' => 'individu',
            'material_url' => 'https://www.bookcreator.com/',
            'start_at' => now()->subDay()->setTime(8, 0)->toIso8601String(),
            'deadline' => now()->addDays(7)->setTime(23, 59)->toIso8601String(),
            'deadline_note' => '',
            'collection_link' => '',
            'status' => 'active',
            'created_by' => $admin['id'],
        ]);

        $taskThree = $this->ensureTask([
            'course_id' => $psi301['id'],
            'group_id' => $groupTwo['id'],
            'group_ids' => $groupTwo['id'],
            'meeting_number' => '3',
            'title' => 'Proposal Penelitian Kelompok',
            'description' => 'Susun proposal penelitian lengkap dengan latar belakang, rumusan masalah, dan metode.',
            'type' => 'kelompok',
            'material_url' => 'https://www.bookcreator.com/',
            'start_at' => now()->setTime(8, 0)->toIso8601String(),
            'deadline' => now()->addDays(14)->setTime(23, 59)->toIso8601String(),
            'deadline_note' => '',
            'collection_link' => 'https://drive.google.com/drive/folders/contoh-pengumpulan-kelompok',
            'status' => 'active',
            'created_by' => $admin['id'],
        ]);

        $taskFour = $this->ensureTask([
            'course_id' => $psi102['id'],
            'group_id' => '',
            'group_ids' => '',
            'meeting_number' => '4',
            'title' => 'Studi Kasus Psikologi Sosial',
            'description' => 'Analisis satu fenomena sosial di sekitar Anda menggunakan teori psikologi sosial.',
            'type' => 'individu',
            'material_url' => 'https://www.bookcreator.com/',
            'start_at' => now()->addDay()->setTime(8, 0)->toIso8601String(),
            'deadline' => now()->addDays(5)->setTime(23, 59)->toIso8601String(),
            'deadline_note' => '',
            'collection_link' => '',
            'status' => 'active',
            'created_by' => $admin['id'],
        ]);

        $this->ensureSubmission($taskOne['id'], $ayu['id'], [
            'group_id' => '',
            'status' => 'submitted',
            'link' => 'https://docs.google.com/document/d/contoh-refleksi-ayu',
            'notes' => 'Sudah saya kerjakan, mohon dicek.',
            'submitted_at' => now()->subHours(5)->toIso8601String(),
        ]);

        $this->ensureSubmission($taskThree['id'], $bima['id'], [
            'group_id' => $groupTwo['id'],
            'status' => 'submitted',
            'link' => 'https://docs.google.com/document/d/contoh-proposal-kelompok-2',
            'notes' => 'Revisi proposal kelompok 2.',
            'submitted_at' => now()->subDay()->toIso8601String(),
        ]);

        $this->ensureSubmission($taskTwo['id'], $citra['id'], [
            'group_id' => '',
            'status' => 'graded',
            'link' => 'https://docs.google.com/document/d/contoh-laporan-citra',
            'notes' => '',
            'grade' => '88',
            'feedback' => 'Analisis sudah baik. Perdalam bagian refleksi dan tambahkan sumber primer.',
            'submitted_at' => now()->subDays(2)->toIso8601String(),
        ]);

        $this->ensureAnnouncement([
            'title' => 'Selamat datang di Psikologi Connect',
            'body' => "Halo mahasiswa Psikologi!\n\nPsikologi Connect adalah portal akademik untuk melihat jadwal, tugas, kelompok, dan pengumuman. Silakan masuk menggunakan email atau NIM Anda.",
            'audience' => 'all',
            'published_at' => now()->subDays(2)->toIso8601String(),
            'created_by' => $admin['id'],
        ], 'Selamat datang di PsikologiConnect');

        $this->ensureAnnouncement([
            'title' => 'Pengisian KRS Semester Ganjil',
            'body' => 'Pengisian KRS dibuka sampai akhir minggu ini. Pastikan mata kuliah yang diambil sesuai dengan rencana studi Anda.',
            'audience' => 'mahasiswa',
            'published_at' => now()->subDay()->toIso8601String(),
            'created_by' => $admin['id'],
            'prodi_ids' => $prodi['id'],
        ]);

        $this->ensureAnnouncement([
            'title' => 'Pengingat Pengumpulan Tugas',
            'body' => 'Jangan lupa mengumpulkan tugas melalui portal sebelum deadline. Tautan yang dikumpulkan harus dapat diakses oleh dosen.',
            'audience' => 'mahasiswa',
            'published_at' => now()->toIso8601String(),
            'created_by' => $admin['id'],
            'prodi_ids' => $prodi['id'],
        ]);

        $this->settings->setMany([
            'program_name' => 'Program Studi Psikologi',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'contact_email' => 'psikologi@example.ac.id',
            'contact_phone' => '0812-3456-7890',
            'public_tagline' => 'Ruang belajar, berkolaborasi, dan bertumbuh bersama.',
            'public_about' => 'Psikologi Connect membantu mahasiswa mengakses jadwal kuliah, tugas, kelompok, dan pengumuman dalam satu tempat.',
            'portal_note' => 'Pilih Prodi, lalu masuk dengan email atau NIM untuk melihat aktivitas akademik Anda.',
        ]);

        return [
            'study_programs' => $this->studyPrograms->count(),
            'users' => $this->users->count(),
            'courses' => $this->courses->count(),
            'schedules' => $this->schedules->count(),
            'groups' => $this->groups->count(),
            'tasks' => $this->tasks->count(),
            'submissions' => $this->submissions->count(),
            'announcements' => $this->announcements->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureStudyProgram(array $data): array
    {
        $existing = $this->studyPrograms->findByCode((string) $data['code']);

        if ($existing) {
            return $existing;
        }

        return $this->studyPrograms->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureUser(array $data): array
    {
        $existing = $this->users->all()->first(
            fn (array $user): bool => mb_strtolower((string) ($user['email'] ?? '')) === mb_strtolower($data['email']),
        );

        if ($existing) {
            $updates = [];

            if (
                filled($data['prodi_id'] ?? null)
                && trim((string) ($existing['prodi_id'] ?? '')) !== trim((string) $data['prodi_id'])
            ) {
                $updates['prodi_id'] = $data['prodi_id'];
            }

            $role = UserRole::tryFromLegacy($data['role'] ?? null)?->value;

            if ($role !== null && mb_strtolower(trim((string) ($existing['role'] ?? ''))) !== $role) {
                $updates['role'] = $role;
            }

            return $updates === []
                ? $existing
                : $this->users->update((string) $existing['id'], $updates);
        }

        $data['password_hash'] = Hash::make('password');
        $data['active'] = '1';

        return $this->users->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureCourse(array $data): array
    {
        $existing = $this->courses->findBy('code', (string) $data['code']);

        if ($existing) {
            return $existing;
        }

        return $this->courses->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $memberIds
     * @return array<string, mixed>
     */
    protected function ensureGroup(array $data, array $memberIds, string $leaderId): array
    {
        $existing = $this->groups->findBy('name', (string) $data['name']);

        if ($existing) {
            return $existing;
        }

        $group = $this->groups->create($data);
        $this->members->syncForGroup((string) $group['id'], $memberIds, $leaderId);

        return $group;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureTask(array $data): array
    {
        $existing = $this->tasks->findBy('title', (string) $data['title']);

        if ($existing) {
            return $existing;
        }

        return $this->tasks->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function ensureSubmission(string $taskId, string $userId, array $data): void
    {
        if ($this->submissions->forTaskAndUser($taskId, $userId)) {
            return;
        }

        $data['task_id'] = $taskId;
        $data['user_id'] = $userId;

        $this->submissions->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function ensureAnnouncement(array $data, ?string $legacyTitle = null): void
    {
        $existing = $this->announcements->findBy('title', (string) $data['title']);

        if ($existing) {
            return;
        }

        if ($legacyTitle !== null) {
            $legacy = $this->announcements->findBy('title', $legacyTitle);

            if ($legacy) {
                $this->announcements->update((string) $legacy['id'], $data);

                return;
            }
        }

        $this->announcements->create($data);
    }
}
