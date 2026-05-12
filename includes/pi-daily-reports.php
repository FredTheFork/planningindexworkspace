<?php
/**
 * Planning Index CRM - Daily Reports Frontend
 * Comprehensive daily reports interface for construction operations
 */

if (!defined('ABSPATH')) exit;

function pi_daily_reports_shortcode($atts) {
    $atts = shortcode_atts(array(
        'job_id' => 0
    ), $atts);
    
    $job_id = intval($atts['job_id']);
    
    if (!$job_id) {
        return '<div class="pi-error">Job ID is required</div>';
    }
    
    ob_start();
    ?>
    <div id="pi-daily-reports-app" data-job-id="<?php echo $job_id; ?>">
        
        <!-- Real-time Dashboard Widgets -->
        <div class="pi-dr-dashboard-widgets">
            <div class="pi-dr-widget pi-dr-widget-headcount">
                <div class="pi-dr-widget-icon">👥</div>
                <div class="pi-dr-widget-content">
                    <div class="pi-dr-widget-label">On Site</div>
                    <div class="pi-dr-widget-value" id="pi-dr-headcount">-</div>
                </div>
            </div>
            <div class="pi-dr-widget pi-dr-widget-incidents">
                <div class="pi-dr-widget-icon">⚠️</div>
                <div class="pi-dr-widget-content">
                    <div class="pi-dr-widget-label">Open Incidents</div>
                    <div class="pi-dr-widget-value" id="pi-dr-incidents">-</div>
                </div>
            </div>
            <div class="pi-dr-widget pi-dr-widget-equipment">
                <div class="pi-dr-widget-icon">🔧</div>
                <div class="pi-dr-widget-content">
                    <div class="pi-dr-widget-label">Equipment Issues</div>
                    <div class="pi-dr-widget-value" id="pi-dr-equipment-issues">-</div>
                </div>
            </div>
            <div class="pi-dr-widget pi-dr-widget-score">
                <div class="pi-dr-widget-icon">📊</div>
                <div class="pi-dr-widget-content">
                    <div class="pi-dr-widget-label">Today's Score</div>
                    <div class="pi-dr-widget-value" id="pi-dr-score">-</div>
                </div>
            </div>
            <div class="pi-dr-widget pi-dr-widget-status">
                <div class="pi-dr-widget-icon">📋</div>
                <div class="pi-dr-widget-content">
                    <div class="pi-dr-widget-label">Status</div>
                    <div class="pi-dr-widget-value" id="pi-dr-status">-</div>
                </div>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <div class="pi-dr-nav">
            <button class="pi-dr-nav-tab active" data-tab="report">Daily Report</button>
            <button class="pi-dr-nav-tab" data-tab="checkin">Check In/Out</button>
            <button class="pi-dr-nav-tab" data-tab="analytics">Analytics</button>
            <button class="pi-dr-nav-tab" data-tab="archive">Archive</button>
        </div>
        
        <!-- Tab Content: Daily Report Form -->
        <div class="pi-dr-tab-content active" id="pi-dr-tab-report">
            
            <!-- Report Header -->
            <div class="pi-dr-header">
                <div class="pi-dr-header-left">
                    <h2>Daily Report</h2>
                    <div class="pi-dr-meta">
                        <span id="pi-dr-report-number">-</span>
                        <span>|</span>
                        <span id="pi-dr-report-date">-</span>
                        <span>|</span>
                        <span id="pi-dr-report-status" class="pi-dr-status-badge">Draft</span>
                    </div>
                </div>
                <div class="pi-dr-header-right">
                    <button class="pi-dr-btn pi-dr-btn-secondary" id="pi-dr-btn-save-draft">Save Draft</button>
                    <button class="pi-dr-btn pi-dr-btn-primary" id="pi-dr-btn-submit">Submit for Review</button>
                    <button class="pi-dr-btn pi-dr-btn-success" id="pi-dr-btn-approve" style="display:none;">Approve</button>
                </div>
            </div>
            
            <!-- Weather Section (existing component - logic preserved) -->
            <div class="pi-dr-section pi-dr-weather-section">
                <h3>Weather</h3>
                <div class="crm-weather-widget" id="crm-weather-widget">
                    <div class="crm-weather-header">
                        <span>Weather Forecast</span>
                        <button class="crm-weather-refresh" id="crm-weather-refresh" title="Refresh weather data">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="crm-weather-content" id="crm-weather-content">
                        <div class="crm-weather-loading" id="crm-weather-loading">
                            <svg class="crm-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="10"></circle>
                            </svg>
                            <span>Loading forecast...</span>
                        </div>
                        <div class="crm-weather-data" id="crm-weather-data" style="display:none;">
                            <div class="crm-weather-main">
                                <div class="crm-weather-icon" id="crm-weather-icon">
                                    <!-- Weather icon will be inserted here -->
                                </div>
                                <div class="crm-weather-temp" id="crm-weather-temp">--°C</div>
                            </div>
                            <div class="crm-weather-condition" id="crm-weather-condition">--</div>
                            <div class="crm-weather-details">
                                <div class="crm-weather-detail">
                                    <span class="crm-weather-detail-label">Wind</span>
                                    <span class="crm-weather-detail-value" id="crm-weather-wind">-- km/h</span>
                                </div>
                                <div class="crm-weather-detail">
                                    <span class="crm-weather-detail-label">Precip</span>
                                    <span class="crm-weather-detail-value" id="crm-weather-precip">-- mm</span>
                                </div>
                            </div>
                            <div class="crm-weather-recommendation" id="crm-weather-recommendation">--</div>
                            <div class="crm-weather-impact" id="crm-weather-impact">--</div>
                            <div class="crm-weather-forecast" id="crm-weather-forecast">
                                <!-- 3-day forecast will be inserted here -->
                            </div>
                        </div>
                        <div class="crm-weather-error" id="crm-weather-error" style="display:none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <p id="crm-weather-error-message">Unable to load weather</p>
                            <button class="pi-btn pi-btn-sm" id="crm-weather-set-location">Set Location</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Workforce Section -->
            <div class="pi-dr-section">
                <h3>Workforce</h3>
                <div class="pi-dr-subsection">
                    <h4>Trade Breakdown</h4>
                    <table class="pi-dr-table" id="pi-dr-labor-table">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Trade</th>
                                <th>Company</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Total Hours</th>
                                <th>Regular</th>
                                <th>Overtime</th>
                                <th>GPS</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-dr-labor-tbody">
                            <!-- Labor entries will be loaded here -->
                        </tbody>
                    </table>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-add-labor">+ Add Worker</button>
                </div>
                
                <div class="pi-dr-subsection">
                    <h4>Visitor Log</h4>
                    <table class="pi-dr-table" id="pi-dr-visitors-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Purpose</th>
                                <th>Arrival</th>
                                <th>Departure</th>
                                <th>Host</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-dr-visitors-tbody">
                            <!-- Visitor entries will be loaded here -->
                        </tbody>
                    </table>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-add-visitor">+ Add Visitor</button>
                </div>
                
                <div class="pi-dr-subsection">
                    <h4>Absentees</h4>
                    <textarea class="pi-dr-textarea" id="pi-dr-absentees" placeholder="List absent workers with reason..."></textarea>
                </div>
            </div>
            
            <!-- Work Performed Section -->
            <div class="pi-dr-section">
                <h3>Work Performed</h3>
                <div class="pi-dr-subsection">
                    <h4>Activity Log</h4>
                    <table class="pi-dr-table" id="pi-dr-activities-table">
                        <thead>
                            <tr>
                                <th>Location/Area</th>
                                <th>Trade/Company</th>
                                <th>Activity Type</th>
                                <th>Description</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>% Complete</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Cost Code</th>
                                <th>Delay</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-dr-activities-tbody">
                            <!-- Activities will be loaded here -->
                        </tbody>
                    </table>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-add-activity">+ Add Activity</button>
                </div>
            </div>
            
            <!-- Equipment Section -->
            <div class="pi-dr-section">
                <h3>Equipment</h3>
                <div class="pi-dr-subsection">
                    <h4>Equipment Log</h4>
                    <table class="pi-dr-table" id="pi-dr-equipment-table">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Hours Used</th>
                                <th>Operator</th>
                                <th>Fuel Notes</th>
                                <th>Downtime</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-dr-equipment-tbody">
                            <!-- Equipment entries will be loaded here -->
                        </tbody>
                    </table>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-add-equipment">+ Add Equipment</button>
                </div>
            </div>
            
            <!-- Materials & Deliveries Section -->
            <div class="pi-dr-section">
                <div class="pi-dr-section-header">
                    <h3>Materials & Deliveries</h3>
                    <div class="pi-dr-section-actions">
                        <button class="pi-dr-btn pi-dr-btn-secondary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.syncMaterials(); else console.warn('PI_Daily_Reports not yet loaded');">
                            <i class="fas fa-sync"></i> Sync from BOM
                        </button>
                        <button class="pi-dr-btn pi-dr-btn-primary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.openMaterialModal(); else console.warn('PI_Daily_Reports not yet loaded');">
                            <i class="fas fa-plus"></i> Add Material
                        </button>
                    </div>
                </div>
                
                <div class="pi-dr-subsection">
                    <h4>Delivery Log</h4>
                    <div class="pi-dr-table-wrapper">
                        <table class="pi-dr-table" id="pi-dr-materials-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Supplier</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Condition</th>
                                    <th>Receipt</th>
                                    <th>Cost</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pi-dr-materials-tbody">
                                <!-- Material entries will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="pi-dr-subsection">
                    <h4>Expected But Missing</h4>
                    <div class="pi-dr-table-wrapper">
                        <table class="pi-dr-table" id="pi-dr-missing-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Supplier</th>
                                    <th>Scheduled Date</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pi-dr-missing-tbody">
                                <!-- Missing items will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-add-missing">+ Add Missing Item</button>
                </div>
            </div>
            
            <!-- Safety & Quality Section -->
            <div class="pi-dr-section">
                <h3>Safety & Quality</h3>
                <div class="pi-dr-subsection">
                    <h4>Safety Walk Checklist</h4>
                    <div class="pi-dr-checklist" id="pi-dr-safety-checklist">
                        <label><input type="checkbox" class="pi-dr-checklist-item"> PPE compliance checked</label>
                        <label><input type="checkbox" class="pi-dr-checklist-item"> Site safety signage in place</label>
                        <label><input type="checkbox" class="pi-dr-checklist-item"> First aid kit accessible</label>
                        <label><input type="checkbox" class="pi-dr-checklist-item"> Fire extinguishers accessible</label>
                        <label><input type="checkbox" class="pi-dr-checklist-item"> Hazard identification completed</label>
                    </div>
                </div>
                
                <div class="pi-dr-subsection">
                    <h4>Incidents & Near Misses</h4>
                    <table class="pi-dr-table" id="pi-dr-incidents-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Severity</th>
                                <th>Description</th>
                                <th>Injured Party</th>
                                <th>Occurred</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-dr-incidents-tbody">
                            <!-- Incidents will be loaded here -->
                        </tbody>
                    </table>
                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-btn-danger" id="pi-dr-btn-add-incident">+ Report Incident</button>
                </div>
                
                <div class="pi-dr-subsection">
                    <h4>Inspections</h4>
                    <table class="pi-dr-table" id="pi-dr-inspections-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Result</th>
                                <th>Inspector</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-dr-inspections-tbody">
                            <!-- Inspections will be loaded here -->
                        </tbody>
                    </table>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-add-inspection">+ Add Inspection</button>
                </div>
                
                <div class="pi-dr-subsection">
                    <h4>Corrective Actions</h4>
                    <table class="pi-dr-table" id="pi-dr-corrective-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned To</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-dr-corrective-tbody">
                            <!-- Corrective actions will be loaded here -->
                        </tbody>
                    </table>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-add-corrective">+ Add Corrective Action</button>
                </div>
            </div>
            
            <!-- Photos Section -->
            <div class="pi-dr-section">
                <h3>Photos</h3>
                <div class="pi-dr-photo-upload">
                    <input type="file" id="pi-dr-photo-input" multiple accept="image/*" style="display:none;">
                    <button class="pi-dr-btn pi-dr-btn-secondary" id="pi-dr-btn-upload-photos">📷 Upload Photos</button>
                    <button class="pi-dr-btn pi-dr-btn-small" id="pi-dr-btn-take-photo">📱 Take Photo</button>
                </div>
                <div class="pi-dr-photo-grid" id="pi-dr-photo-grid">
                    <!-- Photos will be loaded here -->
                </div>
            </div>
            
            <!-- Issues & Blockers Section -->
            <div class="pi-dr-section">
                <h3>Issues & Blockers</h3>
                <div class="pi-dr-subsection">
                    <h4>Tomorrow's Blockers</h4>
                    <textarea class="pi-dr-textarea" id="pi-dr-tomorrow-blockers" placeholder="Describe any issues that may block tomorrow's work..."></textarea>
                </div>
                <div class="pi-dr-subsection">
                    <h4>RFIs Pending</h4>
                    <textarea class="pi-dr-textarea" id="pi-dr-rfis-pending" placeholder="List any pending RFIs..."></textarea>
                </div>
                <div class="pi-dr-subsection">
                    <h4>Change Work Observed</h4>
                    <textarea class="pi-dr-textarea" id="pi-dr-change-work" placeholder="Describe any change work observed..."></textarea>
                </div>
            </div>
            
            <!-- Notes & Observations Section -->
            <div class="pi-dr-section">
                <h3>Notes & Observations</h3>
                <div class="pi-dr-subsection">
                    <h4>General Notes</h4>
                    <textarea class="pi-dr-textarea" id="pi-dr-general-notes" placeholder="Add general notes about the day..."></textarea>
                </div>
                <div class="pi-dr-subsection">
                    <h4>Client/Owner Communication</h4>
                    <textarea class="pi-dr-textarea" id="pi-dr-client-comm" placeholder="Document client communications..."></textarea>
                </div>
            </div>
            
            <!-- Ratings Section -->
            <div class="pi-dr-section">
                <h3>Daily Ratings</h3>
                <div class="pi-dr-ratings-grid">
                    <div class="pi-dr-rating-item">
                        <label>Productivity (1-10)</label>
                        <input type="range" class="pi-dr-rating-slider" id="pi-dr-rating-productivity" min="1" max="10" value="5">
                        <span class="pi-dr-rating-value" id="pi-dr-rating-productivity-value">5</span>
                    </div>
                    <div class="pi-dr-rating-item">
                        <label>Safety (1-10)</label>
                        <input type="range" class="pi-dr-rating-slider" id="pi-dr-rating-safety" min="1" max="10" value="5">
                        <span class="pi-dr-rating-value" id="pi-dr-rating-safety-value">5</span>
                    </div>
                    <div class="pi-dr-rating-item">
                        <label>Quality (1-10)</label>
                        <input type="range" class="pi-dr-rating-slider" id="pi-dr-rating-quality" min="1" max="10" value="5">
                        <span class="pi-dr-rating-value" id="pi-dr-rating-quality-value">5</span>
                    </div>
                    <div class="pi-dr-rating-item">
                        <label>Site Conditions (1-10)</label>
                        <input type="range" class="pi-dr-rating-slider" id="pi-dr-rating-conditions" min="1" max="10" value="5">
                        <span class="pi-dr-rating-value" id="pi-dr-rating-conditions-value">5</span>
                    </div>
                </div>
                <div class="pi-dr-overall-score">
                    <h4>Overall Score: <span id="pi-dr-overall-score">-</span></h4>
                    <div class="pi-dr-score-grade" id="pi-dr-score-grade">-</div>
                </div>
                <div class="pi-dr-rating-justification">
                    <label>Rating Justification (optional)</label>
                    <textarea class="pi-dr-textarea" id="pi-dr-rating-justification" placeholder="Explain any rating adjustments..."></textarea>
                </div>
            </div>
            
        </div>
        
        <!-- Tab Content: Check In/Out -->
        <div class="pi-dr-tab-content" id="pi-dr-tab-checkin">
            <div class="pi-dr-checkin-panel">
                <div class="pi-dr-checkin-status">
                    <h2 id="pi-dr-checkin-status-text">Not Clocked In</h2>
                    <p id="pi-dr-checkin-time">-</p>
                </div>
                <div class="pi-dr-checkin-actions">
                    <button class="pi-dr-btn pi-dr-btn-success pi-dr-btn-large" id="pi-dr-btn-clock-in">📍 Clock In</button>
                    <button class="pi-dr-btn pi-dr-btn-warning pi-dr-btn-large" id="pi-dr-btn-break-start" style="display:none;">☕ Start Break</button>
                    <button class="pi-dr-btn pi-dr-btn-success pi-dr-btn-large" id="pi-dr-btn-break-end" style="display:none;">▶️ End Break</button>
                    <button class="pi-dr-btn pi-dr-btn-danger pi-dr-btn-large" id="pi-dr-btn-clock-out" style="display:none;">🏠 Clock Out</button>
                </div>
                <div class="pi-dr-checkin-gps">
                    <span id="pi-dr-gps-status">GPS: Checking...</span>
                </div>
            </div>
            
            <div class="pi-dr-section">
                <h3>Who's On Site</h3>
                <div class="pi-dr-onsite-list" id="pi-dr-onsite-list">
                    <!-- Onsite workers will be loaded here -->
                </div>
            </div>
            
            <div class="pi-dr-section">
                <h3>Shift Summary</h3>
                <div class="pi-dr-shift-summary">
                    <div class="pi-dr-summary-item">
                        <label>Total Hours</label>
                        <span id="pi-dr-total-hours">-</span>
                    </div>
                    <div class="pi-dr-summary-item">
                        <label>Regular Hours</label>
                        <span id="pi-dr-regular-hours">-</span>
                    </div>
                    <div class="pi-dr-summary-item">
                        <label>Overtime Hours</label>
                        <span id="pi-dr-overtime-hours">-</span>
                    </div>
                    <div class="pi-dr-summary-item">
                        <label>Break Time</label>
                        <span id="pi-dr-break-time">-</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab Content: Analytics -->
        <div class="pi-dr-tab-content" id="pi-dr-tab-analytics">
            <div class="pi-dr-analytics-controls">
                <select id="pi-dr-analytics-period">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                </select>
            </div>
            
            <div class="pi-dr-analytics-grid">
                <div class="pi-dr-chart-card">
                    <h4>Productivity Trend</h4>
                    <canvas id="pi-dr-chart-productivity"></canvas>
                </div>
                <div class="pi-dr-chart-card">
                    <h4>Labor Hours Breakdown</h4>
                    <canvas id="pi-dr-chart-labor"></canvas>
                </div>
                <div class="pi-dr-chart-card">
                    <h4>Safety Performance</h4>
                    <canvas id="pi-dr-chart-safety"></canvas>
                </div>
                <div class="pi-dr-chart-card">
                    <h4>Equipment Utilization</h4>
                    <canvas id="pi-dr-chart-equipment"></canvas>
                </div>
                <div class="pi-dr-chart-card">
                    <h4>Delay Analysis</h4>
                    <canvas id="pi-dr-chart-delays"></canvas>
                </div>
                <div class="pi-dr-chart-card">
                    <h4>Attendance & Punctuality</h4>
                    <canvas id="pi-dr-chart-attendance"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tab Content: Archive -->
        <div class="pi-dr-tab-content" id="pi-dr-tab-archive">
            <div class="pi-dr-archive-controls">
                <input type="text" id="pi-dr-archive-search" placeholder="Search reports...">
                <input type="date" id="pi-dr-archive-date-from">
                <input type="date" id="pi-dr-archive-date-to">
                <select id="pi-dr-archive-status">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                </select>
                <button class="pi-dr-btn pi-dr-btn-primary" id="pi-dr-btn-archive-search">Search</button>
                <button class="pi-dr-btn pi-dr-btn-secondary" id="pi-dr-btn-export-pdf">Export PDF</button>
                <button class="pi-dr-btn pi-dr-btn-secondary" id="pi-dr-btn-export-csv">Export CSV</button>
            </div>
            
            <div class="pi-dr-archive-list" id="pi-dr-archive-list">
                <!-- Archive entries will be loaded here -->
            </div>
        </div>
        
        <!-- Modals -->
        <div class="pi-dr-modal" id="pi-dr-modal-labor" style="display:none;">
            <div class="pi-dr-modal-content">
                <div class="pi-dr-modal-header">
                    <h3>Add Worker</h3>
                    <button class="pi-dr-modal-close">&times;</button>
                </div>
                <div class="pi-dr-modal-body">
                    <form id="pi-dr-form-labor">
                        <input type="hidden" id="pi-dr-labor-id">
                        <div class="pi-dr-form-group">
                            <label>Employee *</label>
                            <select id="pi-dr-labor-employee" required>
                                <option value="">Select Employee...</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Trade</label>
                            <input type="text" id="pi-dr-labor-trade" readonly>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Company</label>
                            <input type="text" id="pi-dr-labor-company" readonly>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Hourly Rate</label>
                            <input type="text" id="pi-dr-labor-rate" readonly>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Cost Code</label>
                            <input type="text" id="pi-dr-labor-cost-code">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Notes</label>
                            <textarea id="pi-dr-labor-notes"></textarea>
                        </div>
                        <button type="submit" class="pi-dr-btn pi-dr-btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="pi-dr-modal" id="pi-dr-modal-activity" style="display:none;">
            <div class="pi-dr-modal-content">
                <div class="pi-dr-modal-header">
                    <h3>Add Activity</h3>
                    <button class="pi-dr-modal-close">&times;</button>
                </div>
                <div class="pi-dr-modal-body">
                    <form id="pi-dr-form-activity">
                        <input type="hidden" id="pi-dr-activity-id">
                        <div class="pi-dr-form-group">
                            <label>Location/Area *</label>
                            <input type="text" id="pi-dr-activity-location" required>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Trade/Company</label>
                            <input type="text" id="pi-dr-activity-trade">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Activity Type</label>
                            <select id="pi-dr-activity-type">
                                <option value="construction">Construction</option>
                                <option value="installation">Installation</option>
                                <option value="inspection">Inspection</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="demolition">Demolition</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Description *</label>
                            <textarea id="pi-dr-activity-description" required></textarea>
                        </div>
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group">
                                <label>Quantity</label>
                                <input type="number" step="0.01" id="pi-dr-activity-quantity">
                            </div>
                            <div class="pi-dr-form-group">
                                <label>Unit</label>
                                <input type="text" id="pi-dr-activity-unit">
                            </div>
                            <div class="pi-dr-form-group">
                                <label>% Complete</label>
                                <input type="number" min="0" max="100" id="pi-dr-activity-percent">
                            </div>
                        </div>
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group">
                                <label>Start Time</label>
                                <input type="time" id="pi-dr-activity-start">
                            </div>
                            <div class="pi-dr-form-group">
                                <label>End Time</label>
                                <input type="time" id="pi-dr-activity-end">
                            </div>
                        </div>
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group">
                                <label>Cost Code</label>
                                <input type="text" id="pi-dr-activity-cost-code">
                            </div>
                            <div class="pi-dr-form-group">
                                <label>Phase/WBS</label>
                                <input type="text" id="pi-dr-activity-phase">
                            </div>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Delay Reason</label>
                            <select id="pi-dr-activity-delay-reason">
                                <option value="">None</option>
                                <option value="weather">Weather</option>
                                <option value="material">Material Delay</option>
                                <option value="labor">Labor Shortage</option>
                                <option value="equipment">Equipment Failure</option>
                                <option value="design">Design Change</option>
                                <option value="permit">Permit Delay</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Delay Hours</label>
                            <input type="number" step="0.5" id="pi-dr-activity-delay-hours">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Blockers/Issues</label>
                            <textarea id="pi-dr-activity-blockers"></textarea>
                        </div>
                        <button type="submit" class="pi-dr-btn pi-dr-btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="pi-dr-modal" id="pi-dr-modal-equipment" style="display:none;">
            <div class="pi-dr-modal-content">
                <div class="pi-dr-modal-header">
                    <h3>Add Equipment</h3>
                    <button class="pi-dr-modal-close">&times;</button>
                </div>
                <div class="pi-dr-modal-body">
                    <form id="pi-dr-form-equipment">
                        <input type="hidden" id="pi-dr-equipment-id">
                        <input type="hidden" id="pi-dr-equipment-linked-id">
                        <div class="pi-dr-form-group">
                            <label>Job Equipment</label>
                            <select id="pi-dr-equipment-select">
                                <option value="">-- Select from job equipment --</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Equipment Name *</label>
                            <input type="text" id="pi-dr-equipment-name" required>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Type</label>
                            <input type="text" id="pi-dr-equipment-type">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Ownership Type</label>
                            <select id="pi-dr-equipment-ownership">
                                <option value="owned">Owned</option>
                                <option value="rented">Rented</option>
                                <option value="subcontractor">Subcontractor</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Status</label>
                            <select id="pi-dr-equipment-status">
                                <option value="active">Active</option>
                                <option value="idle">Idle</option>
                                <option value="broken">Broken</option>
                                <option value="maintenance">In Maintenance</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Hours Used Today</label>
                            <input type="number" step="0.5" id="pi-dr-equipment-hours">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Meter Reading</label>
                            <input type="number" step="0.1" id="pi-dr-equipment-meter">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Operator</label>
                            <input type="text" id="pi-dr-equipment-operator">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Fuel Notes</label>
                            <textarea id="pi-dr-equipment-fuel"></textarea>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Maintenance Issues</label>
                            <textarea id="pi-dr-equipment-maintenance"></textarea>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Downtime Reason</label>
                            <input type="text" id="pi-dr-equipment-downtime-reason">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Downtime Hours</label>
                            <input type="number" step="0.5" id="pi-dr-equipment-downtime-hours">
                        </div>
                        <button type="submit" class="pi-dr-btn pi-dr-btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="pi-dr-modal" id="pi-dr-modal-material" style="display:none;">
            <div class="pi-dr-modal-content">
                <div class="pi-dr-modal-header">
                    <h3>Add Material/Delivery</h3>
                    <button class="pi-dr-modal-close">&times;</button>
                </div>
                <div class="pi-dr-modal-body">
                    <form id="pi-dr-form-material">
                        <input type="hidden" id="pi-dr-material-id">
                        <div class="pi-dr-form-group">
                            <label>Supplier *</label>
                            <input type="text" id="pi-dr-material-supplier" required>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>PO Number</label>
                            <input type="text" id="pi-dr-material-po">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Material Description *</label>
                            <textarea id="pi-dr-material-description" required></textarea>
                        </div>
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group">
                                <label>Quantity *</label>
                                <input type="number" step="0.01" id="pi-dr-material-quantity" required>
                            </div>
                            <div class="pi-dr-form-group">
                                <label>Unit</label>
                                <input type="text" id="pi-dr-material-unit">
                            </div>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Condition on Arrival</label>
                            <select id="pi-dr-material-condition">
                                <option value="good">Good</option>
                                <option value="damaged">Damaged</option>
                                <option value="incomplete">Incomplete</option>
                                <option value="wrong">Wrong Item</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Received By</label>
                            <input type="text" id="pi-dr-material-received-by">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Expected but Missing?</label>
                            <input type="checkbox" id="pi-dr-material-missing">
                        </div>
                        <div class="pi-dr-form-group" id="pi-dr-material-scheduled-date-group" style="display:none;">
                            <label>Scheduled Delivery Date</label>
                            <input type="date" id="pi-dr-material-scheduled-date">
                        </div>
                        <button type="submit" class="pi-dr-btn pi-dr-btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="pi-dr-modal" id="pi-dr-modal-incident" style="display:none;">
            <div class="pi-dr-modal-content">
                <div class="pi-dr-modal-header">
                    <h3>Report Incident</h3>
                    <button class="pi-dr-modal-close">&times;</button>
                </div>
                <div class="pi-dr-modal-body">
                    <form id="pi-dr-form-incident">
                        <input type="hidden" id="pi-dr-incident-id">
                        <div class="pi-dr-form-group">
                            <label>Record Type</label>
                            <select id="pi-dr-incident-type-record">
                                <option value="incident">Incident</option>
                                <option value="near_miss">Near Miss</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Incident Type</label>
                            <select id="pi-dr-incident-type">
                                <option value="injury">Injury</option>
                                <option value="first_aid">First Aid Required</option>
                                <option value="property_damage">Property Damage</option>
                                <option value="environmental">Environmental</option>
                                <option value="safety_violation">Safety Violation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Severity</label>
                            <select id="pi-dr-incident-severity">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Description *</label>
                            <textarea id="pi-dr-incident-description" required></textarea>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Location/Area</label>
                            <input type="text" id="pi-dr-incident-location">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Injured Party</label>
                            <input type="text" id="pi-dr-incident-injured">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Witness Names</label>
                            <textarea id="pi-dr-incident-witnesses"></textarea>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Immediate Actions</label>
                            <textarea id="pi-dr-incident-actions"></textarea>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Root Cause</label>
                            <textarea id="pi-dr-incident-cause"></textarea>
                        </div>
                        <button type="submit" class="pi-dr-btn pi-dr-btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="pi-dr-modal" id="pi-dr-modal-visitor" style="display:none;">
            <div class="pi-dr-modal-content">
                <div class="pi-dr-modal-header">
                    <h3>Add Visitor</h3>
                    <button class="pi-dr-modal-close">&times;</button>
                </div>
                <div class="pi-dr-modal-body">
                    <form id="pi-dr-form-visitor">
                        <input type="hidden" id="pi-dr-visitor-id">
                        <div class="pi-dr-form-group">
                            <label>Name *</label>
                            <input type="text" id="pi-dr-visitor-name" required>
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Company</label>
                            <input type="text" id="pi-dr-visitor-company">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Purpose</label>
                            <input type="text" id="pi-dr-visitor-purpose">
                        </div>
                        <div class="pi-dr-form-group">
                            <label>Host</label>
                            <input type="text" id="pi-dr-visitor-host">
                        </div>
                        <button type="submit" class="pi-dr-btn pi-dr-btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Loading Overlay -->
        <div class="pi-dr-loading" id="pi-dr-loading" style="display:none;">
            <div class="pi-dr-spinner"></div>
            <span>Loading...</span>
        </div>
        
        <!-- Toast Notifications -->
        <div class="pi-dr-toast-container" id="pi-dr-toast-container"></div>
        
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pi_daily_reports', 'pi_daily_reports_shortcode');

// Run migrations when plugin loads
add_action('init', function() {
    if (is_admin() || isset($_GET['page']) && strpos($_GET['page'], 'daily-reports') !== false) {
        $database = PI_Daily_Reports_Database::get_instance();
        $database->run_migrations();
    }
});

// Also run migrations on admin_init for safety
add_action('admin_init', function() {
    $database = PI_Daily_Reports_Database::get_instance();
    $database->run_migrations();
});
