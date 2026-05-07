<?php
/**
 * Dashboard Page Class
 *
 * Handles the main dashboard page with statistics and quick actions.
 * Provides overview of email activity and system status.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Dashboard_Page
 *
 * Manages the dashboard interface with statistics and quick actions.
 */
class Penalis_Dashboard_Page extends Penalis_Admin_Page {
    
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
     * Render dashboard page
     *
     * @return void
     */
    public function render(): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get statistics
        $stats = $this->get_statistics();
        
        // Get recent activity
        $recent_emails = $this->email_logger->get_all_email_log(5, 'all');
        
        // Get recent drafts
        $recent_drafts = $this->email_logger->get_drafts(5);
        
        ?>
        <div class="wrap penalis-dashboard">
            <h1><?php echo esc_html__('Penalis Emailer', 'penalis-emailer'); ?></h1>
            <p class="description">
                <?php echo esc_html__('Manage email notifications for your contributors', 'penalis-emailer'); ?>
            </p>
            
            <!-- Statistics Cards -->
            <?php $this->render_statistics_cards($stats); ?>
            
            <!-- Quick Actions -->
            <?php $this->render_quick_actions(); ?>
            
            <!-- Recent Activity & Drafts Grid -->
            <div class="penalis-recent-grid">
                <!-- Recent Activity -->
                <?php $this->render_recent_activity($recent_emails); ?>
                
                <!-- Recent Drafts -->
                <?php $this->render_recent_drafts($recent_drafts); ?>
            </div>
            
