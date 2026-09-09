# R128 Frozen Findings

Status: findings frozen before correction.

Reviewed exact baseline: `17ea0f4035b3b46fa22e2a20a20d9acbecb387dd` (R127 exact-head QA Green).

## Read-only review finding

### F128-1 — Release-candidate artifact provenance is ambiguous across pull-request and push runs

The canonical `.github/workflows/file10-release.yml` is triggered by both `push` and `pull_request`. Its artifact-publication step is conditioned only on the PHP matrix value and `VWLB_PUBLISH_REVIEW_ARTIFACT`, so a pull-request run can publish an artifact with the same fixed release-candidate name (`file10-video-wall-live-1.2.25-rc1`) used by branch push QA.

This is a provenance defect: a PR-controlled source tree can therefore emit an official-looking release-candidate artifact name even though it is not the verified branch-push exact-head candidate. GitHub run metadata can distinguish runs, but the artifact identity itself does not encode the source commit and the workflow does not restrict publication to push events.

## Frozen correction requirements

1. Keep PR QA read-only and fully test-capable, but do not publish release-candidate artifacts from `pull_request` runs.
2. Permit release-candidate publication only from `push` runs that satisfy the canonical matrix condition.
3. Bind the published artifact name to the exact `${{ github.sha }}` so artifact identity carries its source commit.
4. Add a regression contract preventing reintroduction of PR artifact publication or a fixed, SHA-less release-candidate artifact name.
5. Run the complete regression/retest and exact-head File 10 Release QA after correction; R129 must not begin until the correction head is Green.

No correction was made during the R128 review phase.