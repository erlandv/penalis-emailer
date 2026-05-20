<?php
/**
 * Email Content Card View
 *
 * Displays the email body textarea and a placeholder/formatting guide.
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

<!-- Placeholder & Formatting Guide Card -->
<div class="penalis-form-card">
    <h3>
        <span class="dashicons dashicons-info"></span>
        <?php echo esc_html__('Placeholder & Formatting Guide', 'penalis-emailer'); ?>
    </h3>

    <div class="penalis-template-guide-grid">

        <!-- Quick Tips Column -->
        <div class="penalis-guide-column">
            <strong class="penalis-guide-title"><?php echo esc_html__('Quick Tips:', 'penalis-emailer'); ?></strong>
            <ul class="penalis-tips-list">
                <li><?php echo esc_html__('Placeholders like {USER_NAME} are auto-replaced per recipient', 'penalis-emailer'); ?></li>
                <li><?php echo esc_html__('Use [button: Custom Text](url) to add a CTA button', 'penalis-emailer'); ?></li>
                <li><?php echo esc_html__('Use plain text with markdown formatting for best results', 'penalis-emailer'); ?></li>
                <li><?php echo esc_html__('Click Preview before sending to see how the email looks', 'penalis-emailer'); ?></li>
            </ul>
        </div>

        <!-- Available Placeholders Column -->
        <div class="penalis-guide-column">
            <strong class="penalis-guide-title"><?php echo esc_html__('Available Placeholders:', 'penalis-emailer'); ?></strong>
            <ul class="penalis-placeholder-list">
                <li><code>{USER_NAME}</code> — <?php echo esc_html__("Recipient's full name", 'penalis-emailer'); ?></li>
                <li><code>{USER_EMAIL}</code> — <?php echo esc_html__("Recipient's email address", 'penalis-emailer'); ?></li>
                <li><code>{USERNAME}</code> — <?php echo esc_html__("Recipient's login username", 'penalis-emailer'); ?></li>
                <li><code>{DATE}</code> — <?php echo esc_html__('Current date', 'penalis-emailer'); ?></li>
                <li><code>{SITE_NAME}</code> — <?php echo esc_html__('Website name', 'penalis-emailer'); ?></li>
                <li><code>{SITE_URL}</code> — <?php echo esc_html__('Website URL', 'penalis-emailer'); ?></li>
            </ul>
        </div>

        <!-- Formatting Guide Column -->
        <div class="penalis-guide-column">
            <strong class="penalis-guide-title"><?php echo esc_html__('Formatting Guide:', 'penalis-emailer'); ?></strong>
            <div class="penalis-formatting-section">
                <strong><?php echo esc_html__('Text Formatting:', 'penalis-emailer'); ?></strong>
                <ul>
                    <li><code>**bold**</code> <?php echo esc_html__('or', 'penalis-emailer'); ?> <code>__bold__</code></li>
                    <li><code>*italic*</code> <?php echo esc_html__('or', 'penalis-emailer'); ?> <code>_italic_</code></li>
                </ul>

                <strong><?php echo esc_html__('Links & Buttons:', 'penalis-emailer'); ?></strong>
                <ul>
                    <li><code>[link text](url)</code></li>
                    <li><code>[button: Text](url)</code></li>
                </ul>

                <strong><?php echo esc_html__('Lists:', 'penalis-emailer'); ?></strong>
                <ul>
                    <li><code>- item</code> <?php echo esc_html__('(bullet)', 'penalis-emailer'); ?></li>
                    <li><code>1. item</code> <?php echo esc_html__('(numbered)', 'penalis-emailer'); ?></li>
                </ul>

                <strong><?php echo esc_html__('Line Breaks:', 'penalis-emailer'); ?></strong>
                <ul>
                    <li><?php echo esc_html__('Enter once = line break', 'penalis-emailer'); ?></li>
                    <li><?php echo esc_html__('Enter twice = new paragraph', 'penalis-emailer'); ?></li>
                </ul>
            </div>
        </div>

    </div>
</div>
