<?php
/** F10 V12.3 — dbDelta requires line-bounded SQL column/index grammar.
 * Test first: verify canonical CREATE SQL may be reformatted safely, including
 * decimal commas, composite indexes and quoted comma defaults.
 * These are isolated source-parser tests, NOT Staging/Live upgrade proof.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
define('VWLB_TEXT_DOMAIN','file10-source-test');
final class WP_Error {
    public function __construct(public string $code,public string $message){}
    public function get_error_code(): string{return $this->code;}
}
function is_wp_error($x): bool{return $x instanceof WP_Error;}
function __($s,$domain=''): string{return (string)$s;}
final class VWLB_Helpers {
    public static function error($code,$message,$status=500): WP_Error{return new WP_Error($code,$message);}
}
final class FakeWPDB {
    public string $prefix='wp_';
    public function get_charset_collate(): string{return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';}
}
$GLOBALS['wpdb']=new FakeWPDB();
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-db.php';
$GLOBALS['checked']=0;
function must(bool $condition,string $why): void{
    ++$GLOBALS['checked'];
    if(!$condition){fwrite(STDERR,'File10 V12.3 dbDelta formatter FAIL: '.$why.PHP_EOL);exit(1);}
}
$sample="CREATE TABLE wp_vwlb_fixture (id bigint unsigned NOT NULL AUTO_INCREMENT,public_id varchar(80) NOT NULL,rating decimal(5,2) NULL,note varchar(80) NOT NULL DEFAULT 'a,b',PRIMARY KEY(id),UNIQUE KEY public_id(public_id),KEY combined(public_id,rating)) DEFAULT CHARACTER SET utf8mb4";
$formatted=VWLB_DB::dbdelta_sql($sample);
must(is_string($formatted),'supported CREATE SQL must format');
must(str_contains($formatted,",\n"),'schema declarations are separated by physical lines');
must(str_contains($formatted,'rating decimal(5,2) NULL'),'DECIMAL comma remains part of type');
must(str_contains($formatted,"DEFAULT 'a,b'"),'quoted comma default is preserved');
must(str_contains($formatted,'PRIMARY KEY  (id)'),'primary key grammar normalized');
must(str_contains($formatted,'UNIQUE KEY public_id (public_id)'),'unique key normalized');
must(str_contains($formatted,'KEY combined (public_id,rating)'),'composite key comma preserved');
must(substr_count($formatted,",\n")===6,'exactly six top-level definition separators');
must(VWLB_DB::dbdelta_sql($sample)===$formatted,'deterministic formatting');

$schema=VWLB_DB::schema_sql();
must(is_array($schema)&&count($schema)>=10,'original canonical schema remains present');
foreach($schema as $statement){
    $out=VWLB_DB::dbdelta_sql($statement);
    must(is_string($out),'every existing base table source format is supported');
    must(str_contains($out,",\n"),'every base table is actually line-delimited for dbDelta');
}
must(is_wp_error(VWLB_DB::dbdelta_sql('DROP TABLE wp_vwlb_videos')),'no arbitrary SQL accepted');
must(is_wp_error(VWLB_DB::dbdelta_sql('CREATE TABLE wp_vwlb_broken (id int,KEY idx(id)')),'unbalanced SQL rejected');
must(is_wp_error(VWLB_DB::dbdelta_sql('CREATE TABLE wp_vwlb_broken (id int)) KEY idx (id)')),'extra closing parenthesis rejected');
echo 'File10 V12.3 dbDelta SQL grammar PASS: '.$GLOBALS['checked'].
     ' parser assertions, no DB writes, no Staging/Live claims'.PHP_EOL;
