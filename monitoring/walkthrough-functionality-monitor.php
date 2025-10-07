<?php
/**
 * Production monitoring for AVLP Walkthrough Tour plugin
 * Monitors plugin health, performance, and data integrity
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VLPWalkthroughMonitor {
    
    private $alerts = [];
    private $performance_thresholds = [
        'query_time' => 0.1,      // 100ms
        'memory_usage' => 50,     // 50MB
        'error_rate' => 0.05      // 5%
    ];
    
    /**
     * Run comprehensive health check
     */
    public function run_health_check() {
        $this->alerts = [];
        
        // Database checks
        $this->check_database_tables();
        $this->check_data_integrity();
        
        // Performance checks
        $this->check_performance();
        $this->check_memory_usage();
        
        // Configuration checks
        $this->check_plugin_configuration();
        
        // Usage analytics
        $this->check_usage_analytics();
        
        return $this->alerts;
    }
    
    /**
     * Check database tables exist and are properly structured
     */
    private function check_database_tables() {
        global $wpdb;
        
        $required_tables = [
            $wpdb->prefix . 'avlp_tours',
            $wpdb->prefix . 'avlp_tour_steps',
            $wpdb->prefix . 'avlp_tour_user_tracking'
        ];
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                $this->add_alert('critical', "Database table missing: $table");
            }
        }
        
        // Check table structure
        if (in_array($wpdb->prefix . 'avlp_tours', $required_tables)) {
            $columns = $wpdb->get_results("DESCRIBE {$wpdb->prefix}avlp_tours");
            $required_columns = ['tour_id', 'tour_name', 'tour_description', 'is_active'];
            
            foreach ($required_columns as $column) {
                $found = false;
                foreach ($columns as $col) {
                    if ($col->Field === $column) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $this->add_alert('critical', "Missing column in tours table: $column");
                }
            }
        }
    }
    
    /**
     * Check data integrity
     */
    private function check_data_integrity() {
        global $wpdb;
        
        // Check for orphaned steps
        $orphaned_steps = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}avlp_tour_steps s 
             LEFT JOIN {$wpdb->prefix}avlp_tours t ON s.tour_id = t.tour_id 
             WHERE t.tour_id IS NULL"
        );
        
        if ($orphaned_steps > 0) {
            $this->add_alert('warning', "Found $orphaned_steps orphaned tour steps");
        }
        
        // Check for invalid step orders
        $invalid_orders = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}avlp_tour_steps 
             WHERE step_order <= 0 OR step_order IS NULL"
        );
        
        if ($invalid_orders > 0) {
            $this->add_alert('warning', "Found $invalid_orders steps with invalid order values");
        }
        
        // Check for duplicate step orders within same tour
        $duplicate_orders = $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT tour_id, step_order, COUNT(*) as count 
                FROM {$wpdb->prefix}avlp_tour_steps 
                WHERE is_active = 1 
                GROUP BY tour_id, step_order 
                HAVING count > 1
            ) as duplicates"
        );
        
        if ($duplicate_orders > 0) {
            $this->add_alert('warning', "Found $duplicate_orders duplicate step orders");
        }
    }
    
    /**
     * Check query performance
     */
    private function check_performance() {
        $start_time = microtime(true);
        
        // Test common queries
        vlp_walkthrough_get_active_tours();
        vlp_walkthrough_get_tour_stats();
        
        $execution_time = microtime(true) - $start_time;
        
        if ($execution_time > $this->performance_thresholds['query_time']) {
            $this->add_alert('warning', "Slow query performance: {$execution_time}s (threshold: {$this->performance_thresholds['query_time']}s)");
        }
        
        // Check database query count
        $query_count = get_num_queries();
        if ($query_count > 20) {
            $this->add_alert('warning', "High query count: $query_count queries executed");
        }
    }
    
    /**
     * Check memory usage
     */
    private function check_memory_usage() {
        $memory_usage = memory_get_peak_usage(true) / 1024 / 1024; // Convert to MB
        
        if ($memory_usage > $this->performance_thresholds['memory_usage']) {
            $this->add_alert('warning', "High memory usage: {$memory_usage}MB (threshold: {$this->performance_thresholds['memory_usage']}MB)");
        }
    }
    
    /**
     * Check plugin configuration
     */
    private function check_plugin_configuration() {
        // Check if plugin is active
        if (!is_plugin_active('avlp-walkthrough-tour/default-walkthrough.php')) {
            $this->add_alert('critical', 'Plugin is not active');
        }
        
        // Check required options
        $required_options = [
            'vlp_walkthrough_enabled',
            'vlp_walkthrough_auto_trigger',
            'vlp_walkthrough_url_trigger'
        ];
        
        foreach ($required_options as $option) {
            if (get_option($option) === false) {
                $this->add_alert('warning', "Missing configuration option: $option");
            }
        }
        
        // Check for conflicting plugins
        $conflicting_plugins = [
            'simple-tour-guide/simple-tour-guide.php',
            'dp-intro-tours/dp-intro-tours.php'
        ];
        
        foreach ($conflicting_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                $this->add_alert('warning', "Potential conflict with plugin: $plugin");
            }
        }
    }
    
    /**
     * Check usage analytics
     */
    private function check_usage_analytics() {
        global $wpdb;
        
        // Check tour usage
        $active_tours = vlp_walkthrough_get_active_tours();
        if (empty($active_tours)) {
            $this->add_alert('info', 'No active tours found');
        }
        
        // Check user engagement
        $total_interactions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}avlp_tour_user_tracking"
        );
        
        if ($total_interactions == 0) {
            $this->add_alert('info', 'No user interactions recorded yet');
        }
        
        // Check completion rates
        $stats = vlp_walkthrough_get_tour_stats();
        $total_completed = 0;
        $total_interactions = 0;
        
        foreach ($stats as $stat) {
            $total_interactions += intval($stat->count);
            if ($stat->status === 'completed') {
                $total_completed = intval($stat->count);
            }
        }
        
        if ($total_interactions > 0) {
            $completion_rate = ($total_completed / $total_interactions) * 100;
            
            if ($completion_rate < 20) {
                $this->add_alert('warning', "Low completion rate: {$completion_rate}%");
            } elseif ($completion_rate > 80) {
                $this->add_alert('info', "High completion rate: {$completion_rate}%");
            }
        }
    }
    
    /**
     * Get system information
     */
    public function get_system_info() {
        global $wpdb;
        
        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'plugin_version' => VLP_WALKTHROUGH_VERSION,
            'active_theme' => get_template(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
    }
    
    /**
     * Get performance metrics
     */
    public function get_performance_metrics() {
        $start_time = microtime(true);
        
        // Test various operations
        $tours = vlp_walkthrough_get_active_tours();
        $stats = vlp_walkthrough_get_tour_stats();
        
        $execution_time = microtime(true) - $start_time;
        
        return [
            'query_execution_time' => $execution_time,
            'memory_peak_usage' => memory_get_peak_usage(true),
            'query_count' => get_num_queries(),
            'active_tours_count' => count($tours),
            'total_interactions' => array_sum(array_column($stats, 'count'))
        ];
    }
    
    /**
     * Generate health report
     */
    public function generate_health_report() {
        $alerts = $this->run_health_check();
        $system_info = $this->get_system_info();
        $performance = $this->get_performance_metrics();
        
        $report = [
            'timestamp' => current_time('mysql'),
            'status' => $this->get_overall_status($alerts),
            'alerts' => $alerts,
            'system_info' => $system_info,
            'performance' => $performance,
            'recommendations' => $this->get_recommendations($alerts)
        ];
        
        return $report;
    }
    
    /**
     * Determine overall health status
     */
    private function get_overall_status($alerts) {
        $critical_count = 0;
        $warning_count = 0;
        
        foreach ($alerts as $alert) {
            if ($alert['level'] === 'critical') {
                $critical_count++;
            } elseif ($alert['level'] === 'warning') {
                $warning_count++;
            }
        }
        
        if ($critical_count > 0) {
            return 'critical';
        } elseif ($warning_count > 0) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }
    
    /**
     * Get recommendations based on alerts
     */
    private function get_recommendations($alerts) {
        $recommendations = [];
        
        foreach ($alerts as $alert) {
            switch ($alert['message']) {
                case strpos($alert['message'], 'Database table missing') !== false:
                    $recommendations[] = 'Run plugin activation to recreate missing database tables';
                    break;
                case strpos($alert['message'], 'Slow query performance') !== false:
                    $recommendations[] = 'Consider optimizing database queries or adding indexes';
                    break;
                case strpos($alert['message'], 'High memory usage') !== false:
                    $recommendations[] = 'Increase PHP memory limit or optimize plugin code';
                    break;
                case strpos($alert['message'], 'Potential conflict') !== false:
                    $recommendations[] = 'Disable conflicting plugins or resolve compatibility issues';
                    break;
            }
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Add alert to the alerts array
     */
    private function add_alert($level, $message) {
        $this->alerts[] = [
            'level' => $level,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Log health check results
     */
    public function log_health_check() {
        $report = $this->generate_health_report();
        
        if ($report['status'] !== 'healthy') {
            error_log('VLP Walkthrough Health Check: ' . json_encode($report));
        }
        
        // Store in transient for admin dashboard
        set_transient('vlp_walkthrough_health_report', $report, HOUR_IN_SECONDS);
        
        return $report;
    }
    
    /**
     * Send alerts if critical issues found
     */
    public function send_alerts_if_needed() {
        $alerts = $this->run_health_check();
        $critical_alerts = array_filter($alerts, function($alert) {
            return $alert['level'] === 'critical';
        });
        
        if (!empty($critical_alerts)) {
            $this->send_alert_email($critical_alerts);
        }
    }
    
    /**
     * Send alert email
     */
    private function send_alert_email($alerts) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = "VLP Walkthrough Plugin - Critical Issues Detected";
        $message = "Critical issues have been detected with the VLP Walkthrough plugin on $site_name:\n\n";
        
        foreach ($alerts as $alert) {
            $message .= "- " . $alert['message'] . "\n";
        }
        
        $message .= "\nPlease check the plugin immediately.\n";
        $message .= "Admin URL: " . admin_url('admin.php?page=avlp-walkthrough') . "\n";
        
        wp_mail($admin_email, $subject, $message);
    }
}

/**
 * Initialize monitoring
 */
function vlp_walkthrough_init_monitoring() {
    $monitor = new VLPWalkthroughMonitor();
    
    // Run health check daily
    if (!wp_next_scheduled('vlp_walkthrough_health_check')) {
        wp_schedule_event(time(), 'daily', 'vlp_walkthrough_health_check');
    }
    
    // Run immediate health check on plugin activation
    if (get_transient('vlp_walkthrough_health_check_needed')) {
        $monitor->log_health_check();
        delete_transient('vlp_walkthrough_health_check_needed');
    }
}

/**
 * Scheduled health check
 */
function vlp_walkthrough_daily_health_check() {
    $monitor = new VLPWalkthroughMonitor();
    $monitor->log_health_check();
    $monitor->send_alerts_if_needed();
}

// Initialize monitoring on plugin load
add_action('plugins_loaded', 'vlp_walkthrough_init_monitoring');

// Hook into scheduled event
add_action('vlp_walkthrough_health_check', 'vlp_walkthrough_daily_health_check');
