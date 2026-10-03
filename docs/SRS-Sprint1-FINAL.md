# SIMPLE PLAN — Software Requirements Specification (SRS)
## Sprint 1 — Master Data & Helpdesk

Dokumen ini menjadi **source of truth behavior Sprint 1** untuk pengembangan SIMPLE PLAN pada existing project Laravel.

Dokumen ini disusun berdasarkan:
- SRS/SR terbaru.
- Alur Helpdesk TIK/Sarpras terbaru.
- `DATABASE-UPDATED.md`.
- `SIMPLE-PLAN-Sprint1-UPDATED.dbml`.
- Keputusan finalisasi Sprint 1 yang telah disepakati.

Jika terdapat perbedaan dengan implementasi lama atau SRS lama, gunakan dokumen ini sebagai acuan behavior Sprint 1 dan sesuaikan existing implementation secara bertahap.

---

# 1. Scope Sprint 1

Sprint 1 mencakup:

```text
Master Data
Helpdesk TIK
Helpdesk Sarpras
Asset minimum untuk kebutuhan tiket
Notification terkait Helpdesk
```

Belum menjadi scope implementasi Sprint 1:

```text
Maintenance
Design Grafis
Full Inventory Management
Laporan lanjutan
Barcode/QR scanning implementation
Generic Audit Log
```

Asset tetap tersedia sebagai dependency Helpdesk, tetapi implementasi scan barcode belum menjadi target saat ini.

---

# 2. Actor

Sistem memiliki enam role:

```text
Super Admin
Koordinator Sarpras
Petugas TIK
Petugas Sarpras
User/Unit
Management
```

Business rule:

```text
1 user = 1 role aktif
1 user = 1 unit
```

Role dan permission tetap menggunakan **Spatie Laravel Permission**.

---

# 3. Master Data

## 3.1 User

Struktur field `users` existing dipertahankan untuk Sprint 1.

Field existing seperti:

```text
jabatan
no_hp
status_user
alasan_nonaktif
riwayat_status_akun
```

tidak perlu direname.

Super Admin dapat:

```text
melihat daftar user
melihat detail user
menambah user
mengubah user
mengaktifkan user
menonaktifkan user
mengelola role user
```

Satu user hanya memiliki satu role aktif.

Perubahan role menggunakan mekanisme:

```php
$user->syncRoles($role);
```

## 3.2 Unit

Master unit **tidak dikelola melalui UI pada Sprint 1**.

Unit berasal dari seed/import/database.

Super Admin menggunakan data unit tersebut ketika mengelola user.

Relasi:

```text
Unit 1 ─── N User
```

## 3.3 Role & Permission

Menu Role & Permission existing tetap dipertahankan.

Role utama SIMPLE PLAN:

```text
super-admin
koordinator-sarpras
petugas-tik
petugas-sarpras
user
management
```

Penamaan final boleh mengikuti existing project selama seluruh reference konsisten.

---

# 4. Asset Minimum

Sprint 1 hanya membutuhkan asset untuk mendukung Helpdesk.

Fungsi minimum:

```text
lookup asset
melihat detail asset
memilih asset saat membuat tiket
menghubungkan asset ke tiket
```

Full CRUD inventaris belum menjadi scope Sprint 1.

Tiket dapat dibuat:

```text
dengan asset
atau
tanpa asset
```

Pada pembuatan tiket manual, pemilihan asset bersifat opsional.

Barcode/QR belum diimplementasikan pada Sprint 1 saat ini.

---

# 5. Ticket Service

Jenis Helpdesk:

```text
tik
sarpras
```

Service ditentukan ketika tiket dibuat.

Setelah tiket dibuat:

```text
service tidak dapat diubah
```

Jika user memilih service yang salah:

```text
tiket ditolak
→ user membuat tiket baru
```

---

# 6. Pembuatan Tiket

Semua user yang sudah login dapat membuat tiket, terlepas dari role.

Data minimum:

```text
service
description
asset (opsional)
initial evidence (opsional)
```

Data reporter dan unit diambil dari user yang login.

`tickets.unit_id` menyimpan snapshot unit reporter ketika tiket dibuat.

Status awal:

```text
baru
```

