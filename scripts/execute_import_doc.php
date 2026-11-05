<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$psiProdiId = '01M60CY00TAFA0HV21NX7JFNDF';
$pgmiProdiId = '01M60M043QTPAWJT4PQJ4HQWE5';
$courseId = '01M60M04F890QPY1RZDA8JKSDG'; // B. Indonesia (BIN)
$defaultHash = '$2y$12$.WyMRFcGeNOpu9Dmug0NfOUybbExTYgkfLqo1IZ5iHI8vB.3V3OQ.'; // 'password'
$now = '2026-11-05T12:00:00+07:00';

// Cleanup previous run if exists
DB::table('sheet_group_members')->where('created_at', $now)->delete();
DB::table('sheet_groups')->where('created_at', $now)->delete();
DB::table('sheet_users')->where('created_at', $now)->delete();

$existingUsers = DB::table('sheet_users')->get();
$existingEmails = $existingUsers->pluck('email')->map(fn($e) => mb_strtolower(trim($e)))->toArray();

// Define groups and raw member names from Google Doc
$groupsData = [
    [
        'name' => 'Kelompok 1',
        'theme' => 'Hakikat bahasa; karakteristik bahasa; kedudukan dan fungsi Bahasa Indonesia sebagai bahasa nasional, negara, dan akademik.',
        'members' => ['Yuli Santosa', 'Arya', 'Miqdad', 'Wahyu Suprapto']
    ],
    [
        'name' => 'Kelompok 2',
        'theme' => 'Sejarah dan perkembangan Bahasa Indonesia; Sumpah Pemuda; Bahasa Indonesia sebagai identitas bangsa dan bahasa ilmu pengetahuan.',
        'members' => ['Ibnu', 'Sabah', 'Arif Purnawan H', 'Fajar Zakki', 'Ryan habibillahil']
    ],
    [
        'name' => 'Kelompok 3',
        'theme' => 'Ejaan Bahasa Indonesia: penggunaan huruf, penulisan kata, tanda baca, dan unsur serapan.',
        'members' => ['Moch. Abdul Azis', 'Firman Rusdian', 'Bayu Prast', 'Muhandis Abdullah A.', 'Moh. Sholeh Arrasyid Ridho']
    ],
    [
        'name' => 'Kelompok 4',
        'theme' => 'Diksi, makna kata, ketepatan dan kesesuaian kata, pembentukan kata.',
        'members' => ['Muhammad Nanda Fauji', 'Ody Widya Asmara', 'Achmad Yusuf Afandi', 'Muhamad Wahyudin KS', 'Marchel Bintang Aulia']
    ],
    [
        'name' => 'Kelompok 5',
        'theme' => 'Konsep dan ciri kalimat efektif; kesepadanan, keparalelan, kehematan, kecermatan, kepaduan dan kelogisan.',
        'members' => ['Fatimah Azzahroh', 'Septyara Nurul Azizah', 'Sayyidah Nuril Azizah', 'Ayu Safitri', 'Nadira', 'hariono PSI (new)']
    ],
    [
        'name' => 'Kelompok 6',
        'theme' => 'Paragraf; gagasan utama dan penjelas; kohesi, koherensi; pola pengembangan',
        'members' => ['Siti Aisyah', 'Budi Trisnayanti', 'Nurul Saifa Zuhrita', 'Indri Dwi Wijayanti', 'Mery Anisa', 'Ririn']
    ],
    [
        'name' => 'Kelompok 7',
        'theme' => 'Jenis teks, struktur, isi, fungsi, tujuan komunikatif, dan karakteristik kebahasaan teks.',
        'members' => ['Emi Anggraeta', 'Siti Khoiriyah', 'Putri Firqotun Najiyah', 'Ahya Farida Surya', 'Sri wahyuni', 'Aisy (new)']
    ],
    [
        'name' => 'Kelompok 8',
        'theme' => 'Menyimak kritis: proses, strategi, pemahaman, interpretasi, analisis, dan evaluasi informasi.',
        'members' => ['Suci Yati Fajrin', 'Lora Vistinia', 'Raisya Jasmine Abigail', 'Juwita Setiyanisih', 'Anur lis Refina', 'Endang sajutri PGMI (new)']
    ],
    [
        'name' => 'Kelompok 9',
        'theme' => 'Keterampilan berbicara akademik; presentasi, diskusi, argumentasi, dan komunikasi santun.',
        'members' => ['Mayang Irwanti', 'Hawwa', 'Karismawati Rizka S', 'Najwa Kamel', 'Siti humairoh', 'Anik lilis']
    ],
    [
        'name' => 'Kelompok 10',
        'theme' => 'Membaca kritis; pemahaman informasi, gagasan utama, argumentasi, interpretasi, dan evaluasi sumber.',
        'members' => ['Nabilla Ramadhani', 'Firsta Haura Tsabit', 'Tsaqifah Atsarut Tasrbiyyah', 'Eri Fitri Nur Azizah', 'Ayuni Nanda Meriska', 'siti nur haya']
    ],
    [
        'name' => 'Kelompok 11',
        'theme' => 'Menulis akademik: pengembangan gagasan, organisasi tulisan, koherensi, argumentasi, dan penyuntingan.',
        'members' => ['Aisyah', 'Zulaikha Banatun K', 'Nur Isma Asyaroh', 'Asih', 'Mila Aurum S', 'Trihastuti Masrikha PSI (new)']
    ],
    [
        'name' => 'Kelompok 12',
        'theme' => 'Karya tulis akademik; sistematika; kutipan; parafrasa; sitasi; daftar pustaka; plagiarisme dan etika akademik',
        'members' => ['Eka Fitria Nazliati', 'Ummu Lathifah', 'Nurul Adinda', 'Intan Latifatul H.', 'Kayla Aurellya', 'Nuraini agustin']
    ],
];

