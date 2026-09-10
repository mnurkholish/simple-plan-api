# Helpdesk Implementation Guide

## 1. Status Dokumen

Dokumen ini menjadi panduan sementara untuk implementasi backend modul **Helpdesk** pada project SIMPLE PLAN.

Kondisi saat ini:

- SRS belum tersedia.
- Rancangan/use case dari System Analyst (SA) menjadi acuan fungsional sementara.
- Dokumentasi teknis project yang sudah ada tetap menjadi acuan implementasi.
- Jika terdapat perbedaan antara asumsi implementasi dan rancangan SA, jangan membuat keputusan bisnis baru secara sepihak.
- Requirement yang belum jelas harus dicatat untuk dikonfirmasi, bukan dilengkapi berdasarkan asumsi.

Dokumen ini dapat diperbarui atau digantikan ketika SRS resmi sudah tersedia.

---

## 2. Source of Truth

Gunakan prioritas berikut saat melakukan implementasi:

1. Rancangan/use case terbaru dari System Analyst untuk kebutuhan fungsional.
2. Dokumentasi teknis project untuk aturan implementasi, termasuk:
   - `AGENTS.md`
   - backend architecture
   - database guideline
   - API guideline
   - testing guideline
   - deployment guideline jika relevan
   - `openapi.yaml` jika sudah digunakan sebagai API contract
3. Existing codebase dan konvensi Laravel yang sudah digunakan project.

SRS **belum** menjadi dependency karena saat ini belum tersedia.

---

## 3. Prinsip Implementasi

### 3.1 Jangan membuat requirement baru

Implementasikan hanya behavior yang memiliki dasar pada rancangan yang tersedia.

Jika requirement:

- belum dijelaskan;
- saling bertentangan;
- membutuhkan business rule tambahan;
- atau membutuhkan keputusan dari SA/mitra;

maka jangan membuat behavior permanen berdasarkan asumsi.

Catat sebagai requirement yang perlu dikonfirmasi.

### 3.2 Hindari duplikasi TIK dan Sarpras

Helpdesk TIK dan Sarpras memiliki workflow yang sebagian besar sama.

Gunakan satu resource/domain tiket dengan pembeda kategori apabila kebutuhan dapat diakomodasi tanpa memaksakan desain.

Contoh kategori:

```text
TIK
SARPRAS
```

Jangan membuat implementasi terpisah seperti `TikTicket` dan `SarprasTicket` hanya karena use case ditulis terpisah apabila behavior keduanya sama.

### 3.3 Gunakan struktur minimum yang diperlukan

Jangan menambahkan:

- abstraction yang belum diperlukan;
- repository layer tanpa kebutuhan nyata;
- service atau pattern tambahan hanya untuk formalitas;
- tabel atau model untuk kebutuhan yang belum didefinisikan;
- refactor di luar scope fitur yang sedang dikerjakan.

Ikuti pola existing codebase terlebih dahulu.

### 3.4 PostgreSQL sebagai database

Gunakan PostgreSQL dan ikuti database guideline project.

Gunakan tipe data, constraint, index, foreign key, naming, dan migration yang konsisten dengan project.

### 3.5 API mengikuti contract dan guideline project

Endpoint harus:

- mengikuti REST/API convention project;
- menggunakan format response yang konsisten;
- menggunakan validation dan error handling yang sudah digunakan;
- menggunakan pagination jika daftar data membutuhkannya;
- menghindari N+1 query;
- mendokumentasikan endpoint pada `openapi.yaml` jika file tersebut memang digunakan sebagai API contract.

Jangan menambahkan endpoint ke API contract sebelum endpoint tersebut benar-benar diimplementasikan.

---

## 4. Domain Helpdesk Saat Ini

### 4.1 Kategori Helpdesk

Rancangan saat ini mengenal:

```text
TIK
SARPRAS
```

### 4.2 Status Tiket

Status yang muncul pada rancangan:

```text
Baru
Terverifikasi
Diproses
Selesai
Ditolak
```

Gunakan enum atau mekanisme yang sesuai dengan konvensi project apabila status sudah cukup stabil untuk direpresentasikan sebagai enum.

Jangan membuat aturan transisi status yang belum memiliki dasar requirement.

### 4.3 Prioritas

Rancangan saat ini menggunakan:

```text
Critical
High
Medium
Low
```

Prioritas baru relevan ketika proses assignment/prioritas diimplementasikan.

### 4.4 Informasi Tiket

Struktur tiket harus dibentuk berdasarkan data yang benar-benar diperlukan oleh use case.

Data yang muncul pada rancangan antara lain:

- nomor tiket;
- kategori Helpdesk;
- nama pelapor;
- unit;
- kategori kerusakan untuk kebutuhan yang relevan;
- deskripsi;
- foto bukti awal;
- status;
- prioritas;
- petugas;
- data inventaris jika tiket terkait inventaris;
- tanggal masuk/dibuat;
- tanggal selesai;
- informasi SLA jika requirement-nya sudah jelas;
- riwayat penanganan jika fitur tersebut sudah diimplementasikan.

