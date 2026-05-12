<?php
/**
 * Planning Index Safety Module - REST API Endpoints
 * Comprehensive API for safety management features
 */

if (!defined('ABSPATH')) exit;

class PI_Safety_REST_API {
    
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
        $db_instance = PI_CRM_Database::get_instance();
        $db_tables = $db_instance->get_table_names();
        
        // Add fallback table names for safety module tables
        $safety_tables = array(
            'safety_incidents' => $wpdb->prefix . 'pi_crm_safety_incidents',
            'safety_inspections' => $wpdb->prefix . 'pi_crm_safety_inspections',
            'safety_checklists' => $wpdb->prefix . 'pi_crm_safety_checklists',
            'safety_checklist_templates' => $wpdb->prefix . 'pi_crm_safety_checklist_templates',
            'permits_to_work' => $wpdb->prefix . 'pi_crm_permits_to_work',
            'job_hazard_analyses' => $wpdb->prefix . 'pi_crm_job_hazard_analyses',
            'toolbox_talks' => $wpdb->prefix . 'pi_crm_toolbox_talks',
            'safety_observations' => $wpdb->prefix . 'pi_crm_safety_observations',
            'ppe_inventory' => $wpdb->prefix . 'pi_crm_ppe_inventory',
            'safety_meetings' => $wpdb->prefix . 'pi_crm_safety_meetings',
            'safety_activity' => $wpdb->prefix . 'pi_crm_safety_activity',
        );
        
