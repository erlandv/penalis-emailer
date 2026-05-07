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
        
        // Get search query
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Get all drafts
        $all_drafts = $this->logger->get_drafts();
        
        // Filter drafts by search
        if (!empty($search)) {
            $all_drafts = array_filter($all_drafts, function($draft) use ($search) {
                $subject = strtolower($draft['subject'] ?? '');
                $body = strtolower($draft['body'] ?? '');
                $search_lower = strtolower($search);
                
                return strpos($subject, $search_lower) !== false || 
                       strpos($body, $search_lower) !== false;
            });
        }
        
        // Get sort parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'updated_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        
        // Sort drafts
        $drafts = $this->sort_drafts($all_drafts, $orderby, $order);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Draft Management', 'penalis-emailer'); ?></h1>

            <p class="description">
                <?php echo esc_html__('Manage your email drafts. Edit, preview, send, or delete drafts from here.', 'penalis-emailer'); ?>
            </p>
            
            <?php if (empty($all_drafts)): ?>
                <!-- Empty State -->
                <div class="penalis-empty-state">
                    <span class="dashicons dashicons-edit-large"></span>
                    <h2><?php echo esc_html__('No Drafts Yet', 'penalis-emailer'); ?></h2>
                    <p><?php echo esc_html__('You haven\'t created any email drafts yet. Start composing an email and save it as a draft.', 'penalis-emailer'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-compose')); ?>" class="button button-primary">
                        <?php echo esc_html__('Compose Email', 'penalis-emailer'); ?>
                    </a>
                </div>
            <?php elseif (empty($drafts) && !empty($search)): ?>
                <!-- No Search Results -->
                <div class="penalis-empty-state">
                    <span class="dashicons dashicons-search"></span>
                    <h2><?php echo esc_html__('No Drafts Found', 'penalis-emailer'); ?></h2>
                    <p><?php printf(esc_html__('No drafts match your search for "%s".', 'penalis-emailer'), esc_html($search)); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-drafts')); ?>" class="button">
                        <?php echo esc_html__('Clear Search', 'penalis-emailer'); ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Drafts Table -->
                <?php $this->render_drafts_table($drafts, $search, $orderby, $order); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render drafts table
     *
     * @param array  $drafts  Array of draft entries
     * @param string $search  Search query
     * @param string $orderby Order by column
     * @param string $order   Order direction
     * @return void
     */
    private function render_drafts_table(array $drafts, string $search = '', string $orderby = 'updated_at', string $order = 'desc'): void {
        ?>
        <form method="post" id="drafts-filter">
            <!-- Search Box -->
            <p class="search-box">
                <label class="screen-reader-text" for="draft-search-input">
                    <?php echo esc_html__('Search Drafts:', 'penalis-emailer'); ?>
                </label>
                <input type="search" 
                       id="draft-search-input" 
                       name="s" 
                       value="<?php echo esc_attr($search); ?>"
                       placeholder="<?php echo esc_attr__('Search drafts...', 'penalis-emailer'); ?>">
                <button type="submit" id="search-submit" class="button">
                    <?php echo esc_html__('Search Drafts', 'penalis-emailer'); ?>
                </button>
            </p>
            
            <!-- Bulk Actions -->
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
                
                <div class="alignright">
                    <span class="displaying-num">
                        <?php 
                        if (!empty($search)) {
                            printf(
                                esc_html(_n('%s draft found', '%s drafts found', count($drafts), 'penalis-emailer')), 
                                number_format_i18n(count($drafts))
                            );
                        } else {
                            printf(
                                esc_html(_n('%s draft', '%s drafts', count($drafts), 'penalis-emailer')), 
                                number_format_i18n(count($drafts))
                            );
                        }
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-drafts">
                        </td>
                        <th scope="col" class="manage-column column-subject sortable <?php echo $orderby === 'subject' ? 'sorted' : 'sortable'; ?> <?php echo $orderby === 'subject' ? $order : 'desc'; ?>">
                            <a href="<?php echo esc_url($this->get_sort_url('subject', $orderby, $order, $search)); ?>">
                                <span><?php echo esc_html__('Subject', 'penalis-emailer'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-recipients sortable <?php echo $orderby === 'recipient_count' ? 'sorted' : 'sortable'; ?> <?php echo $orderby === 'recipient_count' ? $order : 'desc'; ?>">
                            <a href="<?php echo esc_url($this->get_sort_url('recipient_count', $orderby, $order, $search)); ?>">
                                <span><?php echo esc_html__('Recipients', 'penalis-emailer'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-created sortable <?php echo $orderby === 'created_at' ? 'sorted' : 'sortable'; ?> <?php echo $orderby === 'created_at' ? $order : 'desc'; ?>">
                            <a href="<?php echo esc_url($this->get_sort_url('created_at', $orderby, $order, $search)); ?>">
                                <span><?php echo esc_html__('Created', 'penalis-emailer'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-modified sortable <?php echo $orderby === 'updated_at' ? 'sorted' : 'sortable'; ?> <?php echo $orderby === 'updated_at' ? $order : 'desc'; ?>">
                            <a href="<?php echo esc_url($this->get_sort_url('updated_at', $orderby, $order, $search)); ?>">
                                <span><?php echo esc_html__('Last Modified', 'penalis-emailer'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-actions">
                            <?php echo esc_html__('Actions', 'penalis-emailer'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody id="drafts-table-body">
                    <?php foreach ($drafts as $draft): ?>
                        <?php $this->render_draft_row($draft); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Bottom Bulk Actions -->
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-bottom" class="screen-reader-text">
                        <?php echo esc_html__('Select bulk action', 'penalis-emailer'); ?>
                    </label>
                    <select name="action2" id="bulk-action-selector-bottom">
                        <option value="-1"><?php echo esc_html__('Bulk Actions', 'penalis-emailer'); ?></option>
                        <option value="delete"><?php echo esc_html__('Delete', 'penalis-emailer'); ?></option>
                    </select>
                    <button type="button" id="doaction2" class="button action">
                        <?php echo esc_html__('Apply', 'penalis-emailer'); ?>
                    </button>
                </div>
            </div>
        </form>
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
        $created_by = $draft['created_by'] ?? 0;
        $creator = get_userdata($created_by);
        $creator_name = $creator ? $creator->display_name : __('Unknown', 'penalis-emailer');
        
        // Format dates
        $created_time = human_time_diff($created_at, time());
        $updated_time = human_time_diff($updated_at, time());
        
        // Edit URL
        $edit_url = add_query_arg([
            'page' => 'penalis-email-compose',
            'draft_id' => $draft_id
        ], admin_url('admin.php'));
        
        ?>
        <tr data-draft-id="<?php echo esc_attr($draft_id); ?>">
            <th scope="row" class="check-column">
                <input type="checkbox" 
                       name="draft_ids[]" 
                       value="<?php echo esc_attr($draft_id); ?>"
                       class="draft-checkbox">
            </th>
            <td class="column-subject">
                <strong>
                    <a href="<?php echo esc_url($edit_url); ?>" class="row-title">
                        <?php echo esc_html($subject); ?>
                    </a>
                </strong>
                <div class="row-actions">
                    <span class="edit">
                        <a href="<?php echo esc_url($edit_url); ?>">
                            <?php echo esc_html__('Edit', 'penalis-emailer'); ?>
                        </a> |
                    </span>
                    <span class="preview">
                        <a href="#" class="preview-draft" data-draft-id="<?php echo esc_attr($draft_id); ?>">
                            <?php echo esc_html__('Preview', 'penalis-emailer'); ?>
                        </a> |
                    </span>
                    <span class="send">
                        <a href="#" class="send-draft" data-draft-id="<?php echo esc_attr($draft_id); ?>">
                            <?php echo esc_html__('Send', 'penalis-emailer'); ?>
                        </a> |
                    </span>
                    <span class="duplicate">
                        <a href="#" class="duplicate-draft" data-draft-id="<?php echo esc_attr($draft_id); ?>">
                            <?php echo esc_html__('Duplicate', 'penalis-emailer'); ?>
                        </a> |
                    </span>
                    <span class="trash">
                        <a href="#" class="delete-draft" data-draft-id="<?php echo esc_attr($draft_id); ?>">
                            <?php echo esc_html__('Delete', 'penalis-emailer'); ?>
                        </a>
                    </span>
                </div>
            </td>
            <td class="column-recipients">
                <?php 
                if ($recipient_count > 0) {
                    printf(
                        esc_html(_n('%d recipient', '%d recipients', $recipient_count, 'penalis-emailer')),
                        $recipient_count
                    );
                } else {
                    echo '<span style="color: #999;">' . esc_html__('No recipients', 'penalis-emailer') . '</span>';
                }
                ?>
            </td>
            <td class="column-created">
                <?php 
                echo esc_html($created_time) . ' ' . esc_html__('ago', 'penalis-emailer');
                ?>
                <br>
                <small style="color: #666;">
                    <?php echo esc_html__('by', 'penalis-emailer') . ' ' . esc_html($creator_name); ?>
                </small>
            </td>
            <td class="column-modified">
                <?php 
                echo esc_html($updated_time) . ' ' . esc_html__('ago', 'penalis-emailer');
                ?>
            </td>
            <td class="column-actions">
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                    <?php echo esc_html__('Edit', 'penalis-emailer'); ?>
                </a>
                <button type="button" class="button button-small send-draft-btn" data-draft-id="<?php echo esc_attr($draft_id); ?>">
                    <?php echo esc_html__('Send', 'penalis-emailer'); ?>
                </button>
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
        
        // Convert draft to sent
        if ($results['success'] > 0) {
            $this->logger->convert_draft_to_sent($draft_id, time());
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
    
    /**
     * Sort drafts by column
     *
     * @param array  $drafts  Array of drafts
     * @param string $orderby Column to sort by
     * @param string $order   Sort direction (asc/desc)
     * @return array Sorted drafts
     */
    private function sort_drafts(array $drafts, string $orderby, string $order): array {
        usort($drafts, function($a, $b) use ($orderby, $order) {
            $value_a = $a[$orderby] ?? '';
            $value_b = $b[$orderby] ?? '';
            
            // Handle different data types
            if ($orderby === 'subject') {
                $value_a = strtolower($value_a);
                $value_b = strtolower($value_b);
                $comparison = strcmp($value_a, $value_b);
            } else {
                $comparison = $value_a - $value_b;
            }
            
            return $order === 'asc' ? $comparison : -$comparison;
        });
        
        return $drafts;
    }
    
    /**
     * Get sort URL
     *
     * @param string $column  Column to sort by
     * @param string $current_orderby Current orderby
     * @param string $current_order   Current order
     * @param string $search  Search query
     * @return string Sort URL
     */
    private function get_sort_url(string $column, string $current_orderby, string $current_order, string $search): string {
        // Toggle order if clicking same column
        $new_order = ($current_orderby === $column && $current_order === 'asc') ? 'desc' : 'asc';
        
        $args = [
            'page' => 'penalis-email-drafts',
            'orderby' => $column,
            'order' => $new_order
        ];
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        return add_query_arg($args, admin_url('admin.php'));
    }
}
