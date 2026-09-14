# R134 Frozen Findings

Review mode: read-only until this ledger was frozen.

Reviewed baseline: `a0ed65111c3aad7397be4515becc1defc53f9daf` (R133 exact-head Release QA Green, run `34861575481`).

Scope included copyright/takedown and restoration boundaries, stale public delivery, cache purge/revocation propagation, existing delivery guards, and post-correction validation of the immediately preceding privacy changes. R131 repository-governance finding remains separately open.

## Frozen findings

### Proven correction defect — R132 added a redundant guard because the pre-existing R78 guard already enforced the required caption privacy policy

During this round the pre-existing `VWLB_R78_Public_Delivery_Guard::caption_cache()` was inspected. It is registered on `rest_request_after_callbacks` and, for File 10 caption GET routes, re-reads the caption and parent video fail-closed. When the parent video is not both `visibility=public` and `status=published`, it already overrides the response to `Cache-Control: private, no-store`. Therefore the R132 finding that non-public captions were left publicly cacheable was incomplete: the raw callback set a public cache header, but the existing R78 final callback-stage guard already corrected non-public delivery before dispatch.

R132 subsequently introduced `VWLB_R132_Caption_Cache_Guard` on `rest_post_dispatch`, duplicating the established R78 responsibility. Keeping both guards would violate the Root-Cause-First / No-Patch-Stacking rule and unnecessarily enlarge the delivery path.

This is a correction-validation defect in repository code introduced during R132, not evidence of a remaining caption authorization/privacy vulnerability in the pre-R132 baseline.

## Authorized correction

Remove the redundant R132 guard, its bootstrap registration, and its dedicated release gate/test. Preserve the immutable R132 frozen finding as historical audit evidence and add a separate correction-validation note recording that the finding was a false positive after full-path inspection. Retain the existing R78 caption privacy guard as the single canonical mitigation. Run full exact-head Release QA before R135.

Live-First rule remains in force: exact deployed source was not available for this review; exact deployed code is unverified, and repository-based diagnosis is provisional.