// Specific known mappings for existing students with slightly different naming
$knownMappings = [
    'Emi Anggraeta' => 'Emi Anggaraeta Wilujeng',
    'Aisy (new)' => 'Nurul Aisy',
    'Firsta Haura Tsabit' => 'Firsta Haura Tsabita',
    'Tsaqifah Atsarut Tasrbiyyah' => 'Tsaqifah Atsarut Tarbiyyah',
    'Zulaikha Banatun K' => 'Zulaikha Banatun Karimah',
    'Trihastuti Masrikha PSI (new)' => 'Trihastuti Masrikha',
    'Bayu Prast' => 'Bayu Prastyo',
    'Muhandis Abdullah A.' => 'Muhandis Abdullah Alkaf',
    'Karismawati Rizka S' => 'Karismawati Rizka Spespatri',
    'Ryan habibillahil' => 'Ryan Habibillahil',
];

function resolveUser($rawName, $existingUsers, $knownMappings) {
    if (isset($knownMappings[$rawName])) {
        $target = $knownMappings[$rawName];
        foreach ($existingUsers as $u) {
            if (mb_strtolower(trim($u->name)) === mb_strtolower($target)) {
                return $u;
            }
        }
    }

    $clean = trim(preg_replace('/(\(new\)|\bPSI\b|\bPGMI\b)/i', '', $rawName));
    $cleanLower = mb_strtolower($clean);

    foreach ($existingUsers as $u) {
        if (mb_strtolower(trim($u->name)) === $cleanLower) {
            return $u;
        }
    }
    foreach ($existingUsers as $u) {
        $uLower = mb_strtolower(trim($u->name));
        if (str_starts_with($uLower, $cleanLower) || str_starts_with($cleanLower, $uLower)) {
            return $u;
        }
    }
    $parts = explode(' ', $cleanLower);
    $firstWord = $parts[0];
    if (strlen($firstWord) > 3) {
        $matches = [];
        foreach ($existingUsers as $u) {
            $uParts = explode(' ', mb_strtolower(trim($u->name)));
            if ($uParts[0] === $firstWord) {
                $matches[] = $u;
            }
        }
        if (count($matches) === 1) {
            return $matches[0];
        }
    }
    return null;
}

// 1. Identify users to create
$sqlInserts = [];
$usersToInsert = [];
$nameToUserMap = [];

$newStudentNpmCounter = 1;

foreach ($groupsData as $g) {
    foreach ($g['members'] as $m) {
        $existing = resolveUser($m, $existingUsers, $knownMappings);
        if ($existing) {
            $nameToUserMap[$m] = $existing;
        } else {
            // Need to create this user
            $cleanName = trim(preg_replace('/(\(new\)|\bPSI\b|\bPGMI\b)/i', '', $m));
            // Capitalize properly without duplicating suffixes
            $words = explode(' ', $cleanName);
            $formattedWords = [];
            foreach ($words as $w) {
                if (in_array(strtoupper($w), ['KS', 'H.', 'S', 'S.', 'M.', 'A.'])) {
                    $formattedWords[] = strtoupper($w);
                } else {
                    $formattedWords[] = ucfirst(mb_strtolower($w));
                }
            }
            $cleanName = implode(' ', $formattedWords);

            $isPsi = str_contains($m, 'PSI');
            $prodiId = $isPsi ? $psiProdiId : $pgmiProdiId;
            $className = $isPsi ? 'Psikologi 1B' : 'PGMI 1B';
            $domain = $isPsi ? 'psi.id' : 'pgmi.id';

            $slugBase = Str::slug(explode(' ', $cleanName)[0]);
            $email = $slugBase . '@' . $domain;
            $counter = 1;
            while (in_array($email, $existingEmails)) {
                $email = $slugBase . (++$counter) . '@' . $domain;
            }
            $existingEmails[] = $email;

            $newId = (string) Str::ulid();
            $npm = ($isPsi ? 'PSI' : 'PGM') . str_pad($newStudentNpmCounter++, 3, '0', STR_PAD_LEFT);

            $newRecord = (object)[
                'id' => $newId,
                'name' => $cleanName,
                'email' => $email,
                'npm' => $npm,
                'password_hash' => $defaultHash,
                'role' => 'mahasiswa',
                'class_name' => $className,
                'cohort' => '2026',
                'phone' => '',
                'active' => '1',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => '',
                'prodi_id' => $prodiId,
                'avatar_url' => '',
                'cover_url' => '',
            ];

            $usersToInsert[$m] = $newRecord;
            $nameToUserMap[$m] = $newRecord;

            // Prepare SQL
            $sqlInserts[] = sprintf(
                "INSERT INTO `sheet_users` (`id`, `name`, `email`, `npm`, `password_hash`, `role`, `class_name`, `cohort`, `phone`, `active`, `created_at`, `updated_at`, `deleted_at`, `prodi_id`, `avatar_url`, `cover_url`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
                $newId,
                addslashes($cleanName),
                addslashes($email),
                $npm,
                addslashes($defaultHash),
                'mahasiswa',
                addslashes($className),
                '2026',
                '',
                '1',
                $now,
                $now,
                '',
                $prodiId,
                '',
                ''
            );
        }
    }
}

