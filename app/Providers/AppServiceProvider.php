<?php

namespace App\Providers;

use App\Auth\SheetUser;
use App\Auth\SheetUserProvider;
use App\Repositories\ApiTokenRepository;
use App\Repositories\UserRepository;
use App\Sheets\GoogleSheetsGateway;
use App\Sheets\LocalSheetGateway;
use App\Sheets\MysqlSheetGateway;
use App\Sheets\SpreadsheetGateway;
use Filament\Support\ArrayRecord;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
                'mysql' => new MysqlSheetGateway(
                    config('sheets.mysql.connection'),
                    (string) config('sheets.mysql.table_prefix', 'sheet_'),
                ),
                default => throw new InvalidArgumentException(
                    'SHEETS_DRIVER harus bernilai "mysql", "google", atau "local".',
                ),
            };
        });
    }

    public function boot(): void
    {
        Auth::provider('sheets', function ($app): SheetUserProvider {
            return new SheetUserProvider($app->make(UserRepository::class));
        });

        Auth::viaRequest('sheet-token', function (Request $request): ?SheetUser {
            $plainTextToken = $request->bearerToken();

            if (! is_string($plainTextToken) || $plainTextToken === '') {
                return null;
            }

            $tokens = $this->app->make(ApiTokenRepository::class);
            $token = $tokens->findValid($plainTextToken);

            if ($token === null) {
                return null;
            }

            $user = (new SheetUserProvider($this->app->make(UserRepository::class)))
                ->retrieveById((string) ($token['user_id'] ?? ''));

            if (! $user instanceof SheetUser || $user->role() === null) {
                return null;
            }

            $request->attributes->set('api_token', $token);
            $tokens->touch($token);

            return $user;
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user('api')?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(max(1, (int) config('api.rate_limit_per_minute', 60)))
                ->by('api:'.$key);
        });

        RateLimiter::for('api-login', function (Request $request): Limit {
            return Limit::perMinute(max(1, (int) config('api.login_rate_limit_per_minute', 10)))
                ->by('api-login:'.$request->ip());
        });

        ArrayRecord::keyName('id');
    }
}
