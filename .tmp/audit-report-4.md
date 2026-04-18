# NexusCare Frontend Static Audit v2 (`TASK-28/repo`)

## 1. Verdict
- **Partial Pass**

## 2. Scope and Verification Boundary
- Reviewed: `README.md`, `frontend/src` (router/views/components/stores/services/utils), `frontend/package.json`, `frontend/vite.config.js`, `e2e` tests/helpers/config, `run_tests.sh`.
- Excluded from evidence: `./.tmp/` (including old reports); prior report was only used for stale-check comparison, not as factual evidence.
- Not executed: project runtime, browser execution, tests, Docker, backend services.
- Cannot statically confirm: real runtime API enforcement behavior, backend tenant/object isolation guarantees, reconciliation replay protection behavior under real uploads, final visual rendering quality.
- Manual verification required: end-to-end backend data-scope enforcement and real session timeout/lock behavior in deployed environment.

## 3. Prompt / Repository Mapping Summary
- Prompt core goals mapped: unified login + RBAC menuing, appointment lifecycle (requested/confirmed/checked-in/no-show/completed), waitlist backfill, moderation/admin, recycle restore, payment/fees, reconciliation exceptions/anomalies, audit/reports.
- Required pages and routes are present and role-gated in router metadata.
- Main areas reviewed against prompt: auth/session guard, route-level RBAC, key workflow pages, service adaptor layer, test coverage (unit/component/E2E), and logging/sensitive-data handling utilities.

## 4. High / Blocker Coverage Panel

### A. Prompt-fit / completeness blockers
- **Pass**
- Short reason: prompt-critical pages/flows are statically implemented and route-wired.
- Evidence: `frontend/src/router/index.js:25`, `frontend/src/views/AppointmentCreate.vue:15`, `frontend/src/views/WaitlistView.vue:72`, `frontend/src/views/ReconciliationImport.vue:7`, `frontend/src/views/UserManagement.vue:10`
- Finding IDs: none

### B. Static delivery / structure blockers
- **Partial Pass**
- Short reason: documentation/scripts/entrypoints are consistent, but delivery still includes build/install artifacts reducing package credibility.
- Evidence: `README.md:38`, `frontend/package.json:6`, `frontend/vite.config.js:5`, `frontend/dist/index.html:1`, `frontend/node_modules/.vite/deps/_metadata.json:1`
- Finding IDs: M-02

### C. Frontend-controllable interaction / state blockers
- **Pass**
- Short reason: key sensitive actions now have submit-locking/loading protection; prior duplicate-submit risk in admin dialogs is fixed.
- Evidence: `frontend/src/components/UserCreateDialog.vue:19`, `frontend/src/components/UserCreateDialog.vue:97`, `frontend/src/components/UserResetPasswordDialog.vue:13`, `frontend/src/components/UserResetPasswordDialog.vue:69`
- Finding IDs: none

### D. Data exposure / delivery-risk blockers
- **Fail**
- Short reason: plaintext test credentials remain hardcoded directly inside multiple E2E specs.
- Evidence: `e2e/tests/02-appointments.spec.ts:7`, `e2e/tests/15-appointment-lifecycle.spec.ts:17`, `e2e/tests/14-coverage-gaps.spec.ts:8`, `e2e/tests/08-recycle-bin.spec.ts:6`
- Finding IDs: F-001

### E. Test-critical gaps
- **Partial Pass**
- Short reason: broad tests exist, but high-risk coverage is still incomplete for tenant/data isolation assertions and some failure-path strictness.
- Evidence: `frontend/package.json:10`, `e2e/package.json:9`, `frontend/src/__tests__/routerGuard.test.js:61`, `e2e/tests/11-role-route-rbac.spec.ts:14`
- Finding IDs: M-03

## 5. Confirmed Blocker / High Findings

### F-001
- **Severity:** High
- **Conclusion:** Hardcoded credentials still exist in multiple E2E specs.
- **Brief rationale:** despite helper migration to env vars, many specs still embed admin/staff/reviewer passwords directly.
- **Evidence:** `e2e/tests/02-appointments.spec.ts:7`, `e2e/tests/02-appointments.spec.ts:8`, `e2e/tests/04-fee-assessments.spec.ts:14`, `e2e/tests/15-appointment-lifecycle.spec.ts:17`, `e2e/tests/14-coverage-gaps.spec.ts:8`, `e2e/tests/08-recycle-bin.spec.ts:6`, `e2e/tests/03-waitlist.spec.ts:6`
- **Impact:** credential leakage risk remains; security posture and audit credibility are materially weakened.
- **Minimum actionable fix:** replace all in-spec literals with environment-driven credential getters (same pattern as `e2e/helpers/auth.ts`) and fail fast when vars are missing.

## 6. Other Findings Summary

### M-01
- **Severity:** Medium
- **Conclusion:** Some E2E assertions accept broad outcomes, reducing defect-detection precision on failure paths.
- **Evidence:** `e2e/tests/06-reconciliation.spec.ts:35`, `e2e/tests/13-behavioral-gaps.spec.ts:119`
- **Minimum actionable fix:** split strict success and strict error scenarios with deterministic fixtures and explicit expected UI/API error assertions.

### M-02
- **Severity:** Medium
- **Conclusion:** Delivery includes transient artifacts (`dist`, `node_modules`) in repository scope.
- **Evidence:** `frontend/dist/index.html:1`, `frontend/node_modules/.vite/deps/_metadata.json:1`
- **Minimum actionable fix:** exclude generated/install artifacts from delivery package; keep source + lockfiles only.

