# SIMPLE-PLAN Database Guidelines

Panduan ini hanya membahas aturan database SIMPLE-PLAN: MySQL, migration,
schema evolution, naming, relationship, constraint, transaction, index/query,
file metadata, dan data integrity.

Requirement dan business flow mengacu pada `docs/SRS.md`.
Arsitektur backend mengacu pada `docs/BACKEND-ARCHITECTURE.md`.

Dokumen ini tidak mendefinisikan schema final. Schema berkembang mengikuti
requirement yang sudah disepakati.

---

## 1. Database Platform

Gunakan MySQL sebagai DBMS utama.

Konfigurasi development yang disarankan:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=simple_plan
DB_USERNAME=
DB_PASSWORD=
```

Gunakan Eloquent dan Query Builder selama cukup jelas. Hindari implementasi
yang mengunci project ke DBMS lain tanpa kebutuhan.

---

## 2. Schema Source

Urutan saat menentukan struktur data:

```text
SRS/requirement -> kebutuhan fitur -> entity/relationship -> migration -> model
```

`database/migrations/` adalah representasi utama schema yang sudah
diimplementasikan. Jangan menganggap rancangan tabel yang belum dimigrasikan
sebagai schema final.

---

## 3. Schema Evolution

Sebelum membuat atau mengubah schema:

1. baca requirement terkait;
2. periksa migration existing;
3. periksa model dan relationship terkait;
4. tentukan data yang benar-benar dibutuhkan;
5. buat perubahan melalui Laravel Migration;
6. sesuaikan test yang relevan.

Jangan merancang seluruh database sekaligus jika requirement modul belum final.
Jika migration lama sudah digunakan bersama atau sudah masuk environment lain,
buat migration baru untuk perubahan lanjutan.

Migration harus:

- fokus pada perubahan yang jelas;
- memiliki `up()` dan `down()` yang sesuai;
- tidak bergantung pada perubahan database manual;
- dapat dijalankan pada environment lain.

---

## 4. Naming and Relationships

Ikuti convention Laravel dan MySQL:

- `snake_case` untuk tabel dan kolom;
- nama tabel plural;
- foreign key jelas seperti `user_id`, `asset_id`, `ticket_id`;
- relationship Eloquent sesuai kebutuhan.

Gunakan foreign key untuk menjaga referential integrity jika sesuai. Hindari
menyimpan data yang dapat diperoleh dari relationship tanpa alasan jelas.

---

## 5. Keys and Constraints

Gunakan primary key sesuai convention project.

Business identifier seperti `nomor_tiket` atau `kode_aset` dipisahkan dari
primary key jika memang dibutuhkan.

Gunakan constraint berdasarkan business rule yang sudah jelas:

- `NOT NULL`;
- `UNIQUE`;
- foreign key;
- check constraint;
- default value.

Jangan membuat constraint berdasarkan asumsi requirement yang belum disepakati.

---

## 6. Controlled Values

Status, role, priority, type, dan nilai workflow lain mengikuti requirement yang
sudah disepakati.

Gunakan pendekatan yang konsisten:

- PHP Enum;
- string/varchar dengan validation;
- MySQL constraint jika nilainya stabil.

Hindari MySQL native ENUM untuk nilai yang masih mungkin sering berubah.
State transition tetap divalidasi di Service layer.

---

## 7. Date and Time

Gunakan tipe waktu MySQL melalui Laravel Migration.

Gunakan `created_at` dan `updated_at` jika relevan. Jangan menyimpan tanggal
atau waktu sebagai string.

Timezone handling harus konsisten untuk data seperti waktu tiket, penugasan,
maintenance, penyelesaian, dan approval.

Untuk Helpdesk, `assigned_at` mencatat assignment terbaru, sedangkan
`sla_started_at` mencatat waktu pertama tiket masuk status `diproses` dan tidak
direset saat reassignment. `completed_at` tiket berasal dari waktu selesai yang
diinput manual pada handling yang mengubah tiket menjadi `terselesaikan`.
Tiket `terselesaikan` yang belum diverifikasi selama 2 hari sejak
`completed_at` ditutup otomatis; `closed_at` diisi waktu eksekusi scheduler dan
status history menggunakan `changed_by_id = null` untuk aksi sistem.

---

## 8. File Metadata

MySQL menyimpan metadata file, bukan file fisik.

File fisik dikelola melalui Laravel Filesystem. Storage dapat berpindah dari
local disk ke MinIO tanpa mengubah schema selama database menyimpan referensi
yang portable.

Gunakan `object_key` sebagai referensi utama file.

Metadata yang umum:

```text
object_key
original_name
mime_type
size
uploaded_by
created_at
```

Contoh `object_key`:

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
- access key atau secret key;
- permanent public URL.

---

## 9. Audit and History

Workflow yang membutuhkan histori dapat menggunakan struktur data terpisah jika
requirement memerlukan.

Contoh histori:

- perubahan status tiket;
- assignment petugas;
- riwayat penanganan;
- perubahan kondisi aset;
- hasil maintenance;
- revisi dan approval desain.

History harus cukup untuk traceability. Hindari audit table generik yang
kompleks sebelum ada kebutuhan nyata.

---

## 10. Indexing and Query Performance

Tambahkan index berdasarkan pola query nyata.

Prioritaskan kolom yang sering digunakan untuk:

- foreign key;
- unique lookup;
- filter;
- sorting;
- join;
- tanggal/status.

Hindari index di semua kolom tanpa kebutuhan. Gunakan eager loading,
pagination, dan query yang tidak berulang dalam loop. Raw SQL hanya digunakan
jika ada alasan teknis yang jelas.

---

## 11. Transactions and Storage Consistency

Gunakan transaction untuk operasi database multi-write yang harus atomic.

```php
DB::transaction(function () {
    // related database writes
});
```

Database transaction tidak mencakup file storage. Jika proses melibatkan
database dan file:

- tangani kegagalan secara eksplisit;
- lakukan cleanup file bila diperlukan;
- jangan menandai proses berhasil jika penyimpanan file gagal.

Tidak perlu membuat distributed transaction yang kompleks.

---

## 12. Delete Strategy

Gunakan `cascade`, `restrict`, `set null`, atau soft delete sesuai karakter data
dan requirement.

Jangan menggunakan cascade delete pada data penting tanpa mempertimbangkan
history dan audit. Soft delete tidak otomatis untuk semua tabel.

Penghapusan record yang memiliki file harus mengikuti business rule fitur:

- file ikut dihapus;
- file tetap disimpan sebagai history;
- file dipertahankan untuk audit.

---

## 13. Seeder and Factory

Gunakan Factory untuk testing jika diperlukan.

Gunakan Seeder untuk initial/reference data yang memang dibutuhkan, seperti
role, data referensi stabil, atau data development/demo.

Jangan memasukkan credential production atau data sensitif ke Seeder.

---

## 14. Security and Integrity

- Jangan menyimpan secret atau credential di migration, seeder, atau source
  code.
- Password harus menggunakan hashing Laravel.
- Credential MySQL dan object storage disimpan melalui environment
  configuration.
- Hak akses data dan file ditegakkan pada application layer melalui
  authentication dan authorization.
- Setiap perubahan schema harus sesuai architecture project, didukung test yang
  relevan, dan tidak mengarang business requirement baru.
