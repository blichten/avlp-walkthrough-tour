<?php
/**
 * Admin interface for AVLP Walkthrough Tour plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize admin interface
 */
function vlp_walkthrough_admin_init() {
    add_action('admin_menu', 'vlp_walkthrough_add_admin_menu');
    add_action('admin_enqueue_scripts', 'vlp_walkthrough_admin_enqueue_scripts');
    add_action('admin_post_vlp_walkthrough_save_tour', 'vlp_walkthrough_admin_save_tour');
    add_action('admin_post_vlp_walkthrough_save_step', 'vlp_walkthrough_admin_save_step');
    add_action('admin_post_vlp_walkthrough_delete_tour', 'vlp_walkthrough_admin_delete_tour');
    add_action('admin_post_vlp_walkthrough_delete_step', 'vlp_walkthrough_admin_delete_step');
    add_action('wp_ajax_vlp_walkthrough_reorder_steps', 'vlp_walkthrough_admin_reorder_steps');
    add_action('wp_ajax_vlp_walkthrough_get_step', 'vlp_walkthrough_admin_get_step');
    add_action('wp_ajax_vlp_walkthrough_delete_step_ajax', 'vlp_walkthrough_admin_delete_step_ajax');
}

/**
 * Enqueue admin scripts and styles
 */
function vlp_walkthrough_admin_enqueue_scripts($hook) {
    // Only load on our admin page
    if ($hook !== 'avlp-admin_page_avlp-walkthrough') {
        return;
    }
    
    // Enqueue admin CSS
    wp_enqueue_style(
        'vlp-walkthrough-admin-style',
        VLP_WALKTHROUGH_URL . 'css/walkthrough-admin.css',
        array(),
        VLP_WALKTHROUGH_VERSION
    );
    
    // Enqueue admin JavaScript
    wp_enqueue_script(
        'vlp-walkthrough-admin-script',
        VLP_WALKTHROUGH_URL . 'js/walkthrough-admin.js',
        array('jquery', 'jquery-ui-sortable'),
        VLP_WALKTHROUGH_VERSION,
        true
    );
    
    // Localize script with AJAX data
    wp_localize_script(
        'vlp-walkthrough-admin-script',
        'vlp_walkthrough_ajax',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vlp_walkthrough_admin_nonce'),
            'strings' => array(
                'confirm_delete_step' => __('Are you sure you want to delete this step?', 'avlp-walkthrough-tour'),
                'confirm_delete_tour' => __('Are you sure you want to delete this tour? This will also delete all its steps.', 'avlp-walkthrough-tour'),
                'saving' => __('Saving...', 'avlp-walkthrough-tour'),
                'error' => __('An error occurred. Please try again.', 'avlp-walkthrough-tour'),
                'success' => __('Success!', 'avlp-walkthrough-tour')
            )
        )
    );
}

/**
 * Add admin menu
 */
function vlp_walkthrough_add_admin_menu() {
    add_submenu_page(
        'avlp-admin',                    // Parent menu slug
        'Walkthrough Tours',             // Page title
        'Walkthrough Tours',             // Menu title
        'manage_options',                // Capability required
        'avlp-walkthrough',              // Menu slug
        'vlp_walkthrough_admin_page'     // Callback function
    );
}

/**
 * Main admin page callback
 */
function vlp_walkthrough_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $tour_id = isset($_GET['tour_id']) ? intval($_GET['tour_id']) : 0;
    
    echo '<div class="wrap">';
    echo '<h1>AVLP Walkthrough Tours</h1>';
    
    switch ($action) {
        case 'edit':
        case 'new':
            vlp_walkthrough_admin_edit_tour($tour_id);
            break;
        case 'steps':
            vlp_walkthrough_admin_tour_steps($tour_id);
            break;
        case 'stats':
            vlp_walkthrough_admin_tour_stats($tour_id);
            break;
        default:
            vlp_walkthrough_admin_tours_list();
            break;
    }
    
    echo '</div>';
}

/**
 * Display tours list
 */
