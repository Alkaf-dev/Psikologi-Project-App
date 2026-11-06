<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$courseId = '01M60M04FMBKHFT5ZRAB9QV50G'; // B. Inggris (BIG)
$psiProdiId = '01M60CY00TAFA0HV21NX7JFNDF'; // S1 Psikologi Islam
$defaultHash = '$2y$12$.WyMRFcGeNOpu9Dmug0NfOUybbExTYgkfLqo1IZ5iHI8vB.3V3OQ.'; // 'password'
$now = '2026-11-06T08:00:00+07:00';

$sqlInserts = [];
$groupSqlInserts = [];
$memberSqlInserts = [];

// 1. Ensure Samsul Anwar exists
$samsul = DB::table('sheet_users')->where('name', 'Samsul Anwar')->first();
if (!$samsul) {
    $samsulId = (string) Str::ulid();
    $samsulRow = [
        'id' => $samsulId,
        'name' => 'Samsul Anwar',
        'email' => 'samsulanwar@psi.id',
        'npm' => '',
        'password_hash' => $defaultHash,
        'role' => 'mahasiswa',
        'class_name' => 'Psikologi 1B',
        'cohort' => '2026',
        'phone' => '',
        'active' => '1',
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => '',
        'prodi_id' => $psiProdiId,
        'avatar_url' => '',
        'cover_url' => '',
    ];
    DB::table('sheet_users')->insert($samsulRow);
    echo "Created user Samsul Anwar (ID: $samsulId)\n";

    $sqlInserts[] = sprintf(
        "INSERT INTO `sheet_users` (`id`, `name`, `email`, `npm`, `password_hash`, `role`, `class_name`, `cohort`, `phone`, `active`, `created_at`, `updated_at`, `deleted_at`, `prodi_id`, `avatar_url`, `cover_url`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
        $samsulId, 'Samsul Anwar', 'samsulanwar@psi.id', '', $defaultHash, 'mahasiswa', 'Psikologi 1B', '2026', '', '1', $now, $now, '', $psiProdiId, '', ''
    );
} else {
    $samsulId = $samsul->id;
    echo "User Samsul Anwar already exists (ID: $samsulId)\n";
}

