<?php

namespace App\Support;

use App\Repositories\GroupRepository;

final class TaskGroups
{
    /**
     * @return array<int, string>
     */
    public static function ids(mixed $value): array
    {
        if (! is_array($value) && str_starts_with(trim((string) $value), '[')) {
            $decoded = json_decode((string) $value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        $values = is_array($value) ? $value : explode(',', (string) $value);

        return collect($values)
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function csv(mixed $value): string
    {
        $ids = self::ids($value);

        sort($ids);

        return implode(',', $ids);
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<int, string>
     */
    public static function assignedIds(array $task): array
    {
        $ids = self::ids($task['group_ids'] ?? '');

        if ($ids !== []) {
            return $ids;
        }

        $legacyGroupId = trim((string) ($task['group_id'] ?? ''));

        return $legacyGroupId !== '' ? [$legacyGroupId] : [];
    }

    /**
     * @param  array<string, mixed>  $task
     * @param  array<int, string>  $studentGroupIds
     */
    public static function matchingGroupId(array $task, array $studentGroupIds): ?string
    {
        $assignedIds = self::assignedIds($task);

        if ($assignedIds === [] || $studentGroupIds === []) {
            return null;
        }

        $studentGroupIds = array_map(fn (mixed $id): string => (string) $id, $studentGroupIds);
        $matches = array_values(array_intersect($assignedIds, $studentGroupIds));

        return $matches[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function names(mixed $value, string $blankLabel = 'Semua mahasiswa'): array
    {
        $ids = self::ids($value);

        if ($ids === []) {
            return [$blankLabel];
        }

        $groups = app(GroupRepository::class);

        $names = array_map(
            fn (string $id): string => (string) ($groups->find($id)['name'] ?? 'Kelompok tidak ditemukan'),
            $ids,
        );

        sort($names);

        return $names;
    }

    public static function label(mixed $value, string $blankLabel = 'Semua mahasiswa'): string
    {
        return implode(', ', self::names($value, $blankLabel));
    }
}
