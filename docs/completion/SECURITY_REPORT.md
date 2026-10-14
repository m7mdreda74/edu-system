# Security Report

## Verified controls

- Group workspace, roster page, and roster API require an authenticated teacher and verify ownership through the teaching assignment.
- Roster search is constrained to students enrolled through a confirmed booking or active, non-expired subscription for the requested group.
- Roster responses expose only the fields needed by the teacher list: id, name, email, and avatar.
- Assignment deep-links resolve only inside the student’s authorized published unit tree.
- Roster API input is validated and throttled at 60 requests per minute.
- Existing full-suite security coverage remains green, including parent-link authorization, private material access, quiz/gradebook scope, inactive users, and production migration endpoint absence.
- Production preflight does not expose secrets and now blocks production when queue, shared-cache, Blob, or GD prerequisites are absent.

## Operational security still requiring staging evidence

- Verify production secrets are present without exposing them in logs or reports.
- Verify queue jobs do not remain indefinitely pending.
- Verify a shared cache/lock backend across more than one serverless instance.
- Verify private Blob URLs, token scope, expiry, and retrieval after redeploy.
- Run an authenticated browser smoke test with redacted screenshots/network data.
