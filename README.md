# SIMPLE-PLAN API

Backend/API Laravel untuk SIMPLE-PLAN, sistem internal RS Citra Husada yang
mencakup Helpdesk, Inventaris, Maintenance, Graphic Design Request,
Notification, Reporting, Backup, dan fitur terkait.

Dokumen entry point untuk development ada di `AGENTS.md`.

---

## Tech Stack

Berdasarkan konfigurasi project dan keputusan teknis saat ini:

- PHP `^8.4`
- Laravel Framework `^13.17`
- REST API + JSON
- PostgreSQL
- Laravel Sanctum
- Spatie Permission
- Spatie Query Builder
- Pest
- L5-Swagger/OpenAPI tooling

---

## Local Setup

1. Install dependency PHP.

    ```bash
    composer install
    ```

2. Siapkan environment.

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3. Konfigurasikan PostgreSQL di `.env`.

    ```env
    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5432
    DB_DATABASE=simple_plan
    DB_USERNAME=
    DB_PASSWORD=
    ```

4. Jalankan migration dan seeder jika diperlukan.

    ```bash
    php artisan migrate --seed
    ```

5. Jalankan server lokal.

    ```bash
    php artisan serve
    ```

API lokal tersedia di `http://localhost:8000`.

---

## Tests

```bash
php artisan test
```

Panduan testing ada di `docs/TESTING.md`.

---

## Documentation

- `AGENTS.md` - entry point untuk coding agent.
- `CONTRIBUTING.md` - workflow kontribusi dan branch.
- `docs/BACKEND-ARCHITECTURE.md` - arsitektur backend.
- `docs/DATABASE.md` - aturan PostgreSQL, schema, dan metadata file.
- `docs/API-GUIDELINES.md` - guideline REST API.
- `docs/TESTING.md` - strategi testing.
- `docs/DEPLOYMENT.md` - deployment production/internal RS.
- `openapi.yaml` - kontrak API formal.
