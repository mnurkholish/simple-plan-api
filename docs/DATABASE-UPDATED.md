# SIMPLE PLAN — Database Guidelines (Sprint 1)

Panduan ini menjadi acuan database **Sprint 1: Helpdesk + Master Data** untuk existing project Laravel + MySQL.

> **Keputusan terbaru:** struktur `users` existing dipertahankan terlebih dahulu. Field lama yang masih campur Indonesia–Inggris tidak perlu direname sekarang. Semua tabel/field baru setelah ini menggunakan bahasa Inggris.

## 1. Existing Master Data

### `units`

Pertahankan:

```text
id
unit_name
slug
description
created_at
updated_at
deleted_at
```

`slug` tetap unique.

### `users`

Jangan rename/hapus field existing untuk sekarang:

```text
id
name
email
email_verified_at
password
remember_token
created_at
updated_at
iam_id
nip
avatar
status
unit_id
jabatan
no_hp
status_user
alasan_nonaktif
riwayat_status_akun
```

Constraint existing yang dipertahankan:

```text
email UNIQUE
iam_id UNIQUE
nip UNIQUE
no_hp UNIQUE
```

Catatan:

- `jabatan`, `no_hp`, `status_user`, `alasan_nonaktif`, dan `riwayat_status_akun` tetap seperti existing.
- `status` dan `status_user` dibiarkan dulu.
- `riwayat_status_akun` juga dibiarkan dulu.
- Jangan menambahkan field pengganti seperti `position`, `phone`, atau `inactive_reason`.
- `unit_id` digunakan untuk relasi satu user ke satu unit.
- `unit_id` boleh nullable sementara karena data existing masih dapat bernilai NULL.
- Tidak perlu tabel pivot `unit_user`.

Relasi target:

```text
units 1 ─── N users
users.unit_id -> units.id
```

## 2. Spatie Laravel Permission

Pertahankan struktur bawaan Spatie:

```text
roles
permissions
model_has_roles
model_has_permissions
role_has_permissions
```

Jangan menambahkan `users.role_id`.

Business rule:

```text
satu user = satu role aktif
```

Gunakan:

```php
$user->syncRoles($role);
```

Jangan rename role existing secara otomatis jika masih dipakai oleh code/seeder.

## 3. Naming Convention

Existing `users` boleh tetap campur bahasa.

Semua tabel/field baru menggunakan bahasa Inggris, misalnya:

```text
quality_categories
it_tags
sarpras_categories
ticket_status_histories
ticket_handlings
ticket_escalations
classified_at
assigned_at
sla_deadline
```

Nilai master yang tampil ke user tetap boleh berbahasa Indonesia.

## 4. Assets

### `assets`

```text
id
asset_number
name
brand
unit_id
location
status
created_at
updated_at
deleted_at
```

Aturan:

- `asset_number` unique.
- Barcode/QR menggunakan `asset_number`.
- Tidak perlu `barcode_value`.
- Asset tidak dimiliki user.

Jangan membuat:

```text
assigned_user_id
owner_user_id
custodian_user_id
```

## 5. Tickets

### `tickets`

```text
id
ticket_number
service
reporter_id
unit_id
asset_id
description
status
priority
classified_by_id
classified_at
assigned_officer_id
assigned_at
sla_deadline
completed_at
closed_at
initial_evidence_object_key
initial_evidence_original_name
initial_evidence_mime_type
initial_evidence_size
created_at
updated_at
```

`service`:

```text
tik
sarpras
```

Rules:

- `service` immutable setelah tiket dibuat.
- `tickets.unit_id` adalah snapshot unit reporter saat tiket dibuat.
- `asset_id` nullable.
- Tiket boleh dibuat tanpa asset.

## 6. Nomor Tiket

Format:

```text
TIK-YYYY-NNNNNN
SPR-YYYY-NNNNNN
```

Contoh:

```text
TIK-2026-000001
SPR-2026-000001
```

Sequence reset setiap tahun per service.

`tickets.ticket_number` harus unique.

Generator nomor tiket berada di application layer dan harus aman terhadap concurrent request.

## 7. Priority dan SLA

Priority:

```text
critical
high
medium
low
```

SLA:

| Priority   | Target |
| ---------- | -----: |
| `critical` |  2 jam |
| `high`     |  4 jam |
| `medium`   | 1 hari |
| `low`      | 3 hari |

SLA menggunakan waktu kalender 24/7 dan mulai dari `assigned_at` pada assignment pertama.

```text
sla_deadline = assigned_at pertama + SLA(priority)
```

Jangan simpan `status_sla`; hitung dinamis.

## 8. TIK Classification

### `quality_categories`

```text
id
name
is_active
created_at
updated_at
```

Seed awal:

1. Kepatuhan Input Operator
2. Ketidakstabilan System
3. Ketidaksesuaian Program
4. Akun dan Hak Akses System
5. Waktu Tanggap Kerusakan Hardware
6. Waktu Tanggap Kerusakan Software

### `it_tags`

```text
id
name
is_active
created_at
updated_at
```

Seed mengikuti daftar Tags IT yang telah ditetapkan, termasuk `Lain-lain`.

### `ticket_tik_details`

