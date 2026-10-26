# Psikologi Connect

Portal akademik **Prodi Psikologi STIT Muhammadiyah Kediri** berbasis **Laravel 12 + Filament 5**. Data utama disimpan di **MySQL**, dengan situs publik dan satu panel terpadu `/app` untuk admin serta mahasiswa. Login mendukung beberapa Prodi melalui pilihan dropdown yang wajib diisi.

## Fitur

### Situs publik
- Beranda dengan informasi program studi dan pengumuman terbaru.
- Daftar pengumuman dan halaman detail.
- Hanya menampilkan pengumuman dengan audiens `all`.
- Tautan masuk ke panel aplikasi.

### Akses mahasiswa
- Login dengan Prodi, email/NIM, dan password di `/app/login`.
- Dashboard berisi ringkasan tugas, jadwal, kelompok, dan pengumuman sesuai Prodi.
- Jadwal kuliah sesuai Prodi dan kelas mahasiswa, termasuk ruangan atau tautan Zoom/Google Meet untuk sesi online.
- Daftar tugas individu dan kelompok hanya dari mata kuliah Prodi mahasiswa.
- Tugas dapat memuat pertemuan, materi, banyak kelompok, tanggal mulai, deadline, keterangan deadline, link pengumpulan, dan status.
- Pengumpulan tugas berupa tautan, dengan status `submitted`, `late`, atau `graded`.
- Tugas kelompok dikumpulkan satu kali per kelompok oleh salah satu anggota sebagai perwakilan; anggota lain melihat status, tautan, nilai, dan feedback yang sama.
- Perwakilan dapat memperbarui pengumpulan sampai deadline; setelah deadline perubahan hanya dapat dilakukan Admin.
- Melihat nilai dan feedback dosen.
- Daftar kelompok beserta peran, ketua, dan anggota, hanya dari mata kuliah Prodi mahasiswa.
- Pengumuman untuk mahasiswa, termasuk target Prodi tertentu atau semua Prodi.
- Lonceng notifikasi dengan panel geser dari kanan dan halaman Notifikasi untuk tugas aktif serta jadwal hari ini dan besok.
- Status baca notifikasi tersimpan per akun di tabel `sheet_notification_reads`.
- Profil dan perubahan password.

### Panel staff
- Satu panel di `/app`; navigasi dan halaman menyesuaikan role pengguna.
- Dashboard ringkasan data.
- Menu diurutkan dari yang paling operasional: **Akademik**, **Administrasi**, **Konten**, **Informasi**, **Akun**, dan **Sistem**.
- Menu **Administrasi** berisi pendataan mahasiswa dan CRUD Program Studi.
- Pendataan mahasiswa melalui **Administrasi > Mahasiswa**: Prodi, Nama Mahasiswa, NIM, email, kelas, angkatan, WhatsApp, dan akun login.
- CRUD user, mata kuliah, jadwal offline/online (Zoom atau Google Meet), kelompok, tugas, pengumpulan/nilai, dan pengumuman.
- Form tugas mendukung pertemuan, instruksi, materi, banyak kelompok, tanggal mulai, deadline, keterangan deadline, link pengumpulan, dan status `Draf`/`Aktif`/`Selesai`.
- Mata kuliah dapat ditetapkan ke beberapa Prodi; satu baris mata kuliah tetap memiliki satu kelas.
- Pengumuman mahasiswa dapat ditargetkan ke Prodi tertentu atau semua Prodi.
- Staff juga memilih Prodi saat login; staff tetap dapat melihat data seluruh Prodi.
- Role user: **Mahasiswa**, **Super Admin**, dan **Admin**.
- Admin memiliki akses yang sama dengan Super Admin. Nilai role lama `pjmk` dipertahankan agar data tetap kompatibel, tetapi UI menampilkan **Admin**.
- Mahasiswa hanya melihat halaman personalnya; staff tidak dapat membuka halaman personal mahasiswa dan sebaliknya.
- Reset password user.
- Lonceng dengan panel notifikasi geser dari kanan dan halaman Notifikasi global untuk tugas aktif serta jadwal hari ini dan besok.
- Pengaturan identitas program, kontak, dan konten halaman publik.
- Penghapusan menggunakan soft delete; riwayat tetap ada di database.