Nomor tiket dihasilkan otomatis:

```text
TIK-YYYY-NNNNNN
SPR-YYYY-NNNNNN
```

Sequence reset setiap tahun dan per service.

Contoh:

```text
TIK-2026-000001
SPR-2026-000001
```

---

# 7. Hak Akses Melihat Tiket

## Super Admin

Dapat melihat:

```text
seluruh tiket TIK
seluruh tiket Sarpras
```

## Koordinator Sarpras

Dapat melihat:

```text
seluruh tiket Sarpras
```

## Petugas TIK

Dapat melihat:

```text
tiket TIK yang ditugaskan kepadanya
tiket yang dia buat sendiri
```

## Petugas Sarpras

Dapat melihat:

```text
tiket Sarpras yang ditugaskan kepadanya
tiket yang dia buat sendiri
```

## User/Unit

Dapat melihat:

```text
tiket yang dia buat sendiri
```

## Management

Dapat melihat seluruh tiket TIK dan Sarpras secara read-only.

Jika Management adalah reporter tiket tersebut, Management tetap memiliki aksi sebagai pelapor terhadap tiket miliknya, termasuk verifikasi penyelesaian.

---

# 8. Ticket Status

Status resmi:

```text
baru
diklasifikasi
ditugaskan
diproses
eskalasi
terselesaikan
terverifikasi
ditutup
ditolak
```

Flow:

```text
Baru
├── Diklasifikasi
└── Ditolak

Diklasifikasi
└── Ditugaskan

Ditugaskan
└── Diproses

Diproses
├── Eskalasi
└── Terselesaikan

Eskalasi
└── Diproses

Terselesaikan
├── Ditutup
└── Diproses
```

Rules:

```text
Ditolak hanya dari Baru
Ditolak adalah terminal state
Ditutup adalah terminal state
Tiket Ditutup tidak dapat di-reopen
```

Jika masalah muncul kembali setelah tiket Ditutup, user membuat tiket baru.

Setiap perubahan status dicatat pada `ticket_status_histories`.

---

# 9. Klasifikasi Tiket

User/pelapor tidak menentukan klasifikasi resmi ketika membuat tiket.

## 9.1 TIK

Aktor:

```text
Super Admin
```

Pre-condition:

```text
status = baru
service = tik
```

Super Admin menentukan:

```text
Kategori Mutu
Tags IT
```

Kategori Mutu hanya satu.

Tags IT hanya satu.

Jika memilih:

```text
Lain-lain
```

maka `custom_it_tag_text` wajib diisi.

Normal flow:

```text
Baru
→ Klasifikasikan
→ simpan Kategori Mutu + Tags IT
→ classified_at diisi
→ Diklasifikasi
```

Alternative:

```text
Baru
→ Tolak
→ alasan wajib diisi
→ Ditolak
```

Alasan penolakan disimpan pada `ticket_status_histories.notes`.

## 9.2 Sarpras

Aktor:

```text
Koordinator Sarpras
Super Admin
```

Pre-condition:

```text
status = baru
service = sarpras
```

Klasifikasi:

```text
Sarpras
Elektronik
Alkes
```

Jenis/detail kerusakan tidak memiliki field klasifikasi terpisah dan cukup dicatat pada deskripsi tiket.

## 9.3 Edit Klasifikasi

Setelah tiket menjadi:

```text
diklasifikasi
```

klasifikasi **tidak dapat diedit**.

Priority belum ditentukan pada tahap klasifikasi dan baru ditetapkan pada assignment pertama.

Jika terjadi kesalahan data klasifikasi, koreksi dilakukan melalui prosedur administratif/manual di luar flow normal aplikasi.

---

# 10. Priority & SLA

Priority:

```text
critical
high
medium
low
```

Target SLA:

```text
Critical → 2 jam
High     → 4 jam
Medium   → 1 hari
Low      → 3 hari
```

SLA:

```text
berjalan 24/7
mulai dari assigned_at pada assignment pertama
tidak pause ketika Eskalasi
```

Rumus:

```text
sla_deadline = assigned_at pertama + SLA(priority)
```

Tiket dianggap selesai untuk pengukuran SLA ketika status berubah menjadi:

