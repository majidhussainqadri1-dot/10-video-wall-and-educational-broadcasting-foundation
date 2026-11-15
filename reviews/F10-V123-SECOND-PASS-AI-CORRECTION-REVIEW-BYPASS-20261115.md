# File10 V12.3 — source Review B finding: AI/imported correction review bypass

**2026-11-15.** First-pass six fixes and tests are green at `a68aae1cf98a7afb7c12b92257a3a73f34660e98`. This is a fresh adversarial source assessment, not independent File24/Founder review or Live acceptance.

**V12.3 governing expectations:** F10-FUT-016 AI suggestions require explicit human review; F10-FUT-021 corrections must have a genuine review and publish state; F10-FR-006 and F10-NFR-001 require controlled publication/rights checks.

**SRC-TP-007:** In `VWLB_Future_Intelligence::create_annotation()`, general source/provenance logic assigns `status='candidate'` to all `ai_assisted` or `imported` annotations. The later unconditional correction-specific line `$status='reviewed';` overrides that safe state whenever `kind='correction'` and the **creator** has a review capability. It marks AI/imported content as having already passed human content review, even without executing the explicit `candidate -> reviewed` transition. This can incorrectly make `publish` the next allowed action.

**Required source correction:** For `kind=correction`, preserve the existing permission requirement to create a correction, but **do not override candidate status for AI/imported source**. Keep historical manual reviewer-created corrections `reviewed` for compatibility. AI/imported correction must follow `candidate -> reviewed -> published`; publishing directly from candidate is 409; event only on actual publish.

**Negative tests:** create AI correction must be candidate (reviewed_by=0); direct publish fails with 409/no published event; reviewer review then explicit publish succeeds with exactly one publication event pair. Imported correction must also be candidate; manual reviewer correction continues as reviewed but unpublished.

**No schema change, plugin activation, automated AI publication, File26 indexing activation or Live Deployment.**
