#!/usr/bin/env python3
from pathlib import Path
import re
files = [
    'video-wall-and-live-broadcasting/includes/class-vwlb-db.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-future-safety.php',
]
changed=[]
for name in files:
    p=Path(name); text=p.read_text()
    new=re.sub(r'(?m)^(?:\\t)+', lambda m: '\t'*(len(m.group(0))//2), text)
    if new != text:
        p.write_text(new); changed.append(name)
if not changed:
    raise SystemExit('no correction-generated literal tab prefixes found')
print('repaired:', ', '.join(changed))
