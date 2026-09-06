# File 10 — R103 Final Frozen Findings — 2026-09-07

Baseline exact HEAD reviewed: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c` (`1.2.13-rc1`), exact-head File 10 Release QA run `34064117765` green on PHP 8.3/8.4 before R103 began.

## Review discipline

This document is the final R103 findings freeze. No R103 product-source correction had begun when these findings were finalized. The earlier cycle ledger entry contained the first four findings; the final frontend traversal completed before correction and added the fifth finding plus the direct-query/shortcode bypass detail under finding 3. This final freeze therefore governs the R103 correction batch.

## Frozen findings

1. **Partial schema drift can be falsely accepted.** `VWLB_DB::verify_schema_sql()` proves only table existence. Base, extension and Future installers call it and then persist their schema-version markers. The strict column/index verifier in `VWLB_R4_Migration_Guard` covers only podcast tables. A partial `dbDelta` result that leaves a table present but a required column/index absent can therefore be accepted as reconciled.

2. **Legacy migration truncates at 10,000 rows and then marks complete.** `VWLB_Compatibility::migrate_legacy()` reads only one `ORDER BY id ASC LIMIT 10000` page and unconditionally persists `vwlb_legacy_migration_complete`. Larger legacy datasets can be silently stranded without a cursor/checkpoint/resume path.

3. **Non-REST public media entry points still accept native numeric IDs.** WordPress rewrite rules for video/live/podcast accept `[A-Za-z0-9_-]+`, registered public query vars can be supplied directly, and the video/live shortcodes also accept `?video=` / `?live=` fallbacks. `VWLB_Repository::find()`, `VWLB_Videos::playback()`, `VWLB_Live::state()` and `VWLB_Podcasts::episode()` ultimately interpret numeric input as native primary keys. R102 hardened REST but did not close these frontend/query-var/shortcode paths.

4. **Podcast public DTO can fatally dereference an undefined database handle.** `VWLB_Podcasts::public_episode_dto()` uses `$wpdb->get_var()` when resolving `series_public_id` but does not declare `global $wpdb`; a normal series-linked public episode can therefore fail instead of rendering safely.

5. **Video Wall public-list DTO and template are inconsistent.** `VWLB_Repository::browse_videos()` deliberately removes native `thumbnail_id` and supplies a public-safe `thumbnail_url`, but `VWLB_Frontend::wall()` still reads `$item['thumbnail_id']` and calls `wp_get_attachment_image()` with it. This can emit an undefined-index warning and prevents the intended thumbnail from rendering after DTO redaction.

## Correction gate

All five findings must be corrected as the R103 correction phase, followed by a dedicated R103 regression gate plus the complete File 10 suite, PHP 8.3/8.4 exact-head release QA, deterministic package build, checksum/archive verification and package/source parity. R104 must not start before that gate is green.
