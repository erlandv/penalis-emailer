<?php
/**
 * Template Settings Page Class
 *
 * Handles the template settings page in the admin interface.
 * Allows customization of auto-email templates.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Settings_Page
 *
 * Manages the template settings interface.
 */
class Penalis_Settings_Page extends Penalis_Admin_Page {
    
    /**
     * Email template instance
     *
     * @var Penalis_Email_Template
     */
    private $email_template;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Template $email_template Email template instance
     */
    public function __construct(Penalis_Email_Template $email_template) {
        $this->email_template = $email_template;
        $this->page_slug = Penalis_Config::SETTINGS_PAGE_SLUG;
    }
    
    /**
     * Render template settings page
     *
     * @return void
     */
    public function render(): void {
        if (!$this->can_access()) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get current template body
        $custom_body = get_option(Penalis_Config::OPTION_KEY_AUTO_BODY, '');
        $current_body = !empty($custom_body) ? $custom_body : $this->email_template->get_default_auto_email_body();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Auto-Email Template Settings', 'penalis-emailer'); ?></h1>
            
            <p class="description">
                <?php echo esc_html__('Customize the template for automatic emails sent after post publish. Use plain text with markdown formatting.', 'penalis-emailer'); ?>
            </p>
            
            <?php $this->render_last_modified_info(); ?>
            
            <?php $this->render_template_form($current_body); ?>
            
            <hr>
            
            <?php $this->render_reset_form(); ?>
        </div>
        
        <?php $this->render_preview_modal(); ?>
        <?php
    }
    
