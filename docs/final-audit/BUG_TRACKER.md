# Confirmed Bug Tracker & Remediation Log

**Platform:** Al-Tafawwuq Educational System  
**Audit Period:** October 2026  
**Auditor:** Principal Software Architect & QA Lead  

---

## Summary of Defects & Fixes

| Bug ID | Severity | Component | Issue Description | Root Cause | Resolution Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **BUG-001** | **CRITICAL** | `Teacher/DashboardController` | HTTP 500 when accessing `/teacher/dashboard` | `LiveSession` model defined `teachingGroup()` relationship, but controller queried `LiveSession::with('group.assignment...')`, triggering `RelationNotFoundException`. | **RESOLVED** (`LiveSession.php` updated with `group()` alias). Verified via Playwright. |
| **BUG-002** | **HIGH** | `TestAccountsSeeder` / DB | Student dashboard missing subjects & curriculum units | Test student was seeded with Arabic string `'الصف العاشر'` instead of valid lookup key `'grade_10'`. | **RESOLVED** (`TestAccountsSeeder.php` and DB record updated to `'grade_10'`). 11 subjects restored. |
| **BUG-003** | **HIGH** | `api/index.php` / Routing | HTTP 404 on live `/api/search-autocomplete` and `/api/cron/*` | `$_SERVER['SCRIPT_NAME'] = '/api/index.php'` caused Symfony's base URL logic to strip `/api` from path. | **RESOLVED** (`api/index.php` normalized `SCRIPT_NAME` to `/index.php` + route aliases in `routes/web.php`). |
| **BUG-004** | **MEDIUM** | `Login.vue` / QA Suite | Playwright tests failed intermittently on login | Tab button relied on fragile index `button.nth(2)`. | **RESOLVED** Added explicit IDs (`#tab-password`, `#tab-otp`) and updated `qa-test.js` & `role-smoke.js`. |
| **BUG-005** | **MEDIUM** | `Student/Assessments.vue` | Broken "Return to Dashboard" link | Link used nonexistent named route `student.dashboard` instead of registered `dashboard`. | **RESOLVED** Corrected route call to `route('dashboard')`. |
| **BUG-006** | **HIGH** | `scripts/vercel-migrate.php` | Danger of unintended production database migrations | Serverless entry script executed `artisan migrate --force` automatically without confirmation. | **RESOLVED** Added strict environment guard requiring `ALLOW_PRODUCTION_MIGRATIONS=true`. |
| **BUG-007** | **MEDIUM** | `Teacher/GroupWorkspace.vue` | Incomplete tab navigation in teacher group view | Workspace tabs lacked clean Inertia `<Link>` routing to roster, gradebook, worksheets, and question bank. | **RESOLVED** Modernized tabs with Inertia links, active states, and localized labels. |
| **BUG-008** | **LOW** | `scripts/package-release.php` | Risk of accidental secret/database inclusion in archives | No automated sanitizer existed for building release archives. | **RESOLVED** Engineered `package-release.php` with automated exclusion rules and pre-release archive scanning. |

---

## Detailed Defect Investigations

### BUG-001: LiveSession Relationship Mismatch
- **Symptom:** When logging in as teacher and loading `/teacher/dashboard`, the server returned HTTP 500 with log entry:
  `Call to undefined relationship [group] on model [App\Domain\Learning\Models\LiveSession]`.
- **Code Trace:**
  `app/Http/Controllers/Teacher/DashboardController.php:150`:
  ```php
  $upcomingSessions = LiveSession::with(['group.assignment.subject', 'group.assignment.gradeLevel'])
  ```
  `app/Domain/Learning/Models/LiveSession.php:65`:
  ```php
  public function teachingGroup(): BelongsTo { ... }
  ```
- **Fix:** Added `group()` as a first-class alias to `teachingGroup()` in `LiveSession.php`:
  ```php
  public function group(): BelongsTo
  {
      return $this->teachingGroup();
  }
  ```
- **Verification:** Re-ran `node qa-playwright/role-smoke.js` with teacher credentials. Route `/teacher/dashboard` returned HTTP 200 OK with zero errors.

---

### BUG-002: Seeded Student Grade String Mismatch
- **Symptom:** Test student `student@almagd.com` logged in successfully, but curriculum, subjects, and group subscriptions failed to render in `/my-grade` and `/my-classes`.
- **Code Trace:**
  `GradeLevel` model uses programmatic keys like `'grade_10'`, `'grade_11_science'`, etc. The seeder and user database record had:
  `$user->grade_level = 'الصف العاشر';`
  Because `GradeLevel::where('key', $student->grade_level)->first()` looked for key `'الصف العاشر'`, it returned `null`, rendering empty curriculum views.
- **Fix:**
  Updated `database/seeders/TestAccountsSeeder.php` to assign `'grade_10'` and updated the student user database row.
- **Verification:** Student dashboard now displays 11 active curriculum subjects, enrolled Math Group #1, and active subscription.

---

### BUG-003: Vercel Serverless `/api` Routing 404
- **Symptom:** `https://altafawwuq-one.vercel.app/api/search-autocomplete` returned HTTP 404, while `https://altafawwuq-one.vercel.app/api/api/search-autocomplete` returned HTTP 200 `[]`.
- **Root Cause:**
  When `$_SERVER['SCRIPT_NAME']` was `/api/index.php`, Symfony stripped the leading `/api`, transforming the route into `/search-autocomplete`. Since `routes/web.php` only listened on `/api/search-autocomplete`, the router reported a 404.
- **Fix:**
  1. Normalized `$_SERVER['SCRIPT_NAME'] = '/index.php'` in `api/index.php`.
  2. Registered dual route aliases in `routes/web.php` (`/search-autocomplete` and `/api/search-autocomplete`).
- **Verification:** Both `/api/search-autocomplete` and `/search-autocomplete` resolve cleanly.
