<?php

namespace App\Support;

use App\Repositories\StudyProgramRepository;

final class ProdiAssignments
{
    /**
     * @return array<int, string>
     */
    public static function ids(mixed $value): array
    {
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

    public static function includes(mixed $value, ?string $prodiId, bool $blankMeansAll = false): bool
    {
        $prodiId = trim((string) $prodiId);

        if ($prodiId === '') {
            return false;
        }

        $ids = self::ids($value);

        if ($ids === []) {
            return $blankMeansAll;
        }

        return in_array($prodiId, $ids, true);
    }

    /**
     * @return array<int, string>
     */
    public static function names(mixed $value, string $blankLabel = 'Semua Prodi'): array
    {
        $ids = self::ids($value);

        if ($ids === []) {
            return [$blankLabel];
        }

        $nameMap = app(StudyProgramRepository::class)->nameMap();

        $names = array_map(
            fn (string $id): string => $nameMap[$id] ?? 'Prodi tidak ditemukan',
            $ids,
        );

        sort($names);

        return $names;
    }

    public static function label(mixed $value, string $blankLabel = 'Semua Prodi'): string
    {
        return implode(', ', self::names($value, $blankLabel));
    }

    /**
     * @return array<int, string>
     */
    public static function invalidIds(mixed $value): array
    {
        $ids = self::ids($value);

        if ($ids === []) {
            return [];
        }

        $validIds = array_keys(app(StudyProgramRepository::class)->nameMap());

        return array_values(array_diff($ids, $validIds));
    }
}
