# Assignment

## Implemented

- Assignment TIK kepada user aktif dengan role `super-admin` atau `petugas-tik`.
- Assignment Sarpras kepada user aktif dengan role `koordinator-sarpras` atau
  `petugas-sarpras`.
- Priority ditentukan pada assignment pertama.
- Assignment pertama langsung mengubah tiket dari `diklasifikasi` menjadi
  `diproses` tanpa status `ditugaskan`.
- SLA dihitung dari `sla_started_at` ketika tiket pertama kali masuk
  `diproses`.
- Reassignment dilakukan saat status `diproses` tanpa mengubah status tiket.
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
- `sla_started_at`, priority, dan `sla_deadline` tidak direset saat
  reassignment.
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
  `assigned_at`, `sla_started_at`, dan `sla_deadline`, lalu mengubah status
  `diklasifikasi` langsung menjadi `diproses` beserta status history.
- Reassignment dari `diproses` memperbarui petugas dan `assigned_at`, tetapi
  mempertahankan status serta waktu SLA pertama tanpa membuat status history
  redundan.
- OpenAPI statis, anotasi Swagger, dan generated Swagger telah diselaraskan.
- Validation: syntax check PASS; Pint PASS; Swagger generation PASS;
  `php artisan optimize:clear` PASS; route inspection PASS; 35 test terfokus
  dengan 215 assertion PASS.

## Pending

- handling;
- escalation;
- resolution;
- notification.
