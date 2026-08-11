# File 10 — Video Wall and Live Broadcasting

Canonical File 10 source for recorded video, channels/playlists, private ingest/processing, captions/transcripts, player/history, podcasts, premieres, live scheduling/stream authorization/moderation/recording/replay, rights/privacy, creator operations and provider adapters.

**Current source candidate:** `1.1.0-rc1` on the dedicated completion branch. It is a source/package candidate only; it is not a live-deployment claim.

Key contracts: canonical REST `video-wall-live-broadcasting/v1` with compatibility alias `vwlb/v1`; PHP prefix `VWLB_`; text domain `video-wall-live-broadcasting`. Raw private uploads are fail-closed outside the public Media Library. File 11 owns Reel entities; File 17 owns chat/relationships; File 19/21/26 consume events; File 20/25 own shell/visual presentation.

See `docs/REQUIREMENTS-TRACEABILITY.md`, `docs/GOVERNING-PLAN-ALIGNMENT-1.1.0-rc1.md`, and `docs/STAGING-ACCEPTANCE-1.1.0-rc1.md`.
