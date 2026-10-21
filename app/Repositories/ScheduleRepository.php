<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

class ScheduleRepository extends BaseSheetRepository
{
    protected string $table = 'schedules';

    public const DAYS = [
        'Senin' => 1,
        'Selasa' => 2,
        'Rabu' => 3,
        'Kamis' => 4,
        'Jumat' => 5,
        'Sabtu' => 6,
        'Minggu' => 7,
    ];

    public const MODES = [
        'offline' => 'Offline',
        'online' => 'Online',
    ];

    public const PLATFORMS = [
        'zoom' => 'Zoom',
        'google_meet' => 'Google Meet',
    ];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forClass(string $className): Collection
    {
        return $this->all()
            ->filter(fn (array $schedule): bool => trim((string) ($schedule['class_name'] ?? '')) === $className)
            ->sortBy(fn (array $schedule): array => [
                self::DAYS[(string) ($schedule['day'] ?? '')] ?? 99,
                (string) ($schedule['start_time'] ?? ''),
            ])
            ->values();
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

        $className = trim((string) ($student['class_name'] ?? ''));

        return $this->all()
            ->filter(fn (array $schedule): bool => in_array((string) ($schedule['course_id'] ?? ''), $courseIds, true)
                && trim((string) ($schedule['class_name'] ?? '')) === $className)
            ->sortBy(fn (array $schedule): array => [
                self::DAYS[(string) ($schedule['day'] ?? '')] ?? 99,
                (string) ($schedule['start_time'] ?? ''),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function withCourse(?Collection $schedules = null): Collection
    {
        $courses = app(CourseRepository::class);

        return ($schedules ?? $this->all())
            ->map(function (array $schedule) use ($courses): array {
                $course = $courses->find((string) ($schedule['course_id'] ?? ''));

                $schedule['course_name'] = $course['name'] ?? '-';
                $schedule['course_code'] = $course['code'] ?? '';
                $schedule['course_lecturer'] = $course['lecturer'] ?? '';

                $mode = self::normalizeMode($schedule['mode'] ?? null);
                $platform = $mode === 'online'
                    ? self::normalizePlatform($schedule['platform'] ?? null)
                    : '';
                $room = trim((string) ($schedule['room'] ?? ''));

                $schedule['mode'] = $mode;
                $schedule['mode_label'] = self::MODES[$mode];
                $schedule['platform'] = $platform;
                $schedule['platform_label'] = $platform !== '' ? self::PLATFORMS[$platform] : '';
                $schedule['meeting_link'] = $mode === 'online'
                    ? trim((string) ($schedule['meeting_link'] ?? ''))
                    : '';
                $schedule['room'] = $room;
                $schedule['location_label'] = $mode === 'online'
                    ? ($schedule['platform_label'] ?: 'Online')
                    : ($room !== '' ? $room : '-');

                return $schedule;
            });
    }

    /**
     * @return array<int, string>
     */
    public static function days(): array
    {
        return array_keys(self::DAYS);
    }

    /**
     * @return array<string, string>
     */
    public static function modes(): array
    {
        return self::MODES;
    }

    /**
     * @return array<string, string>
     */
    public static function platforms(): array
    {
        return self::PLATFORMS;
    }

    public static function normalizeMode(?string $mode): string
    {
        $mode = mb_strtolower(trim((string) $mode));

        return array_key_exists($mode, self::MODES) ? $mode : 'offline';
    }

    public static function normalizePlatform(?string $platform): string
    {
        $platform = str_replace([' ', '-'], '_', mb_strtolower(trim((string) $platform)));

        return array_key_exists($platform, self::PLATFORMS) ? $platform : '';
    }
}
