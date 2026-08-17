# File 10 Status — 1.2.6-rc1

**Classification:** repository/source correction candidate under the fresh sequential R21–R40 review cycle opened 2026-08-18.

- Cycle baseline exact HEAD: `83558aea2e581e6f7b76084e21695989254704b7`.
- Review method: complete one round first → freeze that round's findings → correct all proven findings from that round together → full regression/release QA → only then begin the next round.
- Runtime candidate identity was advanced from `1.2.5-rc1` to `1.2.6-rc1` in R39 because materially different packages must not share the same deployable version identity.
- Specified: complete by governing plans.
- Coded: candidate; R21–R40 review evidence is tracked in Draft PR #5.
- Packaged: candidate packages are valid only when bound to their exact-head CI artifact/checksum.
- Automated-QA Green: exact-head status must be taken from the current workflow run; prior green runs are historical evidence only.
- Staging-Accepted: not established.
- Live-Deployed: not established.
- Operational: not established.

GitHub, staging and live are distinct realities. Repository source/package evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.
