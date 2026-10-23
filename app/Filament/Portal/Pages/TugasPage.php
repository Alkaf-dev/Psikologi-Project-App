<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\ExternalUrl;
use App\Support\TaskGroups;
use App\Support\TaskStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TugasPage extends BaseSheetPage
{
    protected string $view = 'filament.portal.pages.tugas';

    protected static ?string $navigationLabel = 'Materi dan Tugas';

    protected static ?string $title = 'Materi dan Tugas';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 2;

    public ?string $selectedCourseId = null;

    protected ?Collection $cachedAccessibleTasks = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $cachedCourseFilters = null;

    protected array $searchableColumns = [
        'meeting_number', 'title', 'description', 'course_name', 'course_code',
        'portal_group_name', 'type_label', 'status_label', 'submission_label',
    ];

    public function showMascot(): bool
    {
        return true;
    }

    public function mascotContext(): string
    {
        return 'portal-tasks';
    }

    public function pageMascot(): ?string
    {
        return 'membaca';
    }

    public function selectCourse(string $courseId): void
    {
        $courseId = trim($courseId);

        if (! in_array($courseId, $this->courseFilterIds(), true)) {
            return;
        }

        $this->selectedCourseId = $courseId;

        $this->resetTable();
    }

    public function selectAllCourses(): void
    {
        $this->selectedCourseId = null;

        $this->resetTable();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'courseFilters' => $this->courseFilters(),
            'selectedCourseId' => $this->selectedCourseFilterId(),
            'totalTasks' => $this->accessibleTasks()->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function accessibleTasks(): Collection
    {
        if ($this->cachedAccessibleTasks !== null) {
            return $this->cachedAccessibleTasks;
        }

        $student = app(UserRepository::class)->find((string) auth()->id());

        if (! $student) {
            return $this->cachedAccessibleTasks = collect();
        }

        $tasks = app(TaskRepository::class);

        return $this->cachedAccessibleTasks = $tasks->withDetails($tasks->forStudent($student));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function courseFilters(): array
    {
        if ($this->cachedCourseFilters !== null) {
            return $this->cachedCourseFilters;
        }

        $courses = app(CourseRepository::class)
            ->all()
            ->keyBy(fn (array $course): string => (string) ($course['id'] ?? ''));

        return $this->cachedCourseFilters = $this->accessibleTasks()
            ->groupBy(fn (array $task): string => (string) ($task['course_id'] ?? ''))
            ->map(function (Collection $tasks, string $courseId) use ($courses): ?array {
                $course = $courses->get($courseId);

                if (! $course) {
                    return null;
                }

                return [
                    'id' => $courseId,
                    'name' => (string) ($course['name'] ?? 'Mata kuliah'),
                    'code' => (string) ($course['code'] ?? ''),
                    'tasks_count' => $tasks->count(),
                    'materials_count' => $tasks
                        ->filter(fn (array $task): bool => ExternalUrl::http($task['material_url'] ?? null) !== null)
                        ->count(),
                ];
            })
            ->filter()
            ->sortBy(fn (array $filter): string => mb_strtolower((string) $filter['name']), SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function courseFilterIds(): array
    {
        return array_column($this->courseFilters(), 'id');
    }

    protected function selectedCourseFilterId(): ?string
    {
        $courseId = trim((string) $this->selectedCourseId);

        return $courseId !== '' && in_array($courseId, $this->courseFilterIds(), true)
            ? $courseId
            : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'materi-tugas-table sheet-responsive-table task-table-compact'])
            ->records(function (
                ?string $search,
                ?string $sortColumn,
                ?string $sortDirection,
                int|string $page,
                int|string $recordsPerPage,
            ) {
                $userId = (string) auth()->id();
                $studentGroupIds = app(GroupMemberRepository::class)->groupIdsForUser($userId);
                $submissions = app(SubmissionRepository::class);
                $users = app(UserRepository::class);
                $groups = app(GroupRepository::class);
                $selectedCourseId = $this->selectedCourseFilterId();

                $records = $this->accessibleTasks();

                if ($selectedCourseId !== null) {
                    $records = $records
                        ->filter(fn (array $task): bool => (string) ($task['course_id'] ?? '') === $selectedCourseId)
                        ->values();
                }

                $records = $records->map(function (array $task) use (
                    $groups,
                    $submissions,
                    $users,
                    $userId,
                    $studentGroupIds,
                ): array {
                    $isGroupTask = ($task['type'] ?? 'individu') === 'kelompok';
                    $submission = $submissions->forTaskForStudent($task, $userId, $studentGroupIds);
                    $submissionGroupId = $isGroupTask
                        ? (string) (TaskGroups::matchingGroupId($task, $studentGroupIds) ?? '')
                        : '';
                    $status = TaskStatus::normalize($task['status'] ?? '');
                    $deadline = filled($task['deadline'] ?? null)
                        ? Carbon::parse((string) $task['deadline'])
                        : null;
                    $isRepresentative = $submission !== null
                        && (! $isGroupTask || (string) ($submission['user_id'] ?? '') === $userId);
                    $portalGroupName = null;

                    if ($isGroupTask) {
                        $matchedGroupId = TaskGroups::matchingGroupId($task, $studentGroupIds);

                        if ($matchedGroupId !== null) {
                            $portalGroupName = trim((string) ($groups->find($matchedGroupId)['name'] ?? ''));
                        } elseif (TaskGroups::assignedIds($task) === []) {
                            $portalGroupName = 'Semua mahasiswa';
                        }

                        $portalGroupName = $portalGroupName !== '' ? $portalGroupName : null;
                    }

                    $submittedAtLabel = filled($submission['submitted_at'] ?? null)
                        ? Carbon::parse((string) $submission['submitted_at'])->translatedFormat('d M Y H:i')
                        : null;
                    $submissionStatus = $submission['status'] ?? null;

                    $task['material_link'] = ExternalUrl::http($task['material_url'] ?? null);
                    $task['portal_group_name'] = $portalGroupName;
                    $task['submission_id'] = $submission['id'] ?? null;
                    $task['submission_status'] = $submissionStatus;
                    $task['submission_link'] = $submission['link'] ?? null;
                    $task['submission_url'] = ExternalUrl::http($submission['link'] ?? null);
                    $task['submission_notes'] = $submission['notes'] ?? null;
                    $task['submission_grade'] = $submission['grade'] ?? null;
                    $task['submission_feedback'] = $submission['feedback'] ?? null;
                    $task['submission_group_id'] = $submissionGroupId;
                    $task['submission_by_label'] = $isGroupTask && $submission !== null
                        ? 'oleh '.$users->nameById((string) ($submission['user_id'] ?? ''))
                        : null;
                    $task['submission_label'] = match ($submissionStatus) {
                        'graded', 'submitted' => 'Sudah dikumpulkan',
                        'late' => 'Terlambat dikumpulkan',
                        default => 'Belum dikumpulkan',
                    };
                    $task['submission_description'] = match ($submissionStatus) {
                        'graded' => collect(['Dinilai', $submittedAtLabel])->filter()->implode(' • '),
                        'submitted', 'late' => $submittedAtLabel,
                        default => null,
                    };
                    $task['is_representative'] = $isRepresentative;
                    $task['can_submit'] = $submission === null
                        && $status === TaskStatus::ACTIVE
                        && (! $isGroupTask || $submissionGroupId !== '');
                    $task['can_update'] = $submission !== null
                        && $status === TaskStatus::ACTIVE
                        && (! $isGroupTask || $isRepresentative)
                        && (! $isGroupTask || $deadline === null || now()->lessThanOrEqualTo($deadline));

                    return $task;
                });

                return $this->paginateRecords(
                    $records,
                    $search,
                    $sortColumn,
                    $sortDirection,
                    $page,
                    $recordsPerPage,
                );
            })
            ->columns([
                $this->spreadsheetColumn(
                    TextColumn::make('meeting_number')
                        ->label('Pertemuan')
                        ->badge()
                        ->placeholder('-')
                        ->sortable()
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('8.5%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('title')
                        ->label('Tugas')
                        ->weight('semibold')
                        ->color('primary')
                        ->description(fn (array $record): string => (string) $record['course_name'])
                        ->action($this->detailAction())
                        ->tooltip(fn (array $record): string => (string) $record['title'])
                        ->searchable()
                        ->width('16%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('material_url')
                        ->label('Materi')
                        ->formatStateUsing(fn (array $record): string => filled($record['material_link'] ?? null) ? 'Materi' : '-')
                        ->url(fn (array $record): ?string => $record['material_link'] ?? null)
                        ->openUrlInNewTab()
                        ->color(fn (array $record): ?string => filled($record['material_link'] ?? null) ? 'primary' : null)
                        ->tooltip(fn (array $record): ?string => filled($record['material_link'] ?? null) ? 'Buka materi di tab baru' : null)
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('6.5%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('type_label')
                        ->label('Jenis')
                        ->badge()
                        ->color(fn (array $record): string => ($record['type'] ?? 'individu') === 'kelompok' ? 'info' : 'gray')
                        ->tooltip(fn (array $record): string => (string) ($record['type_label'] ?? ''))
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('6.5%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('portal_group_name')
                        ->label('Kelompok')
                        ->placeholder('-')
                        ->tooltip(function (array $record): ?string {
                            $group = trim((string) ($record['portal_group_name'] ?? ''));

                            return $group !== '' ? $group : null;
                        })
                        ->wrapHeader()
                        ->searchable()
                        ->width('9%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('start_at')
                        ->label('Mulai')
                        ->formatStateUsing(fn (?string $state): string => $this->formatDateTime($state))
                        ->tooltip(fn (array $record): ?string => filled($record['start_at'] ?? null)
                            ? $this->formatDateTime((string) $record['start_at'])
                            : null)
                        ->placeholder('-')
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('8%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('deadline')
                        ->label('Deadline')
                        ->description(fn (array $record): string => (string) ($record['deadline_label'] ?? ''))
                        ->formatStateUsing(fn (?string $state): string => $this->formatDateTime($state))
                        ->tooltip(function (array $record): ?string {
                            if (blank($record['deadline'] ?? null)) {
                                return null;
                            }

                            return collect([
                                $this->formatDateTime((string) $record['deadline']),
                                $record['deadline_label'] ?? null,
                            ])->filter()->implode(' • ');
                        })
                        ->sortable()
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('9%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('status_label')
                        ->label('Status')
                        ->badge()
                        ->color(fn (array $record): string => TaskStatus::color($record['status'] ?? ''))
                        ->tooltip(fn (array $record): string => (string) ($record['status_label'] ?? ''))
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('7.5%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('submission_label')
                        ->label('Dikumpulkan')
                        ->badge()
                        ->description(fn (array $record): ?string => $record['submission_description'] ?? null)
                        ->color(fn (array $record): string => match ($record['submission_status']) {
                            'graded' => 'success',
                            'late' => 'danger',
                            'submitted' => 'info',
                            default => 'warning',
                        })
                        ->url(fn (array $record): ?string => $record['submission_url'] ?? null)
                        ->openUrlInNewTab()
                        ->tooltip(function (array $record): ?string {
                            $parts = collect([
                                $record['submission_label'] ?? null,
                                $record['submission_description'] ?? null,
                            ])->filter();

                            if (filled($record['submission_url'] ?? null)) {
                                $parts->push('Buka tautan pengumpulan di tab baru');
                            }

                            return $parts->isNotEmpty() ? $parts->implode(' • ') : null;
                        })
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('9%'),
                ),
            ])
            ->searchPlaceholder('Cari tugas atau mata kuliah...')
            ->recordActions([
                $this->detailAction(),
                $this->submitAction(),
                $this->feedbackAction(),
            ])
            ->recordAction('detail')
            ->defaultSort('deadline')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada materi atau tugas')
            ->emptyStateDescription('Materi dan tugas dari mata kuliah Anda akan tampil di sini.');
    }

    protected function detailAction(): Action
    {
        return Action::make('detail')
            ->label('Detail')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (array $record): string => (string) $record['title'])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalWidth('3xl')
            ->modalContent(fn (array $record) => view('filament.portal.partials.task-detail', [
                'task' => $record,
            ]));
    }

    protected function submitAction(): Action
    {
        return Action::make('submit')
            ->label(fn (array $record): string => $record['submission_id'] ? 'Perbarui' : 'Kumpulkan')
            ->icon(Heroicon::ArrowUpTray)
            ->modalHeading(fn (array $record): string => 'Kumpulkan: '.$record['title'])
            ->modalSubmitActionLabel('Kirim')
            ->modalWidth('2xl')
            ->visible(fn (array $record): bool => (bool) ($record['can_submit'] ?? false)
                || (bool) ($record['can_update'] ?? false))
            ->fillForm(fn (array $record): array => [
                'link' => $record['submission_link'] ?? '',
                'notes' => $record['submission_notes'] ?? '',
            ])
            ->schema([
                TextInput::make('link')
                    ->label('Tautan tugas')
                    ->url()
                    ->required()
                    ->maxLength(500)
                    ->helperText('Tempel tautan Google Drive, Google Docs, atau tautan lain yang dapat diakses dosen.'),
                Textarea::make('notes')
                    ->label('Catatan (opsional)')
                    ->rows(3),
            ])
            ->action(function (array $data, array $record): void {
                $userId = (string) auth()->id();
                $taskId = (string) $record['id'];
                $isGroupTask = ($record['type'] ?? 'individu') === 'kelompok';
                $groupId = (string) ($record['submission_group_id'] ?? '');
                $repository = app(SubmissionRepository::class);

                if (TaskStatus::normalize($record['status'] ?? '') !== TaskStatus::ACTIVE) {
                    Notification::make()
                        ->title('Tugas tidak dapat dikumpulkan')
                        ->body('Tugas ini sudah selesai atau belum dipublikasikan.')
                        ->danger()
                        ->send();

                    return;
                }

                $deadline = filled($record['deadline'] ?? null)
                    ? Carbon::parse((string) $record['deadline'])
                    : null;
                $existing = $isGroupTask
                    ? $repository->forTaskAndGroup($taskId, $groupId)
                    : $repository->forTaskAndUser($taskId, $userId);

                if ($isGroupTask) {
                    if ($groupId === '') {
                        Notification::make()
                            ->title('Kelompok tidak ditemukan')
                            ->body('Hubungi admin untuk memastikan kelompok Anda ditugaskan pada tugas ini.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($existing !== null && (string) ($existing['user_id'] ?? '') !== $userId) {
                        Notification::make()
                            ->title('Kelompok sudah mengumpulkan')
                            ->body('Hanya perwakilan kelompok yang dapat memperbarui pengumpulan ini.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($existing !== null && $deadline !== null && now()->greaterThan($deadline)) {
                        Notification::make()
                            ->title('Batas pembaruan berakhir')
                            ->body('Pengumpulan kelompok hanya dapat diperbarui sampai deadline. Hubungi admin jika perlu perubahan.')
                            ->warning()
                            ->send();

                        return;
                    }
                }

                $isLate = $deadline !== null && now()->greaterThan($deadline);
                $payload = [
                    'group_id' => $isGroupTask ? $groupId : '',
                    'status' => $isLate ? 'late' : 'submitted',
                    'link' => (string) $data['link'],
                    'notes' => (string) ($data['notes'] ?? ''),
                    'submitted_at' => now()->toIso8601String(),
                ];

                if ($isGroupTask) {
                    $payload['user_id'] = $userId;
                    $repository->upsertForTaskAndGroup($taskId, $groupId, $payload);
                } else {
                    $repository->upsertForTaskAndUser($taskId, $userId, $payload);
                }

                $this->resetTable();

                Notification::make()
                    ->title($isLate ? 'Tugas dikumpulkan (terlambat)' : 'Tugas berhasil dikumpulkan')
                    ->{$isLate ? 'warning' : 'success'}()
                    ->send();

                $this->dispatch(
                    'psychology-mascot:celebrate',
                    state: $isLate ? 'supportive' : 'success',
                    message: $isLate
                        ? 'Tugas tetap terkirim. Terima kasih sudah berusaha!'
                        : 'Tugas berhasil dikumpulkan. Kerja bagus!',
                );
            });
    }

    protected function feedbackAction(): Action
    {
        return Action::make('feedback')
            ->label('Feedback')
            ->icon(Heroicon::ChatBubbleLeftRight)
            ->color('info')
            ->visible(fn (array $record): bool => filled($record['submission_feedback'] ?? null))
            ->modalHeading(fn (array $record): string => 'Feedback: '.$record['title'])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (array $record) => view('filament.portal.partials.feedback', [
                'grade' => $record['submission_grade'] ?? null,
                'feedback' => (string) ($record['submission_feedback'] ?? ''),
            ]));
    }

    protected function formatDateTime(?string $value): string
    {
        return filled($value)
            ? Carbon::parse($value)->translatedFormat('d M Y H:i')
            : '-';
    }
}
