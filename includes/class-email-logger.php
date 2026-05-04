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
class Penalis_Email_Logger implements Penalis_Email_Logger_Interface {
    
    /**
     * Manual email log repository
     *
     * @var Penalis_Email_Log_Repository_Interface
     */
    private $manual_log_repository;
    
    /**
     * Post meta repository for automatic emails
     *
     * @var Penalis_Post_Meta_Repository_Interface
     */
    private $post_meta_repository;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Log_Repository_Interface $manual_log_repository Manual email repository
     * @param Penalis_Post_Meta_Repository_Interface $post_meta_repository  Post meta repository
     */
    public function __construct(
        Penalis_Email_Log_Repository_Interface $manual_log_repository,
        Penalis_Post_Meta_Repository_Interface $post_meta_repository
    ) {
        $this->manual_log_repository = $manual_log_repository;
        $this->post_meta_repository = $post_meta_repository;
    }
    
    /**
     * Get the manual log repository instance
     *
     * @return Penalis_Email_Log_Repository_Interface
     */
    public function get_repository(): Penalis_Email_Log_Repository_Interface {
        return $this->manual_log_repository;
    }
    
    /**
     * Log automatic email send for a post
     *
     * Stores detailed log entry in repository and timestamp in post meta.
     *
     * @param int $post_id The post ID
     * @return void
     */
    public function log_automatic_email(int $post_id): void {
        // Get post and author data
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $author = get_userdata($post->post_author);
        if (!$author) {
            return;
        }
        
        // Generate unique log ID
        $log_id = 'auto_' . $post_id . '_' . time();
        
        // Get post title and URL
        $post_title = wp_specialchars_decode($post->post_title, ENT_QUOTES);
        $post_title = stripslashes($post_title);
        $post_url = get_permalink($post_id);
        
        // Create log entry with detailed information
        $log_entry = [
            'id' => $log_id,
            'type' => 'automatic',
            'subject' => 'Karya Tulismu Sudah Publish di Penalis 🎉',
            'post_id' => $post_id,
            'post_title' => $post_title,
            'post_url' => $post_url,
            'recipient_count' => 1,
            'recipients' => [$author->ID],
            'recipient_email' => $author->user_email,
            'recipient_name' => $author->display_name,
            'sent_at' => time(),
            'sent_by' => 0, // System-generated, no user
            'status' => 'sent'
        ];
        
        // Save to unified log repository
        $this->manual_log_repository->save($log_entry);
        
        // Also save timestamp in post meta for backward compatibility and quick lookup
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
        $log_id = 'manual_' . time() . '_' . wp_generate_password(8, false);
        
        // Create body preview (first 100 characters)
        $body_preview = mb_substr(strip_tags($body), 0, 100);
        if (mb_strlen(strip_tags($body)) > 100) {
            $body_preview .= '...';
        }
        
        $log_entry = [
            'id' => $log_id,
            'type' => 'manual',
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
     * Get all email log entries (both manual and automatic)
     *
     * @param int    $limit Optional limit for number of entries
     * @param string $type  Optional filter by type: 'all', 'manual', 'automatic'
     * @return array Array of log entries sorted by sent_at descending
     */
    public function get_all_email_log(int $limit = 0, string $type = 'all'): array {
        $all_logs = $this->manual_log_repository->get_all(0);
        
        // Filter by type if specified
        if ($type === 'manual') {
            $all_logs = array_filter($all_logs, function($log) {
                return !isset($log['type']) || $log['type'] === 'manual';
            });
        } elseif ($type === 'automatic') {
            $all_logs = array_filter($all_logs, function($log) {
                return isset($log['type']) && $log['type'] === 'automatic';
            });
        }
        
        // Re-index array after filtering
        $all_logs = array_values($all_logs);
        
        // Apply limit if specified
        if ($limit > 0) {
            $all_logs = array_slice($all_logs, 0, $limit);
        }
        
        return $all_logs;
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
    
    /**
     * Log email send activity (interface method)
     *
     * @param string $recipient_email Recipient email address
     * @param string $subject         Email subject
     * @param string $status          Send status (success/failed)
     * @param string $type            Email type (manual/auto)
     * @param string $error_message   Error message if failed
     * @return bool True if logged successfully, false otherwise
     */
    public function log_email(
        string $recipient_email,
        string $subject,
        string $status,
        string $type = 'manual',
        string $error_message = ''
    ): bool {
        if ($type === 'manual') {
            // For manual emails, log with recipient info
            $this->log_manual_email([$recipient_email], $subject, '');
            return true;
        }
        
        // For auto emails, we don't have a post_id here, so just return true
        return true;
    }
    
    /**
     * Get email logs (interface method)
     *
     * @param int $limit Maximum number of logs to retrieve (0 = all)
     * @return array Array of log entries
     */
    public function get_logs(int $limit = 0): array {
        return $this->get_manual_email_log($limit);
    }
    
    /**
     * Get total log count (interface method)
     *
     * @return int Total number of logs
     */
    public function get_log_count(): int {
        return $this->manual_log_repository->count();
    }
}

