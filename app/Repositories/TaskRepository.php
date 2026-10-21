<?php

namespace App\Repositories;

use App\Support\TaskGroups;
use App\Support\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TaskRepository extends BaseSheetRepository
{
    protected string $table = 'tasks';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function withDetails(?Collection $tasks = null): Collection
    {
        $courses = app(CourseRepository::class);
        $groups = app(GroupRepository::class);

        return ($tasks ?? $this->all())
            ->map(function (array $task) use ($courses, $groups): array {
                $task['course_name'] = $courses->nameById((string) ($task['course_id'] ?? ''));
                $task['course_code'] = (string) ($courses->find((string) ($task['course_id'] ?? ''))['code'] ?? '');
                $isGroupTask = ($task['type'] ?? 'individu') === 'kelompok';
                $assignedGroupIds = $isGroupTask ? TaskGroups::assignedIds($task) : [];

                $groupNames = array_values(array_filter(array_map(
                    fn (string $groupId): string => (string) ($groups->find($groupId)['name'] ?? ''),
                    $assignedGroupIds,
                )));

                $task['group_name'] = $isGroupTask
                    ? ($groupNames !== [] ? implode(', ', $groupNames) : 'Semua mahasiswa')
                    : 'Semua mahasiswa';
                $task['group_ids'] = TaskGroups::csv($assignedGroupIds);
                $task['type_label'] = $isGroupTask ? 'Kelompok' : 'Individu';
                $task['status'] = TaskStatus::normalize($task['status'] ?? '');
                $task['status_label'] = TaskStatus::label($task['status']);
                $task['deadline_label'] = $this->deadlineLabel($task);

                return $task;
            });
    }

    /**
     * @param  array<string, mixed>  $student
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(array $student): Collection
    {
        $courseIds = app(CourseRepository::class)
            ->forStudent($student)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $groupIds = app(GroupMemberRepository::class)
            ->groupIdsForUser((string) ($student['id'] ?? ''));

        return $this->all()
            ->filter(function (array $task) use ($courseIds, $groupIds): bool {
                if (! in_array((string) ($task['course_id'] ?? ''), $courseIds, true)) {
                    return false;
                }

                if (TaskStatus::normalize($task['status'] ?? '') === TaskStatus::DRAFT) {
                    return false;
                }

                if (($task['type'] ?? 'individu') !== 'kelompok') {
                    return true;
                }

                $assignedGroupIds = TaskGroups::assignedIds($task);

                return $assignedGroupIds === []
                    || TaskGroups::matchingGroupId($task, $groupIds) !== null;
            })
            ->sortBy('deadline')
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $courses = app(CourseRepository::class);

        return $this->all()
            ->sortBy('title')
            ->mapWithKeys(function (array $task) use ($courses): array {
                $label = trim((string) ($task['title'] ?? 'Tugas'));
                $course = $courses->nameById((string) ($task['course_id'] ?? ''));

                return [(string) $task['id'] => "{$label} - {$course}"];
            })
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forCourse(string $courseId): Collection
    {
        return $this->all()
            ->filter(fn (array $task): bool => (string) ($task['course_id'] ?? '') === $courseId)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $task
     */
    protected function deadlineLabel(array $task): string
    {
        $note = trim((string) ($task['deadline_note'] ?? ''));

        if ($note !== '') {
            return $note;
        }

        if (blank($task['deadline'] ?? null)) {
            return '-';
        }

        $deadline = Carbon::parse((string) $task['deadline']);

        if ($deadline->isPast()) {
            return 'Deadline terlewati';
        }

        $remainingHours = max(0, (int) ceil(($deadline->getTimestamp() - now()->getTimestamp()) / 3600));
        $remainingDays = max(1, (int) ceil($remainingHours / 24));

        return "Batas waktu tersisa {$remainingDays} hari";
    }
}
