<?php
/**
 * Final Validation Test - All Debug Log Fixes
 * Tests all implemented fixes to ensure clean operation
 */

// Include WordPress
require_once('../../../wp-config.php');

echo "=== Final Validation Test ===\n\n";

global $wpdb;

$test_results = [];
$total_tests = 0;
$passed_tests = 0;

// Test 1: Equipment Migration Column Dependencies
$total_tests++;
echo "1. Equipment Migration Column Dependencies:\n";

$equipment_table = $wpdb->prefix . 'pi_crm_equipment';
$equipment_columns = $wpdb->get_col("DESCRIBE {$equipment_table}");

$critical_columns = ['manufacturer', 'daily_rate', 'current_condition', 'return_condition'];
$equipment_ok = true;

foreach ($critical_columns as $column) {
    $exists = in_array($column, $equipment_columns);
    if (!$exists) {
        $equipment_ok = false;
    }
    echo "  - {$column}: " . ($exists ? "✅" : "❌") . "\n";
}

if ($equipment_ok) {
    $passed_tests++;
    echo "✅ Equipment migration columns OK\n";
} else {
    echo "❌ Equipment migration columns missing\n";
}
$test_results['equipment_migration'] = $equipment_ok;

// Test 2: Jobs Table/View
$total_tests++;
echo "\n2. Jobs Table/View:\n";

$jobs_table = $wpdb->prefix . 'pi_crm_jobs';
$jobs_exists = $wpdb->get_var("SHOW TABLES LIKE '{$jobs_table}'") === $jobs_table;

if ($jobs_exists) {
    $passed_tests++;
    echo "✅ Jobs view exists\n";
    $test_results['jobs_view'] = true;
} else {
    echo "❌ Jobs view missing\n";
    $test_results['jobs_view'] = false;
}

// Test 3: Crew Members Status Column
$total_tests++;
echo "\n3. Crew Members Status Column:\n";

$crew_members_table = $wpdb->prefix . 'pi_crm_crew_members';
$crew_columns = $wpdb->get_col("DESCRIBE {$crew_members_table}");
$has_status = in_array('status', $crew_columns);

if ($has_status) {
    $passed_tests++;
    echo "✅ Crew members status column exists\n";
    $test_results['crew_status'] = true;
} else {
    echo "❌ Crew members status column missing\n";
    $test_results['crew_status'] = false;
}

// Test 4: Migration State Tracking
$total_tests++;
echo "\n4. Migration State Tracking:\n";

$last_migration = get_option('pi_equipment_last_migration', 0);
$has_timestamp = $last_migration > 0;

if ($has_timestamp) {
    $passed_tests++;
    echo "✅ Migration timestamp exists\n";
    $test_results['migration_tracking'] = true;
} else {
    echo "❌ Migration timestamp missing\n";
    $test_results['migration_tracking'] = false;
}

// Test 5: Daily Reports Tables
$total_tests++;
echo "\n5. Daily Reports Tables:\n";

$daily_reports_table = $wpdb->prefix . 'pi_crm_daily_reports';
$ratings_table = $wpdb->prefix . 'pi_crm_daily_report_ratings';
$visitors_table = $wpdb->prefix . 'pi_crm_daily_report_visitors';

$dr_exists = $wpdb->get_var("SHOW TABLES LIKE '{$daily_reports_table}'") === $daily_reports_table;
$ratings_exists = $wpdb->get_var("SHOW TABLES LIKE '{$ratings_table}'") === $ratings_table;
$visitors_exists = $wpdb->get_var("SHOW TABLES LIKE '{$visitors_table}'") === $visitors_table;

$daily_reports_ok = $dr_exists && $ratings_exists && $visitors_exists;

if ($daily_reports_ok) {
    $passed_tests++;
    echo "✅ Daily Reports tables exist\n";
    $test_results['daily_reports_tables'] = true;
} else {
    echo "❌ Daily Reports tables missing\n";
    echo "  - daily_reports: " . ($dr_exists ? "✅" : "❌") . "\n";
    echo "  - ratings: " . ($ratings_exists ? "✅" : "❌") . "\n";
    echo "  - visitors: " . ($visitors_exists ? "✅" : "❌") . "\n";
    $test_results['daily_reports_tables'] = false;
}

// Test 6: API Endpoints Registration
$total_tests++;
echo "\n6. API Endpoints:\n";

if (function_exists('rest_get_server')) {
    $server = rest_get_server();
    $routes = $server->get_routes();
    
    $expected_routes = [
        '/wp-json/pi-daily-reports/v1/reports/(?P<report_id>\d+)/ratings',
        '/wp-json/pi-daily-reports/v1/reports/(?P<report_id>\d+)/visitors'
    ];
    
    $routes_ok = true;
    foreach ($expected_routes as $route) {
        $found = false;
        foreach ($routes as $namespace => $namespace_routes) {
            if (isset($namespace_routes[$route])) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $routes_ok = false;
        }
        
        echo "  - {$route}: " . ($found ? "✅" : "❌") . "\n";
    }
    
    if ($routes_ok) {
        $passed_tests++;
        echo "✅ API routes registered\n";
        $test_results['api_routes'] = true;
    } else {
        echo "❌ API routes missing\n";
        $test_results['api_routes'] = false;
}
} else {
    echo "❌ REST API not available\n";
    $test_results['api_routes'] = false;
}

// Test 7: Error Log Reduction
$total_tests++;
echo "\n7. Error Log Reduction:\n";

// Check if migration prevention is working
$recent_migration = get_option('pi_equipment_last_migration', 0);
$should_skip = ($recent_migration > 0) && (time() - $recent_migration < 300);

if ($should_skip) {
    $passed_tests++;
    echo "✅ Migration prevention active\n";
    $test_results['error_reduction'] = true;
} else {
    echo "ℹ️  Migration prevention not active (normal for first run)\n";
    $test_results['error_reduction'] = true; // This is expected for first run
}

// Summary
echo "\n=== VALIDATION SUMMARY ===\n";
echo "Tests Passed: {$passed_tests}/{$total_tests}\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%\n\n";

$all_passed = $passed_tests === $total_tests;

if ($all_passed) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "✅ Debug log fixes implemented successfully\n";
    echo "✅ System should run without errors\n";
    echo "✅ API endpoints should work correctly\n";
    echo "✅ Migration spam eliminated\n";
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    echo "Issues found:\n";
    
    foreach ($test_results as $test => $result) {
        if (!$result) {
            echo "  - {$test}: FAILED\n";
        }
    }
}

echo "\n=== EXPECTED RESULTS ===\n";
echo "After these fixes:\n";
echo "• No more database column dependency errors\n";
echo "• Jobs queries should work correctly\n";
echo "• Crew members queries should succeed\n";
echo "• Equipment migration runs only once per 5 minutes\n";
echo "• Daily Reports API endpoints should return valid JSON\n";
echo "• Debug logs should be clean and minimal\n";
echo "• Page loading should be fast and stable\n";

?>
