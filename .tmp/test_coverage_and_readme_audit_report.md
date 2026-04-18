# Test Coverage Audit

## Project Type Detection
- README top does **not** explicitly declare one of the required canonical labels (`backend`, `fullstack`, `web`, `android`, `ios`, `desktop`).
- Inferred type by light inspection: **fullstack** (Vue frontend at `repo/frontend`, Laravel API at `repo/backend`, E2E at `repo/e2e`).

## Backend Endpoint Inventory

1. `GET /api/health`
2. `POST /api/auth/login`
3. `POST /api/auth/logout`
4. `GET /api/auth/me`
5. `POST /api/admin/users/{user}/reset-password`
6. `GET /api/admin/users`
7. `POST /api/admin/users`
8. `GET /api/admin/users/{id}`
9. `PATCH /api/admin/users/{id}`
10. `DELETE /api/admin/users/{id}`
11. `POST /api/admin/users/bulk`
12. `POST /api/admin/users/{id}/unlock`
13. `GET /api/admin/recycle-bin`
14. `POST /api/admin/recycle-bin/{type}/{id}/restore`
15. `DELETE /api/admin/recycle-bin/{type}/{id}`
16. `POST /api/admin/recycle-bin/bulk-restore`
17. `DELETE /api/admin/recycle-bin/bulk`
18. `GET /api/appointments`
19. `POST /api/appointments`
20. `GET /api/appointments/{id}`
21. `PUT /api/appointments/{id}`
22. `PATCH /api/appointments/{id}`
23. `PATCH /api/appointments/{id}/status`
24. `GET /api/appointments/{id}/versions`
25. `GET /api/resources`
26. `GET /api/users/search`
27. `GET /api/waitlist`
28. `POST /api/waitlist`
29. `POST /api/waitlist/{id}/confirm-backfill`
30. `DELETE /api/waitlist/{id}`
31. `GET /api/fee-rules`
32. `POST /api/fee-rules`
33. `DELETE /api/fee-rules/{id}`
34. `GET /api/fee-assessments`
35. `POST /api/fee-assessments`
36. `GET /api/fee-assessments/{id}`
37. `POST /api/fee-assessments/{id}/waiver`
38. `GET /api/fees`
39. `POST /api/fees/{id}/write-off`
40. `GET /api/payments`
41. `POST /api/payments`
42. `GET /api/refund-orders`
43. `POST /api/refund-orders`
44. `PATCH /api/refund-orders/{id}/approve`
45. `GET /api/ledger`
46. `POST /api/reconciliation/import`
47. `GET /api/reconciliation/imports`
48. `GET /api/reconciliation/exceptions`
49. `PATCH /api/reconciliation/exceptions/{id}/resolve`
50. `GET /api/reconciliation/anomalies`
51. `PATCH /api/reconciliation/anomalies/{id}/acknowledge`
52. `GET /api/audit-logs`
53. `GET /api/reports/appointments`
54. `GET /api/reports/financial`
55. `GET /api/reports/audit`

