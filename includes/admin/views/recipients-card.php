<?php
/**
 * Recipients Card View
 *
 * Displays the user selection table with search, filtering,
 * and pagination functionality.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 * 
 * @var array $users        Array of WP_User objects
 * @var int   $current_page Current page number
 * @var int   $total_pages  Total number of pages
 * @var int   $total_users  Total number of users
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="penalis-form-card">
    <h3><?php echo esc_html__('Recipients', 'penalis-emailer'); ?></h3>
    <table class="form-table">
        <tr class="penalis-required-field">
            <th scope="row">
                <label><?php echo esc_html__('Select Recipients', 'penalis-emailer'); ?></label>
            </th>
            <td>
                <!-- Search and Bulk Actions -->
                <div class="penalis-user-selection">
                    <div class="penalis-user-actions">
                        <input type="text" 
                               id="user-search" 
                               class="regular-text" 
                               placeholder="<?php echo esc_attr__('Search by name or email...', 'penalis-emailer'); ?>">
                        <button type="button" class="button" id="select-all-users-btn">
                            <?php echo esc_html__('Select All', 'penalis-emailer'); ?>
                        </button>
                        <button type="button" class="button" id="deselect-all-users-btn">
                            <?php echo esc_html__('Deselect All', 'penalis-emailer'); ?>
                        </button>
                        <button type="button" class="button" id="select-authors-btn">
                            <?php echo esc_html__('Authors Only', 'penalis-emailer'); ?>
                        </button>
                        <button type="button" class="button" id="select-contributors-btn">
                            <?php echo esc_html__('Contributors Only', 'penalis-emailer'); ?>
                        </button>
                    </div>
                    
                    <!-- User Count -->
                    <div class="penalis-user-count">
                        <strong id="selected-count">0</strong> <?php echo esc_html__('of', 'penalis-emailer'); ?> 
                        <strong id="total-count"><?php echo count($users); ?></strong> 
                        <?php echo esc_html__('users selected', 'penalis-emailer'); ?>
                    </div>
                </div>
                
                <!-- Recipients Table -->
                <div class="penalis-recipients-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 40px; padding: 8px 10px;">
                                    <input type="checkbox" id="select-all-checkbox" style="margin: 0;">
                                </th>
                                <th style="padding: 8px 10px;"><?php echo esc_html__('Name', 'penalis-emailer'); ?></th>
                                <th style="padding: 8px 10px;"><?php echo esc_html__('Email', 'penalis-emailer'); ?></th>
                                <th style="padding: 8px 10px; width: 120px;"><?php echo esc_html__('Role', 'penalis-emailer'); ?></th>
                                <th style="padding: 8px 10px; width: 80px; text-align: center;"><?php echo esc_html__('Posts', 'penalis-emailer'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="recipients-table-body">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px;">
                                        <?php echo esc_html__('No eligible users found.', 'penalis-emailer'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php 
                                    $user_roles = implode(', ', array_map('ucfirst', $user->roles));
                                    $post_count = count_user_posts($user->ID, 'post', true);
                                    ?>
                                    <tr class="user-row" 
                                        data-name="<?php echo esc_attr(strtolower($user->display_name)); ?>" 
                                        data-email="<?php echo esc_attr(strtolower($user->user_email)); ?>" 
                                        data-role="<?php echo esc_attr(implode(',', $user->roles)); ?>">
                                        <td style="padding: 8px 10px;">
                                            <input type="checkbox" 
                                                   name="user_ids[]" 
                                                   value="<?php echo esc_attr($user->ID); ?>"
                                                   class="user-checkbox"
                                                   style="margin: 0;">
                                        </td>
                                        <td style="padding: 8px 10px;">
                                            <strong><?php echo esc_html($user->display_name); ?></strong>
                                        </td>
                                        <td style="padding: 8px 10px; color: #666;">
                                            <?php echo esc_html($user->user_email); ?>
                                        </td>
                                        <td style="padding: 8px 10px;">
                                            <span style="display: inline-block; padding: 3px 8px; background: #f0f0f1; border-radius: 3px; font-size: 12px;">
                                                <?php echo esc_html($user_roles); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px 10px; text-align: center; color: #666;">
                                            <?php echo esc_html($post_count); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="penalis-pagination">
                        <div>
                            <?php
                            $base_url = admin_url('admin.php?page=penalis-email-compose');
                            
                            if ($current_page > 1):
                                $prev_url = add_query_arg('paged', $current_page - 1, $base_url);
                                ?>
                                <a href="<?php echo esc_url($prev_url); ?>" class="button">
                                    <?php echo esc_html__('‹ Previous', 'penalis-emailer'); ?>
                                </a>
                            <?php else: ?>
                                <button type="button" class="button" disabled>
                                    <?php echo esc_html__('‹ Previous', 'penalis-emailer'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="penalis-pagination-info">
                            <?php
                            printf(
                                esc_html__('Page %1$d of %2$d', 'penalis-emailer'),
                                $current_page,
                                $total_pages
                            );
                            ?>
                            <span>(<?php echo esc_html($total_users); ?> <?php echo esc_html__('total users', 'penalis-emailer'); ?>)</span>
                        </div>
                        
                        <div>
                            <?php if ($current_page < $total_pages):
                                $next_url = add_query_arg('paged', $current_page + 1, $base_url);
                                ?>
                                <a href="<?php echo esc_url($next_url); ?>" class="button">
                                    <?php echo esc_html__('Next ›', 'penalis-emailer'); ?>
                                </a>
                            <?php else: ?>
                                <button type="button" class="button" disabled>
                                    <?php echo esc_html__('Next ›', 'penalis-emailer'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>