Tidak semua field harus dibuat sekaligus. Tambahkan sesuai kebutuhan fitur yang sedang dikerjakan.

---

## 5. API Daftar Tiket

Daftar tiket TIK dan Sarpras sebaiknya menggunakan resource yang sama apabila desain domain memungkinkan.

API daftar harus dapat mendukung kebutuhan rancangan berupa:

- menampilkan daftar tiket;
- membedakan kategori TIK dan Sarpras;
- filter status;
- filter tanggal;
- pagination sesuai standar project.

Filter kategori dapat digunakan untuk membedakan tampilan TIK dan Sarpras tanpa membuat dua implementasi backend yang sama.

Kondisi tidak ada data harus menghasilkan response API yang valid, bukan server error.

Pastikan query tetap sederhana dan efisien.

---

## 6. API Detail Tiket

API detail mengambil satu tiket berdasarkan identifier yang digunakan project.

Response hanya memuat data yang:

- tersedia pada model/relasi;
- dibutuhkan oleh rancangan;
- dan sudah memiliki dasar requirement.

Gunakan API Resource atau pola response existing project.

Resource yang tidak ditemukan harus menggunakan mekanisme error handling Laravel dan standar API project.

Jangan membuat data placeholder atau nilai turunan yang business rule-nya belum didefinisikan.

---

## 7. Authentication dan Authorization

Gunakan sistem authentication dan authorization yang sudah tersedia pada project.

Rancangan saat ini banyak menggunakan aktor **Super Admin**, tetapi:

- jangan membuat role/permission system baru hanya untuk memenuhi label aktor;
- jangan mengubah sistem authorization existing tanpa kebutuhan yang jelas;
- jika pembatasan khusus Super Admin belum tersedia, catat sebagai dependency;
- authorization yang belum dapat dipastikan sebaiknya tidak diselesaikan menggunakan asumsi sementara.

Ketika requirement role sudah jelas, tambahkan authorization mengikuti mekanisme project.

---

## 8. Pembuatan Tiket

Rancangan memiliki dua jalur:

1. pembuatan tiket manual;
2. pembuatan tiket melalui barcode inventaris.

Keduanya sebaiknya menggunakan business process pembuatan tiket yang sama.

Barcode hanya menjadi cara untuk memperoleh atau memilih data inventaris, bukan jenis tiket yang berbeda.

Hindari duplikasi business logic antara:

```text
Manual Ticket Creation
Barcode Ticket Creation
```

Jika keduanya menghasilkan tiket yang sama, gunakan satu proses/domain creation.

---

## 9. Inventaris dan Barcode

Pada alur barcode, sistem menampilkan informasi inventaris sebelum membuat tiket.

Implementasi harus memisahkan:

```text
Lookup inventaris melalui barcode
```

dengan:

```text
Pembuatan tiket Helpdesk
```

Barcode tidak perlu disimpan sebagai bagian dari tiket apabila tiket cukup mereferensikan inventaris.

Gunakan relasi ke data inventaris jika model inventaris sudah tersedia dan sesuai dengan architecture project.

Jangan membuat struktur inventaris baru sebelum memeriksa existing codebase.

---

## 10. Workflow Tiket

Rancangan saat ini memiliki proses:

```text
Baru
  -> Verifikasi
  -> Terverifikasi

Baru
  -> Tolak
  -> Ditolak
```

Setelah tiket terverifikasi, rancangan juga menyediakan:

- pemilihan prioritas;
- assignment petugas;
- proses penanganan;
- perubahan status menjadi Diproses/Selesai.

Namun jangan menetapkan transisi tambahan apabila belum dijelaskan secara eksplisit.

Contoh pertanyaan yang masih membutuhkan konfirmasi:

```text
Kapan status Terverifikasi berubah menjadi Diproses?
```

Jangan menetapkan bahwa assignment otomatis mengubah status menjadi Diproses kecuali sudah dikonfirmasi.

---

## 11. Riwayat Penanganan

Rancangan memiliki kebutuhan pencatatan:

- catatan penanganan;
- waktu mulai perbaikan;
- waktu selesai perbaikan;
- foto hasil perbaikan;
- status Diproses/Selesai.

Saat fitur ini diimplementasikan, evaluasi penggunaan entitas/relasi riwayat terpisah jika satu tiket dapat memiliki lebih dari satu catatan penanganan.

Hindari menyimpan riwayat kompleks sebagai satu field text apabila requirement membutuhkan histori yang dapat bertambah.

Tetap sesuaikan desain akhir dengan requirement dan database guideline project.

---

## 12. File Storage

Jika foto bukti awal atau foto hasil perbaikan diimplementasikan, gunakan abstraction filesystem Laravel sesuai storage guideline project.

