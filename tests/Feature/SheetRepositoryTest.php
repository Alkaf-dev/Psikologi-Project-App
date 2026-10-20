<?php

namespace Tests\Feature;

use App\Repositories\UserRepository;
use App\Sheets\LocalSheetGateway;
use App\Sheets\SpreadsheetGateway;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class SheetRepositoryTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets(false);
        Cache::flush();

        app(SpreadsheetGateway::class)->ensureSheet('users', config('sheets.tabs.users'));
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_repository_can_create_update_soft_delete_and_restore_sheet_rows(): void
    {
        $users = app(UserRepository::class);

        $created = $users->create([
            'name' => 'Test Mahasiswa',
            'email' => 'test@psikologi.test',
            'npm' => '9990001',
            'password_hash' => 'hash',
            'role' => 'mahasiswa',
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '0812',
            'active' => '1',
        ]);

        $this->assertNotEmpty($created['id']);
        $this->assertSame('Test Mahasiswa', $users->find($created['id'])['name']);

        $users->update((string) $created['id'], ['name' => 'Nama Baru']);

        $this->assertSame('Nama Baru', $users->find($created['id'])['name']);

        $users->delete((string) $created['id']);

        $this->assertNull($users->find((string) $created['id']));
        $this->assertNotNull($users->find((string) $created['id'], true));

        $users->restore((string) $created['id']);

        $this->assertNotNull($users->find((string) $created['id']));
    }

    public function test_data_is_persisted_to_local_json_file(): void
    {
        $users = app(UserRepository::class);

        $users->create([
            'name' => 'Persisten',
            'email' => 'persisten@psikologi.test',
            'npm' => '9990002',
            'password_hash' => 'hash',
            'role' => 'mahasiswa',
            'class_name' => 'Psikologi A',
            'cohort' => '2024',
            'phone' => '',
            'active' => '1',
        ]);

        Cache::flush();

        $gateway = new LocalSheetGateway($this->sheetPath);
        $rows = $gateway->read('users');

        $this->assertCount(2, $rows);
        $this->assertSame('Persisten', $rows[1][1]);
    }
}
