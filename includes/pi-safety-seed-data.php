<?php
/**
 * Planning Index Safety Module - Seed Data
 * Sample data for testing the safety module
 */

if (!defined('ABSPATH')) exit;

class PI_Safety_Seed_Data {
    
    public static function seed() {
        global $wpdb;
        
        // Get some existing job IDs
        $job_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'pi_job' AND post_status = 'publish' LIMIT 3");
        
        if (empty($job_ids)) {
            echo "No jobs found to seed safety data. Please create some jobs first.\n";
            return;
        }
        
        $job_id = $job_ids[0];
        $current_user_id = get_current_user_id();
        
        echo "Seeding safety data for Job #{$job_id}...\n";
        
        // Seed checklist templates
        self::seed_checklist_templates();
        
        // Seed incidents
        self::seed_incidents($job_id, $current_user_id);
        
        // Seed observations
        self::seed_observations($job_id, $current_user_id);
        
        // Seed permits
        self::seed_permits($job_id, $current_user_id);
        
        // Seed JHAs
        self::seed_jhas($job_id, $current_user_id);
        
        // Seed toolbox talks
        self::seed_toolbox_talks($job_id, $current_user_id);
        
        // Seed PPE
        self::seed_ppe($job_id);
        
        // Seed meetings
        self::seed_meetings($job_id, $current_user_id);
        
        echo "Safety module seed data completed!\n";
    }
    
    private static function seed_checklist_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_safety_checklist_templates';
        
        $templates = array(
            array(
                'template_name' => 'General Site Safety',
                'inspection_type' => 'general',
                'category' => 'general',
                'is_default' => 1,
                'items' => json_encode(array(
                    array('item_id' => 1, 'question_text' => 'Are all workers wearing appropriate PPE?', 'response_type' => 'pass_fail', 'category' => 'PPE'),
                    array('item_id' => 2, 'question_text' => 'Are walkways clear and free of obstructions?', 'response_type' => 'pass_fail', 'category' => 'Housekeeping'),
                    array('item_id' => 3, 'question_text' => 'Are fire extinguishers accessible and unobstructed?', 'response_type' => 'pass_fail', 'category' => 'Fire Safety')
                ))
            ),
            array(
                'template_name' => 'Fall Protection',
                'inspection_type' => 'fall_protection',
                'category' => 'height_safety',
                'is_default' => 1,
                'items' => json_encode(array(
                    array('item_id' => 1, 'question_text' => 'Are guardrails installed on all open sides?', 'response_type' => 'pass_fail', 'category' => 'Guardrails'),
                    array('item_id' => 2, 'question_text' => 'Are personal fall arrest systems properly anchored?', 'response_type' => 'pass_fail', 'category' => 'PFAS')
                ))
            )
        );
        
