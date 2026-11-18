# Pemeliharaan Aset (Maintenance)

## Scope
Pengelolaan jadwal pemeliharaan aset berulang (recurring) dan pencatatan log eksekusi fisik, dilengkapi kalkulasi waktu otomatis dan notifikasi keterlambatan.

## Implemented
- **Master Jadwal (Induk)**: CRUD aturan jadwal pemeliharaan (jenis pekerjaan, periode, petugas, dan status aktif).
- **Domain Isolation**: Super Admin (Aset TIK & Petugas TIK), Koordinator Sarpras (Aset Sarpras & Petugas Sarpras).
- **Kalkulasi Next Date**: `AssetMaintenanceService` menghitung otomatis tanggal jadwal berikutnya (mingguan, bulanan, triwulan, semester, tahunan) dari tanggal jadwal lama, mencegah *schedule drift*.
- **Eksekusi Log**: Petugas dapat mengirimkan form eksekusi (uraian, hasil, kondisi, foto) yang mengembalikan status jadwal ke 'terjadwal'.
- **Pelacakan Kejadian Jadwal (`asset_maintenance_occurrences`)**: Setiap siklus jadwal pemeliharaan dilacak secara idempoten (unik per jadwal dan `scheduled_date`), tersinkronisasi saat pembuatan/pembaruan jadwal, otomatis ditautkan ke `asset_maintenance_logs` saat realisasi beserta status keterlambatan (`is_late`), serta diperbarui status keterlambatannya oleh `maintenance:check-status`.
- **Kalkulasi Status Dinamis**: Menggunakan Model Accessor pada atribut `status` untuk mengevaluasi status keterlambatan secara *on-the-fly* ketika `next_scheduled_date` < hari ini, tanpa mutasi database.
- **Sistem Notifikasi Hibrida**: Menggabungkan pendekatan Event-Driven (penembakan langsung saat H-0 pada method store/update) dan Time-Driven (Task Scheduler harian pukul 00:00 untuk notifikasi H-3, H-1, dan eskalasi keterlambatan ke manajer).

## Authorization
- **Create/Update Jadwal**: `super-admin`, `koordinator-sarpras`.
- **Submit Log Eksekusi**: Hanya User (Petugas) yang ditunjuk pada `assigned_officer_id`.
- **Read-Only**: `manajemen`.

## Routes
```text
GET|POST         /api/v1/inventory/asset-maintenances
GET|PUT          /api/v1/inventory/asset-maintenances/{maintenance}
POST             /api/v1/inventory/asset-maintenances/{maintenance}/logs
```
