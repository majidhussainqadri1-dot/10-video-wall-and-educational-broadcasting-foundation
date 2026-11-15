# File 10 V12.3 — correction publication event accuracy

**Review date:** 2026-11-15. **Parent source candidate:** `0f7343e9f21957846ad6c297db4cd7a541f83d65` (the prior four transcript/poll + R77 source findings are already green in GitHub source CI). **Live deployed version:** unverified.

**V12.3 exact duty:** F10-FUT-021 "Correction-at-Timestamp System" requires an append-only scholarly correction/retraction notice, proper review and truthful publication; F10-FUT-017 source reference ownership; F10-NFR-003 reliable event/state semantics; File26 consumer must not infer published content from drafts.

## New source root cause SRC-TP-006

The actual `VWLB_Future_Intelligence::create_annotation()` sets `status='reviewed'` when creating a reviewer-approved **correction**. In the **same create function**, it immediately emits `VWLB_Helpers::outbox('VideoTimestampCorrectionPublished',...)` despite no `reviewed -> published` state transition yet. The canonical `VWLB_Future_Intelligence::transition_annotation()` **already emits** `VideoTimestampCorrectionPublished` at the real `publish` transition. The create event is therefore a false-positive publication fact and may prematurely invalidate search/citation caches, with an additional duplicate event after actual publication.

### Exact minimal correction

- A reviewed but unpublished correction may be saved, edited/reviewed under its current rules, but **must not** emit a `Published` outbox event.
- Keep the existing publish transition as the only source of `VideoTimestampCorrectionPublished`; its parent `VideoAnnotationPublished` event still accompanies it.
- Do not introduce a new event schema or dispatch without File26 owner-tombstone/ACK acceptance.
- Add actual class test: create correction -> 1 candidate source write, `status=reviewed`, **zero Published events**; subsequent `publish` transition -> exactly one `VideoAnnotationPublished` + one `VideoTimestampCorrectionPublished`; denied/invalid transitions -> zero publication events.
- Test first, red CI, then minimal one-line source fix; run both PHP source matrices and two source review passes.

**Not a proved Live bug.** Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
