#!/usr/bin/env python3
from pathlib import Path
p=Path('tests/run-all.sh')
text=p.read_text()
repls={
"text = text.replace('R60 remains pending', 'R103 exact-head QA must be established')":"text = text.replace('R60 remains pending', 'R104 exact-head QA must be established')",
"text = text.replace('R40 found additional package/release-hygiene defects', 'R101 frozen finding:')":"text = text.replace('R40 found additional package/release-hygiene defects', 'R104 correction validation:')",
}
for old,new in repls.items():
    if old not in text:
        raise SystemExit('historical rebase anchor missing: '+old)
    text=text.replace(old,new,1)
p.write_text(text)
print('R104 historical harness rebase updated')
