from pathlib import Path

files = [
    'video-wall-and-live-broadcasting/includes/class-vwlb-live.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-jobs.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-integrations.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php',
]
for name in files:
    p = Path(name)
    lines = p.read_text().splitlines(True)
    fixed = []
    for line in lines:
        prefix = ''
        while line.startswith('\\t'):
            prefix += '\t'
            line = line[2:]
        fixed.append(prefix + line)
    p.write_text(''.join(fixed))
