# File 10 Sequential Review — R19 Checkpoint

This checkpoint records the post-review correction state for R19 in the user-directed sequential discipline: complete review first, freeze findings, correct all findings, add regressions, run full QA, then begin the next review.

R19 finding: expired REST idempotency rows were treated as absent even when durable deletion was not proven. A database delete failure or cleanup race could therefore produce misleading in-progress/replay behavior and weaken the fail-closed mutation contract.

Correction: expired-row deletion is checked, the key/scope is re-read after cleanup, a still-present stale row is rejected with `vwlb_idempotency_expiry_cleanup_failed`, and a concurrently replaced valid row is evaluated normally.

No staging/live/production claim is made by this repository checkpoint.