## API Test Mapping Table

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `GET /api/health` | yes | true no-mock HTTP | `backend/tests/Feature/HealthTest.php` | `backend/tests/Feature/HealthTest.php::test_health_check_returns_ok` |
| `POST /api/auth/login` | yes | true no-mock HTTP | `backend/tests/Feature/AuthTest.php`, `backend/tests/Feature/AdminTest.php` | `backend/tests/Feature/AuthTest.php::test_login_success` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `backend/tests/Feature/AuthTest.php` | `backend/tests/Feature/AuthTest.php::test_logout_invalidates_session` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `backend/tests/Feature/AuthTest.php`, `backend/tests/Feature/SecurityIsolationTest.php` | `backend/tests/Feature/AuthTest.php::test_idle_timeout` |
| `POST /api/admin/users/{user}/reset-password` | yes | true no-mock HTTP | `backend/tests/Feature/AdminUserManagementTest.php` | `backend/tests/Feature/AdminUserManagementTest.php::test_admin_can_reset_password` |
| `GET /api/admin/users` | yes | true no-mock HTTP | `backend/tests/Feature/AdminTest.php`, `backend/tests/Feature/AdminUserManagementTest.php` | `backend/tests/Feature/AdminTest.php::test_government_id_masked_for_reviewer` |
| `POST /api/admin/users` | yes | true no-mock HTTP | `backend/tests/Feature/AdminTest.php` | `backend/tests/Feature/AdminTest.php::test_admin_can_create_user_with_department` |
| `GET /api/admin/users/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/AdminTest.php` | `backend/tests/Feature/AdminTest.php::test_government_id_unmasked_for_admin` |
| `PATCH /api/admin/users/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/AdminTest.php` | `backend/tests/Feature/AdminTest.php::test_admin_can_ban_user` |
| `DELETE /api/admin/users/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/RecycleBinTest.php`, `backend/tests/Feature/AdminUserManagementTest.php` | `backend/tests/Feature/RecycleBinTest.php::test_admin_can_restore_soft_deleted_user` |
| `POST /api/admin/users/bulk` | yes | true no-mock HTTP | `backend/tests/Feature/AdminUserManagementTest.php` | `backend/tests/Feature/AdminUserManagementTest.php::test_bulk_ban` |
| `POST /api/admin/users/{id}/unlock` | yes | true no-mock HTTP | `backend/tests/Feature/AdminUserManagementTest.php` | `backend/tests/Feature/AdminUserManagementTest.php::test_admin_can_unlock_user` |
| `GET /api/admin/recycle-bin` | yes | true no-mock HTTP | `backend/tests/Feature/RecycleBinTest.php` | `backend/tests/Feature/RecycleBinTest.php::test_admin_can_restore_soft_deleted_user` |
| `POST /api/admin/recycle-bin/{type}/{id}/restore` | yes | true no-mock HTTP | `backend/tests/Feature/RecycleBinTest.php`, `backend/tests/Feature/AdminTest.php` | `backend/tests/Feature/RecycleBinTest.php::test_admin_can_restore_soft_deleted_user` |
| `DELETE /api/admin/recycle-bin/{type}/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/RecycleBinTest.php` | `backend/tests/Feature/RecycleBinTest.php::test_admin_can_force_delete_from_recycle_bin` |
| `POST /api/admin/recycle-bin/bulk-restore` | yes | true no-mock HTTP | `backend/tests/Feature/RecycleBinTest.php` | `backend/tests/Feature/RecycleBinTest.php::test_admin_can_bulk_restore_multiple_users` |
| `DELETE /api/admin/recycle-bin/bulk` | no | unit-only / indirect | - | - |
| `GET /api/appointments` | yes | true no-mock HTTP | `backend/tests/Feature/AppointmentCrudTest.php`, `backend/tests/Feature/AppointmentTest.php` | `backend/tests/Feature/AppointmentCrudTest.php::test_staff_can_list_appointments` |
| `POST /api/appointments` | yes | true no-mock HTTP | `backend/tests/Feature/AppointmentCrudTest.php`, `backend/tests/Feature/AppointmentTest.php` | `backend/tests/Feature/AppointmentCrudTest.php::test_full_appointment_lifecycle_requested_to_completed` |
| `GET /api/appointments/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/AppointmentCrudTest.php` | `backend/tests/Feature/AppointmentCrudTest.php::test_staff_can_view_appointment` |
| `PUT /api/appointments/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/AppointmentCrudTest.php` | `backend/tests/Feature/AppointmentCrudTest.php::test_staff_can_update_appointment_schedule` |
| `PATCH /api/appointments/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/AppointmentCrossSiteTest.php` | `backend/tests/Feature/AppointmentCrossSiteTest.php::test_staff_cannot_patch_cross_site_appointment` |
| `PATCH /api/appointments/{id}/status` | yes | true no-mock HTTP | `backend/tests/Feature/WaitlistTest.php`, `backend/tests/Feature/AppointmentTest.php` | `backend/tests/Feature/WaitlistTest.php::test_backfill_proposed_on_cancellation` |
| `GET /api/appointments/{id}/versions` | yes | true no-mock HTTP | `backend/tests/Feature/AppointmentCrudTest.php` | `backend/tests/Feature/AppointmentCrudTest.php::test_reviewer_can_list_appointment_versions` |
| `GET /api/resources` | yes | true no-mock HTTP | `backend/tests/Feature/ResourceTest.php`, `e2e/tests/02-appointments.spec.ts` | `backend/tests/Feature/ResourceTest.php::test_any_authenticated_user_can_list_resources` |
| `GET /api/users/search` | yes | true no-mock HTTP | `backend/tests/Feature/UserSearchTest.php`, `backend/tests/Feature/SecurityIsolationTest.php` | `backend/tests/Feature/UserSearchTest.php::test_staff_can_search_users_by_identifier` |
| `GET /api/waitlist` | yes | true no-mock HTTP | `backend/tests/Feature/WaitlistTest.php` | `backend/tests/Feature/WaitlistTest.php::test_waitlist_index_returns_paginated_envelope` |
| `POST /api/waitlist` | yes | true no-mock HTTP | `backend/tests/Feature/WaitlistTest.php`, `e2e/tests/03-waitlist.spec.ts` | `backend/tests/Feature/WaitlistTest.php::test_add_to_waitlist` |
| `POST /api/waitlist/{id}/confirm-backfill` | yes | true no-mock HTTP | `backend/tests/Feature/WaitlistTest.php` | `backend/tests/Feature/WaitlistTest.php::test_confirm_backfill_creates_appointment` |
| `DELETE /api/waitlist/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/WaitlistDestroyTest.php` | `backend/tests/Feature/WaitlistDestroyTest.php::test_staff_can_remove_waitlist_entry` |
| `GET /api/fee-rules` | yes | true no-mock HTTP | `backend/tests/Feature/FeeRuleTest.php` | `backend/tests/Feature/FeeRuleTest.php::test_admin_can_list_fee_rules` |
| `POST /api/fee-rules` | yes | true no-mock HTTP | `backend/tests/Feature/FeeRuleTest.php` | `backend/tests/Feature/FeeRuleTest.php::test_admin_can_create_fee_rule` |
| `DELETE /api/fee-rules/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/FeeRuleTest.php` | `backend/tests/Feature/FeeRuleTest.php::test_admin_can_delete_fee_rule` |
| `GET /api/fee-assessments` | yes | true no-mock HTTP | `backend/tests/Feature/FeeAssessmentCrudTest.php`, `e2e/tests/04-fee-assessments.spec.ts` | `backend/tests/Feature/FeeAssessmentCrudTest.php::test_staff_can_list_fee_assessments` |
| `POST /api/fee-assessments` | yes | true no-mock HTTP | `backend/tests/Feature/BillingTest.php`, `backend/tests/Feature/LostDamagedFeeTest.php` | `backend/tests/Feature/LostDamagedFeeTest.php::test_staff_can_assess_lost_damaged_fee` |
| `GET /api/fee-assessments/{id}` | yes | true no-mock HTTP | `backend/tests/Feature/FeeAssessmentCrudTest.php` | `backend/tests/Feature/FeeAssessmentCrudTest.php::test_staff_can_view_fee_assessment` |
| `POST /api/fee-assessments/{id}/waiver` | yes | true no-mock HTTP | `backend/tests/Feature/BillingTest.php`, `backend/tests/Feature/FeeAssessmentCrudTest.php` | `backend/tests/Feature/BillingTest.php::test_reviewer_approves_waiver` |
| `GET /api/fees` | no | unit-only / indirect | - | - |
| `POST /api/fees/{id}/write-off` | yes | true no-mock HTTP | `backend/tests/Feature/FeeAssessmentCrudTest.php` | `backend/tests/Feature/FeeAssessmentCrudTest.php::test_reviewer_can_write_off_fee` |
| `GET /api/payments` | yes | true no-mock HTTP | `backend/tests/Feature/PaymentListTest.php`, `backend/tests/Feature/PaymentTest.php` | `backend/tests/Feature/PaymentListTest.php::test_list_payments_returns_paginated_data` |
| `POST /api/payments` | yes | true no-mock HTTP | `backend/tests/Feature/BillingTest.php`, `backend/tests/Feature/PaymentTest.php` | `backend/tests/Feature/BillingTest.php::test_post_payment_marks_fee_paid` |
| `GET /api/refund-orders` | yes | true no-mock HTTP | `backend/tests/Feature/RefundOrderTest.php` | `backend/tests/Feature/RefundOrderTest.php::test_staff_can_list_refund_orders` |
| `POST /api/refund-orders` | yes | true no-mock HTTP | `backend/tests/Feature/RefundOrderTest.php` | `backend/tests/Feature/RefundOrderTest.php::test_staff_can_create_refund_order` |
| `PATCH /api/refund-orders/{id}/approve` | yes | true no-mock HTTP | `backend/tests/Feature/RefundOrderTest.php` | `backend/tests/Feature/RefundOrderTest.php::test_reviewer_can_approve_refund_order` |
| `GET /api/ledger` | yes | true no-mock HTTP | `backend/tests/Feature/LedgerTest.php`, `e2e/tests/05-ledger.spec.ts` | `backend/tests/Feature/LedgerTest.php::test_admin_can_view_ledger` |
| `POST /api/reconciliation/import` | yes | HTTP with mocking | `backend/tests/Feature/ReconciliationTest.php`, `backend/tests/Feature/LoggingTest.php` | `backend/tests/Feature/ReconciliationTest.php::test_import_settlement_csv` |
| `GET /api/reconciliation/imports` | yes | true no-mock HTTP | `backend/tests/Feature/ReconciliationCrudTest.php` | `backend/tests/Feature/ReconciliationCrudTest.php::test_reviewer_can_list_imports` |
| `GET /api/reconciliation/exceptions` | no | unit-only / indirect | - | - |
| `PATCH /api/reconciliation/exceptions/{id}/resolve` | yes | true no-mock HTTP | `backend/tests/Feature/ReconciliationTest.php`, `backend/tests/Feature/SecurityIsolationTest.php` | `backend/tests/Feature/ReconciliationTest.php::test_reviewer_resolves_exception` |
| `GET /api/reconciliation/anomalies` | yes | true no-mock HTTP | `backend/tests/Feature/ReconciliationCrudTest.php` | `backend/tests/Feature/ReconciliationCrudTest.php::test_reviewer_can_list_anomalies` |
| `PATCH /api/reconciliation/anomalies/{id}/acknowledge` | yes | true no-mock HTTP | `backend/tests/Feature/ReconciliationCrudTest.php` | `backend/tests/Feature/ReconciliationCrudTest.php::test_reviewer_can_acknowledge_anomaly` |
| `GET /api/audit-logs` | yes | true no-mock HTTP | `backend/tests/Feature/AuditLogTest.php` | `backend/tests/Feature/AuditLogTest.php::test_reviewer_can_list_audit_logs` |
| `GET /api/reports/appointments` | yes | true no-mock HTTP | `backend/tests/Feature/ReportTest.php` | `backend/tests/Feature/ReportTest.php::test_reviewer_can_get_appointment_report` |
| `GET /api/reports/financial` | yes | true no-mock HTTP | `backend/tests/Feature/ReportTest.php`, `backend/tests/Feature/SecurityIsolationTest.php` | `backend/tests/Feature/ReportTest.php::test_reviewer_can_get_financial_report` |
| `GET /api/reports/audit` | yes | true no-mock HTTP | `backend/tests/Feature/ReportTest.php` | `backend/tests/Feature/ReportTest.php::test_admin_can_get_audit_report` |

