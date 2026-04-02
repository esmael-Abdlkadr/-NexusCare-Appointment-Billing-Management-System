# Design Document — TASK-NEXUSCARE-DUMMY
# NexusCare Appointment & Billing Management System

---

## 1. Overall Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  Browser (Vue 3 + Vite)                  │
│         Staff │ Reviewer │ Administrator                 │
└────────────────────────┬────────────────────────────────┘
                         │ HTTP / REST JSON (local network)
┌────────────────────────▼────────────────────────────────┐
│              Laravel (PHP 8.2)                           │
│  Routes → Controllers → Services → Eloquent ORM         │
│  JWT Auth │ RBAC Policies │ Queued Jobs │ Scheduler      │
└────────────────────────┬────────────────────────────────┘
                         │
        ┌────────────────┼──────────────────┐
        ▼                ▼                  ▼
     MySQL 8         /storage/uploads/   /storage/backups/
   (local volume)    (files, invoices)   (mysqldump + files)
```

### 1.1 Technology Stack

| Layer         | Technology                                  | Version  |
|---------------|---------------------------------------------|----------|
| Frontend      | Vue 3 + Vite + Element Plus                 | Vue 3.4  |
| State Mgmt    | Pinia                                       | 2.x      |
| HTTP Client   | Axios                                       | 1.x      |
| Backend       | PHP + Laravel                               | 8.2 / 11 |
| ORM           | Eloquent                                    | Laravel  |
| Auth          | tymon/jwt-auth (JWT HS256)                  | 2.x      |
| DB            | MySQL                                       | 8.0      |
| Migrations    | Laravel Migrations                          | built-in |
| Scheduler     | Laravel Task Scheduler (Artisan cron)       | built-in |
| Queues        | Laravel Queues (database driver)            | built-in |
| Encryption    | Laravel Encryption (AES-256-CBC)            | built-in |
| Container     | Docker + Docker Compose                     | latest   |

---

## 2. Project Directory Structure

```
TASK-NEXUSCARE-DUMMY/
├── full_stack/
│   ├── docker-compose.yml
│   ├── .env
│   ├── backend/                        ← Laravel app
│   │   ├── Dockerfile
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   ├── AuthController.php
│   │   │   │   │   ├── AppointmentController.php
│   │   │   │   │   ├── WaitlistController.php
│   │   │   │   │   ├── PaymentController.php
│   │   │   │   │   ├── ReconciliationController.php
│   │   │   │   │   ├── FeeController.php
│   │   │   │   │   ├── UserController.php
│   │   │   │   │   ├── ReportController.php
│   │   │   │   │   └── AuditController.php
│   │   │   │   ├── Middleware/
│   │   │   │   │   ├── JwtAuth.php
│   │   │   │   │   ├── CheckMuted.php
│   │   │   │   │   ├── ScopeCheck.php
│   │   │   │   │   └── AuditLogger.php
│   │   │   │   └── Requests/
│   │   │   ├── Models/
│   │   │   │   ├── User.php
│   │   │   │   ├── Appointment.php
│   │   │   │   ├── AppointmentVersion.php
│   │   │   │   ├── Resource.php
│   │   │   │   ├── Waitlist.php
│   │   │   │   ├── Payment.php
│   │   │   │   ├── LedgerEntry.php
│   │   │   │   ├── FeeAssessment.php
│   │   │   │   ├── FeeRule.php
│   │   │   │   ├── SettlementImport.php
│   │   │   │   ├── ReconciliationException.php
│   │   │   │   ├── AnomalyAlert.php
│   │   │   │   └── AuditLog.php
│   │   │   ├── Policies/
│   │   │   │   ├── AppointmentPolicy.php
│   │   │   │   ├── PaymentPolicy.php
│   │   │   │   └── UserPolicy.php
│   │   │   ├── Services/
│   │   │   │   ├── AuthService.php
│   │   │   │   ├── AppointmentService.php
│   │   │   │   ├── WaitlistService.php
│   │   │   │   ├── FeeService.php
│   │   │   │   ├── PaymentService.php
│   │   │   │   ├── ReconciliationService.php
│   │   │   │   ├── SyncService.php
│   │   │   │   ├── MaskingService.php
│   │   │   │   └── ReportService.php
│   │   │   └── Console/
│   │   │       ├── Commands/
│   │   │       │   ├── AssessNoShowFees.php
│   │   │       │   ├── AssessOverdueFines.php
│   │   │       │   ├── RunIncrementalSync.php
│   │   │       │   └── PurgeExpiredRecords.php
│   │   │       └── Kernel.php
│   │   ├── database/
│   │   │   ├── migrations/
│   │   │   └── seeders/
│   │   ├── routes/api.php
│   │   └── tests/
│   │       ├── Feature/
│   │       └── Unit/
│   └── frontend/                       ← Vue.js app
│       ├── Dockerfile
│       ├── src/
│       │   ├── views/
│       │   │   ├── auth/LoginView.vue
│       │   │   ├── staff/
│       │   │   │   ├── AppointmentCreate.vue
│       │   │   │   ├── AppointmentList.vue
│       │   │   │   ├── WaitlistView.vue
│       │   │   │   └── PaymentPost.vue
│       │   │   ├── reviewer/
│       │   │   │   ├── WaiverApproval.vue
│       │   │   │   └── ReconciliationExceptions.vue
│       │   │   └── admin/
│       │   │       ├── UserManagement.vue
│       │   │       ├── FeeRules.vue
│       │   │       ├── RecycleView.vue
│       │   │       └── AuditLogs.vue
│       │   ├── components/
│       │   │   ├── AppHeader.vue
│       │   │   ├── ConflictAlert.vue
│       │   │   ├── StatusBadge.vue
│       │   │   └── ConfirmModal.vue
│       │   ├── stores/
│       │   │   ├── auth.js
│       │   │   ├── appointments.js
│       │   │   └── billing.js
│       │   └── router/index.js
│       └── vite.config.js
├── prompt.md
├── questions.md
└── docs/
    ├── design.md
    └── api-spec.md
