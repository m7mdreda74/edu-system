# Open Issues

Status at 2026-10-14: **NOT READY FOR PRODUCTION**.

| Priority | Issue | Owner/action required | Verification |
| --- | --- | --- | --- |
| P0 | Production commit and migration parity unknown | Release owner must identify the exact deployment and inspect production DB status | Production read-only checklist |
| P0 | Queue consumer for queued notifications not proven | Operations must provide and smoke-test a durable worker/queue service | Notification delivery smoke test |
| P0 | Shared cache and distributed locks not proven | Vercel now selects the shared database cache; verify lock/rate-limit behavior across serverless instances | Multi-instance rate-limit/lock test |
| P1 | Private Vercel Blob/OIDC persistence not proven | Connect the approved private store and execute upload/redeploy/retrieve test | Blob staging checklist |
| P1 | Browser/mobile E2E unavailable | Add an approved browser harness or run the product’s external QA suite | Screenshots, console, network evidence |
| P1 | Live title uses legacy Al-Majd branding | Product owner must confirm final identity and content migration plan | Owner decision plus deployed smoke test |
| P1 | Current PHP CLI lacks GD | Install/enable GD in the verification/runtime environment; production preflight now fails without it | `php -m` and image upload smoke test |

These are intentionally documented rather than hidden behind local test results. No production migration, deploy, credential rotation, or destructive action was performed.

The local preflight command now detects the queue/cache/Blob configuration gates, but a passing configuration check still does not replace the required staging delivery, multi-instance, and upload/redeploy smoke tests.
