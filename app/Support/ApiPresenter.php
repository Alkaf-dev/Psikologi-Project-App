<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Repositories\ScheduleRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\StudyProgramRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

final class ApiPresenter
{
    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function base(array $record): array
    {
        unset($record['_row'], $record['deleted_at']);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    public static function user(array $user): array
    {
        $data = self::base($user);
        $role = UserRole::tryFromLegacy($data['role'] ?? null);
        $program = app(StudyProgramRepository::class)->find((string) ($data['prodi_id'] ?? ''));

        unset($data['password_hash'], $data['remember_token']);

        $data['role'] = $role?->value ?? (string) ($data['role'] ?? '');
        $data['role_label'] = $role?->label();
        $data['active'] = self::boolean($data['active'] ?? false);
        $data['prodi_name'] = (string) ($program['name'] ?? '');
        $data['cohort'] = (string) ($data['cohort'] ?? '');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $program
     * @return array<string, mixed>
     */
    public static function program(array $program): array
    {
        $data = self::base($program);
        $data['active'] = self::boolean($data['active'] ?? false);
        $data['label'] = trim(($data['degree'] ?? '').' '.($data['name'] ?? ''));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    public static function course(array $course): array
    {
        $data = self::base($course);
        $programIds = ProdiAssignments::ids($data['prodi_ids'] ?? '');
        $data['prodi_ids'] = $programIds;
        $data['prodi_names'] = ProdiAssignments::names($programIds);
        $data['credits'] = (int) ($data['credits'] ?? 0);
        $data['meeting_link'] = self::nullableString($data['meeting_link'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    public static function schedule(array $schedule): array
    {
        $data = self::base($schedule);
        $mode = ScheduleRepository::normalizeMode($data['mode'] ?? null);
        $platform = $mode === 'online' ? ScheduleRepository::normalizePlatform($data['platform'] ?? null) : '';

        $data['mode'] = $mode;
        $data['mode_label'] = ScheduleRepository::MODES[$mode];
        $data['platform'] = $platform !== '' ? $platform : null;
        $data['platform_label'] = $platform !== '' ? ScheduleRepository::PLATFORMS[$platform] : null;
        $data['meeting_link'] = $mode === 'online' ? self::nullableString($data['meeting_link'] ?? null) : null;
        $data['room'] = $mode === 'offline' ? self::nullableString($data['room'] ?? null) : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  Collection<int, array<string, mixed>>|null  $members
     * @return array<string, mixed>
     */
    public static function group(array $group, ?Collection $members = null): array
    {
        $data = self::base($group);
        $data['members_count'] = $members !== null
            ? $members->count()
            : (int) ($data['members_count'] ?? 0);

        if ($members !== null) {
            $data['member_ids'] = $members->pluck('user_id')->filter()->values()->all();
            $data['members'] = $members->map(fn (array $member): array => [
                'id' => (string) ($member['id'] ?? ''),
                'user_id' => (string) ($member['user_id'] ?? ''),
                'name' => (string) ($member['user_name'] ?? ''),
                'npm' => (string) ($member['user_npm'] ?? ''),
                'role' => (string) ($member['role'] ?? 'anggota'),
            ])->values()->all();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    public static function task(array $task): array
    {
        $data = self::base($task);
        $data['group_ids'] = TaskGroups::ids($data['group_ids'] ?? '');
        $data['status'] = TaskStatus::normalize($data['status'] ?? '');
        $data['status_label'] = TaskStatus::label($data['status']);
        $data['type'] = (string) ($data['type'] ?? 'individu');
        $data['type_label'] = $data['type'] === 'kelompok' ? 'Kelompok' : 'Individu';
        $data['is_overdue'] = self::isPast($data['deadline'] ?? null);
        $data['deadline_note'] = self::nullableString($data['deadline_note'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $submission
     * @return array<string, mixed>
     */
    public static function submission(array $submission): array
    {
        $data = self::base($submission);
        $data['status'] = (string) ($data['status'] ?? 'submitted');
        $data['is_late'] = self::boolean($data['is_late'] ?? false);
        $data['notes'] = self::nullableString($data['notes'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $announcement
     * @return array<string, mixed>
     */
    public static function announcement(array $announcement): array
    {
        $data = self::base($announcement);
        $data['prodi_ids'] = ProdiAssignments::ids($data['prodi_ids'] ?? '');
        $data['audience'] = (string) ($data['audience'] ?? 'all');
        $data['published'] = trim((string) ($data['published_at'] ?? '')) !== '';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public static function token(array $token): array
    {
        $data = self::base($token);
        unset($data['token_hash']);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public static function settings(): array
    {
        return app(SettingsRepository::class)->allKeyed();
    }

    public static function boolean(mixed $value): bool
    {
        return in_array(
            mb_strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'ya', 'on'],
            true,
        );
    }

    public static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function isPast(mixed $value): bool
    {
        if (blank($value)) {
            return false;
        }

        try {
            return CarbonImmutable::parse((string) $value)->isPast();
        } catch (Throwable) {
            return false;
        }
    }
}
