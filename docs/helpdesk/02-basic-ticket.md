# Basic Ticket

## Scope

Tahap ini menyelaraskan alur dasar Helpdesk untuk membuat tiket, melihat daftar
tiket, dan melihat detail tiket. Implementasi mencakup nomor tiket final, asset
opsional, initial evidence opsional, pagination/filter sederhana yang sudah ada,
serta authorization list dan detail dari Ticket Foundation.

Classification/rejection, assignment, handling, escalation, resolution, dan
notification tidak diperluas pada tahap ini.

## Existing Implementation Audit

Sebelum perubahan, repository sudah memiliki:

- route `GET /tickets`, `POST /tickets`, dan `GET /tickets/{ticket}`;
- `TicketController`, `StoreTicketRequest`, `ListTicketRequest`,
  `TicketResource`, dan `TicketService`;
- pagination serta filter service, status, rentang tanggal, dan search;
- `Ticket::visibleTo()` dan `TicketPolicy` dari Ticket Foundation;
- upload initial evidence melalui Laravel Filesystem, penyimpanan metadata
  file, dan cleanup file jika transaksi database gagal;
- relationship asset, reporter, unit, detail klasifikasi, assigned officer,
  handling, dan status history;
- permission `tickets-create` dan `tickets-access` untuk seluruh role final;
- constraint unique pada `tickets.ticket_number`.

Existing implementation yang dipertahankan adalah route, permission umum,
pagination/filter sederhana, actor view scope, policy, storage abstraction,
metadata initial evidence, dan response compatibility `category` serta
`rejection_reason` yang diturunkan dari relationship terbaru.

Bagian yang sebelumnya tidak sesuai:

- request create menerima `unit_id` dan field klasifikasi dari client;
- create langsung membuat detail klasifikasi jika field tersebut dikirim;
- create belum menerima `asset_id`;
- nomor tiket memakai ID database, empat digit, dan prefix `SARPRAS`;
- response belum memuat asset, classifier, seluruh timestamp ticket, dan
  status history pada detail;
- query list/detail dan eager loading masih berada di controller walaupun arah
  architecture project menggunakan repository;
- factory dan kontrak OpenAPI masih mencerminkan create flow lama;
- tidak ada frontend Helpdesk di repository backend ini untuk disesuaikan.

## Changes

- Create ticket sekarang hanya memvalidasi `service`, `description`,
  `asset_id` opsional, dan `initial_evidence` opsional.
- Reporter dan snapshot unit selalu diambil dari authenticated user. Field
  server-controlled yang dikirim client tidak dipakai.
- User tanpa unit mendapat validation error karena `tickets.unit_id` bersifat
  non-null pada schema terbaru.
- Create tidak lagi membuat `tikDetail` atau `sarprasDetail`.
- Status awal selalu `baru` dan create tidak membuat status history palsu.
- `asset_id` divalidasi terhadap asset yang ada dan tidak soft-deleted.
- Generator nomor tiket disesuaikan ke prefix dan sequence final.
- `TicketRepository` ditambahkan untuk persistence create, generator sequence,
  authorized list query, dan eager loading response summary/detail. Business
  rule dan transaction tetap berada di `TicketService`.
- `TicketResource` sekarang menyediakan asset, classifier, timestamp ticket,
  dan status history pada response detail. Compatibility field `category` dan
  `rejection_reason` tetap berasal dari relationship, bukan kolom legacy.
- Factory, OpenAPI/Swagger, dan test Helpdesk diselaraskan dengan behavior
  tersebut.

## Create Ticket Flow

Flow aktual:

```text
Authenticated user dengan tickets-create
-> validasi service, description, asset opsional, dan evidence opsional
-> server memastikan reporter mempunyai unit
-> server memakai reporter_id dan unit_id dari authenticated user
-> server mengambil lock nomor per service dan tahun
-> generate nomor dan create ticket dalam database transaction
-> status disimpan sebagai baru
-> response summary dikembalikan
```

Field seperti `reporter_id`, `unit_id`, `status`, `priority`, classifier,
assigned officer, SLA, completion, dan closed timestamp tidak dipercaya dari
request create. Tidak tersedia update flow untuk mengganti service.

## Ticket Number

Format yang dihasilkan:

```text
TIK-YYYY-NNNNNN
SPR-YYYY-NNNNNN
```

Sequence dibaca berdasarkan prefix service dan tahun sehingga terpisah per
service serta mulai kembali dari `000001` pada tahun baru. Pembuatan dilindungi
oleh atomic cache lock per service/tahun, database transaction, row lock pada
sequence terbaru, dan unique constraint `tickets.ticket_number`.

