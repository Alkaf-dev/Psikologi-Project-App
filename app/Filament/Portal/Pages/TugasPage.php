<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\GroupMemberRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
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

class TugasPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Tugas Saya';

    protected static ?string $title = 'Tugas Saya';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 2;

    protected array $searchableColumns = [
        'meeting_number', 'title', 'description', 'course_name', 'group_name',
        'type_label', 'status_label', 'submission_label',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->records(function (
                ?string $search,
                ?string $sortColumn,
                ?string $sortDirection,
                int|string $page,
                int|string $recordsPerPage,
            ) {
                $userId = (string) auth()->id();
                $student = app(UserRepository::class)->find($userId);
                $studentGroupIds = app(GroupMemberRepository::class)->groupIdsForUser($userId);
                $submissions = app(SubmissionRepository::class);
                $users = app(UserRepository::class);

                $records = app(TaskRepository::class)
                    ->withDetails(app(TaskRepository::class)->forStudent($student ?? []))
                    ->map(function (array $task) use ($submissions, $users, $userId, $studentGroupIds): array {
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

                        $task['submission_id'] = $submission['id'] ?? null;
                        $task['submission_status'] = $submission['status'] ?? null;
                        $task['submission_link'] = $submission['link'] ?? null;
                        $task['submission_notes'] = $submission['notes'] ?? null;
                        $task['submission_grade'] = $submission['grade'] ?? null;
                        $task['submission_feedback'] = $submission['feedback'] ?? null;
                        $task['submission_group_id'] = $submissionGroupId;
                        $task['submission_by_label'] = $isGroupTask && $submission !== null
                            ? 'oleh '.$users->nameById((string) ($submission['user_id'] ?? ''))
                            : null;
                        $task['submission_label'] = match ($task['submission_status']) {
                            'graded' => 'Dinilai',
                            'late' => 'Terlambat',
                            'submitted' => 'Dikumpulkan',
                            default => 'Belum dikumpulkan',
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
                        ->alignCenter(),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('title')
                        ->label('Tugas')
                        ->weight('semibold')
                        ->color('primary')
                        ->description(fn (array $record): string => (string) $record['course_name'])
                        ->action($this->detailAction())
                        ->tooltip('Klik untuk melihat rincian')
                        ->searchable()
                        ->wrap(),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('group_name')
                        ->label('Kelompok')
                        ->wrap(),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('start_at')
                        ->label('Mulai')
                        ->formatStateUsing(fn (?string $state): string => $this->formatDateTime($state))
                        ->placeholder('-')
                        ->alignCenter(),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('deadline')
                        ->label('Deadline')
                        ->description(fn (array $record): string => (string) ($record['deadline_label'] ?? ''))
                        ->formatStateUsing(fn (?string $state): string => $this->formatDateTime($state))
                        ->sortable()
                        ->alignCenter(),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('status_label')
                        ->label('Status')
                        ->badge()
                        ->color(fn (array $record): string => TaskStatus::color($record['status'] ?? ''))
                        ->alignCenter(),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('submission_label')
                        ->label('Pengumpulan')
                        ->badge()
                        ->description(fn (array $record): string => (string) ($record['submission_by_label'] ?? ''))
                        ->color(fn (array $record): string => match ($record['submission_status']) {
                            'graded' => 'success',
                            'late' => 'danger',
                            'submitted' => 'info',
                            default => 'warning',
                        })
                        ->alignCenter(),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('submission_grade')
                        ->label('Nilai')
                        ->badge()
                        ->placeholder('-')
                        ->alignCenter(),
                ),
            ])
            ->recordActions([
                $this->detailAction(),
                $this->submitAction(),
                $this->feedbackAction(),
            ])
            ->recordAction('detail')
            ->defaultSort('deadline')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada tugas')
            ->emptyStateDescription('Tugas dari mata kuliah Anda akan tampil di sini.');
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
