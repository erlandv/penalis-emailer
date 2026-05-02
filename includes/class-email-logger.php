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
 * Tracks email delivery using repository pattern for data access.
 */
class Penalis_Email_Logger {
    
    /**
     * Manual email log repository
     *
     * @var Penalis_Email_Log_Repository_Interface
     */
    private $manual_log_repository;
    
    /**
     * Post meta repository for automatic emails
     *
     * @var Penalis_Post_Meta_Repository
     */
    private $post_meta_repository;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Log_Repository_Interface|null $manual_log_repository Manual email repository
     * @param Penalis_Post_Meta_Repository|null           $post_meta_repository  Post meta repository
     */
    public function __construct(
        Penalis_Email_Log_Repository_Interface $manual_log_repository = null,
        Penalis_Post_Meta_Repository $post_meta_repository = null
    ) {
        // Use default implementations if not provided
        $this->manual_log_repository = $manual_log_repository ?? 
            new Penalis_Email_Log_Options_Repository(Penalis_Config::OPTION_KEY_MANUAL_LOG);
        
        $this->post_meta_repository = $post_meta_repository ?? 
            new Penalis_Post_Meta_Repository(Penalis_Config::META_KEY_EMAIL_SENT);
    }
    
    /**
     * Log automatic email send for a post
     *
     * Stores timestamp in post meta to track when email was sent.
     *
     * @param int $post_id The post ID
     * @return void
     */
    public function log_automatic_email(int $post_id): void {
        $this->post_meta_repository->save($post_id, time());
    }
    
    /**
     * Check if automatic email has been sent for a post
     *
     * @param int $post_id The post ID
     * @return bool True if email was sent, false otherwise
     */
    public function has_automatic_email_been_sent(int $post_id): bool {
        return $this->post_meta_repository->exists($post_id);
    }
    
    /**
     * Log manual email send
     *
     * Appends log entry to repository with enhanced details.
     * Receives sanitized data as parameters - NO direct $_POST access.
     *
     * @param array  $recipients Array of user IDs who received the email
     * @param string $subject    The email subject (already sanitized)
     * @param string $body       The email body content (for preview)
     * @return void
     */
    public function log_manual_email(array $recipients, string $subject, string $body = ''): void {
        // Get current user
        $current_user = wp_get_current_user();
        
        // Generate unique log ID
        $log_id = 'email_' . time() . '_' . wp_generate_password(8, false);
        
        // Create body preview (first 100 characters)
        $body_preview = mb_substr(strip_tags($body), 0, 100);
        if (mb_strlen(strip_tags($body)) > 100) {
            $body_preview .= '...';
        }
        
        $log_entry = [
            'id' => $log_id,
            'subject' => $subject,
            'body_preview' => $body_preview,
            'recipient_count' => count($recipients),
            'recipients' => $recipients,
            'sent_at' => time(),
            'sent_by' => $current_user->ID,
            'status' => 'sent'
        ];
        
        $this->manual_log_repository->save($log_entry);
    }
    
    /**
     * Get manual email log entries
     *
     * @param int $limit Optional limit for number of entries
     * @return array Array of log entries with enhanced details
     */
    public function get_manual_email_log(int $limit = 0): array {
        return $this->manual_log_repository->get_all($limit);
    }
    
    /**
     * Get log entry by ID
     *
     * @param string $log_id Log entry ID
     * @return array|null Log entry or null if not found
     */
    public function get_log_entry(string $log_id): ?array {
        return $this->manual_log_repository->find_by_id($log_id);
    }
    
    /**
     * Delete old log entries (keep last N entries)
     *
     * @param int $keep_count Number of entries to keep
     * @return int Number of entries deleted
     */
    public function cleanup_old_logs(int $keep_count = 100): int {
        return $this->manual_log_repository->cleanup($keep_count);
    }
}

