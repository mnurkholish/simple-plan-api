# Panduan Kontribusi

Dokumen ini menjelaskan workflow pengembangan dan konvensi yang digunakan oleh tim backend.

## 1. Workflow Pengembangan

Pengembangan menggunakan alur:

`feature/* → develop → main`

### Langkah 1: Perbarui `develop`

Sebelum memulai task baru, pastikan branch `develop` lokal sudah menggunakan versi terbaru.

git checkout develop
git pull origin develop

### Langkah 2: Buat Feature Branch

Buat branch berdasarkan task yang akan dikerjakan.

git checkout -b feature/<nama-task>

Contoh:
- `feature/api-helpdesk`
- `feature/ticket-service`
- `feature/inventory-qr`

Untuk perbaikan bug:
`fix/<nama-task>`

Contoh:
- `fix/validation-error`

### Langkah 3: Kerjakan Task

Kerjakan perubahan sesuai ruang lingkup task pada branch tersebut.
Hindari mencampurkan perubahan yang tidak berkaitan dengan task yang sedang dikerjakan.

### Langkah 4: Commit Perubahan

Project menggunakan Conventional Commits. Format: `<type>: <deskripsi>`

Jenis commit yang umum digunakan:

| Type       | Penggunaan                                     |
| ---------- | ---------------------------------------------- |
| `feat`     | Menambahkan fitur API atau logic baru          |
| `fix`      | Memperbaiki bug atau error backend             |
| `refactor` | Mengubah struktur kode tanpa mengubah behavior |
| `style`    | Perubahan formatting code (linting)            |
| `chore`    | Konfigurasi server, package, atau environment  |
| `docs`     | Perubahan dokumentasi repository               |
| `test`     | Menambahkan atau mengubah automated test       |
| `ci`       | Konfigurasi CI/CD (GitHub Actions)             |

Contoh:

git commit -m "feat: tambah endpoint verifikasi tiket helpdesk"
git commit -m "fix: perbaiki error saat upload bukti foto"
git commit -m "refactor: pindahkan logic upload ke ImageService"
git commit -m "chore: install package laravel pint"
git commit -m "test: tambah feature test untuk API inventaris"

### Langkah 5: Push Branch

git push -u origin feature/<nama-task>

### Langkah 6: Buat Pull Request

Buat Pull Request dari feature branch menuju `develop`.

Pull Request sebaiknya menjelaskan:
* Endpoint atau fitur apa yang diubah/ditambah.
* Detail implementasi arsitektur (jika kompleks).
* Status pengujian lokal.

### Langkah 7: Review dan Merge

Perubahan harus melalui proses review sebelum di-merge ke `develop`. Branch `main` tidak digunakan untuk pengembangan fitur secara langsung.

---

## 2. Konvensi Branch

### `main`
Berisi kode yang stabil dan siap digunakan untuk production atau release. Pengembangan tidak dilakukan langsung pada branch ini.

### `develop`
Berisi hasil integrasi pengembangan dari seluruh anggota tim backend. Branch ini menjadi tujuan utama merge dari feature branch.

### `feature/*`
Digunakan untuk mengembangkan fitur API atau task tertentu. Contoh: `feature/api-helpdesk`, `feature/inventory`

### `fix/*`
Digunakan untuk memperbaiki bug. Contoh: `fix/status-ticket-bug`

### `refactor/*`
Digunakan untuk perbaikan struktur kode tanpa mengubah response API. Contoh: `refactor/service-pattern`

---

## 3. Cakupan Modul & Koordinasi

Penugasan dan tracking modul backend sepenuhnya mengikuti task di **Trello**. Berdasarkan arsitektur sistem SIMPLE-PLAN, modul utama yang akan dikembangkan meliputi:

- **Master Data & Otentikasi**: Manajemen 6 role user (RBAC) dan manajemen akun.
- **Helpdesk (TIK & Sarpras)**: Pembuatan tiket, verifikasi, penugasan, pemantauan status, dan riwayat perbaikan.
- **Inventarisasi Aset**: Pengelolaan data aset dan integrasi QR Code/Barcode.
- **Preventive Maintenance**: Penjadwalan pemeliharaan, pengingat, dan pencatatan hasil pemeliharaan.
- **Desain Grafis**: Alur request, draft berversi, revisi, dan approval.
- **Utilitas & Laporan**: Dashboard KPI, notifikasi sistem, backup/restore data, dan export laporan.

### Koordinasi Wajib
Untuk menjaga konsistensi, pengembang wajib berkoordinasi (berdiskusi dengan tim frontend maupun sesama backend) sebelum melakukan perubahan pada:
1. **Struktur Arsitektur**: Termasuk pola standar `Route -> Middleware -> FormRequest -> Controller -> Service -> Repository`.
2. **Database/Migration**: Menambah/mengubah struktur tabel inti, relasi, foreign key, atau enum status.
3. **API Contract**: Menambah atau mengubah endpoint, request payload, atau format JSON response pada file `openapi.yaml`.

---

## 4. Aturan Umum Backend

* Kerjakan perubahan sesuai ruang lingkup task (Trello).
* Ambil perubahan terbaru dari `develop` sebelum memulai task baru.
* Gunakan nama branch yang deskriptif dan Conventional Commits.
* **DILARANG KERAS** melakukan commit file `.env`, API key, credential, atau secret ke repository. Pastikan file tersebut masuk ke `.gitignore`.
* **WAJIB** menjalankan automated test lokal (`php artisan test`) dan memastikan hasilnya *pass* sebelum membuat Pull Request.
* Dokumentasikan setiap perubahan endpoint yang disepakati ke dalam file `openapi.yaml`.
