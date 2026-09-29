# Flow 9: SLA Filtering

## Scope
Filter status SLA (Service Level Agreement) dinamis pada daftar tiket.

## Implemented
- Penambahan parameter `status_sla` pada List Ticket.
- Kalkulasi status on-the-fly (`melewati_batas`, `mendekati_batas`, `tepat_waktu`) menggunakan query builder di TicketRepository berdasarkan perbandingan waktu saat ini/waktu selesai dengan `sla_deadline`.
- Tidak menggunakan cron job.

## Status Flow
-

## Authorization
Mengikuti otorisasi list ticket (`tickets-access`).

## Routes
- **Endpoint API**: `GET /api/v1/tickets?status_sla={value}`
- Nilai `{value}` yang valid adalah salah satu dari: `melewati_batas`, `mendekati_batas`, atau `tepat_waktu`.

## Notes
- **Response API:** Pada JSON response list tiket (`TicketResource`), Frontend akan menerima atribut tambahan bernama `status_sla`. Atribut ini berisi label status hasil kalkulasi on-the-fly yang bisa langsung dirender sebagai *badge* warna-warni di UI tanpa perlu melakukan perhitungan ulang di klien.

## Pending
- Penyesuaian konfigurasi durasi SLA per kategori di menu pengaturan belum dinamis (masih menggunakan nilai default sistem).