### API v1
- REST API `/api/v1` untuk aplikasi mobile atau klien eksternal, memakai data yang sama dengan panel.
- Bearer token per pengguna dengan masa berlaku, daftar token, logout, dan pencabutan token.
- Endpoint publik, mahasiswa, dan staff sesuai role.
- Dukungan pagination, pencarian, filter, dan sorting pada endpoint daftar.
- Spesifikasi OpenAPI lengkap di `docs/openapi.yaml`.

## Stack

- PHP 8.2+
- Laravel 12
- Filament 5
- `google/apiclient`
- Tailwind CSS 4 + Vite 7
- PHPUnit 11

Database aplikasi memakai MySQL dengan nama database `psikologiproject-app`. Seluruh data akademik disimpan di tabel `sheet_*` yang dapat diisi dari migrasi Google Sheets, file JSON demo, atau input langsung melalui aplikasi.

## Kebutuhan Sistem

- PHP 8.2 dengan ekstensi `openssl`, `pdo`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `intl`, dan `zip`.
- Composer 2.
- MySQL atau MariaDB.
- Node.js 20+ dan npm.
- Akun Google Cloud hanya jika ingin mengimpor data dari Google Sheets.

## Menjalankan Mode Demo

Mode demo menulis data contoh ke MySQL, sehingga tidak memerlukan kredensial Google. Data contoh juga tersedia di `storage/app/private/demo-sheets.json` untuk diimpor dengan `php artisan sheets:migrate-to-mysql --source=local --fresh`.

Buat database MySQL terlebih dahulu:

```sql
CREATE DATABASE `psikologiproject-app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` pada `.env` bila perlu, lalu jalankan:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
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
| Admin | `pjmk@psikologi.test` | `password` |
| Mahasiswa | `ayu@psikologi.test` | `password` |
| Mahasiswa | `bima@psikologi.test` | `password` |
| Mahasiswa | `citra@psikologi.test` | `password` |
| Mahasiswa | `dimas@psikologi.test` | `password` |

Masuk melalui `http://localhost:8000/app/login` menggunakan email atau NIM, misalnya `ayu@psikologi.test` atau `2024001`, dengan pilihan Prodi **S1 Psikologi**. Data demo selalu diarahkan ke Prodi tersebut.

Gunakan `php artisan sheets:setup --demo` lagi untuk menyegarkan data demo. Data yang sudah ada tidak diduplikasi; header baru seperti `prodi_id`, `courses.prodi_ids`, dan `announcements.prodi_ids` akan ditambahkan otomatis. Mata kuliah lama tanpa `prodi_ids` diarahkan ke Prodi default **Psikologi**.

## API v1

API REST tersedia di `/api/v1` untuk aplikasi mobile atau klien lain. Spesifikasi lengkap beserta schema dan contoh request/response ada di [`docs/openapi.yaml`](docs/openapi.yaml). API memakai data MySQL yang sama dengan panel, bukan database terpisah.

### Base URL

```text
http://127.0.0.1:8000/api/v1
```

Ganti host dengan domain aplikasi saat produksi. Gunakan HTTPS di produksi karena token dikirim melalui header `Authorization`.

### Autentikasi

Login membutuhkan Prodi aktif. ID Prodi demo adalah `01M5C0V9EWP828SVQ4W8CRKD1T` dan dapat diambil dari endpoint publik `GET /study-programs`.

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "login": "ayu@psikologi.test",
    "password": "password",
    "prodi_id": "01M5C0V9EWP828SVQ4W8CRKD1T",
    "device_name": "Flutter Android",
    "expires_in_days": 30
  }'
