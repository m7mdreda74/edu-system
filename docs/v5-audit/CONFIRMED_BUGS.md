# V5 Confirmed Defects & Remediation Log

**Platform:** Al-Tafawwuq Educational System  
**Audit Cycle:** V5 Final Completion & Hardening Audit  
**Status:** ALL DEFECTS FULLY REMEDIATED & VERIFIED  

---

## Remediation Summary Table

| Defect ID | Severity | Component | Issue Description | Root Cause | Fix Performed | Verification Evidence |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **BUG-001** | **CRITICAL** | `Teacher/DashboardController` | HTTP 500 when accessing `/teacher/dashboard` | `LiveSession` defined `teachingGroup()`, but controller queried `with('group.assignment...')`. | Added `group(): BelongsTo` alias in `LiveSession.php`. | Playwright live test returned 200 OK across all 15 teacher routes. |
| **BUG-002** | **HIGH** | `TestAccountsSeeder` / DB | Student dashboard missing subjects & curriculum units | Test student had Arabic string `'الصف العاشر'` instead of programmatic key `'grade_10'`. | Updated seeder and DB user record to `'grade_10'`. | Restored 11 curriculum subjects and Math Group #1 subscription. |
| **BUG-003** | **HIGH** | `api/index.php` / Routing | HTTP 404 on live `/api/search-autocomplete` and `/api/cron/*` | `$_SERVER['SCRIPT_NAME'] = '/api/index.php'` caused Symfony to strip `/api` from path. | Normalized `SCRIPT_NAME` to `/index.php` and added dual route aliases in `routes/web.php`. | Live curl test returned HTTP 200 with matching teachers and subjects. |
| **BUG-004** | **MEDIUM** | `Login.vue` / QA Suite | Playwright tests failed intermittently on login | Tab button relied on fragile index `button.nth(2)`. | Added explicit ID `#tab-password` in `Login.vue` and updated QA runners. | Zero login failures in automated test runs. |
| **BUG-005** | **MEDIUM** | `Student/Assessments.vue` | Broken "Return to Dashboard" link | Link used nonexistent route `student.dashboard`. | Updated link to registered route `dashboard`. | Verified in student dashboard navigation. |
| **BUG-006** | **HIGH** | `scripts/vercel-migrate.php` | Danger of unintended production database migrations | Serverless entry script executed `artisan migrate --force` automatically without confirmation. | Added strict environment guard requiring `ALLOW_PRODUCTION_MIGRATIONS=true`. | Migration script safely aborts unless explicitly enabled. |
| **BUG-007** | **CRITICAL** | Documentation | Literal `APP_KEY` exposed in documentation | `DEPLOYMENT_PARITY.md` contained literal base64 encryption key in code block. | Sanitized literal key to `<redacted_application_encryption_key>`. | Grep search confirmed zero literal keys in `docs/`. |
| **BUG-008** | **HIGH** | Packaging Scripts | Risk of accidental secret/database inclusion in archives | No automated sanitizer existed for building release archives. | Created `package-release.php` and verified with `scan-archive.php`. | Negative scanner test proved detection and rejection of unsafe archives. |
