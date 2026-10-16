# V5 Pre-Release Checklist & Deployment Procedures

**Platform:** Al-Tafawwuq Educational System  
**Audit Cycle:** V5 Final Completion & Hardening Audit  
**Deployment Target:** Vercel Serverless Production  

---

## 1. Pre-Deployment Quality Checklist

- [x] **Git Tree Cleanliness:** No unstaged or untracked changes remaining on working tree.
- [x] **Secret Audit:** Confirmed no `.env*`, `*.sqlite*`, or private files tracked by Git.
- [x] **Sanitized Docs:** Verified zero literal `APP_KEY` or passwords written in `docs/`.
- [x] **Archive Packaging Scanner:** Passed negative test proving rejection of unsafe archives.
- [x] **Production Migration Lock:** `scripts/vercel-migrate.php` locked by `ALLOW_PRODUCTION_MIGRATIONS=true`.
- [x] **Code Formatter:** All modified PHP files pass `php vendor/bin/pint --test`.
- [x] **Backend Tests:** 357 tests passed, 2,535 assertions.
- [x] **Frontend Build:** `npm run build` generates valid bundles in `public/build`.
- [x] **Live Role Smoke:** 100% pass across student, teacher, admin, and multi-viewport tests.

---

## 2. Release Execution Runbook

When authorized by the platform owner:

1. **Merge Feature Branch to `main`:**
   ```bash
   git checkout main
   git merge feat/v5-hardening-audit
   git push origin main
   ```
2. **Automated Vercel Deployment:**
   - Vercel automatically detects the push to `main` and initiates the production build.
   - Vite compiles the frontend assets (`npm run build`).
3. **Post-Release Smoke Verification:**
   ```bash
   # 1. Health check
   curl -i https://altafawwuq-one.vercel.app/health

   # 2. Search autocomplete
   curl -i "https://altafawwuq-one.vercel.app/api/search-autocomplete?q=رياضيات"

   # 3. Read-only role smoke
   export QA_BASE_URL="https://altafawwuq-one.vercel.app"
   export QA_ALLOW_PRODUCTION="true"
   node qa-playwright/role-smoke.js
   ```
