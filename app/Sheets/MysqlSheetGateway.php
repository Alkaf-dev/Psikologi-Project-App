<?php

namespace App\Sheets;

use App\Support\SheetValue;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class MysqlSheetGateway implements SpreadsheetGateway
{
    public function __construct(
        protected ?string $connection = null,
        protected ?string $tablePrefix = null,
    ) {
        $this->tablePrefix ??= (string) config('sheets.mysql.table_prefix', 'sheet_');
    }

    public function read(string $sheet): array
    {
        $table = $this->tableName($sheet);

        if (! $this->schema()->hasTable($table)) {
            return [];
        }

        $headers = $this->headers($sheet);
        $rows = $this->db()->table($table)->orderBy('_row_id')->get();

        $result = [$headers];

        foreach ($rows as $row) {
            $result[] = array_map(
                fn (string $header): string => SheetValue::normalize($row->{$header} ?? ''),
                $headers,
            );
        }

        return $result;
    }

    public function append(string $sheet, array $values): void
    {
        $table = $this->requireTable($sheet);

        $this->db()->table($table)->insert($this->recordFor($sheet, $values));
    }

    public function updateRow(string $sheet, int $rowNumber, array $values): void
    {
        if ($rowNumber < 1) {
            throw new InvalidArgumentException("Baris [{$rowNumber}] tidak valid pada sheet [{$sheet}].");
        }

        if ($rowNumber === 1) {
            $this->ensureSheet($sheet, array_values($values));

            return;
        }

        $table = $this->requireTable($sheet);

        $rowId = $this->db()->table($table)
            ->orderBy('_row_id')
            ->offset($rowNumber - 2)
            ->limit(1)
            ->value('_row_id');

        if ($rowId === null) {
            throw new InvalidArgumentException("Baris [{$rowNumber}] tidak ditemukan pada sheet [{$sheet}].");
        }

        $this->db()->table($table)
            ->where('_row_id', $rowId)
            ->update($this->recordFor($sheet, $values));
    }

    public function batchRead(array $sheets): array
    {
        $result = [];

        foreach ($sheets as $sheet) {
            $result[$sheet] = $this->read($sheet);
        }

        return $result;
    }

    public function ensureSheet(string $sheet, array $headers): bool
    {
        $table = $this->tableName($sheet);
        $headers = $this->cleanHeaders($headers);

        if (! $this->schema()->hasTable($table)) {
            $this->schema()->create($table, function (Blueprint $table) use ($headers): void {
                $table->bigIncrements('_row_id');

                foreach ($headers as $header) {
                    if ($header === '_row_id') {
                        continue;
                    }

                    $table->longText($header)->nullable();
                }
            });

            return true;
        }

        $existing = $this->schema()->getColumnListing($table);
        $missing = array_values(array_diff($headers, $existing));

        if ($missing === []) {
            return false;
        }

        $this->schema()->table($table, function (Blueprint $table) use ($missing): void {
            foreach ($missing as $header) {
                if ($header === '_row_id') {
                    continue;
                }

                $table->longText($header)->nullable();
            }
        });

        return true;
    }

    public function healthCheck(): array
    {
        $tabs = [];

        foreach (array_keys((array) config('sheets.tabs', [])) as $sheet) {
            $table = $this->tableName($sheet);
            $tabs[$sheet] = $this->schema()->hasTable($table)
                ? $this->db()->table($table)->count()
                : 0;
        }

        return [
            'driver' => 'mysql',
            'ok' => true,
            'connection' => $this->connection ?? (string) config('database.default'),
            'database' => $this->db()->getDatabaseName(),
            'tabs' => $tabs,
        ];
    }

    public function serviceAccountEmail(): ?string
    {
        return null;
    }

    public function spreadsheetUrl(): ?string
    {
        return null;
    }

    public function tableName(string $sheet): string
    {
        if (! array_key_exists($sheet, (array) config('sheets.tabs', []))) {
            throw new InvalidArgumentException("Sheet [{$sheet}] tidak dikenal.");
        }

        return $this->tablePrefix.preg_replace('/[^A-Za-z0-9_]/', '_', $sheet);
    }

    protected function requireTable(string $sheet): string
    {
        $table = $this->tableName($sheet);

        if (! $this->schema()->hasTable($table)) {
            throw new InvalidArgumentException("Sheet [{$sheet}] belum dibuat. Jalankan php artisan sheets:setup.");
        }

        return $table;
    }

    /**
     * @return array<int, string>
     */
    protected function headers(string $sheet): array
    {
        return $this->cleanHeaders((array) config("sheets.tabs.{$sheet}", []));
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    protected function cleanHeaders(array $headers): array
    {
        return array_values(array_map(
            fn (mixed $header): string => trim((string) $header),
            $headers,
        ));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<string, string>
     */
    protected function recordFor(string $sheet, array $values): array
    {
        $values = array_values($values);
        $record = [];

        foreach ($this->headers($sheet) as $index => $header) {
            $record[$header] = SheetValue::normalize($values[$index] ?? '');
        }

        return $record;
    }

    protected function schema(): Builder
    {
        return Schema::connection($this->connection);
    }

    protected function db(): Connection
    {
        return DB::connection($this->connection);
    }
}
