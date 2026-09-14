# R140 Frozen Findings

Review mode: read-only until this ledger was frozen.

Reviewed baseline: `a6cf87f6f3aa491bb28fa01cd7e06ddf49dc604a` (R139 exact-head Release QA Green, run `34885111283`).

Scope included moderation report target resolution, opaque identifier requirements, reporter visibility checks, moderation decision authorization, transactional case decisions, slow-mode/live restrictions, restore safety, previous-state evidence, cross-case restoration blockers, consent blockers, copyright/takedown claimant identity, claimant-vs-moderator transition authority, case version checks, target restriction/removal/restoration, audit/outbox evidence, and fail-closed blocker reads.

## Frozen findings

No proven defect was found in this round. Moderation and takedown mutations remain case-bound, version-aware and authorization-gated; restoration requires proven prior state and refuses restoration while another moderation, takedown or consent blocker remains. No code correction is authorized for R140.

R131 repository-governance finding remains separately open and is not treated as corrected by this clean moderation/copyright review.

Live-First rule remains in force: exact deployed source was not available for this review; exact deployed code is unverified, and repository-based diagnosis is provisional.
