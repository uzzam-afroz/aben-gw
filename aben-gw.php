<?php

/**
 * Plugin Name: Aben GW
 * Description: Adds GW specific features to Aben including custom filters and magic login functionality
 * Version: 1.0.0
 * Author: Zamy
 * Author URI: https://zamy.dev
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: aben-gw
 * Domain Path: /languages
 * Requires Plugins: auto-bulk-email-notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Constants
 */
define('ABEN_GW_VERSION', '1.0.0');
define('ABEN_GW_SLUG', 'aben-gw');
define('ABEN_GW_URL', plugins_url('/', __FILE__));
define('ABEN_GW_PATH', plugin_dir_path(__FILE__));

/**
 * Check if Aben plugin is active
 */
function aben_gw_check_dependencies()
{
    if (!function_exists('aben_get_options')) {
        add_action('admin_notices', 'aben_gw_missing_aben_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice if Aben is not active
 */
function aben_gw_missing_aben_notice()
{
?>
    <div class="notice notice-error">
        <p><?php _e('Aben GW requires the Aben - Auto Bulk Email Notifications plugin to be installed and activated.', 'aben-gw'); ?></p>
    </div>
<?php
}

/**
 * Initialize the plugin
 */
function aben_gw_init()
{
    if (!aben_gw_check_dependencies()) {
        return;
    }

    // Load core GW features
    require_once ABEN_GW_PATH . 'includes/class-aben-gw.php';

    // Load modules
    require_once ABEN_GW_PATH . 'modules/custom-filters/custom-filters.php';
    require_once ABEN_GW_PATH . 'modules/magic-login/magic-login.php';

    // Initialize main class
    Aben_GW::get_instance();
}
add_action('plugins_loaded', 'aben_gw_init');

/**
 * Activation hook
 */
function aben_gw_activate()
{
    // Check dependencies on activation
    if (!function_exists('aben_get_options')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Aben GW requires the Aben - Auto Bulk Email Notifications plugin to be installed and activated.', 'aben-gw'),
            __('Plugin Activation Error', 'aben-gw'),
            array('back_link' => true)
        );
    }

    // Set default options
    $default_options = array(
        'magic_login_enabled' => true,
        'magic_login_token_expiry' => 24,
        'magic_login_logging' => false,
        'custom_filters_enabled' => true,
    );

    add_option('aben_gw_options', $default_options);

    // Schedule cleanup cron for magic login
    if (!wp_next_scheduled('aben_gw_magic_login_cleanup')) {
        wp_schedule_event(time(), 'daily', 'aben_gw_magic_login_cleanup');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'aben_gw_activate');

/**
 * Deactivation hook
 */
function aben_gw_deactivate()
{
    // Clear scheduled cleanup
    wp_clear_scheduled_hook('aben_gw_magic_login_cleanup');

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'aben_gw_deactivate');

/**
 * Uninstall hook
 */
function aben_gw_uninstall()
{
    // Clean up all tokens
    delete_metadata('user', null, 'aben_gw_magic_login_token', '', true);

    // Remove options
    delete_option('aben_gw_options');
    delete_option('aben_gw_magic_login_logs');
}
register_uninstall_hook(__FILE__, 'aben_gw_uninstall');
