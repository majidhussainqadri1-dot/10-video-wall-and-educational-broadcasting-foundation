# File 10 — R110 Frozen Findings — 2026-09-07

## Governing review discipline

R110 was completed as a read-only review before any R110 product correction began. This document freezes the complete R110 defect ledger. The correction phase may begin only after this freeze, and R110 cannot be called Green until the corrected exact HEAD passes the complete File 10 release QA.

## Exact review baseline

- Branch: `fix/file10-r101-r120-sequential-2026-09-06`
- Exact reviewed HEAD: `779952fe108a3d4b87192c87305a7ff48ea7ec20`
- Candidate identity reviewed: `1.2.20-rc1`
- Prior exact-head release QA: run `34113080172`, PHP 8.3/8.4 Green.
- Scope: public/private/unlisted/member/entitled delivery, current rights/consent enforcement, cache/noindex behavior, playback, captions, podcasts/RSS, downloads, media-contract delivery, public-ID boundaries, SEO/public discovery and security-sensitive revalidation reads.

## Frozen finding R110-01 — late playback override weakens the canonical opaque public-ID route boundary

`VWLB_R3_Playback::register_rest_overrides()` re-registers playback with `(?P<id>[A-Za-z0-9_-]+)` after the stricter canonical route has already been registered. The generic numeric pre-dispatch guard rejects ordinary all-digit values, but the repository still uses `is_numeric()` to decide whether a lookup is a native primary-key lookup. Numeric strings that are not `ctype_digit()` (for example scientific-notation forms) can therefore cross the intended public-ID boundary through the broad late override. The same broad legacy identifier grammar remains in R71/R72/R78/R79/R91 security-sensitive route matchers, creating contradictory route semantics.

Required correction: restore the canonical `^[a-z][a-z0-9]*_[a-z0-9]+$` boundary on playback and normalize the security-sensitive delivery matchers to the same grammar. Public REST paths must never depend on the native-ID branch of repository lookup.

## Frozen finding R110-02 — public video browse can expose stale discovery metadata after current rights/consent becomes invalid

`VWLB_Repository::browse_videos()` selects `published + public + not-deleted` rows and emits browse items without applying `VWLB_Security::can_view()` / the current R109 rights-consent gate to each candidate. Detail/SEO paths are better protected because `public_video_dto()` calls `can_view()`, but the browse/catalog path can continue to advertise a video whose current rights or patient-case consent no longer permits delivery until asynchronous reconciliation changes the stored video status.

Required correction: include the current-policy fields required for authorization in the browse projection and filter every candidate through `VWLB_Security::can_view(..., 'browse_video')`, while preserving fail-closed database-read truth and cursor progression.

## Frozen finding R110-03 — video consent-link lookup can collide with podcast internal IDs

`VWLB_R109_Rights_Consent_Replay_Guard::consent_allow()` treats any published object with an internal `id` as eligible for the `consent_links.video_id` lookup. Podcast episodes also have `published_at`, `id`, and their own `consent_status`. A podcast episode whose internal ID equals an unrelated video's ID can therefore be incorrectly denied by that video's expired/withdrawn consent-link record.

Required correction: retain the object-level `consent_status` rule where applicable, but run the `consent_links.video_id` history query only for canonical video rows (the video schema is distinguishable by its video-specific rights/access fields), never for podcast/live entities.

## Frozen finding R110-04 — security-sensitive delivery revalidation can fail open or run on an incomplete authorization projection

R65 converts repository read failures to 503 only at its early `rest_request_after_callbacks` priority. Several later delivery guards perform new verification reads after R65 has already completed:

- R91 re-reads video/live/podcast state before replacing unlisted media with short-lived secure grants. A failed/missing second read can return the original response, including raw unlisted playback data.
- R72 re-reads podcast episode/media state before replacing private/non-public podcast audio with a secure grant. A failed second read can leave the original audio response unchanged.
- R78 re-reads caption/video state after a caption callback that may have set a public cache header. A failed second read can leave sensitive caption content publicly cacheable.
- R71 performs alternate private-download verification before the canonical callback. If its object/asset verification cannot be proven, falling through to the older callback can permit that callback's raw-derivative path to execute.
- R78 podcast-feed revalidation selects only `public_id,title,description,duration_seconds,published_at,asset_id` and then calls `VWLB_Security::can_view()` on that incomplete row. Because authorization-significant fields such as `status`, `visibility`, `owner_id`, `rights_status` and `consent_status` are absent, a valid public episode can be evaluated as an unpublished/private object and silently omitted from the hardened feed. This is both a correctness defect and evidence that security revalidation must use a complete policy projection.

Required correction: each security-sensitive alternate/late delivery guard must own fail-closed read truth for every read it performs, including direct `$wpdb` and repository reads. A verification outage or contradictory second-read state must return a service error rather than preserve/fall through to the original delivery. Revalidation queries must include every field consumed by `can_view()` and the current rights/consent policy. Do not solve this by merely moving R65 later, because that would change mutation/idempotency response ordering.

## Frozen finding R110-05 — media contract treats unlisted delivery as public and canonical internal contract can expose raw derivatives

The hardened REST media-contract override treats only visibility other than `public`/`unlisted` as protected. Consequently an authorized signed unlisted request can receive raw derivative URLs and lacks the required private/no-store/noindex delivery semantics. In addition, the canonical `VWLB_Extensions::media_contract()` cross-file contract returns raw derivative coordinates after `can_view()` even for non-public authorized media; the REST override does not protect non-REST File 10/File 11 consumers of that contract.

Required correction: only truly `public` media may receive ordinary public derivatives. Every non-public visibility, including `unlisted`, must use an explicit short-lived `vwlb_secure_media_contract_grant` projection (or fail closed), and REST responses for such contracts must be `private, no-store`; unlisted responses must also be `noindex, nofollow, noarchive`. Apply the rule in the canonical media-contract implementation as well as the REST override so cross-file consumers cannot bypass it.

## Reviewed suspicions that were not frozen as defects

- Video SEO detail output was checked and is guarded through `public_video_dto()` / `can_view()`.
- Live SEO is guarded through `VWLB_Live::state()` / `can_view()`.
- Future public annotations, transcript search and poll reads call current object-level `can_view()`; no separate R110 authorization defect was established there.
- Podcast feed/RSS object-level unlisted authorization was already corrected in earlier rounds; R110's new feed issue is the incomplete late-revalidation projection described in R110-04, not a reopening of the earlier authorization rule.

## R110 correction gate

All five frozen findings must be corrected together, followed by dedicated R110 regression assertions, the complete File 10 automated suite, the R101–R120 sequential gate, exact-head PHP 8.3/8.4 release QA, deterministic package build, checksum/archive verification, source/package parity and artifact publication. Only then may R110 be marked Green and the required R101–R110 defect-round report be issued.

Live/staging remain separate realities. Exact deployed code, live DB/schema and migration state are not established by this repository review.