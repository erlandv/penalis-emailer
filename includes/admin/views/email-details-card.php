<?php
/**
 * Email Details Card View
 *
 * Displays the email from name and subject input fields.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="penalis-form-card">
    <h3>
        <span class="dashicons dashicons-admin-generic"></span>
        <?php echo esc_html__('Email Details', 'penalis-emailer'); ?>
    </h3>
    
    <div class="penalis-form-row">
        <div class="penalis-form-group">
            <label for="from_name" class="penalis-label-required">
                <?php echo esc_html__('From Name', 'penalis-emailer'); ?>
            </label>
            <input type="text" 
                   name="from_name" 
                   id="from_name" 
                   class="regular-text" 
                   required
                   value="<?php echo esc_attr($from_name ?? Penalis_Config::DEFAULT_FROM_NAME); ?>"
                   placeholder="<?php echo esc_attr__('e.g., Penalis - Event', 'penalis-emailer'); ?>">
        </div>
        
        <div class="penalis-form-group">
            <label for="subject" class="penalis-label-required">
                <?php echo esc_html__('Subject', 'penalis-emailer'); ?>
            </label>
            <input type="text" 
                   name="subject" 
                   id="subject" 
                   class="regular-text" 
                   required
                   value="<?php echo esc_attr($subject ?? ''); ?>"
                   placeholder="<?php echo esc_attr__('Enter email subject', 'penalis-emailer'); ?>">
        </div>
    </div>
</div>
