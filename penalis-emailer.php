<?php
/**
 * Plugin Name: Penalis Emailer
 * Plugin URI: https://github.com/erlandv/penalis-emailer
 * Description: Automatically notifies post authors via email when posts are published and manage email notifications for your contributors
 * Version: 2.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
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
define('PENALIS_EMAILER_VERSION', '2.0.0');
define('PENALIS_EMAILER_PATH', plugin_dir_path(__FILE__));
define('PENALIS_EMAILER_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    // Only handle classes with Penalis_ prefix
    $prefix = 'Penalis_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    // Remove prefix and convert to lowercase with dashes
    $class_name = substr($class, strlen($prefix));
    $class_file = strtolower(str_replace('_', '-', $class_name));
    
    // Determine file type and path based on class name patterns
    $file_path = null;
    
    // Special case: Base Penalis_Exception class
    if ($class_name === 'Exception') {
        $file_path = PENALIS_EMAILER_PATH . 'includes/exceptions/class-penalis-exception.php';
    }
    // Check if it's an admin class (starts with Admin or Compose or History or Settings or Ajax or Dashboard or Draft)
    // Must be checked BEFORE interface check because Admin_Interface would match both
    elseif (preg_match('/^(Admin|Compose|History|Settings|Ajax|Dashboard|Draft)/', $class_name)) {
        $file_path = PENALIS_EMAILER_PATH . 'includes/admin/class-' . $class_file . '.php';
    }
    // Check if it's an interface (ends with _Interface)
    elseif (substr($class_name, -10) === '_Interface') {
        $interface_file = str_replace('-interface', '', $class_file);
        $file_path = PENALIS_EMAILER_PATH . 'includes/interfaces/interface-' . $interface_file . '.php';
    }
    // Check if it's an exception (ends with _Exception)
    elseif (substr($class_name, -10) === '_Exception') {
        $file_path = PENALIS_EMAILER_PATH . 'includes/exceptions/class-' . $class_file . '.php';
    }
    // Check if it's a repository (ends with _Repository)
    elseif (substr($class_name, -11) === '_Repository') {
        $file_path = PENALIS_EMAILER_PATH . 'includes/repositories/class-' . $class_file . '.php';
    }
    // Check if it's a validator (ends with _Validator)
    elseif (substr($class_name, -10) === '_Validator') {
        $file_path = PENALIS_EMAILER_PATH . 'includes/validators/class-' . $class_file . '.php';
    }
    // Default to includes directory
    else {
        $file_path = PENALIS_EMAILER_PATH . 'includes/class-' . $class_file . '.php';
    }
    
    // Load the file if it exists
    if ($file_path && file_exists($file_path)) {
        require_once $file_path;
    }
});

// Initialize plugin
function penalis_emailer_init() {
    // Load config first (needed for constants used in container setup)
    require_once PENALIS_EMAILER_PATH . 'includes/class-config.php';

    // Run DB install/upgrade check on every load (cheap — only acts when version changes)
    require_once PENALIS_EMAILER_PATH . 'includes/class-database.php';
    $installed_schema = get_option(Penalis_Database::SCHEMA_VERSION_OPTION, '0');
    if (version_compare($installed_schema, Penalis_Database::SCHEMA_VERSION, '<')) {
        Penalis_Database::install();
    }

    // All other classes will be autoloaded on-demand
    // Initialize Service Container and register singletons
    $container = Penalis_Service_Container::class;
    
    // Register core services as singletons
    $container::singleton(Penalis_Markdown_Parser::class);
    $container::singleton(Penalis_Email_Template::class);
    $container::singleton(Penalis_Email_Logger::class);
    $container::singleton(Penalis_Email_Sender::class);
    $container::singleton(Penalis_Email_Validator::class);

    // Register repositories as singletons
    // v2.0.0: Use DB-backed repository instead of wp_options
    $container::bind(
        Penalis_Email_Log_Repository_Interface::class,
        function() {
            return new Penalis_Email_Log_DB_Repository();
        },
        true
    );

    $container::bind(
        Penalis_Post_Meta_Repository_Interface::class,
        function() {
            return new Penalis_Post_Meta_Repository(Penalis_Config::META_KEY_EMAIL_SENT);
        },
        true
    );

    // Queue repository (new in v2.0.0)
    $container::singleton(Penalis_Email_Queue_Repository::class);

    // Register admin pages as singletons
    $container::singleton(Penalis_Dashboard_Page::class);
    $container::singleton(Penalis_Compose_Page::class);
    $container::singleton(Penalis_History_Page::class);
    $container::singleton(Penalis_Settings_Page::class);
    $container::singleton(Penalis_Draft_Page::class);
    $container::singleton(Penalis_Ajax_Handler::class);
    $container::singleton(Penalis_Admin_Interface::class);
    
    // Get instances through container (with automatic dependency injection)
    $sender    = $container::get(Penalis_Email_Sender::class);
    $processor = $container::get(Penalis_Email_Queue_Processor::class);
    $admin     = $container::get(Penalis_Admin_Interface::class);
    
    // Register hooks
    add_action('transition_post_status', [$sender, 'handle_post_status_transition'], 10, 3);
    add_action(Penalis_Config::CRON_HOOK, [$processor, 'process_batch']);
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

// ============================================================================
// Activation / Deactivation / Uninstall
// ============================================================================

/**
 * Plugin activation: create/upgrade DB tables and run migrations.
 */
function penalis_emailer_activate(): void {
    require_once PENALIS_EMAILER_PATH . 'includes/class-config.php';
    require_once PENALIS_EMAILER_PATH . 'includes/class-database.php';
    Penalis_Database::install();
}
register_activation_hook(__FILE__, 'penalis_emailer_activate');

/**
 * Plugin deactivation: clear scheduled cron events.
 */
function penalis_emailer_deactivate(): void {
    $timestamp = wp_next_scheduled(Penalis_Config::CRON_HOOK);
    if ($timestamp) {
        wp_unschedule_event($timestamp, Penalis_Config::CRON_HOOK);
    }
    wp_clear_scheduled_hook(Penalis_Config::CRON_HOOK);
}
register_deactivation_hook(__FILE__, 'penalis_emailer_deactivate');
