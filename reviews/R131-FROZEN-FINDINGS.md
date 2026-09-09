# R131 Frozen Findings

Review mode: read-only until this ledger was frozen.

Reviewed baseline: `2598e63ee074140d5c71200268bcff3439692e0f`.

Scope included the canonical File 10 Release QA workflow and exact-head lineage; repository/branch governance enforcing that QA; branch/ruleset state; pinned Actions/PHP/toolchain controls; archived correction workflows; deterministic package construction, checksum, archive validation, source/package parity, artifact provenance; regression-gate continuity; and the Live-First deployed-state boundary.

## Frozen findings

### Proven defect — the active review/release-candidate branch is not protected and no repository ruleset exists

The active review branch `fix/file10-r101-r120-sequential-2026-09-06` is reported by GitHub as `protected: false`, and the repository rulesets collection is empty. Therefore the canonical QA workflow can prove a particular commit green, but repository governance does not enforce that subsequent changes to the branch must pass that QA before becoming the new branch head. Direct pushes or force-updates by an actor with sufficient repository write permission can bypass the intended review→freeze→fix→regression→exact-head QA discipline.

This is a repository-governance defect, not a product/runtime defect. The canonical workflow itself remains read-only (`contents: read`) and runs on pushes to `fix/**`, but a workflow that merely runs after a push is not equivalent to branch protection requiring the checks before protected state is accepted.

## Authorized correction

Enforce branch-level or repository ruleset protection for `fix/file10-r101-r120-sequential-2026-09-06` (or an equivalent protected release-candidate branch), requiring the canonical File 10 Release QA checks and preventing bypass/force-update inconsistent with the governance sequence. After enforcement, rerun full regression/retest and exact-head QA and verify the protection/ruleset state before R132 begins.

No runtime/product patch is authorized from this finding.

Live-First rule remains in force: exact deployed source was not available for this review; exact deployed code is unverified, and repository-based diagnosis is provisional.
