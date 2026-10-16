# File 10 Release Candidate Manifest — 1.0.0-rc1

## Canonical implementation

- Plugin folder: `video-wall-and-live-broadcasting`
- Plugin version: `1.0.0-rc1`
- Text domain: `video-wall-live-broadcasting`
- PHP namespace prefix: `VWLB_`
- Governing scope: recorded video, channels, playlists, media ingest/processing contracts, captions, playback, history, live events, stream credentials, live moderation, recording/replay, privacy, audit, diagnostics, repair and cross-file adapters.

## Release evidence

- GitHub Actions artifact: `file10-video-wall-live-1.0.0-rc1`
- Generated package: `packages/video-wall-and-live-broadcasting-1.0.0-rc1.zip`
- Generated checksum: `packages/video-wall-and-live-broadcasting-1.0.0-rc1.zip.sha256`
- `docs/REQUIREMENTS-TRACEABILITY.md`
- `docs/REVIEW-ROUND-1.md`
- `docs/REVIEW-ROUND-2.md`
- `docs/STAGING-ACCEPTANCE-1.0.0-rc1.md`
- `STATUS-1.0.0-rc1.md`

## Automated gates

- PHP syntax lint
- JavaScript syntax check
- state-machine unit tests
- static contract tests
- deterministic package build
- ZIP integrity and canonical top-level folder
- package/source parity

## External gates deliberately not claimed

Hostinger staging, real provider credentials and webhooks, real transcoding/CDN/object storage, real browser/device/accessibility evidence, backup/restore, rollback rehearsal, live deployment and Founder acceptance remain separate environment-dependent gates.
