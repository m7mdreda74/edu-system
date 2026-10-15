# Production Rollback Strategy & Incident Response

**Platform:** Al-Tafawwuq Educational System  
**Trigger Conditions:** Critical service degradation, database connection failure, elevated 5xx error rate (>1%), broken authentication.  

---

## 1. Instant Vercel Instant Rollback (Zero Downtime)

Because Vercel creates immutable deployment artifacts for every build, rolling back to the previously stable production deployment can be accomplished in seconds:

### Via Vercel Web Console
1. Navigate to the **Deployments** tab of the `altafawwuq` project on [vercel.com](https://vercel.com).
2. Locate the previous stable deployment (the last deployment known to be healthy).
3. Click the three dots icon (`...`) and select **Instant Rollback**.
4. Confirm rollback. Traffic will instantly route to the prior immutable bundle.

### Via Vercel CLI
```bash
vercel rollback <previous-deployment-id>
```

---

## 2. Database Restoration Procedure

If a failed release included corrupted schema changes or data modifications:

1. **Enable Maintenance Mode:**
   If possible, set `APP_MAINTENANCE_DRIVER=file` or route traffic to a static maintenance page.
2. **Restore from Pre-Deployment MySQL Dump:**
   ```bash
   mysql -u <prod_user> -p<prod_password> -h <prod_host> altafawwuq < backup_pre_release_<timestamp>.sql
   ```
3. **Verify Database Integrity:**
   ```bash
   php artisan migrate:status
   ```

---

## 3. Post-Rollback Clean-Up

1. **Clear Stale Application Cache:**
   ```bash
   # Invalidate cached query results in database cache table
   php artisan cache:clear
   ```
2. **Verify Health Endpoints:**
   ```bash
   curl -i https://altafawwuq-one.vercel.app/health
   ```
3. **Log Incident:**
   Record all error traces, affected user IDs, and duration in `docs/final-audit/BUG_TRACKER.md`.
