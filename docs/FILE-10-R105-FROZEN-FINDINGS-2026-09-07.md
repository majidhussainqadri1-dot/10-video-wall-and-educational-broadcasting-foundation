# File 10 — R105 Frozen Findings — Privacy / Consent / Export / Erasure / Retention — 2026-09-07

## Review discipline
R105 was performed read-only on exact R104 green baseline `79651313324d4a85e5b0b5583c7319a7559d6c16` (`1.2.15-rc1`). No R105 source correction began before this ledger was frozen.

Reviewed: governing File 10 privacy release gate; WordPress exporter/eraser composition; core privacy policy/export/erase; R50 completion proof; R97 private-storage erasure propagation; R20 encrypted retry evidence and its dedicated eraser; R30 encrypted audit/outbox fallbacks and reconciliation; consent-version finalization guard; Future consent-link expiry/restriction; moderation/takedown identity retention; canonical audit/outbox schema and worker retention; private-upload cleanup; uninstall/purge; boot/registration order; representative audit/outbox payloads including guest identity and history-clear actions.

## Frozen defect ledger

### R105-01 — Personal-data exporter does not cover the subject-attributable stores that File 10 itself recognizes and erases
`VWLB_Privacy::export()` exposes only watch history, interactions, live attendee/recording-consent rows, live questions, upload sessions, download grants, broadcast guest rows and poll responses. The same module's erasure/completion logic recognizes many additional user-attributable stores: moderation reporter/reviewer, takedown claimant/reviewer, caption creator/reviewer, production source/scene owner, simulcast creator, media-track creator/reviewer, annotation creator/reviewer, poll creator, consent-link creator, watermark-policy updater, stream-credential creator, creator metrics and audit actor attribution.

This is an export/erasure asymmetry against the File 10 privacy release gate. Sensitive secret-bearing fields must remain excluded, but a privacy-safe export projection of eligible personal attribution is required.

### R105-02 — Canonical retained audit/outbox evidence can preserve direct subject identifiers after the eraser reports completion
The main eraser anonymizes only `audit.actor_id`; its completion proof likewise checks only that audit column. Canonical audit rows can also identify a subject through `object_type/object_id` and `meta_json`. Example: watch-history clearing audits `object_type=history` with `object_id=get_current_user_id()`. Guest-delegation audit metadata and outbox payloads also include `guest_user_id`. The eraser does not inspect/anonymize subject identifiers inside audit object/meta fields or outbox payloads, and completion proof does not prove their removal/anonymization.

Retained integrity evidence may remain where policy requires it, but raw subject identity cannot silently survive a completed erasure unless a scoped retention/legal-hold rule authorizes that identity retention.

### R105-03 — Encrypted R30 audit/outbox fallback evidence is outside privacy erasure and can rehydrate erased identifiers later
When canonical audit/outbox insertion fails, R30 stores the complete row encrypted in `wp_options` and later reconciles it into the canonical table. R20 has a dedicated privacy eraser for encrypted inbound-retry evidence, but R30 has no equivalent erasure/anonymization path for audit/outbox fallback records. The R50/R97 completion proof checks canonical tables/storage, not these encrypted fallbacks.

Therefore a privacy request can complete while a subject-bearing encrypted fallback survives; a later reconciliation can repopulate canonical audit/outbox evidence with the pre-erasure identifier. This violates dependent-storage erasure/anonymization propagation.

### R105-04 — The operative File 10 privacy erasure path has no scoped legal-hold enforcement contract despite the governing release gate
The governing plan requires each relevant data field to have retention/export/erasure/legal-hold behavior and says retained safety/rights/audit evidence must be governed by scoped retention. The current privacy policy text mentions scoped lawful hold, but the actual composed eraser path (R97 → R50 → core privacy eraser, plus R20 retry eraser) contains no native File 10 legal-hold decision/enforcement step. The canonical audit/outbox schema has no hold marker, and reviewed integration/registration paths expose no privacy-hold contract.

The correction must provide an explicit owner-enforced, scope-bounded legal-hold decision contract: default no hold, no raw secret disclosure, auditable basis/scope/expiry, and selective retention rather than a blanket deletion bypass. File 24 or another assurance source may supply policy evidence, but File 10 must enforce the resulting decision on its own data.

## Non-findings / already-correct controls retained
- R97 correctly deletes all selected resumable `.part` files before DB erasure with path containment, symlink rejection and nonblocking file locking.
- R50 independently proves that its listed canonical user-ID columns and idempotency scopes no longer contain the subject before accepting completion.
- R20 retry evidence is encrypted, TTL-bound, cursor-cleaned and has its own privacy eraser that inspects decrypted payloads for known user-ID fields.
- Recording consent requires an explicit current consent-text version and recording finalization fails closed when consent state is unreadable.
- Future consent expiry/withdrawal restricts linked media and triggers derivative-cache purge.
- Destructive uninstall remains dual-confirmed and is not treated as ordinary privacy erasure.

## Required correction gate before R106
Correct every proven R105 finding together, add regression contracts for exporter coverage, canonical evidence anonymization/completion, encrypted fallback propagation and legal-hold enforcement, assign a fresh immutable runtime candidate, then require exact-head PHP 8.3/8.4 full suite, R101–R120 gate, deterministic package/checksum/archive, source/package parity and artifact publication. R106 must not begin before that exact-head QA is green.
