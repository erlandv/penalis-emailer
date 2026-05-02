<?php
/**
 * Email Sender Interface
 *
 * Contract for email sending functionality.
 * Defines methods for sending manual and automatic emails.
 *
 * @package Penalis_Emailer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Penalis_Email_Sender_Interface
 *
 * Defines the contract for email sending services.
 */
interface Penalis_Email_Sender_Interface {
    
    /**
     * Send manual email to selected users
     *
     * @param string $subject   Email subject
     * @param array  $user_ids  Array of user IDs to send to
     * @param string $body      Email body content
     * @param string $from_name Sender name
     * @return array Results with 'success' count and 'failed' array
     */
    public function send_manual_email(string $subject, array $user_ids, string $body, string $from_name): array;
    
    /**
     * Send automatic email to post author
     *
     * @param int $post_id Post ID
     * @return bool True if sent successfully, false otherwise
     */
    public function send_auto_email(int $post_id): bool;
    
    /**
     * Send test email to current user
     *
     * @param string $template_body Template body to test
     * @return bool True if sent successfully, false otherwise
     */
    public function send_test_email(string $template_body): bool;
}
