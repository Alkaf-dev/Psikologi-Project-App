<?php

namespace Tests\Feature\Api;

use App\Repositories\CourseRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\TaskGroups;
use Tests\Concerns\InteractsWithApi;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use InteractsWithApi;
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

    public function test_staff_can_read_dashboard_and_manage_settings(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');

        $this->withApiToken($token)
            ->getJson('/api/v1/staff/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'counts' => [
                        'students', 'staff', 'courses', 'schedules',
                        'groups', 'tasks', 'active_tasks', 'submissions', 'announcements',
                    ],
                    'latest_announcements',
                    'active_tasks',
                    'latest_submissions',
                ],
            ]);

        $this->withApiToken($token)
            ->getJson('/api/v1/staff/settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['program_name']]);

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/settings', [
                'program_name' => 'Program Studi Psikologi API',
                'semester' => 'Ganjil',
            ])
            ->assertOk()
            ->assertJsonPath('data.program_name', 'Program Studi Psikologi API');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.program_name', 'Program Studi Psikologi API');
    }

    public function test_staff_can_manage_study_programs(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $prodiId = $this->apiProdiId();

        $created = $this->withApiToken($token)
            ->postJson('/api/v1/staff/study-programs', [
                'code' => 'prodi-uji',
                'name' => 'Program Studi Uji',
                'degree' => 'S1',
                'active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'prodi-uji')
            ->assertJsonPath('data.active', true);

        $id = $created->json('data.id');
        $this->assertIsString($id);

        $this->withApiToken($token)
            ->postJson('/api/v1/staff/study-programs', [
                'code' => 'prodi-uji',
                'name' => 'Program Studi Lain',
                'degree' => 'S1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/study-programs/'.$id, ['name' => 'Program Studi Uji Baru'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Program Studi Uji Baru');

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/study-programs/'.$prodiId)
            ->assertUnprocessable();

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/study-programs/'.$id)
            ->assertNoContent();
    }

    public function test_staff_can_manage_users_and_last_admin_is_protected(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $prodiId = $this->apiProdiId();
        $users = app(UserRepository::class);

        $created = $this->withApiToken($token)
            ->postJson('/api/v1/staff/users', [
                'name' => 'Mahasiswa API',
                'npm' => 'API2026',
                'email' => 'mahasiswa.api@psikologi.test',
                'role' => 'mahasiswa',
                'prodi_id' => $prodiId,
                'password' => 'password-baru',
                'class_name' => 'Psikologi A',
                'cohort' => '2026',
                'active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'mahasiswa')
            ->assertJsonPath('data.prodi_id', $prodiId)
            ->assertJsonMissingPath('data.password_hash');

        $id = $created->json('data.id');
        $this->assertIsString($id);

        $this->withApiToken($token)
            ->postJson('/api/v1/staff/users', [
                'name' => 'Duplikat',
                'npm' => 'API2027',
                'email' => 'mahasiswa.api@psikologi.test',
                'role' => 'mahasiswa',
                'prodi_id' => $prodiId,
                'password' => 'password-baru',
                'class_name' => 'Psikologi A',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->withApiToken($token)
            ->getJson('/api/v1/staff/students?search=mahasiswa.api@psikologi.test')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->withApiToken($token)
            ->postJson('/api/v1/staff/users/'.$id.'/reset-password', [
                'password' => 'password-reset',
            ])
            ->assertOk();

        $studentToken = $this->apiLogin('mahasiswa.api@psikologi.test', 'password-reset');
        $this->assertNotSame('', $studentToken);

        $pjmk = $users->findActiveByLogin('pjmk@psikologi.test');
        $this->assertNotNull($pjmk);

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/users/'.$pjmk['id'])
            ->assertUnprocessable();

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/users/'.$id)
            ->assertNoContent();

        $admin = $users->findActiveByLogin('admin@psikologi.test');
        $this->assertNotNull($admin);

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/users/'.$admin['id'], ['active' => false])
            ->assertOk();

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/users/'.$pjmk['id'], ['active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('active');
    }

    public function test_staff_can_manage_courses_and_schedules(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $prodiId = $this->apiProdiId();

        $created = $this->withApiToken($token)
            ->postJson('/api/v1/staff/courses', [
                'code' => 'API101',
                'name' => 'Mata Kuliah API',
                'credits' => 3,
                'lecturer' => 'Dosen API',
                'semester' => 'Ganjil',
                'class_name' => 'Psikologi A',
                'prodi_ids' => [$prodiId],
                'meeting_link' => 'https://meet.example.test/api',
                'description' => 'Mata kuliah untuk pengujian.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'API101')
            ->assertJsonPath('data.prodi_ids', [$prodiId]);

        $courseId = $created->json('data.id');
        $this->assertIsString($courseId);

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/courses/'.$courseId, [
                'name' => 'Mata Kuliah API Revisi',
                'prodi_ids' => [$prodiId],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mata Kuliah API Revisi');

        $this->withApiToken($token)
            ->postJson('/api/v1/staff/schedules', [
                'course_id' => $courseId,
                'day' => 'Senin',
                'start_time' => '08:00',
                'end_time' => '09:40',
                'mode' => 'offline',
                'class_name' => 'Psikologi A',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room');

        $schedule = $this->withApiToken($token)
            ->postJson('/api/v1/staff/schedules', [
                'course_id' => $courseId,
                'day' => 'Senin',
                'start_time' => '08:00',
                'end_time' => '09:40',
                'mode' => 'online',
                'platform' => 'zoom',
                'meeting_link' => 'https://zoom.example.test/api',
                'class_name' => 'Psikologi A',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'online')
            ->assertJsonPath('data.meeting_link', 'https://zoom.example.test/api');

        $scheduleId = $schedule->json('data.id');
        $this->assertIsString($scheduleId);

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/schedules/'.$scheduleId, [
                'mode' => 'offline',
                'room' => 'Ruang API',
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'offline')
            ->assertJsonPath('data.room', 'Ruang API')
            ->assertJsonPath('data.meeting_link', null);

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/schedules/'.$scheduleId)
            ->assertNoContent();

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/courses/'.$courseId)
            ->assertNoContent();
    }

    public function test_staff_can_manage_groups_and_members(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $course = app(CourseRepository::class)->all()->firstWhere('code', 'PSI101');
        $users = app(UserRepository::class);
        $ayu = $users->findActiveByLogin('ayu@psikologi.test');
        $bima = $users->findActiveByLogin('bima@psikologi.test');
        $citra = $users->findActiveByLogin('citra@psikologi.test');

        $this->assertNotNull($course);
        $this->assertNotNull($ayu);
        $this->assertNotNull($bima);
        $this->assertNotNull($citra);

        $created = $this->withApiToken($token)
            ->postJson('/api/v1/staff/groups', [
                'course_id' => $course['id'],
                'name' => 'Kelompok API',
                'leader_id' => $ayu['id'],
                'member_ids' => [$bima['id']],
                'description' => 'Kelompok untuk pengujian API.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kelompok API')
            ->assertJsonPath('data.members_count', 2);

        $groupId = $created->json('data.id');
        $this->assertIsString($groupId);

        $this->withApiToken($token)
            ->postJson('/api/v1/staff/groups/'.$groupId.'/members', [
                'user_ids' => [$citra['id']],
            ])
            ->assertOk()
            ->assertJsonPath('data.members_count', 3);

        $this->withApiToken($token)
            ->getJson('/api/v1/staff/groups/'.$groupId.'?with_members=1')
            ->assertOk()
            ->assertJsonCount(3, 'data.members')
            ->assertJsonStructure(['data' => ['members' => [['email', 'phone']]]]);

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/groups/'.$groupId.'/members/'.$ayu['id'])
            ->assertUnprocessable();

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/groups/'.$groupId.'/members/'.$bima['id'])
            ->assertNoContent();

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/groups/'.$groupId)
            ->assertNoContent();
    }

    public function test_staff_task_list_can_be_filtered_by_group(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $task = app(TaskRepository::class)->all()
            ->first(fn (array $candidate): bool => TaskGroups::assignedIds($candidate) !== []);

        $this->assertNotNull($task);
        $groupId = TaskGroups::assignedIds($task)[0];

        $response = $this->withApiToken($token)
            ->getJson('/api/v1/staff/tasks?per_page=100&group_id='.$groupId)
            ->assertOk();

        $tasks = collect($response->json('data'));
        $this->assertNotEmpty($tasks);
        $this->assertTrue($tasks->every(
            fn (array $candidate): bool => in_array($groupId, TaskGroups::assignedIds($candidate), true),
        ));

        $this->withApiToken($token)
            ->getJson('/api/v1/staff/tasks?group_id=missing-group')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_staff_submission_list_can_be_filtered_by_course(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $submission = app(SubmissionRepository::class)->all()->first();
        $this->assertNotNull($submission);

        $task = app(TaskRepository::class)->find((string) $submission['task_id']);
        $this->assertNotNull($task);

        $courseId = (string) $task['course_id'];
        $response = $this->withApiToken($token)
            ->getJson('/api/v1/staff/submissions?per_page=100&course_id='.$courseId)
            ->assertOk();

        $submissions = collect($response->json('data'));
        $this->assertNotEmpty($submissions);
        $this->assertTrue($submissions->every(
            fn (array $candidate): bool => (string) ($candidate['task_course_id'] ?? '') === $courseId,
        ));
    }

    public function test_staff_can_manage_tasks_and_grade_submissions(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $course = app(CourseRepository::class)->all()->firstWhere('code', 'PSI101');
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');

        $this->assertNotNull($course);
        $this->assertNotNull($student);

        $task = $this->withApiToken($token)
            ->postJson('/api/v1/staff/tasks', [
                'course_id' => $course['id'],
                'type' => 'individu',
                'meeting_number' => 3,
                'status' => 'active',
                'title' => 'Tugas API Individu',
                'description' => 'Tugas untuk pengujian API.',
                'deadline' => now()->addWeek()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Tugas API Individu')
            ->assertJsonPath('data.group_ids', []);

        $taskId = $task->json('data.id');
        $this->assertIsString($taskId);

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/tasks/'.$taskId, [
                'deadline_note' => 'Kumpulkan sebelum tengah malam.',
            ])
            ->assertOk()
            ->assertJsonPath('data.deadline_note', 'Kumpulkan sebelum tengah malam.');

        $submission = $this->withApiToken($token)
            ->postJson('/api/v1/staff/submissions', [
                'task_id' => $taskId,
                'user_id' => $student['id'],
                'status' => 'submitted',
                'link' => 'https://docs.google.com/document/d/api-test',
                'notes' => 'Pengumpulan lewat API.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.student_name', 'Ayu Lestari');

        $submissionId = $submission->json('data.id');
        $this->assertIsString($submissionId);

        $this->withApiToken($token)
            ->postJson('/api/v1/staff/submissions', [
                'task_id' => $taskId,
                'user_id' => $student['id'],
                'status' => 'submitted',
                'link' => 'https://docs.google.com/document/d/duplikat',
            ])
            ->assertUnprocessable();

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/submissions/'.$submissionId, [
                'status' => 'graded',
                'grade' => 90,
                'feedback' => 'Kerja bagus.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'graded')
            ->assertJsonPath('data.grade', 90)
            ->assertJsonPath('data.feedback', 'Kerja bagus.');

        $this->withApiToken($token)
            ->getJson('/api/v1/staff/submissions/'.$submissionId)
            ->assertOk()
            ->assertJsonPath('data.task_title', 'Tugas API Individu');

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/submissions/'.$submissionId)
            ->assertNoContent();

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/tasks/'.$taskId)
            ->assertNoContent();
    }

    public function test_staff_can_manage_announcements(): void
    {
        $token = $this->apiLogin('pjmk@psikologi.test');
        $prodiId = $this->apiProdiId();

        $created = $this->withApiToken($token)
            ->postJson('/api/v1/staff/announcements', [
                'title' => 'Pengumuman API',
                'audience' => 'all',
                'published_at' => now()->toIso8601String(),
                'body' => 'Isi pengumuman untuk pengujian API.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Pengumuman API')
            ->assertJsonPath('data.prodi_ids', [])
            ->assertJsonPath('data.prodi_names', ['Publik']);

        $id = $created->json('data.id');
        $this->assertIsString($id);

        $this->withApiToken($token)
            ->patchJson('/api/v1/staff/announcements/'.$id, [
                'audience' => 'mahasiswa',
                'prodi_ids' => [$prodiId],
            ])
            ->assertOk()
            ->assertJsonPath('data.audience', 'mahasiswa')
            ->assertJsonPath('data.prodi_ids', [$prodiId])
            ->assertJsonPath('data.prodi_names', ['Psikologi']);

        $this->withApiToken($token)
            ->getJson('/api/v1/staff/announcements?search=Pengumuman API')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->withApiToken($token)
            ->postJson('/api/v1/staff/announcements', [
                'title' => 'Pengumuman Invalid',
                'audience' => 'all',
                'course_id' => 'tidak-ada',
                'published_at' => now()->toIso8601String(),
                'body' => 'Isi.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');

        $this->withApiToken($token)
            ->deleteJson('/api/v1/staff/announcements/'.$id)
            ->assertNoContent();
    }
}
