<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class GroupMemberRepository extends BaseSheetRepository
{
    protected string $table = 'group_members';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forGroup(string $groupId): Collection
    {
        return $this->all()
            ->filter(fn (array $member): bool => (string) ($member['group_id'] ?? '') === $groupId)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(string $userId): Collection
    {
        return $this->all()
            ->filter(fn (array $member): bool => (string) ($member['user_id'] ?? '') === $userId)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function groupIdsForUser(string $userId): array
    {
        return $this->forUser($userId)
            ->pluck('group_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $userIds
     */
    public function syncForGroup(string $groupId, array $userIds, ?string $leaderId = null): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        foreach ($this->forGroup($groupId) as $member) {
            $this->delete((string) $member['id']);
        }

        foreach ($userIds as $userId) {
            $this->create([
                'group_id' => $groupId,
                'user_id' => $userId,
                'role' => $userId === $leaderId ? 'ketua' : 'anggota',
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function membershipForCourse(string $userId, string $courseId): ?array
    {
        $groupIds = app(GroupRepository::class)
            ->forCourse($courseId)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return $this->forUser($userId)
            ->first(fn (array $member): bool => in_array((string) ($member['group_id'] ?? ''), $groupIds, true));
    }

    public function addMember(string $groupId, string $userId): array
    {
        $group = app(GroupRepository::class)->find($groupId);

        if (! $group) {
            throw new InvalidArgumentException("Kelompok [{$groupId}] tidak ditemukan.");
        }

        return $this->create([
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => (string) ($group['leader_id'] ?? '') === $userId ? 'ketua' : 'anggota',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $assignments
     */
    public function syncAssignmentsForUser(string $userId, array $assignments): void
    {
        $groups = app(GroupRepository::class);
        $groupCourses = $groups->all()
            ->mapWithKeys(fn (array $group): array => [
                (string) ($group['id'] ?? '') => (string) ($group['course_id'] ?? ''),
            ]);

        $currentByCourse = [];

        foreach ($this->forUser($userId) as $member) {
            $courseId = $groupCourses->get((string) ($member['group_id'] ?? ''));

            if ($courseId === null) {
                continue;
            }

            $currentByCourse[$courseId][] = $member;
        }

        $desired = collect($assignments)
            ->map(function (array $assignment): array {
                return [
                    'course_id' => trim((string) ($assignment['course_id'] ?? '')),
                    'group_id' => trim((string) ($assignment['group_id'] ?? '')),
                ];
            })
            ->filter(fn (array $assignment): bool => $assignment['course_id'] !== '')
            ->unique('course_id')
            ->values();

        foreach ($desired as $assignment) {
            $courseId = $assignment['course_id'];
            $groupId = $assignment['group_id'];
            $existing = collect($currentByCourse[$courseId] ?? []);

            if ($groupId === '') {
                foreach ($existing as $member) {
                    $this->clearGroupLeader($member, $userId);
                    $this->delete((string) $member['id']);
                }

                continue;
            }

            $group = $groups->find($groupId);

            if (! $group || (string) ($group['course_id'] ?? '') !== $courseId) {
                throw new InvalidArgumentException("Kelompok [{$groupId}] tidak sesuai dengan mata kuliah [{$courseId}].");
            }

            $matching = $existing->first(
                fn (array $member): bool => (string) ($member['group_id'] ?? '') === $groupId,
            );

            foreach ($existing as $member) {
                if ((string) ($member['group_id'] ?? '') === $groupId) {
                    continue;
                }

                $this->clearGroupLeader($member, $userId);
                $this->delete((string) $member['id']);
            }

            if (! $matching) {
                $this->create([
                    'group_id' => $groupId,
                    'user_id' => $userId,
                    'role' => (string) ($group['leader_id'] ?? '') === $userId ? 'ketua' : 'anggota',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $member
     */
    protected function clearGroupLeader(array $member, string $userId): void
    {
        $groups = app(GroupRepository::class);
        $group = $groups->find((string) ($member['group_id'] ?? ''));

        if ($group && (string) ($group['leader_id'] ?? '') === $userId) {
            $groups->update((string) $group['id'], ['leader_id' => '']);
        }
    }

    /**
     * @return array<string, int>
     */
    public function countsByGroup(): array
    {
        return $this->all()
            ->groupBy('group_id')
            ->map(fn (Collection $members): int => $members->count())
            ->all();
    }
}
