<?php

namespace App\Console\Commands;

use App\Sheets\GoogleSheetsGateway;
use App\Sheets\LocalSheetGateway;
use App\Sheets\MysqlSheetGateway;
use App\Sheets\SpreadsheetGateway;
use App\Support\SheetValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateSheetsToMysql extends Command
{
    protected $signature = 'sheets:migrate-to-mysql
                            {--source= : Sumber data: local atau google}
                            {--fresh : Kosongkan tabel tujuan sebelum impor}';

    protected $description = 'Migrasikan data spreadsheet ke tabel MySQL';

    public function handle(): int
    {
        $sourceName = $this->sourceName();

        $source = $this->sourceGateway($sourceName);

        if ($source === null) {
            return self::FAILURE;
        }

        $destination = new MysqlSheetGateway(
            config('sheets.mysql.connection'),
            (string) config('sheets.mysql.table_prefix', 'sheet_'),
        );

        $connection = config('sheets.mysql.connection');
        $fresh = (bool) $this->option('fresh');
        $report = [];

        try {
            foreach ((array) config('sheets.tabs', []) as $sheet => $headers) {
                $destination->ensureSheet($sheet, $headers);

                $table = $destination->tableName($sheet);
                $query = DB::connection($connection)->table($table);
                $existing = $query->count();

                if ($existing > 0 && ! $fresh) {
                    $report[] = [$sheet, $sourceName, 0, "Dilewati ({$existing} baris sudah ada)"];

                    continue;
                }

                if ($fresh) {
                    $query->truncate();
                }

                $sourceRows = $source->read($sheet);

                if ($sourceRows === []) {
                    $report[] = [$sheet, $sourceName, 0, 'Sumber kosong'];

                    continue;
                }

                $records = $this->records($sourceRows, (array) $headers);

                foreach (array_chunk($records, 500) as $chunk) {
                    DB::connection($connection)->table($table)->insert($chunk);
                }

                Cache::forget("sheet:{$sheet}");

                $report[] = [$sheet, $sourceName, count($records), 'OK'];
            }
        } catch (Throwable $exception) {
            $this->components->error('Migrasi gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Tab', 'Sumber', 'Baris', 'Status'], $report);

        $this->components->info('Migrasi spreadsheet ke MySQL selesai.');
        $this->line('Aktifkan driver MySQL dengan SHEETS_DRIVER=mysql di .env, lalu jalankan php artisan config:clear.');

        return self::SUCCESS;
    }

    protected function sourceName(): string
    {
        $source = mb_strtolower(trim((string) $this->option('source')));

        if ($source !== '') {
            return $source;
        }

        $driver = mb_strtolower(trim((string) config('sheets.driver', 'mysql')));

        return $driver === 'mysql' ? 'local' : $driver;
    }

    protected function sourceGateway(string $source): ?SpreadsheetGateway
    {
        return match ($source) {
            'local' => new LocalSheetGateway(config('sheets.local_path')),
            'google' => new GoogleSheetsGateway(
                config('sheets.spreadsheet_id'),
                config('sheets.credentials_path'),
            ),
            default => $this->invalidSource($source),
        };
    }

    protected function invalidSource(string $source): null
    {
        $this->components->error("Sumber [{$source}] tidak valid. Gunakan local atau google.");

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $sourceRows
     * @param  array<int, string>  $headers
     * @return array<int, array<string, string>>
     */
    protected function records(array $sourceRows, array $headers): array
    {
        $sourceHeaders = array_map(
            fn (mixed $header): string => trim((string) $header),
            array_values((array) ($sourceRows[0] ?? [])),
        );

        $positions = [];

        foreach ($sourceHeaders as $index => $header) {
            if ($header !== '') {
                $positions[$header] = $index;
            }
        }

        $records = [];

        foreach (array_slice($sourceRows, 1) as $row) {
            $row = array_values((array) $row);

            if (SheetValue::isEmptyRow($row)) {
                continue;
            }

            $record = [];

            foreach ($headers as $header) {
                $index = $positions[$header] ?? null;
                $record[$header] = SheetValue::normalize(
                    $index === null ? '' : ($row[$index] ?? ''),
                );
            }

            $records[] = $record;
        }

        return $records;
    }
}
