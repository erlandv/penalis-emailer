<?php
/**
 * Compose Email Page Class
 *
 * Handles the compose email tab in the admin interface.
 * Provides UI for manual email composition and sending.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Compose_Page
 *
 * Manages the compose email interface.
 */
class Penalis_Compose_Page extends Penalis_Admin_Page {
    
    /**
     * Email sender instance
     *
     * @var Penalis_Email_Sender
     */
    private $email_sender;
    
    /**
     * Email validator instance
     *
     * @var Penalis_Email_Validator
     */
    private $validator;
    
    /**
     * Email logger instance
     *
     * @var Penalis_Email_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Sender    $email_sender Email sender instance
     * @param Penalis_Email_Validator $validator    Email validator instance
     * @param Penalis_Email_Logger    $logger       Email logger instance
     */
    public function __construct(
        Penalis_Email_Sender $email_sender, 
        Penalis_Email_Validator $validator,
        Penalis_Email_Logger $logger
    ) {
        $this->email_sender = $email_sender;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->page_slug = 'penalis-email-compose';
    }
    
    /**
     * Render compose email page
     *
     * @return void
     */
    public function render(): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Check if loading a draft
        $draft_data = null;
        if (isset($_GET['draft_id'])) {
            $draft_id = sanitize_text_field($_GET['draft_id']);
            $draft_data = $this->logger->find_draft_by_id($draft_id);
        }
        
        // Get ALL eligible users (no pagination)
        $users = $this->get_all_eligible_users();
        $total_users = count($users);
        
        // Get all drafts for dropdown
        $drafts = $this->logger->get_drafts(50); // Limit to 50 most recent drafts
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Compose Email', 'penalis-emailer'); ?></h1>

            <p class="description">
                <?php echo esc_html__('Manually send emails to authors and contributors to provide information or notifications.', 'penalis-emailer'); ?>
            </p>
            
