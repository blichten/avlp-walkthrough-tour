<?php
/**
 * Shortcodes for AVLP Walkthrough Tour plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize shortcodes
 */
function vlp_walkthrough_init_shortcodes() {
    add_shortcode('vlp_walkthrough_tour', 'vlp_walkthrough_tour_shortcode');
    add_shortcode('vlp_walkthrough_trigger', 'vlp_walkthrough_trigger_shortcode');
    add_shortcode('vlp_walkthrough_stats', 'vlp_walkthrough_stats_shortcode');
}

add_action('init', 'vlp_walkthrough_init_shortcodes');

/**
 * Tour trigger shortcode
 * Usage: [vlp_walkthrough_tour tour_id="1" text="Start Tour" class="custom-class"]
 */
function vlp_walkthrough_tour_shortcode($atts) {
    $atts = shortcode_atts(array(
        'tour_id' => 0,
        'text' => 'Start Tour',
        'class' => 'vlp-walkthrough-trigger'
    ), $atts);
    
    $tour_id = intval($atts['tour_id']);
    
    if (!$tour_id) {
        return '<p>Error: Tour ID required.</p>';
    }
    
    $tour = vlp_walkthrough_get_tour($tour_id);
    
    if (!$tour) {
        return '<p>Error: Tour not found.</p>';
    }
    
    $current_url = vlp_walkthrough_get_current_page_url();
    $tour_data = vlp_walkthrough_get_tour_for_ajax($tour_id, $current_url);
    
    if (!$tour_data || empty($tour_data->steps)) {
        return '<p>Error: No active steps found for this tour.</p>';
    }
    
    $button_text = esc_html($atts['text']);
    $button_class = esc_attr($atts['class']);
    
    $output = '<button type="button" class="' . $button_class . '" data-tour-id="' . $tour_id . '">';
    $output .= $button_text;
    $output .= '</button>';
    
    // Add JavaScript to handle button click
    $output .= '<script type="text/javascript">';
    $output .= 'document.addEventListener("DOMContentLoaded", function() {';
    $output .= 'var button = document.querySelector("[data-tour-id=\'' . $tour_id . '\']");';
    $output .= 'if (button && typeof VLPWalkthrough !== "undefined") {';
    $output .= 'button.addEventListener("click", function() {';
    $output .= 'VLPWalkthrough.startTour(' . wp_json_encode($tour_data) . ');';
    $output .= '});';
    $output .= '}';
    $output .= '});';
    $output .= '</script>';
    
    return $output;
}

/**
 * Generic trigger shortcode
 * Usage: [vlp_walkthrough_trigger tour_id="1" element=".my-element" text="Learn More"]
 */
function vlp_walkthrough_trigger_shortcode($atts) {
    $atts = shortcode_atts(array(
        'tour_id' => 0,
        'element' => '',
        'text' => 'Start Tour',
        'position' => 'after'
    ), $atts);
    
    $tour_id = intval($atts['tour_id']);
    
    if (!$tour_id) {
        return '<p>Error: Tour ID required.</p>';
    }
    
    $tour = vlp_walkthrough_get_tour($tour_id);
    
    if (!$tour) {
        return '<p>Error: Tour not found.</p>';
    }
    
    $element = sanitize_text_field($atts['element']);
    $button_text = esc_html($atts['text']);
    $position = sanitize_text_field($atts['position']);
    
    if (!$element) {
        return '<p>Error: Target element selector required.</p>';
    }
    
    $output = '<script type="text/javascript">';
    $output .= 'document.addEventListener("DOMContentLoaded", function() {';
    $output .= 'var targetElement = document.querySelector("' . esc_js($element) . '");';
    $output .= 'if (targetElement) {';
    $output .= 'var button = document.createElement("button");';
    $output .= 'button.type = "button";';
    $output .= 'button.className = "vlp-walkthrough-trigger vlp-walkthrough-element-trigger";';
    $output .= 'button.setAttribute("data-tour-id", "' . $tour_id . '");';
    $output .= 'button.textContent = "' . esc_js($button_text) . '";';
    
    if ($position === 'before') {
        $output .= 'targetElement.parentNode.insertBefore(button, targetElement);';
    } else {
        $output .= 'targetElement.appendChild(button);';
    }
    
    $output .= 'button.addEventListener("click", function() {';
    $output .= 'if (typeof VLPWalkthrough !== "undefined") {';
    $output .= 'var tourData = window.VLPWalkthroughData ? window.VLPWalkthroughData.find(function(tour) { return tour.tour_id === ' . $tour_id . '; }) : null;';
    $output .= 'if (tourData) {';
    $output .= 'VLPWalkthrough.startTour(tourData);';
    $output .= '}';
    $output .= '}';
    $output .= '});';
    $output .= '}';
    $output .= '});';
    $output .= '</script>';
    
    return $output;
}

/**
 * Tour statistics shortcode
 * Usage: [vlp_walkthrough_stats tour_id="1" show="completion_rate"]
 */
function vlp_walkthrough_stats_shortcode($atts) {
    $atts = shortcode_atts(array(
        'tour_id' => 0,
        'show' => 'completion_rate'
    ), $atts);
    
    $tour_id = intval($atts['tour_id']);
    $show = sanitize_text_field($atts['show']);
    
    if (!$tour_id) {
        return '<p>Error: Tour ID required.</p>';
    }
    
    $stats = vlp_walkthrough_get_tour_stats($tour_id);
    
    if (empty($stats)) {
        return '<p>No statistics available for this tour.</p>';
    }
    
    $output = '<div class="vlp-walkthrough-stats">';
    
    switch ($show) {
        case 'completion_rate':
            $completed = 0;
            $total = 0;
            
            foreach ($stats as $stat) {
                $total += intval($stat->count);
                if ($stat->status === 'completed') {
                    $completed = intval($stat->count);
                }
            }
            
            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
            
            $output .= '<div class="vlp-stats-completion-rate">';
            $output .= '<strong>Completion Rate:</strong> ' . $rate . '%';
            $output .= '<div class="vlp-stats-breakdown">';
            $output .= '<span class="vlp-stats-completed">Completed: ' . $completed . '</span>';
            $output .= '<span class="vlp-stats-total">Total: ' . $total . '</span>';
            $output .= '</div>';
            $output .= '</div>';
            break;
            
        case 'all':
            $output .= '<div class="vlp-stats-all">';
            $output .= '<h4>Tour Statistics</h4>';
            $output .= '<table class="vlp-stats-table">';
            $output .= '<thead>';
            $output .= '<tr><th>Status</th><th>Count</th><th>Unique Users</th></tr>';
            $output .= '</thead>';
            $output .= '<tbody>';
            
            foreach ($stats as $stat) {
                $output .= '<tr>';
                $output .= '<td>' . esc_html(ucfirst(str_replace('_', ' ', $stat->status))) . '</td>';
                $output .= '<td>' . intval($stat->count) . '</td>';
                $output .= '<td>' . intval($stat->unique_users) . '</td>';
                $output .= '</tr>';
            }
            
            $output .= '</tbody>';
            $output .= '</table>';
            $output .= '</div>';
            break;
            
        default:
            $output .= '<p>Invalid show parameter. Use "completion_rate" or "all".</p>';
            break;
    }
    
    $output .= '</div>';
    
    return $output;
}
