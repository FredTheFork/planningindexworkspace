<?php
/**
 * Planning Index Workspace - Team & Timesheets Module
 * Comprehensive construction team management and time tracking
 * 
 * Features:
 * - Global/Common Team & Timesheets page with shortcode [planning_workspace_team]
 * - Job-specific team assignments and timesheet tracking
 * - Full separation between global system and per-job data
 * - Employee management, crew management, clock in/out
 * - Timesheet reporting and analytics
 * 
 * @package PlanningIndex
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define module constants
if (!defined('PI_TEAM_TIMESHEETS_VERSION')) {
    define('PI_TEAM_TIMESHEETS_VERSION', '2.3.4'); // Fixed UTC timestamp parsing in timeAgo
}

if (!defined('PI_TEAM_TIMESHEETS_DB_VERSION')) {
    define('PI_TEAM_TIMESHEETS_DB_VERSION', '7'); // Timesheets indexes added
}

/**
 * Database table creation for Team & Timesheets
 */
add_action('init', 'pi_team_timesheets_init_database');
function pi_team_timesheets_init_database() {
    global $wpdb;
    
    $installed_ver = get_option('pi_team_timesheets_db_version', '0');
    if ($installed_ver == PI_TEAM_TIMESHEETS_DB_VERSION) {
        return;
    }
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Employees table - global employee registry with job association
    $table_employees = $wpdb->prefix . 'pi_crm_employees';
    $sql_employees = "CREATE TABLE IF NOT EXISTS $table_employees (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        job_id bigint(20) unsigned DEFAULT NULL,
        employee_code varchar(50) NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50) DEFAULT NULL,
        mobile varchar(50) DEFAULT NULL,
        role varchar(100) NOT NULL,
        trade varchar(100) DEFAULT NULL,
        skill_level varchar(20) DEFAULT 'skilled',
        employment_type varchar(20) DEFAULT 'full_time',
        hourly_rate decimal(8,2) DEFAULT 0.00,
        default_cost_code varchar(50) DEFAULT NULL,
        department varchar(100) DEFAULT NULL,
        hire_date date DEFAULT NULL,
        termination_date date DEFAULT NULL,
        status varchar(20) DEFAULT 'active',
        permissions_level varchar(20) DEFAULT 'field_worker',
        can_approve_timesheets tinyint(1) DEFAULT 0,
        can_approve_expenses tinyint(1) DEFAULT 0,
        user_id bigint(20) unsigned DEFAULT NULL,
        created_by bigint(20) unsigned NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY job_id (job_id),
        KEY employee_code (employee_code),
        KEY email (email),
        KEY role (role),
        KEY trade (trade),
        KEY status (status),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql_employees);
    
    // Crews table - INDEPENDENT: Crews are reusable groups of employees
    $table_crews = $wpdb->prefix . 'pi_crm_crews';
    $sql_crews = "CREATE TABLE IF NOT EXISTS $table_crews (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        crew_name varchar(200) NOT NULL,
        trade_specialty varchar(100) DEFAULT NULL,
        foreman_id bigint(20) unsigned DEFAULT NULL,
        description text DEFAULT NULL,
        status varchar(20) DEFAULT 'active',
        color_code varchar(7) DEFAULT '#4F46E5',
        created_by bigint(20) unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_crews);
    
    // Crew members table - links employees to crews
    $table_crew_members = $wpdb->prefix . 'pi_crm_crew_members';
    $sql_crew_members = "CREATE TABLE IF NOT EXISTS $table_crew_members (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        crew_id bigint(20) unsigned NOT NULL,
        employee_id bigint(20) unsigned NOT NULL,
        role_in_crew varchar(100) DEFAULT 'member',
        status varchar(20) DEFAULT 'active',
        joined_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY crew_id (crew_id),
        KEY employee_id (employee_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_crew_members);
    
    // Crew assignments table - links crews to jobs (crews can be assigned to multiple jobs)
    $table_crew_assignments = $wpdb->prefix . 'pi_crm_crew_assignments';
    $sql_crew_assignments = "CREATE TABLE IF NOT EXISTS $table_crew_assignments (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        crew_id bigint(20) unsigned NOT NULL,
        job_id bigint(20) unsigned NOT NULL,
        assigned_by bigint(20) unsigned NOT NULL,
        assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) DEFAULT 'active',
        notes text,
        PRIMARY KEY (id),
        UNIQUE KEY crew_job (crew_id, job_id),
        KEY crew_id (crew_id),
        KEY job_id (job_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_crew_assignments);
    
    // Team assignments table - job-specific team assignments
    $table_team_assignments = $wpdb->prefix . 'pi_crm_team_assignments';
    $sql_team_assignments = "CREATE TABLE IF NOT EXISTS $table_team_assignments (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        job_id bigint(20) unsigned NOT NULL,
        employee_id bigint(20) unsigned NOT NULL,
        role varchar(100) NOT NULL,
        trade varchar(100) DEFAULT NULL,
        skill_level varchar(20) DEFAULT 'skilled',
        hourly_rate decimal(8,2) DEFAULT 0.00,
        start_date date DEFAULT NULL,
        end_date date DEFAULT NULL,
        allocation_percentage int(11) DEFAULT 100,
        is_lead tinyint(1) DEFAULT 0,
        responsibilities text,
        certifications_required text,
        notes text,
        assigned_by bigint(20) unsigned NOT NULL,
        assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY job_id (job_id),
        KEY employee_id (employee_id),
        KEY role (role),
        KEY status (status),
        KEY start_date (start_date)
    ) $charset_collate;";
    dbDelta($sql_team_assignments);
    
    // Timesheets table - time entries (job-specific or global)
    $table_timesheets = $wpdb->prefix . 'pi_crm_timesheets';
    $sql_timesheets = "CREATE TABLE IF NOT EXISTS $table_timesheets (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        job_id bigint(20) unsigned DEFAULT NULL,
        worker_id bigint(20) unsigned NOT NULL,
        work_date date NOT NULL,
        start_time time DEFAULT NULL,
        end_time time DEFAULT NULL,
        break_duration int(11) DEFAULT 0,
        total_hours decimal(5,2) DEFAULT 0.00,
        overtime_hours decimal(5,2) DEFAULT 0.00,
        hourly_rate decimal(10,2) DEFAULT 0.00,
        cost_code varchar(50) DEFAULT NULL,
        task_description text,
        location varchar(200) DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        approved_by bigint(20) unsigned DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        rejection_reason text,
        photos text,
        notes text,
        gps_coordinates varchar(100) DEFAULT NULL,
        is_global_entry tinyint(1) DEFAULT 0,
        daily_report_id bigint(20) unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY job_id (job_id),
        KEY worker_id (worker_id),
        KEY work_date (work_date),
        KEY status (status),
        KEY cost_code (cost_code),
        KEY is_global_entry (is_global_entry),
        KEY daily_report_id (daily_report_id)
    ) $charset_collate;";
    dbDelta($sql_timesheets);
    
    // Clock status table - real-time clock in/out tracking
    $table_clock_status = $wpdb->prefix . 'pi_crm_clock_status';
    $sql_clock_status = "CREATE TABLE IF NOT EXISTS $table_clock_status (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        employee_id bigint(20) unsigned NOT NULL,
        job_id bigint(20) unsigned DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'clocked_out',
        clock_in_time datetime DEFAULT NULL,
        clock_out_time datetime DEFAULT NULL,
        break_start_time datetime DEFAULT NULL,
        total_break_minutes int(11) DEFAULT 0,
        gps_lat decimal(10,8) DEFAULT NULL,
        gps_lng decimal(11,8) DEFAULT NULL,
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY employee_id (employee_id),
        KEY status (status),
        KEY job_id (job_id)
    ) $charset_collate;";
    dbDelta($sql_clock_status);
    
    // Certifications table - employee certifications
    $table_certifications = $wpdb->prefix . 'pi_crm_certifications';
    $sql_certifications = "CREATE TABLE IF NOT EXISTS $table_certifications (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        employee_id bigint(20) unsigned NOT NULL,
        certification_name varchar(200) NOT NULL,
        issuing_body varchar(200) NOT NULL,
        certificate_number varchar(100) DEFAULT NULL,
        issue_date date NOT NULL,
        expiry_date date NOT NULL,
        reminder_days int(11) DEFAULT 30,
        status varchar(20) DEFAULT 'valid',
        document_path varchar(500) DEFAULT NULL,
        verified_by bigint(20) unsigned DEFAULT NULL,
        verified_at datetime DEFAULT NULL,
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY employee_id (employee_id),
        KEY expiry_date (expiry_date),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_certifications);
    
    // Timesheet approvals table - approval workflow
    $table_timesheet_approvals = $wpdb->prefix . 'pi_crm_timesheet_approvals';
    $sql_timesheet_approvals = "CREATE TABLE IF NOT EXISTS $table_timesheet_approvals (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        timesheet_id bigint(20) unsigned NOT NULL,
        approver_id bigint(20) unsigned NOT NULL,
        approval_level int(11) DEFAULT 1,
        status varchar(20) DEFAULT 'pending',
        comments text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY timesheet_id (timesheet_id),
        KEY approver_id (approver_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_timesheet_approvals);
    
    // Cost codes table - for categorizing time entries
    $table_cost_codes = $wpdb->prefix . 'pi_crm_cost_codes';
    $sql_cost_codes = "CREATE TABLE IF NOT EXISTS $table_cost_codes (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        code varchar(50) NOT NULL,
        description varchar(255) NOT NULL,
        category varchar(100) DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY code (code),
        KEY is_active (is_active)
    ) $charset_collate;";
    dbDelta($sql_cost_codes);
    
    // Migration: Add job_id column to employees table if not exists (for existing installations)
    $table_employees = $wpdb->prefix . 'pi_crm_employees';
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_employees}` LIKE 'job_id'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE `{$table_employees}` ADD COLUMN job_id bigint(20) unsigned DEFAULT NULL AFTER id, ADD KEY job_id (job_id)");
    }
    
    // CRITICAL MIGRATION: Rename employee_id to worker_id in timesheets table
    // The schema was created with employee_id but all code uses worker_id
    $table_timesheets = $wpdb->prefix . 'pi_crm_timesheets';
    $employee_col_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_timesheets}` LIKE 'employee_id'");
    $worker_col_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_timesheets}` LIKE 'worker_id'");
    
    if (!empty($employee_col_exists) && empty($worker_col_exists)) {
        error_log('[PI Team Timesheets] MIGRATION: Renaming employee_id to worker_id in timesheets table');
        $wpdb->query("ALTER TABLE `{$table_timesheets}` CHANGE COLUMN employee_id worker_id bigint(20) unsigned NOT NULL");
        // Recreate the index
        $wpdb->query("ALTER TABLE `{$table_timesheets}` DROP INDEX employee_id, ADD KEY worker_id (worker_id)");
        error_log('[PI Team Timesheets] MIGRATION: Column renamed successfully');
    }
    
    // MIGRATION: Remove job_id from crews table (transition from job-centric to independent crews)
    $table_crews = $wpdb->prefix . 'pi_crm_crews';
    $crews_job_col = $wpdb->get_results("SHOW COLUMNS FROM `{$table_crews}` LIKE 'job_id'");
    if (!empty($crews_job_col)) {
        error_log('[PI Team Timesheets] MIGRATION: Removing job_id from crews table (transition to independent crews)');
        // Drop the unique key first
        $wpdb->query("ALTER TABLE `{$table_crews}` DROP INDEX job_id");
        // Then drop the column
        $wpdb->query("ALTER TABLE `{$table_crews}` DROP COLUMN job_id");
        error_log('[PI Team Timesheets] MIGRATION: job_id removed from crews table');
    }
    
    // MIGRATION: Add color_code to crews if not exists
    $crews_color_col = $wpdb->get_results("SHOW COLUMNS FROM `{$table_crews}` LIKE 'color_code'");
    if (empty($crews_color_col)) {
        error_log('[PI Team Timesheets] MIGRATION: Adding color_code to crews table');
        $wpdb->query("ALTER TABLE `{$table_crews}` ADD COLUMN color_code varchar(7) DEFAULT '#4F46E5' AFTER status");
        $wpdb->query("ALTER TABLE `{$table_crews}` ADD COLUMN created_by bigint(20) unsigned DEFAULT NULL AFTER color_code");
        error_log('[PI Team Timesheets] MIGRATION: color_code added to crews table');
    }
    
    // CRITICAL MIGRATION: Verify and fix team_assignments table schema
    // dbDelta doesn't properly add missing columns to existing tables
    $table_team_assignments = $wpdb->prefix . 'pi_crm_team_assignments';
    $required_columns = array(
        'id' => "bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'job_id' => "bigint(20) unsigned NOT NULL",
        'employee_id' => "bigint(20) unsigned NOT NULL",
        'role' => "varchar(100) NOT NULL DEFAULT 'Team Member'",
        'trade' => "varchar(100) DEFAULT NULL",
        'skill_level' => "varchar(20) DEFAULT 'skilled'",
        'hourly_rate' => "decimal(8,2) DEFAULT 0.00",
        'start_date' => "date DEFAULT NULL",
        'end_date' => "date DEFAULT NULL",
        'allocation_percentage' => "int(11) DEFAULT 100",
        'is_lead' => "tinyint(1) DEFAULT 0",
        'responsibilities' => "text",
        'certifications_required' => "text",
        'notes' => "text",
        'assigned_by' => "bigint(20) unsigned NOT NULL",
        'assigned_at' => "datetime DEFAULT CURRENT_TIMESTAMP",
        'status' => "varchar(20) DEFAULT 'active'",
        'created_at' => "datetime DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    );
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_team_assignments}'");
    if ($table_exists) {
        // Get existing columns
        $existing_columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_team_assignments}`");
        $existing_col_names = array_map(function($col) { return $col->Field; }, $existing_columns);
        
        error_log('[PI Team Timesheets] MIGRATION: Checking team_assignments table schema. Existing columns: ' . implode(', ', $existing_col_names));
        
        // Add missing columns
        foreach ($required_columns as $col_name => $col_def) {
            if (!in_array($col_name, $existing_col_names)) {
                error_log("[PI Team Timesheets] MIGRATION: Adding missing column '{$col_name}' to team_assignments table");
                $wpdb->query("ALTER TABLE `{$table_team_assignments}` ADD COLUMN {$col_name} {$col_def}");
                if ($wpdb->last_error) {
                    error_log("[PI Team Timesheets] MIGRATION ERROR adding {$col_name}: " . $wpdb->last_error);
                } else {
                    error_log("[PI Team Timesheets] MIGRATION: Successfully added {$col_name}");
                }
            }
        }
        
        // Ensure indexes exist
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table_team_assignments}`");
        $index_names = array_unique(array_map(function($idx) { return $idx->Key_name; }, $indexes));
        $required_indexes = array('job_id', 'employee_id', 'role', 'status', 'start_date');
        foreach ($required_indexes as $idx_name) {
            if (!in_array($idx_name, $index_names)) {
                error_log("[PI Team Timesheets] MIGRATION: Adding missing index '{$idx_name}' to team_assignments table");
                $wpdb->query("ALTER TABLE `{$table_team_assignments}` ADD KEY {$idx_name} ({$idx_name})");
            }
        }
    } else {
        // Table doesn't exist - dbDelta should have created it, but log this
        error_log('[PI Team Timesheets] MIGRATION WARNING: team_assignments table does not exist after dbDelta!');
    }
    
    // MIGRATION: Ensure timesheets table has proper indexes for job filtering
    $table_timesheets = $wpdb->prefix . 'pi_crm_timesheets';
    $timesheets_indexes = $wpdb->get_results("SHOW INDEX FROM `{$table_timesheets}`");
    $timesheet_index_names = array_unique(array_map(function($idx) { return $idx->Key_name; }, $timesheets_indexes));
    
    $required_ts_indexes = array('job_id', 'worker_id', 'work_date', 'status', 'daily_report_id');
    foreach ($required_ts_indexes as $idx_name) {
        if (!in_array($idx_name, $timesheet_index_names)) {
            error_log("[PI Team Timesheets] MIGRATION: Adding missing index '{$idx_name}' to timesheets table");
            $wpdb->query("ALTER TABLE `{$table_timesheets}` ADD KEY {$idx_name} ({$idx_name})");
        }
    }
    
    // MIGRATION: Add daily_report_id column to timesheets table for Daily Reports integration
    $timesheets_columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_timesheets}`");
    $timesheet_column_names = array_map(function($col) { return $col->Field; }, $timesheets_columns);
    
    if (!in_array('daily_report_id', $timesheet_column_names)) {
        error_log("[PI Team Timesheets] MIGRATION: Adding daily_report_id column to timesheets table");
        $wpdb->query("ALTER TABLE `{$table_timesheets}` ADD COLUMN daily_report_id bigint(20) unsigned DEFAULT NULL AFTER is_global_entry");
        $wpdb->query("ALTER TABLE `{$table_timesheets}` ADD KEY daily_report_id (daily_report_id)");
        error_log("[PI Team Timesheets] MIGRATION: daily_report_id column added successfully");
    }
    
    update_option('pi_team_timesheets_db_version', PI_TEAM_TIMESHEETS_DB_VERSION);
}

