from pathlib import Path

for p in Path('tools').glob('file10-r20-payload-*.txt'):
    p.unlink()
Path('tools/file10-current-20-review-fix.py').unlink()
print('Pre-R20 temporary review payload/loader cleanup prepared')
