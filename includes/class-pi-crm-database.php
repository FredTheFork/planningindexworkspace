<?php
/**
 * Planning Index CRM - Database Schema
 * Comprehensive construction CRM database tables
 */

if (!defined('ABSPATH')) exit;

class PI_CRM_Database {
    
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
     * Create all CRM tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Communications - Emails, SMS, Calls
        $this->create_communications_table();
        
        // Documents - Drawings, specs, contracts
        $this->create_documents_table();
        
        // Timesheets - Worker time tracking
        $this->create_timesheets_table();
        
        // Safety - Inspections, incidents, checklists
        $this->create_safety_tables();
        
        // Quality - Snagging, punch lists, sign-offs
        $this->create_quality_tables();
        
        // Change Orders
        $this->create_change_orders_table();
        
        // Invoices & Payments
        $this->create_invoicing_tables();
        
        // Daily Reports
        $this->create_daily_reports_table();
        
        // Equipment & Plant
        $this->create_equipment_tables();
        
        // Subcontractors
        $this->create_subcontractors_table();
        
        // Client Management
        $this->create_client_details_table();
        
        // Job Photos
        $this->create_job_photos_table();
        
        // RFIs & Submittals
        $this->create_rfi_submittal_tables();
        
        // Site Locations for Map
        $this->create_site_locations_table();
        
        // Team Assignments
        $this->create_team_assignments_table();
        
        // Certifications & Training
        $this->create_certifications_table();
        
        // Weather Data
        $this->create_weather_data_table();
        
        // Team & Timesheets Enhanced Tables
        $this->create_employees_table();
        $this->create_crews_table();
        $this->create_crew_members_table();
        $this->create_clock_status_table();
        $this->create_timesheet_approvals_table();
        $this->create_cost_codes_table();
        
