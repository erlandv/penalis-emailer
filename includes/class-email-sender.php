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
class Penalis_Email_Sender implements Penalis_Email_Sender_Interface {
    
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
        try {
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
                throw new Penalis_Email_Send_Exception(
                    'Invalid author email for post',
                    [],
                    ['post_id' => $post->ID, 'author_id' => $post->post_author]
                );
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
            
            // Send email using common method
            $sent = $this->send_email(
                $author->user_email,
                Penalis_Config::get_auto_email_subject(),
                $email_body,
                Penalis_Config::get_auto_email_from(),
                $post->ID
            );
            
            if ($sent) {
                // Mark as sent and log only on success
                $this->mark_email_as_sent($post->ID);
                
                // Fire success action
                do_action('penalis_email_sent_success', [
                    'type' => 'automatic',
                    'post_id' => $post->ID,
                    'recipient' => $author->user_email
                ]);
                
                return true;
            } else {
                throw new Penalis_Email_Send_Exception(
                    'Failed to send email via wp_mail',
                    [$author->user_email],
                    ['post_id' => $post->ID]
                );
            }
        } catch (Penalis_Exception $e) {
            // Log exception with context
            $e->log('error');
            
            // Fire error action
            do_action('penalis_email_send_failed', $e);
            
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
     * @return array Results with 'success' count and 'failed' array of user IDs
     */
    public function send_manual_email(string $subject, array $user_ids, string $message = '', string $from_name = 'Penalis'): array {
        // Apply recipients filter
        $user_ids = apply_filters('penalis_email_recipients', $user_ids);
        
        $results = [
            'success' => 0,
            'failed' => [],
            'errors' => []
        ];
        
        $successful_recipients = [];
        
        // Loop through each user
        foreach ($user_ids as $user_id) {
            try {
                $user = get_userdata($user_id);
                
                // Validate user and email
                if (!$user || !is_email($user->user_email)) {
                    throw new Penalis_Validation_Exception(
                        'Invalid user or email',
                        ['user_id' => 'User not found or invalid email'],
                        ['user_id' => $user_id]
                    );
                }
                
                // Prepare user data for personalization
                $user_data = [
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'user_login' => $user->user_login
                ];
                
                // Generate preheader from subject (first 100 chars)
                $preheader = wp_strip_all_tags($subject);
                $preheader = mb_substr($preheader, 0, 100);
                
                // Render flexible email with markdown support
                $email_body = $this->template->render_flexible_email($message, $user_data, $preheader);
                
                // Send email using common method (post_id = 0 for manual emails)
                $sent = $this->send_email(
                    $user->user_email,
                    $subject,
                    $email_body,
                    $from_name,
                    0
                );
                
                if ($sent) {
                    $results['success']++;
                    $successful_recipients[] = $user_id;
                } else {
                    throw new Penalis_Email_Send_Exception(
                        'Failed to send email via wp_mail',
                        [$user->user_email],
                        ['user_id' => $user_id]
                    );
                }
            } catch (Penalis_Exception $e) {
                // Log exception
                $e->log('warning');
                
                // Track failed recipient
                $results['failed'][] = $user_id;
                $results['errors'][$user_id] = $e->getMessage();
            }
        }
        
        // Log successful sends
        if ($results['success'] > 0) {
            $this->logger->log_manual_email($successful_recipients, $subject, $message);
            
            // Fire success action
            do_action('penalis_email_sent_success', [
                'type' => 'manual',
                'subject' => $subject,
                'success_count' => $results['success'],
                'recipients' => $successful_recipients
            ]);
        }
        
        // Fire partial failure action if some failed
        if (!empty($results['failed'])) {
            do_action('penalis_email_send_partial_failure', $results);
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
     * Send email with common logic
     * 
     * Centralized email sending logic to avoid duplication.
     * Handles filters, headers, and wp_mail execution.
     *
     * @param string $to        Recipient email address
     * @param string $subject   Email subject
     * @param string $body      Email body (already rendered HTML)
     * @param string $from_name From name for email header
     * @param int    $post_id   Post ID (0 for manual emails)
     * @return bool True if sent successfully, false otherwise
     */
    private function send_email(string $to, string $subject, string $body, string $from_name, int $post_id = 0): bool {
        // Apply filters
        $filtered_subject = apply_filters('penalis_email_subject', $subject, $post_id);
        $filtered_body = apply_filters('penalis_email_message', $body, $post_id);
        
        // Prepare headers
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <no-reply@' . $site_domain . '>'
        ];
        
        // Apply headers filter
        $headers = apply_filters('penalis_email_headers', $headers, $post_id);
        
        // Set content type filter before sending
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
        
        // Send email
        $sent = wp_mail($to, $filtered_subject, $filtered_body, $headers);
        
        // Remove content type filter after sending
        remove_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
        
        return $sent;
    }
    
    /**
     * Send automatic email to post author (interface implementation)
     *
     * @param int $post_id Post ID
     * @return bool True if sent successfully, false otherwise
     */
    public function send_auto_email(int $post_id): bool {
        try {
            // Get post
            $post = get_post($post_id);
            if (!$post) {
                throw new Penalis_Email_Send_Exception(
                    'Post not found',
                    [],
                    ['post_id' => $post_id]
                );
            }
            
            // Check if email already sent
            if ($this->has_email_been_sent($post_id)) {
                return true; // Already sent, not an error
            }
            
            // Get author data
            $author = get_userdata($post->post_author);
            if (!$author || !is_email($author->user_email)) {
                throw new Penalis_Email_Send_Exception(
                    'Invalid author email',
                    [],
                    ['post_id' => $post_id, 'author_id' => $post->post_author]
                );
            }
            
            // Get post data
            $post_title = wp_specialchars_decode($post->post_title, ENT_QUOTES);
            $post_title = stripslashes($post_title);
            $post_url = get_permalink($post_id);
            
            // Prepare placeholders
            $placeholders = [
                'AUTHOR_NAME' => $author->display_name,
                'POST_TITLE' => $post_title,
                'POST_URL' => $post_url
            ];
            
            // Render auto-email
            $email_body = $this->template->render_auto_email($placeholders);
            
            // Send email using common method
            $sent = $this->send_email(
                $author->user_email,
                Penalis_Config::get_auto_email_subject(),
                $email_body,
                Penalis_Config::get_auto_email_from(),
                $post_id
            );
            
            if ($sent) {
                $this->mark_email_as_sent($post_id);
                
                // Fire success action
                do_action('penalis_email_sent_success', [
                    'type' => 'automatic',
                    'post_id' => $post_id,
                    'recipient' => $author->user_email
                ]);
                
                return true;
            }
            
            throw new Penalis_Email_Send_Exception(
                'Failed to send email via wp_mail',
                [$author->user_email],
                ['post_id' => $post_id]
            );
        } catch (Penalis_Exception $e) {
            // Log exception with context
            $e->log('error');
            
            // Fire error action
            do_action('penalis_email_send_failed', $e);
            
            return false;
        }
    }
    
    /**
     * Send test email to current user (interface implementation)
     *
     * @param string $template_body Template body to test
     * @return bool True if sent successfully, false otherwise
     */
    public function send_test_email(string $template_body): bool {
        try {
            $current_user = wp_get_current_user();
            
            // Validate current user
            if (!$current_user || !is_email($current_user->user_email)) {
                throw new Penalis_Validation_Exception(
                    'Invalid current user or email',
                    ['user' => 'Current user not found or invalid email'],
                    ['user_id' => $current_user ? $current_user->ID : 0]
                );
            }
            
            // Prepare user data
            $user_data = [
                'display_name' => $current_user->display_name,
                'user_email' => $current_user->user_email,
                'user_login' => $current_user->user_login,
                'post_title' => 'Contoh Judul Tulisan',
                'post_url' => home_url('/contoh-tulisan/')
            ];
            
            // Render email
            $email_body = $this->template->render_flexible_email($template_body, $user_data);
            
            // Send email using common method
            $sent = $this->send_email(
                $current_user->user_email,
                'Test Email - Penalis Emailer',
                $email_body,
                'Penalis - Test',
                0
            );
            
            if ($sent) {
                // Fire success action
                do_action('penalis_email_sent_success', [
                    'type' => 'test',
                    'recipient' => $current_user->user_email
                ]);
                
                return true;
            }
            
            throw new Penalis_Email_Send_Exception(
                'Failed to send test email via wp_mail',
                [$current_user->user_email],
                ['user_id' => $current_user->ID]
            );
        } catch (Penalis_Exception $e) {
            // Log exception
            $e->log('warning');
            
            // Fire error action
            do_action('penalis_email_send_failed', $e);
            
            return false;
        }
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
