<?php

namespace App\Filament\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\GroupRepository;
use App\Repositories\SubmissionRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class PengumpulanPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Pengumpulan';

    protected static ?string $title = 'Pengumpulan & Penilaian';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 5;

    protected array $searchableColumns = ['task_title', 'student_name', 'student_npm', 'group_name', 'status'];

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
                TextColumn::make('task_title')
                    ->label('Tugas')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('student_name')
                    ->label('Mahasiswa')
                    ->description(fn (array $record): string => (string) ($record['student_npm'] ?? ''))
                    ->searchable(),
                TextColumn::make('group_name')
                    ->label('Kelompok')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'graded' => 'Dinilai',
                        'late' => 'Terlambat',
                        default => 'Dikumpulkan',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'graded' => 'success',
                        'late' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('link')
                    ->label('Tautan')
                    ->url(fn (array $record): ?string => filled($record['link'] ?? null) ? (string) $record['link'] : null, true)
                    ->limit(35)
                    ->placeholder('-'),
                TextColumn::make('grade')
                    ->label('Nilai')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('submitted_at')
                    ->label('Dikumpulkan')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? Carbon::parse($state)->translatedFormat('d M Y H:i')
                        : '-')
                    ->sortable(),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([
                $this->gradeAction(),
                $this->editAction(),
                $this->deleteAction(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada pengumpulan');
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
                $data['submitted_at'] ??= now()->toIso8601String();

                app(SubmissionRepository::class)->create($data);

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

    protected function gradeAction(): Action
    {
        return Action::make('grade')
            ->label('Nilai')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading('Beri Nilai')
            ->modalSubmitActionLabel('Simpan nilai')
            ->schema([
                TextInput::make('grade')
                    ->label('Nilai (0-100)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100),
                Textarea::make('feedback')
                    ->label('Catatan untuk mahasiswa')
                    ->rows(3),
            ])
            ->fillForm(fn (array $record): array => [
                'grade' => $record['grade'] ?? '',
                'feedback' => $record['feedback'] ?? '',
            ])
            ->action(function (array $data, array $record): void {
                app(SubmissionRepository::class)->update((string) $record['id'], [
                    'grade' => (string) $data['grade'],
                    'feedback' => (string) ($data['feedback'] ?? ''),
                    'status' => 'graded',
                ]);

                $this->resetTable();

                Notification::make()->title('Nilai berhasil disimpan')->success()->send();
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
                ->required(),
            Select::make('user_id')
                ->label('Mahasiswa')
                ->options(fn (): array => app(UserRepository::class)->options())
                ->searchable()
                ->required(),
            Select::make('group_id')
                ->label('Kelompok')
                ->options(fn (): array => app(GroupRepository::class)->options())
                ->searchable()
                ->placeholder('Tidak ada'),
            Select::make('status')
                ->label('Status')
                ->options([
                    'submitted' => 'Dikumpulkan',
                    'late' => 'Terlambat',
                    'graded' => 'Dinilai',
                ])
                ->default('submitted')
                ->required(),
            TextInput::make('link')
                ->label('Tautan pengumpulan')
                ->url()
                ->maxLength(500)
                ->required(),
            TextInput::make('grade')
                ->label('Nilai')
                ->numeric()
                ->minValue(0)
                ->maxValue(100),
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
