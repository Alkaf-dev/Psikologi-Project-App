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
        $allTasks = $tasks->all();

        return response()->json([
            'data' => [
                'counts' => [
                    'students' => $users->students()->count(),
                    'staff' => $users->admins()->count(),
                    'courses' => $courses->count(),
                    'schedules' => $schedules->count(),
                    'groups' => $groups->count(),
                    'tasks' => $allTasks->count(),
                    'active_tasks' => $allTasks->filter(
                        fn (array $task): bool => TaskStatus::normalize($task['status'] ?? '') === TaskStatus::ACTIVE,
                    )->count(),
                    'submissions' => $submissions->count(),
                    'announcements' => $announcements->count(),
                ],
                'latest_announcements' => $announcements->withAuthor(
                    $announcements->all()->sortByDesc('published_at')->take(3),
                )->map(fn (array $announcement): array => ApiPresenter::announcement($announcement))->values(),
            ],
        ]);
    }
}
