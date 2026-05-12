/**
 * Planning Index Workspace - Team & Timesheets Common Module v1.5.2
 * Fixed: Independent crews system - crews are reusable groups assignable to jobs
 * Global/Common Team & Timesheets management (similar to Expenses V3)
 * 
 * This module provides:
 * - Global overview of all team members and timesheets across all jobs
 * - Employee management (CRUD)
 * - Timesheet entry and approval workflow
 * - Crew management
 * 
 * API Base: /wp-json/pi/v1/
 * Endpoints: employees, timesheets, crews, team, team-dashboard
 * 
 * @version 1.5.2 - Fixed weekly hours chart and activity feed
 */

(function($) {
    'use strict';
    
    const TeamAPI = {
        baseUrl: (window.PI_Team_Data?.restUrl || '/wp-json/pi/v1').replace(/\/$/, ''),
        nonce: window.PI_Team_Data?.nonce || '',
        currency: window.PI_Team_Data?.currency || 'GBP',
        currentJobId: window.PI_Team_Data?.currentJobId || null,
        
        async request(endpoint, options = {}) {
            const url = `${this.baseUrl}/${endpoint.replace(/^\//, '')}`;
            const defaults = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                }
            };
            
            console.log('[TeamAPI] Request:', options.method || 'GET', url, options.body);
            
            const response = await fetch(url, { ...defaults, ...options });
            const data = await response.json().catch(() => ({}));
            
            console.log('[TeamAPI] Response:', response.status, data);
            
            if (!response.ok) {
                // Handle WordPress REST API error formats
                const errorMessage = data.message || data.error || 
                                     (data.data && data.data.message) || 
                                     `HTTP ${response.status}: ${response.statusText}`;
                throw new Error(errorMessage);
            }
            return data;
        },
        
        // Employees
        getEmployees(params = {}) {
            const query = new URLSearchParams(params).toString();
            return this.request(query ? `employees?${query}` : 'employees');
        },
        createEmployee(data) {
            return this.request('employees', { method: 'POST', body: JSON.stringify(data) });
        },
        assignTeamMember(data) {
            // Assign existing employee to a job
            return this.request('team-members', { method: 'POST', body: JSON.stringify(data) });
        },
        updateEmployee(id, data) {
            return this.request(`employees/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        deleteEmployee(id) {
            return this.request(`employees/${id}`, { method: 'DELETE' });
        },
        getEmployeeStats(id) {
            return this.request(`employees/${id}/stats`);
        },
        
        // Crews - Independent/Reusable groups
        getCrews() {
            return this.request('crews');
        },
        getCrew(id) {
            return this.request(`crews/${id}`);
        },
        createCrew(data) {
            return this.request('crews', { method: 'POST', body: JSON.stringify(data) });
        },
        updateCrew(id, data) {
            return this.request(`crews/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        // Crew assignments to jobs
        getAvailableCrews(jobId) {
            const useJobId = jobId || this.currentJobId;
            return this.request(`crews/available?job_id=${useJobId}`);
        },
        getJobCrews(jobId) {
            const useJobId = jobId || this.currentJobId;
            return this.request(`crews/assigned/${useJobId}`);
        },
        assignCrewToJob(crewId, jobId, options = {}) {
            return this.request('crews/assign', { 
                method: 'POST', 
                body: JSON.stringify({ crew_id: crewId, job_id: jobId, ...options })
            });
        },
        unassignCrewFromJob(assignmentId, removeMembers = false) {
            return this.request(`crews/unassign/${assignmentId}`, {
                method: 'POST',
                body: JSON.stringify({ remove_members: removeMembers })
            });
        },
        
        // Team Assignments (job-specific)
        getTeamAssignments(jobId = null) {
            const query = jobId ? `?job_id=${jobId}` : '';
            return this.request(`team${query}`);
        },
        assignTeamMember(data) {
            return this.request('team', { method: 'POST', body: JSON.stringify(data) });
        },
        removeTeamMember(id) {
            return this.request(`team/${id}`, { method: 'DELETE' });
        },
        getAvailableWorkers() {
            return this.request('team/available');
        },
        
        // Timesheets - with full all_jobs support for global sync
        getTimesheets(params = {}) {
            // For global view (no specific job), set all_jobs=1 to fetch ALL timesheets from ALL jobs
            if (!params.job_id && !params.all_jobs) {
                params.all_jobs = '1';
            }
            const query = new URLSearchParams(params).toString();
            console.log('[TeamAPI] Fetching timesheets with query:', query);
            return this.request(query ? `timesheets?${query}` : 'timesheets');
        },
        createTimesheet(data) {
            return this.request('timesheets', { method: 'POST', body: JSON.stringify(data) });
        },
        updateTimesheet(id, data) {
            return this.request(`timesheets/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        deleteTimesheet(id) {
            return this.request(`timesheets/${id}`, { method: 'DELETE' });
        },
        getTimesheetSummary(jobId = null) {
            const query = jobId ? `?job_id=${jobId}` : '';
            return this.request(`timesheets/summary${query}`);
        },
        
        // Dashboard
        getDashboardStats(jobId = null) {
            const useJobId = jobId || this.currentJobId;
            const query = useJobId ? `?job_id=${useJobId}` : '';
            return this.request(`team-dashboard/stats${query}`);
        },
        getWeeklyHours(jobId = null) {
            const useJobId = jobId || this.currentJobId;
            const query = useJobId ? `?job_id=${useJobId}` : '';
            return this.request(`team-dashboard/weekly-hours${query}`);
        },
        getActivity(limit = 50, jobId = null) {
            const useJobId = jobId || this.currentJobId;
            const query = useJobId ? `?job_id=${useJobId}&limit=${limit}` : `?limit=${limit}`;
            return this.request(`team-dashboard/activity${query}`);
        },
        
        // Utilities
        getCostCodes() {
            return this.request('cost-codes');
        },
        getJobs() {
            return this.request('jobs');
        }
    };
    
    const Utils = {
        formatDate(date) {
            if (!date) return '-';
            const d = new Date(date);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        
        formatTime(time) {
            if (!time) return '-';
            return time.substring(0, 5);
        },
        
        formatCurrency(amount) {
            return new Intl.NumberFormat('en-GB', { 
                style: 'currency', 
                currency: TeamAPI.currency 
            }).format(amount || 0);
        },
        
        formatHours(hours) {
            return hours ? parseFloat(hours).toFixed(2) : '0.00';
        },
        
        getStatusBadge(status) {
            const badges = {
                'active': '<span class="pi-status-badge pi-status-active">Active</span>',
                'inactive': '<span class="pi-status-badge pi-status-inactive">Inactive</span>',
                'pending': '<span class="pi-status-badge pi-status-pending">Pending</span>',
                'approved': '<span class="pi-status-badge pi-status-approved">Approved</span>',
                'rejected': '<span class="pi-status-badge pi-status-rejected">Rejected</span>'
            };
            return badges[status] || `<span class="pi-status-badge">${status}</span>`;
        },
        
        showNotification(message, type = 'success') {
            const $toast = $(`<div class="pi-toast pi-toast-${type}">${message}</div>`);
            $('body').append($toast);
            $toast.addClass('show');
            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => $toast.remove(), 300);
            }, 3000);
        },
        
        showModal(title, content, options = {}) {
            const backdrop = options.backdrop !== false;
            const modalHtml = `
                <div class="pi-modal-overlay ${backdrop ? '' : 'no-backdrop'}" id="pi-modal">
                    <div class="pi-modal ${options.size || ''}">
                        <div class="pi-modal-header">
                            <h3>${title}</h3>
                            <button class="pi-modal-close" onclick="TeamApp.closeModal()">&times;</button>
                        </div>
                        <div class="pi-modal-body">
                            ${content}
                        </div>
                        ${options.footer ? `<div class="pi-modal-footer">${options.footer}</div>` : ''}
                    </div>
                </div>
            `;
            
            // Remove any existing modal first
            $('#pi-modal').remove();
            
            // Append to body for maximum z-index and visibility
            $('body').append(modalHtml);
            
            // Force visibility with explicit CSS
            $('#pi-modal').css({
                'display': 'flex',
                'visibility': 'visible',
                'opacity': '1'
            });
            
            // Prevent body scroll
            $('body').addClass('pi-modal-open');
            
            if (backdrop) {
                $('#pi-modal').on('click', (e) => {
                    if (e.target === e.currentTarget) TeamApp.closeModal();
                });
            }
            
            console.log('[Utils] Modal shown:', title);
        },
        
        closeModal() {
            $('#pi-modal, #pi-team-modal').fadeOut(200, function() {
                $(this).remove();
            });
            // Also remove any overlay that might be present
            $('.pi-modal-overlay').fadeOut(200, function() {
                $(this).remove();
            });
            // Re-enable body scroll
            $('body').removeClass('pi-modal-open');
        }
    };
    
    const State = {
        employees: [],
        timesheets: [],
        crews: [],
        jobs: [],
        costCodes: [],
        currentTab: 'overview',
        filters: {
            employees: {},
            timesheets: {}
        },
        selectedItems: {
            employees: [],
            timesheets: []
        }
    };
    
    const UI = {
        init() {
            console.log('[TeamTimesheets] UI.init() starting...');
            this.bindEvents();
            this.loadInitialData();
            feather.replace();
            console.log('[TeamTimesheets] UI.init() complete');
        },
        
        bindEvents() {
            // Navigation
            $(document).on('click', '.pi-team-nav-item', function() {
                const tab = $(this).data('tab');
                TeamApp.switchTab(tab);
            });
            
            // Mobile menu toggle
            $(document).on('click', '.pi-team-mobile-menu-toggle', () => {
                $('.pi-team-nav-container').toggleClass('active');
            });
            
            // Search toggle
            $(document).on('click', '.pi-team-search-trigger', () => {
                $('.pi-team-search-input').addClass('active');
                $('.pi-team-search-input input').focus();
            });
            
            $(document).on('click', '.pi-team-search-close', () => {
                $('.pi-team-search-input').removeClass('active');
                $('.pi-team-search-input input').val('');
            });
            
            // Close search on escape
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    $('.pi-team-search-input').removeClass('active');
                }
            });
            
            // Notifications dropdown
            $(document).on('click', '.pi-team-notifications .pi-team-icon-btn', (e) => {
                e.stopPropagation();
                $('.pi-team-notifications-dropdown').toggleClass('active');
                $('.pi-team-user-dropdown').removeClass('active');
            });
            
            // User menu dropdown
            $(document).on('click', '.pi-team-user-trigger', (e) => {
                e.stopPropagation();
                $('.pi-team-user-dropdown').toggleClass('active');
                $('.pi-team-notifications-dropdown').removeClass('active');
            });
            
            // Close dropdowns on outside click
            $(document).on('click', () => {
                $('.pi-team-dropdown').removeClass('active');
                $('.pi-team-nav-container').removeClass('active');
            });
            
            // Prevent dropdown close when clicking inside
            $(document).on('click', '.pi-team-dropdown, .pi-team-nav-container', (e) => {
                e.stopPropagation();
            });
            
            // Add timesheet
            $(document).on('click', '#add-timesheet-btn, #add-timesheet-btn-2', (e) => {
                console.log('[TeamTimesheets] Add timesheet button clicked');
                e.preventDefault();
                TeamApp.openAddTimesheetModal();
            });
            
            // Add employee
            $(document).on('click', '#add-employee-btn', (e) => {
                console.log('[TeamTimesheets] Add employee button clicked');
                e.preventDefault();
                TeamApp.openAddEmployeeModal();
            });
            
            // Add crew
            $(document).on('click', '#add-crew-btn', (e) => {
                console.log('[TeamTimesheets] Add crew button clicked');
                e.preventDefault();
                TeamApp.openAddCrewModal();
            });
            
            // Employee filters
            $(document).on('input', '#employee-search', Utils.debounce(() => {
                TeamApp.filterEmployees();
            }, 300));
            
            $(document).on('change', '#employee-role-filter, #employee-trade-filter, #employee-status-filter', () => {
                TeamApp.filterEmployees();
            });
            
            // Timesheet filters
            $(document).on('change', '#timesheet-start-date, #timesheet-end-date, #timesheet-employee-filter, #timesheet-status-filter', () => {
                TeamApp.filterTimesheets();
            });
            
            // Select all checkboxes
            $(document).on('change', '#select-all-employees', function() {
                const checked = $(this).is(':checked');
                $('.employee-checkbox').prop('checked', checked);
                TeamApp.updateSelectedEmployees();
            });
            
            $(document).on('change', '#select-all-timesheets', function() {
                const checked = $(this).is(':checked');
                $('.timesheet-checkbox').prop('checked', checked);
                TeamApp.updateSelectedTimesheets();
            });
            
            // Export buttons
            $(document).on('click', '#export-employees-btn', () => TeamApp.exportEmployees());
            $(document).on('click', '#export-timesheets-btn', () => TeamApp.exportTimesheets());
        },
        
        async loadInitialData() {
            try {
                // Set default date range for timesheets - 30 days to capture all recent entries including job-specific
                const today = new Date();
                const thirtyDaysAgo = new Date(today);
                thirtyDaysAgo.setDate(today.getDate() - 30);
                $('#timesheet-start-date').val(thirtyDaysAgo.toISOString().split('T')[0]);
                $('#timesheet-end-date').val(today.toISOString().split('T')[0]);
                
                console.log('[TeamTimesheets] Default date range set:', {
                    start: thirtyDaysAgo.toISOString().split('T')[0],
                    end: today.toISOString().split('T')[0]
                });
                
                // Load all data in parallel
                await Promise.all([
                    this.loadDashboardData(),
                    this.loadEmployees(),
                    this.loadJobs(),
                    this.loadCostCodes()
                ]);
                
            } catch (error) {
                console.error('Error loading initial data:', error);
                Utils.showNotification('Error loading data. Please refresh.', 'error');
            }
        },
        
        async loadDashboardData() {
            try {
                const [stats, weekly, activity] = await Promise.all([
                    TeamAPI.getDashboardStats(),
                    TeamAPI.getWeeklyHours(),
                    TeamAPI.getActivity()
                ]);
                
                this.renderOverviewStats(stats);
                this.renderWeeklyChart(weekly);
                this.renderActivity(activity);
                
            } catch (error) {
                console.error('Dashboard load error:', error);
            }
        },
        
        renderOverviewStats(stats) {
            const statsHtml = `
                <div class="pi-stat-card">
                    <div class="pi-stat-icon pi-stat-icon-blue">
                        <i data-feather="users"></i>
                    </div>
                    <div class="pi-stat-content">
                        <span class="pi-stat-value">${stats.total_employees || 0}</span>
                        <span class="pi-stat-label">Total Employees</span>
                    </div>
                </div>
                <div class="pi-stat-card">
                    <div class="pi-stat-icon pi-stat-icon-purple">
                        <i data-feather="clock"></i>
                    </div>
                    <div class="pi-stat-content">
                        <span class="pi-stat-value">${Utils.formatHours(stats.week_hours)}</span>
                        <span class="pi-stat-label">Hours This Week</span>
                        <span class="pi-stat-sub">${stats.week_start} - ${stats.week_end}</span>
                    </div>
                </div>
                <div class="pi-stat-card">
                    <div class="pi-stat-icon pi-stat-icon-orange">
                        <i data-feather="trending-up"></i>
                    </div>
                    <div class="pi-stat-content">
                        <span class="pi-stat-value">${Utils.formatHours(stats.overtime_hours)}</span>
                        <span class="pi-stat-label">Overtime Hours</span>
                    </div>
                </div>
            `;
            $('#overview-stats').html(statsHtml);
            feather.replace();
        },
        
        renderWeeklyChart(data) {
            if (!data.daily || data.daily.length === 0) {
                $('#weekly-hours-chart').html('<p class="pi-empty-state">No data available</p>');
                return;
            }
            
            // API returns total_hours, not hours
            const maxHours = Math.max(...data.daily.map(d => d.total_hours || 0), 8);
            const chartHtml = data.daily.map(day => {
                const hours = day.total_hours || 0;
                const height = (hours / maxHours * 100);
                const isWeekend = day.day_name === 'Sat' || day.day_name === 'Sun';
                return `
                    <div class="pi-chart-bar ${isWeekend ? 'weekend' : ''}" title="${hours} hours">
                        <div class="pi-chart-bar-fill" style="height: ${height}%"></div>
                        <span class="pi-chart-bar-value">${hours > 0 ? hours.toFixed(1) : ''}</span>
                        <span class="pi-chart-bar-label">${day.day_name}</span>
                    </div>
                `;
            }).join('');
            
            $('#weekly-hours-chart').html(`<div class="pi-chart-bars">${chartHtml}</div>`);
        },
        
        renderActivity(activity) {
            if (!activity || activity.length === 0) {
                $('#activity-feed').html('<p class="pi-empty-state">No recent activity</p>');
                return;
            }
            
            // API returns user_name, not first_name/last_name
            const activityHtml = activity.map(item => {
                const userName = item.user_name || 'Unknown';
                const initials = userName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                return `
                    <div class="pi-activity-item">
                        <div class="pi-activity-avatar">${initials}</div>
                        <div class="pi-activity-content">
                            <p class="pi-activity-text">
                                <strong>${userName}</strong> ${item.description}
                            </p>
                            <span class="pi-activity-time">${Utils.formatDate(item.timestamp)}</span>
                        </div>
                    </div>
                `;
            }).join('');
            
            $('#activity-feed').html(activityHtml);
        },
        
        async loadEmployees() {
            try {
                // On global page, load ALL employees by default (no status filter)
                // unless a specific filter is selected
                const statusFilter = $('#employee-status-filter').val();
                const filters = statusFilter ? { status: statusFilter } : {};
                
                const data = await TeamAPI.getEmployees(filters);
                State.employees = data || [];
                this.renderEmployees();
                this.populateEmployeeDropdown();
            } catch (error) {
                console.error('Error loading employees:', error);
            }
        },
        
        renderEmployees() {
            const tbody = $('#employees-table tbody');
            tbody.empty();
            
            if (State.employees.length === 0) {
                tbody.html('<tr><td colspan="7" class="pi-table-empty">No employees found</td></tr>');
                return;
            }
            
            State.employees.forEach(emp => {
                const row = `
                    <tr data-id="${emp.id}">
                        <td><input type="checkbox" class="employee-checkbox" value="${emp.id}"></td>
                        <td>
                            <div class="pi-employee-cell">
                                <div class="pi-employee-avatar">${emp.first_name[0]}${emp.last_name[0]}</div>
                                <div class="pi-employee-info">
                                    <strong>${emp.first_name} ${emp.last_name}</strong>
                                    <span>${emp.employee_code || emp.email}</span>
                                </div>
                            </div>
                        </td>
                        <td>${emp.role}${emp.trade ? `<br><small>${emp.trade}</small>` : ''}</td>
                        <td>${Utils.formatCurrency(emp.hourly_rate)}/hr</td>
                        <td class="pi-hours-cell" data-emp-id="${emp.id}">Loading...</td>
                        <td>${Utils.getStatusBadge(emp.status)}</td>
                        <td>
                            <button class="pi-btn-icon" onclick="TeamApp.editEmployee(${emp.id})" title="Edit">
                                <i data-feather="edit-2"></i>
                            </button>
                            <button class="pi-btn-icon" onclick="TeamApp.viewEmployeeStats(${emp.id})" title="Stats">
                                <i data-feather="bar-chart-2"></i>
                            </button>
                            <button class="pi-btn-icon pi-btn-danger" onclick="TeamApp.deleteEmployee(${emp.id})" title="Delete">
                                <i data-feather="trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            // Load hours for each employee
            State.employees.forEach(emp => {
                TeamAPI.getEmployeeStats(emp.id).then(stats => {
                    const totalHours = parseFloat(stats.week_hours || 0);
                    const otHours = parseFloat(stats.week_overtime_hours || 0);
                    let hoursText = `${totalHours.toFixed(1)} hrs`;
                    if (otHours > 0) {
                        hoursText += ` <span style="color: var(--pi-warning); font-size: 11px;">(+${otHours.toFixed(1)} OT)</span>`;
                    }
                    $(`.pi-hours-cell[data-emp-id="${emp.id}"]`).html(hoursText);
                });
            });
            
            feather.replace();
        },
        
        populateEmployeeDropdown() {
            const select = $('#timesheet-employee-filter');
            const currentVal = select.val();
            
            select.html('<option value="">All Employees</option>');
            State.employees.forEach(emp => {
                select.append(`<option value="${emp.id}">${emp.first_name} ${emp.last_name}</option>`);
            });
            
            if (currentVal) select.val(currentVal);
        },
        
        async loadTimesheets() {
            try {
                const params = {
                    start_date: $('#timesheet-start-date').val(),
                    end_date: $('#timesheet-end-date').val(),
                    employee_id: $('#timesheet-employee-filter').val(),
                    status: $('#timesheet-status-filter').val()
                };
                
                // Remove empty params
                Object.keys(params).forEach(key => {
                    if (!params[key]) delete params[key];
                });
                
                console.log('[TeamTimesheets] Loading timesheets with params:', params);
                
                const data = await TeamAPI.getTimesheets(params);
                console.log('[TeamTimesheets] Loaded', data?.length || 0, 'timesheets');
                
                // Log all returned data with IDs for debugging
                if (data && data.length > 0) {
                    console.log('[TeamTimesheets] Raw data from API:', data.map(t => ({id: t.id, worker_id: t.worker_id, job_id: t.job_id, work_date: t.work_date})));
                    
                    const jobCounts = {};
                    data.forEach(t => {
                        const jobId = t.job_id || 'null';
                        jobCounts[jobId] = (jobCounts[jobId] || 0) + 1;
                    });
                    console.log('[TeamTimesheets] Timesheets by job:', jobCounts);
                }
                
                // CRITICAL: Check for duplicate IDs from API
                const ids = (data || []).map(t => t.id);
                const uniqueIds = [...new Set(ids)];
                if (ids.length !== uniqueIds.length) {
                    console.error('[TeamTimesheets] CRITICAL: API returned duplicate IDs:', ids);
                    console.error('[TeamTimesheets] Duplicate count:', ids.length - uniqueIds.length);
                    // Remove duplicates by keeping only the first occurrence of each ID
                    const seen = new Set();
                    State.timesheets = (data || []).filter(t => {
                        if (seen.has(t.id)) {
                            console.error('[TeamTimesheets] Removing duplicate entry:', t.id);
                            return false;
                        }
                        seen.add(t.id);
                        return true;
                    });
                } else {
                    State.timesheets = data || [];
                }
                
                this.renderTimesheets();
            } catch (error) {
                console.error('[TeamTimesheets] Error loading timesheets:', error);
            }
        },
        
        renderTimesheets() {
            const tbody = $('#timesheets-table tbody');
            tbody.empty();
            
            console.log('[TeamTimesheets] Rendering', State.timesheets.length, 'timesheets');
            
            // Debug: Log all IDs to check for duplicates
            const ids = State.timesheets.map(ts => ts.id);
            const uniqueIds = [...new Set(ids)];
            if (ids.length !== uniqueIds.length) {
                console.error('[TeamTimesheets] DUPLICATE IDs DETECTED:', ids);
            }
            console.log('[TeamTimesheets] Timesheet IDs:', ids);
            
            if (State.timesheets.length === 0) {
                tbody.html('<tr><td colspan="9" class="pi-table-empty">No timesheet entries found</td></tr>');
                return;
            }
            
            State.timesheets.forEach((ts, index) => {
                console.log(`[TeamTimesheets] Rendering row ${index}: ID=${ts.id}, worker=${ts.worker_id}, job=${ts.job_id}, date=${ts.work_date}`);
                // Get job display - look up job code from State.jobs if available
                let jobDisplay = '<span class="pi-badge-global">Global</span>';
                if (ts.job_id) {
                    const job = State.jobs.find(j => j.id == ts.job_id);
                    const jobCode = job ? (job.code || job.title || `Job #${ts.job_id}`) : (ts.job_code || `Job #${ts.job_id}`);
                    jobDisplay = `<span class="pi-badge-job">${jobCode}</span>`;
                }
                
                // Build worker name from first_name/last_name (API returns these from JOIN)
                const firstName = ts.first_name || '';
                const lastName = ts.last_name || '';
                let workerName = (firstName + ' ' + lastName).trim();
                
                // Fallback to looking up in State.employees if API didn't return names
                if (!workerName && ts.worker_id) {
                    const emp = State.employees.find(e => e.id == ts.worker_id);
                    if (emp) {
                        workerName = `${emp.first_name} ${emp.last_name}`;
                    }
                }
                
                if (!workerName) workerName = 'Unknown';
                
                const row = `
                    <tr data-id="${ts.id}">
                        <td><input type="checkbox" class="timesheet-checkbox" value="${ts.id}"></td>
                        <td>${Utils.formatDate(ts.work_date)}</td>
                        <td><strong>${workerName}</strong></td>
                        <td>${jobDisplay}</td>
                        <td>${Utils.formatTime(ts.start_time)} - ${Utils.formatTime(ts.end_time)}</td>
                        <td>
                            <strong>${Utils.formatHours(ts.total_hours)}</strong>
                            ${ts.overtime_hours > 0 ? `<small class="pi-overtime">+${ts.overtime_hours} OT</small>` : ''}
                        </td>
                        <td>${ts.cost_code || '-'}</td>
                        <td>${Utils.getStatusBadge(ts.status)}</td>
                        <td>
                            <button class="pi-btn-icon" onclick="TeamApp.editTimesheet(${ts.id})" title="Edit">
                                <i data-feather="edit-2"></i>
                            </button>
                            <button class="pi-btn-icon" onclick="TeamApp.deleteTimesheet(${ts.id})" title="Delete">
                                <i data-feather="trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            feather.replace();
        },
        
        async loadCrews() {
            try {
                const data = await TeamAPI.getCrews();
                State.crews = data || [];
                this.renderCrews();
            } catch (error) {
                console.error('Error loading crews:', error);
            }
        },
        
        renderCrews() {
            const container = $('#crews-grid');
            
            if (State.crews.length === 0) {
                container.html(`
                    <div class="pi-crews-empty">
                        <div class="pi-crews-empty-icon">
                            <i data-feather="users" width="48" height="48"></i>
                        </div>
                        <h3>No Crews Created Yet</h3>
                        <p>Crews are reusable groups of employees that can be assigned to any job.</p>
                        <p>Create your first crew to quickly assign teams to jobs.</p>
                        <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.openAddCrewModal()">
                            <i data-feather="plus"></i> Create First Crew
                        </button>
                    </div>
                `);
                feather.replace();
                return;
            }
            
            const crewsHtml = State.crews.map(crew => `
                <div class="pi-crew-card" data-crew-id="${crew.id}">
                    <div class="pi-crew-header" style="border-left-color: ${crew.color_code || '#4F46E5'}">
                        <div class="pi-crew-title">
                            <h4>${crew.crew_name}</h4>
                            <span class="pi-crew-trade">${crew.trade_specialty || 'General'}</span>
                        </div>
                        <div class="pi-crew-badge">
                            <i data-feather="users" width="14"></i>
                            <span>${crew.member_count || 0}</span>
                        </div>
                    </div>
                    <div class="pi-crew-body">
                        ${crew.foreman_first ? `
                        <div class="pi-crew-foreman">
                            <i data-feather="user-check" width="16"></i>
                            <span>Foreman: ${crew.foreman_first} ${crew.foreman_last}</span>
                        </div>
                        ` : '<div class="pi-crew-foreman pi-crew-no-foreman"><i data-feather="alert-circle" width="16"></i><span>No foreman assigned</span></div>'}
                    </div>
                    <div class="pi-crew-actions">
                        <button class="pi-team-btn pi-team-btn-sm pi-team-btn-secondary" onclick="TeamApp.viewCrew(${crew.id})" title="View Details">
                            <i data-feather="eye" width="14"></i>
                        </button>
                        <button class="pi-team-btn pi-team-btn-sm pi-team-btn-secondary" onclick="TeamApp.editCrew(${crew.id})" title="Edit Crew">
                            <i data-feather="edit-2" width="14"></i>
                        </button>
                        <button class="pi-team-btn pi-team-btn-sm pi-team-btn-danger" onclick="TeamApp.deleteCrew(${crew.id})" title="Delete Crew">
                            <i data-feather="trash-2" width="14"></i>
                        </button>
                    </div>
                </div>
            `).join('');
            
            container.html(`
                <div class="pi-crews-header">
                    <div class="pi-crews-count">${State.crews.length} crew${State.crews.length !== 1 ? 's' : ''}</div>
                    <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.openAddCrewModal()">
                        <i data-feather="plus"></i> Create Crew
                    </button>
                </div>
                <div class="pi-crews-grid">${crewsHtml}</div>
            `);
            feather.replace();
        },
        
        async loadJobs() {
            try {
                const data = await TeamAPI.getJobs();
                State.jobs = data || [];
            } catch (error) {
                console.error('Error loading jobs:', error);
            }
        },
        
        async loadCostCodes() {
            try {
                const data = await TeamAPI.getCostCodes();
                State.costCodes = data || [];
            } catch (error) {
                console.error('Error loading cost codes:', error);
            }
        }
    };
    
    const Modals = {
        addEmployee() {
            console.log('[Modals] addEmployee() rendering modal');
            const content = `
                <form id="add-employee-form" class="pi-form" autocomplete="off">
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="emp-first-name">First Name *</label>
                            <input type="text" id="emp-first-name" name="first_name" required class="pi-input" autocomplete="given-name">
                        </div>
                        <div class="pi-form-group">
                            <label for="emp-last-name">Last Name *</label>
                            <input type="text" id="emp-last-name" name="last_name" required class="pi-input" autocomplete="family-name">
                        </div>
                    </div>
                    <div class="pi-form-group">
                        <label for="emp-email">Email *</label>
                        <input type="email" id="emp-email" name="email" required class="pi-input" autocomplete="email">
                    </div>
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="emp-phone">Phone</label>
                            <input type="tel" id="emp-phone" name="phone" class="pi-input" autocomplete="tel">
                        </div>
                        <div class="pi-form-group">
                            <label for="emp-mobile">Mobile</label>
                            <input type="tel" id="emp-mobile" name="mobile" class="pi-input" autocomplete="tel">
                        </div>
                    </div>
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="emp-role">Role *</label>
                            <select id="emp-role" name="role" required class="pi-select" autocomplete="organization-title">
                                <option value="">Select Role</option>
                                <option value="Site Manager">Site Manager</option>
                                <option value="Foreman">Foreman</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Skilled Worker">Skilled Worker</option>
                                <option value="Labourer">Labourer</option>
                                <option value="Subcontractor">Subcontractor</option>
                            </select>
                        </div>
                        <div class="pi-form-group">
                            <label for="emp-trade">Trade</label>
                            <select id="emp-trade" name="trade" class="pi-select" autocomplete="off">
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
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="emp-rate">Hourly Rate</label>
                            <input type="number" id="emp-rate" name="hourly_rate" step="0.01" min="0" class="pi-input" placeholder="0.00" autocomplete="off">
                        </div>
                        <div class="pi-form-group">
                            <label for="emp-cost-code">Default Cost Code</label>
                            <select id="emp-cost-code" name="default_cost_code" class="pi-select" autocomplete="off">
                                <option value="">None</option>
                                ${(State.costCodes || []).map(c => `<option value="${c.code}">${c.code} - ${c.description}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                </form>
            `;
            
            const footer = `
                <button class="pi-team-btn pi-team-btn-secondary" onclick="TeamApp.closeModal()">Cancel</button>
                <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.saveEmployee()">Create Employee</button>
            `;
            
            Utils.showModal('Add New Employee', content, { footer });
        },
        
        editEmployee(employee) {
            // Helper functions
            const isSelected = (value, target) => value == target ? 'selected' : '';
            const isChecked = (value, target) => value === target ? 'checked' : '';
            const escapeHtml = (text) => {
                if (!text) return '';
                return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            };
            
            const content = `
                <form id="edit-employee-form" class="pi-form" data-id="${employee.id}">
                    <!-- Employee Header -->
                    <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: #f8fafc; border-radius: 8px; margin-bottom: 20px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #156349, #0d4a35); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: 600;">
                            ${employee.first_name?.charAt(0) || ''}${employee.last_name?.charAt(0) || ''}
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 16px; font-weight: 600; color: #1e293b;">${escapeHtml(employee.first_name)} ${escapeHtml(employee.last_name)}</div>
                            <div style="font-size: 13px; color: #64748b; margin-top: 2px;">
                                ${employee.employee_code ? `ID: ${escapeHtml(employee.employee_code)} • ` : ''}${employee.role || 'No role'}
                            </div>
                        </div>
                        <span class="pi-status-badge pi-status-${employee.status || 'active'}">${employee.status === 'active' ? 'Active' : 'Inactive'}</span>
                    </div>
                    
                    <!-- Tabs -->
                    <div class="pi-tabs" style="margin-bottom: 20px;">
                        <div class="pi-tab-headers" style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px;">
                            <button type="button" class="pi-tab-header active" data-tab="personal" style="padding: 10px 16px; background: transparent; border: none; border-bottom: 2px solid #156349; margin-bottom: -2px; font-size: 14px; font-weight: 500; color: #156349; cursor: pointer;">Personal Info</button>
                            <button type="button" class="pi-tab-header" data-tab="work" style="padding: 10px 16px; background: transparent; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 14px; font-weight: 500; color: #64748b; cursor: pointer;">Work Details</button>
                            <button type="button" class="pi-tab-header" data-tab="permissions" style="padding: 10px 16px; background: transparent; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 14px; font-weight: 500; color: #64748b; cursor: pointer;">Permissions</button>
                        </div>
                        
                        <!-- Personal Info Tab -->
                        <div class="pi-tab-content active" id="pi-tab-personal">
                            <div class="pi-form-grid">
                                <div class="pi-form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" value="${escapeHtml(employee.first_name)}" class="pi-input" required>
                                </div>
                                <div class="pi-form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" value="${escapeHtml(employee.last_name)}" class="pi-input" required>
                                </div>
                            </div>
                            <div class="pi-form-group">
                                <label>Email *</label>
                                <input type="email" name="email" value="${escapeHtml(employee.email)}" class="pi-input" required>
                            </div>
                            <div class="pi-form-grid">
                                <div class="pi-form-group">
                                    <label>Phone</label>
                                    <input type="tel" name="phone" value="${escapeHtml(employee.phone || '')}" class="pi-input">
                                </div>
                                <div class="pi-form-group">
                                    <label>Mobile</label>
                                    <input type="tel" name="mobile" value="${escapeHtml(employee.mobile || '')}" class="pi-input">
                                </div>
                            </div>
                            <div class="pi-form-group">
                                <label>Status *</label>
                                <select name="status" class="pi-select" required>
                                    <option value="active" ${isSelected(employee.status, 'active')}>Active</option>
                                    <option value="inactive" ${isSelected(employee.status, 'inactive')}>Inactive</option>
                                    <option value="on_leave" ${isSelected(employee.status, 'on_leave')}>On Leave</option>
                                    <option value="terminated" ${isSelected(employee.status, 'terminated')}>Terminated</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Work Details Tab -->
                        <div class="pi-tab-content" id="pi-tab-work" style="display: none;">
                            <div class="pi-form-grid">
                                <div class="pi-form-group">
                                    <label>Role *</label>
                                    <select name="role" class="pi-select" required>
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
                                <div class="pi-form-group">
                                    <label>Trade</label>
                                    <select name="trade" class="pi-select">
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
                            <div class="pi-form-grid">
                                <div class="pi-form-group">
                                    <label>Skill Level</label>
                                    <select name="skill_level" class="pi-select">
                                        <option value="">Select Level</option>
                                        <option value="apprentice" ${isSelected(employee.skill_level, 'apprentice')}>Apprentice</option>
                                        <option value="journeyman" ${isSelected(employee.skill_level, 'journeyman')}>Journeyman</option>
                                        <option value="master" ${isSelected(employee.skill_level, 'master')}>Master</option>
                                        <option value="helper" ${isSelected(employee.skill_level, 'helper')}>Helper</option>
                                    </select>
                                </div>
                                <div class="pi-form-group">
                                    <label>Employment Type</label>
                                    <select name="employment_type" class="pi-select">
                                        <option value="">Select Type</option>
                                        <option value="full_time" ${isSelected(employee.employment_type, 'full_time')}>Full Time</option>
                                        <option value="part_time" ${isSelected(employee.employment_type, 'part_time')}>Part Time</option>
                                        <option value="contract" ${isSelected(employee.employment_type, 'contract')}>Contract</option>
                                        <option value="subcontractor" ${isSelected(employee.employment_type, 'subcontractor')}>Subcontractor</option>
                                        <option value="temporary" ${isSelected(employee.employment_type, 'temporary')}>Temporary</option>
                                    </select>
                                </div>
                            </div>
                            <div class="pi-form-grid">
                                <div class="pi-form-group">
                                    <label>Department</label>
                                    <input type="text" name="department" value="${escapeHtml(employee.department || '')}" class="pi-input" placeholder="e.g., Field Operations">
                                </div>
                                <div class="pi-form-group">
                                    <label>Hourly Rate (£)</label>
                                    <input type="number" name="hourly_rate" step="0.01" min="0" value="${employee.hourly_rate || ''}" class="pi-input" placeholder="0.00">
                                </div>
                            </div>
                            <div class="pi-form-group">
                                <label>Default Cost Code</label>
                                <select name="default_cost_code" class="pi-select">
                                    <option value="">Select Cost Code</option>
                                    ${(State.costCodes || []).map(c => `<option value="${escapeHtml(c.code)}" ${isSelected(employee.default_cost_code, c.code)}>${escapeHtml(c.code)} - ${escapeHtml(c.description)}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                        
                        <!-- Permissions Tab -->
                        <div class="pi-tab-content" id="pi-tab-permissions" style="display: none;">
                            <div class="pi-form-group">
                                <label style="margin-bottom: 12px; display: block;">Permissions Level</label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 6px; cursor: pointer;">
                                        <input type="radio" name="permissions_level" value="field_worker" ${isChecked(employee.permissions_level, 'field_worker') || isChecked(employee.permissions_level, '') || isChecked(employee.permissions_level, null)}>
                                        <span><strong>Field Worker</strong> - Can log time, view own records</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 6px; cursor: pointer;">
                                        <input type="radio" name="permissions_level" value="foreman" ${isChecked(employee.permissions_level, 'foreman')}>
                                        <span><strong>Foreman</strong> - Can approve crew timesheets</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 6px; cursor: pointer;">
                                        <input type="radio" name="permissions_level" value="supervisor" ${isChecked(employee.permissions_level, 'supervisor')}>
                                        <span><strong>Supervisor</strong> - Can approve all timesheets, manage crew</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 6px; cursor: pointer;">
                                        <input type="radio" name="permissions_level" value="admin" ${isChecked(employee.permissions_level, 'admin')}>
                                        <span><strong>Admin</strong> - Full access to all features</span>
                                    </label>
                                </div>
                            </div>
                            <div class="pi-form-group" style="margin-top: 20px;">
                                <label style="margin-bottom: 12px; display: block;">Approval Permissions</label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 6px; cursor: pointer;">
                                        <input type="checkbox" name="can_approve_timesheets" value="1" ${employee.can_approve_timesheets == 1 ? 'checked' : ''}>
                                        <span>Can approve timesheets</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 6px; cursor: pointer;">
                                        <input type="checkbox" name="can_approve_expenses" value="1" ${employee.can_approve_expenses == 1 ? 'checked' : ''}>
                                        <span>Can approve expenses</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            `;
            
            const footer = `
                <button class="pi-team-btn pi-team-btn-danger" onclick="TeamApp.deleteEmployee(${employee.id})">Delete</button>
                <button class="pi-team-btn pi-team-btn-secondary" onclick="TeamApp.closeModal()">Cancel</button>
                <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.updateEmployee()">Save Changes</button>
            `;
            
            Utils.showModal('Edit Employee', content, { footer });
            
            // Bind tab switching after modal is shown
            setTimeout(() => {
                document.querySelectorAll('.pi-tab-header').forEach(header => {
                    header.addEventListener('click', () => {
                        const tab = header.dataset.tab;
                        
                        // Update headers
                        document.querySelectorAll('.pi-tab-header').forEach(h => {
                            h.classList.remove('active');
                            h.style.color = '#64748b';
                            h.style.borderBottomColor = 'transparent';
                        });
                        header.classList.add('active');
                        header.style.color = '#156349';
                        header.style.borderBottomColor = '#156349';
                        
                        // Show content
                        document.querySelectorAll('.pi-tab-content').forEach(c => c.style.display = 'none');
                        document.getElementById(`pi-tab-${tab}`).style.display = 'block';
                    });
                });
            }, 10);
        },
        
        onEmployeeSelect(select) {
            const selectedOption = select.options[select.selectedIndex];
            const rate = selectedOption.dataset.rate;
            const rateInput = document.getElementById('ts-rate');
            if (rateInput && rate) {
                rateInput.value = rate;
                console.log('[Modals] Auto-filled hourly rate:', rate);
            }
        },
        
        addTimesheet() {
            console.log('[Modals] addTimesheet() rendering modal');
            const today = new Date().toISOString().split('T')[0];
            
            const content = `
                <form id="add-timesheet-form" class="pi-form" autocomplete="off">
                    <div class="pi-form-group">
                        <label for="ts-employee">Employee *</label>
                        <select id="ts-employee" name="employee_id" required class="pi-select" autocomplete="off" onchange="Modals.onEmployeeSelect(this)">
                            <option value="">Select Employee</option>
                            ${(State.employees || []).map(e => `<option value="${e.id}" data-rate="${e.hourly_rate || ''}">${e.first_name} ${e.last_name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="ts-job">Job (Optional)</label>
                            <select id="ts-job" name="job_id" class="pi-select" autocomplete="off">
                                <option value="">Global Entry (No Job)</option>
                                ${(State.jobs || []).map(j => `<option value="${j.id}">${j.title}</option>`).join('')}
                            </select>
                        </div>
                        <div class="pi-form-group">
                            <label for="ts-date">Date *</label>
                            <input type="date" id="ts-date" name="work_date" required class="pi-input" value="${today}" autocomplete="off">
                        </div>
                    </div>
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="ts-start">Start Time *</label>
                            <input type="time" id="ts-start" name="start_time" required class="pi-input" value="09:00" autocomplete="off">
                        </div>
                        <div class="pi-form-group">
                            <label for="ts-end">End Time *</label>
                            <input type="time" id="ts-end" name="end_time" required class="pi-input" value="17:00" autocomplete="off">
                        </div>
                        <div class="pi-form-group">
                            <label for="ts-break">Break (min)</label>
                            <input type="number" id="ts-break" name="break_duration" class="pi-input" value="60" min="0" step="5" autocomplete="off">
                        </div>
                    </div>
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="ts-cost-code">Cost Code</label>
                            <select id="ts-cost-code" name="cost_code" class="pi-select" autocomplete="off">
                                <option value="">Select Cost Code</option>
                                ${(State.costCodes || []).map(c => `<option value="${c.code}">${c.code} - ${c.description}</option>`).join('')}
                            </select>
                        </div>
                        <div class="pi-form-group">
                            <label for="ts-rate">Hourly Rate (£)</label>
                            <input type="number" id="ts-rate" name="hourly_rate" step="0.01" min="0" class="pi-input" placeholder="Select employee first" autocomplete="off">
                        </div>
                    </div>
                    <div class="pi-form-group">
                        <label for="ts-description">Task Description</label>
                        <textarea id="ts-description" name="task_description" class="pi-textarea" rows="3" placeholder="Describe the work performed..." autocomplete="off"></textarea>
                    </div>
                </form>
            `;
            
            const footer = `
                <button class="pi-team-btn pi-team-btn-secondary" onclick="TeamApp.closeModal()">Cancel</button>
                <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.saveTimesheet()">Add Entry</button>
            `;
            
            Utils.showModal('Add Timesheet Entry', content, { footer });
        },
        
        editTimesheet(timesheet) {
            const content = `
                <form id="edit-timesheet-form" class="pi-form" data-id="${timesheet.id}" autocomplete="off">
                    <div class="pi-form-group">
                        <label for="edit-ts-employee">Employee</label>
                        <input type="text" id="edit-ts-employee" class="pi-input" value="${timesheet.first_name} ${timesheet.last_name}" disabled>
                    </div>
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="edit-ts-date">Date</label>
                            <input type="date" id="edit-ts-date" name="work_date" class="pi-input" value="${timesheet.work_date}" disabled>
                        </div>
                        <div class="pi-form-group">
                            <label for="edit-ts-status">Status</label>
                            <select id="edit-ts-status" name="status" class="pi-select" autocomplete="off">
                                <option value="pending" ${timesheet.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="approved" ${timesheet.status === 'approved' ? 'selected' : ''}>Approved</option>
                                <option value="rejected" ${timesheet.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="pi-form-grid">
                        <div class="pi-form-group">
                            <label for="edit-ts-start">Start Time</label>
                            <input type="time" id="edit-ts-start" name="start_time" class="pi-input" value="${timesheet.start_time}" autocomplete="off">
                        </div>
                        <div class="pi-form-group">
                            <label for="edit-ts-end">End Time</label>
                            <input type="time" id="edit-ts-end" name="end_time" class="pi-input" value="${timesheet.end_time}" autocomplete="off">
                        </div>
                        <div class="pi-form-group">
                            <label for="edit-ts-break">Break (min)</label>
                            <input type="number" id="edit-ts-break" name="break_duration" class="pi-input" value="${timesheet.break_duration}" min="0" step="5" autocomplete="off">
                        </div>
                    </div>
                    <div class="pi-form-group">
                        <label for="edit-ts-notes">Notes</label>
                        <textarea id="edit-ts-notes" name="notes" class="pi-textarea" rows="3" autocomplete="off">${timesheet.notes || ''}</textarea>
                    </div>
                </form>
            `;
            
            const footer = `
                <button class="pi-team-btn pi-team-btn-danger" onclick="TeamApp.deleteTimesheet(${timesheet.id})">Delete</button>
                <button class="pi-team-btn pi-team-btn-secondary" onclick="TeamApp.closeModal()">Cancel</button>
                <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.updateTimesheet()">Save Changes</button>
            `;
            
            Utils.showModal('Edit Timesheet Entry', content, { footer });
        },
        
        addCrew() {
            console.log('[Modals] addCrew() rendering modal');
            const content = `
                <form id="add-crew-form" class="pi-form" autocomplete="off">
                    <div class="pi-form-group">
                        <label for="crew-name">Crew Name *</label>
                        <input type="text" id="crew-name" name="crew_name" required class="pi-input" autocomplete="off">
                    </div>
                    <div class="pi-form-group">
                        <label for="crew-trade">Trade Specialty</label>
                        <select id="crew-trade" name="trade_specialty" class="pi-select" autocomplete="off">
                            <option value="">General</option>
                            <option value="Carpentry">Carpentry</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Masonry">Masonry</option>
                            <option value="HVAC">HVAC</option>
                            <option value="Painting">Painting</option>
                            <option value="Roofing">Roofing</option>
                        </select>
                    </div>
                    <div class="pi-form-group">
                        <label for="crew-foreman">Foreman</label>
                        <select id="crew-foreman" name="foreman_id" class="pi-select" autocomplete="off">
                            <option value="">Select Foreman</option>
                            ${(State.employees && State.employees.length > 0) ? State.employees.filter(e => e.status === 'active').map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`).join('') : ''}
                        </select>
                    </div>
                    <div class="pi-form-group">
                        <label for="crew-description">Description</label>
                        <textarea id="crew-description" name="description" class="pi-textarea" rows="3" autocomplete="off"></textarea>
                    </div>
                    <div class="pi-form-group">
                        <label for="crew-members">Members</label>
                        <select id="crew-members" name="members[]" multiple class="pi-select pi-select-multiple" size="5" autocomplete="off">
                            ${(State.employees || []).filter(e => e.status === 'active').map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`).join('')}
                        </select>
                        <small>Hold Ctrl/Cmd to select multiple</small>
                    </div>
                </form>
            `;
            
            const footer = `
                <button class="pi-team-btn pi-team-btn-secondary" onclick="TeamApp.closeModal()">Cancel</button>
                <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.saveCrew()">Create Crew</button>
            `;
            
            Utils.showModal('Create New Crew', content, { footer });
        },
        
        editCrew(crew) {
            const memberIds = (crew.members || []).map(m => m.employee_id);
            
            const content = `
                <form id="edit-crew-form" class="pi-form" autocomplete="off" data-crew-id="${crew.id}">
                    <div class="pi-form-group">
                        <label for="edit-crew-name">Crew Name *</label>
                        <input type="text" id="edit-crew-name" name="crew_name" required class="pi-input" 
                               value="${crew.crew_name || ''}" autocomplete="off">
                    </div>
                    <div class="pi-form-group">
                        <label for="edit-crew-trade">Trade Specialty</label>
                        <select id="edit-crew-trade" name="trade_specialty" class="pi-select" autocomplete="off">
                            <option value="" ${!crew.trade_specialty ? 'selected' : ''}>General</option>
                            <option value="Carpentry" ${crew.trade_specialty === 'Carpentry' ? 'selected' : ''}>Carpentry</option>
                            <option value="Electrical" ${crew.trade_specialty === 'Electrical' ? 'selected' : ''}>Electrical</option>
                            <option value="Plumbing" ${crew.trade_specialty === 'Plumbing' ? 'selected' : ''}>Plumbing</option>
                            <option value="Masonry" ${crew.trade_specialty === 'Masonry' ? 'selected' : ''}>Masonry</option>
                            <option value="HVAC" ${crew.trade_specialty === 'HVAC' ? 'selected' : ''}>HVAC</option>
                            <option value="Painting" ${crew.trade_specialty === 'Painting' ? 'selected' : ''}>Painting</option>
                            <option value="Roofing" ${crew.trade_specialty === 'Roofing' ? 'selected' : ''}>Roofing</option>
                        </select>
                    </div>
                    <div class="pi-form-group">
                        <label for="edit-crew-foreman">Foreman</label>
                        <select id="edit-crew-foreman" name="foreman_id" class="pi-select" autocomplete="off">
                            <option value="">Select Foreman</option>
                            ${(State.employees && State.employees.length > 0) ? State.employees.filter(e => e.status === 'active').map(e => 
                                `<option value="${e.id}" ${crew.foreman_id == e.id ? 'selected' : ''}>${e.first_name} ${e.last_name}</option>`
                            ).join('') : ''}
                        </select>
                    </div>
                    <div class="pi-form-group">
                        <label for="edit-crew-description">Description</label>
                        <textarea id="edit-crew-description" name="description" class="pi-textarea" rows="3" autocomplete="off">${crew.description || ''}</textarea>
                    </div>
                    <div class="pi-form-group">
                        <label for="edit-crew-members">Members</label>
                        <select id="edit-crew-members" name="members[]" multiple class="pi-select pi-select-multiple" size="5" autocomplete="off">
                            ${(State.employees || []).filter(e => e.status === 'active').map(e => 
                                `<option value="${e.id}" ${memberIds.includes(e.id) ? 'selected' : ''}>${e.first_name} ${e.last_name}</option>`
                            ).join('')}
                        </select>
                        <small>Hold Ctrl/Cmd to select multiple</small>
                    </div>
                </form>
            `;
            
            const footer = `
                <button class="pi-team-btn pi-team-btn-secondary" onclick="TeamApp.closeModal()">Cancel</button>
                <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.updateCrew()">Save Changes</button>
            `;
            
            Utils.showModal('Edit Crew', content, { footer });
        },
        
        viewCrew(crew) {
            const members = crew.members || [];
            const membersHtml = members.length > 0 
                ? members.map(m => `
                    <div class="pi-crew-member-item">
                        <div class="pi-crew-member-name">${m.first_name || ''} ${m.last_name || ''}</div>
                        <div class="pi-crew-member-role">${m.role_in_crew || 'Member'}${m.trade ? ` • ${m.trade}` : ''}</div>
                    </div>
                `).join('')
                : '<p class="pi-text-muted">No members in this crew</p>';
            
            const content = `
                <div class="pi-crew-detail">
                    <div class="pi-crew-detail-header" style="border-left-color: ${crew.color_code || '#4F46E5'}">
                        <h3>${crew.crew_name}</h3>
                        <span class="pi-crew-detail-trade">${crew.trade_specialty || 'General'}</span>
                    </div>
                    ${crew.description ? `<p class="pi-crew-detail-description">${crew.description}</p>` : ''}
                    ${crew.foreman_first ? `
                    <div class="pi-crew-detail-foreman">
                        <i data-feather="user-check"></i>
                        <span>Foreman: ${crew.foreman_first} ${crew.foreman_last}</span>
                    </div>
                    ` : ''}
                    <div class="pi-crew-detail-stats">
                        <div class="pi-stat">
                            <span class="pi-stat-value">${members.length}</span>
                            <span class="pi-stat-label">Members</span>
                        </div>
                    </div>
                    <div class="pi-crew-detail-members">
                        <h4>Members</h4>
                        <div class="pi-crew-members-list">
                            ${membersHtml}
                        </div>
                    </div>
                </div>
            `;
            
            const footer = `
                <button class="pi-team-btn pi-team-btn-secondary" onclick="TeamApp.closeModal()">Close</button>
                <button class="pi-team-btn pi-team-btn-primary" onclick="TeamApp.editCrew(${crew.id}); TeamApp.closeModal();">Edit Crew</button>
            `;
            
            Utils.showModal('Crew Details', content, { footer });
        }
    };
    
    window.Modals = Modals;
    
    window.TeamApp = {
        init() {
            console.log('[TeamApp] Initializing...');
            UI.init();
            console.log('[TeamApp] Initialization complete');
        },
        
        switchTab(tab) {
            State.currentTab = tab;
            
            // Update nav
            $('.pi-team-nav-item').removeClass('active');
            $(`.pi-team-nav-item[data-tab="${tab}"]`).addClass('active');
            
            // Update content
            $('.pi-team-tab-content').removeClass('active');
            $(`#tab-${tab}`).addClass('active');
            
            // Load tab-specific data
            switch(tab) {
                case 'overview':
                    UI.loadDashboardData();
                    break;
                case 'employees':
                    UI.loadEmployees();
                    break;
                case 'timesheets':
                    UI.loadTimesheets();
                    break;
                case 'crews':
                    UI.loadCrews();
                    break;
            }
        },
        
        closeModal() {
            Utils.closeModal();
        },
        
        // Employee actions
        openAddEmployeeModal() {
            console.log('[TeamApp] openAddEmployeeModal() called');
            try {
                Modals.addEmployee();
            } catch (error) {
                console.error('[TeamApp] Error opening add employee modal:', error);
                Utils.showNotification('Error opening modal. Please refresh the page.', 'error');
            }
        },
        
        async saveEmployee() {
            const form = document.getElementById('add-employee-form');
            const data = Object.fromEntries(new FormData(form));
            
            try {
                await TeamAPI.createEmployee(data);
                Utils.showNotification('Employee created successfully');
                this.closeModal();
                UI.loadEmployees();
            } catch (error) {
                Utils.showNotification(error.message || 'Failed to create employee', 'error');
            }
        },
        
        async editEmployee(id) {
            console.log('[TeamApp] editEmployee() called for id:', id);
            const employee = State.employees.find(e => e.id == id);
            if (employee) {
                try {
                    Modals.editEmployee(employee);
                } catch (error) {
                    console.error('[TeamApp] Error opening edit employee modal:', error);
                    Utils.showNotification('Error opening edit modal. Please refresh the page.', 'error');
                }
            } else {
                console.error('[TeamApp] Employee not found for id:', id);
                Utils.showNotification('Employee not found. Please refresh the page.', 'error');
            }
        },
        
        async updateEmployee() {
            const form = document.getElementById('edit-employee-form');
            const id = form.dataset.id;
            
            // Get all form data
            const data = Object.fromEntries(new FormData(form));
            
            // Handle checkboxes (unchecked checkboxes don't appear in FormData)
            data.can_approve_timesheets = form.querySelector('[name="can_approve_timesheets"]')?.checked ? 1 : 0;
            data.can_approve_expenses = form.querySelector('[name="can_approve_expenses"]')?.checked ? 1 : 0;
            
            // Convert hourly_rate to number if present
            if (data.hourly_rate) {
                data.hourly_rate = parseFloat(data.hourly_rate);
            }
            
            // Remove empty strings for optional fields to prevent overwriting with empty values
            Object.keys(data).forEach(key => {
                if (data[key] === '') {
                    delete data[key];
                }
            });
            
            try {
                await TeamAPI.updateEmployee(id, data);
                Utils.showNotification('Employee updated successfully');
                this.closeModal();
                UI.loadEmployees();
            } catch (error) {
                Utils.showNotification(error.message || 'Failed to update employee', 'error');
            }
        },
        
        async deleteEmployee(id) {
            const employee = State.employees.find(e => e.id == id);
            const name = employee ? `${employee.first_name} ${employee.last_name}` : 'this employee';
            
            if (!confirm(`WARNING: This will permanently DELETE ${name} from the system.\n\nThis action will:\n- Remove them from ALL job assignments\n- Remove them from all crews\n- Keep timesheet records but anonymized\n\nThis cannot be undone. Are you sure?`)) return;
            
            try {
                await TeamAPI.deleteEmployee(id);
                Utils.showNotification(`${name} deleted and removed from all jobs`);
                
                // Close modal if open
                const modal = document.getElementById('pi-team-modal');
                if (modal && modal.style.display === 'block') {
                    this.closeModal();
                }
                
                UI.loadEmployees();
                UI.loadDashboardData(); // Refresh stats
            } catch (error) {
                console.error('Delete employee error:', error);
                Utils.showNotification(error.message || 'Failed to delete employee', 'error');
            }
        },
        
        async viewEmployeeStats(id) {
            try {
                const stats = await TeamAPI.getEmployeeStats(id);
                const employee = State.employees.find(e => e.id == id);
                
                const weekOt = parseFloat(stats.week_overtime_hours || 0);
                const monthOt = parseFloat(stats.month_overtime_hours || 0);
                const weekTotal = parseFloat(stats.week_hours || 0);
                const monthTotal = parseFloat(stats.month_hours || 0);
                
                const content = `
                    <div class="pi-employee-stats">
                        <div class="pi-stat-row">
                            <span>Hours This Week</span>
                            <strong>${weekTotal.toFixed(1)}${weekOt > 0 ? ` <span style="color: var(--pi-warning); font-size: 11px;">(+${weekOt.toFixed(1)} OT)</span>` : ''}</strong>
                        </div>
                        <div class="pi-stat-row">
                            <span>Hours This Month</span>
                            <strong>${monthTotal.toFixed(1)}${monthOt > 0 ? ` <span style="color: var(--pi-warning); font-size: 11px;">(+${monthOt.toFixed(1)} OT)</span>` : ''}</strong>
                        </div>
                        <div class="pi-stat-row">
                            <span>Total Timesheet Entries</span>
                            <strong>${stats.total_entries}</strong>
                        </div>
                    </div>
                `;
                
                Utils.showModal(`Stats: ${employee.first_name} ${employee.last_name}`, content, {});
            } catch (error) {
                Utils.showNotification('Failed to load stats', 'error');
            }
        },
        
        filterEmployees() {
            const search = $('#employee-search').val().toLowerCase();
            const role = $('#employee-role-filter').val();
            const trade = $('#employee-trade-filter').val();
            const status = $('#employee-status-filter').val();
            
            const filtered = State.employees.filter(e => {
                const matchSearch = !search || 
                    e.first_name.toLowerCase().includes(search) ||
                    e.last_name.toLowerCase().includes(search) ||
                    e.email.toLowerCase().includes(search);
                const matchRole = !role || e.role === role;
                const matchTrade = !trade || e.trade === trade;
                const matchStatus = !status || e.status === status;
                
                return matchSearch && matchRole && matchTrade && matchStatus;
            });
            
            // Temporarily update for rendering
            const originalEmployees = State.employees;
            State.employees = filtered;
            UI.renderEmployees();
            State.employees = originalEmployees;
        },
        
        updateSelectedEmployees() {
            State.selectedItems.employees = $('.employee-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
        },
        
        exportEmployees() {
            const csv = [
                ['Employee Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Trade', 'Hourly Rate', 'Status'].join(','),
                ...State.employees.map(e => [
                    e.employee_code,
                    e.first_name,
                    e.last_name,
                    e.email,
                    e.phone,
                    e.role,
                    e.trade,
                    e.hourly_rate,
                    e.status
                ].join(','))
            ].join('\n');
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'employees.csv';
            a.click();
            URL.revokeObjectURL(url);
        },
        
        // Timesheet actions
        openAddTimesheetModal() {
            console.log('[TeamApp] openAddTimesheetModal() called');
            try {
                Modals.addTimesheet();
            } catch (error) {
                console.error('[TeamApp] Error opening add timesheet modal:', error);
                Utils.showNotification('Error opening modal. Please refresh the page.', 'error');
            }
        },
        
        async saveTimesheet() {
            console.log('[TeamApp] saveTimesheet() called');
            const form = document.getElementById('add-timesheet-form');
            if (!form) {
                console.error('[TeamApp] Form not found: add-timesheet-form');
                Utils.showNotification('Error: Form not found. Please refresh and try again.', 'error');
                return;
            }
            
            const data = Object.fromEntries(new FormData(form));
            console.log('[TeamApp] Form data collected:', data);
            
            // Validation
            if (!data.employee_id) {
                Utils.showNotification('Please select an employee', 'error');
                return;
            }
            if (!data.work_date) {
                Utils.showNotification('Please select a work date', 'error');
                return;
            }
            if (!data.start_time || !data.end_time) {
                Utils.showNotification('Please enter start and end times', 'error');
                return;
            }
            
            // Convert empty job_id to null
            if (!data.job_id) data.job_id = null;
            
            // Convert numeric fields
            if (data.hourly_rate) {
                data.hourly_rate = parseFloat(data.hourly_rate);
            }
            if (data.break_duration) {
                data.break_duration = parseInt(data.break_duration, 10);
            }
            
            console.log('[TeamApp] Sending timesheet data:', data);
            
            try {
                const result = await TeamAPI.createTimesheet(data);
                console.log('[TeamApp] Timesheet created successfully with ID:', result?.id);
                
                // CRITICAL: Clear the form to prevent data leakage
                const form = document.getElementById('add-timesheet-form');
                if (form) {
                    form.reset();
                    console.log('[TeamApp] Form reset after successful save');
                }
                
                // Small delay to ensure database consistency
                await new Promise(resolve => setTimeout(resolve, 500));
                
                Utils.showNotification('Timesheet entry added successfully');
                this.closeModal();
                
                console.log('[TeamApp] Reloading timesheets after create...');
                
                // CRITICAL: Clear State.timesheets first to prevent display of stale data
                State.timesheets = [];
                UI.renderTimesheets();
                
                // Force complete reload
                if (State.currentTab === 'timesheets') {
                    await UI.loadTimesheets();
                }
                await UI.loadDashboardData();
                
                console.log('[TeamApp] Timesheet save workflow complete');
                
                // NUCLEAR OPTION: If issues persist, uncomment the line below to force full page reload
                // window.location.reload();
            } catch (error) {
                console.error('[TeamApp] Failed to create timesheet:', error);
                Utils.showNotification(error.message || 'Failed to add timesheet entry', 'error');
            }
        },
        
        async editTimesheet(id) {
            console.log('[TeamApp] editTimesheet() called for id:', id);
            const timesheet = State.timesheets.find(t => t.id == id);
            if (timesheet) {
                try {
                    Modals.editTimesheet(timesheet);
                } catch (error) {
                    console.error('[TeamApp] Error opening edit timesheet modal:', error);
                    Utils.showNotification('Error opening edit modal. Please refresh the page.', 'error');
                }
            } else {
                console.error('[TeamApp] Timesheet not found for id:', id);
                Utils.showNotification('Timesheet not found. Please refresh the page.', 'error');
            }
        },
        
        async updateTimesheet() {
            console.log('[TeamApp] updateTimesheet() called');
            const form = document.getElementById('edit-timesheet-form');
            if (!form) {
                console.error('[TeamApp] Form not found: edit-timesheet-form');
                Utils.showNotification('Error: Form not found. Please refresh and try again.', 'error');
                return;
            }
            
            const id = form.dataset.id;
            if (!id) {
                console.error('[TeamApp] Timesheet ID not found in form dataset');
                Utils.showNotification('Error: Timesheet ID not found.', 'error');
                return;
            }
            
            const data = Object.fromEntries(new FormData(form));
            console.log('[TeamApp] Update form data:', data);
            
            // Convert numeric fields
            if (data.hourly_rate) {
                data.hourly_rate = parseFloat(data.hourly_rate);
            }
            if (data.break_duration) {
                data.break_duration = parseInt(data.break_duration, 10);
            }
            
            try {
                const result = await TeamAPI.updateTimesheet(id, data);
                console.log('[TeamApp] Timesheet updated successfully:', result);
                Utils.showNotification('Timesheet updated successfully');
                this.closeModal();
                UI.loadTimesheets();
                UI.loadDashboardData();
            } catch (error) {
                console.error('[TeamApp] Failed to update timesheet:', error);
                Utils.showNotification(error.message || 'Failed to update timesheet', 'error');
            }
        },
        
        async deleteTimesheet(id) {
            if (!confirm('Are you sure you want to delete this timesheet entry?')) return;
            
            try {
                await TeamAPI.deleteTimesheet(id);
                Utils.showNotification('Timesheet deleted');
                this.closeModal();
                UI.loadTimesheets();
                UI.loadDashboardData();
            } catch (error) {
                Utils.showNotification(error.message || 'Failed to delete timesheet', 'error');
            }
        },
        
        filterTimesheets() {
            UI.loadTimesheets();
        },
        
        updateSelectedTimesheets() {
            State.selectedItems.timesheets = $('.timesheet-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
        },
        
        exportTimesheets() {
            const csv = [
                ['Date', 'Employee', 'Job', 'Start Time', 'End Time', 'Total Hours', 'Overtime', 'Cost Code', 'Status'].join(','),
                ...State.timesheets.map(t => [
                    t.work_date,
                    `${t.first_name} ${t.last_name}`,
                    t.job_id || 'Global',
                    t.start_time,
                    t.end_time,
                    t.total_hours,
                    t.overtime_hours,
                    t.cost_code,
                    t.status
                ].join(','))
            ].join('\n');
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'timesheets.csv';
            a.click();
            URL.revokeObjectURL(url);
        },
        
        // Crew actions
        openAddCrewModal() {
            console.log('[TeamApp] openAddCrewModal() called');
            try {
                Modals.addCrew();
            } catch (error) {
                console.error('[TeamApp] Error opening add crew modal:', error);
                Utils.showNotification('Error opening modal. Please refresh the page.', 'error');
            }
        },
        
        async saveCrew() {
            const form = document.getElementById('add-crew-form');
            const data = Object.fromEntries(new FormData(form));
            
            // Handle multiple select
            const membersSelect = form.querySelector('[name="members[]"]');
            data.members = Array.from(membersSelect.selectedOptions).map(opt => opt.value);
            
            try {
                await TeamAPI.createCrew(data);
                Utils.showNotification('Crew created successfully');
                this.closeModal();
                UI.loadCrews();
            } catch (error) {
                Utils.showNotification(error.message || 'Failed to create crew', 'error');
            }
        },
        
        async editCrew(id) {
            const crew = State.crews.find(c => c.id === id);
            if (!crew) {
                Utils.showNotification('Crew not found', 'error');
                return;
            }
            
            // Get full crew details with members
            try {
                const fullCrew = await TeamAPI.getCrew(id);
                Modals.editCrew(fullCrew);
            } catch (error) {
                Utils.showNotification('Failed to load crew details', 'error');
            }
        },
        
        async updateCrew() {
            const form = document.getElementById('edit-crew-form');
            if (!form) return;
            
            const crewId = form.dataset.crewId;
            const data = Object.fromEntries(new FormData(form));
            
            // Handle multiple select
            const membersSelect = form.querySelector('[name="members[]"]');
            if (membersSelect) {
                data.members = Array.from(membersSelect.selectedOptions).map(opt => ({
                    employee_id: parseInt(opt.value),
                    role: 'member'
                }));
            }
            
            try {
                await TeamAPI.updateCrew(crewId, data);
                Utils.showNotification('Crew updated successfully');
                this.closeModal();
                UI.loadCrews();
            } catch (error) {
                Utils.showNotification(error.message || 'Failed to update crew', 'error');
            }
        },
        
        async viewCrew(id) {
            try {
                const crew = await TeamAPI.getCrew(id);
                Modals.viewCrew(crew);
            } catch (error) {
                Utils.showNotification('Failed to load crew details', 'error');
            }
        },
        
        async deleteCrew(id) {
            if (!confirm('Are you sure you want to delete this crew? This will not remove members from any jobs.')) {
                return;
            }
            
            try {
                await TeamAPI.updateCrew(id, { status: 'deleted' });
                Utils.showNotification('Crew deleted successfully');
                UI.loadCrews();
            } catch (error) {
                Utils.showNotification(error.message || 'Failed to delete crew', 'error');
            }
        },
        
        showCreateCrewModal() {
            this.openAddCrewModal();
        }
    };
    
    // Debounce utility
    Utils.debounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };
    
    // Initialize on DOM ready
    $(document).ready(() => {
        console.log('[TeamTimesheets] DOM ready, checking for containers...');
        const hasWrapper = $('.pi-team-wrapper').length;
        const hasDashboard = $('#teamDashboardV3').length;
        console.log('[TeamTimesheets] pi-team-wrapper found:', hasWrapper);
        console.log('[TeamTimesheets] teamDashboardV3 found:', hasDashboard);
        
        if (hasWrapper || hasDashboard) {
            console.log('[TeamTimesheets] Initializing TeamApp...');
            TeamApp.init();
        } else {
            console.log('[TeamTimesheets] No containers found, skipping initialization');
        }
    });
    
})(jQuery);
