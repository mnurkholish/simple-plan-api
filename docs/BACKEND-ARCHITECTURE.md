# SIMPLE-PLAN Backend Architecture

Dokumen ini menjadi panduan teknis utama untuk struktur backend SIMPLE-PLAN.

Requirement dan business flow mengacu pada `docs/SRS.md`.  
Kontrak API mengacu pada `openapi.yaml`.  
Panduan database, API, testing, dan deployment mengacu pada dokumentasi terkait di folder `docs/`.

---

## 1. Architecture Flow

Gunakan alur utama berikut:

```text
Client
  ↓
Route
  ↓
Middleware
  ↓
FormRequest
  ↓
Controller
  ↓
Service
  ├── Repository → Eloquent → PostgreSQL
  └── Laravel Filesystem → Storage Disk
  ↓
API Resource
  ↓
JSON Response
```

Tujuan arsitektur ini adalah memisahkan HTTP handling, business logic, data access, dan file storage tanpa menambah abstraction yang tidak diperlukan.

---

## 2. Layer Responsibilities

### Route
Mendefinisikan HTTP method, URI, middleware, dan Controller action.

### Middleware
Menangani concern lintas request seperti authentication dan authorization umum.

### FormRequest
Menangani validasi input, termasuk field, format, tipe data, serta file jika ada.

### Controller
Menangani HTTP request dan response.

Controller sebaiknya hanya:
- menerima validated input;
- memanggil Service;
- mengembalikan response.

Hindari business logic dan query kompleks di Controller.

### Service
Menangani business logic dan workflow.

Gunakan Service untuk:
- use case;
- business rule;
- state transition;
- koordinasi Repository;
- database transaction;
- koordinasi file storage bila fitur melibatkan file.

Contoh:
- `TicketService`
- `AssetService`
- `MaintenanceService`
- `DesignRequestService`

### Repository
Menangani akses dan persistence data PostgreSQL.

Repository tidak menangani HTTP response, authorization, atau workflow decision.

### Model
Gunakan Eloquent Model untuk entity, relationship, cast, dan scope sederhana.

### API Resource
Gunakan API Resource atau response pattern project untuk menjaga struktur JSON tetap konsisten.

---

## 3. Suggested Structure

```text
app/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Services/
├── Repositories/
├── Policies/
├── Enums/
├── Jobs/
└── Exceptions/
```

Tidak semua folder harus dibuat sejak awal. Buat hanya jika memang dibutuhkan.

---

## 4. Dependency Direction

Gunakan dependency utama:

```text
Controller
  ↓
Service
  ↓
Repository
  ↓
Model
```

Untuk fitur file:

```text
Service
  ├── Repository
  └── Laravel Filesystem
```

Ikuti pola existing project sebelum membuat abstraction baru.

Jangan membuat `StorageService`, `FileRepository`, atau abstraction tambahan jika penggunaan Laravel Filesystem secara langsung masih sederhana dan jelas.

---

## 5. Authentication and Authorization

Authorization harus ditegakkan di backend.

Gunakan mekanisme Laravel yang sesuai dengan project, seperti:
- middleware;
- Policy;
- Gate.

Saat relevan, pertimbangkan:
- role;
- unit;
- ownership;
- assigned petugas;
- current workflow state.

Detail permission mengikuti SRS.

---

## 6. Workflow and State Transition

Helpdesk, Maintenance, dan Graphic Design Request memiliki workflow yang harus dikontrol.

Perubahan status dilakukan melalui Service.

Sebelum transition:
1. validasi current state;
2. validasi actor;
3. validasi business rule;
4. simpan perubahan;
5. catat history/audit jika diperlukan.

Jika aturan transition belum jelas di SRS, jangan mengarang business rule.

---

## 7. Database

Gunakan PostgreSQL sebagai database utama.

Semua perubahan schema dilakukan melalui Laravel Migration.

Gunakan sesuai kebutuhan:
- foreign key;
- index;
- unique constraint;
- transaction;
- Eloquent relationship;
- pagination.

