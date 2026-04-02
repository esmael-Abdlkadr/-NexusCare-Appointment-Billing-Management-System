# API Specification — TASK-NEXUSCARE-DUMMY
# NexusCare Appointment & Billing Management System

**Base URL**: `http://localhost:8000/api`
**Auth**: JWT Bearer Token in `Authorization: Bearer <token>` header (except login)
**Content-Type**: `application/json` (file uploads use `multipart/form-data`)

---

## Common Response Envelope

```json
{
  "success": true,
  "data": { ... },
  "message": "OK"
}
```

Error response:
```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Human-readable description",
  "details": { ... }
}
```

### Common Error Codes

| Code                        | HTTP Status | Description                                     |
|-----------------------------|-------------|-------------------------------------------------|
| UNAUTHORIZED                | 401         | Missing or invalid JWT token                    |
| SESSION_IDLE_TIMEOUT        | 401         | No activity for 30 minutes                      |
| SESSION_EXPIRED             | 401         | 12-hour absolute token expiry                   |
| FORBIDDEN                   | 403         | Role not permitted for this action              |
| SCOPE_VIOLATION             | 403         | Resource belongs to a different site            |
| ACCOUNT_LOCKED              | 423         | Locked for 15 min after 5 failed attempts       |
| ACCOUNT_BANNED              | 403         | Account permanently banned                      |
| ACCOUNT_MUTED               | 403         | Account muted, write actions blocked            |
| NOT_FOUND                   | 404         | Resource does not exist                         |
| VALIDATION_ERROR            | 422         | Request body/query param invalid                |
| PASSWORD_COMPLEXITY_VIOLATION| 422        | Password does not meet complexity rules         |
| CONFLICT_PROVIDER           | 409         | Provider already booked at this time            |
| CONFLICT_RESOURCE           | 409         | Room/resource already booked at this time       |
| DUPLICATE_SETTLEMENT_FILE   | 409         | Settlement file already imported (replay)       |
| WAIVER_NOT_PERMITTED        | 403         | Reviewer not scoped to this site                |

---

## Group 1: Authentication

### POST /api/auth/login
Login with identifier and password.

**Request:**
```json
{
  "identifier": "emp_12345",
  "password": "SecurePass123!"
}
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 43200,
    "user": {
      "id": 1,
      "identifier": "emp_12345",
      "role": "staff",
      "site_id": 2,
      "department_id": 3
    }
  },
  "message": ""
}
```

**Errors:** 401 `UNAUTHORIZED`, 423 `ACCOUNT_LOCKED`, 403 `ACCOUNT_BANNED`

---

### POST /api/auth/logout
Invalidate the current session.

**Auth required**: Yes

**Response 200:**
```json
{ "success": true, "data": null, "message": "Logged out" }
```

---

### GET /api/auth/me
Get current authenticated user details.

**Auth required**: Yes

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "identifier": "emp_12345",
    "role": "staff",
    "site_id": 2,
    "department_id": 3,
    "muted_until": null
  }
}
```

---

## Group 2: Appointments

### GET /api/appointments
List appointments for the current user's site.

**Auth required**: Yes (Staff, Reviewer, Administrator)
**Query params**: `status`, `date`, `provider_id`, `page`, `per_page`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 10,
        "client_id": 5,
        "provider_id": 3,
        "resource_id": 2,
        "service_type": "consultation",
        "start_time": "2026-03-27T09:00:00",
        "end_time": "2026-03-27T09:30:00",
        "status": "confirmed",
        "site_id": 2
      }
    ],
    "total": 45,
    "page": 1,
    "per_page": 20
  }
}
```

---

### POST /api/appointments
Create a new appointment with conflict detection.

**Auth required**: Yes (Staff, Administrator)

**Request:**
```json
{
  "client_id": 5,
  "provider_id": 3,
  "resource_id": 2,
  "service_type": "consultation",
  "start_time": "2026-03-27T09:00:00",
  "end_time": "2026-03-27T09:30:00"
}
```

**Response 201:**
```json
{
  "success": true,
  "data": { "id": 11, "status": "requested", ... }
}
```

**Errors:** 409 `CONFLICT_PROVIDER`, 409 `CONFLICT_RESOURCE` with `next_available_slots` array

---

### PUT /api/appointments/{id}
Update appointment (reschedule). Captures reason if status change to cancelled.

**Auth required**: Yes (Staff, Administrator)

**Request:**
```json
{
  "start_time": "2026-03-27T10:00:00",
  "end_time": "2026-03-27T10:30:00",
  "cancel_reason": "Client request"
}
```

---

### PATCH /api/appointments/{id}/status
Transition appointment status.

**Auth required**: Yes (Staff, Administrator)

**Request:**
```json
{
  "status": "checked_in"
}
```

**Valid transitions:**
- `requested → confirmed` (Staff)
- `confirmed → checked_in` (Staff)
- `confirmed → no_show` (system/Staff)
- `checked_in → completed` (Staff)
- Any → `cancelled` (Staff with reason)

---

### GET /api/appointments/{id}/versions
Get the version history of an appointment.