/**
 * Register REST API endpoints for Team & Timesheets
 */
add_action('rest_api_init', 'pi_team_timesheets_register_rest_routes');
function pi_team_timesheets_register_rest_routes() {
    $namespace = 'pi/v1';
    
    // ====================
    // EMPLOYEES ENDPOINTS
    // ====================
    
    // List/create employees
    register_rest_route($namespace, '/employees', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pi_team_timesheets_get_employees',
            'permission_callback' => 'pi_team_timesheets_check_permission',
            'args' => array(
                'job_id' => array('required' => false),
                'status' => array('required' => false),
                'role' => array('required' => false),
                'trade' => array('required' => false),
                'search' => array('required' => false),
            )
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'pi_team_timesheets_create_employee',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    // Get/update/delete single employee
    register_rest_route($namespace, '/employees/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pi_team_timesheets_get_employee',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'pi_team_timesheets_update_employee',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'pi_team_timesheets_delete_employee',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    // Employee stats
    register_rest_route($namespace, '/employees/(?P<id>\d+)/stats', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_employee_stats',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // Bulk employee actions
    register_rest_route($namespace, '/employees/bulk', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_bulk_employee_action',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // ====================
    // CREWS ENDPOINTS - INDEPENDENT CREWS
    // ====================
    
    // Get all crews (global view)
    register_rest_route($namespace, '/crews', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pi_team_timesheets_get_crews',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'pi_team_timesheets_create_crew',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    // Get specific crew by ID
    register_rest_route($namespace, '/crews/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pi_team_timesheets_get_crew',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'pi_team_timesheets_update_crew',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    // Get available crews for a job (not yet assigned)
    register_rest_route($namespace, '/crews/available', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_available_crews',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    // Get crews assigned to a job
    register_rest_route($namespace, '/crews/assigned/(?P<job_id>\d+)', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_job_crews',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // Assign a crew to a job
    register_rest_route($namespace, '/crews/assign', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_assign_crew_to_job',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // Remove a crew from a job
    register_rest_route($namespace, '/crews/unassign/(?P<id>\d+)', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_remove_crew_from_job',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // ====================
    // TEAM ASSIGNMENTS (Job-specific)
    // ====================
    
    register_rest_route($namespace, '/team', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pi_team_timesheets_get_team_assignments',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'pi_team_timesheets_assign_team_member',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    register_rest_route($namespace, '/team/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'pi_team_timesheets_update_team_assignment',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'pi_team_timesheets_remove_team_member',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    // Available workers for assignment
    register_rest_route($namespace, '/team/available', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_available_workers',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // ====================
    // TIMESHEETS ENDPOINTS
    // ====================
    
    register_rest_route($namespace, '/timesheets', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pi_team_timesheets_get_timesheets',
            'permission_callback' => 'pi_team_timesheets_check_permission',
            'args' => array(
                'job_id' => array('required' => false),
                'employee_id' => array('required' => false),
                'start_date' => array('required' => false),
                'end_date' => array('required' => false),
                'status' => array('required' => false),
                'is_global' => array('required' => false),
            )
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'pi_team_timesheets_create_timesheet',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    register_rest_route($namespace, '/timesheets/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pi_team_timesheets_get_timesheet',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'pi_team_timesheets_update_timesheet',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        ),
        array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'pi_team_timesheets_delete_timesheet',
            'permission_callback' => 'pi_team_timesheets_check_permission'
        )
    ));
    
    // Timesheet summary
    register_rest_route($namespace, '/timesheets/summary', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_timesheet_summary',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // ====================
    // CLOCK ENDPOINTS
    // ====================
    
    register_rest_route($namespace, '/clock/status', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_clock_status',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    register_rest_route($namespace, '/clock/in', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_clock_in',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    register_rest_route($namespace, '/clock/out', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_clock_out',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    register_rest_route($namespace, '/clock/on-site', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_on_site_workers',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    // ====================
    // APPROVALS ENDPOINTS
    // ====================
    
    register_rest_route($namespace, '/approvals/pending', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_pending_approvals',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    register_rest_route($namespace, '/approvals/(?P<id>\d+)/approve', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_approve_timesheet',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    register_rest_route($namespace, '/approvals/(?P<id>\d+)/reject', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_reject_timesheet',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    register_rest_route($namespace, '/approvals/bulk', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'pi_team_timesheets_bulk_approval',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // ====================
    // DASHBOARD ENDPOINTS
    // ====================
    
    register_rest_route($namespace, '/team-dashboard/stats', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_dashboard_stats',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    register_rest_route($namespace, '/team-dashboard/weekly-hours', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_weekly_hours',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    register_rest_route($namespace, '/team-dashboard/activity', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_activity',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    register_rest_route($namespace, '/team-dashboard/trade-distribution', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_trade_distribution',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    register_rest_route($namespace, '/team-dashboard/attendance-trends', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_attendance_trends',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    register_rest_route($namespace, '/team-dashboard/labor-cost', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_labor_cost',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    register_rest_route($namespace, '/team-dashboard/top-performers', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_top_performers',
        'permission_callback' => 'pi_team_timesheets_check_permission',
        'args' => array(
            'job_id' => array('required' => false, 'default' => null)
        )
    ));
    
    // ====================
    // UTILITY ENDPOINTS
    // ====================
    
    register_rest_route($namespace, '/cost-codes', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_cost_codes',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    register_rest_route($namespace, '/jobs', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_team_timesheets_get_jobs',
        'permission_callback' => 'pi_team_timesheets_check_permission'
    ));
    
    // Debug: Log registered routes (remove in production)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Team Timesheets] REST routes registered for namespace: ' . $namespace);
    }
}

/**
 * Permission check
 */
function pi_team_timesheets_check_permission() {
    return is_user_logged_in();
}

// Include all the API endpoint callback functions
// These will be defined below...

/**
 * Get employees list - JOB SPECIFIC (STRICT)
 * If job_id provided: show ONLY employees assigned via team_assignments table
 * If no job_id: show ALL employees (global view)
 * NOTE: Does NOT use employees.job_id column - only team_assignments
 */
function pi_team_timesheets_get_employees($request) {
    global $wpdb;
    
    $job_id = $request->get_param('job_id');
    $status = $request->get_param('status');
    $role = $request->get_param('role');
    $trade = $request->get_param('trade');
    $search = $request->get_param('search');
    
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // STRICT: If job_id provided, ONLY get employees from team_assignments table
    if ($job_id && intval($job_id) > 0) {
        $sql = "SELECT e.* FROM {$employees_table} e 
                INNER JOIN {$team_assignments_table} ta ON e.id = ta.employee_id 
                WHERE ta.job_id = %d";
        $params = array(intval($job_id));
        
        if ($status) {
            $sql .= " AND e.status = %s";
            $params[] = $status;
        } else {
            $sql .= " AND e.status = 'active'"; // Default to active for job views
        }
        if ($role) {
            $sql .= " AND e.role = %s";
            $params[] = $role;
        }
        if ($trade) {
            $sql .= " AND e.trade = %s";
            $params[] = $trade;
        }
        if ($search) {
            $sql .= " AND (e.first_name LIKE %s OR e.last_name LIKE %s OR e.email LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $sql .= " ORDER BY e.last_name, e.first_name";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return new WP_REST_Response($results ?: array(), 200);
    }
    
    // Global view: show all employees (no job filtering)
    $sql = "SELECT * FROM {$employees_table} WHERE 1=1";
    $params = array();
    
    if ($status) {
        $sql .= " AND status = %s";
        $params[] = $status;
    }
    if ($role) {
        $sql .= " AND role = %s";
        $params[] = $role;
    }
    if ($trade) {
        $sql .= " AND trade = %s";
        $params[] = $trade;
    }
    if ($search) {
        $sql .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)";
        $like = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    
    $sql .= " ORDER BY last_name, first_name";
    
    $results = $wpdb->get_results($params ? $wpdb->prepare($sql, $params) : $sql, ARRAY_A);
    return new WP_REST_Response($results ?: array(), 200);
}

/**
 * Create employee
 */
function pi_team_timesheets_create_employee($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_employees';
    
    // Validate required fields
    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['role'])) {
        return new WP_REST_Response(array('error' => 'Missing required fields: first_name, last_name, email, role'), 400);
    }
    
    // Check if email already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE email = %s",
        sanitize_email($data['email'])
    ));
    if ($existing) {
        return new WP_REST_Response(array('error' => 'An employee with this email already exists'), 409);
    }
    
    // Get job_id from request (for job-specific employee creation)
    $job_id = !empty($data['job_id']) ? intval($data['job_id']) : null;
    
    $insert_data = array(
        'job_id' => $job_id,
        'first_name' => sanitize_text_field($data['first_name']),
        'last_name' => sanitize_text_field($data['last_name']),
        'email' => sanitize_email($data['email']),
        'phone' => sanitize_text_field($data['phone'] ?? ''),
        'mobile' => sanitize_text_field($data['mobile'] ?? ''),
        'role' => sanitize_text_field($data['role']),
        'trade' => sanitize_text_field($data['trade'] ?? ''),
        'skill_level' => sanitize_text_field($data['skill_level'] ?? 'skilled'),
        'employment_type' => sanitize_text_field($data['employment_type'] ?? 'full_time'),
        'hourly_rate' => floatval($data['hourly_rate'] ?? 0),
        'default_cost_code' => sanitize_text_field($data['default_cost_code'] ?? ''),
        'department' => sanitize_text_field($data['department'] ?? ''),
        'hire_date' => !empty($data['hire_date']) ? sanitize_text_field($data['hire_date']) : null,
        'status' => 'active',
        'permissions_level' => sanitize_text_field($data['permissions_level'] ?? 'field_worker'),
        'can_approve_timesheets' => !empty($data['can_approve_timesheets']) ? 1 : 0,
        'can_approve_expenses' => !empty($data['can_approve_expenses']) ? 1 : 0,
        'created_by' => get_current_user_id(),
        'created_at' => current_time('mysql')
    );
    
    // Generate employee code
    $year = date('Y');
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_at >= '{$year}-01-01'") + 1;
    $insert_data['employee_code'] = 'EMP-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    
    $result = $wpdb->insert($table, $insert_data);
    
    if ($result === false) {
        return new WP_REST_Response(array('error' => 'Database insert failed', 'details' => $wpdb->last_error), 500);
    }
    
    $employee_id = $wpdb->insert_id;
    
    // If job_id provided, also create team assignment entry
    if ($job_id) {
        $team_table = $wpdb->prefix . 'pi_crm_team_assignments';
        $assignment_data = array(
            'job_id' => $job_id,
            'employee_id' => $employee_id,
            'role' => $insert_data['role'],
            'trade' => $insert_data['trade'],
            'skill_level' => $insert_data['skill_level'],
            'hourly_rate' => $insert_data['hourly_rate'],
            'is_lead' => 0,
            'assigned_by' => get_current_user_id(),
            'status' => 'active',
            'created_at' => current_time('mysql')
        );
        $team_result = $wpdb->insert($team_table, $assignment_data);
        
        if ($team_result === false || $wpdb->insert_id === 0) {
            error_log('[Team Timesheets] Failed to create team assignment in createEmployee: ' . $wpdb->last_error);
            // Don't fail the whole request - employee was created, just log the error
        } else {
            error_log("[Team Timesheets] Created team assignment ID {$wpdb->insert_id} for employee {$employee_id} on job {$job_id}");
        }
        
        // Also sync to crew
        pi_team_timesheets_sync_team_to_crew($job_id, $employee_id, 'member');
    }
    
    return new WP_REST_Response(array(
        'id' => $employee_id,
        'success' => true,
        'employee_code' => $insert_data['employee_code']
    ), 201);
}

/**
 * Get single employee
 */
function pi_team_timesheets_get_employee($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $table = $wpdb->prefix . 'pi_crm_employees';
    
    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $id
    ), ARRAY_A);
    
    if (!$employee) {
        return new WP_Error('not_found', 'Employee not found', array('status' => 404));
    }
    
    // Get certifications
    $certs_table = $wpdb->prefix . 'pi_crm_certifications';
    $certs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$certs_table} WHERE employee_id = %d ORDER BY expiry_date",
        $id
    ), ARRAY_A);
    
    $employee['certifications'] = $certs;
    
    return new WP_REST_Response($employee, 200);
}

/**
 * Update employee
 */
function pi_team_timesheets_update_employee($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_employees';
    
    $update_data = array();
    $fields = array('first_name', 'last_name', 'email', 'phone', 'mobile', 'role', 'trade', 
                    'skill_level', 'employment_type', 'hourly_rate', 'default_cost_code', 
                    'department', 'status', 'permissions_level', 'termination_date');
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = sanitize_text_field($data[$field]);
        }
    }
    
    if (isset($data['can_approve_timesheets'])) {
        $update_data['can_approve_timesheets'] = !empty($data['can_approve_timesheets']) ? 1 : 0;
    }
    if (isset($data['can_approve_expenses'])) {
        $update_data['can_approve_expenses'] = !empty($data['can_approve_expenses']) ? 1 : 0;
    }
    
    $update_data['updated_at'] = current_time('mysql');
    
    $wpdb->update($table, $update_data, array('id' => $id));
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Delete employee (hard delete with cascade)
 * Removes employee from all jobs and deletes their record
 */
function pi_team_timesheets_delete_employee($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $team_table = $wpdb->prefix . 'pi_crm_team_assignments';
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    $clock_table = $wpdb->prefix . 'pi_crm_clock_status';
    $crew_members_table = $wpdb->prefix . 'pi_crm_crew_members';
    
    // 1. Remove employee from all job assignments
    $wpdb->delete($team_table, array('employee_id' => $id));
    
    // 2. Remove from all crews
    $wpdb->delete($crew_members_table, array('employee_id' => $id));
    
    // 3. Delete clock status
    $wpdb->delete($clock_table, array('employee_id' => $id));
    
    // 4. Handle timesheets - keep records but anonymize or mark as deleted
    // Option: Set employee_id to NULL so records remain for reporting
    $wpdb->query($wpdb->prepare(
        "UPDATE {$timesheets_table} SET worker_id = NULL, notes = CONCAT(COALESCE(notes, ''), ' [Employee deleted]') WHERE worker_id = %d",
        $id
    ));
    
    // 5. Finally, delete the employee record
    $result = $wpdb->delete($employees_table, array('id' => $id));
    
    if ($result === false) {
        return new WP_REST_Response(array('error' => 'Failed to delete employee', 'details' => $wpdb->last_error), 500);
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Employee deleted and removed from all jobs'
    ), 200);
}

/**
 * Get employee stats
 */
function pi_team_timesheets_get_employee_stats($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $month_start = date('Y-m-01');
    
    // Get week hours with overtime breakdown
    $week_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            SUM(total_hours) as regular_hours,
            SUM(overtime_hours) as overtime_hours,
            SUM(total_hours + overtime_hours) as total_hours
         FROM {$timesheets_table} 
         WHERE worker_id = %d AND work_date BETWEEN %s AND %s AND status != 'rejected'",
        $id, $week_start, $week_end
    ), ARRAY_A);
    
    // Get month hours with overtime breakdown
    $month_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            SUM(total_hours) as regular_hours,
            SUM(overtime_hours) as overtime_hours,
            SUM(total_hours + overtime_hours) as total_hours
         FROM {$timesheets_table} 
         WHERE worker_id = %d AND work_date >= %s AND status != 'rejected'",
        $id, $month_start
    ), ARRAY_A);
    
    $total_entries = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$timesheets_table} WHERE worker_id = %d",
        $id
    ));
    
    $week_regular = floatval($week_stats['regular_hours'] ?? 0);
    $week_ot = floatval($week_stats['overtime_hours'] ?? 0);
    $week_total = floatval($week_stats['total_hours'] ?? 0);
    
    $month_regular = floatval($month_stats['regular_hours'] ?? 0);
    $month_ot = floatval($month_stats['overtime_hours'] ?? 0);
    $month_total = floatval($month_stats['total_hours'] ?? 0);
    
    return new WP_REST_Response(array(
        'week_hours' => round($week_total, 2),              // Total includes overtime
        'week_regular_hours' => round($week_regular, 2),
        'week_overtime_hours' => round($week_ot, 2),
        'month_hours' => round($month_total, 2),            // Total includes overtime
        'month_regular_hours' => round($month_regular, 2),
        'month_overtime_hours' => round($month_ot, 2),
        'total_entries' => intval($total_entries)
    ), 200);
}

