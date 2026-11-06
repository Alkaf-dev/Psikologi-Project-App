<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$courseId = '01M60M04G1HH3YBT1AFSGFVS3H'; // AIK
$esProdiId = '01M60M043GTP41WPV6GWJ61FHH'; // S1 Ekonomi Syari'ah
$defaultHash = '$2y$12$.WyMRFcGeNOpu9Dmug0NfOUybbExTYgkfLqo1IZ5iHI8vB.3V3OQ.'; // 'password'
$now = '2026-11-06T09:30:00+07:00';

$sqlInserts = [];
$groupSqlInserts = [];
$memberSqlInserts = [];

// 1. Ensure Thoriq Ibrahim exists
$thoriq = DB::table('sheet_users')->where('name', 'like', '%Thoriq%')->orWhere('email', 'thoriq@es.id')->first();
if (!$thoriq) {
    $thoriqId = (string) Str::ulid();
    $thoriqRow = [
        'id' => $thoriqId,
        'name' => 'Thoriq Ibrahim',
        'email' => 'thoriq@es.id',
        'npm' => '',
        'password_hash' => $defaultHash,
        'role' => 'mahasiswa',
        'class_name' => 'Ekonomi Syari\'ah 1B',
        'cohort' => '2026',
        'phone' => '',
        'active' => '1',
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => '',
        'prodi_id' => $esProdiId,
        'avatar_url' => '',
        'cover_url' => '',
    ];
    DB::table('sheet_users')->insert($thoriqRow);
    echo "Created user Thoriq Ibrahim (ID: $thoriqId)\n";

    $sqlInserts[] = sprintf(
        "INSERT INTO `sheet_users` (`id`, `name`, `email`, `npm`, `password_hash`, `role`, `class_name`, `cohort`, `phone`, `active`, `created_at`, `updated_at`, `deleted_at`, `prodi_id`, `avatar_url`, `cover_url`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
        $thoriqId, 'Thoriq Ibrahim', 'thoriq@es.id', '', $defaultHash, 'mahasiswa', "Ekonomi Syari\\'ah 1B", '2026', '', '1', $now, $now, '', $esProdiId, '', ''
    );
} else {
    $thoriqId = $thoriq->id;
    echo "User Thoriq Ibrahim already exists (ID: $thoriqId)\n";
}

