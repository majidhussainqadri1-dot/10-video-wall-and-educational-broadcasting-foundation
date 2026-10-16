# Public-Safe Operational Runbook — File 10

1. Keep provider secrets outside GitHub.
2. Confirm backup and restore before activation.
3. Install only on staging first.
4. Open Video & Live → Diagnostics.
5. Confirm schema, cron, dependency health and zero unexplained dead-letter items.
6. Run role, media, live, privacy, accessibility and rollback acceptance.
7. Use Safe Mode to close protected writes while preserving approved public reading during a material dependency failure.
8. Use Emergency End for an unsafe live broadcast; record a reason and review the audit trail.
9. Retry dead jobs/outbox only after the underlying cause is corrected.
10. Do not merge or deploy until the staging checklist and Founder sign-off are complete.

Sensitive incident procedures, credentials and internal infrastructure topology must remain outside the public repository.
