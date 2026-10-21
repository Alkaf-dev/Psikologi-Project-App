<?php

namespace App\Support;

final class TaskStatus
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    public static function normalize(mixed $status): string
    {
        $status = mb_strtolower(trim((string) $status));

        return in_array($status, [self::DRAFT, self::ACTIVE, self::COMPLETED], true)
            ? $status
            : self::ACTIVE;
    }

    public static function label(mixed $status): string
    {
        return match (self::normalize($status)) {
            self::DRAFT => 'Draf',
            self::COMPLETED => 'Selesai',
            default => 'Aktif',
        };
    }

    public static function color(mixed $status): string
    {
        return match (self::normalize($status)) {
            self::DRAFT => 'gray',
            self::COMPLETED => 'success',
            default => 'info',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::DRAFT => 'Draf',
            self::ACTIVE => 'Aktif',
            self::COMPLETED => 'Selesai',
        ];
    }
}
