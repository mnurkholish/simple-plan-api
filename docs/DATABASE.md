# SIMPLE-PLAN Database Guidelines

Dokumen ini menjadi panduan desain dan pengelolaan database backend SIMPLE-PLAN.

Database utama yang digunakan adalah **PostgreSQL**.

Requirement dan business flow mengacu pada `docs/SRS.md`.  
Arsitektur backend mengacu pada `docs/BACKEND-ARCHITECTURE.md`.

Dokumen ini tidak mendefinisikan schema final. Struktur database dapat berkembang mengikuti kebutuhan Sprint dan requirement yang telah disepakati.

---

## 1. Database Platform

Gunakan PostgreSQL sebagai DBMS utama.

Konfigurasi development:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=simple_plan
DB_USERNAME=
DB_PASSWORD=
```

Gunakan Laravel Eloquent dan Query Builder selama memungkinkan.

Hindari implementasi yang hanya kompatibel dengan DBMS lain jika tidak diperlukan.

---

## 2. Source of Truth

Gunakan urutan berikut saat menentukan struktur data:

```text
SRS / Requirement
      ↓
Kebutuhan fitur Sprint
      ↓
Analisis entity dan relationship
      ↓
Laravel Migration
      ↓
Eloquent Model
      ↓
PostgreSQL
```

`database/migrations/` merupakan representasi utama schema yang sudah diimplementasikan.

Jangan menganggap rancangan tabel yang belum dimigrasikan sebagai schema final.

---

## 3. Schema Development

Database dikembangkan bertahap sesuai fitur yang sedang dikerjakan.

Sebelum membuat atau mengubah schema:

1. baca requirement terkait;
2. periksa migration yang sudah ada;
3. periksa model dan relationship terkait;
4. tentukan data yang benar-benar dibutuhkan;
5. buat perubahan melalui Laravel Migration;
6. sesuaikan test yang relevan.

Jangan merancang seluruh database sekaligus jika requirement modul belum final.

---

## 4. Naming and Relationships

Ikuti convention Laravel dan PostgreSQL secara konsisten.

Gunakan:
- `snake_case` untuk tabel dan kolom;
- nama tabel plural;
- foreign key yang jelas seperti `user_id`, `asset_id`, `ticket_id`.

Gunakan relationship sesuai kebutuhan:
- one-to-one;
- one-to-many;
- many-to-many.

Gunakan foreign key untuk menjaga referential integrity jika sesuai.

Hindari menyimpan data yang sebenarnya dapat diperoleh melalui relationship tanpa alasan yang jelas.

---

## 5. Keys and Constraints

Gunakan primary key sesuai convention project.

Business identifier seperti `nomor_tiket` atau `kode_aset` dipisahkan dari primary key jika memang dibutuhkan.

Gunakan constraint sesuai business rule yang sudah jelas:
- `NOT NULL`;
- `UNIQUE`;
- foreign key;
- check constraint;
- default value.

Jangan membuat constraint berdasarkan asumsi requirement yang belum disepakati.

---

## 6. Status and Controlled Values

Status, role, priority, type, dan nilai workflow lain harus mengikuti requirement yang telah disepakati.

Jangan mengunci nilai yang masih ambigu di SRS.

Gunakan pendekatan yang konsisten, misalnya:
- PHP Enum;
- string/varchar dengan validation;
- PostgreSQL constraint jika benar-benar diperlukan.

Hindari PostgreSQL native ENUM jika nilainya masih mungkin sering berubah.

State transition tetap divalidasi di Service layer.

---

## 7. Timestamps

Gunakan tipe waktu PostgreSQL yang sesuai melalui Laravel Migration.

Gunakan `created_at` dan `updated_at` jika relevan.

Timezone handling harus konsisten untuk data seperti:
- waktu tiket;
- penugasan;
- maintenance;
- penyelesaian;
- approval.

Jangan menyimpan tanggal/waktu sebagai string.

---

## 8. File Metadata

File fisik tidak disimpan di PostgreSQL.

Seluruh file dikelola melalui Laravel Filesystem.

Pada development, file dapat disimpan pada local disk.  
Pada production, storage dapat dipindahkan ke MinIO tanpa mengubah schema database.

Database hanya menyimpan metadata yang memang diperlukan, misalnya:

```text
object_key
original_name
mime_type
size
uploaded_by
created_at
```

Gunakan `object_key` sebagai referensi utama file.

Contoh:

```text
helpdesk/evidence/{uuid}.jpg
maintenance/{uuid}.pdf
design/drafts/{uuid}.png
```

Jangan menyimpan:
- binary file;
- absolute local path;
- temporary URL;
- endpoint MinIO;
- access key atau secret key.

Dengan pendekatan ini, data file tetap valid ketika storage berpindah dari local disk ke MinIO.

---

## 9. Audit and History

Workflow yang membutuhkan histori dapat menggunakan struktur data terpisah jika diperlukan.

Contoh:
- perubahan status tiket;
- assignment petugas;
- riwayat penanganan;
- perubahan kondisi aset;
- hasil maintenance;
- revisi dan approval desain.

History harus menyimpan data secukupnya untuk traceability.

Hindari audit table generik yang kompleks sebelum ada kebutuhan nyata.

---

## 10. Indexing and Performance

Tambahkan index berdasarkan pola query yang nyata.

Prioritaskan kolom yang sering digunakan untuk:
- foreign key;
- unique lookup;
- filter;
- sorting;
- join;
- query tanggal/status.

Hindari menambahkan index ke semua kolom tanpa kebutuhan.

Gunakan Eloquent dan Query Builder secara efisien:
- hindari N+1 query;
- gunakan eager loading;
- gunakan pagination;
- hindari query berulang dalam loop.

Raw SQL hanya digunakan jika ada alasan teknis yang jelas.

---

## 11. Transactions and Storage Consistency

Gunakan transaction untuk operasi database multi-write yang harus atomic.

Contoh:

```text
Assign Ticket
  ↓
