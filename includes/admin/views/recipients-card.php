<?php
/**
 * Recipients Card View
 *
 * Displays the user selection in a compact sidebar format
 * with search, filtering, and load more functionality.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 * 
 * @var array $users               Array of WP_User objects
 * @var int   $current_page        Current page number
 * @var int   $total_pages         Total number of pages
 * @var int   $total_users         Total number of users
 * @var array $selected_recipients Array of selected user IDs from draft
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure selected_recipients is an array
$selected_recipients = $selected_recipients ?? [];
?>

<div class="penalis-form-card penalis-recipients-card">
    <h3>
        <span class="dashicons dashicons-groups"></span>
        <?php echo esc_html__('Recipients', 'penalis-emailer'); ?>
    </h3>
    
    <!-- Search and Quick Actions -->
    <div class="penalis-recipients-controls">
        <input type="text" 
               id="user-search" 
               class="regular-text" 
               placeholder="<?php echo esc_attr__('Search users...', 'penalis-emailer'); ?>">
        
        <div class="penalis-quick-select">
            <strong><?php echo esc_html__('Quick Select:', 'penalis-emailer'); ?></strong>
            <div class="penalis-quick-select-buttons">
                <button type="button" class="button button-small" id="select-all-users-btn" data-total="<?php echo esc_attr($total_users); ?>">
                    <?php echo esc_html__('All', 'penalis-emailer'); ?>
                </button>
                <button type="button" class="button button-small" id="select-authors-btn">
                    <?php echo esc_html__('Authors', 'penalis-emailer'); ?>
                </button>
                <button type="button" class="button button-small" id="select-contributors-btn">
                    <?php echo esc_html__('Contributors', 'penalis-emailer'); ?>
                </button>
                <button type="button" class="button button-small" id="deselect-all-users-btn">
                    <?php echo esc_html__('Clear', 'penalis-emailer'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Selected Count -->
    <div class="penalis-selected-count">
        <strong><?php echo esc_html__('Selected:', 'penalis-emailer'); ?></strong>
        <span id="selected-count">0</span> <?php echo esc_html__('of', 'penalis-emailer'); ?> 
        <span id="total-available-count"><?php echo esc_html($total_users); ?></span>
    </div>
    
    <!-- Hidden container for dynamically added checkboxes -->
    <div id="hidden-user-checkboxes" style="display: none;">
        <?php
        // Add hidden checkboxes for selected recipients not on current page
        if (!empty($selected_recipients)) {
            $visible_user_ids = array_map(function($user) {
                return $user->ID;
            }, $users);
            
            foreach ($selected_recipients as $recipient_id) {
                if (!in_array($recipient_id, $visible_user_ids)) {
                    echo '<input type="checkbox" name="user_ids[]" value="' . esc_attr($recipient_id) . '" class="hidden-user-checkbox" checked>';
                }
            }
        }
        ?>
    </div>
    
    <!-- Recipients List (Show All, Scrollable) -->
    <div class="penalis-recipients-list">
        <?php if (empty($users)): ?>
            <div class="penalis-no-users">
                <p><?php echo esc_html__('No eligible users found.', 'penalis-emailer'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php 
                $user_roles = implode(', ', array_map('ucfirst', $user->roles));
                $post_count = count_user_posts($user->ID, 'post', true);
                $is_selected = in_array($user->ID, $selected_recipients);
                ?>
                <label class="penalis-user-item" 
                       data-name="<?php echo esc_attr(strtolower($user->display_name)); ?>" 
                       data-email="<?php echo esc_attr(strtolower($user->user_email)); ?>" 
                       data-role="<?php echo esc_attr(implode(',', $user->roles)); ?>">
                    <input type="checkbox" 
                           name="user_ids[]" 
                           value="<?php echo esc_attr($user->ID); ?>"
                           class="user-checkbox"
                           <?php checked($is_selected, true); ?>>
                    <div class="penalis-user-info">
                        <div class="penalis-user-name"><?php echo esc_html($user->display_name); ?></div>
                        <div class="penalis-user-meta">
                            <span class="penalis-user-email"><?php echo esc_html($user->user_email); ?></span>
                            <span class="penalis-user-role"><?php echo esc_html($user_roles); ?></span>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
