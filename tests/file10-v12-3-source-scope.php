<?php
/**
 * File10 V12.3 source-only gate: full requirement lineage and no false release.
 *
 * This test checks the frozen plan-derived overlay against the actual 64-row
 * requirement register. It is NOT a WordPress, provider, staging or live test.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$overlayFile = $root . '/docs/V12.3-FILE10-SOURCE-ACCEPTANCE-OVERLAY-20261114.json';
$ledgerFile = $root . '/docs/V12.1-FILE10-64-REQUIREMENT-EVIDENCE.csv';
$fail = static function (string $message): void {
    fwrite(STDERR, "File10 V12.3 source gate FAIL: {$message}\n");
    exit(1);
};
$json = is_readable($overlayFile) ? file_get_contents($overlayFile) : false;
if ($json === false) {
    $fail('V12.3 source overlay unreadable');
}
try {
    $plan = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    $fail('V12.3 overlay malformed JSON');
}
if (!is_array($plan) ||
    ($plan['artifact'] ?? '') !== 'File10V12.3SourceScopeOverlay.v1' ||
    ($plan['authority']['document'] ?? '') !== 'Sabri-Website-Master-Plan-V12.3.docx' ||
    ($plan['authority']['document_sha256'] ?? '') !== 'c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf') {
    $fail('unrecognized exact V12.3 master-plan baseline');
}
$counts = ['FR' => 19, 'NFR' => 10, 'CEN' => 1, 'FUT' => 24, 'STO' => 10, 'total' => 64];
if (($plan['counts'] ?? null) !== $counts) {
    $fail('requirement group counts changed');
}
if (!is_readable($ledgerFile) || !($handle = fopen($ledgerFile, 'rb'))) {
    $fail('V12.1 64-row evidence ledger unavailable');
}
$header = fgetcsv($handle);
$requiredHeaders = ['requirement_id', 'category', 'title', 'priority', 'source_mapping',
    'source_status', 'individual_test_status', 'test_evidence', 'staging_status',
    'staging_evidence', 'live_status', 'live_evidence', 'gate', 'blocking_dependency'];
if ($header !== $requiredHeaders) {
    $fail('unexpected 64-row evidence schema');
}
$records = [];
$perCategory = array_fill_keys(['FR','NFR','CEN','FUT','STO'], 0);
$future = array_fill_keys(['P0','P1','P2'], 0);
$approved = 0;
while (($values = fgetcsv($handle)) !== false) {
    if (count($values) !== count($header)) {
        $fail('ledger field count drift');
    }
    $row = array_combine($header, $values);
    $id = $row['requirement_id'];
    $class = $row['category'];
    if (isset($records[$id]) || !isset($perCategory[$class])) {
        $fail('duplicate requirement or invalid class');
    }
    $records[$id] = $row;
    ++$perCategory[$class];
    if ($class === 'FUT') {
        if (!isset($future[$row['priority']])) {
            $fail('future priority invalid');
        }
        ++$future[$row['priority']];
    }
    if ($row['individual_test_status'] === 'passed') {
        ++$approved;
    }
    if ($row['live_status'] === 'passed' && $row['staging_status'] !== 'passed') {
        $fail('live PASS without staging PASS');
    }
    if (($row['staging_status'] === 'passed' && trim($row['staging_evidence']) === '') ||
        ($row['live_status'] === 'passed' && trim($row['live_evidence']) === '')) {
        $fail('runtime PASS without recorded evidence');
    }
}
fclose($handle);
if (count($records) !== 64 || $perCategory !== array_diff_key($counts, ['total'=>true]) ||
    $future !== ['P0'=>13,'P1'=>8,'P2'=>3]) {
    $fail('64 ID groups/priority allocation mismatch');
}
$scope = $plan['source_scope'] ?? null;
if (!is_array($scope) || count($scope) !== 64) {
    $fail('source-scope coverage must list exactly 64 rows');
}
$seen = [];
foreach ($scope as $item) {
    $id = (string)($item['id'] ?? '');
    if (!isset($records[$id]) || isset($seen[$id])) {
        $fail('unexpected/duplicated scope ID');
    }
    $seen[$id] = true;
    if (($item['priority'] ?? null) !== $records[$id]['priority'] ||
        ($item['source_gate'] ?? null) !== $records[$id]['gate']) {
        $fail($id . ' priority/gate differs from primary ledger');
    }
    if (preg_match('/^F10-FR-(\d+)$/', $id, $m)) {
        $page = ((int)$m[1] <= 8) ? 162 : 163;
    } elseif (preg_match('/^F10-NFR-(\d+)$/', $id, $m)) {
        $page = ((int)$m[1] <= 5) ? 163 : 164;
    } elseif ($id === 'F10-CEN-01') {
        $page = 164;
    } elseif (preg_match('/^F10-FUT-(\d+)$/', $id, $m)) {
        $n = (int)$m[1]; $page = ($n <= 7) ? 164 : (($n <= 19) ? 165 : 166);
    } elseif (preg_match('/^V12\.1-STO-(\d+)$/', $id, $m)) {
        $page = ((int)$m[1] <= 3) ? 266 : 267;
    } else {
        $fail('unrecognized requirement family');
    }
    if (($item['master_plan_page'] ?? null) !== $page) {
        $fail($id . ' wrong original V12.3 page anchor');
    }
}
if (count($seen) !== 64) {
    $fail('missing plan-scoped ID');
}
$needed = ['M09','M11','UX-03','UX-12','AJ-15','AJ-16','QA-22','QA-24',
    'CEN-REL-001','CEN-PUB-001','V12.3-10'];
$additional = $plan['additional_acceptance'] ?? null;
if (!is_array($additional) || count($additional) !== count($needed)) {
    $fail('missing V12.3 additive acceptance clauses');
}
$actual = [];
foreach ($additional as $clause) {
    $id = (string)($clause['id'] ?? '');
    if (isset($actual[$id]) || trim((string)($clause['acceptance'] ?? '')) === '' ||
        trim((string)($clause['owner'] ?? '')) === '') {
        $fail('duplicated or incomplete V12.3 supplemental clause');
    }
    $actual[$id] = true;
    if (($clause['status'] ?? '') === 'passed') {
        if (empty($clause['evidence'])) {
            $fail('supplemental PASS without evidence: ' . $id);
        }
    } elseif (($clause['status'] ?? '') !== 'not_individually_accepted') {
        $fail('supplemental status not supported by this source-only gate: ' . $id);
    }
}
sort($needed);
$actualNames = array_keys($actual);
sort($actualNames);
if ($actualNames !== $needed) {
    $fail('V12.3 supplemental ID coverage mismatch');
}
$environment = $plan['current_environment_evidence'] ?? null;
if (!is_array($environment) ||
    ($plan['production_claim_allowed'] ?? null) !== false ||
    ($plan['overall_release_status'] ?? '') !== 'blocked_unverified' ||
    !empty($environment['live_verification']) ||
    !empty($environment['staging_acceptance']) ||
    !empty($environment['deployed_plugin_version']) ||
    !empty($environment['live_db_schema_version']) ||
    !empty($environment['migration_state'])) {
    $fail('source-only repository cannot assert unproven deployed acceptance');
}
$issues = array_map(static fn(array $x): int => (int)($x['issue'] ?? 0),
    (array)($plan['unresolved_external_gates'] ?? []));
sort($issues);
if ($issues !== [13,22,26]) {
    $fail('known external blockers not fully represented');
}
if (in_array('--release', $argv, true)) {
    $fail('production release forbidden: exact deployed/runtime, provider, staged and live evidence are unverified');
}
echo "File10 V12.3 scope PASS: 64 authoritative IDs, 11 supplemental clauses, "
    . $approved . "/64 individual tests recorded as passed; LIVE/RELEASE NOT VERIFIED\n";
