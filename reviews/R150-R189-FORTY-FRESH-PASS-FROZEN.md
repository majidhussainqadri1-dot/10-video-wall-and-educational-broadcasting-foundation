# File 10: R150–R189 40 fresh thematic source-review findings (frozen before correction)

Reviewed exact source/package SHA: `53931741090173d8f49e693f442139236c11279f`; version 1.2.25-rc1, verified SHA-bound ZIP.
All 40 review passes re-read each of the 77 packaged files, then checked a distinct implementation/ownership/privacy/reliability topic against File 10 plan, consolidated central plan, and Files 11/17/19/20/21/25/26 boundaries.

These are 40 independent *thematic static/source passes*, not 40 independent full WordPress runtime deployments or 40 full CI reruns. Initial static screen: 37 passes, three candidates; follow-up manual source examination confirmed exactly three new defect groups.

| Round | Scope | Freeze verdict |
|---:|---|---|
| 1 | Bootstrap/autoload | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 2 | File11 contract version | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 3 | DB schema upgrade | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 4 | Migration/deactivation | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 5 | File00 authorization | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 6 | REST object permissions | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 7 | Opaque public IDs | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 8 | Channels | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 9 | Playlists | F150 — playlist row-lock DB failure can be dereferenced before version/destructive mutation |
| 10 | Upload MIME/checksum | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 11 | Resumable ingestion | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 12 | Processing retries | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 13 | Playback | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 14 | Progress/history | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 15 | Captions | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 16 | Rights/consent publication | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 17 | Consent withdrawal | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 18 | Live state | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 19 | Stream credential | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 20 | Moderation | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 21 | Recording/replay | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 22 | Provider/webhook | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 23 | Podcasts/RSS | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 24 | Creator/admin | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 25 | Inbox/outbox | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 26 | File11 reels | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 27 | File11 owner DB failures | F151 — File11 media read failure becomes false 404, lacking explicit 503 |
| 28 | File17 live context | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 29 | File17 DB errors | F152 — File17 live context read failure becomes false 404, lacking explicit 503 |
| 30 | File19 bus | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 31 | File20 shell | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 32 | File21 events | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 33 | File25 RTL UI | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 34 | File26 discovery | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 35 | Future studio guests | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 36 | Future ingest/simulcast | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 37 | Future multilingual | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 38 | Future citations/corrections | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 39 | Future polls/search/watermark | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |
| 40 | Package/lifecycle/governance | Static contract/source passed in audited scope; staging and dynamic acceptance unverified |

## Root causes and correction contract
- F150: `VWLB_Videos::set_playlist_items()` dereferences `$locked['version']` after `VWLB_Repository::find('playlists', ..., true)` without checking `read_failed()` / absent row. A failed SELECT FOR UPDATE must return typed 503 before destructive changes; genuine absence remains 404.
- F151: `VWLB_Extensions::media_contract` and `reels_media_contract` receive `null` from `video_bundle()` when a DB read fails, convert to false 404, and may be called outside the File10 REST post-dispatch DB guard.
- F152: `VWLB_Integrations::file17_live_context()` similarly turns an unverifiable live-event read into false not-found for File17.
- Check sticky `VWLB_Repository::read_failed()` immediately after owner reads; **do not clear that failure flag inside consumer methods**. Verify playlist lock existence/version before writing. Add real 7-case PHP mocked negative regression and bind it to complete File10 CI. Then exact-head QA and package integrity/parity.

## Separate blockers
R131 branch protection/ruleset still absent (Issue #10); this is GitHub administration, not PHP code. WPVibe reports no connected WordPress site, so deployed source/checksum, active DB/schema, migration, staging, provider and live retest cannot be asserted. No changes authorized to live/main or to other canonical domain owners.

Historical 28 defect-bearing / 12 clean R108–R147 rounds are NOT included in these 40 fresh passes.
