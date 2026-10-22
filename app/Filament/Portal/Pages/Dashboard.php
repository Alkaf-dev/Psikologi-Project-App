<?php

namespace App\Filament\Portal\Pages;

use App\Repositories\AnnouncementRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\StudyProgramRepository;
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

        if (! $student) {
            return 'Selamat datang di Psikologi Connect.';
        }

        $prodiName = app(StudyProgramRepository::class)->nameById((string) ($student['prodi_id'] ?? ''));

        return 'Selamat datang kembali, '.$student['name'].($prodiName !== '' ? ' · '.$prodiName : '').'.';
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
                'prodi_name' => '',
                'dashboard_mascot' => null,
            ];
        }

        $prodiName = app(StudyProgramRepository::class)->nameById((string) ($student['prodi_id'] ?? ''));

        $studentGroupIds = app(GroupMemberRepository::class)->groupIdsForUser($userId);
        $submissions = app(SubmissionRepository::class);

        $tasks = app(TaskRepository::class)
            ->withDetails(app(TaskRepository::class)->forStudent($student))
            ->map(function (array $task) use ($submissions, $userId, $studentGroupIds): array {
                $submission = $submissions->forTaskForStudent($task, $userId, $studentGroupIds);
                $task['submission_status'] = $submission['status'] ?? null;
                $task['submission_grade'] = $submission['grade'] ?? null;

                return $task;
            });

        $pendingTaskCount = $tasks
            ->filter(fn (array $task): bool => blank($task['submission_status']))
            ->count();

        $schedules = app(ScheduleRepository::class)->withCourse(
            app(ScheduleRepository::class)->forStudent($student),
        )->take(5);

        return [
            'student' => $student,
            'tasks' => $tasks,
            'submissions' => $submissions,
            'groups' => app(GroupRepository::class)->withDetails(
                app(GroupRepository::class)->forStudent($student),
            ),
            'announcements' => app(AnnouncementRepository::class)->withAuthor(
                app(AnnouncementRepository::class)
                    ->published('mahasiswa', (string) ($student['prodi_id'] ?? ''))
                    ->take(3),
            ),
            'schedules' => $schedules,
            'settings' => app(SettingsRepository::class)->allKeyed(),
            'prodi_name' => $prodiName,
            'dashboard_mascot' => $pendingTaskCount > 3 ? 'minum' : null,
        ];
    }
}
