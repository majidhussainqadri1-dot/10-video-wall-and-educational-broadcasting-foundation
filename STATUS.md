# File 10 Status — 1.2.25-rc1

**Classification:** repository/source correction candidate in the sequential review lineage. Repository status records immutable review evidence; exact-head closure is resolved from the Release QA result attached to the same commit SHA, not by a post-QA status-only commit.

- Cycle baseline exact HEAD: `9a2c317d664b3c0d56797afbf1934f6c55479aaa` (`1.2.11-rc1`).
- Review method: complete one round read-only → freeze that round's findings → correct all proven findings together → full regression/release QA → only then begin the next round.
- Exact-head closure rule: a round is Green only when File 10 Release QA is successful for the same exact commit SHA. Repository documentation does not mutate merely to flip `pending` to `Green`; any subsequent source mutation is a new head and requires its own QA.
- Historical R40 ledger: R40 found additional package/release-hygiene defects; those historical defects were corrected and closed in the prior sequential lineage.
- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009`, PHP 8.3/8.4 green.
- R102 exact-head QA: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c`, run `34064117765`, PHP 8.3/8.4 green.
- R103 exact-head QA: `e0a7ae7efba4c2287dc57c3978e4ef9953ef3aa3`, run `34064836972`, PHP 8.3/8.4 green.
- R104 exact-head QA: `79651313324d4a85e5b0b5583c7319a7559d6c16`, run `34066084479`, PHP 8.3/8.4 green.
- R105 final evidence revalidation: `4c8d9b1a7ccc05f996b6bdb8e49a8939b431d0c1`, run `34083167364`, PHP 8.3/8.4 green; complete suite, R101–R120 gate, deterministic package, checksum/archive, source/package parity and artifact publication green.
- R106 exact-head QA: `a1507752f5dad87e7b22e7a4aa4cf714de4aefd3`, run `34085674761`, PHP 8.3/8.4 green.
- R107 final evidence-recording head: `0de65d9956cf3a4c03e3e0c315c09bb70ca8c82f`, run `34087545859`, PHP 8.3/8.4 green.
- R108 exact-head QA: `762361ae9f1b1c8392e0fbce224a15051432952c`, run `34100057505`, PHP 8.3/8.4 green.
- R109 review/correction: five findings frozen and corrected before the R110 gate.
- R110 exact-head QA: `d7ed00cbaf76093fd1ccadfa4fbcf405ecde2fb3`, run `34174866764`, PHP 8.3/8.4 green.
- R111 review: three findings frozen; exact-head QA `4194d1daf1f6b7ef835f11c0448211c6714c464d`, run `34178262832`, PHP 8.3/8.4 green.
- R112 review: four findings frozen and corrected before R113.
- R113 review: three findings frozen and corrected before R114.
- R114 review: five findings frozen and corrected before R115.
- R115 review: idempotency authoritative-read and transaction rollback-verification durability defects frozen, corrected and QA-closed before R116.
- R116 exact-head QA: `3b6397135ac1d84292be98068f46ccd8b2170227`, run `34223100132`, PHP 8.3/8.4 green.
- R117 exact-head QA: `f2c4345973a4b01896b9250bee6f3d220f82da1c`, run `34228952712`, PHP 8.3/8.4 green.
- R118 review: release-provenance consistency findings frozen in `reviews/R118-FROZEN-FINDINGS.md`; corrected and exact-head QA closed before R119.
- R119 review: nine fail-closed read-integrity defect groups frozen in `reviews/R119-FROZEN-FINDINGS.md`; final exact-head QA `c2d48ed9bbd5cbc29f9fe186340d01acf4e857ce`, run `34259864776`, successful.
- R120 review: four provenance/closure findings frozen in `reviews/R120-FROZEN-FINDINGS.md`; exact-head QA `4094a9a861eae9a47222cc7a28959eaff87c6c76`, run `34271494386`, PHP 8.3/8.4 green with complete suite, R101–R120, R118/R119, package, checksum/archive and parity gates passed.
- R111–R120 ten-round batch result: defects in **R111, R112, R113, R114, R115, R116, R117, R118, R119 and R120**; clean rounds: **0/10**; stopping criterion not met.
- R121 review findings are frozen in `reviews/R121-FROZEN-FINDINGS.md`; corrected exact head `d466a9020d38a49fd9dcee8085da58775cd26601`, Release QA run `34277370167`, PHP 8.3/8.4 successful with complete regression, provenance/read-integrity gates, package/checksum/parity and artifact publication.
- R122 read-only review findings are frozen in `reviews/R122-FROZEN-FINDINGS.md`; correction is provenance/QA-contract infrastructure only. Its closure must be determined by a successful File 10 Release QA run on the eventual exact correction head.
- Coded/reviewed candidate: `1.2.25-rc1` on `fix/file10-r101-r120-sequential-2026-09-06`.
- Staging-Accepted: not established.
- Live-Deployed: not established.
- Operational: not established.
- Deployed version: unverified.
- Live DB/schema version: unverified.
- Migration state: unverified.
- Live verification: not performed.

GitHub, staging and live are distinct realities. Repository source/package/CI evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.
