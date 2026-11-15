# File10 V12.3 — Frontend Privacy and Rights Review (Frozen Before Correction)

Date: 2026-11-15. Source base: exact SHA `ce1a4ca6476fa1c764bca4efa8ee2cae29b75eab` (source Draft PR #35); site-installed code unknown.

## Governing requirements

- F10-FR-006/007/008/019 (rights-aware publishing and playback, history privacy, takedown)
- F10-NFR-001/002/003 (object authorization, privacy lifecycle, truthful failure)
- F10-FUT-022 (case-video consent withdrawal)
- V12.1-STO-07/09 (revocable delivery and deletion)
- M09 / UX-03, 12 / QA-22 (accessible low-bandwidth and current owner state)

## Exact code-level findings (not Live symptom assertions)

1. **F10-FE-01 — Raw low-bandwidth URL exposure (high):** The video shortcode reads `media_assets.derivatives_json` and emits `data-low-bandwidth-src` directly for any otherwise authorized playback, including private/member/entitled and patient-case videos. A protected standard playback grant does not establish that this raw low-rendition URL is revocable or bound to the viewer. This creates a direct-URL authorization bypass risk. No raw secondary URL should be sent to the browser for a non-public or patient-case object without a separate provider-bound short-lived grant. Minimize the correction: suppress that unsupported secondary mode while retaining authorized standard playback.
2. **F10-FE-02 — Stale watch-history titles (high):** `history()` queries a user's previously watched videos by SQL JOIN and renders `title` and direct URL without a current per-object visibility, rights, consent or deletion check. A previously viewed but later withdrawn/removed patient-case video may remain visible in history, violating revocation. Current rights/consent must be checked before a title is rendered; policy DB failures must be shown as an unavailable state, not silently accepted.
3. **F10-FE-03 — Private channel metadata leakage (high):** `channel()` reads channels by `slug` and `status='active'` without `visibility='public'` or `deleted_at IS NULL`. A non-public active channel can have its title/description rendered by public shortcode, despite a private visibility policy.

Related source-read hygiene: `video()` performs second direct owner/asset reads after playback authorization without checking `VWLB_Repository::read_failed()`. Do not claim an updated source state where re-read failed.

## Test-first expectations

- Mock authorized private video playback with raw low derivative `https://private.example.test/raw-low.mp4`: the private URL must NOT appear in rendered HTML; standard authorized source remains available.
- Public non-patient-case video with approved low rendition may retain low-bandwidth mode; patient-case video must NOT reveal raw derivative even if public/consented.
- Historical previously visible content now restricted/withdrawn must not display its title; current authorized content must display; policy read outage must return 503 unavailable state.
- Private active channel slug must not disclose metadata; only public undeleted channel can be displayed.
- Failed secondary owner/asset read must be disclosed as unavailable, not fabricated success.
- Tests in `tests/file10-v12-3-frontend-privacy.php` must fail before source correction; then exact-head PHP source QA and integration must pass.

## Scope boundary

Changes apply to a source candidate only; NO live incident root cause, no schema migration, no new media grant provider, no production deployment. Real provider integration and correct signed low-bandwidth grants remain a separate gate. Existing Live access issue #13, repository ruleset issue #22, File26 purge/ACK issue #26 remain open. `0/64` individually Staging/Live accepted.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## Source-schema correction before implementation (same 2026-11-15 review)

Read-only inspection of `VWLB_DB::schema_sql()` established that the `channels` table has `status` and `visibility` but **no `deleted_at` column**. The original finding above correctly identifies missing public visibility filtering but incorrectly suggested a channel soft-delete predicate. Do **not** add `channels.deleted_at IS NULL` to the SQL; it would be an invalid query and a new bug. Instead, require `status='active'` and `visibility='public'` only. This correction was recorded before modifying application code. The corresponding synthetic fixture must assert public visibility without inventing schema columns.
