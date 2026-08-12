from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="if('correction'===$kind)VWLB_Helpers::outbox('VideoTimestampCorrectionPublished','video',$video['id'],array('annotation_public_id'=>$row['public_id'],'start_ms'=>$row['start_ms']));return self::public_row('video_annotations',$id);"
new="return self::public_row('video_annotations',$id);"
if new not in s:
    if old not in s: raise SystemExit('R13 create event anchor missing')
    s=s.replace(old,new,1)
old2="if('published'===$to)VWLB_Helpers::outbox('VideoAnnotationPublished','video',$video['id'],array('annotation_public_id'=>$ann['public_id'],'kind'=>$ann['kind']));return self::public_row('video_annotations',$ann['id']);"
new2="if('published'===$to){VWLB_Helpers::outbox('VideoAnnotationPublished','video',$video['id'],array('annotation_public_id'=>$ann['public_id'],'kind'=>$ann['kind']));if('correction'===$ann['kind'])VWLB_Helpers::outbox('VideoTimestampCorrectionPublished','video',$video['id'],array('annotation_public_id'=>$ann['public_id'],'start_ms'=>$ann['start_ms']));}return self::public_row('video_annotations',$ann['id']);"
if new2 not in s:
    if old2 not in s: raise SystemExit('R13 publish event anchor missing')
    s=s.replace(old2,new2,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R13 — correction publication fact is tied to the publish transition.\nneed "VideoTimestampCorrectionPublished" "$P/includes/class-vwlb-future-intelligence.php" r13-correction-publication-event\n'''
if 'r13-correction-publication-event' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R13 — DEFECT FIXED\nCreating a timestamp correction emitted `VideoTimestampCorrectionPublished` while the new annotation was only `reviewed`. Downstream consumers could therefore receive a false publication fact. The correction-specific event now fires only when the annotation actually transitions to `published`.\n\n'''
if '## R13 ' not in ls: LEDGER.write_text(ls+entry)
print('R13 correction prepared')
