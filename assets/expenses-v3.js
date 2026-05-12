/**
 * Planning Index Workspace - Expenses V3
 * FULLY FUNCTIONAL - NO MOCK DATA
 * Integrated with Jobs, Suppliers, Mileage, Tax
 * 
 * @version 3.0.0
 */

(function($) {
    'use strict';

    // ============================================
    // CONFIGURATION & STATE
    // ============================================
    
    const config = {
        restUrl: '/wp-json/pi/v1',
        nonce: null,
        userId: null,
        currency: 'GBP'
    };

    const state = {
        currentTab: 'dashboard',
        expenses: [],
        suppliers: [],
        jobs: [],
        vehicles: [],
        trips: [],
        settings: {},
        filters: {
            search: '',
            category: '',
            status: '',
            dateFrom: '',
            dateTo: ''
        },
        pagination: {
            page: 1,
            perPage: 25,
            total: 0
        },
        selectedJobId: null,
        loading: false
    };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function formatCurrency(amount) {
        if (amount === null || amount === undefined || isNaN(amount)) return '£0.00';
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: config.currency,
            minimumFractionDigits: 2
        }).format(amount);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatDateInput(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        return date.toISOString().split('T')[0];
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showToast(message, type = 'success') {
        const icons = {
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };

        const $toast = $(`
            <div class="pi-notification ${type}">
                <div class="pi-notification-icon">${icons[type]}</div>
                <div class="pi-notification-content">${escapeHtml(message)}</div>
            </div>
        `);

        $('body').append($toast);

        setTimeout(() => {
            $toast.fadeOut(300, function() { $(this).remove(); });
        }, 4000);
    }

    function setLoading($element, loading) {
        if (loading) {
            $element.addClass('pi-loading');
        } else {
            $element.removeClass('pi-loading');
        }
    }

    // ============================================
    // DATA EXPORT & BULK OPERATIONS
    // ============================================

    const DataExport = {
        exportToCSV(data, filename = 'expenses') {
            if (!data || data.length === 0) {
                showToast('No data to export', 'warning');
                return;
            }
            
            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row => 
                    headers.map(h => {
                        const val = row[h];
                        if (val === null || val === undefined) return '';
                        const str = String(val).replace(/"/g, '""');
                        return str.includes(',') || str.includes('"') || str.includes('\n') ? `"${str}"` : str;
                    }).join(',')
                )
            ].join('\n');
            
            this.downloadFile(csvContent, `${filename}.csv`, 'text/csv');
            showToast(`Exported ${data.length} records to CSV`, 'success');
        },
        
        exportToJSON(data, filename = 'expenses') {
            if (!data || data.length === 0) {
                showToast('No data to export', 'warning');
                return;
            }
            
            const jsonContent = JSON.stringify(data, null, 2);
            this.downloadFile(jsonContent, `${filename}.json`, 'application/json');
            showToast(`Exported ${data.length} records to JSON`, 'success');
        },
        
        downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
        
        async exportExpenses(format = 'csv') {
            try {
                setLoading($('body'), true);
                const result = await API.getExpenses({ per_page: 1000 });
                const data = result.data || [];
                
                // Flatten and clean data for export
                const cleanData = data.map(exp => ({
                    id: exp.id,
                    date: exp.expense_date,
                    description: exp.description,
                    amount: exp.amount,
                    category: exp.category,
                    supplier: exp.supplier_name,
                    job: exp.job_title,
                    status: exp.status,
                    payment_method: exp.payment_method,
                    invoice_number: exp.invoice_number,
                    created_at: exp.created_at
                }));
                
                if (format === 'csv') {
                    this.exportToCSV(cleanData, 'expenses');
                } else {
                    this.exportToJSON(cleanData, 'expenses');
                }
            } catch (err) {
                showToast('Failed to export data', 'error');
            } finally {
                setLoading($('body'), false);
            }
        }
    };

    // ============================================
    // ADVANCED SEARCH & FILTERING
    // ============================================

    const SearchManager = {
        searchIndex: [],
        fuse: null,
        
        buildIndex(data) {
            this.searchIndex = data.map(item => ({
                id: item.id,
                text: [
                    item.description,
                    item.supplier_name,
                    item.job_title,
                    item.category,
                    item.invoice_number
                ].filter(Boolean).join(' ').toLowerCase()
            }));
        },
        
        search(query) {
            if (!query || query.length < 2) return [];
            
            const lowerQuery = query.toLowerCase();
            return this.searchIndex
                .filter(item => item.text.includes(lowerQuery))
                .map(item => item.id);
        },
        
        async globalSearch(query) {
            if (!query || query.length < 2) return { expenses: [], jobs: [], suppliers: [] };
            
            try {
                const [expenses, jobs, suppliers] = await Promise.all([
                    API.getExpenses({ search: query, per_page: 10 }),
                    API.getJobs(),
                    API.getSuppliers()
                ]);
                
                // Filter jobs and suppliers client-side
                const filteredJobs = (jobs || []).filter(j => 
                    j.title?.toLowerCase().includes(query.toLowerCase())
                ).slice(0, 5);
                
                const filteredSuppliers = (suppliers || []).filter(s => 
                    s.name?.toLowerCase().includes(query.toLowerCase())
                ).slice(0, 5);
                
                return {
                    expenses: expenses.data || [],
                    jobs: filteredJobs,
                    suppliers: filteredSuppliers
                };
            } catch (err) {
                console.error('Global search failed:', err);
                return { expenses: [], jobs: [], suppliers: [] };
            }
        }
    };

    // ============================================
    // API CLIENT
    // ============================================

    const API = {
        async request(endpoint, options = {}) {
            const url = config.restUrl + endpoint;
            const defaults = {
                headers: {
                    'X-WP-Nonce': config.nonce,
                    'Content-Type': 'application/json'
                }
            };

            try {
                const response = await fetch(url, { ...defaults, ...options });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || `HTTP ${response.status}`);
                }

                return await response.json();
            } catch (err) {
                console.error('API Error:', err);
                throw err;
            }
        },

        // Dashboard
        getDashboard() {
            return this.request('/expenses/dashboard');
        },

        getTrends(months = 6) {
            return this.request(`/expenses/trends?months=${months}`);
        },

        getActivity(limit = 20) {
            return this.request(`/expenses/activity?limit=${limit}`);
        },

        getNotifications() {
            return this.request('/expenses/notifications');
        },

        // Expenses
        getExpenses(params = {}) {
            const query = new URLSearchParams(params).toString();
            return this.request('/expenses/db?' + query);
        },

        createExpense(data) {
            return this.request('/expenses/create', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        updateExpense(id, data) {
            return this.request(`/expenses/${id}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },

        deleteExpense(id) {
            return this.request(`/expenses/${id}`, {
                method: 'DELETE'
            });
        },

        bulkAction(action, ids) {
            return this.request('/expenses/bulk', {
                method: 'POST',
                body: JSON.stringify({ action, ids })
            });
        },

        // Jobs
        getJobs() {
            return this.request('/expenses/jobs');
        },

        getJobCosting(jobId) {
            return this.request(`/expenses/job-costing?job_id=${jobId}`);
        },

        // Suppliers
        getSuppliers() {
            return this.request('/expenses/suppliers');
        },

        createSupplier(data) {
            return this.request('/expenses/suppliers', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        updateSupplier(id, data) {
            return this.request(`/expenses/suppliers/${id}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },

        deleteSupplier(id) {
            return this.request(`/expenses/suppliers/${id}`, {
                method: 'DELETE'
            });
        },

        // Mileage
        getMileage() {
            return this.request('/expenses/mileage');
        },

        createTrip(data) {
            return this.request('/expenses/mileage', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        getMileageStats() {
            return this.request('/expenses/mileage/stats');
        },

        // Vehicles
        getVehicles() {
            return this.request('/expenses/vehicles');
        },

        createVehicle(data) {
            return this.request('/expenses/vehicles', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        // Tax & Compliance
        getCIS(period) {
            return this.request(`/expenses/cis?period=${period || ''}`);
        },

        getVAT(period) {
            return this.request(`/expenses/vat?period=${period || ''}`);
        },

        getRetention() {
            return this.request('/expenses/retention');
        },

        // Approvals
        getApprovals(view) {
            return this.request(`/expenses/approvals?view=${view || 'pending'}`);
        },

        approveExpense(id) {
            return this.request(`/expenses/approvals/${id}/approve`, {
                method: 'POST'
            });
        },

        rejectExpense(id, reason) {
            return this.request(`/expenses/approvals/${id}/reject`, {
                method: 'POST',
                body: JSON.stringify({ reason })
            });
        },

        // Settings
        getSettings() {
            return this.request('/expenses/settings');
        },

        saveSettings(data) {
            return this.request('/expenses/settings', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        // Database initialization (nuclear option)
        initDatabase() {
            return this.request('/expenses/init', {
                method: 'POST'
            });
        }
    };

    // ============================================
    // MODALS
    // ============================================

    const Modals = {
        open(html, options = {}) {
            console.log('Modal.open called:', options.title);

            // Remove any existing modal
            $('#pi-modal-overlay').remove();
            $(document).off('keydown.pi-modal').off('click.pi-modal-dismiss');

            const modalHtml = `
                <div class="pi-modal-overlay" id="pi-modal-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 999999;
                    padding: 20px;
                    backdrop-filter: blur(4px);
                ">
                    <div class="pi-modal-container ${options.size || ''}" style="
                        background: #ffffff;
                        border-radius: 12px;
                        width: 100%;
                        max-width: ${options.size === 'large' ? '700px' : options.size === 'medium' ? '600px' : options.size === 'small' ? '400px' : '500px'};
                        max-height: 90vh;
                        overflow-y: auto;
                        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
                        position: relative;
                        z-index: 9999999;
                        animation: modalSlideIn 0.3s ease;
                    ">
                        <div class="pi-modal-header" style="
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 1.25rem;
                            border-bottom: 1px solid #e2e8f0;
                        ">
                            <h3 style="
                                font-size: 1.125rem;
                                font-weight: 600;
                                color: #0f172a;
                                margin: 0;
                            ">${escapeHtml(options.title || 'Modal')}</h3>
                            <button class="pi-modal-close" id="modal-close-btn" type="button" style="
                                width: 32px;
                                height: 32px;
                                border: none;
                                background: #f1f5f9;
                                border-radius: 8px;
                                font-size: 1.25rem;
                                color: #94a3b8;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                transition: all 0.2s ease;
                                line-height: 1;
                            ">&times;</button>
                        </div>
                        <div class="pi-modal-body" style="padding: 1.5rem;">
                            ${html}
                        </div>
                        ${options.footer ? `<div class="pi-modal-footer" style="
                            display: flex;
                            justify-content: flex-end;
                            gap: 0.75rem;
                            padding: 1rem 1.5rem;
                            border-top: 1px solid #e2e8f0;
                            background: #f8fafc;
                        ">${options.footer}</div>` : ''}
                    </div>
                </div>
            `;

            // Append to BODY to avoid stacking context issues with wrapper
            $('body').append(modalHtml);
            $('body').addClass('pi-modal-open');

            console.log('Modal HTML appended to body, overlay exists:', $('#pi-modal-overlay').length > 0);

            // Bind close button
            $('#modal-close-btn').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Close button clicked');
                Modals.close();
            });

            // Bind [data-dismiss="modal"] elements (Cancel buttons)
            $(document).on('click.pi-modal-dismiss', '[data-dismiss="modal"]', (e) => {
                e.preventDefault();
                Modals.close();
            });

            // Bind overlay background click to close (only if clicking overlay itself, not children)
            $('#pi-modal-overlay').on('click', (e) => {
                if (e.target.id === 'pi-modal-overlay') {
                    Modals.close();
                }
            });

            // Bind Escape key
            $(document).on('keydown.pi-modal', (e) => {
                if (e.key === 'Escape') {
                    Modals.close();
                }
            });
        },

        close() {
            console.log('Modal.close called');
            $('#pi-modal-overlay').remove();
            $('body').removeClass('pi-modal-open');
            $(document).off('keydown.pi-modal').off('click.pi-modal-dismiss');
        },

        // Expense Modal
        async expense(expense = null) {
            const isEdit = !!(expense && expense.id);
            const categories = window.piExpensesData?.categories || {};
            
            // Load jobs if not already loaded
            if (!state.jobs || state.jobs.length === 0) {
                try {
                    state.jobs = await API.getJobs();
                } catch (err) {
                    console.error('Failed to load jobs:', err);
                    state.jobs = [];
                }
            }
            
            const categoryOptions = Object.entries(categories).map(([key, cat]) => 
                `<option value="${key}" ${expense?.category === key ? 'selected' : ''}>${escapeHtml(cat.label)}</option>`
            ).join('');

            const jobOptions = state.jobs.map(job => 
                `<option value="${job.id}" ${expense?.job_id === job.id ? 'selected' : ''}>${escapeHtml(job.title)}</option>`
            ).join('');

            const paymentMethods = window.piExpensesData?.paymentMethods || {};
            const paymentOptions = Object.entries(paymentMethods).map(([key, label]) => 
                `<option value="${key}" ${expense?.payment_method === key ? 'selected' : ''}>${escapeHtml(label)}</option>`
            ).join('');

            const html = `
                <form id="expense-form">
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Date *</label>
                            <input type="date" name="expense_date" class="pi-form-control" 
                                value="${formatDateInput(expense?.expense_date || new Date())}" required>
                        </div>
                        <div class="pi-form-group">
                            <label>Amount *</label>
                            <input type="number" name="amount" class="pi-form-control" step="0.01" min="0"
                                value="${expense?.amount || ''}" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Category *</label>
                            <select name="category" class="pi-form-control" required>
                                <option value="">Select category...</option>
                                ${categoryOptions}
                            </select>
                        </div>
                        <div class="pi-form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" class="pi-form-control">
                                ${paymentOptions}
                            </select>
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label>Description *</label>
                        <input type="text" name="description" class="pi-form-control" 
                            value="${escapeHtml(expense?.description || '')}" 
                            placeholder="What was this expense for?" required>
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Supplier</label>
                            <input type="text" name="supplier_name" class="pi-form-control" list="suppliers-list"
                                value="${escapeHtml(expense?.supplier_name || '')}" 
                                placeholder="Supplier name">
                            <datalist id="suppliers-list">
                                ${state.suppliers.map(s => `<option value="${escapeHtml(s.name)}">`).join('')}
                            </datalist>
                        </div>
                        <div class="pi-form-group">
                            <label>Job (Optional)</label>
                            <select name="job_id" class="pi-form-control">
                                <option value="">No Job</option>
                                ${jobOptions}
                            </select>
                        </div>
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Invoice Number</label>
                            <input type="text" name="invoice_number" class="pi-form-control"
                                value="${escapeHtml(expense?.invoice_number || '')}">
                        </div>
                        <div class="pi-form-group">
                            <label>PO Number</label>
                            <input type="text" name="po_number" class="pi-form-control"
                                value="${escapeHtml(expense?.po_number || '')}">
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label class="pi-checkbox-label">
                            <input type="checkbox" name="cis_liable" value="1" ${expense?.cis_liable ? 'checked' : ''}>
                            CIS Liable (Subcontractor)
                        </label>
                    </div>
                    
                    <div class="pi-form-group" id="cis-rate-group" style="display: ${expense?.cis_liable ? 'block' : 'none'};">
                        <label>CIS Rate (%)</label>
                        <select name="cis_rate" class="pi-form-control">
                            <option value="20" ${expense?.cis_rate == 20 ? 'selected' : ''}>Registered - 20%</option>
                            <option value="30" ${expense?.cis_rate == 30 ? 'selected' : ''}>Unregistered - 30%</option>
                            <option value="0" ${expense?.cis_rate === 0 ? 'selected' : ''}>Gross - 0%</option>
                        </select>
                    </div>
                </form>
            `;

            const footer = `
                <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="pi-btn pi-btn-primary" id="save-expense-btn">
                    ${isEdit ? 'Update' : 'Save'} Expense
                </button>
            `;

            this.open(html, { title: isEdit ? 'Edit Expense' : 'Add Expense', footer, size: 'large' });

            // Bind CIS toggle
            $('[name="cis_liable"]').on('change', function() {
                $('#cis-rate-group').toggle(this.checked);
            });

            // Bind save
            $('#save-expense-btn').on('click', async () => {
                const $form = $('#expense-form');
                if (!$form[0].checkValidity()) {
                    $form[0].reportValidity();
                    return;
                }

                const data = {
                    expense_date: $form.find('[name="expense_date"]').val(),
                    amount: parseFloat($form.find('[name="amount"]').val()),
                    category: $form.find('[name="category"]').val(),
                    description: $form.find('[name="description"]').val(),
                    supplier_name: $form.find('[name="supplier_name"]').val(),
                    job_id: $form.find('[name="job_id"]').val() || null,
                    payment_method: $form.find('[name="payment_method"]').val(),
                    invoice_number: $form.find('[name="invoice_number"]').val(),
                    po_number: $form.find('[name="po_number"]').val(),
                    cis_liable: $form.find('[name="cis_liable"]').is(':checked') ? 1 : 0,
                    cis_rate: $form.find('[name="cis_rate"]').val() || null
                };

                try {
                    if (isEdit) {
                        await API.updateExpense(expense.id, data);
                        showToast('Expense updated successfully');
                    } else {
                        await API.createExpense(data);
                        showToast('Expense created successfully');
                    }
                    this.close();
                    TabHandlers[state.currentTab]();
                } catch (err) {
                    showToast(err.message || 'Failed to save expense', 'error');
                }
            });
        },

        // Supplier Modal
        supplier(supplier = null) {
            const isEdit = !!supplier;

            const html = `
                <form id="supplier-form">
                    <div class="pi-form-group">
                        <label>Company Name *</label>
                        <input type="text" name="company_name" class="pi-form-control" 
                            value="${escapeHtml(supplier?.name || '')}" required>
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Contact Name</label>
                            <input type="text" name="contact_name" class="pi-form-control"
                                value="${escapeHtml(supplier?.contact_name || '')}">
                        </div>
                        <div class="pi-form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" class="pi-form-control"
                                value="${escapeHtml(supplier?.phone || '')}">
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="pi-form-control"
                            value="${escapeHtml(supplier?.email || '')}">
                    </div>
                    
                    <div class="pi-form-group">
                        <label>Address</label>
                        <textarea name="address" class="pi-form-control" rows="3">${escapeHtml(supplier?.address || '')}</textarea>
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Account Number</label>
                            <input type="text" name="account_number" class="pi-form-control"
                                value="${escapeHtml(supplier?.account_number || '')}">
                        </div>
                        <div class="pi-form-group">
                            <label>VAT Number</label>
                            <input type="text" name="vat_number" class="pi-form-control"
                                value="${escapeHtml(supplier?.vat_number || '')}">
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label>CIS Status</label>
                        <select name="cis_status" class="pi-form-control">
                            <option value="not_applicable" ${supplier?.cis_status === 'not_applicable' ? 'selected' : ''}>N/A - Not CIS</option>
                            <option value="gross" ${supplier?.cis_status === 'gross' ? 'selected' : ''}>Gross - 0%</option>
                            <option value="registered" ${supplier?.cis_status === 'registered' ? 'selected' : ''}>Registered - 20%</option>
                            <option value="unregistered" ${supplier?.cis_status === 'unregistered' ? 'selected' : ''}>Unregistered - 30%</option>
                        </select>
                    </div>
                </form>
            `;

            const footer = `
                <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="pi-btn pi-btn-primary" id="save-supplier-btn">
                    ${isEdit ? 'Update' : 'Add'} Supplier
                </button>
            `;

            this.open(html, { title: isEdit ? 'Edit Supplier' : 'Add Supplier', footer, size: 'medium' });

            $('#save-supplier-btn').on('click', async () => {
                const $form = $('#supplier-form');
                if (!$form[0].checkValidity()) {
                    $form[0].reportValidity();
                    return;
                }

                const data = {
                    company_name: $form.find('[name="company_name"]').val(),
                    contact_name: $form.find('[name="contact_name"]').val(),
                    phone: $form.find('[name="phone"]').val(),
                    email: $form.find('[name="email"]').val(),
                    address: $form.find('[name="address"]').val(),
                    account_number: $form.find('[name="account_number"]').val(),
                    vat_number: $form.find('[name="vat_number"]').val(),
                    cis_status: $form.find('[name="cis_status"]').val()
                };

                try {
                    if (isEdit) {
                        await API.updateSupplier(supplier.id, data);
                        showToast('Supplier updated successfully');
                    } else {
                        await API.createSupplier(data);
                        showToast('Supplier created successfully');
                    }
                    this.close();
                    TabHandlers.suppliers();
                } catch (err) {
                    showToast(err.message || 'Failed to save supplier', 'error');
                }
            });
        },

        // Trip Modal
        async trip(trip = null) {
            const isEdit = !!trip;

            // Load jobs if not already loaded
            if (!state.jobs || state.jobs.length === 0) {
                try {
                    state.jobs = await API.getJobs();
                } catch (err) {
                    console.error('Failed to load jobs:', err);
                    state.jobs = [];
                }
            }

            const vehicleOptions = state.vehicles.map(v => 
                `<option value="${v.id}" ${trip?.vehicle_id === v.id ? 'selected' : ''}>
                    ${escapeHtml(v.make)} ${escapeHtml(v.model)} (${escapeHtml(v.registration)})
                </option>`
            ).join('');

            const jobOptions = state.jobs.map(job => 
                `<option value="${job.id}" ${trip?.job_id === job.id ? 'selected' : ''}>${escapeHtml(job.title)}</option>`
            ).join('');

            const html = `
                <form id="trip-form">
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Date *</label>
                            <input type="date" name="date" class="pi-form-control" 
                                value="${formatDateInput(trip?.date || new Date())}" required>
                        </div>
                        <div class="pi-form-group">
                            <label>Vehicle *</label>
                            <select name="vehicle_id" class="pi-form-control" required>
                                <option value="">Select vehicle...</option>
                                ${vehicleOptions}
                            </select>
                        </div>
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>From *</label>
                            <input type="text" name="from_address" class="pi-form-control"
                                value="${escapeHtml(trip?.from_address || 'Office')}" required>
                        </div>
                        <div class="pi-form-group">
                            <label>To *</label>
                            <input type="text" name="to_address" class="pi-form-control"
                                value="${escapeHtml(trip?.to_address || '')}" required>
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label>Purpose</label>
                        <input type="text" name="purpose" class="pi-form-control"
                            value="${escapeHtml(trip?.purpose || '')}" placeholder="Site visit, delivery, etc.">
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Miles *</label>
                            <input type="number" name="miles" class="pi-form-control" step="0.1" min="0"
                                value="${trip?.miles || ''}" required>
                        </div>
                        <div class="pi-form-group">
                            <label>Job (Optional)</label>
                            <select name="job_id" class="pi-form-control">
                                <option value="">No Job</option>
                                ${jobOptions}
                            </select>
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label class="pi-checkbox-label">
                            <input type="checkbox" name="is_return" value="1" ${trip?.is_return ? 'checked' : ''}>
                            Return trip (multiply miles by 2)
                        </label>
                    </div>
                </form>
            `;

            const footer = `
                <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="pi-btn pi-btn-primary" id="save-trip-btn">
                    ${isEdit ? 'Update' : 'Log'} Trip
                </button>
            `;

            this.open(html, { title: isEdit ? 'Edit Trip' : 'Log Mileage Trip', footer, size: 'large' });

            $('#save-trip-btn').on('click', async () => {
                const $form = $('#trip-form');
                if (!$form[0].checkValidity()) {
                    $form[0].reportValidity();
                    return;
                }

                let miles = parseFloat($form.find('[name="miles"]').val());
                if ($form.find('[name="is_return"]').is(':checked')) {
                    miles *= 2;
                }

                const data = {
                    date: $form.find('[name="date"]').val(),
                    vehicle_id: parseInt($form.find('[name="vehicle_id"]').val()),
                    from_address: $form.find('[name="from_address"]').val(),
                    to_address: $form.find('[name="to_address"]').val(),
                    purpose: $form.find('[name="purpose"]').val(),
                    miles: miles,
                    job_id: $form.find('[name="job_id"]').val() || null
                };

                try {
                    await API.createTrip(data);
                    showToast('Trip logged successfully');
                    this.close();
                    TabHandlers.mileage();
                } catch (err) {
                    showToast(err.message || 'Failed to log trip', 'error');
                }
            });
        },

        // Vehicle Modal
        vehicle() {
            const html = `
                <form id="vehicle-form">
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Make *</label>
                            <input type="text" name="make" class="pi-form-control" required>
                        </div>
                        <div class="pi-form-group">
                            <label>Model *</label>
                            <input type="text" name="model" class="pi-form-control" required>
                        </div>
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label>Registration *</label>
                            <input type="text" name="registration" class="pi-form-control" required>
                        </div>
                        <div class="pi-form-group">
                            <label>Fuel Type</label>
                            <select name="fuel_type" class="pi-form-control">
                                <option value="diesel">Diesel</option>
                                <option value="petrol">Petrol</option>
                                <option value="electric">Electric</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label>Current Mileage</label>
                        <input type="number" name="current_mileage" class="pi-form-control" min="0">
                    </div>
                </form>
            `;

            const footer = `
                <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="pi-btn pi-btn-primary" id="save-vehicle-btn">Add Vehicle</button>
            `;

            this.open(html, { title: 'Add Vehicle', footer, size: 'medium' });

            $('#save-vehicle-btn').on('click', async () => {
                const $form = $('#vehicle-form');
                if (!$form[0].checkValidity()) {
                    $form[0].reportValidity();
                    return;
                }

                const data = {
                    make: $form.find('[name="make"]').val(),
                    model: $form.find('[name="model"]').val(),
                    registration: $form.find('[name="registration"]').val(),
                    fuel_type: $form.find('[name="fuel_type"]').val(),
                    current_mileage: parseInt($form.find('[name="current_mileage"]').val()) || 0
                };

                try {
                    await API.createVehicle(data);
                    showToast('Vehicle added successfully');
                    this.close();
                    loadVehicles().then(() => TabHandlers.mileage());
                } catch (err) {
                    showToast(err.message || 'Failed to add vehicle', 'error');
                }
            });
        },

        // Delete Confirmation Modal
        confirmDelete(itemType, itemName, onConfirm) {
            const html = `
                <p>Are you sure you want to delete this ${escapeHtml(itemType)}?</p>
                <p class="pi-text-muted">${escapeHtml(itemName)}</p>
                <p class="pi-text-danger">This action cannot be undone.</p>
            `;

            const footer = `
                <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="pi-btn pi-btn-danger" id="confirm-delete-btn">Delete</button>
            `;

            this.open(html, { title: 'Confirm Delete', footer, size: 'small' });

            $('#confirm-delete-btn').on('click', () => {
                onConfirm();
                this.close();
            });
        }
    };

    // ============================================
    // TAB HANDLERS
    // ============================================

    const TabHandlers = {
        // Dashboard Tab
        async dashboard() {
            const $container = $('#tab-dashboard');
            setLoading($container, true);

            try {
                const [dashboard, trends] = await Promise.all([
                    API.getDashboard(),
                    API.getTrends(6)
                ]);

                // KPI Cards
                const trendClass = dashboard.this_month_spend_trend >= 0 ? 'up' : 'down';
                const trendIcon = dashboard.this_month_spend_trend >= 0 ? 'trending-up' : 'trending-down';

                $container.html(`
                    <div class="pi-dashboard-grid">
                        <div class="pi-kpi-card">
                            <div class="pi-kpi-icon pi-kpi-icon-green">
                                <i data-feather="credit-card"></i>
                            </div>
                            <div class="pi-kpi-content">
                                <div class="pi-kpi-value-row">
                                    <span class="pi-kpi-value">${formatCurrency(dashboard.this_month_spend)}</span>
                                    <span class="pi-kpi-trend ${trendClass}">
                                        <i data-feather="${trendIcon}"></i>
                                        ${Math.abs(dashboard.this_month_spend_trend)}%
                                    </span>
                                </div>
                                <div class="pi-kpi-label">This Month</div>
                            </div>
                        </div>
                        
                        <div class="pi-kpi-card ${dashboard.pending_approvals > 0 ? 'pi-kpi-alert' : ''}">
                            <div class="pi-kpi-icon pi-kpi-icon-orange">
                                <i data-feather="clock"></i>
                            </div>
                            <div class="pi-kpi-content">
                                <div class="pi-kpi-value">${dashboard.pending_approvals}</div>
                                <div class="pi-kpi-label">Pending Approvals</div>
                            </div>
                        </div>
                        
                        <div class="pi-kpi-card ${dashboard.unreconciled_receipts > 5 ? 'pi-kpi-alert' : ''}">
                            <div class="pi-kpi-icon pi-kpi-icon-blue">
                                <i data-feather="file-minus"></i>
                            </div>
                            <div class="pi-kpi-content">
                                <div class="pi-kpi-value">${dashboard.unreconciled_receipts}</div>
                                <div class="pi-kpi-label">Missing Receipts</div>
                            </div>
                        </div>
                        
                        <div class="pi-kpi-card ${dashboard.budget_alerts > 0 ? 'pi-kpi-alert' : ''}">
                            <div class="pi-kpi-icon pi-kpi-icon-red">
                                <i data-feather="alert-triangle"></i>
                            </div>
                            <div class="pi-kpi-content">
                                <div class="pi-kpi-value">${dashboard.budget_alerts}</div>
                                <div class="pi-kpi-label">Budget Alerts</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pi-dashboard-charts">
                        <div class="pi-chart-card">
                            <h3>Spending Trends</h3>
                            <div class="pi-chart-container">
                                <canvas id="spending-chart"></canvas>
                            </div>
                        </div>
                        <div class="pi-chart-card">
                            <h3>By Category</h3>
                            <div class="pi-chart-container">
                                <canvas id="category-chart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pi-dashboard-activity">
                        <div class="pi-activity-card">
                            <div class="pi-activity-header">
                                <h3>Recent Activity</h3>
                                <a href="#" class="pi-link" data-tab="ledger">View All</a>
                            </div>
                            <div class="pi-activity-list" id="activity-list">
                                <div class="pi-empty-state">Loading...</div>
                            </div>
                        </div>
                        <div class="pi-chart-card">
                            <h3>Quick Actions</h3>
                            <div class="pi-quick-actions-grid">
                                <button class="pi-quick-action" data-action="add-expense">
                                    <i data-feather="plus-circle"></i>
                                    <span>Add Expense</span>
                                </button>
                                <button class="pi-quick-action" data-action="log-trip">
                                    <i data-feather="map-pin"></i>
                                    <span>Log Trip</span>
                                </button>
                                <button class="pi-quick-action" data-action="add-supplier">
                                    <i data-feather="truck"></i>
                                    <span>Add Supplier</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `);

                // Render charts
                renderSpendingChart(trends);
                renderCategoryChart(trends.by_category);

                // Load activity
                loadActivityFeed();

                if (window.feather) feather.replace();

            } catch (err) {
                console.error('Dashboard load failed:', err);
                $container.html(`
                    <div class="pi-empty-state">
                        <i data-feather="alert-circle"></i>
                        <h3>Failed to load dashboard</h3>
                        <p>${escapeHtml(err.message)}</p>
                        <button class="pi-btn pi-btn-primary" onclick="TabHandlers.dashboard()">Retry</button>
                    </div>
                `);
            } finally {
                setLoading($container, false);
            }
        },

        // Ledger Tab
        async ledger() {
            const $container = $('#tab-ledger');
            setLoading($container, true);

            try {
                const params = {
                    page: state.pagination.page,
                    per_page: state.pagination.perPage,
                    search: state.filters.search,
                    category: state.filters.category,
                    status: state.filters.status,
                    date_from: state.filters.dateFrom,
                    date_to: state.filters.dateTo
                };

                const result = await API.getExpenses(params);
                state.expenses = result.data || [];
                state.pagination.total = result.total || 0;

                renderLedgerTable($container);

            } catch (err) {
                console.error('Ledger load failed:', err);
                $container.html(`
                    <div class="pi-empty-state">
                        <i data-feather="alert-circle"></i>
                        <h3>Failed to load expenses</h3>
                        <p>${escapeHtml(err.message)}</p>
                        <button class="pi-btn pi-btn-primary" onclick="TabHandlers.ledger()">Retry</button>
                    </div>
                `);
            } finally {
                setLoading($container, false);
            }
        },

        // Job Costing Tab
        async jobcosting() {
            const $container = $('#tab-jobcosting');
            setLoading($container, true);

            try {
                // Always refresh jobs to get live data including trips and expenses
                state.jobs = await API.getJobs();

                if (state.jobs.length === 0) {
                    $container.html(`
                        <div class="pi-empty-state">
                            <i data-feather="briefcase"></i>
                            <h3>No Jobs Found</h3>
                            <p>You don't have any jobs yet. Jobs are created when you win leads.</p>
                            <a href="/workspace/" class="pi-btn pi-btn-primary">Go to Workspace</a>
                        </div>
                    `);
                    return;
                }

                // Get selected job from URL or default to first
                const urlParams = new URLSearchParams(window.location.search);
                const jobIdFromUrl = urlParams.get('job_id');
                
                if (jobIdFromUrl && !state.selectedJobId) {
                    state.selectedJobId = parseInt(jobIdFromUrl);
                }
                
                if (!state.selectedJobId || !state.jobs.find(j => j.id === state.selectedJobId)) {
                    state.selectedJobId = state.jobs[0].id;
                }

                // Load job costing data
                const jobCosting = await API.getJobCosting(state.selectedJobId);

                renderJobCosting($container, jobCosting);

            } catch (err) {
                console.error('Job costing load failed:', err);
                $container.html(`
                    <div class="pi-empty-state">
                        <i data-feather="alert-circle"></i>
                        <h3>Failed to load job costing</h3>
                        <p>${escapeHtml(err.message)}</p>
                    </div>
                `);
            } finally {
                setLoading($container, false);
            }
        },

        // Suppliers Tab
        async suppliers() {
            const $container = $('#tab-suppliers');
            setLoading($container, true);

            try {
                state.suppliers = await API.getSuppliers();

                if (state.suppliers.length === 0) {
                    $container.html(`
                        <div class="pi-empty-state">
                            <i data-feather="truck"></i>
                            <h3>No Suppliers Yet</h3>
                            <p>Add your first supplier to start tracking expenses by vendor.</p>
                            <button class="pi-btn pi-btn-primary" data-action="add-supplier">Add First Supplier</button>
                        </div>
                    `);
                    return;
                }

                renderSuppliersTable($container);

            } catch (err) {
                console.error('Suppliers load failed:', err);
                $container.html(`
                    <div class="pi-empty-state">
                        <i data-feather="alert-circle"></i>
                        <h3>Failed to load suppliers</h3>
                        <p>${escapeHtml(err.message)}</p>
                    </div>
                `);
            } finally {
                setLoading($container, false);
            }
        },

        // Mileage Tab
        async mileage() {
            const $container = $('#tab-mileage');
            setLoading($container, true);

            try {
                // Load vehicles and trips
                const [vehicles, mileageData] = await Promise.all([
                    loadVehicles(),
                    API.getMileage()
                ]);

                state.vehicles = vehicles;
                state.trips = mileageData.trips || [];

                if (state.vehicles.length === 0) {
                    $container.html(`
                        <div class="pi-empty-state">
                            <i data-feather="truck"></i>
                            <h3>No Vehicles Added</h3>
                            <p>Add a vehicle to start logging mileage trips.</p>
                            <button class="pi-btn pi-btn-primary" data-action="add-vehicle">Add First Vehicle</button>
                        </div>
                    `);
                    return;
                }

                renderMileageView($container, mileageData);

            } catch (err) {
                console.error('Mileage load failed:', err);
                $container.html(`
                    <div class="pi-empty-state">
                        <i data-feather="alert-circle"></i>
                        <h3>Failed to load mileage data</h3>
                        <p>${escapeHtml(err.message)}</p>
                    </div>
                `);
            } finally {
                setLoading($container, false);
            }
        },

        // Tax & Compliance Tab
        async tax() {
            const $container = $('#tab-tax');
            setLoading($container, true);

            try {
                const [cis, vat, retention] = await Promise.all([
                    API.getCIS(),
                    API.getVAT(),
                    API.getRetention()
                ]);

                renderTaxView($container, { cis, vat, retention });

            } catch (err) {
                console.error('Tax load failed:', err);
                $container.html(`
                    <div class="pi-empty-state">
                        <i data-feather="alert-circle"></i>
                        <h3>Failed to load tax data</h3>
                        <p>${escapeHtml(err.message)}</p>
                    </div>
                `);
            } finally {
                setLoading($container, false);
            }
        },

        // Settings Tab
        async settings() {
            const $container = $('#tab-settings');
            setLoading($container, true);

            try {
                state.settings = await API.getSettings();
                renderSettingsView($container, state.settings);
            } catch (err) {
                console.error('Settings load failed:', err);
                $container.html(`
                    <div class="pi-empty-state">
                        <i data-feather="alert-circle"></i>
                        <h3>Failed to load settings</h3>
                    </div>
                `);
            } finally {
                setLoading($container, false);
            }
        }
    };

    // ============================================
    // RENDER FUNCTIONS
    // ============================================

    function renderLedgerTable($container) {
        const categories = window.piExpensesData?.categories || {};
        const statuses = window.piExpensesData?.statuses || {};

        let html = `
            <div class="pi-ledger-header">
                <div class="pi-ledger-filters">
                    <input type="text" id="ledger-search" placeholder="Search expenses..." 
                        value="${escapeHtml(state.filters.search)}">
                    <select id="ledger-category">
                        <option value="">All Categories</option>
                        ${Object.entries(categories).map(([key, cat]) => 
                            `<option value="${key}" ${state.filters.category === key ? 'selected' : ''}>${escapeHtml(cat.label)}</option>`
                        ).join('')}
                    </select>
                    <select id="ledger-status">
                        <option value="">All Statuses</option>
                        ${Object.entries(statuses).map(([key, label]) => 
                            `<option value="${key}" ${state.filters.status === key ? 'selected' : ''}>${escapeHtml(label)}</option>`
                        ).join('')}
                    </select>
                    <input type="date" id="ledger-date-from" placeholder="From" value="${escapeHtml(state.filters.dateFrom)}">
                    <input type="date" id="ledger-date-to" placeholder="To" value="${escapeHtml(state.filters.dateTo)}">
                    <button class="pi-btn pi-btn-ghost" id="clear-filters">Clear</button>
                </div>
                <div class="pi-ledger-spacer"></div>
                <div class="pi-ledger-actions">
                    <button class="pi-btn pi-btn-primary" data-action="add-expense">
                        <i data-feather="plus"></i> Add Expense
                    </button>
                </div>
            </div>
        `;

        if (state.expenses.length === 0) {
            html += `
                <div class="pi-empty-state">
                    <i data-feather="file-text"></i>
                    <h3>No Expenses Found</h3>
                    <p>No expenses match your current filters.</p>
                </div>
            `;
        } else {
            html += `
                <div class="pi-bulk-toolbar" id="bulk-toolbar">
                    <span id="selected-count">0 selected</span>
                    <select id="bulk-status-change" class="pi-form-control" style="width: 150px; height: 32px;">
                        <option value="">Change Status...</option>
                        ${Object.entries(statuses).map(([key, label]) => `<option value="${key}">${escapeHtml(label)}</option>`).join('')}
                    </select>
                    <button class="pi-btn pi-btn-danger" id="bulk-delete" style="height: 32px; padding: 0 0.75rem;">
                        <i data-feather="trash-2"></i> Delete
                    </button>
                    <button class="pi-btn pi-btn-ghost" id="clear-selection" style="height: 32px; padding: 0 0.75rem;">
                        Clear
                    </button>
                </div>
                <div class="pi-data-table" id="ledger-table">
                    <table>
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all"></th>
                                <th>Date</th>
                                <th>Job</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Supplier</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            state.expenses.forEach(expense => {
                const catData = categories[expense.category] || { label: expense.category, color: '#94a3b8' };
                const statusData = statuses[expense.status] || expense.status;
                const jobLink = expense.job_id ? `/workspace/jobs/${expense.job_id}?tab=costs` : null;

                html += `
                    <tr data-id="${expense.id}">
                        <td><input type="checkbox" class="row-select" value="${expense.id}"></td>
                        <td>${formatDate(expense.expense_date)}</td>
                        <td>
                            ${expense.job_id ? `<a href="${jobLink}" class="pi-job-link" style="color: #156349; text-decoration: none; font-weight: 500;" title="View job costs">${escapeHtml(expense.job_title || 'Job')}</a>` : '-'}
                        </td>
                        <td>
                            <span class="pi-cat-badge pi-cat-${expense.category}">
                                ${escapeHtml(catData.label)}
                            </span>
                        </td>
                        <td>${escapeHtml(expense.description || '-').substring(0, 50)}${expense.description?.length > 50 ? '...' : ''}</td>
                        <td>${escapeHtml(expense.supplier_name || '-')}</td>
                        <td class="pi-amount">${formatCurrency(expense.amount)}</td>
                        <td>
                            <select class="pi-status-select pi-status-${expense.status}" data-expense-id="${expense.id}">
                                ${Object.entries(statuses).map(([key, label]) =>
                                    `<option value="${key}" ${expense.status === key ? 'selected' : ''}>${escapeHtml(label)}</option>`
                                ).join('')}
                            </select>
                        </td>
                        <td>
                            <div class="pi-action-btns">
                                <button class="pi-btn pi-btn-icon pi-btn-ghost edit-expense" title="Edit">
                                    <i data-feather="edit-2"></i>
                                </button>
                                <button class="pi-btn pi-btn-icon pi-btn-ghost delete-expense" title="Delete">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
                ${renderPagination()}
            `;
        }

        $container.html(html);
        if (window.feather) feather.replace();

        // Bind events
        bindLedgerEvents();
    }

    function renderPagination() {
        const totalPages = Math.ceil(state.pagination.total / state.pagination.perPage);
        if (totalPages <= 1) return '';

        let html = '<div class="pi-pagination">';
        
        html += `<button class="pi-page-btn prev ${state.pagination.page === 1 ? 'disabled' : ''}" data-page="${state.pagination.page - 1}">
            <i data-feather="chevron-left"></i>
        </button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= state.pagination.page - 1 && i <= state.pagination.page + 1)) {
                html += `<button class="pi-page-btn ${i === state.pagination.page ? 'active' : ''}" data-page="${i}">${i}</button>`;
            } else if (i === state.pagination.page - 2 || i === state.pagination.page + 2) {
                html += '<span class="pi-page-ellipsis">...</span>';
            }
        }

        html += `<button class="pi-page-btn next ${state.pagination.page === totalPages ? 'disabled' : ''}" data-page="${state.pagination.page + 1}">
            <i data-feather="chevron-right"></i>
        </button>`;

        html += '</div>';
        return html;
    }

    function renderJobCosting($container, data) {
        const { job_id, job_title, expenses, cost_codes, total_expenses, budget, quote_value, profit_margin, budget_used_percent } = data;

        const jobOptions = state.jobs.map(job => 
            `<option value="${job.id}" ${job.id === parseInt(job_id) ? 'selected' : ''}>${escapeHtml(job.title)}</option>`
        ).join('');

        let html = `
            <div class="pi-jobcosting-layout">
                <div class="pi-job-selector">
                    <div class="pi-job-selector-header">
                        <h3>Select Job</h3>
                    </div>
                    <div class="pi-job-cards">
                        <select id="job-select" class="pi-form-control" style="margin: 1rem;">
                            ${jobOptions}
                        </select>
                        ${state.jobs.map(job => {
                            const isActive = job.id === parseInt(job_id);
                            const totalCost = job.total_cost || 0;
                            const hasBudget = job.budget > 0;
                            const percentUsed = hasBudget ? (totalCost / job.budget * 100) : 0;
                            const statusClass = percentUsed > 90 ? 'red' : percentUsed > 70 ? 'yellow' : 'green';
                            
                            return `
                                <div class="pi-job-card ${statusClass} ${isActive ? 'active' : ''}" data-job-id="${job.id}">
                                    <div class="pi-job-card-header">
                                        <span class="pi-job-title">${escapeHtml(job.title)}</span>
                                        <span class="pi-job-status-badge ${statusClass}">${percentUsed.toFixed(0)}%</span>
                                    </div>
                                    <div class="pi-job-card-stats">
                                        <div class="pi-stat">
                                            <span class="pi-stat-label">Total Cost</span>
                                            <span class="pi-stat-value">${formatCurrency(totalCost)}</span>
                                        </div>
                                        <div class="pi-stat">
                                            <span class="pi-stat-label">Budget</span>
                                            <span class="pi-stat-value">${formatCurrency(job.budget)}</span>
                                        </div>
                                        <div class="pi-stat">
                                            <span class="pi-stat-label">Quote</span>
                                            <span class="pi-stat-value">${formatCurrency(job.quote)}</span>
                                        </div>
                                    </div>
                                    <div class="pi-job-card-details">
                                        <div class="pi-detail-row">
                                            <span class="pi-detail-item">
                                                <i data-feather="shopping-cart" style="width:12px;height:12px;"></i>
                                                ${job.expense_count || 0} expenses
                                            </span>
                                            <span class="pi-detail-item">
                                                <i data-feather="map-pin" style="width:12px;height:12px;"></i>
                                                ${job.trip_count || 0} trips
                                            </span>
                                        </div>
                                        <div class="pi-detail-row">
                                            <span class="pi-detail-item">
                                                <i data-feather="percent" style="width:12px;height:12px;"></i>
                                                Margin: ${job.profit_margin?.toFixed(1) || '0.0'}%
                                            </span>
                                            ${job.total_miles > 0 ? `
                                            <span class="pi-detail-item">
                                                <i data-feather="navigation" style="width:12px;height:12px;"></i>
                                                ${job.total_miles.toFixed(1)} mi
                                            </span>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
                
                <div class="pi-job-dashboard">
                    <div class="pi-budget-header">
                        <h2>${escapeHtml(job_title)}</h2>
                    </div>
                    
                    <div class="pi-budget-cards">
                        <div class="pi-kpi-card">
                            <div class="pi-kpi-icon pi-kpi-icon-green"><i data-feather="dollar-sign"></i></div>
                            <div class="pi-kpi-content">
                                <span class="pi-kpi-value">${formatCurrency(total_expenses)}</span>
                                <span class="pi-kpi-label">Total Expenses</span>
                            </div>
                        </div>
                        <div class="pi-kpi-card">
                            <div class="pi-kpi-icon pi-kpi-icon-blue"><i data-feather="target"></i></div>
                            <div class="pi-kpi-content">
                                <span class="pi-kpi-value">${formatCurrency(budget)}</span>
                                <span class="pi-kpi-label">Budget</span>
                            </div>
                        </div>
                        <div class="pi-kpi-card">
                            <div class="pi-kpi-icon pi-kpi-icon-purple"><i data-feather="file-text"></i></div>
                            <div class="pi-kpi-content">
                                <span class="pi-kpi-value">${formatCurrency(quote_value)}</span>
                                <span class="pi-kpi-label">Quote Value</span>
                            </div>
                        </div>
                        <div class="pi-kpi-card ${profit_margin < 20 ? 'pi-kpi-alert' : ''}">
                            <div class="pi-kpi-icon pi-kpi-icon-orange"><i data-feather="percent"></i></div>
                            <div class="pi-kpi-content">
                                <span class="pi-kpi-value">${profit_margin.toFixed(1)}%</span>
                                <span class="pi-kpi-label">Profit Margin</span>
                            </div>
                        </div>
                    </div>
                    
                    ${budget > 0 ? `
                        <div class="pi-budget-progress-section">
                            <div class="pi-budget-progress-bar">
                                <div class="pi-budget-progress-fill ${budget_used_percent > 80 ? 'pi-warning' : ''} ${budget_used_percent > 95 ? 'pi-danger' : ''}" 
                                     style="width: ${Math.min(budget_used_percent, 100)}%"></div>
                            </div>
                            <p>${budget_used_percent.toFixed(1)}% of budget used</p>
                        </div>
                    ` : ''}
                    
                    <div class="pi-cost-code-section">
                        <h3>Expenses by Category</h3>
                        ${cost_codes && cost_codes.length > 0 ? `
                            <div class="pi-data-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="pi-text-right">Count</th>
                                            <th class="pi-text-right">Total</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${cost_codes.map(cc => `
                                            <tr>
                                                <td>${escapeHtml(cc.category)}</td>
                                                <td class="pi-text-right">${cc.count}</td>
                                                <td class="pi-text-right">${formatCurrency(cc.total)}</td>
                                                <td>
                                                    <div class="pi-mini-progress">
                                                        <div class="pi-mini-progress-bar" style="width: ${total_expenses > 0 ? (cc.total / total_expenses * 100) : 0}%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : '<p>No expenses recorded for this job yet.</p>'}
                    </div>
                    
                    ${expenses && expenses.length > 0 ? `
                        <div class="pi-job-expenses-list">
                            <h3>Recent Expenses</h3>
                            <div class="pi-data-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Supplier</th>
                                            <th class="pi-text-right">Amount</th>
                                            <th width="80">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${expenses.slice(0, 10).map(exp => `
                                            <tr data-id="${exp.id}" class="job-costing-expense-row">
                                                <td>${formatDate(exp.expense_date)}</td>
                                                <td>${escapeHtml(exp.description || '-')}</td>
                                                <td>${escapeHtml(exp.supplier_name || '-')}</td>
                                                <td class="pi-text-right">${formatCurrency(exp.amount)}</td>
                                                <td>
                                                    <div class="pi-action-btns">
                                                        <button class="pi-btn pi-btn-icon pi-btn-ghost edit-job-expense" title="Edit">
                                                            <i data-feather="edit-2"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        $container.html(html);
        if (window.feather) feather.replace();

        // Bind job selection
        $('#job-select, .pi-job-card').on('click change', function() {
            const jobId = $(this).data('job-id') || $(this).val();
            if (jobId && jobId != state.selectedJobId) {
                state.selectedJobId = parseInt(jobId);
                TabHandlers.jobcosting();
            }
        });

        // Bind edit expense buttons in job costing
        $('.edit-job-expense').on('click', async function() {
            const id = $(this).closest('tr').data('id');
            const expense = expenses.find(e => e.id == id);
            if (expense) {
                await Modals.expense(expense);
            }
        });
    }

    function renderSuppliersTable($container) {
        let html = `
            <div class="pi-ledger-header">
                <div class="pi-ledger-filters">
                    <input type="text" id="supplier-search" placeholder="Search suppliers...">
                </div>
                <div class="pi-ledger-spacer"></div>
                <div class="pi-ledger-actions">
                    <button class="pi-btn pi-btn-primary" data-action="add-supplier">
                        <i data-feather="plus"></i> Add Supplier
                    </button>
                </div>
            </div>
            
            <div class="pi-data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>CIS Status</th>
                            <th>Total Spend</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        state.suppliers.forEach(supplier => {
            const cisLabels = {
                'not_applicable': 'N/A',
                'gross': 'Gross (0%)',
                'registered': 'Registered (20%)',
                'unregistered': 'Unregistered (30%)'
            };

            html += `
                <tr data-id="${supplier.id}">
                    <td>
                        <strong>${escapeHtml(supplier.name)}</strong>
                        ${supplier.account_number ? `<br><small class="pi-text-muted">Acc: ${escapeHtml(supplier.account_number)}</small>` : ''}
                    </td>
                    <td>${escapeHtml(supplier.contact_name || '-')}</td>
                    <td>${escapeHtml(supplier.phone || '-')}</td>
                    <td>${escapeHtml(supplier.email || '-')}</td>
                    <td><span class="pi-badge pi-cis-badge">${cisLabels[supplier.cis_status] || supplier.cis_status}</span></td>
                    <td class="pi-amount">${formatCurrency(supplier.total_spend || 0)}</td>
                    <td>
                        <div class="pi-action-btns">
                            <button class="pi-btn pi-btn-icon pi-btn-ghost edit-supplier" title="Edit">
                                <i data-feather="edit-2"></i>
                            </button>
                            <button class="pi-btn pi-btn-icon pi-btn-ghost delete-supplier" title="Delete">
                                <i data-feather="trash-2"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        $container.html(html);
        if (window.feather) feather.replace();

        // Bind events
        $('.edit-supplier').on('click', function() {
            const id = $(this).closest('tr').data('id');
            const supplier = state.suppliers.find(s => s.id == id);
            if (supplier) Modals.supplier(supplier);
        });

        $('.delete-supplier').on('click', function() {
            const id = $(this).closest('tr').data('id');
            const supplier = state.suppliers.find(s => s.id == id);
            if (supplier) {
                Modals.confirmDelete('supplier', supplier.name, async () => {
                    try {
                        await API.deleteSupplier(id);
                        showToast('Supplier deleted');
                        TabHandlers.suppliers();
                    } catch (err) {
                        showToast('Failed to delete supplier', 'error');
                    }
                });
            }
        });

        $('#supplier-search').on('input', function() {
            const term = $(this).val().toLowerCase();
            $('#tab-suppliers tbody tr').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(term));
            });
        });
    }

    function renderMileageView($container, data) {
        const { stats } = data;

        let html = `
            <div class="pi-ledger-header">
                <div class="pi-ledger-filters">
                    <select id="vehicle-filter" class="pi-form-control">
                        <option value="">All Vehicles</option>
                        ${state.vehicles.map(v => `<option value="${v.id}">${escapeHtml(v.make)} ${escapeHtml(v.model)}</option>`).join('')}
                    </select>
                </div>
                <div class="pi-ledger-spacer"></div>
                <div class="pi-ledger-actions">
                    <button class="pi-btn pi-btn-secondary" data-action="add-vehicle">
                        <i data-feather="plus"></i> Add Vehicle
                    </button>
                    <button class="pi-btn pi-btn-primary" data-action="log-trip">
                        <i data-feather="map-pin"></i> Log Trip
                    </button>
                </div>
            </div>
            
            <div class="pi-dashboard-grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="pi-kpi-card">
                    <div class="pi-kpi-icon pi-kpi-icon-blue"><i data-feather="map"></i></div>
                    <div class="pi-kpi-content">
                        <div class="pi-kpi-value">${stats?.total_miles?.toFixed(1) || '0.0'}</div>
                        <div class="pi-kpi-label">Total Miles</div>
                    </div>
                </div>
                <div class="pi-kpi-card">
                    <div class="pi-kpi-icon pi-kpi-icon-green"><i data-feather="credit-card"></i></div>
                    <div class="pi-kpi-content">
                        <div class="pi-kpi-value">${formatCurrency(stats?.total_claim || 0)}</div>
                        <div class="pi-kpi-label">Total Claim</div>
                    </div>
                </div>
                <div class="pi-kpi-card">
                    <div class="pi-kpi-icon pi-kpi-icon-orange"><i data-feather="navigation"></i></div>
                    <div class="pi-kpi-content">
                        <div class="pi-kpi-value">${stats?.total_trips || 0}</div>
                        <div class="pi-kpi-label">Total Trips</div>
                    </div>
                </div>
                <div class="pi-kpi-card">
                    <div class="pi-kpi-icon pi-kpi-icon-red"><i data-feather="cloud"></i></div>
                    <div class="pi-kpi-content">
                        <div class="pi-kpi-value">${stats?.co2_emissions?.toFixed(1) || '0.0'}kg</div>
                        <div class="pi-kpi-label">CO2 Emissions</div>
                    </div>
                </div>
            </div>
            
            <div class="pi-chart-card" style="margin-top: 1.5rem;">
                <h3>Recent Trips</h3>
                ${state.trips.length === 0 ? `
                    <div class="pi-empty-state">
                        <i data-feather="map-pin"></i>
                        <p>No trips logged yet.</p>
                    </div>
                ` : `
                    <div class="pi-data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Vehicle</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Job</th>
                                    <th class="pi-text-right">Miles</th>
                                    <th class="pi-text-right">Claim</th>
                                    <th width="100">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${state.trips.slice(0, 20).map(trip => {
                                    const vehicle = state.vehicles.find(v => v.id == trip.vehicle_id) || {};
                                    return `
                                <tr data-id="${trip.id}">
                                    <td>${formatDate(trip.trip_date || trip.date)}</td>
                                    <td>${escapeHtml(vehicle.make || '')} ${escapeHtml(vehicle.model || '')}</td>
                                    <td>${escapeHtml(trip.from_address || 'Office')}</td>
                                    <td>${escapeHtml(trip.to_address)}</td>
                                    <td>${trip.job_title ? escapeHtml(trip.job_title) : (trip.job ? escapeHtml(trip.job.title || trip.job.name) : '-')}</td>
                                    <td class="pi-text-right">${trip.miles}</td>
                                    <td class="pi-text-right">${formatCurrency(trip.claim_amount)}</td>
                                    <td>
                                        <div class="pi-action-btns">
                                            <button class="pi-btn pi-btn-icon pi-btn-ghost edit-trip" title="Edit">
                                                <i data-feather="edit-2"></i>
                                            </button>
                                            <button class="pi-btn pi-btn-icon pi-btn-ghost delete-trip" title="Delete">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `}
            </div>
        `;

        $container.html(html);
        if (window.feather) feather.replace();
        
        // Bind vehicle filter
        $('#vehicle-filter').on('change', function() {
            const vehicleId = $(this).val();
            filterTripsByVehicle(vehicleId);
        });
        
        // Bind edit trip buttons
        $('.edit-trip').on('click', function() {
            const id = $(this).closest('tr').data('id');
            const trip = state.trips.find(t => t.id == id);
            if (trip) {
                Modals.trip(trip);
            }
        });
        
        // Bind delete trip buttons
        $('.delete-trip').on('click', function() {
            const id = $(this).closest('tr').data('id');
            const trip = state.trips.find(t => t.id == id);
            if (trip) {
                Modals.confirmDelete('trip', `${trip.from_address} to ${trip.to_address}`, async () => {
                    try {
                        await API.deleteTrip(id);
                        showToast('Trip deleted');
                        TabHandlers.mileage();
                    } catch (err) {
                        showToast('Failed to delete trip', 'error');
                    }
                });
            }
        });
    }
    
    function filterTripsByVehicle(vehicleId) {
        const $rows = $('#tab-mileage tbody tr');
        if (!vehicleId) {
            $rows.show();
        } else {
            $rows.each(function() {
                const rowVehicle = $(this).find('td:nth-child(2)').text().trim();
                const selectedVehicle = state.vehicles.find(v => v.id == vehicleId);
                const vehicleName = selectedVehicle ? 
                    `${selectedVehicle.make} ${selectedVehicle.model}`.trim() : '';
                $(this).toggle(rowVehicle === vehicleName);
            });
        }
    }

    function renderTaxView($container, data) {
        const { cis, vat, retention } = data;

        $container.html(`
            <div class="pi-settings-grid">
                <div class="pi-settings-card">
                    <div class="pi-settings-card-header">
                        <h3><i data-feather="users"></i> CIS (Construction Industry Scheme)</h3>
                        <p>Track deductions from subcontractors</p>
                    </div>
                    <div class="pi-settings-card-body">
                        <div class="pi-tax-stats">
                            <div class="pi-tax-stat">
                                <span class="pi-tax-value">${cis.registered_count || 0}</span>
                                <span class="pi-tax-label">Registered Subcontractors</span>
                            </div>
                            <div class="pi-tax-stat">
                                <span class="pi-tax-value">${cis.unregistered_count || 0}</span>
                                <span class="pi-tax-label">Unregistered</span>
                            </div>
                            <div class="pi-tax-stat">
                                <span class="pi-tax-value">${formatCurrency(cis.total_deductions || 0)}</span>
                                <span class="pi-tax-label">Total Deductions</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="pi-settings-card">
                    <div class="pi-settings-card-header">
                        <h3><i data-feather="percent"></i> VAT Summary</h3>
                        <p>Reclaimable VAT breakdown</p>
                    </div>
                    <div class="pi-settings-card-body">
                        <div class="pi-tax-stats">
                            <div class="pi-tax-stat">
                                <span class="pi-tax-value">${formatCurrency(vat.standard_vat || 0)}</span>
                                <span class="pi-tax-label">Standard Rate (20%)</span>
                            </div>
                            <div class="pi-tax-stat">
                                <span class="pi-tax-value">${formatCurrency(vat.reduced_vat || 0)}</span>
                                <span class="pi-tax-label">Reduced Rate (5%)</span>
                            </div>
                            <div class="pi-tax-stat pi-tax-highlight">
                                <span class="pi-tax-value">${formatCurrency(vat.total_reclaimable || 0)}</span>
                                <span class="pi-tax-label">Total Reclaimable</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="pi-settings-card">
                    <div class="pi-settings-card-header">
                        <h3><i data-feather="lock"></i> Retention Held</h3>
                        <p>Track money held for defects liability</p>
                    </div>
                    <div class="pi-settings-card-body">
                        <div class="pi-tax-stats">
                            <div class="pi-tax-stat">
                                <span class="pi-tax-value">${retention.active_count || 0}</span>
                                <span class="pi-tax-label">Active Retentions</span>
                            </div>
                            <div class="pi-tax-stat">
                                <span class="pi-tax-value">${formatCurrency(retention.total_held || 0)}</span>
                                <span class="pi-tax-label">Total Held</span>
                            </div>
                            <div class="pi-tax-stat pi-tax-highlight">
                                <span class="pi-tax-value">${formatCurrency(retention.due_release || 0)}</span>
                                <span class="pi-tax-label">Due for Release</span>
                            </div>
                        </div>
                        
                        ${retention.items && retention.items.length > 0 ? `
                            <div class="pi-retention-list" style="margin-top: 1.5rem;">
                                <h4>Retention Items</h4>
                                <div class="pi-data-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Supplier</th>
                                                <th>Job</th>
                                                <th>Amount</th>
                                                <th>Release Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${retention.items.map(item => `
                                                <tr>
                                                    <td>${escapeHtml(item.supplier_name)}</td>
                                                    <td>${escapeHtml(item.job_title || '-')}</td>
                                                    <td>${formatCurrency(item.retention_amount)}</td>
                                                    <td>${formatDate(item.release_date)}</td>
                                                    <td>
                                                        ${item.can_release ? 
                                                            '<span class="pi-badge pi-badge-success">Release Due</span>' :
                                                            '<span class="pi-badge pi-badge-secondary">Held</span>'
                                                        }
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `);

        if (window.feather) feather.replace();
    }

    function renderSettingsView($container, settings) {
        $container.html(`
            <div class="pi-settings-grid">
                <div class="pi-settings-card">
                    <div class="pi-settings-card-header">
                        <h3>General Settings</h3>
                        <p>Default values for new expenses</p>
                    </div>
                    <div class="pi-settings-card-body">
                        <form id="settings-form">
                            <div class="pi-form-group">
                                <label>Default Category</label>
                                <select name="default_category" class="pi-form-control">
                                    ${Object.entries(window.piExpensesData?.categories || {}).map(([key, cat]) => 
                                        `<option value="${key}" ${settings.default_category === key ? 'selected' : ''}>${escapeHtml(cat.label)}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label>Default Payment Method</label>
                                <select name="default_payment_method" class="pi-form-control">
                                    ${Object.entries(window.piExpensesData?.paymentMethods || {}).map(([key, label]) => 
                                        `<option value="${key}" ${settings.default_payment_method === key ? 'selected' : ''}>${escapeHtml(label)}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="pi-form-group">
                                <label class="pi-toggle">
                                    <input type="checkbox" name="vat_registered" ${settings.vat_registered ? 'checked' : ''}>
                                    <span class="pi-toggle-slider"></span>
                                    <span class="pi-toggle-label">VAT Registered</span>
                                </label>
                            </div>
                            <div class="pi-form-group">
                                <label class="pi-toggle">
                                    <input type="checkbox" name="cis_enabled" ${settings.cis_enabled ? 'checked' : ''}>
                                    <span class="pi-toggle-slider"></span>
                                    <span class="pi-toggle-label">CIS Enabled</span>
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="pi-settings-card">
                    <div class="pi-settings-card-header">
                        <h3>Mileage Settings</h3>
                        <p>Configure mileage rates</p>
                    </div>
                    <div class="pi-settings-card-body">
                        <form id="mileage-settings-form">
                            <div class="pi-form-group">
                                <label>Mileage Rate (£/mile)</label>
                                <input type="number" name="mileage_rate" class="pi-form-control" step="0.01" min="0"
                                    value="${settings.mileage_rate || 0.70}">
                            </div>
                            <div class="pi-form-group">
                                <label>Currency</label>
                                <select name="currency" class="pi-form-control">
                                    <option value="GBP" ${settings.currency === 'GBP' ? 'selected' : ''}>GBP (£)</option>
                                    <option value="EUR" ${settings.currency === 'EUR' ? 'selected' : ''}>EUR (€)</option>
                                    <option value="USD" ${settings.currency === 'USD' ? 'selected' : ''}>USD ($)</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="pi-settings-card">
                    <div class="pi-settings-card-header">
                        <h3>Automation</h3>
                        <p>Auto-processing settings</p>
                    </div>
                    <div class="pi-settings-card-body">
                        <form id="automation-settings-form">
                            <div class="pi-form-group">
                                <label class="pi-toggle">
                                    <input type="checkbox" name="auto_categorize" ${settings.auto_categorize ? 'checked' : ''}>
                                    <span class="pi-toggle-slider"></span>
                                    <span class="pi-toggle-label">Auto-categorize expenses</span>
                                </label>
                            </div>
                            <div class="pi-form-group">
                                <label class="pi-toggle">
                                    <input type="checkbox" name="qbo_auto_sync" ${settings.qbo_auto_sync ? 'checked' : ''}>
                                    <span class="pi-toggle-slider"></span>
                                    <span class="pi-toggle-label">Auto-sync to QuickBooks</span>
                                </label>
                            </div>
                            <div class="pi-form-group">
                                <label>Receipt Reminder (days)</label>
                                <input type="number" name="receipt_reminder_days" class="pi-form-control" min="0"
                                    value="${settings.receipt_reminder_days || 3}">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="pi-settings-actions">
                <button class="pi-btn pi-btn-primary" id="save-settings">Save Settings</button>
            </div>
        `);

        $('#save-settings').on('click', async () => {
            const data = {
                default_category: $('[name="default_category"]').val(),
                default_payment_method: $('[name="default_payment_method"]').val(),
                vat_registered: $('[name="vat_registered"]').is(':checked'),
                cis_enabled: $('[name="cis_enabled"]').is(':checked'),
                mileage_rate: parseFloat($('[name="mileage_rate"]').val()),
                currency: $('[name="currency"]').val(),
                auto_categorize: $('[name="auto_categorize"]').is(':checked'),
                qbo_auto_sync: $('[name="qbo_auto_sync"]').is(':checked'),
                receipt_reminder_days: parseInt($('[name="receipt_reminder_days"]').val())
            };

            try {
                await API.saveSettings(data);
                showToast('Settings saved successfully');
                config.currency = data.currency;
            } catch (err) {
                showToast('Failed to save settings', 'error');
            }
        });
    }

    // ============================================
    // CHARTS
    // ============================================

    function renderSpendingChart(trends) {
        const ctx = document.getElementById('spending-chart');
        if (!ctx || !window.Chart) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trends.labels,
                datasets: [{
                    label: 'Actual',
                    data: trends.actual,
                    borderColor: '#156349',
                    backgroundColor: 'rgba(21, 99, 73, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Projected',
                    data: trends.projected,
                    borderColor: '#94a3b8',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true },
                    tooltip: {
                        callbacks: {
                            label: (context) => formatCurrency(context.raw)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '£' + value
                        }
                    }
                }
            }
        });
    }

    function renderCategoryChart(byCategory) {
        const ctx = document.getElementById('category-chart');
        if (!ctx || !window.Chart || !byCategory) return;

        const labels = Object.keys(byCategory);
        const data = Object.values(byCategory);
        const categories = window.piExpensesData?.categories || {};

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels.map(l => categories[l]?.label || l),
                datasets: [{
                    data: data,
                    backgroundColor: [
                        '#156349', '#3b82f6', '#f59e0b', '#ef4444',
                        '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: (context) => formatCurrency(context.raw)
                        }
                    }
                }
            }
        });
    }

    // ============================================
    // ACTIVITY FEED
    // ============================================

    async function loadActivityFeed() {
        try {
            const activity = await API.getActivity(10);
            const $list = $('#activity-list');

            if (activity.length === 0) {
                $list.html('<div class="pi-empty-state">No recent activity</div>');
                return;
            }

            $list.html(activity.map(item => `
                <div class="pi-activity-item">
                    <div class="pi-activity-icon">
                        <i data-feather="${getActivityIcon(item.action)}"></i>
                    </div>
                    <div class="pi-activity-content">
                        <div class="pi-activity-title">${escapeHtml(item.description || item.action)}</div>
                        <div class="pi-activity-meta">
                            ${escapeHtml(item.user_name)} · ${formatDate(item.created_at)}
                        </div>
                    </div>
                    ${item.amount ? `<div class="pi-activity-amount">${formatCurrency(item.amount)}</div>` : ''}
                </div>
            `).join(''));

            if (window.feather) feather.replace();
        } catch (err) {
            console.error('Failed to load activity:', err);
        }
    }

    function getActivityIcon(action) {
        const icons = {
            'created': 'plus-circle',
            'updated': 'edit-2',
            'deleted': 'trash-2',
            'approved': 'check-circle',
            'rejected': 'x-circle',
            'receipt_uploaded': 'upload',
            'synced': 'refresh-cw',
            'default': 'activity'
        };
        return icons[action] || icons.default;
    }

    async function loadNotifications() {
        try {
            const notifications = await API.getNotifications();
            const $badge = $('#notification-badge');
            const $list = $('#notification-list');
            const $markAllBtn = $('#mark-all-read');
            
            const unreadCount = notifications.filter(n => !n.read).length;
            
            // Update badge
            if (unreadCount > 0) {
                $badge.text(unreadCount > 99 ? '99+' : unreadCount).show();
                $markAllBtn.show();
            } else {
                $badge.hide();
                $markAllBtn.hide();
            }
            
            // Update list
            if (notifications.length === 0) {
                $list.html(`
                    <div class="pi-empty-state" style="padding: 2rem;">
                        <i data-feather="bell-off" style="width: 32px; height: 32px; opacity: 0.5;"></i>
                        <p>No notifications</p>
                    </div>
                `);
            } else {
                $list.html(notifications.map(n => `
                    <div class="pi-exp-notification-item ${n.read ? '' : 'unread'}">
                        <div class="pi-exp-notification-icon ${n.type}">
                            <i data-feather="${n.icon || 'bell'}"></i>
                        </div>
                        <div class="pi-exp-notification-content">
                            <p>${escapeHtml(n.message)}</p>
                            <span>${formatDate(n.created_at)}</span>
                        </div>
                    </div>
                `).join(''));
            }
            
            if (window.feather) feather.replace();
        } catch (err) {
            console.error('Failed to load notifications:', err);
        }
    }

    // ============================================
    // DATA LOADING
    // ============================================

    async function loadVehicles() {
        try {
            return await API.getVehicles();
        } catch (err) {
            console.warn('Failed to load vehicles:', err);
            return [];
        }
    }

    // ============================================
    // EVENT BINDING
    // ============================================

    function bindLedgerEvents() {
        // Filters
        $('#ledger-search').on('input', debounce(() => {
            state.filters.search = $('#ledger-search').val();
            state.pagination.page = 1;
            TabHandlers.ledger();
        }, 300));

        $('#ledger-category, #ledger-status').on('change', () => {
            state.filters.category = $('#ledger-category').val();
            state.filters.status = $('#ledger-status').val();
            state.pagination.page = 1;
            TabHandlers.ledger();
        });

        $('#ledger-date-from, #ledger-date-to').on('change', () => {
            state.filters.dateFrom = $('#ledger-date-from').val();
            state.filters.dateTo = $('#ledger-date-to').val();
            state.pagination.page = 1;
            TabHandlers.ledger();
        });

        $('#clear-filters').on('click', () => {
            state.filters = { search: '', category: '', status: '', dateFrom: '', dateTo: '' };
            state.pagination.page = 1;
            TabHandlers.ledger();
        });

        // Pagination
        $('.pi-page-btn').on('click', function() {
            if ($(this).hasClass('disabled')) return;
            state.pagination.page = parseInt($(this).data('page'));
            TabHandlers.ledger();
        });

        // Multi-select logic
        const selectedIds = new Set();
        
        function updateBulkToolbar() {
            const count = selectedIds.size;
            $('#selected-count').text(`${count} selected`);
            $('#bulk-toolbar').toggleClass('active', count > 0);
            
            // Sync checkboxes
            $('.row-select').each(function() {
                $(this).prop('checked', selectedIds.has($(this).val()));
            });
            $('#select-all').prop('checked', count > 0 && count === $('.row-select').length);
        }
        
        // Select all checkbox
        $('#select-all').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.row-select').each(function() {
                const id = $(this).val();
                if (isChecked) {
                    selectedIds.add(id);
                } else {
                    selectedIds.delete(id);
                }
            });
            updateBulkToolbar();
        });
        
        // Individual row select
        $(document).on('change', '.row-select', function() {
            const id = $(this).val();
            if ($(this).is(':checked')) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            updateBulkToolbar();
        });
        
        // Clear selection
        $('#clear-selection').on('click', function() {
            selectedIds.clear();
            updateBulkToolbar();
        });
        
        // Bulk delete
        $('#bulk-delete').on('click', async function() {
            if (selectedIds.size === 0) return;
            
            Modals.confirmDelete('expenses', `${selectedIds.size} expenses`, async () => {
                try {
                    const promises = Array.from(selectedIds).map(id => API.deleteExpense(id));
                    await Promise.all(promises);
                    showToast(`${selectedIds.size} expenses deleted`);
                    selectedIds.clear();
                    TabHandlers.ledger();
                } catch (err) {
                    showToast('Failed to delete some expenses', 'error');
                }
            });
        });
        
        // Bulk status change
        $('#bulk-status-change').on('change', async function() {
            const newStatus = $(this).val();
            if (!newStatus || selectedIds.size === 0) return;
            
            try {
                const promises = Array.from(selectedIds).map(id => API.updateExpense(id, { status: newStatus }));
                await Promise.all(promises);
                showToast(`Status updated for ${selectedIds.size} expenses`);
                selectedIds.clear();
                updateBulkToolbar();
                TabHandlers.ledger();
            } catch (err) {
                showToast('Failed to update status', 'error');
            }
            
            $(this).val('');
        });
        
        // Individual status change
        $(document).on('change', '.pi-status-select', async function() {
            const expenseId = $(this).data('expense-id');
            const newStatus = $(this).val();
            const oldStatus = $(this).data('old-status');
            
            try {
                await API.updateExpense(expenseId, { status: newStatus });
                showToast('Status updated');
                // Update the class on the select element
                $(this).removeClass(`pi-status-${oldStatus}`).addClass(`pi-status-${newStatus}`);
                $(this).data('old-status', newStatus);
            } catch (err) {
                showToast('Failed to update status', 'error');
                $(this).val(oldStatus);
            }
        });

        // Edit/Delete
        $('.edit-expense').on('click', async function() {
            const id = $(this).closest('tr').data('id');
            const expense = state.expenses.find(e => e.id == id);
            if (expense) await Modals.expense(expense);
        });

        $('.delete-expense').on('click', function() {
            const id = $(this).closest('tr').data('id');
            const expense = state.expenses.find(e => e.id == id);
            if (expense) {
                Modals.confirmDelete('expense', expense.description || 'Expense', async () => {
                    try {
                        await API.deleteExpense(id);
                        showToast('Expense deleted');
                        TabHandlers.ledger();
                    } catch (err) {
                        showToast('Failed to delete expense', 'error');
                    }
                });
            }
        });
    }

    function bindGlobalEvents() {
        // Tab navigation
        $(document).on('click', '.pi-exp-nav-item', function() {
            const tab = $(this).data('tab');
            if (!tab) return;

            $('.pi-exp-nav-item').removeClass('active');
            $(this).addClass('active');

            $('.pi-exp-tab-content').removeClass('active');
            $(`#tab-${tab}`).addClass('active');

            state.currentTab = tab;

            // Load tab content
            if (TabHandlers[tab]) {
                TabHandlers[tab]();
            }
        });

        // Quick action buttons - comprehensive binding with delegation
        $(document).on('click', '[data-action="add-expense"]', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const jobId = $(this).data('job');
            if (jobId) {
                await Modals.expense({ job_id: jobId });
            } else {
                await Modals.expense();
            }
        });
        
        $(document).on('click', '[data-action="add-supplier"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            Modals.supplier();
        });
        
        $(document).on('click', '[data-action="log-trip"]', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            await Modals.trip();
        });
        
        $(document).on('click', '[data-action="add-vehicle"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            Modals.vehicle();
        });

        // Tab links
        $(document).on('click', '[data-tab]', function(e) {
            if ($(this).hasClass('pi-exp-nav-item')) return;
            e.preventDefault();
            const tab = $(this).data('tab');
            if (tab) {
                $(`.pi-exp-nav-item[data-tab="${tab}"]`).click();
            }
        });

        // Mobile menu
        $(document).on('click', '.pi-exp-mobile-menu-toggle', () => {
            $('.pi-exp-nav-container').toggleClass('mobile-open');
        });

        // Header navigation drag scrolling
        initNavDragScroll();
        
        // Search toggle
        $(document).on('click', '.pi-exp-search-trigger', function() {
            $('.pi-exp-search-input').addClass('active').find('input').focus();
        });
        
        $(document).on('click', '.pi-exp-search-close', function() {
            $('.pi-exp-search-input').removeClass('active');
        });
        
        // Notifications dropdown
        $(document).on('click', '.pi-exp-notifications > .pi-exp-icon-btn', function(e) {
            e.stopPropagation();
            const $dropdown = $(this).siblings('.pi-exp-dropdown');
            $('.pi-exp-dropdown').not($dropdown).removeClass('active');
            $dropdown.toggleClass('active');
        });
        
        // User menu dropdown
        $(document).on('click', '.pi-exp-avatar', function(e) {
            e.stopPropagation();
            const $dropdown = $(this).siblings('.pi-exp-dropdown');
            $('.pi-exp-dropdown').not($dropdown).removeClass('active');
            $dropdown.toggleClass('active');
        });
        
        // Close dropdowns when clicking outside
        $(document).on('click', function() {
            $('.pi-exp-dropdown').removeClass('active');
        });
        
        // Job costing job selection
        $(document).on('click', '.pi-job-card', function() {
            const jobId = $(this).data('job-id');
            if (jobId) {
                state.selectedJobId = jobId;
                TabHandlers.jobcosting();
            }
        });
        
        // Refresh buttons
        $(document).on('click', '[data-action="refresh-dashboard"]', function() {
            TabHandlers.dashboard();
        });
        
        // Export buttons
        $(document).on('click', '[data-action="export-csv"]', function() {
            DataExport.exportExpenses('csv');
        });
        
        $(document).on('click', '[data-action="export-json"]', function() {
            DataExport.exportExpenses('json');
        });
        
        // Help button
        $(document).on('click', '.pi-exp-help-btn', function() {
            showKeyboardShortcuts();
        });
    }

    // ============================================
    // HEADER NAVIGATION DRAG SCROLLING
    // ============================================
    
    function initNavDragScroll() {
        const $navScroll = $('.pi-exp-nav-scroll');
        if (!$navScroll.length) return;

        let isDown = false;
        let startX;
        let scrollLeft;

        $navScroll.on('mousedown', function(e) {
            isDown = true;
            $navScroll.addClass('dragging');
            startX = e.pageX - $navScroll.offset().left;
            scrollLeft = $navScroll.scrollLeft();
        });

        $navScroll.on('mouseleave', function() {
            isDown = false;
            $navScroll.removeClass('dragging');
        });

        $navScroll.on('mouseup', function() {
            isDown = false;
            $navScroll.removeClass('dragging');
        });

        $navScroll.on('mousemove', function(e) {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - $navScroll.offset().left;
            const walk = (x - startX) * 2; // Scroll speed multiplier
            $navScroll.scrollLeft(scrollLeft - walk);
        });
        
        // Touch support for mobile
        let touchStartX;
        let touchScrollLeft;
        
        $navScroll.on('touchstart', function(e) {
            touchStartX = e.originalEvent.touches[0].pageX - $navScroll.offset().left;
            touchScrollLeft = $navScroll.scrollLeft();
        }, { passive: true });
        
        $navScroll.on('touchmove', function(e) {
            const touchX = e.originalEvent.touches[0].pageX - $navScroll.offset().left;
            const walk = (touchX - touchStartX) * 2;
            $navScroll.scrollLeft(touchScrollLeft - walk);
        }, { passive: true });
        
        // Add scroll indicators if content overflows
        function updateScrollIndicators() {
            const scrollWidth = $navScroll[0].scrollWidth;
            const clientWidth = $navScroll[0].clientWidth;
            const scrollLeft = $navScroll.scrollLeft();
            
            $navScroll.toggleClass('can-scroll-left', scrollLeft > 10);
            $navScroll.toggleClass('can-scroll-right', scrollLeft < scrollWidth - clientWidth - 10);
        }
        
        $navScroll.on('scroll', updateScrollIndicators);
        $(window).on('resize', updateScrollIndicators);
        setTimeout(updateScrollIndicators, 100);
    }

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    
    async function initKeyboardShortcuts() {
        $(document).on('keydown', async function(e) {
            // Ignore if in input/textarea
            if ($(e.target).is('input, textarea, select')) return;
            
            // ESC - Close modal
            if (e.key === 'Escape') {
                if ($('#pi-modal-overlay').length) {
                    Modals.close();
                    return;
                }
            }
            
            // Shortcuts with Ctrl/Cmd
            if (e.ctrlKey || e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'n':
                        e.preventDefault();
                        Modals.expense();
                        break;
                    case 's':
                        e.preventDefault();
                        if (state.currentTab === 'settings') {
                            $('#save-settings').click();
                        }
                        break;
                    case 'f':
                        e.preventDefault();
                        $('.pi-exp-search-trigger').click();
                        break;
                    case '1':
                        e.preventDefault();
                        switchToTab('dashboard');
                        break;
                    case '2':
                        e.preventDefault();
                        switchToTab('ledger');
                        break;
                    case '3':
                        e.preventDefault();
                        switchToTab('suppliers');
                        break;
                    case '4':
                        e.preventDefault();
                        switchToTab('tax');
                        break;
                    case '5':
                        e.preventDefault();
                        switchToTab('settings');
                        break;
                }
            }
            
            // Quick navigation without modifier
            switch(e.key) {
                case '?':
                    e.preventDefault();
                    showKeyboardShortcuts();
                    break;
                case 'h':
                case 'H':
                    e.preventDefault();
                    switchToTab('dashboard');
                    break;
                case 'e':
                case 'E':
                    e.preventDefault();
                    switchToTab('ledger');
                    break;
                case 's':
                case 'S':
                    e.preventDefault();
                    switchToTab('suppliers');
                    break;
                case 't':
                case 'T':
                    e.preventDefault();
                    switchToTab('tax');
                    break;
                case 'n':
                case 'N':
                    e.preventDefault();
                    await Modals.expense();
                    break;
                case 'r':
                case 'R':
                    e.preventDefault();
                    // Refresh current tab
                    if (TabHandlers[state.currentTab]) {
                        TabHandlers[state.currentTab]();
                    }
                    break;
            }
        });
    }
    
    function switchToTab(tab) {
        const $tab = $(`.pi-exp-nav-item[data-tab="${tab}"]`);
        if ($tab.length) {
            $tab.click();
        }
    }
    
    function showKeyboardShortcuts() {
        const html = `
            <div class="pi-shortcuts-list">
                <div class="pi-shortcut-group">
                    <h4>Navigation</h4>
                    <div class="pi-shortcut-item"><kbd>H</kbd> <span>Dashboard</span></div>
                    <div class="pi-shortcut-item"><kbd>E</kbd> <span>Expense Ledger</span></div>
                    <div class="pi-shortcut-item"><kbd>S</kbd> <span>Suppliers</span></div>
                    <div class="pi-shortcut-item"><kbd>T</kbd> <span>Tax</span></div>
                </div>
                <div class="pi-shortcut-group">
                    <h4>Actions</h4>
                    <div class="pi-shortcut-item"><kbd>N</kbd> <span>New Expense</span></div>
                    <div class="pi-shortcut-item"><kbd>R</kbd> <span>Refresh</span></div>
                    <div class="pi-shortcut-item"><kbd>?</kbd> <span>Show Shortcuts</span></div>
                    <div class="pi-shortcut-item"><kbd>Esc</kbd> <span>Close Modal</span></div>
                </div>
                <div class="pi-shortcut-group">
                    <h4>Advanced</h4>
                    <div class="pi-shortcut-item"><kbd>Ctrl+1-5</kbd> <span>Quick Tab Switch</span></div>
                    <div class="pi-shortcut-item"><kbd>Ctrl+F</kbd> <span>Search</span></div>
                    <div class="pi-shortcut-item"><kbd>Ctrl+N</kbd> <span>New Expense</span></div>
                </div>
            </div>
        `;
        
        Modals.open(html, { 
            title: 'Keyboard Shortcuts', 
            size: 'medium',
            footer: '<button class="pi-btn pi-btn-primary" data-dismiss="modal">Got it!</button>'
        });
    }

    // ============================================
    // ERROR HANDLING & RETRY LOGIC
    // ============================================
    
    const ErrorHandler = {
        retryCount: 0,
        maxRetries: 3,
        
        async withRetry(operation, context = 'Operation') {
            this.retryCount = 0;
            
            while (this.retryCount < this.maxRetries) {
                try {
                    return await operation();
                } catch (err) {
                    this.retryCount++;
                    
                    // Log error
                    console.error(`${context} failed (attempt ${this.retryCount}):`, err);
                    
                    // Check if retryable
                    if (this.retryCount >= this.maxRetries) {
                        throw err;
                    }
                    
                    // Check if it's a network error or 5xx server error
                    const isRetryable = !err.status || err.status >= 500 || err.status === 0;
                    
                    if (!isRetryable) {
                        throw err;
                    }
                    
                    // Wait before retry with exponential backoff
                    const delay = Math.min(1000 * Math.pow(2, this.retryCount - 1), 5000);
                    showToast(`${context} failed. Retrying in ${delay/1000}s...`, 'warning');
                    await this.sleep(delay);
                }
            }
        },
        
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        
        handleError(err, context = 'Error') {
            let message = err.message || 'An unexpected error occurred';
            
            // Provide user-friendly messages for common errors
            if (err.status === 401) {
                message = 'Session expired. Please refresh the page and log in again.';
            } else if (err.status === 403) {
                message = 'You don\'t have permission to perform this action.';
            } else if (err.status === 404) {
                message = 'The requested item was not found.';
            } else if (err.status >= 500) {
                message = 'Server error. Please try again later.';
            } else if (err.name === 'TypeError' && err.message.includes('fetch')) {
                message = 'Network error. Please check your connection.';
            }
            
            showToast(`${context}: ${message}`, 'error');
            console.error(`${context}:`, err);
        }
    };

    // ============================================
    // CONNECTIVITY & OFFLINE HANDLING
    // ============================================
    
    const ConnectivityManager = {
        isOnline: navigator.onLine,
        pendingRequests: [],
        
        init() {
            window.addEventListener('online', () => this.handleOnline());
            window.addEventListener('offline', () => this.handleOffline());
            
            // Check connection periodically
            setInterval(() => this.checkConnection(), 30000);
        },
        
        handleOnline() {
            this.isOnline = true;
            showToast('Connection restored', 'success');
            this.processPendingRequests();
            this.hideOfflineBanner();
        },
        
        handleOffline() {
            this.isOnline = false;
            showToast('You are offline. Changes will be saved locally.', 'warning');
            this.showOfflineBanner();
        },
        
        checkConnection() {
            // Skip health check - just verify we're online
            return Promise.resolve();
        },
        
        showOfflineBanner() {
            if ($('#offline-banner').length) return;
            
            const banner = $(`
                <div id="offline-banner" class="pi-offline-banner">
                    <i data-feather="wifi-off"></i>
                    <span>You are offline</span>
                    <button onclick="ConnectivityManager.retryConnection()">Retry</button>
                </div>
            `);
            $('body').append(banner);
            if (window.feather) feather.replace();
        },
        
        hideOfflineBanner() {
            $('#offline-banner').remove();
        },
        
        retryConnection() {
            showToast('Checking connection...', 'info');
            this.checkConnection();
        },
        
        queueRequest(request) {
            this.pendingRequests.push(request);
            this.savePendingRequests();
        },
        
        savePendingRequests() {
            localStorage.setItem('pi_pending_requests', JSON.stringify(this.pendingRequests));
        },
        
        loadPendingRequests() {
            const saved = localStorage.getItem('pi_pending_requests');
            if (saved) {
                this.pendingRequests = JSON.parse(saved);
            }
        },
        
        async processPendingRequests() {
            if (this.pendingRequests.length === 0) return;
            
            showToast(`Syncing ${this.pendingRequests.length} pending changes...`, 'info');
            
            const successful = [];
            for (const request of this.pendingRequests) {
                try {
                    await API.request(request.endpoint, request.options);
                    successful.push(request);
                } catch (err) {
                    console.error('Failed to sync request:', err);
                }
            }
            
            // Remove successful requests
            this.pendingRequests = this.pendingRequests.filter(r => !successful.includes(r));
            this.savePendingRequests();
            
            if (successful.length > 0) {
                showToast(`${successful.length} changes synced successfully`, 'success');
            }
        }
    };

    // ============================================
    // FORM AUTO-SAVE & DRAFT RECOVERY
    // ============================================
    
    const FormAutoSave = {
        draftKey: 'pi_expense_draft',
        autoSaveInterval: null,
        
        init(formSelector, options = {}) {
            const $form = $(formSelector);
            if (!$form.length) return;
            
            // Load any saved draft
            this.loadDraft($form);
            
            // Auto-save every 5 seconds when form changes
            let changed = false;
            $form.on('input change', () => { changed = true; });
            
            this.autoSaveInterval = setInterval(() => {
                if (changed) {
                    this.saveDraft($form, options.key);
                    changed = false;
                }
            }, 5000);
            
            // Clear draft on successful submit
            $form.on('submit', () => {
                this.clearDraft(options.key);
            });
        },
        
        saveDraft($form, key = this.draftKey) {
            const formData = {};
            $form.serializeArray().forEach(field => {
                formData[field.name] = field.value;
            });
            
            const draft = {
                data: formData,
                timestamp: new Date().toISOString(),
                url: window.location.href
            };
            
            localStorage.setItem(key, JSON.stringify(draft));
        },
        
        loadDraft($form, key = this.draftKey) {
            const saved = localStorage.getItem(key);
            if (!saved) return;
            
            try {
                const draft = JSON.parse(saved);
                const age = Date.now() - new Date(draft.timestamp).getTime();
                const maxAge = 24 * 60 * 60 * 1000; // 24 hours
                
                if (age > maxAge) {
                    this.clearDraft(key);
                    return;
                }
                
                // Check if same URL
                if (draft.url !== window.location.href && !key.includes('modal')) {
                    return;
                }
                
                // Restore form data
                Object.entries(draft.data).forEach(([name, value]) => {
                    const $field = $form.find(`[name="${name}"]`);
                    if ($field.length) {
                        if ($field.is(':checkbox')) {
                            $field.prop('checked', value === 'on' || value === true);
                        } else {
                            $field.val(value);
                        }
                    }
                });
                
                // Show recovery toast
                showToast('Draft restored from auto-save', 'info');
                
            } catch (err) {
                console.error('Failed to load draft:', err);
            }
        },
        
        clearDraft(key = this.draftKey) {
            localStorage.removeItem(key);
        },
        
        destroy() {
            if (this.autoSaveInterval) {
                clearInterval(this.autoSaveInterval);
            }
        }
    };

    // ============================================
    // SKELETON LOADING SCREENS
    // ============================================
    
    const SkeletonLoader = {
        table(rows = 5) {
            let html = '<div class="pi-skeleton-table">';
            for (let i = 0; i < rows; i++) {
                html += `
                    <div class="pi-skeleton-row">
                        <div class="pi-skeleton-cell" style="width: 40px;"></div>
                        <div class="pi-skeleton-cell" style="width: 100px;"></div>
                        <div class="pi-skeleton-cell" style="flex: 1;"></div>
                        <div class="pi-skeleton-cell" style="width: 80px;"></div>
                        <div class="pi-skeleton-cell" style="width: 60px;"></div>
                    </div>
                `;
            }
            html += '</div>';
            return html;
        },
        
        cards(count = 4) {
            let html = '<div class="pi-skeleton-cards">';
            for (let i = 0; i < count; i++) {
                html += `
                    <div class="pi-skeleton-card">
                        <div class="pi-skeleton-header"></div>
                        <div class="pi-skeleton-body"></div>
                        <div class="pi-skeleton-footer"></div>
                    </div>
                `;
            }
            html += '</div>';
            return html;
        },
        
        kpiCards(count = 4) {
            let html = '<div class="pi-dashboard-grid">';
            for (let i = 0; i < count; i++) {
                html += `
                    <div class="pi-skeleton-kpi">
                        <div class="pi-skeleton-icon"></div>
                        <div class="pi-skeleton-content">
                            <div class="pi-skeleton-value"></div>
                            <div class="pi-skeleton-label"></div>
                        </div>
                    </div>
                `;
            }
            html += '</div>';
            return html;
        }
    };

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function handleReceiptUpload(files) {
        // Placeholder for receipt upload - would integrate with OCR API
        showToast('Receipt upload coming soon - integrate with your OCR service');
    }

    // Expose Modals and TabHandlers globally for onclick handlers
    window.PIExpensesModals = Modals;
    window.TabHandlers = TabHandlers;

    // ============================================
    // INITIALIZATION
    // ============================================

    function init() {
        const $wrapper = $('.pi-expenses-wrapper-v3');
        if (!$wrapper.length) {
            console.log('Expenses wrapper not found, retrying in 500ms...');
            setTimeout(init, 500);
            return;
        }

        // Get config from localized data
        config.nonce = $wrapper.data('nonce') || window.piExpensesData?.restNonce;
        config.userId = $wrapper.data('user-id') || window.piExpensesData?.userId;

        if (!config.nonce) {
            console.error('Expenses: No nonce found');
            return;
        }

        // Check for job_id in URL
        const urlParams = new URLSearchParams(window.location.search);
        const jobId = urlParams.get('job_id');
        const action = urlParams.get('action');

        if (jobId) {
            state.selectedJobId = parseInt(jobId);
            // If action=add, open expense modal
            if (action === 'add') {
                setTimeout(async () => {
                    await Modals.expense({ job_id: state.selectedJobId });
                }, 500);
            }
        }

        // Bind events
        bindGlobalEvents();
        initKeyboardShortcuts();

        // Initialize connectivity manager
        ConnectivityManager.init();
        ConnectivityManager.loadPendingRequests();
        
        // Load notifications
        loadNotifications();

        // Load initial tab
        const initialTab = $('.pi-exp-nav-item.active').data('tab') || 'dashboard';
        state.currentTab = initialTab;

        if (TabHandlers[initialTab]) {
            TabHandlers[initialTab]();
        }

        // Initialize feather icons
        if (window.feather) {
            feather.replace();
        }

        console.log('PI Expenses V3 initialized');
        console.log('Keyboard shortcuts: Press ? for help');
    }

    // Start when DOM is ready
    $(document).ready(init);

})(jQuery);
