<?php

namespace App\Filament\Pages;

use App\Auth\SheetUser;
use App\Filament\Support\BaseNotificationPage;
use Filament\Support\Icons\Heroicon;

class NotifikasiPage extends BaseNotificationPage
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof SheetUser && $user->role() !== null;
    }

    protected static ?string $navigationLabel = 'Notifikasi';

    protected static ?string $title = 'Notifikasi';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|\UnitEnum|null $navigationGroup = 'Akun';

    protected static ?int $navigationSort = 0;
}
