#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CURRENT_VERSION='1.2.19-rc1'
run_rebased_124(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased.XXXXXX.sh")"
  python3 - "$src" "$tmp" "$CURRENT_VERSION" <<'PY'
import pathlib, sys
src, dst, version = sys.argv[1:]
text = pathlib.Path(src).read_text().replace('1.2.4-rc1', version)
text = text.replace('need video-wall-and-live-broadcasting-'+version+'.zip "$ROOT/tools/build-package.sh" r19-build-artifact', 'need \'video-wall-and-live-broadcasting-${VERSION}.zip\' "$ROOT/tools/build-package.sh" r19-build-artifact')
rebased=[]
for line in text.splitlines():
    if line.rstrip().endswith('r08-fresh-live'):
        line='need "reconcile_provider_observation" "$P/includes/class-vwlb-live.php" r08-fresh-live'
    elif line.rstrip().endswith('r08-live-cas'):
        line='need "update_versioned(\'live_events\'" "$P/includes/class-vwlb-live.php" r08-live-cas'
    rebased.append(line)
text='\n'.join(rebased)+'\n'
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
run_rebased_127(){ local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased127.XXXXXX.sh")"; sed "s/1\\.2\\.7-rc1/${CURRENT_VERSION}/g" "$src" > "$tmp"; bash "$tmp"; rm -f "$tmp"; }
run_rebased_128(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased128.XXXXXX.sh")"
  python3 - "$src" "$tmp" "$CURRENT_VERSION" <<'PY'
import pathlib, sys
src, dst, version = sys.argv[1:]
text = pathlib.Path(src).read_text()
text = text.replace('1.2.8-rc1', version)
text = text.replace('need "video-wall-and-live-broadcasting-'+version+'.zip" "$ROOT/tools/build-package.sh" r20-build-version', 'need \'video-wall-and-live-broadcasting-${VERSION}.zip\' "$ROOT/tools/build-package.sh" r20-build-version')
text = text.replace('need "video-wall-and-live-broadcasting-'+version+'.zip" "$ROOT/tools/build-package.sh" r59-builder', 'need \'video-wall-and-live-broadcasting-${VERSION}.zip\' "$ROOT/tools/build-package.sh" r59-builder')
text = text.replace('need "Version: '+version+'" "$ROOT/tests/static-contracts.sh" r20-static-version', 'need \'grep -F "Version: $VERSION"\' "$ROOT/tests/static-contracts.sh" r20-static-version')
text = text.replace('need "Version: '+version+'" "$ROOT/tests/plan-completion-contracts.sh" r20-plan-version', 'need \'need "Version: $VERSION"\' "$ROOT/tests/plan-completion-contracts.sh" r20-plan-version')
text = text.replace("need '\"version\": \""+version+"\"' \"$ROOT/SBOM-1.2.8-rc1.json\" r59-sbom", "need '\"version\": \"1.2.8-rc1\"' \"$ROOT/SBOM-1.2.8-rc1.json\" r59-sbom")
text = text.replace('round `R59` completed', 'Current review boundary: R101–R120 sequential cycle')
text = text.replace('R60 remains pending', 'only then begin the next round.')
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
run_rebased_r21_r40(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased-r21-r40.XXXXXX.sh")"
  python3 - "$src" "$tmp" "$CURRENT_VERSION" <<'PY'
import pathlib, sys
src, dst, version = sys.argv[1:]
text = pathlib.Path(src).read_text()
text = text.replace('1.2.7-rc1', version)
text = text.replace('need "video-wall-and-live-broadcasting-'+version+'.zip" "$ROOT/tools/build-package.sh" r39-builder-default', 'need \'video-wall-and-live-broadcasting-${VERSION}.zip\' "$ROOT/tools/build-package.sh" r39-builder-default')
text = text.replace('need \'"version": "'+version+'"\' "$ROOT/SBOM-1.2.7-rc1.json" r39-sbom', 'need \'"version": "1.2.7-rc1"\' "$ROOT/SBOM-1.2.7-rc1.json" r39-sbom')
text = text.replace('Cycle baseline exact HEAD: `83558aea2e581e6f7b76084e21695989254704b7`', 'Cycle baseline exact HEAD: `9a2c317d664b3c0d56797afbf1934f6c55479aaa`')
text = text.replace('Review boundary: final sequential cycle round `R40`', 'Current review boundary: R101–R120 sequential cycle')
text = text.replace('R40 found additional package/release-hygiene defects', 'R106 correction:')
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
run_rebased_legacy40(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased40.XXXXXX.sh")"
  python3 - "$src" "$tmp" "$CURRENT_VERSION" <<'PY'
import pathlib, sys
src, dst, version = sys.argv[1:]
text = pathlib.Path(src).read_text()
text = text.replace('1.2.4-rc1', version)
text = text.replace('[A-Za-z0-9_-]+', '[a-z][a-z0-9]*_[a-z0-9]+')
text = text.replace('if(!is_wp_error(\\$published))', 'if(is_wp_error(\\$published))return \\$published')
text = text.replace('"$P/includes/class-vwlb-live.php" r21-credential-write', '"$P/includes/class-vwlb-r46-stream-credential-durability.php" r21-credential-write')
text = text.replace('need "wp_safe_remote_post" "$P/includes/class-vwlb-providers.php" r29-safe-remote-post', 'need "VWLB_Helpers::remote_url" "$P/includes/class-vwlb-providers.php" r29-safe-remote-url')
text = text.replace('need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-rest.php" r30-rest-read-failure', 'need "vwlb_repository_read_failed" "$P/includes/class-vwlb-r65-repository-read-guard.php" r30-rest-read-failure')
text = text.replace('need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-frontend.php" r30-frontend-read-failure', 'need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-plugin.php" r30-frontend-read-failure')
text = text.replace('need "vwlb_public_read_unavailable" "$P/includes/class-vwlb-rest.php" r30-public-error', 'need "vwlb_repository_read_failed" "$P/includes/class-vwlb-r65-repository-read-guard.php" r30-public-error')
text = text.replace('need "vwlb_webhook_integrity_failed" "$P/includes/class-vwlb-r31-webhook-integrity.php" r31-fail-closed', 'need "vwlb_webhook_persist_failed" "$P/includes/class-vwlb-r31-webhook-integrity.php" r31-fail-closed')
text = text.replace('need "safe_proof=array_intersect_key" "$P/includes/class-vwlb-live.php" r22-proof-redaction', 'need "safe=array_intersect_key(\\$observation" "$P/includes/class-vwlb-live.php" r22-proof-redaction')
text = text.replace('need "array_intersect_key(\\$state" "$P/includes/class-vwlb-jobs.php" r25-provider-redaction', 'need "safe=array_intersect_key(\\$observation" "$P/includes/class-vwlb-live.php" r25-provider-redaction')
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
run_rebased_opaque_ids(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased-opaque.XXXXXX.sh")"
  python3 - "$src" "$tmp" <<'PY'
import pathlib, sys
src, dst = sys.argv[1:]
text = pathlib.Path(src).read_text()
text = text.replace('[A-Za-z0-9_-]+', '[a-z][a-z0-9]*_[a-z0-9]+')
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
find "$ROOT/video-wall-and-live-broadcasting" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null
node --check "$ROOT/video-wall-and-live-broadcasting/assets/js/vwlb.js"
node --check "$ROOT/video-wall-and-live-broadcasting/assets/js/vwlb-future.js"
php "$ROOT/tests/unit-state-machine.php"
bash "$ROOT/tests/static-contracts.sh"
run_rebased_legacy40 "$ROOT/tests/fresh-40-review-contracts.sh"
run_rebased_124 "$ROOT/tests/fresh-40-review-adversarial.sh"
run_rebased_124 "$ROOT/tests/fresh-20-review-contracts.sh"
run_rebased_128 "$ROOT/tests/fresh-20-review-2-contracts.sh"
run_rebased_opaque_ids "$ROOT/tests/third-fresh-20-review-contracts.sh"
run_rebased_127 "$ROOT/tests/fourth-fresh-20-review-contracts.sh"
bash "$ROOT/tests/file10-sequential-20-contracts.sh"
bash "$ROOT/tests/file10-sequential-late-contracts.sh"
run_rebased_r21_r40 "$ROOT/tests/file10-r21-r40-contracts.sh"
bash "$ROOT/tests/file10-r41-r60-contracts.sh"
run_rebased_128 "$ROOT/tests/file10-r51-r60-contracts.sh"
bash "$ROOT/tests/file10-r60-contracts.sh"
bash "$ROOT/tests/file10-r61-r80-contracts.sh"
bash "$ROOT/tests/file10-r81-r100-contracts.sh"
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-a.zip >/dev/null
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-b.zip >/dev/null
cmp /tmp/vwlb-build-a.zip /tmp/vwlb-build-b.zip
unzip -t /tmp/vwlb-build-a.zip >/dev/null
[[ "$(unzip -Z1 /tmp/vwlb-build-a.zip | head -n1)" == video-wall-and-live-broadcasting/* ]] || { echo 'bad top folder' >&2; exit 1; }
if command -v grep >/dev/null; then ! grep -R -E "(AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{30,}|BEGIN (RSA|OPENSSH) PRIVATE KEY)" "$ROOT/video-wall-and-live-broadcasting" >/dev/null; fi
echo "all File 10 automated checks PASS"
