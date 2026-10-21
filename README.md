# Psikologi Connect

Portal akademik **Prodi Psikologi STIT Muhammadiyah Kediri** berbasis **Laravel 12 + Filament 5**. Data utama disimpan di **Google Sheets**, dengan situs publik, portal mahasiswa, dan panel admin dalam satu aplikasi. Login mendukung beberapa Prodi melalui pilihan dropdown yang wajib diisi.

## Fitur

### Situs publik
- Beranda dengan informasi program studi dan pengumuman terbaru.
- Daftar pengumuman dan halaman detail.
- Hanya menampilkan pengumuman dengan audiens `all`.
- Tautan masuk ke portal mahasiswa.

### Portal mahasiswa
- Login dengan Prodi, email/NIM, dan password.
- Dashboard berisi ringkasan tugas, jadwal, kelompok, dan pengumuman sesuai Prodi.
- Jadwal kuliah sesuai Prodi dan kelas mahasiswa, termasuk ruangan atau tautan Zoom/Google Meet untuk sesi online.
- Daftar tugas individu dan kelompok hanya dari mata kuliah Prodi mahasiswa.
- Tugas dapat memuat pertemuan, materi, banyak kelompok, tanggal mulai, deadline, keterangan deadline, link pengumpulan, dan status.
- Pengumpulan tugas berupa tautan, dengan status `submitted`, `late`, atau `graded`.
- Tugas kelompok dikumpulkan satu kali per kelompok oleh salah satu anggota sebagai perwakilan; anggota lain melihat status, tautan, nilai, dan feedback yang sama.
- Perwakilan dapat memperbarui pengumpulan sampai deadline; setelah deadline perubahan hanya dapat dilakukan admin/PJMK.
- Melihat nilai dan feedback dosen.
- Daftar kelompok beserta peran, ketua, dan anggota, hanya dari mata kuliah Prodi mahasiswa.
- Pengumuman untuk mahasiswa, termasuk target Prodi tertentu atau semua Prodi.
- Lonceng notifikasi dan halaman Notifikasi untuk tugas aktif serta jadwal hari ini dan besok.
- Status baca notifikasi tersimpan per akun di tab `notification_reads`.
- Profil dan perubahan password.

### Panel admin
- Dashboard ringkasan data.
- Menu diurutkan dari yang paling operasional: **Akademik**, **Administrasi**, **Konten**, lalu **Akun** dan **Sistem**.
- Menu **Administrasi** berisi pendataan mahasiswa dan CRUD Program Studi.
- Pendataan mahasiswa melalui **Administrasi > Mahasiswa**: Prodi, Nama Mahasiswa, NIM, email, kelas, angkatan, WhatsApp, dan akun login.
- CRUD user, mata kuliah, jadwal offline/online (Zoom atau Google Meet), kelompok, tugas, pengumpulan/nilai, dan pengumuman.
- Form tugas mendukung pertemuan, instruksi, materi, banyak kelompok, tanggal mulai, deadline, keterangan deadline, link pengumpulan, dan status `Draf`/`Aktif`/`Selesai`.
- Mata kuliah dapat ditetapkan ke beberapa Prodi; satu baris mata kuliah tetap memiliki satu kelas.
- Pengumuman mahasiswa dapat ditargetkan ke Prodi tertentu atau semua Prodi.
- Login admin juga memilih Prodi; admin tetap dapat melihat data seluruh Prodi.
- Role user: **Mahasiswa**, **Super Admin**, dan **PJMK**.
- PJMK memiliki akses panel yang sama dengan Super Admin, tetapi role tetap ditampilkan sebagai PJMK.
- Reset password user.
- Lonceng dan halaman Notifikasi global untuk tugas aktif serta jadwal hari ini dan besok.
- Pengaturan identitas program, kontak, dan konten halaman publik.
- Penghapusan menggunakan soft delete; riwayat tetap ada di spreadsheet.

## Stack

- PHP 8.2+
- Laravel 12
- Filament 5
- `google/apiclient`
- Tailwind CSS 4 + Vite 7
- PHPUnit 11

Database aplikasi tidak menyimpan data akademik. Konfigurasi default memakai SQLite in-memory; data utama berada di Google Sheets atau file JSON lokal saat mode demo.

## Kebutuhan Sistem

- PHP 8.2 dengan ekstensi `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `intl`, dan `zip`.
- Composer 2.
- Node.js 20+ dan npm.
- Akun Google Cloud jika ingin memakai Google Sheets.

## Menjalankan Mode Demo

Mode demo memakai `LocalSheetGateway` dan file `storage/app/private/demo-sheets.json`, sehingga tidak memerlukan kredensial Google.

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
npm run build
php artisan sheets:setup --demo
php artisan serve
```

Buka `http://localhost:8000`.

Akun demo:

