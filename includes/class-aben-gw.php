<?php
/**
 * Main Aben GW Class
 * 
 * Manages all GW-specific features and modules
 *
 * @package Aben_GW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aben_GW {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Plugin options
     */
    private $options;
    
    /**
     * Magic login module instance
     */
    private $magic_login;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->options = get_option('aben_gw_options', $this->get_default_options());
        $this->init_hooks();
        $this->init_modules();
    }
    
    /**
     * Get default options
     */
    private function get_default_options() {
        return array(
            'magic_login_enabled' => true,
            'magic_login_token_expiry' => 24,
            'magic_login_logging' => false,
            'custom_filters_enabled' => true,
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 100);
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(ABEN_GW_PATH . 'aben-gw.php'), 
                   array($this, 'add_settings_link'));
    }
    
    /**
     * Initialize modules based on settings
     */
    private function init_modules() {
        // Initialize Custom Filters module (always active for your GW filters)
        if ($this->is_module_enabled('custom_filters')) {
            Aben_GW_Custom_Filters::get_instance();
        }
        
        // Initialize Magic Login module
        if ($this->is_module_enabled('magic_login')) {
            $this->magic_login = Aben_GW_Magic_Login::get_instance();
        }
    }
    
    /**
     * Check if a module is enabled
     */
    public function is_module_enabled($module) {
        $key = $module . '_enabled';
        return isset($this->options[$key]) && $this->options[$key];
    }
    
    /**
     * Get option value
     */
    public function get_option($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=aben-gw-settings') . '">' . 
                        __('Settings', 'aben-gw') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'auto-bulk-email-notifications',
            __('GW Settings', 'aben-gw'),
            __('GW Settings', 'aben-gw'),
            'manage_options',
            'aben-gw-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('aben_gw_settings', 'aben_gw_options', array($this, 'sanitize_options'));
        
        // General Settings Section
        add_settings_section(
            'aben_gw_general',
            __('General Settings', 'aben-gw'),
            array($this, 'general_section_callback'),
            'aben-gw-settings'
        );
        
        // Module: Custom Filters
        add_settings_section(
            'aben_gw_custom_filters',
            __('Custom Filters Module', 'aben-gw'),
            array($this, 'custom_filters_section_callback'),
            'aben-gw-settings'
        );
        
        add_settings_field(
            'custom_filters_enabled',
            __('Enable Custom Filters', 'aben-gw'),
            array($this, 'checkbox_field_callback'),
            'aben-gw-settings',
            'aben_gw_custom_filters',
            array('name' => 'custom_filters_enabled', 'label' => __('Customize post titles, excerpts, and images for GW', 'aben-gw'))
        );
        
        // Module: Magic Login
        add_settings_section(
            'aben_gw_magic_login',
            __('Magic Login Module', 'aben-gw'),
            array($this, 'magic_login_section_callback'),
            'aben-gw-settings'
        );
        
        add_settings_field(
            'magic_login_enabled',
            __('Enable Magic Login', 'aben-gw'),
            array($this, 'checkbox_field_callback'),
            'aben-gw-settings',
            'aben_gw_magic_login',
            array('name' => 'magic_login_enabled', 'label' => __('Allow users to auto-login from email links', 'aben-gw'))
        );
        
        add_settings_field(
            'magic_login_token_expiry',
            __('Token Expiry (hours)', 'aben-gw'),
            array($this, 'number_field_callback'),
            'aben-gw-settings',
            'aben_gw_magic_login',
            array('name' => 'magic_login_token_expiry', 'min' => 1, 'max' => 168, 'description' => __('How long magic login links remain valid (1-168 hours)', 'aben-gw'))
        );
        
        add_settings_field(
            'magic_login_logging',
            __('Enable Login Logging', 'aben-gw'),
            array($this, 'checkbox_field_callback'),
            'aben-gw-settings',
            'aben_gw_magic_login',
            array('name' => 'magic_login_logging', 'label' => __('Log all magic login attempts for security monitoring', 'aben-gw'))
        );
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Checkboxes
        $checkboxes = array('magic_login_enabled', 'magic_login_logging', 'custom_filters_enabled');
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($input[$checkbox]) && $input[$checkbox] ? true : false;
        }
        
        // Numbers
        if (isset($input['magic_login_token_expiry'])) {
            $sanitized['magic_login_token_expiry'] = max(1, min(168, intval($input['magic_login_token_expiry'])));
        }
        
        return $sanitized;
    }
    
    /**
     * Section callbacks
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure Aben GW modules and features.', 'aben-gw') . '</p>';
    }
    
    public function custom_filters_section_callback() {
        echo '<p>' . __('Customize how job posts appear in email notifications with GW-specific formatting.', 'aben-gw') . '</p>';
    }
    
    public function magic_login_section_callback() {
        echo '<p>' . __('Allow users to automatically log in when clicking links in email notifications.', 'aben-gw') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function checkbox_field_callback($args) {
        $name = $args['name'];
        $label = isset($args['label']) ? $args['label'] : '';
        $checked = $this->get_option($name, false);
        ?>
        <label>
            <input type="checkbox" 
                   name="aben_gw_options[<?php echo esc_attr($name); ?>]" 
                   value="1" 
                   <?php checked($checked, true); ?>>
            <?php echo esc_html($label); ?>
        </label>
        <?php
    }
    
    public function number_field_callback($args) {
        $name = $args['name'];
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 999;
        $description = isset($args['description']) ? $args['description'] : '';
        $value = $this->get_option($name, $min);
        ?>
        <input type="number" 
               name="aben_gw_options[<?php echo esc_attr($name); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($min); ?>" 
               max="<?php echo esc_attr($max); ?>">
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle settings saved message
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'aben_gw_messages',
                'aben_gw_message',
                __('Settings Saved', 'aben-gw'),
                'updated'
            );
        }
        
        settings_errors('aben_gw_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('aben_gw_settings');
                do_settings_sections('aben-gw-settings');
                submit_button(__('Save Settings', 'aben-gw'));
                ?>
            </form>
            
            <?php $this->render_statistics(); ?>
        </div>
        <?php
    }
    
    /**
     * Render statistics section
     */
    private function render_statistics() {
        if (!$this->is_module_enabled('magic_login')) {
            return;
        }
        ?>
        <hr>
        <h2><?php _e('Magic Login Statistics', 'aben-gw'); ?></h2>
        <?php
        
        if (class_exists('Aben_GW_Magic_Login_Token_Manager')) {
            $token_manager = new Aben_GW_Magic_Login_Token_Manager();
            $stats = $token_manager->get_statistics();
            ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Metric', 'aben-gw'); ?></th>
                        <th><?php _e('Count', 'aben-gw'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Total Tokens Generated', 'aben-gw'); ?></td>
                        <td><?php echo esc_html($stats['total_generated']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Tokens Used (Successful Logins)', 'aben-gw'); ?></td>
                        <td><?php echo esc_html($stats['total_used']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Active Tokens', 'aben-gw'); ?></td>
                        <td><?php echo esc_html($stats['active_tokens']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Expired Tokens', 'aben-gw'); ?></td>
                        <td><?php echo esc_html($stats['expired_tokens']); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php
        }
    }
}
