<?php
/**
 * Email Sender Class
 *
 * Handles all email composition and sending logic for both automatic
 * and manual email notifications.
 *
 * @package Penalis_Emailer
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Sender
 *
 * Composes and sends emails using wp_mail, integrates with Email_Template
 * and Email_Logger for rendering and tracking.
 */
class Penalis_Email_Sender {
    
    /**
     * Email template instance
     *
     * @var Penalis_Email_Template
     */
    private $template;
    
    /**
     * Email logger instance
     *
     * @var Penalis_Email_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Template $template Email template instance
     * @param Penalis_Email_Logger   $logger   Email logger instance
     */
    public function __construct(Penalis_Email_Template $template, Penalis_Email_Logger $logger) {
        $this->template = $template;
        $this->logger = $logger;
    }
    
    /**
     * Handle post status transition to send automatic emails
     *
     * Triggered by transition_post_status hook when a post is published.
     * Validates post, checks for duplicates, and sends email to post author.
     *
     * @param string  $new_status New post status
     * @param string  $old_status Old post status
     * @param WP_Post $post       Post object
     * @return bool True on success, false on failure
     */
    public function handle_post_status_transition(string $new_status, string $old_status, WP_Post $post): bool {
        // Validate post
        if (!$this->validate_post($post, $old_status, $new_status)) {
            return false;
        }
        
        // Check if email already sent
        if ($this->has_email_been_sent($post->ID)) {
            return true; // Already sent, not an error
        }
        
        // Get author data
        $author = get_userdata($post->post_author);
        if (!$author || !is_email($author->user_email)) {
            error_log('Penalis Emailer: Invalid author email for post ID ' . $post->ID);
            return false;
        }
        
        // Get post data - decode any HTML entities and remove slashes
        $post_title = wp_specialchars_decode($post->post_title, ENT_QUOTES);
        $post_title = stripslashes($post_title);
        $post_url = get_permalink($post->ID);
        
        // Prepare placeholders
        $placeholders = [
            'AUTHOR_NAME' => $author->display_name,
            'POST_TITLE' => $post_title,
            'POST_URL' => $post_url
        ];
        
        // Render auto-email with editable template
        $email_body = $this->template->render_auto_email($placeholders);
        
        // Compose email
        $email_data = [
            'to' => $author->user_email,
            'subject' => 'Tulisanmu telah dipublikasikan di Penalis 🎉',
            'message' => $email_body,
            'post_id' => $post->ID
        ];
        
        // Apply filters
        $filtered_subject = apply_filters('penalis_email_subject', $email_data['subject'], $post->ID);
        $filtered_body = apply_filters('penalis_email_message', $email_data['message'], $post->ID);
        
        // Prepare headers
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Penalis - Publikasi <no-reply@' . $site_domain . '>'
        ];
        
        // Apply headers filter
        $headers = apply_filters('penalis_email_headers', $headers, $post->ID);
        
        // Set content type filter before sending
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
        
        // Send email
        $sent = wp_mail($email_data['to'], $filtered_subject, $filtered_body, $headers);
        
        // Remove content type filter after sending
        remove_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
        
