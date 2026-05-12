<?php
/**
 * Planning Index Workspace - Expenses V3
 * - Multi-level Approvals workflow
 * - Mileage & Travel tracking
 * - Supplier Center with POs
 * - Tax & Compliance (CIS, VAT, Retention)
 * - Settings & Automation Rules
 * - Full QuickBooks Online API integration
 *
 * @package PlanningIndex
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('PI_EXPENSES_VERSION')) {
    define('PI_EXPENSES_VERSION', '3.0.0');
}

if (!defined('PI_EXPENSES_DB_VERSION')) {
    define('PI_EXPENSES_DB_VERSION', '1');
}

// Legacy meta key for backward compatibility
if (!defined('PI_EXPENSES_META_KEY')) {
    define('PI_EXPENSES_META_KEY', '_pi_expenses');
}

/**
 * Force recreate all database tables - nuclear option for fixing missing tables
 */
function pi_expenses_force_recreate_tables() {
    global $wpdb;
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();
    
    // Main expenses table
    $table_expenses = $wpdb->prefix . 'pi_expenses';
    $sql_expenses = "CREATE TABLE $table_expenses (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        job_id bigint(20) unsigned DEFAULT NULL,
        category varchar(100) NOT NULL,
        supplier_id bigint(20) unsigned DEFAULT NULL,
        supplier_name varchar(255) DEFAULT NULL,
        description text,
        amount decimal(10,2) NOT NULL,
        tax_amount decimal(10,2) DEFAULT 0.00,
        tax_rate decimal(5,2) DEFAULT 20.00,
        expense_date date NOT NULL,
        payment_method varchar(50) DEFAULT NULL,
        po_number varchar(100) DEFAULT NULL,
        invoice_number varchar(100) DEFAULT NULL,
        receipt_url varchar(500) DEFAULT NULL,
        receipt_ids text DEFAULT NULL,
        status varchar(50) DEFAULT 'draft',
        approval_status varchar(50) DEFAULT 'approved',
        submitted_by bigint(20) unsigned DEFAULT NULL,
        approved_by bigint(20) unsigned DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        cis_liable tinyint(1) DEFAULT 0,
        cis_rate decimal(5,2) DEFAULT NULL,
        retention_percent decimal(5,2) DEFAULT 0.00,
        qbo_sync_status varchar(50) DEFAULT 'not_synced',
        qbo_customer_id varchar(100) DEFAULT NULL,
        qbo_expense_id varchar(100) DEFAULT NULL,
        deleted_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY job_id (job_id),
        KEY category (category),
        KEY expense_date (expense_date),
        KEY status (status),
        KEY qbo_sync_status (qbo_sync_status),
        KEY approval_status (approval_status)
    ) $charset_collate;";
    dbDelta($sql_expenses);
    
    // Suppliers table
    $table_suppliers = $wpdb->prefix . 'pi_suppliers';
    $sql_suppliers = "CREATE TABLE $table_suppliers (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        company_name varchar(255) NOT NULL,
        contact_name varchar(255) DEFAULT NULL,
        email varchar(255) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        address text,
        account_number varchar(100) DEFAULT NULL,
        vat_number varchar(50) DEFAULT NULL,
        cis_status varchar(50) DEFAULT 'not_applicable',
        payment_terms varchar(50) DEFAULT '30_days',
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY company_name (company_name),
        KEY is_active (is_active)
    ) $charset_collate;";
    dbDelta($sql_suppliers);
    
    // Mileage logs table
    $table_mileage = $wpdb->prefix . 'pi_mileage_logs';
    $sql_mileage = "CREATE TABLE $table_mileage (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        vehicle_id bigint(20) unsigned DEFAULT NULL,
        job_id bigint(20) unsigned DEFAULT NULL,
        trip_date date NOT NULL,
        from_address varchar(255) DEFAULT NULL,
        to_address varchar(255) DEFAULT NULL,
        miles decimal(10,2) NOT NULL,
        rate_per_mile decimal(5,2) DEFAULT 0.70,
        claim_amount decimal(10,2) NOT NULL,
        purpose varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY vehicle_id (vehicle_id),
        KEY job_id (job_id),
        KEY trip_date (trip_date)
    ) $charset_collate;";
    dbDelta($sql_mileage);
    
    // Vehicles table
    $table_vehicles = $wpdb->prefix . 'pi_vehicles';
    $sql_vehicles = "CREATE TABLE $table_vehicles (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        make varchar(100) NOT NULL,
        model varchar(100) NOT NULL,
        registration varchar(20) NOT NULL,
        fuel_type varchar(50) DEFAULT 'Diesel',
        co2_emissions int(11) DEFAULT NULL,
        current_mileage int(11) DEFAULT 0,
        business_use_pct int(11) DEFAULT 100,
        is_company_car tinyint(1) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY is_active (is_active)
    ) $charset_collate;";
    dbDelta($sql_vehicles);
    
    // Reset the version to force future updates to run
    update_option('pi_expenses_db_version', PI_EXPENSES_DB_VERSION);
    
    return [
        'expenses' => $wpdb->get_var("SHOW TABLES LIKE '$table_expenses'") === $table_expenses,
        'suppliers' => $wpdb->get_var("SHOW TABLES LIKE '$table_suppliers'") === $table_suppliers,
        'mileage' => $wpdb->get_var("SHOW TABLES LIKE '$table_mileage'") === $table_mileage,
        'vehicles' => $wpdb->get_var("SHOW TABLES LIKE '$table_vehicles'") === $table_vehicles,
    ];
}

/**
 * Database table creation and migration
 */
add_action('init', 'pi_expenses_init_database');
function pi_expenses_init_database() {
    global $wpdb;
    
    $installed_ver = get_option('pi_expenses_db_version', '0');
    if ($installed_ver == PI_EXPENSES_DB_VERSION) {
        return;
    }
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Main expenses table (replaces user meta storage)
    $table_expenses = $wpdb->prefix . 'pi_expenses';
    $sql_expenses = "CREATE TABLE $table_expenses (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        job_id bigint(20) unsigned DEFAULT NULL,
        supplier_id bigint(20) unsigned DEFAULT NULL,
        cost_code_id bigint(20) unsigned DEFAULT NULL,
        category varchar(50) NOT NULL DEFAULT 'other',
        subcategory varchar(50) DEFAULT NULL,
        supplier_name varchar(255) DEFAULT NULL,
        description text DEFAULT NULL,
        amount decimal(15,2) NOT NULL DEFAULT 0.00,
        tax_amount decimal(15,2) DEFAULT 0.00,
        tax_rate decimal(5,2) DEFAULT 20.00,
        currency varchar(3) DEFAULT 'GBP',
        expense_date date NOT NULL,
        receipt_ids text DEFAULT NULL,
        receipt_url varchar(255) DEFAULT NULL,
        payment_method varchar(50) DEFAULT NULL,
        payment_reference varchar(255) DEFAULT NULL,
        po_number varchar(100) DEFAULT NULL,
        invoice_number varchar(100) DEFAULT NULL,
        cis_liable tinyint(1) DEFAULT 0,
        cis_deduction decimal(15,2) DEFAULT 0.00,
        cis_rate decimal(5,2) DEFAULT NULL,
        retention_amount decimal(15,2) DEFAULT 0.00,
        retention_percent decimal(5,2) DEFAULT 0.00,
        status varchar(20) DEFAULT 'draft',
        qbo_sync_status varchar(20) DEFAULT 'not_synced',
        qbo_entity_id varchar(100) DEFAULT NULL,
        qbo_last_sync datetime DEFAULT NULL,
        qbo_sync_error text DEFAULT NULL,
        approval_status varchar(20) DEFAULT 'approved',
        approval_chain_id bigint(20) unsigned DEFAULT NULL,
        submitted_by bigint(20) unsigned NOT NULL,
        approved_by bigint(20) unsigned DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        rejection_reason text DEFAULT NULL,
        change_request_note text DEFAULT NULL,
        gps_latitude decimal(10,8) DEFAULT NULL,
        gps_longitude decimal(11,8) DEFAULT NULL,
        ocr_data text DEFAULT NULL,
        ocr_confidence decimal(5,2) DEFAULT NULL,
        metadata text DEFAULT NULL,
        is_recurring tinyint(1) DEFAULT 0,
        recurring_pattern varchar(50) DEFAULT NULL,
        parent_expense_id bigint(20) unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY job_id (job_id),
        KEY supplier_id (supplier_id),
        KEY category (category),
        KEY expense_date (expense_date),
        KEY status (status),
        KEY qbo_sync_status (qbo_sync_status),
        KEY approval_status (approval_status)
    ) $charset_collate;";
    dbDelta($sql_expenses);
    
    // Expense approvals table
    $table_approvals = $wpdb->prefix . 'pi_expense_approvals';
    $sql_approvals = "CREATE TABLE $table_approvals (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        expense_id bigint(20) unsigned NOT NULL,
        approver_id bigint(20) unsigned NOT NULL,
        approval_level int(11) DEFAULT 1,
        status varchar(20) DEFAULT 'pending',
        comments text DEFAULT NULL,
        delegated_to bigint(20) unsigned DEFAULT NULL,
        delegated_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY expense_id (expense_id),
        KEY approver_id (approver_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_approvals);
    
    // Automation rules table
    $table_rules = $wpdb->prefix . 'pi_expense_rules';
    $sql_rules = "CREATE TABLE $table_rules (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        rule_name varchar(255) NOT NULL,
        rule_type varchar(50) NOT NULL,
        conditions text NOT NULL,
        actions text NOT NULL,
        is_active tinyint(1) DEFAULT 1,
        priority int(11) DEFAULT 10,
        execution_count int(11) DEFAULT 0,
        last_executed datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY rule_type (rule_type),
        KEY is_active (is_active)
    ) $charset_collate;";
    dbDelta($sql_rules);
    
    // Suppliers table
    $table_suppliers = $wpdb->prefix . 'pi_suppliers';
    $sql_suppliers = "CREATE TABLE $table_suppliers (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        company_name varchar(255) NOT NULL,
        contact_name varchar(255) DEFAULT NULL,
        email varchar(255) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        address text DEFAULT NULL,
        account_number varchar(100) DEFAULT NULL,
        payment_terms varchar(50) DEFAULT '30_days',
        credit_limit decimal(15,2) DEFAULT NULL,
        cis_status varchar(20) DEFAULT 'not_applicable',
        cis_verification_number varchar(100) DEFAULT NULL,
        vat_number varchar(50) DEFAULT NULL,
        default_category varchar(50) DEFAULT NULL,
        qbo_vendor_id varchar(100) DEFAULT NULL,
        rating decimal(3,2) DEFAULT NULL,
        notes text DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY company_name (company_name),
        KEY is_active (is_active)
    ) $charset_collate;";
    dbDelta($sql_suppliers);
    
    // Purchase orders table
    $table_pos = $wpdb->prefix . 'pi_purchase_orders';
    $sql_pos = "CREATE TABLE $table_pos (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        job_id bigint(20) unsigned DEFAULT NULL,
        supplier_id bigint(20) unsigned NOT NULL,
        po_number varchar(100) NOT NULL,
        po_date date NOT NULL,
        delivery_date date DEFAULT NULL,
        description text DEFAULT NULL,
        subtotal decimal(15,2) DEFAULT 0.00,
        tax_amount decimal(15,2) DEFAULT 0.00,
        total_amount decimal(15,2) DEFAULT 0.00,
        status varchar(20) DEFAULT 'draft',
        delivery_address text DEFAULT NULL,
        terms text DEFAULT NULL,
        items text NOT NULL,
        qbo_po_id varchar(100) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY job_id (job_id),
        KEY supplier_id (supplier_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_pos);
    
    // Mileage logs table
    $table_mileage = $wpdb->prefix . 'pi_mileage_logs';
    $sql_mileage = "CREATE TABLE $table_mileage (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        vehicle_id bigint(20) unsigned DEFAULT NULL,
        job_id bigint(20) unsigned DEFAULT NULL,
        trip_date date NOT NULL,
        from_address varchar(255) DEFAULT NULL,
        to_address varchar(255) DEFAULT NULL,
        miles decimal(10,2) NOT NULL,
        purpose varchar(255) DEFAULT NULL,
        rate decimal(5,2) DEFAULT 0.70,
        claim_amount decimal(10,2) NOT NULL,
        is_return_trip tinyint(1) DEFAULT 0,
        gps_track text DEFAULT NULL,
        expense_id bigint(20) unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY job_id (job_id),
        KEY trip_date (trip_date)
    ) $charset_collate;";
    dbDelta($sql_mileage);
    
    // Vehicles table
    $table_vehicles = $wpdb->prefix . 'pi_vehicles';
    $sql_vehicles = "CREATE TABLE $table_vehicles (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        make varchar(100) NOT NULL,
        model varchar(100) NOT NULL,
        registration varchar(20) NOT NULL,
        fuel_type varchar(20) DEFAULT 'diesel',
        co2_emissions decimal(5,2) DEFAULT NULL,
        current_mileage int(11) DEFAULT 0,
        business_use_pct int(3) DEFAULT 100,
        is_company_car tinyint(1) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY is_active (is_active)
    ) $charset_collate;";
    dbDelta($sql_vehicles);
    
    // Job cost codes table (CIS compliant structure)
    $table_cost_codes = $wpdb->prefix . 'pi_job_cost_codes';
    $sql_cost_codes = "CREATE TABLE $table_cost_codes (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        job_id bigint(20) unsigned NOT NULL,
        code varchar(50) NOT NULL,
        description varchar(255) NOT NULL,
        parent_id bigint(20) unsigned DEFAULT NULL,
        level int(11) DEFAULT 1,
        budget_amount decimal(15,2) DEFAULT 0.00,
        committed_amount decimal(15,2) DEFAULT 0.00,
        spent_amount decimal(15,2) DEFAULT 0.00,
        is_cis_liable tinyint(1) DEFAULT 0,
        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY job_id (job_id),
        KEY parent_id (parent_id),
        KEY code (code)
    ) $charset_collate;";
    dbDelta($sql_cost_codes);
    
    // QBO sync log table
    $table_qbo_log = $wpdb->prefix . 'pi_qbo_sync_log';
    $sql_qbo_log = "CREATE TABLE $table_qbo_log (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        entity_type varchar(50) NOT NULL,
        entity_id bigint(20) unsigned NOT NULL,
        qbo_entity_id varchar(100) DEFAULT NULL,
        sync_action varchar(50) NOT NULL,
        sync_status varchar(20) NOT NULL,
        request_data text DEFAULT NULL,
        response_data text DEFAULT NULL,
        error_message text DEFAULT NULL,
        retry_count int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY entity_type (entity_type),
        KEY entity_id (entity_id),
        KEY sync_status (sync_status)
    ) $charset_collate;";
    dbDelta($sql_qbo_log);
    
    // Activity log table
    $table_activity = $wpdb->prefix . 'pi_expense_activity';
    $sql_activity = "CREATE TABLE $table_activity (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        expense_id bigint(20) unsigned DEFAULT NULL,
        action varchar(50) NOT NULL,
        description text DEFAULT NULL,
        metadata text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY expense_id (expense_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql_activity);
    
    update_option('pi_expenses_db_version', PI_EXPENSES_DB_VERSION);
    
    // Run migration from legacy format if needed
    pi_expenses_migrate_legacy_data();
}

