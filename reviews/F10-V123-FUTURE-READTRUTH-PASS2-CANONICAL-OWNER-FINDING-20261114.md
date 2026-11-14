# File10 V12.3 — second independent *source-perspective* review of Future read truth

**Scope:** Draft candidate code (after PHP CI passed at `a8ea60396a9dd51bf6a02bf85eb4b3876bff3f1d`), not deployed code. Different lens from Pass 1: **error classification, immutable owner identity, no mutation under partial failure, and rollback status**. This is **not** the independent human/File24 staging approval.

## Current proven corrections

- SQL row failure in Future production studio, watermark REST, source ID lookup, scene and transcript track maps now receives a typed 503, while an authentic unknown ID or denied policy remains 403/404/422.
- Source owner read failure during live/annotation/track enrichment now stops at 503 instead of returning empty success/404.
- Actual registered R79 watermark guard now checks owner SQL failure before issuing any grant; healthy absent media remains 404.
- R79 policy SQL failure was already typed 503; preserved.
- Watermark processor filter now distinguishes SQL outage from a legitimate absent (off) watermark policy.
- Policy setter now denies INSERT on unknown existing-policy state and refuses a false post-write success when verification fails.

## Additional gap discovered in this second review

**SRC-RI-008 — policy setter initial canonical owner read still conflates database failure with authorization denial.** `VWLB_Future_Intelligence::set_watermark_policy()` calls `self::video()` / `self::live()` (wrappers around `VWLB_Repository::find`) before testing `$object`, and returns 403 if the canonical owner read fails. This is not a privilege escalation, but it hides a recoverable 503 outage and mislabels it as an authentication defect. **Fix:** reset repository read-failure flag before the source lookup, return 503 on DB failure before write, retain original 403 for genuine missing/denied object; add video and live branch tests.

## Quality and release boundary

- Failed read is not a successful empty list, 404, 403 or null grant; no writes on failing preconditions.
- Provider cannot be activated by source gate, and receipt state cannot advance to 64/64 accepted from this isolated PHP suite.
- Original V12.3 primary ledger unchanged: source candidate mapping 64/64; individual/staging/live accepted 0/64.
- Exact deployed WordPress, PHP, DB schema, migration, package/ZIP and live logs still unavailable. GitHub source branch is **not live**.
- Full independent File24 reviews and Staging/Live end-to-end acceptance are pending outside scope.

**Fix SRC-RI-008 and rerun exact-head CI before concluding this source review.**