            <!-- Tips & Best Practices -->
            <?php $this->render_tips(); ?>
        </div>
        <?php
    }
    
    /**
     * Get email statistics
     *
     * @return array Statistics data
     */
    private function get_statistics(): array {
        $all_logs = $this->email_logger->get_all_email_log(0, 'all');
        
        $total = count($all_logs);
        $manual = 0;
        $automatic = 0;
        
        foreach ($all_logs as $log) {
            $type = isset($log['type']) ? $log['type'] : 'manual';
            if ($type === 'manual') {
                $manual++;
            } else {
                $automatic++;
            }
        }
        
        return [
            'total' => $total,
            'manual' => $manual,
            'automatic' => $automatic
        ];
    }
    
    /**
     * Render statistics cards
     *
     * @param array $stats Statistics data
     * @return void
     */
    private function render_statistics_cards(array $stats): void {
        ?>
        <div class="penalis-stats-cards">
            <!-- Total Emails Card -->
            <div class="penalis-stat-card penalis-stat-card-primary">
                <div class="penalis-stat-icon">
                    <span class="dashicons dashicons-email-alt"></span>
                </div>
                <div class="penalis-stat-content">
                    <div class="penalis-stat-label"><?php echo esc_html__('Total Emails Sent', 'penalis-emailer'); ?></div>
                    <div class="penalis-stat-value"><?php echo esc_html(number_format_i18n($stats['total'])); ?></div>
                </div>
            </div>
            
            <!-- Manual Emails Card -->
            <div class="penalis-stat-card penalis-stat-card-info">
                <div class="penalis-stat-icon">
                    <span class="dashicons dashicons-edit"></span>
                </div>
                <div class="penalis-stat-content">
                    <div class="penalis-stat-label"><?php echo esc_html__('Manual Emails', 'penalis-emailer'); ?></div>
                    <div class="penalis-stat-value"><?php echo esc_html(number_format_i18n($stats['manual'])); ?></div>
                </div>
            </div>
            
            <!-- Automatic Emails Card -->
            <div class="penalis-stat-card penalis-stat-card-success">
                <div class="penalis-stat-icon">
                    <span class="dashicons dashicons-admin-plugins"></span>
                </div>
                <div class="penalis-stat-content">
                    <div class="penalis-stat-label"><?php echo esc_html__('Automatic Emails', 'penalis-emailer'); ?></div>
                    <div class="penalis-stat-value"><?php echo esc_html(number_format_i18n($stats['automatic'])); ?></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render quick actions section
     *
     * @return void
     */
    private function render_quick_actions(): void {
        ?>
        <div class="penalis-quick-actions">
            <h2><?php echo esc_html__('Quick Actions', 'penalis-emailer'); ?></h2>
            <div class="penalis-action-buttons">
                <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-compose')); ?>" 
                   class="penalis-action-btn penalis-action-btn-primary">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php echo esc_html__('Compose Email', 'penalis-emailer'); ?>
                </a>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-drafts')); ?>" 
                   class="penalis-action-btn penalis-action-btn-secondary">
                    <span class="dashicons dashicons-edit"></span>
                    <?php echo esc_html__('Manage Drafts', 'penalis-emailer'); ?>
                </a>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-history')); ?>" 
                   class="penalis-action-btn penalis-action-btn-secondary">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php echo esc_html__('View History', 'penalis-emailer'); ?>
                </a>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . Penalis_Config::SETTINGS_PAGE_SLUG)); ?>" 
                   class="penalis-action-btn penalis-action-btn-secondary">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php echo esc_html__('Settings', 'penalis-emailer'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent activity section
     *
     * @param array $recent_emails Recent email logs
     * @return void
     */
    private function render_recent_activity(array $recent_emails): void {
        ?>
        <div class="penalis-recent-activity">
            <h2><?php echo esc_html__('Recent Emails', 'penalis-emailer'); ?></h2>
            
            <?php if (empty($recent_emails)): ?>
                <div class="penalis-empty-state">
                    <p><?php echo esc_html__('No emails sent yet.', 'penalis-emailer'); ?></p>
                </div>
            <?php else: ?>
                <div class="penalis-activity-list">
                    <?php foreach ($recent_emails as $email): ?>
                        <?php $this->render_activity_item($email); ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="penalis-view-all">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-history')); ?>" 
                       class="button">
                        <?php echo esc_html__('View All History', 'penalis-emailer'); ?> →
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render single activity item
     *
     * @param array $email Email log entry
     * @return void
     */
    private function render_activity_item(array $email): void {
        $type = isset($email['type']) ? $email['type'] : 'manual';
        $sent_time = isset($email['sent_at']) ? $email['sent_at'] : (isset($email['timestamp']) ? $email['timestamp'] : 0);
        
        // Get time ago
        $time_ago = human_time_diff($sent_time, current_time('timestamp', true)) . ' ' . __('ago', 'penalis-emailer');
        
        ?>
        <div class="penalis-activity-item">
            <div class="penalis-activity-icon">
                <?php if ($type === 'manual'): ?>
                    <span class="dashicons dashicons-email" style="color: #2271b1;"></span>
                <?php else: ?>
                    <span class="dashicons dashicons-admin-plugins" style="color: #00a32a;"></span>
                <?php endif; ?>
            </div>
            
            <div class="penalis-activity-content">
                <div class="penalis-activity-title">
                    <?php if ($type === 'manual'): ?>
                        <strong><?php echo esc_html__('Manual:', 'penalis-emailer'); ?></strong>
                        <?php echo esc_html($email['subject']); ?>
                    <?php else: ?>
                        <strong><?php echo esc_html__('Automatic:', 'penalis-emailer'); ?></strong>
                        <?php 
                        if (isset($email['post_title'])) {
                            echo esc_html($email['post_title']);
                        } else {
                            echo esc_html__('Post Published', 'penalis-emailer');
                        }
                        ?>
                    <?php endif; ?>
                </div>
                
                <div class="penalis-activity-meta">
                    <?php if ($type === 'manual'): ?>
                        <?php 
                        $recipient_count = isset($email['recipient_count']) ? $email['recipient_count'] : 1;
                        printf(
                            esc_html__('%d recipients', 'penalis-emailer'),
                            $recipient_count
                        );
                        ?>
                    <?php else: ?>
                        <?php 
                        if (isset($email['recipient_name'])) {
                            echo esc_html($email['recipient_name']);
                        } else {
                            echo esc_html__('1 recipient', 'penalis-emailer');
                        }
                        ?>
                    <?php endif; ?>
                    <span class="penalis-activity-separator">•</span>
                    <span class="penalis-activity-time"><?php echo esc_html($time_ago); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent drafts section
     *
     * @param array $recent_drafts Recent draft entries
     * @return void
     */
    private function render_recent_drafts(array $recent_drafts): void {
        ?>
        <div class="penalis-recent-drafts">
            <h2><?php echo esc_html__('Recent Drafts', 'penalis-emailer'); ?></h2>
            
            <?php if (empty($recent_drafts)): ?>
                <div class="penalis-empty-state">
                    <p><?php echo esc_html__('No drafts yet.', 'penalis-emailer'); ?></p>
                </div>
            <?php else: ?>
                <div class="penalis-activity-list">
                    <?php foreach ($recent_drafts as $draft): ?>
                        <?php $this->render_draft_item($draft); ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="penalis-view-all">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=penalis-email-drafts')); ?>" 
                       class="button">
                        <?php echo esc_html__('View All Drafts', 'penalis-emailer'); ?> →
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render single draft item
     *
     * @param array $draft Draft entry
     * @return void
     */
    private function render_draft_item(array $draft): void {
        $draft_id = $draft['id'] ?? '';
        $subject = !empty($draft['subject']) ? $draft['subject'] : __('(No subject)', 'penalis-emailer');
        $recipient_count = $draft['recipient_count'] ?? 0;
        $updated_at = $draft['updated_at'] ?? ($draft['created_at'] ?? 0);
        
        // Get time ago
        $time_ago = human_time_diff($updated_at, current_time('timestamp', true)) . ' ' . __('ago', 'penalis-emailer');
        
        // Edit URL
        $edit_url = add_query_arg([
            'page' => 'penalis-email-compose',
            'draft_id' => $draft_id
        ], admin_url('admin.php'));
        
        ?>
        <div class="penalis-activity-item">
            <div class="penalis-activity-icon">
                <span class="dashicons dashicons-edit" style="color: #d63638;"></span>
            </div>
            
            <div class="penalis-activity-content">
                <div class="penalis-activity-title">
                    <a href="<?php echo esc_url($edit_url); ?>" style="text-decoration: none; color: inherit;">
                        <strong><?php echo esc_html($subject); ?></strong>
                    </a>
                </div>
                
                <div class="penalis-activity-meta">
                    <?php 
                    if ($recipient_count > 0) {
                        printf(
                            esc_html__('%d recipients', 'penalis-emailer'),
                            $recipient_count
                        );
                    } else {
                        echo esc_html__('No recipients', 'penalis-emailer');
                    }
                    ?>
                    <span class="penalis-activity-separator">•</span>
                    <span class="penalis-activity-time"><?php echo esc_html($time_ago); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tips section
     *
     * @return void
     */
    private function render_tips(): void {
        $tips = [
            __('Use placeholders like {USER_NAME} to personalize your emails', 'penalis-emailer'),
            __('Always preview your email template before sending to all recipients', 'penalis-emailer'),
            __('Check email history regularly to track delivery and engagement', 'penalis-emailer'),
            __('Automatic emails are sent when posts are published to notify authors', 'penalis-emailer'),
        ];
        
        ?>
        <div class="penalis-tips-section">
            <h2><?php echo esc_html__('Tips & Best Practices', 'penalis-emailer'); ?></h2>
            <ul class="penalis-tips-list">
                <?php foreach ($tips as $tip): ?>
                    <li>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php echo esc_html($tip); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}