```text
terselesaikan
```

Waktu menunggu verifikasi reporter tidak menambah waktu penyelesaian teknis SLA.

Status SLA tidak perlu disimpan sebagai state permanen dan dapat dihitung dari deadline dan waktu penyelesaian.

---

# 11. Assignment

## TIK

Assignment dilakukan oleh:

```text
Super Admin
```

## Sarpras

Assignment dilakukan oleh:

```text
Koordinator Sarpras
```

Setelah assignment:

```text
Diklasifikasi
→ Ditugaskan
```

Input assignment pertama:

```text
assigned_officer_id
priority
```

Data current assignment:

```text
assigned_officer_id
assigned_at
priority
sla_deadline
```

`assigned_at` dan `sla_deadline` dihitung backend. SLA dimulai saat assignment pertama.

Tidak ada tabel assignment history khusus.

Kandidat dan assignee wajib user aktif dengan role sesuai service:

```text
TIK → Petugas TIK
Sarpras → Petugas Sarpras
```

Pencarian kandidat dapat menggunakan nama atau jabatan.

---

# 12. Reassignment

Tiket dapat di-reassign.

Jika tiket masih:

```text
Ditugaskan
```

current assignee cukup diganti.

`assigned_at` diperbarui ke waktu assignment petugas terbaru. Priority dan
`sla_deadline` tetap memakai assignment pertama. Status tetap `Ditugaskan` dan
tidak membuat status history karena status tidak berubah.

Jika tiket sudah:

```text
Diproses
```

maka flow:

```text
Diproses
→ Reassign
→ Ditugaskan
→ petugas baru melakukan penanganan
→ Diproses
```

Reassignment mengganti current assignee pada tiket.

Pada reassignment dari `Diproses`, `assigned_at` diperbarui, priority dan
`sla_deadline` tidak berubah, dan transition `Diproses → Ditugaskan` dicatat
pada status history.

---

# 13. Penanganan Tiket

Existing UI **Tangani Tiket / Catatan Penanganan** tetap dapat digunakan.

Satu tiket dapat memiliki banyak record `ticket_handlings`.

Form penanganan:

```text
Status Tiket *
Waktu Mulai Perbaikan *
Waktu Selesai Perbaikan *
Catatan Penanganan *
Foto Hasil Perbaikan (opsional)
```

## 13.1 Waktu Penanganan

Waktu menggunakan behavior:

```text
otomatis terisi
tetapi dapat diedit oleh petugas
```

Default:

```text
Waktu Mulai Perbaikan   → waktu sekarang
Waktu Selesai Perbaikan → waktu sekarang
```

Petugas dapat mengoreksi waktu jika pekerjaan telah dilakukan sebelumnya dan baru dicatat setelahnya.

Validation:

```text
started_at wajib
completed_at wajib
completed_at >= started_at
```

## 13.2 Catatan Penanganan

Catatan wajib diisi.

Satu field `notes` digunakan untuk mencatat:

```text
tindakan
penyebab
hasil
keterangan tambahan
```

Tidak perlu memisahkan data tersebut menjadi field berbeda.

Foto hasil penanganan bersifat opsional.

## 13.3 Menangani Tiket Berstatus Ditugaskan

Ketika tiket pertama kali ditangani:

```text
status awal = ditugaskan
```

Petugas membuka **Tangani Tiket**.

Status pada form diarahkan ke:

```text
Diproses
```

Saat disimpan:

```text
buat ticket_handling
Ditugaskan → Diproses
buat ticket_status_history
```

## 13.4 Menangani Tiket Berstatus Diproses

Ketika tiket sudah `Diproses`, pilihan status pada modal dibatasi menjadi:

```text
Diproses
Terselesaikan
```

Jika memilih:

```text
Diproses
```

maka:

```text
buat ticket_handling
tickets.status tetap Diproses
tidak membuat status history baru
```

Jika memilih:

```text
Terselesaikan
```

maka:

```text
buat ticket_handling
Diproses → Terselesaikan
buat ticket_status_history
tickets.completed_at diisi berdasarkan waktu selesai penanganan yang disimpan
```

Dropdown penanganan tidak boleh digunakan untuk memilih seluruh status tiket secara bebas.

