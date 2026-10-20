<?php

namespace Tests\Feature;

use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Sheets\LocalSheetGateway;
use App\Sheets\SpreadsheetGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class SetupSheetsTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets(false);
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_setup_adds_new_headers_without_moving_existing_user_data(): void
    {
        $legacyHeaders = [
            'id', 'name', 'email', 'npm', 'password_hash', 'role', 'class_name',
            'cohort', 'phone', 'active', 'created_at', 'updated_at', 'deleted_at',
        ];

        $legacyAdmin = [
            'ADMIN-LAMA',
            'Admin Lama',
            'admin.lama@example.test',
            'ADMIN-LAMA',
            Hash::make('password'),
            'admin',
            '',
            '',
            '',
            '1',
            now()->toIso8601String(),
            now()->toIso8601String(),
            '',
        ];

        $gateway = app(SpreadsheetGateway::class);

        $this->assertInstanceOf(LocalSheetGateway::class, $gateway);

        $gateway->seed('users', [$legacyHeaders, $legacyAdmin]);
        Cache::flush();

        $this->artisan('sheets:setup')->assertSuccessful();

        $rows = $gateway->read('users');

        $this->assertSame('prodi_id', $rows[0][13]);
        $this->assertSame('Admin Lama', $rows[1][1]);

        $admin = app(UserRepository::class)->find('ADMIN-LAMA');

        $this->assertNotNull($admin);
        $this->assertSame('super_admin', $admin['role']);
        $this->assertSame('', $admin['prodi_id']);
        $this->assertNotNull(app(StudyProgramRepository::class)->findByCode('psikologi'));
    }

    public function test_setup_demo_creates_super_admin_pjmk_and_student_accounts(): void
    {
        $this->artisan('sheets:setup', ['--demo' => true])->assertSuccessful();

        $users = app(UserRepository::class);

        $this->assertSame('super_admin', $users->findActiveByLogin('admin@psikologi.test')['role']);
        $this->assertSame('pjmk', $users->findActiveByLogin('pjmk@psikologi.test')['role']);
        $this->assertSame('mahasiswa', $users->findActiveByLogin('ayu@psikologi.test')['role']);
    }
}
