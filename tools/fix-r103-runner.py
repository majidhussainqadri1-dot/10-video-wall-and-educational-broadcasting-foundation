#!/usr/bin/env python3
from pathlib import Path
p=Path(__file__).with_name('apply-r103.py')
text=p.read_text()
old="workflow='.github/workflows/file10-release.yml';text=read(workflow);c=text.count(OLD)\nif c<4: raise SystemExit(f'workflow expected >=4 old version refs, got {c}')\nwrite(workflow,text.replace(OLD,NEW))\n"
if old not in text:
    raise SystemExit('R103 workflow-identity block not found in correction script')
text=text.replace(old,"# Release workflow identity is advanced separately through the authorized connector.\n",1)
p.write_text(text)
print('R103 correction runner repaired for workflow-permission boundary')
