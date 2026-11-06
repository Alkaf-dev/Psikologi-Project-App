<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$courseId = '01M60M04FCBJA5WHE65WA2BWBF'; // Studi Al-Qur'an (SAQ)
$psiProdiId = '01M60CY00TAFA0HV21NX7JFNDF'; // S1 Psikologi Islam
$defaultHash = '$2y$12$.WyMRFcGeNOpu9Dmug0NfOUybbExTYgkfLqo1IZ5iHI8vB.3V3OQ.'; // 'password'
$now = '2026-11-06T09:00:00+07:00';

$sqlInserts = [];
$groupSqlInserts = [];
$memberSqlInserts = [];

// 1. Ensure Uswatun Khasanah exists
$uswatun = DB::table('sheet_users')->where('name', 'Uswatun Khasanah')->first();
if (!$uswatun) {
    $uswatunId = (string) Str::ulid();
    $uswatunRow = [
        'id' => $uswatunId,
        'name' => 'Uswatun Khasanah',
        'email' => 'uswatunkhasanah@psi.id',
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
    DB::table('sheet_users')->insert($uswatunRow);
    echo "Created user Uswatun Khasanah (ID: $uswatunId)\n";

    $sqlInserts[] = sprintf(
        "INSERT INTO `sheet_users` (`id`, `name`, `email`, `npm`, `password_hash`, `role`, `class_name`, `cohort`, `phone`, `active`, `created_at`, `updated_at`, `deleted_at`, `prodi_id`, `avatar_url`, `cover_url`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
        $uswatunId, 'Uswatun Khasanah', 'uswatunkhasanah@psi.id', '', $defaultHash, 'mahasiswa', 'Psikologi 1B', '2026', '', '1', $now, $now, '', $psiProdiId, '', ''
    );
} else {
    $uswatunId = $uswatun->id;
    echo "User Uswatun Khasanah already exists (ID: $uswatunId)\n";
}

// 2. Groups Definition (1-12) based on PDF
$groupsData = [
    [
        'name' => 'Kelompok 1',
        'theme' => 'Sejarah dan Perkembangan Al-Qur’an (Pertemuan ke-4)',
        'leader_id' => '01M60M0445TGYK9RCTVR4N680H', // Sabah Sabilla
        'members' => [
            '01M60M0445TGYK9RCTVR4N680H', // Sabah Sabilla (Leader)
            '01M5TXE7GXA7YHM0NYGQ6BRA79', // Septyara Nurul Azizah
            '01M5TXE0DQYVA1EBBCDS3YJYB8', // Firman Rusdian
            '01M5TXE2C83J0Q25TW72277PMJ', // Miqdad Hafidzul Aslam
        ]
    ],
    [
        'name' => 'Kelompok 2',
        'theme' => 'Ulumul Qur’an (Pertemuan ke-5)',
        'leader_id' => '01M60M048FMZ1XXF528W9BCBVF', // Moh. Sholeh Arrasyid Ridho
        'members' => [
            '01M60M048FMZ1XXF528W9BCBVF', // Moh. Sholeh Arrasyid Ridho (Leader)
            '01M5TXE1FSDG0VN2JFDGG3FHTF', // Ahya Farida Surya
            '01M60M044Q4TC8V03A2Z1HEDN5', // Muhammad Nanda Fauji
            '01M60M047RQY3MKQQARKDNQ9WQ', // Arya Ulilalbab
        ]
    ],
    [
        'name' => 'Kelompok 3',
        'theme' => 'Metodologi Tafsir Al-Qur’an (Pertemuan ke-6)',
        'leader_id' => '01M5TWAWM2707XZ4EXQH96R6CE', // Yuli Santosa
        'members' => [
            '01M5TWAWM2707XZ4EXQH96R6CE', // Yuli Santosa (Leader)
            '01M60M04781YJE769QVAYB8C8D', // Aisyah
            '01M60M0443Z2DJQRTFFBXZRHJG', // Moch. Abdul Azis
            '01M5TXE5NXNRM5SF28PZF8NEQ2', // Muhandis Abdullah Alkaf
        ]
    ],
    [
        'name' => 'Kelompok 4',
        'theme' => 'Praktik Kajian Ayat Al-Qur’an (Pertemuan ke-7)',
        'leader_id' => '01M60M048V1BMT63AYZKZN9KBE', // Ody Widya Asmara
        'members' => [
            '01M60M048V1BMT63AYZKZN9KBE', // Ody Widya Asmara (Leader)
            '01M60M0483F0740JHPCQ0ABB9D', // Marchel Bintang Aulia
            '01M60M047BXT17XKRGMWR50T0R', // Hawwa
            '01M5TXE4T5QC39QWK7FTWCPXZD', // raisya (Raisya Jasmine Abigail)
        ]
    ],
    [
        'name' => 'Kelompok 5',
        'theme' => 'Konsep Manusia dalam Al-Qur’an (Pertemuan ke-9)',
        'leader_id' => '01M60M0480DZXKGK3Z84ZDZXA9', // Arif Purnawan H
        'members' => [
            '01M60M0480DZXKGK3Z84ZDZXA9', // Arif Purnawan H (Leader)
            '01M60M0448CWWHBDPC68QMS4C5', // Eri Fitri Nur Azizah
            '01M5TXE6JTXECA8MSHA8RCD0DX', // Wahyu Suprapto
            '01M5TXE8FCNKZR4J8WRQTD7HCM', // Bayu Prastyo
        ]
    ],
    [
        'name' => 'Kelompok 6',
        'theme' => 'Struktur Psikologis Manusia Perspektif Al-Qur’an (Pertemuan ke-10)',
        'leader_id' => '01M60M047XNRBBR8K0PPYF4P67', // Fajar Zakki
        'members' => [
            '01M60M047XNRBBR8K0PPYF4P67', // Fajar Zakki (Leader)
            '01M5TXE359AFKNAMGT1Z8PKV13', // Lora Vistinia
            '01M60M046YF5BRCRWRX52H2YXT', // Emi Anggaraeta Wilujeng
            '01M5TXDYKSAJSGFTFDC1KK1ZSA', // Eka Fitria Nazliati
        ]
    ],
    [
        'name' => 'Kelompok 7',
        'theme' => 'Konsep Fitrah dan Kepribadian Manusia (Pertemuan ke-11)',
        'leader_id' => '01M60M047V0H57PYZ7MS8Q7BPS', // Ayu Safitri
        'members' => [
            '01M60M047V0H57PYZ7MS8Q7BPS', // Ayu Safitri (Leader)
            '01M60M047J8CMVZTECYBHY82XY', // Juwita Setiyanisih
            '01M60M044ZZMHEZ26HGXKHK2WD', // Achmad Yusuf Afandi
            '01M5TXE41AAPAGV2P8D38XQJBS', // Firsta Haura Tsabita
        ]
    ],
    [
        'name' => 'Kelompok 8',
        'theme' => 'Akhlak Qur’ani dan Pembentukan Karakter (Pertemuan ke-12)',
        'leader_id' => '01M60M0466Q6MQ2RQSQQN6VZDJ', // Nurul Saifa Zuhrita
        'members' => [
            '01M60M0466Q6MQ2RQSQQN6VZDJ', // Nurul Saifa Zuhrita (Leader)
            '01M60M044TMF63A4JE33M25DFH', // Kayla Aurellya
            '01M60M0489ZZAGB3KV9VDTQK14', // Nurul Adinda
            '01M60M0486W7EPDREGHR9EG5E2', // Sri Wahyuni
        ]
    ],
    [
        'name' => 'Kelompok 9',
        'theme' => 'Nilai Sosial dalam Al-Qur’an (Pertemuan ke-13)',
        'leader_id' => '01M60M0476AH7C5XTJM9ZW1HZE', // Tsaqifah Atsarut Tarbiyyah
        'members' => [
            '01M60M0476AH7C5XTJM9ZW1HZE', // Tsaqifah Atsarut Tarbiyyah (Leader)
            '01M60M0456N7JTBS2PABTNFTPY', // Karismawati Rizka Spespatri
            '01M60M044CZW30TAZJHT24XN49', // Zulaikha Banatun Karimah
            '01M60M047D9HRT1FZBA0AX3506', // Budi Trisnayanti
        ]
    ],
    [
        'name' => 'Kelompok 10',
        'theme' => 'Al-Qur’an dan Problematika Psikologi Modern (Pertemuan ke-14)',
        'leader_id' => '01M60M046FQ38QZTT44HSH1Q51', // Ummu Lathifah
        'members' => [
            '01M60M046FQ38QZTT44HSH1Q51', // Ummu Lathifah (Leader)
            '01M60M04732R4PZ7JAB155VME4', // Nabilla Ramadhani
            '01M60M047GTM636QP7763JVFHD', // Ibnu Alfiansyah
            '01M60M0469RYGWD8A499HSH1NG', // Indri Dwi Wijayanti
        ]
    ],
    [
        'name' => 'Kelompok 11',
        'theme' => 'Kajian Tematik Al-Qur’an dan Psikologi Islam (Pertemuan ke-15 Bagian 1)',
        'leader_id' => '01M6N4Y6865WVEZVR8B0T16QVJ', // Nur Isma Asyaroh
        'members' => [
            '01M6N4Y6865WVEZVR8B0T16QVJ', // Nur Isma Asyaroh (Leader)
            '01M60M0461NH396Z1PYHWM913V', // Ayuni Nanda Meriska
            '01M60M046T0KM8TKEKR3KT5S3J', // Nadira
            '01M60M046QRM3K0G6752ZMZN22', // Ryan Habibillahil
        ]
    ],
    [
        'name' => 'Kelompok 12',
        'theme' => 'Refleksi Nilai Qur’ani (Pertemuan ke-15 Bagian 2)',
        'leader_id' => '01M60M0471ASY3736KY2VQC16X', // Ai Hapsah
        'members' => [
            '01M60M0471ASY3736KY2VQC16X', // Ai Hapsah (Leader)
            '01M60M04CV8SPGKWT4N5H4C3EF', // Wildan Al Muzakki
            $uswatunId,                    // Uswatun Khasanah
            '01M60M048YEAJBGRMR7EEQD16C', // Trihastuti Masrikha
        ]
    ],
];

// Clean up existing groups for Studi Al-Qur'an if any
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
$sqlContent .= "-- IMPORT DATA KELOMPOK STUDI AL-QUR'AN (1-12)\n";
$sqlContent .= "-- Mata Kuliah: Studi Al-Qur'an (SAQ - 01M60M04FCBJA5WHE65WA2BWBF)\n";
$sqlContent .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- ====================================================\n\n";

if (!empty($sqlInserts)) {
    $sqlContent .= "-- 1. DATA MAHASISWA BARU (USWATUN KHASANAH)\n";
    $sqlContent .= implode("\n", $sqlInserts) . "\n\n";
}

$sqlContent .= "-- 2. DATA KELOMPOK STUDI AL-QUR'AN (1-12)\n";
$sqlContent .= implode("\n", $groupSqlInserts) . "\n\n";

$sqlContent .= "-- 3. DATA ANGGOTA KELOMPOK STUDI AL-QUR'AN\n";
$sqlContent .= implode("\n", $memberSqlInserts) . "\n\n";

$sqlPath = __DIR__ . '/../import_studi_alquran_groups.sql';
file_put_contents($sqlPath, $sqlContent);

echo "\nSQL export generated at: " . realpath($sqlPath) . "\n";
