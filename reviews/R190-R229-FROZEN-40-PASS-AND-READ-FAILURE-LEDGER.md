# File 10 R190–R229: 40 fresh thematic plan-to-source rounds — frozen BEFORE any source fix

## Immutable baseline
- Reviewed plugin source from exact SHA `8949a2627325bbaa86e2a28ba1346e3f9c0030c0` / candidate `1.2.25-rc1`, sha-bound package contains **77 files** (PHP, CSS, JS, metadata).
- Governing materials: local 2026 File 10 Master Plan, Three-Central-Plans Consolidated Master Plan, File 11 Reels, File 17 Communication, File 25 UI, File 26 Search and bounded File 19/20/21/24 integration contracts.
- 40 distinct source-contract thematic rounds: **29 static indicators present**, **10 uncovered authoritative owner read-failure checks**, **1 external repository-administration blocker**.
- Every round re-read the 77 packaged files but tested a separate scoped invariant; this is **not 40 real WordPress integration runs**.
- The separate 10-case mocked PHP fault-injection suite FAILED all 10 cases on baseline: read failures were misreported as 404 (9 cases) or 422 (one case), not a typed 503.

## Proven defect group: F190 — direct owner command fail-closed reads
Owner service `VWLB_Videos` exposes direct cross-file/callable methods; File10's REST-wide R65 safeguard does not automatically protect non-REST plugin consumers. `VWLB_Repository::find()` and `video_bundle()` correctly set sticky `read_failed()` when DB reads fail, but 10 downstream branches do not test it before coercing null to not-found/policy errors.

The independently proven cases:
1. Playlist create channel authority: mocked database lookup fails; expected 503, actual 404.
2. Playlist membership video row after owner lock: mocked database lookup fails; expected 503, actual 404.
3. Video create asset preflight: mocked database lookup fails; expected 503, actual 404.
4. Video create channel preflight: mocked database lookup fails; expected 503, actual 404.
5. Video publish parent preflight: mocked database lookup fails; expected 503, actual 404.
6. Video publication media scan readiness: mocked database lookup fails; expected 503, actual 422.
7. Caption creation parent: mocked database lookup fails; expected 503, actual 404.
8. Video playback bundle: mocked database lookup fails; expected 503, actual 404.
9. Watch progress parent: mocked database lookup fails; expected 503, actual 404.
10. Like/dislike/save parent: mocked database lookup fails; expected 503, actual 404.

### Authorized minimal correction
For each owner lookup immediately check `VWLB_Repository::read_failed()` and return a safe localized typed 503. Preserve legitimate 404/422 and existing authorization/rights/consent gates; **never clear the sticky read-failure marker inside these services**. In playlist membership mutation fail before adding a membership row; transaction must roll back preliminary deletion. Add and run the fault-injection test in the complete repository QA script; update the exact-head QA provenance only when checks pass.

## Separate blockers (not disguised as runtime defects)
- GitHub R131: active release-candidate branch not protected; repository rulesets API returns `[]`, Issue #10 open.
- GitHub `main` is not the reviewed release candidate; draft PR #11 and #12 remain unmerged.
- No authenticated WordPress source/schema/migration access. WPVibe site connection previously returned Cloudflare HTTP 530. Live source and parity remain unverified.
- Need staging pairing File10–File11–File17, real roles, media providers, replay/privacy, backup/restore, migration, rollback, Founder acceptance and live retest.

**Live First:** Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے.
