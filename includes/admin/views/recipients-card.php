<?php
/**
 * Recipients Card View
 *
 * Displays the user selection in a compact sidebar format.
 * Initial render shows the first batch only; remaining users are
 * loaded via AJAX infinite scroll as the admin scrolls down.
 * Search triggers a full AJAX search across all eligible users.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 *
 * @var array $users               Initial batch of WP_User objects
 * @var int   $total_users         Total number of eligible users
 * @var bool  $has_more            Whether more users exist beyond initial batch
 * @var array $selected_recipients Array of selected user IDs from draft
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$selected_recipients = $selected_recipients ?? [];
$has_more            = $has_more ?? false;
$batch_size          = Penalis_Config::RECIPIENTS_INITIAL_LOAD;
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
               placeholder="<?php echo esc_attr__('Search users...', 'penalis-emailer'); ?>"
               autocomplete="off">

        <div class="penalis-quick-select">
            <strong><?php echo esc_html__('Quick Select:', 'penalis-emailer'); ?></strong>
            <div class="penalis-quick-select-buttons">
                <button type="button" class="button button-small" id="select-all-users-btn"
                        data-total="<?php echo esc_attr($total_users); ?>">
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
        <span id="selected-count">0</span>
        <?php echo esc_html__('of', 'penalis-emailer'); ?>
        <span id="total-available-count"><?php echo esc_html($total_users); ?></span>
    </div>

    <!-- Hidden container for checkboxes of users not currently visible in the list.
         Covers two cases:
         1. Users selected from a draft that are not in the current visible batch.
         2. Users selected via "Select All / Authors / Contributors" AJAX buttons. -->
    <div id="hidden-user-checkboxes" style="display:none;">
        <?php
        if (!empty($selected_recipients)) {
            $visible_ids = array_map(fn($u) => $u->ID, $users);
            foreach ($selected_recipients as $recipient_id) {
                if (!in_array((int) $recipient_id, $visible_ids, true)) {
                    echo '<input type="checkbox" name="user_ids[]" value="'
                        . esc_attr($recipient_id)
                        . '" class="hidden-user-checkbox" checked>';
                }
            }
        }
        ?>
    </div>

    <!-- Recipients List — scrollable, infinite scroll enabled -->
    <div class="penalis-recipients-list"
         id="recipients-list"
         data-offset="<?php echo esc_attr(count($users)); ?>"
         data-batch="<?php echo esc_attr($batch_size); ?>"
         data-has-more="<?php echo $has_more ? '1' : '0'; ?>"
         data-loading="0">

        <?php if (empty($users)): ?>
            <div class="penalis-no-users" id="recipients-empty-state">
                <p><?php echo esc_html__('No eligible users found.', 'penalis-emailer'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php
                $user_roles  = implode(', ', array_map('ucfirst', $user->roles));
                $is_selected = in_array($user->ID, array_map('intval', $selected_recipients), true);
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

            <?php if ($has_more): ?>
                <!-- Sentinel: IntersectionObserver watches this element.
                     When it enters the viewport, the next batch is loaded. -->
                <div id="recipients-scroll-sentinel" class="penalis-scroll-sentinel"></div>
                <!-- Loading indicator shown while fetching next batch -->
                <div id="recipients-loading" class="penalis-recipients-loading" style="display:none;">
                    <span class="penalis-spinner-sm"></span>
                    <span><?php echo esc_html__('Loading...', 'penalis-emailer'); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