```

Respons berisi `access_token` plaintext yang hanya ditampilkan sekali:

```json
{
  "token_type": "Bearer",
  "access_token": "v1.01M5C0V9EWP828SVQ4W8CRKD1T.abcdefghijklmnopqrstuvwxyz0123456789",
  "expires_at": "2026-11-22T10:00:00+07:00",
  "token": { "id": "01M5C0V9EWP828SVQ4W8CRKD1T", "name": "Flutter Android" },
  "user": { "id": "01M5BFPTQ2MKR8ASWTCW9V0AZ6", "name": "Ayu Lestari", "role": "mahasiswa" }
}
```

Gunakan token pada request berikutnya:

```bash
curl http://127.0.0.1:8000/api/v1/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"
```

Token disimpan sebagai hash di tabel `sheet_api_tokens`. Logout mencabut token yang sedang dipakai. Ganti password sendiri mencabut token lain; reset password oleh staff mencabut semua token pengguna. Masa berlaku default 30 hari dan dapat diatur dengan `API_TOKEN_TTL_DAYS`.

### Role dan endpoint

| Grup | Prefix | Role | Keterangan |
| --- | --- | --- | --- |
| Publik | `/` | Tanpa token | Informasi API, Prodi aktif, pengumuman publik, pengaturan publik |
| Auth | `/auth` | Semua pengguna | Login, logout, profil, password, daftar/cabut token |
| Notifikasi | `/notifications` | Semua pengguna | Daftar notifikasi dan status baca |
| Mahasiswa | `/student` | `mahasiswa` | Dashboard, mata kuliah, jadwal, tugas, pengumpulan, kelompok, pengumuman |
| Staff | `/staff` | `pjmk`, `super_admin` | Dashboard, pengguna, Prodi, mata kuliah, jadwal, kelompok, tugas, pengumpulan/nilai, pengumuman, pengaturan |

Ringkasan resource staff: `users` dan `students`, `study-programs`, `courses`, `schedules`, `groups` (termasuk anggota), `tasks`, `submissions`, `announcements`, dan `settings`. Semua CRUD staff memakai ULID pada path `{id}`.

```bash
# Contoh data mahasiswa
curl "http://127.0.0.1:8000/api/v1/student/tasks?per_page=25&sort=deadline" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"

# Contoh data staff
curl "http://127.0.0.1:8000/api/v1/staff/students?role=mahasiswa&search=ayu" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <staff_access_token>"
```

### Pagination, pencarian, dan filter

Endpoint daftar menerima `page`, `per_page` (maksimal 100), `search`, `sort`, dan `direction=asc|desc` sesuai resource. Filter tambahan mencakup `prodi_id`, `class_name`, `semester`, `course_id`, `day`, `mode`, `type`, `status`, `task_id`, `user_id`, `group_id`, `audience`, `role`, dan `active`.

Respons daftar:

```json
{
  "data": [],
  "meta": { "current_page": 1, "per_page": 25, "last_page": 3, "total": 61, "from": 1, "to": 25 },
  "links": { "first": "...", "prev": null, "next": "...", "last": "..." }
}
```

### Error dan batas request

- `401` token tidak ada/tidak valid/kedaluwarsa/dicabut atau akun nonaktif.
- `403` role tidak sesuai (misalnya mahasiswa mengakses `/staff`).
- `404` data tidak ditemukan atau di luar akses mahasiswa.
- `422` validasi atau aturan bisnis gagal, misalnya deadline kelompok sudah lewat.
- `429` rate limit terlampaui.

Rate limit default: 60 request/menit per pengguna untuk endpoint terautentikasi dan 10 login/menit per IP. Atur dengan `API_RATE_LIMIT_PER_MINUTE` dan `API_LOGIN_RATE_LIMIT_PER_MINUTE`. CORS diatur melalui `API_ALLOWED_ORIGINS` (default `*`).

Contoh error validasi:

```json
{
  "message": "The given data was invalid.",
  "errors": { "prodi_id": ["Pilih program studi yang aktif."] }
}
```

### Konfigurasi API

```dotenv
API_TOKEN_TTL_DAYS=30
API_RATE_LIMIT_PER_MINUTE=60
API_LOGIN_RATE_LIMIT_PER_MINUTE=10
API_DEFAULT_PER_PAGE=25
API_MAX_PER_PAGE=100
API_ALLOWED_ORIGINS=*
```

Jalankan `php artisan migrate` setelah menarik perubahan agar tabel `sheet_api_tokens` dan kolom baru tersedia.

## Migrasi dari Google Sheets (Opsional)

Penyimpanan utama aplikasi adalah MySQL. Bagian ini hanya diperlukan jika ingin mengimpor data yang sudah ada di Google Sheets. Setelah impor selesai, kembalikan `SHEETS_DRIVER=mysql`.

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

### 4. Atur `.env` dan impor

Gunakan Google Sheets hanya sebagai sumber data selama migrasi:

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
php artisan migrate
php artisan sheets:migrate-to-mysql --source=google --fresh
```

