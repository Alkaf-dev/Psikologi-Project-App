<?php

namespace App\Console\Commands;

use App\Sheets\SpreadsheetGateway;
use Illuminate\Console\Command;
use Throwable;

class CheckSheets extends Command
{
    protected $signature = 'sheets:check';

    protected $description = 'Periksa koneksi, tab, dan jumlah baris spreadsheet';

    public function handle(SpreadsheetGateway $gateway): int
    {
        $this->components->info('Driver: '.config('sheets.driver'));

        $health = $gateway->healthCheck();

        if (isset($health['error'])) {
            $this->components->error($health['error']);

            if (($health['service_account_email'] ?? null) !== null) {
                $this->line('Service account: '.$health['service_account_email']);
            }

            return self::FAILURE;
        }

        if (($health['driver'] ?? null) === 'local') {
            $this->line('File data: '.($health['path'] ?? '-'));
        } elseif (($health['driver'] ?? null) === 'mysql') {
            $this->line('Koneksi: '.($health['connection'] ?? '-'));
            $this->line('Database: '.($health['database'] ?? '-'));
        } else {
            $this->line('Spreadsheet: '.($health['title'] ?? '-'));
            $this->line('URL: '.($health['spreadsheet_url'] ?? '-'));
            $this->line('Service account: '.($health['service_account_email'] ?? '-'));
        }

        $availableTabs = $health['tabs'] ?? [];
        $rows = [];

        foreach ((array) config('sheets.tabs', []) as $tab => $headers) {
            $exists = is_array($availableTabs) && (
                array_is_list($availableTabs)
                    ? in_array($tab, $availableTabs, true)
                    : array_key_exists($tab, $availableTabs)
            );

            $count = 0;
            $status = $exists ? 'OK' : 'BELUM ADA';

            if ($exists) {
                try {
                    $sheetRows = $gateway->read($tab);
                    $count = max(0, count($sheetRows) - 1);

                    $actualHeaders = empty($sheetRows)
                        ? []
                        : array_values(array_filter(
                            array_map(fn (mixed $header): string => trim((string) $header), (array) $sheetRows[0]),
                            fn (string $header): bool => $header !== '',
                        ));

                    $missingHeaders = array_values(array_diff($headers, $actualHeaders));

                    if ($missingHeaders !== []) {
                        $status = 'HEADER KURANG: '.implode(', ', $missingHeaders);
                    }
                } catch (Throwable) {
                    $status = 'GAGAL BACA';
                }
            }

            $rows[] = [
                $tab,
                count($headers),
                $exists ? $count : '-',
                $status,
            ];
        }

        $this->table(['Tab', 'Header', 'Baris', 'Status'], $rows);

        $missing = array_filter($rows, fn (array $row): bool => $row[3] !== 'OK');

        if ($missing !== []) {
            $this->components->warn('Jalankan php artisan sheets:setup untuk membuat tab atau melengkapi header yang belum ada.');

            return self::FAILURE;
        }

        $this->components->info('Spreadsheet siap digunakan.');

        return self::SUCCESS;
    }
}
