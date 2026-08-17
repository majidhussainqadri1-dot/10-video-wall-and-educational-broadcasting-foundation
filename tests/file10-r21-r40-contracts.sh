#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r21-r40: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R21 — post-run hardening must not register when core boot/migration verification failed.
need "true !== VWLB_Plugin::instance()->run()" "$P/video-wall-and-live-broadcasting.php" r21-core-boot-gate
need "return false;" "$P/includes/class-vwlb-plugin.php" r21-failure-result
need "return true;" "$P/includes/class-vwlb-plugin.php" r21-success-result
# R22 — public video-wall browse DTO must not expose database owner IDs or WordPress attachment IDs.
need "unset(\$r['id'],\$r['channel_id'],\$r['owner_id'],\$r['thumbnail_id'])" "$P/includes/class-vwlb-repository.php" r22-strip-internal-ids
need "\$r['thumbnail_url']" "$P/includes/class-vwlb-repository.php" r22-public-thumbnail-url
printf '%s\n' 'File 10 R21-R40 sequential contracts PASS'
