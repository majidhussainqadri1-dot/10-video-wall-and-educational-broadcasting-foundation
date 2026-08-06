<?php
/**
 * Public wall, publishing, single-video, saved, and history interfaces.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_Frontend {
	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_shortcode( 'svw_video_wall', array( $this, 'wall' ) );
		add_shortcode( 'svw_create_video', array( $this, 'form' ) );
		add_shortcode( 'svw_saved_videos', array( $this, 'saved' ) );
		add_shortcode( 'svw_video_history', array( $this, 'history' ) );
		add_action( 'admin_post_svw_submit_video', array( $this, 'precheck' ), 9 );
		add_action( 'admin_post_svw_submit_video', array( $this, 'submit' ) );
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
		add_filter( 'the_content', array( $this, 'single' ), 20 );
		add_action( 'pre_comment_on_post', array( $this, 'comment_guard' ) );
	}

	/**
	 * Render the public Video Wall.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function wall( $atts = array() ) {
		unset( $atts );
		$search = isset( $_GET['video_search'] ) ? sanitize_text_field( wp_unslash( $_GET['video_search'] ) ) : '';
		$cat    = isset( $_GET['video_category'] ) ? sanitize_title( wp_unslash( $_GET['video_category'] ) ) : '';
		$sort   = isset( $_GET['video_sort'] ) ? sanitize_key( wp_unslash( $_GET['video_sort'] ) ) : 'trending';
		$page   = isset( $_GET['video_page'] ) ? max( 1, absint( $_GET['video_page'] ) ) : 1;
		if ( ! in_array( $sort, array( 'trending', 'latest', 'viewed', 'liked' ), true ) ) {
			$sort = 'trending';
		}

		$args = array(
			'post_type'           => SVW_Helpers::TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => 12,
			'paged'               => $page,
			's'                   => $search,
			'ignore_sticky_posts' => true,
		);
		if ( $cat && isset( SVW_Helpers::categories()[ $cat ] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => SVW_Helpers::TAX,
					'field'    => 'slug',
					'terms'    => $cat,
				),
			);
		}
		if ( 'latest' === $sort ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} elseif ( 'viewed' === $sort ) {
			$args['meta_key'] = '_svw_views';
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
		} elseif ( 'liked' === $sort ) {
			$args['meta_key'] = '_svw_likes';
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
		} else {
			$args['meta_key'] = '_svw_score';
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
		}

		$query    = new WP_Query( $args );
		$video_ids = wp_list_pluck( $query->posts, 'ID' );
		$states   = SVW_Interactions::states_for_videos( $video_ids );
		$featured = get_posts(
			array(
				'post_type'      => SVW_Helpers::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_svw_featured',
				'meta_value'     => '1',
			)
		);
		$map = (array) get_option( 'svw_page_map', array() );

		ob_start();
		?>
		<main class="svw-shell">
			<?php echo SVW_Helpers::nav(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<header class="svw-hero">
				<div>
					<span>Global Educational Broadcasting</span>
					<h1>Video Wall</h1>
					<p>Watch responsible educational videos freely. Log in to react, comment, save, report, or use viewing history.</p>
				</div>
				<form method="get" role="search">
					<label class="screen-reader-text" for="svw-video-search">Search educational videos</label>
					<input id="svw-video-search" type="search" name="video_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search educational videos">
					<button type="submit">Search</button>
				</form>
			</header>

			<?php if ( $featured ) : ?>
				<section class="svw-featured" aria-labelledby="svw-featured-heading">
					<?php echo SVW_Helpers::embed( $featured[0]->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div>
						<span>Featured Video</span>
						<h2 id="svw-featured-heading"><a href="<?php echo esc_url( get_permalink( $featured[0] ) ); ?>"><?php echo esc_html( $featured[0]->post_title ); ?></a></h2>
						<p><?php echo esc_html( $featured[0]->post_excerpt ); ?></p>
					</div>
				</section>
			<?php endif; ?>

			<nav class="svw-categories" aria-label="Video categories">
				<a href="<?php echo esc_url( remove_query_arg( array( 'video_category', 'video_page' ) ) ); ?>" <?php echo '' === $cat ? 'aria-current="page" class="is-active"' : ''; ?>>All</a>
				<?php foreach ( SVW_Helpers::categories() as $slug => $name ) : ?>
					<a class="<?php echo $cat === $slug ? 'is-active' : ''; ?>" <?php echo $cat === $slug ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( add_query_arg( array( 'video_category' => $slug, 'video_page' => false ) ) ); ?>"><?php echo esc_html( $name ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form class="svw-filters" method="get">
				<input type="hidden" name="video_search" value="<?php echo esc_attr( $search ); ?>">
				<label>Category
					<select name="video_category">
						<option value="">All categories</option>
						<?php foreach ( SVW_Helpers::categories() as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cat, $slug ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Order
					<select name="video_sort">
						<option value="trending" <?php selected( $sort, 'trending' ); ?>>Trending</option>
						<option value="latest" <?php selected( $sort, 'latest' ); ?>>Latest</option>
						<option value="viewed" <?php selected( $sort, 'viewed' ); ?>>Most viewed</option>
						<option value="liked" <?php selected( $sort, 'liked' ); ?>>Most liked</option>
					</select>
				</label>
				<button class="svw-button" type="submit">Apply</button>
				<?php if ( is_user_logged_in() && ! empty( $map['saved'] ) ) : ?>
					<a class="svw-button svw-button-secondary" href="<?php echo esc_url( get_permalink( $map['saved'] ) ); ?>">Saved Videos</a>
				<?php endif; ?>
				<?php if ( is_user_logged_in() && ! empty( $map['history'] ) ) : ?>
					<a class="svw-button svw-button-secondary" href="<?php echo esc_url( get_permalink( $map['history'] ) ); ?>">Video History</a>
				<?php endif; ?>
				<?php if ( SVW_Helpers::can_submit() && ! empty( $map['create'] ) ) : ?>
					<a class="svw-button" href="<?php echo esc_url( get_permalink( $map['create'] ) ); ?>">Create Video Publication</a>
				<?php endif; ?>
			</form>

			<section class="svw-section" aria-labelledby="svw-list-heading">
				<h2 id="svw-list-heading"><?php echo $search ? 'Search Results' : 'Educational Videos'; ?></h2>
				<div class="svw-grid">
					<?php
					if ( $query->have_posts() ) {
						foreach ( $query->posts as $video ) {
							echo $this->card( $video, isset( $states[ $video->ID ] ) ? $states[ $video->ID ] : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					} else {
						echo '<div class="svw-empty">No videos matched your search.</div>';
					}
					?>
				</div>
				<?php echo $this->pagination( $page, (int) $query->max_num_pages ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
			<p class="svw-disclaimer">Educational content only. Videos do not provide emergency care, personal diagnosis, prescriptions, or guaranteed outcomes.</p>
		</main>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Render a video card.
	 *
	 * @param WP_Post    $video Video post.
	 * @param array|null $state Optional interaction state.
	 * @return string
	 */
	private function card( $video, $state = null ) {
		$id    = absint( $video->ID );
		$thumb = get_the_post_thumbnail( $id, 'medium_large', array( 'loading' => 'lazy', 'alt' => $video->post_title ) );
		ob_start();
		?>
		<article class="svw-card" data-video-id="<?php echo absint( $id ); ?>">
			<a class="svw-thumb" href="<?php echo esc_url( get_permalink( $id ) ); ?>">
				<?php echo $thumb ? $thumb : '<span aria-hidden="true">▶</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<b><?php echo esc_html( SVW_Helpers::duration( SVW_Helpers::meta( $id, 'duration' ) ) ); ?></b>
			</a>
			<div>
				<span><?php echo esc_html( SVW_Helpers::category( $id ) ); ?></span>
				<h3><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( $video->post_title ); ?></a></h3>
				<p><?php echo esc_html( get_the_author_meta( 'display_name', $video->post_author ) ); ?> · <?php echo absint( SVW_Helpers::meta( $id, 'views', 0 ) ); ?> views</p>
				<?php echo SVW_Interactions::buttons( $id, $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the controlled publishing form.
	 *
	 * @return string
	 */
	public function form() {
		if ( ! is_user_logged_in() ) {
			return '<div class="svw-notice"><a class="svw-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log In to Submit a Video</a></div>';
		}
		if ( ! SVW_Helpers::can_submit() ) {
			return '<div class="svw-notice">Only the Founder, administrators, and currently verified doctors may submit videos.</div>';
		}

		$submitted = isset( $_GET['submitted'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['submitted'] ) );
		ob_start();
		?>
		<main class="svw-shell">
			<header class="svw-page-head">
				<span>Video Wall Publishing</span>
				<h1>Create Video Publication</h1>
				<p>All public information must be written in American English and remain within an approved educational category.</p>
			</header>
			<?php if ( $submitted ) : ?>
				<div class="svw-notice" role="status">The video publication was received successfully.</div>
			<?php endif; ?>
			<form class="svw-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="svw_submit_video">
				<?php wp_nonce_field( 'svw_submit_video', 'svw_nonce' ); ?>
				<label>Video title<input name="title" maxlength="180" required></label>
				<label>Short description<textarea name="excerpt" maxlength="500" required></textarea></label>
				<label class="svw-wide">Complete description<textarea name="description" required></textarea></label>
				<label>Video category<select name="category" required><?php foreach ( SVW_Helpers::categories() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
				<label>Video source<select name="source" required><option value="youtube">YouTube</option><option value="vimeo">Vimeo</option><option value="local">Local video upload</option></select></label>
				<label>Video URL<input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..."></label>
				<label>Local video<input type="file" name="local_video" accept="video/mp4,video/webm,video/ogg"><small>Maximum 200 MB or the lower server limit.</small></label>
				<label>Featured image<input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" required></label>
				<label>Duration<input name="duration" placeholder="00:15:30" pattern="[0-9]{1,3}:[0-5][0-9]:[0-5][0-9]" required></label>
				<label>Video language<input name="language" value="English" required></label>
				<label class="svw-wide">Educational transcript<textarea name="transcript" required></textarea></label>
				<label class="svw-wide">References<textarea name="references" required></textarea></label>
				<label class="svw-wide">Medical safety notice<textarea name="safety" required></textarea></label>
				<label class="svw-check svw-wide"><input type="checkbox" name="copyright" value="1" required> I own this video or have permission to publish every included element.</label>
				<label class="svw-check svw-wide"><input type="checkbox" name="medical" value="1" required> This video is educational, avoids cure guarantees and personal prescriptions, and does not delay emergency care.</label>
				<label class="svw-check svw-wide"><input type="checkbox" name="case_anonymized" value="1"> If this is a Patient Case, identifying information has been removed.</label>
				<label class="svw-check svw-wide"><input type="checkbox" name="case_consent" value="1"> If this is a Patient Case, valid publication consent has been obtained.</label>
				<button class="svw-button" type="submit"><?php echo 'publish' === SVW_Helpers::initial_status() ? 'Publish Video' : 'Submit for Review'; ?></button>
			</form>
		</main>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate and create a publication atomically.
	 *
	 * @return void
	 */
	public function submit() {
		if ( ! is_user_logged_in() || ! SVW_Helpers::can_submit() ) {
			wp_die( esc_html__( 'Video publishing is restricted.', 'video-wall' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'svw_submit_video', 'svw_nonce' );

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$excerpt     = isset( $_POST['excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ) ) : '';
		$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$category    = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';
		$source      = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$language    = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
		$transcript  = isset( $_POST['transcript'] ) ? sanitize_textarea_field( wp_unslash( $_POST['transcript'] ) ) : '';
		$references  = isset( $_POST['references'] ) ? sanitize_textarea_field( wp_unslash( $_POST['references'] ) ) : '';
		$safety      = isset( $_POST['safety'] ) ? sanitize_textarea_field( wp_unslash( $_POST['safety'] ) ) : '';
		$duration    = SVW_Helpers::parse_duration( isset( $_POST['duration'] ) ? wp_unslash( $_POST['duration'] ) : '' );

		if ( ! $title || ! $excerpt || ! trim( wp_strip_all_tags( $description ) ) || ! isset( SVW_Helpers::categories()[ $category ] ) || ! in_array( $source, array( 'youtube', 'vimeo', 'local' ), true ) || ! $duration || ! $language || ! $transcript || ! $references || ! $safety || empty( $_POST['copyright'] ) || empty( $_POST['medical'] ) ) {
			wp_die( esc_html__( 'Complete all required publishing fields with valid values.', 'video-wall' ), '', array( 'response' => 400 ) );
		}
		if ( in_array( $category, array( 'founder-update', 'platform-news' ), true ) && ! SVW_Helpers::founder() && ! current_user_can( 'manage_video_wall' ) ) {
			wp_die( esc_html__( 'That category is reserved for official publishing.', 'video-wall' ), '', array( 'response' => 403 ) );
		}
		if ( 'patient-cases' === $category && ( empty( $_POST['case_anonymized'] ) || empty( $_POST['case_consent'] ) ) ) {
			wp_die( esc_html__( 'Patient Cases require anonymization and valid publication consent.', 'video-wall' ), '', array( 'response' => 400 ) );
		}

		$video_url = '';
		$embed_url = '';
		if ( in_array( $source, array( 'youtube', 'vimeo' ), true ) ) {
			$normalized = SVW_Helpers::normalize_external_url( $source, isset( $_POST['video_url'] ) ? wp_unslash( $_POST['video_url'] ) : '' );
			if ( is_wp_error( $normalized ) ) {
				wp_die( esc_html( $normalized->get_error_message() ), '', array( 'response' => 400 ) );
			}
			$video_url = $normalized['canonical'];
			$embed_url = $normalized['embed'];
		} elseif ( empty( $_FILES['local_video']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Choose a local video file.', 'video-wall' ), '', array( 'response' => 400 ) );
		}

		if ( empty( $_FILES['thumbnail']['tmp_name'] ) ) {
			wp_die( esc_html__( 'A featured image is required.', 'video-wall' ), '', array( 'response' => 400 ) );
		}

		$server_limit = (int) wp_max_upload_size();
		$video_limit  = min( 200 * MB_IN_BYTES, $server_limit );
		$image_limit  = min( 10 * MB_IN_BYTES, $server_limit );
		if ( 'local' === $source && ( empty( $_FILES['local_video']['size'] ) || (int) $_FILES['local_video']['size'] > $video_limit ) ) {
			wp_die( esc_html__( 'The local video exceeds the permitted upload size.', 'video-wall' ), '', array( 'response' => 400 ) );
		}
		if ( empty( $_FILES['thumbnail']['size'] ) || (int) $_FILES['thumbnail']['size'] > $image_limit ) {
			wp_die( esc_html__( 'The featured image exceeds the permitted upload size.', 'video-wall' ), '', array( 'response' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachments = array();
		$local_id    = 0;
		$thumb_id    = 0;

		if ( 'local' === $source ) {
			$local_id = media_handle_upload(
				'local_video',
				0,
				array(),
				array(
					'test_form' => false,
					'mimes'     => array(
						'mp4'  => 'video/mp4',
						'webm' => 'video/webm',
						'ogv'  => 'video/ogg',
					),
				)
			);
			if ( is_wp_error( $local_id ) ) {
				wp_die( esc_html( $local_id->get_error_message() ), '', array( 'response' => 400 ) );
			}
			$attachments[] = absint( $local_id );
		}

		$thumb_id = media_handle_upload(
			'thumbnail',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
				),
			)
		);
		if ( is_wp_error( $thumb_id ) ) {
			$this->cleanup_attachments( $attachments );
			wp_die( esc_html( $thumb_id->get_error_message() ), '', array( 'response' => 400 ) );
		}
		$attachments[] = absint( $thumb_id );

		$post_id = wp_insert_post(
			array(
				'post_type'    => SVW_Helpers::TYPE,
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_excerpt' => $excerpt,
				'post_content' => $description,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			$this->cleanup_attachments( $attachments );
			wp_die( esc_html( $post_id->get_error_message() ), '', array( 'response' => 500 ) );
		}

		$failed = false;
		$meta   = array(
			'source'         => $source,
			'video_url'      => $video_url,
			'embed_url'      => $embed_url,
			'attachment_id'  => absint( $local_id ),
			'duration'       => absint( $duration ),
			'language'       => $language,
			'transcript'     => $transcript,
			'references'     => $references,
			'safety'         => $safety,
			'copyright'      => '1',
			'medical'        => '1',
			'case_anonymized'=> empty( $_POST['case_anonymized'] ) ? '0' : '1',
			'case_consent'   => empty( $_POST['case_consent'] ) ? '0' : '1',
			'views'          => 0,
			'likes'          => 0,
			'dislikes'       => 0,
			'score'          => 0,
		);
		foreach ( $meta as $key => $value ) {
			if ( false === update_post_meta( $post_id, '_svw_' . $key, $value ) && ! metadata_exists( 'post', $post_id, '_svw_' . $key ) ) {
				$failed = true;
				break;
			}
		}

		$term_result = wp_set_object_terms( $post_id, $category, SVW_Helpers::TAX, false );
		if ( is_wp_error( $term_result ) || ! set_post_thumbnail( $post_id, $thumb_id ) ) {
			$failed = true;
		}

		foreach ( $attachments as $attachment_id ) {
			wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $post_id ) );
		}

		$status = SVW_Helpers::initial_status();
		if ( ! $failed ) {
			$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true );
			$failed = is_wp_error( $result );
		}

		if ( $failed ) {
			wp_delete_post( $post_id, true );
			$this->cleanup_attachments( $attachments );
			wp_die( esc_html__( 'The publication could not be completed. No partial public item was retained.', 'video-wall' ), '', array( 'response' => 500 ) );
		}

		SVW_Helpers::audit( 'video', $post_id, 'submitted', '', $status, '', array( 'source' => $source, 'category' => $category ) );
		$key = 'svw_submit_' . get_current_user_id();
		set_transient( $key, absint( get_transient( $key ) ) + 1, HOUR_IN_SECONDS );

		$map = (array) get_option( 'svw_page_map', array() );
		wp_safe_redirect( add_query_arg( 'submitted', '1', ! empty( $map['create'] ) ? get_permalink( $map['create'] ) : home_url( '/' ) ) );
		exit;
	}

	/**
	 * Render the full single-video experience.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function single( $content ) {
		if ( ! is_singular( SVW_Helpers::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$id      = get_the_ID();
		$author  = absint( get_post_field( 'post_author', $id ) );
		$phone   = class_exists( 'SPD_Helpers' ) ? SPD_Helpers::get( $author, 'phone' ) : '';
		$whatsapp = class_exists( 'SPD_Helpers' ) ? SPD_Helpers::get( $author, 'whatsapp' ) : '';
		$contacts = '';
		if ( class_exists( 'SDD_Helpers' ) && SDD_Helpers::contact_is_public( $author, 'phone' ) && $phone ) {
			$contacts .= '<a href="tel:' . esc_attr( SDD_Helpers::phone( $phone ) ) . '">Phone</a>';
		}
		if ( class_exists( 'SDD_Helpers' ) && SDD_Helpers::contact_is_public( $author, 'whatsapp' ) && $whatsapp ) {
			$contacts .= '<a href="' . esc_url( SDD_Helpers::whatsapp( $whatsapp ) ) . '" target="_blank" rel="noopener noreferrer">WhatsApp</a>';
		}

		$related_args = array(
			'post_type'      => SVW_Helpers::TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'post__not_in'   => array( $id ),
		);
		$category_slug = SVW_Helpers::category_slug( $id );
		if ( $category_slug ) {
			$related_args['tax_query'] = array(
				array(
					'taxonomy' => SVW_Helpers::TAX,
					'field'    => 'slug',
					'terms'    => $category_slug,
				),
			);
		}
		$related = get_posts( $related_args );

		$profile_url = class_exists( 'SDD_Helpers' ) ? SDD_Helpers::profile_url( $author ) : get_author_posts_url( $author );
		$tail = '<section class="svw-author"><div><b>' . esc_html( get_the_author_meta( 'display_name', $author ) ) . '</b><span>' . esc_html( SVW_Helpers::author_label( $author ) ) . '</span></div><a href="' . esc_url( $profile_url ) . '">View Profile</a>' . $contacts . '</section>';
		$tail .= SVW_Interactions::buttons( $id );
		$tail .= '<section class="svw-panel"><h2>Educational Transcript</h2><p>' . nl2br( esc_html( SVW_Helpers::meta( $id, 'transcript' ) ) ) . '</p></section>';
		$tail .= '<section class="svw-panel"><h2>References</h2><p>' . nl2br( esc_html( SVW_Helpers::meta( $id, 'references' ) ) ) . '</p></section>';
		$tail .= '<section class="svw-panel svw-safety"><h2>Medical Safety Notice</h2><p>' . nl2br( esc_html( SVW_Helpers::meta( $id, 'safety' ) ) ) . '</p></section>';
		if ( $related ) {
			$tail .= '<section class="svw-panel"><h2>Related Videos</h2><ul>';
			foreach ( $related as $video ) {
				$tail .= '<li><a href="' . esc_url( get_permalink( $video ) ) . '">' . esc_html( $video->post_title ) . '</a></li>';
			}
			$tail .= '</ul></section>';
		}

		return '<div class="svw-player">' . SVW_Helpers::embed( $id ) . '</div><div class="svw-video-body">' . $content . $tail . '</div>';
	}

	/**
	 * Render saved videos.
	 *
	 * @return string
	 */
	public function saved() {
		if ( ! is_user_logged_in() ) {
			return '<div class="svw-notice">Log in to view Saved Videos.</div>';
		}
		return $this->private_listing( 'saved', 'Saved Videos' );
	}

	/**
	 * Render viewing history.
	 *
	 * @return string
	 */
	public function history() {
		if ( ! is_user_logged_in() ) {
			return '<div class="svw-notice">Log in to view Video History.</div>';
		}
		return $this->private_listing( 'history', 'Video History' );
	}

	/**
	 * Build a paginated private video listing.
	 *
	 * @param string $type  saved|history.
	 * @param string $title Page title.
	 * @return string
	 */
	private function private_listing( $type, $title ) {
		global $wpdb;
		$page   = isset( $_GET['video_page'] ) ? max( 1, absint( $_GET['video_page'] ) ) : 1;
		$limit  = 12;
		$offset = ( $page - 1 ) * $limit;
		$table  = 'saved' === $type ? $wpdb->prefix . 'svw_saves' : $wpdb->prefix . 'svw_history';
		$order  = 'saved' === $type ? 'updated_at' : 'updated_at';
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", get_current_user_id() ) );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT video_id, progress, updated_at FROM {$table} WHERE user_id = %d ORDER BY {$order} DESC LIMIT %d OFFSET %d",
				get_current_user_id(),
				$limit,
				$offset
			)
		);
		$ids    = wp_list_pluck( $rows, 'video_id' );
		$items  = get_posts(
			array(
				'post_type'      => SVW_Helpers::TYPE,
				'post_status'    => 'publish',
				'post__in'       => $ids ? array_map( 'absint', $ids ) : array( 0 ),
				'orderby'        => 'post__in',
				'posts_per_page' => $limit,
			)
		);
		$states = SVW_Interactions::states_for_videos( wp_list_pluck( $items, 'ID' ) );

		ob_start();
		?>
		<main class="svw-shell">
			<h1><?php echo esc_html( $title ); ?></h1>
			<div class="svw-grid">
				<?php
				if ( $items ) {
					foreach ( $items as $video ) {
						echo $this->card( $video, isset( $states[ $video->ID ] ) ? $states[ $video->ID ] : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				} else {
					echo '<div class="svw-empty">No videos are available here yet.</div>';
				}
				?>
			</div>
			<?php echo $this->pagination( $page, (int) ceil( $total / $limit ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</main>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send strict privacy headers for private account pages.
	 *
	 * @return void
	 */
	public function private_headers() {
		$private_pages = SVW_Helpers::private_page_ids();
		if ( empty( $private_pages ) || ! is_page( $private_pages ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Robots-Tag: noindex, noarchive, nosnippet', true );
	}

	/**
	 * Require login before commenting on a video.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function comment_guard( $post_id ) {
		if ( SVW_Helpers::TYPE === get_post_type( $post_id ) && ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in to comment on videos.', 'video-wall' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
	}

	/**
	 * Perform inexpensive pre-upload validation and rate limiting.
	 *
	 * @return void
	 */
	public function precheck() {
		check_admin_referer( 'svw_submit_video', 'svw_nonce' );
		if ( ! is_user_logged_in() || ! SVW_Helpers::can_submit() ) {
			wp_die( esc_html__( 'Video publishing is restricted.', 'video-wall' ), '', array( 'response' => 403 ) );
		}

		$required = array( 'title', 'excerpt', 'description', 'transcript', 'references', 'safety', 'duration', 'language' );
		$text = '';
		foreach ( $required as $key ) {
			$value = isset( $_POST[ $key ] ) ? wp_strip_all_tags( wp_unslash( $_POST[ $key ] ) ) : '';
			if ( ! trim( $value ) ) {
				wp_die( esc_html__( 'Complete every required video field.', 'video-wall' ), '', array( 'response' => 400 ) );
			}
			$text .= ' ' . $value;
		}
		if ( preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{0900}-\x{097F}\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}]/u', $text ) ) {
			wp_die( esc_html__( 'This release accepts American English public content only.', 'video-wall' ), '', array( 'response' => 400 ) );
		}
		if ( empty( $_FILES['thumbnail']['tmp_name'] ) ) {
			wp_die( esc_html__( 'A featured image is required.', 'video-wall' ), '', array( 'response' => 400 ) );
		}
		$key = 'svw_submit_' . get_current_user_id();
		if ( absint( get_transient( $key ) ) >= 10 ) {
			wp_die( esc_html__( 'Please wait before submitting another video.', 'video-wall' ), '', array( 'response' => 429 ) );
		}
	}

	/**
	 * Render query-string pagination.
	 *
	 * @param int $current Current page.
	 * @param int $total   Total pages.
	 * @return string
	 */
	private function pagination( $current, $total ) {
		if ( $total <= 1 ) {
			return '';
		}
		$links = paginate_links(
			array(
				'base'      => esc_url_raw( add_query_arg( 'video_page', '%#%' ) ),
				'format'    => '',
				'current'   => max( 1, absint( $current ) ),
				'total'     => max( 1, absint( $total ) ),
				'type'      => 'list',
				'prev_text' => 'Previous',
				'next_text' => 'Next',
			)
		);
		return $links ? '<nav class="svw-pagination" aria-label="Video pages">' . wp_kses_post( $links ) . '</nav>' : '';
	}

	/**
	 * Permanently remove uploaded attachments after a failed transaction.
	 *
	 * @param int[] $attachment_ids Attachment IDs.
	 * @return void
	 */
	private function cleanup_attachments( $attachment_ids ) {
		foreach ( array_filter( array_map( 'absint', (array) $attachment_ids ) ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
