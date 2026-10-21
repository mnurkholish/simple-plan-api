# Manajemen Aset

## Scope
Pengelolaan siklus hidup aset fisik rumah sakit mulai dari registrasi berkas, pencarian lintas relasi, pembaruan data dan mutasi kode otomatis, hingga soft delete yang aman.

## Implemented
- **Preview Kode**: `GET /api/v1/assets/generate-number` untuk mempratinjau kode fisik aset sebelum penyimpanan.
- **Live Search & Multi-Filter**: Pencarian teks bebas pada serial number, full code, nama barang, dan merek barang. Filter `location_id`, `classification_id`, `status`, dan `moved_last_3_months` dapat dikombinasikan dengan filter lama `condition`, `asset_item_id`, dan `purchase_year`. Alias `asset_location_id` dan `asset_classification_id` tetap diterima. Perpindahan memakai riwayat mutasi ruangan tiga bulan terakhir.
- **Cascading Search**: `GET /asset-locations` menyediakan daftar lokasi, `GET /asset-items?location_id={id}` menyediakan jenis barang yang tersedia pada lokasi tersebut, dan `GET /assets?location_id={id}&asset_item_id={id}` menyediakan pilihan aset akhirnya.
- **Create Asset**: Mendukung upload `photo` (JPG/PNG maks 5MB) dan `calibration_document` (PDF maks 5MB) atomik dalam `DB::transaction`.
- **Partial Update & Riwayat**: Seluruh field menggunakan aturan `sometimes`; perubahan klasifikasi, lokasi ruangan, barang, atau tahun pembelian memperbarui record `asset_codes` yang sama. Satu aksi update mencatat satu `asset_histories` berisi field yang berubah. Detail lokasi tidak mengubah nomor inventaris. Mendukung `multipart/form-data` via spoofing `POST _method=PUT`.
- **Safe Soft Delete**: `DELETE /api/v1/assets/{asset}` melakukan soft delete pada `assets`, sedangkan record `asset_codes` tetap ada agar nomor tidak terbit ulang dan relasi `tickets.asset_id` tetap aman.
- **Label inventaris**: `GET /api/v1/assets/{asset}/label` menyediakan preview HTML serta ekspor PNG/PDF. `POST /api/v1/asset-label-exports` membuat PDF A4 sinkron dalam mode `selected` (urutan `asset_ids`) atau `all_filtered` (seluruh hasil filter tanpa pagination, urutan ID menurun). Request lama tanpa `selection_mode` tetap menggunakan `selected`. Hanya aset berkode inventaris dapat diberi label; setiap aset memerlukan izin `printLabel`. Ukuran label 70–150 × 40–100 mm, margin 10 mm, dan jarak 2 mm.

## Authorization
- **Create Asset**: `super-admin`, `koordinator-sarpras`.
- **Read, Update, & Soft Delete**: `super-admin`, `koordinator-sarpras`, `petugas-tik`, `petugas-sarpras`.
- **Read-Only**: `manajemen`.

## Routes
```text
GET              /api/v1/assets/generate-number
GET              /api/v1/assets/search
GET|POST         /api/v1/assets
GET|PUT|DELETE   /api/v1/assets/{asset}
POST             /api/v1/assets/{asset} (spoofing _method=PUT)
GET              /api/v1/assets/{asset}/label
POST             /api/v1/asset-label-exports
```

## Pending
- Ekspor rekapitulasi data inventaris ke format PDF dan Excel.
