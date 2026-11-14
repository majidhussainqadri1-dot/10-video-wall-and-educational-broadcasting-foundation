# File 10 V12.3 — Source Review Pass A: original-plan fidelity

**Review date:** 2026-11-14. **Scope:** read-only governing source, source-only controls on Draft PR #32. This is an internal source review, **not** the two independent File24/Staging release reviews.

## Frozen inputs

- Exact founder-supplied Word master plan: `Sabri-Website-Master-Plan-V12.3.docx`, SHA-256 `c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf`.
- Original Word top-level paragraph indexes: F10-FR-001 to F10-FUT-024 are 1569–1622, and V12.1-STO-01..10 are 2844–2853. These are **0-based Python Word paragraph indexes**, not printed page numbers.
- Governing requirements: 19 FR + 10 NFR + 1 CEN + 24 FUT + 10 shared CF04 STO = **64**; 13 P0, 8 P1, 3 P2 Future requirements.
- Original document remains local/private; only stable per-paragraph hashes, lengths and source indexes are published in `docs/V12.3-FILE10-ORIGINAL-DOCX-CONTENT-DIGESTS.tsv`.
- The 64 source statuses remain candidate-only, and zero are independently accepted in a real staging environment.

## First review — original text and acceptance completeness

1. **SRC-A01** (source traceability gap): V12.1 CSV had 64 ID/title pairs but not an exact **full V12.3 requirement body** proof. Added Word file binary hash and full 64-paragraph NFC/SHA256 manifest; verifier uses Python standard-library DOCX ZIP/XML without uploading document. Test both original SHA mismatch and changed paragraph, rather than asserting that a title is enough. **Source correction done.**
2. **SRC-A02** (acceptance quality gap): source mapping and simple static contract presence could be confused with actual per-requirement negative tests. Added **64 unique adverse acceptance oracles**, each with failure domain, negative scenario, expected safe state and evidence needed, all recorded `not_run`. Negative fixtures reject fabricated pass, missing proof or duplicate tests. **Source correction done.**
3. **SRC-A03** (status/reporting gap): old status report did not expose separate machine-readable per-category `candidate_mapped`, `individual_unaccepted`, `staging_unverified`, `live_unverified` counts. Added read-only CLI acceptance audit and synthetic fake all-pass fixtures, which must still keep `release_approval=false`. **Source correction done.**
4. **SRC-A04** (new QA fixture threshold defect): first Oracle validator demanded at least 8 characters in `failure_domain`, incorrectly rejecting a legitimate short category such as `ingest`. The specific source test failure was reproduced in GitHub CI; changed domain threshold to 3 while keeping actual scenario/evidence requirements at 8. **Source correction and regression done.**

### Source acceptance result

- Exact Word source digests specified: 64.
- Negative scenarios specified: 64; real executions: 0.
- Original ledger: 64/64 source mapping; 0/64 individual accepted, 0/64 staged accepted, 0/64 live accepted.
- Changes described above: **4 source-governance groups corrected**, of which SRC-A04 is a directly CI-proven test defect; the other three are source evidence enhancements, **not 3 proven runtime software defects**.
- Original Word binary verified in the *local review context* using the supplied upload and Python paragraph extraction; CI only validates the frozen manifest and synthetic Word parsing, since the private original is deliberately not checked in.

## Not covered here

This review did not run a real WordPress, PHP plug-in activation, database migration, provider-streaming end-to-end process, accessible UI session, production deployment, privacy erasure or live replay. A full independent post-final-code File24 review remains required before release.

**Outcome:** source traceability and individual acceptance specification improved. No claim of complete V12.3 implementation, Staging-Accepted or Live-Deployed.
