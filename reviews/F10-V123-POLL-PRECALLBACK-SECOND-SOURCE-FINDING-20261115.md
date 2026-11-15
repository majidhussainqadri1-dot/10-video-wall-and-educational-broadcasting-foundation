# File10 V12.3 — Poll registration boundary, second source review finding

**2026-11-15; source parent at first-pass correction:** `fcdbb177e1d7b21086dafdc885f7cd11c48bdea6`. CI source QA green after first four findings. **Original master V12.3**, F10-FUT-019 (live educational polls) / F10-NFR-003 (reliability). Not deployed truth.

**SRC-TP-005 verified source defect:** The real REST `rest_request_before_callbacks` hook `VWLB_R77_Poll_Integrity::guard()` intercepts poll creation and invokes `::create()` *instead of* the normal `VWLB_Future_REST::poll_create()`. Its first canonical `VWLB_Repository::find('live_events',...)` never checks `read_failed()`. On a real SQL read failure (as opposed to a truly missing/unauthorized event), it returns a misleading `403 vwlb_forbidden`; the client cannot distinguish backend outage from permission refusal and cannot safely retry with meaningful diagnostics. A narrow fix should check owner repository failure before denial. The pre-callback route for answers already uses the Future intelligence method corrected in Pass 1; do not duplicate it.

**Test-first:** Extend actual PHP-class negative driver with request route and the real R77 guard; assert SQL outage gives 503 with **zero inserts**, real missing event remains 403, healthy poll creation continues normally. Demonstrate red before the fix and green after. Never claim WordPress/staging/live completed from these mocks.

**Out of scope:** no File26 activation, no new schema, no Live, no merge with older divergent Draft PRs. CEN-REL-001 remains blocked.
