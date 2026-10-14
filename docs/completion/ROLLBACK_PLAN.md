# Rollback Plan

## Application rollback

If a deployment causes application errors, route the platform to the last known-good immutable deployment through the hosting provider’s reversible deployment mechanism. Preserve logs and the failed deployment identifier for diagnosis.

## Database rollback

Do not blindly run migration rollbacks in production. First stop writes if necessary, preserve a backup/snapshot, confirm the migration’s documented down-path, and obtain release-owner approval. For destructive or data-transforming migrations, restore or forward-fix from the backup rather than guessing.

## Queue/cache/storage incidents

- Pause the affected queue consumer only if it prevents duplicate or unsafe delivery; preserve pending jobs.
- Do not flush a shared production cache as a first response when it may contain locks or rate-limit state.
- Disable the affected upload feature through its approved configuration flag if private storage integrity is uncertain; do not delete stored objects.

## Verification after rollback

Run health/security endpoints, authentication, one read-only journey per role, queue/cache checks, and the production error dashboard. Record whether the issue is code, configuration, data, or external service related.
