# 📸 WebP Converter (PHP & External API)

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**WebP Converter** adalah skrip mandiri (single-file) berbasis PHP yang berfungsi sebagai alat konversi gambar dari URL eksternal ke format WebP yang efisien. Alat ini dirancang untuk penggunaan pribadi, mengutamakan **keamanan** (dengan otentikasi kata sandi) dan **kinerja** (dengan memanfaatkan API pihak ketiga yang cepat untuk konversi).



## ✨ Fitur Utama

* **Otentikasi Aman:** Dilindungi kata sandi menggunakan `session` dan `password_verify` (hash yang kuat) untuk membatasi akses.
* **Persistent Login:** Fitur "Ingat Saya" menggunakan *cookie* yang aman.
* **Konversi Eksternal Cepat:** Menggunakan layanan `images.weserv.nl` yang andal dan cepat untuk melakukan konversi gambar ke WebP, mengurangi beban pemrosesan pada server Anda.
* **Watermarking Teks:** Mampu menambahkan watermark teks kustom (misalnya, hak cipta atau branding) ke hasil WebP menggunakan PHP GD Library.
* **Validasi Keamanan Tinggi:** Mencegah **Server-Side Request Forgery (SSRF)** dengan memblokir permintaan ke alamat IP privat atau terlarang.
* **Statistik Penggunaan:** Mencatat dan menampilkan jumlah total gambar yang telah dikonversi dan akumulasi total ukuran file yang disimpan.
* **Format yang Didukung:** Mendukung konversi dari JPEG, PNG, GIF, BMP, dan WebP (jika API mendukungnya) ke WebP.

## ⚙️ Persyaratan Sistem

* Server Web (Apache, Nginx, dll.)
* PHP 7.4 atau lebih tinggi
* Ekstensi PHP:
    * `session` (Umumnya aktif secara default)
    * `curl` (Diperlukan untuk mengambil gambar dari URL)
    * `gd` (Diperlukan untuk fungsionalitas Watermarking)

## 🚀 Instalasi & Konfigurasi

### 1. File Awal

1.  Buat direktori baru di server web Anda (misalnya, `webp-converter/`).
2.  Simpan kode PHP Anda sebagai `index.php` di direktori tersebut.
3.  Buat direktori baru bernama `img/` di root proyek Anda. Direktori ini harus memiliki izin tulis (**chmod 755** atau **777**, tergantung kebutuhan hosting Anda).
4.  Unduh font **Inter Regular** (atau font TTF lainnya) dan simpan sebagai `Inter-Regular.ttf` di direktori yang sama dengan `index.php`.

### 2. Konfigurasi Kata Sandi (PENTING!)

Anda **harus** mengubah *placeholder* hash kata sandi di baris konfigurasi.

1.  Akses shell server Anda atau gunakan skrip PHP sementara dan jalankan kode berikut untuk mendapatkan hash yang aman:
    ```php
    <?php
    echo password_hash('MASUKKAN_KATA_SANDI_ANDA_DI_SINI', PASSWORD_DEFAULT);
    ?>
    ```
2.  Salin output string hash (contoh: `$2y$10$tJ08s.r8Qo1E9zS65n4bB.pP9N4P3zW6tJ08s.r8Qo1E9`) dan ganti nilai `PASSWORD_HASH` di `index.php`:

    ```php
    // Ganti hash ini dengan hash dari kata sandi Anda.
    define('PASSWORD_HASH', 'PASTE_HASH_ANDA_DI_SINI');
    ```

### 3. Konfigurasi Watermark

Buka `index.php` dan sesuaikan bagian `// --- KONFIGURASI WATERMARK (TEKS) ---`:

| Konstanta | Deskripsi | Default |
| :--- | :--- | :--- |
| `ENABLE_WATERMARK` | Atur ke `true` untuk mengaktifkan Watermark. | `true` |
| `WATERMARK_TEXT` | Teks yang akan ditambahkan ke gambar. | `'© watchfullreplay.com'` |
| `WEBP_QUALITY` | Kualitas konversi WebP (0-100). | `70` |
| `WATERMARK_COLOR` | Warna RGBA. `[R, G, B, Alpha(0-127)]`. | `[255, 0, 68, 70]` |

## 💡 Penggunaan

1.  Akses `index.php` di browser Anda.
2.  Masukkan kata sandi yang telah Anda atur pada langkah konfigurasi.
3.  Setelah login, masukkan URL gambar (JPG, PNG, GIF, dll.) yang ingin Anda konversi ke dalam kolom input.
4.  Klik **Konversi**.
5.  Setelah proses selesai, Anda akan menerima pratinjau gambar WebP, URL langsung, dan perbandingan ukuran file.

## ⚠️ Keamanan dan Catatan Penting

* **Kebergantungan API:** Skrip ini bergantung pada layanan `images.weserv.nl`. Jika layanan tersebut tidak tersedia atau memblokir server Anda, konversi akan gagal.
* **Izin Direktori:** Pastikan direktori `img/` dan file `converter_stats.json` (akan dibuat secara otomatis) dapat ditulisi oleh PHP.
* **SSR Protection:** Fitur keamanan SSRF harus aktif pada fungsi `gethostbyname` dan validasi IP. Jangan hapus bagian ini.

## 🤝 Kontribusi

Proyek ini dibuat sebagai solusi mandiri yang cepat. Namun, laporan *bug* dan saran untuk peningkatan, terutama terkait keamanan, selalu diterima.

## 📜 Lisensi

Proyek ini dilisensikan di bawah Lisensi MIT. Lihat file [LICENSE](LICENSE) untuk detailnya.
