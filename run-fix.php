<?php
/**
 * Run Database Fix - Execute database schema fixes
 */

// Include WordPress
$wp_base_path = dirname(dirname(dirname(__FILE__)));
require_once $wp_base_path . '/wp-config.php';

global $wpdb;

echo "<h2>Running Database Schema Fix</h2>\n";

// Fix 1: Add bom_item_id column
$materials_table = $wpdb->prefix . 'pi_crm_daily_report_materials';
$bom_column_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = %s 
     AND TABLE_NAME = %s 
     AND COLUMN_NAME = %s",
    DB_NAME, $materials_table, 'bom_item_id'
));

if (!$bom_column_exists) {
    echo "<p>Adding bom_item_id column...</p>\n";
    $wpdb->query("ALTER TABLE {$materials_table} ADD COLUMN bom_item_id bigint(20) unsigned DEFAULT NULL AFTER material_id");
    echo "<p>✅ bom_item_id column added</p>\n";
} else {
    echo "<p>✅ bom_item_id column already exists</p>\n";
}

// Fix 2: Add material_id column
$material_column_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = %s 
     AND TABLE_NAME = %s 
     AND COLUMN_NAME = %s",
    DB_NAME, $materials_table, 'material_id'
));

if (!$material_column_exists) {
    echo "<p>Adding material_id column...</p>\n";
    $wpdb->query("ALTER TABLE {$materials_table} ADD COLUMN material_id bigint(20) unsigned DEFAULT NULL AFTER bom_item_id");
    echo "<p>✅ material_id column added</p>\n";
} else {
    echo "<p>✅ material_id column already exists</p>\n";
}

// Fix 3: Add last_auto_save column to daily reports table
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
    $wpdb->query("ALTER TABLE {$daily_reports_table} ADD COLUMN last_auto_save datetime DEFAULT NULL");
    echo "<p>✅ last_auto_save column added</p>\n";
} else {
    echo "<p>✅ last_auto_save column already exists</p>\n";
}

// Fix 4: Create sample BOM items for job 56746
echo "<h3>Creating sample BOM items for job 56746</h3>\n";
$bom_table = $wpdb->prefix . 'pi_bom_items';
$bom_exists = $wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table;

if ($bom_exists) {
    $existing_bom = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$bom_table} WHERE project_id = %d",
        56746
    ));
    
    echo "<p>Existing BOM items for job 56746: {$existing_bom}</p>\n";
    
    if ($existing_bom == 0) {
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
} else {
    echo "<p>❌ BOM table does not exist: {$bom_table}</p>\n";
}

echo "<h3>✅ Database Schema Fix Complete</h3>\n";
echo "<p><strong>Test the materials integration now:</strong></p>\n";
echo "<ul>\n";
echo "<li>1. Open daily report for job 56746</li>\n";
echo "<li>2. Click 'Add Material' button</li>\n";
echo "<li>3. Check if materials appear in dropdown</li>\n";
echo "<li>4. Try 'Sync from BOM' button</li>\n";
echo "</ul>\n";
?>
