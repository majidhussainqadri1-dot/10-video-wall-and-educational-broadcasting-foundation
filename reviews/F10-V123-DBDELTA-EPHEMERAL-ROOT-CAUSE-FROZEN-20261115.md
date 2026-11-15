# File10 V12.3 — exact candidate schema migration failure, frozen before source patch

**2026-11-15. Original tested source SHA:** `cf1566208be6479e7462cb663b77bf5b2d4a2158`. **Environment:** fresh disposable Docker WordPress **7.1.4**, PHP **8.3**, MariaDB **11.8**, source-mount SHA256 parity confirmed; newly installed WordPress and no real users. **GitHub Actions:** ephemeral workflow `43666258176`, FAILED. **This is NOT an incident or proven issue on deployed sabrihomeopathy.com**.

## Reproduced evidence

1. Test returned `F10-EPHEMERAL INSTALL PASS`, `ACTIVATE PASS`, and a verified source/container plugin-entry SHA256.
2. Subsequent full WordPress request boot invoked `VWLB_R4_Migration_Guard::verify_release -> VWLB_DB::install_schema -> dbDelta` and emitted repeated MariaDB errors, e.g. `ALTER TABLE wp_vwlb_channels CHANGE COLUMN id id bigint unsigned NOT NULL AUTO_INCREMENT,public_id varchar(80)...` (invalid SQL) and `ALTER COLUMN id SET DEFAULT 'active'`.
3. Extension/Future `dbDelta` reported invalid index tokens `UNIQUE`, `KEY` in composite indexes, e.g. `ALTER TABLE wp_vwlb_live_poll_responses ADD PRIMARY KEY (id,UNIQUE,user_id,...)`. These are not acceptable upgrades; the source QA had previously missed this.
4. The final ephemeral assertion `anonymous user was not denied on a protected poll creation route` failed, but the response status/code was not included in the initial diagnostic, so **the underlying cause of this particular endpoint result is not yet proven**. It must be measured independently after correcting the schema SQL parser. Do not attribute it to authorization bypass without evidence.

## Source root cause, not a deployed defect determination

**SRC-DB-001:** WordPress `dbDelta()` expects a CREATE TABLE declaration with **each field and index definition on its own line**. In File10 candidate:
- `VWLB_DB::schema_sql()` uses one long comma-separated CREATE TABLE body per table, later supplied unchanged to `dbDelta`;
- `VWLB_Extensions` and `VWLB_Future_Intelligence` SQL is multiline but some declarations place `PRIMARY KEY`, `UNIQUE KEY` and `KEY` definitions on a **single line**;
- `VWLB_Podcasts::install_schema()` places multiple column/index declarations on single lines.

This is a **format incompatibility between File10 candidate DDL and `dbDelta`'s line-oriented diff parser**; it is not initially a MariaDB syntax problem for normal CREATE TABLE SQL itself. The existing `verify_schema_sql` performs table/column/index existence checks but it does not prevent this malformed ALTER on a subsequent installation/reconciliation.

## Corrective plan (safe source candidate only)

- Keep canonical `schema_sql()` unchanged for declarative schema verification.
- Add one **pure, deterministic, comment-free** formatter to `VWLB_DB` which splits only commas at the outer CREATE TABLE body depth (never comma inside `DECIMAL(5,2)` or composite index parentheses), tracks quote literals and backticks, places each field and index on its own physical line and normalizes PRIMARY/UNIQUE/KEY spacing for `dbDelta`.
- Invoke the formatter before every relevant `dbDelta` call (base, extension, Future, podcasts); do not change schema versions or table definitions.
- Add isolated PHP negative/positive grammar tests (composite index and decimal preservation, rejection on malformed/unsupported DDL) **before** the source patch.
- Repeat fresh real WordPress+MariaDB activation/boot/idempotent migration test and exact-head full PHP matrix. Investigate the remaining REST status **separately**, logging only safe status/code without any credentials.

**CEN-REL-001 remains blocked:** Live plugin, DB/schema/migration, provider, Source–Live parity, two independent Staging reviews/Founder signoff are still unknown; issues #13/#22/#26 remain OPEN.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
