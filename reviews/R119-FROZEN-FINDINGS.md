# R119 — Frozen Findings

## Sequence integrity

R119 was completed as a read-only review of the frozen repository baseline `7e85c8498ab836cac41a58b91ac4e6e6a67c8a51`. No product code was patched during the review phase. This ledger freezes all proven R119 findings before correction begins.

## Scope

Residual authoritative database-read and projection boundaries in the File 10 podcast/live/extensions surfaces, with emphasis on fail-closed behavior under database read failure.

## Frozen findings

### R119-F1 — Podcast series public-ID resolver can collapse DB failure into 404 absence

`VWLB_Extended_REST::series_id()` performs a direct `$wpdb->get_var()` and casts the result to integer. `podcast_episode_create()` treats zero as `Podcast series not found`. A database read failure can therefore be misclassified as verified absence instead of an operational 503.

**Proven defect.** Required correction: use the standardized fail-closed `VWLB_DB::read_var()` boundary and propagate `WP_Error` before applying absence semantics.

### R119-F2 — Live waiting-room and recording-consent authoritative lock/count reads are not fail-closed

`VWLB_Extensions::join_waiting_room()` performs direct `FOR UPDATE` reads for the live event and attendee plus a direct capacity `COUNT(*)`; `set_recording_consent()` performs a direct attendee `FOR UPDATE` read. These reads participate in mutations. Database read failure may be treated as not-found/no-existing-row/zero-capacity-count instead of aborting safely.

**Proven defect.** Required correction: route lock rows and counts through `VWLB_DB::read_row()` / `read_var()`, propagating operational errors before mutation decisions.

### R119-F3 — Post-insert public-ID projection reads can silently return empty identifiers

Chapter creation, new waiting-room attendee projection, live-question creation, and live-resource creation re-read newly inserted public identifiers using direct `$wpdb->get_var()`. A read failure after a successful write can return an empty identifier while the mutation is reported as successful.

**Proven defect.** Required correction: use fail-closed scalar reads and return the operational error when the persisted identifier cannot be verified.

### R119-F4 — Recording-finalization consent count can fail open

`VWLB_Extensions::recording_consent_gate()` directly reads the number of attendees whose consent is missing/stale and casts the scalar to integer. A database read failure can become zero, allowing recording finalization when required consent state was not actually verified.

**Proven defect; high integrity/privacy significance.** Required correction: use `VWLB_DB::read_var()` and fail closed on error before evaluating the count.

### R119-F5 — Premiere/download authoritative reads can collapse operational failure into ordinary state

Premiere replay mapping and secure download-token resolution use direct row reads. A DB read failure can be interpreted as no existing premiere or an invalid/expired token. In the premiere path this may permit an attempted duplicate mapping; in the download path it hides operational failure behind token state.

**Proven defect.** Required correction: use fail-closed row reads and propagate `WP_Error` before normal absence/token semantics.

### R119-F6 — Operational status counters are not read-integrity protected

`VWLB_Extensions::status()` directly reads dead-job and active-upload counts and casts them to integers. Database read failure can be reported as healthy-looking zero counters.

**Proven defect.** Required correction: use fail-closed scalar reads and expose operational uncertainty rather than fabricated zero state.

## Existing safe primitive

`VWLB_DB` already provides `read_row()`, `read_results()`, and `read_var()`. Each clears/checks `$wpdb->last_error`, emits the operational-failure signal, and returns `vwlb_database_read_failed` on failure. R119 correction must reuse these primitives rather than introducing an independent read-error mechanism.

## Freeze boundary

The finding set above is frozen. Correction may now begin and must address all proven R119 findings together, followed by dedicated R119 regression contracts, the full historical/current regression suite, and exact-head Release QA before R120 is allowed to begin.

## Live-First Exact-Deployed-State Rule

The exact deployed source was not available for this review. Exact deployed code remains unverified. These findings are repository-based and therefore provisional with respect to the live deployment; GitHub state is not treated as Live-Deployed or Operational state.
