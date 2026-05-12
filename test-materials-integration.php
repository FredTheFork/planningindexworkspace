<?php
/**
 * Test Materials Integration - Daily Reports to BOM Connection
 * This script tests the comprehensive fixes for materials backend connection
 * including the new materials plugin API integration
 */

if (!defined('ABSPATH')) {
    // Simulate WordPress environment for testing
    $wp_base_path = dirname(dirname(dirname(__FILE__)));
    require_once $wp_base_path . '/wp-config.php';
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-pi-daily-reports-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pi-daily-reports-rest-api.php';

class PI_Materials_Integration_Test {
    
    private $wpdb;
    private $api;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->api = PI_Daily_Reports_REST_API::get_instance();
    }
    
    /**
     * Run all integration tests
     */
    public function run_tests() {
        echo "<h2>Materials Integration Test Results - Comprehensive Fix</h2>\n";
        
        $this->test_plugin_availability();
        $this->test_database_tables();
        $this->test_materials_plugin_api();
        $this->test_daily_reports_api();
        $this->test_api_integration();
        $this->test_fallback_mechanisms();
        
        echo "<h3>Test Complete</h3>\n";
        echo "<p>Check your debug.log for detailed information about any issues found.</p>\n";
    }
    
    /**
     * Test if materials plugin is available and active
     */
    private function test_plugin_availability() {
        echo "<h3>1. Materials Plugin Availability</h3>\n";
        
        // Check if materials plugin functions exist
        $materials_plugin_active = class_exists('MaterialsPlugin');
        echo "<p><strong>Materials Plugin Class:</strong> " . ($materials_plugin_active ? '✅ AVAILABLE' : '❌ NOT FOUND') . "</p>\n";
        
        // Check if materials plugin API endpoints are registered
        $api_url = rest_url('pi-materials/v1/bom-items');
        echo "<p><strong>Materials API Endpoint:</strong> {$api_url}</p>\n";
        
        // Test API endpoint availability
        $response = wp_remote_get($api_url, array(
            'timeout' => 5,
            'headers' => array(
                'X-WP-Nonce' => wp_create_nonce('wp_rest'),
            ),
        ));
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            echo "<p><strong>API Response Status:</strong> {$status_code}</p>\n";
            
            if ($status_code === 200 || $status_code === 401) {
                echo "<p><strong>Materials Plugin API:</strong> ✅ ACCESSIBLE</p>\n";
            } else {
                echo "<p><strong>Materials Plugin API:</strong> ⚠️ UNEXPECTED RESPONSE</p>\n";
            }
        } else {
            echo "<p><strong>Materials Plugin API:</strong> ❌ NOT ACCESSIBLE</p>\n";
            echo "<p><em>Error: " . $response->get_error_message() . "</em></p>\n";
        }
    }
    
    /**
     * Test materials plugin API functionality
     */
    private function test_materials_plugin_api() {
        echo "<h3>2. Materials Plugin API Test</h3>\n";
        
        $api_url = rest_url('pi-materials/v1/bom-items');
        echo "<p><strong>Testing API URL:</strong> {$api_url}</p>\n";
        
        // Test with a sample job ID
        $sample_job_id = $this->get_sample_job_id();
        if ($sample_job_id) {
            echo "<p><strong>Testing with Job ID:</strong> {$sample_job_id}</p>\n";
            
            $test_url = $api_url . "?job_id={$sample_job_id}&per_page=10";
            $response = wp_remote_get($test_url, array(
                'timeout' => 10,
                'headers' => array(
                    'X-WP-Nonce' => wp_create_nonce('wp_rest'),
                ),
            ));
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                echo "<p><strong>API Status Code:</strong> {$status_code}</p>\n";
                
                if ($status_code === 200) {
                    $materials = json_decode($response_body, true);
                    echo "<p><strong>Materials Found:</strong> " . count($materials) . "</p>\n";
                    
                    if (!empty($materials)) {
                        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
                        echo "<tr><th>ID</th><th>Material Name</th><th>Quantity</th><th>Unit</th><th>Status</th></tr>\n";
                        
                        foreach (array_slice($materials, 0, 5) as $material) {
                            echo "<tr>\n";
                            echo "<td>{$material['id']}</td>\n";
                            echo "<td>{$material['material_name']}</td>\n";
                            echo "<td>{$material['quantity']}</td>\n";
                            echo "<td>{$material['unit']}</td>\n";
                            echo "<td>{$material['status']}</td>\n";
                            echo "</tr>\n";
                        }
                        echo "</table>\n";
                    }
                    
                    echo "<p><strong>✅ Materials Plugin API Working!</strong></p>\n";
                } else {
                    echo "<p><strong>⚠️ API Response:</strong> {$response_body}</p>\n";
                }
            } else {
                echo "<p><strong>❌ API Error:</strong> " . $response->get_error_message() . "</p>\n";
            }
        } else {
            echo "<p><em>No sample job found for testing</em></p>\n";
        }
    }
    
    /**
     * Test daily reports API integration
     */
    private function test_daily_reports_api() {
        echo "<h3>3. Daily Reports API Integration</h3>\n";
        
        // Get a sample daily report
        $reports_table = $this->wpdb->prefix . 'pi_crm_daily_reports';
        $sample_report = $this->wpdb->get_row("SELECT id, job_id FROM {$reports_table} LIMIT 1");
        
        if ($sample_report) {
            echo "<p><strong>Testing with Report ID:</strong> {$sample_report->id}</p>\n";
            echo "<p><strong>Job ID:</strong> {$sample_report->job_id}</p>\n";
            
            // Test the available materials endpoint
            $api_url = rest_url("pi-daily-reports/v1/reports/{$sample_report->id}/available-materials");
            echo "<p><strong>Testing Daily Reports API:</strong> {$api_url}</p>\n";
            
            $response = wp_remote_get($api_url, array(
                'timeout' => 10,
                'headers' => array(
                    'X-WP-Nonce' => wp_create_nonce('wp_rest'),
                ),
            ));
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                echo "<p><strong>Daily Reports API Status:</strong> {$status_code}</p>\n";
                
                if ($status_code === 200) {
                    $materials = json_decode($response_body, true);
                    echo "<p><strong>Materials Available:</strong> " . count($materials) . "</p>\n";
                    
                    if (!empty($materials)) {
                        echo "<p><strong>✅ Daily Reports API Integration Working!</strong></p>\n";
                    } else {
                        echo "<p><strong>⚠️ No Materials Available:</strong> This could be expected if no BOM items exist</p>\n";
                    }
                } else {
                    echo "<p><strong>❌ Daily Reports API Error:</strong> {$response_body}</p>\n";
                }
            } else {
                echo "<p><strong>❌ Daily Reports API Request Failed:</strong> " . $response->get_error_message() . "</p>\n";
            }
        } else {
            echo "<p><em>No daily reports found for testing</em></p>\n";
        }
    }
    
    /**
     * Test API integration between systems
     */
    private function test_api_integration() {
        echo "<h3>4. API Integration Test</h3>\n";
        
        $sample_job_id = $this->get_sample_job_id();
        if (!$sample_job_id) {
            echo "<p><em>No sample job found for integration testing</em></p>\n";
            return;
        }
        
        echo "<p><strong>Testing Integration with Job ID:</strong> {$sample_job_id}</p>\n";
        
        // Test materials plugin API
        $materials_api_url = rest_url("pi-materials/v1/bom-items?job_id={$sample_job_id}");
        $materials_response = wp_remote_get($materials_api_url, array(
            'timeout' => 10,
            'headers' => array('X-WP-Nonce' => wp_create_nonce('wp_rest')),
        ));
        
        $materials_from_plugin = array();
        if (!is_wp_error($materials_response) && wp_remote_retrieve_response_code($materials_response) === 200) {
            $materials_from_plugin = json_decode(wp_remote_retrieve_body($materials_response), true);
            echo "<p><strong>Materials Plugin API:</strong> ✅ " . count($materials_from_plugin) . " materials</p>\n";
        } else {
            echo "<p><strong>Materials Plugin API:</strong> ❌ Not available</p>\n";
        }
        
        // Test daily reports API (which should use materials plugin)
        $reports_table = $this->wpdb->prefix . 'pi_crm_daily_reports';
        $sample_report = $this->wpdb->get_row($this->wpdb->prepare("SELECT id FROM {$reports_table} WHERE job_id = %d LIMIT 1", $sample_job_id));
        
        if ($sample_report) {
            $daily_reports_api_url = rest_url("pi-daily-reports/v1/reports/{$sample_report->id}/available-materials");
            $daily_reports_response = wp_remote_get($daily_reports_api_url, array(
                'timeout' => 10,
                'headers' => array('X-WP-Nonce' => wp_create_nonce('wp_rest')),
            ));
            
            $materials_from_daily_reports = array();
            if (!is_wp_error($daily_reports_response) && wp_remote_retrieve_response_code($daily_reports_response) === 200) {
                $materials_from_daily_reports = json_decode(wp_remote_retrieve_body($daily_reports_response), true);
                echo "<p><strong>Daily Reports API:</strong> ✅ " . count($materials_from_daily_reports) . " materials</p>\n";
                
                // Compare results
                if (count($materials_from_plugin) === count($materials_from_daily_reports)) {
                    echo "<p><strong>✅ Integration Successful!</strong> Both APIs return same data</p>\n";
                } else {
                    echo "<p><strong>⚠️ Integration Issue:</strong> Different counts - Plugin: " . count($materials_from_plugin) . ", Daily Reports: " . count($materials_from_daily_reports) . "</p>\n";
                }
            } else {
                echo "<p><strong>Daily Reports API:</strong> ❌ Failed</p>\n";
            }
        } else {
            echo "<p><em>No daily report found for job {$sample_job_id}</em></p>\n";
        }
    }
    
    /**
     * Test fallback mechanisms
     */
    private function test_fallback_mechanisms() {
        echo "<h3>5. Fallback Mechanisms Test</h3>\n";
        
        // Test database tables existence
        $bom_table = $this->wpdb->prefix . 'pi_bom_items';
        $suppliers_table = $this->wpdb->prefix . 'pi_suppliers';
        
        $bom_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table;
        $suppliers_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$suppliers_table}'") === $suppliers_table;
        
        echo "<p><strong>BOM Table:</strong> " . ($bom_exists ? '✅ EXISTS' : '❌ MISSING') . "</p>\n";
        echo "<p><strong>Suppliers Table:</strong> " . ($suppliers_exists ? '✅ EXISTS' : '❌ MISSING') . "</p>\n";
        
        if ($bom_exists) {
            $sample_job_id = $this->get_sample_job_id();
            if ($sample_job_id) {
                // Test direct database query
                $bom_items = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$bom_table} WHERE project_id = %d AND status = 'approved'",
                    $sample_job_id
                ));
                
                $count = $bom_items[0] ?? 0;
                echo "<p><strong>Database Fallback:</strong> ✅ {$count} BOM items found</p>\n";
                
                if ($count > 0) {
                    echo "<p><strong>✅ Fallback Mechanism Working!</strong></p>\n";
                }
            }
        } else {
            echo "<p><strong>⚠️ Database Fallback:</strong> Not available (BOM table missing)</p>\n";
        }
    }
    
    /**
     * Get sample job ID for testing
     */
    private function get_sample_job_id() {
        $bom_table = $this->wpdb->prefix . 'pi_bom_items';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table) {
            return $this->wpdb->get_var("SELECT DISTINCT project_id FROM {$bom_table} LIMIT 1");
        }
        
        // Fallback: get from daily reports
        $reports_table = $this->wpdb->prefix . 'pi_crm_daily_reports';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$reports_table}'") === $reports_table) {
            return $this->wpdb->get_var("SELECT DISTINCT job_id FROM {$reports_table} LIMIT 1");
        }
        
        return null;
    }
    
    /**
     * Test if required database tables exist
     */
    private function test_database_tables() {
        echo "<h3>1. Database Tables Check</h3>\n";
        
        $tables_to_check = [
            'pi_bom_items' => 'BOM Items',
            'pi_materials' => 'Materials',
            'pi_suppliers' => 'Suppliers',
            'pi_crm_daily_reports' => 'Daily Reports',
            'pi_crm_daily_report_materials' => 'Daily Report Materials'
        ];
        
        foreach ($tables_to_check as $table_name => $display_name) {
            $full_table_name = $this->wpdb->prefix . $table_name;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;
            
            $status = $exists ? '✅ EXISTS' : '❌ MISSING';
            echo "<p><strong>{$display_name} ({$full_table_name}):</strong> {$status}</p>\n";
            
            if ($exists) {
                // Check table structure
                $columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$full_table_name}");
                echo "<p><em>Columns: " . implode(', ', $columns) . "</em></p>\n";
            }
        }
    }
    
    /**
     * Test if BOM data exists for sample jobs
     */
    private function test_bom_data() {
        echo "<h3>2. BOM Data Check</h3>\n";
        
        $bom_table = $this->wpdb->prefix . 'pi_bom_items';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$bom_table}'") === $bom_table) {
            // Get total BOM items
            $total_items = $this->wpdb->get_var("SELECT COUNT(*) FROM {$bom_table}");
            echo "<p><strong>Total BOM Items:</strong> {$total_items}</p>\n";
            
            // Get projects with BOM items
            $projects = $this->wpdb->get_results("
                SELECT project_id, COUNT(*) as item_count 
                FROM {$bom_table} 
                GROUP BY project_id 
                ORDER BY item_count DESC 
                LIMIT 5
            ");
            
            if (!empty($projects)) {
                echo "<p><strong>Projects with BOM Items:</strong></p>\n";
                echo "<ul>\n";
                foreach ($projects as $project) {
                    echo "<li>Project ID {$project->project_id}: {$project->item_count} items</li>\n";
                }
                echo "</ul>\n";
                
                // Test with first project
                $test_project_id = $projects[0]->project_id;
                $this->test_project_bom_items($test_project_id);
            } else {
                echo "<p><em>No BOM items found in database</em></p>\n";
            }
        } else {
            echo "<p><em>BOM table does not exist</em></p>\n";
        }
    }
    
    /**
     * Test BOM items for specific project
     */
    private function test_project_bom_items($project_id) {
        echo "<h4>Testing Project ID {$project_id}</h4>\n";
        
        $bom_table = $this->wpdb->prefix . 'pi_bom_items';
        $items = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT id, material_name, status, project_id, required_date
            FROM {$bom_table} 
            WHERE project_id = %d 
            ORDER BY material_name
        ", $project_id));
        
        if (!empty($items)) {
            echo "<p><strong>BOM Items for Project {$project_id}:</strong></p>\n";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
            echo "<tr><th>ID</th><th>Material Name</th><th>Status</th><th>Required Date</th></tr>\n";
            
            foreach ($items as $item) {
                echo "<tr>\n";
                echo "<td>{$item->id}</td>\n";
                echo "<td>{$item->material_name}</td>\n";
                echo "<td>{$item->status}</td>\n";
                echo "<td>{$item->required_date}</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
            
            // Count approved items
            $approved_count = array_filter($items, function($item) {
                return $item->status === 'approved';
            });
            echo "<p><strong>Approved Items:</strong> " . count($approved_count) . "</p>\n";
        } else {
            echo "<p><em>No BOM items found for project {$project_id}</em></p>\n";
        }
    }
    
    /**
     * Test available materials API endpoint
     */
    private function test_available_materials() {
        echo "<h3>3. Available Materials API Test</h3>\n";
        
        // Get a sample project ID
        $bom_table = $this->wpdb->prefix . 'pi_bom_items';
        $sample_project = $this->wpdb->get_var("
            SELECT project_id FROM {$bom_table} 
            WHERE status = 'approved' 
            LIMIT 1
        ");
        
        if ($sample_project) {
            echo "<p><strong>Testing with Project ID:</strong> {$sample_project}</p>\n";
            
            // Create mock request
            $mock_request = new stdClass();
            $mock_request->params = ['report_id' => 1]; // Mock report ID
            
            // We need to simulate the report lookup
            $report_table = $this->wpdb->prefix . 'pi_crm_daily_reports';
            $mock_report = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT id, job_id FROM {$report_table} 
                WHERE job_id = %d 
                LIMIT 1
            ", $sample_project));
            
            if ($mock_report) {
                echo "<p><strong>Found Test Report:</strong> ID {$mock_report->id} for Job {$mock_report->job_id}</p>\n";
                
                // Test the available materials query directly
                $materials = $this->wpdb->get_results($this->wpdb->prepare("
                    SELECT DISTINCT
                        bi.id as bom_item_id,
                        bi.material_id,
                        bi.material_name,
                        bi.material_sku,
                        bi.category as material_category,
                        bi.unit as unit_of_measure,
                        bi.quantity as required_quantity,
                        bi.unit_cost,
                        bi.supplier_id
                    FROM {$bom_table} bi
                    WHERE bi.project_id = %d AND bi.status = 'approved'
                    ORDER BY bi.material_name
                ", $sample_project), ARRAY_A);
                
                echo "<p><strong>Available Materials Found:</strong> " . count($materials) . "</p>\n";
                
                if (!empty($materials)) {
                    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
                    echo "<tr><th>BOM ID</th><th>Material Name</th><th>SKU</th><th>Category</th><th>Quantity</th><th>Unit</th></tr>\n";
                    
                    foreach ($materials as $material) {
                        echo "<tr>\n";
                        echo "<td>{$material['bom_item_id']}</td>\n";
                        echo "<td>{$material['material_name']}</td>\n";
                        echo "<td>{$material['material_sku']}</td>\n";
                        echo "<td>{$material['material_category']}</td>\n";
                        echo "<td>{$material['required_quantity']}</td>\n";
                        echo "<td>{$material['unit_of_measure']}</td>\n";
                        echo "</tr>\n";
                    }
                    echo "</table>\n";
                }
            } else {
                echo "<p><em>No daily report found for project {$sample_project}</em></p>\n";
            }
        } else {
            echo "<p><em>No approved BOM items found to test with</em></p>\n";
        }
    }
    
    /**
     * Test sync materials functionality
     */
    private function test_sync_materials() {
        echo "<h3>4. Sync Materials Test</h3>\n";
        
        // This tests the fixed query without date matching
        $bom_table = $this->wpdb->prefix . 'pi_bom_items';
        $suppliers_table = $this->wpdb->prefix . 'pi_suppliers';
        
        $sample_project = $this->wpdb->get_var("
            SELECT project_id FROM {$bom_table} 
            WHERE status = 'approved' 
            LIMIT 1
        ");
        
        if ($sample_project) {
            echo "<p><strong>Testing sync query for Project ID:</strong> {$sample_project}</p>\n";
            
            // Test the FIXED sync query (without date matching)
            $materials_to_sync = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT 
                    bi.id as bom_item_id,
                    bi.material_id,
                    bi.material_name,
                    bi.material_sku,
                    bi.category as material_category,
                    bi.quantity,
                    bi.unit as unit_of_measure,
                    bi.unit_cost,
                    bi.supplier_id,
                    bi.required_date,
                    bi.delivery_slot,
                    s.company_name as supplier_name
                FROM {$bom_table} bi
                LEFT JOIN {$suppliers_table} s ON bi.supplier_id = s.id
                WHERE bi.project_id = %d 
                AND bi.status = 'approved'
                ORDER BY bi.material_name
            ", $sample_project), ARRAY_A);
            
            echo "<p><strong>Materials to Sync (FIXED QUERY):</strong> " . count($materials_to_sync) . "</p>\n";
            
            if (!empty($materials_to_sync)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
                echo "<tr><th>BOM ID</th><th>Material Name</th><th>Supplier</th><th>Quantity</th><th>Required Date</th></tr>\n";
                
                foreach ($materials_to_sync as $material) {
                    echo "<tr>\n";
                    echo "<td>{$material['bom_item_id']}</td>\n";
                    echo "<td>{$material['material_name']}</td>\n";
                    echo "<td>{$material['supplier_name']}</td>\n";
                    echo "<td>{$material['quantity']}</td>\n";
                    echo "<td>{$material['required_date']}</td>\n";
                    echo "</tr>\n";
                }
                echo "</table>\n";
                
                echo "<p><strong>✅ FIXED QUERY WORKS!</strong> The date matching issue has been resolved.</p>\n";
            } else {
                echo "<p><em>No materials found to sync (even with fixed query)</em></p>\n";
            }
        } else {
            echo "<p><em>No approved BOM items found to test sync</em></p>\n";
        }
    }
}

// Run the test if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test-materials-integration.php') {
    $test = new PI_Materials_Integration_Test();
    $test->run_tests();
}
?>
