<?php
/** Stable public contracts and requirement identifiers. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Contracts {
	const API_NAMESPACE = 'vwlb/v1';
	const EVENT_VERSION = 1;
	const CAP_SUBMIT = 'vwlb_submit_video';
	const CAP_PUBLISH = 'vwlb_publish_video';
	const CAP_BROADCAST = 'vwlb_broadcast_live';
	const CAP_MODERATE = 'vwlb_moderate_media';
	const CAP_MANAGE = 'vwlb_manage_platform';
	const CAP_DIAGNOSTICS = 'vwlb_run_diagnostics';
	const VISIBILITIES = array( 'public', 'unlisted', 'member', 'entitled', 'private' );
	const VIDEO_STATES = array( 'draft', 'uploading', 'scanning', 'processing', 'review', 'scheduled', 'published', 'restricted', 'removed', 'failed' );
	const ASSET_STATES = array( 'initiated', 'uploaded', 'verified', 'scanning', 'processing', 'ready', 'failed', 'quarantined', 'deleted' );
	const LIVE_STATES = array( 'draft', 'scheduled', 'rehearsal', 'ready', 'live', 'interrupted', 'ended', 'recording_processing', 'replay_review', 'replay_published', 'failed', 'restricted', 'removed' );
	const TAKEDOWN_STATES = array( 'reported', 'restricted', 'reviewed', 'removed', 'restored', 'appealed', 'closed' );
	const REQUIREMENTS = array(
		'F10-FR-001','F10-FR-002','F10-FR-003','F10-FR-004','F10-FR-005','F10-FR-006','F10-FR-007','F10-FR-008','F10-FR-009','F10-FR-010','F10-FR-011','F10-FR-012','F10-FR-013','F10-FR-014','F10-FR-015','F10-FR-016','F10-FR-017','F10-FR-018','F10-FR-019',
		'F10-NFR-001','F10-NFR-002','F10-NFR-003','F10-NFR-004','F10-NFR-005','F10-NFR-006','F10-NFR-007','F10-NFR-008','F10-NFR-009','F10-NFR-010',
	);
	public static function event( $name ) { return sanitize_key( $name ) . '.v' . self::EVENT_VERSION; }
}