`sheets:setup` akan membuat tab yang belum ada, menambahkan header baru yang belum tersedia tanpa mengubah data lama, memigrasikan role lama `admin` menjadi `super_admin`, dan mengisi `prodi_ids` mata kuliah lama dengan Prodi default. Tanpa opsi `--demo`, command akan meminta data Super Admin awal beserta Prodinya.

Setelah impor berhasil, ubah `.env` menjadi:

```dotenv
SHEETS_DRIVER=mysql
SHEETS_MYSQL_PREFIX=sheet_
```

Lalu jalankan `php artisan optimize:clear`. Mulai saat itu aplikasi tidak lagi membaca atau menulis ke Google Sheets.

Untuk spreadsheet lama, akun yang sudah ada tidak diisi Prodinya secara otomatis. Setelah setup, isi Prodi admin pertama melalui CLI agar bisa login:

```bash
php artisan sheets:assign-prodi admin@example.ac.id --prodi=psikologi
```

Setelah admin bisa masuk, lengkapi Prodi dan role akun lain melalui menu **User**, atau data mahasiswa melalui **Administrasi > Mahasiswa**. Akun tanpa Prodi tidak dapat login. Role lama `admin` tetap diterima kode sebagai fallback dan akan diubah menjadi `super_admin` saat setup dijalankan.

### 5. Verifikasi

```bash
php artisan sheets:check
```

Perintah ini menampilkan driver, koneksi/database (atau detail spreadsheet saat memakai driver Google), daftar tabel/tab, jumlah header, dan jumlah baris.

## Struktur Tabel MySQL

Kolom setiap tabel didefinisikan di `config/sheets.php`:

| Tabel | Isi |
| --- | --- |
| `sheet_study_programs` | Daftar Prodi, kode, jenjang, dan status aktif |
| `sheet_users` | Akun Mahasiswa, Super Admin, dan Admin, termasuk `prodi_id` dan `role` |
| `sheet_courses` | Mata kuliah, termasuk `prodi_ids` berisi daftar ID Prodi |
| `sheet_schedules` | Jadwal kuliah, mode offline/online, platform, dan tautan rapat |
| `sheet_groups` | Kelompok mahasiswa |
| `sheet_group_members` | Anggota dan ketua kelompok |
| `sheet_tasks` | Tugas individu/kelompok, termasuk `group_ids`, pertemuan, materi, status, dan link pengumpulan |
| `sheet_submissions` | Pengumpulan, nilai, dan feedback; tugas kelompok memakai satu baris per `task_id` + `group_id` |
| `sheet_announcements` | Pengumuman, termasuk `prodi_ids` untuk target Prodi mahasiswa |
| `sheet_settings` | Pengaturan aplikasi |
| `sheet_notification_reads` | Status baca notifikasi per user dan kunci notifikasi |
| `sheet_api_tokens` | Token API tersimpan sebagai hash, masa berlaku, pemakaian terakhir, dan status pencabutan |

Jangan mengubah urutan atau nama kolom tanpa menyesuaikan `config/sheets.php` dan data yang sudah ada. `prodi_id` pada `sheet_users` dan `prodi_ids` pada `sheet_courses`/`sheet_announcements` merujuk ke `sheet_study_programs.id`. Kolom-kolom ini ditambahkan di akhir agar data lama tidak bergeser. `prodi_ids` berisi beberapa ULID yang dipisahkan koma; kosong pada pengumuman mahasiswa berarti semua Prodi.

`sheet_tasks.group_ids` berisi beberapa ID kelompok yang dipisahkan koma. Kolom `sheet_tasks.group_id` lama tetap dipertahankan untuk kompatibilitas. Status tugas: `draft` (tidak tampil di portal), `active` (tampil dan dapat dikumpulkan), serta `completed` (tampil tetapi read-only). Pengumpulan kelompok disimpan sekali per kombinasi `task_id` dan `group_id`; `user_id` pada baris tersebut adalah anggota yang menjadi perwakilan.

Notifikasi tidak disimpan sebagai daftar event. Aplikasi menghitung notifikasi langsung dari tugas aktif dan jadwal, lalu menyimpan hanya status baca per akun di `sheet_notification_reads`. Kunci tugas memakai `task:{id}:{timestamp deadline}`, sedangkan jadwal memakai `schedule:{id}:{tanggal}`; perubahan deadline atau pergantian hari dapat menghasilkan notifikasi baru. Tugas yang ditampilkan adalah tugas `active` dengan deadline pada pekan berjalan atau setelahnya, tanpa deadline tidak ditampilkan. Jadwal yang ditampilkan hanya jadwal hari ini dan besok.

