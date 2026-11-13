# F10-G01-SYMLINK-QA-001 — test assertion bug, freeze before correction (2026-11-13)

Exact tested source SHA `4764af57157481ebcc7eaa0981c7e43b232fc3c8`, GitHub Actions run `43377017404`, PHP 8.3.36 and PHP 8.4.27 failed at the same new symlink assertion after all earlier File10 suites passed.

The test PHP expression:
`($j["plugin"]["expanded_tree_sha256"]??"sentinel")!==null`
**cannot** be false when `expanded_tree_sha256` is null, since PHP's null-coalescing operator returns `"sentinel"` for a present-but-null key. It therefore rejected the **correct** state of a deliberately incomplete fingerprint. This is a **test-assertion defect**, not evidence that the source symlink guard failed.

Fix the assertion to check both `array_key_exists("expanded_tree_sha256", $j["plugin"])` and strict `===null` without `??`. Preserve negative symlink fixture and regular-only fixture. Run entire exact-head QA for both PHP versions. Do not change the already-reviewed runtime checksum fix or declare any Live parity.

The original missing-symlink source defect remains evidenced separately in `reviews/F10-G01-SYMLINK-FINGERPRINT-20261113-FROZEN.md`. Deployed WordPress still inaccessible (Issue #13).
