<?php
/** Stable public contracts and requirement identifiers for File 10. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Contracts {
	const API_NAMESPACE = 'vwlb/v1'; // Backward-compatible alias.
	const CANONICAL_API_NAMESPACE = 'video-wall-live-broadcasting/v1';
	const EVENT_VERSION = 1;

	const CAP_SUBMIT = 'vwlb_submit_video';
	const CAP_PUBLISH = 'vwlb_publish_video';
	const CAP_BROADCAST = 'vwlb_broadcast_live';
	const CAP_MODERATE = 'vwlb_moderate_media';
	const CAP_REVIEW = 'vwlb_review_media';
	const CAP_OPERATE = 'vwlb_operate_media';
	const CAP_MANAGE = 'vwlb_manage_platform';
	const CAP_DIAGNOSTICS = 'vwlb_run_diagnostics';

	const VISIBILITIES = array( 'public', 'unlisted', 'member', 'entitled', 'private' );
	const MEDIA_CLASSES = array( 'video', 'audio', 'podcast', 'image', 'document' );
	const VIDEO_STATES = array( 'draft', 'uploading', 'scanning', 'processing', 'review', 'scheduled', 'published', 'restricted', 'removed', 'failed' );
	// "processing" is retained only as a compatibility state. New processing uses "transcoding".
	const ASSET_STATES = array( 'initiated', 'uploaded', 'verified', 'scanning', 'transcoding', 'processing', 'ready', 'failed', 'quarantined', 'deleted' );
	const LIVE_STATES = array( 'draft', 'scheduled', 'rehearsal', 'ready', 'live', 'interrupted', 'ended', 'recording_processing', 'replay_review', 'replay_published', 'failed', 'restricted', 'removed' );
	const TAKEDOWN_STATES = array( 'reported', 'restricted', 'reviewed', 'removed', 'restored', 'appealed', 'closed' );

	const FUTURE_REQUIREMENTS = array(
		'F10-FUT-001','F10-FUT-002','F10-FUT-003','F10-FUT-004','F10-FUT-005','F10-FUT-006',
		'F10-FUT-007','F10-FUT-008','F10-FUT-009','F10-FUT-010','F10-FUT-011','F10-FUT-012',
		'F10-FUT-013','F10-FUT-014','F10-FUT-015','F10-FUT-016','F10-FUT-017','F10-FUT-018',
		'F10-FUT-019','F10-FUT-020','F10-FUT-021','F10-FUT-022','F10-FUT-023','F10-FUT-024',
	);

	const REQUIREMENTS = array(
		'F10-FR-001','F10-FR-002','F10-FR-003','F10-FR-004','F10-FR-005','F10-FR-006','F10-FR-007','F10-FR-008','F10-FR-009','F10-FR-010','F10-FR-011','F10-FR-012','F10-FR-013','F10-FR-014','F10-FR-015','F10-FR-016','F10-FR-017','F10-FR-018','F10-FR-019',
		'F10-NFR-001','F10-NFR-002','F10-NFR-003','F10-NFR-004','F10-NFR-005','F10-NFR-006','F10-NFR-007','F10-NFR-008','F10-NFR-009','F10-NFR-010',
		'F10-FUT-001','F10-FUT-002','F10-FUT-003','F10-FUT-004','F10-FUT-005','F10-FUT-006','F10-FUT-007','F10-FUT-008','F10-FUT-009','F10-FUT-010','F10-FUT-011','F10-FUT-012','F10-FUT-013','F10-FUT-014','F10-FUT-015','F10-FUT-016','F10-FUT-017','F10-FUT-018','F10-FUT-019','F10-FUT-020','F10-FUT-021','F10-FUT-022','F10-FUT-023','F10-FUT-024',
		'F10-CEN-01','AJ-15','AJ-16','AJ-17',
	);

	/**
	 * Central catalogue items that File 10 either natively owns or must expose as a
	 * bounded consumer/adapter. Ownership labels prevent accidental duplication.
	 */
	const CENTRAL_TRACE = array(
		'CV-107'=>'native','CV-108'=>'native','CV-109'=>'native','CV-110'=>'native',
		'CV-111'=>'native','CV-112'=>'native','CV-113'=>'native','CV-114'=>'native',
		'CV-115'=>'native','CV-116'=>'native','CV-117'=>'native','CV-118'=>'native',
		'CV-119'=>'consumer-file11','CV-120'=>'consumer-file11','CV-121'=>'shared-accessibility',
		'CV-122'=>'consumer-file11','CV-123'=>'consumer-file11','CV-124'=>'consumer-file11',
		'CV-125'=>'native','CV-126'=>'shared-interaction','CV-127'=>'native',
		'CV-128'=>'native','CV-129'=>'shared-youth-safety','CV-218'=>'native-live',
		'CV-219'=>'shared-download','CV-220'=>'shared-file17','CV-225'=>'shared-access',
		'CV-226'=>'native-upload','CV-239'=>'shared-localization','CV-240'=>'shared-localization',
		'CV-241'=>'shared-accessibility','CV-242'=>'native','CV-243'=>'native-ui',
		'CV-244'=>'native-ui','CV-245'=>'native','CV-246'=>'consumer-file20',
		'CV-247'=>'native-ui','CV-248'=>'native-ui','CV-249'=>'shared-accessibility',
		'CV-250'=>'native-safety','CV-251'=>'shared-safety','CV-252'=>'native-safety',
		'CV-253'=>'native-moderation','CV-254'=>'native-moderation','CV-255'=>'native-moderation',
		'CV-256'=>'native-moderation','CV-257'=>'shared-governance','CV-258'=>'native-moderation',
		'CV-259'=>'shared-assurance','CV-260'=>'shared-file17','CV-261'=>'shared-youth-safety',
		'CV-262'=>'native-security','CV-263'=>'native-security','CV-264'=>'native-security',
		'CV-265'=>'native-audit','CV-266'=>'native-privacy','CV-267'=>'native-privacy',
		'CV-268'=>'consumer-file20','CV-269'=>'native-sdlc','CV-270'=>'shared-file24',
		'CV-271'=>'shared-file24','CV-272'=>'native-privacy','CV-273'=>'native-operations',
		'CV-274'=>'native-operations','CV-275'=>'native-performance','CV-276'=>'native-observability',
		'CV-277'=>'native-resilience','CV-278'=>'native-operations','CV-279'=>'native-release',
		'CV-280'=>'native-release','CV-281'=>'shared-support','CV-282'=>'native-capacity',
		'CV-283'=>'native-migration','CV-284'=>'native-provider','CV-285'=>'native-operations',
	);

	const PUBLISHED_EVENTS = array(
		'VideoPublished.v1','VideoRestricted.v1','LiveEventScheduled.v1','LiveBroadcastStarted.v1',
		'LiveBroadcastEnded.v1','LiveReplayPublished.v1','MediaAssetReady.v1','PodcastEpisodePublished.v1',
		'PodcastSeriesPublished.v1','VideoPremiereScheduled.v1','LiveWaitingRoomOpened.v1','LiveRecordingConsentChanged.v1','MediaRightsChanged.v1',
		'BroadcastGuestInvited.v1','LivePollChanged.v1','VideoTimestampCorrectionPublished.v1','VideoFutureAnnotationPublished.v1','MediaAuxiliaryTrackPublished.v1','LiveProductionSceneChanged.v1','LiveSimulcastStateChanged.v1','VideoConsentRestricted.v1',
	);
	const CONSUMED_EVENTS = array(
		'MembershipEntitlementChanged.v1','MessageUserBlocked.v1','CopyrightReportFiled.v1',
		'KnowledgeEntryCorrected.v1','DocumentRightsChanged.v1','GuardianConsentChanged.v1',
	);

	public static function event( $name ) {
		$name = (string) $name;
		if ( preg_match('/^([A-Za-z][A-Za-z0-9]*)\.v([0-9]+)$/',$name,$m) ) {
			return $m[1] . '.v' . (int) $m[2];
		}
		$name = preg_replace( '/[^A-Za-z0-9]/', '', $name );
		if ( '' === $name ) { $name = 'File10Event'; }
		return $name . '.v' . self::EVENT_VERSION;
	}
	public static function namespaces() {
		return array_values( array_unique( array( self::CANONICAL_API_NAMESPACE, self::API_NAMESPACE ) ) );
	}
}
