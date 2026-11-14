# Frozen finding F10-V12.3-GOV-001 — volatile PHP patch-version check contexts

**Date:** 2026-11-14. **Scope:** source GitHub Actions release governance, not a live WordPress runtime defect.

## Exact proven evidence

- Active ruleset ID `25451533` requires `source-package-qa (8.3.35)` and `source-package-qa (8.4.26)`.
- Current `.github/workflows/file10-release.yml` defines a matrix `php: ['8.3.36','8.4.27']`; observed successful workflow runs show contexts `source-package-qa (8.3.36)` and `source-package-qa (8.4.27)`.
- Ruleset does not include current modern source branch `codex/file10-v12-3-evidence-first-20261114`; source PR success cannot establish branch protection.

## Root cause and impact

Matrix-generated GitHub job contexts include patch-level toolchain versions. Pinning a branch protection rule to these context strings makes the rule stale whenever the PHP patch versions are intentionally updated. Updating only the ruleset to match current patch names would reproduce the same risk at the next toolchain update. This is **release-governance drift**, not evidence of production exploitation.

## Approved source-only corrective scope

Add a **stable-name aggregate gate** which depends on the full PHP matrix, runs on both success and failure and returns success **only when all required matrix jobs pass**. Preserve existing PHP matrix security/QA checks, deterministic package and artifacts. Do not call green aggregate status a staging, deployed, or release acceptance.

## Required regression

1. Exact-head push and PR runs: both PHP matrix jobs and stable aggregate job must be green.
2. Aggregation must report failure if any PHP matrix job fails or is cancelled/skipped.
3. Workflow job name must not embed a volatile PHP patch number.
4. GitHub ruleset still requires separate **administrator action**, verified on same exact ref and SHA before Issue #22 is closed; do not change or weaken its existing protections automatically.

**Fix may proceed as source-only CI hardening. Independent File24/Founder release acceptance remains outstanding.**
