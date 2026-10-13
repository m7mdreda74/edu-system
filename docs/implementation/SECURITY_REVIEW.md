# Security Review - Initial P0

## Finding: direct parent link verification

Before this change, a parent who supplied an active student's phone number received a `parent_student_links` row with `verified_at = now()`. All major parent data and payment/booking guards use `verified_at`, so this was an authorization grant based only on an identifier.

## Remediation

- New direct requests are stored as `pending` and do not expose the student's dashboard data.
- The student account must approve or reject the request while authenticated as that student.
- Requests expire and repeated requests are throttled per parent account.
- Approval, rejection, request, and revocation are written through `AuditLogger`.
- Existing non-null `verified_at` links are preserved as approved for compatibility and recovery safety.
- Admin-created/student-registration links remain explicit authorized flows; they are not silently downgraded by the migration.

## Required regression checks

- An unrelated parent cannot approve, reject, or view a pending link.
- A pending link cannot authorize payment, booking, protected files, chat, or child dashboard data.
- A revoked/rejected/expired link cannot authorize child data.
- Approval creates exactly one verified relationship and notifies the requesting parent.
- The parent cannot use repeated requests to enumerate whether a phone number belongs to a student.
