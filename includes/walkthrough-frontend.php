<?php
/**
 * Frontend functionality for AVLP Walkthrough Tour plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize frontend functionality
 */
function vlp_walkthrough_frontend_init() {
    add_action('wp_enqueue_scripts', 'vlp_walkthrough_enqueue_frontend_assets');
    add_action('wp_footer', 'vlp_walkthrough_render_tour_container');
    add_action('template_redirect', 'vlp_walkthrough_check_tour_triggers');
}

/**
 * Enqueue frontend assets
 */
function vlp_walkthrough_enqueue_frontend_assets() {
    // Always load assets - let the JavaScript handle tour detection
    // This avoids the chicken-and-egg problem of needing assets to detect tours
    
    // Enqueue frontend CSS
    wp_enqueue_style(
        'vlp-walkthrough-frontend-style',
        VLP_WALKTHROUGH_URL . 'css/walkthrough-frontend.css',
        array(),
        VLP_WALKTHROUGH_VERSION
    );
    
    // Enqueue frontend JavaScript
    wp_enqueue_script(
        'vlp-walkthrough-frontend-script',
        VLP_WALKTHROUGH_URL . 'js/walkthrough-frontend.js',
        array('jquery'),
        VLP_WALKTHROUGH_VERSION,
        true
    );
    
    // Localize script with AJAX data
    wp_localize_script(
        'vlp-walkthrough-frontend-script',
        'vlpWalkthroughTour',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vlp_walkthrough_frontend_nonce'),
            'current_url' => vlp_walkthrough_get_current_page_url(),
            'strings' => array(
                'next' => __('Next', 'avlp-walkthrough-tour'),
                'previous' => __('Previous', 'avlp-walkthrough-tour'),
                'finish' => __('Finish', 'avlp-walkthrough-tour'),
                'skip' => __('Skip for now', 'avlp-walkthrough-tour'),
                'disable' => __('Don\'t show again', 'avlp-walkthrough-tour'),
                'close' => __('Close', 'avlp-walkthrough-tour')
            )
        )
    );
}

/**
 * Check for tour triggers on page load
 */
function vlp_walkthrough_check_tour_triggers() {
    // Check if tours are enabled
    if (!get_option('vlp_walkthrough_enabled', true)) {
        return;
    }
    
    // Check if user has disabled tours permanently
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        if (vlp_walkthrough_user_has_disabled_tours($user_id)) {
            return;
        }
    }
    
    $current_url = vlp_walkthrough_get_current_page_url();
    $active_tours = vlp_walkthrough_get_active_tours_for_page($current_url);
    
    if (empty($active_tours)) {
        return;
    }
    
    // Check URL parameter trigger
    $url_trigger = get_option('vlp_walkthrough_url_trigger', 'show_tour');
    if (isset($_GET[$url_trigger])) {
        // Show tour via URL parameter
        add_action('wp_footer', function() use ($active_tours) {
            vlp_walkthrough_start_tour_via_trigger($active_tours[0]->tour_id);
        });
        return;
    }
    
    // Check for automatic triggers
    foreach ($active_tours as $tour) {
        if ($tour->tour_trigger_type === 'automatic') {
            // Check if user has already completed or skipped this tour
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $tracking = vlp_walkthrough_get_user_tracking($user_id, $tour->tour_id, $current_url);
                
                if ($tracking && in_array($tracking->status, array('completed', 'skipped_permanent'))) {
                    continue;
                }
            }
            
            // Start automatic tour
            add_action('wp_footer', function() use ($tour) {
                vlp_walkthrough_start_tour_via_trigger($tour->tour_id);
            });
            break; // Only start one automatic tour per page
        }
    }
}

/**
 * Start tour via trigger (automatic or URL parameter)
 */
