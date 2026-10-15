# Production Deployment Plan & Runbook

**Target Platform:** Vercel Serverless + Managed Cloud MySQL  
**Release Target:** v1.0.0 Stable  
**Rule:** No deployment shall proceed without explicit authorization by the system owner.  

---

## 1. Phase 1: Pre-Deployment Preparation

1. **Lock Git Working Tree:**
   Ensure all changes are committed and verified against local CI.
   ```bash
   git status
   git diff
   ```
2. **Execute Full Automated Test Suites:**
   ```bash
   # Run backend Pest test suites
   php vendor/pestphp/pest/bin/pest --compact tests/Unit tests/Feature
   
   # Verify code style with Pint
   php vendor/bin/pint --test
   
   # Build production frontend assets
   npm run build
   ```
3. **Generate Sanitized Release Package & Self-Audit:**
   ```bash
   php scripts/package-release.php
   ```
   *Verify that the scan reports ZERO secret or database leaks in the archive.*

---

## 2. Phase 2: Database Protection & Backup

1. **Perform Full Database Snapshot:**
   Before any deployment, execute a full MySQL dump of the production database:
   ```bash
   mysqldump -u <prod_user> -p<prod_password> -h <prod_host> altafawwuq > backup_pre_release_$(date +%Y%m%d_%H%M%S).sql
   ```
2. **Verify Migration Status:**
   Ensure no unapproved migrations are executed. If migration is required, set `ALLOW_PRODUCTION_MIGRATIONS=true` only during the controlled maintenance window.

---

## 3. Phase 3: Vercel Production Release

1. **Verify Environment Variables in Vercel Dashboard:**
   - Confirm `APP_ENV=production`
   - Confirm `APP_DEBUG=false`
   - Confirm `CACHE_STORE=database`
   - Confirm `SESSION_DRIVER=database`
   - Confirm `ALLOW_PRODUCTION_MIGRATIONS=false`
2. **Deploy to Vercel:**
   Push the approved commit or deploy via Vercel CLI:
   ```bash
   vercel --prod
   ```

---

## 4. Phase 4: Post-Deployment Smoke Test (Immediate Verification)

1. **System Health Check:**
   ```bash
   curl -i https://altafawwuq-one.vercel.app/health
   # Must return HTTP 200 with {"status":"ok","checks":{"database":"ok","cache":"ok"}}
   ```
2. **Search Autocomplete Verification:**
   ```bash
   curl -i https://altafawwuq-one.vercel.app/api/search-autocomplete?q=رياضيات
   # Must return HTTP 200 with matching subjects/teachers
   ```
3. **Automated Read-Only Role Smoke:**
   ```bash
   export QA_BASE_URL="https://altafawwuq-one.vercel.app"
   export QA_ALLOW_PRODUCTION="true" # Only run read-only smoke!
   
   node qa-playwright/role-smoke.js
   ```
