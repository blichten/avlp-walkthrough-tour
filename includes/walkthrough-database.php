<?php
/**
 * Database operations for AVLP Walkthrough Tour plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create database tables for the walkthrough tour plugin
 */
function vlp_walkthrough_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tours table
    $tours_table = $wpdb->prefix . 'avlp_tours';
    $tours_sql = "CREATE TABLE IF NOT EXISTS $tours_table (
        tour_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tour_name varchar(255) NOT NULL,
        tour_description text,
        tour_trigger_type enum('automatic','manual','url_parameter') DEFAULT 'automatic',
        tour_trigger_value varchar(255),
        show_progress tinyint(1) DEFAULT 1,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (tour_id)
    ) $charset_collate;";
    
    // Tour steps table
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    $steps_sql = "CREATE TABLE IF NOT EXISTS $steps_table (
        step_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tour_id bigint(20) UNSIGNED NOT NULL,
        step_order int(11) NOT NULL DEFAULT 0,
        step_title varchar(255) NOT NULL,
        step_content text NOT NULL,
        target_selector varchar(500) NOT NULL,
        step_position enum('modal','top','bottom','left','right','auto') DEFAULT 'auto',
        page_url_pattern varchar(255),
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (step_id),
        KEY tour_id (tour_id),
        KEY step_order (step_order),
        FOREIGN KEY (tour_id) REFERENCES $tours_table(tour_id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // User tracking table
    $tracking_table = $wpdb->prefix . 'avlp_tour_user_tracking';
    $tracking_sql = "CREATE TABLE IF NOT EXISTS $tracking_table (
        tracking_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        vlp_id bigint(20) UNSIGNED NOT NULL,
        tour_id bigint(20) UNSIGNED NOT NULL,
        page_url varchar(500) NOT NULL,
        status enum('not_started','in_progress','completed','skipped_session','skipped_permanent') DEFAULT 'not_started',
        last_step_completed int(11) DEFAULT 0,
        first_viewed datetime DEFAULT CURRENT_TIMESTAMP,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (tracking_id),
        UNIQUE KEY user_tour_page (vlp_id, tour_id, page_url),
        KEY tour_id (tour_id),
        KEY status (status),
        FOREIGN KEY (vlp_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
        FOREIGN KEY (tour_id) REFERENCES $tours_table(tour_id) ON DELETE CASCADE
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($tours_sql);
    dbDelta($steps_sql);
    dbDelta($tracking_sql);
    
    // Log table creation
    if (defined('VLP_VERBOSE_AI_LOGS') && VLP_VERBOSE_AI_LOGS) {
        error_log('VLP Walkthrough: Created database tables - ' . $tours_table . ', ' . $steps_table . ', ' . $tracking_table);
    } else {
        error_log('VLP Walkthrough: Database tables created successfully.');
    }
}

/**
 * Get all active tours
 */
function vlp_walkthrough_get_active_tours() {
    global $wpdb;
    
    $tours_table = $wpdb->prefix . 'avlp_tours';
    
    $tours = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $tours_table WHERE is_active = %d ORDER BY created_at ASC",
            1
        )
    );
    
    return $tours;
}

/**
 * Get active tours for a specific page
 */
function vlp_walkthrough_get_active_tours_for_page($page_url) {
    global $wpdb;
    
    $tours_table = $wpdb->prefix . 'avlp_tours';
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    $tracking_table = $wpdb->prefix . 'avlp_tour_user_tracking';
    
    // Build the query to exclude tours that user has permanently skipped
    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    
    error_log("VLP Walkthrough: Getting active tours for page: $page_url, User: $user_id");
    
    $query = "SELECT DISTINCT t.* FROM $tours_table t
             INNER JOIN $steps_table s ON t.tour_id = s.tour_id
             WHERE t.is_active = %d 
             AND s.is_active = %d
             AND (s.page_url_pattern IS NULL OR s.page_url_pattern = '' OR %s LIKE CONCAT('%%', s.page_url_pattern, '%%'))";
    
    $params = array(1, 1, $page_url);
    
    // If user is logged in, exclude tours they've permanently skipped
    if ($user_id > 0) {
        $query .= " AND t.tour_id NOT IN (
                    SELECT tour_id FROM $tracking_table 
                    WHERE vlp_id = %d AND status = 'skipped_permanent'
                   )";
        $params[] = $user_id;
        error_log("VLP Walkthrough: Excluding permanently skipped tours for user $user_id");
    }
    
    $query .= " ORDER BY t.created_at ASC";
    
    $tours = $wpdb->get_results(
        $wpdb->prepare($query, $params)
    );
    
    error_log("VLP Walkthrough: Found " . count($tours) . " active tours for page $page_url");
    
    return $tours;
}

/**
 * Get tour by ID
 */
function vlp_walkthrough_get_tour($tour_id) {
    global $wpdb;
    
    $tours_table = $wpdb->prefix . 'avlp_tours';
    
    $tour = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tours_table WHERE tour_id = %d",
            $tour_id
        )
    );
    
    return $tour;
}

