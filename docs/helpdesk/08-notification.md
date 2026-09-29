# Flow 8: Notification

## Scope
Sistem notifikasi database dan siaran real-time (broadcast).

## Implemented
- Pembuatan class `TicketStatusUpdatedNotification`.
- Injeksi notifikasi pada saat eskalasi tiket (dikirim ke target manajemen/terkait).
- Injeksi notifikasi saat verifikasi tiket (dikirim ke teknisi terkait hasil persetujuan/penolakan).

**Struktur Payload JSON:**
Frontend (via WebSocket/Database) akan menerima payload dengan struktur sebagai berikut:
```json
{
  "ticket_id": 123,
  "nomor_tiket": "TIK-2026-000123",
  "status": "ditutup",
  "message": "Penyelesaian tiket #TIK-2026-000123 telah disetujui (Ditutup).",
  "title": "Update Status Tiket",
  "subtitle": "Penyelesaian tiket #TIK-2026-000123 telah disetujui (Ditutup).",
  "icon": "IconTicket",
  "color": "blue"
}
```

## Status Flow
-

## Authorization
Mengikuti masing-masing flow (eskalasi dan verifikasi).

## Routes
Menggunakan endpoint bawaan dari `NotificationController`.

## Notes
- Informasi di atas sangat penting agar Frontend tahu `key` apa saja yang dapat dirender ke dalam komponen pop-up/lonceng notifikasi (terutama `title`, `subtitle`, `icon`, dan `color`).

## Pending
- Penerapan fallback notification (misalnya via email) belum diaktifkan secara default.
