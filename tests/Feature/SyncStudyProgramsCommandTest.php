<?php

namespace Tests\Feature;

use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class SyncStudyProgramsCommandTest extends TestCase
{
    use InteractsWithSheets;

    protected string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSheets();
        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'psikologi-prodi-backup-'.uniqid();
    }

    protected function tearDown(): void
    {
        if (isset($this->backupDir) && File::isDirectory($this->backupDir)) {
            File::deleteDirectory($this->backupDir);
        }

        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_it_updates_legacy_program_and_creates_missing_programs_without_breaking_references(): void
    {
        $programs = app(StudyProgramRepository::class);
        $users = app(UserRepository::class);
        $courses = app(CourseRepository::class);

        $legacy = $programs->findByCode('psikologi');
        $this->assertNotNull($legacy);

        $legacyId = (string) $legacy['id'];
        $student = $users->findActiveByLogin('ayu@psikologi.test');
        $course = $courses->findBy('code', 'PSI101');
        $this->assertNotNull($student);
        $this->assertNotNull($course);

        $this->artisan('sheets:sync-study-programs', [
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $this->assertNull($programs->findByCode('psikologi'));

        $psi = $programs->findByCode('psi');
        $this->assertNotNull($psi);
        $this->assertSame($legacyId, (string) $psi['id']);
        $this->assertSame('Psikologi Islam', $psi['name']);
        $this->assertSame('S1', $psi['degree']);
        $this->assertTrue($programs->isActive($psi));

        $hki = $programs->findByCode('hki');
        $es = $programs->findByCode('es');
        $pgmi = $programs->findByCode('pgmi');
        $this->assertNotNull($hki);
        $this->assertNotNull($es);
        $this->assertNotNull($pgmi);
        $this->assertSame('01M60M042VB05KZ8D99P9D6MJE', (string) $hki['id']);
        $this->assertSame('01M60M043GTP41WPV6GWJ61FHH', (string) $es['id']);
        $this->assertSame('01M60M043QTPAWJT4PQJ4HQWE5', (string) $pgmi['id']);

        $this->assertSame(
            ['es', 'hki', 'pgmi', 'psi'],
            $programs->active()->pluck('code')->sort()->values()->all(),
        );

        $this->assertSame($legacyId, (string) $users->find((string) $student['id'])['prodi_id']);
        $this->assertSame($legacyId, (string) $courses->find((string) $course['id'])['prodi_ids']);

        $files = File::files($this->backupDir);
        $this->assertCount(1, $files);

        $backup = json_decode(File::get($files[0]->getPathname()), true);
        $this->assertSame(1, $backup['count']);
        $this->assertSame('psikologi', $backup['programs'][0]['code']);
        $this->assertSame($legacyId, $backup['programs'][0]['id']);
        $this->assertArrayNotHasKey('_row', $backup['programs'][0]);
    }

    public function test_it_is_idempotent(): void
    {
        $programs = app(StudyProgramRepository::class);

        $this->artisan('sheets:sync-study-programs', [
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $psi = $programs->findByCode('psi');
        $hki = $programs->findByCode('hki');
        $es = $programs->findByCode('es');
        $pgmi = $programs->findByCode('pgmi');

        $this->assertNotNull($psi);
        $this->assertNotNull($hki);
        $this->assertNotNull($es);
        $this->assertNotNull($pgmi);

        $updatedAt = (string) $psi['updated_at'];

        $this->artisan('sheets:sync-study-programs', [
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $this->assertSame(4, $programs->active()->count());
        $this->assertSame((string) $psi['id'], (string) $programs->findByCode('psi')['id']);
        $this->assertSame((string) $hki['id'], (string) $programs->findByCode('hki')['id']);
        $this->assertSame((string) $es['id'], (string) $programs->findByCode('es')['id']);
        $this->assertSame((string) $pgmi['id'], (string) $programs->findByCode('pgmi')['id']);
        $this->assertSame($updatedAt, (string) $programs->findByCode('psi')['updated_at']);
    }

    public function test_dry_run_does_not_change_data_or_create_backup(): void
    {
        $programs = app(StudyProgramRepository::class);

        $this->artisan('sheets:sync-study-programs', [
            '--dry-run' => true,
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $this->assertNotNull($programs->findByCode('psikologi'));
        $this->assertNull($programs->findByCode('psi'));
        $this->assertNull($programs->findByCode('hki'));
        $this->assertNull($programs->findByCode('es'));
        $this->assertNull($programs->findByCode('pgmi'));
        $this->assertSame(1, $programs->active()->count());
        $this->assertFalse(File::isDirectory($this->backupDir));
    }

    public function test_it_aborts_when_target_code_is_already_used_by_another_record(): void
    {
        $programs = app(StudyProgramRepository::class);

        $programs->create([
            'code' => 'psi',
            'name' => 'Program Lain',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $this->artisan('sheets:sync-study-programs', [
            '--backup-dir' => $this->backupDir,
        ])->assertExitCode(1);

        $this->assertNotNull($programs->findByCode('psikologi'));
        $this->assertSame('Program Lain', $programs->findByCode('psi')['name']);
        $this->assertNull($programs->findByCode('hki'));
        $this->assertFalse(File::isDirectory($this->backupDir));
    }

    public function test_it_aborts_when_an_extra_active_program_exists(): void
    {
        $programs = app(StudyProgramRepository::class);

        $programs->create([
            'code' => 'lain',
            'name' => 'Program Lain',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $this->artisan('sheets:sync-study-programs', [
            '--backup-dir' => $this->backupDir,
        ])->assertExitCode(1);

        $this->assertNotNull($programs->findByCode('psikologi'));
        $this->assertNull($programs->findByCode('psi'));
        $this->assertNotNull($programs->findByCode('lain'));
        $this->assertFalse(File::isDirectory($this->backupDir));
    }

    public function test_it_restores_a_soft_deleted_target_program(): void
    {
        $programs = app(StudyProgramRepository::class);

        $deleted = $programs->create([
            'code' => 'hki',
            'name' => 'Hukum Keluarga Islam',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $programs->delete((string) $deleted['id']);

        $this->assertNull($programs->findByCode('hki'));

        $this->artisan('sheets:sync-study-programs', [
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $restored = $programs->findByCode('hki');
        $this->assertNotNull($restored);
        $this->assertSame((string) $deleted['id'], (string) $restored['id']);
        $this->assertTrue($programs->isActive($restored));
    }

    public function test_it_generates_a_new_id_when_reference_id_is_already_used_by_another_record(): void
    {
        $programs = app(StudyProgramRepository::class);

        $programs->create([
            'id' => '01M60M042VB05KZ8D99P9D6MJE',
            'code' => 'lain',
            'name' => 'Program Lain',
            'degree' => 'S1',
            'active' => '0',
        ]);

        $this->artisan('sheets:sync-study-programs', [
            '--backup-dir' => $this->backupDir,
        ])->assertSuccessful();

        $hki = $programs->findByCode('hki');
        $this->assertNotNull($hki);
        $this->assertNotSame('01M60M042VB05KZ8D99P9D6MJE', (string) $hki['id']);
        $this->assertSame('lain', $programs->find('01M60M042VB05KZ8D99P9D6MJE')['code']);
    }
}
