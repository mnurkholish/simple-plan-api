# SIMPLE-PLAN Testing Guidelines

Dokumen ini menjadi panduan pengujian backend SIMPLE-PLAN.

Requirement dan business flow mengacu pada `docs/SRS.md`.  
Arsitektur backend mengacu pada `docs/BACKEND-ARCHITECTURE.md`.  
Kontrak API mengacu pada `openapi.yaml`.

Testing dilakukan untuk memastikan implementasi sesuai requirement, aman, stabil, dan tidak merusak fitur yang sudah berjalan.

---

## 1. Testing Strategy

Backend menggunakan:

```text
Feature / API Test
Unit Test
```

### Feature / API Test
Digunakan untuk menguji behavior yang melibatkan:
- route dan endpoint;
- authentication;
- authorization;
- validation;
- database persistence;
- JSON response;
- workflow;
- file upload jika relevan.

Sebagian besar test backend sebaiknya berupa Feature/API Test.

### Unit Test
Digunakan untuk business logic terisolasi yang cukup kompleks.

Contoh:
- SLA calculation;
- status transition rule;
- business calculation;
- helper/domain logic yang tidak membutuhkan HTTP atau database penuh.

Jangan membuat Unit Test untuk logic sederhana yang sudah tercakup dengan baik oleh Feature Test.

---

## 2. Test Structure

Gunakan struktur sederhana:

```text
tests/
├── Feature/
└── Unit/
```

Kelompokkan test berdasarkan domain jika jumlah test mulai bertambah.

Contoh:

```text
tests/Feature/
├── Auth/
├── Helpdesk/
├── Inventory/
├── Maintenance/
└── DesignRequest/
```

Ikuti struktur existing project sebelum membuat pola baru.

---

## 3. Required API Scenarios

Untuk setiap endpoint, test skenario yang relevan.

Prioritaskan:
- success;
- validation failure;
- unauthenticated;
- unauthorized;
- resource not found;
- invalid business state;
- database change/result;
- response structure.

Tidak semua endpoint wajib memiliki seluruh skenario jika memang tidak relevan.

Contoh untuk verifikasi tiket:

```text
✓ authorized user can verify valid ticket
✓ unauthenticated user cannot verify ticket
✓ unauthorized role cannot verify ticket
✓ invalid ticket returns not found
✓ invalid current state is rejected
✓ status and history are stored correctly
```

---

## 4. Authentication and Authorization Testing

Security behavior harus diuji secara eksplisit.

Saat relevan, test:
- user belum login;
- role tidak sesuai;
- unit tidak sesuai;
- resource bukan miliknya;
- petugas tidak ditugaskan;
- workflow state tidak mengizinkan action.

Frontend restriction tidak dianggap sebagai pengujian authorization.

Backend harus tetap menolak request yang tidak berhak.

---

## 5. Validation Testing

Test validation pada input penting.

Contoh:
- required field;
- invalid type;
- invalid format;
- invalid ID/reference;
- allowed values;
- maximum length;
- file type;
- file size.

Gunakan response validation yang konsisten dengan API guideline.

Jangan hanya menguji happy path.

---

## 6. Workflow Testing

Fitur seperti Helpdesk, Maintenance, dan Graphic Design Request memiliki controlled workflow.

Test harus memastikan:
- transition yang valid berhasil;
- transition yang tidak valid ditolak;
- actor yang benar dapat melakukan action;
- actor yang salah ditolak;
- history/audit dibuat jika requirement memerlukan;
- data akhir sesuai dengan expected state.

State transition test sebaiknya berfokus pada behavior, bukan detail implementasi internal.

---

## 7. Database Testing

Gunakan database test environment yang terisolasi.

Gunakan Laravel testing utilities dan model factories jika tersedia.

Test harus memverifikasi:
- record dibuat/diubah/dihapus sesuai requirement;
- relationship tersimpan benar;
- transaction menjaga konsistensi data;
- duplicate/conflicting data ditangani dengan benar jika relevan.

Hindari ketergantungan pada data manual yang sudah ada di database developer.

---

## 8. File Upload Testing

Untuk fitur file, gunakan Laravel Storage fake jika sesuai agar test tidak bergantung pada MinIO.

