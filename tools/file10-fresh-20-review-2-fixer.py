from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="'/watermarks/(?P<object_type>video|live)/(?P<id>[A-Za-z0-9_-]+)/grant','GET','watermark_grant','public'"
new="'/watermarks/(?P<object_type>video|live)/(?P<id>[A-Za-z0-9_-]+)/grant','POST','watermark_grant','public'"
if new not in s:
    if old not in s: raise SystemExit('R16 route anchor missing')
    s=s.replace(old,new,1)
old2="return $this->response(VWLB_Future_Intelligence::watermark_payload(array('mode'=>'off'),$type,$object,array('claims'=>VWLB_Security::claims())));"
new2="$response=$this->response(VWLB_Future_Intelligence::watermark_payload(array('mode'=>'off'),$type,$object,array('claims'=>VWLB_Security::claims())));if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;"
if new2 not in s:
    if old2 not in s: raise SystemExit('R16 response anchor missing')
    s=s.replace(old2,new2,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R16 — watermark grants are non-cacheable stateful POST operations.\nneed "/grant','POST','watermark_grant'" "$P/includes/class-vwlb-future-rest.php" r16-watermark-post\nneed "private, no-store" "$P/includes/class-vwlb-future-rest.php" r16-watermark-no-store\n'''
if 'r16-watermark-post' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R16 — DEFECT FIXED\nForensic watermark grants mint a fresh token and audit evidence, but the route exposed that stateful operation as `GET`. Browser/intermediary caching or speculative retrieval was unsafe. The grant now uses `POST` and returns `Cache-Control: private, no-store`.\n\n'''
if '## R16 ' not in ls: LEDGER.write_text(ls+entry)
print('R16 correction prepared')
