# File 10 V12.3 — Source Review Pass B: deployment identity and adversarial governance

**Review date:** 2026-11-14. **Scope:** independent *risk lens* from Pass A (different test focus), but performed within the same technical work. This is **not** an independent human reviewer or Founder acceptance.

## Frozen source state

- `main` legacy source uses `video-wall/video-wall.php` at version 0.2.0; modern Draft source uses `video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php` at `1.2.25-rc1`.
- GitHub active ruleset `25451533` covers only `~DEFAULT_BRANCH` and an older `fix/file10-r270-r309...` ref, not current PR #32 head; required contexts `8.3.35 / 8.4.26` do not match actual `8.3.36 / 8.4.27` job names.
- WordPress.com Jetpack connection for the live site is `site_disconnected`; WPVibe HTTP 530 reported. Exact deployed plugin, MySQL schema, migration state and logs are unknown.
- File26 connector is deliberately `proposed`; no accepted durable tombstone/ACK/CDN purge.
- GitHub PR ancestry snapshot records 26 open Draft PRs; PR25 is divergent, but its R109 guard and cursor test **match exact Git blob SHA** in modern source, so no divergent branch merge is justified. Old PR20 is divergent and its connector blob differs.

## Second review — proven source defects and mitigations

1. **SRC-B01** — **Proven source collector scope defect:** original read-only collector only inspected modern slug. If a legacy `video-wall` build were deployed, this could have been misreported as absent modern File10. Added separate legacy header/active/network-active/fingerprint fields, explicit `file10_active_plugin_identity` with `legacy | modern | ambiguous_both_active | none_active | activity_unverified`. It does not assert which one is actually live. **Corrected in source.**
2. **SRC-B02** — **Proven source collector completeness defect:** modern plugin root symlink was not marked incomplete, unlike inner links. Added fail-incomplete detection for modern root, and consistently for legacy root/inner symlinks; no symlink traversal. **Corrected in source.**
3. **SRC-B03** — **Proven newly-authored fixture assertion defect:** PHP's null coalescing `??` substituted a default for a real `null` checksum, causing CI failures when a symlink was correctly detected. Replaced this with `array_key_exists` plus strict-null checks; both PHP 8.3 and 8.4 regression run Green. **Corrected in source.**
4. **SRC-B04** — **Release governance unresolved:** identified branch coverage and required-check drift on live GitHub ruleset, generated a read-only evidence snapshot and executable mismatch assessment. The assessment truthfully returns **BLOCKED** on current settings; synthetic corrected input still carries `effective_protection_verified_live=false`. Actual GitHub ruleset remains unchanged; **Issue #22 OPEN**.
5. **SRC-B05** — **External File26 acceptance unresolved:** recorded 64 negative acceptance scenarios including consent expiry, consumer offline, signed-grant replay, deleted-source resurrection and backup restore. No File26 indexing activation or consumer ACK. **Issue #26 OPEN**.
6. **SRC-B06** — **Deployment proof unavailable:** no authenticated WordPress environment; original exact-build Freeze has not run. Newly protected dual-identity collector is source-only. **Issue #13 OPEN**.

## Outcome and counts

- Source-tool defects proven and corrected in this pass: **3 groups** (B01–B03). CI failure B03 was purely test logic, not plugin runtime.
- Known blocking integration/administrative conditions: **3** (Issue #13, #22, #26); closed **0**.
- Novel live application defects proven/fixed: **0 proven / 0 fixed** (unknown deployed code does not establish absence of live defects).
- Production deployment and staging acceptance: **not performed**.
- Full independent File24 review rounds after final functional coding: **still required**.

## Hard no-go

Do not merge legacy main with advanced source by assumption; do not enable File26 proposed adapter; do not promote an unknown deployed plugin; do not claim real Provider, CF04, Staging or Live success from source CI. Live debugging must start with actual exact deployed artifact and DB/migration state; a mismatch mandates Deployment-Parity Audit first.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