## Perintah Penting

```bash
php artisan migrate               # buat tabel framework dan tabel sheet_*
php artisan sheets:migrate-to-mysql --source=local|google --fresh  # impor data ke MySQL
php artisan sheets:check          # cek driver, tabel, header, dan jumlah baris
php artisan sheets:setup          # buat tabel/kolom, Prodi awal, migrasi role, dan Prodi matkul lama
php artisan sheets:setup --demo   # buat tabel/kolom, migrasi role/matkul, dan isi data demo
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
| Login semua role | `/app/login` |
| Panel semua role | `/app` |
| Halaman staff | `/app/{slug}`, misalnya `/app/student-page` dan `/app/user-page` |
| Halaman mahasiswa | `/app/jadwal-saya`, `/app/tugas-saya`, `/app/kelompok-saya`, `/app/pengumuman-saya`, `/app/profil-saya`, `/app/notifikasi-page` |
| Redirect login lama | `/login` → `/app/login` |
| Redirect panel lama | `/admin`, `/portal` → `/app` |

URL lama di bawah `/admin/*` dan `/portal/*` tetap diarahkan ke halaman yang sesuai di `/app` agar bookmark lama tidak rusak.

## Arsitektur Singkat

- `app/Sheets/SpreadsheetGateway.php`: kontrak akses data spreadsheet.
- `app/Sheets/MysqlSheetGateway.php`: implementasi utama berbasis tabel MySQL `sheet_*`.
- `app/Sheets/GoogleSheetsGateway.php`: implementasi Google Sheets API untuk sumber migrasi.
- `app/Sheets/LocalSheetGateway.php`: implementasi JSON lokal untuk demo, impor, dan test.
- `app/Console/Commands/MigrateSheetsToMysql.php`: impor data Google Sheets/JSON ke MySQL.
- `app/Repositories/`: repository per domain dengan cache, ULID, dan soft delete.
- `app/Repositories/StudyProgramRepository.php`: daftar Prodi dan opsi login.
- `app/Support/ProdiAssignments.php`: parsing, validasi, dan label daftar Prodi pada kolom `prodi_ids`.
- `app/Support/TaskGroups.php`: parsing, validasi, dan label kelompok pada kolom `group_ids`.
- `app/Support/TaskStatus.php`: normalisasi dan label status tugas `Draf`/`Aktif`/`Selesai`.
- `app/Support/NotificationCenter.php`: perhitungan notifikasi tugas dan jadwal per user.
- `app/Repositories/NotificationReadRepository.php`: status baca notifikasi di database.
- `app/Livewire/NotificationBell.php`: lonceng dan panel notifikasi geser kanan pada topbar panel.
- `app/Auth/SheetUser.php` dan `SheetUserProvider.php`: autentikasi tanpa Eloquent, termasuk validasi Prodi dan role.
- `app/Enums/UserRole.php`: definisi role Mahasiswa, Super Admin, dan Admin.
- `app/Providers/Filament/AppPanelProvider.php`: konfigurasi panel terpadu `/app`, navigasi, warna, dan login.
- `app/Filament/Support/BaseSheetPage.php`: halaman tabel Filament berbasis array record.
- `app/Filament/Pages/`: dashboard bersama, halaman staff, User, Program Studi, pendataan Mahasiswa, dan login.
- `app/Console/Commands/AssignProdi.php`: pengisian Prodi akun lama melalui CLI.
- `app/Filament/Portal/Pages/`: halaman personal mahasiswa di panel yang sama.
- `app/Http/Controllers/`: controller halaman publik.
- `routes/api.php`: seluruh route REST `/api/v1`.
- `app/Http/Controllers/Api/V1/`: controller API publik, auth, notifikasi, mahasiswa, dan staff.
- `app/Repositories/ApiTokenRepository.php`: penerbitan, validasi, pemakaian terakhir, dan pencabutan token.
- `app/Support/ApiPresenter.php`: transformasi record database menjadi respons API tanpa field sensitif.
- `app/Http/Middleware/ForceJsonResponse.php` dan `EnsureApiRole.php`: JSON response dan pembatasan role API.
- `docs/openapi.yaml`: spesifikasi OpenAPI 3.0 seluruh endpoint API.
- `app/Support/DemoData.php`: data demo.
- `config/sheets.php`: konfigurasi driver dan seluruh header tab.

Setiap pembacaan tabel di-cache selama `SHEETS_CACHE_TTL` detik (default 60). Setelah operasi tulis melalui aplikasi, cache tabel terkait dibersihkan. Perubahan langsung di database dapat terlihat terlambat sampai cache kedaluwarsa atau cache dibersihkan.

## Batasan yang Diketahui

- Tabel `sheet_*` adalah mirror data spreadsheet: kolom bertipe `longtext` dan belum memakai foreign key atau constraint relasional.
- Gateway MySQL belum memakai row locking; penulisan bersamaan pada baris yang sama dapat saling menimpa.
- Pengumpulan tugas berupa tautan; aplikasi tidak mengunggah atau menyimpan file.
- Pengumpulan kelompok memakai satu perwakilan per kelompok. Perwakilan adalah anggota pertama yang mengumpulkan dan hanya dia yang dapat memperbarui sampai deadline.
- Tidak ada registrasi publik, OAuth, atau remember-me. Notifikasi hanya tersedia di dalam aplikasi; tidak ada email, WhatsApp, atau browser push.
- Kuota Google Sheets API hanya berlaku saat memakai Google Sheets sebagai sumber migrasi; aplikasi utama membaca MySQL. Notifikasi dihitung ulang setiap kali halaman dimuat.
- Pengumuman publik hanya menampilkan audiens `all`.
- Panel memakai halaman custom, bukan Filament Resource Eloquent.
- Admin dapat melihat data semua Prodi; pembatasan Prodi hanya berlaku untuk mahasiswa.
- Satu baris mata kuliah hanya memiliki satu kelas. Mata kuliah gabungan yang dipakai beberapa kelas/Prodi perlu dibuat sebagai baris terpisah.
- Akun lama tanpa Prodi harus diisi manual dan tidak bisa login sebelum diisi.
- API memakai Bearer token statis tanpa refresh token; klien harus login ulang setelah token kedaluwarsa atau dicabut.
- API tidak menyediakan unggah file; pengumpulan tugas tetap berupa tautan.
- Rate limit API dapat menghasilkan `429` saat banyak klien berbagi IP yang sama.

## Testing

```bash
php artisan test
```

Test menggunakan `LocalSheetGateway` dengan file JSON sementara dan SQLite in-memory. Test tidak menghubungi Google Sheets atau MySQL produksi. Cakupan saat ini meliputi:

- Render halaman publik.
- Visibilitas pengumuman publik.
- Login Prodi + email/NIM dan pembatasan peran Mahasiswa, Super Admin, serta Admin.
- Pemisahan akses halaman staff dan mahasiswa dalam panel `/app`, termasuk redirect URL lama.
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
- Gateway MySQL: pembuatan tabel, baca/tulis/update baris, batch read, dan health check.
- Perintah impor spreadsheet ke MySQL beserta pemetaan kolom lama.
- API publik, login email/NIM, validasi Prodi, token kedaluwarsa/dicabut, daftar/cabut token, profil, dan ganti password.
- Pembatasan role API: mahasiswa tidak dapat membuka endpoint staff dan sebaliknya.
- API mahasiswa: dashboard, mata kuliah, jadwal, tugas, pengumpulan individu, kelompok, dan notifikasi.
- API staff: dashboard, pengaturan, pengguna, Prodi, mata kuliah, jadwal, kelompok/anggota, tugas, nilai, dan pengumuman.

## Troubleshooting

### `Vite manifest not found`
Jalankan:

```bash
npm install
npm run build
```

### `Sheet [users] belum dibuat` atau kolom kurang
Jalankan:

```bash
php artisan migrate
php artisan sheets:setup
```

`php artisan migrate` membuat tabel `sheet_*`, sedangkan `sheets:setup` melengkapi kolom baru seperti `prodi_id` atau `prodi_ids` tanpa mengubah data lama. Cek hasilnya dengan `php artisan sheets:check`.

### Data lama masih berasal dari Google Sheets
Pastikan `.env` memakai `SHEETS_DRIVER=mysql`, lalu jalankan:

```bash
php artisan optimize:clear
php artisan sheets:migrate-to-mysql --source=google --fresh
```

Perintah impor hanya menyalin data; aktifkan driver MySQL setelah impor selesai.

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
