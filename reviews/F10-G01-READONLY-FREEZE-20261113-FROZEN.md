# File 10 G01 — deployed-state read-only evidence collector scope (FROZEN BEFORE CODE)

V12.1 acceptance gate: Live Reality Freeze precedes any Live defect diagnosis. Exact deployed plugin/version/expanded file hashes, DB/schema/option migration state, active dependencies and operational logs must be separated from GitHub repository evidence.

## Verified blocker
WordPress.com Jetpack-connected `www.sabrihomeopathy.com` is disconnected from remote site-scoped tools; WPVibe lists no connected sites, and attempted connection reported HTTP 530. GitHub Issue #13 remains OPEN. This creates an evidence-collection need, **not** permission to infer installed code from GitHub main or modify production.

## Proposed operator-only source artifact
Add `tools/file10-live-reality-freeze-readonly.php` intended for **authorized WP-CLI eval-file only** after server/staging access is restored. It will:
- hard-deny web/unauthorized invocations via WP_CLI + CLI guard;
- output plugin active status, header/version, read-only deployed-tree SHA-256 fingerprints and file counts;
- report allowlisted nonsecret `wp_options` schema/migration/version states (lock tokens NEVER output);
- inspect known File10 table presence, column names/fingerprint and bounded PK sample count (not raw rows);
- report WordPress/PHP/MariaDB version and debug/availability flags without password, token, path, individual media/patient data;
- output JSON with `deployed_vs_repo_parity='not_compared'` and `live_verification_status='snapshot_only'` until independent evidence confirms parity;
- perform **NO INSERT/UPDATE/DELETE**, no migration, no plugin activation, no network I/O, no automatic upload/public issue disclosure.

## Test boundary
Add source-only static safety/contract test and wire into canonical GitHub QA; PHP syntax & protected matrix required. Real WordPress permissions, DB queries, logs, backup/restore and actual live data remain unverified because Issue #13 blocks access. This code must not be silently deployed or executed by the WordPress front-end.

## Acceptance caveat
Do not copy patient rows or secrets to ChatGPT/GitHub issues. Operator must review output in private workspace and provide only necessary redacted metadata/aggregates. This is a diagnostic *preparation*, not a successful Live Reality Freeze.