        foreach ($templates as $template) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE template_name = %s",
                $template['template_name']
            ));
            
            if (!$exists) {
                $wpdb->insert($table, $template);
                echo "Created checklist template: {$template['template_name']}\n";
            }
        }
    }
    
    private static function seed_incidents($job_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_safety_incidents';
        
        $incidents = array(
            array(
                'job_id' => $job_id,
                'incident_date' => date('Y-m-d H:i:s', strtotime('-7 days')),
                'reported_by' => 'John Smith',
                'reporter_id' => $user_id,
                'incident_type' => 'slip_trip_fall',
                'severity' => 'minor',
                'description' => 'Worker slipped on wet floor in kitchen area. Minor bruising to elbow.',
                'classification' => 'first_aid_only',
                'location_on_site' => 'Kitchen Area, Floor 2',
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s', strtotime('-6 days')),
                'closed_by' => $user_id
            ),
            array(
                'job_id' => $job_id,
                'incident_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'reported_by' => 'Jane Doe',
                'reporter_id' => $user_id,
                'incident_type' => 'struck_by',
                'severity' => 'major',
                'description' => 'Worker struck by falling debris from scaffolding. Required medical treatment.',
                'classification' => 'recordable',
                'location_on_site' => 'Scaffolding, Building A Exterior',
                'status' => 'open'
            )
        );
        
        foreach ($incidents as $incident) {
            $wpdb->insert($table, $incident);
            echo "Created incident: {$incident['incident_type']}\n";
        }
    }
    
    private static function seed_observations($job_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_safety_observations';
        
        $observations = array(
            array(
                'job_id' => $job_id,
                'observer_id' => $user_id,
                'observation_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'observation_type' => 'unsafe_condition',
                'severity' => 'medium',
                'description' => 'Extension cord running across walkway creating tripping hazard.',
                'location_on_site' => 'Main Corridor, Floor 1',
                'status' => 'resolved',
                'resolution_notes' => 'Cord rerouted overhead.',
                'closed_by' => $user_id,
                'closed_date' => date('Y-m-d H:i:s', strtotime('-4 days'))
            ),
            array(
                'job_id' => $job_id,
                'observer_id' => $user_id,
                'observation_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'observation_type' => 'ppe_violation',
                'severity' => 'high',
                'description' => 'Worker observed without hard hat in active construction zone.',
                'location_on_site' => 'Building B, Ground Floor',
                'status' => 'open'
            )
        );
        
        foreach ($observations as $observation) {
            $wpdb->insert($table, $observation);
            echo "Created observation: {$observation['observation_type']}\n";
        }
    }
    
    private static function seed_permits($job_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_permits_to_work';
        
        $now = current_time('mysql');
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        $permits = array(
            array(
                'job_id' => $job_id,
                'permit_type' => 'hot_work',
                'requestor_id' => $user_id,
                'location_on_site' => 'Welding Area, Building B',
                'start_datetime' => $now,
                'end_datetime' => $tomorrow,
                'validity_duration_hours' => 24,
                'work_description' => 'Welding of steel beams on second floor',
                'hazards_identified' => json_encode(array('Sparks', 'Fumes', 'Heat')),
                'required_ppe' => json_encode(array('Welding helmet', 'Gloves', 'Fire resistant clothing')),
                'status' => 'approved',
                'approval_chain' => json_encode(array(array('approver_id' => $user_id, 'status' => 'approved', 'timestamp' => $now)))
            )
        );
        
        foreach ($permits as $permit) {
            $wpdb->insert($table, $permit);
            echo "Created permit: {$permit['permit_type']}\n";
        }
    }
    
    private static function seed_jhas($job_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_job_hazard_analyses';
        
        $jhas = array(
            array(
                'job_id' => $job_id,
                'task_name' => 'Concrete Pour - Building A Foundation',
                'task_description' => 'Pouring concrete foundation for Building A.',
                'trade_involved' => 'Concrete',
                'supervisor_id' => $user_id,
                'preparation_date' => date('Y-m-d'),
                'steps' => json_encode(array(
                    array('step_number' => 1, 'step_description' => 'Formwork inspection', 'hazards' => 'Form failure', 'risk_level' => 'medium'),
                    array('step_number' => 2, 'step_description' => 'Concrete placement', 'hazards' => 'Back injury', 'risk_level' => 'medium'),
                    array('step_number' => 3, 'step_description' => 'Finishing and curing', 'hazards' => 'Skin irritation', 'risk_level' => 'low')
                )),
                'approval_status' => 'approved',
                'approved_by' => $user_id
            )
        );
        
        foreach ($jhas as $jha) {
            $wpdb->insert($table, $jha);
            echo "Created JHA: {$jha['task_name']}\n";
        }
    }
    
    private static function seed_toolbox_talks($job_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_toolbox_talks';
        
        $talks = array(
            array(
                'job_id' => $job_id,
                'topic' => 'Fall Protection',
                'category' => 'fall_protection',
                'content_body' => 'Key points: Always use fall protection when working 6ft or above.',
                'presenter_id' => $user_id,
                'scheduled_date' => date('Y-m-d H:i:s', strtotime('-7 days')),
                'duration_minutes' => 15,
                'status' => 'completed'
            )
        );
        
        foreach ($talks as $talk) {
            $wpdb->insert($table, $talk);
            echo "Created toolbox talk: {$talk['topic']}\n";
        }
    }
    
    private static function seed_ppe($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_ppe_inventory';
        
        $ppe_items = array(
            array('job_id' => $job_id, 'ppe_type' => 'hard_hat', 'size' => 'M', 'quantity_issued' => 15, 'quantity_available' => 5, 'condition' => 'good'),
            array('job_id' => $job_id, 'ppe_type' => 'safety_glasses', 'size' => 'standard', 'quantity_issued' => 20, 'quantity_available' => 10, 'condition' => 'good'),
            array('job_id' => $job_id, 'ppe_type' => 'gloves', 'size' => 'L', 'quantity_issued' => 30, 'quantity_available' => 20, 'condition' => 'good')
        );
        
        foreach ($ppe_items as $ppe) {
            $wpdb->insert($table, $ppe);
            echo "Created PPE item: {$ppe['ppe_type']}\n";
        }
    }
    
    private static function seed_meetings($job_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'pi_crm_safety_meetings';
        
        $meetings = array(
            array(
                'job_id' => $job_id,
                'meeting_type' => 'safety_committee',
                'scheduled_date' => date('Y-m-d H:i:s', strtotime('-14 days')),
                'minutes' => 'Discussed incident trends, reviewed PPE compliance.',
                'attendees' => json_encode(array($user_id))
            )
        );
        
        foreach ($meetings as $meeting) {
            $wpdb->insert($table, $meeting);
            echo "Created meeting: {$meeting['meeting_type']}\n";
        }
    }
}

// Run seeder if accessed directly
if (defined('WP_CLI') || (isset($_GET['action']) && $_GET['action'] === 'seed_safety_data')) {
    PI_Safety_Seed_Data::seed();
}
