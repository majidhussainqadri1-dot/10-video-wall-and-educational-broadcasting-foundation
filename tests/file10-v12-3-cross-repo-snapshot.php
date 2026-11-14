<?php
/**
 * File10 V12.3 source-only cross-repository snapshot checks.
 * No GitHub branch/PR/CI record may be promoted to deployed owner evidence.
 */
declare(strict_types=1);
$root=dirname(__DIR__);
$path=getenv('FILE10_V123_CROSS_REPO_FIXTURE') ?:
    $root.'/docs/V12.3-FILE10-CROSS-REPOSITORY-TRUTH-SNAPSHOT-20261114.json';
$fail=static function(string $reason): void {
    fwrite(STDERR,'File10 V12.3 cross-repo source gate FAIL: '.$reason.PHP_EOL);
    exit(1);
};
if (!is_readable($path)) $fail('missing cross-repo snapshot');
try {
    $doc=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
    $fail('malformed cross-repo JSON');
}
if (($doc['kind']??'')!=='File10V12.3CrossRepositoryBoundaries.v1' ||
    ($doc['authoritative_sha256']??'')!=='c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf' ||
    ($doc['release_approved']??null)!==false ||
    ($doc['site_modified']??null)!==false) $fail('unknown governing source or fake release');
$records=$doc['records']??[];
if (!is_array($records)||count($records)!==4) $fail('wrong companion inventory size');
$found=[];
foreach ($records as $record) {
    $key=$record['file']??'';
    if (!in_array($key,['File 06','File 11','File 26','CF 04'],true) || isset($found[$key])) {
        $fail('invalid/duplicate companion owner');
    }
    $found[$key]=true;
    if (($record['live']??'')!=='unverified') $fail($key.' made a false live claim');
    foreach (['repo','main_sha','candidate_sha','candidate_state','file10_boundary','acceptance'] as $field) {
        if (!is_string($record[$field]??null) || trim($record[$field])==='') {
            $fail($key.' missing '.$field);
        }
    }
    if (!preg_match('/^[a-f0-9]{40}$/',$record['main_sha']) ||
        !preg_match('/^[a-f0-9]{40}$/',$record['candidate_sha'])) {
        $fail($key.' has unverifiable commit hash syntax');
    }
    if (($record['candidate_pr']??0)<1) $fail('no draft PR identifier');
}
$expected=['FILE10-TO-FILE06-REAL-INTEGRATION','FILE10-TO-FILE11-REAL-INTEGRATION',
    'FILE10-TO-FILE26-TOMBSTONE-ACK-AND-PURGE','FILE10-TO-CF04-APPROVED-CUTOVER',
    'EXACT-LIVE-VERSION-PARITY'];
$gates=$doc['known_missing_evidence']??[];
if (!is_array($gates) || count($gates)!==count($expected)) $fail('missing gates');
foreach ($gates as $gate) {
    if (!in_array(($gate['key']??''),$expected,true)) $fail('unknown gate');
    if (!in_array(($gate['status']??''),['unverified','blocked_issue26','blocked_issue13'],true)) {
        $fail('premature acceptance for missing integration');
    }
}
if (count(array_unique(array_column($gates,'key')))!==count($expected)) $fail('duplicate gate ID');
echo 'File10 V12.3 companion source snapshot PASS: 4 independently versioned repos, 5 unverified/blocked integration gates; NO LIVE CLAIM'.PHP_EOL;