function vlp_walkthrough_admin_tours_list() {
    $tours = vlp_walkthrough_get_active_tours();
    
    echo '<div class="vlp-admin-header">';
    echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough&action=new') . '" class="button button-primary">Add New Tour</a>';
    echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough&action=stats') . '" class="button">View Statistics</a>';
    echo '</div>';
    
    if (empty($tours)) {
        echo '<div class="notice notice-info"><p>No tours found. <a href="' . admin_url('admin.php?page=avlp-walkthrough&action=new') . '">Create your first tour</a>.</p></div>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Tour Name</th>';
    echo '<th>Description</th>';
    echo '<th>Trigger Type</th>';
    echo '<th>Steps</th>';
    echo '<th>Status</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($tours as $tour) {
        $steps_count = vlp_walkthrough_get_tour_steps($tour->tour_id);
        $steps_count = count($steps_count);
        
        echo '<tr>';
        echo '<td><strong>' . esc_html($tour->tour_name) . '</strong></td>';
        echo '<td>' . esc_html(wp_trim_words($tour->tour_description, 10)) . '</td>';
        echo '<td>' . esc_html(ucfirst($tour->tour_trigger_type)) . '</td>';
        echo '<td>' . $steps_count . ' step' . ($steps_count !== 1 ? 's' : '') . '</td>';
        echo '<td>' . ($tour->is_active ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>') . '</td>';
        echo '<td>';
        echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough&action=edit&tour_id=' . $tour->tour_id) . '" class="button button-small">Edit</a> ';
        echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough&action=steps&tour_id=' . $tour->tour_id) . '" class="button button-small">Steps</a> ';
        echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough&action=stats&tour_id=' . $tour->tour_id) . '" class="button button-small">Stats</a> ';
        echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough&action=delete&tour_id=' . $tour->tour_id) . '" class="button button-small button-link-delete" onclick="return confirm(\'Are you sure you want to delete this tour?\')">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}

/**
 * Edit tour form
 */
function vlp_walkthrough_admin_edit_tour($tour_id = 0) {
    $tour = $tour_id ? vlp_walkthrough_get_tour($tour_id) : null;
    
    $tour_name = $tour ? $tour->tour_name : '';
    $tour_description = $tour ? $tour->tour_description : '';
    $tour_trigger_type = $tour ? $tour->tour_trigger_type : 'automatic';
    $tour_trigger_value = $tour ? $tour->tour_trigger_value : '';
    $show_progress = $tour ? $tour->show_progress : 1;
    $is_active = $tour ? $tour->is_active : 1;
    
    $form_action = admin_url('admin-post.php');
    $nonce_field = wp_nonce_field('vlp_walkthrough_admin_nonce', '_wpnonce', true, false);
    
    echo '<form method="post" action="' . $form_action . '">';
    echo $nonce_field;
    echo '<input type="hidden" name="action" value="vlp_walkthrough_save_tour">';
    if ($tour_id) {
        echo '<input type="hidden" name="tour_id" value="' . $tour_id . '">';
    }
    
    echo '<table class="form-table">';
    echo '<tbody>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="tour_name">Tour Name</label></th>';
    echo '<td><input type="text" id="tour_name" name="tour_name" value="' . esc_attr($tour_name) . '" class="regular-text" required></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="tour_description">Description</label></th>';
    echo '<td><textarea id="tour_description" name="tour_description" rows="3" class="large-text">' . esc_textarea($tour_description) . '</textarea></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="tour_trigger_type">Trigger Type</label></th>';
    echo '<td>';
    echo '<select id="tour_trigger_type" name="tour_trigger_type">';
    echo '<option value="automatic"' . selected($tour_trigger_type, 'automatic', false) . '>Automatic (on page visit)</option>';
    echo '<option value="manual"' . selected($tour_trigger_type, 'manual', false) . '>Manual (via shortcode/button)</option>';
    echo '<option value="url_parameter"' . selected($tour_trigger_type, 'url_parameter', false) . '>URL Parameter</option>';
    echo '</select>';
    echo '<p class="description">How the tour should be triggered.</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '<tr id="trigger_value_row"' . ($tour_trigger_type === 'automatic' ? ' style="display:none;"' : '') . '>';
    echo '<th scope="row"><label for="tour_trigger_value">Trigger Value</label></th>';
    echo '<td>';
    echo '<input type="text" id="tour_trigger_value" name="tour_trigger_value" value="' . esc_attr($tour_trigger_value) . '" class="regular-text">';
    echo '<p class="description" id="trigger_value_description">';
    if ($tour_trigger_type === 'manual') {
        echo 'Shortcode to use: [vlp_walkthrough_tour tour_id="' . ($tour_id ?: 'X') . '"]';
    } elseif ($tour_trigger_type === 'url_parameter') {
        echo 'URL parameter name (e.g., "show_tour"). Tour will show when URL contains ?show_tour=1';
    }
    echo '</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="show_progress">Show Progress Tracker</label></th>';
    echo '<td>';
    echo '<label>';
    echo '<input type="checkbox" id="show_progress" name="show_progress" value="1"' . checked($show_progress, 1, false) . '> ';
    echo 'Display step counter (e.g., "1/3", "2/3")';
    echo '</label>';
    echo '<p class="description">Shows users their progress through the tour steps.</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row">Status</th>';
    echo '<td>';
    echo '<label>';
    echo '<input type="checkbox" name="is_active" value="1"' . checked($is_active, 1, false) . '> ';
    echo 'Active';
    echo '</label>';
    echo '<p class="description">Only active tours will be shown to users.</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" class="button-primary" value="' . ($tour_id ? 'Update Tour' : 'Create Tour') . '">';
    echo ' <a href="' . admin_url('admin.php?page=avlp-walkthrough') . '" class="button">Cancel</a>';
    if ($tour_id) {
        echo ' <a href="' . admin_url('admin.php?page=avlp-walkthrough&action=steps&tour_id=' . $tour_id) . '" class="button">Manage Steps</a>';
    }
    echo '</p>';
    
    echo '</form>';
    
    // JavaScript for trigger type changes
    echo '<script>
    jQuery(document).ready(function($) {
        $("#tour_trigger_type").change(function() {
            var triggerType = $(this).val();
            var $triggerRow = $("#trigger_value_row");
            var $description = $("#trigger_value_description");
            
            if (triggerType === "automatic") {
                $triggerRow.hide();
            } else {
                $triggerRow.show();
                if (triggerType === "manual") {
                    $description.text("Shortcode to use: [vlp_walkthrough_tour tour_id=\"' . ($tour_id ?: 'X') . '\"]");
                } else if (triggerType === "url_parameter") {
                    $description.text("URL parameter name (e.g., \"show_tour\"). Tour will show when URL contains ?show_tour=1");
                }
            }
        });
    });
    </script>';
}

/**
 * Tour steps management
 */
function vlp_walkthrough_admin_tour_steps($tour_id) {
    $tour = vlp_walkthrough_get_tour($tour_id);
    
    if (!$tour) {
        echo '<div class="notice notice-error"><p>Tour not found.</p></div>';
        return;
    }
    
    echo '<div class="vlp-admin-header">';
    echo '<h2>Steps for: ' . esc_html($tour->tour_name) . '</h2>';
    echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough&action=edit&tour_id=' . $tour_id) . '" class="button">Edit Tour</a> ';
    echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough') . '" class="button">Back to Tours</a>';
    echo '</div>';
    
    $steps = vlp_walkthrough_get_tour_steps($tour_id);
    
    echo '<div id="tour-steps-container">';
    
    if (empty($steps)) {
        echo '<div class="notice notice-info"><p>No steps found. <a href="#" class="add-step-button">Add your first step</a>.</p></div>';
    } else {
        echo '<ul id="tour-steps-list" class="sortable-steps">';
        
        foreach ($steps as $step) {
            vlp_walkthrough_admin_render_step($step);
        }
        
        echo '</ul>';
    }
    
    echo '</div>';
    
    // Add step form (initially hidden)
    echo '<div id="add-step-form" style="display: none;">';
    vlp_walkthrough_admin_add_step_form($tour_id);
    echo '</div>';
    
    echo '<p><button type="button" id="add-step-button" class="button button-primary">Add Step</button></p>';
}

/**
 * Render individual step
 */
function vlp_walkthrough_admin_render_step($step) {
    echo '<li class="step-item" data-step-id="' . $step->step_id . '">';
    echo '<div class="step-header">';
    echo '<span class="step-order">' . $step->step_order . '</span>';
    echo '<strong>' . esc_html($step->step_title) . '</strong>';
    echo '<div class="step-actions">';
    echo '<a href="#" class="edit-step" data-step-id="' . $step->step_id . '">Edit</a> ';
    echo '<a href="#" class="delete-step" data-step-id="' . $step->step_id . '">Delete</a>';
    echo '</div>';
    echo '</div>';
    echo '<div class="step-content">';
    echo '<p><strong>Target:</strong> ' . esc_html($step->target_selector) . '</p>';
    echo '<p><strong>Position:</strong> ' . esc_html(ucfirst($step->step_position)) . '</p>';
    echo '<p><strong>Content:</strong> ' . esc_html(wp_trim_words(strip_tags($step->step_content), 20)) . '</p>';
    echo '</div>';
    echo '</li>';
}

/**
 * Add step form
 */
function vlp_walkthrough_admin_add_step_form($tour_id, $step_id = 0) {
    $step = $step_id ? vlp_walkthrough_get_tour_step($step_id) : null;
    
    $step_title = $step ? $step->step_title : '';
    $step_content = $step ? $step->step_content : '';
    $target_selector = $step ? $step->target_selector : '';
    $step_position = $step ? $step->step_position : 'auto';
    $page_url_pattern = $step ? $step->page_url_pattern : '';
    $step_order = $step ? $step->step_order : (vlp_walkthrough_get_next_step_order($tour_id));
    
    $form_action = admin_url('admin-post.php');
    $nonce_field = wp_nonce_field('vlp_walkthrough_admin_nonce', '_wpnonce', true, false);
    
    echo '<form method="post" action="' . $form_action . '" class="step-form">';
    echo $nonce_field;
    echo '<input type="hidden" name="action" value="vlp_walkthrough_save_step">';
    echo '<input type="hidden" name="tour_id" value="' . $tour_id . '">';
    if ($step_id) {
        echo '<input type="hidden" name="step_id" value="' . $step_id . '">';
    }
    
    echo '<table class="form-table">';
    echo '<tbody>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="step_title">Step Title</label></th>';
    echo '<td><input type="text" id="step_title" name="step_title" value="' . esc_attr($step_title) . '" class="regular-text" required></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="step_content">Step Content</label></th>';
    echo '<td><textarea id="step_content" name="step_content" rows="4" class="large-text" required>' . esc_textarea($step_content) . '</textarea>';
    echo '<p class="description">HTML is allowed. You can use shortcodes like [vlp_user_field field="first_name"] for dynamic content.</p></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="target_selector">Target Selector</label></th>';
    echo '<td><input type="text" id="target_selector" name="target_selector" value="' . esc_attr($target_selector) . '" class="regular-text" required>';
    echo '<p class="description">CSS selector for the element to highlight (e.g., #header, .nav-menu, [data-tour="step1"])</p></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="step_position">Tooltip Position</label></th>';
    echo '<td>';
    echo '<select id="step_position" name="step_position">';
    echo '<option value="modal"' . selected($step_position, 'modal', false) . '>Modal (center of page)</option>';
    echo '<option value="auto"' . selected($step_position, 'auto', false) . '>Auto (best position)</option>';
    echo '<option value="top"' . selected($step_position, 'top', false) . '>Top</option>';
    echo '<option value="bottom"' . selected($step_position, 'bottom', false) . '>Bottom</option>';
    echo '<option value="left"' . selected($step_position, 'left', false) . '>Left</option>';
    echo '<option value="right"' . selected($step_position, 'right', false) . '>Right</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="page_url_pattern">Page URL Pattern</label></th>';
    echo '<td><input type="text" id="page_url_pattern" name="page_url_pattern" value="' . esc_attr($page_url_pattern) . '" class="regular-text">';
    echo '<p class="description">Optional: Only show this step on pages matching this URL pattern. Leave blank to show on all pages.</p></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row"><label for="step_delay">Step Order</label></th>';
    echo '<td><input type="number" id="step_order" name="step_order" value="' . esc_attr($step_order) . '" min="1" class="small-text">';
    echo '<p class="description">Order of this step in the tour.</p></td>';
    echo '</tr>';
    
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" class="button-primary" value="' . ($step_id ? 'Update Step' : 'Add Step') . '">';
    echo ' <button type="button" class="cancel-step button">Cancel</button>';
    echo '</p>';
    
    echo '</form>';
}

/**
 * Get tour step by ID
 */
function vlp_walkthrough_get_tour_step($step_id) {
    global $wpdb;
    
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    
    $step = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $steps_table WHERE step_id = %d",
            $step_id
        )
    );
    
    return $step;
}

/**
 * Get next step order for a tour
 */
function vlp_walkthrough_get_next_step_order($tour_id) {
    global $wpdb;
    
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    
    $max_order = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT MAX(step_order) FROM $steps_table WHERE tour_id = %d",
            $tour_id
        )
    );
    
    return ($max_order ? $max_order + 1 : 1);
}

