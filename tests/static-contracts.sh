#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

find video-wall -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null
node --check video-wall/assets/js/video-wall.js
php tests/test-helpers.php

grep -Fq 'Version: 0.2.0' video-wall/video-wall.php
grep -Fq "define( 'SVW_VERSION', '0.2.0' );" video-wall/video-wall.php
grep -Fq "'create_posts'           => 'manage_video_wall'" video-wall/includes/class-svw-activator.php
grep -Fq 'svw_history' video-wall/includes/class-svw-activator.php
grep -Fq 'svw_audit' video-wall/includes/class-svw-activator.php
grep -Fq 'DONOTCACHEPAGE' video-wall/includes/class-svw-frontend.php
grep -Fq 'no-store' video-wall/includes/class-svw-frontend.php
grep -Fq 'payload.success' video-wall/assets/js/video-wall.js
grep -Fq "return 'Video Contributor';" video-wall/includes/class-svw-helpers.php
grep -Fq 'posts_per_page' video-wall/includes/class-svw-frontend.php

if grep -R "posts_per_page[^\n]*60" video-wall/includes; then
  echo 'Unbounded 60-video listing remains.' >&2
  exit 1
fi
if grep -R "youtube\\.com|youtu\\.be" video-wall/includes/class-svw-frontend.php; then
  echo 'Frontend still performs weak substring URL validation.' >&2
  exit 1
fi
if grep -R "Verified Founder':'Verified Doctor" video-wall; then
  echo 'False blanket verification label remains.' >&2
  exit 1
fi

echo 'Static correction contracts passed.'