Contoh skenario:
- upload berhasil;
- invalid file ditolak;
- file terlalu besar ditolak;
- unauthorized upload/access ditolak;
- metadata tersimpan;
- file terhapus/terganti sesuai business rule;
- cleanup dilakukan jika proses database gagal dan file tidak lagi diperlukan.

Integration test dengan MinIO asli hanya diperlukan jika ingin menguji konfigurasi/integrasi storage secara nyata.

---

## 9. API Response Testing

Test response yang penting, seperti:
- HTTP status;
- JSON structure;
- field penting;
- pagination metadata jika digunakan;
- error structure;
- absence of sensitive fields.

Hindari test yang terlalu ketat terhadap detail response yang tidak termasuk API contract.

Gunakan `openapi.yaml` sebagai referensi contract endpoint.

---

## 10. Regression Testing

Saat memperbaiki bug:

1. reproduksi bug;
2. buat atau sesuaikan test yang gagal karena bug tersebut;
3. perbaiki implementation;
4. pastikan test menjadi lulus;
5. jalankan test terkait untuk memastikan tidak ada regression.

Jangan memperbaiki bug hanya dengan mengubah test agar sesuai behavior yang salah.

---

## 11. Running Tests

Project menggunakan Pest jika sesuai konfigurasi project.

Jalankan test paling relevan selama development.

Contoh:

```bash
php artisan test --compact --filter=Ticket
```

Atau file tertentu:

```bash
php artisan test --compact tests/Feature/Helpdesk/TicketVerificationTest.php
```

Setelah perubahan signifikan, jalankan test yang mencakup area terdampak.

Sebelum merge/release, jalankan full test suite jika memungkinkan:

```bash
php artisan test --compact
```

---

## 12. Test Data

Gunakan Factory untuk membuat data test jika tersedia.

Gunakan factory states jika sudah disediakan.

Contoh konsep:

```text
User::factory()->petugasTik()
Ticket::factory()->verified()
```

Jangan membuat banyak fixture statis jika Factory lebih fleksibel.

Seeder bukan dependency utama automated test kecuali memang diperlukan oleh project.

---

## 13. Test Naming

Gunakan nama test yang menjelaskan behavior.

Contoh:

```text
authorized_coordinator_can_verify_ticket
unauthorized_user_cannot_assign_ticket
ticket_cannot_be_completed_from_invalid_state
invalid_attachment_type_is_rejected
```

Nama test harus menjelaskan expected behavior, bukan detail implementasi internal.

---

## 14. What Not to Test

Hindari test yang tidak memberikan nilai nyata, seperti:
- getter/setter sederhana;
- behavior bawaan Laravel yang tidak dimodifikasi;
- implementation detail yang mudah berubah;
- private method secara langsung.

Fokus pada behavior sistem yang penting terhadap requirement.

---

## 15. Completion Criteria

Task dianggap selesai dari sisi testing jika applicable:

- [ ] happy path diuji;
- [ ] validation penting diuji;
- [ ] authentication diuji;
- [ ] authorization diuji;
- [ ] invalid business state diuji;
- [ ] database result sesuai;
- [ ] API response sesuai contract;
- [ ] file behavior diuji jika relevan;
- [ ] regression test ditambahkan untuk bug fix;
- [ ] relevant tests lulus.

---

## 16. Principles

- **Test behavior, not implementation detail.**
- **Prioritaskan Feature/API Test untuk backend behavior.**
- **Gunakan Unit Test untuk logic terisolasi yang benar-benar kompleks.**
- **Security dan authorization harus diuji, bukan diasumsikan.**
- **Gunakan test terkecil yang relevan selama development.**
- **Hindari test berlebihan yang memperlambat development tanpa manfaat nyata.**
- **Requirement tetap menjadi dasar expected behavior.**

---

## 17. Summary

```text
Requirement / SRS
      ↓
Implementation
      ↓
Feature / API Test
      ↓
Unit Test (jika perlu)
      ↓
Regression Check
      ↓
Ready to Merge
```

Testing SIMPLE-PLAN harus cukup kuat untuk memverifikasi behavior penting, tetapi tetap sederhana, fokus, dan tidak over-engineering.
