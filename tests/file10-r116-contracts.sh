#!/usr/bin/env bash
set -euo pipefail

ext='video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
sec='video-wall-and-live-broadcasting/includes/class-vwlb-security.php'

# R116-F1: no suppressed private-storage protection writes; every required file is verified.
! grep -Fq '@file_put_contents( $path, $content, LOCK_EX )' "$ext"
grep -Fq "vwlb_private_storage_protection_failed" "$ext"
grep -Fq "vwlb_private_storage_protection_unverified" "$ext"
grep -Fq "hash_equals( hash( 'sha256', \$content ), hash( 'sha256', \$actual ) )" "$ext"

# R116-F2: authoritative verification reads must use VWLB_DB::read_row.
grep -Fq "'rate_limit_verify'" "$sec"
grep -Fq "'idempotency_finish_verify'" "$sec"
! grep -Fq '$row=$wpdb->get_row($wpdb->prepare("SELECT counter,window_ends_at FROM $table WHERE limit_key=%s"' "$sec"
! grep -Fq '$row=$wpdb->get_row($wpdb->prepare("SELECT status,response_json FROM $table WHERE idempotency_key=%s AND scope=%s"' "$sec"

echo 'File 10 R116 contracts: PASS'
