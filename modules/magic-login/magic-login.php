<?php
/**
 * Magic Login Module
 * 
 * Adds magic/auto-login functionality to Aben email links
 * Uses Aben's existing hooks - no core modification required
 *
 * @package Aben_GW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load module classes
require_once dirname(__FILE__) . '/class-token-manager.php';
require_once dirname(__FILE__) . '/class-link-processor.php';
require_once dirname(__FILE__) . '/class-login-handler.php';

class Aben_GW_Magic_Login {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Token manager instance
     */
    private $token_manager;
    
    /**
     * Link processor instance
     */
    private $link_processor;
    
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
        $this->token_manager = new Aben_GW_Magic_Login_Token_Manager();
        $this->link_processor = new Aben_GW_Magic_Login_Link_Processor();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * This is where we hook into Aben's existing filters and actions
     */
    private function init_hooks() {
        
        // Hook before email is sent to generate token
        add_filter('aben_before_email_sent_filter', array($this, 'generate_token_before_email'), 10, 1);
        
        // Hook to modify email HTML and add tokens to links
        add_filter('aben_email_template_html_filter', array($this, 'add_tokens_to_email_links'), 20, 3);
        
        // Hook after email is sent for cleanup/logging
        add_action('aben_after_email_sent_action', array($this, 'log_token_generation'), 10, 2);
        
        // Handle the auto-login on frontend
        add_action('init', array($this, 'process_auto_login'), 1);
        
        // Cleanup expired tokens
        add_action('aben_gw_magic_login_cleanup', array($this->token_manager, 'cleanup_expired_tokens'));
    }
    
    /**
     * Generate token before email is sent
     * 
     * Hooks into: aben_before_email_sent_filter
     * 
     * @param mixed $tracking_id Tracking ID from Aben
     * @return mixed
     */
    public function generate_token_before_email($tracking_id) {
        // Store tracking ID for later use
        $this->current_tracking_id = $tracking_id;
        return $tracking_id;
    }
    
    /**
     * Add magic login tokens to all links in email
     * 
     * Hooks into: aben_email_template_html_filter
     * 
     * @param string $email_html Email HTML content
     * @param mixed $tracking_id Tracking ID
     * @param int $user_id User ID
     * @return string Modified email HTML
     */
    public function add_tokens_to_email_links($email_html, $tracking_id, $user_id) {
        
        // Get user email
        $user = get_userdata($user_id);
        if (!$user) {
            return $email_html;
        }
        
        // Generate token for this user
        $token = $this->token_manager->generate_token($user->user_email, $user_id);
        
        if (empty($token)) {
            return $email_html;
        }
        
        // Process all links in the email
        $email_html = $this->link_processor->add_tokens_to_links($email_html, $token, $user_id);
        
        return $email_html;
    }
    
    /**
     * Log token generation after email is sent
     * 
     * Hooks into: aben_after_email_sent_action
     * 
     * @param mixed $tracking_id Tracking ID
     * @param int $user_id User ID
     */
    public function log_token_generation($tracking_id, $user_id) {
        // Optional: Add logging here
        do_action('aben_gw_magic_login_token_generated', $user_id, $tracking_id);
    }
    
    /**
     * Process auto-login from magic link
     * 
     * Hooks into: init
     */
    public function process_auto_login() {
        // Only process if token parameter exists
        if (!isset($_GET['agw_token'])) {
            return;
        }
        
        $login_handler = new Aben_GW_Magic_Login_Handler();
        $login_handler->process_login();
    }
    
    /**
     * Get settings from main plugin
     */
    public static function get_settings() {
        $main_plugin = Aben_GW::get_instance();
        
        return array(
            'token_expiry' => $main_plugin->get_option('magic_login_token_expiry', 24),
            'enable_logging' => $main_plugin->get_option('magic_login_logging', false)
        );
    }
}
