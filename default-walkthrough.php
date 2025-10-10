<?php
/**
 * Plugin Name: AVLP - Walkthrough Tour
 * Plugin URI: https://virtualleadershipprograms.com
 * Description: A custom plugin for creating interactive site tours and walkthroughs for the Virtual Leadership Programs platform.
 * Version: 1.1.0
 * 
 * Author: Virtual Leadership Programs
 * Author URI: https://virtualleadershipprograms.com
 * License: GPLv2 or later
 * Text Domain: avlp-walkthrough
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin paths and version
define('VLP_WALKTHROUGH_DIR', plugin_dir_path(__FILE__));
define('VLP_WALKTHROUGH_URL', plugin_dir_url(__FILE__));
define('VLP_WALKTHROUGH_VERSION', '1.1.0');

// Include supporting files
require_once VLP_WALKTHROUGH_DIR . '/includes/walkthrough-database.php';
require_once VLP_WALKTHROUGH_DIR . '/includes/walkthrough-admin.php';
require_once VLP_WALKTHROUGH_DIR . '/includes/walkthrough-frontend.php';
require_once VLP_WALKTHROUGH_DIR . '/includes/walkthrough-shortcodes.php';

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'vlp_walkthrough_activate');
function vlp_walkthrough_activate() {
    vlp_walkthrough_create_tables();
    vlp_walkthrough_set_default_options();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'vlp_walkthrough_deactivate');
function vlp_walkthrough_deactivate() {
    // Clean up any temporary data if needed
}

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', 'vlp_walkthrough_init');
function vlp_walkthrough_init() {
    // Check if we need to run any database migrations
    vlp_walkthrough_check_migrations();
    
    // Initialize admin interface if in admin
    if (is_admin()) {
        vlp_walkthrough_admin_init();
    }
    
    // Initialize frontend functionality
    vlp_walkthrough_frontend_init();
}

/**
 * Check for database migrations
 */
function vlp_walkthrough_check_migrations() {
    $current_version = get_option('vlp_walkthrough_version', '0.0.0');
    
    if (version_compare($current_version, VLP_WALKTHROUGH_VERSION, '<')) {
        vlp_walkthrough_run_migrations($current_version);
        update_option('vlp_walkthrough_version', VLP_WALKTHROUGH_VERSION);
    }
}

/**
 * Run database migrations
 */
function vlp_walkthrough_run_migrations($from_version) {
    global $wpdb;
    
    // Future migrations can be added here
    // For now, we'll just ensure tables exist
    vlp_walkthrough_create_tables();
}

/**
 * Set default plugin options
 */
function vlp_walkthrough_set_default_options() {
    $default_options = array(
        'vlp_walkthrough_enabled' => true,
        'vlp_walkthrough_auto_trigger' => true,
        'vlp_walkthrough_url_trigger' => 'show_tour',
        'vlp_walkthrough_modal_theme' => 'vlp_default',
        'vlp_walkthrough_animation_speed' => 300,
        'vlp_walkthrough_show_progress' => true,
        'vlp_walkthrough_allow_skip' => true,
        'vlp_walkthrough_allow_disable' => true
    );
    
    foreach ($default_options as $option => $value) {
        if (get_option($option) === false) {
            update_option($option, $value);
        }
    }
}

/**
 * Enqueue admin scripts and styles
 */
add_action('admin_enqueue_scripts', 'vlp_walkthrough_admin_scripts');
function vlp_walkthrough_admin_scripts($hook) {
    if (strpos($hook, 'avlp-walkthrough') !== false) {
        wp_enqueue_script('vlp-walkthrough-admin', VLP_WALKTHROUGH_URL . 'js/walkthrough-admin.js', array('jquery', 'jquery-ui-sortable'), VLP_WALKTHROUGH_VERSION, true);
        wp_enqueue_style('vlp-walkthrough-admin', VLP_WALKTHROUGH_URL . 'css/walkthrough-admin.css', array(), VLP_WALKTHROUGH_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('vlp-walkthrough-admin', 'vlp_walkthrough_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vlp_walkthrough_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this tour?', 'avlp-walkthrough'),
                'confirm_delete_step' => __('Are you sure you want to delete this step?', 'avlp-walkthrough'),
                'saving' => __('Saving...', 'avlp-walkthrough'),
                'saved' => __('Saved!', 'avlp-walkthrough'),
                'error' => __('An error occurred. Please try again.', 'avlp-walkthrough')
            )
        ));
    }
}

/**
 * Enqueue frontend scripts and styles
 */
add_action('wp_enqueue_scripts', 'vlp_walkthrough_frontend_scripts');
function vlp_walkthrough_frontend_scripts() {
    // Only enqueue on pages where tours might be active
    if (vlp_walkthrough_should_load_assets()) {
        wp_enqueue_script('vlp-walkthrough-frontend', VLP_WALKTHROUGH_URL . 'js/walkthrough-frontend.js', array('jquery'), VLP_WALKTHROUGH_VERSION, true);
        wp_enqueue_style('vlp-walkthrough-frontend', VLP_WALKTHROUGH_URL . 'css/walkthrough-frontend.css', array(), VLP_WALKTHROUGH_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('vlp-walkthrough-frontend', 'vlp_walkthrough_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vlp_walkthrough_frontend_nonce'),
            'animation_speed' => get_option('vlp_walkthrough_animation_speed', 300),
            'show_progress' => get_option('vlp_walkthrough_show_progress', true),
            'allow_skip' => get_option('vlp_walkthrough_allow_skip', true),
            'allow_disable' => get_option('vlp_walkthrough_allow_disable', true),
            'strings' => array(
                'skip_tour' => __('Skip Tour', 'avlp-walkthrough'),
                'disable_tours' => __('Don\'t show tours again', 'avlp-walkthrough'),
                'next' => __('Next', 'avlp-walkthrough'),
                'previous' => __('Previous', 'avlp-walkthrough'),
                'finish' => __('Finish', 'avlp-walkthrough'),
                'step' => __('Step', 'avlp-walkthrough'),
                'of' => __('of', 'avlp-walkthrough')
            )
        ));
    }
}

