<?php
/**
 * Single Job template - Planning Index Workspace
 * Job management page (created from won leads). Same layout as lead, job-specific fields.
 */
if (!defined('ABSPATH')) exit;
$job_id = get_the_ID();
$workspace_url = home_url('/workspace/');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <?php
    // Enqueue FullCalendar for job schedule tab
    wp_enqueue_style('fullcalendar-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css', [], '6.1.10');
    wp_enqueue_script('fullcalendar-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', [], '6.1.10', true);
    wp_enqueue_style('pi-job-calendar-style', plugin_dir_url(__FILE__) . '../assets/job-calendar.css', [], '1.2.3');
    wp_enqueue_script('pi-job-calendar-script', plugin_dir_url(__FILE__) . '../assets/job-calendar.js?v=' . time() . '&t=' . time(), ['fullcalendar-core', 'jquery'], '1.0.0', true);
    
    // Pass job data to JavaScript - CRITICAL for calendar to work
    wp_localize_script('pi-job-calendar-script', 'PI_Job', [
        'job_id' => $job_id,
        'nonce' => wp_create_nonce('wp_rest'),
        'workspace_url' => $workspace_url,
        'job_code' => get_the_title($job_id),
        'rest_base' => rest_url('pi/v1')
    ]);

    // Enqueue Tasks script
    wp_enqueue_script('pi-job-tasks-script', plugin_dir_url(__FILE__) . '../assets/job-tasks.js', ['jquery'], '1.0.4', true);
    
    // Localize data for tasks script too
    wp_localize_script('pi-job-tasks-script', 'PI_Job', [
        'job_id' => $job_id,
        'nonce' => wp_create_nonce('wp_rest'),
        'workspace_url' => $workspace_url,
        'job_code' => get_the_title($job_id),
        'job_ref' => get_post_meta($job_id, '_pi_job_ref', true) ?: get_the_title($job_id),
        'rest_base' => rest_url('pi/v1')
    ]);

    // Get current user email and site email
    $current_user = wp_get_current_user();
    
    // Ensure job_id is set (fallback to current post ID if not set)
    $comm_job_id = $job_id ?: get_the_ID();
    $comm_job_ref = get_post_meta($comm_job_id, '_pi_job_ref', true) ?: get_the_title($comm_job_id);
    
    // Add inline script FIRST to ensure PI_Job exists before main script
    wp_register_script('pi-job-communications-data', '', [], '1.3.2', true);
    wp_enqueue_script('pi-job-communications-data');
    wp_add_inline_script('pi-job-communications-data', "
        window.PI_Job_Communications = {
            job_id: {$comm_job_id},
            job_ref: '{$comm_job_ref}',
            rest_base: '". rest_url('pi/v1') ."',
            nonce: '". wp_create_nonce('wp_rest') ."'
        };
        console.log('[PI] Communications data set:', window.PI_Job_Communications);
        if (!window.PI_Job_Communications.job_id) {
            console.error('[PI] CRITICAL: job_id not set in PI_Job_Communications!');
        }
    ");
    
    // Enqueue Communications script
    wp_enqueue_script('pi-job-communications', plugin_dir_url(__FILE__) . '../assets/job-communications.js', ['jquery', 'pi-job-communications-data'], '1.3.3', true);
    
    // Enqueue Mapbox for Site Map
    wp_enqueue_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css', [], '3.0.1');
    wp_enqueue_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js', [], '3.0.1', true);
    
    // Enqueue CRM script (handles documents, team, safety, quality, etc.)
    wp_enqueue_script('pi-job-crm', plugin_dir_url(__FILE__) . '../assets/job-crm.js', ['jquery', 'mapbox-gl-js'], '2.0.0', true);
    
    // Enqueue Team & Timesheets module (cache busting for dev)
    $tt_version = PI_TEAM_TIMESHEETS_VERSION . '.' . time();
    wp_enqueue_style('pi-team-timesheets', plugin_dir_url(__FILE__) . '../assets/team-timesheets.css', [], $tt_version);
    wp_enqueue_script('pi-team-timesheets', plugin_dir_url(__FILE__) . '../assets/team-timesheets.js', ['jquery'], $tt_version, true);
    
    // Enqueue Equipment Management module
    wp_enqueue_style('pi-job-equipment', plugin_dir_url(__FILE__) . '../assets/job-equipment.css', [], '1.0.0');
    wp_enqueue_script('pi-job-equipment', plugin_dir_url(__FILE__) . '../assets/job-equipment.js', ['jquery', 'pi-job-crm'], '1.0.0', true);
    
    // Enqueue Daily Reports module
    wp_enqueue_style('pi-daily-reports', plugin_dir_url(__FILE__) . '../assets/daily-reports.css', [], '1.0.2');
    wp_enqueue_script('pi-daily-reports', plugin_dir_url(__FILE__) . '../assets/daily-reports.js', ['jquery'], '1.0.2', true);
    
    wp_localize_script('pi-daily-reports', 'PI_Daily_Reports_Settings', [
        'rest_base' => rest_url('pi-daily-reports/v1'),
        'team_rest_base' => rest_url('pi/v1'),
        'nonce' => wp_create_nonce('wp_rest'),
        'user_id' => get_current_user_id()
    ]);
    
    // Enqueue Safety Module
    wp_enqueue_style('pi-safety-module-css', plugin_dir_url(__FILE__) . '../assets/pi-safety-module.css', [], '1.0.0');
    wp_enqueue_script('pi-safety-module-js', plugin_dir_url(__FILE__) . '../assets/pi-safety-module.js', ['jquery'], '1.0.0', true);
    
    wp_localize_script('pi-safety-module-js', 'PI_Safety', [
        'rest_base' => rest_url('pi-crm/v1'),
        'nonce' => wp_create_nonce('wp_rest'),
        'job_id' => $job_id,
        'user_display_name' => $current_user->display_name ?: $current_user->user_nicename
    ]);
    
    wp_localize_script('pi-job-communications', 'PI_Job', [
        'job_id' => $comm_job_id,
        'job_ref' => $comm_job_ref,
        'nonce' => wp_create_nonce('wp_rest'),
        'rest_base' => rest_url('pi/v1'),
        'site_email' => get_option('admin_email'),
        'user_email' => $current_user->user_email,
        'user_name' => $current_user->display_name ?: $current_user->user_nicename
    ]);
    
    // Also localize for CRM script (use unique variable name to avoid conflicts)
    wp_localize_script('pi-job-crm', 'PI_Job_CRM', [
        'job_id' => $comm_job_id,
        'job_ref' => $comm_job_ref,
        'nonce' => wp_create_nonce('wp_rest'),
        'rest_base' => rest_url('pi-crm/v1'),
        'site_email' => get_option('admin_email'),
        'user_email' => $current_user->user_email,
        'user_name' => $current_user->display_name ?: $current_user->user_nicename,
        'mapbox_token' => 'pk.eyJ1IjoicGxhbm5pbmdpbmRleCIsImEiOiJjbWs4ZnZ6MGUxOWg1M2NyNW9xbnZodWx3In0.SOAFHPon69-aJS2G6qAoBQ',
        'site_address' => get_post_meta($job_id, '_pi_job_site_address', true) ?: ''
    ]);
    ?>
    <?php wp_head(); ?>
    <style>
    :root {
      --workspace-pi-navy: #1b2534;
      --workspace-pi-navy-light: #2a3a4d;
      --workspace-pi-sidebar-width: 60px;
      --workspace-pi-radius-sm: 6px;
      --workspace-pi-radius-md: 8px;
      --workspace-pi-transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
      --workspace-pi-transition-normal: 200ms cubic-bezier(0.4, 0, 0.2, 1);
      --workspace-pi-space-sm: 8px;
      --workspace-pi-space-md: 16px;
      --workspace-pi-space-lg: 24px;
    }
    .workspace-pi-sidebar { position: fixed; top: 0; left: 0; width: var(--workspace-pi-sidebar-width); height: 100vh; background: var(--workspace-pi-navy); display: flex; flex-direction: column; align-items: center; padding: var(--workspace-pi-space-md) 0; z-index: 1000; border-right: 1px solid var(--workspace-pi-navy-light); }
    .workspace-pi-sidebar-brand { width: 100%; height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: var(--workspace-pi-space-lg); }
    .workspace-pi-sidebar-nav { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; width: 100%; padding: 0 var(--workspace-pi-space-sm); }
    .workspace-pi-nav-link { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.55); border-radius: var(--workspace-pi-radius-md); transition: all var(--workspace-pi-transition-normal); text-decoration: none; }
    .workspace-pi-nav-link:hover { color: #fff; background: var(--workspace-pi-navy-light); }
    .workspace-pi-nav-link.active { color: #fff; background: var(--workspace-pi-navy-light); }
    .workspace-pi-nav-link.active::after { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 20px; background: #fff; border-radius: 0 2px 2px 0; }
    .workspace-pi-nav-link::before { content: attr(data-tooltip); position: absolute; left: calc(100% + 10px); top: 50%; transform: translateY(-50%); background: var(--workspace-pi-navy); color: #fff; padding: 6px 12px; border-radius: var(--workspace-pi-radius-sm); font-size: 12px; font-weight: 500; white-space: nowrap; opacity: 0; visibility: hidden; transition: all var(--workspace-pi-transition-fast); pointer-events: none; z-index: 1001; }
    .workspace-pi-nav-link:hover::before { opacity: 1; visibility: visible; }
    .pi-main-wrapper { margin-left: var(--workspace-pi-sidebar-width); }
    /* ============================================
    TASKS PAGE – Sidebar matched to workspace page
    ============================================ */

    /* Re-use the same variables */
    :root {
    --workspace-pi-navy: #1b2534;
    --workspace-pi-navy-light: #2a3a4d;
    --workspace-pi-navy-dark: #141c28;
    --workspace-pi-sidebar-width: 60px;
    --workspace-pi-radius-sm: 6px;
    --workspace-pi-radius-md: 8px;
    --workspace-pi-transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    --workspace-pi-transition-normal: 200ms cubic-bezier(0.4, 0, 0.2, 1);
    --workspace-pi-space-sm: 8px;
    --workspace-pi-space-md: 16px;
    --workspace-pi-space-lg: 24px;
    }

    /* Page container */
    .pi-tasks-page-container {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        min-height: 100vh;
        position: relative;
        background: #f8fafc;
    }

    /* Sidebar – identical to workspace */
    .workspace-pi-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--workspace-pi-sidebar-width);
    height: 100vh;
    background: var(--workspace-pi-navy);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--workspace-pi-space-md) 0;
    z-index: 1000;
    border-right: 1px solid var(--workspace-pi-navy-light);
    box-sizing: border-box;
    }

    .workspace-pi-sidebar-brand {
    width: 100%;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: var(--workspace-pi-space-lg);
    padding: 0 var(--workspace-pi-space-sm);
    flex-shrink: 0;
    }

    .workspace-pi-brand-logo {
    width: 44px;
    height: auto;
    }

    .workspace-pi-sidebar-nav {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    flex: 1;
    width: 100%;
    padding: 0 var(--workspace-pi-space-sm);
    }

    .workspace-pi-nav-link {
    position: relative;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.55);
    border-radius: var(--workspace-pi-radius-md);
    transition: all var(--workspace-pi-transition-normal);
    text-decoration: none;
    }

    .workspace-pi-nav-link svg {
    width: 18px;
    height: 18px;
    transition: all var(--workspace-pi-transition-fast);
    }

    .workspace-pi-nav-link:hover {
    color: #ffffff;
    background: var(--workspace-pi-navy-light);
    }

    .workspace-pi-nav-link:hover svg {
    transform: scale(1.05);
    }

    .workspace-pi-nav-link.active {
    color: #ffffff;
    background: var(--workspace-pi-navy-light);
    }

    .workspace-pi-nav-link.active::after {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 20px;
    background: #ffffff;
    border-radius: 0 2px 2px 0;
    }

    /* Tooltip – exact same behavior */
    .workspace-pi-nav-link::before {
    content: attr(data-tooltip);
    position: absolute;
    left: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
    background: var(--workspace-pi-navy);
    color: #ffffff;
    padding: 6px 12px;
    border-radius: var(--workspace-pi-radius-sm);
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all var(--workspace-pi-transition-fast);
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    z-index: 1001;
    border: 1px solid var(--workspace-pi-navy-light);
    }

    .workspace-pi-nav-link:hover::before {
    opacity: 1;
    visibility: visible;
    }

    /* Main content – pushed by sidebar width */
    .workspace-pi-content {
    margin-left: var(--workspace-pi-sidebar-width);
    min-height: 100vh;
    width: calc(100% - var(--workspace-pi-sidebar-width));
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    }

    /* Mobile – hide sidebar */
    @media (max-width: 768px) {
        .workspace-pi-sidebar {
            display: none;
        }
        .workspace-pi-content {
            margin-left: 0;
            width: 100%;
        }
    }

    /* ============================================
    MAIN JOB TABS NAVIGATION (Moved to Header)
    ============================================ */
    .pi-job-main-tabs {
        display: flex;
        gap: 4px;
        margin: 20px 0;
        padding: 4px;
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .pi-job-main-tab {
        padding: 10px 20px;
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .pi-job-main-tab:hover {
        color: #1b2534;
        background: #f1f5f9;
    }
    .pi-job-main-tab.active {
        color: #fff;
        background: #156349;
        box-shadow: 0 1px 2px rgba(21, 99, 73, 0.2);
    }
    @media (max-width: 768px) {
        .pi-job-main-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .pi-job-main-tab {
            padding: 8px 14px;
            font-size: 13px;
        }
    }

    /* ============================================
    OVERVIEW KPI GRID - 4 columns
    ============================================ */
    .pi-kpi-grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    /* Ensure KPI grid is visible when overview is shown */
    .pi-overview-layout:not(.hidden) ~ .pi-kpi-grid,
    body:has(.pi-overview-layout:not(.hidden)) .pi-kpi-grid {
        display: grid !important;
    }
    @media (max-width: 1024px) {
        .pi-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 640px) {
        .pi-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
    .pi-kpi-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }
    .pi-kpi-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }
    .pi-kpi-icon svg {
        width: 24px;
        height: 24px;
    }
    .pi-kpi-icon-blue { background: #dbeafe; color: #2563eb; }
    .pi-kpi-icon-amber { background: #fef3c7; color: #d97706; }
    .pi-kpi-icon-green { background: #dcfce7; color: #16a34a; }
    .pi-kpi-icon-purple { background: #e9d5ff; color: #9333ea; }
    .pi-kpi-content { display: flex; flex-direction: column; }
    .pi-kpi-label { font-size: 13px; color: #64748b; margin-bottom: 4px; }
    .pi-kpi-value { font-size: 20px; font-weight: 700; color: #0f172a; }

    /* ============================================
    OVERVIEW MAIN GRID LAYOUT - 3/4 + 1/4
    ============================================ */
    .pi-lead-grid {
        display: grid !important;
        grid-template-columns: 3fr 1fr;
        gap: 24px;
    }
    /* Ensure overview layout is visible when not hidden */
    .pi-overview-layout:not(.hidden) {
        display: grid !important;
    }
    .pi-overview-layout.hidden {
        display: none !important;
    }
    .pi-kpi-grid.hidden {
        display: none !important;
    }
    .pi-lead-col-main {
        min-width: 0;
    }
    .pi-lead-col-sidebar {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    @media (max-width: 1200px) {
        .pi-lead-grid {
            grid-template-columns: 1fr;
        }
        .pi-lead-col-sidebar {
            order: -1;
        }
    }
    /* Ensure overview content is visible */
    #tab-overview {
        display: block;
    }
    #tab-overview.active {
        display: block !important;
    }
    /* Overview layout specific tab panels */
    .pi-overview-layout .pi-tab-panel {
        display: none;
    }
    .pi-overview-layout .pi-tab-panel.active {
        display: block !important;
    }

    /* ============================================
    FULL PAGE TAB PANELS
    ============================================ */
    /* When full-page tabs are active, they should display as full page */
    #tab-costs.active,
    #tab-materials.active,
    #tab-schedule.active,
    #tab-tasks.active,
    #tab-client.active {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 20px;
    }
    /* Default hidden state for non-overview tabs */
    #tab-costs:not(.active),
    #tab-materials:not(.active),
    #tab-schedule:not(.active),
    #tab-tasks:not(.active),
    #tab-client:not(.active) {
        display: none !important;
    }
    /* Hide overview elements when on full-page tabs */
    body:has(#tab-costs.active) .pi-kpi-grid,
    body:has(#tab-costs.active) .pi-overview-layout,
    body:has(#tab-materials.active) .pi-kpi-grid,
    body:has(#tab-materials.active) .pi-overview-layout,
    body:has(#tab-schedule.active) .pi-kpi-grid,
    body:has(#tab-schedule.active) .pi-overview-layout,
    body:has(#tab-tasks.active) .pi-kpi-grid,
    body:has(#tab-tasks.active) .pi-overview-layout,
    body:has(#tab-client.active) .pi-kpi-grid,
    body:has(#tab-client.active) .pi-overview-layout {
        display: none !important;
    }

    /* ============================================
    FULL PAGE TAB CONTENT BOXES (white background)
    ============================================ */
    /* Common white box style for tab content */
    #tab-costs .pi-planning-card,
    #tab-materials .pi-planning-card,
    #tab-schedule .pi-planning-card,
    #tab-tasks .pi-job-tasks-wrapper,
    #tab-client .pi-customer-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        padding: 20px;
        margin-bottom: 20px;
    }
    /* Card headers within full-page tabs */
    #tab-costs .pi-card-header,
    #tab-materials .pi-card-header,
    #tab-schedule .pi-card-header,
    #tab-tasks .pi-job-tasks-header,
    #tab-client .pi-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    #tab-costs .pi-card-header h3,
    #tab-materials .pi-card-header h3,
    #tab-schedule .pi-card-header h3,
    #tab-tasks .pi-job-tasks-header h3,
    #tab-client .pi-card-header h3 {
        font-size: 18px;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
    }
    /* Card body within full-page tabs */
    #tab-costs .pi-card-body,
    #tab-materials .pi-card-body,
    #tab-schedule .pi-card-body,
    #tab-tasks .pi-job-tasks-container,
    #tab-client .pi-card-body {
        padding: 0;
    }

    /* ============================================
    JOB COSTS TAB STYLES
    ============================================ */
    .pi-costs-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        font-size: 13px;
        color: #64748b;
        flex-wrap: wrap;
    }
    .pi-costs-breadcrumb a {
        color: #1b2534;
        text-decoration: none;
    }
    .pi-costs-breadcrumb a:hover {
        text-decoration: underline;
    }
    .pi-breadcrumb-sep {
        color: #94a3b8;
    }
    .pi-global-ledger-link {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #156349;
        font-weight: 500;
        font-size: 13px;
    }

    /* KPI Grid */
    .pi-costs-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 1024px) {
        .pi-costs-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 640px) {
        .pi-costs-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
    .pi-costs-kpi-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }
    .pi-costs-kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .pi-costs-kpi-icon svg {
        width: 24px;
        height: 24px;
    }
    .pi-costs-kpi-icon-red { background: #fef2f2; color: #dc2626; }
    .pi-costs-kpi-icon-blue { background: #eff6ff; color: #2563eb; }
    .pi-costs-kpi-icon-green { background: #f0fdf4; color: #16a34a; }
    .pi-costs-kpi-icon-amber { background: #fffbeb; color: #d97706; }
    .pi-costs-kpi-icon-purple { background: #f5f3ff; color: #7c3aed; }
    .pi-costs-kpi-content {
        display: flex;
        flex-direction: column;
    }
    .pi-costs-kpi-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
        margin-bottom: 4px;
    }
    .pi-costs-kpi-value {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }

    /* Budget Progress */
    .pi-budget-progress-container {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }
    .pi-budget-progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        font-weight: 500;
        color: #0f172a;
    }
    .pi-budget-progress-track {
        height: 12px;
        background: #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
    }
    .pi-budget-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #156349, #16a34a);
        border-radius: 6px;
        transition: width 0.4s ease;
    }
    .pi-budget-progress-fill.pi-warning { background: linear-gradient(90deg, #d97706, #f59e0b); }
    .pi-budget-progress-fill.pi-danger { background: linear-gradient(90deg, #dc2626, #ef4444); }
    .pi-budget-progress-text {
        margin: 12px 0 0;
        font-size: 14px;
        color: #64748b;
    }
    .pi-profit-margin {
        margin-left: 12px;
        padding: 4px 10px;
        background: #f0fdf4;
        color: #16a34a;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    /* Quick Actions Bar */
    .pi-quick-actions-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .pi-quick-actions-bar .pi-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        font-size: 14px;
    }

    /* Costs Section */
    .pi-costs-section {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }
    .pi-costs-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .pi-costs-section-header h3 {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
    }
    .pi-costs-section-filters {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .pi-form-control-sm {
        height: 36px;
        padding: 8px 12px;
        font-size: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    /* Data Table */
    .pi-data-table-wrapper {
        overflow-x: auto;
    }
    .pi-job-costs-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .pi-job-costs-table th {
        text-align: left;
        padding: 12px;
        font-weight: 600;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .pi-job-costs-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .pi-job-costs-table tr:hover td {
        background: #f8fafc;
    }
    .pi-text-right { text-align: right; }
    .pi-status-badge {
        display: inline-flex;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    .pi-status-approved { background: #dcfce7; color: #166534; }
    .pi-status-pending { background: #fef3c7; color: #92400e; }
    .pi-status-draft { background: #f1f5f9; color: #475569; }

    /* Category Badge */
    .pi-cat-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
    }
    .pi-cat-materials { background: #dcfce7; color: #166534; }
    .pi-cat-labor { background: #dbeafe; color: #1e40af; }
    .pi-cat-subcontractors { background: #ede9fe; color: #5b21b6; }
    .pi-cat-tools { background: #e0e7ff; color: #3730a3; }
    .pi-cat-other { background: #f1f5f9; color: #475569; }

    /* Mileage Stats */
    .pi-mileage-stats {
        display: flex;
        gap: 20px;
        font-size: 14px;
        color: #64748b;
    }
    .pi-mileage-stats span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Cost Code Progress */
    .pi-cost-code-item {
        margin-bottom: 16px;
    }
    .pi-cost-code-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .pi-cost-code-name {
        font-weight: 500;
        color: #0f172a;
    }
    .pi-cost-code-amount {
        font-size: 14px;
        color: #64748b;
    }
    .pi-cost-code-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }
    .pi-cost-code-fill {
        height: 100%;
        background: linear-gradient(90deg, #156349, #16a34a);
        border-radius: 4px;
        transition: width 0.4s ease;
    }

    /* Action Buttons */
    .pi-action-btn {
        padding: 6px;
        border: none;
        background: transparent;
        color: #64748b;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .pi-action-btn:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .pi-action-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Empty State */
    .pi-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }
    .pi-empty-state svg {
        width: 48px;
        height: 48px;
        margin-bottom: 16px;
        opacity: 0.4;
    }
    .pi-loading-state {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .pi-quick-actions-bar {
            flex-direction: column;
        }
        .pi-quick-actions-bar .pi-btn {
            width: 100%;
            justify-content: center;
        }
        .pi-costs-section-filters {
            width: 100%;
        }
        .pi-costs-section-filters select,
        .pi-costs-section-filters input {
            flex: 1;
        }
        .pi-job-costs-table {
            font-size: 13px;
        }
        .pi-job-costs-table th,
        .pi-job-costs-table td {
            padding: 8px;
        }
    }

    /* ============================================
       SAFETY MODULE INTEGRATION STYLES
       ============================================ */
    #job-tab-safety .pi-safety-module {
        background: #f8fafc;
    }
    
    #job-tab-safety .pi-safety-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        margin: -20px -20px 20px -20px;
        padding: 1.5rem 2rem;
        border-radius: 12px 12px 0 0;
    }
    
    #job-tab-safety .pi-safety-nav {
        background: #fff;
        border-radius: 8px;
        padding: 0.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    #job-tab-safety .pi-safety-nav button {
        padding: 0.5rem 1rem;
        font-size: 13px;
        border-radius: 6px;
    }
    
    #job-tab-safety .pi-safety-content {
        padding: 0;
    }
    
    #job-tab-safety .pi-safety-section {
        display: none;
    }
    
    #job-tab-safety .pi-safety-section.active {
        display: block;
    }
    
    #job-tab-safety .pi-safety-stats {
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
    }
    
    @media (max-width: 1200px) {
        #job-tab-safety .pi-safety-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 640px) {
        #job-tab-safety .pi-safety-stats {
            grid-template-columns: 1fr;
        }
        #job-tab-safety .pi-safety-nav {
            overflow-x: auto;
        }
    }
    
    #job-tab-safety .pi-modal {
        z-index: 10000;
    }
    
    #job-tab-safety .pi-toast {
        z-index: 10001;
    }

    /* ============================================
       MATERIALS TAB STYLES
       ============================================ */

    /* Breadcrumb Navigation */
    .pi-materials-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        color: #64748b;
        flex-wrap: wrap;
    }
    .pi-materials-breadcrumb a {
        color: #156349;
        text-decoration: none;
        font-weight: 500;
    }
    .pi-materials-breadcrumb a:hover {
        text-decoration: underline;
    }
    .pi-global-library-link {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #f1f5f9;
        border-radius: 6px;
        font-size: 13px;
        color: #156349 !important;
    }
    .pi-global-library-link:hover {
        background: #e2e8f0;
    }

    /* Quick Stats Grid */
    .pi-materials-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 1024px) {
        .pi-materials-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 640px) {
        .pi-materials-stats-grid {
            grid-template-columns: 1fr;
        }
    }
    .pi-materials-stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }
    .pi-materials-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pi-materials-stat-icon svg {
        width: 22px;
        height: 22px;
    }
    .pi-materials-stat-icon-blue {
        background: #dbeafe;
        color: #1e40af;
    }
    .pi-materials-stat-icon-amber {
        background: #fef3c7;
        color: #92400e;
    }
    .pi-materials-stat-icon-green {
        background: #dcfce7;
        color: #166534;
    }
    .pi-materials-stat-icon-purple {
        background: #ede9fe;
        color: #5b21b6;
    }
    .pi-materials-stat-content {
        display: flex;
        flex-direction: column;
    }
    .pi-materials-stat-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }
    .pi-materials-stat-value {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }

    /* Budget Section */
    .pi-materials-budget-section {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }
    .pi-materials-budget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .pi-materials-budget-label {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
    }
    .pi-materials-budget-value {
        font-size: 14px;
        color: #64748b;
    }
    .pi-materials-progress-wrapper {
        height: 12px;
        background: #f1f5f9;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 12px;
    }
    .pi-materials-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #156349, #16a34a);
        border-radius: 6px;
        transition: width 0.4s ease;
    }
    .pi-materials-progress-bar.warning {
        background: linear-gradient(90deg, #d97706, #f59e0b);
    }
    .pi-materials-progress-bar.danger {
        background: linear-gradient(90deg, #dc2626, #ef4444);
    }
    .pi-materials-budget-legend {
        display: flex;
        gap: 20px;
        font-size: 12px;
        color: #64748b;
    }
    .pi-materials-legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .pi-materials-legend-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    .pi-materials-legend-committed {
        background: #3b82f6;
    }
    .pi-materials-legend-spent {
        background: #156349;
    }
    .pi-materials-legend-remaining {
        background: #e2e8f0;
    }

    /* Sub-navigation */
    .pi-materials-subnav {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        padding: 4px;
        background: #f8fafc;
        border-radius: 10px;
        overflow-x: auto;
    }
    .pi-materials-subnav-btn {
        padding: 10px 20px;
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .pi-materials-subnav-btn:hover {
        color: #0f172a;
        background: #f1f5f9;
    }
    .pi-materials-subnav-btn.active {
        background: #fff;
        color: #0f172a;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    /* Sub-tab Content */
    .pi-materials-subtabs {
        position: relative;
    }
    .pi-materials-subtab-content {
        display: none;
    }
    .pi-materials-subtab-content.active {
        display: block;
    }

    /* Overview Grid */
    .pi-materials-overview-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }
    @media (max-width: 1024px) {
        .pi-materials-overview-grid {
            grid-template-columns: 1fr;
        }
    }
    .pi-materials-overview-main,
    .pi-materials-overview-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Section Styling */
    .pi-materials-section,
    .pi-materials-purchasing-panel {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }
    .pi-materials-purchasing-panel {
        display: none;
    }
    .pi-materials-purchasing-panel.active {
        display: block;
    }
    .pi-materials-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .pi-materials-section-header h3 {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
    }
    .pi-materials-section-actions {
        display: flex;
        gap: 8px;
    }

    /* Purchasing Tabs */
    .pi-materials-purchasing-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 4px;
    }
    .pi-materials-purchasing-tab {
        padding: 10px 16px;
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border-radius: 6px 6px 0 0;
        transition: all 0.2s;
        position: relative;
    }
    .pi-materials-purchasing-tab:hover {
        color: #0f172a;
        background: #f8fafc;
    }
    .pi-materials-purchasing-tab.active {
        color: #156349;
    }
    .pi-materials-purchasing-tab.active::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        right: 0;
        height: 2px;
        background: #156349;
    }
    .pi-materials-purchasing-content {
        position: relative;
    }
    .pi-materials-purchasing-panel {
        display: none;
    }
    .pi-materials-purchasing-panel.active {
        display: block;
    }

    /* Waste Stats */
    .pi-materials-waste-stats {
        display: flex;
        gap: 16px;
        font-size: 14px;
    }
    .pi-waste-stat {
        padding: 4px 12px;
        background: #f1f5f9;
        border-radius: 20px;
        color: #475569;
        font-weight: 500;
    }

    /* CSI Badge Colors */
    .pi-csi-structural {
        background: #ede9fe;
        color: #5b21b6;
    }
    .pi-csi-finishes {
        background: #dcfce7;
        color: #166534;
    }
    .pi-csi-mep {
        background: #fee2e2;
        color: #991b1b;
    }
    .pi-csi-site {
        background: #ffedd5;
        color: #9a3412;
    }

    /* Floating Action Button (Mobile) */
    .pi-materials-fab {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 100;
        display: none;
    }
    @media (max-width: 768px) {
        .pi-materials-fab {
            display: block;
        }
    }
    .pi-materials-fab-btn {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #156349;
        color: #fff;
        border: none;
        box-shadow: 0 4px 12px rgba(21, 99, 73, 0.4);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .pi-materials-fab-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(21, 99, 73, 0.5);
    }
    .pi-materials-fab-menu {
        position: absolute;
        bottom: 70px;
        right: 0;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 8px;
        min-width: 180px;
        display: none;
        flex-direction: column;
        gap: 4px;
    }
    .pi-materials-fab.open .pi-materials-fab-menu {
        display: flex;
    }
    .pi-materials-fab-item {
        padding: 10px 16px;
        border: none;
        background: transparent;
        color: #0f172a;
        font-size: 14px;
        text-align: left;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.2s;
    }
    .pi-materials-fab-item:hover {
        background: #f1f5f9;
    }

    /* BOM Table */
    .pi-bom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .pi-bom-table th {
        text-align: left;
        padding: 12px;
        font-weight: 600;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .pi-bom-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .pi-bom-table tr:hover td {
        background: #f8fafc;
    }
    .pi-bom-editable {
        padding: 4px 8px;
        border: 1px solid transparent;
        border-radius: 4px;
        background: transparent;
        transition: all 0.2s;
        cursor: text;
    }
    .pi-bom-editable:hover {
        border-color: #e2e8f0;
        background: #fff;
    }
    .pi-bom-editable:focus {
        border-color: #156349;
        background: #fff;
        outline: none;
    }

    /* Activity Feed */
    .pi-activity-feed {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .pi-activity-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
    }
    .pi-activity-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .pi-activity-icon svg {
        width: 16px;
        height: 16px;
    }
    .pi-activity-content {
        flex: 1;
    }
    .pi-activity-text {
        font-size: 14px;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .pi-activity-time {
        font-size: 12px;
        color: #64748b;
    }

    /* Supplier Summary */
    .pi-supplier-summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .pi-supplier-summary-item:last-child {
        border-bottom: none;
    }
    .pi-supplier-name {
        font-weight: 500;
        color: #0f172a;
    }
    .pi-supplier-value {
        font-weight: 600;
        color: #156349;
    }

    /* Loading State */
    .pi-loading-state {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
        font-size: 14px;
    }

    /* Empty State */
    .pi-materials-empty {
        text-align: center;
        padding: 48px 20px;
        color: #64748b;
    }
    .pi-materials-empty svg {
        width: 48px;
        height: 48px;
        margin-bottom: 16px;
        opacity: 0.4;
    }
    .pi-materials-empty h4 {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
        margin: 0 0 8px;
    }
    .pi-materials-empty p {
        font-size: 14px;
        margin: 0 0 16px;
    }

    /* ============================================
    DOCUMENT MANAGEMENT SYSTEM
    ============================================ */
    .crm-documents-container {
        display: flex;
        gap: 0;
        height: calc(100vh - 200px);
        min-height: 600px;
        background: #fff;
        border-radius: var(--workspace-pi-radius-md);
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    /* Document Navigation Sidebar */
    .crm-doc-nav {
        width: 240px;
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        padding: 20px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        overflow-y: auto;
    }

    .crm-doc-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: var(--workspace-pi-radius-md);
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        width: 100%;
    }

    .crm-doc-nav-item:hover {
        background: #f1f5f9;
        color: #334155;
    }

    .crm-doc-nav-item.active {
        background: #1b2534;
        color: #fff;
    }

    .crm-doc-nav-item svg {
        flex-shrink: 0;
    }

    .crm-doc-nav-item span:first-of-type {
        flex: 1;
    }

    .crm-doc-count {
        background: #e2e8f0;
        color: #64748b;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        min-width: 24px;
        text-align: center;
    }

    .crm-doc-nav-item.active .crm-doc-count {
        background: rgba(255,255,255,0.2);
        color: #fff;
    }

    /* Document Content Area */
    .crm-doc-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .crm-doc-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        gap: 16px;
    }

    .crm-doc-search {
        position: relative;
        flex: 1;
        max-width: 400px;
    }

    .crm-doc-search svg {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    .crm-doc-search input {
        width: 100%;
        padding: 10px 12px 10px 40px;
        border: 1px solid #e2e8f0;
        border-radius: var(--workspace-pi-radius-md);
        font-size: 14px;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .crm-doc-search input:focus {
        outline: none;
        border-color: #1b2534;
        background: #fff;
    }

    .crm-doc-actions {
        display: flex;
        gap: 8px;
    }

    /* Documents View Container */
    .crm-documents-view {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
    }

    /* Category View Header */
    .crm-doc-view-header {
        margin-bottom: 24px;
    }

    .crm-doc-view-title {
        font-size: 20px;
        font-weight: 600;
        color: #1b2534;
        margin: 0 0 8px;
    }

    .crm-doc-view-desc {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }

    /* RECEIPTS - Row Based Format */
    .crm-receipts-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border-radius: var(--workspace-pi-radius-md);
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .crm-receipts-table th {
        background: #f8fafc;
        padding: 14px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e2e8f0;
    }

    .crm-receipts-table td {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #334155;
    }

    .crm-receipts-table tr:hover td {
        background: #f8fafc;
    }

    .crm-receipts-table tr:last-child td {
        border-bottom: none;
    }

    .crm-receipt-vendor {
        font-weight: 600;
        color: #1b2534;
    }

    .crm-receipt-amount {
        font-weight: 700;
        color: #059669;
        font-size: 15px;
    }

    .crm-receipt-date {
        color: #64748b;
        font-size: 13px;
    }

    .crm-receipt-category {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        background: #e0f2fe;
        color: #0369a1;
    }

    .crm-receipt-actions {
        display: flex;
        gap: 8px;
    }

    .crm-receipt-action {
        padding: 6px 10px;
        border: none;
        background: transparent;
        color: #64748b;
        cursor: pointer;
        border-radius: var(--workspace-pi-radius-sm);
        transition: all 0.2s ease;
    }

    .crm-receipt-action:hover {
        background: #f1f5f9;
        color: #1b2534;
    }

    /* SITE PLANS - Preview Gallery */
    .crm-site-plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 24px;
    }

    .crm-site-plan-card {
        background: #fff;
        border-radius: var(--workspace-pi-radius-md);
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .crm-site-plan-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.12);
    }

    .crm-site-plan-preview {
        position: relative;
        aspect-ratio: 4/3;
        background: #f1f5f9;
        overflow: hidden;
    }

    .crm-site-plan-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .crm-site-plan-card:hover .crm-site-plan-preview img {
        transform: scale(1.05);
    }

    .crm-site-plan-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 50%);
        opacity: 0;
        transition: opacity 0.3s ease;
        display: flex;
        align-items: flex-end;
        padding: 16px;
    }

    .crm-site-plan-card:hover .crm-site-plan-overlay {
        opacity: 1;
    }

    .crm-site-plan-overlay-actions {
        display: flex;
        gap: 8px;
    }

    .crm-site-plan-overlay-btn {
        padding: 8px 16px;
        border: none;
        border-radius: var(--workspace-pi-radius-sm);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .crm-site-plan-overlay-btn.primary {
        background: #fff;
        color: #1b2534;
    }

    .crm-site-plan-overlay-btn.secondary {
        background: rgba(255,255,255,0.2);
        color: #fff;
        backdrop-filter: blur(4px);
    }

    .crm-site-plan-info {
        padding: 16px;
    }

    .crm-site-plan-title {
        font-size: 15px;
        font-weight: 600;
        color: #1b2534;
        margin: 0 0 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .crm-site-plan-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 13px;
        color: #64748b;
    }

    .crm-site-plan-version {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        background: #f1f5f9;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    /* PHOTOS - Grid Gallery */
    .crm-photos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }

    .crm-photo-card {
        position: relative;
        aspect-ratio: 1;
        border-radius: var(--workspace-pi-radius-md);
        overflow: hidden;
        cursor: pointer;
        background: #f1f5f9;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }

    .crm-photo-card:hover {
        transform: scale(1.02);
        box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        z-index: 10;
    }

    .crm-photo-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .crm-photo-card:hover img {
        transform: scale(1.1);
    }

    .crm-photo-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 60%);
        opacity: 0;
        transition: opacity 0.3s ease;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 12px;
    }

    .crm-photo-card:hover .crm-photo-overlay {
        opacity: 1;
    }

    .crm-photo-date {
        font-size: 12px;
        color: rgba(255,255,255,0.9);
        margin-bottom: 4px;
    }

    .crm-photo-actions {
        display: flex;
        gap: 8px;
    }

    .crm-photo-action {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(4px);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .crm-photo-action:hover {
        background: #fff;
        color: #1b2534;
    }

    /* DOCUMENTS - List Format */
    .crm-documents-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .crm-document-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 20px;
        background: #fff;
        border-radius: var(--workspace-pi-radius-md);
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .crm-document-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .crm-document-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--workspace-pi-radius-md);
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .crm-document-icon.pdf { background: #fef2f2; color: #dc2626; }
    .crm-document-icon.word { background: #eff6ff; color: #2563eb; }
    .crm-document-icon.excel { background: #f0fdf4; color: #16a34a; }
    .crm-document-icon.image { background: #fef3c7; color: #d97706; }
    .crm-document-icon.zip { background: #f5f3ff; color: #7c3aed; }

    .crm-document-info {
        flex: 1;
        min-width: 0;
    }

    .crm-document-name {
        font-size: 15px;
        font-weight: 500;
        color: #1b2534;
        margin: 0 0 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .crm-document-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        color: #64748b;
    }

    .crm-document-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .crm-document-actions {
        display: flex;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .crm-document-item:hover .crm-document-actions {
        opacity: 1;
    }

    .crm-document-action {
        padding: 8px;
        border: none;
        background: transparent;
        color: #64748b;
        cursor: pointer;
        border-radius: var(--workspace-pi-radius-sm);
        transition: all 0.2s ease;
    }

    .crm-document-action:hover {
        background: #f1f5f9;
        color: #1b2534;
    }

    /* CONTRACTS & REPORTS - Card Format */
    .crm-doc-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .crm-doc-card {
        background: #fff;
        border-radius: var(--workspace-pi-radius-md);
        padding: 20px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .crm-doc-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .crm-doc-card-header {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 16px;
    }

    .crm-doc-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .crm-doc-card-title {
        font-size: 15px;
        font-weight: 600;
        color: #1b2534;
        margin: 0 0 4px;
        line-height: 1.4;
    }

    .crm-doc-card-meta {
        font-size: 13px;
        color: #64748b;
    }

    .crm-doc-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    .crm-doc-card-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .crm-doc-card-status.signed {
        background: #dcfce7;
        color: #16a34a;
    }

    .crm-doc-card-status.pending {
        background: #fef3c7;
        color: #d97706;
    }

    .crm-doc-card-status.draft {
        background: #f1f5f9;
        color: #64748b;
    }

    .crm-doc-card-actions {
        display: flex;
        gap: 8px;
    }

    /* Upload Modal */
    .crm-upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: var(--workspace-pi-radius-md);
        padding: 40px;
        text-align: center;
        transition: all 0.2s;
        position: relative;
        cursor: pointer;
        overflow: hidden;
    }

    .crm-upload-area:hover,
    .crm-upload-area.dragover {
        border-color: #1b2534;
        background: #f1f5f9;
    }

    .crm-upload-area svg {
        width: 48px;
        height: 48px;
        color: #94a3b8;
        margin-bottom: 16px;
    }

    .crm-upload-area h4 {
        font-size: 16px;
        font-weight: 600;
        color: #1b2534;
        margin: 0 0 8px;
    }

    .crm-upload-area p {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }

    .crm-upload-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        z-index: 10;
    }

    .crm-upload-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 20px;
    }

    .crm-upload-preview-item {
        position: relative;
        width: 100px;
        height: 100px;
        border-radius: var(--workspace-pi-radius-sm);
        overflow: hidden;
        background: #f1f5f9;
    }

    .crm-upload-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .crm-upload-preview-remove {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 24px;
        height: 24px;
        border: none;
        border-radius: 50%;
        background: rgba(0,0,0,0.5);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    /* Empty State */
    .crm-documents-view .crm-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
    }

    .crm-documents-view .crm-empty-state svg {
        width: 64px;
        height: 64px;
        color: #cbd5e1;
        margin-bottom: 20px;
    }

    .crm-documents-view .crm-empty-state h4 {
        font-size: 18px;
        font-weight: 600;
        color: #1b2534;
        margin: 0 0 8px;
    }

    .crm-documents-view .crm-empty-state p {
        font-size: 14px;
        color: #64748b;
        margin: 0 0 20px;
    }

    /* Document Preview Modal */
    .crm-doc-preview-modal {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.9);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
    }

    .crm-doc-preview-container {
        max-width: 90vw;
        max-height: 90vh;
        background: #fff;
        border-radius: var(--workspace-pi-radius-md);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .crm-doc-preview-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .crm-doc-preview-title {
        font-size: 16px;
        font-weight: 600;
        color: #1b2534;
    }

    .crm-doc-preview-close {
        width: 32px;
        height: 32px;
        border: none;
        background: transparent;
        color: #64748b;
        cursor: pointer;
        border-radius: var(--workspace-pi-radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .crm-doc-preview-close:hover {
        background: #f1f5f9;
        color: #1b2534;
    }

    .crm-doc-preview-content {
        flex: 1;
        overflow: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: #f8fafc;
    }

    .crm-doc-preview-content img {
        max-width: 100%;
        max-height: calc(90vh - 140px);
        object-fit: contain;
        border-radius: var(--workspace-pi-radius-sm);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    </style>
</head>
<body class="pi-job-page job-page-with-sidebar">

<!-- Mobile sidebar overlay -->
<div class="job-sidebar-overlay" id="job-sidebar-overlay" onclick="JobSidebar.close()"></div>

<div class="pi-app-layout">
    <!-- Dark App Sidebar (Global Navigation) -->
    <aside class="workspace-pi-sidebar" aria-label="Main navigation">
        <div class="workspace-pi-sidebar-brand">
        <svg class="workspace-pi-brand-logo" viewBox="0 0 80 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Map Pin Icon -->
            <path d="M10 2C6.13 2 3 5.13 3 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="#1b2534"/>
            <!-- PI Text -->
            <text x="22" y="17" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#1b2534">PI</text>
        </svg>
        </div>
    
        <!-- ── EXACT copy of nav from your beautiful settings page ── -->
        <nav class="workspace-pi-sidebar-nav">
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/" title="Home" data-tooltip="Home">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link active" href="https://planningindex.co.uk/workspace/" title="Workspace" data-tooltip="Workspace">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/tasks/" title="Tasks" data-tooltip="Tasks">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/proposals/" title="Proposals" data-tooltip="Proposals">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/calendar" title="Calendar" data-tooltip="Calendar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/materials" title="Materials" data-tooltip="Materials">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="6" width="22" height="4" rx="1"/><rect x="1" y="14" width="22" height="4" rx="1"/><line x1="5" y1="10" x2="5" y2="14"/><line x1="19" y1="10" x2="19" y2="14"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/team" title="Team" data-tooltip="Team">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <!-- Smaller person (back / left) -->
            <circle cx="7" cy="8" r="3"/>
            <path d="M2 21a5 5 0 0 1 8 0"/>

            <!-- Larger person (front / right) -->
            <circle cx="16" cy="7" r="4"/>
            <path d="M10 21a6 6 0 0 1 12 0"/>
        </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/expenses" title="Expenses" data-tooltip="Expenses">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/site-files" title="Site Files" data-tooltip="Site Files">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/subcontractors" title="Subcontractors" data-tooltip="Subcontractors">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="7"  r="4"/>
    <path   d="M5.5 21a6.5 6.5 0 0 1 13 0"/>
    </svg>
        </a>
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/main-search/" title="Search" data-tooltip="Search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </a>
        <div style="flex-grow: 1; min-height: 120px;"></div> <!-- spacer before settings - exact same as settings page -->
        <a class="workspace-pi-nav-link" href="https://planningindex.co.uk/workspace/settings/" title="Settings" data-tooltip="Settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
        </a>
        </nav>
    </aside>

    <!-- White Job Navigation Sidebar -->
    <aside class="job-nav-sidebar" id="job-nav-sidebar" aria-label="Job navigation">
        <!-- Sidebar Header with Job Info -->
        <div class="job-sidebar-header">
            <a href="<?php echo esc_url($workspace_url); ?>" class="job-sidebar-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Jobs
            </a>
            <div class="job-sidebar-job-info">
                <span class="job-sidebar-ref" id="job-sidebar-ref"><?php echo esc_html(get_the_title()); ?></span>
            </div>
        </div>

        <!-- Main Navigation Sections -->
        <nav class="job-nav-sections">
            <!-- Core Section -->
            <div class="job-nav-section">
                <div class="job-nav-section-title">Core</div>
                <div class="job-nav-list">
                    <button class="job-nav-item active" data-job-tab="overview" data-tooltip="Overview">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span class="job-nav-label">Overview</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="costs" data-tooltip="Costs">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span class="job-nav-label">Costs</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="materials" data-tooltip="Materials">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="22" height="4" rx="1"/><rect x="1" y="14" width="22" height="4" rx="1"/><line x1="5" y1="10" x2="5" y2="14"/><line x1="19" y1="10" x2="19" y2="14"/></svg>
                        <span class="job-nav-label">Materials</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="schedule" data-tooltip="Schedule">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span class="job-nav-label">Schedule</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="tasks" data-tooltip="Tasks">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        <span class="job-nav-label">Tasks</span>
                        <span class="job-nav-badge job-tasks-count" style="display:none">0</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="client" data-tooltip="Client & Site">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="job-nav-label">Client & Site</span>
                    </button>
                </div>
            </div>

            <!-- Communications Section -->
            <div class="job-nav-section">
                <div class="job-nav-section-title">Communications</div>
                <div class="job-nav-list">
                    <button class="job-nav-item" data-job-tab="communications" data-tooltip="Communications">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span class="job-nav-label">Email & Comms</span>
                        <span class="job-nav-badge crm-kpi-communications" style="display:none">0</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="documents" data-tooltip="Documents">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span class="job-nav-label">Documents</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="rfi-submittals" data-tooltip="RFI & Submittals">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span class="job-nav-label">RFI & Submittals</span>
                        <span class="job-nav-badge crm-kpi-open-rfi" style="display:none">0</span>
                    </button>
                </div>
            </div>

            <!-- Operations Section -->
            <div class="job-nav-section">
                <div class="job-nav-section-title">Operations</div>
                <div class="job-nav-list">
                    <button class="job-nav-item" data-job-tab="team" data-tooltip="Team & Timesheets">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <span class="job-nav-label">Team & Timesheets</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="equipment" data-tooltip="Equipment">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/></svg>
                        <span class="job-nav-label">Equipment</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="subcontractors" data-tooltip="Subcontractors">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>
                        <span class="job-nav-label">Subcontractors</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="daily-reports" data-tooltip="Daily Reports">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span class="job-nav-label">Daily Reports</span>
                    </button>
                </div>
            </div>

            <!-- Quality & Safety Section -->
            <div class="job-nav-section">
                <div class="job-nav-section-title">Quality & Safety</div>
                <div class="job-nav-list">
                    <button class="job-nav-item" data-job-tab="safety" data-tooltip="Safety">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span class="job-nav-label">Safety</span>
                        <span class="job-nav-badge crm-kpi-safety-incidents" style="display:none">0</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="quality" data-tooltip="Quality">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg>
                        <span class="job-nav-label">Quality Control</span>
                        <span class="job-nav-badge crm-kpi-open-snags" style="display:none">0</span>
                    </button>
                </div>
            </div>

            <!-- Financial Section -->
            <div class="job-nav-section">
                <div class="job-nav-section-title">Financial</div>
                <div class="job-nav-list">
                    <button class="job-nav-item" data-job-tab="change-orders" data-tooltip="Change Orders">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <span class="job-nav-label">Change Orders</span>
                        <span class="job-nav-badge crm-kpi-pending-co" style="display:none">0</span>
                    </button>
                    <button class="job-nav-item" data-job-tab="invoicing" data-tooltip="Invoicing">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><path d="M9 14l2 2 4-4"/></svg>
                        <span class="job-nav-label">Invoicing</span>
                    </button>
                </div>
            </div>

            <!-- Location Section -->
            <div class="job-nav-section">
                <div class="job-nav-section-title">Location</div>
                <div class="job-nav-list">
                    <button class="job-nav-item" data-job-tab="site-map" data-tooltip="Site Map">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                        <span class="job-nav-label">Site Map</span>
                    </button>
                </div>
            </div>

            <!-- Delete Job Section -->
            <div class="job-nav-section job-nav-section-danger">
                <div class="job-nav-list">
                    <button class="job-nav-item job-nav-delete" id="job-sidebar-delete-btn" data-tooltip="Delete Job">
                        <svg class="job-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        <span class="job-nav-label">Delete Job</span>
                    </button>
                </div>
            </div>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <div class="job-main-content">
        <!-- Top Header with Job Info -->
        <header class="job-top-header">
            <div class="job-top-header-left">
                <!-- Mobile toggle button -->
                <button class="job-mobile-toggle" id="job-mobile-toggle" onclick="JobSidebar.toggle()" aria-label="Toggle navigation">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <!-- Job Quick Info (visible in header) -->
                <div class="job-header-quick-info">
                    <span class="job-header-badge" id="job-header-ref"><?php echo esc_html(get_the_title()); ?></span>
                    <span class="job-header-address" id="job-header-address">Loading address...</span>
                </div>

                <!-- Search Box -->
                <div class="job-top-header-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="search" id="job-global-search" placeholder="Search this job..." aria-label="Search job" />
                </div>
            </div>

            <div class="job-top-header-right">
                <!-- Status dropdown -->
                <div class="pi-stage-control" style="margin-right: 16px;">
                    <select id="job-status-header" class="pi-stage-select" style="font-size: 13px; padding: 6px 12px;">
                        <option value="planning">Planning</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <?php echo do_shortcode('[pi_profile_dropdown]'); ?>
            </div>
        </header>

        <main class="pi-content">
            <div class="pi-content-inner">
                <?php wp_body_open(); ?>
                <?php if (!function_exists('pi_jobs_user_owns_job') || !pi_jobs_user_owns_job($job_id)) : ?>
                    <div class="pi-access-denied">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                        <h2>Access Denied</h2>
                        <p>You don't have permission to view this job.</p>
                        <a href="<?php echo esc_url($workspace_url); ?>" class="pi-btn pi-btn-primary">Return to Workspace</a>
                    </div>
                <?php else : ?>
                    <div id="pi-job-single" data-job-id="<?php echo esc_attr($job_id); ?>">
                        <!-- Job Content Panels - Controlled by Sidebar Navigation -->
                        <div class="job-content-panels">
                            <!-- Communications Panel -->
                            <div class="job-tab-panel" id="job-tab-communications">
                                <div class="crm-section">
                                    <div class="crm-section-header">
                                        <h3>Communications</h3>
                                        <div class="crm-section-actions">
                                            <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('email')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                                Compose Email
                                            </button>
                                        </div>
                                    </div>
                                    <div id="crm-communications-list" class="crm-comm-list">
                                        <div class="crm-empty-state">Loading communications...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents Panel -->
                            <div class="job-tab-panel" id="job-tab-documents">
                                <div class="crm-documents-container">
                                    <!-- Document Category Navigation -->
                                    <div class="crm-doc-nav">
                                        <button class="crm-doc-nav-item active" data-doc-category="all">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <span>All Files</span>
                                            <span class="crm-doc-count" id="doc-count-all">0</span>
                                        </button>
                                        <button class="crm-doc-nav-item" data-doc-category="receipts">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                            <span>Receipts</span>
                                            <span class="crm-doc-count" id="doc-count-receipts">0</span>
                                        </button>
                                        <button class="crm-doc-nav-item" data-doc-category="site-plans">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                                            <span>Site Plans</span>
                                            <span class="crm-doc-count" id="doc-count-site-plans">0</span>
                                        </button>
                                        <button class="crm-doc-nav-item" data-doc-category="photos">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            <span>Site Photos</span>
                                            <span class="crm-doc-count" id="doc-count-photos">0</span>
                                        </button>
                                        <button class="crm-doc-nav-item" data-doc-category="contracts">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                            <span>Contracts</span>
                                            <span class="crm-doc-count" id="doc-count-contracts">0</span>
                                        </button>
                                        <button class="crm-doc-nav-item" data-doc-category="reports">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                            <span>Reports</span>
                                            <span class="crm-doc-count" id="doc-count-reports">0</span>
                                        </button>
                                        <button class="crm-doc-nav-item" data-doc-category="general">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <span>Other Documents</span>
                                            <span class="crm-doc-count" id="doc-count-general">0</span>
                                        </button>
                                    </div>

                                    <!-- Document Content Area -->
                                    <div class="crm-doc-content">
                                        <!-- Header with Search and Upload -->
                                        <div class="crm-doc-header">
                                            <div class="crm-doc-search">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                                <input type="text" id="crm-doc-search" placeholder="Search documents..." />
                                            </div>
                                            <div class="crm-doc-actions">
                                                <button class="pi-btn pi-btn-secondary" onclick="CRM_Modal.open('upload-document', 'all')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                    Upload
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Dynamic Document Views -->
                                        <div id="crm-documents-view" class="crm-documents-view">
                                            <div class="crm-empty-state">Loading documents...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Team & Timesheets Panel -->
                            <div class="job-tab-panel" id="job-tab-team">
                                <div id="pi-team-timesheets" class="pi-team-timesheets-container">
                                    <!-- Header -->
                                    <div class="tt-header">
                                        <div class="tt-header-title">
                                            <div class="tt-header-icon">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4"/>
                                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                                </svg>
                                            </div>
                                            <h1>Team & Timesheets</h1>
                                        </div>
                                        <div class="tt-header-actions">
                                            <div class="tt-search-container">
                                                <svg class="tt-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="11" cy="11" r="8"/>
                                                    <path d="m21 21-4.35-4.35"/>
                                                </svg>
                                                <input type="text" class="tt-search-input" id="tt-search" placeholder="Search team or timesheets...">
                                            </div>
                                            <button class="tt-btn tt-btn-primary" onclick="TeamApp.openAddMemberModal()">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                                </svg>
                                                Add Member
                                            </button>
                                            <button class="tt-btn tt-btn-secondary" onclick="TeamApp.openNewEntryModal()">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <polyline points="12 6 12 12 16 14"/>
                                                </svg>
                                                New Timesheet Entry
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Segmented Tabs -->
                                    <div class="tt-tabs-wrapper">
                                        <div class="tt-tabs">
                                            <button class="tt-tab active" data-tab="overview">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="3" width="7" height="7"/>
                                                    <rect x="14" y="3" width="7" height="7"/>
                                                    <rect x="14" y="14" width="7" height="7"/>
                                                    <rect x="3" y="14" width="7" height="7"/>
                                                </svg>
                                                Overview
                                            </button>
                                            <button class="tt-tab" data-tab="team">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4"/>
                                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                                </svg>
                                                Team Members
                                            </button>
                                            <button class="tt-tab" data-tab="timesheets">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <polyline points="12 6 12 12 16 14"/>
                                                </svg>
                                                Timesheets
                                                <span class="tt-tab-badge" id="tt-pending-badge" style="display: none;">0</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Tab Panels -->
                                    <div class="tt-tab-panel active" id="tt-panel-overview">
                                        <div class="tt-skeleton tt-skeleton-card"></div>
                                        <div class="tt-skeleton tt-skeleton-card"></div>
                                    </div>
                                    
                                    <div class="tt-tab-panel" id="tt-panel-team">
                                        <div class="tt-skeleton tt-skeleton-card"></div>
                                    </div>
                                    
                                    <div class="tt-tab-panel" id="tt-panel-timesheets">
                                        <div class="tt-skeleton tt-skeleton-card"></div>
                                    </div>

                                    <!-- Clock Button -->
                                    <button class="tt-clock-btn clocked-out" onclick="TeamApp.handleClockAction()">
                                        <span class="tt-clock-pulse"></span>
                                        <span id="tt-clock-text">Clock In</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Safety Panel -->
                            <div class="job-tab-panel" id="job-tab-safety">
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
                                </div>
                            </div>

                            <!-- Quality Panel -->
                            <div class="job-tab-panel" id="job-tab-quality">
                                <div class="crm-quality-stats">
                                    <div class="crm-quality-stat">
                                        <div class="crm-quality-stat-value crm-kpi-quality-open">0</div>
                                        <div class="crm-quality-stat-label">Open Snags</div>
                                    </div>
                                    <div class="crm-quality-stat">
                                        <div class="crm-quality-stat-value crm-kpi-quality-high">0</div>
                                        <div class="crm-quality-stat-label">High Priority</div>
                                    </div>
                                </div>
                                <div class="crm-section">
                                    <div class="crm-section-header">
                                        <h3>Quality Snags & Punch List</h3>
                                        <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('quality-snag')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            Add Snag
                                        </button>
                                    </div>
                                    <div id="crm-quality-snags-list" class="crm-quality-snags-list">
                                        <div class="crm-empty-state">Loading quality items...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Change Orders Panel -->
                            <div class="job-tab-panel" id="job-tab-change-orders">
                                <div class="crm-section">
                                    <div class="crm-section-header">
                                        <h3>Change Orders & Variations</h3>
                                        <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('change-order')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            Create Change Order
                                        </button>
                                    </div>
                                    <div id="crm-change-orders-list" class="crm-co-list">
                                        <div class="crm-empty-state">Loading change orders...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoicing Panel -->
                            <div class="job-tab-panel" id="job-tab-invoicing">
                                <div class="crm-invoice-summary">
                                    <div class="crm-inv-summary-card">
                                        <div class="crm-inv-summary-value" id="crm-inv-total-billed">£0.00</div>
                                        <div class="crm-inv-summary-label">Total Billed</div>
                                    </div>
                                    <div class="crm-inv-summary-card">
                                        <div class="crm-inv-summary-value" id="crm-inv-total-paid">£0.00</div>
                                        <div class="crm-inv-summary-label">Total Paid</div>
                                    </div>
                                    <div class="crm-inv-summary-card">
                                        <div class="crm-inv-summary-value" id="crm-inv-outstanding">£0.00</div>
                                        <div class="crm-inv-summary-label">Outstanding</div>
                                    </div>
                                    <div class="crm-inv-summary-card">
                                        <div class="crm-inv-summary-value" id="crm-inv-retention">£0.00</div>
                                        <div class="crm-inv-summary-label">Retention</div>
                                    </div>
                                </div>
                                <div class="crm-section">
                                    <div class="crm-section-header">
                                        <h3>Invoices</h3>
                                        <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('invoice')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            Create Invoice
                                        </button>
                                    </div>
                                    <div id="crm-invoices-list" class="crm-invoices-list">
                                        <div class="crm-empty-state">Loading invoices...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- RFI & Submittals Panel - Comprehensive Dashboard -->
                            <div class="job-tab-panel" id="job-tab-rfi-submittals">
                                <div class="rfi-submittals-dashboard">
                                    <!-- Summary Metrics Bar -->
                                    <div class="rfi-metrics-bar" id="rfi-dashboard-metrics">
                                        <div class="rfi-metric-card urgent">
                                            <div class="rfi-metric-value" id="metric-total-open">0</div>
                                            <div class="rfi-metric-label">Total Open RFIs</div>
                                        </div>
                                        <div class="rfi-metric-card warning">
                                            <div class="rfi-metric-value" id="metric-overdue-rfis">0</div>
                                            <div class="rfi-metric-label">Overdue RFIs</div>
                                        </div>
                                        <div class="rfi-metric-card info">
                                            <div class="rfi-metric-value" id="metric-pending-submittals">0</div>
                                            <div class="rfi-metric-label">Pending Submittals</div>
                                        </div>
                                        <div class="rfi-metric-card success">
                                            <div class="rfi-metric-value" id="metric-approved-week">0</div>
                                            <div class="rfi-metric-label">Approved This Week</div>
                                        </div>
                                        <div class="rfi-metric-card">
                                            <div class="rfi-metric-value" id="metric-avg-response">0</div>
                                            <div class="rfi-metric-label">Avg Response (days)</div>
                                        </div>
                                    </div>

                                    <!-- Quick Actions Bar -->
                                    <div class="rfi-quick-actions">
                                        <button class="rfi-btn rfi-btn-primary" onclick="CRM_Modal.open('rfi')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                                            </svg>
                                            Create RFI
                                        </button>
                                        <button class="rfi-btn rfi-btn-primary" onclick="CRM_Modal.open('submittal')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                <polyline points="14 2 14 8 20 8"/>
                                            </svg>
                                            Submit Submittal
                                        </button>
                                        <button class="rfi-btn rfi-btn-secondary" onclick="RFI_Submittals.sendBulkReminder()">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                                            </svg>
                                            Send Reminder
                                        </button>
                                        <button class="rfi-btn rfi-btn-secondary" onclick="RFI_Submittals.exportLog()">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                <polyline points="7 10 12 15 17 10"/>
                                                <line x1="12" y1="15" x2="12" y2="3"/>
                                            </svg>
                                            Export Log
                                        </button>
                                    </div>

                                    <!-- Dashboard Tabs -->
                                    <div class="rfi-dashboard-tabs">
                                        <button class="rfi-tab-btn active" data-tab="all" onclick="RFI_Submittals.switchTab('all')">
                                            All Items
                                            <span class="rfi-tab-badge" id="tab-badge-all">0</span>
                                        </button>
                                        <button class="rfi-tab-btn" data-tab="rfi" onclick="RFI_Submittals.switchTab('rfi')">
                                            RFIs
                                            <span class="rfi-tab-badge" id="tab-badge-rfi">0</span>
                                        </button>
                                        <button class="rfi-tab-btn" data-tab="submittals" onclick="RFI_Submittals.switchTab('submittals')">
                                            Submittals
                                            <span class="rfi-tab-badge" id="tab-badge-submittals">0</span>
                                        </button>
                                        <button class="rfi-tab-btn" data-tab="overdue" onclick="RFI_Submittals.switchTab('overdue')">
                                            Overdue
                                            <span class="rfi-tab-badge" id="tab-badge-overdue">0</span>
                                        </button>
                                        <button class="rfi-tab-btn" data-tab="reports" onclick="RFI_Submittals.switchTab('reports')">
                                            Reports
                                        </button>
                                    </div>

                                    <!-- Unified List Container -->
                                    <div class="rfi-table-container" id="rfi-unified-list">
                                        <div class="rfi-table-header">
                                            <div class="rfi-table-title" id="rfi-list-title">All RFI & Submittals</div>
                                            <div class="rfi-table-toolbar">
                                                <div class="rfi-search-box">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="11" cy="11" r="8"/>
                                                        <path d="m21 21-4.35-4.35"/>
                                                    </svg>
                                                    <input type="text" id="rfi-search-input" placeholder="Search RFIs & Submittals..." onkeyup="RFI_Submittals.handleSearch(this.value)">
                                                </div>
                                                <div class="rfi-filter-dropdown">
                                                    <button class="rfi-filter-btn" onclick="RFI_Submittals.toggleFilterMenu()">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                                        </svg>
                                                        Filter
                                                    </button>
                                                    <div class="rfi-filter-menu" id="rfi-filter-menu">
                                                        <div class="rfi-filter-option active" data-filter="all" onclick="RFI_Submittals.setFilter('all')">All Statuses</div>
                                                        <div class="rfi-filter-option" data-filter="open" onclick="RFI_Submittals.setFilter('open')">Open</div>
                                                        <div class="rfi-filter-option" data-filter="pending" onclick="RFI_Submittals.setFilter('pending')">Pending</div>
                                                        <div class="rfi-filter-option" data-filter="approved" onclick="RFI_Submittals.setFilter('approved')">Approved</div>
                                                        <div class="rfi-filter-option" data-filter="overdue" onclick="RFI_Submittals.setFilter('overdue')">Overdue</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Bulk Actions Toolbar -->
                                        <div class="rfi-bulk-actions" id="rfi-bulk-actions" style="display:none;">
                                            <div class="rfi-bulk-actions-left">
                                                <span class="rfi-selected-count"><span id="rfi-selected-count">0</span> selected</span>
                                                <div class="rfi-bulk-divider"></div>
                                                <button class="rfi-bulk-btn rfi-bulk-delete" onclick="RFI_Submittals.bulkDelete()">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="3 6 5 6 21 6"/>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                                <div class="rfi-bulk-dropdown">
                                                    <button class="rfi-bulk-btn" onclick="RFI_Submittals.toggleBulkStatusMenu()">
                                                        Change Status
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="m6 9 6 6 6-6"/>
                                                        </svg>
                                                    </button>
                                                    <div class="rfi-bulk-menu" id="rfi-bulk-status-menu">
                                                        <div class="rfi-bulk-menu-title">Set Status To:</div>
                                                        <div class="rfi-bulk-option" onclick="RFI_Submittals.bulkChangeStatus('open')">Open</div>
                                                        <div class="rfi-bulk-option" onclick="RFI_Submittals.bulkChangeStatus('pending')">Pending</div>
                                                        <div class="rfi-bulk-option" onclick="RFI_Submittals.bulkChangeStatus('approved')">Approved</div>
                                                        <div class="rfi-bulk-option" onclick="RFI_Submittals.bulkChangeStatus('closed')">Closed</div>
                                                        <div class="rfi-bulk-option" onclick="RFI_Submittals.bulkChangeStatus('rejected')">Rejected</div>
                                                    </div>
                                                </div>
                                                <button class="rfi-bulk-btn" onclick="RFI_Submittals.bulkExport()">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                        <polyline points="7 10 12 15 17 10"/>
                                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                                    </svg>
                                                    Export
                                                </button>
                                            </div>
                                            <button class="rfi-bulk-btn rfi-bulk-clear" onclick="RFI_Submittals.clearSelection()">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                                </svg>
                                                Clear
                                            </button>
                                        </div>
                                        
                                        <table class="rfi-data-table" id="rfi-data-table">
                                            <thead>
                                                <tr>
                                                    <th><input type="checkbox" id="rfi-select-all" onchange="RFI_Submittals.toggleSelectAll()"></th>
                                                    <th class="sortable" onclick="RFI_Submittals.sort('number')">Number</th>
                                                    <th class="sortable" onclick="RFI_Submittals.sort('title')">Title</th>
                                                    <th class="sortable" onclick="RFI_Submittals.sort('type')">Type</th>
                                                    <th class="sortable" onclick="RFI_Submittals.sort('status')">Status</th>
                                                    <th class="sortable" onclick="RFI_Submittals.sort('priority')">Priority</th>
                                                    <th>Ball in Court</th>
                                                    <th class="sortable" onclick="RFI_Submittals.sort('due_date')">Due Date</th>
                                                    <th>Days Open</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rfi-table-body">
                                                <tr>
                                                    <td colspan="10">
                                                        <div class="rfi-empty-state">
                                                            <div class="rfi-empty-icon">
                                                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                                    <circle cx="12" cy="12" r="10"/>
                                                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                                                </svg>
                                                            </div>
                                                            <div class="rfi-empty-title">No RFIs or Submittals</div>
                                                            <div class="rfi-empty-text">Create your first RFI or Submittal to get started with tracking project communications.</div>
                                                            <button class="rfi-btn rfi-btn-primary" onclick="CRM_Modal.open('rfi')">Create RFI</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Reports Panel (Hidden by default) -->
                                    <div class="rfi-table-container" id="rfi-reports-panel" style="display:none;">
                                        <div class="rfi-table-header">
                                            <div class="rfi-table-title">RFI & Submittal Reports</div>
                                        </div>
                                        <div class="rfi-reports-grid">
                                            <div class="rfi-report-card">
                                                <div class="rfi-report-title">RFI Response Time by Assignee</div>
                                                <div class="rfi-chart-placeholder" id="rfi-response-time-chart">
                                                    Loading...
                                                </div>
                                            </div>
                                            <div class="rfi-report-card">
                                                <div class="rfi-report-title">RFI Volume Trends</div>
                                                <div class="rfi-chart-placeholder" id="rfi-volume-chart">
                                                    Loading...
                                                </div>
                                            </div>
                                            <div class="rfi-report-card">
                                                <div class="rfi-report-title">Submittal Turnaround by Reviewer</div>
                                                <div class="rfi-chart-placeholder" id="submittal-turnaround-chart">
                                                    Loading...
                                                </div>
                                            </div>
                                            <div class="rfi-report-card">
                                                <div class="rfi-report-title">Approval Rate</div>
                                                <div class="rfi-chart-placeholder" id="approval-rate-chart">
                                                    Loading...
                                                </div>
                                            </div>
                                            <div class="rfi-report-card">
                                                <div class="rfi-report-title">Overdue Items by Age</div>
                                                <div class="rfi-chart-placeholder" id="overdue-aging-chart">
                                                    Loading...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detail Panel Container -->
                            <div id="rfi-detail-panel-container"></div>
                            <!-- Modal Container -->
                            <div id="rfi-modal-container"></div>

                            <!-- Daily Reports Panel -->
                            <div class="job-tab-panel" id="job-tab-daily-reports">
                                <?php echo do_shortcode('[pi_daily_reports job_id="' . get_the_ID() . '"]'); ?>
                            </div>

                            <!-- Equipment Panel -->
                            <div class="job-tab-panel" id="job-tab-equipment">
                                <!-- Equipment Management Container - Populated by PI_Equipment JS -->
                                <div id="pi-equipment-loading" class="pi-equipment-loading">
                                    <div class="pi-equipment-spinner"></div>
                                    <p>Loading equipment management...</p>
                                </div>
                            </div>

                            <!-- Subcontractors Panel -->
                            <div class="job-tab-panel" id="job-tab-subcontractors">
                                <div class="crm-section">
                                    <div class="crm-section-header">
                                        <h3>Subcontractors</h3>
                                        <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('subcontractor')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            Add Subcontractor
                                        </button>
                                    </div>
                                    <div id="crm-subcontractors-list" class="crm-subcontractors-list">
                                        <div class="crm-empty-state">Loading subcontractors...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Site Map Panel -->
                            <div class="job-tab-panel" id="job-tab-site-map">
                                <div class="crm-section">
                                    <div class="crm-section-header">
                                        <h3>Site Location & Map</h3>
                                        <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('site-location')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                                            Set Location
                                        </button>
                                    </div>
                                    <div id="crm-map-container">
                                        <div class="crm-empty-state">No site location set</div>
                                    </div>
                                </div>
                            </div>
                        <!-- Hidden job data elements for sidebar/header sync -->
                        <div id="pi-job-code" style="display:none"><?php echo esc_html(get_the_title()); ?></div>
                        <div id="pi-job-address" style="display:none">Loading...</div>
                        <div id="pi-job-dates" style="display:none">—</div>

                        <!-- Overview Panel -->
                        <div class="job-tab-panel active" id="job-tab-overview">
                            <div class="pi-kpi-grid">
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                                <div class="pi-kpi-content"><span class="pi-kpi-label">Progress</span><span class="pi-kpi-value" id="pi-job-progress">0%</span></div>
                            </div>
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                                <div class="pi-kpi-content"><span class="pi-kpi-label">Start Date</span><span class="pi-kpi-value" id="pi-job-start">—</span></div>
                            </div>
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                <div class="pi-kpi-content"><span class="pi-kpi-label">Target Finish</span><span class="pi-kpi-value" id="pi-job-end">—</span></div>
                            </div>
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                                <div class="pi-kpi-content"><span class="pi-kpi-label">Crew</span><span class="pi-kpi-value" id="pi-job-crew">—</span></div>
                            </div>
                            </div>

                            <div class="pi-lead-grid pi-overview-layout">
                                <div class="pi-lead-col-main">
                                    <div class="pi-tabs-container">
                                        <div class="pi-tabs-content pi-overview-content">
                                        <div class="pi-tab-panel active" id="tab-overview" role="tabpanel">
                                            <div class="pi-planning-card">
                                                <div class="pi-card-header"><h3>Job Details</h3></div>
                                                <div class="pi-card-body" id="pi-job-overview">Loading...</div>
                                            </div>
                                            <div class="pi-notes-card pi-job-notes">
                                                <div class="pi-card-header"><h3>Notes</h3></div>
                                                <div class="pi-card-body">
                                                    <form id="pi-job-notes-form" class="pi-job-notes-form">
                                                        <textarea name="notes" class="pi-job-notes-textarea" placeholder="Site notes, instructions, changes..."></textarea>
                                                        <div class="pi-form-actions pi-job-notes-actions">
                                                            <button type="submit" class="pi-btn pi-btn-primary pi-job-notes-btn">Save Notes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- /.pi-tabs-content -->
                                </div><!-- /.pi-tabs-container -->
                            </div><!-- /.pi-lead-col-main -->
                            <div class="pi-lead-col-sidebar pi-overview-sidebar">
                                <div class="pi-activity-card">
                                    <div class="pi-card-header"><h3>Activity</h3></div>
                                    <div class="pi-card-body">
                                        <div class="pi-timeline" id="pi-job-activity">Loading...</div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.pi-overview-layout -->
                    </div><!-- /#job-tab-overview -->

                        <!-- Full Page Tab Panels - These are shown/hidden via JS when main tabs are clicked -->
                        <div class="job-tab-panel" id="job-tab-costs">
                            <!-- Breadcrumb Navigation -->
                            <div class="pi-costs-breadcrumb">
                                <a href="<?php echo esc_url($workspace_url); ?>">Workspace</a>
                                <span class="pi-breadcrumb-sep">›</span>
                                <a href="<?php echo esc_url($workspace_url); ?>jobs/">Jobs</a>
                                <span class="pi-breadcrumb-sep">›</span>
                                <span class="pi-breadcrumb-job-name">Job</span>
                                <span class="pi-breadcrumb-sep">›</span>
                                <span>Costs</span>
                                <a href="/workspace/expenses?job_id=<?php echo esc_attr($job_id); ?>&tab=ledger" class="pi-global-ledger-link">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    View in Global Ledger
                                </a>
                            </div>

                            <!-- Financial Summary Cards -->
                            <div class="pi-costs-kpi-grid">
                                <div class="pi-costs-kpi-card">
                                    <div class="pi-costs-kpi-icon pi-costs-kpi-icon-red">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    </div>
                                    <div class="pi-costs-kpi-content">
                                        <span class="pi-costs-kpi-label">Total Expenses</span>
                                        <span class="pi-costs-kpi-value" id="pi-job-total-expenses">£0.00</span>
                                    </div>
                                </div>
                                <div class="pi-costs-kpi-card">
                                    <div class="pi-costs-kpi-icon pi-costs-kpi-icon-blue">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                    </div>
                                    <div class="pi-costs-kpi-content">
                                        <span class="pi-costs-kpi-label">Budget vs Actual</span>
                                        <span class="pi-costs-kpi-value" id="pi-job-budget-status">No Budget</span>
                                    </div>
                                </div>
                                <div class="pi-costs-kpi-card">
                                    <div class="pi-costs-kpi-icon pi-costs-kpi-icon-green">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    </div>
                                    <div class="pi-costs-kpi-content">
                                        <span class="pi-costs-kpi-label">Quote Value</span>
                                        <span class="pi-costs-kpi-value" id="pi-job-quote-value">£0.00</span>
                                    </div>
                                </div>
                                <div class="pi-costs-kpi-card">
                                    <div class="pi-costs-kpi-icon pi-costs-kpi-icon-amber">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <div class="pi-costs-kpi-content">
                                        <span class="pi-costs-kpi-label">Pending Costs</span>
                                        <span class="pi-costs-kpi-value" id="pi-job-pending-costs">£0.00</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Budget Progress Bar -->
                            <div class="pi-budget-progress-container" id="pi-budget-container" style="display: none;">
                                <div class="pi-budget-progress-header">
                                    <span>Budget Consumption</span>
                                    <span id="pi-budget-percent">0%</span>
                                </div>
                                <div class="pi-budget-progress-track">
                                    <div class="pi-budget-progress-fill" id="pi-budget-progress-fill" style="width: 0%"></div>
                                </div>
                                <p class="pi-budget-progress-text">
                                    <span id="pi-budget-spent">£0.00</span> of <span id="pi-budget-total">£0.00</span> budget used
                                    <span class="pi-profit-margin" id="pi-profit-margin"></span>
                                </p>
                            </div>

                            <!-- Quick Actions Bar -->
                            <div class="pi-quick-actions-bar">
                                <button class="pi-btn pi-btn-primary" id="pi-add-expense-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Expense
                                </button>
                                <button class="pi-btn pi-btn-secondary" id="pi-log-mileage-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                                    Log Mileage
                                </button>
                                <button class="pi-btn pi-btn-secondary" id="pi-add-subcontractor-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                    Add Subcontractor
                                </button>
                            </div>

                            <!-- Job Expenses Table -->
                            <div class="pi-costs-section">
                                <div class="pi-costs-section-header">
                                    <h3>Job Expenses</h3>
                                    <div class="pi-costs-section-filters">
                                        <input type="text" id="pi-expense-search" placeholder="Search expenses..." class="pi-form-control pi-form-control-sm">
                                        <select id="pi-expense-category" class="pi-form-control pi-form-control-sm">
                                            <option value="">All Categories</option>
                                        </select>
                                        <select id="pi-expense-status" class="pi-form-control pi-form-control-sm">
                                            <option value="">All Statuses</option>
                                            <option value="approved">Approved</option>
                                            <option value="pending">Pending</option>
                                            <option value="draft">Draft</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pi-data-table-wrapper">
                                    <div id="pi-job-expenses-container">
                                        <div class="pi-loading-state">Loading expenses...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mileage Section -->
                            <div class="pi-costs-section pi-mileage-section">
                                <div class="pi-costs-section-header">
                                    <h3>Mileage & Travel</h3>
                                    <div class="pi-mileage-stats">
                                        <span id="pi-mileage-total">0 miles</span>
                                        <span id="pi-mileage-claim">£0.00 claim</span>
                                    </div>
                                </div>
                                <div id="pi-job-mileage-container">
                                    <div class="pi-loading-state">Loading mileage...</div>
                                </div>
                            </div>

                            <!-- Cost Code Breakdown -->
                            <div class="pi-costs-section pi-cost-codes-section">
                                <div class="pi-costs-section-header">
                                    <h3>Budget Breakdown by Category</h3>
                                    <button class="pi-btn pi-btn-ghost pi-btn-sm" id="pi-add-cost-code-btn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        Add Category
                                    </button>
                                </div>
                                <div id="pi-cost-codes-container">
                                    <div class="pi-loading-state">Loading breakdown...</div>
                                </div>
                            </div>
                                        </div>

                                        <div class="job-tab-panel" id="job-tab-materials">
                                            <!-- Breadcrumb Navigation -->
                            <div class="pi-materials-breadcrumb">
                                <a href="<?php echo esc_url($workspace_url); ?>">Workspace</a>
                                <span class="pi-breadcrumb-sep">›</span>
                                <a href="<?php echo esc_url($workspace_url); ?>jobs/">Jobs</a>
                                <span class="pi-breadcrumb-sep">›</span>
                                <span class="pi-breadcrumb-job-name">Job</span>
                                <span class="pi-breadcrumb-sep">›</span>
                                <span>Materials</span>
                                <a href="/workspace/materials" class="pi-global-library-link">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    View Global Materials
                                </a>
                            </div>

                            <!-- Quick Stats Bar -->
                            <div class="pi-materials-stats-grid">
                                <div class="pi-materials-stat-card">
                                    <div class="pi-materials-stat-icon pi-materials-stat-icon-blue">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                                    </div>
                                    <div class="pi-materials-stat-content">
                                        <span class="pi-materials-stat-label">BOM Items</span>
                                        <span class="pi-materials-stat-value" id="pi-materials-bom-count">0</span>
                                    </div>
                                </div>
                                <div class="pi-materials-stat-card">
                                    <div class="pi-materials-stat-icon pi-materials-stat-icon-amber">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    </div>
                                    <div class="pi-materials-stat-content">
                                        <span class="pi-materials-stat-label">Pending POs</span>
                                        <span class="pi-materials-stat-value" id="pi-materials-pending-pos">0</span>
                                    </div>
                                </div>
                                <div class="pi-materials-stat-card">
                                    <div class="pi-materials-stat-icon pi-materials-stat-icon-green">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    </div>
                                    <div class="pi-materials-stat-content">
                                        <span class="pi-materials-stat-label">Material Cost</span>
                                        <span class="pi-materials-stat-value" id="pi-materials-total-cost">£0.00</span>
                                    </div>
                                </div>
                                <div class="pi-materials-stat-card">
                                    <div class="pi-materials-stat-icon pi-materials-stat-icon-purple">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    </div>
                                    <div class="pi-materials-stat-content">
                                        <span class="pi-materials-stat-label">Budget vs Actual</span>
                                        <span class="pi-materials-stat-value" id="pi-materials-budget-actual">0%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Budget Progress Bar -->
                            <div class="pi-materials-budget-section">
                                <div class="pi-materials-budget-header">
                                    <span class="pi-materials-budget-label">Material Budget Consumption</span>
                                    <span class="pi-materials-budget-value" id="pi-materials-budget-text">£0 / £0</span>
                                </div>
                                <div class="pi-materials-progress-wrapper">
                                    <div class="pi-materials-progress-bar" id="pi-materials-budget-progress" style="width: 0%"></div>
                                </div>
                                <div class="pi-materials-budget-legend">
                                    <span class="pi-materials-legend-item"><span class="pi-materials-legend-dot pi-materials-legend-committed"></span> Committed</span>
                                    <span class="pi-materials-legend-item"><span class="pi-materials-legend-dot pi-materials-legend-spent"></span> Spent</span>
                                    <span class="pi-materials-legend-item"><span class="pi-materials-legend-dot pi-materials-legend-remaining"></span> Remaining</span>
                                </div>
                            </div>

                            <!-- Sub-navigation -->
                            <nav class="pi-materials-subnav">
                                <button class="pi-materials-subnav-btn active" data-materials-subtab="overview">Overview</button>
                                <button class="pi-materials-subnav-btn" data-materials-subtab="bom">BOM</button>
                                <button class="pi-materials-subnav-btn" data-materials-subtab="purchasing">Purchasing</button>
                                <button class="pi-materials-subnav-btn" data-materials-subtab="waste">Waste</button>
                                <button class="pi-materials-subnav-btn" data-materials-subtab="stock">Stock</button>
                            </nav>

                            <!-- Sub-tab Content -->
                            <div class="pi-materials-subtabs">
                                <!-- Overview Sub-tab -->
                                <div class="pi-materials-subtab-content active" id="materials-subtab-overview">
                                    <div class="pi-materials-overview-grid">
                                        <div class="pi-materials-overview-main">
                                            <div class="pi-materials-section">
                                                <div class="pi-materials-section-header">
                                                    <h3>Upcoming Deliveries</h3>
                                                </div>
                                                <div id="pi-materials-deliveries">
                                                    <div class="pi-loading-state">Loading deliveries...</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pi-materials-overview-sidebar">
                                            <div class="pi-materials-section">
                                                <div class="pi-materials-section-header">
                                                    <h3>Cost Breakdown by CSI</h3>
                                                </div>
                                                <div id="pi-materials-csi-breakdown">
                                                    <div class="pi-loading-state">Loading breakdown...</div>
                                                </div>
                                            </div>
                                            <div class="pi-materials-section">
                                                <div class="pi-materials-section-header">
                                                    <h3>Supplier Summary</h3>
                                                </div>
                                                <div id="pi-materials-supplier-summary">
                                                    <div class="pi-loading-state">Loading suppliers...</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- BOM Sub-tab -->
                                <div class="pi-materials-subtab-content" id="materials-subtab-bom">
                                    <div class="pi-materials-section">
                                        <div class="pi-materials-section-header">
                                            <h3>Bill of Materials</h3>
                                            <div class="pi-materials-section-actions">
                                                <button class="pi-btn pi-btn-secondary" id="pi-import-bom-btn">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                    Import
                                                </button>
                                                <button class="pi-btn pi-btn-primary" id="pi-add-bom-item-btn">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                    Add Item
                                                </button>
                                            </div>
                                        </div>
                                        <div id="pi-bom-container">
                                            <div class="pi-loading-state">Loading BOM...</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Purchasing Sub-tab -->
                                <div class="pi-materials-subtab-content" id="materials-subtab-purchasing">
                                    <div class="pi-materials-section">
                                        <div class="pi-materials-purchasing-tabs">
                                            <button class="pi-materials-purchasing-tab active" data-purchasing-tab="requisitions">Requisitions</button>
                                            <button class="pi-materials-purchasing-tab" data-purchasing-tab="orders">Purchase Orders</button>
                                            <button class="pi-materials-purchasing-tab" data-purchasing-tab="deliveries">Deliveries</button>
                                        </div>
                                        <div class="pi-materials-purchasing-content">
                                            <div class="pi-materials-purchasing-panel active" id="purchasing-tab-requisitions">
                                                <div class="pi-materials-section-header">
                                                    <h3>Requisitions</h3>
                                                    <button class="pi-btn pi-btn-primary" id="pi-new-requisition-btn">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                        New Requisition
                                                    </button>
                                                </div>
                                                <div id="pi-requisitions-container">
                                                    <div class="pi-loading-state">Loading requisitions...</div>
                                                </div>
                                            </div>
                                            <div class="pi-materials-purchasing-panel" id="purchasing-tab-orders">
                                                <div class="pi-materials-section-header">
                                                    <h3>Purchase Orders</h3>
                                                    <button class="pi-btn pi-btn-primary" id="pi-create-po-btn">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                        Create PO
                                                    </button>
                                                </div>
                                                <div id="pi-purchase-orders-container">
                                                    <div class="pi-loading-state">Loading purchase orders...</div>
                                                </div>
                                            </div>
                                            <div class="pi-materials-purchasing-panel" id="purchasing-tab-deliveries">
                                                <div class="pi-materials-section-header">
                                                    <h3>Deliveries & Receipts</h3>
                                                    <button class="pi-btn pi-btn-primary" id="pi-new-delivery-btn">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                        New Delivery
                                                    </button>
                                                </div>
                                                <div id="pi-deliveries-container">
                                                    <div class="pi-loading-state">Loading deliveries...</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Waste Sub-tab -->
                                <div class="pi-materials-subtab-content" id="materials-subtab-waste">
                                    <div class="pi-materials-section">
                                        <div class="pi-materials-section-header">
                                            <h3>Waste Transfer Notes</h3>
                                            <div class="pi-materials-waste-stats">
                                                <span class="pi-waste-stat" id="pi-waste-total">0 tonnes</span>
                                                <span class="pi-waste-stat" id="pi-waste-diverted">0% diverted</span>
                                            </div>
                                            <button class="pi-btn pi-btn-primary" id="pi-create-wtn-btn">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                Create WTN
                                            </button>
                                        </div>
                                        <div id="pi-waste-container">
                                            <div class="pi-loading-state">Loading waste notes...</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stock Sub-tab -->
                                <div class="pi-materials-subtab-content" id="materials-subtab-stock">
                                    <div class="pi-materials-section">
                                        <div class="pi-materials-section-header">
                                            <h3>Stock Allocations</h3>
                                            <button class="pi-btn pi-btn-primary" id="pi-reserve-stock-btn">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                Reserve Stock
                                            </button>
                                        </div>
                                        <div id="pi-stock-container">
                                            <div class="pi-loading-state">Loading stock allocations...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Action FAB (Mobile) -->
                            <div class="pi-materials-fab" id="pi-materials-fab">
                                <button class="pi-materials-fab-btn" id="pi-materials-fab-toggle">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                                <div class="pi-materials-fab-menu">
                                    <button class="pi-materials-fab-item" data-action="bom-item">Add BOM Item</button>
                                    <button class="pi-materials-fab-item" data-action="requisition">Create Requisition</button>
                                    <button class="pi-materials-fab-item" data-action="waste">Log Waste</button>
                                    <button class="pi-materials-fab-item" data-action="delivery">Record Delivery</button>
                                </div>
                            </div>
                                        </div>

                                        <div class="job-tab-panel" id="job-tab-schedule">
                                            <div class="pi-job-calendar-wrapper">
                                                <div class="pi-job-calendar-header">
                                                    <h3>Job Schedule</h3>
                                                    <div class="pi-job-calendar-actions">
                                                        <button type="button" id="pi-job-cal-add" class="pi-btn pi-btn-primary">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                                            Add Event
                                                        </button>
                                                        <a href="<?php echo esc_url($workspace_url); ?>calendar" class="pi-btn pi-btn-secondary">View Full Calendar →</a>
                                                    </div>
                                                </div>
                                                <div id="pi-job-calendar" class="pi-job-calendar-container"></div>
                                                <div class="pi-job-calendar-legend">
                                                    <span class="pi-legend-item"><span class="pi-legend-dot" style="background:#10b981"></span> Job</span>
                                                    <span class="pi-legend-item"><span class="pi-legend-dot" style="background:#3b82f6"></span> Site Visit</span>
                                                    <span class="pi-legend-item"><span class="pi-legend-dot" style="background:#f97316"></span> Delivery</span>
                                                    <span class="pi-legend-item"><span class="pi-legend-dot" style="background:#8b5cf6"></span> Appointment</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="job-tab-panel" id="job-tab-tasks">
                                            <div class="pi-job-tasks-wrapper">
                                                <div class="pi-job-tasks-header">
                                                    <h3>Job Tasks</h3>
                                                    <div class="pi-job-tasks-actions">
                                                        <button type="button" id="pi-job-task-add" class="pi-btn pi-btn-primary">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                                            Add Task
                                                        </button>
                                                        <a href="<?php echo esc_url($workspace_url); ?>tasks/" class="pi-btn pi-btn-secondary">View All Tasks →</a>
                                                    </div>
                                                </div>
                                                <div class="pi-job-tasks-stats">
                                                    <div class="pi-stat-card">
                                                        <span class="pi-stat-value" id="pi-job-tasks-total">0</span>
                                                        <span class="pi-stat-label">Total</span>
                                                    </div>
                                                    <div class="pi-stat-card">
                                                        <span class="pi-stat-value" id="pi-job-tasks-pending">0</span>
                                                        <span class="pi-stat-label">Pending</span>
                                                    </div>
                                                    <div class="pi-stat-card">
                                                        <span class="pi-stat-value" id="pi-job-tasks-completed">0</span>
                                                        <span class="pi-stat-label">Done</span>
                                                    </div>
                                                    <div class="pi-stat-card overdue">
                                                        <span class="pi-stat-value" id="pi-job-tasks-overdue">0</span>
                                                        <span class="pi-stat-label">Overdue</span>
                                                    </div>
                                                </div>
                                                <div class="pi-job-tasks-filters">
                                                    <div class="pi-job-tasks-search">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                                                        </svg>
                                                        <input type="text" id="pi-job-task-search" placeholder="Search tasks...">
                                                    </div>
                                                    <div class="pi-job-tasks-filter-group">
                                                        <button class="pi-filter-btn active" data-filter="all">All</button>
                                                        <button class="pi-filter-btn" data-filter="pending">Pending</button>
                                                        <button class="pi-filter-btn" data-filter="completed">Completed</button>
                                                    </div>
                                                </div>
                                                <div class="pi-job-tasks-container">
                                                    <div class="pi-job-tasks-list" id="pi-job-tasks-list">
                                                        <!-- Tasks rendered here -->
                                                    </div>
                                                    <div class="pi-job-tasks-empty" id="pi-job-tasks-empty" style="display:none;">
                                                        <div class="pi-empty-icon">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                                            </svg>
                                                        </div>
                                                        <h4>No tasks yet</h4>
                                                        <p>Create tasks to track work for this job</p>
                                                        <button class="pi-btn pi-btn-primary" onclick="JobTasks.openTaskModal()">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                                            Create First Task
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="job-tab-panel" id="job-tab-client">
                                            <div class="pi-customer-card">
                                <div class="pi-card-header"><h3>Client &amp; Site</h3></div>
                                <div class="pi-card-body">
                                    <form id="pi-job-client-form">
                                        <div class="pi-form-grid">
                                            <div class="pi-form-group pi-form-group-full">
                                                <label>Client name</label>
                                                <input type="text" name="customer_name" class="job-customer-name" placeholder="Client name" />
                                            </div>
                                            <div class="pi-form-group pi-form-group-full">
                                                <label>Site address</label>
                                                <input type="text" name="site_address" class="job-site-address" placeholder="Site address" />
                                            </div>
                                        </div>
                                        <div class="pi-form-actions"><button type="submit" class="pi-btn pi-btn-primary">Save</button></div>
                                    </form>
                                </div>
                            </div>

                            </div>
                                    </div><!-- /job-content-panels -->
                                </div><!-- /#pi-job-single -->
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modals Container -->
<div id="pi-modals-container"></div>

<div class="pi-toast-container" id="pi-toast-container"></div>

<!-- Job Sidebar Navigation JavaScript -->
<script>
(function() {
    'use strict';

    // Job Sidebar Controller
    window.JobSidebar = {
        sidebar: null,
        overlay: null,
        currentTab: 'overview',

        init() {
            this.sidebar = document.getElementById('job-nav-sidebar');
            this.overlay = document.getElementById('job-sidebar-overlay');
            this.jobId = document.getElementById('pi-job-single')?.dataset.jobId || null;

            // Bind click events to sidebar navigation items
            const navItems = document.querySelectorAll('.job-nav-item[data-job-tab]');
            navItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    const tab = item.dataset.jobTab;
                    this.switchTab(tab);
                });
            });

            // Bind delete button click
            const deleteBtn = document.getElementById('job-sidebar-delete-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.deleteJob();
                });
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 1024) {
                    if (!this.sidebar.contains(e.target) &&
                        !e.target.closest('#job-mobile-toggle')) {
                        this.close();
                    }
                }
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 1024) {
                    this.close();
                }
            });

            // Update sidebar job info from job data
            this.updateJobInfo();

            // Setup mutation observer to catch async updates
            this.setupMutationObserver();

            // Start polling for updates (for older browsers or as fallback)
            this.pollForUpdates();
        },

        switchTab(tabName) {
            // Update sidebar active state
            document.querySelectorAll('.job-nav-item').forEach(item => {
                item.classList.remove('active');
                if (item.dataset.jobTab === tabName) {
                    item.classList.add('active');
                }
            });

            // Hide all panels
            document.querySelectorAll('.job-tab-panel').forEach(panel => {
                panel.classList.remove('active');
            });

            // Show selected panel
            const targetPanel = document.getElementById('job-tab-' + tabName);
            if (targetPanel) {
                targetPanel.classList.add('active');
                this.currentTab = tabName;

                // Load CRM data if needed
                if (window.CRM_State && typeof CRM_State.loadTabData === 'function') {
                    CRM_State.loadTabData(tabName);
                }

                // Handle special tabs (overview, costs, materials, schedule, client)
                if (['overview', 'costs', 'materials', 'schedule', 'client'].includes(tabName)) {
                    if (tabName === 'overview') {
                        // Show the overview panel
                        document.getElementById('job-tab-overview')?.classList.add('active');
                        // Show KPI grid and layout
                        document.querySelector('.pi-kpi-grid')?.classList.remove('hidden');
                        document.querySelector('.pi-overview-layout')?.classList.remove('hidden');
                        // Activate the nested overview tab
                        document.getElementById('tab-overview')?.classList.add('active');
                        console.log('[JobSidebar] Activated overview tab');
                    } else {
                        // Hide overview elements
                        document.querySelector('.pi-kpi-grid')?.classList.add('hidden');
                        document.querySelector('.pi-overview-layout')?.classList.add('hidden');
                        document.getElementById('tab-overview')?.classList.remove('active');
                        document.getElementById('job-tab-overview')?.classList.remove('active');

                        console.log('[JobSidebar] Activated tab:', tabName);

                        // Load data for the tab
                        setTimeout(() => {
                            if (tabName === 'costs' && typeof window.loadJobCosts === 'function') {
                                window.loadJobCosts();
                            } else if (tabName === 'materials' && typeof window.loadJobMaterials === 'function') {
                                window.loadJobMaterials();
                            } else if (tabName === 'schedule' && typeof window.loadScheduleEvents === 'function') {
                                window.loadScheduleEvents();
                            }
                        }, 50);
                    }
                }
            }

            // Close mobile sidebar
            if (window.innerWidth <= 1024) {
                this.close();
            }

            // Update URL hash for direct linking
            window.history.replaceState(null, null, '#' + tabName);
        },

        toggle() {
            if (this.sidebar.classList.contains('open')) {
                this.close();
            } else {
                this.open();
            }
        },

        open() {
            this.sidebar.classList.add('open');
            if (this.overlay) this.overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.sidebar.classList.remove('open');
            if (this.overlay) this.overlay.classList.remove('open');
            document.body.style.overflow = '';
        },

        async deleteJob() {
            if (!this.jobId) {
                console.error('[JobSidebar] No job ID found');
                return;
            }

            if (!confirm('Are you sure you want to delete this job? This action cannot be undone.')) {
                return;
            }

            try {
                const resp = await fetch('/wp-json/pi/v1/jobs/' + this.jobId + '/remove', {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
                    }
                });

                // Check if response is OK (2xx status)
                if (resp.ok) {
                    // Try to parse JSON, but if it fails, still consider it a success if status is OK
                    let result;
                    try {
                        result = await resp.json();
                    } catch (jsonErr) {
                        // Response wasn't JSON but status was OK - likely still deleted
                        console.log('[JobSidebar] Delete succeeded but response was not JSON');
                        result = { deleted: true };
                    }

                    if (result.deleted) {
                        // Show success and redirect immediately
                        alert('Job deleted successfully');
                        window.location.href = '/workspace/';
                        return;
                    }
                }

                // If we get here, there was an actual error
                let errorMsg = 'Delete failed';
                try {
                    const errorData = await resp.json();
                    errorMsg = errorData.message || errorData.error || 'Delete failed';
                } catch (e) {
                    errorMsg = 'Server error: ' + resp.status + ' ' + resp.statusText;
                }
                throw new Error(errorMsg);

            } catch (err) {
                console.error('[JobSidebar] Delete error:', err);
                // Check if job was actually deleted despite the error
                try {
                    const checkResp = await fetch('/wp-json/pi/v1/jobs/' + this.jobId, {
                        headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' }
                    });
                    if (!checkResp.ok) {
                        // Job doesn't exist anymore, redirect
                        alert('Job deleted successfully');
                        window.location.href = '/workspace/';
                        return;
                    }
                } catch (checkErr) {
                    // Check failed, assume deleted
                    alert('Job deleted successfully');
                    window.location.href = '/workspace/';
                    return;
                }
                alert('Failed to delete job: ' + err.message);
            }
        },

        updateJobInfo() {
            // Update sidebar with job data when available
            const jobCode = document.getElementById('pi-job-code');
            const jobAddress = document.getElementById('pi-job-address');
            const jobDates = document.getElementById('pi-job-dates');

            if (jobCode) {
                const code = jobCode.textContent.trim();
                const sidebarRef = document.getElementById('job-sidebar-ref');
                const headerRef = document.getElementById('job-header-ref');
                if (sidebarRef && code) sidebarRef.textContent = code;
                if (headerRef && code) headerRef.textContent = code;
            }

            if (jobAddress) {
                const address = jobAddress.textContent.trim();
                const sidebarAddress = document.getElementById('job-sidebar-address');
                const headerAddress = document.getElementById('job-header-address');
                if (sidebarAddress && address && address !== 'Loading...') {
                    sidebarAddress.textContent = address;
                }
                if (headerAddress && address && address !== 'Loading...') {
                    headerAddress.textContent = address;
                }
            }

            if (jobDates) {
                const dates = jobDates.textContent.trim();
                const sidebarMeta = document.getElementById('job-sidebar-meta');
                if (sidebarMeta && dates && dates !== '—') {
                    sidebarMeta.textContent = dates;
                }
            }
        },

        setupMutationObserver() {
            // Watch for changes to hidden job data elements
            const targets = ['pi-job-code', 'pi-job-address', 'pi-job-dates'];
            targets.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    const observer = new MutationObserver(() => {
                        this.updateJobInfo();
                    });
                    observer.observe(el, { childList: true, subtree: true, characterData: true });
                }
            });
        },

        pollForUpdates() {
            // Fallback: poll every 500ms for first 10 seconds to catch async updates
            let attempts = 0;
            const maxAttempts = 20;
            const interval = setInterval(() => {
                this.updateJobInfo();
                attempts++;
                if (attempts >= maxAttempts) clearInterval(interval);
            }, 500);
        },

        handleInitialState() {
            // Check for URL hash to determine initial tab
            let initialTab = 'overview';
            if (window.location.hash) {
                const hashTab = window.location.hash.substring(1);
                const validTabs = ['overview', 'costs', 'materials', 'schedule', 'client', 'communications', 'documents', 'team', 'safety', 'quality', 'change-orders', 'invoicing', 'rfi-submittals', 'daily-reports', 'equipment', 'subcontractors', 'site-map'];
                if (validTabs.includes(hashTab)) {
                    initialTab = hashTab;
                }
            }

            // Ensure only the initial tab is active
            document.querySelectorAll('.job-tab-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            document.querySelectorAll('.job-nav-item').forEach(item => {
                item.classList.remove('active');
            });

            // Activate initial tab
            const targetPanel = document.getElementById('job-tab-' + initialTab);
            const targetNav = document.querySelector('.job-nav-item[data-job-tab="' + initialTab + '"]');

            if (targetPanel) {
                targetPanel.classList.add('active');
            }
            if (targetNav) {
                targetNav.classList.add('active');
            }

            this.currentTab = initialTab;

            // Handle tab-specific initialization
            if (initialTab === 'overview') {
                // Ensure overview elements are visible
                document.querySelector('.pi-kpi-grid')?.classList.remove('hidden');
                document.querySelector('.pi-overview-layout')?.classList.remove('hidden');
                document.getElementById('tab-overview')?.classList.add('active');
                document.getElementById('job-tab-overview')?.classList.add('active');
                console.log('[JobSidebar] Initial state: overview');
            } else {
                // Hide overview elements using native classes
                document.querySelector('.pi-kpi-grid')?.classList.add('hidden');
                document.querySelector('.pi-overview-layout')?.classList.add('hidden');
                document.getElementById('tab-overview')?.classList.remove('active');

                // Ensure the target panel is active
                const panel = document.getElementById('job-tab-' + initialTab);
                if (panel) panel.classList.add('active');

                // Load data after a short delay
                setTimeout(() => {
                    if (initialTab === 'costs' && typeof window.loadJobCosts === 'function') {
                        window.loadJobCosts();
                    } else if (initialTab === 'materials' && typeof window.loadJobMaterials === 'function') {
                        window.loadJobMaterials();
                    } else if (initialTab === 'schedule' && typeof window.loadScheduleEvents === 'function') {
                        window.loadScheduleEvents();
                    }
                }, 100);
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            JobSidebar.init();
            JobSidebar.handleInitialState();
        });
    } else {
        JobSidebar.init();
        JobSidebar.handleInitialState();
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
