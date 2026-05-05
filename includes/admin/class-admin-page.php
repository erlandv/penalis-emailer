<?php
/**
 * Base Admin Page Class
 *
 * Abstract base class for all admin pages in the plugin.
 * Provides common functionality like asset enqueuing, notices, and redirects.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Class Penalis_Admin_Page
 *
 * Base class for admin pages with common functionality.
 */
abstract class Penalis_Admin_Page {
    
    /**
     * Page slug
     *
     * @var string
     */
    protected $page_slug;
    
    /**
     * Check if current user can access this page
     *
     * @return bool
     */
    protected function can_access(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Verify security (nonce and capability)
     *
     * @param string $nonce_action Nonce action name
     * @param string $nonce_field  Nonce field name
     * @return bool
     */
    protected function verify_security(string $nonce_action, string $nonce_field = 'nonce'): bool {
        if (!isset($_POST[$nonce_field]) || 
            !wp_verify_nonce($_POST[$nonce_field], $nonce_action)) {
            return false;
        }
        
        return $this->can_access();
    }
    
    /**
     * Display admin notice
     *
     * @param string $type    Notice type (success, error, warning, info)
     * @param string $message Notice message
     * @return void
     */
    protected function display_notice(string $type, string $message): void {
        $allowed_types = ['success', 'error', 'warning', 'info'];
        $type = in_array($type, $allowed_types) ? $type : 'info';
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
    
    /**
     * Redirect with notice parameters
     *
     * @param string $page    Page slug to redirect to
     * @param string $type    Notice type
     * @param string $message Notice message
     * @return void
     */
    protected function redirect_with_notice(string $page, string $type, string $message): void {
        $redirect_url = add_query_arg(
            [
                'page' => $page,
                'penalis_notice' => urlencode($message),
                'penalis_type' => $type
            ],
            admin_url('admin.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Show admin notices from URL parameters
     *
     * @return void
     */
    public function show_admin_notices(): void {
        if (!isset($_GET['penalis_notice']) || !isset($_GET['penalis_type'])) {
            return;
        }
        
        $message = sanitize_text_field($_GET['penalis_notice']);
        $type = sanitize_text_field($_GET['penalis_type']);
        
        $this->display_notice($type, $message);
    }
    
    /**
     * Enqueue admin assets (CSS and JavaScript)
     *
     * @return void
     */
    public function enqueue_assets(): void {
        // Enqueue CSS
        wp_enqueue_style(
            'penalis-admin',
            PENALIS_EMAILER_URL . 'assets/css/admin.css',
            [],
            PENALIS_EMAILER_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'penalis-admin',
            PENALIS_EMAILER_URL . 'assets/js/admin.js',
            ['jquery'],
            PENALIS_EMAILER_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('penalis-admin', 'penalisAdmin', $this->get_localized_data());
    }
    
    /**
     * Get localized data for JavaScript
     *
     * @return array
     */
    protected function get_localized_data(): array {
        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'siteUrl' => home_url(),
            'siteName' => get_bloginfo('name'),
            'currentDate' => date_i18n(get_option('date_format')),
            'nonces' => [
                'preview' => wp_create_nonce('penalis_preview_email'),
                'previewAuto' => wp_create_nonce('penalis_preview_auto_email'),
                'testEmail' => wp_create_nonce('penalis_send_test_email'),
                'getAllUserIds' => wp_create_nonce('penalis_get_all_user_ids'),
                'bulkDeleteLogs' => wp_create_nonce('penalis_bulk_delete_logs'),
                'clearAllLogs' => wp_create_nonce('penalis_clear_all_logs'),
            ],
            'i18n' => [
                'selectRecipients' => __('Please select at least one recipient.', 'penalis-emailer'),
                'confirmSend' => __('Are you sure you want to send this email?', 'penalis-emailer'),
                'subject' => __('Subject:', 'penalis-emailer'),
                'recipients' => __('Recipients:', 'penalis-emailer'),
                'users' => __('users', 'penalis-emailer'),
                'enterBodyFirst' => __('Please enter email body first.', 'penalis-emailer'),
                'previewFailed' => __('Failed to generate preview.', 'penalis-emailer'),
                'confirmTestEmail' => __('Send a test email to your admin email address?', 'penalis-emailer'),
                'sending' => __('Sending...', 'penalis-emailer'),
                'sendTestEmail' => __('Send Test Email', 'penalis-emailer'),
                'testEmailSent' => __('Test email sent successfully! Check your inbox.', 'penalis-emailer'),
                'testEmailFailed' => __('Failed to send test email:', 'penalis-emailer'),
                // User selection i18n
                'selectingAllUsers' => __('Selecting all users...', 'penalis-emailer'),
                'failedToLoadUsers' => __('Failed to load all users. Please try again.', 'penalis-emailer'),
                // Delete history i18n
                'selectLogs' => __('Please select at least one email log to delete.', 'penalis-emailer'),
                'confirmBulkDelete' => __('Are you sure you want to delete %d email logs?', 'penalis-emailer'),
                'confirmClearAll' => __('Are you sure you want to clear all %s email history? This action cannot be undone.', 'penalis-emailer'),
                'confirmClearAllFinal' => __('This will permanently delete all emails in this tab. Are you absolutely sure?', 'penalis-emailer'),
            ]
        ];
    }
    
    /**
     * Render the page (must be implemented by child classes)
     *
     * @return void
     */
    abstract public function render(): void;
}