/**
 * Bulk employee action
 */
function pi_team_timesheets_bulk_employee_action($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $action = sanitize_text_field($data['action']);
    $ids = array_map('intval', $data['ids'] ?? array());
    
    if (empty($ids)) {
        return new WP_Error('no_ids', 'No employee IDs provided', array('status' => 400));
    }
    
    $table = $wpdb->prefix . 'pi_crm_employees';
    
    switch ($action) {
        case 'activate':
            foreach ($ids as $id) {
                $wpdb->update($table, array('status' => 'active'), array('id' => $id));
            }
            break;
        case 'deactivate':
            foreach ($ids as $id) {
                $wpdb->update($table, array('status' => 'inactive'), array('id' => $id));
            }
            break;
        case 'delete':
            foreach ($ids as $id) {
                $wpdb->update($table, array('status' => 'inactive', 'termination_date' => current_time('Y-m-d')), array('id' => $id));
            }
            break;
    }
    
    return new WP_REST_Response(array('success' => true, 'affected' => count($ids)), 200);
}

/**
 * Get all crews (independent, reusable groups)
 */
function pi_team_timesheets_get_crews($request) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pi_crm_crews';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    $crews = $wpdb->get_results(
        "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last,
                COUNT(cm.id) as member_count
         FROM {$table} c
         LEFT JOIN {$employees_table} e ON c.foreman_id = e.id
         LEFT JOIN {$members_table} cm ON c.id = cm.crew_id AND cm.status = 'active'
         WHERE c.status = 'active'
         GROUP BY c.id
         ORDER BY c.crew_name",
        ARRAY_A
    );
    
    return new WP_REST_Response($crews ?: array(), 200);
}

/**
 * Get crews available for assignment to a job
 */
function pi_team_timesheets_get_available_crews($request) {
    global $wpdb;
    
    $job_id = intval($request->get_param('job_id') ?? 0);
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    $crew_assignments_table = $wpdb->prefix . 'pi_crm_crew_assignments';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    if ($job_id) {
        // Get crews NOT already assigned to this job
        $crews = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last,
                    COUNT(cm.id) as member_count
             FROM {$crews_table} c
             LEFT JOIN {$employees_table} e ON c.foreman_id = e.id
             LEFT JOIN {$members_table} cm ON c.id = cm.crew_id AND cm.status = 'active'
             WHERE c.status = 'active'
             AND c.id NOT IN (
                 SELECT crew_id FROM {$crew_assignments_table} 
                 WHERE job_id = %d AND status = 'active'
             )
             GROUP BY c.id
             ORDER BY c.crew_name",
            $job_id
        ), ARRAY_A);
    } else {
        // Get all active crews
        $crews = $wpdb->get_results(
            "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last,
                    COUNT(cm.id) as member_count
             FROM {$crews_table} c
             LEFT JOIN {$employees_table} e ON c.foreman_id = e.id
             LEFT JOIN {$members_table} cm ON c.id = cm.crew_id AND cm.status = 'active'
             WHERE c.status = 'active'
             GROUP BY c.id
             ORDER BY c.crew_name",
            ARRAY_A
        );
    }
    
    return new WP_REST_Response($crews ?: array(), 200);
}

/**
 * Get crews assigned to a specific job
 */
function pi_team_timesheets_get_job_crews($request) {
    global $wpdb;
    
    $job_id = intval($request->get_param('job_id') ?? 0);
    
    if (!$job_id) {
        return new WP_Error('missing_job_id', 'Job ID is required', array('status' => 400));
    }
    
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    $crew_assignments_table = $wpdb->prefix . 'pi_crm_crew_assignments';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    // Check if required tables exist
    $required_tables = [$crews_table, $crew_assignments_table, $members_table, $employees_table];
    foreach ($required_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            error_log("[Team Timesheets] Missing table: {$table}");
            return new WP_REST_Response(array(), 200);
        }
    }
    
    // Check if crew_members table has status column
    if ($wpdb->get_var("SHOW COLUMNS FROM {$members_table} LIKE 'status'") !== 'status') {
        error_log("[Team Timesheets] Adding missing status column to crew_members table");
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN status varchar(20) DEFAULT 'active'");
    }
    
    $crews = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last,
                ca.assigned_at, ca.notes as assignment_notes,
                COUNT(cm.id) as member_count
         FROM {$crews_table} c
         JOIN {$crew_assignments_table} ca ON c.id = ca.crew_id
         LEFT JOIN {$employees_table} e ON c.foreman_id = e.id
         LEFT JOIN {$members_table} cm ON c.id = cm.crew_id AND cm.status = 'active'
         WHERE ca.job_id = %d AND ca.status = 'active' AND c.status = 'active'
         GROUP BY c.id
         ORDER BY ca.assigned_at DESC",
        $job_id
    ), ARRAY_A);
    
    return new WP_REST_Response($crews ?: array(), 200);
}

/**
 * Assign a crew to a job (adds all crew members to the job)
 */
function pi_team_timesheets_assign_crew_to_job($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $crew_id = intval($data['crew_id'] ?? 0);
    $job_id = intval($data['job_id'] ?? 0);
    
    if (!$crew_id || !$job_id) {
        return new WP_REST_Response(array('error' => 'Crew ID and Job ID are required'), 400);
    }
    
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    $crew_assignments_table = $wpdb->prefix . 'pi_crm_crew_assignments';
    $crew_members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // Check if crew exists
    $crew = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$crews_table} WHERE id = %d AND status = 'active'",
        $crew_id
    ), ARRAY_A);
    
    if (!$crew) {
        return new WP_REST_Response(array('error' => 'Crew not found'), 404);
    }
    
    // Check if already assigned
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$crew_assignments_table} WHERE crew_id = %d AND job_id = %d AND status = 'active'",
        $crew_id, $job_id
    ));
    
    if ($existing) {
        return new WP_REST_Response(array('error' => 'Crew already assigned to this job'), 409);
    }
    
    // Create the assignment
    $wpdb->insert($crew_assignments_table, array(
        'crew_id' => $crew_id,
        'job_id' => $job_id,
        'assigned_by' => get_current_user_id(),
        'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        'status' => 'active'
    ));
    
    $assignment_id = $wpdb->insert_id;
    
    // Get all active crew members
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT employee_id, role_in_crew FROM {$crew_members_table} 
         WHERE crew_id = %d AND status = 'active'",
        $crew_id
    ), ARRAY_A);
    
    $added_count = 0;
    $skipped_count = 0;
    
    // Add each crew member to the job (if not already assigned)
    foreach ($members as $member) {
        $employee_id = $member['employee_id'];
        
        // Check if already assigned to this job
        $existing_assignment = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$team_assignments_table} 
             WHERE job_id = %d AND employee_id = %d AND status = 'active'",
            $job_id, $employee_id
        ));
        
        if ($existing_assignment) {
            $skipped_count++;
            continue;
        }
        
        // Add to job
        $wpdb->insert($team_assignments_table, array(
            'job_id' => $job_id,
            'employee_id' => $employee_id,
            'role' => $member['role_in_crew'] ?: 'Team Member',
            'assigned_by' => get_current_user_id(),
            'status' => 'active',
            'created_at' => current_time('mysql')
        ));
        
        $added_count++;
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'assignment_id' => $assignment_id,
        'crew_id' => $crew_id,
        'job_id' => $job_id,
        'members_added' => $added_count,
        'members_skipped' => $skipped_count,
        'total_members' => count($members)
    ), 201);
}

/**
 * Remove a crew assignment from a job
 */
function pi_team_timesheets_remove_crew_from_job($request) {
    global $wpdb;
    
    $assignment_id = intval($request->get_param('id'));
    $data = $request->get_json_params();
    $remove_members = !empty($data['remove_members']);
    
    $crew_assignments_table = $wpdb->prefix . 'pi_crm_crew_assignments';
    $crew_members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // Get assignment details
    $assignment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$crew_assignments_table} WHERE id = %d",
        $assignment_id
    ), ARRAY_A);
    
    if (!$assignment) {
        return new WP_REST_Response(array('error' => 'Assignment not found'), 404);
    }
    
    $crew_id = $assignment['crew_id'];
    $job_id = $assignment['job_id'];
    
    // Mark assignment as removed
    $wpdb->update($crew_assignments_table, 
        array('status' => 'removed'),
        array('id' => $assignment_id)
    );
    
    $removed_count = 0;
    
    // Optionally remove all crew members from the job
    if ($remove_members) {
        // Get crew members
        $members = $wpdb->get_col($wpdb->prepare(
            "SELECT employee_id FROM {$crew_members_table} WHERE crew_id = %d AND status = 'active'",
            $crew_id
        ));
        
        // Remove each member from the job
        foreach ($members as $employee_id) {
            $wpdb->update($team_assignments_table,
                array('status' => 'removed', 'updated_at' => current_time('mysql')),
                array('job_id' => $job_id, 'employee_id' => $employee_id, 'status' => 'active')
            );
            if ($wpdb->rows_affected > 0) {
                $removed_count++;
            }
        }
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'crew_removed' => true,
        'members_removed' => $removed_count
    ), 200);
}

/**
 * Create crew - INDEPENDENT
 * Creates a reusable crew of employees that can be assigned to jobs
 */
function pi_team_timesheets_create_crew($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_crews';
    
    $crew_name = !empty($data['crew_name']) 
        ? sanitize_text_field($data['crew_name']) 
        : 'New Crew';
    
    $insert_data = array(
        'crew_name' => $crew_name,
        'trade_specialty' => sanitize_text_field($data['trade_specialty'] ?? ''),
        'foreman_id' => intval($data['foreman_id'] ?? 0),
        'description' => sanitize_textarea_field($data['description'] ?? ''),
        'color_code' => sanitize_text_field($data['color_code'] ?? '#4F46E5'),
        'created_by' => get_current_user_id(),
        'status' => 'active',
        'created_at' => current_time('mysql')
    );
    
    $wpdb->insert($table, $insert_data);
    $crew_id = $wpdb->insert_id;
    
    // Add members if provided
    if (!empty($data['members']) && is_array($data['members'])) {
        $members_table = $wpdb->prefix . 'pi_crm_crew_members';
        foreach ($data['members'] as $member) {
            if (is_array($member)) {
                $member_id = intval($member['employee_id'] ?? 0);
                $role = sanitize_text_field($member['role'] ?? 'member');
            } else {
                $member_id = intval($member);
                $role = 'member';
            }
            
            if ($member_id) {
                $wpdb->insert($members_table, array(
                    'crew_id' => $crew_id,
                    'employee_id' => $member_id,
                    'role_in_crew' => $role,
                    'status' => 'active',
                    'joined_at' => current_time('mysql')
                ));
            }
        }
    }
    
    return new WP_REST_Response(array('id' => $crew_id, 'success' => true), 201);
}

/**
 * Get single crew
 */
function pi_team_timesheets_get_crew($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $table = $wpdb->prefix . 'pi_crm_crews';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    $crew = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last
         FROM {$table} c
         LEFT JOIN {$employees_table} e ON c.foreman_id = e.id
         WHERE c.id = %d",
        $id
    ), ARRAY_A);
    
    if (!$crew) {
        return new WP_Error('not_found', 'Crew not found', array('status' => 404));
    }
    
    // Get members
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT cm.*, e.first_name, e.last_name, e.role, e.trade
         FROM {$members_table} cm
         JOIN {$employees_table} e ON cm.employee_id = e.id
         WHERE cm.crew_id = %d AND cm.status = 'active'",
        $id
    ), ARRAY_A);
    
    $crew['members'] = $members;
    
    return new WP_REST_Response($crew, 200);
}

/**
 * Update crew
 */
function pi_team_timesheets_update_crew($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_crews';
    
    $update_data = array();
    if (isset($data['crew_name'])) $update_data['crew_name'] = sanitize_text_field($data['crew_name']);
    if (isset($data['trade_specialty'])) $update_data['trade_specialty'] = sanitize_text_field($data['trade_specialty']);
    if (isset($data['foreman_id'])) $update_data['foreman_id'] = intval($data['foreman_id']);
    if (isset($data['description'])) $update_data['description'] = sanitize_textarea_field($data['description']);
    if (isset($data['status'])) $update_data['status'] = sanitize_text_field($data['status']);
    
    $update_data['updated_at'] = current_time('mysql');
    
    $wpdb->update($table, $update_data, array('id' => $id));
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Get or create a crew for a specific job
 * Every job has exactly one crew
 */
function pi_team_timesheets_get_or_create_job_crew($job_id, $job_title = '') {
    global $wpdb;
    
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    
    // Check if crew already exists for this job
    $crew = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$crews_table} WHERE job_id = %d",
        $job_id
    ), ARRAY_A);
    
    if ($crew) {
        return $crew['id'];
    }
    
    // Create new crew for this job
    $crew_name = $job_title ? "Crew - {$job_title}" : "Crew - Job #{$job_id}";
    
    $wpdb->insert($crews_table, array(
        'job_id' => $job_id,
        'crew_name' => $crew_name,
        'trade_specialty' => '',
        'foreman_id' => null,
        'description' => "Auto-created crew for job #{$job_id}",
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ));
    
    $crew_id = $wpdb->insert_id;
    
    error_log("[PI Team Timesheets] Created crew ID {$crew_id} for job {$job_id}");
    
    return $crew_id;
}

/**
 * Sync team assignment to crew membership
 * When an employee is assigned to a job, add them to the job's crew
 */
