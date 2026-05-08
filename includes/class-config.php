<?php
/**
 * Configuration Class
 *
 * Centralized configuration constants and settings for the plugin.
 * Eliminates magic strings and numbers throughout the codebase.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Config
 *
 * Provides centralized configuration management.
 */
class Penalis_Config {
    
    // Admin Page Settings
    const PAGE_SLUG = 'penalis-email';
    const SETTINGS_PAGE_SLUG = 'penalis-email-settings';
    const USERS_PER_PAGE = 20;
    
    // Database Keys
    const META_KEY_EMAIL_SENT = '_penalis_email_sent';
    const OPTION_KEY_MANUAL_LOG = 'penalis_manual_emails_log';
    const OPTION_KEY_AUTO_BODY = 'penalis_auto_email_body';
    const OPTION_KEY_AUTO_BODY_MODIFIED_TIME = 'penalis_auto_email_body_modified_time';
    const OPTION_KEY_AUTO_BODY_MODIFIED_BY = 'penalis_auto_email_body_modified_by';
    
    // Email Settings
    const DEFAULT_FROM_NAME = 'Penalis';
    const DEFAULT_SUBJECT = 'Karya Tulismu Sudah Publish di Penalis 🎉';
    const DEFAULT_AUTO_EMAIL_FROM = 'Penalis - Publikasi';
    const DEFAULT_LOGO_URL = 'https://penalis.com/wp-content/uploads/2021/01/logo-penalis.png';
    
    // User Roles
    const ELIGIBLE_ROLES = ['author', 'contributor'];
    
    // Log Settings
    const DEFAULT_LOG_LIMIT = 50;
    const LOG_CLEANUP_KEEP_COUNT = 100;

    // Queue Settings
    const DEFAULT_QUEUE_BATCH_SIZE    = 30;   // emails per cron batch
    const DEFAULT_QUEUE_INTERVAL      = 60;   // seconds between batches
    const DEFAULT_QUEUE_MAX_ATTEMPTS  = 3;    // max retry attempts before permanent failure
    const OPTION_KEY_QUEUE_BATCH_SIZE = 'penalis_queue_batch_size';
    const OPTION_KEY_QUEUE_INTERVAL   = 'penalis_queue_interval';
    const OPTION_KEY_QUEUE_MAX_ATTEMPTS = 'penalis_queue_max_attempts';

    // WP-Cron hook name
    const CRON_HOOK = 'penalis_process_email_queue';
    
    /**
     * Get logo URL with filter support
     *
     * @return string Logo URL
     */
    public static function get_logo_url(): string {
        return apply_filters('penalis_logo_url', self::DEFAULT_LOGO_URL);
    }
    
    /**
     * Get automatic email subject with filter support
     *
     * @return string Email subject
     */
    public static function get_auto_email_subject(): string {
        return apply_filters('penalis_auto_email_subject', self::DEFAULT_SUBJECT);
    }
    
    /**
     * Get automatic email from name with filter support
     *
     * @return string From name
     */
    public static function get_auto_email_from(): string {
        return apply_filters('penalis_auto_email_from', self::DEFAULT_AUTO_EMAIL_FROM);
    }
    
    /**
     * Get from email address based on site domain
     *
     * @param string $from_name From name
     * @return string From email header
     */
    public static function get_from_email(string $from_name = ''): string {
        $from_name = $from_name ?: self::DEFAULT_FROM_NAME;
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        return $from_name . ' <no-reply@' . $site_domain . '>';
    }
    
    /**
     * Get eligible user roles
     *
     * @return array Array of role names
     */
    public static function get_eligible_roles(): array {
        return apply_filters('penalis_eligible_roles', self::ELIGIBLE_ROLES);
    }
    
    /**
     * Get users per page for admin interface
     *
     * @return int Number of users per page
     */
    public static function get_users_per_page(): int {
        return apply_filters('penalis_users_per_page', self::USERS_PER_PAGE);
    }

    /**
     * Get queue batch size (emails per cron run)
     *
     * @return int
     */
    public static function get_queue_batch_size(): int {
        return (int) get_option(self::OPTION_KEY_QUEUE_BATCH_SIZE, self::DEFAULT_QUEUE_BATCH_SIZE);
    }

    /**
     * Get queue processing interval in seconds
     *
     * @return int
     */
    public static function get_queue_interval(): int {
        return (int) get_option(self::OPTION_KEY_QUEUE_INTERVAL, self::DEFAULT_QUEUE_INTERVAL);
    }

    /**
     * Get maximum retry attempts before permanent failure
     *
     * @return int
     */
    public static function get_queue_max_attempts(): int {
        return (int) get_option(self::OPTION_KEY_QUEUE_MAX_ATTEMPTS, self::DEFAULT_QUEUE_MAX_ATTEMPTS);
    }
}