        if ($sent) {
            // Mark as sent and log only on success
            $this->mark_email_as_sent($post->ID);
            return true;
        } else {
            error_log('Penalis Emailer: Failed to send email for post ID ' . $post->ID);
            return false;
        }
    }
    
    /**
     * Send manual email to selected users
     *
     * Sends emails to specified user IDs with custom or template-based content.
     * Tracks success and failures.
     *
     * @param string $subject      Email subject
     * @param array  $user_ids     Array of user IDs to send to
     * @param string $message      Custom message content (plain text/markdown)
     * @param string $from_name    From name for email header (default: 'Penalis')
     * @param bool   $use_template Whether to use template or custom message (deprecated, always uses flexible template now)
     * @return array Results with 'success' count and 'failed' array of user IDs
     */
    public function send_manual_email(string $subject, array $user_ids, string $message = '', string $from_name = 'Penalis', bool $use_template = true): array {
        // Apply recipients filter
        $user_ids = apply_filters('penalis_email_recipients', $user_ids);
        
        $results = [
            'success' => 0,
            'failed' => []
        ];
        
        $successful_recipients = [];
        
        // Loop through each user
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            
            // Validate user and email
            if (!$user || !is_email($user->user_email)) {
                $results['failed'][] = $user_id;
                continue;
            }
            
            // Prepare user data for personalization
            $user_data = [
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login
            ];
            
            // Render flexible email with markdown support
            $email_body = $this->template->render_flexible_email($message, $user_data);
            
            // Apply filters (post_id = 0 for manual emails)
            $filtered_subject = apply_filters('penalis_email_subject', $subject, 0);
            $filtered_body = apply_filters('penalis_email_message', $email_body, 0);
            
            // Prepare headers with custom from name
            $site_domain = parse_url(home_url(), PHP_URL_HOST);
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $from_name . ' <no-reply@' . $site_domain . '>'
            ];
            
            // Apply headers filter
            $headers = apply_filters('penalis_email_headers', $headers, 0);
            
            // Set content type filter before sending
            add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
            
            // Send email
            $sent = wp_mail($user->user_email, $filtered_subject, $filtered_body, $headers);
            
            // Remove content type filter after sending
            remove_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
            
            if ($sent) {
                $results['success']++;
                $successful_recipients[] = $user_id;
            } else {
                $results['failed'][] = $user_id;
            }
        }
        
        // Log successful sends
        if ($results['success'] > 0) {
            $this->logger->log_manual_email($successful_recipients, $subject, $message);
        }
        
        return $results;
    }
    
    /**
     * Validate post for automatic email sending
     *
     * Checks post type, revision status, and status transition.
     *
     * @param WP_Post $post       Post object
     * @param string  $old_status Old post status
     * @param string  $new_status New post status
     * @return bool True if valid, false otherwise
     */
    private function validate_post(WP_Post $post, string $old_status, string $new_status): bool {
        // Check new status is publish and old status is not publish
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return false;
        }
        
        // Check post type is 'post'
        if ($post->post_type !== 'post') {
            return false;
        }
        
        // Check post is not a revision
        if (wp_is_post_revision($post)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if email has been sent for a post
     *
     * @param int $post_id Post ID
     * @return bool True if sent, false otherwise
     */
    private function has_email_been_sent(int $post_id): bool {
        return $this->logger->has_automatic_email_been_sent($post_id);
    }
    
    /**
     * Mark email as sent for a post
     *
     * Should only be called after successful email delivery.
     *
     * @param int $post_id Post ID
     * @return void
     */
    private function mark_email_as_sent(int $post_id): void {
        $this->logger->log_automatic_email($post_id);
    }
    
    /**
     * Compose email with headers and content
     *
     * @param array $data         Email data including to, subject, placeholders
     * @param bool  $use_template Whether to use template or custom message
     * @return array Email array with 'to', 'subject', 'message', 'headers'
     */
    private function compose_email(array $data, bool $use_template = true): array {
        // Set default subject
        $subject = 'Tulisanmu telah dipublikasikan di Penalis 🎉';
        
        // Render message using template
        $custom_message = $data['custom_message'] ?? '';
        $message = $this->template->render($data['placeholders'], $use_template, $custom_message);
        
        // Set headers - CRITICAL: Always include Content-Type for HTML emails
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Penalis - Publikasi <no-reply@' . $site_domain . '>'
        ];
        
        // Apply headers filter to allow customization
        $headers = apply_filters('penalis_email_headers', $headers, $data['post_id'] ?? 0);
        
        return [
            'to' => $data['to'],
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers
        ];
    }
    
    /**
     * Apply WordPress filters for email customization
     *
     * @param string $subject Email subject
     * @param string $message Email message content
     * @param int    $post_id Post ID (0 for manual emails)
     * @return array Filtered subject and message
     */
    private function apply_email_filters(string $subject, string $message, int $post_id = 0): array {
        $subject = apply_filters('penalis_email_subject', $subject, $post_id);
        $message = apply_filters('penalis_email_message', $message, $post_id);
        
        return [
            'subject' => $subject,
            'message' => $message
        ];
    }
    
    /**
     * Set email content type to HTML
     *
     * @return string Content type
     */
    public function set_html_content_type(): string {
        return 'text/html';
    }
}
