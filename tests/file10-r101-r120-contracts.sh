#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
# R101 — production studio must be reload-safe and must not expose native integer IDs.
grep -F "production/state','GET','production_state','broadcast'" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'function production_state' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'live_event_public_id' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'source_public_ids' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'Production studio state could not be verified.' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'data-vwlb-production-state' "$P/includes/class-vwlb-future-frontend.php" >/dev/null
grep -F '/production/state' "$P/assets/js/vwlb-future.js" >/dev/null
# Public DTO query selects must not emit raw live_event_id/user_id/credential refs for the studio-state response.
python3 - "$P/includes/class-vwlb-future-rest.php" <<'PY'
import pathlib,re,sys
text=pathlib.Path(sys.argv[1]).read_text()
m=re.search(r'public function production_state\s*\([^)]*\)\s*\{(.*?)\n\t\}',text,re.S)
assert m, 'production_state missing'
body=m.group(1)
for forbidden in ("'user_id'=>", "'live_event_id'=>", "'credential_ref'=>"):
    assert forbidden not in body, f'raw/internal field leaked in production state: {forbidden}'
PY

# R102 — every public route/reference must stay on the opaque public-ID boundary.
grep -F "function is_public_id" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)" "$P/includes/class-vwlb-extended-rest.php" >/dev/null
grep -F "(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F "video_public_ids" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "video_public_id" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "object_public_id" "$P/includes/class-vwlb-extended-rest.php" >/dev/null
grep -F "target_public_id" "$P/includes/class-vwlb-moderation.php" >/dev/null
! grep -F "return array('id'=>\$id,'public_id'=>\$public,'slug'=>\$slug" "$P/includes/class-vwlb-podcasts.php" >/dev/null
! grep -F "'series_id'=>(int)\$ep['series_id']" "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "public_id'=>\$public,'status'=>\$status,'version'=>\$version" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "\$internal=\$video?(int)\$video['id']:0" "$P/includes/class-vwlb-rest.php" >/dev/null


# R103 — schema/migration completeness and non-REST public opaque-ID boundary.
grep -F 'SHOW COLUMNS FROM' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'SHOW INDEX FROM' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_schema_column_missing' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_schema_index_missing' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_legacy_migration_cursor' "$P/includes/class-vwlb-compatibility.php" >/dev/null
grep -F 'WHERE id>%d ORDER BY id ASC LIMIT 500' "$P/includes/class-vwlb-compatibility.php" >/dev/null
! grep -F 'ORDER BY id ASC LIMIT 10000' "$P/includes/class-vwlb-compatibility.php" >/dev/null
grep -F '^video/([a-z][a-z0-9]*_[a-z0-9]+)/([^/]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F '^live/([a-z][a-z0-9]*_[a-z0-9]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F '^podcast/([a-z][a-z0-9]*_[a-z0-9]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F "is_public_id((string)\$id)" "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F "is_public_id((string)\$id)" "$P/includes/class-vwlb-frontend.php" >/dev/null
grep -F 'global $wpdb;if(!VWLB_Helpers::is_public_id' "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "item['thumbnail_url']" "$P/includes/class-vwlb-frontend.php" >/dev/null
! grep -F "item['thumbnail_id']" "$P/includes/class-vwlb-frontend.php" >/dev/null


# R104 — public/read truth, cache privacy and podcast unlisted authorization.
grep -F "public static function read_results" "$P/includes/class-vwlb-db.php" >/dev/null
grep -F "public static function read_row" "$P/includes/class-vwlb-db.php" >/dev/null
grep -F "public static function read_var" "$P/includes/class-vwlb-db.php" >/dev/null
grep -F "VWLB_DB::read_results" "$P/includes/class-vwlb-frontend.php" >/dev/null
grep -F "VWLB_DB::read_results" "$P/includes/class-vwlb-extensions.php" >/dev/null
grep -F "VWLB_DB::read_results" "$P/includes/class-vwlb-future-safety.php" >/dev/null
grep -F "is_wp_error(\$chapters)" "$P/includes/class-vwlb-frontend.php" >/dev/null
grep -F "is_wp_error(\$extras)" "$P/includes/class-vwlb-frontend.php" >/dev/null
# R78 already owned caption cache privacy before R104; keep that canonical guard rather than stacking a duplicate response filter.
grep -F "function caption_cache" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "Cache-Control','private, no-store" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
# Feed/RSS must apply the same object-level unlisted policy as normal podcast reads.
grep -F "can_view(\$series,'podcast_feed')" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "can_view(\$ep,'podcast_feed')" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "can_view(\$series,'podcast_feed')" "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "can_view(\$ep,'podcast_feed')" "$P/includes/class-vwlb-podcasts.php" >/dev/null

echo 'R101-R120 contracts PASS'
