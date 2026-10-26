<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\ApiPresenter;
use App\Support\TaskStatus;
use Illuminate\Http\JsonResponse;

class DashboardController extends ApiController
{
    public function __invoke(
        UserRepository $users,
        CourseRepository $courses,
        ScheduleRepository $schedules,
        GroupRepository $groups,
        TaskRepository $tasks,
        SubmissionRepository $submissions,
        AnnouncementRepository $announcements,
    ): JsonResponse {
        $allUsers = $users->all();
        $allTasks = $tasks->all();
        $allSubmissions = $submissions->all();
        $allAnnouncements = $announcements->all();
        $submissionCounts = $allSubmissions->groupBy('task_id')->map->count();

        $activeTasks = $allTasks
            ->filter(fn (array $task): bool => TaskStatus::normalize($task['status'] ?? '') === TaskStatus::ACTIVE)
            ->sortBy('deadline')
            ->take(5)
            ->values();

        $activeTasks = $tasks->withDetails($activeTasks)
            ->map(function (array $task) use ($submissionCounts): array {
                $task['submission_count'] = $submissionCounts[(string) ($task['id'] ?? '')] ?? 0;

                return ApiPresenter::task($task);
            })
            ->values();

        $latestSubmissions = $submissions->withDetails(
            $allSubmissions->sortByDesc('submitted_at')->take(5)->values(),
        )->map(fn (array $submission): array => ApiPresenter::submission($submission))->values();

        return response()->json([
            'data' => [
                'counts' => [
                    'students' => $allUsers->filter(fn (array $user): bool => $users->isStudent($user))->count(),
                    'staff' => $allUsers->filter(fn (array $user): bool => $users->isAdmin($user))->count(),
                    'courses' => $courses->count(),
                    'schedules' => $schedules->count(),
                    'groups' => $groups->count(),
                    'tasks' => $allTasks->count(),
                    'active_tasks' => $allTasks->filter(
                        fn (array $task): bool => TaskStatus::normalize($task['status'] ?? '') === TaskStatus::ACTIVE,
                    )->count(),
                    'submissions' => $allSubmissions->count(),
                    'announcements' => $allAnnouncements->count(),
                ],
                'latest_announcements' => $announcements->withAuthor(
                    $allAnnouncements->sortByDesc('published_at')->take(3),
                )->map(fn (array $announcement): array => ApiPresenter::announcement($announcement))->values(),
                'active_tasks' => $activeTasks,
                'latest_submissions' => $latestSubmissions,
            ],
        ]);
    }
}