// 2. Exact Mapping of all 12 Groups
$groupsData = [
    [
        'name' => 'Kelompok 1 (PSI)',
        'theme' => 'Islam sebagai Sistem Kehidupan (Way of Life) (29 Oktober 2026)',
        'leader_id' => '01M60M0445TGYK9RCTVR4N680H', // Sabah Sabilla
        'members' => [
            '01M60M0445TGYK9RCTVR4N680H', // Sabah Sabilla (Leader)
            '01M60M0480DZXKGK3Z84ZDZXA9', // Arif Purnawan H
            '01M60M047XNRBBR8K0PPYF4P67', // Fajar Zakki
            '01M60M046QRM3K0G6752ZMZN22', // Ryan Habibillahil
            '01M60M048V1BMT63AYZKZN9KBE', // Ody Widya Asmara
            '01M60M047RQY3MKQQARKDNQ9WQ', // Arya Ulilalbab
        ]
    ],
    [
        'name' => 'Kelompok 2 (PSI)',
        'theme' => 'Akidah Islam dan Konsep Ketuhanan (5 November 2026)',
        'leader_id' => '01M5TXE2C83J0Q25TW72277PMJ', // Miqdad Hafidzul Aslam
        'members' => [
            '01M5TXE2C83J0Q25TW72277PMJ', // Miqdad Hafidzul Aslam (Leader)
            '01M5TXE6JTXECA8MSHA8RCD0DX', // Wahyu Suprapto
            '01M60M044Q4TC8V03A2Z1HEDN5', // Muhammad Nanda Fauji
            '01M5TWAWM2707XZ4EXQH96R6CE', // Yuli Santosa
            '01M60M044ZZMHEZ26HGXKHK2WD', // Achmad Yusuf Afandi
            '01M5TXE5NXNRM5SF28PZF8NEQ2', // Muhandis Abdullah Alkaf
        ]
    ],
    [
        'name' => 'Kelompok 3 (PSI)',
        'theme' => 'Sumber Ajaran Islam (19 November 2026)',
        'leader_id' => '01M60M047GTM636QP7763JVFHD', // Ibnu Alfiansyah
        'members' => [
            '01M60M047GTM636QP7763JVFHD', // Ibnu Alfiansyah (Leader)
            '01M60M0443Z2DJQRTFFBXZRHJG', // Moch. Abdul Azis
            '01M5TXE0DQYVA1EBBCDS3YJYB8', // Firman Rusdian
            '01M5TXE8FCNKZR4J8WRQTD7HCM', // Bayu Prastyo
            '01M60M0483F0740JHPCQ0ABB9D', // Marchel Bintang Aulia
            '01M60M048FMZ1XXF528W9BCBVF', // Moh. Sholeh Arrasyid Ridho
            '01M6N4Y681FWHQ0A9HWT94KV1W', // Hariono
        ]
    ],
    [
        'name' => 'Kelompok 4 (PSI)',
        'theme' => 'Rukun Iman dan Implementasinya dalam Kehidupan',
        'leader_id' => '01M5TXE7GXA7YHM0NYGQ6BRA79', // Septyara Nurul Azizah
        'members' => [
            '01M5TXE7GXA7YHM0NYGQ6BRA79', // Septyara Nurul Azizah (Leader)
            '01M60M047V0H57PYZ7MS8Q7BPS', // Ayu Safitri
            '01M60M046FQ38QZTT44HSH1Q51', // Ummu Lathifah
            '01M60M046T0KM8TKEKR3KT5S3J', // Nadira
            '01M60M0448CWWHBDPC68QMS4C5', // Eri Fitri Nur Azizah
            '01M60M047D9HRT1FZBA0AX3506', // Budi Trisnayanti
            '01M60M044CZW30TAZJHT24XN49', // Zulaikha Banatun Karimah
        ]
    ],
    [
        'name' => 'Kelompok 5 (PSI)',
        'theme' => 'Akidah, Syariah, dan Akhlak Islam',
        'leader_id' => '01M60M0466Q6MQ2RQSQQN6VZDJ', // Nurul Saifa Zuhrita
        'members' => [
            '01M60M0466Q6MQ2RQSQQN6VZDJ', // Nurul Saifa Zuhrita (Leader)
            '01M60M0469RYGWD8A499HSH1NG', // Indri Dwi Wijayanti
            '01M60M04781YJE769QVAYB8C8D', // Aisyah
            '01M60M046YF5BRCRWRX52H2YXT', // Emi Anggaraeta Wilujeng
            '01M5TXE1FSDG0VN2JFDGG3FHTF', // Ahya Farida Surya
            '01M60M0486W7EPDREGHR9EG5E2', // Sri Wahyuni
            '01M60M048YEAJBGRMR7EEQD16C', // Trihastuti Masrikha
        ]
    ],
    [
        'name' => 'Kelompok 6 (PSI)',
        'theme' => 'Nabi Muhammad SAW sebagai Uswah Hasanah',
        'leader_id' => '01M5TXE359AFKNAMGT1Z8PKV13', // Lora Vistinia
        'members' => [
            '01M5TXE359AFKNAMGT1Z8PKV13', // Lora Vistinia (Leader)
            '01M60M046JE7GSZ0PFX8C037TD', // Raisya Jasmine Abigail
            '01M60M047J8CMVZTECYBHY82XY', // Juwita Setiyanisih
            '01M60M047BXT17XKRGMWR50T0R', // Hawwa
            '01M60M0456N7JTBS2PABTNFTPY', // Karismawati Rizka Spespatri
            '01M5TXDYKSAJSGFTFDC1KK1ZSA', // Eka Fitria Nazliati
            '01M60M0495H5NM5DPQG07RR1QD', // Nuraini Agustin
        ]
    ],
    [
        'name' => 'Kelompok 7 (PSI)',
        'theme' => 'Islam dan Pengembangan Ilmu Pengetahuan',
        'leader_id' => '01M60M0489ZZAGB3KV9VDTQK14', // Nurul Adinda
        'members' => [
            '01M60M0489ZZAGB3KV9VDTQK14', // Nurul Adinda (Leader)
            '01M60M044TMF63A4JE33M25DFH', // Kayla Aurellya
            '01M60M0461NH396Z1PYHWM913V', // Ayuni Nanda Meriska
            '01M60M04732R4PZ7JAB155VME4', // Nabilla Ramadhani
            '01M5TXE41AAPAGV2P8D38XQJBS', // Firsta Haura Tsabita
            '01M60M0476AH7C5XTJM9ZW1HZE', // Tsaqifah Atsarut Tarbiyyah
        ]
    ],
    [
        'name' => 'Kelompok 8 (HKI)',
        'theme' => 'Islam Berkemajuan sebagai Paradigma Muhammadiyah',
        'leader_id' => '01M60M04984V2CZHFTQ56Q2CT8', // Safari Hasan
        'members' => [
            '01M60M04984V2CZHFTQ56Q2CT8', // Safari Hasan (Leader)
            '01M60M049AFNVYV634RQ86HY0B', // Usamah Al Ghozi
            '01M60M049CTFJSQ24H349CJAV3', // M. Abdurrahman Brilianto
            '01M60M049RGER7TA302AMK5MBC', // Faza Fatiyurrobbany
            '01M60M04B4X9S9FTCDKRQFMMNE', // Muhammad Virginiawan L
        ]
    ],
    [
        'name' => 'Kelompok 9 (HKI)',
        'theme' => 'Muhammadiyah sebagai Gerakan Islam, Dakwah, dan Tajdid',
        'leader_id' => '01M60M04AR9G1FC9XTPJWBGPEY', // Mia Dwi Jayanti
        'members' => [
            '01M60M04AR9G1FC9XTPJWBGPEY', // Mia Dwi Jayanti (Leader)
            '01M60M04AT5D5AYFARKQ6H9T03', // Utari Handayani Dede Hasan
            '01M60M04AYGYEXQWV00WQZXGRJ', // Nunung Irawati
            '01M60M04ANRWQ770WWC0M66VK4', // Denisa Kaffa Vidina
        ]
    ],
    [
        'name' => 'Kelompok 10 (ES)',
        'theme' => 'Akidah Islam dan Pembentukan Akhlakul Karimah',
        'leader_id' => '01M60M04E7ZT8PWMQBQBN3TSWS', // Moh Samsul Arifin
        'members' => [
            '01M60M04E7ZT8PWMQBQBN3TSWS', // Moh Samsul Arifin (Leader)
            '01M60M04D1657MXQA4Q8H9XMVC', // Herjuno Hamzah Fanani
            '01M60M04DAPKZPDJ3A4XDY7PA3', // M. Khoirul Anam
            '01M60M04DEB1M5KMQDJNQ65F9Z', // Achmad Syafii
            '01M60M04DHPBY0GTNT22Z5RA0Y', // Moh. Royyan Al Firdaus
            '01M60M04B8BMQMNXT352FNPMSM', // Muchtar Abdul Kois
            '01M60M04C4SVRB9VD60QSF66NW', // Muhajjatul Intizom
            $thoriqId, // Thoriq Ibrahim
        ]
    ],
    [
        'name' => 'Kelompok 11 (ES)',
        'theme' => 'Islam Menghadapi Tantangan Zaman',
        'leader_id' => '01M60M04DSSP3WVBQZ9DW2BZH1', // Mohammad Hanifdzaki
        'members' => [
            '01M60M04DSSP3WVBQZ9DW2BZH1', // Mohammad Hanifdzaki (Leader)
            '01M60M04ECPJDGPY7EK59A3TSZ', // Risang Prisaltomo
            '01M60M04BAZ9198Q64MW3HNXXE', // Nur Fithryyah Ningsha
            '01M60M04BPSBSSZHN208C0173T', // Nisaul Azizah
            '01M60M04CQHSWZV09HRKCXWHB9', // Nur Aulia
            '01M60M04CDREGXA1Q2ZDTV5GAP', // Dik Dik Romadon
            '01M60M04CV8SPGKWT4N5H4C3EF', // Wildan Al Muzakki
        ]
    ],
    [
        'name' => 'Kelompok 12 (ES)',
        'theme' => 'Aktualisasi Nilai AIK dalam Kehidupan',
        'leader_id' => '01M60M04CY51XF4APV6A621MNQ', // Sofyanti
        'members' => [
            '01M60M04CY51XF4APV6A621MNQ', // Sofyanti (Leader)
            '01M60M04EJ05HRWPDWVBC75VZT', // Kaisa Syakira
            '01M60M04D4G5DC1GY1DQAXGM7Y', // Dini Rosyanti
            '01M60M04D728FRWMQTA1DDP756', // Indira Indah Syafitri
            '01M60M04DMKZTZ9809MHDTVDEM', // Iyus Silpiah
            '01M60M04E2VEH2RA0B7YK6F2MB', // Salman Januar Alfarizi
            '01M60M04DQHQCX2GXSGC6Q6Q0V', // Saniatun Rodhiyah
        ]
    ],
];