function pi_team_timesheets_sync_team_to_crew($job_id, $employee_id, $role = 'member') {
    global $wpdb;
    
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    
    // Get or create the job's crew
    $crew_id = pi_team_timesheets_get_or_create_job_crew($job_id);
    
    // Check if already a member
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$members_table} WHERE crew_id = %d AND employee_id = %d",
        $crew_id, $employee_id
    ));
    
    if ($existing) {
        // Update to active if exists
        $wpdb->update($members_table, 
            array('status' => 'active', 'role_in_crew' => $role),
            array('id' => $existing)
        );
    } else {
        // Add as new member
        $wpdb->insert($members_table, array(
            'crew_id' => $crew_id,
            'employee_id' => $employee_id,
            'role_in_crew' => $role,
            'status' => 'active',
            'joined_at' => current_time('mysql')
        ));
    }
    
    return true;
}

/**
 * Remove employee from job's crew when unassigned from job
 */
function pi_team_timesheets_remove_from_job_crew($job_id, $employee_id) {
    global $wpdb;
    
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    
    // Find the job's crew
    $crew = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$crews_table} WHERE job_id = %d",
        $job_id
    ), ARRAY_A);
    
    if (!$crew) {
        return false;
    }
    
    // Set member as inactive
    $wpdb->update($members_table,
        array('status' => 'inactive'),
        array('crew_id' => $crew['id'], 'employee_id' => $employee_id)
    );
    
    return true;
}

/**
 * Get complete job crew with members, skills, and stats
 */
function pi_team_timesheets_get_job_crew_complete($job_id) {
    global $wpdb;
    
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    
    // Get crew
    $crew = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last
         FROM {$crews_table} c
         LEFT JOIN {$employees_table} e ON c.foreman_id = e.id
         WHERE c.job_id = %d",
        $job_id
    ), ARRAY_A);
    
    if (!$crew) {
        return null;
    }
    
    // Get detailed member information
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            e.id as employee_id,
            e.first_name,
            e.last_name,
            e.email,
            e.phone,
            e.role,
            e.trade,
            e.skill_level,
            e.hourly_rate,
            e.status as employee_status,
            cm.role_in_crew,
            cm.joined_at as crew_joined,
            ta.role as job_role,
            ta.skill_level as job_skill_level,
            ta.hourly_rate as job_hourly_rate,
            ta.start_date,
            ta.end_date,
            ta.allocation_percentage,
            ta.is_lead,
            ta.responsibilities,
            (SELECT SUM(total_hours) FROM {$timesheets_table} 
             WHERE worker_id = e.id AND job_id = %d AND status != 'rejected') as total_hours
         FROM {$members_table} cm
         JOIN {$employees_table} e ON cm.employee_id = e.id
         LEFT JOIN {$team_assignments_table} ta ON e.id = ta.employee_id AND ta.job_id = %d
         WHERE cm.crew_id = %d AND cm.status = 'active' AND e.status = 'active'
         ORDER BY ta.is_lead DESC, e.last_name, e.first_name",
        $job_id, $job_id, $crew['id']
    ), ARRAY_A);
    
    $crew['members'] = $members;
    $crew['member_count'] = count($members);
    
    // Calculate crew stats
    $crew['total_hours'] = array_sum(array_column($members, 'total_hours'));
    $crew['lead_count'] = count(array_filter($members, function($m) { return $m['is_lead']; }));
    $crew['trade_breakdown'] = array();
    
    foreach ($members as $member) {
        $trade = $member['trade'] ?: 'Unspecified';
        if (!isset($crew['trade_breakdown'][$trade])) {
            $crew['trade_breakdown'][$trade] = 0;
        }
        $crew['trade_breakdown'][$trade]++;
    }
    
    return $crew;
}

/**
 * Get all job crews (for global crews view)
 */
function pi_team_timesheets_get_all_job_crews() {
    global $wpdb;
    
    $crews_table = $wpdb->prefix . 'pi_crm_crews';
    $members_table = $wpdb->prefix . 'pi_crm_crew_members';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    
    // Get all crews with member counts and aggregated data
    $crews = $wpdb->get_results(
        "SELECT 
            c.*,
            e.first_name as foreman_first,
            e.last_name as foreman_last,
            COUNT(DISTINCT cm.employee_id) as member_count,
            COALESCE(SUM(ts.total_hours), 0) as total_hours
         FROM {$crews_table} c
         LEFT JOIN {$employees_table} e ON c.foreman_id = e.id
         LEFT JOIN {$members_table} cm ON c.id = cm.crew_id AND cm.status = 'active'
         LEFT JOIN {$timesheets_table} ts ON ts.job_id = c.job_id AND ts.status != 'rejected'
         WHERE c.status = 'active'
         GROUP BY c.id
         HAVING member_count > 0
         ORDER BY c.created_at DESC",
        ARRAY_A
    );
    
    return $crews ?: array();
}

/**
 * Get team assignments (job-specific)
 */
function pi_team_timesheets_get_team_assignments($request) {
    global $wpdb;
    
    $job_id = intval($request->get_param('job_id') ?? 0);
    $table = $wpdb->prefix . 'pi_crm_team_assignments';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    // If job_id is provided, get assignments for that specific job
    if ($job_id > 0) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ta.*, e.first_name, e.last_name, e.email, e.phone, e.trade as employee_trade
             FROM {$table} ta
             JOIN {$employees_table} e ON ta.employee_id = e.id
             WHERE ta.job_id = %d AND ta.status = 'active'
             ORDER BY ta.is_lead DESC, ta.role",
            $job_id
        ), ARRAY_A);
    } else {
        // Get all active assignments across all jobs (for global view)
        $results = $wpdb->get_results(
            "SELECT ta.*, e.first_name, e.last_name, e.email, e.phone, e.trade as employee_trade,
                    p.post_title as job_title
             FROM {$table} ta
             JOIN {$employees_table} e ON ta.employee_id = e.id
             LEFT JOIN {$wpdb->posts} p ON ta.job_id = p.ID
             WHERE ta.status = 'active'
             ORDER BY ta.job_id, ta.is_lead DESC, ta.role",
            ARRAY_A
        );
    }
    
    return new WP_REST_Response($results ?: array(), 200);
}

/**
 * Assign team member to job
 */
function pi_team_timesheets_assign_team_member($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    $job_id = intval($data['job_id'] ?? 0);
    $employee_id = intval($data['employee_id'] ?? $data['user_id'] ?? 0);
    
    if (!$job_id || !$employee_id) {
        return new WP_REST_Response(array('error' => 'Job ID and Employee ID are required'), 400);
    }
    
    // Check if already assigned
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE job_id = %d AND employee_id = %d AND status = 'active'",
        $job_id, $employee_id
    ));
    
    if ($existing) {
        return new WP_REST_Response(array('error' => 'Employee is already assigned to this job'), 409);
    }
    
    $insert_data = array(
        'job_id' => $job_id,
        'employee_id' => $employee_id,
        'role' => sanitize_text_field($data['role'] ?? 'Team Member'),
        'trade' => sanitize_text_field($data['trade'] ?? ''),
        'skill_level' => sanitize_text_field($data['skill_level'] ?? 'skilled'),
        'hourly_rate' => floatval($data['hourly_rate'] ?? 0),
        'start_date' => !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
        'end_date' => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
        'allocation_percentage' => intval($data['allocation_percentage'] ?? 100),
        'is_lead' => !empty($data['is_lead']) ? 1 : 0,
        'responsibilities' => sanitize_textarea_field($data['responsibilities'] ?? ''),
        'assigned_by' => get_current_user_id(),
        'status' => 'active',
        'created_at' => current_time('mysql')
    );
    
    $result = $wpdb->insert($table, $insert_data);
    $assignment_id = $wpdb->insert_id;
    
    if ($result === false || $assignment_id === 0) {
        error_log('[Team Timesheets] Failed to insert team assignment: ' . $wpdb->last_error);
        return new WP_REST_Response(array(
            'error' => 'Failed to create assignment: ' . $wpdb->last_error,
            'success' => false
        ), 500);
    }
    
    error_log("[Team Timesheets] Successfully created team assignment ID {$assignment_id} for employee {$employee_id} on job {$job_id}");
    
    // SYNC: Add employee to job's crew
    $role = $insert_data['is_lead'] ? 'lead' : 'member';
    pi_team_timesheets_sync_team_to_crew($job_id, $employee_id, $role);
    
    return new WP_REST_Response(array(
        'id' => $assignment_id,
        'success' => true
    ), 201);
}

/**
 * Update team assignment
 */
function pi_team_timesheets_update_team_assignment($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    $update_data = array();
    $fields = array('role', 'trade', 'skill_level', 'hourly_rate', 'start_date', 'end_date', 
                    'allocation_percentage', 'is_lead', 'responsibilities', 'status');
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = is_string($data[$field]) ? sanitize_text_field($data[$field]) : $data[$field];
        }
    }
    
    $update_data['updated_at'] = current_time('mysql');
    
    $wpdb->update($table, $update_data, array('id' => $id));
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Remove team member from job
 */
function pi_team_timesheets_remove_team_member($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // Get assignment details before removing
    $assignment = $wpdb->get_row($wpdb->prepare(
        "SELECT job_id, employee_id FROM {$table} WHERE id = %d",
        $id
    ), ARRAY_A);
    
    $wpdb->update($table, array(
        'status' => 'removed',
        'updated_at' => current_time('mysql')
    ), array('id' => $id));
    
    // SYNC: Remove employee from job's crew
    if ($assignment) {
        pi_team_timesheets_remove_from_job_crew($assignment['job_id'], $assignment['employee_id']);
    }
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Get available workers
 */
function pi_team_timesheets_get_available_workers($request) {
    global $wpdb;
    
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $cert_table = $wpdb->prefix . 'pi_crm_certifications';
    
    // Get all active employees
    $employees = $wpdb->get_results(
        "SELECT id, first_name, last_name, email, role, trade, hourly_rate, skill_level
         FROM {$employees_table}
         WHERE status = 'active'
         ORDER BY last_name, first_name",
        ARRAY_A
    );
    
    // Get certifications for each
    foreach ($employees as &$employee) {
        $certs = $wpdb->get_results($wpdb->prepare(
            "SELECT certification_name, expiry_date, status 
             FROM {$cert_table} 
             WHERE employee_id = %d AND status = 'valid' AND expiry_date > CURDATE()",
            $employee['id']
        ), ARRAY_A);
        
        $employee['certifications'] = $certs;
        $employee['name'] = $employee['first_name'] . ' ' . $employee['last_name'];
    }
    
    return new WP_REST_Response($employees, 200);
}

/**
 * Get timesheets - with job-specific filtering
 */
function pi_team_timesheets_get_timesheets($request) {
    global $wpdb;
    
    $job_id = $request->get_param('job_id');
    $employee_id = $request->get_param('employee_id');
    $start_date = $request->get_param('start_date');
    $end_date = $request->get_param('end_date');
    $status = $request->get_param('status');
    $is_global = $request->get_param('is_global');
    $all_jobs = $request->get_param('all_jobs');
    
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    // Debug logging
    error_log('[TeamTimesheets API] Get timesheets called with params: ' . json_encode([
        'job_id' => $job_id,
        'employee_id' => $employee_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'status' => $status,
        'is_global' => $is_global,
        'all_jobs' => $all_jobs
    ]));
    
    // CRITICAL FIX: Use subquery to avoid duplicate rows from JOIN issues
    // First, get all timesheets, then join with employees
    $sql = "SELECT t.*, e.first_name, e.last_name, e.employee_code 
            FROM {$table} t
            LEFT JOIN {$employees_table} e ON t.worker_id = e.id
            WHERE 1=1";
    $params = array();
    
    // CRITICAL: Handle all_jobs parameter - if set, return ALL timesheets from ALL jobs
    if ($all_jobs === '1' || $all_jobs === 1 || $all_jobs === true) {
        // Don't filter by job_id - return everything
        error_log('[TeamTimesheets API] all_jobs=1 - returning ALL timesheets from ALL jobs');
    }
    // Job filtering logic:
    // - If job_id is provided and > 0: get timesheets for that specific job
    // - If job_id === 0 or 'global': get global timesheets (no job or is_global_entry = 1)
    elseif ($job_id !== null && $job_id !== '') {
        $job_id = intval($job_id);
        if ($job_id > 0) {
            $sql .= " AND t.job_id = %d";
            $params[] = $job_id;
            error_log('[TeamTimesheets API] Filtering for specific job_id: ' . $job_id);
        } elseif ($job_id === 0 || $is_global === '1') {
            $sql .= " AND (t.job_id IS NULL OR t.job_id = 0 OR t.is_global_entry = 1)";
            error_log('[TeamTimesheets API] Filtering for global timesheets only');
        }
    } else {
        error_log('[TeamTimesheets API] No job_id filter - returning ALL timesheets from all jobs');
    }
    
    if ($employee_id) {
        $sql .= " AND t.worker_id = %d";
        $params[] = intval($employee_id);
    }
    
    if ($start_date) {
        $sql .= " AND t.work_date >= %s";
        $params[] = sanitize_text_field($start_date);
    }
    
    if ($end_date) {
        $sql .= " AND t.work_date <= %s";
        $params[] = sanitize_text_field($end_date);
    }
    
    if ($status) {
        $sql .= " AND t.status = %s";
        $params[] = sanitize_text_field($status);
    }
    
    $sql .= " GROUP BY t.id ORDER BY t.work_date DESC, t.created_at DESC";
    
    error_log('[TeamTimesheets API] SQL Query: ' . $sql);
    error_log('[TeamTimesheets API] SQL Params: ' . json_encode($params));
    
    if (!empty($params)) {
        $prepared_sql = $wpdb->prepare($sql, $params);
        error_log('[TeamTimesheets API] Prepared SQL: ' . $prepared_sql);
        $results = $wpdb->get_results($prepared_sql, ARRAY_A);
    } else {
        $results = $wpdb->get_results($sql, ARRAY_A);
    }
    
    // CRITICAL DEBUG: Log raw results before any processing
    if ($results) {
        $raw_ids = array_map(function($r) { return $r['id'] ?? 'NULL'; }, $results);
        error_log('[TeamTimesheets API] Raw result IDs: ' . json_encode($raw_ids));
        error_log('[TeamTimesheets API] Raw result count: ' . count($results));
        // DEBUG: Log each row's job_id to detect corruption
        $job_debug = array_map(function($r) { return 'ID:' . ($r['id'] ?? '?') . '=job:' . ($r['job_id'] ?? 'null'); }, $results);
        error_log('[TeamTimesheets API] Raw job mapping: ' . json_encode($job_debug));
    }
    
    // CRITICAL: Verify database state by getting direct count
    $db_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $db_max_id = $wpdb->get_var("SELECT MAX(id) FROM {$table}");
    error_log('[TeamTimesheets API] Database verification: total_rows=' . $db_count . ', max_id=' . $db_max_id);
    
    // Add job_code to each result for display
    // CRITICAL FIX: Don't use reference (&$row) to avoid memory aliasing bugs
    if ($results) {
        foreach ($results as $index => $row) {
            if (!empty($row['job_id'])) {
                $job_code = get_post_meta($row['job_id'], '_pi_job_ref', true);
                $results[$index]['job_code'] = $job_code ?: get_the_title($row['job_id']) ?: 'Job #' . $row['job_id'];
            } else {
                $results[$index]['job_code'] = null;
            }
        }
    }
    
    // Count total timesheets by job for debugging
    $total_count = count($results);
    $job_counts = array();
    foreach ($results as $row) {
        $jid = $row['job_id'] ?: 'null';
        $job_counts[$jid] = ($job_counts[$jid] ?? 0) + 1;
    }
    
    error_log('[TeamTimesheets API] Results: ' . $total_count . ' timesheets. By job: ' . json_encode($job_counts));
    
    return new WP_REST_Response($results ?: array(), 200);
}

/**
 * Create timesheet entry
 */
function pi_team_timesheets_create_timesheet($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    $employee_id = intval($data['employee_id'] ?? 0);
    $job_id = intval($data['job_id'] ?? 0);
    
    if (!$employee_id) {
        return new WP_REST_Response(array('message' => 'Employee ID is required'), 400);
    }
    
    if (empty($data['work_date'])) {
        return new WP_REST_Response(array('message' => 'Work date is required'), 400);
    }
    
    if (empty($data['start_time']) || empty($data['end_time'])) {
        return new WP_REST_Response(array('message' => 'Start and end time are required'), 400);
    }
    
    // Validate employee exists
    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$employees_table} WHERE id = %d",
        $employee_id
    ));
    
    if (!$employee) {
        return new WP_REST_Response(array('message' => 'Selected employee not found'), 400);
    }
    
    // STRICT: If job_id provided, verify employee is assigned to this job
    if ($job_id > 0) {
        $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
        $is_assigned = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$team_assignments_table} 
             WHERE job_id = %d AND employee_id = %d AND status = 'active'",
            $job_id, $employee_id
        ));
        
        if (!$is_assigned) {
            error_log("[PI Team Timesheets] BLOCKED: Employee {$employee_id} is not assigned to job {$job_id}");
            return new WP_REST_Response(array(
                'message' => 'Employee is not assigned to this job. Please add the employee to the job team first.',
                'error_code' => 'employee_not_assigned'
            ), 403);
        }
        
        error_log("[PI Team Timesheets] Verified: Employee {$employee_id} is assigned to job {$job_id}");
    }
    
    // Get employee details
    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name, hourly_rate FROM {$employees_table} WHERE id = %d",
        $employee_id
    ), ARRAY_A);
    
    // Calculate hours
    $start = strtotime($data['start_time']);
    $end = strtotime($data['end_time']);
    $break = intval($data['break_duration'] ?? 0) / 60; // convert minutes to hours
    $total_hours = (($end - $start) / 3600) - $break;
    
    $overtime_hours = $total_hours > 8 ? $total_hours - 8 : 0;
    $regular_hours = $total_hours - $overtime_hours;
    
    $insert_data = array(
        'job_id' => $job_id > 0 ? $job_id : null,
        'worker_id' => $employee_id,
        'work_date' => sanitize_text_field($data['work_date']),
        'start_time' => sanitize_text_field($data['start_time']),
        'end_time' => sanitize_text_field($data['end_time']),
        'break_duration' => intval($data['break_duration'] ?? 0),
        'total_hours' => max(0, $regular_hours),
        'overtime_hours' => max(0, $overtime_hours),
        'hourly_rate' => floatval($data['hourly_rate'] ?? $employee['hourly_rate'] ?? 0),
        'cost_code' => sanitize_text_field($data['cost_code'] ?? ''),
        'task_description' => sanitize_textarea_field($data['task_description'] ?? $data['notes'] ?? ''),
        'location' => sanitize_text_field($data['location'] ?? ''),
        'status' => 'pending',
        'created_at' => current_time('mysql')
    );
    
    error_log('[PI Team Timesheets] Inserting timesheet for worker_id=' . $employee_id . ', job_id=' . $job_id . ', date=' . $data['work_date']);
    error_log('[PI Team Timesheets] Insert data: ' . print_r($insert_data, true));
    
    // Get max ID before insert for comparison
    $max_id_before = $wpdb->get_var("SELECT MAX(id) FROM {$table}");
    error_log('[PI Team Timesheets] Max ID before insert: ' . ($max_id_before ?: 'NULL'));
    
    // Check if there's a row with this exact data already (potential duplicate prevention)
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE worker_id = %d AND job_id = %s AND work_date = %s AND start_time = %s AND end_time = %s ORDER BY id DESC LIMIT 1",
        $employee_id,
        $job_id > 0 ? $job_id : null,
        $data['work_date'],
        $data['start_time'],
        $data['end_time']
    ));
    if ($existing) {
        error_log('[PI Team Timesheets] WARNING: Potential duplicate detected. Existing ID: ' . $existing);
    }
    
    $result = $wpdb->insert($table, $insert_data);
    
    if ($result === false) {
        error_log('[PI Team Timesheets] Database insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(array('message' => 'Database error: ' . $wpdb->last_error), 500);
    }
    
    $new_id = $wpdb->insert_id;
    error_log('[PI Team Timesheets] Insert result: ' . ($result ? 'success' : 'failed') . ', Insert ID: ' . ($new_id ?: 'NULL'));
    
    if (!$new_id) {
        error_log('[PI Team Timesheets] No insert ID returned - checking last query');
        error_log('[PI Team Timesheets] Last query: ' . $wpdb->last_query);
        return new WP_REST_Response(array('message' => 'Failed to create timesheet entry - no ID returned'), 500);
    }
    
    // Verify the insert by reading back the row
    $verify = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $new_id), ARRAY_A);
    if ($verify) {
        error_log('[PI Team Timesheets] Verified new row: ID=' . $verify['id'] . ', worker=' . $verify['worker_id'] . ', job=' . $verify['job_id']);
    } else {
        error_log('[PI Team Timesheets] WARNING: Could not verify new row with ID ' . $new_id);
    }
    
    return new WP_REST_Response(array(
        'id' => $new_id,
        'total_hours' => $total_hours,
        'success' => true,
        'message' => 'Timesheet entry created successfully'
    ), 201);
}

