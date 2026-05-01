<?php
/**
 * Admin Interface Class
 *
 * Provides WordPress admin interface for manual email sending.
 * Handles user selection, form rendering, security verification, and email dispatch.
 *
 * @package Penalis_Emailer
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Admin_Interface
 *
 * Manages the admin page for manually sending emails to selected users.
 */
class Penalis_Admin_Interface {
    
    /**
     * Email sender instance
     *
     * @var Penalis_Email_Sender
     */
    private $email_sender;
    
    /**
     * Email template instance
     *
     * @var Penalis_Email_Template
     */
    private $email_template;
    
    /**
     * Email logger instance
     *
     * @var Penalis_Email_Logger
     */
    private $email_logger;
    
    /**
     * Admin page slug
     *
     * @var string
     */
    private $page_slug = 'penalis-email';
    
    /**
     * Settings page slug
     *
     * @var string
     */
    private $settings_page_slug = 'penalis-email-settings';
    
    /**
     * Number of users to display per page
     *
     * @var int
     */
    private $users_per_page = 50;
    
    /**
     * Constructor
     *
     * @param Penalis_Email_Sender   $email_sender   Email sender instance
     * @param Penalis_Email_Template $email_template Email template instance
     * @param Penalis_Email_Logger   $email_logger   Email logger instance
     */
    public function __construct(
        Penalis_Email_Sender $email_sender, 
        Penalis_Email_Template $email_template,
        Penalis_Email_Logger $email_logger
    ) {
        $this->email_sender = $email_sender;
        $this->email_template = $email_template;
        $this->email_logger = $email_logger;
    }
    
