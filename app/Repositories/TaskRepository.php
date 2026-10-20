<?php

namespace App\Repositories;

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
                $group = $isGroupTask ? $groups->find((string) ($task['group_id'] ?? '')) : null;
                $task['group_name'] = $group['name'] ?? 'Semua mahasiswa';
                $task['type_label'] = $isGroupTask ? 'Kelompok' : 'Individu';

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

                $groupId = ($task['type'] ?? 'individu') === 'kelompok'
                    ? trim((string) ($task['group_id'] ?? ''))
                    : '';

                return $groupId === '' || in_array($groupId, $groupIds, true);
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
}
