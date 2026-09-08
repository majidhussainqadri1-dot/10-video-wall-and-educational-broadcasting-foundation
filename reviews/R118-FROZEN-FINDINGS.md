# R118 Frozen Findings — Release Provenance Consistency

Baseline exact repository head reviewed: `f2c4345973a4b01896b9250bee6f3d220f82da1c`.

Review discipline: the complete R118 review was performed read-only. No product or repository metadata was modified during the review. Findings below are frozen before correction.

## Proven defect group R118-1 — current SBOM carries stale and contradictory review provenance

`SBOM-1.2.25-rc1.json` identifies the current 1.2.25-rc1 component but its provenance fields are stale/internally contradictory:

- serial number still identifies `file10-r112-1.2.25-rc1`;
- `baseline_exact_head` points to an older R108-era head;
- it contains two `review_boundary` properties describing R109 and R112-era states rather than the current reviewed/corrected boundary.

This makes the current SBOM unsuitable as an exact repository provenance statement.

## Proven defect group R118-2 — STATUS/MANIFEST progression is stale and self-contradictory

`STATUS.md` states near the top that automated QA for R110 is "not yet established", while later in the same file it records R110 exact-head QA Green. It also stops substantive sequential closure tracking at R114 and omits the established R115, R116 and R117 Green gates.

`MANIFEST.md` still describes the current review boundary as R110 correction pending before R111, even though R111 through R117 have subsequently completed their required review/correction/QA gates. It likewise stops detailed progression at R114.

These are repository evidence/traceability defects: they do not change live runtime behavior, but they can misstate which exact repository state has actually passed the mandated sequential QA process.

## Correction contract

After this ledger is frozen, correction must:

- normalize the current SBOM to one unambiguous R118 provenance boundary tied to the frozen R118 baseline head;
- remove stale/contradictory current-state claims from STATUS and MANIFEST;
- record R115, R116 and R117 closure evidence without making any staging/live/deployed claim;
- retain the Live-First separation between repository QA and deployed state;
- add deterministic regression coverage for current provenance consistency;
- run the full regression/retest and exact-head Release QA before R119 begins.

## Live-First rule

Exact deployed source was not available for comparison. Exact deployed code remains unverified; repository-based diagnosis is provisional and GitHub is not treated as live state.
