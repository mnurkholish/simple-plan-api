# Ticket Foundation

## Scope

Tahap ini merapikan fondasi shared Helpdesk Ticket: representasi service,
priority, dan status; aturan transisi; pencatatan status history; relationship
dasar; serta pembatasan view ticket berdasarkan actor. Flow lengkap
classification, assignment, handling, escalation, resolution verification, dan
notification tidak ditambahkan pada tahap ini.

## Existing Implementation Audit

Sebelum perubahan, repository sudah memiliki:

- backed enum `TicketService` dengan value `tik` dan `sarpras` yang sesuai;
- backed enum `TicketPriority` dengan value `critical`, `high`, `medium`, dan
  `low` yang sesuai;
- model `Ticket` dengan relationship `reporter`, `unit`, `asset`,
  `classifiedBy`, `assignedOfficer`, `tikDetail`, `sarprasDetail`,
  `statusHistories`, `handlings`, dan `escalations` yang sesuai schema terbaru;
- cast enum dan datetime utama pada `Ticket`;
- tabel/model `ticket_status_histories` dengan field yang sesuai database;
- permission `tickets-access`, `tickets-create`, `tickets-verify`,
  `tickets-reject`, `tickets-assign`, dan `tickets-handle`;
- history untuk penolakan tiket baru;
- test Helpdesk untuk create, read, workflow, dan handling.

Bagian legacy/tidak sesuai yang ditemukan:

- `TicketStatus` hanya berisi lima status dan masih menggunakan value
  `selesai`;
- belum ada satu definisi pusat untuk seluruh allowed transition dan terminal
  state;
- list dan detail ticket hanya memeriksa permission umum, tanpa actor view
  scope;
- perubahan status pada workflow existing selain reject belum konsisten
  membuat `ticket_status_histories`;
- assignment existing masih berangkat dari `terverifikasi` dan tidak mengubah
  status menjadi `ditugaskan`;
- role Super Admin masih memakai dua bentuk nama (`super admin` dan
  `super-admin`);
- OpenAPI/Swagger masih mengekspos daftar status legacy;
- tidak ada frontend/status badge di repository backend ini untuk dirapikan;
- tidak ditemukan behavior reopen aktif.

Existing implementation retained:

- enum service dan priority;
- schema migration ticket dan status history;
- seluruh relationship dasar Ticket yang sudah benar;
- permission granular ticket;
- struktur controller, request, resource, service, dan route existing.

## Changes

- `TicketStatus` disesuaikan menjadi sembilan status final dan diberi
  `allowedTransitions()`, `canTransitionTo()`, serta `isTerminal()`.
- `TicketService` sekarang menggunakan satu jalur transisi atomik dengan row
  lock. Perubahan status nyata selalu memperbarui ticket dan membuat status
  history dalam transaction yang sama.
- Transisi ke status yang sama tidak membuat status history.
- Workflow existing diarahkan ke status final: aksi legacy verify memakai
  `baru -> diklasifikasi`, assignment memakai
  `diklasifikasi -> ditugaskan`, dan handling memakai
  `ditugaskan -> diproses` atau `diproses -> terselesaikan`.
- `TicketStatusHistory.from_status` dan `to_status` menggunakan enum cast.
- Local scope `Ticket::visibleTo()` ditambahkan sebagai query foundation view
  berdasarkan actor.
- `TicketPolicy` ditambahkan untuk `viewAny` dan `view`, lalu diterapkan pada
  list dan detail ticket.
- Nama role Super Admin dinormalisasi menjadi `super-admin` pada seeder,
  request user, user seeder, dan proteksi profile.
- Seluruh role final menerima capability umum `tickets-access` dan
  `tickets-create`; permission workflow granular tetap dipertahankan dan Super
  Admin tetap menerima seluruh permission. Business authorization per aksi
  selain view tetap menjadi pekerjaan tahap fitur terkait.
- Kontrak OpenAPI, anotasi Swagger, generated Swagger artifact, request
  handling, resource, factory/test reference, dan test terkait diselaraskan
  dengan status final.

## Status Transition

Allowed transition yang digunakan implementation:

```text
baru -> diklasifikasi
baru -> ditolak

diklasifikasi -> ditugaskan

ditugaskan -> diproses

diproses -> eskalasi
diproses -> terselesaikan

eskalasi -> diproses

terselesaikan -> terverifikasi
terselesaikan -> diproses

terverifikasi -> ditutup
```

`ditolak` dan `ditutup` adalah terminal state. Tidak ada transition reopen.

## Authorization

View scope yang diterapkan pada backend:

- `super-admin`: seluruh ticket TIK dan Sarpras;
- `koordinator-sarpras`: seluruh ticket Sarpras;
- `petugas-tik`: ticket TIK yang assigned kepadanya, ditambah ticket yang
  dibuat sendiri;
- `petugas-sarpras`: ticket Sarpras yang assigned kepadanya, ditambah ticket
  yang dibuat sendiri;
- `user`: ticket yang dibuat sendiri;
- `management`: seluruh ticket TIK dan Sarpras.

Permission `tickets-access` tetap menjadi capability umum. Ticket hanya masuk
hasil query atau dapat dibuka jika actor juga memenuhi business view scope.

## Files Changed

File utama yang dibuat/diubah:

- `app/Enums/TicketStatus.php`
- `app/Models/Ticket.php`
- `app/Models/TicketStatusHistory.php`
- `app/Policies/TicketPolicy.php`
- `app/Services/TicketService.php`
- `app/Http/Controllers/Api/V1/TicketController.php`
- `app/Http/Requests/StoreTicketHandlingRequest.php`
- `app/Http/Resources/TicketResource.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/UserSeeder.php`
- `openapi.yaml`
- `app/OpenApi/ApiDocumentation.php`
- test Helpdesk, test OpenAPI, dan `tests/Unit/TicketStatusTest.php`

## Validation

- `composer dump-autoload`: PASS
- `php artisan optimize:clear`: PASS
- `php artisan migrate:fresh --seed`: PASS
- `./vendor/bin/pint`: PASS
- `php artisan route:list --path=tickets`: PASS, 7 route ticket terdaftar
- `php artisan l5-swagger:generate`: PASS
- `php artisan test --compact`: PASS, 140 tests dan 778 assertions
- global legacy-value search: PASS; tidak ada value status/service/priority
  legacy pada active code. `urgent` hanya tersisa sebagai invalid-input case
  pada test.

## Pending

- alignment lengkap create/list/detail API, termasuk unit reporter, asset, dan
  ticket-number generator, pada Tahap 02;
- penyelarasan nama route/action legacy `/tickets/{ticket}/verify` dengan flow
  classification lengkap pada Tahap 03;
- input, metadata, priority, SLA, dan authorization classification;
- assignment dan reassignment lengkap beserta business authorization;
- handling lengkap dan authorization assigned officer;
- escalation dan resume escalation;
- resolution verification/rejection dan automatic close;
- notification;
- penyesuaian UI/action legacy di frontend repository terpisah, termasuk status
  mapping dan badge.

## Next Step

Tahap 02 — Basic Ticket
