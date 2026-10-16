# File 10 R100 — Release identity, traceability, package and final-cycle closure

Review completed in full before correction.

Reviewed: runtime/plugin identity; workflow package and artifact names; deterministic builder default; current-version test gates; historical R61–R80 release evidence; current R81–R100 regression contracts; MANIFEST/STATUS/README/WordPress readme; SBOM identity; exact-head package/parity workflow and the governing rule that materially changed candidates receive a fresh immutable source/package identity without falsely inflating schema versions.

Frozen findings: material R81–R99 source changes still reused the `1.2.10-rc1` immutable candidate identity created for the prior R61–R80 closure. The workflow, package builder, current-version test gates, manifest/status/readme and current SBOM therefore described an older candidate boundary while the source had materially advanced. The R81–R100 regression suite also lacked an explicit R100 release-identity closure gate. This was a traceability/release-evidence defect, not a schema defect.

Correction batch: advance only the runtime/package candidate identity to `1.2.11-rc1`; keep base/extension/Future schema versions unchanged; synchronize workflow, builder, current-version tests, manifest/status/readmes and a new `SBOM-1.2.11-rc1.json`; retain `SBOM-1.2.10-rc1.json` and its changelog entry as historical R80 evidence; convert the R61–R80 release checks to historical evidence; and add R100 assertions covering current runtime/workflow/builder/manifest/SBOM identity plus the complete R81–R100 defect ledger.

R99 exact head `1cc9842fadb7c468739fee3ecf94543bbc60e72b` passed File 10 Release QA run `33323370963` before R100 began. The corrected R100 exact head must pass the complete PHP 8.3/8.4 source/package/parity suite before this 20-round cycle can be closed.
