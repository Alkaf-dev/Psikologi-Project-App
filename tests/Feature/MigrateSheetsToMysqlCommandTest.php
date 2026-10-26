<?php

namespace Tests\Feature;

use App\Sheets\MysqlSheetGateway;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateSheetsToMysqlCommandTest extends TestCase
{
    protected string $sourcePath;

    protected string $prefix = 'import_test_sheet_';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = (string) tempnam(sys_get_temp_dir(), 'psikologi-import-');

        config([
            'sheets.driver' => 'mysql',
            'sheets.local_path' => $this->sourcePath,
            'sheets.mysql.table_prefix' => $this->prefix,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (array_keys((array) config('sheets.tabs', [])) as $sheet) {
            Schema::dropIfExists($this->prefix.$sheet);
        }

        if (is_file($this->sourcePath)) {
            @unlink($this->sourcePath);
        }

        parent::tearDown();
    }

    public function test_it_imports_sheet_rows_into_mysql_tables(): void
    {
        $source = [
            'users' => [
                ['id', 'name', 'email', 'active'],
                ['user-1', 'Ayu', 'ayu@psikologi.test', 1],
                ['user-2', 'Bima', 'bima@psikologi.test', 0],
            ],
            'settings' => [
                ['key', 'value'],
                ['program_name', 'Psikologi'],
            ],
        ];

        File::put($this->sourcePath, json_encode($source, JSON_PRETTY_PRINT));

        $this->artisan('sheets:migrate-to-mysql', [
            '--source' => 'local',
            '--fresh' => true,
        ])->assertSuccessful();

        $gateway = new MysqlSheetGateway(null, $this->prefix);

        $userRows = $gateway->read('users');
        $userHeaders = (array) config('sheets.tabs.users');

        $this->assertCount(3, $userRows);
        $this->assertSame('user-1', array_combine($userHeaders, $userRows[1])['id']);
        $this->assertSame('Ayu', array_combine($userHeaders, $userRows[1])['name']);
        $this->assertSame('', array_combine($userHeaders, $userRows[1])['npm']);

        $settingRows = $gateway->read('settings');
        $settingHeaders = (array) config('sheets.tabs.settings');

        $this->assertSame('program_name', array_combine($settingHeaders, $settingRows[1])['key']);
        $this->assertSame('Psikologi', array_combine($settingHeaders, $settingRows[1])['value']);
    }
}
