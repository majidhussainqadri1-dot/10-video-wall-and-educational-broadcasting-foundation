<?php
/**
 * Master Plan V12.1: enforce coverage and evidence hygiene, not runtime acceptance.
 * The ledger is intentionally unverified until real staging/live proof is attached.
 */
$path = __DIR__ . '/../docs/V12.1-FILE10-64-REQUIREMENT-EVIDENCE.csv';
$in = fopen($path, 'rb');
if (!$in) { fwrite(STDERR, "Missing File10 V12.1 ledger\n"); exit(1); }
$header = fgetcsv($in);
$required = array(
    'requirement_id', 'category', 'title', 'priority', 'source_mapping',
    'source_status', 'individual_test_status', 'test_evidence',
    'staging_status', 'staging_evidence', 'live_status', 'live_evidence',
    'gate', 'blocking_dependency'
);
if ($header !== $required) {
    fwrite(STDERR, "V12.1 ledger header/field contract changed\n");
    exit(1);
}
$expected = array();
foreach (array('FR'=>19, 'NFR'=>10, 'FUT'=>24) as $class=>$total) {
    for ($n=1; $n<=$total; $n++) {
        $expected['F10-'.$class.'-'.str_pad((string)$n,3,'0',STR_PAD_LEFT)] = $class;
    }
}
$expected['F10-CEN-01']='CEN';
for($n=1;$n<=10;$n++) {
    $expected['V12.1-STO-'.str_pad((string)$n,2,'0',STR_PAD_LEFT)]='STO';
}
// Captions are a separate checked-in transcription of the original user-supplied
// SSH Master Plan V12.1 (F10: DOCX paragraphs 1320–1373; STO: 2595–2604).
// Existence of an ID alone does not prove that its acceptance scope is preserved.
$captions_path = __DIR__ . '/../docs/V12.1-FILE10-AUTHORITATIVE-TITLES-20261113.json';
$caption_raw = is_readable($captions_path) ? file_get_contents($captions_path) : false;
$canonical_titles = false === $caption_raw ? null : json_decode($caption_raw,true);
if(!is_array($canonical_titles) || count($canonical_titles)!==64 ||
    array_diff(array_keys($expected),array_keys($canonical_titles)) ||
    array_diff(array_keys($canonical_titles),array_keys($expected))) {
    fwrite(STDERR, "V12.1 source titles are missing, stale or no longer cover all 64 IDs\n");
    exit(1);
}
$seen = array();
$priority = array('P0'=>0,'P1'=>0,'P2'=>0);
$line = 1;
$fail = static function ($message) use (&$line) {
    fwrite(STDERR, "File10 V12.1 ledger FAIL at line ".$line.": ".$message."\n");
    exit(1);
};
while (($raw=fgetcsv($in)) !== false) {
    ++$line;
    if (count($raw) !== count($header)) { $fail('row/header field count'); }
    $row = array_combine($header,$raw);
    $id=$row['requirement_id'];
    if (!isset($expected[$id])) { $fail('unexpected ID: '.$id); }
    if (isset($seen[$id])) { $fail('duplicate ID: '.$id); }
    $seen[$id]=true;
    if ($expected[$id] !== $row['category']) { $fail('incorrect category: '.$id); }
    if (!is_string($canonical_titles[$id]) || $row['title'] !== $canonical_titles[$id]) {
        $fail($id.': caption differs from the exact V12.1 master plan');
    }
    foreach (array('title','priority','source_mapping','source_status','gate','blocking_dependency') as $k) {
        if (trim($row[$k]) === '') { $fail($id.': missing '.$k); }
    }
    if (!in_array($row['source_status'],array('candidate_mapped_not_accepted','independently_reviewed','verified'),true)) {
        $fail($id.': invalid source status');
    }
    if (!in_array($row['individual_test_status'],array('not_individually_accepted','passed','failed','blocked'),true)) {
        $fail($id.': invalid individual test status');
    }
    if ($row['individual_test_status']==='passed' && trim($row['test_evidence'])==='') {
        $fail($id.': individual test PASS without evidence');
    }
    foreach (array('staging','live') as $env) {
        if (!in_array($row[$env.'_status'],array('unverified','blocked','failed','passed'),true)) {
            $fail($id.': invalid '.$env.' status');
        }
        if ($row[$env.'_status']==='passed' && trim($row[$env.'_evidence'])==='') {
            $fail($id.': unsupported '.$env.' PASS');
        }
    }
    if ($row['live_status']==='passed' && $row['staging_status']!=='passed') {
        $fail($id.': live PASSED while staging not PASSED');
    }
    if ($row['category']==='FUT') {
        if (!array_key_exists($row['priority'],$priority)) { $fail($id.': bad Future24 priority'); }
        ++$priority[$row['priority']];
    }
}
fclose($in);
if (count($seen)!==64 || count($seen)!==count($expected)) { $fail('missing one or more of 64 exact IDs'); }
if ($priority !== array('P0'=>13,'P1'=>8,'P2'=>3)) { $fail('Future24 priority allocation differs from approved plan'); }
echo "File10 V12.1 all 64 evidence-gate records PASS (source/runtime acceptance not inferred)\n";
