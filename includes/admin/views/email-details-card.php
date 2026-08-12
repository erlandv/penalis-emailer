<?php
/**
 * Email Details Card View
 *
 * Displays the email from name, subject, and CC input fields.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 *
 * Variables available from Compose_Page::render_email_details_card():
 *   $from_name  string  Sender display name
 *   $subject    string  Email subject
 *   $cc_emails  array   Pre-selected CC email addresses (from draft, optional)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure cc_emails is always an array
if (!isset($cc_emails) || !is_array($cc_emails)) {
    $cc_emails = [];
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

    <!-- CC Field -->
    <div class="penalis-form-group penalis-cc-group" id="penalis-cc-group">
        <label for="penalis-cc-input">
            <?php echo esc_html__('CC', 'penalis-emailer'); ?>
            <span class="penalis-cc-badge" id="penalis-cc-badge" style="display:none;"></span>
        </label>

        <!-- CC Disabled Notice (shown when recipients > 1) -->
        <div class="penalis-cc-disabled-notice" id="penalis-cc-disabled-notice" style="display:none;">
            <span class="dashicons dashicons-info-outline"></span>
            <?php echo esc_html__('CC is only available when sending to 1 recipient.', 'penalis-emailer'); ?>
        </div>

        <!-- CC Input Area (shown when recipients = 0 or 1) -->
        <div class="penalis-cc-input-wrapper" id="penalis-cc-input-wrapper">
            <!-- Tag pills container -->
            <div class="penalis-cc-tags" id="penalis-cc-tags">
                <!-- Populated by JS from draft data or user selection -->
            </div>

            <!-- Combobox search input -->
            <div class="penalis-cc-search-wrap">
                <input type="text"
                       id="penalis-cc-input"
                       class="regular-text"
                       autocomplete="off"
                       placeholder="<?php echo esc_attr__('Search admin or editor to CC…', 'penalis-emailer'); ?>">
                <div class="penalis-cc-dropdown" id="penalis-cc-dropdown" style="display:none;"></div>
            </div>
        </div>

        <!-- Hidden inputs — submitted with the form -->
        <div id="penalis-cc-hidden-inputs"></div>

        <p class="description">
            <?php echo esc_html__('Selected admins/editors will receive a CC copy of the email.', 'penalis-emailer'); ?>
        </p>
    </div>

    <!-- Pass draft CC data to JS -->
    <script type="application/json" id="penalis-cc-draft-data">
        <?php echo wp_json_encode($cc_emails); ?>
    </script>
</div>
