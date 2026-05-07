<?php
/**
 * Email Content Card View
 *
 * Displays the email body textarea.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="penalis-form-card">
    <h3>
        <span class="dashicons dashicons-edit"></span>
        <?php echo esc_html__('Email Content', 'penalis-emailer'); ?>
    </h3>
    
    <div class="penalis-form-group">
        <label for="body" class="penalis-label-required">
            <?php echo esc_html__('Email Body', 'penalis-emailer'); ?>
        </label>
        <textarea name="body" 
                  id="body" 
                  rows="24" 
                  class="large-text code"
                  required
                  style="font-family: monospace; padding: 4px 8px 0 8px;"
                  placeholder="<?php echo esc_attr__('Write your email message here...', 'penalis-emailer'); ?>"><?php echo esc_textarea($body ?? ''); ?></textarea>
    </div>
</div>
