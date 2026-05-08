<?php
/**
 * Draft Management Page Class
 *
 * Handles the draft management page in the admin interface.
 * Provides UI for viewing, editing, and managing email drafts.
 *
 * @package Penalis_Emailer
 * @since 1.4.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Draft_Page
 *
 * Manages the draft management interface.
 */
class Penalis_Draft_Page extends Penalis_Admin_Page {
    
    /**
     * Email logger instance
     *
     * @var Penalis_Email_Logger
     */
    private $logger;
    
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
     * @param Penalis_Email_Logger    $logger       Email logger instance
     * @param Penalis_Email_Sender    $email_sender Email sender instance
     * @param Penalis_Email_Validator $validator    Email validator instance
     */
    public function __construct(
        Penalis_Email_Logger $logger,
        Penalis_Email_Sender $email_sender,
        Penalis_Email_Validator $validator
    ) {
        $this->logger = $logger;
        $this->email_sender = $email_sender;
        $this->validator = $validator;
        $this->page_slug = 'penalis-email-drafts';
    }
    
    /**
     * Render draft management page
     *
     * @return void
     */
    public function render(): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get all drafts
        $drafts = $this->logger->get_drafts();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Draft Management', 'penalis-emailer'); ?></h1>

            <p class="description">
                <?php echo esc_html__('Manage your email drafts. Edit, send, or delete drafts from here.', 'penalis-emailer'); ?>
            </p>
            
