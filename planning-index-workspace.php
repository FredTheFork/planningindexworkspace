<?php
/**
 * Plugin Name: Planning Index Workspace
 * Description: Professional CRM-style pipeline for managing planning application leads
 * Version: 4.0.5
 * Author: Planning Index
 */

if (!defined('ABSPATH')) exit;
define('PI_LEAD_CPT', 'pi_lead');
define('PI_LEAD_META_PREFIX', '_pi_lead_');
define('PIF_WORKSPACE_META', '_pi_workspace_state');
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/pi-tasks.php';
require_once __DIR__ . '/includes/pi-jobs.php';
require_once __DIR__ . '/includes/pi-calendar.php';
require_once __DIR__ . '/includes/pi-expenses.php';
require_once __DIR__ . '/includes/pi-dashboard.php';

// Team & Timesheets Module (Global and Job-specific)
require_once __DIR__ . '/includes/pi-team-timesheets.php';

// Comprehensive CRM modules
require_once __DIR__ . '/includes/class-pi-crm-database.php';
require_once __DIR__ . '/includes/class-pi-crm-rest-api.php';

// Equipment Management Module
require_once __DIR__ . '/includes/class-pi-equipment-database.php';
require_once __DIR__ . '/includes/class-pi-equipment-rest-api.php';

// Daily Reports Module
require_once __DIR__ . '/includes/class-pi-daily-reports-database.php';
require_once __DIR__ . '/includes/class-pi-daily-reports-rest-api.php';
require_once __DIR__ . '/includes/pi-daily-reports.php';

// Safety Module
require_once __DIR__ . '/includes/pi-safety-module.php';

/**
 * Single shortcode for Workspace: [planning_workspace]
 * Use this ONCE on your Workspace page or in the template that powers it.
 * It shows BOTH pipelines: use the "Leads" | "Jobs" switch in the header to toggle.
 * No separate shortcode or page is needed for Jobs — they are built into this one.
 */
