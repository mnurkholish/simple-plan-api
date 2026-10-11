# Manajemen Aset

## Scope
Pengelolaan siklus hidup aset fisik rumah sakit mulai dari registrasi berkas, pencarian lintas relasi, pembaruan data dan mutasi kode otomatis, hingga soft delete yang aman.

## Implemented
- **Preview Kode**: `GET /api/v1/assets/generate-number` untuk mempratinjau kode fisik aset sebelum penyimpanan.
- **Live Search & Multi-Filter**: Pencarian teks bebas pada serial number, full code, nama barang, dan merek barang, serta filter `status`, `condition`, `asset_classification_id`, `asset_location_id`, `asset_item_id`, `purchase_year`, dan `per_page`.
- **Create Asset**: Mendukung upload `photo` (JPG/PNG maks 5MB) dan `calibration_document` (PDF maks 5MB) atomik dalam `DB::transaction`.
- **Partial Update & Smart Code Recalculation**: Seluruh field menggunakan aturan `sometimes`; sistem otomatis menghitung ulang `sequence_number` dan `full_code` baru hanya jika klasifikasi, lokasi, barang, atau tahun berubah. Mendukung `multipart/form-data` via spoofing `POST _method=PUT`.
- **Safe Soft Delete**: `DELETE /api/v1/assets/{asset}` melakukan soft delete pada `assets` dan `asset_codes` tanpa menghapus file fisik di Storage, mencegah kegagalan relasi pada tabel `tickets` serta menjaga integritas nomor urut fisik.

## Authorization
- **Full Access (CRUD & Soft Delete)**: `super-admin`, `koordinator-sarpras`, `petugas-tik`, `petugas-sarpras`.
- **Read-Only**: `manajemen`.

## Routes
```text
GET              /api/v1/assets/generate-number
GET              /api/v1/assets/search
GET|POST         /api/v1/assets
GET|PUT|DELETE   /api/v1/assets/{asset}
POST             /api/v1/assets/{asset} (spoofing _method=PUT)
```

## Pending
- Cetak Label QR / Barcode termal (`GET /api/v1/assets/{asset}/label`).
- Ekspor rekapitulasi data inventaris ke format PDF dan Excel.
