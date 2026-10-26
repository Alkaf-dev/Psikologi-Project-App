<?php

namespace App\Http\Controllers\Api\V1;

use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Support\ApiPresenter;
use App\Support\NotificationCenter;
use App\Support\TaskGroups;
use App\Support\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class StudentController extends ApiController
{
    public function dashboard(
        Request $request,
        TaskRepository $tasks,
        ScheduleRepository $schedules,
        GroupRepository $groups,
        SubmissionRepository $submissions,
        AnnouncementRepository $announcements,
        NotificationCenter $notifications,
    ): JsonResponse {
        $student = $this->currentUser();
        $userId = (string) $student['id'];
        $studentGroupIds = app(GroupMemberRepository::class)->groupIdsForUser($userId);
        $taskItems = $this->decorateTasks(
            $tasks->withDetails($tasks->forStudent($student)),
            $userId,
            $studentGroupIds,
            $submissions,
            $groups,
        );

        $activeTasks = $taskItems->where('status', TaskStatus::ACTIVE);
        $pendingTasks = $activeTasks->whereNull('submission');
        $overdueTasks = $pendingTasks->filter(fn (array $task): bool => (bool) ($task['is_overdue'] ?? false));
        $today = (string) (array_search(now()->dayOfWeekIso, ScheduleRepository::DAYS, true) ?: '');
        $scheduleItems = $schedules->withCourse($schedules->forStudent($student))
            ->filter(fn (array $schedule): bool => (string) ($schedule['day'] ?? '') === $today)
            ->values();
        $groupItems = $this->presentGroups($groups->forStudent($student), $groups);
        $announcementItems = $announcements->withAuthor(
            $announcements->published('mahasiswa', (string) ($student['prodi_id'] ?? ''))->take(3),
        );

        return response()->json([
            'data' => [
                'student' => ApiPresenter::user($student),
                'counts' => [
                    'tasks' => $taskItems->count(),
                    'active_tasks' => $activeTasks->count(),
                    'pending_tasks' => $pendingTasks->count(),
                    'overdue_tasks' => $overdueTasks->count(),
                    'submitted_tasks' => $taskItems->whereNotNull('submission')->count(),
                    'courses' => app(CourseRepository::class)->forStudent($student)->count(),
                    'groups' => $groupItems->count(),
                    'today_schedules' => $scheduleItems->count(),
                    'unread_notifications' => $notifications->unreadCount($student),
                ],
                'upcoming_tasks' => $taskItems
                    ->where('status', TaskStatus::ACTIVE)
                    ->sortBy('deadline')
                    ->take(5)
                    ->values(),
                'today_schedules' => $scheduleItems
                    ->map(fn (array $schedule): array => ApiPresenter::schedule($schedule))
                    ->values(),
                'groups' => $groupItems,
                'latest_announcements' => $announcementItems
                    ->map(fn (array $announcement): array => ApiPresenter::announcement($announcement))
                    ->values(),
            ],
        ]);
    }

    public function courses(Request $request, CourseRepository $courses): JsonResponse
    {
        $student = $this->currentUser();
        $items = $this->search(
            $courses->withProgramNames($courses->forStudent($student)),
            $request,
            ['code', 'name', 'lecturer', 'semester', 'class_name'],
        );
        $items = $this->sort($items, $request, ['code', 'name', 'semester', 'credits'], 'name');

        return $this->paginated(
            $items,
            $request,
            fn (array $course): array => ApiPresenter::course($course),
        );
    }

    public function schedules(Request $request, ScheduleRepository $schedules): JsonResponse
    {
        $student = $this->currentUser();
        $items = $schedules->withCourse($schedules->forStudent($student));
        $items = $this->search($items, $request, ['course_name', 'course_code', 'day', 'room']);
        $items = $this->filter($items, $request, 'day', 'day');
        $items = $this->filter($items, $request, 'mode', 'mode');
        $items = $this->filter($items, $request, 'course_id', 'course_id');

        return $this->paginated(
            $items,
            $request,
            fn (array $schedule): array => ApiPresenter::schedule($schedule),
        );
    }

    public function tasks(
        Request $request,
        TaskRepository $tasks,
        SubmissionRepository $submissions,
        GroupRepository $groups,
    ): JsonResponse {
        $student = $this->currentUser();
        $userId = (string) $student['id'];
        $studentGroupIds = app(GroupMemberRepository::class)->groupIdsForUser($userId);
        $items = $tasks->withDetails($tasks->forStudent($student));
        $items = $this->search($items, $request, [
            'title', 'description', 'course_name', 'course_code', 'type_label', 'status_label',
        ]);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->filter($items, $request, 'type', 'type');
        $items = $this->filter($items, $request, 'status', 'status');
        $items = $this->filterByTaskGroup($items, $request);
        $items = $this->sort($items, $request, ['deadline', 'title', 'created_at'], 'deadline');

        return $this->paginated(
            $this->decorateTasks($items, $userId, $studentGroupIds, $submissions, $groups),
            $request,
        );
    }

    public function task(
        string $id,
        TaskRepository $tasks,
        SubmissionRepository $submissions,
        GroupRepository $groups,
        GroupMemberRepository $members,
    ): JsonResponse {
        $student = $this->currentUser();
        $userId = (string) $student['id'];
        $task = $tasks->forStudent($student)->first(
            fn (array $task): bool => (string) ($task['id'] ?? '') === $id,
        );

        $task = $this->found($task, 'Tugas tidak ditemukan.');
        $task = $tasks->withDetails(collect([$task]))->first();
        $studentGroupIds = $members->groupIdsForUser($userId);
        $decorated = $this->decorateTasks(
            collect([$task]),
            $userId,
            $studentGroupIds,
            $submissions,
            $groups,
        )->first();

        return $this->data($decorated);
    }

    public function submitTask(
        Request $request,
        string $id,
        TaskRepository $tasks,
        SubmissionRepository $submissions,
        GroupMemberRepository $members,
    ): JsonResponse {
        $data = $request->validate([
            'link' => ['required', 'url:http,https', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $student = $this->currentUser();
        $userId = (string) $student['id'];
        $task = $tasks->forStudent($student)->first(
            fn (array $task): bool => (string) ($task['id'] ?? '') === $id,
        );

        $task = $this->found($task, 'Tugas tidak ditemukan.');

        if (TaskStatus::normalize($task['status'] ?? '') !== TaskStatus::ACTIVE) {
            abort(422, 'Tugas ini sudah selesai atau belum dipublikasikan.');
        }

        $isGroupTask = ($task['type'] ?? 'individu') === 'kelompok';
        $studentGroupIds = $members->groupIdsForUser($userId);
        $groupId = $isGroupTask ? (string) (TaskGroups::matchingGroupId($task, $studentGroupIds) ?? '') : '';

        if ($isGroupTask && $groupId === '') {
            abort(422, 'Kelompok Anda belum ditugaskan pada tugas ini.');
        }

        $deadline = $this->parseDate($task['deadline'] ?? null);
        $existing = $isGroupTask
            ? $submissions->forTaskAndGroup($id, $groupId)
            : $submissions->forTaskAndUser($id, $userId);

        if ($isGroupTask && $existing !== null) {
            if ((string) ($existing['user_id'] ?? '') !== $userId) {
                abort(422, 'Hanya perwakilan kelompok yang dapat memperbarui pengumpulan ini.');
            }

            if ($deadline !== null && now()->greaterThan($deadline)) {
                abort(422, 'Pengumpulan kelompok hanya dapat diperbarui sampai deadline.');
            }
        }

        $isLate = $deadline !== null && now()->greaterThan($deadline);
        $payload = [
            'group_id' => $isGroupTask ? $groupId : '',
            'status' => $isLate ? 'late' : 'submitted',
            'link' => (string) $data['link'],
            'notes' => (string) ($data['notes'] ?? ''),
            'submitted_at' => now()->toIso8601String(),
        ];

        if ($isGroupTask) {
            $payload['user_id'] = $userId;
            $submission = $submissions->upsertForTaskAndGroup($id, $groupId, $payload);
        } else {
            $submission = $submissions->upsertForTaskAndUser($id, $userId, $payload);
        }

        return response()->json([
            'data' => ApiPresenter::submission($submission),
            'message' => $isLate ? 'Tugas berhasil dikumpulkan (terlambat).' : 'Tugas berhasil dikumpulkan.',
        ], $existing === null ? 201 : 200);
    }

    public function groups(Request $request, GroupRepository $groups): JsonResponse
    {
        $student = $this->currentUser();
        $items = $groups->forStudent($student);
        $items = $this->search($items, $request, ['name', 'description']);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->sort($items, $request, ['name', 'created_at'], 'name');

        return $this->paginated(
            $this->presentGroups($items, $groups),
            $request,
        );
    }

    public function group(string $id, GroupRepository $groups): JsonResponse
    {
        $student = $this->currentUser();
        $group = $groups->forStudent($student)->first(
            fn (array $group): bool => (string) ($group['id'] ?? '') === $id,
        );

        $group = $this->found($group, 'Kelompok tidak ditemukan.');
        $group = $groups->withDetails(collect([$group]))->first();

        return $this->data(ApiPresenter::group(
            $group,
            $groups->membersWithUser($id),
        ));
    }

    public function announcements(Request $request, AnnouncementRepository $announcements): JsonResponse
    {
        $student = $this->currentUser();
        $items = $announcements->published('mahasiswa', (string) ($student['prodi_id'] ?? ''));
        $items = $this->search($items, $request, ['title', 'body']);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->withAnnouncementDetails($items);
        $items = $this->sort($items, $request, ['published_at', 'title'], 'published_at', 'desc');

        return $this->paginated(
            $items,
            $request,
            fn (array $announcement): array => ApiPresenter::announcement($announcement),
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tasks
     * @return Collection<int, array<string, mixed>>
     */
    protected function decorateTasks(
        Collection $tasks,
        string $userId,
        array $studentGroupIds,
        SubmissionRepository $submissions,
        GroupRepository $groups,
    ): Collection {
        return $tasks->map(function (array $task) use (
            $userId,
            $studentGroupIds,
            $submissions,
            $groups,
        ): array {
            $isGroupTask = ($task['type'] ?? 'individu') === 'kelompok';
            $submission = $submissions->forTaskForStudent($task, $userId, $studentGroupIds);
            $matchedGroupId = $isGroupTask
                ? (string) (TaskGroups::matchingGroupId($task, $studentGroupIds) ?? '')
                : '';
            $deadline = $this->parseDate($task['deadline'] ?? null);
            $status = TaskStatus::normalize($task['status'] ?? '');
            $isRepresentative = $submission !== null
                && (! $isGroupTask || (string) ($submission['user_id'] ?? '') === $userId);
            $groupName = '';

            if ($isGroupTask && $matchedGroupId !== '') {
                $groupName = (string) ($groups->find($matchedGroupId)['name'] ?? '');
            } elseif ($isGroupTask && TaskGroups::assignedIds($task) === []) {
                $groupName = 'Semua mahasiswa';
            }

            $item = ApiPresenter::task($task);
            $item['matched_group_id'] = $matchedGroupId !== '' ? $matchedGroupId : null;
            $item['portal_group_name'] = $groupName !== '' ? $groupName : null;
            $item['submission'] = $submission !== null ? ApiPresenter::submission($submission) : null;
            $item['submission_status'] = $submission['status'] ?? null;
            $item['submission_label'] = match ($submission['status'] ?? null) {
                'graded', 'submitted' => 'Sudah dikumpulkan',
                'late' => 'Terlambat dikumpulkan',
                default => 'Belum dikumpulkan',
            };
            $item['is_representative'] = $isRepresentative;
            $item['can_submit'] = $submission === null
                && $status === TaskStatus::ACTIVE
                && (! $isGroupTask || $matchedGroupId !== '');
            $item['can_update'] = $submission !== null
                && $status === TaskStatus::ACTIVE
                && (! $isGroupTask || $isRepresentative)
                && (! $isGroupTask || $deadline === null || now()->lessThanOrEqualTo($deadline));

            return $item;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    protected function presentGroups(Collection $groups, GroupRepository $repository): Collection
    {
        return $repository->withDetails($groups)
            ->map(fn (array $group): array => ApiPresenter::group(
                $group,
                $repository->membersWithUser((string) $group['id']),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $announcements
     * @return Collection<int, array<string, mixed>>
     */
    protected function withAnnouncementDetails(Collection $announcements): Collection
    {
        return app(AnnouncementRepository::class)->withAuthor($announcements);
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
