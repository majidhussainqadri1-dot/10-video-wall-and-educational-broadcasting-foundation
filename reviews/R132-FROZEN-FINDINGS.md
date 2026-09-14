# R132 Frozen Findings

Review mode: read-only until this ledger was frozen.

Reviewed baseline: `0e76c147d2ee3f7dd5c99ce5098bb3a8cae93915`.

Scope included REST public/read authorization boundaries, opaque-ID handling, caption delivery, video visibility semantics, protected media-contract delivery, extended REST routes, and cache/privacy behavior. R131 repository-governance finding remains separately open and is not represented as corrected by this round.

## Frozen findings

### Proven defect — authorized non-public captions are emitted with a public shared-cache policy

`VWLB_REST::caption()` first authorizes the parent video with `VWLB_Security::can_view($video)`. That authorization intentionally permits some non-public video visibilities (`unlisted`, `member`, `private`, and `entitled`) for eligible callers. However, after authorization the caption endpoint always sends `Cache-Control: public, max-age=300`.

Therefore caption content belonging to an authorized non-public video can be marked as publicly cacheable by shared intermediaries. This contradicts the privacy/access boundary already enforced by `can_view()` and creates a cache-disclosure path that is absent from the parent video response, which already switches non-public responses to `private, no-store`.

The finding is limited to response cache policy; no authorization bypass was found in the reviewed caption path.

## Authorized correction

Keep the existing `can_view()` authorization. Emit `Cache-Control: public, max-age=300` only when the parent video visibility is exactly `public`; for every other visibility emit `Cache-Control: private, no-store`. Add a permanent regression contract and wire it into the complete File 10 automated suite. Then run full exact-head Release QA before beginning R133.

Live-First rule remains in force: exact deployed source was not available for this review; exact deployed code is unverified, and repository-based diagnosis is provisional.
