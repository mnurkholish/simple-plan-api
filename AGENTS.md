# SIMPLE-PLAN API Development Guide

SIMPLE-PLAN adalah sistem internal RS Citra Husada untuk Helpdesk,
Inventaris, Maintenance, Graphic Design Request, Notification, Reporting,
Backup, dan fitur terkait.

Repository ini berisi backend/API Laravel.

---

## 1. Source of Truth

Gunakan sumber berikut sesuai urutan:

1. Task/request saat ini.
2. `docs/SRS.md` untuk requirement dan business flow.
3. `openapi.yaml` untuk kontrak API formal.
4. Dokumentasi teknis di `docs/`.
5. Existing code conventions.

Dokumentasi teknis:

- `docs/BACKEND-ARCHITECTURE.md`
- `docs/DATABASE.md`
- `docs/API-GUIDELINES.md`
- `docs/TESTING.md`
- `docs/DEPLOYMENT.md`

Task, sprint, dan progress tracking dikelola di Trello. Jangan membuat
dokumen tracking duplikatif seperti `CURRENT-SPRINT`, `TASKS`, `BACKLOG`,
`STATUS`, atau `ROADMAP` di repository ini.

SRS masih berkembang. Jangan mengarang role permission, status transition,
schema, endpoint, atau business rule yang belum jelas. Jika requirement
ambigu atau bertentangan, laporkan sebelum mengambil keputusan product-level.

---

## 2. Project Stack

- Laravel backend/API.
- REST API + JSON.
- MySQL untuk data terstruktur.
- Laravel Filesystem untuk seluruh operasi file.
- Local storage boleh digunakan saat development.
- MinIO production dapat digunakan melalui S3-compatible driver.
- Pest digunakan untuk testing jika sesuai konfigurasi project.

Sebelum memakai API Laravel/package yang version-specific, cek
`composer.json`, package terpasang, dan dokumentasi Laravel Boost jika berguna.

Jangan menambah, menghapus, atau upgrade dependency kecuali task memang
membutuhkan.

---

## 3. Architecture Direction

Ikuti arah utama:

```text
Route -> Middleware -> FormRequest -> Controller -> Service -> Repository -> Model/MySQL
```

Prinsip layer dan dependency mengikuti `docs/BACKEND-ARCHITECTURE.md`.

Ringkasnya:

- Controller menangani HTTP concern.
- FormRequest menangani validasi input.
- Service menangani business logic, workflow, state transition, transaction,
  dan koordinasi storage bila perlu.
- Repository menangani data access/persistence.
- Model menangani entity, relationship, cast, dan scope sederhana.
- API Resource atau response pattern project digunakan bila sudah menjadi pola.

Gunakan Policy, Gate, atau Middleware untuk authorization sesuai pola project.
Authorization wajib ditegakkan di backend; frontend visibility bukan kontrol
akses.

---

## 4. Scope Control

Sebelum membuat atau mengubah code:

1. baca requirement terkait;
2. baca file existing yang berhubungan;
3. ikuti pola sibling class/module;
4. batasi perubahan pada scope task;
5. hindari top-level directory, abstraction, package, atau feature baru tanpa
   kebutuhan jelas.

Jangan memperkenalkan pola kedua untuk masalah yang sudah diselesaikan secara
konsisten di project.

---

## 5. API, Database, Storage

Untuk endpoint, request, response, parameter, authentication, authorization,
dan status code, ikuti `docs/API-GUIDELINES.md` dan `openapi.yaml`.

Jika implementasi mengubah kontrak API, update `openapi.yaml` pada task yang
sama.

Untuk schema, migration, relationship, constraint, index, transaction, dan file
metadata, ikuti `docs/DATABASE.md`.

Untuk file:

- semua operasi melalui Laravel Filesystem;
- jangan mengikat business logic ke local path atau endpoint MinIO;
- MySQL menyimpan metadata/`object_key`, bukan binary file atau permanent
  storage URL.

---

## 6. Testing and Documentation

Ikuti `docs/TESTING.md`.

Saat perubahan menyentuh behavior penting, tambahkan atau sesuaikan test yang
relevan, terutama untuk:

- endpoint/API behavior;
- validation;
- authentication/authorization;
- workflow/state transition;
- database persistence;
- file upload/access jika relevan;
- bug regression.

Jalankan test paling sempit yang relevan selama development. Untuk perubahan
signifikan, jalankan area test terdampak atau full suite jika memungkinkan.

Update dokumentasi terkait dalam task yang sama jika perubahan mengubah:

- API contract (`openapi.yaml`);
- schema/database rule (`docs/DATABASE.md`);
- architecture convention (`docs/BACKEND-ARCHITECTURE.md`);
- deployment/environment requirement (`docs/DEPLOYMENT.md`).

---

## 7. Security

- Validasi seluruh input client.
- Require authentication untuk endpoint yang membutuhkannya.
- Enforce authorization di backend.
- Jangan expose raw model data jika sudah ada API representation.
- Jangan expose internal exception, stack trace, query, secret, atau path
  storage internal.
- Jangan commit `.env`, password, API key, database credential, object storage
  credential, atau secret lain.
- Simpan konfigurasi environment-specific di `.env` dan Laravel config.
