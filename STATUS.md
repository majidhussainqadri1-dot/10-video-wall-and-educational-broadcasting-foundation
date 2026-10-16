# File 10 Status — 1.2.9-rc1

**Classification:** repository/source correction candidate after completion of the fresh sequential R41–R60 review cycle on 2026-08-29.

- Cycle baseline exact HEAD: `824f149269f451a2071882128a655581a3d18ef4` (`1.2.7-rc1`).
- Review method: complete one round first → freeze that round's findings → correct all proven findings from that round together → full regression/release QA → only then begin the next round.
- R41–R60 completed. Defect-bearing rounds: R41, R42, R43, R45, R46, R50, R51, R52, R53, R54, R55, R56, R57, R58, R59, R60. Clean rounds: R44, R47, R48, R49.
- R60 found final reliability and failure-truth defects: evidence/legacy DB-read fail-open paths, incomplete whole-activation compensation, unsafe external-effect retry/reconciliation behavior, public catalogue DB-failure truth gaps and repair recount DB-failure hazards. The correction batch closes those repository-level findings and advances the immutable deployable identity to `1.2.9-rc1`.
- R60 completed the cycle at source-review level; no later review round is preclaimed in this document.
- Specified: complete by governing plans at repository specification level.
- Coded: `1.2.9-rc1` candidate on Draft PR #6.
- Packaged: only an artifact generated from the exact final reviewed head is valid as the current candidate package.
- Automated-QA Green: not preclaimed by this source file; an exact-head PHP 8.3/8.4 workflow run after the R60 correction batch must establish it.
- Staging-Accepted: not established.
- Live-Deployed: not established.
- Operational: not established.

GitHub, staging and live are distinct realities. Repository source/package evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.
