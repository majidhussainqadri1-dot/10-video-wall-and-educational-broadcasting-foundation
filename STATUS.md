# File 10 Status — 1.2.12-rc1

**Classification:** repository/source correction candidate in the sequential R101–R120 review cycle begun 2026-09-06.

- Cycle baseline exact HEAD: `9a2c317d664b3c0d56797afbf1934f6c55479aaa` (`1.2.11-rc1`).
- Review method: complete one round read-only → freeze that round's findings → correct all proven findings together → full regression/release QA → only then begin the next round.
- R101 frozen finding: Future P0 production-studio mutations existed, but the canonical REST/UI surface had no reload-safe owner-scoped read of current sources, scenes, guests, live policy and simulcast targets. A broadcaster reloading the studio therefore could not reconstruct its current File 10 state through the canonical public-ID contract.
- R101 correction: added an object-authorized, private/no-store production-state GET surface; redacted internal IDs/credential references; mapped scene membership to opaque source IDs; fail-closed on DB verification failure; and added progressive production-state rendering in the File 10 studio UI.
- Coded/reviewed candidate: `1.2.12-rc1` on `fix/file10-r101-r120-sequential-2026-09-06` after R101 correction.
- Automated-QA Green: must be established by the exact-head workflow after the R101 correction; not preclaimed here.
- Staging-Accepted: not established.
- Live-Deployed: not established.
- Operational: not established.
- Deployed version: unverified.
- Live DB/schema version: unverified.
- Migration state: unverified.
- Live verification: not performed.

GitHub, staging and live are distinct realities. Repository source/package evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.
