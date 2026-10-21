# Flow 8: Notification

## Scope
Sistem notifikasi database dan siaran real-time (broadcast).

## Implemented
- Pembuatan class `TicketStatusUpdatedNotification`.
- Pembuatan tiket TIK memberi tahu Super Admin; tiket Sarpras memberi tahu Super Admin dan Koordinator Sarpras.
- Penolakan tiket memberi tahu pelapor; assignment memberi tahu petugas yang ditugaskan. Reassignment ke petugas yang sama tidak membuat notifikasi baru.
- Eskalasi TIK memberi tahu Super Admin; eskalasi Sarpras memberi tahu Koordinator Sarpras.
- Tiket terselesaikan memberi tahu pelapor; hasil verifikasi pelapor memberi tahu petugas yang ditugaskan.
- Semua notifikasi tiket dikirim setelah perubahan tiket berhasil disimpan.

**Struktur Payload JSON:**
Frontend (via WebSocket/Database) akan menerima payload dengan struktur sebagai berikut:
```json
{
  "ticket_id": 123,
  "nomor_tiket": "TIK-2026-000123",
  "status": "ditutup",
  "message": "Pelapor telah memverifikasi penyelesaian tiket TIK-2026-000123. Tiket kini berstatus Ditutup.",
  "title": "Penyelesaian Tiket Diverifikasi",
  "subtitle": "Pelapor telah memverifikasi penyelesaian tiket TIK-2026-000123. Tiket kini berstatus Ditutup.",
  "url": "/tickets/123",
  "icon": "IconTicket",
  "color": "blue"
}
```

## Status Flow
-

## Authorization
Mengikuti masing-masing flow tiket. Daftar notifikasi hanya menampilkan notifikasi milik user yang terautentikasi.

## Routes
`GET /api/v1/notifications` menampilkan `id`, `title`, `subtitle`, `ticket_id`, `nomor_tiket`, `url`, `is_read`, dan `created_at` per notifikasi. Field tiket dan URL bernilai `null` untuk notifikasi non-tiket.

`PATCH /api/v1/notifications/{id}/read` dan `PATCH /api/v1/notifications/read-all` menggunakan endpoint yang sudah ada.

## Notes
- Informasi di atas sangat penting agar Frontend tahu `key` apa saja yang dapat dirender ke dalam komponen pop-up/lonceng notifikasi (terutama `title`, `subtitle`, `icon`, dan `color`).

## Pending
- Penerapan fallback notification (misalnya via email) belum diaktifkan secara default.
