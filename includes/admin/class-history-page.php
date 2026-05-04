<?php
/**
 * Email History Page Class
 *
 * Handles the email history tab in the admin interface.
 * Displays log of sent emails with filtering capabilities.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_History_Page
 *
 * Manages the email history interface.
 */
class Penalis_History_Page extends Penalis_Admin_Page {
    
    /**
     * Email logger instance
     *
     * @var Penalis_Email_Logger
     */
    private $email_logger;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Logger $email_logger Email logger instance
     */
    public function __construct(Penalis_Email_Logger $email_logger) {
        $this->email_logger = $email_logger;
        $this->page_slug = Penalis_Config::PAGE_SLUG;
    }
    
    /**
     * Render email history page
     *
     * @param string $tab Current tab (manual or automatic)
     * @return void
     */
    public function render(string $tab = 'manual'): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get email logs filtered by type
        $log_entries = $this->email_logger->get_all_email_log(Penalis_Config::DEFAULT_LOG_LIMIT, $tab);
        
        ?>
        <div class="penalis-history-list">
            <?php if (empty($log_entries)): ?>
                <p><?php echo esc_html__('No emails sent yet.', 'penalis-emailer'); ?></p>
            <?php else: ?>
                <?php $this->render_bulk_actions_bar($tab); ?>
                <?php $this->render_history_table($log_entries, $tab); ?>
                
                <p class="description">
                    <?php 
                    printf(
                        esc_html__('Showing last %d emails. Older entries are automatically archived.', 'penalis-emailer'),
                        Penalis_Config::DEFAULT_LOG_LIMIT
                    ); 
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render bulk actions bar
     *
     * @param string $tab Current tab
     * @return void
     */
    private function render_bulk_actions_bar(string $tab): void {
        $tab_label = $tab === 'manual' ? __('Manual', 'penalis-emailer') : __('Automatic', 'penalis-emailer');
        ?>
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select id="bulk-action-selector" class="regular-text" style="width: auto;">
                    <option value="-1"><?php echo esc_html__('Bulk Actions', 'penalis-emailer'); ?></option>
                    <option value="delete"><?php echo esc_html__('Delete', 'penalis-emailer'); ?></option>
                </select>
                <button type="button" class="button action" id="doaction">
                    <?php echo esc_html__('Apply', 'penalis-emailer'); ?>
                </button>
            </div>
            <div class="alignright actions">
                <button type="button" class="button button-secondary" id="clear-all-logs" data-type="<?php echo esc_attr($tab); ?>">
                    <?php 
                    printf(
                        esc_html__('Clear All %s History', 'penalis-emailer'),
                        $tab_label
                    ); 
                    ?>
                </button>
            </div>
            <br class="clear">
        </div>
        <?php
    }
    
    /**
     * Render history table
     *
     * @param array  $log_entries Array of log entries
     * @param string $tab         Current tab (manual or automatic)
     * @return void
     */
    private function render_history_table(array $log_entries, string $tab): void {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" id="cb" class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all-logs">
                    </th>
                    <?php if ($tab === 'manual'): ?>
                        <th scope="col"><?php echo esc_html__('Subject', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Recipients', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Sent By', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Sent At', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'penalis-emailer'); ?></th>
                    <?php else: ?>
                        <th scope="col"><?php echo esc_html__('Post', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Recipient', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Sent At', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'penalis-emailer'); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="history-table-body">
                <?php foreach ($log_entries as $entry): ?>
                    <?php $this->render_history_row($entry, $tab); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render single history row
     *
     * @param array  $entry Log entry data
     * @param string $tab   Current tab (manual or automatic)
     * @return void
     */
    private function render_history_row(array $entry, string $tab): void {
        // Handle both old and new log format
        $sent_time = isset($entry['sent_at']) ? $entry['sent_at'] : (isset($entry['timestamp']) ? $entry['timestamp'] : 0);
        
        // Generate ID if not exists (for old log entries)
        if (isset($entry['id'])) {
            $log_id = $entry['id'];
        } else {
            // Generate ID from timestamp and subject for old entries
            $subject_hash = substr(md5($entry['subject'] ?? ''), 0, 8);
            $log_id = 'legacy_' . $sent_time . '_' . $subject_hash;
        }
        
        ?>
        <tr class="history-row">
            <th scope="row" class="check-column">
                <input type="checkbox" class="log-checkbox" value="<?php echo esc_attr($log_id); ?>">
            </th>
            
            <?php if ($tab === 'manual'): ?>
                <!-- Manual Email Columns -->
                
                <!-- Subject Column -->
                <td>
                    <strong><?php echo esc_html($entry['subject']); ?></strong>
                    
                    <?php if (isset($entry['body_preview']) && !empty($entry['body_preview'])): ?>
                        <br>
                        <small style="color: #666;"><?php echo esc_html($entry['body_preview']); ?></small>
                    <?php endif; ?>
                </td>
                
                <!-- Recipients Column -->
                <td>
                    <?php 
                    $recipient_count = isset($entry['recipient_count']) ? $entry['recipient_count'] : 1;
                    echo esc_html($recipient_count) . ' ' . esc_html__('users', 'penalis-emailer'); 
                    ?>
                </td>
                
                <!-- Sent By Column -->
                <td>
                    <?php 
                    $sent_by_id = isset($entry['sent_by']) ? $entry['sent_by'] : 0;
                    $sent_by_user = $sent_by_id ? get_userdata($sent_by_id) : null;
                    
                    if ($sent_by_user) {
                        echo esc_html($sent_by_user->display_name);
                    } else {
                        echo esc_html__('Unknown', 'penalis-emailer');
                    }
                    ?>
                </td>
                
            <?php else: ?>
                <!-- Automatic Email Columns -->
                
                <!-- Post Column -->
                <td>
                    <?php if (isset($entry['post_title'])): ?>
                        <?php if (isset($entry['post_url'])): ?>
                            <a href="<?php echo esc_url($entry['post_url']); ?>" target="_blank">
                                <?php echo esc_html($entry['post_title']); ?>
                            </a>
                        <?php else: ?>
                            <?php echo esc_html($entry['post_title']); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <em style="color: #666;"><?php echo esc_html__('N/A', 'penalis-emailer'); ?></em>
                    <?php endif; ?>
                </td>
                
                <!-- Recipient Column -->
                <td>
                    <?php 
                    if (isset($entry['recipient_name'])) {
                        echo esc_html($entry['recipient_name']);
                    } else {
                        echo esc_html__('Unknown', 'penalis-emailer');
                    }
                    ?>
                </td>
                
            <?php endif; ?>
            
            <!-- Sent At Column (both tabs) -->
            <td>
                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $sent_time)); ?>
                <br>
                <small style="color: #666;">
                    <?php 
                    echo esc_html(human_time_diff($sent_time, current_time('timestamp'))); 
                    echo ' ';
                    echo esc_html__('ago', 'penalis-emailer'); 
                    ?>
                </small>
            </td>
            
            <!-- Status Column (both tabs) -->
            <td>
                <span style="color: #46b450;">●</span> 
                <?php echo esc_html__('Sent', 'penalis-emailer'); ?>
            </td>
        </tr>
        <?php
    }
}
