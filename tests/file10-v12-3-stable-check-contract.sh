#!/usr/bin/env bash
# Source-only structural guard for stable GitHub required-check candidate.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WF="$ROOT/.github/workflows/file10-release.yml"
test -r "$WF"
for literal in \
  'source-package-qa:' \
  "matrix:" \
  "php: ['8.3.36','8.4.27']" \
  'file10-stable-source-gate:' \
  'name: File10 Source Aggregate Gate' \
  'needs: [source-package-qa]' \
  'if: ${{ always() }}' \
  "MATRIX_RESULT: \${{ needs['source-package-qa'].result }}" \
  'test "$MATRIX_RESULT" = "success"'; do
    grep -F -- "$literal" "$WF" > /dev/null || {
        echo "File10 source QA release gate malformed or missing: $literal" >&2
        exit 1
    }
done
if grep -Eq 'name: File10 Source Aggregate Gate \([0-9]+\.[0-9]+\.[0-9]+\)' "$WF"; then
    echo 'File10 stable QA context includes volatile PHP patch number' >&2
    exit 1
fi
echo 'File10 source aggregate check contract PASS; effective GitHub ruleset protection still requires administration'
