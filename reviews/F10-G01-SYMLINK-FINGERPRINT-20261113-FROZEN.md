# File 10 G01 deployed-tree fingerprint integrity — FROZEN BEFORE SOURCE EDIT (2026-11-13)

## Exact code and factual root cause
Reviewed advanced File10 Draft PR #29 HEAD `3120b7a53f86ef44caa34332c854323c4f68517d`. In `tools/file10-live-reality-freeze-readonly.php` the iterator currently executes:
`if ( $item->isLink() || ! $item->isFile() ) { continue; }`
and sets `fingerprint_status = complete` whenever `$truncated=false`.
Therefore a deployed plugin containing a source file symlink may be represented with `complete` checksum even though the symlink target (potential active PHP code) was **not hashed**. This is a precise repository-tool defect, not a claim about live installed plugin structure.

## V12.1 control
Live Reality Freeze, G01/G02 and F10-NFR-007, V12.1-STO-10: installed-source parity can be assessed only from complete, fixed content. A partial tree checksum is not proof of full deployed artifact parity.

## Safe correction
- On encountering any plugin-tree symlink, mark fingerprint as `incomplete` and suppress `expanded_tree_sha256` rather than silently omitting the link.
- Keep no symlink traversal, file contents, tokens or private WordPress paths in the published result.
- Add executable fake WP-CLI **disposable local fixture** creating a regular file + a symlink; assert collector returns `incomplete` and hash `null`, while regular-only fixture returns `complete` and a stable 64-hex SHA.
- Keep real WordPress Live/Staging unverified: this self-test is not a live evidence snapshot.

## Pre-existing blocking gates
Issue #13 live Jetpack/WPVibe disconnected and DB/schema/migration unverified; Issue #22 GitHub branch rule/check names; Issue #26 File10→File26 tombstone+CDN purge delivery/ack; Founder approval required. No merge/deploy.
