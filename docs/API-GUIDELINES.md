# SIMPLE-PLAN API Guidelines

Panduan ini hanya membahas aturan REST API SIMPLE-PLAN: URL, versioning, HTTP
method/status, request/response, validation boundary, pagination/filter/search,
workflow action endpoint, file upload contract, dan hubungan dengan OpenAPI.

Requirement dan business flow mengacu pada `docs/SRS.md`.
Kontrak endpoint formal berada di `openapi.yaml`.

Dokumen ini bukan daftar endpoint lengkap. Endpoint final, parameter, request,
response, dan status code mengikuti `openapi.yaml`.

---

## 1. API Principles

API menggunakan:

- REST-style endpoint;
- JSON request/response;
- HTTP status code yang sesuai;
- authentication dan authorization di backend;
- versioning.

Gunakan pola existing project sebelum membuat convention baru.

---

## 2. Base Path and Versioning

Gunakan prefix:

```text
/api/v1
```

Breaking change harus memakai strategi versioning yang disepakati, bukan
mengubah kontrak lama secara diam-diam.

---

## 3. Resource Naming

Gunakan noun/resource name dalam bentuk plural.

Contoh:

```text
/tickets
/assets
/maintenance-schedules
/design-requests
/notifications
```

Hindari endpoint berbasis verb seperti `/getTickets` atau `/createTicket`.

Action endpoint boleh digunakan untuk business operation yang jelas dan tidak
cocok sebagai CRUD biasa.

Contoh:

```text
POST /tickets/{ticket}/verify
POST /tickets/{ticket}/reject
POST /tickets/{ticket}/assign
```

Nama final tetap mengikuti `openapi.yaml`.

---

## 4. HTTP Methods and Status

Gunakan method sesuai tujuan:

```text
GET     membaca data
POST    membuat resource atau menjalankan business action
PUT     mengganti resource penuh jika memang digunakan
PATCH   memperbarui sebagian resource
DELETE  menghapus resource jika diperbolehkan
```

Jangan menggunakan `GET` untuk operasi yang mengubah data.

Status code umum:

- `200 OK` untuk sukses dengan response body;
- `201 Created` untuk resource baru;
- `204 No Content` untuk sukses tanpa response body;
- `400 Bad Request` untuk request invalid non-validation jika diperlukan;
- `401 Unauthorized` untuk unauthenticated;
- `403 Forbidden` untuk authenticated user tanpa izin;
- `404 Not Found` untuk resource tidak ditemukan;
- `409 Conflict` untuk konflik business/workflow state jika sesuai;
- `422 Unprocessable Entity` untuk validation error;
- `500 Internal Server Error` hanya untuk unexpected server error.

Jangan mengembalikan `200` untuk operasi yang gagal.

---

## 5. Request and Response

Request body menggunakan JSON, kecuali upload file yang memakai
`multipart/form-data`.

Gunakan format response yang konsisten dengan implementation project. Jika API
Resource sudah menjadi pola, gunakan untuk representasi data.

Contoh shape umum:

```json
{
  "success": true,
  "message": "Request processed successfully.",
  "data": {}
}
```

Error response harus jelas dan tidak mengekspos internal exception, stack trace,
query, secret, atau path storage internal.

---

## 6. Validation Boundary

Validasi request dilakukan di backend, biasanya melalui FormRequest.

Validasi mencakup field, tipe data, format, allowed values, relasi data, dan
file requirement jika ada.

Validation rule tidak menggantikan business rule. Business rule, workflow rule,
dan state transition diproses di Service layer.

---

## 7. Authentication and Authorization

Endpoint yang membutuhkan login harus dilindungi authentication middleware.

Authorization diterapkan di backend berdasarkan requirement. Saat relevan,
pertimbangkan role, unit, ownership, assigned petugas, dan current workflow
state.

Gunakan `401` untuk unauthenticated dan `403` untuk authenticated user yang
tidak memiliki izin.

---

## 8. Filtering, Search, Sorting, Pagination

List endpoint yang berpotensi besar harus mendukung pagination jika diperlukan.

Contoh query parameter:

```text
GET /api/v1/tickets?page=1&per_page=15
GET /api/v1/tickets?status=baru
GET /api/v1/tickets?unit_id=3
GET /api/v1/tickets?date_from=2026-09-01&date_to=2026-09-30
GET /api/v1/tickets?search=printer
GET /api/v1/tickets?sort=created_at&direction=desc
```

Field filter dan sort yang diperbolehkan harus eksplisit. Jangan meneruskan
arbitrary query parameter langsung menjadi database query.

Format final per endpoint mengikuti `openapi.yaml`.

---

## 9. Date and Time

Gunakan format tanggal/waktu konsisten. Untuk API, gunakan ISO 8601 jika
memungkinkan.

```text
2026-09-09T08:30:00+07:00
```

Jangan menggunakan format tanggal yang berubah-ubah antar endpoint.

---

## 10. File Upload Contract

File dikirim melalui `multipart/form-data` jika diperlukan.

Backend harus memvalidasi:

- file presence jika wajib;
- MIME/type;
- ukuran;
- authorization.

File disimpan melalui Laravel Filesystem. Database hanya menyimpan metadata atau
`object_key` sesuai `docs/DATABASE.md`.

Detail storage environment production mengikuti `docs/DEPLOYMENT.md`.

---

## 11. Workflow Action Endpoints

Business workflow seperti Helpdesk, Maintenance, dan Design Request tidak boleh
menerima perubahan status bebas tanpa validasi.

Gunakan action endpoint yang merepresentasikan use case ketika lebih jelas
daripada generic status update.

Alur minimal:

```text
request -> authentication -> authorization -> validation -> service rule -> response
```

Jika current state membuat operation tidak valid, gunakan error response yang
jelas dan status code yang sesuai, biasanya `409` jika contract endpoint
menetapkannya.

---

## 12. OpenAPI Contract

`openapi.yaml` adalah kontrak API formal antara backend dan frontend.

Dokumentasikan di sana untuk endpoint yang sudah disepakati:

- path dan HTTP method;
- authentication/authorization marker jika digunakan;
- parameter;
- request body;
- response body;
- validation/error response;
- HTTP status code.

Jika implementation mengubah API contract, update `openapi.yaml` pada task yang
sama.

Jangan mendokumentasikan endpoint yang belum disepakati atau belum stabil
sebagai kontrak final.
