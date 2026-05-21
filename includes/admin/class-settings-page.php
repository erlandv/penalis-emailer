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
     * Email validator instance
     *
     * @var Penalis_Email_Validator
     */
    private $validator;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Template  $email_template Email template instance
     * @param Penalis_Email_Validator $validator      Email validator instance
     */
    public function __construct(Penalis_Email_Template $email_template, ?Penalis_Email_Validator $validator = null) {
        $this->email_template = $email_template;
        $this->validator = $validator ?? new Penalis_Email_Validator();
        $this->page_slug = Penalis_Config::SETTINGS_PAGE_SLUG;
    }
    
    /**
     * Render template settings page
     *
     * @return void
     */
    public function render(): void {
        if (!$this->can_access()) {
            $this->render_no_access_page();
            return;
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

            <hr>

            <?php $this->render_uninstall_settings(); ?>
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
            
            <!-- Email Body Template Card -->
            <div class="penalis-form-card">
                <h3>
                    <span class="dashicons dashicons-edit"></span>
                    <?php echo esc_html__('Email Body Template', 'penalis-emailer'); ?>
                </h3>
                
                <div class="penalis-form-group">
                    <textarea name="email_body" 
                              id="email_body" 
                              rows="20" 
                              class="large-text code"
                              style="font-family: monospace; padding: 4px 8px 0 8px;"><?php echo esc_textarea($current_body); ?></textarea>
                </div>
            </div>
            
            <?php $this->render_template_help(); ?>
            
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
                        class="button penalis-btn-secondary" 
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
        <!-- Template Guide Card -->
        <div class="penalis-form-card">
            <h3>
                <span class="dashicons dashicons-info"></span>
                <?php echo esc_html__('Template Guide & Placeholders', 'penalis-emailer'); ?>
            </h3>
            
            <div class="penalis-template-guide-grid">
                <!-- Quick Tips Column -->
                <div class="penalis-guide-column">
                    <strong class="penalis-guide-title"><?php echo esc_html__('Quick Tips:', 'penalis-emailer'); ?></strong>
                    <ul class="penalis-tips-list">
                        <li><?php echo esc_html__('Use {BUTTON_CTA} for default "Baca Tulisanmu" button', 'penalis-emailer'); ?></li>
                        <li><?php echo esc_html__('Use [button: Custom Text](url) for additional custom buttons', 'penalis-emailer'); ?></li>
                        <li><?php echo esc_html__('Placeholders like {AUTHOR_NAME} are auto-replaced with actual data', 'penalis-emailer'); ?></li>
                        <li><?php echo esc_html__('Use plain text with markdown formatting for best results', 'penalis-emailer'); ?></li>
                        <li><?php echo esc_html__('Preview your template before saving to see how it looks', 'penalis-emailer'); ?></li>
                        <li><?php echo esc_html__('Send test email to verify formatting and content', 'penalis-emailer'); ?></li>
                    </ul>
                </div>
                
                <!-- Available Placeholders Column -->
                <div class="penalis-guide-column">
                    <strong class="penalis-guide-title"><?php echo esc_html__('Available Placeholders:', 'penalis-emailer'); ?></strong>
                    <ul class="penalis-placeholder-list">
                        <li><code>{AUTHOR_NAME}</code> — <?php echo esc_html__("Author's full name", 'penalis-emailer'); ?></li>
                        <li><code>{POST_TITLE}</code> — <?php echo esc_html__('Post title', 'penalis-emailer'); ?></li>
                        <li><code>{POST_URL}</code> — <?php echo esc_html__('Post URL', 'penalis-emailer'); ?></li>
                        <li><code>{BUTTON_CTA}</code> — <?php echo esc_html__('Default "Baca Tulisanmu" button', 'penalis-emailer'); ?></li>
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
                            <li><code>**bold**</code> or <code>__bold__</code></li>
                            <li><code>*italic*</code> or <code>_italic_</code></li>
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
        
        // Validate template body
        if (!$this->validator->validate_template(['email_body' => $template_body])) {
            $error_message = $this->validator->get_first_error();
            $this->redirect_with_notice($this->page_slug, 'error', $error_message);
            return;
        }
        
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
     * Render uninstall settings section
     *
     * @return void
     */
    private function render_uninstall_settings(): void {
        $delete_on_uninstall = (bool) get_option(Penalis_Config::OPTION_KEY_DELETE_DATA_ON_UNINSTALL, false);
        ?>
        <h2><?php echo esc_html__('Uninstall Settings', 'penalis-emailer'); ?></h2>
        <p class="description">
            <?php echo esc_html__('Control what happens to plugin data when this plugin is deleted from WordPress admin.', 'penalis-emailer'); ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('penalis_save_uninstall_settings', 'penalis_uninstall_nonce'); ?>
            <input type="hidden" name="action" value="penalis_save_uninstall_settings">

            <div class="penalis-form-card">
                <h3>
                    <span class="dashicons dashicons-trash"></span>
                    <?php echo esc_html__('Data Deletion', 'penalis-emailer'); ?>
                </h3>

                <div class="penalis-form-group">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input type="checkbox"
                               name="delete_data_on_uninstall"
                               id="delete_data_on_uninstall"
                               value="1"
                               style="margin-top: 3px; flex-shrink: 0;"
                               <?php checked($delete_on_uninstall, true); ?>>
                        <span>
                            <strong><?php echo esc_html__('Delete all plugin data when uninstalling', 'penalis-emailer'); ?></strong><br>
                            <span class="description">
                                <?php echo esc_html__('When enabled, uninstalling this plugin will permanently delete all email logs, drafts, queue data, and plugin settings. This cannot be undone.', 'penalis-emailer'); ?>
                            </span>
                        </span>
                    </label>
                </div>

                <?php if ($delete_on_uninstall): ?>
                <div class="notice notice-warning inline" style="margin: 12px 0 0; padding: 8px 12px;">
                    <p>
                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                        <strong><?php echo esc_html__('Warning:', 'penalis-emailer'); ?></strong>
                        <?php echo esc_html__('Data deletion is currently enabled. Uninstalling this plugin will permanently erase all data.', 'penalis-emailer'); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Save Uninstall Settings', 'penalis-emailer'); ?>
                </button>
            </p>
        </form>
        <?php
    }

    /**
     * Handle save uninstall settings
     *
     * @return void
     */
    public function handle_save_uninstall_settings(): void {
        if (!$this->verify_security('penalis_save_uninstall_settings', 'penalis_uninstall_nonce')) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }

        $delete_on_uninstall = isset($_POST['delete_data_on_uninstall']) && $_POST['delete_data_on_uninstall'] === '1';

        update_option(Penalis_Config::OPTION_KEY_DELETE_DATA_ON_UNINSTALL, $delete_on_uninstall);

        $this->redirect_with_notice(
            $this->page_slug,
            'success',
            __('Uninstall settings saved successfully.', 'penalis-emailer')
        );
    }
}