// 2. Exact Mapping of all 11 Groups
$groupsData = [
    [
        'name' => 'Kelompok 1',
        'theme' => 'Introducing oneself and others; to be (is, am, are, was, were); has/have; simple sentence. [U1-U3]',
        'leader_id' => '01M60M04781YJE769QVAYB8C8D', // Aisyah
        'members' => [
            '01M60M04781YJE769QVAYB8C8D', // Aisyah
            '01M60M044CZW30TAZJHT24XN49', // Zulaikha Banatun Karimah
            '01M6N4Y6865WVEZVR8B0T16QVJ', // Nur Isma Asyaroh
            '01M6N4Y6865WVEZVR8B0T16QVK', // Asih
            '01M6N4Y687K18VACCFG3AVBBC0', // Intan Latifatul H.
            '01M60M046BHVKNRWQ7BM5DT2G8', // Sitti Humaira
        ]
    ],
    [
        'name' => 'Kelompok 2',
        'theme' => 'Word Order: Verb + Object; Adverb of Place, Time and Manner; Adverbs of Frequency. [U1-U5]',
        'leader_id' => '01M60M04732R4PZ7JAB155VME4', // Nabilla Ramadhani
        'members' => [
            '01M60M04732R4PZ7JAB155VME4', // Nabilla Ramadhani
            '01M5TXE41AAPAGV2P8D38XQJBS', // Firsta Haura Tsabita
            '01M60M0448CWWHBDPC68QMS4C5', // Eri Fitri Nur Azizah
            '01M60M0476AH7C5XTJM9ZW1HZE', // Tsaqifah Atsarut Tarbiyyah
            '01M60M044TMF63A4JE33M25DFH', // Kayla Aurellya
        ]
    ],
    [
        'name' => 'Kelompok 3',
        'theme' => 'Pronouns: Subject, Object, Possessive Adjectives, Possessive Pronouns, Reflexive Pronouns. [U1-U5]',
        'leader_id' => '01M6N4Y684R738H074R03C07FH', // Mayang Irwanti
        'members' => [
            '01M6N4Y684R738H074R03C07FH', // Mayang Irwanti
            '01M60M047BXT17XKRGMWR50T0R', // Hawwa
            '01M60M0456N7JTBS2PABTNFTPY', // Karismawati Rizka Spespatri
            '01M6N4Y685PX62RKS3JSS4NZ85', // Najwa Kamel
            '01M60M0489ZZAGB3KV9VDTQK14', // Nurul Adinda
            '01M60M0471ASY3736KY2VQC16X', // Ai Hapsah
        ]
    ],
    [
        'name' => 'Kelompok 4',
        'theme' => 'Singular and Plural Nouns; These, Those; Regular and Irregular Plural Nouns. [U1-U4]',
        'leader_id' => '01M6N4Y683NDJY5EMVD9XZTWGN', // Suci Yati Fajrin
        'members' => [
            '01M6N4Y683NDJY5EMVD9XZTWGN', // Suci Yati Fajrin
            '01M5TXE359AFKNAMGT1Z8PKV13', // Lora Vistinia
            '01M5TXE4T5QC39QWK7FTWCPXZD', // raisya (Raisya Jasmine A.)
            '01M60M047J8CMVZTECYBHY82XY', // Juwita Setiyanisih
            '01M60M046FQ38QZTT44HSH1Q51', // Ummu Lathifah
            '01M60M0461NH396Z1PYHWM913V', // Ayuni Nanda Meriska
        ]
    ],
    [
        'name' => 'Kelompok 5',
        'theme' => 'Noun Phrase and Adjective Phrase. [U1-U5]',
        'leader_id' => '01M60M046YF5BRCRWRX52H2YXT', // Emi Anggaraeta Wilujeng
        'members' => [
            '01M60M046YF5BRCRWRX52H2YXT', // Emi Anggaraeta Wilujeng
            '01M6N4Y682RR0640P6WW2V4GXV', // Siti Khoiriyah
            '01M6N4Y683NDJY5EMVD9XZTWGM', // Putri Firqotun Najiyah
            '01M5TXE1FSDG0VN2JFDGG3FHTF', // Ahya Farida Surya
            '01M5TXDYKSAJSGFTFDC1KK1ZSA', // Eka Fitria Nazliati
            '01M60M0486W7EPDREGHR9EG5E2', // Sri Wahyuni (Bu Sri)
            '01M6N4Y6865WVEZVR8B0T16QVH', // Siti Nur Haya
            '01M60M048YEAJBGRMR7EEQD16C', // Trihastuti Masrikha
        ]
    ],
    [
        'name' => 'Kelompok 6',
        'theme' => 'Review: Introduction; Word Order; Pronouns; Singular-Plural Nouns; Noun Phrase; Adjective Phrase. [U1-U6]',
        'leader_id' => '01M6N4Y68032QE3FKP1A1XY355', // Fatimah Azzahroh
        'members' => [
            '01M6N4Y68032QE3FKP1A1XY355', // Fatimah Azzahroh
            '01M5TXE7GXA7YHM0NYGQ6BRA79', // Septyara Nurul Azizah
            '01M6N4Y68032QE3FKP1A1XY356', // Sayyidah Nuril Azizah
            '01M60M047V0H57PYZ7MS8Q7BPS', // Ayu Safitri
            '01M6N4Y681FWHQ0A9HWT94KV1Y', // Mery Anisa
            '01M60M046T0KM8TKEKR3KT5S3J', // Nadira
            '01M6N4Y682RR0640P6WW2V4GXT', // Ririn (Ririn Zahrotunnisa)
            '01M6N4Y684R738H074R03C07FG', // Endang Sajutri
        ]
    ],
    [
        'name' => 'Kelompok 7',
        'theme' => 'Daily Routine; Simple Present Tense; Adverbs of Frequency. [U1-U4]',
        'leader_id' => '01M6N4Y681FWHQ0A9HWT94KV1X', // Siti Aisyah
        'members' => [
            '01M6N4Y681FWHQ0A9HWT94KV1X', // Siti Aisyah
            '01M60M047D9HRT1FZBA0AX3506', // Budi Trisnayanti
            '01M60M0466Q6MQ2RQSQQN6VZDJ', // Nurul Saifa Zuhrita
            '01M60M0469RYGWD8A499HSH1NG', // Indri Dwi Wijayanti
            '01M6N4Y684R738H074R03C07FF', // Anur Lis Refina
            '01M6N4Y687K18VACCFG3AVBBBZ', // Mila Aurum S
            '01M6N4Y685PX62RKS3JSS4NZ87', // Anik Lilis (Anik Lilis Setiawati)
            '01M60M0492CGM3K0TVF6A2G9FG', // Nurul Aisy
            '01M60M0495H5NM5DPQG07RR1QD', // Nuraini Agustin
        ]
    ],
    [
        'name' => 'Kelompok 8',
        'theme' => 'I am doing something now; Present Progressive Tense; Auxiliary is, am, are. [U1-U5]',
        'leader_id' => '01M60M044Q4TC8V03A2Z1HEDN5', // Muhammad Nanda Fauji
        'members' => [
            '01M60M044Q4TC8V03A2Z1HEDN5', // Muhammad Nanda Fauji
            '01M60M048V1BMT63AYZKZN9KBE', // Ody Widya Asmara
            '01M60M044ZZMHEZ26HGXKHK2WD', // Achmad Yusuf Afandi
            '01M6N4Y67ZJSMKBB9M0RJEFJ26', // Muhamad Wahyudin KS
            '01M60M0483F0740JHPCQ0ABB9D', // Marchel Bintang Aulia
            $samsulId,                    // Samsul Anwar
        ]
    ],
    [
        'name' => 'Kelompok 9',
        'theme' => 'Planning and Schedule; Simple Future Tense; Will and Be Going To. [U1-U5]',
        'leader_id' => '01M60M0443Z2DJQRTFFBXZRHJG', // Moch. Abdul Azis
        'members' => [
            '01M60M0443Z2DJQRTFFBXZRHJG', // Moch. Abdul Azis
            '01M5TXE8FCNKZR4J8WRQTD7HCM', // Bayu Prastyo
            '01M5TXE0DQYVA1EBBCDS3YJYB8', // Firman Rusdian
            '01M5TXE5NXNRM5SF28PZF8NEQ2', // Muhandis Abdullah Alkaf
            '01M60M046QRM3K0G6752ZMZN22', // Ryan Habibillahil
            '01M60M04CV8SPGKWT4N5H4C3EF', // Wildan Al Muzakki
        ]
    ],
    [
        'name' => 'Kelompok 10',
        'theme' => 'Retelling Stories; Simple Past Tense; Regular and Irregular Verbs. [U1-U5]',
        'leader_id' => '01M60M047GTM636QP7763JVFHD', // Ibnu Alfiansyah
        'members' => [
            '01M60M047GTM636QP7763JVFHD', // Ibnu Alfiansyah
            '01M60M0445TGYK9RCTVR4N680H', // Sabah Sabilla
            '01M60M0480DZXKGK3Z84ZDZXA9', // Arif Purnawan H
            '01M60M047XNRBBR8K0PPYF4P67', // Fajar Zakki
            '01M6N4Y681FWHQ0A9HWT94KV1W', // Hariono
        ]
    ],
    [
        'name' => 'Kelompok 11',
        'theme' => 'I have done my work; Present Perfect Tense. [U1-U5]',
        'leader_id' => '01M5TWAWM2707XZ4EXQH96R6CE', // Yuli Santosa
        'members' => [
            '01M5TWAWM2707XZ4EXQH96R6CE', // Yuli Santosa
            '01M60M047RQY3MKQQARKDNQ9WQ', // Arya Ulilalbab
            '01M5TXE2C83J0Q25TW72277PMJ', // Miqdad Hafidzul Aslam
            '01M5TXE6JTXECA8MSHA8RCD0DX', // Wahyu Suprapto
        ]
    ],
];

