#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
for n in A B C; do php "$ROOT/tests/file10-r270-negative-${n}.php"; done
