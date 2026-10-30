<?php

namespace App\Repositories;

use App\Support\ProdiAssignments;
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

        $studentProdiId = (string) ($student['prodi_id'] ?? '');
        $prodi = app(StudyProgramRepository::class)->find($studentProdiId);
        $prodiCode = strtolower(trim((string) ($prodi['code'] ?? '')));
        $prodiName = strtolower(trim((string) ($prodi['name'] ?? '')));

        $isPsi = $prodiCode === 'psi'
            || str_contains($prodiName, 'psikologi')
            || $studentProdiId === '01M60CY00TAFA0HV21NX7JFNDF';

        $studentClass = trim((string) ($student['class_name'] ?? ''));

        return $this->all()
            ->filter(function (array $schedule) use ($courseIds, $isPsi, $studentClass, $prodiCode): bool {
                $scheduleCourseId = (string) ($schedule['course_id'] ?? '');
                if (! in_array($scheduleCourseId, $courseIds, true)) {
                    return false;
                }

                // Untuk mahasiswa PSI, semua jadwal dari mata kuliah yang tersedia dapat dilihat
                if ($isPsi) {
                    return true;
                }

                $scheduleClass = trim((string) ($schedule['class_name'] ?? ''));
                if ($scheduleClass === '') {
                    return true;
                }

                return $this->matchesStudentClass($scheduleClass, $studentClass, $prodiCode);
            })
            ->sortBy(fn (array $schedule): array => [
                self::DAYS[(string) ($schedule['day'] ?? '')] ?? 99,
                (string) ($schedule['start_time'] ?? ''),
            ])
            ->values();
    }

    protected function matchesStudentClass(string $scheduleClass, string $studentClass, string $prodiCode): bool
    {
        $scheduleLower = strtolower($scheduleClass);
        $studentLower = strtolower($studentClass);

        // Check if student specifies rombel (1a, 1b, 1c)
        $studentRombel = null;
        foreach (['1a', '1b', '1c'] as $r) {
            if (preg_match('/\b'.$r.'\b/i', $studentLower)) {
                $studentRombel = $r;
                break;
            }
        }

        // Check if schedule specifies rombels
        $scheduleRombels = [];
        foreach (['1a', '1b', '1c'] as $r) {
            if (preg_match('/\b'.$r.'\b/i', $scheduleLower)) {
                $scheduleRombels[] = $r;
            }
        }

        // If both student and schedule have rombels, they must match
        if ($studentRombel !== null && ! empty($scheduleRombels)) {
            if (! in_array($studentRombel, $scheduleRombels, true)) {
                return false;
            }
        }

        // Check prodi code in schedule class
        if ($prodiCode !== '' && str_contains($scheduleLower, $prodiCode)) {
            return true;
        }

        // Direct containment
        if ($studentClass !== '' && (str_contains($scheduleLower, $studentLower) || str_contains($studentLower, $scheduleLower))) {
            return true;
        }

        return true;
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
                $schedule['prodi_names'] = ProdiAssignments::label($course['prodi_ids'] ?? '', 'Belum diatur');
                $schedule['prodi_ids'] = ProdiAssignments::ids($course['prodi_ids'] ?? '');

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
