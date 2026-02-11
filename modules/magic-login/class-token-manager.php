<?php
/**
 * Token Manager Class
 * 
 * Handles token generation, validation, and cleanup
 *
 * @package Aben_GW_Magic_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aben_GW_Magic_Login_Token_Manager {
    
    /**
     * Meta key for storing tokens
     */
    const META_KEY = 'aben_gw_magic_login_token';
    
    /**
     * Generate a secure token for a user
     * 
     * @param string $email User email
     * @param int $user_id User ID
     * @return string Token
     */
    public function generate_token($email, $user_id) {
        
        // Get settings
        $settings = Aben_GW_Magic_Login::get_settings();
        $expiry_hours = isset($settings['token_expiry']) ? intval($settings['token_expiry']) : 24;
        
        // Generate secure token
        $token = wp_generate_password(32, false, false);
        
        // Prepare token data
        $token_data = array(
            'token' => $token,
            'user_id' => $user_id,
            'email' => $email,
            'created' => time(),
            'expiry' => time() + ($expiry_hours * HOUR_IN_SECONDS),
            'used' => false,
            'ip_address' => $this->get_client_ip()
        );
        
        // Store in user meta
        update_user_meta($user_id, self::META_KEY, $token_data);
        
        // Allow other plugins to hook in
        do_action('aben_gw_magic_login_token_created', $token, $user_id, $token_data);
        
        return $token;
    }
    
    /**
     * Validate a token
     * 
     * @param string $token Token to validate
     * @return array|false Token data if valid, false otherwise
     */
    public function validate_token($token) {
        
        if (empty($token)) {
            return false;
        }
        
        // Find user with this token
        $users = get_users(array(
            'meta_key' => self::META_KEY,
            'fields' => 'ID'
        ));
        
        foreach ($users as $user_id) {
            $token_data = get_user_meta($user_id, self::META_KEY, true);
            
            if (!is_array($token_data) || !isset($token_data['token'])) {
                continue;
            }
            
            // Check if token matches
            if ($token_data['token'] !== $token) {
                continue;
            }
            
            // Check if expired
            if ($token_data['expiry'] < time()) {
                delete_user_meta($user_id, self::META_KEY);
                return array(
                    'valid' => false,
                    'error' => 'expired',
                    'message' => __('This login link has expired. Please request a new one.', 'aben-gw')
                );
            }
            
            // Check if already used
            if ($token_data['used']) {
                delete_user_meta($user_id, self::META_KEY);
                return array(
                    'valid' => false,
                    'error' => 'used',
                    'message' => __('This login link has already been used.', 'aben-gw')
                );
            }
            
            // Token is valid
            return array(
                'valid' => true,
                'user_id' => $user_id,
                'token_data' => $token_data
            );
        }
        
        // Token not found
        return array(
            'valid' => false,
            'error' => 'invalid',
            'message' => __('Invalid login link.', 'aben-gw')
        );
    }
    
    /**
     * Mark token as used
     * 
     * @param int $user_id User ID
     * @return bool Success
     */
    public function mark_token_used($user_id) {
        $token_data = get_user_meta($user_id, self::META_KEY, true);
        
        if (!is_array($token_data)) {
            return false;
        }
        
        $token_data['used'] = true;
        $token_data['used_at'] = time();
        
        update_user_meta($user_id, self::META_KEY, $token_data);
        
        do_action('aben_gw_magic_login_token_used', $user_id, $token_data);
        
        return true;
    }
    
    /**
     * Cleanup expired and used tokens
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        $users = get_users(array(
            'meta_key' => self::META_KEY,
            'fields' => 'ID'
        ));
        
        $cleaned = 0;
        
        foreach ($users as $user_id) {
            $token_data = get_user_meta($user_id, self::META_KEY, true);
            
            if (!is_array($token_data)) {
                delete_user_meta($user_id, self::META_KEY);
                $cleaned++;
                continue;
            }
            
            // Delete if expired or used
            if (isset($token_data['expiry']) && $token_data['expiry'] < time()) {
                delete_user_meta($user_id, self::META_KEY);
                $cleaned++;
            } elseif (isset($token_data['used']) && $token_data['used']) {
                delete_user_meta($user_id, self::META_KEY);
                $cleaned++;
            }
        }
        
        do_action('aben_gw_magic_login_tokens_cleaned', $cleaned);
        
        return $cleaned;
    }
    
    /**
     * Get statistics
     * 
     * @return array Statistics
     */
    public function get_statistics() {
        $users = get_users(array(
            'meta_key' => self::META_KEY,
            'fields' => 'ID'
        ));
        
        $stats = array(
            'total_generated' => 0,
            'total_used' => 0,
            'active_tokens' => 0,
            'expired_tokens' => 0
        );
        
        foreach ($users as $user_id) {
            $token_data = get_user_meta($user_id, self::META_KEY, true);
            
            if (!is_array($token_data)) {
                continue;
            }
            
            $stats['total_generated']++;
            
            if (isset($token_data['used']) && $token_data['used']) {
                $stats['total_used']++;
            } elseif (isset($token_data['expiry']) && $token_data['expiry'] < time()) {
                $stats['expired_tokens']++;
            } else {
                $stats['active_tokens']++;
            }
        }
        
        return $stats;
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
