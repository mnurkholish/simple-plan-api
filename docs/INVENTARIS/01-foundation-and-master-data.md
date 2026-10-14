# Master Data Inventaris

## Scope
Fondasi Master Data modul inventaris yang mencakup enum berbahasa Indonesia, data master klasifikasi, lokasi, barang, dan generator kode aset fisik otomatis.

## Implemented
- **Enums Bahasa Indonesia**:
  - `AssetStatus`: `Digunakan`, `Tidak Digunakan`, `Rusak`, `Dipinjam`.
  - `AssetCondition`: `Baik`, `Rusak Ringan`, `Rusak Berat`.
- **Master Data CRUD**:
  - Pengelolaan data untuk `asset_classifications`, `asset_locations`, dan `asset_items` (tanpa delete endpoint demi integritas relasi).
  - Mendukung Live Search via `?search=` (mencari kode/nama/brand) dan pagination dinamis via `?per_page=`.
- **`AssetCodeService`**:
  - Format kode fisik: `{class_code}/{loc_code}/{seq_num}/{item_code}/{year}` (contoh: `MED/UGD/1/12/2026`).
  - Menggunakan `AssetCode::withTrashed()` agar nomor urut aset yang dihapus tidak pernah dipakai ulang oleh aset baru.
  - Mendukung parameter `$excludeId` untuk mencegah lonjakan sequence saat update data sendiri.

## Authorization
- **Read & Write**: `super-admin`, `koordinator-sarpras`, `petugas-tik`, `petugas-sarpras`.
- **Read-Only**: `manajemen`.

## Routes
```text
GET|POST         /api/v1/asset-classifications
GET|PUT|PATCH    /api/v1/asset-classifications/{id}

GET|POST         /api/v1/asset-locations
GET|PUT|PATCH    /api/v1/asset-locations/{id}

GET|POST         /api/v1/asset-items
GET|PUT|PATCH    /api/v1/asset-items/{id}
```
