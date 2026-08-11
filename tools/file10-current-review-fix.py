from pathlib import Path

path = Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
text = path.read_text()

replacements = [
    (
        "\t\t$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','provider','ai_assisted'),'manual');$status='manual'===$source&&VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_review')?'reviewed':'candidate';if(isset($data['status']))$status=VWLB_Helpers::enum($data['status'],array('candidate','reviewed','published','rejected','removed'),$status);if('published'===$status&&!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_publish'))return VWLB_Helpers::error('vwlb_review_required',__('Human review is required before publishing generated tracks.',VWLB_TEXT_DOMAIN),403);\n",
        "\t\t$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','provider','ai_assisted'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_review');$status='manual'===$source&&$can_review?'reviewed':'candidate';if(isset($data['status'])){$requested=VWLB_Helpers::enum($data['status'],array('candidate','reviewed','published','rejected','removed'),$status);if('candidate'!==$requested&&!$can_review)return VWLB_Helpers::error('vwlb_review_required',__('Human review permission is required to change a generated track review state.',VWLB_TEXT_DOMAIN),403);$status=$requested;}if('published'===$status&&!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_publish'))return VWLB_Helpers::error('vwlb_review_required',__('Human review is required before publishing generated tracks.',VWLB_TEXT_DOMAIN),403);\n",
    ),
    (
        "\t\t$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','ai_assisted','imported'),'manual');$status=($source==='manual'&&VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_review'))?'reviewed':'candidate';if('correction'===$kind)$status='reviewed';\n",
        "\t\t$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','ai_assisted','imported'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_review');$status=($source==='manual'&&$can_review)?'reviewed':'candidate';if('correction'===$kind){if(!$can_review)return VWLB_Helpers::error('vwlb_review_required',__('Timestamp corrections require independent review permission.',VWLB_TEXT_DOMAIN),403);$status='reviewed';}\n",
    ),
]

changed = False
for old, new in replacements:
    if old in text:
        text = text.replace(old, new, 1)
        changed = True
    elif new not in text:
        raise SystemExit('R03 expected source pattern not found; refusing an unverified patch')

if changed:
    path.write_text(text)
