# SIMPLE-PLAN API Guidelines

Dokumen ini menjadi panduan umum perancangan dan implementasi REST API backend SIMPLE-PLAN.

Requirement dan business flow mengacu pada `docs/SRS.md`.  
Arsitektur backend mengacu pada `docs/BACKEND-ARCHITECTURE.md`.  
Kontrak endpoint yang sudah disepakati didokumentasikan pada `openapi.yaml`.

Dokumen ini tidak mendefinisikan seluruh endpoint. Endpoint dikembangkan bertahap sesuai kebutuhan Sprint dan requirement yang telah disepakati.

---

## 1. General Principles

API SIMPLE-PLAN menggunakan:

- REST-style endpoint;
- JSON untuk request/response;
- HTTP status code yang sesuai;
- authentication dan authorization pada backend;
- API versioning.

Gunakan pola existing project sebelum membuat convention baru.

Hindari endpoint atau response khusus yang tidak diperlukan.

---

## 2. Base Path and Versioning

Gunakan prefix API:

```text
/api/v1
```

Contoh:

```text
GET    /api/v1/tickets
POST   /api/v1/tickets
GET    /api/v1/tickets/{ticket}
PATCH  /api/v1/tickets/{ticket}
```

Perubahan breaking API harus menggunakan strategi versioning yang disepakati, bukan mengubah contract lama secara diam-diam.

---

## 3. Resource Naming

Gunakan noun/resource name dan bentuk plural.

Gunakan:

```text
/tickets
/assets
/maintenance-schedules
/design-requests
/notifications
```

Hindari:

```text
/getTickets
/createTicket
/updateAsset
```

Action endpoint boleh digunakan jika merepresentasikan business operation yang jelas dan tidak cocok sebagai CRUD biasa.

Contoh:

```text
POST /tickets/{ticket}/verify
POST /tickets/{ticket}/reject
POST /tickets/{ticket}/assign
```

Gunakan action endpoint secara konsisten dan hanya jika workflow memang membutuhkan.

---

## 4. HTTP Methods

Gunakan method sesuai tujuan:

```text
GET     membaca data
POST    membuat resource atau menjalankan business action
PUT     mengganti resource secara penuh jika memang digunakan
PATCH   memperbarui sebagian resource
DELETE  menghapus resource jika diperbolehkan
```

Jangan menggunakan `GET` untuk operasi yang mengubah data.

---

## 5. HTTP Status Codes

Gunakan status code yang sesuai.

Umum:

```text
200 OK
201 Created
204 No Content

400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
500 Internal Server Error
```

Pedoman:

- `200` untuk request sukses dengan response body;
- `201` untuk resource berhasil dibuat;
- `204` untuk sukses tanpa response body;
- `401` jika belum terautentikasi;
- `403` jika tidak memiliki izin;
- `404` jika resource tidak ditemukan;
- `409` untuk konflik business state jika sesuai;
- `422` untuk validation error;
- `500` hanya untuk unexpected server error.

Jangan mengembalikan `200` untuk operasi yang sebenarnya gagal.

---

## 6. Response Format

Gunakan format response yang konsisten dengan implementation project.

Contoh sukses:

```json
{
  "success": true,
  "message": "Ticket created successfully.",
  "data": {}
}
```