/**
 * Get single timesheet
 */
function pi_team_timesheets_get_timesheet($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    $timesheet = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, e.first_name, e.last_name 
         FROM {$table} t
         JOIN {$employees_table} e ON t.worker_id = e.id
         WHERE t.id = %d",
        $id
    ), ARRAY_A);
    
    if (!$timesheet) {
        return new WP_Error('not_found', 'Timesheet not found', array('status' => 404));
    }
    
    return new WP_REST_Response($timesheet, 200);
}

/**
 * Update timesheet
 */
function pi_team_timesheets_update_timesheet($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    
    $update_data = array();
    
    if (isset($data['start_time'])) $update_data['start_time'] = sanitize_text_field($data['start_time']);
    if (isset($data['end_time'])) $update_data['end_time'] = sanitize_text_field($data['end_time']);
    if (isset($data['break_duration'])) $update_data['break_duration'] = intval($data['break_duration']);
    if (isset($data['task_description'])) $update_data['task_description'] = sanitize_textarea_field($data['task_description']);
    if (isset($data['cost_code'])) $update_data['cost_code'] = sanitize_text_field($data['cost_code']);
    if (isset($data['status'])) $update_data['status'] = sanitize_text_field($data['status']);
    if (isset($data['notes'])) $update_data['notes'] = sanitize_textarea_field($data['notes']);
    
    // Recalculate hours if time fields changed
    if (isset($data['start_time']) || isset($data['end_time']) || isset($data['break_duration'])) {
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        $start = strtotime($update_data['start_time'] ?? $current['start_time']);
        $end = strtotime($update_data['end_time'] ?? $current['end_time']);
        $break = ($update_data['break_duration'] ?? $current['break_duration']) / 60;
        $total_hours = (($end - $start) / 3600) - $break;
        
        $update_data['overtime_hours'] = $total_hours > 8 ? $total_hours - 8 : 0;
        $update_data['total_hours'] = $total_hours - $update_data['overtime_hours'];
    }
    
    $update_data['updated_at'] = current_time('mysql');
    
    $wpdb->update($table, $update_data, array('id' => $id));
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Delete timesheet
 */
function pi_team_timesheets_delete_timesheet($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    
    $wpdb->delete($table, array('id' => $id));
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Get timesheet summary
 */
function pi_team_timesheets_get_timesheet_summary($request) {
    global $wpdb;
    
    $job_id = intval($request->get_param('job_id') ?? 0);
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    // Build where clause
    $where = "work_date BETWEEN '{$week_start}' AND '{$week_end}'";
    if ($job_id > 0) {
        $where .= " AND job_id = {$job_id}";
    }
    
    $summary = $wpdb->get_row(
        "SELECT 
            COUNT(*) as total_entries,
            SUM(total_hours) as total_hours,
            SUM(overtime_hours) as overtime_hours,
            COUNT(DISTINCT employee_id) as unique_workers
         FROM {$table}
         WHERE {$where} AND status != 'rejected'",
        ARRAY_A
    );
    
    // Get by employee
    $by_employee = $wpdb->get_results(
        "SELECT 
            t.worker_id as employee_id,
            e.first_name,
            e.last_name,
            SUM(t.total_hours) as hours,
            COUNT(*) as entries
         FROM {$table} t
         JOIN {$employees_table} e ON t.worker_id = e.id
         WHERE {$where} AND t.status != 'rejected'
         GROUP BY t.worker_id
         ORDER BY hours DESC",
        ARRAY_A
    );
    
    return new WP_REST_Response(array(
        'summary' => $summary,
        'by_employee' => $by_employee,
        'week_start' => $week_start,
        'week_end' => $week_end
    ), 200);
}

/**
 * Get clock status
 */
function pi_team_timesheets_get_clock_status($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $table = $wpdb->prefix . 'pi_crm_clock_status';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    // Get employee ID for current user
    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$employees_table} WHERE user_id = %d AND status = 'active'",
        $user_id
    ), ARRAY_A);
    
    if (!$employee) {
        return new WP_REST_Response(array(
            'clocked_in' => false,
            'message' => 'No active employee record found'
        ), 200);
    }
    
    $employee_id = $employee['id'];
    
    $status = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE employee_id = %d",
        $employee_id
    ), ARRAY_A);
    
    if (!$status) {
        // Create initial status
        $wpdb->insert($table, array(
            'employee_id' => $employee_id,
            'status' => 'clocked_out',
            'created_at' => current_time('mysql')
        ));
        
        return new WP_REST_Response(array(
            'clocked_in' => false,
            'employee_id' => $employee_id
        ), 200);
    }
    
    return new WP_REST_Response(array(
        'clocked_in' => $status['status'] === 'clocked_in',
        'on_break' => $status['status'] === 'on_break',
        'clock_in_time' => $status['clock_in_time'],
        'job_id' => $status['job_id'],
        'employee_id' => $employee_id,
        'total_break_minutes' => $status['total_break_minutes']
    ), 200);
}

/**
 * Clock in
 */
function pi_team_timesheets_clock_in($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_clock_status';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    $user_id = get_current_user_id();
    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$employees_table} WHERE user_id = %d AND status = 'active'",
        $user_id
    ), ARRAY_A);
    
    if (!$employee) {
        return new WP_REST_Response(array('error' => 'No active employee record found'), 400);
    }
    
    $employee_id = $employee['id'];
    $job_id = intval($data['job_id'] ?? 0);
    
    $update_data = array(
        'status' => 'clocked_in',
        'clock_in_time' => current_time('mysql'),
        'clock_out_time' => null,
        'break_start_time' => null,
        'total_break_minutes' => 0,
        'job_id' => $job_id > 0 ? $job_id : null,
        'gps_lat' => $data['gps_lat'] ?? null,
        'gps_lng' => $data['gps_lng'] ?? null,
        'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        'updated_at' => current_time('mysql')
    );
    
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE employee_id = %d", $employee_id));
    
    if ($existing) {
        $wpdb->update($table, $update_data, array('employee_id' => $employee_id));
    } else {
        $update_data['employee_id'] = $employee_id;
        $update_data['created_at'] = current_time('mysql');
        $wpdb->insert($table, $update_data);
    }
    
    return new WP_REST_Response(array('success' => true, 'clocked_in' => true), 200);
}

/**
 * Clock out
 */
function pi_team_timesheets_clock_out($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_clock_status';
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    
    $user_id = get_current_user_id();
    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT id, hourly_rate FROM {$employees_table} WHERE user_id = %d AND status = 'active'",
        $user_id
    ), ARRAY_A);
    
    if (!$employee) {
        return new WP_REST_Response(array('error' => 'No active employee record found'), 400);
    }
    
    $employee_id = $employee['id'];
    
    // Get current status
    $status = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE employee_id = %d",
        $employee_id
    ), ARRAY_A);
    
    if (!$status || $status['status'] !== 'clocked_in') {
        return new WP_REST_Response(array('error' => 'Not currently clocked in'), 400);
    }
    
    $clock_in = strtotime($status['clock_in_time']);
    $clock_out = time();
    $total_minutes = ($clock_out - $clock_in) / 60;
    $break_minutes = $status['total_break_minutes'] ?? 0;
    $work_minutes = $total_minutes - $break_minutes;
    $total_hours = $work_minutes / 60;
    
    // Create timesheet entry
    $insert_data = array(
        'job_id' => $status['job_id'],
        'employee_id' => $employee_id,
        'work_date' => date('Y-m-d'),
        'start_time' => date('H:i:s', $clock_in),
        'end_time' => date('H:i:s', $clock_out),
        'break_duration' => $break_minutes,
        'total_hours' => max(0, min($total_hours, 8)),
        'overtime_hours' => max(0, $total_hours - 8),
        'hourly_rate' => $employee['hourly_rate'] ?? 0,
        'task_description' => sanitize_textarea_field($data['notes'] ?? ''),
        'status' => 'pending',
        'is_global_entry' => empty($status['job_id']) ? 1 : 0,
        'created_at' => current_time('mysql')
    );
    
    $wpdb->insert($timesheets_table, $insert_data);
    
    // Update clock status
    $wpdb->update($table, array(
        'status' => 'clocked_out',
        'clock_out_time' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ), array('employee_id' => $employee_id));
    
    return new WP_REST_Response(array(
        'success' => true,
        'timesheet_id' => $wpdb->insert_id,
        'total_hours' => round($total_hours, 2)
    ), 200);
}

/**
 * Get on-site workers - JOB SPECIFIC
 * When job_id provided, only shows workers clocked in for that job
 */
function pi_team_timesheets_get_on_site_workers($request) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pi_crm_clock_status';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    if ($job_id) {
        // STRICT: Only employees assigned to this job who are clocked in
        $workers = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*, e.first_name, e.last_name, e.role, e.trade
             FROM {$table} cs
             JOIN {$employees_table} e ON cs.employee_id = e.id
             INNER JOIN {$team_assignments_table} ta ON cs.employee_id = ta.employee_id
             WHERE cs.status = 'clocked_in' AND cs.job_id = %d AND ta.job_id = %d
             ORDER BY cs.clock_in_time DESC",
            $job_id, $job_id
        ), ARRAY_A);
    } else {
        // Global view: all clocked in workers
        $workers = $wpdb->get_results(
            "SELECT cs.*, e.first_name, e.last_name, e.role, e.trade
             FROM {$table} cs
             JOIN {$employees_table} e ON cs.employee_id = e.id
             WHERE cs.status = 'clocked_in'
             ORDER BY cs.clock_in_time DESC",
            ARRAY_A
        );
    }
    
    return new WP_REST_Response($workers ?: array(), 200);
}

/**
 * Get pending approvals - JOB SPECIFIC
 * When job_id provided, only shows pending approvals for that job
 */
