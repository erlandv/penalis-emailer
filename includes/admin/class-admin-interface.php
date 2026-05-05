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
     * Dashboard page instance
     *
     * @var Penalis_Dashboard_Page
     */
    private $dashboard_page;
    
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
     * @param Penalis_Dashboard_Page $dashboard_page Dashboard page instance
     * @param Penalis_Compose_Page   $compose_page   Compose page instance
     * @param Penalis_History_Page   $history_page   History page instance
     * @param Penalis_Settings_Page  $settings_page  Settings page instance
     * @param Penalis_Ajax_Handler   $ajax_handler   AJAX handler instance
     */
    public function __construct(
        Penalis_Dashboard_Page $dashboard_page,
        Penalis_Compose_Page $compose_page,
        Penalis_History_Page $history_page,
        Penalis_Settings_Page $settings_page,
        Penalis_Ajax_Handler $ajax_handler
    ) {
        $this->dashboard_page = $dashboard_page;
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
        
        // Admin notices - single handler for all pages
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // AJAX handlers
        $this->ajax_handler->register_hooks();
    }
    
    /**
     * Add admin menu pages
     *
     * @return void
     */
    public function add_admin_menu(): void {
        // Main menu page - Dashboard
        add_menu_page(
            __('Penalis Email', 'penalis-emailer'),
            __('Penalis Email', 'penalis-emailer'),
            'manage_options',
            Penalis_Config::PAGE_SLUG,
            [$this, 'render_main_page'],
            'dashicons-email',
            30
        );
        
        // Dashboard submenu (same as main page)
        add_submenu_page(
            Penalis_Config::PAGE_SLUG,
            __('Dashboard', 'penalis-emailer'),
            __('Dashboard', 'penalis-emailer'),
            'manage_options',
            Penalis_Config::PAGE_SLUG,
            [$this, 'render_main_page']
        );
        
        // Compose Email submenu
        add_submenu_page(
            Penalis_Config::PAGE_SLUG,
            __('Compose Email', 'penalis-emailer'),
            __('Compose Email', 'penalis-emailer'),
            'manage_options',
            Penalis_Config::PAGE_SLUG . '&tab=compose',
            [$this->compose_page, 'render']
        );
        
        // Email History submenu
        add_submenu_page(
            Penalis_Config::PAGE_SLUG,
            __('Email History', 'penalis-emailer'),
            __('Email History', 'penalis-emailer'),
            'manage_options',
            'penalis-email-history',
            [$this, 'render_history_page']
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
     * Render main page (dashboard or compose based on tab)
     *
     * @return void
     */
    public function render_main_page(): void {
        // Check if tab parameter exists
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
        
        // Route to appropriate page
        switch ($tab) {
            case 'compose':
                $this->compose_page->render();
                break;
            case 'settings':
                $this->settings_page->render();
                break;
            default:
                // Default to dashboard
                $this->dashboard_page->render();
                break;
        }
    }
    
    /**
     * Render history page with tabs
     *
     * @return void
     */
    public function render_history_page(): void {
        // Delegate to history page - it handles everything
        $this->history_page->render();
    }
    
    /**
     * Show admin notices from URL parameters
     *
     * @return void
     */
    public function show_admin_notices(): void {
        // Only show on our plugin pages
        if (!isset($_GET['page'])) {
            return;
        }
        
        $our_pages = [
            Penalis_Config::PAGE_SLUG,
            'penalis-email-history',
            Penalis_Config::SETTINGS_PAGE_SLUG
        ];
        
        if (!in_array($_GET['page'], $our_pages)) {
            return;
        }
        
        // Check for notice parameters
        if (!isset($_GET['penalis_notice']) || !isset($_GET['penalis_type'])) {
            return;
        }
        
        $message = sanitize_text_field($_GET['penalis_notice']);
        $type = sanitize_text_field($_GET['penalis_type']);
        
        $allowed_types = ['success', 'error', 'warning', 'info'];
        $type = in_array($type, $allowed_types) ? $type : 'info';
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
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
            'penalis-email_page_penalis-email-history',
            'penalis-email_page_' . Penalis_Config::SETTINGS_PAGE_SLUG
        ];
        
        if (!in_array($hook, $our_pages)) {
            return;
        }
        
        // Enqueue assets from base page class
        $this->compose_page->enqueue_assets();
    }
}
