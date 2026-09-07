# File 10 Release Candidate Manifest — 1.2.21-rc1

- Plugin folder: `video-wall-and-live-broadcasting`
- Plugin version: `1.2.21-rc1`
- Base schema: `1.1.0`
- Extension schema: `1.1.0`
- Future schema: `1.2.0`
- WordPress baseline: `7.0+`
- PHP baseline: `8.3+`
- Canonical API: `video-wall-live-broadcasting/v1`
- Compatibility API: `vwlb/v1`
- Text domain: `video-wall-live-broadcasting`
- Package target: `packages/video-wall-and-live-broadcasting-1.2.21-rc1.zip`
- Checksum target: same filename plus `.sha256`
- SBOM: `SBOM-1.2.21-rc1.json`
- Prior review boundary: R81–R100 closed at `1.2.11-rc1` on exact reviewed HEAD `9a2c317d664b3c0d56797afbf1934f6c55479aaa`.
- Current review boundary: R101–R120 sequential cycle; R101–R109 are QA-closed at their recorded exact reviewed heads. R110 review/freeze is complete; its correction candidate is `1.2.21-rc1` and final exact-head release QA is required before R111.
- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009`.
- R102 exact-head QA: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c`, run `34064117765`.
- R103 exact-head QA: `e0a7ae7efba4c2287dc57c3978e4ef9953ef3aa3`, run `34064836972`.
- R104 exact-head QA: `79651313324d4a85e5b0b5583c7319a7559d6c16`, run `34066084479`.
- R105 final evidence revalidation: `4c8d9b1a7ccc05f996b6bdb8e49a8939b431d0c1`, run `34083167364`, PHP 8.3/8.4 green; complete suite, R101–R120 gate, deterministic package, checksum/archive, source/package parity and artifact publication green. Artifact ID `10004334313`, uploaded artifact digest `9282da4838aafcce66bdd50ec42bbf97a412e533a523298396c7dc13d567d1e8`.
- R106 frozen findings: cleanup/write race on private resumable files; uncontained validation/checksum/scanner failures at the processing-worker boundary; permissive late resumable-completion route grammar.
- R106 correction source commit: `bf88573db6eb205bc8d0e4b362022a894f5bb19f`; candidate identity advanced to `1.2.17-rc1` for final exact-head verification.

This manifest establishes repository-source/package QA identity only. Staging, deployed/live and operational evidence remain separate and are not predeclared.

- R107 frozen findings: late permissive REST IDs/stale playback enrichment; incomplete provider-compensation Throwable boundaries; incomplete secret-key detection; stale provider-health DB error contamination; implementation-vs-runtime readiness ambiguity.
- R107 correction candidate identity: `1.2.18-rc1`.

- R108 frozen findings: public provider-proof injection; missing canonical verified provider lifecycle reconciliation; unconfirmed emergency provider end; unfair/silent core live reconciliation; unsafe/unfair Future redundancy reconciliation; permissive effective late REST override grammar.
- R108 correction candidate identity: `1.2.19-rc1`; exact-head release QA is required before R109.

- R108 exact-head QA: `762361ae9f1b1c8392e0fbce224a15051432952c`, run `34100057505`, PHP 8.3/8.4 Green before R109.
- R109 frozen findings: inconsistent current rights delivery; cron-only stale consent exposure; unsafe/unfair consent-expiry reconciliation; mutable terminal consent history without lifecycle evidence; replay policy/consent/lineage gap.
- R109 correction candidate identity: `1.2.20-rc1`; exact-head release QA was required before R110.

- R110 frozen findings: late public-delivery opaque-ID inconsistency; public browse rights/consent revalidation gap; video-specific consent-link scope ambiguity; security-sensitive late revalidation fail-open/incomplete authorization projections; and non-public media/download derivative delivery bypass risks.
- R110 correction candidate identity: `1.2.21-rc1`; exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive verification, source/package parity and artifact publication must all be green before R111 begins.
