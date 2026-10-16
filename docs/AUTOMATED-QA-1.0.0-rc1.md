# Automated QA — File 10 1.0.0-rc1

## Local verified result before GitHub publication

The canonical source tree was tested with the repository scripts before publication:

- PHP syntax across all canonical PHP files: PASS
- JavaScript syntax: PASS
- Recorded-video, asset, live and takedown state-machine tests: PASS
- Requirements/static security contracts: PASS
- Secret-pattern scan: PASS
- Deterministic package build A/B byte comparison: PASS
- ZIP integrity and canonical top-level folder: PASS

Expected terminal result:

```text
state-machine tests PASS
static contracts PASS
all File 10 automated checks PASS
```

## GitHub Actions gate

`.github/workflows/file10-release.yml` repeats the suite on PHP 8.1 and PHP 8.3, rebuilds the ZIP, checks SHA-256 and package/source parity, and publishes the release-candidate artifact only after success.

## Evidence boundary

This document records source/package QA, not Hostinger staging, provider credential, live streaming, browser/device, accessibility, backup restoration, rollback or production acceptance.
