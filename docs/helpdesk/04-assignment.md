# Assignment

## Implemented

- Assignment TIK kepada user aktif dengan role `petugas-tik`.
- Assignment Sarpras kepada user aktif dengan role `petugas-sarpras`.
- Priority ditentukan pada assignment pertama.
- SLA dihitung dari `assigned_at` assignment pertama.
- Reassignment dari status `ditugaskan` atau `diproses`.
- Live search kandidat petugas melalui endpoint ringan.

## Authorization

- TIK: hanya `super-admin`.
- Sarpras: hanya `koordinator-sarpras`.
- Middleware memerlukan permission `tickets-assign`; `TicketPolicy` tetap
  membatasi actor berdasarkan service tiket.

## SLA

- Critical: 2 jam.
- High: 4 jam.
- Medium: 1 hari.
- Low: 3 hari.
- Priority dan `sla_deadline` tidak direset saat reassignment.
- `assigned_at` diperbarui untuk mencatat waktu assignment petugas terbaru.

## Search Petugas

- Search mencocokkan `users.name` atau `users.jabatan`.
- Kandidat dibatasi pada user aktif dan role sesuai service tiket.
- Response hanya memuat `id`, `name`, dan `jabatan`, maksimal 20 kandidat.
- Endpoint siap dikonsumsi UI dengan live search debounce sekitar 300 ms.
  Repository backend ini tidak memuat UI Helpdesk yang dapat diubah.

## Routes

```text
GET  /api/v1/tickets/{ticket}/assignee-options?search=...
POST /api/v1/tickets/{ticket}/assign
```

## Notes

- Classification hanya menyimpan detail klasifikasi, `classified_by_id`, dan
  `classified_at`; priority/SLA dipindahkan ke assignment pertama.
- Assignment pertama menerima `assigned_officer_id` dan `priority`, mengisi
  `assigned_at` dan `sla_deadline`, lalu mengubah status `diklasifikasi` menjadi
  `ditugaskan` beserta status history.
- Reassignment dari `ditugaskan` mempertahankan status tanpa membuat history.
- Reassignment dari `diproses` mengubah status kembali ke `ditugaskan` dan
  membuat status history.
- OpenAPI statis, anotasi Swagger, dan generated Swagger telah diselaraskan.
- Validation: syntax check PASS; Pint PASS; Swagger generation PASS;
  `php artisan optimize:clear` PASS; route inspection PASS; 35 test terfokus
  dengan 215 assertion PASS.

## Pending

- handling;
- escalation;
- resolution;
- notification.
