# Classification

## Implemented

- Klasifikasi TIK menyimpan Kategori Mutu, IT Tag, custom IT Tag untuk
  `Lain-lain`, priority, classifier, waktu klasifikasi, dan SLA deadline.
- Klasifikasi Sarpras menyimpan kategori aktif `Sarpras`, `Elektronik`, atau
  `Alkes`, beserta priority, classifier, waktu klasifikasi, dan SLA deadline.
- SLA dihitung 24/7 sejak `classified_at`: critical 2 jam, high 4 jam, medium
  1 hari, dan low 3 hari.
- Klasifikasi mengubah status `baru` menjadi `diklasifikasi` dan membuat status
  history dalam transaksi yang sama dengan detail dan update ticket.
- Rejection mewajibkan alasan, mengubah `baru` menjadi `ditolak`, dan menyimpan
  alasan pada `ticket_status_histories.notes`.
- Priority tidak dapat diubah lagi melalui endpoint assignment existing.

## Authorization

- TIK: hanya `super-admin` yang dapat classify atau reject.
- Sarpras: `super-admin` dan `koordinator-sarpras` dapat classify atau reject.
- Middleware tetap menggunakan capability existing `tickets-verify` untuk
  classify dan `tickets-reject` untuk reject. `TicketPolicy` menerapkan rule
  actor per service di backend.
- `koordinator-sarpras` sekarang menerima kedua capability tersebut dari role
  seeder. Role lain tetap ditolak.

## Routes

```text
POST /api/v1/tickets/{ticket}/classify
POST /api/v1/tickets/{ticket}/reject
```

## Notes

- Route legacy `/tickets/{ticket}/verify` diganti menjadi `/classify` karena
  action sekarang benar-benar menerima dan menyimpan data klasifikasi.
- Hanya ticket berstatus `baru` yang dapat diklasifikasi atau ditolak. Percobaan
  ulang setelah status berubah ditolak oleh transition foundation.
- Request klasifikasi hanya menerima master data aktif. Custom IT Tag wajib dan
  hanya boleh diisi ketika IT Tag adalah `Lain-lain`.
- OpenAPI, anotasi Swagger, dan generated Swagger artifact sudah diselaraskan
  dengan request TIK/Sarpras, actor, validation, dan response aktual.
- Repository ini tidak memiliki UI Helpdesk selain Swagger UI, sehingga tidak
  ada form/button frontend yang diubah.
- Validation: `php artisan optimize:clear`, `db:seed --class=RoleSeeder`, route
  inspection, Pint, Swagger generation, serta 31 test terfokus dengan 197
  assertion berhasil.

## Pending

- assignment;
- handling;
- escalation;
- resolution;
- notification.