echo "New Students to create: " . count($usersToInsert) . "\n";
foreach ($usersToInsert as $rawName => $u) {
    echo "  - {$rawName} => Name: {$u->name}, Email: {$u->email}, NPM: {$u->npm}, Class: {$u->class_name}\n";
}

// 2. Insert new users into local DB
foreach ($usersToInsert as $u) {
    DB::table('sheet_users')->insert((array)$u);
}
echo "\nInserted " . count($usersToInsert) . " new users into local MySQL!\n";

// 3. Create the 12 Groups
$groupSqlInserts = [];
$memberSqlInserts = [];

foreach ($groupsData as $index => $g) {
    $groupId = (string) Str::ulid();
    $leaderRaw = $g['members'][0];
    $leaderUser = $nameToUserMap[$leaderRaw];

    $groupRecord = [
        'id' => $groupId,
        'course_id' => $courseId,
        'name' => $g['name'],
        'leader_id' => $leaderUser->id,
        'description' => $g['theme'],
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => '',
    ];

    DB::table('sheet_groups')->insert($groupRecord);

    $groupSqlInserts[] = sprintf(
        "INSERT INTO `sheet_groups` (`id`, `course_id`, `name`, `leader_id`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
        $groupId,
        $courseId,
        addslashes($g['name']),
        $leaderUser->id,
        addslashes($g['theme']),
        $now,
        $now,
        ''
    );

    // Members
    foreach ($g['members'] as $mRaw) {
        $memberUser = $nameToUserMap[$mRaw];
        $memberRecordId = (string) Str::ulid();
        $isLeader = ($memberUser->id === $leaderUser->id);

        $memberRow = [
            'id' => $memberRecordId,
            'group_id' => $groupId,
            'user_id' => $memberUser->id,
            'role' => $isLeader ? 'leader' : 'member',
            'created_at' => $now,
            'deleted_at' => '',
        ];

        DB::table('sheet_group_members')->insert($memberRow);

        $memberSqlInserts[] = sprintf(
            "INSERT INTO `sheet_group_members` (`id`, `group_id`, `user_id`, `role`, `created_at`, `deleted_at`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s');",
            $memberRecordId,
            $groupId,
            $memberUser->id,
            $isLeader ? 'leader' : 'member',
            $now,
            ''
        );
    }

    echo "Created {$g['name']} (Leader: {$leaderUser->name}, " . count($g['members']) . " members)\n";
}

// 4. Generate SQL File
$sqlContent = "-- ====================================================\n";
$sqlContent .= "-- IMPORT DATA MAHASISWA BARU (PGMI & PSI) & KELOMPOK B. INDONESIA\n";
$sqlContent .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- ====================================================\n\n";

$sqlContent .= "-- 1. DATA MAHASISWA BARU\n";
$sqlContent .= implode("\n", $sqlInserts) . "\n\n";

$sqlContent .= "-- 2. DATA KELOMPOK (1-12)\n";
$sqlContent .= implode("\n", $groupSqlInserts) . "\n\n";

$sqlContent .= "-- 3. DATA ANGGOTA KELOMPOK\n";
$sqlContent .= implode("\n", $memberSqlInserts) . "\n\n";

file_put_contents(__DIR__ . '/../import_b_indonesia_groups.sql', $sqlContent);

echo "\nSQL export generated at: " . realpath(__DIR__ . '/../import_b_indonesia_groups.sql') . "\n";
