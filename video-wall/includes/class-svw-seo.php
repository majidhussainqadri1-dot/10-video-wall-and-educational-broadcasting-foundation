<?php
/**
 * Search-engine metadata and private-page robots controls.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_SEO {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_head', array( $this, 'schema' ), 40 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
	}

	/**
	 * Output source-correct VideoObject structured data.
	 *
	 * @return void
	 */
	public function schema() {
		if ( ! is_singular( SVW_Helpers::TYPE ) || 'publish' !== get_post_status( get_queried_object_id() ) ) {
			return;
		}

		$post_id    = get_queried_object_id();
		$source     = SVW_Helpers::meta( $post_id, 'source' );
		$duration   = absint( SVW_Helpers::meta( $post_id, 'duration', 0 ) );
		$video_url  = SVW_Helpers::meta( $post_id, 'video_url' );
		$embed_url  = SVW_Helpers::meta( $post_id, 'embed_url' );
		$attachment = absint( SVW_Helpers::meta( $post_id, 'attachment_id', 0 ) );

		$data = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => get_the_title( $post_id ),
			'description'  => get_the_excerpt( $post_id ),
			'uploadDate'   => get_the_date( DATE_W3C, $post_id ),
			'thumbnailUrl' => get_the_post_thumbnail_url( $post_id, 'full' ),
			'transcript'   => SVW_Helpers::meta( $post_id, 'transcript' ),
		);
		if ( $duration > 0 ) {
			$data['duration'] = 'PT' . $duration . 'S';
		}
		if ( 'local' === $source && $attachment ) {
			$content_url = wp_get_attachment_url( $attachment );
			if ( $content_url ) {
				$data['contentUrl'] = $content_url;
			}
		} elseif ( in_array( $source, array( 'youtube', 'vimeo' ), true ) ) {
			if ( $video_url ) {
				$data['contentUrl'] = $video_url;
			}
			if ( $embed_url ) {
				$data['embedUrl'] = $embed_url;
			}
		}

		$data = array_filter(
			$data,
			static function ( $value ) {
				return '' !== $value && null !== $value && array() !== $value;
			}
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}

	/**
	 * Prevent indexing and archiving of private account pages.
	 *
	 * @param array $robots Existing directives.
	 * @return array
	 */
	public function robots( $robots ) {
		$private_pages = SVW_Helpers::private_page_ids();
		if ( $private_pages && is_page( $private_pages ) ) {
			$robots['noindex']   = true;
			$robots['noarchive'] = true;
			$robots['nosnippet'] = true;
		}
		return $robots;
	}
}
