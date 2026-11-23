<?php

namespace App\Http\Controllers\Api\V1;

use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\MaterialRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\TaskRepository;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDisplayController extends ApiController
{
    public function courses(Request $request, CourseRepository $courses): JsonResponse
    {
        $items = $courses->withProgramNames($courses->all());
        $items = $this->search($items, $request, [
            'code', 'name', 'lecturer', 'semester', 'class_name', 'prodi_names',
        ]);
        $items = $this->filter($items, $request, 'class_name', 'class_name');
        $items = $this->filter($items, $request, 'semester', 'semester');
        $items = $this->sort($items, $request, ['code', 'name', 'credits', 'semester', 'class_name'], 'name');

        return $this->paginated(
            $items,
            $request,
            fn (array $course): array => ApiPresenter::course($course),
        );
    }

    public function schedules(Request $request, ScheduleRepository $schedules): JsonResponse
    {
        $items = $schedules->withCourse($schedules->all());
        $items = $this->search($items, $request, [
            'course_name', 'course_code', 'course_lecturer', 'day', 'room', 'class_name',
        ]);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->filter($items, $request, 'day', 'day');
        $items = $this->filter($items, $request, 'mode', 'mode');
        $items = $this->filter($items, $request, 'class_name', 'class_name');
        $items = $this->sort($items, $request, ['start_time', 'end_time', 'day', 'class_name'], 'start_time');

        return $this->paginated(
            $items,
            $request,
            fn (array $schedule): array => ApiPresenter::schedule($schedule),
        );
    }

    public function groups(Request $request, GroupRepository $groups): JsonResponse
    {
        $items = $groups->withDetails($groups->all());
        $items = $this->search($items, $request, ['name', 'description', 'course_name', 'leader_name']);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->sort($items, $request, ['name', 'course_name', 'created_at'], 'name');

        $includeMembers = $request->boolean('with_members');

        return $this->paginated(
            $items,
            $request,
            fn (array $group): array => ApiPresenter::group(
                $group,
                $includeMembers ? $groups->membersWithUser((string) $group['id']) : null,
                false,
            ),
        );
    }

    public function tasks(Request $request, TaskRepository $tasks): JsonResponse
    {
        $items = $tasks->withDetails($tasks->all());
        $items = $this->search($items, $request, [
            'title', 'description', 'course_name', 'course_code', 'type_label', 'status_label', 'group_name',
        ]);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->filter($items, $request, 'type', 'type');
        $items = $this->filter($items, $request, 'status', 'status');
        $items = $this->sort($items, $request, ['deadline', 'title', 'created_at', 'status'], 'deadline');

        return $this->paginated(
            $items,
            $request,
            fn (array $task): array => ApiPresenter::task($task),
        );
    }

    public function materials(Request $request, MaterialRepository $materials): JsonResponse
    {
        $items = $materials->withCourse($materials->all());
        $items = $this->search($items, $request, [
            'title', 'description', 'course_name', 'course_code',
        ]);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->sort($items, $request, ['title', 'meeting_number', 'created_at'], 'title');

        return $this->paginated(
            $items,
            $request,
            fn (array $material): array => ApiPresenter::material($material),
        );
    }
}
