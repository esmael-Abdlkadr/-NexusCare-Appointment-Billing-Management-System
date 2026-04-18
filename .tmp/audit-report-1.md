# NexusCare Frontend Static Audit v3 (`TASK-28/repo`)

## 1. Verdict
- **Partial Pass**

## 2. Scope and Verification Boundary
- Reviewed: `README.md`, `run.sh`, `frontend/src` (router/views/components/stores/services/utils), `frontend/package.json`, `frontend/vite.config.js`, `e2e` helpers/config/specs, `.gitignore`.
- Excluded sources: `./.tmp/` contents were excluded as evidence; prior reports were used only for stale-check comparison.
- Not executed: app runtime, browser runtime, Docker, tests, backend services.
- Cannot confirm statically: backend-enforced RBAC/data-scope correctness, real session timeout behavior, real reconciliation anti-replay behavior in deployed runtime.
- Manual verification required: end-to-end backend authorization/object isolation and production credential rotation policy.

## 3. Prompt / Repository Mapping Summary
- Prompt business goals covered in code structure: role-based login/navigation, appointments lifecycle, waitlist/backfill, admin moderation/recycle, payments/fees, reconciliation exceptions/anomalies, audit/reports.
- Required pages and route guards are statically present and role-scoped.
- Previously reported stale items rechecked:
  - Old issue (admin dialog duplicate-submit protection) is **fixed** (`frontend/src/components/UserCreateDialog.vue:19`, `frontend/src/components/UserResetPasswordDialog.vue:13`).
  - Old issue (hardcoded E2E credentials in spec helpers/tests) is **mostly fixed** via env-based helpers (`e2e/helpers/auth.ts:20`, `e2e/helpers/api.ts:22`), but one credential exposure source remains in `run.sh`.

## 4. High / Blocker Coverage Panel

### A. Prompt-fit / completeness blockers
- **Pass**
- Reason: prompt-critical routes/pages/flows are present and wired.
- Evidence: `frontend/src/router/index.js:25`, `frontend/src/views/AppointmentCreate.vue:15`, `frontend/src/views/WaitlistView.vue:72`, `frontend/src/views/ReconciliationImport.vue:7`, `frontend/src/views/UserManagement.vue:10`
- Finding IDs: none

### B. Static delivery / structure blockers
- **Partial Pass**
- Reason: startup/build/test docs and entrypoints are coherent, but delivery still includes local build/install artifacts in tree.
- Evidence: `README.md:38`, `frontend/package.json:6`, `.gitignore:22`, `frontend/dist/index.html:1`, `frontend/node_modules/.vite/deps/_metadata.json:1`
- Finding IDs: M-01

### C. Frontend-controllable interaction / state blockers
- **Pass**
- Reason: key sensitive dialogs now have submitting guards + loading/disabled protection.
- Evidence: `frontend/src/components/UserCreateDialog.vue:19`, `frontend/src/components/UserCreateDialog.vue:97`, `frontend/src/components/UserResetPasswordDialog.vue:13`, `frontend/src/components/UserResetPasswordDialog.vue:69`
- Finding IDs: none

### D. Data exposure / delivery-risk blockers
- **Fail**
- Reason: plaintext demo credentials are still printed by a startup script.
- Evidence: `run.sh:22`, `run.sh:23`, `run.sh:24`, `run.sh:25`
- Finding IDs: F-001

### E. Test-critical gaps
- **Partial Pass**
- Reason: tests are substantial and now include explicit isolation-focused scenarios, but some failure-path assertions remain broad.
- Evidence: `frontend/package.json:10`, `e2e/package.json:9`, `e2e/tests/17-isolation.spec.ts:1`, `e2e/tests/06-reconciliation.spec.ts:35`
- Finding IDs: M-02

## 5. Confirmed Blocker / High Findings

### F-001
- **Severity:** High
- **Conclusion:** Plaintext credentials are exposed in operational startup script output.
- **Brief rationale:** script prints explicit admin/staff/reviewer passwords to console.
- **Evidence:** `run.sh:22`, `run.sh:23`, `run.sh:24`, `run.sh:25`
- **Impact:** avoidable credential exposure risk and weak delivery security posture.
- **Minimum actionable fix:** remove passwords from script output; print only role/usernames and instruct credential retrieval from secure local env/seeder output.

## 6. Other Findings Summary

### M-01
- **Severity:** Medium
- **Conclusion:** Delivery contains generated/install artifacts (`frontend/dist`, `frontend/node_modules`) despite ignore rules.
- **Evidence:** `.gitignore:22`, `.gitignore:23`, `frontend/dist/index.html:1`, `frontend/node_modules/.vite/deps/_metadata.json:1`
- **Minimum actionable fix:** exclude these artifacts from final delivery archive/submission.

