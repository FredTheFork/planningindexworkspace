<?php
/**
 * Planning Index CRM - Equipment Database Schema
 * Comprehensive equipment management tables for construction CRM
 */

if (!defined('ABSPATH')) exit;

class PI_Equipment_Database {
    
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
     * Create all equipment-related tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Main equipment table (enhanced)
        $this->create_equipment_table();
        
        // Equipment timeline/audit trail
        $this->create_equipment_timeline_table();
        
        // Equipment inspections
        $this->create_equipment_inspections_table();
        
        // Equipment documents
        $this->create_equipment_documents_table();
        
        // Equipment operators (link to employees)
        $this->create_equipment_operators_table();
        
        // Equipment requests (for approval workflow)
        $this->create_equipment_requests_table();
        
        // Equipment expenses linking
        $this->create_equipment_expenses_table();
    }
    
    private function create_equipment_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            
            -- Job scoping
            job_id bigint(20) unsigned NOT NULL,
            company_id bigint(20) unsigned DEFAULT NULL,
            
            -- Identification
            equipment_type varchar(100) NOT NULL,
            category varchar(50) DEFAULT 'Heavy Machinery',
            internal_name varchar(200) NOT NULL,
            manufacturer varchar(100) DEFAULT NULL,
            brand varchar(100) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            model_number varchar(100) DEFAULT NULL,
            serial_number varchar(100) DEFAULT NULL,
            vin varchar(100) DEFAULT NULL,
            asset_tag varchar(100) DEFAULT NULL,
            year_of_manufacture int(4) DEFAULT NULL,
            specifications text,
            
            -- Ownership & Financial
            acquisition_type varchar(50) DEFAULT 'Owned',
            supplier_name varchar(200) DEFAULT NULL,
            supplier_contact varchar(200) DEFAULT NULL,
            supplier_email varchar(200) DEFAULT NULL,
            supplier_phone varchar(50) DEFAULT NULL,
            hire_reference_number varchar(100) DEFAULT NULL,
            po_number varchar(100) DEFAULT NULL,
            
            -- Rate structure
            rate_type varchar(20) DEFAULT 'daily',
            daily_rate decimal(10,2) DEFAULT 0.00,
            weekly_rate decimal(10,2) DEFAULT 0.00,
            monthly_rate decimal(10,2) DEFAULT 0.00,
            flat_fee decimal(10,2) DEFAULT 0.00,
            cost_per_hour decimal(8,2) DEFAULT 0.00,
            cost_to_job decimal(12,2) DEFAULT 0.00,
            deposit_held decimal(10,2) DEFAULT 0.00,
            insurance_included tinyint(1) DEFAULT 0,
            fuel_policy varchar(50) DEFAULT 'Excluded',
            ownership_document varchar(500) DEFAULT NULL,
            
            -- Job Allocation & Logistics
            allocated_from_date datetime DEFAULT NULL,
            allocated_to_date datetime DEFAULT NULL,
            actual_on_site_date datetime DEFAULT NULL,
            expected_return_date datetime DEFAULT NULL,
            actual_return_date datetime DEFAULT NULL,
            delivery_method varchar(50) DEFAULT 'Supplier Delivery',
            collection_method varchar(50) DEFAULT 'Supplier Collection',
            current_location_on_site varchar(200) DEFAULT NULL,
            next_job_allocation_id bigint(20) unsigned DEFAULT NULL,
            next_job_allocation_type varchar(50) DEFAULT 'Return to Depot',
            
            -- Status
            status varchar(50) DEFAULT 'On-Site',
            previous_status varchar(50) DEFAULT NULL,
            status_changed_at datetime DEFAULT NULL,
            status_changed_by bigint(20) unsigned DEFAULT NULL,
            
            -- Operator & Responsibility
            assigned_operator_id bigint(20) unsigned DEFAULT NULL,
            operator_certification_required varchar(100) DEFAULT NULL,
            operator_certification_verified tinyint(1) DEFAULT 0,
            operator_certification_expiry date DEFAULT NULL,
            supervisor_responsible_id bigint(20) unsigned DEFAULT NULL,
            check_in_by bigint(20) unsigned DEFAULT NULL,
            check_in_at datetime DEFAULT NULL,
            check_out_by bigint(20) unsigned DEFAULT NULL,
            check_out_at datetime DEFAULT NULL,
            
            -- Condition & Compliance
            condition_on_arrival varchar(50) DEFAULT 'Good',
            condition_on_return varchar(50) DEFAULT NULL,
            current_condition varchar(50) DEFAULT 'Good',
            last_inspection_date date DEFAULT NULL,
            next_inspection_due date DEFAULT NULL,
            inspection_status varchar(20) DEFAULT 'Pass',
            hours_meter_reading decimal(10,2) DEFAULT 0.00,
            hours_meter_on_arrival decimal(10,2) DEFAULT 0.00,
            fuel_level_on_arrival varchar(20) DEFAULT NULL,
            fuel_level_current varchar(20) DEFAULT NULL,
            
            -- Photos & Documents
            photos text,
            arrival_photos text,
            return_photos text,
            damage_photos text,
            
            -- Notes
            notes text,
            check_in_notes text,
            check_out_notes text,
            
            -- Soft delete & audit
            is_deleted tinyint(1) DEFAULT 0,
            deleted_at datetime DEFAULT NULL,
            deleted_by bigint(20) unsigned DEFAULT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY equipment_type (equipment_type),
            KEY category (category),
            KEY status (status),
            KEY acquisition_type (acquisition_type),
            KEY assigned_operator_id (assigned_operator_id),
            KEY next_inspection_due (next_inspection_due),
            KEY allocated_from_date (allocated_from_date),
            KEY allocated_to_date (allocated_to_date),
            KEY is_deleted (is_deleted),
            KEY serial_number (serial_number)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_equipment_timeline_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment_timeline';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            event_title varchar(200) NOT NULL,
            event_description text,
            old_value text,
            new_value text,
            performed_by bigint(20) unsigned NOT NULL,
            performed_by_name varchar(200) DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY job_id (job_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_equipment_inspections_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment_inspections';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            inspection_type varchar(50) DEFAULT 'Daily',
            inspection_date date NOT NULL,
            inspected_by bigint(20) unsigned NOT NULL,
            inspected_by_name varchar(200) DEFAULT NULL,
            hours_meter_reading decimal(10,2) DEFAULT NULL,
            fuel_level varchar(20) DEFAULT NULL,
            condition_rating varchar(20) DEFAULT 'Good',
            inspection_status varchar(20) DEFAULT 'Pass',
            safety_certificates_verified tinyint(1) DEFAULT 0,
            loler_date date DEFAULT NULL,
            puwer_date date DEFAULT NULL,
            pat_test_date date DEFAULT NULL,
            notes text,
            photos text,
            follow_up_required tinyint(1) DEFAULT 0,
            follow_up_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY job_id (job_id),
            KEY inspection_date (inspection_date),
            KEY inspection_status (inspection_status)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_equipment_documents_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment_documents';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            document_type varchar(50) NOT NULL,
            document_name varchar(300) NOT NULL,
            file_name varchar(300) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            expiry_date date DEFAULT NULL,
            is_verified tinyint(1) DEFAULT 0,
            uploaded_by bigint(20) unsigned NOT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY job_id (job_id),
            KEY document_type (document_type),
            KEY expiry_date (expiry_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_equipment_operators_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment_operators';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            employee_id bigint(20) unsigned NOT NULL,
            assigned_by bigint(20) unsigned NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            unassigned_at datetime DEFAULT NULL,
            unassigned_by bigint(20) unsigned DEFAULT NULL,
            is_primary tinyint(1) DEFAULT 1,
            certification_required varchar(100) DEFAULT NULL,
            certification_verified tinyint(1) DEFAULT 0,
            certification_expiry date DEFAULT NULL,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY job_id (job_id),
            KEY employee_id (employee_id),
            UNIQUE KEY active_operator (equipment_id, employee_id, unassigned_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_equipment_requests_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment_requests';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            requested_by bigint(20) unsigned NOT NULL,
            requested_by_name varchar(200) DEFAULT NULL,
            equipment_type varchar(100) NOT NULL,
            quantity int(11) DEFAULT 1,
            date_required_from date DEFAULT NULL,
            date_required_to date DEFAULT NULL,
            justification text,
            estimated_cost decimal(10,2) DEFAULT 0.00,
            status varchar(50) DEFAULT 'Pending',
            approved_by bigint(20) unsigned DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            rejection_reason text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY status (status),
            KEY requested_by (requested_by)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_equipment_expenses_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment_expenses';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            expense_id bigint(20) unsigned NOT NULL,
            expense_type varchar(50) DEFAULT 'hire',
            amount decimal(10,2) NOT NULL,
            period_from date DEFAULT NULL,
            period_to date DEFAULT NULL,
            description varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY job_id (job_id),
            KEY expense_id (expense_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Log an event to the equipment timeline
     */
    public function log_timeline_event($equipment_id, $job_id, $event_type, $event_title, $event_description = '', $old_value = '', $new_value = '', $metadata = []) {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment_timeline';
        
        $current_user = wp_get_current_user();
        $performed_by = $current_user->ID ?: 0;
        $performed_by_name = $current_user->display_name ?: 'System';
        
        return $this->wpdb->insert($table_name, [
            'equipment_id' => $equipment_id,
            'job_id' => $job_id,
            'event_type' => $event_type,
            'event_title' => $event_title,
            'event_description' => $event_description,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'performed_by' => $performed_by,
            'performed_by_name' => $performed_by_name,
            'metadata' => json_encode($metadata)
        ]);
    }
    
    /**
     * Calculate cost to job for equipment
     */
    public function calculate_cost_to_job($equipment_id) {
        $equipment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}pi_crm_equipment WHERE id = %d",
            $equipment_id
        ), ARRAY_A);
        
        if (!$equipment) return 0;
        
        $cost = 0;
        
        // Determine the date range for calculation
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
                // For owned equipment, could use depreciation or flat fee allocation
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
        
        // Update the stored cost
        $this->wpdb->update(
            $this->wpdb->prefix . 'pi_crm_equipment',
            ['cost_to_job' => $cost],
            ['id' => $equipment_id]
        );
        
        return $cost;
    }
    
    /**
     * Migrate existing equipment table to add new columns
     * This handles upgrading from the old basic schema to the enhanced schema
     */
    public function migrate_existing_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_equipment';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            error_log('[Equipment Migration] Table does not exist, will be created by create_tables()');
            return; // Table doesn't exist, will be created by create_tables()
        }
        
        // Get existing columns
        $existing_columns = $this->wpdb->get_col("DESCRIBE {$table_name}");
        error_log('[Equipment Migration] Existing columns: ' . implode(', ', $existing_columns));
        
        // Add columns one by one with error handling - fixed order and dependencies
        $columns_to_add = [
            // Base columns that other columns depend on
            'manufacturer' => "ALTER TABLE {$table_name} ADD COLUMN manufacturer varchar(100) DEFAULT NULL AFTER make",
            'daily_rate' => "ALTER TABLE {$table_name} ADD COLUMN daily_rate decimal(10,2) DEFAULT 0.00 AFTER rate_type",
            
            // Standard columns in correct order
            'category' => "ALTER TABLE {$table_name} ADD COLUMN category varchar(50) DEFAULT 'Heavy Machinery' AFTER equipment_type",
            'current_condition' => "ALTER TABLE {$table_name} ADD COLUMN current_condition varchar(20) DEFAULT 'Good' AFTER status",
            'brand' => "ALTER TABLE {$table_name} ADD COLUMN brand varchar(100) DEFAULT NULL AFTER manufacturer",
            'model_number' => "ALTER TABLE {$table_name} ADD COLUMN model_number varchar(100) DEFAULT NULL AFTER model",
            'vin' => "ALTER TABLE {$table_name} ADD COLUMN vin varchar(100) DEFAULT NULL AFTER serial_number",
            'asset_tag' => "ALTER TABLE {$table_name} ADD COLUMN asset_tag varchar(100) DEFAULT NULL AFTER vin",
            'year_of_manufacture' => "ALTER TABLE {$table_name} ADD COLUMN year_of_manufacture int(4) DEFAULT NULL AFTER asset_tag",
            'specifications' => "ALTER TABLE {$table_name} ADD COLUMN specifications text AFTER year_of_manufacture",
            'acquisition_type' => "ALTER TABLE {$table_name} ADD COLUMN acquisition_type varchar(50) DEFAULT 'Owned' AFTER specifications",
            'supplier_name' => "ALTER TABLE {$table_name} ADD COLUMN supplier_name varchar(200) DEFAULT NULL AFTER acquisition_type",
            'supplier_contact' => "ALTER TABLE {$table_name} ADD COLUMN supplier_contact varchar(200) DEFAULT NULL AFTER supplier_name",
            'supplier_email' => "ALTER TABLE {$table_name} ADD COLUMN supplier_email varchar(200) DEFAULT NULL AFTER supplier_contact",
            'supplier_phone' => "ALTER TABLE {$table_name} ADD COLUMN supplier_phone varchar(50) DEFAULT NULL AFTER supplier_email",
            'hire_reference_number' => "ALTER TABLE {$table_name} ADD COLUMN hire_reference_number varchar(100) DEFAULT NULL AFTER supplier_phone",
            'po_number' => "ALTER TABLE {$table_name} ADD COLUMN po_number varchar(100) DEFAULT NULL AFTER hire_reference_number",
            'rate_type' => "ALTER TABLE {$table_name} ADD COLUMN rate_type varchar(20) DEFAULT 'daily' AFTER po_number",
            'weekly_rate' => "ALTER TABLE {$table_name} ADD COLUMN weekly_rate decimal(10,2) DEFAULT 0.00 AFTER daily_rate",
            'monthly_rate' => "ALTER TABLE {$table_name} ADD COLUMN monthly_rate decimal(10,2) DEFAULT 0.00 AFTER weekly_rate",
            'flat_fee' => "ALTER TABLE {$table_name} ADD COLUMN flat_fee decimal(10,2) DEFAULT 0.00 AFTER monthly_rate",
            'cost_per_hour' => "ALTER TABLE {$table_name} ADD COLUMN cost_per_hour decimal(8,2) DEFAULT 0.00 AFTER flat_fee",
            'cost_to_job' => "ALTER TABLE {$table_name} ADD COLUMN cost_to_job decimal(12,2) DEFAULT 0.00 AFTER cost_per_hour",
            'deposit_held' => "ALTER TABLE {$table_name} ADD COLUMN deposit_held decimal(10,2) DEFAULT 0.00 AFTER cost_to_job",
            'insurance_included' => "ALTER TABLE {$table_name} ADD COLUMN insurance_included tinyint(1) DEFAULT 0 AFTER deposit_held",
            'fuel_policy' => "ALTER TABLE {$table_name} ADD COLUMN fuel_policy varchar(50) DEFAULT 'Excluded' AFTER insurance_included",
            'ownership_document' => "ALTER TABLE {$table_name} ADD COLUMN ownership_document varchar(500) DEFAULT NULL AFTER fuel_policy",
            'allocated_from_date' => "ALTER TABLE {$table_name} ADD COLUMN allocated_from_date datetime DEFAULT NULL AFTER ownership_document",
            'allocated_to_date' => "ALTER TABLE {$table_name} ADD COLUMN allocated_to_date datetime DEFAULT NULL AFTER allocated_from_date",
            'actual_on_site_date' => "ALTER TABLE {$table_name} ADD COLUMN actual_on_site_date datetime DEFAULT NULL AFTER allocated_to_date",
            'expected_return_date' => "ALTER TABLE {$table_name} ADD COLUMN expected_return_date datetime DEFAULT NULL AFTER actual_on_site_date",
            'actual_return_date' => "ALTER TABLE {$table_name} ADD COLUMN actual_return_date datetime DEFAULT NULL AFTER expected_return_date",
            'delivery_method' => "ALTER TABLE {$table_name} ADD COLUMN delivery_method varchar(50) DEFAULT 'Supplier Delivery' AFTER actual_return_date",
            'collection_method' => "ALTER TABLE {$table_name} ADD COLUMN collection_method varchar(50) DEFAULT 'Supplier Collection' AFTER delivery_method",
            'current_location_on_site' => "ALTER TABLE {$table_name} ADD COLUMN current_location_on_site varchar(200) DEFAULT NULL AFTER collection_method",
            'arrival_condition' => "ALTER TABLE {$table_name} ADD COLUMN arrival_condition varchar(20) DEFAULT 'Good' AFTER current_location_on_site",
            'return_condition' => "ALTER TABLE {$table_name} ADD COLUMN return_condition varchar(20) DEFAULT 'Good' AFTER arrival_condition",
            'condition_notes' => "ALTER TABLE {$table_name} ADD COLUMN condition_notes text AFTER return_condition",
            'arrival_photos' => "ALTER TABLE {$table_name} ADD COLUMN arrival_photos text AFTER condition_notes",
            'return_photos' => "ALTER TABLE {$table_name} ADD COLUMN return_photos text AFTER arrival_photos",
            'damage_photos' => "ALTER TABLE {$table_name} ADD COLUMN damage_photos text AFTER return_photos",
            'hours_meter_on_arrival' => "ALTER TABLE {$table_name} ADD COLUMN hours_meter_on_arrival decimal(10,2) DEFAULT 0.00 AFTER damage_photos",
            'hours_meter_reading' => "ALTER TABLE {$table_name} ADD COLUMN hours_meter_reading decimal(10,2) DEFAULT 0.00 AFTER hours_meter_on_arrival",
            'fuel_level_on_arrival' => "ALTER TABLE {$table_name} ADD COLUMN fuel_level_on_arrival varchar(20) DEFAULT NULL AFTER hours_meter_reading",
            'fuel_level_on_return' => "ALTER TABLE {$table_name} ADD COLUMN fuel_level_on_return varchar(20) DEFAULT NULL AFTER fuel_level_on_arrival",
            'fuel_refill_charge' => "ALTER TABLE {$table_name} ADD COLUMN fuel_refill_charge decimal(10,2) DEFAULT 0.00 AFTER fuel_level_on_return",
            'next_inspection_due' => "ALTER TABLE {$table_name} ADD COLUMN next_inspection_due datetime DEFAULT NULL AFTER fuel_refill_charge",
            'last_inspection_date' => "ALTER TABLE {$table_name} ADD COLUMN last_inspection_date datetime DEFAULT NULL AFTER next_inspection_due",
            'inspection_frequency' => "ALTER TABLE {$table_name} ADD COLUMN inspection_frequency varchar(20) DEFAULT 'Weekly' AFTER last_inspection_date",
            'assigned_operator_id' => "ALTER TABLE {$table_name} ADD COLUMN assigned_operator_id bigint(20) unsigned DEFAULT NULL AFTER inspection_frequency",
            'operator_certification_required' => "ALTER TABLE {$table_name} ADD COLUMN operator_certification_required tinyint(1) DEFAULT 0 AFTER assigned_operator_id",
            'operator_certification_verified' => "ALTER TABLE {$table_name} ADD COLUMN operator_certification_verified tinyint(1) DEFAULT 0 AFTER operator_certification_required",
            'operator_certification_expiry' => "ALTER TABLE {$table_name} ADD COLUMN operator_certification_expiry date DEFAULT NULL AFTER operator_certification_verified",
            'supervisor_responsible_id' => "ALTER TABLE {$table_name} ADD COLUMN supervisor_responsible_id bigint(20) unsigned DEFAULT NULL AFTER operator_certification_expiry",
            'check_in_by' => "ALTER TABLE {$table_name} ADD COLUMN check_in_by bigint(20) unsigned DEFAULT NULL AFTER supervisor_responsible_id",
            'check_in_date' => "ALTER TABLE {$table_name} ADD COLUMN check_in_date datetime DEFAULT NULL AFTER check_in_by",
            'check_out_by' => "ALTER TABLE {$table_name} ADD COLUMN check_out_by bigint(20) unsigned DEFAULT NULL AFTER check_in_date",
            'check_out_date' => "ALTER TABLE {$table_name} ADD COLUMN check_out_date datetime DEFAULT NULL AFTER check_out_by",
            'transfer_to_job_id' => "ALTER TABLE {$table_name} ADD COLUMN transfer_to_job_id bigint(20) unsigned DEFAULT NULL AFTER check_out_date",
            'notes' => "ALTER TABLE {$table_name} ADD COLUMN notes text AFTER transfer_to_job_id",
            'linked_expense_id' => "ALTER TABLE {$table_name} ADD COLUMN linked_expense_id bigint(20) unsigned DEFAULT NULL AFTER notes",
            'is_deleted' => "ALTER TABLE {$table_name} ADD COLUMN is_deleted tinyint(1) DEFAULT 0 AFTER linked_expense_id",
            'deleted_at' => "ALTER TABLE {$table_name} ADD COLUMN deleted_at datetime DEFAULT NULL AFTER is_deleted",
            'deleted_by' => "ALTER TABLE {$table_name} ADD COLUMN deleted_by bigint(20) unsigned DEFAULT NULL AFTER deleted_at",
        ];
        
        // Add columns that don't exist
        foreach ($columns_to_add as $column => $sql) {
            if (!in_array($column, $existing_columns)) {
                $this->wpdb->query($sql);
            }
        }
        
        // Migrate data from old fields to new fields
        // Copy equipment_name to internal_name where internal_name is empty
        $this->wpdb->query("UPDATE {$table_name} SET internal_name = equipment_name WHERE internal_name = '' OR internal_name IS NULL");
        
        // Copy make to manufacturer where manufacturer is empty
        $this->wpdb->query("UPDATE {$table_name} SET manufacturer = make WHERE manufacturer = '' OR manufacturer IS NULL");
        
        // Copy ownership_type to acquisition_type where acquisition_type is empty
        $this->wpdb->query("UPDATE {$table_name} SET acquisition_type = ownership_type WHERE acquisition_type = '' OR acquisition_type IS NULL");
        
        // Copy hire_start_date to allocated_from_date where allocated_from_date is empty
        $migrated_dates = $this->wpdb->query(
            "UPDATE {$table_name} 
            SET allocated_from_date = hire_start_date 
            WHERE (allocated_from_date IS NULL OR allocated_from_date = '') 
            AND hire_start_date IS NOT NULL 
            AND hire_start_date != ''"
        );
        error_log('[Equipment Migration] Migrated ' . $migrated_dates . ' records from hire_start_date to allocated_from_date');
        
        // Copy hire_end_date to allocated_to_date where allocated_to_date is empty
        $migrated_dates = $this->wpdb->query(
            "UPDATE {$table_name} 
            SET allocated_to_date = hire_end_date 
            WHERE (allocated_to_date IS NULL OR allocated_to_date = '') 
            AND hire_end_date IS NOT NULL 
            AND hire_end_date != ''"
        );
        error_log('[Equipment Migration] Migrated ' . $migrated_dates . ' records from hire_end_date to allocated_to_date');
        
        // Copy condition_notes to current_condition where current_condition is empty
        $migrated_condition = $this->wpdb->query(
            "UPDATE {$table_name} 
            SET current_condition = SUBSTRING(condition_notes, 1, 20) 
            WHERE (current_condition IS NULL OR current_condition = '' OR current_condition = 'Good') 
            AND condition_notes IS NOT NULL 
            AND condition_notes != ''"
        );
        error_log('[Equipment Migration] Migrated ' . $migrated_condition . ' records from condition_notes to current_condition');
        
        // Set default status where missing
        $this->wpdb->query("UPDATE {$table_name} SET status = 'On-Site' WHERE status = '' OR status IS NULL");
    }
}
