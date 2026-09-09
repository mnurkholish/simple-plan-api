# SIMPLE-PLAN Deployment Guidelines

Panduan ini hanya membahas deployment production SIMPLE-PLAN: environment,
server requirements, `.env`, PostgreSQL, MinIO production, migration, network,
scheduler/queue jika digunakan, logging, backup, rollback, verification, dan
CI/CD sederhana.

Requirement dan batasan infrastruktur mengacu pada `docs/SRS.md`.
Arsitektur backend mengacu pada `docs/BACKEND-ARCHITECTURE.md`.
Aturan database mengacu pada `docs/DATABASE.md`.

Production berjalan pada infrastruktur internal RS, bukan public cloud. Akses
utama melalui jaringan internal.

---

## 1. Deployment Target

Komponen production utama:

```text
Client/Browser -> HTTPS -> Web Server -> Laravel Application
                                      -> PostgreSQL
                                      -> MinIO (jika digunakan)
```

MinIO digunakan sebagai object storage production jika sudah tersedia dan
disetujui.

---

## 2. Environment Separation

Minimal gunakan environment terpisah:

- development;
- production.

Staging dapat ditambahkan jika dibutuhkan.

Setiap environment memiliki `.env` sendiri dan tidak disimpan di repository.
Jangan menggunakan credential production pada development.

---

## 3. Production Requirements

Server production menyediakan sesuai kebutuhan project:

- PHP version sesuai `composer.json`;
- Composer;
- required PHP extensions;
- web server seperti Nginx atau Apache;
- PostgreSQL;
- MinIO jika digunakan;
- HTTPS/SSL;
- scheduler/cron jika fitur menggunakan Laravel Scheduler;
- queue worker jika fitur menggunakan Queue.

Versi final mengikuti dependency dan server RS yang disepakati.

---

## 4. Environment Configuration

Gunakan `.env` untuk konfigurasi environment-specific.

Contoh production:

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

Nilai sebenarnya mengikuti server RS. Jangan menyimpan secret, password, access
key, atau credential production di repository.

---

## 5. Storage Production

Development boleh menggunakan `FILESYSTEM_DISK=local`.

Production dapat menggunakan MinIO melalui S3-compatible Laravel Filesystem:

```env
FILESYSTEM_DISK=s3
```

Jika ada file lama di local storage, migrasikan file ke MinIO sebelum atau saat
cutover production.

Detail metadata file berada di `docs/DATABASE.md`; cara implementasi akses file
berada di `docs/BACKEND-ARCHITECTURE.md`.

---

## 6. Standard Deployment Flow

Baseline deployment:

```text
backup -> release code -> install dependencies -> configure environment
-> migrate -> optimize Laravel -> restart services -> health check -> smoke test
```

Contoh command:

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

Pastikan web server memiliki write access yang tepat untuk:

```text
storage/
bootstrap/cache/
```

Hindari permission terlalu terbuka seperti `777` jika tidak diperlukan.

---

## 8. Database Migration in Production

Jalankan migration production dengan:

```bash
php artisan migrate --force
```

Sebelum migration:

- pastikan backup tersedia jika perubahan berisiko;
- review migration yang akan dijalankan;
- pastikan perubahan kompatibel dengan data existing.

Jangan melakukan perubahan schema manual tanpa alasan dan dokumentasi yang
jelas.

---

## 9. MinIO Setup

Jika MinIO digunakan:

- buat bucket yang dibutuhkan;
- gunakan credential khusus aplikasi;
- gunakan bucket/private object access;
- simpan credential hanya di environment server;
- pastikan Laravel dapat mengakses endpoint MinIO;
- uji upload, download, dan delete.

PostgreSQL tetap menyimpan `object_key`/metadata, bukan endpoint atau temporary
URL.

---

## 10. Scheduler and Queue

Jika project menggunakan Laravel Scheduler, konfigurasi cron sesuai server:

```cron
* * * * * cd /path/to/simple-plan && php artisan schedule:run >> /dev/null 2>&1
```

Jika project menggunakan Queue, jalankan worker dengan process manager yang
sesuai server.

Queue dan scheduler hanya dikonfigurasi jika fitur production menggunakannya.

---

## 11. HTTPS and Network

Production harus menggunakan HTTPS.

Akses sistem dibatasi sesuai kebijakan jaringan internal RS.

Pastikan:

- certificate valid;
- port dibuka hanya sesuai kebutuhan;
- PostgreSQL dan MinIO tidak diekspos ke jaringan publik;
- akses administratif dibatasi.

---

## 12. Logging

Production harus menggunakan:

```env
APP_DEBUG=false
```

Gunakan Laravel logging untuk error dan kejadian teknis relevan. Pastikan log
dapat dipantau dan tidak menyimpan secret atau credential.

---

## 13. Backup

Sebelum deployment berisiko, backup data relevan:

- PostgreSQL;
- MinIO/object storage jika digunakan;
- konfigurasi penting;
- application release metadata jika dibutuhkan.

Backup mengikuti kebijakan RS. Restore procedure harus pernah diverifikasi,
bukan hanya backup creation.

---

## 14. Rollback

Jika deployment gagal:

1. hentikan perubahan lanjutan;
2. identifikasi masalah;
3. rollback application release jika diperlukan;
4. rollback migration hanya jika aman;
5. restore backup jika diperlukan;
6. lakukan verification ulang.

Jangan menjalankan rollback database otomatis jika migration destructive atau
berisiko kehilangan data.

---

## 15. Post-Deployment Verification

Smoke test minimal:

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

CI/CD dapat diterapkan bertahap.

Minimal CI:

```text
install dependencies -> code style/static check jika digunakan -> automated tests
```

Deployment otomatis hanya diterapkan jika environment server dan workflow tim
sudah siap. Jangan memaksakan pipeline kompleks pada tahap awal project.
