from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
old = '1.2.3-rc1'
new = '1.2.4-rc1'
changed = []
for path in sorted((ROOT / 'tests').glob('*.sh')):
    text = path.read_text()
    if old in text:
        path.write_text(text.replace(old, new))
        changed.append(str(path.relative_to(ROOT)))

plugin = ROOT / 'video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php'
build = ROOT / 'tools/build-package.sh'
release = ROOT / '.github/workflows/file10-release.yml'
for path in (plugin, build, release):
    text = path.read_text()
    if old in text:
        raise SystemExit(f'stale release identity remains in governed release file: {path.relative_to(ROOT)}')
    if new not in text:
        raise SystemExit(f'current release identity missing from governed release file: {path.relative_to(ROOT)}')

ledger = ROOT / 'docs/FILE-10-THIRD-FRESH-20-REVIEW-2026-08-12.md'
text = ledger.read_text()
closure = '''\n## Release-hygiene closure\nAll twenty sequential review/fix rounds are closed. The corrected runtime/package/release identity is `1.2.4-rc1`; database schema constants remain unchanged (`base 1.1.0`, `extension 1.1.0`, `future 1.2.0`) because this corrective cycle introduced no structural schema migration. Active release-identity regression expectations were synchronized, temporary review machinery is removed, and the cleaned exact HEAD must pass the canonical PHP 8.3/8.4 File 10 Release QA before it can be called Automated-QA Green. This remains repository evidence only, not staging/live evidence.\n'''
if '## Release-hygiene closure' not in text:
    ledger.write_text(text + closure)

for path in sorted((ROOT / 'tests').glob('*.sh')):
    if old in path.read_text():
        raise SystemExit(f'stale active test release identity: {path.relative_to(ROOT)}')
print('release hygiene synchronized:', ', '.join(changed) if changed else 'no test replacements needed')
