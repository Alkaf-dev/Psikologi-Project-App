<?php

namespace App\Repositories;

use App\Support\TaskGroups;
use Illuminate\Support\Collection;

class SubmissionRepository extends BaseSheetRepository
{
    protected string $table = 'submissions';

    /**
     * @return array<string, mixed>|null
     */
    public function forTaskAndUser(string $taskId, string $userId): ?array
    {
        return $this->all()->first(function (array $submission) use ($taskId, $userId): bool {
            return (string) ($submission['task_id'] ?? '') === $taskId
                && (string) ($submission['user_id'] ?? '') === $userId;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forTaskAndGroup(string $taskId, string $groupId): ?array
    {
        if (trim($groupId) === '') {
            return null;
        }

        return $this->all()
            ->filter(fn (array $submission): bool => (string) ($submission['task_id'] ?? '') === $taskId
                && (string) ($submission['group_id'] ?? '') === $groupId)
            ->sortByDesc(fn (array $submission): string => (string) ($submission['submitted_at'] ?? ''))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $task
     * @param  array<int, string>  $studentGroupIds
     * @return array<string, mixed>|null
     */
    public function forTaskForStudent(array $task, string $userId, array $studentGroupIds): ?array
    {
        if (($task['type'] ?? 'individu') === 'kelompok') {
            $groupId = TaskGroups::matchingGroupId($task, $studentGroupIds);

            return $groupId !== null
                ? $this->forTaskAndGroup((string) ($task['id'] ?? ''), $groupId)
                : null;
        }

        return $this->forTaskAndUser((string) ($task['id'] ?? ''), $userId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertForTaskAndUser(string $taskId, string $userId, array $data): array
    {
        $existing = $this->forTaskAndUser($taskId, $userId);

        $data['task_id'] = $taskId;
        $data['user_id'] = $userId;

        if ($existing) {
            return $this->update((string) $existing['id'], $data);
        }

        return $this->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertForTaskAndGroup(string $taskId, string $groupId, array $data): array
    {
        $existing = $this->forTaskAndGroup($taskId, $groupId);

        $data['task_id'] = $taskId;
        $data['group_id'] = $groupId;

        if ($existing) {
            return $this->update((string) $existing['id'], $data);
        }

        return $this->create($data);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(string $userId): Collection
    {
        return $this->all()
            ->filter(fn (array $submission): bool => (string) ($submission['user_id'] ?? '') === $userId)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function withDetails(?Collection $submissions = null): Collection
    {
        $tasks = app(TaskRepository::class);
        $users = app(UserRepository::class);
        $groups = app(GroupRepository::class);

        return ($submissions ?? $this->all())
            ->map(function (array $submission) use ($tasks, $users, $groups): array {
                $task = $tasks->find((string) ($submission['task_id'] ?? ''));
                $submission['task_title'] = $task['title'] ?? '-';
                $submission['task_type'] = $task['type'] ?? 'individu';
                $submission['student_name'] = $users->nameById((string) ($submission['user_id'] ?? ''));
                $submission['student_npm'] = (string) ($users->find((string) ($submission['user_id'] ?? ''))['npm'] ?? '');
                $submission['representative_name'] = $submission['student_name'];
                $submission['group_name'] = $groups->find((string) ($submission['group_id'] ?? ''))['name'] ?? '-';

                return $submission;
            })
            ->values();
    }

    /**
     * @return array<string, int>
     */
    public function countsByTask(): array
    {
        return $this->all()
            ->groupBy('task_id')
            ->map(fn (Collection $submissions): int => $submissions->count())
            ->all();
    }
}
