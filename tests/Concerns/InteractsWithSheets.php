<?php

namespace Tests\Concerns;

use App\Sheets\LocalSheetGateway;
use App\Sheets\SpreadsheetGateway;
use App\Support\DemoData;
use Illuminate\Support\Facades\Cache;

trait InteractsWithSheets
{
    protected string $sheetPath;

    protected function setUpSheets(bool $withDemoData = true): void
    {
        $this->sheetPath = tempnam(sys_get_temp_dir(), 'psikologi-sheets-');

        config([
            'sheets.driver' => 'local',
            'sheets.local_path' => $this->sheetPath,
            'sheets.cache_ttl' => 1,
        ]);

        $gateway = new LocalSheetGateway($this->sheetPath);

        $this->app->instance(SpreadsheetGateway::class, $gateway);

        foreach ((array) config('sheets.tabs', []) as $tab => $headers) {
            $gateway->ensureSheet($tab, $headers);
        }

        Cache::flush();

        if ($withDemoData) {
            app(DemoData::class)->seed();
        }
    }

    protected function tearDownSheets(): void
    {
        Cache::flush();

        if (isset($this->sheetPath) && is_file($this->sheetPath)) {
            @unlink($this->sheetPath);
        }
    }
}
