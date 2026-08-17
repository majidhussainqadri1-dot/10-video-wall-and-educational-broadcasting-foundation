<?php
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('VWLB_TEXT_DOMAIN','video-wall-live-broadcasting');
function __($s){return $s;}
class WP_Error {public $code;public $message;public $data;public function __construct($c,$m,$d=array()){$this->code=$c;$this->message=$m;$this->data=$d;}public function get_error_code(){return $this->code;}}
function is_wp_error($v){return $v instanceof WP_Error;}
class VWLB_Helpers {public static function error($c,$m,$s=400,$e=array()){return new WP_Error($c,$m,array_merge(array('status'=>$s),$e));}}
require __DIR__.'/../video-wall-and-live-broadcasting/includes/class-vwlb-state-machine.php';
$must=array(
 array('video','draft','uploading',true),array('video','published','draft',false),array('asset','uploaded','verified',true),array('asset','ready','initiated',false),
 array('live','scheduled','rehearsal',true),array('live','ready','live',true),array('live','live','ended',true),array('live','ended','live',false),
 array('takedown','reported','restricted',true),array('takedown','removed','appealed',true),array('takedown','closed','reported',false)
);
foreach($must as $case){[$type,$from,$to,$expected]=$case;$actual=VWLB_State_Machine::allowed($type,$from,$to);if($actual!==$expected){fwrite(STDERR,"FAIL $type $from -> $to\n");exit(1);}}
if(!is_wp_error(VWLB_State_Machine::assert('live','ended','live'))){fwrite(STDERR,"FAIL invalid transition did not return error\n");exit(1);}
echo "state-machine tests PASS\n";