/**
 * Get tour steps for a specific tour and page
 */
function vlp_walkthrough_get_tour_steps($tour_id, $page_url = null) {
    global $wpdb;
    
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    
    $where_clause = "tour_id = %d AND is_active = %d";
    $params = array($tour_id, 1);
    
    if ($page_url) {
        $where_clause .= " AND (page_url_pattern IS NULL OR page_url_pattern = '' OR %s LIKE CONCAT('%%', page_url_pattern, '%%'))";
        $params[] = $page_url;
    }
    
    $steps = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $steps_table WHERE $where_clause ORDER BY step_order ASC",
            $params
        )
    );
    
    return $steps;
}

/**
 * Get complete tour data for a specific tour and page
 */
function vlp_walkthrough_get_tour_for_page($tour_id, $page_url) {
    $tour = vlp_walkthrough_get_tour($tour_id);
    
    if (!$tour) {
        return false;
    }
    
    $tour->steps = vlp_walkthrough_get_tour_steps($tour_id, $page_url);
    
    return $tour;
}

/**
 * Create a new tour
 */
function vlp_walkthrough_create_tour($tour_data) {
    global $wpdb;
    
    $tours_table = $wpdb->prefix . 'avlp_tours';
    
    $result = $wpdb->insert(
        $tours_table,
        array(
            'tour_name' => sanitize_text_field($tour_data['tour_name']),
            'tour_description' => sanitize_textarea_field($tour_data['tour_description']),
            'tour_trigger_type' => sanitize_text_field($tour_data['tour_trigger_type']),
            'tour_trigger_value' => sanitize_text_field($tour_data['tour_trigger_value']),
            'show_progress' => isset($tour_data['show_progress']) ? intval($tour_data['show_progress']) : 1,
            'is_active' => isset($tour_data['is_active']) ? intval($tour_data['is_active']) : 1
        ),
        array('%s', '%s', '%s', '%s', '%d', '%d')
    );
    
    if ($result === false) {
        return false;
    }
    
    return $wpdb->insert_id;
}

/**
 * Update a tour
 */
function vlp_walkthrough_update_tour($tour_id, $tour_data) {
    global $wpdb;
    
    $tours_table = $wpdb->prefix . 'avlp_tours';
    
    $update_data = array();
    $update_format = array();
    
    if (isset($tour_data['tour_name'])) {
        $update_data['tour_name'] = sanitize_text_field($tour_data['tour_name']);
        $update_format[] = '%s';
    }
    
    if (isset($tour_data['tour_description'])) {
        $update_data['tour_description'] = sanitize_textarea_field($tour_data['tour_description']);
        $update_format[] = '%s';
    }
    
    if (isset($tour_data['tour_trigger_type'])) {
        $update_data['tour_trigger_type'] = sanitize_text_field($tour_data['tour_trigger_type']);
        $update_format[] = '%s';
    }
    
    if (isset($tour_data['tour_trigger_value'])) {
        $update_data['tour_trigger_value'] = sanitize_text_field($tour_data['tour_trigger_value']);
        $update_format[] = '%s';
    }
    
    if (isset($tour_data['show_progress'])) {
        $update_data['show_progress'] = intval($tour_data['show_progress']);
        $update_format[] = '%d';
    }
    
    if (isset($tour_data['is_active'])) {
        $update_data['is_active'] = intval($tour_data['is_active']);
        $update_format[] = '%d';
    }
    
    if (empty($update_data)) {
        return false;
    }
    
    $result = $wpdb->update(
        $tours_table,
        $update_data,
        array('tour_id' => $tour_id),
        $update_format,
        array('%d')
    );
    
    return $result !== false;
}

/**
 * Delete a tour
 */
function vlp_walkthrough_delete_tour($tour_id) {
    global $wpdb;
    
    $tours_table = $wpdb->prefix . 'avlp_tours';
    
    $result = $wpdb->delete(
        $tours_table,
        array('tour_id' => $tour_id),
        array('%d')
    );
    
    return $result !== false;
}

/**
 * Create a new tour step
 */