## API Test Classification

### 1) True No-Mock HTTP
- Laravel feature tests using HTTP helpers against real routes (e.g., `backend/tests/Feature/AuthTest.php::test_logout_invalidates_session`, `backend/tests/Feature/AppointmentCrudTest.php::test_staff_can_update_appointment_schedule`).
- Playwright API/browser E2E calls through HTTP stack (e.g., `e2e/tests/15-appointment-lifecycle.spec.ts`, `e2e/tests/03-waitlist.spec.ts`).

### 2) HTTP with Mocking
- `backend/tests/Feature/LoggingTest.php` (`Log::shouldReceive(...)`), including HTTP tests for `/api/auth/login` and `/api/reconciliation/import`.
- `backend/tests/Feature/ReconciliationTest.php` (`Storage::fake('local')` in `csvFile()` helper), affecting import path tests.

### 3) Non-HTTP (unit/integration without HTTP)
- Backend unit tests in `backend/tests/Unit/*.php` (6 files).
- Frontend unit tests in `frontend/src/__tests__/*.js` (17 files, component/store/utils-level).

## Mock Detection
- **WHAT:** `Log` facade mocked via `shouldReceive`.
  - **WHERE:** `backend/tests/Feature/LoggingTest.php:52`, `backend/tests/Feature/LoggingTest.php:76`, `backend/tests/Feature/LoggingTest.php:103`, `backend/tests/Feature/LoggingTest.php:145`.
