<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('sheets.mysql.table_prefix', 'sheet_');

        if (Schema::hasTable($prefix.'courses') && ! Schema::hasColumn($prefix.'courses', 'attendance_link')) {
            Schema::table($prefix.'courses', function (Blueprint $table): void {
                $table->longText('attendance_link')->nullable();
            });
        }

        if (Schema::hasTable($prefix.'schedules') && ! Schema::hasColumn($prefix.'schedules', 'attendance_link')) {
            Schema::table($prefix.'schedules', function (Blueprint $table): void {
                $table->longText('attendance_link')->nullable();
            });
        }
    }

    public function down(): void
    {
        $prefix = (string) config('sheets.mysql.table_prefix', 'sheet_');

        if (Schema::hasTable($prefix.'courses') && Schema::hasColumn($prefix.'courses', 'attendance_link')) {
            Schema::table($prefix.'courses', function (Blueprint $table): void {
                $table->dropColumn('attendance_link');
            });
        }

        if (Schema::hasTable($prefix.'schedules') && Schema::hasColumn($prefix.'schedules', 'attendance_link')) {
            Schema::table($prefix.'schedules', function (Blueprint $table): void {
                $table->dropColumn('attendance_link');
            });
        }
    }
};
