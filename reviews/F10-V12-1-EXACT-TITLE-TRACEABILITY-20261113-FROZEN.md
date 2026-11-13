# File10 V12.1 source title fidelity — FROZEN BEFORE LEDGER CORRECTION

## Immutable source texts and reviewed candidate
- Master source: user-supplied `صابری سوشل ہومیوپیتھی پلیٹ فارم — جامع مرکزی ماسٹر پلان V12.1.docx`.
- Requirement identities: File10 `F10-FR-001…019`, `F10-NFR-001…010`, `F10-CEN-01`, `F10-FUT-001…024` in original DOCX paragraphs 1320–1373; `V12.1-STO-01…10` in paragraphs 2595–2604.
- Repository source before any changes: File10 PR #30 exact SHA `323f194fb3c9b5315e0c63c1fb0fef0d27f23fab`; `docs/V12.1-FILE10-64-REQUIREMENT-EVIDENCE.csv` and `tests/file10-v12-1-evidence-ledger.php`.

## Proven source-traceability defect TRACE-001 (medium, planning not Live runtime)
All 64 IDs were present and their overall categories/future P0/P1/P2 counts correct. However, the CSV `title` fields paraphrased or shortened the **actual binding V12.1 requirement captions**. Examples: `F10-FR-008` used "Progress and viewing history" instead of `Watch progress/history`; `F10-FUT-016` omitted "Chapter" from `AI-Assisted Chapter & Key-Moment Suggestions`; `F10-FUT-022` omitted `Case-Video Auto-Restriction`; `V12.1-STO-10` was English paraphrase instead of the actual Urdu `بحالی اور فراہم کنندہ کی تبدیلی`. This weakens auditability and could allow key requirements to disappear during acceptance review.

## Minimal source-controlled correction
1. Replace CSV title strings with **exact** V12.1 captions (not new requirements).
2. Add `docs/V12.1-FILE10-AUTHORITATIVE-TITLES-20261113.json`, the 64-code-to-caption transcription from the provided DOCX, with source locations recorded in this review.
3. Strengthen `tests/file10-v12-1-evidence-ledger.php` to reject any omission or mismatch against the frozen 64-caption document, while preserving all existing evidence, priorities and false-acceptance checks.
4. Never mark an individual, Staging or Live acceptance PASS from title alignment alone.

## Gates
Exact-head PHP 8.3/8.4 QA and source-package parity required. WordPress/Jetpack disconnected; GitHub Issue #13 Live parity remains unknown; #22 release branch governance; #26 File26 durable ack/CDN purge. This is source-only and stays Draft.
