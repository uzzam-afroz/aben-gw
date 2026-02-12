<?php
/**
 * Link Processor Class
 * 
 * Processes email HTML and adds tokens to links
 *
 * @package Aben_GW_Magic_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aben_GW_Magic_Login_Link_Processor {
    
    /**
     * Add tokens to all links in email HTML
     * 
     * @param string $html Email HTML
     * @param string $token Magic login token
     * @param int $user_id User ID
     * @return string Modified HTML
     */
    public function add_tokens_to_links($html, $token, $user_id) {
        
        if (empty($token)) {
            return $html;
        }
        
        // Process all <a> tags
        $html = preg_replace_callback(
            '/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>/i',
            function($matches) use ($token, $user_id) {
                return $this->process_link($matches, $token, $user_id);
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Process individual link
     * 
     * @param array $matches Regex matches
     * @param string $token Token
     * @param int $user_id User ID
     * @return string Modified link tag
     */
    private function process_link($matches, $token, $user_id) {
        $original_tag = $matches[0];
        $original_url = $matches[2];
        
        // Skip certain types of links
        if ($this->should_skip_link($original_url)) {
            return $original_tag;
        }
        
        // Add token to URL
        $new_url = $this->add_token_to_url($original_url, $token);
        
        // Allow filtering
        $new_url = apply_filters('aben_gw_magic_login_link_url', $new_url, $original_url, $token, $user_id);
        
        // Replace URL in tag
        return str_replace($original_url, $new_url, $original_tag);
    }
    
    /**
     * Check if link should be skipped
     * 
     * @param string $url URL
     * @return bool Should skip
     */
    private function should_skip_link($url) {
        
        // Skip special protocols
        $skip_protocols = array('mailto:', 'tel:', 'sms:', 'javascript:', '#');
        foreach ($skip_protocols as $protocol) {
            if (strpos($url, $protocol) === 0) {
                return true;
            }
        }
        
        // Skip unsubscribe links
        if (strpos($url, 'aben-unsubscribe') !== false) {
            return true;
        }
        
        // Skip external domains (always)
        if ($this->is_external_url($url)) {
            return true;
        }
        
        // Allow custom filtering
        return apply_filters('aben_gw_magic_login_skip_link', false, $url);
    }
    
    /**
     * Check if URL is external
     * 
     * @param string $url URL
     * @return bool Is external
     */
    private function is_external_url($url) {
        $site_url = parse_url(home_url(), PHP_URL_HOST);
        $link_url = parse_url($url, PHP_URL_HOST);
        
        if (!$link_url) {
            return false;
        }
        
        return $site_url !== $link_url;
    }
    
    /**
     * Add token to URL
     * 
     * @param string $url Original URL
     * @param string $token Token
     * @return string Modified URL
     */
    private function add_token_to_url($url, $token) {
        
        // Parse URL
        $parsed = parse_url($url);
        
        // Build query parameters
        $params = array();
        
        // Preserve existing query parameters
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
        }
        
        // Add magic login token
        $params['agw_token'] = $token;
        
        // Add redirect URL (the actual destination)
        $params['agw_redirect'] = $url;
        
        // Rebuild URL
        $new_url = $this->build_url($parsed, $params);
        
        return $new_url;
    }
    
    /**
     * Build URL from parsed components
     * 
     * @param array $parsed Parsed URL components
     * @param array $params Query parameters
     * @return string Built URL
     */
    private function build_url($parsed, $params) {
        $url = '';
        
        if (isset($parsed['scheme'])) {
            $url .= $parsed['scheme'] . '://';
        }
        
        if (isset($parsed['host'])) {
            $url .= $parsed['host'];
        }
        
        if (isset($parsed['port'])) {
            $url .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $url .= $parsed['path'];
        }
        
        // Add query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        if (isset($parsed['fragment'])) {
            $url .= '#' . $parsed['fragment'];
        }
        
        return $url;
    }
    
    /**
     * Extract destination URL from magic link
     * 
     * @param string $url Magic link URL
     * @return string Original destination
     */
    public function extract_destination($url) {
        $parsed = parse_url($url);
        
        if (!isset($parsed['query'])) {
            return home_url();
        }
        
        parse_str($parsed['query'], $params);
        
        if (isset($params['agw_redirect'])) {
            return urldecode($params['agw_redirect']);
        }
        
        // Fallback: remove magic login params
        unset($params['agw_token']);
        unset($params['agw_redirect']);
        
        return $this->build_url($parsed, $params);
    }
}