add_shortcode('planning_workspace', function () {
    if (!is_user_logged_in()) {
        return '<div class="pi-crm-wrapper">
            <div class="pi-auth-required">
                <div class="pi-auth-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3>Sign In Required</h3>
                <p>Please log in to access your pipeline and manage leads.</p>
            </div>
        </div>';
    }

    // Enqueue Inter font
    wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null);
    wp_enqueue_style('pi-workspace-css', plugin_dir_url(__FILE__) . 'assets/workspace.css', [], '4.0');
    wp_enqueue_script('sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', [], '1.15.2', true);
    wp_enqueue_script('pi-workspace-js', plugin_dir_url(__FILE__) . 'assets/workspace.js', ['jquery', 'sortablejs'], '4.0', true);

    wp_localize_script('pi-workspace-js', 'PI_Workspace', [
        'rest_base'    => rest_url('pi/v1/workspace'),
        'jobs_rest'   => rest_url('pi/v1/workspace/jobs'),
        'job_single_base' => home_url('/job/'),
        'nonce'        => wp_create_nonce('wp_rest'),
    ]);

    ob_start(); ?>
    <div class="pi-crm-wrapper">
        <!-- Header Bar -->
        <div class="pi-crm-header">
            <div class="pi-crm-title">
                <h1>Pipeline</h1>
                <span class="pi-lead-count" id="pi-total-leads">0 leads</span>
            </div>
            
            <!-- Stats Bar - Inline Horizontal -->
            <div class="pi-stats-bar">
                <div class="pi-stat-item total">
                    <span class="pi-stat-value" id="pi-total-value">£0</span>
                    <span class="pi-stat-label">Total Value</span>
                </div>
                <div class="pi-stat-divider"></div>
                <div class="pi-stat-item won">
                    <span class="pi-stat-value" id="pi-won-value">£0</span>
                    <span class="pi-stat-label">Won</span>
                </div>
            </div>
            
            <div class="pi-crm-actions">
                <!-- Leads / Jobs switch (left of filter) -->
                <div class="pi-pipeline-switch" role="tablist" aria-label="Pipeline view">
                    <button type="button" class="pi-pipeline-tab active" data-view="leads" role="tab" aria-selected="true">Leads</button>
                    <button type="button" class="pi-pipeline-tab" data-view="jobs" role="tab" aria-selected="false">Jobs</button>
                </div>
                <!-- Filter (Leads) -->
                <div class="pi-filter-dropdown" id="pi-filter-dropdown-leads">
                    <button class="pi-filter-btn" id="pi-filter-toggle" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                        </svg>
                        <span>Filter</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="pi-filter-menu" id="pi-filter-menu">
                        <button class="pi-filter-option active" data-filter="all" type="button">All Leads</button>
                        <button class="pi-filter-option" data-filter="highlighted" type="button">Highlighted</button>
                        <button class="pi-filter-option" data-filter="with-value" type="button">With Value</button>
                        <button class="pi-filter-option" data-filter="with-invoice" type="button">With Proposal</button>
                        <button class="pi-filter-option" data-filter="with-tasks" type="button">With Tasks</button>
                        <button class="pi-filter-option" data-filter="overdue" type="button">Overdue</button>
                    </div>
                </div>
                <!-- Filter (Jobs) - shown when Jobs view active -->
                <div class="pi-filter-dropdown pi-filter-dropdown-jobs" id="pi-filter-dropdown-jobs">
                    <button class="pi-filter-btn" id="pi-filter-toggle-jobs" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                        </svg>
                        <span>Filter</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="pi-filter-menu" id="pi-filter-menu-jobs">
                        <button class="pi-filter-option active" data-filter="all" type="button">All Jobs</button>
                        <button class="pi-filter-option" data-filter="planning" type="button">Planning</button>
                        <button class="pi-filter-option" data-filter="scheduled" type="button">Scheduled</button>
                        <button class="pi-filter-option" data-filter="in_progress" type="button">In Progress</button>
                        <button class="pi-filter-option" data-filter="review" type="button">Review</button>
                        <button class="pi-filter-option" data-filter="completed" type="button">Completed</button>
                        <button class="pi-filter-option" data-filter="overdue" type="button">Overdue</button>
                    </div>
                </div>
                
                <!-- Search -->
                <div class="pi-search-container">
                    <svg class="pi-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" id="pi-workspace-search" placeholder="Search..." autocomplete="off" />
                </div>
            </div>
        </div>

        <!-- Pipeline Board -->
        <div id="pi-workspace-board">
            <?php
            $columns = [
                'new_lead' => [
                    'title' => 'New Lead',
                    'color' => '#6366f1',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>'
                ],
                'proposal_sent' => [
                    'title' => 'Proposal Sent',
                    'color' => '#8b5cf6',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'
                ],
                'contacted' => [
                    'title' => 'Contacted',
                    'color' => '#06b6d4',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
                ],
                'negotiation' => [
                    'title' => 'Negotiation',
                    'color' => '#f59e0b',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
                ],
                'won' => [
                    'title' => 'Won',
                    'color' => '#10b981',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                ],
            ];
            
            foreach ($columns as $key => $col): ?>
                <div class="pi-workspace-column" data-stage="<?= esc_attr($key) ?>">
                    <div class="pi-workspace-header" style="--stage-color: <?= esc_attr($col['color']) ?>">
                        <div class="pi-header-left">
                            <span class="pi-stage-icon"><?= $col['icon'] ?></span>
                            <h3><?= esc_html($col['title']) ?></h3>
                        </div>
                        <div class="pi-column-meta">
                            <span class="pi-column-value" data-stage-value="<?= esc_attr($key) ?>" style="display:none;"></span>
                            <span class="pi-workspace-count" data-stage-count="<?= esc_attr($key) ?>">0</span>
                        </div>
                    </div>
                    <div class="pi-workspace-list" id="pi-workspace-<?= esc_attr($key) ?>">
                        <div class="pi-empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            <span>No leads</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Jobs Pipeline Board (same layout as leads) -->
        <div id="pi-workspace-board-jobs" class="pi-workspace-board pi-workspace-board-jobs" aria-hidden="true">
            <?php
            $job_columns = [
                'planning' => [
                    'title' => 'Planning',
                    'color' => '#6366f1',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>'
                ],
                'scheduled' => [
                    'title' => 'Scheduled',
                    'color' => '#8b5cf6',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
                ],
                'in_progress' => [
                    'title' => 'In Progress',
                    'color' => '#f59e0b',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>'
                ],
                'review' => [
                    'title' => 'Review',
                    'color' => '#06b6d4',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
                ],
                'completed' => [
                    'title' => 'Completed',
                    'color' => '#10b981',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                ],
            ];
            foreach ($job_columns as $jkey => $jcol): ?>
                <div class="pi-workspace-column" data-stage="<?= esc_attr($jkey) ?>">
                    <div class="pi-workspace-header" style="--stage-color: <?= esc_attr($jcol['color']) ?>">
                        <div class="pi-header-left">
                            <span class="pi-stage-icon"><?= $jcol['icon'] ?></span>
                            <h3><?= esc_html($jcol['title']) ?></h3>
                        </div>
                        <div class="pi-column-meta">
                            <span class="pi-column-value" data-stage-value="<?= esc_attr($jkey) ?>" style="display:none;"></span>
                            <span class="pi-workspace-count" data-stage-count="<?= esc_attr($jkey) ?>">0</span>
                        </div>
                    </div>
                    <div class="pi-workspace-list" id="pi-workspace-job-<?= esc_attr($jkey) ?>">
                        <div class="pi-empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            <span>No jobs</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});




add_action('init', function() {
    register_post_type(PI_LEAD_CPT, [
        'labels' => ['name' => 'Leads', 'singular_name' => 'Lead'],
        'public' => true,
        'show_ui' => true,
        'supports' => ['title', 'custom-fields'],
        'rewrite' => ['slug' => 'lead'],
        'capability_type' => 'post',
        'capabilities' => ['edit_post' => 'edit_pi_lead'],
    ]);

    $subscriber = get_role('subscriber');
    if ($subscriber) $subscriber->add_cap('edit_pi_lead');
    $admin = get_role('administrator');
    if ($admin) $admin->add_cap('edit_pi_lead');
});

add_filter('wp_insert_post_data', function($data, $postarr) {
    if ($data['post_type'] !== PI_LEAD_CPT || $postarr['ID']) return $data;

    global $wpdb;
    $year_month = date('Ym');
    $prefix = "LEAD-{$year_month}-%";
    $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = 'any' AND post_title LIKE %s", PI_LEAD_CPT, $prefix));
    $seq = $existing + 1;
    $code = "LEAD-{$year_month}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);

    $data['post_name'] = sanitize_title($code);
    $data['post_title'] = $code;
    return $data;
}, 10, 2);

add_action('save_post', function($post_id, $post) {
    if ($post->post_type !== PI_LEAD_CPT || get_post_meta($post_id, PI_LEAD_META_PREFIX . 'lead_code', true)) return;

    $year_month = date('Ym');
    $option = 'pi_lead_seq_' . $year_month;
    $seq = (int) get_option($option, 0) + 1;
    update_option($option, $seq);

    $code = "PROP-{$year_month}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);

    update_post_meta($post_id, PI_LEAD_META_PREFIX . 'lead_code', $code);

    wp_update_post([
        'ID' => $post_id,
        'post_title' => $code,
        'post_name' => sanitize_title($code)
    ]);
}, 10, 2);

add_action('pre_get_posts', function($query) {
    if (is_admin() || $query->get('post_type') !== PI_LEAD_CPT) return;
    // Don't override if meta_value is already set (e.g. team dashboard querying other members' leads)
    if ($query->get('meta_value')) return;
    $query->set('meta_key', PI_LEAD_META_PREFIX . 'owner_user_id');
    $query->set('meta_value', get_current_user_id());
    $query->set('post_status', 'any');
});

function pi_migrate_workspace_to_leads($user_id) {
    $workspace = get_user_meta($user_id, PIF_WORKSPACE_META, true) ?: [];
    
    $stage_mapping = [
        'possible' => 'new_lead',
        'letter' => 'proposal_sent',
        'finalised' => 'won'
    ];
    
    $new_workspace = [
        'new_lead' => [],
        'proposal_sent' => [],
        'contacted' => [],
        'negotiation' => [],
        'won' => []
    ];
    
    foreach ($workspace as $stage => &$items) {
        $new_stage = $stage_mapping[$stage] ?? $stage;
        if (!isset($new_workspace[$new_stage])) continue;
        
        foreach ($items as &$item) {
            $old_id = intval($item['id'] ?? 0);
            if (get_post_type($old_id) !== 'planning_app') {
                $new_workspace[$new_stage][] = $item;
                continue;
            }

            $existing_lead = get_posts([
                'post_type' => PI_LEAD_CPT,
                'meta_query' => [
                    ['key' => PI_LEAD_META_PREFIX . 'linked_planning_app_id', 'value' => $old_id],
                    ['key' => PI_LEAD_META_PREFIX . 'owner_user_id', 'value' => $user_id],
                ],
                'posts_per_page' => 1,
                'post_status' => 'any',
            ]);
            if (!empty($existing_lead)) {
                $item['id'] = $existing_lead[0]->ID;
                $new_workspace[$new_stage][] = $item;
                continue;
            }

            $lead_id = wp_insert_post(['post_type' => PI_LEAD_CPT, 'post_status' => 'draft']);
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', $user_id);
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', $old_id);
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'stage', $new_stage);
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'notes', $item['notes'] ?? '');
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'due_date', $item['due'] ?? '');
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'estimated_value', $item['est'] ?? 0);
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'highlighted', $item['highlighted'] ?? 0);

            $item['id'] = $lead_id;
            $new_workspace[$new_stage][] = $item;
        }
    }
    update_user_meta($user_id, PIF_WORKSPACE_META, $new_workspace);
}

