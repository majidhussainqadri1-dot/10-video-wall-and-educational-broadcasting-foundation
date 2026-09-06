# File 10 — R104 Final Frozen Findings — 2026-09-07

Baseline exact HEAD reviewed: `e0a7ae7efba4c2287dc57c3978e4ef9953ef3aa3` (`1.2.14-rc1`), exact-head File 10 Release QA run `34064836972` green on PHP 8.3/8.4 before R104 began.

## Review discipline

R104 was completed as a read-only review before any R104 product correction. The review covered public/core REST reads, extended and Future read helpers, repository-read failure propagation, public cache policy, podcast feed/RSS authorization, public frontend/list/detail paths, live/video playback authorization, chapters/premieres and cross-file public contracts.

## Frozen findings

1. **Direct database read failures can masquerade as legitimate empty/not-found public state outside the repository guard.** `VWLB_R65_Repository_Read_Guard` only detects `VWLB_Repository::read_failed()`. Several public or public-facing paths query `$wpdb` directly without checking `$wpdb->last_error`, including core live browsing, extended podcast-series resolution, Future public-object resolution/annotations/transcript search/polls, chapter/premiere reads, podcast episode/feed projections and frontend live/channel/podcast/history lists. A database read failure can therefore become an empty `200`, a misleading `404`, a zero aggregate, or a partially empty page instead of a fail-closed service error.

2. **Caption response caching can disclose access-controlled caption content through shared caches.** `VWLB_REST::caption()` correctly authorizes the parent video with `VWLB_Security::can_view()`, but then always emits `Cache-Control: public, max-age=300`. For a private/member/entitled/unlisted video viewed by an authorized caller, the caption bytes are therefore marked share-cacheable even though the parent media is not public.

3. **Podcast feed/RSS bypasses the platform's unlisted authorization policy.** `VWLB_Podcasts::feed()` accepts both `public` and `unlisted` series and episodes on a public endpoint without applying `VWLB_Security::can_view()` to the series/episode rows. The individual podcast DTO does apply `can_view()`, so feed/RSS is an inconsistent alternate read surface. Because the feed includes derived audio URLs and the RSS response is publicly cacheable, an unlisted item can be exposed to callers who would fail the normal unlisted access policy.

## Correction gate

All three findings must be corrected together in the R104 correction phase. The correction must add direct-read error truthfulness on the affected public/read-helper surfaces, make caption cache policy follow parent visibility, and make podcast feed/RSS enforce the same object authorization as individual podcast reads. A dedicated R104 regression gate plus the full File 10 suite, exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive verification and package/source parity are required before R105 may begin.