## List Authorization

`GET /tickets` lebih dahulu menerapkan `Ticket::visibleTo()` melalui
`TicketRepository`, kemudian filter dan pagination:

- `super-admin`: seluruh tiket TIK dan Sarpras;
- `koordinator-sarpras`: seluruh tiket Sarpras;
- `petugas-tik`: tiket TIK yang assigned kepadanya dan tiket yang dibuat
  sendiri;
- `petugas-sarpras`: tiket Sarpras yang assigned kepadanya dan tiket yang
  dibuat sendiri;
- `user`: tiket yang dibuat sendiri;
- `management`: seluruh tiket TIK dan Sarpras.

Permission `tickets-access` tetap menjadi capability umum dan tidak mengganti
business view scope.

## Detail Authorization

`GET /tickets/{ticket}` menggunakan `TicketPolicy::view`. Actor harus memiliki
`tickets-access` dan ticket harus termasuk hasil `Ticket::visibleTo(actor)`.
Actor di luar scope menerima `403`, sedangkan ID ticket yang tidak ditemukan
mengikuti route convention dan menerima `404`.

Detail memuat data ticket yang tersedia melalui relationship terbaru,
termasuk asset, informasi klasifikasi, assigned officer, handling history,
status history, dan timestamp. Data yang belum tersedia dikembalikan sebagai
`null` atau collection kosong sesuai bentuk resource.

## Asset

`asset_id` tidak wajib. Jika diberikan, nilainya harus menunjuk asset yang ada
dan tidak soft-deleted. Ticket tanpa asset tetap dapat dibuat. Tahap ini tidak
menambahkan CRUD asset atau barcode scanner.

## Initial Evidence

`initial_evidence` tidak wajib dan divalidasi sebagai image maksimal 5 MB.
File disimpan menggunakan default Laravel Filesystem dan database hanya
menyimpan object key, original name, MIME type, serta size. Jika proses create
gagal setelah file tersimpan, service menghapus file tersebut agar tidak
menjadi orphan.

## Routes

Route Basic Ticket yang aktif:

```text
GET  /api/v1/tickets
POST /api/v1/tickets
GET  /api/v1/tickets/{ticket}
```

Route workflow existing untuk verify/reject/assign/handling tetap dipertahankan
agar implementation existing tidak rusak, tetapi tidak diperluas pada tahap
ini.

## Files Changed

File utama yang dibuat/diubah:

- `app/Enums/TicketService.php`
- `app/Repositories/TicketRepository.php`
- `app/Services/TicketService.php`
- `app/Http/Controllers/Api/V1/TicketController.php`
- `app/Http/Controllers/Api/V1/TicketHandlingController.php`
- `app/Http/Requests/StoreTicketRequest.php`
- `app/Http/Resources/TicketResource.php`
- `database/factories/TicketFactory.php`
- `openapi.yaml`
- `app/OpenApi/ApiDocumentation.php`
- `storage/api-docs/api-docs.json`
- test Helpdesk dan test OpenAPI terkait

Tidak ada migration atau source-of-truth Sprint 1 yang diubah.

## Validation

- PHP syntax check pada file PHP yang berubah: PASS
- parsing `openapi.yaml`: PASS
- `composer dump-autoload`: PASS
- `php artisan optimize:clear`: PASS
- `php artisan migrate:fresh --seed`: PASS
- `./vendor/bin/pint`: PASS
- `php artisan route:list --path=tickets`: PASS, 7 route ticket terdaftar
- `php artisan l5-swagger:generate`: PASS
- test Helpdesk dan OpenAPI terfokus: PASS
- `php artisan test --compact`: PASS, 138 tests dan 793 assertions
- global search create flow dan value legacy: PASS; tidak ada create flow aktif
  yang menerima klasifikasi/arbitrary reporter, unit, status, priority, atau
  officer, dan tidak ada generator format lama pada active code. `urgent`
  hanya tersisa sebagai invalid-input test.

## Pending

- classification dan rejection lengkap;
- assignment dan reassignment;
- handling lengkap;
- escalation dan resume escalation;
- resolution verification/rejection dan automatic close;
- notification;
- advanced filtering dan UI polish;
- keputusan product jika user tanpa unit harus dapat membuat ticket pada
  schema yang mewajibkan `tickets.unit_id`.

## Next Step

Tahap 03 — Classification
