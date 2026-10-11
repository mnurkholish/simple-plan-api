# Master Data User API Documentation

Dokumentasi ini menjelaskan endpoint untuk fitur Master Data User. Respons dari API ini menggunakan `UserResource` untuk menjaga konsistensi format JSON.

**Catatan Penting Autentikasi:**
Semua endpoint di bawah ini wajib menyertakan header berikut:
- `Authorization: Bearer <token>`
- `Accept: application/json`

---

## 1. Get All Users

Mengambil daftar seluruh pengguna (users) dengan dukungan pagination dan pencarian.

- **Method**: `GET`
- **Endpoint**: `/api/v1/users`
- **Headers**:
  - `Authorization`: `Bearer <token>`
  - `Accept`: `application/json`
- **Query Parameters**:
  - `page` (optional): Halaman pagination (default: 1).
  - `filter[search]` (optional): Keyword pencarian berdasarkan nama atau NIP pengguna.
  - `filter[unit_id]` (optional): ID Unit untuk menyaring pengguna berdasarkan unit kerjanya.

### Contoh Response Sukses
```json
{
  "success": true,
  "message": "Berhasil mengambil daftar user.",
  "data": [
    {
      "id": 1,
      "nama": "Dr. Rani",
      "unit_user": "ICU",
      "jabatan": "Dokter Kandungan",
      "role": "super admin",
      "status_user": "Aktif"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### Contoh Response Error (Alternative Flow - Filter Kosong)
```json
{
  "success": true,
  "message": "User tidak ditemukan",
  "data": [],
  "links": { ... },
  "meta": { ... }
}
```

---

## 2. Get Detail User

Mengambil detail informasi dari satu pengguna berdasarkan ID.

- **Method**: `GET`
- **Endpoint**: `/api/v1/users/{id}`
- **Headers**:
  - `Authorization`: `Bearer <token>`
  - `Accept`: `application/json`

### Contoh Response Sukses
```json
{
  "success": true,
  "message": "Berhasil mengambil detail user.",
  "data": {
    "id": 1,
    "nama": "Dr. Rani",
    "nip": "19801234567754",
    "unit_user": "ICU",
    "jabatan": "Dokter Kandungan",
    "role": "super admin",
    "no_hp": "081234567098",
    "status_user": "Aktif",
    "alasan_nonaktif": null,
    "riwayat_status_akun": []
  }
}
```

---

## 3. Create User

Menambahkan pengguna baru ke dalam sistem. Email akan otomatis dihasilkan berdasarkan pola `{nip}@simpleplan.local`.

- **Method**: `POST`
- **Endpoint**: `/api/v1/users`
- **Headers**:
  - `Authorization`: `Bearer <token>`
  - `Accept`: `application/json`
  - `Content-Type`: `application/json`

### Request Body

| Field      | Tipe Data | Keterangan                                       |
| ---------- | --------- | ------------------------------------------------ |
| `nama`     | String    | **Mandatory**. Maksimal 255 karakter.            |
| `nip`      | String    | **Mandatory**. Maksimal 255 karakter, harus unik.|
| `password` | String    | **Mandatory**. Minimal 8 karakter.               |
| `unit_id`  | Integer   | **Mandatory**. ID unit yang valid di sistem.     |
| `jabatan`  | String    | **Mandatory**. Maksimal 255 karakter.            |
| `no_hp`    | String    | **Mandatory**. Maksimal 20 karakter, harus unik. |
| `role`     | String    | **Mandatory**. Enum: 'super admin', 'koordinator-sarpras', 'petugas-tik', 'petugas-sarpras', 'user', 'management' |

### Contoh Response Sukses
```json
{
  "success": true,
  "message": "Berhasil menambahkan user baru.",
  "data": {
    "id": 2,
    "nama": "Dr. Rani",
    "nip": "19801234567754",
    "unit_user": "ICU",
    "jabatan": "Dokter Kandungan",
    "role": "user",
    "no_hp": "081234567098",
    "status_user": "Aktif",
    "alasan_nonaktif": null,
    "riwayat_status_akun": []
  }
}
```

### Contoh Response Error (Alternative Flow - Validasi Form & Unik)
```json
{
  "message": "Form wajib diisi",
  "errors": {
    "password": [
      "Form wajib diisi"
    ],
    "nip": [
      "NIP sudah terdaftar di sistem."
    ]
  }
}
```

---

## 4. Update User

Memperbarui data pengguna yang sudah ada.

- **Method**: `PUT` atau `PATCH`
- **Endpoint**: `/api/v1/users/{id}`
- **Headers**:
  - `Authorization`: `Bearer <token>`
  - `Accept`: `application/json`
  - `Content-Type`: `application/json`

### Request Body

| Field      | Tipe Data | Keterangan                                                              |
| ---------- | --------- | ----------------------------------------------------------------------- |
| `nama`     | String    | Opsional (jika dikirim, **Mandatory** & maks 255 karakter).             |
| `nip`      | String    | Opsional (jika dikirim, **Mandatory**, harus unik selain ID ini).       |
| `password` | String    | Opsional. Jika ingin diubah, minimal 8 karakter.                        |
| `unit_id`  | Integer   | Opsional (jika dikirim, **Mandatory** & harus valid).                   |
| `jabatan`  | String    | Opsional (jika dikirim, **Mandatory** & maks 255 karakter).             |
| `no_hp`    | String    | Opsional (jika dikirim, **Mandatory**, harus unik selain ID ini).       |
| `role`     | String    | Opsional. Enum: 'super admin', 'koordinator-sarpras', 'petugas-tik', 'petugas-sarpras', 'user', 'management' |

### Contoh Response Sukses
```json
{
  "success": true,
  "message": "Berhasil mengubah data user.",
  "data": {
    "id": 2,
    "nama": "Dr. Rani (Revisi)",
    "nip": "19801234567754",
    "unit_user": "ICU",
    "jabatan": "Dokter Kandungan",
    "role": "user",
    "no_hp": "081234567098",
    "status_user": "Aktif",
    "alasan_nonaktif": null,
    "riwayat_status_akun": []
  }
}
```

### Contoh Response Error (Alternative Flow - Format Data)
```json
{
  "message": "Format data tidak sesuai. Silahkan periksa kembali data yang dimasukkan",
  "errors": {
    "no_hp": [
      "Format data tidak sesuai. Silahkan periksa kembali data yang dimasukkan"
    ]
  }
}
```

---

## 5. Toggle Status User

Menonaktifkan atau mengaktifkan kembali pengguna.

- **Method**: `PATCH`
- **Endpoint**: `/api/v1/users/{id}/status`
- **Headers**:
  - `Authorization`: `Bearer <token>`
  - `Accept`: `application/json`
  - `Content-Type`: `application/json`

### Request Body

| Field             | Tipe Data | Keterangan                                                              |
| ----------------- | --------- | ----------------------------------------------------------------------- |
| `status_user`     | String    | **Mandatory**. Nilai harus `Aktif` atau `Nonaktif`.                     |
| `alasan_nonaktif` | String    | **Mandatory** HANYA JIKA `status_user` bernilai `Nonaktif`. Pilihan Enum: 'Resign', 'Cuti Panjang', 'Mutasi', 'Lainnya'. |

### Contoh Response Sukses (Menonaktifkan)
```json
{
  "success": true,
  "message": "User berhasil dinonaktifkan",
  "data": {
    "id": 2,
    "nama": "Dr. Rani",
    "nip": "19801234567754",
    "unit_user": "ICU",
    "jabatan": "Dokter Kandungan",
    "role": "user",
    "no_hp": "081234567098",
    "status_user": "Nonaktif",
    "alasan_nonaktif": "Cuti Panjang",
    "riwayat_status_akun": [
      {
        "status": "Nonaktif",
        "alasan": "Cuti Panjang",
        "tanggal": "2026-09-18 14:00:00"
      }
    ]
  }
}
```

### Contoh Response Error Validasi (Menonaktifkan Tanpa Alasan / Format Salah)
```json
{
  "message": "Format data tidak sesuai. Silahkan periksa kembali data yang dimasukkan",
  "errors": {
    "alasan_nonaktif": [
      "Format data tidak sesuai. Silahkan periksa kembali data yang dimasukkan"
    ]
  }
}
```
