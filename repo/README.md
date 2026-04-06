# NexusCare — Appointment & Billing Management System

Multi-department appointment scheduling, offline billing, and financial reconciliation system with role-based access control.

---

## Quick Start

```bash
docker compose up -d --build
```

That is the only command needed. The backend container automatically:
1. Generates `APP_KEY` and `JWT_SECRET` on first start (no `.env` required)
2. Runs `php artisan migrate --force`
3. Seeds all reference and demo data (admin users, test fixtures, demo data)

Wait ~30 seconds for MySQL to initialize and migrations to complete, then open:
- **Frontend:** http://localhost:80
- **Backend API:** http://localhost:8000/api/health

> **Note:** Seeders are skipped on subsequent container restarts if the database already
> contains users, making restarts safe and fast.

---

## Service URLs

| Service  | URL                          |
|----------|------------------------------|
| Frontend | http://localhost:80           |
| Backend API | http://localhost:8000/api  |
| Health check | http://localhost:8000/api/health |
| MySQL    | localhost:3306               |

---

## Frontend — Local Development

To build or test the frontend without Docker, use the **repository root** — the directory that contains `docker-compose.yml`, `frontend/`, `backend/`, and `e2e/` (not a parent `full_stack/` folder):

```bash
cd frontend

npm install
npm run dev      # Start local Vite dev server (proxies API to http://localhost:8000)
npm run build    # Production build -> dist/
npm run test     # Run Vitest unit tests
```

E2E tests require the full Docker stack to be running at `http://localhost:80` (frontend) and `http://localhost:8000` (backend API). Run the stack first if not already up:

```bash
# Step 1 — start the Docker stack (from repo root; skip if already running)
docker compose up -d --build

# Step 2 — verify both services are reachable before running E2E
curl -sf http://localhost:80 > /dev/null && echo "Frontend OK" || echo "Frontend NOT reachable — start Docker first"
curl -sf http://localhost:8000/api/health > /dev/null && echo "Backend OK"  || echo "Backend NOT reachable — start Docker first"

# Step 3 — run E2E tests (only after both checks show OK)
cd e2e
npm install
npx playwright install chromium
npx playwright test
```

---

## Login Credentials

Demo users are created by the database seeder on first start. Credentials are **not** committed to the repository.

To log in locally, check the seeder output or ask your team lead for the current demo passwords.

### E2E Test Credentials

E2E tests read credentials from environment variables. Create a local `.env` file in the `e2e/` directory (gitignored) or export them in your shell before running tests:

```bash
export E2E_ADMIN_USER=admin
export E2E_ADMIN_PASS=<your-seeded-admin-password>
export E2E_STAFF_USER=staff1
export E2E_STAFF_PASS=<your-seeded-staff-password>
export E2E_REVIEWER_USER=reviewer1
export E2E_REVIEWER_PASS=<your-seeded-reviewer-password>
```

If any of these variables are missing, the E2E suite will fail immediately with a clear error message.

---

## Role Permissions

| Feature                  | Staff | Reviewer | Administrator |
|--------------------------|-------|----------|---------------|
| Create/reschedule appointments | ✅ | ❌ | ✅ |
| Confirm appointments     | ✅    | ❌       | ✅            |
| View waitlist            | ✅    | ❌       | ✅            |
| Manage waitlist (add/remove/backfill) | ✅ | ❌ | ✅ |
| Post payments            | ✅    | ❌       | ✅            |
| Approve waivers          | ❌    | ✅       | ✅            |
| Import reconciliation CSV| ❌    | ✅       | ✅            |
| Resolve exceptions       | ❌    | ✅       | ✅            |
| Acknowledge anomalies    | ❌    | ✅       | ✅            |
| User management          | ❌    | ❌       | ✅            |
| Account moderation (ban/mute) | ❌ | ❌    | ✅            |
| Recycle bin              | ❌    | ❌       | ✅            |
| Export reports           | ❌    | ✅       | ✅            |
| View audit logs          | ❌    | ✅       | ✅            |
| Fee rule management      | ❌    | ❌       | ✅            |
| View ledger              | ❌    | ❌       | ✅            |

---

## Tech Stack

| Layer      | Technology                          |
|------------|-------------------------------------|
| Frontend   | Vue 3 + Vite + Element Plus + Pinia |
| Backend    | PHP 8.2 + Laravel 11                |
| Database   | MySQL 8.0                           |
| Auth       | JWT HS256 (HttpOnly cookie)         |
| Container  | Docker Compose                      |

---

## Run Tests

```bash
# Strict mode (default): fails if E2E dependencies are not installed
bash run_tests.sh

# Non-strict mode: backend tests pass even if E2E is skipped
bash run_tests.sh --allow-skip-e2e
```

`run_tests.sh` runs backend unit+feature tests (via Docker) and then Playwright E2E tests. In strict mode (default), a skipped E2E suite causes a non-zero exit and prints a clear `PARTIAL RUN` message. Use `--allow-skip-e2e` only in CI environments where E2E dependencies cannot be installed.

Or run backend tests individually:

```bash
# Unit tests
docker exec nexuscare-backend php artisan test --testsuite=Unit

# Feature tests
docker exec nexuscare-backend php artisan test --testsuite=Feature
```

---

## Container Names

| Container           | Role              |
|---------------------|-------------------|
| `nexuscare-backend` | PHP-FPM + Nginx   |
| `nexuscare-frontend`| Vue (Nginx static)|
| `nexuscare-mysql`   | MySQL 8.0         |
