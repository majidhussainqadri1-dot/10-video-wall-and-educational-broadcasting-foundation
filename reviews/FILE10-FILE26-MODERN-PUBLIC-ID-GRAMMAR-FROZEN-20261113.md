# File10 V12.1 ↔ File26 public-ID grammar audit (source frozen before correction)

**Baseline:** modern PR #18 `0cb0b24c4734271baf61417d5f534dd1ec185b67` plus the File26 adapter copied into Draft PR #21.

## Confirmed source contract conflict F26-PI-004 (medium; potential invalid public projection)
- Modern canonical `VWLB_Helpers::is_public_id` accepts `^[a-z][a-z0-9]*_[a-z0-9]+$` (lowercase, **one underscore** separating type and opaque segment).
- The original older File26 connector's `project()` accepted `^[A-Za-z0-9_-]{3,80}$` (with a numeric-only exclusion from F26-PI-003), and its `can_view()` used only emptiness/length/numeric rules.
- Thus noncanonical IDs such as `f26_video_demo` or `video-old-id` could be offered as clickable File26 search documents even though the modern File10 owner-level public-ID contract excludes them.
- This review concerns source-only candidate semantics; no live wrong link was observed.

## Minimal owner-consistent correction
Require `VWLB_Helpers::is_public_id` and 80-character maximum in both `project` and `can_view`. Do not silently remap identifiers or change WordPress rewrite logic. Add negative regression for a noncanonical ID (both list and click-time), preserve a canonical `vid_abc1` case.

## Cross-plugin acceptance note
File26 PR #22 old disposable MariaDB fixtures currently use IDs containing multiple underscores (`f26_video_demo_20261109`), which are not canonical File10 modern `is_public_id` values. Test-fixture alignment and real cross-plugin staging remain necessary before any activation. Do not make File10 more permissive merely to pass outdated fixtures.

**No live bug claim / no site deployment / no File26 activation.**
