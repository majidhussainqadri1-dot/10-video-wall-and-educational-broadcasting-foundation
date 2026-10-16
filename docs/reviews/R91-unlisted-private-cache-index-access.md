# File 10 R91 — Unlisted/private cache, index and access review

Review completed in full before correction.

Reviewed: canonical video/live/channel/podcast routes, `VWLB_Security::can_view()`, public playback, live state, podcast episode/feed behavior, SEO output, private response headers and cross-file public projections against the plan rule that unlisted/private access is signed and noindex while public indexing is limited to eligible public records.

Frozen findings: (1) `can_view()` treated `unlisted` exactly like `public`, so an anonymous caller with an opaque identifier could access unlisted video/live/podcast objects without any signed authorization proof. (2) R3/live playback also exempted unlisted media from the short-lived secure-delivery grant path. (3) authorized non-public channel and podcast deep links lacked an explicit route-level noindex/no-store header path. Together these could turn an opaque unlisted identifier into a bearer secret and allow cache/index or static-delivery leakage beyond the plan boundary.

Correction batch: unlisted visibility now fails closed unless the current verified owner/manager is authorized or a bounded HMAC access proof is valid; the new R91 guard provides a 24-hour-maximum signed query contract, applies noindex/no-store to unlisted REST delivery, requires short-lived secure video/live/podcast delivery grants, and applies private/noindex headers to non-public channel/podcast canonical routes. Static regression contracts cover registration, signing, expiry, headers and all three delivery grant families.

R90 exact head `3ff9cb0a18eaa5c6c1f81af7d7a4b38e930b0dd4` passed File 10 Release QA run `33297710639` before R91 began. This corrected R91 exact head must pass the complete File 10 Release QA before R92 begins.