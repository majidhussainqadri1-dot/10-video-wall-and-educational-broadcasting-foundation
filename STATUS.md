# File 10 Status — 1.2.7-rc1

**Classification:** repository/source correction candidate after completion of the fresh sequential R21–R40 review cycle opened 2026-08-18.

- Cycle baseline exact HEAD: `83558aea2e581e6f7b76084e21695989254704b7`.
- Review method: complete one round first → freeze that round's findings → correct all proven findings from that round together → full regression/release QA → only then begin the next round.
- R39 advanced the interim runtime candidate from `1.2.5-rc1` to `1.2.6-rc1` because materially different packages must not share the same deployable version identity.
- R40 found additional package/release-hygiene defects; its correction therefore advances the candidate to `1.2.7-rc1` rather than overwriting the earlier `1.2.6-rc1` identity.
- Specified: complete by governing plans.
- Coded: candidate; the R21–R40 source-review cycle is complete and evidence is tracked in Draft PR #5.
- Packaged: only an artifact generated from the final exact R40 head is valid as the cycle package.
- Automated-QA Green: must be established by the final exact-head workflow run; this source file does not preclaim a future CI result.
- Staging-Accepted: not established.
- Live-Deployed: not established.
- Operational: not established.

GitHub, staging and live are distinct realities. Repository source/package evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.
