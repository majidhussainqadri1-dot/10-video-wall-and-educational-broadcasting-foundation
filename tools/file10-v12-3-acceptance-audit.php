<?php
/**
 * File10 V12.3 — independent source evidence/status audit.
 *
 * Read-only CLI, no WordPress bootstrap, network, credentials or DB writes.
 * Source/test mapping is never counted as functional or deployed acceptance.
 * Input fixtures are allowed for regression tests, but not production claims.
 */
declare(strict_types=1);

const F10_V123_PLAN_SHA256 = 'c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf';
const F10_V123_REPORT_KIND = 'File10V12.3ReadOnlyAcceptanceAudit.v1';

$root = dirname(__DIR__);
$ledger = getenv('FILE10_V123_LEDGER_FIXTURE') ?: ($root.'/docs/V12.1-FILE10-64-REQUIREMENT-EVIDENCE.csv');
$matrix = getenv('FILE10_V123_MATRIX_FIXTURE') ?: ($root.'/docs/V12.3-FILE10-CODE-AND-ACCEPTANCE-GAPS.csv');
$overlay = getenv('FILE10_V123_OVERLAY_FIXTURE') ?: ($root.'/docs/V12.3-FILE10-SOURCE-ACCEPTANCE-OVERLAY-20261114.json');
$jsonOutput = in_array('--json', $argv, true);
$releaseAttempt = in_array('--release', $argv, true);

