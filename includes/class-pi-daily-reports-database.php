<?php
/**
 * Planning Index CRM - Daily Reports Database Schema
 * Enhanced daily reports system for construction operations
 */

if (!defined('ABSPATH')) exit;

class PI_Daily_Reports_Database {
    
    private static $instance = null;
    private $wpdb;
    private $charset_collate;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Create all daily reports tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Main daily reports table (enhanced)
        $this->create_daily_reports_table();
        
        // Daily report activities (work performed)
        $this->create_daily_report_activities_table();
        
        // Daily report labor (attendance and hours)
        $this->create_daily_report_labor_table();
        
        // Daily report equipment logs
        $this->create_daily_report_equipment_table();
        
        // Daily report materials/deliveries
        $this->create_daily_report_materials_table();
        
        // Daily report safety incidents
        $this->create_daily_report_safety_table();
        
        // Daily report photos with metadata
        $this->create_daily_report_photos_table();
        
        // Daily report ratings
        $this->create_daily_report_ratings_table();
        
        // Delay claims extracted from activities
        $this->create_delay_claims_table();
        
        // Report approvals workflow
        $this->create_report_approvals_table();
        
        // Audit logs for all changes
        $this->create_daily_reports_audit_table();
        
        // Visitor log
        $this->create_daily_report_visitors_table();
        
        // Corrective actions
        $this->create_daily_report_corrective_actions_table();
        