// Clean up existing groups for AIK if any
$existingGroupIds = DB::table('sheet_groups')->where('course_id', $courseId)->pluck('id');
if ($existingGroupIds->isNotEmpty()) {
    DB::table('sheet_group_members')->whereIn('group_id', $existingGroupIds)->delete();
    DB::table('sheet_groups')->whereIn('id', $existingGroupIds)->delete();
    echo "Cleaned up {$existingGroupIds->count()} old AIK groups.\n";
}

$cleanupSql = "-- Hapus kelompok lama untuk AIK jika ada\n";
$cleanupSql .= "DELETE FROM `sheet_group_members` WHERE `group_id` IN (SELECT `id` FROM `sheet_groups` WHERE `course_id` = '{$courseId}');\n";
$cleanupSql .= "DELETE FROM `sheet_groups` WHERE `course_id` = '{$courseId}';\n\n";

$totalMembersCount = 0;

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
        $totalMembersCount++;
    }

    $leaderName = DB::table('sheet_users')->where('id', $g['leader_id'])->value('name');
    echo "Created {$g['name']} (Leader: {$leaderName}, " . count($g['members']) . " members)\n";
}

echo "\nTotal Groups Created: " . count($groupsData) . "\n";
echo "Total Member Allocations: " . $totalMembersCount . "\n";

