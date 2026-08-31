# File 10 R90 — Provider/webhook/remote-media security review and first-ten summary

Review completed in full before any correction decision.

Reviewed: provider abstraction and capability selection, YouTube/Vimeo host and identifier normalization, custom/local HTTPS remote URL validation, custom webhook HMAC/timestamp validation, R31 durable verified-webhook dedupe/replay handling, stream-secret generation boundary, private resumable technical validation, malware scan gate, and the explicit external-media validation gate. The File 10 plan requirements for SSRF/replay/upload/provider failure and secret non-disclosure were used as the governing threat model.

Frozen finding: no new unresolved defect was proven in this round. Private uploaded files require checksum/MIME compatibility and an explicit clean malware-scan result; non-private/external media fails technical validation unless an external validator explicitly returns clean/passed/provider_verified; YouTube/Vimeo playback hosts are allowlisted; custom webhook verification requires HMAC plus a five-minute timestamp window and R31 adds durable content-bound deduplication; arbitrary provider failures remain fail-closed rather than being interpreted as success.

Correction: none required.

R89 exact head `c5f4865f7ced93e420bc078348f04472a6795ac3` passed File 10 Release QA run `33297599952` before R90 began.

## First ten rounds (R81–R90) — defect-bearing rounds

Defects were proven in **R81, R84, R85 and R86**.

Clean/no-new-product-defect rounds were **R82, R83, R87, R88, R89 and R90**. R84 was an exact-source/review-parity defect caused by concurrent branch drift; it was corrected by isolating the continuation branch before later rounds proceeded.

This R90 evidence commit must pass the complete File 10 Release QA before R91 begins.