            <?php
            // Render form (pass dummy values for pagination since we show all)
            $this->render_form($users, 1, 1, $total_users, $drafts, $draft_data);
            $this->render_preview_modal();
            ?>
        </div>
        <?php
    }
    
    /**
     * Handle form submission
     *
     * @return void
     */
    public function handle_submission(): void {
        // Verify security
        if (!$this->verify_security('penalis_send_email', 'penalis_email_nonce')) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }
        
        // Check if this is a save draft action
        if (isset($_POST['action_type']) && $_POST['action_type'] === 'save_draft') {
            $this->handle_save_draft();
            return;
        }
        
        // Otherwise, handle normal send email
        // Sanitize inputs
        $sanitized = $this->sanitize_inputs($_POST);
        
        // Validate using validator class
        if (!$this->validator->validate_manual_email($sanitized)) {
            $error_message = $this->validator->get_first_error();
            $this->redirect_with_notice($this->page_slug, 'error', $error_message);
            return;
        }
        
        // Check if sending from draft
        $draft_id = isset($_POST['draft_id']) ? sanitize_text_field($_POST['draft_id']) : '';
        
        // Send emails
        $results = $this->email_sender->send_manual_email(
            $sanitized['subject'],
            $sanitized['user_ids'],
            $sanitized['body'],
            $sanitized['from_name']
        );
        
        // If sent from draft, delete the draft (log already created by send_manual_email)
        if (!empty($draft_id)) {
            $this->logger->delete_draft($draft_id);
        }
        
        // Prepare notice message
        $this->handle_send_results($results);
    }
    
    /**
     * Handle save draft action
     *
     * @return void
     */
    private function handle_save_draft(): void {
        // Sanitize inputs
        $sanitized = $this->sanitize_inputs($_POST);
        
        // Validate draft (more lenient)
        if (!$this->validator->validate_draft($sanitized)) {
            $error_message = $this->validator->get_first_error();
            $this->redirect_with_notice($this->page_slug, 'error', $error_message);
            return;
        }
        
        // Check if updating existing draft
        $draft_id = isset($_POST['draft_id']) ? sanitize_text_field($_POST['draft_id']) : '';
        
        // Prepare draft data
        $draft_data = [
            'type' => 'manual',
            'from_name' => $sanitized['from_name'],
            'subject' => $sanitized['subject'],
            'body' => $sanitized['body'],
            'recipient_count' => count($sanitized['user_ids']),
            'recipients' => $sanitized['user_ids']
        ];
        
        // Save or update draft
        if (!empty($draft_id)) {
            // Update existing draft
            $success = $this->logger->update_draft($draft_id, $draft_data);
            $message = $success 
                ? __('Draft updated successfully.', 'penalis-emailer')
                : __('Failed to update draft.', 'penalis-emailer');
        } else {
            // Save new draft
            $success = $this->logger->save_draft($draft_data);
            $message = $success 
                ? __('Draft saved successfully.', 'penalis-emailer')
                : __('Failed to save draft.', 'penalis-emailer');
        }
        
        $type = $success ? 'success' : 'error';
        $this->redirect_with_notice($this->page_slug, $type, $message);
    }
    
    /**
     * Sanitize form inputs
     *
     * @param array $post_data Raw POST data
     * @return array Sanitized data
     */
    private function sanitize_inputs(array $post_data): array {
        return [
            'from_name' => sanitize_text_field($post_data['from_name'] ?? Penalis_Config::DEFAULT_FROM_NAME),
            'subject' => sanitize_text_field($post_data['subject'] ?? ''),
            'body' => wp_kses_post($post_data['body'] ?? ''),
            'user_ids' => isset($post_data['user_ids']) && is_array($post_data['user_ids']) 
                ? array_map('intval', $post_data['user_ids']) 
                : []
        ];
    }
    

    
    /**
     * Handle send results and redirect with appropriate notice
     *
     * Since v2.0.0, send_manual_email() is asynchronous — it enqueues
     * recipients and returns immediately. The notice reflects this.
     *
     * @param array $results Send results from email sender
     * @return void
     */
    private function handle_send_results(array $results): void {
        $invalid_count = count($results['failed']);

        // Async path (v2.0.0+)
        if (!empty($results['queued'])) {
            if ($results['success'] > 0 && $invalid_count === 0) {
                $message = sprintf(
                    __('%d email(s) have been queued and will be sent in the background.', 'penalis-emailer'),
                    $results['success']
                );
                $this->redirect_with_notice($this->page_slug, 'success', $message);
            } elseif ($results['success'] > 0 && $invalid_count > 0) {
                $message = sprintf(
                    __('%d email(s) queued. %d recipient(s) were skipped (invalid user or email).', 'penalis-emailer'),
                    $results['success'],
                    $invalid_count
                );
                $this->redirect_with_notice($this->page_slug, 'warning', $message);
            } else {
                $message = __('No valid recipients found. Please check your recipient list.', 'penalis-emailer');
                $this->redirect_with_notice($this->page_slug, 'error', $message);
            }
            return;
        }

        // Fallback: synchronous path (should not be reached in v2.0.0)
        if ($results['success'] > 0 && empty($results['failed'])) {
            $message = sprintf(
                __('Successfully sent %d email(s).', 'penalis-emailer'),
                $results['success']
            );
            $this->redirect_with_notice($this->page_slug, 'success', $message);
        } elseif ($results['success'] > 0 && !empty($results['failed'])) {
            $message = sprintf(
                __('Sent %d email(s) successfully, but %d failed.', 'penalis-emailer'),
                $results['success'],
                count($results['failed'])
            );
            $this->redirect_with_notice($this->page_slug, 'warning', $message);
        } else {
            $message = __('Failed to send emails. Please check your configuration.', 'penalis-emailer');
            $this->redirect_with_notice($this->page_slug, 'error', $message);
        }
    }
    
    /**
     * Get eligible users with pagination
     *
     * @param int $page Current page number
     * @return array Array of WP_User objects
     */
    private function get_eligible_users(int $page = 1): array {
        $per_page = Penalis_Config::get_users_per_page();
        
        $args = [
            'role__in' => Penalis_Config::get_eligible_roles(),
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ];
        
        return get_users($args);
    }
    
    /**
     * Get ALL eligible users (no pagination)
     *
     * @return array Array of WP_User objects
     */
    private function get_all_eligible_users(): array {
        $args = [
            'role__in' => Penalis_Config::get_eligible_roles(),
            'orderby' => 'display_name',
            'order' => 'ASC'
        ];
        
        return get_users($args);
    }
    
    /**
     * Get total number of eligible users
     *
     * @return int Total user count
     */
    private function get_total_users(): int {
        $args = [
            'role__in' => Penalis_Config::get_eligible_roles(),
            'count_total' => true
        ];
        
        $user_query = new WP_User_Query($args);
        return $user_query->get_total();
    }
    
    /**
     * Render the compose form
     *
     * @param array      $users        Array of WP_User objects
     * @param int        $current_page Current page number
     * @param int        $total_pages  Total number of pages
     * @param int        $total_users  Total number of users
     * @param array      $drafts       Array of draft entries
     * @param array|null $draft_data   Draft data if loading a draft
     * @return void
     */
    private function render_form(
        array $users, 
        int $current_page, 
        int $total_pages, 
        int $total_users,
        array $drafts = [],
        ?array $draft_data = null
    ): void {
        // Extract draft data if available
        $from_name = $draft_data['from_name'] ?? Penalis_Config::DEFAULT_FROM_NAME;
        $subject = $draft_data['subject'] ?? '';
        $body = $draft_data['body'] ?? '';
        $selected_recipients = $draft_data['recipients'] ?? [];
        $draft_id = $draft_data['id'] ?? '';
        
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="penalis-email-form">
            <?php wp_nonce_field('penalis_send_email', 'penalis_email_nonce'); ?>
            <input type="hidden" name="action" value="penalis_send_email">
            <input type="hidden" name="action_type" id="action-type" value="send">
            <input type="hidden" name="draft_id" id="draft-id" value="<?php echo esc_attr($draft_id); ?>">
            
            <!-- 2-Column Layout -->
            <div class="penalis-compose-layout">
                <!-- Main Content (Left) -->
                <div class="penalis-main-content">
                    
                    <!-- Draft Management Section -->
                    <?php if (!empty($drafts) || $draft_data): ?>
                    <div class="penalis-form-card penalis-draft-card">
                        <h3>
                            <span class="dashicons dashicons-media-document"></span>
                            <?php echo esc_html__('Draft Management', 'penalis-emailer'); ?>
                        </h3>
                        
                        <div class="penalis-draft-controls">
                            <?php if (!empty($drafts)): ?>
                            <div class="penalis-draft-select-group">
                                <select id="load-draft-select" class="regular-text">
                                    <option value=""><?php echo esc_html__('-- Select a draft --', 'penalis-emailer'); ?></option>
                                    <?php foreach ($drafts as $draft): ?>
                                        <option value="<?php echo esc_attr($draft['id']); ?>" <?php selected($draft_id, $draft['id']); ?>>
                                            <?php 
                                            $draft_subject = !empty($draft['subject']) ? $draft['subject'] : __('(No subject)', 'penalis-emailer');
                                            echo esc_html($draft_subject);
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="load-draft-btn" class="button">
                                    <?php echo esc_html__('Load', 'penalis-emailer'); ?>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($draft_data): ?>
                            <div class="penalis-draft-loaded">
                                <span class="penalis-draft-status">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <strong><?php echo esc_html__('Draft Loaded', 'penalis-emailer'); ?></strong>
                                </span>
                                <button type="button" id="clear-draft-btn" class="button">
                                    <?php echo esc_html__('Clear', 'penalis-emailer'); ?>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Email Details Card -->
                    <?php $this->render_email_details_card($from_name, $subject); ?>
                    
                    <!-- Email Content Card -->
                    <?php $this->render_email_content_card($body); ?>
                </div>
                
                <!-- Sidebar (Right) - Actions & Recipients -->
                <div class="penalis-sidebar">
                    <!-- Action Buttons (Invisible Card) -->
                    <div class="penalis-actions-wrapper">
                        <div class="penalis-actions-row">
                            <button type="button" id="preview-email-btn" class="button button-secondary">
                                <?php echo esc_html__('Preview', 'penalis-emailer'); ?>
                            </button>
                            
                            <button type="button" id="save-draft-btn" class="button button-secondary">
                                <?php echo esc_html__('Save Draft', 'penalis-emailer'); ?>
                            </button>
                        </div>
                        
                        <button type="submit" class="button button-primary penalis-btn-send-full">
                            <?php echo esc_html__('Send to', 'penalis-emailer'); ?>
                            <strong id="main-selected-count">0</strong>
                            <?php echo esc_html__('users', 'penalis-emailer'); ?>
                        </button>
                    </div>
                    
                    <!-- Recipients Card -->
                    <?php $this->render_recipients_card($users, $current_page, $total_pages, $total_users, $selected_recipients); ?>
                </div>
            </div>
        </form>
        <?php
    }
    
    /**
     * Render email details card
     *
     * @param string $from_name Default from name
     * @param string $subject   Default subject
     * @return void
     */
    private function render_email_details_card(string $from_name = '', string $subject = ''): void {
        if (empty($from_name)) {
            $from_name = Penalis_Config::DEFAULT_FROM_NAME;
        }
        
        // Pass variables to view
        $data = compact('from_name', 'subject');
        extract($data);
        
        require PENALIS_EMAILER_PATH . 'includes/admin/views/email-details-card.php';
    }
    
    /**
     * Render email content card
     *
     * @param string $body Default body content
     * @return void
     */
    private function render_email_content_card(string $body = ''): void {
        // Pass variables to view
        $data = compact('body');
        extract($data);
        
        require PENALIS_EMAILER_PATH . 'includes/admin/views/email-content-card.php';
    }
    
    /**
     * Render recipients card
     *
     * @param array $users               Array of WP_User objects
     * @param int   $current_page        Current page number
     * @param int   $total_pages         Total number of pages
     * @param int   $total_users         Total number of users
     * @param array $selected_recipients Array of selected user IDs
     * @return void
     */
    private function render_recipients_card(
        array $users, 
        int $current_page, 
        int $total_pages, 
        int $total_users,
        array $selected_recipients = []
    ): void {
        // Pass variables to view
        $data = compact('users', 'current_page', 'total_pages', 'total_users', 'selected_recipients');
        extract($data);
        
        require PENALIS_EMAILER_PATH . 'includes/admin/views/recipients-card.php';
    }
    
    /**
     * Render preview modal
     *
     * @return void
     */
    private function render_preview_modal(): void {
        require PENALIS_EMAILER_PATH . 'includes/admin/views/preview-modal.php';
    }
}
