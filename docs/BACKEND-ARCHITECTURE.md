# SIMPLE-PLAN Backend Architecture

Panduan ini menjelaskan struktur backend SIMPLE-PLAN: alur layer, tanggung
jawab layer, arah dependency, authorization placement, workflow/state handling,
dan storage abstraction.

Requirement dan business flow mengacu pada `docs/SRS.md`.
Kontrak API mengacu pada `openapi.yaml`.

---

## 1. Architecture Flow

Gunakan alur utama:

```text
Route
-> Middleware
-> FormRequest
-> Controller
-> Service
-> Repository
-> Eloquent Model
-> MySQL
```

Untuk fitur file, Service juga berkoordinasi dengan Laravel Filesystem.

Tujuannya: HTTP handling, validation, business logic, data access, dan storage
tetap terpisah tanpa abstraction tambahan yang belum perlu.

---

## 2. Layer Responsibilities

### Route

Mendefinisikan HTTP method, URI, middleware, dan Controller action.

### Middleware

Menangani concern lintas request seperti authentication dan authorization umum.

### FormRequest

Menangani validasi input request, termasuk field, format, tipe data, dan file.
Validation rule tidak menggantikan business rule.

### Controller

Menangani HTTP concern:

- menerima request yang sudah divalidasi;
- memanggil Service;
- mengembalikan API response.

Hindari business logic, workflow decision, dan query kompleks di Controller.

### Service

Menangani use case dan workflow:

- business rule;
- state transition;
- authorization check yang bergantung workflow jika sesuai;
- database transaction;
- koordinasi Repository;
- koordinasi Laravel Filesystem untuk fitur file.

Contoh nama Service mengikuti domain, seperti `TicketService`, `AssetService`,
`MaintenanceService`, atau `DesignRequestService`.

### Repository

Menangani data access dan persistence. Repository tidak mengurus HTTP response,
authorization, atau workflow decision.

### Model

Gunakan Eloquent Model untuk entity, relationship, cast, scope sederhana, dan
konfigurasi model. Workflow kompleks tetap di Service.

### API Resource

Gunakan API Resource atau response pattern project untuk menjaga struktur JSON
konsisten bila pola tersebut sudah digunakan.

---

## 3. Dependency Direction

Arah dependency utama:

```text
Controller -> Service -> Repository -> Model
```

Untuk fitur file:

```text
Service -> Laravel Filesystem
```

Ikuti pola existing project sebelum membuat abstraction baru. Jangan membuat
`StorageService`, `FileRepository`, atau wrapper serupa jika penggunaan Laravel
Filesystem langsung masih sederhana dan jelas.

---

## 4. Suggested Structure

Struktur yang dapat digunakan saat dibutuhkan:

```text
app/
  Http/
    Controllers/Api/V1/
    Requests/
    Resources/
  Models/
  Services/
  Repositories/
  Policies/
  Enums/
  Jobs/
  Exceptions/
```

Tidak semua folder harus ada sejak awal. Tambahkan hanya saat ada kebutuhan
nyata dan konsisten dengan existing project.

---

## 5. Authorization Placement

Authorization wajib ditegakkan di backend.

Gunakan mekanisme Laravel yang sesuai dengan pola project:

- middleware untuk akses umum;
- Policy/Gate untuk resource/action authorization;
- Service untuk rule yang bergantung pada workflow atau state saat action.

Saat relevan, cek role, unit, ownership, assigned petugas, dan current workflow
state. Detail permission tetap mengikuti SRS.

---

## 6. Workflow and State Handling

Helpdesk, Maintenance, dan Graphic Design Request memiliki controlled workflow.

Perubahan status dilakukan melalui Service, bukan generic mass assignment.

Sebelum transition:

1. validasi current state;
2. validasi requested transition/action;
3. validasi actor authorization;
4. validasi data wajib;
5. simpan perubahan dan history/audit jika requirement memerlukan.

Jika aturan transition belum jelas di SRS, jangan mengarang business rule.
Pertahankan implementasi fleksibel sampai requirement final.

---

## 7. Storage Abstraction

Semua operasi file harus melalui Laravel Filesystem.

Development boleh menggunakan local storage. Production dapat menggunakan MinIO
melalui S3-compatible driver. Business logic tidak boleh bergantung pada local
path, endpoint MinIO, credential, atau permanent storage URL.

Service boleh menyimpan atau menghapus file melalui `Storage`, lalu Repository
menyimpan metadata yang diperlukan. Aturan metadata dan `object_key` mengikuti
`docs/DATABASE.md`. Konfigurasi environment production mengikuti
`docs/DEPLOYMENT.md`.

---

## 8. Background Processing

Gunakan Job, Queue, atau Scheduler hanya jika fitur memang membutuhkan proses
asynchronous atau terjadwal.

Kandidat yang mungkin:

- maintenance reminder;
- report generation;
- notification processing;
- backup terjadwal;
- proses file berat.

Jangan membuat proses sederhana menjadi asynchronous tanpa alasan nyata.

---

## 9. Architecture Principles

- Existing pattern first.
- Framework feature before new package.
- Keep workflow logic in Service.
- Keep HTTP concern in Controller/FormRequest/API Resource.
- Keep persistence concern in Repository/Model.
- Keep database rules in `docs/DATABASE.md`.
- Keep API contract in `openapi.yaml`.
- Avoid speculative features and premature abstraction.
