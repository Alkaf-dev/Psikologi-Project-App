<?php

namespace App\Repositories;

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
                $submission['student_name'] = $users->nameById((string) ($submission['user_id'] ?? ''));
                $submission['student_npm'] = (string) ($users->find((string) ($submission['user_id'] ?? ''))['npm'] ?? '');
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
