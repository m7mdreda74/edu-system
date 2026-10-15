# Open Issues

Status at 2026-10-15: **NOT READY FOR PRODUCTION**.

| Priority | Issue | Owner/action required | Verification |
| --- | --- | --- | --- |
| P0 | Production commit and migration parity unknown | Release owner must identify the exact deployment and inspect production DB status | Production read-only checklist |
| P0 | Queue consumer for queued notifications not proven | Operations must provide and smoke-test a durable worker/queue service | Notification delivery smoke test |
| P0 | Shared cache and distributed locks not proven | Vercel now selects the shared database cache; verify lock/rate-limit behavior across serverless instances | Multi-instance rate-limit/lock test |
| P1 | Private Vercel Blob/OIDC persistence not proven | Connect the approved private store and execute upload/redeploy/retrieve test | Blob staging checklist |
| P1 | Staging/browser role E2E not yet verified | Run the target-gated `qa-playwright` harness against approved staging fixtures and review screenshots, console, network, and role evidence; all four local read-only role smokes passed | Approved staging browser run |
| P1 | Live title uses legacy Al-Majd branding | Product owner must confirm final identity and content migration plan | Owner decision plus deployed smoke test |
| P1 | Current PHP CLI lacks GD | Install/enable GD in the verification/runtime environment; production preflight now fails without it | `php -m` and image upload smoke test |
| P1 | Local release archives contain sensitive entries | Remove or rebuild the ignored archives outside the release workspace before packaging; do not upload them | `php scripts/scan-archive.php` passes for every release archive |

These are intentionally documented rather than hidden behind local test results. No production migration, deploy, credential rotation, or destructive action was performed.

The local preflight command now detects the queue/cache/Blob configuration gates, but a passing configuration check still does not replace the required staging delivery, multi-instance, and upload/redeploy smoke tests.
