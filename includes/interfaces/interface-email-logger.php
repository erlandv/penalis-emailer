<?php
/**
 * Email Logger Interface
 *
 * Contract for email logging functionality.
 * Defines methods for logging email activities.
 *
 * @package Penalis_Emailer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Penalis_Email_Logger_Interface
 *
 * Defines the contract for email logging services.
 */
interface Penalis_Email_Logger_Interface {
    
    /**
     * Log email send activity
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
    ): bool;
    
    /**
     * Get email logs
     *
     * @param int $limit Maximum number of logs to retrieve (0 = all)
     * @return array Array of log entries
     */
    public function get_logs(int $limit = 0): array;
    
    /**
     * Get total log count
     *
     * @return int Total number of logs
     */
    public function get_log_count(): int;
    
    /**
     * Cleanup old logs
     *
     * @param int $days_to_keep Number of days to keep logs
     * @return int Number of logs deleted
     */
    public function cleanup_old_logs(int $days_to_keep = 30): int;
}
