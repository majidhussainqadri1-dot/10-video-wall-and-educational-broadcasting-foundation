#!/usr/bin/env bash
set -euo pipefail

workflows=(
  .github/workflows/r110-correction.yml
  .github/workflows/r111-correction.yml
  .github/workflows/r112-correction.yml
  .github/workflows/r113-correction.yml
  .github/workflows/r115-correction.yml
  .github/workflows/r116-correction.yml
  .github/workflows/r119-correction.yml
)

for workflow in "${workflows[@]}"; do
  test -f "$workflow" || { echo "FAIL r125: missing $workflow"; exit 1; }
  grep -Fq 'workflow_dispatch:' "$workflow" || { echo "FAIL r125: $workflow is not manual/archive-only"; exit 1; }
  grep -Fq 'contents: read' "$workflow" || { echo "FAIL r125: $workflow lacks read-only permission"; exit 1; }
  if grep -Eq '^[[:space:]]+contents:[[:space:]]+write([[:space:]]|$)' "$workflow"; then
    echo "FAIL r125: $workflow retains contents: write"
    exit 1
  fi
  if grep -Eq '^[[:space:]]+push:[[:space:]]*$' "$workflow"; then
    echo "FAIL r125: $workflow retains a push trigger"
    exit 1
  fi
  if grep -Eq 'git[[:space:]]+push' "$workflow"; then
    echo "FAIL r125: $workflow retains repository push logic"
    exit 1
  fi
done

echo 'PASS r125 archived correction workflow boundary'