Jika project menggunakan MinIO/S3-compatible object storage:

- simpan file pada object storage;
- simpan metadata/path/reference yang diperlukan pada database;
- jangan menyimpan binary file langsung pada PostgreSQL kecuali ada requirement khusus.

Jangan mengikat business logic langsung pada provider storage tertentu jika Laravel filesystem sudah menyediakan abstraction yang digunakan project.

---

## 13. SLA

Rancangan menyebutkan:

```text
Tepat Waktu
Mendekati Batas
Melewati Batas
```

Namun aturan SLA belum cukup untuk diimplementasikan secara penuh jika belum terdapat definisi seperti:

- durasi SLA per prioritas;
- waktu mulai perhitungan SLA;
- aturan "Mendekati Batas";
- kondisi pause/reset SLA jika ada.

Jangan hard-code nilai SLA sebelum aturan tersebut tersedia.

Struktur yang berkaitan dengan SLA hanya boleh ditambahkan jika memang diperlukan oleh fitur saat ini dan tidak memaksakan business rule yang belum ditentukan.

---

## 14. Testing

Testing dilakukan bersamaan dengan implementasi fitur, bukan hanya di akhir development.

Prioritaskan feature test untuk behavior API.

Untuk setiap fitur yang dibuat:

1. implementasikan behavior;
2. buat/update test;
3. jalankan test terkait;
4. perbaiki jika gagal;
5. lakukan regression test yang relevan.

Test harus fokus pada behavior, bukan terlalu terikat pada detail internal implementasi.

Untuk API daftar/detail, minimal periksa:

- akses authenticated user sesuai aturan project;
- akses guest jika endpoint protected;
- daftar tiket dapat ditampilkan;
- filter kategori;
- filter status;
- filter tanggal;
- kombinasi filter yang relevan;
- response ketika data kosong;
- detail tiket;
- resource tidak ditemukan;
- format response sesuai API guideline.

---

## 15. Handling Requirement yang Ambigu

Jika menemukan requirement yang ambigu:

### Jangan

- menebak behavior;
- menambah business rule;
- memperbaiki rancangan SA secara sepihak;
- membuat workaround permanen;
- mengimplementasikan fitur tambahan untuk menutup gap.

### Lakukan

- implementasikan bagian yang sudah jelas;
- jaga struktur agar mudah dikembangkan;
- catat gap requirement;
- laporkan bagian yang membutuhkan konfirmasi.

Contoh gap yang sudah terlihat:

- transisi `Terverifikasi -> Diproses` belum dijelaskan secara jelas;
- aktor yang melakukan update penanganan perlu dipastikan;
- aturan SLA belum lengkap;
- beberapa detail status pada use case belum konsisten;
- detail role/authorization belum sepenuhnya didefinisikan.

---

## 16. Aturan untuk AI Coding Agent

Sebelum coding, agent harus:

1. membaca `AGENTS.md`;
2. membaca dokumentasi teknis yang relevan;
3. memeriksa existing codebase;
4. memahami scope perubahan;
5. menggunakan rancangan SA sebagai functional reference sementara;
6. mengidentifikasi requirement yang ambigu sebelum membuat keputusan desain besar.

Saat coding, agent harus:

- mengikuti existing convention;
- membuat perubahan minimum;
- menjaga scope;
- menghindari over-engineering;
- tidak membuat requirement baru;
- tidak melakukan refactor tidak terkait;
- menambahkan test untuk behavior yang dibuat;
- memperbarui API contract jika memang digunakan dan relevan.

Setelah coding, agent harus:

1. menjalankan formatter/linter;
2. menjalankan test terkait;
3. menjalankan regression test yang relevan;
4. memeriksa perubahan di luar scope;
5. merangkum file yang dibuat/diubah;
6. melaporkan hasil test;
7. mencatat requirement yang masih membutuhkan konfirmasi.

Agent **tidak melakukan git commit** kecuali secara eksplisit diminta.

---

## 17. Definition of Done

Sebuah perubahan dianggap selesai jika:

- sesuai rancangan/use case yang tersedia;
- mengikuti guideline teknis project;
- tidak menambahkan requirement berdasarkan asumsi;
- migration/model/API konsisten;
- validation dan error handling sesuai standar;
- test untuk behavior utama tersedia dan berhasil;
- tidak terdapat perubahan di luar scope;
- API contract diperbarui jika digunakan;
- requirement ambigu yang ditemukan sudah dicatat.

---

## 18. Catatan Penggunaan

Dokumen ini adalah **implementation guide sementara**, bukan pengganti SRS.

Ketika SRS resmi tersedia:

1. bandingkan SRS dengan implementasi yang sudah berjalan;
2. identifikasi perubahan requirement;
3. perbarui panduan ini jika masih diperlukan;
4. gunakan SRS sebagai acuan fungsional utama setelah dinyatakan berlaku.
