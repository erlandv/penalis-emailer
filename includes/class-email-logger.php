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
     * Appends log entry to options array with enhanced details.
     * Receives sanitized data as parameters - NO direct $_POST access.
     *
     * @param array  $recipients Array of user IDs who received the email
     * @param string $subject    The email subject (already sanitized)
     * @param string $body       The email body content (for preview)
     * @return void
     */
    public function log_manual_email(array $recipients, string $subject, string $body = ''): void {
        $log = get_option($this->option_key, []);
        
        // Get current user
        $current_user = wp_get_current_user();
        
        // Generate unique log ID
        $log_id = 'email_' . time() . '_' . wp_generate_password(8, false);
        
        // Create body preview (first 100 characters)
        $body_preview = mb_substr(strip_tags($body), 0, 100);
        if (mb_strlen(strip_tags($body)) > 100) {
            $body_preview .= '...';
        }
        
        $log[] = [
            'id' => $log_id,
            'subject' => $subject,
            'body_preview' => $body_preview,
            'recipient_count' => count($recipients),
            'recipients' => $recipients,
            'sent_at' => time(),
            'sent_by' => $current_user->ID,
            'status' => 'sent'
        ];
        
        update_option($this->option_key, $log);
    }
    
    /**
     * Get manual email log entries
     *
     * @param int $limit Optional limit for number of entries
     * @return array Array of log entries with enhanced details
     */
    public function get_manual_email_log(int $limit = 0): array {
        $log = get_option($this->option_key, []);
        
        // Sort by sent_at descending (newest first)
        usort($log, function($a, $b) {
            $time_a = isset($a['sent_at']) ? $a['sent_at'] : (isset($a['timestamp']) ? $a['timestamp'] : 0);
            $time_b = isset($b['sent_at']) ? $b['sent_at'] : (isset($b['timestamp']) ? $b['timestamp'] : 0);
            return $time_b - $time_a;
        });
        
        // Apply limit if specified
        if ($limit > 0) {
            $log = array_slice($log, 0, $limit);
        }
        
        return $log;
    }
    
    /**
     * Get log entry by ID
     *
     * @param string $log_id Log entry ID
     * @return array|null Log entry or null if not found
     */
    public function get_log_entry(string $log_id): ?array {
        $log = get_option($this->option_key, []);
        
        foreach ($log as $entry) {
            if (isset($entry['id']) && $entry['id'] === $log_id) {
                return $entry;
            }
        }
        
        return null;
    }
    
    /**
     * Delete old log entries (keep last N entries)
     *
     * @param int $keep_count Number of entries to keep
     * @return int Number of entries deleted
     */
    public function cleanup_old_logs(int $keep_count = 100): int {
        $log = $this->get_manual_email_log();
        
        if (count($log) <= $keep_count) {
            return 0;
        }
        
        $deleted_count = count($log) - $keep_count;
        $log = array_slice($log, 0, $keep_count);
        
        update_option($this->option_key, $log);
        
        return $deleted_count;
    }
}

