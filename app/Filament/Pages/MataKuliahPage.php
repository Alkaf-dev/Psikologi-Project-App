<?php

namespace App\Filament\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Support\ExternalUrl;
use App\Support\ProdiAssignments;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class MataKuliahPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Mata Kuliah';

    protected static ?string $title = 'Mata Kuliah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 1;

    protected array $searchableColumns = ['code', 'name', 'lecturer', 'semester', 'class_name', 'prodi_names'];

    public function pageMascot(): ?string
    {
        return 'memegang-buku';
    }

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
                return $this->paginateRecords(
                    app(CourseRepository::class)->withProgramNames(),
                    $search,
                    $sortColumn,
                    $sortDirection,
                    $page,
                    $recordsPerPage,
                );
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->weight('semibold')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama mata kuliah')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('credits')
                    ->label('SKS')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('lecturer')
                    ->label('Dosen')
                    ->searchable(),
                TextColumn::make('semester')
                    ->label('Semester')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('class_name')
                    ->label('Kelas')
                    ->sortable(),
                TextColumn::make('prodi_names')
                    ->label('Prodi')
                    ->badge()
                    ->searchable()
                    ->wrap()
                    ->alignCenter(),
                TextColumn::make('meeting_link')
                    ->label('Link (Online)')
                    ->formatStateUsing(fn (?string $state): string => ExternalUrl::http($state) !== null ? 'Masuk' : '-')
                    ->url(fn (?string $state): ?string => ExternalUrl::http($state))
                    ->openUrlInNewTab()
                    ->color(fn (?string $state): ?string => ExternalUrl::http($state) !== null ? 'primary' : null)
                    ->tooltip(fn (?string $state): ?string => ExternalUrl::http($state) !== null ? 'Buka tautan perkuliahan di tab baru' : null)
                    ->alignCenter(),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([$this->editAction(), $this->deleteAction()])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada mata kuliah');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Mata Kuliah')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Mata Kuliah')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('2xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $this->validateCourse($data);
                $this->prepareCourseData($data);

                app(CourseRepository::class)->create($data);

                $this->resetTable();

                Notification::make()->title('Mata kuliah ditambahkan')->success()->send();
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(fn (array $record): string => 'Ubah '.$record['name'])
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('2xl')
            ->fillForm(fn (array $record): array => $this->normalizeCourse($record))
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                $this->validateCourse($data);
                $this->prepareCourseData($data);

                app(CourseRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()->title('Mata kuliah diperbarui')->success()->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus mata kuliah?')
            ->action(function (array $record): void {
                app(CourseRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()->title('Mata kuliah dihapus')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function formSchema(): array
    {
        return [
            TextInput::make('code')
                ->label('Kode mata kuliah')
                ->required()
                ->maxLength(30)
                ->placeholder('PSI101'),
            TextInput::make('name')
                ->label('Nama mata kuliah')
                ->required()
                ->maxLength(150),
            TextInput::make('credits')
                ->label('SKS')
                ->numeric()
                ->required()
                ->default(2)
                ->minValue(1)
                ->maxValue(8),
            TextInput::make('lecturer')
                ->label('Dosen pengampu')
                ->required()
                ->maxLength(150),
            TextInput::make('semester')
                ->label('Semester')
                ->required()
                ->maxLength(20)
                ->placeholder('Ganjil'),
            TextInput::make('class_name')
                ->label('Kelas')
                ->required()
                ->maxLength(50)
                ->datalist(fn (): array => app(UserRepository::class)->classNames()),
            Select::make('prodi_ids')
                ->label('Program Studi')
                ->options(fn (): array => app(StudyProgramRepository::class)->allOptions())
                ->multiple()
                ->required()
                ->searchable()
                ->preload()
                ->helperText('Satu mata kuliah dapat dipakai beberapa Prodi. Buat data terpisah jika kelasnya berbeda.')
                ->columnSpanFull(),
            TextInput::make('meeting_link')
                ->label('Link (Online)')
                ->url()
                ->maxLength(500)
                ->nullable()
                ->placeholder('https://meet.google.com/... atau tautan Zoom')
                ->helperText('Tautan perkuliahan daring (Zoom, Google Meet, dll) jika ada.')
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function normalizeCourse(array $record): array
    {
        $record['prodi_ids'] = ProdiAssignments::ids($record['prodi_ids'] ?? '');
        $record['meeting_link'] = (string) ($record['meeting_link'] ?? '');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function prepareCourseData(array &$data): void
    {
        $data['prodi_ids'] = ProdiAssignments::csv($data['prodi_ids'] ?? []);
        $data['meeting_link'] = trim((string) ($data['meeting_link'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateCourse(array $data): void
    {
        $ids = ProdiAssignments::ids($data['prodi_ids'] ?? []);
        $errors = [];

        if ($ids === []) {
            $errors[$this->actionErrorKey('prodi_ids')] = 'Pilih minimal satu Prodi.';
        } elseif (ProdiAssignments::invalidIds($ids) !== []) {
            $errors[$this->actionErrorKey('prodi_ids')] = 'Pilihan Prodi tidak valid.';
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
}
