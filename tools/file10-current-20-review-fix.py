from pathlib import Path
import base64, zlib

# Assemble the verified staged payload in lexical order.
payload = ''.join(
    p.read_text()
    for p in sorted(Path('tools').glob('file10-r20-payload-*.txt'))
)
code = zlib.decompress(base64.b64decode(payload)).decode()
# QA tooling correction within R03: quote literal contract needles so PHP $variables
# are not expanded by bash under set -u. Targets remain double-quoted so $ROOT/$P resolve.
code = code.replace('import subprocess\n', 'import subprocess\nimport shlex\n', 1)
code = code.replace(
    '        text += f\'need "{needle}" "{target}" {tag}\\n\'\n',
    '        text += f\'need {shlex.quote(needle)} "{target}" {tag}\\n\'\n',
    1,
)
exec(code)
