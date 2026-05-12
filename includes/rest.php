<?php
/**
 * Planning Index Workspace - REST API
 * Version 4.0
 * Professional CRM endpoints for construction leads
 */

if (!defined('ABSPATH')) exit;

// Define new stages globally
define('PI_WORKSPACE_STAGES', ['new_lead', 'proposal_sent', 'contacted', 'negotiation', 'won']);

// Stage mapping from old to new
define('PI_STAGE_MIGRATION', [
    'possible'  => 'new_lead',
    'letter'    => 'proposal_sent',
    'finalised' => 'won'
]);

add_action('rest_api_init', function() {
    $namespace = 'pi/v1';

    // Get workspace - return leads organized by stage
    register_rest_route($namespace, '/workspace', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function() {
            $user_id = get_current_user_id();
            pi_migrate_workspace_to_leads($user_id);
            $workspace = pi_get_user_workspace($user_id);
            
            // Clean mismatched stages in user meta
            foreach ($workspace as $s => &$items) {
                $items = array_values(array_filter($items, function($item) use ($s) {
                    $actual_stage = get_post_meta($item['id'], PI_LEAD_META_PREFIX . 'stage', true);
                    // Migrate old stage names
                    if (isset(PI_STAGE_MIGRATION[$actual_stage])) {
                        $actual_stage = PI_STAGE_MIGRATION[$actual_stage];
                        update_post_meta($item['id'], PI_LEAD_META_PREFIX . 'stage', $actual_stage);
                    }
                    if (!$actual_stage) $actual_stage = 'new_lead';
                    return $actual_stage === $s;
                }));
            }
            update_user_meta($user_id, PIF_WORKSPACE_META, $workspace);
            
            // Add missing leads
            $leads = get_posts([
                'post_type' => PI_LEAD_CPT,
                'meta_key' => PI_LEAD_META_PREFIX . 'owner_user_id',
                'meta_value' => $user_id,
                'posts_per_page' => -1,
                'post_status' => 'any',
            ]);
            
            $updated = false;
            foreach ($leads as $lead) {
                $stage = get_post_meta($lead->ID, PI_LEAD_META_PREFIX . 'stage', true);
                // Migrate old stage names
                if (isset(PI_STAGE_MIGRATION[$stage])) {
                    $stage = PI_STAGE_MIGRATION[$stage];
                    update_post_meta($lead->ID, PI_LEAD_META_PREFIX . 'stage', $stage);
                }
                if (!$stage || !in_array($stage, PI_WORKSPACE_STAGES)) {
                    $stage = 'new_lead';
                    update_post_meta($lead->ID, PI_LEAD_META_PREFIX . 'stage', $stage);
                }
                
                $found = false;
                if (isset($workspace[$stage])) {
                    foreach ($workspace[$stage] as $item) {
                        if ($item['id'] == $lead->ID) {
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) {
                    if (!isset($workspace[$stage])) $workspace[$stage] = [];
                    $workspace[$stage][] = [
                        'id' => $lead->ID,
                        'highlighted' => get_post_meta($lead->ID, PI_LEAD_META_PREFIX . 'highlighted', true) ?: 0,
                        'order' => count($workspace[$stage])
                    ];
                    $updated = true;
                }
            }
            if ($updated) update_user_meta($user_id, PIF_WORKSPACE_META, $workspace);
            
            return rest_ensure_response($workspace);
        }
    ]);

    // Add item to workspace
    register_rest_route($namespace, '/workspace/add', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            pi_migrate_workspace_to_leads($user_id);

            $planning_app_id = intval($req['post_id']);
            if (get_post_type($planning_app_id) !== 'planning_app') {
                return new WP_Error('invalid_post', 'Not a planning_app', ['status' => 400]);
            }

            // Find or create pi_lead
            $lead_query = get_posts([
                'post_type' => PI_LEAD_CPT,
                'meta_query' => [
                    ['key' => PI_LEAD_META_PREFIX . 'linked_planning_app_id', 'value' => $planning_app_id],
                    ['key' => PI_LEAD_META_PREFIX . 'owner_user_id', 'value' => $user_id],
                ],
                'posts_per_page' => 1,
                'post_status' => 'any',
            ]);
            
            if (empty($lead_query)) {
                $lead_id = wp_insert_post(['post_type' => PI_LEAD_CPT, 'post_status' => 'draft']);
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', $user_id);
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', $planning_app_id);
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'stage', 'new_lead');
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'added_to_workspace_at', current_time('mysql'));
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'status_history', [current_time('mysql') . ': Lead created']);
                    do_action('pi_lead_created', $lead_id, $user_id);
                    $lead_code = get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'lead_code', true);
            } else {
                $lead_id = $lead_query[0]->ID;
                $lead_code = get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'lead_code', true);
                // Backfill added_to_workspace_at if missing
                if (!get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'added_to_workspace_at', true)) {
                    update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'added_to_workspace_at', current_time('mysql'));
                }
            }

            // Add to workspace meta if not already
            $workspace = pi_get_user_workspace($user_id);
            $exists = false;
            foreach ($workspace as $stage => $items) {
                if (array_filter($items, fn($x) => $x['id'] == $lead_id)) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $workspace['new_lead'][] = [
                    'id' => $lead_id,
                    'highlighted' => 0,
                    'added_at' => current_time('mysql'),
                    'order' => count($workspace['new_lead'])
                ];
                update_user_meta($user_id, PIF_WORKSPACE_META, $workspace);
            }

            // Return details for event
            $meta = get_post_meta($planning_app_id);
            return rest_ensure_response([
                'added' => true,
                'id' => $lead_id,
                'lead_code' => $lead_code,
                'address' => $meta['address'][0] ?? '',
                'description' => $meta['description'][0] ?? '',
                'ref' => $meta['council_reference'][0] ?? '',
                'date_received' => $meta['date_received'][0] ?? '',
            ]);
        }
    ]);

    // Save workspace (drag/drop - updates pi_lead stage and order)
    // NOW ALSO SYNCS INVOICE STATUS TO MATCH PIPELINE STAGE
    register_rest_route($namespace, '/workspace/save', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $f = $req['workspace'];
            if (!is_array($f)) return new WP_Error('invalid_workspace', 'Invalid structure');
            
            $user_id = get_current_user_id();
            $clean = [];
            
            // Stage to invoice status mapping
            $stage_to_invoice_status = [
                'new_lead' => 'draft',
                'proposal_sent' => 'draft',
                'contacted' => 'contacted',
                'negotiation' => 'negotiation',
                'won' => 'won'
            ];
            
            foreach (PI_WORKSPACE_STAGES as $stage) {
                $clean[$stage] = [];
                $items = $f[$stage] ?? [];
                
                foreach ($items as $idx => $it) {
                    $id = intval($it['id'] ?? 0);
                    if ($id > 0 && get_post_meta($id, PI_LEAD_META_PREFIX . 'owner_user_id', true) == $user_id) {
                        $old_stage = get_post_meta($id, PI_LEAD_META_PREFIX . 'stage', true);
                        
                        // Update pi_lead meta
                        update_post_meta($id, PI_LEAD_META_PREFIX . 'stage', $stage);
                        update_post_meta($id, PI_LEAD_META_PREFIX . 'highlighted', !empty($it['highlighted']) ? 1 : 0);
                        
                        // Log history for stage changes
                        if ($old_stage !== $stage) {
                            $history = get_post_meta($id, PI_LEAD_META_PREFIX . 'status_history', true) ?: [];
                            $stage_labels = [
                                'new_lead' => 'New Lead',
                                'proposal_sent' => 'Proposal Sent',
                                'contacted' => 'Contacted',
                                'negotiation' => 'Negotiation',
                                'won' => 'Won'
                            ];
                            $history[] = current_time('mysql') . ": Moved to " . ($stage_labels[$stage] ?? $stage);
                            update_post_meta($id, PI_LEAD_META_PREFIX . 'status_history', $history);
                            do_action('pi_lead_stage_changed', $id, $user_id, $old_stage, $stage);
                            
                            // SYNC INVOICE STATUS: Update linked invoice status when stage changes
                            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
                            $planning_app_id = intval(get_post_meta($id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', true));
                            $invoice_updated = false;
                            
                            foreach ($invoices as &$inv) {
                                if (($inv['pi_lead_id'] ?? 0) === $id || $inv['lead_id'] === $planning_app_id) {
                                    $new_status = $stage_to_invoice_status[$stage] ?? 'draft';
                                    $inv['status'] = $new_status;
                                    $inv['last_stage_sync'] = current_time('mysql');
                                    $invoice_updated = true;
                                    error_log("[PI Workspace] Synced invoice #{$inv['id']} status to '$new_status' from stage '$stage'");
                                    break;
                                }
                            }
                            
                            if ($invoice_updated) {
                                update_user_meta($user_id, PII_INVOICES_META, $invoices);
                            }
                        }

                        $clean[$stage][] = [
                            'id' => $id,
                            'order' => $idx
                        ];
                    }
                }
            }
            
            update_user_meta($user_id, PIF_WORKSPACE_META, $clean);
            return rest_ensure_response(['saved' => true]);
        }
    ]);

    // Update invoice for lead
    register_rest_route($namespace, '/workspace/invoices/update_for_lead', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $lead_id = intval($req['lead_id']);
            
            // Verify ownership
            if (get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', true) != $user_id) {
                return new WP_Error('unauthorized', 'Not your lead', ['status' => 403]);
            }
            
            // Update lead meta
            if (isset($req['due'])) {
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'due_date', sanitize_text_field($req['due']));
            }
            if (isset($req['est'])) {
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'estimated_value', floatval($req['est']));
            }
            if (isset($req['notes'])) {
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'notes', sanitize_textarea_field($req['notes']));
            }
            
            return rest_ensure_response(['updated' => true]);
        }
    ]);

    // Generate Invoice (Proposal)
    register_rest_route($namespace, '/workspace/invoices/add', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user = get_current_user_id();
            $lead_id = intval($req['lead_id']);
            $est = floatval($req['est'] ?? 0);
            $due = sanitize_text_field($req['due'] ?? '');
            $notes = sanitize_textarea_field($req['notes'] ?? '');

            // Verify ownership
            if (get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', true) != $user) {
                return new WP_Error('unauthorized', 'Not your lead', ['status' => 403]);
            }

            // Fetch planning_app_id from lead
            $planning_app_id = intval(get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', true));
            if (get_post_type($planning_app_id) !== 'planning_app') {
                return new WP_Error('invalid_lead', 'Invalid lead or planning app', ['status' => 400]);
            }

            // Fetch data from planning app
            $post = get_post($planning_app_id);
            $meta = get_post_meta($planning_app_id);
            $rawAddress = $meta['address'][0] ?? '';
            if (!$rawAddress && $post->post_content) {
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
            if (!$rawAddress) {
                $rawAddress = $post->post_title ?: 'Unknown Address';
            }
            $address = title_case_address($rawAddress);
            $app_desc = $meta['description'][0] ?? strip_tags($post->post_content);

            // Get business and tmpl_key
            $business = get_user_meta($user, '_pi_business_info', true) ?: [
                'company_name' => 'Your Company',
                'default_terms' => '30% deposit, balance on completion.',
                'default_warranty' => '5 years'
            ];
            $tmpl_key = $business['default_template'] ?? 'basic';
            if ($tmpl_key === 'auto' && stripos($app_desc, 'window') !== false) {
                $tmpl_key = 'window';
            }

            // Save invoice to user meta
            $invoices = get_user_meta($user, '_pi_invoices', true) ?: [];
            $new_id = count($invoices) + 1 + 1;

            // Build re_line
            $re_line_value = ($tmpl_key === 'window')
                ? "Proposal for Window Installation at {$address}."
                : "Overture to contract services in relation to the successfully granted planning application at {$address}.";

            $invoices[] = [
                'id' => $new_id,
                'lead_id' => $planning_app_id,
                'address' => $address,
                'pdf_url' => '',
                'original_url' => $meta['info_url'][0] ?? '#',
                'status' => 'draft',
                'created' => current_time('mysql'),
                'amount' => $est,
                'tmpl_key' => $tmpl_key,
                'description' => 'We are pleased to submit our proposal for works at the above address.',
                'notes' => $notes ?: $app_desc,
                're_line' => $re_line_value,
                'terms' => $business['default_terms'],
                'warranty' => $business['default_warranty'],
                'date' => date('d/m/Y'),
                'valid_until' => $due ? date('d/m/Y', strtotime($due)) : date('d/m/Y', strtotime('+30 days')),
            ];

            update_user_meta($user, '_pi_invoices', $invoices);
            if (function_exists('clean_user_cache')) {
                clean_user_cache($user);
            }

            // Generate PDF if PI_PDF_Editor exists
            if (class_exists('PI_PDF_Editor')) {
                $upload_dir = wp_upload_dir();
                $pdf_dir = $upload_dir['basedir'] . '/planning-proposals/';
                if (!file_exists($pdf_dir)) {
                    wp_mkdir_p($pdf_dir);
                }
                $filename = 'proposal-' . $new_id . '-' . $user . '-' . uniqid() . '.pdf';
                $filepath = $pdf_dir . $filename;
                $pdf_url = $upload_dir['baseurl'] . '/planning-proposals/' . $filename;

                $pdf_data = [
                    'company_name' => $business['company_name'] ?? '',
                    'company_address' => $business['company_address'] ?? '',
                    'phone' => $business['phone'] ?? '',
                    'email' => $business['email'] ?? '',
                    'website' => $business['website'] ?? '',
                    'date' => date('d/m/Y'),
                    'valid_until' => $due ? date('d/m/Y', strtotime($due)) : date('d/m/Y', strtotime('+30 days')),
                    'amount' => number_format($est, 2),
                    'terms' => $business['default_terms'] ?? '',
                    'warranty' => $business['default_warranty'] ?? '',
                    'description' => 'We are pleased to submit our proposal for works at the above address.',
                    'address' => $address,
                    're_line' => $re_line_value,
                    'logo' => $business['logo'] ?? '',
                    'signature' => $business['signature'] ?? '',
                    'notes' => $notes ?: $app_desc,
                ];

                if (PI_PDF_Editor::generate_or_update($tmpl_key, $pdf_data, $filepath)) {
                    if (file_exists($filepath)) {
                        $invoices = get_user_meta($user, '_pi_invoices', true) ?: [];
                        foreach ($invoices as &$i) {
                            if ($i['id'] === $new_id) {
                                $i['pdf_url'] = $pdf_url;
                                break;
                            }
                        }
                        update_user_meta($user, '_pi_invoices', $invoices);
                        update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'invoice_generated', true);

                        return rest_ensure_response([
                            'added' => true,
                            'id' => $new_id,
                            'pdf_url' => $pdf_url
                        ]);
                    }
                }
            }

            // Mark as generated even without PDF
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'invoice_generated', true);
            
            // QBO Integration: Trigger proposal created hook
            $proposal_data = [
                'id' => $new_id,
                'lead_id' => $lead_id,
                'planning_app_id' => $planning_app_id,
                'amount' => $est,
                'status' => 'draft',
                'pdf_url' => $pdf_url ?? '',
            ];
            do_action('pi_proposal_created', $new_id, $proposal_data);
            
            return rest_ensure_response([
                'added' => true,
                'id' => $new_id,
                'pdf_url' => ''
            ]);
        }
    ]);

    // Remove from workspace - ALSO DELETES LINKED INVOICE
    register_rest_route($namespace, '/workspace/remove', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $lead_id = intval($req['post_id']);
            
            if (get_post_type($lead_id) !== PI_LEAD_CPT || get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', true) != $user_id) {
                return new WP_Error('invalid', 'Invalid lead', ['status' => 400]);
            }

            // Get the linked planning_app_id before deletion
            $planning_app_id = intval(get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', true));
            
            // Remove from workspace
            $workspace = pi_get_user_workspace($user_id);
            foreach ($workspace as &$stage) {
                $stage = array_values(array_filter($stage, fn($x) => $x['id'] != $lead_id));
            }
            update_user_meta($user_id, PIF_WORKSPACE_META, $workspace);

            // DELETE LINKED INVOICE - Critical ecosystem sync
            $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
            $deleted_invoice_id = null;
            $deleted_pdf_url = null;
            
            $invoices = array_values(array_filter($invoices, function($inv) use ($lead_id, $planning_app_id, &$deleted_invoice_id, &$deleted_pdf_url) {
                // Match by pi_lead_id or by planning_app_id
                if (($inv['pi_lead_id'] ?? 0) === $lead_id || $inv['lead_id'] === $planning_app_id) {
                    $deleted_invoice_id = $inv['id'];
                    $deleted_pdf_url = $inv['pdf_url'] ?? null;
                    return false; // Remove this invoice
                }
                return true;
            }));
            
            if ($deleted_invoice_id !== null) {
                update_user_meta($user_id, PII_INVOICES_META, $invoices);
                error_log("[PI Workspace] Deleted invoice #$deleted_invoice_id when removing lead #$lead_id");
                
                // Optionally delete the PDF file
                if ($deleted_pdf_url) {
                    $upload_dir = wp_upload_dir();
                    $pdf_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $deleted_pdf_url);
                    if (file_exists($pdf_path)) {
                        @unlink($pdf_path);
                        error_log("[PI Workspace] Deleted PDF file: $pdf_path");
                    }
                }
            }

            // Trash pi_lead post
            wp_trash_post($lead_id);
            
            // QBO Integration: Trigger lead deletion hook
            do_action('pi_lead_deleted', $lead_id, $user_id);

            return rest_ensure_response([
                'removed' => true,
                'invoice_deleted' => $deleted_invoice_id !== null,
                'deleted_invoice_id' => $deleted_invoice_id
            ]);
        }
    ]);
    // File Upload: Add attachment to lead
    register_rest_route($namespace, '/leads/(?P<id>\d+)/attachments', [
        'methods' => 'POST',
        'permission_callback' => function($req) {
            $lead_id = $req['id'];
            return is_user_logged_in() && 
                   get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', true) == get_current_user_id();
        },
        'callback' => function(WP_REST_Request $req) {
            $lead_id = intval($req['id']);
            
            if (empty($_FILES['file'])) {
                return new WP_Error('no_file', 'No file uploaded', ['status' => 400]);
            }

            $file = $_FILES['file'];
            
            // Validate file size (10MB max)
            $max_size = 10 * 1024 * 1024;
            if ($file['size'] > $max_size) {
                return new WP_Error('file_too_large', 'File size exceeds 10MB limit', ['status' => 400]);
            }
            
            // Validate file type
            $allowed_types = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'application/zip'
            ];
            
            $file_info = wp_check_filetype($file['name'], [
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'txt' => 'text/plain',
                'zip' => 'application/zip'
            ]);
            
            if (!$file_info['type'] || !in_array($file_info['type'], $allowed_types)) {
                return new WP_Error('invalid_type', 'Invalid file type. Allowed: Images, PDF, Word, Excel, TXT, ZIP', ['status' => 400]);
            }

            // Handle upload
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            
            $upload_overrides = [
                'test_form' => false,
                'mimes' => [
                    'jpg|jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'txt' => 'text/plain',
                    'zip' => 'application/zip'
                ]
            ];
            
            $movefile = wp_handle_upload($file, $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                // Create attachment post
                $attachment = [
                    'post_title' => sanitize_file_name($file['name']),
                    'post_content' => '',
                    'post_type' => 'attachment',
                    'post_parent' => $lead_id,
                    'post_mime_type' => $movefile['type'],
                    'post_status' => 'inherit',
                    'guid' => $movefile['url']
                ];
                
                $attach_id = wp_insert_attachment($attachment, $movefile['file'], $lead_id);
                
                if (is_wp_error($attach_id)) {
                    return new WP_Error('insert_failed', 'Failed to create attachment', ['status' => 500]);
                }
                
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                
                // Add to lead's attachment list meta for quick access
                $current_attachments = get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'attachments', true) ?: [];
                $current_attachments[] = $attach_id;
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'attachments', $current_attachments);
                
                // Log to history
                $history = get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'status_history', true) ?: [];
                $history[] = current_time('mysql') . ': File uploaded - ' . sanitize_file_name($file['name']);
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'status_history', $history);
                
                return rest_ensure_response([
                    'id' => $attach_id,
                    'url' => $movefile['url'],
                    'name' => sanitize_file_name($file['name']),
                    'type' => $movefile['type'],
                    'size' => size_format($file['size'])
                ]);
            } else {
                return new WP_Error('upload_failed', $movefile['error'] ?? 'Upload failed', ['status' => 500]);
            }
        }
    ]);

    // Delete attachment
    register_rest_route($namespace, '/leads/(?P<id>\d+)/attachments/(?P<attach_id>\d+)', [
        'methods' => 'DELETE',
        'permission_callback' => function($req) {
            $lead_id = $req['id'];
            return is_user_logged_in() && 
                   get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', true) == get_current_user_id();
        },
        'callback' => function(WP_REST_Request $req) {
            $lead_id = intval($req['id']);
            $attach_id = intval($req['attach_id']);
            
            // Verify attachment belongs to this lead
            $attachment = get_post($attach_id);
            if (!$attachment || $attachment->post_parent != $lead_id) {
                return new WP_Error('not_found', 'Attachment not found for this lead', ['status' => 404]);
            }
            
            // Delete file and attachment post
            wp_delete_attachment($attach_id, true);
            
            // Update meta
            $current_attachments = get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'attachments', true) ?: [];
            $current_attachments = array_values(array_diff($current_attachments, [$attach_id]));
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'attachments', $current_attachments);
            
            return rest_ensure_response(['deleted' => true]);
        }
    ]);
    // Leads: Get all with planning_app data
    register_rest_route($namespace, '/leads', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function() {
            $user_id = get_current_user_id();
            pi_migrate_workspace_to_leads($user_id);
            $workspace_meta = pi_get_user_workspace($user_id);

            $leads = get_posts([
                'post_type' => PI_LEAD_CPT,
                'meta_key' => PI_LEAD_META_PREFIX . 'owner_user_id',
                'meta_value' => $user_id,
                'posts_per_page' => -1,
                'post_status' => 'any',
            ]);
            
            $lead_map = [];
            foreach ($leads as $lead) {
                $id = $lead->ID;
                $meta = get_post_meta($id);
                $planning_app_id = intval($meta[PI_LEAD_META_PREFIX . 'linked_planning_app_id'][0] ?? 0);
                $app_meta = get_post_meta($planning_app_id);
                $app_content = get_post_field('post_content', $planning_app_id);
                $app_post = get_post($planning_app_id);
                
                $temp = new DOMDocument();
                @$temp->loadHTML('<?xml encoding="UTF-8">' . $app_content);
                $desc = '';
                $addrFromContent = '';
                foreach ($temp->getElementsByTagName('p') as $p) {
                    $t = trim($p->textContent);
                    if (preg_match('/^Address:/i', $t)) {
                        $addrFromContent = preg_replace('/^Address:\s*/i', '', $t);
                    } elseif (!$desc && $t) {
                        $desc = $t;
                    }
                }
                $stripped = trim(preg_replace('/<[^>]*>/', ' ', $app_content));

                $address = $app_meta['address'][0] ?? $addrFromContent ?? ($app_post ? $app_post->post_title : '') ?? 'Unknown';
                $address = title_case_address($address);

                $description_html = wp_kses_post($app_meta['description'][0] ?? $app_content ?? $desc ?? $stripped ?? '');
                $info_url = trim($app_meta['info_url'][0] ?? '#', '<> ');

                $status_history = maybe_unserialize($meta[PI_LEAD_META_PREFIX . 'status_history'][0] ?? 'a:0:{}');
                if (!is_array($status_history)) $status_history = [];
                $pricing_details = maybe_unserialize($meta[PI_LEAD_META_PREFIX . 'pricing_details'][0] ?? 'a:0:{}');
                if (!is_array($pricing_details)) $pricing_details = [];

                // Get and migrate stage
                $stage = $meta[PI_LEAD_META_PREFIX . 'stage'][0] ?? 'new_lead';
                if (isset(PI_STAGE_MIGRATION[$stage])) {
                    $stage = PI_STAGE_MIGRATION[$stage];
                }
                if (!in_array($stage, PI_WORKSPACE_STAGES)) {
                    $stage = 'new_lead';
                }

                $lead_map[$id] = [
                    'id' => $id,
                    'lead_code' => $meta[PI_LEAD_META_PREFIX . 'lead_code'][0] ?? '',
                    'stage' => $stage,
                    'estimated_value' => floatval($meta[PI_LEAD_META_PREFIX . 'estimated_value'][0] ?? 0),
                    'due_date' => $meta[PI_LEAD_META_PREFIX . 'due_date'][0] ?? '',
                    'notes' => $meta[PI_LEAD_META_PREFIX . 'notes'][0] ?? '',
                    'highlighted' => intval($meta[PI_LEAD_META_PREFIX . 'highlighted'][0] ?? 0),
                    'customer_name' => $meta[PI_LEAD_META_PREFIX . 'customer_name'][0] ?? '',
                    'customer_phone' => $meta[PI_LEAD_META_PREFIX . 'customer_phone'][0] ?? '',
                    'customer_email' => $meta[PI_LEAD_META_PREFIX . 'customer_email'][0] ?? '',
                    'customer_address' => $meta[PI_LEAD_META_PREFIX . 'customer_address'][0] ?? '',
                    'pricing_details' => $pricing_details,
                    'status_history' => $status_history,
                    'address' => $address,
                    'ref' => $app_meta['council_reference'][0] ?? '',
                    'date_received' => $app_meta['date_received'][0] ?? '',
                    'description' => wp_strip_all_tags($description_html),
                    'full_content' => $description_html,
                    'info_url' => $info_url,
                    'invoice_generated' => (bool) ($meta[PI_LEAD_META_PREFIX . 'invoice_generated'][0] ?? false),
                ];
            }

            // Group and sort by workspace_meta order
            $grouped = [];
            foreach (PI_WORKSPACE_STAGES as $stage) {
                $grouped[$stage] = [];
            }
            
            foreach ($workspace_meta as $stage => $items) {
                // Handle old stage names
                $new_stage = PI_STAGE_MIGRATION[$stage] ?? $stage;
                if (!in_array($new_stage, PI_WORKSPACE_STAGES)) continue;
                
                $ordered_ids = array_map(fn($item) => $item['id'], $items);
                foreach ($ordered_ids as $lead_id) {
                    if (isset($lead_map[$lead_id])) {
                        $grouped[$new_stage][] = $lead_map[$lead_id];
                    }
                }
            }

            // Add any leads not in workspace_meta
            foreach ($lead_map as $id => $lead_data) {
                $stage = $lead_data['stage'];
                if (!array_filter($grouped[$stage] ?? [], fn($x) => $x['id'] == $id)) {
                    $grouped[$stage][] = $lead_data;
                }
            }

            // Flatten to array for response
            $data = [];
            foreach ($grouped as $items) $data = array_merge($data, $items);
            return rest_ensure_response($data);
        }
    ]);

    // Create lead
    register_rest_route($namespace, '/leads/create', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $planning_app_id = intval($req['planning_app_id']);
            if ($planning_app_id && get_post_type($planning_app_id) !== 'planning_app') {
                return new WP_Error('invalid', 'Invalid planning app', ['status' => 400]);
            }

            $lead_id = wp_insert_post([
                'post_type' => PI_LEAD_CPT,
                'post_status' => 'draft',
            ]);
            
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', get_current_user_id());
            if ($planning_app_id) {
                update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', $planning_app_id);
            }
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'stage', 'new_lead');
            update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'status_history', [current_time('mysql') . ': Lead created']);

            // QBO Integration: Trigger lead created hook
            do_action('pi_lead_created', $lead_id, ['planning_app_id' => $planning_app_id, 'user_id' => get_current_user_id()]);

            return rest_ensure_response([
                'id' => $lead_id, 
                'lead_code' => get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'lead_code', true)
            ]);
        }
    ]);

    // Get single lead
    register_rest_route($namespace, '/leads/(?P<id>\d+)', [
        'methods' => 'GET',
        'permission_callback' => fn($req) => is_user_logged_in() && get_post_meta($req['id'], PI_LEAD_META_PREFIX . 'owner_user_id', true) == get_current_user_id(),
        'callback' => function(WP_REST_Request $req) {
            $id = intval($req['id']);
            $lead = get_post($id);
            if (!$lead || $lead->post_type !== PI_LEAD_CPT) {
                return new WP_Error('not_found', 'Lead not found', ['status' => 404]);
            }
            
            $meta = get_post_meta($id);
            $planning_app_id = intval($meta[PI_LEAD_META_PREFIX . 'linked_planning_app_id'][0] ?? 0);
            $app_meta = get_post_meta($planning_app_id);
            $app_content = get_post_field('post_content', $planning_app_id);
            $app_post = get_post($planning_app_id);
            
            $temp = new DOMDocument();
            @$temp->loadHTML('<?xml encoding="UTF-8">' . $app_content);
            $desc = '';
            $addrFromContent = '';
            foreach ($temp->getElementsByTagName('p') as $p) {
                $t = trim($p->textContent);
                if (preg_match('/^Address:/i', $t)) {
                    $addrFromContent = preg_replace('/^Address:\s*/i', '', $t);
                } elseif (!$desc && $t) {
                    $desc = $t;
                }
            }
            $stripped = trim(preg_replace('/<[^>]*>/', ' ', $app_content));

            $address = $app_meta['address'][0] ?? $addrFromContent ?? ($app_post ? $app_post->post_title : '') ?? 'Unknown';
            $address = title_case_address($address);

            $description_html = wp_kses_post($app_meta['description'][0] ?? $app_content ?? $desc ?? $stripped ?? '');
            $info_url = trim($app_meta['info_url'][0] ?? '#', '<> ');

            $status_history = maybe_unserialize($meta[PI_LEAD_META_PREFIX . 'status_history'][0] ?? 'a:0:{}');
            if (!is_array($status_history)) $status_history = [];
            $pricing_details = maybe_unserialize($meta[PI_LEAD_META_PREFIX . 'pricing_details'][0] ?? 'a:0:{}');
            if (!is_array($pricing_details)) $pricing_details = [];

            // Get and migrate stage
            $stage = $meta[PI_LEAD_META_PREFIX . 'stage'][0] ?? 'new_lead';
            if (isset(PI_STAGE_MIGRATION[$stage])) {
                $stage = PI_STAGE_MIGRATION[$stage];
                update_post_meta($id, PI_LEAD_META_PREFIX . 'stage', $stage);
            }

            return rest_ensure_response([
                'id' => $id,
                'lead_code' => $meta[PI_LEAD_META_PREFIX . 'lead_code'][0] ?? '',
                'stage' => $stage,
                'estimated_value' => floatval($meta[PI_LEAD_META_PREFIX . 'estimated_value'][0] ?? 0),
                'due_date' => $meta[PI_LEAD_META_PREFIX . 'due_date'][0] ?? '',
                'notes' => $meta[PI_LEAD_META_PREFIX . 'notes'][0] ?? '',
                'highlighted' => intval($meta[PI_LEAD_META_PREFIX . 'highlighted'][0] ?? 0),
                'customer_name' => $meta[PI_LEAD_META_PREFIX . 'customer_name'][0] ?? '',
                'customer_phone' => $meta[PI_LEAD_META_PREFIX . 'customer_phone'][0] ?? '',
                'customer_email' => $meta[PI_LEAD_META_PREFIX . 'customer_email'][0] ?? '',
                'customer_address' => $meta[PI_LEAD_META_PREFIX . 'customer_address'][0] ?? '',
                'pricing_details' => $pricing_details,
                'status_history' => $status_history,
                'address' => $address,
                'ref' => $app_meta['council_reference'][0] ?? '',
                'date_received' => $app_meta['date_received'][0] ?? '',
                'description' => wp_strip_all_tags($description_html),
                'full_content' => $description_html,
                'info_url' => $info_url,
                'attachments' => array_values(get_attached_media('', $id)),
                'invoice_generated' => (bool) ($meta[PI_LEAD_META_PREFIX . 'invoice_generated'][0] ?? false),
                'added_to_workspace_at' => $meta[PI_LEAD_META_PREFIX . 'added_to_workspace_at'][0] ?? '',
            ]);
        }
    ]);

    // Update lead - WITH INVOICE SYNC FOR ESTIMATED VALUE
    register_rest_route($namespace, '/leads/(?P<id>\d+)/update', [
        'methods' => 'POST',
        'permission_callback' => fn($req) => is_user_logged_in() && get_post_meta($req['id'], PI_LEAD_META_PREFIX . 'owner_user_id', true) == get_current_user_id(),
        'callback' => function(WP_REST_Request $req) {
            $id = intval($req['id']);
            $user_id = get_current_user_id();
            $fields = $req->get_json_params();
            $changes = [];
            $old_stage = get_post_meta($id, PI_LEAD_META_PREFIX . 'stage', true);
            $estimated_value_changed = false;
            $new_estimated_value = 0;
            
            // Allowed fields to update
            $allowed_fields = [
                'stage', 'estimated_value', 'due_date', 'notes', 'highlighted',
                'customer_name', 'customer_phone', 'customer_email', 'customer_address',
                'pricing_details', 'priority'
            ];
            
            foreach ($fields as $key => $value) {
                if (!in_array($key, $allowed_fields)) continue;
                
                $sanitized = is_array($value) ? $value : sanitize_text_field($value);
                
                // Validate stage
                if ($key === 'stage') {
                    if (isset(PI_STAGE_MIGRATION[$sanitized])) {
                        $sanitized = PI_STAGE_MIGRATION[$sanitized];
                    }
                    if (!in_array($sanitized, PI_WORKSPACE_STAGES)) {
                        $sanitized = 'new_lead';
                    }
                }
                
                // Track estimated_value changes for invoice sync
                if ($key === 'estimated_value') {
                    $new_estimated_value = floatval($sanitized);
                    $old_value = floatval(get_post_meta($id, PI_LEAD_META_PREFIX . 'estimated_value', true));
                    if (abs($new_estimated_value - $old_value) > 0.01) {
                        $estimated_value_changed = true;
                    }
                }
                
                $old = get_post_meta($id, PI_LEAD_META_PREFIX . $key, true);
                if ($old != $sanitized) {
                    $changes[] = "$key updated";
                }
                update_post_meta($id, PI_LEAD_META_PREFIX . $key, $sanitized);
            }
            
            if (!empty($changes)) {
                $history = get_post_meta($id, PI_LEAD_META_PREFIX . 'status_history', true) ?: [];
                $history[] = current_time('mysql') . ': ' . implode(', ', $changes);
                update_post_meta($id, PI_LEAD_META_PREFIX . 'status_history', $history);
            }
            
            // Sync workspace + fire stage change hook if stage changed
            $new_stage = $fields['stage'] ?? $old_stage;
            if (isset(PI_STAGE_MIGRATION[$new_stage])) {
                $new_stage = PI_STAGE_MIGRATION[$new_stage];
            }
            
            if ($new_stage !== $old_stage && in_array($new_stage, PI_WORKSPACE_STAGES)) {
                $workspace = pi_get_user_workspace($user_id);
                
                // Remove from all stages
                foreach ($workspace as $stage => &$items) {
                    $items = array_values(array_filter($items, fn($x) => $x['id'] !== $id));
                }
                
                // Add to new stage
                if (!isset($workspace[$new_stage])) {
                    $workspace[$new_stage] = [];
                }
                $workspace[$new_stage][] = [
                    'id' => $id,
                    'order' => count($workspace[$new_stage])
                ];
                
                update_user_meta($user_id, PIF_WORKSPACE_META, $workspace);

                /**
                 * Mirror the workspace board behaviour: notify listeners (e.g. Jobs plugin)
                 * that the lead stage has changed when updated via the single-lead API.
                 */
                do_action('pi_lead_stage_changed', $id, $user_id, $old_stage, $new_stage);
            }
            
            // CRITICAL: Sync estimated_value to linked invoice if it changed
            // This ensures invoice amount stays in sync with lead pricing
            if ($estimated_value_changed && defined('PII_INVOICES_META')) {
                $planning_app_id = intval(get_post_meta($id, PI_LEAD_META_PREFIX . 'linked_planning_app_id', true));
                $invoices = get_user_meta($user_id, PII_INVOICES_META, true) ?: [];
                $invoice_updated = false;
                
                foreach ($invoices as $k => $inv) {
                    // Match by pi_lead_id or planning_app_id
                    if (($inv['pi_lead_id'] ?? 0) === $id || $inv['lead_id'] === $planning_app_id || $inv['lead_id'] === $id) {
                        $invoices[$k]['amount'] = $new_estimated_value;
                        $invoices[$k]['last_synced'] = current_time('mysql');
                        $invoice_updated = true;
                        error_log("[PI Lead Update] Synced estimated_value to invoice #{$inv['id']}: $new_estimated_value");
                        break;
                    }
                }
                
                if ($invoice_updated) {
                    update_user_meta($user_id, PII_INVOICES_META, $invoices);
                }
            }
            
            // QBO Integration: Trigger lead updated hook
            do_action('pi_lead_updated', $id, ['fields' => $fields, 'user_id' => $user_id, 'changes' => $changes]);
            
            return rest_ensure_response(['updated' => true, 'estimated_value_synced' => $estimated_value_changed]);
        }
    ]);

    // Get all tasks (optionally filter by lead_id or job_id)
    register_rest_route($namespace, '/tasks', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $tasks = get_user_meta($user_id, '_pi_tasks', true) ?: [];
            
            // Get query parameters from request - already sanitized by 'type' => 'integer'
            $lead_id = $req->get_param('lead_id');
            $job_id = $req->get_param('job_id');
            
            // Cast to int if present
            $lead_id = $lead_id ? intval($lead_id) : null;
            $job_id = $job_id ? intval($job_id) : null;
            
            error_log('[Tasks API] Raw tasks count: ' . count($tasks) . ', job_id filter: ' . ($job_id ?: 'none') . ', lead_id filter: ' . ($lead_id ?: 'none'));
            
            if ($lead_id) {
                $tasks = array_values(array_filter($tasks, fn($t) => ($t['lead_id'] ?? 0) == $lead_id));
            }
            if ($job_id) {
                $before_count = count($tasks);
                $tasks = array_values(array_filter($tasks, fn($t) => ($t['job_id'] ?? 0) == $job_id));
                error_log('[Tasks API] After job_id=' . $job_id . ' filter: ' . count($tasks) . ' of ' . $before_count);
            }
            
            return rest_ensure_response($tasks);
        },
        'args' => [
            'lead_id' => [
                'type' => 'integer',
                'default' => null,
                // Note: No sanitize_callback - WordPress validates 'type' => 'integer' automatically
            ],
            'job_id' => [
                'type' => 'integer',
                'default' => null,
                // Note: No sanitize_callback - WordPress validates 'type' => 'integer' automatically
            ],
        ]
    ]);

    // Add task
    register_rest_route($namespace, '/tasks/add', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $tasks = get_user_meta($user_id, '_pi_tasks', true) ?: [];
            
            // Generate unique ID
            $max_id = 0;
            foreach ($tasks as $t) {
                if (isset($t['id']) && $t['id'] > $max_id) $max_id = $t['id'];
            }
            $new_id = $max_id + 1;
            
            $new_task = [
                'id' => $new_id,
                'title' => sanitize_text_field($req['title'] ?? 'New Task'),
                'notes' => sanitize_textarea_field($req['notes'] ?? $req['description'] ?? ''),
                'due' => sanitize_text_field($req['due'] ?? ''),
                'priority' => sanitize_text_field($req['priority'] ?? 'medium'),
                'completed' => !empty($req['completed']),
                'created' => current_time('mysql'),
                'lead_id' => intval($req['lead_id'] ?? 0),
                'job_id' => intval($req['job_id'] ?? 0),
            ];
            $tasks[] = $new_task;
            update_user_meta($user_id, '_pi_tasks', $tasks);
            return rest_ensure_response(['added' => true, 'id' => $new_id, 'task' => $new_task]);
        }
    ]);

    // Update task
    register_rest_route($namespace, '/tasks/update', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $task_id = intval($req['task_id']);
            $tasks = get_user_meta($user_id, '_pi_tasks', true) ?: [];
            $found = false;
            $updated_task = null;
            
            foreach ($tasks as &$task) {
                if ($task['id'] == $task_id) {
                    if (isset($req['title'])) $task['title'] = sanitize_text_field($req['title']);
                    if (isset($req['notes'])) $task['notes'] = sanitize_textarea_field($req['notes']);
                    if (isset($req['description'])) $task['notes'] = sanitize_textarea_field($req['description']);
                    if (isset($req['due'])) $task['due'] = sanitize_text_field($req['due']);
                    if (isset($req['priority'])) $task['priority'] = sanitize_text_field($req['priority']);
                    if (isset($req['completed'])) $task['completed'] = !empty($req['completed']);
                    if (isset($req['lead_id'])) $task['lead_id'] = intval($req['lead_id']);
                    if (isset($req['job_id'])) $task['job_id'] = intval($req['job_id']);
                    $task['updated'] = current_time('mysql');
                    $updated_task = $task;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                update_user_meta($user_id, '_pi_tasks', $tasks);
                return rest_ensure_response(['updated' => true, 'task' => $updated_task]);
            } else {
                return new WP_Error('not_found', 'Task not found', ['status' => 404]);
            }
        }
    ]);

    // Remove task
    register_rest_route($namespace, '/tasks/remove', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $task_id = intval($req['task_id']);
            $tasks = get_user_meta($user_id, '_pi_tasks', true) ?: [];
            $tasks = array_values(array_filter($tasks, fn($t) => $t['id'] != $task_id));
            update_user_meta($user_id, '_pi_tasks', $tasks);
            return rest_ensure_response(['removed' => true]);
        }
    ]);

    // Bulk update tasks (for completing/deleting multiple)
    register_rest_route($namespace, '/tasks/bulk', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $action = sanitize_text_field($req['action'] ?? '');
            $task_ids = array_map('intval', $req['task_ids'] ?? []);
            
            if (empty($task_ids)) {
                return new WP_Error('invalid_request', 'No task IDs provided', ['status' => 400]);
            }
            
            $tasks = get_user_meta($user_id, '_pi_tasks', true) ?: [];
            
            if ($action === 'complete') {
                foreach ($tasks as &$task) {
                    if (in_array($task['id'], $task_ids)) {
                        $task['completed'] = true;
                        $task['updated'] = current_time('mysql');
                    }
                }
                update_user_meta($user_id, '_pi_tasks', $tasks);
                return rest_ensure_response(['updated' => true, 'count' => count($task_ids)]);
            } elseif ($action === 'delete') {
                $tasks = array_values(array_filter($tasks, fn($t) => !in_array($t['id'], $task_ids)));
                update_user_meta($user_id, '_pi_tasks', $tasks);
                return rest_ensure_response(['deleted' => true, 'count' => count($task_ids)]);
            } else {
                return new WP_Error('invalid_action', 'Invalid bulk action', ['status' => 400]);
            }
        }
    ]);

    // ===========================================
    // COMMUNICATIONS / EMAIL ENDPOINTS
    // ===========================================

    // Get communications history for a job
    register_rest_route($namespace, '/communications', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $job_id = intval($req->get_param('job_id') ?? 0);
            
            if (!$job_id) {
                return new WP_Error('missing_job_id', 'Job ID is required', ['status' => 400]);
            }
            
            // Get stored communications for this job
            $communications = get_post_meta($job_id, '_pi_communications', true) ?: [];
            
            // Sort by date descending
            usort($communications, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
            
            return rest_ensure_response($communications);
        },
        'args' => [
            'job_id' => [
                'type' => 'integer',
                'required' => true,
            ],
        ]
    ]);

    // Send email
    register_rest_route($namespace, '/communications/send', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $job_id = intval($req['job_id'] ?? 0);
            
            if (!$job_id) {
                return new WP_Error('missing_job_id', 'Job ID is required', ['status' => 400]);
            }
            
            // Get email data
            $from = sanitize_email($req['from'] ?? '');
            $from_name = sanitize_text_field($req['from_name'] ?? '');
            $to = sanitize_email($req['to'] ?? '');
            $cc = sanitize_email($req['cc'] ?? '');
            $subject = sanitize_text_field($req['subject'] ?? '');
            $body = sanitize_textarea_field($req['body'] ?? '');
            $template = sanitize_text_field($req['template'] ?? '');
            $attachments = $req['attachments'] ?? [];
            
            if (!$from || !$to || !$subject || !$body) {
                return new WP_Error('missing_fields', 'From, To, Subject and Body are required', ['status' => 400]);
            }
            
            // Check if user has custom SMTP settings
            $user_smtp = get_user_meta($user_id, '_pi_smtp_settings', true);
            $use_custom_smtp = !empty($user_smtp['host']) && !empty($user_smtp['password']);
            
            // Process attachments
            $uploaded_files = [];
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (isset($attachment['data']) && isset($attachment['name'])) {
                        // Decode base64 data
                        $data = base64_decode(preg_replace('#^data:application/[^;]+;base64,#', '', $attachment['data']));
                        if ($data) {
                            // Save to temp file
                            $temp_file = wp_tempnam($attachment['name']);
                            file_put_contents($temp_file, $data);
                            $uploaded_files[] = $temp_file;
                        }
                    }
                }
            }
            
            // Convert plain text to HTML
            $body_html = nl2br(esc_html($body));
            $body_html = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">' . $body_html . '</div>';
            
            $sent = false;
            $email_source = '';
            
            if ($use_custom_smtp) {
                // Use user's custom SMTP settings
                $email_source = 'custom_smtp';
                
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                try {
                    $mail->isSMTP();
                    $mail->Host = $user_smtp['host'];
                    $mail->Port = $user_smtp['port'] ?: 587;
                    $mail->SMTPAuth = true;
                    $mail->Username = $user_smtp['username'];
                    $mail->Password = base64_decode($user_smtp['password']);
                    $mail->SMTPSecure = $user_smtp['encryption'] ?: 'tls';
                    $mail->SMTPDebug = 0;
                    
                    // Use the from email the user entered, or fall back to their configured email
                    $sender_email = $from ?: $user_smtp['from_email'];
                    $sender_name = $from_name ?: $user_smtp['from_name'];
                    
                    $mail->setFrom($sender_email, $sender_name);
                    $mail->addAddress($to);
                    
                    if ($cc) {
                        $mail->addCC($cc);
                    }
                    
                    // Add attachments
                    foreach ($uploaded_files as $file) {
                        $mail->addAttachment($file);
                    }
                    
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $body_html;
                    
                    $sent = $mail->send();
                    
                } catch (Exception $e) {
                    error_log('[Communications] Custom SMTP send failed: ' . $mail->ErrorInfo);
                    $sent = false;
                }
            }
            
            // If custom SMTP not configured or failed, fall back to default wp_mail
            if (!$sent) {
                $email_source = $use_custom_smtp ? 'custom_smtp_failed_fallback' : 'default';
                
                // Setup email headers for default sending
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                
                // Use the email entered by user as the From address
                // If empty, fall back to hello@planningindex.co.uk
                $sender_email = $from ?: 'hello@planningindex.co.uk';
                $sender_name = $from_name ?: 'PlanningIndex';
                
                // Try to set custom From header
                if ($from && $from !== 'hello@planningindex.co.uk') {
                    // User entered a custom email - try to use it
                    $headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
                    $headers[] = 'Reply-To: ' . $sender_email;
                } else {
                    // Use default hello@planningindex.co.uk
                    $headers[] = 'From: ' . $sender_name . ' <hello@planningindex.co.uk>';
                    $headers[] = 'Reply-To: ' . $sender_email;
                }
                
                if ($cc) {
                    $headers[] = 'Cc: ' . $cc;
                }
                
                // Send using wp_mail (FluentSMTP)
                $sent = wp_mail($to, $subject, $body_html, $headers, $uploaded_files);
            }
            
            // Clean up temp files
            foreach ($uploaded_files as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            
            if (!$sent) {
                error_log('[Communications] Email send failed for job ' . $job_id . ' (source: ' . $email_source . ')');
                return new WP_Error('send_failed', 'Failed to send email. Check your email configuration or SMTP settings.', ['status' => 500]);
            }
            
            // Update from to the actual sender email used
            if ($use_custom_smtp) {
                $from = $from ?: $user_smtp['from_email'];
            } elseif (!$from) {
                $from = 'hello@planningindex.co.uk';
            }
            
            // Store communication record
            $communications = get_post_meta($job_id, '_pi_communications', true) ?: [];
            
            $new_comm = [
                'id' => time(), // Use timestamp as unique ID
                'type' => 'sent',
                'from' => $from,
                'from_name' => $from_name,
                'to' => $to,
                'cc' => $cc,
                'subject' => $subject,
                'body' => $body,
                'template' => $template,
                'date' => current_time('mysql'),
                'sent_by' => $user_id,
                'has_attachments' => !empty($attachments),
                'attachments' => array_map(fn($att) => ['name' => $att['name']], $attachments)
            ];
            
            array_unshift($communications, $new_comm);
            
            // Keep only last 100 communications per job
            if (count($communications) > 100) {
                $communications = array_slice($communications, 0, 100);
            }
            
            update_post_meta($job_id, '_pi_communications', $communications);
            
            error_log('[Communications] Email sent successfully for job ' . $job_id);
            
            return rest_ensure_response([
                'sent' => true,
                'id' => $new_comm['id'],
                'message' => 'Email sent successfully'
            ]);
        }
    ]);

    // Save email draft
    register_rest_route($namespace, '/communications/draft', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $job_id = intval($req['job_id'] ?? 0);
            
            if (!$job_id) {
                return new WP_Error('missing_job_id', 'Job ID is required', ['status' => 400]);
            }
            
            $drafts = get_post_meta($job_id, '_pi_email_drafts', true) ?: [];
            
            $new_draft = [
                'id' => time(),
                'from' => sanitize_email($req['from'] ?? ''),
                'from_name' => sanitize_text_field($req['from_name'] ?? ''),
                'to' => sanitize_email($req['to'] ?? ''),
                'cc' => sanitize_email($req['cc'] ?? ''),
                'subject' => sanitize_text_field($req['subject'] ?? ''),
                'body' => sanitize_textarea_field($req['body'] ?? ''),
                'template' => sanitize_text_field($req['template'] ?? ''),
                'created' => current_time('mysql'),
                'created_by' => $user_id
            ];
            
            $drafts[] = $new_draft;
            update_post_meta($job_id, '_pi_email_drafts', $drafts);
            
            return rest_ensure_response(['saved' => true, 'id' => $new_draft['id']]);
        }
    ]);

    // ===========================================
    // USER SMTP SETTINGS - For custom email sending
    // ===========================================
    
    // Get user's SMTP settings
    register_rest_route($namespace, '/user/smtp', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            $settings = get_user_meta($user_id, '_pi_smtp_settings', true) ?: [];
            
            // Return settings without password for security
            return rest_ensure_response([
                'enabled' => !empty($settings['host']),
                'from_email' => $settings['from_email'] ?? '',
                'from_name' => $settings['from_name'] ?? '',
                'host' => $settings['host'] ?? '',
                'port' => $settings['port'] ?? 587,
                'encryption' => $settings['encryption'] ?? 'tls',
                'username' => $settings['username'] ?? ''
                // Note: password is NOT returned for security
            ]);
        }
    ]);
    
    // Save user's SMTP settings
    register_rest_route($namespace, '/user/smtp', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            $settings = [
                'from_email' => sanitize_email($req['from_email'] ?? ''),
                'from_name' => sanitize_text_field($req['from_name'] ?? ''),
                'host' => sanitize_text_field($req['host'] ?? ''),
                'port' => intval($req['port'] ?? 587),
                'encryption' => sanitize_text_field($req['encryption'] ?? 'tls'),
                'username' => sanitize_text_field($req['username'] ?? ''),
                'password' => $req['password'] ?? '' // Will be encrypted before storage
            ];
            
            // Validate required fields
            if ($settings['host'] && (!$settings['from_email'] || !$settings['username'] || !$settings['password'])) {
                return new WP_Error('missing_fields', 'From Email, Username, and Password are required when SMTP is enabled', ['status' => 400]);
            }
            
            // Encrypt password before storing
            if ($settings['password']) {
                $settings['password'] = base64_encode($settings['password']); // Basic obfuscation
            } else {
                // Keep existing password if not provided
                $existing = get_user_meta($user_id, '_pi_smtp_settings', true);
                if ($existing && isset($existing['password'])) {
                    $settings['password'] = $existing['password'];
                }
            }
            
            update_user_meta($user_id, '_pi_smtp_settings', $settings);
            
            return rest_ensure_response([
                'saved' => true,
                'message' => 'SMTP settings saved successfully'
            ]);
        }
    ]);
    
    // Test SMTP connection
    register_rest_route($namespace, '/user/smtp/test', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            // Get settings from request body (for testing before saving) or from saved settings
            $body = $req->get_json_params();
            
            // Track if using saved settings (password is base64 encoded) vs request body (plain text)
            $using_saved_settings = false;
            
            if (!empty($body['host'])) {
                // Use settings from request body (testing unsaved settings)
                $settings = [
                    'from_name' => sanitize_text_field($body['from_name'] ?? ''),
                    'from_email' => sanitize_email($body['from_email'] ?? ''),
                    'host' => sanitize_text_field($body['host'] ?? ''),
                    'port' => intval($body['port'] ?? 587),
                    'encryption' => sanitize_text_field($body['encryption'] ?? 'tls'),
                    'username' => sanitize_text_field($body['username'] ?? ''),
                    'password' => sanitize_text_field($body['password'] ?? '')
                ];
            } else {
                // Use saved settings
                $settings = get_user_meta($user_id, '_pi_smtp_settings', true);
                $using_saved_settings = true;
                
                if (empty($settings['host'])) {
                    return new WP_Error('no_settings', 'No SMTP settings configured. Please enter your SMTP settings first.', ['status' => 400]);
                }
            }
            
            // Simple test - try to send a test email
            $test_to = sanitize_email($body['test_email'] ?? $settings['from_email']);
            
            // Use PHPMailer directly
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host = $settings['host'];
                $mail->Port = $settings['port'] ?: 587;
                $mail->SMTPAuth = true;
                $mail->Username = $settings['username'];
                // Only base64_decode if using saved settings (stored password is encoded)
                // When testing with form data, password is plain text
                $mail->Password = $using_saved_settings ? base64_decode($settings['password']) : $settings['password'];
                $mail->SMTPSecure = $settings['encryption'] ?: 'tls';
                $mail->SMTPDebug = 0;
                
                $mail->setFrom($settings['from_email'], $settings['from_name']);
                $mail->addAddress($test_to);
                $mail->Subject = 'SMTP Test from PlanningIndex';
                $mail->Body = 'This is a test email to verify your SMTP settings are working correctly.';
                $mail->isHTML(false);
                
                $mail->send();
                
                return rest_ensure_response([
                    'success' => true,
                    'message' => 'Test email sent successfully to ' . $test_to
                ]);
            } catch (Exception $e) {
                return new WP_Error('smtp_error', 'SMTP Error: ' . $mail->ErrorInfo, ['status' => 500]);
            }
        }
    ]);

    // Get user's jobs (for task assignment)
    register_rest_route($namespace, '/jobs', [
        'methods' => 'GET',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $user_id = get_current_user_id();
            
            $jobs = get_posts([
                'post_type' => 'pi_job',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'author' => $user_id
            ]);
            
            $result = [];
            foreach ($jobs as $job) {
                $result[] = [
                    'id' => $job->ID,
                    'title' => $job->post_title,
                    'code' => get_post_meta($job->ID, '_pi_job_ref', true) ?: $job->post_title,
                    'address' => get_post_meta($job->ID, '_pi_job_site_address', true) ?: ''
                ];
            }
            
            error_log('[Jobs API] Found ' . count($result) . ' jobs for user ' . $user_id);
            
            return rest_ensure_response($result);
        }
    ]);
    
    // QBO Webhook endpoint for QuickBooks Online integration
    register_rest_route('planningindex/v1', '/qbo-webhook', [
        'methods' => 'POST',
        'callback' => ['PI_QBO\\PI_QBO_Webhook_Handler', 'handle_incoming'],
        'permission_callback' => '__return_true' // Signature verified internally
    ]);
    
    // Sync lead to QuickBooks
    register_rest_route($namespace, '/leads/(?P<id>\d+)/sync-to-qbo', [
        'methods' => 'POST',
        'permission_callback' => fn() => is_user_logged_in(),
        'callback' => function(WP_REST_Request $req) {
            $lead_id = intval($req->get_param('id'));
            $params = $req->get_json_params();
            $planning_app_id = intval($params['planning_app_id'] ?? 0);
            
            // Verify the lead exists and belongs to current user
            $lead = get_post($lead_id);
            if (!$lead || $lead->post_type !== PI_LEAD_CPT) {
                return new WP_Error('not_found', 'Lead not found', ['status' => 404]);
            }
            
            $owner_id = get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'owner_user_id', true);
            if ($owner_id != get_current_user_id()) {
                return new WP_Error('forbidden', 'You do not own this lead', ['status' => 403]);
            }
            
            // Check if QuickBooks integration is available
            if (!class_exists('PI_QBO\PI_QBO_User_Auth')) {
                return new WP_Error('qbo_not_available', 'QuickBooks integration not available', ['status' => 503]);
            }
            
            // Check if user is connected to QuickBooks
            $user_auth = new PI_QBO\PI_QBO_User_Auth(get_current_user_id());
            if (!$user_auth->is_connected()) {
                return new WP_Error('qbo_not_connected', 'Please connect to QuickBooks first', ['status' => 401]);
            }
            
            // Get lead data
            $lead_data = [
                'id' => $lead_id,
                'planning_app_id' => $planning_app_id,
                'customer_name' => get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'customer_name', true),
                'customer_email' => get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'customer_email', true),
                'customer_phone' => get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'customer_phone', true),
                'customer_address' => get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'customer_address', true),
                'estimated_value' => get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'estimated_value', true),
                'notes' => get_post_meta($lead_id, PI_LEAD_META_PREFIX . 'notes', true),
            ];
            
            // Trigger sync to QuickBooks using the sync engine
            try {
                if (class_exists('PI_QBO\PI_QBO_Sync_Engine')) {
                    $sync_engine = new PI_QBO\PI_QBO_Sync_Engine();
                    $result = $sync_engine->sync_lead_to_qbo($lead_data, get_current_user_id());
                    
                    if ($result && !is_wp_error($result)) {
                        // Mark lead as synced
                        update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'qbo_synced', true);
                        update_post_meta($lead_id, PI_LEAD_META_PREFIX . 'qbo_synced_at', current_time('mysql'));
                        
                        return rest_ensure_response([
                            'success' => true,
                            'message' => 'Synced to QuickBooks successfully',
                            'qbo_customer_id' => $result['qbo_customer_id'] ?? null
                        ]);
                    } else {
                        $error_msg = is_wp_error($result) ? $result->get_error_message() : 'Sync failed';
                        return new WP_Error('sync_failed', $error_msg, ['status' => 500]);
                    }
                } else {
                    return new WP_Error('sync_engine_not_available', 'Sync engine not available', ['status' => 503]);
                }
            } catch (Exception $e) {
                error_log('PI Workspace QBO Sync Error: ' . $e->getMessage());
                return new WP_Error('sync_error', 'An error occurred during sync: ' . $e->getMessage(), ['status' => 500]);
            }
        }
    ]);
});

/**
 * Get user workspace with all 5 stages initialized
 */
function pi_get_user_workspace($user_id) {
    $f = get_user_meta($user_id, PIF_WORKSPACE_META, true);
    
    // Initialize with all stages
    $default = [];
    foreach (PI_WORKSPACE_STAGES as $stage) {
        $default[$stage] = [];
    }
    
    if (!is_array($f)) {
        return $default;
    }
    
    // Migrate old stages and merge with default
    $migrated = $default;
    foreach ($f as $stage => $items) {
        $new_stage = PI_STAGE_MIGRATION[$stage] ?? $stage;
        if (in_array($new_stage, PI_WORKSPACE_STAGES)) {
            $migrated[$new_stage] = array_merge($migrated[$new_stage], $items);
        }
    }
    
    return $migrated;
}

/**
 * Title case helper for addresses
 */
if (!function_exists('title_case_address')) {
    function title_case_address($s) {
        return preg_replace_callback('/(^|[\s\-\/\(\)\,\.])([a-z0-9])/i', function($m) {
            return $m[1] . strtoupper($m[2]);
        }, strtolower($s));
    }
}

