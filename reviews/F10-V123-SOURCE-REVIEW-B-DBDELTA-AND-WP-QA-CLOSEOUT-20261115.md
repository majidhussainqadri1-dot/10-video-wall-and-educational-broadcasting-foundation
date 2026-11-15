# File10 V12.3 — second source-review closeout and isolated WordPress evidence

**Date:** 2026-11-15. **Scope:** modern candidate advanced Draft source only. The original founder master plan is **V12.3**, document SHA256 `c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf`. **Not an independent File24 human/Founder review, and not Staging/Live.**

## Sequential review outcomes

- **First source review:** canonical transcript search/track DB failures, poll owner/option partial-acceptance, registered R77 pre-callback, and correction false publication event. Frozen root causes `SRC-TP-001..006`; test-first red workflows and green after minimal source corrections. See `reviews/F10-V123-TRANSCRIPT-POLL-CORRECTION-SOURCE-REVIEW-A-20261115.md`.
- **Second adversarial source review:** creator permission vs content review of AI/imported corrections (`SRC-TP-007`), REST vs internal domain option cardinality (`SRC-TP-008`), and need to test actual WordPress/MySQL migration rather than isolated PHP mocks.
- **Fresh disposable WordPress review:** exact source SHA `cf1566208be6479e7462cb663b77bf5b2d4a2158` produced a repeatable `dbDelta` schema-diff error against isolated WordPress 7.1.4/MariaDB 11.8 (`SRC-DB-001`). The root cause was frozen **before** modifications in `reviews/F10-V123-DBDELTA-EPHEMERAL-ROOT-CAUSE-FROZEN-20261115.md`. Modified only the canonical DDL-to-dbDelta presentation layer, across base, extension, Future and podcast schema, without rewriting schema contracts/version constants or touching any deployed site.

## Test-first defect ledger for THIS iteration

| Finding | Source correction status | Test scope |
|---|---|---|
| SRC-TP-001: search owner/query SQL read truth | Fixed, regressed | actual PHP classes |
| SRC-TP-002: transcript index owner/track SQL read truth | Fixed, regressed | actual PHP classes |
| SRC-TP-003: poll owner/root/options read failures | Fixed, regressed | actual PHP classes |
| SRC-TP-004: partial multi-choice answer after bad/SQL-failed option | Fixed, regressed | actual PHP classes + real MySQL journey |
| SRC-TP-005: R77 registered poll-create owner 403 on SQL outage | Fixed, regressed | actual PHP class + real REST denial |
| SRC-TP-006: reviewed-only correction false Published event | Fixed, regressed | actual PHP class |
| SRC-TP-007: AI/imported correction bypasses human review transition | Fixed, regressed | actual PHP class |
| SRC-TP-008: 21-option bypass directly in domain method | Fixed, regressed | actual PHP class + real domain 21-option check |
| SRC-DB-001: malformed dbDelta ALTER on real WP reload | Fixed in candidate, regressed | real disposable WP+MariaDB, repeated loads, both PHP versions |

**Nine distinct source defect groups identified, nine corrected and tested in source/disposable CI, zero proven or verified as Live defects.** Test-harness setup corrections (adding Idempotency-Key to the authorization assertion and generating safe Docker container names) are not additional application bug fixes and are excluded from the above nine.

## Exact successful build and runtime receipts

**Source/Package QA:** GitHub Actions `43667329424` for SHA `7f3650a3d622c9ac9f88c2c4f8a8b85545cfda71` — all required PHP 8.3.36 / 8.4.27 and stable aggregate source gates **success**.

**Disposable real WordPress/MySQL Integration:** run `43667329376`, same exact SHA — **PHP 8.3.36 and PHP 8.4.27 both success** using official WordPress 7.1.4 Apache image + fresh MariaDB 11.8. Each real isolated container verified:

1. Entry-file SHA256 in container matches same source candidate file; no real production database, user or credential.
2. Fresh WordPress core install and File10 plugin activation.
3. 11 core/Future media, transcript and poll tables, 3 canonical schema version options and repeated idempotent schema reconciliation.
4. Two independent WordPress reloads/reconciliation checks with **zero detected WordPress database SQL warnings**.
5. Public capabilities REST endpoint explicitly says `implementation_presence_not_runtime_readiness`, and anonymous poll-create attempts are denied.
6. Synthetic File00 role fixture: real MySQL insert of a synthetic public educational video and live session; index/search reviewed Urdu transcript; current owner visibility restriction immediately suppresses search; create education-only poll, persist 2 selected answers; reject unknown 2nd option without changing previous answers; reject oversized poll.

`tests/file10-v12-3-ephemeral-wordpress.php`, `tests/file10-v12-3-ephemeral-domain.php`, `tests/file10-v12-3-ephemeral-runtime.sh`, `.github/workflows/file10-ephemeral-wp.yml` contain reusable, disposable tests. **No external Provider, File06/11/26 integration, CF04 storage or genuine File00 identity verification is tested in these disposable journeys.**

## Explicit non-acceptance

- Original File10/CF04 **64 requirement IDs remain 64 source-mapped / 0 individually real-environment approved**; source CI and disposable integration cannot change their Staging/Live acceptance statuses.
- **GitHub Issue #13:** Hostinger/WordPress access still disconnected with HTTP 530; exact deployed files, plugin version, schema, migrations and runtime logs UNVERIFIED.
- **GitHub Issue #22:** Source stable aggregate QA exists but exact GitHub branch-protection ruleset still needs admin adjustment and verification.
- **GitHub Issue #26:** File26 durable tombstone/consumer ACK/CDN purge and restore reconciliation still unaccepted, adapters remain inactive.
- No production package parity, official Staging, external-media transcode/provider, two independent File24 review rounds, Founder approval, real Deployment, live re-test or rollback rehearsal. Keep Draft-only.

### Mandatory final runtime fields

| Field | Status |
|---|---|
| Repository main HEAD | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3` (legacy source) |
| Advanced source head at evidence run | `7f3650a3d622c9ac9f88c2c4f8a8b85545cfda71` |
| Deployed Version | UNVERIFIED |
| DB Version | UNVERIFIED |
| Migration State | UNVERIFIED |
| Live Verification Status | NOT PERFORMED |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
