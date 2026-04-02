# NexusCare — Backend

Laravel 11 API backend for NexusCare. Runs inside Docker; do **not** run directly on the host.

---

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.2 |
| Framework | Laravel 11 |
| Auth | JWT (tymon/jwt-auth) via HTTP-only cookie |
| Database | MySQL 8.0 |
| Process manager | Supervisord (PHP-FPM + Nginx) |

---

## Start (from project root)

```bash
docker compose up -d --build
```

Migrations and seeders run automatically on container start via `entrypoint.sh`.
No manual `migrate` or `db:seed` commands are needed.

---

## Verify

```bash
curl http://localhost:8000/api/health
# → {"status":"ok"}
```

---

## Run backend tests

```bash
# From project root — run_tests.sh handles env vars automatically
./run_tests.sh

# Or directly (APP_KEY required):
APP_KEY=$(docker compose exec -T backend bash -c "grep APP_KEY /var/www/.env | cut -d= -f2-")
JWT_SECRET=$(docker compose exec -T backend bash -c "grep JWT_SECRET /var/www/.env | cut -d= -f2-")
docker compose exec -T -e APP_KEY="$APP_KEY" -e JWT_SECRET="$JWT_SECRET" backend php artisan test
```

---

## Key directories

```
app/
  Http/Controllers/Api/   # Thin controllers — validate, delegate, respond
  Services/               # Business logic layer
  Repositories/           # Eloquent data access layer
  Models/                 # Eloquent models + global scopes
  Policies/               # Object-level authorization
  Rules/                  # Custom validation rules
  Support/                # AuditLogger, MaskingService
  Console/Commands/       # Scheduled commands (purge, sync)
database/
  migrations/             # Schema history
  seeders/                # Demo + test fixture data
tests/
  Unit/                   # Pure unit tests (no DB)
  Feature/                # HTTP integration tests (in-memory SQLite)
```

---

## Logging channels

| Channel | File | Purpose |
|---|---|---|
| `auth` | `storage/logs/auth.log` | Login, logout, lockout events |
| `billing` | `storage/logs/billing.log` | Payment post, refund events |
| `reconciliation` | `storage/logs/reconciliation.log` | Import runs, anomaly detection |
| `sync` | `storage/logs/sync.log` | Incremental sync operations |

Sensitive fields (`password_hash`, `access_token`, `government_id`) are redacted to `[REDACTED]` in all audit log payloads.

---

## Scheduled commands

| Command | Schedule | Purpose |
|---|---|---|
| `app:purge-expired-records` | Daily | Soft-delete records older than 24 months |
| `app:incremental-sync` | Every 15 min | Fingerprint-based incremental data sync |

---

## Environment variables (set by Docker Compose)

| Variable | Description |
|---|---|
| `APP_KEY` | Laravel encryption key |
| `JWT_SECRET` | JWT signing secret |
| `DB_HOST` | MySQL service hostname (`mysql`) |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` / `DB_PASSWORD` | Database credentials |
