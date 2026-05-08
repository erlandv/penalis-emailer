<?php
/**
 * Database Manager Class
 *
 * Handles creation, upgrade, and migration of custom database tables.
 * Manages schema versioning and data migration from wp_options.
 *
 * @package Penalis_Emailer
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Database
 *
 * Manages all custom database table operations for the plugin.
 */
class Penalis_Database {

    /**
     * Current database schema version
     *
     * Increment this whenever table structure changes.
     *
     * @var string
     */
    const SCHEMA_VERSION = '2.0.0';

    /**
     * Option key for storing installed schema version
     *
     * @var string
     */
    const SCHEMA_VERSION_OPTION = 'penalis_db_schema_version';

    /**
     * Option key for migration status
     *
     * @var string
     */
    const MIGRATION_DONE_OPTION = 'penalis_migration_v2_done';

    /**
     * Get the email log table name (with prefix)
     *
     * @return string
     */
    public static function get_log_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'penalis_email_log';
    }

    /**
     * Get the email queue table name (with prefix)
     *
     * @return string
     */
    public static function get_queue_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'penalis_email_queue';
    }

    /**
     * Run on plugin activation or update.
     * Creates or upgrades tables and runs migrations if needed.
     *
     * @return void
     */
    public static function install(): void {
        $installed_version = get_option(self::SCHEMA_VERSION_OPTION, '0');

        // Always run dbDelta — it is idempotent and safe to call repeatedly
        self::create_tables();

        // Run data migration from wp_options only once
        if (!get_option(self::MIGRATION_DONE_OPTION, false)) {
            self::migrate_from_options();
            update_option(self::MIGRATION_DONE_OPTION, true);
        }

        // Store current schema version
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION);
    }

    /**
     * Create or upgrade custom tables using dbDelta.
     *
     * dbDelta only adds missing columns/indexes — it never removes existing data.
     *
     * @return void
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // ----------------------------------------------------------------
        // Table: penalis_email_log
        // Stores completed send records (both manual and automatic).
        // ----------------------------------------------------------------
        $log_table = self::get_log_table();
        $sql_log = "CREATE TABLE {$log_table} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            log_key       VARCHAR(64)     NOT NULL DEFAULT '',
            type          VARCHAR(20)     NOT NULL DEFAULT 'manual',
            subject       VARCHAR(255)    NOT NULL DEFAULT '',
            body_preview  TEXT,
            job_id        VARCHAR(64)     NOT NULL DEFAULT '',
            post_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            post_title    VARCHAR(255)    NOT NULL DEFAULT '',
            post_url      VARCHAR(2083)   NOT NULL DEFAULT '',
            recipient_count INT UNSIGNED  NOT NULL DEFAULT 0,
            recipients    LONGTEXT,
            recipient_email VARCHAR(255)  NOT NULL DEFAULT '',
            recipient_name  VARCHAR(255)  NOT NULL DEFAULT '',
            sent_by       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            sent_at       INT UNSIGNED    NOT NULL DEFAULT 0,
            status        VARCHAR(20)     NOT NULL DEFAULT 'sent',
            PRIMARY KEY  (id),
            UNIQUE KEY   log_key (log_key),
            KEY          type (type),
            KEY          job_id (job_id),
            KEY          sent_at (sent_at)
        ) {$charset_collate};";

        // ----------------------------------------------------------------
        // Table: penalis_email_queue
        // Stores individual per-recipient send jobs for async processing.
        // ----------------------------------------------------------------
        $queue_table = self::get_queue_table();
        $sql_queue = "CREATE TABLE {$queue_table} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id        VARCHAR(64)     NOT NULL DEFAULT '',
            user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            subject       VARCHAR(255)    NOT NULL DEFAULT '',
            body          LONGTEXT,
            from_name     VARCHAR(100)    NOT NULL DEFAULT '',
            status        VARCHAR(20)     NOT NULL DEFAULT 'pending',
            attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
            next_attempt  INT UNSIGNED    NOT NULL DEFAULT 0,
            created_at    INT UNSIGNED    NOT NULL DEFAULT 0,
            sent_at       INT UNSIGNED    NOT NULL DEFAULT 0,
            error_message TEXT,
            PRIMARY KEY  (id),
            KEY          job_id (job_id),
            KEY          status (status),
            KEY          next_attempt (next_attempt),
            KEY          created_at (created_at)
        ) {$charset_collate};";

        // ----------------------------------------------------------------
        // Table: penalis_email_draft
        // Stores draft emails separately from the log.
        // ----------------------------------------------------------------
        $draft_table = self::get_draft_table();
        $sql_draft = "CREATE TABLE {$draft_table} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_key     VARCHAR(64)     NOT NULL DEFAULT '',
            from_name     VARCHAR(100)    NOT NULL DEFAULT '',
            subject       VARCHAR(255)    NOT NULL DEFAULT '',
            body          LONGTEXT,
            recipient_count INT UNSIGNED  NOT NULL DEFAULT 0,
            recipients    LONGTEXT,
            created_by    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at    INT UNSIGNED    NOT NULL DEFAULT 0,
            updated_at    INT UNSIGNED    NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY   draft_key (draft_key),
            KEY          created_by (created_by),
            KEY          updated_at (updated_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_log);
        dbDelta($sql_queue);
        dbDelta($sql_draft);
    }

    /**
     * Get the email draft table name (with prefix)
     *
     * @return string
     */
    public static function get_draft_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'penalis_email_draft';
    }

    /**
     * Migrate existing data from wp_options to custom tables.
     *
     * Reads the legacy option key, inserts each entry into the appropriate
     * custom table, then removes the legacy option to free up space.
     *
     * @return void
     */
    private static function migrate_from_options(): void {
        global $wpdb;

        $legacy_data = get_option(Penalis_Config::OPTION_KEY_MANUAL_LOG, []);

        if (empty($legacy_data) || !is_array($legacy_data)) {
            return;
        }

        $log_table   = self::get_log_table();
        $draft_table = self::get_draft_table();

        foreach ($legacy_data as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $status = $entry['status'] ?? 'sent';

            if ($status === 'draft') {
                // Migrate draft entries
                $draft_key = $entry['id'] ?? ('draft_' . time() . '_' . wp_generate_password(8, false));

                // Avoid duplicate key on re-run
                $exists = $wpdb->get_var(
                    $wpdb->prepare("SELECT id FROM {$draft_table} WHERE draft_key = %s", $draft_key)
                );
                if ($exists) {
                    continue;
                }

                $recipients = $entry['recipients'] ?? [];

                $wpdb->insert($draft_table, [
                    'draft_key'       => $draft_key,
                    'from_name'       => $entry['from_name']       ?? '',
                    'subject'         => $entry['subject']         ?? '',
                    'body'            => $entry['body']            ?? '',
                    'recipient_count' => $entry['recipient_count'] ?? count($recipients),
                    'recipients'      => wp_json_encode($recipients),
                    'created_by'      => $entry['created_by']      ?? 0,
                    'created_at'      => $entry['created_at']      ?? ($entry['updated_at'] ?? time()),
                    'updated_at'      => $entry['updated_at']      ?? time(),
                ], ['%s','%s','%s','%s','%d','%s','%d','%d','%d']);

            } else {
                // Migrate sent log entries
                $log_key = $entry['id'] ?? ('legacy_' . ($entry['sent_at'] ?? time()) . '_' . wp_generate_password(8, false));

                // Avoid duplicate key on re-run
                $exists = $wpdb->get_var(
                    $wpdb->prepare("SELECT id FROM {$log_table} WHERE log_key = %s", $log_key)
                );
                if ($exists) {
                    continue;
                }

                $recipients = $entry['recipients'] ?? [];

                $wpdb->insert($log_table, [
                    'log_key'         => $log_key,
                    'type'            => $entry['type']            ?? 'manual',
                    'subject'         => $entry['subject']         ?? '',
                    'body_preview'    => $entry['body_preview']    ?? '',
                    'job_id'          => $entry['job_id']          ?? '',
                    'post_id'         => $entry['post_id']         ?? 0,
                    'post_title'      => $entry['post_title']      ?? '',
                    'post_url'        => $entry['post_url']        ?? '',
                    'recipient_count' => $entry['recipient_count'] ?? count($recipients),
                    'recipients'      => wp_json_encode($recipients),
                    'recipient_email' => $entry['recipient_email'] ?? '',
                    'recipient_name'  => $entry['recipient_name']  ?? '',
                    'sent_by'         => $entry['sent_by']         ?? 0,
                    'sent_at'         => $entry['sent_at']         ?? ($entry['timestamp'] ?? time()),
                    'status'          => 'sent',
                ], ['%s','%s','%s','%s','%s','%d','%s','%s','%d','%s','%s','%s','%d','%d','%s']);
            }
        }

        // Remove legacy option to free up autoload memory
        delete_option(Penalis_Config::OPTION_KEY_MANUAL_LOG);
    }

    /**
     * Drop all custom tables. Called on plugin uninstall.
     *
     * @return void
     */
    public static function uninstall(): void {
        global $wpdb;

        $tables = [
            self::get_queue_table(),
            self::get_log_table(),
            self::get_draft_table(),
        ];

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::SCHEMA_VERSION_OPTION);
        delete_option(self::MIGRATION_DONE_OPTION);
    }

    /**
     * Check whether all required tables exist.
     * Useful for health checks.
     *
     * @return bool
     */
    public static function tables_exist(): bool {
        global $wpdb;

        $tables = [
            self::get_log_table(),
            self::get_queue_table(),
            self::get_draft_table(),
        ];

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $result = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($result !== $table) {
                return false;
            }
        }

        return true;
    }
}
