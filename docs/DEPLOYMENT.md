# SIMPLE-PLAN Deployment Guidelines

Dokumen ini menjadi panduan deployment backend SIMPLE-PLAN ke environment server RS.

Requirement dan batasan infrastruktur mengacu pada `docs/SRS.md`.  
Arsitektur backend mengacu pada `docs/BACKEND-ARCHITECTURE.md`.  
Konfigurasi database mengacu pada `docs/DATABASE.md`.

Deployment harus tetap sederhana, aman, dapat diulang, dan sesuai dengan infrastruktur internal RS.

---

## 1. Deployment Target

SIMPLE-PLAN ditujukan untuk berjalan pada infrastruktur internal RS dan diakses melalui jaringan LAN/Wi-Fi internal.

Komponen production utama:

```text
Client / Browser
      ↓
HTTPS
      ↓
Web Server
      ↓
Laravel Application
   ├── PostgreSQL
   └── MinIO
```

MinIO digunakan sebagai object storage production jika sudah tersedia dan disetujui.

---

## 2. Environment Separation

Minimal gunakan environment terpisah:

```text
Development
Production
```

Jika diperlukan, dapat ditambahkan:

```text
Staging
```

Jangan menggunakan credential production pada environment development.

Setiap environment memiliki `.env` sendiri dan tidak disimpan di repository.

---

## 3. Production Requirements

Server production harus menyediakan sesuai kebutuhan project:

- supported PHP version;
- Composer;
- required PHP extensions;
- web server seperti Nginx atau Apache;
- PostgreSQL;
- MinIO jika digunakan;
- HTTPS/SSL;
- scheduler/cron jika diperlukan;
- queue worker jika project menggunakan queue.

Versi final mengikuti dependency pada `composer.json` dan environment server yang disepakati.

---

## 4. Application Configuration

Gunakan `.env` untuk konfigurasi environment-specific.

Contoh utama:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://simple-plan.internal

DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=simple_plan
DB_USERNAME=
DB_PASSWORD=

FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=simple-plan
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Nilai sebenarnya disesuaikan dengan server RS.

Jangan menyimpan secret, password, access key, atau credential production di repository.

---

## 5. Storage Strategy

### Development

Development dapat menggunakan:

```env
FILESYSTEM_DISK=local
```

### Production

Production dapat menggunakan MinIO melalui S3-compatible Laravel Filesystem:

```env
FILESYSTEM_DISK=s3
```

Business logic tidak boleh bergantung langsung pada local path agar perpindahan storage tidak membutuhkan refactor besar.

Jika terdapat file lama di local storage, lakukan migrasi file ke MinIO sebelum atau saat cutover production.

---

## 6. Standard Deployment Flow

Gunakan alur deployment berikut sebagai baseline:

```text
Backup
  ↓
Pull / Release Code
  ↓
Install Dependencies
  ↓
Configure Environment
  ↓
Run Migration
  ↓
Optimize Laravel
  ↓
Restart Required Services
  ↓
Health Check
  ↓
Smoke Test
```

Contoh command Laravel:

