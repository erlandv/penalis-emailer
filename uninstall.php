<?php
/**
 * Plugin Uninstall
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all plugin data: custom tables, options, post meta,
 * and scheduled cron events.
 *
 * This file is executed by WordPress directly — NOT via include/require.
 * WordPress verifies WP_UNINSTALL_PLUGIN is defined before running this file,
 * which ensures it can only be triggered through the proper uninstall flow.
 *
 * @package Penalis_Emailer
 * @since 2.0.0
 */

// Prevent direct access — must be called by WordPress uninstall process
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// ============================================================================
// 1. Drop custom tables
// ============================================================================

$tables = [
    $wpdb->prefix . 'penalis_email_log',
    $wpdb->prefix . 'penalis_email_queue',
    $wpdb->prefix . 'penalis_email_draft',
];

foreach ($tables as $table) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// ============================================================================
// 2. Delete all plugin options
// ============================================================================

$options = [
    // Template settings
    'penalis_auto_email_body',
    'penalis_auto_email_body_modified_time',
    'penalis_auto_email_body_modified_by',

    // Legacy log (wp_options based, pre-v2.0.0)
    'penalis_manual_emails_log',

    // Queue settings
    'penalis_queue_batch_size',
    'penalis_queue_interval',
    'penalis_queue_max_attempts',
    'penalis_queue_throttle_delay',

    // Database schema version
    'penalis_db_schema_version',
    'penalis_migration_v2_done',
];

foreach ($options as $option) {
    delete_option($option);
}

// ============================================================================
// 3. Delete post meta (email-sent tracking for automatic emails)
// ============================================================================

$wpdb->delete(
    $wpdb->postmeta,
    ['meta_key' => '_penalis_email_sent'],
    ['%s']
);

// ============================================================================
// 4. Clear scheduled cron events
// ============================================================================

wp_clear_scheduled_hook('penalis_process_email_queue');
