# File 10 V12.1 — modern branch integration decision, frozen 2026-11-13

## Immutable source baseline and no-live boundary
- Governing requirements: SSH MASTER V12.1; File10 F10-FR-018, F10-NFR-001/002/007, F10-FUT-024 and File26 permitted-public search/canonical File10 owner, CF04 deletion/consent.
- Modern baseline: Draft PR #18 exact head `0cb0b24c4734271baf61417d5f534dd1ec185b67`; runtime metadata `1.2.25-rc1`, includes File06 independently reviewed source links, R310–R349 media validation exception regression, and the later sequential hardening lineage.
- Legacy proposed adapter: Draft PR #19 head `08fb8ff525b346be88038589aff5d1f979017c5b` (older `1.2.5-rc1` lineage).
- Independent File26 source correction: Draft PR #20 head `17fe8e960acbc24a070619a5107cde6e6b563ceb`, frozen root causes in `reviews/F26-PI-20261113-FROZEN-SOURCE-REVIEW.md`.
- Git comparison of PR #18 to PR #20: **diverged**, 635 commits behind modern PR #18, 16 ahead from old lineage, shared ancestor `c5431ea9ef505759a476b822c041131dca4bda13`. Not safe to merge the old entire branch.

## Integration decision before changes
Port **only** the reviewed File26 public-only proposed connector class, its standalone mocked regressions, and the frozen review findings into the modern baseline. Do not merge outdated plugin bootstrap, provider implementations, DB migrations, version metadata, or complete PR #19 ancestry.

## Safety constraints
1. Keep `status=proposed`, `deletion_semantics=owner_outbox_tombstone_gate_pending`; no shadow/public index activation.
2. Deliberately **do not autoload/register** the connector in the modern production composition root before File26 tombstone+reconciliation, approved provider/rights/consent staging tests and Founder acceptance. This is a code candidate only.
3. Keep `VWLB_VERSION=1.2.25-rc1` and existing modern review/CI gates unchanged; do not rename versions to branch labels.
4. Add the isolated File26 PHP regression to modern `tests/run-all.sh`, then verify exact-head PHP 8.3 and 8.4, source-to-package parity.
5. Real WP/File26 integration, index deletions, media permission revalidation, install/upgrade, backup/restore, live code and DB version remain unverified. Issue #13 HTTP 530 access blocker stays open; no production claim.

## Acceptance of this integration branch
- File06 changes stay intact; File26 class source is present but dormant.
- All new File26 stale-index/privacy tests pass.
- Modern File10 canonical QA suite passes on exact branch SHA.
- PR stays **Draft** targeting modern PR #18 until the full V12.1 cross-plugin and Live-First gates are satisfied.