- **WHAT:** Filesystem/storage faked.
  - **WHERE:** `backend/tests/Feature/ReconciliationTest.php:325` (`Storage::fake('local')`).
- **WHAT:** Frontend service/router/http mocks (`vi.mock`, `vi.spyOn`).
  - **WHERE:** `frontend/src/__tests__/WaitlistView.test.js:15`, `frontend/src/__tests__/PaymentPost.test.js:12`, `frontend/src/__tests__/authStore.test.js:4`, `frontend/src/__tests__/Login.test.js:10`.

## Coverage Summary
- Total endpoints: **55**
- Endpoints with HTTP tests: **52**
- Endpoints with TRUE no-mock HTTP evidence: **51**
- HTTP coverage: **94.55%** (`52/55`)
- True API coverage: **92.73%** (`51/55`)
- Uncovered endpoints:
  - `DELETE /api/admin/recycle-bin/bulk`
  - `GET /api/fees`
  - `GET /api/reconciliation/exceptions`

## Unit Test Summary

### Backend Unit Tests
- Test files: `backend/tests/Unit/SyncServiceRuleTest.php`, `backend/tests/Unit/ConflictDetectionTest.php`, `backend/tests/Unit/FeeCalculationTest.php`, `backend/tests/Unit/MaskingTest.php`, `backend/tests/Unit/IdentifierFormatRuleTest.php`, `backend/tests/Unit/PasswordComplexityTest.php`.
- Modules covered (observed from file scope):
  - services/rules: fee calculation, sync rules, conflict logic
  - validation/security helpers: identifier/password/masking
