<?php

namespace App\Filament\Portal\Pages;

use App\Repositories\AnnouncementRepository;
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
            ];
        }

        $prodiName = app(StudyProgramRepository::class)->nameById((string) ($student['prodi_id'] ?? ''));

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
                app(GroupRepository::class)->forStudent($student),
            ),
            'announcements' => app(AnnouncementRepository::class)->withAuthor(
                app(AnnouncementRepository::class)
                    ->published('mahasiswa', (string) ($student['prodi_id'] ?? ''))
                    ->take(3),
            ),
            'schedules' => app(ScheduleRepository::class)->withCourse(
                app(ScheduleRepository::class)->forStudent($student),
            )->take(5),
            'settings' => app(SettingsRepository::class)->allKeyed(),
            'prodi_name' => $prodiName,
        ];
    }
}
