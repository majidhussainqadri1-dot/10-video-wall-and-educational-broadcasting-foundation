# File 10 ↔ File 06 QA integration review — 2026-11-13 (frozen before runner change)

## Exact source inspected
- Advanced source baseline: Draft PR #18 `0cb0b24c4734271baf61417d5f534dd1ec185b67`.
- `class-vwlb-file06-links.php::safe_video` invokes `VWLB_Security::can_view`.
- Advanced `VWLB_Security::can_view` **already invokes** `VWLB_R109_Rights_Consent_Replay_Guard::current_delivery_allowed`. Therefore, a separate File06 consent check would duplicate existing central safety law. **No speculative duplicate runtime patch is authorized.**
- `tests/file06-approved-video-links.php` is present in source (File06 approved/current rights/version and independent reviewer tests), but `tests/run-all.sh` did not call it.

## Frozen finding F06-QA-001 (medium, source CI coverage)
The canonical File 10 Release QA reported green without executing the available dedicated File06 link acceptance script. This is a **test runner integration gap**, not proof of a production consent defect.

## Minimal corrective scope
- Include the existing standalone `php tests/file06-approved-video-links.php` in the canonical test runner.
- Do not modify File06/Video rights/consent business logic, provider wiring, deployment, DB or migrations.
- Verify exact-head PHP 8.3/8.4 GitHub Action and package/source parity.

## Evidence limitation
Existing test is mocked and checks File06 owner contract; it does **not** prove real File06 plugin, author credentials, WP database, storage or live behavior. Issue #13 remains blocking staging/live reality freeze.