/**
 * Migrate legacy user meta expenses to database table
 */
function pi_expenses_migrate_legacy_data() {
    global $wpdb;
    
    $table_expenses = $wpdb->prefix . 'pi_expenses';
    
    // Check if migration already done
    if (get_option('pi_expenses_legacy_migrated')) {
        return;
    }
    
    // Get all users with legacy expenses
    $users = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", PI_EXPENSES_META_KEY);
    
    foreach ($users as $user) {
        $expenses = maybe_unserialize($user->meta_value);
        if (!is_array($expenses) || empty($expenses)) {
            continue;
        }
        
        foreach ($expenses as $exp) {
            // Check if already migrated (by checking if expense with same date/amount exists)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_expenses 
                WHERE user_id = %d AND expense_date = %s AND amount = %f 
                AND supplier_name = %s LIMIT 1",
                $user->user_id,
                $exp['date'] ?? current_time('Y-m-d'),
                (float) ($exp['amount'] ?? 0),
                $exp['supplier'] ?? ''
            ));
            
            if ($existing) {
                continue;
            }
            
            $wpdb->insert($table_expenses, [
                'user_id' => $user->user_id,
                'job_id' => !empty($exp['job_id']) ? (int) $exp['job_id'] : null,
                'category' => sanitize_text_field($exp['category'] ?? 'other'),
                'supplier_name' => sanitize_text_field($exp['supplier'] ?? ''),
                'description' => sanitize_textarea_field($exp['notes'] ?? ''),
                'amount' => (float) ($exp['amount'] ?? 0),
                'expense_date' => sanitize_text_field($exp['date'] ?? current_time('Y-m-d')),
                'receipt_ids' => !empty($exp['receipt_id']) ? json_encode([(int) $exp['receipt_id']]) : null,
                'status' => 'approved',
                'approval_status' => 'approved',
                'submitted_by' => $user->user_id,
                'created_at' => $exp['created'] ?? current_time('mysql'),
                'updated_at' => $exp['updated'] ?? current_time('mysql'),
            ]);
        }
    }
    
    update_option('pi_expenses_legacy_migrated', true);
}

/**
 * Get comprehensive expense categories (hierarchical)
 */
function pi_expenses_get_categories() {
    return [
        'materials' => [
            'label' => __('Materials', 'planningindex'),
            'icon' => 'package',
            'color' => '#10b981',
            'children' => [
                'lumber' => __('Lumber & Timber', 'planningindex'),
                'concrete' => __('Concrete & Aggregates', 'planningindex'),
                'steel' => __('Steel & Metal', 'planningindex'),
                'electrical' => __('Electrical Supplies', 'planningindex'),
                'plumbing' => __('Plumbing Supplies', 'planningindex'),
                'insulation' => __('Insulation', 'planningindex'),
                'roofing' => __('Roofing Materials', 'planningindex'),
                'flooring' => __('Flooring', 'planningindex'),
                'paint' => __('Paint & Finishes', 'planningindex'),
                'hardware' => __('Hardware & Fixings', 'planningindex'),
            ],
            'qbo_account' => 'Cost of Goods Sold',
            'cis_liable' => false,
        ],
        'tools' => [
            'label' => __('Tools & Equipment', 'planningindex'),
            'icon' => 'tool',
            'color' => '#3b82f6',
            'children' => [
                'power_tools' => __('Power Tools', 'planningindex'),
                'hand_tools' => __('Hand Tools', 'planningindex'),
                'measuring' => __('Measuring & Layout', 'planningindex'),
                'safety_equip' => __('Safety Equipment', 'planningindex'),
            ],
            'qbo_account' => 'Tools & Equipment',
            'cis_liable' => false,
        ],
        'fuel' => [
            'label' => __('Fuel & Energy', 'planningindex'),
            'icon' => 'zap',
            'color' => '#f59e0b',
            'children' => [
                'diesel' => __('Diesel', 'planningindex'),
                'petrol' => __('Petrol', 'planningindex'),
                'electric' => __('Electric Charging', 'planningindex'),
                'gas' => __('Gas/Propane', 'planningindex'),
            ],
            'qbo_account' => 'Motor Vehicle Expenses',
            'cis_liable' => false,
        ],
        'subcontractors' => [
            'label' => __('Subcontractors', 'planningindex'),
            'icon' => 'users',
            'color' => '#8b5cf6',
            'children' => [
                'labour_only' => __('Labour Only', 'planningindex'),
                'supply_fix' => __('Supply & Fix', 'planningindex'),
                'specialist' => __('Specialist Trades', 'planningindex'),
            ],
            'qbo_account' => 'Subcontractor Costs',
            'cis_liable' => true,
        ],
        'waste' => [
            'label' => __('Waste & Disposal', 'planningindex'),
            'icon' => 'trash-2',
            'color' => '#ef4444',
            'children' => [
                'skip_hire' => __('Skip Hire', 'planningindex'),
                'tip_fees' => __('Tip Fees', 'planningindex'),
                'hazardous' => __('Hazardous Waste', 'planningindex'),
                'recycling' => __('Recycling', 'planningindex'),
            ],
            'qbo_account' => 'Waste Disposal',
            'cis_liable' => false,
        ],
        'equipment_rental' => [
            'label' => __('Equipment Rental', 'planningindex'),
            'icon' => 'truck',
            'color' => '#06b6d4',
            'children' => [
                'plant_hire' => __('Plant Hire', 'planningindex'),
                'scaffolding' => __('Scaffolding', 'planningindex'),
                'portacabin' => __('Portacabin/Site Office', 'planningindex'),
                'toilet_hire' => __('Toilet Hire', 'planningindex'),
            ],
            'qbo_account' => 'Equipment Rental',
            'cis_liable' => false,
        ],
        'ppe' => [
            'label' => __('PPE & Workwear', 'planningindex'),
            'icon' => 'hard-hat',
            'color' => '#ec4899',
            'children' => [
                'safety_boots' => __('Safety Boots', 'planningindex'),
                'helmets' => __('Helmets & Headgear', 'planningindex'),
                'hi_vis' => __('Hi-Vis Clothing', 'planningindex'),
                'gloves' => __('Gloves & Protection', 'planningindex'),
            ],
            'qbo_account' => 'PPE & Safety Equipment',
            'cis_liable' => false,
        ],
        'permits' => [
            'label' => __('Permits & Licenses', 'planningindex'),
            'icon' => 'file-text',
            'color' => '#f97316',
            'children' => [
                'building_permits' => __('Building Permits', 'planningindex'),
                'road_permits' => __('Road/Highway Permits', 'planningindex'),
                'license_fees' => __('License Fees', 'planningindex'),
            ],
            'qbo_account' => 'Permits & Licenses',
            'cis_liable' => false,
        ],
        'insurance' => [
            'label' => __('Insurance', 'planningindex'),
            'icon' => 'shield',
            'color' => '#84cc16',
            'children' => [
                'public_liability' => __('Public Liability', 'planningindex'),
                'employers_liability' => __('Employers Liability', 'planningindex'),
                'contractor_all_risk' => __('Contractor All Risk', 'planningindex'),
                'tools_insurance' => __('Tools Insurance', 'planningindex'),
            ],
            'qbo_account' => 'Insurance',
            'cis_liable' => false,
        ],
        'overhead' => [
            'label' => __('Overhead & Admin', 'planningindex'),
            'icon' => 'briefcase',
            'color' => '#64748b',
            'children' => [
                'office_rent' => __('Office Rent', 'planningindex'),
                'utilities' => __('Utilities', 'planningindex'),
                'phone_internet' => __('Phone & Internet', 'planningindex'),
                'accounting' => __('Accounting Fees', 'planningindex'),
                'software' => __('Software Subscriptions', 'planningindex'),
            ],
            'qbo_account' => 'General Overheads',
            'cis_liable' => false,
        ],
        'other' => [
            'label' => __('Other', 'planningindex'),
            'icon' => 'more-horizontal',
            'color' => '#94a3b8',
            'children' => [],
            'qbo_account' => 'Other Expenses',
            'cis_liable' => false,
        ],
    ];
}

/**
 * Flatten categories for legacy compatibility
 */
function pi_expenses_get_types() {
    $categories = pi_expenses_get_categories();
    $flat = [];
    foreach ($categories as $key => $cat) {
        $flat[$key] = $cat['label'];
    }
    return $flat;
}

/**
 * Get payment methods
 */
function pi_expenses_get_payment_methods() {
    return [
        'company_card' => __('Company Card', 'planningindex'),
        'personal_card' => __('Personal Card', 'planningindex'),
        'bank_transfer' => __('Bank Transfer', 'planningindex'),
        'cash' => __('Cash', 'planningindex'),
        'cheque' => __('Cheque', 'planningindex'),
        'paypal' => __('PayPal', 'planningindex'),
        'credit_account' => __('Supplier Credit Account', 'planningindex'),
        'direct_debit' => __('Direct Debit', 'planningindex'),
    ];
}

/**
 * Get expense statuses
 */
function pi_expenses_get_statuses() {
    return [
        'draft' => __('Draft', 'planningindex'),
        'pending' => __('Pending Approval', 'planningindex'),
        'approved' => __('Approved', 'planningindex'),
        'rejected' => __('Rejected', 'planningindex'),
        'reconciled' => __('Reconciled', 'planningindex'),
        'archived' => __('Archived', 'planningindex'),
    ];
}

/**
 * Get QBO sync statuses
 */
function pi_expenses_get_qbo_sync_statuses() {
    return [
        'not_synced' => __('Not Synced', 'planningindex'),
        'pending' => __('Pending Sync', 'planningindex'),
        'synced' => __('Synced', 'planningindex'),
        'error' => __('Sync Error', 'planningindex'),
    ];
}

/**
 * Get CIS rates (UK Construction Industry Scheme)
 */
function pi_expenses_get_cis_rates() {
    return [
        'not_applicable' => ['label' => __('N/A - Not CIS', 'planningindex'), 'rate' => 0],
        'gross' => ['label' => __('Gross - 0%', 'planningindex'), 'rate' => 0],
        'registered' => ['label' => __('Registered - 20%', 'planningindex'), 'rate' => 20],
        'unregistered' => ['label' => __('Unregistered - 30%', 'planningindex'), 'rate' => 30],
    ];
}

/**
 * Get VAT rates
 */
function pi_expenses_get_vat_rates() {
    return [
        20.00 => __('Standard (20%)', 'planningindex'),
        5.00 => __('Reduced (5%)', 'planningindex'),
        0.00 => __('Zero Rate (0%)', 'planningindex'),
        -1 => __('Exempt', 'planningindex'),
    ];
}

/**
 * Persist all expenses for a user.
 */
if (!function_exists('pi_expenses_save_for_user')) {
    function pi_expenses_save_for_user($user_id, array $expenses) {
        update_user_meta($user_id, PI_EXPENSES_META_KEY, array_values($expenses));
    }
}

/**
 * Get expenses from database with advanced filtering
 *
 * @deprecated 3.1.0 Use PI_DB_Expenses::get_with_filters() instead
 * @see PI_DB_Expenses::get_with_filters()
 *
 * @param array $args Filter arguments
 * @return array Filtered expenses with pagination
 */