        $this->tables = array_merge($db_tables, $safety_tables);
    }
    
    public function register_routes() {
        add_action('rest_api_init', array($this, 'init_routes'));
    }
    
    public function init_routes() {
        $this->register_incident_routes();
        $this->register_observation_routes();
        $this->register_inspection_routes();
        $this->register_permit_routes();
        $this->register_jha_routes();
        $this->register_toolbox_talk_routes();
        $this->register_certification_routes();
        $this->register_ppe_routes();
        $this->register_meeting_routes();
        $this->register_dashboard_routes();
        $this->register_report_routes();
    }
    
    public function check_job_permission($request) {
        $job_id = $request->get_param('job_id');
        
        // If job_id not in params, try to get it from URL path (id param)
        if (!$job_id) {
            $id = $request->get_param('id');
            if ($id) {
                // Try to fetch the record to get its job_id
                $route = $request->get_route();
                if (strpos($route, '/incidents/') !== false) {
                    $incident = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$this->tables['safety_incidents']} WHERE id = %d", $id));
                    if ($incident) $job_id = $incident->job_id;
                } elseif (strpos($route, '/observations/') !== false) {
                    $observation = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$this->tables['safety_observations']} WHERE id = %d", $id));
                    if ($observation) $job_id = $observation->job_id;
                } elseif (strpos($route, '/permits/') !== false) {
                    $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$this->tables['permits_to_work']} WHERE id = %d", $id));
                    if ($permit) $job_id = $permit->job_id;
                } elseif (strpos($route, '/jha/') !== false) {
                    $jha = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$this->tables['job_hazard_analyses']} WHERE id = %d", $id));
                    if ($jha) $job_id = $jha->job_id;
                } elseif (strpos($route, '/toolbox-talks/') !== false) {
                    $talk = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$this->tables['toolbox_talks']} WHERE id = %d", $id));
                    if ($talk) $job_id = $talk->job_id;
                } elseif (strpos($route, '/inspections/') !== false) {
                    $inspection = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$this->tables['safety_inspections']} WHERE id = %d", $id));
                    if ($inspection) $job_id = $inspection->job_id;
                } elseif (strpos($route, '/meetings/') !== false) {
                    $meeting = $this->wpdb->get_row($this->wpdb->prepare("SELECT job_id FROM {$this->tables['safety_meetings']} WHERE id = %d", $id));
                    if ($meeting) $job_id = $meeting->job_id;
                }
            }
        }
        
        if (current_user_can('manage_options')) return true;
        if ($job_id && function_exists('pi_jobs_user_owns_job')) {
            return pi_jobs_user_owns_job($job_id);
        }
        return current_user_can('edit_posts');
    }
    
    public function check_general_permission() {
        return current_user_can('edit_posts') || current_user_can('manage_options');
    }
    
    private function log_safety_activity($job_id, $entity_type, $entity_id, $activity_type, $description, $old_value, $new_value, $performed_by) {
        $table = $this->tables['safety_activity'];
        $user = get_userdata($performed_by);
        $performed_by_name = $user ? $user->display_name : 'Unknown';
        
        $this->wpdb->insert($table, array(
            'job_id' => $job_id,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'activity_type' => $activity_type,
            'description' => $description,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'performed_by' => $performed_by,
            'performed_by_name' => $performed_by_name
        ));
    }
    
    private function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function trigger_critical_alert($job_id, $incident_id, $severity) {
        // Create notification entry
        $notifications_table = $this->wpdb->prefix . 'pi_crm_notifications';
        $this->wpdb->insert($notifications_table, array(
            'job_id' => $job_id,
            'item_type' => 'incident',
            'item_id' => $incident_id,
            'notification_type' => 'critical_alert',
            'recipient_id' => 1, // Admin - should be configurable
            'recipient_email' => get_option('admin_email'),
            'subject' => "CRITICAL: {$severity} incident reported on Job #{$job_id}",
            'message' => "A critical safety incident has been reported. Immediate attention required.",
            'status' => 'pending',
            'scheduled_at' => current_time('mysql')
        ));
    }
    
    // INCIDENT ROUTES - Core CRUD + special actions
    private function register_incident_routes() {
        register_rest_route($this->namespace, '/safety/incidents', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_incidents'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_incident'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/incidents/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_incident'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_incident'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_incident'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/incidents/(?P<id>\d+)/close', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'close_incident'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/incidents/(?P<id>\d+)/audit-trail', array(
            'methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_incident_audit_trail'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/incidents/(?P<id>\d+)/generate-osha', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'generate_osha_report'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/incidents/near-miss', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_near_miss'), 'permission_callback' => array($this, 'check_job_permission'))
        );
    }
    
    public function get_incidents($request) {
        $job_id = $request->get_param('job_id');
        $status = $request->get_param('status');
        $severity = $request->get_param('severity');
        $limit = intval($request->get_param('limit', 25));
        $offset = intval($request->get_param('offset', 0));
        
        $table = $this->tables['safety_incidents'];
        $where = array('1=1');
        $params = array();
        
        if ($job_id) { $where[] = 'job_id = %d'; $params[] = intval($job_id); }
        if ($status) { $where[] = 'status = %s'; $params[] = sanitize_text_field($status); }
        if ($severity) { $where[] = 'severity = %s'; $params[] = sanitize_text_field($severity); }
        
        $where_clause = implode(' AND ', $where);
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY incident_date DESC LIMIT %d OFFSET %d", array_merge($params, array($limit, $offset)));
        $results = $this->wpdb->get_results($sql);
        
        foreach ($results as &$inc) {
            if ($inc->persons_involved) $inc->persons_involved = json_decode($inc->persons_involved, true);
            if ($inc->injuries) $inc->injuries = json_decode($inc->injuries, true);
            if ($inc->equipment_involved) $inc->equipment_involved = json_decode($inc->equipment_involved, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_incident($request) {
        $params = $request->get_json_params();
        $table = $this->tables['safety_incidents'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'incident_date' => sanitize_text_field($params['incident_date'] ?? current_time('mysql')),
            'reported_by' => sanitize_text_field($params['reported_by']),
            'reporter_id' => intval($params['reporter_id'] ?? get_current_user_id()),
            'incident_type' => sanitize_text_field($params['incident_type']),
            'severity' => sanitize_text_field($params['severity'] ?? 'minor'),
            'description' => wp_kses_post($params['description']),
            'status' => 'open',
            'classification' => sanitize_text_field($params['classification'] ?? null),
            'location_on_site' => sanitize_text_field($params['location_on_site'] ?? null),
            'geo_coordinates' => sanitize_text_field($params['geo_coordinates'] ?? null),
            'weather_conditions' => sanitize_text_field($params['weather_conditions'] ?? null)
        );
        
        if (isset($params['persons_involved'])) $data['persons_involved'] = json_encode($params['persons_involved']);
        if (isset($params['equipment_involved'])) $data['equipment_involved'] = json_encode($params['equipment_involved']);
        if (isset($params['witnesses'])) $data['witnesses'] = json_encode($params['witnesses']);
        if (isset($params['injuries'])) $data['injuries'] = json_encode($params['injuries']);
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create incident', array('status' => 500));
        
        $incident_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'incident', $incident_id, 'created', 'Incident created', null, null, get_current_user_id());
        
        if (in_array($data['severity'], array('fatal', 'critical', 'major'))) {
            $this->trigger_critical_alert($data['job_id'], $incident_id, $data['severity']);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_incident_by_id($incident_id)), 201);
    }
    
    public function get_incident($request) {
        $id = intval($request->get_param('id'));
        $incident = $this->get_incident_by_id($id);
        if (!$incident) return new WP_Error('not_found', 'Incident not found', array('status' => 404));
        return new WP_REST_Response(array('success' => true, 'data' => $incident), 200);
    }
    
    public function update_incident($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_incidents'];
        $incident = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$incident) return new WP_Error('not_found', 'Incident not found', array('status' => 404));
        
        $data = array();
        $allowed = array('incident_type', 'severity', 'description', 'status', 'classification', 'location_on_site', 'geo_coordinates', 'weather_conditions', 'immediate_action', 'root_cause', 'corrective_action');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        if (isset($params['persons_involved'])) $data['persons_involved'] = json_encode($params['persons_involved']);
        if (isset($params['equipment_involved'])) $data['equipment_involved'] = json_encode($params['equipment_involved']);
        if (isset($params['witnesses'])) $data['witnesses'] = json_encode($params['witnesses']);
        if (isset($params['injuries'])) $data['injuries'] = json_encode($params['injuries']);
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
            $this->log_safety_activity($incident->job_id, 'incident', $id, 'updated', 'Incident updated', null, json_encode($data), get_current_user_id());
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_incident_by_id($id)), 200);
    }
    
    public function delete_incident($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['safety_incidents'];
        $incident = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$incident) return new WP_Error('not_found', 'Incident not found', array('status' => 404));
        
        $this->wpdb->delete($table, array('id' => $id));
        $this->log_safety_activity($incident->job_id, 'incident', $id, 'deleted', 'Incident deleted', json_encode($incident), null, get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'message' => 'Incident deleted'), 200);
    }
    
    public function close_incident($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_incidents'];
        $incident = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$incident) return new WP_Error('not_found', 'Incident not found', array('status' => 404));
        
        $data = array('status' => 'closed', 'closed_at' => current_time('mysql'), 'closed_by' => get_current_user_id());
        if (isset($params['corrective_action'])) $data['corrective_action'] = wp_kses_post($params['corrective_action']);
        
        $this->wpdb->update($table, $data, array('id' => $id));
        $this->log_safety_activity($incident->job_id, 'incident', $id, 'closed', 'Incident closed', $incident->status, 'closed', get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_incident_by_id($id)), 200);
    }
    
    public function get_incident_audit_trail($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['safety_activity'];
        $results = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$table} WHERE entity_type = 'incident' AND entity_id = %d ORDER BY performed_at DESC", $id));
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function generate_osha_report($request) {
        $id = intval($request->get_param('id'));
        $incident = $this->get_incident_by_id($id);
        if (!$incident) return new WP_Error('not_found', 'Incident not found', array('status' => 404));
        
        $osha_data = array(
            'form_type' => '301',
            'incident_id' => $id,
            'case_number' => $incident->osha_case_number ?? 'TBD',
            'date_of_injury' => substr($incident->incident_date, 0, 10),
            'where_did_it_happen' => $incident->location_on_site,
            'injury_description' => $incident->description,
            'days_away_from_work' => $incident->days_away_from_work ?? 0,
            'classification' => $incident->classification
        );
        
        return new WP_REST_Response(array('success' => true, 'data' => $osha_data), 200);
    }
    
    public function create_near_miss($request) {
        $params = $request->get_json_params();
        $params['classification'] = 'near_miss';
        $params['severity'] = 'low';
        return $this->create_incident($request);
    }
    
    private function get_incident_by_id($id) {
        $table = $this->tables['safety_incidents'];
        $incident = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($incident) {
            if ($incident->persons_involved) $incident->persons_involved = json_decode($incident->persons_involved, true);
            if ($incident->injuries) $incident->injuries = json_decode($incident->injuries, true);
            if ($incident->equipment_involved) $incident->equipment_involved = json_decode($incident->equipment_involved, true);
        }
        return $incident;
    }
    
    // INSPECTION ROUTES - Completely missing, add all CRUD + special actions
    private function register_inspection_routes() {
        register_rest_route($this->namespace, '/safety/inspections', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_inspections'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_inspection'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/inspections/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_inspection'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_inspection'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_inspection'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/inspections/(?P<id>\d+)/complete', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'complete_inspection'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/inspections/(?P<id>\d+)/checklist-item', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'save_checklist_item'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/checklist-templates', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_checklist_templates'), 'permission_callback' => array($this, 'check_general_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_checklist_template'), 'permission_callback' => array($this, 'check_general_permission'))
        ));
    }
    
    public function get_inspections($request) {
        $job_id = $request->get_param('job_id');
        $status = $request->get_param('status');
        $limit = intval($request->get_param('limit', 25));
        $offset = intval($request->get_param('offset', 0));
        $table = $this->tables['safety_inspections'];
        $where = array('1=1');
        $params = array();
        
        if ($job_id) { $where[] = 'job_id = %d'; $params[] = intval($job_id); }
        if ($status) { $where[] = 'status = %s'; $params[] = sanitize_text_field($status); }
        
        $where_clause = implode(' AND ', $where);
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY inspection_date DESC LIMIT %d OFFSET %d", array_merge($params, array($limit, $offset)));
        $results = $this->wpdb->get_results($sql);
        
        foreach ($results as &$insp) {
            if ($insp->checklist_items) $insp->checklist_items = json_decode($insp->checklist_items, true);
            if ($insp->findings) $insp->findings = json_decode($insp->findings, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_inspection($request) {
        $params = $request->get_json_params();
        $table = $this->tables['safety_inspections'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'inspection_type' => sanitize_text_field($params['inspection_type']),
            'title' => sanitize_text_field($params['title']),
            'inspector_id' => intval($params['inspector_id'] ?? get_current_user_id()),
            'inspection_date' => sanitize_text_field($params['inspection_date'] ?? current_time('mysql')),
            'next_due_date' => sanitize_text_field($params['next_due_date'] ?? null),
            'checklist_template_id' => intval($params['checklist_template_id'] ?? null),
            'status' => 'scheduled'
        );
        
        if (isset($params['checklist_items'])) $data['checklist_items'] = json_encode($params['checklist_items']);
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create inspection', array('status' => 500));
        
        $inspection_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'inspection', $inspection_id, 'created', 'Inspection created', null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_inspection_by_id($inspection_id)), 201);
    }
    
    public function get_inspection($request) {
        $id = intval($request->get_param('id'));
        $inspection = $this->get_inspection_by_id($id);
        if (!$inspection) return new WP_Error('not_found', 'Inspection not found', array('status' => 404));
        return new WP_REST_Response(array('success' => true, 'data' => $inspection), 200);
    }
    
    public function update_inspection($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_inspections'];
        $inspection = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$inspection) return new WP_Error('not_found', 'Inspection not found', array('status' => 404));
        
        $data = array();
        $allowed = array('inspection_type', 'title', 'inspector_id', 'inspection_date', 'next_due_date', 'status', 'score');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        if (isset($params['checklist_items'])) $data['checklist_items'] = json_encode($params['checklist_items']);
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
            $this->log_safety_activity($inspection->job_id, 'inspection', $id, 'updated', 'Inspection updated', null, json_encode($data), get_current_user_id());
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_inspection_by_id($id)), 200);
    }
    
    public function delete_inspection($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['safety_inspections'];
        $inspection = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$inspection) return new WP_Error('not_found', 'Inspection not found', array('status' => 404));
        
        $this->wpdb->delete($table, array('id' => $id));
        $this->log_safety_activity($inspection->job_id, 'inspection', $id, 'deleted', 'Inspection deleted', json_encode($inspection), null, get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'message' => 'Inspection deleted'), 200);
    }
    
    public function complete_inspection($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_inspections'];
        $inspection = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$inspection) return new WP_Error('not_found', 'Inspection not found', array('status' => 404));
        
        $data = array(
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'score' => intval($params['overall_score'] ?? 0),
            'digital_signature' => sanitize_text_field($params['digital_signature'] ?? null)
        );
        
        if (isset($params['findings'])) $data['findings'] = json_encode($params['findings']);
        
        $this->wpdb->update($table, $data, array('id' => $id));
        $this->log_safety_activity($inspection->job_id, 'inspection', $id, 'completed', 'Inspection completed', $inspection->status, 'completed', get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_inspection_by_id($id)), 200);
    }
    
    public function save_checklist_item($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_inspections'];
        $inspection = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$inspection) return new WP_Error('not_found', 'Inspection not found', array('status' => 404));
        
        $checklist_items = $inspection->checklist_items ? json_decode($inspection->checklist_items, true) : array();
        
        // Find and update the item
        $item_found = false;
        foreach ($checklist_items as &$item) {
            if ($item['item_id'] == $params['item_id']) {
                $item['response'] = sanitize_text_field($params['response']);
                $item['severity'] = sanitize_text_field($params['severity'] ?? null);
                $item['notes'] = wp_kses_post($params['notes'] ?? '');
                $item['photo_urls'] = $params['photo_urls'] ?? array();
                $item_found = true;
                break;
            }
        }
        
        if (!$item_found) {
            $checklist_items[] = array(
                'item_id' => intval($params['item_id']),
                'response' => sanitize_text_field($params['response']),
                'severity' => sanitize_text_field($params['severity'] ?? null),
                'notes' => wp_kses_post($params['notes'] ?? ''),
                'photo_urls' => $params['photo_urls'] ?? array()
            );
        }
        
        $this->wpdb->update($table, array('checklist_items' => json_encode($checklist_items)), array('id' => $id));
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_inspection_by_id($id)), 200);
    }
    
    public function get_checklist_templates($request) {
        $table = $this->tables['safety_checklist_templates'];
        $results = $this->wpdb->get_results("SELECT * FROM {$table} ORDER BY template_name ASC");
        
        foreach ($results as &$template) {
            if ($template->checklist_items) $template->checklist_items = json_decode($template->checklist_items, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_checklist_template($request) {
        $params = $request->get_json_params();
        $table = $this->tables['safety_checklist_templates'];
        
        $data = array(
            'template_name' => sanitize_text_field($params['template_name']),
            'template_type' => sanitize_text_field($params['template_type'] ?? 'general'),
            'checklist_items' => json_encode($params['checklist_items'] ?? array())
        );
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create template', array('status' => 500));
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->wpdb->insert_id), 201);
    }
    
    private function get_inspection_by_id($id) {
        $table = $this->tables['safety_inspections'];
        $inspection = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($inspection) {
            if ($inspection->checklist_items) $inspection->checklist_items = json_decode($inspection->checklist_items, true);
            if ($inspection->findings) $inspection->findings = json_decode($inspection->findings, true);
        }
        return $inspection;
    }
    
    // OBSERVATION ROUTES
    private function register_observation_routes() {
        register_rest_route($this->namespace, '/safety/observations', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_observations'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_observation'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/observations/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_observation'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_observation'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_observation'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/observations/(?P<id>\d+)/resolve', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'resolve_observation'), 'permission_callback' => array($this, 'check_job_permission'))
        );
    }
    
    public function get_observations($request) {
        $job_id = $request->get_param('job_id');
        $status = $request->get_param('status');
        $limit = intval($request->get_param('limit', 25));
        $table = $this->tables['safety_observations'];
        $where = array('1=1');
        $params = array();
        
        if ($job_id) { $where[] = 'job_id = %d'; $params[] = intval($job_id); }
        if ($status) { $where[] = 'status = %s'; $params[] = sanitize_text_field($status); }
        
        $where_clause = implode(' AND ', $where);
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY observation_date DESC LIMIT %d", array_merge($params, array($limit)));
        $results = $this->wpdb->get_results($sql);
        
        foreach ($results as &$obs) {
            if ($obs->photo_urls) $obs->photo_urls = json_decode($obs->photo_urls, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_observation($request) {
        $params = $request->get_json_params();
        $table = $this->tables['safety_observations'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'observer_id' => intval($params['observer_id'] ?? get_current_user_id()),
            'observation_type' => sanitize_text_field($params['observation_type']),
            'severity' => sanitize_text_field($params['severity'] ?? 'low'),
            'description' => wp_kses_post($params['description']),
            'location_on_site' => sanitize_text_field($params['location_on_site'] ?? null),
            'assigned_to' => intval($params['assigned_to'] ?? null),
            'due_date' => sanitize_text_field($params['due_date'] ?? null),
            'status' => 'open'
        );
        
        if (isset($params['photo_urls'])) $data['photo_urls'] = json_encode($params['photo_urls']);
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create observation', array('status' => 500));
        
        $observation_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'observation', $observation_id, 'created', 'Observation created', null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_observation_by_id($observation_id)), 201);
    }
    
    public function get_observation($request) {
        $id = intval($request->get_param('id'));
        $observation = $this->get_observation_by_id($id);
        if (!$observation) return new WP_Error('not_found', 'Observation not found', array('status' => 404));
        return new WP_REST_Response(array('success' => true, 'data' => $observation), 200);
    }
    
    public function update_observation($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_observations'];
        $observation = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$observation) return new WP_Error('not_found', 'Observation not found', array('status' => 404));
        
        $data = array();
        $allowed = array('observation_type', 'severity', 'description', 'assigned_to', 'due_date', 'status');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
            $this->log_safety_activity($observation->job_id, 'observation', $id, 'updated', 'Observation updated', null, json_encode($data), get_current_user_id());
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_observation_by_id($id)), 200);
    }
    
    public function delete_observation($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['safety_observations'];
        $observation = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$observation) return new WP_Error('not_found', 'Observation not found', array('status' => 404));
        
        $this->wpdb->delete($table, array('id' => $id));
        $this->log_safety_activity($observation->job_id, 'observation', $id, 'deleted', 'Observation deleted', json_encode($observation), null, get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'message' => 'Observation deleted'), 200);
    }
    
    public function resolve_observation($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_observations'];
        $observation = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$observation) return new WP_Error('not_found', 'Observation not found', array('status' => 404));
        
        $data = array('status' => 'resolved', 'resolution_notes' => wp_kses_post($params['resolution_notes'] ?? ''), 'closed_by' => get_current_user_id(), 'closed_date' => current_time('mysql'));
        $this->wpdb->update($table, $data, array('id' => $id));
        $this->log_safety_activity($observation->job_id, 'observation', $id, 'resolved', 'Observation resolved', $observation->status, 'resolved', get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_observation_by_id($id)), 200);
    }
    
    private function get_observation_by_id($id) {
        $table = $this->tables['safety_observations'];
        $observation = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($observation && $observation->photo_urls) $observation->photo_urls = json_decode($observation->photo_urls, true);
        return $observation;
    }
    
    // PERMIT ROUTES - Basic CRUD + approve/extend/close + conflicts + suspend
    private function register_permit_routes() {
        register_rest_route($this->namespace, '/safety/permits', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_permits'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_permit'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/permits/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_permit'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_permit'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_permit'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/permits/(?P<id>\d+)/approve', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'approve_permit'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/permits/(?P<id>\d+)/extend', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'extend_permit'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/permits/(?P<id>\d+)/close', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'close_permit'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/permits/(?P<id>\d+)/suspend', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'suspend_permit'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/permits/conflicts', array(
            'methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'check_permit_conflicts'), 'permission_callback' => array($this, 'check_job_permission'))
        );
    }
    
    public function suspend_permit($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['permits_to_work'];
        $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$permit) return new WP_Error('not_found', 'Permit not found', array('status' => 404));
        
        $this->wpdb->update($table, array('status' => 'suspended', 'suspended_at' => current_time('mysql'), 'suspended_by' => get_current_user_id()), array('id' => $id));
        $this->log_safety_activity($permit->job_id, 'permit', $id, 'suspended', 'Permit suspended (emergency stop)', $permit->status, 'suspended', get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_permit_by_id($id)), 200);
    }
    
    public function check_permit_conflicts($request) {
        $job_id = intval($request->get_param('job_id'));
        $location = sanitize_text_field($request->get_param('location'));
        $start = sanitize_text_field($request->get_param('start'));
        $end = sanitize_text_field($request->get_param('end'));
        
        $table = $this->tables['permits_to_work'];
        $where = array('1=1');
        $params = array();
        
        if ($job_id) { $where[] = 'job_id = %d'; $params[] = $job_id; }
        if ($location) { $where[] = 'location_on_site = %s'; $params[] = $location; }
        
        $where_clause = implode(' AND ', $where);
        
        // Check for overlapping time periods with active permits
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_clause} AND status IN ('approved', 'active') 
             AND ((start_datetime <= %s AND end_datetime >= %s) OR (start_datetime <= %s AND end_datetime >= %s) OR (start_datetime >= %s AND end_datetime <= %s))",
            array_merge($params, array($start, $start, $end, $end, $start, $end))
        );
        
        $conflicts = $this->wpdb->get_results($sql);
        
        foreach ($conflicts as &$permit) {
            if ($permit->hazards_identified) $permit->hazards_identified = json_decode($permit->hazards_identified, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $conflicts), 200);
    }
    
    public function get_permits($request) {
        $job_id = $request->get_param('job_id');
        $status = $request->get_param('status');
        $limit = intval($request->get_param('limit', 25));
        $table = $this->tables['permits_to_work'];
        $where = array('1=1');
        $params = array();
        
        if ($job_id) { $where[] = 'job_id = %d'; $params[] = intval($job_id); }
        if ($status) { $where[] = 'status = %s'; $params[] = sanitize_text_field($status); }
        
        $where_clause = implode(' AND ', $where);
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY start_datetime DESC LIMIT %d", array_merge($params, array($limit)));
        $results = $this->wpdb->get_results($sql);
        
        foreach ($results as &$permit) {
            if ($permit->hazards_identified) $permit->hazards_identified = json_decode($permit->hazards_identified, true);
            if ($permit->approval_chain) $permit->approval_chain = json_decode($permit->approval_chain, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_permit($request) {
        $params = $request->get_json_params();
        $table = $this->tables['permits_to_work'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'permit_type' => sanitize_text_field($params['permit_type']),
            'requestor_id' => intval($params['requestor_id'] ?? get_current_user_id()),
            'location_on_site' => sanitize_text_field($params['location_on_site'] ?? null),
            'start_datetime' => sanitize_text_field($params['start_datetime']),
            'end_datetime' => sanitize_text_field($params['end_datetime']),
            'work_description' => wp_kses_post($params['work_description'] ?? ''),
            'status' => 'draft'
        );
        
        if (isset($params['hazards_identified'])) $data['hazards_identified'] = json_encode($params['hazards_identified']);
        if (isset($params['required_ppe'])) $data['required_ppe'] = json_encode($params['required_ppe']);
        if (isset($params['precautions_taken'])) $data['precautions_taken'] = json_encode($params['precautions_taken']);
        if (isset($params['isolation_requirements'])) $data['isolation_requirements'] = json_encode($params['isolation_requirements']);
        if (isset($params['atmospheric_testing'])) $data['atmospheric_testing'] = json_encode($params['atmospheric_testing']);
        if (isset($params['equipment_ids'])) $data['equipment_ids'] = json_encode($params['equipment_ids']);
        if (isset($params['conflict_permit_ids'])) $data['conflict_permit_ids'] = json_encode($params['conflict_permit_ids']);
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create permit', array('status' => 500));
        
        $permit_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'permit', $permit_id, 'created', 'Permit created', null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_permit_by_id($permit_id)), 201);
    }
    
    public function get_permit($request) {
        $id = intval($request->get_param('id'));
        $permit = $this->get_permit_by_id($id);
        if (!$permit) return new WP_Error('not_found', 'Permit not found', array('status' => 404));
        return new WP_REST_Response(array('success' => true, 'data' => $permit), 200);
    }
    
    public function update_permit($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['permits_to_work'];
        $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$permit) return new WP_Error('not_found', 'Permit not found', array('status' => 404));
        
        $data = array();
        $allowed = array('permit_type', 'location_on_site', 'start_datetime', 'end_datetime', 'work_description', 'status');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        if (isset($params['hazards_identified'])) $data['hazards_identified'] = json_encode($params['hazards_identified']);
        if (isset($params['required_ppe'])) $data['required_ppe'] = json_encode($params['required_ppe']);
        if (isset($params['precautions_taken'])) $data['precautions_taken'] = json_encode($params['precautions_taken']);
        if (isset($params['isolation_requirements'])) $data['isolation_requirements'] = json_encode($params['isolation_requirements']);
        if (isset($params['atmospheric_testing'])) $data['atmospheric_testing'] = json_encode($params['atmospheric_testing']);
        if (isset($params['equipment_ids'])) $data['equipment_ids'] = json_encode($params['equipment_ids']);
        if (isset($params['conflict_permit_ids'])) $data['conflict_permit_ids'] = json_encode($params['conflict_permit_ids']);
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
            $this->log_safety_activity($permit->job_id, 'permit', $id, 'updated', 'Permit updated', null, json_encode($data), get_current_user_id());
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_permit_by_id($id)), 200);
    }
    
    public function delete_permit($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['permits_to_work'];
        $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$permit) return new WP_Error('not_found', 'Permit not found', array('status' => 404));
        
        $this->wpdb->delete($table, array('id' => $id));
        $this->log_safety_activity($permit->job_id, 'permit', $id, 'deleted', 'Permit deleted', json_encode($permit), null, get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'message' => 'Permit deleted'), 200);
    }
    
    public function approve_permit($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['permits_to_work'];
        $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$permit) return new WP_Error('not_found', 'Permit not found', array('status' => 404));
        
        $approval_chain = $permit->approval_chain ? json_decode($permit->approval_chain, true) : array();
        $approval_chain[] = array('approver_id' => get_current_user_id(), 'status' => 'approved', 'timestamp' => current_time('mysql'));
        
        $this->wpdb->update($table, array('approval_chain' => json_encode($approval_chain), 'status' => 'approved'), array('id' => $id));
        $this->log_safety_activity($permit->job_id, 'permit', $id, 'approved', 'Permit approved', $permit->status, 'approved', get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_permit_by_id($id)), 200);
    }
    
    public function extend_permit($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['permits_to_work'];
        $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$permit) return new WP_Error('not_found', 'Permit not found', array('status' => 404));
        if ($permit->extension_count >= $permit->max_extensions) return new WP_Error('max_extensions', 'Maximum extensions reached', array('status' => 400));
        
        $this->wpdb->update($table, array('end_datetime' => sanitize_text_field($params['end_datetime']), 'extension_count' => $permit->extension_count + 1, 'status' => 'extended'), array('id' => $id));
        $this->log_safety_activity($permit->job_id, 'permit', $id, 'extended', 'Permit extended', $permit->end_datetime, $params['end_datetime'], get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_permit_by_id($id)), 200);
    }
    
    public function close_permit($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['permits_to_work'];
        $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$permit) return new WP_Error('not_found', 'Permit not found', array('status' => 404));
        
        $data = array('status' => 'closed', 'closure_signature' => sanitize_text_field($params['closure_signature'] ?? null), 'closure_notes' => wp_kses_post($params['closure_notes'] ?? ''));
        $this->wpdb->update($table, $data, array('id' => $id));
        $this->log_safety_activity($permit->job_id, 'permit', $id, 'closed', 'Permit closed', $permit->status, 'closed', get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_permit_by_id($id)), 200);
    }
    
    private function get_permit_by_id($id) {
        $table = $this->tables['permits_to_work'];
        $permit = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($permit) {
            if ($permit->hazards_identified) $permit->hazards_identified = json_decode($permit->hazards_identified, true);
            if ($permit->approval_chain) $permit->approval_chain = json_decode($permit->approval_chain, true);
        }
        return $permit;
    }
    
    // JHA ROUTES
    private function register_jha_routes() {
        register_rest_route($this->namespace, '/safety/jha', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_jhas'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_jha'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/jha/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_jha'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_jha'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_jha'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/jha/(?P<id>\d+)/acknowledge', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'acknowledge_jha'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/jha/(?P<id>\d+)/revise', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'revise_jha'), 'permission_callback' => array($this, 'check_job_permission'))
        );
    }
    
    public function revise_jha($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['job_hazard_analyses'];
        $original_jha = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$original_jha) return new WP_Error('not_found', 'JHA not found', array('status' => 404));
        
        // Clone the JHA with incremented revision number
        $data = array(
            'job_id' => $original_jha->job_id,
            'task_name' => $original_jha->task_name,
            'task_description' => $original_jha->task_description,
            'trade_involved' => $original_jha->trade_involved,
            'supervisor_id' => $original_jha->supervisor_id,
            'preparation_date' => current_time('mysql'),
            'parent_jha_id' => $id,
            'revision_number' => ($original_jha->revision_number ?? 0) + 1,
            'approval_status' => 'draft',
            'steps' => $original_jha->steps
        );
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create JHA revision', array('status' => 500));
        
        $new_jha_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'jha', $new_jha_id, 'revised', 'JHA revised from #' . $id, null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_jha_by_id($new_jha_id)), 201);
    }
    
    public function get_jhas($request) {
        $job_id = $request->get_param('job_id');
        $limit = intval($request->get_param('limit', 25));
        $table = $this->tables['job_hazard_analyses'];
        $where = $job_id ? 'job_id = ' . intval($job_id) : '1=1';
        $results = $this->wpdb->get_results("SELECT * FROM {$table} WHERE {$where} ORDER BY preparation_date DESC LIMIT {$limit}");
        
        foreach ($results as &$jha) {
            if ($jha->steps) $jha->steps = json_decode($jha->steps, true);
            if ($jha->digital_acknowledgments) $jha->digital_acknowledgments = json_decode($jha->digital_acknowledgments, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_jha($request) {
        $params = $request->get_json_params();
        $table = $this->tables['job_hazard_analyses'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'task_name' => sanitize_text_field($params['task_name']),
            'task_description' => wp_kses_post($params['task_description'] ?? ''),
            'trade_involved' => sanitize_text_field($params['trade_involved'] ?? null),
            'supervisor_id' => intval($params['supervisor_id'] ?? null),
            'preparation_date' => sanitize_text_field($params['preparation_date'] ?? current_time('mysql')),
            'approval_status' => 'draft'
        );
        
        if (isset($params['steps'])) $data['steps'] = json_encode($params['steps']);
        if (isset($params['required_training_ids'])) $data['required_training_ids'] = json_encode($params['required_training_ids']);
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create JHA', array('status' => 500));
        
        $jha_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'jha', $jha_id, 'created', 'JHA created', null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_jha_by_id($jha_id)), 201);
    }
    
    public function get_jha($request) {
        $id = intval($request->get_param('id'));
        $jha = $this->get_jha_by_id($id);
        if (!$jha) return new WP_Error('not_found', 'JHA not found', array('status' => 404));
        return new WP_REST_Response(array('success' => true, 'data' => $jha), 200);
    }
    
    public function update_jha($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['job_hazard_analyses'];
        $jha = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$jha) return new WP_Error('not_found', 'JHA not found', array('status' => 404));
        
        $data = array();
        $allowed = array('task_name', 'task_description', 'trade_involved', 'supervisor_id', 'approval_status');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        if (isset($params['steps'])) $data['steps'] = json_encode($params['steps']);
        if (isset($params['required_training_ids'])) $data['required_training_ids'] = json_encode($params['required_training_ids']);
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
            $this->log_safety_activity($jha->job_id, 'jha', $id, 'updated', 'JHA updated', null, json_encode($data), get_current_user_id());
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_jha_by_id($id)), 200);
    }
    
    public function delete_jha($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['job_hazard_analyses'];
        $jha = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$jha) return new WP_Error('not_found', 'JHA not found', array('status' => 404));
        
        $this->wpdb->delete($table, array('id' => $id));
        $this->log_safety_activity($jha->job_id, 'jha', $id, 'deleted', 'JHA deleted', json_encode($jha), null, get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'message' => 'JHA deleted'), 200);
    }
    
    public function acknowledge_jha($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['job_hazard_analyses'];
        $jha = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$jha) return new WP_Error('not_found', 'JHA not found', array('status' => 404));
        
        $acknowledgments = $jha->digital_acknowledgments ? json_decode($jha->digital_acknowledgments, true) : array();
        $acknowledgments[] = array('worker_id' => get_current_user_id(), 'signature' => $params['signature'] ?? null, 'timestamp' => current_time('mysql'));
        
        $this->wpdb->update($table, array('digital_acknowledgments' => json_encode($acknowledgments)), array('id' => $id));
        $this->log_safety_activity($jha->job_id, 'jha', $id, 'acknowledged', 'JHA acknowledged', null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_jha_by_id($id)), 200);
    }
    
    private function get_jha_by_id($id) {
        $table = $this->tables['job_hazard_analyses'];
        $jha = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($jha) {
            if ($jha->steps) $jha->steps = json_decode($jha->steps, true);
            if ($jha->digital_acknowledgments) $jha->digital_acknowledgments = json_decode($jha->digital_acknowledgments, true);
            if ($jha->required_training_ids) $jha->required_training_ids = json_decode($jha->required_training_ids, true);
        }
        return $jha;
    }
    
    // TOOLBOX TALK ROUTES
    private function register_toolbox_talk_routes() {
        register_rest_route($this->namespace, '/safety/toolbox-talks', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_toolbox_talks'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_toolbox_talk'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/toolbox-talks/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_toolbox_talk'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_toolbox_talk'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_toolbox_talk'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/toolbox-talks/(?P<id>\d+)/attend', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'attend_toolbox_talk'), 'permission_callback' => array($this, 'check_job_permission'))
        );
    }
    
    public function attend_toolbox_talk($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['toolbox_talks'];
        $talk = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$talk) return new WP_Error('not_found', 'Toolbox talk not found', array('status' => 404));
        
        $attendance = $talk->attendance ? json_decode($talk->attendance, true) : array();
        $new_attendance = $params['attendance'] ?? array();
        
        // Merge or replace attendance records
        foreach ($new_attendance as $att) {
            $existing_index = array_search($att['worker_id'], array_column($attendance, 'worker_id'));
            if ($existing_index !== false) {
                $attendance[$existing_index] = $att;
            } else {
                $attendance[] = $att;
            }
        }
        
        $this->wpdb->update($table, array('attendance' => json_encode($attendance)), array('id' => $id));
        $this->log_safety_activity($talk->job_id, 'toolbox_talk', $id, 'attendance_updated', 'Toolbox talk attendance updated', null, json_encode($attendance), get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_toolbox_talk_by_id($id)), 200);
    }
    
    public function get_toolbox_talks($request) {
        $job_id = $request->get_param('job_id');
        $limit = intval($request->get_param('limit', 25));
        $table = $this->tables['toolbox_talks'];
        $where = $job_id ? 'job_id = ' . intval($job_id) : '1=1';
        $results = $this->wpdb->get_results("SELECT * FROM {$table} WHERE {$where} ORDER BY scheduled_date DESC LIMIT {$limit}");
        
        foreach ($results as &$talk) {
            if ($talk->attendance) $talk->attendance = json_decode($talk->attendance, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_toolbox_talk($request) {
        $params = $request->get_json_params();
        $table = $this->tables['toolbox_talks'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'topic' => sanitize_text_field($params['topic']),
            'category' => sanitize_text_field($params['category'] ?? null),
            'content_body' => wp_kses_post($params['content_body'] ?? ''),
            'presenter_id' => intval($params['presenter_id'] ?? get_current_user_id()),
            'scheduled_date' => sanitize_text_field($params['scheduled_date'] ?? current_time('mysql')),
            'duration_minutes' => intval($params['duration_minutes'] ?? 15),
            'status' => 'scheduled'
        );
        
        if (isset($params['attendance'])) $data['attendance'] = json_encode($params['attendance']);
        if (isset($params['questions_asked'])) $data['questions_asked'] = json_encode($params['questions_asked']);
        if (isset($params['photos'])) $data['photos'] = json_encode($params['photos']);
        if (isset($params['required_for_trade'])) $data['required_for_trade'] = json_encode($params['required_for_trade']);
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create toolbox talk', array('status' => 500));
        
        $talk_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'toolbox_talk', $talk_id, 'created', 'Toolbox talk created', null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_toolbox_talk_by_id($talk_id)), 201);
    }
    
    public function get_toolbox_talk($request) {
        $id = intval($request->get_param('id'));
        $talk = $this->get_toolbox_talk_by_id($id);
        if (!$talk) return new WP_Error('not_found', 'Toolbox talk not found', array('status' => 404));
        return new WP_REST_Response(array('success' => true, 'data' => $talk), 200);
    }
    
    public function update_toolbox_talk($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['toolbox_talks'];
        $talk = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$talk) return new WP_Error('not_found', 'Toolbox talk not found', array('status' => 404));
        
        $data = array();
        $allowed = array('topic', 'category', 'content_body', 'scheduled_date', 'status');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        if (isset($params['attendance'])) $data['attendance'] = json_encode($params['attendance']);
        if (isset($params['questions_asked'])) $data['questions_asked'] = json_encode($params['questions_asked']);
        if (isset($params['photos'])) $data['photos'] = json_encode($params['photos']);
        if (isset($params['required_for_trade'])) $data['required_for_trade'] = json_encode($params['required_for_trade']);
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
            $this->log_safety_activity($talk->job_id, 'toolbox_talk', $id, 'updated', 'Toolbox talk updated', null, json_encode($data), get_current_user_id());
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->get_toolbox_talk_by_id($id)), 200);
    }
    
    public function delete_toolbox_talk($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['toolbox_talks'];
        $talk = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$talk) return new WP_Error('not_found', 'Toolbox talk not found', array('status' => 404));
        
        $this->wpdb->delete($table, array('id' => $id));
        $this->log_safety_activity($talk->job_id, 'toolbox_talk', $id, 'deleted', 'Toolbox talk deleted', json_encode($talk), null, get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'message' => 'Toolbox talk deleted'), 200);
    }
    
    private function get_toolbox_talk_by_id($id) {
        $table = $this->tables['toolbox_talks'];
        $talk = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($talk) {
            if ($talk->attendance) $talk->attendance = json_decode($talk->attendance, true);
            if ($talk->questions_asked) $talk->questions_asked = json_decode($talk->questions_asked, true);
            if ($talk->photos) $talk->photos = json_decode($talk->photos, true);
            if ($talk->required_for_trade) $talk->required_for_trade = json_decode($talk->required_for_trade, true);
        }
        return $talk;
    }
    
    // CERTIFICATION ROUTES (Enhanced existing)
    private function register_certification_routes() {
        register_rest_route($this->namespace, '/safety/certifications', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_certifications'), 'permission_callback' => array($this, 'check_general_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_certification'), 'permission_callback' => array($this, 'check_general_permission'))
        ));
        register_rest_route($this->namespace, '/safety/certifications/expiring', array(
            'methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_expiring_certifications'), 'permission_callback' => array($this, 'check_general_permission'))
        );
        register_rest_route($this->namespace, '/safety/certifications/matrix', array(
            'methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_certifications_matrix'), 'permission_callback' => array($this, 'check_general_permission'))
        );
    }
    
    public function get_certifications_matrix($request) {
        $job_id = intval($request->get_param('job_id'));
        $employees_table = $this->tables['employees'];
        $certs_table = $this->tables['certifications'];
        
        // Get all employees for the job
        $employees = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, first_name, last_name FROM {$employees_table} WHERE status = 'active'"
        ));
        
        // Get all unique certification types
        $cert_types = $this->wpdb->get_results("SELECT DISTINCT certification_name FROM {$certs_table} WHERE status = 'valid' ORDER BY certification_name ASC");
        
        // Build matrix
        $matrix = array();
        foreach ($employees as $emp) {
            $row = array(
                'employee_id' => $emp->id,
                'employee_name' => $emp->first_name . ' ' . $emp->last_name,
                'certifications' => array()
            );
            
            foreach ($cert_types as $cert_type) {
                $cert = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT * FROM {$certs_table} WHERE user_id = %d AND certification_name = %s ORDER BY expiry_date DESC LIMIT 1",
                    $emp->id, $cert_type->certification_name
                ));
                
                if ($cert) {
                    $days_until_expiry = floor((strtotime($cert->expiry_date) - time()) / (60 * 60 * 24));
                    $status = $days_until_expiry < 30 ? 'expiring' : 'valid';
                    $row['certifications'][$cert_type->certification_name] = array(
                        'status' => $status,
                        'expiry_date' => $cert->expiry_date,
                        'days_remaining' => $days_until_expiry
                    );
                } else {
                    $row['certifications'][$cert_type->certification_name] = array('status' => 'missing');
                }
            }
            
            $matrix[] = $row;
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => array('employees' => $employees, 'cert_types' => $cert_types, 'matrix' => $matrix)), 200);
    }
    
    public function get_certifications($request) {
        $employee_id = $request->get_param('employee_id');
        $status = $request->get_param('status');
        $limit = intval($request->get_param('limit', 25));
        $table = $this->tables['certifications'];
        $where = array('1=1');
        $params = array();
        
        if ($employee_id) { $where[] = 'user_id = %d'; $params[] = intval($employee_id); }
        if ($status) { $where[] = 'status = %s'; $params[] = sanitize_text_field($status); }
        
        $where_clause = implode(' AND ', $where);
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY expiry_date ASC LIMIT %d", array_merge($params, array($limit)));
        $results = $this->wpdb->get_results($sql);
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_certification($request) {
        $params = $request->get_json_params();
        $table = $this->tables['certifications'];
        
        $data = array(
            'user_id' => intval($params['user_id']),
            'certification_name' => sanitize_text_field($params['certification_name']),
            'issuing_body' => sanitize_text_field($params['issuing_body']),
            'issue_date' => sanitize_text_field($params['issue_date']),
            'expiry_date' => sanitize_text_field($params['expiry_date']),
            'certificate_number' => sanitize_text_field($params['certificate_number'] ?? null),
            'reminder_days' => intval($params['reminder_days'] ?? 30),
            'status' => 'valid'
        );
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create certification', array('status' => 500));
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->wpdb->insert_id), 201);
    }
    
    public function get_expiring_certifications($request) {
        $days = intval($request->get_param('days', 30));
        $table = $this->tables['certifications'];
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY) AND status = 'valid' ORDER BY expiry_date ASC",
            $days
        ));
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    // PPE ROUTES
    private function register_ppe_routes() {
        register_rest_route($this->namespace, '/safety/ppe', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_ppe'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_ppe'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/ppe/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_ppe'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_ppe'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/ppe/(?P<id>\d+)/issue', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'issue_ppe'), 'permission_callback' => array($this, 'check_job_permission'))
        );
        register_rest_route($this->namespace, '/safety/ppe/(?P<id>\d+)/inspect', array(
            'methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'inspect_ppe'), 'permission_callback' => array($this, 'check_job_permission'))
        );
    }
    
    public function issue_ppe($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['ppe_inventory'];
        $ppe = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$ppe) return new WP_Error('not_found', 'PPE not found', array('status' => 404));
        if ($ppe->quantity_available < 1) return new WP_Error('out_of_stock', 'No PPE available', array('status' => 400));
        
        $data = array(
            'assigned_to_worker_id' => intval($params['assigned_to_worker_id']),
            'issue_date' => sanitize_text_field($params['issue_date'] ?? current_time('mysql')),
            'expected_return_date' => sanitize_text_field($params['expected_return_date'] ?? null),
            'quantity_issued' => $ppe->quantity_issued + 1,
            'quantity_available' => $ppe->quantity_available - 1
        );
        
        $this->wpdb->update($table, $data, array('id' => $id));
        $this->log_safety_activity($ppe->job_id ?? 0, 'ppe', $id, 'issued', 'PPE issued to worker ' . $params['assigned_to_worker_id'], $ppe->quantity_issued, $data['quantity_issued'], get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id))), 200);
    }
    
    public function inspect_ppe($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['ppe_inventory'];
        $ppe = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$ppe) return new WP_Error('not_found', 'PPE not found', array('status' => 404));
        
        $data = array(
            'condition' => sanitize_text_field($params['condition'] ?? $ppe->condition),
            'inspection_notes' => wp_kses_post($params['notes'] ?? ''),
            'next_inspection_date' => sanitize_text_field($params['next_inspection_date'] ?? null),
            'last_inspection_date' => current_time('mysql')
        );
        
        $this->wpdb->update($table, $data, array('id' => $id));
        $this->log_safety_activity($ppe->job_id ?? 0, 'ppe', $id, 'inspected', 'PPE inspected', $ppe->condition, $data['condition'], get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id))), 200);
    }
    
    public function get_ppe($request) {
        $job_id = $request->get_param('job_id');
        $limit = intval($request->get_param('limit', 25));
        $table = $this->tables['ppe_inventory'];
        $where = $job_id ? 'job_id = ' . intval($job_id) : '1=1';
        $results = $this->wpdb->get_results("SELECT * FROM {$table} WHERE {$where} LIMIT {$limit}");
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_ppe($request) {
        $params = $request->get_json_params();
        $table = $this->tables['ppe_inventory'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'ppe_type' => sanitize_text_field($params['ppe_type']),
            'size' => sanitize_text_field($params['size'] ?? null),
            'quantity_issued' => intval($params['quantity_issued'] ?? 0),
            'quantity_available' => intval($params['quantity_available'] ?? 0),
            'condition' => sanitize_text_field($params['condition'] ?? 'good'),
            'assigned_to_worker_id' => intval($params['assigned_to_worker_id'] ?? null)
        );
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create PPE record', array('status' => 500));
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->wpdb->insert_id), 201);
    }
    
    public function update_ppe($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['ppe_inventory'];
        
        $data = array();
        $allowed = array('ppe_type', 'size', 'quantity_issued', 'quantity_available', 'condition', 'assigned_to_worker_id', 'next_inspection_date');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_ppe($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['ppe_inventory'];
        $this->wpdb->delete($table, array('id' => $id));
        return new WP_REST_Response(array('success' => true, 'message' => 'PPE record deleted'), 200);
    }
    
    // MEETING ROUTES
    private function register_meeting_routes() {
        register_rest_route($this->namespace, '/safety/meetings', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_meetings'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'create_meeting'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
        register_rest_route($this->namespace, '/safety/meetings/(?P<id>\d+)', array(
            array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_meeting'), 'permission_callback' => array($this, 'check_job_permission')),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array($this, 'delete_meeting'), 'permission_callback' => array($this, 'check_job_permission'))
        ));
    }
    
    public function get_meetings($request) {
        $job_id = $request->get_param('job_id');
        $limit = intval($request->get_param('limit', 25));
        $table = $this->tables['safety_meetings'];
        $where = $job_id ? 'job_id = ' . intval($job_id) : '1=1';
        $results = $this->wpdb->get_results("SELECT * FROM {$table} WHERE {$where} ORDER BY scheduled_date DESC LIMIT {$limit}");
        
        foreach ($results as &$meeting) {
            if ($meeting->attendees) $meeting->attendees = json_decode($meeting->attendees, true);
            if ($meeting->action_items) $meeting->action_items = json_decode($meeting->action_items, true);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function create_meeting($request) {
        $params = $request->get_json_params();
        $table = $this->tables['safety_meetings'];
        
        $data = array(
            'job_id' => intval($params['job_id']),
            'meeting_type' => sanitize_text_field($params['meeting_type'] ?? 'safety_committee'),
            'scheduled_date' => sanitize_text_field($params['scheduled_date'] ?? current_time('mysql')),
            'minutes' => wp_kses_post($params['minutes'] ?? '')
        );
        
        if (isset($params['attendees'])) $data['attendees'] = json_encode($params['attendees']);
        if (isset($params['action_items'])) $data['action_items'] = json_encode($params['action_items']);
        
        $result = $this->wpdb->insert($table, $data);
        if ($result === false) return new WP_Error('db_error', 'Failed to create meeting', array('status' => 500));
        
        $meeting_id = $this->wpdb->insert_id;
        $this->log_safety_activity($data['job_id'], 'meeting', $meeting_id, 'created', 'Meeting created', null, null, get_current_user_id());
        
        return new WP_REST_Response(array('success' => true, 'data' => $this->wpdb->insert_id), 201);
    }
    
    public function update_meeting($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $table = $this->tables['safety_meetings'];
        $meeting = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$meeting) return new WP_Error('not_found', 'Meeting not found', array('status' => 404));
        
        $data = array();
        $allowed = array('meeting_type', 'scheduled_date', 'minutes');
        foreach ($allowed as $field) {
            if (isset($params[$field])) $data[$field] = sanitize_text_field($params[$field]);
        }
        if (isset($params['attendees'])) $data['attendees'] = json_encode($params['attendees']);
        if (isset($params['action_items'])) $data['action_items'] = json_encode($params['action_items']);
        
        if (!empty($data)) {
            $this->wpdb->update($table, $data, array('id' => $id));
            $this->log_safety_activity($meeting->job_id, 'meeting', $id, 'updated', 'Meeting updated', null, json_encode($data), get_current_user_id());
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    public function delete_meeting($request) {
        $id = intval($request->get_param('id'));
        $table = $this->tables['safety_meetings'];
        $meeting = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$meeting) return new WP_Error('not_found', 'Meeting not found', array('status' => 404));
        
        $this->wpdb->delete($table, array('id' => $id));
        $this->log_safety_activity($meeting->job_id, 'meeting', $id, 'deleted', 'Meeting deleted', json_encode($meeting), null, get_current_user_id());
        return new WP_REST_Response(array('success' => true, 'message' => 'Meeting deleted'), 200);
    }
    
    // DASHBOARD ROUTES
    private function register_dashboard_routes() {
        register_rest_route($this->namespace, '/safety/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_safety_dashboard'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array('job_id' => array('required' => true))
        ));
        register_rest_route($this->namespace, '/safety/alerts', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_safety_alerts'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array('job_id' => array('required' => true))
        ));
        register_rest_route($this->namespace, '/safety/activity-feed', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_activity_feed'),
            'permission_callback' => array($this, 'check_job_permission'),
            'args' => array('job_id' => array('required' => true))
        ));
    }
    
    public function get_safety_dashboard($request) {
        $job_id = intval($request->get_param('job_id'));
        
        // Calculate safety score
        $incidents_table = $this->tables['safety_incidents'];
        $inspections_table = $this->tables['safety_inspections'];
        $observations_table = $this->tables['safety_observations'];
        $permits_table = $this->tables['permits_to_work'];
        
        $open_incidents = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$incidents_table} WHERE job_id = %d AND status = 'open'", $job_id));
        $avg_inspection_score = $this->wpdb->get_var($this->wpdb->prepare("SELECT AVG(score) FROM {$inspections_table} WHERE job_id = %d AND score IS NOT NULL", $job_id));
        $active_permits = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$permits_table} WHERE job_id = %d AND status IN ('approved', 'active')", $job_id));
        $open_observations = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$observations_table} WHERE job_id = %d AND status = 'open'", $job_id));
        
        // Simple safety score calculation (0-100)
        $score = 100;
        $score -= min($open_incidents * 5, 30); // Up to 30 points deducted for open incidents
        $score -= min($open_observations * 2, 20); // Up to 20 points deducted for open observations
        if ($avg_inspection_score) $score = ($score + $avg_inspection_score) / 2;
        $score = max(0, min(100, $score));
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'safety_score' => round($score),
                'open_incidents' => intval($open_incidents),
                'active_permits' => intval($active_permits),
                'open_observations' => intval($open_observations),
                'avg_inspection_score' => $avg_inspection_score ? round($avg_inspection_score) : null
            )
        ), 200);
    }
    
    public function get_safety_alerts($request) {
        $job_id = intval($request->get_param('job_id'));
        $alerts = array();
        
        // Check for critical incidents
        $incidents_table = $this->tables['safety_incidents'];
        $critical_incidents = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$incidents_table} WHERE job_id = %d AND severity IN ('fatal', 'critical') AND status = 'open'",
            $job_id
        ));
        foreach ($critical_incidents as $inc) {
            $alerts[] = array('type' => 'critical', 'message' => 'Critical incident requires attention', 'entity_type' => 'incident', 'entity_id' => $inc->id);
        }
        
        // Check for expiring permits
        $permits_table = $this->tables['permits_to_work'];
        $expiring_permits = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$permits_table} WHERE job_id = %d AND status = 'approved' AND end_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)",
            $job_id
        ));
        foreach ($expiring_permits as $permit) {
            $alerts[] = array('type' => 'warning', 'message' => 'Permit expiring soon', 'entity_type' => 'permit', 'entity_id' => $permit->id);
        }
        
        return new WP_REST_Response(array('success' => true, 'data' => $alerts), 200);
    }
    
    public function get_activity_feed($request) {
        $job_id = intval($request->get_param('job_id'));
        $limit = intval($request->get_param('limit', 50));
        $table = $this->tables['safety_activity'];
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_id = %d ORDER BY performed_at DESC LIMIT %d",
            $job_id, $limit
        ));
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    // REPORT ROUTES
    private function register_report_routes() {
        register_rest_route($this->namespace, '/safety/reports/incidents', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_incident_report'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
        register_rest_route($this->namespace, '/safety/reports/inspections', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_inspection_report'),
            'permission_callback' => array($this, 'check_job_permission')
        ));
    }
    
    public function get_incident_report($request) {
        $job_id = intval($request->get_param('job_id'));
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        $table = $this->tables['safety_incidents'];
        $where = array('job_id = ' . intval($job_id));
        $params = array();
        
        if ($start_date) { $where[] = 'incident_date >= %s'; $params[] = sanitize_text_field($start_date); }
        if ($end_date) { $where[] = 'incident_date <= %s'; $params[] = sanitize_text_field($end_date); }
        
        $where_clause = implode(' AND ', $where);
        if (!empty($params)) {
            $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY incident_date DESC", $params);
        } else {
            $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY incident_date DESC";
        }
        
        $results = $this->wpdb->get_results($sql);
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
    
    public function get_inspection_report($request) {
        $job_id = intval($request->get_param('job_id'));
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        $table = $this->tables['safety_inspections'];
        $where = array('job_id = ' . intval($job_id));
        $params = array();
        
        if ($start_date) { $where[] = 'inspection_date >= %s'; $params[] = sanitize_text_field($start_date); }
        if ($end_date) { $where[] = 'inspection_date <= %s'; $params[] = sanitize_text_field($end_date); }
        
        $where_clause = implode(' AND ', $where);
        if (!empty($params)) {
            $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY inspection_date DESC", $params);
        } else {
            $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY inspection_date DESC";
        }
        
        $results = $this->wpdb->get_results($sql);
        
        return new WP_REST_Response(array('success' => true, 'data' => $results), 200);
    }
}
