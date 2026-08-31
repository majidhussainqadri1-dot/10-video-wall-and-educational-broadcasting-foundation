#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r101-r120: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
reject(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R101 — activation rollback must cover the exact fully-filtered role/capability mutation map.
need "public static function capture_role_map" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-capture
need "vwlb_activation_role_capabilities" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-filter
need "PHP_INT_MAX" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-after-companion-filters
need "captured_at" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-captured-evidence
need "rollback evidence was not captured before commit" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-commit-gate
need "foreach((array)\$caps as \$cap=>\$had)" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-exact-restore
reject "private static function caps()" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-no-hardcoded-rollback-map

# R102 — clean source review: resumable/private ingest durability remains covered by retained R45/R70 contracts.

# R103 — processing jobs for positive asset IDs must never be created from missing/unreadable asset truth or silently default to local.
need "VWLB_Repository::reset_read_failure();\$asset=VWLB_Repository::find('media_assets',\$asset_id)" "$P/includes/class-vwlb-media.php" r103-asset-read
need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-media.php" r103-read-failure
need "vwlb_processing_asset_read_failed" "$P/includes/class-vwlb-media.php" r103-operational-signal
need "if(!\$asset)return 0" "$P/includes/class-vwlb-media.php" r103-missing-asset
need "!VWLB_Providers::get(\$provider)" "$P/includes/class-vwlb-media.php" r103-provider-exists

# R104 — public object routes and playlist membership accept opaque File 10 identifiers only.
need "class-vwlb-r104-public-id-boundary.php" "$P/video-wall-and-live-broadcasting.php" r104-autoload
need "VWLB_R104_Public_ID_Boundary::register" "$P/video-wall-and-live-broadcasting.php" r104-register
need "public static function opaque_path" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-path-guard
need "foreach(array('id','scene','target') as \$key)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-route-fields
need "ctype_digit(\$value)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-reject-numeric-path
need "array_key_exists('video_ids',\$data)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-reject-internal
need "video_public_ids" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-public-contract
need "is_numeric(\$public_id)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-no-numeric-alias
need "vwlb_playlist_video_read_failed" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-db-failclosed

# R105-R107 — clean source reviews: live external-effect retry guards, webhook integrity and privacy storage/proof remain retained.

# R108 — after-callback privacy revalidation must fail closed; it may never preserve public/unlisted delivery on an unreadable second read.
need "vwlb_caption_cache_state_unreadable" "$P/includes/class-vwlb-r78-public-delivery-guard.php" r108-caption-db-failclosed
need "if(!\$caption)return VWLB_Helpers::error('vwlb_not_found'" "$P/includes/class-vwlb-r78-public-delivery-guard.php" r108-caption-race
need "vwlb_unlisted_state_unreadable" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r108-unlisted-db-failclosed
need "if(!\$row)return VWLB_Helpers::error('vwlb_not_found'" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r108-unlisted-race
need "catch(Throwable \$e)" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r108-grant-exception

printf '%s\n' 'File 10 R101-R120 sequential contracts PASS'
