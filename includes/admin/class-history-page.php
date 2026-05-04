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
     * @return void
     */
    public function render(): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get all email logs (both manual and automatic)
        $log_entries = $this->email_logger->get_all_email_log(Penalis_Config::DEFAULT_LOG_LIMIT);
        
        ?>
        <div class="penalis-history-list">
            <h2><?php echo esc_html__('Email History', 'penalis-emailer'); ?></h2>
            
            <?php if (empty($log_entries)): ?>
                <p><?php echo esc_html__('No emails sent yet.', 'penalis-emailer'); ?></p>
            <?php else: ?>
                <?php $this->render_filter_box(); ?>
                <?php $this->render_history_table($log_entries); ?>
                
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
     * Render filter box
     *
     * @return void
     */
    private function render_filter_box(): void {
        ?>
        <div class="penalis-user-selection">
            <div class="penalis-user-actions">
                <select id="email-type-filter" class="regular-text" style="width: auto;">
                    <option value="all"><?php echo esc_html__('All Emails', 'penalis-emailer'); ?></option>
                    <option value="manual"><?php echo esc_html__('Manual Only', 'penalis-emailer'); ?></option>
                    <option value="automatic"><?php echo esc_html__('Automatic Only', 'penalis-emailer'); ?></option>
                </select>
                <input type="text" 
                       id="history-search" 
                       class="regular-text" 
                       placeholder="<?php echo esc_attr__('Search by subject...', 'penalis-emailer'); ?>">
                <input type="date" 
                       id="history-date-filter" 
                       class="regular-text"
                       style="width: auto;">
                <button type="button" class="button" id="clear-history-filter">
                    <?php echo esc_html__('Clear Filters', 'penalis-emailer'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render history table
     *
     * @param array $log_entries Array of log entries
     * @return void
     */
    private function render_history_table(array $log_entries): void {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Subject', 'penalis-emailer'); ?></th>
                    <th><?php echo esc_html__('Recipients', 'penalis-emailer'); ?></th>
                    <th><?php echo esc_html__('Sent By', 'penalis-emailer'); ?></th>
                    <th><?php echo esc_html__('Sent At', 'penalis-emailer'); ?></th>
                    <th><?php echo esc_html__('Status', 'penalis-emailer'); ?></th>
                </tr>
            </thead>
            <tbody id="history-table-body">
                <?php foreach ($log_entries as $entry): ?>
                    <?php $this->render_history_row($entry); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render single history row
     *
     * @param array $entry Log entry data
     * @return void
     */
    private function render_history_row(array $entry): void {
        // Handle both old and new log format
        $sent_time = isset($entry['sent_at']) ? $entry['sent_at'] : (isset($entry['timestamp']) ? $entry['timestamp'] : 0);
        $recipient_count = isset($entry['recipient_count']) ? $entry['recipient_count'] : 1;
        $sent_by_id = isset($entry['sent_by']) ? $entry['sent_by'] : 0;
        $sent_by_user = $sent_by_id ? get_userdata($sent_by_id) : null;
        $body_preview = isset($entry['body_preview']) ? $entry['body_preview'] : '';
        $sent_date = date('Y-m-d', $sent_time);
        
        // Determine email type
        $email_type = isset($entry['type']) ? $entry['type'] : 'manual';
        $is_automatic = ($email_type === 'automatic');
        
        // Get recipient info for automatic emails
        $recipient_display = '';
        if ($is_automatic && isset($entry['recipient_name'])) {
            $recipient_display = $entry['recipient_name'];
        } else {
            $recipient_display = $recipient_count . ' ' . esc_html__('users', 'penalis-emailer');
        }
        ?>
        <tr class="history-row" 
            data-subject="<?php echo esc_attr(strtolower($entry['subject'])); ?>" 
            data-date="<?php echo esc_attr($sent_date); ?>"
            data-timestamp="<?php echo esc_attr($sent_time); ?>"
            data-type="<?php echo esc_attr($email_type); ?>">
            <td>
                <strong><?php echo esc_html($entry['subject']); ?></strong>
                <?php if ($is_automatic): ?>
                    <span class="penalis-email-badge penalis-badge-automatic">
                        <?php echo esc_html__('AUTO', 'penalis-emailer'); ?>
                    </span>
                <?php else: ?>
                    <span class="penalis-email-badge penalis-badge-manual">
                        <?php echo esc_html__('MANUAL', 'penalis-emailer'); ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($is_automatic && isset($entry['post_title'])): ?>
                    <br>
                    <small style="color: #666;">
                        <?php echo esc_html__('Post:', 'penalis-emailer'); ?> 
                        <?php if (isset($entry['post_url'])): ?>
                            <a href="<?php echo esc_url($entry['post_url']); ?>" target="_blank">
                                <?php echo esc_html($entry['post_title']); ?>
                            </a>
                        <?php else: ?>
                            <?php echo esc_html($entry['post_title']); ?>
                        <?php endif; ?>
                    </small>
                <?php elseif (!empty($body_preview)): ?>
                    <br>
                    <small style="color: #666;"><?php echo esc_html($body_preview); ?></small>
                <?php endif; ?>
            </td>
            <td>
                <?php echo esc_html($recipient_display); ?>
            </td>
            <td>
                <?php 
                if ($is_automatic) {
                    echo '<em style="color: #666;">' . esc_html__('System', 'penalis-emailer') . '</em>';
                } elseif ($sent_by_user) {
                    echo esc_html($sent_by_user->display_name);
                } else {
                    echo esc_html__('Unknown', 'penalis-emailer');
                }
                ?>
            </td>
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
            <td>
                <span style="color: #46b450;">●</span> 
                <?php echo esc_html__('Sent', 'penalis-emailer'); ?>
            </td>
        </tr>
        <?php
    }
}
