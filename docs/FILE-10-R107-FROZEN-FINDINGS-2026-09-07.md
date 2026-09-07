# File 10 — R107 Frozen Findings — 2026-09-07

## Governing sequence

R107 was completed as a read-only review against the exact R106-green repository state before any R107 source correction. Findings were frozen only after the review traversal completed. No R108 review may begin until every proven R107 finding is corrected and the resulting exact HEAD passes the complete release regression/package/parity gate on PHP 8.3 and 8.4.

## Review baseline

- Branch: `fix/file10-r101-r120-sequential-2026-09-06`
- R106 exact green HEAD / R107 read-only baseline: `a1507752f5dad87e7b22e7a4aa4cf714de4aefd3`
- R106 Release QA run: `34085674761`
- R106 artifact: `file10-video-wall-live-1.2.17-rc1`, artifact ID `10005125569`, artifact digest `sha256:a240fb08de3eed272240978a8b6ae7ff48c76d2c3285f78caeb84f54f0b5529d`
- R107 lens: provider adapters, webhooks, credential/secrets boundaries, runtime capability/readiness, plus a package-wide contradiction/regression pass.

## Frozen findings

### R107-F01 — late REST override chain regresses the opaque-ID boundary and playback enrichment

The final REST registration chain does not preserve the R102 opaque public-ID contract everywhere. `VWLB_Future_Safety` registers later overrides for playback/live/polls/annotations with permissive `[A-Za-z0-9_-]+` route parameters, and `VWLB_Sequential_Review_Hardening` later overrides captions/annotations/kill with the same permissive grammar. Because repository lookup accepts numeric strings as native primary keys, these late overrides reopen native-ID addressing on public routes. In addition, `VWLB_Future_Safety::rest_playback()` again attempts chapter/track enrichment from `$payload['video']['id']`, although the public playback DTO no longer exposes the internal ID; the effective override therefore repeats the stale enrichment bug corrected earlier in the core REST handler.

Required correction: every final route override must use the canonical prefixed opaque grammar, and playback enrichment must re-resolve the already-authorized video internally before loading chapters/tracks.

### R107-F02 — provider compensation callbacks are not fully Throwable-contained

`VWLB_Live::compensate_live_creation()` invokes the external compensation action/filter without a Throwable boundary. A third-party provider compensation callback can therefore abort the command after provider-side creation/local persistence failure, before File 10 can return its typed reconciliation-required result. `VWLB_R46_Stream_Credential_Durability::compensate()` catches the result filter but invokes `vwlb_provider_ingest_compensation_requested` outside that catch, leaving the same escape path before credential revocation confirmation.

Required correction: contain the entire external compensation callback boundary and, on any throwable, emit privacy-safe operational evidence and return/retain reconciliation-required state without falsely releasing retry/idempotency safety.

### R107-F03 — raw-secret denial is key-name incomplete

The Future processor-option guard and annotation metadata guard reject a short exact list (`secret`, `password`, `api_key`, `access_token`, `refresh_token`, `private_key`, `token`, `stream_key`). Common credential names such as `client_secret`, `webhook_secret`, `secret_key`, `signing_key`, `authorization`, `bearer`, `credential`, and suffix variants such as `*_token`, `*_secret`, `*_password` are not covered and can pass the stated “raw credentials forbidden” boundary unless a caller/provider happens to filter them first.

Required correction: use one recursive, reusable secret-key detector with explicit `_ref` exemptions and a materially complete credential-name/suffix policy, then apply it to processor options and annotation metadata before persistence or external dispatch.

### R107-F04 — provider-health read can inherit stale database failure state

`VWLB_Observability::provider_available()` checks `$wpdb->last_error` after its health query but does not clear the field immediately before that query. A prior unrelated database failure in the same request/process can therefore make a successful provider-health read look failed and can drive false provider-unavailable/failover decisions.

Required correction: clear the database error immediately before the provider-health read and only fail closed on the fresh query result.

### R107-F05 — Future capability response does not distinguish implementation presence from runtime/provider readiness

`VWLB_Future_Intelligence::capabilities()` unconditionally returns all 24 implementation capability names, and the public Future capabilities endpoint returns that list without a readiness semantic or provider/runtime state. This contradicts the governing Future-24 rule that feature presence is not proof that the configured provider/runtime supports the feature.

Required correction: keep implementation capability declarations for compatibility, but expose an explicit semantics field and fail-safe runtime-readiness projection (including provider-backed readiness as `ready`, `unavailable`, or `unverified`) so callers cannot interpret the feature list as operational provider proof.

## Review result

R107 is defect-bearing. Five findings are frozen. Correction begins only after this freeze; R108 remains blocked until R107 exact-head release QA is green.