function pi_team_timesheets_get_pending_approvals($request) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $user_id = get_current_user_id();
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    // Build job-specific WHERE clause
    $job_where = $job_id ? " AND t.job_id = {$job_id}" : "";
    
    // Check if user can approve
    $can_approve = current_user_can('administrator') || current_user_can('manage_options');
    
    if (!$can_approve) {
        // Check employee permissions
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT can_approve_timesheets FROM {$employees_table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$employee || !$employee['can_approve_timesheets']) {
            return new WP_REST_Response(array(
                'approvals' => array(),
                'can_approve' => false
            ), 200);
        }
    }
    
    $approvals = $wpdb->get_results(
        "SELECT t.*, e.first_name, e.last_name, e.employee_code
         FROM {$table} t
         JOIN {$employees_table} e ON t.worker_id = e.id
         WHERE t.status = 'pending'" . $job_where . "
         ORDER BY t.work_date DESC, t.created_at DESC
         LIMIT 50",
        ARRAY_A
    );
    
    return new WP_REST_Response(array(
        'approvals' => $approvals,
        'can_approve' => true,
        'count' => count($approvals)
    ), 200);
}

/**
 * Approve timesheet
 */
function pi_team_timesheets_approve_timesheet($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    
    $wpdb->update($table, array(
        'status' => 'approved',
        'approved_by' => get_current_user_id(),
        'approved_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ), array('id' => $id));
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Reject timesheet
 */
function pi_team_timesheets_reject_timesheet($request) {
    global $wpdb;
    
    $id = intval($request->get_param('id'));
    $data = $request->get_json_params();
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    
    $wpdb->update($table, array(
        'status' => 'rejected',
        'rejection_reason' => sanitize_textarea_field($data['reason'] ?? ''),
        'updated_at' => current_time('mysql')
    ), array('id' => $id));
    
    return new WP_REST_Response(array('success' => true), 200);
}

/**
 * Bulk approval
 */
function pi_team_timesheets_bulk_approval($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    $action = sanitize_text_field($data['action']);
    $ids = array_map('intval', $data['ids'] ?? array());
    
    if (empty($ids)) {
        return new WP_Error('no_ids', 'No timesheet IDs provided', array('status' => 400));
    }
    
    $table = $wpdb->prefix . 'pi_crm_timesheets';
    
    foreach ($ids as $id) {
        $wpdb->update($table, array(
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'approved_by' => $action === 'approve' ? get_current_user_id() : null,
            'approved_at' => $action === 'approve' ? current_time('mysql') : null,
            'updated_at' => current_time('mysql')
        ), array('id' => $id));
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'affected' => count($ids)
    ), 200);
}

/**
 * Get dashboard stats - JOB SPECIFIC
 * When job_id is provided, returns stats only for that job
 * When no job_id, returns global stats (all jobs)
 */
function pi_team_timesheets_get_dashboard_stats($request) {
    global $wpdb;
    
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    $clock_table = $wpdb->prefix . 'pi_crm_clock_status';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // Get job_id parameter (null if not provided = global view)
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    // Build WHERE conditions based on job_id
    $job_where_employees = $job_id ? " AND job_id = {$job_id}" : "";
    $job_where_timesheets = $job_id ? " AND job_id = {$job_id}" : "";
    $job_where_clock = $job_id ? " AND job_id = {$job_id}" : "";
    
    // Total employees - if job_id provided, count ONLY employees assigned via team_assignments
    if ($job_id) {
        // STRICT: Only count employees explicitly added via Team Members "Add Member" button
        // This uses ONLY the team_assignments table, NOT the employees.job_id column
        $total_employees = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ta.employee_id) FROM {$team_assignments_table} ta 
             JOIN {$employees_table} e ON ta.employee_id = e.id 
             WHERE ta.job_id = %d AND e.status = 'active'",
            $job_id
        ));
    } else {
        // Global view: count all active employees
        $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$employees_table} WHERE status = 'active'");
    }
    
    // Currently on site - STRICT: only count employees assigned to this job who are clocked in
    if ($job_id) {
        $on_site = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT c.employee_id) FROM {$clock_table} c
             INNER JOIN {$team_assignments_table} ta ON c.employee_id = ta.employee_id
             WHERE c.status = 'clocked_in' AND c.job_id = %d AND ta.job_id = %d",
            $job_id, $job_id
        ));
    } else {
        $on_site_sql = "SELECT COUNT(*) FROM {$clock_table} WHERE status = 'clocked_in'";
        $on_site = $wpdb->get_var($on_site_sql);
    }
    
    // Hours this week - get regular, overtime, and total (job-specific or global)
    $week_stats_sql = "SELECT 
            SUM(total_hours) as regular_hours,
            SUM(overtime_hours) as overtime_hours,
            SUM(total_hours + overtime_hours) as total_hours
         FROM {$timesheets_table} 
         WHERE work_date BETWEEN '{$week_start}' AND '{$week_end}' AND status != 'rejected'" . $job_where_timesheets;
    $week_stats = $wpdb->get_row($week_stats_sql, ARRAY_A);
    
    $week_regular = floatval($week_stats['regular_hours'] ?? 0);
    $week_ot = floatval($week_stats['overtime_hours'] ?? 0);
    $week_total = floatval($week_stats['total_hours'] ?? 0);
    
    // Pending approvals - job-specific
    $pending_sql = "SELECT COUNT(*) FROM {$timesheets_table} WHERE status = 'pending'" . $job_where_timesheets;
    $pending_approvals = $wpdb->get_var($pending_sql);
    
    // Labor cost this week - calculate from timesheets with their hourly rates (job-specific)
    $labor_cost_sql = "SELECT SUM((total_hours + COALESCE(overtime_hours, 0)) * hourly_rate) 
         FROM {$timesheets_table} 
         WHERE work_date BETWEEN '{$week_start}' AND '{$week_end}' AND status != 'rejected'" . $job_where_timesheets;
    $labor_cost = $wpdb->get_var($labor_cost_sql);
    
    return new WP_REST_Response(array(
        'total_employees' => intval($total_employees),
        'on_site' => intval($on_site),
        'week_hours' => round($week_total, 1),
        'week_regular_hours' => round($week_regular, 1),
        'week_overtime_hours' => round($week_ot, 1),
        'overtime_hours' => round($week_ot, 1),
        'pending_approvals' => intval($pending_approvals),
        'labor_cost' => floatval($labor_cost ?: 0),
        'week_start' => date('M j', strtotime($week_start)),
        'week_end' => date('M j', strtotime($week_end)),
        'job_id' => $job_id  // Return job_id for verification
    ), 200);
}

/**
 * Get weekly hours chart data - JOB SPECIFIC
 */
function pi_team_timesheets_get_weekly_hours($request) {
    global $wpdb;
    
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    // Build job-specific WHERE clause
    $job_where = $job_id ? " AND job_id = {$job_id}" : "";
    
    $daily = $wpdb->get_results(
        "SELECT 
            work_date,
            SUM(total_hours) as regular_hours,
            SUM(COALESCE(overtime_hours, 0)) as overtime_hours,
            SUM(total_hours + COALESCE(overtime_hours, 0)) as total_hours,
            COUNT(*) as entries
         FROM {$timesheets_table}
         WHERE work_date BETWEEN '{$week_start}' AND '{$week_end}' AND status != 'rejected'" . $job_where . "
         GROUP BY work_date
         ORDER BY work_date",
        ARRAY_A
    );
    
    // Fill in missing days with 0
    $days = array();
    $current = strtotime($week_start);
    $end = strtotime($week_end);
    
    while ($current <= $end) {
        $date = date('Y-m-d', $current);
        $found = false;
        foreach ($daily as $d) {
            if ($d['work_date'] === $date) {
                $days[] = array(
                    'work_date' => $date,
                    'day_name' => date('D', $current),
                    'regular_hours' => floatval($d['regular_hours']),
                    'overtime_hours' => floatval($d['overtime_hours']),
                    'total_hours' => floatval($d['total_hours']),
                    'entries' => intval($d['entries'])
                );
                $found = true;
                break;
            }
        }
        if (!$found) {
            $days[] = array(
                'work_date' => $date,
                'day_name' => date('D', $current),
                'regular_hours' => 0,
                'overtime_hours' => 0,
                'total_hours' => 0,
                'entries' => 0
            );
        }
        $current = strtotime('+1 day', $current);
    }
    
    return new WP_REST_Response(array('daily' => $days), 200);
}

/**
 * Get activity feed - JOB SPECIFIC
 * When job_id is provided, returns activity only for that job
 * When no job_id, returns global activity (all jobs)
 */
function pi_team_timesheets_get_activity($request) {
    global $wpdb;
    
    $limit = intval($request->get_param('limit') ?? 50);
    $prefix = $wpdb->prefix . 'pi_crm_';
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    error_log('[PI Team Timesheets] pi_team_timesheets_get_activity called - job_id: ' . ($job_id ?: 'null') . ', limit: ' . $limit);
    
    // Build job-specific WHERE clause
    $job_where_timesheets = $job_id ? " AND t.job_id = {$job_id}" : "";
    $job_where_comms = $job_id ? " AND c.job_id = {$job_id}" : "";
    $job_where_docs = $job_id ? " AND d.job_id = {$job_id}" : "";
    $job_where_safety = $job_id ? " AND si.job_id = {$job_id}" : "";
    
    // Build timeline from activity sources (job-specific or global)
    $timeline = array();
    
    // 1. Timesheet entries (job-specific or all jobs)
    $timesheets = $wpdb->get_results(
        "SELECT 
            t.id,
            t.work_date,
            t.total_hours,
            t.status,
            t.created_at as timestamp,
            t.job_id,
            j.job_name as job_title,
            e.first_name,
            e.last_name,
            'timesheet' as type,
            'clock' as icon,
            CONCAT('logged ', t.total_hours, ' hours') as description,
            e.avatar_color
         FROM {$prefix}timesheets t
         JOIN {$prefix}employees e ON t.worker_id = e.id
         LEFT JOIN {$prefix}jobs j ON t.job_id = j.id
         WHERE 1=1" . $job_where_timesheets . "
         ORDER BY t.created_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    error_log('[PI Team Timesheets] Found ' . count($timesheets) . ' timesheets for activity timeline');
    foreach ($timesheets as $t) {
        $timeline[] = array(
            'id' => 'ts_' . $t['id'],
            'type' => 'timesheet',
            'icon' => 'clock',
            'title' => $t['first_name'] . ' ' . $t['last_name'] . ' logged ' . $t['total_hours'] . ' hours',
            'description' => $t['job_title'] ? ' on ' . $t['job_title'] : '',
            'timestamp' => $t['timestamp'],
            'status' => $t['status'],
            'job_id' => $t['job_id'],
            'job_title' => $t['job_title'],
            'user_name' => $t['first_name'] . ' ' . $t['last_name'],
            'avatar_color' => $t['avatar_color'] ?? null,
            'metadata' => array(
                'hours' => $t['total_hours'],
                'work_date' => $t['work_date']
            )
        );
    }
    
    // 2. Communications (job-specific or all jobs)
    $comms = $wpdb->get_results(
        "SELECT 
            c.id, c.subject, c.created_at, c.status, c.job_id,
            j.job_name as job_title,
            u.display_name as user_name
         FROM {$prefix}communications c
         LEFT JOIN {$prefix}jobs j ON c.job_id = j.id
         LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID
         WHERE 1=1" . $job_where_comms . "
         ORDER BY c.created_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    foreach ($comms as $c) {
        $timeline[] = array(
            'id' => 'comm_' . $c['id'],
            'type' => 'communication',
            'icon' => 'message-square',
            'title' => 'Communication: ' . ($c['subject'] ?: 'No subject'),
            'description' => $c['job_title'] ? 'on ' . $c['job_title'] : 'General',
            'timestamp' => $c['created_at'],
            'status' => $c['status'],
            'job_id' => $c['job_id'],
            'job_title' => $c['job_title'],
            'user_name' => $c['user_name'] ?: 'System',
            'metadata' => array()
        );
    }
    
    // 3. Documents uploaded (job-specific or all jobs)
    $docs = $wpdb->get_results(
        "SELECT 
            d.id, d.title, d.file_name, d.uploaded_at as created_at, 
            d.category as status, d.job_id,
            j.job_name as job_title,
            u.display_name as user_name
         FROM {$prefix}documents d
         LEFT JOIN {$prefix}jobs j ON d.job_id = j.id
         LEFT JOIN {$wpdb->users} u ON d.uploaded_by = u.ID
         WHERE d.is_latest = 1" . $job_where_docs . "
         ORDER BY d.uploaded_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    foreach ($docs as $d) {
        $timeline[] = array(
            'id' => 'doc_' . $d['id'],
            'type' => 'document',
            'icon' => 'file-text',
            'title' => $d['title'] ?: $d['file_name'],
            'description' => $d['job_title'] ? 'uploaded to ' . $d['job_title'] : 'General upload',
            'timestamp' => $d['created_at'],
            'status' => $d['status'],
            'job_id' => $d['job_id'],
            'job_title' => $d['job_title'],
            'user_name' => $d['user_name'] ?: 'System',
            'metadata' => array(
                'category' => $d['status']
            )
        );
    }
    
    // 4. Safety incidents (job-specific or all jobs)
    $incidents = $wpdb->get_results(
        "SELECT 
            si.id, si.incident_type, si.severity, si.created_at, 
            si.status, si.job_id,
            j.job_name as job_title,
            u.display_name as user_name
         FROM {$prefix}safety_incidents si
         LEFT JOIN {$prefix}jobs j ON si.job_id = j.id
         LEFT JOIN {$wpdb->users} u ON si.reported_by = u.ID
         WHERE 1=1" . $job_where_safety . "
         ORDER BY si.created_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    foreach ($incidents as $i) {
        $timeline[] = array(
            'id' => 'safety_' . $i['id'],
            'type' => 'safety',
            'icon' => 'alert-triangle',
            'title' => 'Safety: ' . $i['incident_type'],
            'description' => $i['job_title'] ? 'at ' . $i['job_title'] . ' (' . $i['severity'] . ')' : $i['severity'],
            'timestamp' => $i['created_at'],
            'status' => $i['status'],
            'job_id' => $i['job_id'],
            'job_title' => $i['job_title'],
            'user_name' => $i['user_name'] ?: 'System',
            'severity' => $i['severity'],
            'metadata' => array(
                'severity' => $i['severity']
            )
        );
    }
    
    // 5. Photos uploaded across all jobs
    $photos = $wpdb->get_results(
        "SELECT 
            p.id, p.title, p.file_name, p.taken_at as created_at, 
            p.category as status, p.job_id,
            j.job_name as job_title,
            u.display_name as user_name
         FROM {$prefix}job_photos p
         LEFT JOIN {$prefix}jobs j ON p.job_id = j.id
         LEFT JOIN {$wpdb->users} u ON p.uploaded_by = u.ID
         ORDER BY p.taken_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    foreach ($photos as $p) {
        $timeline[] = array(
            'id' => 'photo_' . $p['id'],
            'type' => 'photo',
            'icon' => 'camera',
            'title' => $p['title'] ?: 'Photo',
            'description' => $p['job_title'] ? 'from ' . $p['job_title'] : '',
            'timestamp' => $p['created_at'],
            'status' => $p['status'],
            'job_id' => $p['job_id'],
            'job_title' => $p['job_title'],
            'user_name' => $p['user_name'] ?: 'System',
            'metadata' => array(
                'category' => $p['status']
            )
        );
    }
    
    // 6. Change orders across all jobs
    $cos = $wpdb->get_results(
        "SELECT 
            co.id, co.co_number, co.title, co.created_at, 
            co.status, co.job_id,
            j.job_name as job_title,
            u.display_name as user_name
         FROM {$prefix}change_orders co
         LEFT JOIN {$prefix}jobs j ON co.job_id = j.id
         LEFT JOIN {$wpdb->users} u ON co.created_by = u.ID
         ORDER BY co.created_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    foreach ($cos as $co) {
        $timeline[] = array(
            'id' => 'co_' . $co['id'],
            'type' => 'change_order',
            'icon' => 'edit-3',
            'title' => 'CO ' . ($co['co_number'] ?: '#') . ': ' . $co['title'],
            'description' => $co['job_title'] ? 'on ' . $co['job_title'] : '',
            'timestamp' => $co['created_at'],
            'status' => $co['status'],
            'job_id' => $co['job_id'],
            'job_title' => $co['job_title'],
            'user_name' => $co['user_name'] ?: 'System',
            'metadata' => array()
        );
    }
    
    // 7. RFI activity across all jobs
    $rfis = $wpdb->get_results(
        "SELECT 
            r.id, r.rfi_number, r.title, r.created_at, 
            r.status, r.job_id,
            j.job_name as job_title,
            u.display_name as user_name
         FROM {$prefix}rfi r
         LEFT JOIN {$prefix}jobs j ON r.job_id = j.id
         LEFT JOIN {$wpdb->users} u ON r.created_by = u.ID
         ORDER BY r.created_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    foreach ($rfis as $r) {
        $timeline[] = array(
            'id' => 'rfi_' . $r['id'],
            'type' => 'rfi',
            'icon' => 'help-circle',
            'title' => 'RFI ' . ($r['rfi_number'] ?: '#') . ': ' . $r['title'],
            'description' => $r['job_title'] ? 'on ' . $r['job_title'] : '',
            'timestamp' => $r['created_at'],
            'status' => $r['status'],
            'job_id' => $r['job_id'],
            'job_title' => $r['job_title'],
            'user_name' => $r['user_name'] ?: 'System',
            'metadata' => array()
        );
    }
    
    // 8. Submittals across all jobs
    $submittals = $wpdb->get_results(
        "SELECT 
            s.id, s.submittal_number, s.title, s.created_at, 
            s.status, s.job_id,
            j.job_name as job_title,
            u.display_name as user_name
         FROM {$prefix}submittals s
         LEFT JOIN {$prefix}jobs j ON s.job_id = j.id
         LEFT JOIN {$wpdb->users} u ON s.created_by = u.ID
         ORDER BY s.created_at DESC
         LIMIT {$limit}",
        ARRAY_A
    );
    foreach ($submittals as $s) {
        $timeline[] = array(
            'id' => 'sub_' . $s['id'],
            'type' => 'submittal',
            'icon' => 'check-circle',
            'title' => 'Submittal ' . ($s['submittal_number'] ?: '#') . ': ' . $s['title'],
            'description' => $s['job_title'] ? 'for ' . $s['job_title'] : '',
            'timestamp' => $s['created_at'],
            'status' => $s['status'],
            'job_id' => $s['job_id'],
            'job_title' => $s['job_title'],
            'user_name' => $s['user_name'] ?: 'System',
            'metadata' => array()
        );
    }
    
    // Sort all activity by timestamp descending
    usort($timeline, function($a, $b) {
        $time_a = strtotime($a['timestamp']);
        $time_b = strtotime($b['timestamp']);
        return $time_b - $time_a;
    });
    
    // Limit final results
    $timeline = array_slice($timeline, 0, $limit);
    
    error_log('[PI Team Timesheets] Returning ' . count($timeline) . ' activity items');
    
    return new WP_REST_Response($timeline, 200);
}

/**
 * Get trade distribution for pie chart - JOB SPECIFIC
 * When job_id provided, only shows trades of employees assigned to that job
 */
function pi_team_timesheets_get_trade_distribution($request) {
    global $wpdb;
    
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    // Get trade distribution - job-specific or global
    if ($job_id) {
        // STRICT: Only employees assigned to this job via team_assignments
        $distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COALESCE(e.trade, 'Unspecified') as trade,
                COUNT(DISTINCT e.id) as count
             FROM {$employees_table} e
             INNER JOIN {$team_assignments_table} ta ON e.id = ta.employee_id
             WHERE ta.job_id = %d AND e.status = 'active'
             GROUP BY e.trade
             ORDER BY count DESC",
            $job_id
        ), ARRAY_A);
    } else {
        // Global: All active employees
        $distribution = $wpdb->get_results(
            "SELECT 
                COALESCE(e.trade, 'Unspecified') as trade,
                COUNT(DISTINCT e.id) as count
             FROM {$employees_table} e
             WHERE e.status = 'active'
             GROUP BY e.trade
             ORDER BY count DESC",
            ARRAY_A
        );
    }
    
    // If no employees, return empty array
    if (empty($distribution)) {
        return new WP_REST_Response(array(), 200);
    }
    
    return new WP_REST_Response($distribution, 200);
}

/**
 * Get attendance trends - JOB SPECIFIC
 * When job_id provided, only counts employees assigned to that job
 */
function pi_team_timesheets_get_attendance_trends($request) {
    global $wpdb;
    
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    $period = $request->get_param('period') ?: 'month';
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    $data = array();
    
    // Build job-specific WHERE clauses
    $job_where_timesheets = $job_id ? " AND job_id = {$job_id}" : "";
    
    if ($period === 'week') {
        // Last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $day_name = date('D', strtotime($date));
            
            // Total employees - job-specific or global
            if ($job_id) {
                $total_employees = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT ta.employee_id) FROM {$team_assignments_table} ta 
                     JOIN {$employees_table} e ON ta.employee_id = e.id 
                     WHERE ta.job_id = %d AND e.status = 'active'",
                    $job_id
                ));
            } else {
                $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$employees_table} WHERE status = 'active'");
            }
            
            $present = $wpdb->get_var(
                "SELECT COUNT(DISTINCT worker_id) FROM {$timesheets_table} 
                 WHERE work_date = '{$date}' AND status != 'rejected'" . $job_where_timesheets
            );
            
            $rate = $total_employees > 0 ? round(($present / $total_employees) * 100, 1) : 0;
            
            $data[] = array(
                'label' => $day_name,
                'rate' => $rate,
                'present' => intval($present),
                'total' => intval($total_employees)
            );
        }
    } else {
        // Last 4 weeks for month view
        for ($i = 3; $i >= 0; $i--) {
            $week_start = date('Y-m-d', strtotime("monday -{$i} week"));
            $week_end = date('Y-m-d', strtotime("sunday -{$i} week"));
            
            // Total employees - job-specific or global
            if ($job_id) {
                $total_employees = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT ta.employee_id) FROM {$team_assignments_table} ta 
                     JOIN {$employees_table} e ON ta.employee_id = e.id 
                     WHERE ta.job_id = %d AND e.status = 'active'",
                    $job_id
                ));
            } else {
                $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$employees_table} WHERE status = 'active'");
            }
            
            $present = $wpdb->get_var(
                "SELECT COUNT(DISTINCT worker_id) FROM {$timesheets_table} 
                 WHERE work_date BETWEEN '{$week_start}' AND '{$week_end}' AND status != 'rejected'" . $job_where_timesheets
            );
            
            $rate = $total_employees > 0 ? round(($present / $total_employees) * 100, 1) : 0;
            
            $data[] = array(
                'label' => 'Week ' . (4 - $i),
                'rate' => $rate,
                'present' => intval($present),
                'total' => intval($total_employees),
                'week_start' => $week_start,
                'week_end' => $week_end
            );
        }
    }
    
    return new WP_REST_Response($data, 200);
}

