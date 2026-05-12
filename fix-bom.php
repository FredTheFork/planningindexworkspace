<?php
/**
 * Fix BOM Data - Create sample BOM items and fix database schema
 */

// Simple WordPress bootstrap
if (!defined('ABSPATH')) {
    $base = dirname(__FILE__);
    while (!file_exists($base . '/wp-config.php')) {
        $base = dirname($base);
    }
    require_once $base . '/wp-config.php';
}

global $wpdb;

echo "<h2>BOM Data Fix</h2>\n";

// 1. Fix database schema
echo "<h3>1. Fixing Database Schema</h3>\n";

$materials_table = $wpdb->prefix . 'pi_crm_daily_report_materials';

// Add bom_item_id column if missing
$bom_column_check = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
    DB_NAME, $materials_table, 'bom_item_id'
));

if (!$bom_column_check) {
    $wpdb->query("ALTER TABLE {$materials_table} ADD COLUMN bom_item_id bigint(20) unsigned DEFAULT NULL");
    echo "<p>✅ Added bom_item_id column</p>\n";
} else {
    echo "<p>✅ bom_item_id column already exists</p>\n";
}

// Add material_id column if missing
$material_column_check = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
    DB_NAME, $materials_table, 'material_id'
));

if (!$material_column_check) {
    $wpdb->query("ALTER TABLE {$materials_table} ADD COLUMN material_id bigint(20) unsigned DEFAULT NULL");
    echo "<p>✅ Added material_id column</p>\n";
} else {
    echo "<p>✅ material_id column already exists</p>\n";
}

// 2. Create sample BOM items for job 56746
echo "<h3>2. Creating Sample BOM Items for Job 56746</h3>\n";

$bom_table = $wpdb->prefix . 'pi_bom_items';
$bom_exists = $wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table;

if ($bom_exists) {
    // Check existing BOM items
    $existing_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$bom_table} WHERE project_id = %d",
        56746
    ));
    
    echo "<p>Existing BOM items for job 56746: {$existing_count}</p>\n";
    
    if ($existing_count == 0) {
        echo "<p>Creating sample BOM items...</p>\n";
        
        $sample_materials = array(
            array(
                'project_id' => 56746,
                'material_name' => 'Concrete Mix',
                'material_sku' => 'CONC-001',
                'category' => 'Materials',
                'quantity' => 10.5,
                'unit' => 'm3',
                'unit_cost' => 85.00,
                'supplier_id' => 1,
                'status' => 'approved'
            ),
            array(
                'project_id' => 56746,
                'material_name' => 'Steel Rebar',
                'material_sku' => 'STEEL-001',
                'category' => 'Materials',
                'quantity' => 500,
                'unit' => 'kg',
                'unit_cost' => 2.50,
                'supplier_id' => 1,
                'status' => 'approved'
            ),
            array(
                'project_id' => 56746,
                'material_name' => 'Formwork Plywood',
                'material_sku' => 'WOOD-001',
                'category' => 'Materials',
                'quantity' => 20,
                'unit' => 'sheets',
                'unit_cost' => 45.00,
                'supplier_id' => 1,
                'status' => 'approved'
            )
        );
        
        foreach ($sample_materials as $material) {
            $wpdb->insert($bom_table, $material);
        }
        
        echo "<p>✅ Created " . count($sample_materials) . " sample BOM items</p>\n";
    }
    
    // Show current BOM items
    $current_bom = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$bom_table} WHERE project_id = %d ORDER BY id LIMIT 5",
        56746
    ), ARRAY_A);
    
    echo "<h4>Current BOM Items for Job 56746:</h4>\n";
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Material Name</th><th>SKU</th><th>Quantity</th><th>Unit</th><th>Status</th></tr>\n";
    
    foreach ($current_bom as $item) {
        echo "<tr>\n";
        echo "<td>{$item->id}</td>\n";
        echo "<td>{$item->material_name}</td>\n";
        echo "<td>{$item->material_sku}</td>\n";
        echo "<td>{$item->quantity}</td>\n";
        echo "<td>{$item->unit}</td>\n";
        echo "<td>{$item->status}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
} else {
    echo "<p>❌ BOM table does not exist: {$bom_table}</p>\n";
}

echo "<h3>✅ BOM Data Fix Complete</h3>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ol>\n";
echo "<li>1. Test materials dropdown in daily reports</li>\n";
echo "<li>2. Try 'Sync from BOM' button</li>\n";
echo "<li>3. Check debug logs for any issues</li>\n";
echo "</ol>\n";
?>