        // Offline queue for clock events
        $this->create_clock_events_queue_table();
    }
    
    /**
     * Main daily reports table - enhanced version
     */
    private function create_daily_reports_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_reports';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            
            -- Job and Report Identification
            job_id bigint(20) unsigned NOT NULL,
            report_date date NOT NULL,
            report_number varchar(50) DEFAULT NULL,
            report_status varchar(20) DEFAULT 'draft',
            
            -- Shift Information
            shift_start datetime DEFAULT NULL,
            shift_end datetime DEFAULT NULL,
            shift_duration_minutes int(11) DEFAULT NULL,
            
            -- Weather (preserved - existing fields)
            weather_conditions varchar(200) DEFAULT NULL,
            weather_temp_high int(11) DEFAULT NULL,
            weather_temp_low int(11) DEFAULT NULL,
            weather_precipitation varchar(50) DEFAULT NULL,
            weather_wind_speed int(11) DEFAULT NULL,
            
            -- Workforce Summary
            workforce_count int(11) DEFAULT 0,
            workforce_total_hours decimal(8,2) DEFAULT 0.00,
            workforce_regular_hours decimal(8,2) DEFAULT 0.00,
            workforce_overtime_hours decimal(8,2) DEFAULT 0.00,
            
            -- Legacy text fields (for backward compatibility, will be phased out)
            workforce_details text,
            subcontractors_present text,
            equipment_used text,
            work_completed text,
            work_planned text,
            delays text,
            safety_issues text,
            quality_issues text,
            materials_received text,
            visitors text,
            photos text,
            
            -- New structured fields
            general_notes text,
            client_communications text,
            tomorrow_blockers text,
            
            -- Workflow
            submitted_by bigint(20) unsigned DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            approved_by bigint(20) unsigned DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            locked_at datetime DEFAULT NULL,
            
            -- Auto-save
            last_auto_save datetime DEFAULT NULL,
            
            -- Soft delete
            is_deleted tinyint(1) DEFAULT 0,
            deleted_at datetime DEFAULT NULL,
            deleted_by bigint(20) unsigned DEFAULT NULL,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY job_date (job_id, report_date, is_deleted),
            KEY job_id (job_id),
            KEY report_date (report_date),
            KEY report_status (report_status),
            KEY report_number (report_number),
            KEY submitted_by (submitted_by),
            KEY is_deleted (is_deleted)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Work performed activities
     */
    private function create_daily_report_activities_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_activities';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Location and Trade
            location_area varchar(300) DEFAULT NULL,
            trade_company varchar(200) DEFAULT NULL,
            
            -- Activity Details
            activity_type varchar(100) DEFAULT NULL,
            activity_description text NOT NULL,
            quantity_completed decimal(10,2) DEFAULT NULL,
            unit_of_measure varchar(50) DEFAULT NULL,
            percent_complete int(11) DEFAULT 0,
            
            -- Time Tracking
            start_time datetime DEFAULT NULL,
            end_time datetime DEFAULT NULL,
            
            -- Cost Code Integration
            cost_code varchar(50) DEFAULT NULL,
            phase_wbs varchar(100) DEFAULT NULL,
            
            -- Delay Tagging
            delay_reason varchar(100) DEFAULT NULL,
            delay_hours decimal(5,2) DEFAULT 0.00,
            
            -- Issues
            blockers_issues text,
            
            -- Photos
            photos text,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY trade_company (trade_company),
            KEY cost_code (cost_code),
            KEY delay_reason (delay_reason)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Labor attendance and hours
     */
    private function create_daily_report_labor_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_labor';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Worker Information
            employee_id bigint(20) unsigned DEFAULT NULL,
            worker_name varchar(200) NOT NULL,
            trade varchar(100) DEFAULT NULL,
            company varchar(200) DEFAULT NULL,
            is_subcontractor tinyint(1) DEFAULT 0,
            
            -- Time Tracking
            clock_in datetime DEFAULT NULL,
            clock_out datetime DEFAULT NULL,
            total_hours decimal(5,2) DEFAULT 0.00,
            regular_hours decimal(5,2) DEFAULT 0.00,
            overtime_hours decimal(5,2) DEFAULT 0.00,
            
            -- Break Tracking
            break_start datetime DEFAULT NULL,
            break_end datetime DEFAULT NULL,
            break_minutes int(11) DEFAULT 0,
            break_deduction tinyint(1) DEFAULT 0,
            
            -- GPS Verification
            gps_lat decimal(10,8) DEFAULT NULL,
            gps_lng decimal(11,8) DEFAULT NULL,
            gps_accuracy decimal(8,2) DEFAULT NULL,
            location_verified tinyint(1) DEFAULT 0,
            location_status varchar(20) DEFAULT 'no_gps',
            
            -- Cost Code
            cost_code varchar(50) DEFAULT NULL,
            phase_wbs varchar(100) DEFAULT NULL,
            
            -- Notes
            notes text,
            
            -- Offline sync
            device_timestamp datetime DEFAULT NULL,
            synced_at datetime DEFAULT NULL,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY employee_id (employee_id),
            KEY clock_in (clock_in),
            KEY location_status (location_status)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Equipment logs
     */
    private function create_daily_report_equipment_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_equipment';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Equipment Information
            equipment_id bigint(20) unsigned DEFAULT NULL,
            equipment_name varchar(200) NOT NULL,
            equipment_type varchar(100) DEFAULT NULL,
            ownership_type varchar(50) DEFAULT 'owned',
            
            -- Status
            status varchar(20) DEFAULT 'active',
            
            -- Usage
            hours_used decimal(5,2) DEFAULT 0.00,
            meter_reading decimal(10,2) DEFAULT NULL,
            
            -- Operator
            operator_name varchar(200) DEFAULT NULL,
            operator_id bigint(20) unsigned DEFAULT NULL,
            
            -- Fuel
            fuel_notes text,
            
            -- Issues
            maintenance_issues text,
            downtime_reason varchar(200) DEFAULT NULL,
            downtime_hours decimal(5,2) DEFAULT 0.00,
            estimated_resolution datetime DEFAULT NULL,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY equipment_id (equipment_id),
            KEY status (status)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Materials and deliveries
     */
    private function create_daily_report_materials_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_materials';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Material Reference (link to materials page)
            material_id bigint(20) unsigned DEFAULT NULL,
            bom_item_id bigint(20) unsigned DEFAULT NULL,
            
            -- Delivery Information
            supplier_id bigint(20) unsigned DEFAULT NULL,
            supplier_name varchar(200) NOT NULL,
            po_number varchar(100) DEFAULT NULL,
            delivery_status enum('scheduled','in_transit','delivered','delayed','cancelled','partial') DEFAULT 'scheduled',
            
            -- Material Details
            material_name varchar(255) NOT NULL,
            material_sku varchar(100) DEFAULT NULL,
            material_category varchar(100) DEFAULT NULL,
            material_description text,
            quantity_ordered decimal(10,2) DEFAULT 0,
            quantity_delivered decimal(10,2) DEFAULT 0,
            unit_of_measure varchar(50) DEFAULT 'each',
            unit_cost decimal(10,2) DEFAULT 0,
            total_cost decimal(12,2) DEFAULT 0,
            
            -- Quality and Condition
            condition_on_arrival varchar(20) DEFAULT 'good',
            quality_notes text,
            damaged_quantity decimal(10,2) DEFAULT 0,
            missing_quantity decimal(10,2) DEFAULT 0,
            
            -- Receipt Information
            received_by varchar(200) DEFAULT NULL,
            received_by_user_id bigint(20) unsigned DEFAULT NULL,
            received_at datetime DEFAULT NULL,
            delivery_ticket_number varchar(100) DEFAULT NULL,
            
            -- Documentation
            delivery_ticket_url varchar(500) DEFAULT NULL,
            photos text,
            attachments text,
            
            -- Expected but missing
            is_expected_missing tinyint(1) DEFAULT 0,
            scheduled_delivery_date date DEFAULT NULL,
            actual_delivery_date date DEFAULT NULL,
            delay_reason text,
            
            -- Stock Integration
            stock_location_id varchar(100) DEFAULT 'main',
            stock_movement_recorded tinyint(1) DEFAULT 0,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY material_id (material_id),
            KEY bom_item_id (bom_item_id),
            KEY supplier_id (supplier_id),
            KEY supplier_name (supplier_name),
            KEY po_number (po_number),
            KEY delivery_status (delivery_status),
            KEY scheduled_delivery_date (scheduled_delivery_date),
            KEY actual_delivery_date (actual_delivery_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety incidents and inspections
     */
    private function create_daily_report_safety_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_safety';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Record Type
            record_type varchar(20) NOT NULL,
            
            -- For Incidents
            incident_type varchar(50) DEFAULT NULL,
            severity varchar(20) DEFAULT NULL,
            injured_party varchar(200) DEFAULT NULL,
            witness_names text,
            immediate_actions text,
            root_cause text,
            
            -- For Inspections
            inspection_type varchar(100) DEFAULT NULL,
            inspection_result varchar(20) DEFAULT NULL,
            inspector_name varchar(200) DEFAULT NULL,
            certificate_url varchar(500) DEFAULT NULL,
            
            -- Common
            description text NOT NULL,
            location_area varchar(300) DEFAULT NULL,
            occurred_at datetime DEFAULT NULL,
            photos text,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY record_type (record_type),
            KEY incident_type (incident_type),
            KEY severity (severity)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Photos with metadata
     */
    private function create_daily_report_photos_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_photos';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Photo Details
            photo_url varchar(500) NOT NULL,
            thumbnail_url varchar(500) DEFAULT NULL,
            file_name varchar(300) DEFAULT NULL,
            
            -- GPS
            gps_lat decimal(10,8) DEFAULT NULL,
            gps_lng decimal(11,8) DEFAULT NULL,
            gps_accuracy decimal(8,2) DEFAULT NULL,
            
            -- Metadata
            taken_at datetime DEFAULT NULL,
            uploaded_by bigint(20) unsigned NOT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            
            -- Tagging
            category varchar(100) DEFAULT NULL,
            tags text,
            associated_activity_id bigint(20) unsigned DEFAULT NULL,
            
            -- Annotation
            has_annotations tinyint(1) DEFAULT 0,
            annotation_data text,
            
            -- Audit
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY category (category),
            KEY taken_at (taken_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Daily ratings
     */
    private function create_daily_report_ratings_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_ratings';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Individual Ratings (1-10 scale)
            productivity_rating decimal(3,1) DEFAULT NULL,
            safety_rating decimal(3,1) DEFAULT NULL,
            quality_rating decimal(3,1) DEFAULT NULL,
            site_conditions_rating decimal(3,1) DEFAULT NULL,
            
            -- Overall Score
            overall_score decimal(3,1) DEFAULT NULL,
            letter_grade varchar(2) DEFAULT NULL,
            score_color varchar(20) DEFAULT NULL,
            
            -- Justification for overrides
            rating_justification text,
            
            -- Calculation metadata
            productivity_weight decimal(3,2) DEFAULT 0.30,
            safety_weight decimal(3,2) DEFAULT 0.30,
            quality_weight decimal(3,2) DEFAULT 0.20,
            site_conditions_weight decimal(3,2) DEFAULT 0.20,
            
            -- Audit
            rated_by bigint(20) unsigned NOT NULL,
            rated_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY overall_score (overall_score)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Delay claims
     */
    private function create_delay_claims_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_delay_claims';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            activity_id bigint(20) unsigned DEFAULT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Delay Details
            delay_reason varchar(100) NOT NULL,
            delay_hours decimal(5,2) NOT NULL,
            delay_description text,
            
            -- Claim Status
            claim_status varchar(20) DEFAULT 'open',
            claim_reference varchar(100) DEFAULT NULL,
            
            -- Resolution
            resolved_at datetime DEFAULT NULL,
            resolution_notes text,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY activity_id (activity_id),
            KEY job_id (job_id),
            KEY delay_reason (delay_reason),
            KEY claim_status (claim_status)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Report approvals workflow
     */
    private function create_report_approvals_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_report_approvals';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Workflow State
            from_status varchar(20) NOT NULL,
            to_status varchar(20) NOT NULL,
            
            -- Approval/Rejection
            action varchar(20) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            actor_name varchar(200) DEFAULT NULL,
            actor_role varchar(100) DEFAULT NULL,
            
            -- Digital Signature
            signature varchar(200) DEFAULT NULL,
            signature_hash varchar(64) DEFAULT NULL,
            
            -- Comments
            comments text,
            
            -- Timestamp
            acted_at datetime DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY action (action),
            KEY acted_at (acted_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Audit logs
     */
    private function create_daily_reports_audit_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_reports_audit';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Change Details
            action_type varchar(50) NOT NULL,
            table_name varchar(100) NOT NULL,
            record_id bigint(20) unsigned NOT NULL,
            
            -- Change Data
            field_name varchar(100) DEFAULT NULL,
            old_value text,
            new_value text,
            
            -- Actor
            actor_id bigint(20) unsigned NOT NULL,
            actor_name varchar(200) DEFAULT NULL,
            
            -- Metadata
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            
            -- Timestamp
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Visitor log
     */
    private function create_daily_report_visitors_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_visitors';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Visitor Information
            visitor_name varchar(200) NOT NULL,
            company varchar(200) DEFAULT NULL,
            purpose varchar(300) DEFAULT NULL,
            
            -- Time Tracking
            arrival_time datetime DEFAULT NULL,
            departure_time datetime DEFAULT NULL,
            
            -- Host
            host_name varchar(200) DEFAULT NULL,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Corrective actions
     */
    private function create_daily_report_corrective_actions_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_report_corrective_actions';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daily_report_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            
            -- Action Details
            action_description text NOT NULL,
            status varchar(20) DEFAULT 'open',
            priority varchar(20) DEFAULT 'medium',
            
            -- Assignment
            assigned_to varchar(200) DEFAULT NULL,
            
            -- Deadlines
            due_date date DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            
            -- Resolution
            resolution_notes text,
            
            -- Photos
            photos text,
            
            -- Audit
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY daily_report_id (daily_report_id),
            KEY job_id (job_id),
            KEY status (status),
            KEY due_date (due_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Offline queue for clock events
     */
    private function create_clock_events_queue_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_clock_events_queue';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            
            -- Event Details
            employee_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned DEFAULT NULL,
            event_type varchar(20) NOT NULL,
            
            -- GPS
            gps_lat decimal(10,8) DEFAULT NULL,
            gps_lng decimal(11,8) DEFAULT NULL,
            gps_accuracy decimal(8,2) DEFAULT NULL,
            
            -- Device Info
            device_info varchar(200) DEFAULT NULL,
            
            -- Offline Metadata
            device_timestamp datetime NOT NULL,
            synced_at datetime DEFAULT NULL,
            sync_status varchar(20) DEFAULT 'pending',
            sync_error text,
            
            -- Processed
            processed_daily_report_id bigint(20) unsigned DEFAULT NULL,
            processed_labor_id bigint(20) unsigned DEFAULT NULL,
            
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY job_id (job_id),
            KEY sync_status (sync_status),
            KEY device_timestamp (device_timestamp)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Run migrations to ensure all required columns exist
     */
    public function run_migrations() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_reports';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            error_log('[Daily Reports] Creating new daily reports table');
            return; // create_tables() will handle this
        }
        
        // Get existing columns
        $existing_columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$table_name}");
        
        // Required columns that might be missing
        $required_columns = [
            'report_status' => "ALTER TABLE {$table_name} ADD COLUMN report_status varchar(20) DEFAULT 'draft'",
            'is_deleted' => "ALTER TABLE {$table_name} ADD COLUMN is_deleted tinyint(1) DEFAULT 0",
            'deleted_at' => "ALTER TABLE {$table_name} ADD COLUMN deleted_at datetime DEFAULT NULL",
            'deleted_by' => "ALTER TABLE {$table_name} ADD COLUMN deleted_by bigint(20) unsigned DEFAULT NULL",
            'created_by' => "ALTER TABLE {$table_name} ADD COLUMN created_by bigint(20) unsigned NOT NULL DEFAULT 1",
            'created_at' => "ALTER TABLE {$table_name} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP",
            'updated_by' => "ALTER TABLE {$table_name} ADD COLUMN updated_by bigint(20) unsigned DEFAULT NULL",
            'updated_at' => "ALTER TABLE {$table_name} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($required_columns as $column => $alter_sql) {
            if (!in_array($column, $existing_columns)) {
                error_log("[Daily Reports] Adding missing column: {$column}");
                $this->wpdb->query($alter_sql);
            }
        }
        
        // Ensure audit table exists
        $this->ensure_audit_table_exists();
        
        // Ensure materials table has material_id column
        $this->ensure_materials_table_has_material_id();
    }
    
    /**
     * Ensure materials table has material_id column
     */
    private function ensure_materials_table_has_material_id() {
        $materials_table = $this->wpdb->prefix . 'pi_crm_daily_report_materials';
        
        // Check if materials table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$materials_table}'") === $materials_table;
        
        if (!$table_exists) {
            error_log('[Daily Reports] Materials table does not exist, will be created by create_tables()');
            return;
        }
        
        // Get existing columns
        $existing_columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$materials_table}");
        
        // Required columns for materials integration - ALL columns from schema
        $required_columns = [
            'material_id' => "ALTER TABLE {$materials_table} ADD COLUMN material_id bigint(20) unsigned DEFAULT NULL AFTER job_id",
            'bom_item_id' => "ALTER TABLE {$materials_table} ADD COLUMN bom_item_id bigint(20) unsigned DEFAULT NULL AFTER material_id",
            'supplier_id' => "ALTER TABLE {$materials_table} ADD COLUMN supplier_id bigint(20) unsigned DEFAULT NULL AFTER bom_item_id",
            'supplier_name' => "ALTER TABLE {$materials_table} ADD COLUMN supplier_name varchar(200) NOT NULL DEFAULT '' AFTER supplier_id",
            'po_number' => "ALTER TABLE {$materials_table} ADD COLUMN po_number varchar(100) DEFAULT NULL AFTER supplier_name",
            'delivery_status' => "ALTER TABLE {$materials_table} ADD COLUMN delivery_status enum('scheduled','in_transit','delivered','delayed','cancelled','partial') DEFAULT 'scheduled' AFTER po_number",
            'material_name' => "ALTER TABLE {$materials_table} ADD COLUMN material_name varchar(255) NOT NULL DEFAULT '' AFTER delivery_status",
            'material_sku' => "ALTER TABLE {$materials_table} ADD COLUMN material_sku varchar(100) DEFAULT NULL AFTER material_name",
            'material_category' => "ALTER TABLE {$materials_table} ADD COLUMN material_category varchar(100) DEFAULT NULL AFTER material_sku",
            'material_description' => "ALTER TABLE {$materials_table} ADD COLUMN material_description text AFTER material_category",
            'quantity_ordered' => "ALTER TABLE {$materials_table} ADD COLUMN quantity_ordered decimal(10,2) DEFAULT 0 AFTER material_description",
            'quantity_delivered' => "ALTER TABLE {$materials_table} ADD COLUMN quantity_delivered decimal(10,2) DEFAULT 0 AFTER quantity_ordered",
            'unit_of_measure' => "ALTER TABLE {$materials_table} ADD COLUMN unit_of_measure varchar(50) DEFAULT 'each' AFTER quantity_delivered",
            'unit_cost' => "ALTER TABLE {$materials_table} ADD COLUMN unit_cost decimal(10,2) DEFAULT 0 AFTER unit_of_measure",
            'total_cost' => "ALTER TABLE {$materials_table} ADD COLUMN total_cost decimal(12,2) DEFAULT 0 AFTER unit_cost",
            'condition_on_arrival' => "ALTER TABLE {$materials_table} ADD COLUMN condition_on_arrival varchar(20) DEFAULT 'good' AFTER total_cost",
            'quality_notes' => "ALTER TABLE {$materials_table} ADD COLUMN quality_notes text AFTER condition_on_arrival",
            'damaged_quantity' => "ALTER TABLE {$materials_table} ADD COLUMN damaged_quantity decimal(10,2) DEFAULT 0 AFTER quality_notes",
            'missing_quantity' => "ALTER TABLE {$materials_table} ADD COLUMN missing_quantity decimal(10,2) DEFAULT 0 AFTER damaged_quantity",
            'received_by' => "ALTER TABLE {$materials_table} ADD COLUMN received_by varchar(200) DEFAULT NULL AFTER missing_quantity",
            'received_by_user_id' => "ALTER TABLE {$materials_table} ADD COLUMN received_by_user_id bigint(20) unsigned DEFAULT NULL AFTER received_by",
            'received_at' => "ALTER TABLE {$materials_table} ADD COLUMN received_at datetime DEFAULT NULL AFTER received_by_user_id",
            'delivery_ticket_number' => "ALTER TABLE {$materials_table} ADD COLUMN delivery_ticket_number varchar(100) DEFAULT NULL AFTER received_at",
            'delivery_ticket_url' => "ALTER TABLE {$materials_table} ADD COLUMN delivery_ticket_url varchar(500) DEFAULT NULL AFTER delivery_ticket_number",
            'photos' => "ALTER TABLE {$materials_table} ADD COLUMN photos text AFTER delivery_ticket_url",
            'attachments' => "ALTER TABLE {$materials_table} ADD COLUMN attachments text AFTER photos",
            'is_expected_missing' => "ALTER TABLE {$materials_table} ADD COLUMN is_expected_missing tinyint(1) DEFAULT 0 AFTER attachments",
            'scheduled_delivery_date' => "ALTER TABLE {$materials_table} ADD COLUMN scheduled_delivery_date date DEFAULT NULL AFTER is_expected_missing",
            'actual_delivery_date' => "ALTER TABLE {$materials_table} ADD COLUMN actual_delivery_date date DEFAULT NULL AFTER scheduled_delivery_date",
            'delay_reason' => "ALTER TABLE {$materials_table} ADD COLUMN delay_reason text AFTER actual_delivery_date",
            'stock_location_id' => "ALTER TABLE {$materials_table} ADD COLUMN stock_location_id varchar(100) DEFAULT 'main' AFTER delay_reason",
            'stock_movement_recorded' => "ALTER TABLE {$materials_table} ADD COLUMN stock_movement_recorded tinyint(1) DEFAULT 0 AFTER stock_location_id",
            'created_by' => "ALTER TABLE {$materials_table} ADD COLUMN created_by bigint(20) unsigned NOT NULL DEFAULT 1 AFTER stock_movement_recorded",
            'created_at' => "ALTER TABLE {$materials_table} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER created_by",
            'updated_by' => "ALTER TABLE {$materials_table} ADD COLUMN updated_by bigint(20) unsigned DEFAULT NULL AFTER created_at",
            'updated_at' => "ALTER TABLE {$materials_table} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by"
        ];
        
        foreach ($required_columns as $column => $alter_sql) {
            if (!in_array($column, $existing_columns)) {
                error_log("[Daily Reports] Adding missing column to materials table: {$column}");
                $this->wpdb->query($alter_sql);
            }
        }
    }
    
    /**
     * Ensure audit table exists
     */
    private function ensure_audit_table_exists() {
        $audit_table = $this->wpdb->prefix . 'pi_crm_daily_reports_audit';
        
        // Check if audit table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$audit_table}'") === $audit_table;
        
        if (!$table_exists) {
            error_log('[Daily Reports] Creating audit table');
            $this->create_daily_reports_audit_table();
        }
    }
    
    /**
     * Log audit entry
     */
    public function log_audit($daily_report_id, $job_id, $action_type, $table_name, $record_id, $field_name = null, $old_value = null, $new_value = null) {
        $table_name_audit = $this->wpdb->prefix . 'pi_crm_daily_reports_audit';
        
        $current_user = wp_get_current_user();
        $actor_id = $current_user->ID ?: 0;
        $actor_name = $current_user->display_name ?: 'System';
        
        return $this->wpdb->insert($table_name_audit, [
            'daily_report_id' => $daily_report_id,
            'job_id' => $job_id,
            'action_type' => $action_type,
            'table_name' => $table_name,
            'record_id' => $record_id,
            'field_name' => $field_name,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'actor_id' => $actor_id,
            'actor_name' => $actor_name,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return null;
    }
}
