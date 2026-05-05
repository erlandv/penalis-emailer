<?php
/**
 * Email Preview Modal View
 *
 * Displays the modal dialog for previewing email content
 * before sending.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Preview Modal -->
<div id="email-preview-modal">
    <div>
        <div class="penalis-modal-header">
            <h2><?php echo esc_html__('Email Preview', 'penalis-emailer'); ?></h2>
            <button type="button" id="close-preview-modal" class="penalis-modal-close">
                <?php echo esc_html__('Close', 'penalis-emailer'); ?>
            </button>
        </div>
        <div id="preview-loading">
            <div class="penalis-spinner"></div>
            <p class="penalis-spinner-text"><?php echo esc_html__('Generating preview...', 'penalis-emailer'); ?></p>
        </div>
        <iframe id="email-preview-iframe"></iframe>
    </div>
</div>
