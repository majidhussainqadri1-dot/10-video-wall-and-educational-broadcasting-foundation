#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WF="$ROOT/.github/workflows/file10-release.yml"

fail() { echo "FAIL r128: $1" >&2; exit 1; }

[[ -f "$WF" ]] || fail "canonical release workflow missing"

grep -Fq "if: github.event_name == 'push' && matrix.php == '8.3.33' && env.VWLB_PUBLISH_REVIEW_ARTIFACT == '1'" "$WF" \
  || fail "artifact publication is not restricted to canonical push QA"

grep -Fq 'name: file10-video-wall-live-1.2.25-rc1-${{ github.sha }}' "$WF" \
  || fail "release-candidate artifact name is not bound to github.sha"

if grep -Fq 'name: file10-video-wall-live-1.2.25-rc1' "$WF" && \
   ! grep -Fq 'name: file10-video-wall-live-1.2.25-rc1-${{ github.sha }}' "$WF"; then
  fail "fixed SHA-less release-candidate artifact name detected"
fi

echo "PASS r128 artifact provenance contract"
