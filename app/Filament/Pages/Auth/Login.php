<?php

namespace App\Filament\Pages\Auth;

use App\Repositories\StudyProgramRepository;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('login')
                    ->label('Email atau NPM')
                    ->required()
                    ->autocomplete('username')
                    ->autofocus(),
                $this->getPasswordFormComponent(),
                Select::make('prodi_id')
                    ->label('Prodi')
                    ->options(fn (): array => app(StudyProgramRepository::class)->activeOptions())
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih Prodi'),
            ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return $panel?->getId() === 'admin'
            ? 'Masuk ke Panel Admin'
            : 'Masuk ke Portal Mahasiswa';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return config('app.brand_subtitle').'. Pilih Prodi, lalu gunakan email atau NPM yang terdaftar beserta password.';
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'login' => $data['login'],
            'password' => $data['password'],
            'prodi_id' => $data['prodi_id'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
