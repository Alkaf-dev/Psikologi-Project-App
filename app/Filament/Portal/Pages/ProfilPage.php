<?php

namespace App\Filament\Portal\Pages;

use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

class ProfilPage extends Page
{
    protected static ?string $navigationLabel = 'Profil Saya';

    protected static ?string $title = 'Profil Saya';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|\UnitEnum|null $navigationGroup = 'Akun';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.portal.pages.profil';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $student = app(UserRepository::class)->find((string) auth()->id());

        $this->form->fill([
            'name' => $student['name'] ?? '',
            'npm' => $student['npm'] ?? '',
            'email' => $student['email'] ?? '',
            'prodi' => app(StudyProgramRepository::class)->nameById($student['prodi_id'] ?? null),
            'class_name' => $student['class_name'] ?? '',
            'cohort' => $student['cohort'] ?? '',
            'phone' => $student['phone'] ?? '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Data Mahasiswa')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama lengkap')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('npm')
                            ->label('NPM')
                            ->disabled(),
                        TextInput::make('email')
                            ->label('Email')
                            ->disabled(),
                        TextInput::make('prodi')
                            ->label('Prodi')
                            ->disabled(),
                        TextInput::make('class_name')
                            ->label('Kelas')
                            ->disabled(),
                        TextInput::make('cohort')
                            ->label('Angkatan')
                            ->disabled(),
                        TextInput::make('phone')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->maxLength(30),
                    ])
                    ->columns(2),
                Section::make('Ubah Password')
                    ->description('Kosongkan bagian ini jika tidak ingin mengganti password.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Password saat ini')
                            ->password()
                            ->revealable(),
                        TextInput::make('new_password')
                            ->label('Password baru')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->rules(['confirmed']),
                        TextInput::make('new_password_confirmation')
                            ->label('Ulangi password baru')
                            ->password()
                            ->revealable(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $userId = (string) auth()->id();

        $users = app(UserRepository::class);
        $student = $users->find($userId);

        if (! $student) {
            Notification::make()->title('Data mahasiswa tidak ditemukan')->danger()->send();

            return;
        }

        $payload = [
            'name' => (string) $data['name'],
            'phone' => (string) ($data['phone'] ?? ''),
        ];

        if (filled($data['new_password'] ?? null)) {
            if (! Hash::check((string) ($data['current_password'] ?? ''), (string) ($student['password_hash'] ?? ''))) {
                $this->addError('data.current_password', 'Password saat ini tidak sesuai.');

                return;
            }

            $payload['password_hash'] = Hash::make((string) $data['new_password']);
        }

        $users->update($userId, $payload);

        $this->data['current_password'] = '';
        $this->data['new_password'] = '';
        $this->data['new_password_confirmation'] = '';

        Notification::make()
            ->title('Profil berhasil diperbarui')
            ->success()
            ->send();
    }
}
