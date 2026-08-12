from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="foreach($raw_ids as $raw){if(ctype_digit($raw))$id=(int)$wpdb->get_var($wpdb->prepare(\"SELECT id FROM $option_table WHERE poll_id=%d AND id=%d\",$poll['id'],(int)$raw));else$id=(int)$wpdb->get_var($wpdb->prepare(\"SELECT id FROM $option_table WHERE poll_id=%d AND public_id=%s\",$poll['id'],$raw));if($id)$ids[]=$id;}"
new="foreach($raw_ids as $raw){$id=(int)$wpdb->get_var($wpdb->prepare(\"SELECT id FROM $option_table WHERE poll_id=%d AND public_id=%s\",$poll['id'],$raw));if($id)$ids[]=$id;}"
if new not in s:
    if old not in s: raise SystemExit('R07 poll identifier anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R07 — public poll answers use opaque option IDs only; internal numeric PKs are not accepted.\nneed "WHERE poll_id=%d AND public_id=%s" "$P/includes/class-vwlb-future-intelligence.php" r07-public-option-id\nforbid(){ ! grep -F -- "$1" "$2" >/dev/null || { echo "FAIL second-fresh-20: $3" >&2; exit 1; }; }\nforbid "ctype_digit($raw)" "$P/includes/class-vwlb-future-intelligence.php" r07-no-numeric-pk-answer\n'''
# Escape the generated shell variable so set -u cannot expand it while parsing the test.
checks=checks.replace('ctype_digit($raw)', 'ctype_digit(\\$raw)')
if 'r07-no-numeric-pk-answer' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R07 — DEFECT FIXED\nThe public live-poll answer path accepted either the opaque option `public_id` or a guessed numeric database primary key. Public DTOs intentionally hide internal IDs, so accepting them reintroduced a guessable identifier path and weakened the object-identity boundary. Poll answers now resolve only the option public ID within the current poll; internal numeric option keys remain server-side implementation details.\n\n'''
if '## R07 ' not in ls: LEDGER.write_text(ls+entry)
print('R07 correction prepared')
