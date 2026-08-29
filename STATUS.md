# File 10 Status — 1.2.8-rc1

**Classification:** repository/source correction candidate in the fresh sequential R41–R60 review cycle opened 2026-08-29.

- Cycle baseline exact HEAD: `824f149269f451a2071882128a655581a3d18ef4` (`1.2.7-rc1`).
- Review method: complete one round first → freeze that round's findings → correct all proven findings from that round together → full regression/release QA → only then begin the next round.
- R41–R58 completed before R59; R59 found release/package identity and release-evidence synchronization defects after materially package-changing corrections beyond 1.2.7-rc1.
- R59 advances the immutable candidate identity to `1.2.8-rc1` and synchronizes plugin/workflow/builder/readme/test-harness/manifest/SBOM metadata. R60 remains pending and no R60 result is preclaimed here.
- Specified: complete by governing plans at repository specification level.
- Coded: candidate; R41–R59 corrections are present on Draft PR #6.
- Packaged: only an artifact generated from the exact current reviewed head is valid as the current candidate package.
- Automated-QA Green: must be established by an exact-head workflow run after the complete R59 correction batch; this source file does not preclaim a future CI result.
- Staging-Accepted: not established.
- Live-Deployed: not established.
- Operational: not established.

GitHub, staging and live are distinct realities. Repository source/package evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.