| Peran | Login | Password |
| --- | --- | --- |
| Super Admin | `admin@psikologi.test` | `password` |
| PJMK | `pjmk@psikologi.test` | `password` |
| Mahasiswa | `ayu@psikologi.test` | `password` |
| Mahasiswa | `bima@psikologi.test` | `password` |
| Mahasiswa | `citra@psikologi.test` | `password` |
| Mahasiswa | `dimas@psikologi.test` | `password` |

Login dapat menggunakan email atau NIM, misalnya `ayu@psikologi.test` atau `2024001`, dengan pilihan Prodi **S1 Psikologi**. Data demo selalu diarahkan ke Prodi tersebut.

Gunakan `php artisan sheets:setup --demo` lagi untuk menyegarkan data demo. Data yang sudah ada tidak diduplikasi; header baru seperti `prodi_id`, `courses.prodi_ids`, dan `announcements.prodi_ids` akan ditambahkan otomatis. Mata kuliah lama tanpa `prodi_ids` diarahkan ke Prodi default **Psikologi**.

## Konfigurasi Google Sheets

### 1. Buat project Google Cloud
1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat project baru atau pilih project yang sudah ada.
3. Aktifkan **Google Sheets API** melalui menu **APIs & Services > Library**.

### 2. Buat service account
1. Buka **APIs & Services > Credentials**.
2. Pilih **Create credentials > Service account**.
3. Isi nama service account, lalu selesaikan proses pembuatan.
4. Buka service account tersebut, masuk ke tab **Keys**.
5. Pilih **Add key > Create new key > JSON**.
6. Simpan file JSON ke:

```text
storage/app/private/google/service-account.json
```

Atau simpan di lokasi lain dan sesuaikan `GOOGLE_SERVICE_ACCOUNT_JSON`.

Jangan commit file kredensial ke Git.

### 3. Buat spreadsheet
1. Buat spreadsheet baru di Google Sheets.
2. Salin ID dari URL:

```text
https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit
```

3. Klik **Share**, lalu tambahkan email service account sebagai **Editor**.
   Email service account dapat dilihat di file JSON pada field `client_email`, atau melalui:

```bash
php artisan sheets:check
```

### 4. Atur `.env`

```dotenv
SHEETS_DRIVER=google
GOOGLE_SHEETS_ID=isi_spreadsheet_id_di_sini
GOOGLE_SERVICE_ACCOUNT_JSON=storage/app/private/google/service-account.json
SHEETS_CACHE_TTL=60
```

Jalankan:

```bash
php artisan optimize:clear
php artisan sheets:check
php artisan sheets:setup
```

`sheets:setup` akan membuat tab yang belum ada, menambahkan header baru yang belum tersedia tanpa mengubah data lama, memigrasikan role lama `admin` menjadi `super_admin`, dan mengisi `prodi_ids` mata kuliah lama dengan Prodi default. Tanpa opsi `--demo`, command akan meminta data Super Admin awal beserta Prodinya.

Untuk spreadsheet lama, akun yang sudah ada tidak diisi Prodinya secara otomatis. Setelah setup, isi Prodi admin pertama melalui CLI agar bisa login:

```bash
php artisan sheets:assign-prodi admin@example.ac.id --prodi=psikologi
```

Setelah admin bisa masuk, lengkapi Prodi dan role akun lain melalui menu **User**, atau data mahasiswa melalui **Administrasi > Mahasiswa**. Akun tanpa Prodi tidak dapat login. Role lama `admin` tetap diterima kode sebagai fallback dan akan diubah menjadi `super_admin` saat setup dijalankan.

### 5. Verifikasi

```bash
php artisan sheets:check
```

Perintah ini menampilkan driver, judul spreadsheet, URL, email service account, daftar tab, jumlah header, dan jumlah baris.

## Struktur Tab Spreadsheet

Header setiap tab didefinisikan di `config/sheets.php`:

| Tab | Isi |
| --- | --- |
| `study_programs` | Daftar Prodi, kode, jenjang, dan status aktif |
| `users` | Akun Mahasiswa, Super Admin, dan PJMK, termasuk `prodi_id` dan `role` |
| `courses` | Mata kuliah, termasuk `prodi_ids` berisi daftar ID Prodi |
| `schedules` | Jadwal kuliah, mode offline/online, platform, dan tautan rapat |
| `groups` | Kelompok mahasiswa |
| `group_members` | Anggota dan ketua kelompok |
| `tasks` | Tugas individu/kelompok, termasuk `group_ids`, pertemuan, materi, status, dan link pengumpulan |
| `submissions` | Pengumpulan, nilai, dan feedback; tugas kelompok memakai satu baris per `task_id` + `group_id` |
| `announcements` | Pengumuman, termasuk `prodi_ids` untuk target Prodi mahasiswa |
| `settings` | Pengaturan aplikasi |
| `notification_reads` | Status baca notifikasi per user dan kunci notifikasi |