### M-02
- **Severity:** Medium
- **Conclusion:** Some E2E failure-path checks remain permissive and may hide regressions.
- **Evidence:** `e2e/tests/06-reconciliation.spec.ts:35`, `e2e/tests/13-behavioral-gaps.spec.ts:119`
- **Minimum actionable fix:** split strict success vs strict failure scenarios with deterministic fixtures and explicit expected error assertions.

### L-01
- **Severity:** Low
- **Conclusion:** `Reports` actions still lack explicit loading/disabled state during downloads.
- **Evidence:** `frontend/src/views/ReportsView.vue:27`, `frontend/src/views/ReportsView.vue:45`
- **Minimum actionable fix:** add per-action in-flight state (`loading/disabled`) to prevent repeated clicks and improve UX clarity.

## 7. Data Exposure and Delivery Risk Summary
- Real sensitive information exposure: **Fail** — `run.sh` prints plaintext demo credentials (`run.sh:23`).
- Hidden debug/config/demo-only surfaces: **Pass** — no default-enabled frontend runtime debug bypass found; console logging is opt-in in dev (`frontend/src/utils/logger.js:5`).
- Undisclosed mock scope/default mock behavior: **Pass** — app runtime uses axios `/api`; mocks are test-local (`frontend/src/main.js:17`, `frontend/src/__tests__/WaitlistView.test.js:15`).
- Fake-success/misleading behavior: **Partial Pass** — runtime fake-success path not found, but some tests allow broad outcomes (`e2e/tests/06-reconciliation.spec.ts:35`).
- Visible UI/console/storage leakage risk: **Pass** — masking/redaction utilities exist and are tested (`frontend/src/utils/maskPayload.js:13`, `frontend/src/__tests__/maskPayload.test.js:4`).

## 8. Test Sufficiency Summary

### Test Overview
- Unit tests exist: yes (`frontend/src/__tests__/identifierValidation.test.js:1`, `frontend/src/__tests__/authStore.test.js:20`).
- Component tests exist: yes (`frontend/src/__tests__/AppointmentCreate.test.js:56`, `frontend/src/__tests__/AppointmentCancelDialog.test.js:24`).
- Page/route integration tests exist: yes (`frontend/src/__tests__/routerGuard.test.js:61`, `e2e/tests/11-role-route-rbac.spec.ts:14`).
- E2E tests exist: yes (`e2e/package.json:9`, `e2e/tests/15-appointment-lifecycle.spec.ts:49`).
- Obvious test entry points: `frontend/package.json:10`, `e2e/package.json:9`, `run_tests.sh:24`.

### Core Coverage
- Happy path: **covered**
- Key failure paths: **partially covered**
- Interaction/state coverage: **covered**

### Major Gaps
1. Reconciliation/report error assertions are broad in places.  
   Evidence: `e2e/tests/06-reconciliation.spec.ts:35`  
   Minimum test addition: deterministic negative fixture + explicit exact error expectation.
2. Some security-route checks accept dual outcomes (`/forbidden|/login`) rather than exact expected route state.  
   Evidence: `e2e/tests/11-role-route-rbac.spec.ts:17`  
   Minimum test addition: assert exact route outcome per auth scenario.
3. No explicit test around credential-safe startup script behavior/documentation boundary.  
   Evidence: `run.sh:22`  
   Minimum test/addition: static policy/lint check preventing plaintext credential output in scripts.

### Final Test Verdict
- **Partial Pass**

## 9. Engineering Quality Summary
- Modular frontend architecture remains solid: route/store/service/view separation is clear.
- Role-based navigation and guard logic are maintainable and test-covered.
- Major credibility risks are now mostly in delivery hygiene (credential output in scripts, artifact packaging), not core frontend structure.

## 10. Visual and Interaction Summary
- Static structure supports credible app composition: layout shell, role-based nav, forms/tables/dialogs for core workflows.
- Key interaction states exist for major flows (loading/submitting/empty/error).
- Cannot confirm statically: final rendered visual quality, responsive behavior quality, and transition/hover fidelity.

## 11. Next Actions
1. **[High]** Remove plaintext credentials from `run.sh` output; switch to secure retrieval guidance.
2. **[Medium]** Remove `frontend/dist` and `frontend/node_modules` from delivery payload.
3. **[Medium]** Tighten permissive E2E failure assertions into strict deterministic checks.
4. **[Medium]** Make authorization-route tests assert exact expected outcomes where feasible.
5. **[Low]** Add loading/disabled state for `Reports` download buttons.
