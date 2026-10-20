# Instruksi Proyek

## Alur Git

- Setelah menyelesaikan setiap perubahan kode, jalankan test dan lint yang relevan.
- Setelah verifikasi lulus, langsung stage file yang berubah, buat commit dengan pesan yang jelas, lalu push ke `origin main` tanpa menunggu instruksi tambahan.
- Jangan commit file rahasia, kredensial, data lokal, atau perubahan yang belum selesai.
- Jika push gagal karena masalah koneksi, coba ulang dengan `git -c http.version=HTTP/1.1 -c http.postBuffer=524288000 push origin main`.
