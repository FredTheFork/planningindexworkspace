<?php
/**
 * Planning Index Workspace - Premium Calendar & Scheduler v3.0
 *
 * Construction-optimized calendar system with comprehensive features:
 * - Event management (create, update, delete, duplicate)
 * - Weather integration with Open-Meteo API
 * - Advanced filtering and search with saved views
 * - Crew scheduling with conflict detection and availability heatmap
 * - Event status tracking and priorities
 * - Job-centric mode with mini Gantt sidebar
 * - Recurring events support
 * - Bulk operations
 * - Event templates with construction-specific presets
 * - Export functionality
 * - Linked expenses integration
 * - Mileage logging from site visits
 * - Photo attachments and gallery
 * - Dependencies and critical path
 *
 * @package PlanningIndex
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PI_SCHED_META_KEY')) {
    define('PI_SCHED_META_KEY', '_pi_schedule_events');
}

if (!defined('PI_SCHED_TEMPLATES_KEY')) {
    define('PI_SCHED_TEMPLATES_KEY', '_pi_schedule_templates');
}

if (!defined('PI_SCHED_VIEWS_KEY')) {
    define('PI_SCHED_VIEWS_KEY', '_pi_schedule_saved_views');
}

// ──────────────────────────────────────────────────────────────────────────────
// Helper functions
// ──────────────────────────────────────────────────────────────────────────────

if (!function_exists('pi_scheduler_get_events_for_user')) {
    function pi_scheduler_get_events_for_user($user_id) {
        $events = get_user_meta($user_id, PI_SCHED_META_KEY, true);
        $events = is_array($events) ? $events : [];
        
        // Add sample events if user has no events
        if (empty($events)) {
            $events = pi_scheduler_get_sample_events($user_id);
            pi_scheduler_save_events_for_user($user_id, $events);
        }
        
        return $events;
    }
}

if (!function_exists('pi_scheduler_get_sample_events')) {
    function pi_scheduler_get_sample_events($user_id) {
        $now = current_time('timestamp');
        $today = date('Y-m-d', $now);
        $tomorrow = date('Y-m-d', strtotime('+1 day', $now));
        $next_week = date('Y-m-d', strtotime('+3 days', $now));
        $next_month = date('Y-m-d', strtotime('+14 days', $now));
        
        return [
            [
                'id' => 1,
                'job_id' => 0,
                'type' => 'site_visit',
                'title' => 'Weekly Site Inspection',
                'start' => $today . 'T10:00:00',
                'end' => $today . 'T12:00:00',
                'all_day' => false,
                'crew' => [],
                'notes' => 'Regular weekly site inspection and safety check',
                'status' => 'scheduled',
                'priority' => 'medium',
                'trade' => '',
                'weather_sensitive' => false,
                'created' => current_time('mysql'),
                'updated' => current_time('mysql')
            ],
            [
                'id' => 2,
                'job_id' => 0,
                'type' => 'delivery',
                'title' => 'Material Delivery - Bricks',
                'start' => $tomorrow . 'T09:00:00',
                'end' => $tomorrow . 'T13:00:00',
                'all_day' => true,
                'crew' => [],
                'notes' => 'Brick delivery for foundation work',
                'status' => 'scheduled',
                'priority' => 'high',
                'trade' => '',
                'weather_sensitive' => true,
                'created' => current_time('mysql'),
                'updated' => current_time('mysql')
            ],
            [
                'id' => 3,
                'job_id' => 0,
                'type' => 'delivery',
                'title' => 'Steel Beam Delivery',
                'start' => $next_week . 'T08:00:00',
                'end' => $next_week . 'T16:00:00',
                'all_day' => true,
                'crew' => [],
                'notes' => 'Structural steel beams for first floor',
                'status' => 'scheduled',
                'priority' => 'high',
                'trade' => '',
                'weather_sensitive' => true,
                'created' => current_time('mysql'),
                'updated' => current_time('mysql')
            ],
            [
                'id' => 4,
                'job_id' => 0,
                'type' => 'appointment',
                'title' => 'Client Meeting - Project Review',
                'start' => $next_month . 'T14:00:00',
                'end' => $next_month . 'T16:00:00',
                'all_day' => false,
                'crew' => [],
                'notes' => 'Monthly progress review with client',
                'status' => 'scheduled',
                'priority' => 'medium',
                'trade' => '',
                'weather_sensitive' => false,
                'created' => current_time('mysql'),
                'updated' => current_time('mysql')
            ],
            [
                'id' => 5,
                'job_id' => 0,
                'type' => 'job',
                'title' => 'Foundation Works',
                'start' => $today . 'T08:00:00',
                'end' => $today . 'T17:00:00',
                'all_day' => true,
                'crew' => [],
                'notes' => 'Concrete foundation pouring and finishing',
                'status' => 'in_progress',
                'priority' => 'high',
                'trade' => 'groundworks',
                'weather_sensitive' => true,
                'created' => current_time('mysql'),
                'updated' => current_time('mysql')
            ]
        ];
    }
}

if (!function_exists('pi_scheduler_save_events_for_user')) {
    function pi_scheduler_save_events_for_user($user_id, array $events) {
        update_user_meta($user_id, PI_SCHED_META_KEY, array_values($events));
    }
}

if (!function_exists('pi_scheduler_get_templates_for_user')) {
    function pi_scheduler_get_templates_for_user($user_id) {
        $templates = get_user_meta($user_id, PI_SCHED_TEMPLATES_KEY, true);
        if (!is_array($templates) || empty($templates)) {
            // Return default construction templates
            return pi_scheduler_get_default_templates();
        }
        return $templates;
    }
}

function pi_scheduler_get_default_templates() {
    return [
        [
            'id' => 1,
            'name' => 'Weekly Site Visit',
            'type' => 'site_visit',
            'title' => 'Site Visit - [JOB]',
            'duration_hours' => 2,
            'all_day' => false,
            'crew' => [],
            'notes' => 'Weekly progress check, safety inspection, and photo documentation.',
            'priority' => 'medium',
            'trade' => '',
            'weather_sensitive' => false,
            'checklist' => ['Safety check', 'Progress photos', 'Update client', 'Log mileage'],
            'created' => current_time('mysql')
        ],
        [
            'id' => 2,
            'name' => 'Material Delivery Window',
            'type' => 'delivery',
            'title' => 'Delivery - [MATERIALS]',
            'duration_hours' => 4,
            'all_day' => true,
            'crew' => [],
            'notes' => 'Materials delivery - ensure site access and offloading area clear.',
            'priority' => 'high',
            'trade' => '',
            'weather_sensitive' => true,
            'checklist' => ['Confirm delivery slot', 'Prepare offload area', 'Check materials against PO', 'Sign delivery note'],
            'created' => current_time('mysql')
        ],
        [
            'id' => 3,
            'name' => 'Subcontractor Rough-In',
            'type' => 'job',
            'title' => '[TRADE] Rough-In',
            'duration_hours' => 8,
            'all_day' => true,
            'crew' => [],
            'notes' => 'First fix / rough-in phase. Coordinate with other trades.',
            'priority' => 'high',
            'trade' => 'electrical',
            'weather_sensitive' => false,
            'checklist' => ['First fix complete', 'Test before closing', 'Photo documentation', 'Sign off'],
            'created' => current_time('mysql')
        ],
        [
            'id' => 4,
            'name' => 'Final Inspection',
            'type' => 'appointment',
            'title' => 'Final Inspection - [JOB]',
            'duration_hours' => 2,
            'all_day' => false,
            'crew' => [],
            'notes' => 'Building control / client final inspection.',
            'priority' => 'critical',
            'trade' => '',
            'weather_sensitive' => false,
            'checklist' => ['All works complete', 'Clean site', 'Certificates ready', 'Keys/access ready'],
            'created' => current_time('mysql')
        ],
        [
            'id' => 5,
            'name' => 'Scaffold Erection',
            'type' => 'job',
            'title' => 'Scaffold - Erect / Modify',
            'duration_hours' => 6,
            'all_day' => true,
            'crew' => [],
            'notes' => 'Scaffold erection or modification. Check wind speed forecast.',
            'priority' => 'high',
            'trade' => 'scaffolding',
            'weather_sensitive' => true,
            'checklist' => ['Wind speed < 25mph', 'Clear access', 'Handover certificate', 'Safety tags'],
            'created' => current_time('mysql')
        ],
    ];
}

if (!function_exists('pi_scheduler_save_templates_for_user')) {
    function pi_scheduler_save_templates_for_user($user_id, array $templates) {
        update_user_meta($user_id, PI_SCHED_TEMPLATES_KEY, array_values($templates));
    }
}

if (!function_exists('pi_scheduler_get_saved_views')) {
    function pi_scheduler_get_saved_views($user_id) {
        $views = get_user_meta($user_id, PI_SCHED_VIEWS_KEY, true);
        return is_array($views) ? $views : [];
    }
}

if (!function_exists('pi_scheduler_user_owns_job')) {
    function pi_scheduler_user_owns_job($job_id, $user_id = null) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if (!$job_id || !$user_id) return false;
        if (!defined('PI_JOB_META_PREFIX')) return false;
        return (int) get_post_meta($job_id, PI_JOB_META_PREFIX . 'owner_user_id', true) === $user_id;
    }
}

if (!function_exists('pi_scheduler_validate_event_data')) {
    function pi_scheduler_validate_event_data($data, $is_update = false) {
        $errors = [];

        if (!$is_update || isset($data['title'])) {
            $title = trim($data['title'] ?? '');
            if (empty($title)) {
                $errors[] = 'Title is required';
            }
        }

        if (!$is_update || isset($data['start'])) {
            $start = $data['start'] ?? '';
            if (empty($start) || !strtotime($start)) {
                $errors[] = 'Valid start date/time is required';
            }
        }

        if (isset($data['end']) && !empty($data['end'])) {
            if (!strtotime($data['end'])) {
                $errors[] = 'End date/time is invalid';
            } elseif (isset($data['start']) && strtotime($data['end']) < strtotime($data['start'])) {
                $errors[] = 'End date/time must be after start date/time';
            }
        }

        if (isset($data['priority'])) {
            $valid_priorities = ['low', 'medium', 'high', 'critical'];
            if (!in_array($data['priority'], $valid_priorities, true)) {
                $errors[] = 'Invalid priority value';
            }
        }

        if (isset($data['status'])) {
            $valid_statuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($data['status'], $valid_statuses, true)) {
                $errors[] = 'Invalid status value';
            }
        }

        return $errors;
    }
}

if (!function_exists('pi_scheduler_check_crew_conflicts')) {
    function pi_scheduler_check_crew_conflicts($crew_ids, $start, $end, $exclude_event_id, $user_id) {
        if (empty($crew_ids) || !is_array($crew_ids) || empty($start)) {
            return [];
        }

        $events = pi_scheduler_get_events_for_user($user_id);
        $conflicts = [];
        $start_ts = strtotime($start);
        $end_ts = $end ? strtotime($end) : $start_ts;

        foreach ($events as $ev) {
            if ((int)($ev['id'] ?? 0) === $exclude_event_id) continue;
            if (empty($ev['crew']) || !is_array($ev['crew'])) continue;

            $ev_start = strtotime($ev['start'] ?? '');
            $ev_end = !empty($ev['end']) ? strtotime($ev['end']) : $ev_start;

            if ($ev_start === false || $ev_end === false) continue;

            if (!($end_ts < $ev_start || $start_ts > $ev_end)) {
                $overlapping_crew = array_intersect($crew_ids, $ev['crew']);
                if (!empty($overlapping_crew)) {
                    $conflicts[] = [
                        'event_id' => $ev['id'],
                        'event_title' => $ev['title'],
                        'crew_ids' => array_values($overlapping_crew),
                        'start' => $ev['start'],
                        'end' => $ev['end']
                    ];
                }
            }
        }

        return $conflicts;
    }
}

// Get trades for dropdown
function pi_scheduler_get_trades() {
    return [
        '' => 'Select Trade',
        'bricklaying' => 'Bricklaying',
        'carpentry' => 'Carpentry',
        'electrical' => 'Electrical',
        'plumbing' => 'Plumbing',
        'roofing' => 'Roofing',
        'plastering' => 'Plastering',
        'tiling' => 'Tiling',
        'painting' => 'Painting & Decorating',
        'groundworks' => 'Groundworks',
        'scaffolding' => 'Scaffolding',
        'hvac' => 'HVAC',
        'flooring' => 'Flooring',
        'glazing' => 'Glazing',
        'landscaping' => 'Landscaping',
        'steelwork' => 'Steelwork',
        'specialist' => 'Specialist Contractor',
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// REST API
// ──────────────────────────────────────────────────────────────────────────────

add_action('rest_api_init', function () {
    $namespace = 'pi/v1';

    // Events endpoints
    register_rest_route($namespace, '/schedule/events', [
        'methods'             => 'GET',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_get_events',
    ]);

    register_rest_route($namespace, '/schedule/events/add', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_add_event',
    ]);

    register_rest_route($namespace, '/schedule/events/update', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_update_event',
    ]);

    register_rest_route($namespace, '/schedule/events/remove', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_remove_event',
    ]);

    register_rest_route($namespace, '/schedule/events/duplicate', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_duplicate_event',
    ]);

    register_rest_route($namespace, '/schedule/events/bulk-delete', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_bulk_delete',
    ]);

    register_rest_route($namespace, '/schedule/events/bulk-update', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_bulk_update',
    ]);

    // Linked expenses endpoint
    register_rest_route($namespace, '/schedule/events/linked-expenses', [
        'methods'             => 'GET',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_get_linked_expenses',
    ]);

    // Templates endpoints
    register_rest_route($namespace, '/schedule/templates', [
        'methods'             => 'GET',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_get_templates',
    ]);

    register_rest_route($namespace, '/schedule/templates/add', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_add_template',
    ]);

    register_rest_route($namespace, '/schedule/templates/remove', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_remove_template',
    ]);

    // Saved views endpoints
    register_rest_route($namespace, '/schedule/views', [
        'methods'             => 'GET',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_get_views',
    ]);

    register_rest_route($namespace, '/schedule/views/save', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_save_view',
    ]);

    register_rest_route($namespace, '/schedule/views/delete', [
        'methods'             => 'POST',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_delete_view',
    ]);

    // Crew users
    register_rest_route($namespace, '/schedule/crew-users', [
        'methods'             => 'GET',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_get_crew_users',
    ]);

    // Stats endpoint
    register_rest_route($namespace, '/schedule/stats', [
        'methods'             => 'GET',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => 'pi_scheduler_rest_get_stats',
    ]);
});

// REST Callback functions
function pi_scheduler_rest_get_events(WP_REST_Request $req) {
    $user_id    = get_current_user_id();
    $events     = pi_scheduler_get_events_for_user($user_id);
    $start_ts   = $req->get_param('start') ? strtotime($req->get_param('start')) : null;
    $end_ts     = $req->get_param('end')   ? strtotime($req->get_param('end'))   : null;
    $job_filter = (int) $req->get_param('job_id');
    $type_filter= sanitize_text_field((string) $req->get_param('type'));
    $status_filter = sanitize_text_field((string) $req->get_param('status'));
    $priority_filter = sanitize_text_field((string) $req->get_param('priority'));
    $crew_filter = sanitize_text_field((string) $req->get_param('crew'));
    $trade_filter = sanitize_text_field((string) $req->get_param('trade'));
    $search = sanitize_text_field((string) $req->get_param('search'));
    $out = [];

    $color_map = [
        'job'         => '#10b981',
        'site_visit'  => '#3b82f6',
        'delivery'    => '#f97316',
        'appointment' => '#8b5cf6',
    ];

    foreach ($events as $ev) {
        $ev_start = !empty($ev['start']) ? strtotime($ev['start']) : null;
        $ev_end   = !empty($ev['end'])   ? strtotime($ev['end'])   : $ev_start;

        if ($start_ts && $ev_end && $ev_end < $start_ts) continue;
        if ($end_ts && $ev_start && $ev_start > $end_ts) continue;
        if ($job_filter && (int)($ev['job_id'] ?? 0) !== $job_filter) continue;
        if ($type_filter && ($ev['type'] ?? '') !== $type_filter) continue;
        if ($status_filter && ($ev['status'] ?? 'scheduled') !== $status_filter) continue;
        if ($priority_filter && ($ev['priority'] ?? 'medium') !== $priority_filter) continue;
        if ($trade_filter && ($ev['trade'] ?? '') !== $trade_filter) continue;
        if ($crew_filter && (!in_array((int)$crew_filter, (array)($ev['crew'] ?? []), true))) continue;

        if ($search) {
            $searchable = strtolower(($ev['title'] ?? '') . ' ' . ($ev['notes'] ?? '') . ' ' . ($ev['supplier_name'] ?? ''));
            if (strpos($searchable, strtolower($search)) === false) continue;
        }

        $job_id   = (int)($ev['job_id'] ?? 0);
        $job_code = '';
        $job_postcode = '';
        $cpt      = defined('PI_JOB_CPT') ? PI_JOB_CPT : 'pi_job';
        if ($job_id && get_post_type($job_id) === $cpt) {
            $p = get_post($job_id);
            if ($p) {
                $job_code = $p->post_title;
                $job_postcode = get_post_meta($job_id, PI_JOB_META_PREFIX . 'site_address_postcode', true);
            }
        }

        $type  = $ev['type'] ?? 'job';
        $title = $ev['title'] ?? '';
        $status = $ev['status'] ?? 'scheduled';
        $priority = $ev['priority'] ?? 'medium';

        $display_prefix = '';
        if ($status === 'completed') $display_prefix = '✓ ';
        if ($status === 'cancelled') $display_prefix = '✕ ';
        if ($job_code) $display_prefix .= "[$job_code] ";

        $display = $display_prefix . $title;

        $color = $ev['color'] ?? $color_map[$type] ?? '#4b5563';

        if ($status === 'completed') $color = '#6b7280';
        if ($status === 'cancelled') $color = '#9ca3af';

        $out[] = [
            'id'              => (int)$ev['id'],
            'title'           => $display,
            'start'           => $ev['start'],
            'end'             => $ev['end'] ?? null,
            'allDay'          => !empty($ev['all_day']),
            'backgroundColor' => $color,
            'borderColor'     => $color,
            'extendedProps'   => [
                'raw' => $ev,
                'job_id' => $job_id,
                'job_postcode' => $job_postcode,
                'type' => $type,
                'status' => $status,
                'priority' => $priority,
                'source' => 'scheduler'
            ],
        ];
    }
    return rest_ensure_response($out);
}

function pi_scheduler_rest_add_event(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $data    = $req->get_json_params();

    $validation_errors = pi_scheduler_validate_event_data($data, false);
    if (!empty($validation_errors)) {
        return new WP_Error('validation_failed', implode('; ', $validation_errors), ['status' => 400]);
    }

    $job_id  = isset($data['job_id']) ? (int)$data['job_id'] : 0;

    if ($job_id && !pi_scheduler_user_owns_job($job_id, $user_id)) {
        return new WP_Error('forbidden', 'You do not own this job', ['status' => 403]);
    }

    $allowed = ['job','site_visit','delivery','appointment'];
    $type    = in_array($data['type'] ?? '', $allowed, true) ? $data['type'] : 'job';
    $title   = sanitize_text_field($data['title'] ?? '');

    $start = sanitize_text_field($data['start'] ?? '');
    $end   = sanitize_text_field($data['end'] ?? '');

    $crew = array_map('intval', (array)($data['crew'] ?? []));

    $conflicts = pi_scheduler_check_crew_conflicts($crew, $start, $end, 0, $user_id);
    if (!empty($conflicts) && empty($data['ignore_conflicts'])) {
        return rest_ensure_response([
            'conflicts' => $conflicts,
            'requires_confirmation' => true
        ]);
    }

    $events  = pi_scheduler_get_events_for_user($user_id);
    $next_id = 1;
    foreach ($events as $ev) {
        if (($ev['id'] ?? 0) >= $next_id) $next_id = (int)$ev['id'] + 1;
    }

    $new = [
        'id'                => $next_id,
        'job_id'            => $job_id,
        'type'              => $type,
        'title'             => $title,
        'start'             => $start,
        'end'               => $end,
        'all_day'           => !empty($data['all_day']),
        'crew'              => $crew,
        'notes'             => sanitize_textarea_field($data['notes'] ?? ''),
        'status'            => sanitize_text_field($data['status'] ?? 'scheduled'),
        'priority'          => sanitize_text_field($data['priority'] ?? 'medium'),
        'trade'             => sanitize_text_field($data['trade'] ?? ''),
        'tags'              => array_map('sanitize_text_field', (array)($data['tags'] ?? [])),
        'color'             => sanitize_hex_color($data['color'] ?? ''),
        'weather_sensitive' => !empty($data['weather_sensitive']),
        'supplier_name'     => sanitize_text_field($data['supplier_name'] ?? ''),
        'po_number'         => sanitize_text_field($data['po_number'] ?? ''),
        'checklist'         => (array)($data['checklist'] ?? []),
        'attachments'       => (array)($data['attachments'] ?? []),
        'dependencies'      => (array)($data['dependencies'] ?? []),
        'created'           => current_time('mysql'),
        'updated'           => current_time('mysql'),
    ];

    $events[] = $new;
    pi_scheduler_save_events_for_user($user_id, $events);
    return rest_ensure_response(['created' => true, 'event' => $new]);
}

function pi_scheduler_rest_update_event(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $data    = $req->get_json_params();
    $id      = (int)($data['id'] ?? 0);
    if ($id <= 0) return new WP_Error('invalid_id','Missing or invalid event ID',['status'=>400]);

    $validation_errors = pi_scheduler_validate_event_data($data, true);
    if (!empty($validation_errors)) {
        return new WP_Error('validation_failed', implode('; ', $validation_errors), ['status' => 400]);
    }

    $events  = pi_scheduler_get_events_for_user($user_id);
    $allowed = ['job','site_visit','delivery','appointment'];
    $found   = false;

    foreach ($events as &$ev) {
        if ((int)($ev['id'] ?? 0) !== $id) continue;
        $found = true;

        $updated_crew = isset($data['crew']) ? array_map('intval', (array)$data['crew']) : ($ev['crew'] ?? []);
        $updated_start = isset($data['start']) ? sanitize_text_field($data['start']) : ($ev['start'] ?? '');
        $updated_end = isset($data['end']) ? sanitize_text_field($data['end']) : ($ev['end'] ?? '');

        $conflicts = pi_scheduler_check_crew_conflicts($updated_crew, $updated_start, $updated_end, $id, $user_id);
        if (!empty($conflicts) && empty($data['ignore_conflicts'])) {
            return rest_ensure_response([
                'conflicts' => $conflicts,
                'requires_confirmation' => true
            ]);
        }

        if (isset($data['job_id'])) {
            $jid = (int)$data['job_id'];
            if ($jid && !pi_scheduler_user_owns_job($jid, $user_id))
                return new WP_Error('forbidden','You do not own this job',['status'=>403]);
            $ev['job_id'] = $jid;
        }
        if (isset($data['type']) && in_array($data['type'], $allowed, true)) $ev['type'] = $data['type'];
        if (isset($data['title'])) $ev['title'] = sanitize_text_field($data['title']);
        if (isset($data['start'])) $ev['start'] = $updated_start;
        if (isset($data['end'])) $ev['end'] = $updated_end;
        if (isset($data['all_day'])) $ev['all_day'] = !empty($data['all_day']);
        if (isset($data['crew'])) $ev['crew'] = $updated_crew;
        if (isset($data['notes'])) $ev['notes'] = sanitize_textarea_field($data['notes']);
        if (isset($data['status'])) $ev['status'] = sanitize_text_field($data['status']);
        if (isset($data['priority'])) $ev['priority'] = sanitize_text_field($data['priority']);
        if (isset($data['trade'])) $ev['trade'] = sanitize_text_field($data['trade']);
        if (isset($data['tags'])) $ev['tags'] = array_map('sanitize_text_field', (array)$data['tags']);
        if (isset($data['color'])) $ev['color'] = sanitize_hex_color($data['color']);
        if (isset($data['weather_sensitive'])) $ev['weather_sensitive'] = !empty($data['weather_sensitive']);
        if (isset($data['supplier_name'])) $ev['supplier_name'] = sanitize_text_field($data['supplier_name']);
        if (isset($data['po_number'])) $ev['po_number'] = sanitize_text_field($data['po_number']);
        if (isset($data['checklist'])) $ev['checklist'] = (array)$data['checklist'];
        if (isset($data['attachments'])) $ev['attachments'] = (array)$data['attachments'];
        if (isset($data['dependencies'])) $ev['dependencies'] = (array)$data['dependencies'];
        $ev['updated'] = current_time('mysql');
        break;
    }
    unset($ev);

    if (!$found) return new WP_Error('not_found','Event not found',['status'=>404]);
    pi_scheduler_save_events_for_user($user_id, $events);
    return rest_ensure_response(['updated' => true, 'event' => $events[array_search($id, array_column($events, 'id'))]]);
}

function pi_scheduler_rest_remove_event(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $id      = (int)$req->get_param('id');
    if ($id <= 0) return new WP_Error('invalid_id','Missing or invalid event ID',['status'=>400]);

    $events = pi_scheduler_get_events_for_user($user_id);
    $before = count($events);
    $events = array_values(array_filter($events, fn($ev) => (int)($ev['id'] ?? 0) !== $id));
    pi_scheduler_save_events_for_user($user_id, $events);
    return rest_ensure_response(['removed' => count($events) < $before]);
}

function pi_scheduler_rest_duplicate_event(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $id      = (int)$req->get_param('id');
    if ($id <= 0) return new WP_Error('invalid_id','Missing or invalid event ID',['status'=>400]);

    $events = pi_scheduler_get_events_for_user($user_id);
    $source = null;
    foreach ($events as $ev) {
        if ((int)($ev['id'] ?? 0) === $id) {
            $source = $ev;
            break;
        }
    }

    if (!$source) return new WP_Error('not_found','Event not found',['status'=>404]);

    $next_id = 1;
    foreach ($events as $ev) {
        if (($ev['id'] ?? 0) >= $next_id) $next_id = (int)$ev['id'] + 1;
    }

    $duplicate = $source;
    $duplicate['id'] = $next_id;
    $duplicate['title'] = ($source['title'] ?? '') . ' (Copy)';
    $duplicate['created'] = current_time('mysql');
    $duplicate['updated'] = current_time('mysql');

    $events[] = $duplicate;
    pi_scheduler_save_events_for_user($user_id, $events);
    return rest_ensure_response(['duplicated' => true, 'event' => $duplicate]);
}

function pi_scheduler_rest_bulk_delete(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $ids = array_map('intval', (array)$req->get_param('ids'));
    if (empty($ids)) return new WP_Error('invalid_ids','No event IDs provided',['status'=>400]);

    $events = pi_scheduler_get_events_for_user($user_id);
    $before = count($events);
    $events = array_values(array_filter($events, fn($ev) => !in_array((int)($ev['id'] ?? 0), $ids, true)));
    pi_scheduler_save_events_for_user($user_id, $events);
    $deleted = $before - count($events);
    return rest_ensure_response(['deleted' => $deleted, 'count' => $deleted]);
}

function pi_scheduler_rest_bulk_update(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $data = $req->get_json_params();
    $ids = array_map('intval', (array)($data['ids'] ?? []));
    $updates = $data['updates'] ?? [];

    if (empty($ids) || empty($updates)) {
        return new WP_Error('invalid_data','IDs and updates required',['status'=>400]);
    }

    $events = pi_scheduler_get_events_for_user($user_id);
    $updated_count = 0;

    foreach ($events as &$ev) {
        if (!in_array((int)($ev['id'] ?? 0), $ids, true)) continue;

        if (isset($updates['status'])) $ev['status'] = sanitize_text_field($updates['status']);
        if (isset($updates['priority'])) $ev['priority'] = sanitize_text_field($updates['priority']);
        if (isset($updates['type'])) $ev['type'] = sanitize_text_field($updates['type']);
        $ev['updated'] = current_time('mysql');
        $updated_count++;
    }
    unset($ev);

    pi_scheduler_save_events_for_user($user_id, $events);
    return rest_ensure_response(['updated' => $updated_count]);
}

function pi_scheduler_rest_get_linked_expenses(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $job_id = (int)$req->get_param('job_id');
    $date = sanitize_text_field($req->get_param('date'));

    global $wpdb;
    $table = $wpdb->prefix . 'pi_expenses';

    $query = "SELECT * FROM {$table} WHERE user_id = %d AND deleted_at IS NULL";
    $args = [$user_id];

    if ($job_id) {
        $query .= " AND job_id = %d";
        $args[] = $job_id;
    }
    if ($date) {
        $query .= " AND expense_date = %s";
        $args[] = $date;
    }

    $query .= " ORDER BY expense_date DESC LIMIT 50";

    $expenses = $wpdb->get_results($wpdb->prepare($query, $args), ARRAY_A);

    return rest_ensure_response($expenses);
}

function pi_scheduler_rest_get_templates() {
    $user_id = get_current_user_id();
    $templates = pi_scheduler_get_templates_for_user($user_id);
    return rest_ensure_response($templates);
}

function pi_scheduler_rest_add_template(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $data = $req->get_json_params();

    if (empty($data['name'])) {
        return new WP_Error('invalid_name','Template name required',['status'=>400]);
    }

    $templates = pi_scheduler_get_templates_for_user($user_id);
    $next_id = 1;
    foreach ($templates as $t) {
        if (($t['id'] ?? 0) >= $next_id) $next_id = (int)$t['id'] + 1;
    }

    $template = [
        'id' => $next_id,
        'name' => sanitize_text_field($data['name']),
        'type' => sanitize_text_field($data['type'] ?? 'job'),
        'title' => sanitize_text_field($data['title'] ?? ''),
        'duration_hours' => (int)($data['duration_hours'] ?? 8),
        'all_day' => !empty($data['all_day']),
        'crew' => array_map('intval', (array)($data['crew'] ?? [])),
        'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        'priority' => sanitize_text_field($data['priority'] ?? 'medium'),
        'trade' => sanitize_text_field($data['trade'] ?? ''),
        'weather_sensitive' => !empty($data['weather_sensitive']),
        'checklist' => (array)($data['checklist'] ?? []),
        'created' => current_time('mysql')
    ];

    $templates[] = $template;
    pi_scheduler_save_templates_for_user($user_id, $templates);
    return rest_ensure_response(['created' => true, 'template' => $template]);
}

function pi_scheduler_rest_remove_template(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $id = (int)$req->get_param('id');
    if ($id <= 0) return new WP_Error('invalid_id','Invalid template ID',['status'=>400]);

    $templates = pi_scheduler_get_templates_for_user($user_id);
    $templates = array_values(array_filter($templates, fn($t) => (int)($t['id'] ?? 0) !== $id));
    pi_scheduler_save_templates_for_user($user_id, $templates);
    return rest_ensure_response(['removed' => true]);
}

function pi_scheduler_rest_get_views() {
    $user_id = get_current_user_id();
    $views = pi_scheduler_get_saved_views($user_id);
    return rest_ensure_response($views);
}

function pi_scheduler_rest_save_view(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $data = $req->get_json_params();

    if (empty($data['name'])) {
        return new WP_Error('invalid_name','View name required',['status'=>400]);
    }

    $views = pi_scheduler_get_saved_views($user_id);
    $next_id = 1;
    foreach ($views as $v) {
        if (($v['id'] ?? 0) >= $next_id) $next_id = (int)$v['id'] + 1;
    }

    $view = [
        'id' => $next_id,
        'name' => sanitize_text_field($data['name']),
        'filters' => $data['filters'] ?? [],
        'created' => current_time('mysql')
    ];

    $views[] = $view;
    update_user_meta($user_id, PI_SCHED_VIEWS_KEY, $views);
    return rest_ensure_response(['created' => true, 'view' => $view]);
}

function pi_scheduler_rest_delete_view(WP_REST_Request $req) {
    $user_id = get_current_user_id();
    $id = (int)$req->get_param('id');

    $views = pi_scheduler_get_saved_views($user_id);
    $views = array_values(array_filter($views, fn($v) => ($v['id'] ?? 0) !== $id));
    update_user_meta($user_id, PI_SCHED_VIEWS_KEY, $views);
    return rest_ensure_response(['deleted' => true]);
}

function pi_scheduler_rest_get_crew_users() {
    $workers = get_users(['fields' => ['ID','display_name'], 'orderby' => 'display_name', 'order' => 'ASC']);
    $out = [];
    foreach ($workers as $w) {
        $out[] = ['id' => (int)$w->ID, 'name' => $w->display_name];
    }
    return rest_ensure_response($out);
}

function pi_scheduler_rest_get_stats() {
    $user_id = get_current_user_id();
    $events = pi_scheduler_get_events_for_user($user_id);
    $now = current_time('timestamp');

    $stats = [
        'total' => count($events),
        'upcoming' => 0,
        'site_visits' => 0,  // Today's site visits only
        'crew_on_site' => 0,
        'pending_deliveries' => 0,  // Future deliveries only
        'weather_sensitive' => 0,
        'overdue' => 0,
        'today' => 0,
        'this_week' => 0,
    ];

    $today_start = strtotime('today', $now);
    $today_end = strtotime('tomorrow', $now) - 1;
    $week_end = strtotime('+7 days', $now);

    foreach ($events as $ev) {
        $status = $ev['status'] ?? 'scheduled';
        $type = $ev['type'] ?? 'job';
        $ev_start = strtotime($ev['start'] ?? 'now');

        if ($status === 'cancelled') continue;

        // Count today's site visits ONLY (not all site visits)
        if ($type === 'site_visit' && $status !== 'completed' && $ev_start >= $today_start && $ev_start <= $today_end) {
            $stats['site_visits']++;
        }
        
        // Count upcoming/future deliveries ONLY (not all deliveries)
        if ($type === 'delivery' && $status !== 'completed' && $ev_start > $now) {
            $stats['pending_deliveries']++;
        }
        
        if (!empty($ev['weather_sensitive'])) {
            $stats['weather_sensitive']++;
        }
        if (!empty($ev['crew'])) {
            $stats['crew_on_site'] += count((array)$ev['crew']);
        }

        if ($ev_start > $now && $status !== 'completed') {
            $stats['upcoming']++;
        }
        if ($ev_start >= $today_start && $ev_start <= $today_end && $status !== 'completed') {
            $stats['today']++;
        }
        if ($ev_start >= $today_start && $ev_start <= $week_end && $status !== 'completed') {
            $stats['this_week']++;
        }
        if ($ev_start < $now && $status !== 'completed') {
            $stats['overdue']++;
        }
    }

    return rest_ensure_response($stats);
}

// ──────────────────────────────────────────────────────────────────────────────
// Shortcode
// ──────────────────────────────────────────────────────────────────────────────

function pi_workspace_calendar_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="pi-calendar-wrapper-v3">
            <div class="pi-auth-required">
                <div class="pi-auth-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3>Sign In Required</h3>
                <p>Please log in to access your calendar.</p>
            </div>
        </div>';
    }

    wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null);
    wp_enqueue_style('pi-workspace-calendar-v3-style', plugin_dir_url(__FILE__) . '../assets/calendar.css', [], '3.0.0');
    wp_enqueue_style('fullcalendar-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css', [], '6.1.10');
    wp_enqueue_script('fullcalendar-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', [], '6.1.10', true);
    wp_enqueue_script('pi-workspace-calendar-v3-script', plugin_dir_url(__FILE__) . '../assets/calendar.js', ['fullcalendar-core', 'jquery'], '3.0.0', true);

    $user_id = get_current_user_id();
    $jobs_for_filter = [];
    if (defined('PI_JOB_CPT') && defined('PI_JOB_META_PREFIX')) {
        $jobs = get_posts([
            'post_type' => PI_JOB_CPT, 'posts_per_page' => -1,
            'post_status' => ['publish','draft','pending','private'],
            'meta_key' => PI_JOB_META_PREFIX . 'owner_user_id', 'meta_value' => $user_id,
        ]);
        foreach ($jobs as $job) {
            $jobs_for_filter[] = [
                'id' => $job->ID,
                'title' => $job->post_title,
                'postcode' => get_post_meta($job->ID, PI_JOB_META_PREFIX . 'site_address_postcode', true)
            ];
        }
    }

    $workers = get_users(['fields' => ['ID','display_name'], 'orderby' => 'display_name', 'order' => 'ASC']);
    $trades = pi_scheduler_get_trades();
    $saved_views = pi_scheduler_get_saved_views($user_id);

    wp_localize_script('pi-workspace-calendar-v3-script', 'PI_Calendar', [
        'rest_base' => esc_url_raw(rest_url('pi/v1/schedule')),
        'nonce'     => wp_create_nonce('wp_rest'),
        'jobs'      => $jobs_for_filter,
        'trades'    => $trades,
        'saved_views' => $saved_views,
        'user_id'   => $user_id,
    ]);

    ob_start();
    ?>
    <div class="pi-calendar-wrapper-v3" id="pi-calendar-app">
        <!-- Premium SaaS Header -->
        <header class="pi-cal-saas-header">
            <div class="pi-cal-header-left">
                <div class="pi-cal-brand">
                    <div class="pi-cal-brand-text">
                        <h1>Calendar</h1>
                    </div>
                </div>
            </div>

            <!-- Live KPI Pills -->
            <div class="pi-cal-kpi-bar">
                <div class="pi-cal-kpi-pill" data-kpi="total">
                    <span class="pi-cal-kpi-value" id="pi-cal-kpi-total">0</span><?php // No whitespace here ?><span class="pi-cal-kpi-label">Total Events</span>
                </div>
                <div class="pi-cal-kpi-pill" data-kpi="upcoming">
                    <span class="pi-cal-kpi-value" id="pi-cal-kpi-upcoming">0</span><?php // No whitespace here ?><span class="pi-cal-kpi-label">Upcoming</span>
                </div>
                <div class="pi-cal-kpi-pill" data-kpi="visits">
                    <span class="pi-cal-kpi-value" id="pi-cal-kpi-visits">0</span><?php // No whitespace here ?><span class="pi-cal-kpi-label">Site Visits</span>
                </div>
                <div class="pi-cal-kpi-pill" data-kpi="crew">
                    <span class="pi-cal-kpi-value" id="pi-cal-kpi-crew">0</span><?php // No whitespace here ?><span class="pi-cal-kpi-label">Crew On Site</span>
                </div>
                <div class="pi-cal-kpi-pill pi-cal-kpi-pill--warning" data-kpi="deliveries">
                    <span class="pi-cal-kpi-value" id="pi-cal-kpi-deliveries">0</span><?php // No whitespace here ?><span class="pi-cal-kpi-label">Pending Deliveries</span>
                </div>
            </div>

            <div class="pi-cal-header-right">
                <div class="pi-cal-header-right-inner">
                    <!-- Global Search -->
                    <div class="pi-cal-search-box">
                        <svg class="pi-cal-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" id="pi-cal-search-input" placeholder="Search events... (/" autocomplete="off" />
                        <button type="button" class="pi-cal-search-clear" id="pi-cal-search-clear" style="display:none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <span class="pi-cal-btn-spacer"></span>

                    <!-- Jump to Date -->
                    <div class="pi-cal-btn-wrap">
                        <button type="button" class="pi-cal-btn pi-cal-btn--secondary" id="pi-cal-jump-btn" title="Jump to date (J)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg><span>Jump</span>
                        </button>
                    </div>

                    <!-- Templates -->
                    <div class="pi-cal-btn-wrap">
                        <button type="button" class="pi-cal-btn pi-cal-btn--secondary" id="pi-cal-templates-btn" title="Templates">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg><span>Templates</span>
                        </button>
                    </div>

                    <!-- Lookahead -->
                    <div class="pi-cal-btn-wrap">
                        <button type="button" class="pi-cal-btn pi-cal-btn--secondary" id="pi-cal-lookahead-btn" title="3-Week Lookahead">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg><span>Lookahead</span>
                        </button>
                    </div>

                    <!-- New Event -->
                    <div class="pi-cal-btn-wrap">

                        <button type="button" class="pi-cal-btn pi-cal-btn--primary" id="pi-cal-add-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg><span>New Event</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Filter Bar with Multi-select Chips -->
        <div class="pi-cal-filter-bar">
            <div class="pi-cal-filters-inner">
                <div class="pi-cal-filter-group">
                    <label for="pi-cal-filter-type">Type</label><?php // No whitespace here ?><select id="pi-cal-filter-type" class="pi-cal-select">
                        <option value="">All Types</option>
                        <option value="job">Job Windows</option>
                        <option value="site_visit">Site Visits</option>
                        <option value="delivery">Deliveries</option>
                        <option value="appointment">Appointments</option>
                    </select>
                </div>

                <div class="pi-cal-filter-group">
                    <label for="pi-cal-filter-job">Job</label><?php // No whitespace here ?><select id="pi-cal-filter-job" class="pi-cal-select">
                        <option value="">All Jobs</option>
                        <?php foreach ($jobs_for_filter as $job) : ?>
                            <option value="<?php echo esc_attr($job['id']); ?>"><?php echo esc_html($job['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pi-cal-filter-group">
                    <label for="pi-cal-filter-status">Status</label><?php // No whitespace here ?><select id="pi-cal-filter-status" class="pi-cal-select">
                        <option value="">All Statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="pi-cal-filter-group">
                    <label for="pi-cal-filter-priority">Priority</label><?php // No whitespace here ?><select id="pi-cal-filter-priority" class="pi-cal-select">
                        <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <div class="pi-cal-filter-group">
                    <label for="pi-cal-filter-crew">Crew</label><?php // No whitespace here ?><select id="pi-cal-filter-crew" class="pi-cal-select">
                        <option value="">All Crew</option>
                        <?php foreach ($workers as $w) : ?>
                            <option value="<?php echo esc_attr($w->ID); ?>"><?php echo esc_html($w->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pi-cal-filter-group">
                    <label for="pi-cal-filter-trade">Trade</label><?php // No whitespace here ?><select id="pi-cal-filter-trade" class="pi-cal-select">
                        <?php foreach ($trades as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Saved Views -->
                <div class="pi-cal-filter-group pi-cal-saved-views">
                    <label for="pi-cal-saved-views">Saved Views</label><?php // No whitespace here ?><select id="pi-cal-saved-views" class="pi-cal-select">
                        <option value="">Select View...</option>
                        <?php foreach ($saved_views as $view) : ?>
                            <option value="<?php echo esc_attr($view['id']); ?>" data-filters="<?php echo esc_attr(json_encode($view['filters'])); ?>"><?php echo esc_html($view['name']); ?></option>
                        <?php endforeach; ?>
                        <option value="__save__">+ Save Current View</option>
                    </select>
                </div>

                <!-- Filter Actions -->
                <div class="pi-cal-filter-actions">
                    <button type="button" class="pi-cal-btn pi-cal-btn--ghost" id="pi-cal-clear-filters">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg><span>Clear</span>
                    </button>
                </div>
            </div>

            <!-- Legend -->
            <div class="pi-cal-legend">
                <span class="pi-cal-legend-item"><span class="pi-cal-legend-dot pi-cal-legend-dot--job"></span>Jobs</span>
                <span class="pi-cal-legend-item"><span class="pi-cal-legend-dot pi-cal-legend-dot--visit"></span>Visits</span>
                <span class="pi-cal-legend-item"><span class="pi-cal-legend-dot pi-cal-legend-dot--delivery"></span>Deliveries</span>
                <span class="pi-cal-legend-item"><span class="pi-cal-legend-dot pi-cal-legend-dot--appointment"></span>Appointments</span>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="pi-cal-main">
            <!-- Calendar Container -->
            <div class="pi-cal-calendar-container">
                <!-- Quick Links Bar -->
                <div class="pi-cal-quick-links">
                    <span class="pi-cal-quick-links-label">Quick Links:</span>
                    <button type="button" class="pi-cal-quick-link" id="pi-cal-today-visits">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                        </svg><span>Today's Site Visits</span>
                        <span class="pi-cal-quick-link-badge" id="pi-cal-badge-visits">0</span>
                    </button>
                    <button type="button" class="pi-cal-quick-link" id="pi-cal-upcoming-deliveries">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg><span>Upcoming Deliveries</span>
                        <span class="pi-cal-quick-link-badge" id="pi-cal-badge-deliveries">0</span>
                    </button>
                    <button type="button" class="pi-cal-quick-link" id="pi-cal-crew-conflicts">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg><span>Crew Conflicts</span>
                        <span class="pi-cal-quick-link-badge pi-cal-quick-link-badge--alert" id="pi-cal-badge-conflicts">0</span>
                    </button>
                    <button type="button" class="pi-cal-quick-link pi-cal-job-mode-toggle" id="pi-cal-job-mode-toggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg><span>Job-Centric Mode</span>
                    </button>
                </div>

                <!-- View Toggle -->
                <div class="pi-cal-view-toggle">
                    <button type="button" class="pi-cal-view-btn active" data-view="dayGridMonth">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg><span>Month</span>
                    </button>
                    <button type="button" class="pi-cal-view-btn" data-view="timeGridWeek">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg><span>Week</span>
                    </button>
                    <button type="button" class="pi-cal-view-btn" data-view="timeGridDay">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg><span>Day</span>
                    </button>
                    <button type="button" class="pi-cal-view-btn" data-view="listWeek">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg><span>List</span>
                    </button>
                    <button type="button" class="pi-cal-view-btn" data-view="gantt" id="pi-cal-gantt-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>
                        </svg><span>Timeline</span>
                    </button>
                </div>

                <!-- Calendar -->
                <div class="pi-cal-calendar-card">
                    <div class="pi-cal-loading-overlay" id="pi-cal-loading" style="display: none;">
                        <div class="pi-cal-spinner"></div>
                    </div>
                    <div id="pi-workspace-calendar"></div>
                </div>
            </div>

            <!-- Job-Centric Sidebar (hidden by default) -->
            <aside class="pi-cal-job-sidebar" id="pi-cal-job-sidebar" style="display: none;">
                <div class="pi-cal-sidebar-header">
                    <h3>Job Timeline</h3>
                    <button type="button" class="pi-cal-sidebar-close" id="pi-cal-sidebar-close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="pi-cal-job-selector">
                    <select id="pi-cal-job-select" class="pi-cal-select">
                        <option value="">Select a job...</option>
                        <?php foreach ($jobs_for_filter as $job) : ?>
                            <option value="<?php echo esc_attr($job['id']); ?>" data-postcode="<?php echo esc_attr($job['postcode']); ?>"><?php echo esc_html($job['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pi-cal-job-mini-gantt" id="pi-cal-mini-gantt">
                    <div class="pi-cal-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                        <p>Select a job to view timeline</p>
                    </div>
                </div>
                <div class="pi-cal-job-stats" id="pi-cal-job-stats"></div>
            </aside>
        </div>

        <!-- Keyboard Shortcuts Panel -->
        <div class="pi-cal-shortcuts-panel" id="pi-cal-shortcuts-panel" style="display: none;">
            <div class="pi-cal-shortcuts-header">
                <h3>Keyboard Shortcuts</h3>
                <button type="button" class="pi-cal-shortcuts-close" id="pi-cal-shortcuts-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="pi-cal-shortcuts-body">
                <div class="pi-cal-shortcut-item"><kbd>N</kbd><span>New event</span></div>
                <div class="pi-cal-shortcut-item"><kbd>/</kbd><span>Search events</span></div>
                <div class="pi-cal-shortcut-item"><kbd>T</kbd><span>Jump to today</span></div>
                <div class="pi-cal-shortcut-item"><kbd>J</kbd><span>Jump to date</span></div>
                <div class="pi-cal-shortcut-item"><kbd>L</kbd><span>Toggle job-centric mode</span></div>
                <div class="pi-cal-shortcut-item"><kbd>G</kbd><span>Toggle Gantt/Timeline view</span></div>
                <div class="pi-cal-shortcut-item"><kbd>←</kbd> / <kbd>→</kbd><span>Previous / Next period</span></div>
                <div class="pi-cal-shortcut-item"><kbd>ESC</kbd><span>Close modal or cancel</span></div>
                <div class="pi-cal-shortcut-item"><kbd>?</kbd><span>Toggle shortcuts</span></div>
            </div>
        </div>
    </div>

    <!-- Event Modal with Tabs -->
    <div class="pi-cal-modal-backdrop" id="pi-cal-event-modal">
        <div class="pi-cal-modal pi-cal-modal--large">
            <div class="pi-cal-modal-header">
                <div class="pi-cal-modal-header-left" style="align-items: center; justify-content: center;">
                    <div class="pi-cal-modal-icon" id="pi-cal-modal-icon" style="flex-shrink: 0; margin-right: 12px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: block;">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="pi-cal-modal-title-group" style="display: flex; flex-direction: column; justify-content: center; flex-shrink: 0;">
                        <span id="pi-cal-modal-title" style="flex-shrink: 0; line-height: 1.2;"><bold>New Event</bold></span>
                    </div>
                </div>
                <button type="button" class="pi-cal-modal-close" id="pi-cal-modal-close" aria-label="Close modal">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <!-- Modal Tabs -->
            <div class="pi-cal-modal-tabs">
                <button type="button" class="pi-cal-tab-btn active" data-tab="details"><?php esc_html_e('Details', 'planningindex'); ?></button>
                <button type="button" class="pi-cal-tab-btn" data-tab="crew"><?php esc_html_e('Crew & Resources', 'planningindex'); ?></button>
                <button type="button" class="pi-cal-tab-btn" data-tab="checklist"><?php esc_html_e('Checklist', 'planningindex'); ?></button>
                <button type="button" class="pi-cal-tab-btn" data-tab="attachments"><?php esc_html_e('Attachments', 'planningindex'); ?></button>
                <button type="button" class="pi-cal-tab-btn" data-tab="expenses"><?php esc_html_e('Linked Expenses', 'planningindex'); ?></button>
                <button type="button" class="pi-cal-tab-btn" data-tab="notes"><?php esc_html_e('Notes', 'planningindex'); ?></button>
            </div>

            <div class="pi-cal-modal-body">
                <form id="pi-cal-event-form" novalidate>
                    <input type="hidden" id="pi-cal-event-id" />

                    <!-- Tab: Details -->
                    <div class="pi-cal-tab-content active" data-tab="details">
                        <div class="pi-cal-field-grid">
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-type"><?php esc_html_e('Type', 'planningindex'); ?> <span class="pi-cal-required">*</span></label>
                                <select id="pi-cal-event-type" class="pi-cal-input" required><?php // No whitespace here ?><option value="job"><?php esc_html_e('Job Window', 'planningindex'); ?></option><?php // No whitespace here ?><option value="site_visit"><?php esc_html_e('Site Visit', 'planningindex'); ?></option><?php // No whitespace here ?><option value="delivery"><?php esc_html_e('Delivery', 'planningindex'); ?></option><?php // No whitespace here ?><option value="appointment"><?php esc_html_e('Appointment', 'planningindex'); ?></option><?php // No whitespace here ?></select>
                            </div>
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-job"><?php esc_html_e('Job', 'planningindex'); ?></label>
                                <select id="pi-cal-event-job" class="pi-cal-input"><?php // No whitespace here ?><option value=""><?php esc_html_e('Unassigned', 'planningindex'); ?></option><?php // No whitespace here ?><?php foreach ($jobs_for_filter as $job) : ?><option value="<?php echo esc_attr($job['id']); ?>" data-postcode="<?php echo esc_attr($job['postcode']); ?>"><?php echo esc_html($job['title']); ?></option><?php endforeach; ?><?php // No whitespace here ?></select>
                            </div>
                        </div>

                        <div class="pi-cal-field">
                            <label for="pi-cal-event-title"><?php esc_html_e('Title', 'planningindex'); ?> <span class="pi-cal-required">*</span></label>
                            <input type="text" id="pi-cal-event-title" class="pi-cal-input" placeholder="e.g. Roof install, Site survey" required />
                            <div class="pi-cal-field-error" id="pi-cal-error-title"></div>
                        </div>

                        <div class="pi-cal-field-grid">
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-start"><?php esc_html_e('Start', 'planningindex'); ?> <span class="pi-cal-required">*</span></label>
                                <input type="datetime-local" id="pi-cal-event-start" class="pi-cal-input" required />
                                <div class="pi-cal-field-error" id="pi-cal-error-start"></div>
                            </div>
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-end"><?php esc_html_e('End', 'planningindex'); ?></label>
                                <input type="datetime-local" id="pi-cal-event-end" class="pi-cal-input" />
                                <div class="pi-cal-field-error" id="pi-cal-error-end"></div>
                            </div>
                        </div>

                        <div class="pi-cal-field pi-cal-field--inline">
                            <label class="pi-cal-toggle">
                                <input type="checkbox" id="pi-cal-event-all-day" />
                                <span class="pi-cal-toggle-slider"></span>
                                <span class="pi-cal-toggle-label"><?php esc_html_e('All day event', 'planningindex'); ?></span>
                            </label>
                        </div>

                        <div class="pi-cal-field-grid">
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-status"><?php esc_html_e('Status', 'planningindex'); ?></label>
                                <select id="pi-cal-event-status" class="pi-cal-input"><?php // No whitespace here ?><option value="scheduled"><?php esc_html_e('Scheduled', 'planningindex'); ?></option><?php // No whitespace here ?><option value="in_progress"><?php esc_html_e('In Progress', 'planningindex'); ?></option><?php // No whitespace here ?><option value="completed"><?php esc_html_e('Completed', 'planningindex'); ?></option><?php // No whitespace here ?><option value="cancelled"><?php esc_html_e('Cancelled', 'planningindex'); ?></option><?php // No whitespace here ?></select>
                            </div>
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-priority"><?php esc_html_e('Priority', 'planningindex'); ?></label>
                                <select id="pi-cal-event-priority" class="pi-cal-input"><?php // No whitespace here ?><option value="low"><?php esc_html_e('Low', 'planningindex'); ?></option><?php // No whitespace here ?><option value="medium" selected><?php esc_html_e('Medium', 'planningindex'); ?></option><?php // No whitespace here ?><option value="high"><?php esc_html_e('High', 'planningindex'); ?></option><?php // No whitespace here ?><option value="critical"><?php esc_html_e('Critical', 'planningindex'); ?></option><?php // No whitespace here ?></select>
                            </div>
                        </div>

                        <div class="pi-cal-field-grid">
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-trade"><?php esc_html_e('Trade / Subcontractor', 'planningindex'); ?></label>
                                <select id="pi-cal-event-trade" class="pi-cal-input"><?php // No whitespace here ?><?php foreach ($trades as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?><?php // No whitespace here ?></select>
                            </div>
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-supplier"><?php esc_html_e('Supplier (for deliveries)', 'planningindex'); ?></label>
                                <input type="text" id="pi-cal-event-supplier" class="pi-cal-input" placeholder="e.g. Travis Perkins, Screwfix" />
                            </div>
                        </div>

                        <div class="pi-cal-field-grid">
                            <div class="pi-cal-field">
                                <label for="pi-cal-event-po"><?php esc_html_e('PO Number', 'planningindex'); ?></label>
                                <input type="text" id="pi-cal-event-po" class="pi-cal-input" placeholder="e.g. PO-2024-001" />
                            </div>
                            <div class="pi-cal-field pi-cal-field--inline">
                                <label class="pi-cal-toggle">
                                    <input type="checkbox" id="pi-cal-event-weather-sensitive" />
                                    <span class="pi-cal-toggle-slider"></span>
                                    <span class="pi-cal-toggle-label"><?php esc_html_e('Weather Sensitive', 'planningindex'); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="pi-cal-field" id="pi-cal-weather-forecast" style="display: none;">
                            <label><?php esc_html_e('Weather Forecast', 'planningindex'); ?></label>
                            <div class="pi-cal-weather-preview" id="pi-cal-weather-preview"></div>
                        </div>
                    </div>

                    <!-- Tab: Crew & Resources -->
                    <div class="pi-cal-tab-content" data-tab="crew">
                        <div class="pi-cal-field">
                            <label for="pi-cal-event-crew"><?php esc_html_e('Assign Crew', 'planningindex'); ?></label>
                            <select id="pi-cal-event-crew" class="pi-cal-input pi-cal-input--multi" multiple size="6"><?php // No whitespace here ?><?php foreach ($workers as $w) : ?><option value="<?php echo esc_attr($w->ID); ?>"><?php echo esc_html($w->display_name); ?></option><?php endforeach; ?><?php // No whitespace here ?></select>
                            <p class="pi-cal-field-help"><?php esc_html_e('Hold Ctrl/Cmd to select multiple crew members', 'planningindex'); ?></p>
                        </div>

                        <div class="pi-cal-conflict-warning" id="pi-cal-conflict-warning" style="display: none;">
                            <div class="pi-cal-conflict-header">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg><?php esc_html_e('Crew Scheduling Conflicts Detected', 'planningindex'); ?>
                            </div>
                            <div class="pi-cal-conflict-list" id="pi-cal-conflict-list"></div>
                        </div>

                        <div class="pi-cal-field">
                            <label><?php esc_html_e('Crew Availability Heatmap', 'planningindex'); ?></label>
                            <div class="pi-cal-availability-heatmap" id="pi-cal-availability-heatmap">
                                <div class="pi-cal-empty-state--small">
                                    <p><?php esc_html_e('Select crew members and dates to view availability', 'planningindex'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="pi-cal-field pi-cal-field--inline">
                            <label class="pi-cal-toggle">
                                <input type="checkbox" id="pi-cal-event-ignore-conflicts" />
                                <span class="pi-cal-toggle-slider"></span>
                                <span class="pi-cal-toggle-label"><?php esc_html_e('Ignore scheduling conflicts', 'planningindex'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Tab: Checklist -->
                    <div class="pi-cal-tab-content" data-tab="checklist">
                        <div class="pi-cal-field">
                            <label><?php esc_html_e('Task Checklist', 'planningindex'); ?></label>
                            <div class="pi-cal-checklist" id="pi-cal-checklist-container">
                                <div class="pi-cal-checklist-empty">
                                    <p><?php esc_html_e('No checklist items yet. Add tasks below.', 'planningindex'); ?></p>
                                </div>
                            </div>
                            <div class="pi-cal-checklist-add">
                                <input type="text" id="pi-cal-checklist-input" class="pi-cal-input" placeholder="Add a task..." />
                                <button type="button" class="pi-cal-btn pi-cal-btn--secondary" id="pi-cal-checklist-add-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg><?php esc_html_e('Add', 'planningindex'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="pi-cal-field pi-cal-field--inline">
                            <label class="pi-cal-toggle">
                                <input type="checkbox" id="pi-cal-event-mileage" />
                                <span class="pi-cal-toggle-slider"></span>
                                <span class="pi-cal-toggle-label"><?php esc_html_e('Log mileage for this site visit', 'planningindex'); ?></span>
                            </label>
                        </div>

                        <div class="pi-cal-mileage-form" id="pi-cal-mileage-form" style="display: none;">
                            <div class="pi-cal-field-grid">
                                <div class="pi-cal-field">
                                    <label><?php esc_html_e('From Address', 'planningindex'); ?></label>
                                    <input type="text" id="pi-cal-mileage-from" class="pi-cal-input" placeholder="Your office/base" />
                                </div>
                                <div class="pi-cal-field">
                                    <label><?php esc_html_e('To Address', 'planningindex'); ?></label>
                                    <input type="text" id="pi-cal-mileage-to" class="pi-cal-input" placeholder="Job site postcode" readonly />
                                </div>
                            </div>
                            <div class="pi-cal-mileage-estimate" id="pi-cal-mileage-estimate"></div>
                        </div>
                    </div>

                    <!-- Tab: Attachments -->
                    <div class="pi-cal-tab-content" data-tab="attachments">
                        <div class="pi-cal-field">
                            <label><?php esc_html_e('Photos & Documents', 'planningindex'); ?></label>
                            <div class="pi-cal-attachments-grid" id="pi-cal-attachments-grid">
                                <div class="pi-cal-attachment-upload" id="pi-cal-attachment-upload">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32">
                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                    </svg><?php esc_html_e('Click or drag files to upload', 'planningindex'); ?>
                                    <small><?php esc_html_e('Supports: JPG, PNG, PDF (max 10MB)', 'planningindex'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Linked Expenses -->
                    <div class="pi-cal-tab-content" data-tab="expenses">
                        <div class="pi-cal-linked-expenses" id="pi-cal-linked-expenses">
                            <div class="pi-cal-empty-state--small">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32">
                                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                <p><?php esc_html_e('Assign a job and save the event to see linked expenses', 'planningindex'); ?></p>
                            </div>
                        </div>
                        <div class="pi-cal-expense-actions">
                            <button type="button" class="pi-cal-btn pi-cal-btn--secondary" id="pi-cal-create-expense-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg><?php esc_html_e('Create Linked Expense', 'planningindex'); ?>
                            </button>
                            <button type="button" class="pi-cal-btn pi-cal-btn--secondary" id="pi-cal-log-mileage-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="12" r="3"/>
                                </svg><?php esc_html_e('Log Mileage', 'planningindex'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Tab: Notes -->
                    <div class="pi-cal-tab-content" data-tab="notes">
                        <div class="pi-cal-field">
                            <label for="pi-cal-event-notes"><?php esc_html_e('Notes & Instructions', 'planningindex'); ?></label>
                            <textarea id="pi-cal-event-notes" class="pi-cal-textarea" rows="8" placeholder="Access instructions, delivery reference, materials needed, site contacts, etc."></textarea>
                        </div>

                        <div class="pi-cal-field pi-cal-field--inline">
                            <label class="pi-cal-toggle">
                                <input type="checkbox" id="pi-cal-save-template" />
                                <span class="pi-cal-toggle-slider"></span>
                                <span class="pi-cal-toggle-label"><?php esc_html_e('Save as template for reuse', 'planningindex'); ?></span>
                            </label>
                        </div>
                        <div class="pi-cal-field" id="pi-cal-template-name-field" style="display: none;">
                            <input type="text" id="pi-cal-template-name" class="pi-cal-input" placeholder="Template name" />
                        </div>
                    </div>
                </form>
            </div>

            <div class="pi-cal-modal-footer">
                <div class="pi-cal-modal-footer-left">
                    <button type="button" class="pi-cal-btn pi-cal-btn--danger" id="pi-cal-event-delete" style="display:none; background:#dc2626 !important; color:#fff !important; border-color:#dc2626 !important;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg><?php esc_html_e('Delete', 'planningindex'); ?>
                    </button>
                    <button type="button" class="pi-cal-btn pi-cal-btn--ghost" id="pi-cal-event-duplicate" style="display:none;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg><?php esc_html_e('Duplicate', 'planningindex'); ?>
                    </button>
                </div>
                <div class="pi-cal-modal-footer-right">
                    <button type="button" class="pi-cal-btn pi-cal-btn--ghost" id="pi-cal-modal-cancel"><?php esc_html_e('Cancel', 'planningindex'); ?></button>
                    <button type="submit" form="pi-cal-event-form" class="pi-cal-btn pi-cal-btn--primary" id="pi-cal-event-save" style="background:#156349 !important;color:#fff !important;border-color:#156349 !important;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg><?php esc_html_e('Save Event', 'planningindex'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates Modal -->
    <div class="pi-cal-modal-backdrop" id="pi-cal-templates-modal">
        <div class="pi-cal-modal">
            <div class="pi-cal-modal-header">
                <div class="pi-cal-modal-header-left">
                    <div class="pi-cal-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <h2><?php esc_html_e('Event Templates', 'planningindex'); ?></h2>
                </div>
                <button type="button" class="pi-cal-modal-close" id="pi-cal-templates-modal-close">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="pi-cal-modal-body">
                <div id="pi-cal-templates-list" class="pi-cal-templates-list"></div>
                <div class="pi-cal-empty-state" id="pi-cal-templates-empty" style="display: none;">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <p><?php esc_html_e('No templates yet', 'planningindex'); ?></p>
                    <span><?php esc_html_e('Create events and save them as templates for quick reuse', 'planningindex'); ?></span>
                </div>
            </div>
            <div class="pi-cal-modal-footer">
                <button type="button" class="pi-cal-btn pi-cal-btn--ghost" id="pi-cal-templates-modal-cancel"><?php esc_html_e('Close', 'planningindex'); ?></button>
            </div>
        </div>
    </div>

    <!-- Jump to Date Modal -->
    <div class="pi-cal-modal-backdrop" id="pi-cal-jump-modal">
        <div class="pi-cal-modal pi-cal-modal--small">
            <div class="pi-cal-modal-header">
                <div class="pi-cal-modal-header-left">
                    <div class="pi-cal-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <h2><?php esc_html_e('Jump to Date', 'planningindex'); ?></h2>
                </div>
                <button type="button" class="pi-cal-modal-close" id="pi-cal-jump-modal-close">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="pi-cal-modal-body">
                <div class="pi-cal-field">
                    <label for="pi-cal-jump-input"><?php esc_html_e('Select date', 'planningindex'); ?></label>
                    <input type="date" id="pi-cal-jump-input" class="pi-cal-input" />
                </div>
            </div>
            <div class="pi-cal-modal-footer">
                <button type="button" class="pi-cal-btn pi-cal-btn--ghost" id="pi-cal-jump-cancel"><?php esc_html_e('Cancel', 'planningindex'); ?></button>
                <button type="button" class="pi-cal-btn pi-cal-btn--primary" id="pi-cal-jump-go" style="background:#22c55e !important;color:#fff !important;border-color:#22c55e !important;"><?php esc_html_e('Go', 'planningindex'); ?></button>
            </div>
        </div>
    </div>

    <!-- Lookahead Modal -->
    <div class="pi-cal-modal-backdrop" id="pi-cal-lookahead-modal">
        <div class="pi-cal-modal pi-cal-modal--large">
            <div class="pi-cal-modal-header">
                <div class="pi-cal-modal-header-left">
                    <div class="pi-cal-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </div>
                    <h2><?php esc_html_e('3-Week Lookahead', 'planningindex'); ?></h2>
                </div>
                <button type="button" class="pi-cal-modal-close" id="pi-cal-lookahead-modal-close">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="pi-cal-modal-body">
                <div class="pi-cal-lookahead-content" id="pi-cal-lookahead-content">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="pi-cal-modal-footer">
                <button type="button" class="pi-cal-btn pi-cal-btn--secondary" id="pi-cal-lookahead-export">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg><?php esc_html_e('Export PDF', 'planningindex'); ?>
                </button>
                <button type="button" class="pi-cal-btn pi-cal-btn--ghost" id="pi-cal-lookahead-close"><?php esc_html_e('Close', 'planningindex'); ?></button>
            </div>
        </div>
    </div>

    <!-- Conflict Confirmation Modal -->
    <div class="pi-cal-modal-backdrop" id="pi-cal-conflict-modal">
        <div class="pi-cal-modal pi-cal-modal--small">
            <div class="pi-cal-modal-header">
                <div class="pi-cal-modal-header-left">
                    <div class="pi-cal-modal-icon pi-cal-modal-icon--warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <h2><?php esc_html_e('Scheduling Conflict', 'planningindex'); ?></h2>
                </div>
            </div>
            <div class="pi-cal-modal-body">
                <p class="pi-cal-conflict-desc"><?php esc_html_e('The selected crew members have scheduling conflicts:', 'planningindex'); ?></p>
                <div class="pi-cal-conflict-details" id="pi-cal-conflict-details"></div>
                <p class="pi-cal-conflict-question"><?php esc_html_e('Do you want to proceed anyway?', 'planningindex'); ?></p>
            </div>
            <div class="pi-cal-modal-footer">
                <button type="button" class="pi-cal-btn pi-cal-btn--ghost" id="pi-cal-conflict-cancel"><?php esc_html_e('Cancel', 'planningindex'); ?></button>
                <button type="button" class="pi-cal-btn pi-cal-btn--primary" id="pi-cal-conflict-proceed"><?php esc_html_e('Proceed Anyway', 'planningindex'); ?></button>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div class="pi-cal-context-menu" id="pi-cal-context-menu" style="display: none;">
        <button type="button" class="pi-cal-context-item" data-action="edit">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            <span>Edit Event</span>
        </button>
        <button type="button" class="pi-cal-context-item" data-action="duplicate">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
            Duplicate
        </button>
        <button type="button" class="pi-cal-context-item" data-action="mark-complete">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            Mark Complete
        </button>
        <div class="pi-cal-context-divider"></div>
        <button type="button" class="pi-cal-context-item" data-action="log-expense">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
            Log Expense
        </button>
        <button type="button" class="pi-cal-context-item" data-action="mark-delivered">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
            Mark Delivered
        </button>
        <button type="button" class="pi-cal-context-item" data-action="add-photo">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
            </svg>
            Add Photo
        </button>
        <div class="pi-cal-context-divider"></div>
        <button type="button" class="pi-cal-context-item pi-cal-context-item--danger" data-action="delete">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Delete Event
        </button>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('planning_workspace_calendar', 'pi_workspace_calendar_shortcode');

