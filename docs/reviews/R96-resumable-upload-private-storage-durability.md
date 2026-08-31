# File 10 R96 — Resumable upload, private storage and cleanup durability review

Review completed in full before any correction decision.

Reviewed: resumable session ownership/token/expiry; declared-size and chunk-size enforcement; optional per-chunk SHA-256; exclusive file locking; partial-write rollback; DB CAS vs file rollback; completion size/checksum proof; private path basename/symlink/containment rules; technical MIME/magic and malware-scan gates; expired upload cleanup and cursor fairness; private-storage protection files.

Frozen finding: no new unresolved defect was proven. Chunk writes are serialized with `flock`, are bounded by declared expected bytes and session chunk size, and roll the file back if the DB CAS cannot advance. Completion reopens and locks the file, compares durable expected size, computes SHA-256 and fails closed on DB-state uncertainty. Private filenames are basename/sanitized `.part` names under the dedicated File 10 private root; completion and cleanup additionally realpath-check containment and reject symlinks. Publication remains behind technical validation and explicit clean malware scanning.

Correction: none required.

R95 exact head `3ed05f5ba976f30048e51b31c2e6a43a60ec2865` passed File 10 Release QA run `33298365424` on PHP 8.3 and 8.4 before R96 began. This R96 evidence head must pass the full suite before R97 begins.