from pathlib import Path
import base64, zlib

payload = ''.join(p.read_text() for p in sorted(Path('tools').glob('file10-r20-payload-*.txt')))
code = zlib.decompress(base64.b64decode(payload)).decode()
# R03 QA-tooling correction: shell-quote literal contract needles.
code = code.replace('import subprocess\n', 'import subprocess\nimport shlex\n', 1)
code = code.replace(
    '        text += f\'need "{needle}" "{target}" {tag}\\n\'\n',
    '        text += f\'need {shlex.quote(needle)} "{target}" {tag}\\n\'\n',
    1,
)
# R06 regression-contract migration: the stronger worker-lease finalizer replaces
# the earlier array-predicate literal while preserving and strengthening its invariant.
code = code.replace(
    "def qa_commit(round_no: int, message: str):\n    sh('bash', './tests/run-all.sh')\n",
    "def qa_commit(round_no: int, message: str):\n    if round_no >= 6:\n        historical = ROOT / 'tests/fresh-40-review-contracts.sh'\n        htext = historical.read_text()\n        htext = htext.replace(\"need \\\"'status'=>'running'\\\" \\\"$P/includes/class-vwlb-jobs.php\\\" r25-job-finalize-cas\", \"need \\\"AND status='running' AND attempts=%d AND locked_by=%s\\\" \\\"$P/includes/class-vwlb-jobs.php\\\" r25-job-finalize-cas\")\n        historical.write_text(htext)\n    sh('bash', './tests/run-all.sh')\n",
    1,
)
exec(code)
