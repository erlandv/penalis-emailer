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
    $file = PENALIS_EMAILER_PATH . 'includes/class-' . $class_file . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize plugin
function penalis_emailer_init() {
    $template = new Penalis_Email_Template();
    $logger = new Penalis_Email_Logger();
    $sender = new Penalis_Email_Sender($template, $logger);
    $admin = new Penalis_Admin_Interface($sender, $template);
    
    // Register hooks
    add_action('transition_post_status', [$sender, 'handle_post_status_transition'], 10, 3);
    $admin->register_hooks();
}
add_action('plugins_loaded', 'penalis_emailer_init');
