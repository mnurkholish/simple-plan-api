# Flow 7: Resolution Verification

## Scope
Verifikasi penyelesaian tiket oleh pelapor (user/unit).

## Implemented
- Pelapor dapat menyetujui atau menolak tiket yang telah ditangani.
- Menghapus penggunaan status perantara `terverifikasi`.
- Wajib menyertakan keterangan kendala jika ditolak.

**Kontrak Request Body:**
- `is_approved` (boolean, required): `true` untuk setuju, `false` untuk tolak.
- `keterangan_kendala` (string, required_if is_approved false): Wajib diisi jika pelapor menolak (`is_approved` = false).

## Status Flow
- **Disetujui**: `terselesaikan -> ditutup`
- **Ditolak**: `terselesaikan -> diproses`
- **Tidak diverifikasi selama 2 hari sejak `completed_at`**:
  `terselesaikan -> ditutup` secara otomatis oleh scheduler.

## Authorization
Hanya dapat dilakukan oleh pelapor tiket (`reporter_id`).

## Routes
`POST /api/v1/tickets/{ticket}/verify`

## Notes
- **UI/Frontend:** Frontend perlu menyiapkan tombol "Setuju" dan "Tolak", serta memunculkan modal/form isian kendala secara dinamis jika tombol "Tolak" ditekan sebelum mengirimkan request ke backend.

## Automatic Close

Command `tickets:auto-close-unverified` dijalankan scheduler setiap menit.
Penutupan bersifat idempotent, mengisi `closed_at`, dan membuat status history
dengan `changed_by_id = null` untuk menandai aksi sistem.
