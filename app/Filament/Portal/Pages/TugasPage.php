<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
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

    protected array $searchableColumns = ['title', 'course_name', 'type_label', 'submission_label'];

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

                $submissions = app(SubmissionRepository::class)
                    ->forStudent($userId)
                    ->keyBy('task_id');

                $records = app(TaskRepository::class)
                    ->withDetails(app(TaskRepository::class)->forStudent($student ?? []))
                    ->map(function (array $task) use ($submissions): array {
                        $submission = $submissions->get((string) $task['id']);

                        $task['submission_id'] = $submission['id'] ?? null;
                        $task['submission_status'] = $submission['status'] ?? null;
                        $task['submission_link'] = $submission['link'] ?? null;
                        $task['submission_notes'] = $submission['notes'] ?? null;
                        $task['submission_grade'] = $submission['grade'] ?? null;
                        $task['submission_feedback'] = $submission['feedback'] ?? null;
                        $task['submission_label'] = match ($task['submission_status']) {
                            'graded' => 'Dinilai',
                            'late' => 'Terlambat',
                            'submitted' => 'Dikumpulkan',
                            default => 'Belum dikumpulkan',
                        };

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
                TextColumn::make('title')
                    ->label('Tugas')
                    ->weight('semibold')
                    ->description(fn (array $record): string => (string) $record['course_name'])
                    ->searchable()
                    ->wrap(),
                TextColumn::make('type_label')
                    ->label('Jenis')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? Carbon::parse($state)->translatedFormat('d M Y H:i')
                        : '-')
                    ->sortable(),
                TextColumn::make('submission_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn (array $record): string => match ($record['submission_status']) {
                        'graded' => 'success',
                        'late' => 'danger',
                        'submitted' => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('submission_grade')
                    ->label('Nilai')
                    ->badge()
                    ->placeholder('-'),
            ])
            ->recordActions([
                $this->submitAction(),
                $this->feedbackAction(),
            ])
            ->defaultSort('deadline')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada tugas')
            ->emptyStateDescription('Tugas dari mata kuliah Anda akan tampil di sini.');
    }

    protected function submitAction(): Action
    {
        return Action::make('submit')
            ->label(fn (array $record): string => $record['submission_id'] ? 'Perbarui' : 'Kumpulkan')
            ->icon(Heroicon::ArrowUpTray)
            ->modalHeading(fn (array $record): string => 'Kumpulkan: '.$record['title'])
            ->modalSubmitActionLabel('Kirim')
            ->modalWidth('2xl')
            ->visible(fn (array $record): bool => ($record['submission_status'] ?? null) !== 'graded')
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
                $deadline = filled($record['deadline'] ?? null)
                    ? Carbon::parse($record['deadline'])
                    : null;

                $isLate = $deadline !== null && now()->greaterThan($deadline);

                $groupId = ($record['type'] ?? 'individu') === 'kelompok'
                    ? (string) ($record['group_id'] ?? '')
                    : '';

                app(SubmissionRepository::class)->upsertForTaskAndUser(
                    (string) $record['id'],
                    (string) auth()->id(),
                    [
                        'group_id' => $groupId,
                        'status' => $isLate ? 'late' : 'submitted',
                        'link' => (string) $data['link'],
                        'notes' => (string) ($data['notes'] ?? ''),
                        'submitted_at' => now()->toIso8601String(),
                    ],
                );

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
}
