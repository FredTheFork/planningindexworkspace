<?php
/**
 * Planning Index Safety Module
 * Comprehensive construction safety management
 */

if (!defined('ABSPATH')) exit;

class PI_Safety_Module {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Register safety REST API
        PI_Safety_REST_API::get_instance()->register_routes();
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Register shortcode
        add_shortcode('pi_safety_module', array($this, 'render_shortcode'));
        
        // Add safety tab to job pages
        add_filter('pi_job_tabs', array($this, 'add_safety_tab'));
    }
    
    public function enqueue_assets() {
        // Always load on job pages or safety module pages
        if (is_singular('pi_job') || is_page() || has_shortcode(get_the_content(), 'pi_safety_module')) {
            // Enqueue CSS
            wp_enqueue_style(
                'pi-safety-module-css',
                plugin_dir_url(__FILE__) . '../assets/pi-safety-module.css',
                array(),
                '1.0.0'
            );
            
            // Enqueue JS
            wp_enqueue_script(
                'pi-safety-module-js',
                plugin_dir_url(__FILE__) . '../assets/pi-safety-module.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            // Localize script with job_id
            $job_id = 0;
            if (is_singular('pi_job')) {
                $job_id = get_the_ID();
            } elseif (isset($_GET['job_id'])) {
                $job_id = intval($_GET['job_id']);
            }
            
            $current_user = wp_get_current_user();
            
            wp_localize_script('pi-safety-module-js', 'PI_Safety', array(
                'rest_base' => rest_url('pi-crm/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
                'job_id' => $job_id,
                'user_display_name' => $current_user->display_name
            ));
        }
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'job_id' => 0
        ), $atts);
        
        $job_id = intval($atts['job_id']);
        
        if (!$job_id && is_singular('pi_job')) {
            $job_id = get_the_ID();
        }
        
        if (!$job_id) {
            return '<div class="pi-safety-error">Please provide a job_id parameter or use on a job page.</div>';
        }
        
        // Force enqueue assets
        wp_enqueue_style('pi-safety-module-css');
        wp_enqueue_script('pi-safety-module-js');
        
        // Re-localize with specific job_id
        $current_user = wp_get_current_user();
        wp_localize_script('pi-safety-module-js', 'PI_Safety', array(
            'rest_base' => rest_url('pi-crm/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'job_id' => $job_id,
            'user_display_name' => $current_user->display_name
        ));
        
        ob_start();
        ?>
        <div class="pi-safety-module" data-job-id="<?php echo esc_attr($job_id); ?>">
            <!-- Safety Header -->
            <div class="pi-safety-header">
                <div>
                    <h1>Safety Command Center</h1>
                    <p>Job #<?php echo esc_html($job_id); ?></p>
                </div>
                <div class="safety-score" id="pi-safety-score">--</div>
            </div>
            
            <!-- Alerts Banner -->
            <div class="pi-safety-alerts"></div>
            
            <!-- Navigation -->
            <div class="pi-safety-nav">
                <button class="active" data-section="dashboard">Dashboard</button>
                <button data-section="incidents">Incidents</button>
                <button data-section="observations">Observations</button>
                <button data-section="inspections">Inspections</button>
                <button data-section="permits">Permits</button>
                <button data-section="jha">JHA</button>
                <button data-section="toolbox-talks">Toolbox Talks</button>
                <button data-section="certifications">Certifications</button>
                <button data-section="ppe">PPE</button>
                <button data-section="meetings">Meetings</button>
                <button data-section="activity">Activity</button>
            </div>
            
            <!-- Dashboard Section -->
            <div class="pi-safety-content">
                <div class="pi-safety-section active" data-section="dashboard">
                    <div class="pi-safety-stats">
                        <div class="pi-stat-card">
                            <div class="stat-value" id="pi-open-incidents">--</div>
                            <div class="stat-label">Open Incidents</div>
                        </div>
                        <div class="pi-stat-card">
                            <div class="stat-value" id="pi-active-permits">--</div>
                            <div class="stat-label">Active Permits</div>
                        </div>
                        <div class="pi-stat-card">
                            <div class="stat-value" id="pi-open-observations">--</div>
                            <div class="stat-label">Open Observations</div>
                        </div>
                        <div class="pi-stat-card">
                            <div class="stat-value" id="pi-avg-inspection-score">--</div>
                            <div class="stat-label">Avg Inspection Score</div>
                        </div>
                    </div>
                </div>
                
                <!-- Incidents Section -->
                <div class="pi-safety-section" data-section="incidents">
                    <div style="margin-bottom: 1rem;">
                        <button class="pi-btn pi-btn-primary pi-create-incident-btn">+ Report Incident</button>
                    </div>
                    <div class="pi-incident-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Observations Section -->
                <div class="pi-safety-section" data-section="observations">
                    <div style="margin-bottom: 1rem;">
                        <button class="pi-btn pi-btn-primary pi-create-observation-btn">+ Record Observation</button>
                    </div>
                    <div class="pi-observation-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Inspections Section -->
                <div class="pi-safety-section" data-section="inspections">
                    <div style="margin-bottom: 1rem;">
                        <button class="pi-btn pi-btn-primary pi-create-inspection-btn">+ Schedule Inspection</button>
                    </div>
                    <div class="pi-inspection-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Permits Section -->
                <div class="pi-safety-section" data-section="permits">
                    <div style="margin-bottom: 1rem;">
                        <button class="pi-btn pi-btn-primary pi-create-permit-btn">+ Request Permit</button>
                    </div>
                    <div class="pi-permit-grid">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- JHA Section -->
                <div class="pi-safety-section" data-section="jha">
                    <div style="margin-bottom: 1rem;">
                        <button class="pi-btn pi-btn-primary pi-create-jha-btn">+ Create JHA</button>
                    </div>
                    <div class="pi-jha-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Toolbox Talks Section -->
                <div class="pi-safety-section" data-section="toolbox-talks">
                    <div style="margin-bottom: 1rem;">
                        <button class="pi-btn pi-btn-primary pi-create-toolbox-talk-btn">+ Schedule Toolbox Talk</button>
                    </div>
                    <div class="pi-toolbox-talk-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Certifications Section -->
                <div class="pi-safety-section" data-section="certifications">
                    <div class="pi-certification-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- PPE Section -->
                <div class="pi-safety-section" data-section="ppe">
                    <div class="pi-ppe-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Meetings Section -->
                <div class="pi-safety-section" data-section="meetings">
                    <div class="pi-meeting-list">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Section -->
                <div class="pi-safety-section" data-section="activity">
                    <div class="pi-activity-feed">
                        <div class="pi-loading">
                            <div class="pi-spinner"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Incident Modal -->
            <div class="pi-modal" id="pi-incident-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Report Incident</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-incident-form">
                            <div class="pi-form-group">
                                <label>Incident Type *</label>
                                <select name="incident_type" required>
                                    <option value="">Select type...</option>
                                    <option value="slip_trip_fall">Slip/Trip/Fall</option>
                                    <option value="struck_by">Struck By Object</option>
                                    <option value="caught_in">Caught In/Between</option>
                                    <option value="fall_elevation">Fall from Elevation</option>
                                    <option value="electrical">Electrical</option>
                                    <option value="burn">Burn/Heat Stress</option>
                                    <option value="chemical">Chemical Exposure</option>
                                    <option value="vehicle">Vehicle Incident</option>
                                    <option value="equipment">Equipment Incident</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Severity *</label>
                                <select name="severity" required>
                                    <option value="minor">Minor (First Aid Only)</option>
                                    <option value="major">Major (Medical Treatment)</option>
                                    <option value="critical">Critical (Hospitalization)</option>
                                    <option value="fatal">Fatal</option>
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Description *</label>
                                <textarea name="description" required placeholder="Describe what happened..."></textarea>
                            </div>
                            <div class="pi-form-group">
                                <label>Location on Site</label>
                                <input type="text" name="location_on_site" placeholder="e.g., Building A, Floor 3">
                            </div>
                            <div class="pi-form-group">
                                <label>Reported By *</label>
                                <input type="text" name="reported_by" required value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>">
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-submit-incident">Submit Report</button>
                    </div>
                </div>
            </div>
            
            <!-- Observation Modal -->
            <div class="pi-modal" id="pi-observation-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Record Observation</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-observation-form">
                            <div class="pi-form-group">
                                <label>Observation Type *</label>
                                <select name="observation_type" required>
                                    <option value="">Select type...</option>
                                    <option value="unsafe_act">Unsafe Act</option>
                                    <option value="unsafe_condition">Unsafe Condition</option>
                                    <option value="positive_observation">Positive Observation</option>
                                    <option value="housekeeping">Housekeeping</option>
                                    <option value="ppe_violation">PPE Violation</option>
                                    <option value="environmental">Environmental Hazard</option>
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Severity</label>
                                <select name="severity">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Description *</label>
                                <textarea name="description" required placeholder="Describe what you observed..."></textarea>
                            </div>
                            <div class="pi-form-group">
                                <label>Location</label>
                                <input type="text" name="location_on_site" placeholder="e.g., Building A, Floor 3">
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-submit-observation">Submit</button>
                    </div>
                </div>
            </div>
            
            <!-- Permit Modal -->
            <div class="pi-modal" id="pi-permit-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Request Permit to Work</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-permit-form">
                            <div class="pi-form-group">
                                <label>Permit Type *</label>
                                <select name="permit_type" required>
                                    <option value="">Select type...</option>
                                    <option value="hot_work">Hot Work</option>
                                    <option value="confined_space">Confined Space</option>
                                    <option value="excavation_trenching">Excavation/Trenching</option>
                                    <option value="electrical_work">Electrical Work</option>
                                    <option value="work_at_height">Work at Height</option>
                                    <option value="cold_work">Cold Work</option>
                                    <option value="general_high_risk">General High Risk</option>
                                </select>
                            </div>
                            <div class="pi-form-row">
                                <div class="pi-form-group">
                                    <label>Start Date/Time *</label>
                                    <input type="datetime-local" name="start_datetime" required>
                                </div>
                                <div class="pi-form-group">
                                    <label>End Date/Time *</label>
                                    <input type="datetime-local" name="end_datetime" required>
                                </div>
                            </div>
                            <div class="pi-form-group">
                                <label>Location on Site *</label>
                                <input type="text" name="location_on_site" required>
                            </div>
                            <div class="pi-form-group">
                                <label>Work Description *</label>
                                <textarea name="work_description" required></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-submit-permit">Submit Request</button>
                    </div>
                </div>
            </div>
            
            <!-- JHA Modal -->
            <div class="pi-modal" id="pi-jha-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Create Job Hazard Analysis</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-jha-form">
                            <div class="pi-form-group">
                                <label>Task Name *</label>
                                <input type="text" name="task_name" required>
                            </div>
                            <div class="pi-form-group">
                                <label>Task Description</label>
                                <textarea name="task_description"></textarea>
                            </div>
                            <div class="pi-form-group">
                                <label>Trade Involved</label>
                                <input type="text" name="trade_involved">
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-submit-jha">Create JHA</button>
                    </div>
                </div>
            </div>
            
            <!-- Toolbox Talk Modal -->
            <div class="pi-modal" id="pi-toolbox-talk-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Schedule Toolbox Talk</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-toolbox-talk-form">
                            <div class="pi-form-group">
                                <label>Topic *</label>
                                <input type="text" name="topic" required>
                            </div>
                            <div class="pi-form-group">
                                <label>Category</label>
                                <select name="category">
                                    <option value="">Select category...</option>
                                    <option value="fall_protection">Fall Protection</option>
                                    <option value="ppe">PPE</option>
                                    <option value="electrical">Electrical Safety</option>
                                    <option value="heat_stress">Heat Stress</option>
                                    <option value="trenching">Trenching</option>
                                    <option value="crane">Crane Safety</option>
                                    <option value="hazard_communication">Hazard Communication</option>
                                    <option value="general">General Safety</option>
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Scheduled Date/Time *</label>
                                <input type="datetime-local" name="scheduled_date" required>
                            </div>
                            <div class="pi-form-group">
                                <label>Content</label>
                                <textarea name="content_body" placeholder="Key points, takeaways..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-submit-toolbox-talk">Schedule</button>
                    </div>
                </div>
            </div>
            
            <!-- Inspection Create Modal -->
            <div class="pi-modal" id="pi-inspection-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Schedule Inspection</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-inspection-form">
                            <div class="pi-form-group">
                                <label>Inspection Type *</label>
                                <select name="inspection_type" required>
                                    <option value="">Select type...</option>
                                    <option value="site_safety">Site Safety</option>
                                    <option value="equipment">Equipment Inspection</option>
                                    <option value="fire_safety">Fire Safety</option>
                                    <option value="electrical">Electrical Inspection</option>
                                    <option value="scaffold">Scaffold Inspection</option>
                                    <option value="excavation">Excavation Inspection</option>
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Title *</label>
                                <input type="text" name="title" required>
                            </div>
                            <div class="pi-form-row">
                                <div class="pi-form-group">
                                    <label>Inspection Date *</label>
                                    <input type="datetime-local" name="inspection_date" required>
                                </div>
                                <div class="pi-form-group">
                                    <label>Next Due Date</label>
                                    <input type="datetime-local" name="next_due_date">
                                </div>
                            </div>
                            <div class="pi-form-group">
                                <label>Checklist Template</label>
                                <select name="checklist_template_id">
                                    <option value="">Select template...</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-submit-inspection">Schedule</button>
                    </div>
                </div>
            </div>
            
            <!-- Inspection Execute Modal -->
            <div class="pi-modal" id="pi-inspection-execute-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Execute Inspection</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <div id="pi-inspection-checklist-container"></div>
                        <div class="pi-form-group">
                            <label>Overall Score</label>
                            <input type="number" name="overall_score" min="0" max="100" value="100">
                        </div>
                        <div class="pi-form-group">
                            <label>Digital Signature</label>
                            <input type="text" name="digital_signature" placeholder="Type name to sign">
                        </div>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-complete-inspection">Complete Inspection</button>
                    </div>
                </div>
            </div>
            
            <!-- Incident Detail Modal -->
            <div class="pi-modal" id="pi-incident-detail-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Incident Details</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <div class="detail-row">
                            <span class="detail-label">Type:</span>
                            <span class="detail-type"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Severity:</span>
                            <span class="detail-severity"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date:</span>
                            <span class="detail-date"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Description:</span>
                            <p class="detail-description"></p>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Location:</span>
                            <span class="detail-location"></span>
                        </div>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Close</button>
                    </div>
                </div>
            </div>
            
            <!-- Observation Resolve Modal -->
            <div class="pi-modal" id="pi-observation-resolve-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Resolve Observation</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-observation-resolve-form">
                            <div class="pi-form-group">
                                <label>Resolution Notes *</label>
                                <textarea name="resolution_notes" required placeholder="Describe what was done to resolve this observation..."></textarea>
                            </div>
                            <div class="pi-form-group">
                                <label>Photos</label>
                                <input type="file" name="photos" multiple accept="image/*">
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-resolve-observation">Resolve</button>
                    </div>
                </div>
            </div>
            
            <!-- Permit Approve Modal -->
            <div class="pi-modal" id="pi-permit-approve-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Approve Permit</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <div class="pi-form-group">
                            <label>Digital Signature *</label>
                            <input type="text" name="signature" required placeholder="Type name to sign approval">
                        </div>
                        <div class="pi-form-group">
                            <label>Notes</label>
                            <textarea name="notes" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-approve-permit-confirm">Approve</button>
                    </div>
                </div>
            </div>
            
            <!-- Permit Extend Modal -->
            <div class="pi-modal" id="pi-permit-extend-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Extend Permit</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-permit-extend-form">
                            <div class="pi-form-group">
                                <label>New End Date/Time *</label>
                                <input type="datetime-local" name="end_datetime" required>
                            </div>
                            <div class="pi-form-group">
                                <label>Reason for Extension *</label>
                                <textarea name="reason" required placeholder="Explain why extension is needed..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-extend-permit-confirm">Extend</button>
                    </div>
                </div>
            </div>
            
            <!-- JHA Acknowledge Modal -->
            <div class="pi-modal" id="pi-jha-acknowledge-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Acknowledge JHA</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-jha-acknowledge-form">
                            <div class="pi-form-group">
                                <label>Digital Signature *</label>
                                <input type="text" name="signature" required placeholder="Type name to sign">
                            </div>
                            <div class="pi-form-group">
                                <label>I have read and understand the hazards and controls</label>
                                <input type="checkbox" name="understand" required>
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-acknowledge-jha-confirm">Acknowledge</button>
                    </div>
                </div>
            </div>
            
            <!-- Toolbox Talk Attendance Modal -->
            <div class="pi-modal" id="pi-toolbox-talk-attendance-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Record Attendance</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-toolbox-talk-attendance-form">
                            <div id="pi-attendance-list"></div>
                            <div class="pi-form-group">
                                <label>Questions Asked</label>
                                <textarea name="questions_asked" placeholder="Any questions from attendees..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-save-attendance">Save Attendance</button>
                    </div>
                </div>
            </div>
            
            <!-- PPE Issue Modal -->
            <div class="pi-modal" id="pi-ppe-issue-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Issue PPE</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-ppe-issue-form">
                            <div class="pi-form-group">
                                <label>Worker *</label>
                                <select name="assigned_to_worker_id" required>
                                    <option value="">Select worker...</option>
                                </select>
                            </div>
                            <div class="pi-form-row">
                                <div class="pi-form-group">
                                    <label>Issue Date</label>
                                    <input type="date" name="issue_date">
                                </div>
                                <div class="pi-form-group">
                                    <label>Expected Return</label>
                                    <input type="date" name="expected_return_date">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-issue-ppe-confirm">Issue</button>
                    </div>
                </div>
            </div>
            
            <!-- PPE Inspect Modal -->
            <div class="pi-modal" id="pi-ppe-inspect-modal">
                <div class="pi-modal-content">
                    <div class="pi-modal-header">
                        <h2>Inspect PPE</h2>
                        <button class="pi-modal-close">&times;</button>
                    </div>
                    <div class="pi-modal-body">
                        <form class="pi-ppe-inspect-form">
                            <div class="pi-form-group">
                                <label>Condition *</label>
                                <select name="condition" required>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                    <option value="damaged">Damaged - Replace</option>
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Inspection Notes</label>
                                <textarea name="notes" placeholder="Observations during inspection..."></textarea>
                            </div>
                            <div class="pi-form-group">
                                <label>Next Inspection Date</label>
                                <input type="date" name="next_inspection_date">
                            </div>
                        </form>
                    </div>
                    <div class="pi-modal-footer">
                        <button class="pi-btn pi-btn-secondary pi-modal-close">Cancel</button>
                        <button class="pi-btn pi-btn-primary pi-inspect-ppe-confirm">Save Inspection</button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .pi-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .pi-toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .pi-toast-success {
            background: #10b981;
        }
        .pi-toast-error {
            background: #ef4444;
        }
        .pi-toast-info {
            background: #3b82f6;
        }
        .pi-toast-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0;
        }
        
        /* Modal Styles */
        .pi-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .pi-modal.active {
            display: flex;
        }
        .pi-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        .pi-modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pi-modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }
        .pi-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            line-height: 1;
        }
        .pi-modal-body {
            padding: 1.5rem;
        }
        .pi-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        /* Form Styles */
        .pi-form-group {
            margin-bottom: 1rem;
        }
        .pi-form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        .pi-form-group input,
        .pi-form-group select,
        .pi-form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .pi-form-group input.error,
        .pi-form-group select.error,
        .pi-form-group textarea.error {
            border-color: #ef4444;
        }
        .pi-form-row {
            display: flex;
            gap: 1rem;
        }
        .pi-form-row .pi-form-group {
            flex: 1;
        }
        .pi-field-error {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        /* Loading State */
        .pi-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .pi-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Empty State */
        .pi-empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }
        .pi-empty-state svg {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }
        .pi-empty-state p {
            margin: 0;
        }
        
        /* List Items */
        .pi-list-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .pi-list-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Severity/Status Badges */
        .pi-item-severity {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .pi-item-severity.minor,
        .pi-item-severity.low {
            background: #fef3c7;
            color: #92400e;
        }
        .pi-item-severity.major,
        .pi-item-severity.medium {
            background: #fed7aa;
            color: #9a3412;
        }
        .pi-item-severity.critical,
        .pi-item-severity.high {
            background: #fecaca;
            color: #991b1b;
        }
        .pi-item-severity.fatal {
            background: #1f2937;
            color: white;
        }
        
        /* Buttons */
        .pi-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        .pi-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .pi-btn-primary {
            background: #3b82f6;
            color: white;
        }
        .pi-btn-primary:hover:not(:disabled) {
            background: #2563eb;
        }
        .pi-btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        .pi-btn-secondary:hover:not(:disabled) {
            background: #d1d5db;
        }
        .pi-btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Detail Row */
        .detail-row {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
        }
        .detail-label {
            font-weight: 600;
            min-width: 100px;
            color: #374151;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .pi-safety-nav {
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 0.5rem;
            }
            .pi-modal-content {
                width: 95%;
                max-height: 95vh;
            }
            .pi-form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    public function add_safety_tab($tabs) {
        $tabs['safety'] = array(
            'label' => 'Safety',
            'icon' => 'shield',
            'content' => function($job_id) {
                return do_shortcode('[pi_safety_module job_id="' . $job_id . '"]');
            }
        );
        return $tabs;
    }
}

// Initialize the safety module
PI_Safety_Module::get_instance();

// Load the safety REST API class
require_once plugin_dir_path(__FILE__) . 'class-pi-safety-rest-api.php';
