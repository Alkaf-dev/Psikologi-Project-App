<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseNotificationPage;
use Filament\Support\Icons\Heroicon;

class NotifikasiPage extends BaseNotificationPage
{
    protected static ?string $navigationLabel = 'Notifikasi';

    protected static ?string $title = 'Notifikasi';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|\UnitEnum|null $navigationGroup = 'Akun';

    protected static ?int $navigationSort = 0;
}
