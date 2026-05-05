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
     * Constructor
     *
     * @param Penalis_Email_Sender    $email_sender Email sender instance
     * @param Penalis_Email_Validator $validator    Email validator instance
     */
    public function __construct(Penalis_Email_Sender $email_sender, Penalis_Email_Validator $validator) {
        $this->email_sender = $email_sender;
        $this->validator = $validator;
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
        
        // Get current page for pagination
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Get eligible users
        $users = $this->get_eligible_users($current_page);
        $total_users = $this->get_total_users();
        $total_pages = ceil($total_users / Penalis_Config::get_users_per_page());
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Compose Email', 'penalis-emailer'); ?></h1>

            <p class="description">
                <?php echo esc_html__('Manually send emails to authors and contributors to provide information or notifications.', 'penalis-emailer'); ?>
            </p>
            
            <?php
            // Render form
            $this->render_form($users, $current_page, $total_pages, $total_users);
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
        
        // Sanitize inputs
        $sanitized = $this->sanitize_inputs($_POST);
        
        // Validate using validator class
        if (!$this->validator->validate_manual_email($sanitized)) {
            $error_message = $this->validator->get_first_error();
            $this->redirect_with_notice($this->page_slug, 'error', $error_message);
            return;
        }
        
        // Send emails
        $results = $this->email_sender->send_manual_email(
            $sanitized['subject'],
            $sanitized['user_ids'],
            $sanitized['body'],
            $sanitized['from_name']
        );
        
        // Prepare notice message
        $this->handle_send_results($results);
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
     * @param array $results Send results from email sender
     * @return void
     */
    private function handle_send_results(array $results): void {
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
     * @param array $users        Array of WP_User objects
     * @param int   $current_page Current page number
     * @param int   $total_pages  Total number of pages
     * @param int   $total_users  Total number of users
     * @return void
     */
    private function render_form(array $users, int $current_page, int $total_pages, int $total_users): void {
        ?>
        <div class="penalis-compose-form">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="penalis-email-form">
                <?php wp_nonce_field('penalis_send_email', 'penalis_email_nonce'); ?>
                <input type="hidden" name="action" value="penalis_send_email">
                
                <!-- Email Details Card -->
                <?php $this->render_email_details_card(); ?>
                
                <!-- Email Content Card -->
                <?php $this->render_email_content_card(); ?>
                
                <!-- Recipients Card -->
                <?php $this->render_recipients_card($users, $current_page, $total_pages, $total_users); ?>
                
                <!-- Action Buttons -->
                <p class="submit penalis-submit-actions">
                    <button type="button" id="preview-email-btn" class="button penalis-btn-secondary">
                        <?php echo esc_html__('Preview Email', 'penalis-emailer'); ?>
                    </button>
                    
                    <button type="submit" class="button penalis-btn-primary" style="margin-left: 10px;">
                        <?php echo esc_html__('Send Email', 'penalis-emailer'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render email details card
     *
     * @return void
     */
    private function render_email_details_card(): void {
        require PENALIS_EMAILER_PATH . 'includes/admin/views/email-details-card.php';
    }
    
    /**
     * Render email content card
     *
     * @return void
     */
    private function render_email_content_card(): void {
        require PENALIS_EMAILER_PATH . 'includes/admin/views/email-content-card.php';
    }
    
    /**
     * Render recipients card
     *
     * @param array $users        Array of WP_User objects
     * @param int   $current_page Current page number
     * @param int   $total_pages  Total number of pages
     * @param int   $total_users  Total number of users
     * @return void
     */
    private function render_recipients_card(array $users, int $current_page, int $total_pages, int $total_users): void {
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