- Important backend modules NOT unit-tested directly:
  - API controllers under `backend/app/Http/Controllers/Api/`
  - auth/guard middleware behavior at pure-unit level (`app.jwt`, `scope.check`, `check.muted`, `audit.logger` are exercised mainly via feature tests)
  - repository/data-access abstractions (no dedicated repository test layer found)

### Frontend Unit Tests (STRICT REQUIREMENT)
- Frontend test files: **PRESENT** in `frontend/src/__tests__/` (17 files).
- Frameworks/tools detected:
  - `vitest` (`import { ... } from 'vitest'`)
  - `@vue/test-utils` (`mount`, `flushPromises`)
  - `@pinia/testing`
- Components/modules covered (examples):
  - views: `WaitlistView.vue`, `Login.vue`, `AppointmentCreate.vue`, `AppointmentList.vue`, `PaymentPost.vue`, `ReportsView.vue`, `FeeRulesView.vue`, `ReconciliationExceptions.vue`
  - components: `UserCreateDialog.vue`, `UserResetPasswordDialog.vue`, `AppointmentCancelDialog.vue`
  - stores/utils/router/composables: `stores/auth`, `utils/logger`, `utils/maskPayload`, `router/index.js`, `composables/useNavSections`
- Important frontend components/modules NOT tested (direct evidence absent):
  - views: `AnomalyAlerts.vue`, `AppointmentVersions.vue`, `AuditLogs.vue`, `ContentModeration.vue`, `FeeAssessmentList.vue`, `ForbiddenView.vue`, `LedgerView.vue`, `ReconciliationImport.vue`, `RecycleBinView.vue`, `UserManagement.vue`
  - components: `AppointmentRescheduleDialog.vue`, `ConflictAlert.vue`, `AppLayout.vue` (only nav-section composable tested)

