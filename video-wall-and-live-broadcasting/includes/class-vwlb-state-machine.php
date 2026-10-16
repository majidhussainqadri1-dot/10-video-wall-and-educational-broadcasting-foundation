<?php
defined( 'ABSPATH' ) || exit;
final class VWLB_State_Machine {
	private static $maps = array(
		'video'=>array('draft'=>array('uploading','review','removed'),'uploading'=>array('scanning','failed','removed'),'scanning'=>array('processing','failed','removed'),'processing'=>array('review','failed','removed'),'review'=>array('scheduled','published','restricted','removed'),'scheduled'=>array('published','restricted','removed'),'published'=>array('restricted','removed'),'restricted'=>array('published','removed'),'failed'=>array('draft','removed')),
		'asset'=>array('initiated'=>array('uploaded','failed'),'uploaded'=>array('verified','quarantined','failed'),'verified'=>array('scanning','transcoding','processing','ready','failed'),'scanning'=>array('transcoding','processing','quarantined','failed'),'transcoding'=>array('ready','failed','quarantined'),'processing'=>array('ready','failed','quarantined'),'failed'=>array('transcoding','processing','deleted'),'quarantined'=>array('verified','deleted'),'ready'=>array('deleted')),
		'live'=>array('draft'=>array('scheduled','removed'),'scheduled'=>array('rehearsal','ready','restricted','removed'),'rehearsal'=>array('ready','scheduled','failed'),'ready'=>array('live','scheduled','failed'),'live'=>array('interrupted','ended','restricted'),'interrupted'=>array('live','ended','failed'),'ended'=>array('recording_processing','replay_review','replay_published','failed'),'recording_processing'=>array('replay_review','failed'),'replay_review'=>array('replay_published','failed','restricted'),'replay_published'=>array('restricted','removed'),'restricted'=>array('scheduled','replay_published','removed'),'failed'=>array('scheduled','removed')),
		'takedown'=>array('reported'=>array('restricted','reviewed','closed'),'restricted'=>array('reviewed','removed','restored','appealed'),'reviewed'=>array('removed','restored','appealed','closed'),'removed'=>array('appealed','restored','closed'),'restored'=>array('closed','reported'),'appealed'=>array('reviewed','removed','restored','closed')),
	);
	public static function allowed( $type, $from, $to ) { return isset(self::$maps[$type][$from]) && in_array($to,self::$maps[$type][$from],true); }
	public static function assert( $type, $from, $to ) { return self::allowed($type,$from,$to) ? true : VWLB_Helpers::error('vwlb_invalid_transition',sprintf(__('Invalid %1$s transition from %2$s to %3$s.',VWLB_TEXT_DOMAIN),$type,$from,$to),409); }
	public static function all() { return self::$maps; }
}
