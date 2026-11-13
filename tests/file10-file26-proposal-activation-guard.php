<?php
/** Source-only fail-closed guard for unapproved File10→File26 connector. */
$root=dirname(__DIR__);
$paths=array(
    $root.'/video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php',
    $root.'/video-wall-and-live-broadcasting/includes/class-vwlb-plugin.php',
    $root.'/video-wall-and-live-broadcasting/includes/class-vwlb-file26-connector.php'
);
foreach($paths as $path) {
    if(!is_readable($path)) { fwrite(STDERR,"File26 activation guard FAIL: missing ".basename($path)."\n"); exit(1); }
}
$entry=file_get_contents($paths[0]);
$composition=file_get_contents($paths[1]);
$adapter=file_get_contents($paths[2]);
$fail=static function($reason){fwrite(STDERR,"File26 activation guard FAIL: ".$reason."\n");exit(1);};
foreach(array($entry,$composition) as $bootstrap) {
    if(strpos($bootstrap,'class-vwlb-file26-connector.php')!==false) $fail('proposed adapter must not be autoloaded in runtime');
    if(strpos($bootstrap,'VWLB_File26_Connector::register(')!==false) $fail('proposed adapter must not be registered in runtime');
}
if(!preg_match("/'status'\\s*=>\\s*'proposed'/",$adapter)) $fail('missing proposed manifest state');
if(strpos($adapter,'owner_outbox_tombstone_gate_pending')===false) $fail('missing blocked deletion/tombstone cutover');
if(strpos($adapter,'event_tombstone_cutover_approved')===false) $fail('missing explicit event cutover false in health');
echo "File26 unapproved activation guard PASS (dormant adapter; staging/live unverified)\n";
