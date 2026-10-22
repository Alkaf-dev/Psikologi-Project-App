<?php

namespace App\Filament\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Support\TaskGroups;
use App\Support\TaskStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TugasPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Tugas';

    protected static ?string $title = 'Manajemen Tugas';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    protected array $searchableColumns = [
        'title', 'description', 'course_name', 'group_name', 'type_label',
        'meeting_number', 'status_label',
    ];

    public function showMascot(): bool
    {
        return true;
    }

    public function mascotContext(): string
    {
        return 'admin-tasks';
    }

    public function table(Table $table): Table
    {
        return $table
            // Filament v5 mengatur kepadatan tabel melalui kelas CSS scoped,
            // karena TableDensity bawaan sudah tidak tersedia di versi ini.
            ->extraAttributes(['class' => 'task-table-compact'])
            ->records(function (
                ?string $search,
                ?string $sortColumn,
                ?string $sortDirection,
                int|string $page,
                int|string $recordsPerPage,
            ) {
                $counts = app(SubmissionRepository::class)->countsByTask();

                $records = app(TaskRepository::class)
                    ->withDetails()
                    ->map(function (array $task) use ($counts): array {
                        $task['submission_count'] = $counts[(string) $task['id']] ?? 0;

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
                        ->alignCenter()
                        ->width('6rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('title')
                        ->label('Tugas')
                        ->weight('semibold')
                        ->color('primary')
                        // Batasi teks agar baris tidak melar, tanpa memotong tooltip.
                        ->limit(50)
                        ->tooltip(fn (array $record): string => trim((string) ($record['description'] ?? '')) ?: 'Klik untuk melihat rincian')
                        ->action($this->detailAction())
                        ->searchable()
                        ->width('18rem'),
                ),
                $this->spreadsheetColumn(
                    // Tautan pengumpulan dibuka di tab baru dan hanya aktif jika terisi.
                    TextColumn::make('collection_link')
                        ->label('Link Pengumpulan')
                        ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Buka link' : '-')
                        ->url(fn (array $record): ?string => filled($record['collection_link'] ?? null) ? (string) $record['collection_link'] : null)
                        ->openUrlInNewTab()
                        ->icon(fn (array $record): ?Heroicon => filled($record['collection_link'] ?? null) ? Heroicon::OutlinedArrowTopRightOnSquare : null)
                        ->iconPosition(IconPosition::After)
                        ->color(fn (array $record): ?string => filled($record['collection_link'] ?? null) ? 'primary' : null)
                        ->tooltip(fn (array $record): ?string => filled($record['collection_link'] ?? null) ? 'Buka link pengumpulan di tab baru' : null)
                        ->width('10rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('course_name')
                        ->label('Mata kuliah')
                        ->searchable()
                        ->width('12rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('type_label')
                        ->label('Jenis')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Kelompok' ? 'info' : 'gray')
                        ->alignCenter()
                        ->width('6rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('group_name')
                        ->label('Kelompok')
                        ->width('10rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('start_at')
                        ->label('Mulai')
                        ->formatStateUsing(fn (?string $state): string => $this->formatDateTime($state))
                        ->placeholder('-')
                        ->alignCenter()
                        ->width('10rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('deadline')
                        ->label('Deadline')
                        // Sisa waktu tampil sebagai sub-teks abu-abu bawaan Filament.
                        ->description(fn (array $record): string => (string) ($record['deadline_label'] ?? ''))
                        ->formatStateUsing(fn (?string $state): string => $this->formatDateTime($state))
                        ->sortable()
                        ->alignCenter()
                        ->width('11rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('status_label')
                        ->label('Status')
                        ->badge()
                        ->color(fn (array $record): string => TaskStatus::color($record['status'] ?? ''))
                        ->icon(fn (array $record): Heroicon => match ($record['status'] ?? '') {
                            TaskStatus::DRAFT => Heroicon::OutlinedPencilSquare,
                            TaskStatus::COMPLETED => Heroicon::OutlinedCheckCircle,
                            default => Heroicon::OutlinedPlayCircle,
                        })
                        ->alignCenter()
                        ->width('7rem'),
                ),
                $this->spreadsheetColumn(
                    TextColumn::make('submission_count')
                        ->label('Dikumpulkan')
                        ->badge()
                        ->color('success')
                        ->alignCenter()
                        ->width('7rem'),
                ),
            ])
            ->searchPlaceholder('Cari tugas, mata kuliah, atau kelompok...')
            ->headerActions([$this->createAction()])
            ->recordActions([$this->detailAction(), $this->editAction(), $this->deleteAction()])
            ->recordAction('detail')
            ->defaultSort('deadline')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada tugas');
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

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Tugas')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Tugas')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('4xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $data = $this->prepareTaskData($data);
                $this->validateTaskData($data);
                $data['created_by'] = (string) auth()->id();

                app(TaskRepository::class)->create($data);

                $this->resetTable();

                Notification::make()->title('Tugas ditambahkan')->success()->send();
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(fn (array $record): string => 'Ubah '.$record['title'])
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('4xl')
            ->fillForm(function (array $record): array {
                $record['deadline'] = filled($record['deadline'] ?? null)
                    ? Carbon::parse($record['deadline'])->format('Y-m-d H:i:s')
                    : null;
                $record['start_at'] = filled($record['start_at'] ?? null)
                    ? Carbon::parse($record['start_at'])->format('Y-m-d H:i:s')
                    : null;
                $record['group_ids'] = TaskGroups::ids($record['group_ids'] ?? '');

                if ($record['group_ids'] === [] && filled($record['group_id'] ?? null)) {
                    $record['group_ids'] = [(string) $record['group_id']];
                }

                $record['status'] = TaskStatus::normalize($record['status'] ?? '');

                return $record;
            })
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                $data = $this->prepareTaskData($data);
                $this->validateTaskData($data);

                app(TaskRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()->title('Tugas diperbarui')->success()->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus tugas?')
            ->modalDescription('Pengumpulan yang sudah ada tetap tersimpan di spreadsheet.')
            ->action(function (array $record): void {
                app(TaskRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()->title('Tugas dihapus')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function formSchema(): array
    {
        return [
            Select::make('course_id')
                ->label('Mata kuliah')
                ->options(fn (): array => app(CourseRepository::class)->options())
                ->searchable()
                ->live()
                ->required(),
            Select::make('type')
                ->label('Jenis tugas')
                ->options([
                    'individu' => 'Individu',
                    'kelompok' => 'Kelompok',
                ])
                ->default('individu')
                ->live()
                ->required(),
            TextInput::make('meeting_number')
                ->label('Pertemuan')
                ->numeric()
                ->minValue(1)
                ->maxValue(99),
            Select::make('status')
                ->label('Keterangan')
                ->options(TaskStatus::options())
                ->default(TaskStatus::ACTIVE)
                ->required(),
            TextInput::make('title')
                ->label('Judul tugas / topik')
                ->required()
                ->maxLength(180)
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Tugas / instruksi')
                ->rows(5)
                ->columnSpanFull(),
            TextInput::make('material_url')
                ->label('Materi (tautan)')
                ->url()
                ->maxLength(500),
            TextInput::make('collection_link')
                ->label('Link pengumpulan (Drive)')
                ->url()
                ->maxLength(500),
            Select::make('group_ids')
                ->label('Kelompok yang bertugas')
                ->options(function (Get $get): array {
                    $courseId = (string) ($get('course_id') ?? '');

                    if ($courseId === '') {
                        return [];
                    }

                    return app(GroupRepository::class)
                        ->withDetails(app(GroupRepository::class)->forCourse($courseId))
                        ->mapWithKeys(fn (array $group): array => [
                            (string) $group['id'] => $group['name'],
                        ])
                        ->all();
                })
                ->multiple()
                ->searchable()
                ->preload()
                ->placeholder('Pilih kelompok')
                ->visible(fn (Get $get): bool => $get('type') === 'kelompok')
                ->required(fn (Get $get): bool => $get('type') === 'kelompok')
                ->helperText('Satu tugas dapat ditugaskan ke beberapa kelompok.')
                ->columnSpanFull(),
            DateTimePicker::make('start_at')
                ->label('Tanggal mulai')
                ->seconds(false),
            DateTimePicker::make('deadline')
                ->label('Deadline')
                ->seconds(false)
                ->required(),
            TextInput::make('deadline_note')
                ->label('Keterangan deadline')
                ->maxLength(150)
                ->helperText('Kosongkan untuk dihitung otomatis dari deadline.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareTaskData(array $data): array
    {
        $groupIds = ($data['type'] ?? 'individu') === 'kelompok'
            ? TaskGroups::ids($data['group_ids'] ?? [])
            : [];

        $data['group_ids'] = TaskGroups::csv($groupIds);
        $data['group_id'] = $groupIds[0] ?? '';
        $data['status'] = TaskStatus::normalize($data['status'] ?? '');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateTaskData(array $data): void
    {
        if (($data['type'] ?? 'individu') !== 'kelompok') {
            return;
        }

        $groupIds = TaskGroups::ids($data['group_ids'] ?? '');
        $errors = [];

        if ($groupIds === []) {
            $errors[$this->actionErrorKey('group_ids')] = 'Pilih minimal satu kelompok.';
        } else {
            $allowedGroupIds = app(GroupRepository::class)
                ->forCourse((string) ($data['course_id'] ?? ''))
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            if (array_diff($groupIds, $allowedGroupIds) !== []) {
                $errors[$this->actionErrorKey('group_ids')] = 'Kelompok harus berasal dari mata kuliah yang dipilih.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function actionErrorKey(string $field): string
    {
        $statePath = $this->getMountedActionSchema()?->getStatePath();

        return $statePath ? "{$statePath}.{$field}" : $field;
    }

    protected function formatDateTime(?string $value): string
    {
        return filled($value)
            ? Carbon::parse($value)->translatedFormat('d M Y H:i')
            : '-';
    }
}
