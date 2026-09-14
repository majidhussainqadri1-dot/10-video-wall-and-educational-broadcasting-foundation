#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
BOOT="$P/video-wall-and-live-broadcasting.php"
GUARD="$P/includes/class-vwlb-r132-caption-cache-guard.php"
REST="$P/includes/class-vwlb-rest.php"

need(){ grep -F -- "$1" "$2" >/dev/null || { echo "R132 contract missing: $3" >&2; exit 1; }; }

need "VWLB_Security::can_view(\$video)" "$REST" r132-caption-parent-auth
need "class-vwlb-r132-caption-cache-guard.php" "$BOOT" r132-guard-autoload
need "VWLB_R132_Caption_Cache_Guard::register()" "$BOOT" r132-guard-register
need "rest_post_dispatch" "$GUARD" r132-final-response-hook
need "'public' === ( \$video['visibility'] ?? 'private' )" "$GUARD" r132-public-only-cache-eligibility
need "'public, max-age=300' : 'private, no-store'" "$GUARD" r132-nonpublic-no-store

php -l "$GUARD" >/dev/null
php -l "$BOOT" >/dev/null

echo "R132 caption cache privacy contracts PASS"
