# File 10 — R113 Provider Output, URL and Secret Boundary Review

Baseline: `0a613611870f1099421eaf5edde3a277983daf42`

## Review scope
The round was completed before correction. Reviewed built-in/local/custom provider adapters, provider registration/failover, `remote_url`/`safe_url`, R75 exception wrapping, R46 stream-credential durability, webhook verification and provider-return contracts for source normalization, live creation, ingest issuance, playback, processing and reconciliation.

## Frozen findings
R113 is defect-bearing.

1. Provider/filter extension points were exception-contained by R75 but their successful return values were still trusted structurally. A plugin provider could return scalars or malformed arrays where downstream File 10 code assumes typed contracts.
2. Ingest URL output was persisted and disclosed using generic `esc_url_raw()` rather than a strict post-provider HTTPS/remote boundary. A provider/filter could therefore return an insecure or otherwise unsafe ingest endpoint after earlier validation layers.
3. Provider-returned stream secrets were not bounded for scalar type, control characters or length before one-time disclosure/hash persistence.
4. Playback and processor derivative URLs from provider/filter output were not revalidated at the final provider boundary; built-in YouTube/Vimeo playback also required final host pinning after extension points.
5. An invalid ingest result may already represent a provider-side effect, so rejecting it without compensation/reconciliation would leave an uncertain external credential.

## Correction batch
After freeze, `VWLB_R113_Validated_Provider` and `VWLB_R113_Provider_Output_Guard` were added after R75 wrapping. They enforce typed provider results, strict HTTPS remote URLs, built-in player-host pinning, derivative URL validation and bounded stream-secret syntax. Unsafe ingest results request provider revocation and return reconciliation-required if revocation cannot be confirmed. Regression contracts were added.

## Gate
Do not begin R114 until this exact correction HEAD passes the complete File 10 Release QA matrix and package/source parity checks.
