/**
 * Planning Index CRM - Team & Timesheets Module v2.3.4
 * Comprehensive construction team management and time tracking
 * 
 * Features:
 * - Overview: Stats, charts, activity feed, crew snapshot
 * - Team Members: Full employee management with profiles
 * - Timesheets: Entry, approval workflow, weekly grid view
 * - Integrations: Tasks, Calendar, Expenses
 * 
 * @version 2.3.4 - Fixed UTC timestamp parsing in timeAgo, added backend timestamp debugging
 */

(function($) {
    'use strict';

    // ============================================
    // API CLIENT
    // ============================================
    const TeamAPI = {
        base: '/wp-json/pi/v1',
        nonce: PI_Job_CRM?.nonce || PI_Job?.nonce || '',

        request(endpoint, options = {}) {
            const url = this.base + endpoint;
            const config = {
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                },
                ...options
            };
            
            return fetch(url, config).then(async r => {
                const data = await r.json();
                if (!r.ok) return Promise.reject(data);
                return data;
            }).catch(err => {
                console.error('[TeamAPI] Error:', err);
                throw err;
            });
        },

        // Dashboard
        getDashboardStats(jobId = 0) {
            const query = jobId ? `?job_id=${jobId}` : '';
            return this.request(`/team-dashboard/stats${query}`);
        },
        getWeeklyHours(jobId = 0) {
            const query = jobId ? `?job_id=${jobId}` : '';
            return this.request(`/team-dashboard/weekly-hours${query}`);
        },
        getActivity(limit = 20, jobId = 0) {
            const query = jobId ? `?job_id=${jobId}&limit=${limit}` : `?limit=${limit}`;
            return this.request(`/team-dashboard/activity${query}`);
        },
        // Log activity to job timeline (integrates with job-single.js pattern)
        logActivity(jobId, message, type = 'team') {
            if (!jobId) {
                console.log('[TeamAPI] Skipping activity log - no job_id');
                return Promise.resolve({ skipped: true });
            }
            return this.request(`/jobs/${jobId}/activity`, {
                method: 'POST',
                body: JSON.stringify({ message, type })
            });
        },
        exportTimesheets(params = {}) {
            const query = new URLSearchParams(params);
            return this.request(`/team-dashboard/export?${query}`);
        },

        // Employees
        getEmployees(jobId = 0, params = {}) {
            // If jobId provided, filter employees by that job
            const queryParams = jobId ? { job_id: jobId, ...params } : params;
            const query = new URLSearchParams(queryParams);
            return this.request(`/employees?${query}`);
        },
        getEmployee(id) {
            return this.request(`/employees/${id}`);
        },
        getEmployeeStats(id) {
            return this.request(`/employees/${id}/stats`);
        },
        createEmployee(data) {
            return this.request('/employees', { method: 'POST', body: JSON.stringify(data) });
        },
        updateEmployee(id, data) {
            return this.request(`/employees/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        deleteEmployee(id) {
            return this.request(`/employees/${id}`, { method: 'DELETE' });
        },
        getEmployeeStats(id) {
            return this.request(`/employees/${id}/stats`);
        },
        bulkEmployeeAction(data) {
            return this.request('/employees/bulk', { method: 'POST', body: JSON.stringify(data) });
        },
        assignTeamMember(data) {
            return this.request('/team', { method: 'POST', body: JSON.stringify(data) });
        },

        // Crews - Independent/Reusable groups
        getCrews(jobId = 0) {
            // If jobId provided, get crews assigned to that job
            if (jobId) {
                return this.request(`/crews/assigned/${jobId}`);
            }
            return this.request('/crews');
        },
        getCrew(id) {
            return this.request(`/crews/${id}`);
        },
        createCrew(data) {
            return this.request('/crews', { method: 'POST', body: JSON.stringify(data) });
        },
        updateCrew(id, data) {
            return this.request(`/crews/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        // Crew assignments to jobs
        getAvailableCrews(jobId) {
            return this.request(`/crews/available?job_id=${jobId}`);
        },
        getJobCrews(jobId) {
            return this.request(`/crews/assigned/${jobId}`);
        },
        assignCrewToJob(crewId, jobId, options = {}) {
            return this.request('/crews/assign', { 
                method: 'POST', 
                body: JSON.stringify({ crew_id: crewId, job_id: jobId, ...options })
            });
        },
        unassignCrewFromJob(assignmentId, removeMembers = false) {
            return this.request(`/crews/unassign/${assignmentId}`, {
                method: 'POST',
                body: JSON.stringify({ remove_members: removeMembers })
            });
        },

        // Timesheets
        getTimesheets(jobId, params = {}) {
            // Only include job_id if explicitly provided (null/undefined = global view)
            const queryParams = jobId ? { job_id: jobId, ...params } : params;
            const query = new URLSearchParams(queryParams);
            return this.request(`/timesheets?${query}`);
        },
        createTimesheet(data) {
            return this.request('/timesheets', { method: 'POST', body: JSON.stringify(data) });
        },
        updateTimesheet(id, data) {
            return this.request(`/timesheets/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        deleteTimesheet(id) {
            return this.request(`/timesheets/${id}`, { method: 'DELETE' });
        },
        getTimesheetSummary(jobId) {
            return this.request(`/timesheets/summary?job_id=${jobId}`);
        },

        // Approvals
        getPendingApprovals(jobId = null) {
            const query = jobId ? `?job_id=${jobId}` : '';
            return this.request(`/approvals/pending${query}`);
        },
        approveTimesheet(id, data = {}) {
            return this.request(`/approvals/${id}/approve`, { method: 'POST', body: JSON.stringify(data) });
        },
        rejectTimesheet(id, data = {}) {
            return this.request(`/approvals/${id}/reject`, { method: 'POST', body: JSON.stringify(data) });
        },
        bulkApproval(data) {
            return this.request('/approvals/bulk', { method: 'POST', body: JSON.stringify(data) });
        },

        // Cost Codes
        getCostCodes() {
            return this.request('/cost-codes');
        },

        // Tasks integration
        getTasks() {
            return CRM_API.getTasks ? CRM_API.getTasks(this.jobId) : Promise.resolve([]);
        }
    };

    // ============================================
    // STATE MANAGEMENT
    // ============================================
    const TeamState = {
        currentTab: 'overview',
        employees: [],
        crews: [],
        timesheets: [],
        approvals: [],
        costCodes: [],
        currentUser: null,
        selectedEmployees: new Set(),
        selectedTimesheets: new Set(),
        filters: {
            employees: { role: '', trade: '', status: '', search: '', job: '' },
            timesheets: { startDate: '', endDate: '', employee: '', status: '', job: '' }
        },
        pagination: {
            employees: { page: 1, perPage: 20, total: 0 },
            timesheets: { page: 1, perPage: 20, total: 0 }
        },
        jobs: [], // Will be populated from existing job data

        async init() {
            // Initialize system - ensure database tables exist
            await this.initializeSystem();
            
            // Detect if we're on a job page and set job filter
            const jobId = this.getCurrentJobId();
            if (jobId) {
                console.log('[TeamApp] Job page detected, setting job filter:', jobId);
                this.filters.employees.job = jobId;
                this.filters.timesheets.job = jobId;
            }
            
            this.loadCostCodes();
            this.loadTabData('overview');
            this.setupEventListeners();
        },
        
        // Get current job ID if on a job page
        getCurrentJobId() {
            // Check various sources for job ID
            if (typeof PI_Job !== 'undefined' && PI_Job?.job_id) {
                return PI_Job.job_id;
            }
            if (typeof PI_Job_CRM !== 'undefined' && PI_Job_CRM?.job_id) {
                return PI_Job_CRM.job_id;
            }
            if (typeof window.piJobId !== 'undefined' && window.piJobId) {
                return window.piJobId;
            }
            // Check URL for job parameter
            const urlParams = new URLSearchParams(window.location.search);
            const jobParam = urlParams.get('job_id');
            if (jobParam) return parseInt(jobParam);
            
            return null;
        },
        
        async initializeSystem() {
            try {
                console.log('[TeamApp] Initializing system...');
                const result = await TeamAPI.request('/team-system/init');
                console.log('[TeamApp] System initialized:', result);
            } catch (error) {
                console.error('[TeamApp] System initialization failed:', error);
                // Continue anyway - tables might already exist
            }
        },

        async loadCostCodes() {
            try {
                this.costCodes = await TeamAPI.getCostCodes();
            } catch (e) {
                console.error('Failed to load cost codes:', e);
            }
        },

        async loadTabData(tabName) {
            this.currentTab = tabName;
            
            switch(tabName) {
                case 'overview':
                    await this.loadOverviewData();
                    break;
                case 'team':
                    await this.loadTeamData();
                    break;
                case 'timesheets':
                    await this.loadTimesheetsData();
                    break;
            }
        },

        async loadOverviewData() {
            try {
                // Get job_id from filters - this is set in init() when on a job page
                const jobId = this.filters.employees.job || this.filters.timesheets.job || 0;
                console.log('[TeamApp] Loading overview data for job_id:', jobId);
                
                // Use individual try/catch for each call to identify failures
                let stats = null, weeklyHours = null, activity = null, crews = null;
                let errors = [];
                
                try {
                    stats = await TeamAPI.getDashboardStats(jobId);
                    console.log('[TeamApp] Dashboard stats loaded:', stats);
                } catch (e) {
                    console.error('[TeamApp] Dashboard stats failed:', e);
                    errors.push('stats: ' + (e.message || e));
                    stats = { total_employees: 0, week_hours: 0, overtime_hours: 0, pending_approvals: 0, week_start: '-', week_end: '-' };
                }
                
                try {
                    weeklyHours = await TeamAPI.getWeeklyHours(jobId);
                    console.log('[TeamApp] Weekly hours loaded:', weeklyHours);
                    // Ensure we have the correct structure
                    if (!weeklyHours || !weeklyHours.daily) {
                        console.warn('[TeamApp] Weekly hours missing daily array, creating empty structure');
                        weeklyHours = { daily: [] };
                    }
                } catch (e) {
                    console.error('[TeamApp] Weekly hours failed:', e);
                    errors.push('weeklyHours: ' + (e.message || e));
                    weeklyHours = { daily: [] };
                }
                
                try {
                    // Fetch both team activity and job activity
                    let teamActivity = await TeamAPI.getActivity(10, jobId);
                    console.log('[TeamApp] Team activity loaded:', teamActivity);
                    
                    // Debug: Log all timestamps
                    if (teamActivity && teamActivity.length > 0) {
                        console.log('[TeamApp] Team activity timestamps:');
                        teamActivity.forEach((item, i) => {
                            console.log(`  [${i}] timestamp: "${item.timestamp}", type: ${typeof item.timestamp}`);
                        });
                    }
                    
                    // If on a job page, also fetch job activity from job meta
                    let jobActivity = [];
                    if (jobId) {
                        try {
                            console.log('[TeamApp] Fetching job data for activity, jobId:', jobId);
                            const jobData = await TeamAPI.request(`/jobs/${jobId}`);
                            console.log('[TeamApp] Job data received:', jobData);
                            if (jobData && jobData.activity && Array.isArray(jobData.activity)) {
                                console.log('[TeamApp] Job has', jobData.activity.length, 'activity entries');
                                // Debug job activity timestamps
                                console.log('[TeamApp] Job activity raw entries:');
                                jobData.activity.slice(-10).forEach((entry, i) => {
                                    const str = typeof entry === 'string' ? entry : (entry.text || '');
                                    console.log(`  [${i}] raw: "${str}"`);
                                });
                                // Parse job activity format (timestamp: message)
                                jobActivity = jobData.activity.slice(-10).map((entry, idx) => {
                                    const str = typeof entry === 'string' ? entry : (entry.text || '');
                                    const parts = str.split(/:\s/);
                                    const timestamp = parts.shift() || '';
                                    const message = parts.join(': ') || str;
                                    return {
                                        id: 'job_' + idx,
                                        type: 'job',
                                        user_name: 'System',
                                        description: message,
                                        timestamp: timestamp,
                                        status: 'completed'
                                    };
                                }).reverse();
                                console.log('[TeamApp] Job activity loaded:', jobActivity);
                            } else {
                                console.log('[TeamApp] No activity in job data or empty array');
                            }
                        } catch (jobErr) {
                            console.warn('[TeamApp] Could not load job activity:', jobErr);
                        }
                    }
                    
                    // Merge and sort all activity
                    activity = [...teamActivity, ...jobActivity];
                    console.log('[TeamApp] Before sort - all timestamps:');
                    activity.forEach((item, i) => {
                        console.log(`  [${i}] "${item.timestamp}"`);
                    });
                    activity.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
                    activity = activity.slice(0, 10);
                    
                    console.log('[TeamApp] Merged activity:', activity);
                } catch (e) {
                    console.error('[TeamApp] Activity failed:', e);
                    errors.push('activity: ' + (e.message || e));
                    activity = [];
                }
                
                try {
                    crews = await TeamAPI.getCrews(jobId);
                    console.log('[TeamApp] Crews loaded:', crews);
                } catch (e) {
                    console.error('[TeamApp] Crews failed:', e);
                    errors.push('crews: ' + (e.message || e));
                    crews = [];
                }
                
                if (errors.length > 0) {
                    console.warn('[TeamApp] Some overview data failed to load:', errors);
                }
                
                this.renderOverview(stats, weeklyHours, activity, crews);
            } catch (error) {
                console.error('Failed to load overview data:', error);
                this.renderOverviewError();
            }
        },

        async loadTeamData() {
            try {
                console.log('[TeamApp] Loading team data with filters:', this.filters.employees);
                
                const params = {};
                if (this.filters.employees.status) params.status = this.filters.employees.status;
                if (this.filters.employees.role) params.role = this.filters.employees.role;
                if (this.filters.employees.trade) params.trade = this.filters.employees.trade;
                if (this.filters.employees.search) params.search = this.filters.employees.search;
                
                // Get job filter - if set, only show employees for that job
                const jobId = this.filters.employees.job || 0;
                
                // Load both filtered (job-specific) and all account employees
                const [response, allAccountResponse] = await Promise.all([
                    TeamAPI.getEmployees(jobId, params),
                    TeamAPI.getEmployees(0, {}) // Get all account employees for Add Member modal
                ]);
                
                console.log('[TeamApp] Raw API response:', response);
                
                // Ensure we always have an array for job-specific employees
                let employees = [];
                if (Array.isArray(response)) {
                    employees = response;
                } else if (response && typeof response === 'object') {
                    employees = response.data || response.employees || response.results || [];
                }
                
                // Store all account employees for "Select Existing" modal
                let allEmployees = [];
                if (Array.isArray(allAccountResponse)) {
                    allEmployees = allAccountResponse;
                } else if (allAccountResponse && typeof allAccountResponse === 'object') {
                    allEmployees = allAccountResponse.data || allAccountResponse.employees || allAccountResponse.results || [];
                }

                console.log('[TeamApp] Processed employees:', employees.length, 'All account employees:', allEmployees.length);

                this.employees = employees;
                this.allEmployees = allEmployees; // Store for Select Existing modal
                this.renderTeamTable();

                // Load hours for each employee after rendering
                setTimeout(() => this.loadEmployeeHours(), 100);
            } catch (error) {
                console.error('[TeamApp] Failed to load team data:', error);
                this.showToast('Failed to load team members', 'error');
                this.employees = [];
                this.renderTeamTable();
            }
        },

        async loadTimesheetsData() {
            try {
                // Load timesheets - either filtered by job or all timesheets
                const jobId = this.filters.timesheets.job || 0;
                
                // Get approvals with job filter if applicable
                const approvals = await TeamAPI.getPendingApprovals(jobId > 0 ? jobId : null);
                this.approvals = approvals || { approvals: [], can_approve: false };

                console.log(`[TeamApp] Loading timesheets data for job: ${jobId || 'global'}`);
                const params = {
                    start_date: this.filters.timesheets.startDate || this.getWeekStart(),
                    end_date: this.filters.timesheets.endDate || this.getWeekEnd(),
                    worker_id: this.filters.timesheets.employee || ''
                };

                // Determine if we're on a job page or global page
                const isJobPage = jobId > 0;
                let timesheets;

                if (isJobPage) {
                    // Job-specific view: only get timesheets for this job
                    console.log(`[TeamApp] Loading timesheets for job ${jobId}`);
                    timesheets = await TeamAPI.getTimesheets(jobId, params);
                } else {
                    // Global view: get ALL timesheets from ALL jobs
                    console.log('[TeamApp] Loading all timesheets (global view)');
                    params.all_jobs = '1';
                    // Don't pass job_id for global view
                    timesheets = await TeamAPI.getTimesheets(null, params);
                }

                this.timesheets = timesheets || [];
                console.log(`[TeamApp] Loaded ${this.timesheets.length} timesheets`);

                this.renderTimesheetsTable();
            } catch (error) {
                console.error('Failed to load timesheets data:', error);
                this.timesheets = [];
                this.renderTimesheetsTable();
            }
        },

        setupEventListeners() {
            // Tab switching
            $(document).on('click', '.tt-tab', (e) => {
                const tab = $(e.currentTarget).data('tab');
                this.switchTab(tab);
            });

            // Search
            $(document).on('input', '#tt-search', this.debounce((e) => {
                const search = e.target.value;
                if (this.currentTab === 'team') {
                    this.filters.employees.search = search;
                    this.loadTeamData();
                }
            }, 300));

            // Employee filters
            $(document).on('change', '.tt-filter-role, .tt-filter-trade, .tt-filter-status', () => {
                this.filters.employees.role = $('.tt-filter-role').val();
                this.filters.employees.trade = $('.tt-filter-trade').val();
                this.filters.employees.status = $('.tt-filter-status').val();
                this.loadTeamData();
            });

            // Selection
            $(document).on('change', '.tt-select-row', (e) => {
                const id = $(e.currentTarget).data('id');
                if (e.currentTarget.checked) {
                    this.selectedEmployees.add(id);
                } else {
                    this.selectedEmployees.delete(id);
                }
                this.updateBulkActions();
            });

            $(document).on('change', '.tt-select-all', (e) => {
                const checked = e.currentTarget.checked;
                $('.tt-select-row').each((i, el) => {
                    const id = $(el).data('id');
                    el.checked = checked;
                    if (checked) {
                        this.selectedEmployees.add(id);
                    } else {
                        this.selectedEmployees.delete(id);
                    }
                });
                this.updateBulkActions();
            });

            // Add Member button (event delegation for dynamically rendered content)
            $(document).on('click', '.tt-add-member-btn', () => {
                this.openAddMemberModal();
            });

            // Timesheet filters
            $(document).on('change', '#tt-filter-start, #tt-filter-end, #tt-filter-employee, #tt-filter-status', () => {
                this.filters.timesheets.startDate = $('#tt-filter-start').val();
                this.filters.timesheets.endDate = $('#tt-filter-end').val();
                this.filters.timesheets.employee = $('#tt-filter-employee').val();
                this.filters.timesheets.status = $('#tt-filter-status').val();
                this.loadTimesheetsData();
            });

            // Timesheet selection
            $(document).on('change', '.tt-select-timesheet-row', (e) => {
                const id = $(e.currentTarget).data('id');
                if (e.currentTarget.checked) {
                    this.selectedTimesheets.add(id);
                } else {
                    this.selectedTimesheets.delete(id);
                }
                this.updateTimesheetBulkActions();
            });

            $(document).on('change', '.tt-select-all-timesheets', (e) => {
                const checked = e.currentTarget.checked;
                $('.tt-select-timesheet-row').each((i, el) => {
                    const id = $(el).data('id');
                    el.checked = checked;
                    if (checked) {
                        this.selectedTimesheets.add(id);
                    } else {
                        this.selectedTimesheets.delete(id);
                    }
                });
                this.updateTimesheetBulkActions();
            });

            // New Entry button
            $(document).on('click', '.tt-new-entry-btn', () => {
                this.openNewEntryModal();
            });

            // Bulk action buttons for timesheets
            $(document).on('click', '.tt-bulk-approve-btn', () => {
                this.bulkApprove();
            });
            $(document).on('click', '.tt-bulk-reject-btn', () => {
                this.bulkReject();
            });
            $(document).on('click', '.tt-clear-ts-selection-btn', () => {
                this.clearTimesheetSelection();
            });

            // Toggle view button
            $(document).on('click', '.tt-toggle-grid-btn', () => {
                this.toggleTimesheetView();
            });

            // Export button
            $(document).on('click', '.tt-export-timesheets-btn', () => {
                this.exportTimesheets();
            });

            // Timesheet entry action buttons (using event delegation)
            $(document).on('click', '.tt-approve-entry-btn', (e) => {
                const id = $(e.currentTarget).data('id');
                this.approveEntry(id);
            });
            $(document).on('click', '.tt-reject-entry-btn', (e) => {
                const id = $(e.currentTarget).data('id');
                this.rejectEntry(id);
            });
            $(document).on('click', '.tt-edit-entry-btn', (e) => {
                const id = $(e.currentTarget).data('id');
                this.editEntry(id);
            });

        },

        switchTab(tab) {
            $('.tt-tab').removeClass('active');
            $(`.tt-tab[data-tab="${tab}"]`).addClass('active');
            $('.tt-tab-panel').removeClass('active');
            $(`#tt-panel-${tab}`).addClass('active');
            this.loadTabData(tab);
        },

        // ============================================
        // RENDER: OVERVIEW TAB
        // ============================================
        renderOverview(stats, weeklyHours, activity, crews) {
            console.log('[TeamApp] renderOverview called with activity:', activity);
            console.log('[TeamApp] activity length:', activity?.length || 0);
            
            // Ensure activity is an array
            if (!activity || !Array.isArray(activity)) {
                console.warn('[TeamApp] Activity is not an array in renderOverview, fixing');
                activity = [];
            }
            
            const html = `
                <div class="tt-overview-content">
                    <!-- Stats Grid -->
                    <div class="tt-stats-grid">
                        <div class="tt-stat-card">
                            <div class="tt-stat-header">
                                <span class="tt-stat-label">Total Team Members</span>
                                <div class="tt-stat-icon blue">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="tt-stat-value">${stats.total_employees}</div>
                            <div class="tt-stat-subtitle">Active employees</div>
                        </div>

                        <div class="tt-stat-card">
                            <div class="tt-stat-header">
                                <span class="tt-stat-label">Hours This Week</span>
                                <div class="tt-stat-icon purple">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="tt-stat-value">${stats.week_hours.toFixed(1)}</div>
                            <div class="tt-stat-subtitle">${stats.week_start} to ${stats.week_end}</div>
                        </div>

                        <div class="tt-stat-card">
                            <div class="tt-stat-header">
                                <span class="tt-stat-label">Overtime Hours</span>
                                <div class="tt-stat-icon ${stats.overtime_hours > 20 ? 'red' : 'amber'}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="tt-stat-value">${stats.overtime_hours.toFixed(1)}</div>
                            <div class="tt-stat-subtitle">${stats.overtime_hours > 20 ? 'High overtime - review needed' : 'Within normal range'}</div>
                        </div>
                    </div>

                    <!-- Charts and Activity Row -->
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">
                        <!-- Weekly Hours Chart -->
                        <div class="tt-chart-card">
                            <div class="tt-chart-header">
                                <h3 class="tt-chart-title">Team Performance - Hours by Day</h3>
                                <div class="tt-chart-actions">
                                    <button class="tt-btn tt-btn-secondary tt-btn-sm" onclick="TeamApp.viewCalendar()">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        View Calendar
                                    </button>
                                </div>
                            </div>
                            <div class="tt-chart-container" id="tt-weekly-chart">
                                ${this.renderWeeklyChart(weeklyHours.daily)}
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="tt-activity-card" style="min-height: 300px;">
                            <div class="tt-activity-header">
                                <h3 class="tt-activity-title">Recent Activity</h3>
                                <a href="#" class="tt-btn tt-btn-ghost tt-btn-sm" onclick="TeamApp.switchTab('timesheets'); return false;">View All</a>
                            </div>
                            <div class="tt-activity-list" id="tt-activity-list" style="max-height: 350px; overflow-y: auto; min-height: 200px;">
                                ${activity && activity.length > 0 
                                    ? activity.map((item, index) => {
                                        console.log(`[TeamApp] Rendering activity item ${index}:`, item);
                                        return this.renderActivityItem(item);
                                      }).join('') 
                                    : '<div class="tt-empty-state" style="padding: 40px 20px; text-align: center; color: var(--tt-gray-500);"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 12px; opacity: 0.5;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><div>No recent activity</div></div>'}
                            </div>
                        </div>
                    </div>

                    <!-- Crews Snapshot -->
                    <div class="tt-chart-card">
                        <div class="tt-chart-header">
                            <h3 class="tt-chart-title">Crews On Site Today</h3>
                            <span class="tt-badge tt-badge-blue">${crews.length} Active Crews</span>
                        </div>
                        <div class="tt-crews-grid">
                            ${crews.slice(0, 3).map(crew => this.renderCrewCard(crew)).join('')}
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="tt-filters-bar" style="margin-top: 24px;">
                        <span class="tt-filter-label">Quick Links:</span>
                        <button class="tt-btn tt-btn-secondary tt-btn-sm" onclick="TeamApp.viewCalendar()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            Calendar
                        </button>
                        <button class="tt-btn tt-btn-secondary tt-btn-sm" onclick="TeamApp.viewTasks()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            Tasks
                        </button>
                        <button class="tt-btn tt-btn-secondary tt-btn-sm" onclick="TeamApp.viewExpenses()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            Expenses
                        </button>
                        ${stats.pending_approvals > 0 ? `
                            <button class="tt-btn tt-btn-danger tt-btn-sm" onclick="TeamApp.switchTab('timesheets'); TeamApp.showPendingApprovals();">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                                ${stats.pending_approvals} Pending Approvals
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
            
            $('#tt-panel-overview').html(html);
        },

        renderWeeklyChart(dailyData) {
            console.log('[TeamApp] renderWeeklyChart called with:', dailyData);
            
            if (!dailyData || dailyData.length === 0) {
                console.log('[TeamApp] No daily data, showing empty state');
                return '<div class="tt-empty-state" style="display: flex; align-items: center; justify-content: center; height: 100%;"><p>No hours logged this week</p></div>';
            }

            // API returns total_hours, not hours
            const hoursList = dailyData.map(d => parseFloat(d.total_hours) || 0);
            const maxHours = Math.max(...hoursList, 8); // Minimum 8 hours for scale
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            
            console.log('[TeamApp] Chart data:', { hoursList, maxHours, dailyData });
            
            const chartHtml = `
                <div style="display: flex; align-items: flex-end; justify-content: space-around; height: 100%; padding: 20px 0;">
                    ${dailyData.map((day, index) => {
                        const hours = parseFloat(day.total_hours) || 0;
                        const heightPercent = maxHours > 0 ? (hours / maxHours * 100) : 0;
                        const minHeight = hours > 0 ? '4px' : '0'; // Ensure visible bar if any hours
                        const dayName = days[index] || day.day_name || day.work_date;
                        
                        return `
                            <div style="display: flex; flex-direction: column; align-items: center; flex: 1; height: 100%; justify-content: flex-end;">
                                <div style="font-size: 12px; font-weight: 600; color: var(--tt-gray-700); margin-bottom: 8px;">
                                    ${hours > 0 ? hours.toFixed(1) + 'h' : '-'}
                                </div>
                                <div style="width: 40px; background: linear-gradient(to top, var(--tt-primary), var(--tt-primary-light)); 
                                            border-radius: 4px 4px 0 0; min-height: ${minHeight}; height: ${heightPercent}%;"
                                     class="tt-chart-bar" data-hours="${hours}" data-day="${dayName}">
                                </div>
                                <div style="font-size: 12px; color: var(--tt-gray-500); margin-top: 8px;">${dayName}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
            
            console.log('[TeamApp] Chart HTML generated');
            return chartHtml;
        },

        renderActivityItem(item) {
            console.log('[TeamApp] renderActivityItem called with:', item);
            
            // Handle null/undefined item
            if (!item) {
                console.warn('[TeamApp] renderActivityItem called with null item');
                return '<!-- null item -->';
            }
            
            // Support both formats: job activity (user_name + description) and legacy format
            let userName, description, timestamp, typeLabel;
            
            if (item.user_name && item.description) {
                // New format from job activity
                userName = item.user_name;
                description = item.description;
                timestamp = item.timestamp;
                typeLabel = item.type ? `<span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: var(--tt-primary-light); color: var(--tt-primary); margin-left: 8px;">${item.type}</span>` : '';
            } else if (item.title) {
                // Legacy format with title
                userName = item.user_name || 'System';
                description = item.title + (item.description ? ' ' + item.description : '');
                timestamp = item.timestamp;
                typeLabel = item.type ? `<span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: var(--tt-primary-light); color: var(--tt-primary); margin-left: 8px;">${item.type}</span>` : '';
            } else {
                // Fallback - try to extract any meaningful data
                console.warn('[TeamApp] Unknown activity format:', item);
                userName = 'System';
                description = typeof item === 'string' ? item : 'Activity recorded';
                timestamp = item?.timestamp || new Date().toISOString();
                typeLabel = '';
            }
            
            // Ensure we have values
            userName = userName || 'Unknown';
            description = description || 'Activity';
            
            // Generate initials from user name
            const initials = userName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || '?';
            const avatar = item?.avatar_url ? 
                `<img src="${item.avatar_url}" class="tt-avatar-img" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="">` : 
                `<span style="font-size: 14px; font-weight: 600; color: white;">${initials}</span>`;
            
            const timeAgo = timestamp ? this.timeAgo(timestamp) : 'Recently';
            
            const html = `
                <div class="tt-activity-item" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; border-bottom: 1px solid var(--tt-gray-200);">
                    <div class="tt-activity-avatar" style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--tt-primary), var(--tt-primary-dark)); 
                                        display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                        ${avatar}
                    </div>
                    <div class="tt-activity-content" style="flex: 1; min-width: 0;">
                        <div class="tt-activity-text" style="font-size: 14px; line-height: 1.4; color: var(--tt-gray-800);">
                            <strong style="color: var(--tt-gray-900);">${userName}</strong>${typeLabel} ${description}
                        </div>
                        <div class="tt-activity-time" style="font-size: 12px; color: var(--tt-gray-500); margin-top: 4px;">${timeAgo}</div>
                    </div>
                    ${item?.status && item.status !== 'completed' ? `<span class="tt-activity-status ${item.status}" style="font-size: 11px; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; flex-shrink: 0;">${item.status}</span>` : ''}
                </div>
            `;
            
            console.log('[TeamApp] renderActivityItem returning HTML length:', html.length);
            return html;
        },

        renderCrewCard(crew) {
            const memberAvatars = crew.members ? crew.members.slice(0, 4).map(m => {
                const initial = m.first_name.charAt(0);
                return `<div class="tt-crew-member-avatar">${initial}</div>`;
            }).join('') : '';
            
            const extraCount = crew.member_count > 4 ? `<div class="tt-crew-count">+${crew.member_count - 4}</div>` : '';
            
            return `
                <div class="tt-crew-card">
                    <div class="tt-crew-header">
                        <span class="tt-crew-name">${crew.crew_name}</span>
                        <span class="tt-crew-badge">${crew.trade_specialty || 'General'}</span>
                    </div>
                    <div class="tt-crew-foreman">Foreman: ${crew.foreman_first || 'TBD'} ${crew.foreman_last || ''}</div>
                    <div class="tt-crew-members">
                        ${memberAvatars}
                        ${extraCount}
                    </div>
                </div>
            `;
        },

        renderOverviewError() {
            $('#tt-panel-overview').html(`
                <div class="tt-empty-state">
                    <div class="tt-empty-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <h3 class="tt-empty-title">Unable to load dashboard</h3>
                    <p class="tt-empty-description">Please try refreshing the page or contact support if the problem persists.</p>
                    <button class="tt-btn tt-btn-primary" onclick="location.reload()">Refresh Page</button>
                </div>
            `);
        },

        // ============================================
        // RENDER: TEAM MEMBERS TAB
        // ============================================
        renderTeamTable() {
            const filters = this.filters.employees;
            const html = `
                <div class="tt-team-content">
                    <!-- Filters -->
                    <div class="tt-filters-bar">
                        <div class="tt-filter-group">
                            <label class="tt-filter-label">Role:</label>
                            <select class="tt-filter-select tt-filter-role">
                                <option value="" ${!filters.role ? 'selected' : ''}>All Roles</option>
                                <option value="Site Manager" ${filters.role === 'Site Manager' ? 'selected' : ''}>Site Manager</option>
                                <option value="Foreman" ${filters.role === 'Foreman' ? 'selected' : ''}>Foreman</option>
                                <option value="Superintendent" ${filters.role === 'Superintendent' ? 'selected' : ''}>Superintendent</option>
                                <option value="Carpenter" ${filters.role === 'Carpenter' ? 'selected' : ''}>Carpenter</option>
                                <option value="Electrician" ${filters.role === 'Electrician' ? 'selected' : ''}>Electrician</option>
                                <option value="Plumber" ${filters.role === 'Plumber' ? 'selected' : ''}>Plumber</option>
                                <option value="Laborer" ${filters.role === 'Laborer' ? 'selected' : ''}>Laborer</option>
                            </select>
                        </div>
                        <div class="tt-filter-group">
                            <label class="tt-filter-label">Trade:</label>
                            <select class="tt-filter-select tt-filter-trade">
                                <option value="" ${!filters.trade ? 'selected' : ''}>All Trades</option>
                                <option value="Carpentry" ${filters.trade === 'Carpentry' ? 'selected' : ''}>Carpentry</option>
                                <option value="Electrical" ${filters.trade === 'Electrical' ? 'selected' : ''}>Electrical</option>
                                <option value="Plumbing" ${filters.trade === 'Plumbing' ? 'selected' : ''}>Plumbing</option>
                                <option value="Masonry" ${filters.trade === 'Masonry' ? 'selected' : ''}>Masonry</option>
                                <option value="HVAC" ${filters.trade === 'HVAC' ? 'selected' : ''}>HVAC</option>
                                <option value="Painting" ${filters.trade === 'Painting' ? 'selected' : ''}>Painting</option>
                                <option value="Roofing" ${filters.trade === 'Roofing' ? 'selected' : ''}>Roofing</option>
                                <option value="General" ${filters.trade === 'General' ? 'selected' : ''}>General</option>
                            </select>
                        </div>
                        <div class="tt-filter-group">
                            <label class="tt-filter-label">Status:</label>
                            <select class="tt-filter-select tt-filter-status">
                                <option value="" ${filters.status === '' ? 'selected' : ''}>All Statuses</option>
                                <option value="active" ${filters.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${filters.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                        </div>
                        <div class="tt-filter-actions">
                            <button class="tt-btn tt-btn-secondary tt-btn-sm" onclick="TeamApp.exportEmployees()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Export
                            </button>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="tt-bulk-actions" id="tt-bulk-actions">
                        <span class="tt-bulk-count"><span id="tt-selected-count">0</span> selected</span>
                        <button class="tt-btn tt-btn-primary tt-btn-sm" onclick="TeamApp.bulkInvite()">Invite</button>
                        <button class="tt-btn tt-btn-secondary tt-btn-sm" onclick="TeamApp.bulkDeactivate()">Deactivate</button>
                        <button class="tt-btn tt-btn-ghost tt-btn-sm" onclick="TeamApp.clearSelection()">Clear</button>
                    </div>

                    <!-- Table -->
                    <div class="tt-table-container">
                        <div class="tt-table-toolbar">
                            <span class="tt-table-title">Team Members</span>
                            <div class="tt-table-actions">
                                <button class="tt-btn tt-btn-primary tt-add-member-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                    Add Member
                                </button>
                            </div>
                        </div>
                        <table class="tt-table">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="tt-select-all">
                                    </th>
                                    <th>Employee</th>
                                    <th>Role / Trade</th>
                                    <th>Contact</th>
                                    <th>Hours This Week</th>
                                    <th>Status</th>
                                    <th>Last Active</th>
                                    <th width="60">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(this.employees && this.employees.length > 0) ? this.employees.map(emp => this.renderEmployeeRow(emp)).join('') : ''}
                            </tbody>
                        </table>
                        ${(this.employees && this.employees.length === 0) ? `
                            <div class="tt-empty-state">
                                <div class="tt-empty-icon">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <h3 class="tt-empty-title">No team members found</h3>
                                <p class="tt-empty-description">
                                    ${filters.role || filters.trade || filters.status || filters.search ? 
                                        'No members match your current filters. Try adjusting or clearing them.' : 
                                        'Get started by adding your first team member.'}
                                </p>
                                <div style="display: flex; gap: 12px; justify-content: center;">
                                    ${filters.role || filters.trade || filters.status || filters.search ? 
                                        `<button class="tt-btn tt-btn-secondary" onclick="TeamApp.clearEmployeeFilters()">Clear Filters</button>` : ''}
                                    <button class="tt-btn tt-btn-primary tt-add-member-btn">Add Member</button>
                                </div>
                            </div>
                        ` : ''}
                        <div class="tt-pagination">
                            <span class="tt-pagination-info">Showing ${(this.employees || []).length} members</span>
                            <div class="tt-pagination-controls">
                                <button class="tt-page-btn" disabled>Previous</button>
                                <button class="tt-page-btn active">1</button>
                                <button class="tt-page-btn" disabled>Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#tt-panel-team').html(html);
        },

        renderEmployeeRow(emp) {
            // Handle null/undefined values safely
            const firstName = emp.first_name || '';
            const lastName = emp.last_name || '';
            const role = emp.role || 'No role';
            const trade = (emp.trade || '').trim();
            const email = emp.email || '';
            const phone = emp.phone || emp.mobile || 'No phone';
            const employeeCode = emp.employee_code || '';

            const avatar = emp.avatar_url ?
                `<img src="${emp.avatar_url}" class="tt-cell-avatar-img" alt="">` :
                `<div class="tt-cell-avatar-initials">${firstName.charAt(0)}${lastName.charAt(0)}</div>`;

            const statusBadge = emp.status === 'active' ?
                `<span class="tt-badge tt-badge-green"><span class="tt-status-dot online"></span> Active</span>` :
                `<span class="tt-badge tt-badge-gray">Inactive</span>`;

            const lastActive = emp.last_active_at ? this.timeAgo(emp.last_active_at) : 'Never';

            // Use stats if already loaded, otherwise will be fetched
            const weekHours = emp.week_hours || 0;
            const progressWidth = Math.min((weekHours / 40) * 100, 100);
            const progressColor = weekHours > 40 ? 'var(--tt-warning)' : 'var(--tt-primary)';

            return `
                <tr data-id="${emp.id}" class="tt-employee-row" data-employee-id="${emp.id}">
                    <td><input type="checkbox" class="tt-select-row" data-id="${emp.id}"></td>
                    <td>
                        <div class="tt-cell-avatar">
                            ${avatar}
                            <div class="tt-cell-info">
                                <span class="tt-cell-name">${firstName} ${lastName}</span>
                                <span class="tt-cell-subtitle">${employeeCode}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="tt-cell-info">
                            <span class="tt-cell-name">${role}</span>
                            ${trade ? `<span class="tt-cell-subtitle">${trade}</span>` : ''}
                        </div>
                    </td>
                    <td>
                        <div class="tt-cell-info">
                            <span class="tt-cell-name">${email}</span>
                            <span class="tt-cell-subtitle">${phone}</span>
                        </div>
                    </td>
                    <td class="tt-hours-cell" data-employee-id="${emp.id}">
                        <div style="width: 120px;">
                            <div class="tt-progress-bar">
                                <div class="tt-progress-fill" style="width: ${progressWidth}%; background: ${progressColor}"></div>
                            </div>
                            <div class="tt-progress-text">${weekHours.toFixed(1)}h this week</div>
                        </div>
                    </td>
                    <td>${statusBadge}</td>
                    <td>${lastActive}</td>
                    <td>
                        <div class="tt-row-actions">
                            <button class="tt-action-btn" onclick="TeamApp.viewMemberProfile(${emp.id})" title="View Profile">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            <button class="tt-action-btn" onclick="TeamApp.editMember(${emp.id})" title="Edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button class="tt-action-btn" onclick="TeamApp.logTimeForMember(${emp.id})" title="Log Time">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        },

        async loadEmployeeHours() {
            // Fetch hours for all visible employees
            const rows = document.querySelectorAll('.tt-employee-row');
            for (const row of rows) {
                const employeeId = row.dataset.employeeId;
                if (!employeeId) continue;

                try {
                    const stats = await TeamAPI.request(`/employees/${employeeId}/stats`);
                    const weekHours = stats.week_hours || 0;
                    const weekOt = stats.week_overtime_hours || 0;
                    const progressWidth = Math.min((weekHours / 40) * 100, 100);
                    const progressColor = weekHours > 40 ? 'var(--tt-warning)' : 'var(--tt-primary)';

                    // Update the hours cell
                    const hoursCell = row.querySelector('.tt-hours-cell');
                    if (hoursCell) {
                        hoursCell.innerHTML = `
                            <div style="width: 120px;">
                                <div class="tt-progress-bar">
                                    <div class="tt-progress-fill" style="width: ${progressWidth}%; background: ${progressColor}"></div>
                                </div>
                                <div class="tt-progress-text">${weekHours.toFixed(1)}h${weekOt > 0 ? ` <span style="color: var(--tt-warning);">(${weekOt.toFixed(1)} OT)</span>` : ''}</div>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error(`[TeamApp] Failed to load hours for employee ${employeeId}:`, error);
                }
            }
        },

        // ============================================
        // RENDER: TIMESHEETS TAB
        // ============================================
        renderTimesheetsTable() {
            const html = `
                <div class="tt-timesheets-content">
                    <!-- Filters -->
                    <div class="tt-filters-bar">
                        <div class="tt-filter-group">
                            <label class="tt-filter-label">Date Range:</label>
                            <input type="date" class="tt-filter-input" id="tt-filter-start" value="${this.getWeekStart()}">
                            <span class="tt-time-separator">to</span>
                            <input type="date" class="tt-filter-input" id="tt-filter-end" value="${this.getWeekEnd()}">
                        </div>
                        <div class="tt-filter-group">
                            <label class="tt-filter-label">Employee:</label>
                            <select class="tt-filter-select" id="tt-filter-employee">
                                <option value="">All Employees</option>
                                ${(this.employees || []).map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`).join('')}
                            </select>
                        </div>
                        <div class="tt-filter-group">
                            <label class="tt-filter-label">Status:</label>
                            <select class="tt-filter-select" id="tt-filter-status">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="tt-filter-actions">
                            <button class="tt-btn tt-btn-secondary tt-btn-sm tt-toggle-grid-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                </svg>
                                Grid View
                            </button>
                            <button class="tt-btn tt-btn-secondary tt-btn-sm tt-export-timesheets-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Export
                            </button>
                        </div>
                    </div>

                    <!-- Pending Approvals Alert -->
                    ${this.approvals.approvals && this.approvals.approvals.length > 0 ? `
                        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: var(--tt-radius-lg); padding: 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <span style="font-weight: 600; color: #92400e;">${this.approvals.approvals.length} timesheet entries pending approval</span>
                            </div>
                            <button class="tt-btn tt-btn-primary tt-btn-sm" onclick="TeamApp.showPendingApprovals()">Review Now</button>
                        </div>
                    ` : ''}

                    <!-- Bulk Actions -->
                    <div class="tt-bulk-actions" id="tt-timesheet-bulk-actions">
                        <span class="tt-bulk-count"><span id="tt-timesheet-selected-count">0</span> selected</span>
                        <button class="tt-btn tt-btn-primary tt-btn-sm tt-bulk-approve-btn">Approve</button>
                        <button class="tt-btn tt-btn-danger tt-btn-sm tt-bulk-reject-btn">Reject</button>
                        <button class="tt-btn tt-btn-ghost tt-btn-sm tt-clear-ts-selection-btn">Clear</button>
                    </div>

                    <!-- Table View -->
                    <div class="tt-table-container" id="tt-timesheet-table-view">
                        <div class="tt-table-toolbar">
                            <span class="tt-table-title">Timesheet Entries</span>
                            <div class="tt-table-actions">
                                <button class="tt-btn tt-btn-primary tt-new-entry-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                    New Entry
                                </button>
                            </div>
                        </div>
                        <table class="tt-table">
                            <thead>
                                <tr>
                                    <th width="40"><input type="checkbox" class="tt-select-all-timesheets"></th>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Project</th>
                                    <th>Cost Code</th>
                                    <th>Time</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th width="60">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.timesheets.map(ts => this.renderTimesheetRow(ts)).join('')}
                            </tbody>
                        </table>
                    </div>

                    <!-- Grid View (Hidden by default) -->
                    <div class="tt-table-container" id="tt-timesheet-grid-view" style="display: none;">
                        ${this.renderWeeklyGrid()}
                    </div>
                </div>
            `;
            
            $('#tt-panel-timesheets').html(html);
        },

        renderTimesheetRow(ts) {
            const statusBadge = ts.status === 'approved' ?
                `<span class="tt-badge tt-badge-green">Approved</span>` :
                ts.status === 'rejected' ?
                `<span class="tt-badge tt-badge-red">Rejected</span>` :
                `<span class="tt-badge tt-badge-amber">Pending</span>`;

            // Build worker name from first_name/last_name (API returns these from JOIN)
            const firstName = ts.first_name || '';
            const lastName = ts.last_name || '';
            const workerName = (firstName + ' ' + lastName).trim() || 'Unknown';
            const workerInitial = (firstName.charAt(0) || 'U').toUpperCase();

            return `
                <tr data-id="${ts.id}">
                    <td><input type="checkbox" class="tt-select-timesheet-row" data-id="${ts.id}"></td>
                    <td>${this.formatDate(ts.work_date)}</td>
                    <td>
                        <div class="tt-cell-avatar">
                            <div class="tt-cell-avatar-initials">${workerInitial}</div>
                            <div class="tt-cell-info">
                                <span class="tt-cell-name">${workerName}</span>
                            </div>
                        </div>
                    </td>
                    <td>${ts.job_code ? `<span class="tt-badge tt-badge-blue">${ts.job_code}</span>` : (ts.job_id ? `<span class="tt-badge tt-badge-blue">Job #${ts.job_id}</span>` : '<span class="tt-badge tt-badge-gray">No Project</span>')}</td>
                    <td><span class="tt-badge tt-badge-gray">${ts.cost_code || 'N/A'}</span></td>
                    <td>${ts.start_time ? ts.start_time.substring(0, 5) : '-'} - ${ts.end_time ? ts.end_time.substring(0, 5) : '-'}</td>
                    <td><strong>${(parseFloat(ts.total_hours || 0) + parseFloat(ts.overtime_hours || 0)).toFixed(2)}</strong></td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="tt-row-actions">
                            ${ts.status === 'pending' && this.approvals.can_approve ? `
                                <button class="tt-action-btn tt-approve-entry-btn" data-id="${ts.id}" title="Approve">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </button>
                                <button class="tt-action-btn tt-reject-entry-btn" data-id="${ts.id}" title="Reject">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            ` : ''}
                            <button class="tt-action-btn tt-edit-entry-btn" data-id="${ts.id}" title="Edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        },

        renderWeeklyGrid() {
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const employees = this.employees.slice(0, 10); // Limit for grid view
            
            return `
                <div class="tt-weekly-grid">
                    <div class="tt-grid-header employee">Employee</div>
                    ${days.map(d => `<div class="tt-grid-header">${d}</div>`).join('')}
                    
                    ${employees.map(emp => `
                        <div class="tt-grid-cell employee">
                            <div class="tt-cell-avatar-initials">${emp.first_name.charAt(0)}</div>
                            <span style="font-size: 12px; font-weight: 500;">${emp.first_name}</span>
                        </div>
                        ${days.map(() => `
                            <div class="tt-grid-cell" onclick="TeamApp.openNewEntryModal(${emp.id})">
                                <span class="tt-grid-hours zero">-</span>
                            </div>
                        `).join('')}
                    `).join('')}
                </div>
            `;
        },

        // ============================================
        // MODALS
        // ============================================
        openAddMemberModal() {
            try {
                console.log('[TeamApp] openAddMemberModal() called');
                // Remove any existing modal first
                $('#tt-modal-overlay').remove();
                $('body').removeClass('tt-modal-open');
                
                const jobId = this.getCurrentJobId();
                
                // Get all account employees for "Select Existing" mode
                const accountEmployees = this.allEmployees || this.employees || [];
                
                // Filter out employees already on this job
                const currentEmployeeIds = new Set((this.employees || []).map(e => e.id));
                const availableEmployees = accountEmployees.filter(e => !currentEmployeeIds.has(e.id));
            
            const html = `
                <div class="tt-modal-overlay" id="tt-modal-overlay">
                    <div class="tt-modal-container medium">
                        <div class="tt-modal-header">
                            <h3>Add Team Member</h3>
                            <button type="button" class="tt-modal-close" id="tt-modal-close">&times;</button>
                        </div>
                        <div class="tt-modal-body">
                            <!-- Mode Toggle -->
                            <div class="tt-member-mode-toggle">
                                <div class="tt-toggle-container">
                                    <button type="button" class="tt-toggle-btn active" data-mode="existing" id="tt-mode-existing">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                        Select Existing
                                    </button>
                                    <button type="button" class="tt-toggle-btn" data-mode="new" id="tt-mode-new">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                        Create New
                                    </button>
                                </div>
                            </div>
                            
                            <form id="tt-add-member-form" autocomplete="off">
                                <!-- EXISTING EMPLOYEE SELECTION -->
                                <div id="tt-existing-employee-section" class="tt-form-section">
                                    <div class="tt-form-group">
                                        <label for="tt-select-existing" class="tt-form-label-required">Select Employee</label>
                                        <select id="tt-select-existing" class="tt-form-select tt-select-large" name="existing_employee_id" required>
                                            <option value="">${availableEmployees.length > 0 ? 'Choose an employee...' : (accountEmployees.length === 0 ? 'No employees in system - create one first' : 'All employees are already on this job')}</option>
                                            ${availableEmployees.map(e => `
                                                <option value="${e.id}" data-name="${e.first_name} ${e.last_name}" data-email="${e.email || ''}" data-role="${e.role || ''}" data-trade="${e.trade || ''}" data-rate="${e.hourly_rate || ''}">
                                                    ${e.first_name} ${e.last_name} ${e.employee_code ? `(${e.employee_code})` : ''} - ${e.role || 'No role'} ${e.trade ? `| ${e.trade}` : ''}
                                                </option>
                                            `).join('')}
                                        </select>
                                        ${availableEmployees.length === 0 ? `
                                            <div class="tt-helper-text">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                                </svg>
                                                ${accountEmployees.length === 0 ? 'No employees in system - create one first' : 'All account employees are already assigned to this job. Create a new employee instead.'}
                                            </div>
                                        ` : `
                                            <div class="tt-helper-text">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                                </svg>
                                                ${availableEmployees.length} employee${availableEmployees.length !== 1 ? 's' : ''} available to add
                                            </div>
                                        `}
                                    </div>
                                    
                                    <!-- Employee Preview Card (shown when selected) -->
                                    <div id="tt-employee-preview" class="tt-employee-preview-card" style="display: none;">
                                        <div class="tt-preview-avatar">
                                            <span id="tt-preview-initials"></span>
                                        </div>
                                        <div class="tt-preview-info">
                                            <div class="tt-preview-name" id="tt-preview-name"></div>
                                            <div class="tt-preview-details" id="tt-preview-details"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- CREATE NEW EMPLOYEE FORM -->
                                <div id="tt-new-employee-section" class="tt-form-section" style="display: none;">
                                    <div class="tt-form-row">
                                        <div class="tt-form-group">
                                            <label for="tt-add-first-name" class="tt-form-label-required">First Name</label>
                                            <input type="text" id="tt-add-first-name" class="tt-form-input" name="first_name" autocomplete="given-name">
                                        </div>
                                        <div class="tt-form-group">
                                            <label for="tt-add-last-name" class="tt-form-label-required">Last Name</label>
                                            <input type="text" id="tt-add-last-name" class="tt-form-input" name="last_name" autocomplete="family-name">
                                        </div>
                                    </div>
                                    <div class="tt-form-row">
                                        <div class="tt-form-group">
                                            <label for="tt-add-email" class="tt-form-label-required">Email</label>
                                            <input type="email" id="tt-add-email" class="tt-form-input" name="email" autocomplete="email">
                                        </div>
                                        <div class="tt-form-group">
                                            <label for="tt-add-phone">Phone</label>
                                            <input type="tel" id="tt-add-phone" class="tt-form-input" name="phone" autocomplete="tel">
                                        </div>
                                    </div>
                                    <div class="tt-form-row">
                                        <div class="tt-form-group">
                                            <label for="tt-add-role" class="tt-form-label-required">Role</label>
                                            <select id="tt-add-role" class="tt-form-select" name="role" autocomplete="organization-title">
                                                <option value="">Select Role</option>
                                                <option value="Site Manager">Site Manager</option>
                                                <option value="Foreman">Foreman</option>
                                                <option value="Superintendent">Superintendent</option>
                                                <option value="Carpenter">Carpenter</option>
                                                <option value="Electrician">Electrician</option>
                                                <option value="Plumber">Plumber</option>
                                                <option value="Laborer">Laborer</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="tt-form-group">
                                            <label for="tt-add-trade">Trade</label>
                                            <select id="tt-add-trade" class="tt-form-select" name="trade" autocomplete="off">
                                                <option value="">Select Trade</option>
                                                <option value="Carpentry">Carpentry</option>
                                                <option value="Electrical">Electrical</option>
                                                <option value="Plumbing">Plumbing</option>
                                                <option value="Masonry">Masonry</option>
                                                <option value="HVAC">HVAC</option>
                                                <option value="Painting">Painting</option>
                                                <option value="Roofing">Roofing</option>
                                                <option value="General">General</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="tt-form-row">
                                        <div class="tt-form-group">
                                            <label for="tt-add-rate">Hourly Rate</label>
                                            <input type="number" id="tt-add-rate" class="tt-form-input" name="hourly_rate" step="0.01" min="0" autocomplete="off">
                                        </div>
                                        <div class="tt-form-group">
                                            <label for="tt-add-cost-code">Default Cost Code</label>
                                            <select id="tt-add-cost-code" class="tt-form-select" name="default_cost_code" autocomplete="off">
                                                <option value="">Select Cost Code</option>
                                                ${(this.costCodes || []).map(c => `<option value="${c.code}">${c.code} - ${c.description}</option>`).join('')}
                                            </select>
                                        </div>
                                    </div>
                                    <div class="tt-form-group">
                                        <label>Permissions Level</label>
                                        <div class="tt-radio-group">
                                            <label class="tt-radio-item" for="tt-perm-worker">
                                                <input type="radio" id="tt-perm-worker" name="permissions_level" value="field_worker" checked>
                                                <span class="tt-radio-label">Field Worker - Can log time, view own records</span>
                                            </label>
                                            <label class="tt-radio-item" for="tt-perm-foreman">
                                                <input type="radio" id="tt-perm-foreman" name="permissions_level" value="foreman">
                                                <span class="tt-radio-label">Foreman - Can approve crew timesheets</span>
                                            </label>
                                            <label class="tt-radio-item" for="tt-perm-admin">
                                                <input type="radio" id="tt-perm-admin" name="permissions_level" value="admin">
                                                <span class="tt-radio-label">Admin - Full access</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields for mode tracking -->
                                <input type="hidden" id="tt-member-mode" name="add_mode" value="existing">
                                ${jobId ? `<input type="hidden" name="job_id" value="${jobId}">` : ''}
                            </form>
                        </div>
                        <div class="tt-modal-footer">
                            <button type="button" class="tt-btn tt-btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="tt-btn tt-btn-primary" id="tt-save-member-btn">Add to Job</button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(html).addClass('tt-modal-open');
            
            // Bind mode toggle
            $('.tt-toggle-btn').on('click', (e) => {
                const $btn = $(e.currentTarget);
                const mode = $btn.data('mode');
                
                // Update toggle state
                $('.tt-toggle-btn').removeClass('active');
                $btn.addClass('active');
                
                // Update form mode
                $('#tt-member-mode').val(mode);
                
                // Show/hide sections with animation
                if (mode === 'existing') {
                    $('#tt-new-employee-section').slideUp(200, () => {
                        $('#tt-existing-employee-section').slideDown(200);
                    });
                    $('#tt-save-member-btn').text('Add to Job');
                    $('#tt-select-existing').prop('required', true);
                    $('#tt-add-first-name, #tt-add-last-name, #tt-add-email, #tt-add-role').prop('required', false);
                } else {
                    $('#tt-existing-employee-section').slideUp(200, () => {
                        $('#tt-new-employee-section').slideDown(200);
                    });
                    $('#tt-save-member-btn').text('Create & Add');
                    $('#tt-select-existing').prop('required', false);
                    $('#tt-add-first-name, #tt-add-last-name, #tt-add-email, #tt-add-role').prop('required', true);
                }
            });
            
            // Bind employee selection change for preview
            $('#tt-select-existing').on('change', (e) => {
                const employeeId = $(e.target).val();
                const $option = $(e.target).find('option:selected');
                
                if (employeeId) {
                    const name = $option.data('name');
                    const email = $option.data('email');
                    const role = $option.data('role');
                    const trade = $option.data('trade');
                    const rate = $option.data('rate');
                    
                    // Update preview card
                    const initials = name.split(' ').map(n => n.charAt(0)).join('').toUpperCase();
                    $('#tt-preview-initials').text(initials);
                    $('#tt-preview-name').text(name);
                    
                    const details = [];
                    if (role) details.push(role);
                    if (trade) details.push(trade);
                    if (email) details.push(email);
                    if (rate) details.push(`£${rate}/hr`);
                    
                    $('#tt-preview-details').text(details.join(' • '));
                    $('#tt-employee-preview').fadeIn(200);
                } else {
                    $('#tt-employee-preview').fadeOut(200);
                }
            });
            
            // Bind close button
            $('#tt-modal-close, [data-dismiss="modal"]').on('click', () => this.closeModal());
            
            // Bind save button
            $('#tt-save-member-btn').on('click', () => this.saveMember());
            
            // Bind overlay click
            $('#tt-modal-overlay').on('click', (e) => {
                if (e.target.id === 'tt-modal-overlay') this.closeModal();
            });
            
            // Bind escape key
            $(document).on('keydown.tt-modal', (e) => {
                if (e.key === 'Escape') this.closeModal();
            });
            } catch (error) {
                console.error('[TeamApp] Error in openAddMemberModal:', error);
                this.showToast('Error opening modal. Please try again.', 'error');
            }
        },

        openNewEntryModal(employeeId = null) {
            // Remove any existing modal first
            $('#tt-modal-overlay').remove();
            $('body').removeClass('tt-modal-open');
            
            // Check if on job page
            const currentJobId = this.getCurrentJobId();
            
            const html = `
                <div class="tt-modal-overlay" id="tt-modal-overlay">
                    <div class="tt-modal-container medium">
                        <div class="tt-modal-header">
                            <h3>New Timesheet Entry</h3>
                            <button type="button" class="tt-modal-close" id="tt-modal-close">&times;</button>
                        </div>
                        <div class="tt-modal-body">
                            <form id="tt-timesheet-entry-form" autocomplete="off">
                                <div class="tt-form-group">
                                    <label for="tt-entry-employee" class="tt-form-label-required">Employee</label>
                                    <select id="tt-entry-employee" class="tt-form-select" name="employee_id" ${employeeId ? 'disabled' : ''} required autocomplete="off">
                                        <option value="">Select Employee</option>
                                        ${(this.employees || []).map(e => `<option value="${e.id}" ${e.id == employeeId ? 'selected' : ''}>${e.first_name} ${e.last_name}</option>`).join('')}
                                    </select>
                                    ${employeeId ? `<input type="hidden" name="employee_id" value="${employeeId}">` : ''}
                                </div>
                                <div class="tt-form-row">
                                    <div class="tt-form-group">
                                        <label for="tt-entry-date" class="tt-form-label-required">Date</label>
                                        <input type="date" id="tt-entry-date" class="tt-form-input" name="work_date" value="${new Date().toISOString().split('T')[0]}" required autocomplete="off">
                                    </div>
                                    ${currentJobId ? `
                                        <div class="tt-form-group">
                                            <label for="tt-entry-job">Project</label>
                                            <input type="text" id="tt-entry-job" class="tt-form-input" value="Job #${currentJobId}" disabled>
                                            <input type="hidden" name="job_id" value="${currentJobId}">
                                        </div>
                                    ` : `
                                        <div class="tt-form-group">
                                            <label for="tt-entry-job-id">Project (Optional)</label>
                                            <input type="number" id="tt-entry-job-id" class="tt-form-input" name="job_id" placeholder="Enter Job ID" autocomplete="off">
                                        </div>
                                    `}
                                </div>
                                <div class="tt-form-row">
                                    <div class="tt-form-group">
                                        <label for="tt-entry-start" class="tt-form-label-required">Start Time</label>
                                        <input type="time" id="tt-entry-start" class="tt-form-input" name="start_time" required autocomplete="off">
                                    </div>
                                    <div class="tt-form-group">
                                        <label for="tt-entry-end" class="tt-form-label-required">End Time</label>
                                        <input type="time" id="tt-entry-end" class="tt-form-input" name="end_time" required autocomplete="off">
                                    </div>
                                </div>
                                <div class="tt-form-row">
                                    <div class="tt-form-group">
                                        <label for="tt-entry-break">Break (minutes)</label>
                                        <input type="number" id="tt-entry-break" class="tt-form-input" name="break_duration" value="30" min="0" autocomplete="off">
                                    </div>
                                    <div class="tt-form-group">
                                        <label for="tt-entry-cost-code">Cost Code</label>
                                        <select id="tt-entry-cost-code" class="tt-form-select" name="cost_code" autocomplete="off">
                                            <option value="">Select Cost Code</option>
                                            ${(this.costCodes || []).map(c => `<option value="${c.code}">${c.code} - ${c.description}</option>`).join('')}
                                        </select>
                                    </div>
                                </div>
                                <div class="tt-form-group">
                                    <label class="tt-checkbox-label" for="tt-entry-overtime">
                                        <input type="checkbox" id="tt-entry-overtime" name="is_overtime"> This is overtime
                                    </label>
                                </div>
                                <div class="tt-form-group">
                                    <label for="tt-entry-notes">Notes</label>
                                    <textarea id="tt-entry-notes" class="tt-form-textarea" name="notes" placeholder="What work was completed?" autocomplete="off"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="tt-modal-footer">
                            <button type="button" class="tt-btn tt-btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="tt-btn tt-btn-primary" id="tt-save-entry-btn">Save Entry</button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(html).addClass('tt-modal-open');
            
            // Bind close button
            $('#tt-modal-close, [data-dismiss="modal"]').on('click', () => this.closeModal());
            
            // Bind save button
            $('#tt-save-entry-btn').on('click', () => this.saveTimesheetEntry());
            
            // Bind overlay click
            $('#tt-modal-overlay').on('click', (e) => {
                if (e.target.id === 'tt-modal-overlay') this.closeModal();
            });
            
            // Bind escape key
            $(document).on('keydown.tt-modal', (e) => {
                if (e.key === 'Escape') this.closeModal();
            });
        },

        closeModal() {
            $('#tt-modal-overlay').remove();
            $('body').removeClass('tt-modal-open');
            $(document).off('keydown.tt-modal');
        },

        // ============================================
        // UTILITIES
        // ============================================
        formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        timeAgo(timestamp) {
            console.log('[TeamApp] timeAgo called with raw timestamp:', timestamp, 'type:', typeof timestamp);
            
            // Handle different timestamp formats
            if (!timestamp) return 'Recently';
            
            // Parse the timestamp - handle MySQL format and ISO format
            let date;
            let originalTimestamp = timestamp;
            
            if (typeof timestamp === 'string') {
                // MySQL format: 2024-01-15 14:30:00 - assume UTC, append Z
                if (timestamp.includes(' ')) {
                    // Append Z to treat as UTC, then convert to local
                    date = new Date(timestamp.replace(' ', 'T') + 'Z');
                    console.log('[TeamApp] Parsed MySQL timestamp as UTC:', date.toISOString());
                } else {
                    date = new Date(timestamp);
                }
            } else if (typeof timestamp === 'number') {
                // Unix timestamp - could be seconds or milliseconds
                if (timestamp < 10000000000) {
                    // Seconds - convert to milliseconds
                    date = new Date(timestamp * 1000);
                } else {
                    date = new Date(timestamp);
                }
            } else {
                date = new Date(timestamp);
            }
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                console.warn('[TeamApp] Invalid timestamp:', originalTimestamp, 'parsed as:', date);
                return 'Recently';
            }
            
            const now = new Date();
            const diffMs = now - date;
            const seconds = Math.floor(diffMs / 1000);
            
            console.log('[TeamApp] timeAgo calculation:', {
                originalTimestamp,
                parsedDate: date.toISOString(),
                now: now.toISOString(),
                diffMs,
                seconds,
                diffMinutes: Math.floor(seconds / 60)
            });
            
            // Handle future dates (clock mismatch) - treat as just now
            if (seconds < 0) {
                console.log('[TeamApp] Future date detected, returning Just now');
                return 'Just now';
            }
            
            // Less than 60 seconds
            if (seconds < 60) {
                return 'Just now';
            }
            
            // Minutes (1-59)
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) {
                return minutes + ' minute' + (minutes === 1 ? '' : 's') + ' ago';
            }
            
            // Hours (1-23)
            const hours = Math.floor(seconds / 3600);
            if (hours < 24) {
                return hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
            }
            
            // Days (1-30)
            const days = Math.floor(seconds / 86400);
            if (days < 30) {
                return days + ' day' + (days === 1 ? '' : 's') + ' ago';
            }
            
            // Months (1-11)
            const months = Math.floor(seconds / 2592000);
            if (months < 12) {
                return months + ' month' + (months === 1 ? '' : 's') + ' ago';
            }
            
            // Years
            const years = Math.floor(seconds / 31536000);
            return years + ' year' + (years === 1 ? '' : 's') + ' ago';
        },

        getWeekStart() {
            const d = new Date();
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            return new Date(d.setDate(diff)).toISOString().split('T')[0];
        },

        getWeekEnd() {
            const d = new Date();
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? 0 : 7);
            return new Date(d.setDate(diff)).toISOString().split('T')[0];
        },

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        showToast(message, type = 'info', title = '') {
            const container = $('.tt-toast-container');
            if (!container.length) {
                $('body').append('<div class="tt-toast-container"></div>');
            }
            
            const icons = {
                success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
                error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
                warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
            };
            
            const toast = $(`
                <div class="tt-toast ${type}">
                    <div class="tt-toast-icon">${icons[type]}</div>
                    <div class="tt-toast-content">
                        ${title ? `<div class="tt-toast-title">${title}</div>` : ''}
                        <div class="tt-toast-message">${message}</div>
                    </div>
                    <button class="tt-toast-close">&times;</button>
                </div>
            `);
            
            $('.tt-toast-container').append(toast);
            
            setTimeout(() => toast.fadeOut(() => toast.remove()), 5000);
            toast.find('.tt-toast-close').click(() => toast.remove());
        },

        updateBulkActions() {
            const count = this.selectedEmployees.size;
            $('#tt-selected-count').text(count);
            $('#tt-bulk-actions').toggleClass('active', count > 0);
        },

        // ============================================
        // PLACEHOLDER METHODS (To be implemented as needed)
        // ============================================
        async saveMember() {
            const form = $('#tt-add-member-form');
            const mode = $('#tt-member-mode').val();
            
            // Get job_id if on a job page
            const jobId = this.getCurrentJobId();
            
            // Show loading state
            const saveBtn = $('#tt-save-member-btn');
            const originalText = saveBtn.text();
            saveBtn.prop('disabled', true).text(mode === 'existing' ? 'Adding...' : 'Creating...');
            
            try {
                if (mode === 'existing') {
                    // Validate existing employee selection
                    const existingEmployeeId = $('#tt-select-existing').val();
                    if (!existingEmployeeId) {
                        this.showToast('Please select an employee to add', 'error');
                        $('#tt-select-existing').focus();
                        return;
                    }
                    
                    // Add existing employee to job using team API
                    const data = {
                        employee_id: existingEmployeeId,
                        job_id: jobId
                    };
                    
                    console.log('[TeamApp] Adding existing employee to job:', data);
                    
                    const result = await TeamAPI.assignTeamMember(data);
                    console.log('[TeamApp] Employee added to job:', result);
                    
                    if (!result || !result.success) {
                        throw new Error(result?.error || 'Failed to add employee to job - invalid response');
                    }
                    
                    this.showToast('Employee added to job successfully', 'success');
                    
                    // Log activity
                    const employeeName = $('#tt-existing-employee option:selected').text() || 'Employee';
                    try { await TeamAPI.logActivity(jobId, `Added team member: ${employeeName}`, 'team'); } catch(e) { console.error('[Activity] Failed:', e); }
                    
                    this.closeModal();
                    await this.loadTeamData();
                    
                } else {
                    // Validate required fields for new employee
                    const requiredFields = ['first_name', 'last_name', 'email', 'role'];
                    for (const field of requiredFields) {
                        const value = form.find(`[name="${field}"]`).val()?.trim();
                        if (!value) {
                            this.showToast(`${field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())} is required`, 'error');
                            form.find(`[name="${field}"]`).focus();
                            return;
                        }
                    }
                    
                    // Collect form data for new employee
                    const data = {};
                    form.serializeArray().forEach(item => {
                        if (item.value) data[item.name] = item.value;
                    });
                    
                    // Convert hourly_rate to number
                    if (data.hourly_rate) {
                        data.hourly_rate = parseFloat(data.hourly_rate);
                    }
                    
                    // Add job_id if on a job page (creates job-specific employee)
                    if (jobId) {
                        data.job_id = jobId;
                        console.log('[TeamApp] Creating job-specific employee for job:', jobId);
                    }
                    
                    console.log('[TeamApp] Creating new employee:', data);
                    
                    const result = await TeamAPI.createEmployee(data);
                    console.log('[TeamApp] Employee created:', result);
                    
                    if (!result || !result.id) {
                        throw new Error(result?.error || 'Failed to create employee - invalid response');
                    }
                    
                    this.showToast('Member created and added successfully', 'success');
                    
                    // Log activity
                    const newEmployeeName = `${data.first_name || ''} ${data.last_name || ''}`.trim() || 'New employee';
                    try { await TeamAPI.logActivity(jobId, `Created team member: ${newEmployeeName} (${data.role || 'Team Member'})`, 'team'); } catch(e) { console.error('[Activity] Failed:', e); }
                    
                    this.closeModal();
                    await this.loadTeamData();
                }
            } catch (error) {
                console.error('[TeamApp] Failed to save member:', error);
                const message = error?.message || error?.error || error?.details || 'Failed to add member. Please try again.';
                this.showToast(message, 'error');
            } finally {
                saveBtn.prop('disabled', false).text(originalText);
            }
        },

        async saveTimesheetEntry() {
            console.log('[TeamApp] saveTimesheetEntry called');

            const form = $('#tt-timesheet-entry-form');
            if (!form.length) {
                console.error('[TeamApp] Form not found!');
                this.showToast('Form not found', 'error');
                return;
            }

            // Debug: Log all form fields
            console.log('[TeamApp] Form fields:', form.serializeArray());

            // Validation - check both visible and hidden fields
            const requiredFields = ['employee_id', 'work_date', 'start_time', 'end_time'];
            for (const field of requiredFields) {
                // Try to get value from any input with this name (including hidden)
                let value = '';
                const fields = form.find(`[name="${field}"]`);
                fields.each(function() {
                    if ($(this).val()) {
                        value = $(this).val();
                        return false; // break
                    }
                });

                if (!value || !value.trim()) {
                    this.showToast(`${field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())} is required`, 'error');
                    fields.first().focus();
                    return;
                }
            }

            // Collect data from all form fields including disabled ones
            const data = {};
            form.find('input, select, textarea').each(function() {
                const name = $(this).attr('name');
                const value = $(this).val();
                if (name && value) {
                    data[name] = value;
                }
            });

            // Handle job_id: use form value if provided, otherwise use current job context
            const currentJobId = this.getCurrentJobId();
            if (currentJobId) {
                // On job page - force the job_id to be the current job
                data.job_id = currentJobId;
                console.log('[TeamApp] Creating job-specific timesheet for job:', currentJobId);
            } else if (!data.job_id) {
                // Global page with no job selected - set to 0 (unassigned)
                data.job_id = 0;
            }
            // If data.job_id is already set from form, use that value

            // Convert employee_id to worker_id for API compatibility
            data.worker_id = data.employee_id;

            // Convert numeric fields
            if (data.break_duration) data.break_duration = parseInt(data.break_duration);
            data.is_overtime = data.is_overtime === 'on' ? 1 : 0;

            // Find employee name for worker_name
            const employee = this.employees.find(e => e.id == data.employee_id);
            if (employee) {
                data.worker_name = `${employee.first_name} ${employee.last_name}`;
            }

            console.log('[TeamApp] Creating timesheet:', data);

            // Show loading state
            const saveBtn = $('#tt-save-entry-btn');
            const originalText = saveBtn.text();
            saveBtn.prop('disabled', true).text('Saving...');

            try {
                const result = await TeamAPI.createTimesheet(data);
                console.log('[TeamApp] Timesheet created:', result);
                this.showToast('Timesheet entry saved', 'success');
                
                // Log activity to job timeline
                const jobId = data.job_id || this.getCurrentJobId();
                const hours = result?.total_hours || data.total_hours || 'some';
                const employeeName = data.worker_name || 'Employee';
                const workDate = data.work_date || 'today';
                try { 
                    await TeamAPI.logActivity(jobId, `Logged ${hours} hours - ${employeeName} (${workDate})`, 'timesheets'); 
                } catch(e) { 
                    console.error('[Activity] Failed to log:', e); 
                }
                
                this.closeModal();
                // Refresh both timesheets and team data to update hours
                await this.loadTimesheetsData();
                if (this.currentTab === 'team') {
                    await this.loadTeamData();
                }
            } catch (error) {
                console.error('[TeamApp] Failed to save timesheet:', error);
                // Extract error message from various possible formats
                let message = 'Failed to save entry. Please try again.';
                if (error?.message) {
                    message = error.message;
                } else if (error?.error) {
                    message = error.error;
                } else if (typeof error === 'string') {
                    message = error;
                }
                this.showToast(message, 'error', 'Save Failed');
            } finally {
                saveBtn.prop('disabled', false).text(originalText);
            }
        },

        viewMemberProfile(id) {
            // To be implemented with full profile modal
            this.showToast('Profile view coming soon', 'info');
        },

        async editMember(id) {
            // Find employee from current list or fetch from API
            let employee = this.employees.find(e => e.id == id);
            
            if (!employee) {
                try {
                    employee = await TeamAPI.getEmployee(id);
                } catch (error) {
                    console.error('[TeamApp] Failed to load employee:', error);
                    this.showToast('Failed to load employee data', 'error');
                    return;
                }
            }
            
            if (!employee) {
                this.showToast('Employee not found', 'error');
                return;
            }
            
            this.openEditMemberModal(employee);
        },

        openEditMemberModal(employee) {
            // Remove any existing modal first
            $('#tt-modal-overlay').remove();
            $('body').removeClass('tt-modal-open');
            
            const jobId = this.getCurrentJobId();
            
            // Helper function to safely escape HTML
            const escapeHtml = (text) => {
                if (!text) return '';
                return text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            };
            
            // Helper to check if radio should be checked
            const isChecked = (value, target) => value === target ? 'checked' : '';
            
            // Helper to check if option should be selected
            const isSelected = (value, target) => value == target ? 'selected' : '';
            
            const html = `
                <div class="tt-modal-overlay" id="tt-modal-overlay">
                    <div class="tt-modal-container large">
                        <div class="tt-modal-header">
                            <h3>Edit Team Member</h3>
                            <button type="button" class="tt-modal-close" id="tt-modal-close">&times;</button>
                        </div>
                        <div class="tt-modal-body">
                            <form id="tt-edit-member-form" autocomplete="off" data-employee-id="${employee.id}">
                                <!-- Employee Header Card -->
                                <div class="tt-employee-header-card" style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--tt-bg-secondary); border-radius: 8px; margin-bottom: 20px;">
                                    <div class="tt-preview-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--tt-primary), var(--tt-primary-dark)); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 600;">
                                        ${employee.first_name?.charAt(0) || ''}${employee.last_name?.charAt(0) || ''}
                                    </div>
                                    <div class="tt-preview-info">
                                        <div class="tt-preview-name" style="font-size: 18px; font-weight: 600; color: var(--tt-text-primary);">${escapeHtml(employee.first_name)} ${escapeHtml(employee.last_name)}</div>
                                        <div class="tt-preview-details" style="font-size: 14px; color: var(--tt-text-secondary); margin-top: 4px;">
                                            ${employee.employee_code ? `ID: ${escapeHtml(employee.employee_code)} • ` : ''}
                                            ${employee.role || 'No role'}${employee.trade ? ` • ${employee.trade}` : ''}
                                        </div>
                                    </div>
                                    <div class="tt-status-badge-container" style="margin-left: auto;">
                                        <span class="tt-badge ${employee.status === 'active' ? 'tt-badge-green' : 'tt-badge-gray'}">
                                            ${employee.status === 'active' ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Form Sections in Tabs -->
                                <div class="tt-edit-tabs">
                                    <div class="tt-edit-tab-headers">
                                        <button type="button" class="tt-edit-tab-header active" data-tab="personal">Personal Info</button>
                                        <button type="button" class="tt-edit-tab-header" data-tab="work">Work Details</button>
                                        <button type="button" class="tt-edit-tab-header" data-tab="permissions">Permissions</button>
                                    </div>
                                    
                                    <!-- Personal Info Tab -->
                                    <div class="tt-edit-tab-content active" id="tab-personal">
                                        <div class="tt-form-row">
                                            <div class="tt-form-group">
                                                <label for="tt-edit-first-name" class="tt-form-label-required">First Name</label>
                                                <input type="text" id="tt-edit-first-name" class="tt-form-input" name="first_name" value="${escapeHtml(employee.first_name)}" required autocomplete="given-name">
                                            </div>
                                            <div class="tt-form-group">
                                                <label for="tt-edit-last-name" class="tt-form-label-required">Last Name</label>
                                                <input type="text" id="tt-edit-last-name" class="tt-form-input" name="last_name" value="${escapeHtml(employee.last_name)}" required autocomplete="family-name">
                                            </div>
                                        </div>
                                        <div class="tt-form-row">
                                            <div class="tt-form-group">
                                                <label for="tt-edit-email" class="tt-form-label-required">Email</label>
                                                <input type="email" id="tt-edit-email" class="tt-form-input" name="email" value="${escapeHtml(employee.email)}" required autocomplete="email">
                                            </div>
                                            <div class="tt-form-group">
                                                <label for="tt-edit-phone">Phone</label>
                                                <input type="tel" id="tt-edit-phone" class="tt-form-input" name="phone" value="${escapeHtml(employee.phone || '')}" autocomplete="tel">
                                            </div>
                                        </div>
                                        <div class="tt-form-row">
                                            <div class="tt-form-group">
                                                <label for="tt-edit-mobile">Mobile</label>
                                                <input type="tel" id="tt-edit-mobile" class="tt-form-input" name="mobile" value="${escapeHtml(employee.mobile || '')}" autocomplete="tel">
                                            </div>
                                            <div class="tt-form-group">
                                                <label for="tt-edit-status" class="tt-form-label-required">Status</label>
                                                <select id="tt-edit-status" class="tt-form-select" name="status" required>
                                                    <option value="active" ${isSelected(employee.status, 'active')}>Active</option>
                                                    <option value="inactive" ${isSelected(employee.status, 'inactive')}>Inactive</option>
                                                    <option value="on_leave" ${isSelected(employee.status, 'on_leave')}>On Leave</option>
                                                    <option value="terminated" ${isSelected(employee.status, 'terminated')}>Terminated</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Work Details Tab -->
                                    <div class="tt-edit-tab-content" id="tab-work" style="display: none;">
                                        <div class="tt-form-row">
                                            <div class="tt-form-group">
                                                <label for="tt-edit-role" class="tt-form-label-required">Role</label>
                                                <select id="tt-edit-role" class="tt-form-select" name="role" required>
                                                    <option value="">Select Role</option>
                                                    <option value="Site Manager" ${isSelected(employee.role, 'Site Manager')}>Site Manager</option>
                                                    <option value="Foreman" ${isSelected(employee.role, 'Foreman')}>Foreman</option>
                                                    <option value="Superintendent" ${isSelected(employee.role, 'Superintendent')}>Superintendent</option>
                                                    <option value="Project Manager" ${isSelected(employee.role, 'Project Manager')}>Project Manager</option>
                                                    <option value="Carpenter" ${isSelected(employee.role, 'Carpenter')}>Carpenter</option>
                                                    <option value="Electrician" ${isSelected(employee.role, 'Electrician')}>Electrician</option>
                                                    <option value="Plumber" ${isSelected(employee.role, 'Plumber')}>Plumber</option>
                                                    <option value="Mason" ${isSelected(employee.role, 'Mason')}>Mason</option>
                                                    <option value="HVAC Technician" ${isSelected(employee.role, 'HVAC Technician')}>HVAC Technician</option>
                                                    <option value="Painter" ${isSelected(employee.role, 'Painter')}>Painter</option>
                                                    <option value="Roofer" ${isSelected(employee.role, 'Roofer')}>Roofer</option>
                                                    <option value="Laborer" ${isSelected(employee.role, 'Laborer')}>Laborer</option>
                                                    <option value="Other" ${isSelected(employee.role, 'Other')}>Other</option>
                                                </select>
                                            </div>
                                            <div class="tt-form-group">
                                                <label for="tt-edit-trade">Trade</label>
                                                <select id="tt-edit-trade" class="tt-form-select" name="trade">
                                                    <option value="">Select Trade</option>
                                                    <option value="Carpentry" ${isSelected(employee.trade, 'Carpentry')}>Carpentry</option>
                                                    <option value="Electrical" ${isSelected(employee.trade, 'Electrical')}>Electrical</option>
                                                    <option value="Plumbing" ${isSelected(employee.trade, 'Plumbing')}>Plumbing</option>
                                                    <option value="Masonry" ${isSelected(employee.trade, 'Masonry')}>Masonry</option>
                                                    <option value="HVAC" ${isSelected(employee.trade, 'HVAC')}>HVAC</option>
                                                    <option value="Painting" ${isSelected(employee.trade, 'Painting')}>Painting</option>
                                                    <option value="Roofing" ${isSelected(employee.trade, 'Roofing')}>Roofing</option>
                                                    <option value="General" ${isSelected(employee.trade, 'General')}>General</option>
                                                    <option value="Landscaping" ${isSelected(employee.trade, 'Landscaping')}>Landscaping</option>
                                                    <option value="Demolition" ${isSelected(employee.trade, 'Demolition')}>Demolition</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="tt-form-row">
                                            <div class="tt-form-group">
                                                <label for="tt-edit-skill-level">Skill Level</label>
                                                <select id="tt-edit-skill-level" class="tt-form-select" name="skill_level">
                                                    <option value="">Select Level</option>
                                                    <option value="apprentice" ${isSelected(employee.skill_level, 'apprentice')}>Apprentice</option>
                                                    <option value="journeyman" ${isSelected(employee.skill_level, 'journeyman')}>Journeyman</option>
                                                    <option value="master" ${isSelected(employee.skill_level, 'master')}>Master</option>
                                                    <option value="helper" ${isSelected(employee.skill_level, 'helper')}>Helper</option>
                                                </select>
                                            </div>
                                            <div class="tt-form-group">
                                                <label for="tt-edit-employment-type">Employment Type</label>
                                                <select id="tt-edit-employment-type" class="tt-form-select" name="employment_type">
                                                    <option value="">Select Type</option>
                                                    <option value="full_time" ${isSelected(employee.employment_type, 'full_time')}>Full Time</option>
                                                    <option value="part_time" ${isSelected(employee.employment_type, 'part_time')}>Part Time</option>
                                                    <option value="contract" ${isSelected(employee.employment_type, 'contract')}>Contract</option>
                                                    <option value="subcontractor" ${isSelected(employee.employment_type, 'subcontractor')}>Subcontractor</option>
                                                    <option value="temporary" ${isSelected(employee.employment_type, 'temporary')}>Temporary</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="tt-form-row">
                                            <div class="tt-form-group">
                                                <label for="tt-edit-department">Department</label>
                                                <input type="text" id="tt-edit-department" class="tt-form-input" name="department" value="${escapeHtml(employee.department || '')}" placeholder="e.g., Field Operations">
                                            </div>
                                            <div class="tt-form-group">
                                                <label for="tt-edit-termination-date">Termination Date</label>
                                                <input type="date" id="tt-edit-termination-date" class="tt-form-input" name="termination_date" value="${employee.termination_date || ''}">
                                                <div class="tt-helper-text">Only applicable if status is Terminated</div>
                                            </div>
                                        </div>
                                        <div class="tt-form-row">
                                            <div class="tt-form-group">
                                                <label for="tt-edit-rate">Hourly Rate (£)</label>
                                                <input type="number" id="tt-edit-rate" class="tt-form-input" name="hourly_rate" step="0.01" min="0" value="${employee.hourly_rate || ''}" placeholder="0.00">
                                            </div>
                                            <div class="tt-form-group">
                                                <label for="tt-edit-cost-code">Default Cost Code</label>
                                                <select id="tt-edit-cost-code" class="tt-form-select" name="default_cost_code">
                                                    <option value="">Select Cost Code</option>
                                                    ${(this.costCodes || []).map(c => `<option value="${escapeHtml(c.code)}" ${isSelected(employee.default_cost_code, c.code)}>${escapeHtml(c.code)} - ${escapeHtml(c.description)}</option>`).join('')}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Permissions Tab -->
                                    <div class="tt-edit-tab-content" id="tab-permissions" style="display: none;">
                                        <div class="tt-form-group">
                                            <label>Permissions Level</label>
                                            <div class="tt-radio-group">
                                                <label class="tt-radio-item" for="tt-edit-perm-worker">
                                                    <input type="radio" id="tt-edit-perm-worker" name="permissions_level" value="field_worker" ${isChecked(employee.permissions_level, 'field_worker') || isChecked(employee.permissions_level, '') || isChecked(employee.permissions_level, null)}>
                                                    <span class="tt-radio-label">
                                                        <strong>Field Worker</strong> - Can log time, view own records
                                                    </span>
                                                </label>
                                                <label class="tt-radio-item" for="tt-edit-perm-foreman">
                                                    <input type="radio" id="tt-edit-perm-foreman" name="permissions_level" value="foreman" ${isChecked(employee.permissions_level, 'foreman')}>
                                                    <span class="tt-radio-label">
                                                        <strong>Foreman</strong> - Can approve crew timesheets
                                                    </span>
                                                </label>
                                                <label class="tt-radio-item" for="tt-edit-perm-supervisor">
                                                    <input type="radio" id="tt-edit-perm-supervisor" name="permissions_level" value="supervisor" ${isChecked(employee.permissions_level, 'supervisor')}>
                                                    <span class="tt-radio-label">
                                                        <strong>Supervisor</strong> - Can approve all timesheets, manage crew
                                                    </span>
                                                </label>
                                                <label class="tt-radio-item" for="tt-edit-perm-admin">
                                                    <input type="radio" id="tt-edit-perm-admin" name="permissions_level" value="admin" ${isChecked(employee.permissions_level, 'admin')}>
                                                    <span class="tt-radio-label">
                                                        <strong>Admin</strong> - Full access to all features
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="tt-form-group" style="margin-top: 20px;">
                                            <label>Approval Permissions</label>
                                            <div class="tt-checkbox-group">
                                                <label class="tt-checkbox-item" for="tt-edit-approve-timesheets">
                                                    <input type="checkbox" id="tt-edit-approve-timesheets" name="can_approve_timesheets" value="1" ${employee.can_approve_timesheets == 1 ? 'checked' : ''}>
                                                    <span class="tt-checkbox-label">Can approve timesheets</span>
                                                </label>
                                                <label class="tt-checkbox-item" for="tt-edit-approve-expenses">
                                                    <input type="checkbox" id="tt-edit-approve-expenses" name="can_approve_expenses" value="1" ${employee.can_approve_expenses == 1 ? 'checked' : ''}>
                                                    <span class="tt-checkbox-label">Can approve expenses</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${jobId ? `<input type="hidden" name="job_id" value="${jobId}">` : ''}
                            </form>
                        </div>
                        <div class="tt-modal-footer" style="justify-content: space-between;">
                            <button type="button" class="tt-btn tt-btn-danger tt-btn-sm" id="tt-delete-member-btn">Delete Employee</button>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="tt-btn tt-btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="tt-btn tt-btn-primary" id="tt-save-edit-btn">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(html).addClass('tt-modal-open');
            
            // Bind tab switching
            $('.tt-edit-tab-header').on('click', (e) => {
                const $btn = $(e.currentTarget);
                const tab = $btn.data('tab');
                
                // Update active tab header
                $('.tt-edit-tab-header').removeClass('active');
                $btn.addClass('active');
                
                // Show corresponding content
                $('.tt-edit-tab-content').hide().removeClass('active');
                $(`#tab-${tab}`).show().addClass('active');
            });
            
            // Bind close button
            $('#tt-modal-close, [data-dismiss="modal"]').on('click', () => this.closeModal());
            
            // Bind save button
            $('#tt-save-edit-btn').on('click', () => this.saveEditedMember());
            
            // Bind delete button
            $('#tt-delete-member-btn').on('click', () => {
                if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                    this.deleteMember(employee.id);
                }
            });
            
            // Bind overlay click
            $('#tt-modal-overlay').on('click', (e) => {
                if (e.target.id === 'tt-modal-overlay') this.closeModal();
            });
            
            // Bind escape key
            $(document).on('keydown.tt-modal', (e) => {
                if (e.key === 'Escape') this.closeModal();
            });
        },

        async saveEditedMember() {
            const form = $('#tt-edit-member-form');
            const employeeId = form.data('employee-id');
            
            // Validate required fields
            const requiredFields = ['first_name', 'last_name', 'email', 'role'];
            for (const field of requiredFields) {
                const value = form.find(`[name="${field}"]`).val()?.trim();
                if (!value) {
                    this.showToast(`${field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())} is required`, 'error');
                    form.find(`[name="${field}"]`).focus();
                    // Switch to appropriate tab
                    if (field === 'first_name' || field === 'last_name' || field === 'email') {
                        $('.tt-edit-tab-header[data-tab="personal"]').click();
                    } else if (field === 'role') {
                        $('.tt-edit-tab-header[data-tab="work"]').click();
                    }
                    return;
                }
            }
            
            // Collect form data
            const data = {};
            form.serializeArray().forEach(item => {
                if (item.value !== '') {
                    data[item.name] = item.value;
                }
            });
            
            // Handle checkbox values (unchecked checkboxes don't appear in serializeArray)
            data.can_approve_timesheets = form.find('[name="can_approve_timesheets"]').is(':checked') ? 1 : 0;
            data.can_approve_expenses = form.find('[name="can_approve_expenses"]').is(':checked') ? 1 : 0;
            
            // Convert hourly_rate to number
            if (data.hourly_rate) {
                data.hourly_rate = parseFloat(data.hourly_rate);
            }
            
            // Show loading state
            const saveBtn = $('#tt-save-edit-btn');
            const originalText = saveBtn.text();
            saveBtn.prop('disabled', true).text('Saving...');
            
            try {
                console.log('[TeamApp] Updating employee:', employeeId, data);
                const result = await TeamAPI.updateEmployee(employeeId, data);
                console.log('[TeamApp] Employee updated:', result);
                
                this.showToast('Employee updated successfully', 'success');
                this.closeModal();
                await this.loadTeamData();
            } catch (error) {
                console.error('[TeamApp] Failed to update employee:', error);
                const message = error?.message || error?.error || 'Failed to update employee. Please try again.';
                this.showToast(message, 'error');
            } finally {
                saveBtn.prop('disabled', false).text(originalText);
            }
        },

        async deleteMember(id) {
            try {
                await TeamAPI.deleteEmployee(id);
                this.showToast('Employee deleted successfully', 'success');
                this.closeModal();
                await this.loadTeamData();
            } catch (error) {
                console.error('[TeamApp] Failed to delete employee:', error);
                const message = error?.message || error?.error || 'Failed to delete employee. Please try again.';
                this.showToast(message, 'error');
            }
        },

        logTimeForMember(id) {
            this.openNewEntryModal(id);
        },

        viewCalendar() {
            window.open('/calendar', '_blank');
        },

        viewTasks() {
            window.open('/tasks', '_blank');
        },

        viewExpenses() {
            window.open('/expenses', '_blank');
        },

        toggleTimesheetView() {
            $('#tt-timesheet-table-view, #tt-timesheet-grid-view').toggle();
        },

        showPendingApprovals() {
            $('#tt-filter-status').val('pending').trigger('change');
        },

        bulkInvite() { this.showToast('Bulk invite coming soon', 'info'); },
        bulkDeactivate() { this.showToast('Bulk deactivate coming soon', 'info'); },
        clearSelection() { 
            this.selectedEmployees.clear();
            $('.tt-select-row, .tt-select-all').prop('checked', false);
            this.updateBulkActions();
        },
        clearEmployeeFilters() {
            this.filters.employees = { role: '', trade: '', status: '', search: '' };
            $('#tt-search').val('');
            this.loadTeamData();
            this.showToast('Filters cleared', 'success');
        },
        exportEmployees() { this.showToast('Export coming soon', 'info'); },
        
        updateTimesheetBulkActions() {
            const count = this.selectedTimesheets.size;
            $('#tt-timesheet-selected-count').text(count);
            $('#tt-timesheet-bulk-actions').toggleClass('active', count > 0);
        },
        
        async bulkApprove() {
            if (this.selectedTimesheets.size === 0) return;
            
            try {
                const promises = Array.from(this.selectedTimesheets).map(id => 
                    TeamAPI.updateTimesheet(id, { status: 'approved' })
                );
                await Promise.all(promises);
                this.showToast(`${this.selectedTimesheets.size} entries approved`, 'success');
                this.clearTimesheetSelection();
                await this.loadTimesheetsData();
            } catch (error) {
                this.showToast('Failed to approve entries', 'error');
            }
        },
        
        async bulkReject() {
            if (this.selectedTimesheets.size === 0) return;
            
            try {
                const promises = Array.from(this.selectedTimesheets).map(id => 
                    TeamAPI.updateTimesheet(id, { status: 'rejected' })
                );
                await Promise.all(promises);
                this.showToast(`${this.selectedTimesheets.size} entries rejected`, 'success');
                this.clearTimesheetSelection();
                await this.loadTimesheetsData();
            } catch (error) {
                this.showToast('Failed to reject entries', 'error');
            }
        },
        
        clearTimesheetSelection() { 
            this.selectedTimesheets.clear();
            $('.tt-select-timesheet-row, .tt-select-all-timesheets').prop('checked', false);
            this.updateTimesheetBulkActions();
        },
        
        async approveEntry(id) {
            try {
                await TeamAPI.updateTimesheet(id, { status: 'approved' });
                this.showToast('Entry approved', 'success');
                await this.loadTimesheetsData();
            } catch (error) {
                this.showToast('Failed to approve entry', 'error');
            }
        },
        
        async rejectEntry(id) {
            try {
                await TeamAPI.updateTimesheet(id, { status: 'rejected' });
                this.showToast('Entry rejected', 'success');
                await this.loadTimesheetsData();
            } catch (error) {
                this.showToast('Failed to reject entry', 'error');
            }
        },
        
        async deleteEntry(id) {
            if (!confirm('Are you sure you want to delete this timesheet entry?')) return;
            
            try {
                await TeamAPI.deleteTimesheet(id);
                this.showToast('Entry deleted', 'success');
                await this.loadTimesheetsData();
            } catch (error) {
                this.showToast('Failed to delete entry', 'error');
            }
        },
        
        editEntry(id) {
            const timesheet = this.timesheets.find(ts => ts.id == id);
            if (!timesheet) {
                this.showToast('Entry not found', 'error');
                return;
            }
            this.openEditEntryModal(timesheet);
        },
        
        openEditEntryModal(timesheet) {
            // Remove any existing modal first
            $('#tt-modal-overlay').remove();
            $('body').removeClass('tt-modal-open');
            
            const html = `
                <div class="tt-modal-overlay" id="tt-modal-overlay">
                    <div class="tt-modal-container medium">
                        <div class="tt-modal-header">
                            <h3>Edit Timesheet Entry</h3>
                            <button type="button" class="tt-modal-close" id="tt-modal-close">&times;</button>
                        </div>
                        <div class="tt-modal-body">
                            <form id="tt-edit-timesheet-form" autocomplete="off">
                                <input type="hidden" name="timesheet_id" value="${timesheet.id}">
                                <div class="tt-form-group">
                                    <label for="tt-edit-employee" class="tt-form-label-required">Employee</label>
                                    <select id="tt-edit-employee" class="tt-form-select" name="employee_id" disabled autocomplete="off">
                                        ${(this.employees || []).map(e => `<option value="${e.id}" ${e.id == timesheet.worker_id ? 'selected' : ''}>${e.first_name} ${e.last_name}</option>`).join('')}
                                    </select>
                                    <input type="hidden" name="employee_id" value="${timesheet.worker_id}">
                                </div>
                                <div class="tt-form-row">
                                    <div class="tt-form-group">
                                        <label for="tt-edit-date" class="tt-form-label-required">Date</label>
                                        <input type="date" id="tt-edit-date" class="tt-form-input" name="work_date" value="${timesheet.work_date}" required autocomplete="off">
                                    </div>
                                    <div class="tt-form-group">
                                        <label for="tt-edit-cost-code">Cost Code</label>
                                        <select id="tt-edit-cost-code" class="tt-form-select" name="cost_code" autocomplete="off">
                                            <option value="">Select Cost Code</option>
                                            ${(this.costCodes || []).map(c => `<option value="${c.code}" ${c.code === timesheet.cost_code ? 'selected' : ''}>${c.code} - ${c.description}</option>`).join('')}
                                        </select>
                                    </div>
                                </div>
                                <div class="tt-form-row">
                                    <div class="tt-form-group">
                                        <label for="tt-edit-start" class="tt-form-label-required">Start Time</label>
                                        <input type="time" id="tt-edit-start" class="tt-form-input" name="start_time" value="${timesheet.start_time ? timesheet.start_time.substring(0, 5) : ''}" required autocomplete="off">
                                    </div>
                                    <div class="tt-form-group">
                                        <label for="tt-edit-end" class="tt-form-label-required">End Time</label>
                                        <input type="time" id="tt-edit-end" class="tt-form-input" name="end_time" value="${timesheet.end_time ? timesheet.end_time.substring(0, 5) : ''}" required autocomplete="off">
                                    </div>
                                </div>
                                <div class="tt-form-row">
                                    <div class="tt-form-group">
                                        <label for="tt-edit-break">Break (minutes)</label>
                                        <input type="number" id="tt-edit-break" class="tt-form-input" name="break_duration" value="${timesheet.break_duration || 0}" min="0" step="5" autocomplete="off">
                                    </div>
                                    <div class="tt-form-group">
                                        <label for="tt-edit-status">Status</label>
                                        <select id="tt-edit-status" class="tt-form-select" name="status" autocomplete="off">
                                            <option value="pending" ${timesheet.status === 'pending' ? 'selected' : ''}>Pending</option>
                                            <option value="approved" ${timesheet.status === 'approved' ? 'selected' : ''}>Approved</option>
                                            <option value="rejected" ${timesheet.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="tt-form-group">
                                    <label for="tt-edit-notes">Notes</label>
                                    <textarea id="tt-edit-notes" class="tt-form-input" name="notes" rows="3" autocomplete="off">${timesheet.task_description || ''}</textarea>
                                </div>
                            </form>
                        </div>
                        <div class="tt-modal-footer">
                            <button type="button" class="tt-btn tt-btn-danger tt-btn-sm" onclick="TeamApp.deleteEntry(${timesheet.id})">Delete</button>
                            <div style="margin-left: auto; display: flex; gap: 8px;">
                                <button type="button" class="tt-btn tt-btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="tt-btn tt-btn-primary" id="tt-update-entry-btn">Update Entry</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(html).addClass('tt-modal-open');
            
            // Bind close button
            $('#tt-modal-close, [data-dismiss="modal"]').on('click', () => this.closeModal());
            
            // Bind update button
            $('#tt-update-entry-btn').on('click', () => this.updateTimesheetEntry());
            
            // Bind overlay click
            $('#tt-modal-overlay').on('click', (e) => {
                if (e.target.id === 'tt-modal-overlay') this.closeModal();
            });
            
            // Bind escape key
            $(document).on('keydown.tt-modal', (e) => {
                if (e.key === 'Escape') this.closeModal();
            });
        },
        
        async updateTimesheetEntry() {
            const form = $('#tt-edit-timesheet-form');
            if (!form.length) return;
            
            const timesheetId = form.find('[name="timesheet_id"]').val();
            const data = {
                work_date: form.find('[name="work_date"]').val(),
                start_time: form.find('[name="start_time"]').val(),
                end_time: form.find('[name="end_time"]').val(),
                break_duration: parseInt(form.find('[name="break_duration"]').val() || 0),
                cost_code: form.find('[name="cost_code"]').val(),
                status: form.find('[name="status"]').val(),
                task_description: form.find('[name="notes"]').val()
            };
            
            const saveBtn = $('#tt-update-entry-btn');
            saveBtn.prop('disabled', true).text('Updating...');
            
            try {
                await TeamAPI.updateTimesheet(timesheetId, data);
                this.showToast('Entry updated successfully', 'success');
                this.closeModal();
                await this.loadTimesheetsData();
            } catch (error) {
                this.showToast('Failed to update entry', 'error');
                saveBtn.prop('disabled', false).text('Update Entry');
            }
        },
        
        exportTimesheets() { 
            TeamAPI.exportTimesheets({ format: 'csv' }).then(data => {
                if (data.content) {
                    const blob = new Blob([data.content], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.filename;
                    a.click();
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(() => {
        // Check if we're on the team timesheets page
        if ($('#pi-team-timesheets').length) {
            TeamState.init();
        }
    });

    // Expose to global scope for onclick handlers
    window.TeamApp = TeamState;

})(jQuery);
