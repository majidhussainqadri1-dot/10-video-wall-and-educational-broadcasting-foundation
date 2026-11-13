# File10 V12.1 unified source candidate — consent/owner-search reconciliation FREEZE

Date: 2026-11-13. Source only: **no Live deployed-state proof**.

## Immutable parents before any source change
- Main modern File26 connector lineage: File10 Draft PR #24 exact HEAD `2e04a6ae8a6dafb95fb90cfa3962f7c4dd8625d9`, itself stacked PR #23 → #21 → #18.
- Independent patient-consent cursor root-cause fix: File10 Draft PR #25 exact HEAD `54f34b8cf19d7fe1353176f17df2007ef385e61d`, based directly on modern PR #18 `0cb0b24c4734271baf61417d5f534dd1ec185b67`.
- These are two *different* stacks. Treating their CI successes as one tested release is forbidden.

## Safe integration decision
Create a new branch from the File26 PR #24 exact source. Port **only**
1. `video-wall-and-live-broadcasting/includes/class-vwlb-r109-rights-consent-replay-guard.php` from PR #25 (no other runtime file),
2. `tests/file10-r109-consent-expiry-cursor.php` (isolated retry regression), and
3. one runner invocation for that test.

Preserve current File26 proposal, File06 owner links, all File10 advanced hardening, plugin/schema versions, security manifests and provider configuration unchanged.

## Evidence / acceptance
- Existing independently tested consent source SHA `54f34b8cf19d7fe1353176f17df2007ef385e61d` passed PHP 8.3.36/8.4.27 Release QA run `43368577001`; this alone does not accept the combined candidate.
- New branch must run **its own exact-HEAD full** Release QA PHP 8.3/8.4, deterministic package, checksum/source parity, and assert the versioned File26 proposal remains deliberately dormant.
- The combined PR remains Draft and cannot merge into old main or deploy without protected rule coverage (Issue #22), actual Staging/DB/provider/deployed artifact parity (Issue #13), rights/consent outbox delivery/ack and CDN index purge acceptance (Issue #26), Founder approval.

## Not a Live fix
Deployed Version, DB Version, Migration State and Live Verification Status remain unverified. Repository bugs are source facts only.
