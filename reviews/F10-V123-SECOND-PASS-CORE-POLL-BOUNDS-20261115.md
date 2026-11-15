# File10 V12.3 — additional source review B: interior poll work boundedness

**Date:** 2026-11-15. **Code already source-green at:** `b1ca27d4c8cf9cf3cfc6ab90af9fd0049b6c573c`. Exact deployed runtime remains unknown. Governing V12.3: `F10-FUT-019` Live Polls, `F10-NFR-004` bounded performance, `F10-NFR-003` reliability.

**SRC-TP-008 — endpoint-only option cap, inner domain methods unrestricted.** `VWLB_R77_Poll_Integrity::guard()` caps REST poll creation/answer arrays at 20 options, and `VWLB_Future_Safety::create_poll()` also caps, but the shared **domain methods** `VWLB_Future_Intelligence::create_poll` and `::answer_poll` do not. An internal caller or an alternate endpoint which directly calls the domain methods may process arbitrarily many distinct IDs and insert many option rows, breaching the 20-item contractual limit and bounded-query principle. This is an independently reviewable source invariant, **not proof of a Live exploit**.

**Minimal fix:** Set a domain-internal max of 20 normalized non-empty options and 20 distinct answer public IDs, **before any SQL reads or writes**. Preserve legitimate 2–20 options and normal role/ownership/transaction checks. Do not change public JSON DTO, schema, or R77 guard. Avoid a new global static patch layer.

**Test-first:** Invoke *actual* Future Intelligence PHP `create_poll` with 21 unique valid entries; assert 422, no SQL writes; invoke direct `answer_poll` with 21 distinct opaque IDs; assert 422 and zero option reads/writes; 20-item authenticated inputs are accepted only under existing correct owner/DB conditions. Ensure full PHP 8.3/8.4 CI after the minimal fix.

**No Staging/Live acceptance, external CF04/File26 activation, or protected-branch modification.**
