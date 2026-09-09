# R125 Frozen Findings

Review phase: read-only until this ledger was created. No R125 correction was applied before freeze.

Baseline reviewed: R124 exact-head `fdbf4c91a225b03b7edae3a4e608bdd58a15aa0f`, whose File 10 Release QA run 34301131909 completed successfully for PHP 8.3 and 8.4.

## Proven defects

### F1 — Closed historical correction workflows remain armed with repository write permission

The active review branch still contains correction workflows for already-closed rounds R110, R111, R114 (stored as `r112-correction.yml`), R113, R115, R116, and R119. These workflows are push-triggered by marker files on the current review branch and declare `permissions: contents: write`; their jobs run historical correction applicators and push commits back to the branch.

This leaves obsolete mutation paths executable after their rounds are closed. A future marker-file change can reactivate historical correction logic against a later repository state, violating the sequential round boundary and increasing the chance of stale/non-idempotent mutations outside the current frozen correction phase.

### Required correction

Archive the closed correction workflows so they cannot mutate repository contents: remove push-triggered correction execution and repository write permission while retaining an auditable historical workflow note. Add/retain regression coverage so the canonical QA fails if a closed historical correction workflow becomes write-capable again.

## Scope boundary

This is repository/CI governance evidence only. Exact deployed source was not available during R125 review; deployed code remains unverified and repository-based diagnosis remains provisional under the Live-First Exact-Deployed-State Rule.
