# R123 Frozen Findings

Status: FROZEN after complete read-only review. No correction was applied during the review phase.

Baseline exact head reviewed: `6d846e7d13c3aa4a9cd131f49c6b69282bd64bf6`.
Baseline File 10 Release QA: run `34292765153`, successful.

## Proven findings

### R123-F1 — Release QA uses mutable third-party GitHub Action tags

The authoritative `.github/workflows/file10-release.yml` invokes:

- `actions/checkout@v4`
- `shivammathur/setup-php@v2`
- `actions/upload-artifact@v4`

These major-version refs are mutable upstream references. A future rerun for an otherwise identical repository commit can therefore execute different third-party Action code after an upstream tag moves. That weakens exact-head reproducibility and creates a CI supply-chain integrity gap: repository SHA identity alone does not freeze the implementation of the Actions used to validate that SHA.

Verified upstream commit targets at freeze time:

- `actions/checkout@v4` -> `11d5960a326750d5838078e36cf38b85af677262`
- `shivammathur/setup-php@v2` -> annotated tag target commit `f3e473d116dcccaddc5834248c87452386958240`
- `actions/upload-artifact@v4` -> `ea165f8d65b6e75b540449e92b4886f43607fa02`

Required correction: pin these three third-party Actions in the authoritative File 10 Release QA workflow to the verified full commit SHAs while retaining readable version comments. Then run full regression/retest and exact-head Release QA before beginning R124.

## Live-first boundary

This round is repository/CI review only. GitHub source, CI, packages, and review artifacts are not live-deployed evidence. Exact deployed source, live database/schema, migration state, staging acceptance, and operational state remain unverified.
