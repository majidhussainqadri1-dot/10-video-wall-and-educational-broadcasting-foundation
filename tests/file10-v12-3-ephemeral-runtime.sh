#!/usr/bin/env bash
# Fully disposable, isolated WordPress/MariaDB test; not Staging/Live.
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RUN_TAG="f10ci-$(date +%s)-$$"
NET="f10-ci-net-$RUN_TAG"
DB="f10-ci-db-$RUN_TAG"
WP="f10-ci-wp-$RUN_TAG"
DB_PWD="$(openssl rand -hex 28)"
ADMIN_PWD="$(openssl rand -hex 28)"
PLUGIN="$ROOT/video-wall-and-live-broadcasting"
ENTRY="$PLUGIN/video-wall-and-live-broadcasting.php"
cleanup() {
  docker rm -f "$WP" "$DB" >/dev/null 2>&1 || true
  docker network rm "$NET" >/dev/null 2>&1 || true
  unset DB_PWD ADMIN_PWD
}
trap cleanup EXIT
test -r "$ENTRY"
docker network create "$NET" >/dev/null

docker run -d --name "$DB" --network "$NET" \
  -e MARIADB_ROOT_PASSWORD="$DB_PWD" \
  -e MARIADB_DATABASE=wordpress \
  -e MARIADB_USER=wordpress \
  -e MARIADB_PASSWORD="$DB_PWD" \
  mariadb:11.8 >/dev/null
ready=false
for attempt in $(seq 1 75);do
  if docker exec "$DB" mariadb-admin ping --silent -u root -p"$DB_PWD" >/dev/null 2>&1;then
    ready=true
    break
  fi
  sleep 2
done
if [[ "$ready" != true ]];then
  echo 'F10-EPHEMERAL FAIL: isolated MariaDB did not become ready' >&2
  exit 1
fi

# Exact modern PHP code is bind-mounted read-only. WordPress gets only a fresh
# ephemeral DB, fake local-only HTTP hostname, and disposable credentials.
docker run -d --name "$WP" --network "$NET" \
  -e WORDPRESS_DB_HOST="$DB:3306" \
  -e WORDPRESS_DB_USER=wordpress \
  -e WORDPRESS_DB_PASSWORD="$DB_PWD" \
  -e WORDPRESS_DB_NAME=wordpress \
  -v "$PLUGIN:/var/www/html/wp-content/plugins/video-wall-and-live-broadcasting:ro" \
  wordpress:7.1.4-php8.3-apache >/dev/null
ready=false
for attempt in $(seq 1 75);do
  if docker exec "$WP" sh -c 'test -s /var/www/html/wp-config.php && test -s /var/www/html/wp-load.php' >/dev/null 2>&1;then
    ready=true
    break
  fi
  sleep 2
done
if [[ "$ready" != true ]];then
  echo 'F10-EPHEMERAL FAIL: isolated WordPress bootstrap did not become ready' >&2
  exit 1
fi
docker cp "$ROOT/tests/file10-v12-3-ephemeral-wordpress.php" "$WP:/tmp/file10-v123-ephemeral.php"

# Verify exact GitHub source mounting before touching the isolated database.
local_sha="$(sha256sum "$ENTRY" | awk '{print $1}')"
image_sha="$(docker exec "$WP" sha256sum \
    /var/www/html/wp-content/plugins/video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php | awk '{print $1}')"
if [[ "$local_sha" != "$image_sha" ]];then
  echo 'F10-EPHEMERAL FAIL: source/container plugin entry SHA256 mismatch' >&2
  exit 1
fi
echo "F10-EPHEMERAL SOURCE PARITY PASS: file entry SHA256 $local_sha"

docker exec -e QA_F10_ADMIN_PASSWORD="$ADMIN_PWD" "$WP" \
  php /tmp/file10-v123-ephemeral.php install
docker exec "$WP" php /tmp/file10-v123-ephemeral.php activate
docker exec "$WP" php /tmp/file10-v123-ephemeral.php verify
echo 'F10-EPHEMERAL QA COMPLETE: real isolated WP/MySQL integration; NEVER a deployed/Staging/Live claim'