Jangan mengubah urutan atau nama header tanpa menyesuaikan `config/sheets.php` dan data yang sudah ada. `prodi_id` pada `users` dan `prodi_ids` pada `courses`/`announcements` merujuk ke `study_programs.id`. Kolom-kolom ini ditambahkan di akhir header agar data lama tidak bergeser. `prodi_ids` berisi beberapa ULID yang dipisahkan koma; kosong pada pengumuman mahasiswa berarti semua Prodi.

`tasks.group_ids` berisi beberapa ID kelompok yang dipisahkan koma. Kolom `tasks.group_id` lama tetap dipertahankan untuk kompatibilitas. Status tugas: `draft` (tidak tampil di portal), `active` (tampil dan dapat dikumpulkan), serta `completed` (tampil tetapi read-only). Pengumpulan kelompok disimpan sekali per kombinasi `task_id` dan `group_id`; `user_id` pada baris tersebut adalah anggota yang menjadi perwakilan.

Notifikasi tidak disimpan sebagai daftar event. Aplikasi menghitung notifikasi langsung dari tugas aktif dan jadwal, lalu menyimpan hanya status baca per akun di `notification_reads`. Kunci tugas memakai `task:{id}:{timestamp deadline}`, sedangkan jadwal memakai `schedule:{id}:{tanggal}`; perubahan deadline atau pergantian hari dapat menghasilkan notifikasi baru. Tugas yang ditampilkan adalah tugas `active` dengan deadline pada pekan berjalan atau setelahnya, tanpa deadline tidak ditampilkan. Jadwal yang ditampilkan hanya jadwal hari ini dan besok.

## Perintah Penting

```bash
php artisan sheets:check          # cek koneksi, tab, header, dan jumlah baris
php artisan sheets:setup          # buat tab/header, Prodi awal, migrasi role, dan Prodi matkul lama
php artisan sheets:setup --demo   # buat tab/header, migrasi role/matkul, dan isi data demo
php artisan sheets:assign-prodi {email} --prodi=psikologi  # isi Prodi akun lama
php artisan test                  # jalankan seluruh test
php artisan optimize:clear        # bersihkan cache config/route/view
npm run build                     # build asset produksi
npm run dev                       # Vite development server
```

## URL Aplikasi

| Halaman | URL |
| --- | --- |
| Beranda | `/` |
| Daftar pengumuman | `/pengumuman` |
| Detail pengumuman | `/pengumuman/{id}` |
| Redirect login | `/login` |
| Login admin | `/admin/login` |
| Panel admin | `/admin` |
| Login mahasiswa | `/portal/login` |
| Portal mahasiswa | `/portal` |

Halaman admin dan portal memakai slug default Filament, misalnya `/admin/student-page`, `/admin/user-page`, dan `/portal/tugas-page`.

## Arsitektur Singkat

- `app/Sheets/SpreadsheetGateway.php`: kontrak akses spreadsheet.
- `app/Sheets/GoogleSheetsGateway.php`: implementasi Google Sheets API.
- `app/Sheets/LocalSheetGateway.php`: implementasi JSON lokal untuk demo/test.
- `app/Repositories/`: repository per domain dengan cache, ULID, dan soft delete.
- `app/Repositories/StudyProgramRepository.php`: daftar Prodi dan opsi login.
- `app/Support/ProdiAssignments.php`: parsing, validasi, dan label daftar Prodi pada kolom `prodi_ids`.
- `app/Support/TaskGroups.php`: parsing, validasi, dan label kelompok pada kolom `group_ids`.
- `app/Support/TaskStatus.php`: normalisasi dan label status tugas `Draf`/`Aktif`/`Selesai`.
- `app/Support/NotificationCenter.php`: perhitungan notifikasi tugas dan jadwal per user.
- `app/Repositories/NotificationReadRepository.php`: status baca notifikasi di sheet.
- `app/Livewire/NotificationBell.php`: lonceng notifikasi pada topbar kedua panel.
- `app/Auth/SheetUser.php` dan `SheetUserProvider.php`: autentikasi tanpa Eloquent, termasuk validasi Prodi dan role.
- `app/Enums/UserRole.php`: definisi role Mahasiswa, Super Admin, dan PJMK.
- `app/Filament/Support/BaseSheetPage.php`: halaman tabel Filament berbasis array record.
- `app/Filament/Pages/`: halaman admin, User, Program Studi, pendataan Mahasiswa, dan login.
- `app/Console/Commands/AssignProdi.php`: pengisian Prodi akun lama melalui CLI.
- `app/Filament/Portal/Pages/`: halaman portal mahasiswa.
- `app/Http/Controllers/`: controller halaman publik.
- `app/Support/DemoData.php`: data demo.
- `config/sheets.php`: konfigurasi driver dan seluruh header tab.