**Frontend unit tests: PRESENT**

### Cross-Layer Observation
- Testing is backend-heavy but not frontend-empty.
- Frontend has unit coverage, but coverage concentration is uneven: many high-impact admin/reporting/reconciliation views remain untested directly.

## API Observability Check
- Strong in most Laravel feature tests: method/path, request payload, and response assertions are explicit (e.g., `assertStatus`, `assertJsonStructure`, `assertJson`).
- Weak in some E2E UI tests where API effects are inferred through UI state only, without direct response-content assertions.

## Tests Check
- Success/failure coverage is broad (auth failures, role denials, invalid payloads, cross-site restrictions).
- Edge/validation coverage is present in multiple domains (pagination limits, duplicate references, invalid transitions).
- Integration boundaries are exercised through feature tests and E2E.
- `run_tests.sh` is **partially non-compliant** with strict Docker-only requirement:
  - Docker used for backend tests (`docker compose exec ... php artisan test`).
  - Local runtime dependency/install path exists for E2E (`npm ci`, `npx playwright install chromium`) at `run_tests.sh:51-60`.

## Test Coverage Score (0-100)
**83/100**

## Score Rationale
- High endpoint coverage and broad role/validation scenarios keep score elevated.
- Deduction for 3 uncovered endpoints, mock usage in parts of API-path tests, and uneven frontend unit distribution across critical views.
- Deduction for non-Docker local dependency path in standard test runner script.

## Key Gaps
1. No direct tests for `DELETE /api/admin/recycle-bin/bulk`.
2. No direct tests for `GET /api/fees`.
3. No direct tests for `GET /api/reconciliation/exceptions`.
4. Import-path tests include mocked logging/storage in key files (`LoggingTest`, `ReconciliationTest`).
5. Frontend missing direct unit coverage for multiple high-impact operational views.

## Confidence & Assumptions
- Confidence: **high** for route inventory and major coverage conclusions.
- Assumption: Laravel route base `/api` is active via `routes/api.php` conventions.
- Static-only limitation: no runtime execution, so unreachable/conditional route behavior is not validated dynamically.

---

# README Audit

## README Location
- Present at `repo/README.md`.

## Hard Gate Failures
1. **Startup instruction gate (Backend/Fullstack) FAIL**
   - Required literal command: `docker-compose up`
   - README uses `docker compose up --build -d` at `repo/README.md:38`.
2. **Verification method gate FAIL**
   - README does not provide explicit verification flow (no concrete curl/Postman request list and no deterministic UI walkthrough to confirm end-to-end correctness).
3. **Environment rules gate FAIL (strict Docker-contained policy)**
   - README’s prescribed test entrypoint (`./run_tests.sh`, `repo/README.md:64-67`) invokes local installs/runtime dependencies for E2E (`run_tests.sh:51-60`, `run_tests.sh:62`).

## High Priority Issues
- Missing canonical project-type declaration token at top (`backend/fullstack/web/android/ios/desktop`) despite fullstack content; this weakens strict parser compatibility.
- Verification section is insufficiently actionable for strict acceptance criteria.
- Startup wording does not include required `docker-compose up` literal.

## Medium Priority Issues
- API base URL inconsistency risk: README references backend API at `http://localhost:8000/api` (`repo/README.md:50`) while E2E helper targets `http://localhost:80/api` (`repo/e2e/helpers/api.ts:3`).
- Testing section delegates to script behavior instead of documenting exact containerized test pathways and expected artifacts.

## Low Priority Issues
- Architecture section is concise but does not explain module boundaries/flow in detail.
- No explicit security model narrative beyond role credentials table.

## Engineering Quality Review
- Tech stack clarity: **good**.
- Architecture explanation: **basic**.
- Testing instructions: **present but strict-gate non-compliant**.
- Security/roles documentation: **partially present** (seed credentials by role provided).
- Workflow/presentation quality: **readable markdown**, coherent structure.

## README Verdict
**FAIL**

Primary reason: one or more hard gates failed (startup command literal, verification method specificity, strict Docker-contained environment rule).