add_action('init', function() {
    if (is_user_logged_in()) pi_migrate_workspace_to_leads(get_current_user_id());
});

// Enqueue for single lead pages
add_action('wp_enqueue_scripts', function () {
    if (is_singular(PI_LEAD_CPT)) {
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css', [], '1.13.2');
        wp_enqueue_script('jquery-ui', 'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js', ['jquery'], '1.13.2', true);
        wp_enqueue_style('pi-lead-css', plugin_dir_url(__FILE__) . 'assets/lead.css', [], '4.0');
        wp_enqueue_script('pi-lead-single-js', plugin_dir_url(__FILE__) . 'assets/lead-single.js', ['jquery', 'jquery-ui'], '4.0', true);
        wp_localize_script('pi-lead-single-js', 'PI_Lead', [
            'nonce' => wp_create_nonce('wp_rest'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'lead_id' => get_the_ID(),
            'workspace_url' => home_url('/workspace'),
        ]);
    }
});

// Load single lead template
add_filter('single_template', function($template) {
    global $post;
    if ($post->post_type == PI_LEAD_CPT) {
        return plugin_dir_path(__FILE__) . 'includes/pi_lead.php';
    }
    if ($post->post_type == PI_JOB_CPT) {
        return plugin_dir_path(__FILE__) . 'includes/pi_job.php';
    }
    return $template;
});

// Enqueue for single job page (reuse lead.css for layout; job.css for overrides)
add_action('wp_enqueue_scripts', function () {
    if (is_singular(PI_JOB_CPT)) {
        wp_enqueue_style('pi-lead-css', plugin_dir_url(__FILE__) . 'assets/lead.css', [], '4.0');
        wp_enqueue_style('pi-job-css', plugin_dir_url(__FILE__) . 'assets/job.css', ['pi-lead-css'], '4.0');
        wp_enqueue_style('pi-job-crm-css', plugin_dir_url(__FILE__) . 'assets/job-crm.css', ['pi-lead-css', 'pi-job-css'], '4.0');
        wp_enqueue_style('pi-job-sidebar-css', plugin_dir_url(__FILE__) . 'assets/job-sidebar-nav.css', ['pi-lead-css', 'pi-job-css'], '4.0');
        wp_enqueue_style('pi-svg-alignment-fix', plugin_dir_url(__FILE__) . 'assets/svg-alignment-fix.css', ['pi-lead-css'], '1.0.0');
        wp_enqueue_style('pi-rfi-submittals-css', plugin_dir_url(__FILE__) . 'assets/job-rfi-submittals.css', ['pi-job-crm-css'], '1.0');
        wp_enqueue_script('pi-job-single-js', plugin_dir_url(__FILE__) . 'assets/job-single.js', ['jquery'], '4.0', true);
        wp_enqueue_script('pi-job-crm-js', plugin_dir_url(__FILE__) . 'assets/job-crm.js', ['jquery', 'pi-job-single-js'], '4.4', true);
        wp_enqueue_script('pi-rfi-submittals-js', plugin_dir_url(__FILE__) . 'assets/job-rfi-submittals.js', ['jquery', 'pi-job-crm-js'], '1.4', true);
        wp_localize_script('pi-job-single-js', 'PI_Job', [
            'nonce' => wp_create_nonce('wp_rest'),
            'workspace_url' => home_url('/workspace'),
            'lead_id' => get_the_ID(),
            'job_id' => get_the_ID(),
        ]);
        wp_add_inline_script('pi-rfi-submittals-js', 'window.piJobId = ' . get_the_ID() . ';');
    }
}, 20);

// Admin init - ensure database is up to date
add_action('admin_init', function() {
    // Check if we need to update the database schema
    $db_version = get_option('pi_crm_db_version', '1.0');
    if (version_compare($db_version, '1.1', '<')) {
        // Run database updates
        PI_CRM_Database::get_instance()->create_tables();
        update_option('pi_crm_db_version', '1.1');
    }
    
    // Initialize equipment database tables
    $eq_db_version = get_option('pi_equipment_db_version', '1.0');
    if (version_compare($eq_db_version, '1.5', '<')) {
        PI_Equipment_Database::get_instance()->create_tables();
        PI_Equipment_Database::get_instance()->migrate_existing_table();
        
        // Force data migration to fix existing equipment names
        global $wpdb;
        $table_name = $wpdb->prefix . 'pi_crm_equipment';
        
        // Check if internal_name column exists
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table_name} LIKE 'internal_name'");
        
        if ($column_exists) {
            // Fix records where internal_name equals equipment_type (wrong data)
            $fixed = $wpdb->query(
                "UPDATE {$table_name} 
                SET internal_name = CONCAT(equipment_type, ' #', id) 
                WHERE internal_name = equipment_type 
                AND internal_name != '' 
                AND internal_name IS NOT NULL"
            );
            error_log('[Equipment Migration] Fixed ' . $fixed . ' records with wrong internal_name values');
        }
        
        update_option('pi_equipment_db_version', '1.5');
    }
    
    // Initialize daily reports database tables
    $dr_db_version = get_option('pi_daily_reports_db_version', '1.0');
    if (version_compare($dr_db_version, '1.0', '<')) {
        PI_Daily_Reports_Database::get_instance()->create_tables();
        update_option('pi_daily_reports_db_version', '1.0');
    }
    
    // Initialize safety module database tables
    $safety_db_version = get_option('pi_safety_db_version', '1.0');
    if (version_compare($safety_db_version, '1.0', '<')) {
        PI_CRM_Database::get_instance()->create_tables();
        update_option('pi_safety_db_version', '1.0');
    }
});

