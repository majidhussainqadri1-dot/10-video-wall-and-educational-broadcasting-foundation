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
# R25 — legacy/direct completion must atomically persist both uploaded state and its required processing job.
need "R25: state transition and processing-queue persistence are one transaction" "$P/includes/class-vwlb-media.php" r25-atomic-intent
need "VWLB_DB::transaction(function()use(\$asset" "$P/includes/class-vwlb-media.php" r25-transaction
need "Media completion was rolled back because processing could not be queued" "$P/includes/class-vwlb-media.php" r25-queue-rollback
# R27 — live credential issuance must fail closed if a stored/configured provider is no longer registered.
need "vwlb_provider_missing" "$P/includes/class-vwlb-live.php" r27-provider-missing
need "The configured live provider is unavailable." "$P/includes/class-vwlb-live.php" r27-provider-message
need "if(!\$provider)return VWLB_Helpers::error('vwlb_provider_missing'" "$P/includes/class-vwlb-live.php" r27-null-guard
# R28 — restoration blocker search must cover all historical cases, not only the newest 100.
need "moderation_blocker_exists" "$P/includes/class-vwlb-r11-restore-guard.php" r28-moderation-pagination
need "takedown_blocker_exists" "$P/includes/class-vwlb-r11-restore-guard.php" r28-takedown-pagination
need "id<%d" "$P/includes/class-vwlb-r11-restore-guard.php" r28-cursor
need "count(\$rows) < 100" "$P/includes/class-vwlb-r11-restore-guard.php" r28-bounded-pages
# R29 — privacy erasure must not report completion when encrypted retry evidence cannot be inspected/decrypted.
need "vwlb_retry_erasure_unverifiable" "$P/includes/class-vwlb-r20-retry-privacy.php" r29-unverifiable-signal
need "privacy erasure was stopped and remains incomplete" "$P/includes/class-vwlb-r20-retry-privacy.php" r29-fail-closed-message
need "'done'=>false" "$P/includes/class-vwlb-r20-retry-privacy.php" r29-incomplete
printf '%s\n' 'File 10 R21-R40 sequential contracts PASS'
