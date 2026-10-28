# Pemeliharaan Aset (Maintenance)

## Scope
Pengelolaan jadwal pemeliharaan aset berulang (recurring) dan pencatatan log eksekusi fisik, dilengkapi kalkulasi waktu otomatis dan notifikasi keterlambatan.

## Implemented
- **Master Jadwal (Induk)**: CRUD aturan jadwal pemeliharaan (jenis pekerjaan, periode, petugas, dan status aktif).
- **Domain Isolation**: Super Admin (Aset TIK & Petugas TIK), Koordinator Sarpras (Aset Sarpras & Petugas Sarpras).
- **Kalkulasi Next Date**: `AssetMaintenanceService` menghitung otomatis tanggal jadwal berikutnya (mingguan, bulanan, triwulan, semester, tahunan) dari tanggal jadwal lama, mencegah *schedule drift*.
- **Eksekusi Log**: Petugas dapat mengirimkan form eksekusi (uraian, hasil, kondisi, foto) yang mengembalikan status jadwal ke 'terjadwal'.
- **Cron Job & Notifikasi**: Task Scheduler (`maintenance:check-status`) berjalan pukul 00:00. Mengecek jadwal *overdue* (status 'terlambat' + notifikasi pembuat), serta mengirim notifikasi pengingat (H-3, H-1, Hari H) ke petugas.

## Authorization
- **Create/Update Jadwal**: `super-admin`, `koordinator-sarpras`.
- **Submit Log Eksekusi**: Hanya User (Petugas) yang ditunjuk pada `assigned_officer_id`.
- **Read-Only**: `manajemen`.

## Routes
```text
GET|POST         /api/v1/inventory/asset-maintenances
GET|PUT|DELETE   /api/v1/inventory/asset-maintenances/{maintenance}
POST             /api/v1/inventory/asset-maintenances/{maintenance}/logs
```
