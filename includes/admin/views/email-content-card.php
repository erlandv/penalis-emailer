<?php
/**
 * Email Content Card View
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
    <h3><?php echo esc_html__('Email Content', 'penalis-emailer'); ?></h3>
    <table class="form-table">
        <tr class="penalis-required-field">
            <th scope="row">
                <label for="body"><?php echo esc_html__('Email Body', 'penalis-emailer'); ?></label>
            </th>
            <td>
                <textarea name="body" 
                          id="body" 
                          rows="15" 
                          class="large-text code"
                          required
                          style="font-family: monospace;"
                          placeholder="<?php echo esc_attr__('Write your email message here...', 'penalis-emailer'); ?>"></textarea>
            
                <div class="description" style="margin-top: 10px;">
                    <!-- Quick Tips Box -->
                    <div class="penalis-tips-box">
                        <strong><?php echo esc_html__('Quick Tips:', 'penalis-emailer'); ?></strong>
                        <ul>
                            <li><?php echo esc_html__('Use placeholders like {USER_NAME} to personalize emails', 'penalis-emailer'); ?></li>
                            <li><?php echo esc_html__('Format text with markdown (e.g., **bold**, *italic*)', 'penalis-emailer'); ?></li>
                            <li><?php echo esc_html__('Press Enter twice to create new paragraphs', 'penalis-emailer'); ?></li>
                        </ul>
                    </div>
                    
                    <!-- Available Placeholders -->
                    <strong><?php echo esc_html__('Available Placeholders:', 'penalis-emailer'); ?></strong>
                    <ul style="margin: 5px 0 15px 0; padding-left: 20px;">
                        <li><code>{USER_NAME}</code> → <?php echo esc_html__("Recipient's full name", 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "John Doe"', 'penalis-emailer'); ?>)</em></li>
                        <li><code>{USER_EMAIL}</code> → <?php echo esc_html__("Recipient's email address", 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "john@example.com"', 'penalis-emailer'); ?>)</em></li>
                        <li><code>{USERNAME}</code> → <?php echo esc_html__("Recipient's username", 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "johndoe"', 'penalis-emailer'); ?>)</em></li>
                        <li><code>{DATE}</code> → <?php echo esc_html__('Current date', 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "May 1, 2026"', 'penalis-emailer'); ?>)</em></li>
                        <li><code>{SITE_NAME}</code> → <?php echo esc_html__('Website name', 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "Penalis"', 'penalis-emailer'); ?>)</em></li>
                        <li><code>{SITE_URL}</code> → <?php echo esc_html__('Website URL', 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "https://penalis.com"', 'penalis-emailer'); ?>)</em></li>
                    </ul>
                    
                    <!-- Collapsible Formatting Tips -->
                    <details class="penalis-formatting-guide">
                        <summary><?php echo esc_html__('Formatting Guide (click to expand)', 'penalis-emailer'); ?></summary>
                        <div>
                            <strong><?php echo esc_html__('Text Formatting:', 'penalis-emailer'); ?></strong>
                            <ul>
                                <li><code>**bold**</code> or <code>__bold__</code> → <strong><?php echo esc_html__('bold text', 'penalis-emailer'); ?></strong></li>
                                <li><code>*italic*</code> or <code>_italic_</code> → <em><?php echo esc_html__('italic text', 'penalis-emailer'); ?></em></li>
                            </ul>
                            
                            <strong><?php echo esc_html__('Links:', 'penalis-emailer'); ?></strong>
                            <ul>
                                <li><code>[link text](https://example.com)</code> → <?php echo esc_html__('Creates a clickable link', 'penalis-emailer'); ?></li>
                            </ul>
                            
                            <strong><?php echo esc_html__('Lists:', 'penalis-emailer'); ?></strong>
                            <ul>
                                <li><code>- item</code> → <?php echo esc_html__('Bullet list item', 'penalis-emailer'); ?></li>
                                <li><code>1. item</code> → <?php echo esc_html__('Numbered list item', 'penalis-emailer'); ?></li>
                            </ul>
                            
                            <strong><?php echo esc_html__('Line Breaks:', 'penalis-emailer'); ?></strong>
                            <ul>
                                <li><?php echo esc_html__('Press Enter once for line break', 'penalis-emailer'); ?></li>
                                <li><?php echo esc_html__('Press Enter twice for new paragraph', 'penalis-emailer'); ?></li>
                            </ul>
                        </div>
                    </details>
                </div>
            </td>
        </tr>
    </table>
</div>
