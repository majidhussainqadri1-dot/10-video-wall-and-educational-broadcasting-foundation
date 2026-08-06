<?php
/**
 * WordPress privacy export and erasure integration.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_Privacy {
	const PAGE_SIZE = 100;

	/**
	 * Register privacy callbacks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	/**
	 * Register exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function exporters( $exporters ) {
		$exporters['video-wall'] = array(
			'exporter_friendly_name' => 'Video Wall',
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Export reactions, saves, history, reports, and moderation audit records.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		global $wpdb;
		$page   = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * self::PAGE_SIZE;
		$items  = array();

		$union = $wpdb->prepare(
			"(SELECT 'reaction' AS record_type, id, video_id AS object_id, reaction AS value_one, '' AS value_two, created_at AS record_date FROM {$wpdb->prefix}svw_reactions WHERE user_id = %d)
			UNION ALL
			(SELECT 'save' AS record_type, id, video_id AS object_id, CAST(progress AS CHAR) AS value_one, '' AS value_two, updated_at AS record_date FROM {$wpdb->prefix}svw_saves WHERE user_id = %d)
			UNION ALL
			(SELECT 'history' AS record_type, id, video_id AS object_id, CAST(progress AS CHAR) AS value_one, CONCAT(duration, ':', completed) AS value_two, updated_at AS record_date FROM {$wpdb->prefix}svw_history WHERE user_id = %d)
			UNION ALL
			(SELECT 'report' AS record_type, id, video_id AS object_id, reason AS value_one, CONCAT(status, ':', details) AS value_two, created_at AS record_date FROM {$wpdb->prefix}svw_reports WHERE user_id = %d)
			UNION ALL
			(SELECT 'audit' AS record_type, id, object_id, action AS value_one, CONCAT(previous_state, ':', new_state, ':', note) AS value_two, created_at AS record_date FROM {$wpdb->prefix}svw_audit WHERE actor_id = %d)
			ORDER BY record_date DESC LIMIT %d OFFSET %d",
			$user->ID,
			$user->ID,
			$user->ID,
			$user->ID,
			$user->ID,
			self::PAGE_SIZE,
			$offset
		);

		$rows = (array) $wpdb->get_results( $union );
		foreach ( $rows as $row ) {
			$data = array(
				array( 'name' => 'Record type', 'value' => ucfirst( $row->record_type ) ),
				array( 'name' => 'Date', 'value' => $row->record_date ),
			);
			if ( in_array( $row->record_type, array( 'reaction', 'save', 'history', 'report' ), true ) ) {
				$data[] = array( 'name' => 'Video', 'value' => get_the_title( $row->object_id ) );
			}
			if ( 'reaction' === $row->record_type ) {
				$data[] = array( 'name' => 'Reaction', 'value' => $row->value_one );
			} elseif ( 'save' === $row->record_type ) {
				$data[] = array( 'name' => 'Saved progress in seconds', 'value' => $row->value_one );
			} elseif ( 'history' === $row->record_type ) {
				list( $duration, $completed ) = array_pad( explode( ':', $row->value_two, 2 ), 2, '' );
				$data[] = array( 'name' => 'Progress in seconds', 'value' => $row->value_one );
				$data[] = array( 'name' => 'Duration in seconds', 'value' => $duration );
				$data[] = array( 'name' => 'Completed', 'value' => '1' === $completed ? 'Yes' : 'No' );
			} elseif ( 'report' === $row->record_type ) {
				list( $status, $details ) = array_pad( explode( ':', $row->value_two, 2 ), 2, '' );
				$data[] = array( 'name' => 'Reason', 'value' => $row->value_one );
				$data[] = array( 'name' => 'Status', 'value' => $status );
				$data[] = array( 'name' => 'Details', 'value' => $details );
			} else {
				$data[] = array( 'name' => 'Audit action', 'value' => $row->value_one );
				$data[] = array( 'name' => 'Audit detail', 'value' => $row->value_two );
			}

			$items[] = array(
				'group_id'    => 'video-wall',
				'group_label' => 'Video Wall',
				'item_id'     => $row->record_type . '-' . absint( $row->id ),
				'data'        => $data,
			);
		}

		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Register eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function erasers( $erasers ) {
		$erasers['video-wall'] = array(
			'eraser_friendly_name' => 'Video Wall',
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Erase private interactions and anonymize records retained for safety/audit.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		unset( $page );
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;
		$video_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT video_id FROM {$wpdb->prefix}svw_reactions WHERE user_id = %d", $user->ID )
		);
		$removed = false;
		$removed = (bool) $wpdb->delete( $wpdb->prefix . 'svw_reactions', array( 'user_id' => $user->ID ), array( '%d' ) ) || $removed;
		$removed = (bool) $wpdb->delete( $wpdb->prefix . 'svw_saves', array( 'user_id' => $user->ID ), array( '%d' ) ) || $removed;
		$removed = (bool) $wpdb->delete( $wpdb->prefix . 'svw_history', array( 'user_id' => $user->ID ), array( '%d' ) ) || $removed;

		$reports = $wpdb->update(
			$wpdb->prefix . 'svw_reports',
			array( 'user_id' => 0, 'details' => '[Removed through privacy request]' ),
			array( 'user_id' => $user->ID ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		$audits = $wpdb->update(
			$wpdb->prefix . 'svw_audit',
			array( 'actor_id' => 0 ),
			array( 'actor_id' => $user->ID ),
			array( '%d' ),
			array( '%d' )
		);

		foreach ( $video_ids as $video_id ) {
			SVW_Interactions::recount_video( $video_id );
		}

		$retained = (int) $reports > 0 || (int) $audits > 0;
		$messages = array();
		if ( $retained ) {
			$messages[] = 'Safety reports and moderation audit records were retained in anonymized form to preserve platform accountability.';
		}

		return array(
			'items_removed'  => $removed || $retained,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}
}