```bash
composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jalankan hanya command yang sesuai dengan konfigurasi project.

---

## 7. File Permissions

Pastikan web server memiliki permission yang sesuai untuk directory Laravel yang membutuhkan write access, terutama:

```text
storage/
bootstrap/cache/
```

Hindari permission terlalu terbuka seperti `777` jika tidak diperlukan.

Gunakan ownership dan permission sesuai user/service pada server.

---

## 8. Database Migration

Semua perubahan schema harus melalui Laravel Migration.

Pada production:

```bash
php artisan migrate --force
```

Sebelum migration:
- pastikan backup tersedia jika perubahan berisiko;
- review migration yang akan dijalankan;
- pastikan perubahan kompatibel dengan data existing.

Jangan melakukan perubahan schema manual tanpa alasan dan dokumentasi yang jelas.

---

## 9. MinIO Setup

Jika MinIO digunakan pada production:

- buat bucket yang dibutuhkan;
- gunakan credential khusus aplikasi;
- gunakan bucket/private object access;
- simpan credential hanya di environment server;
- pastikan Laravel dapat mengakses endpoint MinIO;
- uji upload, download, dan delete.

Database tetap menyimpan `object_key`/metadata, bukan endpoint atau temporary URL.

MinIO tidak wajib aktif pada development jika project masih menggunakan local storage.

---

## 10. Scheduler and Queue

Jika project menggunakan Laravel Scheduler, konfigurasi cron sesuai kebutuhan server.

Contoh:

```cron
* * * * * cd /path/to/simple-plan && php artisan schedule:run >> /dev/null 2>&1
```

Jika project menggunakan Queue, jalankan worker menggunakan process manager yang sesuai dengan server.

Queue dan scheduler hanya dikonfigurasi jika memang digunakan oleh fitur production.

---

## 11. HTTPS and Network

Production harus menggunakan HTTPS.

Akses sistem dibatasi sesuai kebijakan jaringan internal RS.

Pastikan:
- certificate valid;
- port yang diperlukan dibuka hanya sesuai kebutuhan;
- PostgreSQL dan MinIO tidak diekspos ke jaringan publik;
- akses administratif dibatasi.

---

## 12. Logging

Gunakan Laravel logging untuk error dan kejadian teknis yang relevan.

Production harus menggunakan:

```env
APP_DEBUG=false
```

Jangan mengekspos stack trace atau informasi sensitif ke pengguna.

Pastikan log dapat dipantau dan tidak menyimpan secret atau credential.

---

## 13. Backup

Sebelum deployment berisiko, lakukan backup data yang relevan.

Backup production dapat mencakup:

- PostgreSQL;
- MinIO/object storage;
- konfigurasi penting;
- application release metadata jika dibutuhkan.

Backup harus mengikuti kebijakan penyimpanan dan infrastruktur RS.

Restore procedure harus pernah diverifikasi, bukan hanya backup creation.

---

## 14. Rollback

Jika deployment gagal:

1. hentikan perubahan lanjutan;
2. identifikasi masalah;
3. rollback application release jika diperlukan;
4. rollback migration hanya jika aman;
5. restore backup jika diperlukan;
6. lakukan verification ulang.

Jangan menjalankan rollback database secara otomatis jika migration bersifat destructive atau berisiko kehilangan data.

---

## 15. Post-Deployment Verification

Setelah deployment, lakukan smoke test minimal pada area utama yang terdampak.

Contoh:

- login;
- authentication/authorization;
- endpoint utama;
- koneksi PostgreSQL;
- upload/download file jika MinIO digunakan;
- scheduler/queue jika digunakan;
- error log;
- health endpoint jika tersedia.

Pastikan tidak ada error kritis sebelum deployment dianggap selesai.

---

## 16. CI/CD

CI/CD dapat diterapkan secara bertahap.

Minimal CI sebaiknya menjalankan:

```text
Install Dependencies
      ↓
Code Style / Static Check jika digunakan
      ↓
Automated Tests
      ↓
Pass / Fail
```

Deployment otomatis hanya diterapkan jika environment server dan workflow tim sudah siap.

Jangan memaksakan pipeline kompleks pada tahap awal project.

---

## 17. Security

Pada production:

- gunakan `APP_DEBUG=false`;
- jangan commit `.env`;
- gunakan credential terpisah per service;
- batasi akses PostgreSQL dan MinIO;
- gunakan HTTPS;
- rotasi credential jika diperlukan;
- jangan gunakan credential development;
- jangan mengekspos management console tanpa kontrol akses.

---

## 18. Deployment Principles

- **Repeatable** — deployment dapat dijalankan ulang dengan langkah yang jelas.
- **Environment-based** — konfigurasi berbeda disimpan di environment, bukan source code.
- **Safe migration** — perubahan database dilakukan melalui migration.
- **Private storage** — file production tidak public secara default.
- **Minimal change** — deployment tidak mengubah business logic.
- **Rollback aware** — perubahan berisiko memiliki strategi pemulihan.
- **Flexible** — detail server dapat berubah tanpa mengubah architecture utama.

---

## 19. Summary

```text
Development
Laravel
├── PostgreSQL
└── Local Storage

Production
Laravel
├── PostgreSQL
└── MinIO
```

Perbedaan environment terutama berada pada konfigurasi dan infrastruktur.

Selama aplikasi menggunakan Laravel Filesystem, Laravel Migration, dan environment configuration secara konsisten, perpindahan dari development ke production tidak membutuhkan perubahan besar pada business logic.
