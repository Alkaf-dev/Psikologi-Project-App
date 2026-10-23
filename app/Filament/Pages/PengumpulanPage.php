<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresStaffRole;
use App\Filament\Support\BaseSheetPage;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Support\TaskGroups;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PengumpulanPage extends BaseSheetPage
{
    use RequiresStaffRole;

    protected static ?string $navigationLabel = 'Pengumpulan';

    protected static ?string $title = 'Pengumpulan';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 4;

    protected array $searchableColumns = ['task_title', 'student_name', 'student_npm', 'group_name', 'status'];

    public function pageMascot(): ?string
    {
        return 'menunjuk';
    }

    public function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'submission-table sheet-responsive-table task-table-compact'])
            ->records(function (
                ?string $search,
                ?string $sortColumn,
                ?string $sortDirection,
                int|string $page,
                int|string $recordsPerPage,
            ) {
                $records = app(SubmissionRepository::class)->withDetails();

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
                    TextColumn::make('task_title')
                        ->label('Tugas')
                        ->weight('semibold')
                        ->color('primary')
                        ->tooltip(fn (array $record): string => trim((string) ($record['task_title'] ?? '')) ?: 'Klik untuk melihat rincian')
                        ->action($this->detailAction())
                        ->searchable()
                        ->width('19%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('student_name')
                        ->label('Mahasiswa / Perwakilan')
                        ->description(fn (array $record): string => (string) ($record['student_npm'] ?? ''))
                        ->tooltip(function (array $record): ?string {
                            $name = trim((string) ($record['student_name'] ?? ''));
                            $npm = trim((string) ($record['student_npm'] ?? ''));
                            $label = trim("{$name} {$npm}");

                            return $label !== '' ? "{$label} (Klik untuk melihat rincian)" : 'Klik untuk melihat rincian';
                        })
                        ->action($this->detailAction())
                        ->wrapHeader()
                        ->searchable()
                        ->width('17%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('group_name')
                        ->label('Kelompok')
                        ->tooltip(function (array $record): ?string {
                            $group = trim((string) ($record['group_name'] ?? ''));

                            return $group !== '' ? "{$group} (Klik untuk melihat rincian)" : 'Klik untuk melihat rincian';
                        })
                        ->action($this->detailAction())
                        ->wrapHeader()
                        ->searchable()
                        ->width('13%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'late' => 'Terlambat',
                            'graded' => 'Dinilai',
                            default => 'Dikumpulkan',
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'late' => 'danger',
                            'graded' => 'success',
                            default => 'warning',
                        })
                        ->action($this->detailAction())
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('12%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('link')
                        ->label('Tautan')
                        ->formatStateUsing(fn (array $record): string => $this->submissionLink($record) !== null ? 'Kirim' : '-')
                        ->url(fn (array $record): ?string => $this->submissionLink($record))
                        ->openUrlInNewTab()
                        ->color(fn (array $record): ?string => $this->submissionLink($record) !== null ? 'primary' : null)
                        ->tooltip(fn (array $record): ?string => $this->submissionLink($record) !== null ? 'Buka tautan pengumpulan di tab baru' : null)
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('5.5%'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('submitted_at')
                        ->label('Dikumpulkan')
                        ->formatStateUsing(fn (?string $state): string => $state
                            ? Carbon::parse($state)->translatedFormat('d M Y H:i')
                            : '-')
                        ->tooltip(fn (?string $state): string => $state ? Carbon::parse($state)->translatedFormat('d F Y H:i:s') : 'Klik untuk melihat rincian')
                        ->action($this->detailAction())
                        ->sortable()
                        ->wrapHeader()
                        ->alignCenter()
                        ->width('9.5%'),
                ),
            ])
            ->searchPlaceholder('Cari tugas, mahasiswa, atau kelompok...')
            ->headerActions([$this->createAction()])
            ->recordActions([
                $this->detailAction(),
                $this->editAction(),
                $this->deleteAction(),
            ])
            ->recordAction('detail')
            ->defaultSort('submitted_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada pengumpulan');
    }

    protected function detailAction(): Action
    {
        return Action::make('detail')
            ->label('Detail')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (array $record): string => 'Detail Pengumpulan: '.($record['task_title'] ?? ''))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalWidth('3xl')
            ->modalContent(fn (array $record) => view('filament.pages.partials.submission-detail', [
                'submission' => $record,
            ]));
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Pengumpulan')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Pengumpulan')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('3xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $data = $this->prepareSubmissionData($data);
                $this->validateSubmissionData($data);
                $data['submitted_at'] ??= now()->toIso8601String();

                $repository = app(SubmissionRepository::class);

                if ($this->isGroupTask($data['task_id'] ?? null)) {
                    $repository->upsertForTaskAndGroup(
                        (string) $data['task_id'],
                        (string) $data['group_id'],
                        $data,
                    );
                } else {
                    $repository->create($data);
                }

                $this->resetTable();

                Notification::make()->title('Pengumpulan ditambahkan')->success()->send();
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading('Ubah Pengumpulan')
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('3xl')
            ->fillForm(fn (array $record): array => $this->normalizeSubmission($record))
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                app(SubmissionRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()->title('Pengumpulan diperbarui')->success()->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus pengumpulan?')
            ->action(function (array $record): void {
                app(SubmissionRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()->title('Pengumpulan dihapus')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function formSchema(): array
    {
        return [
            Select::make('task_id')
                ->label('Tugas')
                ->options(fn (): array => app(TaskRepository::class)->options())
                ->searchable()
                ->live()
                ->required(),
            Select::make('group_id')
                ->label('Kelompok')
                ->options(function (Get $get): array {
                    $task = $this->selectedTask($get('task_id'));

                    if (! $this->isGroupTask($get('task_id'))) {
                        return [];
                    }

                    $groups = app(GroupRepository::class);
                    $options = [];

                    foreach (TaskGroups::assignedIds($task ?? []) as $groupId) {
                        $group = $groups->find($groupId);

                        if ($group) {
                            $options[$groupId] = (string) ($group['name'] ?? 'Kelompok');
                        }
                    }

                    return $options;
                })
                ->searchable()
                ->live()
                ->visible(fn (Get $get): bool => $this->isGroupTask($get('task_id')))
                ->required(fn (Get $get): bool => $this->isGroupTask($get('task_id'))),
            Select::make('user_id')
                ->label(fn (Get $get): string => $this->isGroupTask($get('task_id'))
                    ? 'Perwakilan kelompok'
                    : 'Mahasiswa')
                ->options(function (Get $get): array {
                    if (! $this->isGroupTask($get('task_id'))) {
                        return app(UserRepository::class)->options();
                    }

                    $groupId = trim((string) ($get('group_id') ?? ''));

                    if ($groupId === '') {
                        return [];
                    }

                    $users = app(UserRepository::class);

                    return app(GroupMemberRepository::class)
                        ->forGroup($groupId)
                        ->mapWithKeys(function (array $member) use ($users): array {
                            $user = $users->find((string) ($member['user_id'] ?? ''));
                            $name = (string) ($user['name'] ?? 'Tanpa nama');
                            $npm = (string) ($user['npm'] ?? '');

                            return [(string) ($member['user_id'] ?? '') => trim("{$name} - {$npm}", ' -')];
                        })
                        ->all();
                })
                ->searchable()
                ->required(),
            Select::make('status')
                ->label('Status')
                ->options([
                    'submitted' => 'Dikumpulkan',
                    'late' => 'Terlambat',
                ])
                ->default('submitted')
                ->required(),
            TextInput::make('link')
                ->label('Tautan pengumpulan')
                ->url()
                ->maxLength(500)
                ->required(),
            DateTimePicker::make('submitted_at')
                ->label('Waktu dikumpulkan')
                ->seconds(false),
            Textarea::make('notes')
                ->label('Catatan mahasiswa')
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('feedback')
                ->label('Feedback dosen')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareSubmissionData(array $data): array
    {
        $data['group_id'] = $this->isGroupTask($data['task_id'] ?? null)
            ? trim((string) ($data['group_id'] ?? ''))
            : '';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateSubmissionData(array $data): void
    {
        $task = $this->selectedTask($data['task_id'] ?? null);

        if (! $this->isGroupTask($data['task_id'] ?? null)) {
            return;
        }

        $groupId = trim((string) ($data['group_id'] ?? ''));
        $userId = trim((string) ($data['user_id'] ?? ''));
        $errors = [];

        if ($groupId === '') {
            $errors[$this->actionErrorKey('group_id')] = 'Pilih kelompok untuk tugas kelompok.';
        } else {
            if (! in_array($groupId, TaskGroups::assignedIds($task ?? []), true)) {
                $errors[$this->actionErrorKey('group_id')] = 'Kelompok tidak ditugaskan pada tugas ini.';
            }

            $memberIds = app(GroupMemberRepository::class)
                ->forGroup($groupId)
                ->pluck('user_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            if (! in_array($userId, $memberIds, true)) {
                $errors[$this->actionErrorKey('user_id')] = 'Perwakilan harus anggota kelompok yang dipilih.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function selectedTask(mixed $taskId): ?array
    {
        return app(TaskRepository::class)->find((string) $taskId);
    }

    protected function isGroupTask(mixed $taskId): bool
    {
        return ($this->selectedTask($taskId)['type'] ?? 'individu') === 'kelompok';
    }

    protected function actionErrorKey(string $field): string
    {
        $statePath = $this->getMountedActionSchema()?->getStatePath();

        return $statePath ? "{$statePath}.{$field}" : $field;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function submissionLink(array $record): ?string
    {
        $link = trim((string) ($record['link'] ?? ''));

        if ($link === '' || filter_var($link, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $link : null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function normalizeSubmission(array $record): array
    {
        $record['submitted_at'] = filled($record['submitted_at'] ?? null)
            ? Carbon::parse($record['submitted_at'])->format('Y-m-d H:i:s')
            : null;

        return $record;
    }
}
