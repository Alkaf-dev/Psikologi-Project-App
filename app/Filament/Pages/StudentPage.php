<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Support\BaseSheetPage;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StudentPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Mahasiswa';

    protected static ?string $title = 'Pendataan Mahasiswa';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Administrasi';

    protected static ?int $navigationSort = 1;

    protected array $searchableColumns = ['name', 'npm', 'email', 'prodi_name', 'class_name', 'cohort'];

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
                $programNames = app(StudyProgramRepository::class)->nameMap();

                $students = app(UserRepository::class)->students()->map(function (array $student) use ($programNames): array {
                    $student['prodi_name'] = $programNames[(string) ($student['prodi_id'] ?? '')] ?? 'Belum diisi';

                    return $student;
                });

                return $this->paginateRecords(
                    $students,
                    $search,
                    $sortColumn,
                    $sortDirection,
                    $page,
                    $recordsPerPage,
                );
            })
            ->columns([
                TextColumn::make('prodi_name')
                    ->label('Prodi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Mahasiswa')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('npm')
                    ->label('NIM')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('class_name')
                    ->label('Kelas')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cohort')
                    ->label('Angkatan')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->placeholder('-'),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->headerActions([
                $this->createAction(),
            ])
            ->recordActions([
                $this->editAction(),
                $this->resetPasswordAction(),
                $this->deleteAction(),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada mahasiswa')
            ->emptyStateDescription('Tambahkan mahasiswa beserta akun login agar dapat mengakses portal.');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Mahasiswa')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Mahasiswa')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('2xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $this->validateStudent($data);
                $this->prepareStudentData($data);

                app(UserRepository::class)->create($data);

                $this->resetTable();

                Notification::make()
                    ->title('Mahasiswa berhasil ditambahkan')
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
            ->modalWidth('2xl')
            ->fillForm(fn (array $record): array => $record)
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                $this->validateStudent($data, (string) $record['id']);
                $this->prepareStudentData($data);

                if (blank($data['password'] ?? null)) {
                    unset($data['password']);
                }

                app(UserRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()
                    ->title('Data mahasiswa diperbarui')
                    ->success()
                    ->send();
            });
    }

    protected function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon(Heroicon::Key)
            ->color('warning')
            ->modalHeading(fn (array $record): string => 'Reset Password '.$record['name'])
            ->modalDescription('Password baru akan langsung aktif. Bagikan password ini kepada mahasiswa secara aman.')
            ->modalSubmitActionLabel('Reset Password')
            ->schema([
                TextInput::make('password')
                    ->label('Password baru')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8),
            ])
            ->action(function (array $data, array $record): void {
                app(UserRepository::class)->update((string) $record['id'], [
                    'password_hash' => Hash::make((string) $data['password']),
                ]);

                Notification::make()
                    ->title('Password berhasil direset')
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
            ->modalHeading('Hapus data mahasiswa?')
            ->modalDescription('Data mahasiswa akan dihapus dari tampilan. Riwayatnya tetap tersimpan di spreadsheet.')
            ->action(function (array $record): void {
                app(UserRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()
                    ->title('Data mahasiswa dihapus')
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
            Select::make('prodi_id')
                ->label('Prodi')
                ->options(fn (): array => app(StudyProgramRepository::class)->allOptions())
                ->searchable()
                ->required()
                ->placeholder('Pilih Prodi'),
            TextInput::make('name')
                ->label('Nama Mahasiswa')
                ->required()
                ->maxLength(120),
            TextInput::make('npm')
                ->label('NIM')
                ->required()
                ->maxLength(30),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(150),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->helperText('Wajib diisi saat menambah mahasiswa. Kosongkan jika tidak ingin mengubah password.'),
            TextInput::make('class_name')
                ->label('Kelas')
                ->required()
                ->maxLength(50)
                ->datalist(fn (): array => app(UserRepository::class)->classNames()),
            TextInput::make('cohort')
                ->label('Angkatan')
                ->maxLength(10)
                ->placeholder('2024'),
            TextInput::make('phone')
                ->label('Nomor WhatsApp')
                ->tel()
                ->maxLength(30),
            Toggle::make('active')
                ->label('Akun aktif')
                ->default(true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function prepareStudentData(array &$data): void
    {
        if (filled($data['password'] ?? null)) {
            $data['password_hash'] = Hash::make((string) $data['password']);
        }

        unset($data['password']);

        $data['role'] = UserRole::Mahasiswa->value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateStudent(array $data, ?string $exceptId = null): void
    {
        $users = app(UserRepository::class);
        $programs = app(StudyProgramRepository::class);
        $errors = [];

        if (blank($data['password'] ?? null) && $exceptId === null) {
            $errors[$this->actionErrorKey('password')] = 'Password wajib diisi.';
        }

        if (! $programs->find((string) ($data['prodi_id'] ?? ''))) {
            $errors[$this->actionErrorKey('prodi_id')] = 'Pilih Prodi yang valid.';
        }

        if (blank($data['class_name'] ?? null)) {
            $errors[$this->actionErrorKey('class_name')] = 'Kelas wajib diisi.';
        }

        if ($users->emailExists((string) ($data['email'] ?? ''), $exceptId)) {
            $errors[$this->actionErrorKey('email')] = 'Email sudah digunakan oleh user lain.';
        }

        if ($users->npmExists((string) ($data['npm'] ?? ''), $exceptId)) {
            $errors[$this->actionErrorKey('npm')] = 'NIM sudah digunakan oleh mahasiswa lain.';
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