```

---

## 3. Database Schema

### 3.1 Core Tables

**roles**
| Column       | Type         | Notes                              |
|--------------|--------------|------------------------------------|
| id           | bigint PK    |                                    |
| name         | varchar(50)  | staff, reviewer, administrator     |
| display_name | varchar(100) |                                    |

**permissions**
| Column      | Type         | Notes                                       |
|-------------|--------------|---------------------------------------------|
| id          | bigint PK    |                                             |
| name        | varchar(100) | e.g. appointment.create, waiver.approve     |
| description | varchar(255) |                                             |

**role_permissions** (pivot): `role_id` FK, `permission_id` FK

**user_roles** (pivot): `user_id` FK, `role_id` FK, `site_id` FK (nullable scope override)

**users**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| identifier     | varchar(100)  | employee ID / student ID / username, unique    |
| password_hash  | varchar(255)  | bcrypt/argon2 hashed                           |
| role           | enum          | staff, reviewer, administrator                 |
| site_id        | bigint FK     | scoped to site                                 |
| department_id  | bigint FK     | scoped to department                           |
| is_banned      | boolean       | default false                                  |
| muted_until    | timestamp     | null = not muted                               |
| locked_until   | timestamp     | null = not locked                              |
| failed_attempts| int           | reset on success                               |
| deleted_at     | timestamp     | soft delete                                    |

**appointments**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| client_id      | bigint FK     | users                                          |
| provider_id    | bigint FK     | users (staff/provider)                         |
| resource_id    | bigint FK     | resources                                      |
| service_type   | varchar(100)  |                                                |
| start_time     | datetime      |                                                |
| end_time       | datetime      |                                                |
| status         | enum          | requested, confirmed, checked_in, no_show, completed, cancelled |
| cancel_reason  | text          | nullable                                       |
| site_id        | bigint FK     |                                                |
| deleted_at     | timestamp     | soft delete                                    |

**appointment_versions**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| appointment_id | bigint FK     |                                                |
| snapshot       | json          | full appointment state at time of change       |
| changed_by     | bigint FK     | users                                          |
| created_at     | timestamp     |                                                |

**waitlist**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| client_id      | bigint FK     |                                                |
| service_type   | varchar(100)  |                                                |
| priority       | int           | lower = higher priority                        |
| preferred_start| datetime      |                                                |
| preferred_end  | datetime      |                                                |
| status         | enum          | waiting, proposed, booked, expired             |
| site_id        | bigint FK     |                                                |

**fee_rules**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| fee_type       | enum          | no_show, overdue, lost_damaged                 |
| amount         | decimal(10,2) | base amount                                    |
| rate           | decimal(5,4)  | for percentage-based (overdue = 0.015)         |
| period_days    | int           | for overdue: 30                                |
| grace_minutes  | int           | for no_show: 10                                |
| site_id        | bigint FK     | per-site config                                |

**fee_assessments**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| appointment_id | bigint FK     | nullable (overdue not tied to appointment)     |
| client_id      | bigint FK     |                                                |
| fee_type       | enum          |                                                |
| amount         | decimal(10,2) |                                                |
| status         | enum          | pending, paid, waived, written_off             |
| waiver_by      | bigint FK     | reviewer who approved waiver                   |
| waiver_note    | text          |                                                |

**payments**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| reference_id   | varchar(100)  | order/transaction reference                    |
| amount         | decimal(10,2) |                                                |
| method         | enum          | cash, check, terminal_batch                    |
| posted_by      | bigint FK     | staff user                                     |
| site_id        | bigint FK     |                                                |
| created_at     | timestamp     |                                                |

**refund_orders**
| Column       | Type          | Notes                                        |
|--------------|---------------|----------------------------------------------|
| id           | bigint PK     |                                              |
| payment_id   | bigint FK     | payments                                     |
| client_id    | bigint FK     | users                                        |
| amount       | decimal(10,2) | cannot exceed original payment amount        |
| reason       | text          |                                              |
| status       | enum          | pending, approved, rejected, processed       |
| requested_by | bigint FK     | users                                        |
| approved_by  | bigint FK     | users, nullable                              |
| site_id      | bigint FK     |                                              |
| created_at   | timestamp     |                                              |
| updated_at   | timestamp     |                                              |

**settlement_imports**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| filename       | varchar(255)  |                                                |
| file_hash      | char(64)      | SHA-256, unique (replay prevention)            |
| imported_by    | bigint FK     |                                                |
| row_count      | int           |                                                |
| matched_count  | int           |                                                |
| discrepancy_count | int        |                                                |
| created_at     | timestamp     |                                                |

**audit_logs**
| Column         | Type          | Notes                                          |
|----------------|---------------|------------------------------------------------|
| id             | bigint PK     |                                                |
| user_id        | bigint FK     | actor                                          |
| action         | varchar(100)  | LOGIN, CREATE_APPOINTMENT, RESET_PASSWORD, etc |
| target_type    | varchar(100)  | model name                                     |
| target_id      | bigint        |                                                |
| payload        | json          | before/after snapshot                          |
| ip_address     | varchar(45)   |                                                |
| created_at     | timestamp     | immutable — no updated_at, no soft delete      |

---

## 4. Authentication & Security Design

### 4.1 JWT Token Flow
- `POST /api/auth/login` returns `access_token` (JWT, HS256)
- Token payload: `{ sub: user_id, role, site_id, department_id, iat, exp }`
- Absolute expiry: `iat + 12h` embedded in token
- Idle timeout: tracked server-side in `user_sessions` table (`last_active_at`)
- Every API request updates `last_active_at`. If `now - last_active_at > 30min` → 401 `SESSION_IDLE_TIMEOUT`

### 4.2 Password Policy
- Minimum 12 characters
- Must contain: uppercase, lowercase, digit, special character
- Hashed with bcrypt (cost factor 12) or Argon2id
- No plaintext storage at any layer

### 4.3 Account Lockout
- 5 failed attempts → locked for 15 minutes
- Tracked in `login_attempts` table (identifier, attempted_at)
- `locked_until` stored on `users` table
- Admin can unlock manually via `POST /api/admin/users/{id}/unlock`

### 4.4 Sensitive Field Encryption
- Government IDs encrypted at rest using Laravel's `Crypt` facade (AES-256-CBC)
- `encrypted_*` prefix on column names for clarity
- Decryption only in service layer; never in raw queries
- Masking applied on API response by `MaskingService`

---

## 5. RBAC & Data Scoping

### 5.1 Role Permissions Matrix

| Feature                      | Staff | Reviewer | Administrator |
|------------------------------|-------|----------|---------------|
| Create/edit appointments     | own site | — | all sites |
| View appointments            | own site | own site | all sites |
| Waitlist management          | own site | — | all sites |
| Post offline payments        | own site | — | all sites |
| Approve waiver/write-off     | — | own site | all sites |
| Reconciliation import        | — | own site | all sites |
| View audit logs              | — | own site | all sites |
| User management (ban/mute)   | — | — | all sites |
| Configure fee rules          | — | — | all sites |
| Export reports               | — | own site | all sites |

### 5.2 Data Scope Enforcement
- `ScopeCheck` middleware attaches `site_id` and `department_id` from JWT to every request
- All Eloquent queries scoped with `->where('site_id', $request->site_id)` via global scopes
- Policies (`AppointmentPolicy`, `PaymentPolicy`) enforce object-level ownership

---

## 6. Appointment Lifecycle

```
requested → confirmed → checked_in → completed
                     ↘               ↗
                      no_show
          ↘
           cancelled (any stage before completed)
