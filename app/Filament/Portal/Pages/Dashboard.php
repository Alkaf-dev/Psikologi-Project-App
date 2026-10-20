<?php

namespace App\Filament\Portal\Pages;

use App\Repositories\AnnouncementRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.portal.pages.dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getHeading(): string|Htmlable|null
    {
        return 'Portal Mahasiswa';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $student = app(UserRepository::class)->find((string) auth()->id());

        return $student
            ? 'Selamat datang kembali, '.$student['name'].'.'
            : 'Selamat datang di Psikologi Connect.';
    }

    protected function getViewData(): array
    {
        $userId = (string) auth()->id();
        $student = app(UserRepository::class)->find($userId);

        if (! $student) {
            return [
                'student' => null,
                'tasks' => collect(),
                'submissions' => collect(),
                'groups' => collect(),
                'announcements' => collect(),
                'schedules' => collect(),
                'settings' => [],
            ];
        }

        $submissions = app(SubmissionRepository::class)
            ->forStudent($userId)
            ->keyBy('task_id');

        $tasks = app(TaskRepository::class)
            ->withDetails(app(TaskRepository::class)->forStudent($student))
            ->map(function (array $task) use ($submissions): array {
                $submission = $submissions->get((string) $task['id']);
                $task['submission_status'] = $submission['status'] ?? null;
                $task['submission_grade'] = $submission['grade'] ?? null;

                return $task;
            });

        return [
            'student' => $student,
            'tasks' => $tasks,
            'submissions' => $submissions,
            'groups' => app(GroupRepository::class)->withDetails(
                app(GroupRepository::class)->forStudent($userId),
            ),
            'announcements' => app(AnnouncementRepository::class)->withAuthor(
                app(AnnouncementRepository::class)->published('mahasiswa')->take(3),
            ),
            'schedules' => app(ScheduleRepository::class)->withCourse(
                app(ScheduleRepository::class)->forClass((string) ($student['class_name'] ?? '')),
            )->take(5),
            'settings' => app(SettingsRepository::class)->allKeyed(),
        ];
    }
}
