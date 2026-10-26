<?php

namespace Tests\Feature;

use App\Sheets\MysqlSheetGateway;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MysqlSheetGatewayTest extends TestCase
{
    protected MysqlSheetGateway $gateway;

    protected string $prefix = 'gateway_test_sheet_';

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new MysqlSheetGateway(null, $this->prefix);
    }

    protected function tearDown(): void
    {
        foreach (array_keys((array) config('sheets.tabs', [])) as $sheet) {
            Schema::dropIfExists($this->prefix.$sheet);
        }

        parent::tearDown();
    }

    public function test_it_creates_tables_and_round_trips_rows(): void
    {
        $headers = (array) config('sheets.tabs.users');

        $this->assertTrue($this->gateway->ensureSheet('users', $headers));
        $this->assertFalse($this->gateway->ensureSheet('users', $headers));

        $first = array_fill_keys($headers, '');
        $first['id'] = 'user-1';
        $first['name'] = 'Ayu';
        $first['email'] = 'ayu@psikologi.test';
        $first['active'] = true;

        $this->gateway->append('users', array_values($first));

        $second = $first;
        $second['id'] = 'user-2';
        $second['name'] = 'Bima';

        $this->gateway->append('users', array_values($second));

        $rows = $this->gateway->read('users');

        $this->assertCount(3, $rows);
        $this->assertSame($headers, $rows[0]);

        $firstRow = array_combine($headers, $rows[1]);
        $secondRow = array_combine($headers, $rows[2]);

        $this->assertSame('user-1', $firstRow['id']);
        $this->assertSame('1', $firstRow['active']);
        $this->assertSame('user-2', $secondRow['id']);
        $this->assertSame('Bima', $secondRow['name']);

        $first['name'] = 'Ayu Lestari';
        $this->gateway->updateRow('users', 2, array_values($first));

        $rows = $this->gateway->read('users');

        $this->assertSame('Ayu Lestari', array_combine($headers, $rows[1])['name']);
        $this->assertSame('Bima', array_combine($headers, $rows[2])['name']);
    }

    public function test_it_reads_multiple_sheets_and_reports_health(): void
    {
        $programHeaders = (array) config('sheets.tabs.study_programs');

        $this->gateway->ensureSheet('study_programs', $programHeaders);

        $program = array_fill_keys($programHeaders, '');
        $program['id'] = 'prodi-1';
        $program['name'] = 'Psikologi';
        $program['active'] = '1';

        $this->gateway->append('study_programs', array_values($program));

        $batch = $this->gateway->batchRead(['study_programs', 'users']);

        $this->assertCount(2, $batch['study_programs']);
        $this->assertSame([], $batch['users']);

        $health = $this->gateway->healthCheck();

        $this->assertSame('mysql', $health['driver']);
        $this->assertTrue($health['ok']);
        $this->assertSame(1, $health['tabs']['study_programs']);
        $this->assertSame(0, $health['tabs']['users']);
    }
}
