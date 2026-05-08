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
     * Queue repository instance
     *
     * @var Penalis_Email_Queue_Repository
     */
    private $queue;

    /**
     * Compose page instance (used for recipient queries)
     *
     * @var Penalis_Compose_Page
     */
    private $compose_page;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Template         $email_template Email template instance
     * @param Penalis_Email_Logger           $email_logger   Email logger instance
     * @param Penalis_Email_Queue_Repository $queue          Queue repository instance
     * @param Penalis_Compose_Page           $compose_page   Compose page instance
     */
    public function __construct(
        Penalis_Email_Template $email_template,
        Penalis_Email_Logger $email_logger,
        Penalis_Email_Queue_Repository $queue,
        Penalis_Compose_Page $compose_page
    ) {
        $this->email_template = $email_template;
        $this->email_logger   = $email_logger;
        $this->queue          = $queue;
        $this->compose_page   = $compose_page;
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
        
        // User selection
        add_action('wp_ajax_penalis_get_all_user_ids', [$this, 'get_all_user_ids']);
        add_action('wp_ajax_penalis_get_users_by_role', [$this, 'get_users_by_role']);
        add_action('wp_ajax_penalis_load_recipients',  [$this, 'load_recipients']);
        
        // Delete actions
        add_action('wp_ajax_penalis_bulk_delete_logs', [$this, 'bulk_delete_logs']);
        add_action('wp_ajax_penalis_clear_all_logs', [$this, 'clear_all_logs']);
        
        // Draft actions
        add_action('wp_ajax_penalis_delete_draft', [$this, 'delete_draft']);
        add_action('wp_ajax_penalis_bulk_delete_drafts', [$this, 'bulk_delete_drafts']);
        add_action('wp_ajax_penalis_send_draft_ajax', [$this, 'send_draft_ajax']);
        add_action('wp_ajax_penalis_autosave_draft', [$this, 'autosave_draft']);

        // Queue status (v2.0.0)
        add_action('wp_ajax_penalis_get_queue_status', [$this, 'get_queue_status']);
        add_action('wp_ajax_penalis_cancel_job',       [$this, 'cancel_job']);
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
     * AJAX handler for getting all eligible user IDs
     *
     * @return void
     */
    public function get_all_user_ids(): void {
        // Check nonce
        check_ajax_referer('penalis_get_all_user_ids', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        // Get all eligible users
        $args = [
            'role__in' => Penalis_Config::get_eligible_roles(),
            'fields' => 'ID',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ];
        
        $user_ids = get_users($args);
        
        wp_send_json_success([
            'user_ids' => $user_ids,
            'total' => count($user_ids)
        ]);
    }
    
    /**
     * AJAX handler for getting user IDs by role
     *
     * @return void
     */
    public function get_users_by_role(): void {
        // Check nonce
        check_ajax_referer('penalis_get_users_by_role', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        // Get and validate role
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
        
        $eligible_roles = Penalis_Config::get_eligible_roles();
        if (empty($role) || !in_array($role, $eligible_roles)) {
            wp_send_json_error(['message' => __('Invalid role', 'penalis-emailer')]);
        }
        
        // Get users by role
        $args = [
            'role' => $role,
            'fields' => 'ID',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ];
        
        $user_ids = get_users($args);
        
        wp_send_json_success([
            'user_ids' => $user_ids,
            'total' => count($user_ids),
            'role' => $role
        ]);
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
    
    /**
     * AJAX handler for delete draft
     *
     * @return void
     */
    public function delete_draft(): void {
        // Check nonce
        check_ajax_referer('penalis_delete_draft', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $draft_id = isset($_POST['draft_id']) ? sanitize_text_field($_POST['draft_id']) : '';
        
        if (empty($draft_id)) {
            wp_send_json_error(['message' => __('Draft ID is required', 'penalis-emailer')]);
        }
        
        // Delete draft
        $success = $this->email_logger->delete_draft($draft_id);
        
        if ($success) {
            wp_send_json_success([
                'message' => __('Draft deleted successfully', 'penalis-emailer')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete draft', 'penalis-emailer')
            ]);
        }
    }
    
    /**
     * AJAX handler for bulk delete drafts
     *
     * @return void
     */
    public function bulk_delete_drafts(): void {
        // Check nonce
        check_ajax_referer('penalis_bulk_delete_drafts', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $draft_ids = isset($_POST['draft_ids']) && is_array($_POST['draft_ids']) 
            ? array_map('sanitize_text_field', $_POST['draft_ids']) 
            : [];
        
        if (empty($draft_ids)) {
            wp_send_json_error(['message' => __('No drafts selected', 'penalis-emailer')]);
        }
        
        // Delete each draft
        $deleted_count = 0;
        foreach ($draft_ids as $draft_id) {
            if ($this->email_logger->delete_draft($draft_id)) {
                $deleted_count++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(__('%d draft(s) deleted successfully', 'penalis-emailer'), $deleted_count),
            'deleted_count' => $deleted_count
        ]);
    }
    
    /**
     * AJAX handler for send draft
     *
     * @return void
     */
    public function send_draft_ajax(): void {
        // Check nonce
        check_ajax_referer('penalis_send_draft_ajax', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $draft_id = isset($_POST['draft_id']) ? sanitize_text_field($_POST['draft_id']) : '';
        
        if (empty($draft_id)) {
            wp_send_json_error(['message' => __('Draft ID is required', 'penalis-emailer')]);
        }
        
        // Get draft
        $draft = $this->email_logger->find_draft_by_id($draft_id);
        
        if (!$draft) {
            wp_send_json_error(['message' => __('Draft not found', 'penalis-emailer')]);
        }
        
        // Validate for sending (strict validation)
        $validator = new Penalis_Email_Validator();
        if (!$validator->validate_manual_email($draft)) {
            $error_message = $validator->get_first_error();
            wp_send_json_error(['message' => $error_message]);
        }
        
        // Get email sender
        $email_sender = Penalis_Service_Container::get(Penalis_Email_Sender::class);
        
        // Send emails
        $results = $email_sender->send_manual_email(
            $draft['subject'],
            $draft['recipients'],
            $draft['body'],
            $draft['from_name']
        );
        
        // Delete draft after sending (log already created by send_manual_email)
        if ($results['success'] > 0) {
            $this->email_logger->delete_draft($draft_id);
        }
        
        // Return results
        if (!empty($results['queued'])) {
            // Async path (v2.0.0+)
            if ($results['success'] > 0) {
                $invalid_count = count($results['failed']);
                if ($invalid_count > 0) {
                    wp_send_json_success([
                        'message' => sprintf(
                            __('%d email(s) queued from draft. %d recipient(s) skipped (invalid).', 'penalis-emailer'),
                            $results['success'],
                            $invalid_count
                        ),
                        'queued'  => true,
                        'job_id'  => $results['job_id'],
                    ]);
                } else {
                    wp_send_json_success([
                        'message' => sprintf(
                            __('%d email(s) from draft have been queued and will be sent in the background.', 'penalis-emailer'),
                            $results['success']
                        ),
                        'queued'  => true,
                        'job_id'  => $results['job_id'],
                    ]);
                }
            } else {
                wp_send_json_error([
                    'message' => __('No valid recipients found in draft.', 'penalis-emailer'),
                ]);
            }
            return;
        }

        // Fallback: synchronous path
        if ($results['success'] > 0 && empty($results['failed'])) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully sent %d email(s) from draft.', 'penalis-emailer'),
                    $results['success']
                )
            ]);
        } elseif ($results['success'] > 0 && !empty($results['failed'])) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Sent %d email(s) successfully, but %d failed.', 'penalis-emailer'),
                    $results['success'],
                    count($results['failed'])
                )
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to send emails. Please check your configuration.', 'penalis-emailer')
            ]);
        }
    }
    
    /**
     * AJAX handler for auto-save draft
     *
     * @return void
     */
    public function autosave_draft(): void {
        // Check nonce
        check_ajax_referer('penalis_autosave_draft', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        // Get draft data
        $draft_id = isset($_POST['draft_id']) ? sanitize_text_field($_POST['draft_id']) : '';
        $from_name = isset($_POST['from_name']) ? sanitize_text_field($_POST['from_name']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';
        $user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids']) 
            ? array_map('intval', $_POST['user_ids']) 
            : [];
        
        // Prepare draft data
        $draft_data = [
            'type' => 'manual',
            'from_name' => $from_name,
            'subject' => $subject,
            'body' => $body,
            'recipient_count' => count($user_ids),
            'recipients' => $user_ids
        ];
        
        // Check if updating existing draft or creating new
        if (!empty($draft_id)) {
            // Update existing draft
            $success = $this->email_logger->update_draft($draft_id, $draft_data);
            
            if ($success) {
                wp_send_json_success([
                    'message' => __('Draft auto-saved', 'penalis-emailer'),
                    'draft_id' => $draft_id,
                    'timestamp' => time()
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to auto-save draft', 'penalis-emailer')
                ]);
            }
        } else {
            // Create new draft
            $success = $this->email_logger->save_draft($draft_data);
            
            if ($success) {
                // Get the newly created draft to return its ID
                $drafts = $this->email_logger->get_drafts(1);
                $new_draft_id = !empty($drafts) ? $drafts[0]['id'] : '';
                
                wp_send_json_success([
                    'message' => __('Draft auto-saved', 'penalis-emailer'),
                    'draft_id' => $new_draft_id,
                    'timestamp' => time()
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to auto-save draft', 'penalis-emailer')
                ]);
            }
        }
    }

    /**
     * AJAX handler for loading recipients (infinite scroll + search).
     *
     * Accepts:
     *   offset  (int)    — zero-based offset for load-more
     *   search  (string) — search term (triggers search mode, ignores offset)
     *
     * Returns an array of user HTML items ready to be appended to the list,
     * plus a `has_more` flag so JS knows whether to keep the scroll observer.
     *
     * @return void
     */
    public function load_recipients(): void {
        check_ajax_referer('penalis_load_recipients', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }

        $batch_size = Penalis_Config::RECIPIENTS_INITIAL_LOAD;
        $search     = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $offset     = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;

        if ($search !== '') {
            // Search mode — return matching users, no offset
            $users    = $this->compose_page->search_eligible_users($search, 50);
            $has_more = false; // Search always returns full result set (capped at 50)
        } else {
            // Load-more mode — return next batch by offset
            $users      = $this->compose_page->get_eligible_users_batch($offset, $batch_size);
            $total      = $this->get_total_eligible_users();
            $has_more   = ($offset + $batch_size) < $total;
        }

        // Build HTML for each user item — same markup as recipients-card.php
        $items_html = '';
        foreach ($users as $user) {
            $user_roles = implode(', ', array_map('ucfirst', $user->roles));
            $items_html .= sprintf(
                '<label class="penalis-user-item" data-name="%s" data-email="%s" data-role="%s">'
                . '<input type="checkbox" name="user_ids[]" value="%d" class="user-checkbox">'
                . '<div class="penalis-user-info">'
                . '<div class="penalis-user-name">%s</div>'
                . '<div class="penalis-user-meta">'
                . '<span class="penalis-user-email">%s</span>'
                . '<span class="penalis-user-role">%s</span>'
                . '</div></div></label>',
                esc_attr(strtolower($user->display_name)),
                esc_attr(strtolower($user->user_email)),
                esc_attr(implode(',', $user->roles)),
                $user->ID,
                esc_html($user->display_name),
                esc_html($user->user_email),
                esc_html($user_roles)
            );
        }

        wp_send_json_success([
            'html'     => $items_html,
            'has_more' => $has_more,
            'count'    => count($users),
        ]);
    }

    /**
     * Get total count of eligible users.
     *
     * @return int
     */
    private function get_total_eligible_users(): int {
        $query = new WP_User_Query([
            'role__in'    => Penalis_Config::get_eligible_roles(),
            'count_total' => true,
            'number'      => 0,
        ]);
        return (int) $query->get_total();
    }

    /**
     * AJAX handler for queue job status polling (v2.0.0)
     *
     * Returns progress data for a specific job_id so the frontend
     * can display a live progress indicator.
     *
     * @return void
     */
    public function get_queue_status(): void {
        check_ajax_referer('penalis_get_queue_status', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';

        if (empty($job_id)) {
            wp_send_json_error(['message' => __('Job ID is required', 'penalis-emailer')]);
        }

        $summary = $this->queue->get_job_summary($job_id);

        wp_send_json_success($summary);
    }

    /**
     * AJAX handler for cancelling a queue job (v2.0.0)
     *
     * Deletes all pending/failed items for the given job_id.
     * Already-sent items are left intact.
     *
     * @return void
     */
    public function cancel_job(): void {
        check_ajax_referer('penalis_cancel_job', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';

        if (empty($job_id)) {
            wp_send_json_error(['message' => __('Job ID is required', 'penalis-emailer')]);
        }

        // Only delete items that have NOT been sent yet
        global $wpdb;
        $table   = Penalis_Database::get_queue_table();
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                 WHERE job_id = %s
                   AND status IN ('pending', 'processing', 'failed')",
                $job_id
            )
        );

        wp_send_json_success([
            'message' => sprintf(
                __('Job cancelled. %d pending item(s) removed.', 'penalis-emailer'),
                (int) $deleted
            ),
            'deleted' => (int) $deleted,
        ]);
    }
}
