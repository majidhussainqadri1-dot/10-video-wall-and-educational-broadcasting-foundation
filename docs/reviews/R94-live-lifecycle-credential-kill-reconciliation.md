# File 10 R94 — Live lifecycle, credential, emergency-kill and reconciliation review

Review completed in full before correction.

Reviewed: live scheduling and provider compensation; idempotency; stream credential issuance/rotation/display-once behavior; step-up; live state transitions; active-credential gate; emergency end; credential revocation; recording queue; provider exception containment; R60 external-effect guards and uncertain-outcome classification.

Frozen finding: emergency `kill` is correctly classified as an external-effect route, but R60's uncertain-response classifier did not treat every kill-route 5xx as reconciliation-required. The command invokes the provider emergency-end action inside a database transaction and can subsequently return a 5xx (for example if recording finalization cannot be queued). In that sequence the provider may already have ended the stream while local changes roll back, yet the external guard could be released because the later error code was not one of the narrow unsafe codes. A retry with the same idempotency key could therefore lose the intended uncertainty lock.

Correction batch: R94 adds an after-callback uncertainty guard before R60's external-guard finalizer. Any server-side failure on the File 10 emergency-kill route is converted to the existing typed `vwlb_provider_emergency_end_reconcile_required` error with `reconcile_required=true`. R60 already treats that code as unsafe, so the durable external-effect guard remains in reconciliation-required state instead of being released. Successful and non-5xx responses are unchanged. Regression contracts cover registration, kill-route recognition, the 5xx boundary and R60 unsafe-code/external-route integration.

R93 exact head `d556c250e7d03566ea6b37e0ca22d123aeb22f1d` passed File 10 Release QA run `33298146877` on PHP 8.3/8.4 before R94 began. The corrected R94 exact head must pass the full suite before R95 begins.