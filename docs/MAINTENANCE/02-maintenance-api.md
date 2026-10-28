# Asset Maintenance Module API Documentation

Dokumentasi ini menjelaskan seluruh spesifikasi kontrak API untuk Modul Pemeliharaan Aset (Asset Maintenance: Jadwal Induk & Eksekusi Log Fisik).

**Catatan Penting Autentikasi:**
Semua endpoint di bawah ini wajib menyertakan header otentikasi Sanctum:
- `Authorization: Bearer <token>`
- `Accept: application/json`

---

## Aturan Otorisasi (Domain Isolation & RBAC)

1. **Jadwal Induk (Create / Update / Delete)**:
   - `super-admin`: Khusus mengelola jadwal untuk aset **TIK** (Klasifikasi `Alat IT`) dan wajib menugaskan petugas dengan role `petugas-tik`.
   - `koordinator-sarpras`: Khusus mengelola jadwal untuk aset **Sarpras** (Non-TIK) dan wajib menugaskan petugas dengan role `petugas-sarpras`.
2. **Submit Log Eksekusi**:
   - Hanya user (petugas) yang ID-nya terdaftar pada field `assigned_officer_id` pada jadwal terkait yang berhak mengirimkan log eksekusi fisik.
3. **Read-Only / Monitoring**:
   - `manajemen`, `super-admin`, dan `koordinator-sarpras` dapat melihat daftar serta riwayat log pemeliharaan aset.

---

# DAFTAR ENDPOINT API

## 1. Get All Asset Maintenances (Daftar Jadwal Induk)
Mengambil daftar master jadwal pemeliharaan aset berulang (recurring) dengan dukungan pencarian multi-kriteria, filter rentang tanggal, status, keaktifan, dan pagination.

- **Method**: `GET`
- **Endpoint**: `/api/v1/inventory/asset-maintenances`
  *(Catatan: Endpoint juga dapat diakses melalui rute `/api/v1/asset-maintenances`)*
- **Query Parameters**:
  | Parameter | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `search` | string | Tidak | Kata kunci pencarian pada nomor register aset (`full_code`), nama barang (`asset_items.name`), nama petugas (`users.name`), atau jenis pemeliharaan (`maintenance_type`). |
  | `status` | string | Tidak | Filter status jadwal: `terjadwal`, `selesai`, atau `terlambat`. |
  | `date_from` | string (date) | Tidak | Filter tanggal mulai jadwal (`next_scheduled_date >= date_from`, format: `YYYY-MM-DD`). |
  | `date_to` | string (date) | Tidak | Filter batas akhir tanggal jadwal (`next_scheduled_date <= date_to`, format: `YYYY-MM-DD`). |
  | `is_active` | boolean | Tidak | Filter status keaktifan jadwal (`true` / `false` atau `1` / `0`). |
  | `per_page` | integer | Tidak | Jumlah item per halaman (1–100, default: 15). |
  | `page` | integer | Tidak | Nomor halaman paginasi (default: 1). |

### Contoh Response Sukses (200 OK)
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "asset_code_id": 10,
      "assigned_officer_id": 5,
      "creator_id": 1,
      "maintenance_type": "Pembersihan Rutin dan Penggantian Pasta Pendingin",
      "period": "monthly",
      "next_scheduled_date": "2026-11-01",
      "status": "terjadwal",
      "is_active": true,
      "created_at": "2026-10-28T09:00:00.000000Z",
      "updated_at": "2026-10-28T09:00:00.000000Z",
      "asset_code": {
        "id": 10,
        "full_code": "1/2/1/1/2026",
        "asset_item": {
          "id": 1,
          "name": "PC Desktop Dell OptiPlex"
        },
        "classification": {
          "id": 1,
          "name": "Alat IT"
        },
        "location": {
          "id": 2,
          "name": "Ruang IT Server"
        }
      },
      "assigned_officer": {
        "id": 5,
        "name": "Budi Petugas TIK",
        "email": "budi.tik@rsch.co.id"
      },
      "creator": {
        "id": 1,
        "name": "Super Administrator",
        "email": "superadmin@rsch.co.id"
      }
    }
  ],
  "first_page_url": "http://localhost:8000/api/v1/inventory/asset-maintenances?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://localhost:8000/api/v1/inventory/asset-maintenances?page=1",
  "links": [
    {
      "url": null,
      "label": "&laquo; Previous",
      "active": false
    },
    {
      "url": "http://localhost:8000/api/v1/inventory/asset-maintenances?page=1",
      "label": "1",
      "active": true
    },
    {
      "url": null,
      "label": "Next &raquo;",
      "active": false
    }
  ],
  "next_page_url": null,
  "path": "http://localhost:8000/api/v1/inventory/asset-maintenances",
  "per_page": 15,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

