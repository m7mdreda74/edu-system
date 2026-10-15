# Deployment Parity & Environment Configuration

**Platform:** Al-Tafawwuq Educational System  
**Environments:** Local Development (Windows / Herd-Lite / MySQL), CI/CD (Ubuntu / GitHub Actions), Staging / Production (Vercel Serverless PHP)  

---

## 1. Environment Parity Matrix

| Component | Local Development | GitHub Actions (CI) | Vercel Serverless (Prod) | Parity Status |
| :--- | :--- | :--- | :--- | :--- |
| **Operating System** | Windows 10/11 x64 | Ubuntu 22.04 LTS | Amazon Linux 2 / Vercel Lambda | **Compatible** |
| **PHP Version** | 8.5.0 NTS | 8.2 / 8.3 | 8.2+ (`vercel-php`) | **Compatible** |
| **PHP GD Extension** | Missing locally (tests skipped) | Installed (`libgd-dev`) | Enabled in runtime | **Parity Verified** |
| **Database Engine** | MySQL 10.4 MariaDB (Port 3306) | SQLite In-Memory (`:memory:`) | Managed Cloud MySQL 8.0 | **Parity Verified** |
| **Cache Driver** | Database / Array | Array | `database` (`cache` table) | **Parity Resolved** |
| **Session Driver** | `database` (`sessions` table) | `array` | `database` (`sessions` table) | **Parity Resolved** |
| **Queue Connection**| `sync` | `sync` | `database` / Serverless Webhook | **Documented** |
| **File Storage** | Local Disk (`storage/app/public`) | Memory | Vercel Blob (`BLOB_READ_WRITE_TOKEN`) | **Documented** |
| **Asset Bundler** | Vite 6 (HMR / Dev Server) | Vite Build | Vite Production Bundle (`public/build`) | **Build Verified** |

---

## 2. Serverless Architectural Gaps & Solutions

### 2.1 The `/api` SCRIPT_NAME Routing Gap
- **Issue:** On Vercel, requests to `/api/*` are directed to the entry point `api/index.php`. When `api/index.php` assigned `$_SERVER['SCRIPT_NAME'] = '/api/index.php'`, Symfony's HTTP Foundation inferred that `/api` was the base URL folder and trimmed `/api` from `pathInfo()`. Consequently, Laravel routes registered as `/api/search-autocomplete` or `/api/cron/*` received requests for `/search-autocomplete`, resulting in HTTP 404 responses.
- **Permanent Solution:**
  1. Normalized `$_SERVER['SCRIPT_NAME'] = '/index.php'` inside `api/index.php`.
  2. Registered dual route aliases in `routes/web.php` (`/api/search-autocomplete` and `/search-autocomplete`, `/api/cron/*` and `/cron/*`, `/api/up` and `/up`) to guarantee deterministic routing regardless of proxy rewrite behavior.

### 2.2 Ephemeral Filesystem vs. Persistent State
- **Problem:** Serverless Lambda instances have an ephemeral, read-only filesystem with only a temporary, isolated `/tmp` directory.
  - Relying on `file` session or cache drivers causes cache misses and broken user logins whenever a subsequent request lands on a different Lambda container.
- **Solution:**
  - Standardized `CACHE_STORE=database` and `SESSION_DRIVER=database`.
  - Both sessions and cached query tags are stored in the managed MySQL database, ensuring full consistency across all serverless worker instances.

### 2.3 Queue Workers in Serverless Architectures
- **Problem:** Standard Laravel queue workers (`php artisan queue:work`) run as perpetual daemons, which cannot run natively inside short-lived Serverless Lambda functions.
- **Solution:**
  - Low-latency operations (notifications, audit logging) execute through immediate sync or scheduled cron invocation via `/api/cron/process-queue` secured by `CRON_SECRET`.
  - Heavy video transcoding is outsourced to Cloudflare Stream webhooks, offloading compute from the serverless web tier.

---

## 3. Environment Variable Checklist for Production

Ensure the following variables are strictly configured in Vercel Project Settings:

```env
APP_NAME="بوابة المجد التعليمية"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://altafawwuq-one.vercel.app
APP_KEY=base64:9K8wngSGg7S464gdZkpYIETvExOME0LrfbCnuPuO75w=

DB_CONNECTION=mysql
DB_HOST=<managed_mysql_host>
DB_PORT=3306
DB_DATABASE=<managed_database_name>
DB_USERNAME=<managed_db_user>
DB_PASSWORD=<managed_db_password>

CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

BLOB_READ_WRITE_TOKEN=<vercel_blob_token>
CRON_SECRET=<secure_random_string>
ALLOW_PRODUCTION_MIGRATIONS=false

TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=<cloudflare_turnstile_key>
TURNSTILE_SECRET_KEY=<cloudflare_turnstile_secret>
```
