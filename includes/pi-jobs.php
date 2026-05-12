<?php
/**
 * Planning Index Workspace - Jobs (integrated)
 * Job CPT, creation from won leads, and jobs pipeline.
 */

if (!defined('ABSPATH')) exit;

define('PI_JOB_CPT', 'pi_job');
define('PI_JOB_META_PREFIX', '_pi_job_');
define('PIF_JOBS_WORKSPACE_META', '_pi_jobs_workspace_state');

// Job pipeline stages (5 columns to match leads layout, Pipedrive-style)
define('PI_JOBS_STAGES', ['planning', 'scheduled', 'in_progress', 'review', 'completed']);

if (!function_exists('pi_jobs_title_case_address')) {
    function pi_jobs_title_case_address($s) {
        $s = (string) $s;
        return preg_replace_callback(
            '/(^|[\s\-\/\(\)\,\.])([a-z0-9])/i',
            static function ($m) {
                return $m[1] . strtoupper($m[2]);
            },
            strtolower($s)
        );
    }
}

function pi_jobs_generate_job_code() {
    $year_month = date('Ym');
    $option_key = 'pi_job_seq_' . $year_month;
    $seq        = (int) get_option($option_key, 0) + 1;
    update_option($option_key, $seq);
    return 'JOB-' . $year_month . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

// Create database view for jobs compatibility with CRM queries
add_action('init', 'pi_jobs_create_crm_view');
function pi_jobs_create_crm_view() {
    global $wpdb;
    
    $view_name = $wpdb->prefix . 'pi_crm_jobs';
    $posts_table = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;
    
    // Check if view already exists
    $view_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS 
        WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
        DB_NAME, $view_name
    ));
    
    if ($view_exists) {
        return;
    }
    
    // Create view to map WordPress posts to CRM jobs structure
    $create_view_sql = "
        CREATE VIEW {$view_name} AS
        SELECT 
            p.ID as id,
            p.post_title as job_name,
            p.post_content as description,
            p.post_status as status,
            p.post_date as created_at,
            p.post_modified as updated_at,
            p.post_author as created_by,
            CAST(pm_job_code.meta_value AS CHAR) as job_code,
            CAST(pm_job_value.meta_value AS DECIMAL(12,2)) as job_value,
            CAST(pm_start_date.meta_value AS DATETIME) as start_date,
            CAST(pm_end_date.meta_value AS DATETIME) as end_date,
            CAST(pm_client_id.meta_value AS UNSIGNED) as client_id,
            CAST(pm_address.meta_value AS CHAR) as address,
            CAST(pm_job_type.meta_value AS CHAR) as job_type
        FROM {$posts_table} p
        LEFT JOIN {$postmeta_table} pm_job_code ON p.ID = pm_job_code.post_id AND pm_job_code.meta_key = '_pi_job_code'
        LEFT JOIN {$postmeta_table} pm_job_value ON p.ID = pm_job_value.post_id AND pm_job_value.meta_key = '_pi_job_value'
        LEFT JOIN {$postmeta_table} pm_start_date ON p.ID = pm_start_date.post_id AND pm_start_date.meta_key = '_pi_job_start_date'
        LEFT JOIN {$postmeta_table} pm_end_date ON p.ID = pm_end_date.post_id AND pm_end_date.meta_key = '_pi_job_end_date'
        LEFT JOIN {$postmeta_table} pm_client_id ON p.ID = pm_client_id.post_id AND pm_client_id.meta_key = '_pi_job_client_id'
        LEFT JOIN {$postmeta_table} pm_address ON p.ID = pm_address.post_id AND pm_address.meta_key = '_pi_job_address'
        LEFT JOIN {$postmeta_table} pm_job_type ON p.ID = pm_job_type.post_id AND pm_job_type.meta_key = '_pi_job_type'
        WHERE p.post_type = %s
        AND p.post_status NOT IN ('auto-draft', 'trash')
    ";
    
    $wpdb->query($wpdb->prepare($create_view_sql, PI_JOB_CPT));
}

