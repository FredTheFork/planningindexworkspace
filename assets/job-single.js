  /**
   * Planning Index - Single Job Page
   * Load job data, update status/dates/notes/client, activity timeline.
   * V2 - Integrated Job Cost Management
   */
  jQuery(($) => {
    'use strict';

    const jobId = $('#pi-job-single').data('job-id');
    if (!jobId) return;

    const endpoint = '/wp-json/pi/v1/jobs/' + jobId;
    const nonce = PI_Job?.nonce || '';
    let currentData = null;
    let costsData = null;
    let vehicles = [];
    let suppliers = [];

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function formatDate(dateStr) {
      if (!dateStr) return '—';
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function formatDateInput(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return '';
      return d.toISOString().split('T')[0];
    }

    function formatCurrency(amount) {
      if (amount === null || amount === undefined || isNaN(amount)) return '£0.00';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 2
      }).format(amount);
    }

    function showToast(message, type) {
      type = type || 'success';
      const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
      };
      const toast = $(`
        <div class="pi-toast ${type}">
          <span class="pi-toast-icon">${icons[type]}</span>
          <span class="pi-toast-message">${escapeHtml(message)}</span>
          <button class="pi-toast-close" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      `);
      $('#pi-toast-container').append(toast);
      toast.find('.pi-toast-close').on('click', function() {
        toast.fadeOut(200, function() { toast.remove(); });
      });
      setTimeout(function() {
        toast.fadeOut(200, function() { toast.remove(); });
      }, 5000);
    }

    // ============================================
    // API CLIENT
    // ============================================

    const API = {
      async request(endpoint, options = {}) {
        const url = '/wp-json/pi/v1' + endpoint;
        const defaults = {
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json'
          }
        };
        try {
          const response = await fetch(url, { ...defaults, ...options });
          if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `HTTP ${response.status}`);
          }
          return await response.json();
        } catch (err) {
          console.error('API Error:', err);
          throw err;
        }
      },

      // Job costing data
      getJobCosting(jobId) {
        return this.request(`/expenses/job-costing?job_id=${jobId}`);
      },

      // Get expenses for job
      getJobExpenses(jobId, params = {}) {
        const query = new URLSearchParams({ job_id: jobId, ...params }).toString();
        return this.request(`/expenses/db?${query}`);
      },

      // Get mileage for job
      getJobMileage(jobId) {
        return this.request(`/expenses/mileage?job_id=${jobId}`);
      },

      // Create expense
      createExpense(data) {
        return this.request('/expenses/create', {
          method: 'POST',
          body: JSON.stringify(data)
        });
      },

      // Update expense
      updateExpense(id, data) {
        return this.request(`/expenses/${id}`, {
          method: 'PUT',
          body: JSON.stringify(data)
        });
      },

      // Delete expense
      deleteExpense(id) {
        return this.request(`/expenses/${id}`, {
          method: 'DELETE'
        });
      },

      // Create mileage trip
      createMileage(data) {
        return this.request('/expenses/mileage', {
          method: 'POST',
          body: JSON.stringify(data)
        });
      },

      // Get vehicles
      getVehicles() {
        return this.request('/expenses/vehicles');
      },

      // Get suppliers
      getSuppliers() {
        return this.request('/expenses/suppliers');
      },

      // Log activity to job timeline
      async logActivity(jobId, message, type = 'general') {
        try {
          const result = await this.request(`/jobs/${jobId}/activity`, {
            method: 'POST',
            body: JSON.stringify({ message, type })
          });
          console.log('[Activity] Logged:', { jobId, message, type, result });
          return result;
        } catch (err) {
          console.error('[Activity] Failed to log:', { jobId, message, type, error: err.message });
          throw err;
        }
      }
    };

    async function loadJob() {
      try {
        // Add cache-busting to ensure fresh data
        const cacheBuster = '?_t=' + Date.now();
        const resp = await fetch(endpoint + cacheBuster, { headers: { 'X-WP-Nonce': nonce } });
        if (!resp.ok) throw new Error('Failed to load job');
        const data = await resp.json();
        currentData = data;

        $('#pi-job-code').text(data.code || 'Job');
        $('#pi-job-address').text(data.site_address || 'No address');
        $('#pi-map-link').attr('href', 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(data.site_address || ''));

        let dates = '';
        if (data.start_date) dates += 'Start: ' + formatDate(data.start_date);
        if (data.end_date) dates += (dates ? ' · ' : '') + 'Finish: ' + formatDate(data.end_date);
        $('#pi-job-dates').text(dates || '—');

        $('#pi-job-progress').text((data.progress || 0) + '%');
        $('#pi-job-start').text(formatDate(data.start_date));
        $('#pi-job-end').text(formatDate(data.end_date));

        const workers = data.assigned_workers || [];
        $('#pi-job-crew').text(workers.length ? workers.length + ' assigned' : '—');

        $('#job-status').val(data.status || 'planning');
        $('.job-customer-name').val(data.customer_name || '');
        $('.job-site-address').val(data.site_address || '');

        // Overview: description, council ref
        let overview = '';
        if (data.council_reference) overview += '<p><strong>Council ref:</strong> ' + escapeHtml(data.council_reference) + '</p>';
        if (data.date_received) overview += '<p><strong>Received:</strong> ' + formatDate(data.date_received) + '</p>';
        if (data.description) overview += '<p>' + escapeHtml(data.description).replace(/\n/g, '<br>') + '</p>';
        $('#pi-job-overview').html(overview || '<p>No details.</p>');

        // Activity timeline
        const activity = data.activity || [];
        console.log('[Activity] Loaded', activity.length, 'entries:', activity);
        const $timeline = $('#pi-job-activity');
        $timeline.empty();
        if (activity.length === 0) {
          $timeline.html('<div class="pi-empty-state"><p>No activity yet</p></div>');
        } else {
          // Show latest 10 by default, with Show more button
          const SHOW_LIMIT = 10;
          const reversedActivity = activity.slice().reverse();
          const visibleActivity = reversedActivity.slice(0, SHOW_LIMIT);
          const hasMore = reversedActivity.length > SHOW_LIMIT;

          visibleActivity.forEach(entry => {
            const str = typeof entry === 'string' ? entry : (entry.text || '');
            const parts = str.split(/:\s/);
            const time = parts.shift() || '';
            const text = parts.join(': ') || str;
            $timeline.append(`<div class="pi-timeline-item"><div class="pi-timeline-dot"></div><div class="pi-timeline-content"><span class="pi-timeline-meta">${escapeHtml(time)}</span> ${escapeHtml(text)}</div></div>`);
          });

          // Add Show more button if there are more entries
          if (hasMore) {
            const remainingCount = reversedActivity.length - SHOW_LIMIT;
            $timeline.append(`
              <div class="pi-show-more-activity" style="text-align: center; padding: 12px 0; border-top: 1px solid #e5e7eb; margin-top: 8px;">
                <button id="show-more-activity-btn" style="padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #374151; font-size: 0.875rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                  <span>Show ${remainingCount} more</span>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
              </div>
              <div id="hidden-activity-entries" style="display: none;">
                ${reversedActivity.slice(SHOW_LIMIT).map(entry => {
                  const str = typeof entry === 'string' ? entry : (entry.text || '');
                  const parts = str.split(/:\s/);
                  const time = parts.shift() || '';
                  const text = parts.join(': ') || str;
                  return `<div class="pi-timeline-item"><div class="pi-timeline-dot"></div><div class="pi-timeline-content"><span class="pi-timeline-meta">${escapeHtml(time)}</span> ${escapeHtml(text)}</div></div>`;
                }).join('')}
              </div>
            `);
          }
        }

        loadScheduleEvents();
      } catch (err) {
        console.error(err);
        showToast('Failed to load job', 'error');
      }
    }

    // Load/refresh schedule events for this job - uses FullCalendar now
    function loadScheduleEvents() {
      // The new JobCalendar handles this automatically
      // Just refresh the calendar if it exists
      if (window.JobCalendar && window.JobCalendar.refresh) {
        window.JobCalendar.refresh();
      }
    }

    $('#job-update-status').on('click', async function() {
      const status = $('#job-status').val();
      try {
        const resp = await fetch(endpoint + '/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ status })
        });
        if (!resp.ok) throw new Error('Update failed');
        showToast('Status updated');
        loadJob();
      } catch (err) {
        showToast('Failed to update status', 'error');
      }
    });

    $('#pi-job-notes-form').on('submit', async function(e) {
      e.preventDefault();
      const notes = $(this).find('textarea').val();
      try {
        const resp = await fetch(endpoint + '/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ notes })
        });
        if (!resp.ok) throw new Error('Update failed');
        showToast('Notes saved');
        loadJob();
      } catch (err) {
        showToast('Failed to save notes', 'error');
      }
    });

    $('#pi-job-client-form').on('submit', async function(e) {
      e.preventDefault();
      const customer_name = $(this).find('.job-customer-name').val();
      const site_address = $(this).find('.job-site-address').val();
      try {
        const resp = await fetch(endpoint + '/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ customer_name, site_address })
        });
        if (!resp.ok) throw new Error('Update failed');
        showToast('Client & site saved');
        currentData = null;
        loadJob();
      } catch (err) {
        showToast('Failed to save', 'error');
      }
    });

    // ============================================
    // JOB COSTS TAB - COMPREHENSIVE EXPENSE MANAGEMENT
    // ============================================

    // Categories for dropdowns
    const categories = {
      materials: { label: 'Materials', color: '#10b981' },
      tools: { label: 'Tools & Equipment', color: '#3b82f6' },
      fuel: { label: 'Fuel & Energy', color: '#f59e0b' },
      subcontractors: { label: 'Subcontractors', color: '#8b5cf6' },
      waste: { label: 'Waste & Disposal', color: '#ef4444' },
      equipment_rental: { label: 'Equipment Rental', color: '#06b6d4' },
      ppe: { label: 'PPE & Workwear', color: '#ec4899' },
      permits: { label: 'Permits & Licenses', color: '#f97316' },
      insurance: { label: 'Insurance', color: '#84cc16' },
      overhead: { label: 'Overhead & Admin', color: '#64748b' },
      other: { label: 'Other', color: '#94a3b8' }
    };

    const paymentMethods = {
      company_card: 'Company Card',
      personal_card: 'Personal Card',
      bank_transfer: 'Bank Transfer',
      cash: 'Cash',
      cheque: 'Cheque',
      paypal: 'PayPal',
      credit_account: 'Supplier Credit',
      direct_debit: 'Direct Debit'
    };

    // ============================================
    // MODALS
    // ============================================

    const Modals = {
      close() {
        $('#pi-modal-overlay').remove();
        $('body').removeClass('pi-modal-open');
        $(document).off('keydown.pi-modal').off('click.pi-modal-dismiss');
      },

      open(html, options = {}) {
        $('#pi-modal-overlay').remove();
        $(document).off('keydown.pi-modal').off('click.pi-modal-dismiss');

        const modalHtml = `
          <div class="pi-modal-overlay" id="pi-modal-overlay" style="
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.6); display: flex;
            align-items: center; justify-content: center;
            z-index: 999999; padding: 20px; backdrop-filter: blur(4px);
          ">
            <div class="pi-modal-container" style="
              background: #ffffff; border-radius: 12px; width: 100%;
              max-width: ${options.size === 'large' ? '700px' : options.size === 'medium' ? '600px' : '500px'};
              max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            ">
              <div class="pi-modal-header" style="
                display: flex; justify-content: space-between; align-items: center;
                padding: 1.25rem; border-bottom: 1px solid #e2e8f0;
              ">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a; margin: 0;">${escapeHtml(options.title || 'Modal')}</h3>
                <button class="pi-modal-close" id="modal-close-btn" type="button" style="
                  width: 32px; height: 32px; border: none; background: #f1f5f9;
                  border-radius: 8px; font-size: 1.25rem; color: #94a3b8;
                  cursor: pointer; display: flex; align-items: center; justify-content: center;
                ">&times;</button>
              </div>
              <div class="pi-modal-body" style="padding: 1.5rem;">${html}</div>
              ${options.footer ? `<div class="pi-modal-footer" style="
                display: flex; justify-content: flex-end; gap: 0.75rem;
                padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc;
              ">${options.footer}</div>` : ''}
            </div>
          </div>
        `;

        $('body').append(modalHtml).addClass('pi-modal-open');

        $('#modal-close-btn').on('click', () => this.close());
        $(document).on('click.pi-modal-dismiss', '[data-dismiss="modal"]', () => this.close());
        $('#pi-modal-overlay').on('click', (e) => { if (e.target.id === 'pi-modal-overlay') this.close(); });
        $(document).on('keydown.pi-modal', (e) => { if (e.key === 'Escape') this.close(); });
      },

      // Expense Modal (job_id pre-filled, no job dropdown)
      async expense(expense = null) {
        const isEdit = !!(expense && expense.id);
        const categoryOptions = Object.entries(categories).map(([key, cat]) =>
          `<option value="${key}" ${expense?.category === key ? 'selected' : ''}>${escapeHtml(cat.label)}</option>`
        ).join('');
        const paymentOptions = Object.entries(paymentMethods).map(([key, label]) =>
          `<option value="${key}" ${expense?.payment_method === key ? 'selected' : ''}>${escapeHtml(label)}</option>`
        ).join('');

        // Load suppliers for dropdown
        if (!suppliers.length) {
          try {
            suppliers = await API.getSuppliers();
            console.log('Loaded', suppliers.length, 'suppliers for dropdown');
          } catch (e) {
            console.error('Failed to load suppliers:', e);
            suppliers = [];
          }
        }
        const supplierOptions = suppliers.map(s => {
          const displayName = s.name || s.company_name || 'Unknown';
          return `<option value="${escapeHtml(displayName)}">`;
        }).join('');
        console.log('Generated', suppliers.length, 'supplier options');

        const html = `
          <form id="job-expense-form">
            <input type="hidden" name="job_id" value="${jobId}">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Date *</label>
                <input type="date" name="expense_date" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                  value="${formatDateInput(expense?.expense_date || new Date())}" required>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Amount *</label>
                <input type="number" name="amount" class="pi-form-control" step="0.01" min="0" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                  value="${expense?.amount || ''}" placeholder="0.00" required>
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Category *</label>
                <select name="category" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;" required>
                  <option value="">Select category...</option>
                  ${categoryOptions}
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Payment Method</label>
                <select name="payment_method" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                  ${paymentOptions}
                </select>
              </div>
            </div>
            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Description *</label>
              <input type="text" name="description" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                value="${escapeHtml(expense?.description || '')}" placeholder="What was this expense for?" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Supplier</label>
                <input type="text" name="supplier_name" class="pi-form-control" list="job-suppliers-list" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                  value="${escapeHtml(expense?.supplier_name || '')}" placeholder="Supplier name">
                <datalist id="job-suppliers-list">${supplierOptions}</datalist>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Status</label>
                <select name="approval_status" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                  <option value="approved" ${expense?.approval_status === 'approved' ? 'selected' : ''}>Approved</option>
                  <option value="pending" ${expense?.approval_status === 'pending' ? 'selected' : ''}>Pending</option>
                  <option value="draft" ${expense?.approval_status === 'draft' ? 'selected' : ''}>Draft</option>
                </select>
              </div>
            </div>
            <div style="margin-bottom: 1rem;">
              <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer;">
                <input type="checkbox" name="cis_liable" value="1" ${expense?.cis_liable ? 'checked' : ''}>
                CIS Liable (Subcontractor)
              </label>
            </div>
            <div id="cis-rate-group" style="display: ${expense?.cis_liable ? 'block' : 'none'};">
              <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">CIS Rate (%)</label>
              <select name="cis_rate" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                <option value="20" ${expense?.cis_rate == 20 ? 'selected' : ''}>Registered - 20%</option>
                <option value="30" ${expense?.cis_rate == 30 ? 'selected' : ''}>Unregistered - 30%</option>
                <option value="0" ${expense?.cis_rate === 0 ? 'selected' : ''}>Gross - 0%</option>
              </select>
            </div>
          </form>
        `;

        const footer = `
          <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="pi-btn pi-btn-primary" id="save-job-expense-btn">${isEdit ? 'Update' : 'Save'} Expense</button>
        `;

        this.open(html, { title: isEdit ? 'Edit Expense' : 'Add Expense', footer, size: 'large' });

        $('[name="cis_liable"]').on('change', function() {
          $('#cis-rate-group').toggle(this.checked);
        });

        $('#save-job-expense-btn').on('click', async () => {
          const $form = $('#job-expense-form');
          if (!$form[0].checkValidity()) { $form[0].reportValidity(); return; }

          const data = {
            expense_date: $form.find('[name="expense_date"]').val(),
            amount: parseFloat($form.find('[name="amount"]').val()),
            category: $form.find('[name="category"]').val(),
            description: $form.find('[name="description"]').val(),
            supplier_name: $form.find('[name="supplier_name"]').val(),
            job_id: jobId,
            payment_method: $form.find('[name="payment_method"]').val(),
            approval_status: $form.find('[name="approval_status"]').val(),
            cis_liable: $form.find('[name="cis_liable"]').is(':checked') ? 1 : 0,
            cis_rate: $form.find('[name="cis_rate"]').val() || null
          };

          try {
            if (isEdit) {
              await API.updateExpense(expense.id, data);
              showToast('Expense updated successfully');
              try { await API.logActivity(jobId, `Updated expense: ${data.description} - ${formatCurrency(data.amount)}`, 'expenses'); } catch(e) { console.error('[Activity] Failed:', e); }
            } else {
              await API.createExpense(data);
              showToast('Expense created successfully');
              try { await API.logActivity(jobId, `Added expense: ${data.description} - ${formatCurrency(data.amount)}`, 'expenses'); } catch(e) { console.error('[Activity] Failed:', e); }
            }
            this.close();
            await loadJobCosts();
            await new Promise(r => setTimeout(r, 300));
            await loadJob(); // Refresh activity timeline
          } catch (err) {
            showToast(err.message || 'Failed to save expense', 'error');
          }
        });
      },

      // Mileage Modal (job_id pre-filled)
      async mileage(trip = null) {
        const isEdit = !!(trip && trip.id);

        // Load vehicles if not loaded
        if (!vehicles.length) {
          try { vehicles = await API.getVehicles(); } catch (e) { vehicles = []; }
        }

        if (!vehicles.length) {
          showToast('Please add a vehicle first in the Expenses page', 'error');
          return;
        }

        const vehicleOptions = vehicles.map(v =>
          `<option value="${v.id}" ${trip?.vehicle_id === v.id ? 'selected' : ''}>${escapeHtml(v.make)} ${escapeHtml(v.model)} (${escapeHtml(v.registration)})</option>`
        ).join('');

        // Pre-fill to address from job site if available
        const toAddress = trip?.to_address || currentData?.site_address || '';

        const html = `
          <form id="job-mileage-form">
            <input type="hidden" name="job_id" value="${jobId}">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Date *</label>
                <input type="date" name="trip_date" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                  value="${formatDateInput(trip?.trip_date || new Date())}" required>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Vehicle *</label>
                <select name="vehicle_id" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;" required>
                  <option value="">Select vehicle...</option>
                  ${vehicleOptions}
                </select>
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">From *</label>
                <input type="text" name="from_address" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                  value="${escapeHtml(trip?.from_address || 'Office')}" required>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">To *</label>
                <input type="text" name="to_address" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                  value="${escapeHtml(toAddress)}" required>
              </div>
            </div>
            <div style="margin-bottom: 1rem;">
              <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Purpose</label>
              <input type="text" name="purpose" class="pi-form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                value="${escapeHtml(trip?.purpose || '')}" placeholder="Site visit, material delivery, etc.">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Miles *</label>
                <input type="number" name="miles" class="pi-form-control" step="0.1" min="0" style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 8px;"
                  value="${trip?.miles || ''}" required>
              </div>
              <div style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer;">
                  <input type="checkbox" name="is_return" value="1">
                  Return trip (double miles)
                </label>
              </div>
            </div>
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; font-size: 0.875rem; color: #64748b;">
              <strong>Rate:</strong> £0.70/mile (HMRC approved rate)<br>
              <strong>Estimated claim:</strong> <span id="mileage-estimate">£0.00</span>
            </div>
          </form>
        `;

        const footer = `
          <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="pi-btn pi-btn-primary" id="save-job-mileage-btn">Log Trip</button>
        `;

        this.open(html, { title: 'Log Mileage Trip', footer, size: 'medium' });

        // Calculate estimate
        function updateEstimate() {
          let miles = parseFloat($('[name="miles"]').val()) || 0;
          if ($('[name="is_return"]').is(':checked')) miles *= 2;
          $('#mileage-estimate').text(formatCurrency(miles * 0.70));
        }
        $('[name="miles"], [name="is_return"]').on('input change', updateEstimate);

        $('#save-job-mileage-btn').on('click', async () => {
          const $form = $('#job-mileage-form');
          if (!$form[0].checkValidity()) { $form[0].reportValidity(); return; }

          let miles = parseFloat($form.find('[name="miles"]').val());
          if ($form.find('[name="is_return"]').is(':checked')) miles *= 2;

          const data = {
            trip_date: $form.find('[name="trip_date"]').val(),
            vehicle_id: parseInt($form.find('[name="vehicle_id"]').val()),
            from_address: $form.find('[name="from_address"]').val(),
            to_address: $form.find('[name="to_address"]').val(),
            purpose: $form.find('[name="purpose"]').val(),
            miles: miles,
            job_id: jobId,
            rate: 0.70,
            claim_amount: miles * 0.70
          };

          try {
            await API.createMileage(data);
            showToast('Trip logged successfully');
            try { await API.logActivity(jobId, `Logged mileage: ${data.miles} miles from ${data.from_address} to ${data.to_address} - ${formatCurrency(data.claim_amount)}`, 'expenses'); } catch(e) { console.error('[Activity] Failed:', e); }
            this.close();
            await loadJobCosts();
            await new Promise(r => setTimeout(r, 300));
            await loadJob(); // Refresh activity timeline
          } catch (err) {
            showToast(err.message || 'Failed to log trip', 'error');
          }
        });
      },

      confirmDelete(itemType, itemName, onConfirm) {
        const html = `
          <p style="margin-bottom: 1rem;">Are you sure you want to delete this ${escapeHtml(itemType)}?</p>
          <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">${escapeHtml(itemName)}</p>
          <p style="color: #dc2626; font-size: 0.875rem;">This action cannot be undone.</p>
        `;
        const footer = `
          <button type="button" class="pi-btn pi-btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="pi-btn pi-btn-danger" id="confirm-delete-btn">Delete</button>
        `;
        this.open(html, { title: 'Confirm Delete', footer, size: 'small' });
        $('#confirm-delete-btn').on('click', () => { onConfirm(); this.close(); });
      }
    };

    // ============================================
    // LOAD AND RENDER JOB COSTS
    // ============================================

    async function loadJobCosts() {
      if (!$('#job-tab-costs').length) return;

      try {
        // Load all data in parallel
        const [costing, mileageData, vehiclesData, suppliersData] = await Promise.all([
          API.getJobCosting(jobId).catch(() => null),
          API.getJobMileage(jobId).catch(() => ({ trips: [] })),
          API.getVehicles().catch(() => []),
          API.getSuppliers().catch(() => [])
        ]);

        vehicles = vehiclesData || [];
        suppliers = suppliersData || [];
        costsData = costing;

        if (costing) {
          renderCostsTab(costing, mileageData);
        } else {
          renderEmptyCostsState();
        }
      } catch (err) {
        console.error('Failed to load job costs:', err);
        renderEmptyCostsState();
      }
    }

    function renderCostsTab(data, mileageData) {
      const { expenses, total_expenses, budget, quote_value, pending_costs, cost_codes } = data;
      const trips = mileageData?.trips || [];
      const totalMiles = trips.reduce((sum, t) => sum + (parseFloat(t.miles) || 0), 0);
      const totalClaim = trips.reduce((sum, t) => sum + (parseFloat(t.claim_amount) || 0), 0);

      // Calculate profit margin
      const profitMargin = quote_value > 0 ? ((quote_value - total_expenses) / quote_value * 100) : 0;
      const budgetUsedPercent = budget > 0 ? (total_expenses / budget * 100) : 0;

      // Update breadcrumb
      $('.pi-breadcrumb-job-name').text(currentData?.code || 'Job');

      // Update KPI cards
      $('#pi-job-total-expenses').text(formatCurrency(total_expenses));
      $('#pi-job-budget-status').text(budget > 0 ? `${budgetUsedPercent.toFixed(1)}% used` : 'No Budget');
      $('#pi-job-quote-value').text(formatCurrency(quote_value));
      $('#pi-job-pending-costs').text(formatCurrency(pending_costs || 0));

      // Show/hide pending card
      $('#pi-pending-card').toggle((pending_costs || 0) > 0);

      // Update budget progress
      if (budget > 0) {
        $('#pi-budget-container').show();
        $('#pi-budget-percent').text(`${budgetUsedPercent.toFixed(1)}%`);
        $('#pi-budget-spent').text(formatCurrency(total_expenses));
        $('#pi-budget-total').text(formatCurrency(budget));
        const $fill = $('#pi-budget-progress-fill').css('width', `${Math.min(budgetUsedPercent, 100)}%`);
        $fill.removeClass('pi-warning pi-danger');
        if (budgetUsedPercent > 95) $fill.addClass('pi-danger');
        else if (budgetUsedPercent > 80) $fill.addClass('pi-warning');

        // Profit margin badge
        const marginHtml = profitMargin >= 20
          ? `<span class="pi-profit-margin" style="background: #dcfce7; color: #166534;">${profitMargin.toFixed(1)}% margin</span>`
          : profitMargin >= 10
          ? `<span class="pi-profit-margin" style="background: #fef3c7; color: #92400e;">${profitMargin.toFixed(1)}% margin</span>`
          : `<span class="pi-profit-margin" style="background: #fee2e2; color: #dc2626;">${profitMargin.toFixed(1)}% margin</span>`;
        $('#pi-profit-margin').html(marginHtml);
      } else {
        $('#pi-budget-container').hide();
      }

      // Render expenses table
      renderExpensesTable(expenses || []);

      // Render mileage section
      $('#pi-mileage-total').text(`${totalMiles.toFixed(1)} miles`);
      $('#pi-mileage-claim').text(formatCurrency(totalClaim));
      renderMileageTable(trips);

      // Render cost codes
      renderCostCodes(cost_codes || [], total_expenses);

      // Populate category filter
      const categoryFilter = $('#pi-expense-category').empty().append('<option value="">All Categories</option>');
      Object.entries(categories).forEach(([key, cat]) => {
        categoryFilter.append(`<option value="${key}">${escapeHtml(cat.label)}</option>`);
      });
    }

    function renderExpensesTable(expenses) {
      const $container = $('#pi-job-expenses-container');

      if (!expenses || expenses.length === 0) {
        $container.html(`
          <div class="pi-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <p>No expenses recorded for this job.</p>
          </div>
        `);
        return;
      }

      let html = `
        <table class="pi-job-costs-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Category</th>
              <th>Description</th>
              <th>Supplier</th>
              <th class="pi-text-right">Amount</th>
              <th>Status</th>
              <th width="80">Actions</th>
            </tr>
          </thead>
          <tbody>
      `;

      expenses.forEach(expense => {
        const catKey = expense.category || 'other';
        const catClass = `pi-cat-${catKey}`;
        const catLabel = categories[catKey]?.label || expense.category || 'Other';
        const statusClass = `pi-status-${expense.approval_status || 'draft'}`;

        html += `
          <tr data-id="${expense.id}">
            <td>${formatDate(expense.expense_date)}</td>
            <td><span class="pi-cat-badge ${catClass}">${escapeHtml(catLabel)}</span></td>
            <td>${escapeHtml(expense.description || '-').substring(0, 50)}${(expense.description?.length || 0) > 50 ? '...' : ''}</td>
            <td>${escapeHtml(expense.supplier_name || '-')}</td>
            <td class="pi-text-right">${formatCurrency(expense.amount)}</td>
            <td>
              <select class="pi-status-badge ${statusClass} pi-status-select" data-expense-id="${expense.id}" style="border: none; cursor: pointer;">
                <option value="approved" ${expense.approval_status === 'approved' ? 'selected' : ''}>Approved</option>
                <option value="pending" ${expense.approval_status === 'pending' ? 'selected' : ''}>Pending</option>
                <option value="draft" ${expense.approval_status === 'draft' ? 'selected' : ''}>Draft</option>
              </select>
            </td>
            <td>
              <div style="display: flex; gap: 4px;">
                <button class="pi-action-btn edit-expense" title="Edit" data-id="${expense.id}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="pi-action-btn delete-expense" title="Delete" data-id="${expense.id}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        `;
      });

      html += '</tbody></table>';
      $container.html(html);
    }

    function renderMileageTable(trips) {
      const $container = $('#pi-job-mileage-container');

      if (!trips || trips.length === 0) {
        $container.html(`
          <div class="pi-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            <p>No trips logged for this job.</p>
          </div>
        `);
        return;
      }

      let html = `
        <table class="pi-job-costs-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Route</th>
              <th>Miles</th>
              <th class="pi-text-right">Claim</th>
              <th>Purpose</th>
            </tr>
          </thead>
          <tbody>
      `;

      trips.forEach(trip => {
        html += `
          <tr>
            <td>${formatDate(trip.trip_date)}</td>
            <td>${escapeHtml(trip.from_address || 'Office')} → ${escapeHtml(trip.to_address)}</td>
            <td>${parseFloat(trip.miles).toFixed(1)}</td>
            <td class="pi-text-right">${formatCurrency(trip.claim_amount)}</td>
            <td>${escapeHtml(trip.purpose || '-')}</td>
          </tr>
        `;
      });

      html += '</tbody></table>';
      $container.html(html);
    }

    function renderCostCodes(costCodes, totalExpenses) {
      const $container = $('#pi-cost-codes-container');

      if (!costCodes || costCodes.length === 0) {
        $container.html(`
          <div class="pi-empty-state" style="padding: 20px;">
            <p style="font-size: 14px; color: #64748b;">No category breakdown available.</p>
          </div>
        `);
        return;
      }

      let html = '';
      costCodes.forEach(cc => {
        const percent = totalExpenses > 0 ? (cc.total / totalExpenses * 100) : 0;
        html += `
          <div class="pi-cost-code-item">
            <div class="pi-cost-code-header">
              <span class="pi-cost-code-name">${escapeHtml(cc.category || 'Other')}</span>
              <span class="pi-cost-code-amount">${formatCurrency(cc.total)} (${percent.toFixed(1)}%)</span>
            </div>
            <div class="pi-cost-code-bar">
              <div class="pi-cost-code-fill" style="width: ${Math.min(percent, 100)}%"></div>
            </div>
          </div>
        `;
      });

      $container.html(html);
    }

    function renderEmptyCostsState() {
      $('#pi-job-total-expenses').text(formatCurrency(0));
      $('#pi-job-budget-status').text('No Budget');
      $('#pi-job-quote-value').text(formatCurrency(0));
      $('#pi-job-pending-costs').text(formatCurrency(0));
      $('#pi-budget-container').hide();
      $('#pi-pending-card').hide();
      $('#pi-mileage-total').text('0 miles');
      $('#pi-mileage-claim').text(formatCurrency(0));
      $('#pi-job-expenses-container').html('<div class="pi-loading-state">Unable to load expenses data.</div>');
      $('#pi-job-mileage-container').html('<div class="pi-loading-state">Unable to load mileage data.</div>');
      $('#pi-cost-codes-container').html('<div class="pi-loading-state">Unable to load breakdown.</div>');
    }

    // ============================================
    // MATERIALS TAB FUNCTIONALITY
    // ============================================

    let materialsData = null;
    let materialsState = {
      currentSubtab: 'overview',
      bomItems: [],
      requisitions: [],
      purchaseOrders: [],
      deliveries: [],
      wasteNotes: [],
      stockAllocations: [],
      suppliers: [],
      purchasingTab: 'requisitions'
    };

    // Materials API Client
    const MaterialsAPI = {
      baseUrl: '/wp-json/pi-materials/v1',

      async request(endpoint, options = {}) {
        const url = this.baseUrl + '/' + endpoint;
        const config = {
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
          },
          ...options
        };
        if (config.body && typeof config.body === 'object') {
          config.body = JSON.stringify(config.body);
        }

        try {
          const response = await fetch(url, config);
          const data = await response.json().catch(() => ({ message: 'Invalid JSON response' }));
          if (!response.ok) {
            const errorMsg = data.error || data.message || `HTTP ${response.status} Error`;
            console.error(`Materials API Error (${endpoint}):`, errorMsg, data);
            throw new Error(errorMsg);
          }
          return data;
        } catch (error) {
          console.error(`Materials API Error (${endpoint}):`, error);
          throw error;
        }
      },

      // Helper to extract array from response (handles both [] and {data: []})
      extractArray(response) {
        if (Array.isArray(response)) return response;
        if (response && Array.isArray(response.data)) return response.data;
        if (response && Array.isArray(response.results)) return response.results;
        if (response && Array.isArray(response.waste_notes)) return response.waste_notes;
        if (response && Array.isArray(response.movements)) return response.movements;
        return [];
      },

      // BOM - Uses bom_items endpoint with job_id filter
      async getJobBOM() {
        const response = await this.request(`bom-items?job_id=${jobId}&per_page=100`);
        return this.extractArray(response);
      },
      async createBOMItem(data) {
        return await this.request('bom-items', { method: 'POST', body: { ...data, job_id: jobId } });
      },
      async updateBOMItem(id, data) {
        return await this.request(`bom-items/${id}`, { method: 'PUT', body: data });
      },
      async deleteBOMItem(id) {
        return await this.request(`bom-items/${id}`, { method: 'DELETE' });
      },

      // Purchasing
      async getRequisitions() {
        const response = await this.request(`requisitions?job_id=${jobId}`);
        return this.extractArray(response);
      },
      async getPurchaseOrders() {
        const response = await this.request(`purchase-orders?job_id=${jobId}`);
        return this.extractArray(response);
      },
      async getDeliveries() {
        const response = await this.request(`deliveries?job_id=${jobId}`);
        return this.extractArray(response);
      },
      async createRequisition(data) {
        return await this.request('requisitions', { method: 'POST', body: { ...data, job_id: jobId } });
      },
      async createPurchaseOrder(data) {
        return await this.request('purchase-orders', { method: 'POST', body: { ...data, job_id: jobId } });
      },

      // Deliveries
      async getDeliveries() {
        const response = await this.request(`deliveries?job_id=${jobId}`);
        return this.extractArray(response);
      },
      async createDelivery(data) {
        return await this.request('deliveries', { method: 'POST', body: { ...data, job_id: jobId } });
      },

      // Waste
      async getWasteNotes() {
        const response = await this.request(`waste-notes?job_id=${jobId}`);
        return this.extractArray(response);
      },
      async createWasteNote(data) {
        return await this.request('waste-notes', { method: 'POST', body: { ...data, job_id: jobId } });
      },

      // Stock - Uses stock-movements endpoint
      async getStockAllocations() {
        const response = await this.request(`stock-movements?job_id=${jobId}&type=allocation`);
        return this.extractArray(response);
      },
      async reserveStock(data) {
        return await this.request('stock-movements', { method: 'POST', body: { ...data, job_id: jobId, movement_type: 'allocation' } });
      },
      async issueStock(data) {
        return await this.request('stock-movements', { method: 'POST', body: { ...data, job_id: jobId, movement_type: 'issue' } });
      },
      async returnStock(data) {
        return await this.request('stock-movements', { method: 'POST', body: { ...data, job_id: jobId, movement_type: 'return' } });
      },

      // Suppliers & Materials
      async getSuppliers() {
        const response = await this.request('suppliers?per_page=100');
        return this.extractArray(response);
      },
      async getMaterials() {
        const response = await this.request('materials?per_page=100');
        return this.extractArray(response);
      },

      // Job Materials Summary - Uses project cost endpoint or returns defaults
      async getJobMaterialsSummary() {
        try {
          // Try to get from project/meta data first
          const response = await this.request(`boms?job_id=${jobId}&per_page=1`);
          const bomData = this.extractArray(response);

          // Calculate summary from BOM data if available
          const bomCount = bomData.length;
          let totalCost = 0;
          let pendingPOs = 0;

          if (bomCount > 0) {
            // Get all BOM items for this job
            const itemsResponse = await this.request(`bom-items?job_id=${jobId}&per_page=100`);
            const items = this.extractArray(itemsResponse);
            totalCost = items.reduce((sum, item) => sum + ((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_cost) || 0)), 0);
            pendingPOs = items.filter(item => item.status === 'pending' || item.status === 'draft').length;
          }

          return {
            bom_count: bomCount,
            pending_pos: pendingPOs,
            total_material_cost: totalCost,
            budget: 0, // Will be 0 until budget is set
            committed_cost: totalCost,
            spent_cost: 0,
            csi_breakdown: [],
            supplier_summary: []
          };
        } catch (e) {
          // Return default empty summary on error
          return {
            bom_count: 0,
            pending_pos: 0,
            total_material_cost: 0,
            budget: 0,
            committed_cost: 0,
            spent_cost: 0,
            csi_breakdown: [],
            supplier_summary: []
          };
        }
      }
    };

    // Load Materials Data
    async function loadJobMaterials() {
      if (!$('#job-tab-materials').length || !jobId) return;

      try {
        const summary = await MaterialsAPI.getJobMaterialsSummary();
        materialsData = summary;

        // Update KPI cards
        $('#pi-materials-bom-count').text(summary.bom_count || 0);
        $('#pi-materials-pending-pos').text(summary.pending_pos || 0);
        $('#pi-materials-total-cost').text(formatCurrency(summary.total_material_cost || 0));

        const budgetPercent = summary.budget > 0 ? Math.round((summary.total_material_cost / summary.budget) * 100) : 0;
        $('#pi-materials-budget-actual').text(`${budgetPercent}%`);

        // Update budget progress bar
        const committed = summary.committed_cost || 0;
        const spent = summary.spent_cost || 0;
        const budget = summary.budget || 0;
        const progressPercent = budget > 0 ? Math.min((committed / budget) * 100, 100) : 0;

        $('#pi-materials-budget-text').text(`${formatCurrency(committed)} / ${formatCurrency(budget)}`);
        $('#pi-materials-budget-progress').css('width', `${progressPercent}%`);

        // Color coding for progress bar
        const $progressBar = $('#pi-materials-budget-progress');
        $progressBar.removeClass('warning danger');
        if (progressPercent > 95) {
          $progressBar.addClass('danger');
        } else if (progressPercent > 80) {
          $progressBar.addClass('warning');
        }

        // Update breadcrumb job name
        if (currentData && currentData.title) {
          $('.pi-breadcrumb-job-name').text(currentData.title);
        }

        // Pre-load suppliers for dropdowns
        try {
          if (!materialsState.suppliers || materialsState.suppliers.length === 0) {
            materialsState.suppliers = await MaterialsAPI.getSuppliers();
            console.log('Materials tab: Pre-loaded', materialsState.suppliers.length, 'suppliers');
          }
        } catch (e) {
          console.error('Materials tab: Failed to pre-load suppliers:', e);
          materialsState.suppliers = [];
        }

        // Ensure the correct subtab is visually active
        const currentSubtab = materialsState.currentSubtab || 'overview';
        $('.pi-materials-subnav-btn').removeClass('active');
        $(`.pi-materials-subnav-btn[data-materials-subtab="${currentSubtab}"]`).addClass('active');

        // Show the correct subtab content
        $('.pi-materials-subtab-content').removeClass('active');
        $('#materials-subtab-' + currentSubtab).addClass('active');

        // Load subtab content
        await loadMaterialsSubtab(currentSubtab);

      } catch (err) {
        console.error('Failed to load materials:', err);
        showToast('Failed to load materials data', 'error');
      }
    }

    // Load Materials Subtab Content
    async function loadMaterialsSubtab(subtab) {
      materialsState.currentSubtab = subtab;

      try {
        switch (subtab) {
          case 'overview':
            await loadMaterialsOverview();
            break;
          case 'bom':
            await loadBOM();
            break;
          case 'purchasing':
            await loadPurchasing();
            break;
          case 'waste':
            await loadWaste();
            break;
          case 'stock':
            await loadStockAllocations();
            break;
        }
      } catch (err) {
        console.error(`Failed to load ${subtab}:`, err);
      }
    }

    // Load Materials Overview
    async function loadMaterialsOverview() {
      // Set loading states first
      $('#pi-materials-deliveries').html('<div class="pi-loading-state">Loading deliveries...</div>');
      $('#pi-materials-csi-breakdown').html('<div class="pi-loading-state">Loading breakdown...</div>');
      $('#pi-materials-supplier-summary').html('<div class="pi-loading-state">Loading suppliers...</div>');

      try {
        // Fetch data with individual error handling for each call
        let deliveries = [], csiBreakdown = [], suppliers = [];

        try {
          deliveries = await MaterialsAPI.getDeliveries();
        } catch (e) {
          console.log('Deliveries fetch failed:', e.message);
          deliveries = [];
        }

        // Use materialsData if available
        csiBreakdown = materialsData?.csi_breakdown || [];
        suppliers = materialsData?.supplier_summary || [];

        // Render upcoming deliveries
        const $deliveries = $('#pi-materials-deliveries');
        const now = new Date();
        const upcomingDeliveries = (deliveries || []).filter(d => d && d.date && new Date(d.date) >= now).slice(0, 5);
        if (upcomingDeliveries.length === 0) {
          $deliveries.html('<div class="pi-materials-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/></svg><p>No upcoming deliveries</p></div>');
        } else {
          const deliveriesHtml = upcomingDeliveries.map(d => `
            <div class="pi-activity-item">
              <div class="pi-activity-icon" style="background: #dbeafe; color: #1e40af">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/></svg>
              </div>
              <div class="pi-activity-content">
                <div class="pi-activity-text">${escapeHtml(d.po_number || 'PO-Unknown')} - ${escapeHtml(d.supplier_name || 'Unknown Supplier')}</div>
                <div class="pi-activity-time">${formatDate(d.delivery_date || d.date)}</div>
              </div>
            </div>
          `).join('');
          $deliveries.html(`<div class="pi-activity-feed">${deliveriesHtml}</div>`);
        }

        // Render CSI breakdown
        const $csiBreakdown = $('#pi-materials-csi-breakdown');
        if (!csiBreakdown || csiBreakdown.length === 0) {
          $csiBreakdown.html('<div class="pi-materials-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg><p>No cost data available</p></div>');
        } else {
          const csiHtml = csiBreakdown.map(item => `
            <div class="pi-cost-code-item">
              <div class="pi-cost-code-header">
                <span class="pi-cost-code-name">${escapeHtml(item.division || 'Unknown')}</span>
                <span class="pi-cost-code-amount">${formatCurrency(item.cost || 0)}</span>
              </div>
              <div class="pi-cost-code-bar">
                <div class="pi-cost-code-fill" style="width: ${item.percentage || 0}%; background: ${item.color || '#156349'}"></div>
              </div>
            </div>
          `).join('');
          $csiBreakdown.html(csiHtml);
        }

        // Render supplier summary
        const $supplierSummary = $('#pi-materials-supplier-summary');
        if (!suppliers || suppliers.length === 0) {
          $supplierSummary.html('<div class="pi-materials-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><p>No supplier data</p></div>');
        } else {
          const supplierHtml = suppliers.map(s => `
            <div class="pi-supplier-summary-item">
              <span class="pi-supplier-name">${escapeHtml(s.name || 'Unknown')}</span>
              <span class="pi-supplier-value">${formatCurrency(s.total_value || 0)}</span>
            </div>
          `).join('');
          $supplierSummary.html(supplierHtml);
        }

      } catch (err) {
        console.error('Failed to load overview:', err);
        // Show error state for all sections
        const errorHtml = '<div class="pi-materials-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><p>Unable to load data</p></div>';
        $('#pi-materials-deliveries').html(errorHtml);
        $('#pi-materials-csi-breakdown').html(errorHtml);
        $('#pi-materials-supplier-summary').html(errorHtml);
      }
    }

    // Load BOM
    async function loadBOM() {
      const $container = $('#pi-bom-container');
      $container.html('<div class="pi-loading-state">Loading Bill of Materials...</div>');

      try {
        let bomItems = [];
        try {
          bomItems = await MaterialsAPI.getJobBOM();
        } catch (e) {
          console.log('BOM endpoint error:', e.message);
        }

        materialsState.bomItems = bomItems || [];

        if (!bomItems || bomItems.length === 0) {
          $container.html(`
            <div class="pi-materials-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
              <h4>No BOM items yet</h4>
              <p>Add materials to create a bill of materials for this job</p>
              <button class="pi-btn pi-btn-primary" id="pi-add-first-bom-btn">Add BOM Item</button>
            </div>
          `);
          return;
        }

        const html = `
          <table class="pi-bom-table">
            <thead>
              <tr>
                <th>CSI</th>
                <th>Material</th>
                <th>Qty</th>
                <th>Unit Cost</th>
                <th>Markup</th>
                <th>Wastage</th>
                <th>Line Total</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              ${bomItems.map(item => {
                const csiClass = `pi-csi-${(item.category || 'other').toLowerCase()}`;
                const lineTotal = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_cost) || 0) * (1 + (parseFloat(item.wastage_percent) || 0) / 100);
                return `
                  <tr data-id="${item.id}">
                    <td><span class="pi-cat-badge ${csiClass}">${escapeHtml(item.category || 'Other')}</span></td>
                    <td><strong>${escapeHtml(item.material_name || 'Unknown')}</strong></td>
                    <td><span class="pi-bom-editable" data-field="quantity">${item.quantity || 0}</span> ${escapeHtml(item.unit || 'each')}</td>
                    <td>${formatCurrency(item.unit_cost)}</td>
                    <td><span class="pi-bom-editable" data-field="markup">${item.markup_percent || 0}%</span></td>
                    <td>${item.wastage_percent || 0}%</td>
                    <td class="pi-text-right">${formatCurrency(lineTotal)}</td>
                    <td><span class="pi-status-badge pi-status-${(item.status || 'draft').toLowerCase()}">${escapeHtml(item.status || 'Draft')}</span></td>
                    <td>
                      <div class="pi-action-btns">
                        <button class="pi-btn pi-btn-icon pi-btn-ghost edit-bom-item" title="Edit">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="pi-btn pi-btn-icon pi-btn-ghost delete-bom-item" title="Delete">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        `;

        $container.html(html);
        bindBOMEvents();

      } catch (err) {
        console.error('Failed to load BOM:', err);
        $container.html(`
          <div class="pi-materials-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h4>Failed to load BOM</h4>
            <p>${escapeHtml(err.message || 'Please try again')}</p>
            <button class="pi-btn pi-btn-primary" onclick="loadMaterialsSubtab('bom')">Retry</button>
          </div>
        `);
      }
    }

    // Load Purchasing
    async function loadPurchasing() {
      const tab = materialsState.purchasingTab;

      // Set loading states
      $('#pi-requisitions-container').html('<div class="pi-loading-state">Loading requisitions...</div>');
      $('#pi-purchase-orders-container').html('<div class="pi-loading-state">Loading purchase orders...</div>');
      $('#pi-deliveries-container').html('<div class="pi-loading-state">Loading deliveries...</div>');

      try {
        if (tab === 'requisitions') {
          let requisitions = [];
          try {
            requisitions = await MaterialsAPI.getRequisitions();
          } catch (e) {
            console.log('Requisitions fetch failed:', e.message);
          }
          materialsState.requisitions = requisitions || [];
          renderRequisitions(materialsState.requisitions);
        } else if (tab === 'orders') {
          let orders = [];
          try {
            orders = await MaterialsAPI.getPurchaseOrders();
          } catch (e) {
            console.log('PO fetch failed:', e.message);
          }
          materialsState.purchaseOrders = orders || [];
          renderPurchaseOrders(materialsState.purchaseOrders);
        } else if (tab === 'deliveries') {
          let deliveries = [];
          try {
            deliveries = await MaterialsAPI.getDeliveries();
          } catch (e) {
            console.log('Deliveries fetch failed:', e.message);
          }
          materialsState.deliveries = deliveries || [];
          renderDeliveries(materialsState.deliveries);
        }
      } catch (err) {
        console.error('Failed to load purchasing:', err);
        const errorHtml = `
          <div class="pi-materials-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Failed to load data</p>
          </div>
        `;
        $('#pi-requisitions-container').html(errorHtml);
        $('#pi-purchase-orders-container').html(errorHtml);
        $('#pi-deliveries-container').html(errorHtml);
      }
    }

    function renderRequisitions(requisitions) {
      const $container = $('#pi-requisitions-container');
      if (!requisitions || requisitions.length === 0) {
        $container.html(`
          <div class="pi-materials-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <h4>No Requisitions</h4>
            <p>Create requisitions to request materials for this job</p>
            <button class="pi-btn pi-btn-primary" id="pi-first-requisition-btn">Create Requisition</button>
          </div>
        `);
        return;
      }

      const html = `
        <table class="pi-bom-table">
          <thead>
            <tr>
              <th>Req #</th>
              <th>Item</th>
              <th>Qty</th>
              <th>Est. Cost</th>
              <th>Requested By</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            ${requisitions.map(req => `
              <tr data-id="${req.id}">
                <td>${escapeHtml(req.reference || req.id)}</td>
                <td>${escapeHtml(req.item_name || req.material_name || 'Unknown')}</td>
                <td>${req.quantity} ${escapeHtml(req.unit || '')}</td>
                <td>${formatCurrency(req.total || req.estimated_cost || req.unit_cost)}</td>
                <td>${escapeHtml(req.requested_by_name || req.requested_by || 'Unknown')}</td>
                <td>${formatDate(req.created_at || req.date || req.required_date)}</td>
                <td><span class="pi-status-badge pi-status-${(req.status || 'draft').toLowerCase()}">${escapeHtml(req.status || 'Draft')}</span></td>
                <td>
                  <button class="pi-btn pi-btn-sm pi-btn-primary convert-to-po" data-id="${req.id}">Create PO</button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
      $container.html(html);
    }

    function renderPurchaseOrders(orders) {
      const $container = $('#pi-purchase-orders-container');
      if (!orders || orders.length === 0) {
        $container.html(`
          <div class="pi-materials-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
            <h4>No Purchase Orders</h4>
            <p>Convert requisitions into purchase orders for suppliers</p>
          </div>
        `);
        return;
      }

      const html = `
        <table class="pi-bom-table">
          <thead>
            <tr>
              <th>PO #</th>
              <th>Supplier</th>
              <th>Date</th>
              <th>Total</th>
              <th>Status</th>
              <th>Delivery</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            ${orders.map(po => `
              <tr data-id="${po.id}">
                <td><strong>${escapeHtml(po.po_number || po.reference || po.id)}</strong></td>
                <td>${escapeHtml(po.supplier_name || 'Unknown')}</td>
                <td>${formatDate(po.created_at || po.date || po.order_date)}</td>
                <td class="pi-text-right">${formatCurrency(po.total || 0)}</td>
                <td><span class="pi-status-badge pi-status-${(po.status || 'draft').toLowerCase()}">${escapeHtml(po.status || 'Draft')}</span></td>
                <td>${formatDate(po.delivery_date || po.required_date)}</td>
                <td>
                  <button class="pi-btn pi-btn-icon pi-btn-ghost view-po" title="View" data-id="${po.id}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
      $container.html(html);
    }

    function renderDeliveries(deliveries) {
      const $container = $('#pi-deliveries-container');
      if (!deliveries || deliveries.length === 0) {
        $container.html(`
          <div class="pi-materials-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/></svg>
            <h4>No Deliveries</h4>
            <p>Track incoming deliveries against purchase orders</p>
            <button class="pi-btn pi-btn-primary" id="pi-create-first-delivery-btn">Record Delivery</button>
          </div>
        `);
        return;
      }

      const html = `
        <table class="pi-bom-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>PO #</th>
              <th>Supplier</th>
              <th>Items</th>
              <th>Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            ${deliveries.map(d => `
              <tr data-id="${d.id}">
                <td>${formatDate(d.delivery_date || d.date)}</td>
                <td>${escapeHtml(d.po_number || d.po_id || '-')}</td>
                <td>${escapeHtml(d.supplier_name || 'Unknown')}</td>
                <td>${d.item_count || 1} items</td>
                <td><span class="pi-status-badge pi-status-${(d.status || 'pending').toLowerCase()}">${escapeHtml(d.status || 'Pending')}</span></td>
                <td>${escapeHtml(d.notes || '-')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
      $container.html(html);
    }

    // Load Waste
    async function loadWaste() {
      const $container = $('#pi-waste-container');
      $container.html('<div class="pi-loading-state">Loading waste transfer notes...</div>');
      $('#pi-waste-total').text('—');
      $('#pi-waste-diverted').text('—');

      try {
        let wasteNotes = [];
        try {
          wasteNotes = await MaterialsAPI.getWasteNotes();
        } catch (e) {
          console.log('Waste notes fetch failed:', e.message);
        }

        wasteNotes = wasteNotes || [];
        materialsState.wasteNotes = wasteNotes;

        // Calculate totals
        const totalTonnes = wasteNotes.reduce((sum, w) => sum + (parseFloat(w?.quantity) || 0), 0);
        const diverted = wasteNotes.filter(w => w?.diverted).reduce((sum, w) => sum + (parseFloat(w?.quantity) || 0), 0);
        const divertedPercent = totalTonnes > 0 ? Math.round((diverted / totalTonnes) * 100) : 0;

        $('#pi-waste-total').text(`${totalTonnes.toFixed(2)} tonnes`);
        $('#pi-waste-diverted').text(`${divertedPercent}% diverted`);

        if (wasteNotes.length === 0) {
          $container.html(`
            <div class="pi-materials-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18l-1.5 14H4.5z"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              <h4>No Waste Transfer Notes</h4>
              <p>Create waste transfer notes to track disposal and compliance</p>
              <button class="pi-btn pi-btn-primary" id="pi-create-first-wtn-btn">Create WTN</button>
            </div>
          `);
          return;
        }

        const html = `
          <table class="pi-bom-table">
            <thead>
              <tr>
                <th>WTN #</th>
                <th>Date</th>
                <th>EWC Code</th>
                <th>Quantity</th>
                <th>Carrier</th>
                <th>Disposal Site</th>
                <th>Hazardous</th>
                <th>Diverted</th>
              </tr>
            </thead>
            <tbody>
              ${wasteNotes.map(w => `
                <tr data-id="${w.id}">
                  <td><strong>${escapeHtml(w.wtn_number || w.id)}</strong></td>
                  <td>${formatDate(w.collection_date || w.date)}</td>
                  <td>${escapeHtml(w.ewc_code || '-')}</td>
                  <td>${w.quantity || 0} ${escapeHtml(w.unit || 'tonnes')}</td>
                  <td>${escapeHtml(w.carrier_name || '-')}</td>
                  <td>${escapeHtml(w.disposal_site || '-')}</td>
                  <td>${w.hazardous ? '<span class="pi-status-badge pi-status-danger">Yes</span>' : '<span class="pi-status-badge pi-status-approved">No</span>'}</td>
                  <td>${w.diverted ? '<span class="pi-status-badge pi-status-approved">Yes</span>' : '<span class="pi-status-badge pi-status-rejected">No</span>'}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
        $container.html(html);

      } catch (err) {
        console.error('Failed to load waste notes:', err);
        $container.html(`
          <div class="pi-materials-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h4>Failed to load waste data</h4>
            <p>${escapeHtml(err.message || 'Please try again')}</p>
            <button class="pi-btn pi-btn-primary" onclick="loadMaterialsSubtab('waste')">Retry</button>
          </div>
        `);
      }
    }

    // Load Stock Allocations
    async function loadStockAllocations() {
      const $container = $('#pi-stock-container');
      $container.html('<div class="pi-loading-state">Loading stock allocations...</div>');

      try {
        let movements = [];
        try {
          movements = await MaterialsAPI.getStockAllocations();
        } catch (e) {
          console.log('Stock allocations fetch failed:', e.message);
        }

        movements = movements || [];
        
        // Transform raw movements into allocation format
        // Group by material_id and aggregate quantities
        const materialMap = new Map();
        
        movements.forEach(m => {
          const materialId = m.material_id;
          if (!materialMap.has(materialId)) {
            materialMap.set(materialId, {
              id: m.id,
              material_id: materialId,
              material_name: m.material_name || `Material #${materialId}`,
              unit: m.unit || 'each',
              reserved_quantity: 0,
              issued_quantity: 0,
              available_quantity: 0,
              status: 'reserved'
            });
          }
          
          const alloc = materialMap.get(materialId);
          const qty = parseFloat(m.quantity) || 0;
          
          if (m.movement_type === 'allocation') {
            alloc.reserved_quantity += qty;
          } else if (m.movement_type === 'issue') {
            alloc.issued_quantity += qty;
          }
        });
        
        // Calculate available quantities
        const allocations = Array.from(materialMap.values()).map(a => {
          a.available_quantity = a.reserved_quantity - a.issued_quantity;
          a.status = a.available_quantity > 0 ? 'reserved' : 'issued';
          return a;
        }).filter(a => a.reserved_quantity > 0); // Only show materials with reservations
        
        materialsState.stockAllocations = allocations;

        if (allocations.length === 0) {
          $container.html(`
            <div class="pi-materials-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
              <h4>No Stock Reserved</h4>
              <p>Reserve stock from your inventory for this job</p>
              <button class="pi-btn pi-btn-primary" id="pi-reserve-first-stock-btn">Reserve Stock</button>
            </div>
          `);
          return;
        }

        const html = `
          <table class="pi-bom-table">
            <thead>
              <tr>
                <th>Material</th>
                <th>Reserved</th>
                <th>Issued</th>
                <th>Available</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              ${allocations.map(a => `
                <tr data-id="${a.id}">
                  <td><strong>${escapeHtml(a.material_name || 'Unknown')}</strong></td>
                  <td>${a.reserved_quantity.toFixed(2)} ${escapeHtml(a.unit || 'each')}</td>
                  <td>${a.issued_quantity.toFixed(2)} ${escapeHtml(a.unit || 'each')}</td>
                  <td>${a.available_quantity.toFixed(2)} ${escapeHtml(a.unit || 'each')}</td>
                  <td><span class="pi-status-badge pi-status-${(a.status || 'reserved').toLowerCase()}">${escapeHtml(a.status === 'reserved' ? 'Reserved' : 'Issued')}</span></td>
                  <td>
                    <button class="pi-btn pi-btn-sm pi-btn-primary issue-stock" data-id="${a.material_id}">Issue</button>
                    <button class="pi-btn pi-btn-sm pi-btn-ghost return-stock" data-id="${a.material_id}">Return</button>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
        $container.html(html);

      } catch (err) {
        console.error('Failed to load stock allocations:', err);
        $container.html(`
          <div class="pi-materials-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h4>Failed to load stock data</h4>
            <p>${escapeHtml(err.message || 'Please try again')}</p>
            <button class="pi-btn pi-btn-primary" onclick="loadMaterialsSubtab('stock')">Retry</button>
          </div>
        `);
      }
    }

    // Bind BOM Events
    function bindBOMEvents() {
      // Edit BOM item
      $('#pi-bom-container').on('click', '.edit-bom-item', function() {
        const id = $(this).closest('tr').data('id');
        const item = materialsState.bomItems.find(i => i.id == id);
        if (item) MaterialsModals.bomItem(item);
      });

      // Delete BOM item
      $('#pi-bom-container').on('click', '.delete-bom-item', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $row = $(this).closest('tr');
        const id = $row.data('id');
        const itemName = $row.find('td:first').text().trim() || 'BOM item';
        if (!confirm('Delete this BOM item?')) return;

        try {
          await MaterialsAPI.deleteBOMItem(id);
          showToast('BOM item deleted');
          try { await API.logActivity(jobId, `Deleted BOM item: ${itemName}`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
          await loadBOM();
          await loadJobMaterials(); // Refresh summary
          await new Promise(r => setTimeout(r, 300));
          await loadJob(); // Refresh activity timeline
        } catch (err) {
          showToast(err.message || 'Failed to delete BOM item', 'error');
        }
      });

      // Create PO from requisition
      $(document).on('click', '.convert-to-po', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        const reqId = $(this).data('id');
        const req = materialsState.requisitions.find(r => r.id == reqId);
        if (!req) {
          showToast('Requisition not found', 'error');
          return;
        }
        // Open PO modal with this requisition pre-selected
        MaterialsModals.purchaseOrderFromRequisition(req);
      });

      // View PO button
      $(document).on('click', '.view-po', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        const poId = $(this).data('id');
        const po = materialsState.purchaseOrders.find(p => p.id == poId);
        if (!po) {
          showToast('Purchase order not found', 'error');
          return;
        }
        MaterialsModals.viewPurchaseOrder(po);
      });

      // Issue stock button
      $(document).on('click', '.issue-stock', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        const materialId = $(this).data('id');
        const alloc = materialsState.stockAllocations.find(a => a.material_id == materialId);
        if (!alloc) {
          showToast('Stock allocation not found', 'error');
          return;
        }
        
        const qty = prompt(`Issue quantity (available: ${alloc.available_quantity} ${alloc.unit}):`, alloc.available_quantity);
        if (!qty || parseFloat(qty) <= 0) return;
        
        try {
          await MaterialsAPI.issueStock({
            material_id: materialId,
            quantity: parseFloat(qty),
            unit: alloc.unit
          });
          showToast('Stock issued successfully');
          await loadStockAllocations();
          await loadJobMaterials();
        } catch (err) {
          showToast(err.message || 'Failed to issue stock', 'error');
        }
      });

      // Return stock button
      $(document).on('click', '.return-stock', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        const materialId = $(this).data('id');
        const alloc = materialsState.stockAllocations.find(a => a.material_id == materialId);
        if (!alloc) {
          showToast('Stock allocation not found', 'error');
          return;
        }
        
        const qty = prompt(`Return quantity (issued: ${alloc.issued_quantity} ${alloc.unit}):`, alloc.issued_quantity);
        if (!qty || parseFloat(qty) <= 0) return;
        
        try {
          await MaterialsAPI.returnStock({
            material_id: materialId,
            quantity: parseFloat(qty),
            unit: alloc.unit
          });
          showToast('Stock returned successfully');
          await loadStockAllocations();
          await loadJobMaterials();
        } catch (err) {
          showToast(err.message || 'Failed to return stock', 'error');
        }
      });
    }

    // Materials Modals - Uses same pattern as Costs tab
    const MaterialsModals = {
      // Create modal with inline styles (like Costs tab)
      create(options) {
        const { title, content, size = 'md', onSubmit, onCreate, onClose, customFooter } = options;
        
        // Remove any existing materials modal first
        $('#pi-modal-overlay-materials').remove();
        
        const modalHtml = `
          <div id="pi-modal-overlay-materials" style="
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            z-index: 100000; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          ">
            <div id="pi-modal-container-materials" style="
              background: #fff; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
              max-width: ${size === 'lg' ? '700px' : size === 'xl' ? '900px' : '500px'};
              width: 90%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;
            ">
              <div style="
                display: flex; align-items: center; justify-content: space-between;
                padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;
              ">
                <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: #111827;">${title}</h3>
                <button type="button" id="pi-modal-close-materials" style="
                  background: none; border: none; font-size: 1.5rem; color: #6b7280;
                  cursor: pointer; padding: 4px; line-height: 1;
                ">&times;</button>
              </div>
              <div style="padding: 24px; overflow-y: auto; flex: 1;">
                ${content}
              </div>
              <div style="
                display: flex; justify-content: flex-end; gap: 12px;
                padding: 16px 24px; border-top: 1px solid #e5e7eb; background: #f9fafb;
              ">
                ${customFooter || `
                  <button type="button" id="pi-modal-cancel-materials" style="
                    padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px;
                    background: #fff; color: #374151; font-size: 0.875rem; font-weight: 500;
                    cursor: pointer;
                  ">Cancel</button>
                  <button type="button" id="pi-modal-submit-materials" style="
                    padding: 8px 16px; border: none; border-radius: 6px;
                    background: #156349; color: #fff; font-size: 0.875rem; font-weight: 500;
                    cursor: pointer;
                  ">Save</button>
                `}
              </div>
            </div>
          </div>
        `;
        
        $('body').append(modalHtml);
        
        const closeModal = () => {
          if (onClose) onClose();
          $('#pi-modal-overlay-materials').remove();
          $(document).off('keydown.pi-materials-modal');
        };
        
        // Call onCreate callback after modal is in DOM
        if (onCreate) {
          setTimeout(() => onCreate(), 0);
        }
        
        // Close handlers
        $('#pi-modal-close-materials, #pi-modal-cancel-materials').on('click', closeModal);
        $('#pi-modal-overlay-materials').on('click', function(e) {
          if (e.target === this) closeModal();
        });
        
        // Submit handler
        if (onSubmit) {
          $('#pi-modal-submit-materials').on('click', function() {
            onSubmit(closeModal);
          });
        }
        
        // Escape key
        $(document).on('keydown.pi-materials-modal', function(e) {
          if (e.key === 'Escape') closeModal();
        });
        
        return { close: closeModal };
      },

      // BOM Item Modal
      async bomItem(item = null) {
        const isEdit = !!item;
        
        // Show loading state while fetching data
      const loadingModal = this.create({
        title: isEdit ? 'Edit BOM Item' : 'Add BOM Item',
        content: '<div style="text-align:center;padding:40px;"><div class="pi-loading-state">Loading...</div></div>',
        customFooter: '<button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>'
      });
      
      try {
        // Load suppliers and materials with explicit error handling
        let suppliers = [];
        let materials = [];
        
        // Try to use pre-loaded suppliers first
        if (materialsState.suppliers && materialsState.suppliers.length > 0) {
          suppliers = materialsState.suppliers;
          console.log('BOM Modal: Using pre-loaded', suppliers.length, 'suppliers');
        } else {
          try {
            suppliers = await MaterialsAPI.getSuppliers();
            materialsState.suppliers = suppliers;
            console.log('BOM Modal: Loaded', suppliers.length, 'suppliers');
          } catch (e) {
            console.error('BOM Modal: Failed to load suppliers:', e);
            suppliers = [];
          }
        }
        
        try {
          materials = await MaterialsAPI.getMaterials();
          console.log('BOM Modal: Loaded', materials.length, 'materials');
        } catch (e) {
          console.error('BOM Modal: Failed to load materials:', e);
          materials = [];
        }
        
        // Close loading modal and show actual form
        loadingModal.close();
        
        const content = `
          <form id="bom-item-form-materials" style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Material *</label>
              <select name="material_id" id="bom-material-select" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" ${isEdit ? 'disabled' : ''}>
                <option value="">Select from library...</option>
                <option value="custom">Custom Item</option>
                ${materials.map(m => {
                const stockStatus = (m.stock_level || 0) <= 0 ? 'out' : (m.stock_level || 0) <= (m.reorder_point || 0) ? 'low' : 'ok';
                const stockLabel = stockStatus === 'out' ? ' [OUT OF STOCK]' : stockStatus === 'low' ? ' [LOW STOCK]' : '';
                return `<option value="${m.id}" 
                  data-base-cost="${m.base_cost || 0}" 
                  data-unit="${escapeHtml(m.unit || 'each')}"
                  data-category="${escapeHtml(m.category || '')}"
                  data-stock-level="${m.stock_level || 0}"
                  data-reorder-point="${m.reorder_point || 0}"
                  data-markup="${m.markup_percent || 0}"
                  data-wastage="${m.wastage_percent || 0}"
                  data-sku="${escapeHtml(m.sku || '')}"
                  data-supplier-id="${m.supplier_id || ''}"
                  ${item?.material_id == m.id ? 'selected' : ''}>${escapeHtml(m.name)} (${escapeHtml(m.sku || 'No SKU')}) - ${formatCurrency(m.base_cost || 0)}${stockLabel}</option>`;
              }).join('')}
              </select>
            </div>
            <div id="custom-name-group-materials" style="display:${item?.material_id ? 'none' : 'flex'};flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Custom Name *</label>
              <input type="text" name="material_name" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" value="${escapeHtml(item?.material_name || '')}">
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Category</label>
                <select name="category" id="bom-category" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                  <option value="">Select...</option>
                  <option value="structural" ${item?.category === 'structural' ? 'selected' : ''}>Structural</option>
                  <option value="finishes" ${item?.category === 'finishes' ? 'selected' : ''}>Finishes</option>
                  <option value="mep" ${item?.category === 'mep' ? 'selected' : ''}>MEP</option>
                  <option value="site" ${item?.category === 'site' ? 'selected' : ''}>Site Works</option>
                </select>
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Quantity *</label>
                <input type="number" name="quantity" step="0.01" min="0" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" value="${item?.quantity || ''}">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Unit</label>
                <input type="text" name="unit" id="bom-unit" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" value="${escapeHtml(item?.unit || 'each')}">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Unit Cost (£)</label>
                <input type="number" name="unit_cost" id="bom-unit-cost" step="0.01" min="0" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" value="${item?.unit_cost || ''}">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Markup %</label>
                <input type="number" name="markup_percent" id="bom-markup" step="0.1" min="0" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" value="${item?.markup_percent || 0}">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Wastage %</label>
                <input type="number" name="wastage_percent" id="bom-wastage" step="0.1" min="0" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" value="${item?.wastage_percent || 0}">
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Supplier (${suppliers.length} available)</label>
              <select name="supplier_id" id="bom-supplier" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                <option value="">${suppliers.length === 0 ? 'No suppliers found' : 'Select supplier...'}</option>
                ${suppliers.map(s => {
                  const name = s.company_name || s.name || 'Unnamed Supplier';
                  return `<option value="${s.id}" ${item?.supplier_id == s.id ? 'selected' : ''}>${escapeHtml(name)}</option>`;
                }).join('')}
              </select>
            </div>
            
            <!-- Cost Summary Panel -->
            <div id="cost-summary-panel" style="display:none;padding:12px;border-radius:8px;background:#f0fdf4;border:1px solid #86efac;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span style="font-size:1.25rem;">💰</span>
                <strong style="font-size:0.875rem;color:#0f172a;">Cost Calculation</strong>
              </div>
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;font-size:0.8125rem;">
                <div>
                  <span style="color:#64748b;">Unit Cost:</span>
                  <div id="cost-unit-display" style="font-weight:600;color:#0f172a;">-</div>
                </div>
                <div>
                  <span style="color:#64748b;">Quantity:</span>
                  <div id="cost-qty-display" style="font-weight:600;color:#0f172a;">-</div>
                </div>
                <div>
                  <span style="color:#64748b;">Total Cost:</span>
                  <div id="cost-total-display" style="font-weight:700;color:#15803d;font-size:1rem;">-</div>
                </div>
              </div>
            </div>
            
            <!-- Stock Info Panel -->
            <div id="stock-info-panel" style="display:none;padding:12px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span id="stock-status-icon" style="font-size:1.25rem;"></span>
                <strong style="font-size:0.875rem;color:#0f172a;">Stock Information</strong>
              </div>
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;font-size:0.8125rem;">
                <div>
                  <span style="color:#64748b;">Current Stock:</span>
                  <div id="stock-level-display" style="font-weight:600;color:#0f172a;">-</div>
                </div>
                <div>
                  <span style="color:#64748b;">After This:</span>
                  <div id="stock-after-display" style="font-weight:600;color:#0f172a;">-</div>
                </div>
                <div>
                  <span style="color:#64748b;">Reorder Point:</span>
                  <div id="reorder-point-display" style="font-weight:600;color:#0f172a;">-</div>
                </div>
              </div>
              <div id="stock-warning" style="margin-top:8px;padding:8px;border-radius:6px;font-size:0.8125rem;display:none;"></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Required Date</label>
              <input type="date" name="required_date" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" value="${formatDateInput(item?.required_date)}">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Notes</label>
              <textarea name="notes" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;">${escapeHtml(item?.notes || '')}</textarea>
            </div>
          </form>
        `;
        
        const footer = `
          <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>
          <button type="button" id="pi-modal-submit-materials" style="padding:8px 16px;border:none;border-radius:6px;background:#156349;color:#fff;font-size:0.875rem;font-weight:500;cursor:pointer;">${isEdit ? 'Update' : 'Add'} Item</button>
        `;
        
        this.create({
          title: isEdit ? 'Edit BOM Item' : 'Add BOM Item',
          content: content,
          customFooter: footer,
          onCreate: () => {
            // Function to update cost display
            function updateCostDisplay() {
              const unitCost = parseFloat($('#bom-unit-cost').val()) || 0;
              const quantity = parseFloat($('input[name="quantity"]').val()) || 0;
              const totalCost = unitCost * quantity;
              
              $('#cost-unit-display').text(formatCurrency(unitCost));
              $('#cost-qty-display').text(quantity.toFixed(2));
              $('#cost-total-display').text(formatCurrency(totalCost));
              
              // Show cost panel if we have values
              if (unitCost > 0 || quantity > 0) {
                $('#cost-summary-panel').show();
              }
            }
            
            // Function to update stock display based on quantity
            function updateStockDisplay(stockLevel, reorderPoint, quantity) {
              const qty = parseFloat(quantity) || 0;
              const afterStock = Math.max(0, stockLevel - qty);
              const reorderNeeded = afterStock <= reorderPoint;
              const outOfStock = stockLevel <= 0;
              const lowStock = stockLevel <= reorderPoint && stockLevel > 0;
              
              // Update display values
              $('#stock-level-display').text(stockLevel.toFixed(2));
              $('#stock-after-display').text(afterStock.toFixed(2));
              $('#reorder-point-display').text(reorderPoint.toFixed(2));
              
              // Update colors based on status
              const $afterDisplay = $('#stock-after-display');
              const $icon = $('#stock-status-icon');
              
              if (outOfStock) {
                $icon.html('⚠️');
                $afterDisplay.css('color', '#dc2626');
              } else if (lowStock) {
                $icon.html('⚡');
                $afterDisplay.css('color', '#ea580c');
              } else if (reorderNeeded) {
                $icon.html('📦');
                $afterDisplay.css('color', '#ca8a04');
              } else {
                $icon.html('✅');
                $afterDisplay.css('color', '#16a34a');
              }
              
              // Show/hide warning
              const $warning = $('#stock-warning');
              if (outOfStock) {
                $warning.show().css({background: '#fef2f2', color: '#dc2626', border: '1px solid #fecaca'})
                  .html('<strong>OUT OF STOCK:</strong> This material has no stock available. You will need to order all required quantity.');
              } else if (lowStock) {
                $warning.show().css({background: '#fff7ed', color: '#ea580c', border: '1px solid #fed7aa'})
                  .html('<strong>LOW STOCK:</strong> Current stock is at or below reorder point. Consider ordering soon.');
              } else if (reorderNeeded) {
                $warning.show().css({background: '#fefce8', color: '#ca8a04', border: '1px solid #fde68a'})
                  .html('<strong>REORDER WARNING:</strong> After this allocation, stock will fall below reorder point.');
              } else {
                $warning.hide();
              }
            }
            
            // Auto-fill all fields when material is selected
            $(document).on('change', '#bom-material-select', function() {
              const selectedOption = $(this).find('option:selected');
              const materialId = $(this).val();
              const materialName = selectedOption.text().split('(')[0]?.trim();
              
              console.log('Material selected:', materialId, materialName);
              
              if (materialId && materialId !== 'custom') {
                // Get all data attributes
                const baseCost = selectedOption.data('base-cost');
                const unit = selectedOption.data('unit');
                const category = selectedOption.data('category');
                const markup = selectedOption.data('markup');
                const wastage = selectedOption.data('wastage');
                const stockLevel = parseFloat(selectedOption.data('stock-level')) || 0;
                const reorderPoint = parseFloat(selectedOption.data('reorder-point')) || 0;
                const supplierId = selectedOption.data('supplier-id');
                
                console.log('Auto-filling from material data:', { baseCost, unit, category, markup, wastage, supplierId, stockLevel });
                
                // Auto-fill form fields with visual highlight effect
                if (baseCost !== undefined && baseCost !== '') {
                  $('#bom-unit-cost').val(baseCost).css('background-color', '#dcfce7').animate({backgroundColor: '#ffffff'}, 500);
                }
                if (unit !== undefined && unit !== '') {
                  $('#bom-unit').val(unit).css('background-color', '#dcfce7').animate({backgroundColor: '#ffffff'}, 500);
                }
                if (category !== undefined && category !== '') {
                  $('#bom-category').val(category).css('background-color', '#dcfce7').animate({backgroundColor: '#ffffff'}, 500);
                }
                if (markup !== undefined && markup !== '' && markup !== '0') {
                  $('#bom-markup').val(markup).css('background-color', '#dcfce7').animate({backgroundColor: '#ffffff'}, 500);
                }
                if (wastage !== undefined && wastage !== '' && wastage !== '0') {
                  $('#bom-wastage').val(wastage).css('background-color', '#dcfce7').animate({backgroundColor: '#ffffff'}, 500);
                }
                
                // Auto-fill supplier if material has one
                if (supplierId !== undefined && supplierId !== '' && supplierId !== '0') {
                  $('#bom-supplier').val(supplierId).css('background-color', '#dcfce7').animate({backgroundColor: '#ffffff'}, 500);
                  console.log('Supplier auto-filled:', supplierId);
                }
                
                // Show panels
                $('#stock-info-panel').show().css('animation', 'slideDown 0.3s ease');
                $('#cost-summary-panel').show().css('animation', 'slideDown 0.3s ease');
                
                // Update cost display
                updateCostDisplay();
                
                // Update stock display with current quantity
                const quantity = $('input[name="quantity"]').val() || 0;
                updateStockDisplay(stockLevel, reorderPoint, quantity);
                
                // Store current stock data for quantity change handler
                $('#stock-info-panel').data('stock-level', stockLevel).data('reorder-point', reorderPoint);
                
                // Hide custom name field when material selected
                $('#custom-name-group-materials').hide();
                
                showToast(`"${materialName}" loaded - fields auto-filled from material library`, 'success');
              } else if (materialId === 'custom') {
                // Show custom name field for custom items
                $('#custom-name-group-materials').show();
                $('#stock-info-panel').hide();
              } else {
                $('#custom-name-group-materials').hide();
                $('#stock-info-panel').hide();
              }
            });
            
            // Update stock display when quantity changes
            $(document).on('input', 'input[name="quantity"]', function() {
              const stockLevel = $('#stock-info-panel').data('stock-level');
              const reorderPoint = $('#stock-info-panel').data('reorder-point');
              if (stockLevel !== undefined && reorderPoint !== undefined) {
                updateStockDisplay(stockLevel, reorderPoint, $(this).val());
              }
              // Update cost display when quantity changes
              updateCostDisplay();
            });
            
            // Update cost display when unit cost changes
            $(document).on('input', '#bom-unit-cost', function() {
              updateCostDisplay();
            });
          },
          onSubmit: async (closeModal) => {
            const $form = $('#bom-item-form-materials');
            if (!$form[0].checkValidity()) {
              $form[0].reportValidity();
              return;
            }
            
            const data = {};
            $form.serializeArray().forEach(item => {
              data[item.name] = item.value;
            });
            
            // If material selected, set the material name from the dropdown
            const materialId = $('#bom-material-select').val();
            if (materialId && materialId !== 'custom') {
              const selectedMaterial = materials.find(m => m.id == materialId);
              if (selectedMaterial) {
                data.material_name = selectedMaterial.name;
                data.material_sku = selectedMaterial.sku || '';
              }
            }
            
            try {
              const materialName = data.material_id ? $('#bom-material-select option:selected').text().split('(')[0]?.trim() : (data.material_name || 'Custom item');
              if (isEdit) {
                await MaterialsAPI.updateBOMItem(item.id, data);
                showToast('BOM item updated');
                try { await API.logActivity(jobId, `Updated BOM item: ${materialName} (${data.quantity} ${data.unit})`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
              } else {
                await MaterialsAPI.createBOMItem(data);
                showToast('BOM item added');
                try { await API.logActivity(jobId, `Added BOM item: ${materialName} (${data.quantity} ${data.unit}) - ${formatCurrency(data.unit_cost * data.quantity)}`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
              }
              closeModal();
              $(document).off('change', '#bom-material-select');
              $(document).off('input', 'input[name="quantity"]');
              await loadBOM();
              await loadJobMaterials();
              await new Promise(r => setTimeout(r, 300));
              await loadJob(); // Refresh activity timeline
            } catch (err) {
              showToast(err.message || 'Failed to save BOM item', 'error');
            }
          },
          onClose: () => {
            $(document).off('change', '#bom-material-select');
            $(document).off('input', 'input[name="quantity"]');
          }
        });
        
      } catch (err) {
        loadingModal.close();
        showToast('Failed to load data for modal', 'error');
      }
    },

    // Requisition Modal
    async requisition() {
      // Load suppliers with explicit error handling
      let suppliers = [];
      // Try to use pre-loaded suppliers first
      if (materialsState.suppliers && materialsState.suppliers.length > 0) {
        suppliers = materialsState.suppliers;
        console.log('Requisition Modal: Using pre-loaded', suppliers.length, 'suppliers');
      } else {
        try {
          suppliers = await MaterialsAPI.getSuppliers();
          materialsState.suppliers = suppliers;
          console.log('Requisition Modal: Loaded', suppliers.length, 'suppliers');
        } catch (e) {
          console.error('Requisition Modal: Failed to load suppliers:', e);
          suppliers = [];
        }
      }

      // Get BOM items for selection
      const bomItems = materialsState.bomItems || [];
      const hasBOMItems = bomItems.length > 0;

      const content = `
        <form id="requisition-form-materials" style="display:flex;flex-direction:column;gap:16px;">
          ${hasBOMItems ? `
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Select from BOM (optional)</label>
            <select name="bom_item_id" id="req-bom-select" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
              <option value="">Custom item...</option>
              ${bomItems.map(b => `<option value="${b.id}" data-name="${escapeHtml(b.material_name || '')}" data-quantity="${b.quantity || 1}" data-unit="${escapeHtml(b.unit || 'each')}" data-cost="${b.unit_cost || 0}">${escapeHtml(b.material_name || 'Unknown')} - ${b.quantity} ${escapeHtml(b.unit || 'each')} @ ${formatCurrency(b.unit_cost || 0)}</option>`).join('')}
            </select>
          </div>
          ` : ''}
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Item Name *</label>
            <input type="text" name="item_name" id="req-item-name" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Quantity *</label>
              <input type="number" name="quantity" id="req-quantity" step="0.01" min="0" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Unit</label>
              <input type="text" name="unit" id="req-unit" value="each" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Estimated Cost (£)</label>
            <input type="number" name="estimated_cost" id="req-estimated-cost" step="0.01" min="0" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Supplier Preference (${suppliers.length} available)</label>
            <select name="supplier_id" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
              <option value="">${suppliers.length === 0 ? 'No suppliers found' : 'No preference'}</option>
              ${suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.company_name || s.name || 'Unnamed')}</option>`).join('')}
            </select>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Required Date</label>
            <input type="date" name="required_date" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Notes</label>
            <textarea name="notes" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;"></textarea>
          </div>
        </form>
      `;

      const footer = `
        <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>
        <button type="button" id="pi-modal-submit-materials" style="padding:8px 16px;border:none;border-radius:6px;background:#156349;color:#fff;font-size:0.875rem;font-weight:500;cursor:pointer;">Create Requisition</button>
      `;

      this.create({
        title: 'New Requisition',
        content: content,
        customFooter: footer,
        onCreate: () => {
          // Auto-fill fields when BOM item selected
          $(document).on('change', '#req-bom-select', function() {
            const selectedOption = $(this).find('option:selected');
            const bomId = $(this).val();
            
            if (bomId) {
              const name = selectedOption.data('name');
              const quantity = selectedOption.data('quantity');
              const unit = selectedOption.data('unit');
              const cost = selectedOption.data('cost');
              
              if (name) $('#req-item-name').val(name);
              if (quantity) $('#req-quantity').val(quantity);
              if (unit) $('#req-unit').val(unit);
              if (cost !== undefined && cost !== '') {
                $('#req-estimated-cost').val(cost);
              }
            }
          });
        },
        onSubmit: async (closeModal) => {
          const $form = $('#requisition-form-materials');
          if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
          }

          const data = { job_id: jobId };
          $form.serializeArray().forEach(item => data[item.name] = item.value);

          try {
            await MaterialsAPI.createRequisition(data);
            showToast('Requisition created');
            const bomItem = $('#req-bom-select option:selected').text();
            const itemName = bomItem ? bomItem.split('(')[0]?.trim() : 'Custom item';
            try {
              await API.logActivity(jobId, `Created requisition for: ${itemName} (${data.quantity} ${data.unit}) - ${formatCurrency(data.estimated_cost || 0)}`, 'materials');
            } catch (logErr) {
              console.error('[Activity] Failed to log requisition:', logErr);
            }
            closeModal();
            $(document).off('change', '#req-bom-select');
            await loadPurchasing();
            // Small delay to ensure DB write completes before refreshing
            await new Promise(r => setTimeout(r, 300));
            await loadJob(); // Refresh activity timeline
          } catch (err) {
            showToast(err.message || 'Failed to create requisition', 'error');
          }
        },
        onClose: () => {
          $(document).off('change', '#req-bom-select');
        }
      });
    },

    // Purchase Order Modal
    async purchaseOrder() {
      // Load suppliers with explicit error handling
      let suppliers = [];
      // Try to use pre-loaded suppliers first
      if (materialsState.suppliers && materialsState.suppliers.length > 0) {
        suppliers = materialsState.suppliers;
        console.log('PO Modal: Using pre-loaded', suppliers.length, 'suppliers');
      } else {
        try {
          suppliers = await MaterialsAPI.getSuppliers();
          materialsState.suppliers = suppliers;
          console.log('PO Modal: Loaded', suppliers.length, 'suppliers');
        } catch (e) {
          console.error('PO Modal: Failed to load suppliers:', e);
          suppliers = [];
        }
      }

      // Show ALL requisitions, not just approved (so user can convert any requisition to PO)
      const requisitions = materialsState.requisitions;

      const content = `
        <form id="po-form-materials" style="display:flex;flex-direction:column;gap:16px;">
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Supplier * (${suppliers.length} available)</label>
            <select name="supplier_id" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;"
              ${suppliers.length === 0 ? 'disabled' : ''}>
              <option value="">${suppliers.length === 0 ? 'No suppliers found - create suppliers first' : 'Select supplier...'}</option>
              ${suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.company_name || s.name || 'Unnamed')}</option>`).join('')}
            </select>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Delivery Address</label>
            <textarea name="delivery_address" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;">${escapeHtml(currentData?.site_address || currentData?.address || '')}</textarea>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Delivery Date</label>
              <input type="date" name="delivery_date" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Reference</label>
              <input type="text" name="reference" placeholder="Your reference" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Notes</label>
            <textarea name="notes" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;"></textarea>
          </div>
          ${requisitions.length > 0 ? `
            <div style="display:flex;flex-direction:column;gap:8px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Include Requisitions</label>
              <div style="display:flex;flex-direction:column;gap:4px;max-height:150px;overflow-y:auto;padding:8px;border:1px solid #e5e7eb;border-radius:6px;">
                ${requisitions.map(r => `
                  <label style="display:flex;align-items:center;gap:8px;font-size:0.875rem;cursor:pointer;">
                    <input type="checkbox" name="requisition_ids" value="${r.id}" style="cursor:pointer;">
                    ${escapeHtml(r.item_name || r.material_name)} - ${r.quantity} ${escapeHtml(r.unit || '')} (${r.status || 'draft'})
                  </label>
                `).join('')}
              </div>
            </div>
          ` : ''}
        </form>
      `;

      const footer = `
        <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>
        <button type="button" id="pi-modal-submit-materials" style="padding:8px 16px;border:none;border-radius:6px;background:#156349;color:#fff;font-size:0.875rem;font-weight:500;cursor:pointer;">Create PO</button>
      `;

      this.create({
        title: 'Create Purchase Order',
        content: content,
        customFooter: footer,
        size: 'lg',
        onSubmit: async (closeModal) => {
          const $form = $('#po-form-materials');
          if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
          }

          const data = { job_id: jobId };
          $form.serializeArray().forEach(item => {
            if (item.name === 'requisition_ids') {
              if (!data.requisition_ids) data.requisition_ids = [];
              data.requisition_ids.push(item.value);
            } else {
              data[item.name] = item.value;
            }
          });

          try {
            await MaterialsAPI.createPurchaseOrder(data);
            showToast('Purchase order created');
            const supplierName = $('#po-supplier option:selected').text() || 'Unknown supplier';
            const reqCount = data.requisition_ids?.length || 0;
            try { await API.logActivity(jobId, `Created purchase order from ${reqCount} requisition(s) with ${supplierName}`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
            closeModal();
            await loadPurchasing();
            await loadJobMaterials();
            await new Promise(r => setTimeout(r, 300));
            await loadJob(); // Refresh activity timeline
          } catch (err) {
            showToast(err.message || 'Failed to create PO', 'error');
          }
        }
      });
    },

    // Purchase Order from Requisition - pre-selects the requisition
    async purchaseOrderFromRequisition(req) {
      const suppliers = await MaterialsAPI.getSuppliers().catch(() => []);
      const allReqs = materialsState.requisitions;

      const content = `
        <form id="po-form-materials" style="display:flex;flex-direction:column;gap:16px;">
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Supplier *</label>
            <select name="supplier_id" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
              <option value="">Select supplier...</option>
              ${suppliers.map(s => `<option value="${s.id}" ${req.supplier_id == s.id ? 'selected' : ''}>${escapeHtml(s.company_name || s.name)}</option>`).join('')}
            </select>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Delivery Address</label>
            <textarea name="delivery_address" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;">${escapeHtml(currentData?.site_address || currentData?.address || '')}</textarea>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Delivery Date</label>
              <input type="date" name="delivery_date" value="${req.required_date || ''}" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Reference</label>
              <input type="text" name="reference" value="${escapeHtml(req.item_name || req.material_name || '')}" placeholder="Your reference" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;">Notes</label>
            <textarea name="notes" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;">${escapeHtml(req.notes || '')}</textarea>
          </div>
          ${allReqs.length > 0 ? `
            <div style="display:flex;flex-direction:column;gap:8px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Include Additional Requisitions</label>
              <div style="display:flex;flex-direction:column;gap:4px;max-height:150px;overflow-y:auto;padding:8px;border:1px solid #e5e7eb;border-radius:6px;">
                ${allReqs.map(r => `
                  <label style="display:flex;align-items:center;gap:8px;font-size:0.875rem;cursor:pointer;">
                    <input type="checkbox" name="requisition_ids" value="${r.id}" style="cursor:pointer;" ${r.id === req.id ? 'checked' : ''}>
                    ${escapeHtml(r.item_name || r.material_name)} - ${r.quantity} ${escapeHtml(r.unit || '')} (${r.status || 'draft'})
                  </label>
                `).join('')}
              </div>
            </div>
          ` : ''}
        </form>
      `;

      const footer = `
        <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>
        <button type="button" id="pi-modal-submit-materials" style="padding:8px 16px;border:none;border-radius:6px;background:#156349;color:#fff;font-size:0.875rem;font-weight:500;cursor:pointer;">Create PO</button>
      `;

      this.create({
        title: 'Create Purchase Order',
        content: content,
        customFooter: footer,
        size: 'lg',
        onSubmit: async (closeModal) => {
          const $form = $('#po-form-materials');
          if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
          }

          const data = { job_id: jobId };
          $form.serializeArray().forEach(item => {
            if (item.name === 'requisition_ids') {
              if (!data.requisition_ids) data.requisition_ids = [];
              data.requisition_ids.push(item.value);
            } else {
              data[item.name] = item.value;
            }
          });

          try {
            await MaterialsAPI.createPurchaseOrder(data);
            showToast('Purchase order created');
            const supplierName = $('#po-form-materials [name="supplier_id"] option:selected').text() || 'Unknown supplier';
            const reqCount = data.requisition_ids?.length || 0;
            try { await API.logActivity(jobId, `Created purchase order from ${reqCount} requisition(s) with ${supplierName}`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
            closeModal();
            await loadPurchasing();
            await loadJobMaterials();
            await new Promise(r => setTimeout(r, 300));
            await loadJob(); // Refresh activity timeline
          } catch (err) {
            showToast(err.message || 'Failed to create PO', 'error');
          }
        }
      });
    },

    // Waste Transfer Note Modal
    async wasteNote() {
      const suppliers = await MaterialsAPI.getSuppliers().catch(() => []);
      const wtnNumber = `WTN-${new Date().getFullYear()}-${Math.floor(Math.random() * 90000) + 10000}`;

        const content = `
          <form id="waste-form-materials" style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">WTN Number</label>
                <input type="text" name="wtn_number" value="${wtnNumber}" readonly style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;background:#f3f4f6;">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Collection Date *</label>
                <input type="date" name="collection_date" value="${new Date().toISOString().split('T')[0]}" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Waste Type *</label>
              <select name="waste_type" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                <option value="">Select waste type...</option>
                <option value="Concrete">Concrete</option>
                <option value="Bricks">Bricks</option>
                <option value="Tiles">Tiles and ceramics</option>
                <option value="Mixed">Mixed construction waste</option>
                <option value="Wood">Wood</option>
                <option value="Metal">Metal scrap</option>
                <option value="Insulation">Insulation materials</option>
                <option value="General">General waste</option>
              </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">EWC Code *</label>
              <select name="ewc_code" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                <option value="">Select EWC Code...</option>
                <option value="17-01-01">17-01-01 - Concrete</option>
                <option value="17-01-02">17-01-02 - Bricks</option>
                <option value="17-01-03">17-01-03 - Tiles and ceramics</option>
                <option value="17-01-07">17-01-07 - Mixed construction waste</option>
                <option value="17-02-01">17-02-01 - Wood</option>
                <option value="17-04-09">17-04-09 - Metal scrap</option>
                <option value="17-06-04">17-06-04 - Insulation materials</option>
                <option value="20-01-35">20-01-35 - Mixed municipal waste</option>
              </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Quantity *</label>
                <input type="number" name="quantity" step="0.01" min="0" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Unit</label>
                <select name="unit" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                  <option value="tonnes">Tonnes</option>
                  <option value="kg">Kilograms</option>
                  <option value="m3">Cubic Metres</option>
                  <option value="litres">Litres</option>
                </select>
              </div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:0.875rem;cursor:pointer;">
              <input type="checkbox" name="hazardous" value="1" style="cursor:pointer;">
              <span>Hazardous Waste</span>
            </label>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Carrier Name</label>
              <input type="text" name="carrier_name" placeholder="e.g. Waste Solutions Ltd" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Disposal Site</label>
              <input type="text" name="disposal_site" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:0.875rem;cursor:pointer;">
              <input type="checkbox" name="diverted" value="1" checked style="cursor:pointer;">
              <span>Diverted from landfill</span>
            </label>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Notes</label>
              <textarea name="notes" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;"></textarea>
            </div>
          </form>
        `;

        const footer = `
          <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>
          <button type="button" id="pi-modal-submit-materials" style="padding:8px 16px;border:none;border-radius:6px;background:#156349;color:#fff;font-size:0.875rem;font-weight:500;cursor:pointer;">Create WTN</button>
        `;

        this.create({
          title: 'Create Waste Transfer Note',
          content: content,
          customFooter: footer,
          size: 'lg',
          onSubmit: async (closeModal) => {
            const $form = $('#waste-form-materials');
            if (!$form[0].checkValidity()) {
              $form[0].reportValidity();
              return;
            }

            const data = {};
            $form.serializeArray().forEach(item => {
              if (item.name === 'hazardous' || item.name === 'diverted') {
                data[item.name] = true;
              } else {
                data[item.name] = item.value;
              }
            });

            try {
              await MaterialsAPI.createWasteNote(data);
              showToast('Waste Transfer Note created');
              try { await API.logActivity(jobId, `Created waste transfer note: ${data.waste_type} - ${data.quantity} ${data.unit}`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
              closeModal();
              await loadWaste();
              await loadJobMaterials();
              await new Promise(r => setTimeout(r, 300));
              await loadJob(); // Refresh activity timeline
            } catch (err) {
              showToast(err.message || 'Failed to create WTN', 'error');
            }
          }
        });
      },

    // Reserve Stock Modal
    async reserveStock() {
      const materials = await MaterialsAPI.getMaterials().catch(() => []);

        const content = `
          <form id="reserve-stock-form-materials" style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Material *</label>
              <select name="material_id" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                <option value="">Select material...</option>
                ${materials.map(m => `<option value="${m.id}">${escapeHtml(m.name)} (${escapeHtml(m.sku || 'No SKU')})</option>`).join('')}
              </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Quantity to Reserve *</label>
              <input type="number" name="quantity" step="0.01" min="0.01" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Unit</label>
              <input type="text" name="unit" value="each" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Notes</label>
              <textarea name="notes" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;"></textarea>
            </div>
          </form>
        `;

        const footer = `
          <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>
          <button type="button" id="pi-modal-submit-materials" style="padding:8px 16px;border:none;border-radius:6px;background:#156349;color:#fff;font-size:0.875rem;font-weight:500;cursor:pointer;">Reserve Stock</button>
        `;

        this.create({
          title: 'Reserve Stock',
          content: content,
          customFooter: footer,
          onSubmit: async (closeModal) => {
            const $form = $('#reserve-stock-form-materials');
            if (!$form[0].checkValidity()) {
              $form[0].reportValidity();
              return;
            }

            const data = {};
            $form.serializeArray().forEach(item => {
              data[item.name] = item.value;
            });

            try {
              await MaterialsAPI.reserveStock(data);
              showToast('Stock reserved successfully');
              const materialName = $('#reserve-stock-form-materials [name="material_id"] option:selected').text().split('(')[0]?.trim() || 'Unknown material';
              try { await API.logActivity(jobId, `Reserved stock: ${materialName} (${data.quantity} ${data.unit})`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
              closeModal();
              await loadStockAllocations();
              await loadJobMaterials();
              await new Promise(r => setTimeout(r, 300));
              await loadJob(); // Refresh activity timeline
            } catch (err) {
              showToast(err.message || 'Failed to reserve stock', 'error');
            }
          }
        });
      },

    // Delivery Modal
    async delivery() {
      const pos = materialsState.purchaseOrders || [];

        const content = `
          <form id="delivery-form-materials" style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Related PO</label>
              <select name="po_id" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                <option value="">No PO (Manual Entry)</option>
                ${pos.map(p => `<option value="${p.id}">${escapeHtml(p.po_number || p.reference || p.id)} - ${escapeHtml(p.supplier_name || 'Unknown')}</option>`).join('')}
              </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Delivery Date *</label>
                <input type="date" name="delivery_date" value="${new Date().toISOString().split('T')[0]}" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.875rem;font-weight:500;color:#374151;">Number of Items</label>
                <input type="number" name="item_count" value="1" min="1" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Supplier Name</label>
              <input type="text" name="supplier_name" placeholder="e.g. Builders Supply Ltd" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Status</label>
              <select name="status" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;">
                <option value="scheduled">Scheduled</option>
                <option value="in_transit">In Transit</option>
                <option value="delivered" selected>Delivered</option>
                <option value="delayed">Delayed</option>
              </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.875rem;font-weight:500;color:#374151;">Notes</label>
              <textarea name="notes" rows="2" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;"></textarea>
            </div>
          </form>
        `;

        const footer = `
          <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Cancel</button>
          <button type="button" id="pi-modal-submit-materials" style="padding:8px 16px;border:none;border-radius:6px;background:#156349;color:#fff;font-size:0.875rem;font-weight:500;cursor:pointer;">Record Delivery</button>
        `;

        this.create({
          title: 'Record Delivery',
          content: content,
          customFooter: footer,
          size: 'md',
          onSubmit: async (closeModal) => {
            const $form = $('#delivery-form-materials');
            if (!$form[0].checkValidity()) {
              $form[0].reportValidity();
              return;
            }

            const data = {};
            $form.serializeArray().forEach(item => {
              data[item.name] = item.value;
            });

            try {
              await MaterialsAPI.createDelivery(data);
              showToast('Delivery recorded');
              const poNumber = $('#delivery-po-select option:selected').text() || 'Unknown PO';
              try { await API.logActivity(jobId, `Recorded delivery for ${poNumber} - Quantity: ${data.quantity}`, 'materials'); } catch(e) { console.error('[Activity] Failed:', e); }
              closeModal();
              await loadPurchasing();
              await loadJobMaterials();
              await new Promise(r => setTimeout(r, 300));
              await loadJob(); // Refresh activity timeline
            } catch (err) {
              showToast(err.message || 'Failed to record delivery', 'error');
            }
          }
        });
      },

    // View Purchase Order Modal (read-only)
    async viewPurchaseOrder(po) {
      // Parse requisition IDs if stored as JSON
      let linkedReqIds = [];
      try {
        linkedReqIds = typeof po.requisition_ids === 'string' ? JSON.parse(po.requisition_ids) : (po.requisition_ids || []);
      } catch (e) {
        linkedReqIds = [];
      }

      // Get linked requisition details
      const linkedReqs = materialsState.requisitions.filter(r => linkedReqIds.includes(r.id));

        const content = `
          <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">PO Number</label>
                <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;font-weight:500;">${escapeHtml(po.po_number || po.id)}</div>
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">Status</label>
                <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;">
                  <span class="pi-status-badge pi-status-${(po.status || 'draft').toLowerCase()}">${escapeHtml(po.status || 'Draft')}</span>
                </div>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
              <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">Supplier</label>
              <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;">${escapeHtml(po.supplier_name || 'Unknown')}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">Total</label>
                <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;font-weight:600;">${formatCurrency(po.total || 0)}</div>
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">Delivery Date</label>
                <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;">${formatDate(po.delivery_date)}</div>
              </div>
            </div>
            ${po.reference ? `
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">Reference</label>
                <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;">${escapeHtml(po.reference)}</div>
              </div>
            ` : ''}
            ${linkedReqs.length > 0 ? `
              <div style="display:flex;flex-direction:column;gap:8px;">
                <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">Linked Requisitions (${linkedReqs.length})</label>
                <div style="display:flex;flex-direction:column;gap:4px;">
                  ${linkedReqs.map(r => `
                    <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;">
                      <strong>${escapeHtml(r.item_name || r.material_name || 'Unknown')}</strong> - ${r.quantity} ${escapeHtml(r.unit || 'each')} @ ${formatCurrency(r.estimated_cost || r.unit_cost || 0)}
                    </div>
                  `).join('')}
                </div>
              </div>
            ` : ''}
            ${po.notes ? `
              <div style="display:flex;flex-direction:column;gap:4px;">
                <label style="font-size:0.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;">Notes</label>
                <div style="padding:8px 12px;background:#f3f4f6;border-radius:6px;font-size:0.875rem;white-space:pre-wrap;">${escapeHtml(po.notes)}</div>
              </div>
            ` : ''}
          </div>
        `;

        const footer = `
          <button type="button" id="pi-modal-cancel-materials" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;font-size:0.875rem;font-weight:500;cursor:pointer;">Close</button>
        `;

        this.create({
          title: 'Purchase Order Details',
          content: content,
          customFooter: footer,
          size: 'md'
        });
      }
    };

    // ============================================
    // EVENT HANDLERS
    // ============================================

    // Main Tab switching (header navigation) - Full-page tabs outside overview layout
    $(document).on('click', '.pi-job-main-tab', function() {
      const tab = $(this).data('main-tab');
      console.log('Main tab clicked:', tab);

      // Update tab buttons
      $('.pi-job-main-tab').removeClass('active').attr('aria-selected', 'false');
      $(this).addClass('active').attr('aria-selected', 'true');

      if (tab === 'overview') {
        // Show overview layout and nested tabs
        $('.pi-kpi-grid').removeClass('hidden');
        $('.pi-overview-layout').removeClass('hidden');
        // Show the overview tab panel, hide full-page tabs
        $('#tab-overview').addClass('active');
        $('#job-tab-overview').addClass('active');
        $('#job-tab-costs, #job-tab-materials, #job-tab-schedule, #job-tab-client').removeClass('active');
      } else {
        // Hide overview layout
        $('.pi-kpi-grid').addClass('hidden');
        $('.pi-overview-layout').addClass('hidden');
        // Hide overview tab
        $('#tab-overview').removeClass('active');
        $('#job-tab-overview').removeClass('active');
        // Show the selected full-page tab
        $('#job-tab-costs, #job-tab-materials, #job-tab-schedule, #job-tab-client').removeClass('active');
        $('#job-tab-' + tab).addClass('active');
        console.log('Panel activated:', tab);
      }

      // Load data for specific tabs
      if (tab === 'costs') {
        loadJobCosts();
      } else if (tab === 'materials') {
        loadJobMaterials();
      } else if (tab === 'schedule') {
        loadScheduleEvents?.();
      } else if (tab === 'client') {
        // Ensure client form has current data
        if (currentData) {
          $('.job-customer-name').val(currentData.customer_name || '');
          $('.job-site-address').val(currentData.site_address || '');
        }
      }
    });

    // Tab switching (old nested tabs - kept for overview layout)
    $('.pi-tab-btn').on('click', function() {
      const tab = $(this).data('tab');
      $('.pi-tab-btn').removeClass('active').attr('aria-selected', 'false');
      $(this).addClass('active').attr('aria-selected', 'true');
      // Only affect tab panels within the overview layout, not the full-page tabs
      $('.pi-overview-layout .pi-tab-panel').removeClass('active');
      $('#job-tab-' + tab).addClass('active');

      if (tab === 'costs') {
        loadJobCosts();
      } else if (tab === 'materials') {
        loadJobMaterials();
      }
    });

    // Materials sub-tab switching
    $(document).on('click', '.pi-materials-subnav-btn', function() {
      const subtab = $(this).data('materials-subtab');
      $('.pi-materials-subnav-btn').removeClass('active');
      $(this).addClass('active');
      $('.pi-materials-subtab-content').removeClass('active');
      $('#materials-subtab-' + subtab).addClass('active');
      loadMaterialsSubtab(subtab);
    });

    // Materials purchasing sub-tabs
    $(document).on('click', '.pi-materials-purchasing-tab', function() {
      const tab = $(this).data('purchasing-tab');
      materialsState.purchasingTab = tab;
      $('.pi-materials-purchasing-tab').removeClass('active');
      $(this).addClass('active');
      $('.pi-materials-purchasing-panel').removeClass('active');
      $('#purchasing-tab-' + tab).addClass('active');
      loadPurchasing();
    });

    // Materials quick action buttons
    $(document).on('click', '#pi-add-bom-item-btn, #pi-add-first-bom-btn', () => MaterialsModals.bomItem());
    $(document).on('click', '#pi-import-bom-btn', () => showToast('Import functionality coming soon', 'info'));
    $(document).on('click', '#pi-new-requisition-btn, #pi-first-requisition-btn', () => MaterialsModals.requisition());
    $(document).on('click', '#pi-create-po-btn', () => MaterialsModals.purchaseOrder());
    $(document).on('click', '#pi-create-wtn-btn, #pi-create-first-wtn-btn', () => MaterialsModals.wasteNote());
    $(document).on('click', '#pi-reserve-stock-btn, #pi-reserve-first-stock-btn', () => MaterialsModals.reserveStock());
    $(document).on('click', '#pi-new-delivery-btn, #pi-create-first-delivery-btn', () => MaterialsModals.delivery());

    // Materials FAB (mobile)
    $(document).on('click', '#pi-materials-fab-toggle', function() {
      $('#pi-materials-fab').toggleClass('open');
    });

    $(document).on('click', '.pi-materials-fab-item', function() {
      const action = $(this).data('action');
      $('#pi-materials-fab').removeClass('open');
      switch (action) {
        case 'bom-item':
          MaterialsModals.bomItem();
          break;
        case 'requisition':
          MaterialsModals.requisition();
          break;
        case 'waste':
          MaterialsModals.wasteNote();
          break;
        case 'delivery':
          MaterialsModals.delivery();
          break;
      }
    });

    // Close FAB when clicking outside
    $(document).on('click', function(e) {
      if (!$(e.target).closest('#pi-materials-fab').length) {
        $('#pi-materials-fab').removeClass('open');
      }
    });

    // Quick action buttons
    $('#pi-add-expense-btn').on('click', () => Modals.expense());
    $('#pi-log-mileage-btn').on('click', () => Modals.mileage());
    $('#pi-add-subcontractor-btn').on('click', () => {
      window.open('/workspace/expenses?tab=suppliers', '_blank');
    });

    // Expense filters
    $('#pi-expense-search').on('input', debounce(() => {
      const term = $('#pi-expense-search').val().toLowerCase();
      $('#pi-job-expenses-container tbody tr').each(function() {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(term));
      });
    }, 300));

    $('#pi-expense-category').on('change', function() {
      const category = $(this).val();
      $('#pi-job-expenses-container tbody tr').each(function() {
        if (!category) { $(this).show(); return; }
        const rowCategory = $(this).find('td:nth-child(2)').text().toLowerCase();
        $(this).toggle(rowCategory.includes(category.toLowerCase()));
      });
    });

    $('#pi-expense-status').on('change', function() {
      const status = $(this).val();
      $('#pi-job-expenses-container tbody tr').each(function() {
        if (!status) { $(this).show(); return; }
        const rowStatus = $(this).find('select.pi-status-select').val();
        $(this).toggle(rowStatus === status);
      });
    });

    // Edit expense
    $(document).on('click', '.edit-expense', async function() {
      const id = $(this).data('id');
      const expense = costsData?.expenses?.find(e => e.id == id);
      if (expense) await Modals.expense(expense);
    });

    // Delete expense
    $(document).on('click', '.delete-expense', function() {
      const id = $(this).data('id');
      const expense = costsData?.expenses?.find(e => e.id == id);
      if (expense) {
        Modals.confirmDelete('expense', expense.description || 'Expense', async () => {
          try {
            await API.deleteExpense(id);
            showToast('Expense deleted');
            try { await API.logActivity(jobId, `Deleted expense: ${expense.description} - ${formatCurrency(expense.amount)}`, 'expenses'); } catch(e) { console.error('[Activity] Failed:', e); }
            await loadJobCosts();
            await new Promise(r => setTimeout(r, 300));
            await loadJob(); // Refresh activity timeline
          } catch (err) {
            showToast('Failed to delete expense', 'error');
          }
        });
      }
    });

    // Status change inline
    $(document).on('change', '.pi-status-select', async function() {
      const id = $(this).data('expense-id');
      const newStatus = $(this).val();
      try {
        await API.updateExpense(id, { approval_status: newStatus });
        showToast('Status updated');
        $(this).attr('class', `pi-status-badge pi-status-${newStatus} pi-status-select`);
        await loadJobCosts();
      } catch (err) {
        showToast('Failed to update status', 'error');
      }
    });

    // Show more activity entries
    $(document).on('click', '#show-more-activity-btn', function() {
      const $hidden = $('#hidden-activity-entries');
      const $btn = $(this);
      if ($hidden.is(':visible')) {
        $hidden.hide();
        $btn.find('span').text($btn.find('span').text().replace('Show less', 'Show').replace('Hide', 'Show'));
        $btn.find('svg').css('transform', 'rotate(0deg)');
      } else {
        $hidden.show();
        $btn.find('span').text('Show less');
        $btn.find('svg').css('transform', 'rotate(180deg)');
      }
    });

    // Add cost code
    $('#pi-add-cost-code-btn').on('click', () => {
      window.open('/workspace/expenses?tab=jobcosting&job_id=' + jobId, '_blank');
    });

    // Debounce helper
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => { clearTimeout(timeout); func(...args); };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // Job updates
    $('#job-update-status').on('click', async function() {
      const status = $('#job-status').val();
      try {
        const resp = await fetch(endpoint + '/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ status })
        });
        if (!resp.ok) throw new Error('Update failed');
        showToast('Status updated');
        loadJob();
      } catch (err) {
        showToast('Failed to update status', 'error');
      }
    });

    $('#pi-job-notes-form').on('submit', async function(e) {
      e.preventDefault();
      const notes = $(this).find('textarea').val();
      try {
        const resp = await fetch(endpoint + '/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ notes })
        });
        if (!resp.ok) throw new Error('Update failed');
        showToast('Notes saved');
        loadJob();
      } catch (err) {
        showToast('Failed to save notes', 'error');
      }
    });

    // Initial load
    loadJob();

    // Handle initial active tab state - Full-page tabs outside overview layout
    const activeMainTab = $('.pi-job-main-tab.active').data('main-tab');
    console.log('Initial active tab:', activeMainTab);

    if (activeMainTab && activeMainTab !== 'overview') {
      // Hide overview elements
      $('.pi-kpi-grid').addClass('hidden');
      $('.pi-overview-layout').addClass('hidden');
      $('#tab-overview').removeClass('active');

      // Hide all full-page tabs first
      $('#job-tab-costs, #job-tab-materials, #job-tab-schedule, #job-tab-client').removeClass('active');

      // Show the selected tab panel
      $('#job-tab-' + activeMainTab).addClass('active');

      // Load data for the active tab
      if (activeMainTab === 'costs') {
        loadJobCosts();
      } else if (activeMainTab === 'materials') {
        loadJobMaterials();
      } else if (activeMainTab === 'schedule') {
        loadScheduleEvents?.();
      } else if (activeMainTab === 'client') {
        // Ensure client form has current data
        if (currentData) {
          $('.job-customer-name').val(currentData.customer_name || '');
          $('.job-site-address').val(currentData.site_address || '');
        }
      }
    } else {
      // Show overview layout for overview tab (default)
      $('.pi-kpi-grid').removeClass('hidden');
      $('.pi-overview-layout').removeClass('hidden');
      $('#tab-overview').addClass('active');
      $('#job-tab-overview').addClass('active');

      // Hide full-page tabs
      $('#job-tab-costs, #job-tab-materials, #job-tab-schedule, #job-tab-client').removeClass('active');
    }

    // Expose functions globally for sidebar navigation
    window.loadJobCosts = loadJobCosts;
    window.loadJobMaterials = loadJobMaterials;
    window.loadScheduleEvents = loadScheduleEvents;
    window.loadJob = loadJob;
  });
