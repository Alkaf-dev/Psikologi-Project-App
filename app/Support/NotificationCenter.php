<?php

namespace App\Support;

use App\Filament\Pages\JadwalPage as AdminJadwalPage;
use App\Filament\Pages\NotifikasiPage as AdminNotifikasiPage;
use App\Filament\Pages\TugasPage as AdminTugasPage;
use App\Filament\Portal\Pages\JadwalPage as PortalJadwalPage;
use App\Filament\Portal\Pages\NotifikasiPage as PortalNotifikasiPage;
use App\Filament\Portal\Pages\TugasPage as PortalTugasPage;
use App\Repositories\GroupMemberRepository;
use App\Repositories\NotificationReadRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class NotificationCenter
{
    public function __construct(
        protected TaskRepository $tasks,
        protected ScheduleRepository $schedules,
        protected SubmissionRepository $submissions,
        protected GroupMemberRepository $members,
        protected NotificationReadRepository $reads,
        protected UserRepository $users,
    ) {}

    /**
     * @param  array<string, mixed>  $user
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(array $user, ?CarbonInterface $now = null): Collection
    {
        $userId = trim((string) ($user['id'] ?? ''));

        if ($userId === '') {
            return collect();
        }

        $now = $now !== null ? Carbon::instance($now) : now();
        $reads = $this->reads->forUser($userId);

        return $this->taskNotifications($user, $now)
            ->concat($this->scheduleNotifications($user, $now))
            ->map(function (array $notification) use ($reads): array {
                $read = $reads->get($notification['key']);

                $notification['read'] = $read !== null;
                $notification['read_at'] = $read['read_at'] ?? null;

                return $notification;
            })
            ->sortBy('sort_at')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function unreadCount(array $user, ?CarbonInterface $now = null): int
    {
        return $this->forUser($user, $now)
            ->where('read', false)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function markAsRead(array $user, string $key): void
    {
        $this->reads->markAsRead((string) ($user['id'] ?? ''), $key);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function markManyAsRead(array $user, array $keys): void
    {
        $this->reads->markManyAsRead((string) ($user['id'] ?? ''), $keys);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function markAllAsRead(array $user, ?CarbonInterface $now = null): void
    {
        $this->markManyAsRead(
            $user,
            $this->forUser($user, $now)->pluck('key')->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function panelId(array $user): string
    {
        return $this->users->isStudent($user) ? 'portal' : 'admin';
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function notificationPageUrl(array $user): string
    {
        return $this->panelId($user) === 'portal'
            ? PortalNotifikasiPage::getUrl(panel: 'portal')
            : AdminNotifikasiPage::getUrl(panel: 'admin');
    }

    /**
     * @param  array<string, mixed>  $user
     * @return Collection<int, array<string, mixed>>
     */
    protected function taskNotifications(array $user, Carbon $now): Collection
    {
        $isStudent = $this->users->isStudent($user);
        $tasks = $isStudent
            ? $this->tasks->forStudent($user)
            : $this->tasks->all();

        $tasks = $this->tasks->withDetails($tasks);
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $studentGroupIds = $isStudent
            ? $this->members->groupIdsForUser((string) $user['id'])
            : [];
        $submissionCounts = $isStudent ? [] : $this->submissions->countsByTask();

        return $tasks
            ->filter(function (array $task) use ($startOfWeek): bool {
                if (TaskStatus::normalize($task['status'] ?? '') !== TaskStatus::ACTIVE) {
                    return false;
                }

                $deadline = $this->parseDateTime($task['deadline'] ?? null);

                return $deadline !== null && $deadline->greaterThanOrEqualTo($startOfWeek);
            })
            ->map(function (array $task) use ($isStudent, $studentGroupIds, $submissionCounts, $now, $user): array {
                $deadline = $this->parseDateTime($task['deadline']);

                if ($deadline === null) {
                    return [];
                }

                $deadlineNote = trim((string) ($task['deadline_note'] ?? ''));
                $isOverdue = $deadline->isPast();
                $isDueSoon = ! $isOverdue && $deadline->lessThanOrEqualTo($now->copy()->addDays(3));

                [$badge, $color] = match (true) {
                    $isOverdue => ['Lewat deadline', 'danger'],
                    $isDueSoon => ['Deadline dekat', 'warning'],
                    default => ['Tugas aktif', 'info'],
                };

                $submission = $isStudent
                    ? $this->submissions->forTaskForStudent($task, (string) $user['id'], $studentGroupIds)
                    : null;

                $submissionLabel = match ($submission['status'] ?? null) {
                    'graded' => 'Sudah dinilai',
                    'late' => 'Dikumpulkan terlambat',
                    'submitted' => 'Sudah dikumpulkan',
                    default => 'Belum dikumpulkan',
                };

                $deadlineDescription = $deadline->translatedFormat('d M Y H:i')
                    .($deadlineNote !== '' ? ' • '.$deadlineNote : '');

                return [
                    'key' => 'task:'.(string) $task['id'].':'.$deadline->getTimestamp(),
                    'type' => 'task',
                    'type_label' => 'Tugas',
                    'group' => 'tasks',
                    'title' => (string) $task['title'],
                    'description' => (string) $task['course_name']."\nDeadline ".$deadlineDescription,
                    'meta' => $isStudent
                        ? 'Pengumpulan: '.$submissionLabel
                        : ($submissionCounts[(string) $task['id']] ?? 0).' pengumpulan',
                    'badge' => $badge,
                    'time_label' => $this->relativeLabel($deadline, $now),
                    'color' => $color,
                    'icon' => 'heroicon-o-clipboard-document-list',
                    'url' => $this->taskPageUrl($user),
                    'action_label' => 'Buka tugas',
                    'external' => false,
                    'sort_at' => $deadline->getTimestamp(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $user
     * @return Collection<int, array<string, mixed>>
     */
    protected function scheduleNotifications(array $user, Carbon $now): Collection
    {
        $isStudent = $this->users->isStudent($user);
        $schedules = $isStudent
            ? $this->schedules->forStudent($user)
            : $this->schedules->all();

        $schedules = $this->schedules->withCourse($schedules);
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $todayName = $this->dayName($today);
        $tomorrowName = $this->dayName($tomorrow);

        return $schedules
            ->map(function (array $schedule) use ($today, $tomorrow, $todayName, $tomorrowName, $user): array {
                $scheduleDay = trim((string) ($schedule['day'] ?? ''));

                [$date, $dayLabel, $group, $color] = match ($scheduleDay) {
                    $todayName => [$today, 'Hari Ini', 'today', 'warning'],
                    $tomorrowName => [$tomorrow, 'Besok', 'tomorrow', 'info'],
                    default => [null, '', '', ''],
                };

                if ($date === null) {
                    return [];
                }

                $startTime = $this->formatTime($schedule['start_time'] ?? null);
                $endTime = $this->formatTime($schedule['end_time'] ?? null);
                $start = $this->parseDateTime($date->toDateString().' '.($startTime !== '' ? $startTime : '00:00'));

                if ($start === null) {
                    return [];
                }

                $timeRange = $startTime !== '' && $endTime !== ''
                    ? $startTime.'–'.$endTime
                    : ($startTime ?: $endTime);
                $class = trim((string) ($schedule['class_name'] ?? ''));
                $lecturer = trim((string) ($schedule['course_lecturer'] ?? ''));
                $meetingLink = trim((string) ($schedule['meeting_link'] ?? ''));
                $isExternal = ($schedule['mode'] ?? 'offline') === 'online' && $meetingLink !== '';
                $meta = array_values(array_filter([
                    $class !== '' ? 'Kelas '.$class : '',
                    $lecturer,
                ]));

                return [
                    'key' => 'schedule:'.(string) $schedule['id'].':'.$date->toDateString(),
                    'type' => 'schedule',
                    'type_label' => 'Jadwal',
                    'group' => $group,
                    'title' => 'Jadwal '.mb_strtolower($dayLabel).': '.(string) $schedule['course_name'],
                    'description' => trim($timeRange.' • '.($schedule['location_label'] ?? '-')),
                    'meta' => implode(' • ', $meta),
                    'badge' => $dayLabel,
                    'time_label' => $dayLabel,
                    'color' => $color,
                    'icon' => 'heroicon-o-calendar-days',
                    'url' => $isExternal ? $meetingLink : $this->schedulePageUrl($user),
                    'action_label' => $isExternal ? 'Gabung rapat' : 'Lihat jadwal',
                    'external' => $isExternal,
                    'sort_at' => $start->getTimestamp(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function taskPageUrl(array $user): string
    {
        return $this->users->isStudent($user)
            ? PortalTugasPage::getUrl(panel: 'portal')
            : AdminTugasPage::getUrl(panel: 'admin');
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function schedulePageUrl(array $user): string
    {
        return $this->users->isStudent($user)
            ? PortalJadwalPage::getUrl(panel: 'portal')
            : AdminJadwalPage::getUrl(panel: 'admin');
    }

    protected function dayName(Carbon $date): string
    {
        $day = array_search($date->dayOfWeekIso, ScheduleRepository::DAYS, true);

        return $day === false ? '' : (string) $day;
    }

    protected function formatTime(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : mb_substr($value, 0, 5);
    }

    protected function parseDateTime(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function relativeLabel(Carbon $target, Carbon $now): string
    {
        if ($target->isPast()) {
            return 'Lewat deadline';
        }

        if ($target->isSameDay($now)) {
            return 'Hari ini';
        }

        if ($target->isSameDay($now->copy()->addDay())) {
            return 'Besok';
        }

        $days = (int) $now->copy()->startOfDay()->diffInDays($target->copy()->startOfDay());

        return max(1, $days).' hari lagi';
    }
}