/**
 * Get labor cost data - JOB SPECIFIC
 * When job_id provided, only includes costs for that job
 */
function pi_team_timesheets_get_labor_cost($request) {
    global $wpdb;
    
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    $period = $request->get_param('period') ?: 'month';
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    // Build job-specific WHERE clause
    $job_where = $job_id ? " AND job_id = {$job_id}" : "";
    
    $data = array();
    
    if ($period === 'week') {
        // Last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $day_name = date('D', strtotime($date));
            
            $cost = $wpdb->get_var(
                "SELECT SUM((total_hours + COALESCE(overtime_hours, 0)) * hourly_rate) 
                 FROM {$timesheets_table} 
                 WHERE work_date = '{$date}' AND status != 'rejected'" . $job_where
            );
            
            $data[] = array(
                'label' => $day_name,
                'cost' => floatval($cost ?: 0)
            );
        }
    } else {
        // Last 4 weeks
        for ($i = 3; $i >= 0; $i--) {
            $week_start = date('Y-m-d', strtotime("monday -{$i} week"));
            $week_end = date('Y-m-d', strtotime("sunday -{$i} week"));
            
            $cost = $wpdb->get_var(
                "SELECT SUM((total_hours + COALESCE(overtime_hours, 0)) * hourly_rate) 
                 FROM {$timesheets_table} 
                 WHERE work_date BETWEEN '{$week_start}' AND '{$week_end}' AND status != 'rejected'" . $job_where
            );
            
            $data[] = array(
                'label' => 'Week ' . (4 - $i),
                'cost' => floatval($cost ?: 0),
                'week_start' => $week_start,
                'week_end' => $week_end
            );
        }
    }
    
    return new WP_REST_Response($data, 200);
}

/**
 * Get top performers - JOB SPECIFIC
 * When job_id is provided, returns top performers only for that job
 * When no job_id, returns global top performers
 */
function pi_team_timesheets_get_top_performers($request) {
    global $wpdb;
    
    $limit = intval($request->get_param('limit') ?: 4);
    $employees_table = $wpdb->prefix . 'pi_crm_employees';
    $timesheets_table = $wpdb->prefix . 'pi_crm_timesheets';
    $team_assignments_table = $wpdb->prefix . 'pi_crm_team_assignments';
    
    // Get job_id parameter
    $job_id = $request->get_param('job_id');
    $job_id = ($job_id && intval($job_id) > 0) ? intval($job_id) : null;
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    if ($job_id) {
        // STRICT: Only employees explicitly added via Team Members "Add Member" button
        // Uses INNER JOIN on team_assignments - ONLY shows assigned employees
        $performers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                e.id,
                e.first_name,
                e.last_name,
                e.role,
                e.trade,
                COALESCE(SUM(t.total_hours + COALESCE(t.overtime_hours, 0)), 0) as total_hours,
                COUNT(t.id) as entries
             FROM {$team_assignments_table} ta
             INNER JOIN {$employees_table} e ON ta.employee_id = e.id
             LEFT JOIN {$timesheets_table} t ON e.id = t.worker_id 
                AND t.job_id = %d
                AND t.work_date BETWEEN '{$week_start}' AND '{$week_end}'
                AND t.status != 'rejected'
             WHERE ta.job_id = %d AND e.status = 'active'
             GROUP BY e.id
             ORDER BY total_hours DESC
             LIMIT {$limit}",
            $job_id, $job_id
        ), ARRAY_A);
    } else {
        // Global: Get all active employees with hours across all jobs
        $performers = $wpdb->get_results(
            "SELECT 
                e.id,
                e.first_name,
                e.last_name,
                e.role,
                e.trade,
                COALESCE(SUM(t.total_hours + COALESCE(t.overtime_hours, 0)), 0) as total_hours,
                COUNT(t.id) as entries
             FROM {$employees_table} e
             LEFT JOIN {$timesheets_table} t ON e.id = t.worker_id 
                AND t.work_date BETWEEN '{$week_start}' AND '{$week_end}'
                AND t.status != 'rejected'
             WHERE e.status = 'active'
             GROUP BY e.id
             HAVING total_hours > 0
             ORDER BY total_hours DESC
             LIMIT {$limit}",
            ARRAY_A
        );
    }
    
    return new WP_REST_Response($performers, 200);
}

/**
 * Get cost codes
 */
function pi_team_timesheets_get_cost_codes($request) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pi_crm_cost_codes';
    
    $codes = $wpdb->get_results(
        "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY code",
        ARRAY_A
    );
    
    // Return default cost codes if none exist
    if (empty($codes)) {
        $defaults = array(
            array('code' => 'LABOR', 'description' => 'General Labor', 'category' => 'Labor'),
            array('code' => 'OT', 'description' => 'Overtime', 'category' => 'Labor'),
            array('code' => 'ADMIN', 'description' => 'Administrative', 'category' => 'Overhead'),
            array('code' => 'TRAVEL', 'description' => 'Travel Time', 'category' => 'Overhead'),
        );
        return new WP_REST_Response($defaults, 200);
    }
    
    return new WP_REST_Response($codes, 200);
}

/**
 * Get jobs for dropdown
 */
function pi_team_timesheets_get_jobs($request) {
    global $wpdb;
    
    $jobs = $wpdb->get_results(
        "SELECT ID as id, post_title as title 
         FROM {$wpdb->posts} 
         WHERE post_type = 'pi_job' AND post_status = 'publish'
         ORDER BY post_date DESC
         LIMIT 100",
        ARRAY_A
    );
    
    return new WP_REST_Response($jobs ?: array(), 200);
}

/**
 * ============================================
 * SHORTCODE: [planning_workspace_team]
 * Global/Common Team & Timesheets Page
 * ============================================
 */
add_shortcode('planning_workspace_team', 'pi_workspace_team_timesheets_shortcode');

