<?php
/**
 * Plugin Name: Penalis Emailer
 * Description: Automatically notifies post authors via email when posts are published
 * Version: 1.0.0
 * Author: Penalis
 * Text Domain: penalis-emailer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PENALIS_EMAILER_VERSION', '1.0.0');
define('PENALIS_EMAILER_PATH', plugin_dir_path(__FILE__));
define('PENALIS_EMAILER_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'Penalis_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $class_file = strtolower(str_replace('_', '-', substr($class, strlen($prefix))));
    
    // Check in multiple directories
    $possible_paths = [
        PENALIS_EMAILER_PATH . 'includes/admin/class-' . $class_file . '.php',
        PENALIS_EMAILER_PATH . 'includes/services/class-' . $class_file . '.php',
        PENALIS_EMAILER_PATH . 'includes/repositories/class-' . $class_file . '.php',
        PENALIS_EMAILER_PATH . 'includes/validators/class-' . $class_file . '.php',
        PENALIS_EMAILER_PATH . 'includes/exceptions/class-' . $class_file . '.php',
        PENALIS_EMAILER_PATH . 'includes/class-' . $class_file . '.php',
    ];
    
    foreach ($possible_paths as $file) {
        // Skip backup files
        if (strpos($file, '.old') !== false) {
            continue;
        }
        
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Initialize plugin
function penalis_emailer_init() {
    // Load config first
    require_once PENALIS_EMAILER_PATH . 'includes/class-config.php';
    
    // Initialize core services
    $template = new Penalis_Email_Template();
    $logger = new Penalis_Email_Logger();
    $sender = new Penalis_Email_Sender($template, $logger);
    
    // Initialize admin pages
    $compose_page = new Penalis_Compose_Page($sender);
    $history_page = new Penalis_History_Page($logger);
    $settings_page = new Penalis_Settings_Page($template);
    
    // Initialize AJAX handler
    $ajax_handler = new Penalis_Ajax_Handler($template);
    
    // Initialize main admin interface
    $admin = new Penalis_Admin_Interface(
        $compose_page,
        $history_page,
        $settings_page,
        $ajax_handler
    );
    
    // Register hooks
    add_action('transition_post_status', [$sender, 'handle_post_status_transition'], 10, 3);
    $admin->register_hooks();
}
add_action('plugins_loaded', 'penalis_emailer_init');
