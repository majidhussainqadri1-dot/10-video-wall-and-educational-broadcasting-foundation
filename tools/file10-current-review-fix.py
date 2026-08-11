from pathlib import Path

roots=[Path('video-wall-and-live-broadcasting'),Path('tests'),Path('tools'),Path('.github'),Path('docs')]
changed=[]
for root in roots:
    if not root.exists(): continue
    for path in root.rglob('*'):
        if not path.is_file() or path.name=='file10-current-review-fix.py': continue
        if path.suffix.lower() not in {'.php','.sh','.yml','.yaml','.md','.txt','.js','.css','.json'}: continue
        try: text=path.read_text()
        except UnicodeDecodeError: continue
        if '1.2.0-rc1' in text:
            path.write_text(text.replace('1.2.0-rc1','1.2.1-rc1'))
            changed.append(str(path))
if not changed: raise SystemExit('R37 expected current runtime identity 1.2.0-rc1 was not found')
main=Path('video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php').read_text()
if "Version: 1.2.1-rc1" not in main or "define( 'VWLB_VERSION', '1.2.1-rc1' );" not in main: raise SystemExit('R37 runtime identity did not advance')
if "define( 'VWLB_FUTURE_SCHEMA_VERSION', '1.2.0' );" not in main: raise SystemExit('R37 schema version was incorrectly inflated')
reg=Path('tests/fresh-40-review-contracts.sh');r=reg.read_text();marker="""# R37 — source, tests, build and release workflow use one runtime/package identity without schema inflation.\nneed \"Version: 1.2.1-rc1\" \"$P/video-wall-and-live-broadcasting.php\" r37-plugin-version\nneed \"VWLB_VERSION', '1.2.1-rc1\" \"$P/video-wall-and-live-broadcasting.php\" r37-runtime-version\nneed \"VWLB_FUTURE_SCHEMA_VERSION', '1.2.0\" \"$P/video-wall-and-live-broadcasting.php\" r37-schema-stable\nneed \"video-wall-and-live-broadcasting-1.2.1-rc1.zip\" \"$ROOT/tools/build-package.sh\" r37-build-name\nneed \"file10-video-wall-live-1.2.1-rc1\" \"$ROOT/.github/workflows/file10-release.yml\" r37-artifact-name\n"""
if '# R37 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
print('R37 advanced files:',len(changed))
