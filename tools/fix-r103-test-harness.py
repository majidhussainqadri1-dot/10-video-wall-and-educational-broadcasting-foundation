#!/usr/bin/env python3
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'tests/run-all.sh'
text=p.read_text()
old="text = text.replace('R60 remains pending', 'R102 exact-head QA must be established')"
new="text = text.replace('R60 remains pending', 'R103 exact-head QA must be established')"
if old not in text:
    raise SystemExit('stale R59 status rebase marker not found')
p.write_text(text.replace(old,new,1))
print('R103 historical R59 status assertion rebased to current R103 QA gate')
