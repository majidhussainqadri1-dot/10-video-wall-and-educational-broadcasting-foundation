# R133 Frozen Findings

Review mode: read-only until this ledger was frozen.

Reviewed baseline: `5c8c05f4cbb6ddc863ba5afbf0c267ebc98f1ed4` (R132 exact-head Release QA Green, run `34860950359`).

Scope included live scheduling, recording-policy defaults, recording consent/version binding, attendee consent finalization, canonical recording lineage, replay publication, consent expiry/withdrawal delivery blocking, provider-ended/emergency-ended recording finalization, and protected replay delivery. R131 repository-governance finding remains separately open.

## Frozen findings

### Proven defect — command-service recording fallback is record-on while the public scheduling boundary is record-off

`VWLB_REST::schedule_live()` intentionally supplies a fail-safe default recording policy with `record=false`, `publish_replay=false`, and `consent_required=true` when the caller omits recording policy. However, the underlying public command service `VWLB_Live::schedule()` independently persists a fallback of `array('record'=>true,'publish_replay'=>false)` when `recording_policy` is omitted.

The service can be invoked by trusted integrations or future/internal command paths without passing through the REST normalization. Such callers therefore receive a materially less private default than the canonical public boundary, and the persisted policy also omits the explicit consent-required default. This conflicts with the governing privacy rule that recording is opt-in/fail-safe and sensitive sessions default to no recording.

The existing R73/R109 consent and replay guards correctly enforce explicit/version-bound consent and replay lineage after a recording policy exists; the defect is the service-level default itself.

## Authorized correction

Change the `VWLB_Live::schedule()` fallback recording policy to the same fail-safe default used at the REST boundary: `record=false`, `publish_replay=false`, `consent_required=true`, with a default consent version. Add a permanent regression contract and run full exact-head Release QA before R134.

Live-First rule remains in force: exact deployed source was not available for this review; exact deployed code is unverified, and repository-based diagnosis is provisional.
