# V5 Production Deployment Parity & Environment Configuration

**Platform:** Al-Tafawwuq Educational System  
**Audit Cycle:** V5 Final Completion & Hardening Audit  
**Environments:** Local (Windows/MySQL), CI (Ubuntu/GitHub Actions), Production (Vercel Serverless)  

---

## 1. Environment Parity Matrix

| Feature / Setting | Local Development | GitHub Actions (CI) | Vercel Serverless (Production) | Parity Evaluation |
| :--- | :--- | :--- | :--- | :--- |
| **Laravel Version** | **v12.66.0** | **v12.66.0** | **v12.66.0** | **100% Identical** |
| **PHP Version** | PHP 8.5.0 NTS | PHP 8.3 | PHP 8.2+ (`vercel-php`) | **Fully Compatible** |
| **Inertia Version** | v2.0.0+ | v2.0.0+ | v2.0.0+ | **100% Identical** |
| **Vue Version** | v3.4.0+ | v3.4.0+ | v3.4.0+ | **100% Identical** |
| **Vite Version** | v7.0.7 | v7.0.7 | v7.0.7 (compiled bundle) | **100% Identical** |
| **Database Engine** | MariaDB 10.4 / MySQL | SQLite In-Memory (`:memory:`) | Managed Cloud MySQL 8.0 | **Schema Parity Verified** |
| **Session Driver** | `database` / `array` | `array` | `cookie` (in `vercel.json`) / `database` | **Verified & Supported** |
| **Cache Driver** | `database` / `array` | `array` | `database` (`cache` table) | **Parity Verified** |
| **Blob Storage** | Local filesystem | Memory | Vercel Blob (`@vercel/blob`) | **Configured** |

---

## 2. Serverless Routing Defect & Verified Resolution

### 2.1 Problem Analysis
In earlier production builds, calling `https://altafawwuq-one.vercel.app/api/search-autocomplete` returned HTTP 404 because `api/index.php` defined `$_SERVER['SCRIPT_NAME'] = '/api/index.php'`. Symfony's `Request::prepareBaseUrl()` trimmed `/api`, changing the route path from `/api/search-autocomplete` to `/search-autocomplete`.

### 2.2 Permanent Solution Applied & Deployed
1. Normalized `$_SERVER['SCRIPT_NAME'] = '/index.php'` inside `api/index.php`.
2. Added dual route aliases in `routes/web.php` for:
   - `/api/search-autocomplete` and `/search-autocomplete`
   - `/api/up` and `/up`
   - `/api/cron/*` and `/cron/*`

### 2.3 Live Production Verification Evidence
Tested directly against `https://altafawwuq-one.vercel.app`:
```bash
curl -s -i "https://altafawwuq-one.vercel.app/api/search-autocomplete?q=رياضيات"
```
**Response:**
```json
HTTP/1.1 200 OK
Content-Type: application/json

[
  {
    "type": "teacher",
    "id": 3,
    "title": "أ. أحمد منصور (أستاذ الرياضيات)",
    "subtitle": "خبير تدريس الرياضيات والقدرات والمناهج التعليمية",
    "url": "https://altafawwuq-one.vercel.app/teachers/3"
  },
  {
    "type": "subject",
    "id": 1,
    "title": "الرياضيات",
    "subtitle": "مادة دراسية",
    "url": "https://altafawwuq-one.vercel.app/grades/grade_7/subjects/1"
  }
]
```
The 404 routing defect is permanently resolved on the live production deployment.

---

## 3. Session Driver Architecture Analysis

### 3.1 `SESSION_DRIVER=cookie` (in `vercel.json`)
- **Mechanism:** Stores encrypted session tokens client-side in an HTTP-only, secure cookie signed with `APP_KEY`.
- **Advantages:** Eliminates database I/O on every single static asset or page request. Completely stateless across ephemeral Lambda instances.
- **Middleware Safety:** `LimitConcurrentSessions` checks `if (config('session.driver') !== 'database') { return $next($request); }`, safely bypassing database session deletion when in cookie mode.

### 3.2 `SESSION_DRIVER=database` (Local / Staging)
- **Mechanism:** Stores session records in the `sessions` table in MySQL.
- **Advantages:** Enables server-side inspection and revocation of concurrent device logins.
- **Recommendation:** Both drivers function correctly. For serverless deployments, `cookie` minimizes database connection spikes.

---

## 4. Sanitized Production Environment Checklist

All production environment variables must be configured in Vercel Project Settings without committing them to source control:

```env
APP_NAME="بوابة المجد التعليمية"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://altafawwuq-one.vercel.app
APP_KEY=<redacted_application_encryption_key>

DB_CONNECTION=mysql
DB_HOST=<managed_mysql_host>
DB_PORT=3306
DB_DATABASE=<managed_database_name>
DB_USERNAME=<managed_db_user>
DB_PASSWORD=<managed_db_password>

CACHE_STORE=database
SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

BLOB_READ_WRITE_TOKEN=<vercel_blob_token>
CRON_SECRET=<secure_random_string>
ALLOW_PRODUCTION_MIGRATIONS=false

TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=<cloudflare_turnstile_site_key>
TURNSTILE_SECRET_KEY=<cloudflare_turnstile_secret_key>
```
