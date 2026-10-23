<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresStaffRole;
use App\Repositories\SettingsRepository;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PengaturanPage extends Page
{
    use RequiresStaffRole;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan Aplikasi';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pengaturan';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(SettingsRepository::class)->allKeyed());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identitas Program')
                    ->schema([
                        TextInput::make('program_name')
                            ->label('Nama program studi')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('academic_year')
                            ->label('Tahun akademik')
                            ->placeholder('2026/2027')
                            ->maxLength(20),
                        TextInput::make('semester')
                            ->label('Semester')
                            ->placeholder('Ganjil')
                            ->maxLength(20),
                    ])
                    ->columns(2),
                Section::make('Kontak')
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('Email kontak')
                            ->email()
                            ->maxLength(150),
                        TextInput::make('contact_phone')
                            ->label('Nomor telepon / WhatsApp')
                            ->tel()
                            ->maxLength(30),
                    ])
                    ->columns(2),
                Section::make('Halaman Publik')
                    ->schema([
                        TextInput::make('public_tagline')
                            ->label('Tagline')
                            ->maxLength(180)
                            ->columnSpanFull(),
                        Textarea::make('public_about')
                            ->label('Tentang program')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('portal_note')
                            ->label('Catatan portal mahasiswa')
                            ->maxLength(180)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(SettingsRepository::class)->setMany($data);

        Notification::make()
            ->title('Pengaturan disimpan')
            ->success()
            ->send();
    }
}