// Clean up existing groups for B. Inggris if any
$existingGroupIds = DB::table('sheet_groups')->where('course_id', $courseId)->pluck('id');
if ($existingGroupIds->isNotEmpty()) {
    DB::table('sheet_group_members')->whereIn('group_id', $existingGroupIds)->delete();
    DB::table('sheet_groups')->whereIn('id', $existingGroupIds)->delete();
}

foreach ($groupsData as $g) {
    $groupId = (string) Str::ulid();
    
    $groupRow = [
        'id' => $groupId,
        'course_id' => $courseId,
        'name' => $g['name'],
        'leader_id' => $g['leader_id'],
        'description' => $g['theme'],
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => '',
    ];
    DB::table('sheet_groups')->insert($groupRow);

    $groupSqlInserts[] = sprintf(
        "INSERT INTO `sheet_groups` (`id`, `course_id`, `name`, `leader_id`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
        $groupId,
        $courseId,
        addslashes($g['name']),
        $g['leader_id'],
        addslashes($g['theme']),
        $now,
        $now,
        ''
    );

    foreach ($g['members'] as $mUserId) {
        $memberRecordId = (string) Str::ulid();
        $isLeader = ($mUserId === $g['leader_id']);

        $memberRow = [
            'id' => $memberRecordId,
            'group_id' => $groupId,
            'user_id' => $mUserId,
            'role' => $isLeader ? 'leader' : 'member',
            'created_at' => $now,
            'deleted_at' => '',
        ];
        DB::table('sheet_group_members')->insert($memberRow);

        $memberSqlInserts[] = sprintf(
            "INSERT INTO `sheet_group_members` (`id`, `group_id`, `user_id`, `role`, `created_at`, `deleted_at`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s');",
            $memberRecordId,
            $groupId,
            $mUserId,
            $isLeader ? 'leader' : 'member',
            $now,
            ''
        );
    }

    $leaderName = DB::table('sheet_users')->where('id', $g['leader_id'])->value('name');
    echo "Created {$g['name']} (Leader: {$leaderName}, " . count($g['members']) . " members)\n";
}

// 4. Generate SQL File for Production
$sqlContent = "-- ====================================================\n";
$sqlContent .= "-- IMPORT DATA KELOMPOK B. INGGRIS (1-11)\n";
$sqlContent .= "-- Mata Kuliah: B. Inggris (BIG - 01M60M04FMBKHFT5ZRAB9QV50G)\n";
$sqlContent .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- ====================================================\n\n";

if (!empty($sqlInserts)) {
    $sqlContent .= "-- 1. DATA MAHASISWA BARU (SAMSUL ANWAR)\n";
    $sqlContent .= implode("\n", $sqlInserts) . "\n\n";
}

$sqlContent .= "-- 2. DATA KELOMPOK B. INGGRIS (1-11)\n";
$sqlContent .= implode("\n", $groupSqlInserts) . "\n\n";

$sqlContent .= "-- 3. DATA ANGGOTA KELOMPOK B. INGGRIS\n";
$sqlContent .= implode("\n", $memberSqlInserts) . "\n\n";

$sqlPath = __DIR__ . '/../import_b_inggris_groups.sql';
file_put_contents($sqlPath, $sqlContent);

echo "\nSQL export generated at: " . realpath($sqlPath) . "\n";