---

# 14. Eskalasi

Eskalasi adalah aksi terpisah dari modal penanganan.

Tiket hanya dapat dieskalasikan ketika:

```text
status = diproses
```

Yang boleh melakukan eskalasi:

```text
current assignee
Super Admin sesuai scope Helpdesk
Koordinator Sarpras untuk tiket Sarpras
```

Pilihan tujuan:

```text
Management
Vendor
Tim Terkait
```

Form:

```text
Tujuan Eskalasi *
Catatan Eskalasi *
```

Flow:

```text
Diproses
→ Eskalasi
```

Komunikasi dengan target eskalasi dilakukan di luar sistem, misalnya melalui telepon/WhatsApp/rapat.

Setelah menerima tindak lanjut, current assignee menekan:

```text
Lanjutkan Penanganan
```

Flow:

```text
Eskalasi
→ Diproses
```

---

# 15. Penyelesaian & Verifikasi

Yang berhak memverifikasi hasil penyelesaian adalah:

```text
reporter tiket
```

Role tidak menentukan hak verifikasi.

Pre-condition:

```text
status = terselesaikan
```

Reporter melihat ringkasan:

```text
catatan penanganan
foto hasil jika ada
petugas
waktu penyelesaian
```

## Setujui Penyelesaian

Flow:

```text
Terselesaikan
→ Ditutup
```

## Tolak Penyelesaian

Reporter wajib mengisi keterangan kendala.

Flow:

```text
Terselesaikan
→ Diproses
```

Keterangan disimpan pada:

```text
ticket_status_histories.notes
```

Assigned officer menerima notification untuk menangani kembali.

## Tidak Ada Verifikasi

Jika reporter tidak melakukan verifikasi selama 2 hari sejak
`tickets.completed_at`, sistem menutup tiket secara otomatis:

```text
Terselesaikan
→ Ditutup
```

Penutupan otomatis mencatat `closed_at` dan status history sebagai aksi sistem.

---

# 16. Initial Evidence & Result Photo

## Initial Evidence

Foto/bukti awal bersifat opsional.

Satu tiket hanya menggunakan satu initial evidence pada Sprint 1.

## Result Photo

Foto hasil penanganan bersifat opsional.

File disimpan melalui filesystem/object storage.

Database menyimpan metadata file, bukan binary/blob.

---

# 17. Notification

Event minimum:

```text
Ticket Created
→ notifikasi ke pihak yang melakukan klasifikasi

Ticket Rejected
→ reporter

Ticket Assigned
→ assigned officer

Ticket Escalated
→ notification internal sebagai informasi status

Ticket Resolved
→ reporter untuk verifikasi

Resolution Rejected
→ assigned officer

Ticket Closed
→ reporter
```

Komunikasi dengan target eskalasi tetap dilakukan di luar sistem.

---

# 18. Access Summary

| Action | Super Admin | Koord. Sarpras | Petugas TIK | Petugas Sarpras | User/Unit | Management |
|---|---|---|---|---|---|---|
| Create ticket | Yes | Yes | Yes | Yes | Yes | Yes |
| View all TIK | Yes | No | No | No | No | Read-only |
| View all Sarpras | Yes | Yes | No | No | No | Read-only |
| View own ticket | Yes | Yes | Yes | Yes | Yes | Yes |
| Classify TIK | Yes | No | No | No | No | No |
| Classify Sarpras | Yes | Yes | No | No | No | No |
| Assign TIK | Yes | No | No | No | No | No |
| Assign Sarpras | No | Yes | No | No | No | No |
| Handle assigned TIK | Normal flow via assigned officer | No | Yes | No | No | No |
| Handle assigned Sarpras | Normal flow via assigned officer | Supervisory scope | No | Yes | No | No |
| Escalate | According to management scope | Sarpras scope | Assigned TIK | Assigned Sarpras | No | No |
| Verify resolution | Only when reporter | Only when reporter | Only when reporter | Only when reporter | Only when reporter | Only when reporter |

---

# 19. Data Validation

## Create Ticket

```text
service required
service in: tik,sarpras
description required
asset_id nullable and valid
initial evidence nullable and valid file
```

## TIK Classification