---

## 2. Create Asset Maintenance Schedule (Buat Jadwal Baru)
Mendaftarkan aturan jadwal pemeliharaan baru. Pembuat jadwal otomatis dicatat sebagai `creator_id`. Backend melakukan validasi domain isolation klasifikasi aset terhadap role pembuat dan role petugas yang ditugaskan.

- **Method**: `POST`
- **Endpoint**: `/api/v1/inventory/asset-maintenances`
- **Headers**: `Content-Type: application/json`
- **Request Body**:
  | Field | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `asset_code_id` | integer | Ya | ID kode fisik aset yang valid (`asset_codes.id`). |
  | `assigned_officer_id` | integer | Ya | ID user petugas yang ditugaskan (`users.id`). |
  | `maintenance_type` | string | Ya | Uraian jenis pemeliharaan (max: 255 karakter). |
  | `period` | string | Ya | Pilihan periode: `weekly`, `monthly`, `quarterly`, `semiannually`, `yearly`. |
  | `next_scheduled_date` | string (date) | Ya | Tanggal rencana pemeliharaan pertama (format: `YYYY-MM-DD`, minimal hari ini / `after_or_equal:today`). |

### Contoh Request Body
```json
{
  "asset_code_id": 10,
  "assigned_officer_id": 5,
  "maintenance_type": "Pembersihan Rutin dan Penggantian Pasta Pendingin",
  "period": "monthly",
  "next_scheduled_date": "2026-11-01"
}
```

### Contoh Response Sukses (201 Created)
```json
{
  "data": {
    "id": 1,
    "asset_code_id": 10,
    "assigned_officer_id": 5,
    "creator_id": 1,
    "maintenance_type": "Pembersihan Rutin dan Penggantian Pasta Pendingin",
    "period": "monthly",
    "next_scheduled_date": "2026-11-01",
    "status": "terjadwal",
    "is_active": true,
    "created_at": "2026-10-28T09:00:00.000000Z",
    "updated_at": "2026-10-28T09:00:00.000000Z",
    "asset_code": {
      "id": 10,
      "full_code": "1/2/1/1/2026",
      "asset_item": {
        "id": 1,
        "name": "PC Desktop Dell OptiPlex"
      },
      "classification": {
        "id": 1,
        "name": "Alat IT"
      },
      "location": {
        "id": 2,
        "name": "Ruang IT Server"
      }
    },
    "assigned_officer": {
      "id": 5,
      "name": "Budi Petugas TIK",
      "email": "budi.tik@rsch.co.id"
    },
    "creator": {
      "id": 1,
      "name": "Super Administrator",
      "email": "superadmin@rsch.co.id"
    }
  },
  "message": "Jadwal pemeliharaan aset berhasil dibuat."
}
```

---

## 3. Get Asset Maintenance Schedule Detail (Detail Jadwal)
Mengambil detail satu jadwal pemeliharaan aset beserta relasi lengkap (informasi aset, klasifikasi, lokasi, petugas, pembuat) dan riwayat log eksekusi yang pernah dikerjakan (`logs` terurut dari yang paling baru).

- **Method**: `GET`
- **Endpoint**: `/api/v1/inventory/asset-maintenances/{id}`
- **Path Parameters**:
  | Parameter | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `id` | integer | Ya | ID unik master jadwal pemeliharaan (`asset_maintenances.id`). |

