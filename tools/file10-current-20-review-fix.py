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
# R06 regression-contract migration: stronger lease-token finalization supersedes the old literal.
code = code.replace(
    "def qa_commit(round_no: int, message: str):\n    sh('bash', './tests/run-all.sh')\n",
    "def qa_commit(round_no: int, message: str):\n    if round_no >= 6:\n        historical = ROOT / 'tests/fresh-40-review-contracts.sh'\n        htext = historical.read_text()\n        htext = htext.replace(\"need \\\"'status'=>'running'\\\" \\\"$P/includes/class-vwlb-jobs.php\\\" r25-job-finalize-cas\", \"need \\\"AND status='running' AND attempts=%d AND locked_by=%s\\\" \\\"$P/includes/class-vwlb-jobs.php\\\" r25-job-finalize-cas\")\n        historical.write_text(htext)\n    sh('bash', './tests/run-all.sh')\n",
    1,
)
# R19 is closed in two controlled substeps because GitHub's Actions token cannot modify workflow files.
# The sequential bot completes source/build/test identity first; the release workflow is updated by the
# connected GitHub write immediately afterward, followed by full QA, before R20 begins.
code = code.replace("for base in [P, ROOT/'tests', ROOT/'tools', ROOT/'.github/workflows', ROOT/'docs']:", "for base in [P, ROOT/'tests', ROOT/'tools', ROOT/'docs']:", 1)
code = code.replace("('video-wall-and-live-broadcasting-1.2.2-rc1.zip', '$ROOT/.github/workflows/file10-release.yml', 'r19-release-artifact'),", "('video-wall-and-live-broadcasting-1.2.2-rc1.zip', '$ROOT/tools/build-package.sh', 'r19-build-artifact'),", 1)
code = code.replace("Runtime/build/test/release metadata is advanced to `1.2.2-rc1`;", "Runtime/build/test metadata is advanced to `1.2.2-rc1`; the canonical release workflow is the remaining R19 substep and must be updated and re-tested before R20;", 1)
code = code.split('# R20 —', 1)[0] + "print('Fresh File 10 reviews R02-R19 source stage completed sequentially; R19 release-workflow substep remains before R20.')\n"
exec(code)
