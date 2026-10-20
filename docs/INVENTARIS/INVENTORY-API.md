# Inventory Module API Documentation

Dokumentasi ini menjelaskan seluruh spesifikasi kontrak API untuk Modul Inventaris (Master Data & Manajemen Aset).

**Catatan Penting Autentikasi:**
Semua endpoint di bawah ini wajib menyertakan header berikut:
- `Authorization: Bearer <token>`
- `Accept: application/json`

---

# BAGIAN 1: MASTER DATA API

## 1. Get All Asset Classifications
Mengambil daftar klasifikasi aset dengan dukungan pencarian dan pagination.

- **Method**: `GET`
- **Endpoint**: `/api/v1/asset-classifications`
- **Query Parameters**:
  - `search` *(optional, string)*: Keyword pencarian berdasarkan kode atau nama klasifikasi.
  - `per_page` *(optional, integer)*: Jumlah item per halaman (1–100, default: 15).
  - `page` *(optional, integer)*: Nomor halaman (default: 1).

### Contoh Response Sukses (200 OK)
```json
{
  "data": [
    {
      "id": 1,
      "code": 5,
      "name": "Peralatan Medis",
      "created_at": "2026-10-08T10:00:00.000000Z",
      "updated_at": "2026-10-08T10:00:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/asset-classifications?page=1",
    "last": "http://localhost:8000/api/v1/asset-classifications?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

---

## 2. Create Asset Classification
Menambahkan klasifikasi aset baru ke dalam sistem.

- **Method**: `POST`
- **Endpoint**: `/api/v1/asset-classifications`
- **Headers**: `Content-Type: application/json`
- **Request Body**:
  | Field | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `name` | string | Ya | Nama klasifikasi aset. |

### Contoh Request Body
```json
{
  "name": "Peralatan Non-Medis"
}
```

### Contoh Response Sukses (201 Created)
```json
{
  "data": {
    "id": 2,
    "code": 6,
    "name": "Peralatan Non-Medis",
    "created_at": "2026-10-12T01:00:00.000000Z",
    "updated_at": "2026-10-12T01:00:00.000000Z"
  },
  "message": "Asset classification created successfully."
}
```

---

## 3. Get All Asset Locations
Mengambil daftar lokasi/ruangan penempatan aset.

- **Method**: `GET`
- **Endpoint**: `/api/v1/asset-locations`
- **Query Parameters**:
  - `search` *(optional, string)*: Keyword pencarian berdasarkan kode atau nama lokasi.
  - `per_page` *(optional, integer)*: Jumlah item per halaman.
  - `page` *(optional, integer)*: Nomor halaman.

### Contoh Response Sukses (200 OK)
```json
{
  "data": [
    {
      "id": 1,
      "code": 10,
      "name": "Unit Gawat Darurat",
      "created_at": "2026-10-08T10:00:00.000000Z",
      "updated_at": "2026-10-08T10:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

## 4. Create Asset Location
Menambahkan lokasi/ruangan baru.

- **Method**: `POST`
- **Endpoint**: `/api/v1/asset-locations`
- **Headers**: `Content-Type: application/json`
- **Request Body**:
  | Field | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `name` | string | Ya | Nama lengkap ruangan/lokasi. |

---

## 5. Get All Asset Items
Mengambil daftar jenis barang inventaris.

- **Method**: `GET`
- **Endpoint**: `/api/v1/asset-items`
- **Query Parameters**:
  - `search` *(optional, string)*: Keyword pencarian pada nama atau kode barang.
  - `location_id` *(optional, integer)*: Hanya menampilkan jenis barang yang memiliki aset aktif pada lokasi tersebut.
  - `per_page` *(optional, integer)*: Jumlah per halaman.
  - `page` *(optional, integer)*: Nomor halaman.

### Contoh Response Sukses (200 OK)
```json
{
  "data": [
    {
      "id": 1,
      "code": 12,
      "name": "Kursi Roda Bed Pasien",
      "created_at": "2026-10-08T10:00:00.000000Z",
      "updated_at": "2026-10-08T10:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

## 6. Create Asset Item
Mendaftarkan jenis barang baru ke dalam master data.

Kode barang otomatis di-generate oleh backend berupa integer urut tanpa padding 0 (1, 2, 3, dst) yang di-scope unik per klasifikasi aset. Method update tidak mengizinkan perubahan kode maupun klasifikasi aset master data; nama masih dapat diperbarui.

- **Method**: `POST`
- **Endpoint**: `/api/v1/asset-items`
- **Headers**: `Content-Type: application/json`
- **Request Body**:
  | Field | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `asset_classification_id` | integer | Ya | ID Klasifikasi aset induk. |
  | `name` | string | Ya | Nama jenis barang (unik per klasifikasi). |

### Contoh Request Body
```json
{
  "asset_classification_id": 1,
  "name": "Tensimeter Digital"
}
```

---

# BAGIAN 2: ASSET MANAGEMENT API

## 1. Preview Generate Asset Code
Menghitung dan mempratinjau kode aset fisik sebelum form registrasi disubmit ke database.

- **Method**: `GET`
- **Endpoint**: `/api/v1/assets/generate-number`
- **Query Parameters**:
  | Parameter | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `asset_classification_id` | integer | Ya | ID Klasifikasi aset yang valid. |
  | `asset_location_id` | integer | Ya | ID Lokasi aset yang valid. |
  | `asset_item_id` | integer | Ya | ID Item barang yang valid. |
  | `year` | integer | Ya | Tahun pembelian aset (1900–2100); nama parameter preview dipertahankan. |

### Contoh Response Sukses (200 OK)
```json
{
  "data": {
    "full_code": "5/10/1/12/2026"
  }
}
```

---

## 2. Get All Assets (Live Search & Multi-Filter)
Mengambil daftar aset dengan pencarian komprehensif dan penyaringan multi-kriteria.

- **Method**: `GET`
- **Endpoint**: `/api/v1/assets`
- **Query Parameters**:
  | Parameter | Tipe | Keterangan |
  |---|---|---|
  | `search` | string | Pencarian live pada serial number, full_code, nama item, dan brand. |
  | `status` | string | Filter status (`Digunakan`, `Tidak Digunakan`, `Rusak`, `Dipinjam`). |
  | `condition` | string | Filter kondisi fisik (`Baik`, `Rusak Ringan`, `Rusak Berat`). |
  | `asset_classification_id` | integer | Filter berdasarkan ID klasifikasi. |
  | `asset_location_id` | integer | Filter berdasarkan ID lokasi. |
  | `asset_item_id` | integer | Filter berdasarkan ID jenis item. |
  | `purchase_year` | integer | Filter tahun pengadaan. |
  | `per_page` | integer | Jumlah item per halaman (default: 15). |
  | `page` | integer | Nomor halaman. |

### Contoh Response Sukses (200 OK)
```json
{
  "data": [
    {
      "id": 1,
      "asset_code": {
        "id": 1,
        "full_code": "5/10/1/12/2026",
        "sequence_number": 1,
        "classification": {
          "id": 1,
          "code": 5,
          "name": "Peralatan Medis"
        },
        "location": {
          "id": 1,
          "code": 10,
          "name": "Unit Gawat Darurat"
        },
        "item": {
          "id": 1,
          "code": 12,
          "name": "Kursi Roda Bed Pasien"
        }
      },
      "asset_uid": "01K7A5H3YC0M7WJY3PQ8E6KD2V",
      "brand": "Sella",
      "model_specification": "Seri A",
      "location_detail": "Lantai 1",
      "serial_number": "SN-2026-001",
      "purchase_year": 2026,
      "status": "Digunakan",
      "condition": "Baik",
      "warranty_until": "2028-12-31",
      "notes": "Kondisi baru dan siap beroperasi.",
      "photo": {
        "original_name": "wheelchair.jpg",
        "mime_type": "image/jpeg",
        "size": 1048576
      },
      "calibration_document": null,
      "created_at": "2026-10-08T12:00:00.000000Z",
      "updated_at": "2026-10-08T12:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

## 3. Get Detail Asset
Mengambil detail satu aset lengkap dengan relasi kodenya.

- **Method**: `GET`
- **Endpoint**: `/api/v1/assets/{id}`

### Contoh Response Sukses (200 OK)
```json
{
  "data": {
    "id": 1,
    "asset_code": {
      "id": 1,
      "full_code": "5/10/1/12/2026",
      "sequence_number": 1,
      "classification": { "id": 1, "code": 5, "name": "Peralatan Medis" },
      "location": { "id": 1, "code": 10, "name": "Unit Gawat Darurat" },
      "item": { "id": 1, "code": 12, "name": "Kursi Roda Bed Pasien" }
    },
    "asset_uid": "01K7A5H3YC0M7WJY3PQ8E6KD2V",
    "brand": "Sella",
    "model_specification": "Seri A",
    "location_detail": "Lantai 1",
    "serial_number": "SN-2026-001",
    "purchase_year": 2026,
    "status": "Digunakan",
    "condition": "Baik",
    "warranty_until": "2028-12-31",
    "notes": "Kondisi baru",
    "calibration_document": { ... },
    "created_at": "2026-10-08T12:00:00.000000Z",
    "updated_at": "2026-10-08T12:00:00.000000Z"
  }
}
```

---

## 4. Create Asset
Mendaftarkan aset baru dan mengunggah berkas foto/dokumen kalibrasi.

- **Method**: `POST`
- **Endpoint**: `/api/v1/assets`
- **Role yang diizinkan**: `super-admin`, `koordinator-sarpras`
- **Headers**: `Content-Type: multipart/form-data`
- **Form Data Fields**:
  | Field | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `asset_classification_id` | integer | Ya | ID Klasifikasi aset. |
  | `asset_location_id` | integer | Ya | ID Lokasi aset. |
  | `asset_item_id` | integer | Ya | ID Master item barang. |
  | `purchase_year` | integer | Ya | Tahun pembelian (1900–2100). |
  | `serial_number` | string | Tidak | Nomor seri pabrik. |
  | `brand` | string | Tidak | Merek aset fisik, maks 100 karakter. |
  | `model_specification` | string | Tidak | Model/spesifikasi, maks 100 karakter. |
  | `location_detail` | string | Tidak | Detail lokasi dalam ruangan, maks 100 karakter. |
  | `status` | string | Ya | `Digunakan`, `Tidak Digunakan`, `Rusak`, atau `Dipinjam`. |
  | `condition` | string | Ya | `Baik`, `Rusak Ringan`, atau `Rusak Berat`. |
  | `warranty_until` | date | Tidak | Tanggal kadaluarsa garansi (`YYYY-MM-DD`). |
  | `notes` | string | Tidak | Catatan operasional aset. |
  | `calibration_document` | file | Tidak | Dokumen PDF sertifikat kalibrasi (PDF, max 5 MB). |

### Contoh Response Sukses (201 Created)
```json
{
  "data": {
    "id": 3,
    "asset_code": {
      "id": 3,
      "full_code": "5/10/2/12/2026",
      "sequence_number": 2
    },
    "serial_number": "SN-2026-002",
    "purchase_year": 2026,
    "status": "Digunakan",
    "condition": "Baik"
  },
  "message": "Asset created successfully."
}
```

---

## 5. Update Asset (Partial Update & Smart Code Recalculation)
Memperbarui data aset secara parsial. Jika lokasi ruangan, klasifikasi, jenis item, atau tahun pembelian diubah, sistem memperbarui record kode yang sama dan mencatat kode lama serta baru dalam satu riwayat update. Perubahan `location_detail` saja tidak mengubah kode.

`asset_uid` dibuat otomatis dan tidak diterima sebagai field update. Pratinjau nomor tidak mereservasi nomor; nomor final ditentukan saat transaksi penyimpanan.

- **Method**: `PUT` atau `POST` (dengan field `_method=PUT` saat menggunakan `multipart/form-data`)
- **Endpoint**: `/api/v1/assets/{id}`
- **Headers**: `Content-Type: multipart/form-data` atau `application/json`
- **Rules**: Semua field bersifat opsional (*sometimes*).

### Contoh Request Form Data (Pindah Lokasi ke Poli 1)
```text
_method: PUT
asset_location_id: 2
status: Digunakan
```

### Contoh Response Sukses (200 OK)
```json
{
  "data": {
    "id": 1,
    "asset_code": {
      "id": 1,
      "full_code": "5/11/1/12/2026",
      "sequence_number": 1
    },
    "serial_number": "SN-2026-001",
    "purchase_year": 2026,
    "status": "Digunakan",
    "condition": "Baik"
  },
  "message": "Asset updated successfully."
}
```

---

## 6. Delete Asset (Safe Soft Delete)
Melakukan soft delete pada aset. Record kode inventaris tetap tersimpan dan tidak diterbitkan ulang. Relasi tiket Helpdesk tetap memakai `assets.id`.

- **Method**: `DELETE`
- **Endpoint**: `/api/v1/assets/{id}`

### Contoh Response Sukses (200 OK)
```json
{
  "message": "Asset deleted successfully."
}
```

---

## 7. Search Assets for Ticket Selection
Endpoint pencarian aset berdasarkan lokasi ruangan untuk pemilihan barang pada tiket. Unit pelapor tidak menentukan posisi aset.

- **Method**: `GET`
- **Endpoint**: `/api/v1/assets/search`
- **Query Parameters**:
  - `location_id` *(required, integer)*: ID lokasi ruangan dari master `asset_locations`.
  - `search` *(optional, string)*: Keyword nama barang atau nomor inventaris.

### Contoh Response Sukses (200 OK)
```json
{
  "data": [
    {
      "id": 1,
      "asset_uid": "01K7KTCXRH1WKCD26R2YQA5P1M",
      "asset_code": {
        "id": 1,
        "full_code": "5/10/1/12/2026",
        "location": { "id": 10, "code": 10, "name": "Ruang IT" },
        "item": { "id": 12, "code": 12, "name": "Komputer" }
      },
      "serial_number": "SN-2026-001",
      "status": "Digunakan",
      "condition": "Baik"
    }
  ]
}
```

---

# BAGIAN 3: COMMON ERROR RESPONSES

### A. 401 Unauthorized
Terjadi jika token Bearer hilang, kedaluwarsa, atau tidak valid.
```json
{
  "message": "Unauthenticated."
}
```

### B. 403 Forbidden
Terjadi jika role pengguna tidak memiliki izin (misal: akun role `manajemen` mencoba menambahkan aset).
```json
{
  "message": "This action is unauthorized."
}
```

### C. 404 Not Found
Terjadi jika ID aset atau ID master data tidak ditemukan dalam database.
```json
{
  "message": "Resource not found."
}
```

### D. 422 Unprocessable Entity
Terjadi saat validasi form request gagal.
```json
{
  "message": "The status field is required.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ],
    "asset_location_id": [
      "The selected asset location id is invalid."
    ]
  }
}
```
