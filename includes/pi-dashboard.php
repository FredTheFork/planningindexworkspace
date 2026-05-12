<?php
/**
 * Planning Index Workspace - Unified Dashboard v3.0
 * Professional Overview Dashboard matching Expenses/Materials/Calendar/Team design
 * 
 * Shortcode: [planning_dashboard]
 * 
 * @package PlanningIndex
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

if (!defined('PI_DASHBOARD_VERSION')) {
    define('PI_DASHBOARD_VERSION', '3.0.0');
}

/**
 * Shortcode: [planning_dashboard]
 * Unified Overview Dashboard
 */
add_shortcode('planning_dashboard', 'pi_workspace_dashboard_shortcode');

function pi_workspace_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="pi-dashboard-wrapper-v3"><div class="pi-db-auth-required"><div class="pi-db-auth-icon"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div><h3>Sign In Required</h3><p>Please log in to access your dashboard.</p></div></div>';
    }

    $user_id = get_current_user_id();
    $user = wp_get_current_user();

    // Enqueue Inter font
    wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null);

    // Enqueue dashboard CSS
    wp_enqueue_style('pi-dashboard-v3', plugin_dir_url(__FILE__) . '../assets/dashboard-v3.css', [], PI_DASHBOARD_VERSION);

    // Enqueue dashboard JS
    wp_enqueue_script('pi-dashboard-v3', plugin_dir_url(__FILE__) . '../assets/dashboard-v3.js', ['jquery'], PI_DASHBOARD_VERSION, true);

    // Localize script
    wp_localize_script('pi-dashboard-v3', 'PI_Dashboard', [
        'restUrl' => rest_url('pi/v1'),
        'nonce' => wp_create_nonce('wp_rest'),
        'userId' => $user_id,
        'currency' => 'GBP'
    ]);

    // Feather Icons
    wp_enqueue_script('feather-icons', 'https://unpkg.com/feather-icons', [], null, true);

    ob_start();
    ?><?php // No whitespace here ?><div class="pi-dashboard-wrapper-v3" data-user-id="<?php echo esc_attr($user_id); ?>"><?php // No whitespace here ?>

    <!-- SaaS Header (Dark - Matching Calendar/Materials/Team) -->
    <header class="pi-db-header">
        <div class="pi-db-header-left">
            <div class="pi-db-brand">
                <div class="pi-db-brand-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="21" x2="9" y2="9"/>
                    </svg>
                </div>
                <div class="pi-db-brand-text">
                    <h1>Dashboard</h1>
                    <p>Overview & Analytics</p>
                </div>
            </div>
        </div><?php // No whitespace here ?>

        <!-- Navigation -->
        <nav class="pi-db-nav">
            <button class="pi-db-nav-item active" data-tab="overview" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span>Overview</span>
            </button>
            <button class="pi-db-nav-item" data-tab="pipeline" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
                <span>Pipeline</span>
            </button>
            <button class="pi-db-nav-item" data-tab="analytics" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
                <span>Analytics</span>
            </button>
            <button class="pi-db-nav-item" data-tab="activity" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
                <span>Activity</span>
            </button>
        </nav><?php // No whitespace here ?>

        <div class="pi-db-header-right">
            <!-- Search -->
            <div class="pi-db-search-wrapper">
                <div class="pi-db-search-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" id="globalSearch" placeholder="Search..." autocomplete="off">
                </div>
            </div><?php // No whitespace here ?>

            <!-- Notifications -->
            <button class="pi-db-icon-btn" aria-label="Notifications" id="notificationsBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="pi-db-badge" id="notificationBadge" style="display: none;">3</span>
            </button><?php // No whitespace here ?>

            <!-- Help -->
            <button class="pi-db-icon-btn" aria-label="Help" title="Help Center">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </button><?php // No whitespace here ?>

            <!-- Add Button -->
            <button class="pi-db-add-btn" id="addNewBtn" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>New</span>
            </button>
        </div>
    </header><?php // No whitespace here ?>

    <!-- Main Content -->
    <main class="pi-db-main">
        
        <!-- Overview Tab -->
        <div class="pi-db-tab-content active" id="tab-overview">
            
            <!-- Section Header -->
            <div class="pi-db-section-header">
                <div class="pi-db-section-title">
                    <h2>Dashboard Overview</h2>
                    <p>Real-time insights into your business performance</p>
                </div>
                <div class="pi-db-section-actions">
                    <select id="dateRangeFilter" class="pi-db-btn pi-db-btn-secondary pi-db-btn-sm">
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                    </select>
                    <button id="refreshDashboard" class="pi-db-btn pi-db-btn-primary pi-db-btn-sm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                        <span>Refresh</span>
                    </button>
                </div>
            </div><?php // No whitespace here ?>

            <!-- KPI Cards -->
            <div class="pi-db-kpi-grid" id="kpiCards">
                <!-- Loaded dynamically via JS -->
                <div class="pi-db-kpi-card brand">
                    <div class="pi-db-kpi-header">
                        <span class="pi-db-kpi-label">Total Pipeline Value</span>
                        <div class="pi-db-kpi-icon brand">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                <path d="M22 12A10 10 0 0 0 12 2v10l10 0"/>
                            </svg>
                        </div>
                    </div>
                    <div class="pi-db-kpi-value">£125,000</div>
                    <div class="pi-db-kpi-subtitle">65 active leads</div>
                </div>
                <div class="pi-db-kpi-card success">
                    <div class="pi-db-kpi-header">
                        <span class="pi-db-kpi-label">Won This Month</span>
                        <div class="pi-db-kpi-icon success">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                    </div>
                    <div class="pi-db-kpi-value">£45,000</div>
                    <div class="pi-db-kpi-subtitle">8 jobs won</div>
                </div>
                <div class="pi-db-kpi-card warning">
                    <div class="pi-db-kpi-header">
                        <span class="pi-db-kpi-label">Pending Proposals</span>
                        <div class="pi-db-kpi-icon warning">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        </div>
                    </div>
                    <div class="pi-db-kpi-value">12</div>
                    <div class="pi-db-kpi-subtitle">£89,000 value</div>
                </div>
                <div class="pi-db-kpi-card danger">
                    <div class="pi-db-kpi-header">
                        <span class="pi-db-kpi-label">Tasks Due This Week</span>
                        <div class="pi-db-kpi-icon danger">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                        </div>
                    </div>
                    <div class="pi-db-kpi-value">18</div>
                    <div class="pi-db-kpi-subtitle">3 overdue</div>
                </div>
                <div class="pi-db-kpi-card info">
                    <div class="pi-db-kpi-header">
                        <span class="pi-db-kpi-label">Active Jobs</span>
                        <div class="pi-db-kpi-icon info">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>
                        </div>
                    </div>
                    <div class="pi-db-kpi-value">24</div>
                    <div class="pi-db-kpi-subtitle">156 completed total</div>
                </div>
                <div class="pi-db-kpi-card purple">
                    <div class="pi-db-kpi-header">
                        <span class="pi-db-kpi-label">Team Hours This Week</span>
                        <div class="pi-db-kpi-icon purple">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="pi-db-kpi-value">340h</div>
                    <div class="pi-db-kpi-subtitle">45h overtime</div>
                </div>
            </div><?php // No whitespace here ?>

            <!-- Quick Stats Row -->
            <div class="pi-db-quick-stats" id="quickStats">
                <div class="pi-db-quick-stat">
                    <div class="pi-db-quick-stat-icon info">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div class="pi-db-quick-stat-content">
                        <div class="pi-db-quick-stat-value">65</div>
                        <div class="pi-db-quick-stat-label">Total Leads</div>
                    </div>
                </div>
                <div class="pi-db-quick-stat">
                    <div class="pi-db-quick-stat-icon success">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 11 12 14 22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                    <div class="pi-db-quick-stat-content">
                        <div class="pi-db-quick-stat-value">42</div>
                        <div class="pi-db-quick-stat-label">Tasks Done</div>
                    </div>
                </div>
                <div class="pi-db-quick-stat">
                    <div class="pi-db-quick-stat-icon warning">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="pi-db-quick-stat-content">
                        <div class="pi-db-quick-stat-value">£3,200</div>
                        <div class="pi-db-quick-stat-label">This Week Expenses</div>
                    </div>
                </div>
                <div class="pi-db-quick-stat">
                    <div class="pi-db-quick-stat-icon purple">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="pi-db-quick-stat-content">
                        <div class="pi-db-quick-stat-value">12</div>
                        <div class="pi-db-quick-stat-label">Events This Week</div>
                    </div>
                </div>
            </div><?php // No whitespace here ?>

            <!-- Pipeline Preview -->
            <div class="pi-db-card pi-db-full-width">
                <div class="pi-db-card-header">
                    <div class="pi-db-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                        <span>Pipeline Overview</span>
                    </div>
                    <div class="pi-db-card-actions">
                        <a href="<?php echo esc_url(home_url('/workspace')); ?>" class="pi-db-btn pi-db-btn-secondary pi-db-btn-sm">
                            <span>View Full Pipeline</span>
                        </a>
                    </div>
                </div>
                <div class="pi-db-card-body">
                    <div class="pi-db-pipeline-preview" id="pipelinePreview">
                        <div class="pi-db-pipeline-stage pi-db-stage-new">
                            <div class="pi-db-stage-header">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="8.5" cy="7" r="4"/>
                                    <line x1="20" y1="8" x2="20" y2="14"/>
                                    <line x1="23" y1="11" x2="17" y2="11"/>
                                </svg>
                                <span>New Lead</span>
                            </div>
                            <div class="pi-db-stage-count">12</div>
                            <div class="pi-db-stage-value">£0</div>
                        </div>
                        <div class="pi-db-pipeline-stage pi-db-stage-proposal">
                            <div class="pi-db-stage-header">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <span>Proposal</span>
                            </div>
                            <div class="pi-db-stage-count">8</div>
                            <div class="pi-db-stage-value">£45,000</div>
                        </div>
                        <div class="pi-db-pipeline-stage pi-db-stage-contacted">
                            <div class="pi-db-stage-header">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                <span>Contacted</span>
                            </div>
                            <div class="pi-db-stage-count">15</div>
                            <div class="pi-db-stage-value">£67,000</div>
                        </div>
                        <div class="pi-db-pipeline-stage pi-db-stage-negotiation">
                            <div class="pi-db-stage-header">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                </svg>
                                <span>Negotiation</span>
                            </div>
                            <div class="pi-db-stage-count">6</div>
                            <div class="pi-db-stage-value">£35,000</div>
                        </div>
                        <div class="pi-db-pipeline-stage pi-db-stage-won">
                            <div class="pi-db-stage-header">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="8" r="7"/>
                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                                </svg>
                                <span>Won</span>
                            </div>
                            <div class="pi-db-stage-count">24</div>
                            <div class="pi-db-stage-value">£156,000</div>
                        </div>
                    </div>
                </div>
            </div><?php // No whitespace here ?>

            <!-- Main Grid: Charts & Activity -->
            <div class="pi-db-main-grid">
                
                <!-- Revenue Chart -->
                <div class="pi-db-card">
                    <div class="pi-db-card-header">
                        <div class="pi-db-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="20" x2="12" y2="10"/>
                                <line x1="18" y1="20" x2="18" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="16"/>
                            </svg>
                            <span>Revenue Trends</span>
                        </div>
                    </div>
                    <div class="pi-db-card-body">
                        <div class="pi-db-chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div><?php // No whitespace here ?>

                <!-- Pipeline Distribution -->
                <div class="pi-db-card">
                    <div class="pi-db-card-header">
                        <div class="pi-db-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                <path d="M22 12A10 10 0 0 0 12 2v10l10 0"/>
                            </svg>
                            <span>Pipeline Distribution</span>
                        </div>
                    </div>
                    <div class="pi-db-card-body">
                        <div class="pi-db-chart-container">
                            <canvas id="pipelineChart"></canvas>
                        </div>
                    </div>
                </div><?php // No whitespace here ?>

                <!-- Activity Feed -->
                <div class="pi-db-card">
                    <div class="pi-db-card-header">
                        <div class="pi-db-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                            <span>Recent Activity</span>
                        </div>
                        <div class="pi-db-card-actions">
                            <span id="lastRefresh" style="font-size: 12px; color: var(--pi-text-muted);">Updated just now</span>
                        </div>
                    </div>
                    <div class="pi-db-card-body">
                        <div class="pi-db-activity-list" id="activityFeed">
                            <div class="pi-db-activity-item">
                                <div class="pi-db-activity-avatar" style="background: #15634920; color: #156349;">JD</div>
                                <div class="pi-db-activity-content">
                                    <div class="pi-db-activity-text"><strong>John Doe</strong> moved <strong>LEAD-2025-001</strong> to Won</div>
                                    <div class="pi-db-activity-meta">
                                        <span class="pi-db-activity-type lead">lead</span>
                                        <span>2 hours ago</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pi-db-activity-item">
                                <div class="pi-db-activity-avatar" style="background: #3b82f620; color: #3b82f6;">AS</div>
                                <div class="pi-db-activity-content">
                                    <div class="pi-db-activity-text"><strong>Alice Smith</strong> completed task <strong>Site Survey</strong></div>
                                    <div class="pi-db-activity-meta">
                                        <span class="pi-db-activity-type task">task</span>
                                        <span>3 hours ago</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pi-db-activity-item">
                                <div class="pi-db-activity-avatar" style="background: #f59e0b20; color: #f59e0b;">MJ</div>
                                <div class="pi-db-activity-content">
                                    <div class="pi-db-activity-text"><strong>Mike Johnson</strong> added expense <strong>Materials</strong></div>
                                    <div class="pi-db-activity-meta">
                                        <span class="pi-db-activity-type expense">expense</span>
                                        <span>5 hours ago</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><?php // No whitespace here ?>

                <!-- Weekly Activity Chart -->
                <div class="pi-db-card">
                    <div class="pi-db-card-header">
                        <div class="pi-db-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20v-6M6 20V10M18 20V4"/>
                            </svg>
                            <span>Weekly Activity</span>
                        </div>
                    </div>
                    <div class="pi-db-card-body">
                        <div class="pi-db-chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div><?php // No whitespace here ?>

        <!-- Pipeline Tab -->
        <div class="pi-db-tab-content" id="tab-pipeline">
            <div class="pi-db-section-header">
                <div class="pi-db-section-title">
                    <h2>Pipeline Management</h2>
                    <p>View and manage your leads and jobs pipeline</p>
                </div>
                <div class="pi-db-section-actions">
                    <a href="<?php echo esc_url(home_url('/workspace')); ?>" class="pi-db-btn pi-db-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="3" y1="9" x2="21" y2="9"/>
                            <line x1="9" y1="21" x2="9" y2="9"/>
                        </svg>
                        <span>Open Full Pipeline</span>
                    </a>
                </div>
            </div>
            <div class="pi-db-empty">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <line x1="3" y1="9" x2="21" y2="9"/>
                    <line x1="9" y1="21" x2="9" y2="9"/>
                </svg>
                <h3>Pipeline View</h3>
                <p>The full pipeline view is available in the workspace. Click the button above to access it.</p>
            </div>
        </div><?php // No whitespace here ?>

        <!-- Analytics Tab -->
        <div class="pi-db-tab-content" id="tab-analytics">
            <div class="pi-db-section-header">
                <div class="pi-db-section-title">
                    <h2>Analytics & Reports</h2>
                    <p>Deep dive into your business metrics</p>
                </div>
                <div class="pi-db-section-actions">
                    <button class="pi-db-btn pi-db-btn-secondary pi-db-btn-sm" id="exportReport">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>Export Report</span>
                    </button>
                </div>
            </div>
            <div class="pi-db-empty">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
                <h3>Analytics</h3>
                <p>Detailed analytics and reporting features coming soon.</p>
            </div>
        </div><?php // No whitespace here ?>

        <!-- Activity Tab -->
        <div class="pi-db-tab-content" id="tab-activity">
            <div class="pi-db-section-header">
                <div class="pi-db-section-title">
                    <h2>Activity Log</h2>
                    <p>Complete history of all activities</p>
                </div>
            </div>
            <div class="pi-db-card pi-db-full-width">
                <div class="pi-db-card-body">
                    <div class="pi-db-activity-list" id="fullActivityFeed">
                        <!-- Full activity feed loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>

    </main><?php // No whitespace here ?>