function pi_jobs_convert_won_lead_to_job($lead_id, $user_id, $old_stage, $new_stage) {
    if ($new_stage !== 'won') return;

    if (get_post_type($lead_id) !== PI_LEAD_CPT) return;

    $owner_id = (int) get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', true);
    if ($owner_id !== (int) $user_id) return;

    $existing = get_posts([
        'post_type'      => PI_JOB_CPT,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'meta_key'       => PI_JOB_META_PREFIX . 'lead_id',
        'meta_value'     => $lead_id,
        'fields'         => 'ids',
    ]);
    if (!empty($existing)) return;

    $planning_app_id = (int) get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', true);
    $site_address = $description = $council_ref = $date_received = '';

    if ($planning_app_id && get_post_type($planning_app_id) === 'planning_app') {
        $meta = get_post_meta($planning_app_id);
        $post = get_post($planning_app_id);
        $rawAddress = $meta['address'][0] ?? '';
        if (!$rawAddress && $post && $post->post_content) {
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $post->post_content);
            foreach ($dom->getElementsByTagName('p') as $p) {
                $t = trim($p->textContent);
                if (preg_match('/^Address:/i', $t)) {
                    $rawAddress = preg_replace('/^Address:\s*/i', '', $t);
                    break;
                }
            }
        }
        if (!$rawAddress && $post) $rawAddress = $post->post_title ?: '';
        $site_address  = $rawAddress ? pi_jobs_title_case_address($rawAddress) : '';
        $description   = $meta['description'][0] ?? ($post ? wp_strip_all_tags($post->post_content) : '');
        $council_ref   = $meta['council_reference'][0] ?? '';
        $date_received = $meta['date_received'][0] ?? '';
    }

    $customer_name = get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'customer_name', true);
    $job_code = pi_jobs_generate_job_code();

    $job_id = wp_insert_post([
        'post_type'   => PI_JOB_CPT,
        'post_status' => 'publish',
        'post_title'  => $job_code,
        'post_name'   => sanitize_title($job_code),
        'post_author'  => $owner_id,
        'post_content' => '',
    ]);

    if (!$job_id || is_wp_error($job_id)) return;

    update_post_meta($job_id, PI_JOB_META_PREFIX . 'lead_id', $lead_id);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'owner_user_id', $owner_id);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'planning_app_id', $planning_app_id);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'status', 'planning');
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'site_address', $site_address);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'description', $description);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'council_reference', $council_ref);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'date_received', $date_received);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'customer_name', $customer_name);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'progress', 0);
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'start_date', '');
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'end_date', '');
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'assigned_workers', []);

    $activity = [current_time('mysql') . ': Job created from won lead #' . $lead_id];
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'activity', $activity);
}
add_action('pi_lead_stage_changed', 'pi_jobs_convert_won_lead_to_job', 10, 4);

function pi_jobs_user_owns_job($job_id, $user_id = null) {
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    $owner   = (int) get_post_meta($job_id, PI_JOB_META_PREFIX . 'owner_user_id', true);
    return $owner === $user_id;
}

function pi_jobs_add_activity($job_id, $message) {
    $activity = get_post_meta($job_id, PI_JOB_META_PREFIX . 'activity', true);
    if (!is_array($activity)) $activity = [];
    $activity[] = current_time('mysql') . ': ' . $message;
    update_post_meta($job_id, PI_JOB_META_PREFIX . 'activity', $activity);
}

function pi_get_user_jobs_workspace($user_id) {
    $f = get_user_meta($user_id, PIF_JOBS_WORKSPACE_META, true);
    $default = [];
    foreach (PI_JOBS_STAGES as $stage) $default[$stage] = [];
    if (!is_array($f)) return $default;
    $out = $default;
    foreach ($f as $stage => $items) {
        if (in_array($stage, PI_JOBS_STAGES)) $out[$stage] = $items;
    }
    return $out;
}

// Register Job CPT
add_action('init', function () {
    register_post_type(PI_JOB_CPT, [
        'labels' => ['name' => 'Jobs', 'singular_name' => 'Job'],
        'public' => true,
        'show_ui' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
        'rewrite' => ['slug' => 'job'],
        'capability_type' => 'post',
        'capabilities' => ['edit_post' => 'edit_pi_job'],
    ]);
    $subscriber = get_role('subscriber');
    if ($subscriber && !$subscriber->has_cap('edit_pi_job')) $subscriber->add_cap('edit_pi_job');
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap('edit_pi_job')) $admin->add_cap('edit_pi_job');
});