function pi_expenses_get_from_db($args = []) {
	// Trigger deprecation notice in development
	if (defined('WP_DEBUG') && WP_DEBUG) {
		trigger_error(
			'pi_expenses_get_from_db() is deprecated since version 3.1.0. Use PI_DB_Expenses::get_with_filters() instead.',
			E_USER_DEPRECATED
		);
	}

	// Ensure database class is loaded
	if (!class_exists('PI_DB_Expenses')) {
		require_once __DIR__ . '/class-pi-db-expenses.php';
	}

	$defaults = [
		'user_id' => get_current_user_id(),
		'job_id' => null,
		'category' => null,
		'status' => null,
		'approval_status' => null,
		'qbo_sync_status' => null,
		'date_from' => null,
		'date_to' => null,
		'amount_min' => null,
		'amount_max' => null,
		'supplier_id' => null,
		'search' => null,
		'has_receipt' => null,
		'is_recurring' => null,
		'order_by' => 'expense_date',
		'order' => 'DESC',
		'limit' => 100,
		'offset' => 0,
		'page' => 1,
		'include_deleted' => false,
	];

	$args = wp_parse_args($args, $defaults);

	// Map legacy args to new format
	$db_args = [
		'user_id' => $args['user_id'],
		'job_id' => $args['job_id'],
		'category' => $args['category'],
		'status' => $args['status'],
		'approval_status' => $args['approval_status'],
		'qbo_sync_status' => $args['qbo_sync_status'],
		'date_from' => $args['date_from'],
		'date_to' => $args['date_to'],
		'amount_min' => $args['amount_min'],
		'amount_max' => $args['amount_max'],
		'supplier_id' => $args['supplier_id'],
		'search' => $args['search'],
		'has_receipt' => $args['has_receipt'],
		'is_recurring' => $args['is_recurring'],
		'order_by' => $args['order_by'],
		'order' => $args['order'],
		'limit' => $args['limit'],
		'offset' => $args['offset'],
		'page' => $args['page'] > 1 ? $args['page'] : ceil($args['offset'] / $args['limit']) + 1,
	];

	$expenses_db = new PI_DB_Expenses();

	if ($args['include_deleted']) {
		$expenses_db->with_trashed();
	}

	$result = $expenses_db->get_with_filters($db_args);

	// Format for backward compatibility
	return [
		'data' => $result['data'],
		'total' => $result['total'],
		'limit' => $result['per_page'],
		'offset' => $args['offset'],
	];
}

/**
 * Get single expense by ID
 *
 * @deprecated 3.1.0 Use PI_DB_Expenses::get() instead
 * @see PI_DB_Expenses::get()
 *
 * @param int $expense_id Expense ID
 * @param int|null $user_id User ID for ownership verification
 * @return array|null Expense data or null
 */
function pi_expenses_get_by_id($expense_id, $user_id = null) {
	// Trigger deprecation notice in development
	if (defined('WP_DEBUG') && WP_DEBUG) {
		trigger_error(
			'pi_expenses_get_by_id() is deprecated since version 3.1.0. Use PI_DB_Expenses::get() instead.',
			E_USER_DEPRECATED
		);
	}

	// Ensure database class is loaded
	if (!class_exists('PI_DB_Expenses')) {
		require_once __DIR__ . '/class-pi-db-expenses.php';
	}

	$expenses_db = new PI_DB_Expenses();
	return $expenses_db->get(intval($expense_id), $user_id ? intval($user_id) : null);
}

/**
 * Create new expense
 *
 * @deprecated 3.1.0 Use PI_DB_Expenses::insert() instead
 * @see PI_DB_Expenses::insert()
 *
 * @param array $data Expense data
 * @return int|false Expense ID or false
 */
function pi_expenses_create($data) {
	// Trigger deprecation notice in development
	if (defined('WP_DEBUG') && WP_DEBUG) {
		trigger_error(
			'pi_expenses_create() is deprecated since version 3.1.0. Use PI_DB_Expenses::insert() instead.',
			E_USER_DEPRECATED
		);
	}

	// Ensure database class is loaded
	if (!class_exists('PI_DB_Expenses')) {
		require_once __DIR__ . '/class-pi-db-expenses.php';
	}

	// Calculate CIS deduction if applicable
	if (!empty($data['cis_liable']) && !empty($data['cis_rate'])) {
		$data['cis_deduction'] = ($data['amount'] * $data['cis_rate']) / 100;
	}

	// Calculate retention
	if (!empty($data['retention_percent'])) {
		$data['retention_amount'] = ($data['amount'] * $data['retention_percent']) / 100;
	}

	$expenses_db = new PI_DB_Expenses();

	// Validate before insert
	$errors = $expenses_db->validate($data);
	if (!empty($errors)) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			trigger_error('Validation errors: ' . implode(', ', $errors), E_USER_WARNING);
		}
		return false;
	}

	return $expenses_db->insert($data);
}

/**
 * Log expense activity
 */
function pi_expenses_log_activity($user_id, $expense_id, $action, $description = '', $metadata = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pi_expense_activity';
    
    return $wpdb->insert($table, [
        'user_id' => $user_id,
        'expense_id' => $expense_id,
        'action' => $action,
        'description' => $description,
        'metadata' => $metadata ? json_encode($metadata) : null,
        'created_at' => current_time('mysql'),
    ]);
}

/**
 * Determine if a given job belongs to the user.
 */
if (!function_exists('pi_expenses_user_owns_job')) {
    function pi_expenses_user_owns_job($job_id, $user_id = null) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if (!$job_id || !$user_id) {
            return false;
        }

        if (function_exists('pi_jobs_user_owns_job')) {
            return pi_jobs_user_owns_job($job_id, $user_id);
        }

        if (!defined('PI_JOB_META_PREFIX')) {
            return false;
        }

        $owner = (int) get_post_meta($job_id, PI_JOB_META_PREFIX . 'owner_user_id', true);
        return $owner === $user_id;
    }
}

/**
 * Recalculate and persist expense totals on a specific job for analytics.
 */
if (!function_exists('pi_expenses_recalculate_job_totals')) {
    function pi_expenses_recalculate_job_totals($user_id, $job_id) {
        if (!defined('PI_JOB_META_PREFIX')) {
            return;
        }

        if (get_post_type($job_id) !== (defined('PI_JOB_CPT') ? PI_JOB_CPT : 'pi_job')) {
            return;
        }

        $expenses = pi_expenses_get_for_user($user_id);
        $total    = 0.0;
        $by_type  = [];

        foreach ($expenses as $exp) {
            if ((int) ($exp['job_id'] ?? 0) !== (int) $job_id) {
                continue;
            }
            $amount = (float) ($exp['amount'] ?? 0);
            $total += $amount;
            $cat = $exp['category'] ?? 'other';
            if (!isset($by_type[$cat])) {
                $by_type[$cat] = 0.0;
            }
            $by_type[$cat] += $amount;
        }

        update_post_meta($job_id, PI_JOB_META_PREFIX . 'total_expenses', $total);
        update_post_meta($job_id, PI_JOB_META_PREFIX . 'total_expenses_by_type', $by_type);
    }
}

/**
 * REST API for expenses.
 */
add_action('rest_api_init', function () {
    $namespace = 'pi/v1';

    // GET /pi/v1/expenses – list expenses for current user with optional filters.
    register_rest_route($namespace, '/expenses', [
        'methods'             => 'GET',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $user_id   = get_current_user_id();
            $expenses  = pi_expenses_get_for_user($user_id);
            $job_id    = (int) $req->get_param('job_id');
            $type      = sanitize_text_field((string) $req->get_param('type'));
            $month_str = sanitize_text_field((string) $req->get_param('month')); // YYYY-MM

            $out = [];
            foreach ($expenses as $exp) {
                if ($job_id && (int) ($exp['job_id'] ?? 0) !== $job_id) {
                    continue;
                }
                if ($type && ($exp['category'] ?? '') !== $type) {
                    continue;
                }
                if ($month_str && !empty($exp['date'])) {
                    if (substr((string) $exp['date'], 0, 7) !== $month_str) {
                        continue;
                    }
                }
                $out[] = $exp;
            }

            return rest_ensure_response($out);
        },
    ]);

    // GET /pi/v1/expenses/summary – aggregated totals.
    register_rest_route($namespace, '/expenses/summary', [
        'methods'             => 'GET',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'callback'            => static function () {
            $user_id  = get_current_user_id();
            $expenses = pi_expenses_get_for_user($user_id);

            $total     = 0.0;
            $by_type   = [];
            $by_month  = [];
            $by_job    = [];

            foreach ($expenses as $exp) {
                $amount = (float) ($exp['amount'] ?? 0);
                $total += $amount;

                $cat = $exp['category'] ?? 'other';
                if (!isset($by_type[$cat])) {
                    $by_type[$cat] = 0.0;
                }
                $by_type[$cat] += $amount;

                $date = $exp['date'] ?? '';
                $month = $date ? substr((string) $date, 0, 7) : '';
                if ($month) {
                    if (!isset($by_month[$month])) {
                        $by_month[$month] = 0.0;
                    }
                    $by_month[$month] += $amount;
                }

                $job_id = (int) ($exp['job_id'] ?? 0);
                if ($job_id) {
                    if (!isset($by_job[$job_id])) {
                        $by_job[$job_id] = 0.0;
                    }
                    $by_job[$job_id] += $amount;
                }
            }

            return rest_ensure_response([
                'total'    => $total,
                'by_type'  => $by_type,
                'by_month' => $by_month,
                'by_job'   => $by_job,
            ]);
        },
    ]);

    // POST /pi/v1/expenses/add – create an expense.
    register_rest_route($namespace, '/expenses/add', [
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data    = $req->get_json_params();

            $job_id = isset($data['job_id']) ? (int) $data['job_id'] : 0;
            if ($job_id && !pi_expenses_user_owns_job($job_id, $user_id)) {
                return new WP_Error('forbidden', 'You do not own this job', ['status' => 403]);
            }

            $types = array_keys(pi_expenses_get_types());
            $cat_raw = isset($data['category']) ? sanitize_text_field((string) $data['category']) : 'materials';
            $category = in_array($cat_raw, $types, true) ? $cat_raw : 'materials';

            $supplier = isset($data['supplier']) ? sanitize_text_field((string) $data['supplier']) : '';
            $date     = isset($data['date']) ? sanitize_text_field((string) $data['date']) : current_time('Y-m-d');

            $amount  = isset($data['amount']) ? (float) $data['amount'] : 0.0;
            $notes   = isset($data['notes']) ? sanitize_textarea_field((string) $data['notes']) : '';

            $expenses = pi_expenses_get_for_user($user_id);
            $next_id  = 1;
            foreach ($expenses as $exp) {
                if (isset($exp['id']) && (int) $exp['id'] >= $next_id) {
                    $next_id = (int) $exp['id'] + 1;
                }
            }

            $now = current_time('mysql');

            $new = [
                'id'         => $next_id,
                'job_id'     => $job_id,
                'category'   => $category,
                'supplier'   => $supplier,
                'amount'     => $amount,
                'date'       => $date,
                'notes'      => $notes,
                'receipt_id' => 0,
                'created'    => $now,
                'updated'    => $now,
            ];

            $expenses[] = $new;
            pi_expenses_save_for_user($user_id, $expenses);

            if ($job_id) {
                pi_expenses_recalculate_job_totals($user_id, $job_id);
            }

            // QBO Integration: Trigger expense created hook
            do_action('pi_expense_created', $next_id, $new);

            return rest_ensure_response([
                'created' => true,
                'expense' => $new,
            ]);
        },
    ]);

    // POST /pi/v1/expenses/update – update an existing expense.
    register_rest_route($namespace, '/expenses/update', [
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data    = $req->get_json_params();
            $id      = isset($data['id']) ? (int) $data['id'] : 0;

            if ($id <= 0) {
                return new WP_Error('invalid_id', 'Missing or invalid expense ID', ['status' => 400]);
            }

            $expenses = pi_expenses_get_for_user($user_id);
            $types    = array_keys(pi_expenses_get_types());
            $updated  = false;
            $job_ids_to_recalc = [];

            foreach ($expenses as &$exp) {
                if ((int) ($exp['id'] ?? 0) !== $id) {
                    continue;
                }

                $orig_job_id = (int) ($exp['job_id'] ?? 0);

                if (isset($data['job_id'])) {
                    $job_id = (int) $data['job_id'];
                    if ($job_id && !pi_expenses_user_owns_job($job_id, $user_id)) {
                        return new WP_Error('forbidden', 'You do not own this job', ['status' => 403]);
                    }
                    $exp['job_id'] = $job_id;
                    $job_ids_to_recalc[] = $job_id;
                    if ($orig_job_id && $orig_job_id !== $job_id) {
                        $job_ids_to_recalc[] = $orig_job_id;
                    }
                }

                if (isset($data['category'])) {
                    $cat_raw = sanitize_text_field((string) $data['category']);
                    if (in_array($cat_raw, $types, true)) {
                        $exp['category'] = $cat_raw;
                    }
                }

                if (isset($data['supplier'])) {
                    $exp['supplier'] = sanitize_text_field((string) $data['supplier']);
                }

                if (isset($data['amount'])) {
                    $exp['amount'] = (float) $data['amount'];
                }

                if (isset($data['date'])) {
                    $exp['date'] = sanitize_text_field((string) $data['date']);
                }

                if (isset($data['notes'])) {
                    $exp['notes'] = sanitize_textarea_field((string) $data['notes']);
                }

                $exp['updated'] = current_time('mysql');
                $updated = true;
                break;
            }
            unset($exp);

            if (!$updated) {
                return new WP_Error('not_found', 'Expense not found', ['status' => 404]);
            }

            pi_expenses_save_for_user($user_id, $expenses);

            $job_ids_to_recalc = array_unique(array_filter($job_ids_to_recalc));
            foreach ($job_ids_to_recalc as $jid) {
                pi_expenses_recalculate_job_totals($user_id, $jid);
            }

            // QBO Integration: Trigger expense updated hook
            do_action('pi_expense_updated', $id, $expenses[$index] ?? []);

            return rest_ensure_response(['updated' => true]);
        },
    ]);

    // POST /pi/v1/expenses/remove – delete an expense.
    register_rest_route($namespace, '/expenses/remove', [
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id      = (int) $req->get_param('id');
            if ($id <= 0) {
                return new WP_Error('invalid_id', 'Missing or invalid expense ID', ['status' => 400]);
            }

            $expenses = pi_expenses_get_for_user($user_id);
            $before   = count($expenses);
            $job_ids_to_recalc = [];
            $receipt_ids_to_delete = [];

            foreach ($expenses as $exp) {
                if ((int) ($exp['id'] ?? 0) === $id) {
                    if (!empty($exp['job_id'])) {
                        $job_ids_to_recalc[] = (int) $exp['job_id'];
                    }
                    if (!empty($exp['receipt_id'])) {
                        $receipt_ids_to_delete[] = (int) $exp['receipt_id'];
                    }
                }
            }

            $expenses = array_values(array_filter($expenses, static function ($exp) use ($id) {
                return (int) ($exp['id'] ?? 0) !== $id;
            }));

            pi_expenses_save_for_user($user_id, $expenses);

            $after  = count($expenses);
            $removed = $after < $before;

            foreach (array_unique($job_ids_to_recalc) as $jid) {
                pi_expenses_recalculate_job_totals($user_id, $jid);
            }

            foreach (array_unique($receipt_ids_to_delete) as $att_id) {
                if ($att_id && get_post_type($att_id) === 'attachment') {
                    wp_delete_attachment($att_id, true);
                }
            }

            return rest_ensure_response(['removed' => $removed]);
        },
    ]);

    // POST /pi/v1/expenses/upload-receipt – upload or replace receipt for an expense.
    register_rest_route($namespace, '/expenses/upload-receipt', [
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id      = (int) $req->get_param('id');
            if ($id <= 0) {
                return new WP_Error('invalid_id', 'Missing or invalid expense ID', ['status' => 400]);
            }

            $expenses = pi_expenses_get_for_user($user_id);
            $index    = null;
            foreach ($expenses as $k => $exp) {
                if ((int) ($exp['id'] ?? 0) === $id) {
                    $index = $k;
                    break;
                }
            }

            if ($index === null) {
                return new WP_Error('not_found', 'Expense not found', ['status' => 404]);
            }

            $files = $req->get_file_params();
            if (empty($files['file'])) {
                return new WP_Error('no_file', 'No file uploaded', ['status' => 400]);
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            // Delete any existing receipt to avoid orphaned files.
            $existing_att = (int) ($expenses[$index]['receipt_id'] ?? 0);
            if ($existing_att && get_post_type($existing_att) === 'attachment') {
                wp_delete_attachment($existing_att, true);
            }

            $attachment_id = media_handle_upload('file', 0);
            if (is_wp_error($attachment_id)) {
                return new WP_Error('upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
            }

            $expenses[$index]['receipt_id'] = $attachment_id;
            $expenses[$index]['updated']    = current_time('mysql');
            pi_expenses_save_for_user($user_id, $expenses);

            $url = wp_get_attachment_url($attachment_id);

            return rest_ensure_response([
                'uploaded'     => true,
                'attachment_id'=> $attachment_id,
                'url'          => $url,
            ]);
        },
    ]);
});



