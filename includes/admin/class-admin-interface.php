<?php
/**
 * Main Admin Interface Class
 *
 * Coordinates all admin pages and functionality.
 * Acts as the main entry point for the admin interface.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Admin_Interface
 *
 * Main coordinator for admin interface.
 */
class Penalis_Admin_Interface {
    
    /**
     * Compose page instance
     *
     * @var Penalis_Compose_Page
     */
    private $compose_page;
    
    /**
     * History page instance
     *
     * @var Penalis_History_Page
     */
    private $history_page;
    
    /**
     * Settings page instance
     *
     * @var Penalis_Settings_Page
     */
    private $settings_page;
    
    /**
     * AJAX handler instance
     *
     * @var Penalis_Ajax_Handler
     */
    private $ajax_handler;
    
    /**
     * Constructor
     *
     * @param Penalis_Compose_Page  $compose_page  Compose page instance
     * @param Penalis_History_Page  $history_page  History page instance
     * @param Penalis_Settings_Page $settings_page Settings page instance
     * @param Penalis_Ajax_Handler  $ajax_handler  AJAX handler instance
     */
    public function __construct(
        Penalis_Compose_Page $compose_page,
        Penalis_History_Page $history_page,
        Penalis_Settings_Page $settings_page,
        Penalis_Ajax_Handler $ajax_handler
    ) {
        $this->compose_page = $compose_page;
        $this->history_page = $history_page;
        $this->settings_page = $settings_page;
        $this->ajax_handler = $ajax_handler;
    }
    
    /**
     * Register WordPress hooks
     *
     * @return void
     */
    public function register_hooks(): void {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Form submissions
        add_action('admin_post_penalis_send_email', [$this->compose_page, 'handle_submission']);
        add_action('admin_post_penalis_save_template', [$this->settings_page, 'handle_save']);
        add_action('admin_post_penalis_reset_template', [$this->settings_page, 'handle_reset']);
        
        // Admin notices
        add_action('admin_notices', [$this->compose_page, 'show_admin_notices']);
        add_action('admin_notices', [$this->settings_page, 'show_admin_notices']);
        
        // AJAX handlers
        $this->ajax_handler->register_hooks();
    }
    
    /**
     * Add admin menu pages
     *
     * @return void
     */
    public function add_admin_menu(): void {
        // Main menu page
        add_menu_page(
            __('Penalis Email', 'penalis-emailer'),
            __('Penalis Email', 'penalis-emailer'),
            'manage_options',
            Penalis_Config::PAGE_SLUG,
            [$this, 'render_main_page'],
            'dashicons-email',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            Penalis_Config::PAGE_SLUG,
            __('Email Template Settings', 'penalis-emailer'),
            __('Template Settings', 'penalis-emailer'),
            'manage_options',
            Penalis_Config::SETTINGS_PAGE_SLUG,
            [$this->settings_page, 'render']
        );
    }
    
    /**
     * Render main admin page with tabs
     *
     * @return void
     */
    public function render_main_page(): void {
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . Penalis_Config::PAGE_SLUG . '&tab=compose')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'compose' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Compose Email', 'penalis-emailer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . Penalis_Config::PAGE_SLUG . '&tab=history')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'history' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Email History', 'penalis-emailer'); ?>
                </a>
            </h2>
            
            <div class="tab-content" style="margin-top: 20px;">
                <?php
                switch ($current_tab) {
                    case 'history':
                        $this->history_page->render();
                        break;
                    case 'compose':
                    default:
                        $this->compose_page->render();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on our admin pages
        $our_pages = [
            'toplevel_page_' . Penalis_Config::PAGE_SLUG,
            'penalis-email_page_' . Penalis_Config::SETTINGS_PAGE_SLUG
        ];
        
        if (!in_array($hook, $our_pages)) {
            return;
        }
        
        // Enqueue assets from base page class
        $this->compose_page->enqueue_assets();
    }
}
