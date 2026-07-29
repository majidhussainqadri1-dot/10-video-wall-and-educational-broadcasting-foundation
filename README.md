# File 10 — Video Wall and Educational Broadcasting Foundation

Controlled source repository for **File 10** of the **Sabri Social Homeopathy Platform**.

## Current corrected release

- Plugin: `Video Wall and Educational Broadcasting Foundation`
- Corrected version: `0.2.0`
- WordPress: `6.0+`
- PHP: `7.4+`
- Text domain: `video-wall`
- Corrected package: `packages/10-video-wall-and-educational-broadcasting-foundation-0.2.0.zip`
- Corrected SHA-256: `eebed41b671b9eefcae4933000e57e1a15076a1aed1883e1fe0b94b4ad8e6c68`

The repository also preserves the project-supplied Version `0.1.0` archive byte-for-byte under `packages/`, together with its original checksum and baseline source tree.

## Corrective scope

Version `0.2.0` addresses the audit blockers found in Version `0.1.0`:

- WordPress admin publishing bypass;
- unsafe activation-time page overwriting;
- missing moderation and report audit history;
- private Saved Videos caching exposure;
- incomplete privacy export/erasure and stale reaction counts;
- partial public posts after upload failure;
- false blanket “Verified Doctor” labels;
- false AJAX report-success confirmations;
- claimed but absent viewing history and resume progress;
- untracked source architecture;
- orphaned data, missing schema upgrades, unbounded admin/public listings, N+1 state queries, weak URL/duration validation, inaccurate structured data, and accessibility gaps.

## Repository structure

- `video-wall/` — corrected, reviewable plugin source;
- `packages/` — preserved original ZIP and corrected deterministic ZIP;
- `tests/` — helper and static contract tests;
- `tools/build-package.sh` — deterministic package builder;
- `docs/CORRECTION-AUDIT-0.2.0.md` — defect-to-correction traceability;
- `docs/STAGING-ACCEPTANCE-0.2.0.md` — remaining real WordPress acceptance gates;
- `CHECKSUMS.sha256` — original Version 0.1.0 provenance;
- `CHECKSUMS-0.2.0.sha256` — corrected source/package integrity.

## Validation completed locally

- PHP syntax: `10/10 PASS` on PHP `8.4.16`;
- JavaScript syntax: `PASS` on Node.js `22.16.0`;
- strict duration helper tests: `PASS`;
- official/fake YouTube and Vimeo URL tests: `PASS`;
- static corrective contracts: `PASS`;
- corrected ZIP integrity: `PASS`;
- deterministic ZIP SHA-256 recorded.

## Acceptance boundary

Version `0.2.0` is a **code-corrected release candidate**, not yet a production acceptance declaration. It still requires a fresh and upgrade installation on the approved Hostinger staging site, companion-plugin integration, role matrix testing, media tests, privacy requests, cache verification, responsive/accessibility review, backup restoration, rollback, and founder acceptance before merge to production or live deployment.
