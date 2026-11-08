<?php
/** File 10 current media owner vs File 06: public/rights/consent + review. */
declare(strict_types=1);define('ABSPATH',__DIR__);
class VWLB_Helpers{
	public static function is_public_id($x){return !!preg_match('/^vid_[a-z0-9]+$/',$x);}
	public static function error($c,$m,$status=400){return new WP_Error($c,$m,array('status'=>$status));}
}
class WP_Error{public function __construct($c,$m='',$d=array()){}}
class VWLB_Contracts{const CAP_PUBLISH='vwlb_publish';}
class VWLB_Security{
	public static function can_view($r,$p=''){return !$GLOBALS['rights_withdrawn'];}
	public static function can($c,$obj=null,$purpose=''){return true;}
}
class VWLB_R109_Rights_Consent_Replay_Guard{
	public static function policy_read_failed(){return false;}
}
class VWLB_Repository {
	public static function reset_read_failure(){}
	public static function read_failed(){return false;}
	public static function find($e,$id){return $GLOBALS['video'];}
	public static function public_video_dto($r){return array('url'=>'https://example.org/video/'.$r['public_id']);}
}
function absint($x){return abs((int)$x);}
function sanitize_text_field($x){return trim((string)$x);}
function wp_http_validate_url($x){return str_starts_with($x,'https://');}
function get_option($key,$default){return $GLOBALS['options'][$key]??$default;}
function must($v,$label){$GLOBALS['checks']++;if(!$v){fwrite(STDERR,"FAILED {$label}\n");exit(1);}}
$uuid='11111111-1111-4111-8111-111111111111';$GLOBALS['checks']=0;$GLOBALS['rights_withdrawn']=false;
$GLOBALS['video']=array('public_id'=>'vid_abc01234','version'=>5,'status'=>'published','visibility'=>'public',
	'owner_id'=>8,'rights_status'=>'verified','title'=>'Verified Homeopathy Video','deleted_at'=>null);
require dirname(__DIR__).'/video-wall-and-live-broadcasting/includes/class-vwlb-file06-links.php';
$ref=new ReflectionMethod('VWLB_File06_Links','key');$ref->setAccessible(true);$key=$ref->invoke(null,$uuid);
$GLOBALS['options']=array($key=>array(array('approved'=>true,'concept_public_id'=>$uuid,
	'concept_version'=>3,'video_public_id'=>'vid_abc01234','video_version'=>5,
	'rights_evidence_ref'=>'rights:12345678','reviewer_id'=>4)));
$dto=array('id'=>$uuid,'version'=>3);
$links=VWLB_File06_Links::collect(array(),$dto);
must(count($links)===1,'Current public verified media visible');
must(VWLB_File06_Links::attest(false,$links[0],$dto)===true,'Owner rechecks current video URL and version');
$GLOBALS['rights_withdrawn']=true;
must(VWLB_File06_Links::collect(array(),$dto)===array(),'Rights consent revocation disables video');
$GLOBALS['rights_withdrawn']=false;$GLOBALS['video']['status']='restricted';
must(VWLB_File06_Links::collect(array(),$dto)===array(),'Restricted video never publicly referenced');
$GLOBALS['video']['status']='published';$GLOBALS['video']['visibility']='private';
must(VWLB_File06_Links::collect(array(),$dto)===array(),'Private video cannot leak through File06');
$GLOBALS['video']['visibility']='public';$GLOBALS['video']['version']=6;
must(VWLB_File06_Links::collect(array(),$dto)===array(),'Edited video version invalidates old citation');
$GLOBALS['video']['version']=5;$GLOBALS['video']['rights_status']='revoked';
must(VWLB_File06_Links::collect(array(),$dto)===array(),'Revoked rights invalidates citation');
$GLOBALS['video']['rights_status']='verified';$GLOBALS['options'][$key][0]['reviewer_id']=8;
must(VWLB_File06_Links::collect(array(),$dto)===array(),'Video owner cannot self-review source relation');
echo "File10→File06 rights/consent video links: {$GLOBALS['checks']} isolated checks PASS.\n";
