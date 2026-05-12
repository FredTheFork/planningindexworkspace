<?php
/**
 * Test script to verify Daily Reports fixes
 */

// Include WordPress
require_once('../../../wp-config.php');

// Test database schema
global $wpdb;

$daily_reports_table = $wpdb->prefix . 'pi_crm_daily_reports';
$audit_table = $wpdb->prefix . 'pi_crm_daily_reports_audit';

echo "=== Daily Reports Database Schema Test ===\n\n";

// Check if tables exist
$daily_reports_exists = $wpdb->get_var("SHOW TABLES LIKE '{$daily_reports_table}'") === $daily_reports_table;
$audit_exists = $wpdb->get_var("SHOW TABLES LIKE '{$audit_table}'") === $audit_table;

echo "Daily Reports Table: " . ($daily_reports_exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "Audit Table: " . ($audit_exists ? "✅ EXISTS" : "❌ MISSING") . "\n\n";

if ($daily_reports_exists) {
    // Check required columns
    $columns = $wpdb->get_col("DESCRIBE {$daily_reports_table}");
    $required_columns = ['id', 'job_id', 'report_date', 'report_status', 'is_deleted', 'created_by', 'created_at'];
    
    echo "Required Columns:\n";
    foreach ($required_columns as $column) {
        echo "  - {$column}: " . (in_array($column, $columns) ? "✅" : "❌") . "\n";
    }
}

if ($audit_exists) {
    // Check audit table structure
    $audit_columns = $wpdb->get_col("DESCRIBE {$audit_table}");
    $required_audit_columns = ['id', 'daily_report_id', 'job_id', 'action_type', 'created_at'];
    
    echo "\nAudit Table Columns:\n";
    foreach ($required_audit_columns as $column) {
        echo "  - {$column}: " . (in_array($column, $audit_columns) ? "✅" : "❌") . "\n";
    }
}

// Test migration function
echo "\n=== Testing Migration Function ===\n";

if (class_exists('PI_Daily_Reports_Database')) {
    $database = PI_Daily_Reports_Database::get_instance();
    try {
        $database->run_migrations();
        echo "✅ Migration completed successfully\n";
    } catch (Exception $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ PI_Daily_Reports_Database class not found\n";
}

echo "\n=== Test Complete ===\n";
?>
