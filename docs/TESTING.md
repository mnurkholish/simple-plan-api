# SIMPLE-PLAN Testing Guidelines

Panduan ini hanya membahas strategi testing backend SIMPLE-PLAN: Feature/API vs
Unit, authentication/authorization, validation, workflow, database, storage fake,
regression, command test, dan completion criteria.

Requirement dan business flow mengacu pada `docs/SRS.md`.
Kontrak API mengacu pada `openapi.yaml`.

---

## 1. Test Types

Project menggunakan Pest jika sesuai konfigurasi project.

### Feature/API Test

Utama untuk behavior yang melibatkan:

- route dan endpoint;
- authentication;
- authorization;
- validation;
- database persistence;
- JSON response;
- workflow;
- file upload/access jika relevan.

Sebagian besar test backend sebaiknya berupa Feature/API Test.

### Unit Test

Gunakan untuk business logic terisolasi yang cukup kompleks, seperti:

- SLA calculation;
- status transition rule;
- business calculation;
- helper/domain logic tanpa HTTP atau database penuh.

Jangan membuat Unit Test untuk logic sederhana yang sudah tercakup baik oleh
Feature Test.

---

## 2. Test Structure

Gunakan struktur sederhana:

```text
tests/
  Feature/
  Unit/
```

Kelompokkan berdasarkan domain jika test mulai banyak, misalnya `Helpdesk`,
`Inventory`, `Maintenance`, atau `DesignRequest`.

Ikuti struktur existing project sebelum membuat pola baru.

---

## 3. API Scenario Coverage

Untuk setiap endpoint, test skenario yang relevan:

- success;
- validation failure;
- unauthenticated;
- unauthorized;
- resource not found;
- invalid business state;
- database change/result;
- response structure.

Tidak semua endpoint wajib memiliki seluruh skenario jika memang tidak relevan.

---

## 4. Authentication and Authorization

Security behavior harus diuji eksplisit.

Saat relevan, test:

- user belum login;
- role tidak sesuai;
- unit tidak sesuai;
- resource bukan miliknya;
- petugas tidak ditugaskan;
- workflow state tidak mengizinkan action.

Frontend restriction tidak dianggap sebagai authorization test.

---

## 5. Validation

Test validation pada input penting:

- required field;
- invalid type/format;
- invalid ID/reference;
- allowed values;
- maximum length;
- file type;
- file size.

Gunakan response validation yang konsisten dengan API guideline. Jangan hanya
menguji happy path.

---

## 6. Workflow

Untuk Helpdesk, Maintenance, dan Graphic Design Request, test behavior workflow:

- transition valid berhasil;
- transition invalid ditolak;
- actor yang benar dapat melakukan action;
- actor yang salah ditolak;
- history/audit dibuat jika requirement memerlukan;
- data akhir sesuai expected state.

State transition test berfokus pada behavior, bukan detail implementasi
internal.

---

## 7. Database

Gunakan database test environment yang terisolasi.

Gunakan Laravel testing utilities dan model factories jika tersedia.

Verifikasi:

- record dibuat/diubah/dihapus sesuai requirement;
- relationship tersimpan benar;
- transaction menjaga konsistensi data;
- duplicate/conflicting data ditangani jika relevan.

Hindari ketergantungan pada data manual di database developer.

---

## 8. File Upload and Storage

Untuk fitur file, gunakan Laravel Storage fake jika sesuai agar automated test
tidak bergantung pada MinIO.

Skenario yang relevan:

- upload berhasil;
- invalid file ditolak;
- file terlalu besar ditolak;
- unauthorized upload/access ditolak;
- metadata tersimpan;
- file terhapus/terganti sesuai business rule;
- cleanup dilakukan jika proses database gagal dan file tidak lagi diperlukan.

Integration test dengan MinIO asli hanya diperlukan untuk menguji konfigurasi
storage secara nyata.

---

## 9. API Response

Test response penting:

- HTTP status;
- JSON structure;
- field penting;
- pagination metadata jika digunakan;
- error structure;
- absence of sensitive fields.

Hindari test yang terlalu ketat terhadap detail response di luar API contract.
Gunakan `openapi.yaml` sebagai referensi contract endpoint.

---

## 10. Regression

Saat memperbaiki bug:

1. reproduksi bug;
2. buat atau sesuaikan test yang gagal karena bug tersebut;
3. perbaiki implementation;
4. pastikan test lulus;
5. jalankan test terkait untuk memastikan tidak ada regression.

Jangan memperbaiki bug hanya dengan mengubah test agar sesuai behavior yang
salah.

---

## 11. Running Tests

Jalankan test paling relevan selama development:

```bash
php artisan test --compact --filter=Ticket
```

File tertentu:

```bash
php artisan test --compact tests/Feature/Helpdesk/TicketVerificationTest.php
```

Full suite sebelum merge/release jika memungkinkan:

```bash
php artisan test --compact
```

---

## 12. Test Data

Gunakan Factory untuk membuat data test jika tersedia. Gunakan factory states
jika sudah disediakan.

Seeder bukan dependency utama automated test kecuali memang diperlukan project.
Jangan membuat fixture statis besar jika Factory lebih fleksibel.

---

## 13. Test Naming

Gunakan nama test yang menjelaskan behavior:

```text
authorized_coordinator_can_verify_ticket
unauthorized_user_cannot_assign_ticket
ticket_cannot_be_completed_from_invalid_state
invalid_attachment_type_is_rejected
```

Nama test menjelaskan expected behavior, bukan detail implementasi internal.

---

## 14. What Not to Test

Hindari test yang tidak memberi nilai nyata:

- getter/setter sederhana;
- behavior bawaan Laravel yang tidak dimodifikasi;
- implementation detail yang mudah berubah;
- private method secara langsung.

Fokus pada behavior sistem yang penting terhadap requirement.

---

## 15. Completion Criteria

Task dianggap cukup dari sisi testing jika applicable:

- happy path diuji;
- validation penting diuji;
- authentication diuji;
- authorization diuji;
- invalid business state diuji;
- database result sesuai;
- API response sesuai contract;
- file behavior diuji jika relevan;
- regression test ditambahkan untuk bug fix;
- relevant tests lulus.
