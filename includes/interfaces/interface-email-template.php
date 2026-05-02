<?php
/**
 * Email Template Interface
 *
 * Contract for email template generation.
 * Defines methods for rendering email templates.
 *
 * @package Penalis_Emailer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Penalis_Email_Template_Interface
 *
 * Defines the contract for email template services.
 */
interface Penalis_Email_Template_Interface {
    
    /**
     * Generate HTML email from manual content
     *
     * @param string $subject   Email subject
     * @param string $body      Email body content
     * @param string $from_name Sender name
     * @return string Complete HTML email
     */
    public function generate_manual_email(string $subject, string $body, string $from_name): string;
    
    /**
     * Generate HTML email from auto template
     *
     * @param int $post_id Post ID
     * @return string Complete HTML email
     */
    public function generate_auto_email(int $post_id): string;
    
    /**
     * Get default auto email body template
     *
     * @return string Default template body
     */
    public function get_default_auto_email_body(): string;
    
    /**
     * Get email wrapper HTML
     *
     * @param string $content Email content
     * @return string Complete HTML with wrapper
     */
    public function get_email_wrapper(string $content): string;
}