function vlp_walkthrough_start_tour_via_trigger($tour_id) {
    $current_url = vlp_walkthrough_get_current_page_url();
    $tour_data = vlp_walkthrough_get_tour_for_ajax($tour_id, $current_url);
    
    if (!$tour_data || empty($tour_data->steps)) {
        return;
    }
    
    // Track that tour was started
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        vlp_walkthrough_track_user_interaction($user_id, $tour_id, 'in_progress', $current_url, 0);
    }
    
    // Output JavaScript to start tour
    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'if (typeof VLPWalkthrough !== "undefined") {';
    echo 'VLPWalkthrough.startTour(' . wp_json_encode($tour_data) . ');';
    echo '}';
    echo '});';
    echo '</script>';
}


/**
 * Render tour container in footer
 */
function vlp_walkthrough_render_tour_container() {
    // Always render container - let JavaScript handle tour detection
    
    echo '<div id="vlp-walkthrough-container" style="display: none;">';
    echo '<div id="vlp-walkthrough-overlay" class="vlp-walkthrough-overlay"></div>';
    echo '<div id="vlp-walkthrough-tooltip" class="vlp-walkthrough-tooltip">';
    echo '<div class="vlp-walkthrough-arrow"></div>';
    echo '<div class="vlp-walkthrough-content">';
    echo '<div class="vlp-walkthrough-header">';
    echo '<h3 class="vlp-walkthrough-title"></h3>';
    echo '<button type="button" class="vlp-walkthrough-close" aria-label="Close tour">&times;</button>';
    echo '</div>';
    echo '<div class="vlp-walkthrough-body"></div>';
    echo '<div class="vlp-walkthrough-footer">';
    echo '<div class="vlp-walkthrough-progress">';
    echo '<div class="vlp-walkthrough-progress-bar">';
    echo '<div class="vlp-walkthrough-progress-fill"></div>';
    echo '</div>';
    echo '<div class="vlp-walkthrough-progress-text"></div>';
    echo '</div>';
    echo '<div class="vlp-walkthrough-actions">';
    echo '<button type="button" class="vlp-walkthrough-skip">Skip Tour</button>';
    echo '<button type="button" class="vlp-walkthrough-disable">Don\'t show this tour again</button>';
    echo '<button type="button" class="vlp-walkthrough-prev">Previous</button>';
    echo '<button type="button" class="vlp-walkthrough-next">Next</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Process dynamic content in step content
 */
function vlp_walkthrough_process_step_content($content, $user_id = null) {
    // Process shortcodes
    $content = do_shortcode($content);
    
    // Process user-specific content if user is logged in
    if ($user_id && is_user_logged_in()) {
        // Replace user field shortcodes
        $content = preg_replace_callback('/\[vlp_user_field\s+field="([^"]+)"\]/', function($matches) use ($user_id) {
            $field = $matches[1];
            return vlp_get_user_field($user_id, $field);
        }, $content);
        
        // Replace coach image shortcode
        $content = preg_replace_callback('/\[vlp_coach_image\s+size="([^"]+)"\]/', function($matches) use ($user_id) {
            $size = $matches[1];
            $coach_id = vlp_get_user_field($user_id, 'ai_preferred_coach');
            
            if ($coach_id) {
                // Get coach image based on size
                $env_vars = vlp_get_environment_vars();
                $base_url = $env_vars['Domain'];
                
                switch ($size) {
                    case 'small':
                        return '<img src="' . $base_url . '/wp-content/plugins/avlp-ai-assistant/img/ai-sphere.webp" alt="AI Coach" class="vlp-coach-image-small">';
                    case 'default':
                        return '<img src="' . $base_url . '/wp-content/plugins/avlp-ai-assistant/img/ai-sphere.webp" alt="AI Coach" class="vlp-coach-image-default">';
                    default:
                        return '<img src="' . $base_url . '/wp-content/plugins/avlp-ai-assistant/img/ai-sphere.webp" alt="AI Coach">';
                }
            }
            
            return '';
        }, $content);
    }
    
    return $content;
}

/**
 * Get tour data for AJAX requests with processed content
 */
function vlp_walkthrough_get_tour_for_ajax($tour_id, $page_url) {
    $tour = vlp_walkthrough_get_tour_for_page($tour_id, $page_url);
    
    if (!$tour) {
        return false;
    }
    
    $user_id = is_user_logged_in() ? get_current_user_id() : null;
    
    // Process step content
    foreach ($tour->steps as $step) {
        $step->step_content = vlp_walkthrough_process_step_content($step->step_content, $user_id);
    }
    
    return $tour;
}

/**
 * Handle tour completion tracking
 */
function vlp_walkthrough_handle_tour_completion($tour_id, $status, $current_step = 0) {
    $current_url = vlp_walkthrough_get_current_page_url();
    
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        vlp_walkthrough_track_user_interaction($user_id, $tour_id, $status, $current_url, $current_step);
        
        // Handle permanent disable
        if ($status === 'skipped_permanent') {
            vlp_walkthrough_set_user_tour_preference($user_id, true);
        }
    }
    
    // Set session-based skip for non-logged-in users
    if ($status === 'skipped_session') {
        $_SESSION['vlp_walkthrough_skipped_' . $tour_id] = true;
    }
}