Update ticket
  +
Create assignment
  +
Create history
```

Gunakan:

```php
DB::transaction(function () {
    // related database writes
});
```

Perlu diingat bahwa database transaction tidak mencakup file storage.

Jika proses melibatkan database dan file:
- tangani kegagalan secara eksplisit;
- lakukan cleanup file bila diperlukan;
- jangan menandai proses berhasil jika penyimpanan file gagal.

Tidak perlu membuat distributed transaction yang kompleks.

---

## 12. Delete Strategy

Gunakan `cascade`, `restrict`, `set null`, atau soft delete sesuai karakter data dan requirement.

Jangan menggunakan cascade delete pada data penting tanpa mempertimbangkan history dan audit.

Soft delete tidak digunakan otomatis untuk semua tabel.

Penghapusan record yang memiliki file harus mempertimbangkan apakah file:
- ikut dihapus;
- tetap disimpan sebagai history;
- dipertahankan untuk kebutuhan audit.

Keputusan mengikuti business rule fitur terkait.

---

## 13. Migration Rules

Semua perubahan schema dilakukan melalui Laravel Migration.

Migration harus:
- fokus pada perubahan yang jelas;
- memiliki `up()` dan `down()` yang sesuai;
- tidak bergantung pada perubahan database manual;
- dapat dijalankan pada environment lain.

Jika migration lama sudah digunakan bersama atau sudah masuk environment lain, buat migration baru untuk perubahan lanjutan.

---

## 14. Seeder and Factory

Gunakan Factory untuk testing jika diperlukan.

Gunakan Seeder untuk initial/reference data yang memang dibutuhkan.

Contoh:
- role;
- data referensi stabil;
- data development/demo.

Jangan memasukkan credential production atau data sensitif ke Seeder.

---

## 15. Security

Jangan menyimpan secret atau credential di migration, seeder, atau source code.

Password harus menggunakan hashing Laravel.

Credential PostgreSQL maupun object storage disimpan melalui environment configuration.

Hak akses data dan file tetap ditegakkan pada application layer melalui authentication dan authorization.

---

## 16. Flexibility

Schema database dapat berkembang selama development.

Perubahan diperbolehkan ketika:
- requirement diperjelas;
- fitur Sprint baru membutuhkan data;
- relationship perlu diperbaiki;
- ditemukan masalah integritas atau performa.

Setiap perubahan harus:
- melalui migration;
- sesuai architecture project;
- didukung test yang relevan;
- tidak mengarang business requirement baru.

---

## 17. Summary

```text
Structured Data
      ↓
PostgreSQL

File Metadata
      ↓
PostgreSQL

Actual Files
      ↓
Laravel Filesystem
      ↓
Local (development)
MinIO (production)
```

Alur pengembangan database:

```text
Requirement
    ↓
Design What Is Needed
    ↓
Laravel Migration
    ↓
Eloquent Model
    ↓
PostgreSQL
```

Gunakan PostgreSQL untuk data terstruktur dan metadata file. Gunakan Laravel Filesystem agar lokasi file dapat berpindah dari local storage ke MinIO tanpa perubahan besar pada schema maupun business logic.