/**
 * Tour statistics
 */
function vlp_walkthrough_admin_tour_stats($tour_id = null) {
    echo '<div class="vlp-admin-header">';
    echo '<h2>Tour Statistics</h2>';
    echo '<a href="' . admin_url('admin.php?page=avlp-walkthrough') . '" class="button">Back to Tours</a>';
    echo '</div>';
    
    $stats = vlp_walkthrough_get_tour_stats($tour_id);
    
    if (empty($stats)) {
        echo '<div class="notice notice-info"><p>No statistics available yet.</p></div>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Status</th>';
    echo '<th>Total Interactions</th>';
    echo '<th>Unique Users</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($stats as $stat) {
        echo '<tr>';
        echo '<td>' . esc_html(ucfirst(str_replace('_', ' ', $stat->status))) . '</td>';
        echo '<td>' . intval($stat->count) . '</td>';
        echo '<td>' . intval($stat->unique_users) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}

/**
 * Save tour handler
 */
function vlp_walkthrough_admin_save_tour() {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'vlp_walkthrough_admin_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }
    
    $tour_data = array(
        'tour_name' => sanitize_text_field($_POST['tour_name']),
        'tour_description' => sanitize_textarea_field($_POST['tour_description']),
        'tour_trigger_type' => sanitize_text_field($_POST['tour_trigger_type']),
        'tour_trigger_value' => sanitize_text_field($_POST['tour_trigger_value']),
        'show_progress' => isset($_POST['show_progress']) && $_POST['show_progress'] ? 1 : 0,
        'is_active' => isset($_POST['is_active']) && $_POST['is_active'] ? 1 : 0
    );
    
    $tour_id = isset($_POST['tour_id']) ? intval($_POST['tour_id']) : 0;
    
    if ($tour_id) {
        $result = vlp_walkthrough_update_tour($tour_id, $tour_data);
    } else {
        $result = vlp_walkthrough_create_tour($tour_data);
    }
    
    if ($result) {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&action=edit&tour_id=' . ($tour_id ?: $result) . '&updated=1'));
    } else {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&error=1'));
    }
    
    exit;
}

/**
 * Save step handler
 */
function vlp_walkthrough_admin_save_step() {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'vlp_walkthrough_admin_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }
    
    $step_data = array(
        'tour_id' => intval($_POST['tour_id']),
        'step_order' => intval($_POST['step_order']),
        'step_title' => sanitize_text_field($_POST['step_title']),
        'step_content' => wp_kses_post($_POST['step_content']),
        'target_selector' => sanitize_text_field($_POST['target_selector']),
        'step_position' => sanitize_text_field($_POST['step_position']),
        'page_url_pattern' => sanitize_text_field($_POST['page_url_pattern'])
    );
    
    $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;
    
    if ($step_id) {
        $result = vlp_walkthrough_update_tour_step($step_id, $step_data);
    } else {
        $result = vlp_walkthrough_create_tour_step($step_data);
    }
    
    if ($result) {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&action=steps&tour_id=' . $step_data['tour_id'] . '&updated=1'));
    } else {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&action=steps&tour_id=' . $step_data['tour_id'] . '&error=1'));
    }
    
    exit;
}

/**
 * Delete tour handler
 */
function vlp_walkthrough_admin_delete_tour() {
    if (!wp_verify_nonce($_GET['_wpnonce'], 'vlp_walkthrough_admin_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }
    
    $tour_id = intval($_GET['tour_id']);
    $result = vlp_walkthrough_delete_tour($tour_id);
    
    if ($result) {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&deleted=1'));
    } else {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&error=1'));
    }
    
    exit;
}

/**
 * Delete step handler
 */
function vlp_walkthrough_admin_delete_step() {
    if (!wp_verify_nonce($_GET['_wpnonce'], 'vlp_walkthrough_admin_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }
    
    $step_id = intval($_GET['step_id']);
    $result = vlp_walkthrough_delete_tour_step($step_id);
    
    if ($result) {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&action=steps&tour_id=' . intval($_GET['tour_id']) . '&deleted=1'));
    } else {
        wp_redirect(admin_url('admin.php?page=avlp-walkthrough&action=steps&tour_id=' . intval($_GET['tour_id']) . '&error=1'));
    }
    
    exit;
}

/**
 * AJAX handler for reordering steps
 */
function vlp_walkthrough_admin_reorder_steps() {
    if (!wp_verify_nonce($_POST['nonce'], 'vlp_walkthrough_admin_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }
    
    $step_ids = $_POST['step_ids'];
    
    foreach ($step_ids as $order => $step_id) {
        vlp_walkthrough_update_tour_step($step_id, array('step_order' => $order + 1));
    }
    
    wp_send_json_success();
}

/**
 * AJAX handler for getting step data
 */
function vlp_walkthrough_admin_get_step() {
    if (!wp_verify_nonce($_POST['nonce'], 'vlp_walkthrough_admin_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }
    
    $step_id = intval($_POST['step_id']);
    $step = vlp_walkthrough_get_tour_step($step_id);
    
    if ($step) {
        wp_send_json_success($step);
    } else {
        wp_send_json_error('Step not found');
    }
}

/**
 * AJAX handler for deleting step
 */
function vlp_walkthrough_admin_delete_step_ajax() {
    if (!wp_verify_nonce($_POST['nonce'], 'vlp_walkthrough_admin_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }
    
    $step_id = intval($_POST['step_id']);
    $result = vlp_walkthrough_delete_tour_step($step_id);
    
    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete step');
    }
}