// Create daily reports tables automatically if they don't exist
add_action('plugins_loaded', function() {
    // Check if tables need to be created
    global $wpdb;
    $table_name = $wpdb->prefix . 'pi_crm_daily_report_equipment';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        // Create the tables
        if (class_exists('PI_Daily_Reports_Database')) {
            PI_Daily_Reports_Database::get_instance()->create_tables();
            update_option('pi_daily_reports_db_version', '1.0');
            
            // Log success
            error_log('[Daily Reports] Database tables created automatically on plugins_loaded');
        }
    }
    
    // Create safety module tables automatically if they don't exist
    $safety_table = $wpdb->prefix . 'pi_crm_permits_to_work';
    $safety_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$safety_table}'") === $safety_table;
    
    if (!$safety_table_exists) {
        // Create the tables
        if (class_exists('PI_CRM_Database')) {
            PI_CRM_Database::get_instance()->create_tables();
            update_option('pi_safety_db_version', '1.0');
            
            // Log success
            error_log('[Safety Module] Database tables created automatically on plugins_loaded');
        }
    }
}, 1); // Run with priority 1 to ensure early execution

// Register REST API routes
add_action('rest_api_init', function() {
    // Register CRM routes
    PI_CRM_REST_API::get_instance()->init_routes();
    
    // Register Equipment routes
    PI_Equipment_REST_API::get_instance()->init_routes();
    
    // Register Daily Reports routes
    if (class_exists('PI_Daily_Reports_REST_API')) {
        PI_Daily_Reports_REST_API::get_instance()->init_routes();
    }
});
