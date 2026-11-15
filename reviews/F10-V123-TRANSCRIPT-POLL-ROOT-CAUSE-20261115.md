# File 10 V12.3 — Future transcript/poll source root-cause freeze

**Repository source parent SHA:** `3ee05e231ddab5a7bce6bd8c79683589a3a5d881` (Draft PR #33); **date:** 2026-11-15. **Deployment parity:** UNKNOWN. The Word master V12.3 original pages 163–166 require `F10-FUT-019` live educational polls and `F10-FUT-020` reviewed transcript search with per-viewer authorization and fail-closed states, plus `F10-NFR-001/003/006`.

## Source defects identified by reading actual candidate

- **SRC-TP-001:** `VWLB_Future_Intelligence::search_transcript()` reads canonical owner through `self::video()` without distinguishing repository SQL failure from a genuine unavailable or forbidden object (wrong **404**). The query uses direct `$wpdb->get_results()` and blindly returns `items=null` with HTTP **200** if SQL failed. These are false success/read-truth defects. Correction: check owner read flag, use `VWLB_DB::read_results` for bounded query, stop at **503**, preserve 404 or healthy empty list.
- **SRC-TP-002:** `index_transcript_segment()` reads canonical video without typing SQL outage (wrong **403**) and an optional reviewed `media_tracks` row through `public_row()` without error classification (wrong **422**). It must not INSERT if either source is unknown. Correction: typed 503 owner and track-read; valid missing track remains 422, healthy valid track preserves index behavior.
- **SRC-TP-003:** `poll()` public poll-row read and live owner reads use raw `public_row`/`self::live`, so read failure becomes null/not found; `VWLB_Future_REST::poll_get()` maps that to 404. Correction: typed read for canonical poll and owner, stop at 503; genuine missing/invisible remains null.
- **SRC-TP-004:** `answer_poll()` reads poll and live owner without SQL failure typing (wrong **409**/**404**). Each option reference uses raw `$wpdb->get_var` and silently drops any missing/failed ID. In a multi-choice poll, an SQL error or invalid option can be discarded while valid options are committed as a **partial, false-success answer**. Correction: reject every invalid choice before writes, propagate DB fault as 503, preserve legitimate unknown options as 422, count at least 1 selection, and continue the existing atomic `VWLB_DB::transaction()` for replacement.
  
**Explicitly excluded:** No new schema, provider activation, File26/CF04 cutover, repository main merge, version bump or live patch.

## Test-first verification sequence

1. Author disposable WordPress/PHP mock driver of the *actual* `VWLB_Future_Intelligence` class: canonical SQL fail, transcript DB failure, tracked segment read failure, poll owner failure, one failed option among two, unknown option among two, all valid/empty/missing legitimate states. Verify no changes on bad state.
2. Attach to `tests/run-all.sh`, demonstrate test failure before modifications.
3. Fix only above source methods; keep existing contract and REST DTO.
4. Full source CI on PHP 8.3.36, PHP 8.4.27 and stable aggregate; two sequential analytical reviews and any further proven failures.
5. Keep original 64 requirement **real acceptance** at 0/64 and Issues #13, #22, #26 open pending authorized live/staging verification.

**Hard boundary:** Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
