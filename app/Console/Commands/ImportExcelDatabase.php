<?php

namespace App\Console\Commands;

use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Support\SheetRowCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportExcelDatabase extends Command
{
    protected $signature = 'sheets:import-excel
                            {path? : Jalur file Database Psikologiconnect.xlsx}';

    protected $description = 'Impor data Program Studi, Mahasiswa/User, Mata Kuliah, dan Jadwal dari file Excel ke tabel MySQL';

    public function handle(): int
    {
        $filePath = $this->resolveFilePath();

        if (! file_exists($filePath)) {
            $this->error("File Excel tidak ditemukan pada: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Membaca file Excel: {$filePath}");

        $data = $this->extractDataFromExcel($filePath);

        if (empty($data['programs']) || empty($data['users'])) {
            $this->error('Gagal mengekstrak data dari file Excel.');

            return self::FAILURE;
        }

        $now = now()->toIso8601String();
        $defaultPasswordHash = Hash::make('123');

        // 1. Program Studi
        $this->info('Mengimpor Program Studi...');
        $prodiIdMap = [];

        foreach ($data['programs'] as $prog) {
            $code = strtolower(trim((string) $prog['code']));
            $name = trim((string) $prog['name']);

            $existing = DB::table('sheet_study_programs')
                ->where('code', $code)
                ->orWhere('name', $name)
                ->first();

            if (! $existing && $code === 'psi') {
                $existing = DB::table('sheet_study_programs')->where('code', 'psikologi')->first();
            }

            if ($existing) {
                DB::table('sheet_study_programs')
                    ->where('_row_id', $existing->_row_id)
                    ->update([
                        'code' => $code,
                        'name' => $name,
                        'degree' => $prog['degree'] ?? 'S1',
                        'active' => $prog['active'] ?? '1',
                        'updated_at' => $now,
                    ]);
                $prodiIdMap[$code] = (string) $existing->id;
                $prodiIdMap[$name] = (string) $existing->id;
            } else {
                $id = (string) Str::ulid();
                DB::table('sheet_study_programs')->insert([
                    'id' => $id,
                    'code' => $code,
                    'name' => $name,
                    'degree' => $prog['degree'] ?? 'S1',
                    'active' => $prog['active'] ?? '1',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => '',
                ]);
                $prodiIdMap[$code] = $id;
                $prodiIdMap[$name] = $id;
            }
        }

        $prodiIdMap['Psikologi Islam'] = $prodiIdMap['psi'] ?? '';
        $prodiIdMap['Hukum Keluarga Islam'] = $prodiIdMap['hki'] ?? '';
        $prodiIdMap["Ekonomi Syari'ah"] = $prodiIdMap['es'] ?? '';
        $prodiIdMap['Ekonomi Syariah'] = $prodiIdMap['es'] ?? '';
        $prodiIdMap['Pendidikan Guru Madrasah Ibtidaiyah'] = $prodiIdMap['pgmi'] ?? '';

        // 2. Users
        $this->info('Mengimpor Mahasiswa / Pengguna...');
        DB::table('sheet_users')
            ->whereIn('role', ['super_admin', 'admin', 'pjmk'])
            ->update(['password_hash' => $defaultPasswordHash]);

        $existingUsers = DB::table('sheet_users')->get();
        $existingUserMap = [];
        foreach ($existingUsers as $u) {
            $existingUserMap[strtolower(trim((string) $u->name))] = $u;
        }

        $importedUserCount = 0;
        foreach ($data['users'] as $u) {
            $name = trim((string) $u['name']);
            $email = trim((string) $u['email']);
            $nick = trim((string) ($u['nick'] ?? ''));
            $prodiCode = $u['prodi_code'] ?? 'psi';
            $prodiId = $prodiIdMap[$prodiCode] ?? ($prodiIdMap[$u['prodi_name'] ?? ''] ?? '');

            $matchedExisting = $existingUserMap[$nick] ?? ($existingUserMap[strtolower($name)] ?? null);

            if ($matchedExisting && in_array($matchedExisting->role, ['pjmk', 'admin', 'super_admin'], true)) {
                DB::table('sheet_users')
                    ->where('_row_id', $matchedExisting->_row_id)
                    ->update([
                        'name' => $name,
                        'email' => $email,
                        'npm' => $u['npm'] !== '' ? $u['npm'] : $matchedExisting->npm,
                        'password_hash' => $defaultPasswordHash,
                        'class_name' => $u['class_name'] ?? '',
                        'cohort' => $u['cohort'] ?? '2026',
                        'phone' => $u['phone'] !== '' ? $u['phone'] : $matchedExisting->phone,
                        'active' => '1',
                        'prodi_id' => $prodiId,
                        'updated_at' => $now,
                    ]);
            } else {
                $existingUser = DB::table('sheet_users')
                    ->where('email', $email)
                    ->orWhere('name', $name)
                    ->first();

                if ($existingUser) {
                    DB::table('sheet_users')
                        ->where('_row_id', $existingUser->_row_id)
                        ->update([
                            'name' => $name,
                            'email' => $email,
                            'npm' => $u['npm'] ?? '',
                            'password_hash' => $defaultPasswordHash,
                            'role' => $existingUser->role ?: 'mahasiswa',
                            'class_name' => $u['class_name'] ?? '',
                            'cohort' => $u['cohort'] ?? '2026',
                            'phone' => $u['phone'] ?? '',
                            'active' => '1',
                            'prodi_id' => $prodiId,
                            'updated_at' => $now,
                        ]);
                } else {
                    $id = (string) Str::ulid();
                    DB::table('sheet_users')->insert([
                        'id' => $id,
                        'name' => $name,
                        'email' => $email,
                        'npm' => $u['npm'] ?? '',
                        'password_hash' => $defaultPasswordHash,
                        'role' => 'mahasiswa',
                        'class_name' => $u['class_name'] ?? '',
                        'cohort' => $u['cohort'] ?? '2026',
                        'phone' => $u['phone'] ?? '',
                        'active' => '1',
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => '',
                        'prodi_id' => $prodiId,
                    ]);
                }
            }
            $importedUserCount++;
        }

        // 3. Courses
        $this->info('Mengimpor Mata Kuliah...');
        $createdCourses = [];
        foreach ($data['courses'] as $c) {
            $prodiIds = [];
            foreach ((array) ($c['prodi_names'] ?? []) as $pName) {
                if (isset($prodiIdMap[$pName]) && $prodiIdMap[$pName] !== '') {
                    $prodiIds[] = $prodiIdMap[$pName];
                }
            }
            $prodiIdsStr = implode(',', array_unique($prodiIds));

            $existingCourse = DB::table('sheet_courses')
                ->where('name', $c['name'])
                ->where('class_name', $c['class_name'])
                ->first();

            if ($existingCourse) {
                DB::table('sheet_courses')
                    ->where('_row_id', $existingCourse->_row_id)
                    ->update([
                        'code' => $c['code'],
                        'name' => $c['name'],
                        'credits' => $c['credits'] ?? 2,
                        'lecturer' => $c['lecturer'],
                        'semester' => $c['semester'],
                        'class_name' => $c['class_name'],
                        'description' => $c['name'],
                        'prodi_ids' => $prodiIdsStr,
                        'meeting_link' => $c['meeting_link'] ?? '',
                        'updated_at' => $now,
                    ]);
                $courseId = (string) $existingCourse->id;
            } else {
                $courseId = (string) Str::ulid();
                DB::table('sheet_courses')->insert([
                    'id' => $courseId,
                    'code' => $c['code'],
                    'name' => $c['name'],
                    'credits' => $c['credits'] ?? 2,
                    'lecturer' => $c['lecturer'],
                    'semester' => $c['semester'],
                    'class_name' => $c['class_name'],
                    'description' => $c['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => '',
                    'prodi_ids' => $prodiIdsStr,
                    'meeting_link' => $c['meeting_link'] ?? '',
                ]);
            }

            $createdCourses[$c['name']][$c['class_name']] = $courseId;

            $otherClasses = [];
            if (in_array('Hukum Keluarga Islam', (array) $c['prodi_names'], true) && isset($prodiIdMap['hki'])) {
                $otherClasses['Hukum Keluarga Islam 1B'] = $prodiIdMap['hki'];
            }
            if (in_array("Ekonomi Syari'ah", (array) $c['prodi_names'], true) && isset($prodiIdMap['es'])) {
                $otherClasses["Ekonomi Syari'ah 1B"] = $prodiIdMap['es'];
            }

            foreach ($otherClasses as $otherClassName => $singleProdiId) {
                $existingOther = DB::table('sheet_courses')
                    ->where('name', $c['name'])
                    ->where('class_name', $otherClassName)
                    ->first();

                if ($existingOther) {
                    DB::table('sheet_courses')
                        ->where('_row_id', $existingOther->_row_id)
                        ->update([
                            'code' => $c['code'],
                            'name' => $c['name'],
                            'credits' => $c['credits'] ?? 2,
                            'lecturer' => $c['lecturer'],
                            'semester' => $c['semester'],
                            'class_name' => $otherClassName,
                            'description' => $c['name'],
                            'prodi_ids' => $singleProdiId,
                            'meeting_link' => $c['meeting_link'] ?? '',
                            'updated_at' => $now,
                        ]);
                    $createdCourses[$c['name']][$otherClassName] = (string) $existingOther->id;
                } else {
                    $otherId = (string) Str::ulid();
                    DB::table('sheet_courses')->insert([
                        'id' => $otherId,
                        'code' => $c['code'],
                        'name' => $c['name'],
                        'credits' => $c['credits'] ?? 2,
                        'lecturer' => $c['lecturer'],
                        'semester' => $c['semester'],
                        'class_name' => $otherClassName,
                        'description' => $c['name'],
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => '',
                        'prodi_ids' => $singleProdiId,
                        'meeting_link' => $c['meeting_link'] ?? '',
                    ]);
                    $createdCourses[$c['name']][$otherClassName] = $otherId;
                }
            }
        }

        // 4. Schedules
        $this->info('Mengimpor Jadwal...');
        foreach ($data['schedules'] as $s) {
            $mkName = $s['course_name'];
            if (! isset($createdCourses[$mkName])) {
                continue;
            }

            foreach ($createdCourses[$mkName] as $className => $courseId) {
                $existingSched = DB::table('sheet_schedules')
                    ->where('course_id', $courseId)
                    ->where('day', $s['day'])
                    ->where('class_name', $className)
                    ->first();

                if ($existingSched) {
                    DB::table('sheet_schedules')
                        ->where('_row_id', $existingSched->_row_id)
                        ->update([
                            'day' => $s['day'],
                            'start_time' => $s['start_time'],
                            'end_time' => $s['end_time'],
                            'room' => '',
                            'class_name' => $className,
                            'mode' => $s['mode'],
                            'platform' => $s['platform'],
                            'meeting_link' => $s['meeting_link'] ?? '',
                            'updated_at' => $now,
                        ]);
                } else {
                    $schedId = (string) Str::ulid();
                    DB::table('sheet_schedules')->insert([
                        'id' => $schedId,
                        'course_id' => $courseId,
                        'day' => $s['day'],
                        'start_time' => $s['start_time'],
                        'end_time' => $s['end_time'],
                        'room' => '',
                        'class_name' => $className,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => '',
                        'mode' => $s['mode'],
                        'platform' => $s['platform'],
                        'meeting_link' => $s['meeting_link'] ?? '',
                    ]);
                }
            }
        }

        app(SheetRowCache::class)->flush();
        app(UserRepository::class)->flushCache();
        app(StudyProgramRepository::class)->flushCache();

        $this->info("Berhasil mengimpor data! {$importedUserCount} mahasiswa/pengguna, 4 program studi, mata kuliah dan jadwal.");

        return self::SUCCESS;
    }

    protected function resolveFilePath(): string
    {
        $arg = $this->argument('path');
        if ($arg && file_exists((string) $arg)) {
            return (string) $arg;
        }

        $userProfile = getenv('USERPROFILE') ?: '';
        $candidates = [
            $userProfile.'\\OneDrive\\Desktop\\Database Psikologiconnect.xlsx',
            $userProfile.'\\Desktop\\Database Psikologiconnect.xlsx',
            base_path('Database Psikologiconnect.xlsx'),
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        return $candidates[0];
    }

    /**
     * @return array{programs: list<array<string, mixed>>, users: list<array<string, mixed>>, courses: list<array<string, mixed>>, schedules: list<array<string, mixed>>}
     */
    protected function extractDataFromExcel(string $filePath): array
    {
        $pythonScript = <<<'PY'
import sys, json, openpyxl, re

wb = openpyxl.load_workbook(sys.argv[1], data_only=True)

# 1. Programs
ws_ps = wb['Program Studi']
programs = []
for r in list(ws_ps.iter_rows(values_only=True))[1:]:
    if not r or not r[0]: continue
    code = str(r[0]).strip().lower()
    name = str(r[1]).strip()
    status = str(r[4]).strip() if len(r) > 4 and r[4] else 'Aktif'
    active = '1' if status.lower() in ['aktif', 'active', '1', 'true'] else '0'
    programs.append({'code': code, 'name': name, 'degree': 'S1', 'active': active})

prodi_code_map = {
    'Psikologi Islam': 'psi',
    'Hukum Keluarga Islam': 'hki',
    "Ekonomi Syari'ah": 'es',
    'Ekonomi Syariah': 'es',
    'Pendidikan Guru Madrasah Ibtidaiyah': 'pgmi',
}

def clean_nick(nick, name):
    if not nick: nick = name.split()[0] if name else 'user'
    nick = str(nick).strip()
    if '/' in nick:
        parts = [p.strip() for p in nick.split('/') if p.strip()]
        nick = parts[0]
    words = nick.split()
    if len(words) > 1: nick = words[0]
    nick = re.sub(r'[^a-zA-Z0-9]', '', nick).lower()
    return nick

# 2. Users
ws_user = wb['User']
users = []
seen_emails = set()

for r in list(ws_user.iter_rows(values_only=True))[1:]:
    if not r or r[0] is None or r[1] is None: continue
    no, name, raw_nick, raw_nim, raw_kelas, raw_prodi, raw_hp = r[0], str(r[1]).strip(), r[2], r[3], r[4], str(r[5]).strip(), r[6]
    raw_status = r[9] if len(r) > 9 else 'Aktif'

    class_name, cohort = '', '2026'
    if raw_kelas:
        parts = [p.strip() for p in str(raw_kelas).split('\n') if p.strip()]
        if len(parts) >= 1: class_name = parts[0]
        if len(parts) >= 2:
            m = re.search(r'\d{4}', parts[1])
            if m: cohort = m.group(0)

    nick = clean_nick(raw_nick, name)
    code = prodi_code_map.get(raw_prodi, 'psi')
    email = f"{nick}@{code}.id"

    base_email, counter = email, 1
    while email in seen_emails:
        counter += 1
        email = f"{nick}{counter}@{code}.id"
    seen_emails.add(email)

    phone = str(raw_hp).strip() if raw_hp and str(raw_hp).strip() not in ['-', ''] else ''
    npm = str(raw_nim).strip() if raw_nim and str(raw_nim).strip() not in ['-', ''] else ''
    status_str = str(raw_status).strip() if raw_status else 'Aktif'
    active = '1' if status_str.lower() in ['aktif', 'active', '1', 'true'] else '0'

    users.append({
        'no': no, 'name': name, 'nick': nick, 'email': email, 'npm': npm,
        'class_name': class_name, 'cohort': cohort, 'prodi_name': raw_prodi,
        'prodi_code': code, 'phone': phone, 'active': active
    })

# 3. Courses
ws_mk = wb['Mata Kuliah']
courses = []
course_code_map = {
    'B. Arab': 'ARB101', 'Sejarah Aliran Psikologi': 'SAP101', 'B. Indonesia': 'BIN101',
    "Studi Al-Qur'an": 'SAQ101', 'B. Inggris': 'BIG101', 'Pengantar Filsafat': 'PFL101',
    'Biopsikologi': 'BIO101', 'AIK': 'AIK101', 'PPKn': 'PKN101', 'Psikologi Umum': 'PUM101',
}

for r in list(ws_mk.iter_rows(values_only=True))[1:]:
    if not r or not r[0]: continue
    mk_name, lecturer = str(r[0]).strip(), str(r[1]).strip()
    raw_sem_kelas = str(r[2]).strip()
    tautan = str(r[3]).strip() if r[3] and str(r[3]).strip() != '-' else ''
    prodi_names = [p.strip() for p in str(r[4]).split(',') if p.strip()]

    sem_parts = [p.strip() for p in raw_sem_kelas.split('\n') if p.strip()]
    semester = sem_parts[0] if len(sem_parts) > 0 else 'Ganjil'
    class_name = sem_parts[1] if len(sem_parts) > 1 else 'Psikologi 1B'
    code = course_code_map.get(mk_name, re.sub(r'[^A-Z0-9]', '', mk_name.upper())[:6])

    courses.append({
        'code': code, 'name': mk_name, 'credits': 2, 'lecturer': lecturer,
        'semester': semester, 'class_name': class_name, 'meeting_link': tautan,
        'prodi_names': prodi_names
    })

# 4. Schedules
ws_j = wb['Jadwal']
schedules = []
for r in list(ws_j.iter_rows(values_only=True))[1:]:
    if not r or not r[0]: continue
    mk_name, raw_waktu, raw_mode = str(r[0]).strip(), str(r[1]).strip(), str(r[2]).strip()
    tautan = str(r[3]).strip() if r[3] and str(r[3]).strip() != '-' else ''

    waktu_parts = [p.strip() for p in raw_waktu.split('\n') if p.strip()]
    day = waktu_parts[0] if len(waktu_parts) > 0 else 'Senin'
    time_str = waktu_parts[1] if len(waktu_parts) > 1 else '18.00 - 19.00'
    start_time, end_time = '18:00', '19:00'
    if '-' in time_str:
        st, et = time_str.split('-', 1)
        start_time, end_time = st.strip().replace('.', ':'), et.strip().replace('.', ':')

    mode_parts = [p.strip().lower() for p in raw_mode.split('\n') if p.strip()]
    mode = 'online' if 'online' in mode_parts else 'offline'
    platform = 'zoom' if 'zoom' in mode_parts else ('google_meet' if 'meet' in mode_parts else '')

    schedules.append({
        'course_name': mk_name, 'day': day, 'start_time': start_time,
        'end_time': end_time, 'mode': mode, 'platform': platform,
        'meeting_link': tautan
    })

print(json.dumps({'programs': programs, 'users': users, 'courses': courses, 'schedules': schedules}))
PY;

        $tempPy = tempnam(sys_get_temp_dir(), 'exc_');
        file_put_contents($tempPy, $pythonScript);

        $command = 'python '.escapeshellarg($tempPy).' '.escapeshellarg($filePath);
        $output = shell_exec($command);
        @unlink($tempPy);

        if (! is_string($output)) {
            return ['programs' => [], 'users' => [], 'courses' => [], 'schedules' => []];
        }

        $decoded = json_decode($output, true);

        return is_array($decoded) ? $decoded : ['programs' => [], 'users' => [], 'courses' => [], 'schedules' => []];
    }
}