```text
ticket_id
quality_category_id
it_tag_id
custom_it_tag_text
created_at
updated_at
```

Rules:

- Relasi 1:1 dengan tiket TIK.
- Dibuat ketika tiket diklasifikasikan.
- Satu tiket TIK hanya satu IT tag.
- `custom_it_tag_text` hanya untuk `Lain-lain`.
- Tidak perlu `ticket_it_tags`.

## 9. Sarpras Classification

### `sarpras_categories`

```text
id
name
is_active
created_at
updated_at
```

Seed:

```text
Sarpras
Elektronik
Alkes
```

### `ticket_sarpras_details`

```text
ticket_id
sarpras_category_id
created_at
updated_at
```

Relasi 1:1 dengan tiket Sarpras.

Tidak perlu `damage_types`. Detail kerusakan cukup di `tickets.description`.

## 10. Ticket Status

Status:

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

Workflow:

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

- `Ditolak` hanya dari `Baru`.
- `Ditolak` dan `Ditutup` adalah terminal state.
- Tiket `Ditutup` tidak di-reopen; buat tiket baru.

## 11. Ticket Status History

### `ticket_status_histories`

```text
id
ticket_id
from_status
to_status
changed_by_id
notes
created_at
```

Rules:

- Insert hanya saat status berubah.
- `changed_by_id` boleh null untuk transisi otomatis.
- Alasan penolakan disimpan di `notes`.
- Keterangan penolakan hasil penyelesaian disimpan di `notes`.
- Tidak perlu `tickets.rejection_reason`.

## 12. Assignment

Current assignment disimpan langsung di:

```text
tickets.assigned_officer_id
tickets.assigned_at
```

Tidak perlu `ticket_assignments`.

Reassignment mengganti current assignee.

Tidak perlu database constraint berdasarkan role; filtering kandidat dilakukan di application/UI layer.

## 13. Ticket Handling

### `ticket_handlings`

```text
id
ticket_id
handled_by_id
notes
started_at
completed_at
result_photo_object_key
result_photo_original_name
result_photo_mime_type
result_photo_size
created_at
updated_at
```

Penyebab, tindakan, hasil, dan keterangan tambahan cukup di `notes`.

Jangan membuat `ticket_handlings.status`.

## 14. Escalation

### `ticket_escalations`

```text
id
ticket_id
escalated_by_id
target
notes
escalated_at
created_at
updated_at
```

Pilihan target:

```text
management
vendor
related_team
```

Komunikasi dengan pihak tersebut dilakukan di luar sistem.

Tidak perlu:

```text
type
target_user_id
vendor_id
resolved_at
resolved_by_id
```

Flow:

```text
diproses -> eskalasi -> diproses
```

## 15. Verification

Tidak perlu tabel `ticket_verifications`.

Jika disetujui:

```text
terselesaikan
-> terverifikasi
-> ditutup
```

Jika ditolak pelapor:

```text
terselesaikan
-> diproses
```

Keterangan disimpan di `ticket_status_histories.notes`.

## 16. Initial Evidence

Satu initial evidence per tiket.

Metadata berada langsung di `tickets`:

```text
initial_evidence_object_key
initial_evidence_original_name
initial_evidence_mime_type
initial_evidence_size
```

File fisik disimpan di filesystem/object storage.

Tidak perlu `ticket_attachments`.

## 17. Struktur yang Tidak Dibutuhkan

Jangan membuat:

```text
unit_user
ticket_assignments
ticket_verifications
ticket_attachments
ticket_it_tags
damage_types
vendors
teams
```

Jangan membuat:

```text
assets.barcode_value
assets.assigned_user_id
assets.owner_user_id
assets.custodian_user_id
tickets.rejection_reason
ticket_handlings.status
```

## 18. Migration Strategy

Project masih development, sehingga migration lama boleh:

- diedit,
- digabung,
- dihapus jika tidak diperlukan,
- dirapikan agar schema final bersih.

Target:

```bash
php artisan migrate:fresh --seed
```

harus berhasil.

Jangan merusak migration package Spatie dan jangan refactor struktur `users` sekarang.

Recommended order:

```text
1. units
2. users
3. Spatie permission tables
4. assets
5. quality_categories
6. it_tags
7. sarpras_categories
8. tickets
9. ticket_tik_details
10. ticket_sarpras_details
11. ticket_status_histories
12. ticket_handlings
13. ticket_escalations
14. seed master data
```

## 19. Laravel Rules

- Gunakan Eloquent relationships.
- Gunakan Form Request untuk validation.
- Gunakan PHP backed enum untuk nilai terbatas baru.
- Gunakan transaction untuk operasi multi-table.
- Update status tiket dan insert status history harus atomic.
- Password menggunakan Laravel `Hash`.
- Ikuti arsitektur existing project.
- Jangan menambah abstraction yang tidak diperlukan.
- Jangan refactor `users` hanya untuk menyamakan naming.

## 20. Source of Truth

Urutan acuan:

1. Dokumen ini.
2. DBML Sprint 1.
3. Workflow bisnis yang telah disepakati.
4. SRS/SR terbaru.
5. Existing implementation sebagai referensi kompatibilitas.

Khusus tabel `users`, struktur existing sengaja dipertahankan sampai ada keputusan refactor terpisah.