/**
 * Check if tour should be skipped for current session
 */
function vlp_walkthrough_is_tour_skipped_for_session($tour_id) {
    if (!is_user_logged_in()) {
        return isset($_SESSION['vlp_walkthrough_skipped_' . $tour_id]);
    }
    
    return false;
}

/**
 * Add tour trigger button to specific elements
 */
function vlp_walkthrough_add_trigger_to_element($selector, $tour_id, $button_text = 'Start Tour') {
    static $triggers_added = array();
    
    $key = $selector . '_' . $tour_id;
    
    if (isset($triggers_added[$key])) {
        return;
    }
    
    $triggers_added[$key] = true;
    
    echo '<script type="text/javascript">';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var element = document.querySelector("' . esc_js($selector) . '");';
    echo 'if (element) {';
    echo 'var button = document.createElement("button");';
    echo 'button.type = "button";';
    echo 'button.className = "vlp-walkthrough-trigger vlp-walkthrough-element-trigger";';
    echo 'button.setAttribute("data-tour-id", "' . intval($tour_id) . '");';
    echo 'button.textContent = "' . esc_js($button_text) . '";';
    echo 'element.appendChild(button);';
    echo '}';
    echo '});';
    echo '</script>';
}

/**
 * Initialize session for non-logged-in users
 */
function vlp_walkthrough_init_session() {
    if (!session_id()) {
        session_start();
    }
}

// Initialize session for tour tracking
add_action('init', 'vlp_walkthrough_init_session');

/**
 * Add tour data to page for JavaScript access
 */
function vlp_walkthrough_add_tour_data_to_page() {
    if (!vlp_walkthrough_should_load_assets()) {
        return;
    }
    
    $current_url = vlp_walkthrough_get_current_page_url();
    $active_tours = vlp_walkthrough_get_active_tours_for_page($current_url);
    
    if (!empty($active_tours)) {
        $tour_data = array();
        
        foreach ($active_tours as $tour) {
            if ($tour->tour_trigger_type === 'automatic' || 
                (isset($_GET[get_option('vlp_walkthrough_url_trigger', 'show_tour')]))) {
                
                $full_tour = vlp_walkthrough_get_tour_for_ajax($tour->tour_id, $current_url);
                
                if ($full_tour && !empty($full_tour->steps)) {
                    $tour_data[] = $full_tour;
                }
            }
        }
        
        if (!empty($tour_data)) {
            echo '<script type="text/javascript">';
            echo 'window.VLPWalkthroughData = ' . wp_json_encode($tour_data) . ';';
            echo '</script>';
        }
    }
}

add_action('wp_head', 'vlp_walkthrough_add_tour_data_to_page');