```text
ticket status = baru
service = tik
quality_category required
it_tag required
custom_it_tag_text required when IT Tag = Lain-lain
```

## Sarpras Classification

```text
ticket status = baru
service = sarpras
sarpras_category required
```

## Assignment

```text
assigned officer required
assigned officer aktif dan memiliki role sesuai service
initial assignment: ticket status = diklasifikasi
initial assignment: priority required and in critical,high,medium,low
reassignment: ticket status = ditugaskan or diproses
reassignment: priority tidak dikirim ulang
```

## Handling

```text
ticket status = ditugaskan or diproses
notes required
started_at required
completed_at required
completed_at >= started_at
result photo optional
selected status must follow allowed transition
```

## Escalation

```text
ticket status = diproses
target required
target in:
- management
- vendor
- related_team
notes required
```

## Verification

```text
ticket status = terselesaikan
authenticated user = reporter_id
```

---

# 20. Transaction Rules

Operasi berikut harus atomic menggunakan database transaction:

```text
klasifikasi + status history
assignment/reassignment + status history ketika status berubah
penanganan + status transition
escalation + status history
lanjut penanganan setelah escalation
verification + status history
```

Jangan sampai `tickets.status` berubah tanpa history ketika memang terjadi transition.

---

# 21. Existing Implementation Alignment

Existing implementation tiket yang sudah dibuat **tidak perlu dibuang dari nol**.

Coding agent harus:

```text
audit implementation existing
bandingkan dengan SRS ini
bandingkan dengan DATABASE-UPDATED.md
pertahankan UI/flow yang masih sesuai
ubah behavior yang bertentangan
hapus legacy logic yang tidak diperlukan
```

Khusus modal **Catatan Penanganan**, pertahankan bentuk UI existing sebisa mungkin:

```text
Status Tiket
Waktu Mulai Perbaikan
Waktu Selesai Perbaikan
Catatan Penanganan
Foto Hasil Perbaikan
```

Yang harus disesuaikan terutama:

```text
allowed status
status transition
history
timestamp behavior
database mapping
validation
```

---

# 22. Database Reference

Struktur database detail mengacu pada:

```text
docs/DATABASE-UPDATED.md
docs/SIMPLE-PLAN-Sprint1-UPDATED.dbml
```

Jangan menduplikasi schema detail yang sudah menjadi tanggung jawab dokumen database.

Jika behavior SRS membutuhkan perubahan schema, update dokumen database dan DBML terlebih dahulu sebelum mengubah migration.

---

# 23. Out of Scope

Jangan menambah secara otomatis:

```text
ticket_assignments
ticket_verifications
ticket_attachments
ticket_it_tags
damage_types
vendors
teams
generic audit_logs
```

Sprint 1 tidak mengimplementasikan:

```text
Maintenance
Design Grafis
full Inventory
barcode scanner
advanced reporting
```

---

# 24. Acceptance Conditions Sprint 1

Sprint 1 Helpdesk dianggap sesuai requirement jika:

1. Semua role yang login dapat membuat tiket.
2. TIK dan Sarpras menggunakan workflow status yang sama.
3. Tiket dapat dibuat dengan/tanpa asset.
4. Klasifikasi hanya dilakukan pada tiket `Baru`.
5. TIK dan Sarpras memakai master klasifikasi masing-masing.
6. Assignment mengubah status menjadi `Ditugaskan`.
7. Penanganan dapat mempunyai banyak catatan.
8. Existing modal penanganan bekerja sesuai transition yang diizinkan.
9. Eskalasi mencatat target dan kembali ke `Diproses` setelah tindak lanjut.
10. Tiket hanya dapat diverifikasi oleh reporter.
11. Reporter dapat menolak penyelesaian dan tiket kembali `Diproses`.
12. Tiket yang disetujui langsung menjadi `Ditutup`.
13. Tiket yang tidak diverifikasi selama 2 hari sejak `completed_at` otomatis menjadi `Ditutup`.
14. SLA dihitung dari assignment pertama sampai `Terselesaikan`.
15. Notification minimum berjalan pada event Helpdesk utama.
16. Existing user/master data tidak mengalami refactor naming yang tidak diperlukan.
