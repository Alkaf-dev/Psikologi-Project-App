<?php

namespace App\Filament\Pages;

use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.admin-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getHeading(): string|Htmlable|null
    {
        return 'Dashboard Admin';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Ringkasan aktivitas akademik Psikologi Connect.';
    }

    protected function getViewData(): array
    {
        $users = app(UserRepository::class);
        $tasks = app(TaskRepository::class);
        $submissions = app(SubmissionRepository::class);
        $announcements = app(AnnouncementRepository::class);

        return [
            'studentCount' => $users->students()->count(),
            'courseCount' => app(CourseRepository::class)->count(),
            'groupCount' => app(GroupRepository::class)->count(),
            'taskCount' => $tasks->count(),
            'submissionCount' => $submissions->count(),
            'pendingCount' => $submissions->all()->filter(
                fn (array $submission): bool => ($submission['status'] ?? '') === 'submitted',
            )->count(),
            'announcementCount' => $announcements->count(),
            'latestTasks' => $tasks->withDetails()->sortByDesc('created_at')->take(5)->values(),
            'latestSubmissions' => $submissions->withDetails()->sortByDesc('submitted_at')->take(5)->values(),
            'dataDriver' => config('sheets.driver') === 'google' ? 'Google Sheets' : 'Demo lokal',
        ];
    }
}
