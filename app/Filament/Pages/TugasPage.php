<?php

namespace App\Filament\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
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

class TugasPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Tugas';

    protected static ?string $title = 'Manajemen Tugas';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 4;

    protected array $searchableColumns = ['title', 'course_name', 'group_name', 'type_label'];

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
                TextColumn::make('title')
                    ->label('Judul tugas')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_name')
                    ->label('Mata kuliah')
                    ->searchable(),
                TextColumn::make('type_label')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Kelompok' ? 'info' : 'gray'),
                TextColumn::make('group_name')
                    ->label('Kelompok')
                    ->wrap(),
                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? Carbon::parse($state)->translatedFormat('d M Y H:i')
                        : '-')
                    ->sortable(),
                TextColumn::make('submission_count')
                    ->label('Dikumpulkan')
                    ->badge()
                    ->color('success'),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([$this->editAction(), $this->deleteAction()])
            ->defaultSort('deadline')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada tugas');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Tugas')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Tugas')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('3xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
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
            ->modalWidth('3xl')
            ->fillForm(function (array $record): array {
                $record['deadline'] = filled($record['deadline'] ?? null)
                    ? Carbon::parse($record['deadline'])->format('Y-m-d H:i:s')
                    : null;

                return $record;
            })
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
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
            Select::make('group_id')
                ->label('Kelompok')
                ->options(function (Get $get): array {
                    $courseId = (string) ($get('course_id') ?? '');

                    if ($courseId === '') {
                        return app(GroupRepository::class)->options();
                    }

                    return app(GroupRepository::class)
                        ->withDetails(app(GroupRepository::class)->forCourse($courseId))
                        ->mapWithKeys(fn (array $group): array => [
                            (string) $group['id'] => $group['name'],
                        ])
                        ->all();
                })
                ->searchable()
                ->placeholder('Semua mahasiswa')
                ->visible(fn (Get $get): bool => $get('type') === 'kelompok')
                ->required(fn (Get $get): bool => $get('type') === 'kelompok'),
            TextInput::make('title')
                ->label('Judul tugas')
                ->required()
                ->maxLength(180),
            DateTimePicker::make('deadline')
                ->label('Deadline')
                ->seconds(false)
                ->required(),
            Textarea::make('description')
                ->label('Deskripsi / instruksi')
                ->rows(4)
                ->columnSpanFull(),
        ];
    }
}
