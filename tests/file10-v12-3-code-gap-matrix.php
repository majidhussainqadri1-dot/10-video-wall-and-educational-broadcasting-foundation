<?php
/**
 * File10 V12.3 exact code-to-requirement map.
 * A source path existing means it can be audited; NOT functional acceptance.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$files = [
    'primary' => $root . '/docs/V12.1-FILE10-64-REQUIREMENT-EVIDENCE.csv',
    'mapping' => $root . '/docs/V12.3-FILE10-CODE-AND-ACCEPTANCE-GAPS.csv',
];
$fail = static function (string $reason): void {
    fwrite(STDERR, "File10 V12.3 code-gap map FAIL: {$reason}\n");
    exit(1);
};
$read = static function (string $file) use ($fail): array {
    if (!is_readable($file) || !($fp = fopen($file, 'rb'))) {
        $fail("unreadable CSV " . basename($file));
    }
    $headers = fgetcsv($fp);
    if (!is_array($headers)) { $fail('missing CSV header'); }
    $records = [];
    while (($cells = fgetcsv($fp)) !== false) {
        if (count($cells) !== count($headers)) { $fail('CSV row/header mismatch'); }
        $row = array_combine($headers, $cells);
        $id = (string)($row['requirement_id'] ?? '');
        if ($id === '' || isset($records[$id])) { $fail('missing/duplicate requirement ID'); }
        $records[$id] = $row;
    }
    fclose($fp);
    return $records;
};
$base = $read($files['primary']);
$map = $read($files['mapping']);
if (count($base) !== 64 || count($map) !== 64 ||
    array_diff_key($base, $map) || array_diff_key($map, $base)) {
    $fail('all and only 64 governing IDs must be mapped');
}
$counts = ['code_candidate'=>0,'shared_external_acceptance'=>0];
foreach ($base as $id=>$original) {
    $item = $map[$id];
    foreach (['category','priority','source_status','individual_test_status','staging_status','live_status'] as $field) {
        if (($item[$field] ?? null) !== ($original[$field] ?? null)) {
            $fail($id . " field drift: {$field}");
        }
    }
    $kind = (string)($item['source_kind'] ?? '');
    if (!isset($counts[$kind])) { $fail('invalid source-evidence class: '.$id); }
    ++$counts[$kind];
    if ($kind === 'shared_external_acceptance') {
        if ($original['category'] !== 'STO' ||
            $item['source_path'] !== 'external:CF04' ||
            $item['source_test_path'] !== 'external:CF04') {
            $fail('shared storage ownership wrongly reassigned: '.$id);
        }
    } else {
        if ($original['category'] === 'STO') {
            $fail('shared storage falsely claimed to be native: '.$id);
        }
        foreach (['source_path' => 'video-wall-and-live-broadcasting/includes/',
                  'source_test_path' => 'tests/'] as $field=>$prefix) {
            $relative = (string)($item[$field] ?? '');
            if (!str_starts_with($relative, $prefix) ||
                str_contains($relative, '..') || !is_file($root . '/' . $relative)) {
                $fail($id . ' source/test file absent or unsafe: ' . $relative);
            }
        }
    }
    if (trim((string)($item['required_real_acceptance'] ?? '')) === '') {
        $fail('missing specific real acceptance statement: '.$id);
    }
}
if ($counts !== ['code_candidate'=>54,'shared_external_acceptance'=>10]) {
    $fail('expected 54 candidate mappings and 10 separately owned storage gates');
}
echo "File10 V12.3 map PASS: 54 existing file/test mapping pointers, "
    . "10 external CF04 gates; functionality, staging and live NOT inferred\n";
