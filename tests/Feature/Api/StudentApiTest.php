<?php

namespace Tests\Feature\Api;

use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\TaskGroups;
use Tests\Concerns\InteractsWithApi;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class StudentApiTest extends TestCase
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

    public function test_student_can_read_dashboard_and_academic_resources(): void
    {
        $token = $this->apiLogin('ayu@psikologi.test');

        $this->withApiToken($token)
            ->getJson('/api/v1/student/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'student' => ['id', 'name', 'npm', 'prodi_name'],
                    'counts' => ['tasks', 'courses', 'groups', 'unread_notifications'],
                    'upcoming_tasks',
                    'today_schedules',
                    'groups',
                    'latest_announcements',
                ],
            ])
            ->assertJsonPath('data.student.email', 'ayu@psikologi.test');

        $this->withApiToken($token)
            ->getJson('/api/v1/student/courses?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'prodi_ids']], 'meta']);

        $this->withApiToken($token)
            ->getJson('/api/v1/student/schedules?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->withApiToken($token)
            ->getJson('/api/v1/student/announcements?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->withApiToken($token)
            ->getJson('/api/v1/student/groups?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'members', 'member_ids']]]);
    }

    public function test_student_task_list_only_contains_accessible_tasks_and_details(): void
    {
        $token = $this->apiLogin('ayu@psikologi.test');
        $response = $this->withApiToken($token)
            ->getJson('/api/v1/student/tasks?per_page=100')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'status', 'course_name', 'submission',
                    'can_submit', 'can_update',
                ]],
                'meta',
            ]);

        $tasks = collect($response->json('data'));
        $this->assertNotEmpty($tasks);

        $task = $tasks->first();
        $this->assertNotNull($task['id']);

        $this->withApiToken($token)
            ->getJson('/api/v1/student/tasks/'.$task['id'])
            ->assertOk()
            ->assertJsonPath('data.id', $task['id'])
            ->assertJsonStructure(['data' => ['id', 'title', 'is_overdue', 'submission']]);

        $privateTask = app(TaskRepository::class)->all()
            ->first(fn (array $candidate): bool => (string) ($candidate['type'] ?? '') === 'kelompok'
                && TaskGroups::assignedIds($candidate) !== []);

        if ($privateTask !== null) {
            $accessible = app(TaskRepository::class)->forStudent(
                app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test'),
            );

            if ($accessible->doesntContain(fn (array $candidate): bool => $candidate['id'] === $privateTask['id'])) {
                $this->withApiToken($token)
                    ->getJson('/api/v1/student/tasks/'.$privateTask['id'])
                    ->assertNotFound();
            }
        }
    }

    public function test_student_can_submit_and_update_an_individual_task(): void
    {
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');
        $this->assertNotNull($student);

        $task = app(TaskRepository::class)->forStudent($student)
            ->first(fn (array $candidate): bool => (string) ($candidate['type'] ?? 'individu') === 'individu'
                && app(SubmissionRepository::class)->forTaskAndUser((string) $candidate['id'], (string) $student['id']) === null);

        $this->assertNotNull($task);

        app(TaskRepository::class)->update((string) $task['id'], [
            'status' => 'active',
            'deadline' => now()->addDay()->toIso8601String(),
        ]);

        $token = $this->apiLogin('ayu@psikologi.test');

        $this->withApiToken($token)
            ->postJson('/api/v1/student/tasks/'.$task['id'].'/submission', [
                'link' => 'https://docs.google.com/document/d/tes-api',
                'notes' => 'Dikirim melalui API.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.notes', 'Dikirim melalui API.');

        $this->withApiToken($token)
            ->postJson('/api/v1/student/tasks/'.$task['id'].'/submission', [
                'link' => 'https://docs.google.com/document/d/tes-api-revisi',
                'notes' => 'Revisi.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->withApiToken($token)
            ->getJson('/api/v1/student/tasks/'.$task['id'])
            ->assertOk()
            ->assertJsonPath('data.can_update', true)
            ->assertJsonPath('data.submission.notes', 'Revisi.');
    }

    public function test_student_cannot_open_another_group(): void
    {
        $token = $this->apiLogin('ayu@psikologi.test');
        $student = app(UserRepository::class)->findActiveByLogin('ayu@psikologi.test');
        $ownGroupIds = app(GroupRepository::class)->forStudent($student)->pluck('id')->all();
        $other = app(GroupRepository::class)->all()->first(
            fn (array $group): bool => ! in_array((string) $group['id'], $ownGroupIds, true),
        );

        $this->assertNotNull($other);

        $this->withApiToken($token)
            ->getJson('/api/v1/student/groups/'.$other['id'])
            ->assertNotFound();
    }

    public function test_student_can_read_and_mark_notifications(): void
    {
        $token = $this->apiLogin('ayu@psikologi.test');
        $response = $this->withApiToken($token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'unread']]);

        $key = $response->json('data.0.key');

        if (is_string($key) && $key !== '') {
            $this->withApiToken($token)
                ->postJson('/api/v1/notifications/read', ['key' => $key])
                ->assertOk()
                ->assertJsonStructure(['message', 'unread_count']);
        }

        $this->withApiToken($token)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
