<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

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
