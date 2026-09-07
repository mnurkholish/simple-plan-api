# AGENTS.md

# SIMPLE-PLAN API Development Guide

SIMPLE-PLAN is an internal information system for RS Citra Husada
covering Helpdesk, Inventory, Maintenance, Graphic Design Requests,
Notifications, Reports, and Backup.

This repository contains the Laravel backend/API.

---

## 1. Source of Truth

Use the following sources in this order:

1. The current task/request.
2. `docs/SRS.md` for functional requirements and business flows.
3. `openapi.yaml` for the approved API contract.
4. Project technical documentation.
5. Existing code conventions and patterns.

Technical documentation:

- `docs/BACKEND-ARCHITECTURE.md`
- `docs/DATABASE.md`
- `docs/API-GUIDELINES.md`
- `docs/TESTING.md`
- `docs/DEPLOYMENT.md`

Task, sprint, and progress tracking are managed in Trello.
Do not create duplicate tracking documents in this repository.

The SRS is still evolving.

Do not invent missing business requirements.
If a requirement is incomplete, contradictory, or materially ambiguous,
report it before making a product-level decision.

---

## 2. Technology

This is a Laravel application.

Before using version-specific Laravel or package APIs:

- inspect `composer.json` / installed Composer packages;
- inspect `package.json` when JavaScript dependencies are relevant;
- use Laravel Boost documentation/search tools when available and useful.

Prefer existing project dependencies and Laravel built-in features.

Do not add, remove, or upgrade dependencies unless required by the task.

---

## 3. Architecture

Use:

Route
-> Middleware
-> FormRequest
-> Controller
-> Service
-> Repository
-> Model
-> Database

### Controller

Controllers handle HTTP concerns only.

They should:

- receive validated input;
- call Services;
- return API responses.

Do not place substantial business logic in Controllers.

### Service

Services contain business logic and workflow orchestration.

Examples:

- `TicketService`
- `AssetService`
- `MaintenanceService`
- `DesignRequestService`

State transitions and multi-step business operations belong here.

### Repository

Repositories handle data access and persistence.

Do not place HTTP concerns or unrelated business workflows
inside repositories.

### Model

Use Eloquent models and relationships.

Avoid placing complex workflows directly in models.

### Validation

Use FormRequest classes for request validation when appropriate.

### Response

Use Laravel API Resources or the established project response pattern.

Do not expose raw model data when a defined API representation exists.

---

## 4. Existing Conventions First

Before creating or modifying code:

1. inspect related existing files;
2. inspect sibling classes;
3. reuse established naming and structure;
4. reuse existing components or abstractions where appropriate.

Do not introduce a second pattern for a problem already solved
consistently elsewhere in the project.

Do not create new top-level directories without a clear need.

---

## 5. API

Follow:

- `docs/API-GUIDELINES.md`
- `openapi.yaml`

Use REST conventions and JSON responses.

Each relevant endpoint must define:

- HTTP method;
- path;
- authentication;
- authorization;
- parameters;
- request body;
- response body;
- validation errors;
- HTTP status codes.

When an implementation changes the API contract,
update `openapi.yaml` in the same task.

Do not create undocumented API behavior.

---

## 6. Authentication and Authorization

SIMPLE-PLAN uses role-based access control.

Roles defined by the project include:

- Super Admin
- Koordinator TIK
- Koordinator Sarpras
- Petugas TIK
- Petugas Sarpras
- Desainer Grafis
- User/Unit
- Manajemen

Authorization must be enforced by the backend.

Frontend visibility is not authorization.

When relevant, check:

- authenticated user;
- role;
- unit;
- resource ownership;
- assigned petugas;
- current workflow state.

Use the authentication and authorization mechanism already adopted
by the application. Do not introduce a different mechanism
without an explicit requirement.

---

## 7. Business Workflows

Helpdesk, Maintenance, and Design Requests contain controlled
workflow/state transitions.

Before changing a workflow state:

1. validate the current state;
2. validate the requested transition;
3. verify actor authorization;
4. validate required data;
5. persist required history/audit information.

Never allow arbitrary status changes simply because a submitted
status value is syntactically valid.

If valid transitions are unclear in the SRS, report the ambiguity.

---

## 8. Database

Follow `docs/DATABASE.md`.

Before changing the database:

- inspect existing migrations;
- inspect related models and relationships;
- inspect relevant requirements.

Use Laravel migrations for schema changes.

Use appropriate:

- foreign keys;
- constraints;
- indexes;
- transactions;
- relationships.

Avoid duplicate tables, fields, or stored derived values
unless justified by the requirement.

Use factories and seeders when they provide value for development
or testing; do not create unnecessary sample infrastructure.

---

## 9. Security

For relevant features:

- validate all client input;
- require authentication;
- enforce authorization;
- use Laravel-supported password hashing;
- protect against mass assignment;
- validate uploaded files;
- avoid exposing sensitive fields;
- protect audit/history records;
- avoid exposing internal exceptions.

Never commit:

- `.env`;
- passwords;
- API keys;
- database credentials;
- other secrets.

Configuration that varies by environment belongs in `.env`
and Laravel configuration files.

Keep `.env.example` updated when required configuration changes.

---

## 10. File Uploads

SIMPLE-PLAN may handle:

- ticket evidence;
- repair evidence;
- maintenance documentation;
- design drafts;
- design revisions;
- final design files.

Validate applicable:

- file type;
- size;
- required/optional state;
- ownership and access.

Follow SRS limits where defined.

Do not expose internal storage paths unnecessarily.

---

## 11. Performance

Keep implementation appropriate for an internal hospital system.

Prefer:

- pagination for large collections;
- eager loading where needed;
- avoiding N+1 queries;
- appropriate database indexes;
- efficient filtering and search.

Do not add caching, queues, or complex optimization
without an actual requirement or demonstrated need.

Avoid premature optimization.

---

## 12. Testing

This project uses Pest unless the existing project configuration says otherwise.

Follow `docs/TESTING.md`.

Prefer Feature/API tests for behavior involving:

- routing;
- authentication;
- authorization;
- validation;
- database persistence;
- JSON responses.

Use Unit tests for isolated business logic when useful.

For an endpoint, test applicable scenarios:

- success;
- validation failure;
- unauthenticated;
- unauthorized;
- not found;
- invalid business state.

Use model factories in tests where available.

Run the narrowest relevant tests while developing.

Example:

```bash
php artisan test --compact --filter=Ticket
```