Contoh error:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": [
      "The field is required."
    ]
  }
}
```

Jangan mengekspos internal exception, stack trace, query, atau informasi sensitif ke client.

Gunakan Laravel API Resource untuk representasi data jika sesuai.

---

## 7. Validation

Validasi request dilakukan di backend.

Gunakan Laravel FormRequest jika sesuai dengan arsitektur project.

Validasi dapat mencakup:

- required field;
- tipe data;
- panjang data;
- format;
- allowed values;
- relasi data;
- file type;
- file size.

Validation rule tidak menggantikan business rule.

Business rule dan state transition tetap diproses di Service layer.

---

## 8. Authentication and Authorization

Endpoint yang membutuhkan login harus dilindungi authentication middleware.

Authorization harus diterapkan di backend berdasarkan requirement.

Saat relevan, pertimbangkan:

- role;
- unit;
- ownership;
- assigned petugas;
- current workflow state.

Frontend tidak boleh menjadi satu-satunya kontrol akses.

Gunakan `401` untuk unauthenticated dan `403` untuk authenticated user yang tidak memiliki izin.

---

## 9. Filtering, Searching, Sorting, and Pagination

List endpoint yang berpotensi memiliki banyak data harus mendukung pagination jika diperlukan.

Contoh:

```text
GET /api/v1/tickets?page=1
```

Filtering dapat menggunakan query parameter:

```text
GET /api/v1/tickets?status=baru
GET /api/v1/tickets?unit_id=3
GET /api/v1/tickets?date_from=2026-09-01&date_to=2026-09-30
```

Search:

```text
GET /api/v1/tickets?search=printer
```

Sorting:

```text
GET /api/v1/tickets?sort=created_at&direction=desc
```

Field filter dan sort yang diperbolehkan harus eksplisit.

Jangan meneruskan arbitrary query parameter langsung menjadi database query.

Format final setiap endpoint mengikuti `openapi.yaml`.

---

## 10. Date and Time

Gunakan format tanggal/waktu yang konsisten.

Untuk API, gunakan ISO 8601 jika memungkinkan.

Contoh:

```text
2026-09-09T08:30:00+07:00
```

Jangan menggunakan format tanggal yang berubah-ubah antar endpoint.

Timezone harus ditangani secara konsisten pada seluruh aplikasi.

---

## 11. File Upload

File dikirim melalui `multipart/form-data` jika diperlukan.

Contoh kebutuhan:

- bukti tiket;
- foto hasil perbaikan;
- dokumentasi maintenance;
- draft/revisi desain.

Backend harus memvalidasi:

- file presence jika wajib;
- MIME/type;
- ukuran;
- authorization.

File disimpan melalui Laravel Filesystem.

Database hanya menyimpan metadata atau `object_key` yang diperlukan.

Detail storage mengikuti `docs/BACKEND-ARCHITECTURE.md` dan `docs/DATABASE.md`.

---

## 12. Workflow Actions

Business workflow seperti Helpdesk, Maintenance, atau Design Request tidak boleh menerima perubahan status bebas tanpa validasi.

Contoh alur:

```text
Request
  ↓
Authentication
  ↓
Authorization
  ↓
Validation
  ↓
Service / Business Rule
  ↓
State Transition
  ↓
Response
```

Gunakan endpoint yang merepresentasikan use case ketika lebih jelas daripada generic status update.

Contoh:

```text
POST /tickets/{ticket}/verify
POST /tickets/{ticket}/reject
POST /tickets/{ticket}/assign
```

Nama dan method final mengikuti API contract.

---

## 13. Errors and Business Conflicts

Expected error harus ditangani secara konsisten.

Contoh:

- validation error;
- unauthenticated;
- unauthorized;
- resource not found;
- invalid workflow transition;
- duplicate/conflicting operation.

Jika business operation tidak valid karena current state, gunakan error response yang jelas dan status code yang sesuai.

Jangan menjadikan expected business error sebagai unhandled `500`.

---

## 14. OpenAPI Contract

`openapi.yaml` menjadi kontrak API formal antara backend dan frontend.

Dokumentasikan endpoint yang sudah disepakati, termasuk:

- path;
- HTTP method;
- authentication;
- parameters;
- request body;
- response body;
- validation/error response;
- HTTP status code.

Jika implementation mengubah API contract, update `openapi.yaml` pada task yang sama.

Jangan mendokumentasikan endpoint yang belum benar-benar disepakati atau diimplementasikan sebagai final.

---

## 15. Compatibility and Flexibility

API dapat berkembang mengikuti Sprint dan perubahan requirement.

Perubahan diperbolehkan jika:

- requirement diperjelas;
- frontend membutuhkan contract yang disepakati;
- workflow berubah;
- ditemukan masalah desain API.

Perubahan harus:

- konsisten dengan SRS;
- tidak mematahkan contract yang sudah digunakan tanpa pembahasan;
- diperbarui pada `openapi.yaml`;
- memiliki test yang relevan.

---

## 16. Summary

```text
SRS / Requirement
      ↓
API Design
      ↓
openapi.yaml
      ↓
Controller
      ↓
Service
      ↓
Repository / Storage
      ↓
JSON Response
      ↓
Frontend
```

Gunakan API yang konsisten, aman, terdokumentasi, dan sederhana. Prioritaskan kejelasan contract antara backend dan frontend tanpa over-engineering.
