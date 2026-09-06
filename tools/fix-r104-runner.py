#!/usr/bin/env python3
from pathlib import Path
p=Path('tools/apply-r104.py')
text=p.read_text()
old='pattern = rf"\\tpublic static function {re.escape(name)}\\s*\\([^\\n]*\\)\\s*\\{{.*?\\n\\t\\}}(?=\\n\\n\\t(?:public|private) static function|\\n\\}})"'
new='pattern = rf"\\tpublic static function {re.escape(name)}\\s*\\([^\\n]*\\)\\s*\\{{.*?\\n\\t\\}}(?=\\n+\\t(?:public|private) static function|\\n\\}})"'
if old not in text:
    raise SystemExit('replace_function regex anchor missing')
p.write_text(text.replace(old,new,1))
print('R104 runner regex repaired')
