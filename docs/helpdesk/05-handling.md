# Ticket Handling

## Implemented

- Mulai penanganan dari tiket berstatus `ditugaskan`.
- Multiple handling records pada satu tiket.
- Catatan serta waktu mulai/selesai penanganan wajib.
- Satu foto hasil opsional melalui Laravel Filesystem beserta metadata file.
- Penyelesaian pekerjaan teknis mengisi `tickets.completed_at` dari
  `ticket_handlings.completed_at` tanpa menghitung ulang SLA.

## Status Flow

```text
Ditugaskan → Diproses
Diproses → Diproses (tanpa status history)
Diproses → Terselesaikan
```

Transition yang benar-benar mengubah status membuat
`ticket_status_histories`. Status tetap disimpan pada `tickets.status`; tabel
`ticket_handlings` tidak mempunyai kolom status.

## Authorization

Handling hanya dapat dilakukan oleh authenticated user yang:

- memiliki permission `tickets-handle`;
- sama dengan `tickets.assigned_officer_id`; dan
- memiliki role `petugas-tik` untuk tiket TIK atau `petugas-sarpras` untuk
  tiket Sarpras.

Role Petugas TIK dan Petugas Sarpras menerima permission `tickets-handle` dari
role seeder. Policy dan pemeriksaan ulang pada ticket yang dikunci menegakkan
current assignment di backend.

## Handling Fields

- `status`: `diproses` untuk tiket Ditugaskan; `diproses` atau `terselesaikan`
  untuk tiket Diproses.
- `started_at`: wajib dan editable.
- `completed_at`: wajib, harus sama atau setelah `started_at`.
- `notes`: wajib.
- `result_photo`: opsional, satu image maksimal 2 MB.
- `handled_by_id`: selalu berasal dari authenticated user.

## Routes

```text
POST /api/v1/tickets/{ticket}/handlings
```

## Notes

- Controller, model/relationship, transaction, storage abstraction, metadata
  foto, cleanup file saat gagal, dan transition helper existing dipertahankan.
- Request validation kini mengikuti status ticket saat ini dan kedua field
  waktu diwajibkan sesuai SRS.
- Repository ini tidak memiliki UI Helpdesk selain Swagger UI. Default waktu
  sekarang, visibilitas action, dan refresh modal tetap menjadi tanggung jawab
  frontend yang mengonsumsi endpoint ini.
- Static OpenAPI, anotasi Swagger, dan generated Swagger telah diselaraskan.
- Validation: Pint PASS; Swagger generation PASS; `php artisan optimize:clear`
  PASS; route inspection PASS; 61 test terfokus dengan 344 assertion PASS.

## Pending

- escalation;
- resolution verification;
- notification.