Hindari N+1 query dan duplicate data structure.

Detail mengikuti `docs/DATABASE-GUIDELINES.md`.

---

## 8. File Storage

Semua operasi file harus melalui Laravel Filesystem.

Jangan mengikat business logic langsung ke local filesystem atau MinIO.

### Development

Gunakan local disk:

```env
FILESYSTEM_DISK=local
```

Contoh:

```php
Storage::putFile('helpdesk/evidence', $file);
```

### Production

Storage dapat dipindahkan ke MinIO menggunakan S3-compatible driver:

```env
FILESYSTEM_DISK=s3
```

Kode aplikasi tetap menggunakan Laravel Filesystem sehingga perpindahan storage tidak memerlukan perubahan besar pada business logic.

PostgreSQL hanya menyimpan metadata atau `object_key` yang dibutuhkan.

Jangan menyimpan:
- absolute local path;
- temporary URL;
- endpoint MinIO;
- credential storage

sebagai data permanen.

Contoh `object_key`:

```text
helpdesk/evidence/{uuid}.jpg
maintenance/{uuid}.pdf
design/drafts/{uuid}.png
```

Default file production bersifat private dan akses tetap mengikuti authentication dan authorization aplikasi.

---

## 9. API

Gunakan REST API dan JSON secara konsisten.

Ikuti:
- `docs/API-GUIDELINES.md`
- `openapi.yaml`

Jika endpoint, request, response, parameter, authentication, atau HTTP status berubah, perbarui `openapi.yaml` pada task yang sama.

---

## 10. Background Processing

Gunakan Job, Queue, atau Scheduler hanya jika memang dibutuhkan.

Contoh kandidat:
- maintenance reminder;
- report generation;
- notification processing;
- backup terjadwal;
- proses file berat.

Jangan membuat proses sederhana menjadi asynchronous tanpa alasan nyata.

---

## 11. Performance

Gunakan optimasi dasar terlebih dahulu:
- pagination;
- eager loading;
- index yang relevan;
- query yang efisien;
- hindari N+1 query;
- hindari mengambil data yang tidak diperlukan.

Caching, Redis, queue, atau optimasi kompleks hanya digunakan jika memang dibutuhkan.

---

## 12. Testing

Feature/API Test digunakan untuk:
- endpoint;
- authentication;
- authorization;
- validation;
- persistence;
- JSON response;
- workflow;
- file upload/access jika relevan.

Untuk test file, gunakan Laravel Storage fake jika sesuai agar automated test tidak bergantung pada MinIO.

Unit Test digunakan untuk business logic terisolasi yang cukup kompleks.

Detail testing mengikuti `docs/TESTING.md`.

---

## 13. Architecture Principles

- **Keep it simple** — gunakan solusi paling sederhana yang memenuhi requirement.
- **Separation of concerns** — pisahkan HTTP, business logic, database, dan file storage.
- **Existing pattern first** — ikuti pola existing sebelum membuat abstraction baru.
- **Framework first** — gunakan kemampuan Laravel sebelum menambah package.
- **No speculative feature** — jangan implementasikan kebutuhan yang belum disepakati.
- **Avoid premature abstraction** — jangan membuat abstraction tambahan tanpa kebutuhan nyata.
- **Testable design** — business logic harus mudah diuji.
- **Flexible architecture** — struktur boleh berkembang jika requirement atau kebutuhan teknis berubah.

---

## 14. Summary

```text
Requirement (SRS)
      ↓
API Contract
      ↓
Controller
      ↓
Service
   ┌──┴──────────────┐
   ↓                 ↓
Repository      Laravel Filesystem
   ↓                 ↓
PostgreSQL     Local / MinIO
      ↓
Testing
```

Pada development, storage dapat menggunakan local disk.

Pada production, storage dapat dipindahkan ke MinIO hanya melalui perubahan konfigurasi dan setup environment selama seluruh kode file tetap menggunakan Laravel Filesystem.
