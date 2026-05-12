/**
 * Planning Index CRM - Equipment Management System
 * Comprehensive equipment management for construction jobs
 */

(function($) {
    'use strict';

    // ============================================
    // CONFIGURATION & STATE
    // ============================================
    const PI_Equipment = {
        jobId: null,
        equipment: [],
        employees: [],
        currentFilter: 'all',
        currentView: 'table',
        currentSort: { field: 'internal_name', direction: 'asc' },
        pagination: { page: 1, perPage: 25, total: 0 },
        selectedEquipment: null,
        drawerOpen: false,
        
        // Equipment types with icons
        equipmentTypes: {
            'Excavator': { icon: '<path d="M12 12H5a2 2 0 0 0-2 2v5"/><path d="M15 19h7"/><path d="M16 19V2"/><path d="M6 12V7a2 2 0 0 1 2-2h2.172a2 2 0 0 1 1.414.586l3.828 3.828A2 2 0 0 1 16 10.828"/><path d="M7 19h4"/><circle cx="13" cy="19" r="2"/><circle cx="5" cy="19" r="2"/>', color: '#fef3c7' },
            'Dumper': { icon: '<path d="M12 12H5a2 2 0 0 0-2 2v5"/><path d="M15 19h7"/><path d="M16 19V2"/><path d="M6 12V7a2 2 0 0 1 2-2h2.172a2 2 0 0 1 1.414.586l3.828 3.828A2 2 0 0 1 16 10.828"/><path d="M7 19h4"/><circle cx="13" cy="19" r="2"/><circle cx="5" cy="19" r="2"/>', color: '#dbeafe' },
            'Crane': { icon: '<path d="M6 20h12"/><path d="M10 20L16 4"/><path d="M16 4v16"/><path d="M16 10h6"/><path d="M22 10v2"/><circle cx="22" cy="13" r="1.5"/>', color: '#f3e8ff' },
            'Scaffolding': { icon: '<path d="M6 4v16"/><path d="M18 4v16"/><path d="M6 8h12"/><path d="M6 12h12"/><path d="M6 16h12"/>', color: '#ffedd5' },
            'Power Tool': { icon: '<path d="M10 18a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a3 3 0 0 1-3-3 1 1 0 0 1 1-1z"/><path d="M13 10H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1l-.81 3.242a1 1 0 0 1-.97.758H8"/><path d="M14 4h3a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-3"/><path d="M18 6h4"/><path d="m5 10-2 8"/><path d="m7 18 2-8"/>', color: '#dcfce7' },
            'Hand Tool': { icon: '<path d="m15 12-9.373 9.373a1 1 0 0 1-3.001-3L12 9"/><path d="m18 15 4-4"/><path d="m21.5 11.5-1.914-1.914A2 2 0 0 1 19 8.172v-.344a2 2 0 0 0-.586-1.414l-1.657-1.657A6 6 0 0 0 12.516 3H9l1.243 1.243A6 6 0 0 1 12 8.485V10l2 2h1.172a2 2 0 0 1 1.414.586L18.5 14.5"/>', color: '#f1f5f9' },
            'Vehicle': { icon: '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>', color: '#ccfbf1' },
            'Trailer': { icon: '<path d="M18 19V9a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v8a2 2 0 0 0 2 2h2"/><path d="M2 9h3a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2"/><path d="M22 17v1a1 1 0 0 1-1 1H10v-9a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v9"/><circle cx="8" cy="19" r="2"/>', color: '#fce7f3' },
            'Generator': { icon: '<path d="M11 8c2-3-2-3 0-6"/><path d="M15.5 8c2-3-2-3 0-6"/><path d="M6 10h.01"/><path d="M6 14h.01"/><path d="M10 16v-4"/><path d="M14 16v-4"/><path d="M18 16v-4"/><path d="M20 6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3"/><path d="M5 20v2"/><path d="M19 20v2"/>', color: '#fef9c3' },
            'Compressor': { icon: '<path d="M12.8 19.6A2 2 0 1 0 14 16H2"/><path d="M17.5 8a2.5 2.5 0 1 1 2 4H2"/><path d="M9.8 4.4A2 2 0 1 1 11 8H2"/>', color: '#e0e7ff' },
            'Mixer': { icon: '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M12 9v6"/><path d="M16 15v6"/><path d="M16 3v6"/><path d="M3 15h18"/><path d="M3 9h18"/><path d="M8 15v6"/><path d="M8 3v6"/>', color: '#f3f4f6' },
            'Safety Equipment': { icon: '<path d="M10 10V5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5"/><path d="M14 6a6 6 0 0 1 6 6v3"/><path d="M4 15v-3a6 6 0 0 1 6-6"/><rect x="2" y="15" width="20" height="4" rx="1"/>', color: '#fecaca' }
        },
        
        // Status configurations
        statusConfig: {
            'On-Site': { dot: 'on-site', color: '#22c55e' },
            'En Route': { dot: 'en-route', color: '#3b82f6' },
            'Off-Site / Returned': { dot: 'off-site', color: '#94a3b8' },
            'In Maintenance': { dot: 'maintenance', color: '#f59e0b' },
            'Idle / Standby': { dot: 'idle', color: '#a855f7' },
            'Damaged': { dot: 'damaged', color: '#ef4444' },
            'Awaiting Delivery': { dot: 'awaiting-delivery', color: '#06b6d4' },
            'Awaiting Collection': { dot: 'awaiting-collection', color: '#f97316' }
        },

        init(jobId, skipLoad = false) {
            console.log('[PI_Equipment] init called, jobId:', jobId, 'skipLoad:', skipLoad);
            this.jobId = jobId;
            this.loadEmployees();
            if (!skipLoad) {
                this.loadEquipment();
            } else {
                console.log('[PI_Equipment] Skipping loadEquipment, data already provided');
            }
            this.bindEvents();
        },

        // ============================================
        // API METHODS
        // ============================================
        api: {
            base: '/wp-json/pi-crm/v1',
            nonce: PI_Job_CRM?.nonce || PI_Job?.nonce || '',

            async request(endpoint, options = {}) {
                const url = this.base + endpoint;
                const config = {
                    headers: {
                        'X-WP-Nonce': this.nonce,
                        'Content-Type': 'application/json'
                    },
                    ...options
                };
                
                const response = await fetch(url, config);
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'API request failed');
                }
                return response.json();
            },

            getEquipment(jobId, filters = {}) {
                const params = new URLSearchParams({ job_id: jobId, ...filters });
                return this.request(`/equipment?${params}`);
            },

            getEquipmentSummary(jobId) {
                return this.request(`/equipment/summary?job_id=${jobId}`);
            },

            getSingleEquipment(id) {
                return this.request(`/equipment/${id}`);
            },

            createEquipment(data) {
                return this.request('/equipment', { 
                    method: 'POST', 
                    body: JSON.stringify(data) 
                });
            },

            updateEquipment(id, data) {
                return this.request(`/equipment/${id}`, { 
                    method: 'PATCH', 
                    body: JSON.stringify(data) 
                });
            },

            deleteEquipment(id) {
                return this.request(`/equipment/${id}`, { method: 'DELETE' });
            },

            checkIn(id, data) {
                // Use NEW equipment API namespace for check-in/check-out
                const url = '/wp-json/pi-equipment/v1/equipment/' + id + '/check-in';
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.nonce
                    },
                    body: JSON.stringify(data)
                }).then(res => res.json());
            },

            checkOut(id, data) {
                // Use NEW equipment API namespace for check-in/check-out
                const url = '/wp-json/pi-equipment/v1/equipment/' + id + '/check-out';
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.nonce
                    },
                    body: JSON.stringify(data)
                }).then(res => res.json());
            },

            getTimeline(id) {
                return this.request(`/equipment/${id}/timeline`);
            },

            getInspections(id) {
                return this.request(`/equipment/${id}/inspections`);
            },

            createInspection(id, data) {
                return this.request(`/equipment/${id}/inspections`, { 
                    method: 'POST', 
                    body: JSON.stringify(data) 
                });
            },

            getEmployees(jobId) {
                return this.request(`/employees?job_id=${jobId}`);
            }
        },

        // ============================================
        // DATA LOADING
        // ============================================
        async loadEquipment() {
            try {
                this.showLoading();
                const [equipment, summary] = await Promise.all([
                    this.api.getEquipment(this.jobId),
                    this.api.getEquipmentSummary(this.jobId)
                ]);
                
                console.log('[Equipment] Raw API response:', equipment);
                console.log('[Equipment] Summary API response:', summary);
                
                // Handle API response format - may be wrapped in data property
                this.equipment = equipment.data || equipment || [];
                const summaryData = summary?.data || summary || {};
                
                console.log('[Equipment] Processed equipment data:', this.equipment);
                
                // Debug: log first item to see what fields we have
                if (this.equipment.length > 0) {
                    const first = this.equipment[0];
                    console.log('[Equipment] First item raw:', first);
                    console.log('[Equipment] All keys:', Object.keys(first));
                    console.log('[Equipment] internal_name:', first.internal_name);
                    console.log('[Equipment] equipment_name:', first.equipment_name);
                    console.log('[Equipment] name:', first.name);
                    console.log('[Equipment] title:', first.title);
                    if (first._debug) {
                        console.log('[Equipment] API Debug:', first._debug);
                    }
                }
                
                this.renderStats(summaryData);
                this.renderTable();
                this.hideLoading();
            } catch (error) {
                console.error('[Equipment] Failed to load:', error);
                this.showError('Failed to load equipment data');
            }
        },

        async loadEmployees() {
            try {
                this.employees = await this.api.getEmployees(this.jobId) || [];
            } catch (error) {
                console.error('[Equipment] Failed to load employees:', error);
                this.employees = [];
            }
        },

        async loadEquipmentDetail(id) {
            try {
                const [detail, timeline, inspections] = await Promise.all([
                    this.api.getSingleEquipment(id),
                    this.api.getTimeline(id),
                    this.api.getInspections(id)
                ]);
                
                // Handle API response format - may be wrapped in data property
                const equipmentData = detail.data || detail;
                const timelineData = timeline?.data || timeline || [];
                const inspectionsData = inspections?.data || inspections || [];
                
                // Map all fields with fallbacks for old schema compatibility
                this.selectedEquipment = {
                    id: equipmentData.id,
                    internal_name: equipmentData.internal_name || equipmentData.equipment_name || 'Unnamed Equipment',
                    equipment_name: equipmentData.equipment_name,
                    equipment_type: equipmentData.equipment_type || 'Other',
                    category: equipmentData.category || 'General',
                    manufacturer: equipmentData.manufacturer || equipmentData.make || '',
                    make: equipmentData.make,
                    brand: equipmentData.brand,
                    model: equipmentData.model || '',
                    serial_number: equipmentData.serial_number,
                    asset_tag: equipmentData.asset_tag,
                    year_of_manufacture: equipmentData.year_of_manufacture,
                    
                    // Ownership
                    acquisition_type: equipmentData.acquisition_type || equipmentData.ownership_type || 'Owned',
                    ownership_type: equipmentData.ownership_type,
                    supplier_name: equipmentData.supplier_name,
                    hire_reference_number: equipmentData.hire_reference_number,
                    delivery_method: equipmentData.delivery_method,
                    
                    // Financial
                    rate_type: equipmentData.rate_type || 'daily',
                    daily_rate: parseFloat(equipmentData.daily_rate || equipmentData.hire_rate || 0),
                    hire_rate: equipmentData.hire_rate,
                    deposit_held: parseFloat(equipmentData.deposit_held || 0),
                    cost_to_job: parseFloat(equipmentData.cost_to_job || 0),
                    
                    // Status
                    status: equipmentData.status || 'On-Site',
                    current_condition: equipmentData.current_condition || 'Good',
                    hours_meter_reading: equipmentData.hours_meter_reading,
                    condition_notes: equipmentData.condition_notes,
                    current_location_on_site: equipmentData.current_location_on_site,
                    
                    // Dates
                    allocated_from_date: equipmentData.allocated_from_date,
                    allocated_to_date: equipmentData.allocated_to_date,
                    actual_on_site_date: equipmentData.actual_on_site_date,
                    actual_return_date: equipmentData.actual_return_date,
                    days_on_site: equipmentData.days_on_site || this.calculateDaysOnSite(
                        equipmentData.actual_on_site_date || equipmentData.allocated_from_date,
                        equipmentData.actual_return_date
                    ),
                    
                    // Personnel
                    assigned_operator_id: equipmentData.assigned_operator_id,
                    operator_name: equipmentData.operator_name,
                    operator_certification_required: equipmentData.operator_certification_required,
                    operator_certification_verified: equipmentData.operator_certification_verified,
                    supervisor_responsible_id: equipmentData.supervisor_responsible_id,
                    supervisor_name: equipmentData.supervisor_name,
                    
                    // Photos & Documents
                    photos: equipmentData.photos || [],
                    arrival_photos: equipmentData.arrival_photos || [],
                    return_photos: equipmentData.return_photos || [],
                    damage_photos: equipmentData.damage_photos || [],
                    specifications: equipmentData.specifications || [],
                    
                    // Related data
                    timeline: timelineData,
                    inspections: inspectionsData
                };
                
                this.openDrawer();
            } catch (error) {
                console.error('[Equipment] Failed to load detail:', error);
                this.showToast('Failed to load equipment details', 'error');
            }
        },

        // ============================================
        // RENDERING
        // ============================================
        renderStats(summary) {
            const stats = summary || {};
            
            // Calculate stats from equipment data if not provided by API
            const total = stats.total || this.equipment.length;
            const total_cost = stats.total_cost || this.equipment.reduce((sum, e) => sum + parseFloat(e.cost_to_job || 0), 0);
            const on_site = stats.on_site || this.equipment.filter(e => (e.status || 'On-Site') === 'On-Site').length;
            const hired = stats.hired || this.equipment.filter(e => ['hired', 'rented', 'leased'].includes((e.acquisition_type || '').toLowerCase())).length;
            
            // Calculate arriving/leaving today
            const today = new Date().toISOString().split('T')[0];
            const arriving_today = stats.arriving_today || this.equipment.filter(e => {
                const fromDate = e.allocated_from_date ? e.allocated_from_date.split('T')[0] : null;
                return fromDate === today;
            }).length;
            const leaving_today = stats.leaving_today || this.equipment.filter(e => {
                const toDate = e.allocated_to_date ? e.allocated_to_date.split('T')[0] : null;
                return toDate === today;
            }).length;
            
            // Calculate needs attention
            const attention_count = stats.requiring_attention?.length || this.equipment.filter(e => 
                (e.current_condition || '').toLowerCase() === 'damaged' ||
                (e.next_inspection_due && new Date(e.next_inspection_due) < new Date()) ||
                (e.allocated_to_date && new Date(e.allocated_to_date) < new Date() && !e.actual_return_date)
            ).length;
            
            const html = `
                <div class="pi-equipment-stat-card">
                    <div class="pi-equipment-stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="4" y="4" width="16" height="16" rx="2"/>
                            <rect x="9" y="9" width="6" height="6"/>
                        </svg>
                    </div>
                    <div class="pi-equipment-stat-content">
                        <div class="pi-equipment-stat-label">Total Equipment</div>
                        <div class="pi-equipment-stat-value">${total}</div>
                    </div>
                </div>
                <div class="pi-equipment-stat-card">
                    <div class="pi-equipment-stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="pi-equipment-stat-content">
                        <div class="pi-equipment-stat-label">Equipment Cost</div>
                        <div class="pi-equipment-stat-value">${this.formatCurrency(total_cost)}</div>
                    </div>
                </div>
                <div class="pi-equipment-stat-card">
                    <div class="pi-equipment-stat-icon ${attention_count > 0 ? 'red' : 'amber'}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <div class="pi-equipment-stat-content">
                        <div class="pi-equipment-stat-label">Needs Attention</div>
                        <div class="pi-equipment-stat-value ${attention_count > 0 ? 'danger' : ''}">${attention_count}</div>
                    </div>
                </div>
                <div class="pi-equipment-stat-card">
                    <div class="pi-equipment-stat-icon teal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="pi-equipment-stat-content">
                        <div class="pi-equipment-stat-label">Arriving Today</div>
                        <div class="pi-equipment-stat-value">${arriving_today}</div>
                    </div>
                </div>
                <div class="pi-equipment-stat-card">
                    <div class="pi-equipment-stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="pi-equipment-stat-content">
                        <div class="pi-equipment-stat-label">Leaving Today</div>
                        <div class="pi-equipment-stat-value">${leaving_today}</div>
                    </div>
                </div>
                <div class="pi-equipment-stat-card">
                    <div class="pi-equipment-stat-icon ${stats.overdue_inspections > 0 ? 'red' : 'green'}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                    <div class="pi-equipment-stat-content">
                        <div class="pi-equipment-stat-label">Overdue Inspections</div>
                        <div class="pi-equipment-stat-value ${stats.overdue_inspections > 0 ? 'warning' : ''}">${stats.overdue_inspections || 0}</div>
                    </div>
                </div>
            `;
            
            $('#pi-equipment-stats').html(html);
            
            // Render attention alerts if any
            const attention_items = stats.requiring_attention || this.equipment.filter(e => 
                (e.current_condition || '').toLowerCase() === 'damaged' ||
                (e.next_inspection_due && new Date(e.next_inspection_due) < new Date()) ||
                (e.allocated_to_date && new Date(e.allocated_to_date) < new Date() && !e.actual_return_date)
            );
            if (attention_items.length > 0) {
                this.renderAttentionAlerts(attention_items);
            }
        },

        renderAttentionAlerts(attention) {
            const alerts = attention.map(item => {
                let message = '';
                let type = 'warning';
                const itemName = item.internal_name || item.equipment_name || 'Equipment';
                
                if ((item.current_condition || '').toLowerCase() === 'damaged') {
                    message = `${itemName} is reported as damaged`;
                    type = 'danger';
                } else if (item.next_inspection_due && new Date(item.next_inspection_due) < new Date()) {
                    message = `${itemName} inspection is overdue`;
                    type = 'warning';
                } else if (item.allocated_to_date && new Date(item.allocated_to_date) < new Date() && !item.actual_return_date) {
                    message = `${itemName} hire period has ended - collection overdue`;
                    type = 'warning';
                }
                
                return `
                    <div class="pi-equipment-alert ${type}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <div class="pi-equipment-alert-content">${message}</div>
                        <button class="pi-equipment-btn pi-equipment-btn-ghost pi-equipment-alert-action" onclick="PI_Equipment.viewEquipment(${item.id})">
                            View
                        </button>
                    </div>
                `;
            }).join('');
            
            $('#pi-equipment-alerts').html(alerts);
        },

        
        renderTable() {
            let filtered = this.filterEquipment();
            filtered = this.sortEquipment(filtered);
            
            // Pagination
            const start = (this.pagination.page - 1) * this.pagination.perPage;
            const paginated = filtered.slice(start, start + this.pagination.perPage);
            this.pagination.total = filtered.length;
            
            if (paginated.length === 0) {
                $('#pi-equipment-table-body').html(`
                    <tr>
                        <td colspan="8">
                            <div class="pi-equipment-empty">
                                <div class="pi-equipment-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="4" y="4" width="16" height="16" rx="2"/>
                                        <rect x="9" y="9" width="6" height="6"/>
                                    </svg>
                                </div>
                                <h4>No equipment found</h4>
                                <p>Get started by adding equipment to this job</p>
                                <button class="pi-equipment-btn pi-equipment-btn-primary" onclick="PI_Equipment.openAddModal()">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                    Add Equipment
                                </button>
                            </div>
                        </td>
                    </tr>
                `);
                $('#pi-equipment-pagination').hide();
                return;
            }
            
            const html = paginated.map(item => this.renderTableRow(item)).join('');
            $('#pi-equipment-table-body').html(html);
            
            this.renderPagination();
        },

        renderTableRow(item) {
            const status = this.calculateStatus(item);
            const statusConfig = this.statusConfig[status] || this.statusConfig['On-Site'];
            const equipmentType = item.equipment_type || 'Other';
            const typeConfig = this.equipmentTypes[equipmentType] || { icon: '<rect x="4" y="4" width="16" height="16" rx="2"/>', color: '#f1f5f9' };
            const acquisitionType = item.acquisition_type || 'Owned';
            
            // Calculate stroke color based on background color (darker shade)
            const strokeColor = this.getDarkerColor(typeConfig.color);
            // Check ALL possible name fields to find where the name is stored
            const internalName = item.internal_name 
                || item.equipment_name 
                || item.name 
                || item.title 
                || item.equipment_type 
                || 'Unnamed Equipment';
            
            const operator = item.operator_name ? `
                <div class="pi-equipment-operator">
                    <div class="pi-equipment-operator-avatar">${this.getInitials(item.operator_name)}</div>
                    <div class="pi-equipment-operator-info">
                        <div class="pi-equipment-operator-name">${this.escapeHtml(item.operator_name)}</div>
                        <div class="pi-equipment-operator-cert ${item.operator_certification_verified ? 'verified' : 'expired'}">
                            ${item.operator_certification_verified ? 'Verified' : 'Certification Required'}
                        </div>
                    </div>
                </div>
            ` : '<span class="pi-equipment-meta">Unassigned</span>';
            
            const dates = item.allocated_from_date ? `
                <div class="pi-equipment-dates">
                    <span class="date-range">${this.formatDate(item.allocated_from_date)} - ${item.allocated_to_date ? this.formatDate(item.allocated_to_date) : 'Open'}</span>
                    ${item.allocated_to_date && new Date(item.allocated_to_date) < new Date() && !item.actual_return_date ? '<div class="date-overdue">Overdue</div>' : ''}
                </div>
            ` : '-';
            
            return `
                <tr data-id="${item.id}">
                    <td>
                        <div class="pi-equipment-status">
                            <div class="pi-equipment-status-dot ${statusConfig.dot}"></div>
                            <span class="pi-equipment-status-text">${status}</span>
                        </div>
                    </td>
                    <td>
                        <div class="pi-equipment-info">
                            <div class="pi-equipment-icon" style="background: ${typeConfig.color}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="${strokeColor}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    ${typeConfig.icon}
                                </svg>
                            </div>
                            <div class="pi-equipment-details">
                                <div class="pi-equipment-name">${this.escapeHtml(internalName)}</div>
                                <div class="pi-equipment-meta">${item.manufacturer || item.brand || item.make || ''} ${item.model || ''}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="pi-equipment-badge ${acquisitionType.toLowerCase().replace(/\s+/g, '-')}">${acquisitionType}</span>
                    </td>
                    <td>${operator}</td>
                    <td>${dates}</td>
                    <td>
                        <div class="pi-equipment-cost">${this.formatCurrency(item.cost_to_job || 0)}</div>
                        ${(item.daily_rate > 0 && !['Owned', 'Client Supplied'].includes(acquisitionType)) ? `<div class="pi-equipment-cost-rate">${this.formatCurrency(item.daily_rate)}/day</div>` : ''}
                    </td>
                    <td>
                        <span class="pi-equipment-condition ${(item.current_condition || 'good').toLowerCase()}">${item.current_condition || 'Good'}</span>
                    </td>
                    <td>
                        <div class="pi-equipment-actions">
                            <button class="pi-equipment-action-btn view" onclick="PI_Equipment.viewEquipment(${item.id})" title="View">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            <button class="pi-equipment-action-btn edit" onclick="PI_Equipment.editEquipment(${item.id})" title="Edit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            ${item.status !== 'On-Site' ? `
                            <button class="pi-equipment-action-btn checkin" onclick="PI_Equipment.openCheckInModal(${item.id})" title="Check In">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                            </button>
                            ` : ''}
                            ${item.status === 'On-Site' ? `
                            <button class="pi-equipment-action-btn checkout" onclick="PI_Equipment.openCheckOutModal(${item.id})" title="Check Out">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </button>
                            ` : ''}
                            <button class="pi-equipment-action-btn issue" onclick="PI_Equipment.reportIssue(${item.id})" title="Report Issue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </button>
                            <button class="pi-equipment-action-btn delete" onclick="PI_Equipment.deleteEquipmentItem(${item.id})" title="Delete">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        },

        renderPagination() {
            const totalPages = Math.ceil(this.pagination.total / this.pagination.perPage);
            if (totalPages <= 1) {
                $('#pi-equipment-pagination').hide();
                return;
            }
            
            let html = `
                <button class="pi-equipment-page-btn" ${this.pagination.page === 1 ? 'disabled' : ''} onclick="PI_Equipment.goToPage(${this.pagination.page - 1})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                </button>
            `;
            
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.pagination.page - 1 && i <= this.pagination.page + 1)) {
                    html += `<button class="pi-equipment-page-btn ${i === this.pagination.page ? 'active' : ''}" onclick="PI_Equipment.goToPage(${i})">${i}</button>`;
                } else if (i === this.pagination.page - 2 || i === this.pagination.page + 2) {
                    html += `<span class="pi-equipment-page-btn" disabled>...</span>`;
                }
            }
            
            html += `
                <button class="pi-equipment-page-btn" ${this.pagination.page === totalPages ? 'disabled' : ''} onclick="PI_Equipment.goToPage(${this.pagination.page + 1})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            `;
            
            $('#pi-equipment-pagination').html(html).show();
        },

        // ============================================
        // DRAWER RENDERING
        // ============================================
        openDrawer() {
            this.drawerOpen = true;
            $('#pi-equipment-drawer-overlay').addClass('active');
            $('#pi-equipment-drawer').addClass('active');
            this.renderDrawerOverview();
        },

        closeDrawer() {
            this.drawerOpen = false;
            $('#pi-equipment-drawer-overlay').removeClass('active');
            $('#pi-equipment-drawer').removeClass('active');
            this.selectedEquipment = null;
        },

        renderDrawerOverview() {
            if (!this.selectedEquipment) return;
            
            const item = this.selectedEquipment;
            
            // Properly map all data fields with fallbacks
            const displayName = item.internal_name 
                || item.equipment_name 
                || item.name 
                || item.title 
                || item.equipment_type 
                || 'Unnamed Equipment';
            const displayType = item.equipment_type || item.category || 'Equipment';
            const manufacturer = item.manufacturer || item.make || '';
            const model = item.model || '';
            const acquisitionType = item.acquisition_type || item.ownership_type || 'Owned';
            const isOwned = acquisitionType.toLowerCase() === 'owned';
            const isHired = ['hired', 'rented', 'leased'].includes(acquisitionType.toLowerCase());
            
            // Update header
            $('#pi-equipment-drawer-title').text(displayName);
            $('#pi-equipment-drawer-subtitle').text(`${displayType}${manufacturer ? ' | ' + manufacturer : ''}${model ? ' ' + model : ''}`);
            
            // Format dates properly
            const fromDate = item.allocated_from_date ? this.formatDate(item.allocated_from_date) : 'Not set';
            const toDate = item.allocated_to_date ? this.formatDate(item.allocated_to_date) : (isHired ? 'Open-ended' : 'No end date');
            const onSiteDate = item.actual_on_site_date ? this.formatDate(item.actual_on_site_date) : (item.allocated_from_date ? this.formatDate(item.allocated_from_date) : '-');
            const returnDate = item.actual_return_date ? this.formatDate(item.actual_return_date) : (item.allocated_to_date ? this.formatDate(item.allocated_to_date) : '-');
            
            // Calculate or get cost
            const dailyRate = parseFloat(item.daily_rate || item.hire_rate || 0);
            const daysOnSite = item.days_on_site || this.calculateDaysOnSite(item.actual_on_site_date || item.allocated_from_date, item.actual_return_date);
            const costToJob = parseFloat(item.cost_to_job || (dailyRate * daysOnSite) || 0);
            
            // Overview tab content
            const overviewHtml = `
                <div class="pi-equipment-overview-grid">
                    <!-- Identification Section -->
                    <div class="pi-equipment-section">
                        <div class="pi-equipment-section-title">Identification</div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Internal Name</span>
                            <span class="pi-equipment-detail-value">${displayName}</span>
                        </div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Equipment Type</span>
                            <span class="pi-equipment-detail-value">${displayType}</span>
                        </div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Category</span>
                            <span class="pi-equipment-detail-value">${item.category || 'Uncategorized'}</span>
                        </div>
                        ${manufacturer ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Manufacturer</span>
                            <span class="pi-equipment-detail-value">${manufacturer}</span>
                        </div>
                        ` : ''}
                        ${model ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Model</span>
                            <span class="pi-equipment-detail-value">${model}</span>
                        </div>
                        ` : ''}
                        ${item.serial_number ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Serial Number</span>
                            <span class="pi-equipment-detail-value mono">${item.serial_number}</span>
                        </div>
                        ` : ''}
                        ${item.asset_tag ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Asset Tag</span>
                            <span class="pi-equipment-detail-value mono">${item.asset_tag}</span>
                        </div>
                        ` : ''}
                        ${item.year_of_manufacture ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Year</span>
                            <span class="pi-equipment-detail-value">${item.year_of_manufacture}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Ownership Section -->
                    <div class="pi-equipment-section">
                        <div class="pi-equipment-section-title">Ownership & Acquisition</div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Acquisition Type</span>
                            <span class="pi-equipment-detail-value">
                                <span class="pi-equipment-badge ${acquisitionType.toLowerCase().replace(/\s+/g, '-')}">${acquisitionType}</span>
                            </span>
                        </div>
                        ${item.supplier_name ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Supplier</span>
                            <span class="pi-equipment-detail-value">${item.supplier_name}</span>
                        </div>
                        ` : ''}
                        ${item.hire_reference_number ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Reference #</span>
                            <span class="pi-equipment-detail-value mono">${item.hire_reference_number}</span>
                        </div>
                        ` : ''}
                        ${item.delivery_method ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Delivery Method</span>
                            <span class="pi-equipment-detail-value">${item.delivery_method}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Financial Section - Conditional based on acquisition type -->
                    <div class="pi-equipment-section">
                        <div class="pi-equipment-section-title">${isOwned ? 'Ownership Costs' : 'Hire Costs'}</div>
                        <div class="pi-equipment-cost-card">
                            <h4>Cost to Job</h4>
                            <div class="pi-equipment-cost-value">${this.formatCurrency(costToJob)}</div>
                            <div class="pi-equipment-cost-breakdown">
                                ${!isOwned ? `
                                <div class="pi-equipment-cost-breakdown-item">
                                    <span>Rate Type</span>
                                    <span>${item.rate_type || 'Daily'}</span>
                                </div>
                                <div class="pi-equipment-cost-breakdown-item">
                                    <span>Daily Rate</span>
                                    <span>${this.formatCurrency(dailyRate)}</span>
                                </div>
                                ` : `
                                <div class="pi-equipment-cost-breakdown-item">
                                    <span>Ownership Type</span>
                                    <span>Company Owned</span>
                                </div>
                                <div class="pi-equipment-cost-breakdown-item">
                                    <span>Fuel & Maintenance</span>
                                    <span>See Costs Page</span>
                                </div>
                                `}
                                <div class="pi-equipment-cost-breakdown-item">
                                    <span>Days on Site</span>
                                    <span>${daysOnSite}</span>
                                </div>
                                ${item.deposit_held ? `
                                <div class="pi-equipment-cost-breakdown-item">
                                    <span>Deposit Held</span>
                                    <span>${this.formatCurrency(item.deposit_held)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allocation Dates Section -->
                    <div class="pi-equipment-section">
                        <div class="pi-equipment-section-title">Allocation Period</div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Expected Start</span>
                            <span class="pi-equipment-detail-value">${fromDate}</span>
                        </div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Expected End</span>
                            <span class="pi-equipment-detail-value">${toDate}</span>
                        </div>
                        ${item.actual_on_site_date ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Actual Arrival</span>
                            <span class="pi-equipment-detail-value">${onSiteDate}</span>
                        </div>
                        ` : ''}
                        ${item.actual_return_date ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Actual Return</span>
                            <span class="pi-equipment-detail-value">${returnDate}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Current Status Section -->
                    <div class="pi-equipment-section">
                        <div class="pi-equipment-section-title">Current Status</div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Status</span>
                            <span class="pi-equipment-detail-value">
                                <span class="pi-equipment-badge ${(item.status || 'On-Site').toLowerCase().replace(/\s+/g, '-')}">${item.status || 'On-Site'}</span>
                            </span>
                        </div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Current Location</span>
                            <span class="pi-equipment-detail-value">${item.current_location_on_site || 'Not specified'}</span>
                        </div>
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Condition</span>
                            <span class="pi-equipment-detail-value ${(item.current_condition || 'Good').toLowerCase() === 'damaged' || (item.current_condition || 'Good').toLowerCase() === 'poor' ? 'warning' : ''}">${item.current_condition || 'Good'}</span>
                        </div>
                        ${item.hours_meter_reading ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Hours Meter</span>
                            <span class="pi-equipment-detail-value mono">${item.hours_meter_reading}</span>
                        </div>
                        ` : ''}
                        ${item.condition_notes ? `
                        <div class="pi-equipment-detail-row full-width">
                            <span class="pi-equipment-detail-label">Condition Notes</span>
                            <span class="pi-equipment-detail-value">${item.condition_notes}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Personnel Section -->
                    <div class="pi-equipment-section">
                        <div class="pi-equipment-section-title">Personnel</div>
                        ${item.operator_name ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Assigned Operator</span>
                            <span class="pi-equipment-detail-value">
                                <div class="pi-equipment-operator-card compact">
                                    <div class="pi-equipment-operator-card-avatar">${this.getInitials(item.operator_name)}</div>
                                    <div class="pi-equipment-operator-card-info">
                                        <div class="pi-equipment-operator-card-name">${item.operator_name}</div>
                                        ${item.operator_certification_required ? `<div class="pi-equipment-operator-card-role">Cert: ${item.operator_certification_required}</div>` : ''}
                                    </div>
                                </div>
                            </span>
                        </div>
                        ` : '<div class="pi-equipment-detail-row"><span class="pi-equipment-detail-label">Assigned Operator</span><span class="pi-equipment-detail-value">Not assigned</span></div>'}
                        ${item.supervisor_name ? `
                        <div class="pi-equipment-detail-row">
                            <span class="pi-equipment-detail-label">Supervisor</span>
                            <span class="pi-equipment-detail-value">${item.supervisor_name}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            $('#pi-equipment-tab-overview').html(overviewHtml);
            
            // Render timeline
            this.renderDrawerTimeline();
            
            // Render inspections
            this.renderDrawerInspections();
        },

        calculateDaysOnSite(startDate, endDate) {
            if (!startDate) return 0;
            const start = new Date(startDate);
            const end = endDate ? new Date(endDate) : new Date();
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays;
        },

        renderDrawerTimeline() {
            if (!this.selectedEquipment?.timeline) return;
            
            const html = this.selectedEquipment.timeline.map(event => `
                <div class="pi-equipment-timeline-item">
                    <div class="pi-equipment-timeline-dot ${event.event_type}"></div>
                    <div class="pi-equipment-timeline-content">
                        <div class="pi-equipment-timeline-title">${event.event_title}</div>
                        ${event.event_description ? `<div class="pi-equipment-timeline-desc">${event.event_description}</div>` : ''}
                        <div class="pi-equipment-timeline-meta">
                            ${event.performed_by_name} • ${this.formatDateTime(event.created_at)}
                        </div>
                    </div>
                </div>
            `).join('');
            
            $('#pi-equipment-tab-timeline').html(`<div class="pi-equipment-timeline">${html || '<p style="color: #64748b; text-align: center; padding: 40px;">No activity recorded</p>'}</div>`);
        },

        renderDrawerInspections() {
            if (!this.selectedEquipment?.inspections) return;
            
            const html = this.selectedEquipment.inspections.map(inspection => `
                <div class="pi-equipment-inspection-item ${inspection.inspection_status.toLowerCase()}">
                    <div class="pi-equipment-inspection-icon ${inspection.inspection_status.toLowerCase()}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${inspection.inspection_status === 'Pass' 
                                ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
                                : inspection.inspection_status === 'Fail'
                                ? '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'
                                : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
                            }
                        </svg>
                    </div>
                    <div class="pi-equipment-inspection-info">
                        <div class="pi-equipment-inspection-date">${this.formatDate(inspection.inspection_date)}</div>
                        <div class="pi-equipment-inspection-inspector">By ${inspection.inspected_by_name || 'Unknown'}</div>
                        <span class="pi-equipment-inspection-status ${inspection.inspection_status.toLowerCase()}">${inspection.inspection_status}</span>
                        ${inspection.notes ? `<div class="pi-equipment-inspection-notes">${inspection.notes}</div>` : ''}
                    </div>
                </div>
            `).join('');
            
            $('#pi-equipment-tab-inspections').html(`<div class="pi-equipment-inspections-list">${html || '<p style="color: #64748b; text-align: center; padding: 40px;">No inspections recorded</p>'}</div>`);
        },

        // ============================================
        // FILTERING & SORTING
        // ============================================
        filterEquipment() {
            let filtered = [...this.equipment];
            
            if (this.currentFilter !== 'all') {
                switch (this.currentFilter) {
                    case 'on-site':
                        filtered = filtered.filter(e => (e.status || 'On-Site') === 'On-Site');
                        break;
                    case 'hired':
                        filtered = filtered.filter(e => (e.acquisition_type || '').toLowerCase() === 'hired' || (e.acquisition_type || '').toLowerCase() === 'rented');
                        break;
                    case 'owned':
                        filtered = filtered.filter(e => (e.acquisition_type || '').toLowerCase() === 'owned');
                        break;
                    case 'attention':
                        filtered = filtered.filter(e => 
                            (e.current_condition || '').toLowerCase() === 'damaged' ||
                            (e.next_inspection_due && new Date(e.next_inspection_due) < new Date()) ||
                            (e.allocated_to_date && new Date(e.allocated_to_date) < new Date() && !e.actual_return_date)
                        );
                        break;
                    case 'overdue':
                        filtered = filtered.filter(e => 
                            e.allocated_to_date && new Date(e.allocated_to_date) < new Date() && !e.actual_return_date
                        );
                        break;
                }
            }
            
            return filtered;
        },

        sortEquipment(equipment) {
            return equipment.sort((a, b) => {
                let valA = a[this.currentSort.field] || '';
                let valB = b[this.currentSort.field] || '';
                
                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();
                
                if (valA < valB) return this.currentSort.direction === 'asc' ? -1 : 1;
                if (valA > valB) return this.currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
        },

        // ============================================
        // MODALS
        // ============================================
        openAddModal() {
            const html = `
                <div class="pi-equipment-modal-tabs">
                    <button type="button" class="pi-equipment-modal-tab active" data-tab="details" onclick="PI_Equipment.switchModalTab('details')">Details</button>
                    <button type="button" class="pi-equipment-modal-tab" data-tab="allocation" onclick="PI_Equipment.switchModalTab('allocation')">Allocation</button>
                    <button type="button" class="pi-equipment-modal-tab" data-tab="condition" onclick="PI_Equipment.switchModalTab('condition')">Condition</button>
                </div>
                
                <form id="pi-equipment-add-form">
                    <!-- Details Tab -->
                    <div class="pi-equipment-modal-panel active" data-panel="details">
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Equipment Details</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Equipment Type <span class="required">*</span></label>
                                    <select name="equipment_type" required>
                                        <option value="">Select type...</option>
                                        ${Object.keys(this.equipmentTypes).map(t => `<option value="${t}">${t}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Category <span class="required">*</span></label>
                                    <select name="category" required>
                                        <option value="Heavy Machinery">Heavy Machinery</option>
                                        <option value="Light Machinery">Light Machinery</option>
                                        <option value="Tooling">Tooling</option>
                                        <option value="Vehicle">Vehicle</option>
                                        <option value="Temporary Structure">Temporary Structure</option>
                                        <option value="Safety Gear">Safety Gear</option>
                                        <option value="Consumable">Consumable</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group full-width">
                                    <label>Internal Name / Nickname <span class="required">*</span></label>
                                    <input type="text" name="internal_name" placeholder="e.g., Big Digger, Scaffold Pack A" required>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Manufacturer / Brand</label>
                                    <input type="text" name="manufacturer" placeholder="e.g., JCB, Caterpillar">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Model</label>
                                    <input type="text" name="model" placeholder="e.g., 3CX, 320D">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Serial Number</label>
                                    <input type="text" name="serial_number">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Asset Tag</label>
                                    <input type="text" name="asset_tag">
                                </div>
                            </div>
                        </div>
                        
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Ownership & Financial</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Acquisition Type</label>
                                    <select name="acquisition_type" id="add-acquisition-type" onchange="PI_Equipment.toggleAcquisitionFields()">
                                        <option value="Owned">Owned</option>
                                        <option value="Hired">Hired</option>
                                        <option value="Rented">Rented</option>
                                        <option value="Leased">Leased</option>
                                        <option value="Subcontractor Supplied">Subcontractor Supplied</option>
                                        <option value="Client Supplied">Client Supplied</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group hire-only" style="display:none;">
                                    <label>Supplier / Hire Company</label>
                                    <input type="text" name="supplier_name">
                                </div>
                                <div class="pi-equipment-form-group hire-only" style="display:none;">
                                    <label>Hire Reference / PO Number</label>
                                    <input type="text" name="hire_reference_number">
                                </div>
                                <div class="pi-equipment-form-group rate-field" style="display:none;">
                                    <label>Rate Type</label>
                                    <select name="rate_type">
                                        <option value="daily">Daily Rate</option>
                                        <option value="weekly">Weekly Rate</option>
                                        <option value="monthly">Monthly Rate</option>
                                        <option value="flat">Flat Fee</option>
                                        <option value="hourly">Cost-per-hour</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group rate-field" style="display:none;">
                                    <label>Daily / Hire Rate (£)</label>
                                    <input type="number" name="daily_rate" step="0.01" value="0">
                                </div>
                                <div class="pi-equipment-form-group owned-only">
                                    <label class="owned-note">Fuel & maintenance costs tracked on Costs page</label>
                                </div>
                                <div class="pi-equipment-form-group hire-only" style="display:none;">
                                    <label>Deposit Held (£)</label>
                                    <input type="number" name="deposit_held" step="0.01" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allocation Tab -->
                    <div class="pi-equipment-modal-panel" data-panel="allocation">
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Job Allocation</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Allocation Start Date</label>
                                    <input type="datetime-local" name="allocated_from_date">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Allocation End Date</label>
                                    <input type="datetime-local" name="allocated_to_date">
                                    <span class="help-text">Leave empty for open-ended hire</span>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Delivery Method</label>
                                    <select name="delivery_method" id="add-delivery-method" onchange="PI_Equipment.updateStatusFromDeliveryMethod()">
                                        <option value="Supplier Delivery">Supplier Delivery</option>
                                        <option value="Self-Collection">Self-Collection</option>
                                        <option value="Third-Party Haulage">Third-Party Haulage</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Status</label>
                                    <select name="status" id="add-status">
                                        <option value="On-Site">On-Site</option>
                                        <option value="En Route">En Route</option>
                                        <option value="Off-Site / Returned">Off-Site / Returned</option>
                                        <option value="In Maintenance">In Maintenance</option>
                                        <option value="Idle / Standby">Idle / Standby</option>
                                        <option value="Damaged">Damaged</option>
                                        <option value="Awaiting Delivery" selected>Awaiting Delivery</option>
                                        <option value="Awaiting Collection">Awaiting Collection</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group full-width">
                                    <label>Expected Location on Site</label>
                                    <input type="text" name="current_location_on_site" placeholder="e.g., Rear Garden, Front Drive">
                                </div>
                            </div>
                        </div>
                        
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Operator Assignment</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Assigned Operator</label>
                                    <select name="assigned_operator_id">
                                        <option value="">Select operator...</option>
                                        ${this.employees.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name} (${e.role})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Certification Required</label>
                                    <select name="operator_certification_required">
                                        <option value="">None</option>
                                        <option value="CPCS">CPCS</option>
                                        <option value="NPORS">NPORS</option>
                                        <option value="IPAF">IPAF</option>
                                        <option value="PASMA">PASMA</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Supervisor Responsible</label>
                                    <select name="supervisor_responsible_id">
                                        <option value="">Select supervisor...</option>
                                        ${this.employees.filter(e => e.role === 'Site Manager' || e.role === 'Foreman' || e.permissions_level === 'supervisor').map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`).join('')}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Condition Tab -->
                    <div class="pi-equipment-modal-panel" data-panel="condition">
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Initial Condition</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Current Condition</label>
                                    <select name="current_condition">
                                        <option value="Excellent">Excellent</option>
                                        <option value="Good" selected>Good</option>
                                        <option value="Fair">Fair</option>
                                        <option value="Poor">Poor</option>
                                        <option value="Damaged">Damaged</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Hours Meter Reading</label>
                                    <input type="number" name="hours_meter_reading" step="0.1" placeholder="Current hours">
                                </div>
                                <div class="pi-equipment-form-group full-width">
                                    <label>Condition Notes</label>
                                    <textarea name="condition_notes" rows="3" placeholder="Any observations about condition..."></textarea>
                                </div>
                                <div class="pi-equipment-form-group full-width">
                                    <label>Initial Photos</label>
                                    <div class="pi-equipment-photo-upload" onclick="document.getElementById('add-equipment-photos').click()">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                        <div class="pi-equipment-photo-upload-text">Click to upload photos of equipment condition</div>
                                        <input type="file" id="add-equipment-photos" multiple accept="image/*" style="display: none;" onchange="PI_Equipment.handlePhotoPreview(this, 'add-photo-preview')">
                                    </div>
                                    <div class="pi-equipment-photo-preview" id="add-photo-preview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            `;
            
            this.showModal('Add Equipment', html, [
                { text: 'Cancel', class: 'pi-equipment-btn-secondary', action: 'close' },
                { text: 'Add Equipment', class: 'pi-equipment-btn-primary', action: 'submit' }
            ], (formData) => this.handleAddEquipment(formData));
            
            // Set initial status based on delivery method
            setTimeout(() => this.updateStatusFromDeliveryMethod(), 100);
        },

        switchModalTab(tab) {
            // Update tab buttons
            $('.pi-equipment-modal-tab').removeClass('active');
            $(`.pi-equipment-modal-tab[data-tab="${tab}"]`).addClass('active');
            
            // Update panels
            $('.pi-equipment-modal-panel').removeClass('active');
            $(`.pi-equipment-modal-panel[data-panel="${tab}"]`).addClass('active');
        },

        toggleAcquisitionFields() {
            const acquisitionType = $('#add-acquisition-type').val();
            const isHired = ['Hired', 'Rented', 'Leased'].includes(acquisitionType);
            const isOwned = acquisitionType === 'Owned';
            
            // Show/hide hire-specific fields
            $('.hire-only').toggle(isHired);
            
            // Show/hide rate fields for hired equipment
            $('.rate-field').toggle(isHired);
            
            // Show/hide owned note for owned equipment
            $('.owned-only').toggle(isOwned);
        },

        updateStatusFromDeliveryMethod() {
            const deliveryMethod = $('#add-delivery-method').val();
            const statusSelect = $('#add-status');
            
            if (deliveryMethod === 'Self-Collection') {
                statusSelect.val('Awaiting Collection');
            } else {
                statusSelect.val('Awaiting Delivery');
            }
        },

        toggleEditAcquisitionFields() {
            const acquisitionType = $('#edit-acquisition-type').val();
            const isHired = ['Hired', 'Rented', 'Leased'].includes(acquisitionType);
            const isOwned = acquisitionType === 'Owned';
            
            // Show/hide hire-specific fields
            $('.hire-only').toggle(isHired);
            
            // Show/hide rate fields for hired equipment
            $('.rate-field').toggle(isHired);
            
            // Show/hide owned note for owned equipment
            $('.owned-only').toggle(isOwned);
        },

        handlePhotoPreview(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('div');
                        img.className = 'pi-equipment-photo-preview-item';
                        img.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            }
        },

        openCheckInModal(id) {
            const equipment = this.equipment.find(e => e.id === id);
            if (!equipment) return;
            
            const html = `
                <form id="pi-equipment-checkin-form" data-id="${id}">
                    <div class="pi-equipment-form-grid">
                        <div class="pi-equipment-form-group">
                            <label>Arrival Date/Time <span class="required">*</span></label>
                            <input type="datetime-local" name="actual_on_site_date" value="${new Date().toISOString().slice(0, 16)}" required>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Location on Site <span class="required">*</span></label>
                            <input type="text" name="location" placeholder="e.g., Rear Garden" required>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Hours Meter Reading</label>
                            <input type="number" name="hours_meter_reading" step="0.1" placeholder="Current reading">
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Fuel Level</label>
                            <select name="fuel_level">
                                <option value="">Select level...</option>
                                <option value="Full">Full</option>
                                <option value="3/4">3/4</option>
                                <option value="1/2">1/2</option>
                                <option value="1/4">1/4</option>
                                <option value="Empty">Empty</option>
                            </select>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Condition <span class="required">*</span></label>
                            <select name="condition" required>
                                <option value="Excellent">Excellent</option>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Assign Operator</label>
                            <select name="assigned_operator_id">
                                <option value="">Select operator...</option>
                                ${this.employees.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`).join('')}
                            </select>
                        </div>
                        <div class="pi-equipment-form-group full-width">
                            <label>Condition Notes</label>
                            <textarea name="notes" rows="3" placeholder="Any observations about condition..."></textarea>
                        </div>
                        <div class="pi-equipment-form-group full-width">
                            <label>Photos</label>
                            <div class="pi-equipment-photo-upload" onclick="document.getElementById('checkin-photos').click()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <div class="pi-equipment-photo-upload-text">Click to upload photos of arrival condition</div>
                                <input type="file" id="checkin-photos" multiple accept="image/*" style="display: none;">
                            </div>
                            <div class="pi-equipment-photo-preview" id="checkin-photo-preview"></div>
                        </div>
                    </div>
                </form>
            `;
            
            this.showModal('Check In Equipment', html, [
                { text: 'Cancel', class: 'pi-equipment-btn-secondary', action: 'close' },
                { text: 'Check In', class: 'pi-equipment-btn-primary', action: 'submit' }
            ], (formData) => this.handleCheckIn(id, formData));
        },

        openCheckOutModal(id) {
            const equipment = this.equipment.find(e => e.id === id);
            if (!equipment) return;
            
            const html = `
                <form id="pi-equipment-checkout-form" data-id="${id}">
                    <div class="pi-equipment-form-grid">
                        <div class="pi-equipment-form-group">
                            <label>Return Date/Time <span class="required">*</span></label>
                            <input type="datetime-local" name="actual_return_date" value="${new Date().toISOString().slice(0, 16)}" required>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Next Status <span class="required">*</span></label>
                            <select name="next_status" required>
                                <option value="Off-Site / Returned">Return to Depot</option>
                                <option value="En Route">Transfer to Another Job</option>
                                <option value="In Maintenance">Send to Maintenance</option>
                            </select>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Hours Meter Reading</label>
                            <input type="number" name="hours_meter_reading" step="0.1" placeholder="Current reading">
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Fuel Level</label>
                            <select name="fuel_level">
                                <option value="">Select level...</option>
                                <option value="Full">Full</option>
                                <option value="3/4">3/4</option>
                                <option value="1/2">1/2</option>
                                <option value="1/4">1/4</option>
                                <option value="Empty">Empty</option>
                            </select>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Condition on Return <span class="required">*</span></label>
                            <select name="condition" required>
                                <option value="Excellent">Excellent</option>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                        <div class="pi-equipment-form-group">
                            <label>Transfer to Job (if applicable)</label>
                            <input type="text" name="next_job_allocation" placeholder="Job ID or Name">
                        </div>
                        <div class="pi-equipment-form-group full-width">
                            <label>Return Notes</label>
                            <textarea name="notes" rows="3" placeholder="Any observations..."></textarea>
                        </div>
                    </div>
                </form>
            `;
            
            this.showModal('Check Out Equipment', html, [
                { text: 'Cancel', class: 'pi-equipment-btn-secondary', action: 'close' },
                { text: 'Check Out', class: 'pi-equipment-btn-primary', action: 'submit' }
            ], (formData) => this.handleCheckOut(id, formData));
        },

        showModal(title, body, buttons, onSubmit) {
            const buttonsHtml = buttons.map(btn => 
                `<button type="${btn.action === 'submit' ? 'submit' : 'button'}" 
                    class="pi-equipment-btn ${btn.class}" 
                    ${btn.action === 'close' ? 'onclick="PI_Equipment.closeModal()"' : ''}
                    ${btn.action === 'submit' ? 'id="pi-equipment-modal-submit"' : ''}>
                    ${btn.text}
                </button>`
            ).join('');
            
            const html = `
                <div class="pi-equipment-modal-overlay active" id="pi-equipment-modal">
                    <div class="pi-equipment-modal">
                        <div class="pi-equipment-modal-header">
                            <h3>${title}</h3>
                            <button class="pi-equipment-modal-close" onclick="PI_Equipment.closeModal()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="pi-equipment-modal-body">${body}</div>
                        <div class="pi-equipment-modal-footer">${buttonsHtml}</div>
                    </div>
                </div>
            `;
            
            $('body').append(html);
            
            if (onSubmit) {
                $('#pi-equipment-modal-submit').on('click', (e) => {
                    e.preventDefault();
                    const form = $('#pi-equipment-modal form');
                    if (form[0].checkValidity()) {
                        const formData = new FormData(form[0]);
                        const data = {};
                        formData.forEach((value, key) => {
                            data[key] = value;
                        });
                        console.log('[Equipment] Form data being submitted:', data);
                        onSubmit(data);
                    } else {
                        form[0].reportValidity();
                    }
                });
            }
        },

        closeModal() {
            $('#pi-equipment-modal').remove();
        },

        // ============================================
        // FORM HANDLERS
        // ============================================
        async handleAddEquipment(data) {
            try {
                data.job_id = this.jobId;
                console.log('[Equipment] Submitting to API:', data);
                const response = await this.api.createEquipment(data);
                console.log('[Equipment] API response:', response);
                if (response._debug) {
                    console.log('[Equipment] API debug info:', response._debug);
                }
                this.closeModal();
                this.showToast('Equipment added successfully');
                this.loadEquipment();
            } catch (error) {
                console.error('[Equipment] Failed to add:', error);
                this.showToast('Failed to add equipment', 'error');
            }
        },

        async handleCheckIn(id, data) {
            console.log('[Equipment] handleCheckIn called with id:', id, 'data:', data);
            try {
                // Explicitly set status to On-Site when checking in
                data.status = 'On-Site';
                console.log('[Equipment] Calling API checkIn with data:', data);
                const response = await this.api.checkIn(id, data);
                console.log('[Equipment] Check in response:', response);
                this.closeModal();
                this.showToast('Equipment checked in successfully');
                this.loadEquipment();
            } catch (error) {
                console.error('[Equipment] Failed to check in:', error);
                this.showToast('Failed to check in equipment', 'error');
            }
        },

        async handleCheckOut(id, data) {
            console.log('[Equipment] handleCheckOut called with id:', id, 'data:', data);
            try {
                // Set status from the next_status field
                if (data.next_status) {
                    data.status = data.next_status;
                }
                console.log('[Equipment] Calling API checkOut with data:', data);
                const response = await this.api.checkOut(id, data);
                console.log('[Equipment] Check out response:', response);
                this.closeModal();
                this.showToast('Equipment checked out successfully');
                this.loadEquipment();
            } catch (error) {
                console.error('[Equipment] Failed to check out:', error);
                this.showToast('Failed to check out equipment', 'error');
            }
        },

        // ============================================
        // EVENT HANDLERS
        // ============================================
        bindEvents() {
            // Filter buttons
            $(document).on('click', '.pi-equipment-filter', (e) => {
                $('.pi-equipment-filter').removeClass('active');
                $(e.currentTarget).addClass('active');
                this.currentFilter = $(e.currentTarget).data('filter');
                this.pagination.page = 1;
                this.renderTable();
            });
            
            // View toggle
            $(document).on('click', '.pi-equipment-view-btn', (e) => {
                $('.pi-equipment-view-btn').removeClass('active');
                $(e.currentTarget).addClass('active');
                this.currentView = $(e.currentTarget).data('view');
                this.renderTable();
            });
            
            // Drawer tabs
            $(document).on('click', '.pi-equipment-drawer-tab', (e) => {
                const tab = $(e.currentTarget).data('tab');
                $('.pi-equipment-drawer-tab').removeClass('active');
                $(e.currentTarget).addClass('active');
                $('.pi-equipment-tab-panel').removeClass('active');
                $(`#pi-equipment-tab-${tab}`).addClass('active');
            });
            
            // Close drawer
            $(document).on('click', '#pi-equipment-drawer-overlay, #pi-equipment-drawer-close', () => {
                this.closeDrawer();
            });
        },

        // ============================================
        // UTILITY METHODS
        // ============================================
        getDarkerColor(hex) {
            // Convert hex to RGB
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            
            // Darken by 40%
            const factor = 0.6;
            const darkerR = Math.round(r * factor);
            const darkerG = Math.round(g * factor);
            const darkerB = Math.round(b * factor);
            
            return `#${darkerR.toString(16).padStart(2, '0')}${darkerG.toString(16).padStart(2, '0')}${darkerB.toString(16).padStart(2, '0')}`;
        },

        calculateStatus(item) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            const returnDate = item.actual_return_date ? new Date(item.actual_return_date) : null;
            const onSiteDate = item.actual_on_site_date ? new Date(item.actual_on_site_date) : null;
            
            // If equipment has been returned, it's off-site
            if (returnDate && returnDate <= today) {
                return 'Off-Site / Returned';
            }
            
            // If equipment has been checked in (actual_on_site_date set), it's on-site
            if (onSiteDate && onSiteDate <= today) {
                return 'On-Site';
            }
            
            // If status was manually set (check-in/check-out or manual edit), respect it
            // Unless it's a calculated status that should be recalculated
            const manualStatuses = ['On-Site', 'Off-Site / Returned', 'In Maintenance', 'Idle / Standby', 'Damaged', 'Awaiting Delivery', 'Awaiting Collection'];
            if (item.status && manualStatuses.includes(item.status)) {
                return item.status;
            }
            
            // Calculate default status based on dates and delivery method
            const fromDate = item.allocated_from_date ? new Date(item.allocated_from_date) : null;
            const toDate = item.allocated_to_date ? new Date(item.allocated_to_date) : null;
            const deliveryMethod = item.delivery_method || 'Supplier Delivery';
            
            // If no from date, use delivery method to determine default
            if (!fromDate) {
                if (deliveryMethod === 'Self-Collection') {
                    return 'Awaiting Collection';
                } else {
                    return 'Awaiting Delivery';
                }
            }
            
            // Check if today is within the date range
            const isAfterFrom = today >= fromDate;
            const isBeforeTo = !toDate || today <= toDate;
            
            if (isAfterFrom && isBeforeTo) {
                return 'On-Site';
            } else if (!isAfterFrom) {
                // Today is before the start date - use delivery method
                if (deliveryMethod === 'Self-Collection') {
                    return 'Awaiting Collection';
                } else {
                    return 'Awaiting Delivery';
                }
            } else {
                // Today is after the end date but not returned
                return 'Overdue';
            }
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(amount || 0);
        },

        formatDate(date) {
            if (!date) return '-';
            return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        formatDateTime(date) {
            if (!date) return '-';
            return new Date(date).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        getInitials(name) {
            if (!name) return '?';
            return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        goToPage(page) {
            this.pagination.page = page;
            this.renderTable();
        },

        viewEquipment(id) {
            this.loadEquipmentDetail(id);
        },

        editEquipment(id) {
            console.log('[Equipment] editEquipment called with id:', id);
            console.log('[Equipment] Available equipment count:', this.equipment.length);
            console.log('[Equipment] Equipment array:', this.equipment);
            
            const equipment = this.equipment.find(e => e.id == id);
            console.log('[Equipment] Found equipment:', equipment);
            
            if (!equipment) {
                console.error('[Equipment] Equipment not found for id:', id);
                // Try to load equipment data first
                console.log('[Equipment] Attempting to reload equipment data...');
                this.loadEquipment().then(() => {
                    console.log('[Equipment] Equipment reloaded, count:', this.equipment.length);
                    const equipment = this.equipment.find(e => e.id == id);
                    console.log('[Equipment] Equipment found after reload:', equipment);
                    if (equipment) {
                        this.openEditModal(equipment);
                    } else {
                        console.error('[Equipment] Still cannot find equipment after reload');
                        this.showToast('Equipment not found', 'error');
                    }
                }).catch(err => {
                    console.error('[Equipment] Failed to load equipment:', err);
                    this.showToast('Failed to load equipment data', 'error');
                });
                return;
            }
            
            console.log('[Equipment] Opening edit modal for equipment:', equipment);
            try {
                this.openEditModal(equipment);
            } catch (error) {
                console.error('[Equipment] Error opening edit modal:', error);
                this.showToast('Failed to open edit modal', 'error');
            }
        },

        editCurrentEquipment() {
            console.log('[Equipment] editCurrentEquipment called');
            console.log('[Equipment] selectedEquipment:', this.selectedEquipment);
            if (this.selectedEquipment) {
                console.log('[Equipment] Closing drawer and opening edit modal');
                // Save equipment data before closing drawer (closeDrawer sets selectedEquipment to null)
                const equipmentToEdit = this.selectedEquipment;
                this.closeDrawer();
                this.openEditModal(equipmentToEdit);
            } else {
                console.error('[Equipment] No selectedEquipment found');
                this.showToast('No equipment selected', 'error');
            }
        },

        openEditModal(equipment) {
            console.log('[Equipment] openEditModal called with:', equipment);
            console.log('[Equipment] Equipment data keys:', Object.keys(equipment));
            
            try {
                const html = `
                <div class="pi-equipment-modal-tabs">
                    <button type="button" class="pi-equipment-modal-tab active" data-tab="details" onclick="PI_Equipment.switchModalTab('details')">Details</button>
                    <button type="button" class="pi-equipment-modal-tab" data-tab="allocation" onclick="PI_Equipment.switchModalTab('allocation')">Allocation</button>
                    <button type="button" class="pi-equipment-modal-tab" data-tab="condition" onclick="PI_Equipment.switchModalTab('condition')">Condition</button>
                </div>
                
                <form id="pi-equipment-edit-form" data-id="${equipment.id}">
                    <!-- Details Tab -->
                    <div class="pi-equipment-modal-panel active" data-panel="details">
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Equipment Details</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Equipment Type <span class="required">*</span></label>
                                    <select name="equipment_type" required>
                                        <option value="">Select type...</option>
                                        ${Object.keys(this.equipmentTypes).map(t => `<option value="${t}" ${equipment.equipment_type === t ? 'selected' : ''}>${t}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Category <span class="required">*</span></label>
                                    <select name="category" required>
                                        <option value="Heavy Machinery" ${equipment.category === 'Heavy Machinery' ? 'selected' : ''}>Heavy Machinery</option>
                                        <option value="Light Machinery" ${equipment.category === 'Light Machinery' ? 'selected' : ''}>Light Machinery</option>
                                        <option value="Tooling" ${equipment.category === 'Tooling' ? 'selected' : ''}>Tooling</option>
                                        <option value="Vehicle" ${equipment.category === 'Vehicle' ? 'selected' : ''}>Vehicle</option>
                                        <option value="Temporary Structure" ${equipment.category === 'Temporary Structure' ? 'selected' : ''}>Temporary Structure</option>
                                        <option value="Safety Gear" ${equipment.category === 'Safety Gear' ? 'selected' : ''}>Safety Gear</option>
                                        <option value="Consumable" ${equipment.category === 'Consumable' ? 'selected' : ''}>Consumable</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group full-width">
                                    <label>Internal Name / Nickname <span class="required">*</span></label>
                                    <input type="text" name="internal_name" value="${this.escapeHtml(equipment.internal_name || '')}" required>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Manufacturer / Brand</label>
                                    <input type="text" name="manufacturer" value="${this.escapeHtml(equipment.manufacturer || '')}">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Model</label>
                                    <input type="text" name="model" value="${this.escapeHtml(equipment.model || '')}">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Serial Number</label>
                                    <input type="text" name="serial_number" value="${this.escapeHtml(equipment.serial_number || '')}">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Asset Tag</label>
                                    <input type="text" name="asset_tag" value="${this.escapeHtml(equipment.asset_tag || '')}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Ownership & Financial</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Acquisition Type</label>
                                    <select name="acquisition_type" id="edit-acquisition-type" onchange="PI_Equipment.toggleEditAcquisitionFields()">
                                        <option value="Owned" ${equipment.acquisition_type === 'Owned' ? 'selected' : ''}>Owned</option>
                                        <option value="Hired" ${equipment.acquisition_type === 'Hired' ? 'selected' : ''}>Hired</option>
                                        <option value="Rented" ${equipment.acquisition_type === 'Rented' ? 'selected' : ''}>Rented</option>
                                        <option value="Leased" ${equipment.acquisition_type === 'Leased' ? 'selected' : ''}>Leased</option>
                                        <option value="Subcontractor Supplied" ${equipment.acquisition_type === 'Subcontractor Supplied' ? 'selected' : ''}>Subcontractor Supplied</option>
                                        <option value="Client Supplied" ${equipment.acquisition_type === 'Client Supplied' ? 'selected' : ''}>Client Supplied</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group hire-only" style="display:${['Hired', 'Rented', 'Leased'].includes(equipment.acquisition_type) ? 'block' : 'none'};">
                                    <label>Supplier / Hire Company</label>
                                    <input type="text" name="supplier_name" value="${this.escapeHtml(equipment.supplier_name || '')}">
                                </div>
                                <div class="pi-equipment-form-group hire-only" style="display:${['Hired', 'Rented', 'Leased'].includes(equipment.acquisition_type) ? 'block' : 'none'};">
                                    <label>Hire Reference / PO Number</label>
                                    <input type="text" name="hire_reference_number" value="${this.escapeHtml(equipment.hire_reference_number || '')}">
                                </div>
                                <div class="pi-equipment-form-group rate-field" style="display:${['Hired', 'Rented', 'Leased'].includes(equipment.acquisition_type) ? 'block' : 'none'};">
                                    <label>Rate Type</label>
                                    <select name="rate_type">
                                        <option value="daily" ${equipment.rate_type === 'daily' ? 'selected' : ''}>Daily Rate</option>
                                        <option value="weekly" ${equipment.rate_type === 'weekly' ? 'selected' : ''}>Weekly Rate</option>
                                        <option value="monthly" ${equipment.rate_type === 'monthly' ? 'selected' : ''}>Monthly Rate</option>
                                        <option value="flat" ${equipment.rate_type === 'flat' ? 'selected' : ''}>Flat Fee</option>
                                        <option value="hourly" ${equipment.rate_type === 'hourly' ? 'selected' : ''}>Cost-per-hour</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group rate-field" style="display:${['Hired', 'Rented', 'Leased'].includes(equipment.acquisition_type) ? 'block' : 'none'};">
                                    <label>Daily / Hire Rate (£)</label>
                                    <input type="number" name="daily_rate" step="0.01" value="${equipment.daily_rate || 0}">
                                </div>
                                <div class="pi-equipment-form-group owned-only" style="display:${equipment.acquisition_type === 'Owned' ? 'block' : 'none'};">
                                    <label class="owned-note">Fuel & maintenance costs tracked on Costs page</label>
                                </div>
                                <div class="pi-equipment-form-group hire-only" style="display:${['Hired', 'Rented', 'Leased'].includes(equipment.acquisition_type) ? 'block' : 'none'};">
                                    <label>Deposit Held (£)</label>
                                    <input type="number" name="deposit_held" step="0.01" value="${equipment.deposit_held || 0}">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allocation Tab -->
                    <div class="pi-equipment-modal-panel" data-panel="allocation">
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Job Allocation</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Allocation Start Date</label>
                                    <input type="datetime-local" name="allocated_from_date" value="${equipment.allocated_from_date ? equipment.allocated_from_date.slice(0, 16) : ''}">
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Allocation End Date</label>
                                    <input type="datetime-local" name="allocated_to_date" value="${equipment.allocated_to_date ? equipment.allocated_to_date.slice(0, 16) : ''}">
                                    <span class="help-text">Leave empty for open-ended hire</span>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Delivery Method</label>
                                    <select name="delivery_method">
                                        <option value="Supplier Delivery" ${equipment.delivery_method === 'Supplier Delivery' ? 'selected' : ''}>Supplier Delivery</option>
                                        <option value="Self-Collection" ${equipment.delivery_method === 'Self-Collection' ? 'selected' : ''}>Self-Collection</option>
                                        <option value="Third-Party Haulage" ${equipment.delivery_method === 'Third-Party Haulage' ? 'selected' : ''}>Third-Party Haulage</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="On-Site" ${equipment.status === 'On-Site' ? 'selected' : ''}>On-Site</option>
                                        <option value="En Route" ${equipment.status === 'En Route' ? 'selected' : ''}>En Route</option>
                                        <option value="Off-Site / Returned" ${equipment.status === 'Off-Site / Returned' ? 'selected' : ''}>Off-Site / Returned</option>
                                        <option value="In Maintenance" ${equipment.status === 'In Maintenance' ? 'selected' : ''}>In Maintenance</option>
                                        <option value="Idle / Standby" ${equipment.status === 'Idle / Standby' ? 'selected' : ''}>Idle / Standby</option>
                                        <option value="Damaged" ${equipment.status === 'Damaged' ? 'selected' : ''}>Damaged</option>
                                        <option value="Awaiting Delivery" ${equipment.status === 'Awaiting Delivery' ? 'selected' : ''}>Awaiting Delivery</option>
                                        <option value="Awaiting Collection" ${equipment.status === 'Awaiting Collection' ? 'selected' : ''}>Awaiting Collection</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group full-width">
                                    <label>Current Location on Site</label>
                                    <input type="text" name="current_location_on_site" value="${this.escapeHtml(equipment.current_location_on_site || '')}" placeholder="e.g., Rear Garden, Front Drive">
                                </div>
                            </div>
                        </div>
                        
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Operator Assignment</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Assigned Operator</label>
                                    <select name="assigned_operator_id">
                                        <option value="">Select operator...</option>
                                        ${this.employees.map(e => `<option value="${e.id}" ${equipment.assigned_operator_id == e.id ? 'selected' : ''}>${e.first_name} ${e.last_name} (${e.role})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Certification Required</label>
                                    <select name="operator_certification_required">
                                        <option value="" ${!equipment.operator_certification_required ? 'selected' : ''}>None</option>
                                        <option value="CPCS" ${equipment.operator_certification_required === 'CPCS' ? 'selected' : ''}>CPCS</option>
                                        <option value="NPORS" ${equipment.operator_certification_required === 'NPORS' ? 'selected' : ''}>NPORS</option>
                                        <option value="IPAF" ${equipment.operator_certification_required === 'IPAF' ? 'selected' : ''}>IPAF</option>
                                        <option value="PASMA" ${equipment.operator_certification_required === 'PASMA' ? 'selected' : ''}>PASMA</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Supervisor Responsible</label>
                                    <select name="supervisor_responsible_id">
                                        <option value="">Select supervisor...</option>
                                        ${this.employees.filter(e => e.role === 'Site Manager' || e.role === 'Foreman' || e.permissions_level === 'supervisor').map(e => `<option value="${e.id}" ${equipment.supervisor_responsible_id == e.id ? 'selected' : ''}>${e.first_name} ${e.last_name}</option>`).join('')}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Condition Tab -->
                    <div class="pi-equipment-modal-panel" data-panel="condition">
                        <div class="pi-equipment-form-section">
                            <div class="pi-equipment-form-section-title">Current Condition</div>
                            <div class="pi-equipment-form-grid">
                                <div class="pi-equipment-form-group">
                                    <label>Condition</label>
                                    <select name="current_condition">
                                        <option value="Excellent" ${equipment.current_condition === 'Excellent' ? 'selected' : ''}>Excellent</option>
                                        <option value="Good" ${equipment.current_condition === 'Good' || !equipment.current_condition ? 'selected' : ''}>Good</option>
                                        <option value="Fair" ${equipment.current_condition === 'Fair' ? 'selected' : ''}>Fair</option>
                                        <option value="Poor" ${equipment.current_condition === 'Poor' ? 'selected' : ''}>Poor</option>
                                        <option value="Damaged" ${equipment.current_condition === 'Damaged' ? 'selected' : ''}>Damaged</option>
                                    </select>
                                </div>
                                <div class="pi-equipment-form-group">
                                    <label>Hours Meter Reading</label>
                                    <input type="number" name="hours_meter_reading" step="0.1" value="${equipment.hours_meter_reading || ''}" placeholder="Current hours">
                                </div>
                                <div class="pi-equipment-form-group full-width">
                                    <label>Condition Notes</label>
                                    <textarea name="condition_notes" rows="3" placeholder="Any observations about condition...">${this.escapeHtml(equipment.condition_notes || '')}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            `;
            
            this.showModal('Edit Equipment', html, [
                { text: 'Cancel', class: 'pi-equipment-btn-secondary', action: 'close' },
                { text: 'Save Changes', class: 'pi-equipment-btn-primary', action: 'submit' }
            ], (formData) => this.handleEditEquipment(equipment.id, formData));
            
            // Apply acquisition fields visibility after modal opens
            setTimeout(() => this.toggleEditAcquisitionFields(), 100);
            console.log('[Equipment] Edit modal opened successfully');
            } catch (error) {
                console.error('[Equipment] Error in openEditModal:', error);
                this.showToast('Failed to open edit modal', 'error');
            }
        },

        async handleEditEquipment(id, data) {
            try {
                await this.api.updateEquipment(id, data);
                this.closeModal();
                this.showToast('Equipment updated successfully');
                this.loadEquipment();
            } catch (error) {
                console.error('[Equipment] Failed to update:', error);
                this.showToast('Failed to update equipment', 'error');
            }
        },

        async deleteEquipmentItem(id) {
            if (!confirm('Are you sure you want to delete this equipment? This action cannot be undone.')) {
                return;
            }
            
            try {
                await this.api.deleteEquipment(id);
                this.showToast('Equipment deleted successfully');
                this.loadEquipment();
            } catch (error) {
                console.error('[Equipment] Failed to delete:', error);
                this.showToast('Failed to delete equipment', 'error');
            }
        },

        deleteCurrentEquipment() {
            if (!this.selectedEquipment) return;
            this.deleteEquipmentItem(this.selectedEquipment.id);
            this.closeDrawer();
        },

        reportIssue(id) {
            this.showModal('Report Issue', `
                <form id="pi-equipment-issue-form">
                    <div class="pi-equipment-form-group">
                        <label>Issue Type <span class="required">*</span></label>
                        <select name="issue_type" required>
                            <option value="Damage">Damage</option>
                            <option value="Breakdown">Breakdown</option>
                            <option value="Missing Item">Missing Item</option>
                            <option value="Safety Concern">Safety Concern</option>
                        </select>
                    </div>
                    <div class="pi-equipment-form-group">
                        <label>Severity</label>
                        <select name="severity">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="pi-equipment-form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" rows="4" required></textarea>
                    </div>
                </form>
            `, [
                { text: 'Cancel', class: 'pi-equipment-btn-secondary', action: 'close' },
                { text: 'Report Issue', class: 'pi-equipment-btn-danger', action: 'submit' }
            ]);
        },

        showLoading() {
            // Only show loading in the stats area, don't destroy the entire UI
            $('#pi-equipment-stats').html(`
                <div class="pi-equipment-loading">
                    <div class="pi-equipment-spinner"></div>
                    <p>Loading equipment...</p>
                </div>
            `);
            // Also show loading in table body
            $('#pi-equipment-table-body').html(`
                <tr><td colspan="8" class="pi-equipment-loading-cell">
                    <div class="pi-equipment-loading">
                        <div class="pi-equipment-spinner"></div>
                        <p>Loading equipment data...</p>
                    </div>
                </td></tr>
            `);
        },

        hideLoading() {
            // Loading states are automatically replaced by render functions
        },

        showError(message) {
            $('#pi-equipment-container').html(`
                <div class="pi-equipment-empty">
                    <div class="pi-equipment-empty-icon" style="background: #fee2e2; color: #dc2626;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <h4>Error</h4>
                    <p>${message}</p>
                    <button class="pi-equipment-btn pi-equipment-btn-primary" onclick="PI_Equipment.loadEquipment()">Retry</button>
                </div>
            `);
        },

        showToast(message, type = 'success') {
            // Use existing toast system if available
            if (typeof showToast === 'function') {
                showToast(message, type);
            } else {
                alert(message);
            }
        }
    };

    // Make globally accessible
    window.PI_Equipment = PI_Equipment;

})(jQuery);