    /**
     * Render last modified info
     *
     * @return void
     */
    private function render_last_modified_info(): void {
        $last_modified_time = get_option(Penalis_Config::OPTION_KEY_AUTO_BODY_MODIFIED_TIME, 0);
        $last_modified_user_id = get_option(Penalis_Config::OPTION_KEY_AUTO_BODY_MODIFIED_BY, 0);
        $last_modified_user = $last_modified_user_id ? get_userdata($last_modified_user_id) : null;
        
        if ($last_modified_time > 0): ?>
            <div class="penalis-info-box">
                <strong><?php echo esc_html__('Last Modified:', 'penalis-emailer'); ?></strong>
                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_modified_time)); ?>
                <?php if ($last_modified_user): ?>
                    <?php echo esc_html__('by', 'penalis-emailer'); ?> 
                    <strong><?php echo esc_html($last_modified_user->display_name); ?></strong>
                <?php endif; ?>
            </div>
        <?php endif;
    }
    
    /**
     * Render template form
     *
     * @param string $current_body Current template body
     * @return void
     */
    private function render_template_form(string $current_body): void {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="template-settings-form">
            <?php wp_nonce_field('penalis_save_template', 'penalis_template_nonce'); ?>
            <input type="hidden" name="action" value="penalis_save_template">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_body"><?php echo esc_html__('Email Body Template', 'penalis-emailer'); ?></label>
                    </th>
                    <td>
                        <textarea name="email_body" 
                                  id="email_body" 
                                  rows="20" 
                                  class="large-text code"
                                  style="font-family: monospace; width: 100%;"><?php echo esc_textarea($current_body); ?></textarea>
                        
                        <?php $this->render_template_help(); ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit penalis-submit-actions">
                <button type="submit" class="button penalis-btn-primary">
                    <?php echo esc_html__('Save Template', 'penalis-emailer'); ?>
                </button>
                
                <button type="button" 
                        class="button penalis-btn-secondary" 
                        id="preview-template"
                        style="margin-left: 10px;">
                    <?php echo esc_html__('Preview Template', 'penalis-emailer'); ?>
                </button>
                
                <button type="button" 
                        class="button" 
                        id="test-email-btn"
                        style="margin-left: 10px; background: #00a32a; color: #fff; border-color: #00a32a;">
                    <?php echo esc_html__('Send Test Email', 'penalis-emailer'); ?>
                </button>
            </p>
        </form>
        <?php
    }
    
    /**
     * Render template help section
     *
     * @return void
     */
    private function render_template_help(): void {
        ?>
        <div class="description" style="margin-top: 10px;">
            <!-- Quick Tips Box -->
            <div class="penalis-warning-box">
                <strong><?php echo esc_html__('Quick Tips:', 'penalis-emailer'); ?></strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 13px;">
                    <li><?php echo esc_html__('Use {BUTTON_CTA} for default "Baca Tulisanmu" button', 'penalis-emailer'); ?></li>
                    <li><?php echo esc_html__('Use [button: Custom Text](url) for additional custom buttons', 'penalis-emailer'); ?></li>
                    <li><?php echo esc_html__('Placeholders like {AUTHOR_NAME} are auto-replaced with actual data', 'penalis-emailer'); ?></li>
                </ul>
            </div>
            
            <!-- Available Placeholders -->
            <strong><?php echo esc_html__('Available Placeholders:', 'penalis-emailer'); ?></strong>
            <ul style="margin: 5px 0 15px 0; padding-left: 20px;">
                <li><code>{AUTHOR_NAME}</code> → <?php echo esc_html__("Author's full name", 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "John Doe"', 'penalis-emailer'); ?>)</em></li>
                <li><code>{POST_TITLE}</code> → <?php echo esc_html__('Post title', 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "My Amazing Article"', 'penalis-emailer'); ?>)</em></li>
                <li><code>{POST_URL}</code> → <?php echo esc_html__('Post URL', 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('e.g., "https://penalis.com/post"', 'penalis-emailer'); ?>)</em></li>
                <li><code>{BUTTON_CTA}</code> → <?php echo esc_html__('Default button "Baca Tulisanmu"', 'penalis-emailer'); ?> <em style="color: #666;">(<?php echo esc_html__('auto-links to post', 'penalis-emailer'); ?>)</em></li>
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
                    
                    <strong><?php echo esc_html__('Links & Buttons:', 'penalis-emailer'); ?></strong>
                    <ul>
                        <li><code>[link text](https://example.com)</code> → <?php echo esc_html__('Regular clickable link', 'penalis-emailer'); ?></li>
                        <li><code>[button: Button Text](https://example.com)</code> → <?php echo esc_html__('Custom CTA button', 'penalis-emailer'); ?></li>
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
        <?php
    }
    
    /**
     * Render reset form
     *
     * @return void
     */
    private function render_reset_form(): void {
        ?>
        <h2><?php echo esc_html__('Reset to Default Template', 'penalis-emailer'); ?></h2>
        <p class="description">
            <?php echo esc_html__('This will restore the original default email template and discard any custom changes.', 'penalis-emailer'); ?>
        </p>
        
        <form method="post" 
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
              onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset to the default template? This will discard all custom changes.', 'penalis-emailer')); ?>');">
            <?php wp_nonce_field('penalis_reset_template', 'penalis_reset_nonce'); ?>
            <input type="hidden" name="action" value="penalis_reset_template">
            <?php submit_button(__('Reset to Default Template', 'penalis-emailer'), 'secondary', 'submit', false); ?>
        </form>
        <?php
    }
    
    /**
     * Render preview modal
     *
     * @return void
     */
    private function render_preview_modal(): void {
        ?>
        <!-- Preview Modal -->
        <div id="template-preview-modal">
            <div>
                <div class="penalis-modal-header">
                    <h2><?php echo esc_html__('Template Preview', 'penalis-emailer'); ?></h2>
                    <button type="button" id="close-preview" class="penalis-modal-close">
                        <?php echo esc_html__('Close', 'penalis-emailer'); ?>
                    </button>
                </div>
                <div id="template-preview-loading">
                    <div class="penalis-spinner"></div>
                    <p class="penalis-spinner-text"><?php echo esc_html__('Generating preview...', 'penalis-emailer'); ?></p>
                </div>
                <iframe id="preview-iframe"></iframe>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle template save
     *
     * @return void
     */
    public function handle_save(): void {
        // Verify security
        if (!$this->verify_security('penalis_save_template', 'penalis_template_nonce')) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }
        
        // Sanitize template body
        $template_body = isset($_POST['email_body']) ? wp_kses_post($_POST['email_body']) : '';
        
        // Save to database
        update_option(Penalis_Config::OPTION_KEY_AUTO_BODY, $template_body);
        
        // Save last modified info
        update_option(Penalis_Config::OPTION_KEY_AUTO_BODY_MODIFIED_TIME, current_time('timestamp'));
        update_option(Penalis_Config::OPTION_KEY_AUTO_BODY_MODIFIED_BY, get_current_user_id());
        
        // Redirect with success message
        $this->redirect_with_notice(
            $this->page_slug,
            'success',
            __('Auto-email template saved successfully.', 'penalis-emailer')
        );
    }
    
    /**
     * Handle template reset
     *
     * @return void
     */
    public function handle_reset(): void {
        // Verify security
        if (!$this->verify_security('penalis_reset_template', 'penalis_reset_nonce')) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }
        
        // Delete custom template (will fallback to default)
        delete_option(Penalis_Config::OPTION_KEY_AUTO_BODY);
        
        // Redirect with success message
        $this->redirect_with_notice(
            $this->page_slug,
            'success',
            __('Auto-email template reset to default successfully.', 'penalis-emailer')
        );
    }
}
