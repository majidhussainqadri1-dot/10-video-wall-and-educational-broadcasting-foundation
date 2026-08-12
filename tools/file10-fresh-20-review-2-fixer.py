from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="if(!empty($data['expires_at'])&&!$expires)return VWLB_Helpers::error('vwlb_consent_time_invalid',__('Consent expiry time is invalid.',VWLB_TEXT_DOMAIN),422);$metadata="
new="if(!empty($data['expires_at'])&&!$expires)return VWLB_Helpers::error('vwlb_consent_time_invalid',__('Consent expiry time is invalid.',VWLB_TEXT_DOMAIN),422);if('active'===$status&&$expires&&strtotime($expires.' UTC')<=time())return VWLB_Helpers::error('vwlb_consent_time_invalid',__('Active consent expiry must be in the future.',VWLB_TEXT_DOMAIN),422);$metadata="
if new not in s:
    if old not in s: raise SystemExit('R12 consent expiry anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R12 — active consent cannot enter already expired.\nneed "Active consent expiry must be in the future." "$P/includes/class-vwlb-future-intelligence.php" r12-active-expiry\n'''
if 'r12-active-expiry' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R12 — DEFECT FIXED\nAn `active` consent link could be saved with an expiry timestamp already in the past, leaving the video available until a later reconciliation run. Active consent now requires a future expiry; explicitly expired/withdrawn states retain immediate restriction semantics.\n\n'''
if '## R12 ' not in ls: LEDGER.write_text(ls+entry)
print('R12 correction prepared')
