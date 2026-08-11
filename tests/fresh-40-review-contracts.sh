#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL fresh-40-review: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R02 — serialized base/extension/Future migration and activation parity.
need "MIGRATION_LOCK" "$P/includes/class-vwlb-activator.php" r02-migration-lock
need "VWLB_Future_Intelligence::install_schema" "$P/includes/class-vwlb-activator.php" r02-future-activation-schema
need "VWLB_Activator::reconcile_schema" "$P/includes/class-vwlb-plugin.php" r02-runtime-reconcile
# R03 — publisher/broadcaster cannot self-assert human-reviewed Future states.
need "Human review permission is required to change a generated track review state" "$P/includes/class-vwlb-future-intelligence.php" r03-track-review-guard
need "Timestamp corrections require independent review permission" "$P/includes/class-vwlb-future-intelligence.php" r03-correction-review-guard
# R04 — all File 10 REST mutations are rate-limited and idempotency guarded; signed webhooks retain provider replay dedupe.
need "rest_request_before_callbacks" "$P/includes/class-vwlb-plugin.php" r04-before-hook
need "rest_request_after_callbacks" "$P/includes/class-vwlb-plugin.php" r04-after-hook
need "rest_mutation_before" "$P/includes/class-vwlb-security.php" r04-mutation-guard
need "idempotency_abort" "$P/includes/class-vwlb-security.php" r04-idempotency-abort
need "'webhook'===\$name" "$P/includes/class-vwlb-security.php" r04-webhook-exception
need "Idempotency-Key" "$P/assets/js/vwlb.js" r04-browser-idempotency
# R05 — production sources/scenes preserve ownership, reject raw secrets/cross-event sources, and serialize program switching.
need "contains_raw_secret" "$P/includes/class-vwlb-future-intelligence.php" r05-secret-rejection
need "Every scene source must be an active source of the same live event" "$P/includes/class-vwlb-future-intelligence.php" r05-scene-source-scope
need "find('live_events',\$event['id'],true)" "$P/includes/class-vwlb-future-intelligence.php" r05-program-lock
need "id<>%d" "$P/includes/class-vwlb-future-intelligence.php" r05-single-program
# R06 — F10-FUT-002 guest/co-host delegation is File00-eligible, scoped, expiring, audited, CAS-protected and revocable.
need "File 00 identity and eligibility assertions" "$P/includes/class-vwlb-future-intelligence.php" r06-target-identity
need "Guest invitation changed concurrently" "$P/includes/class-vwlb-future-intelligence.php" r06-accept-cas
need "BroadcastGuestRevoked" "$P/includes/class-vwlb-future-intelligence.php" r06-revoke-event
need "/revoke','POST','guest_revoke'" "$P/includes/class-vwlb-future-rest.php" r06-revoke-route
# R07 — provider-dependent DVR/latency/backup policy is capability-declared, version-safe and truthfully normalized.
need "vwlb_latency_mode_unavailable" "$P/includes/class-vwlb-future-intelligence.php" r07-latency-capability
need "vwlb_dvr_unavailable" "$P/includes/class-vwlb-future-intelligence.php" r07-dvr-capability
need "vwlb_backup_provider_required" "$P/includes/class-vwlb-future-intelligence.php" r07-backup-required
need "submit its current version" "$P/includes/class-vwlb-future-intelligence.php" r07-config-cas
need "min(6*HOUR_IN_SECONDS" "$P/includes/class-vwlb-future-adapters.php" r07-provider-result-bound
# R08 — simulcast secrets are recursively rejected and provider transitions reserve a CAS state before external side effects.
need "self::contains_raw_secret(\$config)" "$P/includes/class-vwlb-future-intelligence.php" r08-nested-secret
need "array('disabled','ready')" "$P/includes/class-vwlb-future-intelligence.php" r08-no-client-active
need "status'=>'transitioning'" "$P/includes/class-vwlb-future-adapters.php" r08-transition-reservation
need "submit its current version" "$P/includes/class-vwlb-future-adapters.php" r08-transition-version
need "reconciliation is required" "$P/includes/class-vwlb-future-adapters.php" r08-provider-local-divergence
# R09 — broadcaster health telemetry is bounded and cannot fabricate cross-event source identity.
need "vwlb_health_source_invalid" "$P/includes/class-vwlb-future-intelligence.php" r09-source-scope
need "is_finite(\$candidate)" "$P/includes/class-vwlb-future-intelligence.php" r09-finite-audio
need "min(1000000000" "$P/includes/class-vwlb-future-intelligence.php" r09-dropped-frame-bound
# R10 — generated accessibility/language tracks have an explicit human-review state machine before publication.
need "vwlb_track_secret_forbidden" "$P/includes/class-vwlb-future-intelligence.php" r10-track-secret
need "transition_track" "$P/includes/class-vwlb-future-intelligence.php" r10-track-transition
need "candidate','reviewed" "$P/includes/class-vwlb-future-intelligence.php" r10-candidate-review
need "Human review is required before publishing generated tracks" "$P/includes/class-vwlb-future-intelligence.php" r10-human-review-contract
need "MediaTrackPublished" "$P/includes/class-vwlb-future-intelligence.php" r10-publish-event
need "/media-tracks/(?P<id>[A-Za-z0-9_-]+)/transition" "$P/includes/class-vwlb-future-rest.php" r10-transition-rest
# R11 — annotation candidates require explicit human review; timestamps/metadata/public DTOs are bounded.
need "transition_annotation" "$P/includes/class-vwlb-future-intelligence.php" r11-transition
need "Annotation end time cannot precede" "$P/includes/class-vwlb-future-intelligence.php" r11-time-order
need "verified video duration" "$P/includes/class-vwlb-future-intelligence.php" r11-duration
need "vwlb_annotation_secret_forbidden" "$P/includes/class-vwlb-future-intelligence.php" r11-secret
need "unset(\$i['metadata_json'])" "$P/includes/class-vwlb-future-intelligence.php" r11-dto
need "/video-annotations/(?P<id>[A-Za-z0-9_-]+)/transition" "$P/includes/class-vwlb-future-rest.php" r11-rest
# R12 — transcript indexing binds tracks to the same video, bounds time to verified duration and limits search input.
need "vwlb_transcript_track_invalid" "$P/includes/class-vwlb-future-intelligence.php" r12-track-scope
need "Transcript segment end cannot precede" "$P/includes/class-vwlb-future-intelligence.php" r12-time-order
need "verified video duration" "$P/includes/class-vwlb-future-intelligence.php" r12-duration
need "vwlb_search_too_long" "$P/includes/class-vwlb-future-intelligence.php" r12-query-bound
# R13 — live polls validate UTC windows, roll back failed options/responses and return a bounded public DTO.
need "Poll closing time must be after its opening time" "$P/includes/class-vwlb-future-intelligence.php" r13-window-order
need "Poll option could not be saved" "$P/includes/class-vwlb-future-intelligence.php" r13-option-atomic
need "outside its active response window" "$P/includes/class-vwlb-future-intelligence.php" r13-window-enforce
need "Previous poll response could not be replaced" "$P/includes/class-vwlb-future-intelligence.php" r13-response-atomic
need "'not_diagnostic'=>true" "$P/includes/class-vwlb-future-intelligence.php" r13-public-dto
# R14 — consent withdrawal/expiry and video restriction are one fail-closed transaction; races cannot leave public media after consent loss.
need "FOR UPDATE" "$P/includes/class-vwlb-future-intelligence.php" r14-consent-lock
need "Consent record changed concurrently" "$P/includes/class-vwlb-future-intelligence.php" r14-consent-cas
need "restrict_video_for_consent(\$locked" "$P/includes/class-vwlb-future-intelligence.php" r14-atomic-restrict
need "if(is_wp_error(\$restricted))return \$restricted" "$P/includes/class-vwlb-future-intelligence.php" r14-fail-closed
need "vwlb_purge_media_derivative_caches" "$P/includes/class-vwlb-future-intelligence.php" r14-cache-purge
# R15 — watermark policies reject credentials, use optimistic concurrency and forensic grants retain privacy-safe trace evidence.
need "vwlb_watermark_secret_forbidden" "$P/includes/class-vwlb-future-intelligence.php" r15-secret
need "Watermark policy changed. Refresh and submit its current version" "$P/includes/class-vwlb-future-intelligence.php" r15-version
need "watermark_grant" "$P/includes/class-vwlb-future-intelligence.php" r15-grant-audit
need "token_hash" "$P/includes/class-vwlb-future-intelligence.php" r15-token-hash
need "not an absolute copying-prevention guarantee" "$P/includes/class-vwlb-future-intelligence.php" r15-honesty
# R16 — external processor/AI adapters receive minimized canonical context and only explicitly approved safe options.
need "safe_processor_context" "$P/includes/class-vwlb-future-adapters.php" r16-safe-context
need "vwlb_media_track_generation_safe_options" "$P/includes/class-vwlb-future-adapters.php" r16-option-allowlist
need "vwlb_processor_secret_forbidden" "$P/includes/class-vwlb-future-adapters.php" r16-secret
need "16384" "$P/includes/class-vwlb-future-adapters.php" r16-size-bound
need "clinical_authority'=>false" "$P/includes/class-vwlb-future-adapters.php" r16-ai-authority
# R17 — recorded video create is atomic, checks DB writes and releases internal idempotency after failure.
need "VWLB_DB::transaction(function()use" "$P/includes/class-vwlb-videos.php" r17-transaction
need "vwlb_asset_link_failed" "$P/includes/class-vwlb-videos.php" r17-asset-atomic
need "idempotency_abort(\$idempotency_key,'create_video')" "$P/includes/class-vwlb-videos.php" r17-idem-abort
need "if(!\$inserted||!(int)\$wpdb->insert_id)" "$P/includes/class-vwlb-videos.php" r17-insert-check
# R18 — generated captions require human review and caption persistence failure is not reported as success.
need "'machine_draft'!==\$source&&\$can_review" "$P/includes/class-vwlb-videos.php" r18-human-review
need "VWLB_Contracts::CAP_REVIEW" "$P/includes/class-vwlb-videos.php" r18-review-cap
need "Caption could not be saved" "$P/includes/class-vwlb-videos.php" r18-db-check
# R19 — channel/playlist creation and playlist replacement never report success after failed DB writes.
need "Channel could not be created" "$P/includes/class-vwlb-videos.php" r19-channel
need "Playlist could not be created" "$P/includes/class-vwlb-videos.php" r19-playlist
need "Existing playlist items could not be replaced" "$P/includes/class-vwlb-videos.php" r19-delete
need "A playlist item could not be saved" "$P/includes/class-vwlb-videos.php" r19-item
# R20 — media completion validates checksums and cannot report a complete upload when processing enqueue fails.
need "vwlb_processing_queue_failed" "$P/includes/class-vwlb-media.php" r20-queue-fail
need "Checksum must be SHA-256" "$P/includes/class-vwlb-media.php" r20-checksum
need "return \$saved?(int)\$wpdb->insert_id:0" "$P/includes/class-vwlb-media.php" r20-enqueue-check
# R21 — live schedule/credential operations fail closed, redact provider state and cleanly release idempotency on provider/DB failure.
need "vwlb_provider_live_orphaned" "$P/includes/class-vwlb-live.php" r21-provider-orphan
need "array_intersect_key((array)\$provider_result" "$P/includes/class-vwlb-live.php" r21-provider-redaction
need "idempotency_abort(\$idempotency_key,'schedule_live')" "$P/includes/class-vwlb-live.php" r21-idem-abort
need "Stream credential could not be recorded" "$P/includes/class-vwlb-live.php" r21-credential-write
# R22 — live transitions store bounded provider proof, emergency end checks credential revocation, and replay linking authorizes both event and video.
need "safe_proof=array_intersect_key" "$P/includes/class-vwlb-live.php" r22-proof-redaction
need "Active stream credentials could not be revoked" "$P/includes/class-vwlb-live.php" r22-kill-revoke
need "scheduled_window_elapsed" "$P/includes/class-vwlb-live.php" r22-reconcile-event
need "CAP_BROADCAST,\$event,'publish_replay'" "$P/includes/class-vwlb-live.php" r22-event-auth
need "CAP_PUBLISH,\$video,'publish_replay'" "$P/includes/class-vwlb-live.php" r22-video-auth
# R23 — moderation decisions are atomic and takedown claimants cannot self-issue restrict/remove/restore decisions.
need "Moderation report could not be saved" "$P/includes/class-vwlb-moderation.php" r23-report-db
need "Takedown report could not be saved" "$P/includes/class-vwlb-moderation.php" r23-takedown-db
need "FOR UPDATE" "$P/includes/class-vwlb-moderation.php" r23-lock
need "vwlb_takedown_decision_forbidden" "$P/includes/class-vwlb-moderation.php" r23-claimant-authority
need "VWLB_DB::transaction" "$P/includes/class-vwlb-moderation.php" r23-atomicity
# R24 — verified webhook DB failures are never acknowledged as harmless duplicates.
need "vwlb_webhook_persist_failed" "$P/includes/class-vwlb-rest.php" r24-db-failure
need "WHERE provider=%s AND event_id=%s" "$P/includes/class-vwlb-rest.php" r24-duplicate-check
need "vwlb_webhook_replay_window" "$P/includes/class-vwlb-rest.php" r24-replay-window
# R25 — outbox/job workers honor CAS locks and reconciliation never emits publication events after a failed state transition.
need "if(1!==\$locked)continue" "$P/includes/class-vwlb-jobs.php" r25-outbox-lock
need "if(!is_wp_error(\$published))" "$P/includes/class-vwlb-jobs.php" r25-scheduled-cas
need "array_intersect_key(\$state" "$P/includes/class-vwlb-jobs.php" r25-provider-redaction
need "'status'=>'running'" "$P/includes/class-vwlb-jobs.php" r25-job-finalize-cas
# R26 — privacy export/erasure covers Future user-linked data and persistence/storage failures keep the eraser retryable.
need "Broadcast guest participation" "$P/includes/class-vwlb-privacy.php" r26-export-guests
need "Live poll responses" "$P/includes/class-vwlb-privacy.php" r26-export-polls
need "live_poll_responses" "$P/includes/class-vwlb-privacy.php" r26-erase-polls
need "media_tracks','reviewed_by" "$P/includes/class-vwlb-privacy.php" r26-review-attribution
need "vwlb_privacy_delete_failed" "$P/includes/class-vwlb-privacy.php" r26-db-fail-closed
need "done'=>false" "$P/includes/class-vwlb-privacy.php" r26-retryable
# R27 — diagnostics inventory includes Future schema/tables and repair actions never report completion after failed DB/schema work.
need "'future_schema'=>get_option(VWLB_Future_Intelligence::OPTION" "$P/includes/class-vwlb-diagnostics.php" r27-future-schema
need "'watermark_policies'" "$P/includes/class-vwlb-diagnostics.php" r27-future-tables
need "VWLB_Activator::reconcile_schema" "$P/includes/class-vwlb-diagnostics.php" r27-serialized-schema
need "vwlb_repair_database_failed" "$P/includes/class-vwlb-diagnostics.php" r27-db-fail
need "A configured provider is required" "$P/includes/class-vwlb-diagnostics.php" r27-provider-scope
printf '%s\n' 'fresh 40-review regression contracts PASS'