/**
 * Check if tour assets should be loaded on current page
 */
function vlp_walkthrough_should_load_assets() {
    // Check if tours are enabled
    if (!get_option('vlp_walkthrough_enabled', true)) {
        return false;
    }
    
    // Check if user has disabled tours permanently
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        if (get_user_meta($user_id, 'vlp_walkthrough_disabled', true)) {
            return false;
        }
    }
    
    // Check if there are active tours for current page
    $current_url = vlp_walkthrough_get_current_page_url();
    $active_tours = vlp_walkthrough_get_active_tours_for_page($current_url);
    
    return !empty($active_tours);
}

/**
 * Get current page URL for tour matching
 */
function vlp_walkthrough_get_current_page_url() {
    global $wp;
    
    // Get the current URL path
    $current_path = home_url(add_query_arg(array(), $wp->request));
    
    // Remove query parameters except our tour trigger
    $parsed_url = parse_url($current_path);
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '/';
    
    return $path;
}

/**
 * AJAX handler for tour interactions
 */
add_action('wp_ajax_vlp_walkthrough_track_interaction', 'vlp_walkthrough_ajax_track_interaction');
add_action('wp_ajax_nopriv_vlp_walkthrough_track_interaction', 'vlp_walkthrough_ajax_track_interaction');
function vlp_walkthrough_ajax_track_interaction() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vlp_walkthrough_frontend_nonce')) {
        wp_die('Security check failed');
    }
    
    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    $tour_id = intval($_POST['tour_id']);
    $action = isset($_POST['action_type']) ? sanitize_text_field(wp_unslash($_POST['action_type'])) : '';
    $page_url = isset($_POST['page_url']) ? sanitize_text_field(wp_unslash($_POST['page_url'])) : '';
    $step_completed = intval($_POST['step_completed']);
    
    // Track the interaction
    $result = vlp_walkthrough_track_user_interaction($user_id, $tour_id, $action, $page_url, $step_completed);
    
    wp_send_json_success($result);
}

/**
 * AJAX handler for getting tour data
 */
add_action('wp_ajax_vlp_walkthrough_get_tour', 'vlp_walkthrough_ajax_get_tour');
add_action('wp_ajax_nopriv_vlp_walkthrough_get_tour', 'vlp_walkthrough_ajax_get_tour');
function vlp_walkthrough_ajax_get_tour() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vlp_walkthrough_frontend_nonce')) {
        wp_die('Security check failed');
    }
    
    $tour_id = intval($_POST['tour_id']);
    $page_url = isset($_POST['page_url']) ? sanitize_text_field(wp_unslash($_POST['page_url'])) : '';
    
    $tour_data = vlp_walkthrough_get_tour_for_ajax($tour_id, $page_url);
    
    if ($tour_data) {
        wp_send_json_success($tour_data);
    } else {
        wp_send_json_error('Tour not found');
    }
}

// New AJAX handler for getting tours for current page
add_action('wp_ajax_vlp_walkthrough_get_page_tours', 'vlp_walkthrough_ajax_get_page_tours');
add_action('wp_ajax_nopriv_vlp_walkthrough_get_page_tours', 'vlp_walkthrough_ajax_get_page_tours');
function vlp_walkthrough_ajax_get_page_tours() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vlp_walkthrough_frontend_nonce')) {
        wp_die('Security check failed');
    }
    
    $current_url = isset($_POST['current_url']) ? sanitize_text_field(wp_unslash($_POST['current_url'])) : '';
    $user_id = get_current_user_id();
    
    // Get active tours for this page
    $tours = vlp_walkthrough_get_active_tours_for_page($current_url);
    
    if (empty($tours)) {
        wp_send_json_success(array('tours' => array()));
    }
    
    $tour_data = array();
    
    foreach ($tours as $tour) {
        // Check if user has already completed/skipped this tour for this page
        $user_tracking = vlp_walkthrough_get_user_tracking($user_id, $tour->tour_id, $current_url);
        
        if ($user_tracking && in_array($user_tracking->status, array('completed', 'skipped_permanent'))) {
            continue; // Skip this tour
        }
        
        // Get tour steps for this page
        $steps = vlp_walkthrough_get_tour_steps($tour->tour_id, $current_url);
        
        if (!empty($steps)) {
            $tour_info = array(
                'tour_id' => $tour->tour_id,
                'tour_name' => $tour->tour_name,
                'tour_description' => $tour->tour_description,
                'show_progress' => $tour->show_progress,
                'steps' => array(),
                'user_tracking' => $user_tracking ? array(
                    'status' => $user_tracking->status,
                    'last_step_completed' => $user_tracking->last_step_completed
                ) : array(
                    'status' => 'not_started',
                    'last_step_completed' => 0
                )
            );
            
            foreach ($steps as $step) {
                // Process step content (shortcodes, etc.)
                $step_content = vlp_walkthrough_process_step_content($step->step_content, $user_id);
                
                $tour_info['steps'][] = array(
                    'step_id' => $step->step_id,
                    'step_order' => $step->step_order,
                    'step_title' => $step->step_title,
                    'step_content' => $step_content,
                    'target_selector' => $step->target_selector,
                    'step_position' => $step->step_position,
                    'step_delay' => $step->step_delay
                );
            }
            
            $tour_data[] = $tour_info;
        }
    }
    
    wp_send_json_success(array('tours' => $tour_data));
}
