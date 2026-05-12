/**
 * Planning Index CRM - Comprehensive Construction CRM System
 * Job page CRM features - Communications, Documents, Team, Safety, Quality, etc.
 */

(function($) {
    'use strict';

    // ============================================
    // CRM API Client
    // ============================================
    const CRM_API = {
        base: '/wp-json/pi-crm/v1',
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
            
            console.log('[CRM_API] Request:', {
                url: url,
                method: config.method || 'GET',
                headers: config.headers,
                body: config.body ? JSON.parse(config.body) : null
            });
            
            return fetch(url, config).then(async r => {
                const responseText = await r.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    data = { raw: responseText, parseError: e.message };
                }
                
                console.log('[CRM_API] Response:', {
                    status: r.status,
                    statusText: r.statusText,
                    ok: r.ok,
                    data: data
                });
                
                if (!r.ok) {
                    console.error('[CRM_API] Error Response:', data);
                    return Promise.reject(data);
                }
                return data;
            }).catch(err => {
                console.error('[CRM_API] Fetch Error:', err);
                throw err;
            });
        },

        // Communications
        getCommunications(jobId, params = {}) {
            const query = new URLSearchParams({ job_id: jobId, ...params });
            return this.request(`/communications?${query}`);
        },
        createCommunication(data) {
            return this.request('/communications', { method: 'POST', body: JSON.stringify(data) });
        },
        sendEmail(data) {
            return this.request('/communications/send', { method: 'POST', body: JSON.stringify(data) });
        },
        getEmailTemplates() {
            return this.request('/email-templates');
        },

        // Documents
        getDocuments(jobId, params = {}) {
            const query = new URLSearchParams({ job_id: jobId, ...params });
            return this.request(`/documents?${query}`);
        },
        deleteDocument(id) {
            return this.request(`/documents/${id}`, { method: 'DELETE' });
        },

        // Timesheets
        getTimesheets(jobId, params = {}) {
            const query = new URLSearchParams({ job_id: jobId, ...params });
            return this.request(`/timesheets?${query}`);
        },
        createTimesheet(data) {
            return this.request('/timesheets', { method: 'POST', body: JSON.stringify(data) });
        },
        clockInOut(data) {
            return this.request('/timesheets/clock', { method: 'POST', body: JSON.stringify(data) });
        },
        getTimesheetSummary(jobId) {
            return this.request(`/timesheets/summary?job_id=${jobId}`);
        },

        // Safety
        getSafetyInspections(jobId) {
            return this.request(`/safety/inspections?job_id=${jobId}`);
        },
        createSafetyInspection(data) {
            return this.request('/safety/inspections', { method: 'POST', body: JSON.stringify(data) });
        },
        getSafetyIncidents(jobId) {
            return this.request(`/safety/incidents?job_id=${jobId}`);
        },
        createSafetyIncident(data) {
            return this.request('/safety/incidents', { method: 'POST', body: JSON.stringify(data) });
        },
        getChecklistTemplates() {
            return this.request('/safety/checklist-templates');
        },

        // Quality
        getQualitySnags(jobId, status = '') {
            const query = status ? `&status=${status}` : '';
            return this.request(`/quality/snags?job_id=${jobId}${query}`);
        },
        createQualitySnag(data) {
            return this.request('/quality/snags', { method: 'POST', body: JSON.stringify(data) });
        },
        updateQualitySnag(id, data) {
            return this.request(`/quality/snags/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        getQualitySignoffs(jobId) {
            return this.request(`/quality/signoffs?job_id=${jobId}`);
        },
        createQualitySignoff(data) {
            return this.request('/quality/signoffs', { method: 'POST', body: JSON.stringify(data) });
        },

        // Change Orders
        getChangeOrders(jobId) {
            return this.request(`/change-orders?job_id=${jobId}`);
        },
        createChangeOrder(data) {
            return this.request('/change-orders', { method: 'POST', body: JSON.stringify(data) });
        },
        approveChangeOrder(id, data) {
            return this.request(`/change-orders/${id}/approve`, { method: 'POST', body: JSON.stringify(data) });
        },

        // Invoicing
        getInvoices(jobId) {
            return this.request(`/invoices?job_id=${jobId}`);
        },
        createInvoice(data) {
            return this.request('/invoices', { method: 'POST', body: JSON.stringify(data) });
        },
        recordPayment(invoiceId, data) {
            return this.request(`/invoices/${invoiceId}/payment`, { method: 'POST', body: JSON.stringify(data) });
        },
        getInvoiceSummary(jobId) {
            return this.request(`/invoices/summary?job_id=${jobId}`);
        },

        // Daily Reports
        getDailyReports(jobId) {
            return this.request(`/daily-reports?job_id=${jobId}`);
        },
        createDailyReport(data) {
            return this.request('/daily-reports', { method: 'POST', body: JSON.stringify(data) });
        },

        // Equipment
        getEquipment(jobId) {
            return this.request(`/equipment?job_id=${jobId}`);
        },
        createEquipment(data) {
            return this.request('/equipment', { method: 'POST', body: JSON.stringify(data) });
        },

        // Subcontractors
        getSubcontractors(jobId) {
            return this.request(`/subcontractors?job_id=${jobId}`);
        },
        createSubcontractor(data) {
            return this.request('/subcontractors', { method: 'POST', body: JSON.stringify(data) });
        },

        // Photos
        getPhotos(jobId, params = {}) {
            const query = new URLSearchParams({ job_id: jobId, ...params });
            return this.request(`/photos?${query}`);
        },
        deletePhoto(id) {
            return this.request(`/photos/${id}`, { method: 'DELETE' });
        },
        getPhotoGallery(jobId) {
            return this.request(`/photos/gallery?job_id=${jobId}`);
        },

        // RFI & Submittals
        getRFI(params) {
            // Support both simple jobId and object params
            if (typeof params === 'object' && params !== null) {
                const query = new URLSearchParams(params);
                return this.request(`/rfi?${query}`);
            }
            return this.request(`/rfi?job_id=${params}`);
        },
        createRFI(data) {
            return this.request('/rfi', { method: 'POST', body: JSON.stringify(data) });
        },
        respondRFI(id, data) {
            return this.request(`/rfi/${id}/respond`, { method: 'POST', body: JSON.stringify(data) });
        },
        updateRFI(id, data) {
            return this.request(`/rfi/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        addRFIComment(id, data) {
            return this.request(`/rfi/${id}/comment`, { method: 'POST', body: JSON.stringify(data) });
        },
        closeRFI(id) {
            return this.request(`/rfi/${id}/close`, { method: 'POST' });
        },
        getSingleRFI(id) {
            return this.request(`/rfi/${id}`);
        },
        getSubmittals(params) {
            // Support both simple jobId and object params
            if (typeof params === 'object' && params !== null) {
                const query = new URLSearchParams(params);
                return this.request(`/submittals?${query}`);
            }
            return this.request(`/submittals?job_id=${params}`);
        },
        createSubmittal(data) {
            return this.request('/submittals', { method: 'POST', body: JSON.stringify(data) });
        },
        reviewSubmittal(id, data) {
            return this.request(`/submittals/${id}/review`, { method: 'POST', body: JSON.stringify(data) });
        },
        resubmitSubmittal(id, data) {
            return this.request(`/submittals/${id}/resubmit`, { method: 'POST', body: JSON.stringify(data) });
        },
        getSingleSubmittal(id) {
            return this.request(`/submittals/${id}`);
        },
        deleteRFI(id) {
            return this.request(`/rfi/${id}`, { method: 'DELETE' });
        },
        deleteSubmittal(id) {
            return this.request(`/submittals/${id}`, { method: 'DELETE' });
        },
        bulkRFIAction(data) {
            return this.request('/rfi/bulk-action', { method: 'POST', body: JSON.stringify(data) });
        },
        bulkSubmittalAction(data) {
            return this.request('/submittals/bulk-action', { method: 'POST', body: JSON.stringify(data) });
        },
        getRFISubmittalsDashboard(params) {
            const query = new URLSearchParams(params);
            return this.request(`/dashboard/kpis?${query}`);
        },

        // Site Location / Map
        getSiteLocation(jobId) {
            return this.request(`/sites/${jobId}`);
        },
        updateSiteLocation(jobId, data) {
            return this.request(`/sites/${jobId}`, { method: 'PATCH', body: JSON.stringify(data) });
        },
        geocodeAddress(address) {
            return this.request('/sites/geocode', { method: 'POST', body: JSON.stringify({ address }) });
        },

        // Team / Employees
        getEmployees(jobId) {
            // If jobId provided, get job-specific employees; otherwise get all
            const query = jobId ? `?job_id=${jobId}` : '';
            return this.request(`/employees${query}`);
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
        getTeamAssignments(jobId) {
            return this.request(`/team?job_id=${jobId}`);
        },
        assignTeamMember(data) {
            return this.request('/team', { method: 'POST', body: JSON.stringify(data) });
        },
        removeTeamMember(assignmentId) {
            return this.request(`/team/${assignmentId}`, { method: 'DELETE' });
        },
        getAvailableWorkers() {
            return this.request('/team/available');
        },

        // Weather
        getWeather(jobId, days = 7) {
            return this.request(`/weather?job_id=${jobId}&days=${days}`);
        },

        // Dashboard
        getJobSummary(jobId) {
            return this.request(`/dashboard/summary?job_id=${jobId}`);
        },
        getTimeline(jobId, limit = 50) {
            return this.request(`/dashboard/timeline?job_id=${jobId}&limit=${limit}`);
        },
        getKPIs(jobId) {
            return this.request(`/dashboard/kpis?job_id=${jobId}`);
        },

        // Client Details
        getClientDetails(jobId) {
            return this.request(`/clients/${jobId}`);
        },
        updateClientDetails(jobId, data) {
            return this.request(`/clients/${jobId}`, { method: 'PATCH', body: JSON.stringify(data) });
        }
    };

    // ============================================
    // CRM State Management
    // ============================================
    const CRM_State = {
        jobId: null,
        currentTab: 'overview',
        communications: [],
        documents: [],
        timesheets: [],
        safetyInspections: [],
        safetyIncidents: [],
        qualitySnags: [],
        changeOrders: [],
        invoices: [],
        dailyReports: [],
        equipment: [],
        subcontractors: [],
        photos: [],
        rfi: [],
        submittals: [],
        team: [],
        weather: [],
        siteLocation: null,
        clientDetails: null,

        init(jobId) {
            this.jobId = jobId;
            this.loadAllData();
        },

        async loadAllData() {
            try {
                const [summary, timeline, kpis] = await Promise.all([
                    CRM_API.getJobSummary(this.jobId),
                    CRM_API.getTimeline(this.jobId, 20),
                    CRM_API.getKPIs(this.jobId)
                ]);

                this.summary = summary;
                this.timeline = timeline;
                this.kpis = kpis;

                this.renderDashboard();
            } catch (error) {
                console.error('CRM: Failed to load dashboard data', error);
            }
        },

        async loadTabData(tabName) {
            switch(tabName) {
                case 'communications':
                    this.communications = await CRM_API.getCommunications(this.jobId);
                    this.renderCommunications();
                    break;
                case 'documents':
                    const [docs, photos] = await Promise.all([
                        CRM_API.getDocuments(this.jobId),
                        CRM_API.getPhotos(this.jobId)
                    ]);
                    this.documents = docs;
                    this.photos = photos;
                    this.renderDocuments();
                    break;
                case 'team':
                    const [employees, timesheets] = await Promise.all([
                        CRM_API.getEmployees(this.jobId), // Get job-specific employees
                        CRM_API.getTimesheets(this.jobId)
                    ]);
                    this.employees = employees || []; // Store job-specific employees
                    this.timesheets = timesheets;
                    this.renderTeam();
                    break;
                case 'safety':
                    // Initialize the new Safety Module
                    if (typeof SafetyModule !== 'undefined') {
                        SafetyModule.init();
                    } else {
                        console.error('[CRM] SafetyModule not available');
                        // Fallback to old implementation
                        const [inspections, incidents] = await Promise.all([
                            CRM_API.getSafetyInspections(this.jobId),
                            CRM_API.getSafetyIncidents(this.jobId)
                        ]);
                        this.safetyInspections = inspections;
                        this.safetyIncidents = incidents;
                        this.renderSafety();
                    }
                    break;
                case 'quality':
                    this.qualitySnags = await CRM_API.getQualitySnags(this.jobId);
                    this.renderQuality();
                    break;
                case 'invoicing':
                    const [invoices, summary] = await Promise.all([
                        CRM_API.getInvoices(this.jobId),
                        CRM_API.getInvoiceSummary(this.jobId)
                    ]);
                    this.invoices = invoices;
                    this.invoiceSummary = summary;
                    this.renderInvoicing();
                    break;
                case 'change-orders':
                    this.changeOrders = await CRM_API.getChangeOrders(this.jobId);
                    this.renderChangeOrders();
                    break;
                case 'rfi-submittals':
                    // Initialize the RFI_Submittals module with the jobId
                    // This module handles its own data loading and rendering to #rfi-table-body
                    if (typeof RFI_Submittals !== 'undefined') {
                        RFI_Submittals.init(this.jobId);
                    } else {
                        console.error('[CRM_State] RFI_Submittals module not loaded');
                    }
                    break;
                case 'daily-reports':
                    try {
                        const [reports, weather] = await Promise.all([
                            CRM_API.getDailyReports(this.jobId),
                            CRM_API.getWeather(this.jobId)
                        ]);
                        console.log('[Weather] Daily reports data:', { reports, weather });
                        this.dailyReports = reports;
                        // Handle both array response and error object response
                        this.weather = Array.isArray(weather) ? weather : (weather?.data || []);
                        if (weather?.error) {
                            console.warn('[Weather] API returned error:', weather.message);
                            this.weather = { error: true, message: weather.message, has_address: weather.has_address };
                        }
                        this.renderDailyReports();
                    } catch (err) {
                        console.error('[Weather] Failed to load daily reports data:', err);
                        this.dailyReports = [];
                        this.weather = { error: true, message: 'Failed to load weather data' };
                        this.renderDailyReports();
                    }
                    break;
                case 'equipment':
                    this.equipment = await CRM_API.getEquipment(this.jobId);
                    this.renderEquipment();
                    break;
                case 'subcontractors':
                    this.subcontractors = await CRM_API.getSubcontractors(this.jobId);
                    this.renderSubcontractors();
                    break;
                case 'site-map':
                    try {
                        const location = await CRM_API.getSiteLocation(this.jobId);
                        // location could be null (404) or an object with/without coordinates
                        this.siteLocation = location || {};
                    } catch (e) {
                        this.siteLocation = {};
                    }
                    this.renderSiteMap();
                    break;
                case 'client-details':
                    this.clientDetails = await CRM_API.getClientDetails(this.jobId);
                    this.renderClientDetails();
                    break;
            }
        },

        renderDashboard() {
            // Update KPI cards
            if (this.summary) {
                $(`.crm-kpi-communications`).text(this.summary.communications || 0);
                $(`.crm-kpi-documents`).text(this.summary.documents || 0);
                $(`.crm-kpi-open-snags`).text(this.summary.quality_snags || 0);
                $(`.crm-kpi-open-rfi`).text(this.summary.open_rfi || 0);
                $(`.crm-kpi-pending-co`).text(this.summary.pending_change_orders || 0);
                $(`.crm-kpi-outstanding`).text(this.formatCurrency(this.summary.outstanding_invoices || 0));
            }

            // Render timeline
            if (this.timeline && this.timeline.length > 0) {
                const html = this.timeline.map(item => this.renderTimelineItem(item)).join('');
                $('#crm-timeline-container').html(html);
            }
        },

        renderTimelineItem(item) {
            const icons = {
                communication: 'mail',
                document: 'file-text',
                timesheet: 'clock',
                safety_incident: 'alert-triangle',
                photo: 'camera',
                change_order: 'git-pull-request'
            };

            const icon = icons[item.type] || 'activity';
            const date = new Date(item.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

            return `
                <div class="crm-timeline-item" data-type="${item.type}">
                    <div class="crm-timeline-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${this.getIconPath(icon)}
                        </svg>
                    </div>
                    <div class="crm-timeline-content">
                        <div class="crm-timeline-title">${this.escapeHtml(item.title)}</div>
                        <div class="crm-timeline-meta">${date} · ${item.type.replace('_', ' ')}</div>
                    </div>
                    <span class="crm-timeline-status ${item.status}">${item.status}</span>
                </div>
            `;
        },

        renderCommunications() {
            const container = $('#crm-communications-list');
            if (!this.communications || this.communications.length === 0) {
                container.html('<div class="crm-empty-state">No communications yet. Send your first email.</div>');
                return;
            }

            const html = this.communications.map(comm => `
                <div class="crm-comm-item ${comm.direction}" data-id="${comm.id}">
                    <div class="crm-comm-header">
                        <div class="crm-comm-direction">
                            ${comm.direction === 'outbound' ? 
                                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>' :
                                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>'
                            }
                        </div>
                        <div class="crm-comm-info">
                            <div class="crm-comm-subject">${this.escapeHtml(comm.subject || 'No subject')}</div>
                            <div class="crm-comm-recipient">${this.escapeHtml(comm.recipient_name || comm.recipient_email || 'Unknown')}</div>
                        </div>
                        <div class="crm-comm-date">${new Date(comm.created_at).toLocaleDateString('en-GB')}</div>
                    </div>
                    <div class="crm-comm-preview">${this.escapeHtml(comm.content?.substring(0, 100) || '')}...</div>
                </div>
            `).join('');

            container.html(html);
        },

        renderDocuments() {
            const container = $('#crm-documents-view');
            const category = this.currentDocCategory || 'all';
            
            // Update category counts
            this.updateDocumentCounts();
            
            // Filter documents by category
            let docs = this.documents || [];
            if (category !== 'all') {
                docs = docs.filter(doc => (doc.category || 'general') === category);
            }
            
            // Also include photos in 'photos' and 'all' views
            if ((category === 'photos' || category === 'all') && this.photos) {
                const photoDocs = this.photos.map(photo => ({
                    ...photo,
                    category: 'photos',
                    isPhoto: true,
                    file_path: photo.file_url || photo.file_path,
                    title: photo.title || photo.file_name
                }));
                if (category === 'photos') {
                    docs = photoDocs;
                } else {
                    docs = [...docs, ...photoDocs];
                }
            }
            
            if (docs.length === 0) {
                container.html(this.renderEmptyState(category));
                return;
            }

            // Render based on category
            let html = '';
            switch(category) {
                case 'receipts':
                    html = this.renderReceiptsView(docs);
                    break;
                case 'site-plans':
                    html = this.renderSitePlansView(docs);
                    break;
                case 'photos':
                    html = this.renderPhotosView(docs);
                    break;
                case 'contracts':
                case 'reports':
                    html = this.renderDocCardsView(docs, category);
                    break;
                default:
                    html = this.renderAllDocumentsView(docs);
            }
            
            container.html(html);
        },

        updateDocumentCounts() {
            const counts = {
                all: (this.documents?.length || 0) + (this.photos?.length || 0),
                receipts: this.documents?.filter(d => d.category === 'receipts').length || 0,
                'site-plans': this.documents?.filter(d => d.category === 'site-plans').length || 0,
                photos: this.photos?.length || 0,
                contracts: this.documents?.filter(d => d.category === 'contracts').length || 0,
                reports: this.documents?.filter(d => d.category === 'reports').length || 0,
                general: this.documents?.filter(d => !d.category || d.category === 'general').length || 0
            };
            
            Object.keys(counts).forEach(cat => {
                $(`#doc-count-${cat}`).text(counts[cat]);
            });
        },

        renderEmptyState(category) {
            const titles = {
                all: 'No documents yet',
                receipts: 'No receipts uploaded',
                'site-plans': 'No site plans uploaded',
                photos: 'No photos uploaded',
                contracts: 'No contracts uploaded',
                reports: 'No reports uploaded',
                general: 'No documents uploaded'
            };
            
            const descriptions = {
                all: 'Upload documents, receipts, site plans, and photos to get started.',
                receipts: 'Upload receipts to track expenses and maintain financial records.',
                'site-plans': 'Upload architectural drawings, floor plans, and site layouts.',
                photos: 'Upload site photos to document progress and conditions.',
                contracts: 'Upload signed contracts, agreements, and legal documents.',
                reports: 'Upload inspection reports, assessments, and documentation.',
                general: 'Upload any additional documents related to this job.'
            };
            
            return `
                <div class="crm-empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <h4>${titles[category] || titles.general}</h4>
                    <p>${descriptions[category] || descriptions.general}</p>
                    <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('upload-document', CRM_State.currentDocCategory)">
                        Upload Document
                    </button>
                </div>
            `;
        },

        renderReceiptsView(docs) {
            const receipts = docs.filter(d => d.category === 'receipts' || d.file_type?.includes('receipt'));
            
            if (receipts.length === 0) {
                return this.renderEmptyState('receipts');
            }
            
            return `
                <div class="crm-doc-view-header">
                    <div>
                        <h2 class="crm-doc-view-title">Receipts & Expenses</h2>
                        <p class="crm-doc-view-desc">Track all job-related expenses and receipts</p>
                    </div>
                    <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('upload-document', 'receipts')">
                        Upload Receipt
                    </button>
                </div>
                <table class="crm-receipts-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${receipts.map(receipt => `
                            <tr data-id="${receipt.id}">
                                <td>
                                    <div class="crm-receipt-vendor">${this.escapeHtml(receipt.title || receipt.file_name)}</div>
                                </td>
                                <td>${this.escapeHtml(receipt.description || 'No description')}</td>
                                <td>
                                    <span class="crm-receipt-category">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                                        Receipt
                                    </span>
                                </td>
                                <td class="crm-receipt-date">${new Date(receipt.uploaded_at).toLocaleDateString('en-GB')}</td>
                                <td class="crm-receipt-amount">${receipt.amount ? '£' + parseFloat(receipt.amount).toFixed(2) : '-'}</td>
                                <td>
                                    <div class="crm-receipt-actions">
                                        <button class="crm-receipt-action" onclick="window.open('${this.getViewerUrl(receipt)}', '_blank')" title="View">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <button class="crm-receipt-action" onclick="CRM_App.downloadFile('${this.getFileUrl(receipt)}', '${this.escapeHtml(receipt.file_name)}')" title="Download">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        </button>
                                        <button class="crm-receipt-action" onclick="CRM_App.deleteDocument(${receipt.id}, 'receipts')" title="Delete">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        },

        getFileUrl(doc) {
            // file_path now contains the web URL (not local path)
            let url = doc.file_path || '';
            
            // Handle old local paths for backward compatibility
            if (url && url.startsWith('/home/')) {
                const wpContentMatch = url.match(/\/wp-content\/(.+)$/);
                if (wpContentMatch) {
                    url = '/wp-content/' + wpContentMatch[1];
                }
            }
            
            return url;
        },

        getViewerUrl(doc) {
            const fileUrl = this.getFileUrl(doc);
            const fileName = doc.file_name || '';
            const fileType = doc.file_type || '';
            
            // Office documents - use Google Docs viewer
            if (fileType.includes('word') || fileType.includes('excel') || fileType.includes('powerpoint') ||
                fileName.match(/\.(docx?|xlsx?|pptx?)$/i)) {
                return `https://docs.google.com/gview?url=${encodeURIComponent(fileUrl)}&embedded=true`;
            }
            
            // For PDFs, images, and other files - open directly (browser handles it)
            return fileUrl;
        },

        renderSitePlansView(docs) {
            const sitePlans = docs.filter(d => 
                d.category === 'site-plans' || 
                d.file_type?.includes('plan') ||
                d.file_type?.includes('drawing') ||
                d.file_type?.includes('pdf')
            );
            
            if (sitePlans.length === 0) {
                return this.renderEmptyState('site-plans');
            }
            
            return `
                <div class="crm-doc-view-header">
                    <div>
                        <h2 class="crm-doc-view-title">Site Plans & Drawings</h2>
                        <p class="crm-doc-view-desc">Architectural drawings, floor plans, and site layouts</p>
                    </div>
                    <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('upload-document', 'site-plans')">
                        Upload Plan
                    </button>
                </div>
                <div class="crm-site-plans-grid">
                    ${sitePlans.map(plan => `
                        <div class="crm-site-plan-card" data-id="${plan.id}">
                            <div class="crm-site-plan-preview" onclick="window.open('${this.getViewerUrl(plan)}', '_blank')">
                                ${plan.file_type?.startsWith('image/') 
                                    ? `<img src="${this.getFileUrl(plan)}" alt="${this.escapeHtml(plan.title || plan.file_name)}" 
                                         onerror="this.src='${this.getPlaceholderForType(plan.file_type)}'" />`
                                    : `<div class="crm-file-placeholder" style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f1f5f9;">
                                        <div style="text-align: center; color: #64748b;">
                                            ${this.getFileIcon(plan.file_type)}
                                            <p style="margin: 8px 0 0; font-size: 12px;">${plan.file_type?.split('/')[1]?.toUpperCase() || 'FILE'}</p>
                                        </div>
                                    </div>`
                                }
                                <div class="crm-site-plan-overlay">
                                    <div class="crm-site-plan-overlay-actions">
                                        <button class="crm-site-plan-overlay-btn primary" onclick="event.stopPropagation(); window.open('${this.getViewerUrl(plan)}', '_blank')">View</button>
                                        <button class="crm-site-plan-overlay-btn secondary" onclick="event.stopPropagation(); CRM_App.downloadFile('${this.getFileUrl(plan)}', '${this.escapeHtml(plan.file_name)}')">Download</button>
                                        <button class="crm-site-plan-overlay-btn danger" onclick="event.stopPropagation(); CRM_App.deleteDocument(${plan.id}, 'site-plans')" title="Delete">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="crm-site-plan-info">
                                <h4 class="crm-site-plan-title">${this.escapeHtml(plan.title || plan.file_name)}</h4>
                                <div class="crm-site-plan-meta">
                                    <span>${new Date(plan.uploaded_at).toLocaleDateString('en-GB')}</span>
                                    <span class="crm-site-plan-version">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        v${plan.version || '1.0'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        },

        renderPhotosView(docs) {
            const photos = docs.filter(d => d.isPhoto || d.category === 'photos' || d.file_type?.startsWith('image/'));
            
            if (photos.length === 0) {
                return this.renderEmptyState('photos');
            }
            
            return `
                <div class="crm-doc-view-header">
                    <div>
                        <h2 class="crm-doc-view-title">Site Photos</h2>
                        <p class="crm-doc-view-desc">Document progress, conditions, and milestones</p>
                    </div>
                    <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('upload-document', 'photos')">
                        Upload Photo
                    </button>
                </div>
                <div class="crm-photos-grid">
                    ${photos.map(photo => `
                        <div class="crm-photo-card" data-id="${photo.id}" data-is-photo="true" onclick="window.open('${this.getViewerUrl(photo)}', '_blank')">
                            <img src="${this.getFileUrl(photo)}" alt="${this.escapeHtml(photo.title || 'Site photo')}" 
                                 onerror="this.src='${this.getPlaceholderForType('image')}'" />
                            <div class="crm-photo-overlay">
                                <div class="crm-photo-date">${new Date(photo.uploaded_at || photo.taken_at).toLocaleDateString('en-GB')}</div>
                                <div class="crm-photo-actions">
                                    <button class="crm-photo-action" onclick="event.stopPropagation(); window.open('${this.getViewerUrl(photo)}', '_blank')" title="View">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button class="crm-photo-action" onclick="event.stopPropagation(); CRM_App.downloadFile('${this.getFileUrl(photo)}', '${this.escapeHtml(photo.file_name)}')" title="Download">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    </button>
                                    <button class="crm-photo-action" onclick="event.stopPropagation(); CRM_App.deletePhoto(${photo.id})" title="Delete">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        },

        renderDocCardsView(docs, category) {
            const filtered = docs.filter(d => d.category === category);
            
            if (filtered.length === 0) {
                return this.renderEmptyState(category);
            }
            
            const titles = {
                contracts: 'Contracts & Agreements',
                reports: 'Reports & Assessments'
            };
            
            const descriptions = {
                contracts: 'Signed contracts, agreements, and legal documentation',
                reports: 'Inspection reports, assessments, and project documentation'
            };
            
            return `
                <div class="crm-doc-view-header">
                    <h2 class="crm-doc-view-title">${titles[category]}</h2>
                    <p class="crm-doc-view-desc">${descriptions[category]}</p>
                </div>
                <div class="crm-doc-cards-grid">
                    ${filtered.map(doc => `
                        <div class="crm-doc-card" data-id="${doc.id}">
                            <div class="crm-doc-card-header">
                                <div class="crm-doc-card-icon ${this.getFileIconClass(doc.file_type)}">
                                    ${this.getFileIcon(doc.file_type)}
                                </div>
                                <div>
                                    <h4 class="crm-doc-card-title">${this.escapeHtml(doc.title || doc.file_name)}</h4>
                                    <div class="crm-doc-card-meta">${this.formatFileSize(doc.file_size)} · ${new Date(doc.uploaded_at).toLocaleDateString('en-GB')}</div>
                                </div>
                            </div>
                            <div class="crm-doc-card-footer">
                                <span class="crm-doc-card-status signed">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    Active
                                </span>
                                <div class="crm-doc-card-actions">
                                    <button class="crm-document-action" onclick="event.stopPropagation(); window.open('${this.getViewerUrl(doc)}', '_blank')" title="View">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button class="crm-document-action" onclick="event.stopPropagation(); CRM_App.downloadFile('${this.getFileUrl(doc)}', '${this.escapeHtml(doc.file_name)}')" title="Download">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    </button>
                                    <button class="crm-document-action" onclick="event.stopPropagation(); CRM_App.deleteDocument(${doc.id}, '${category}')" title="Delete">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        },

        renderAllDocumentsView(docs) {
            // Group by category for the "All" view
            const categories = {};
            docs.forEach(doc => {
                const cat = doc.category || 'general';
                if (!categories[cat]) categories[cat] = [];
                categories[cat].push(doc);
            });
            
            return `
                <div class="crm-doc-view-header">
                    <div>
                        <h2 class="crm-doc-view-title">All Documents</h2>
                        <p class="crm-doc-view-desc">${docs.length} files across all categories</p>
                    </div>
                    <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('upload-document', 'all')">
                        Upload Document
                    </button>
                </div>
                <div class="crm-documents-list">
                    ${docs.slice(0, 20).map(doc => `
                        <div class="crm-document-item" data-id="${doc.id}" onclick="window.open('${this.getViewerUrl(doc)}', '_blank')">
                            <div class="crm-document-icon ${this.getFileIconClass(doc.file_type)}">
                                ${this.getFileIcon(doc.file_type)}
                            </div>
                            <div class="crm-document-info">
                                <div class="crm-document-name">${this.escapeHtml(doc.title || doc.file_name)}</div>
                                <div class="crm-document-meta">
                                    <span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        ${new Date(doc.uploaded_at).toLocaleDateString('en-GB')}
                                    </span>
                                    <span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        ${this.formatFileSize(doc.file_size)}
                                    </span>
                                    <span>${doc.category || 'General'}</span>
                                </div>
                            </div>
                            <div class="crm-document-actions">
                                <button class="crm-document-action" onclick="event.stopPropagation(); window.open('${this.getViewerUrl(doc)}', '_blank')" title="View">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="crm-document-action" onclick="event.stopPropagation(); CRM_App.downloadFile('${this.getFileUrl(doc)}', '${this.escapeHtml(doc.file_name)}')" title="Download">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                </button>
                                <button class="crm-document-action" onclick="event.stopPropagation(); CRM_App.deleteDocument(${doc.id}, 'all')" title="Delete">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                    ${docs.length > 20 ? `<div style="text-align: center; padding: 20px; color: #64748b;">And ${docs.length - 20} more documents...</div>` : ''}
                </div>
            `;
        },

        getFileIconClass(fileType) {
            if (!fileType) return '';
            if (fileType.includes('pdf')) return 'pdf';
            if (fileType.includes('word') || fileType.includes('document')) return 'word';
            if (fileType.includes('excel') || fileType.includes('sheet')) return 'excel';
            if (fileType.startsWith('image/')) return 'image';
            if (fileType.includes('zip') || fileType.includes('compressed')) return 'zip';
            return '';
        },

        getFileIcon(fileType) {
            const icons = {
                pdf: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                word: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                excel: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                image: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
                zip: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>'
            };
            
            if (fileType?.includes('pdf')) return icons.pdf;
            if (fileType?.includes('word') || fileType?.includes('document')) return icons.word;
            if (fileType?.includes('excel') || fileType?.includes('sheet')) return icons.excel;
            if (fileType?.startsWith('image/')) return icons.image;
            if (fileType?.includes('zip') || fileType?.includes('compressed')) return icons.zip;
            return icons.pdf;
        },

        getPlaceholderForType(fileType) {
            if (fileType?.startsWith('image/')) {
                return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="%23f1f5f9" width="100" height="100"/><text fill="%2394a3b8" x="50" y="50" text-anchor="middle" font-size="12">Image</text></svg>';
            }
            return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="%23f1f5f9" width="100" height="100"/><text fill="%2394a3b8" x="50" y="50" text-anchor="middle" font-size="12">Document</text></svg>';
        },

        previewDocument(docId, isPhoto = false) {
            let doc;
            if (isPhoto) {
                doc = this.photos?.find(p => p.id === docId);
            } else {
                doc = this.documents?.find(d => d.id === docId) || this.photos?.find(p => p.id === docId);
            }
            if (!doc) return;
            
            // Open file in new tab for full preview
            const fileUrl = this.getFileUrl(doc);
            if (fileUrl) {
                window.open(fileUrl, '_blank');
            }
        },

        async deleteDocument(docId, category) {
            if (!confirm('Are you sure you want to delete this document?')) return;
            
            // INSTANT UI REMOVAL - Remove from DOM immediately (no animation)
            $(`[data-id="${docId}"]`).remove();
            
            // Also remove from local array immediately
            const deletedDoc = this.documents.find(d => d.id === docId);
            this.documents = this.documents.filter(d => d.id !== docId);
            
            // INSTANT RE-RENDER - Update the view immediately
            this.renderDocuments();
            this.updateDocumentCounts();
            
            try {
                await CRM_API.deleteDocument(docId);
                showToast('Document deleted and removed from server');
            } catch (err) {
                // Restore on failure
                if (deletedDoc) {
                    this.documents.push(deletedDoc);
                }
                this.renderDocuments();
                this.updateDocumentCounts();
                showToast('Failed to delete: ' + err.message, 'error');
            }
        },

        downloadFile(url, filename) {
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = filename || 'download';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        async deletePhoto(photoId) {
            if (!confirm('Are you sure you want to delete this photo?')) return;
            
            // INSTANT UI REMOVAL - Remove from DOM immediately (no animation)
            $(`[data-id="${photoId}"][data-is-photo="true"]`).remove();
            
            // Also remove from local array immediately
            const deletedPhoto = this.photos.find(p => p.id === photoId);
            this.photos = this.photos.filter(p => p.id !== photoId);
            
            // INSTANT RE-RENDER - Update the view immediately
            this.renderDocuments();
            this.updateDocumentCounts();
            
            try {
                await CRM_API.deletePhoto(photoId);
                showToast('Photo deleted and removed from server');
            } catch (err) {
                // Restore on failure
                if (deletedPhoto) {
                    this.photos.push(deletedPhoto);
                }
                this.renderDocuments();
                this.updateDocumentCounts();
                showToast('Failed to delete: ' + err.message, 'error');
            }
        },

        renderTeam() {
            // Job-specific employees
            const teamContainer = $('#crm-team-list');
            if (!this.employees || this.employees.length === 0) {
                teamContainer.html('<div class="crm-empty-state">No employees for this job yet. Click "Add Team Member" to create one.</div>');
            } else {
                const html = this.employees.map(emp => `
                    <div class="crm-team-member ${emp.is_lead ? 'is-lead' : ''}" data-id="${emp.id}">
                        <div class="crm-team-avatar">${(emp.first_name || 'U').charAt(0).toUpperCase()}${(emp.last_name || '').charAt(0).toUpperCase()}</div>
                        <div class="crm-team-info">
                            <div class="crm-team-name">${this.escapeHtml(emp.first_name + ' ' + emp.last_name)}</div>
                            <div class="crm-team-role">${this.escapeHtml(emp.role)}${emp.trade ? ` · ${emp.trade}` : ''}</div>
                        </div>
                        <div class="crm-team-meta">
                            <span class="crm-team-rate">£${emp.hourly_rate || 0}/hr</span>
                            ${emp.is_lead ? '<span class="crm-team-lead-badge">Lead</span>' : ''}
                        </div>
                        <button class="crm-team-remove" onclick="CRM_State.deleteJobEmployee(${emp.id}, '${this.escapeHtml(emp.first_name + ' ' + emp.last_name)}')" title="Delete from job and system">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                `).join('');
                teamContainer.html(html);
            }

            // Timesheets
            const timesheetContainer = $('#crm-timesheet-list');
            if (!this.timesheets || this.timesheets.length === 0) {
                timesheetContainer.html('<div class="crm-empty-state">No timesheet entries yet.</div>');
            } else {
                const html = this.timesheets.slice(0, 10).map(ts => `
                    <div class="crm-timesheet-row ${ts.status}" data-id="${ts.id}">
                        <div class="crm-ts-date">${new Date(ts.work_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</div>
                        <div class="crm-ts-worker">${this.escapeHtml(ts.worker_name)}</div>
                        <div class="crm-ts-hours">${ts.total_hours} hrs</div>
                        <div class="crm-ts-status">${ts.status}</div>
                    </div>
                `).join('');
                timesheetContainer.html(html);
            }

            // Summary
            if (this.timesheets) {
                const totalHours = this.timesheets.reduce((sum, ts) => sum + parseFloat(ts.total_hours || 0), 0);
                const uniqueWorkers = new Set(this.timesheets.map(ts => ts.worker_id)).size;
                $('#crm-ts-total-hours').text(totalHours.toFixed(1));
                $('#crm-ts-unique-workers').text(uniqueWorkers);
            }
        },

        // Delete job-specific employee (hard delete like global page)
        deleteJobEmployee(employeeId, name) {
            if (!confirm(`WARNING: Permanently DELETE ${name}?\n\nThis will remove them completely from the system.`)) return;
            
            CRM_API.deleteEmployee(employeeId).then(() => {
                showToast(`${name} deleted`);
                this.loadTabData('team');
            }).catch(err => {
                console.error('Failed to delete employee:', err);
                showToast('Failed to delete employee', 'error');
            });
        },

        renderSafety() {
            // Safety incidents
            const incidentsContainer = $('#crm-safety-incidents-list');
            if (!this.safetyIncidents || this.safetyIncidents.length === 0) {
                incidentsContainer.html('<div class="crm-empty-state">No safety incidents reported. Great job!</div>');
            } else {
                const html = this.safetyIncidents.map(incident => `
                    <div class="crm-safety-incident ${incident.severity} ${incident.status}" data-id="${incident.id}">
                        <div class="crm-si-header">
                            <span class="crm-si-severity">${incident.severity}</span>
                            <span class="crm-si-status">${incident.status}</span>
                            <span class="crm-si-date">${new Date(incident.incident_date).toLocaleDateString('en-GB')}</span>
                        </div>
                        <div class="crm-si-type">${this.escapeHtml(incident.incident_type)}</div>
                        <div class="crm-si-desc">${this.escapeHtml(incident.description?.substring(0, 100) || '')}</div>
                        ${incident.rIDDOR_reportable ? '<span class="crm-si-riddor">RIDDOR Reportable</span>' : ''}
                    </div>
                `).join('');
                incidentsContainer.html(html);
            }

            // Inspections
            const inspectionsContainer = $('#crm-safety-inspections-list');
            if (!this.safetyInspections || this.safetyInspections.length === 0) {
                inspectionsContainer.html('<div class="crm-empty-state">No inspections recorded.</div>');
            } else {
                const html = this.safetyInspections.map(insp => `
                    <div class="crm-inspection-item ${insp.status}" data-id="${insp.id}">
                        <div class="crm-insp-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                        </div>
                        <div class="crm-insp-info">
                            <div class="crm-insp-title">${this.escapeHtml(insp.title)}</div>
                            <div class="crm-insp-meta">${insp.inspection_type} · ${new Date(insp.inspection_date).toLocaleDateString('en-GB')}</div>
                        </div>
                        <div class="crm-insp-score">${insp.score || '-'}/100</div>
                    </div>
                `).join('');
                inspectionsContainer.html(html);
            }

            // Update stats
            const openIncidents = (this.safetyIncidents || []).filter(i => i.status === 'open').length;
            const highRisk = (this.safetyIncidents || []).filter(i => i.severity === 'major' || i.severity === 'critical').length;
            $('#crm-safety-open').text(openIncidents);
            $('#crm-safety-high-risk').text(highRisk);
            $('#crm-safety-days-since').text('0'); // Would calculate from last incident date
        },

        renderQuality() {
            const container = $('#crm-quality-snags-list');
            if (!this.qualitySnags || this.qualitySnags.length === 0) {
                container.html('<div class="crm-empty-state">No quality snags. Excellent work!</div>');
                return;
            }

            const html = this.qualitySnags.map(snag => `
                <div class="crm-snag-item ${snag.status} ${snag.priority}" data-id="${snag.id}">
                    <div class="crm-snag-header">
                        <span class="crm-snag-priority">${snag.priority}</span>
                        <span class="crm-snag-status">${snag.status}</span>
                    </div>
                    <div class="crm-snag-title">${this.escapeHtml(snag.title)}</div>
                    <div class="crm-snag-location">${this.escapeHtml(snag.location || 'No location')}</div>
                    <div class="crm-snag-meta">
                        <span>Identified: ${new Date(snag.identified_date).toLocaleDateString('en-GB')}</span>
                        ${snag.due_date ? `<span>Due: ${new Date(snag.due_date).toLocaleDateString('en-GB')}</span>` : ''}
                        ${snag.cost_estimate ? `<span>Est: £${snag.cost_estimate}</span>` : ''}
                    </div>
                </div>
            `).join('');

            container.html(html);

            // Update stats
            const open = (this.qualitySnags || []).filter(s => s.status === 'open').length;
            const highPriority = (this.qualitySnags || []).filter(s => s.priority === 'high' && s.status === 'open').length;
            $('#crm-quality-open').text(open);
            $('#crm-quality-high').text(highPriority);
        },

        renderChangeOrders() {
            const container = $('#crm-change-orders-list');
            if (!this.changeOrders || this.changeOrders.length === 0) {
                container.html('<div class="crm-empty-state">No change orders yet.</div>');
                return;
            }

            const html = this.changeOrders.map(co => `
                <div class="crm-co-item ${co.status}" data-id="${co.id}">
                    <div class="crm-co-header">
                        <span class="crm-co-number">${co.co_number}</span>
                        <span class="crm-co-status">${co.status}</span>
                    </div>
                    <div class="crm-co-title">${this.escapeHtml(co.title)}</div>
                    <div class="crm-co-impacts">
                        ${co.cost_impact ? `<span class="crm-co-cost ${co.cost_impact > 0 ? 'positive' : 'negative'}">£${co.cost_impact > 0 ? '+' : ''}${co.cost_impact}</span>` : ''}
                        ${co.schedule_impact_days ? `<span class="crm-co-schedule">${co.schedule_impact_days > 0 ? '+' : ''}${co.schedule_impact_days} days</span>` : ''}
                    </div>
                    <div class="crm-co-meta">
                        <span>Requested by ${this.escapeHtml(co.requested_by)}</span>
                        <span>${new Date(co.request_date).toLocaleDateString('en-GB')}</span>
                    </div>
                </div>
            `).join('');

            container.html(html);
        },

        renderInvoicing() {
            const container = $('#crm-invoices-list');
            if (!this.invoices || this.invoices.length === 0) {
                container.html('<div class="crm-empty-state">No invoices created yet.</div>');
                return;
            }

            const html = this.invoices.map(inv => `
                <div class="crm-invoice-item ${inv.status}" data-id="${inv.id}">
                    <div class="crm-inv-header">
                        <span class="crm-inv-number">${inv.invoice_number}</span>
                        <span class="crm-inv-status">${inv.status}</span>
                    </div>
                    <div class="crm-inv-amount">${this.formatCurrency(inv.total_amount)}</div>
                    <div class="crm-inv-meta">
                        <span>Due: ${new Date(inv.due_date).toLocaleDateString('en-GB')}</span>
                        ${inv.paid_amount > 0 ? `<span>Paid: ${this.formatCurrency(inv.paid_amount)}</span>` : ''}
                    </div>
                    ${inv.retention_amount > 0 ? `<div class="crm-inv-retention">Retention: ${this.formatCurrency(inv.retention_amount)}</div>` : ''}
                </div>
            `).join('');

            container.html(html);

            // Summary
            if (this.invoiceSummary) {
                $('#crm-inv-total-billed').text(this.formatCurrency(this.invoiceSummary.total_billed || 0));
                $('#crm-inv-total-paid').text(this.formatCurrency(this.invoiceSummary.total_paid || 0));
                $('#crm-inv-outstanding').text(this.formatCurrency(this.invoiceSummary.total_outstanding || 0));
                $('#crm-inv-retention').text(this.formatCurrency(this.invoiceSummary.total_retention || 0));
            }
        },

        renderRFISubmittals() {
            // RFI
            const rfiContainer = $('#crm-rfi-list');
            if (!this.rfi || this.rfi.length === 0) {
                rfiContainer.html('<div class="crm-empty-state">No RFIs raised.</div>');
            } else {
                const html = this.rfi.map(r => `
                    <div class="crm-rfi-item ${r.status} ${r.ball_in_court === 'consultant' ? 'with-consultant' : 'with-contractor'}" data-id="${r.id}">
                        <div class="crm-rfi-header">
                            <span class="crm-rfi-number">${r.rfi_number}</span>
                            <span class="crm-rfi-bic">${r.ball_in_court}</span>
                        </div>
                        <div class="crm-rfi-title">${this.escapeHtml(r.title)}</div>
                        <div class="crm-rfi-meta">
                            <span>${r.status}</span>
                            ${r.due_date ? `<span>Due: ${new Date(r.due_date).toLocaleDateString('en-GB')}</span>` : ''}
                        </div>
                    </div>
                `).join('');
                rfiContainer.html(html);
            }

            // Submittals
            const subContainer = $('#crm-submittals-list');
            if (!this.submittals || this.submittals.length === 0) {
                subContainer.html('<div class="crm-empty-state">No submittals submitted.</div>');
            } else {
                const html = this.submittals.map(s => `
                    <div class="crm-submittal-item ${s.status} ${s.ball_in_court === 'architect' ? 'with-architect' : 'with-contractor'}" data-id="${s.id}">
                        <div class="crm-sub-header">
                            <span class="crm-sub-number">${s.submittal_number}</span>
                            <span class="crm-sub-bic">${s.ball_in_court}</span>
                        </div>
                        <div class="crm-sub-title">${this.escapeHtml(s.title)}</div>
                        <div class="crm-sub-meta">
                            <span>${s.submittal_type}</span>
                            <span>${s.status}</span>
                        </div>
                    </div>
                `).join('');
                subContainer.html(html);
            }
        },

        renderDailyReports() {
            const container = $('#crm-daily-reports-list');

            // Render daily reports list
            if (!this.dailyReports || this.dailyReports.length === 0) {
                container.html('<div class="crm-empty-state">No daily reports submitted.</div>');
            } else {
                const html = this.dailyReports.map(report => `
                    <div class="crm-daily-report-item" data-id="${report.id}">
                        <div class="crm-dr-date">
                            <div class="crm-dr-day">${new Date(report.report_date).getDate()}</div>
                            <div class="crm-dr-month">${new Date(report.report_date).toLocaleDateString('en-GB', { month: 'short' })}</div>
                        </div>
                        <div class="crm-dr-content">
                            <div class="crm-dr-weather">${report.weather_conditions || 'No weather data'}</div>
                            <div class="crm-dr-workforce">${report.workforce_count} workers on site</div>
                            ${report.delays ? `<div class="crm-dr-delays">Delays: ${this.escapeHtml(report.delays)}</div>` : ''}
                        </div>
                    </div>
                `).join('');
                container.html(html);
            }

            // Render weather widget
            this.renderWeatherWidget();
        },

        renderWeatherWidget() {
            const loadingEl = $('#crm-weather-loading');
            const dataEl = $('#crm-weather-data');
            const errorEl = $('#crm-weather-error');

            console.log('[Weather] renderWeatherWidget called with:', this.weather);

            // Check if weather data is an error response
            if (this.weather && this.weather.error) {
                console.log('[Weather] Showing error state:', this.weather.message);
                loadingEl.hide();
                dataEl.hide();
                errorEl.show();
                $('#crm-weather-error-message').text(this.weather.message || 'Unable to load weather data');
                return;
            }

            // Check if we have weather data (must be a non-empty array)
            if (!this.weather || !Array.isArray(this.weather) || this.weather.length === 0) {
                console.log('[Weather] No data available, showing error state');
                loadingEl.hide();
                dataEl.hide();
                errorEl.show();
                $('#crm-weather-error-message').text('No weather data available. Set a site location to get weather forecasts.');
                return;
            }

            // We have data - show it
            console.log('[Weather] Showing weather data:', this.weather[0]);
            loadingEl.hide();
            errorEl.hide();
            dataEl.show();

            const today = this.weather[0];

            // Weather icon based on condition (40px = 20% smaller than 48px)
            const iconSvg = this.getWeatherIcon(today.weather_code, today.condition_text, 40);
            $('#crm-weather-icon').html(iconSvg);

            // Temperature (parse as numbers since DB returns strings)
            const tempHigh = parseFloat(today.temperature_high) || 0;
            const tempLow = parseFloat(today.temperature_low) || 0;
            $('#crm-weather-temp').text(`${Math.round(tempHigh)}° / ${Math.round(tempLow)}°`);

            // Condition text
            $('#crm-weather-condition').text(today.condition_text);

            // Wind and precipitation (parse as numbers since DB returns strings)
            const windSpeed = parseFloat(today.wind_speed) || 0;
            const precipAmount = parseFloat(today.precipitation_amount) || 0;
            $('#crm-weather-wind').text(`${Math.round(windSpeed)} km/h`);
            $('#crm-weather-precip').text(`${precipAmount.toFixed(1)} mm`);

            // Work recommendation
            $('#crm-weather-recommendation').text(today.work_recommendation);

            // Impact score with color (parse as number)
            const impact = parseInt(today.work_impact_score) || 5;
            const impactColor = impact >= 7 ? '#22c55e' : (impact >= 5 ? '#f59e0b' : '#ef4444');
            $('#crm-weather-impact').css({
                'background': impactColor,
                'color': '#fff'
            }).text(`Work Impact: ${impact}/10`);

            // 3-day forecast (parse temperatures as numbers)
            const forecastHtml = this.weather.slice(1, 4).map(day => {
                const date = new Date(day.forecast_date);
                const dayName = date.toLocaleDateString('en-GB', { weekday: 'short' });
                const icon = this.getWeatherIcon(day.weather_code, day.condition_text, 16);
                const tempHigh = parseFloat(day.temperature_high) || 0;
                return `
                    <div class="crm-forecast-day">
                        <span class="crm-forecast-name">${dayName}</span>
                        <span class="crm-forecast-icon">${icon}</span>
                        <span class="crm-forecast-temp">${Math.round(tempHigh)}°</span>
                    </div>
                `;
            }).join('');
            $('#crm-weather-forecast').html(forecastHtml);
        },

        getWeatherIcon(code, condition, size = 48) {
            // Embedded Weather Icons - Professional SVG icons for all weather conditions
            const WeatherIcons = {
                sunny: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19.566 5.163a6.746 6.746 0 0 1 8.872 0 9.746 9.746 0 0 0 5.747 2.38 6.747 6.747 0 0 1 6.273 6.274 9.746 9.746 0 0 0 2.38 5.747 6.746 6.746 0 0 1 0 8.871 9.746 9.746 0 0 0-2.38 5.747 6.747 6.747 0 0 1-6.273 6.274 9.746 9.746 0 0 0-5.748 2.38 6.746 6.746 0 0 1-8.87 0 9.746 9.746 0 0 0-5.748-2.38 6.747 6.747 0 0 1-6.273-6.274 9.746 9.746 0 0 0-2.381-5.747 6.746 6.746 0 0 1 0-8.871 9.746 9.746 0 0 0 2.38-5.747 6.747 6.747 0 0 1 6.274-6.274 9.746 9.746 0 0 0 5.747-2.38z" fill="#FCBD00" stroke="#DA4F00" stroke-width="3"/></svg>',
                heavy_rain: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.514 2.005C39.02 2.208 46 8.762 46 16.999l-.008.473-.022.454a14.187 14.187 0 0 1-.536 3.05l-2.54-2.453c.05-.367.085-.74.098-1.116l.007-.436c-.016-6.409-5.492-11.788-12.534-11.967L30.093 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.25 0-7.598 2.97-8.081 6.629l-2.885 2.788a9.574 9.574 0 0 1-.12-.8l-.042-.524-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path d="M20.403 36.433l.036.034a5.479 5.479 0 0 1 0 7.898c-2.242 2.18-5.876 2.18-8.117 0a5.479 5.479 0 0 1 0-7.898l4.058-3.948 4.023 3.914zm15.305 0l.035.034a5.478 5.478 0 0 1 0 7.898c-2.241 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.898l4.057-3.948 4.024 3.914zM11.794 25.84l.036.035a5.479 5.479 0 0 1 0 7.897c-2.241 2.18-5.874 2.18-8.115 0a5.479 5.479 0 0 1-.001-7.897l4.057-3.95 4.023 3.915zm16.261-1.925l.036.034a5.479 5.479 0 0 1 0 7.897c-2.241 2.18-5.875 2.18-8.117 0a5.479 5.479 0 0 1 0-7.897L24.032 20l4.023 3.915zm16.261-.001l.036.035a5.478 5.478 0 0 1 0 7.897c-2.241 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.897L40.292 20l4.023 3.914z" fill="#0B57D0"/></svg>',
                partly_cloudy: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M23.55 3.315a8.246 8.246 0 0 1 10.524 2.612 8.246 8.246 0 0 0 4.235 3.126 8.246 8.246 0 0 1 5.595 9.29 8.244 8.244 0 0 0 .069 3.222c-2.96-2.758-6.962-4.456-11.276-4.56h-.037L32.31 17h-.035c-5.982 0-11.353 3.032-14.32 7.728l-.332-.004c-6.189 0-11.693 4.406-12.517 10.538A8.226 8.226 0 0 1 4.1 29.657a8.247 8.247 0 0 0-.784-5.206 8.246 8.246 0 0 1 2.612-10.524 8.246 8.246 0 0 0 3.127-4.234 8.246 8.246 0 0 1 9.289-5.595 8.246 8.246 0 0 0 5.205-.783z" fill="#FCBD00"/><path d="M23.55 3.315a8.246 8.246 0 0 1 10.524 2.612 8.245 8.245 0 0 0 4.235 3.126 8.246 8.246 0 0 1 5.595 9.29 8.25 8.25 0 0 0 .069 3.223 16.827 16.827 0 0 0-3.183-2.331c.023-.473.076-.946.16-1.416a5.246 5.246 0 0 0-3.56-5.91 11.249 11.249 0 0 1-5.774-4.264 5.246 5.246 0 0 0-6.696-1.661 11.247 11.247 0 0 1-7.098 1.068 5.246 5.246 0 0 0-5.91 3.56 11.247 11.247 0 0 1-4.264 5.774 5.246 5.246 0 0 0-1.661 6.695 11.248 11.248 0 0 1 1.068 7.099l-.007.041a11.661 11.661 0 0 0-1.942 5.041A8.226 8.226 0 0 1 4.1 29.657a8.246 8.246 0 0 0-.636-4.902l-.147-.304a8.246 8.246 0 0 1 2.301-10.296l.311-.228a8.247 8.247 0 0 0 3.126-4.234 8.246 8.246 0 0 1 9.289-5.595 8.246 8.246 0 0 0 4.901-.636l.304-.147z" fill="#DA4F00"/><path d="M32.59 21.503c6.62.16 11.91 5.258 11.91 11.496l-.007.378c-.126 3.684-2.098 6.937-5.08 8.957a12.552 12.552 0 0 1-4.373 1.864c-2.867.347-5.707.3-8.754.3h-8.663c-4.22-.001-7.676-3.027-8.083-6.867l-.03-.374-.01-.397c0-4.202 3.623-7.636 8.123-7.636.588 0 1.162.059 1.714.17l1.177.239.49-1.096c1.848-4.128 6.198-7.034 11.264-7.037l.322.003z" stroke="#70757A" stroke-width="3"/></svg>',
                cloudy: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.1 11.5l.396.004c7.655.191 13.808 5.983 14 13.152L44.5 25l-.008.44c-.148 4.33-2.474 8.15-5.984 10.521a14.802 14.802 0 0 1-5.15 2.188c-3.345.404-6.656.35-10.184.35H13.143c-5.002-.001-9.11-3.575-9.595-8.13l-.035-.444-.013-.469c0-4.83 4.045-8.788 9.146-9.031l.497-.012c.697 0 1.377.07 2.032.202l1.175.237.49-1.092c2.177-4.848 7.3-8.256 13.26-8.259z" stroke="#70757A" stroke-width="3"/></svg>',
                drizzle: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M35.707 36.433l.035.034a5.479 5.479 0 0 1 0 7.898c-2.24 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.898l4.058-3.948 4.023 3.914z" fill="#0B57D0"/><path d="M30.1 3.5l.396.004c7.655.191 13.808 5.983 14 13.152L44.5 17l-.008.44c-.148 4.33-2.474 8.15-5.984 10.521a14.802 14.802 0 0 1-5.15 2.188c-3.345.404-6.656.35-10.184.35H13.143c-5.002-.001-9.11-3.575-9.595-8.13l-.035-.444-.013-.469c0-4.83 4.045-8.788 9.146-9.031l.497-.012c.697 0 1.377.07 2.032.202l1.175.237.49-1.092c2.177-4.85 7.3-8.257 13.26-8.26z" stroke="#70757A" stroke-width="3"/></svg>',
                snow_showers: '<svg width="20" height="20" viewBox="0 0 49 49" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.598 2.86c8.338.2 15.208 6.502 15.476 14.51l-3.125-1.214c-.866-5.647-5.98-10.133-12.4-10.297l-.372-.004c-5.387.006-9.96 3.086-11.885 7.373l-.982 2.186-2.348-.474a8.726 8.726 0 0 0-1.291-.16l-.444-.012c-4.592 0-8.13 3.465-8.143 7.521l.012.425c.217 3.88 3.686 7.138 8.13 7.139h2.145l1.888 3h-4.032c-5.693 0-10.515-4.084-11.086-9.468l-.042-.525-.015-.55c0-5.905 5.077-10.542 11.143-10.542.797 0 1.576.08 2.329.231C17.985 6.59 23.66 2.856 30.19 2.855l.407.005z" fill="#70757A"/><path d="M37.339 37.787l3.045-1.622.246-.132.896 1.432-3.274 1.743 3.274 1.747-.896 1.432-3.291-1.755v3.516h-1.752v-3.517l-3.292 1.756-.15-.238-.746-1.194 3.274-1.747-3.274-1.743.895-1.432.248.132 3.045 1.623v-3.515h1.752v3.514zM26.32 27.4l3.989-2.127.84 1.344.171.273-3.971 2.114 3.971 2.119-1.011 1.615-3.989-2.127v4.261h-1.983v-4.26l-3.988 2.126-.84-1.343-.171-.272 3.969-2.117-3.686-1.965-.283-.151 1.01-1.617.248.132 3.74 1.994v-4.26h1.984v4.26zm14.844-3.71l3.988-2.127.841 1.344.171.273-3.972 2.114 3.972 2.12-1.012 1.614-3.988-2.127v4.261h-1.983v-4.26l-3.989 2.126-.84-1.342-.17-.273 3.968-2.117-3.685-1.965-.283-.151 1.01-1.616.248.131 3.74 1.994v-4.26h1.984v4.26z" fill="#303134"/></svg>',
                heavy_snow: '<svg width="20" height="20" viewBox="0 0 49 49" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.598 2.86c8.338.2 15.208 6.502 15.476 14.51l-3.126-1.215c-.866-5.646-5.979-10.132-12.4-10.296l-.371-.004c-5.387.006-9.96 3.086-11.885 7.373l-.982 2.186-2.348-.474a8.726 8.726 0 0 0-1.291-.16l-.444-.012c-4.592 0-8.13 3.465-8.143 7.521l.012.425c.027.49.107.972.234 1.437h-.077l-1.887 3.076a10.033 10.033 0 0 1-1.225-3.842l-.042-.525-.015-.55c0-5.905 5.077-10.542 11.143-10.542.797 0 1.576.08 2.329.231C17.985 6.59 23.66 2.856 30.19 2.855l.407.005z" fill="#70757A"/><path d="M23.42 40.57l3.045-1.622.246-.132.896 1.432-3.274 1.743 3.274 1.747-.896 1.432-3.291-1.755v3.518h-1.753v-3.518l-3.044 1.623-.248.132-.895-1.432 3.273-1.747-3.273-1.743.895-1.432 3.292 1.754v-3.514h1.753v3.514zm13.917-2.783l3.045-1.622.246-.132.896 1.432-3.274 1.743 3.274 1.747-.896 1.432-3.291-1.755v3.516h-1.752v-3.517l-3.292 1.756-.15-.238-.745-1.194 3.273-1.747-3.273-1.743.894-1.432.248.132 3.045 1.623v-3.515h1.752v3.514zm-25.86-6.677l3.988-2.126.84 1.344.172.273-.284.151-3.686 1.965 3.97 2.117-.171.272-.841 1.344-3.988-2.127v4.26H9.493v-4.26l-3.741 1.995-.248.132-1.01-1.616 3.969-2.117-3.686-1.965-.283-.151 1.01-1.617.248.132 3.741 1.994v-4.26h1.984v4.26zm14.841-3.71l3.989-2.127.84 1.344.171.273-3.971 2.114 3.971 2.119-1.011 1.615-3.989-2.127v4.261h-1.983v-4.26l-3.988 2.126-.84-1.343-.171-.272 3.969-2.117-3.686-1.965-.283-.151 1.01-1.617.248.132 3.741 1.994v-4.26h1.983v4.26zm14.844-3.71l3.988-2.127.841 1.344.171.273-3.972 2.114 3.972 2.12-1.012 1.614-3.988-2.127v4.261H39.18v-4.26l-3.989 2.126-.84-1.342-.17-.273 3.968-2.117-3.685-1.965-.283-.151 1.01-1.616.248.131 3.74 1.994v-4.26h1.984v4.26z" fill="#303134"/></svg>',
                blizzard: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.049 29.535a5.881 5.881 0 0 1 5.875 5.876 5.88 5.88 0 0 1-5.875 5.875h-1.53v-3.058h1.53a2.824 2.824 0 0 0 2.817-2.817 2.823 2.823 0 0 0-2.817-2.817H4v-3.059h8.049zm26.075-7.064A5.882 5.882 0 0 1 44 28.347a5.882 5.882 0 0 1-5.876 5.875h-1.529v-3.058h1.53a2.824 2.824 0 0 0 2.816-2.817 2.823 2.823 0 0 0-2.817-2.816H4v-3.06h34.124zM21.827 6.716a5.88 5.88 0 0 1 5.875 5.875 5.88 5.88 0 0 1-5.875 5.876H4v-3.059h17.827a2.824 2.824 0 0 0 2.817-2.816 2.823 2.823 0 0 0-2.817-2.817h-1.529v-3.06h1.53z" fill="#70757A"/><path d="M28.788 28.991v4.715l.15-.087 3.922-2.275.986 1.716-4.073 2.35 4.073 2.351-.987 1.717-3.92-2.263-.151-.088v4.703h-1.973v-4.703l-.15.088-3.933 2.263-.976-1.717 4.074-2.35-4.074-2.35.986-1.717 3.923 2.264.15.086v-4.703h1.973zM38.903 6.172v4.715l.15-.087 3.922-2.275.987 1.716-4.073 2.35 4.073 2.351-.988 1.717-3.92-2.263-.15-.088v4.703H36.93v-4.703l-.15.088-3.932 2.263-.977-1.717 4.074-2.35-4.074-2.35.987-1.717 3.922 2.264.15.086V6.172h1.973z" fill="#303134"/></svg>',
                thunderstorms: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M30.514 2.005c8.06.192 14.75 6.088 15.428 13.715h-3.017c-.671-5.843-5.881-10.549-12.46-10.716L30.093 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.592 0-8.13 3.466-8.143 7.522l.006.226-1.894 1.831a8.572 8.572 0 0 0-.71.779 9.996 9.996 0 0 1-.345-1.74l-.042-.525-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path d="M21.796 36.153l.035.033a5.479 5.479 0 0 1 0 7.898c-2.241 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.898l4.058-3.948 4.023 3.915zm-8.609-10.594l.036.035a5.479 5.479 0 0 1 0 7.897c-2.242 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.897l4.057-3.949 4.023 3.914z" fill="#0B57D0"/><path fill-rule="evenodd" clip-rule="evenodd" d="M33.06 18.72l-6.667 13.756h6l-.744 12.243 14.744-16.037-5.335-.022 5.335-9.94H33.059z" fill="#DA4F00"/></g><defs><clipPath id="a"><path fill="#fff" d="M0 0h48v48H0z"/></clipPath></defs></svg>',
                strong_thunderstorms: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.514 2.005c8.159.194 14.912 6.233 15.45 13.995h-3.01c-.535-5.972-5.805-10.826-12.49-10.996L30.094 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.592 0-8.13 3.466-8.143 7.522l.012.424c.12 2.154 1.243 4.114 2.977 5.434L6.624 30c-2.5-1.707-4.238-4.372-4.567-7.47l-.042-.525-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path fill-rule="evenodd" clip-rule="evenodd" d="M32.667 19L26 32.757h6L31.257 45 46 28.962l-5.335-.02L46 19H32.667zm-12 16L18 40.82h2.4L20.103 46 26 39.215l-2.134-.01L26 35h-5.333zM12 26l-4 7.937h3.6L11.154 41 20 31.748l-3.201-.013L20 26h-8z" fill="#DA4F00"/></svg>',
                sleet_hail: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M30.514 2.005C39.02 2.208 46 8.762 46 16.999l-.008.473-.022.454c-.242 3.735-1.932 7.084-4.518 9.573l-2.122-2.121c2.188-2.084 3.552-4.892 3.662-7.97L43 16.97c-.016-6.409-5.492-11.788-12.534-11.967L30.093 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.592 0-8.13 3.466-8.143 7.522l.01.41-2.567 2.567a9.994 9.994 0 0 1-.386-1.882l-.042-.524-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path fill="#303134" d="M9.523 21.575l5.44 5.44-5.44 5.439-5.44-5.44zm13.602 13.597l5.44 5.439-5.44 5.44-5.44-5.44zm5.439-16.317l2.72 2.72-16.32 16.318-2.72-2.72zm6.801 6.799l2.72 2.72-8.16 8.158-2.72-2.72z"/></g><defs><clipPath id="a"><path fill="#fff" d="M0 0h48v48H0z"/></clipPath></defs></svg>',
                hurricane: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.08 1.508c11.94.302 21.523 10.088 21.523 22.112a1 1 0 0 1-1.997 0c0-11.113-8.998-20.12-20.096-20.12a1 1 0 0 1 0-2l.57.008zM5.54 24.38c0 12.215 9.892 22.119 22.093 22.12a1 1 0 0 0 0-1.999c-11.098 0-20.096-9.008-20.096-20.121a1 1 0 0 0-.896-.994l-.102-.006-.103.006a1 1 0 0 0-.896.994z" fill="#70757A" stroke="#70757A" stroke-width=".999"/><mask id="a" fill="#fff"><path fill-rule="evenodd" clip-rule="evenodd" d="M24 8.944c9.546 0 15.965 7.334 18.978 11.778a5.817 5.817 0 0 1-.024 6.624C39.93 31.73 33.52 38.916 24 38.916c-9.52 0-15.93-7.187-18.954-11.57a5.816 5.816 0 0 1-.024-6.624C8.036 16.278 14.454 8.944 24 8.944zm0 2.997c-6.612 0-11.974 5.368-11.974 11.99 0 6.62 5.362 11.988 11.975 11.988 6.613 0 11.973-5.368 11.973-11.989 0-6.62-5.36-11.989-11.973-11.989z"/></mask><path fill-rule="evenodd" clip-rule="evenodd" d="M24 8.944c9.546 0 15.965 7.334 18.978 11.778a5.817 5.817 0 0 1-.024 6.624C39.93 31.73 33.52 38.916 24 38.916c-9.52 0-15.93-7.187-18.954-11.57a5.816 5.816 0 0 1-.024-6.624C8.036 16.278 14.454 8.944 24 8.944zm0 2.997c-6.612 0-11.974 5.368-11.974 11.99 0 6.62 5.362 11.988 11.975 11.988 6.613 0 11.973-5.368 11.973-11.989 0-6.62-5.36-11.989-11.973-11.989z" fill="#70757A"/></svg>',
                tornado: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M21.01 39h5.49c0-1.47.43-2.84 1.16-4H19c0 1.64.79 3.09 2.01 4zM44 3H7v4h33l4-4zM21 31h13l4-4H21v4zm-2.1-8H40c.87-1.16 1.5-2.52 1.8-4H14.9l4 4zM8.67 11c.62 1.43 1.41 2.77 2.33 4h30.8c-.3-1.48-.93-2.84-1.8-4H8.67z" fill="#8F8F8F"/></g><defs><clipPath id="a"><path fill="#fff" d="M0 0h48v48H0z"/></clipPath></defs></svg>',
                clear_night: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M25.761 2c-3.077 3.766-4.917 8.544-4.917 13.743 0 12.153 10.055 22.005 22.459 22.005.233 0 .465-.003.697-.01C39.884 42.774 33.556 46 26.459 46 14.055 46 4 36.148 4 23.995 4 12.07 13.68 2.36 25.761 2z" fill="#3271EA"/></svg>',
                mostly_sunny: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18.58 4.033a8.246 8.246 0 0 1 10.844 0 8.247 8.247 0 0 0 4.863 2.014 8.246 8.246 0 0 1 7.667 7.668 8.245 8.245 0 0 0 2.015 4.862 8.246 8.246 0 0 1 1.61 8.031 14.018 14.018 0 0 0-10.064-4.604h-.038L35.2 22h-.036c-4.844 0-9.184 2.448-11.666 6.24C17.862 28.292 13 32.75 13 38.618v.04l.012.463.002.04c.05.973.239 1.902.538 2.776a8.243 8.243 0 0 1-7.502-7.653 8.246 8.246 0 0 0-2.015-4.862 8.247 8.247 0 0 1 0-10.845 8.245 8.245 0 0 0 2.015-4.862 8.246 8.246 0 0 1 7.667-7.668 8.247 8.247 0 0 0 4.863-2.014z" fill="#FCBD00"/><path d="M18.58 4.033a8.246 8.246 0 0 1 10.844 0 8.249 8.249 0 0 0 4.863 2.015 8.246 8.246 0 0 1 7.667 7.667 8.248 8.248 0 0 0 2.015 4.863 8.244 8.244 0 0 1 1.608 8.03 13.84 13.84 0 0 0-2.59-2.24 5.236 5.236 0 0 0-1.28-3.817 11.247 11.247 0 0 1-2.746-6.632 5.246 5.246 0 0 0-4.878-4.878 11.248 11.248 0 0 1-6.632-2.747 5.245 5.245 0 0 0-6.898 0 11.248 11.248 0 0 1-6.632 2.747 5.246 5.246 0 0 0-4.878 4.878 11.248 11.248 0 0 1-2.747 6.632 5.246 5.246 0 0 0 0 6.898 11.248 11.248 0 0 1 2.747 6.632 5.246 5.246 0 0 0 3.96 4.732l.009.308.002.04c.05.972.238 1.902.537 2.776a8.244 8.244 0 0 1-7.467-7.269l-.034-.383a8.248 8.248 0 0 0-2.015-4.863 8.246 8.246 0 0 1-.246-10.548l.246-.296a8.247 8.247 0 0 0 1.984-4.527l.03-.336a8.246 8.246 0 0 1 7.285-7.633l.383-.034A8.248 8.248 0 0 0 18.32 4.25l.259-.217z" fill="#DA4F00"/><path d="M35.404 26.502c5.042.124 9.096 4.1 9.096 8.997l-.005.283c-.092 2.895-1.605 5.446-3.886 7.027a9.464 9.464 0 0 1-3.311 1.45c-2.235.276-4.45.24-6.862.239h-6.838c-3.252 0-5.918-2.46-6.09-5.557l-.008-.323c0-3.25 2.733-5.88 6.098-5.88.44 0 .87.045 1.283.131l1.191.247.488-1.114c1.414-3.234 4.738-5.5 8.597-5.502l.247.002z" stroke="#70757A" stroke-width="3"/></svg>',
                mostly_cloudy_night: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.689 2c-2.077 2.482-3.319 5.631-3.319 9.058 0 4.015 1.705 7.649 4.46 10.275a16.159 16.159 0 0 0-2.875 3.394l-.332-.003c-2.825 0-5.505.92-7.664 2.5C3.913 24.573 2 20.749 2 16.497 2 8.637 8.534 2.238 16.689 2z" fill="#3271EA"/><path d="M29.589 21.503c6.62.16 11.91 5.257 11.911 11.496l-.007.378c-.007.203-.02.41-.04.62l-.083.91.773.491c1.456.926 2.357 2.439 2.357 4.102 0 2.659-2.356 5-5.5 5H19.5v-.003h-4.877c-4.22 0-7.675-3.026-8.083-6.866l-.03-.374-.01-.397c0-4.07 3.4-7.421 7.704-7.627l.419-.01c.587 0 1.161.06 1.714.172l1.177.238.49-1.096c1.848-4.128 6.198-7.034 11.264-7.037l.32.003z" stroke="#70757A" stroke-width="3"/></svg>'
            };
            
            // WMO Weather interpretation codes mapped to our SVG icons
            const iconMap = {
                0: 'sunny',                    // Clear sky
                1: 'sunny',                    // Mainly clear
                2: 'partly_cloudy',            // Partly cloudy
                3: 'mostly_sunny',             // Overcast
                45: 'cloudy',                  // Fog
                48: 'cloudy',                  // Rime fog
                51: 'drizzle',                 // Light drizzle
                53: 'drizzle',                 // Moderate drizzle
                55: 'drizzle',                 // Dense drizzle
                56: 'sleet_hail',              // Freezing drizzle
                57: 'sleet_hail',              // Dense freezing drizzle
                61: 'heavy_rain',              // Slight rain
                63: 'heavy_rain',              // Moderate rain
                65: 'heavy_rain',              // Heavy rain
                66: 'sleet_hail',              // Light freezing rain
                67: 'sleet_hail',              // Heavy freezing rain
                71: 'snow_showers',            // Slight snow
                73: 'heavy_snow',              // Moderate snow
                75: 'blizzard',                // Heavy snow
                77: 'snow_showers',            // Snow grains
                80: 'heavy_rain',              // Slight rain showers
                81: 'heavy_rain',              // Moderate rain showers
                82: 'heavy_rain',              // Violent rain showers
                85: 'snow_showers',            // Slight snow showers
                86: 'heavy_snow',              // Heavy snow showers
                95: 'thunderstorms',           // Thunderstorm
                96: 'strong_thunderstorms',    // Thunderstorm with hail
                99: 'strong_thunderstorms',    // Thunderstorm with heavy hail
                
                // Additional conditions
                'hurricane': 'hurricane',
                'tornado': 'tornado',
                'blowing_snow': 'blowing_snow',
                'flurries': 'flurries',
                'icy': 'icy',
                'wintry_mix': 'wintry_mix',
                'windy': 'windy'
            };

            const iconName = iconMap[code] || 'sunny';
            const svgIcon = WeatherIcons[iconName] || WeatherIcons.sunny;
            
            // Return embedded SVG icon with proper sizing
            return `<span class="weather-svg-icon" style="display: inline-block; width: ${size}px; height: ${size}px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">${svgIcon}</span>`;
        },

        async refreshWeather() {
            const loadingEl = $('#crm-weather-loading');
            const dataEl = $('#crm-weather-data');
            const errorEl = $('#crm-weather-error');

            console.log('[Weather] Refreshing weather for job:', this.jobId);

            // Show loading
            loadingEl.show();
            dataEl.hide();
            errorEl.hide();

            try {
                // Force fetch new weather data
                console.log('[Weather] Calling fetch endpoint...');
                const fetchResult = await CRM_API.request('/weather/fetch', {
                    method: 'POST',
                    body: JSON.stringify({ job_id: this.jobId })
                });
                console.log('[Weather] Fetch result:', fetchResult);

                // Reload weather data
                console.log('[Weather] Reloading weather data...');
                const weather = await CRM_API.getWeather(this.jobId);
                console.log('[Weather] Refreshed data:', weather);

                // Handle response format
                this.weather = Array.isArray(weather) ? weather : (weather?.data || []);
                if (weather?.error) {
                    this.weather = { error: true, message: weather.message, has_address: weather.has_address };
                }

                this.renderWeatherWidget();

                if (!this.weather?.error && this.weather.length > 0) {
                    showToast('Weather data refreshed');
                }
            } catch (err) {
                console.error('[Weather] Failed to refresh weather:', err);
                this.weather = { error: true, message: 'Failed to refresh weather data' };
                this.renderWeatherWidget();
            }
        },

        renderEquipment() {
            console.log('[renderEquipment] Called, equipment count:', this.equipment?.length);
            
            // Initialize the comprehensive equipment management system
            if (typeof PI_Equipment !== 'undefined') {
                console.log('[renderEquipment] PI_Equipment is defined');
                const container = $('#job-tab-equipment');
                
                // Check if already initialized
                if (!container.find('#pi-equipment-container').length) {
                    console.log('[renderEquipment] Creating new equipment UI');
                    
                    // Render the full equipment management UI
                    const html = `
                        <div id="pi-equipment-container" class="pi-equipment-page">
                            <!-- Header with stats -->
                            <div class="pi-equipment-header">
                                <div class="pi-equipment-title-bar">
                                    <div class="pi-equipment-title">
                                        <h2>Equipment & Plant</h2>
                                    </div>
                                    <div class="pi-equipment-title-actions">
                                        <button class="pi-equipment-btn pi-equipment-btn-primary" onclick="PI_Equipment.openAddModal()">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19"/>
                                                <line x1="5" y1="12" x2="19" y2="12"/>
                                            </svg>
                                            Add Equipment
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Alerts container -->
                                <div id="pi-equipment-alerts"></div>
                                
                                <!-- Stats cards -->
                                <div class="pi-equipment-stats" id="pi-equipment-stats">
                                    <div class="pi-equipment-loading">
                                        <div class="pi-equipment-spinner"></div>
                                        <p>Loading equipment...</p>
                                    </div>
                                </div>
                            </div>
                            
                                                        
                            <!-- Filters and controls -->
                            <div class="pi-equipment-controls">
                                <div class="pi-equipment-filters">
                                    <button class="pi-equipment-filter active" data-filter="all">All Equipment</button>
                                    <button class="pi-equipment-filter" data-filter="on-site">On-Site</button>
                                    <button class="pi-equipment-filter" data-filter="hired">Hired</button>
                                    <button class="pi-equipment-filter" data-filter="owned">Owned</button>
                                    <button class="pi-equipment-filter" data-filter="attention">Needs Attention</button>
                                </div>
                            </div>
                            
                            <!-- Equipment table -->
                            <div class="pi-equipment-table-container">
                                <table class="pi-equipment-table">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-sort="status">Status</th>
                                            <th class="sortable" data-sort="internal_name">Equipment</th>
                                            <th class="sortable" data-sort="acquisition_type">Type</th>
                                            <th class="sortable" data-sort="operator_name">Operator</th>
                                            <th class="sortable" data-sort="allocated_from_date">Dates</th>
                                            <th class="sortable" data-sort="cost_to_job">Cost</th>
                                            <th class="sortable" data-sort="current_condition">Condition</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pi-equipment-table-body">
                                        <tr><td colspan="8" class="pi-equipment-loading-cell">
                                            <div class="pi-equipment-loading">
                                                <div class="pi-equipment-spinner"></div>
                                                <p>Loading equipment data...</p>
                                            </div>
                                        </td></tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="pi-equipment-pagination" id="pi-equipment-pagination" style="display: none;"></div>
                        </div>
                        
                        <!-- Equipment Detail Drawer -->
                        <div class="pi-equipment-drawer-overlay" id="pi-equipment-drawer-overlay"></div>
                        <div class="pi-equipment-drawer" id="pi-equipment-drawer">
                            <div class="pi-equipment-drawer-header">
                                <div class="pi-equipment-drawer-title">
                                    <h3 id="pi-equipment-drawer-title">Equipment Name</h3>
                                    <div class="pi-equipment-drawer-subtitle" id="pi-equipment-drawer-subtitle">Type | Manufacturer</div>
                                </div>
                                <button class="pi-equipment-drawer-close" id="pi-equipment-drawer-close">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="pi-equipment-drawer-tabs">
                                <button class="pi-equipment-drawer-tab active" data-tab="overview">Overview</button>
                                <button class="pi-equipment-drawer-tab" data-tab="timeline">Timeline</button>
                                <button class="pi-equipment-drawer-tab" data-tab="inspections">Inspections</button>
                                <button class="pi-equipment-drawer-tab" data-tab="documents">Documents</button>
                            </div>
                            <div class="pi-equipment-drawer-content">
                                <div class="pi-equipment-tab-panel active" id="pi-equipment-tab-overview"></div>
                                <div class="pi-equipment-tab-panel" id="pi-equipment-tab-timeline"></div>
                                <div class="pi-equipment-tab-panel" id="pi-equipment-tab-inspections"></div>
                                <div class="pi-equipment-tab-panel" id="pi-equipment-tab-documents"></div>
                            </div>
                            <div class="pi-equipment-drawer-footer">
                                <button class="pi-equipment-btn pi-equipment-btn-danger" onclick="PI_Equipment.deleteCurrentEquipment()">Delete</button>
                                <button class="pi-equipment-btn pi-equipment-btn-secondary" onclick="PI_Equipment.closeDrawer()">Close</button>
                                <button class="pi-equipment-btn pi-equipment-btn-primary" onclick="PI_Equipment.editCurrentEquipment()">Edit Equipment</button>
                            </div>
                        </div>
                    `;
                    
                    container.html(html);
                    
                    // Check if we already have equipment data
                    const hasData = this.equipment && this.equipment.length > 0;
                    
                    // Initialize the equipment system - skip loading if we already have data
                    console.log('[renderEquipment] Initializing PI_Equipment with jobId:', this.jobId, 'hasData:', hasData);
                    PI_Equipment.init(this.jobId, hasData);
                    
                    // If we already have equipment data loaded, pass it to PI_Equipment
                    if (hasData) {
                        console.log('[renderEquipment] Passing loaded equipment to PI_Equipment');
                        PI_Equipment.equipment = this.equipment;
                        PI_Equipment.renderStats({
                            total_items: this.equipment.length,
                            total_cost: this.equipment.reduce((sum, e) => sum + parseFloat(e.cost_to_job || 0), 0),
                            on_site: this.equipment.filter(e => (e.status || 'On-Site') === 'On-Site').length,
                            hired: this.equipment.filter(e => (e.acquisition_type || '').toLowerCase() === 'hired').length,
                            overdue_inspections: 0,
                            requiring_attention: []
                        });
                        PI_Equipment.renderTable();
                        PI_Equipment.renderCalendarStrip();
                    }
                } else {
                    console.log('[renderEquipment] UI already exists, refreshing data');
                    // UI exists, just refresh the data
                    if (this.equipment && PI_Equipment) {
                        PI_Equipment.equipment = this.equipment;
                        PI_Equipment.renderTable();
                    }
                }
            } else {
                console.warn('[renderEquipment] PI_Equipment not defined, using fallback');
                // Fallback to simple view if PI_Equipment is not loaded
                const container = $('#crm-equipment-list');
                if (!this.equipment || this.equipment.length === 0) {
                    container.html('<div class="crm-empty-state">No equipment assigned.</div>');
                    return;
                }
                
                container.html(this.equipment.map(eq => `
                    <div class="crm-equipment-item ${eq.status || 'On-Site'}" data-id="${eq.id}">
                        <div class="crm-eq-info">
                            <div class="crm-eq-name">${this.escapeHtml(eq.internal_name || eq.equipment_name || 'Unnamed')}</div>
                            <div class="crm-eq-meta">${eq.equipment_type || 'Other'}</div>
                        </div>
                        <div class="crm-eq-status">
                            <span class="crm-eq-badge ${eq.status || 'On-Site'}">${eq.status || 'On-Site'}</span>
                        </div>
                    </div>
                `).join(''));
            }
        },

        renderSubcontractors() {
            const container = $('#crm-subcontractors-list');
            if (!this.subcontractors || this.subcontractors.length === 0) {
                container.html('<div class="crm-empty-state">No subcontractors assigned.</div>');
                return;
            }

            const html = this.subcontractors.map(sub => `
                <div class="crm-subcontractor-item ${sub.status}" data-id="${sub.id}">
                    <div class="crm-sub-header">
                        <div class="crm-sub-trade">${this.escapeHtml(sub.trade)}</div>
                        <span class="crm-sub-status">${sub.status}</span>
                    </div>
                    <div class="crm-sub-name">${this.escapeHtml(sub.subcontractor_name)}</div>
                    ${sub.company_name ? `<div class="crm-sub-company">${this.escapeHtml(sub.company_name)}</div>` : ''}
                    <div class="crm-sub-contract">${this.formatCurrency(sub.contract_value || 0)}</div>
                    <div class="crm-sub-compliance">
                        ${!sub.insurance_verified ? '<span class="crm-compliance-missing">Insurance</span>' : '<span class="crm-compliance-ok">Insurance</span>'}
                        ${!sub.cis_verified ? '<span class="crm-compliance-missing">CIS</span>' : '<span class="crm-compliance-ok">CIS</span>'}
                    </div>
                </div>
            `).join('');

            container.html(html);
        },

        renderSiteMap() {
            const container = $('#crm-map-container');
            const mapboxToken = PI_Job_CRM?.mapbox_token;

            // Get address from: 1) siteLocation (from API), 2) PI_Job_CRM localized data, 3) DOM element where job-single.js displays it
            let siteAddress = this.siteLocation?.address || PI_Job_CRM?.site_address || '';

            // Fallback: try to get from the DOM where job-single.js displays the address
            if (!siteAddress) {
                const domAddress = $('#pi-job-address').text();
                if (domAddress && domAddress !== 'No address' && domAddress !== 'Loading...') {
                    siteAddress = domAddress;
                }
            }

            console.log('[Map] renderSiteMap:', {
                siteLocation: this.siteLocation,
                piJobCRMAddress: PI_Job_CRM?.site_address,
                finalAddress: siteAddress,
                hasToken: !!mapboxToken
            });

            // Check if we have stored coordinates
            const hasStoredLocation = this.siteLocation && this.siteLocation.latitude && this.siteLocation.longitude;

            if (!hasStoredLocation && !siteAddress) {
                container.html(`
                    <div class="crm-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; margin-bottom: 16px; color: #94a3b8;">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <h4>No site location set</h4>
                        <p>Add a site address to view the location on the map.</p>
                        <button class="pi-btn pi-btn-primary" onclick="CRM_Modal.open('site-location')">Set Location</button>
                    </div>
                `);
                return;
            }

            // Build the map container
            container.html(`
                <div class="crm-map-wrapper">
                    <div id="pi-job-map" class="pi-job-mapbox-map"></div>
                    <div class="crm-map-info">
                        <div class="crm-map-address">${this.escapeHtml(siteAddress)}</div>
                        ${this.siteLocation?.what3words ? `<div class="crm-map-w3w">${this.escapeHtml(this.siteLocation.what3words)}</div>` : ''}
                        ${this.siteLocation?.access_instructions ? `<div class="crm-map-access">${this.escapeHtml(this.siteLocation.access_instructions)}</div>` : ''}
                    </div>
                </div>
            `);

            // Initialize Mapbox map
            if (!mapboxToken || !window.mapboxgl) {
                container.find('#pi-job-map').html(`
                    <div class="crm-map-error">
                        <p>Map is loading...</p>
                    </div>
                `);
                return;
            }

            // Set Mapbox token
            mapboxgl.accessToken = mapboxToken;

            // Initialize the map
            const map = new mapboxgl.Map({
                container: 'pi-job-map',
                style: 'mapbox://styles/mapbox/light-v11',
                center: hasStoredLocation ? [this.siteLocation.longitude, this.siteLocation.latitude] : [-2.5, 54.0],
                zoom: hasStoredLocation ? 15 : 5.5,
                antialias: true,
                attributionControl: false
            });

            // Add navigation controls
            map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
            map.addControl(new mapboxgl.AttributionControl({ compact: true }), 'bottom-right');

            // Store map reference for cleanup
            this._siteMap = map;

            map.on('load', () => {
                if (hasStoredLocation) {
                    // Add marker for stored location
                    this.addSiteMarker(map, this.siteLocation.longitude, this.siteLocation.latitude, siteAddress);
                } else if (siteAddress) {
                    // Geocode the address and show on map
                    this.geocodeAndShowLocation(map, siteAddress);
                }
            });

            map.on('error', (e) => {
                console.error('Map error:', e);
                container.find('#pi-job-map').html(`
                    <div class="crm-map-error">
                        <p>Failed to load map. Please try again later.</p>
                    </div>
                `);
            });
        },

        addSiteMarker(map, lng, lat, address) {
            // Create a custom marker element
            const el = document.createElement('div');
            el.className = 'pi-job-map-marker';
            el.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
            `;

            // Add the marker
            const marker = new mapboxgl.Marker({
                element: el,
                anchor: 'bottom'
            })
                .setLngLat([lng, lat])
                .addTo(map);

            // Add popup with address
            const popup = new mapboxgl.Popup({
                offset: 15,
                closeButton: false,
                closeOnClick: false
            })
                .setLngLat([lng, lat])
                .setHTML(`
                    <div class="pi-job-map-popup">
                        <strong>Site Location</strong>
                        <p>${this.escapeHtml(address)}</p>
                    </div>
                `)
                .addTo(map);

            // Zoom to the marker with animation
            map.easeTo({
                center: [lng, lat],
                zoom: 16,
                duration: 1000,
                pitch: 45
            });

            this._siteMarker = marker;
            this._sitePopup = popup;
        },

        async geocodeAndShowLocation(map, address) {
            console.log('[Map] Geocoding address:', address);
            try {
                // Use Mapbox geocoding API
                const token = PI_Job_CRM?.mapbox_token;
                if (!token) {
                    console.error('[Map] No Mapbox token available');
                    return;
                }

                const response = await fetch(
                    `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(address)}.json?access_token=${token}&country=GB&limit=1`,
                    { headers: { 'Accept': 'application/json' } }
                );

                if (!response.ok) {
                    console.error('[Map] Geocoding HTTP error:', response.status);
                    throw new Error('Geocoding failed');
                }

                const data = await response.json();
                console.log('[Map] Geocoding response:', data);

                if (!data.features || data.features.length === 0) {
                    console.warn('[Map] No geocoding results for address:', address);
                    // Show the map centered on UK but no marker
                    map.easeTo({ center: [-2.5, 54.0], zoom: 5, duration: 500 });
                    return;
                }

                const [lng, lat] = data.features[0].center;
                const placeName = data.features[0].place_name;
                console.log('[Map] Found coordinates:', lng, lat, placeName);

                // Add marker at geocoded location
                this.addSiteMarker(map, lng, lat, placeName);

                // Also save these coordinates to the site location for future use
                if (CRM_State.jobId) {
                    CRM_API.updateSiteLocation(CRM_State.jobId, {
                        address: address,
                        latitude: lat,
                        longitude: lng,
                        city: data.features[0].context?.find(c => c.id.startsWith('place'))?.text || '',
                        postcode: data.features[0].context?.find(c => c.id.startsWith('postcode'))?.text || ''
                    }).catch(err => console.warn('[Map] Failed to save geocoded location:', err));
                }

            } catch (error) {
                console.error('[Map] Geocoding error:', error);
                // Map still shows default view, no marker
                map.easeTo({ center: [-2.5, 54.0], zoom: 5, duration: 500 });
            }
        },

        renderClientDetails() {
            const container = $('#crm-client-form-container');
            const client = this.clientDetails || {};

            container.html(`
                <form id="crm-client-form" class="crm-form">
                    <div class="crm-form-section">
                        <h4>Primary Contact</h4>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Contact Name</label>
                                <input type="text" name="primary_contact_name" value="${this.escapeHtml(client.primary_contact_name || '')}" required>
                            </div>
                            <div class="crm-form-group">
                                <label>Email</label>
                                <input type="email" name="primary_contact_email" value="${this.escapeHtml(client.primary_contact_email || '')}">
                            </div>
                            <div class="crm-form-group">
                                <label>Phone</label>
                                <input type="tel" name="primary_contact_phone" value="${this.escapeHtml(client.primary_contact_phone || '')}">
                            </div>
                        </div>
                    </div>
                    <div class="crm-form-section">
                        <h4>Site Address</h4>
                        <div class="crm-form-group">
                            <textarea name="site_address" rows="3">${this.escapeHtml(client.site_address || '')}</textarea>
                        </div>
                    </div>
                    <div class="crm-form-section">
                        <h4>Preferences & Requirements</h4>
                        <div class="crm-form-group">
                            <label>Communication Preferences</label>
                            <select name="communication_preferences">
                                <option value="email" ${client.communication_preferences === 'email' ? 'selected' : ''}>Email</option>
                                <option value="phone" ${client.communication_preferences === 'phone' ? 'selected' : ''}>Phone</option>
                                <option value="sms" ${client.communication_preferences === 'sms' ? 'selected' : ''}>SMS</option>
                            </select>
                        </div>
                        <div class="crm-form-group">
                            <label>Special Requirements</label>
                            <textarea name="special_requirements" rows="2">${this.escapeHtml(client.special_requirements || '')}</textarea>
                        </div>
                    </div>
                    <div class="crm-form-actions">
                        <button type="submit" class="pi-btn pi-btn-primary">Save Changes</button>
                    </div>
                </form>
            `);
        },

        // Utility functions
        formatCurrency(amount) {
            return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(amount);
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        getIconPath(name) {
            const paths = {
                'mail': '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
                'file-text': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
                'clock': '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                'alert-triangle': '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                'camera': '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
                'git-pull-request': '<circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><line x1="6" y1="9" x2="6" y2="21"/>',
                'activity': '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'
            };
            return paths[name] || paths['activity'];
        }
    };

    // ============================================
    // CRM Modal System
    // ============================================
    const CRM_Modal = {
        open(type, data = {}) {
            const modals = {
                'email': () => this.renderEmailCompose(data),
                'upload-document': () => this.renderUploadDocumentForm(data),
                'safety-incident': () => this.renderSafetyIncidentForm(),
                'quality-snag': () => this.renderQualitySnagForm(),
                'change-order': () => this.renderChangeOrderForm(),
                'rfi': () => this.renderRFIForm(),
                'rfi-respond': () => this.renderRFIRespondForm(data),
                'rfi-edit': () => this.renderRFIEditForm(data),
                'submittal': () => this.renderSubmittalForm(),
                'submittal-review': () => this.renderSubmittalReviewForm(data),
                'submittal-resubmit': () => this.renderSubmittalResubmitForm(data),
                'invoice': () => this.renderInvoiceForm(),
                'timesheet': () => this.renderTimesheetForm(),
                'daily-report': () => this.renderDailyReportForm(),
                'equipment': () => this.renderEquipmentForm(),
                'subcontractor': () => this.renderSubcontractorForm(),
                'team-assignment': () => this.renderTeamAssignmentForm(),
                'site-location': () => this.renderSiteLocationForm()
            };

            const renderer = modals[type];
            if (renderer) {
                this.show(renderer());
            }
        },

        show(content) {
            const modal = $(`
                <div class="crm-modal-overlay">
                    <div class="crm-modal-container">
                        <div class="crm-modal-header">
                            <h3>${content.title}</h3>
                            <button class="crm-modal-close" onclick="CRM_Modal.close()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="crm-modal-body">${content.body}</div>
                        <div class="crm-modal-footer">${content.footer || ''}</div>
                    </div>
                </div>
            `);

            $('#pi-modals-container').append(modal);
            modal.fadeIn(200);

            // Call onInit callback if present (for binding event handlers)
            if (content.onInit) {
                content.onInit();
            }

            // Bind form submission if present
            modal.find('form').on('submit', (e) => {
                e.preventDefault();
                content.onSubmit && content.onSubmit(e.target);
            });
        },

        close() {
            $('.crm-modal-overlay').fadeOut(200, function() { $(this).remove(); });
        },

        renderEmailCompose(data) {
            return {
                title: 'Compose Email',
                body: `
                    <form id="crm-email-form">
                        <div class="crm-form-group">
                            <label>To</label>
                            <input type="email" name="to" value="${data.to || ''}" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Message</label>
                            <textarea name="message" rows="8" required></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Template</label>
                            <select id="crm-email-template">
                                <option value="">-- Select Template --</option>
                                <option value="project_start">Project Start</option>
                                <option value="progress_update">Progress Update</option>
                                <option value="change_order">Change Order</option>
                                <option value="invoice">Invoice</option>
                                <option value="project_complete">Project Complete</option>
                            </select>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-email-form">Send Email</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    CRM_API.sendEmail({
                        to: formData.get('to'),
                        subject: formData.get('subject'),
                        message: formData.get('message')
                    }).then(() => {
                        this.close();
                        CRM_State.loadTabData('communications');
                        showToast('Email sent successfully');
                    });
                }
            };
        },

        renderSafetyIncidentForm() {
            return {
                title: 'Report Safety Incident',
                body: `
                    <form id="crm-safety-form">
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Incident Type</label>
                                <select name="incident_type" required>
                                    <option value="">Select type...</option>
                                    <option value="Injury">Injury</option>
                                    <option value="Near Miss">Near Miss</option>
                                    <option value="Property Damage">Property Damage</option>
                                    <option value="Environmental">Environmental</option>
                                    <option value="Equipment Failure">Equipment Failure</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="crm-form-group">
                                <label>Severity</label>
                                <select name="severity" required>
                                    <option value="minor">Minor</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="major">Major</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Description</label>
                            <textarea name="description" rows="4" required placeholder="Describe what happened..."></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>
                                <input type="checkbox" name="riddor_reportable">
                                RIDDOR Reportable
                            </label>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-danger" form="crm-safety-form">Report Incident</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    CRM_API.createSafetyIncident({
                        job_id: CRM_State.jobId,
                        incident_type: formData.get('incident_type'),
                        severity: formData.get('severity'),
                        description: formData.get('description'),
                        riddor_reportable: formData.get('riddor_reportable') === 'on',
                        reported_by: 'Current User',
                        incident_date: new Date().toISOString()
                    }).then(() => {
                        this.close();
                        CRM_State.loadTabData('safety');
                        showToast('Incident reported');
                    });
                }
            };
        },

        renderQualitySnagForm() {
            return {
                title: 'Add Quality Snag',
                body: `
                    <form id="crm-snag-form">
                        <div class="crm-form-group">
                            <label>Title</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Location</label>
                                <input type="text" name="location">
                            </div>
                            <div class="crm-form-group">
                                <label>Category</label>
                                <select name="category">
                                    <option value="general">General</option>
                                    <option value="finishes">Finishes</option>
                                    <option value="mep">MEP</option>
                                    <option value="structural">Structural</option>
                                    <option value="external">External</option>
                                </select>
                            </div>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Priority</label>
                                <select name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="crm-form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date">
                            </div>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-snag-form">Add Snag</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    CRM_API.createQualitySnag({
                        job_id: CRM_State.jobId,
                        title: formData.get('title'),
                        description: formData.get('description'),
                        location: formData.get('location'),
                        category: formData.get('category'),
                        priority: formData.get('priority'),
                        due_date: formData.get('due_date'),
                        identified_by: 'Current User',
                        identified_date: new Date().toISOString().split('T')[0]
                    }).then(() => {
                        this.close();
                        CRM_State.loadTabData('quality');
                        showToast('Snag added');
                    });
                }
            };
        },

        renderChangeOrderForm() {
            return {
                title: 'Create Change Order',
                body: `
                    <form id="crm-co-form">
                        <div class="crm-form-group">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="Brief description of the change">
                        </div>
                        <div class="crm-form-group">
                            <label>Full Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Reason for Change</label>
                            <textarea name="reason" rows="2"></textarea>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Cost Impact (£)</label>
                                <input type="number" name="cost_impact" step="0.01" value="0">
                            </div>
                            <div class="crm-form-group">
                                <label>Schedule Impact (days)</label>
                                <input type="number" name="schedule_impact_days" value="0">
                            </div>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-co-form">Create Change Order</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    CRM_API.createChangeOrder({
                        job_id: CRM_State.jobId,
                        title: formData.get('title'),
                        description: formData.get('description'),
                        reason: formData.get('reason'),
                        cost_impact: parseFloat(formData.get('cost_impact')),
                        schedule_impact_days: parseInt(formData.get('schedule_impact_days')),
                        requested_by: 'Current User',
                        request_date: new Date().toISOString().split('T')[0]
                    }).then(() => {
                        this.close();
                        CRM_State.loadTabData('change-orders');
                        showToast('Change order created');
                    });
                }
            };
        },

        renderRFIForm() {
            return {
                title: 'Create RFI',
                body: `
                    <form id="crm-rfi-form">
                        <div class="crm-form-group">
                            <label>Title *</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Priority</label>
                                <select name="priority">
                                    <option value="low">Low</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="crm-form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date">
                            </div>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Assigned To</label>
                                <input type="text" name="assigned_to" placeholder="e.g., Architect, Consultant">
                            </div>
                            <div class="crm-form-group">
                                <label>Drawing Reference</label>
                                <input type="text" name="drawing_references" placeholder="e.g., A-101">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Description *</label>
                            <textarea name="description" rows="4" required></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Suggested Solution</label>
                            <textarea name="suggested_solution" rows="3" placeholder="Proposed solution or answer..."></textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="button" class="pi-btn pi-btn-primary" id="crm-rfi-submit">Create RFI</button>
                `,
                onInit: () => {
                    console.log('[RFI Form] Initialized. CRM_State.jobId:', CRM_State.jobId);
                    $('#crm-rfi-submit').on('click', () => {
                        console.log('[RFI Form] Submit button clicked');
                        const form = document.getElementById('crm-rfi-form');
                        if (!form.checkValidity()) {
                            console.log('[RFI Form] Form validation failed');
                            form.reportValidity();
                            return;
                        }
                        const formData = new FormData(form);
                        const payload = {
                            job_id: CRM_State.jobId,
                            title: formData.get('title'),
                            description: formData.get('description'),
                            suggested_solution: formData.get('suggested_solution'),
                            priority: formData.get('priority'),
                            due_date: formData.get('due_date'),
                            assigned_to: formData.get('assigned_to'),
                            drawing_references: formData.get('drawing_references')
                        };
                        console.log('[RFI Form] Submitting payload:', payload);
                        CRM_API.createRFI(payload).then((response) => {
                            console.log('[RFI Form] Success:', response);
                            CRM_Modal.close();
                            CRM_State.loadTabData('rfi-submittals');
                            showToast('RFI created successfully');
                        }).catch(err => {
                            console.error('[RFI Form] Error:', err);
                            showToast(err.message || err.code || JSON.stringify(err), 'error');
                        });
                    });
                }
            };
        },

        renderRFIRespondForm(data) {
            return {
                title: 'Respond to RFI',
                body: `
                    <form id="crm-rfi-respond-form">
                        <input type="hidden" name="rfi_id" value="${data.id || ''}">
                        <div class="crm-form-group">
                            <label>Response *</label>
                            <textarea name="response" rows="6" required placeholder="Provide your response to the RFI..."></textarea>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>
                                    <input type="checkbox" name="cost_impact" id="rfi-cost-impact">
                                    Has Cost Impact
                                </label>
                            </div>
                            <div class="crm-form-group">
                                <label>
                                    <input type="checkbox" name="schedule_impact" id="rfi-schedule-impact">
                                    Has Schedule Impact
                                </label>
                            </div>
                        </div>
                        <div class="crm-form-row" id="impact-details" style="display:none;">
                            <div class="crm-form-group">
                                <label>Cost Impact Amount (£)</label>
                                <input type="number" name="cost_impact_amount" step="0.01" placeholder="0.00">
                            </div>
                            <div class="crm-form-group">
                                <label>Schedule Impact (days)</label>
                                <input type="number" name="schedule_impact_days" placeholder="0">
                            </div>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="button" class="pi-btn pi-btn-primary" id="crm-rfi-respond-submit">Submit Response</button>
                `,
                onInit: () => {
                    // Show/hide impact details based on checkboxes
                    $('#rfi-cost-impact, #rfi-schedule-impact').on('change', function() {
                        const showDetails = $('#rfi-cost-impact').is(':checked') || $('#rfi-schedule-impact').is(':checked');
                        $('#impact-details').toggle(showDetails);
                    });
                    $('#crm-rfi-respond-submit').on('click', () => {
                        const form = document.getElementById('crm-rfi-respond-form');
                        if (!form.checkValidity()) {
                            form.reportValidity();
                            return;
                        }
                        const formData = new FormData(form);
                        CRM_API.respondRFI(formData.get('rfi_id'), {
                            response: formData.get('response'),
                            cost_impact: $('#rfi-cost-impact').is(':checked'),
                            cost_impact_amount: formData.get('cost_impact_amount'),
                            schedule_impact: $('#rfi-schedule-impact').is(':checked'),
                            schedule_impact_days: formData.get('schedule_impact_days')
                        }).then(() => {
                            CRM_Modal.close();
                            CRM_State.loadTabData('rfi-submittals');
                            showToast('Response submitted successfully');
                        }).catch(err => {
                            showToast(err.message || 'Failed to submit response', 'error');
                        });
                    });
                }
            };
        },

        renderRFIEditForm(data) {
            return {
                title: 'Edit RFI',
                body: `
                    <form id="crm-rfi-edit-form">
                        <input type="hidden" name="rfi_id" value="${data.id || ''}">
                        <div class="crm-form-group">
                            <label>Title</label>
                            <input type="text" name="title" value="${data.title || ''}" required>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Priority</label>
                                <select name="priority">
                                    <option value="low" ${data.priority === 'low' ? 'selected' : ''}>Low</option>
                                    <option value="normal" ${data.priority === 'normal' ? 'selected' : ''}>Normal</option>
                                    <option value="high" ${data.priority === 'high' ? 'selected' : ''}>High</option>
                                    <option value="critical" ${data.priority === 'critical' ? 'selected' : ''}>Critical</option>
                                </select>
                            </div>
                            <div class="crm-form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" value="${data.due_date || ''}">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Description</label>
                            <textarea name="description" rows="4">${data.description || ''}</textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="button" class="pi-btn pi-btn-primary" id="crm-rfi-edit-submit">Save Changes</button>
                `,
                onInit: () => {
                    $('#crm-rfi-edit-submit').on('click', () => {
                        const form = document.getElementById('crm-rfi-edit-form');
                        if (!form.checkValidity()) {
                            form.reportValidity();
                            return;
                        }
                        const formData = new FormData(form);
                        CRM_API.updateRFI(formData.get('rfi_id'), {
                            title: formData.get('title'),
                            priority: formData.get('priority'),
                            due_date: formData.get('due_date'),
                            description: formData.get('description')
                        }).then(() => {
                            CRM_Modal.close();
                            CRM_State.loadTabData('rfi-submittals');
                            showToast('RFI updated successfully');
                        }).catch(err => {
                            showToast(err.message || 'Failed to update RFI', 'error');
                        });
                    });
                }
            };
        },

        renderSubmittalForm() {
            return {
                title: 'Submit Submittal',
                body: `
                    <form id="crm-submittal-form">
                        <div class="crm-form-group">
                            <label>Title *</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Type</label>
                                <select name="submittal_type">
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
                            <div class="crm-form-group">
                                <label>Specification Section</label>
                                <input type="text" name="specification_section" placeholder="e.g. 03 30 00">
                            </div>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Review Due Date</label>
                                <input type="date" name="due_date">
                            </div>
                            <div class="crm-form-group">
                                <label>Submission Method</label>
                                <select name="submission_method">
                                    <option value="digital">Digital</option>
                                    <option value="physical">Physical</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="button" class="pi-btn pi-btn-primary" id="crm-submittal-submit">Submit Submittal</button>
                `,
                onInit: () => {
                    console.log('[Submittal Form] Initialized. CRM_State.jobId:', CRM_State.jobId);
                    $('#crm-submittal-submit').on('click', () => {
                        console.log('[Submittal Form] Submit button clicked');
                        const form = document.getElementById('crm-submittal-form');
                        if (!form.checkValidity()) {
                            console.log('[Submittal Form] Form validation failed');
                            form.reportValidity();
                            return;
                        }
                        const formData = new FormData(form);
                        const payload = {
                            job_id: CRM_State.jobId,
                            title: formData.get('title'),
                            description: formData.get('description'),
                            submittal_type: formData.get('submittal_type'),
                            specification_section: formData.get('specification_section'),
                            due_date: formData.get('due_date'),
                            submission_method: formData.get('submission_method')
                        };
                        console.log('[Submittal Form] Submitting payload:', payload);
                        CRM_API.createSubmittal(payload).then((response) => {
                            console.log('[Submittal Form] Success:', response);
                            CRM_Modal.close();
                            CRM_State.loadTabData('rfi-submittals');
                            showToast('Submittal submitted successfully');
                        }).catch(err => {
                            console.error('[Submittal Form] Error:', err);
                            showToast(err.message || err.code || JSON.stringify(err), 'error');
                        });
                    });
                }
            };
        },

        renderSubmittalReviewForm(data) {
            return {
                title: 'Review Submittal',
                body: `
                    <form id="crm-submittal-review-form">
                        <input type="hidden" name="submittal_id" value="${data.id || ''}">
                        <div class="crm-form-group">
                            <label>Decision *</label>
                            <select name="decision" required>
                                <option value="">Select decision...</option>
                                <option value="approved">Approved</option>
                                <option value="approved_as_noted">Approved as Noted</option>
                                <option value="revise_resubmit">Revise & Resubmit</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="crm-form-group">
                            <label>Review Comments *</label>
                            <textarea name="comments" rows="4" required placeholder="Enter your review comments..."></textarea>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>
                                    <input type="checkbox" name="cost_impact">
                                    Cost Impact Identified
                                </label>
                            </div>
                            <div class="crm-form-group">
                                <label>
                                    <input type="checkbox" name="schedule_impact">
                                    Schedule Impact Identified
                                </label>
                            </div>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="button" class="pi-btn pi-btn-primary" id="crm-submittal-review-submit">Submit Review</button>
                `,
                onInit: () => {
                    $('#crm-submittal-review-submit').on('click', () => {
                        const form = document.getElementById('crm-submittal-review-form');
                        if (!form.checkValidity()) {
                            form.reportValidity();
                            return;
                        }
                        const formData = new FormData(form);
                        CRM_API.reviewSubmittal(formData.get('submittal_id'), {
                            decision: formData.get('decision'),
                            comments: formData.get('comments'),
                            cost_impact: formData.get('cost_impact') === 'on',
                            schedule_impact: formData.get('schedule_impact') === 'on'
                        }).then(() => {
                            CRM_Modal.close();
                            CRM_State.loadTabData('rfi-submittals');
                            showToast('Review submitted successfully');
                        }).catch(err => {
                            showToast(err.message || 'Failed to submit review', 'error');
                        });
                    });
                }
            };
        },

        renderSubmittalResubmitForm(data) {
            return {
                title: 'Resubmit Submittal',
                body: `
                    <form id="crm-submittal-resubmit-form">
                        <input type="hidden" name="submittal_id" value="${data.id || ''}">
                        <div class="crm-form-group">
                            <label>Change Description *</label>
                            <textarea name="change_description" rows="4" required placeholder="Describe the changes made for this resubmission..."></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Updated Title</label>
                            <input type="text" name="title" value="${data.title || ''}">
                        </div>
                        <div class="crm-form-group">
                            <label>Updated Description</label>
                            <textarea name="description" rows="3">${data.description || ''}</textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="button" class="pi-btn pi-btn-primary" id="crm-submittal-resubmit-submit">Resubmit</button>
                `,
                onInit: () => {
                    $('#crm-submittal-resubmit-submit').on('click', () => {
                        const form = document.getElementById('crm-submittal-resubmit-form');
                        if (!form.checkValidity()) {
                            form.reportValidity();
                            return;
                        }
                        const formData = new FormData(form);
                        CRM_API.resubmitSubmittal(formData.get('submittal_id'), {
                            change_description: formData.get('change_description'),
                            title: formData.get('title'),
                            description: formData.get('description')
                        }).then(() => {
                            CRM_Modal.close();
                            CRM_State.loadTabData('rfi-submittals');
                            showToast('Submittal resubmitted successfully');
                        }).catch(err => {
                            showToast(err.message || 'Failed to resubmit submittal', 'error');
                        });
                    });
                }
            };
        },

        renderInvoiceForm() {
            return {
                title: 'Create Invoice',
                body: `
                    <form id="crm-invoice-form">
                        <div class="crm-form-group">
                            <label>Invoice Type</label>
                            <select name="invoice_type">
                                <option value="progress">Progress Payment</option>
                                <option value="milestone">Milestone</option>
                                <option value="final">Final Account</option>
                                <option value="retention">Retention Release</option>
                            </select>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" required>
                            </div>
                            <div class="crm-form-group">
                                <label>Total Amount (£)</label>
                                <input type="number" name="total_amount" step="0.01" required>
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Description</label>
                            <textarea name="description" rows="2"></textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-invoice-form">Create Invoice</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    CRM_API.createInvoice({
                        job_id: CRM_State.jobId,
                        invoice_type: formData.get('invoice_type'),
                        due_date: formData.get('due_date'),
                        total_amount: parseFloat(formData.get('total_amount')),
                        description: formData.get('description')
                    }).then(() => {
                        this.close();
                        CRM_State.loadTabData('invoicing');
                        showToast('Invoice created');
                    });
                }
            };
        },

        renderTimesheetForm() {
            return {
                title: 'Add Timesheet Entry',
                body: `
                    <form id="crm-timesheet-form">
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Worker Name</label>
                                <input type="text" name="worker_name" required>
                            </div>
                            <div class="crm-form-group">
                                <label>Work Date</label>
                                <input type="date" name="work_date" required value="${new Date().toISOString().split('T')[0]}">
                            </div>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" value="08:00">
                            </div>
                            <div class="crm-form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" value="17:00">
                            </div>
                            <div class="crm-form-group">
                                <label>Break (mins)</label>
                                <input type="number" name="break_duration" value="60">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Task Description</label>
                            <textarea name="task_description" rows="2"></textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-timesheet-form">Add Entry</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    CRM_API.createTimesheet({
                        job_id: CRM_State.jobId,
                        worker_name: formData.get('worker_name'),
                        work_date: formData.get('work_date'),
                        start_time: formData.get('start_time'),
                        end_time: formData.get('end_time'),
                        break_duration: formData.get('break_duration'),
                        task_description: formData.get('task_description')
                    }).then(() => {
                        this.close();
                        CRM_State.loadTabData('team');
                        showToast('Timesheet entry added');
                    });
                }
            };
        },

        renderDailyReportForm() {
            return {
                title: 'Submit Daily Report',
                body: `
                    <form id="crm-daily-report-form">
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Report Date</label>
                                <input type="date" name="report_date" value="${new Date().toISOString().split('T')[0]}" required>
                            </div>
                            <div class="crm-form-group">
                                <label>Workers on Site</label>
                                <input type="number" name="workforce_count" value="0" min="0">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Weather Conditions</label>
                            <input type="text" name="weather_conditions" placeholder="e.g. Sunny, 18°C">
                        </div>
                        <div class="crm-form-group">
                            <label>Work Completed</label>
                            <textarea name="work_completed" rows="3" placeholder="Describe work completed today..."></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Work Planned Tomorrow</label>
                            <textarea name="work_planned" rows="2"></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Delays / Issues</label>
                            <textarea name="delays" rows="2" placeholder="Any delays or issues encountered..."></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Safety Issues</label>
                            <textarea name="safety_issues" rows="2"></textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-daily-report-form">Submit Report</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    data.job_id = CRM_State.jobId;
                    CRM_API.createDailyReport(data).then(() => {
                        this.close();
                        CRM_State.loadTabData('daily-reports');
                        showToast('Daily report submitted');
                    });
                }
            };
        },

        renderEquipmentForm() {
            return {
                title: 'Add Equipment',
                body: `
                    <form id="crm-equipment-form">
                        <div class="crm-form-group">
                            <label>Equipment Name</label>
                            <input type="text" name="equipment_name" required>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Type</label>
                                <select name="equipment_type" required>
                                    <option value="">Select type...</option>
                                    <option value="Excavator">Excavator</option>
                                    <option value="Dumper">Dumper</option>
                                    <option value="Crane">Crane</option>
                                    <option value="Scaffolding">Scaffolding</option>
                                    <option value="Tool">Tool</option>
                                    <option value="Vehicle">Vehicle</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="crm-form-group">
                                <label>Ownership</label>
                                <select name="ownership_type">
                                    <option value="owned">Owned</option>
                                    <option value="hired">Hired</option>
                                    <option value="leased">Leased</option>
                                </select>
                            </div>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Make</label>
                                <input type="text" name="make">
                            </div>
                            <div class="crm-form-group">
                                <label>Model</label>
                                <input type="text" name="model">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Serial Number</label>
                            <input type="text" name="serial_number">
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-equipment-form">Add Equipment</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    data.job_id = CRM_State.jobId;
                    CRM_API.createEquipment(data).then(() => {
                        this.close();
                        CRM_State.loadTabData('equipment');
                        showToast('Equipment added');
                    });
                }
            };
        },

        renderSubcontractorForm() {
            return {
                title: 'Add Subcontractor',
                body: `
                    <form id="crm-subcontractor-form">
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Contact Name</label>
                                <input type="text" name="subcontractor_name" required>
                            </div>
                            <div class="crm-form-group">
                                <label>Company Name</label>
                                <input type="text" name="company_name">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Trade</label>
                            <select name="trade" required>
                                <option value="">Select trade...</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="HVAC">HVAC</option>
                                <option value="Carpentry">Carpentry</option>
                                <option value="Masonry">Masonry</option>
                                <option value="Painting">Painting</option>
                                <option value="Flooring">Flooring</option>
                                <option value="Roofing">Roofing</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>Contact Email</label>
                                <input type="email" name="contact_email">
                            </div>
                            <div class="crm-form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="contact_phone">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Contract Value (£)</label>
                            <input type="number" name="contract_value" step="0.01" value="0">
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-subcontractor-form">Add Subcontractor</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    data.job_id = CRM_State.jobId;
                    CRM_API.createSubcontractor(data).then(() => {
                        this.close();
                        CRM_State.loadTabData('subcontractors');
                        showToast('Subcontractor added');
                    });
                }
            };
        },

        renderTeamAssignmentForm() {
            // Load job-specific employees for the select dropdown
            setTimeout(() => {
                CRM_API.getEmployees(CRM_State.jobId).then(employees => {
                    const select = $('#crm-team-existing-select');
                    if (employees && employees.length > 0) {
                        select.html('<option value="">Select an employee...</option>' + 
                            employees.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name} (${e.role})</option>`).join(''));
                    } else {
                        select.html('<option value="">No existing employees for this job</option>');
                    }
                }).catch(() => {
                    $('#crm-team-existing-select').html('<option value="">Error loading employees</option>');
                });
            }, 100);

            return {
                title: 'Add Team Member',
                body: `
                    <div class="crm-team-tabs">
                        <button type="button" class="crm-team-tab active" data-tab="existing" onclick="CRM_Modal.switchTeamTab('existing')">Existing Employee</button>
                        <button type="button" class="crm-team-tab" data-tab="new" onclick="CRM_Modal.switchTeamTab('new')">Create New</button>
                    </div>
                    
                    <!-- Tab: Existing Employee -->
                    <form id="crm-team-existing-form" class="crm-team-tab-content active">
                        <div class="crm-form-group">
                            <label>Select Existing Employee</label>
                            <select name="employee_id" id="crm-team-existing-select" required>
                                <option value="">Loading...</option>
                            </select>
                            <small class="crm-help-text">Only shows employees created for this job</small>
                        </div>
                        <div class="crm-form-group">
                            <label>Role on Project</label>
                            <select name="role" required>
                                <option value="Site Manager">Site Manager</option>
                                <option value="Foreman">Foreman</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Skilled Worker">Skilled Worker</option>
                                <option value="Labourer">Labourer</option>
                                <option value="Subcontractor">Subcontractor</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="crm-form-group">
                            <label>
                                <input type="checkbox" name="is_lead"> Project Lead
                            </label>
                        </div>
                    </form>
                    
                    <!-- Tab: Create New -->
                    <form id="crm-team-new-form" class="crm-team-tab-content" style="display:none;">
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="crm-form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" required>
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Role *</label>
                            <select name="role" required>
                                <option value="Site Manager">Site Manager</option>
                                <option value="Foreman">Foreman</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Skilled Worker">Skilled Worker</option>
                                <option value="Labourer">Labourer</option>
                                <option value="Subcontractor">Subcontractor</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="crm-form-group">
                            <label>Trade / Specialization</label>
                            <input type="text" name="trade" placeholder="e.g. Electrician, Carpenter">
                        </div>
                        <div class="crm-form-group">
                            <label>
                                <input type="checkbox" name="is_lead"> Project Lead
                            </label>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" id="crm-team-submit-btn">Add to Project</button>
                `,
                onOpen: () => {
                    // Set up form submission handler
                    $('#crm-team-submit-btn').off('click').on('click', () => {
                        const activeTab = $('.crm-team-tab.active').data('tab');
                        const form = activeTab === 'existing' ? '#crm-team-existing-form' : '#crm-team-new-form';
                        const $form = $(form);
                        
                        if (!$form[0].checkValidity()) {
                            $form[0].reportValidity();
                            return;
                        }
                        
                        const formData = new FormData($form[0]);
                        const data = Object.fromEntries(formData.entries());
                        data.job_id = CRM_State.jobId;
                        data.is_lead = formData.get('is_lead') === 'on' ? 1 : 0;
                        
                        if (activeTab === 'existing') {
                            // Assign existing employee to job
                            data.user_id = data.employee_id;
                            delete data.employee_id;
                            CRM_API.assignTeamMember(data).then(() => {
                                CRM_Modal.close();
                                CRM_State.loadTabData('team');
                                showToast('Team member assigned');
                            }).catch(err => {
                                showToast(err.message || 'Failed to assign', 'error');
                            });
                        } else {
                            // Create new job-specific employee
                            data.hourly_rate = 0;
                            CRM_API.createEmployee(data).then(() => {
                                CRM_Modal.close();
                                CRM_State.loadTabData('team');
                                showToast('Employee created and added to project');
                            }).catch(err => {
                                showToast(err.message || 'Failed to create employee', 'error');
                            });
                        }
                    });
                },
                onSubmit: (form) => {
                    // Prevent default, handled by button click
                    return false;
                }
            };
        },
        
        switchTeamTab(tab) {
            $('.crm-team-tab').removeClass('active');
            $(`.crm-team-tab[data-tab="${tab}"]`).addClass('active');
            $('.crm-team-tab-content').hide();
            $(`#crm-team-${tab}-form`).show();
        },

        renderSiteLocationForm() {
            return {
                title: 'Set Site Location',
                body: `
                    <form id="crm-site-form">
                        <div class="crm-form-group">
                            <label>Site Address</label>
                            <textarea name="address" rows="3" required placeholder="Enter full site address..."></textarea>
                        </div>
                        <div class="crm-form-row">
                            <div class="crm-form-group">
                                <label>City</label>
                                <input type="text" name="city">
                            </div>
                            <div class="crm-form-group">
                                <label>Postcode</label>
                                <input type="text" name="postcode">
                            </div>
                        </div>
                        <div class="crm-form-group">
                            <label>Access Instructions</label>
                            <textarea name="access_instructions" rows="2" placeholder="Parking, site entry, security requirements..."></textarea>
                        </div>
                        <div class="crm-form-group">
                            <label>Parking Information</label>
                            <textarea name="parking_info" rows="2"></textarea>
                        </div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" form="crm-site-form">Save Location</button>
                `,
                onSubmit: (form) => {
                    const formData = new FormData(form);
                    const address = formData.get('address');
                    
                    // Geocode address first
                    CRM_API.geocodeAddress(address).then(coords => {
                        const data = Object.fromEntries(formData.entries());
                        data.latitude = coords.latitude;
                        data.longitude = coords.longitude;
                        
                        CRM_API.updateSiteLocation(CRM_State.jobId, data).then(() => {
                            this.close();
                            CRM_State.loadTabData('site-map');
                            showToast('Site location saved');
                        });
                    }).catch(() => {
                        // Save without coordinates if geocoding fails
                        const data = Object.fromEntries(formData.entries());
                        CRM_API.updateSiteLocation(CRM_State.jobId, data).then(() => {
                            this.close();
                            CRM_State.loadTabData('site-map');
                            showToast('Site location saved (geocoding failed)');
                        });
                    });
                }
            };
        },

        renderUploadDocumentForm(categoryData = 'general') {
            const category = typeof categoryData === 'string' ? categoryData : (categoryData?.category || 'general');
            const currentCategory = category || CRM_State.currentDocCategory || 'general';
            const isAllFiles = currentCategory === 'all';
            
            const categoryMap = {
                'all': 'general',
                'receipts': 'receipts',
                'site-plans': 'site-plans',
                'photos': 'photos',
                'contracts': 'contracts',
                'reports': 'reports',
                'general': 'general'
            };
            
            const selectedCategory = categoryMap[currentCategory] || 'general';
            const showReceiptField = selectedCategory === 'receipts';
            
            // Global file storage for this modal instance
            const modalId = 'upload_' + Date.now();
            window._crmUploadFiles = window._crmUploadFiles || {};
            window._crmUploadFiles[modalId] = [];
            
            return {
                title: 'Upload Document',
                body: `
                    <form id="crm-upload-form-${modalId}" enctype="multipart/form-data" data-modal-id="${modalId}">
                        ${isAllFiles ? `
                        <div class="crm-form-group">
                            <label class="crm-form-label">Document Category</label>
                            <select class="crm-form-select" name="category" id="upload-category" required>
                                <option value="general" ${selectedCategory === 'general' || selectedCategory === 'all' ? 'selected' : ''}>General Document</option>
                                <option value="receipts" ${selectedCategory === 'receipts' ? 'selected' : ''}>Receipt / Expense</option>
                                <option value="site-plans" ${selectedCategory === 'site-plans' ? 'selected' : ''}>Site Plan / Drawing</option>
                                <option value="photos" ${selectedCategory === 'photos' ? 'selected' : ''}>Site Photo</option>
                                <option value="contracts" ${selectedCategory === 'contracts' ? 'selected' : ''}>Contract / Agreement</option>
                                <option value="reports" ${selectedCategory === 'reports' ? 'selected' : ''}>Report / Assessment</option>
                            </select>
                        </div>
                        ` : `
                        <input type="hidden" name="category" value="${selectedCategory}">
                        <div class="crm-form-group">
                            <label class="crm-form-label">Document Type</label>
                            <div class="crm-form-static-value" style="padding: 10px 14px; background: #f8fafc; border-radius: 6px; font-weight: 500; color: #1e293b; border: 1px solid #e2e8f0;">
                                ${selectedCategory === 'receipts' ? 'Receipt / Expense' : ''}
                                ${selectedCategory === 'site-plans' ? 'Site Plan / Drawing' : ''}
                                ${selectedCategory === 'photos' ? 'Site Photo' : ''}
                                ${selectedCategory === 'contracts' ? 'Contract / Agreement' : ''}
                                ${selectedCategory === 'reports' ? 'Report / Assessment' : ''}
                                ${selectedCategory === 'general' ? 'General Document' : ''}
                            </div>
                        </div>
                        `}
                        
                        <div class="crm-form-group" id="title-field-group">
                            <label class="crm-form-label" id="title-label">${selectedCategory === 'receipts' ? 'Vendor' : 'Document Title'} <span style="color: #94a3b8; font-weight: normal;">(optional - uses filename if blank)</span></label>
                            <input type="text" class="crm-form-input" name="title" id="doc-title" placeholder="${selectedCategory === 'receipts' ? 'e.g., Builders Merchant Ltd' : 'e.g., Foundation Plan v2'}">
                        </div>
                        
                        <div class="crm-form-group" style="margin-bottom: 20px;">
                            <label class="crm-form-label">Description <span style="color: #94a3b8; font-weight: normal;">(optional)</span></label>
                            <textarea class="crm-form-textarea" name="description" rows="3" placeholder="Add notes about this document..."></textarea>
                        </div>
                        
                        <div class="crm-form-group" id="receipt-fields" style="display: ${showReceiptField ? 'block' : 'none'}; margin-bottom: 20px;">
                            <label class="crm-form-label">Amount (£)</label>
                            <input type="number" class="crm-form-input" name="amount" step="0.01" placeholder="0.00">
                        </div>
                        
                        <div class="crm-upload-area" id="drop-zone-${modalId}" style="margin-top: 20px;">
                            <div id="drop-content-${modalId}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 48px; height: 48px; color: #94a3b8; margin-bottom: 12px;">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                <h4 style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #1b2534;">Drop files here or click to browse</h4>
                                <p style="margin: 0; font-size: 13px; color: #64748b;">PDF, Word, Excel, Images, DWG up to 50MB</p>
                            </div>
                            <div id="drop-files-${modalId}" style="display: none; text-align: center; width: 100%;">
                                <div id="file-list-${modalId}" style="text-align: left; max-height: 150px; overflow-y: auto; margin-bottom: 12px;"></div>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <button type="button" id="add-btn-${modalId}" style="padding: 6px 12px; font-size: 12px; border: 1px solid #1b2534; background: #1b2534; border-radius: 4px; cursor: pointer; color: #fff;">Add More Files</button>
                                    <button type="button" id="clear-btn-${modalId}" style="padding: 6px 12px; font-size: 12px; border: 1px solid #e2e8f0; background: #fff; border-radius: 4px; cursor: pointer; color: #64748b;">Clear All</button>
                                </div>
                            </div>
                            <input type="file" id="file-input-${modalId}" multiple 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.dwg,.dxf,.txt,.zip,.rar"
                                   style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
                        </div>
                        <div id="upload-error-${modalId}" style="color: #dc2626; font-size: 13px; margin-top: 8px; display: none;"></div>
                    </form>
                `,
                footer: `
                    <button type="button" class="pi-btn pi-btn-secondary" onclick="CRM_Modal.close()">Cancel</button>
                    <button type="button" class="pi-btn pi-btn-primary" id="submit-btn-${modalId}">Upload Document</button>
                `,
                onInit: () => {
                    const mid = modalId;
                    const files = window._crmUploadFiles[mid];
                    
                    // Category change handler
                    const $cat = $('#upload-category');
                    if ($cat.length) {
                        const updateFormForCategory = () => {
                            const catVal = $cat.val();
                            $('#receipt-fields').toggle(catVal === 'receipts');
                            // Change title label for receipts
                            if (catVal === 'receipts') {
                                $('#title-label').html('Vendor <span style="color: #94a3b8; font-weight: normal;">(optional - uses filename if blank)</span>');
                                $('#doc-title').attr('placeholder', 'e.g., Builders Merchant Ltd');
                            } else {
                                $('#title-label').html('Document Title <span style="color: #94a3b8; font-weight: normal;">(optional - uses filename if blank)</span>');
                                $('#doc-title').attr('placeholder', 'e.g., Foundation Plan v2');
                            }
                        };
                        updateFormForCategory();
                        $cat.on('change', updateFormForCategory);
                    }
                    
                    // File input handler
                    $(`#file-input-${mid}`).on('change', function(e) {
                        if (e.target.files && files) {
                            Array.from(e.target.files).forEach(f => files.push(f));
                            renderFiles();
                        }
                        e.target.value = '';
                    });
                    
                    // Add more button
                    $(`#add-btn-${mid}`).on('click', e => {
                        e.stopPropagation();
                        $(`#file-input-${mid}`).trigger('click');
                    });
                    
                    // Clear all button
                    $(`#clear-btn-${mid}`).on('click', e => {
                        e.stopPropagation();
                        if (files) {
                            files.length = 0;
                            $('#doc-title').val('');
                            renderFiles();
                        }
                    });
                    
                    // Remove single file
                    $(document).on('click', `#file-list-${mid} .rm-file`, function(e) {
                        e.stopPropagation();
                        if (files) {
                            files.splice($(this).data('idx'), 1);
                            renderFiles();
                        }
                    });
                    
                    // Drag & drop
                    const $zone = $(`#drop-zone-${mid}`);
                    $zone.on('dragenter dragover', e => { e.preventDefault(); $zone.addClass('dragover'); });
                    $zone.on('dragleave', e => { e.preventDefault(); if (!$(e.relatedTarget).closest($zone).length) $zone.removeClass('dragover'); });
                    $zone.on('drop', e => {
                        e.preventDefault();
                        $zone.removeClass('dragover');
                        const dt = e.originalEvent.dataTransfer;
                        if (dt.files && files) Array.from(dt.files).forEach(f => files.push(f));
                        renderFiles();
                    });
                    
                    // Capture category from this specific form instance
                    const formCategory = $(`#crm-upload-form-${modalId} [name="category"]`).val() || selectedCategory;
                    
                    // Submit button
                    $(`#submit-btn-${mid}`).on('click', () => {
                        if (!files || files.length === 0) {
                            showToast('Please select a file to upload', 'error');
                            return;
                        }
                        
                        const $btn = $(`#submit-btn-${mid}`);
                        const $err = $(`#upload-error-${mid}`);
                        const originalText = $btn.text();
                        
                        const fd = new FormData();
                        fd.append('job_id', CRM_State.jobId);
                        
                        // Use the captured category from this specific form instance
                        const category = $(`#crm-upload-form-${modalId} [name="category"]`).val() || formCategory || 'general';
                        const isPhotoUpload = category === 'photos';
                        
                        const title = $('#doc-title').val();
                        fd.append('title', title || files[0].name.replace(/\.[^/.]+$/, ''));
                        fd.append('description', $('textarea[name="description"]').val());
                        fd.append('category', category);
                        fd.append('photo_type', category); // For photos endpoint
                        
                        const amt = $('input[name="amount"]').val();
                        if (amt) fd.append('amount', amt);
                        
                        files.forEach((f, i) => fd.append(i === 0 ? 'file' : `file_${i}`, f));
                        
                        $btn.prop('disabled', true).text('Uploading...');
                        $err.hide();
                        
                        // Route to correct endpoint based on category
                        const uploadUrl = CRM_API.base + (isPhotoUpload ? '/photos' : '/documents');
                        
                        fetch(uploadUrl, {
                            method: 'POST',
                            headers: { 'X-WP-Nonce': CRM_API.nonce },
                            body: fd
                        })
                        .then(r => r.json().then(d => ({ ok: r.ok, status: r.status, data: d })))
                        .then(({ ok, status, data }) => {
                            if (!ok || (!data.success && !data.id)) {
                                const errorMsg = data.message || data.error || 'Upload failed (HTTP ' + status + ')';
                                throw new Error(errorMsg);
                            }
                            CRM_Modal.close();
                            CRM_State.loadTabData('documents');
                            const itemType = isPhotoUpload ? 'photo' : 'document';
                            showToast(files.length > 1 ? `${files.length} ${itemType}s uploaded` : `${itemType.charAt(0).toUpperCase() + itemType.slice(1)} uploaded`);
                            delete window._crmUploadFiles[mid];
                        })
                        .catch(err => {
                            $err.text('Upload failed: ' + err.message).show();
                            $btn.prop('disabled', false).text(originalText);
                        });
                    });
                    
                    function formatSize(b) {
                        if (!b) return '0 B';
                        const u = ['B', 'KB', 'MB', 'GB'];
                        const i = Math.floor(Math.log(b) / Math.log(1024));
                        return parseFloat((b / Math.pow(1024, i)).toFixed(1)) + ' ' + u[i];
                    }
                    
                    function fileIcon(n) {
                        const e = n.split('.').pop().toLowerCase();
                        const c = { pdf: '#dc2626', doc: '#2563eb', docx: '#2563eb', xls: '#16a34a', xlsx: '#16a34a', jpg: '#d97706', jpeg: '#d97706', png: '#d97706', dwg: '#7c3aed' };
                        return c[e] || '#64748b';
                    }
                    
                    function renderFiles() {
                        const $list = $(`#file-list-${mid}`);
                        const $content = $(`#drop-content-${mid}`);
                        const $files = $(`#drop-files-${mid}`);
                        const $err = $(`#upload-error-${mid}`);
                        
                        if (!files || files.length === 0) {
                            $content.show();
                            $files.hide();
                            $err.hide();
                            return;
                        }
                        
                        $content.hide();
                        $files.show();
                        
                        let html = files.map((f, i) => `
                            <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8fafc; border-radius: 6px; margin-bottom: 6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="${fileIcon(f.name)}" stroke-width="2" style="width: 20px; height: 20px; flex-shrink: 0;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 13px; font-weight: 500; color: #1b2534; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${f.name}</div>
                                    <div style="font-size: 11px; color: #64748b;">${formatSize(f.size)}</div>
                                </div>
                                <button type="button" class="rm-file" data-idx="${i}" style="width: 20px; height: 20px; border: none; background: #e2e8f0; border-radius: 50%; cursor: pointer; color: #64748b; font-size: 12px; line-height: 1;">×</button>
                            </div>
                        `).join('');
                        
                        if (files.length > 1) {
                            const total = files.reduce((s, f) => s + f.size, 0);
                            html += `<div style="font-size: 12px; color: #64748b; text-align: center; margin-top: 4px;">${files.length} files • ${formatSize(total)} total</div>`;
                        }
                        
                        $list.html(html);
                        
                        if (files.length === 1 && !$('#doc-title').val()) {
                            $('#doc-title').val(files[0].name.replace(/\.[^/.]+$/, ''));
                        }
                    }
                    
                    renderFiles();
                },
                onSubmit: () => false
            };
        }
    };

    // ============================================
    // Toast Notifications
    // ============================================
    function showToast(message, type = 'success') {
        const toast = $(`
            <div class="crm-toast ${type}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <span>${message}</span>
            </div>
        `);

        $('#pi-toast-container').append(toast);
        toast.addClass('show');

        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ============================================
    // Initialize CRM
    // ============================================
    $(document).ready(function() {
        // Check if we're on a job page (check both PI_Job_CRM and PI_Job for compatibility)
        const jobId = PI_Job_CRM?.job_id || PI_Job?.job_id || PI_Job?.lead_id || window.piJobId;
        if (!jobId) return;

        // Initialize state
        CRM_State.init(jobId);

        // Tab switching
        $(document).on('click', '.crm-tab-btn', function() {
            const tab = $(this).data('crm-tab');
            $('.crm-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.crm-tab-panel').removeClass('active');
            $(`#crm-tab-${tab}`).addClass('active');
            CRM_State.loadTabData(tab);
        });

        // Document category navigation
        $(document).on('click', '.crm-doc-nav-item', function() {
            const category = $(this).data('doc-category');
            $('.crm-doc-nav-item').removeClass('active');
            $(this).addClass('active');
            CRM_State.currentDocCategory = category;
            CRM_State.renderDocuments();
        });

        // Document search
        $(document).on('input', '#crm-doc-search', function() {
            const searchTerm = $(this).val().toLowerCase();
            CRM_State.renderDocuments();
        });

        // Load initial tab
        CRM_State.loadTabData('overview');

        // Initialize document category
        CRM_State.currentDocCategory = 'all';

        // Weather widget event listeners
        $(document).on('click', '#crm-weather-refresh', function() {
            CRM_State.refreshWeather();
        });

        $(document).on('click', '#crm-weather-set-location', function() {
            // Switch to site-map tab to set location
            $('.job-nav-item[data-job-tab="site-map"]').trigger('click');
        });

        // Expose globals for modal callbacks
        window.CRM_Modal = CRM_Modal;
        window.CRM_State = CRM_State;
        window.CRM_App = CRM_State; // Alias for inline onclick handlers
        window.CRM_API = CRM_API;
        window.showToast = showToast;
    });

})(jQuery);