```

- **Conflict detection**: checks provider + resource availability for the requested time window
- **Next-available suggestion**: queries next 3 open slots for the same provider+resource pair
- **Version snapshot**: every status change writes a snapshot to `appointment_versions`

---

## 7. Financial Reconciliation Flow

```
CSV Import → SHA-256 fingerprint check → Parse rows
    → Match each row against payments table (by reference_id + amount)
    → Flag unmatched rows as discrepancies
    → Compute daily variance (expected vs actual)
    → If |variance| > $50 → insert anomaly_alert
    → Reviewer resolves exceptions via exception workflow
```

---

## 8. Scheduled Jobs (Laravel Scheduler)

| Job                    | Schedule       | Purpose                                       |
|------------------------|----------------|-----------------------------------------------|
| AssessNoShowFees       | Every minute   | Mark no-shows + create fee records after grace|
| AssessOverdueFines     | Daily 00:05    | Recalculate overdue fines on outstanding balances |
| RunIncrementalSync     | Every 15 min   | Diff/fingerprint sync between sites           |
| PurgeExpiredRecords    | Monthly        | Hard delete soft-deleted records > 24 months  |

---

## 9. Docker Services

| Service   | Image         | Port  | Notes                          |
|-----------|---------------|-------|--------------------------------|
| mysql     | mysql:8.0     | 3306  | persistent named volume        |
| backend   | php:8.2-fpm   | 8000  | depends_on mysql (healthcheck) |
| frontend  | node/nginx    | 80    | Vite build, proxies /api → backend |
