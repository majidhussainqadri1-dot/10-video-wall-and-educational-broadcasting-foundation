<?php
/**
 * V12.3 File10 has one adverse acceptance oracle per original requirement.
 * An oracle is a planned test, NOT a test result, and may not be marked pass
 * without independent integration/staging/live receipts.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$source = getenv('FILE10_V123_ORACLES_FIXTURE') ?:
    ($root.'/docs/V12.3-FILE10-64-REAL-NEGATIVE-ACCEPTANCE-ORACLES.csv');
$baseline = $root.'/docs/V12.1-FILE10-64-REQUIREMENT-EVIDENCE.csv';
$fail = static function (string $s): void {
    fwrite(STDERR, 'File10 V12.3 negative-oracle gate FAIL: '.$s.PHP_EOL);
    exit(1);
};
$read = static function (string $file) use ($fail): array {
    if (!is_readable($file) || !($fp=fopen($file,'rb'))) {
        $fail('required CSV unavailable');
    }
    $header=fgetcsv($fp);
    if (!is_array($header)) $fail('empty CSV');
    $rows=[];
    while (false!==($values=fgetcsv($fp))) {
        if (count($values)!==count($header)) $fail('field count mismatch');
        $row=array_combine($header,$values);
        $key=(string)($row['requirement_id']??'');
        if (''===$key || isset($rows[$key])) $fail('duplicate/missing requirement ID');
        $rows[$key]=$row;
    }
    fclose($fp);
    return [$header,$rows];
};
[$header,$rows]=$read($source);
[$originalHeader,$baselineRows]=$read($baseline);
$fields=['requirement_id','category','failure_domain','negative_scenario',
    'expected_safe_outcome','required_real_evidence','execution_status'];
if ($header!==$fields || count($rows)!==64 || count($baselineRows)!==64 ||
    array_diff_key($rows,$baselineRows) || array_diff_key($baselineRows,$rows)) {
    $fail('missing/extra IDs or source oracle schema drift');
}
$types=['FR'=>0,'NFR'=>0,'CEN'=>0,'FUT'=>0,'STO'=>0];
$unique=[];
foreach ($rows as $id=>$row) {
    $class=$baselineRows[$id]['category']??'';
    if ($row['category']!==$class || !isset($types[$class])) {
        $fail($id.' category or source authority mismatch');
    }
    ++$types[$class];
    foreach (['failure_domain','negative_scenario','expected_safe_outcome','required_real_evidence'] as $column) {
        if (mb_strlen(trim($row[$column]),'UTF-8')<8) {
            $fail($id.' missing substantive '.$column);
        }
    }
    $signature=hash('sha256',$row['negative_scenario']);
    if (isset($unique[$signature])) {
        $fail('two distinct requirements share an identical negative scenario');
    }
    $unique[$signature]=$id;
    if ($row['execution_status']!=='not_run') {
        $fail('unverified adverse scenario cannot be declared executed: '.$id);
    }
}
if ($types!==['FR'=>19,'NFR'=>10,'CEN'=>1,'FUT'=>24,'STO'=>10]) {
    $fail('original priority/domain counts are missing');
}
echo 'File10 V12.3 adverse acceptance catalogue PASS: 64 distinct scenarios, 0 execution claims; real Staging/Live tests NOT RUN'.PHP_EOL;
