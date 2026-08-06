# File 10 — Video Wall and Live Broadcasting

Canonical implementation repository for **Sabri Social Homeopathy Platform File 10**.

## Candidate

- Source version: `1.0.0-rc1`
- Plugin folder: `video-wall-and-live-broadcasting`
- Text domain: `video-wall-live-broadcasting`
- PHP namespace/prefix: `VWLB_`
- Package: `packages/video-wall-and-live-broadcasting-1.0.0-rc1.zip`

The implementation covers the recorded-video and live-broadcasting code scope defined by `SSH-F10-PLAN-2026-v1.0`. Production acceptance remains evidence-based: Hostinger staging, real providers, migration, accessibility, backup/restore, rollback and Founder sign-off are separate gates.

## Local verification

```bash
./tests/run-all.sh
./tools/build-package.sh /tmp/file10.zip
unzip -t /tmp/file10.zip
```

## Repository law

The original `0.1.0` and corrected `0.2.0` packages remain provenance records. The new canonical source is `video-wall-and-live-broadcasting/`. No secret, live stream key, provider token or private incident material may be committed.
