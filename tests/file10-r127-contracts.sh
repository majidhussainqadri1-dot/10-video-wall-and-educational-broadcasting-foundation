#!/usr/bin/env bash
set -euo pipefail

workflow='.github/workflows/file10-release.yml'

fail() {
  printf '%s\n' "FAIL r127-php-patch-pinning-boundary: $1" >&2
  exit 1
}

grep -Fq "php: ['8.3.33','8.4.25']" "$workflow" || fail 'exact PHP patch matrix missing'
if grep -Fq "php: ['8.3','8.4']" "$workflow"; then
  fail 'major/minor-only PHP matrix remains'
fi

grep -Fq 'actual="$(php -r '\''echo PHP_VERSION;'\'')"' "$workflow" || fail 'runtime PHP_VERSION capture missing'
grep -Fq 'test "$actual" = "$expected"' "$workflow" || fail 'runtime exact-version assertion missing'
grep -Fq 'Run R127 PHP patch pinning gate' "$workflow" || fail 'R127 gate is not wired into canonical QA'

printf '%s\n' 'PASS r127-php-patch-pinning-boundary'
