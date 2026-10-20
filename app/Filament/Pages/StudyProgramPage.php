<?php

namespace App\Filament\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class StudyProgramPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Program Studi';

    protected static ?string $title = 'Program Studi';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Administrasi';

    protected static ?int $navigationSort = 2;

    protected array $searchableColumns = ['code', 'name', 'degree'];

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
                    app(StudyProgramRepository::class)->all(),
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Prodi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('degree')
                    ->label('Jenjang')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([$this->editAction(), $this->deleteAction()])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada program studi')
            ->emptyStateDescription('Tambahkan Prodi agar mahasiswa dan admin dapat memilihnya saat login.');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Prodi')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Program Studi')
            ->modalSubmitActionLabel('Simpan')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $this->validateProgram($data);
                $this->prepareProgramData($data);

                app(StudyProgramRepository::class)->create($data);

                $this->resetTable();

                Notification::make()
                    ->title('Program studi berhasil ditambahkan')
                    ->success()
                    ->send();
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(fn (array $record): string => 'Ubah '.$record['name'])
            ->modalSubmitActionLabel('Simpan perubahan')
            ->fillForm(fn (array $record): array => $record)
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                $this->validateProgram($data, (string) $record['id']);
                $this->prepareProgramData($data);

                app(StudyProgramRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()
                    ->title('Program studi berhasil diperbarui')
                    ->success()
                    ->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus program studi?')
            ->modalDescription('Prodi yang masih dipakai akun, mata kuliah, atau pengumuman tidak dapat dihapus. Nonaktifkan Prodi untuk mencegah login baru.')
            ->action(function (array $record): void {
                $programId = (string) $record['id'];
                $usage = [];

                $userCount = app(UserRepository::class)->countByProdi($programId, true);
                $courseCount = app(CourseRepository::class)->countByProdi($programId, true);
                $announcementCount = app(AnnouncementRepository::class)->countByProdi($programId, true);

                if ($userCount > 0) {
                    $usage[] = "{$userCount} akun";
                }

                if ($courseCount > 0) {
                    $usage[] = "{$courseCount} mata kuliah";
                }

                if ($announcementCount > 0) {
                    $usage[] = "{$announcementCount} pengumuman";
                }

                if ($usage !== []) {
                    Notification::make()
                        ->title('Prodi tidak dapat dihapus')
                        ->body('Masih dipakai oleh '.implode(', ', $usage).'. Nonaktifkan Prodi sebagai gantinya.')
                        ->danger()
                        ->send();

                    return;
                }

                app(StudyProgramRepository::class)->delete($programId);

                $this->resetTable();

                Notification::make()
                    ->title('Program studi dihapus')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function formSchema(): array
    {
        return [
            TextInput::make('code')
                ->label('Kode Prodi')
                ->required()
                ->maxLength(30)
                ->placeholder('psikologi'),
            TextInput::make('name')
                ->label('Nama Prodi')
                ->required()
                ->maxLength(150)
                ->placeholder('Psikologi'),
            TextInput::make('degree')
                ->label('Jenjang')
                ->required()
                ->maxLength(20)
                ->placeholder('S1'),
            Toggle::make('active')
                ->label('Prodi aktif')
                ->helperText('Hanya Prodi aktif yang muncul pada pilihan login.')
                ->default(true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function prepareProgramData(array &$data): void
    {
        $data['code'] = mb_strtolower(trim((string) $data['code']));
        $data['name'] = trim((string) $data['name']);
        $data['degree'] = trim((string) $data['degree']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateProgram(array $data, ?string $exceptId = null): void
    {
        $programs = app(StudyProgramRepository::class);
        $errors = [];

        if ($programs->codeExists((string) ($data['code'] ?? ''), $exceptId)) {
            $errors['data.code'] = 'Kode Prodi sudah digunakan.';
        }

        if ($programs->nameExists((string) ($data['name'] ?? ''), $exceptId)) {
            $errors['data.name'] = 'Nama Prodi sudah digunakan.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
