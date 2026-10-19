# File 10 R149 — exact-PHP toolchain drift finding and correction
Read-only finding frozen from File 10 correction head `ca2ec47dc35abb9d8ef4e5bf043384840d9ee5a8` and GitHub Actions run `39202465952`; both matrix jobs failed before the application/regression suite could execute.

## Proven external environment change
- Canonical workflow expected PHP `8.3.33` and `8.4.25` on `ubuntu-24.04`; exact patch validation deliberately failed rather than silently accepting drift.
- Runner log on 2026-10-19 showed actual PHP `8.3.35` (PHP 8.3 job) and `8.4.26` (PHP 8.4 job).
- Root cause is exact-version/toolchain mismatch, **not** File 11 contract bridge failing.
- R127 and R128 static gates hard-code the formerly approved exact PHP matrix and canonical publishing job; they must be reconciled with the new validated patch versions.

## Corrective scope
Update only canonical Release QA's exact PHP matrix and artifact publication condition to the verified `8.3.35`/`8.4.26`, and update the two linked R127/R128 regression expectations together. Keep the exact-version assertion, pinned GitHub Actions, fixed runner OS, deterministic build, checksum, source/package parity and SHA-bound artifact identity. No PHP runtime product functionality is patched for this environmental issue.

## Validation / live limits
Run full File 10 Release QA on the exact new correction commit. Real PHP staging/WordPress File 11 integration and R131 branch-protection repair remain unverified independent gates. No live-deployed or operational claim.
