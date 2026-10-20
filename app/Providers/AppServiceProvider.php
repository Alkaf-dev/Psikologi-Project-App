<?php

namespace App\Providers;

use App\Auth\SheetUserProvider;
use App\Repositories\UserRepository;
use App\Sheets\GoogleSheetsGateway;
use App\Sheets\LocalSheetGateway;
use App\Sheets\SpreadsheetGateway;
use Filament\Support\ArrayRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SpreadsheetGateway::class, function (): SpreadsheetGateway {
            return match ((string) config('sheets.driver', 'local')) {
                'google' => new GoogleSheetsGateway(
                    config('sheets.spreadsheet_id'),
                    config('sheets.credentials_path'),
                ),
                'local' => new LocalSheetGateway(config('sheets.local_path')),
                default => throw new InvalidArgumentException(
                    'SHEETS_DRIVER harus bernilai "google" atau "local".',
                ),
            };
        });
    }

    public function boot(): void
    {
        Auth::provider('sheets', function ($app): SheetUserProvider {
            return new SheetUserProvider($app->make(UserRepository::class));
        });

        ArrayRecord::keyName('id');
    }
}
