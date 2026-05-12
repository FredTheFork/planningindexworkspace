<?php
/**
 * Database Schema Check - Debug script to understand current table structure
 */

// Include WordPress
$wp_base_path = dirname(dirname(dirname(__FILE__)));
require_once $wp_base_path . '/wp-config.php';

global $wpdb;

echo "<h2>Database Schema Analysis</h2>\n";

// Check daily reports materials table
echo "<h3>Daily Reports Materials Table</h3>\n";
$materials_table = $wpdb->prefix . 'pi_crm_daily_report_materials';
$materials_columns = $wpdb->get_results("SHOW COLUMNS FROM {$materials_table}");

echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>\n";

foreach ($materials_columns as $column) {
    echo "<tr>\n";
    echo "<td>{$column->Field}</td>\n";
    echo "<td>{$column->Type}</td>\n";
    echo "<td>{$column->Null}</td>\n";
    echo "<td>{$column->Key}</td>\n";
    echo "</tr>\n";
}
echo "</table>\n";

// Check BOM items table
echo "<h3>BOM Items Table</h3>\n";
$bom_table = $wpdb->prefix . 'pi_bom_items';
$bom_exists = $wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table;

if ($bom_exists) {
    $bom_columns = $wpdb->get_results("SHOW COLUMNS FROM {$bom_table}");
    
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>\n";
    
    foreach ($bom_columns as $column) {
        echo "<tr>\n";
        echo "<td>{$column->Field}</td>\n";
        echo "<td>{$column->Type}</td>\n";
        echo "<td>{$column->Null}</td>\n";
        echo "<td>{$column->Key}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Check BOM data for job 56746
    echo "<h3>BOM Items for Job 56746</h3>\n";
    $bom_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$bom_table} WHERE project_id = %d LIMIT 10",
        56746
    ));
    
    echo "<p><strong>Total BOM Items:</strong> " . count($bom_items) . "</p>\n";
    
    if (!empty($bom_items)) {
        echo "<p><em>No BOM items found for job 56746</em></p>\n";
        
        // Check if any BOM items exist at all
        $total_bom = $wpdb->get_var("SELECT COUNT(*) FROM {$bom_table}");
        echo "<p><strong>Total BOM Items in System:</strong> {$total_bom}</p>\n";
        
        // Check different job IDs
        $job_ids = $wpdb->get_col("SELECT DISTINCT project_id FROM {$bom_table} LIMIT 10");
        echo "<p><strong>Sample Job IDs:</strong> " . implode(', ', $job_ids) . "</p>\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>ID</th><th>Project ID</th><th>Material Name</th><th>Status</th><th>Quantity</th></tr>\n";
        
        foreach ($bom_items as $item) {
            echo "<tr>\n";
            echo "<td>{$item->id}</td>\n";
            echo "<td>{$item->project_id}</td>\n";
            echo "<td>{$item->material_name}</td>\n";
            echo "<td>{$item->status}</td>\n";
            echo "<td>{$item->quantity}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
} else {
    echo "<p><strong>BOM Table Missing:</strong> {$bom_table} does not exist</p>\n";
}

// Check daily reports table for missing columns
echo "<h3>Daily Reports Table</h3>\n";
$daily_reports_table = $wpdb->prefix . 'pi_crm_daily_reports';
$daily_reports_columns = $wpdb->get_results("SHOW COLUMNS FROM {$daily_reports_table}");

echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>\n";

foreach ($daily_reports_columns as $column) {
    echo "<tr>\n";
    echo "<td>{$column->Field}</td>\n";
    echo "<td>{$column->Type}</td>\n";
    echo "<td>{$column->Null}</td>\n";
    echo "<td>{$column->Key}</td>\n";
    echo "</tr>\n";
}
echo "</table>\n";
?>
