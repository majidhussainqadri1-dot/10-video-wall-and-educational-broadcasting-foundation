# File 10 ↔ File 26: Frozen source review — 2026-11-13

## Exact immutable baseline
- Repository: `majidhussainqadri1-dot/10-video-wall-and-educational-broadcasting-foundation`
- Source branch: `integration/file10-file26-public-owner-contract-20261109`
- Reviewed HEAD: `08fb8ff525b346be88038589aff5d1f979017c5b` (File 10 proposed adapter in Draft PR #19).
- Governing plan: SSH MASTER V12.1; F10-FR-018, F10-NFR-001/002, F10-FUT-024, File 26 search/visibility contract and CF04 revocation boundary.
- Scope: File 10 source only. Exact deployed WordPress plugin, DB/schema, migration and live runtime **unverified**; no live incident or production repair is alleged.

## Findings frozen before any corrective source edit
**F26-PI-001 — stale source version is considered viewable (high, source-contract).**
`VWLB_File26_Connector::can_view()` checks that the File 26 document has the owner connector, public visibility and resolves to a currently eligible File 10 object. It does not compare the document's `object_version` to the **current canonical File 10 row version**. Thus an indexed description/title for version N can remain eligible even after the owner advances to N+1, provided the object is still public. A withdrawn/corrected metadata string in the stale index can be exposed until reindex. Required fix: reject missing, invalid or nonmatching versions; require re-fetch/reindex from canonical source.

**F26-PI-002 — internal WordPress owner integer crosses public search boundary (high, privacy contract).**
`project()` fills `author_key` with `file10-owner-<owner_id>`, which embeds the internal numeric File 10/WordPress principal ID into the File 26 public document. This conflicts with the owner's public-opaque, minimum-data contract. Required fix: no raw numeric owner ID in public search projection; an independent approved opaque public-author contract may follow later.

**F26-PI-003 — source/consumer disagreement on numeric public IDs (low, consistency).**
`project()` regex accepts all-digit public IDs but `can_view()` refuses them. Required fix: do not emit document candidates that will necessarily fail the canonical consumer permission check.

## Required adversarial regressions
- Stale owner version is denied even when the owner row remains published/public.
- Exact current version is allowed when rights, consent, safe mode and owner access all permit it.
- Missing/invalid version fails closed; private/revoked/removed source fails closed.
- Public File 26 document contains no source owner numeric ID.
- All-digit public IDs never enter the public candidate batch.
- New suite included in canonical `tests/run-all.sh` and verified on the branch's exact HEAD in PHP 8.3/8.4 GitHub Actions.

## No-change constraints
- Never change File 26's own database or bypass its proposed/disabled registration status.
- Keep `status=proposed`, `owner_outbox_tombstone_gate_pending`, deletion/cutover and Founder acceptance gates.
- Do not merge to main, deploy or mark staging/live acceptance on the strength of source-only tests.