### Contoh Response Sukses (200 OK)
```json
{
  "data": {
    "id": 1,
    "asset_code_id": 10,
    "assigned_officer_id": 5,
    "creator_id": 1,
    "maintenance_type": "Pembersihan Rutin dan Penggantian Pasta Pendingin",
    "period": "monthly",
    "next_scheduled_date": "2026-12-01",
    "status": "terjadwal",
    "is_active": true,
    "created_at": "2026-10-28T09:00:00.000000Z",
    "updated_at": "2026-10-28T10:30:00.000000Z",
    "asset_code": {
      "id": 10,
      "full_code": "1/2/1/1/2026",
      "asset_item": {
        "id": 1,
        "name": "PC Desktop Dell OptiPlex"
      },
      "classification": {
        "id": 1,
        "name": "Alat IT"
      },
      "location": {
        "id": 2,
        "name": "Ruang IT Server"
      }
    },
    "assigned_officer": {
      "id": 5,
      "name": "Budi Petugas TIK",
      "email": "budi.tik@rsch.co.id"
    },
    "creator": {
      "id": 1,
      "name": "Super Administrator",
      "email": "superadmin@rsch.co.id"
    },
    "logs": [
      {
        "id": 1,
        "asset_maintenance_id": 1,
        "scheduled_date": "2026-11-01",
        "work_description": "Pembersihan kipas prosesor dan casing dari debu tebal.",
        "maintenance_result": "Suhu CPU stabil di 42 derajat Celcius.",
        "post_maintenance_condition": "Baik",
        "started_at": "2026-10-28 09:00:00",
        "completed_at": "2026-10-28 10:15:00",
        "proof_photo_object_key": "maintenance_photos/abc123xyz.jpg",
        "proof_photo_original_name": "foto_pemeliharaan.jpg",
        "proof_photo_mime_type": "image/jpeg",
        "proof_photo_size": 245760,
        "created_at": "2026-10-28T10:15:00.000000Z",
        "updated_at": "2026-10-28T10:15:00.000000Z"
      }
    ]
  }
}
```

---

## 4. Update Asset Maintenance Schedule (Perbarui Jadwal)
Memperbarui konfigurasi jadwal pemeliharaan. Seluruh field bersifat opsional (partial update). Jika `assigned_officer_id` diubah, backend akan memvalidasi kesesuaian role petugas baru terhadap klasifikasi aset yang bersangkutan.

- **Method**: `PUT` / `PATCH`
- **Endpoint**: `/api/v1/inventory/asset-maintenances/{id}`
- **Headers**: `Content-Type: application/json`
- **Request Body (Partial Update)**:
  | Field | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `maintenance_type` | string | Tidak | Jenis pemeliharaan (max: 255 karakter). |
  | `period` | string | Tidak | Periode: `weekly`, `monthly`, `quarterly`, `semiannually`, `yearly`. |
  | `next_scheduled_date` | string (date) | Tidak | Tanggal jadwal berikutnya (`YYYY-MM-DD`). |
  | `assigned_officer_id` | integer | Tidak | ID user petugas baru yang ditugaskan. |
  | `is_active` | boolean | Tidak | Status keaktifan jadwal (`true` / `false`). |

### Contoh Request Body
```json
{
  "maintenance_type": "Pembersihan Rutin, Kalibrasi dan Penggantian Pasta Thermal",
  "period": "quarterly",
  "is_active": true
}
```

### Contoh Response Sukses (200 OK)
```json
{
  "data": {
    "id": 1,
    "asset_code_id": 10,
    "assigned_officer_id": 5,
    "creator_id": 1,
    "maintenance_type": "Pembersihan Rutin, Kalibrasi dan Penggantian Pasta Thermal",
    "period": "quarterly",
    "next_scheduled_date": "2026-12-01",
    "status": "terjadwal",
    "is_active": true,
    "created_at": "2026-10-28T09:00:00.000000Z",
    "updated_at": "2026-10-28T11:00:00.000000Z"
  },
  "message": "Jadwal pemeliharaan aset berhasil diperbarui."
}
```

---

## 5. Delete Asset Maintenance Schedule (Hapus Jadwal)
Menghapus jadwal pemeliharaan aset secara permanen/soft delete dari database.

- **Method**: `DELETE`
- **Endpoint**: `/api/v1/inventory/asset-maintenances/{id}`

### Contoh Response Sukses (200 OK)
```json
{
  "message": "Jadwal pemeliharaan aset berhasil dihapus."
}
```

