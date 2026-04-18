# NexusCare Appointment & Billing Management System

> **Project type:** fullstack

A full-stack healthcare operations platform for appointment scheduling, billing, reconciliation, and role-based workflow control across staff, reviewers, and administrators.

## Architecture & Tech Stack

* **Frontend:** Vue 3, Vite, Element Plus, Pinia
* **Backend:** PHP 8.2, Laravel 11, Nginx/PHP-FPM
* **Database:** MySQL 8.0
* **Containerization:** Docker & Docker Compose (Required)

## Project Structure

*Below is a sample project structure*

```text
.
├── backend/                # Backend source code and Dockerfile
├── frontend/               # Frontend source code and Dockerfile
├── e2e/                    # Playwright end-to-end tests
├── backend/.env.example    # Example backend environment variables
├── docker-compose.yml      # Multi-container orchestration - MANDATORY
├── run_tests.sh            # Standardized test execution script - MANDATORY
└── README.md               # Project documentation - MANDATORY
```

## Prerequisites

To ensure a consistent environment, this project is designed to run entirely within containers. You must have the following installed:
* [Docker](https://docs.docker.com/get-docker/)
* [Docker Compose](https://docs.docker.com/compose/install/)

## Running the Application

1. **Build and Start Containers:**
   Use Docker Compose to build the images and spin up the entire stack in detached mode.
   ```bash
   docker-compose up --build -d
   ```
   *(Also works as:* `docker compose up --build -d` *on modern Docker Compose plugin)*

2. **Environment Setup:**
   This project can boot without manual `.env` creation because startup scripts generate runtime secrets and run migrations/seeding automatically.
   If you need local customization, you can copy the backend example file:
   ```bash
   cp backend/.env.example backend/.env
   ```

3. **Access the App:**
   * Frontend: `http://localhost:80`
   * Backend API: `http://localhost:8000/api` (direct to PHP-FPM/Nginx backend)
   * Health endpoint: `http://localhost:8000/api/health`
   * **Note on ports:** Direct API calls use port `8000`. E2E tests proxy through the frontend container on port `80` (`http://localhost:80/api`). Both routes reach the same backend.

4. **Stop the Application:**
   ```bash
   docker compose down -v
   ```

## Testing

All unit, integration, and E2E tests are executed via a single, standardized shell script. This script automatically handles required test execution steps.

Make sure the script is executable, then run it:

```bash
chmod +x run_tests.sh
./run_tests.sh
```

*Note: The `run_tests.sh` script returns standard exit codes (`0` for success, non-zero for failure), which is suitable for CI/CD validators.*

## Seeded Credentials

The database is pre-seeded with the following test users on startup. Use these credentials to verify authentication and role-based access controls.

| Role | Identifier | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `Admin@NexusCare1` | Full access to all modules and admin functions. |
| **Staff** | `staff1` | `Staff@NexusCare1` | Operational staff permissions (appointments/payments/waitlist). |
| **Reviewer** | `reviewer1` | `Reviewer@NexusCare1` | Review and approval permissions (waivers/reconciliation/reports). |

## Verification

Use these steps to confirm the stack is healthy after starting the containers.

**Health check:**
```bash
curl http://localhost:8000/api/health
# Expected: {"status":"ok"}
```

**Authenticated endpoint (admin users list):**
```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Client-Type: api" \
  -d '{"identifier":"admin","password":"Admin@NexusCare1"}' | jq -r '.data.access_token')
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/admin/users
```

**Minimal UI walkthrough:**
Open `http://localhost:80`, log in as `admin` / `Admin@NexusCare1`, then navigate to **User Management** to confirm role-based access is working.

## Optional E2E Environment Variables

> **Note:** Frontend unit tests (Vitest) and E2E tests (Playwright) both run on the host and require `npm` and Node.js. The `run_tests.sh` script installs dependencies automatically when `npm` is available. The frontend production container is an nginx image and does not include a Node.js runtime.

If you run E2E tests manually, set:

```bash
export E2E_ADMIN_USER=admin
export E2E_ADMIN_PASS=Admin@NexusCare1
export E2E_STAFF_USER=staff1
export E2E_STAFF_PASS=Staff@NexusCare1
export E2E_REVIEWER_USER=reviewer1
export E2E_REVIEWER_PASS=Reviewer@NexusCare1
```
