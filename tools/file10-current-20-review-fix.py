from pathlib import Path

p = Path('tests/fresh-40-review-contracts.sh')
s = p.read_text()
old = 'need "file10-video-wall-live-1.2.1-rc1" "$ROOT/.github/workflows/file10-release.yml" r37-artifact-name'
new = 'need "file10-video-wall-live-1.2.2-rc1" "$ROOT/.github/workflows/file10-release.yml" r37-artifact-name'
if old not in s:
    raise SystemExit('R19 historical release artifact gate anchor missing')
p.write_text(s.replace(old, new, 1))

p = Path('tests/fresh-20-review-contracts.sh')
s = p.read_text()
line = 'need file10-video-wall-live-1.2.2-rc1 "$ROOT/.github/workflows/file10-release.yml" r19-release-artifact\n'
if line not in s:
    s += line
p.write_text(s)

p = Path('docs/FILE-10-FRESH-20-REVIEW-2026-08-12.md')
s = p.read_text()
old_phrase = 'Runtime/build/test metadata is advanced to `1.2.2-rc1`; the canonical release workflow is the remaining R19 substep and must be updated and re-tested before R20;'
new_phrase = 'Runtime/build/test/release metadata is aligned to `1.2.2-rc1`; the canonical release workflow now builds, verifies and publishes the 1.2.2 release-candidate artifact;'
if old_phrase not in s:
    raise SystemExit('R19 ledger closure anchor missing')
p.write_text(s.replace(old_phrase, new_phrase, 1))
print('R19 release-workflow contract and ledger closed')
