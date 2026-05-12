<?php
/**
 * Planning Index CRM - REST API Endpoints
 * Comprehensive API for construction CRM features
 */

if (!defined('ABSPATH')) exit;

class PI_CRM_REST_API {
    
    private static $instance = null;
    private $namespace = 'pi-crm/v1';
    private $wpdb;
    private $tables;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = $this->get_table_names();
    }
    
    public function register_routes() {
        add_action('rest_api_init', array($this, 'init_routes'));
    }
    
    public function init_routes() {
        // Communications endpoints
        $this->register_communications_routes();
        
        // Documents endpoints
        $this->register_documents_routes();
        
        // Timesheets endpoints
        $this->register_timesheets_routes();
        
        // Safety endpoints
        $this->register_safety_routes();
        
        // Quality endpoints
        $this->register_quality_routes();
        
        // Change Orders endpoints
        $this->register_change_orders_routes();
        
        // Invoicing endpoints
        $this->register_invoicing_routes();
        
        // Daily Reports endpoints
        $this->register_daily_reports_routes();
        
        // Equipment endpoints
        $this->register_equipment_routes();
        
        // Subcontractors endpoints
        $this->register_subcontractors_routes();
        
        // Client endpoints
        $this->register_client_routes();
        
        // Photos endpoints
        $this->register_photos_routes();
        
        // RFI & Submittals endpoints
        $this->register_rfi_submittal_routes();
        
        // Site Location / Map endpoints
        $this->register_site_location_routes();
        
        // Team endpoints
        $this->register_team_routes();
        
        // Enhanced Team & Timesheets endpoints
        $this->register_employees_routes();
        $this->register_crews_routes();
        $this->register_clock_routes();
        $this->register_approvals_routes();
        $this->register_cost_codes_routes();
        $this->register_team_dashboard_routes();
        
        // Weather endpoints
        $this->register_weather_routes();
        
        // Dashboard / Summary endpoints
        $this->register_dashboard_routes();
    }
    
    private function register_communications_routes() {
        // Get communications for a job
        register_rest_route($this->namespace, '/communications', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_communications'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array('required' => true, 'validate_callback' => function($param) { return is_numeric($param); }),
                    'type' => array('required' => false),
                    'limit' => array('default' => 50),
                    'offset' => array('default' => 0)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_communication'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Individual communication
        register_rest_route($this->namespace, '/communications/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_communication'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_communication'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_communication'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Send email
        register_rest_route($this->namespace, '/communications/send', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'send_email'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Get email templates
        register_rest_route($this->namespace, '/email-templates', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_email_templates'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_documents_routes() {
        register_rest_route($this->namespace, '/documents', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_documents'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array('required' => true),
                    'category' => array('required' => false),
                    'search' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'upload_document'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array(
                        'required' => true,
                        'type' => 'integer',
                        'validate_callback' => function($param) { return is_numeric($param); }
                    ),
                    'title' => array('required' => false, 'type' => 'string'),
                    'description' => array('required' => false, 'type' => 'string'),
                    'category' => array('required' => false, 'type' => 'string', 'default' => 'general'),
                    'amount' => array('required' => false, 'type' => 'number')
                )
            )
        ));
        
        register_rest_route($this->namespace, '/documents/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_document'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_document'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Document versions
        register_rest_route($this->namespace, '/documents/(?P<id>\d+)/versions', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_document_versions'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    private function register_timesheets_routes() {
        register_rest_route($this->namespace, '/timesheets', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_timesheets'),
                'permission_callback' => array($this, 'check_general_permission'),
                'args' => array(
                    'job_id' => array('required' => false),
                    'worker_id' => array('required' => false),
                    'employee_id' => array('required' => false),
                    'start_date' => array('required' => false),
                    'end_date' => array('required' => false),
                    'status' => array('required' => false),
                    'is_global' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_timesheet'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/timesheets/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_timesheet'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_timesheet'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Legacy clock endpoint (job-specific - clock routes are in register_clock_routes)
        register_rest_route($this->namespace, '/timesheets/clock', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'clock_in_out'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Timesheet summary
        register_rest_route($this->namespace, '/timesheets/summary', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_timesheet_summary'),
            'permission_callback' => array($this, 'check_general_permission'),
            'args' => array(
                'job_id' => array('required' => false)
            )
        ));
    }
    
    private function register_safety_routes() {
        // Inspections
        register_rest_route($this->namespace, '/safety/inspections', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_safety_inspections'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_safety_inspection'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/safety/inspections/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_safety_inspection'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_safety_inspection'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Incidents
        register_rest_route($this->namespace, '/safety/incidents', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_safety_incidents'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_safety_incident'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Checklist templates
        register_rest_route($this->namespace, '/safety/checklist-templates', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_checklist_templates'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_quality_routes() {
        // Snags
        register_rest_route($this->namespace, '/quality/snags', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_quality_snags'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_quality_snag'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/quality/snags/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_quality_snag'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_quality_snag'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Sign-offs
        register_rest_route($this->namespace, '/quality/signoffs', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_quality_signoffs'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_quality_signoff'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    private function register_change_orders_routes() {
        register_rest_route($this->namespace, '/change-orders', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_change_orders'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_change_order'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/change-orders/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_change_order'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_change_order'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Approve change order
        register_rest_route($this->namespace, '/change-orders/(?P<id>\d+)/approve', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'approve_change_order'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    private function register_invoicing_routes() {
        register_rest_route($this->namespace, '/invoices', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_invoices'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_invoice'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/invoices/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_invoice'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_invoice'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Record payment
        register_rest_route($this->namespace, '/invoices/(?P<id>\d+)/payment', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'record_payment'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Invoice summary
        register_rest_route($this->namespace, '/invoices/summary', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_invoice_summary'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    private function register_daily_reports_routes() {
        register_rest_route($this->namespace, '/daily-reports', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_daily_reports'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_daily_report'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/daily-reports/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_daily_report'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_daily_report'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    private function register_equipment_routes() {
        register_rest_route($this->namespace, '/equipment', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_equipment'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Equipment usage
        register_rest_route($this->namespace, '/equipment/(?P<id>\d+)/usage', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_equipment_usage'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'log_equipment_usage'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    private function register_subcontractors_routes() {
        register_rest_route($this->namespace, '/subcontractors', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_job_subcontractors'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_job_subcontractor'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/subcontractors/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_job_subcontractor'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_job_subcontractor'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
    }
    
    private function register_client_routes() {
        register_rest_route($this->namespace, '/clients/(?P<job_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_client_details'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_client_details'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Client communications
        register_rest_route($this->namespace, '/clients/(?P<job_id>\d+)/communications', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_client_communications'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Client jobs history
        register_rest_route($this->namespace, '/clients/(?P<email>.+)/jobs', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_client_job_history'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_photos_routes() {
        register_rest_route($this->namespace, '/photos', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_photos'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array('required' => true),
                    'category' => array('required' => false),
                    'photo_type' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'upload_photo'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array(
                        'required' => true,
                        'type' => 'integer',
                        'validate_callback' => function($param) { return is_numeric($param); }
                    ),
                    'title' => array('required' => false, 'type' => 'string'),
                    'description' => array('required' => false, 'type' => 'string'),
                    'category' => array('required' => false, 'type' => 'string', 'default' => 'site'),
                    'photo_type' => array('required' => false, 'type' => 'string', 'default' => 'general')
                )
            )
        ));
        
        register_rest_route($this->namespace, '/photos/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_photo'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_photo'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Photo gallery
        register_rest_route($this->namespace, '/photos/gallery', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_photo_gallery'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    private function register_rfi_submittal_routes() {
        // RFIs - List and Create
        register_rest_route($this->namespace, '/rfi', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_rfi'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array('required' => true, 'validate_callback' => function($p) { return is_numeric($p); }),
                    'status' => array('required' => false),
                    'priority' => array('required' => false),
                    'search' => array('required' => false),
                    'sort_by' => array('required' => false),
                    'sort_order' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_rfi'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Single RFI - Get, Update, Delete
        register_rest_route($this->namespace, '/rfi/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_single_rfi'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_rfi'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_rfi'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // RFI Actions
        register_rest_route($this->namespace, '/rfi/(?P<id>\d+)/respond', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'respond_rfi'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/rfi/(?P<id>\d+)/close', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'close_rfi'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/rfi/(?P<id>\d+)/comment', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'add_rfi_comment'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/rfi/(?P<id>\d+)/link-task', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'link_rfi_to_task'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // RFI Reports
        register_rest_route($this->namespace, '/rfi/reports/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_rfi_dashboard_report'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/rfi/export', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'export_rfi_log'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Submittals - List and Create
        register_rest_route($this->namespace, '/submittals', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_submittals'),
                'permission_callback' => array($this, 'check_job_permission'),
                'args' => array(
                    'job_id' => array('required' => true, 'validate_callback' => function($p) { return is_numeric($p); }),
                    'status' => array('required' => false),
                    'type' => array('required' => false),
                    'group_by' => array('required' => false),
                    'search' => array('required' => false),
                    'sort_by' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_submittal'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Single Submittal - Get, Update, Delete
        register_rest_route($this->namespace, '/submittals/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_single_submittal'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_submittal'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_submittal'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Submittal Actions
        register_rest_route($this->namespace, '/submittals/(?P<id>\d+)/review', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'review_submittal'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/submittals/(?P<id>\d+)/resubmit', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'resubmit_submittal'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/submittals/(?P<id>\d+)/close', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'close_submittal'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Submittal Reports
        register_rest_route($this->namespace, '/submittals/reports/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_submittal_dashboard_report'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/submittals/export', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'export_submittal_log'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // RFI/Submittals Combined Dashboard
        register_rest_route($this->namespace, '/rfi-submittals/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_rfi_submittals_dashboard'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        // Bulk Actions
        register_rest_route($this->namespace, '/rfi/bulk-action', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'bulk_rfi_action'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        
        register_rest_route($this->namespace, '/submittals/bulk-action', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'bulk_submittal_action'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    private function register_site_location_routes() {
        register_rest_route($this->namespace, '/sites/(?P<job_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_site_location'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_site_location'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // All sites for map
        register_rest_route($this->namespace, '/sites/all', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_all_sites'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        // Geocode address
        register_rest_route($this->namespace, '/sites/geocode', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'geocode_address'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_team_routes() {
        register_rest_route($this->namespace, '/team', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_team_assignments'),
                'permission_callback' => array($this, 'check_general_permission'),
                'args' => array(
                    'job_id' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'assign_team_member'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Available workers for global team management
        register_rest_route($this->namespace, '/team/available', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_available_workers'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/team/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_team_assignment'),
                'permission_callback' => array($this, 'check_job_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'remove_team_member'),
                'permission_callback' => array($this, 'check_job_permission')
            )
        ));
        
        // Worker certifications
        register_rest_route($this->namespace, '/team/certifications', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_certifications'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'add_certification'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
    }
    
    private function register_employees_routes() {
        // System init - ensure tables exist
        register_rest_route($this->namespace, '/team-system/init', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'init_team_system'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        // Employees CRUD
        register_rest_route($this->namespace, '/employees', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_employees'),
                'permission_callback' => array($this, 'check_general_permission'),
                'args' => array(
                    'job_id' => array('required' => false),
                    'status' => array('required' => false),
                    'role' => array('required' => false),
                    'trade' => array('required' => false),
                    'search' => array('required' => false)
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_employee'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/employees/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_employee'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_employee'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_employee'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        // Employee stats
        register_rest_route($this->namespace, '/employees/(?P<id>\d+)/stats', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_employee_stats'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        // Bulk employee actions
        register_rest_route($this->namespace, '/employees/bulk', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'bulk_employee_action'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        // Team members - assign existing employees to jobs
        register_rest_route($this->namespace, '/team-members', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'assign_team_member'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        // Jobs list for dropdowns
        register_rest_route($this->namespace, '/jobs', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_jobs_list'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_crews_routes() {
        // Crews CRUD
        register_rest_route($this->namespace, '/crews', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_crews'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_crew'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/crews/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_crew'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_crew'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_crew'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        // Crew members
        register_rest_route($this->namespace, '/crews/(?P<id>\d+)/members', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_crew_members'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'add_crew_member'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/crews/(?P<crew_id>\d+)/members/(?P<member_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_crew_member'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'remove_crew_member'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
    }
    
    private function register_clock_routes() {
        // Clock in/out
        register_rest_route($this->namespace, '/clock/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_clock_status'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/clock/in', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'clock_in'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/clock/out', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'clock_out'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/clock/break/start', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'start_break'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/clock/break/end', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'end_break'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        // Who's on site
        register_rest_route($this->namespace, '/clock/on-site', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_on_site_workers'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_approvals_routes() {
        // Timesheet approvals (global)
        register_rest_route($this->namespace, '/approvals', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_pending_approvals'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/approvals/(?P<id>\d+)/approve', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'approve_timesheet'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/approvals/(?P<id>\d+)/reject', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'reject_timesheet'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/approvals/bulk', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'bulk_approval_action'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_cost_codes_routes() {
        register_rest_route($this->namespace, '/cost-codes', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_cost_codes'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_cost_code'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/cost-codes/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_cost_code'),
                'permission_callback' => array($this, 'check_general_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_cost_code'),
                'permission_callback' => array($this, 'check_general_permission')
            )
        ));
    }
    
    private function register_team_dashboard_routes() {
        // Team dashboard endpoints (global)
        register_rest_route($this->namespace, '/team-dashboard/stats', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_team_dashboard_stats'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/team-dashboard/weekly-hours', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_weekly_hours_chart'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/team-dashboard/activity', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_team_activity'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        // Export timesheets
        register_rest_route($this->namespace, '/team-dashboard/export', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'export_timesheets'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        // Additional dashboard chart endpoints
        register_rest_route($this->namespace, '/team-dashboard/trade-distribution', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_trade_distribution'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/team-dashboard/attendance-trends', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_attendance_trends'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/team-dashboard/labor-cost', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_labor_cost'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
        
        register_rest_route($this->namespace, '/team-dashboard/top-performers', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_top_performers'),
            'permission_callback' => array($this, 'check_general_permission')
        ));
    }
    
    private function register_weather_routes() {
        register_rest_route($this->namespace, '/weather', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_weather'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array(
                'job_id' => array('required' => true),
                'days' => array('default' => 7)
            )
        ));
        
        // Fetch and store weather
        register_rest_route($this->namespace, '/weather/fetch', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'fetch_weather_data'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    private function register_dashboard_routes() {
        // Job summary
        register_rest_route($this->namespace, '/dashboard/summary', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_job_summary'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array('job_id' => array('required' => true))
        ));
        
        // Timeline / Activity feed
        register_rest_route($this->namespace, '/dashboard/timeline', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_timeline'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array(
                'job_id' => array('required' => true),
                'limit' => array('default' => 50)
            )
        ));
        
        // KPIs
        register_rest_route($this->namespace, '/dashboard/kpis', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_kpis'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array('job_id' => array('required' => true))
        ));
    }
    
    // Permission callbacks
    public function check_job_permission($request) {
        error_log('[PI_CRM_API] Permission check - Method: ' . $request->get_method() . ', URI: ' . $request->get_route());
        
        // Try to get job_id from various sources
        $job_id = $request->get_param('job_id');
        error_log('[PI_CRM_API] job_id from param: ' . ($job_id ?: 'null'));
        
        // For multipart/form-data requests, params might not be parsed yet
        // Try getting from file params or $_POST as fallback
        if (!$job_id) {
            $job_id = $request->get_param('id');
            if ($job_id) error_log('[PI_CRM_API] job_id from id param: ' . $job_id);
        }
        if (!$job_id && !empty($_POST['job_id'])) {
            $job_id = intval($_POST['job_id']);
            error_log('[PI_CRM_API] job_id from POST: ' . $job_id);
        }
        
        // For JSON POST/PUT requests, try to get job_id from JSON body
        if (!$job_id && in_array($request->get_method(), ['POST', 'PUT', 'PATCH'])) {
            $body = $request->get_body();
            if ($body) {
                $json = json_decode($body, true);
                if (isset($json['job_id'])) {
                    $job_id = intval($json['job_id']);
                    error_log('[PI_CRM_API] job_id from JSON body: ' . $job_id);
                } else {
                    error_log('[PI_CRM_API] No job_id in JSON body. Body keys: ' . implode(', ', array_keys($json ?: [])));
                }
            } else {
                error_log('[PI_CRM_API] Empty request body');
            }
        }
        
        // If still no job_id, allow the request to proceed and let the callback validate
        // This is necessary for multipart uploads where the permission check runs before body parsing
        if (!$job_id) {
            $method = $request->get_method();
            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                $can_edit = current_user_can('edit_posts') || current_user_can('manage_options');
                error_log('[PI_CRM_API] No job_id found, using general permission: ' . ($can_edit ? 'allowed' : 'denied'));
                return $can_edit;
            }
        }
        
        if (!function_exists('pi_jobs_user_owns_job')) {
            error_log('[PI_CRM_API] pi_jobs_user_owns_job not found, using edit_posts capability');
            return current_user_can('edit_posts');
        }
        
        $owns_job = pi_jobs_user_owns_job($job_id);
        $is_admin = current_user_can('manage_options');
        error_log('[PI_CRM_API] Permission result - owns_job: ' . ($owns_job ? 'yes' : 'no') . ', is_admin: ' . ($is_admin ? 'yes' : 'no'));
        
        return $owns_job || $is_admin;
    }
    
    public function check_general_permission() {
        return current_user_can('edit_posts') || current_user_can('manage_options');
    }
    
    // Helper: Get table names
    private function get_table_names() {
        $prefix = $this->wpdb->prefix . 'pi_crm_';
        return array(
            'communications' => $prefix . 'communications',
            'documents' => $prefix . 'documents',
            'timesheets' => $prefix . 'timesheets',
            'safety_inspections' => $prefix . 'safety_inspections',
            'safety_incidents' => $prefix . 'safety_incidents',
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
            'rfi_activity' => $prefix . 'rfi_activity',
            'rfi_comments' => $prefix . 'rfi_comments',
            'submittals' => $prefix . 'submittals',
            'submittal_revisions' => $prefix . 'submittal_revisions',
            'submittal_reviews' => $prefix . 'submittal_reviews',
            'submittal_activity' => $prefix . 'submittal_activity',
            'notifications' => $prefix . 'notifications',
            'site_locations' => $prefix . 'site_locations',
            'team_assignments' => $prefix . 'team_assignments',
            'certifications' => $prefix . 'certifications',
            'weather_data' => $prefix . 'weather_data',
            'employees' => $prefix . 'employees',
            'crews' => $prefix . 'crews',
            'crew_members' => $prefix . 'crew_members',
            'clock_status' => $prefix . 'clock_status',
            'timesheet_approvals' => $prefix . 'timesheet_approvals',
            'cost_codes' => $prefix . 'cost_codes'
        );
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Communications
    // ============================================
    
    public function get_communications($request) {
        $job_id = intval($request->get_param('job_id'));
        $type = $request->get_param('type');
        $limit = intval($request->get_param('limit'));
        $offset = intval($request->get_param('offset'));
        
        $table = $this->tables['communications'];
        $sql = "SELECT * FROM {$table} WHERE job_id = %d";
        $params = array($job_id);
        
        if ($type) {
            $sql .= " AND type = %s";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_communication($request) {
        $data = $request->get_json_params();
        $table = $this->tables['communications'];
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'type' => sanitize_text_field($data['type']),
            'direction' => sanitize_text_field($data['direction'] ?? 'outbound'),
            'subject' => sanitize_text_field($data['subject'] ?? ''),
            'content' => wp_kses_post($data['content'] ?? ''),
            'recipient_name' => sanitize_text_field($data['recipient_name'] ?? ''),
            'recipient_email' => sanitize_email($data['recipient_email'] ?? ''),
            'status' => 'draft',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create communication', array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    public function send_email($request) {
        $data = $request->get_json_params();
        
        $to = sanitize_email($data['to']);
        $subject = sanitize_text_field($data['subject']);
        $message = wp_kses_post($data['message']);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        if (!empty($data['from_name']) && !empty($data['from_email'])) {
            $headers[] = 'From: ' . sanitize_text_field($data['from_name']) . ' <' . sanitize_email($data['from_email']) . '>';
        }
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            // Save to communications log
            $this->create_communication($request);
        }
        
        return new WP_REST_Response(array('sent' => $sent), 200);
    }
    
    public function get_email_templates() {
        $templates = array(
            array(
                'id' => 'project_start',
                'name' => 'Project Start',
                'subject' => 'Your Project is Starting - {{PROJECT_NAME}}',
                'body' => 'Dear {{CLIENT_NAME}},<br><br>We are pleased to inform you that work on your project at {{SITE_ADDRESS}} is scheduled to begin on {{START_DATE}}.<br><br>Your project manager is {{PM_NAME}} and can be reached at {{PM_PHONE}} or {{PM_EMAIL}}.<br><br>Best regards,<br>{{COMPANY_NAME}}'
            ),
            array(
                'id' => 'progress_update',
                'name' => 'Progress Update',
                'subject' => 'Weekly Progress Update - {{PROJECT_NAME}}',
                'body' => 'Dear {{CLIENT_NAME}},<br><br>Here is your weekly progress update for {{PROJECT_NAME}}:<br><br>Current Status: {{STATUS}}<br>Completion: {{PROGRESS}}%<br><br>Work Completed This Week:<br>{{WORK_COMPLETED}}<br><br>Planned for Next Week:<br>{{WORK_PLANNED}}<br><br>Best regards,<br>{{PM_NAME}}'
            ),
            array(
                'id' => 'change_order',
                'name' => 'Change Order Request',
                'subject' => 'Change Order Request - {{CO_NUMBER}}',
                'body' => 'Dear {{CLIENT_NAME}},<br><br>We have identified a need for a change to the original scope of work. Please review the following:<br><br>Change Description: {{DESCRIPTION}}<br>Cost Impact: £{{COST_IMPACT}}<br>Schedule Impact: {{SCHEDULE_IMPACT}} days<br><br>Please approve or discuss this change at your earliest convenience.<br><br>Best regards,<br>{{PM_NAME}}'
            ),
            array(
                'id' => 'invoice',
                'name' => 'Invoice Notification',
                'subject' => 'Invoice {{INVOICE_NUMBER}} - {{PROJECT_NAME}}',
                'body' => 'Dear {{CLIENT_NAME}},<br><br>Please find attached invoice {{INVOICE_NUMBER}} for {{PROJECT_NAME}}.<br><br>Amount Due: £{{AMOUNT}}<br>Due Date: {{DUE_DATE}}<br><br>Payment can be made by bank transfer to:<br>Account: {{BANK_ACCOUNT}}<br>Sort Code: {{SORT_CODE}}<br><br>Thank you for your business.<br><br>Best regards,<br>{{COMPANY_NAME}}'
            ),
            array(
                'id' => 'project_complete',
                'name' => 'Project Completion',
                'subject' => 'Project Complete - {{PROJECT_NAME}}',
                'body' => 'Dear {{CLIENT_NAME}},<br><br>We are delighted to inform you that your project at {{SITE_ADDRESS}} has been completed successfully.<br><br>Final handover will take place on {{HANDOVER_DATE}}. Please ensure all snagging items have been addressed to your satisfaction.<br><br>All documentation, including warranties and O&M manuals, will be provided at handover.<br><br>It has been a pleasure working with you.<br><br>Best regards,<br>{{PM_NAME}}<br>{{COMPANY_NAME}}'
            )
        );
        
        return new WP_REST_Response($templates, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Documents
    // ============================================
    
    public function get_documents($request) {
        $job_id = intval($request->get_param('job_id'));
        $category = $request->get_param('category');
        $search = $request->get_param('search');
        
        $table = $this->tables['documents'];
        $sql = "SELECT * FROM {$table} WHERE job_id = %d AND is_latest = 1";
        $params = array($job_id);
        
        if ($category) {
            $sql .= " AND category = %s";
            $params[] = sanitize_text_field($category);
        }
        
        if ($search) {
            $sql .= " AND (title LIKE %s OR description LIKE %s)";
            $params[] = '%' . $this->wpdb->esc_like($search) . '%';
            $params[] = '%' . $this->wpdb->esc_like($search) . '%';
        }
        
        $sql .= " ORDER BY uploaded_at DESC";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function upload_document($request) {
        // Handle file upload via multipart/form-data
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Get file params from REST API request (for multipart/form-data)
        $file_params = $request->get_file_params();
        $uploaded_file = $file_params['file'] ?? null;
        
        if (!$uploaded_file || empty($uploaded_file['tmp_name'])) {
            return new WP_Error('no_file', 'No file uploaded', array('status' => 400));
        }
        
        // Get body parameters from request
        // For multipart/form-data, try $_POST as fallback since get_param may not work
        $job_id = intval($request->get_param('job_id') ?: ($_POST['job_id'] ?? 0));
        
        // Validate job_id is present
        if (!$job_id) {
            return new WP_Error('missing_job_id', 'Job ID is required', array('status' => 400));
        }
        
        $title = sanitize_text_field($request->get_param('title') ?: ($_POST['title'] ?? $uploaded_file['name']));
        $description = sanitize_textarea_field($request->get_param('description') ?: ($_POST['description'] ?? ''));
        $category = sanitize_text_field($request->get_param('category') ?: ($_POST['category'] ?? 'general'));
        
        // Ensure upload directory exists
        $upload_dir = wp_upload_dir();
        $crm_upload_dir = $upload_dir['basedir'] . '/pi-crm-documents';
        if (!file_exists($crm_upload_dir)) {
            wp_mkdir_p($crm_upload_dir);
        }
        
        // Move uploaded file to proper location
        $filename = sanitize_file_name($uploaded_file['name']);
        $unique_filename = wp_unique_filename($crm_upload_dir, $filename);
        $file_path = $crm_upload_dir . '/' . $unique_filename;
        
        if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            return new WP_Error('upload_failed', 'Failed to move uploaded file', array('status' => 500));
        }
        
        // Determine file type
        $file_type = $uploaded_file['type'];
        if (function_exists('wp_check_filetype')) {
            $file_info = wp_check_filetype($file_path);
            $file_type = $file_info['type'] ?: $file_type;
        }
        
        $file_url = $upload_dir['baseurl'] . '/pi-crm-documents/' . $unique_filename;
        
        $table = $this->tables['documents'];
        
        // Ensure table exists before inserting
        $this->maybe_create_documents_table();
        
        $amount = $request->get_param('amount') ?: ($_POST['amount'] ?? null);
        
        $data = array(
            'job_id' => $job_id,
            'title' => $title,
            'description' => $description,
            'file_name' => $filename,
            'file_path' => $file_url, // Store web URL for display
            'file_type' => $file_type,
            'file_size' => $uploaded_file['size'],
            'amount' => $amount ? floatval($amount) : null,
            'category' => $category,
            'version' => sanitize_text_field($request->get_param('version') ?: ($_POST['version'] ?? '1.0')),
            'uploaded_by' => get_current_user_id(),
            'uploaded_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($table, $data);
        
        if ($result === false) {
            // Clean up uploaded file if DB insert failed
            @unlink($file_path);
            return new WP_Error('db_error', 'Failed to save document record: ' . $this->wpdb->last_error, array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'url' => $file_url,
            'success' => true,
            'message' => 'Document uploaded successfully'
        ), 201);
    }
    
    /**
     * Create documents table if it doesn't exist
     */
    private function maybe_create_documents_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_documents';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        // Check if amount column exists in existing table
        if ($table_exists) {
            $column_exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = 'amount'",
                $table_name
            ));
            
            if (!$column_exists) {
                // Add amount column to existing table
                $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN amount decimal(10,2) DEFAULT NULL AFTER file_size");
            }
            return;
        }
        
        // Create table
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            title varchar(300) NOT NULL,
            description text,
            file_name varchar(300) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            amount decimal(10,2) DEFAULT NULL,
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
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create employees table if it doesn't exist
     */
    private function maybe_create_employees_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_employees';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        if ($table_exists) {
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned DEFAULT NULL,
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
            KEY job_id (job_id),
            KEY email (email),
            KEY status (status),
            KEY role (role),
            KEY created_by (created_by)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create photos table if it doesn't exist
     */
    private function maybe_create_photos_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_job_photos';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        if ($table_exists) {
            return;
        }
        
        // Create table
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            photo_type varchar(50) DEFAULT 'general',
            category varchar(50) DEFAULT 'site',
            title varchar(300) DEFAULT NULL,
            description text,
            file_name varchar(300) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            taken_by varchar(100) DEFAULT NULL,
            photographer_id bigint(20) unsigned DEFAULT NULL,
            taken_at datetime DEFAULT NULL,
            gps_latitude decimal(10,8) DEFAULT NULL,
            gps_longitude decimal(11,8) DEFAULT NULL,
            is_before_photo tinyint(1) DEFAULT 0,
            is_after_photo tinyint(1) DEFAULT 0,
            tags text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY category (category),
            KEY photo_type (photo_type),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Migration: Add job_id column if not exists (for existing installations)
        $column_exists = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'job_id'");
        if (empty($column_exists)) {
            $this->wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN job_id bigint(20) unsigned DEFAULT NULL AFTER id, ADD KEY job_id (job_id)");
        }
    }
    
    /**
     * Create crews table if it doesn't exist
     */
    private function maybe_create_crews_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_crews';
        
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        if ($table_exists) return;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            crew_name varchar(100) NOT NULL,
            trade_specialty varchar(100) DEFAULT NULL,
            foreman_id bigint(20) unsigned DEFAULT NULL,
            description text,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY foreman_id (foreman_id),
            KEY status (status)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create crew members table if it doesn't exist
     */
    private function maybe_create_crew_members_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_crew_members';
        
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        if ($table_exists) return;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            crew_id bigint(20) unsigned NOT NULL,
            employee_id bigint(20) unsigned NOT NULL,
            role_in_crew varchar(50) DEFAULT 'member',
            joined_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY crew_id (crew_id),
            KEY employee_id (employee_id)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create clock status table if it doesn't exist
     */
    private function maybe_create_clock_status_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_clock_status';
        
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        if ($table_exists) return;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) unsigned NOT NULL,
            status varchar(20) DEFAULT 'clocked_out',
            job_id bigint(20) unsigned DEFAULT NULL,
            clock_in_time datetime DEFAULT NULL,
            clock_out_time datetime DEFAULT NULL,
            break_start_time datetime DEFAULT NULL,
            break_end_time datetime DEFAULT NULL,
            total_break_minutes int(11) DEFAULT 0,
            gps_lat decimal(10,8) DEFAULT NULL,
            gps_lng decimal(11,8) DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY status (status)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create timesheet approvals table if it doesn't exist
     */
    private function maybe_create_timesheet_approvals_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_timesheet_approvals';
        
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        if ($table_exists) return;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timesheet_id bigint(20) unsigned NOT NULL,
            approver_id bigint(20) unsigned NOT NULL,
            status varchar(20) DEFAULT 'pending',
            approved_at datetime DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY timesheet_id (timesheet_id),
            KEY approver_id (approver_id),
            KEY status (status)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create cost codes table if it doesn't exist
     */
    private function maybe_create_cost_codes_table() {
        $table_name = $this->wpdb->prefix . 'pi_crm_cost_codes';
        
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        if ($table_exists) return;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            description varchar(255) DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Insert default cost codes
        $defaults = array(
            array('code' => 'LAB', 'description' => 'General Labour', 'category' => 'Labour'),
            array('code' => 'SKILL', 'description' => 'Skilled Labour', 'category' => 'Labour'),
            array('code' => 'OT', 'description' => 'Overtime', 'category' => 'Labour'),
            array('code' => 'TRAVEL', 'description' => 'Travel Time', 'category' => 'Other')
        );
        
        foreach ($defaults as $code) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE code = %s",
                $code['code']
            ));
            if (!$exists) {
                $this->wpdb->insert($table_name, $code);
            }
        }
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Timesheets
    // ============================================
    
    public function get_timesheets($request) {
        $job_id = intval($request->get_param('job_id'));
        $worker_id = $request->get_param('worker_id');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $all_jobs = $request->get_param('all_jobs');

        $table = $this->tables['timesheets'];

        // If all_jobs is set, fetch timesheets for all jobs
        if ($all_jobs || $job_id === 0) {
            $sql = "SELECT * FROM {$table} WHERE 1=1";
            $params = array();
        } else {
            $sql = "SELECT * FROM {$table} WHERE job_id = %d";
            $params = array($job_id);
        }

        if ($worker_id) {
            $sql .= " AND worker_id = %d";
            $params[] = intval($worker_id);
        }

        if ($start_date) {
            $sql .= " AND work_date >= %s";
            $params[] = sanitize_text_field($start_date);
        }

        if ($end_date) {
            $sql .= " AND work_date <= %s";
            $params[] = sanitize_text_field($end_date);
        }

        $sql .= " ORDER BY work_date DESC, created_at DESC";

        if (!empty($params)) {
            $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            $results = $this->wpdb->get_results($sql, ARRAY_A);
        }
        
        // Add job_code to each result for proper display
        if ($results) {
            foreach ($results as &$row) {
                if (!empty($row['job_id'])) {
                    $job_code = get_post_meta($row['job_id'], '_pi_job_ref', true);
                    $row['job_code'] = $job_code ?: get_the_title($row['job_id']);
                }
            }
        }

        return new WP_REST_Response($results ?: array(), 200);
    }
    
    public function create_timesheet($request) {
        $data = $request->get_json_params();
        $table = $this->tables['timesheets'];
        $employees_table = $this->tables['employees'];

        error_log('[PI_CRM_API] create_timesheet received data: ' . json_encode($data));

        // Support both employee_id and worker_id
        $worker_id = intval($data['employee_id'] ?? $data['worker_id'] ?? 0);

        // Lookup worker name from employees table if not provided
        $worker_name = sanitize_text_field($data['worker_name'] ?? '');
        if (empty($worker_name) && $worker_id) {
            $employee = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT first_name, last_name FROM {$employees_table} WHERE id = %d",
                $worker_id
            ));
            if ($employee) {
                $worker_name = $employee->first_name . ' ' . $employee->last_name;
            } else {
                $worker_name = 'Unknown';
            }
        }

        error_log('[PI_CRM_API] worker_id: ' . $worker_id . ', worker_name: ' . $worker_name);

        // Validate required fields
        if (!$worker_id) {
            return new WP_Error('missing_worker', 'Employee ID is required', array('status' => 400));
        }
        if (empty($data['work_date'])) {
            return new WP_Error('missing_date', 'Work date is required', array('status' => 400));
        }
        if (empty($data['start_time']) || empty($data['end_time'])) {
            return new WP_Error('missing_time', 'Start and end time are required', array('status' => 400));
        }

        // Calculate total hours
        $start = strtotime($data['start_time'] ?? '00:00');
        $end = strtotime($data['end_time'] ?? '00:00');
        $break = intval($data['break_duration'] ?? 0) / 60; // convert minutes to hours
        $total_hours = (($end - $start) / 3600) - $break;

        // Support both job_id from data or from PI_Job context
        $job_id = intval($data['job_id'] ?? 0);

        // Calculate overtime if total hours > 8
        $overtime_hours = $total_hours > 8 ? $total_hours - 8 : 0;
        $regular_hours = $total_hours - $overtime_hours;

        $insert_data = array(
            'job_id' => $job_id,
            'worker_id' => $worker_id,
            'worker_name' => $worker_name,
            'work_date' => sanitize_text_field($data['work_date']),
            'start_time' => sanitize_text_field($data['start_time']),
            'end_time' => sanitize_text_field($data['end_time']),
            'break_duration' => intval($data['break_duration'] ?? 0),
            'total_hours' => max(0, $regular_hours),
            'overtime_hours' => max(0, $overtime_hours),
            'hourly_rate' => floatval($data['hourly_rate'] ?? 0),
            'cost_code' => sanitize_text_field($data['cost_code'] ?? ''),
            'task_description' => sanitize_textarea_field($data['notes'] ?? $data['task_description'] ?? ''),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );

        error_log('[PI_CRM_API] insert_data: ' . json_encode($insert_data));

        $result = $this->wpdb->insert($table, $insert_data);

        if ($result === false) {
            error_log('[PI_CRM_API] insert error: ' . $this->wpdb->last_error);
            return new WP_Error('insert_failed', 'Failed to create timesheet: ' . $this->wpdb->last_error, array('status' => 500));
        }

        error_log('[PI_CRM_API] timesheet created with ID: ' . $this->wpdb->insert_id);

        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'total_hours' => $total_hours,
            'success' => true
        ), 201);
    }
    
    public function clock_in_out($request) {
        $data = $request->get_json_params();
        $job_id = intval($data['job_id']);
        $worker_id = get_current_user_id();
        $action = sanitize_text_field($data['action']); // 'in' or 'out'
        
        $table = $this->tables['timesheets'];
        
        if ($action === 'in') {
            // Create new timesheet entry with start time
            $insert_data = array(
                'job_id' => $job_id,
                'worker_id' => $worker_id,
                'worker_name' => wp_get_current_user()->display_name,
                'work_date' => current_time('Y-m-d'),
                'start_time' => current_time('H:i:s'),
                'status' => 'active',
                'gps_coordinates' => sanitize_text_field($data['gps'] ?? ''),
                'created_at' => current_time('mysql')
            );
            
            $this->wpdb->insert($table, $insert_data);
            
            return new WP_REST_Response(array(
                'timesheet_id' => $this->wpdb->insert_id,
                'clock_in_time' => $insert_data['start_time'],
                'success' => true
            ), 201);
        } else {
            // Find active timesheet and update end time
            $active_entry = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT id, start_time FROM {$table} WHERE job_id = %d AND worker_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
                $job_id, $worker_id
            ));
            
            if (!$active_entry) {
                return new WP_Error('no_active_entry', 'No active timesheet found', array('status' => 400));
            }
            
            $end_time = current_time('H:i:s');
            $start = strtotime($active_entry->start_time);
            $end = strtotime($end_time);
            $total_hours = ($end - $start) / 3600;
            
            $this->wpdb->update($table, array(
                'end_time' => $end_time,
                'total_hours' => $total_hours,
                'status' => 'pending'
            ), array('id' => $active_entry->id));
            
            return new WP_REST_Response(array(
                'timesheet_id' => $active_entry->id,
                'clock_out_time' => $end_time,
                'total_hours' => round($total_hours, 2),
                'success' => true
            ), 200);
        }
    }
    
    public function get_timesheet_summary($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->tables['timesheets'];
        
        $summary = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT worker_id) as total_workers,
                SUM(total_hours) as total_hours,
                SUM(total_hours * hourly_rate) as total_labor_cost,
                COUNT(*) as total_entries
            FROM {$table} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($summary, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Safety
    // ============================================
    
    public function get_safety_inspections($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['safety_inspections'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY inspection_date DESC",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_safety_inspection($request) {
        $data = $request->get_json_params();
        $table = $this->tables['safety_inspections'];
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'inspection_type' => sanitize_text_field($data['inspection_type']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'inspector_name' => sanitize_text_field($data['inspector_name']),
            'inspection_date' => sanitize_text_field($data['inspection_date']),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    public function get_safety_incidents($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['safety_incidents'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY incident_date DESC",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_safety_incident($request) {
        $data = $request->get_json_params();
        $table = $this->tables['safety_incidents'];
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'incident_date' => sanitize_text_field($data['incident_date']),
            'reported_by' => sanitize_text_field($data['reported_by']),
            'incident_type' => sanitize_text_field($data['incident_type']),
            'severity' => sanitize_text_field($data['severity'] ?? 'minor'),
            'description' => sanitize_textarea_field($data['description']),
            'status' => 'open',
            'rIDDOR_reportable' => !empty($data['riddor_reportable']) ? 1 : 0,
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    public function get_checklist_templates() {
        $templates = array(
            'daily_site' => array(
                'name' => 'Daily Site Inspection',
                'items' => array(
                    array('category' => 'Access', 'text' => 'Site access controlled and secure'),
                    array('category' => 'Access', 'text' => 'Signage in place and visible'),
                    array('category' => 'Welfare', 'text' => 'Toilet facilities clean and stocked'),
                    array('category' => 'Welfare', 'text' => 'Drinking water available'),
                    array('category' => 'Welfare', 'text' => 'First aid kit stocked and accessible'),
                    array('category' => 'PPE', 'text' => 'All personnel wearing hard hats'),
                    array('category' => 'PPE', 'text' => 'All personnel wearing hi-vis vests'),
                    array('category' => 'PPE', 'text' => 'Safety boots worn by all'),
                    array('category' => 'Housekeeping', 'text' => 'Walkways clear and unobstructed'),
                    array('category' => 'Housekeeping', 'text' => 'Materials stored safely'),
                    array('category' => 'Fire Safety', 'text' => 'Fire extinguishers in place and charged'),
                    array('category' => 'Fire Safety', 'text' => 'Fire exits clear'),
                    array('category' => 'Electrical', 'text' => 'Electrical equipment PAT tested'),
                    array('category' => 'Electrical', 'text' => 'No trailing cables')
                )
            ),
            'weekly_site' => array(
                'name' => 'Weekly Site Inspection',
                'items' => array(
                    array('category' => 'Scaffolding', 'text' => 'Scaffolding inspected and tagged'),
                    array('category' => 'Scaffolding', 'text' => 'No missing boards or guardrails'),
                    array('category' => 'Excavations', 'text' => 'Excavations properly shored/battered'),
                    array('category' => 'Excavations', 'text' => 'Edge protection in place'),
                    array('category' => 'Plant', 'text' => 'Plant machinery inspected'),
                    array('category' => 'Plant', 'text' => 'Operators certified and present'),
                    array('category' => 'Hazardous Substances', 'text' => 'COSHH assessments current'),
                    array('category' => 'Hazardous Substances', 'text' => 'SDS available for all substances')
                )
            ),
            'monthly_management' => array(
                'name' => 'Monthly Management Inspection',
                'items' => array(
                    array('category' => 'Documentation', 'text' => 'Risk assessments up to date'),
                    array('category' => 'Documentation', 'text' => 'Method statements reviewed'),
                    array('category' => 'Documentation', 'text' => 'Toolbox talks records complete'),
                    array('category' => 'Training', 'text' => 'Training records current for all workers'),
                    array('category' => 'Training', 'text' => 'Induction records complete'),
                    array('category' => 'Emergency', 'text' => 'Emergency procedures posted'),
                    array('category' => 'Emergency', 'text' => 'Assembly point clearly marked')
                )
            )
        );
        
        return new WP_REST_Response($templates, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Quality
    // ============================================
    
    public function get_quality_snags($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $status = $request->get_param('status');
        
        $table = $this->tables['quality_snags'];
        $sql = "SELECT * FROM {$table} WHERE job_id = %d";
        $params = array($job_id);
        
        if ($status) {
            $sql .= " AND status = %s";
            $params[] = sanitize_text_field($status);
        }
        
        $sql .= " ORDER BY identified_date DESC";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_quality_snag($request) {
        $data = $request->get_json_params();
        $table = $this->tables['quality_snags'];
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description']),
            'location' => sanitize_text_field($data['location'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? 'general'),
            'priority' => sanitize_text_field($data['priority'] ?? 'medium'),
            'identified_by' => sanitize_text_field($data['identified_by']),
            'identified_date' => sanitize_text_field($data['identified_date'] ?? current_time('Y-m-d')),
            'due_date' => sanitize_text_field($data['due_date'] ?? ''),
            'status' => 'open',
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    public function get_quality_signoffs($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['quality_signoffs'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY requested_at DESC",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Change Orders
    // ============================================
    
    public function get_change_orders($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['change_orders'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY request_date DESC",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_change_order($request) {
        $data = $request->get_json_params();
        $table = $this->tables['change_orders'];
        
        // Generate CO number
        $co_number = 'CO-' . strtoupper(wp_generate_password(6, false));
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'co_number' => $co_number,
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description']),
            'reason' => sanitize_textarea_field($data['reason'] ?? ''),
            'requested_by' => sanitize_text_field($data['requested_by']),
            'request_date' => sanitize_text_field($data['request_date'] ?? current_time('Y-m-d')),
            'cost_impact' => floatval($data['cost_impact'] ?? 0),
            'schedule_impact_days' => intval($data['schedule_impact_days'] ?? 0),
            'status' => 'draft',
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'co_number' => $co_number,
            'success' => true
        ), 201);
    }
    
    public function approve_change_order($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['change_orders'];
        
        $update_data = array(
            'status' => 'approved',
            'approved_by' => sanitize_text_field($data['approved_by']),
            'approver_id' => get_current_user_id(),
            'approved_at' => current_time('mysql')
        );
        
        if (!empty($data['client_approved'])) {
            $update_data['client_approved'] = 1;
            $update_data['client_approved_at'] = current_time('mysql');
        }
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Invoicing
    // ============================================
    
    public function get_invoices($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['invoices'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY issue_date DESC",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_invoice($request) {
        $data = $request->get_json_params();
        $table = $this->tables['invoices'];
        
        // Generate invoice number
        $invoice_number = 'INV-' . date('Y') . '-' . strtoupper(wp_generate_password(4, false));
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'invoice_number' => $invoice_number,
            'invoice_type' => sanitize_text_field($data['invoice_type'] ?? 'progress'),
            'issue_date' => sanitize_text_field($data['issue_date'] ?? current_time('Y-m-d')),
            'due_date' => sanitize_text_field($data['due_date']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'subtotal' => floatval($data['subtotal'] ?? 0),
            'tax_amount' => floatval($data['tax_amount'] ?? 0),
            'total_amount' => floatval($data['total_amount'] ?? 0),
            'retention_amount' => floatval($data['retention_amount'] ?? 0),
            'status' => 'draft',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        // Insert line items if provided
        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            $items_table = $this->tables['invoice_items'];
            foreach ($data['line_items'] as $item) {
                $this->wpdb->insert($items_table, array(
                    'invoice_id' => $this->wpdb->insert_id,
                    'description' => sanitize_text_field($item['description']),
                    'quantity' => floatval($item['quantity'] ?? 1),
                    'unit_price' => floatval($item['unit_price'] ?? 0),
                    'total_price' => floatval($item['total_price'] ?? 0),
                    'cost_code' => sanitize_text_field($item['cost_code'] ?? '')
                ));
            }
        }
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'invoice_number' => $invoice_number,
            'success' => true
        ), 201);
    }
    
    public function record_payment($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['invoices'];
        
        $update_data = array(
            'paid_amount' => floatval($data['amount']),
            'payment_method' => sanitize_text_field($data['payment_method']),
            'payment_reference' => sanitize_text_field($data['reference'] ?? ''),
            'paid_at' => current_time('mysql'),
            'status' => 'paid'
        );
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_invoice_summary($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->tables['invoices'];
        
        $summary = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_invoices,
                SUM(total_amount) as total_billed,
                SUM(paid_amount) as total_paid,
                SUM(total_amount - paid_amount) as total_outstanding,
                SUM(CASE WHEN status = 'draft' THEN total_amount ELSE 0 END) as draft_amount,
                SUM(CASE WHEN status = 'sent' THEN total_amount ELSE 0 END) as awaiting_payment,
                SUM(retention_amount) as total_retention
            FROM {$table} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($summary, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Daily Reports
    // ============================================
    
    public function get_daily_reports($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['daily_reports'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY report_date DESC",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_daily_report($request) {
        $data = $request->get_json_params();
        $table = $this->tables['daily_reports'];
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'report_date' => sanitize_text_field($data['report_date'] ?? current_time('Y-m-d')),
            'weather_conditions' => sanitize_text_field($data['weather_conditions'] ?? ''),
            'weather_temp_high' => intval($data['weather_temp_high'] ?? 0),
            'weather_temp_low' => intval($data['weather_temp_low'] ?? 0),
            'workforce_count' => intval($data['workforce_count'] ?? 0),
            'workforce_details' => sanitize_textarea_field($data['workforce_details'] ?? ''),
            'work_completed' => sanitize_textarea_field($data['work_completed'] ?? ''),
            'work_planned' => sanitize_textarea_field($data['work_planned'] ?? ''),
            'delays' => sanitize_textarea_field($data['delays'] ?? ''),
            'safety_issues' => sanitize_textarea_field($data['safety_issues'] ?? ''),
            'materials_received' => sanitize_textarea_field($data['materials_received'] ?? ''),
            'submitted_by' => get_current_user_id(),
            'submitted_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Equipment
    // ============================================
    
    public function get_equipment($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['equipment'];
        $employees_table = $this->wpdb->prefix . 'pi_crm_employees';
        
        // Check if employees table exists
        $employees_table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$employees_table}'") === $employees_table;
        
        // Build query with employee joins if table exists
        if ($employees_table_exists) {
            $sql = "SELECT e.*,
                op.first_name as operator_first_name,
                op.last_name as operator_last_name,
                op.role as operator_role,
                sup.first_name as supervisor_first_name,
                sup.last_name as supervisor_last_name
                FROM {$table} e
                LEFT JOIN {$employees_table} op ON e.assigned_operator_id = op.id
                LEFT JOIN {$employees_table} sup ON e.supervisor_responsible_id = sup.id";
        } else {
            $sql = "SELECT * FROM {$table}";
        }
        
        $params = array();
        
        if ($job_id) {
            $sql .= " WHERE e.job_id = %d OR e.job_id IS NULL";
            $params[] = $job_id;
        }
        
        $sql .= " ORDER BY e.equipment_name";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        // Map and normalize data for frontend consistency
        foreach ($results as &$item) {
            // Map internal_name to ensure consistent response
            if (empty($item['internal_name'])) {
                $item['internal_name'] = $item['equipment_name'] ?? $item['equipment_type'] ?? 'Unnamed Equipment';
            }
            
            // Map acquisition_type from ownership_type for consistency
            if (empty($item['acquisition_type'])) {
                $item['acquisition_type'] = $item['ownership_type'] ?? 'Owned';
            }
            
            // Build operator name from joins
            $item['operator_name'] = null;
            if ($employees_table_exists && !empty($item['operator_first_name']) && !empty($item['operator_last_name'])) {
                $item['operator_name'] = $item['operator_first_name'] . ' ' . $item['operator_last_name'];
                if (!empty($item['operator_role'])) {
                    $item['operator_name'] .= ' (' . $item['operator_role'] . ')';
                }
            }
            
            // Build supervisor name from joins
            $item['supervisor_name'] = null;
            if ($employees_table_exists && !empty($item['supervisor_first_name']) && !empty($item['supervisor_last_name'])) {
                $item['supervisor_name'] = $item['supervisor_first_name'] . ' ' . $item['supervisor_last_name'];
            }
            
            // Ensure cost fields have defaults
            $item['cost_to_job'] = floatval($item['cost_to_job'] ?? 0);
            $item['daily_rate'] = floatval($item['daily_rate'] ?? $item['hire_rate'] ?? 0);
            $item['weekly_rate'] = floatval($item['weekly_rate'] ?? 0);
            $item['monthly_rate'] = floatval($item['monthly_rate'] ?? 0);
            
            // Ensure condition has default - map from old field if needed
            if (empty($item['current_condition'])) {
                $item['current_condition'] = $item['condition_notes'] ?? 'Good';
            }
            
            // Map old date field names to new field names for frontend
            if (empty($item['allocated_from_date'])) {
                $item['allocated_from_date'] = $item['hire_start_date'] ?? null;
            }
            if (empty($item['allocated_to_date'])) {
                $item['allocated_to_date'] = $item['hire_end_date'] ?? null;
            }
            
            // Remove temporary join fields and old field names
            unset($item['operator_first_name'], $item['operator_last_name'], $item['operator_role']);
            unset($item['supervisor_first_name'], $item['supervisor_last_name']);
            unset($item['hire_start_date'], $item['hire_end_date']);
        }
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_equipment($request) {
        $data = $request->get_json_params();
        $table = $this->tables['equipment'];
        
        // Handle internal_name/equipment_name - use internal_name if provided, otherwise equipment_name
        $name_value = sanitize_text_field($data['internal_name'] ?? $data['equipment_name'] ?? $data['equipment_type'] ?? 'Equipment');
        
        error_log('[OLD CRM API] Create equipment - name value: ' . $name_value);
        
        // Get available columns for backward compatibility
        $columns = $this->wpdb->get_col("DESCRIBE {$table}");
        error_log('[OLD CRM API] Available columns: ' . implode(', ', $columns));
        
        $insert_data = array(
            'job_id' => !empty($data['job_id']) ? intval($data['job_id']) : null,
            'equipment_name' => $name_value,
            'equipment_type' => sanitize_text_field($data['equipment_type']),
            'make' => sanitize_text_field($data['make'] ?? ''),
            'model' => sanitize_text_field($data['model'] ?? ''),
            'serial_number' => sanitize_text_field($data['serial_number'] ?? ''),
            'ownership_type' => sanitize_text_field($data['acquisition_type'] ?? $data['ownership_type'] ?? 'owned'),
            'hire_company' => sanitize_text_field($data['hire_company'] ?? $data['supplier_name'] ?? ''),
            'hire_rate' => floatval($data['hire_rate'] ?? $data['daily_rate'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? 'On-Site'),
            'next_service_due' => sanitize_text_field($data['next_service_due'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        // Save to internal_name column if it exists
        if (in_array('internal_name', $columns)) {
            $insert_data['internal_name'] = $name_value;
        }
        
        // Save new schema fields if columns exist
        if (in_array('category', $columns)) {
            $insert_data['category'] = sanitize_text_field($data['category'] ?? 'General');
        }
        if (in_array('manufacturer', $columns)) {
            $insert_data['manufacturer'] = sanitize_text_field($data['manufacturer'] ?? $data['make'] ?? '');
        }
        if (in_array('brand', $columns)) {
            $insert_data['brand'] = sanitize_text_field($data['brand'] ?? '');
        }
        if (in_array('model_number', $columns)) {
            $insert_data['model_number'] = sanitize_text_field($data['model_number'] ?? '');
        }
        if (in_array('vin', $columns)) {
            $insert_data['vin'] = sanitize_text_field($data['vin'] ?? '');
        }
        if (in_array('asset_tag', $columns)) {
            $insert_data['asset_tag'] = sanitize_text_field($data['asset_tag'] ?? '');
        }
        if (in_array('acquisition_type', $columns)) {
            $insert_data['acquisition_type'] = sanitize_text_field($data['acquisition_type'] ?? 'Owned');
        }
        if (in_array('supplier_name', $columns)) {
            $insert_data['supplier_name'] = sanitize_text_field($data['supplier_name'] ?? '');
        }
        if (in_array('supplier_contact', $columns)) {
            $insert_data['supplier_contact'] = sanitize_text_field($data['supplier_contact'] ?? '');
        }
        if (in_array('hire_reference_number', $columns)) {
            $insert_data['hire_reference_number'] = sanitize_text_field($data['hire_reference_number'] ?? '');
        }
        if (in_array('rate_type', $columns)) {
            $insert_data['rate_type'] = sanitize_text_field($data['rate_type'] ?? 'daily');
        }
        if (in_array('daily_rate', $columns)) {
            $insert_data['daily_rate'] = floatval($data['daily_rate'] ?? 0);
        }
        if (in_array('weekly_rate', $columns)) {
            $insert_data['weekly_rate'] = floatval($data['weekly_rate'] ?? 0);
        }
        if (in_array('monthly_rate', $columns)) {
            $insert_data['monthly_rate'] = floatval($data['monthly_rate'] ?? 0);
        }
        if (in_array('cost_to_job', $columns)) {
            $insert_data['cost_to_job'] = floatval($data['cost_to_job'] ?? 0);
        }
        if (in_array('deposit_held', $columns)) {
            $insert_data['deposit_held'] = floatval($data['deposit_held'] ?? 0);
        }
        if (in_array('allocated_from_date', $columns) && !empty($data['allocated_from_date'])) {
            $insert_data['allocated_from_date'] = sanitize_text_field($data['allocated_from_date']);
        } else {
            // Fallback to old schema field name
            if (!empty($data['allocated_from_date'])) {
                $insert_data['hire_start_date'] = sanitize_text_field($data['allocated_from_date']);
            }
        }
        if (in_array('allocated_to_date', $columns) && !empty($data['allocated_to_date'])) {
            $insert_data['allocated_to_date'] = sanitize_text_field($data['allocated_to_date']);
        } else {
            // Fallback to old schema field name
            if (!empty($data['allocated_to_date'])) {
                $insert_data['hire_end_date'] = sanitize_text_field($data['allocated_to_date']);
            }
        }
        if (in_array('actual_on_site_date', $columns) && !empty($data['actual_on_site_date'])) {
            $insert_data['actual_on_site_date'] = sanitize_text_field($data['actual_on_site_date']);
        }
        if (in_array('actual_return_date', $columns) && !empty($data['actual_return_date'])) {
            $insert_data['actual_return_date'] = sanitize_text_field($data['actual_return_date']);
        }
        if (in_array('current_condition', $columns)) {
            $insert_data['current_condition'] = sanitize_text_field($data['current_condition'] ?? 'Good');
        } else {
            // Fallback to old schema field name
            if (!empty($data['current_condition'])) {
                $insert_data['condition_notes'] = sanitize_text_field($data['current_condition']);
            }
        }
        if (in_array('assigned_operator_id', $columns) && !empty($data['assigned_operator_id'])) {
            $insert_data['assigned_operator_id'] = intval($data['assigned_operator_id']);
        }
        if (in_array('operator_certification_required', $columns)) {
            $insert_data['operator_certification_required'] = sanitize_text_field($data['operator_certification_required'] ?? '');
        }
        if (in_array('supervisor_responsible_id', $columns) && !empty($data['supervisor_responsible_id'])) {
            $insert_data['supervisor_responsible_id'] = intval($data['supervisor_responsible_id']);
        }
        if (in_array('current_location_on_site', $columns)) {
            $insert_data['current_location_on_site'] = sanitize_text_field($data['current_location_on_site'] ?? '');
        }
        
        error_log('[OLD CRM API] Inserting equipment with data: ' . json_encode($insert_data));
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Subcontractors
    // ============================================
    
    public function get_job_subcontractors($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['job_subcontractors'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY subcontractor_name",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function create_job_subcontractor($request) {
        $data = $request->get_json_params();
        $table = $this->tables['job_subcontractors'];
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'subcontractor_name' => sanitize_text_field($data['subcontractor_name']),
            'company_name' => sanitize_text_field($data['company_name'] ?? ''),
            'trade' => sanitize_text_field($data['trade']),
            'contact_name' => sanitize_text_field($data['contact_name'] ?? ''),
            'contact_email' => sanitize_email($data['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($data['contact_phone'] ?? ''),
            'contract_value' => floatval($data['contract_value'] ?? 0),
            'contract_scope' => sanitize_textarea_field($data['contract_scope'] ?? ''),
            'status' => 'active',
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Client
    // ============================================
    
    public function get_client_details($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->tables['client_details'];
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        
        if (!$result) {
            // Return empty structure
            return new WP_REST_Response(array(
                'job_id' => $job_id,
                'primary_contact_name' => '',
                'primary_contact_email' => '',
                'primary_contact_phone' => '',
                'site_address' => ''
            ), 200);
        }
        
        return new WP_REST_Response($result, 200);
    }
    
    public function update_client_details($request) {
        $job_id = intval($request->get_param('job_id'));
        $data = $request->get_json_params();
        $table = $this->tables['client_details'];
        
        $update_data = array(
            'client_type' => sanitize_text_field($data['client_type'] ?? 'residential'),
            'company_name' => sanitize_text_field($data['company_name'] ?? ''),
            'primary_contact_name' => sanitize_text_field($data['primary_contact_name']),
            'primary_contact_email' => sanitize_email($data['primary_contact_email']),
            'primary_contact_phone' => sanitize_text_field($data['primary_contact_phone'] ?? ''),
            'secondary_contact_name' => sanitize_text_field($data['secondary_contact_name'] ?? ''),
            'secondary_contact_email' => sanitize_email($data['secondary_contact_email'] ?? ''),
            'secondary_contact_phone' => sanitize_text_field($data['secondary_contact_phone'] ?? ''),
            'billing_address' => sanitize_textarea_field($data['billing_address'] ?? ''),
            'site_address' => sanitize_textarea_field($data['site_address'] ?? ''),
            'preferences' => sanitize_textarea_field($data['preferences'] ?? ''),
            'special_requirements' => sanitize_textarea_field($data['special_requirements'] ?? ''),
            'communication_preferences' => sanitize_text_field($data['communication_preferences'] ?? 'email'),
            'updated_at' => current_time('mysql')
        );
        
        // Check if record exists
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE job_id = %d",
            $job_id
        ));
        
        if ($exists) {
            $this->wpdb->update($table, $update_data, array('job_id' => $job_id));
        } else {
            $update_data['job_id'] = $job_id;
            $update_data['created_at'] = current_time('mysql');
            $this->wpdb->insert($table, $update_data);
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_client_job_history($request) {
        $email = sanitize_email($request->get_param('email'));
        $table = $this->tables['client_details'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT cd.*, p.post_title as job_title, p.post_status as job_status 
            FROM {$table} cd 
            JOIN {$this->wpdb->posts} p ON cd.job_id = p.ID 
            WHERE cd.primary_contact_email = %s OR cd.secondary_contact_email = %s",
            $email, $email
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Photos
    // ============================================
    
    public function get_photos($request) {
        $job_id = intval($request->get_param('job_id'));
        $category = $request->get_param('category');
        $photo_type = $request->get_param('photo_type');
        
        $table = $this->tables['job_photos'];
        $sql = "SELECT * FROM {$table} WHERE job_id = %d";
        $params = array($job_id);
        
        if ($category) {
            $sql .= " AND category = %s";
            $params[] = sanitize_text_field($category);
        }
        
        if ($photo_type) {
            $sql .= " AND photo_type = %s";
            $params[] = sanitize_text_field($photo_type);
        }
        
        $sql .= " ORDER BY taken_at DESC";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function upload_photo($request) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Get file params from REST API request (for multipart/form-data)
        $file_params = $request->get_file_params();
        $uploaded_file = $file_params['file'] ?? null;
        
        if (!$uploaded_file || empty($uploaded_file['tmp_name'])) {
            return new WP_Error('no_file', 'No file uploaded', array('status' => 400));
        }
        
        // Get body parameters from request
        // For multipart/form-data, try $_POST as fallback since get_param may not work
        $job_id = intval($request->get_param('job_id') ?: ($_POST['job_id'] ?? 0));
        
        // Validate job_id is present
        if (!$job_id) {
            return new WP_Error('missing_job_id', 'Job ID is required', array('status' => 400));
        }
        
        $photo_type = sanitize_text_field($request->get_param('photo_type') ?: ($_POST['photo_type'] ?? 'general'));
        $category = sanitize_text_field($request->get_param('category') ?: ($_POST['category'] ?? 'site'));
        $title = sanitize_text_field($request->get_param('title') ?: ($_POST['title'] ?? ''));
        $description = sanitize_textarea_field($request->get_param('description') ?: ($_POST['description'] ?? ''));
        
        // Ensure upload directory exists
        $upload_dir = wp_upload_dir();
        $photos_upload_dir = $upload_dir['basedir'] . '/pi-crm-photos';
        if (!file_exists($photos_upload_dir)) {
            wp_mkdir_p($photos_upload_dir);
        }
        
        // Move uploaded file to proper location
        $filename = sanitize_file_name($uploaded_file['name']);
        $unique_filename = wp_unique_filename($photos_upload_dir, $filename);
        $file_path = $photos_upload_dir . '/' . $unique_filename;
        
        if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            return new WP_Error('upload_failed', 'Failed to move uploaded file', array('status' => 500));
        }
        
        // Determine file type
        $file_type = $uploaded_file['type'];
        if (function_exists('wp_check_filetype')) {
            $file_info = wp_check_filetype($file_path);
            $file_type = $file_info['type'] ?: $file_type;
        }
        
        $file_url = $upload_dir['baseurl'] . '/pi-crm-photos/' . $unique_filename;
        
        $table = $this->tables['job_photos'];
        
        // Ensure table exists before inserting
        $this->maybe_create_photos_table();
        
        $data = array(
            'job_id' => $job_id,
            'photo_type' => $photo_type,
            'category' => $category,
            'title' => $title ?: $filename,
            'description' => $description,
            'file_name' => $filename,
            'file_path' => $file_url, // Store web URL for display
            'file_size' => $uploaded_file['size'],
            'taken_by' => wp_get_current_user()->display_name,
            'photographer_id' => get_current_user_id(),
            'taken_at' => current_time('mysql'),
            'gps_latitude' => floatval($request->get_param('gps_latitude') ?: ($_POST['gps_latitude'] ?? 0)),
            'gps_longitude' => floatval($request->get_param('gps_longitude') ?: ($_POST['gps_longitude'] ?? 0)),
            'is_before_photo' => !empty($request->get_param('is_before_photo') ?: ($_POST['is_before_photo'] ?? false)) ? 1 : 0,
            'is_after_photo' => !empty($request->get_param('is_after_photo') ?: ($_POST['is_after_photo'] ?? false)) ? 1 : 0,
            'tags' => sanitize_text_field($request->get_param('tags') ?: ($_POST['tags'] ?? '')),
            'created_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($table, $data);
        
        if ($result === false) {
            // Clean up uploaded file if DB insert failed
            @unlink($file_path);
            return new WP_Error('db_error', 'Failed to save photo record: ' . $this->wpdb->last_error, array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'url' => $file_url,
            'success' => true,
            'message' => 'Photo uploaded successfully'
        ), 201);
    }
    
    public function get_photo_gallery($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->tables['job_photos'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, file_path, title, category, taken_at, is_before_photo, is_after_photo 
            FROM {$table} 
            WHERE job_id = %d 
            ORDER BY category, taken_at DESC",
            $job_id
        ), ARRAY_A);
        
        // Group by category
        $gallery = array();
        foreach ($results as $photo) {
            $cat = $photo['category'];
            if (!isset($gallery[$cat])) {
                $gallery[$cat] = array();
            }
            $gallery[$cat][] = $photo;
        }
        
        return new WP_REST_Response($gallery, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - RFI & Submittals
    // COMPREHENSIVE CONSTRUCTION CRM MODULE
    // ============================================
    
    /**
     * GET /rfi - Get RFI list with filtering and metrics
     */
    public function get_rfi($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $status = sanitize_text_field($request->get_param('status') ?? '');
        $priority = sanitize_text_field($request->get_param('priority') ?? '');
        $assigned_to = sanitize_text_field($request->get_param('assigned_to') ?? '');
        $search = sanitize_text_field($request->get_param('search') ?? '');
        $sort_by = sanitize_text_field($request->get_param('sort_by') ?? 'requested_at');
        $sort_order = sanitize_text_field($request->get_param('sort_order') ?? 'DESC');
        
        $table = $this->tables['rfi'];
        
        // Use defensive SQL that handles NULL dates
        $sql = "SELECT r.*, 
            CASE 
                WHEN r.requested_at IS NULL THEN 0
                ELSE DATEDIFF(COALESCE(r.closed_at, NOW()), r.requested_at)
            END as days_open,
            CASE 
                WHEN r.due_date < CURDATE() AND r.status NOT IN ('closed', 'answered') THEN 'overdue'
                WHEN r.requested_at IS NOT NULL AND DATEDIFF(CURDATE(), r.requested_at) > 7 AND r.status = 'open' THEN 'warning'
                ELSE 'normal'
            END as urgency_flag
            FROM {$table} r WHERE r.job_id = %d";
        $params = array($job_id);
        
        if ($status) {
            $sql .= " AND r.status = %s";
            $params[] = $status;
        }
        if ($priority) {
            $sql .= " AND r.priority = %s";
            $params[] = $priority;
        }
        if ($assigned_to) {
            $sql .= " AND (r.assigned_to LIKE %s OR r.assignee_id = %d)";
            $params[] = '%' . $assigned_to . '%';
            $params[] = intval($assigned_to);
        }
        if ($search) {
            $sql .= " AND (r.title LIKE %s OR r.description LIKE %s OR r.rfi_number LIKE %s)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        
        // Validate sort column
        $allowed_sort = array('requested_at', 'due_date', 'priority', 'status', 'rfi_number');
        if (!in_array($sort_by, $allowed_sort)) {
            $sort_by = 'requested_at';
        }
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY r.{$sort_by} {$sort_order}";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        // Check for database errors
        if ($this->wpdb->last_error) {
            error_log('[PI_CRM_API] get_rfi DATABASE ERROR: ' . $this->wpdb->last_error);
        }
        
        // Ensure results is an array
        if (!is_array($results)) {
            $results = array();
        }
        
        // Calculate metrics
        $metrics = $this->calculate_rfi_metrics($job_id);
        
        return new WP_REST_Response(array(
            'data' => $results,
            'metrics' => $metrics,
            'count' => count($results)
        ), 200);
    }
    
    /**
     * Calculate RFI metrics for dashboard
     */
    private function calculate_rfi_metrics($job_id) {
        $table = $this->tables['rfi'];
        
        $metrics = array(
            'total_open' => 0,
            'overdue' => 0,
            'pending_response' => 0,
            'answered_this_week' => 0,
            'avg_response_days' => 0
        );
        
        // Total open
        $metrics['total_open'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND status IN ('open', 'sent')",
            $job_id
        )));
        
        // Overdue
        $metrics['overdue'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND due_date < CURDATE() AND status NOT IN ('closed', 'answered')",
            $job_id
        )));
        
        // Pending response (with consultant)
        $metrics['pending_response'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND ball_in_court IN ('consultant', 'architect', 'engineer') AND status IN ('open', 'sent')",
            $job_id
        )));
        
        // Answered this week
        $metrics['answered_this_week'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND status = 'answered' AND responded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $job_id
        )));
        
        // Average response time for closed/answered RFIs
        $avg_days = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(DATEDIFF(COALESCE(responded_at, closed_at), requested_at)) 
             FROM {$table} WHERE job_id = %d AND status IN ('closed', 'answered') AND responded_at IS NOT NULL",
            $job_id
        ));
        $metrics['avg_response_days'] = $avg_days ? round(floatval($avg_days), 1) : 0;
        
        return $metrics;
    }
    
    /**
     * GET /rfi/(?P<id>\d+) - Get single RFI with full details
     */
    public function get_single_rfi($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['rfi'];
        $activity_table = $this->tables['rfi_activity'];
        $comments_table = $this->tables['rfi_comments'];
        
        $rfi = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT r.*, 
                DATEDIFF(COALESCE(r.closed_at, NOW()), r.requested_at) as days_open,
                u.display_name as requested_by_name
            FROM {$table} r
            LEFT JOIN {$this->wpdb->users} u ON r.requested_by = u.ID
            WHERE r.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$rfi) {
            return new WP_Error('rfi_not_found', 'RFI not found', array('status' => 404));
        }
        
        // Get activity log
        $activity = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$activity_table} WHERE rfi_id = %d ORDER BY performed_at DESC",
            $id
        ), ARRAY_A);
        
        // Get comments
        $comments = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT c.*, u.display_name as commented_by_name, u.user_email
            FROM {$comments_table} c
            LEFT JOIN {$this->wpdb->users} u ON c.commented_by = u.ID
            WHERE c.rfi_id = %d ORDER BY c.commented_at ASC",
            $id
        ), ARRAY_A);
        
        return new WP_REST_Response(array(
            'rfi' => $rfi,
            'activity_log' => $activity,
            'comments' => $comments
        ), 200);
    }
    
    /**
     * POST /rfi - Create new RFI with auto-numbering
     */
    public function create_rfi($request) {
        error_log('[PI_CRM_API] create_rfi called');
        
        $data = $request->get_json_params();
        error_log('[PI_CRM_API] create_rfi data received: ' . json_encode($data));
        
        // Validate required fields
        if (empty($data['job_id'])) {
            error_log('[PI_CRM_API] create_rfi error: missing job_id');
            return new WP_Error('missing_job_id', 'Job ID is required', array('status' => 400));
        }
        if (empty($data['title'])) {
            error_log('[PI_CRM_API] create_rfi error: missing title');
            return new WP_Error('missing_title', 'Title is required', array('status' => 400));
        }
        
        $table = $this->tables['rfi'];
        $activity_table = $this->tables['rfi_activity'];
        
        $job_id = intval($data['job_id']);
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        error_log('[PI_CRM_API] create_rfi - job_id: ' . $job_id . ', user_id: ' . $current_user_id);
        
        // Get table columns first
        $columns = $this->wpdb->get_col("DESCRIBE {$table}");
        
        // Generate unique RFI number using timestamp + random to avoid collisions
        $timestamp = date('YmdHis');
        $random = mt_rand(100, 999);
        $rfi_number = 'RFI-' . $timestamp . '-' . $random;
        
        // Verify it's truly unique
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE rfi_number = %s",
            $rfi_number
        ));
        
        if ($exists) {
            // Retry with different random
            $random = mt_rand(1000, 9999);
            $rfi_number = 'RFI-' . $timestamp . '-' . $random;
        }
        
        error_log('[PI_CRM_API] create_rfi - generated rfi_number: ' . $rfi_number);
        
        // Build insert data only for existing columns
        $insert_data = array(
            'job_id' => $job_id,
            'rfi_number' => $rfi_number,
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'priority' => sanitize_text_field($data['priority'] ?? 'normal'),
            'created_at' => current_time('mysql')
        );
        
        // Only add optional fields if they exist in table
        $optional_fields = array(
            'suggested_solution' => sanitize_textarea_field($data['suggested_solution'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'open'),
            'assigned_to' => sanitize_text_field($data['assigned_to'] ?? ''),
            'due_date' => sanitize_text_field($data['due_date'] ?? null),
            'drawing_references' => sanitize_text_field($data['drawing_references'] ?? ''),
            'requested_by' => $current_user_id,
            'requested_by_name' => $user->display_name,
            'ball_in_court' => 'contractor',
            'requested_at' => current_time('mysql'),
        );
        
        foreach ($optional_fields as $field => $value) {
            if (in_array($field, $columns)) {
                $insert_data[$field] = $value;
            }
        }
        
        error_log('[PI_CRM_API] create_rfi - attempting insert with rfi_number: ' . $rfi_number);
        $result = $this->wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            error_log('[PI_CRM_API] create_rfi database error: ' . $this->wpdb->last_error);
            return new WP_Error('insert_failed', 'Failed to create RFI: ' . $this->wpdb->last_error, array('status' => 500));
        }
        
        $rfi_id = $this->wpdb->insert_id;
        error_log('[PI_CRM_API] create_rfi success - rfi_id: ' . $rfi_id . ', rfi_number: ' . $rfi_number);
        
        // Log activity
        $this->wpdb->insert($activity_table, array(
            'rfi_id' => $rfi_id,
            'job_id' => $job_id,
            'activity_type' => 'created',
            'description' => 'RFI created',
            'performed_by' => $current_user_id,
            'performed_by_name' => $user->display_name,
            'performed_at' => current_time('mysql')
        ));
        
        // If not draft, log status change
        if ($insert_data['status'] !== 'draft') {
            $this->wpdb->insert($activity_table, array(
                'rfi_id' => $rfi_id,
                'job_id' => $job_id,
                'activity_type' => 'status_change',
                'description' => "Status changed to {$insert_data['status']}",
                'old_value' => 'draft',
                'new_value' => $insert_data['status'],
                'performed_by' => $current_user_id,
                'performed_by_name' => $user->display_name,
                'performed_at' => current_time('mysql')
            ));
        }
        
        error_log('[PI_CRM_API] create_rfi returning success response');
        return new WP_REST_Response(array(
            'id' => $rfi_id,
            'rfi_number' => $rfi_number,
            'success' => true
        ), 201);
    }
    
    /**
     * PATCH /rfi/(?P<id>\d+) - Update RFI
     */
    public function update_rfi($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['rfi'];
        $activity_table = $this->tables['rfi_activity'];
        
        $current_rfi = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$current_rfi) {
            return new WP_Error('rfi_not_found', 'RFI not found', array('status' => 404));
        }
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        $job_id = $current_rfi['job_id'];
        
        $update_data = array();
        $activity_logs = array();
        
        // Track changes for activity log
        $fields_to_track = array(
            'title', 'description', 'suggested_solution', 'priority', 'status',
            'assigned_to', 'assignee_role', 'due_date', 'ball_in_court',
            'drawing_references', 'specification_section', 'schedule_impact',
            'schedule_impact_days', 'cost_impact', 'cost_impact_amount'
        );
        
        foreach ($fields_to_track as $field) {
            if (isset($data[$field])) {
                $new_value = is_string($data[$field]) ? sanitize_text_field($data[$field]) : $data[$field];
                if ($current_rfi[$field] != $new_value) {
                    $update_data[$field] = $new_value;
                    $activity_logs[] = array(
                        'rfi_id' => $id,
                        'job_id' => $job_id,
                        'activity_type' => $field === 'status' ? 'status_change' : 'field_update',
                        'description' => ucfirst(str_replace('_', ' ', $field)) . ' updated',
                        'old_value' => $current_rfi[$field],
                        'new_value' => $new_value,
                        'performed_by' => $current_user_id,
                        'performed_by_name' => $user->display_name,
                        'performed_at' => current_time('mysql')
                    );
                }
            }
        }
        
        if (isset($data['attachments'])) {
            $update_data['attachments'] = is_array($data['attachments']) ? json_encode($data['attachments']) : sanitize_text_field($data['attachments']);
        }
        
        if (!empty($update_data)) {
            $update_data['updated_at'] = current_time('mysql');
            $this->wpdb->update($table, $update_data, array('id' => $id));
            
            // Log activities
            foreach ($activity_logs as $log) {
                $this->wpdb->insert($activity_table, $log);
            }
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * POST /rfi/(?P<id>\d+)/respond - Respond to RFI
     */
    public function respond_rfi($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['rfi'];
        $activity_table = $this->tables['rfi_activity'];
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $update_data = array(
            'response' => sanitize_textarea_field($data['response']),
            'responded_by' => $current_user_id,
            'responded_by_name' => $user->display_name,
            'responded_at' => current_time('mysql'),
            'status' => 'answered',
            'ball_in_court' => 'contractor',
            'updated_at' => current_time('mysql')
        );
        
        // Handle cost impact flag from response
        if (isset($data['cost_impact']) && $data['cost_impact']) {
            $update_data['cost_impact'] = 1;
            $update_data['cost_impact_amount'] = floatval($data['cost_impact_amount'] ?? 0);
        }
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        
        // Get job_id for activity log
        $rfi = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$table} WHERE id = %d", $id), ARRAY_A);
        
        // Log response activity
        $this->wpdb->insert($activity_table, array(
            'rfi_id' => $id,
            'job_id' => $rfi['job_id'],
            'activity_type' => 'response',
            'description' => 'RFI response submitted',
            'performed_by' => $current_user_id,
            'performed_by_name' => $user->display_name,
            'performed_at' => current_time('mysql')
        ));
        
        // Create comment for response
        if (isset($data['add_comment']) && $data['add_comment']) {
            $comments_table = $this->tables['rfi_comments'];
            $this->wpdb->insert($comments_table, array(
                'rfi_id' => $id,
                'job_id' => $rfi['job_id'],
                'comment' => sanitize_textarea_field($data['response']),
                'commented_by' => $current_user_id,
                'commented_by_name' => $user->display_name,
                'commented_at' => current_time('mysql')
            ));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * POST /rfi/(?P<id>\d+)/close - Close RFI
     */
    public function close_rfi($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['rfi'];
        $activity_table = $this->tables['rfi_activity'];
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Get current status BEFORE updating
        $rfi = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id, status FROM {$table} WHERE id = %d", $id), ARRAY_A);
        
        if (!$rfi) {
            return new WP_REST_Response(array('success' => false, 'message' => 'RFI not found'), 404);
        }
        
        $old_status = $rfi['status'];
        
        // Get table columns to check which exist
        $columns = $this->wpdb->get_col("DESCRIBE {$table}");
        
        // Build update data only for existing columns
        $update_data = array(
            'status' => 'closed',
            'updated_at' => current_time('mysql')
        );
        
        // Only add closed_by and closed_at if columns exist
        if (in_array('closed_by', $columns)) {
            $update_data['closed_by'] = $current_user_id;
        }
        if (in_array('closed_at', $columns)) {
            $update_data['closed_at'] = current_time('mysql');
        }
        
        // Now update the status
        $result = $this->wpdb->update($table, $update_data, array('id' => $id));
        
        if ($result === false) {
            error_log('[PI_CRM_API] close_rfi database error: ' . $this->wpdb->last_error);
            return new WP_REST_Response(array('success' => false, 'message' => 'Database update failed: ' . $this->wpdb->last_error), 500);
        }
        
        $this->wpdb->insert($activity_table, array(
            'rfi_id' => $id,
            'job_id' => $rfi['job_id'],
            'activity_type' => 'status_change',
            'description' => 'RFI closed',
            'old_value' => $old_status,
            'new_value' => 'closed',
            'performed_by' => $current_user_id,
            'performed_by_name' => $user->display_name,
            'performed_at' => current_time('mysql')
        ));
        
        return new WP_REST_Response(array('success' => true, 'message' => 'RFI closed successfully'), 200);
    }
    
    /**
     * POST /rfi/(?P<id>\d+)/comment - Add comment to RFI
     */
    public function add_rfi_comment($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $comments_table = $this->tables['rfi_comments'];
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Check if table exists, create if not
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$comments_table}'") === $comments_table;
        if (!$table_exists) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $charset_collate = $this->wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$comments_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                rfi_id bigint(20) NOT NULL,
                job_id bigint(20) NOT NULL,
                comment text NOT NULL,
                commented_by bigint(20) DEFAULT NULL,
                commented_by_name varchar(255) DEFAULT NULL,
                parent_id bigint(20) DEFAULT 0,
                attachments text DEFAULT NULL,
                commented_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY rfi_id (rfi_id),
                KEY job_id (job_id)
            ) {$charset_collate};";
            
            dbDelta($sql);
            error_log('[PI_CRM_API] Created missing rfi_comments table: ' . $comments_table);
        }
        
        $rfi = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id FROM {$this->tables['rfi']} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$rfi) {
            return new WP_Error('rfi_not_found', 'RFI not found', array('status' => 404));
        }
        
        // Get table columns to check which exist
        $columns = $this->wpdb->get_col("DESCRIBE {$comments_table}");
        
        // Build insert data only for existing columns
        $insert_data = array(
            'rfi_id' => $id,
            'job_id' => $rfi['job_id'],
            'comment' => sanitize_textarea_field($data['comment']),
            'commented_at' => current_time('mysql')
        );
        
        // Only add optional columns if they exist
        if (in_array('commented_by', $columns)) {
            $insert_data['commented_by'] = $current_user_id;
        }
        if (in_array('commented_by_name', $columns)) {
            $insert_data['commented_by_name'] = $user->display_name;
        }
        if (in_array('parent_id', $columns)) {
            $insert_data['parent_id'] = intval($data['parent_id'] ?? 0);
        }
        if (in_array('attachments', $columns) && isset($data['attachments'])) {
            $insert_data['attachments'] = json_encode($data['attachments']);
        }
        
        $result = $this->wpdb->insert($comments_table, $insert_data);
        
        if ($result === false) {
            error_log('[PI_CRM_API] add_rfi_comment database error: ' . $this->wpdb->last_error);
            return new WP_Error('db_error', 'Failed to insert comment: ' . $this->wpdb->last_error, array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    /**
     * POST /rfi/(?P<id>\d+)/link-task - Link RFI to Task
     */
    public function link_rfi_to_task($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['rfi'];
        
        $this->wpdb->update($table, array(
            'related_task_id' => intval($data['task_id']),
            'updated_at' => current_time('mysql')
        ), array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * GET /submittals - Get submittals list with filtering and metrics
     */
    public function get_submittals($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $status = sanitize_text_field($request->get_param('status') ?? '');
        $type = sanitize_text_field($request->get_param('type') ?? '');
        $subcontractor_id = intval($request->get_param('subcontractor_id') ?? 0);
        $spec_section = sanitize_text_field($request->get_param('spec_section') ?? '');
        $search = sanitize_text_field($request->get_param('search') ?? '');
        $group_by = sanitize_text_field($request->get_param('group_by') ?? '');
        $sort_by = sanitize_text_field($request->get_param('sort_by') ?? 'submitted_at');
        $sort_order = sanitize_text_field($request->get_param('sort_order') ?? 'DESC');
        
        $table = $this->tables['submittals'];
        
        // Use defensive SQL that handles NULL dates - using actual column names from database
        $sql = "SELECT s.*, 
            CASE 
                WHEN s.submitted_at IS NULL THEN 0
                ELSE DATEDIFF(COALESCE(s.responded_at, NOW()), s.submitted_at)
            END as days_in_review,
            CASE 
                WHEN s.due_date < CURDATE() AND s.status NOT IN ('approved', 'rejected', 'closed') THEN 'overdue'
                WHEN s.submitted_at IS NOT NULL AND DATEDIFF(CURDATE(), s.submitted_at) > 14 AND s.status = 'in_review' THEN 'warning'
                ELSE 'normal'
            END as urgency_flag
            FROM {$table} s WHERE s.job_id = %d";
        $params = array($job_id);
        
        if ($status) {
            $sql .= " AND s.status = %s";
            $params[] = $status;
        }
        if ($type) {
            $sql .= " AND s.submittal_type = %s";
            $params[] = $type;
        }
        if ($subcontractor_id) {
            $sql .= " AND s.subcontractor_id = %d";
            $params[] = $subcontractor_id;
        }
        if ($spec_section) {
            $sql .= " AND s.specification_section LIKE %s";
            $params[] = '%' . $spec_section . '%';
        }
        if ($search) {
            $sql .= " AND (s.title LIKE %s OR s.description LIKE %s OR s.submittal_number LIKE %s)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        
        $allowed_sort = array('submitted_at', 'due_date', 'submittal_number', 'status', 'submittal_type');
        if (!in_array($sort_by, $allowed_sort)) {
            $sort_by = 'submitted_at';
        }
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY s.{$sort_by} {$sort_order}";
        
        error_log('[PI_CRM_API] get_submittals SQL: ' . $sql);
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
        
        // Check for database errors
        if ($this->wpdb->last_error) {
            error_log('[PI_CRM_API] get_submittals DATABASE ERROR: ' . $this->wpdb->last_error);
        }
        
        // Ensure results is an array (get_results can return null on error)
        if (!is_array($results)) {
            $results = array();
        }
        
        // Calculate metrics
        $metrics = $this->calculate_submittal_metrics($job_id);
        
        // Group if requested
        $grouped = null;
        if ($group_by === 'spec_division') {
            $grouped = array();
            foreach ($results as $item) {
                $division = $item['specification_division'] ?: 'Uncategorized';
                if (!isset($grouped[$division])) {
                    $grouped[$division] = array('division' => $division, 'items' => array());
                }
                $grouped[$division]['items'][] = $item;
            }
            $grouped = array_values($grouped);
        } elseif ($group_by === 'subcontractor') {
            $grouped = array();
            foreach ($results as $item) {
                $sub = $item['subcontractor_name'] ?: 'Unknown';
                if (!isset($grouped[$sub])) {
                    $grouped[$sub] = array('subcontractor' => $sub, 'items' => array());
                }
                $grouped[$sub]['items'][] = $item;
            }
            $grouped = array_values($grouped);
        }
        
        return new WP_REST_Response(array(
            'data' => $results,
            'grouped' => $grouped,
            'metrics' => $metrics,
            'count' => count($results)
        ), 200);
    }
    
    /**
     * Calculate Submittal metrics for dashboard
     */
    private function calculate_submittal_metrics($job_id) {
        $table = $this->tables['submittals'];
        
        $metrics = array(
            'total_pending' => 0,
            'overdue' => 0,
            'approved_this_week' => 0,
            'in_review' => 0,
            'requires_resubmission' => 0,
            'avg_turnaround_days' => 0
        );
        
        // Pending (submitted or in_review)
        $metrics['total_pending'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND status IN ('submitted', 'in_review', 'draft')",
            $job_id
        )));
        
        // Overdue
        $metrics['overdue'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND due_date < CURDATE() AND status NOT IN ('approved', 'rejected', 'closed')",
            $job_id
        )));
        
        // Approved this week (using responded_at as the approval/completion date)
        $metrics['approved_this_week'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND status = 'approved' AND responded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $job_id
        )));
        
        // In review
        $metrics['in_review'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND status = 'in_review'",
            $job_id
        )));
        
        // Requires resubmission
        $metrics['requires_resubmission'] = intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND status = 'revise_resubmit'",
            $job_id
        )));
        
        // Average turnaround for approved submittals (using responded_at as completion date)
        $avg_days = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(DATEDIFF(responded_at, submitted_at)) 
             FROM {$table} WHERE job_id = %d AND status = 'approved' AND responded_at IS NOT NULL",
            $job_id
        ));
        $metrics['avg_turnaround_days'] = $avg_days ? round(floatval($avg_days), 1) : 0;
        
        return $metrics;
    }
    
    /**
     * GET /submittals/(?P<id>\d+) - Get single submittal with full details
     */
    public function get_single_submittal($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['submittals'];
        $revisions_table = $this->tables['submittal_revisions'];
        $reviews_table = $this->tables['submittal_reviews'];
        $activity_table = $this->tables['submittal_activity'];
        
        $submittal = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT s.*, u.display_name as submitted_by_name
            FROM {$table} s
            LEFT JOIN {$this->wpdb->users} u ON s.submitted_by = u.ID
            WHERE s.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$submittal) {
            return new WP_Error('submittal_not_found', 'Submittal not found', array('status' => 404));
        }
        
        // Get revision history
        $revisions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$revisions_table} WHERE submittal_id = %d ORDER BY revision_number DESC",
            $id
        ), ARRAY_A);
        
        // Get reviews
        $reviews = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.*, u.display_name as reviewer_name, u.user_email as reviewer_email
            FROM {$reviews_table} r
            LEFT JOIN {$this->wpdb->users} u ON r.reviewer_id = u.ID
            WHERE r.submittal_id = %d ORDER BY r.reviewed_at DESC",
            $id
        ), ARRAY_A);
        
        // Get activity log
        $activity = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$activity_table} WHERE submittal_id = %d ORDER BY performed_at DESC",
            $id
        ), ARRAY_A);
        
        return new WP_REST_Response(array(
            'submittal' => $submittal,
            'revisions' => $revisions,
            'reviews' => $reviews,
            'activity_log' => $activity
        ), 200);
    }
    
    /**
     * POST /submittals - Create new submittal with auto-numbering
     */
    public function create_submittal($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (empty($data['job_id'])) {
            return new WP_Error('missing_job_id', 'Job ID is required', array('status' => 400));
        }
        if (empty($data['title'])) {
            return new WP_Error('missing_title', 'Title is required', array('status' => 400));
        }
        
        $table = $this->tables['submittals'];
        $activity_table = $this->tables['submittal_activity'];
        
        $job_id = intval($data['job_id']);
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Get table columns first
        $columns = $this->wpdb->get_col("DESCRIBE {$table}");
        
        // Generate unique submittal number using timestamp + random to avoid collisions
        $timestamp = date('YmdHis');
        $random = mt_rand(100, 999);
        $submittal_number = 'SUB-' . $timestamp . '-' . $random;
        
        // Verify it's truly unique
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE submittal_number = %s",
            $submittal_number
        ));
        
        if ($exists) {
            // Retry with different random
            $random = mt_rand(1000, 9999);
            $submittal_number = 'SUB-' . $timestamp . '-' . $random;
        }
        
        // Build insert data only for existing columns
        $insert_data = array(
            'job_id' => $job_id,
            'submittal_number' => $submittal_number,
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        // Only add optional fields if they exist in table
        // Note: Using actual database column names
        $optional_fields = array(
            'submittal_type' => sanitize_text_field($data['submittal_type'] ?? 'product_data'),
            'specification_division' => sanitize_text_field($data['specification_division'] ?? ''),
            'specification_section' => sanitize_text_field($data['specification_section'] ?? ''),
            'specification_title' => sanitize_text_field($data['specification_title'] ?? ''),
            'subcontractor_id' => intval($data['subcontractor_id'] ?? 0),
            'subcontractor_name' => sanitize_text_field($data['subcontractor_name'] ?? ''),
            'submitted_by' => $current_user_id,
            'submitted_by_name' => $user->display_name,
            'required_approver' => sanitize_text_field($data['required_approver'] ?? ''),  // Single approver field
            'due_date' => sanitize_text_field($data['due_date'] ?? null),  // Correct column name
            'status' => sanitize_text_field($data['status'] ?? 'draft'),
            'priority' => sanitize_text_field($data['priority'] ?? 'normal'),
            'revision_number' => '0',
            'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : null,
            'ball_in_court' => sanitize_text_field($data['ball_in_court'] ?? 'subcontractor'),
            'submitted_at' => current_time('mysql'),
        );
        
        foreach ($optional_fields as $field => $value) {
            if (in_array($field, $columns)) {
                $insert_data[$field] = $value;
            }
        }
        
        $result = $this->wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            error_log('[PI_CRM_API] create_submittal database error: ' . $this->wpdb->last_error);
            return new WP_Error('insert_failed', 'Failed to create submittal: ' . $this->wpdb->last_error, array('status' => 500));
        }
        
        $submittal_id = $this->wpdb->insert_id;
        
        // Create initial revision
        $revisions_table = $this->tables['submittal_revisions'];
        $this->wpdb->insert($revisions_table, array(
            'submittal_id' => $submittal_id,
            'job_id' => $job_id,
            'revision_number' => '0',
            'title' => $insert_data['title'],
            'description' => $insert_data['description'],
            'attachments' => $insert_data['attachments'],
            'submitted_by' => $current_user_id,
            'submitted_by_name' => $user->display_name,
            'submitted_at' => current_time('mysql'),
            'change_description' => 'Initial submission'
        ));
        
        // Log activity
        $this->wpdb->insert($activity_table, array(
            'submittal_id' => $submittal_id,
            'job_id' => $job_id,
            'activity_type' => 'created',
            'description' => 'Submittal created',
            'performed_by' => $current_user_id,
            'performed_by_name' => $user->display_name,
            'performed_at' => current_time('mysql')
        ));
        
        return new WP_REST_Response(array(
            'id' => $submittal_id,
            'submittal_number' => $submittal_number,
            'success' => true
        ), 201);
    }
    
    /**
     * PATCH /submittals/(?P<id>\d+) - Update submittal
     */
    public function update_submittal($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['submittals'];
        $activity_table = $this->tables['submittal_activity'];
        
        $current_submittal = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$current_submittal) {
            return new WP_Error('submittal_not_found', 'Submittal not found', array('status' => 404));
        }
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        $job_id = $current_submittal['job_id'];
        
        $update_data = array();
        $activity_logs = array();
        
        $fields_to_track = array(
            'title', 'description', 'submittal_type', 'specification_section',
            'priority', 'status', 'due_date', 'ball_in_court',
            'subcontractor_id', 'subcontractor_name', 'required_approver'
        );
        
        foreach ($fields_to_track as $field) {
            if (isset($data[$field])) {
                $new_value = is_string($data[$field]) ? sanitize_text_field($data[$field]) : $data[$field];
                if ($current_submittal[$field] != $new_value) {
                    $update_data[$field] = $new_value;
                    $activity_logs[] = array(
                        'submittal_id' => $id,
                        'job_id' => $job_id,
                        'activity_type' => $field === 'status' ? 'status_change' : 'field_update',
                        'description' => ucfirst(str_replace('_', ' ', $field)) . ' updated',
                        'old_value' => $current_submittal[$field],
                        'new_value' => $new_value,
                        'performed_by' => $current_user_id,
                        'performed_by_name' => $user->display_name,
                        'performed_at' => current_time('mysql')
                    );
                }
            }
        }
        
        if (isset($data['required_approvers'])) {
            $update_data['required_approvers'] = is_array($data['required_approvers']) ? 
                json_encode($data['required_approvers']) : sanitize_text_field($data['required_approvers']);
        }
        
        if (isset($data['attachments'])) {
            $update_data['attachments'] = is_array($data['attachments']) ? 
                json_encode($data['attachments']) : sanitize_text_field($data['attachments']);
        }
        
        if (!empty($update_data)) {
            $update_data['updated_at'] = current_time('mysql');
            $this->wpdb->update($table, $update_data, array('id' => $id));
            
            foreach ($activity_logs as $log) {
                $this->wpdb->insert($activity_table, $log);
            }
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * POST /submittals/(?P<id>\d+)/review - Submit review for submittal
     */
    public function review_submittal($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['submittals'];
        $reviews_table = $this->tables['submittal_reviews'];
        $activity_table = $this->tables['submittal_activity'];
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $submittal = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id, status FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$submittal) {
            return new WP_Error('submittal_not_found', 'Submittal not found', array('status' => 404));
        }
        
        $decision = sanitize_text_field($data['decision']);
        $allowed_decisions = array('approved', 'approved_as_noted', 'revise_resubmit', 'rejected');
        
        if (!in_array($decision, $allowed_decisions)) {
            return new WP_Error('invalid_decision', 'Invalid review decision', array('status' => 400));
        }
        
        // Add review record
        $this->wpdb->insert($reviews_table, array(
            'submittal_id' => $id,
            'job_id' => $submittal['job_id'],
            'reviewer_id' => $current_user_id,
            'reviewer_name' => $user->display_name,
            'reviewer_role' => sanitize_text_field($data['reviewer_role'] ?? ''),
            'decision' => $decision,
            'comments' => sanitize_textarea_field($data['comments'] ?? ''),
            'pdf_annotations' => isset($data['pdf_annotations']) ? json_encode($data['pdf_annotations']) : null,
            'reviewed_at' => current_time('mysql')
        ));
        
        // Update submittal status
        $new_status = $decision === 'revise_resubmit' ? 'revise_resubmit' : 
                     ($decision === 'rejected' ? 'rejected' : 'approved');
        
        $update_data = array(
            'status' => $new_status,
            'review_notes' => sanitize_textarea_field($data['comments'] ?? ''),
            'decision' => $decision,
            'updated_at' => current_time('mysql')
        );
        
        if ($new_status === 'approved') {
            $update_data['approver_id'] = $current_user_id;  // Using actual column name
            $update_data['responded_at'] = current_time('mysql');  // Using actual column name
        }
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        
        // Log activity
        $this->wpdb->insert($activity_table, array(
            'submittal_id' => $id,
            'job_id' => $submittal['job_id'],
            'activity_type' => 'reviewed',
            'description' => "Review submitted: {$decision}",
            'old_value' => $submittal['status'],
            'new_value' => $new_status,
            'performed_by' => $current_user_id,
            'performed_by_name' => $user->display_name,
            'performed_at' => current_time('mysql')
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'decision' => $decision,
            'new_status' => $new_status
        ), 200);
    }
    
    /**
     * POST /submittals/(?P<id>\d+)/resubmit - Resubmit with new revision
     */
    public function resubmit_submittal($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['submittals'];
        $revisions_table = $this->tables['submittal_revisions'];
        $activity_table = $this->tables['submittal_activity'];
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $submittal = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$submittal) {
            return new WP_Error('submittal_not_found', 'Submittal not found', array('status' => 404));
        }
        
        // Increment revision number
        $current_rev = intval($submittal['revision_number']);
        $new_rev = $current_rev + 1;
        
        // Create new revision record
        $this->wpdb->insert($revisions_table, array(
            'submittal_id' => $id,
            'job_id' => $submittal['job_id'],
            'revision_number' => strval($new_rev),
            'title' => sanitize_text_field($data['title'] ?? $submittal['title']),
            'description' => sanitize_textarea_field($data['description'] ?? $submittal['description']),
            'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : $submittal['attachments'],
            'submitted_by' => $current_user_id,
            'submitted_by_name' => $user->display_name,
            'submitted_at' => current_time('mysql'),
            'change_description' => sanitize_textarea_field($data['change_description'] ?? 'Resubmitted for review')
        ));
        
        // Update submittal
        $this->wpdb->update($table, array(
            'revision_number' => strval($new_rev),
            'status' => 'submitted',
            'title' => sanitize_text_field($data['title'] ?? $submittal['title']),
            'description' => sanitize_textarea_field($data['description'] ?? $submittal['description']),
            'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : $submittal['attachments'],
            'ball_in_court' => 'architect',
            'updated_at' => current_time('mysql')
        ), array('id' => $id));
        
        // Log activity
        $this->wpdb->insert($activity_table, array(
            'submittal_id' => $id,
            'job_id' => $submittal['job_id'],
            'activity_type' => 'resubmitted',
            'description' => "Revision {$new_rev} submitted",
            'old_value' => "Rev {$current_rev}",
            'new_value' => "Rev {$new_rev}",
            'performed_by' => $current_user_id,
            'performed_by_name' => $user->display_name,
            'performed_at' => current_time('mysql')
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'revision_number' => $new_rev
        ), 200);
    }
    
    /**
     * DELETE /rfi/(?P<id>\d+) - Delete RFI
     */
    public function delete_rfi($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['rfi'];
        $comments_table = $this->tables['rfi_comments'];
        $activity_table = $this->tables['rfi_activity'];
        
        // Delete related records first
        $this->wpdb->delete($comments_table, array('rfi_id' => $id));
        $this->wpdb->delete($activity_table, array('rfi_id' => $id));
        
        // Delete RFI
        $result = $this->wpdb->delete($table, array('id' => $id));
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete RFI', array('status' => 500));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * POST /submittals/(?P<id>\d+)/close - Close Submittal
     */
    public function close_submittal($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['submittals'];
        $activity_table = $this->tables['submittal_activity'];
        
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $submittal = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT job_id, status FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$submittal) {
            return new WP_Error('submittal_not_found', 'Submittal not found', array('status' => 404));
        }
        
        $this->wpdb->update($table, array(
            'status' => 'closed',
            'updated_at' => current_time('mysql')
        ), array('id' => $id));
        
        $this->wpdb->insert($activity_table, array(
            'submittal_id' => $id,
            'job_id' => $submittal['job_id'],
            'activity_type' => 'status_change',
            'description' => 'Submittal closed',
            'old_value' => $submittal['status'],
            'new_value' => 'closed',
            'performed_by' => $current_user_id,
            'performed_by_name' => $user->display_name,
            'performed_at' => current_time('mysql')
        ));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * DELETE /submittals/(?P<id>\d+) - Delete Submittal
     */
    public function delete_submittal($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['submittals'];
        $revisions_table = $this->tables['submittal_revisions'];
        $reviews_table = $this->tables['submittal_reviews'];
        $activity_table = $this->tables['submittal_activity'];
        
        // Delete related records first
        $this->wpdb->delete($revisions_table, array('submittal_id' => $id));
        $this->wpdb->delete($reviews_table, array('submittal_id' => $id));
        $this->wpdb->delete($activity_table, array('submittal_id' => $id));
        
        // Delete Submittal
        $result = $this->wpdb->delete($table, array('id' => $id));
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete submittal', array('status' => 500));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * GET /rfi-submittals/dashboard - Combined dashboard data
     */
    public function get_rfi_submittals_dashboard($request) {
        $job_id = intval($request->get_param('job_id'));
        
        $rfi_metrics = $this->calculate_rfi_metrics($job_id);
        $submittal_metrics = $this->calculate_submittal_metrics($job_id);
        
        // Get recent activity for both
        $recent_rfi = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, rfi_number as number, title, status, priority, requested_at as date, 'rfi' as type
            FROM {$this->tables['rfi']} WHERE job_id = %d 
            ORDER BY requested_at DESC LIMIT 5",
            $job_id
        ), ARRAY_A);
        
        $recent_submittals = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, submittal_number as number, title, status, submittal_type as type, submitted_at as date, 'submittal' as type
            FROM {$this->tables['submittals']} WHERE job_id = %d 
            ORDER BY submitted_at DESC LIMIT 5",
            $job_id
        ), ARRAY_A);
        
        $recent_activity = array_merge($recent_rfi, $recent_submittals);
        usort($recent_activity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        $recent_activity = array_slice($recent_activity, 0, 10);
        
        return new WP_REST_Response(array(
            'rfi_metrics' => $rfi_metrics,
            'submittal_metrics' => $submittal_metrics,
            'combined_metrics' => array(
                'total_items_open' => $rfi_metrics['total_open'] + $submittal_metrics['total_pending'],
                'total_overdue' => $rfi_metrics['overdue'] + $submittal_metrics['overdue'],
                'total_resolved_this_week' => $rfi_metrics['answered_this_week'] + $submittal_metrics['approved_this_week']
            ),
            'recent_activity' => $recent_activity
        ), 200);
    }
    
    /**
     * POST /rfi/bulk-action - Bulk actions on RFIs
     */
    public function bulk_rfi_action($request) {
        $data = $request->get_json_params();
        $ids = isset($data['ids']) ? array_map('intval', $data['ids']) : array();
        $action = sanitize_text_field($data['action'] ?? '');
        
        if (empty($ids) || empty($action)) {
            return new WP_Error('invalid_params', 'Invalid parameters', array('status' => 400));
        }
        
        $table = $this->tables['rfi'];
        $activity_table = $this->tables['rfi_activity'];
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        switch ($action) {
            case 'send_reminder':
                // Log reminder activity for each RFI
                foreach ($ids as $rfi_id) {
                    $rfi = $this->wpdb->get_row($this->wpdb->prepare(
                        "SELECT job_id FROM {$table} WHERE id = %d",
                        $rfi_id
                    ), ARRAY_A);
                    if ($rfi) {
                        $this->wpdb->insert($activity_table, array(
                            'rfi_id' => $rfi_id,
                            'job_id' => $rfi['job_id'],
                            'activity_type' => 'reminder_sent',
                            'description' => 'Reminder sent',
                            'performed_by' => $current_user_id,
                            'performed_by_name' => $user->display_name,
                            'performed_at' => current_time('mysql')
                        ));
                    }
                }
                break;
                
            case 'close':
                $this->wpdb->query($this->wpdb->prepare(
                    "UPDATE {$table} SET status = 'closed', closed_at = NOW(), updated_at = NOW() 
                     WHERE id IN ($placeholders)",
                    ...$ids
                ));
                break;
                
            case 'change_status':
                $new_status = sanitize_text_field($data['new_status'] ?? '');
                if ($new_status) {
                    $this->wpdb->query($this->wpdb->prepare(
                        "UPDATE {$table} SET status = %s, updated_at = NOW() 
                         WHERE id IN ($placeholders)",
                        array_merge(array($new_status), $ids)
                    ));
                }
                break;
                
            case 'delete':
                $comments_table = $this->tables['rfi_comments'];
                foreach ($ids as $rfi_id) {
                    // Delete related records first
                    $this->wpdb->delete($comments_table, array('rfi_id' => $rfi_id));
                    $this->wpdb->delete($activity_table, array('rfi_id' => $rfi_id));
                    // Delete RFI
                    $this->wpdb->delete($table, array('id' => $rfi_id));
                }
                break;
        }
        
        return new WP_REST_Response(array('success' => true, 'affected' => count($ids)), 200);
    }
    
    /**
     * POST /submittals/bulk-action - Bulk actions on Submittals
     */
    public function bulk_submittal_action($request) {
        $data = $request->get_json_params();
        $ids = isset($data['ids']) ? array_map('intval', $data['ids']) : array();
        $action = sanitize_text_field($data['action'] ?? '');
        
        if (empty($ids) || empty($action)) {
            return new WP_Error('invalid_params', 'Invalid parameters', array('status' => 400));
        }
        
        $table = $this->tables['submittals'];
        $activity_table = $this->tables['submittal_activity'];
        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        switch ($action) {
            case 'send_reminder':
                foreach ($ids as $sub_id) {
                    $sub = $this->wpdb->get_row($this->wpdb->prepare(
                        "SELECT job_id FROM {$table} WHERE id = %d",
                        $sub_id
                    ), ARRAY_A);
                    if ($sub) {
                        $this->wpdb->insert($activity_table, array(
                            'submittal_id' => $sub_id,
                            'job_id' => $sub['job_id'],
                            'activity_type' => 'reminder_sent',
                            'description' => 'Reminder sent',
                            'performed_by' => $current_user_id,
                            'performed_by_name' => $user->display_name,
                            'performed_at' => current_time('mysql')
                        ));
                    }
                }
                break;
                
            case 'close':
                $this->wpdb->query($this->wpdb->prepare(
                    "UPDATE {$table} SET status = 'closed', updated_at = NOW() 
                     WHERE id IN ($placeholders)",
                    ...$ids
                ));
                break;
                
            case 'change_status':
                $new_status = sanitize_text_field($data['new_status'] ?? '');
                if ($new_status) {
                    $this->wpdb->query($this->wpdb->prepare(
                        "UPDATE {$table} SET status = %s, updated_at = NOW() 
                         WHERE id IN ($placeholders)",
                        array_merge(array($new_status), $ids)
                    ));
                }
                break;
                
            case 'delete':
                $revisions_table = $this->tables['submittal_revisions'];
                $reviews_table = $this->tables['submittal_reviews'];
                foreach ($ids as $sub_id) {
                    // Delete related records first
                    $this->wpdb->delete($revisions_table, array('submittal_id' => $sub_id));
                    $this->wpdb->delete($reviews_table, array('submittal_id' => $sub_id));
                    $this->wpdb->delete($activity_table, array('submittal_id' => $sub_id));
                    // Delete Submittal
                    $this->wpdb->delete($table, array('id' => $sub_id));
                }
                break;
        }
        
        return new WP_REST_Response(array('success' => true, 'affected' => count($ids)), 200);
    }
    
    /**
     * GET /rfi/export - Export RFI log to CSV
     */
    public function export_rfi_log($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->tables['rfi'];
        
        $items = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT rfi_number, title, description, status, priority, requested_by_name, 
                    requested_at, due_date, responded_at, ball_in_court, cost_impact, schedule_impact
             FROM {$table} WHERE job_id = %d ORDER BY requested_at DESC",
            $job_id
        ), ARRAY_A);
        
        // Generate CSV
        $filename = 'rfi_log_' . $job_id . '_' . date('Y-m-d') . '.csv';
        $headers = array('RFI Number', 'Title', 'Description', 'Status', 'Priority', 'Requested By', 
                        'Date Requested', 'Due Date', 'Date Answered', 'Ball in Court', 'Cost Impact', 'Schedule Impact');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        
        foreach ($items as $item) {
            fputcsv($output, array(
                $item['rfi_number'],
                $item['title'],
                $item['description'],
                $item['status'],
                $item['priority'],
                $item['requested_by_name'],
                $item['requested_at'],
                $item['due_date'],
                $item['responded_at'],
                $item['ball_in_court'],
                $item['cost_impact'] ? 'Yes' : 'No',
                $item['schedule_impact'] ? 'Yes' : 'No'
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * GET /submittals/export - Export Submittal log to CSV
     */
    public function export_submittal_log($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->tables['submittals'];
        
        $items = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT submittal_number, title, description, submittal_type, specification_section,
                    subcontractor_name, status, submitted_at, due_date, responded_at, 
                    revision_number, ball_in_court
             FROM {$table} WHERE job_id = %d ORDER BY submitted_at DESC",
            $job_id
        ), ARRAY_A);
        
        $filename = 'submittal_log_' . $job_id . '_' . date('Y-m-d') . '.csv';
        $headers = array('Submittal Number', 'Title', 'Description', 'Type', 'Spec Section',
                        'Subcontractor', 'Status', 'Date Submitted', 'Review Due', 'Date Approved', 
                        'Revision', 'Ball in Court');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        
        foreach ($items as $item) {
            fputcsv($output, array(
                $item['submittal_number'],
                $item['title'],
                $item['description'],
                $item['submittal_type'],
                $item['specification_section'],
                $item['subcontractor_name'],
                $item['status'],
                $item['submitted_at'],
                $item['due_date'],
                $item['responded_at'],
                $item['revision_number'],
                $item['ball_in_court']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * GET /rfi/reports/dashboard - RFI Dashboard Report
     */
    public function get_rfi_dashboard_report($request) {
        $job_id = intval($request->get_param('job_id'));
        
        $metrics = $this->calculate_rfi_metrics($job_id);
        
        // Response time by responder
        $response_times = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT responded_by_name, 
                    COUNT(*) as count, 
                    AVG(DATEDIFF(responded_at, requested_at)) as avg_days
             FROM {$this->tables['rfi']} 
             WHERE job_id = %d AND status IN ('answered', 'closed') AND responded_at IS NOT NULL
             GROUP BY responded_by_name",
            $job_id
        ), ARRAY_A);
        
        // Volume trends (last 12 weeks)
        $trends = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT YEARWEEK(requested_at) as week, 
                    COUNT(*) as raised,
                    SUM(CASE WHEN status IN ('answered', 'closed') THEN 1 ELSE 0 END) as answered
             FROM {$this->tables['rfi']} 
             WHERE job_id = %d AND requested_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
             GROUP BY YEARWEEK(requested_at)
             ORDER BY week",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response(array(
            'metrics' => $metrics,
            'response_times' => $response_times,
            'trends' => $trends
        ), 200);
    }
    
    /**
     * GET /submittals/reports/dashboard - Submittal Dashboard Report
     */
    public function get_submittal_dashboard_report($request) {
        $job_id = intval($request->get_param('job_id'));
        
        $metrics = $this->calculate_submittal_metrics($job_id);
        
        // Turnaround time calculation (by submitted_by_name since approved_by fields don't exist)
        $turnaround = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT submitted_by_name as reviewer, 
                    COUNT(*) as count, 
                    AVG(DATEDIFF(responded_at, submitted_at)) as avg_days
             FROM {$this->tables['submittals']} 
             WHERE job_id = %d AND status = 'approved' AND responded_at IS NOT NULL
             GROUP BY submitted_by_name",
            $job_id
        ), ARRAY_A);
        
        // Approval rate (first time vs revisions)
        $first_time_approvals = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['submittals']} 
             WHERE job_id = %d AND status = 'approved' AND revision_number = '0'",
            $job_id
        ));
        
        $total_approved = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['submittals']} 
             WHERE job_id = %d AND status = 'approved'",
            $job_id
        ));
        
        $approval_rate = $total_approved > 0 ? round(($first_time_approvals / $total_approved) * 100, 1) : 0;
        
        return new WP_REST_Response(array(
            'metrics' => $metrics,
            'turnaround_by_reviewer' => $turnaround,
            'first_time_approval_rate' => $approval_rate
        ), 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Site Location
    // ============================================
    
    public function get_site_location($request) {
        $job_id = intval($request->get_param('job_id'));
        $table = $this->tables['site_locations'];

        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d",
            $job_id
        ), ARRAY_A);

        if ($result) {
            return new WP_REST_Response($result, 200);
        }

        // Fallback: Check job post meta for site address
        $site_address = get_post_meta($job_id, '_pi_job_site_address', true);
        if ($site_address) {
            return new WP_REST_Response(array(
                'address' => $site_address,
                'city' => '',
                'postcode' => '',
                'latitude' => null,
                'longitude' => null,
                'what3words' => '',
                'access_instructions' => '',
                'parking_info' => '',
                'job_id' => $job_id
            ), 200);
        }

        return new WP_REST_Response(null, 404);
    }
    
    public function update_site_location($request) {
        $job_id = intval($request->get_param('job_id'));
        $data = $request->get_json_params();
        $table = $this->tables['site_locations'];
        
        $update_data = array(
            'address' => sanitize_textarea_field($data['address']),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'postcode' => sanitize_text_field($data['postcode'] ?? ''),
            'latitude' => floatval($data['latitude'] ?? 0),
            'longitude' => floatval($data['longitude'] ?? 0),
            'what3words' => sanitize_text_field($data['what3words'] ?? ''),
            'access_instructions' => sanitize_textarea_field($data['access_instructions'] ?? ''),
            'parking_info' => sanitize_textarea_field($data['parking_info'] ?? ''),
            'site_contact_name' => sanitize_text_field($data['site_contact_name'] ?? ''),
            'site_contact_phone' => sanitize_text_field($data['site_contact_phone'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE job_id = %d",
            $job_id
        ));
        
        if ($exists) {
            $this->wpdb->update($table, $update_data, array('job_id' => $job_id));
        } else {
            $update_data['job_id'] = $job_id;
            $update_data['created_at'] = current_time('mysql');
            $this->wpdb->insert($table, $update_data);
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_all_sites($request) {
        $table = $this->tables['site_locations'];
        
        $results = $this->wpdb->get_results(
            "SELECT sl.*, p.post_title as job_title, p.post_status as job_status 
            FROM {$table} sl 
            JOIN {$this->wpdb->posts} p ON sl.job_id = p.ID 
            WHERE sl.latitude IS NOT NULL AND sl.longitude IS NOT NULL",
            ARRAY_A
        );
        
        return new WP_REST_Response($results, 200);
    }
    
    public function geocode_address($request) {
        $data = $request->get_json_params();
        $address = sanitize_text_field($data['address']);

        // Use Mapbox Geocoding API (consistent with frontend)
        $mapbox_token = 'pk.eyJ1IjoicGxhbm5pbmdpbmRleCIsImEiOiJjbWs4ZnZ6MGUxOWg1M2NyNW9xbnZodWx3In0.SOAFHPon69-aJS2G6qAoBQ';
        $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode($address) . '.json?access_token=' . $mapbox_token . '&country=GB&limit=1';

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return new WP_Error('geocode_failed', 'Failed to geocode address: ' . $response->get_error_message(), array('status' => 500));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('geocode_failed', 'Geocoding service returned error: ' . $status_code, array('status' => 500));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['features'][0])) {
            return new WP_Error('no_results', 'No results found for this address', array('status' => 404));
        }

        $feature = $body['features'][0];
        $context = $feature['context'] ?? array();

        // Extract city and postcode from context
        $city = '';
        $postcode = '';
        foreach ($context as $ctx) {
            if (strpos($ctx['id'], 'place') === 0) {
                $city = $ctx['text'];
            }
            if (strpos($ctx['id'], 'postcode') === 0) {
                $postcode = $ctx['text'];
            }
        }

        return new WP_REST_Response(array(
            'latitude' => floatval($feature['center'][1]),
            'longitude' => floatval($feature['center'][0]),
            'display_name' => $feature['place_name'],
            'city' => $city,
            'postcode' => $postcode
        ), 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Team
    // ============================================
    
    public function get_team_assignments($request) {
        $job_id = intval($request->get_param('job_id') ?? $request->get_param('id'));
        $table = $this->tables['team_assignments'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT ta.*, u.display_name, u.user_email 
            FROM {$table} ta 
            LEFT JOIN {$this->wpdb->users} u ON ta.user_id = u.ID 
            WHERE ta.job_id = %d AND ta.status = 'active'
            ORDER BY ta.is_lead DESC, ta.role",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    public function assign_team_member($request) {
        $data = $request->get_json_params();
        $table = $this->tables['employees'];
        
        // Validate required fields
        if (empty($data['employee_id'])) {
            return new WP_REST_Response(array('error' => 'Missing required field: employee_id'), 400);
        }
        
        $employee_id = intval($data['employee_id']);
        $job_id = !empty($data['job_id']) ? intval($data['job_id']) : null;
        
        // Ensure table exists
        $this->maybe_create_employees_table();
        
        // Check if employee exists
        $employee = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $employee_id
        ), ARRAY_A);
        
        if (!$employee) {
            return new WP_REST_Response(array('error' => 'Employee not found'), 404);
        }
        
        // Update the employee's job_id
        $result = $this->wpdb->update($table, array(
            'job_id' => $job_id,
            'updated_at' => current_time('mysql')
        ), array('id' => $employee_id));
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'error' => 'Failed to assign employee to job',
                'details' => $this->wpdb->last_error
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Employee assigned to job successfully',
            'employee_id' => $employee_id,
            'job_id' => $job_id
        ), 200);
    }
    
    public function get_available_workers($request) {
        // Get all users with construction-related roles
        $users = get_users(array(
            'role__in' => array('administrator', 'editor', 'author', 'contributor'),
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        // Get their certifications
        $cert_table = $this->tables['certifications'];
        $workers = array();
        
        foreach ($users as $user) {
            $certs = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT certification_name, expiry_date, status 
                FROM {$cert_table} 
                WHERE user_id = %d AND status = 'valid' AND expiry_date > CURDATE()",
                $user->ID
            ), ARRAY_A);
            
            $workers[] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'certifications' => $certs
            );
        }
        
        return new WP_REST_Response($workers, 200);
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Weather
    // ============================================
    
    public function get_weather($request) {
        $job_id = intval($request->get_param('job_id'));
        $days = intval($request->get_param('days') ?? 7);
        $table = $this->tables['weather_data'];

        // Check if we have recent data
        $recent = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(fetched_at) FROM {$table} WHERE job_id = %d AND forecast_date >= CURDATE()",
            $job_id
        ));

        $fetch_success = true;
        $location_error = null;

        // If data is older than 6 hours, fetch new data
        if (!$recent || strtotime($recent) < strtotime('-6 hours')) {
            $fetch_success = $this->fetch_weather_for_job($job_id);
            if (!$fetch_success) {
                // Check why it failed
                $site_address = get_post_meta($job_id, '_pi_job_site_address', true);
                if (!$site_address) {
                    $location_error = 'No site address set for this job';
                } else {
                    $location_error = 'Could not determine coordinates from address. Please set location on Site Map tab.';
                }
            }
        }

        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE job_id = %d AND forecast_date >= CURDATE()
            ORDER BY forecast_date
            LIMIT %d",
            $job_id, $days
        ), ARRAY_A);

        // If no results and fetch failed, return error info
        if (empty($results) && !$fetch_success) {
            return new WP_REST_Response(array(
                'error' => true,
                'message' => $location_error ?? 'Unable to fetch weather data',
                'has_address' => !empty(get_post_meta($job_id, '_pi_job_site_address', true)),
                'data' => array()
            ), 200);
        }

        return new WP_REST_Response($results, 200);
    }
    
    public function fetch_weather_data($request) {
        $job_id = intval($request->get_param('job_id'));
        $success = $this->fetch_weather_for_job($job_id);
        
        return new WP_REST_Response(array('success' => $success), 200);
    }
    
    private function fetch_weather_for_job($job_id) {
        // Get site location with coordinates
        $site_table = $this->tables['site_locations'];
        $location = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT latitude, longitude, address FROM {$site_table} WHERE job_id = %d",
            $job_id
        ));

        $lat = null;
        $lng = null;

        // If we have stored coordinates, use them
        if ($location && $location->latitude && $location->longitude) {
            $lat = $location->latitude;
            $lng = $location->longitude;
        } else {
            // Try to geocode from job address
            $site_address = get_post_meta($job_id, '_pi_job_site_address', true);
            if ($site_address) {
                $coords = $this->geocode_address_for_weather($site_address);
                if ($coords) {
                    $lat = $coords['latitude'];
                    $lng = $coords['longitude'];

                    // Save coordinates for future use
                    $this->wpdb->replace($site_table, array(
                        'job_id' => $job_id,
                        'address' => $site_address,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ), array('job_id'));
                }
            }
        }

        if (!$lat || !$lng) {
            error_log("Weather fetch failed for job {$job_id}: No coordinates available");
            return false;
        }

        // Use Open-Meteo API (free, no API key required)
        // Added weather_code parameter for better condition descriptions
        $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lng}&daily=temperature_2m_max,temperature_2m_min,precipitation_sum,windspeed_10m_max,weather_code&timezone=Europe/London&forecast_days=14";

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            error_log("Weather API error for job {$job_id}: " . $response->get_error_message());
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            error_log("Weather API returned status {$status} for job {$job_id}");
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['daily'])) {
            error_log("Weather API returned no daily data for job {$job_id}");
            return false;
        }

        $table = $this->tables['weather_data'];
        $daily = $data['daily'];

        // Clear old forecasts
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$table} WHERE job_id = %d AND forecast_date >= CURDATE()",
            $job_id
        ));

        // Insert new forecasts
        for ($i = 0; $i < count($daily['time']); $i++) {
            // Calculate work impact score (1-10) - higher score = worse conditions (higher negative impact)
            $temp = ($daily['temperature_2m_max'][$i] + $daily['temperature_2m_min'][$i]) / 2;
            $precip = $daily['precipitation_sum'][$i] ?? 0;
            $wind = $daily['windspeed_10m_max'][$i] ?? 0;
            $weather_code = $daily['weather_code'][$i] ?? 0;

            $impact = 2; // default good (low impact)
            if ($precip > 10 || $wind > 40) $impact = 8;
            else if ($precip > 5 || $wind > 25) $impact = 6;
            else if ($temp < 0 || $temp > 35) $impact = 7;
            else if ($temp < 5 || $temp > 30) $impact = 4;

            $recommendation = $impact <= 3 ? 'Good conditions for outdoor work' :
                             ($impact <= 6 ? 'Conditions acceptable - monitor weather' : 'Consider postponing exposed work');

            $this->wpdb->insert($table, array(
                'job_id' => $job_id,
                'forecast_date' => $daily['time'][$i],
                'fetched_at' => current_time('mysql'),
                'latitude' => $lat,
                'longitude' => $lng,
                'temperature_high' => $daily['temperature_2m_max'][$i],
                'temperature_low' => $daily['temperature_2m_min'][$i],
                'precipitation_amount' => $daily['precipitation_sum'][$i] ?? 0,
                'wind_speed' => $daily['windspeed_10m_max'][$i] ?? 0,
                'condition_text' => $this->get_weather_condition_from_code($weather_code, $precip),
                'work_impact_score' => $impact,
                'work_recommendation' => $recommendation,
                'weather_code' => $weather_code,
                'raw_data' => json_encode(array(
                    'max_temp' => $daily['temperature_2m_max'][$i],
                    'min_temp' => $daily['temperature_2m_min'][$i],
                    'precip' => $daily['precipitation_sum'][$i],
                    'weather_code' => $weather_code
                ))
            ));
        }

        return true;
    }

    /**
     * Geocode address using Mapbox for weather location
     */
    private function geocode_address_for_weather($address) {
        $mapbox_token = 'pk.eyJ1IjoicGxhbm5pbmdpbmRleCIsImEiOiJjbWs4ZnZ6MGUxOWg1M2NyNW9xbnZodWx3In0.SOAFHPon69-aJS2G6qAoBQ';
        $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode($address) . '.json?access_token=' . $mapbox_token . '&country=GB&limit=1';

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['features'][0])) {
            return false;
        }

        $feature = $body['features'][0];
        return array(
            'latitude' => floatval($feature['center'][1]),
            'longitude' => floatval($feature['center'][0])
        );
    }

    /**
     * Convert Open-Meteo weather code to readable condition
     * WMO Weather interpretation codes (WW)
     */
    private function get_weather_condition_from_code($code, $precipitation = 0) {
        $codes = array(
            0 => 'Clear sky',
            1 => 'Mainly clear',
            2 => 'Partly cloudy',
            3 => 'Overcast',
            45 => 'Fog',
            48 => 'Depositing rime fog',
            51 => 'Light drizzle',
            53 => 'Moderate drizzle',
            55 => 'Dense drizzle',
            56 => 'Light freezing drizzle',
            57 => 'Dense freezing drizzle',
            61 => 'Slight rain',
            63 => 'Moderate rain',
            65 => 'Heavy rain',
            66 => 'Light freezing rain',
            67 => 'Heavy freezing rain',
            71 => 'Slight snow',
            73 => 'Moderate snow',
            75 => 'Heavy snow',
            77 => 'Snow grains',
            80 => 'Slight rain showers',
            81 => 'Moderate rain showers',
            82 => 'Violent rain showers',
            85 => 'Slight snow showers',
            86 => 'Heavy snow showers',
            95 => 'Thunderstorm',
            96 => 'Thunderstorm with hail',
            99 => 'Thunderstorm with heavy hail'
        );

        if (isset($codes[$code])) {
            return $codes[$code];
        }

        // Fallback to precipitation-based logic
        if ($precipitation > 10) return 'Heavy Rain';
        if ($precipitation > 2) return 'Rain';
        if ($precipitation > 0) return 'Light Rain';
        return 'Clear';
    }
    
    // ============================================
    // API METHOD IMPLEMENTATIONS - Dashboard
    // ============================================
    
    public function get_job_summary($request) {
        $job_id = intval($request->get_param('job_id'));
        
        $summary = array(
            'job_id' => $job_id,
            'communications' => $this->get_count($this->tables['communications'], $job_id),
            'documents' => $this->get_count($this->tables['documents'], $job_id),
            'timesheets' => $this->get_count($this->tables['timesheets'], $job_id),
            'safety_incidents' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['safety_incidents']} WHERE job_id = %d AND status = 'open'",
                $job_id
            )),
            'quality_snags' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['quality_snags']} WHERE job_id = %d AND status = 'open'",
                $job_id
            )),
            'pending_change_orders' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['change_orders']} WHERE job_id = %d AND status IN ('draft', 'pending')",
                $job_id
            )),
            'open_rfi' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['rfi']} WHERE job_id = %d AND status = 'open'",
                $job_id
            )),
            'pending_submittals' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['submittals']} WHERE job_id = %d AND status = 'pending'",
                $job_id
            )),
            'outstanding_invoices' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT SUM(total_amount - paid_amount) FROM {$this->tables['invoices']} WHERE job_id = %d AND status != 'paid'",
                $job_id
            )) ?? 0
        );
        
        return new WP_REST_Response($summary, 200);
    }
    
    private function get_count($table, $job_id) {
        return intval($this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE job_id = %d",
            $job_id
        )));
    }
    
    public function get_timeline($request) {
        $job_id = intval($request->get_param('job_id'));
        $limit = intval($request->get_param('limit') ?? 50);
        
        // Build a unified timeline from all activity sources
        $timeline = array();
        
        // Communications
        $comms = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, 'communication' as type, subject as title, created_at, status 
            FROM {$this->tables['communications']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        $timeline = array_merge($timeline, $comms);
        
        // Documents
        $docs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, 'document' as type, title, uploaded_at as created_at, category as status 
            FROM {$this->tables['documents']} 
            WHERE job_id = %d AND is_latest = 1",
            $job_id
        ), ARRAY_A);
        $timeline = array_merge($timeline, $docs);
        
        // Timesheets
        $timesheets = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, 'timesheet' as type, CONCAT(worker_name, ' - ', total_hours, 'hrs') as title, created_at, status 
            FROM {$this->tables['timesheets']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        $timeline = array_merge($timeline, $timesheets);
        
        // Safety incidents
        $incidents = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, 'safety_incident' as type, CONCAT(incident_type, ' - ', severity) as title, created_at, status 
            FROM {$this->tables['safety_incidents']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        $timeline = array_merge($timeline, $incidents);
        
        // Photos
        $photos = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, 'photo' as type, COALESCE(title, file_name) as title, taken_at as created_at, category as status 
            FROM {$this->tables['job_photos']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        $timeline = array_merge($timeline, $photos);
        
        // Change orders
        $cos = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, 'change_order' as type, CONCAT(co_number, ' - ', title) as title, created_at, status 
            FROM {$this->tables['change_orders']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        $timeline = array_merge($timeline, $cos);
        
        // Sort by date descending
        usort($timeline, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Limit results
        $timeline = array_slice($timeline, 0, $limit);
        
        return new WP_REST_Response($timeline, 200);
    }
    
    public function get_kpis($request) {
        $job_id = intval($request->get_param('job_id'));
        
        // Financial KPIs
        $financial = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN status = 'paid' THEN paid_amount ELSE 0 END) as revenue_received,
                SUM(CASE WHEN status != 'paid' THEN total_amount - paid_amount ELSE 0 END) as revenue_outstanding,
                SUM(retention_amount) as retention_held
            FROM {$this->tables['invoices']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        
        // Labor KPIs
        $labor = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT worker_id) as unique_workers,
                SUM(total_hours) as total_hours,
                SUM(total_hours * hourly_rate) as labor_cost
            FROM {$this->tables['timesheets']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        
        // Safety KPIs
        $safety = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_incidents,
                SUM(CASE WHEN severity = 'major' THEN 1 ELSE 0 END) as major_incidents,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_incidents,
                SUM(CASE WHEN rIDDOR_reportable = 1 THEN 1 ELSE 0 END) as riddor_reportable
            FROM {$this->tables['safety_incidents']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        
        // Quality KPIs
        $quality = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_snags,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_snags,
                SUM(CASE WHEN priority = 'high' AND status = 'open' THEN 1 ELSE 0 END) as high_priority_open,
                SUM(actual_cost) as snag_costs
            FROM {$this->tables['quality_snags']} 
            WHERE job_id = %d",
            $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response(array(
            'financial' => $financial,
            'labor' => $labor,
            'safety' => $safety,
            'quality' => $quality
        ), 200);
    }
    
    // ============================================
    // Generic update/delete methods
    // ============================================
    
    public function get_communication($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['communications'];
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        return new WP_REST_Response($result, 200);
    }
    
    public function update_communication($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['communications'];
        
        $update_data = array(
            'subject' => sanitize_text_field($data['subject'] ?? ''),
            'content' => wp_kses_post($data['content'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'draft'),
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_communication($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['communications'];
        
        $this->wpdb->delete($table, array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // Generic implementations for other resources
    public function get_document($request) {
        $id = intval($request->get_param('id'));
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['documents']} WHERE id = %d",
            $id
        ), ARRAY_A);
        return new WP_REST_Response($result, 200);
    }
    
    public function delete_document($request) {
        $id = intval($request->get_param('id'));
        
        // Get the document record to find the file path
        $document = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT file_path FROM {$this->tables['documents']} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        // Delete the physical file if it exists
        if ($document && !empty($document['file_path'])) {
            $file_path = $document['file_path'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            // Also check for URL-based paths and convert to local
            if (strpos($file_path, '/wp-content/') !== false) {
                $upload_dir = wp_upload_dir();
                $relative_path = parse_url($file_path, PHP_URL_PATH);
                if ($relative_path) {
                    $local_path = str_replace('/wp-content/uploads/', $upload_dir['basedir'] . '/', $relative_path);
                    if (file_exists($local_path) && $local_path !== $file_path) {
                        @unlink($local_path);
                    }
                }
            }
        }
        
        // Delete from database
        $this->wpdb->delete($this->tables['documents'], array('id' => $id));
        
        return new WP_REST_Response(array('success' => true, 'message' => 'Document deleted'), 200);
    }
    
    public function get_document_versions($request) {
        $id = intval($request->get_param('id'));
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT parent_document_id FROM {$this->tables['documents']} WHERE id = %d",
            $id
        ));
        
        $parent_id = $result->parent_document_id ?? $id;
        
        $versions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['documents']} 
            WHERE (id = %d OR parent_document_id = %d) 
            ORDER BY version DESC",
            $parent_id, $parent_id
        ), ARRAY_A);
        
        return new WP_REST_Response($versions, 200);
    }
    
    public function update_timesheet($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'approved_by' => !empty($data['approved']) ? get_current_user_id() : null,
            'approved_at' => !empty($data['approved']) ? current_time('mysql') : null,
            'notes' => sanitize_textarea_field($data['notes'] ?? '')
        );
        
        $this->wpdb->update($this->tables['timesheets'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_timesheet($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['timesheets'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function update_safety_inspection($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'findings' => sanitize_textarea_field($data['findings'] ?? ''),
            'score' => intval($data['score'] ?? 0),
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($this->tables['safety_inspections'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_safety_inspection($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['safety_inspections'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function update_quality_snag($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'status' => sanitize_text_field($data['status'] ?? 'open'),
            'assigned_to' => sanitize_text_field($data['assigned_to'] ?? ''),
            'due_date' => sanitize_text_field($data['due_date'] ?? ''),
            'completion_notes' => sanitize_textarea_field($data['completion_notes'] ?? ''),
            'actual_cost' => floatval($data['actual_cost'] ?? 0),
            'completed_date' => !empty($data['status']) && $data['status'] === 'closed' ? current_time('Y-m-d') : null,
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($this->tables['quality_snags'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_quality_snag($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['quality_snags'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function create_quality_signoff($request) {
        $data = $request->get_json_params();
        $table = $this->tables['quality_signoffs'];
        
        $insert_data = array(
            'job_id' => intval($data['job_id']),
            'signoff_type' => sanitize_text_field($data['signoff_type']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'stage' => sanitize_text_field($data['stage'] ?? ''),
            'requested_by' => get_current_user_id(),
            'approver_name' => sanitize_text_field($data['approver_name']),
            'approver_email' => sanitize_email($data['approver_email'] ?? ''),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        
        return new WP_REST_Response(array(
            'id' => $this->wpdb->insert_id,
            'success' => true
        ), 201);
    }
    
    public function update_change_order($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'cost_impact' => floatval($data['cost_impact'] ?? 0),
            'schedule_impact_days' => intval($data['schedule_impact_days'] ?? 0),
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($this->tables['change_orders'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_change_order($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['change_orders'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_invoice($request) {
        $id = intval($request->get_param('id'));
        $invoice = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($invoice) {
            $invoice['line_items'] = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['invoice_items']} WHERE invoice_id = %d",
                $id
            ), ARRAY_A);
        }
        
        return new WP_REST_Response($invoice, 200);
    }
    
    public function update_invoice($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'draft'),
            'due_date' => sanitize_text_field($data['due_date'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($this->tables['invoices'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function update_daily_report($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'weather_conditions' => sanitize_text_field($data['weather_conditions'] ?? ''),
            'workforce_count' => intval($data['workforce_count'] ?? 0),
            'work_completed' => sanitize_textarea_field($data['work_completed'] ?? ''),
            'work_planned' => sanitize_textarea_field($data['work_planned'] ?? ''),
            'delays' => sanitize_textarea_field($data['delays'] ?? ''),
            'safety_issues' => sanitize_textarea_field($data['safety_issues'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($this->tables['daily_reports'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_daily_report($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['daily_reports'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function update_equipment($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['equipment'];
        
        // Handle internal_name/equipment_name - use internal_name if provided, otherwise equipment_name
        $name_value = sanitize_text_field($data['internal_name'] ?? $data['equipment_name'] ?? '');
        
        error_log('[OLD CRM API] Update equipment ID: ' . $id . ' - name value: ' . $name_value);
        
        // Get available columns for backward compatibility
        $columns = $this->wpdb->get_col("DESCRIBE {$table}");
        
        $update_data = array(
            'equipment_name' => $name_value,
            'status' => sanitize_text_field($data['status'] ?? 'On-Site'),
            'updated_at' => current_time('mysql')
        );
        
        // Update only if provided
        if (isset($data['equipment_type'])) {
            $update_data['equipment_type'] = sanitize_text_field($data['equipment_type']);
        }
        if (isset($data['make'])) {
            $update_data['make'] = sanitize_text_field($data['make']);
        }
        if (isset($data['model'])) {
            $update_data['model'] = sanitize_text_field($data['model']);
        }
        if (isset($data['serial_number'])) {
            $update_data['serial_number'] = sanitize_text_field($data['serial_number']);
        }
        if (isset($data['acquisition_type']) || isset($data['ownership_type'])) {
            $update_data['ownership_type'] = sanitize_text_field($data['acquisition_type'] ?? $data['ownership_type'] ?? 'owned');
        }
        if (isset($data['hire_company']) || isset($data['supplier_name'])) {
            $update_data['hire_company'] = sanitize_text_field($data['hire_company'] ?? $data['supplier_name'] ?? '');
        }
        if (isset($data['hire_rate']) || isset($data['daily_rate'])) {
            $update_data['hire_rate'] = floatval($data['hire_rate'] ?? $data['daily_rate'] ?? 0);
        }
        if (isset($data['next_service_due'])) {
            $update_data['next_service_due'] = sanitize_text_field($data['next_service_due']);
        }
        if (isset($data['condition_notes'])) {
            $update_data['condition_notes'] = sanitize_textarea_field($data['condition_notes']);
        }
        
        // Update internal_name column if it exists
        if (in_array('internal_name', $columns) && !empty($name_value)) {
            $update_data['internal_name'] = $name_value;
        }
        
        // Update new schema fields if columns exist and data is provided
        if (in_array('category', $columns) && isset($data['category'])) {
            $update_data['category'] = sanitize_text_field($data['category']);
        }
        if (in_array('manufacturer', $columns) && isset($data['manufacturer'])) {
            $update_data['manufacturer'] = sanitize_text_field($data['manufacturer']);
        }
        if (in_array('brand', $columns) && isset($data['brand'])) {
            $update_data['brand'] = sanitize_text_field($data['brand']);
        }
        if (in_array('model_number', $columns) && isset($data['model_number'])) {
            $update_data['model_number'] = sanitize_text_field($data['model_number']);
        }
        if (in_array('vin', $columns) && isset($data['vin'])) {
            $update_data['vin'] = sanitize_text_field($data['vin']);
        }
        if (in_array('asset_tag', $columns) && isset($data['asset_tag'])) {
            $update_data['asset_tag'] = sanitize_text_field($data['asset_tag']);
        }
        if (in_array('acquisition_type', $columns) && isset($data['acquisition_type'])) {
            $update_data['acquisition_type'] = sanitize_text_field($data['acquisition_type']);
        }
        if (in_array('supplier_name', $columns) && isset($data['supplier_name'])) {
            $update_data['supplier_name'] = sanitize_text_field($data['supplier_name']);
        }
        if (in_array('supplier_contact', $columns) && isset($data['supplier_contact'])) {
            $update_data['supplier_contact'] = sanitize_text_field($data['supplier_contact']);
        }
        if (in_array('hire_reference_number', $columns) && isset($data['hire_reference_number'])) {
            $update_data['hire_reference_number'] = sanitize_text_field($data['hire_reference_number']);
        }
        if (in_array('rate_type', $columns) && isset($data['rate_type'])) {
            $update_data['rate_type'] = sanitize_text_field($data['rate_type']);
        }
        if (in_array('daily_rate', $columns) && isset($data['daily_rate'])) {
            $update_data['daily_rate'] = floatval($data['daily_rate']);
        }
        if (in_array('weekly_rate', $columns) && isset($data['weekly_rate'])) {
            $update_data['weekly_rate'] = floatval($data['weekly_rate']);
        }
        if (in_array('monthly_rate', $columns) && isset($data['monthly_rate'])) {
            $update_data['monthly_rate'] = floatval($data['monthly_rate']);
        }
        if (in_array('cost_to_job', $columns) && isset($data['cost_to_job'])) {
            $update_data['cost_to_job'] = floatval($data['cost_to_job']);
        }
        if (in_array('deposit_held', $columns) && isset($data['deposit_held'])) {
            $update_data['deposit_held'] = floatval($data['deposit_held']);
        }
        if (in_array('allocated_from_date', $columns) && !empty($data['allocated_from_date'])) {
            $update_data['allocated_from_date'] = sanitize_text_field($data['allocated_from_date']);
        } else {
            // Fallback to old schema field name
            if (!empty($data['allocated_from_date'])) {
                $update_data['hire_start_date'] = sanitize_text_field($data['allocated_from_date']);
            }
        }
        if (in_array('allocated_to_date', $columns) && !empty($data['allocated_to_date'])) {
            $update_data['allocated_to_date'] = sanitize_text_field($data['allocated_to_date']);
        } else {
            // Fallback to old schema field name
            if (!empty($data['allocated_to_date'])) {
                $update_data['hire_end_date'] = sanitize_text_field($data['allocated_to_date']);
            }
        }
        if (in_array('actual_on_site_date', $columns) && !empty($data['actual_on_site_date'])) {
            $update_data['actual_on_site_date'] = sanitize_text_field($data['actual_on_site_date']);
        }
        if (in_array('actual_return_date', $columns) && !empty($data['actual_return_date'])) {
            $update_data['actual_return_date'] = sanitize_text_field($data['actual_return_date']);
        }
        if (in_array('current_condition', $columns) && isset($data['current_condition'])) {
            $update_data['current_condition'] = sanitize_text_field($data['current_condition']);
        } else {
            // Fallback to old schema field name
            if (!empty($data['current_condition'])) {
                $update_data['condition_notes'] = sanitize_text_field($data['current_condition']);
            }
        }
        if (in_array('assigned_operator_id', $columns) && isset($data['assigned_operator_id'])) {
            $update_data['assigned_operator_id'] = intval($data['assigned_operator_id']);
        }
        if (in_array('operator_certification_required', $columns) && isset($data['operator_certification_required'])) {
            $update_data['operator_certification_required'] = sanitize_text_field($data['operator_certification_required']);
        }
        if (in_array('supervisor_responsible_id', $columns) && isset($data['supervisor_responsible_id'])) {
            $update_data['supervisor_responsible_id'] = intval($data['supervisor_responsible_id']);
        }
        if (in_array('current_location_on_site', $columns) && isset($data['current_location_on_site'])) {
            $update_data['current_location_on_site'] = sanitize_text_field($data['current_location_on_site']);
        }
        
        error_log('[OLD CRM API] Updating equipment ID: ' . $id . ' with data: ' . json_encode($update_data));
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_equipment($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['equipment'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_equipment_usage($request) {
        $id = intval($request->get_param('id'));
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['equipment_usage']} WHERE equipment_id = %d ORDER BY usage_date DESC",
            $id
        ), ARRAY_A);
        return new WP_REST_Response($results, 200);
    }
    
    public function log_equipment_usage($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $insert_data = array(
            'equipment_id' => $id,
            'job_id' => intval($data['job_id']),
            'usage_date' => sanitize_text_field($data['usage_date'] ?? current_time('Y-m-d')),
            'hours_used' => floatval($data['hours_used'] ?? 0),
            'operator_name' => sanitize_text_field($data['operator_name'] ?? ''),
            'fuel_used' => floatval($data['fuel_used'] ?? 0),
            'fuel_cost' => floatval($data['fuel_cost'] ?? 0),
            'maintenance_required' => !empty($data['maintenance_required']) ? 1 : 0,
            'maintenance_notes' => sanitize_textarea_field($data['maintenance_notes'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($this->tables['equipment_usage'], $insert_data);
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'success' => true), 201);
    }
    
    public function update_job_subcontractor($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'contract_value' => floatval($data['contract_value'] ?? 0),
            'insurance_verified' => !empty($data['insurance_verified']) ? 1 : 0,
            'insurance_expiry' => sanitize_text_field($data['insurance_expiry'] ?? ''),
            'cis_verified' => !empty($data['cis_verified']) ? 1 : 0,
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'invoices_submitted' => floatval($data['invoices_submitted'] ?? 0),
            'invoices_paid' => floatval($data['invoices_paid'] ?? 0),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($this->tables['job_subcontractors'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_job_subcontractor($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['job_subcontractors'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function update_photo($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? 'site'),
            'tags' => sanitize_text_field($data['tags'] ?? ''),
            'approved_for_client' => !empty($data['approved_for_client']) ? 1 : 0
        );
        
        $this->wpdb->update($this->tables['job_photos'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_photo($request) {
        $id = intval($request->get_param('id'));
        
        // Get the photo record to find the file path
        $photo = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT file_path, file_url FROM {$this->tables['job_photos']} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        // Delete the physical file if it exists (check both file_path and file_url)
        $file_paths = array();
        if ($photo && !empty($photo['file_path'])) {
            $file_paths[] = $photo['file_path'];
        }
        if ($photo && !empty($photo['file_url'])) {
            $file_paths[] = $photo['file_url'];
        }
        
        foreach ($file_paths as $file_path) {
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            // Convert URL to local path if needed
            if (strpos($file_path, '/wp-content/') !== false || strpos($file_path, 'http') === 0) {
                $upload_dir = wp_upload_dir();
                if (strpos($file_path, 'http') === 0) {
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_path);
                } else {
                    $relative_path = parse_url($file_path, PHP_URL_PATH);
                    if ($relative_path) {
                        $file_path = str_replace('/wp-content/uploads/', $upload_dir['basedir'] . '/', $relative_path);
                    }
                }
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        }
        
        // Delete from database
        $this->wpdb->delete($this->tables['job_photos'], array('id' => $id));
        
        return new WP_REST_Response(array('success' => true, 'message' => 'Photo deleted'), 200);
    }
    
    public function update_team_assignment($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        
        $update_data = array(
            'role' => sanitize_text_field($data['role'] ?? ''),
            'allocation_percentage' => intval($data['allocation_percentage'] ?? 100),
            'start_date' => sanitize_text_field($data['start_date'] ?? ''),
            'end_date' => sanitize_text_field($data['end_date'] ?? ''),
            'is_lead' => !empty($data['is_lead']) ? 1 : 0,
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'updated_at' => current_time('mysql')
        );
        
        $this->wpdb->update($this->tables['team_assignments'], $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function remove_team_member($request) {
        $id = intval($request->get_param('id'));
        $this->wpdb->delete($this->tables['team_assignments'], array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_certifications($request) {
        $user_id = intval($request->get_param('user_id') ?? 0);
        
        if ($user_id) {
            $results = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['certifications']} WHERE user_id = %d ORDER BY expiry_date",
                $user_id
            ), ARRAY_A);
        } else {
            $results = $this->wpdb->get_results(
                "SELECT c.*, u.display_name as user_name 
                FROM {$this->tables['certifications']} c 
                LEFT JOIN {$this->wpdb->users} u ON c.user_id = u.ID 
                WHERE c.status = 'valid' AND c.expiry_date > CURDATE()
                ORDER BY c.expiry_date",
                ARRAY_A
            );
        }
        
        return new WP_REST_Response($results, 200);
    }
    
    public function add_certification($request) {
        $data = $request->get_json_params();
        
        $insert_data = array(
            'user_id' => intval($data['user_id']),
            'certification_name' => sanitize_text_field($data['certification_name']),
            'issuing_body' => sanitize_text_field($data['issuing_body']),
            'certificate_number' => sanitize_text_field($data['certificate_number'] ?? ''),
            'issue_date' => sanitize_text_field($data['issue_date']),
            'expiry_date' => sanitize_text_field($data['expiry_date']),
            'status' => 'valid',
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($this->tables['certifications'], $insert_data);
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'success' => true), 201);
    }
    
    public function get_client_communications($request) {
        $job_id = intval($request->get_param('job_id'));
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['communications']} 
            WHERE job_id = %d AND (recipient_email IN (
                SELECT primary_contact_email FROM {$this->tables['client_details']} WHERE job_id = %d
            ) OR recipient_email IN (
                SELECT secondary_contact_email FROM {$this->tables['client_details']} WHERE job_id = %d
            ))
            ORDER BY created_at DESC",
            $job_id, $job_id, $job_id
        ), ARRAY_A);
        
        return new WP_REST_Response($results, 200);
    }
    
    // ============================================
    // ENHANCED TEAM & TIMESHEETS API METHODS
    // ============================================
    
    // Employees
    public function get_employees($request) {
        $job_id = $request->get_param('job_id');
        $status = $request->get_param('status');
        $role = $request->get_param('role');
        $trade = $request->get_param('trade');
        $search = $request->get_param('search');
        
        $table = $this->tables['employees'];
        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = array();
        
        // Filter by job_id if provided (for job-specific views)
        // job_id = specific job: show only that job's employees
        // job_id = 0 or empty: show ALL employees (global view)
        if ($job_id && intval($job_id) > 0) {
            $sql .= " AND job_id = %d";
            $params[] = intval($job_id);
        }
        
        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }
        if ($role) {
            $sql .= " AND role = %s";
            $params[] = $role;
        }
        if ($trade) {
            $sql .= " AND trade = %s";
            $params[] = $trade;
        }
        if ($search) {
            $sql .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)";
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $sql .= " ORDER BY last_name, first_name";
        
        $results = $this->wpdb->get_results($params ? $this->wpdb->prepare($sql, $params) : $sql, ARRAY_A);
        return new WP_REST_Response($results, 200);
    }
    
    public function create_employee($request) {
        $data = $request->get_json_params();
        $table = $this->tables['employees'];
        
        // Ensure table exists
        $this->maybe_create_employees_table();
        
        // Validate required fields
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['role'])) {
            return new WP_REST_Response(array('error' => 'Missing required fields: first_name, last_name, email, role'), 400);
        }
        
        // Check if email already exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s",
            sanitize_email($data['email'])
        ));
        if ($existing) {
            return new WP_REST_Response(array('error' => 'An employee with this email already exists'), 409);
        }
        
        // Get job_id from request (for job-specific employee creation)
        $job_id = !empty($data['job_id']) ? intval($data['job_id']) : null;
        
        $insert_data = array(
            'job_id' => $job_id,
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'mobile' => sanitize_text_field($data['mobile'] ?? ''),
            'role' => sanitize_text_field($data['role']),
            'trade' => sanitize_text_field($data['trade'] ?? ''),
            'skill_level' => sanitize_text_field($data['skill_level'] ?? 'skilled'),
            'employment_type' => sanitize_text_field($data['employment_type'] ?? 'full_time'),
            'hourly_rate' => floatval($data['hourly_rate'] ?? 0),
            'default_cost_code' => sanitize_text_field($data['default_cost_code'] ?? ''),
            'department' => sanitize_text_field($data['department'] ?? ''),
            'hire_date' => !empty($data['hire_date']) ? sanitize_text_field($data['hire_date']) : null,
            'status' => 'active',
            'permissions_level' => sanitize_text_field($data['permissions_level'] ?? 'field_worker'),
            'can_approve_timesheets' => !empty($data['can_approve_timesheets']) ? 1 : 0,
            'can_approve_expenses' => !empty($data['can_approve_expenses']) ? 1 : 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        // Generate employee code
        $year = date('Y');
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_at >= '{$year}-01-01'") + 1;
        $insert_data['employee_code'] = 'EMP-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        $result = $this->wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'error' => 'Database insert failed',
                'details' => $this->wpdb->last_error
            ), 500);
        }
        
        $insert_id = $this->wpdb->insert_id;
        
        if ($insert_id == 0) {
            return new WP_REST_Response(array(
                'error' => 'Failed to create employee - no insert ID returned',
                'details' => $this->wpdb->last_error
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'id' => $insert_id,
            'success' => true,
            'employee_code' => $insert_data['employee_code']
        ), 201);
    }
    
    public function get_employee($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['employees'];
        
        $employee = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$employee) {
            return new WP_Error('not_found', 'Employee not found', array('status' => 404));
        }
        
        // Get certifications
        $certs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['certifications']} WHERE user_id = %d ORDER BY expiry_date",
            $employee['user_id'] ?? 0
        ), ARRAY_A);
        
        $employee['certifications'] = $certs;
        
        return new WP_REST_Response($employee, 200);
    }
    
    public function update_employee($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['employees'];
        
        $update_data = array();
        $fields = array('first_name', 'last_name', 'email', 'phone', 'mobile', 'role', 'trade', 
                        'skill_level', 'employment_type', 'hourly_rate', 'default_cost_code', 
                        'department', 'status', 'permissions_level');
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        if (isset($data['can_approve_timesheets'])) {
            $update_data['can_approve_timesheets'] = !empty($data['can_approve_timesheets']) ? 1 : 0;
        }
        if (isset($data['can_approve_expenses'])) {
            $update_data['can_approve_expenses'] = !empty($data['can_approve_expenses']) ? 1 : 0;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_employee($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['employees'];
        
        // Soft delete - set status to inactive
        $this->wpdb->update($table, array(
            'status' => 'inactive',
            'termination_date' => current_time('Y-m-d'),
            'updated_at' => current_time('mysql')
        ), array('id' => $id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_employee_stats($request) {
        $id = intval($request->get_param('id'));
        $timesheets_table = $this->tables['timesheets'];
        
        // Get hours this week
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        // Get regular hours, overtime hours, and total separately
        $week_stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                SUM(total_hours) as regular_hours,
                SUM(overtime_hours) as overtime_hours,
                SUM(total_hours + overtime_hours) as total_hours
             FROM {$timesheets_table} 
             WHERE worker_id = %d AND work_date BETWEEN %s AND %s AND status != 'rejected'",
            $id, $week_start, $week_end
        ), ARRAY_A);
        
        // Get hours this month (with overtime included)
        $month_stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                SUM(total_hours) as regular_hours,
                SUM(overtime_hours) as overtime_hours,
                SUM(total_hours + overtime_hours) as total_hours
             FROM {$timesheets_table} 
             WHERE worker_id = %d AND work_date >= %s AND status != 'rejected'",
            $id, date('Y-m-01')
        ), ARRAY_A);
        
        // Get total entries
        $total_entries = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$timesheets_table} WHERE worker_id = %d",
            $id
        ));
        
        $week_regular = floatval($week_stats['regular_hours'] ?? 0);
        $week_ot = floatval($week_stats['overtime_hours'] ?? 0);
        $week_total = floatval($week_stats['total_hours'] ?? 0);
        
        $month_regular = floatval($month_stats['regular_hours'] ?? 0);
        $month_ot = floatval($month_stats['overtime_hours'] ?? 0);
        $month_total = floatval($month_stats['total_hours'] ?? 0);
        
        return new WP_REST_Response(array(
            'week_hours' => round($week_total, 2),           // Total = regular + OT
            'week_regular_hours' => round($week_regular, 2),
            'week_overtime_hours' => round($week_ot, 2),
            'month_hours' => round($month_total, 2),         // Total = regular + OT
            'month_regular_hours' => round($month_regular, 2),
            'month_overtime_hours' => round($month_ot, 2),
            'total_entries' => intval($total_entries)
        ), 200);
    }
    
    public function bulk_employee_action($request) {
        $data = $request->get_json_params();
        $action = sanitize_text_field($data['action']);
        $ids = array_map('intval', $data['ids'] ?? array());
        
        if (empty($ids)) {
            return new WP_Error('no_ids', 'No employee IDs provided', array('status' => 400));
        }
        
        $table = $this->tables['employees'];
        
        switch ($action) {
            case 'activate':
                foreach ($ids as $id) {
                    $this->wpdb->update($table, array('status' => 'active'), array('id' => $id));
                }
                break;
            case 'deactivate':
                foreach ($ids as $id) {
                    $this->wpdb->update($table, array('status' => 'inactive'), array('id' => $id));
                }
                break;
            case 'delete':
                foreach ($ids as $id) {
                    $this->wpdb->update($table, array('status' => 'inactive', 'termination_date' => current_time('Y-m-d')), array('id' => $id));
                }
                break;
        }
        
        return new WP_REST_Response(array('success' => true, 'affected' => count($ids)), 200);
    }
    
    /**
     * Initialize team system - create all required tables
     */
    public function init_team_system($request) {
        // Ensure all required tables exist
        $this->maybe_create_employees_table();
        $this->maybe_create_crews_table();
        $this->maybe_create_crew_members_table();
        $this->maybe_create_clock_status_table();
        $this->maybe_create_timesheet_approvals_table();
        $this->maybe_create_cost_codes_table();
        
        // Check if employees table exists
        $employees_table = $this->wpdb->prefix . 'pi_crm_employees';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$employees_table}'") === $employees_table;
        
        return new WP_REST_Response(array(
            'success' => true,
            'tables_created' => true,
            'employees_table_exists' => $table_exists,
            'message' => 'Team system initialized successfully'
        ), 200);
    }
    
    // Crews
    public function get_crews($request) {
        try {
            $table = $this->tables['crews'];
            $members_table = $this->tables['crew_members'];
            
            $crews = $this->wpdb->get_results(
                "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last, 
                        COUNT(cm.id) as member_count
                 FROM {$table} c
                 LEFT JOIN {$this->tables['employees']} e ON c.foreman_id = e.id
                 LEFT JOIN {$members_table} cm ON c.id = cm.crew_id AND cm.status = 'active'
                 WHERE c.status = 'active'
                 GROUP BY c.id
                 ORDER BY c.crew_name",
                ARRAY_A
            );
            
            return new WP_REST_Response($crews ?: array(), 200);
        } catch (Exception $e) {
            error_log('[PI_CRM_API] Get crews error: ' . $e->getMessage());
            return new WP_REST_Response(array(), 200);
        }
    }
    
    public function create_crew($request) {
        $data = $request->get_json_params();
        $table = $this->tables['crews'];
        
        $insert_data = array(
            'crew_name' => sanitize_text_field($data['crew_name']),
            'crew_code' => sanitize_text_field($data['crew_code'] ?? ''),
            'foreman_id' => intval($data['foreman_id'] ?? 0),
            'trade_specialty' => sanitize_text_field($data['trade_specialty'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($table, $insert_data);
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'success' => true), 201);
    }
    
    public function get_crew($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['crews'];
        
        $crew = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT c.*, e.first_name as foreman_first, e.last_name as foreman_last
             FROM {$table} c
             LEFT JOIN {$this->tables['employees']} e ON c.foreman_id = e.id
             WHERE c.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$crew) {
            return new WP_Error('not_found', 'Crew not found', array('status' => 404));
        }
        
        // Get members
        $crew['members'] = $this->get_crew_members_data($id);
        
        return new WP_REST_Response($crew, 200);
    }
    
    private function get_crew_members_data($crew_id) {
        $members_table = $this->tables['crew_members'];
        $employees_table = $this->tables['employees'];
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT cm.*, e.first_name, e.last_name, e.email, e.role, e.trade, e.hourly_rate
             FROM {$members_table} cm
             JOIN {$employees_table} e ON cm.employee_id = e.id
             WHERE cm.crew_id = %d AND cm.status = 'active'
             ORDER BY cm.is_lead_hand DESC, e.last_name",
            $crew_id
        ), ARRAY_A);
    }
    
    public function update_crew($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['crews'];
        
        $update_data = array();
        $fields = array('crew_name', 'foreman_id', 'trade_specialty', 'description', 'status');
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_crew($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['crews'];
        
        $this->wpdb->update($table, array('status' => 'inactive'), array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_crew_members($request) {
        $id = intval($request->get_param('id'));
        return new WP_REST_Response($this->get_crew_members_data($id), 200);
    }
    
    public function add_crew_member($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['crew_members'];
        
        $insert_data = array(
            'crew_id' => $id,
            'employee_id' => intval($data['employee_id']),
            'role_in_crew' => sanitize_text_field($data['role_in_crew'] ?? 'member'),
            'is_lead_hand' => !empty($data['is_lead_hand']) ? 1 : 0
        );
        
        $this->wpdb->insert($table, $insert_data);
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'success' => true), 201);
    }
    
    public function update_crew_member($request) {
        $crew_id = intval($request->get_param('crew_id'));
        $member_id = intval($request->get_param('member_id'));
        $data = $request->get_json_params();
        $table = $this->tables['crew_members'];
        
        $update_data = array(
            'role_in_crew' => sanitize_text_field($data['role_in_crew'] ?? 'member'),
            'is_lead_hand' => !empty($data['is_lead_hand']) ? 1 : 0
        );
        
        $this->wpdb->update($table, $update_data, array('crew_id' => $crew_id, 'employee_id' => $member_id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function remove_crew_member($request) {
        $crew_id = intval($request->get_param('crew_id'));
        $member_id = intval($request->get_param('member_id'));
        $table = $this->tables['crew_members'];
        
        $this->wpdb->update($table, array('status' => 'inactive', 'left_at' => current_time('mysql')), 
                         array('crew_id' => $crew_id, 'employee_id' => $member_id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // Clock
    public function get_clock_status($request) {
        $current_user_id = get_current_user_id();
        $employees_table = $this->tables['employees'];
        $clock_table = $this->tables['clock_status'];
        
        // Get employee for current user
        $employee = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id FROM {$employees_table} WHERE user_id = %d AND status = 'active'",
            $current_user_id
        ), ARRAY_A);
        
        if (!$employee) {
            return new WP_REST_Response(array('status' => 'no_employee'), 200);
        }
        
        $clock_status = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$clock_table} WHERE employee_id = %d",
            $employee['id']
        ), ARRAY_A);
        
        if (!$clock_status) {
            return new WP_REST_Response(array('status' => 'clocked_out', 'employee_id' => $employee['id']), 200);
        }
        
        $clock_status['employee_id'] = $employee['id'];
        return new WP_REST_Response($clock_status, 200);
    }
    
    public function clock_in($request) {
        $data = $request->get_json_params();
        $employee_id = intval($data['employee_id']);
        $job_id = intval($data['job_id'] ?? 0);
        $clock_table = $this->tables['clock_status'];
        $timesheets_table = $this->tables['timesheets'];
        
        // Create timesheet entry
        $employee = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['employees']} WHERE id = %d",
            $employee_id
        ), ARRAY_A);
        
        $insert_data = array(
            'job_id' => $job_id,
            'worker_id' => $employee_id,
            'worker_name' => $employee['first_name'] . ' ' . $employee['last_name'],
            'work_date' => current_time('Y-m-d'),
            'start_time' => current_time('H:i:s'),
            'status' => 'active',
            'gps_coordinates' => ($data['gps_lat'] ?? '') . ',' . ($data['gps_lng'] ?? ''),
            'cost_code' => $employee['default_cost_code']
        );
        
        $this->wpdb->insert($timesheets_table, $insert_data);
        $timesheet_id = $this->wpdb->insert_id;
        
        // Update clock status
        $this->wpdb->replace($clock_table, array(
            'employee_id' => $employee_id,
            'job_id' => $job_id,
            'status' => 'clocked_in',
            'clocked_in_at' => current_time('mysql'),
            'current_timesheet_id' => $timesheet_id,
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_lng' => $data['gps_lng'] ?? null,
            'gps_accuracy' => $data['gps_accuracy'] ?? null,
            'location_verified' => !empty($data['location_verified']) ? 1 : 0,
            'device_info' => sanitize_text_field($data['device_info'] ?? ''),
            'break_minutes' => 0
        ));
        
        return new WP_REST_Response(array(
            'success' => true, 
            'timesheet_id' => $timesheet_id,
            'clocked_in_at' => current_time('mysql')
        ), 200);
    }
    
    public function clock_out($request) {
        $data = $request->get_json_params();
        $employee_id = intval($data['employee_id']);
        $clock_table = $this->tables['clock_status'];
        $timesheets_table = $this->tables['timesheets'];
        
        // Get current clock status
        $clock_status = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$clock_table} WHERE employee_id = %d",
            $employee_id
        ), ARRAY_A);
        
        if (!$clock_status || $clock_status['status'] !== 'clocked_in') {
            return new WP_Error('not_clocked_in', 'Not currently clocked in', array('status' => 400));
        }
        
        $end_time = current_time('H:i:s');
        $start_time = $clock_status['clocked_in_at'];
        
        // Calculate hours
        $start_timestamp = strtotime($start_time);
        $end_timestamp = strtotime($end_time);
        $total_seconds = $end_timestamp - $start_timestamp;
        $break_seconds = intval($clock_status['break_minutes']) * 60;
        $work_seconds = $total_seconds - $break_seconds;
        $total_hours = round($work_seconds / 3600, 2);
        
        // Update timesheet
        $this->wpdb->update($timesheets_table, array(
            'end_time' => $end_time,
            'total_hours' => $total_hours,
            'break_duration' => $clock_status['break_minutes'],
            'status' => 'pending',
            'notes' => sanitize_textarea_field($data['notes'] ?? '')
        ), array('id' => $clock_status['current_timesheet_id']));
        
        // Update clock status
        $this->wpdb->update($clock_table, array(
            'status' => 'clocked_out',
            'clocked_out_at' => current_time('mysql'),
            'current_timesheet_id' => null,
            'break_minutes' => 0,
            'break_started_at' => null
        ), array('employee_id' => $employee_id));
        
        return new WP_REST_Response(array(
            'success' => true,
            'total_hours' => $total_hours,
            'timesheet_id' => $clock_status['current_timesheet_id']
        ), 200);
    }
    
    public function start_break($request) {
        $data = $request->get_json_params();
        $employee_id = intval($data['employee_id']);
        $clock_table = $this->tables['clock_status'];
        
        $this->wpdb->update($clock_table, array(
            'status' => 'on_break',
            'break_started_at' => current_time('mysql')
        ), array('employee_id' => $employee_id));
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function end_break($request) {
        $data = $request->get_json_params();
        $employee_id = intval($data['employee_id']);
        $clock_table = $this->tables['clock_status'];
        
        $clock_status = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$clock_table} WHERE employee_id = %d",
            $employee_id
        ), ARRAY_A);
        
        if ($clock_status && $clock_status['break_started_at']) {
            $break_start = strtotime($clock_status['break_started_at']);
            $break_end = strtotime(current_time('mysql'));
            $break_minutes = round(($break_end - $break_start) / 60);
            $total_break = intval($clock_status['break_minutes']) + $break_minutes;
            
            $this->wpdb->update($clock_table, array(
                'status' => 'clocked_in',
                'break_started_at' => null,
                'break_minutes' => $total_break
            ), array('employee_id' => $employee_id));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function get_on_site_workers($request) {
        try {
            $clock_table = $this->tables['clock_status'];
            $employees_table = $this->tables['employees'];
            
            $workers = $this->wpdb->get_results(
                "SELECT cs.*, e.first_name, e.last_name, e.role, e.trade, e.avatar_url
                 FROM {$clock_table} cs
                 JOIN {$employees_table} e ON cs.employee_id = e.id
                 WHERE cs.status = 'clocked_in' OR cs.status = 'on_break'
                 ORDER BY cs.clocked_in_at DESC",
                ARRAY_A
            );
            
            return new WP_REST_Response($workers ?: array(), 200);
        } catch (Exception $e) {
            error_log('[PI_CRM_API] On-site workers error: ' . $e->getMessage());
            return new WP_REST_Response(array(), 200);
        }
    }
    
    // Approvals
    public function get_pending_approvals($request) {
        $current_user_id = get_current_user_id();
        $approvals_table = $this->tables['timesheet_approvals'];
        $timesheets_table = $this->tables['timesheets'];
        $employees_table = $this->tables['employees'];
        
        // Check if user can approve
        $can_approve = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT can_approve_timesheets FROM {$employees_table} WHERE user_id = %d",
            $current_user_id
        ));
        
        if (!$can_approve) {
            return new WP_REST_Response(array('approvals' => array(), 'can_approve' => false), 200);
        }
        
        $approvals = $this->wpdb->get_results(
            "SELECT ta.*, ts.work_date, ts.total_hours, ts.start_time, ts.end_time, ts.job_id,
                    e.first_name, e.last_name, e.role, e.trade
             FROM {$approvals_table} ta
             JOIN {$timesheets_table} ts ON ta.timesheet_id = ts.id
             JOIN {$employees_table} e ON ta.employee_id = e.id
             WHERE ta.status = 'pending'
             ORDER BY ta.submitted_at DESC",
            ARRAY_A
        );
        
        // Add job_code to each approval
        if ($approvals) {
            foreach ($approvals as &$row) {
                if (!empty($row['job_id'])) {
                    $job_code = get_post_meta($row['job_id'], '_pi_job_ref', true);
                    $row['job_code'] = $job_code ?: get_the_title($row['job_id']);
                }
            }
        }
        
        return new WP_REST_Response(array('approvals' => $approvals, 'can_approve' => true, 'count' => count($approvals)), 200);
    }
    
    public function approve_timesheet($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $approvals_table = $this->tables['timesheet_approvals'];
        $timesheets_table = $this->tables['timesheets'];
        
        $current_user_id = get_current_user_id();
        
        $this->wpdb->update($approvals_table, array(
            'status' => 'approved',
            'approver_id' => $current_user_id,
            'approved_at' => current_time('mysql'),
            'approval_notes' => sanitize_textarea_field($data['notes'] ?? '')
        ), array('id' => $id));
        
        // Update timesheet status
        $approval = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT timesheet_id FROM {$approvals_table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($approval) {
            $this->wpdb->update($timesheets_table, array('status' => 'approved'), array('id' => $approval['timesheet_id']));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function reject_timesheet($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $approvals_table = $this->tables['timesheet_approvals'];
        $timesheets_table = $this->tables['timesheets'];
        
        $current_user_id = get_current_user_id();
        
        $this->wpdb->update($approvals_table, array(
            'status' => 'rejected',
            'approver_id' => $current_user_id,
            'approved_at' => current_time('mysql'),
            'rejection_reason' => sanitize_textarea_field($data['reason'] ?? '')
        ), array('id' => $id));
        
        // Update timesheet status
        $approval = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT timesheet_id FROM {$approvals_table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($approval) {
            $this->wpdb->update($timesheets_table, array('status' => 'rejected'), array('id' => $approval['timesheet_id']));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function bulk_approval_action($request) {
        $data = $request->get_json_params();
        $action = sanitize_text_field($data['action']);
        $ids = array_map('intval', $data['ids'] ?? array());
        
        if (empty($ids)) {
            return new WP_Error('no_ids', 'No IDs provided', array('status' => 400));
        }
        
        foreach ($ids as $id) {
            if ($action === 'approve') {
                $this->approve_timesheet(new WP_REST_Request('POST', '/approvals/' . $id . '/approve'));
            } elseif ($action === 'reject') {
                $this->reject_timesheet(new WP_REST_Request('POST', '/approvals/' . $id . '/reject'));
            }
        }
        
        return new WP_REST_Response(array('success' => true, 'affected' => count($ids)), 200);
    }
    
    // Cost Codes
    public function get_cost_codes($request) {
        $table = $this->tables['cost_codes'];
        $codes = $this->wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY category, code",
            ARRAY_A
        );
        return new WP_REST_Response($codes, 200);
    }
    
    public function create_cost_code($request) {
        $data = $request->get_json_params();
        $table = $this->tables['cost_codes'];
        
        $insert_data = array(
            'code' => sanitize_text_field($data['code']),
            'description' => sanitize_text_field($data['description']),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'hourly_rate' => floatval($data['hourly_rate'] ?? 0)
        );
        
        $this->wpdb->insert($table, $insert_data);
        return new WP_REST_Response(array('id' => $this->wpdb->insert_id, 'success' => true), 201);
    }
    
    public function update_cost_code($request) {
        $id = intval($request->get_param('id'));
        $data = $request->get_json_params();
        $table = $this->tables['cost_codes'];
        
        $update_data = array(
            'code' => sanitize_text_field($data['code']),
            'description' => sanitize_text_field($data['description']),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'hourly_rate' => floatval($data['hourly_rate'] ?? 0),
            'is_active' => !empty($data['is_active']) ? 1 : 0
        );
        
        $this->wpdb->update($table, $update_data, array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_cost_code($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['cost_codes'];
        
        $this->wpdb->update($table, array('is_active' => 0), array('id' => $id));
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    // Team Dashboard
    public function get_team_dashboard_stats($request) {
        try {
            $employees_table = $this->tables['employees'];
            $timesheets_table = $this->tables['timesheets'];
            $clock_table = $this->tables['clock_status'];
            $approvals_table = $this->tables['timesheet_approvals'];
            
            // Total active employees
            $total_employees = $this->wpdb->get_var("SELECT COUNT(*) FROM {$employees_table} WHERE status = 'active'");
            
            // Currently on site
            $on_site = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$clock_table} WHERE status IN ('clocked_in', 'on_break')"
            );
            
            // Hours this week
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $week_end = date('Y-m-d', strtotime('sunday this week'));
            
            // Get regular and overtime hours separately, then combine for total
            $week_stats = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT 
                    SUM(total_hours) as regular_hours,
                    SUM(overtime_hours) as overtime_hours,
                    SUM(total_hours + overtime_hours) as total_hours
                 FROM {$timesheets_table} 
                 WHERE work_date BETWEEN %s AND %s AND status != 'rejected'",
                $week_start, $week_end
            ), ARRAY_A);
            
            $week_regular = floatval($week_stats['regular_hours'] ?? 0);
            $week_ot = floatval($week_stats['overtime_hours'] ?? 0);
            $week_total = floatval($week_stats['total_hours'] ?? 0);
            
            // Pending approvals
            $pending_approvals = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$approvals_table} WHERE status = 'pending'"
            );
            
            return new WP_REST_Response(array(
                'total_employees' => intval($total_employees ?? 0),
                'on_site' => intval($on_site ?? 0),
                'week_hours' => round($week_total, 2),           // Total includes overtime
                'week_regular_hours' => round($week_regular, 2),
                'week_overtime_hours' => round($week_ot, 2),
                'overtime_hours' => round($week_ot, 2),           // Keep for backwards compatibility
                'pending_approvals' => intval($pending_approvals ?? 0),
                'week_start' => $week_start,
                'week_end' => $week_end
            ), 200);
        } catch (Exception $e) {
            error_log('[PI_CRM_API] Dashboard stats error: ' . $e->getMessage());
            return new WP_REST_Response(array(
                'total_employees' => 0,
                'on_site' => 0,
                'week_hours' => 0,
                'week_regular_hours' => 0,
                'week_overtime_hours' => 0,
                'overtime_hours' => 0,
                'pending_approvals' => 0,
                'week_start' => date('Y-m-d', strtotime('monday this week')),
                'week_end' => date('Y-m-d', strtotime('sunday this week')),
                'error' => $e->getMessage()
            ), 200);
        }
    }
    
    public function get_weekly_hours_report($request) {
        try {
            $timesheets_table = $this->tables['timesheets'];
            $employees_table = $this->tables['employees'];
            
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $week_end = date('Y-m-d', strtotime('sunday this week'));
            
            // Get daily totals
            $daily = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT work_date, SUM(total_hours) as hours, COUNT(DISTINCT worker_id) as workers
                 FROM {$timesheets_table}
                 WHERE work_date BETWEEN %s AND %s AND status != 'rejected'
                 GROUP BY work_date
                 ORDER BY work_date",
                $week_start, $week_end
            ), ARRAY_A);
            
            // Get by project/trade
            $by_trade = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT e.trade, SUM(ts.total_hours) as hours
                 FROM {$timesheets_table} ts
                 JOIN {$employees_table} e ON ts.worker_id = e.id
                 WHERE ts.work_date BETWEEN %s AND %s AND ts.status != 'rejected'
                 GROUP BY e.trade
                 ORDER BY hours DESC",
                $week_start, $week_end
            ), ARRAY_A);
            
            return new WP_REST_Response(array(
                'daily' => $daily ?: array(),
                'by_trade' => $by_trade ?: array(),
                'week_start' => $week_start,
                'week_end' => $week_end
            ), 200);
        } catch (Exception $e) {
            error_log('[PI_CRM_API] Weekly hours report error: ' . $e->getMessage());
            return new WP_REST_Response(array(
                'daily' => array(),
                'by_trade' => array(),
                'week_start' => date('Y-m-d', strtotime('monday this week')),
                'week_end' => date('Y-m-d', strtotime('sunday this week')),
                'error' => $e->getMessage()
            ), 200);
        }
    }
    
    public function get_team_activity($request) {
        try {
            $limit = intval($request->get_param('limit') ?? 20);
            $timesheets_table = $this->tables['timesheets'];
            $employees_table = $this->tables['employees'];
            $clock_table = $this->tables['clock_status'];
            
            $activities = array();
            
            // Recent timesheet entries
            $timesheets = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT ts.*, e.first_name, e.last_name, e.avatar_url
                 FROM {$timesheets_table} ts
                 JOIN {$employees_table} e ON ts.worker_id = e.id
                 ORDER BY ts.created_at DESC
                 LIMIT %d",
                $limit
            ), ARRAY_A);
            
            foreach ($timesheets as $ts) {
                $activities[] = array(
                    'type' => 'timesheet',
                    'employee_name' => $ts['first_name'] . ' ' . $ts['last_name'],
                    'avatar_url' => $ts['avatar_url'],
                    'description' => 'Logged ' . $ts['total_hours'] . ' hours',
                    'timestamp' => $ts['created_at'],
                    'status' => $ts['status']
                );
            }
            
            // Recent clock events
            $clocks = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT cs.*, e.first_name, e.last_name, e.avatar_url
                 FROM {$clock_table} cs
                 JOIN {$employees_table} e ON cs.employee_id = e.id
                 WHERE cs.clocked_in_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 ORDER BY cs.clocked_in_at DESC
                 LIMIT %d",
                $limit
            ), ARRAY_A);
            
            foreach ($clocks as $clock) {
                $activities[] = array(
                    'type' => 'clock',
                    'employee_name' => $clock['first_name'] . ' ' . $clock['last_name'],
                    'avatar_url' => $clock['avatar_url'],
                    'description' => $clock['status'] === 'clocked_in' ? 'Clocked in' : 'Clocked out',
                    'timestamp' => $clock['clocked_in_at'] ?: $clock['clocked_out_at'],
                    'status' => $clock['status']
                );
            }
            
            // Sort by timestamp
            usort($activities, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            return new WP_REST_Response(array_slice($activities, 0, $limit), 200);
        } catch (Exception $e) {
            error_log('[PI_CRM_API] Team activity error: ' . $e->getMessage());
            return new WP_REST_Response(array(), 200);
        }
    }
    
    public function export_timesheets($request) {
        $format = sanitize_text_field($request->get_param('format') ?? 'csv');
        $start_date = sanitize_text_field($request->get_param('start_date'));
        $end_date = sanitize_text_field($request->get_param('end_date'));
        
        $timesheets_table = $this->tables['timesheets'];
        $employees_table = $this->tables['employees'];
        
        $where = "WHERE ts.status != 'rejected'";
        if ($start_date) {
            $where .= $this->wpdb->prepare(" AND ts.work_date >= %s", $start_date);
        }
        if ($end_date) {
            $where .= $this->wpdb->prepare(" AND ts.work_date <= %s", $end_date);
        }
        
        $data = $this->wpdb->get_results(
            "SELECT ts.*, e.first_name, e.last_name, e.trade, e.employee_code
             FROM {$timesheets_table} ts
             JOIN {$employees_table} e ON ts.worker_id = e.id
             {$where}
             ORDER BY ts.work_date DESC, e.last_name",
            ARRAY_A
        );
        
        if ($format === 'csv') {
            $csv = "Date,Employee Code,Name,Trade,Cost Code,Start,End,Hours,Breaks,Overtime,Status,Notes\n";
            foreach ($data as $row) {
                $csv .= sprintf("%s,%s,%s %s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $row['work_date'],
                    $row['employee_code'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['trade'],
                    $row['cost_code'],
                    $row['start_time'],
                    $row['end_time'],
                    $row['total_hours'],
                    $row['break_duration'],
                    $row['overtime_hours'],
                    $row['status'],
                    str_replace(',', ';', $row['notes'] ?? '')
                );
            }
            
            return new WP_REST_Response(array(
                'content' => $csv,
                'filename' => 'timesheets_' . date('Y-m-d') . '.csv',
                'mime_type' => 'text/csv'
            ), 200);
        }
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * Get jobs list for dropdown selection
     */
    public function get_jobs_list($request) {
        $limit = intval($request->get_param('limit') ?? 100);
        
        $jobs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT ID as id, post_title as title, post_name as slug
             FROM {$this->wpdb->posts}
             WHERE post_type = 'pi_job' AND post_status = 'publish'
             ORDER BY post_date DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        // Add job code (_pi_job_ref meta) to each job
        if ($jobs) {
            foreach ($jobs as &$job) {
                $job_code = get_post_meta($job['id'], '_pi_job_ref', true);
                $job['code'] = $job_code ?: $job['title'];
            }
        }
        
        return new WP_REST_Response($jobs ?: array(), 200);
    }
    
    /**
     * Get trade distribution for dashboard chart
     */
    public function get_trade_distribution($request) {
        global $wpdb;
        
        $employees_table = $this->tables['employees'];
        $timesheets_table = $this->tables['timesheets'];
        
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        // Get hours by trade
        $distribution = $wpdb->get_results(
            "SELECT 
                e.trade,
                COALESCE(SUM(t.total_hours + COALESCE(t.overtime_hours, 0)), 0) as hours,
                COUNT(DISTINCT t.employee_id) as employee_count
             FROM {$employees_table} e
             LEFT JOIN {$timesheets_table} t ON e.id = t.employee_id 
                AND t.work_date BETWEEN '{$week_start}' AND '{$week_end}'
                AND t.status != 'rejected'
             WHERE e.status = 'active'
             GROUP BY e.trade
             HAVING hours > 0 OR employee_count > 0
             ORDER BY hours DESC",
            ARRAY_A
        );
        
        // Format for chart
        $result = array();
        $colors = array('#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#ec4899');
        $i = 0;
        
        foreach ($distribution as $row) {
            $trade = $row['trade'] ?: 'General';
            $result[] = array(
                'label' => $trade,
                'value' => floatval($row['hours']),
                'count' => intval($row['employee_count']),
                'color' => $colors[$i % count($colors)]
            );
            $i++;
        }
        
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * Get attendance trends for dashboard chart
     */
    public function get_attendance_trends($request) {
        global $wpdb;
        
        $employees_table = $this->tables['employees'];
        $timesheets_table = $this->tables['timesheets'];
        $period = $request->get_param('period') ?: 'week';
        
        $data = array();
        
        if ($period === 'week') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $day_name = date('D', strtotime($date));
                
                $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$employees_table} WHERE status = 'active'");
                $present = $wpdb->get_var(
                    "SELECT COUNT(DISTINCT employee_id) FROM {$timesheets_table} 
                     WHERE work_date = '{$date}' AND status != 'rejected'"
                );
                
                $rate = $total_employees > 0 ? round(($present / $total_employees) * 100, 1) : 0;
                
                $data[] = array(
                    'label' => $day_name,
                    'rate' => $rate,
                    'present' => intval($present),
                    'total' => intval($total_employees)
                );
            }
        } else {
            // Last 4 weeks
            for ($i = 3; $i >= 0; $i--) {
                $week_start = date('Y-m-d', strtotime("monday -{$i} week"));
                $week_end = date('Y-m-d', strtotime("sunday -{$i} week"));
                
                $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$employees_table} WHERE status = 'active'");
                $present = $wpdb->get_var(
                    "SELECT COUNT(DISTINCT employee_id) FROM {$timesheets_table} 
                     WHERE work_date BETWEEN '{$week_start}' AND '{$week_end}' AND status != 'rejected'"
                );
                
                $rate = $total_employees > 0 ? round(($present / ($total_employees * 5)) * 100, 1) : 0; // Assuming 5 working days
                
                $data[] = array(
                    'label' => 'Week ' . (4 - $i),
                    'rate' => min($rate, 100),
                    'present' => intval($present),
                    'total' => intval($total_employees)
                );
            }
        }
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * Get labor cost analysis for dashboard chart
     */
    public function get_labor_cost($request) {
        global $wpdb;
        
        $timesheets_table = $this->tables['timesheets'];
        $period = $request->get_param('period') ?: 'month';
        
        $data = array();
        
        if ($period === 'week') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $day_name = date('D', strtotime($date));
                
                $cost = $wpdb->get_var(
                    "SELECT SUM((total_hours + COALESCE(overtime_hours, 0)) * hourly_rate) 
                     FROM {$timesheets_table} 
                     WHERE work_date = '{$date}' AND status != 'rejected'"
                );
                
                $data[] = array(
                    'label' => $day_name,
                    'cost' => floatval($cost ?: 0)
                );
            }
        } else {
            // Last 4 weeks
            for ($i = 3; $i >= 0; $i--) {
                $week_start = date('Y-m-d', strtotime("monday -{$i} week"));
                $week_end = date('Y-m-d', strtotime("sunday -{$i} week"));
                
                $cost = $wpdb->get_var(
                    "SELECT SUM((total_hours + COALESCE(overtime_hours, 0)) * hourly_rate) 
                     FROM {$timesheets_table} 
                     WHERE work_date BETWEEN '{$week_start}' AND '{$week_end}' AND status != 'rejected'"
                );
                
                $data[] = array(
                    'label' => 'Week ' . (4 - $i),
                    'cost' => floatval($cost ?: 0),
                    'week_start' => $week_start,
                    'week_end' => $week_end
                );
            }
        }
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * Get top performers for dashboard
     */
    public function get_top_performers($request) {
        global $wpdb;
        
        $limit = intval($request->get_param('limit') ?: 4);
        $employees_table = $this->tables['employees'];
        $timesheets_table = $this->tables['timesheets'];
        
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        $performers = $wpdb->get_results(
            "SELECT 
                e.id,
                e.first_name,
                e.last_name,
                e.role,
                e.trade,
                e.avatar_url,
                COALESCE(SUM(t.total_hours + COALESCE(t.overtime_hours, 0)), 0) as total_hours,
                COUNT(t.id) as entries
             FROM {$employees_table} e
             LEFT JOIN {$timesheets_table} t ON e.id = t.employee_id 
                AND t.work_date BETWEEN '{$week_start}' AND '{$week_end}'
                AND t.status != 'rejected'
             WHERE e.status = 'active'
             GROUP BY e.id
             ORDER BY total_hours DESC
             LIMIT {$limit}",
            ARRAY_A
        );
        
        // Format results
        $result = array();
        foreach ($performers as $p) {
            $result[] = array(
                'id' => intval($p['id']),
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'role' => $p['role'] ?: $p['trade'] ?: 'Worker',
                'trade' => $p['trade'] ?: 'General',
                'avatar_url' => $p['avatar_url'],
                'hours' => round(floatval($p['total_hours']), 1),
                'entries' => intval($p['entries'])
            );
        }
        
        return new WP_REST_Response($result, 200);
    }
}

// Initialize REST API
add_action('init', function() {
    PI_CRM_REST_API::get_instance()->register_routes();
});
