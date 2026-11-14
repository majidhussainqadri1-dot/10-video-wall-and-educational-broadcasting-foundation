# File 10 V12.3 — review Pass 1: watermark policy and pre-callback handler

**Review scope:** current advanced source candidate PR #33 branch, no deployed WordPress access. **This is a second-stage source code review**, not independent File24 release acceptance. Governing plan: F10-FUT-023, F10-NFR-001, F10-NFR-003, F10-NFR-006.

Following the first correction batch for Future REST read truth, reviewed the actual registered `VWLB_R79_Watermark_Session_Guard::guard` and the provider filter `VWLB_Future_Intelligence::watermark_payload`.

## Frozen source findings

**SRC-RI-005 — actual registered watermark pre-callback guard masks SQL failure as 404.** At `class-vwlb-r79-watermark-session-guard.php::guard()`, `self::object()` reads File10 owner through repository but the condition `!$object` is conflated with a SQL outage. The post-regression direct `Future_REST::watermark_grant` is not sufficient, because R79 intercepts the route at `rest_request_before_callbacks`. Root cause: R79 lacks typed repository failure verification on the pre-callback path. **Corrective target:** reset read-state, verify `VWLB_Repository::read_failed()` and return 503 on failure before permission or policy processing; preserve 404 for genuine absence.

**SRC-RI-006 — watermark policy lookup failure can yield false 'off' success.** `VWLB_Future_Intelligence::watermark_payload()` uses raw `$wpdb->get_row` without DB error checking, then returns original payload when policy is absent; a SQL outage is interpreted as an explicit off-policy. This filter can be used on playback outside the separate watermark REST route. **Corrective target:** explicit SQL read error -> WP_Error 503; a healthy empty policy remains unchanged.

**SRC-RI-007 — watermark policy mutation may insert when existing-policy read fails.** `VWLB_Future_Intelligence::set_watermark_policy()` reads current version with `$wpdb->get_row` but fails to distinguish SQL failure from genuine first creation. A failed read can enter INSERT branch; this is a source authorization/state-integrity risk even when SQL insert eventually fails. **Corrective target:** fail before INSERT with a typed 503, and verify post-write owner row instead of returning unchecked null after commit.

## Test plan (isolated actual class executions)

- Simulate read-error vs legitimate absence on R79 pre-callback for both video and live; verify false outcome never becomes a normal 404, and healthy off-policy returns 200 no-store.
- Simulate watermark filter SQL outage and a healthy empty policy; require typed 503 vs unchanged payload.
- Simulate watermark policy read outage and assert zero INSERT/UPDATE; test healthy insert/versioned update and final post-write read outage response.
- Re-run complete suite on PHP 8.3.36 and 8.4.27 before a second adversarial review.
- Preserve draft, no actual stage/live proof, no version bump, no database schema change, no deployment.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
