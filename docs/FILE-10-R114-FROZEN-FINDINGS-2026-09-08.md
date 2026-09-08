# File 10 R114 — Frozen Findings

Baseline exact reviewed HEAD: `95a00e906ba69f814137c741ca4e346c12d5cb3f` (`1.2.24-rc1`).

Method: complete read-only review first. No product correction was applied during this review. This ledger freezes the complete proven R114 findings before correction begins.

## R114-01 — Video publication caption gate can convert a database read failure into a false policy rejection

`VWLB_Videos::publication_gate()` uses raw `$wpdb->get_var()` for the published-caption count and immediately casts the result to integer. A database error therefore becomes zero and is returned as `vwlb_caption_gate` (422), falsely asserting that no reviewed caption exists instead of failing closed because authoritative state could not be verified.

## R114-02 — Caption version allocation is not read-safe or concurrency-safe

`VWLB_Videos::add_caption()` derives `MAX(version)+1` through raw `$wpdb->get_var()` without checking `$wpdb->last_error` and without serializing the allocation. A failed read can be interpreted as the first version. Concurrent writers can also compute the same version; the schema unique key then converts the race into an avoidable generic insert failure rather than a deterministic serialized version allocation.

## R114-03 — Playback/progress existence reads can treat database failure as absence and attempt conflicting writes

`VWLB_Videos::playback_session()` and `VWLB_Videos::progress()` use raw `$wpdb->get_row()` existence reads without checking database error state. A failed lookup can therefore be treated as “no session”, causing an insert attempt against the unique `user_object` key and returning a misleading write failure rather than a fail-closed read-integrity error.

## R114-04 — Interaction reconciliation can proceed after failed lock/count reads and can zero canonical counters

`VWLB_Videos::interact()` ignores the result/error of the `SELECT ... FOR UPDATE` lock read, uses an unchecked existence read, and casts unchecked `COUNT(*)` reads to integer. A read failure can therefore allow mutation to continue without a verified lock/existence state, and a failed count can be interpreted as zero and persisted into `like_count` / `dislike_count`.

## R114-05 — Moderation decision lock read can misreport database failure as a missing report

Inside `VWLB_Moderation::decide()` the authoritative `SELECT ... FOR UPDATE` uses raw `$wpdb->get_row()` and maps any falsy result directly to `vwlb_report_missing` (404). A database failure is therefore indistinguishable from verified absence at a privileged state-changing boundary.

## Frozen correction requirements

1. All five boundaries must use fail-closed database read helpers or explicit `$wpdb->last_error` verification.
2. Caption version allocation must be serialized within a transaction before insert.
3. Interaction row lock, existence and counter reads must abort on unverifiable state before any canonical counter write.
4. Playback/progress session existence failures must return an explicit 503 integrity error rather than attempting an insert.
5. Moderation `FOR UPDATE` failure must return an explicit database-read error, not 404.
6. Add R114 regression assertions and advance immutable candidate identity only after all corrections are applied together.
7. Run full historical/current regression, R101–R120 gate, deterministic package, checksum/archive, source/package parity and exact-head PHP 8.3/8.4 Release QA before R115 begins.
