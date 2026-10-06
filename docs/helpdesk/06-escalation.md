# Flow 6: Escalation

## Scope
Fitur eskalasi tiket ke pihak eksternal/manajemen.

## Implemented
- Validasi target eskalasi (Manajemen, Vendor, Tim Terkait).
- Kewajiban mengisi catatan (notes) eskalasi.

**Kontrak Request Body:**
- `target` (string, required): Hanya menerima nilai exact `Manajemen`, `Vendor`, atau `Tim Terkait`.
- `notes` (string, required): Minimal 5 karakter.

**Response:**
- `200 OK`: Jika eskalasi berhasil dilakukan.
- `409 Conflict`: Jika tiket tidak berstatus `diproses`.
- `422 Unprocessable Entity`: Jika validasi gagal (misalnya format target salah atau notes kurang dari 5 karakter).

## Status Flow
`diproses -> eskalasi -> diproses`

## Authorization

Kedua action memerlukan permission `tickets-handle`, dengan cakupan:

- Super Admin dapat mengeskalasi dan melanjutkan seluruh tiket TIK dan Sarpras
  tanpa harus ditugaskan.
- Koordinator Sarpras dapat mengeskalasi dan melanjutkan seluruh tiket Sarpras
  tanpa harus ditugaskan, tetapi tidak dapat melakukannya pada tiket TIK.
- Petugas TIK dan Petugas Sarpras hanya dapat melakukan action pada tiket yang
  ditugaskan kepadanya sesuai layanan masing-masing.

## Routes
`POST /api/v1/tickets/{ticket}/escalate`

`POST /api/v1/tickets/{ticket}/de-escalate`

## Notes
- Frontend dapat mengandalkan error 422 untuk menampilkan feedback validasi.

## Pending
- Saat ini belum ada integrasi sistem eksternal otomatis untuk vendor, eskalasi masih dikelola secara internal melalui platform.
