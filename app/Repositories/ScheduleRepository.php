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
}