---

## 6. Submit Maintenance Execution Log (Kirim Log Eksekusi Fisik)
Petugas yang ditugaskan mengirimkan bukti dan catatan hasil pemeliharaan yang telah diselesaikan di lapangan.

> [!IMPORTANT]
> **Wajib menggunakan format `multipart/form-data`**: Endpoint ini mendukung pengunggahan file foto bukti fisik (`photo`). Jika menggunakan JSON (`application/json`), form upload foto tidak dapat diproses.

**Mekanisme Otomatis oleh Backend:**
1. Memvalidasi bahwa user login (`auth()->id()`) sama dengan `assigned_officer_id` pada jadwal. Jika berbeda, sistem melempar error `403 Forbidden`.
2. Menyimpan file gambar bukti ke disk publik di direktori `maintenance_photos/`.
3. Mengunci transaksi database (`DB::transaction`):
   - Membuat rekaman baru di tabel `asset_maintenance_logs` dengan `scheduled_date` berisi tanggal jadwal yang baru saja dieksekusi.
   - Menghitung tanggal jadwal berikutnya (`next_scheduled_date`) via `AssetMaintenanceService::calculateNextScheduledDate()` secara akurat dari tanggal jadwal lama guna mencegah pergeseran jadwal (*schedule drift*).
   - Mengembalikan status master jadwal menjadi `terjadwal`.

- **Method**: `POST`
- **Endpoint**: `/api/v1/inventory/asset-maintenances/{id}/logs`
- **Headers**:
  - `Content-Type: multipart/form-data`
- **Request Body (Form-Data)**:
  | Field | Tipe | Wajib | Keterangan |
  |---|---|:---:|---|
  | `work_description` | string | Ya | Uraian lengkap pekerjaan yang telah dilaksanakan. |
  | `maintenance_result` | string | Ya | Hasil pekerjaan / temuan kondisi perangkat. |
  | `post_maintenance_condition` | string | Ya | Kondisi fisik pasca-pemeliharaan: `Baik`, `Rusak Ringan`, atau `Rusak Berat`. |
  | `started_at` | string (datetime) | Ya | Waktu mulai pengerjaan fisik (format: `YYYY-MM-DD HH:mm:ss`, contoh: `2026-10-28 09:00:00`). |
  | `completed_at` | string (datetime) | Ya | Waktu selesai pengerjaan fisik (format: `YYYY-MM-DD HH:mm:ss`, harus setelah atau sama dengan `started_at`). |
  | `photo` | file (image) | Tidak | Berkas foto bukti pemeliharaan fisik (ekstensi: JPEG, PNG, JPG, GIF; ukuran maksimal 5120 KB / 5 MB). |

### Contoh Request Form Data
```text
work_description: Pembersihan menyeluruh heatsink prosesor dan exhaust fan.
maintenance_result: Suhu prosesor turun 15°C dan kipas bekerja tanpa suara bising.
post_maintenance_condition: Baik
started_at: 2026-10-28 09:00:00
completed_at: 2026-10-28 10:15:00
photo: [Binary File Image: fan_cleaned.jpg]
```

### Contoh Response Sukses (201 Created)
```json
{
  "data": {
    "id": 1,
    "asset_maintenance_id": 1,
    "scheduled_date": "2026-11-01",
    "work_description": "Pembersihan menyeluruh heatsink prosesor dan exhaust fan.",
    "maintenance_result": "Suhu prosesor turun 15°C dan kipas bekerja tanpa suara bising.",
    "post_maintenance_condition": "Baik",
    "started_at": "2026-10-28 09:00:00",
    "completed_at": "2026-10-28 10:15:00",
    "proof_photo_object_key": "maintenance_photos/aB1c2D3e4F5g6H7i8J9k0L1m2N.jpg",
    "proof_photo_original_name": "fan_cleaned.jpg",
    "proof_photo_mime_type": "image/jpeg",
    "proof_photo_size": 312450,
    "created_at": "2026-10-28T10:15:00.000000Z",
    "updated_at": "2026-10-28T10:15:00.000000Z"
  },
  "message": "Log pemeliharaan berhasil dicatat dan jadwal berikutnya telah diperbarui."
}
```
