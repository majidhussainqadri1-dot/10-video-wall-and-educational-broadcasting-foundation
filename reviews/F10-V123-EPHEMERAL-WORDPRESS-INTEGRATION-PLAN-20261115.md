# File10 V12.3 — disposable WordPress+MariaDB runtime QA plan

**Date:** 2026-11-15. **Exact source baseline:** `782696c9fd34bbf5a8bac44d9b291aa2b1ff8e07` (Draft after 8 source transcript/poll corrections). **Original V12.3:** F10-NFR-007 (migration), NFR-009 (WordPress/PHP compatibility), NFR-008 (operability), CEN-REL-001 (staging/live independent signoff). Frozen V12.3 compatibility baseline WordPress 7.0.1/PHP8.3; testing a *newer* WordPress release does not modify that source baseline.

## Evidence gap and root cause of test insufficiency

Existing 64/64 source mapping, 39 former isolated PHP assertions, 35 new transcript/poll + 18 correction assertions and PHP matrix/package parity all operate in isolated mocks or static inspection. They cannot establish that WordPress actually activates this modern candidate, creates the requisite MySQL tables, exposes the registered REST endpoint, or preserves a migration/version state. The site itself is disconnected and returns HTTP 530 through WPVibe; no authorized live runtime is available.

## Safe additional QA scope

- Create an **ephemeral** fresh WordPress 7.1.4 / PHP 8.3 Apache container and independent MariaDB 11.8 container, **outside** the real Hostinger site, using random temporary DB and WordPress admin credentials.
- Mount exact current File10 candidate plugin source **read-only**, no real users, no external streaming/AI/CDN/CF04/File26 service, no production URL, no copied data.
- Record WordPress/PHP versions, actual plugin activation outcome, base/extension/Future schema options, expected tables, registered REST capabilities, anonymous denial on a protected operation, and a second idempotent schema reconcile.
- Compare SHA256 of plugin entrypoint inside test container vs GitHub runner source entrypoint. Use dedicated GitHub Actions workflow; failure means only **ephemeral integration not accepted**, not a live fault.
- Tear down containers, network and test-only credentials on success/failure. Avoid publishing logs with any credential, WP user data or database dumps.

## Disposition

This is **additional isolated integration QA**, not the V12.3-authorized Staging or Live acceptance. Do not add ephemeral results to 0/64 individually/real-stage/real-live approved. Issues #13, #22 and #26 remain open. If the isolated runtime reveals a defect, freeze exact source reproduction before fixing it, then rerun the whole QA suite. No merge/deploy.
