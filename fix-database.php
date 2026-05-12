<?php
/**
 * Fix Database Schema - Add missing columns and create sample BOM data
 */

// Include WordPress
$wp_base_path = dirname(dirname(dirname(__FILE__)));
require_once $wp_base_path . '/wp-config.php';

global $wpdb;

echo "<h2>Database Schema Fix</h2>\n";

// Fix 1: Add missing bom_item_id column to daily reports materials table
echo "<h3>1. Adding bom_item_id column to daily reports materials table</h3>\n";

$materials_table = $wpdb->prefix . 'pi_crm_daily_report_materials';

// Check if bom_item_id column exists
$bom_column_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = %s 
     AND TABLE_NAME = %s 
     AND COLUMN_NAME = %s",
    DB_NAME, $materials_table, 'bom_item_id'
));

if (!$bom_column_exists) {
    echo "<p>Adding bom_item_id column...</p>\n";
    
    $alter_sql = "ALTER TABLE {$materials_table} ADD COLUMN bom_item_id bigint(20) unsigned DEFAULT NULL AFTER material_id";
    $wpdb->query($alter_sql);
    echo "<p>✅ bom_item_id column added</p>\n";
} else {
    echo "<p>✅ bom_item_id column already exists</p>\n";
}

// Fix 2: Check if material_id column exists
$material_column_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = %s 
     AND TABLE_NAME = %s 
     AND COLUMN_NAME = %s",
    DB_NAME, $materials_table, 'material_id'
));

if (!$material_column_exists) {
    echo "<p>Adding material_id column...</p>\n";
    
    $alter_sql = "ALTER TABLE {$materials_table} ADD COLUMN material_id bigint(20) unsigned DEFAULT NULL AFTER bom_item_id";
    $wpdb->query($alter_sql);
    echo "<p>✅ material_id column added</p>\n";
} else {
    echo "<p>✅ material_id column already exists</p>\n";
}

// Fix 3: Add last_auto_save column to daily reports table
echo "<h3>2. Adding last_auto_save column to daily reports table</h3>\n";

$daily_reports_table = $wpdb->prefix . 'pi_crm_daily_reports';

$last_save_column_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = %s 
     AND TABLE_NAME = %s 
     AND COLUMN_NAME = %s",
    DB_NAME, $daily_reports_table, 'last_auto_save'
));

if (!$last_save_column_exists) {
    echo "<p>Adding last_auto_save column...</p>\n";
    
    $alter_sql = "ALTER TABLE {$daily_reports_table} ADD COLUMN last_auto_save datetime DEFAULT NULL";
    $wpdb->query($alter_sql);
    echo "<p>✅ last_auto_save column added</p>\n";
} else {
    echo "<p>✅ last_auto_save column already exists</p>\n";
}

// Fix 4: Create sample BOM items for job 56746
echo "<h3>3. Creating sample BOM items for job 56746</h3>\n";

$bom_table = $wpdb->prefix . 'pi_bom_items';

// Check if BOM table exists
$bom_exists = $wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table;

if ($bom_exists) {
    // Check if any BOM items exist for job 56746
    $existing_bom = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$bom_table} WHERE project_id = %d",
        56746
    ));
    
    echo "<p>Existing BOM items for job 56746: {$existing_bom}</p>\n";
    
    if ($existing_bom == 0) {
        echo "<p>Creating sample BOM items...</p>\n";
        
        // Create sample BOM items
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
        "SELECT * FROM {$bom_table} WHERE project_id = %d ORDER BY id",
        56746
    ), ARRAY_A);
    
    echo "<h4>Current BOM Items for Job 56746:</h4>\n";
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Material Name</th><th>SKU</th><th>Category</th><th>Quantity</th><th>Unit</th><th>Cost</th><th>Status</th></tr>\n";
    
    foreach ($current_bom as $item) {
        echo "<tr>\n";
        echo "<td>{$item->id}</td>\n";
        echo "<td>{$item->material_name}</td>\n";
        echo "<td>{$item->material_sku}</td>\n";
        echo "<td>{$item->category}</td>\n";
        echo "<td>{$item->quantity}</td>\n";
        echo "<td>{$item->unit}</td>\n";
        echo "<td>{$item->unit_cost}</td>\n";
        echo "<td>{$item->status}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
} else {
    echo "<p>❌ BOM table does not exist: {$bom_table}</p>\n";
}

echo "<h3>✅ Database Schema Fix Complete</h3>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>1. Test the materials sync functionality</li>\n";
echo "<li>2. Check the materials dropdown in daily reports</li>\n";
echo "<li>3. Verify the 'Sync from BOM' button works</li>\n";
echo "</ul>\n";
?>
