<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <?php wp_head(); ?>

    <!-- =============================================================================
         Copied from workspace template - required for the new sidebar to look correct
    ============================================================================== -->
    <style>
    /* CSS Variables */
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

    /* Sidebar */
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

    /* Tooltip on hover */
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

    /* Push main content aside so it doesn't overlap fixed sidebar */
    .pi-main-wrapper {
      margin-left: var(--workspace-pi-sidebar-width);
    }
    </style>
</head>
<body class="pi-lead-page">

<div class="pi-app-layout">

    <!-- LEFT SIDEBAR – exact copy from workspace template -->
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

    <!-- MAIN CONTENT AREA -->
    <div class="pi-main-wrapper">
        <!-- TOP HEADER -->
        <header class="pi-topbar">
            <div class="pi-topbar-left">
                <a href="https://planningindex.co.uk/workspace/" class="pi-back-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    <span>Back to Workspace</span>
                </a>
            </div>
            
            <div class="pi-topbar-center">
                <form id="pi-mini-search" class="pi-search-box" action="" method="get" role="search" onsubmit="return false;">
                    <svg class="pi-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="search" name="s" placeholder="Search planning applications..." aria-label="Search" />
                    <kbd class="pi-search-kbd">⌘K</kbd>
                </form>
            </div>
            
            <div class="pi-topbar-right">
                <?php echo do_shortcode('[pi_profile_dropdown]'); ?>
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="pi-content">
            <div class="pi-content-inner">
                <?php wp_body_open(); ?>
                
                <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
                    <?php if (get_post_meta(get_the_ID(), PI_LEAD_META_PREFIX . 'owner_user_id', true) != get_current_user_id()) : ?>
                        <div class="pi-access-denied">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                            </svg>
                            <h2>Access Denied</h2>
                            <p>You don't have permission to view this lead.</p>
                            <a href="https://planningindex.co.uk/workspace/" class="pi-btn pi-btn-primary">Return to Workspace</a>
                        </div>
                        <?php wp_die(); ?>
                    <?php endif; ?>
                    
                    <div id="pi-lead-single" data-lead-id="<?php echo esc_attr(get_the_ID()); ?>">
                        <!-- LEAD HEADER -->
                        <div class="pi-lead-header">
                            <div class="pi-lead-header-main">
                                <div class="pi-lead-avatar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M3 21h18M5 21V7l8-4 8 4v14M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/>
                                    </svg>
                                </div>
                                <div class="pi-lead-title-group">
                                    <div class="pi-lead-ref-badge" id="pi-lead-ref">Loading...</div>
                                    <h1 class="pi-lead-title" id="pi-lead-address">Loading property details...</h1>
                                    <div class="pi-lead-meta">
                                        <span class="pi-lead-meta-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                            <span id="pi-date-received">—</span>
                                        </span>
                                        <span class="pi-lead-meta-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            <a href="#" id="pi-map-link" target="_blank">View on Map</a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pi-lead-header-actions">
                                <div class="pi-stage-control">
                                    <label for="lead-stage">Stage:</label>
                                    <select id="lead-stage" class="pi-stage-select">
                                        <option value="new">🆕 New Lead</option>
                                        <option value="proposal">📄 Proposal Sent</option>
                                        <option value="contacted">📞 Contacted</option>
                                        <option value="negotiation">🤝 Negotiation</option>
                                        <option value="won">🏆 Won</option>
                                    </select>
                                    <button id="update-stage" class="pi-btn pi-btn-primary pi-btn-sm">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                                        </svg>
                                        Update
                                    </button>
                                </div>
                                
                                <div class="pi-quick-actions">
                                    <button class="pi-action-btn" id="pi-quick-call" title="Quick Call">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                    </button>
                                    <button class="pi-action-btn" id="pi-quick-email" title="Send Email">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                        </svg>
                                    </button>
                                    <button class="pi-action-btn pi-action-btn-primary" id="pi-generate-proposal" title="Generate Proposal">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- KPI CARDS -->
                        <div class="pi-kpi-grid">
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-blue">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                </div>
                                <div class="pi-kpi-content">
                                    <span class="pi-kpi-label">Estimated Value</span>
                                    <span class="pi-kpi-value" id="pi-total-value">£0.00</span>
                                </div>
                            </div>
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-green">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                                    </svg>
                                </div>
                                <div class="pi-kpi-content">
                                    <span class="pi-kpi-label">Lead Score</span>
                                    <span class="pi-kpi-value" id="pi-lead-score">—</span>
                                </div>
                            </div>
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-amber">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                </div>
                                <div class="pi-kpi-content">
                                    <span class="pi-kpi-label">Days in Pipeline</span>
                                    <span class="pi-kpi-value" id="pi-days-pipeline">0</span>
                                </div>
                            </div>
                            <div class="pi-kpi-card">
                                <div class="pi-kpi-icon pi-kpi-icon-purple">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <div class="pi-kpi-content">
                                    <span class="pi-kpi-label">Interactions</span>
                                    <span class="pi-kpi-value" id="pi-interactions">0</span>
                                </div>
                            </div>
                        </div>

                        <!-- MAIN GRID LAYOUT -->
                        <div class="pi-lead-grid">
                            <!-- LEFT COLUMN -->
                            <div class="pi-lead-col-main">
                                <!-- TABS NAVIGATION -->
                                <div class="pi-tabs-container">
                                    <nav class="pi-tabs-nav" role="tablist">
                                        <button class="pi-tab-btn active" data-tab="overview" role="tab" aria-selected="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                                            </svg>
                                            Overview
                                        </button>
                                        <button class="pi-tab-btn" data-tab="customer" role="tab" aria-selected="false">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                            </svg>
                                            Customer
                                        </button>
                                        <button class="pi-tab-btn" data-tab="pricing" role="tab" aria-selected="false">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                            </svg>
                                            Pricing
                                        </button>
                                        <button class="pi-tab-btn" data-tab="documents" role="tab" aria-selected="false">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>
                                            </svg>
                                            Documents
                                        </button>
                                        <button class="pi-tab-btn" data-tab="tasks" role="tab" aria-selected="false">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                            </svg>
                                            Tasks
                                        </button>
                                    </nav>
                                    
                                    <!-- TAB PANELS -->
                                    <div class="pi-tabs-content">
                                        <!-- OVERVIEW TAB -->
                                        <div class="pi-tab-panel active" id="tab-overview" role="tabpanel">
                                            <div class="pi-planning-card">
                                                <div class="pi-card-header">
                                                    <h3>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                                        </svg>
                                                        Planning Application Details
                                                    </h3>
                                                    <a href="#" id="pi-original-link" class="pi-card-link" target="_blank">View Original →</a>
                                                </div>
                                                <div class="pi-card-body" id="pi-planning-overview">
                                                    <div class="pi-skeleton-loader">
                                                        <div class="pi-skeleton pi-skeleton-text"></div>
                                                        <div class="pi-skeleton pi-skeleton-text pi-skeleton-short"></div>
                                                        <div class="pi-skeleton pi-skeleton-text"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="pi-notes-card">
                                                <div class="pi-card-header">
                                                    <h3>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                        </svg>
                                                        Notes & Comments
                                                    </h3>
                                                </div>
                                                <div class="pi-card-body">
                                                    <form id="pi-notes-form">
                                                        <textarea name="notes" placeholder="Add notes about this lead, site conditions, customer requirements, etc..." rows="4"></textarea>
                                                        <div class="pi-form-actions">
                                                            <button type="submit" class="pi-btn pi-btn-primary">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                                                                </svg>
                                                                Save Notes
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- CUSTOMER TAB -->
                                        <div class="pi-tab-panel" id="tab-customer" role="tabpanel">
                                            <div class="pi-customer-card">
                                                <div class="pi-card-header">
                                                    <h3>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                                        </svg>
                                                        Customer Information
                                                    </h3>
                                                </div>
                                                <div class="pi-card-body">
                                                    <form id="pi-lead-customer-form">
                                                        <div class="pi-form-grid">
                                                            <div class="pi-form-group">
                                                                <label for="customer_name">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                                                    </svg>
                                                                    Full Name
                                                                </label>
                                                                <input type="text" name="customer_name" id="customer_name" placeholder="John Smith" />
                                                            </div>
                                                            <div class="pi-form-group">
                                                                <label for="customer_phone">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                                                    </svg>
                                                                    Phone Number
                                                                </label>
                                                                <input type="tel" name="customer_phone" id="customer_phone" placeholder="+44 7XXX XXX XXX" />
                                                            </div>
                                                            <div class="pi-form-group">
                                                                <label for="customer_email">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                                                    </svg>
                                                                    Email Address
                                                                </label>
                                                                <input type="email" name="customer_email" id="customer_email" placeholder="john@example.com" />
                                                            </div>
                                                            <div class="pi-form-group pi-form-group-full">
                                                                <label for="customer_address">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                                                    </svg>
                                                                    Property Address
                                                                </label>
                                                                <input type="text" name="customer_address" id="customer_address" placeholder="123 Main Street, London" />
                                                            </div>
                                                        </div>
                                                        <div class="pi-form-actions">
                                                            <button type="submit" class="pi-btn pi-btn-primary">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                                                                </svg>
                                                                Save Customer Info
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- PRICING TAB -->
                                        <div class="pi-tab-panel" id="tab-pricing" role="tabpanel">
                                            <div class="pi-pricing-card">
                                                <div class="pi-card-header">
                                                    <h3>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                                        </svg>
                                                        Quote Builder
                                                    </h3>
                                                    <button id="pi-add-pricing-row" class="pi-btn pi-btn-outline pi-btn-sm">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                                        </svg>
                                                        Add Item
                                                    </button>
                                                </div>
                                                <div class="pi-card-body">
                                                    <form id="pi-pricing-form">
                                                        <div class="pi-table-wrapper">
                                                            <table id="pi-pricing-table" class="pi-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Description</th>
                                                                        <th>Qty</th>
                                                                        <th>Unit Price</th>
                                                                        <th>Total</th>
                                                                        <th></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <!-- Rows added dynamically -->
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="pi-subtotal-row">
                                                                        <td colspan="3">Subtotal</td>
                                                                        <td id="pi-subtotal">£0.00</td>
                                                                        <td></td>
                                                                    </tr>
                                                                    <tr class="pi-vat-row">
                                                                        <td colspan="3">VAT (20%)</td>
                                                                        <td id="pi-vat">£0.00</td>
                                                                        <td></td>
                                                                    </tr>
                                                                    <tr class="pi-total-row">
                                                                        <td colspan="3"><strong>Total</strong></td>
                                                                        <td id="pi-grand-total"><strong>£0.00</strong></td>
                                                                        <td></td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                        <div class="pi-form-actions">
                                                            <button type="submit" class="pi-btn pi-btn-primary">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                                                                </svg>
                                                                Save Quote
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- DOCUMENTS TAB -->
                                        <div class="pi-tab-panel" id="tab-documents" role="tabpanel">
                                            <!-- PROPOSAL SECTION - Shows either Generate or Preview -->
                                            <div class="pi-proposal-section" id="pi-proposal-section">
                                                <!-- Proposal content loaded dynamically via JS -->
                                                <div class="pi-proposal-loading">
                                                    <div class="pi-skeleton pi-skeleton-text"></div>
                                                    <div class="pi-skeleton pi-skeleton-text pi-skeleton-short"></div>
                                                </div>
                                            </div>
                                            
                                            <!-- ATTACHMENTS SECTION -->
                                            <div class="pi-documents-card">
                                                <div class="pi-card-header">
                                                    <h3>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                                        </svg>
                                                        Attachments
                                                    </h3>
                                                </div>
                                                <div class="pi-card-body">
                                                    <div class="pi-upload-zone" id="pi-upload-zone">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                                        </svg>
                                                        <p>Drag & drop files here or <span class="pi-upload-link">browse</span></p>
                                                        <span class="pi-upload-hint">Supports: PDF, Images, Documents up to 10MB</span>
                                                        <form id="pi-attachments-form" enctype="multipart/form-data">
                                                            <input type="file" name="attachment" id="pi-file-input" multiple hidden />
                                                        </form>
                                                    </div>
                                                    
                                                    <div class="pi-attachments-grid" id="pi-attachments-list">
                                                        <!-- Attachments loaded dynamically -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- TASKS TAB -->
                                        <div class="pi-tab-panel" id="tab-tasks" role="tabpanel">
                                            <div class="pi-tasks-card">
                                                <div class="pi-card-header">
                                                    <h3>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                                        </svg>
                                                        Tasks & To-Do
                                                    </h3>
                                                    <button id="pi-add-task" class="pi-btn pi-btn-outline pi-btn-sm">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                                        </svg>
                                                        Add Task
                                                    </button>
                                                </div>
                                                <div class="pi-card-body">
                                                    <div class="pi-tasks-list" id="pi-tasks-list">
                                                        <div class="pi-empty-state">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                                            </svg>
                                                            <p>No tasks yet</p>
                                                            <span>Create tasks to track work for this lead</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- RIGHT COLUMN - ACTIVITY TIMELINE -->
                            <div class="pi-lead-col-sidebar">
                                <div class="pi-activity-card">
                                    <div class="pi-card-header">
                                        <h3>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                                            </svg>
                                            Activity Timeline
                                        </h3>
                                    </div>
                                    <div class="pi-card-body">
                                        <div class="pi-timeline" id="pi-history-list">
                                            <!-- Timeline items loaded dynamically -->
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="pi-communication-card">
                                    <div class="pi-card-header">
                                        <h3>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                            </svg>
                                            Quick Message
                                        </h3>
                                    </div>
                                    <div class="pi-card-body">
                                        <div class="pi-message-compose">
                                            <textarea placeholder="Type a quick message to the customer..." rows="3" id="pi-quick-message"></textarea>
                                            <div class="pi-message-actions">
                                                <button class="pi-btn pi-btn-icon" title="Attach file">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                                    </svg>
                                                </button>
                                                <button class="pi-btn pi-btn-primary pi-btn-sm" id="pi-send-message">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                                    </svg>
                                                    Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- TOAST NOTIFICATIONS -->
<div class="pi-toast-container" id="pi-toast-container"></div>

<!-- TASK MODAL -->
<div class="pi-modal-overlay" id="pi-task-modal">
    <div class="pi-modal">
        <div class="pi-modal-header">
            <h3>Add New Task</h3>
            <button class="pi-modal-close" id="pi-close-task-modal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="pi-modal-body">
            <form id="pi-task-form">
                <div class="pi-form-group">
                    <label for="task_title">Task Title</label>
                    <input type="text" name="task_title" id="task_title" placeholder="e.g., Schedule site visit" required />
                </div>
                <div class="pi-form-group">
                    <label for="task_due">Due Date</label>
                    <input type="date" name="task_due" id="task_due" />
                </div>
                <div class="pi-form-group">
                    <label for="task_priority">Priority</label>
                    <select name="task_priority" id="task_priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="pi-form-actions">
                    <button type="button" class="pi-btn pi-btn-ghost" id="pi-cancel-task">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>