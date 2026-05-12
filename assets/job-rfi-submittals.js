/**
 * RFI & Submittals Module - Comprehensive Construction CRM Dashboard
 * 
 * This module provides full functionality for:
 * - Unified dashboard with metrics, filtering, and unified list view
 * - RFI management: create, view, edit, respond, close, add comments
 * - Submittal management: create, view, edit, review, resubmit, close
 * - Reports and analytics
 * - Bulk actions
 * - Integration hooks for tasks, materials, change orders
 */

(function($) {
    'use strict';

    // ============================================
    // Global State
    // ============================================
    const RFI_Submittals = {
        jobId: null,
        currentTab: 'all',
        currentFilter: 'all',
        currentSort: { field: 'number', order: 'asc' },
        searchQuery: '',
        rfiData: [],
        submittalData: [],
        combinedData: [],
        selectedItems: new Set(),
        metrics: {},
        
        /**
         * Initialize the module
         */
        init: function(jobId) {
            console.log('[RFI_Submittals] INIT called with jobId:', jobId);
            this.jobId = jobId;
            console.log('[RFI_Submittals] State after init:', { jobId: this.jobId, currentTab: this.currentTab });
            this.bindEvents();
            this.loadDashboardData();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Close modals on escape key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeAllModals();
                }
            });

            // Close modals on backdrop click
            $(document).on('click', '.rfi-modal, .rfi-detail-panel-backdrop', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeAllModals();
                }
            });

            // Close filter menu when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.rfi-filter-dropdown').length) {
                    $('#rfi-filter-menu').removeClass('show');
                }
            });
        },

        /**
         * Load dashboard data from API
         */
        loadDashboardData: function() {
            console.log('[RFI_Submittals] loadDashboardData called');
            console.log('[RFI_Submittals] Current state:', { jobId: this.jobId, rfiData: this.rfiData.length, submittalData: this.submittalData.length });
            this.loadRFIs();
            this.loadSubmittals();
            this.loadDashboardMetrics();
        },

        /**
         * Load RFIs from API
         */
        loadRFIs: function() {
            const params = {
                job_id: this.jobId,
                search: this.searchQuery,
                sort_by: this.currentSort.field === 'number' ? 'rfi_number' : this.currentSort.field,
                sort_order: this.currentSort.order
            };

            if (this.currentFilter !== 'all') {
                params.status = this.currentFilter;
            }

            CRM_API.getRFI(params).then((response) => {
                console.log('[RFI_Submittals] RFI response:', response);
                
                // Robust data extraction - handle multiple response formats
                let rfiArray = [];
                if (Array.isArray(response)) {
                    rfiArray = response;
                } else if (response && typeof response === 'object') {
                    if (response.data && Array.isArray(response.data)) {
                        rfiArray = response.data;
                    } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
                        rfiArray = response.data.data;
                    } else if (response.results && Array.isArray(response.results)) {
                        rfiArray = response.results;
                    } else if (response.items && Array.isArray(response.items)) {
                        rfiArray = response.items;
                    }
                }
                
                console.log('[RFI_Submittals] Extracted RFI array:', rfiArray);
                this.rfiData = rfiArray.map(item => ({
                    ...item,
                    item_type: 'rfi',
                    display_number: item.rfi_number || item.id || 'Unknown',
                    display_type: 'RFI',
                    display_date: item.requested_at || item.created_at,
                    display_due: item.due_date,
                    // Ensure required fields have fallback values
                    title: item.title || 'Untitled RFI',
                    status: item.status || 'draft',
                    priority: item.priority || 'normal'
                }));
                console.log('[RFI_Submittals] Mapped rfiData:', this.rfiData);
                console.log('[RFI_Submittals] rfiData length:', this.rfiData.length);
                
                // Capture RFI metrics if available
                if (response && response.metrics) {
                    this.metrics.rfi_metrics = response.metrics;
                }
                this.updateCombinedData();
                this.updateTabBadges();
                this.render();
                this.renderMetrics();
            }).catch((err) => {
                console.error('[RFI_Submittals] RFI load error:', err);
                this.rfiData = [];
                this.updateCombinedData();
                this.render();
            });
        },

        /**
         * Load Submittals from API
         */
        loadSubmittals: function() {
            const params = {
                job_id: this.jobId,
                search: this.searchQuery,
                sort_by: this.currentSort.field === 'number' ? 'submittal_number' : this.currentSort.field,
                sort_order: this.currentSort.order
            };

            if (this.currentFilter !== 'all') {
                params.status = this.currentFilter;
            }

            CRM_API.getSubmittals(params).then((response) => {
                console.log('[RFI_Submittals] Submittal raw response:', response);
                console.log('[RFI_Submittals] Response type:', typeof response);
                console.log('[RFI_Submittals] Response is array:', Array.isArray(response));
                if (response && typeof response === 'object') {
                    console.log('[RFI_Submittals] Response keys:', Object.keys(response));
                    console.log('[RFI_Submittals] response.data:', response.data);
                    console.log('[RFI_Submittals] response.data is array:', Array.isArray(response.data));
                    console.log('[RFI_Submittals] response.metrics:', response.metrics);
                }
                
                // Robust data extraction - handle multiple response formats
                let submittalArray = [];
                if (Array.isArray(response)) {
                    // Response is already an array
                    submittalArray = response;
                    console.log('[RFI_Submittals] Using response directly as array');
                } else if (response && typeof response === 'object') {
                    // Try various common response structures
                    if (response.data && Array.isArray(response.data)) {
                        submittalArray = response.data;
                        console.log('[RFI_Submittals] Extracted from response.data');
                    } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
                        submittalArray = response.data.data;
                        console.log('[RFI_Submittals] Extracted from response.data.data');
                    } else if (response.results && Array.isArray(response.results)) {
                        submittalArray = response.results;
                        console.log('[RFI_Submittals] Extracted from response.results');
                    } else if (response.items && Array.isArray(response.items)) {
                        submittalArray = response.items;
                        console.log('[RFI_Submittals] Extracted from response.items');
                    } else {
                        console.warn('[RFI_Submittals] Could not extract array from response:', response);
                    }
                } else {
                    console.error('[RFI_Submittals] Invalid response format:', response);
                }
                
                console.log('[RFI_Submittals] Extracted Submittal array:', submittalArray);
                this.submittalData = submittalArray.map(item => {
                    // Defensive mapping with fallbacks to prevent undefined values
                    const mapped = {
                        ...item,
                        item_type: 'submittal',
                        display_number: item.submittal_number || item.id || 'Unknown',
                        display_type: 'Submittal',
                        display_date: item.submitted_at || item.created_at,
                        display_due: item.due_date,  // Using actual column name from database
                        // Ensure required fields have fallback values for rendering
                        title: item.title || 'Untitled Submittal',
                        status: item.status || 'draft',
                        priority: item.priority || 'normal'
                    };
                    return mapped;
                });
                console.log('[RFI_Submittals] Mapped submittalData:', this.submittalData);
                console.log('[RFI_Submittals] submittalData length:', this.submittalData.length);
                
                // Capture submittal metrics if available
                if (response && response.metrics) {
                    this.metrics.submittal_metrics = response.metrics;
                }
                this.updateCombinedData();
                this.updateTabBadges();
                this.render();
                this.renderMetrics();
            }).catch((err) => {
                console.error('[RFI_Submittals] Submittal load error:', err);
                this.submittalData = [];
                this.updateCombinedData();
                this.render();
            });
        },

        /**
         * Load dashboard metrics - now handled within loadRFIs/loadSubmittals
         */
        loadDashboardMetrics: function() {
            // Metrics are now captured directly from get_rfi and get_submittals responses
            // This method is kept for backwards compatibility but no longer needs to fetch
            console.log('[RFI_Submittals] Dashboard metrics loaded from individual API calls');
            this.renderMetrics();
            this.updateTabBadges();
        },

        /**
         * Update combined data based on current tab
         */
        updateCombinedData: function() {
            console.log('[RFI_Submittals] updateCombinedData called');
            console.log('[RFI_Submittals] Current state:', { 
                rfiData: this.rfiData?.length || 0, 
                submittalData: this.submittalData?.length || 0, 
                currentTab: this.currentTab 
            });
            let data = [];
            
            switch (this.currentTab) {
                case 'rfi':
                    data = [...(this.rfiData || [])];
                    console.log('[RFI_Submittals] Filtered to RFI only:', data.length);
                    break;
                case 'submittals':
                    data = [...(this.submittalData || [])];
                    console.log('[RFI_Submittals] Filtered to Submittals only:', data.length);
                    break;
                case 'overdue':
                    data = [
                        ...(this.rfiData || []).filter(item => item.urgency_flag === 'overdue'),
                        ...(this.submittalData || []).filter(item => item.urgency_flag === 'overdue')
                    ];
                    console.log('[RFI_Submittals] Filtered to Overdue only:', data.length);
                    break;
                case 'all':
                default:
                    data = [...(this.rfiData || []), ...(this.submittalData || [])];
                    console.log('[RFI_Submittals] Combined all data:', data.length);
                    break;
            }

            // Sort data
            data.sort((a, b) => {
                let valA, valB;
                switch (this.currentSort.field) {
                    case 'number':
                        valA = a.display_number;
                        valB = b.display_number;
                        break;
                    case 'title':
                        valA = a.title;
                        valB = b.title;
                        break;
                    case 'type':
                        valA = a.display_type;
                        valB = b.display_type;
                        break;
                    case 'status':
                        valA = a.status;
                        valB = b.status;
                        break;
                    case 'priority':
                        valA = a.priority;
                        valB = b.priority;
                        break;
                    case 'due_date':
                        valA = a.display_due || '';
                        valB = b.display_due || '';
                        break;
                    default:
                        valA = a.display_number;
                        valB = b.display_number;
                }
                
                if (this.currentSort.order === 'asc') {
                    return valA > valB ? 1 : -1;
                } else {
                    return valA < valB ? 1 : -1;
                }
            });

            this.combinedData = data;
            console.log('[RFI_Submittals] combinedData set:', this.combinedData.length, 'items');
            console.log('[RFI_Submittals] First item:', this.combinedData[0]);
        },

        /**
         * Render the dashboard
         */
        render: function() {
            console.log('[RFI_Submittals] render called');
            this.renderTable();
        },

        /**
         * Render metrics
         */
        renderMetrics: function() {
            if (this.metrics.rfi_metrics) {
                $('#metric-total-open').text(this.metrics.rfi_metrics.total_open);
                $('#metric-overdue-rfis').text(this.metrics.rfi_metrics.overdue);
            }
            if (this.metrics.submittal_metrics) {
                $('#metric-pending-submittals').text(this.metrics.submittal_metrics.total_pending);
                $('#metric-approved-week').text(
                    (this.metrics.rfi_metrics?.answered_this_week || 0) + 
                    (this.metrics.submittal_metrics?.approved_this_week || 0)
                );
                $('#metric-avg-response').text(
                    this.metrics.rfi_metrics?.avg_response_days || 0
                );
            }
        },

        /**
         * Update tab badges
         */
        updateTabBadges: function() {
            const rfiCount = this.rfiData?.length || 0;
            const subCount = this.submittalData?.length || 0;
            const overdueCount = 
                (this.rfiData || []).filter(i => i.urgency_flag === 'overdue').length +
                (this.submittalData || []).filter(i => i.urgency_flag === 'overdue').length;

            $('#tab-badge-all').text(rfiCount + subCount);
            $('#tab-badge-rfi').text(rfiCount);
            $('#tab-badge-submittals').text(subCount);
            $('#tab-badge-overdue').text(overdueCount);
        },

        /**
         * Render the data table
         */
        renderTable: function() {
            console.log('[RFI_Submittals] renderTable called');
            console.log('[RFI_Submittals] combinedData length:', this.combinedData.length);
            const tbody = $('#rfi-table-body');
            console.log('[RFI_Submittals] tbody element:', tbody.length > 0 ? 'found' : 'NOT FOUND');
            
            if (this.combinedData.length === 0) {
                console.log('[RFI_Submittals] No data to render - showing empty state');
                tbody.html(`
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
                                <div class="rfi-empty-title">No Items Found</div>
                                <div class="rfi-empty-text">${this.searchQuery ? 'Try adjusting your search or filters.' : 'Create your first RFI or Submittal to get started.'}</div>
                                ${!this.searchQuery ? '<button class="rfi-btn rfi-btn-primary" onclick="CRM_Modal.open(\'rfi\')">Create RFI</button>' : ''}
                            </div>
                        </td>
                    </tr>
                `);
                return;
            }

            const rows = this.combinedData.map((item, index) => {
                try {
                    // Validate item has required properties
                    if (!item || typeof item !== 'object') {
                        console.warn(`[RFI_Submittals] Invalid item at index ${index}:`, item);
                        return '';
                    }
                    
                    // Ensure item_type is set
                    if (!item.item_type) {
                        console.warn(`[RFI_Submittals] Missing item_type at index ${index}:`, item);
                        item.item_type = item.rfi_number ? 'rfi' : 'submittal';
                    }
                    
                    const statusClass = this.getStatusClass(item.status);
                    const priorityClass = this.getPriorityClass(item.priority);
                    const urgencyClass = item.urgency_flag === 'overdue' ? 'urgent' : 
                                        item.urgency_flag === 'warning' ? 'warning' : '';
                    
                    // Ensure ID is valid for DOM attributes
                    const itemId = item.id || 0;
                    const displayNum = item.display_number || itemId || 'N/A';
                    const itemTitle = item.title || 'Untitled';
                    
                    return `
                        <tr class="${urgencyClass}" data-id="${itemId}" data-type="${item.item_type}">
                            <td><input type="checkbox" class="rfi-item-checkbox" data-id="${itemId}" data-type="${item.item_type}" ${this.selectedItems.has(`${item.item_type}-${itemId}`) ? 'checked' : ''} onchange="RFI_Submittals.toggleItemSelect('${item.item_type}', ${itemId})"></td>
                            <td><strong>${displayNum}</strong></td>
                            <td>${this.escapeHtml(itemTitle)}</td>
                            <td><span class="rfi-type-badge">${item.display_type || item.item_type}</span></td>
                            <td><span class="rfi-status-badge ${statusClass}">${this.formatStatus(item.status)}</span></td>
                            <td><span class="rfi-priority-badge ${priorityClass}">${this.formatPriority(item.priority)}</span></td>
                            <td>${item.ball_in_court || '-'}</td>
                            <td>${item.display_due ? this.formatDate(item.display_due) : '-'}</td>
                            <td>${item.days_open || item.days_in_review || 0}</td>
                            <td>
                                <div class="rfi-row-actions">
                                    <button class="rfi-action-btn" onclick="RFI_Submittals.viewDetail('${item.item_type}', ${itemId})" title="View Details">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                    ${item.item_type === 'rfi' ? `
                                        <button class="rfi-action-btn" onclick="CRM_Modal.open('rfi-respond', {id: ${itemId}})" title="Respond">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                            </svg>
                                        </button>
                                    ` : `
                                        <button class="rfi-action-btn" onclick="CRM_Modal.open('submittal-review', {id: ${itemId}})" title="Review">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3z"/>
                                                <path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                                            </svg>
                                        </button>
                                    `}
                                    <button class="rfi-action-btn rfi-delete-btn" onclick="RFI_Submittals.deleteItem('${item.item_type}', ${itemId})" title="Delete">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                } catch (rowError) {
                    console.error(`[RFI_Submittals] Error rendering row ${index}:`, rowError, item);
                    return ''; // Return empty string so other rows still render
                }
            }).join('');

            tbody.html(rows);
        },

        /**
         * Get status CSS class
         */
        getStatusClass: function(status) {
            const classes = {
                'draft': 'status-draft',
                'open': 'status-open',
                'sent': 'status-open',
                'in_review': 'status-review',
                'answered': 'status-resolved',
                'approved': 'status-resolved',
                'approved_as_noted': 'status-resolved',
                'closed': 'status-closed',
                'rejected': 'status-rejected',
                'revise_resubmit': 'status-rejected',
                'submitted': 'status-pending',
                'pending': 'status-pending'
            };
            return classes[status] || 'status-draft';
        },

        /**
         * Get priority CSS class
         */
        getPriorityClass: function(priority) {
            const classes = {
                'critical': 'priority-critical',
                'high': 'priority-high',
                'normal': 'priority-normal',
                'low': 'priority-low'
            };
            return classes[priority] || 'priority-normal';
        },

        /**
         * Format status for display
         */
        formatStatus: function(status) {
            return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },

        /**
         * Format priority for display
         */
        formatPriority: function(priority) {
            return priority ? priority.charAt(0).toUpperCase() + priority.slice(1) : 'Normal';
        },

        /**
         * Format date for display
         */
        formatDate: function(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString();
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // ============================================
        // UI Actions
        // ============================================

        /**
         * Switch dashboard tab
         */
        switchTab: function(tab) {
            console.log('[RFI_Submittals] switchTab called:', tab);
            this.currentTab = tab;
            
            // Update tab buttons
            $('.rfi-tab-btn').removeClass('active');
            $(`.rfi-tab-btn[data-tab="${tab}"]`).addClass('active');

            // Show/hide appropriate panels
            if (tab === 'reports') {
                $('#rfi-unified-list').hide();
                $('#rfi-reports-panel').show();
                this.loadReports();
            } else {
                $('#rfi-unified-list').show();
                $('#rfi-reports-panel').hide();
                
                // Reset filter when switching tabs to ensure all items show
                // (RFIs and Submittals have different status values)
                if (this.currentFilter !== 'all') {
                    console.log('[RFI_Submittals] Resetting filter to all for tab:', tab);
                    this.currentFilter = 'all';
                    $('.rfi-filter-option').removeClass('active');
                    $('.rfi-filter-option[data-filter="all"]').addClass('active');
                }
                
                // Reload data to ensure we have fresh data for this tab
                console.log('[RFI_Submittals] Reloading data for tab:', tab);
                this.loadRFIs();
                this.loadSubmittals();
            }
        },

        /**
         * Handle search input
         */
        handleSearch: function(query) {
            this.searchQuery = query;
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadRFIs();
                this.loadSubmittals();
            }, 300);
        },

        /**
         * Toggle filter menu
         */
        toggleFilterMenu: function() {
            $('#rfi-filter-menu').toggleClass('show');
        },

        /**
         * Set filter
         */
        setFilter: function(filter) {
            this.currentFilter = filter;
            $('.rfi-filter-option').removeClass('active');
            $(`.rfi-filter-option[data-filter="${filter}"]`).addClass('active');
            $('#rfi-filter-menu').removeClass('show');
            this.loadRFIs();
            this.loadSubmittals();
        },

        /**
         * Sort table
         */
        sort: function(field) {
            if (this.currentSort.field === field) {
                this.currentSort.order = this.currentSort.order === 'asc' ? 'desc' : 'asc';
            } else {
                this.currentSort.field = field;
                this.currentSort.order = 'asc';
            }
            this.updateCombinedData();
            this.renderTable();
        },

        /**
         * Toggle select all items
         */
        toggleSelectAll: function() {
            const checked = $('#rfi-select-all').is(':checked');
            this.selectedItems.clear();
            
            if (checked) {
                this.combinedData.forEach(item => {
                    this.selectedItems.add(`${item.item_type}-${item.id}`);
                });
            }
            
            $('.rfi-item-checkbox').prop('checked', checked);
            this.updateSelectedCount();
        },

        /**
         * Toggle single item selection
         */
        toggleItemSelect: function(type, id) {
            const key = `${type}-${id}`;
            if (this.selectedItems.has(key)) {
                this.selectedItems.delete(key);
            } else {
                this.selectedItems.add(key);
            }
            this.updateSelectedCount();
        },

        /**
         * Update the selected count display
         */
        updateSelectedCount: function() {
            const count = this.selectedItems.size;
            $('#rfi-selected-count').text(count);
            if (count > 0) {
                $('#rfi-bulk-actions').show();
            } else {
                $('#rfi-bulk-actions').hide();
            }
        },

        /**
         * View item detail
         */
        viewDetail: function(type, id) {
            console.log('[RFI_Submittals.viewDetail] Called with type:', type, 'id:', id);
            if (!type || !id) {
                console.error('[RFI_Submittals.viewDetail] Missing type or id');
                showToast('Error: Cannot view item - missing information', 'error');
                return;
            }
            try {
                if (type === 'rfi') {
                    console.log('[RFI_Submittals.viewDetail] Opening RFI panel');
                    RFI_DetailPanel.open(id);
                } else if (type === 'submittal') {
                    console.log('[RFI_Submittals.viewDetail] Opening Submittal panel');
                    Submittal_DetailPanel.open(id);
                } else {
                    console.error('[RFI_Submittals.viewDetail] Unknown type:', type);
                    showToast('Error: Unknown item type', 'error');
                }
            } catch (err) {
                console.error('[RFI_Submittals.viewDetail] Error:', err);
                showToast('Error opening detail view: ' + err.message, 'error');
            }
        },

        /**
         * Toggle select all items on current page
         */
        toggleSelectAll: function() {
            const checked = $('#rfi-select-all').is(':checked');
            const visibleRows = $('#rfi-table-body tr:visible');
            
            visibleRows.each((_, row) => {
                const $row = $(row);
                const id = $row.data('id');
                const type = $row.data('type');
                const key = `${type}-${id}`;
                
                if (checked) {
                    this.selectedItems.add(key);
                } else {
                    this.selectedItems.delete(key);
                }
            });
            
            $('.rfi-item-checkbox').prop('checked', checked);
            this.updateSelectedCount();
        },

        /**
         * Toggle single item selection
         */
        toggleItemSelect: function(type, id) {
            const key = `${type}-${id}`;
            if (this.selectedItems.has(key)) {
                this.selectedItems.delete(key);
            } else {
                this.selectedItems.add(key);
            }
            this.updateSelectedCount();
        },

        /**
         * Update the selected count display
         */
        updateSelectedCount: function() {
            const count = this.selectedItems.size;
            $('#rfi-selected-count').text(count);
            if (count > 0) {
                $('#rfi-bulk-actions').show();
            } else {
                $('#rfi-bulk-actions').hide();
                $('#rfi-select-all').prop('checked', false);
            }
        },

        /**
         * Clear all selections
         */
        clearSelection: function() {
            this.selectedItems.clear();
            $('#rfi-select-all').prop('checked', false);
            $('.rfi-item-checkbox').prop('checked', false);
            this.updateSelectedCount();
        },

        /**
         * Toggle the bulk status dropdown menu
         */
        toggleBulkStatusMenu: function() {
            $('#rfi-bulk-status-menu').toggleClass('active');
        },

        /**
         * Bulk change status of selected items
         */
        bulkChangeStatus: function(newStatus) {
            if (this.selectedItems.size === 0) {
                alert('Please select at least one item.');
                return;
            }

            const rfiIds = [];
            const submittalIds = [];

            this.selectedItems.forEach(key => {
                const [type, id] = key.split('-');
                if (type === 'rfi') {
                    rfiIds.push(parseInt(id));
                } else {
                    submittalIds.push(parseInt(id));
                }
            });

            const promises = [];

            if (rfiIds.length > 0) {
                promises.push(CRM_API.bulkRFIAction({ action: 'change_status', ids: rfiIds, new_status: newStatus }));
            }

            if (submittalIds.length > 0) {
                promises.push(CRM_API.bulkSubmittalAction({ action: 'change_status', ids: submittalIds, new_status: newStatus }));
            }

            Promise.all(promises).then(() => {
                showToast(`Status updated to "${newStatus}" for ${this.selectedItems.size} item(s)`);
                this.selectedItems.clear();
                this.updateSelectedCount();
                this.loadDashboardData();
            }).catch(err => {
                console.error('[RFI_Submittals] Error changing status:', err);
                showToast(err.message || 'Failed to change status', 'error');
            });

            // Hide the menu
            $('#rfi-bulk-status-menu').removeClass('active');
        },

        /**
         * Bulk export selected items to CSV
         */
        bulkExport: function() {
            if (this.selectedItems.size === 0) {
                alert('Please select at least one item to export.');
                return;
            }

            const selectedRFIs = [];
            const selectedSubmittals = [];

            this.selectedItems.forEach(key => {
                const [type, id] = key.split('-');
                if (type === 'rfi') {
                    const rfi = this.rfiData.find(r => r.id === parseInt(id));
                    if (rfi) selectedRFIs.push(rfi);
                } else {
                    const sub = this.submittalData.find(s => s.id === parseInt(id));
                    if (sub) selectedSubmittals.push(sub);
                }
            });

            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Type,Number,Title,Status,Priority,Submitted By,Date,Days Open\n';

            selectedRFIs.forEach(item => {
                csvContent += `RFI,"${item.rfi_number || ''}","${(item.subject || '').replace(/"/g, '""')}","${item.status}","${item.priority || ''}","${item.requester_name || ''}","${item.requested_at || ''}","${item.days_open || 0}"\n`;
            });

            selectedSubmittals.forEach(item => {
                csvContent += `Submittal,"${item.submittal_number || ''}","${(item.title || '').replace(/"/g, '""')}","${item.status}","${item.priority || ''}","${item.submitted_by_name || ''}","${item.submitted_at || ''}","${item.days_in_review || 0}"\n`;
            });

            // Download
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `rfi-submittals-export-${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showToast(`${this.selectedItems.size} item(s) exported successfully`);
        },

        /**
         * Close all modals and panels
         */
        closeAllModals: function() {
            console.log('[RFI_Submittals] Closing all modals');
            $('#rfi-modal-container').empty();
            $('#rfi-detail-panel-container').empty();
            $('body').removeClass('rfi-modal-open');
            console.log('[RFI_Submittals] All modals closed');
        },

        /**
         * Delete individual item (RFI or Submittal)
         */
        deleteItem: function(type, id) {
            const itemType = type === 'rfi' ? 'RFI' : 'Submittal';
            if (!confirm(`Are you sure you want to delete this ${itemType}? This action cannot be undone.`)) {
                return;
            }

            const promise = type === 'rfi' 
                ? CRM_API.deleteRFI(id)
                : CRM_API.deleteSubmittal(id);

            promise.then(() => {
                showToast(`${itemType} deleted successfully`);
                // Remove from selected items if it was selected
                this.selectedItems.delete(`${type}-${id}`);
                this.updateSelectedCount();
                // Reload the data
                this.loadDashboardData();
            }).catch(err => {
                console.error(`[RFI_Submittals] Error deleting ${itemType}:`, err);
                showToast(err.message || `Failed to delete ${itemType}`, 'error');
            });
        },

        /**
         * Bulk delete selected items
         */
        bulkDelete: function() {
            if (this.selectedItems.size === 0) {
                alert('Please select at least one item to delete.');
                return;
            }

            if (!confirm(`Are you sure you want to delete ${this.selectedItems.size} item(s)? This action cannot be undone.`)) {
                return;
            }

            const rfiIds = [];
            const submittalIds = [];

            this.selectedItems.forEach(key => {
                const [type, id] = key.split('-');
                if (type === 'rfi') {
                    rfiIds.push(parseInt(id));
                } else {
                    submittalIds.push(parseInt(id));
                }
            });

            const promises = [];

            if (rfiIds.length > 0) {
                promises.push(CRM_API.bulkRFIAction({ action: 'delete', ids: rfiIds }));
            }

            if (submittalIds.length > 0) {
                promises.push(CRM_API.bulkSubmittalAction({ action: 'delete', ids: submittalIds }));
            }

            Promise.all(promises).then(() => {
                showToast(`${this.selectedItems.size} item(s) deleted successfully`);
                this.selectedItems.clear();
                this.updateSelectedCount();
                this.loadDashboardData();
            }).catch(err => {
                console.error('[RFI_Submittals] Error in bulk delete:', err);
                showToast(err.message || 'Failed to delete items', 'error');
            });
        },

        /**
         * Send bulk reminder
         */
        sendBulkReminder: function() {
            if (this.selectedItems.size === 0) {
                alert('Please select at least one item to send reminder.');
                return;
            }

            const rfiIds = [];
            const submittalIds = [];

            this.selectedItems.forEach(key => {
                const [type, id] = key.split('-');
                if (type === 'rfi') {
                    rfiIds.push(parseInt(id));
                } else {
                    submittalIds.push(parseInt(id));
                }
            });

            if (rfiIds.length > 0) {
                CRM_API.bulkRFIAction({ ids: rfiIds, action: 'send_reminder' });
            }
            if (submittalIds.length > 0) {
                CRM_API.bulkSubmittalAction({ ids: submittalIds, action: 'send_reminder' });
            }

            alert('Reminders sent successfully!');
            this.selectedItems.clear();
            $('#rfi-select-all').prop('checked', false);
            this.renderTable();
        },

        /**
         * Export log
         */
        exportLog: function() {
            const format = prompt('Export format: (csv or pdf)', 'csv');
            if (format === 'csv') {
                window.open(CRM_API.baseUrl + '/rfi/export?job_id=' + this.jobId, '_blank');
            }
        },

        /**
         * Load reports data
         */
        loadReports: function() {
            // Load RFI report
            CRM_API.getRFIReport({ job_id: this.jobId }).done((response) => {
                this.renderRFIReports(response);
            });

            // Load Submittal report
            CRM_API.getSubmittalReport({ job_id: this.jobId }).done((response) => {
                this.renderSubmittalReports(response);
            });
        },

        /**
         * Render RFI reports
         */
        renderRFIReports: function(data) {
            if (data.response_times) {
                const html = data.response_times.map(rt => `
                    <div class="rfi-report-row">
                        <span>${rt.responded_by_name || 'Unknown'}</span>
                        <span>${rt.count} RFIs</span>
                        <span>${parseFloat(rt.avg_days).toFixed(1)} days avg</span>
                    </div>
                `).join('');
                $('#rfi-response-time-chart').html(html || '<div class="rfi-empty-text">No data available</div>');
            }

            if (data.trends) {
                const html = data.trends.map(t => `
                    <div class="rfi-report-row">
                        <span>Week ${t.week}</span>
                        <span>${t.raised} raised</span>
                        <span>${t.answered} answered</span>
                    </div>
                `).join('');
                $('#rfi-volume-chart').html(html || '<div class="rfi-empty-text">No data available</div>');
            }
        },

        /**
         * Render Submittal reports
         */
        renderSubmittalReports: function(data) {
            if (data.turnaround_by_reviewer) {
                const html = data.turnaround_by_reviewer.map(t => `
                    <div class="rfi-report-row">
                        <span>${t.approved_by_name || 'Unknown'}</span>
                        <span>${t.count} submittals</span>
                        <span>${parseFloat(t.avg_days).toFixed(1)} days avg</span>
                    </div>
                `).join('');
                $('#submittal-turnaround-chart').html(html || '<div class="rfi-empty-text">No data available</div>');
            }

            if (data.first_time_approval_rate !== undefined) {
                $('#approval-rate-chart').html(`
                    <div class="rfi-metric-value" style="font-size: 48px; color: var(--pi-success);">
                        ${data.first_time_approval_rate}%
                    </div>
                    <div class="rfi-metric-label">First-time approval rate</div>
                `);
            }
        }
    };

    // ============================================
    // RFI Modal
    // ============================================
    const RFI_Modal = {
        currentId: null,
        mode: 'create',

        open: function(mode, id) {
            this.mode = mode;
            this.currentId = id;

            const titles = {
                'create': 'Create RFI',
                'edit': 'Edit RFI',
                'respond': 'Respond to RFI'
            };

            const modalHtml = `
                <div class="rfi-modal">
                    <div class="rfi-modal-content">
                        <div class="rfi-modal-header">
                            <h2>${titles[mode]}</h2>
                            <button class="rfi-modal-close" onclick="RFI_Submittals.closeAllModals()">&times;</button>
                        </div>
                        <div class="rfi-modal-body">
                            <form id="rfi-form">
                                <div class="rfi-form-grid">
                                    <div class="rfi-form-group rfi-form-full">
                                        <label class="rfi-label">Title *</label>
                                        <input type="text" class="rfi-input" id="rfi-title" required>
                                    </div>
                                    <div class="rfi-form-group">
                                        <label class="rfi-label">Priority</label>
                                        <select class="rfi-select" id="rfi-priority">
                                            <option value="low">Low</option>
                                            <option value="normal" selected>Normal</option>
                                            <option value="high">High</option>
                                            <option value="critical">Critical</option>
                                        </select>
                                    </div>
                                    <div class="rfi-form-group">
                                        <label class="rfi-label">Due Date</label>
                                        <input type="date" class="rfi-input" id="rfi-due-date">
                                    </div>
                                    <div class="rfi-form-group">
                                        <label class="rfi-label">Assigned To</label>
                                        <input type="text" class="rfi-input" id="rfi-assigned-to" placeholder="e.g., Architect, Consultant">
                                    </div>
                                    <div class="rfi-form-group">
                                        <label class="rfi-label">Drawing Reference</label>
                                        <input type="text" class="rfi-input" id="rfi-drawing-ref" placeholder="e.g., A-101">
                                    </div>
                                    <div class="rfi-form-group rfi-form-full">
                                        <label class="rfi-label">Description</label>
                                        <textarea class="rfi-textarea" id="rfi-description" rows="4"></textarea>
                                    </div>
                                    <div class="rfi-form-group rfi-form-full">
                                        <label class="rfi-label">Suggested Solution</label>
                                        <textarea class="rfi-textarea" id="rfi-suggested-solution" rows="3"></textarea>
                                    </div>
                                    ${mode === 'respond' ? `
                                        <div class="rfi-form-group rfi-form-full">
                                            <label class="rfi-label">Response *</label>
                                            <textarea class="rfi-textarea" id="rfi-response" rows="4" required></textarea>
                                        </div>
                                        <div class="rfi-form-group">
                                            <label class="rfi-checkbox-label">
                                                <input type="checkbox" id="rfi-cost-impact"> Has Cost Impact
                                            </label>
                                        </div>
                                    ` : ''}
                                </div>
                            </form>
                        </div>
                        <div class="rfi-modal-footer">
                            <button class="rfi-btn rfi-btn-secondary" onclick="RFI_Submittals.closeAllModals()">Cancel</button>
                            <button class="rfi-btn rfi-btn-primary" onclick="RFI_Modal.save()">${mode === 'respond' ? 'Submit Response' : 'Save RFI'}</button>
                        </div>
                    </div>
                </div>
            `;

            $('#rfi-modal-container').html(modalHtml);
            $('body').addClass('rfi-modal-open');

            // If editing or responding, load data
            if (id && (mode === 'edit' || mode === 'respond')) {
                this.loadRFIData(id);
            }
        },

        loadRFIData: function(id) {
            CRM_API.getSingleRFI(id).done((response) => {
                const rfi = response.rfi;
                $('#rfi-title').val(rfi.title);
                $('#rfi-priority').val(rfi.priority);
                $('#rfi-due-date').val(rfi.due_date);
                $('#rfi-assigned-to').val(rfi.assigned_to);
                $('#rfi-drawing-ref').val(rfi.drawing_references);
                $('#rfi-description').val(rfi.description);
                $('#rfi-suggested-solution').val(rfi.suggested_solution);
            });
        },

        save: function() {
            const data = {
                job_id: RFI_Submittals.jobId,
                title: $('#rfi-title').val(),
                priority: $('#rfi-priority').val(),
                due_date: $('#rfi-due-date').val(),
                assigned_to: $('#rfi-assigned-to').val(),
                drawing_references: $('#rfi-drawing-ref').val(),
                description: $('#rfi-description').val(),
                suggested_solution: $('#rfi-suggested-solution').val()
            };

            if (this.mode === 'create') {
                CRM_API.createRFI(data).then(() => {
                    RFI_Submittals.closeAllModals();
                    RFI_Submittals.loadDashboardData();
                    showToast('RFI created successfully');
                }).catch(err => {
                    showToast(err.message || 'Failed to create RFI', 'error');
                });
            } else if (this.mode === 'edit') {
                CRM_API.updateRFI(this.currentId, data).then(() => {
                    RFI_Submittals.closeAllModals();
                    RFI_Submittals.loadDashboardData();
                    showToast('RFI updated successfully');
                }).catch(err => {
                    showToast(err.message || 'Failed to update RFI', 'error');
                });
            } else if (this.mode === 'respond') {
                const responseData = {
                    response: $('#rfi-response').val(),
                    cost_impact: $('#rfi-cost-impact').is(':checked'),
                    add_comment: true
                };
                CRM_API.respondRFI(this.currentId, responseData).then(() => {
                    RFI_Submittals.closeAllModals();
                    RFI_Submittals.loadDashboardData();
                    showToast('Response submitted successfully');
                }).catch(err => {
                    showToast(err.message || 'Failed to submit response', 'error');
                });
            }
        }
    };

    // ============================================
    // Submittal Modal
    // ============================================
    const Submittal_Modal = {
        currentId: null,
        mode: 'create',

        open: function(mode, id) {
            this.mode = mode;
            this.currentId = id;

            const titles = {
                'create': 'Submit Submittal',
                'edit': 'Edit Submittal',
                'review': 'Review Submittal',
                'resubmit': 'Resubmit Submittal'
            };

            const modalHtml = `
                <div class="rfi-modal">
                    <div class="rfi-modal-content">
                        <div class="rfi-modal-header">
                            <h2>${titles[mode]}</h2>
                            <button class="rfi-modal-close" onclick="RFI_Submittals.closeAllModals()">&times;</button>
                        </div>
                        <div class="rfi-modal-body">
                            <form id="submittal-form">
                                <div class="rfi-form-grid">
                                    <div class="rfi-form-group rfi-form-full">
                                        <label class="rfi-label">Title *</label>
                                        <input type="text" class="rfi-input" id="submittal-title" required>
                                    </div>
                                    <div class="rfi-form-group">
                                        <label class="rfi-label">Type</label>
                                        <select class="rfi-select" id="submittal-type">
                                            <option value="product_data">Product Data</option>
                                            <option value="shop_drawings">Shop Drawings</option>
                                            <option value="samples">Samples</option>
                                            <option value="mockups">Mockups</option>
                                            <option value="schedules">Schedules</option>
                                            <option value="calculations">Calculations</option>
                                            <option value="certificates">Certificates</option>
                                            <option value="warranties">Warranties</option>
                                        </select>
                                    </div>
                                    <div class="rfi-form-group">
                                        <label class="rfi-label">Specification Section</label>
                                        <input type="text" class="rfi-input" id="submittal-spec-section" placeholder="e.g., 03 30 00">
                                    </div>
                                    <div class="rfi-form-group">
                                        <label class="rfi-label">Review Due Date</label>
                                        <input type="date" class="rfi-input" id="submittal-due-date">
                                    </div>
                                    <div class="rfi-form-group rfi-form-full">
                                        <label class="rfi-label">Description</label>
                                        <textarea class="rfi-textarea" id="submittal-description" rows="4"></textarea>
                                    </div>
                                    ${mode === 'review' ? `
                                        <div class="rfi-form-group">
                                            <label class="rfi-label">Decision *</label>
                                            <select class="rfi-select" id="submittal-decision" required>
                                                <option value="">Select...</option>
                                                <option value="approved">Approved</option>
                                                <option value="approved_as_noted">Approved as Noted</option>
                                                <option value="revise_resubmit">Revise & Resubmit</option>
                                                <option value="rejected">Rejected</option>
                                            </select>
                                        </div>
                                        <div class="rfi-form-group rfi-form-full">
                                            <label class="rfi-label">Comments</label>
                                            <textarea class="rfi-textarea" id="submittal-comments" rows="4"></textarea>
                                        </div>
                                    ` : ''}
                                    ${mode === 'resubmit' ? `
                                        <div class="rfi-form-group rfi-form-full">
                                            <label class="rfi-label">Change Description</label>
                                            <textarea class="rfi-textarea" id="submittal-changes" rows="3" placeholder="Describe the changes made in this revision..."></textarea>
                                        </div>
                                    ` : ''}
                                </div>
                            </form>
                        </div>
                        <div class="rfi-modal-footer">
                            <button class="rfi-btn rfi-btn-secondary" onclick="RFI_Submittals.closeAllModals()">Cancel</button>
                            <button class="rfi-btn rfi-btn-primary" onclick="Submittal_Modal.save()">
                                ${mode === 'review' ? 'Submit Review' : mode === 'resubmit' ? 'Resubmit' : 'Save Submittal'}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('#rfi-modal-container').html(modalHtml);
            $('body').addClass('rfi-modal-open');

            if (id && (mode === 'edit' || mode === 'review' || mode === 'resubmit')) {
                this.loadSubmittalData(id);
            }
        },

        loadSubmittalData: function(id) {
            CRM_API.getSingleSubmittal(id).done((response) => {
                const sub = response.submittal;
                $('#submittal-title').val(sub.title);
                $('#submittal-type').val(sub.submittal_type);
                $('#submittal-spec-section').val(sub.specification_section);
                $('#submittal-due-date').val(sub.due_date);
                $('#submittal-description').val(sub.description);
            });
        },

        save: function() {
            if (this.mode === 'create') {
                const data = {
                    job_id: RFI_Submittals.jobId,
                    title: $('#submittal-title').val(),
                    submittal_type: $('#submittal-type').val(),
                    specification_section: $('#submittal-spec-section').val(),
                    due_date: $('#submittal-due-date').val(),
                    description: $('#submittal-description').val()
                };
                CRM_API.createSubmittal(data).then(() => {
                    RFI_Submittals.closeAllModals();
                    RFI_Submittals.loadDashboardData();
                    showToast('Submittal created successfully');
                }).catch(err => {
                    showToast(err.message || 'Failed to create submittal', 'error');
                });
            } else if (this.mode === 'edit') {
                const data = {
                    title: $('#submittal-title').val(),
                    submittal_type: $('#submittal-type').val(),
                    specification_section: $('#submittal-spec-section').val(),
                    due_date: $('#submittal-due-date').val(),
                    description: $('#submittal-description').val()
                };
                CRM_API.updateSubmittal(this.currentId, data).then(() => {
                    RFI_Submittals.closeAllModals();
                    RFI_Submittals.loadDashboardData();
                    showToast('Submittal updated successfully');
                }).catch(err => {
                    showToast(err.message || 'Failed to update submittal', 'error');
                });
            } else if (this.mode === 'review') {
                const data = {
                    decision: $('#submittal-decision').val(),
                    comments: $('#submittal-comments').val()
                };
                CRM_API.reviewSubmittal(this.currentId, data).then(() => {
                    RFI_Submittals.closeAllModals();
                    RFI_Submittals.loadDashboardData();
                    showToast('Review submitted successfully');
                }).catch(err => {
                    showToast(err.message || 'Failed to submit review', 'error');
                });
            } else if (this.mode === 'resubmit') {
                const data = {
                    title: $('#submittal-title').val(),
                    description: $('#submittal-description').val(),
                    change_description: $('#submittal-changes').val()
                };
                CRM_API.resubmitSubmittal(this.currentId, data).then(() => {
                    RFI_Submittals.closeAllModals();
                    RFI_Submittals.loadDashboardData();
                    showToast('Submittal resubmitted successfully');
                }).catch(err => {
                    showToast(err.message || 'Failed to resubmit submittal', 'error');
                });
            }
        }
    };

    // ============================================
    // RFI Detail Panel
    // ============================================
    const RFI_DetailPanel = {
        open: function(id) {
            console.log('[RFI_DetailPanel] Opening RFI:', id);
            if (!id) {
                console.error('[RFI_DetailPanel] No ID provided');
                showToast('Error: No RFI ID provided', 'error');
                return;
            }
            if (typeof CRM_API === 'undefined' || !CRM_API.getSingleRFI) {
                console.error('[RFI_DetailPanel] CRM_API.getSingleRFI not available');
                showToast('Error: API not available', 'error');
                return;
            }
            CRM_API.getSingleRFI(id).then((response) => {
                console.log('[RFI_DetailPanel] Got response:', response);
                if (!response || !response.rfi) {
                    console.error('[RFI_DetailPanel] Invalid response, no rfi data');
                    showToast('Failed to load RFI details', 'error');
                    return;
                }
                this.render(response);
                $('body').addClass('rfi-modal-open');
                console.log('[RFI_DetailPanel] Panel opened successfully');
            }).catch((err) => {
                console.error('[RFI_DetailPanel] Error loading RFI:', err);
                showToast('Failed to load RFI details: ' + (err.message || 'Unknown error'), 'error');
            });
        },

        render: function(data) {
            console.log('[RFI_DetailPanel.render] Rendering RFI:', data);
            if (!data || !data.rfi) {
                console.error('[RFI_DetailPanel.render] No data or rfi provided');
                showToast('Error: No RFI data to display', 'error');
                return;
            }
            const rfi = data.rfi;
            console.log('[RFI_DetailPanel.render] RFI data:', rfi);
            const statusClass = RFI_Submittals.getStatusClass(rfi.status);
            // Bind escapeHtml locally for template use
            const escapeHtml = RFI_Submittals.escapeHtml.bind(RFI_Submittals);

            const panelHtml = `
                <div class="rfi-detail-panel-backdrop" onclick="RFI_Submittals.closeAllModals()"></div>
                <div class="rfi-detail-panel">
                    <div class="rfi-detail-panel-header">
                        <div class="rfi-detail-panel-title">
                            <span class="rfi-status-badge ${statusClass}">${RFI_Submittals.formatStatus(rfi.status)}</span>
                            <h2>${rfi.rfi_number}: ${escapeHtml(rfi.title)}</h2>
                        </div>
                        <button class="rfi-detail-panel-close" onclick="RFI_Submittals.closeAllModals()">&times;</button>
                    </div>
                    <div class="rfi-detail-panel-body">
                        <div class="rfi-detail-grid">
                            <div class="rfi-detail-main">
                                <div class="rfi-detail-section">
                                    <div class="rfi-detail-label">Description</div>
                                    <div class="rfi-detail-text">${escapeHtml(rfi.description) || 'No description provided.'}</div>
                                </div>
                                <div class="rfi-detail-section">
                                    <div class="rfi-detail-label">Suggested Solution</div>
                                    <div class="rfi-detail-text">${escapeHtml(rfi.suggested_solution) || 'No solution suggested.'}</div>
                                </div>
                                ${rfi.response ? `
                                    <div class="rfi-detail-section">
                                        <div class="rfi-detail-label">Response</div>
                                        <div class="rfi-response-box">
                                            <div class="rfi-response-meta">
                                                <span>By ${rfi.responded_by_name || 'Unknown'}</span>
                                                <span>${rfi.responded_at ? RFI_Submittals.formatDate(rfi.responded_at) : ''}</span>
                                            </div>
                                            <div class="rfi-detail-text">${escapeHtml(rfi.response)}</div>
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <!-- Comments Section -->
                                <div class="rfi-comments-section">
                                    <div class="rfi-comments-header">
                                        <h4>Comments & Discussion</h4>
                                    </div>
                                    <div class="rfi-comments-list">
                                        ${data.comments && data.comments.length > 0 ? 
                                            data.comments.map(c => `
                                                <div class="rfi-comment">
                                                    <div class="rfi-comment-meta">
                                                        <span class="rfi-comment-author">${c.commented_by_name || 'Unknown'}</span>
                                                        <span class="rfi-comment-time">${RFI_Submittals.formatDate(c.commented_at)}</span>
                                                    </div>
                                                    <div class="rfi-comment-text">${escapeHtml(c.comment)}</div>
                                                </div>
                                            `).join('') : 
                                            '<div class="rfi-empty-text">No comments yet.</div>'
                                        }
                                    </div>
                                    <div class="rfi-comment-input">
                                        <textarea class="rfi-textarea" id="rfi-new-comment" rows="3" placeholder="Add a comment..."></textarea>
                                        <button class="rfi-btn rfi-btn-primary rfi-comment-submit" onclick="RFI_DetailPanel.addComment(${rfi.id})">Add Comment</button>
                                    </div>
                                </div>
                            </div>
                            <div class="rfi-detail-sidebar">
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Requester</div>
                                    <div class="rfi-detail-value">${rfi.requested_by_name || 'Unknown'}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Date Requested</div>
                                    <div class="rfi-detail-value">${RFI_Submittals.formatDate(rfi.requested_at)}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Due Date</div>
                                    <div class="rfi-detail-value">${rfi.due_date ? RFI_Submittals.formatDate(rfi.due_date) : 'Not set'}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Assigned To</div>
                                    <div class="rfi-detail-value">${rfi.assigned_to || 'Not assigned'}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Ball in Court</div>
                                    <div class="rfi-detail-value">${rfi.ball_in_court || '-'}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Priority</div>
                                    <div class="rfi-detail-value"><span class="rfi-priority-badge ${RFI_Submittals.getPriorityClass(rfi.priority)}">${RFI_Submittals.formatPriority(rfi.priority)}</span></div>
                                </div>
                                ${rfi.drawing_references ? `
                                    <div class="rfi-detail-info-group">
                                        <div class="rfi-detail-label">Drawing References</div>
                                        <div class="rfi-detail-value">${rfi.drawing_references}</div>
                                    </div>
                                ` : ''}
                                ${rfi.cost_impact ? `
                                    <div class="rfi-detail-info-group">
                                        <div class="rfi-detail-label">Cost Impact</div>
                                        <div class="rfi-detail-value urgent">$${parseFloat(rfi.cost_impact_amount).toFixed(2)}</div>
                                    </div>
                                ` : ''}
                                ${rfi.schedule_impact ? `
                                    <div class="rfi-detail-info-group">
                                        <div class="rfi-detail-label">Schedule Impact</div>
                                        <div class="rfi-detail-value warning">${rfi.schedule_impact_days} days</div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="rfi-detail-panel-footer">
                        <button class="rfi-btn rfi-btn-secondary" onclick="RFI_Submittals.closeAllModals()">Close</button>
                        <div class="rfi-detail-actions">
                            <button class="rfi-btn rfi-btn-primary" onclick="CRM_Modal.open('rfi-respond', {id: ${rfi.id}})">Respond</button>
                            <button class="rfi-btn rfi-btn-warning" onclick="CRM_Modal.open('rfi-edit', {id: ${rfi.id}, title: '${escapeHtml(rfi.title)}', priority: '${rfi.priority}', due_date: '${rfi.due_date || ''}', description: '${escapeHtml(rfi.description || '')}'})">Edit</button>
                            <button class="rfi-btn rfi-btn-danger" onclick="RFI_DetailPanel.closeRFI(${rfi.id})">Close RFI</button>
                        </div>
                    </div>
                </div>
            `;

            $('#rfi-detail-panel-container').html(panelHtml);
            console.log('[RFI_DetailPanel.render] HTML injected, container now has:', $('#rfi-detail-panel-container').children().length, 'children');
        },

        addComment: function(rfiId) {
            console.log('[RFI_DetailPanel.addComment] Adding comment to RFI:', rfiId);
            const comment = $('#rfi-new-comment').val();
            if (!comment.trim()) {
                console.log('[RFI_DetailPanel.addComment] Empty comment, aborting');
                showToast('Please enter a comment', 'warning');
                return;
            }

            if (typeof CRM_API === 'undefined' || !CRM_API.addRFIComment) {
                console.error('[RFI_DetailPanel.addComment] CRM_API.addRFIComment not available');
                showToast('Error: Comment API not available', 'error');
                return;
            }

            console.log('[RFI_DetailPanel.addComment] Sending comment:', comment);
            CRM_API.addRFIComment(rfiId, { comment }).then((response) => {
                console.log('[RFI_DetailPanel.addComment] Comment added successfully:', response);
                showToast('Comment added successfully', 'success');
                $('#rfi-new-comment').val('');
                // Use RFI_DetailPanel explicitly instead of this to ensure correct context
                RFI_DetailPanel.open(rfiId);
            }).catch((err) => {
                console.error('[RFI_DetailPanel.addComment] Error adding comment:', err);
                showToast('Failed to add comment: ' + (err.message || 'Unknown error'), 'error');
            });
        },

        closeRFI: function(id) {
            console.log('[RFI_DetailPanel.closeRFI] Closing RFI:', id);
            if (!confirm('Are you sure you want to close this RFI?')) {
                console.log('[RFI_DetailPanel.closeRFI] User cancelled');
                return;
            }

            if (typeof CRM_API === 'undefined' || !CRM_API.closeRFI) {
                console.error('[RFI_DetailPanel.closeRFI] CRM_API.closeRFI not available');
                showToast('Error: Close API not available', 'error');
                return;
            }

            console.log('[RFI_DetailPanel.closeRFI] Sending close request');
            CRM_API.closeRFI(id).then((response) => {
                console.log('[RFI_DetailPanel.closeRFI] RFI closed successfully:', response);
                showToast('RFI closed successfully', 'success');
                RFI_Submittals.closeAllModals();
                // Force clear cache and reload with timestamp to prevent stale data
                console.log('[RFI_DetailPanel.closeRFI] Forcing dashboard refresh');
                RFI_Submittals.rfiData = [];
                RFI_Submittals.submittalData = [];
                RFI_Submittals.combinedData = [];
                RFI_Submittals.lastRefresh = Date.now();
                RFI_Submittals.loadDashboardData();
            }).catch((err) => {
                console.error('[RFI_DetailPanel.closeRFI] Error closing RFI:', err);
                showToast('Failed to close RFI: ' + (err.message || 'Unknown error'), 'error');
            });
        }
    };

    // ============================================
    // Submittal Detail Panel
    // ============================================
    const Submittal_DetailPanel = {
        open: function(id) {
            console.log('[Submittal_DetailPanel] Opening Submittal:', id);
            CRM_API.getSingleSubmittal(id).then((response) => {
                console.log('[Submittal_DetailPanel] Got response:', response);
                if (!response || !response.submittal) {
                    console.error('[Submittal_DetailPanel] Invalid response, no submittal data');
                    showToast('Failed to load Submittal details', 'error');
                    return;
                }
                this.render(response);
                $('body').addClass('rfi-modal-open');
            }).catch((err) => {
                console.error('[Submittal_DetailPanel] Error loading Submittal:', err);
                showToast('Failed to load Submittal details', 'error');
            });
        },

        render: function(data) {
            console.log('[Submittal_DetailPanel.render] Rendering Submittal:', data);
            if (!data || !data.submittal) {
                console.error('[Submittal_DetailPanel.render] No data or submittal provided');
                showToast('Error: No Submittal data to display', 'error');
                return;
            }
            const sub = data.submittal;
            console.log('[Submittal_DetailPanel.render] Submittal data:', sub);
            const statusClass = RFI_Submittals.getStatusClass(sub.status);
            // Bind escapeHtml locally for template use
            const escapeHtml = RFI_Submittals.escapeHtml.bind(RFI_Submittals);

            const panelHtml = `
                <div class="rfi-detail-panel-backdrop" onclick="RFI_Submittals.closeAllModals()"></div>
                <div class="rfi-detail-panel">
                    <div class="rfi-detail-panel-header">
                        <div class="rfi-detail-panel-title">
                            <span class="rfi-status-badge ${statusClass}">${RFI_Submittals.formatStatus(sub.status)}</span>
                            <span class="rfi-revision-badge">Rev ${sub.revision_number}</span>
                            <h2>${sub.submittal_number}: ${escapeHtml(sub.title)}</h2>
                        </div>
                        <button class="rfi-detail-panel-close" onclick="RFI_Submittals.closeAllModals()">&times;</button>
                    </div>
                    <div class="rfi-detail-panel-body">
                        <div class="rfi-detail-grid">
                            <div class="rfi-detail-main">
                                <div class="rfi-detail-section">
                                    <div class="rfi-detail-label">Description</div>
                                    <div class="rfi-detail-text">${escapeHtml(sub.description) || 'No description provided.'}</div>
                                </div>
                                
                                <!-- Reviews Section -->
                                <div class="rfi-detail-section">
                                    <div class="rfi-detail-label">Review History</div>
                                    ${data.reviews && data.reviews.length > 0 ? `
                                        <div class="rfi-reviews-list">
                                            ${data.reviews.map(r => `
                                                <div class="rfi-review-item">
                                                    <div class="rfi-review-header">
                                                        <span class="rfi-review-decision ${r.decision}">${RFI_Submittals.formatStatus(r.decision)}</span>
                                                        <span class="rfi-review-meta">By ${r.reviewer_name || 'Unknown'} on ${RFI_Submittals.formatDate(r.reviewed_at)}</span>
                                                    </div>
                                                    ${r.comments ? `<div class="rfi-review-comments">${escapeHtml(r.comments)}</div>` : ''}
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : '<div class="rfi-empty-text">No reviews yet.</div>'}
                                </div>
                                
                                <!-- Revisions Section -->
                                <div class="rfi-detail-section">
                                    <div class="rfi-detail-label">Revision History</div>
                                    ${data.revisions && data.revisions.length > 0 ? `
                                        <div class="rfi-revisions-list">
                                            ${data.revisions.map(r => `
                                                <div class="rfi-revision-item">
                                                    <div class="rfi-revision-header">
                                                        <span class="rfi-revision-number">Revision ${r.revision_number}</span>
                                                        <span class="rfi-revision-date">${RFI_Submittals.formatDate(r.submitted_at)}</span>
                                                    </div>
                                                    <div class="rfi-revision-by">By ${r.submitted_by_name || 'Unknown'}</div>
                                                    ${r.change_description ? `<div class="rfi-revision-changes">${escapeHtml(r.change_description)}</div>` : ''}
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : '<div class="rfi-empty-text">No revisions yet.</div>'}
                                </div>
                                
                                <!-- Activity Log -->
                                <div class="rfi-detail-section">
                                    <div class="rfi-detail-label">Activity Log</div>
                                    ${data.activity_log && data.activity_log.length > 0 ? `
                                        <div class="rfi-activity-list">
                                            ${data.activity_log.map(a => `
                                                <div class="rfi-activity-item">
                                                    <span class="rfi-activity-type">${a.activity_type}</span>
                                                    <span class="rfi-activity-desc">${escapeHtml(a.description)}</span>
                                                    <span class="rfi-activity-meta">By ${a.performed_by_name || 'Unknown'} on ${RFI_Submittals.formatDate(a.performed_at)}</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : '<div class="rfi-empty-text">No activity recorded.</div>'}
                                </div>
                            </div>
                            <div class="rfi-detail-sidebar">
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Submittal Type</div>
                                    <div class="rfi-detail-value">${RFI_Submittals.formatStatus(sub.submittal_type)}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Submitted By</div>
                                    <div class="rfi-detail-value">${sub.submitted_by_name || 'Unknown'}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Date Submitted</div>
                                    <div class="rfi-detail-value">${RFI_Submittals.formatDate(sub.submitted_at)}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Review Due Date</div>
                                    <div class="rfi-detail-value">${sub.due_date ? RFI_Submittals.formatDate(sub.due_date) : 'Not set'}</div>
                                </div>
                                <div class="rfi-detail-info-group">
                                    <div class="rfi-detail-label">Ball in Court</div>
                                    <div class="rfi-detail-value">${sub.ball_in_court || '-'}</div>
                                </div>
                                ${sub.specification_section ? `
                                    <div class="rfi-detail-info-group">
                                        <div class="rfi-detail-label">Specification</div>
                                        <div class="rfi-detail-value">${sub.specification_section}</div>
                                    </div>
                                ` : ''}
                                ${sub.subcontractor_name ? `
                                    <div class="rfi-detail-info-group">
                                        <div class="rfi-detail-label">Subcontractor</div>
                                        <div class="rfi-detail-value">${sub.subcontractor_name}</div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="rfi-detail-panel-footer">
                        <button class="rfi-btn rfi-btn-secondary" onclick="RFI_Submittals.closeAllModals()">Close</button>
                        <div class="rfi-detail-actions">
                            ${sub.status !== 'approved' && sub.status !== 'closed' ? `
                                <button class="rfi-btn rfi-btn-primary" onclick="CRM_Modal.open('submittal-review', {id: ${sub.id}})">Review</button>
                            ` : ''}
                            ${sub.status === 'revise_resubmit' ? `
                                <button class="rfi-btn rfi-btn-warning" onclick="CRM_Modal.open('submittal-resubmit', {id: ${sub.id}, title: '${escapeHtml(sub.title)}', description: '${escapeHtml(sub.description || '')}'})">Resubmit</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;

            $('#rfi-detail-panel-container').html(panelHtml);
        }
    };

    // ============================================
    // API Client Extensions
    // ============================================
    window.CRM_API = window.CRM_API || {};

    // Extend CRM_API with new methods
    Object.assign(CRM_API, {
        getRFISubmittalsDashboard: function(params) {
            return $.ajax({
                url: this.baseUrl + '/rfi-submittals/dashboard',
                method: 'GET',
                data: params,
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        getSingleRFI: function(id) {
            return $.ajax({
                url: this.baseUrl + '/rfi/' + id,
                method: 'GET',
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        updateRFI: function(id, data) {
            return $.ajax({
                url: this.baseUrl + '/rfi/' + id,
                method: 'PATCH',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        closeRFI: function(id) {
            return $.ajax({
                url: this.baseUrl + '/rfi/' + id + '/close',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({}),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        addRFIComment: function(id, data) {
            return $.ajax({
                url: this.baseUrl + '/rfi/' + id + '/comment',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        bulkRFIAction: function(data) {
            return $.ajax({
                url: this.baseUrl + '/rfi/bulk-action',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        getRFIReport: function(params) {
            return $.ajax({
                url: this.baseUrl + '/rfi/reports/dashboard',
                method: 'GET',
                data: params,
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        getSingleSubmittal: function(id) {
            return $.ajax({
                url: this.baseUrl + '/submittals/' + id,
                method: 'GET',
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        updateSubmittal: function(id, data) {
            return $.ajax({
                url: this.baseUrl + '/submittals/' + id,
                method: 'PATCH',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        reviewSubmittal: function(id, data) {
            return $.ajax({
                url: this.baseUrl + '/submittals/' + id + '/review',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        resubmitSubmittal: function(id, data) {
            return $.ajax({
                url: this.baseUrl + '/submittals/' + id + '/resubmit',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        bulkSubmittalAction: function(data) {
            return $.ajax({
                url: this.baseUrl + '/submittals/bulk-action',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        },

        getSubmittalReport: function(params) {
            return $.ajax({
                url: this.baseUrl + '/submittals/reports/dashboard',
                method: 'GET',
                data: params,
                beforeSend: (xhr) => {
                    if (this.nonce) xhr.setRequestHeader('X-WP-Nonce', this.nonce);
                }
            });
        }
    });

    // ============================================
    // Expose to global scope
    // ============================================
    window.RFI_Submittals = RFI_Submittals;
    window.RFI_Modal = RFI_Modal;
    window.Submittal_Modal = Submittal_Modal;
    window.RFI_DetailPanel = RFI_DetailPanel;
    window.Submittal_DetailPanel = Submittal_DetailPanel;

    // Initialize when document is ready and job tab is shown
    $(document).on('crm-tab-shown', function(e, tabId) {
        if (tabId === 'rfi-submittals' && window.piJobId) {
            RFI_Submittals.init(window.piJobId);
        }
    });

})(jQuery);
