<?php
/**
 * Plugin Name: Penalis Emailer
 * Plugin URI: https://github.com/erlandv/penalis-emailer
 * Description: Automatically notifies post authors via email when posts are published
 * Version: 1.2.0
 * Author: Penalis
 * Author URI: https://penalis.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: penalis-emailer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PENALIS_EMAILER_VERSION', '1.2.0');
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
        PENALIS_EMAILER_PATH . 'includes/interfaces/interface-' . $class_file . '.php',
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
    
    // Load all interfaces first (must be loaded before implementations)
    require_once PENALIS_EMAILER_PATH . 'includes/interfaces/interface-email-log-repository.php';
    require_once PENALIS_EMAILER_PATH . 'includes/interfaces/interface-email-sender.php';
    require_once PENALIS_EMAILER_PATH . 'includes/interfaces/interface-email-template.php';
    require_once PENALIS_EMAILER_PATH . 'includes/interfaces/interface-email-validator.php';
    require_once PENALIS_EMAILER_PATH . 'includes/interfaces/interface-email-logger.php';
    require_once PENALIS_EMAILER_PATH . 'includes/interfaces/interface-markdown-parser.php';
    
    // Load core classes
    require_once PENALIS_EMAILER_PATH . 'includes/class-service-container.php';
    require_once PENALIS_EMAILER_PATH . 'includes/class-markdown-parser.php';
    require_once PENALIS_EMAILER_PATH . 'includes/class-email-template.php';
    require_once PENALIS_EMAILER_PATH . 'includes/repositories/class-email-log-options-repository.php';
    require_once PENALIS_EMAILER_PATH . 'includes/repositories/class-post-meta-repository.php';
    require_once PENALIS_EMAILER_PATH . 'includes/class-email-logger.php';
    require_once PENALIS_EMAILER_PATH . 'includes/class-email-sender.php';
    require_once PENALIS_EMAILER_PATH . 'includes/validators/class-email-validator.php';
    
    // Load exception classes
    require_once PENALIS_EMAILER_PATH . 'includes/exceptions/class-penalis-exception.php';
    require_once PENALIS_EMAILER_PATH . 'includes/exceptions/class-validation-exception.php';
    require_once PENALIS_EMAILER_PATH . 'includes/exceptions/class-container-exception.php';
    require_once PENALIS_EMAILER_PATH . 'includes/exceptions/class-template-exception.php';
    require_once PENALIS_EMAILER_PATH . 'includes/exceptions/class-repository-exception.php';
    require_once PENALIS_EMAILER_PATH . 'includes/exceptions/class-email-send-exception.php';
    
    // Load admin classes
    require_once PENALIS_EMAILER_PATH . 'includes/admin/class-admin-page.php';
    require_once PENALIS_EMAILER_PATH . 'includes/admin/class-compose-page.php';
    require_once PENALIS_EMAILER_PATH . 'includes/admin/class-history-page.php';
    require_once PENALIS_EMAILER_PATH . 'includes/admin/class-settings-page.php';
    require_once PENALIS_EMAILER_PATH . 'includes/admin/class-ajax-handler.php';
    require_once PENALIS_EMAILER_PATH . 'includes/admin/class-admin-interface.php';
    
    // Initialize Service Container and register singletons
    $container = Penalis_Service_Container::class;
    
    // Register core services as singletons
    $container::singleton(Penalis_Markdown_Parser::class);
    $container::singleton(Penalis_Email_Template::class);
    $container::singleton(Penalis_Email_Logger::class);
    $container::singleton(Penalis_Email_Sender::class);
    $container::singleton(Penalis_Email_Validator::class);
    
    // Register repositories as singletons
    $container::bind(
        Penalis_Email_Log_Repository_Interface::class,
        function() {
            return new Penalis_Email_Log_Options_Repository(Penalis_Config::OPTION_KEY_MANUAL_LOG);
        },
        true
    );
    
    $container::bind(
        Penalis_Post_Meta_Repository::class,
        function() {
            return new Penalis_Post_Meta_Repository(Penalis_Config::META_KEY_EMAIL_SENT);
        },
        true
    );
    
    // Register admin pages as singletons
    $container::singleton(Penalis_Compose_Page::class);
    $container::singleton(Penalis_History_Page::class);
    $container::singleton(Penalis_Settings_Page::class);
    $container::singleton(Penalis_Ajax_Handler::class);
    $container::singleton(Penalis_Admin_Interface::class);
    
    // Get instances through container (with automatic dependency injection)
    $sender = $container::get(Penalis_Email_Sender::class);
    $admin = $container::get(Penalis_Admin_Interface::class);
    
    // Register hooks
    add_action('transition_post_status', [$sender, 'handle_post_status_transition'], 10, 3);
    $admin->register_hooks();
}
add_action('plugins_loaded', 'penalis_emailer_init');

/**
 * Get service instance from container
 * 
 * Helper function to retrieve services from the container.
 * 
 * @param string $class Class name to retrieve
 * @return object Instance of the requested class
 */
function penalis_get_service(string $class) {
    return Penalis_Service_Container::get($class);
}
