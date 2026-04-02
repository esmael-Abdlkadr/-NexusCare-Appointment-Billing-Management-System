# Business Logic Questions Log — TASK-NEXUSCARE-DUMMY
# NexusCare Appointment & Billing Management System

---

1. JWT idle timeout reset behavior
   - Question: Does every API request reset the 30-minute idle timer, or only specific interaction endpoints?
   - My Understanding: Any authenticated API call resets the idle timer. The 12-hour absolute timeout is always enforced regardless of activity.
   - Solution: Middleware records `last_active_at` on each request. On token validation, check both `(now - last_active_at) < 30min` and `(now - issued_at) < 12h`. Either violation returns 401 with `TOKEN_EXPIRED`.

2. Account lockout scope — per identifier or per IP
   - Question: The prompt says "accounts lock after 5 failed attempts" — does this lock the account identifier or the source IP?
   - My Understanding: Lockout is per account identifier (employee ID / username). IP-based rate limiting is a separate independent layer.
   - Solution: Track failed attempts in `login_attempts` table keyed by `identifier`. Lock account for 15 minutes when 5 failures occur within a rolling window. Store `locked_until` on `users` table.

3. No-show fee grace period — when does the 10-minute clock start
   - Question: Does the 10-minute grace period for a $25.00 no-show fee start from the scheduled appointment time or from the confirmed check-in window?
   - My Understanding: Grace period starts at the exact scheduled appointment start time. If a client has not checked in by `start_time + 10 minutes`, the system marks the appointment as no-show and creates a fee record automatically.
   - Solution: Scheduled job runs every minute checking appointments past their `start_time + 10min` with status `confirmed`. Marks them `no_show` and inserts a `fee_assessments` record with `fee_type=no_show, amount=25.00`.

4. Overdue fine calculation — simple or compound interest
   - Question: The 1.5% per 30 days overdue fine — is this applied as simple interest on the original balance or compounded each period?
   - My Understanding: Simple interest on the original outstanding balance. Each 30-day period adds `original_amount * 0.015`. No compounding.
   - Solution: `fine_amount = original_balance * 0.015 * floor(days_overdue / 30)`. Recalculated daily by a scheduler job. Stored in `ledger_entries` with `entry_type=overdue_fine`.

5. Waiver/write-off approval — single Reviewer or any Reviewer
   - Question: Can any Reviewer approve a waiver/write-off, or must it be a Reviewer scoped to the same site/department as the fee?
   - My Understanding: Any Reviewer with access to the site where the fee was assessed can approve. Cross-site approval is not permitted.
   - Solution: Waiver requests are scoped by `site_id`. `require_scope('reviewer', site_id)` policy applied on approval endpoint. Approval is logged in `audit_logs` with reviewer ID and site.

6. Waitlist backfill — automatic proposal or automatic booking
   - Question: When a slot opens, does the system automatically book the next waitlisted client, or only propose the backfill to Staff for confirmation?
   - My Understanding: The system proposes the backfill — it does not auto-book. Staff sees a notification and must confirm before the appointment is created.
   - Solution: On appointment cancellation/reschedule, a `waitlist_proposals` record is created for the best-matched waitlist entry. Staff sees a dashboard alert. Confirmation by Staff creates the appointment and removes the waitlist entry.

7. Conflict detection scope — resource or provider or both
   - Question: Appointment conflict detection — does it check the provider's schedule, the room/resource booking, or both simultaneously?
   - My Understanding: Conflicts are checked on both the assigned provider AND the assigned room/resource independently. Either conflict blocks the booking.
   - Solution: On appointment creation/reschedule, query `appointments` for overlapping time ranges where `provider_id = ? OR resource_id = ?` and `status NOT IN (cancelled, no_show)`. Return `CONFLICT_PROVIDER` or `CONFLICT_RESOURCE` error codes with next-available suggestion.

8. Settlement file fingerprint — what constitutes a replay
   - Question: Is a replay detected by file hash alone, or also by filename/date combination?
   - My Understanding: Replay is detected by SHA-256 hash of the file content. A file with the same content but a different name is still considered a replay.
   - Solution: On import, compute SHA-256 of the uploaded file. Check against `settlement_imports` table. If hash exists, return `DUPLICATE_SETTLEMENT_FILE` error with the original import date.

9. Daily variance alert threshold — net or absolute variance
   - Question: The $50.00 daily variance alert — is this based on net variance (could be positive or negative) or absolute variance (|expected - actual| > $50)?
   - My Understanding: Absolute variance. A $55 surplus and a $55 shortfall both trigger the alert.
   - Solution: After each reconciliation run, compute `abs(expected_total - actual_total)`. If `> 50.00`, insert into `anomaly_alerts` table and surface on Reviewer dashboard with status `unresolved`.

