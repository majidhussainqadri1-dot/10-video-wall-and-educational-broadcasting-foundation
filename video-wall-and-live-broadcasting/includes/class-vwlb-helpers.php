<?php
/** General bounded helpers. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Helpers {
	public static function table( $name ) { global $wpdb; return $wpdb->prefix . 'vwlb_' . sanitize_key( $name ); }
	public static function now() { return current_time( 'mysql', true ); }
	public static function public_id( $prefix ) { return sanitize_key( $prefix ) . '_' . strtolower( wp_generate_password( 24, false, false ) ); }
	public static function trace_id() { return 'f10_' . strtolower( wp_generate_password( 20, false, false ) ); }
	public static function enum( $value, $allowed, $default = '' ) { $value = sanitize_key( (string) $value ); return in_array( $value, $allowed, true ) ? $value : $default; }
	public static function text( $value, $max = 191 ) { return mb_substr( sanitize_text_field( (string) $value ), 0, $max ); }
	public static function textarea( $value, $max = 100000 ) { return mb_substr( wp_kses_post( (string) $value ), 0, $max ); }
	public static function json( $value, $default = array() ) {
		if ( is_array( $value ) ) { return $value; }
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : $default;
	}
	public static function json_encode( $value ) { return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
	public static function datetime( $value, $nullable = true ) {
		if ( empty( $value ) ) { return $nullable ? null : self::now(); }
		$time = strtotime( (string) $value );
		return $time ? gmdate( 'Y-m-d H:i:s', $time ) : null;
	}
	public static function duration_seconds( $value ) {
		if ( is_numeric( $value ) ) { return max( 0, min( DAY_IN_SECONDS, (int) $value ) ); }
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(?:(\d{1,2}):)?([0-5]?\d):([0-5]?\d)$/', $value, $m ) ) { return 0; }
		return min( DAY_IN_SECONDS, ( (int) ( $m[1] ?? 0 ) * HOUR_IN_SECONDS ) + ( (int) $m[2] * MINUTE_IN_SECONDS ) + (int) $m[3] );
	}
	public static function opaque_cursor( $id ) { return rtrim( strtr( base64_encode( (string) absint( $id ) ), '+/', '-_' ), '=' ); }
	public static function cursor_id( $cursor ) { $raw = base64_decode( strtr( (string) $cursor, '-_', '+/' ), true ); return $raw && ctype_digit( $raw ) ? absint( $raw ) : 0; }
	public static function ip_hash() { $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : ''; }
	public static function ua_hash() { $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; return $ua ? hash_hmac( 'sha256', $ua, wp_salt( 'secure_auth' ) ) : ''; }
	public static function no_cache_private() { if ( ! headers_sent() ) { nocache_headers(); header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true ); header( 'X-Robots-Tag: noindex, nofollow, noarchive', true ); } }
	public static function error( $code, $message, $status = 400, $extra = array() ) { return new WP_Error( $code, $message, array_merge( array( 'status' => $status, 'trace_id' => self::trace_id() ), $extra ) ); }
	public static function audit( $object_type, $object_id, $action, $before = '', $after = '', $note = '', $meta = array() ) {
		global $wpdb;
		$wpdb->insert( self::table( 'audit' ), array( 'public_id'=>self::public_id('aud'),'object_type'=>sanitize_key($object_type),'object_id'=>(string)$object_id,'action'=>sanitize_key($action),'previous_state'=>sanitize_key($before),'new_state'=>sanitize_key($after),'actor_id'=>get_current_user_id(),'purpose'=>self::text($meta['purpose'] ?? 'platform_operation',64),'trace_id'=>self::text($meta['trace_id'] ?? self::trace_id(),80),'note'=>self::textarea($note,10000),'meta_json'=>self::json_encode($meta),'created_at'=>self::now() ) );
	}
	public static function outbox( $event_name, $object_type, $object_id, $payload = array() ) {
		global $wpdb;
		$wpdb->insert( self::table( 'outbox' ), array( 'public_id'=>self::public_id('evt'),'event_name'=>VWLB_Contracts::event($event_name),'object_type'=>sanitize_key($object_type),'object_id'=>(string)$object_id,'payload_json'=>self::json_encode($payload),'status'=>'pending','attempts'=>0,'available_at'=>self::now(),'created_at'=>self::now(),'updated_at'=>self::now() ) );
	}
	public static function option_bool( $name, $default = false ) { return (bool) get_option( 'vwlb_' . sanitize_key( $name ), $default ); }
	public static function safe_url( $url, $allowed_hosts = array() ) {
		$url = esc_url_raw( (string) $url, array( 'https' ) ); if ( ! $url ) { return ''; }
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $allowed_hosts && ! in_array( $host, $allowed_hosts, true ) ) { return ''; }
		return $url;
	}
	public static function remote_url( $url, $allowed_hosts = array() ) {
		$url=self::safe_url($url,$allowed_hosts); if(!$url)return '';
		$parts=wp_parse_url($url);$host=strtolower((string)($parts['host']??''));$port=isset($parts['port'])?(int)$parts['port']:443;
		if(!$host||isset($parts['user'])||isset($parts['pass'])||443!==$port)return '';
		if(in_array($host,array('localhost','localhost.localdomain','metadata.google.internal'),true)||str_ends_with($host,'.local')||str_ends_with($host,'.internal'))return '';
		if(filter_var($host,FILTER_VALIDATE_IP)){$flags=FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE;if(false===filter_var($host,FILTER_VALIDATE_IP,$flags))return '';}
		return (string)apply_filters('vwlb_remote_url_allowed',$url,$host);
	}
	public static function datetime_in_timezone($value,$timezone='UTC') {
		if(empty($value))return null; try{$tz=new DateTimeZone($timezone?:'UTC');$dt=new DateTimeImmutable((string)$value,$tz);return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}catch(Throwable $e){return null;}
	}
	public static function iso_utc($value){$time=$value?strtotime((string)$value.' UTC'):false;return $time?gmdate('Y-m-d\\TH:i:s\\Z',$time):null;}
}
