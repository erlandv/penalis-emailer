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
     * Render email history page with tabs
     *
     * @param string $tab Current tab (manual or automatic), optional
     * @return void
     */
    public function render(string $tab = ''): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get current tab from parameter or URL
        if (empty($tab)) {
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'manual';
        }
        
        // Validate tab
        $tab = in_array($tab, ['manual', 'automatic']) ? $tab : 'manual';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Email History', 'penalis-emailer'); ?></h1>

            <p class="description">
                <?php echo esc_html__('View history of sent emails, both manual and automatic.', 'penalis-emailer'); ?>
            </p>
            
            <!-- Tabs -->
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-history&tab=manual')); ?>" 
                   class="nav-tab <?php echo $tab === 'manual' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Manual Emails', 'penalis-emailer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-history&tab=automatic')); ?>" 
                   class="nav-tab <?php echo $tab === 'automatic' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Automatic Emails', 'penalis-emailer'); ?>
                </a>
            </h2>
            
            <div class="tab-content" style="margin-top: 20px;">
                <?php $this->render_tab_content($tab); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tab content
     *
     * @param string $tab Current tab (manual or automatic)
     * @return void
     */
    private function render_tab_content(string $tab): void {
        
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
                    <td id="cb" class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all-logs">
                        <label for="select-all-logs">
                            <span class="screen-reader-text"><?php echo esc_html__('Select All', 'penalis-emailer'); ?></span>
                        </label>
                    </td>
                    <?php if ($tab === 'manual'): ?>
                        <th scope="col"><?php echo esc_html__('Subject', 'penalis-emailer'); ?></th>
                        <th scope="col" style="width: 80px;"><?php echo esc_html__('Recipients', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Recipient Names', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Sent By', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Sent At', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'penalis-emailer'); ?></th>
                    <?php else: ?>
                        <th scope="col"><?php echo esc_html__('Post', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Recipient', 'penalis-emailer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Email', 'penalis-emailer'); ?></th>
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
                <input type="checkbox" class="log-checkbox" id="cb-select-<?php echo esc_attr($log_id); ?>" value="<?php echo esc_attr($log_id); ?>">
                <label for="cb-select-<?php echo esc_attr($log_id); ?>">
                    <span class="screen-reader-text"><?php echo esc_html__('Select email', 'penalis-emailer'); ?></span>
                </label>
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
                
                <!-- Recipients Count Column -->
                <td style="text-align: center;">
                    <?php 
                    $recipient_count = isset($entry['recipient_count']) ? $entry['recipient_count'] : 1;
                    echo '<strong>' . esc_html($recipient_count) . '</strong>';
                    ?>
                </td>
                
                <!-- Recipient Names Column -->
                <td>
                    <?php $this->render_recipient_names($entry); ?>
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
                        // Get recipient ID for author link
                        $recipient_id = null;
                        if (isset($entry['recipients']) && is_array($entry['recipients']) && !empty($entry['recipients'])) {
                            $recipient_id = $entry['recipients'][0]; // Automatic emails have single recipient
                        }
                        
                        // Display name with link to author profile if ID available
                        if ($recipient_id) {
                            $author_url = get_author_posts_url($recipient_id);
                            echo '<a href="' . esc_url($author_url) . '" target="_blank">';
                            echo esc_html($entry['recipient_name']);
                            echo '</a>';
                        } else {
                            echo esc_html($entry['recipient_name']);
                        }
                    } else {
                        echo esc_html__('Unknown', 'penalis-emailer');
                    }
                    ?>
                </td>
                
                <!-- Email Column -->
                <td>
                    <?php 
                    if (isset($entry['recipient_email'])) {
                        echo '<span style="color: #666;">' . esc_html($entry['recipient_email']) . '</span>';
                    } else {
                        echo '<em style="color: #999;">' . esc_html__('N/A', 'penalis-emailer') . '</em>';
                    }
                    ?>
                </td>
                
            <?php endif; ?>
            
            <!-- Sent At Column (both tabs) -->
            <td>
                <?php 
                // Use wp_date() for proper timezone handling (WordPress 5.3+)
                // Falls back to date_i18n() for older versions
                if (function_exists('wp_date')) {
                    echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $sent_time));
                } else {
                    // Fallback: convert UTC to local timezone manually
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $sent_time + (get_option('gmt_offset') * HOUR_IN_SECONDS)));
                }
                ?>
                <br>
                <small style="color: #666;">
                    <?php 
                    // Use current_time with GMT parameter for accurate comparison
                    echo esc_html(human_time_diff($sent_time, current_time('timestamp', true))); 
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
    
    /**
     * Render recipient names with tooltip for overflow
     *
     * Displays up to 5 recipient names inline, with remaining names
     * shown in a tooltip on hover.
     *
     * @param array $entry Log entry data
     * @return void
     */
    private function render_recipient_names(array $entry): void {
        // Get recipient IDs
        $recipient_ids = isset($entry['recipients']) ? $entry['recipients'] : [];
        
        if (empty($recipient_ids)) {
            echo '<em style="color: #999;">' . esc_html__('No recipients', 'penalis-emailer') . '</em>';
            return;
        }
        
        // Fetch user data for all recipients
        $recipient_names = [];
        foreach ($recipient_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $recipient_names[] = $user->display_name;
            }
        }
        
        if (empty($recipient_names)) {
            echo '<em style="color: #999;">' . esc_html__('Recipients not found', 'penalis-emailer') . '</em>';
            return;
        }
        
        $total_count = count($recipient_names);
        $display_limit = 5;
        
        if ($total_count <= $display_limit) {
            // Display all names
            echo esc_html(implode(', ', $recipient_names));
        } else {
            // Display first 5 names + "and X more" with tooltip
            $visible_names = array_slice($recipient_names, 0, $display_limit);
            $hidden_names = array_slice($recipient_names, $display_limit);
            $remaining_count = count($hidden_names);
            
            echo esc_html(implode(', ', $visible_names));
            
            // Create tooltip with remaining names
            $tooltip_content = implode(', ', $hidden_names);
            ?>
            <span class="penalis-recipient-more" 
                  data-tooltip="<?php echo esc_attr($tooltip_content); ?>"
                  style="color: #2271b1; cursor: help; font-weight: 500;">
                <?php 
                printf(
                    esc_html__(' and %d more', 'penalis-emailer'),
                    $remaining_count
                ); 
                ?>
            </span>
            <?php
        }
    }
}