            <?php if (empty($drafts)): ?>
                <!-- Empty State -->
                <div class="penalis-empty-state">
                    <span class="dashicons dashicons-edit-large"></span>
                    <h2><?php echo esc_html__('No Drafts Yet', 'penalis-emailer'); ?></h2>
                    <p><?php echo esc_html__('You haven\'t created any email drafts yet. Start composing an email and save it as a draft.', 'penalis-emailer'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-compose')); ?>" class="button button-primary">
                        <?php echo esc_html__('Compose Email', 'penalis-emailer'); ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Drafts Table -->
                <?php $this->render_drafts_table($drafts); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render drafts table
     *
     * @param array $drafts Array of draft entries
     * @return void
     */
    private function render_drafts_table(array $drafts): void {
        ?>
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">
                    <?php echo esc_html__('Select bulk action', 'penalis-emailer'); ?>
                </label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php echo esc_html__('Bulk Actions', 'penalis-emailer'); ?></option>
                    <option value="delete"><?php echo esc_html__('Delete', 'penalis-emailer'); ?></option>
                </select>
                <button type="button" id="doaction" class="button action">
                    <?php echo esc_html__('Apply', 'penalis-emailer'); ?>
                </button>
            </div>
            <br class="clear">
        </div>
        
        <!-- Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all-drafts">
                        <label for="select-all-drafts">
                            <span class="screen-reader-text"><?php echo esc_html__('Select All', 'penalis-emailer'); ?></span>
                        </label>
                    </td>
                    <th scope="col"><?php echo esc_html__('Subject', 'penalis-emailer'); ?></th>
                    <th scope="col" style="width: 80px;"><?php echo esc_html__('Recipients', 'penalis-emailer'); ?></th>
                    <th scope="col"><?php echo esc_html__('Created', 'penalis-emailer'); ?></th>
                    <th scope="col"><?php echo esc_html__('Last Modified', 'penalis-emailer'); ?></th>
                </tr>
            </thead>
            <tbody id="drafts-table-body">
                <?php foreach ($drafts as $draft): ?>
                    <?php $this->render_draft_row($draft); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render single draft row
     *
     * @param array $draft Draft entry
     * @return void
     */
    private function render_draft_row(array $draft): void {
        $draft_id = $draft['id'] ?? '';
        $subject = !empty($draft['subject']) ? $draft['subject'] : __('(No subject)', 'penalis-emailer');
        $recipient_count = $draft['recipient_count'] ?? 0;
        $created_at = $draft['created_at'] ?? 0;
        $updated_at = $draft['updated_at'] ?? $created_at;
        
        // Get creator info
        $created_by   = $draft['created_by'] ?? 0;
        $updated_by   = $draft['updated_by']  ?? $created_by;
        $creator      = get_userdata($created_by);
        $editor       = get_userdata($updated_by);
        $creator_name = $creator ? $creator->display_name : __('Unknown', 'penalis-emailer');
        $editor_name  = $editor  ? $editor->display_name  : __('Unknown', 'penalis-emailer');
        
        // Edit URL
        $edit_url = add_query_arg([
            'page' => 'penalis-email-compose',
            'draft_id' => $draft_id
        ], admin_url('admin.php'));
        
        ?>
        <tr class="draft-row" data-draft-id="<?php echo esc_attr($draft_id); ?>">
            <th scope="row" class="check-column">
                <input type="checkbox" 
                       id="cb-select-<?php echo esc_attr($draft_id); ?>"
                       class="draft-checkbox" 
                       value="<?php echo esc_attr($draft_id); ?>">
                <label for="cb-select-<?php echo esc_attr($draft_id); ?>">
                    <span class="screen-reader-text"><?php echo esc_html__('Select draft', 'penalis-emailer'); ?></span>
                </label>
            </th>
            <td>
                <strong>
                    <a href="<?php echo esc_url($edit_url); ?>" class="row-title">
                        <?php echo esc_html($subject); ?>
                    </a>
                </strong>
                
                <?php if (isset($draft['body']) && !empty($draft['body'])): ?>
                    <?php 
                    // Generate body preview
                    $body_preview = mb_substr(strip_tags($draft['body']), 0, 100);
                    if (mb_strlen(strip_tags($draft['body'])) > 100) {
                        $body_preview .= '...';
                    }
                    ?>
                    <br>
                    <small style="color: #666;"><?php echo esc_html($body_preview); ?></small>
                <?php endif; ?>
                
                <div class="row-actions">
                    <span class="edit">
                        <a href="<?php echo esc_url($edit_url); ?>">
                            <?php echo esc_html__('Edit', 'penalis-emailer'); ?>
                        </a> |
                    </span>
                    <span class="send">
                        <a href="#" class="send-draft" data-draft-id="<?php echo esc_attr($draft_id); ?>">
                            <?php echo esc_html__('Send', 'penalis-emailer'); ?>
                        </a> |
                    </span>
                    <span class="trash">
                        <a href="#" class="delete-draft" data-draft-id="<?php echo esc_attr($draft_id); ?>">
                            <?php echo esc_html__('Delete', 'penalis-emailer'); ?>
                        </a>
                    </span>
                </div>
            </td>
            <td style="text-align: center;">
                <?php 
                if ($recipient_count > 0) {
                    echo '<strong>' . esc_html($recipient_count) . '</strong>';
                } else {
                    echo '<span style="color: #999;">0</span>';
                }
                ?>
            </td>
            <td>
                <?php 
                // Use wp_date() for proper timezone handling
                if (function_exists('wp_date')) {
                    echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $created_at));
                } else {
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $created_at + (get_option('gmt_offset') * HOUR_IN_SECONDS)));
                }
                ?>
                <br>
                <small style="color: #666;">
                    <?php 
                    echo esc_html(human_time_diff($created_at, current_time('timestamp', true))); 
                    echo ' ';
                    echo esc_html__('ago', 'penalis-emailer');
                    echo ' ' . esc_html__('by', 'penalis-emailer') . ' ' . esc_html($creator_name);
                    ?>
                </small>
            </td>
            <td>
                <?php 
                // Use wp_date() for proper timezone handling
                if (function_exists('wp_date')) {
                    echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $updated_at));
                } else {
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $updated_at + (get_option('gmt_offset') * HOUR_IN_SECONDS)));
                }
                ?>
                <br>
                <small style="color: #666;">
                    <?php
                    echo esc_html(human_time_diff($updated_at, current_time('timestamp', true)));
                    echo ' ';
                    echo esc_html__('ago', 'penalis-emailer');
                    // Show who last edited, but only if different from creator
                    if ($updated_by && $updated_by !== $created_by) {
                        echo ' ' . esc_html__('by', 'penalis-emailer') . ' <strong>' . esc_html($editor_name) . '</strong>';
                    } elseif ($updated_by) {
                        echo ' ' . esc_html__('by', 'penalis-emailer') . ' ' . esc_html($editor_name);
                    }
                    ?>
                </small>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Handle send draft from management page
     *
     * @return void
     */
    public function handle_send_draft(): void {
        // Verify security
        if (!$this->verify_security('penalis_send_draft', 'penalis_draft_nonce')) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }
        
        $draft_id = isset($_POST['draft_id']) ? sanitize_text_field($_POST['draft_id']) : '';
        
        if (empty($draft_id)) {
            $this->redirect_with_notice($this->page_slug, 'error', __('Invalid draft ID.', 'penalis-emailer'));
            return;
        }
        
        // Get draft
        $draft = $this->logger->find_draft_by_id($draft_id);
        
        if (!$draft) {
            $this->redirect_with_notice($this->page_slug, 'error', __('Draft not found.', 'penalis-emailer'));
            return;
        }
        
        // Validate for sending (strict validation)
        if (!$this->validator->validate_manual_email($draft)) {
            $error_message = $this->validator->get_first_error();
            $this->redirect_with_notice($this->page_slug, 'error', $error_message);
            return;
        }
        
        // Send emails
        $results = $this->email_sender->send_manual_email(
            $draft['subject'],
            $draft['recipients'],
            $draft['body'],
            $draft['from_name']
        );
        
        // Delete draft after sending (log already created by send_manual_email)
        if ($results['success'] > 0) {
            $this->logger->delete_draft($draft_id);
        }
        
        // Prepare notice message
        if ($results['success'] > 0 && empty($results['failed'])) {
            $message = sprintf(
                __('Successfully sent %d email(s) from draft.', 'penalis-emailer'),
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
    
}
