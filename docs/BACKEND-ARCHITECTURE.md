# SIMPLE-PLAN Backend Architecture

Dokumen ini menjadi panduan teknis utama untuk struktur backend SIMPLE-PLAN.

Requirement dan business flow mengacu pada `docs/SRS.md`.  
Kontrak API mengacu pada `openapi.yaml`.  
Standar API, database, dan testing mengacu pada dokumentasi terkait di folder `docs/`.

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
  ↓
Repository
  ↓
Eloquent Model
  ↓
MySQL / MariaDB
```

Response dikembalikan melalui API Resource atau response pattern yang sudah digunakan project.

Tujuan utama arsitektur ini adalah menjaga pemisahan antara HTTP handling, business logic, dan data access tanpa menambah abstraction yang tidak diperlukan.

---

## 2. Layer Responsibilities

### Route
Mendefinisikan HTTP method, URI, middleware, dan Controller action.

Jangan menaruh business logic di route.

### Middleware
Menangani concern lintas request seperti authentication dan authorization umum.

Business rule spesifik fitur tetap ditangani di Service.

### FormRequest
Menangani validasi input request.

Gunakan untuk:
- required field;
- format;
- tipe data;
- validasi file;
- nilai input yang diperbolehkan.

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
- koordinasi beberapa Repository;
- transaction;
- proses multi-step.

Contoh:
- `TicketService`
- `AssetService`
- `MaintenanceService`
- `DesignRequestService`

### Repository
Menangani akses dan persistence data.

Gunakan Repository untuk:
- query;
- create/update/delete;
- pencarian data;
- filtering yang berkaitan dengan persistence.

Repository tidak menangani HTTP response, authorization, atau workflow decision.

### Model
Gunakan Eloquent Model untuk entity, relationship, cast, dan scope sederhana.

Hindari meletakkan workflow kompleks di Model.

### API Resource
Gunakan API Resource untuk menjaga struktur JSON tetap konsisten dan mencegah field internal terekspos langsung.

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

Tidak semua folder harus dibuat sejak awal.

Buat hanya jika memang dibutuhkan oleh implementasi.

---

## 4. Dependency Direction

Gunakan dependency satu arah:

```text
Controller
  ↓
Service
  ↓
Repository
  ↓
Model
```

Hindari dependency terbalik seperti:

```text
Model → Controller
Repository → Controller
Repository → FormRequest
```

Ikuti pola existing project sebelum membuat pola baru.

---

## 5. Authentication and Authorization

Authorization harus ditegakkan di backend.

Gunakan mekanisme Laravel yang sesuai dengan pola project, seperti:
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

Frontend tidak boleh menjadi satu-satunya lapisan authorization.

---

## 6. Workflow and State Transition

Helpdesk, Maintenance, dan Graphic Design Request memiliki workflow yang harus dikontrol.

Perubahan status harus dilakukan melalui Service.

Sebelum transition:
1. validasi current state;
2. validasi actor;
3. validasi business rule;
4. simpan perubahan;
5. catat history/audit jika diperlukan.

Jangan mengizinkan perubahan status bebas hanya karena nilai status valid secara format.

Jika aturan transition belum jelas di SRS, jangan mengarang business rule.

---

## 7. Database

Gunakan migration untuk semua perubahan schema.

Gunakan sesuai kebutuhan:
- foreign key;
- index;
- unique constraint;
- transaction;
- Eloquent relationship;
- pagination.

Hindari:
- duplicate data structure;
- raw SQL tanpa kebutuhan;
- N+1 query;
- menyimpan derived value jika tidak diperlukan.

Untuk operasi multi-write yang harus atomic, gunakan `DB::transaction()`.

Detail schema mengikuti `docs/DATABASE.md`.

---

## 8. API

Gunakan REST API dan JSON secara konsisten.

Ikuti:
- `docs/API-GUIDELINES.md`
- `openapi.yaml`

Jika endpoint, request, response, parameter, authentication, atau HTTP status berubah, perbarui `openapi.yaml` pada task yang sama.

Gunakan API versioning sesuai convention project, misalnya:

```text
/api/v1/...
```

---

## 9. File and Storage

Untuk file seperti bukti tiket, hasil perbaikan, dokumentasi maintenance, atau file desain:

- gunakan Laravel Filesystem;
- validasi tipe dan ukuran file;
- batasi akses sesuai authorization;
- simpan path/metadata di database bila sesuai;
- jangan mengekspos internal storage path tanpa kebutuhan.

Storage harus tetap dapat dikonfigurasi sesuai environment RS.

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

Implementation harus mempertimbangkan kemampuan server internal RS.

---

## 11. Performance

Gunakan optimasi dasar terlebih dahulu:

- pagination;
- eager loading;
- index yang relevan;
- query yang efisien;
- hindari N+1 query;
- hindari mengambil data yang tidak diperlukan.

Caching, Redis, queue, atau optimasi kompleks hanya digunakan jika terdapat kebutuhan atau hasil pengukuran yang mendukung.

---

## 12. Testing

Feature/API Test digunakan untuk:
- endpoint;
- authentication;
- authorization;
- validation;
- persistence;
- JSON response;
- workflow.

Unit Test digunakan untuk business logic terisolasi yang cukup kompleks.

Struktur umum:

```text
tests/
├── Feature/
└── Unit/
```

Detail testing mengikuti `docs/TESTING.md`.

---

## 13. Architecture Principles

Gunakan prinsip berikut:

- **Keep it simple** — gunakan solusi paling sederhana yang memenuhi requirement.
- **Separation of concerns** — pisahkan HTTP, business logic, dan data access.
- **Existing pattern first** — ikuti pola existing sebelum membuat abstraction baru.
- **Framework first** — gunakan kemampuan Laravel sebelum menambah package.
- **No speculative feature** — jangan implementasikan kebutuhan yang belum disepakati.
- **Avoid premature abstraction** — jangan membuat interface, helper, base repository, atau generic service tanpa kebutuhan nyata.
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
      ↓
Repository
      ↓
Database
      ↓
Testing
```

Requirement menentukan **apa yang harus dilakukan sistem**.

API Contract menentukan **bagaimana frontend dan backend berkomunikasi**.

Architecture ini menentukan **bagaimana backend mengimplementasikannya** secara konsisten tanpa over-engineering.
