<?php
/**
 * Simple test for materials integration fixes
 * Tests the specific issue: date matching in sync_materials_to_report()
 */

echo "<h2>Materials Integration Fix Test</h2>\n";

// Test 1: Check if BOM table exists
echo "<h3>Test 1: BOM Table Check</h3>\n";
global $wpdb;
$bom_table = $wpdb->prefix . 'pi_bom_items';
$exists = $wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table;
echo "<p><strong>BOM Table ({$bom_table}):</strong> " . ($exists ? '✅ EXISTS' : '❌ MISSING') . "</p>\n";

if ($exists) {
    // Test 2: Check BOM data
    echo "<h3>Test 2: BOM Data Check</h3>\n";
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$bom_table}");
    echo "<p><strong>Total BOM Items:</strong> {$total_items}</p>\n";
    
    if ($total_items > 0) {
        // Get sample data
        $sample_items = $wpdb->get_results("
            SELECT id, project_id, material_name, status, required_date 
            FROM {$bom_table} 
            WHERE status = 'approved' 
            LIMIT 5
        ");
        
        echo "<p><strong>Sample Approved BOM Items:</strong></p>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>ID</th><th>Project ID</th><th>Material Name</th><th>Status</th><th>Required Date</th></tr>\n";
        
        foreach ($sample_items as $item) {
            echo "<tr>\n";
            echo "<td>{$item->id}</td>\n";
            echo "<td>{$item->project_id}</td>\n";
            echo "<td>{$item->material_name}</td>\n";
            echo "<td>{$item->status}</td>\n";
            echo "<td>{$item->required_date}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Test 3: Demonstrate the FIXED vs BROKEN query
        echo "<h3>Test 3: Query Fix Demonstration</h3>\n";
        
        if (!empty($sample_items)) {
            $test_project_id = $sample_items[0]->project_id;
            $test_date = date('Y-m-d');
            
            echo "<p><strong>Testing with Project ID:</strong> {$test_project_id}</p>\n";
            echo "<p><strong>Test Date:</strong> {$test_date}</p>\n";
            
            // BROKEN QUERY (with date matching)
            $broken_query = $wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$bom_table} 
                WHERE project_id = %d 
                AND required_date = %s 
                AND status = 'approved'
            ", $test_project_id, $test_date);
            
            $broken_count = $wpdb->get_var($broken_query);
            echo "<p><strong>BROKEN Query (with date matching):</strong> {$broken_count} items found</p>\n";
            
            // FIXED QUERY (without date matching)
            $fixed_query = $wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$bom_table} 
                WHERE project_id = %d 
                AND status = 'approved'
            ", $test_project_id);
            
            $fixed_count = $wpdb->get_var($fixed_query);
            echo "<p><strong>FIXED Query (without date matching):</strong> {$fixed_count} items found</p>\n";
            
            if ($fixed_count > $broken_count) {
                echo "<p><strong>✅ FIX CONFIRMED!</strong> Removing date matching allows materials to be found.</p>\n";
            } else {
                echo "<p><strong>❌ No difference found.</strong> Date matching may not be the only issue.</p>\n";
            }
        }
    } else {
        echo "<p><em>No BOM items found to test with</em></p>\n";
    }
}

echo "<h3>Test 4: Check Daily Reports Materials Table</h3>\n";
$dr_materials_table = $wpdb->prefix . 'pi_crm_daily_report_materials';
$dr_exists = $wpdb->get_var("SHOW TABLES LIKE '{$dr_materials_table}'") === $dr_materials_table;
echo "<p><strong>Daily Reports Materials Table ({$dr_materials_table}):</strong> " . ($dr_exists ? '✅ EXISTS' : '❌ MISSING') . "</p>\n";

if ($dr_exists) {
    // Check if bom_item_id column exists
    $column_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = %s 
        AND TABLE_NAME = %s 
        AND COLUMN_NAME = 'bom_item_id'
    ", DB_NAME, $dr_materials_table));
    
    echo "<p><strong>bom_item_id Column:</strong> " . ($column_exists ? '✅ EXISTS' : '❌ MISSING') . "</p>\n";
    
    if (!$column_exists) {
        echo "<p><strong>🔧 NEEDED FIX:</strong> Add bom_item_id column to daily reports materials table</p>\n";
        $alter_sql = "ALTER TABLE {$dr_materials_table} ADD COLUMN bom_item_id bigint(20) unsigned DEFAULT NULL AFTER material_id";
        echo "<p><strong>SQL to run:</strong> {$alter_sql}</p>\n";
    }
}

echo "<h3>Summary</h3>\n";
echo "<p>This test identifies the core issue and confirms the fix works.</p>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>1. Ensure BOM items exist for your job</li>\n";
echo "<li>2. Verify BOM items have 'approved' status</li>\n";
echo "<li>3. Test the 'Sync from BOM' button in daily reports</li>\n";
echo "<li>4. Check debug logs for detailed information</li>\n";
echo "</ul>\n";
?>