    /**
     * Register WordPress hooks
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_penalis_send_email', [$this, 'handle_form_submission']);
        add_action('admin_post_penalis_save_template', [$this, 'handle_template_save']);
        add_action('admin_post_penalis_reset_template', [$this, 'handle_template_reset']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
        add_action('wp_ajax_penalis_preview_email', [$this, 'ajax_preview_email']);
        add_action('wp_ajax_penalis_preview_auto_email', [$this, 'ajax_preview_auto_email']);
    }
    
    /**
     * Add admin menu page
     *
     * @return void
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('Penalis Email', 'penalis-emailer'),  // Page title
            __('Penalis Email', 'penalis-emailer'),  // Menu title
            'manage_options',                         // Capability
            $this->page_slug,                         // Menu slug
            [$this, 'render_admin_page'],            // Callback
            'dashicons-email',                        // Icon
            30                                        // Position
        );
        
        // Add submenu for template settings
        add_submenu_page(
            $this->page_slug,                                    // Parent slug
            __('Email Template Settings', 'penalis-emailer'),   // Page title
            __('Template Settings', 'penalis-emailer'),         // Menu title
            'manage_options',                                    // Capability
            $this->settings_page_slug,                          // Menu slug
            [$this, 'render_settings_page']                     // Callback
        );
    }
    
    /**
     * Render admin page HTML
     *
     * @return void
     */
    public function render_admin_page(): void {
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'compose';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Penalis Email', 'penalis-emailer'); ?></h1>
            
            <!-- Tabs -->
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->page_slug . '&tab=compose')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'compose' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Compose Email', 'penalis-emailer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->page_slug . '&tab=history')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'history' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Email History', 'penalis-emailer'); ?>
                </a>
            </h2>
            
            <div class="tab-content" style="margin-top: 20px;">
                <?php
                switch ($current_tab) {
                    case 'history':
                        $this->render_history_tab();
                        break;
                    case 'compose':
                    default:
                        $this->render_compose_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render compose email tab
     *
     * @return void
     */
    private function render_compose_tab(): void {
        // Get current page for pagination
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Get eligible users
        $users = $this->get_eligible_users($current_page, $this->users_per_page);
        $total_users = count(get_users(['role__in' => ['author', 'contributor']]));
        $total_pages = ceil($total_users / $this->users_per_page);
        
        ?>
        <div class="penalis-compose-form">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="penalis-email-form">
                <?php wp_nonce_field('penalis_send_email', 'penalis_email_nonce'); ?>
                <input type="hidden" name="action" value="penalis_send_email">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="from_name"><?php echo esc_html__('Email From', 'penalis-emailer'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="from_name" 
                                   id="from_name" 
                                   class="regular-text" 
                                   required
                                   value="Penalis"
                                   placeholder="<?php echo esc_attr__('e.g., Penalis - Event', 'penalis-emailer'); ?>">
                            <p class="description">
                                <?php echo esc_html__('Nama pengirim yang akan muncul di email. Alamat email tetap menggunakan no-reply@domain.', 'penalis-emailer'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="subject"><?php echo esc_html__('Email Subject', 'penalis-emailer'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="subject" 
                                   id="subject" 
                                   class="regular-text" 
                                   required
                                   placeholder="<?php echo esc_attr__('Enter email subject', 'penalis-emailer'); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="body"><?php echo esc_html__('Email Body', 'penalis-emailer'); ?> <span class="required">*</span></label>
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
                                <strong><?php echo esc_html__('Formatting Tips:', 'penalis-emailer'); ?></strong>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li><code>**bold**</code> atau <code>__bold__</code> → <strong>bold text</strong></li>
                                    <li><code>*italic*</code> atau <code>_italic_</code> → <em>italic text</em></li>
                                    <li><code>[link text](url)</code> → link</li>
                                    <li><code>- item</code> → bullet list</li>
                                    <li><code>1. item</code> → numbered list</li>
                                    <li>Enter 1x untuk baris baru</li>
                                    <li>Enter 2x untuk paragraf baru</li>
                                </ul>
                                <strong><?php echo esc_html__('Available Placeholders:', 'penalis-emailer'); ?></strong>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li><code>{NAMA_USER}</code> → Nama penerima</li>
                                    <li><code>{EMAIL_USER}</code> → Email penerima</li>
                                    <li><code>{USERNAME}</code> → Username penerima</li>
                                    <li><code>{TANGGAL}</code> → Tanggal hari ini</li>
                                    <li><code>{SITE_NAME}</code> → Nama website</li>
                                    <li><code>{SITE_URL}</code> → URL website</li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Select Recipients', 'penalis-emailer'); ?> <span class="required">*</span>
                        </th>
                        <td>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php if (empty($users)): ?>
                                    <p><?php echo esc_html__('No eligible users found.', 'penalis-emailer'); ?></p>
                                <?php else: ?>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="checkbox" id="select-all-users">
                                        <strong><?php echo esc_html__('Select All', 'penalis-emailer'); ?></strong>
                                    </label>
                                    <hr style="margin: 10px 0;">
                                    <?php foreach ($users as $user): ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" 
                                                   name="user_ids[]" 
                                                   value="<?php echo esc_attr($user->ID); ?>"
                                                   class="user-checkbox">
                                            <?php echo esc_html($user->display_name); ?> 
                                            (<?php echo esc_html($user->user_email); ?>)
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($total_pages > 1): ?>
                                <div style="margin-top: 10px;">
                                    <?php
                                    $base_url = admin_url('admin.php?page=' . $this->page_slug . '&tab=compose');
                                    
                                    if ($current_page > 1):
                                        $prev_url = add_query_arg('paged', $current_page - 1, $base_url);
                                        ?>
                                        <a href="<?php echo esc_url($prev_url); ?>" class="button">
                                            <?php echo esc_html__('« Previous', 'penalis-emailer'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <span style="margin: 0 10px;">
                                        <?php
                                        printf(
                                            esc_html__('Page %1$d of %2$d', 'penalis-emailer'),
                                            $current_page,
                                            $total_pages
                                        );
                                        ?>
                                    </span>
                                    
                                    <?php if ($current_page < $total_pages):
                                        $next_url = add_query_arg('paged', $current_page + 1, $base_url);
                                        ?>
                                        <a href="<?php echo esc_url($next_url); ?>" class="button">
                                            <?php echo esc_html__('Next »', 'penalis-emailer'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="preview-email-btn" class="button">
                        <?php echo esc_html__('Preview Email', 'penalis-emailer'); ?>
                    </button>
                    
                    <?php submit_button(__('Send Email', 'penalis-emailer'), 'primary', 'submit', false); ?>
                </p>
            </form>
        </div>
        
        <!-- Preview Modal -->
        <div id="email-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:100000;">
            <div style="position:relative; width:90%; max-width:800px; height:90%; margin:2% auto; background:#fff; border-radius:5px; overflow:hidden;">
                <div style="padding:20px; background:#f1f1f1; border-bottom:1px solid #ddd;">
                    <h2 style="margin:0; display:inline-block;"><?php echo esc_html__('Email Preview', 'penalis-emailer'); ?></h2>
                    <button type="button" id="close-preview-modal" style="float:right; font-size:20px; background:none; border:none; cursor:pointer;">&times;</button>
                </div>
                <div id="preview-loading" style="padding:40px; text-align:center; display:none;">
                    <p><?php echo esc_html__('Loading preview...', 'penalis-emailer'); ?></p>
                </div>
                <iframe id="email-preview-iframe" style="width:100%; height:calc(100% - 70px); border:none;"></iframe>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Select all users functionality
            $('#select-all-users').on('change', function() {
                $('.user-checkbox').prop('checked', $(this).is(':checked'));
            });
            
            // Update select all checkbox when individual checkboxes change
            $('.user-checkbox').on('change', function() {
                var allChecked = $('.user-checkbox:checked').length === $('.user-checkbox').length;
                $('#select-all-users').prop('checked', allChecked);
            });
            
            // Preview email
            $('#preview-email-btn').on('click', function() {
                var body = $('#body').val();
                
                if (!body) {
                    alert('<?php echo esc_js(__('Please enter email body first.', 'penalis-emailer')); ?>');
                    return;
                }
                
                $('#email-preview-modal').show();
                $('#preview-loading').show();
                $('#email-preview-iframe').hide();
                
                $.post(ajaxurl, {
                    action: 'penalis_preview_email',
                    body: body,
                    nonce: '<?php echo wp_create_nonce('penalis_preview_email'); ?>'
                }, function(response) {
                    $('#preview-loading').hide();
                    $('#email-preview-iframe').show();
                    
                    if (response.success) {
                        var iframe = document.getElementById('email-preview-iframe');
                        iframe.contentWindow.document.open();
                        iframe.contentWindow.document.write(response.data.html);
                        iframe.contentWindow.document.close();
                    } else {
                        alert('<?php echo esc_js(__('Failed to generate preview.', 'penalis-emailer')); ?>');
                        $('#email-preview-modal').hide();
                    }
                });
            });
            
            // Close preview modal
            $('#close-preview-modal, #email-preview-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#email-preview-modal').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render email history tab
     *
     * @return void
     */
    private function render_history_tab(): void {
        $log_entries = $this->email_logger->get_manual_email_log(50);
        
        ?>
        <div class="penalis-history-list">
            <h2><?php echo esc_html__('Email History', 'penalis-emailer'); ?></h2>
            
            <?php if (empty($log_entries)): ?>
                <p><?php echo esc_html__('No emails sent yet.', 'penalis-emailer'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Subject', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Recipients', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Sent By', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Sent At', 'penalis-emailer'); ?></th>
                            <th><?php echo esc_html__('Status', 'penalis-emailer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log_entries as $entry): ?>
                            <?php
                            // Handle both old and new log format
                            $sent_time = isset($entry['sent_at']) ? $entry['sent_at'] : (isset($entry['timestamp']) ? $entry['timestamp'] : 0);
                            $recipient_count = isset($entry['recipient_count']) ? $entry['recipient_count'] : 1;
                            $sent_by_id = isset($entry['sent_by']) ? $entry['sent_by'] : 0;
                            $sent_by_user = $sent_by_id ? get_userdata($sent_by_id) : null;
                            $body_preview = isset($entry['body_preview']) ? $entry['body_preview'] : '';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($entry['subject']); ?></strong>
                                    <?php if (!empty($body_preview)): ?>
                                        <br>
                                        <small style="color: #666;"><?php echo esc_html($body_preview); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($recipient_count); ?> <?php echo esc_html__('users', 'penalis-emailer'); ?></td>
                                <td>
                                    <?php 
                                    if ($sent_by_user) {
                                        echo esc_html($sent_by_user->display_name);
                                    } else {
                                        echo esc_html__('Unknown', 'penalis-emailer');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $sent_time)); ?>
                                    <br>
                                    <small style="color: #666;">
                                        <?php echo esc_html(human_time_diff($sent_time, current_time('timestamp'))); ?> <?php echo esc_html__('ago', 'penalis-emailer'); ?>
                                    </small>
                                </td>
                                <td>
                                    <span style="color: #46b450;">●</span> <?php echo esc_html__('Sent', 'penalis-emailer'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="description">
                    <?php echo esc_html__('Showing last 50 emails. Older entries are automatically archived.', 'penalis-emailer'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle form submission
     *
     * @return void
     */
    public function handle_form_submission(): void {
        // Verify security
        if (!$this->verify_security()) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }
        
        // Sanitize inputs
        $sanitized = $this->sanitize_inputs($_POST);
        
        // Validate required fields
        if (empty($sanitized['from_name'])) {
            $this->redirect_with_notice('error', __('Email from name is required.', 'penalis-emailer'));
            return;
        }
        
        if (empty($sanitized['subject'])) {
            $this->redirect_with_notice('error', __('Email subject is required.', 'penalis-emailer'));
            return;
        }
        
        if (empty($sanitized['body'])) {
            $this->redirect_with_notice('error', __('Email body is required.', 'penalis-emailer'));
            return;
        }
        
        if (empty($sanitized['user_ids'])) {
            $this->redirect_with_notice('error', __('Please select at least one recipient.', 'penalis-emailer'));
            return;
        }
        
        // Send emails using flexible template
        $results = $this->email_sender->send_manual_email(
            $sanitized['subject'],
            $sanitized['user_ids'],
            $sanitized['body'],
            $sanitized['from_name']
        );
        
        // Prepare notice message
        if ($results['success'] > 0 && empty($results['failed'])) {
            $message = sprintf(
                __('Successfully sent %d email(s).', 'penalis-emailer'),
                $results['success']
            );
            $this->redirect_with_notice('success', $message);
        } elseif ($results['success'] > 0 && !empty($results['failed'])) {
            $message = sprintf(
                __('Sent %d email(s) successfully, but %d failed.', 'penalis-emailer'),
                $results['success'],
                count($results['failed'])
            );
            $this->redirect_with_notice('warning', $message);
        } else {
            $message = __('Failed to send emails. Please check your configuration.', 'penalis-emailer');
            $this->redirect_with_notice('error', $message);
        }
    }
    
    /**
     * AJAX handler for email preview
     *
     * @return void
     */
    public function ajax_preview_email(): void {
        // Check nonce
        check_ajax_referer('penalis_preview_email', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';
        
        if (empty($body)) {
            wp_send_json_error(['message' => __('Body is required', 'penalis-emailer')]);
        }
        
        // Generate preview with sample user data
        $sample_user_data = [
            'display_name' => 'John Doe',
            'user_email' => 'john@example.com',
            'user_login' => 'johndoe'
        ];
        
        $preview_html = $this->email_template->render_flexible_email($body, $sample_user_data);
        
        wp_send_json_success(['html' => $preview_html]);
    }
    
    /**
     * Show admin notices
     *
     * @return void
     */
    public function show_admin_notices(): void {
        if (!isset($_GET['penalis_notice']) || !isset($_GET['penalis_type'])) {
            return;
        }
        
        $message = sanitize_text_field($_GET['penalis_notice']);
        $type = sanitize_text_field($_GET['penalis_type']);
        
        $this->display_admin_notice($type, $message);
    }
    
    /**
     * Verify security (nonce and capability)
     *
     * @return bool True if security checks pass, false otherwise
     */
    private function verify_security(): bool {
        // Check nonce
        if (!isset($_POST['penalis_email_nonce']) || 
            !wp_verify_nonce($_POST['penalis_email_nonce'], 'penalis_send_email')) {
            return false;
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize form inputs
     *
     * @param array $post_data Raw POST data
     * @return array Sanitized data
     */
    private function sanitize_inputs(array $post_data): array {
        return [
            'from_name' => sanitize_text_field($post_data['from_name'] ?? 'Penalis'),
            'subject' => sanitize_text_field($post_data['subject'] ?? ''),
            'body' => wp_kses_post($post_data['body'] ?? ''),
            'user_ids' => isset($post_data['user_ids']) && is_array($post_data['user_ids']) 
                ? array_map('intval', $post_data['user_ids']) 
                : []
        ];
    }
    
    /**
     * Get eligible users (authors and contributors) with pagination
     *
     * @param int $page     Current page number
     * @param int $per_page Number of users per page
     * @return array Array of WP_User objects
     */
    private function get_eligible_users(int $page = 1, int $per_page = 50): array {
        $args = [
            'role__in' => ['author', 'contributor'],
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ];
        
        return get_users($args);
    }
    
    /**
     * Display admin notice
     *
     * @param string $type    Notice type (success, error, warning, info)
     * @param string $message Notice message
     * @return void
     */
    private function display_admin_notice(string $type, string $message): void {
        $allowed_types = ['success', 'error', 'warning', 'info'];
        $type = in_array($type, $allowed_types) ? $type : 'info';
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
    
    /**
     * Redirect back to admin page with notice parameters
     *
     * @param string $type    Notice type
     * @param string $message Notice message
     * @return void
     */
    private function redirect_with_notice(string $type, string $message): void {
        $redirect_url = add_query_arg(
            [
                'page' => $this->page_slug,
                'penalis_notice' => urlencode($message),
                'penalis_type' => $type
            ],
            admin_url('admin.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Render template settings page
     *
     * @return void
     */
    public function render_settings_page(): void {
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'penalis-emailer'));
        }
        
        // Get current template body (plain text/markdown)
        $custom_body = get_option('penalis_auto_email_body', '');
        $current_body = !empty($custom_body) ? $custom_body : $this->email_template->get_default_auto_email_body();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Auto-Email Template Settings', 'penalis-emailer'); ?></h1>
            
            <p class="description">
                <?php echo esc_html__('Customize template untuk email otomatis yang dikirim setelah post publish. Gunakan plain text dengan markdown formatting.', 'penalis-emailer'); ?>
            </p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
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
                            
                            <div class="description" style="margin-top: 10px;">
                                <strong><?php echo esc_html__('Available Placeholders:', 'penalis-emailer'); ?></strong>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li><code>{AUTHOR_NAME}</code> → Nama penulis</li>
                                    <li><code>{POST_TITLE}</code> → Judul post</li>
                                    <li><code>{POST_URL}</code> → URL post</li>
                                    <li><code>{BUTTON_CTA}</code> → Button "Baca Tulisanmu" (otomatis link ke post)</li>
                                    <li><code>{TANGGAL}</code> → Tanggal hari ini</li>
                                    <li><code>{SITE_NAME}</code> → Nama website</li>
                                    <li><code>{SITE_URL}</code> → URL website</li>
                                </ul>
                                
                                <strong><?php echo esc_html__('Formatting Tips:', 'penalis-emailer'); ?></strong>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li><code>**bold**</code> atau <code>__bold__</code> → <strong>bold text</strong></li>
                                    <li><code>*italic*</code> atau <code>_italic_</code> → <em>italic text</em></li>
                                    <li><code>[link text](url)</code> → link biasa</li>
                                    <li><code>[button: Button Text](url)</code> → button CTA custom</li>
                                    <li><code>- item</code> → bullet list</li>
                                    <li><code>1. item</code> → numbered list</li>
                                    <li>Enter 1x untuk baris baru</li>
                                    <li>Enter 2x untuk paragraf baru</li>
                                </ul>
                                
                                <p style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                                    <strong>💡 Tips:</strong> Gunakan <code>{BUTTON_CTA}</code> untuk button default "Baca Tulisanmu", 
                                    atau <code>[button: Custom Text](url)</code> untuk button custom tambahan.
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php submit_button(__('Save Template', 'penalis-emailer'), 'primary', 'submit', false); ?>
                    
                    <button type="button" 
                            class="button" 
                            id="preview-template"
                            style="margin-left: 10px;">
                        <?php echo esc_html__('Preview Template', 'penalis-emailer'); ?>
                    </button>
                </p>
            </form>
            
            <hr>
            
            <h2><?php echo esc_html__('Reset to Default Template', 'penalis-emailer'); ?></h2>
            <p class="description">
                <?php echo esc_html__('This will restore the original default email template and discard any custom changes.', 'penalis-emailer'); ?>
            </p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset to the default template? This will discard all custom changes.', 'penalis-emailer')); ?>');">
                <?php wp_nonce_field('penalis_reset_template', 'penalis_reset_nonce'); ?>
                <input type="hidden" name="action" value="penalis_reset_template">
                <?php submit_button(__('Reset to Default Template', 'penalis-emailer'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        
        <!-- Preview Modal -->
        <div id="template-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:100000;">
            <div style="position:relative; width:90%; max-width:800px; height:90%; margin:2% auto; background:#fff; border-radius:5px; overflow:hidden;">
                <div style="padding:20px; background:#f1f1f1; border-bottom:1px solid #ddd;">
                    <h2 style="margin:0; display:inline-block;"><?php echo esc_html__('Template Preview', 'penalis-emailer'); ?></h2>
                    <button type="button" id="close-preview" style="float:right; font-size:20px; background:none; border:none; cursor:pointer;">&times;</button>
                </div>
                <iframe id="preview-iframe" style="width:100%; height:calc(100% - 70px); border:none;"></iframe>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Preview template
            $('#preview-template').on('click', function() {
                var body = $('#email_body').val();
                
                // Replace placeholders with sample data for preview
                var preview = body
                    .replace(/{AUTHOR_NAME}/g, 'John Doe')
                    .replace(/{POST_TITLE}/g, 'Sample Post Title')
                    .replace(/{POST_URL}/g, '<?php echo esc_js(home_url('/sample-post')); ?>')
                    .replace(/{TANGGAL}/g, '<?php echo esc_js(date_i18n(get_option('date_format'))); ?>')
                    .replace(/{SITE_NAME}/g, '<?php echo esc_js(get_bloginfo('name')); ?>')
                    .replace(/{SITE_URL}/g, '<?php echo esc_js(home_url()); ?>');
                
                // Show modal
                $('#template-preview-modal').show();
                
                // Send AJAX request to render preview
                $.post(ajaxurl, {
                    action: 'penalis_preview_auto_email',
                    body: preview,
                    nonce: '<?php echo wp_create_nonce('penalis_preview_auto_email'); ?>'
                }, function(response) {
                    if (response.success) {
                        var iframe = document.getElementById('preview-iframe');
                        iframe.contentWindow.document.open();
                        iframe.contentWindow.document.write(response.data.html);
                        iframe.contentWindow.document.close();
                    } else {
                        alert('<?php echo esc_js(__('Failed to generate preview.', 'penalis-emailer')); ?>');
                        $('#template-preview-modal').hide();
                    }
                });
            });
            
            // Close preview
            $('#close-preview, #template-preview-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#template-preview-modal').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle template save
     *
     * @return void
     */
    public function handle_template_save(): void {
        // Verify security
        if (!isset($_POST['penalis_template_nonce']) || 
            !wp_verify_nonce($_POST['penalis_template_nonce'], 'penalis_save_template')) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'penalis-emailer'));
        }
        
        // Sanitize template body (plain text/markdown)
        $template_body = isset($_POST['email_body']) ? wp_kses_post($_POST['email_body']) : '';
        
        // Save to database
        update_option('penalis_auto_email_body', $template_body);
        
        // Redirect with success message
        $redirect_url = add_query_arg(
            [
                'page' => $this->settings_page_slug,
                'penalis_notice' => urlencode(__('Auto-email template saved successfully.', 'penalis-emailer')),
                'penalis_type' => 'success'
            ],
            admin_url('admin.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle template reset
     *
     * @return void
     */
    public function handle_template_reset(): void {
        // Verify security
        if (!isset($_POST['penalis_reset_nonce']) || 
            !wp_verify_nonce($_POST['penalis_reset_nonce'], 'penalis_reset_template')) {
            wp_die(__('Security verification failed.', 'penalis-emailer'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'penalis-emailer'));
        }
        
        // Delete custom template (will fallback to default)
        delete_option('penalis_auto_email_body');
        
        // Redirect with success message
        $redirect_url = add_query_arg(
            [
                'page' => $this->settings_page_slug,
                'penalis_notice' => urlencode(__('Auto-email template reset to default successfully.', 'penalis-emailer')),
                'penalis_type' => 'success'
            ],
            admin_url('admin.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * AJAX handler for auto-email preview
     *
     * @return void
     */
    public function ajax_preview_auto_email(): void {
        // Check nonce
        check_ajax_referer('penalis_preview_auto_email', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';
        
        if (empty($body)) {
            wp_send_json_error(['message' => __('Body is required', 'penalis-emailer')]);
        }
        
        // Generate preview with sample data
        $sample_data = [
            'display_name' => 'John Doe',
            'author_name' => 'John Doe',
            'post_title' => 'Sample Post Title',
            'post_url' => home_url('/sample-post')
        ];
        
        $preview_html = $this->email_template->render_flexible_email($body, $sample_data);
        
        wp_send_json_success(['html' => $preview_html]);
    }
    
    /**
     * AJAX endpoint for loading users (prepared for future use)
     *
     * @return void
     */
    public function ajax_load_users(): void {
        // Check AJAX nonce
        check_ajax_referer('penalis_load_users', 'nonce');
        
        // Verify user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'penalis-emailer')]);
        }
        
        // Get page parameter
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        
        // Get users
        $users = $this->get_eligible_users($page, $this->users_per_page);
        
        // Format user data for JSON response
        $user_data = array_map(function($user) {
            return [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email
            ];
        }, $users);
        
        // Return JSON response
        wp_send_json_success([
            'users' => $user_data,
            'has_more' => count($users) === $this->users_per_page
        ]);
    }
}
