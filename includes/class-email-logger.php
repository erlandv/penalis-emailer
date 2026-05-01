<?php
/**
 * Email Logger Class
 *
 * Handles logging of email delivery for both automatic and manual emails.
 * Uses WordPress post meta for automatic emails and options API for manual emails.
 *
 * @package Penalis_Emailer
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Logger
 *
 * Tracks email delivery using WordPress post meta and options API.
 */
class Penalis_Email_Logger {
    
    /**
     * Post meta key for automatic email logging
     *
     * @var string
     */
    private $meta_key = '_penalis_email_sent';
    
    /**
     * Options key for manual email logging
     *
     * @var string
     */
    private $option_key = 'penalis_manual_emails_log';
    
    /**
     * Log automatic email send for a post
     *
     * Stores timestamp in post meta to track when email was sent.
     *
     * @param int $post_id The post ID
     * @return void
     */
    public function log_automatic_email(int $post_id): void {
        update_post_meta($post_id, $this->meta_key, time());
    }
    
    /**
     * Check if automatic email has been sent for a post
     *
     * @param int $post_id The post ID
     * @return bool True if email was sent, false otherwise
     */
    public function has_automatic_email_been_sent(int $post_id): bool {
        $sent = get_post_meta($post_id, $this->meta_key, true);
        return !empty($sent);
    }
    
    /**
     * Log manual email send
     *
     * Appends log entry to options array with user_id, timestamp, and subject.
     * Receives sanitized data as parameters - NO direct $_POST access.
     *
     * @param int    $user_id The user ID who received the email
     * @param string $subject The email subject (already sanitized)
     * @return void
     */
    public function log_manual_email(int $user_id, string $subject): void {
        $log = get_option($this->option_key, []);
        
        $log[] = [
            'user_id' => $user_id,
            'timestamp' => time(),
            'subject' => $subject
        ];
        
        update_option($this->option_key, $log);
    }
    
    /**
     * Get manual email log entries
     *
     * @return array Array of log entries with user_id, timestamp, and subject
     */
    public function get_manual_email_log(): array {
        return get_option($this->option_key, []);
    }
}
