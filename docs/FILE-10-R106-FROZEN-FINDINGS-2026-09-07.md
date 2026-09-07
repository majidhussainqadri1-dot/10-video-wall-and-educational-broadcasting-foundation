# File 10 R106 — Upload, Scan and Transcode Safety Review — Frozen Findings

Review baseline: exact R105 evidence-green HEAD `4c8d9b1a7ccc05f996b6bdb8e49a8939b431d0c1`, File 10 Release QA run `34083167364` green on PHP 8.3 and 8.4.

## Review discipline

R106 was completed read-only before this findings freeze. No R106 source correction was started during the review. The review traversed the canonical media initiation/completion path, resumable private ingest, REST upload registration and request flow, chunk durability guard, completion guard, private-storage integrity guard, asynchronous verification/processing worker, malware/external-validation contracts, local/custom provider processing, provider exception containment, expired-upload cleanup, publication/reels scan gates and the existing upload regression contracts.

## Confirmed findings

### R106-F01 — Expired-upload cleanup can race a chunk writer

`VWLB_R76_Cleanup_Durability::cleanup_uploads()` resolves an expired `.part` file and calls `unlink()` without first obtaining the upload file's exclusive lock. The canonical chunk writer deliberately holds `LOCK_EX` while writing and rolling back. An already-authorized request can therefore be inside a valid write when the session crosses its expiry boundary and cleanup unlinks the same pathname. On Unix-like storage the writer may continue against the unlinked inode while cleanup marks the database session expired; this can destroy resumability/completion truth and strand bytes/state. R97 privacy erasure already demonstrates the correct storage-first exclusive-lock pattern, but R76 expiry cleanup does not use it.

### R106-F02 — Scan/validation callback exceptions can escape the processing worker

`VWLB_Extensions::technical_validation()` invokes `vwlb_malware_scan_result` and `vwlb_external_media_validation` directly. `VWLB_Media::verify_magic()` delegates to that filter chain, and `VWLB_Jobs::run_job()` calls `verify_magic()` without a Throwable boundary around technical validation. A scanner/integration callback exception can therefore abort the cron worker after the processing job has already been claimed as `running`, instead of becoming a typed retryable validation failure. R75 contains provider-method exceptions, but this scan/validation filter path is outside that wrapper.

The same validation function also assumes `hash_file('sha256', $path)` returns a string when a stored checksum exists. A filesystem/read failure can make `hash_file()` return `false`; passing that into `hash_equals()` is not a controlled File 10 validation failure on PHP 8.x.

### R106-F03 — A late historical REST override reintroduces a permissive resumable-completion route pattern

The canonical extended REST route requires the current prefixed opaque public-ID syntax, but `VWLB_Sequential_Review_Hardening::register_rest_overrides()` later re-registers `/media/resumable/(?P<id>[A-Za-z0-9_-]+)/complete`. This late override weakens the route-level boundary established in R102. Numeric IDs are separately blocked, but arbitrary non-prefixed identifiers still reach the registered completion surface. The route must use the same canonical opaque-ID grammar as the rest of File 10.

## Reviewed controls that were not findings

- Private resumable initiation uses bounded declared size, random one-time token, password-hashed token storage, random `.part` filename, private storage and explicit expiry.
- R10 re-verifies private-storage root/protection files and rejects symlinks before every resumable mutation.
- R45 performs exact-offset, chunk-size, overflow and optional chunk-SHA checks, locks the file, loops partial writes safely and compensates DB CAS failures.
- R70 performs DB-readable completion preflight, realpath containment, file symlink rejection, exclusive completion lock, physical-size proof and whole-file SHA proof.
- Private-file technical validation is fail closed on absent scanner verdict: only `true` or explicit `clean`/`passed` scanner status succeeds.
- Non-private/external media requires an explicit `vwlb_external_media_validation` success/provider-verification result; absence of a validator fails closed.
- Publication and File 11 media contracts require a ready asset with `scan_status=passed`.
- The local private pipeline does not fabricate derivatives when no real scanner/transcoder provider is configured.

## Correction gate

Only after this freeze may R106 correction begin. All confirmed R106 findings must be corrected together and the complete exact-head release QA must pass on PHP 8.3 and 8.4, including deterministic package, checksum/archive, source/package parity and artifact publication, before R107 begins.
