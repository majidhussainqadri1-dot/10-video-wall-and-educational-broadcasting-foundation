<?php
/**
 * Minimal isolated helper tests; no WordPress installation is required.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function __( $value ) { return $value; }

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

require dirname( __DIR__ ) . '/video-wall/includes/class-svw-helpers.php';

function assert_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
	echo "PASS: {$label}\n";
}

assert_same( 930, SVW_Helpers::parse_duration( '00:15:30' ), 'strict duration parses' );
assert_same( false, SVW_Helpers::parse_duration( '00:99:00' ), 'invalid minutes rejected' );
assert_same( false, SVW_Helpers::parse_duration( '15:30' ), 'non-HH:MM:SS rejected' );
assert_same( '00:15:30', SVW_Helpers::duration( 930 ), 'duration formats' );

$youtube = SVW_Helpers::normalize_external_url( 'youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
assert_same( false, $youtube instanceof WP_Error, 'official YouTube URL accepted' );
assert_same( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $youtube['embed'], 'YouTube privacy embed normalized' );

$fake_youtube = SVW_Helpers::normalize_external_url( 'youtube', 'https://evil.example/?next=youtube.com/watch?v=dQw4w9WgXcQ' );
assert_same( true, $fake_youtube instanceof WP_Error, 'fake YouTube hostname rejected' );

$vimeo = SVW_Helpers::normalize_external_url( 'vimeo', 'https://vimeo.com/123456789' );
assert_same( false, $vimeo instanceof WP_Error, 'official Vimeo URL accepted' );
assert_same( 'https://player.vimeo.com/video/123456789', $vimeo['embed'], 'Vimeo embed normalized' );

echo "All helper tests passed.\n";
