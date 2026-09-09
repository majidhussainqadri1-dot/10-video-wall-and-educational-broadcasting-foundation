#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WF="$ROOT/.github/workflows/file10-release.yml"

fail(){ echo "FAIL r126: $1" >&2; exit 1; }

[[ -f "$WF" ]] || fail 'canonical release workflow missing'
grep -Fq 'runs-on: ubuntu-24.04' "$WF" || fail 'runner release is not explicitly bounded to ubuntu-24.04'
if grep -Eq 'runs-on:[[:space:]]+ubuntu-latest' "$WF"; then
  fail 'moving ubuntu-latest runner selector reintroduced'
fi
if grep -Eq 'apt-get[[:space:]]+(update|install)' "$WF"; then
  fail 'live APT package resolution reintroduced into canonical QA'
fi
grep -Fq 'command -v zip' "$WF" || fail 'zip fail-closed availability check missing'
grep -Fq 'command -v unzip' "$WF" || fail 'unzip fail-closed availability check missing'
grep -Fq 'zip -v' "$WF" || fail 'zip version provenance output missing'
grep -Fq 'unzip -v' "$WF" || fail 'unzip version provenance output missing'

echo 'PASS r126-environment-drift-boundary'