function vlp_walkthrough_create_tour_step($step_data) {
    global $wpdb;
    
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    
    $result = $wpdb->insert(
        $steps_table,
        array(
            'tour_id' => intval($step_data['tour_id']),
            'step_order' => intval($step_data['step_order']),
            'step_title' => sanitize_text_field($step_data['step_title']),
            'step_content' => wp_kses_post($step_data['step_content']),
            'target_selector' => sanitize_text_field($step_data['target_selector']),
            'step_position' => sanitize_text_field($step_data['step_position']),
            'page_url_pattern' => sanitize_text_field($step_data['page_url_pattern']),
            'is_active' => isset($step_data['is_active']) ? intval($step_data['is_active']) : 1
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d')
    );
    
    if ($result === false) {
        return false;
    }
    
    return $wpdb->insert_id;
}

/**
 * Update a tour step
 */
function vlp_walkthrough_update_tour_step($step_id, $step_data) {
    global $wpdb;
    
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    
    $update_data = array();
    $update_format = array();
    
    if (isset($step_data['step_order'])) {
        $update_data['step_order'] = intval($step_data['step_order']);
        $update_format[] = '%d';
    }
    
    if (isset($step_data['step_title'])) {
        $update_data['step_title'] = sanitize_text_field($step_data['step_title']);
        $update_format[] = '%s';
    }
    
    if (isset($step_data['step_content'])) {
        $update_data['step_content'] = wp_kses_post($step_data['step_content']);
        $update_format[] = '%s';
    }
    
    if (isset($step_data['target_selector'])) {
        $update_data['target_selector'] = sanitize_text_field($step_data['target_selector']);
        $update_format[] = '%s';
    }
    
    if (isset($step_data['step_position'])) {
        $update_data['step_position'] = sanitize_text_field($step_data['step_position']);
        $update_format[] = '%s';
    }
    
    if (isset($step_data['page_url_pattern'])) {
        $update_data['page_url_pattern'] = sanitize_text_field($step_data['page_url_pattern']);
        $update_format[] = '%s';
    }
    
    if (isset($step_data['is_active'])) {
        $update_data['is_active'] = intval($step_data['is_active']);
        $update_format[] = '%d';
    }
    
    if (empty($update_data)) {
        return false;
    }
    
    $result = $wpdb->update(
        $steps_table,
        $update_data,
        array('step_id' => $step_id),
        $update_format,
        array('%d')
    );
    
    return $result !== false;
}

/**
 * Delete a tour step
 */
function vlp_walkthrough_delete_tour_step($step_id) {
    global $wpdb;
    
    $steps_table = $wpdb->prefix . 'avlp_tour_steps';
    
    $result = $wpdb->delete(
        $steps_table,
        array('step_id' => $step_id),
        array('%d')
    );
    
    return $result !== false;
}

/**
 * Track user interaction with tours
 */
function vlp_walkthrough_track_user_interaction($user_id, $tour_id, $action, $page_url, $step_completed = 0) {
    global $wpdb;
    
    $tracking_table = $wpdb->prefix . 'avlp_tour_user_tracking';
    
    // Debug logging
    error_log("VLP Walkthrough: Tracking interaction - User: $user_id, Tour: $tour_id, Action: $action, Page: $page_url");
    
    // Get existing tracking record
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tracking_table WHERE vlp_id = %d AND tour_id = %d AND page_url = %s",
            $user_id, $tour_id, $page_url
        )
    );
    
    if ($existing) {
        // Update existing record
        $update_data = array(
            'status' => $action,
            'last_updated' => current_time('mysql')
        );
        
        if ($action === 'in_progress' && $step_completed > $existing->last_step_completed) {
            $update_data['last_step_completed'] = $step_completed;
        }
        
        $result = $wpdb->update(
            $tracking_table,
            $update_data,
            array('tracking_id' => $existing->tracking_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        error_log("VLP Walkthrough: Updated tracking record - Result: " . ($result !== false ? 'success' : 'failed'));
        return $result !== false;
    } else {
        // Create new tracking record
        $result = $wpdb->insert(
            $tracking_table,
            array(
                'vlp_id' => $user_id,
                'tour_id' => $tour_id,
                'page_url' => $page_url,
                'status' => $action,
                'last_step_completed' => $step_completed
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );
        
        error_log("VLP Walkthrough: Created tracking record - Result: " . ($result !== false ? 'success' : 'failed'));
        return $result !== false;
    }
}

/**
 * Get user tour tracking status
 */
function vlp_walkthrough_get_user_tracking($user_id, $tour_id, $page_url) {
    global $wpdb;
    
    $tracking_table = $wpdb->prefix . 'avlp_tour_user_tracking';
    
    $tracking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tracking_table WHERE vlp_id = %d AND tour_id = %d AND page_url = %s",
            $user_id, $tour_id, $page_url
        )
    );
    
    return $tracking;
}

/**
 * Check if user has disabled tours permanently
 */
function vlp_walkthrough_user_has_disabled_tours($user_id) {
    return get_user_meta($user_id, 'vlp_walkthrough_disabled', true);
}

/**
 * Set user tour disable preference
 */
function vlp_walkthrough_set_user_tour_preference($user_id, $disabled) {
    if ($disabled) {
        update_user_meta($user_id, 'vlp_walkthrough_disabled', true);
    } else {
        delete_user_meta($user_id, 'vlp_walkthrough_disabled');
    }
}

/**
 * Get tour statistics
 */
function vlp_walkthrough_get_tour_stats($tour_id = null) {
    global $wpdb;
    
    $tracking_table = $wpdb->prefix . 'avlp_tour_user_tracking';
    
    $where_clause = '';
    $params = array();
    
    if ($tour_id) {
        $where_clause = 'WHERE tour_id = %d';
        $params[] = $tour_id;
    }
    
    $stats = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                status,
                COUNT(*) as count,
                COUNT(DISTINCT vlp_id) as unique_users
             FROM $tracking_table 
             $where_clause 
             GROUP BY status",
            $params
        )
    );
    
    return $stats;
}
