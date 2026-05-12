/**
 * Planning Index Safety Module - Frontend Application
 * Construction safety management SPA
 */

(function($) {
    'use strict';

    // Safety Module App
    const SafetyModule = {
        config: {},
        currentSection: 'dashboard',
        jobId: null,
        initialized: false,
        data: {
            dashboard: null,
            incidents: [],
            observations: [],
            inspections: [],
            permits: [],
            jhas: [],
            toolboxTalks: [],
            certifications: [],
            ppe: [],
            meetings: [],
            activityFeed: [],
            checklistTemplates: []
        },

        init: function() {
            console.log('Safety Module: Initializing...');
            this.config = PI_Safety || {};
            this.jobId = this.config.job_id;

            console.log('Safety Module Config:', this.config);
            console.log('Safety Module Job ID:', this.jobId);

            if (!this.jobId) {
                console.error('Safety Module: No job_id provided');
                if ($('.pi-safety-module').length) {
                    $('.pi-safety-module').html('<div class="pi-safety-error">Error: No job_id provided. Please refresh the page or contact support.</div>');
                }
                return;
            }

            this.initialized = true;
            this.bindEvents();
            this.loadDashboard();
            this.render();
            console.log('Safety Module: Initialized successfully');
        },

        bindEvents: function() {
            // Navigation
            $(document).on('click', '.pi-safety-nav button', (e) => {
                const section = $(e.currentTarget).data('section');
                this.switchSection(section);
            });

            // Incident actions
            $(document).on('click', '.pi-create-incident-btn', () => this.openModal('pi-incident-modal'));
            $(document).on('click', '.pi-incident-item', (e) => {
                const id = $(e.currentTarget).data('id');
                this.viewIncident(id);
            });

            // Observation actions
            $(document).on('click', '.pi-create-observation-btn', () => this.openModal('pi-observation-modal'));
            $(document).on('click', '.pi-resolve-observation', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.resolveObservation(id);
            });

            // Inspection actions
            $(document).on('click', '.pi-create-inspection-btn', () => {
                this.loadChecklistTemplates();
                this.openModal('pi-inspection-modal');
            });
            $(document).on('click', '.pi-execute-inspection', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.executeInspection(id);
            });

            // Permit actions
            $(document).on('click', '.pi-create-permit-btn', () => this.openModal('pi-permit-modal'));
            $(document).on('click', '.pi-approve-permit', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.openModal('pi-permit-approve-modal', { permitId: id });
            });
            $(document).on('click', '.pi-extend-permit', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.openModal('pi-permit-extend-modal', { permitId: id });
            });

            // JHA actions
            $(document).on('click', '.pi-create-jha-btn', () => this.openModal('pi-jha-modal'));
            $(document).on('click', '.pi-acknowledge-jha', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.openModal('pi-jha-acknowledge-modal', { jhaId: id });
            });

            // Toolbox talk actions
            $(document).on('click', '.pi-create-toolbox-talk-btn', () => this.openModal('pi-toolbox-talk-modal'));
            $(document).on('click', '.pi-record-attendance', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.openModal('pi-toolbox-talk-attendance-modal', { talkId: id });
            });

            // PPE actions
            $(document).on('click', '.pi-issue-ppe', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.openModal('pi-ppe-issue-modal', { ppeId: id });
            });
            $(document).on('click', '.pi-inspect-ppe', (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data('id');
                this.openModal('pi-ppe-inspect-modal', { ppeId: id });
            });

            // Modal close
            $(document).on('click', '.pi-modal-close, .pi-modal-backdrop', () => this.closeModal());
            $(document).on('click', '.pi-modal', (e) => {
                if ($(e.target).hasClass('pi-modal')) {
                    this.closeModal();
                }
            });

            // ESC key to close modal
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeModal();
                }
            });

            // Form submissions
            $(document).on('submit', '.pi-incident-form', (e) => {
                e.preventDefault();
                this.submitIncidentForm();
            });
            $(document).on('submit', '.pi-observation-form', (e) => {
                e.preventDefault();
                this.submitObservationForm();
            });
            $(document).on('submit', '.pi-observation-resolve-form', (e) => {
                e.preventDefault();
                this.submitObservationResolveForm();
            });
            $(document).on('submit', '.pi-inspection-form', (e) => {
                e.preventDefault();
                this.submitInspectionForm();
            });
            $(document).on('submit', '.pi-permit-form', (e) => {
                e.preventDefault();
                this.submitPermitForm();
            });
            $(document).on('submit', '.pi-permit-extend-form', (e) => {
                e.preventDefault();
                this.submitPermitExtendForm();
            });
            $(document).on('submit', '.pi-jha-form', (e) => {
                e.preventDefault();
                this.submitJHAForm();
            });
            $(document).on('submit', '.pi-jha-acknowledge-form', (e) => {
                e.preventDefault();
                this.submitJHAAcknowledgeForm();
            });
            $(document).on('submit', '.pi-toolbox-talk-form', (e) => {
                e.preventDefault();
                this.submitToolboxTalkForm();
            });
            $(document).on('submit', '.pi-toolbox-talk-attendance-form', (e) => {
                e.preventDefault();
                this.submitToolboxTalkAttendanceForm();
            });
            $(document).on('submit', '.pi-ppe-issue-form', (e) => {
                e.preventDefault();
                this.submitPPEIssueForm();
            });
            $(document).on('submit', '.pi-ppe-inspect-form', (e) => {
                e.preventDefault();
                this.submitPPEInspectForm();
            });

            // Modal action buttons
            $(document).on('click', '.pi-approve-permit-confirm', () => this.submitPermitApprove());
            $(document).on('click', '.pi-extend-permit-confirm', () => this.submitPermitExtendForm());
            $(document).on('click', '.pi-acknowledge-jha-confirm', () => this.submitJHAAcknowledgeForm());
            $(document).on('click', '.pi-complete-inspection', () => this.submitInspectionComplete());
            $(document).on('click', '.pi-resolve-observation', () => this.submitObservationResolveForm());
            $(document).on('click', '.pi-save-attendance', () => this.submitToolboxTalkAttendanceForm());
            $(document).on('click', '.pi-issue-ppe-confirm', () => this.submitPPEIssueForm());
            $(document).on('click', '.pi-inspect-ppe-confirm', () => this.submitPPEInspectForm());
        },

        switchSection: function(section) {
            this.currentSection = section;
            $('.pi-safety-nav button').removeClass('active');
            $(`.pi-safety-nav button[data-section="${section}"]`).addClass('active');
            $('.pi-safety-section').removeClass('active');
            $(`.pi-safety-section[data-section="${section}"]`).addClass('active');

            // Load section data
            switch(section) {
                case 'dashboard':
                    this.loadDashboard();
                    break;
                case 'incidents':
                    this.loadIncidents();
                    break;
                case 'observations':
                    this.loadObservations();
                    break;
                case 'inspections':
                    this.loadInspections();
                    break;
                case 'permits':
                    this.loadPermits();
                    break;
                case 'jha':
                    this.loadJHAs();
                    break;
                case 'toolbox-talks':
                    this.loadToolboxTalks();
                    break;
                case 'certifications':
                    this.loadCertifications();
                    break;
                case 'ppe':
                    this.loadPPE();
                    break;
                case 'meetings':
                    this.loadMeetings();
                    break;
                case 'activity':
                    this.loadActivityFeed();
                    break;
            }
        },

        // API Calls - Fixed to handle GET params properly
        apiCall: async function(endpoint, method = 'GET', data = null) {
            let url = `${this.config.rest_base}${endpoint}`;
            const options = {
                method: method,
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                    'Content-Type': 'application/json'
                }
            };

            if (method === 'GET' && data) {
                const params = new URLSearchParams(data).toString();
                url += (url.includes('?') ? '&' : '?') + params;
            } else if (data) {
                options.body = JSON.stringify(data);
            }

            try {
                const response = await fetch(url, options);
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || `HTTP ${response.status}`);
                }

                return result;
            } catch (error) {
                this.showError(error.message);
                throw error;
            }
        },

        // Dashboard
        loadDashboard: async function() {
            try {
                const result = await this.apiCall('/safety/dashboard', 'GET', { job_id: this.jobId });
                this.data.dashboard = result.data;
                this.renderDashboard();

                // Also load alerts
                this.loadAlerts();
            } catch (error) {
                console.error('Failed to load dashboard:', error);
            }
        },

        loadAlerts: async function() {
            try {
                const result = await this.apiCall('/safety/alerts', 'GET', { job_id: this.jobId });
                this.renderAlerts(result.data);
            } catch (error) {
                console.error('Failed to load alerts:', error);
            }
        },

        renderDashboard: function() {
            const dashboard = this.data.dashboard;
            if (!dashboard) return;

            $('#pi-safety-score').text(dashboard.safety_score);
            $('#pi-open-incidents').text(dashboard.open_incidents);
            $('#pi-active-permits').text(dashboard.active_permits);
            $('#pi-open-observations').text(dashboard.open_observations);

            // Color code safety score
            const scoreEl = $('#pi-safety-score');
            scoreEl.removeClass('excellent good warning critical');
            if (dashboard.safety_score >= 90) scoreEl.addClass('excellent');
            else if (dashboard.safety_score >= 70) scoreEl.addClass('good');
            else if (dashboard.safety_score >= 50) scoreEl.addClass('warning');
            else scoreEl.addClass('critical');
        },

        renderAlerts: function(alerts) {
            const container = $('.pi-safety-alerts');
            container.empty();

            if (alerts && alerts.length > 0) {
                container.addClass('has-alerts');
                alerts.forEach(alert => {
                    const alertClass = alert.type === 'critical' ? 'critical' : 'warning';
                    container.append(`
                        <div class="alert-item ${alertClass}">
                            <span>⚠️</span>
                            <span>${alert.message}</span>
                        </div>
                    `);
                });
            } else {
                container.removeClass('has-alerts');
            }
        },

        // Modal System
        openModal: function(modalId, editData = null) {
            const modal = $(`#${modalId}`);
            modal.addClass('active');

            // Reset form
            const form = modal.find('form');
            if (form.length) {
                form[0].reset();
                form.find('.error').removeClass('error');
                form.find('.pi-field-error').remove();
            }

            // Populate edit data if provided
            if (editData) {
                modal.data('edit-data', editData);
                this.populateModalForm(modal, editData);
            }

            // Focus first input
            setTimeout(() => {
                const firstInput = modal.find('input, select, textarea').first();
                if (firstInput.length) firstInput.focus();
            }, 100);
        },

        closeModal: function() {
            $('.pi-modal').removeClass('active');
            $('.pi-modal').removeData('edit-data');
        },

        populateModalForm: function(modal, data) {
            // Override in specific implementations
        },

        // Incidents
        loadIncidents: async function() {
            try {
                const result = await this.apiCall('/safety/incidents', 'GET', { job_id: this.jobId });
                this.data.incidents = result.data;
                this.renderIncidents();
            } catch (error) {
                console.error('Failed to load incidents:', error);
            }
        },

        renderIncidents: function() {
            const container = $('.pi-incident-list');
            container.empty();

            if (this.data.incidents.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p>No incidents recorded</p>
                    </div>
                `);
                return;
            }

            this.data.incidents.forEach(incident => {
                container.append(`
                    <div class="pi-list-item pi-incident-item" data-id="${incident.id}">
                        <div>
                            <div class="pi-item-severity ${incident.severity}">${incident.severity}</div>
                            <div>${incident.incident_type}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${new Date(incident.incident_date).toLocaleDateString()}</div>
                        </div>
                        <div class="pi-item-status ${incident.status}">${incident.status}</div>
                    </div>
                `);
            });
        },

        submitIncidentForm: async function() {
            const form = $('.pi-incident-form');
            const data = {
                job_id: this.jobId,
                incident_type: form.find('[name="incident_type"]').val(),
                severity: form.find('[name="severity"]').val(),
                description: form.find('[name="description"]').val(),
                location_on_site: form.find('[name="location_on_site"]').val(),
                reported_by: form.find('[name="reported_by"]').val() || this.config.user_display_name || 'Unknown'
            };

            try {
                await this.apiCall('/safety/incidents', 'POST', data);
                this.closeModal();
                this.loadIncidents();
                this.loadDashboard();
                this.showSuccess('Incident reported successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to report incident');
            }
        },

        viewIncident: async function(id) {
            try {
                const result = await this.apiCall(`/safety/incidents/${id}`, 'GET');
                const incident = result.data;
                this.showIncidentDetail(incident);
            } catch (error) {
                console.error('Failed to load incident:', error);
            }
        },

        showIncidentDetail: function(incident) {
            const modal = $('#pi-incident-detail-modal');
            modal.find('.detail-type').text(incident.incident_type);
            modal.find('.detail-severity').text(incident.severity);
            modal.find('.detail-description').text(incident.description);
            modal.find('.detail-date').text(new Date(incident.incident_date).toLocaleString());
            modal.find('.detail-location').text(incident.location_on_site || 'N/A');
            modal.addClass('active');
        },

        // Observations
        loadObservations: async function() {
            try {
                const result = await this.apiCall('/safety/observations', 'GET', { job_id: this.jobId });
                this.data.observations = result.data;
                this.renderObservations();
            } catch (error) {
                console.error('Failed to load observations:', error);
            }
        },

        renderObservations: function() {
            const container = $('.pi-observation-list');
            container.empty();

            if (this.data.observations.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <p>No observations recorded</p>
                    </div>
                `);
                return;
            }

            this.data.observations.forEach(obs => {
                container.append(`
                    <div class="pi-list-item pi-observation-item" data-id="${obs.id}">
                        <div>
                            <div>${obs.observation_type}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${obs.description}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${new Date(obs.observation_date).toLocaleDateString()}</div>
                        </div>
                        <div>
                            <div class="pi-item-severity ${obs.severity}">${obs.severity}</div>
                            <div class="pi-item-status ${obs.status}">${obs.status}</div>
                            ${obs.status === 'open' ? `<button class="pi-btn pi-btn-sm pi-btn-secondary pi-resolve-observation" data-id="${obs.id}">Resolve</button>` : ''}
                        </div>
                    </div>
                `);
            });
        },

        submitObservationForm: async function() {
            const form = $('.pi-observation-form');
            const data = {
                job_id: this.jobId,
                observation_type: form.find('[name="observation_type"]').val(),
                severity: form.find('[name="severity"]').val(),
                description: form.find('[name="description"]').val(),
                location_on_site: form.find('[name="location_on_site"]').val()
            };

            try {
                await this.apiCall('/safety/observations', 'POST', data);
                this.closeModal();
                this.loadObservations();
                this.showSuccess('Observation recorded successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to record observation');
            }
        },

        resolveObservation: function(id) {
            this.openModal('pi-observation-resolve-modal', { observationId: id });
        },

        submitObservationResolveForm: async function() {
            const modal = $('#pi-observation-resolve-modal');
            const editData = modal.data('edit-data');
            const form = modal.find('form');
            const data = {
                resolution_notes: form.find('[name="resolution_notes"]').val(),
                status: 'resolved',
                resolved_at: new Date().toISOString()
            };

            try {
                await this.apiCall(`/safety/observations/${editData.observationId}`, 'PUT', data);
                this.closeModal();
                this.loadObservations();
                this.showSuccess('Observation resolved successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to resolve observation');
            }
        },

        // Inspections
        loadInspections: async function() {
            try {
                const result = await this.apiCall('/safety/inspections', 'GET', { job_id: this.jobId });
                this.data.inspections = result.data;
                this.renderInspections();
            } catch (error) {
                console.error('Failed to load inspections:', error);
            }
        },

        loadChecklistTemplates: async function() {
            try {
                const result = await this.apiCall('/safety/checklist-templates', 'GET');
                this.data.checklistTemplates = result.data;
                this.populateChecklistTemplates();
            } catch (error) {
                console.error('Failed to load checklist templates:', error);
            }
        },

        populateChecklistTemplates: function() {
            const select = $('#pi-inspection-modal select[name="checklist_template_id"]');
            select.empty().append('<option value="">Select template...</option>');
            this.data.checklistTemplates.forEach(tpl => {
                select.append(`<option value="${tpl.id}">${tpl.template_name}</option>`);
            });
        },

        renderInspections: function() {
            const container = $('.pi-inspection-list');
            container.empty();

            if (this.data.inspections.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <p>No inspections scheduled</p>
                    </div>
                `);
                return;
            }

            this.data.inspections.forEach(insp => {
                container.append(`
                    <div class="pi-list-item pi-inspection-item" data-id="${insp.id}">
                        <div>
                            <div>${insp.title || insp.inspection_type.replace('_', ' ')}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${new Date(insp.inspection_date).toLocaleString()}</div>
                        </div>
                        <div>
                            <div class="pi-item-status ${insp.status}">${insp.status}</div>
                            ${insp.status === 'scheduled' ? `<button class="pi-btn pi-btn-sm pi-btn-primary pi-execute-inspection" data-id="${insp.id}">Execute</button>` : ''}
                        </div>
                    </div>
                `);
            });
        },

        submitInspectionForm: async function() {
            const form = $('.pi-inspection-form');
            const data = {
                job_id: this.jobId,
                inspection_type: form.find('[name="inspection_type"]').val(),
                title: form.find('[name="title"]').val(),
                inspection_date: form.find('[name="inspection_date"]').val(),
                next_due_date: form.find('[name="next_due_date"]').val(),
                checklist_template_id: form.find('[name="checklist_template_id"]').val()
            };

            try {
                await this.apiCall('/safety/inspections', 'POST', data);
                this.closeModal();
                this.loadInspections();
                this.showSuccess('Inspection scheduled successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to schedule inspection');
            }
        },

        executeInspection: async function(id) {
            try {
                const result = await this.apiCall(`/safety/inspections/${id}`, 'GET');
                const inspection = result.data;
                this.openModal('pi-inspection-execute-modal', { inspectionId: id, inspection });
                this.renderInspectionChecklist(inspection);
            } catch (error) {
                console.error('Failed to load inspection:', error);
            }
        },

        renderInspectionChecklist: function(inspection) {
            const container = $('#pi-inspection-checklist-container');
            if (!inspection.checklist_items || inspection.checklist_items.length === 0) {
                container.html('<p>No checklist items</p>');
                return;
            }

            let html = '<div class="checklist-items">';
            inspection.checklist_items.forEach((item, index) => {
                html += `
                    <div class="checklist-item" data-item-id="${item.item_id || index}">
                        <label>${item.item || item.description || 'Item ' + (index + 1)}</label>
                        <div>
                            <label><input type="radio" name="item_${index}" value="pass"> Pass</label>
                            <label><input type="radio" name="item_${index}" value="fail"> Fail</label>
                            <label><input type="radio" name="item_${index}" value="na"> N/A</label>
                        </div>
                        <input type="text" name="item_${index}_notes" placeholder="Notes">
                    </div>
                `;
            });
            html += '</div>';
            container.html(html);
        },

        submitInspectionComplete: async function() {
            const modal = $('#pi-inspection-execute-modal');
            const editData = modal.data('edit-data');
            const data = {
                overall_score: modal.find('[name="overall_score"]').val(),
                digital_signature: modal.find('[name="digital_signature"]').val(),
                findings: []
            };

            try {
                await this.apiCall(`/safety/inspections/${editData.inspectionId}/complete`, 'POST', data);
                this.closeModal();
                this.loadInspections();
                this.showSuccess('Inspection completed successfully');
            } catch (error) {
                this.showError('Failed to complete inspection');
            }
        },

        // Permits
        loadPermits: async function() {
            try {
                const result = await this.apiCall('/safety/permits', 'GET', { job_id: this.jobId });
                this.data.permits = result.data;
                this.renderPermits();
            } catch (error) {
                console.error('Failed to load permits:', error);
            }
        },

        renderPermits: function() {
            const container = $('.pi-permit-grid');
            container.empty();

            if (this.data.permits.length === 0) {
                container.html(`
                    <div class="pi-empty-state" style="grid-column: 1/-1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p>No permits to work</p>
                    </div>
                `);
                return;
            }

            this.data.permits.forEach(permit => {
                container.append(`
                    <div class="pi-permit-card ${permit.status}">
                        <div class="permit-type">${permit.permit_type.replace('_', ' ').toUpperCase()}</div>
                        <div class="permit-time">
                            ${new Date(permit.start_datetime).toLocaleString()} - ${new Date(permit.end_datetime).toLocaleString()}
                        </div>
                        <div class="permit-location">${permit.location_on_site || 'No location specified'}</div>
                        <div class="permit-actions">
                            ${permit.status === 'draft' ? `<button class="pi-btn pi-btn-sm pi-btn-primary pi-approve-permit" data-id="${permit.id}">Approve</button>` : ''}
                            ${permit.status === 'active' ? `<button class="pi-btn pi-btn-sm pi-btn-secondary pi-extend-permit" data-id="${permit.id}">Extend</button>` : ''}
                        </div>
                    </div>
                `);
            });
        },

        submitPermitForm: async function() {
            const form = $('.pi-permit-form');
            const data = {
                job_id: this.jobId,
                permit_type: form.find('[name="permit_type"]').val(),
                location_on_site: form.find('[name="location_on_site"]').val(),
                start_datetime: form.find('[name="start_datetime"]').val(),
                end_datetime: form.find('[name="end_datetime"]').val(),
                work_description: form.find('[name="work_description"]').val()
            };

            try {
                await this.apiCall('/safety/permits', 'POST', data);
                this.closeModal();
                this.loadPermits();
                this.showSuccess('Permit created successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to create permit');
            }
        },

        submitPermitApprove: async function() {
            const modal = $('#pi-permit-approve-modal');
            const editData = modal.data('edit-data');
            const data = {
                signature: modal.find('[name="signature"]').val(),
                notes: modal.find('[name="notes"]').val()
            };

            try {
                await this.apiCall(`/safety/permits/${editData.permitId}/approve`, 'POST', data);
                this.closeModal();
                this.loadPermits();
                this.showSuccess('Permit approved successfully');
            } catch (error) {
                this.showError('Failed to approve permit');
            }
        },

        submitPermitExtendForm: async function() {
            const modal = $('#pi-permit-extend-modal');
            const editData = modal.data('edit-data');
            const form = modal.find('form');
            const data = {
                end_datetime: form.find('[name="end_datetime"]').val(),
                reason: form.find('[name="reason"]').val()
            };

            try {
                await this.apiCall(`/safety/permits/${editData.permitId}/extend`, 'POST', data);
                this.closeModal();
                this.loadPermits();
                this.showSuccess('Permit extended successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to extend permit');
            }
        },

        // JHA (Job Hazard Analysis)
        loadJHAs: async function() {
            try {
                const result = await this.apiCall('/safety/jha', 'GET', { job_id: this.jobId });
                this.data.jhas = result.data;
                this.renderJHAs();
            } catch (error) {
                console.error('Failed to load JHAs:', error);
            }
        },

        renderJHAs: function() {
            const container = $('.pi-jha-list');
            container.empty();

            if (this.data.jhas.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <p>No JHAs created</p>
                    </div>
                `);
                return;
            }

            this.data.jhas.forEach(jha => {
                container.append(`
                    <div class="pi-list-item pi-jha-item" data-id="${jha.id}">
                        <div>
                            <div>${jha.task_name}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${jha.trade_involved || 'No trade specified'}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${new Date(jha.preparation_date).toLocaleDateString()}</div>
                        </div>
                        <div>
                            <div class="pi-item-status ${jha.approval_status}">${jha.approval_status}</div>
                            ${jha.approval_status === 'approved' ? `<button class="pi-btn pi-btn-sm pi-btn-secondary pi-acknowledge-jha" data-id="${jha.id}">Acknowledge</button>` : ''}
                        </div>
                    </div>
                `);
            });
        },

        submitJHAForm: async function() {
            const form = $('.pi-jha-form');
            const data = {
                job_id: this.jobId,
                task_name: form.find('[name="task_name"]').val(),
                task_description: form.find('[name="task_description"]').val(),
                trade_involved: form.find('[name="trade_involved"]').val()
            };

            try {
                await this.apiCall('/safety/jha', 'POST', data);
                this.closeModal();
                this.loadJHAs();
                this.showSuccess('JHA created successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to create JHA');
            }
        },

        submitJHAAcknowledgeForm: async function() {
            const modal = $('#pi-jha-acknowledge-modal');
            const editData = modal.data('edit-data');
            const form = modal.find('form');
            const data = {
                signature: form.find('[name="signature"]').val(),
                understand: form.find('[name="understand"]').is(':checked')
            };

            try {
                await this.apiCall(`/safety/jha/${editData.jhaId}/acknowledge`, 'POST', data);
                this.closeModal();
                this.loadJHAs();
                this.showSuccess('JHA acknowledged successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to acknowledge JHA');
            }
        },

        // Toolbox Talks
        loadToolboxTalks: async function() {
            try {
                const result = await this.apiCall('/safety/toolbox-talks', 'GET', { job_id: this.jobId });
                this.data.toolboxTalks = result.data;
                this.renderToolboxTalks();
            } catch (error) {
                console.error('Failed to load toolbox talks:', error);
            }
        },

        renderToolboxTalks: function() {
            const container = $('.pi-toolbox-talk-list');
            container.empty();

            if (this.data.toolboxTalks.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p>No toolbox talks scheduled</p>
                    </div>
                `);
                return;
            }

            this.data.toolboxTalks.forEach(talk => {
                container.append(`
                    <div class="pi-list-item pi-toolbox-talk-item" data-id="${talk.id}">
                        <div>
                            <div>${talk.topic}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${talk.category || 'General'}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${new Date(talk.scheduled_date).toLocaleString()}</div>
                        </div>
                        <div>
                            <div class="pi-item-status ${talk.status}">${talk.status}</div>
                            ${talk.status === 'scheduled' ? `<button class="pi-btn pi-btn-sm pi-btn-secondary pi-record-attendance" data-id="${talk.id}">Attendance</button>` : ''}
                        </div>
                    </div>
                `);
            });
        },

        submitToolboxTalkForm: async function() {
            const form = $('.pi-toolbox-talk-form');
            const data = {
                job_id: this.jobId,
                topic: form.find('[name="topic"]').val(),
                category: form.find('[name="category"]').val(),
                content_body: form.find('[name="content_body"]').val(),
                scheduled_date: form.find('[name="scheduled_date"]').val()
            };

            try {
                await this.apiCall('/safety/toolbox-talks', 'POST', data);
                this.closeModal();
                this.loadToolboxTalks();
                this.showSuccess('Toolbox talk created successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to create toolbox talk');
            }
        },

        submitToolboxTalkAttendanceForm: async function() {
            const modal = $('#pi-toolbox-talk-attendance-modal');
            const editData = modal.data('edit-data');
            const form = modal.find('form');
            const data = {
                questions_asked: form.find('[name="questions_asked"]').val()
            };

            try {
                await this.apiCall(`/safety/toolbox-talks/${editData.talkId}/attend`, 'POST', data);
                this.closeModal();
                this.loadToolboxTalks();
                this.showSuccess('Attendance recorded successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to record attendance');
            }
        },

        // Certifications
        loadCertifications: async function() {
            try {
                const result = await this.apiCall('/safety/certifications', 'GET');
                this.data.certifications = result.data;
                this.renderCertifications();
            } catch (error) {
                console.error('Failed to load certifications:', error);
            }
        },

        renderCertifications: function() {
            const container = $('.pi-certification-list');
            container.empty();

            if (this.data.certifications.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 01-1.946-.806 3.42 3.42 0 00-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                        <p>No certifications recorded</p>
                    </div>
                `);
                return;
            }

            this.data.certifications.forEach(cert => {
                const daysUntilExpiry = Math.floor((new Date(cert.expiry_date) - new Date()) / (1000 * 60 * 60 * 24));
                const statusClass = daysUntilExpiry < 30 ? 'expiring' : 'valid';

                container.append(`
                    <div class="pi-list-item pi-certification-item ${statusClass}">
                        <div>
                            <div>${cert.certification_name}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${cert.issuing_body}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Expires: ${new Date(cert.expiry_date).toLocaleDateString()} (${daysUntilExpiry} days)</div>
                        </div>
                        <div class="pi-item-status ${statusClass}">${daysUntilExpiry < 30 ? 'Expiring' : 'Valid'}</div>
                    </div>
                `);
            });
        },

        // PPE
        loadPPE: async function() {
            try {
                const result = await this.apiCall('/safety/ppe', 'GET', { job_id: this.jobId });
                this.data.ppe = result.data;
                this.renderPPE();
            } catch (error) {
                console.error('Failed to load PPE:', error);
            }
        },

        renderPPE: function() {
            const container = $('.pi-ppe-list');
            container.empty();

            if (this.data.ppe.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7l8-4m0 10L4 17m16 0l-8-4"/>
                        </svg>
                        <p>No PPE items</p>
                    </div>
                `);
                return;
            }

            this.data.ppe.forEach(item => {
                container.append(`
                    <div class="pi-list-item pi-ppe-item" data-id="${item.id}">
                        <div>
                            <div>${item.ppe_type.replace('_', ' ').toUpperCase()}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Available: ${item.quantity_available} | Issued: ${item.quantity_issued}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Condition: ${item.condition}</div>
                        </div>
                        <div>
                            <button class="pi-btn pi-btn-sm pi-btn-primary pi-issue-ppe" data-id="${item.id}">Issue</button>
                            <button class="pi-btn pi-btn-sm pi-btn-secondary pi-inspect-ppe" data-id="${item.id}">Inspect</button>
                        </div>
                    </div>
                `);
            });
        },

        submitPPEIssueForm: async function() {
            const modal = $('#pi-ppe-issue-modal');
            const editData = modal.data('edit-data');
            const form = modal.find('form');
            const data = {
                assigned_to_worker_id: form.find('[name="assigned_to_worker_id"]').val(),
                issue_date: form.find('[name="issue_date"]').val(),
                expected_return_date: form.find('[name="expected_return_date"]').val()
            };

            try {
                await this.apiCall(`/safety/ppe/${editData.ppeId}/issue`, 'POST', data);
                this.closeModal();
                this.loadPPE();
                this.showSuccess('PPE issued successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to issue PPE');
            }
        },

        submitPPEInspectForm: async function() {
            const modal = $('#pi-ppe-inspect-modal');
            const editData = modal.data('edit-data');
            const form = modal.find('form');
            const data = {
                condition: form.find('[name="condition"]').val(),
                notes: form.find('[name="notes"]').val(),
                next_inspection_date: form.find('[name="next_inspection_date"]').val()
            };

            try {
                await this.apiCall(`/safety/ppe/${editData.ppeId}/inspect`, 'POST', data);
                this.closeModal();
                this.loadPPE();
                this.showSuccess('Inspection recorded successfully');
                form[0].reset();
            } catch (error) {
                this.showError('Failed to record inspection');
            }
        },

        // Meetings
        loadMeetings: async function() {
            try {
                const result = await this.apiCall('/safety/meetings', 'GET', { job_id: this.jobId });
                this.data.meetings = result.data;
                this.renderMeetings();
            } catch (error) {
                console.error('Failed to load meetings:', error);
            }
        },

        renderMeetings: function() {
            const container = $('.pi-meeting-list');
            container.empty();

            if (this.data.meetings.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p>No meetings scheduled</p>
                    </div>
                `);
                return;
            }

            this.data.meetings.forEach(meeting => {
                container.append(`
                    <div class="pi-list-item pi-meeting-item">
                        <div>
                            <div>${meeting.meeting_type.replace('_', ' ').toUpperCase()}</div>
                            <div style="color: #6b7280; font-size: 0.875rem;">${new Date(meeting.scheduled_date).toLocaleString()}</div>
                        </div>
                    </div>
                `);
            });
        },

        // Activity Feed
        loadActivityFeed: async function() {
            try {
                const result = await this.apiCall('/safety/activity-feed', 'GET', { job_id: this.jobId });
                this.data.activityFeed = result.data;
                this.renderActivityFeed();
            } catch (error) {
                console.error('Failed to load activity feed:', error);
            }
        },

        renderActivityFeed: function() {
            const container = $('.pi-activity-feed');
            container.empty();

            if (this.data.activityFeed.length === 0) {
                container.html(`
                    <div class="pi-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p>No recent activity</p>
                    </div>
                `);
                return;
            }

            this.data.activityFeed.forEach(activity => {
                container.append(`
                    <div class="pi-activity-item">
                        <div class="pi-activity-icon">
                            <span>📝</span>
                        </div>
                        <div class="pi-activity-content">
                            <div class="activity-text">${activity.description || `${activity.activity_type} on ${activity.entity_type}`}</div>
                            <div class="activity-time">${new Date(activity.performed_at).toLocaleString()} by ${activity.performed_by_name}</div>
                        </div>
                    </div>
                `);
            });
        },

        // UI Helpers
        closeModal: function() {
            $('.pi-modal').removeClass('active');
            $('.pi-modal').removeData('edit-data');
        },

        showSuccess: function(message) {
            this.showToast(message, 'success');
        },

        showError: function(message) {
            this.showToast(message, 'error');
        },

        showToast: function(message, type = 'info') {
            const toast = $(`
                <div class="pi-toast pi-toast-${type}">
                    ${message}
                    <button class="pi-toast-close">&times;</button>
                </div>
            `);

            $('body').append(toast);

            setTimeout(() => {
                toast.addClass('show');
            }, 10);

            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);

            toast.on('click', '.pi-toast-close', () => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            });
        },

        render: function() {
            // Initial render handled by section switching
        }
    };

    // Initialize on document ready or when safety tab is activated
    $(document).ready(function() {
        if (typeof PI_Safety !== 'undefined') {
            SafetyModule.init();
        }
    });

    // Also initialize when safety tab is clicked in job CRM
    $(document).on('click', '[data-job-tab="safety"]', function() {
        if (typeof PI_Safety !== 'undefined' && !SafetyModule.initialized) {
            SafetyModule.init();
        } else if (typeof PI_Safety !== 'undefined') {
            // Reload dashboard if already initialized
            SafetyModule.loadDashboard();
        }
    });

})(jQuery);