// 4. Generate SQL File for Production
$sqlContent = "-- ====================================================\n";
$sqlContent .= "-- IMPORT DATA KELOMPOK AIK (1-12)\n";
$sqlContent .= "-- Mata Kuliah: AIK (01M60M04G1HH3YBT1AFSGFVS3H)\n";
$sqlContent .= "-- Prodi: S1 Psikologi Islam, S1 Hukum Keluarga Islam, S1 Ekonomi Syari'ah\n";
$sqlContent .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- ====================================================\n\n";

if (!empty($sqlInserts)) {
    $sqlContent .= "-- 1. DATA MAHASISWA BARU (THORIQ IBRAHIM)\n";
    $sqlContent .= implode("\n", $sqlInserts) . "\n\n";
}

$sqlContent .= "-- 2. CLEANUP DATA LAMA\n";
$sqlContent .= $cleanupSql;

$sqlContent .= "-- 3. DATA KELOMPOK AIK (1-12)\n";
$sqlContent .= implode("\n", $groupSqlInserts) . "\n\n";

$sqlContent .= "-- 4. DATA ANGGOTA KELOMPOK AIK\n";
$sqlContent .= implode("\n", $memberSqlInserts) . "\n\n";

$sqlPath = __DIR__ . '/../import_aik_groups.sql';
file_put_contents($sqlPath, $sqlContent);

echo "\nSQL export generated at: " . realpath($sqlPath) . "\n";