function pi_workspace_team_timesheets_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="pi-team-wrapper">
            <div class="pi-auth-required">
                <div class="pi-auth-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3>Sign In Required</h3>
                <p>Please log in to access the Team & Timesheets management system.</p>
            </div>
        </div>';
    }
    
    $user_id = get_current_user_id();
    
    // Enqueue Inter font
    wp_enqueue_style(
        'google-fonts-inter',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        array(),
        null
    );
    
    // Enqueue team & timesheets CSS with cache busting
    $tt_css_version = PI_TEAM_TIMESHEETS_VERSION . '.' . time();
    wp_enqueue_style(
        'pi-team-timesheets-common',
        plugin_dir_url(__FILE__) . '../assets/team-timesheets-common.css',
        array(),
        $tt_css_version
    );
    
    wp_enqueue_style(
        'pi-team-dashboard-v3',
        plugin_dir_url(__FILE__) . '../assets/team-dashboard-v3.css',
        array('pi-team-timesheets-common'),
        $tt_css_version
    );
    
    // Enqueue the common team & timesheets JavaScript with cache busting
    $tt_js_version = PI_TEAM_TIMESHEETS_VERSION . '.' . time();
    wp_enqueue_script(
        'pi-team-timesheets-common',
        plugin_dir_url(__FILE__) . '../assets/team-timesheets-common.js',
        array('jquery'),
        $tt_js_version,
        true
    );
    
    // Enqueue new dashboard v3 JavaScript (with cache busting)
    wp_enqueue_script(
        'pi-team-dashboard-v3',
        plugin_dir_url(__FILE__) . '../assets/team-dashboard-v3.js?v=' . time(),
        array('jquery', 'pi-team-timesheets-common'),
        PI_TEAM_TIMESHEETS_VERSION,
        true
    );
    
    // Detect job_id from current page context
    $current_job_id = null;
    if (is_singular('pi_job')) {
        // On a single job page - use the post ID as job_id
        $current_job_id = get_the_ID();
    } elseif (isset($_GET['job_id']) && intval($_GET['job_id']) > 0) {
        // From query parameter
        $current_job_id = intval($_GET['job_id']);
    }
    
    // Localize script with necessary data
    wp_localize_script('pi-team-timesheets-common', 'PI_Team_Data', array(
        'restUrl' => rest_url('pi/v1'),
        'nonce' => wp_create_nonce('wp_rest'),
        'userId' => $user_id,
        'currency' => 'GBP',
        'currentJobId' => $current_job_id  // Pass job_id to JavaScript
    ));
    
    // Feather Icons
    wp_enqueue_script(
        'feather-icons',
        'https://unpkg.com/feather-icons',
        array(),
        null,
        true
    );
    
    ob_start(); ?><?php // No whitespace here ?><div class="pi-team-wrapper" data-user-id="<?php echo esc_attr($user_id); ?>"><?php // No whitespace here ?><div class="pi-team-app"><?php // No whitespace here ?><!-- Premium SaaS Header --><?php // No whitespace here ?><header class="pi-team-saas-header">
                <div class="pi-team-header-left">
                    <button class="pi-team-mobile-menu-toggle" aria-label="Toggle menu"><?php // No whitespace here ?>
                        <i data-feather="menu"></i>
                    </button>
                    <div class="pi-team-brand">
                        <h1 class="pi-team-title">Team & Timesheets</h1>
                    </div>
                </div><?php // No whitespace here ?>
                
                <nav class="pi-team-nav-container">
                    <div class="pi-team-nav-scroll">
                        <button class="pi-team-nav-item active" data-tab="overview"><?php // No whitespace here ?>
                            <i data-feather="pie-chart"></i><?php // No whitespace here ?>
                            <span>Overview</span>
                        </button>
                        <button class="pi-team-nav-item" data-tab="employees"><?php // No whitespace here ?>
                            <i data-feather="users"></i><?php // No whitespace here ?>
                            <span>Employees</span>
                        </button>
                        <button class="pi-team-nav-item" data-tab="timesheets"><?php // No whitespace here ?>
                            <i data-feather="clock"></i><?php // No whitespace here ?>
                            <span>Timesheets</span>
                        </button>
                        <button class="pi-team-nav-item" data-tab="crews"><?php // No whitespace here ?>
                            <i data-feather="hard-hat"></i><?php // No whitespace here ?>
                            <span>Crews</span>
                        </button>
                    </div>
                </nav><?php // No whitespace here ?>
                
                <div class="pi-team-header-right">
                    <div class="pi-team-search-wrapper">
                        <button class="pi-team-search-trigger" aria-label="Search"><?php // No whitespace here ?>
                            <i data-feather="search"></i>
                        </button>
                        <div class="pi-team-search-input"><?php // No whitespace here ?>
                            <i data-feather="search"></i>
                            <input type="text" id="global-search" placeholder="Search team..." data-filter="search">
                            <button class="pi-team-search-close" aria-label="Close search"><?php // No whitespace here ?>
                                <i data-feather="x"></i>
                            </button>
                        </div>
                    </div><?php // No whitespace here ?>
                    
                    <div class="pi-team-notifications">
                        <button class="pi-team-icon-btn" aria-label="Notifications"><?php // No whitespace here ?>
                            <i data-feather="bell"></i><?php // No whitespace here ?>
                            <span class="pi-team-notification-badge" id="notification-badge" style="display: none;"></span>
                        </button>
                        <div class="pi-team-dropdown pi-team-notifications-dropdown">
                            <div class="pi-team-dropdown-header">
                                <h4>Notifications</h4>
                                <button class="pi-team-text-btn" id="mark-all-read" style="display: none;">Mark all read</button>
                            </div>
                            <div class="pi-team-dropdown-list" id="notification-list">
                                <div class="pi-team-empty-state" style="padding: 2rem;">
                                    <i data-feather="bell-off" style="width: 32px; height: 32px; opacity: 0.5;"></i>
                                    <p>No notifications</p>
                                </div>
                            </div>
                        </div>
                    </div><?php // No whitespace here ?>
                    
                    <button class="pi-team-icon-btn pi-team-help-btn" aria-label="Help Center" title="Help Center"><?php // No whitespace here ?>
                        <i data-feather="help-circle"></i>
                    </button><?php // No whitespace here ?>
                    
                    <div class="pi-team-user-menu">
                        <button class="pi-team-user-trigger" aria-label="User menu"><?php // No whitespace here ?>
                            <span class="pi-team-user-avatar"><?php echo esc_html(substr(get_user_meta($user_id, 'first_name', true) ?: 'U', 0, 1)); ?></span>
                            <span class="pi-team-user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
                            <i data-feather="chevron-down" style="width: 16px; height: 16px;"></i>
                        </button>
                        <div class="pi-team-dropdown pi-team-user-dropdown">
                            <div class="pi-team-dropdown-header">
                                <span class="pi-team-user-email"><?php echo esc_html(wp_get_current_user()->user_email); ?></span>
                            </div>
                            <div class="pi-team-dropdown-list">
                                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pi-team-dropdown-item">
                                    <i data-feather="log-out"></i>
                                    <span>Sign Out</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header><?php // No whitespace here ?><!-- Main Content --><?php // No whitespace here ?><main class="pi-team-main"><?php // No whitespace here ?><!-- Overview Tab - Enhanced Dashboard v3 -->
                <div class="pi-team-tab-content active" id="tab-overview">
                    <div id="teamDashboardV3" class="pi-team-dashboard-v3">
                        <div class="pi-td-container"><?php // No whitespace here ?><!-- Dashboard Header -->
                            <div class="pi-td-header">
                                <div class="pi-td-header-left">
                                    <h1>Team Dashboard</h1>
                                    <p>Real-time workforce analytics and management</p>
                                </div>
                                <div class="pi-td-header-actions">
                                    <select id="dateRangeFilter" class="pi-td-btn pi-td-btn-secondary pi-td-btn-sm">
                                        <option value="week">This Week</option>
                                        <option value="month">This Month</option>
                                        <option value="quarter">This Quarter</option>
                                        <option value="year">This Year</option>
                                    </select><?php // No whitespace here ?><button id="refreshDashboard" class="pi-td-btn pi-td-btn-primary pi-td-btn-sm">
                                        <i data-feather="refresh-cw"></i><span>Refresh</span>
                                    </button>
                                </div>
                            </div><?php // No whitespace here ?><!-- KPI Cards Row -->
                            <div class="pi-td-kpi-grid" id="kpiCards">
                                <!-- Loaded dynamically -->
                            </div>

                            <!-- Charts Row - FLEXBOX LAYOUT -->
                            <div class="pi-charts-flex-row">
                                <!-- Hours Chart -->
                                <div class="pi-td-card pi-chart-card-large">
                                    <div class="pi-td-card-header">
                                        <div class="pi-td-card-title">
                                            <i data-feather="bar-chart-2"></i><span>Weekly Hours Breakdown</span>
                                        </div>
                                        <div class="pi-td-card-actions">
                                            <button class="pi-td-btn pi-td-btn-secondary pi-td-btn-icon" title="Download">
                                                <i data-feather="download" style="width: 16px; height: 16px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="pi-td-card-body">
                                        <div class="pi-td-chart-container">
                                            <canvas id="hoursChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <!-- Trade Distribution -->
                                <div class="pi-td-card pi-chart-card-small">
                                    <div class="pi-td-card-header">
                                        <div class="pi-td-card-title">
                                            <i data-feather="pie-chart"></i><span>Trade Distribution</span>
                                        </div>
                                    </div>
                                    <div class="pi-td-card-body">
                                        <div class="pi-td-chart-container">
                                            <canvas id="departmentChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Activity & Trends Row - FLEXBOX LAYOUT -->
                            <div class="pi-activity-flex-row">
                                <!-- Activity Feed -->
                                <div class="pi-td-card pi-activity-card-large">
                                    <div class="pi-td-card-header">
                                        <div class="pi-td-card-title">
                                            <i data-feather="activity"></i><span>Live Activity Feed</span>
                                        </div>
                                        <div class="pi-td-card-actions">
                                            <span id="lastRefresh" style="font-size: 12px; color: var(--pi-text-muted);">Updated just now</span>
                                        </div>
                                    </div>
                                    <div class="pi-td-card-body">
                                        <div class="pi-td-activity-list" id="activityFeed">
                                            <!-- Loaded dynamically -->
                                        </div>
                                    </div>
                                </div>

                                <!-- Attendance Rate -->
                                <div class="pi-td-card pi-activity-card-small">
                                    <div class="pi-td-card-header">
                                        <div class="pi-td-card-title">
                                            <i data-feather="trending-up"></i><span>Attendance Trends</span>
                                        </div>
                                    </div>
                                    <div class="pi-td-card-body">
                                        <div class="pi-td-chart-container">
                                            <canvas id="attendanceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cost Analysis - Full Width -->
                            <div class="pi-td-card" style="margin-bottom: 24px;">
                                <div class="pi-td-card-header">
                                    <div class="pi-td-card-title">
                                        <i data-feather="credit-card"></i><span>Labor Cost Analysis</span>
                                    </div>
                                    <div class="pi-td-card-actions">
                                        <button class="pi-td-btn pi-td-btn-secondary pi-td-btn-sm">This Month</button><?php // No whitespace here ?><button class="pi-td-btn pi-td-btn-primary pi-td-btn-sm">Export Report</button>
                                    </div>
                                </div>
                                <div class="pi-td-card-body">
                                    <div class="pi-td-chart-container">
                                        <canvas id="costChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Secondary Grid - FLEXBOX LAYOUT -->
                            <div class="pi-cards-flex-row">
                                <!-- Employee Overview -->
                                <div class="pi-td-card pi-card-half">
                                    <div class="pi-td-card-header">
                                        <div class="pi-td-card-title">
                                            <i data-feather="users"></i><span>Top Performers</span>
                                        </div>
                                        <div class="pi-td-card-actions">
                                            <button class="pi-td-btn pi-td-btn-secondary pi-td-btn-sm" onclick="TeamApp.switchTab('employees')">
                                                <span class="pi-td-btn-text">View All</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="pi-td-card-body">
                                        <div class="pi-td-employee-grid" id="employeeGrid">
                                            <!-- Loaded dynamically -->
                                        </div>
                                    </div>
                                </div>

                                <!-- Crew Overview -->
                                <div class="pi-td-card pi-card-half">
                                    <div class="pi-td-card-header">
                                        <div class="pi-td-card-title">
                                            <i data-feather="hard-hat"></i><span>Crew Overview</span>
                                        </div>
                                        <div class="pi-td-card-actions">
                                            <button class="pi-td-btn pi-td-btn-secondary pi-td-btn-sm" onclick="TeamApp.switchTab('crews')">
                                                <span class="pi-td-btn-text">Manage</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="pi-td-card-body">
                                        <div class="pi-td-crew-grid" id="crewGrid">
                                            <!-- Loaded dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Heatmap - Full Width -->
                            <div class="pi-td-card" style="margin-bottom: 24px;">
                                <div class="pi-td-card-header">
                                    <div class="pi-td-card-title">
                                        <i data-feather="grid"></i><span>Weekly Attendance Heatmap</span>
                                    </div>
                                    <div class="pi-td-card-actions">
                                        <div class="pi-td-legend-item">
                                            <span class="pi-td-legend-dot" style="background: #e2e8f0;"></span><span>0h</span>
                                        </div>
                                        <div class="pi-td-legend-item">
                                            <span class="pi-td-legend-dot" style="background: #22c55e;"></span><span>10h+</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pi-td-card-body">
                                    <div id="attendanceHeatmap">
                                        <!-- Loaded dynamically -->
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions - Full Width -->
                            <div class="pi-td-card">
                                <div class="pi-td-card-header">
                                    <div class="pi-td-card-title">
                                        <i data-feather="zap"></i><span>Quick Actions</span>
                                    </div>
                                </div>
                                <div class="pi-td-card-body">
                                    <div class="pi-td-quick-actions">
                                        <button class="pi-td-action-btn" onclick="TeamApp.openAddTimesheetModal()">
                                            <i data-feather="plus-circle"></i><span>Add Timesheet</span>
                                        </button>
                                        <button class="pi-td-action-btn" onclick="TeamApp.openAddEmployeeModal()">
                                            <i data-feather="user-plus"></i><span>Add Employee</span>
                                        </button>
                                        <button class="pi-td-action-btn" onclick="TeamApp.openAddCrewModal()">
                                            <i data-feather="users"></i><span>Create Crew</span>
                                        </button>
                                        <button class="pi-td-action-btn" onclick="TeamApp.exportTimesheets()">
                                            <i data-feather="download"></i><span>Export Report</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                
                <!-- Employees Tab -->
                <div class="pi-team-tab-content" id="tab-employees">
                    <div class="pi-team-toolbar">
                        <div class="pi-team-filters">
                            <input type="text" id="employee-search" placeholder="Search employees..." class="pi-team-search">
                            <select id="employee-role-filter" class="pi-team-filter-select">
                                <option value="">All Roles</option>
                                <option value="Site Manager">Site Manager</option>
                                <option value="Foreman">Foreman</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Skilled Worker">Skilled Worker</option>
                                <option value="Labourer">Labourer</option>
                                <option value="Subcontractor">Subcontractor</option>
                            </select>
                            <select id="employee-trade-filter" class="pi-team-filter-select">
                                <option value="">All Trades</option>
                                <option value="Carpentry">Carpentry</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Masonry">Masonry</option>
                                <option value="HVAC">HVAC</option>
                                <option value="Painting">Painting</option>
                                <option value="Roofing">Roofing</option>
                                <option value="General">General</option>
                            </select>
                            <select id="employee-status-filter" class="pi-team-filter-select">
                                <option value="" selected>All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="pi-team-actions">
                            <button class="pi-team-btn pi-team-btn-secondary" id="export-employees-btn">
                                <i data-feather="download"></i> Export
                            </button>
                            <button class="pi-team-btn pi-team-btn-primary" id="add-employee-btn">
                                <i data-feather="user-plus"></i> Add Employee
                            </button>
                        </div>
                    </div><?php // No whitespace here ?><div class="pi-team-data-table" id="employees-table-container">
                        <table id="employees-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-employees"></th>
                                    <th>Employee</th>
                                    <th>Role / Trade</th>
                                    <th>Hourly Rate</th>
                                    <th>Hours This Week</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                </div><?php // No whitespace here ?><!-- Timesheets Tab -->
                <div class="pi-team-tab-content" id="tab-timesheets">
                    <div class="pi-team-toolbar">
                        <div class="pi-team-filters">
                            <input type="date" id="timesheet-start-date" class="pi-team-date-input">
                            <span class="pi-team-date-separator">to</span>
                            <input type="date" id="timesheet-end-date" class="pi-team-date-input">
                            <select id="timesheet-employee-filter" class="pi-team-filter-select">
                                <option value="">All Employees</option>
                                <!-- Loaded via JS -->
                            </select>
                            <select id="timesheet-status-filter" class="pi-team-filter-select">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="pi-team-actions">
                            <button class="pi-team-btn pi-team-btn-secondary" id="export-timesheets-btn">
                                <i data-feather="download"></i> Export
                            </button>
                            <button class="pi-team-btn pi-team-btn-primary" id="add-timesheet-btn-2">
                                <i data-feather="plus"></i> Add Entry
                            </button>
                        </div>
                    </div><?php // No whitespace here ?><div class="pi-team-data-table" id="timesheets-table-container">
                        <table id="timesheets-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-timesheets"></th>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Job</th>
                                    <th>Time</th>
                                    <th>Hours</th>
                                    <th>Cost Code</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                </div><?php // No whitespace here ?><!-- Crews Tab -->
                <div class="pi-team-tab-content" id="tab-crews">
                    <div class="pi-team-toolbar">
                        <div class="pi-team-filters">
                            <input type="text" id="crew-search" placeholder="Search crews..." class="pi-team-search">
                        </div>
                        <div class="pi-team-actions">
                            <button class="pi-team-btn pi-team-btn-primary" id="add-crew-btn">
                                <i data-feather="plus"></i> Create Crew
                            </button>
                        </div>
                    </div><?php // No whitespace here ?><div class="pi-team-crews-grid" id="crews-grid">
                        <!-- Crews loaded via JS -->
                    </div>
                </div><?php // No whitespace here ?><!-- End Crews Tab -->
            </main>
        </div>
    </div>
    
    <!-- Modal Container -->
    <div id="pi-team-modal-container"></div>
    
    <?php
    return ob_get_clean();
}
