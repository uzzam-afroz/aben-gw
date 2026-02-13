<?php
/**
 * Custom Filters Module
 * 
 * GW-specific customizations for Aben email content
 * Customizes post titles, excerpts, and featured images
 *
 * @package Aben_GW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aben_GW_Custom_Filters {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Aben Email Template Filters
        // Available Filters: title, excerpt, featured_image_url, author
        
        add_filter('aben_post_title_filter', array($this, 'filter_post_title'), 10, 2);
        add_filter('aben_post_excerpt_filter', array($this, 'filter_post_excerpt'), 10, 2);
        add_filter('aben_post_featured_image_url_filter', array($this, 'filter_featured_image'), 10, 2);
    }
    
    /**
     * Filter post title to include country
     * 
     * Adds country taxonomy term to the title
     * Example: "Job Title - United States"
     * 
     * @param string $title Original post title
     * @param int $id Post ID
     * @return string|false Modified title or false to skip post
     */
    public function filter_post_title($title, $id) {
        $country_term = get_the_terms($id, 'country');
        
        // Skip posts without country taxonomy
        if (empty($country_term) || is_wp_error($country_term)) {
            return false;
        }
        
        $term_name = $country_term[0]->name;
        $new_title = "$title - $term_name";
        
        return $new_title;
    }
    
    /**
     * Filter post excerpt to use job description
     * 
     * Uses custom field 'job_description' instead of default excerpt
     * 
     * @param string $excerpt Original excerpt
     * @param int $id Post ID
     * @return string Modified excerpt
     */
    public function filter_post_excerpt($excerpt, $id) {
        $job_description = get_post_meta($id, 'job_description', true);
        
        if (!empty($job_description)) {
            $excerpt = wp_trim_words($job_description, 15, '...');
        }
        
        return $excerpt;
    }
    
    /**
     * Filter featured image to use author logo
     * 
     * Uses the author's company logo instead of post featured image
     * Falls back to default logo if author logo not found
     * 
     * @param string $image_url Original image URL
     * @param int $id Post ID
     * @return string Modified image URL
     */
    public function filter_featured_image($image_url, $id) {
        // Get post author
        $author_id = get_post_field('post_author', $id);
        
        // Get author's logo from user meta
        $author_logo_id = get_user_meta($author_id, 'cs_logo', true);
        
        if ($author_logo_id) {
            $author_logo_url = wp_get_attachment_url($author_logo_id);
            
            if ($author_logo_url) {
                return $author_logo_url;
            }
        }
        
        // Fallback to default GW logo
        return $this->get_default_logo();
    }
    
    /**
     * Get default logo path
     * 
     * @return string|false Default logo URL or false if not found
     */
    private function get_default_logo() {
        $logo_path = ABEN_GW_PATH . 'assets/img/logo.png';
        
        if (file_exists($logo_path)) {
            return ABEN_GW_URL . 'assets/img/logo.png';
        }
        
        return '';
    }
}