add_shortcode('planning_workspace_expenses', 'pi_workspace_expenses_v3_shortcode');

/**
 * NEW REST API ENDPOINTS FOR V3 - Enhanced Expense Management System
 */
add_action('rest_api_init', function () {
    $namespace = 'pi/v1';

    // GET /pi/v1/health - Health check endpoint
    register_rest_route($namespace, '/health', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            return rest_ensure_response(['status' => 'ok', 'timestamp' => current_time('mysql')]);
        },
    ]);

    // POST /pi/v1/expenses/init - Force recreate database tables (nuclear option)
    register_rest_route($namespace, '/expenses/init', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function () {
            $result = pi_expenses_force_recreate_tables();
            return rest_ensure_response([
                'success' => true,
                'message' => 'Database tables recreated',
                'tables' => $result,
                'timestamp' => current_time('mysql'),
            ]);
        },
    ]);

    // GET /pi/v1/expenses/dashboard - Dashboard KPIs and data
    register_rest_route($namespace, '/expenses/dashboard', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            global $wpdb;
            
            $table = $wpdb->prefix . 'pi_expenses';
            $current_month = current_time('Y-m');
            $last_month = date('Y-m', strtotime('-1 month'));
            
            // This month's spend
            $this_month = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND DATE_FORMAT(expense_date, '%Y-%m') = %s",
                $user_id, $current_month
            ));
            
            // Last month's spend for trend
            $last_month_spend = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND DATE_FORMAT(expense_date, '%Y-%m') = %s",
                $user_id, $last_month
            ));
            
            $trend = $last_month_spend > 0 ? round((($this_month - $last_month_spend) / $last_month_spend) * 100, 1) : 0;
            
            // Pending approvals
            $pending_approvals = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND approval_status = 'pending'",
                $user_id
            ));
            
            // Unreconciled receipts (expenses without receipts)
            $unreconciled = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND (receipt_ids IS NULL OR receipt_ids = '' OR receipt_ids = '[]')",
                $user_id
            ));
            
            // Budget alerts (jobs approaching 80% of budget - placeholder for future integration)
            $budget_alerts = 0;
            
            return rest_ensure_response([
                'this_month_spend' => (float) $this_month,
                'this_month_spend_trend' => $trend,
                'pending_approvals' => (int) $pending_approvals,
                'unreconciled_receipts' => (int) $unreconciled,
                'budget_alerts' => $budget_alerts,
                'last_updated' => current_time('mysql'),
            ]);
        },
    ]);

    // GET /pi/v1/expenses/trends - Spending trends for charts
    register_rest_route($namespace, '/expenses/trends', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $months = (int) $req->get_param('months') ?: 6;
            global $wpdb;
            
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Get monthly spend data
            $labels = [];
            $actual = [];
            $projected = [];
            
            for ($i = $months - 1; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $labels[] = date('M Y', strtotime("-$i months"));
                
                $spend = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM $table 
                    WHERE user_id = %d AND deleted_at IS NULL 
                    AND DATE_FORMAT(expense_date, '%Y-%m') = %s",
                    $user_id, $month
                ));
                
                $actual[] = (float) $spend;
                // Simple projection based on average of last 3 months
                $projected[] = null; // Calculated below
            }
            
            // Calculate projected (simple trend line based on last 3 months average)
            $last3 = array_slice($actual, -3);
            $avg = array_sum($last3) / count($last3);
            $projected[count($projected) - 1] = $avg;
            
            // Get category breakdown
            $category_data = $wpdb->get_results($wpdb->prepare(
                "SELECT category, SUM(amount) as total FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND expense_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY category",
                $user_id
            ), ARRAY_A);
            
            $by_category = [];
            foreach ($category_data as $row) {
                $by_category[$row['category']] = (float) $row['total'];
            }
            
            // Get job profitability (placeholder)
            $by_job = [];
            
            return rest_ensure_response([
                'labels' => $labels,
                'actual' => $actual,
                'projected' => $projected,
                'by_category' => $by_category,
                'by_job' => $by_job,
            ]);
        },
    ]);

    // GET /pi/v1/expenses/activity - Activity feed
    register_rest_route($namespace, '/expenses/activity', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $limit = (int) $req->get_param('limit') ?: 20;
            
            $activity = pi_expenses_get_activity([
                'user_id' => $user_id,
                'limit' => $limit,
            ]);
            
            // Enhance with user names
            foreach ($activity as &$item) {
                $user = get_userdata($item['user_id']);
                $item['user_name'] = $user ? $user->display_name : 'Unknown';
            }
            
            return rest_ensure_response($activity);
        },
    ]);

    // GET /pi/v1/expenses/notifications - Get user notifications
    register_rest_route($namespace, '/expenses/notifications', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            // For now, return empty array - can be populated later with real notification data
            // This could come from a notifications table, user meta, or be generated from
            // pending approvals, budget alerts, etc.
            $notifications = [];
            
            // Example: Check for pending approvals and add as notification
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            $pending_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE user_id = %d AND approval_status = 'pending' AND deleted_at IS NULL",
                $user_id
            ));
            
            if ($pending_count > 0) {
                $notifications[] = [
                    'id' => 'pending_' . time(),
                    'type' => 'warning',
                    'icon' => 'clock',
                    'message' => sprintf('%d expense%s pending approval', $pending_count, $pending_count > 1 ? 's' : ''),
                    'read' => false,
                    'created_at' => current_time('mysql'),
                    'action_url' => '#ledger?status=pending',
                ];
            }
            
            // Example: Check for missing receipts
            $missing_receipts = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE user_id = %d AND receipt_ids IS NULL AND deleted_at IS NULL",
                $user_id
            ));
            
            if ($missing_receipts > 5) {
                $notifications[] = [
                    'id' => 'receipts_' . time(),
                    'type' => 'info',
                    'icon' => 'file-minus',
                    'message' => sprintf('%d expenses missing receipts', $missing_receipts),
                    'read' => false,
                    'created_at' => current_time('mysql'),
                    'action_url' => '#ledger',
                ];
            }
            
            return rest_ensure_response($notifications);
        },
    ]);

    // GET /pi/v1/expenses/db - Get expenses from database (new table)
    register_rest_route($namespace, '/expenses/db', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            global $wpdb;
            
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Check if table exists, create if not
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                pi_expenses_force_recreate_tables();
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                if (!$table_exists) {
                    return new WP_Error('table_error', 'Failed to create expenses table', ['status' => 500]);
                }
            }
            
            // Build query
            $where = ['user_id = %d', 'deleted_at IS NULL'];
            $params = [$user_id];
            
            // Search filter
            $search = sanitize_text_field($req->get_param('search') ?: '');
            if ($search) {
                $where[] = '(description LIKE %s OR supplier_name LIKE %s OR invoice_number LIKE %s)';
                $search_like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $search_like;
                $params[] = $search_like;
                $params[] = $search_like;
            }
            
            // Category filter
            $category = sanitize_text_field($req->get_param('category') ?: '');
            if ($category) {
                $where[] = 'category = %s';
                $params[] = $category;
            }
            
            // Status filter
            $status = sanitize_text_field($req->get_param('status') ?: '');
            if ($status) {
                $where[] = 'status = %s';
                $params[] = $status;
            }
            
            // Approval status filter
            $approval_status = sanitize_text_field($req->get_param('approval_status') ?: '');
            if ($approval_status) {
                $where[] = 'approval_status = %s';
                $params[] = $approval_status;
            }
            
            // Date filters
            $date_from = sanitize_text_field($req->get_param('date_from') ?: '');
            if ($date_from) {
                $where[] = 'expense_date >= %s';
                $params[] = $date_from;
            }
            
            $date_to = sanitize_text_field($req->get_param('date_to') ?: '');
            if ($date_to) {
                $where[] = 'expense_date <= %s';
                $params[] = $date_to;
            }
            
            // Build WHERE clause
            $where_clause = implode(' AND ', $where);
            
            // Get total count
            $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
            $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
            
            // Pagination
            $per_page = (int) ($req->get_param('per_page') ?: 25);
            $page = (int) ($req->get_param('page') ?: 1);
            $offset = ($page - 1) * $per_page;
            
            // Get expenses
            $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY expense_date DESC, id DESC LIMIT %d OFFSET %d";
            $query_params = array_merge($params, [$per_page, $offset]);
            $expenses = $wpdb->get_results($wpdb->prepare($sql, ...$query_params), ARRAY_A);
            
            // Enhance with job titles
            foreach ($expenses as &$expense) {
                if (!empty($expense['job_id'])) {
                    $job = get_post($expense['job_id']);
                    $expense['job_title'] = $job ? $job->post_title : 'Unknown Job';
                } else {
                    $expense['job_title'] = null;
                }
            }
            
            return rest_ensure_response([
                'data' => $expenses,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page),
            ]);
        },
    ]);

    // POST /pi/v1/expenses/create - Create expense in database
    register_rest_route($namespace, '/expenses/create', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data = $req->get_json_params();
            
            // Validate required fields
            if (empty($data['amount']) || empty($data['expense_date'])) {
                return new WP_Error('missing_fields', 'Amount and date are required', ['status' => 400]);
            }
            
            // Check job ownership
            if (!empty($data['job_id'])) {
                $job_id = (int) $data['job_id'];
                if (!pi_expenses_user_owns_job($job_id, $user_id)) {
                    return new WP_Error('forbidden', 'You do not own this job', ['status' => 403]);
                }
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Check if table exists, create if not
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                pi_expenses_init_database();
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                if (!$table_exists) {
                    return new WP_Error('table_error', 'Failed to create expenses table', ['status' => 500]);
                }
            }
            
            $expense_data = [
                'user_id' => $user_id,
                'job_id' => !empty($data['job_id']) ? (int) $data['job_id'] : null,
                'category' => sanitize_text_field($data['category'] ?: 'other'),
                'supplier_name' => sanitize_text_field($data['supplier_name'] ?: ''),
                'description' => sanitize_textarea_field($data['description'] ?: ''),
                'amount' => (float) $data['amount'],
                'tax_amount' => (float) ($data['tax_amount'] ?: 0),
                'tax_rate' => (float) ($data['tax_rate'] ?: 20),
                'expense_date' => sanitize_text_field($data['expense_date']),
                'payment_method' => sanitize_text_field($data['payment_method'] ?: ''),
                'po_number' => sanitize_text_field($data['po_number'] ?: ''),
                'invoice_number' => sanitize_text_field($data['invoice_number'] ?: ''),
                'cis_liable' => !empty($data['cis_liable']) ? 1 : 0,
                'cis_rate' => !empty($data['cis_rate']) ? (float) $data['cis_rate'] : null,
                'retention_percent' => !empty($data['retention_percent']) ? (float) $data['retention_percent'] : 0,
                'status' => sanitize_text_field($data['status'] ?: 'draft'),
                'approval_status' => sanitize_text_field($data['approval_status'] ?: 'approved'),
                'submitted_by' => $user_id,
                'receipt_ids' => !empty($data['receipt_ids']) ? $data['receipt_ids'] : null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];
            
            $result = $wpdb->insert($table, $expense_data);
            
            if ($result) {
                return rest_ensure_response([
                    'success' => true,
                    'id' => $wpdb->insert_id,
                    'message' => 'Expense created successfully',
                ]);
            }
            
            return new WP_Error('create_failed', 'Failed to create expense: ' . $wpdb->last_error, ['status' => 500]);
        },
    ]);

    // PUT /pi/v1/expenses/{id} - Update expense
    register_rest_route($namespace, '/expenses/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                return new WP_Error('not_found', 'Expense not found', ['status' => 404]);
            }
            
            // Get existing expense
            $expense = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND user_id = %d AND deleted_at IS NULL",
                $id, $user_id
            ), ARRAY_A);
            
            if (!$expense) {
                return new WP_Error('not_found', 'Expense not found', ['status' => 404]);
            }
            
            // Check job ownership if changing job
            if (!empty($data['job_id']) && $data['job_id'] != $expense['job_id']) {
                if (!pi_expenses_user_owns_job((int) $data['job_id'], $user_id)) {
                    return new WP_Error('forbidden', 'You do not own this job', ['status' => 403]);
                }
            }
            
            $update_data = [];
            $allowed_fields = ['job_id', 'category', 'supplier_name', 'description', 'amount', 
                              'tax_amount', 'tax_rate', 'expense_date', 'payment_method', 
                              'po_number', 'invoice_number', 'cis_liable', 'cis_rate', 
                              'retention_percent', 'status', 'approval_status', 'receipt_ids'];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_data[$field] = $data[$field];
                }
            }
            
            // Add updated_at timestamp
            $update_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update($table, $update_data, ['id' => $id, 'user_id' => $user_id]);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Expense updated']);
            }
            
            return new WP_Error('update_failed', 'Failed to update expense: ' . $wpdb->last_error, ['status' => 500]);
        },
    ]);

    // DELETE /pi/v1/expenses/{id} - Soft delete expense
    register_rest_route($namespace, '/expenses/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Soft delete by setting deleted_at timestamp
            $result = $wpdb->update($table, [
                'deleted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['id' => $id, 'user_id' => $user_id]);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Expense deleted']);
            }
            
            return new WP_Error('delete_failed', 'Failed to delete expense: ' . $wpdb->last_error, ['status' => 500]);
        },
    ]);

    // POST /pi/v1/expenses/bulk - Bulk actions
    register_rest_route($namespace, '/expenses/bulk', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data = $req->get_json_params();
            $action = sanitize_text_field($data['action'] ?: '');
            $ids = array_map('intval', (array) ($data['ids'] ?: []));
            
            if (empty($ids)) {
                return new WP_Error('no_ids', 'No expense IDs provided', ['status' => 400]);
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            switch ($action) {
                case 'delete':
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $table SET deleted_at = %s, updated_at = %s 
                        WHERE id IN (" . implode(',', $ids) . ") AND user_id = %d",
                        current_time('mysql'), current_time('mysql'), $user_id
                    ));
                    break;
                    
                case 'approve':
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $table SET approval_status = 'approved', approved_by = %d, approved_at = %s 
                        WHERE id IN (" . implode(',', $ids) . ") AND user_id = %d",
                        $user_id, current_time('mysql'), $user_id
                    ));
                    break;
                    
                case 'sync_to_qbo':
                    // Mark for QBO sync
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $table SET qbo_sync_status = 'pending' 
                        WHERE id IN (" . implode(',', $ids) . ") AND user_id = %d",
                        $user_id
                    ));
                    break;
                    
                default:
                    return new WP_Error('invalid_action', 'Invalid bulk action', ['status' => 400]);
            }
            
            return rest_ensure_response(['success' => true, 'action' => $action, 'count' => count($ids)]);
        },
    ]);

    // GET /pi/v1/expenses/settings - Get user settings
    register_rest_route($namespace, '/expenses/settings', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            $settings = get_user_meta($user_id, 'pi_expense_settings', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            
            // Merge with defaults
            $defaults = [
                'default_category' => 'materials',
                'default_payment_method' => 'company_card',
                'vat_registered' => true,
                'cis_enabled' => false,
                'receipt_reminder_days' => 3,
                'auto_categorize' => true,
                'qbo_auto_sync' => false,
                'currency' => 'GBP',
                'mileage_rate' => 0.70,
            ];
            
            return rest_ensure_response(array_merge($defaults, $settings));
        },
    ]);

    // POST /pi/v1/expenses/settings - Save user settings
    register_rest_route($namespace, '/expenses/settings', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data = $req->get_json_params();
            
            $settings = get_user_meta($user_id, 'pi_expense_settings', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            
            // Sanitize and merge
            $allowed = ['default_category', 'default_payment_method', 'vat_registered', 'cis_enabled',
                       'receipt_reminder_days', 'auto_categorize', 'qbo_auto_sync', 'currency', 'mileage_rate'];
            
            foreach ($allowed as $key) {
                if (isset($data[$key])) {
                    $settings[$key] = $data[$key];
                }
            }
            
            update_user_meta($user_id, 'pi_expense_settings', $settings);
            
            return rest_ensure_response(['success' => true, 'settings' => $settings]);
        },
    ]);

    // ============================================
    // JOB COSTING ENDPOINTS
    // ============================================
    
    // GET /pi/v1/expenses/job-costing - Get job costing data with expenses
    register_rest_route($namespace, '/expenses/job-costing', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $job_id = (int) $req->get_param('job_id');
            
            if (!$job_id) {
                return new WP_Error('missing_job_id', 'Job ID is required', ['status' => 400]);
            }
            
            if (!pi_expenses_user_owns_job($job_id, $user_id)) {
                return new WP_Error('forbidden', 'You do not own this job', ['status' => 403]);
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Get job expenses
            $expenses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table 
                WHERE user_id = %d AND job_id = %d AND deleted_at IS NULL 
                ORDER BY expense_date DESC",
                $user_id, $job_id
            ), ARRAY_A);
            
            // Get cost code breakdown
            $cost_codes = $wpdb->get_results($wpdb->prepare(
                "SELECT category, SUM(amount) as total, COUNT(*) as count 
                FROM $table 
                WHERE user_id = %d AND job_id = %d AND deleted_at IS NULL 
                GROUP BY category",
                $user_id, $job_id
            ), ARRAY_A);
            
            // Get job totals
            $job_total = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $table 
                WHERE user_id = %d AND job_id = %d AND deleted_at IS NULL",
                $user_id, $job_id
            ));
            
            // Get job budget from job meta
            $job_budget = (float) get_post_meta($job_id, '_pi_job_budget', true);
            $job_quote = (float) get_post_meta($job_id, '_pi_job_quote_value', true);
            
            return rest_ensure_response([
                'job_id' => $job_id,
                'job_title' => get_post_field('post_title', $job_id),
                'expenses' => $expenses,
                'cost_codes' => $cost_codes,
                'total_expenses' => (float) $job_total,
                'budget' => $job_budget,
                'quote_value' => $job_quote,
                'profit_margin' => $job_quote > 0 ? round((($job_quote - $job_total) / $job_quote) * 100, 2) : 0,
                'budget_used_percent' => $job_budget > 0 ? round(($job_total / $job_budget) * 100, 2) : 0,
            ]);
        },
    ]);

    // GET /pi/v1/expenses/jobs - Get all jobs with expense summaries
    register_rest_route($namespace, '/expenses/jobs', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            $jobs = get_posts([
                'post_type' => 'pi_job',
                'posts_per_page' => -1,
                'author' => $user_id,
                'post_status' => ['publish', 'draft'],
            ]);
            
            global $wpdb;
            $table_expenses = $wpdb->prefix . 'pi_expenses';
            $table_mileage = $wpdb->prefix . 'pi_mileage_logs';
            
            $jobs_with_expenses = [];
            foreach ($jobs as $job) {
                // Get expenses data
                $job_total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM $table_expenses 
                    WHERE user_id = %d AND job_id = %d AND deleted_at IS NULL",
                    $user_id, $job->ID
                ));
                
                $expense_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_expenses 
                    WHERE user_id = %d AND job_id = %d AND deleted_at IS NULL",
                    $user_id, $job->ID
                ));
                
                // Get trip counts and mileage
                $trip_count = 0;
                $total_miles = 0;
                $mileage_claim = 0;
                
                $mileage_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_mileage'") === $table_mileage;
                if ($mileage_exists) {
                    $trip_count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_mileage 
                        WHERE user_id = %d AND job_id = %d",
                        $user_id, $job->ID
                    ));
                    
                    $total_miles = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(miles), 0) FROM $table_mileage 
                        WHERE user_id = %d AND job_id = %d",
                        $user_id, $job->ID
                    ));
                    
                    $mileage_claim = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(claim_amount), 0) FROM $table_mileage 
                        WHERE user_id = %d AND job_id = %d",
                        $user_id, $job->ID
                    ));
                }
                
                $budget = (float) get_post_meta($job->ID, '_pi_job_budget', true);
                $quote = (float) get_post_meta($job->ID, '_pi_job_quote_value', true);
                $total_cost = (float) $job_total + $mileage_claim;
                
                $jobs_with_expenses[] = [
                    'id' => $job->ID,
                    'title' => $job->post_title,
                    'status' => $job->post_status,
                    'total_expenses' => (float) $job_total,
                    'expense_count' => (int) $expense_count,
                    'trip_count' => $trip_count,
                    'total_miles' => round($total_miles, 1),
                    'mileage_claim' => $mileage_claim,
                    'total_cost' => $total_cost,
                    'budget' => $budget,
                    'quote' => $quote,
                    'profit_margin' => $quote > 0 ? round((($quote - $total_cost) / $quote) * 100, 2) : 0,
                    'budget_used_percent' => $budget > 0 ? round(($total_cost / $budget) * 100, 2) : 0,
                ];
            }
            
            return rest_ensure_response($jobs_with_expenses);
        },
    ]);

    // ============================================
    // SUPPLIER ENDPOINTS
    // ============================================
    
    // GET /pi/v1/expenses/suppliers - Get all suppliers
    register_rest_route($namespace, '/expenses/suppliers', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_suppliers';
            $table_expenses = $wpdb->prefix . 'pi_expenses';
            
            // Check if suppliers table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            $suppliers = [];
            
            if ($table_exists) {
                // Get suppliers from database
                $db_suppliers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table WHERE user_id = %d AND is_active = 1 ORDER BY company_name",
                    $user_id
                ), ARRAY_A);
                
                foreach ($db_suppliers as $s) {
                    // Get total spend for this supplier
                    $total_spend = $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(amount), 0) FROM $table_expenses 
                        WHERE user_id = %d AND supplier_id = %d AND deleted_at IS NULL",
                        $user_id, $s['id']
                    ));
                    
                    $suppliers[] = [
                        'id' => $s['id'],
                        'name' => $s['company_name'],
                        'contact_name' => $s['contact_name'],
                        'email' => $s['email'],
                        'phone' => $s['phone'],
                        'address' => $s['address'],
                        'account_number' => $s['account_number'],
                        'vat_number' => $s['vat_number'],
                        'cis_status' => $s['cis_status'],
                        'payment_terms' => $s['payment_terms'],
                        'total_spend' => (float) $total_spend,
                        'created_at' => $s['created_at']
                    ];
                }
            }
            
            // Also get unique suppliers from expenses (legacy support)
            $expense_suppliers = $wpdb->get_results($wpdb->prepare(
                "SELECT supplier_name, COUNT(*) as expense_count, SUM(amount) as total_amount
                FROM $table_expenses 
                WHERE user_id = %d AND deleted_at IS NULL AND supplier_name != '' AND supplier_id IS NULL
                GROUP BY supplier_name 
                ORDER BY total_amount DESC",
                $user_id
            ), ARRAY_A);
            
            foreach ($expense_suppliers as $s) {
                // Check if already added from main suppliers table
                $exists = false;
                foreach ($suppliers as $existing) {
                    if (strcasecmp($existing['name'], $s['supplier_name']) === 0) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $suppliers[] = [
                        'id' => 'exp_' . sanitize_title($s['supplier_name']),
                        'name' => $s['supplier_name'],
                        'contact_name' => '',
                        'email' => '',
                        'phone' => '',
                        'address' => '',
                        'account_number' => '',
                        'vat_number' => '',
                        'cis_status' => 'not_applicable',
                        'payment_terms' => '',
                        'total_spend' => (float) $s['total_amount'],
                        'expense_count' => (int) $s['expense_count'],
                        'is_from_expense' => true
                    ];
                }
            }
            
            return rest_ensure_response($suppliers);
        },
    ]);

    // POST /pi/v1/expenses/suppliers - Create supplier
    register_rest_route($namespace, '/expenses/suppliers', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data = $req->get_json_params();
            
            if (empty($data['company_name'])) {
                return new WP_Error('missing_name', 'Company name is required', ['status' => 400]);
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_suppliers';
            
            // Check if table exists, create if not
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                pi_expenses_init_database();
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                if (!$table_exists) {
                    return new WP_Error('table_error', 'Failed to create suppliers table', ['status' => 500]);
                }
            }
            
            $result = $wpdb->insert($table, [
                'user_id' => $user_id,
                'company_name' => sanitize_text_field($data['company_name']),
                'contact_name' => sanitize_text_field($data['contact_name'] ?? ''),
                'email' => sanitize_email($data['email'] ?? ''),
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'address' => sanitize_textarea_field($data['address'] ?? ''),
                'account_number' => sanitize_text_field($data['account_number'] ?? ''),
                'vat_number' => sanitize_text_field($data['vat_number'] ?? ''),
                'cis_status' => sanitize_text_field($data['cis_status'] ?? 'not_applicable'),
                'payment_terms' => sanitize_text_field($data['payment_terms'] ?? '30_days'),
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            
            if ($result) {
                return rest_ensure_response([
                    'success' => true,
                    'id' => $wpdb->insert_id,
                    'message' => 'Supplier created successfully'
                ]);
            }
            
            return new WP_Error('create_failed', 'Failed to create supplier', ['status' => 500]);
        },
    ]);

    // PUT /pi/v1/expenses/suppliers/{id} - Update supplier
    register_rest_route($namespace, '/expenses/suppliers/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_suppliers';
            
            // Verify ownership
            $supplier = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND user_id = %d",
                $id, $user_id
            ));
            
            if (!$supplier) {
                return new WP_Error('not_found', 'Supplier not found', ['status' => 404]);
            }
            
            $update_data = [
                'updated_at' => current_time('mysql'),
            ];
            
            $allowed_fields = ['company_name', 'contact_name', 'email', 'phone', 'address', 
                              'account_number', 'vat_number', 'cis_status', 'payment_terms'];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
            }
            
            $result = $wpdb->update($table, $update_data, ['id' => $id, 'user_id' => $user_id]);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Supplier updated']);
            }
            
            return new WP_Error('update_failed', 'Failed to update supplier', ['status' => 500]);
        },
    ]);

    // DELETE /pi/v1/expenses/suppliers/{id} - Delete supplier
    register_rest_route($namespace, '/expenses/suppliers/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_suppliers';
            
            // Soft delete - set is_active = 0
            $result = $wpdb->update($table, [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ], ['id' => $id, 'user_id' => $user_id]);
            
            if ($result) {
                return rest_ensure_response(['success' => true, 'message' => 'Supplier deleted']);
            }
            
            return new WP_Error('delete_failed', 'Failed to delete supplier', ['status' => 500]);
        },
    ]);

    // GET /pi/v1/expenses/suppliers/{id} - Get supplier details
    register_rest_route($namespace, '/expenses/suppliers/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $supplier_id = (int) $req->get_param('id');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_suppliers';
            
            $supplier = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND user_id = %d AND is_active = 1",
                $supplier_id, $user_id
            ), ARRAY_A);
            
            if (!$supplier) {
                return new WP_Error('not_found', 'Supplier not found', ['status' => 404]);
            }
            
            // Get purchase orders for this supplier
            $table_pos = $wpdb->prefix . 'pi_purchase_orders';
            $pos = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_pos WHERE supplier_id = %d AND user_id = %d ORDER BY po_date DESC LIMIT 10",
                $supplier_id, $user_id
            ), ARRAY_A);
            
            return rest_ensure_response([
                'id' => $supplier['id'],
                'name' => $supplier['company_name'],
                'contact_name' => $supplier['contact_name'],
                'email' => $supplier['email'],
                'phone' => $supplier['phone'],
                'address' => $supplier['address'],
                'account_number' => $supplier['account_number'],
                'vat_number' => $supplier['vat_number'],
                'cis_status' => $supplier['cis_status'],
                'payment_terms' => $supplier['payment_terms'],
                'purchase_orders' => $pos,
            ]);
        },
    ]);

    // GET /pi/v1/expenses/suppliers/{id}/pos - Get supplier purchase orders
    register_rest_route($namespace, '/expenses/suppliers/(?P<id>\d+)/pos', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $supplier_id = (int) $req->get_param('id');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_purchase_orders';
            
            $pos = $wpdb->get_results($wpdb->prepare(
                "SELECT po.*, p.post_title as job_title 
                FROM $table po
                LEFT JOIN {$wpdb->posts} p ON po.job_id = p.ID
                WHERE po.supplier_id = %d AND po.user_id = %d 
                ORDER BY po.po_date DESC",
                $supplier_id, $user_id
            ), ARRAY_A);
            
            return rest_ensure_response($pos);
        },
    ]);

    // ============================================
    // MILEAGE ENDPOINTS
    // ============================================
    
    // GET /pi/v1/expenses/mileage - Get mileage data
    register_rest_route($namespace, '/expenses/mileage', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            global $wpdb;
            $table_mileage = $wpdb->prefix . 'pi_mileage_logs';
            $table_vehicles = $wpdb->prefix . 'pi_vehicles';
            $table_expenses = $wpdb->prefix . 'pi_expenses';
            
            // Check if tables exist
            $mileage_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_mileage'") === $table_mileage;
            $vehicles_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_vehicles'") === $table_vehicles;
            
            $trips = [];
            $vehicles = [];
            
            if ($mileage_exists) {
                $raw_trips = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_mileage WHERE user_id = %d ORDER BY trip_date DESC LIMIT 100",
                    $user_id
                ), ARRAY_A);
                
                foreach ($raw_trips as $t) {
                    $trips[] = [
                        'id' => $t['id'],
                        'date' => $t['trip_date'],
                        'vehicle_id' => $t['vehicle_id'],
                        'from_address' => $t['from_address'],
                        'to_address' => $t['to_address'],
                        'miles' => (float) $t['miles'],
                        'rate_per_mile' => (float) $t['rate_per_mile'],
                        'claim_amount' => (float) $t['claim_amount'],
                        'purpose' => $t['purpose'],
                        'job_id' => $t['job_id'],
                    ];
                }
            }
            
            if ($vehicles_exists) {
                $raw_vehicles = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_vehicles WHERE user_id = %d AND is_active = 1 ORDER BY created_at DESC",
                    $user_id
                ), ARRAY_A);
                
                foreach ($raw_vehicles as $v) {
                    $vehicles[] = [
                        'id' => $v['id'],
                        'name' => $v['make'] . ' ' . $v['model'] . ' (' . $v['registration'] . ')',
                        'make' => $v['make'],
                        'model' => $v['model'],
                        'registration' => $v['registration'],
                        'fuel_type' => $v['fuel_type'],
                        'co2_emissions' => (float) $v['co2_emissions'],
                        'current_mileage' => (int) $v['current_mileage'],
                        'business_use_pct' => (int) $v['business_use_pct'],
                        'is_company_car' => (bool) $v['is_company_car'],
                    ];
                }
            }
            
            // Calculate stats
            $total_miles = array_sum(array_column($trips, 'miles'));
            $total_claim = array_sum(array_column($trips, 'claim_amount'));
            $total_trips = count($trips);
            
            // Estimate CO2 emissions (average 150g/km for diesel, convert to kg)
            $co2_emissions = ($total_miles * 1.609 * 150) / 1000;
            
            return rest_ensure_response([
                'vehicles' => $vehicles,
                'trips' => $trips,
                'stats' => [
                    'total_miles' => $total_miles,
                    'total_claim' => $total_claim,
                    'total_trips' => $total_trips,
                    'co2_emissions' => round($co2_emissions, 2),
                ],
            ]);
        },
    ]);

    // POST /pi/v1/expenses/mileage - Create mileage entry
    register_rest_route($namespace, '/expenses/mileage', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_mileage_logs';
            
            // Check if table exists, create if not
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                pi_expenses_init_database();
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                if (!$table_exists) {
                    return new WP_Error('table_error', 'Failed to create mileage table', ['status' => 500]);
                }
            }
            
            $result = $wpdb->insert($table, [
                'user_id' => $user_id,
                'vehicle_id' => (int) ($data['vehicle_id'] ?? 0),
                'trip_date' => sanitize_text_field($data['date']),
                'from_address' => sanitize_text_field($data['from_address']),
                'to_address' => sanitize_text_field($data['to_address']),
                'job_id' => !empty($data['job_id']) ? (int) $data['job_id'] : null,
                'miles' => (float) $data['miles'],
                'rate_per_mile' => 0.70,
                'claim_amount' => (float) $data['miles'] * 0.70,
                'purpose' => sanitize_text_field($data['purpose'] ?? ''),
                'created_at' => current_time('mysql'),
            ]);
            
            if ($result) {
                return rest_ensure_response(['success' => true, 'id' => $wpdb->insert_id]);
            }
            
            return new WP_Error('insert_failed', 'Failed to create mileage entry: ' . $wpdb->last_error, ['status' => 500]);
        },
    ]);

    // PUT /pi/v1/expenses/mileage/{id} - Update mileage entry
    register_rest_route($namespace, '/expenses/mileage/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_mileage_logs';
            
            $update_data = [];
            $allowed = ['vehicle_id', 'trip_date', 'from_address', 'to_address', 'job_id', 'miles', 'purpose'];
            
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    if ($field === 'miles') {
                        $update_data['miles'] = (float) $data[$field];
                        $update_data['claim_amount'] = (float) $data[$field] * 0.70;
                    } else {
                        $update_data[$field] = sanitize_text_field($data[$field]);
                    }
                }
            }
            
            $result = $wpdb->update($table, $update_data, ['id' => $id, 'user_id' => $user_id]);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Trip updated']);
            }
            
            return new WP_Error('update_failed', 'Failed to update trip', ['status' => 500]);
        },
    ]);

    // DELETE /pi/v1/expenses/mileage/{id} - Delete mileage entry
    register_rest_route($namespace, '/expenses/mileage/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_mileage_logs';
            
            $result = $wpdb->delete($table, ['id' => $id, 'user_id' => $user_id]);
            
            if ($result) {
                return rest_ensure_response(['success' => true, 'message' => 'Trip deleted']);
            }
            
            return new WP_Error('delete_failed', 'Failed to delete trip', ['status' => 500]);
        },
    ]);

    // ============================================
    // VEHICLE ENDPOINTS
    // ============================================

    // GET /pi/v1/expenses/vehicles - Get all vehicles
    register_rest_route($namespace, '/expenses/vehicles', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_vehicles';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if (!$table_exists) {
                return rest_ensure_response([]);
            }
            
            $vehicles = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d AND is_active = 1 ORDER BY created_at DESC",
                $user_id
            ), ARRAY_A);
            
            return rest_ensure_response($vehicles);
        },
    ]);

    // POST /pi/v1/expenses/vehicles - Create vehicle
    register_rest_route($namespace, '/expenses/vehicles', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_vehicles';
            
            // Check if table exists, create if not
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                pi_expenses_force_recreate_tables();
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                if (!$table_exists) {
                    return new WP_Error('table_error', 'Failed to create vehicles table', ['status' => 500]);
                }
            }
            
            $result = $wpdb->insert($table, [
                'user_id' => $user_id,
                'make' => sanitize_text_field($data['make'] ?? ''),
                'model' => sanitize_text_field($data['model'] ?? ''),
                'registration' => sanitize_text_field($data['registration'] ?? ''),
                'fuel_type' => sanitize_text_field($data['fuel_type'] ?? 'Diesel'),
                'co2_emissions' => (float) ($data['co2_emissions'] ?? 0),
                'current_mileage' => (int) ($data['current_mileage'] ?? 0),
                'business_use_pct' => (int) ($data['business_use_pct'] ?? 100),
                'is_company_car' => (int) ($data['is_company_car'] ?? 0),
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            
            if ($result) {
                return rest_ensure_response(['success' => true, 'id' => $wpdb->insert_id]);
            }
            
            return new WP_Error('insert_failed', 'Failed to create vehicle: ' . $wpdb->last_error, ['status' => 500]);
        },
    ]);

    // PUT /pi/v1/expenses/vehicles/{id} - Update vehicle
    register_rest_route($namespace, '/expenses/vehicles/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_vehicles';
            
            $update_data = ['updated_at' => current_time('mysql')];
            $allowed = ['make', 'model', 'registration', 'fuel_type', 'co2_emissions', 'current_mileage', 'business_use_pct', 'is_company_car'];
            
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
            }
            
            $result = $wpdb->update($table, $update_data, ['id' => $id, 'user_id' => $user_id]);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Vehicle updated']);
            }
            
            return new WP_Error('update_failed', 'Failed to update vehicle', ['status' => 500]);
        },
    ]);

    // DELETE /pi/v1/expenses/vehicles/{id} - Delete vehicle
    register_rest_route($namespace, '/expenses/vehicles/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $id = (int) $req->get_param('id');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_vehicles';
            
            // Soft delete
            $result = $wpdb->update($table, [
                'is_active' => 0,
                'updated_at' => current_time('mysql'),
            ], ['id' => $id, 'user_id' => $user_id]);
            
            if ($result) {
                return rest_ensure_response(['success' => true, 'message' => 'Vehicle deleted']);
            }
            
            return new WP_Error('delete_failed', 'Failed to delete vehicle', ['status' => 500]);
        },
    ]);

    // ============================================
    // TAX & COMPLIANCE ENDPOINTS
    // ============================================
    
    // GET /pi/v1/expenses/cis - Get CIS data
    register_rest_route($namespace, '/expenses/cis', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $period = (int) $req->get_param('period') ?: date('Y');
            
            global $wpdb;
            $table_expenses = $wpdb->prefix . 'pi_expenses';
            $table_suppliers = $wpdb->prefix . 'pi_suppliers';
            
            // Get CIS-liable expenses
            $cis_expenses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_expenses 
                WHERE user_id = %d AND cis_liable = 1 AND deleted_at IS NULL 
                AND YEAR(expense_date) = %d",
                $user_id, $period
            ), ARRAY_A);
            
            $total_deductions = 0;
            $total_payments = 0;
            
            // Group by supplier
            $supplier_data = [];
            
            foreach ($cis_expenses as $exp) {
                $supplier_name = $exp['supplier_name'];
                $amount = (float) $exp['amount'];
                $rate = (float) ($exp['cis_rate'] ?? 20);
                $deduction = ($amount * $rate) / 100;
                
                $total_payments += $amount;
                $total_deductions += $deduction;
                
                if (!isset($supplier_data[$supplier_name])) {
                    $supplier_data[$supplier_name] = [
                        'name' => $supplier_name,
                        'utr' => '',
                        'cis_status' => 'unknown',
                        'verified' => false,
                        'total_payments' => 0,
                        'total_deductions' => 0,
                    ];
                }
                
                $supplier_data[$supplier_name]['total_payments'] += $amount;
                $supplier_data[$supplier_name]['total_deductions'] += $deduction;
            }
            
            // Check for supplier details in suppliers table
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_suppliers'") === $table_suppliers) {
                foreach ($supplier_data as $name => &$supplier) {
                    $supplier_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT utr, cis_status, vat_number FROM $table_suppliers 
                        WHERE user_id = %d AND company_name = %s AND is_active = 1",
                        $user_id, $name
                    ), ARRAY_A);
                    
                    if ($supplier_row) {
                        $supplier['utr'] = $supplier_row['utr'] ?? '';
                        $supplier['cis_status'] = $supplier_row['cis_status'] ?? 'unknown';
                        $supplier['verified'] = !empty($supplier_row['utr']);
                    }
                }
            }
            
            // Count by status
            $registered_count = 0;
            $unregistered_count = 0;
            $gross_count = 0;
            
            foreach ($supplier_data as $supplier) {
                switch ($supplier['cis_status']) {
                    case 'registered':
                        $registered_count++;
                        break;
                    case 'unregistered':
                        $unregistered_count++;
                        break;
                    case 'gross':
                        $gross_count++;
                        break;
                }
            }
            
            // Get missing receipts count
            $missing_receipts = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_expenses 
                WHERE user_id = %d AND cis_liable = 1 AND deleted_at IS NULL 
                AND receipt_url IS NULL AND YEAR(expense_date) = %d",
                $user_id, $period
            ));
            
            // Assign IDs to subcontractors
            $subcontractors = [];
            $id = 1;
            foreach ($supplier_data as $name => $data) {
                $subcontractors[] = array_merge(['id' => $id++], $data);
            }
            
            return rest_ensure_response([
                'registered_count' => $registered_count,
                'unregistered_count' => $unregistered_count,
                'gross_count' => $gross_count,
                'total_deductions' => $total_deductions,
                'potential_savings' => 0,
                'missing_receipts' => $missing_receipts,
                'subcontractors' => $subcontractors,
            ]);
        },
    ]);

    // GET /pi/v1/expenses/vat - Get VAT data
    register_rest_route($namespace, '/expenses/vat', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $period = (int) $req->get_param('period') ?: date('Y');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Get expenses with VAT
            $vat_expenses = $wpdb->get_results($wpdb->prepare(
                "SELECT tax_rate, SUM(amount) as total, SUM(tax_amount) as total_vat 
                FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND YEAR(expense_date) = %d 
                AND tax_amount > 0
                GROUP BY tax_rate",
                $user_id, $period
            ), ARRAY_A);
            
            $standard_amount = 0;
            $standard_vat = 0;
            $reduced_amount = 0;
            $reduced_vat = 0;
            $zero_amount = 0;
            
            foreach ($vat_expenses as $row) {
                if ($row['tax_rate'] == 20) {
                    $standard_amount += (float) $row['total'];
                    $standard_vat += (float) $row['total_vat'];
                } elseif ($row['tax_rate'] == 5) {
                    $reduced_amount += (float) $row['total'];
                    $reduced_vat += (float) $row['total_vat'];
                } elseif ($row['tax_rate'] == 0) {
                    $zero_amount += (float) $row['total'];
                }
            }
            
            // Get zero-rated expenses separately
            $zero_rated = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND YEAR(expense_date) = %d 
                AND tax_rate = 0 AND tax_amount = 0",
                $user_id, $period
            ));
            $zero_amount += (float) $zero_rated;
            
            return rest_ensure_response([
                'standard_amount' => $standard_amount,
                'standard_vat' => $standard_vat,
                'reduced_amount' => $reduced_amount,
                'reduced_vat' => $reduced_vat,
                'zero_amount' => $zero_amount,
                'total_reclaimable' => $standard_vat + $reduced_vat,
            ]);
        },
    ]);

    // GET /pi/v1/expenses/retention - Get retention data
    register_rest_route($namespace, '/expenses/retention', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Get expenses with retention
            $retention_items = $wpdb->get_results($wpdb->prepare(
                "SELECT id as expense_id, supplier_name, job_id, amount as original_amount, 
                        retention_percent, 
                        (amount * retention_percent / 100) as retention_amount,
                        expense_date
                FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL AND retention_percent > 0",
                $user_id
            ), ARRAY_A);
            
            // Enhance with job titles
            foreach ($retention_items as &$item) {
                if ($item['job_id']) {
                    $job = get_post($item['job_id']);
                    $item['job_title'] = $job ? $job->post_title : 'Unknown Job';
                } else {
                    $item['job_title'] = '-';
                }
                // Calculate release date (typically 12 months from expense date for retention)
                $item['release_date'] = date('Y-m-d', strtotime($item['expense_date'] . ' +12 months'));
                $item['is_overdue'] = strtotime($item['release_date']) < time();
                $item['can_release'] = $item['is_overdue'];
            }
            
            $total_held = array_sum(array_column($retention_items, 'retention_amount'));
            $due_release = array_sum(array_filter($retention_items, function($i) { return $i['can_release']; }, ARRAY_FILTER_USE_BOTH) ?: []);
            
            return rest_ensure_response([
                'active_count' => count($retention_items),
                'total_held' => $total_held,
                'due_release' => $due_release,
                'items' => $retention_items,
            ]);
        },
    ]);

    // ============================================
    // APPROVALS ENDPOINTS
    // ============================================
    
    // GET /pi/v1/expenses/approvals - Get pending approvals
    register_rest_route($namespace, '/expenses/approvals', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $view = sanitize_text_field($req->get_param('view') ?: 'pending');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            if ($view === 'pending') {
                // Get expenses pending approval (submitted by others, pending current user's approval)
                $expenses = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table 
                    WHERE approval_status = 'pending' AND submitted_by != %d AND deleted_at IS NULL 
                    ORDER BY created_at DESC",
                    $user_id
                ), ARRAY_A);
            } else if ($view === 'submitted') {
                // Get current user's submitted expenses
                $expenses = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table 
                    WHERE submitted_by = %d AND deleted_at IS NULL 
                    ORDER BY created_at DESC",
                    $user_id
                ), ARRAY_A);
            } else {
                $expenses = [];
            }
            
            // Enhance with user names
            foreach ($expenses as &$expense) {
                $user = get_userdata($expense['submitted_by']);
                $expense['submitter_name'] = $user ? $user->display_name : 'Unknown';
                if ($expense['job_id']) {
                    $job = get_post($expense['job_id']);
                    $expense['job_title'] = $job ? $job->post_title : 'Unknown Job';
                }
            }
            
            return rest_ensure_response([
                'pending_count' => count($expenses),
                'expenses' => $expenses,
                'workflow' => [
                    'levels' => ['Manager', 'Finance', 'Director'],
                    'auto_approve_below' => 100.00,
                    'require_receipt_above' => 25.00,
                ],
            ]);
        },
    ]);

    // POST /pi/v1/expenses/approvals/{id}/approve - Approve expense
    register_rest_route($namespace, '/expenses/approvals/(?P<id>\d+)/approve', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $expense_id = (int) $req->get_param('id');
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            $result = $wpdb->update($table, [
                'approval_status' => 'approved',
                'approved_by' => $user_id,
                'approved_at' => current_time('mysql'),
            ], ['id' => $expense_id], ['%s', '%d', '%s'], ['%d']);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Expense approved']);
            }
            
            return new WP_Error('update_failed', 'Failed to approve expense', ['status' => 500]);
        },
    ]);

    // POST /pi/v1/expenses/approvals/{id}/reject - Reject expense
    register_rest_route($namespace, '/expenses/approvals/(?P<id>\d+)/reject', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $expense_id = (int) $req->get_param('id');
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            $result = $wpdb->update($table, [
                'approval_status' => 'rejected',
                'approved_by' => $user_id,
                'approved_at' => current_time('mysql'),
                'rejection_reason' => sanitize_textarea_field($data['reason'] ?? ''),
            ], ['id' => $expense_id], ['%s', '%d', '%s', '%s'], ['%d']);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Expense rejected']);
            }
            
            return new WP_Error('update_failed', 'Failed to reject expense', ['status' => 500]);
        },
    ]);

    // POST /pi/v1/expenses/approvals/{id}/request-changes - Request changes
    register_rest_route($namespace, '/expenses/approvals/(?P<id>\d+)/request-changes', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $expense_id = (int) $req->get_param('id');
            $data = $req->get_json_params();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            $result = $wpdb->update($table, [
                'approval_status' => 'needs_info',
                'change_request_note' => sanitize_textarea_field($data['note'] ?? ''),
            ], ['id' => $expense_id], ['%s', '%s'], ['%d']);
            
            if ($result !== false) {
                return rest_ensure_response(['success' => true, 'message' => 'Changes requested']);
            }
            
            return new WP_Error('update_failed', 'Failed to request changes', ['status' => 500]);
        },
    ]);

    // ============================================
    // RECEIPTS ENDPOINT
    // ============================================
    
    // GET /pi/v1/expenses/receipts - Get unreconciled receipts
    register_rest_route($namespace, '/expenses/receipts', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            global $wpdb;
            $table = $wpdb->prefix . 'pi_expenses';
            
            // Get expenses with receipt_url that are unreconciled
            $receipts = $wpdb->get_results($wpdb->prepare(
                "SELECT id, expense_date, description, amount, receipt_url, supplier_name, category,
                        created_at, status
                FROM $table 
                WHERE user_id = %d AND deleted_at IS NULL 
                AND receipt_url IS NOT NULL 
                AND status = 'draft'
                ORDER BY created_at DESC LIMIT 50",
                $user_id
            ), ARRAY_A);
            
            return rest_ensure_response($receipts);
        },
    ]);
});

