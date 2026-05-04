<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for the admin interface.
 * Centralizes AJAX logic for better organization and testability.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Ajax_Handler
 *
 * Manages AJAX endpoints for admin functionality.
 */
class Penalis_Ajax_Handler {
    
    /**
     * Email template instance
     *
     * @var Penalis_Email_Template
     */
    private $email_template;
    
    /**
     * Email logger instance
     *
     * @var Penalis_Email_Logger
     */
    private $email_logger;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Template $email_template Email template instance
     * @param Penalis_Email_Logger   $email_logger   Email logger instance
     */
    public function __construct(Penalis_Email_Template $email_template, Penalis_Email_Logger $email_logger) {
        $this->email_template = $email_template;
        $this->email_logger = $email_logger;
    }
    
    /**
     * Register AJAX hooks
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action('wp_ajax_penalis_preview_email', [$this, 'preview_email']);
        add_action('wp_ajax_penalis_preview_auto_email', [$this, 'preview_auto_email']);
        add_action('wp_ajax_penalis_send_test_email', [$this, 'send_test_email']);
        
        // Delete actions
        add_action('wp_ajax_penalis_bulk_delete_logs', [$this, 'bulk_delete_logs']);
        add_action('wp_ajax_penalis_clear_all_logs', [$this, 'clear_all_logs']);
    }
    
    /**
     * AJAX handler for manual email preview
     *
     * @return void
     */
    public function preview_email(): void {
        // Check nonce
        check_ajax_referer('penalis_preview_email', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';
        
        if (empty($body)) {
            wp_send_json_error(['message' => __('Body is required', 'penalis-emailer')]);
        }
        
        // Generate preview with sample user data
        $sample_user_data = [
            'display_name' => 'John Doe',
            'user_email' => 'john@example.com',
            'user_login' => 'johndoe'
        ];
        
        $preview_html = $this->email_template->render_flexible_email($body, $sample_user_data);
        
        wp_send_json_success(['html' => $preview_html]);
    }
    
    /**
     * AJAX handler for auto-email preview
     *
     * @return void
     */
    public function preview_auto_email(): void {
        // Check nonce
        check_ajax_referer('penalis_preview_auto_email', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';
        
        if (empty($body)) {
            wp_send_json_error(['message' => __('Body is required', 'penalis-emailer')]);
        }
        
        // Generate preview with sample data
        $sample_data = [
            'display_name' => 'John Doe',
            'author_name' => 'John Doe',
            'post_title' => 'Sample Post Title',
            'post_url' => home_url('/sample-post')
        ];
        
        $preview_html = $this->email_template->render_flexible_email($body, $sample_data);
        
        wp_send_json_success(['html' => $preview_html]);
    }
    
    /**
     * AJAX handler for sending test email
     *
     * @return void
     */
    public function send_test_email(): void {
        // Check nonce
        check_ajax_referer('penalis_send_test_email', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';
        
        if (empty($body)) {
            wp_send_json_error(['message' => __('Body is required', 'penalis-emailer')]);
        }
        
        // Get current admin user
        $current_user = wp_get_current_user();
        $admin_email = $current_user->user_email;
        
        // Prepare sample data for test email
        $sample_data = [
            'display_name' => $current_user->display_name,
            'author_name' => $current_user->display_name,
            'post_title' => 'Sample Post Title - Test Email',
            'post_url' => home_url('/sample-post')
        ];
        
        // Generate email HTML
        $email_html = $this->email_template->render_flexible_email($body, $sample_data);
        
        // Send test email
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Penalis - Test <no-reply@' . $site_domain . '>'
        ];
        
        $subject = __('Test Email - Auto-Email Template', 'penalis-emailer');
        
        $sent = wp_mail($admin_email, $subject, $email_html, $headers);
        
        if ($sent) {
            wp_send_json_success(['message' => __('Test email sent successfully!', 'penalis-emailer')]);
        } else {
            wp_send_json_error(['message' => __('Failed to send test email. Please check your email configuration.', 'penalis-emailer')]);
        }
    }
    
    /**
     * AJAX handler for bulk delete
     *
     * @return void
     */
    public function bulk_delete_logs(): void {
        // Check nonce
        check_ajax_referer('penalis_bulk_delete_logs', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $log_ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) 
            ? array_map('sanitize_text_field', $_POST['log_ids']) 
            : [];
        
        if (empty($log_ids)) {
            wp_send_json_error(['message' => __('No logs selected', 'penalis-emailer')]);
        }
        
        // Get repository
        $repository = $this->email_logger->get_repository();
        
        $deleted_count = $repository->delete_multiple($log_ids);
        
        wp_send_json_success([
            'message' => sprintf(__('%d log entries deleted successfully', 'penalis-emailer'), $deleted_count),
            'deleted_count' => $deleted_count
        ]);
    }
    
    /**
     * AJAX handler for clear all logs
     *
     * @return void
     */
    public function clear_all_logs(): void {
        // Check nonce
        check_ajax_referer('penalis_clear_all_logs', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        // Validate type
        if (!in_array($type, ['all', 'manual', 'automatic'])) {
            wp_send_json_error(['message' => __('Invalid type', 'penalis-emailer')]);
        }
        
        // Get repository
        $repository = $this->email_logger->get_repository();
        
        $deleted_count = $repository->delete_by_type($type);
        
        wp_send_json_success([
            'message' => sprintf(__('%d log entries deleted successfully', 'penalis-emailer'), $deleted_count),
            'deleted_count' => $deleted_count
        ]);
    }
}
