# SIMPLE-PLAN API

Sistem manajemen Inventarisasi, Maintenance, dan Helpdesk RS Citra Husada. Aplikasi ini menggunakan arsitektur REST API berbasis JSON.

Sistem ini mengimplementasikan Role-Based Access Control (RBAC) dengan 6 role utama:
1. Super Admin
2. Koordinator Sarpras
3. Petugas TIK
4. Petugas Sarpras
5. User/Unit
6. Manajemen

## Tech Stack

Berdasarkan konfigurasi saat ini, berikut adalah teknologi utama yang digunakan dalam project ini:
- **Bahasa Pemrograman:** PHP `^8.4`
- **Framework Utama:** Laravel Framework `^13.17`
- **Database:** SQLite (default) / MySQL / MariaDB
- **Autentikasi & Otorisasi:** Laravel Sanctum (`^4.0`) & Spatie Permission (`^8.3`)
- **Query Builder:** Spatie Query Builder (`^7.3`)
- **Testing:** PestPHP (`^5.1`)
- **Dokumentasi API:** L5-Swagger (`^11.1`)

## Persyaratan Server (Minimum)

- PHP >= 8.4
- Ekstensi PHP: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML (Sesuai standar Laravel)
- Composer (v2.x)
- Node.js & NPM (untuk frontend assets jika diperlukan)
- Database: SQLite (sudah terintegrasi via PDO) atau MySQL / MariaDB
- Web Server: Nginx / Apache

## Panduan Setup Lokal

Ikuti langkah-langkah berikut untuk menjalankan project ini di lingkungan lokal:

1. **Clone repository**
   ```bash
   git clone <repository-url>
   cd simple-plan-api
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment Setup**
   Copy file `.env.example` menjadi `.env` (jika belum terbuat secara otomatis oleh script instalasi):
   ```bash
   cp .env.example .env
   ```
   *Secara default, konfigurasi `.env.example` menggunakan SQLite (`DB_CONNECTION=sqlite`). Jika ingin menggunakan MySQL, sesuaikan nilai `DB_*` di dalam file `.env`.*

4. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Run Migrations & Seeders**
   ```bash
   php artisan migrate --seed
   ```
   *(Catatan: pastikan file database SQLite telah terbuat di `database/database.sqlite` jika Anda menggunakan SQLite, jalankan perintah `touch database/database.sqlite` jika belum ada)*

6. **Create Storage Symlink**
   ```bash
   php artisan storage:link
   ```

7. **Jalankan Local Server**
   ```bash
   php artisan serve
   ```
   Aplikasi (API) dapat diakses di `http://localhost:8000`.

## Automated Test

Project ini menggunakan **PestPHP** untuk pengujian otomatis. Untuk menjalankan seluruh test suite, jalankan perintah:

```bash
php artisan test
```

Atau menggunakan Pest secara langsung:

```bash
vendor/bin/pest
```

## Tautan Dokumentasi

Silakan merujuk pada file-file dokumentasi berikut untuk informasi lebih rinci terkait pengembangan, standar, dan spesifikasi sistem:

- [AGENTS.md](AGENTS.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [docs/SRS.md](docs/SRS.md)
- [docs/BACKEND-ARCHITECTURE.md](docs/BACKEND-ARCHITECTURE.md)
- [docs/DATABASE.md](docs/DATABASE.md)
- [docs/API-GUIDELINES.md](docs/API-GUIDELINES.md)
- [docs/TESTING.md](docs/TESTING.md)
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- [openapi.yaml](openapi.yaml)
