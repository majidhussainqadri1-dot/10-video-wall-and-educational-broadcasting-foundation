from pathlib import Path
import base64
import hashlib
import zlib

root = Path(__file__).resolve().parent
expected = [
    ('file10-third-fresh-20-review.part1', '7ffe92231e3cb75d48b6dddeb345831195a03d9ad5c6e5ba954355e6371aa68c'),
    ('file10-third-fresh-20-review.part2', '44e434b68868a0c4e0ddccc8519976ea5c61c3940213e6d81ee2bcc61a9a2685'),
    ('file10-third-fresh-20-review.part3', '84c3d909681891fd45749ff8661508910db0761ab32873c71b10e0c2426b35ab'),
    ('file10-third-fresh-20-review.part4', '2ce3e40483c0c38a567353f6ccabb31d8d881fed325ed3673a97128f6663a676'),
]
pieces = []
for name, want in expected:
    data = (root / name).read_text().strip()
    got = hashlib.sha256(data.encode()).hexdigest()
    if got != want:
        raise SystemExit(f'payload checksum mismatch {name}: {got}')
    pieces.append(data)
source = zlib.decompress(base64.b64decode(''.join(pieces)))
got_source = hashlib.sha256(source).hexdigest()
want_source = '2f384f3295f425396dcce9db43c10a36b4d48bb45f6b175c0bef9a9b412ec468'
if got_source != want_source:
    raise SystemExit(f'payload source checksum mismatch: {got_source}')
exec(compile(source, '<file10-third-fresh-20-review>', 'exec'), globals(), globals())
