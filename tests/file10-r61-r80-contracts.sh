#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r61-r80: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R61 — activation role capabilities must roll back for every existing role, and active review-cycle CI must not republish an old immutable RC artifact.
need "class-vwlb-r61-activation-role-guard.php" "$P/video-wall-and-live-broadcasting.php" r61-autoload
need "VWLB_R61_Activation_Role_Guard', 'activation_begin" "$P/video-wall-and-live-broadcasting.php" r61-begin
need "VWLB_R61_Activation_Role_Guard', 'activation_commit" "$P/video-wall-and-live-broadcasting.php" r61-commit
need "wp_roles()" "$P/includes/class-vwlb-r61-activation-role-guard.php" r61-all-roles
need "vwlb_activation_role_caps_compensated" "$P/includes/class-vwlb-r61-activation-role-guard.php" r61-observable
need "vwlb_r61_activation_role_snapshot" "$P/uninstall.php" r61-purge
need "VWLB_PUBLISH_REVIEW_ARTIFACT: '0'" "$ROOT/.github/workflows/file10-release.yml" r61-no-intermediate-artifact
need "env.VWLB_PUBLISH_REVIEW_ARTIFACT == '1'" "$ROOT/.github/workflows/file10-release.yml" r61-explicit-final-publish

printf '%s\n' 'File 10 R61-R80 sequential contracts PASS'
