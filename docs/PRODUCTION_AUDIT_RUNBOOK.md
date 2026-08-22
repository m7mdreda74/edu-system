# Altafawwuq production audit runbook

This runbook is intentionally operational. Run it against the production deployment with an approved operator account; never run `migrate:fresh` or a demo seeder against production.

## 1. Secrets and local artifacts

- Rotate `APP_KEY`, database credentials, mail credentials, `CRON_SECRET`, Jitsi JWT secrets, Turnstile secret, storage tokens, and every payment/provider secret that appeared in the old archive.
- Remove `altafawwuq.zip` only after the required evidence has been copied to an approved encrypted store. The archive must not be uploaded to Git, Vercel, support tickets, or chat.
- Confirm `.env`, SQLite files, Laravel logs, `*.zip`, and deployment caches are absent from the deployment artifact. Run `php scripts/scan-secrets.php` in CI.

## 2. Database deployment

1. Take a verified backup and record its restore test.
2. Confirm the application is in maintenance/read-only mode if the deployment process requires it.
3. Run `php artisan migrate --force` once against the intended production database.
4. Confirm these migrations are applied in order:
   - `2026_08_22_000100_create_audit_events_table`
   - `2026_08_22_000200_add_payment_integrity_fields`
   - `2026_08_22_000300_add_current_query_indexes`
5. Check that `audit_events` is writable, `payments.receipt_sha256` is unique, and the new indexes exist.
6. Do not delete demo users/payments automatically. First export a reviewed list, obtain approval, then use a targeted reversible cleanup plan.

For a non-destructive summary of the same deployment prerequisites, run `php artisan audit:production-readiness`. It reports presence/absence only and never prints secret values.

## 3. Required production environment

```dotenv
APP_ENV=production
APP_DEBUG=false
JITSI_REQUIRE_AUTH=true
JITSI_FILE_RECORDINGS_ENABLED=true
JITSI_FILE_RECORDINGS_AUTO_START=true
JITSI_RECORDING_ALLOWED_HOSTS=recordings.example.com
JITSI_WHITEBOARD_ENABLED=true
JITSI_WHITEBOARD_COLLAB_SERVER=https://collab.example.com
ADMIN_SENSITIVE_PASSWORD_TIMEOUT=900
```

Use the actual HTTPS Jitsi/recording/collaboration hosts. The recording service must be configured with Jibri/JaaS (or the approved server-side equivalent); enabling the Laravel flag alone does not create a hosted recording.

## 4. Live-class smoke test

1. A teacher opens a scheduled room; the server moves it to `live` only after room entry.
2. Confirm the recording control reports server-side `file` recording and starts automatically.
3. A student joins only with a confirmed booking and an active subscription.
4. The teacher ends the class. Jitsi emits `recordingLinkAvailable`; the browser posts the HTTPS link automatically to the protected endpoint.
5. Confirm the link is stored, a protected `GroupMaterial` is created, and `is_published_as_lesson` is true.
6. Confirm the student plays the lesson through the in-platform HTML5/Plyr player and that another account cannot reuse its signed URL.
7. Test whiteboard open, collaboration, attendance join/leave, and the forced-close path.

## 5. Admin and payment smoke test

- A production admin mutation without recent password confirmation must redirect to `/confirm-password`; confirmation lasts only the configured short window.
- Approve and reject a Vodafone Cash receipt from the admin UI. Verify the audit event contains hashes/identifiers, not raw receipt notes or payment secrets.
- Verify the table masks the sender phone and the receipt endpoint is private, `no-store`, MIME-checked, and outside the public disk.
- Submit the same receipt twice and confirm only one payment exists.

## 6. Headers and monitoring

- Check the real HTTPS response for CSP, HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and the absence of `X-Powered-By`.
- Monitor failed logins, 423 password-confirmation responses, payment duplicate attempts, receipt access, Jitsi recording failures, and `audit_events` growth.
- Run the responsive smoke matrix at 375, 768, 1024, and 1366 pixels for `/`, `/login`, `/register`, dashboard, settings, payments, and the live room.

## Rollback

Keep the backup and migration output. If rollback is approved, use the migration rollback commands for these three migrations in reverse order and verify the application version matches the database schema. Never roll back by deleting users, payments, receipts, or audit events.
