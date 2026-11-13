# File10 V12.1 — R109 expiry consent cursor retry failure (FROZEN BEFORE SOURCE EDIT)

**Exact baseline:** modern File10 Draft PR #18 SHA `0cb0b24c4734271baf61417d5f534dd1ec185b67` (independent of later File26 adapter PR stack). Canonical source: `video-wall-and-live-broadcasting/includes/class-vwlb-r109-rights-consent-replay-guard.php`.

## Requirements
V12.1 F10-FUT-022, F10-NFR-002/003/006/008, V12.1-STO-07/09/10: patient-case consent expiry or withdrawal must restrict media, revoke derivations and reconcile failures; no silent skip.

## Proven source mechanism (HIGH / source-code bug; no Live claim)
In `VWLB_R109_Rights_Consent_Replay_Guard::reconcile_consent_expiry()` the loop updated `$last=max($last,$id)` BEFORE verifying the transaction for that candidate. On transaction failure it emitted an operational signal then `continue`d. When a page contained exactly `PAGE_SIZE=100` rows, the final persisted cursor advanced beyond the failing row. The next scheduled scan used `id > saved_cursor`, so that failed expired-consent row was skipped until an eventual full reset. Under continuous backlog this can delay authoritative restriction, cache purge, and ledger reconciliation arbitrarily.

The current R109 `current_delivery_allowed()` also checks expired consent at playback; this reduces direct access risk but **does not prove derivative/CDN purge or durable expiry row reconciliation**, so the cursor defect matters independently. This is source-repository evidence, NOT a confirmed problem on installed website.

## Minimal repair
- Advance saved cursor only AFTER a successful, skipped-as-not-applicable, or already-concurrently-settled transaction, never before.
- On first typed per-row failure, break current batch and persist the last safely handled ID, even for 100-row pages; do not skip the failed ID and silently mark batch completed.
- Keep existing error signal and bounded batch size; retry the failed ID on next scheduled call.
- Add isolated executable PHP test with 100 real candidate IDs and a fault-injected failure at #2; assert saved cursor is 1 not 100; on retry #2 must be included again.
- Include test in canonical release runner; exact-head GitHub PHP 8.3/8.4 QA required.

## Remaining validation
Real WP MariaDB concurrency/consent provider/cache purge, staging and Live DB/plugin code unverified. Issue #13 WordPress/Jetpack disconnection and Issue #22 ruleset gate remain. No direct Production patch/deploy is authorized.
