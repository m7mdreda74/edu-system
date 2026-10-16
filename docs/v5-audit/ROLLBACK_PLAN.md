# V5 Production Rollback Strategy & Disaster Recovery

**Platform:** Al-Tafawwuq Educational System  
**Audit Cycle:** V5 Final Completion & Hardening Audit  
**Target:** Vercel Production + Managed MySQL  

---

## 1. Instant Vercel Rollback (Zero Downtime)

Because Vercel maintains immutable deployment bundles for every release commit, traffic can be redirected to the prior healthy build in seconds:

### Web Console Method
1. Navigate to the **Deployments** page of the `altafawwuq` project on [vercel.com](https://vercel.com).
2. Locate the previous healthy deployment (e.g. deployment `396c583`).
3. Click the context menu (`...`) and select **Instant Rollback**.
4. Confirm the rollback. Vercel edge routers immediately redirect 100% of incoming requests.

### CLI Method
```bash
vercel rollback <previous-deployment-url-or-id>
```

---

## 2. Database Recovery Procedure

If a database schema change or corrupting query occurred:

1. **Restore Pre-Release Snapshot:**
   ```bash
   mysql -u <prod_user> -p<prod_password> -h <prod_host> altafawwuq < backup_pre_v5_<timestamp>.sql
   ```
2. **Invalidate Application Cache:**
   ```bash
   # Clear the shared database cache table
   php artisan cache:clear
   ```
3. **Verify Health Endpoint:**
   ```bash
   curl -i https://altafawwuq-one.vercel.app/health
   # Verify database: ok and cache: ok
   ```