10. Password complexity rules — exact requirements
    - Question: The prompt says "at least 12 characters with complexity rules" but does not define the complexity rules.
    - My Understanding: Standard complexity: at least one uppercase letter, one lowercase letter, one digit, and one special character. No spaces.
    - Solution: Backend validates with regex: `^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z\d]).{12,}$`. Returns 422 with `PASSWORD_COMPLEXITY_VIOLATION` if not met.

11. Sensitive field masking — exact role matrix
    - Question: Which roles see government IDs and contact fields unmasked? The prompt says "partially masked" but gives no masking matrix.
    - My Understanding: Administrators see all fields unmasked. Reviewers see name + last 4 digits of ID only. Staff see only what is needed for their workflow (no government ID at all).
    - Solution: Masking applied in API response transformer. Format: Gov ID → `****-****-1234`, Phone → `(***) ***-5678`, Email → `j***@domain.com`. Role check in `MaskingService::apply($user, $field, $value)`.

12. Recycle bin retention — how long before permanent delete
    - Question: Soft-deleted items go to the recycle bin — how long are they retained before permanent deletion?
    - My Understanding: The prompt requires 24-month retention for critical records. Recycle bin items are permanently deleted after 24 months.
    - Solution: Scheduler job runs monthly. Deletes soft-deleted records where `deleted_at < now() - 24 months`. Excludes appointment and payment records (always retained per prompt requirement).

13. Incremental sync — conflict resolution for same-time edits
    - Question: If two sites edit the same appointment at the same time and sync, which version wins?
    - My Understanding: The prompt says "preserve the latest confirmed appointment state." The record with the newer `updated_at` timestamp wins. Both prior versions are retained in `appointment_versions` for traceability.
    - Solution: Sync job compares fingerprints. On conflict, `confirmed` status takes priority over other statuses. If both are `confirmed`, the higher `updated_at` wins. Both versions saved to `appointment_versions` before merge.

14. Admin-assisted password reset — what identity checks are required
    - Question: The prompt mentions "offline-only admin-assisted reset with identity checks" — what constitutes a valid identity check?
    - My Understanding: Identity check is a manual in-person process. The Admin records the verification method (e.g., ID card checked, manager vouched) in a free-text field. The system does not enforce a specific check type but requires the field to be non-empty.
    - Solution: `POST /api/admin/users/{id}/reset-password` requires `verification_note` (non-empty string). The note is stored in `audit_logs` with `action=PASSWORD_RESET`, `performed_by=admin_id`.

15. Temporary mute — what actions does a 24-hour mute restrict
    - Question: A 24-hour mute — does it prevent login entirely, or only certain actions (e.g., creating appointments)?
    - My Understanding: A muted user can still log in but cannot create, update, or cancel appointments. Read-only access is preserved. Admin and billing actions are also blocked.
    - Solution: `muted_until` column on `users` table. Middleware checks `muted_until > now()`. If muted, request passes authentication but write endpoints return 403 with `ACCOUNT_MUTED` and the `muted_until` timestamp.

16. Offline terminal batch import — expected file format
    - Question: "Locally imported terminal batch records" — what file format is expected (CSV, XML, proprietary)?
    - My Understanding: CSV format is the most universally supported for POS terminal exports. The system should accept CSV with a defined column schema.
    - Solution: Import endpoint accepts CSV with required columns: `transaction_id, amount, type, timestamp, terminal_id`. Backend validates schema before processing. Malformed rows are reported in the import summary rather than failing the entire batch.

17. Variance calculation includes ORDER_NOT_FOUND rows
    - Question: When a CSV row's transaction_id has no matching payment, should its amount count toward the daily_variance that triggers anomaly alerts?
    - My Understanding: Yes — an unmatched transaction represents real money received at the terminal with no corresponding internal record, which IS a genuine discrepancy.
    - Solution: ORDER_NOT_FOUND rows add their actual_amount to sumActual with expected=0, so variance = |sumActual - sumExpected| naturally captures both missing orders and amount mismatches. Operators should be aware that imports with many unknown transactions will trigger anomaly alerts at the $50 threshold.

18. Masking scope — which endpoints apply MaskingService
    - Question: The spec says masking applies to "every API response that includes user records." Does this mean all nested user objects in appointments, waitlist, fee assessments, etc. must also be masked?
    - My Understanding: Sensitive fields (government_id, phone, full email) are only stored on User records accessed via the admin user management module. Appointment and billing responses expose only user identifiers (id, identifier, role) — not sensitive PII fields. The /api/auth/me endpoint exposes own-profile data which is always unmasked per spec.
    - Solution: MaskingService is applied in AdminUserService (user list/detail) and AuthController (me endpoint). Nested user references in other modules expose only non-sensitive fields (identifier, role, site_id) which do not require masking.
