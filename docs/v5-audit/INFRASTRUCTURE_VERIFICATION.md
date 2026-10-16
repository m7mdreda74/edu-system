# V5 Infrastructure Verification & Service Topology

**Platform:** Al-Tafawwuq Educational System  
**Audit Cycle:** V5 Final Completion & Hardening Audit  
**Runtime:** Vercel Serverless PHP Runtime + Managed Cloud MySQL 8.0  

---

## 1. Queue Architecture & Serverless Delivery

### 1.1 The Serverless Queue Constraint
In a serverless environment like Vercel Lambda, processes are ephemeral and terminate after each HTTP request. Continuous daemon workers (`php artisan queue:work`) cannot run indefinitely inside a Lambda instance.

### 1.2 Proposed & Implemented Architecture
- **Low-Latency Jobs:** Synchronous dispatch (`sync`) for immediate transactional events (e.g., student enrollment confirmation, password resets).
- **Scheduled Background Delivery:** Batch notifications and background jobs are queued into the database `jobs` table and processed periodically via Vercel Cron endpoints protected by `CRON_SECRET`.
- **Heavy Media Processing:** Delegated to Cloudflare Stream webhooks (`/webhooks/cloudflare-stream`), offloading compute from the serverless web tier entirely.

---

## 2. Session Architecture: Cookie vs. Database

### 2.1 The Two Operational Modes
| Mode | Configured In | Operation | Concurrency Impact |
| :--- | :--- | :--- | :--- |
| **`cookie`** | `vercel.json` | Sessions encrypted client-side in secure cookie signed with `APP_KEY`. | Completely stateless across Lambda instances. Bypasses MySQL `sessions` table. |
| **`database`** | `.env` (local/staging) | Sessions stored in `sessions` table. | Middleware `LimitConcurrentSessions` monitors and limits active devices to 2 per user. |

### 2.2 Middleware Safety Guarantee
`app/Http/Middleware/LimitConcurrentSessions.php`:
```php
if (config('session.driver') !== 'database') {
    return $next($request);
}
```
The middleware gracefully bypasses database deletion when the application runs in `cookie` mode on Vercel, preventing runtime exceptions.

---

## 3. Shared Cache & Concurrency Control

- **Cache Driver:** `CACHE_STORE=database` in `vercel.json` and production settings.
- **Shared State:** All Lambda instances share the centralized `cache` and `cache_locks` tables in MySQL.
- **Atomic Locks:** Used during booking confirmation and seat allocation (`Cache::lock('booking_slot_'.$slotId, 10)`), preventing concurrent race conditions.
- **Ephemerality Protection:** The application never relies on `/tmp` for shared state or cache storage.

---

## 4. Vercel Cron Schedules & Dual Routing

All five scheduled maintenance tasks are defined in `vercel.json` and registered with dual route aliases in `routes/web.php`:

| Cron Task | Schedule (UTC) | Endpoint | Middleware Protection |
| :--- | :--- | :--- | :--- |
| **Live Session Reminders** | `0 0 * * *` | `/api/cron/live-session-reminders` | `throttle:10,1`, `cron.secret` |
| **Academic Alerts** | `30 6 * * *` | `/api/cron/academic-alerts` | `throttle:10,1`, `cron.secret` |
| **Subscription Renewal Reminders** | `0 0 * * *` | `/api/cron/subscription-renewal-reminders` | `throttle:10,1`, `cron.secret` |
| **Missed Live Sessions** | `0 0 * * *` | `/api/cron/missed-live-sessions` | `throttle:10,1`, `cron.secret` |
| **Video Reconciliation** | `0 3 * * *` | `/api/cron/video-reconciliation` | `throttle:10,1`, `cron.secret` |

---

## 5. Object Storage & Vercel Blob Integration

- **Routes:** Direct upload via `/api/blob-upload` and download via `/api/blob-download`.
- **Token Protection:** `BLOB_READ_WRITE_TOKEN` configured in Vercel Project Environment Variables.
- **File Integrity:** User attachments stream directly to Vercel Blob, respecting maximum upload size limits and preventing serverless memory exhaustion.

---

## 6. PHP GD Extension Status

- **Local Windows CLI:** Herd-lite PHP 8.5 lacks `php_gd.dll` (5 image-upload unit tests safely skipped).
- **GitHub Actions (CI):** Installs `php-gd` via `shivammathur/setup-php`, passing 100% of image tests.
- **Vercel Production Runtime:** `vercel-php@0.9.0` includes built-in GD support for runtime avatar and image processing.
