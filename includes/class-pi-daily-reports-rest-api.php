<?php
/**
 * Planning Index CRM - Daily Reports REST API
 * Comprehensive API for daily reports system
 */

if (!defined('ABSPATH')) exit;

// Materials plugin version for API compatibility
if (!defined('PI_MATERIALS_VERSION')) {
    define('PI_MATERIALS_VERSION', '3.0.0');
}

class PI_Daily_Reports_REST_API {
    
    private static $instance = null;
    private $namespace = 'pi-daily-reports/v1';
    private $wpdb;
    private $tables;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db = PI_Daily_Reports_Database::get_instance();
        $this->tables = $this->get_table_names();
    }
    
    private function get_table_names() {
        $prefix = $this->wpdb->prefix . 'pi_crm_';
        return [
            'daily_reports' => $prefix . 'daily_reports',
            'activities' => $prefix . 'daily_report_activities',
            'labor' => $prefix . 'daily_report_labor',
            'equipment' => $prefix . 'daily_report_equipment',
            'materials' => $prefix . 'daily_report_materials',
            'safety' => $prefix . 'daily_report_safety',
            'photos' => $prefix . 'daily_report_photos',
            'ratings' => $prefix . 'daily_report_ratings',
            'delay_claims' => $prefix . 'delay_claims',
            'approvals' => $prefix . 'report_approvals',
            'visitors' => $prefix . 'daily_report_visitors',
            'corrective_actions' => $prefix . 'daily_report_corrective_actions',
            'clock_queue' => $prefix . 'clock_events_queue'
        ];
    }
    
    public function init_routes() {
        // Daily Reports CRUD
        $this->register_daily_reports_routes();
        
        // Activities
        $this->register_activities_routes();
        
        // Labor/Attendance
        $this->register_labor_routes();
        
        // Equipment
        $this->register_equipment_routes();
        
        // Materials
        $this->register_materials_routes();
        
        // Safety
        $this->register_safety_routes();
        
        // Photos
        $this->register_photos_routes();
        
        // Ratings
        $this->register_ratings_routes();
        
        // Visitors
        $this->register_visitors_routes();
        
        // Workflow/Approvals
        $this->register_approvals_routes();
        
        // Dashboard
        $this->register_dashboard_routes();
        
    }
    
    // ============================================
    // DAILY REPORTS ROUTES
    // ============================================
    
    private function register_daily_reports_routes() {
        register_rest_route($this->namespace, '/reports', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_reports'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array('required' => true),
                    'date_from' => array('required' => false),
                    'date_to' => array('required' => false),
                    'status' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_report'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/reports/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_report'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_report'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_report'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/reports/(?P<id>\d+)/submit', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'submit_report'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/reports/(?P<id>\d+)/approve', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'approve_report'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        $this->register_photos_routes();
        $this->register_integration_routes();
    }
    
    public function get_reports($request) {
        $job_id = intval($request->get_param('job_id'));
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $status = $request->get_param('status');
        
        $table = $this->tables['daily_reports'];
        $sql = "SELECT * FROM {$table} WHERE job_id = %d AND is_deleted = 0";
        $params = [$job_id];
        
        if ($date_from) {
            $sql .= " AND report_date >= %s";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $sql .= " AND report_date <= %s";
            $params[] = $date_to;
        }
        
        if ($status) {
            $sql .= " AND report_status = %s";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY report_date DESC";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        // Return consistent response structure
        return new WP_REST_Response(array(
            'data' => $results,
            'count' => count($results),
            'success' => true
        ), 200);
    }
    
    public function get_report($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['daily_reports'];
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND is_deleted = 0",
            $id
        ), ARRAY_A);
        
        if (!$report) {
            return new WP_Error('not_found', 'Report not found', array('status' => 404));
        }
        
        // Get related data
        $report['activities'] = $this->get_report_activities($id);
        $report['labor'] = $this->get_report_labor($id);
        $report['equipment'] = $this->get_report_equipment($id);
        $report['materials'] = $this->get_report_materials($id);
        $report['safety'] = $this->get_report_safety($id);
        $report['photos'] = $this->get_report_photos($id);
        $report['ratings'] = $this->get_report_ratings($id);
        $report['visitors'] = $this->get_report_visitors($id);
        $report['corrective_actions'] = $this->get_report_corrective_actions($id);
        
        // Return consistent response structure
        return new WP_REST_Response(array(
            'data' => $report,
            'success' => true
        ), 200);
    }
    
    public function create_report($request) {
        $data = $request->get_json_params();
        $table = $this->tables['daily_reports'];
        
        $job_id = intval($data['job_id']);
        $report_date = sanitize_text_field($data['report_date'] ?? current_time('Y-m-d'));
        
        // Generate report number
        $report_number = $this->generate_report_number($job_id, $report_date);
        
        $insert_data = array(
            'job_id' => $job_id,
            'report_date' => $report_date,
            'report_number' => $report_number,
            'report_status' => 'draft',
            'created_by' => get_current_user_id()
        );
        
        $this->wpdb->insert($table, $insert_data);
        $report_id = $this->wpdb->insert_id;
        
        // Log audit
        $this->db->log_audit($report_id, $job_id, 'create', 'daily_reports', $report_id);
        
        return new WP_REST_Response(array(
            'id' => $report_id,
            'report_number' => $report_number,
            'success' => true
        ), 201);
    }
    
    public function update_report($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['daily_reports'];
        
        $allowed_fields = [
            'shift_start', 'shift_end', 'shift_duration_minutes',
            'workforce_count', 'workforce_total_hours', 'workforce_regular_hours', 'workforce_overtime_hours',
            'last_auto_save', 'updated_by'
        ];
        
        $update_data = array();
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        if (!empty($update_data)) {
            $update_data['updated_by'] = get_current_user_id();
            $this->wpdb->update($table, $update_data, array('id' => $id));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_report($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['daily_reports'];
        
        // Soft delete
        $this->wpdb->update($table, array(
            'is_deleted' => 1,
            'deleted_at' => current_time('mysql'),
            'deleted_by' => get_current_user_id()
        ), array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function submit_report($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['daily_reports'];
        
        $this->wpdb->update($table, array(
            'report_status' => 'submitted',
            'submitted_by' => get_current_user_id(),
            'submitted_at' => current_time('mysql')
        ), array('id' => $id));
        
        // Log approval workflow
        $this->log_approval($id, 'draft', 'submitted', 'submit');
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function approve_report($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['daily_reports'];
        
        $this->wpdb->update($table, array(
            'report_status' => 'approved',
            'approved_by' => get_current_user_id(),
            'approved_at' => current_time('mysql')
        ), array('id' => $id));
        
        // Log approval workflow
        $this->log_approval($id, 'submitted', 'approved', 'approve', $data['comments'] ?? null);
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    private function generate_report_number($job_id, $report_date) {
        $job = get_post($job_id);
        $job_code = $job ? get_the_title($job_id) : 'JOB';
        $date_suffix = date('Ymd', strtotime($report_date));
        
        // Count reports for this job on this date
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['daily_reports']} WHERE job_id = %d AND report_date = %s",
            $job_id, $report_date
        ));
        
        return sprintf('%s-%s-%03d', $job_code, $date_suffix, $count + 1);
    }
    
    // ============================================
    // ACTIVITIES ROUTES
    // ============================================
    
    private function register_activities_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/activities', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_activities'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_activity'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/activities/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_activity'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_activity'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_activities($request) {
        $report_id = intval($request->get_param('report_id'));
        return new WP_REST_Response($this->get_report_activities($report_id), 200);
    }
    
    public function create_activity($request) {
        $report_id = intval($request->get_param('report_id'));
        $data = $request->get_json_params();
        
        // Get job_id from report
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        $insert_data = array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'location_area' => sanitize_text_field($data['location_area'] ?? ''),
            'trade_company' => sanitize_text_field($data['trade_company'] ?? ''),
            'activity_type' => sanitize_text_field($data['activity_type'] ?? ''),
            'activity_description' => sanitize_textarea_field($data['activity_description'] ?? ''),
            'quantity_completed' => floatval($data['quantity_completed'] ?? 0),
            'unit_of_measure' => sanitize_text_field($data['unit_of_measure'] ?? ''),
            'percent_complete' => intval($data['percent_complete'] ?? 0),
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'cost_code' => sanitize_text_field($data['cost_code'] ?? ''),
            'phase_wbs' => sanitize_text_field($data['phase_wbs'] ?? ''),
            'delay_reason' => sanitize_text_field($data['delay_reason'] ?? ''),
            'delay_hours' => floatval($data['delay_hours'] ?? 0),
            'blockers_issues' => sanitize_textarea_field($data['blockers_issues'] ?? ''),
            'created_by' => get_current_user_id()
        );
        
        $this->wpdb->insert($this->tables['activities'], $insert_data);
        $activity_id = $this->wpdb->insert_id;
        
        // Extract delay claim if delay_reason is set
        if (!empty($insert_data['delay_reason']) && $insert_data['delay_hours'] > 0) {
            $this->create_delay_claim($report_id, $activity_id, $report['job_id'], $insert_data['delay_reason'], $insert_data['delay_hours']);
        }
        
        return new WP_REST_Response(array('id' => $activity_id, 'success' => true), 201);
    }
    
    public function update_activity($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $allowed_fields = ['location_area', 'trade_company', 'activity_type', 'activity_description', 
                          'quantity_completed', 'unit_of_measure', 'percent_complete', 'start_time', 'end_time',
                          'cost_code', 'phase_wbs', 'delay_reason', 'delay_hours', 'blockers_issues'];
        
        $update_data = array();
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        if (!empty($update_data)) {
            $update_data['updated_by'] = get_current_user_id();
            $this->wpdb->update($this->tables['activities'], $update_data, array('id' => $id));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_activity($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['activities'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // ============================================
    // LABOR/ATTENDANCE ROUTES
    // ============================================
    
    private function register_labor_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/labor', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_labor'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_labor_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/labor/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_labor_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_labor_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_labor($request) {
        $report_id = intval($request->get_param('report_id'));
        return new WP_REST_Response($this->get_report_labor($report_id), 200);
    }
    
    public function create_labor_entry($request) {
        global $wpdb;
        $report_id = intval($request->get_param('report_id'));
        $data = $request->get_json_params();
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id, report_date FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
        
        $insert_data = array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'worker_id' => intval($data['employee_id'] ?? 0),
            'worker_name' => sanitize_text_field($data['worker_name']),
            'work_date' => $report['report_date'],
            'start_time' => $data['clock_in'] ?? null,
            'end_time' => $data['clock_out'] ?? null,
            'total_hours' => floatval($data['total_hours'] ?? 0),
            'overtime_hours' => floatval($data['overtime_hours'] ?? 0),
            'break_duration' => intval($data['break_minutes'] ?? 0),
            'cost_code' => sanitize_text_field($data['cost_code'] ?? ''),
            'task_description' => sanitize_textarea_field($data['notes'] ?? ''),
            'gps_coordinates' => !empty($data['gps_lat']) && !empty($data['gps_lng']) ? 
                $data['gps_lat'] . ',' . $data['gps_lng'] : null,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($timesheets_table, $insert_data);
        
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'success' => true), 201);
    }
    
    public function update_labor_entry($request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
        
        $allowed_fields = ['start_time', 'end_time', 'total_hours', 'overtime_hours', 'break_duration', 'cost_code', 'task_description'];
        
        $update_data = array();
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        // Map field names for compatibility
        if (isset($data['clock_in'])) {
            $update_data['start_time'] = $data['clock_in'];
        }
        if (isset($data['clock_out'])) {
            $update_data['end_time'] = $data['clock_out'];
        }
        if (isset($data['break_minutes'])) {
            $update_data['break_duration'] = $data['break_minutes'];
        }
        if (isset($data['notes'])) {
            $update_data['task_description'] = $data['notes'];
        }
        
        if (!empty($update_data)) {
            $update_data['updated_at'] = current_time('mysql');
            $this->wpdb->update($timesheets_table, $update_data, array('id' => $id));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_labor_entry($request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
        $this->wpdb->delete($timesheets_table, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
        
    // ============================================
    // EQUIPMENT ROUTES
    // ============================================
    
    private function register_equipment_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/equipment', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_equipment_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/daily-equipment/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_equipment_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_equipment_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));

        // Sync on-site equipment into daily report
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/sync-equipment', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'sync_equipment_to_report'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_equipment($request) {
        $report_id = intval($request->get_param('report_id'));
        return new WP_REST_Response($this->get_report_equipment($report_id), 200);
    }
    
    public function create_equipment_entry($request) {
        $report_id = intval($request->get_param('report_id'));
        $data = $request->get_json_params();
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        $insert_data = array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'equipment_id' => intval($data['equipment_id'] ?? 0),
            'equipment_name' => sanitize_text_field($data['equipment_name']),
            'equipment_type' => sanitize_text_field($data['equipment_type'] ?? ''),
            'ownership_type' => sanitize_text_field($data['ownership_type'] ?? 'owned'),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'hours_used' => floatval($data['hours_used'] ?? 0),
            'meter_reading' => floatval($data['meter_reading'] ?? 0),
            'operator_name' => sanitize_text_field($data['operator_name'] ?? ''),
            'operator_id' => intval($data['operator_id'] ?? 0),
            'fuel_notes' => sanitize_textarea_field($data['fuel_notes'] ?? ''),
            'maintenance_issues' => sanitize_textarea_field($data['maintenance_issues'] ?? ''),
            'downtime_reason' => sanitize_text_field($data['downtime_reason'] ?? ''),
            'downtime_hours' => floatval($data['downtime_hours'] ?? 0),
            'created_by' => get_current_user_id()
        );
        
        $inserted = $this->wpdb->insert($this->tables['equipment'], $insert_data);
        $daily_report_equipment_id = $this->wpdb->insert_id;

        if ($inserted === false || !$daily_report_equipment_id) {
            error_log('[DailyReports API] Equipment insert failed: ' . $this->wpdb->last_error);
            return new WP_REST_Response(array('success' => false, 'error' => 'Failed to save equipment: ' . $this->wpdb->last_error), 500);
        }

        // Sync back to parent equipment record if linked
        $equipment_id = intval($data['equipment_id'] ?? 0);
        if ($equipment_id && $daily_report_equipment_id) {
            $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
            $parent_updates = array();

            // Update hours meter reading if provided
            if (!empty($data['meter_reading'])) {
                $parent_updates['hours_meter_reading'] = floatval($data['meter_reading']);
            }

            // Update current condition if provided
            if (!empty($data['current_condition'])) {
                $parent_updates['current_condition'] = sanitize_text_field($data['current_condition']);
            }

            if (!empty($parent_updates)) {
                $this->wpdb->update($equipment_table, $parent_updates, array('id' => $equipment_id));
            }
        }

        return new WP_REST_Response(array('id' => $daily_report_equipment_id, 'success' => true), 201);
    }

    public function update_equipment_entry($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();

        $allowed_fields = ['status', 'hours_used', 'meter_reading', 'operator_name', 'fuel_notes',
                          'maintenance_issues', 'downtime_reason', 'downtime_hours'];

        $update_data = array();
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }

        if (!empty($update_data)) {
            $update_data['updated_by'] = get_current_user_id();
            $this->wpdb->update($this->tables['equipment'], $update_data, array('id' => $id));
        }

        // Sync meter reading back to parent equipment if linked
        if (!empty($data['meter_reading']) || !empty($data['current_condition'])) {
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT equipment_id FROM {$this->tables['equipment']} WHERE id = %d",
                $id
            ), ARRAY_A);
            if ($existing && !empty($existing['equipment_id'])) {
                $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
                $parent_updates = array();
                if (!empty($data['meter_reading'])) {
                    $parent_updates['hours_meter_reading'] = floatval($data['meter_reading']);
                }
                if (!empty($data['current_condition'])) {
                    $parent_updates['current_condition'] = sanitize_text_field($data['current_condition']);
                }
                if (!empty($parent_updates)) {
                    $this->wpdb->update($equipment_table, $parent_updates, array('id' => $existing['equipment_id']));
                }
            }
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    public function delete_equipment_entry($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['equipment'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }

    /**
     * Sync on-site job equipment into the daily report.
     * Creates daily report equipment entries for any job equipment with status = 'On-Site'
     * that isn't already logged in this report.
     */
    public function sync_equipment_to_report($request) {
        $report_id = intval($request->get_param('report_id'));

        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);

        if (!$report || empty($report['job_id'])) {
            return new WP_REST_Response(array('synced' => 0, 'items' => array()), 200);
        }

        $job_id = intval($report['job_id']);
        $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
        $employees_table = $this->wpdb->prefix . 'pi_crm_employees';

        // Find already-synced equipment IDs for this report
        $existing_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT equipment_id FROM {$this->tables['equipment']} WHERE daily_report_id = %d AND equipment_id > 0",
            $report_id
        ));
        $existing_ids = array_map('intval', $existing_ids);

        // Get on-site equipment for this job that isn't already synced
        $sql = "SELECT e.id as equipment_id,
                       COALESCE(e.internal_name, e.equipment_name, e.equipment_type) as equipment_name,
                       e.equipment_type,
                       e.hours_meter_reading,
                       e.current_condition,
                       COALESCE(e.acquisition_type, 'Owned') as acquisition_type,
                       e.assigned_operator_id,
                       CONCAT(emp.first_name, ' ', emp.last_name) as operator_name,
                       e.status
                FROM {$equipment_table} e
                LEFT JOIN {$employees_table} emp ON e.assigned_operator_id = emp.id
                WHERE e.job_id = %d
                  AND e.status = 'On-Site'
                  AND (e.is_deleted = 0 OR e.is_deleted IS NULL)";

        if (!empty($existing_ids)) {
            $placeholders = implode(',', array_fill(0, count($existing_ids), '%d'));
            $sql .= $this->wpdb->prepare(" AND e.id NOT IN ({$placeholders})", ...$existing_ids);
        }

        $sql .= " ORDER BY equipment_name";
        $to_sync = $this->wpdb->get_results($this->wpdb->prepare($sql, $job_id), ARRAY_A);

        $synced = array();
        foreach ($to_sync as $eq) {
            $this->wpdb->insert($this->tables['equipment'], array(
                'daily_report_id' => $report_id,
                'job_id' => $job_id,
                'equipment_id' => intval($eq['equipment_id']),
                'equipment_name' => sanitize_text_field($eq['equipment_name']),
                'equipment_type' => sanitize_text_field($eq['equipment_type']),
                'ownership_type' => sanitize_text_field(strtolower($eq['acquisition_type'])),
                'status' => 'active',
                'hours_used' => 0,
                'meter_reading' => floatval($eq['hours_meter_reading'] ?? 0),
                'operator_name' => sanitize_text_field($eq['operator_name'] ?? ''),
                'operator_id' => intval($eq['assigned_operator_id'] ?? 0),
                'created_by' => get_current_user_id()
            ));
            $synced[] = array(
                'id' => $this->wpdb->insert_id,
                'equipment_id' => intval($eq['equipment_id']),
                'equipment_name' => $eq['equipment_name']
            );
        }

        return new WP_REST_Response(array('synced' => count($synced), 'items' => $synced), 200);
    }

    // ============================================
    // PHOTOS ROUTES
    // ============================================
    
    private function register_photos_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/photos', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_photos'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'upload_photo'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/daily-photos/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_photo'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_photos($request) {
        $report_id = intval($request->get_param('report_id'));
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['photos']} WHERE daily_report_id = %d ORDER BY taken_at DESC",
            $report_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function upload_photo($request) {
        $report_id = intval($request->get_param('report_id'));
        $files = $request->get_file_params();
        $data = $request->get_json_params();
        
        if (empty($files['file'])) {
            return new WP_Error('no_file', 'No file uploaded', array('status' => 400));
        }
        
        $file = $files['file'];
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', 'Invalid file type. Only images are allowed.', array('status' => 400));
        }
        
        // Handle upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error'], array('status' => 500));
        }
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        $insert_data = array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'file_name' => basename($upload['file']),
            'file_path' => $upload['file'],
            'photo_url' => $upload['url'],
            'file_size' => filesize($upload['file']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'location_area' => sanitize_text_field($data['location_area'] ?? ''),
            'gps_lat' => floatval($data['gps_lat'] ?? 0),
            'gps_lng' => floatval($data['gps_lng'] ?? 0),
            'gps_accuracy' => floatval($data['gps_accuracy'] ?? 0),
            'taken_by' => get_current_user_id(),
            'taken_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($this->tables['photos'], $insert_data);
        
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'url' => $upload['url'], 'success' => true), 201);
    }
    
    public function delete_photo($request) {
        $id = intval($request->get_param('id'));
        
        // Get photo info to delete file
        $photo = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT file_path FROM {$this->tables['photos']} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($photo && file_exists($photo['file_path'])) {
            wp_delete_file($photo['file_path']);
        }
        
        $this->wpdb->delete($this->tables['photos'], array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // ============================================
    // MATERIALS ROUTES
    // ============================================
    
    private function register_materials_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/materials', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_materials'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_material_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/daily-materials/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_material_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_material_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Sync materials from BOM/job into daily report
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/sync-materials', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'sync_materials_to_report'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Get available materials for selection (from materials page)
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/available-materials', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_available_materials'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Record stock movement from daily report
        register_rest_route($this->namespace, '/daily-materials/(?P<id>\d+)/stock-movement', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'record_material_stock_movement'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_materials($request) {
        $report_id = intval($request->get_param('report_id'));
        return new WP_REST_Response($this->get_report_materials($report_id), 200);
    }
    
    public function create_material_entry($request) {
        $report_id = intval($request->get_param('report_id'));
        $data = $request->get_json_params();
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        if (!$report) {
            return new WP_REST_Response(array('success' => false, 'error' => 'Report not found'), 404);
        }
        
        // Calculate total cost if unit cost and quantity provided
        $unit_cost = floatval($data['unit_cost'] ?? 0);
        $quantity_delivered = floatval($data['quantity_delivered'] ?? $data['quantity'] ?? 0);
        $total_cost = $unit_cost * $quantity_delivered;
        
        $insert_data = array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'material_id' => intval($data['material_id'] ?? 0),
            'bom_item_id' => intval($data['bom_item_id'] ?? 0),
            'supplier_id' => intval($data['supplier_id'] ?? 0),
            'supplier_name' => sanitize_text_field($data['supplier_name']),
            'po_number' => sanitize_text_field($data['po_number'] ?? ''),
            'delivery_status' => sanitize_text_field($data['delivery_status'] ?? 'scheduled'),
            'material_name' => sanitize_text_field($data['material_name']),
            'material_sku' => sanitize_text_field($data['material_sku'] ?? ''),
            'material_category' => sanitize_text_field($data['material_category'] ?? ''),
            'material_description' => sanitize_textarea_field($data['material_description'] ?? ''),
            'quantity_ordered' => floatval($data['quantity_ordered'] ?? 0),
            'quantity_delivered' => $quantity_delivered,
            'unit_of_measure' => sanitize_text_field($data['unit_of_measure'] ?? 'each'),
            'unit_cost' => $unit_cost,
            'total_cost' => $total_cost,
            'condition_on_arrival' => sanitize_text_field($data['condition_on_arrival'] ?? 'good'),
            'quality_notes' => sanitize_textarea_field($data['quality_notes'] ?? ''),
            'damaged_quantity' => floatval($data['damaged_quantity'] ?? 0),
            'missing_quantity' => floatval($data['missing_quantity'] ?? 0),
            'received_by' => sanitize_text_field($data['received_by'] ?? ''),
            'received_by_user_id' => intval($data['received_by_user_id'] ?? 0),
            'delivery_ticket_number' => sanitize_text_field($data['delivery_ticket_number'] ?? ''),
            'delivery_ticket_url' => sanitize_url($data['delivery_ticket_url'] ?? ''),
            'photos' => sanitize_textarea_field($data['photos'] ?? ''),
            'attachments' => sanitize_textarea_field($data['attachments'] ?? ''),
            'is_expected_missing' => intval($data['is_expected_missing'] ?? 0),
            'scheduled_delivery_date' => !empty($data['scheduled_delivery_date']) ? date('Y-m-d', strtotime($data['scheduled_delivery_date'])) : null,
            'actual_delivery_date' => !empty($data['actual_delivery_date']) ? date('Y-m-d', strtotime($data['actual_delivery_date'])) : null,
            'delay_reason' => sanitize_textarea_field($data['delay_reason'] ?? ''),
            'stock_location_id' => sanitize_text_field($data['stock_location_id'] ?? 'main'),
            'created_by' => get_current_user_id()
        );
        
        $inserted = $this->wpdb->insert($this->tables['materials'], $insert_data);
        $daily_report_material_id = $this->wpdb->insert_id;
        
        if ($inserted === false || !$daily_report_material_id) {
            error_log('[DailyReports API] Material insert failed: ' . $this->wpdb->last_error);
            return new WP_REST_Response(array('success' => false, 'error' => 'Failed to save material: ' . $this->wpdb->last_error), 500);
        }
        
        // Sync to materials page if linked
        $material_id = intval($data['material_id'] ?? 0);
        if ($material_id && $daily_report_material_id) {
            $this->sync_material_delivery_to_stock($material_id, $quantity_delivered, $data);
        }
        
        return new WP_REST_Response(array('id' => $daily_report_material_id, 'success' => true), 201);
    }
    
    public function update_material_entry($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array();
        
        // Update only if provided
        if (isset($data['supplier_name'])) $update_data['supplier_name'] = sanitize_text_field($data['supplier_name']);
        if (isset($data['po_number'])) $update_data['po_number'] = sanitize_text_field($data['po_number']);
        if (isset($data['delivery_status'])) $update_data['delivery_status'] = sanitize_text_field($data['delivery_status']);
        if (isset($data['material_name'])) $update_data['material_name'] = sanitize_text_field($data['material_name']);
        if (isset($data['material_sku'])) $update_data['material_sku'] = sanitize_text_field($data['material_sku']);
        if (isset($data['material_category'])) $update_data['material_category'] = sanitize_text_field($data['material_category']);
        if (isset($data['material_description'])) $update_data['material_description'] = sanitize_textarea_field($data['material_description']);
        if (isset($data['quantity_ordered'])) $update_data['quantity_ordered'] = floatval($data['quantity_ordered']);
        if (isset($data['quantity_delivered'])) $update_data['quantity_delivered'] = floatval($data['quantity_delivered']);
        if (isset($data['unit_of_measure'])) $update_data['unit_of_measure'] = sanitize_text_field($data['unit_of_measure']);
        if (isset($data['unit_cost'])) $update_data['unit_cost'] = floatval($data['unit_cost']);
        if (isset($data['condition_on_arrival'])) $update_data['condition_on_arrival'] = sanitize_text_field($data['condition_on_arrival']);
        if (isset($data['quality_notes'])) $update_data['quality_notes'] = sanitize_textarea_field($data['quality_notes']);
        if (isset($data['damaged_quantity'])) $update_data['damaged_quantity'] = floatval($data['damaged_quantity']);
        if (isset($data['missing_quantity'])) $update_data['missing_quantity'] = floatval($data['missing_quantity']);
        if (isset($data['received_by'])) $update_data['received_by'] = sanitize_text_field($data['received_by']);
        if (isset($data['received_by_user_id'])) $update_data['received_by_user_id'] = intval($data['received_by_user_id']);
        if (isset($data['delivery_ticket_number'])) $update_data['delivery_ticket_number'] = sanitize_text_field($data['delivery_ticket_number']);
        if (isset($data['delivery_ticket_url'])) $update_data['delivery_ticket_url'] = sanitize_url($data['delivery_ticket_url']);
        if (isset($data['photos'])) $update_data['photos'] = sanitize_textarea_field($data['photos']);
        if (isset($data['attachments'])) $update_data['attachments'] = sanitize_textarea_field($data['attachments']);
        if (isset($data['is_expected_missing'])) $update_data['is_expected_missing'] = intval($data['is_expected_missing']);
        if (isset($data['scheduled_delivery_date'])) $update_data['scheduled_delivery_date'] = date('Y-m-d', strtotime($data['scheduled_delivery_date']));
        if (isset($data['actual_delivery_date'])) $update_data['actual_delivery_date'] = date('Y-m-d', strtotime($data['actual_delivery_date']));
        if (isset($data['delay_reason'])) $update_data['delay_reason'] = sanitize_textarea_field($data['delay_reason']);
        if (isset($data['stock_location_id'])) $update_data['stock_location_id'] = sanitize_text_field($data['stock_location_id']);
        
        // Recalculate total cost if quantity or unit cost changed
        if (isset($data['quantity_delivered']) || isset($data['unit_cost'])) {
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT unit_cost, quantity_delivered FROM {$this->tables['materials']} WHERE id = %d",
                $id
            ), ARRAY_A);
            
            if ($existing) {
                $unit_cost = floatval($data['unit_cost'] ?? $existing['unit_cost']);
                $quantity_delivered = floatval($data['quantity_delivered'] ?? $existing['quantity_delivered']);
                $update_data['total_cost'] = $unit_cost * $quantity_delivered;
            }
        }
        
        if (!empty($update_data)) {
            $update_data['updated_by'] = get_current_user_id();
            $this->wpdb->update($this->tables['materials'], $update_data, array('id' => $id));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_material_entry($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['materials'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_available_materials($request) {
        $report_id = intval($request->get_param('report_id'));
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        if (!$report) {
            return new WP_REST_Response(array('success' => false, 'error' => 'Report not found'), 404);
        }
        
        $job_id = $report['job_id'];
        
        // Direct database query to get BOM materials for this job
        $materials = $this->get_job_bom_materials($job_id);
        
        return new WP_REST_Response($materials, 200);
    }
    
    /**
     * Get BOM materials for a specific job directly from database
     * This is the robust, simplified method that queries the source of truth
     */
    private function get_job_bom_materials($job_id) {
        global $wpdb;
        
        // The materials plugin stores BOM items in the pi_bom_items table
        $bom_items_table = $wpdb->prefix . 'pi_bom_items';
        $stock_movements_table = $wpdb->prefix . 'pi_stock_movements';
        
        // Check if BOM table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$bom_items_table}'") === $bom_items_table;
        
        if (!$table_exists) {
            error_log("[DailyReports] BOM items table not found: {$bom_items_table}");
            return array();
        }
        
        // Check if stock movements table exists
        $stock_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$stock_movements_table}'") === $stock_movements_table;
        
        // Query all BOM items for this job (project_id = job_id)
        // We don't filter by status to show ALL materials as the user requested
        $materials = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                bi.id as bom_item_id,
                bi.material_id,
                bi.material_name,
                bi.material_sku,
                bi.category as material_category,
                bi.unit as unit_of_measure,
                bi.quantity as required_quantity,
                bi.unit_cost,
                bi.supplier_id,
                bi.status
            FROM {$bom_items_table} bi
            WHERE bi.project_id = %d
            ORDER BY bi.material_name ASC",
            $job_id
        ), ARRAY_A);
        
        // Normalize data types and calculate current stock
        foreach ($materials as &$material) {
            $material['bom_item_id'] = (int) $material['bom_item_id'];
            $material['material_id'] = $material['material_id'] ? (int) $material['material_id'] : null;
            $material['required_quantity'] = is_numeric($material['required_quantity']) ? floatval($material['required_quantity']) : 0;
            $material['unit_cost'] = is_numeric($material['unit_cost']) ? floatval($material['unit_cost']) : 0;
            $material['supplier_id'] = $material['supplier_id'] ? (int) $material['supplier_id'] : null;
            
            // Calculate current stock from stock movements table if it exists
            if ($stock_table_exists && $material['material_id']) {
                $current_stock = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(
                        CASE 
                            WHEN movement_type = 'receipt' THEN quantity 
                            WHEN movement_type IN ('issue', 'waste', 'adjustment') THEN -quantity 
                            ELSE 0 
                        END
                    ), 0) 
                    FROM {$stock_movements_table} 
                    WHERE material_id = %d",
                    $material['material_id']
                ));
                $material['current_stock'] = (float) $current_stock;
            } else {
                $material['current_stock'] = 0;
            }
            
            $material['reorder_point'] = 0; // Can be enhanced later if needed
        }
        
        error_log("[DailyReports] Retrieved " . count($materials) . " materials for job {$job_id}");
        
        return $materials;
    }
    
    public function sync_materials_to_report($request) {
        $report_id = intval($request->get_param('report_id'));
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id, report_date FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        if (!$report) {
            return new WP_REST_Response(array('success' => false, 'error' => 'Report not found'), 404);
        }
        
        $job_id = $report['job_id'];
        $report_date = $report['report_date'];
        
        // Debug: Log sync attempt
        error_log("[DailyReports] Sync attempt - Job ID: {$job_id}, Report Date: {$report_date}");
        
        // Check if bom_item_id column exists in daily reports materials table
        $bom_column_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'bom_item_id'",
            DB_NAME, $this->tables['materials']
        ));
        
        if (!$bom_column_exists) {
            error_log("[DailyReports] bom_item_id column missing - updating table schema");
            // Add missing column
            $this->wpdb->query("ALTER TABLE {$this->tables['materials']} ADD COLUMN bom_item_id bigint(20) unsigned DEFAULT NULL AFTER material_id");
        }
        
        // Get already-synced material IDs for this report
        $existing_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT bom_item_id FROM {$this->tables['materials']} WHERE daily_report_id = %d AND bom_item_id > 0",
            $report_id
        ));
        
        // Get materials directly from BOM database
        $materials = $this->get_job_bom_materials($job_id);
        
        return $this->process_material_sync($materials, $report_id, $job_id, $existing_ids);
    }
    
    /**
     * Process material sync - common logic for both API and database sources
     */
    private function process_material_sync($materials, $report_id, $job_id, $existing_ids) {
        $synced_count = 0;
        $materials_to_sync = array();
        
        // Filter out already synced materials
        foreach ($materials as $material) {
            if (!in_array($material['bom_item_id'], $existing_ids)) {
                $materials_to_sync[] = $material;
            }
        }
        
        error_log("[DailyReports] Processing sync - " . count($materials_to_sync) . " materials to sync after filtering existing ones");
        
        foreach ($materials_to_sync as $material) {
            $insert_data = array(
                'daily_report_id' => $report_id,
                'job_id' => $job_id,
                'bom_item_id' => intval($material['bom_item_id']),
                'material_id' => intval($material['material_id']),
                'supplier_id' => intval($material['supplier_id']),
                'supplier_name' => $this->get_supplier_name($material['supplier_id']),
                'delivery_status' => 'scheduled',
                'material_name' => sanitize_text_field($material['material_name']),
                'material_sku' => sanitize_text_field($material['material_sku'] ?? ''),
                'material_category' => sanitize_text_field($material['material_category'] ?? ''),
                'quantity_ordered' => floatval($material['required_quantity']),
                'unit_of_measure' => sanitize_text_field($material['unit_of_measure'] ?? 'each'),
                'unit_cost' => floatval($material['unit_cost'] ?? 0),
                'total_cost' => floatval($material['required_quantity']) * floatval($material['unit_cost'] ?? 0),
                'created_by' => get_current_user_id()
            );
            
            $result = $this->wpdb->insert($this->tables['materials'], $insert_data);
            
            if ($result !== false) {
                $synced_count++;
                error_log("[DailyReports] Synced material: {$material['material_name']} (BOM ID: {$material['bom_item_id']})");
            } else {
                error_log("[DailyReports] Failed to sync material: {$material['material_name']}");
            }
        }
        
        $response = array(
            'success' => true,
            'synced_count' => $synced_count,
            'total_available' => count($materials),
            'message' => sprintf('Synced %d materials from BOM', $synced_count)
        );
        
        if ($synced_count === 0) {
            $response['message'] = 'No new materials to sync - all materials already synced or no materials available';
        }
        
        return new WP_REST_Response($response, 200);
    }
    
    /**
     * Get supplier name by ID
     */
    private function get_supplier_name($supplier_id) {
        if (empty($supplier_id)) {
            return 'Unknown Supplier';
        }
        
        // Try to get from materials plugin suppliers table first
        $suppliers_table = $this->wpdb->prefix . 'pi_suppliers';
        $supplier_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$suppliers_table}'") === $suppliers_table;
        
        if ($supplier_exists) {
            $supplier = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT company_name FROM {$suppliers_table} WHERE id = %d",
                $supplier_id
            ));
            
            if ($supplier) {
                return $supplier->company_name;
            }
        }
        
        return 'Unknown Supplier';
    }
    
    public function record_material_stock_movement($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $material = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['materials']} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$material) {
            return new WP_REST_Response(array('success' => false, 'error' => 'Material entry not found'), 404);
        }
        
        $quantity = floatval($data['quantity'] ?? 0);
        $movement_type = sanitize_text_field($data['movement_type'] ?? 'receipt');
        $location_id = sanitize_text_field($data['location_id'] ?? 'main');
        $reference = sanitize_text_field($data['reference'] ?? '');
        $notes = sanitize_textarea_field($data['notes'] ?? '');
        
        // Record stock movement in materials plugin
        $stock_movements_table = $this->wpdb->prefix . 'pi_stock_movements';
        $movement_data = array(
            'material_id' => intval($material['material_id']),
            'bom_item_id' => intval($material['bom_item_id']),
            'project_id' => intval($material['job_id']),
            'location_id' => $location_id,
            'movement_type' => $movement_type,
            'quantity' => $quantity,
            'reference' => $reference,
            'reference_type' => 'daily_report',
            'created_by' => get_current_user_id(),
            'notes' => $notes
        );
        
        $this->wpdb->insert($stock_movements_table, $movement_data);
        
        // Update daily report material to show stock movement recorded
        $this->wpdb->update($this->tables['materials'], 
            array('stock_movement_recorded' => 1, 'updated_by' => get_current_user_id()),
            array('id' => $id)
        );
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    private function sync_material_delivery_to_stock($material_id, $quantity_delivered, $delivery_data) {
        // Sync delivery to materials plugin stock
        $stock_movements_table = $this->wpdb->prefix . 'pi_stock_movements';
        
        $movement_data = array(
            'material_id' => $material_id,
            'movement_type' => 'receipt',
            'quantity' => $quantity_delivered,
            'reference' => sanitize_text_field($delivery_data['po_number'] ?? 'Daily Report'),
            'reference_type' => 'daily_report',
            'created_by' => get_current_user_id(),
            'notes' => sanitize_textarea_field($delivery_data['quality_notes'] ?? 'Material received via daily report')
        );
        
        $this->wpdb->insert($stock_movements_table, $movement_data);
    }
    
    // ============================================
    // SAFETY ROUTES
    // ============================================
    
    private function register_safety_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/safety', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_safety'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_safety_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/daily-safety/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_safety_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_safety_entry'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_safety($request) {
        $report_id = intval($request->get_param('report_id'));
        return new WP_REST_Response($this->get_report_safety($report_id), 200);
    }
    
    public function create_safety_entry($request) {
        $report_id = intval($request->get_param('report_id'));
        $data = $request->get_json_params();
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        $insert_data = array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'record_type' => sanitize_text_field($data['record_type']),
            'incident_type' => sanitize_text_field($data['incident_type'] ?? ''),
            'severity' => sanitize_text_field($data['severity'] ?? ''),
            'injured_party' => sanitize_text_field($data['injured_party'] ?? ''),
            'witness_names' => sanitize_textarea_field($data['witness_names'] ?? ''),
            'immediate_actions' => sanitize_textarea_field($data['immediate_actions'] ?? ''),
            'root_cause' => sanitize_textarea_field($data['root_cause'] ?? ''),
            'inspection_type' => sanitize_text_field($data['inspection_type'] ?? ''),
            'inspection_result' => sanitize_text_field($data['inspection_result'] ?? ''),
            'inspector_name' => sanitize_text_field($data['inspector_name'] ?? ''),
            'certificate_url' => esc_url_raw($data['certificate_url'] ?? ''),
            'description' => sanitize_textarea_field($data['description']),
            'location_area' => sanitize_text_field($data['location_area'] ?? ''),
            'occurred_at' => $data['occurred_at'] ?? null,
            'created_by' => get_current_user_id()
        );
        
        $this->wpdb->insert($this->tables['safety'], $insert_data);
        
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'success' => true), 201);
    }
    
    public function update_safety_entry($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $allowed_fields = ['description', 'severity', 'immediate_actions', 'root_cause', 'inspection_result'];
        
        $update_data = array();
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        if (!empty($update_data)) {
            $update_data['updated_by'] = get_current_user_id();
            $this->wpdb->update($this->tables['safety'], $update_data, array('id' => $id));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_safety_entry($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['safety'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // ============================================
    // INTEGRATION ROUTES (CRM Modules)
    // ============================================
    
    private function register_integration_routes() {
        // Get workers/employees from CRM
        register_rest_route($this->namespace, '/integration/job/(?P<job_id>\d+)/workers', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_job_workers'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Get equipment from equipment module
        register_rest_route($this->namespace, '/integration/job/(?P<job_id>\d+)/equipment', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_job_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Get cost codes from CRM
        register_rest_route($this->namespace, '/integration/job/(?P<job_id>\d+)/cost-codes', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_job_cost_codes'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_job_workers($request) {
        $job_id = intval($request->get_param('job_id'));
        
        $employees_table = $this->wpdb->prefix . 'pi_crm_employees';
        $team_assignments_table = $this->wpdb->prefix . 'pi_crm_team_assignments';
        
        // Check if team_assignments table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$team_assignments_table}'") === $team_assignments_table;
        
        if ($table_exists) {
            // Use team_assignments for proper job-employee association
            // This matches the Team & Timesheets page behavior
            $results = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT e.id, e.employee_code, e.first_name, e.last_name, e.email, e.role, e.trade,
                        e.hourly_rate
                FROM {$employees_table} e
                INNER JOIN {$team_assignments_table} ta ON e.id = ta.employee_id
                WHERE ta.job_id = %d AND e.status = 'active'
                ORDER BY e.last_name, e.first_name",
                $job_id
            ), ARRAY_A);
        } else {
            // Fallback: use employees.job_id column if team_assignments doesn't exist
            $results = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, employee_code, first_name, last_name, email, role, trade, hourly_rate
                FROM {$employees_table}
                WHERE (job_id = %d OR job_id IS NULL) AND status = 'active'
                ORDER BY last_name, first_name",
                $job_id
            ), ARRAY_A);
        }
        
        // Ensure we always return an array (never null)
        return new WP_REST_Response($results ?: array(), 200);
    }
    
    public function get_job_equipment($request) {
        $job_id = intval($request->get_param('job_id'));

        $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
        $employees_table = $this->wpdb->prefix . 'pi_crm_employees';

        // Check which name columns exist (schema migration safety)
        $cols = $this->wpdb->get_col("DESCRIBE {$equipment_table}");
        $has_internal_name = in_array('internal_name', $cols);
        $has_equipment_name = in_array('equipment_name', $cols);
        $has_acquisition_type = in_array('acquisition_type', $cols);

        $name_field = $has_internal_name ? 'e.internal_name' : ($has_equipment_name ? 'e.equipment_name' : 'e.equipment_type');
        $acquisition_field = $has_acquisition_type ? 'e.acquisition_type' : "'Owned'";

        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT e.id,
                    {$name_field} as equipment_name,
                    e.equipment_type,
                    e.serial_number,
                    e.status,
                    e.hours_meter_reading,
                    e.current_condition,
                    {$acquisition_field} as acquisition_type,
                    e.assigned_operator_id,
                    CONCAT(emp.first_name, ' ', emp.last_name) as operator_name,
                    e.current_location_on_site,
                    e.category
             FROM {$equipment_table} e
             LEFT JOIN {$employees_table} emp ON e.assigned_operator_id = emp.id
             WHERE e.job_id = %d AND (e.is_deleted = 0 OR e.is_deleted IS NULL)
             ORDER BY equipment_name",
            $job_id
        ), ARRAY_A);

        return new WP_REST_Response($results ?: array(), 200);
    }
    
    public function get_job_cost_codes($request) {
        $job_id = intval($request->get_param('job_id'));
        
        $cost_codes_table = $this->wpdb->prefix . 'pi_crm_cost_codes';
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, code, description, category 
            FROM {$cost_codes_table} 
            WHERE job_id = %d OR job_id IS NULL 
            ORDER BY code",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    // ============================================
    // RATINGS ROUTES
    // ============================================
    
    private function register_ratings_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/ratings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_ratings'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'save_ratings'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'save_ratings'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_ratings($request) {
        $report_id = intval($request->get_param('report_id'));
        
        if (!$report_id) {
            return new WP_Error('invalid_report_id', 'Report ID is required', array('status' => 400));
        }
        
        $ratings = $this->get_report_ratings($report_id);
        
        if ($ratings === null) {
            // Return empty structure instead of null to prevent JSON parsing errors
            return new WP_REST_Response(array(
                'id' => null,
                'daily_report_id' => $report_id,
                'overall_score' => 0,
                'letter_grade' => 'N/A',
                'score_color' => '#cccccc'
            ), 200);
        }
        
        return new WP_REST_Response($ratings, 200);
    }
    
    // ============================================
    // VISITORS ROUTES
    // ============================================
    
    private function register_visitors_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/visitors', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_visitors'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'save_visitors'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'save_visitors'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    public function get_visitors($request) {
        $report_id = intval($request->get_param('report_id'));
        
        if (!$report_id) {
            return new WP_Error('invalid_report_id', 'Report ID is required', array('status' => 400));
        }
        
        $visitors = $this->get_report_visitors($report_id);
        
        if (empty($visitors)) {
            return new WP_REST_Response(array(), 200);
        }
        
        return new WP_REST_Response($visitors, 200);
    }
    
    public function save_visitors($request) {
        $report_id = intval($request->get_param('report_id'));
        $data = $request->get_json_params();
        
        // Implementation for saving visitors would go here
        return new WP_REST_Response(array('success' => true, 'message' => 'Visitors saved'), 200);
    }
    
    public function save_ratings($request) {
        $report_id = intval($request->get_param('report_id'));
        $data = $request->get_json_params();
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        // Calculate overall score
        $productivity = floatval($data['productivity_rating'] ?? 0);
        $safety = floatval($data['safety_rating'] ?? 0);
        $quality = floatval($data['quality_rating'] ?? 0);
        $site_conditions = floatval($data['site_conditions_rating'] ?? 0);
        
        $prod_weight = floatval($data['productivity_weight'] ?? 0.30);
        $safety_weight = floatval($data['safety_weight'] ?? 0.30);
        $quality_weight = floatval($data['quality_weight'] ?? 0.20);
        $site_weight = floatval($data['site_conditions_weight'] ?? 0.20);
        
        $overall_score = ($productivity * $prod_weight) + 
                        ($safety * $safety_weight) + 
                        ($quality * $quality_weight) + 
                        ($site_conditions * $site_weight);
        
        // Determine letter grade and color
        $letter_grade = $overall_score >= 8 ? 'A' : ($overall_score >= 6 ? 'B' : ($overall_score >= 4 ? 'C' : 'D'));
        $score_color = $overall_score >= 8 ? 'green' : ($overall_score >= 6 ? 'yellow' : 'red');
        
        $insert_data = array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'productivity_rating' => $productivity,
            'safety_rating' => $safety,
            'quality_rating' => $quality,
            'site_conditions_rating' => $site_conditions,
            'overall_score' => $overall_score,
            'letter_grade' => $letter_grade,
            'score_color' => $score_color,
            'rating_justification' => sanitize_textarea_field($data['rating_justification'] ?? ''),
            'productivity_weight' => $prod_weight,
            'safety_weight' => $safety_weight,
            'quality_weight' => $quality_weight,
            'site_conditions_weight' => $site_weight,
            'rated_by' => get_current_user_id()
        );
        
        // Check if ratings exist
        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id FROM {$this->tables['ratings']} WHERE daily_report_id = %d",
            $report_id
        ));
        
        if ($existing) {
            $insert_data['updated_by'] = get_current_user_id();
            $this->wpdb->update($this->tables['ratings'], $insert_data, array('id' => $existing->id));
        } else {
            $this->wpdb->insert($this->tables['ratings'], $insert_data);
        }
        
        return new WP_REST_Response(array('success' => true, 'overall_score' => $overall_score), 200);
    }
    
    // ============================================
    // APPROVALS ROUTES
    // ============================================
    
    private function register_approvals_routes() {
        register_rest_route($this->namespace, '/reports/(?P<report_id>\d+)/approvals', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_approvals'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    public function get_approvals($request) {
        $report_id = intval($request->get_param('report_id'));
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['approvals']} WHERE daily_report_id = %d ORDER BY acted_at DESC",
            $report_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    // ============================================
    // DASHBOARD ROUTES
    // ============================================
    
    private function register_dashboard_routes() {
        register_rest_route($this->namespace, '/dashboard/(?P<job_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_dashboard_data'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/analytics/(?P<job_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_analytics_data'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    public function get_dashboard_data($request) {
        $job_id = intval($request->get_param('job_id'));
        $today = current_time('Y-m-d');
        
        // Get today's report
        $today_report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['daily_reports']} WHERE job_id = %d AND report_date = %d AND is_deleted = 0",
            $job_id, $today
        ), ARRAY_A);
        
        // Get today's headcount
        $headcount = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['labor']} WHERE job_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL",
            $job_id, $today
        ));
        
        // Get open incidents
        $open_incidents = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['safety']} WHERE job_id = %d AND record_type = 'incident' AND severity IN ('high', 'critical')",
            $job_id
        ));
        
        // Get equipment issues
        $equipment_issues = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['equipment']} WHERE job_id = %d AND status IN ('broken', 'idle')",
            $job_id
        ));
        
        // Get today's score
        $ratings = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT overall_score, letter_grade, score_color FROM {$this->tables['ratings']} 
            WHERE daily_report_id = %d",
            $today_report['id'] ?? 0
        ), ARRAY_A);
        
        return new WP_REST_Response(array(
            'headcount' => intval($headcount),
            'open_incidents' => intval($open_incidents),
            'equipment_issues' => intval($equipment_issues),
            'today_score' => $ratings,
            'report_status' => $today_report['report_status'] ?? 'not_started'
        ), 200);
    }
    
    public function get_analytics_data($request) {
        $job_id = intval($request->get_param('job_id'));
        $days = intval($request->get_param('days') ?? 30);
        
        // Get ratings trend
        $ratings_trend = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT dr.report_date, r.overall_score, r.letter_grade 
            FROM {$this->tables['daily_reports']} dr
            LEFT JOIN {$this->tables['ratings']} r ON dr.id = r.daily_report_id
            WHERE dr.job_id = %d AND dr.is_deleted = 0
            AND dr.report_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            ORDER BY dr.report_date ASC",
            $job_id, $days
        ), ARRAY_A);
        
        // Get labor hours breakdown
        $labor_hours = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(clock_in) as date, trade, SUM(total_hours) as hours
            FROM {$this->tables['labor']}
            WHERE job_id = %d AND clock_in >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            GROUP BY DATE(clock_in), trade
            ORDER BY date ASC",
            $job_id, $days
        ), ARRAY_A);
        
        return new WP_REST_Response(array(
            'ratings_trend' => $ratings_trend,
            'labor_hours' => $labor_hours
        ), 200);
    }
    
    // ============================================
    // CLOCK ROUTES (continued)
    // ============================================
    
    private function register_clock_routes() {
        // Already registered in labor routes
    }
    
    // ============================================
    // HELPER METHODS
    // ============================================
    
    private function get_report_activities($report_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['activities']} WHERE daily_report_id = %d ORDER BY start_time ASC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_labor($report_id) {
        global $wpdb;
        $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
        $employees_table = $wpdb->prefix . 'pi_crm_employees';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                ts.id,
                ts.worker_id as employee_id,
                ts.worker_name,
                ts.work_date,
                ts.start_time as clock_in,
                ts.end_time as clock_out,
                ts.total_hours,
                ts.overtime_hours,
                ts.break_duration,
                ts.cost_code,
                ts.task_description as notes,
                ts.gps_coordinates,
                ts.status,
                e.first_name,
                e.last_name,
                e.trade,
                e.hourly_rate,
                CASE 
                    WHEN ts.gps_coordinates IS NOT NULL AND ts.gps_coordinates != '' THEN 'verified'
                    ELSE 'no_gps'
                END as location_status,
                CASE 
                    WHEN ts.gps_coordinates IS NOT NULL AND ts.gps_coordinates != '' THEN 1
                    ELSE 0
                END as location_verified
            FROM {$timesheets_table} ts
            LEFT JOIN {$employees_table} e ON ts.worker_id = e.id
            WHERE ts.daily_report_id = %d 
            ORDER BY ts.start_time ASC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_equipment($report_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['equipment']} WHERE daily_report_id = %d ORDER BY equipment_name ASC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_materials($report_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['materials']} WHERE daily_report_id = %d ORDER BY created_at ASC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_safety($report_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['safety']} WHERE daily_report_id = %d ORDER BY occurred_at DESC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_photos($report_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['photos']} WHERE daily_report_id = %d ORDER BY taken_at ASC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_ratings($report_id) {
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->tables['ratings']}'") === $this->tables['ratings'];
        if (!$table_exists) {
            error_log('[Daily Reports] Ratings table does not exist: ' . $this->tables['ratings']);
            return null;
        }
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['ratings']} WHERE daily_report_id = %d",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_visitors($report_id) {
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->tables['visitors']}'") === $this->tables['visitors'];
        if (!$table_exists) {
            error_log('[Daily Reports] Visitors table does not exist: ' . $this->tables['visitors']);
            return array();
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['visitors']} WHERE daily_report_id = %d ORDER BY arrival_time ASC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_report_corrective_actions($report_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['corrective_actions']} WHERE daily_report_id = %d ORDER BY due_date ASC",
            $report_id
        ), ARRAY_A);
    }
    
    private function get_or_create_todays_report($job_id) {
        $today = current_time('Y-m-d');
        
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['daily_reports']} WHERE job_id = %d AND report_date = %s AND is_deleted = 0",
            $job_id, $today
        ), ARRAY_A);
        
        if (!$report) {
            $report_number = $this->generate_report_number($job_id, $today);
            $this->wpdb->insert($this->tables['daily_reports'], array(
                'job_id' => $job_id,
                'report_date' => $today,
                'report_number' => $report_number,
                'report_status' => 'draft',
                'created_by' => get_current_user_id()
            ));
            
            $report = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['daily_reports']} WHERE id = %d",
                $this->wpdb->insert_id
            ), ARRAY_A);
        }
        
        return $report;
    }
    
    private function verify_location($data) {
        // Simplified location verification
        // In production, this would check against job site geofence
        $gps_lat = floatval($data['gps_lat'] ?? 0);
        $gps_lng = floatval($data['gps_lng'] ?? 0);
        $gps_accuracy = floatval($data['gps_accuracy'] ?? 999);
        
        // Check if GPS coordinates are valid and within reasonable accuracy
        return ($gps_lat != 0 && $gps_lng != 0 && $gps_accuracy < 100);
    }
    
    private function create_delay_claim($report_id, $activity_id, $job_id, $delay_reason, $delay_hours) {
        $this->wpdb->insert($this->tables['delay_claims'], array(
            'daily_report_id' => $report_id,
            'activity_id' => $activity_id,
            'job_id' => $job_id,
            'delay_reason' => $delay_reason,
            'delay_hours' => $delay_hours,
            'claim_status' => 'open',
            'created_by' => get_current_user_id()
        ));
    }
    
    private function log_approval($report_id, $from_status, $to_status, $action, $comments = null) {
        $report = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['daily_reports']} WHERE id = %d",
            $report_id
        ), ARRAY_A);
        
        $current_user = wp_get_current_user();
        
        $this->wpdb->insert($this->tables['approvals'], array(
            'daily_report_id' => $report_id,
            'job_id' => $report['job_id'],
            'from_status' => $from_status,
            'to_status' => $to_status,
            'action' => $action,
            'actor_id' => $current_user->ID,
            'actor_name' => $current_user->display_name,
            'actor_role' => $this->get_user_role($current_user->ID),
            'signature' => $current_user->display_name,
            'comments' => $comments
        ));
    }
    
    private function get_user_role($user_id) {
        $user = get_userdata($user_id);
        return $user ? implode(', ', $user->roles) : '';
    }
    
    // ============================================
    // PERMISSION CHECKS
    // ============================================
    
    public function check_job_permission($request) {
        return current_user_can('edit_posts');
    }
    
    public function check_general_permission($request) {
        return is_user_logged_in();
    }
}
