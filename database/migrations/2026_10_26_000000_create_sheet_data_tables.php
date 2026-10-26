<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('sheets.mysql.table_prefix', 'sheet_');

        foreach ((array) config('sheets.tabs', []) as $sheet => $headers) {
            $tableName = $prefix.$sheet;

            if (Schema::hasTable($tableName)) {
                continue;
            }

            Schema::create($tableName, function (Blueprint $table) use ($headers): void {
                $table->bigIncrements('_row_id');

                foreach ((array) $headers as $header) {
                    $header = trim((string) $header);

                    if ($header === '' || $header === '_row_id') {
                        continue;
                    }

                    $table->longText($header)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $prefix = (string) config('sheets.mysql.table_prefix', 'sheet_');

        foreach (array_keys((array) config('sheets.tabs', [])) as $sheet) {
            Schema::dropIfExists($prefix.$sheet);
        }
    }
};