        // Safety Module Enhanced Tables
        $this->add_safety_columns_to_existing_tables();
        $this->create_safety_checklist_templates_table();
        $this->create_permits_to_work_table();
        $this->create_job_hazard_analyses_table();
        $this->create_toolbox_talks_table();
        $this->create_safety_observations_table();
        $this->create_ppe_inventory_table();
        $this->create_safety_meetings_table();
        $this->create_safety_activity_table();
    }
    
    private function create_employees_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_employees';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            employee_code varchar(50) DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(200) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            mobile varchar(50) DEFAULT NULL,
            role varchar(100) NOT NULL,
            trade varchar(100) DEFAULT NULL,
            skill_level varchar(20) DEFAULT 'skilled',
            employment_type varchar(20) DEFAULT 'full_time',
            hourly_rate decimal(8,2) DEFAULT 0.00,
            salary decimal(10,2) DEFAULT NULL,
            default_cost_code varchar(50) DEFAULT NULL,
            department varchar(100) DEFAULT NULL,
            hire_date date DEFAULT NULL,
            termination_date date DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            avatar_url varchar(500) DEFAULT NULL,
            emergency_contact_name varchar(200) DEFAULT NULL,
            emergency_contact_phone varchar(50) DEFAULT NULL,
            address text,
            postcode varchar(20) DEFAULT NULL,
            ni_number varchar(50) DEFAULT NULL,
            utr_number varchar(50) DEFAULT NULL,
            cis_verified tinyint(1) DEFAULT 0,
            cis_status varchar(20) DEFAULT NULL,
            permissions_level varchar(20) DEFAULT 'field_worker',
            can_approve_timesheets tinyint(1) DEFAULT 0,
            can_approve_expenses tinyint(1) DEFAULT 0,
            max_approval_amount decimal(10,2) DEFAULT 0.00,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_active_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            UNIQUE KEY email (email),
            KEY employee_code (employee_code),
            KEY role (role),
            KEY trade (trade),
            KEY status (status),
            KEY permissions_level (permissions_level)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_crews_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_crews';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            crew_name varchar(200) NOT NULL,
            crew_code varchar(50) DEFAULT NULL,
            foreman_id bigint(20) unsigned DEFAULT NULL,
            default_job_id bigint(20) unsigned DEFAULT NULL,
            trade_specialty varchar(100) DEFAULT NULL,
            description text,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY foreman_id (foreman_id),
            KEY default_job_id (default_job_id),
            KEY status (status)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_crew_members_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_crew_members';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            crew_id bigint(20) unsigned NOT NULL,
            employee_id bigint(20) unsigned NOT NULL,
            role_in_crew varchar(100) DEFAULT 'member',
            is_lead_hand tinyint(1) DEFAULT 0,
            joined_at datetime DEFAULT CURRENT_TIMESTAMP,
            left_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY crew_employee (crew_id, employee_id),
            KEY crew_id (crew_id),
            KEY employee_id (employee_id),
            KEY status (status)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_clock_status_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_clock_status';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'clocked_out',
            clocked_in_at datetime DEFAULT NULL,
            clocked_out_at datetime DEFAULT NULL,
            current_timesheet_id bigint(20) unsigned DEFAULT NULL,
            gps_lat decimal(10,8) DEFAULT NULL,
            gps_lng decimal(11,8) DEFAULT NULL,
            gps_accuracy decimal(8,2) DEFAULT NULL,
            location_verified tinyint(1) DEFAULT 0,
            device_info varchar(200) DEFAULT NULL,
            break_started_at datetime DEFAULT NULL,
            break_minutes int(11) DEFAULT 0,
            expected_hours_today decimal(5,2) DEFAULT 8.00,
            notes text,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_id (employee_id),
            KEY status (status),
            KEY job_id (job_id),
            KEY clocked_in_at (clocked_in_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_timesheet_approvals_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_timesheet_approvals';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timesheet_id bigint(20) unsigned NOT NULL,
            employee_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            submitted_by bigint(20) unsigned NOT NULL,
            status varchar(20) DEFAULT 'pending',
            approver_id bigint(20) unsigned DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            rejection_reason text,
            approval_notes text,
            batch_id varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY timesheet_id (timesheet_id),
            KEY employee_id (employee_id),
            KEY job_id (job_id),
            KEY status (status),
            KEY batch_id (batch_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_cost_codes_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_cost_codes';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            description varchar(300) NOT NULL,
            category varchar(100) DEFAULT NULL,
            division varchar(50) DEFAULT NULL,
            hourly_rate decimal(8,2) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY category (category),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Insert default construction cost codes
        $this->seed_default_cost_codes($table_name);
    }
    
    private function seed_default_cost_codes($table_name) {
        $codes = array(
            array('code' => 'GEN-001', 'description' => 'General Labour', 'category' => 'General', 'hourly_rate' => 15.00),
            array('code' => 'CAR-001', 'description' => 'Carpenter - 1st Fix', 'category' => 'Carpentry', 'hourly_rate' => 22.00),
            array('code' => 'CAR-002', 'description' => 'Carpenter - 2nd Fix', 'category' => 'Carpentry', 'hourly_rate' => 22.00),
            array('code' => 'BRK-001', 'description' => 'Bricklayer', 'category' => 'Masonry', 'hourly_rate' => 24.00),
            array('code' => 'ELEC-001', 'description' => 'Electrician', 'category' => 'Electrical', 'hourly_rate' => 28.00),
            array('code' => 'PLUMB-001', 'description' => 'Plumber', 'category' => 'Plumbing', 'hourly_rate' => 28.00),
            array('code' => 'HVAC-001', 'description' => 'HVAC Technician', 'category' => 'Mechanical', 'hourly_rate' => 26.00),
            array('code' => 'PAINT-001', 'description' => 'Painter & Decorator', 'category' => 'Finishes', 'hourly_rate' => 20.00),
            array('code' => 'TIL-001', 'description' => 'Tiler', 'category' => 'Finishes', 'hourly_rate' => 22.00),
            array('code' => 'ROOF-001', 'description' => 'Roofer', 'category' => 'Roofing', 'hourly_rate' => 24.00),
            array('code' => 'STEEL-001', 'description' => 'Steel Fixer', 'category' => 'Structural', 'hourly_rate' => 25.00),
            array('code' => 'CONC-001', 'description' => 'Concrete Worker', 'category' => 'Concrete', 'hourly_rate' => 21.00),
            array('code' => 'SCAF-001', 'description' => 'Scaffolder', 'category' => 'Access', 'hourly_rate' => 23.00),
            array('code' => 'FLOOR-001', 'description' => 'Flooring Specialist', 'category' => 'Finishes', 'hourly_rate' => 22.00),
            array('code' => 'DRYW-001', 'description' => 'Drywall/Plasterer', 'category' => 'Finishes', 'hourly_rate' => 21.00),
            array('code' => 'SITE-001', 'description' => 'Site Manager', 'category' => 'Management', 'hourly_rate' => 35.00),
            array('code' => 'FORE-001', 'description' => 'Foreman', 'category' => 'Management', 'hourly_rate' => 28.00),
            array('code' => 'SUPER-001', 'description' => 'Superintendent', 'category' => 'Management', 'hourly_rate' => 40.00),
            array('code' => 'LAB-001', 'description' => 'General Labourer', 'category' => 'Labour', 'hourly_rate' => 14.00),
            array('code' => 'SKILL-001', 'description' => 'Skilled Labourer', 'category' => 'Labour', 'hourly_rate' => 16.00),
            array('code' => 'DEMO-001', 'description' => 'Demolition Worker', 'category' => 'Demolition', 'hourly_rate' => 18.00),
            array('code' => 'GLAZ-001', 'description' => 'Glazier', 'category' => 'Glazing', 'hourly_rate' => 24.00),
            array('code' => 'INSUL-001', 'description' => 'Insulation Installer', 'category' => 'Insulation', 'hourly_rate' => 20.00),
            array('code' => 'FENCE-001', 'description' => 'Fencing Contractor', 'category' => 'External', 'hourly_rate' => 21.00),
            array('code' => 'LAND-001', 'description' => 'Landscaper', 'category' => 'External', 'hourly_rate' => 19.00)
        );
        
        foreach ($codes as $code) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE code = %s",
                $code['code']
            ));
            if (!$exists) {
                $this->wpdb->insert($table_name, $code);
            }
        }
    }
    
    private function create_communications_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_communications';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'email',
            direction varchar(10) NOT NULL DEFAULT 'outbound',
            subject varchar(500) DEFAULT NULL,
            content longtext,
            recipient_name varchar(200) DEFAULT NULL,
            recipient_email varchar(200) DEFAULT NULL,
            recipient_phone varchar(50) DEFAULT NULL,
            sender_name varchar(200) DEFAULT NULL,
            sender_email varchar(200) DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            sent_at datetime DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            attachments text,
            thread_id varchar(100) DEFAULT NULL,
            parent_id bigint(20) unsigned DEFAULT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY type (type),
            KEY status (status),
            KEY thread_id (thread_id),
            KEY created_at (created_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_documents_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_documents';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            title varchar(300) NOT NULL,
            description text,
            file_name varchar(300) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            category varchar(50) DEFAULT 'general',
            version varchar(20) DEFAULT '1.0',
            is_latest tinyint(1) DEFAULT 1,
            parent_document_id bigint(20) unsigned DEFAULT NULL,
            tags text,
            metadata longtext,
            uploaded_by bigint(20) unsigned NOT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY category (category),
            KEY file_type (file_type),
            KEY is_latest (is_latest),
            KEY uploaded_at (uploaded_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_timesheets_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_timesheets';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            worker_id bigint(20) unsigned NOT NULL,
            worker_name varchar(200) NOT NULL,
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
            photos text,
            notes text,
            gps_coordinates varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY worker_id (worker_id),
            KEY work_date (work_date),
            KEY status (status),
            KEY cost_code (cost_code)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_safety_tables() {
        // Safety Inspections
        $inspections_table = $this->wpdb->prefix . 'pi_crm_safety_inspections';
        $sql = "CREATE TABLE IF NOT EXISTS {$inspections_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            inspection_type varchar(50) NOT NULL,
            title varchar(300) NOT NULL,
            description text,
            inspector_name varchar(200) NOT NULL,
            inspector_id bigint(20) unsigned DEFAULT NULL,
            inspection_date date NOT NULL,
            next_due_date date DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            findings longtext,
            corrective_actions longtext,
            risk_level varchar(20) DEFAULT 'low',
            score int(11) DEFAULT NULL,
            attachments text,
            signed_by varchar(200) DEFAULT NULL,
            signature_image varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY inspection_type (inspection_type),
            KEY status (status),
            KEY inspection_date (inspection_date),
            KEY next_due_date (next_due_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Safety Incidents
        $incidents_table = $this->wpdb->prefix . 'pi_crm_safety_incidents';
        $sql = "CREATE TABLE IF NOT EXISTS {$incidents_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            incident_date datetime NOT NULL,
            reported_by varchar(200) NOT NULL,
            reporter_id bigint(20) unsigned DEFAULT NULL,
            incident_type varchar(50) NOT NULL,
            severity varchar(20) DEFAULT 'minor',
            description longtext NOT NULL,
            persons_involved text,
            injuries text,
            witnesses text,
            immediate_action text,
            root_cause text,
            corrective_action text,
            status varchar(20) DEFAULT 'open',
            reported_to varchar(200) DEFAULT NULL,
            rIDDOR_reportable tinyint(1) DEFAULT 0,
            rIDDOR_reference varchar(100) DEFAULT NULL,
            photos text,
            attachments text,
            closed_at datetime DEFAULT NULL,
            closed_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY incident_type (incident_type),
            KEY severity (severity),
            KEY status (status),
            KEY rIDDOR_reportable (rIDDOR_reportable)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Checklist Items
        $checklists_table = $this->wpdb->prefix . 'pi_crm_safety_checklists';
        $sql = "CREATE TABLE IF NOT EXISTS {$checklists_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            inspection_id bigint(20) unsigned NOT NULL,
            category varchar(100) NOT NULL,
            item_text varchar(500) NOT NULL,
            is_checked tinyint(1) DEFAULT 0,
            notes text,
            photo_url varchar(500) DEFAULT NULL,
            priority varchar(20) DEFAULT 'normal',
            PRIMARY KEY (id),
            KEY inspection_id (inspection_id),
            KEY category (category)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_quality_tables() {
        // Snag Lists / Punch Lists
        $snags_table = $this->wpdb->prefix . 'pi_crm_quality_snags';
        $sql = "CREATE TABLE IF NOT EXISTS {$snags_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            title varchar(300) NOT NULL,
            description text,
            location varchar(200) DEFAULT NULL,
            category varchar(50) DEFAULT 'general',
            priority varchar(20) DEFAULT 'medium',
            status varchar(20) DEFAULT 'open',
            identified_by varchar(200) NOT NULL,
            identifier_id bigint(20) unsigned DEFAULT NULL,
            identified_date date NOT NULL,
            assigned_to varchar(200) DEFAULT NULL,
            assignee_id bigint(20) unsigned DEFAULT NULL,
            due_date date DEFAULT NULL,
            completed_date date DEFAULT NULL,
            completed_by varchar(200) DEFAULT NULL,
            completion_notes text,
            before_photos text,
            after_photos text,
            cost_estimate decimal(10,2) DEFAULT 0.00,
            actual_cost decimal(10,2) DEFAULT 0.00,
            sign_off_required tinyint(1) DEFAULT 0,
            signed_off_by varchar(200) DEFAULT NULL,
            signed_off_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY status (status),
            KEY priority (priority),
            KEY category (category),
            KEY due_date (due_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Sign-offs / Approvals
        $signoffs_table = $this->wpdb->prefix . 'pi_crm_quality_signoffs';
        $sql = "CREATE TABLE IF NOT EXISTS {$signoffs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            signoff_type varchar(50) NOT NULL,
            title varchar(300) NOT NULL,
            description text,
            stage varchar(100) DEFAULT NULL,
            requested_by bigint(20) unsigned NOT NULL,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            approver_name varchar(200) NOT NULL,
            approver_id bigint(20) unsigned DEFAULT NULL,
            approver_email varchar(200) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            decision varchar(20) DEFAULT NULL,
            comments text,
            signed_at datetime DEFAULT NULL,
            signature_image varchar(500) DEFAULT NULL,
            attachments text,
            expiry_date date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY signoff_type (signoff_type),
            KEY status (status),
            KEY approver_id (approver_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_change_orders_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_change_orders';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            co_number varchar(50) NOT NULL,
            title varchar(300) NOT NULL,
            description longtext,
            reason text,
            requested_by varchar(200) NOT NULL,
            requester_id bigint(20) unsigned DEFAULT NULL,
            request_date date NOT NULL,
            status varchar(20) DEFAULT 'draft',
            impact_scope text,
            schedule_impact_days int(11) DEFAULT 0,
            cost_impact decimal(12,2) DEFAULT 0.00,
            original_contract_value decimal(12,2) DEFAULT 0.00,
            revised_contract_value decimal(12,2) DEFAULT 0.00,
            attachments text,
            approved_by varchar(200) DEFAULT NULL,
            approver_id bigint(20) unsigned DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            client_approved tinyint(1) DEFAULT 0,
            client_approved_at datetime DEFAULT NULL,
            client_signature varchar(500) DEFAULT NULL,
            implemented_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY co_number (co_number),
            KEY job_id (job_id),
            KEY status (status),
            KEY request_date (request_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_invoicing_tables() {
        // Invoices
        $invoices_table = $this->wpdb->prefix . 'pi_crm_invoices';
        $sql = "CREATE TABLE IF NOT EXISTS {$invoices_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            invoice_number varchar(50) NOT NULL,
            invoice_type varchar(30) DEFAULT 'progress',
            issue_date date NOT NULL,
            due_date date NOT NULL,
            description text,
            subtotal decimal(12,2) DEFAULT 0.00,
            tax_rate decimal(5,2) DEFAULT 20.00,
            tax_amount decimal(12,2) DEFAULT 0.00,
            total_amount decimal(12,2) DEFAULT 0.00,
            retention_percentage decimal(5,2) DEFAULT 0.00,
            retention_amount decimal(12,2) DEFAULT 0.00,
            net_amount decimal(12,2) DEFAULT 0.00,
            status varchar(20) DEFAULT 'draft',
            sent_at datetime DEFAULT NULL,
            paid_amount decimal(12,2) DEFAULT 0.00,
            paid_at datetime DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL,
            payment_reference varchar(200) DEFAULT NULL,
            client_po_reference varchar(100) DEFAULT NULL,
            pdf_path varchar(500) DEFAULT NULL,
            notes text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY job_id (job_id),
            KEY status (status),
            KEY issue_date (issue_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Invoice Line Items
        $line_items_table = $this->wpdb->prefix . 'pi_crm_invoice_items';
        $sql = "CREATE TABLE IF NOT EXISTS {$line_items_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            description varchar(500) NOT NULL,
            quantity decimal(10,2) DEFAULT 1.00,
            unit varchar(50) DEFAULT 'each',
            unit_price decimal(12,2) DEFAULT 0.00,
            total_price decimal(12,2) DEFAULT 0.00,
            cost_code varchar(50) DEFAULT NULL,
            previous_billed decimal(12,2) DEFAULT 0.00,
            this_bill decimal(12,2) DEFAULT 0.00,
            percent_complete decimal(5,2) DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY cost_code (cost_code)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_daily_reports_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_daily_reports';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            report_date date NOT NULL,
            report_number varchar(50) DEFAULT NULL,
            weather_conditions varchar(200) DEFAULT NULL,
            weather_temp_high int(11) DEFAULT NULL,
            weather_temp_low int(11) DEFAULT NULL,
            weather_precipitation varchar(50) DEFAULT NULL,
            weather_wind_speed int(11) DEFAULT NULL,
            workforce_count int(11) DEFAULT 0,
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
            submitted_by bigint(20) unsigned NOT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY report_date (report_date),
            KEY submitted_by (submitted_by)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_equipment_tables() {
        // Equipment
        $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
        $sql = "CREATE TABLE IF NOT EXISTS {$equipment_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned DEFAULT NULL,
            equipment_name varchar(200) NOT NULL,
            equipment_type varchar(100) NOT NULL,
            make varchar(100) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            serial_number varchar(100) DEFAULT NULL,
            asset_number varchar(100) DEFAULT NULL,
            ownership_type varchar(20) DEFAULT 'owned',
            hire_company varchar(200) DEFAULT NULL,
            hire_rate decimal(10,2) DEFAULT 0.00,
            hire_start_date date DEFAULT NULL,
            hire_end_date date DEFAULT NULL,
            operator_required tinyint(1) DEFAULT 0,
            certified_operator_required tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'available',
            condition_notes text,
            last_service_date date DEFAULT NULL,
            next_service_due date DEFAULT NULL,
            service_interval_days int(11) DEFAULT 90,
            insurance_expiry date DEFAULT NULL,
            test_certificate_expiry date DEFAULT NULL,
            gps_tracking_id varchar(100) DEFAULT NULL,
            photos text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY equipment_type (equipment_type),
            KEY status (status),
            KEY next_service_due (next_service_due)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Equipment Usage Log
        $usage_table = $this->wpdb->prefix . 'pi_crm_equipment_usage';
        $sql = "CREATE TABLE IF NOT EXISTS {$usage_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            usage_date date NOT NULL,
            hours_used decimal(5,2) DEFAULT 0.00,
            operator_name varchar(200) DEFAULT NULL,
            operator_id bigint(20) unsigned DEFAULT NULL,
            fuel_used decimal(8,2) DEFAULT 0.00,
            fuel_cost decimal(10,2) DEFAULT 0.00,
            maintenance_required tinyint(1) DEFAULT 0,
            maintenance_notes text,
            condition_on_return varchar(50) DEFAULT 'good',
            photos text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY job_id (job_id),
            KEY usage_date (usage_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_subcontractors_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_job_subcontractors';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            subcontractor_name varchar(200) NOT NULL,
            company_name varchar(200) DEFAULT NULL,
            trade varchar(100) NOT NULL,
            contact_name varchar(200) DEFAULT NULL,
            contact_email varchar(200) DEFAULT NULL,
            contact_phone varchar(50) DEFAULT NULL,
            contract_value decimal(12,2) DEFAULT 0.00,
            contract_scope text,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            insurance_verified tinyint(1) DEFAULT 0,
            insurance_expiry date DEFAULT NULL,
            cis_verified tinyint(1) DEFAULT 0,
            cis_status varchar(20) DEFAULT NULL,
            health_safety_file tinyint(1) DEFAULT 0,
            method_statements text,
            risk_assessments text,
            status varchar(20) DEFAULT 'active',
            performance_rating int(11) DEFAULT NULL,
            payment_terms varchar(50) DEFAULT '30 days',
            invoices_submitted decimal(12,2) DEFAULT 0.00,
            invoices_paid decimal(12,2) DEFAULT 0.00,
            retention_held decimal(12,2) DEFAULT 0.00,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY trade (trade),
            KEY status (status),
            KEY insurance_expiry (insurance_expiry)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_client_details_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_client_details';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            client_type varchar(50) DEFAULT 'residential',
            company_name varchar(200) DEFAULT NULL,
            primary_contact_name varchar(200) NOT NULL,
            primary_contact_email varchar(200) DEFAULT NULL,
            primary_contact_phone varchar(50) DEFAULT NULL,
            secondary_contact_name varchar(200) DEFAULT NULL,
            secondary_contact_email varchar(200) DEFAULT NULL,
            secondary_contact_phone varchar(50) DEFAULT NULL,
            billing_address text,
            site_address text,
            preferences text,
            special_requirements text,
            access_restrictions text,
            decision_maker varchar(200) DEFAULT NULL,
            budget_range varchar(100) DEFAULT NULL,
            funding_status varchar(50) DEFAULT NULL,
            previous_work_history text,
            referral_source varchar(100) DEFAULT NULL,
            communication_preferences varchar(50) DEFAULT 'email',
            notification_frequency varchar(20) DEFAULT 'weekly',
            gdpr_consent tinyint(1) DEFAULT 0,
            gdpr_consent_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY client_type (client_type),
            KEY primary_contact_email (primary_contact_email)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_job_photos_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_job_photos';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            photo_type varchar(50) DEFAULT 'general',
            category varchar(50) DEFAULT 'site',
            title varchar(300) DEFAULT NULL,
            description text,
            file_name varchar(300) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            taken_by varchar(200) DEFAULT NULL,
            photographer_id bigint(20) unsigned DEFAULT NULL,
            taken_at datetime DEFAULT NULL,
            gps_latitude decimal(10,8) DEFAULT NULL,
            gps_longitude decimal(11,8) DEFAULT NULL,
            is_before_photo tinyint(1) DEFAULT 0,
            is_after_photo tinyint(1) DEFAULT 0,
            related_photo_id bigint(20) unsigned DEFAULT NULL,
            tags text,
            visibility varchar(20) DEFAULT 'all',
            approved_for_client tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY photo_type (photo_type),
            KEY category (category),
            KEY taken_at (taken_at),
            KEY is_before_photo (is_before_photo),
            KEY is_after_photo (is_after_photo)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_rfi_submittal_tables() {
        // RFIs - Enhanced Table
        $rfi_table = $this->wpdb->prefix . 'pi_crm_rfi';
        $sql = "CREATE TABLE IF NOT EXISTS {$rfi_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            rfi_number varchar(50) NOT NULL,
            title varchar(300) NOT NULL,
            description longtext,
            suggested_solution longtext,
            priority varchar(20) DEFAULT 'normal',
            status varchar(20) DEFAULT 'draft',
            requested_by bigint(20) unsigned NOT NULL,
            requested_by_name varchar(200) DEFAULT NULL,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            assigned_to varchar(200) DEFAULT NULL,
            assignee_id bigint(20) unsigned DEFAULT NULL,
            assignee_role varchar(100) DEFAULT NULL,
            due_date date DEFAULT NULL,
            drawing_references text,
            specification_division varchar(50) DEFAULT NULL,
            specification_section varchar(50) DEFAULT NULL,
            schedule_impact tinyint(1) DEFAULT 0,
            schedule_impact_days int(11) DEFAULT 0,
            cost_impact tinyint(1) DEFAULT 0,
            cost_impact_amount decimal(12,2) DEFAULT 0.00,
            response longtext,
            responded_by bigint(20) unsigned DEFAULT NULL,
            responded_by_name varchar(200) DEFAULT NULL,
            responded_at datetime DEFAULT NULL,
            closed_by bigint(20) unsigned DEFAULT NULL,
            closed_at datetime DEFAULT NULL,
            attachments text,
            ball_in_court varchar(50) DEFAULT 'contractor',
            ball_in_court_id bigint(20) unsigned DEFAULT NULL,
            related_task_id bigint(20) unsigned DEFAULT NULL,
            related_change_order_id bigint(20) unsigned DEFAULT NULL,
            related_submittal_id bigint(20) unsigned DEFAULT NULL,
            email_thread_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY rfi_number (rfi_number),
            KEY job_id (job_id),
            KEY status (status),
            KEY priority (priority),
            KEY due_date (due_date),
            KEY requested_by (requested_by),
            KEY ball_in_court (ball_in_court),
            KEY related_task_id (related_task_id),
            KEY related_change_order_id (related_change_order_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // RFI Activity Log
        $rfi_activity_table = $this->wpdb->prefix . 'pi_crm_rfi_activity';
        $sql = "CREATE TABLE IF NOT EXISTS {$rfi_activity_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rfi_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            activity_type varchar(50) NOT NULL,
            description text,
            old_value text,
            new_value text,
            performed_by bigint(20) unsigned NOT NULL,
            performed_by_name varchar(200) DEFAULT NULL,
            performed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rfi_id (rfi_id),
            KEY job_id (job_id),
            KEY activity_type (activity_type),
            KEY performed_at (performed_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // RFI Comments
        $rfi_comments_table = $this->wpdb->prefix . 'pi_crm_rfi_comments';
        $sql = "CREATE TABLE IF NOT EXISTS {$rfi_comments_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rfi_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            comment text NOT NULL,
            commented_by bigint(20) unsigned NOT NULL,
            commented_by_name varchar(200) DEFAULT NULL,
            commented_at datetime DEFAULT CURRENT_TIMESTAMP,
            parent_id bigint(20) unsigned DEFAULT NULL,
            attachments text,
            PRIMARY KEY (id),
            KEY rfi_id (rfi_id),
            KEY job_id (job_id),
            KEY parent_id (parent_id),
            KEY commented_at (commented_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Submittals - Enhanced Table
        $submittal_table = $this->wpdb->prefix . 'pi_crm_submittals';
        $sql = "CREATE TABLE IF NOT EXISTS {$submittal_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            submittal_number varchar(50) NOT NULL,
            title varchar(300) NOT NULL,
            description longtext,
            submittal_type varchar(50) DEFAULT 'product_data',
            specification_division varchar(50) DEFAULT NULL,
            specification_section varchar(50) DEFAULT NULL,
            specification_title varchar(200) DEFAULT NULL,
            subcontractor_id bigint(20) unsigned DEFAULT NULL,
            subcontractor_name varchar(200) DEFAULT NULL,
            submitted_by bigint(20) unsigned NOT NULL,
            submitted_by_name varchar(200) DEFAULT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            required_approvers text,
            review_due_date date DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            priority varchar(20) DEFAULT 'normal',
            revision_number varchar(10) DEFAULT '0',
            attachments text,
            ball_in_court varchar(50) DEFAULT 'subcontractor',
            ball_in_court_id bigint(20) unsigned DEFAULT NULL,
            final_response text,
            approved_by bigint(20) unsigned DEFAULT NULL,
            approved_by_name varchar(200) DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            rejected_by bigint(20) unsigned DEFAULT NULL,
            rejected_reason text,
            distributed_to text,
            related_rfi_ids text,
            related_material_ids text,
            related_task_ids text,
            email_thread_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY submittal_number (submittal_number),
            KEY job_id (job_id),
            KEY status (status),
            KEY priority (priority),
            KEY review_due_date (review_due_date),
            KEY subcontractor_id (subcontractor_id),
            KEY submittal_type (submittal_type)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Submittal Revision History
        $submittal_revisions_table = $this->wpdb->prefix . 'pi_crm_submittal_revisions';
        $sql = "CREATE TABLE IF NOT EXISTS {$submittal_revisions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submittal_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            revision_number varchar(10) NOT NULL,
            title varchar(300) NOT NULL,
            description longtext,
            attachments text,
            submitted_by bigint(20) unsigned NOT NULL,
            submitted_by_name varchar(200) DEFAULT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            change_description text,
            PRIMARY KEY (id),
            KEY submittal_id (submittal_id),
            KEY job_id (job_id),
            KEY revision_number (revision_number)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Submittal Review Comments
        $submittal_reviews_table = $this->wpdb->prefix . 'pi_crm_submittal_reviews';
        $sql = "CREATE TABLE IF NOT EXISTS {$submittal_reviews_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submittal_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            reviewer_id bigint(20) unsigned NOT NULL,
            reviewer_name varchar(200) DEFAULT NULL,
            reviewer_role varchar(100) DEFAULT NULL,
            decision varchar(20) NOT NULL,
            comments text,
            pdf_annotations text,
            reviewed_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_consolidated tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY submittal_id (submittal_id),
            KEY job_id (job_id),
            KEY reviewer_id (reviewer_id),
            KEY decision (decision),
            KEY reviewed_at (reviewed_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Submittal Activity Log
        $submittal_activity_table = $this->wpdb->prefix . 'pi_crm_submittal_activity';
        $sql = "CREATE TABLE IF NOT EXISTS {$submittal_activity_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submittal_id bigint(20) unsigned NOT NULL,
            job_id bigint(20) unsigned NOT NULL,
            activity_type varchar(50) NOT NULL,
            description text,
            old_value text,
            new_value text,
            performed_by bigint(20) unsigned NOT NULL,
            performed_by_name varchar(200) DEFAULT NULL,
            performed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submittal_id (submittal_id),
            KEY job_id (job_id),
            KEY activity_type (activity_type),
            KEY performed_at (performed_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // RFI/Submittal Notifications Queue
        $notifications_table = $this->wpdb->prefix . 'pi_crm_notifications';
        $sql = "CREATE TABLE IF NOT EXISTS {$notifications_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            item_type varchar(20) NOT NULL,
            item_id bigint(20) unsigned NOT NULL,
            notification_type varchar(50) NOT NULL,
            recipient_id bigint(20) unsigned NOT NULL,
            recipient_email varchar(200) NOT NULL,
            subject varchar(500) DEFAULT NULL,
            message longtext,
            status varchar(20) DEFAULT 'pending',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY item_type_item_id (item_type, item_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_site_locations_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_site_locations';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            location_type varchar(50) DEFAULT 'site',
            address text NOT NULL,
            city varchar(100) DEFAULT NULL,
            postcode varchar(20) DEFAULT NULL,
            country varchar(50) DEFAULT 'UK',
            latitude decimal(10,8) DEFAULT NULL,
            longitude decimal(11,8) DEFAULT NULL,
            what3words varchar(100) DEFAULT NULL,
            access_instructions text,
            parking_info text,
            site_contact_name varchar(200) DEFAULT NULL,
            site_contact_phone varchar(50) DEFAULT NULL,
            site_hours varchar(200) DEFAULT NULL,
            security_requirements text,
            ppe_requirements text,
            map_zoom int(11) DEFAULT 15,
            geofence_radius int(11) DEFAULT 100,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY location_type (location_type),
            KEY latitude (latitude),
            KEY longitude (longitude)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_team_assignments_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_team_assignments';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
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
            KEY user_id (user_id),
            KEY role (role),
            KEY status (status),
            KEY start_date (start_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_certifications_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_certifications';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
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
            KEY user_id (user_id),
            KEY expiry_date (expiry_date),
            KEY status (status)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    private function create_weather_data_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_weather_data';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            forecast_date date NOT NULL,
            fetched_at datetime DEFAULT CURRENT_TIMESTAMP,
            location_name varchar(200) DEFAULT NULL,
            latitude decimal(10,8) DEFAULT NULL,
            longitude decimal(11,8) DEFAULT NULL,
            temperature_high decimal(4,1) DEFAULT NULL,
            temperature_low decimal(4,1) DEFAULT NULL,
            feels_like decimal(4,1) DEFAULT NULL,
            humidity int(11) DEFAULT NULL,
            precipitation_chance int(11) DEFAULT NULL,
            precipitation_amount decimal(5,2) DEFAULT NULL,
            wind_speed decimal(5,2) DEFAULT NULL,
            wind_direction varchar(10) DEFAULT NULL,
            visibility decimal(5,2) DEFAULT NULL,
            uv_index int(11) DEFAULT NULL,
            condition_code varchar(50) DEFAULT NULL,
            condition_text varchar(100) DEFAULT NULL,
            weather_code int(11) DEFAULT NULL,
            icon_url varchar(300) DEFAULT NULL,
            alerts text,
            work_impact_score int(11) DEFAULT 5,
            work_recommendation text,
            raw_data longtext,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY forecast_date (forecast_date),
            KEY fetched_at (fetched_at)
        ) {$this->charset_collate};";
        dbDelta($sql);

        // Check if weather_code column exists, add it if not
        $column_exists = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'weather_code'");
        if (empty($column_exists)) {
            $this->wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN weather_code int(11) DEFAULT NULL AFTER condition_text");
        }
    }
    
    /**
     * Safety Module - Add columns to existing tables
     */
    private function add_safety_columns_to_existing_tables() {
        // pi_crm_employees - Add safety-related columns
        $employees_table = $this->wpdb->prefix . 'pi_crm_employees';
        $this->add_column_if_not_exists($employees_table, 'blood_type', 'VARCHAR(10) DEFAULT NULL');
        $this->add_column_if_not_exists($employees_table, 'allergies', 'VARCHAR(500) DEFAULT NULL');
        $this->add_column_if_not_exists($employees_table, 'site_access_cleared', 'TINYINT(1) DEFAULT 1');
        
        // pi_crm_safety_incidents - Add enhanced columns
        $incidents_table = $this->wpdb->prefix . 'pi_crm_safety_incidents';
        $this->add_column_if_not_exists($incidents_table, 'location_on_site', 'VARCHAR(500) DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'geo_coordinates', 'VARCHAR(100) DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'classification', 'VARCHAR(50) DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'osha_reportable', 'TINYINT(1) DEFAULT 0');
        $this->add_column_if_not_exists($incidents_table, 'osha_reported_date', 'DATETIME DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'osha_case_number', 'VARCHAR(100) DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'workers_comp_filed', 'TINYINT(1) DEFAULT 0');
        $this->add_column_if_not_exists($incidents_table, 'weather_conditions', 'VARCHAR(200) DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'days_away_from_work', 'INT DEFAULT 0');
        $this->add_column_if_not_exists($incidents_table, 'restricted_duty_days', 'INT DEFAULT 0');
        $this->add_column_if_not_exists($incidents_table, 'medical_treatment_beyond_first_aid', 'TINYINT(1) DEFAULT 0');
        $this->add_column_if_not_exists($incidents_table, 'police_report_filed', 'TINYINT(1) DEFAULT 0');
        $this->add_column_if_not_exists($incidents_table, 'equipment_involved', 'TEXT DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'subcontractor_involved_id', 'BIGINT UNSIGNED DEFAULT NULL');
        $this->add_column_if_not_exists($incidents_table, 'pdf_reports', 'TEXT DEFAULT NULL');
        
        // pi_crm_safety_inspections - Add enhanced columns
        $inspections_table = $this->wpdb->prefix . 'pi_crm_safety_inspections';
        $this->add_column_if_not_exists($inspections_table, 'checklist_template_id', 'BIGINT UNSIGNED DEFAULT NULL');
        $this->add_column_if_not_exists($inspections_table, 'weather_conditions', 'VARCHAR(200) DEFAULT NULL');
        $this->add_column_if_not_exists($inspections_table, 'location_areas', 'TEXT DEFAULT NULL');
        $this->add_column_if_not_exists($inspections_table, 'overall_score', 'DECIMAL(5,2) DEFAULT NULL');
        $this->add_column_if_not_exists($inspections_table, 'digital_signature_inspector', 'TEXT DEFAULT NULL');
        $this->add_column_if_not_exists($inspections_table, 'digital_signature_supervisor', 'TEXT DEFAULT NULL');
        $this->add_column_if_not_exists($inspections_table, 'notes', 'TEXT DEFAULT NULL');
        
        // pi_crm_equipment - Add safety columns
        $equipment_table = $this->wpdb->prefix . 'pi_crm_equipment';
        $this->add_column_if_not_exists($equipment_table, 'assigned_operator_id', 'BIGINT UNSIGNED DEFAULT NULL');
        $this->add_column_if_not_exists($equipment_table, 'operator_certification_required', 'VARCHAR(100) DEFAULT NULL');
        $this->add_column_if_not_exists($equipment_table, 'supervisor_responsible_id', 'BIGINT UNSIGNED DEFAULT NULL');
        $this->add_column_if_not_exists($equipment_table, 'current_location_on_site', 'VARCHAR(200) DEFAULT NULL');
    }
    
    /**
     * Helper method to add column if it doesn't exist
     */
    private function add_column_if_not_exists($table_name, $column_name, $column_definition) {
        $column_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, str_replace($this->wpdb->prefix, '', $table_name), $column_name
        ));
        
        if ($column_exists == 0) {
            $this->wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}");
        }
    }
    
    /**
     * Safety Module - Checklist Templates
     */
    private function create_safety_checklist_templates_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_safety_checklist_templates';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_name varchar(300) NOT NULL,
            inspection_type varchar(50) NOT NULL,
            category varchar(100) DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            tenant_id bigint(20) unsigned DEFAULT NULL,
            items longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY inspection_type (inspection_type),
            KEY category (category)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety Module - Permits to Work
     */
    private function create_permits_to_work_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_permits_to_work';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            permit_type varchar(50) NOT NULL,
            requestor_id bigint(20) unsigned DEFAULT NULL,
            location_on_site varchar(500) DEFAULT NULL,
            geo_coordinates varchar(100) DEFAULT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            validity_duration_hours int(11) DEFAULT 0,
            extension_count int(11) DEFAULT 0,
            max_extensions int(11) DEFAULT 2,
            work_description longtext DEFAULT NULL,
            hazards_identified longtext DEFAULT NULL,
            precautions_taken longtext DEFAULT NULL,
            required_ppe longtext DEFAULT NULL,
            isolation_requirements longtext DEFAULT NULL,
            atmospheric_testing longtext DEFAULT NULL,
            fire_watch_assigned_id bigint(20) unsigned DEFAULT NULL,
            attendant_assigned_id bigint(20) unsigned DEFAULT NULL,
            rescue_plan_id bigint(20) unsigned DEFAULT NULL,
            equipment_ids longtext DEFAULT NULL,
            approval_chain longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            conflict_permit_ids longtext DEFAULT NULL,
            closure_checklist_completed tinyint(1) DEFAULT 0,
            closure_signature text DEFAULT NULL,
            closure_notes longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY status (status),
            KEY permit_type (permit_type),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety Module - Job Hazard Analyses (JHA)
     */
    private function create_job_hazard_analyses_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_job_hazard_analyses';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            task_name varchar(300) NOT NULL,
            task_description longtext DEFAULT NULL,
            trade_involved varchar(100) DEFAULT NULL,
            supervisor_id bigint(20) unsigned DEFAULT NULL,
            preparation_date date DEFAULT NULL,
            review_date date DEFAULT NULL,
            required_training_ids longtext DEFAULT NULL,
            steps longtext DEFAULT NULL,
            approval_status varchar(20) DEFAULT 'draft',
            approved_by bigint(20) unsigned DEFAULT NULL,
            digital_acknowledgments longtext DEFAULT NULL,
            revision_number int(11) DEFAULT 1,
            parent_jha_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY approval_status (approval_status),
            KEY preparation_date (preparation_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety Module - Toolbox Talks
     */
    private function create_toolbox_talks_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_toolbox_talks';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            topic varchar(300) NOT NULL,
            category varchar(100) DEFAULT NULL,
            content_body longtext DEFAULT NULL,
            presenter_id bigint(20) unsigned DEFAULT NULL,
            scheduled_date datetime DEFAULT NULL,
            duration_minutes int(11) DEFAULT 15,
            attendance longtext DEFAULT NULL,
            questions_asked longtext DEFAULT NULL,
            photos longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'scheduled',
            required_for_trade longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY status (status),
            KEY scheduled_date (scheduled_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety Module - Safety Observations
     */
    private function create_safety_observations_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_safety_observations';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            observer_id bigint(20) unsigned DEFAULT NULL,
            observation_date datetime DEFAULT CURRENT_TIMESTAMP,
            location_on_site varchar(500) DEFAULT NULL,
            geo_coordinates varchar(100) DEFAULT NULL,
            observation_type varchar(50) DEFAULT NULL,
            severity varchar(20) DEFAULT 'low',
            description longtext NOT NULL,
            immediate_action_taken longtext DEFAULT NULL,
            photo_urls longtext DEFAULT NULL,
            video_urls longtext DEFAULT NULL,
            assigned_to bigint(20) unsigned DEFAULT NULL,
            due_date date DEFAULT NULL,
            status varchar(20) DEFAULT 'open',
            resolution_notes longtext DEFAULT NULL,
            resolution_photos longtext DEFAULT NULL,
            closed_by bigint(20) unsigned DEFAULT NULL,
            closed_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY status (status),
            KEY observation_type (observation_type),
            KEY observation_date (observation_date),
            KEY due_date (due_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety Module - PPE Inventory
     */
    private function create_ppe_inventory_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_ppe_inventory';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            ppe_type varchar(50) NOT NULL,
            size varchar(50) DEFAULT NULL,
            quantity_issued int(11) DEFAULT 0,
            quantity_available int(11) DEFAULT 0,
            condition varchar(50) DEFAULT 'good',
            inspection_date date DEFAULT NULL,
            next_inspection_date date DEFAULT NULL,
            assigned_to_worker_id bigint(20) unsigned DEFAULT NULL,
            issue_date date DEFAULT NULL,
            return_date date DEFAULT NULL,
            batch_number varchar(100) DEFAULT NULL,
            supplier varchar(200) DEFAULT NULL,
            certification_standard varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY ppe_type (ppe_type),
            KEY assigned_to_worker_id (assigned_to_worker_id),
            KEY next_inspection_date (next_inspection_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety Module - Safety Meetings
     */
    private function create_safety_meetings_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_safety_meetings';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            meeting_type varchar(50) DEFAULT 'safety_committee',
            scheduled_date datetime DEFAULT NULL,
            minutes longtext DEFAULT NULL,
            attendees longtext DEFAULT NULL,
            action_items longtext DEFAULT NULL,
            next_meeting_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY meeting_type (meeting_type),
            KEY scheduled_date (scheduled_date)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Safety Module - Activity Audit Trail
     */
    private function create_safety_activity_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_safety_activity';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            activity_type varchar(50) NOT NULL,
            description longtext DEFAULT NULL,
            old_value longtext DEFAULT NULL,
            new_value longtext DEFAULT NULL,
            performed_by bigint(20) unsigned NOT NULL,
            performed_by_name varchar(200) DEFAULT NULL,
            performed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY entity_type_entity_id (entity_type, entity_id),
            KEY activity_type (activity_type),
            KEY performed_at (performed_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Get table names for reference
     */
    public function get_table_names() {
        $prefix = $this->wpdb->prefix . 'pi_crm_';
        return array(
            'communications' => $prefix . 'communications',
            'documents' => $prefix . 'documents',
            'timesheets' => $prefix . 'timesheets',
            'safety_inspections' => $prefix . 'safety_inspections',
            'safety_incidents' => $prefix . 'safety_incidents',
            'safety_checklists' => $prefix . 'safety_checklists',
            'quality_snags' => $prefix . 'quality_snags',
            'quality_signoffs' => $prefix . 'quality_signoffs',
            'change_orders' => $prefix . 'change_orders',
            'invoices' => $prefix . 'invoices',
            'invoice_items' => $prefix . 'invoice_items',
            'daily_reports' => $prefix . 'daily_reports',
            'equipment' => $prefix . 'equipment',
            'equipment_usage' => $prefix . 'equipment_usage',
            'job_subcontractors' => $prefix . 'job_subcontractors',
            'client_details' => $prefix . 'client_details',
            'job_photos' => $prefix . 'job_photos',
            'rfi' => $prefix . 'rfi',
            'submittals' => $prefix . 'submittals',
            'site_locations' => $prefix . 'site_locations',
            'team_assignments' => $prefix . 'team_assignments',
            'certifications' => $prefix . 'certifications',
            'weather_data' => $prefix . 'weather_data',
            'employees' => $prefix . 'employees',
            'crews' => $prefix . 'crews',
            'crew_members' => $prefix . 'crew_members',
            'clock_status' => $prefix . 'clock_status',
            'timesheet_approvals' => $prefix . 'timesheet_approvals',
            'cost_codes' => $prefix . 'cost_codes',
            // Safety Module Tables
            'safety_checklist_templates' => $prefix . 'safety_checklist_templates',
            'permits_to_work' => $prefix . 'permits_to_work',
            'job_hazard_analyses' => $prefix . 'job_hazard_analyses',
            'toolbox_talks' => $prefix . 'toolbox_talks',
            'safety_observations' => $prefix . 'safety_observations',
            'ppe_inventory' => $prefix . 'ppe_inventory',
            'safety_meetings' => $prefix . 'safety_meetings',
            'safety_activity' => $prefix . 'safety_activity'
        );
    }
}

// Activation hook
function pi_crm_activate() {
    PI_CRM_Database::get_instance()->create_tables();
}
register_activation_hook(__FILE__, 'pi_crm_activate');