</div><?php // No whitespace here ?>
<script>
    // Initialize feather icons when available
    document.addEventListener('DOMContentLoaded', function() {
        if (window.feather) {
            feather.replace();
        }
    });
</script>
<?php
    return ob_get_clean();
}

/**
 * Register REST API endpoints for Dashboard
 */
add_action('rest_api_init', 'pi_dashboard_register_rest_routes');
function pi_dashboard_register_rest_routes() {
    $namespace = 'pi/v1';
    
    // Dashboard stats
    register_rest_route($namespace, '/dashboard/stats', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_dashboard_get_stats',
        'permission_callback' => 'pi_dashboard_check_permission'
    ]);
    
    // Recent activity
    register_rest_route($namespace, '/dashboard/activity', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_dashboard_get_activity',
        'permission_callback' => 'pi_dashboard_check_permission'
    ]);
    
    // Pipeline data
    register_rest_route($namespace, '/dashboard/pipeline', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'pi_dashboard_get_pipeline',
        'permission_callback' => 'pi_dashboard_check_permission'
    ]);
}

/**
 * Check permission for dashboard endpoints
 */
function pi_dashboard_check_permission() {
    return is_user_logged_in();
}

/**
 * Get dashboard stats
 */
function pi_dashboard_get_stats(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    
    // This is sample data - replace with actual queries
    $stats = [
        'total_value' => 125000,
        'lead_count' => 65,
        'won_value' => 45000,
        'won_count' => 8,
        'proposal_count' => 12,
        'proposal_value' => 89000,
        'tasks_due' => 18,
        'tasks_overdue' => 3,
        'active_jobs' => 24,
        'jobs_completed' => 156,
        'hours_this_week' => 340,
        'hours_overtime' => 45,
        'total_leads' => 65,
        'tasks_completed' => 42,
        'expenses_this_week' => 3200,
        'events_this_week' => 12
    ];
    
    return rest_ensure_response($stats);
}

/**
 * Get recent activity
 */
function pi_dashboard_get_activity(WP_REST_Request $request) {
    $limit = $request->get_param('limit') ?: 20;
    $user_id = get_current_user_id();
    
    // This is sample data - replace with actual queries
    $activities = [
        [
            'user_name' => 'John Doe',
            'action' => 'moved LEAD-2025-001 to Won',
            'type' => 'lead',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'user_name' => 'Alice Smith',
            'action' => 'completed task Site Survey',
            'type' => 'task',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours'))
        ],
        [
            'user_name' => 'Mike Johnson',
            'action' => 'added expense Materials',
            'type' => 'expense',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-5 hours'))
        ]
    ];
    
    return rest_ensure_response($activities);
}

/**
 * Get pipeline data
 */
function pi_dashboard_get_pipeline(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    
    // This is sample data - replace with actual queries
    $pipeline = [
        'new_lead' => ['count' => 12, 'value' => 0],
        'proposal_sent' => ['count' => 8, 'value' => 45000],
        'contacted' => ['count' => 15, 'value' => 67000],
        'negotiation' => ['count' => 6, 'value' => 35000],
        'won' => ['count' => 24, 'value' => 156000]
    ];
    
    return rest_ensure_response($pipeline);
}
