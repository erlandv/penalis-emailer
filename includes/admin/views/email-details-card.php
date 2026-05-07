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
    <h3><?php echo esc_html__('Email Details', 'penalis-emailer'); ?></h3>
    <table class="form-table">
        <tr class="penalis-required-field">
            <th scope="row">
                <label for="from_name"><?php echo esc_html__('Email From', 'penalis-emailer'); ?></label>
            </th>
            <td>
                <input type="text" 
                       name="from_name" 
                       id="from_name" 
                       class="regular-text" 
                       required
                       value="<?php echo esc_attr($from_name ?? Penalis_Config::DEFAULT_FROM_NAME); ?>"
                       placeholder="<?php echo esc_attr__('e.g., Penalis - Event', 'penalis-emailer'); ?>">
                <p class="description">
                    <?php echo esc_html__('Sender name that will appear in the email. Email address will always use no-reply@domain.', 'penalis-emailer'); ?>
                </p>
            </td>
        </tr>
        
        <tr class="penalis-required-field">
            <th scope="row">
                <label for="subject"><?php echo esc_html__('Email Subject', 'penalis-emailer'); ?></label>
            </th>
            <td>
                <input type="text" 
                       name="subject" 
                       id="subject" 
                       class="regular-text" 
                       required
                       value="<?php echo esc_attr($subject ?? ''); ?>"
                       placeholder="<?php echo esc_attr__('Enter email subject', 'penalis-emailer'); ?>">
            </td>
        </tr>
    </table>
</div>
