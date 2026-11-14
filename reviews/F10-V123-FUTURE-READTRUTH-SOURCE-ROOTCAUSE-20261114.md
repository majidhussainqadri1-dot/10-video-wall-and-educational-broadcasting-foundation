# File10 V12.3 — frozen source findings for Future read-integrity

**Reviewed exact parent SHA:** `32fcbdddca8c1880533f35d50abc47fb6580f815`; PR #32 Draft.  
**Environment:** advanced GitHub source only, **not** proven Live or Staging.

**Governing V12.3:** `F10-FUT-001` multicamera scenes, `F10-FUT-017` evidence links, `F10-FUT-020` search/captions, `F10-FUT-023` watermark grants; `F10-NFR-001` object/field authorization and `F10-NFR-003` reliability/degraded mode. User-provided Master Word SHA256 `c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf`, original pages 163–166.

## Source root causes (NOT live incident determinations)

**SRC-RI-001 — Future public-ID lookup misreports DB errors as missing IDs.**
`VWLB_Future_REST::row_id_from_public()` calls `$wpdb->get_var()` without testing `$wpdb->last_error` and casts the result to integer. If MySQL fails, `source_save`, `scene_save`, `simulcast_save`, or `transcript_index` can report a false 404/422 and may conceal a recoverable database outage. Fix: delegate to typed `VWLB_DB::read_var`, propagate `WP_Error(503)` without any mutation, and preserve legitimate missing ID errors.

**SRC-RI-002 — Public watermark issuance owner-read error mapped to absence.**
`VWLB_Future_REST::watermark_grant()` looks up a video/live event via `VWLB_Repository::find` and converts both SQL failure and true missing object to 404, losing NFR-003 diagnostic truth. Fix: reset owner read flag at API entrance, return typed 503 on `read_failed`, preserve 404 for genuine absent/ineligible object; keep no-store.

**SRC-RI-003 — Future enrichments can turn a failed owner read into a successful empty response.**
`VWLB_Future_Safety::rest_live()` reads owner state after `VWLB_Live::state` but does not check `VWLB_Repository::read_failed`; `published_tracks` can return empty track array on SQL failure; `annotations` can emit 404 rather than 503. Fix: verify each owner read before enrichment, no false success/empty response on failed owner read, preserve correct 404 on genuine absence and authorization denials.

**SRC-RI-004 — Studio view’s first owner query masks SQL outage with a 403.**
`VWLB_Future_REST::production_state()` checks the returned object and permission but not the typed read failure flag. Fix: report 503 for a verified source read error and 403 for real authorization denial.

### Execution order and acceptance

1. Add isolated adversarial test reproducing SQL failure/no-write and healthy/absent conditions against actual candidate PHP classes.
2. Apply the smallest source corrections above; avoid unrelated schema changes, deployment, provider activation or cross-repository patch stacking.
3. Re-run isolated tests and full PHP 8.3 + 8.4 source QA.
4. Do two sequential source reviews (first behavior/ownership/contract, second fault/policy/boundary), fix proven findings and rerun.
5. Retain Draft PR, original 64-row ledger and 0/64 real Staging/Live acceptance unchanged.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔** No root cause for a Live bug is asserted by these findings.