$fail = static function (string $reason): void {
    fwrite(STDERR, 'File10 V12.3 acceptance audit FAIL: '.$reason.PHP_EOL);
    exit(2);
};
$readCsv = static function (string $path) use ($fail): array {
    if (!is_readable($path) || false === ($fp = fopen($path, 'rb'))) {
        $fail('required source/acceptance CSV unavailable');
    }
    $fields = fgetcsv($fp);
    if (!is_array($fields) || count($fields) !== count(array_unique($fields)) ||
        !in_array('requirement_id', $fields, true)) {
        $fail('acceptance CSV has invalid header');
    }
    $items = [];
    while (false !== ($values = fgetcsv($fp))) {
        if (count($values) !== count($fields)) {
            $fail('CSV field count mismatch');
        }
        $row = array_combine($fields, $values);
        $id = (string)$row['requirement_id'];
        if ('' === $id || isset($items[$id])) {
            $fail('missing or repeated requirement');
        }
        $items[$id] = $row;
    }
    fclose($fp);
    return $items;
};
$original = $readCsv($ledger);
$mapping = $readCsv($matrix);
$raw = is_readable($overlay) ? file_get_contents($overlay) : false;
if (false === $raw) {
    $fail('V12.3 overlay missing');
}
try {
    $source = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $fail('V12.3 overlay not valid JSON');
}
if (!is_array($source) || ($source['authority']['document_sha256'] ?? '') !== F10_V123_PLAN_SHA256 ||
    ($source['production_claim_allowed'] ?? null) !== false ||
    ($source['overall_release_status'] ?? '') !== 'blocked_unverified') {
    $fail('original master-plan identity/source-only boundary not preserved');
}
if (count($original) !== 64 || count($mapping) !== 64 ||
    array_diff_key($original, $mapping) || array_diff_key($mapping, $original)) {
    $fail('64 original requirements were lost or duplicated');
}
$categoryTotals = ['FR'=>19,'NFR'=>10,'CEN'=>1,'FUT'=>24,'STO'=>10];
$categoryCount = array_fill_keys(array_keys($categoryTotals), 0);
$counts = [
    'total'=>0, 'candidate_mapped'=>0, 'independently_source_reviewed'=>0,
    'individual_passed_declared'=>0,'individual_unaccepted'=>0,
    'individual_failed'=>0,'individual_blocked'=>0,
    'staging_passed_declared'=>0,'staging_unverified'=>0,
    'staging_failed'=>0,'staging_blocked'=>0,
    'live_passed_declared'=>0,'live_unverified'=>0,
    'live_failed'=>0,'live_blocked'=>0,
    'external_cf04_required'=>0,'native_candidate_sources'=>0
];
$requirements = [];
foreach ($original as $id=>$row) {
    $m = $mapping[$id];
    $class = $row['category'];
    if (!isset($categoryTotals[$class]) || ($m['category'] ?? '') !== $class ||
        ($m['priority'] ?? '') !== $row['priority']) {
        $fail('ID/category/priority disagreement: '.$id);
    }
    ++$categoryCount[$class];
    $src = $row['source_status'];
    $individual = $row['individual_test_status'];
    $stage = $row['staging_status'];
    $live = $row['live_status'];
    if (!in_array($src, ['candidate_mapped_not_accepted','independently_reviewed','verified'], true) ||
        !in_array($individual, ['not_individually_accepted','passed','failed','blocked'], true) ||
        !in_array($stage, ['unverified','passed','failed','blocked'], true) ||
        !in_array($live, ['unverified','passed','failed','blocked'], true)) {
        $fail('unrecognized evidence stage: '.$id);
    }
    foreach (['source_status','individual_test_status','staging_status','live_status'] as $field) {
        if (($m[$field] ?? null) !== $row[$field]) {
            $fail('matrix and ledger diverge: '.$id.' / '.$field);
        }
    }
    if (('passed'===$individual && ''===trim($row['test_evidence'])) ||
        ('passed'===$stage && ''===trim($row['staging_evidence'])) ||
        ('passed'===$live && ''===trim($row['live_evidence']))) {
        $fail('unsupported pass declaration: '.$id);
    }
    if ('passed'===$stage && 'passed'!==$individual) {
        $fail('staging pass without individual test: '.$id);
    }
    if ('passed'===$live && 'passed'!==$stage) {
        $fail('live pass without staging test: '.$id);
    }
    $kind = $m['source_kind'];
    if ($class === 'STO') {
        if ('shared_external_acceptance' !== $kind ||
            'external:CF04' !== $m['source_path'] ||
            'external:CF04' !== $m['source_test_path']) {
            $fail('CF04 canonical ownership was changed: '.$id);
        }
        ++$counts['external_cf04_required'];
    } else {
        if ('code_candidate' !== $kind) {
            $fail('native code candidate class changed: '.$id);
        }
        ++$counts['native_candidate_sources'];
    }
    ++$counts['total'];
    if ('candidate_mapped_not_accepted'===$src) ++$counts['candidate_mapped'];
    else ++$counts['independently_source_reviewed'];
    if ('passed'===$individual) ++$counts['individual_passed_declared'];
    elseif ('not_individually_accepted'===$individual) ++$counts['individual_unaccepted'];
    elseif ('failed'===$individual) ++$counts['individual_failed'];
    else ++$counts['individual_blocked'];
    foreach ([['staging',$stage],['live',$live]] as [$which,$status]) {
        $suffix = $status === 'passed' ? 'passed_declared' : $status;
        ++$counts[$which.'_'.$suffix];
    }
    $requirements[]=[
        'id'=>$id, 'category'=>$class, 'priority'=>$row['priority'],
        'source_status'=>$src, 'individual_status'=>$individual,
        'staging_status'=>$stage, 'live_status'=>$live,
        'evidence_is_environment_verified'=>false,
        'source_kind'=>$kind
    ];
}
if ($categoryCount !== $categoryTotals || $counts['total']!==64 ||
    $counts['external_cf04_required']!==10 ||
    $counts['native_candidate_sources']!==54) {
    $fail('source classification distribution changed');
}
$blockers = [];
foreach ((array)($source['unresolved_external_gates']??[]) as $blocked) {
    $n = (int)($blocked['issue']??0);
    if (!in_array($n,[13,22,26],true) || in_array($n,$blockers,true) ||
        ($blocked['status']??'')!=='blocked' && ($blocked['status']??'')!=='open') {
        $fail('invalid/historically incomplete blocker snapshot');
    }
    $blockers[] = $n;
}
sort($blockers);
if ($blockers !== [13,22,26]) {
    $fail('missing known snapshot blocker');
}
$summary = [
    'kind'=>F10_V123_REPORT_KIND,
    'scope'=>'repository_source_only',
    'master_plan_sha256'=>F10_V123_PLAN_SHA256,
    'reporting_basis'=>'stored, unverified ledger declarations; no live runtime observation',
    'totals'=>$counts,
    'by_category'=>$categoryCount,
    'historical_open_issue_snapshot'=>$blockers,
    'live_source_verified'=>false,
    'staging_environment_verified'=>false,
    'release_approval'=>false,
    'defect_closeout_authorized'=>false,
    'requirements'=>$requirements
];
if ($releaseAttempt) {
    $fail('NO-GO: release evidence must be independently validated on exact deployed code and runtime');
}
if ($jsonOutput) {
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} else {
    echo sprintf(
        'File10 V12.3 SOURCE audit: %d IDs; %d code candidates; %d CF04 external; '.
        'individual unaccepted %d; Staging unverified %d; Live unverified %d; open snapshot issues %s. '.
        'NO LIVE/RELEASE CLAIM'.PHP_EOL,
        $counts['total'], $counts['native_candidate_sources'],
        $counts['external_cf04_required'], $counts['individual_unaccepted'],
        $counts['staging_unverified'], $counts['live_unverified'], implode(',',$blockers)
    );
}