Setiap pembacaan sheet di-cache selama `SHEETS_CACHE_TTL` detik (default 60). Setelah operasi tulis melalui aplikasi, cache tab terkait dibersihkan. Perubahan langsung di Google Sheets dapat terlihat terlambat sampai cache kedaluwarsa.

## Batasan yang Diketahui

- Google Sheets bukan database transaksional. Tidak ada row locking; penulisan bersamaan dapat saling menimpa.
- Pengumpulan tugas berupa tautan; aplikasi tidak mengunggah atau menyimpan file.
- Pengumpulan kelompok memakai satu perwakilan per kelompok. Perwakilan adalah anggota pertama yang mengumpulkan dan hanya dia yang dapat memperbarui sampai deadline.
- Tidak ada registrasi publik, OAuth, atau remember-me. Notifikasi hanya tersedia di dalam aplikasi; tidak ada email, WhatsApp, atau browser push.
- Kuota Google Sheets API berlaku; cache membantu mengurangi pembacaan. Notifikasi dihitung ulang setiap kali halaman dimuat.
- Pengumuman publik hanya menampilkan audiens `all`.
- Panel admin dan portal memakai halaman custom, bukan Filament Resource Eloquent.
- Admin dapat melihat data semua Prodi; pembatasan Prodi hanya berlaku di portal mahasiswa.
- Satu baris mata kuliah hanya memiliki satu kelas. Mata kuliah gabungan yang dipakai beberapa kelas/Prodi perlu dibuat sebagai baris terpisah.
- Akun lama tanpa Prodi harus diisi manual dan tidak bisa login sebelum diisi.

## Testing

```bash
php artisan test
```

Test menggunakan `LocalSheetGateway` dengan file JSON sementara. Test tidak menghubungi Google Sheets. Cakupan saat ini meliputi:

- Render halaman publik.
- Visibilitas pengumuman publik.
- Login Prodi + email/NIM dan pembatasan peran Mahasiswa, Super Admin, serta PJMK.
- Penolakan login dengan Prodi salah/kosong dan akun tanpa Prodi.
- CRUD Program Studi dan pencegahan hapus Prodi yang dipakai akun, mata kuliah, atau pengumuman.
- CRUD user untuk seluruh role, pendataan mahasiswa, dan proteksi admin aktif terakhir.
- CRUD mata kuliah multi-Prodi dan pengumuman dengan target Prodi.
- Filter jadwal, tugas, kelompok, dan pengumuman portal berdasarkan Prodi serta kelas.
- Notifikasi tugas/jadwal, scope per user, tautan rapat online, dan status baca.
- Tugas multi-kelompok, status draf/selesai, pengumpulan kelompok bersama, dan pembatasan revisi setelah deadline.
- Migrasi header lama, role `admin`, dan mata kuliah lama tanpa menggeser data.
- Render seluruh halaman admin dan portal.
- Mount action tabel dan form pengaturan/profil.
- CRUD admin, pengumpulan tugas, dan reset password.
- Repository soft delete/restore serta persistensi JSON.

## Troubleshooting

### `Vite manifest not found`
Jalankan:

```bash
npm install
npm run build
```

### `Sheet [users] belum dibuat` atau header kurang
Jalankan:

```bash
php artisan sheets:setup
```

Perintah ini membuat tab yang belum ada dan menambahkan header baru seperti `prodi_id` atau `prodi_ids` tanpa mengubah data lama. Cek hasilnya dengan `php artisan sheets:check`.

### Login gagal karena Prodi
- Pastikan Prodi yang dipilih aktif.
- Pastikan akun memiliki `prodi_id` yang sama dengan pilihan saat login.
- Untuk akun lama, jalankan `php artisan sheets:assign-prodi <email> --prodi=<kode>`.
- Prodi nonaktif tidak muncul di dropdown dan penggunanya tidak bisa login sampai diaktifkan.

### Google API 403 / permission denied
- Pastikan Google Sheets API sudah diaktifkan.
- Pastikan spreadsheet dibagikan ke email service account sebagai Editor.
- Pastikan `GOOGLE_SHEETS_ID` benar.

### Perubahan `.env` tidak terbaca
Jalankan:

```bash
php artisan optimize:clear
```

### Warning `Module "openssl" is already loaded`
Warning ini berasal dari konfigurasi PHP lokal dan tidak memblokir aplikasi. Periksa `php.ini` jika ingin menghilangkannya.

### Composer hang saat optimized autoload
Proyek ini memakai `"optimize-autoloader": false` di `composer.json`. Biarkan pengaturan tersebut kecuali sudah diuji dengan aman.

## Lisensi

Aplikasi ini menggunakan lisensi MIT.