**Auth required**: Yes (Reviewer, Administrator)

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "version": 1,
      "snapshot": { ... },
      "changed_by": 3,
      "created_at": "2026-03-25T08:00:00"
    }
  ]
}
```

---

## Group 3: Waitlist

### GET /api/waitlist
List waitlist entries for the current site.

**Auth required**: Yes (Staff, Administrator)

---

### POST /api/waitlist
Add a client to the waitlist.

**Request:**
```json
{
  "client_id": 7,
  "service_type": "consultation",
  "priority": 1,
  "preferred_start": "2026-03-27T08:00:00",
  "preferred_end": "2026-03-27T17:00:00"
}
```

---

### POST /api/waitlist/{id}/confirm-backfill
Staff confirms a proposed backfill slot, creating the appointment.

**Auth required**: Yes (Staff)

**Request:**
```json
{
  "appointment_slot": {
    "start_time": "2026-03-27T09:00:00",
    "end_time": "2026-03-27T09:30:00",
    "provider_id": 3,
    "resource_id": 2
  }
}
```

---

## Group 4: Fees & Payments

### GET /api/fee-rules
List configured fee rules for the current site.

**Auth required**: Yes (Administrator)

---

### POST /api/fee-rules
Create or update a fee rule.

**Auth required**: Yes (Administrator)

**Request:**
```json
{
  "fee_type": "no_show",
  "amount": 25.00,
  "grace_minutes": 10
}
```

---

### GET /api/fee-assessments
List fee assessments for the current site.

**Auth required**: Yes (Staff, Reviewer, Administrator)
**Query params**: `client_id`, `status`, `fee_type`

---

### POST /api/fee-assessments/{id}/waiver
Request a waiver or write-off (Reviewer approves).

**Auth required**: Yes (Reviewer, Administrator)

**Request:**
```json
{
  "waiver_type": "waived",
  "waiver_note": "Client hardship approved"
}
```

---

### POST /api/payments
Post an offline payment.

**Auth required**: Yes (Staff, Administrator)

**Request:**
```json
{
  "reference_id": "CHK-20260327-001",
  "amount": 75.00,
  "method": "check",
  "fee_assessment_id": 12
}
```

---

## Group 5: Reconciliation

### POST /api/reconciliation/import
Import a settlement CSV file.

**Auth required**: Yes (Reviewer, Administrator)
**Content-Type**: `multipart/form-data`

**Form fields**: `file` (CSV)

**Response 200:**
```json
{
  "success": true,
  "data": {
    "import_id": 3,
    "row_count": 120,
    "matched_count": 117,
    "discrepancy_count": 3,
    "daily_variance": 72.50,
    "anomaly_alert": true
  }
}
```

**Errors:** 409 `DUPLICATE_SETTLEMENT_FILE`

---

### GET /api/reconciliation/exceptions
List unresolved reconciliation discrepancies.

**Auth required**: Yes (Reviewer, Administrator)

---

### PATCH /api/reconciliation/exceptions/{id}/resolve
Mark a discrepancy as resolved with a note.

**Auth required**: Yes (Reviewer, Administrator)

**Request:**
```json
{
  "resolution_note": "Confirmed terminal error, adjusted manually"
}
```

---

## Group 6: User Management (Admin)

### GET /api/admin/users
List all users (scoped to admin's site unless super-admin).

**Auth required**: Yes (Administrator)

---

### POST /api/admin/users
Create a new user.

**Auth required**: Yes (Administrator)

**Request:**
```json
{
  "identifier": "emp_99001",
  "password": "SecurePass123!",
  "role": "staff",
  "site_id": 2,
  "department_id": 3
}
```

---

### PATCH /api/admin/users/{id}
Update user role, ban, or mute status.

**Auth required**: Yes (Administrator)

**Request:**
```json
{
  "role": "reviewer",
  "is_banned": false,
  "muted_until": "2026-03-28T10:00:00"
}
```

---

### POST /api/admin/users/{id}/reset-password
Admin-assisted offline password reset.

**Auth required**: Yes (Administrator)

**Request:**
```json
{
  "new_password": "NewSecurePass456!",
  "verification_note": "Identity verified via employee ID card"
}
```

---

### DELETE /api/admin/users/{id}
Soft delete a user (moves to recycle bin).

**Auth required**: Yes (Administrator)

---

### GET /api/admin/recycle-bin
List soft-deleted records across all entity types.

**Auth required**: Yes (Administrator)

---

### POST /api/admin/recycle-bin/{type}/{id}/restore
Restore a soft-deleted record.

**Auth required**: Yes (Administrator)

---

## Group 7: Audit Logs

### GET /api/audit-logs
List audit log entries.

**Auth required**: Yes (Reviewer, Administrator)
**Query params**: `user_id`, `action`, `target_type`, `from`, `to`, `page`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 501,
        "user_id": 3,
        "action": "CREATE_APPOINTMENT",
        "target_type": "appointment",
        "target_id": 11,
        "payload": { ... },
        "ip_address": "192.168.1.10",
        "created_at": "2026-03-27T09:01:00"
      }
    ],
    "total": 1203
  }
}
```

---

## Group 8: Reports

### GET /api/reports/appointments
Export appointment summary report.

**Auth required**: Yes (Reviewer, Administrator)
**Query params**: `from`, `to`, `site_id`, `format` (csv|xlsx)

---

### GET /api/reports/financial
Export financial reconciliation report.

**Auth required**: Yes (Reviewer, Administrator)
**Query params**: `from`, `to`, `site_id`, `format`

---

### GET /api/reports/audit
Export compliance audit report.

**Auth required**: Yes (Administrator)
**Query params**: `from`, `to`, `format`

---

## Group 9: Health

### GET /api/health
System health check.

**Auth required**: No

**Response 200:**
```json
{
  "success": true,
  "data": { "status": "ok", "db": "connected" },
  "message": ""
}
```
