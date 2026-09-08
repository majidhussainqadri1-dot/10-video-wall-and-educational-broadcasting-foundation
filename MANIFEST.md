# File 10 Release Candidate Manifest — 1.2.25-rc1

- Plugin folder: `video-wall-and-live-broadcasting`
- Plugin version: `1.2.25-rc1`
- Base schema: `1.1.0`
- Extension schema: `1.1.0`
- Future schema: `1.2.0`
- WordPress baseline: `7.0+`
- PHP baseline: `8.3+`
- Canonical API: `video-wall-live-broadcasting/v1`
- Compatibility API: `vwlb/v1`
- Text domain: `video-wall-live-broadcasting`
- Package target: `packages/video-wall-and-live-broadcasting-1.2.25-rc1.zip`
- Checksum target: same filename plus `.sha256`
- SBOM: `SBOM-1.2.25-rc1.json`
- Prior review boundary: R81–R100 closed at `1.2.11-rc1` on exact reviewed HEAD `9a2c317d664b3c0d56797afbf1934f6c55479aaa`.
- R101–R120 sequential cycle is QA-closed. R120 exact-head Release QA was Green at `4094a9a861eae9a47222cc7a28959eaff87c6c76`, run `34271494386`, on PHP 8.3/8.4 after complete regression, R101–R120, R118/R119, package, checksum/archive and source/package parity gates.
- R111–R120 ten-round batch: defects found in R111, R112, R113, R114, R115, R116, R117, R118, R119 and R120; clean rounds 0/10; >70% clean stopping criterion not met.
- R121 post-R120 closure/provenance findings are frozen in `reviews/R121-FROZEN-FINDINGS.md`; corrected exact head `d466a9020d38a49fd9dcee8085da58775cd26601` passed File 10 Release QA run `34277370167` on PHP 8.3 and PHP 8.4, including complete regression, R118/R119 gates, package/checksum/parity and artifact publication.
- R122 read-only review findings are frozen in `reviews/R122-FROZEN-FINDINGS.md`.
- Exact-head closure model: repository files record immutable historical reviewed evidence. A current round is Green only when File 10 Release QA succeeds for the same exact commit SHA. No post-QA repository mutation is required merely to flip a textual `pending` state to `Green`; any later mutation is a new head and requires its own QA.

This manifest establishes repository-source/package QA identity only. Staging, deployed/live and operational evidence remain separate and are not predeclared. Exact deployed source is unverified; GitHub is not live evidence.
