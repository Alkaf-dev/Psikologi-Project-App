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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'User';

    protected static ?string $title = 'Manajemen User';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Akun';

    protected static ?int $navigationSort = 1;

    protected array $searchableColumns = ['name', 'npm', 'email', 'role_label', 'prodi_name', 'class_name', 'cohort'];

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

                $users = app(UserRepository::class)->all()->map(function (array $user) use ($programNames): array {
                    $role = UserRole::tryFromLegacy($user['role'] ?? null);

                    $user['role'] = $role?->value ?? '';
                    $user['role_label'] = $role?->label() ?? 'Tidak diketahui';
                    $user['prodi_name'] = $programNames[(string) ($user['prodi_id'] ?? '')] ?? 'Belum diisi';

                    return $user;
                });

                return $this->paginateRecords(
                    $users,
                    $search,
                    $sortColumn,
                    $sortDirection,
                    $page,
                    $recordsPerPage,
                );
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
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
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserRole::tryFromLegacy($state)?->label() ?? $state)
                    ->color(fn (string $state): string => UserRole::tryFromLegacy($state)?->color() ?? 'gray')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('prodi_name')
                    ->label('Prodi')
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
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->placeholder('-'),
                IconColumn::make('active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable()
                    ->alignCenter(),
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
            ->emptyStateHeading('Belum ada user')
            ->emptyStateDescription('Tambahkan user pertama untuk mulai mengelola akses aplikasi.');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah User')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah User')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('2xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $this->validateUser($data);
                $this->prepareUserData($data);

                app(UserRepository::class)->create($data);

                $this->resetTable();

                Notification::make()
                    ->title('User berhasil ditambahkan')
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
                $this->validateUser($data, (string) $record['id']);
                $this->guardLastActiveAdmin($record, $data);
                $this->prepareUserData($data);

                if (blank($data['password'] ?? null)) {
                    unset($data['password']);
                }

                app(UserRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()
                    ->title('Data user diperbarui')
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
            ->modalDescription('Password baru akan langsung aktif. Bagikan password ini kepada pengguna secara aman.')
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
            ->modalHeading('Hapus user?')
            ->modalDescription('Data user akan dihapus dari tampilan. Riwayatnya tetap tersimpan di spreadsheet.')
            ->action(function (array $record): void {
                if ((string) ($record['id'] ?? '') === (string) auth()->id()) {
                    Notification::make()
                        ->title('Tidak dapat menghapus akun sendiri')
                        ->danger()
                        ->send();

                    return;
                }

                if ($this->isLastActiveAdmin($record)) {
                    Notification::make()
                        ->title('Tidak dapat menghapus admin terakhir')
                        ->danger()
                        ->send();

                    return;
                }

                app(UserRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()
                    ->title('User dihapus')
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
            TextInput::make('name')
                ->label('Nama lengkap')
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
            Select::make('role')
                ->label('Role')
                ->options(UserRole::options())
                ->default(UserRole::Mahasiswa->value)
                ->required()
                ->live()
                ->helperText('PJMK memiliki akses panel yang sama dengan Super Admin.'),
            Select::make('prodi_id')
                ->label('Prodi')
                ->options(fn (): array => app(StudyProgramRepository::class)->allOptions())
                ->searchable()
                ->required()
                ->placeholder('Pilih Prodi'),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->helperText('Wajib diisi saat menambah user. Kosongkan jika tidak ingin mengubah password.'),
            TextInput::make('class_name')
                ->label('Kelas')
                ->required(fn (Get $get): bool => $get('role') === UserRole::Mahasiswa->value)
                ->visible(fn (Get $get): bool => $get('role') === UserRole::Mahasiswa->value)
                ->maxLength(50)
                ->datalist(fn (): array => app(UserRepository::class)->classNames()),
            TextInput::make('cohort')
                ->label('Angkatan')
                ->visible(fn (Get $get): bool => $get('role') === UserRole::Mahasiswa->value)
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
    protected function prepareUserData(array &$data): void
    {
        $role = UserRole::tryFromLegacy($data['role'] ?? null);

        if ($role === null) {
            throw ValidationException::withMessages([$this->actionErrorKey('role') => 'Role tidak valid.']);
        }

        if (filled($data['password'] ?? null)) {
            $data['password_hash'] = Hash::make((string) $data['password']);
        }

        unset($data['password']);

        $data['role'] = $role->value;

        if ($role !== UserRole::Mahasiswa) {
            $data['class_name'] = '';
            $data['cohort'] = '';
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateUser(array $data, ?string $exceptId = null): void
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

        $role = UserRole::tryFromLegacy($data['role'] ?? null);

        if ($role === null) {
            $errors[$this->actionErrorKey('role')] = 'Role tidak valid.';
        }

        if ($role === UserRole::Mahasiswa && blank($data['class_name'] ?? null)) {
            $errors[$this->actionErrorKey('class_name')] = 'Kelas wajib diisi untuk Mahasiswa.';
        }

        if ($users->emailExists((string) ($data['email'] ?? ''), $exceptId)) {
            $errors[$this->actionErrorKey('email')] = 'Email sudah digunakan oleh user lain.';
        }

        if ($users->npmExists((string) ($data['npm'] ?? ''), $exceptId)) {
            $errors[$this->actionErrorKey('npm')] = 'NIM sudah digunakan oleh user lain.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $data
     */
    protected function guardLastActiveAdmin(array $record, array $data): void
    {
        if (! $this->isLastActiveAdmin($record)) {
            return;
        }

        $remainsAdmin = UserRole::tryFromLegacy($data['role'] ?? null)?->isAdminPanel() ?? false;
        $remainsActive = filter_var($data['active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($remainsAdmin && $remainsActive) {
            return;
        }

        throw ValidationException::withMessages([
            $this->actionErrorKey($remainsAdmin ? 'active' : 'role') => 'Minimal satu akun admin aktif harus tersedia.',
        ]);
    }

    protected function actionErrorKey(string $field): string
    {
        $statePath = $this->getMountedActionSchema()?->getStatePath();

        return $statePath ? "{$statePath}.{$field}" : $field;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function isLastActiveAdmin(array $record): bool
    {
        $users = app(UserRepository::class);

        return $users->isAdmin($record)
            && $users->isActive($record)
            && ! $users->hasOtherActiveAdmin((string) ($record['id'] ?? ''));
    }
}