### M-03
- **Severity:** Medium
- **Conclusion:** Route-level authorization tests are strong, but frontend-observable tenant/data isolation checks remain limited.
- **Evidence:** `e2e/tests/11-role-route-rbac.spec.ts:14`, `frontend/src/views/AppointmentList.vue:181`, `frontend/src/views/WaitlistView.vue:234`
- **Minimum actionable fix:** add E2E assertions with multi-site/multi-department fixtures proving out-of-scope records are not visible in lists/filters.

### L-01
- **Severity:** Low
- **Conclusion:** `Reports` download actions do not expose explicit in-flight loading/disabled state.
- **Evidence:** `frontend/src/views/ReportsView.vue:27`, `frontend/src/views/ReportsView.vue:45`
- **Minimum actionable fix:** add per-button loading/disable flags during download request lifecycle.

## 7. Data Exposure and Delivery Risk Summary
- Real sensitive information exposure: **Fail** — plaintext credentials are still hardcoded in E2E specs (`e2e/tests/15-appointment-lifecycle.spec.ts:17`).
- Hidden debug/config/demo-only surfaces: **Pass** — no default-enabled frontend runtime debug bypass found; logging is opt-in in dev mode (`frontend/src/utils/logger.js:5`).
- Undisclosed mock scope/default mock behavior: **Pass** — app runtime uses axios to `/api`; mocks are in test files only (`frontend/src/main.js:17`, `frontend/src/__tests__/WaitlistView.test.js:15`).
- Fake-success/misleading behavior: **Partial Pass** — no obvious runtime fake-success path, but some tests allow broad pass criteria (`e2e/tests/06-reconciliation.spec.ts:35`).
- UI/console/storage leakage risk: **Pass** — payload masking and redaction utilities exist; console logging disabled by default unless explicitly enabled (`frontend/src/utils/maskPayload.js:13`, `frontend/src/utils/logger.js:25`).

## 8. Test Sufficiency Summary

### Test Overview
- Unit tests: **yes** (`frontend/src/__tests__/identifierValidation.test.js:1`, `frontend/src/__tests__/authStore.test.js:20`)
- Component tests: **yes** (`frontend/src/__tests__/AppointmentCreate.test.js:56`, `frontend/src/__tests__/AppointmentCancelDialog.test.js:24`)
- Page/route integration tests: **yes** (`frontend/src/__tests__/routerGuard.test.js:61`, `e2e/tests/11-role-route-rbac.spec.ts:14`)
- E2E tests: **yes** (`e2e/package.json:9`, `e2e/tests/15-appointment-lifecycle.spec.ts:49`)
- Test entry points: `frontend/package.json:10`, `e2e/package.json:9`, `run_tests.sh:24`

### Core Coverage
- Happy path: **covered**
- Key failure paths: **partially covered**
- Interaction/state coverage: **partially covered**

### Major Gaps (top risk)
1. Hardcoded credentials in specs undermine secure test execution model.  
   Evidence: `e2e/tests/02-appointments.spec.ts:7`  
   Minimum test addition/change: centralize env-backed credential acquisition across all specs.
2. Tenant/data isolation visibility is not strongly asserted in list-level UI behavior.  
   Evidence: `e2e/tests/11-role-route-rbac.spec.ts:14`  
   Minimum addition: role+scope fixture matrix asserting cross-site/dept absence.
3. Reconciliation/report error-path tests are not strict enough.  
   Evidence: `e2e/tests/06-reconciliation.spec.ts:35`  
   Minimum addition: deterministic negative fixtures with exact error assertions.
4. Sensitive admin dialogs now have guards, but no dedicated regression tests were added for those exact guards.  
   Evidence: `frontend/src/components/UserCreateDialog.vue:97`, `frontend/src/components/UserResetPasswordDialog.vue:69`  
   Minimum addition: component tests asserting second-click suppression (single API call).
5. Some authorization tests allow `/forbidden|/login` dual outcomes, reducing precision of expected state.  
   Evidence: `e2e/tests/11-role-route-rbac.spec.ts:17`  
   Minimum addition: assert exact expected route per scenario and auth state.

### Final Test Verdict
- **Partial Pass**

## 9. Engineering Quality Summary
- Architecture remains generally modular and maintainable: router/store/services/views separation is present.
- Role-based access design is coherent at frontend guard/navigation level.
- Major maintainability risks are not from file organization, but from test-suite hygiene/precision and delivery artifact cleanliness.
- Previously reported admin-submit guard issue is fixed and should not be carried forward.

## 10. Visual and Interaction Summary
- Static structure supports a credible product UI: app shell, role-scoped navigation, table/form/card-based workflow pages.
- Core interaction states (loading/empty/error/submitting) are implemented in key flows.
- Cannot confirm statically: final visual polish, responsive behavior quality, and runtime interaction feel (hover/transition fidelity) without execution.

## 11. Next Actions
1. **[High]** Remove all remaining hardcoded credentials from E2E specs; use env vars consistently.
2. **[Medium]** Add explicit tenant/site/department isolation assertions in E2E list/filter flows.
3. **[Medium]** Tighten failure-path E2E assertions for reconciliation/reporting to deterministic expected outcomes.
4. **[Medium]** Add regression tests for newly fixed duplicate-submit guards in `UserCreateDialog` and `UserResetPasswordDialog`.
5. **[Medium]** Exclude `frontend/dist` and `frontend/node_modules` from final delivery package.
6. **[Low]** Add loading/disabled state for report download buttons.
