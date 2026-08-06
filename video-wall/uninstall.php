<?php
/**
 * Uninstall boundary.
 *
 * Video publications, moderation evidence, interaction data, activation
 * snapshots, and rollback records are intentionally retained to prevent
 * accidental or unreviewed data loss. Destructive removal requires a separate
 * audited administrator migration.
 *
 * @package Video_Wall
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