/**
 * NEW SHORTCODE: [planning_workspace_expenses_v3]
 *
 * Multi-tab expense management interface with comprehensive features
 */
function pi_workspace_expenses_v3_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="pi-expenses-wrapper-v3">
            <div class="pi-auth-required">
                <div class="pi-auth-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3>Sign In Required</h3>
                <p>Please log in to view and manage your project expenses.</p>
            </div>
        </div>';
    }

    $user_id = get_current_user_id();
    $categories = pi_expenses_get_categories();
    $statuses = pi_expenses_get_statuses();
    $qbo_statuses = pi_expenses_get_qbo_sync_statuses();
    $payment_methods = pi_expenses_get_payment_methods();
    $cis_rates = pi_expenses_get_cis_rates();
    $vat_rates = pi_expenses_get_vat_rates();
    
    // Create REST API nonce
    wp_enqueue_script('wp-api');
    wp_localize_script('wp-api', 'wpApiSettings', [
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
    
    // Enqueue v3 assets
    wp_enqueue_style(
        'google-fonts-inter',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'planningindex-expenses-v3',
        plugin_dir_url(__FILE__) . '../assets/expenses-v3.css',
        [],
        PI_EXPENSES_VERSION
    );
    wp_enqueue_script(
        'planningindex-expenses-v3',
        plugin_dir_url(__FILE__) . '../assets/expenses-v3.js',
        ['jquery'],
        PI_EXPENSES_VERSION,
        true
    );
    
    // Pass data to JS
    wp_localize_script('planningindex-expenses-v3', 'piExpensesData', [
        'userId' => $user_id,
        'categories' => $categories,
        'statuses' => $statuses,
        'qboStatuses' => $qbo_statuses,
        'paymentMethods' => $payment_methods,
        'cisRates' => $cis_rates,
        'vatRates' => $vat_rates,
        'restNonce' => wp_create_nonce('wp_rest'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
    
    // Feather Icons
    wp_enqueue_script(
        'feather-icons',
        'https://unpkg.com/feather-icons',
        [],
        null,
        true
    );
    
    // Chart.js for dashboard
    wp_enqueue_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        [],
        null,
        true
    );
    
    ob_start();
    ?><div class="pi-expenses-wrapper-v3" data-user-id="<?php echo esc_attr($user_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
        <div class="pi-exp-app">
            <main class="pi-exp-main"><?php // No whitespace here ?>
            <!-- Premium SaaS Header -->
            <header class="pi-exp-saas-header">
                <div class="pi-exp-header-left">
                    <button class="pi-exp-mobile-menu-toggle" aria-label="Toggle menu"><?php // No whitespace here ?>
                        <i data-feather="menu"></i>
                    </button>
                    <div class="pi-exp-brand">
                        <h1 class="pi-exp-title">Expenses</h1>
                    </div>
                </div><?php // No whitespace here ?>
                
                <nav class="pi-exp-nav-container">
                    <div class="pi-exp-nav-scroll">
                        <button class="pi-exp-nav-item active" data-tab="dashboard"><?php // No whitespace here ?>
                            <i data-feather="pie-chart"></i><?php // No whitespace here ?>
                            <span>Dashboard</span>
                        </button>
                        <button class="pi-exp-nav-item" data-tab="ledger"><?php // No whitespace here ?>
                            <i data-feather="list"></i><?php // No whitespace here ?>
                            <span>Expense Ledger</span>
                        </button>
                        <button class="pi-exp-nav-item" data-tab="suppliers"><?php // No whitespace here ?>
                            <i data-feather="truck"></i><?php // No whitespace here ?>
                            <span>Supplier Center</span>
                        </button>
                        <button class="pi-exp-nav-item" data-tab="tax"><?php // No whitespace here ?>
                            <i data-feather="file-text"></i><?php // No whitespace here ?>
                            <span>Tax & Compliance</span>
                        </button>
                        <button class="pi-exp-nav-item" data-tab="settings"><?php // No whitespace here ?>
                            <i data-feather="settings"></i><?php // No whitespace here ?>
                            <span>Settings</span>
                        </button>
                    </div>
                </nav><?php // No whitespace here ?>
                
                <div class="pi-exp-header-right">
                    <div class="pi-exp-search-wrapper">
                        <button class="pi-exp-search-trigger" aria-label="Search"><?php // No whitespace here ?>
                            <i data-feather="search"></i>
                        </button>
                        <div class="pi-exp-search-input"><?php // No whitespace here ?>
                            <i data-feather="search"></i>
                            <input type="text" id="global-search" placeholder="Search expenses..." data-filter="search">
                            <button class="pi-exp-search-close" aria-label="Close search"><?php // No whitespace here ?>
                                <i data-feather="x"></i>
                            </button>
                        </div>
                    </div><?php // No whitespace here ?>
                    
                    <div class="pi-exp-notifications">
                        <button class="pi-exp-icon-btn" aria-label="Notifications"><?php // No whitespace here ?>
                            <i data-feather="bell"></i><?php // No whitespace here ?>
                            <span class="pi-exp-notification-badge" id="notification-badge" style="display: none;"></span>
                        </button>
                        <div class="pi-exp-dropdown pi-exp-notifications-dropdown">
                            <div class="pi-exp-dropdown-header">
                                <h4>Notifications</h4>
                                <button class="pi-exp-text-btn" id="mark-all-read" style="display: none;">Mark all read</button>
                            </div>
                            <div class="pi-exp-dropdown-list" id="notification-list">
                                <div class="pi-empty-state" style="padding: 2rem;">
                                    <i data-feather="bell-off" style="width: 32px; height: 32px; opacity: 0.5;"></i>
                                    <p>No notifications</p>
                                </div>
                            </div>
                        </div>
                    </div><?php // No whitespace here ?>
                    
                    <button class="pi-exp-icon-btn pi-exp-help-btn" aria-label="Help Center" title="Help Center"><?php // No whitespace here ?>
                        <i data-feather="help-circle"></i>
                    </button><?php // No whitespace here ?>
                    
                    <div class="pi-exp-user-menu">
                        <button class="pi-exp-avatar" aria-label="User menu"><?php // No whitespace here ?>
                            <img src="<?php echo esc_url(get_avatar_url($user_id, ['size' => 64])); ?>" alt="<?php echo esc_attr(wp_get_current_user()->display_name); ?>"><?php // No whitespace here ?>
                            <span class="pi-exp-status-dot online"></span>
                        </button>
                        <div class="pi-exp-dropdown pi-exp-user-dropdown">
                            <div class="pi-exp-dropdown-header">
                                <div class="pi-exp-user-info">
                                    <strong><?php echo esc_html(wp_get_current_user()->display_name); ?></strong>
                                    <span><?php echo esc_html(wp_get_current_user()->user_email); ?></span>
                                </div>
                            </div>
                            <div class="pi-exp-dropdown-divider"></div>
                            <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="pi-exp-dropdown-item"><?php // No whitespace here ?>
                                <i data-feather="user"></i><?php // No whitespace here ?>
                                <span>Profile</span>
                            </a>
                            <a href="#" class="pi-exp-dropdown-item" data-tab-trigger="settings"><?php // No whitespace here ?>
                                <i data-feather="settings"></i><?php // No whitespace here ?>
                                <span>Settings</span>
                            </a>
                            <div class="pi-exp-dropdown-divider"></div>
                            <a href="<?php echo esc_url(wp_logout_url()); ?>" class="pi-exp-dropdown-item danger"><?php // No whitespace here ?>
                                <i data-feather="log-out"></i><?php // No whitespace here ?>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div><?php // No whitespace here ?>
                    
                    <button class="pi-exp-add-btn" data-action="add-expense"><?php // No whitespace here ?>
                        <i data-feather="plus"></i><?php // No whitespace here ?>
                        <span>Add Expense</span>
                    </button>
                </div>
            </header><?php // No whitespace here ?>
            
            <!-- Mobile Floating Action Button -->
            <button class="pi-exp-mobile-fab" data-action="add-expense" aria-label="Add Expense"><?php // No whitespace here ?>
                <i data-feather="plus"></i>
            </button>
                
                <!-- Tab Content -->
                <div class="pi-exp-content">
                    <!-- Dashboard Tab -->
                    <div class="pi-exp-tab-content active" id="tab-dashboard">
                        <div class="pi-dashboard-grid" id="dashboard-kpis">
                            <!-- KPIs loaded via JS -->
                        </div>
                        
                        <div class="pi-dashboard-charts">
                            <div class="pi-chart-card">
                                <h3>Spend Trend (Last 6 Months)</h3>
                                <div class="pi-chart-container">
                                    <canvas id="spendTrendChart"></canvas>
                                </div>
                            </div>
                            <div class="pi-chart-card">
                                <h3>Category Breakdown</h3>
                                <div class="pi-chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pi-dashboard-activity">
                            <div class="pi-activity-card">
                                <div class="pi-activity-header">
                                    <h3>Recent Activity</h3>
                                    <button class="pi-btn pi-btn-ghost pi-btn-icon" data-action="refresh-dashboard">
                                        <i data-feather="refresh-cw"></i>
                                    </button>
                                </div>
                                <div class="pi-activity-list" id="dashboard-activity">
                                    <!-- Activity loaded via JS -->
                                </div>
                            </div>
                            
                            <div class="pi-chart-card">
                                <h3>Job Profitability</h3>
                                <div class="pi-chart-container">
                                    <canvas id="jobProfitabilityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ledger Tab -->
                    <div class="pi-exp-tab-content" id="tab-ledger">
                        <div class="pi-ledger-header">
                            <div class="pi-ledger-filters" id="ledger-filters">
                                <input type="date" data-filter="date_from" placeholder="From">
                                <input type="date" data-filter="date_to" placeholder="To">
                                <select data-filter="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $key => $cat) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($cat['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select data-filter="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($statuses as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="pi-ledger-spacer"></div>
                            <div class="pi-ledger-actions">
                                <button class="pi-btn pi-btn-secondary" data-action="export">
                                    <i data-feather="download"></i> Export
                                </button>
                            </div>
                        </div>
                        
                        <div class="pi-bulk-toolbar" id="bulk-actions-toolbar">
                            <span id="selected-count">0 selected</span>
                            <button class="pi-btn pi-btn-ghost" data-bulk-action="approve">Approve</button>
                            <button class="pi-btn pi-btn-ghost" data-bulk-action="sync_to_qbo">Sync to QBO</button>
                            <button class="pi-btn pi-btn-ghost" data-bulk-action="delete">Delete</button>
                        </div>
                        
                        <div class="pi-data-table" id="ledger-table-container">
                            <table id="ledger-table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all-expenses"></th>
                                        <th>Date</th>
                                        <th>Job</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th class="pi-amount">Amount</th>
                                        <th>Status</th>
                                        <th>QBO Sync</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded via JS -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="pi-pagination" id="ledger-pagination">
                            <!-- Pagination loaded via JS -->
                        </div>
                    </div>
                    
                    <!-- Settings Tab -->
                    <div class="pi-exp-tab-content" id="tab-settings">
                        <div class="pi-settings-grid">
                            <div class="pi-settings-card">
                                <div class="pi-settings-card-header">
                                    <h3>General Settings</h3>
                                    <p>Configure your expense preferences</p>
                                </div>
                                <div class="pi-settings-card-body">
                                    <div class="pi-form-group">
                                        <label class="pi-form-label">Default Category</label>
                                        <select class="pi-form-control" id="setting-default-category">
                                            <?php foreach ($categories as $key => $cat) : ?>
                                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($cat['label']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="pi-form-group">
                                        <label class="pi-form-label">Default Payment Method</label>
                                        <select class="pi-form-control" id="setting-default-payment">
                                            <?php foreach ($payment_methods as $key => $label) : ?>
                                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="pi-form-group">
                                        <label class="pi-form-label">Currency</label>
                                        <select class="pi-form-control" id="setting-currency">
                                            <option value="GBP">GBP (£)</option>
                                            <option value="USD">USD ($)</option>
                                            <option value="EUR">EUR (€)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pi-settings-card">
                                <div class="pi-settings-card-header">
                                    <h3>Tax & CIS Settings</h3>
                                    <p>Configure tax and CIS preferences</p>
                                </div>
                                <div class="pi-settings-card-body">
                                    <div class="pi-form-group">
                                        <label class="pi-toggle">
                                            <input type="checkbox" id="setting-vat-registered">
                                            <span class="pi-toggle-slider"></span>
                                            <span class="pi-toggle-label">VAT Registered</span>
                                        </label>
                                    </div>
                                    <div class="pi-form-group">
                                        <label class="pi-toggle">
                                            <input type="checkbox" id="setting-cis-enabled">
                                            <span class="pi-toggle-slider"></span>
                                            <span class="pi-toggle-label">Enable CIS Tracking</span>
                                        </label>
                                        <p class="pi-form-help">Construction Industry Scheme deductions for subcontractors</p>
                                    </div>
                                    <div class="pi-form-group">
                                        <label class="pi-form-label">Default VAT Rate</label>
                                        <select class="pi-form-control" id="setting-default-vat">
                                            <?php foreach ($vat_rates as $rate => $label) : ?>
                                                <option value="<?php echo esc_attr($rate); ?>"><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pi-settings-card">
                                <div class="pi-settings-card-header">
                                    <h3>QuickBooks Online</h3>
                                    <p>Sync settings for QBO integration</p>
                                </div>
                                <div class="pi-settings-card-body">
                                    <div class="pi-form-group">
                                        <label class="pi-toggle">
                                            <input type="checkbox" id="setting-qbo-auto-sync">
                                            <span class="pi-toggle-slider"></span>
                                            <span class="pi-toggle-label">Auto-sync to QBO</span>
                                        </label>
                                    </div>
                                    <div class="pi-form-group">
                                        <label class="pi-form-label">Sync Frequency</label>
                                        <select class="pi-form-control" id="setting-qbo-frequency">
                                            <option value="realtime">Real-time</option>
                                            <option value="hourly">Hourly</option>
                                            <option value="daily">Daily</option>
                                            <option value="manual">Manual only</option>
                                        </select>
                                    </div>
                                    <button class="pi-btn pi-btn-secondary" id="btn-connect-qbo">
                                        <i data-feather="link"></i> Connect to QuickBooks
                                    </button>
                                </div>
                            </div>
                            
                            <div class="pi-settings-card">
                                <div class="pi-settings-card-header">
                                    <h3>Automation Rules</h3>
                                    <p>Create if-this-then-that rules for expenses</p>
                                </div>
                                <div class="pi-settings-card-body">
                                    <div id="automation-rules-list">
                                        <!-- Rules loaded via JS -->
                                    </div>
                                    <button class="pi-btn pi-btn-secondary" id="btn-add-rule">
                                        <i data-feather="plus"></i> Add Rule
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Job Costing Tab - Phase 3 -->
                    <div class="pi-exp-tab-content" id="tab-jobcosting">
                        <div class="pi-jobcosting-layout">
                            <div class="pi-job-selector" id="jobcosting-selector">
                                <div class="pi-job-selector-header">
                                    <h3>Your Jobs</h3>
                                </div>
                                <div class="pi-job-cards">
                                    <!-- Jobs loaded via JS -->
                                </div>
                            </div>
                            <div class="pi-job-dashboard" id="jobcosting-dashboard">
                                <!-- Job dashboard loaded via JS -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mileage Tab - Phase 5 -->
                    <div class="pi-exp-tab-content" id="tab-mileage">
                        <div class="pi-mileage-dashboard" id="mileage-content">
                            <!-- Mileage content loaded via JS -->
                        </div>
                    </div>
                    
                    <!-- Suppliers Tab - Phase 4 -->
                    <div class="pi-exp-tab-content" id="tab-suppliers">
                        <div class="pi-suppliers-layout">
                            <div class="pi-suppliers-sidebar" id="suppliers-content">
                                <!-- Suppliers list loaded via JS -->
                            </div>
                            <div class="pi-supplier-detail" id="supplier-detail">
                                <div class="pi-empty-state">
                                    <i data-feather="truck"></i>
                                    <p>Select a supplier to view details</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tax & Compliance Tab - Phase 5 -->
                    <div class="pi-exp-tab-content" id="tab-tax">
                        <div class="pi-tax-dashboard" id="tax-content">
                            <!-- Tax content loaded via JS -->
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * Helper function to build hierarchical cost code tree
 */
function pi_expenses_build_cost_code_tree($flat_codes) {
    $tree = [];
    $lookup = [];
    
    foreach ($flat_codes as $code) {
        $node = [
            'code' => $code['code'],
            'name' => $code['name'],
            'budget' => (float) $code['budget'],
            'actual' => (float) $code['actual'],
            'remaining' => (float) $code['remaining'],
            'children' => [],
        ];
        
        $lookup[$code['code']] = &$node;
        
        // Check if this is a child code (has parent)
        $parts = explode('.', $code['code']);
        if (count($parts) > 1) {
            $parent_code = $parts[0];
            if (isset($lookup[$parent_code])) {
                $lookup[$parent_code]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        } else {
            $tree[] = &$node;
        }
    }
    
    return $tree;
}

