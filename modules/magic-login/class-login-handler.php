<?php
/**
 * Login Handler Class
 * 
 * Handles the actual login process when user clicks magic link
 *
 * @package Aben_GW_Magic_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aben_GW_Magic_Login_Handler {
    
    /**
     * Process login from magic link
     */
    public function process_login() {
        
        // Get and validate token
        if (!isset($_GET['agw_token'])) {
            return;
        }
        
        $token = sanitize_text_field(wp_unslash($_GET['agw_token']));
        
        if (empty($token)) {
            $this->handle_failed_token(__('Invalid login link.', 'aben-gw'), 'invalid');
            return;
        }
        
        // Validate token
        $token_manager = new Aben_GW_Magic_Login_Token_Manager();
        $validation = $token_manager->validate_token($token);
        
        if (!$validation || !$validation['valid']) {
            $error = isset($validation['error']) ? $validation['error'] : 'invalid';
            $message = isset($validation['message']) ? $validation['message'] : __('Invalid login link.', 'aben-gw');
            $this->handle_failed_token($message, $error);
            return;
        }
        
        $user_id = $validation['user_id'];
        $token_data = $validation['token_data'];
        
        // Log the attempt
        $this->log_login_attempt($user_id, 'success', $token_data);
        
        // Mark token as used
        $token_manager->mark_token_used($user_id);
        
        // Log the user in
        $this->perform_login($user_id);
        
        // Get redirect URL
        $redirect_url = $this->get_redirect_url();
        
        // Allow filtering of redirect URL
        $redirect_url = apply_filters('aben_gw_magic_login_redirect_url', $redirect_url, $user_id);
        
        // Fire action after successful login
        do_action('aben_gw_magic_login_success', $user_id, $token_data);
        
        // Redirect
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Handle token validation failures by redirecting without logging in.
     *
     * @param string $message Error message.
     * @param string $error Error code.
     */
    private function handle_failed_token($message, $error = 'invalid') {
        $this->log_login_attempt(0, 'failed', array(
            'error' => $error,
            'message' => $message
        ));

        $redirect_url = $this->get_redirect_url();
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Perform the actual login
     * 
     * @param int $user_id User ID
     */
    private function perform_login($user_id) {
        
        // Clean any existing auth cookies
        wp_clear_auth_cookie();
        
        // Set the auth cookie
        wp_set_auth_cookie($user_id, true);
        
        // Set current user
        wp_set_current_user($user_id);
        
        // Fire WordPress login action
        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
    }
    
    /**
     * Get redirect URL
     * 
     * @return string Redirect URL
     */
    private function get_redirect_url() {
        
        // Check for redirect parameter
        if (isset($_GET['agw_redirect'])) {
            $redirect = $this->decode_url_param($_GET['agw_redirect']);
            
            // Remove magic login parameters from redirect URL
            $redirect = remove_query_arg(array('agw_token', 'agw_redirect'), $redirect);
            
            // Validate it's a local URL
            if ($this->is_local_url($redirect)) {
                return $redirect;
            }
        }

        // If Aben tracking wraps links, use its "url" param as the destination
        if (isset($_GET['url'])) {
            $redirect = $this->decode_url_param($_GET['url']);
            $redirect = remove_query_arg(array('agw_token', 'agw_redirect'), $redirect);

            if ($this->is_local_url($redirect)) {
                return $redirect;
            }
        }
        
        // Fallback: redirect back to current URL without magic params
        $current_url = $this->get_current_url_without_magic_params();
        if (!empty($current_url) && $this->is_local_url($current_url)) {
            return $current_url;
        }

        // Last resort
        return home_url();
    }

    /**
     * Get current URL without magic login parameters
     *
     * @return string Current URL without agw params
     */
    private function get_current_url_without_magic_params() {
        if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
            return '';
        }

        $scheme = is_ssl() ? 'https' : 'http';
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
        $request_uri = wp_unslash($_SERVER['REQUEST_URI']);

        $url = $scheme . '://' . $host . $request_uri;
        $url = remove_query_arg(array('agw_token', 'agw_redirect'), $url);

        return $url;
    }

    /**
     * Decode URL params that may be double-encoded by upstream tracking.
     *
     * @param string $value Raw param value
     * @return string Decoded URL value
     */
    private function decode_url_param($value) {
        $decoded = wp_unslash($value);

        // Decode up to 2 times to handle nested encoding safely.
        for ($i = 0; $i < 2; $i++) {
            $next = urldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        return esc_url_raw($decoded);
    }
    
    /**
     * Check if URL is local
     * 
     * @param string $url URL to check
     * @return bool Is local
     */
    private function is_local_url($url) {
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $url_host = parse_url($url, PHP_URL_HOST);
        $request_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        
        // If no host in URL, it's relative (local)
        if (!$url_host) {
            return true;
        }
        
        // Check if hosts match
        return $site_host === $url_host || (!empty($request_host) && $request_host === $url_host);
    }
    
    /**
     * Show error page
     * 
     * @param string $message Error message
     */
    private function show_error($message) {
        
        // Log the failed attempt
        $this->log_login_attempt(0, 'failed', array('error' => $message));
        
        // Allow custom error handling
        if (has_filter('aben_gw_magic_login_error_page')) {
            $custom_page = apply_filters('aben_gw_magic_login_error_page', $message);
            if ($custom_page !== $message) {
                echo $custom_page;
                exit;
            }
        }
        
        // Default error page
        wp_die(
            esc_html($message),
            esc_html__('Login Error', 'aben-gw'),
            array(
                'response' => 403,
                'back_link' => true
            )
        );
    }
    
    /**
     * Log login attempt
     * 
     * @param int $user_id User ID
     * @param string $status Status (success/failed)
     * @param array $data Additional data
     */
    private function log_login_attempt($user_id, $status, $data = array()) {
        
        // Check if logging is enabled
        $settings = Aben_GW_Magic_Login::get_settings();
        if (!isset($settings['enable_logging']) || !$settings['enable_logging']) {
            return;
        }
        
        // Prepare log data
        $log_entry = array(
            'user_id' => $user_id,
            'status' => $status,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? 
                           sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'timestamp' => current_time('mysql'),
            'data' => $data
        );
        
        // Store in option (can be changed to custom table for better performance)
        $logs = get_option('aben_gw_magic_login_logs', array());
        
        // Keep only last 1000 entries
        if (count($logs) >= 1000) {
            array_shift($logs);
        }
        
        $logs[] = $log_entry;
        update_option('aben_gw_magic_login_logs', $logs, false);
        
        // Fire action for custom logging
        do_action('aben_gw_magic_login_logged', $log_entry);
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'UNKNOWN';
    }
}