// REST: Jobs pipeline (get + save)
add_action('rest_api_init', function () {
    $namespace = 'pi/v1';

    register_rest_route($namespace, '/workspace/jobs', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function () {
            $user_id = get_current_user_id();
            $workspace = pi_get_user_jobs_workspace($user_id);

            $jobs = get_posts([
                'post_type' => PI_JOB_CPT,
                'meta_key' => PI_JOB_META_PREFIX . 'owner_user_id',
                'meta_value' => $user_id,
                'posts_per_page' => -1,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
            ]);

            $job_details = [];
            foreach ($jobs as $job) {
                $id = $job->ID;
                $meta = get_post_meta($id);
                $status = $meta[PI_JOB_META_PREFIX . 'status'][0] ?? 'planning';
                if (!in_array($status, PI_JOBS_STAGES)) $status = 'planning';

                $job_details[$id] = [
                    'id' => $id,
                    'code' => $job->post_title,
                    'slug' => $job->post_name,
                    'site_address' => $meta[PI_JOB_META_PREFIX . 'site_address'][0] ?? '',
                    'customer_name' => $meta[PI_JOB_META_PREFIX . 'customer_name'][0] ?? '',
                    'progress' => (int) ($meta[PI_JOB_META_PREFIX . 'progress'][0] ?? 0),
                    'start_date' => $meta[PI_JOB_META_PREFIX . 'start_date'][0] ?? '',
                    'end_date' => $meta[PI_JOB_META_PREFIX . 'end_date'][0] ?? '',
                    'highlighted' => (int) ($meta[PI_JOB_META_PREFIX . 'highlighted'][0] ?? 0),
                ];

                $found = false;
                foreach ($workspace[$status] ?? [] as $item) {
                    if (($item['id'] ?? 0) == $id) { $found = true; break; }
                }
                if (!$found) {
                    if (!isset($workspace[$status])) $workspace[$status] = [];
                    $workspace[$status][] = ['id' => $id, 'order' => count($workspace[$status])];
                }
            }
            update_user_meta($user_id, PIF_JOBS_WORKSPACE_META, $workspace);

            // Return workspace order with full job details per item
            $out = [];
            foreach (PI_JOBS_STAGES as $stage) {
                $out[$stage] = [];
                foreach ($workspace[$stage] ?? [] as $item) {
                    $id = (int) ($item['id'] ?? 0);
                    if (isset($job_details[$id])) {
                        $out[$stage][] = array_merge($job_details[$id], ['order' => $item['order'] ?? 0]);
                    }
                }
            }
            return rest_ensure_response($out);
        },
    ]);

    register_rest_route($namespace, '/workspace/jobs/save', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $body = $req->get_json_params();
            $workspace = $body['workspace'] ?? [];
            if (!is_array($workspace)) return new WP_Error('invalid', 'Invalid structure', ['status' => 400]);

            $user_id = get_current_user_id();
            $clean = [];
            foreach (PI_JOBS_STAGES as $stage) {
                $clean[$stage] = [];
                $items = $workspace[$stage] ?? [];
                foreach ($items as $idx => $it) {
                    $id = (int) ($it['id'] ?? 0);
                    if ($id > 0 && pi_jobs_user_owns_job($id, $user_id)) {
                        update_post_meta($id, PI_JOB_META_PREFIX . 'status', $stage);
                        $clean[$stage][] = ['id' => $id, 'order' => $idx];
                    }
                }
            }
            update_user_meta($user_id, PIF_JOBS_WORKSPACE_META, $clean);
            return rest_ensure_response(['saved' => true]);
        },
    ]);

    register_rest_route($namespace, '/jobs/(?P<id>\d+)', [
        'methods' => 'GET',
        'permission_callback' => function ($req) {
            return is_user_logged_in() && pi_jobs_user_owns_job((int) $req['id']);
        },
        'callback' => function (WP_REST_Request $req) {
            $job_id = (int) $req['id'];
            $job = get_post($job_id);
            if (!$job || $job->post_type !== PI_JOB_CPT)
                return new WP_Error('not_found', 'Job not found', ['status' => 404]);

            $meta = get_post_meta($job_id);
            $activity = get_post_meta($job_id, PI_JOB_META_PREFIX . 'activity', true);
            if (!is_array($activity)) $activity = [];
            $workers = get_post_meta($job_id, PI_JOB_META_PREFIX . 'assigned_workers', true);
            if (!is_array($workers)) $workers = [];

            return rest_ensure_response([
                'id' => $job_id,
                'code' => $job->post_title,
                'status' => $meta[PI_JOB_META_PREFIX . 'status'][0] ?? 'planning',
                'progress' => (int) ($meta[PI_JOB_META_PREFIX . 'progress'][0] ?? 0),
                'start_date' => $meta[PI_JOB_META_PREFIX . 'start_date'][0] ?? '',
                'end_date' => $meta[PI_JOB_META_PREFIX . 'end_date'][0] ?? '',
                'lead_id' => (int) ($meta[PI_JOB_META_PREFIX . 'lead_id'][0] ?? 0),
                'site_address' => $meta[PI_JOB_META_PREFIX . 'site_address'][0] ?? '',
                'customer_name' => $meta[PI_JOB_META_PREFIX . 'customer_name'][0] ?? '',
                'description' => $meta[PI_JOB_META_PREFIX . 'description'][0] ?? '',
                'council_reference' => $meta[PI_JOB_META_PREFIX . 'council_reference'][0] ?? '',
                'date_received' => $meta[PI_JOB_META_PREFIX . 'date_received'][0] ?? '',
                'activity' => $activity,
                'assigned_workers' => $workers,
            ]);
        },
    ]);

    register_rest_route($namespace, '/jobs/(?P<id>\d+)/update', [
        'methods' => 'POST',
        'permission_callback' => function ($req) {
            return is_user_logged_in() && pi_jobs_user_owns_job((int) $req['id']);
        },
        'callback' => function (WP_REST_Request $req) {
            $job_id = (int) $req['id'];
            $fields = $req->get_json_params();
            $allowed = ['status', 'start_date', 'end_date', 'site_address', 'progress', 'customer_name', 'notes', 'assigned_workers', 'highlighted'];
            $changes = [];

            foreach ($fields as $key => $value) {
                if (!in_array($key, $allowed)) continue;
                $meta_key = PI_JOB_META_PREFIX . $key;
                if ($key === 'assigned_workers') {
                    $value = array_map('intval', is_array($value) ? $value : []);
                } elseif ($key === 'progress') {
                    $value = max(0, min(100, (int) $value));
                } elseif ($key === 'highlighted') {
                    $value = (int) $value ? 1 : 0;
                } elseif ($key === 'notes') {
                    if (trim((string) $value) !== '') {
                        pi_jobs_add_activity($job_id, 'Note: ' . sanitize_textarea_field($value));
                        $changes[] = 'notes';
                    }
                    continue;
                } else {
                    $value = sanitize_text_field((string) $value);
                }
                $old = get_post_meta($job_id, $meta_key, true);
                if ($old != $value) {
                    update_post_meta($job_id, $meta_key, $value);
                    $changes[] = $key;
                }
            }
            if (!empty($changes)) pi_jobs_add_activity($job_id, 'Updated: ' . implode(', ', $changes));

            return rest_ensure_response(['updated' => true, 'changed' => $changes]);
        },
    ]);

    // Log activity for a job (used by materials, expenses, etc.)
    register_rest_route($namespace, '/jobs/(?P<id>\d+)/activity', [
        'methods' => 'POST',
        'permission_callback' => function ($req) {
            return is_user_logged_in() && pi_jobs_user_owns_job((int) $req['id']);
        },
        'callback' => function (WP_REST_Request $req) {
            $job_id = (int) $req['id'];
            $body = $req->get_json_params();
            $message = sanitize_text_field($body['message'] ?? '');
            $type = sanitize_text_field($body['type'] ?? 'general');
            
            if (empty($message)) {
                return new WP_Error('invalid', 'Message is required', ['status' => 400]);
            }
            
            $prefix = $type ? '[' . strtoupper($type) . '] ' : '';
            $full_message = $prefix . $message;
            pi_jobs_add_activity($job_id, $full_message);
            
            // Get updated activity count for debugging
            $activity = get_post_meta($job_id, PI_JOB_META_PREFIX . 'activity', true);
            $count = is_array($activity) ? count($activity) : 0;
            
            return rest_ensure_response([
                'logged' => true,
                'activity_count' => $count,
                'last_entry' => $count > 0 ? end($activity) : null
            ]);
        },
    ]);

    // Delete job endpoint - matches leads pattern
    register_rest_route($namespace, '/jobs/(?P<id>\d+)/remove', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function (WP_REST_Request $req) {
            $job_id = (int) $req['id'];
            $user_id = get_current_user_id();

            // Validate ownership inside callback (like leads does)
            if (get_post_type($job_id) !== PI_JOB_CPT || !pi_jobs_user_owns_job($job_id, $user_id)) {
                return new WP_Error('invalid', 'Invalid job or permission denied', ['status' => 400]);
            }

            // Get job info before deletion
            $job_code = get_the_title($job_id);

            // Delete the job post permanently (force delete, bypass trash)
            $result = wp_delete_post($job_id, true);

            if (!$result) {
                return new WP_Error('delete_failed', 'Failed to delete job', ['status' => 500]);
            }

            // Remove from workspace pipeline in all stages
            $workspace = pi_jobs_get_user_workspace($user_id);
            foreach ($workspace as $stage => &$jobs) {
                $jobs = array_values(array_filter($jobs, function($job) use ($job_id) {
                    return $job['id'] !== $job_id;
                }));
            }
            pi_jobs_save_user_workspace($user_id, $workspace);

            return rest_ensure_response(['deleted' => true, 'job_id' => $job_id]);
        },
    ]);
});
