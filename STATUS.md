# File 10 Status — 1.2.25-rc1

**Classification:** repository/source correction candidate in the sequential R101–R120 review cycle begun 2026-09-06.

- Cycle baseline exact HEAD: `9a2c317d664b3c0d56797afbf1934f6c55479aaa` (`1.2.11-rc1`).
- Review method: complete one round read-only → freeze that round's findings → correct all proven findings together → full regression/release QA → only then begin the next round.
- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009`, PHP 8.3/8.4 green.
- R102 exact-head QA: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c`, run `34064117765`, PHP 8.3/8.4 green.
- R103 exact-head QA: `e0a7ae7efba4c2287dc57c3978e4ef9953ef3aa3`, run `34064836972`, PHP 8.3/8.4 green.
- R104 exact-head QA: `79651313324d4a85e5b0b5583c7319a7559d6c16`, run `34066084479`, PHP 8.3/8.4 green.
- R105 final evidence revalidation: `4c8d9b1a7ccc05f996b6bdb8e49a8939b431d0c1`, run `34083167364`, PHP 8.3/8.4 green; complete suite, R101–R120 gate, deterministic package, checksum/archive, source/package parity and artifact publication green. Artifact ID `10004334313`; uploaded artifact digest `9282da4838aafcce66bdd50ec42bbf97a412e533a523298396c7dc13d567d1e8`.
- R106 review: completed read-only before correction; three findings frozen in `docs/FILE-10-R106-FROZEN-FINDINGS-2026-09-07.md`.
- R106 correction: cleanup coordinates with the private-upload writer lock; checksum/scanner/external validation failures are contained at the worker boundary and fail through normal retry/dead-letter handling; the late resumable-completion override enforces canonical prefixed opaque public IDs.
- R106 exact-head QA: `a1507752f5dad87e7b22e7a4aa4cf714de4aefd3`, run `34085674761`, PHP 8.3/8.4 green; complete suite, deterministic `1.2.17-rc1` package, checksum/archive, source/package parity and artifact publication green. Artifact ID `10005125569`.
- R107 review: completed read-only before correction; five findings frozen covering late REST opaque-ID/playback enrichment, provider compensation exception containment, recursive secret detection, provider-health DB truth, and capability/readiness semantics.
- R107 correction: applied; candidate identity advanced to `1.2.18-rc1`. Source/package QA at `cdb5aaddb831f61070c0c62c49ee42cf0aa38e48`, run `34087336458`, was green on PHP 8.3/8.4 with complete suite, R101–R120 gate, deterministic package, checksum/archive and source/package parity. Artifact ID `10005650563`; digest `sha256:7607f7c28fb0900991046dfc84538869b9a8b2323fef06030b257cb0bfdfc6b9`.
- R107 final evidence-recording head `0de65d9956cf3a4c03e3e0c315c09bb70ca8c82f`, run `34087545859`, is Green on PHP 8.3/8.4; R108 began only after that gate.
- R108 exact-head `762361ae9f1b1c8392e0fbce224a15051432952c`, run `34100057505`, is green on PHP 8.3/8.4 with complete suite/package/parity/artifact gates.
- R109 review: completed read-only after R108 Green; five findings frozen in `docs/FILE-10-R109-FROZEN-FINDINGS-2026-09-07.md`.
- R109 correction: current rights/consent delivery, consent-expiry fairness/history and replay-lineage gates applied as `1.2.20-rc1`; R110 began only after the R109 Green gate.
- R110 review: completed read-only before correction; frozen findings cover late public-delivery opaque-ID consistency, public browse current rights/consent revalidation, consent-link scope, fail-closed late authorization reads/projections, and non-public derivative/download delivery boundaries.
- Historical R110 correction checkpoint (superseded by the Green gate below): candidate `1.2.22-rc1` was awaiting full regression and exact-head release QA before R111.
- Coded/reviewed candidate: `1.2.25-rc1` on `fix/file10-r101-r120-sequential-2026-09-06`.
- Automated-QA Green: R101–R117 established at their recorded exact heads; R118 correction requires its own exact-head Green before R119.
- Staging-Accepted: not established.
- Live-Deployed: not established.
- Operational: not established.
- Deployed version: unverified.
- Live DB/schema version: unverified.
- Migration state: unverified.
- Live verification: not performed.

GitHub, staging and live are distinct realities. Repository source/package evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.

- R110 exact-head QA: `d7ed00cbaf76093fd1ccadfa4fbcf405ecde2fb3`, File 10 Release QA run `34174866764`, PHP 8.3/8.4 Green with complete suite, R101–R120 gate, package/checksum/archive and source/package parity.
- R111 review: completed read-only from the R110 Green baseline; three findings frozen in `docs/FILE-10-R111-FROZEN-FINDINGS-2026-09-08.md`.
- R111 exact-head QA: `4194d1daf1f6b7ef835f11c0448211c6714c464d`, File 10 Release QA run `34178262832`, PHP 8.3/8.4 Green with complete suite, R101–R120 gate, package/checksum/archive, source/package parity and artifact publication. Artifact ID `10037991198`; digest `sha256:c8c90f910d94c7fdb4b5ae591f4edecc1ded063be30fe6887d5e588df294b044`.
- R112 review: completed read-only from the R111 Green baseline; four findings frozen in `docs/FILE-10-R112-FROZEN-FINDINGS-2026-09-08.md`.
- R113 review: completed read-only and three findings frozen in `docs/FILE-10-R113-FROZEN-FINDINGS-2026-09-08.md`; correction/QA completed before R114.
- R114 review: completed read-only and five findings frozen in `docs/FILE-10-R114-FROZEN-FINDINGS-2026-09-08.md`; correction/QA completed before R115.
- R115: read-only review found idempotency authoritative-read and transaction rollback-verification durability defects; correction completed and exact-head QA was Green before R116.
- R116: read-only review found private-media protection-write verification and authoritative post-write reread defects; correction completed. Final exact-head QA: `3b6397135ac1d84292be98068f46ccd8b2170227`, run `34223100132`, PHP 8.3/8.4 Green with complete suite, R101–R120 gate, canonical package, checksum/archive, source/package parity and artifact publication.
- R117: read-only review found destructive uninstall purge integrity defects; findings frozen in `reviews/R117-FROZEN-FINDINGS.md`, corrected, and exact-head QA Green at `f2c4345973a4b01896b9250bee6f3d220f82da1c`, run `34228952712`, PHP 8.3/8.4 with complete suite, R101–R120 gate, canonical package, checksum/archive, source/package parity and PHP 8.3 artifact publication.
- R118: read-only provenance review completed from the R117 Green baseline; findings frozen in `reviews/R118-FROZEN-FINDINGS.md`. Current correction normalizes SBOM/STATUS/MANIFEST evidence only. R119 remains blocked until R118 exact-head QA is Green.
