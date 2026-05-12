<?php
/**
 * Planning Index CRM - Equipment REST API
 * Comprehensive API endpoints for equipment management
 */

if (!defined('ABSPATH')) exit;

class PI_Equipment_REST_API {
    
    private static $instance = null;
    private $namespace = 'pi-crm/v1';
    private $wpdb;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Force migration to run immediately on class instantiation
        $this->force_migration();
        
        // Also hook to admin_init as backup
        add_action('admin_init', array($this, 'force_migration'));
    }
    
    public function force_migration() {
        global $wpdb;
        
        // Prevent multiple migrations in the same request
        static $migration_run = false;
        if ($migration_run) {
            return;
        }
        $migration_run = true;
        
        // Check if migration already completed recently (within last 5 minutes)
        $last_migration = get_option('pi_equipment_last_migration', 0);
        if (time() - $last_migration < 300) {
            return; // Skip migration if run within last 5 minutes
        }
        
        error_log('[Equipment API] Force migration starting...');
        
        // Run standard migration with error handling
        try {
            PI_Equipment_Database::get_instance()->create_tables();
            PI_Equipment_Database::get_instance()->migrate_existing_table();
        } catch (Exception $e) {
            error_log('[Equipment API] Migration error: ' . $e->getMessage());
        }
        
        // Double-check and add internal_name column if it doesn't exist
        $table_name = $wpdb->prefix . 'pi_crm_equipment';
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table_name, 'internal_name'
        ));
        
        if (!$column_exists) {
            error_log('[Equipment API] internal_name column missing, adding...');
            $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN internal_name varchar(200) DEFAULT '' AFTER equipment_type");
            if ($result === false) {
                error_log('[Equipment API] ERROR: Failed to add internal_name column: ' . $wpdb->last_error);
            } else {
                error_log('[Equipment API] SUCCESS: Added internal_name column');
            }
        } else {
            error_log('[Equipment API] internal_name column exists');
        }
        
        // Migrate existing data: copy equipment_name to internal_name where internal_name is empty
        $migrated = $wpdb->query(
            "UPDATE {$table_name} 
            SET internal_name = equipment_name 
            WHERE (internal_name = '' OR internal_name IS NULL) 
            AND equipment_name IS NOT NULL 
            AND equipment_name != ''"
        );
        
        error_log('[Equipment API] Migrated ' . $migrated . ' records from equipment_name to internal_name');
        
        // Update migration timestamp to prevent re-running
        update_option('pi_equipment_last_migration', time());
        
        error_log('[Equipment API] Force migration complete');
    }
    
    public function register_routes() {
        add_action('rest_api_init', array($this, 'init_routes'));
    }
    
    public function init_routes() {
        // Equipment CRUD
        register_rest_route($this->namespace, '/equipment', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_equipment'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array('required' => true, 'validate_callback' => function($param) { return is_numeric($param); }),
                    'status' => array('required' => false),
                    'category' => array('required' => false),
                    'acquisition_type' => array('required' => false),
                    'operator_id' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Single equipment
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_single_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Equipment timeline
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/timeline', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_equipment_timeline'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Equipment inspections
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/inspections', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_equipment_inspections'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_inspection'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Equipment documents
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/documents', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_equipment_documents'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'upload_document'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Check-in / Check-out
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/check-in', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'check_in_equipment'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/check-out', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'check_out_equipment'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Report issue
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/report-issue', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'report_issue'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Request extension
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/request-extension', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'request_extension'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Equipment summary/stats
        register_rest_route($this->namespace, '/equipment/summary', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_equipment_summary'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array(
                'job_id' => array('required' => true, 'validate_callback' => function($param) { return is_numeric($param); })
            )
        ));
        
        // Equipment requests (approval workflow)
        register_rest_route($this->namespace, '/equipment-requests', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_equipment_requests'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_equipment_request'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/equipment-requests/(?P<id>\d+)/approve', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'approve_equipment_request'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/equipment-requests/(?P<id>\d+)/reject', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'reject_equipment_request'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Global equipment inventory
        register_rest_route($this->namespace, '/equipment-inventory', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_equipment_inventory'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        // Available equipment for allocation
        register_rest_route($this->namespace, '/equipment-available', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_available_equipment'),
            'permission_callback' => array($this, 'check_general_permission'),
            'args' => array(
                'date_from' => array('required' => false),
                'date_to' => array('required' => false)
            )
        ));
    }
    
    public function check_job_permission($request) {
        return is_user_logged_in();
    }
    
    public function check_general_permission() {
        return is_user_logged_in();
    }
    
    // ============================================
    // API METHODS - Equipment CRUD
    // ============================================
    
    public function get_equipment($request) {
        $job_id = intval($request->get_param('job_id'));
        $status = $request->get_param('status');
        $category = $request->get_param('category');
        $acquisition_type = $request->get_param('acquisition_type');
        $operator_id = $request->get_param('operator_id');
        
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if (!$table_exists) {
            return new WP_REST_Response([], 200);
        }
        
        // Get existing columns to handle old schema
        $existing_columns = $this->wpdb->get_col("DESCRIBE {$table}");
        $has_is_deleted = in_array('is_deleted', $existing_columns);
        $has_internal_name = in_array('internal_name', $existing_columns);
        $has_acquisition_type = in_array('acquisition_type', $existing_columns);
        $has_category = in_array('category', $existing_columns);
        $has_status = in_array('status', $existing_columns);
        $has_assigned_operator = in_array('assigned_operator_id', $existing_columns);
        $has_supervisor = in_array('supervisor_responsible_id', $existing_columns);
        $has_equipment_name = in_array('equipment_name', $existing_columns);
        $has_ownership_type = in_array('ownership_type', $existing_columns);
        $has_make = in_array('make', $existing_columns);
        $has_manufacturer = in_array('manufacturer', $existing_columns);
        $has_hire_rate = in_array('hire_rate', $existing_columns);
        $has_daily_rate = in_array('daily_rate', $existing_columns);
        $has_cost_to_job = in_array('cost_to_job', $existing_columns);
        $has_photos = in_array('photos', $existing_columns);
        $has_specifications = in_array('specifications', $existing_columns);
        
        // Build where clause
        $where_clause = $has_is_deleted ? "AND e.is_deleted = 0" : "";
        
        $sql = "SELECT e.*, 
            op.first_name as operator_first_name, 
            op.last_name as operator_last_name,
            sup.first_name as supervisor_first_name,
            sup.last_name as supervisor_last_name
            FROM {$table} e
            LEFT JOIN {$this->wpdb->prefix}pi_crm_employees op ON " . ($has_assigned_operator ? "e.assigned_operator_id = op.id" : "0") . "
            LEFT JOIN {$this->wpdb->prefix}pi_crm_employees sup ON " . ($has_supervisor ? "e.supervisor_responsible_id = sup.id" : "0") . "
            WHERE e.job_id = %d {$where_clause}";
        
        $params = array($job_id);
        
        if ($status && $has_status) {
            $sql .= " AND e.status = %s";
            $params[] = $status;
        }
        
        if ($category && $has_category) {
            $sql .= " AND e.category = %s";
            $params[] = $category;
        }
        
        if ($acquisition_type && $has_acquisition_type) {
            $sql .= " AND e.acquisition_type = %s";
            $params[] = $acquisition_type;
        }
        
        if ($operator_id && $has_assigned_operator) {
            $sql .= " AND e.assigned_operator_id = %d";
            $params[] = intval($operator_id);
        }
        
        // Order by appropriate name field
        $order_field = $has_internal_name ? 'e.internal_name' : ($has_equipment_name ? 'e.equipment_name' : 'e.id');
        $sql .= " ORDER BY {$order_field} ASC";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        // Parse JSON fields and provide defaults for missing data
        foreach ($results as &$item) {
            // Handle photos and specifications
            if ($has_photos && $item['photos']) {
                $item['photos'] = json_decode($item['photos'], true);
            } else {
                $item['photos'] = [];
            }
            if ($has_specifications && $item['specifications']) {
                $item['specifications'] = json_decode($item['specifications'], true);
            } else {
                $item['specifications'] = [];
            }
            
            // Build operator and supervisor names from employee table joins
            $item['operator_name'] = ($item['operator_first_name'] && $item['operator_last_name']) ? 
                $item['operator_first_name'] . ' ' . $item['operator_last_name'] : null;
            $item['supervisor_name'] = ($item['supervisor_first_name'] && $item['supervisor_last_name']) ? 
                $item['supervisor_first_name'] . ' ' . $item['supervisor_last_name'] : null;
            unset($item['operator_first_name'], $item['operator_last_name'], $item['supervisor_first_name'], $item['supervisor_last_name']);
            
            // Map old schema to new schema - prioritize actual name over type
            // Simple priority: internal_name -> equipment_name -> name -> title -> equipment_type
            $name_value = null;
            if (!empty($item['internal_name'])) {
                $name_value = $item['internal_name'];
            } elseif (!empty($item['equipment_name'])) {
                $name_value = $item['equipment_name'];
            } elseif (!empty($item['name'])) {
                $name_value = $item['name'];
            } elseif (!empty($item['title'])) {
                $name_value = $item['title'];
            } elseif (!empty($item['equipment_type'])) {
                $name_value = $item['equipment_type'];
            }
            
            // Set internal_name to the best available value
            $item['internal_name'] = $name_value ?: 'Unnamed Equipment';
            
            // Debug: log what we're using for the name
            error_log('[Equipment API] Get - ID: ' . $item['id'] . ' internal_name: ' . $item['internal_name'] . 
                ' (from: ' . ($name_value === $item['internal_name'] ? 'internal_name' : 'fallback') . ')');
            
            // Equipment type mapping
            if (empty($item['equipment_type'])) {
                if (!empty($item['type'])) {
                    $item['equipment_type'] = $item['type'];
                } else {
                    $item['equipment_type'] = 'Other';
                }
            }
            
            // Category mapping
            if (!$has_category || empty($item['category'])) {
                if (!empty($item['equipment_category'])) {
                    $item['category'] = $item['equipment_category'];
                } else {
                    $item['category'] = 'General';
                }
            }
            
            // Acquisition type mapping
            if ($has_acquisition_type && !empty($item['acquisition_type'])) {
                $item['acquisition_type'] = $item['acquisition_type'];
            } elseif ($has_ownership_type && !empty($item['ownership_type'])) {
                $item['acquisition_type'] = $item['ownership_type'];
            } else {
                $item['acquisition_type'] = 'Owned';
            }
            
            // Status mapping
            if (!$has_status || empty($item['status'])) {
                $item['status'] = 'On-Site';
            }
            
            // Current condition mapping
            if (empty($item['current_condition'])) {
                if (!empty($item['condition'])) {
                    $item['current_condition'] = $item['condition'];
                } else {
                    $item['current_condition'] = 'Good';
                }
            }
            
            // Manufacturer mapping
            if ($has_manufacturer && !empty($item['manufacturer'])) {
                $item['manufacturer'] = $item['manufacturer'];
            } elseif ($has_make && !empty($item['make'])) {
                $item['manufacturer'] = $item['make'];
            } else {
                $item['manufacturer'] = '';
            }
            
            // Model mapping
            if (empty($item['model'])) {
                $item['model'] = '';
            }
            
            // Operator mapping - if we have operator_id but no name from join
            if (empty($item['operator_name']) && !empty($item['assigned_operator_id'])) {
                $item['operator_name'] = 'Operator #' . $item['assigned_operator_id'];
            }
            
            // Handle rate fields - map old to new
            if (!$has_daily_rate && $has_hire_rate) {
                $item['daily_rate'] = $item['hire_rate'];
            }
            if (empty($item['daily_rate'])) {
                if (!empty($item['rate'])) {
                    $item['daily_rate'] = $item['rate'];
                } elseif (!empty($item['price'])) {
                    $item['daily_rate'] = $item['price'];
                } else {
                    $item['daily_rate'] = 0;
                }
            }
            
            // Cost to job calculation
            if (!$has_cost_to_job || empty($item['cost_to_job'])) {
                $rate = floatval($item['daily_rate']);
                $days = intval($item['days_on_site'] ?? 30);
                $item['cost_to_job'] = $rate * $days;
            }
            
            // Ensure cost_to_job is a number
            $item['cost_to_job'] = floatval($item['cost_to_job']);
            $item['daily_rate'] = floatval($item['daily_rate']);
        }
        
        // Add debug info to first item to help diagnose name display issues
        if (!empty($results)) {
            $first = $results[0];
            $results[0]['_debug'] = array(
                'raw_internal_name' => $first['internal_name'] ?? 'NOT SET',
                'raw_equipment_name' => $first['equipment_name'] ?? 'NOT SET',
                'raw_name' => $first['name'] ?? 'NOT SET',
                'raw_title' => $first['title'] ?? 'NOT SET',
                'raw_equipment_type' => $first['equipment_type'] ?? 'NOT SET',
                'mapped_internal_name' => $first['internal_name'],
                'available_columns' => array_keys($first)
            );
        }
        
        return new WP_REST_Response($results, 200);
    }
    
    public function get_single_equipment($request) {
        $id = intval($request->get_param('id'));
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        // Get existing columns to handle old schema
        $existing_columns = $this->wpdb->get_col("DESCRIBE {$table}");
        $has_assigned_operator = in_array('assigned_operator_id', $existing_columns);
        $has_supervisor = in_array('supervisor_responsible_id', $existing_columns);
        $has_check_in_by = in_array('check_in_by', $existing_columns);
        $has_check_out_by = in_array('check_out_by', $existing_columns);
        $has_photos = in_array('photos', $existing_columns);
        $has_arrival_photos = in_array('arrival_photos', $existing_columns);
        $has_return_photos = in_array('return_photos', $existing_columns);
        $has_damage_photos = in_array('damage_photos', $existing_columns);
        $has_specifications = in_array('specifications', $existing_columns);
        $has_internal_name = in_array('internal_name', $existing_columns);
        $has_equipment_name = in_array('equipment_name', $existing_columns);
        $has_acquisition_type = in_array('acquisition_type', $existing_columns);
        $has_ownership_type = in_array('ownership_type', $existing_columns);
        $has_make = in_array('make', $existing_columns);
        $has_manufacturer = in_array('manufacturer', $existing_columns);
        $has_category = in_array('category', $existing_columns);
        $has_status = in_array('status', $existing_columns);
        $has_daily_rate = in_array('daily_rate', $existing_columns);
        $has_hire_rate = in_array('hire_rate', $existing_columns);
        $has_cost_to_job = in_array('cost_to_job', $existing_columns);
        $has_actual_on_site = in_array('actual_on_site_date', $existing_columns);
        $has_actual_return = in_array('actual_return_date', $existing_columns);
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT e.*, 
                op.first_name as operator_first_name, 
                op.last_name as operator_last_name,
                sup.first_name as supervisor_first_name,
                sup.last_name as supervisor_last_name,
                cib.display_name as check_in_by_name,
                cob.display_name as check_out_by_name
            FROM {$table} e
            LEFT JOIN {$this->wpdb->prefix}pi_crm_employees op ON " . ($has_assigned_operator ? "e.assigned_operator_id = op.id" : "0") . "
            LEFT JOIN {$this->wpdb->prefix}pi_crm_employees sup ON " . ($has_supervisor ? "e.supervisor_responsible_id = sup.id" : "0") . "
            LEFT JOIN {$this->wpdb->users} cib ON " . ($has_check_in_by ? "e.check_in_by = cib.ID" : "0") . "
            LEFT JOIN {$this->wpdb->users} cob ON " . ($has_check_out_by ? "e.check_out_by = cob.ID" : "0") . "
            WHERE e.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$result) {
            return new WP_REST_Response(array('error' => 'Equipment not found'), 404);
        }
        
        // Parse JSON fields with column checks
        $result['photos'] = ($has_photos && $result['photos']) ? json_decode($result['photos'], true) : [];
        $result['arrival_photos'] = ($has_arrival_photos && $result['arrival_photos']) ? json_decode($result['arrival_photos'], true) : [];
        $result['return_photos'] = ($has_return_photos && $result['return_photos']) ? json_decode($result['return_photos'], true) : [];
        $result['damage_photos'] = ($has_damage_photos && $result['damage_photos']) ? json_decode($result['damage_photos'], true) : [];
        $result['specifications'] = ($has_specifications && $result['specifications']) ? json_decode($result['specifications'], true) : [];
        
        // Build names only if we have both parts
        $result['operator_name'] = ($result['operator_first_name'] && $result['operator_last_name']) ? 
            $result['operator_first_name'] . ' ' . $result['operator_last_name'] : null;
        $result['supervisor_name'] = ($result['supervisor_first_name'] && $result['supervisor_last_name']) ? 
            $result['supervisor_first_name'] . ' ' . $result['supervisor_last_name'] : null;
        
        // Map old schema to new schema - prioritize actual name over type
        if (empty($result['internal_name'])) {
            if ($has_equipment_name && $result['equipment_name']) {
                $result['internal_name'] = $result['equipment_name'];
            } elseif ($result['name']) {
                $result['internal_name'] = $result['name'];
            } elseif ($result['title']) {
                $result['internal_name'] = $result['title'];
            } elseif ($result['equipment_type']) {
                $result['internal_name'] = $result['equipment_type'];
            } else {
                $result['internal_name'] = 'Unnamed Equipment';
            }
        }
        
        // Debug: log what we're using for the name
        error_log('[Equipment API] Get Single - ID: ' . $result['id'] . ' internal_name: ' . $result['internal_name']);
        
        $result['acquisition_type'] = ($has_acquisition_type && $result['acquisition_type']) ? $result['acquisition_type'] : 
            (($has_ownership_type && $result['ownership_type']) ? $result['ownership_type'] : 'Owned');
        
        $result['status'] = ($has_status && $result['status']) ? $result['status'] : 'On-Site';
        $result['current_condition'] = $result['current_condition'] ?: 'Good';
        $result['category'] = ($has_category && $result['category']) ? $result['category'] : 'General';
        $result['equipment_type'] = $result['equipment_type'] ?: 'Other';
        $result['manufacturer'] = ($has_manufacturer && $result['manufacturer']) ? $result['manufacturer'] : 
            (($has_make && $result['make']) ? $result['make'] : '');
        
        // Handle rate fields
        if (!$has_daily_rate && $has_hire_rate) {
            $result['daily_rate'] = $result['hire_rate'];
        }
        $result['daily_rate'] = $result['daily_rate'] ?: 0;
        
        // Calculate cost_to_job
        if (!$has_cost_to_job || !$result['cost_to_job']) {
            $rate = $result['daily_rate'] ?: ($result['hire_rate'] ?: 0);
            $days = $result['days_on_site'] ?: 30;
            $result['cost_to_job'] = $rate * $days;
        }
        
        // Calculate days on site if we have the dates
        if ($has_actual_on_site && $result['actual_on_site_date']) {
            $start = new DateTime($result['actual_on_site_date']);
            $end = ($has_actual_return && $result['actual_return_date']) ? new DateTime($result['actual_return_date']) : new DateTime();
            $result['days_on_site'] = $start->diff($end)->days;
        } else {
            $result['days_on_site'] = $result['days_on_site'] ?: 0;
        }
        
        // Add debug info
        $result['_debug'] = [
            'has_internal_name' => $has_internal_name,
            'has_equipment_name' => $has_equipment_name,
            'available_columns' => $existing_columns,
            'raw_keys' => array_keys($result)
        ];
        
        return new WP_REST_Response($result, 200);
    }
    
    public function create_equipment($request) {
        $data = $request->get_json_params();
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        // Get available columns to know what we can save to
        $columns = $this->wpdb->get_col("DESCRIBE {$table}");
        
        $current_user = wp_get_current_user();
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'equipment_type' => sanitize_text_field($data['equipment_type']),
            'created_by' => $current_user->ID,
            'created_at' => current_time('mysql')
        );
        
        // Handle internal_name - ALWAYS save to both columns for compatibility
        $name_value = sanitize_text_field($data['internal_name'] ?? '');
        
        // If internal_name is empty, use equipment_type as fallback
        if (empty($name_value)) {
            $name_value = sanitize_text_field($data['equipment_type'] ?? 'Equipment');
        }
        
        error_log('[Equipment API] Create - internal_name value: ' . $name_value);
        error_log('[Equipment API] Create - available columns: ' . implode(', ', $columns));
        
        // ALWAYS save to equipment_name (exists in old schema)
        if (in_array('equipment_name', $columns)) {
            $insert_data['equipment_name'] = $name_value;
            error_log('[Equipment API] Create - Saved to equipment_name: ' . $name_value);
        }
        
        // ALSO save to internal_name if it exists (new schema)
        if (in_array('internal_name', $columns)) {
            $insert_data['internal_name'] = $name_value;
            error_log('[Equipment API] Create - Saved to internal_name: ' . $name_value);
        }
        
        // Fallback to 'name' column if needed
        if (in_array('name', $columns)) {
            $insert_data['name'] = $name_value;
            error_log('[Equipment API] Create - Saved to name: ' . $name_value);
        }
        
        // Add other fields only if column exists
        if (in_array('category', $columns)) {
            $insert_data['category'] = sanitize_text_field($data['category'] ?? 'Heavy Machinery');
        }
        if (in_array('acquisition_type', $columns)) {
            $insert_data['acquisition_type'] = sanitize_text_field($data['acquisition_type'] ?? 'Owned');
        }
        if (in_array('manufacturer', $columns) && !empty($data['manufacturer'])) {
            $insert_data['manufacturer'] = sanitize_text_field($data['manufacturer']);
        }
        if (in_array('brand', $columns) && !empty($data['brand'])) {
            $insert_data['brand'] = sanitize_text_field($data['brand']);
        }
        if (in_array('model', $columns) && !empty($data['model'])) {
            $insert_data['model'] = sanitize_text_field($data['model']);
        }
        if (in_array('daily_rate', $columns) && !empty($data['daily_rate'])) {
            $insert_data['daily_rate'] = floatval($data['daily_rate']);
        }
        if (in_array('allocated_from_date', $columns) && !empty($data['allocated_from_date'])) {
            $insert_data['allocated_from_date'] = sanitize_text_field($data['allocated_from_date']);
        }
        if (in_array('allocated_to_date', $columns) && !empty($data['allocated_to_date'])) {
            $insert_data['allocated_to_date'] = sanitize_text_field($data['allocated_to_date']);
        }
        if (in_array('assigned_operator_id', $columns) && !empty($data['assigned_operator_id'])) {
            $insert_data['assigned_operator_id'] = intval($data['assigned_operator_id']) ?: null;
        }
        if (in_array('current_condition', $columns)) {
            $insert_data['current_condition'] = sanitize_text_field($data['condition_on_arrival'] ?? 'Good');
        }
        
        $result = $this->wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            return new WP_REST_Response(array('error' => 'Failed to create equipment'), 500);
        }
        
        $equipment_id = $this->wpdb->insert_id;
        
        // Log creation
        $this->log_timeline($equipment_id, $data['job_id'], 'created', 'Equipment added to job', '', '');
        
        return new WP_REST_Response(array(
            'id' => $equipment_id,
            'success' => true,
            'message' => 'Equipment created successfully',
            '_debug' => $debug_info
        ), 201);
    }
    
    public function update_equipment($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        // Get current data for comparison
        $current = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        
        if (!$current) {
            return new WP_REST_Response(array('error' => 'Equipment not found'), 404);
        }
        
        // Get available columns
        $columns = $this->wpdb->get_col("DESCRIBE {$table}");
        
        $update_data = array();
        $changes = array();
        
        // Helper to track changes
        $track_change = function($field, $new_val, $old_val) use (&$changes) {
            if ($new_val !== null && $new_val !== $old_val) {
                $changes[$field] = array('old' => $old_val, 'new' => $new_val);
                return true;
            }
            return false;
        };
        
        // Handle internal_name - ALWAYS save to both columns for compatibility
        if (isset($data['internal_name'])) {
            $name_value = sanitize_text_field($data['internal_name']);
            error_log('[Equipment API] Update - equipment ID ' . $id . ' - internal_name value: ' . $name_value);
            error_log('[Equipment API] Update - available columns: ' . implode(', ', $columns));
            
            // ALWAYS save to equipment_name (exists in old schema)
            if (in_array('equipment_name', $columns)) {
                $update_data['equipment_name'] = $name_value;
                error_log('[Equipment API] Update - Saved to equipment_name: ' . $name_value);
            }
            
            // ALSO save to internal_name if it exists (new schema)
            if (in_array('internal_name', $columns)) {
                $update_data['internal_name'] = $name_value;
                error_log('[Equipment API] Update - Saved to internal_name: ' . $name_value);
            }
            
            // Fallback to 'name' column if needed
            if (in_array('name', $columns)) {
                $update_data['name'] = $name_value;
                error_log('[Equipment API] Update - Saved to name: ' . $name_value);
            }
        } else {
            error_log('[Equipment API] WARNING: internal_name not set in request data!');
        }
        
        // Updateable fields - only update if column exists
        $fields = array(
            'equipment_type', 'category', 'manufacturer', 'brand', 'model',
            'model_number', 'serial_number', 'vin', 'asset_tag', 'year_of_manufacture',
            'acquisition_type', 'supplier_name', 'supplier_contact', 'supplier_email',
            'supplier_phone', 'hire_reference_number', 'po_number', 'rate_type',
            'daily_rate', 'weekly_rate', 'monthly_rate', 'flat_fee', 'cost_per_hour',
            'deposit_held', 'insurance_included', 'fuel_policy', 'allocated_from_date',
            'allocated_to_date', 'actual_on_site_date', 'expected_return_date',
            'actual_return_date', 'delivery_method', 'collection_method',
            'current_location_on_site', 'assigned_operator_id', 'operator_certification_required',
            'operator_certification_verified', 'operator_certification_expiry',
            'supervisor_responsible_id', 'condition_on_arrival', 'condition_on_return',
            'current_condition', 'last_inspection_date', 'next_inspection_due',
            'inspection_status', 'hours_meter_reading', 'hours_meter_on_arrival',
            'fuel_level_on_arrival', 'fuel_level_current', 'status', 'notes'
        );
        
        foreach ($fields as $field) {
            if (isset($data[$field]) && in_array($field, $columns)) {
                $new_val = is_numeric($data[$field]) || is_bool($data[$field]) ? $data[$field] : sanitize_text_field($data[$field]);
                if ($track_change($field, $new_val, $current[$field] ?? '')) {
                    $update_data[$field] = $new_val;
                }
            }
        }
        
        // Handle JSON fields
        if (isset($data['specifications'])) {
            $update_data['specifications'] = json_encode($data['specifications']);
        }
        
        if (!empty($update_data)) {
            $update_data['updated_at'] = current_time('mysql');
            $this->wpdb->update($table, $update_data, array('id' => $id));
            
            // Log changes
            foreach ($changes as $field => $change) {
                $this->log_timeline($id, $current['job_id'], 'updated', ucfirst(str_replace('_', ' ', $field)) . ' updated', $change['old'], $change['new']);
            }
            
            // Recalculate cost if financial fields changed
            if (isset($update_data['daily_rate']) || isset($update_data['weekly_rate']) || 
                isset($update_data['actual_on_site_date']) || isset($update_data['actual_return_date'])) {
                $this->recalculate_cost($id);
            }
        }
        
        return new WP_REST_Response(array('success' => true, 'changes' => count($changes)), 200);
    }
    
    public function delete_equipment($request) {
        $id = intval($request->get_param('id'));
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        $current_user = wp_get_current_user();
        
        // Soft delete
        $this->wpdb->update($table, array(
            'is_deleted' => 1,
            'deleted_at' => current_time('mysql'),
            'deleted_by' => $current_user->ID,
            'status' => 'Off-Site / Returned'
        ), array('id' => $id));
        
        $this->log_timeline($id, 0, 'deleted', 'Equipment removed from job', 'Active', 'Deleted');
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // ============================================
    // Check-in / Check-out
    // ============================================
    
    public function check_in_equipment($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        $current_user = wp_get_current_user();
        $now = current_time('mysql');
        
        $update_data = array(
            'status' => 'On-Site',
            'actual_on_site_date' => sanitize_text_field($data['actual_on_site_date'] ?? $now),
            'current_location_on_site' => sanitize_text_field($data['location'] ?? ''),
            'hours_meter_on_arrival' => floatval($data['hours_meter_reading'] ?? 0),
            'hours_meter_reading' => floatval($data['hours_meter_reading'] ?? 0),
            'fuel_level_on_arrival' => sanitize_text_field($data['fuel_level'] ?? ''),
            'fuel_level_current' => sanitize_text_field($data['fuel_level'] ?? ''),
            'condition_on_arrival' => sanitize_text_field($data['condition'] ?? 'Good'),
            'current_condition' => sanitize_text_field($data['condition'] ?? 'Good'),
            'check_in_by' => $current_user->ID,
            'check_in_at' => $now,
            'check_in_notes' => sanitize_textarea_field($data['notes'] ?? '')
        );
        
        if (!empty($data['photos'])) {
            $update_data['arrival_photos'] = json_encode($data['photos']);
        }
        
        if (!empty($data['assigned_operator_id'])) {
            $update_data['assigned_operator_id'] = intval($data['assigned_operator_id']);
        }
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        
        $this->log_timeline($id, $data['job_id'] ?? 0, 'checked_in', 'Equipment checked in on site', 'En Route', 'On-Site', array(
            'location' => $update_data['current_location_on_site'],
            'hours_reading' => $update_data['hours_meter_reading'],
            'condition' => $update_data['condition_on_arrival']
        ));
        
        return new WP_REST_Response(array('success' => true, 'message' => 'Equipment checked in'), 200);
    }
    
    public function check_out_equipment($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        $current_user = wp_get_current_user();
        $now = current_time('mysql');
        
        // Get current equipment data for cost calculation
        $equipment = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        
        $update_data = array(
            'status' => sanitize_text_field($data['next_status'] ?? 'Off-Site / Returned'),
            'actual_return_date' => sanitize_text_field($data['actual_return_date'] ?? $now),
            'hours_meter_reading' => floatval($data['hours_meter_reading'] ?? 0),
            'fuel_level_current' => sanitize_text_field($data['fuel_level'] ?? ''),
            'condition_on_return' => sanitize_text_field($data['condition'] ?? 'Good'),
            'current_condition' => sanitize_text_field($data['condition'] ?? 'Good'),
            'check_out_by' => $current_user->ID,
            'check_out_at' => $now,
            'check_out_notes' => sanitize_textarea_field($data['notes'] ?? '')
        );
        
        if (!empty($data['next_job_allocation_id'])) {
            $update_data['next_job_allocation_id'] = intval($data['next_job_allocation_id']);
            $update_data['next_job_allocation_type'] = sanitize_text_field($data['next_job_allocation_type'] ?? 'Transfer to Job');
        }
        
        if (!empty($data['photos'])) {
            $update_data['return_photos'] = json_encode($data['photos']);
        }
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        
        // Calculate final cost
        $final_cost = $this->recalculate_cost($id);
        
        $this->log_timeline($id, $equipment['job_id'], 'checked_out', 'Equipment checked out from site', 'On-Site', $update_data['status'], array(
            'final_cost' => $final_cost,
            'hours_reading' => $update_data['hours_meter_reading'],
            'condition' => $update_data['condition_on_return']
        ));
        
        return new WP_REST_Response(array(
            'success' => true, 
            'message' => 'Equipment checked out',
            'final_cost' => $final_cost
        ), 200);
    }
    
    // ============================================
    // Timeline & Inspections
    // ============================================
    
    public function get_equipment_timeline($request) {
        $id = intval($request->get_param('id'));
        $table = $this->wpdb->prefix . 'pi_crm_equipment_timeline';
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE equipment_id = %d ORDER BY created_at DESC",
            $id
        ), ARRAY_A);
        
        foreach ($results as &$item) {
            $item['metadata'] = $item['metadata'] ? json_decode($item['metadata'], true) : [];
        }
        
        return new WP_REST_Response($results, 200);
    }
    
    public function get_equipment_inspections($request) {
        $id = intval($request->get_param('id'));
        $table = $this->wpdb->prefix . 'pi_crm_equipment_inspections';
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE equipment_id = %d ORDER BY inspection_date DESC",
            $id
        ), ARRAY_A);
        
        foreach ($results as &$item) {
            $item['photos'] = $item['photos'] ? json_decode($item['photos'], true) : [];
        }
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_inspection($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->wpdb->prefix . 'pi_crm_equipment_inspections';
        $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        $current_user = wp_get_current_user();
        
        $insert_data = array(
            'equipment_id' => $id,
            'job_id' => intval($data['job_id']),
            'inspection_type' => sanitize_text_field($data['inspection_type'] ?? 'Daily'),
            'inspection_date' => sanitize_text_field($data['inspection_date'] ?? current_time('mysql')),
            'inspected_by' => $current_user->ID,
            'inspected_by_name' => $current_user->display_name,
            'hours_meter_reading' => floatval($data['hours_meter_reading'] ?? 0),
            'fuel_level' => sanitize_text_field($data['fuel_level'] ?? ''),
            'condition_rating' => sanitize_text_field($data['condition_rating'] ?? 'Good'),
            'inspection_status' => sanitize_text_field($data['inspection_status'] ?? 'Pass'),
            'safety_certificates_verified' => intval($data['safety_certificates_verified'] ?? 0),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'photos' => json_encode($data['photos'] ?? []),
            'follow_up_required' => intval($data['follow_up_required'] ?? 0),
            'follow_up_notes' => sanitize_textarea_field($data['follow_up_notes'] ?? '')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        // Update equipment with latest inspection info
        $this->wpdb->update($equipment_table, array(
            'last_inspection_date' => $insert_data['inspection_date'],
            'current_condition' => $insert_data['condition_rating'],
            'inspection_status' => $insert_data['inspection_status'],
            'hours_meter_reading' => $insert_data['hours_meter_reading'],
            'fuel_level_current' => $insert_data['fuel_level']
        ), array('id' => $id));
        
        $this->log_timeline($id, $data['job_id'], 'inspection', 'Inspection completed - ' . $insert_data['inspection_status'], '', '', array(
            'condition' => $insert_data['condition_rating'],
            'hours' => $insert_data['hours_meter_reading']
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'id' => $equipment_id,
            'message' => 'Equipment created successfully',
            '_debug' => $debug_info
        ), 201);
    }
    
    // ============================================
    // Summary & Stats
    // ============================================
    
    public function get_equipment_summary($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->wpdb->prefix . 'pi_crm_equipment';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if (!$table_exists) {
            return new WP_REST_Response(array(
                'total' => 0,
                'by_status' => [],
                'by_category' => [],
                'by_acquisition' => [],
                'total_cost' => 0,
                'overdue_inspections' => 0,
                'requiring_attention' => [],
                'arriving_today' => 0,
                'leaving_today' => 0
            ), 200);
        }
        
        // Get existing columns
        $existing_columns = $this->wpdb->get_col("DESCRIBE {$table}");
        $has_is_deleted = in_array('is_deleted', $existing_columns);
        $has_internal_name = in_array('internal_name', $existing_columns);
        $has_acquisition_type = in_array('acquisition_type', $existing_columns);
        $has_cost_to_job = in_array('cost_to_job', $existing_columns);
        $has_category = in_array('category', $existing_columns);
        $has_next_inspection_due = in_array('next_inspection_due', $existing_columns);
        $has_current_condition = in_array('current_condition', $existing_columns);
        $has_allocated_from_date = in_array('allocated_from_date', $existing_columns);
        $has_allocated_to_date = in_array('allocated_to_date', $existing_columns);
        $has_actual_return_date = in_array('actual_return_date', $existing_columns);
        
        $where_clause = $has_is_deleted ? "AND is_deleted = 0" : "";
        
        // Total equipment count
        $total = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d {$where_clause}",
            $job_id
        ));
        
        // By status
        $by_status = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM {$table} WHERE job_id = %d {$where_clause} GROUP BY status",
            $job_id
        ), ARRAY_A);
        
        // By category (fallback to equipment_type if category doesn't exist)
        if ($has_category) {
            $by_category = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT category, COUNT(*) as count FROM {$table} WHERE job_id = %d {$where_clause} GROUP BY category",
                $job_id
            ), ARRAY_A);
        } else {
            $by_category = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT equipment_type as category, COUNT(*) as count FROM {$table} WHERE job_id = %d {$where_clause} GROUP BY equipment_type",
                $job_id
            ), ARRAY_A);
        }
        
        // By acquisition type (fallback to ownership_type)
        $acquisition_field = $has_acquisition_type ? 'acquisition_type' : 'ownership_type';
        $cost_field = $has_cost_to_job ? 'SUM(cost_to_job)' : '0';
        $by_acquisition = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT {$acquisition_field} as acquisition_type, COUNT(*) as count, {$cost_field} as total_cost 
            FROM {$table} WHERE job_id = %d {$where_clause} GROUP BY {$acquisition_field}",
            $job_id
        ), ARRAY_A);
        
        // Total cost
        if ($has_cost_to_job) {
            $total_cost = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT SUM(cost_to_job) FROM {$table} WHERE job_id = %d {$where_clause}",
                $job_id
            ));
        } else {
            // Calculate from hire_rate * days for old schema
            $total_cost = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT SUM(hire_rate * 30) FROM {$table} WHERE job_id = %d AND hire_rate > 0",
                $job_id
            ));
        }
        
        // Overdue inspections (use next_service_due as fallback)
        $inspection_field = $has_next_inspection_due ? 'next_inspection_due' : 'next_service_due';
        $overdue = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d {$where_clause} 
            AND {$inspection_field} < CURDATE() AND {$inspection_field} IS NOT NULL",
            $job_id
        ));
        
        // Equipment requiring attention
        $attention = [];
        if ($has_internal_name && ($has_current_condition || $has_next_inspection_due || $has_allocated_to_date)) {
            $condition_check = $has_current_condition ? "current_condition = 'Damaged'" : "0";
            $inspection_check = $has_next_inspection_due ? "(next_inspection_due < CURDATE() AND next_inspection_due IS NOT NULL)" : "0";
            $return_check = ($has_allocated_to_date && $has_actual_return_date) ? 
                "(allocated_to_date < CURDATE() AND actual_return_date IS NULL AND status = 'On-Site')" : "0";
            
            $attention = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, internal_name, status, current_condition, next_inspection_due, allocated_to_date
                FROM {$table} WHERE job_id = %d {$where_clause} 
                AND ({$inspection_check} OR {$condition_check} OR {$return_check})",
                $job_id
            ), ARRAY_A);
        }
        
        // Arriving today / Leaving today (use hire dates as fallback)
        $arriving_today = 0;
        $leaving_today = 0;
        if ($has_allocated_from_date) {
            $arriving_today = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE job_id = %d {$where_clause} 
                AND DATE(allocated_from_date) = CURDATE()",
                $job_id
            ));
        }
        if ($has_allocated_to_date) {
            $leaving_today = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE job_id = %d {$where_clause} 
                AND DATE(allocated_to_date) = CURDATE()",
                $job_id
            ));
        }
        
        return new WP_REST_Response(array(
            'total' => intval($total ?? 0),
            'by_status' => $by_status ?: [],
            'by_category' => $by_category ?: [],
            'by_acquisition' => $by_acquisition ?: [],
            'total_cost' => floatval($total_cost ?? 0),
            'overdue_inspections' => intval($overdue ?? 0),
            'requiring_attention' => $attention ?: [],
            'arriving_today' => intval($arriving_today ?? 0),
            'leaving_today' => intval($leaving_today ?? 0)
        ), 200);
    }
    
    // ============================================
    // Helper Methods
    // ============================================
    
    private function log_timeline($equipment_id, $job_id, $event_type, $title, $old_value, $new_value, $metadata = []) {
        $table = $this->wpdb->prefix . 'pi_crm_equipment_timeline';
        $current_user = wp_get_current_user();
        
        $this->wpdb->insert($table, array(
            'equipment_id' => $equipment_id,
            'job_id' => $job_id,
            'event_type' => $event_type,
            'event_title' => $title,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'performed_by' => $current_user->ID ?: 0,
            'performed_by_name' => $current_user->display_name ?: 'System',
            'metadata' => json_encode($metadata)
        ));
    }
    
    private function recalculate_cost($equipment_id) {
        $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
        $equipment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$equipment_table} WHERE id = %d",
            $equipment_id
        ), ARRAY_A);
        
        if (!$equipment) return 0;
        
        $cost = 0;
        $start_date = $equipment['actual_on_site_date'] ?: $equipment['allocated_from_date'];
        $end_date = $equipment['actual_return_date'] ?: current_time('mysql');
        
        if (!$start_date) return 0;
        
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $days = max(1, $start->diff($end)->days);
        
        switch ($equipment['acquisition_type']) {
            case 'Hired':
            case 'Rented':
                if ($equipment['daily_rate'] > 0) {
                    $cost = $equipment['daily_rate'] * $days;
                } elseif ($equipment['weekly_rate'] > 0) {
                    $weeks = ceil($days / 7);
                    $cost = $equipment['weekly_rate'] * $weeks;
                } elseif ($equipment['monthly_rate'] > 0) {
                    $months = ceil($days / 30);
                    $cost = $equipment['monthly_rate'] * $months;
                }
                break;
                
            case 'Owned':
                if ($equipment['flat_fee'] > 0) {
                    $cost = $equipment['flat_fee'];
                } elseif ($equipment['daily_rate'] > 0) {
                    $cost = $equipment['daily_rate'] * $days;
                }
                break;
                
            case 'Leased':
                if ($equipment['monthly_rate'] > 0) {
                    $months = ceil($days / 30);
                    $cost = $equipment['monthly_rate'] * $months;
                }
                break;
                
            case 'Cost-per-hour':
                $hours = $equipment['hours_meter_reading'] - $equipment['hours_meter_on_arrival'];
                $cost = $equipment['cost_per_hour'] * max(0, $hours);
                break;
        }
        
        $this->wpdb->update($equipment_table, array('cost_to_job' => $cost), array('id' => $equipment_id));
        
        return $cost;
    }
    
    // Placeholder methods for remaining endpoints
    public function get_equipment_documents($request) { return new WP_REST_Response([], 200); }
    public function upload_document($request) { return new WP_REST_Response(['success' => true], 201); }
    public function report_issue($request) { return new WP_REST_Response(['success' => true], 200); }
    public function request_extension($request) { return new WP_REST_Response(['success' => true], 200); }
    public function get_equipment_requests($request) { return new WP_REST_Response([], 200); }
    public function create_equipment_request($request) { return new WP_REST_Response(['success' => true, 'id' => 1], 201); }
    public function approve_equipment_request($request) { return new WP_REST_Response(['success' => true], 200); }
    public function reject_equipment_request($request) { return new WP_REST_Response(['success' => true], 200); }
    public function get_equipment_inventory($request) { return new WP_REST_Response([], 200); }
    public function get_available_equipment($request) { return new WP_REST_Response([], 200); }
}
