/* global PI_Workspace, Sortable */
/**
 * Planning Index CRM - Professional Pipeline
 * Version 5.0 - Unified CRM Ecosystem
 * Integrated with Invoices, Lead Pages, and Tasks
 */

jQuery(($) => {
  'use strict';

  const endpoint = PI_Workspace.rest_base;
  const nonce = PI_Workspace.nonce;
  const jobsRest = PI_Workspace.jobs_rest || (endpoint + '/jobs');
  const jobSingleBase = PI_Workspace.job_single_base || '/job/';
  const STORAGE_VIEW_KEY = 'pi_workspace_view';

  // 5 stages for leads
  const stages = ['new_lead', 'proposal_sent', 'contacted', 'negotiation', 'won'];
  const stageLabels = {
    new_lead: 'New Lead',
    proposal_sent: 'Proposal Sent',
    contacted: 'Contacted',
    negotiation: 'Negotiation',
    won: 'Won'
  };

  // Job pipeline stages (5 columns to match leads)
  const jobStages = ['planning', 'scheduled', 'in_progress', 'review', 'completed'];
  const jobStageLabels = {
    planning: 'Planning',
    scheduled: 'Scheduled',
    in_progress: 'In Progress',
    review: 'Review',
    completed: 'Completed'
  };

  let workspace = {};
  let invoicesData = [];
  stages.forEach(s => workspace[s] = []);

  let jobWorkspace = {};
  jobStages.forEach(s => jobWorkspace[s] = []);

  let currentView = localStorage.getItem(STORAGE_VIEW_KEY) || 'leads';

  // Column references
  const $cols = {};
  stages.forEach(s => {
    $cols[s] = $(`#pi-workspace-${s}`);
  });
  const $jobCols = {};
  jobStages.forEach(s => {
    $jobCols[s] = $(`#pi-workspace-job-${s}`);
  });

  // === Utility Functions ===
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatCurrency(amount) {
    if (!amount || amount === 0) return null;
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency: 'GBP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  }

  function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    return date.toLocaleDateString('en-GB', {
      day: 'numeric',
      month: 'short',
      year: 'numeric'
    });
  }

  function formatRelativeDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    
    const now = new Date();
    const diffTime = date - now;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays < 0) return { text: `${Math.abs(diffDays)}d overdue`, overdue: true };
    if (diffDays === 0) return { text: 'Due today', overdue: false };
    if (diffDays === 1) return { text: 'Due tomorrow', overdue: false };
    if (diffDays <= 7) return { text: `Due in ${diffDays}d`, overdue: false };
    return { text: formatDate(dateStr), overdue: false };
  }

  // Server-synced tasks cache
  let allTasksCache = [];

  async function loadAllTasks() {
    try {
      const resp = await fetch('/wp-json/pi/v1/tasks', {
        headers: { 'X-WP-Nonce': nonce }
      });
      if (resp.ok) {
        allTasksCache = await resp.json();
      }
    } catch (err) {
      console.warn('Could not load tasks:', err);
      allTasksCache = [];
    }
  }

  function getTasksForLead(leadId) {
    try {
      const leadTasks = allTasksCache.filter(t => t.lead_id === leadId);
      const pending = leadTasks.filter(t => !t.completed).length;
      const highPriority = leadTasks.find(t => !t.completed && t.priority === 'high');
      const medPriority = leadTasks.find(t => !t.completed && t.priority === 'medium');
      return {
        total: leadTasks.length,
        pending: pending,
        priority: highPriority ? 'high' : (medPriority ? 'medium' : (pending > 0 ? 'low' : null))
      };
    } catch (e) {
      return { total: 0, pending: 0, priority: null };
    }
  }

  function getInvoiceForLead(leadId, planningAppId) {
    // Check if this lead has an associated invoice
    const invoice = invoicesData.find(inv => 
      inv.lead_id === planningAppId || inv.lead_id === leadId
    );
    return invoice || null;
  }

  function showToast(message, type = 'success') {
    const icons = {
      success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
      error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
      info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };
    
    const toast = $(`
      <div class="pi-toast ${type}">
        <span class="pi-toast-icon">${icons[type]}</span>
        <span>${escapeHtml(message)}</span>
      </div>
    `);
    $('body').append(toast);
    
    setTimeout(() => {
      toast.fadeOut(300, function() { $(this).remove(); });
    }, 3500);
  }

  // === Card Template ===
  function cardHTML(item) {
    const ref = escapeHtml(item.ref || item.lead_code || '');
    const address = escapeHtml(item.address || item.customer_address || 'Unknown address');
    const date = formatDate(item.date_received);
    const highlighted = !!item.highlighted;
    const leadCode = escapeHtml(item.lead_code || '');
    const leadUrl = leadCode ? `/lead/${leadCode}` : '#';
    const estValue = formatCurrency(item.estimated_value);
    const customerName = escapeHtml(item.customer_name || '');
    const dueDate = item.due_date;
    const hasInvoice = !!item.invoice_generated;
    const planningAppId = item.linked_planning_app_id || item.planning_app_id;
    
    // Get tasks from localStorage
    const tasks = getTasksForLead(item.id);
    const priority = tasks.priority;
    
    // Get invoice data if available
    const invoice = getInvoiceForLead(item.id, planningAppId);
    
    // Format due date
    const dueDateInfo = dueDate ? formatRelativeDate(dueDate) : null;
    
    // Build meta tags
    let metaTags = '';
    
    if (estValue) {
      metaTags += `
        <span class="pi-meta-tag value">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          ${estValue}
        </span>
      `;
    }
    
    if (hasInvoice || invoice) {
      const invoiceAmount = invoice ? formatCurrency(invoice.amount) : '';
      metaTags += `
        <span class="pi-meta-tag invoice" title="Proposal generated">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          ${invoiceAmount ? invoiceAmount : 'Proposal'}
        </span>
      `;
    }
    
    if (tasks.pending > 0) {
      metaTags += `
        <span class="pi-meta-tag tasks">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          ${tasks.pending} task${tasks.pending > 1 ? 's' : ''}
        </span>
      `;
    }
    
    if (dueDateInfo) {
      metaTags += `
        <span class="pi-meta-tag ${dueDateInfo.overdue ? 'overdue' : 'due'}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          ${dueDateInfo.text}
        </span>
      `;
    }
    
    if (customerName) {
      metaTags += `
        <span class="pi-meta-tag contact">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          ${customerName}
        </span>
      `;
    }

    const cardClasses = [
      'pi-workspace-item',
      highlighted ? 'highlighted' : '',
      hasInvoice ? 'has-invoice' : ''
    ].filter(Boolean).join(' ');

    return `
      <div class="${cardClasses}" data-id="${item.id}" data-priority="${priority || ''}" data-planning-app-id="${planningAppId || ''}">
        <div class="pi-card-actions">
          <button class="pi-btn pi-btn-icon pi-qbo-sync" title="Sync to QuickBooks">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </button>
          <button class="pi-btn pi-btn-icon pi-toggle-highlight ${highlighted ? 'active' : ''}" title="${highlighted ? 'Remove highlight' : 'Highlight lead'}"></button>
          <button class="pi-btn pi-btn-icon pi-remove" title="Delete lead"></button>
        </div>
        <div class="pi-card-header">
          <span class="pi-card-ref">${ref || 'New'}</span>
        </div>
        
        <div class="pi-card-body">
          <div class="pi-card-address">${address}</div>
          ${date ? `
            <div class="pi-card-date">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Received ${date}
            </div>
          ` : ''}
        </div>
        
        ${metaTags ? `<div class="pi-card-meta">${metaTags}</div>` : ''}
        
        <div class="pi-card-footer">
          <div class="pi-card-owner">
            <div class="pi-owner-avatar">PI</div>
          </div>
          <a class="pi-view-lead" href="${leadUrl}" title="Open lead details">
            <span>Open</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
              <polyline points="15 3 21 3 21 9"/>
              <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
          </a>
        </div>
      </div>
    `;
  }

  // === Job card template with expense integration ===
  function jobCardHTML(job, stage) {
    stage = stage || job.status || 'planning';
    const code = escapeHtml(job.code || 'JOB');
    const address = escapeHtml(job.site_address || 'No address');
    const customerName = escapeHtml(job.customer_name || '');
    const progress = Math.max(0, Math.min(100, parseInt(job.progress, 10) || 0));
    const startDate = job.start_date ? formatDate(job.start_date) : '';
    const endDate = job.end_date ? formatDate(job.end_date) : '';
    const jobUrl = (job.slug ? (jobSingleBase.replace(/\/$/, '') + '/' + job.slug + '/') : (jobSingleBase + '?p=' + job.id));
    const expensesUrl = `/workspace/expenses?job_id=${job.id}`;
    const endDateRaw = job.end_date || '';
    const isOverdue = endDateRaw && stage !== 'completed' && new Date(endDateRaw) < new Date();
    const hasExpenses = job.total_expenses > 0;
    const highlighted = job.highlighted || 0;

    let cardClasses = 'pi-workspace-item pi-workspace-item-job';
    if (highlighted) cardClasses += ' highlighted';
    if (isOverdue) cardClasses += ' pi-overdue';

    let metaTags = '';
    if (customerName) {
      metaTags += `<span class="pi-meta-tag contact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>${customerName}</span>`;
    }
    if (progress > 0) {
      metaTags += `<span class="pi-meta-tag value">${progress}%</span>`;
    }
    if (hasExpenses) {
      const expenseAmount = formatCurrency(job.total_expenses) || '£0';
      metaTags += `<span class="pi-meta-tag expense" title="View expenses"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>${expenseAmount}</span>`;
    }
    if (endDate) {
      metaTags += `<span class="pi-meta-tag due">${endDate}</span>`;
    }

    return `
      <div class="${cardClasses}" data-id="${job.id}" data-stage="${stage}" data-end-date="${escapeHtml(endDateRaw)}">
        <div class="pi-card-actions">
          <button class="pi-btn pi-btn-icon pi-toggle-highlight ${highlighted ? 'active' : ''}" title="${highlighted ? 'Remove highlight' : 'Highlight job'}"></button>
        </div>
        <div class="pi-card-header">
          <span class="pi-card-ref">${code}</span>
          ${hasExpenses ? `<a href="${expensesUrl}" class="pi-card-expense-link" title="View expenses" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </a>` : ''}
        </div>
        <div class="pi-card-body">
          <div class="pi-card-address">${address}</div>
          ${startDate ? `<div class="pi-card-date"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/></svg>Start ${startDate}</div>` : ''}
        </div>
        ${metaTags ? `<div class="pi-card-meta">${metaTags}</div>` : ''}
        <div class="pi-card-footer">
          <div class="pi-card-owner"><div class="pi-owner-avatar">PI</div></div>
          <a class="pi-view-lead" href="${jobUrl}" title="Open job">
            <span>Open</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
              <polyline points="15 3 21 3 21 9"/>
              <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
          </a>
        </div>
      </div>
    `;
  }

  // === Job expenses cache ===
  let jobExpensesCache = {};

  // === Load job expenses ===
  async function loadJobExpenses(jobId) {
    try {
      const resp = await fetch(`/wp-json/pi/v1/expenses/job-costing?job_id=${jobId}`, {
        headers: { 'X-WP-Nonce': nonce }
      });
      if (resp.ok) {
        const data = await resp.json();
        jobExpensesCache[jobId] = data.total_expenses || 0;
        return data.total_expenses || 0;
      }
    } catch (err) {
      console.warn(`Could not load expenses for job ${jobId}:`, err);
    }
    return 0;
  }

  // === Load all job expenses ===
  async function loadAllJobExpenses() {
    const jobIds = [];
    jobStages.forEach(stage => {
      (jobWorkspace[stage] || []).forEach(job => {
        jobIds.push(job.id);
      });
    });
    
    // Load expenses for all jobs in parallel
    await Promise.all(jobIds.map(id => loadJobExpenses(id)));
  }

  // === Update Counts & Stats - FULL ECOSYSTEM INTEGRATION ===
  // Aggregates totals from VISIBLE leads only (respects filters/search)
  function updateCounts() {
    let totalLeads = 0;
    let totalValue = 0;
    let wonValue = 0;
    let proposalValue = 0;
    let proposalCount = 0;
    
    // Get all visible lead IDs for invoice cross-reference
    const visibleLeadIds = new Set();
    
    // First, aggregate values from VISIBLE leads in workspace
    stages.forEach(s => {
      let visibleCount = 0;
      let stageValue = 0;
      
      // Count only visible items in this stage
      $cols[s].find('.pi-workspace-item').each(function() {
        const $card = $(this);
        // Check if card is visible (not hidden by filter/search)
        if (!$card.hasClass('pi-hidden') && $card.css('display') !== 'none') {
          visibleCount++;
          const itemId = parseInt($card.data('id'));
          visibleLeadIds.add(itemId);
          
          // Find the item in workspace data to get its value
          const item = workspace[s].find(x => x.id === itemId);
          if (item) {
            const val = parseFloat(item.estimated_value) || 0;
            stageValue += val;
            totalValue += val;
            if (s === 'won') wonValue += val;
            if (s === 'proposal_sent') proposalValue += val;
          }
        }
      });
      
      totalLeads += visibleCount;
      $(`[data-stage-count="${s}"]`).text(visibleCount);
      
      // Update column value display
      const $valueEl = $(`[data-stage-value="${s}"]`);
      if (stageValue > 0) {
        $valueEl.text(formatCurrency(stageValue)).show();
      } else {
        $valueEl.hide();
      }
    });
    
    // Cross-reference with invoices data for VISIBLE leads only
    invoicesData.forEach(inv => {
      const amount = parseFloat(inv.amount) || 0;
      const leadId = inv.pi_lead_id || inv.lead_id;
      
      // Only count if this invoice's lead is currently visible
      let isVisible = visibleLeadIds.has(leadId);
      if (!isVisible) {
        // Also check by planning app ID
        for (const stage of stages) {
          const item = workspace[stage].find(x => 
            (x.linked_planning_app_id === inv.lead_id || x.planning_app_id === inv.lead_id) &&
            visibleLeadIds.has(x.id)
          );
          if (item) {
            isVisible = true;
            proposalCount++;
            break;
          }
        }
      }
      
      // Count won invoices toward won value only if visible
      if (isVisible && inv.status === 'won') {
        // Only add if not already counted
        wonValue += amount;
      }
    });
    
    // Update header stats with aggregated values for VISIBLE leads
    $('#pi-total-leads').text(`${totalLeads} lead${totalLeads !== 1 ? 's' : ''}`);
    $('#pi-total-value').text(formatCurrency(totalValue) || '£0');
    $('#pi-won-value').text(formatCurrency(wonValue) || '£0');
    
    // Sync stats to localStorage for cross-page consistency
    const crmStats = {
      totalLeads,
      totalValue,
      wonValue,
      proposalValue,
      proposalCount,
      invoiceCount: invoicesData.length,
      source: 'workspace',
      updatedAt: Date.now()
    };
    localStorage.setItem('pi_crm_stats', JSON.stringify(crmStats));
    
    console.log('[PI Workspace] Stats updated:', crmStats);
  }

  // === Render Pipeline ===
  function render() {
    stages.forEach(s => {
      // Remove existing cards but keep the empty state background
      $cols[s].find('.pi-workspace-item').remove();
      
      // Add empty state background if not already present (always visible behind cards)
      if (!$cols[s].find('.pi-column-empty-bg').length) {
        $cols[s].prepend(`
        `);
      }
      
      // Append cards
      workspace[s].forEach(item => {
        $cols[s].append(cardHTML(item));
      });
    });
    bindCardEvents();
    updateCounts();
  }

  // === Load Invoices Data ===
  async function loadInvoices() {
    try {
      const resp = await fetch('/wp-json/pi/v1/workspace/invoices', {
        headers: { 'X-WP-Nonce': nonce }
      });
      if (resp.ok) {
        invoicesData = await resp.json();
      }
    } catch (err) {
      console.warn('Could not load invoices:', err);
      invoicesData = [];
    }
  }

  // === Jobs pipeline: load ===
  async function loadJobs() {
    try {
      const response = await fetch(jobsRest, { headers: { 'X-WP-Nonce': nonce } });
      if (!response.ok) throw new Error('Failed to load jobs');
      const data = await response.json();
      jobStages.forEach(s => { jobWorkspace[s] = Array.isArray(data[s]) ? data[s] : []; });
      renderJobs();
      setupJobSortable();
    } catch (err) {
      console.error('Jobs load failed:', err);
      showToast('Failed to load jobs pipeline', 'error');
    }
  }

  // === Jobs pipeline: render ===
  function renderJobs() {
    jobStages.forEach(s => {
      $jobCols[s].find('.pi-workspace-item').remove();
      (jobWorkspace[s] || []).forEach(job => {
        $jobCols[s].append(jobCardHTML(job, s));
      });
    });
    updateJobCounts();
    bindJobCardEvents();
  }

  // === Bind Job Card Events (highlight only) ===
  function bindJobCardEvents() {
    // Toggle highlight
    $('#pi-workspace-board-jobs .pi-toggle-highlight').off('click').on('click', async function(e) {
      e.stopPropagation();
      e.preventDefault();
      const $card = $(this).closest('.pi-workspace-item');
      const id = parseInt($card.data('id'), 10);
      const $btn = $(this);

      const currentHighlighted = $card.hasClass('highlighted') ? 1 : 0;
      const newHighlighted = currentHighlighted ? 0 : 1;

      // Optimistic UI update
      $card.toggleClass('highlighted', newHighlighted);
      $btn.toggleClass('active', newHighlighted);
      $btn.attr('title', newHighlighted ? 'Remove highlight' : 'Highlight job');

      // Update job data in workspace
      for (const js of jobStages) {
        const job = (jobWorkspace[js] || []).find(x => x.id === id);
        if (job) {
          job.highlighted = newHighlighted;
          break;
        }
      }

      // Save to server
      try {
        await fetch(`${jobsRest}/${id}/update`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ highlighted: newHighlighted })
        });
        showToast(newHighlighted ? 'Job highlighted' : 'Highlight removed');
      } catch (err) {
        console.error('Highlight update failed:', err);
        showToast('Failed to update highlight', 'error');
      }
    });
  }

  function updateJobCounts() {
    let total = 0;
    jobStages.forEach(s => {
      const count = $jobCols[s].find('.pi-workspace-item:not(.pi-hidden)').length;
      total += count;
      $(`#pi-workspace-board-jobs [data-stage-count="${s}"]`).text(count);
    });
    $('#pi-total-leads').text(total + ' job' + (total !== 1 ? 's' : ''));
  }

  // === Jobs pipeline: save ===
  async function saveJobs() {
    try {
      await fetch(jobsRest + '/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({ workspace: jobWorkspace })
      });
    } catch (err) {
      console.error('Save jobs failed:', err);
      showToast('Failed to save jobs', 'error');
    }
  }

  // === Jobs pipeline: sortable ===
  function setupJobSortable() {
    jobStages.forEach(s => {
      const el = $jobCols[s][0];
      if (!el || el._sortable) return;
      el._sortable = new Sortable(el, {
        group: { name: 'jobs-pipeline', pull: true, put: true },
        animation: 180,
        ghostClass: 'pi-workspace-ghost',
        chosenClass: 'pi-workspace-chosen',
        dragClass: 'pi-workspace-drag',
        forceFallback: true,
        fallbackTolerance: 3,
        handle: '.pi-workspace-item',
        filter: '.pi-empty-state, .pi-btn, a',
        onStart: function() {
          document.body.style.cursor = 'grabbing';
          document.body.style.userSelect = 'none';
        },
        onEnd: async (evt) => {
          document.body.style.cursor = '';
          document.body.style.userSelect = '';
          const itemEl = evt.item;
          const id = parseInt(itemEl.dataset.id, 10);
          let newStage = null;
          for (const js of jobStages) {
            if ($jobCols[js][0] && $jobCols[js][0].contains(itemEl)) { newStage = js; break; }
          }
          if (!newStage) return;
          let moved = null;
          for (const js of jobStages) {
            const idx = (jobWorkspace[js] || []).findIndex(x => x.id === id);
            if (idx !== -1) {
              moved = jobWorkspace[js].splice(idx, 1)[0];
              break;
            }
          }
          if (moved) {
            const siblings = Array.from($jobCols[newStage][0].querySelectorAll('.pi-workspace-item'));
            const newIdx = siblings.findIndex(n => parseInt(n.dataset.id, 10) === id);
            (jobWorkspace[newStage] = jobWorkspace[newStage] || []).splice(Math.max(0, newIdx), 0, moved);
            await saveJobs();
            updateJobCounts();
            showToast('Moved to ' + (jobStageLabels[newStage] || newStage));
          }
        }
      });
    });
  }

  // === Load Data ===
  async function load() {
    try {
      // Load invoices and tasks first for integration
      await Promise.all([loadInvoices(), loadAllTasks()]);
      
      const response = await fetch(endpoint, {
        headers: { 'X-WP-Nonce': nonce }
      });
      
      if (!response.ok) throw new Error('Failed to load pipeline');
      
      const data = await response.json();
      
      // Initialize with empty arrays for all stages
      workspace = {};
      stages.forEach(s => workspace[s] = []);
      
      // Handle old format migration
      if (data.possible) workspace.new_lead = data.possible;
      if (data.letter) workspace.proposal_sent = data.letter;
      if (data.finalised) workspace.won = data.finalised;
      
      // Use new format if available
      stages.forEach(s => {
        if (data[s]) workspace[s] = data[s];
      });

      // Fetch full lead details for each item
      const fetchPromises = [];
      
      for (const stage of stages) {
        for (let i = 0; i < workspace[stage].length; i++) {
          const item = workspace[stage][i];
          fetchPromises.push(
            fetch(`/wp-json/pi/v1/leads/${item.id}`, {
              headers: { 'X-WP-Nonce': nonce }
            })
            .then(resp => resp.ok ? resp.json() : null)
            .then(leadData => {
              if (leadData) {
                workspace[stage][i] = {
                  ...item,
                  address: leadData.customer_address || leadData.address || item.address,
                  ref: leadData.ref || item.ref || '',
                  date_received: leadData.date_received || item.date_received || '',
                  info_url: leadData.info_url || item.info_url || '#',
                  lead_code: leadData.lead_code || item.lead_code || '',
                  highlighted: leadData.highlighted || item.highlighted || 0,
                  estimated_value: leadData.estimated_value || item.estimated_value || 0,
                  due_date: leadData.due_date || item.due_date || '',
                  customer_name: leadData.customer_name || item.customer_name || '',
                  notes: leadData.notes || item.notes || '',
                  invoice_generated: leadData.invoice_generated || false,
                  linked_planning_app_id: leadData.linked_planning_app_id
                };
              }
            })
            .catch(() => {})
          );
        }
      }
      
      await Promise.all(fetchPromises);
      render();
    } catch (err) {
      console.error('Pipeline load failed:', err);
      showToast('Failed to load pipeline', 'error');
    }
  }

  // === Save Pipeline State ===
  async function save() {
    try {
      await fetch(`${endpoint}/save`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce
        },
        body: JSON.stringify({ workspace })
      });
      
      // Dispatch event for other pages to sync
      window.dispatchEvent(new CustomEvent('pi:crm-updated', {
        detail: { workspace, invoices: invoicesData }
      }));
    } catch (err) {
      console.error('Save failed:', err);
      showToast('Failed to save changes', 'error');
    }
  }

  // === Find Item by ID ===
  function findItem(id) {
    for (const stage of stages) {
      const item = workspace[stage].find(x => x.id === id);
      if (item) return { item, stage };
    }
    return null;
  }

  // === Update Lead on Server ===
  async function updateLead(leadId, data) {
    try {
      const resp = await fetch(`/wp-json/pi/v1/leads/${leadId}/update`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce
        },
        body: JSON.stringify(data)
      });
      return resp.ok;
    } catch (err) {
      console.error('Update lead failed:', err);
      return false;
    }
  }

  // === Sync Invoice when Lead Changes ===
  async function syncInvoiceForLead(leadId, data) {
    try {
      await fetch('/wp-json/pi/v1/workspace/invoices/update_for_lead', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce
        },
        body: JSON.stringify({
          lead_id: leadId,
          est: data.estimated_value || 0,
          due: data.due_date || '',
          notes: data.notes || ''
        })
      });
    } catch (err) {
      console.warn('Invoice sync failed:', err);
    }
  }

  // === Bind Card Events ===
  function bindCardEvents() {
    // Remove/Delete lead - ALSO DELETES LINKED INVOICE
    $('.pi-remove').off('click').on('click', async function(e) {
      e.stopPropagation();
      e.preventDefault();
      const $card = $(this).closest('.pi-workspace-item');
      const id = parseInt($card.data('id'), 10);
      
      if (!confirm('Are you sure you want to delete this lead? This will also delete any linked proposal. This action cannot be undone.')) return;
      
      try {
        const resp = await fetch(`${endpoint}/remove`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
          },
          body: JSON.stringify({ post_id: id })
        });
        
        const result = await resp.json();
        
        stages.forEach(s => {
          workspace[s] = workspace[s].filter(x => x.id !== id);
        });
        
        // Also remove from local invoices cache
        invoicesData = invoicesData.filter(inv => 
          inv.pi_lead_id !== id && inv.lead_id !== id
        );
        
        $card.slideUp(200, function() {
          $(this).remove();
          updateCounts();
        });
        
        await save();
        
        // Dispatch event for invoices page cross-tab sync
        const eventData = {
          type: 'lead-deleted',
          leadId: id,
          invoiceDeleted: result.invoice_deleted || false,
          deletedInvoiceId: result.deleted_invoice_id || null,
          timestamp: Date.now()
        };
        localStorage.setItem('pi_crm_last_update', JSON.stringify(eventData));
        
        if (result.invoice_deleted) {
          showToast('Lead and linked proposal deleted');
        } else {
          showToast('Lead deleted');
        }
      } catch (err) {
        console.error('Delete failed:', err);
        showToast('Failed to delete lead', 'error');
      }
    });

    // Toggle highlight
    $('.pi-toggle-highlight').off('click').on('click', async function(e) {
      e.stopPropagation();
      e.preventDefault();
      const $card = $(this).closest('.pi-workspace-item');
      const $btn = $(this);
      const id = parseInt($card.data('id'), 10);
      
      const found = findItem(id);
      if (!found) return;
      
      const newHighlighted = found.item.highlighted ? 0 : 1;
      found.item.highlighted = newHighlighted;
      
      $card.toggleClass('highlighted', !!newHighlighted);
      $btn.toggleClass('active', !!newHighlighted);
      $btn.find('svg').attr('fill', newHighlighted ? 'currentColor' : 'none');
      
      await updateLead(id, { highlighted: newHighlighted });
      await save();
      
      showToast(newHighlighted ? 'Lead highlighted' : 'Highlight removed');
    });

    // QuickBooks Sync
    $('.pi-qbo-sync').off('click').on('click', async function(e) {
      e.stopPropagation();
      e.preventDefault();
      const $card = $(this).closest('.pi-workspace-item');
      const $btn = $(this);
      const id = parseInt($card.data('id'), 10);
      const planningAppId = $card.data('planning-app-id');
      
      if (!planningAppId) {
        showToast('No planning application linked to this lead', 'error');
        return;
      }
      
      // Show loading state
      $btn.prop('disabled', true).addClass('syncing');
      const originalSvg = $btn.html();
      $btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="20"/></svg>');
      
      try {
        const resp = await fetch('/wp-json/pi/v1/leads/' + id + '/sync-to-qbo', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
          },
          body: JSON.stringify({ planning_app_id: planningAppId })
        });
        
        const result = await resp.json();
        
        if (resp.ok && result.success) {
          showToast('Synced to QuickBooks successfully!', 'success');
          $btn.addClass('synced');
          setTimeout(() => $btn.removeClass('synced'), 3000);
        } else {
          throw new Error(result.message || 'Sync failed');
        }
      } catch (err) {
        console.error('QuickBooks sync failed:', err);
        showToast(err.message || 'Failed to sync to QuickBooks', 'error');
      } finally {
        $btn.prop('disabled', false).removeClass('syncing');
        $btn.html(originalSvg);
      }
    });

    // Prevent link click from triggering drag
    $('.pi-view-lead').off('mousedown').on('mousedown', function(e) {
      e.stopPropagation();
    });
  }

  // === Setup Sortable (Drag & Drop) ===
  function setupSortable() {
    const sortableOpts = {
      group: {
        name: 'pipeline',
        pull: true,
        put: true
      },
      animation: 180,
      ghostClass: 'pi-workspace-ghost',
      chosenClass: 'pi-workspace-chosen',
      dragClass: 'pi-workspace-drag',
      forceFallback: true,
      fallbackTolerance: 3,
      handle: '.pi-workspace-item',
      filter: '.pi-empty-state, .pi-btn, a',
      onStart: function() {
        document.body.style.cursor = 'grabbing';
        document.body.style.userSelect = 'none';
      },
      onEnd: async (evt) => {
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        const itemEl = evt.item;
        const id = parseInt(itemEl.dataset.id, 10);
        
        // Find which column the item is now in
        let newStage = null;
        for (const s of stages) {
          if ($cols[s][0] && $cols[s][0].contains(itemEl)) {
            newStage = s;
            break;
          }
        }
        
        if (!newStage) return;
        
        // Find and remove from old location
        let movedItem = null;
        let oldStage = null;
        for (const s of stages) {
          const idx = workspace[s].findIndex(x => x.id === id);
          if (idx !== -1) {
            movedItem = workspace[s].splice(idx, 1)[0];
            oldStage = s;
            break;
          }
        }
        
        if (!movedItem) {
          movedItem = { id };
        }
        
        // Calculate new index
        const siblings = Array.from($cols[newStage][0].querySelectorAll('.pi-workspace-item'));
        const newIndex = siblings.findIndex(el => parseInt(el.dataset.id, 10) === id);
        
        // Insert at new position
        workspace[newStage].splice(Math.max(0, newIndex), 0, movedItem);
        
        // Update stage in backend
        await updateLead(id, { stage: newStage });
        await save();
        
        updateCounts();
        
        if (oldStage !== newStage) {
          showToast(`Moved to ${stageLabels[newStage]}`);
        }
      }
    };

    stages.forEach(s => {
      if ($cols[s][0]) {
        new Sortable($cols[s][0], sortableOpts);
      }
    });
  }

  // === Search Functionality ===
  function setupSearch() {
    let searchTimeout;
    
    $('#pi-workspace-search').on('input', function() {
      clearTimeout(searchTimeout);
      const query = $(this).val().trim().toLowerCase();
      
      searchTimeout = setTimeout(() => {
        filterPipeline(query);
      }, 150);
    });
    
    $('#pi-workspace-search').on('keydown', function(e) {
      if (e.key === 'Escape') {
        $(this).val('');
        filterPipeline('');
      }
    });
  }

  function filterPipeline(query) {
    const selector = currentView === 'jobs' ? '#pi-workspace-board-jobs .pi-workspace-item' : '#pi-workspace-board .pi-workspace-item';
    const $items = $(selector);

    if (!query) {
      $items.removeClass('pi-hidden').show();
      if (currentView === 'jobs') updateJobCounts(); else updateCounts();
      return;
    }

    $items.each(function() {
      const $card = $(this);
      const searchText = [
        $card.find('.pi-card-ref').text(),
        $card.find('.pi-card-address').text(),
        $card.find('.pi-meta-tag').text()
      ].join(' ').toLowerCase();
      if (searchText.includes(query)) {
        $card.removeClass('pi-hidden').show();
      } else {
        $card.addClass('pi-hidden').hide();
      }
    });

    if (currentView === 'jobs') updateJobCounts(); else updateCounts();
  }

  // === Filter Dropdown ===
  function setupFilter() {
    $('#pi-filter-toggle').on('click', function(e) {
      e.stopPropagation();
      $('#pi-filter-menu').toggleClass('active');
      $('#pi-filter-menu-jobs').removeClass('active');
    });
    $('#pi-filter-toggle-jobs').on('click', function(e) {
      e.stopPropagation();
      $('#pi-filter-menu-jobs').toggleClass('active');
      $('#pi-filter-menu').removeClass('active');
    });

    $(document).on('click', function(e) {
      if (!$(e.target).closest('.pi-filter-dropdown').length) {
        $('#pi-filter-menu').removeClass('active');
        $('#pi-filter-menu-jobs').removeClass('active');
      }
    });

    $('#pi-filter-menu .pi-filter-option').on('click', function() {
      const filter = $(this).data('filter');
      const label = $(this).text();
      $('#pi-filter-menu .pi-filter-option').removeClass('active');
      $(this).addClass('active');
      $('#pi-filter-menu').removeClass('active');
      $('#pi-filter-toggle span').text(filter === 'all' ? 'Filter' : label);
      applyFilter(filter);
    });

    $('#pi-filter-menu-jobs .pi-filter-option').on('click', function() {
      const filter = $(this).data('filter');
      const label = $(this).text();
      $('#pi-filter-menu-jobs .pi-filter-option').removeClass('active');
      $(this).addClass('active');
      $('#pi-filter-menu-jobs').removeClass('active');
      $('#pi-filter-toggle-jobs span').text(filter === 'all' ? 'Filter' : label);
      applyJobFilter(filter);
    });
  }

  function applyJobFilter(filter) {
    const $cards = $('#pi-workspace-board-jobs .pi-workspace-item');
    $cards.show().removeClass('pi-hidden');
    if (filter !== 'all') {
      $cards.each(function() {
        const $c = $(this);
        const stage = $c.data('stage') || '';
        const endDate = $c.data('end-date') || '';
        const isOverdue = endDate && stage !== 'completed' && new Date(endDate) < new Date();
        let show = false;
        if (filter === 'overdue') show = isOverdue;
        else show = stage === filter;
        if (!show) $c.addClass('pi-hidden').hide();
      });
    }
    updateJobCounts();
    const visible = $('#pi-workspace-board-jobs .pi-workspace-item:not(.pi-hidden)').length;
    if (filter !== 'all') showToast('Showing ' + visible + ' job' + (visible !== 1 ? 's' : ''), 'info');
  }

  function applyFilter(filter) {
    $('.pi-workspace-item').show().removeClass('pi-hidden');
    
    switch (filter) {
      case 'highlighted':
        $('.pi-workspace-item').each(function() {
          if (!$(this).hasClass('highlighted')) {
            $(this).addClass('pi-hidden').hide();
          }
        });
        break;
      case 'with-value':
        $('.pi-workspace-item').each(function() {
          if (!$(this).find('.pi-meta-tag.value').length) {
            $(this).addClass('pi-hidden').hide();
          }
        });
        break;
      case 'with-tasks':
        $('.pi-workspace-item').each(function() {
          if (!$(this).find('.pi-meta-tag.tasks').length) {
            $(this).addClass('pi-hidden').hide();
          }
        });
        break;
      case 'overdue':
        $('.pi-workspace-item').each(function() {
          if (!$(this).find('.pi-meta-tag.overdue').length) {
            $(this).addClass('pi-hidden').hide();
          }
        });
        break;
      case 'with-invoice':
        $('.pi-workspace-item').each(function() {
          if (!$(this).find('.pi-meta-tag.invoice').length) {
            $(this).addClass('pi-hidden').hide();
          }
        });
        break;
      default:
        break;
    }
    
    updateCounts();
    
    const visible = $('.pi-workspace-item:visible').length;
    if (filter !== 'all') {
      showToast(`Showing ${visible} lead${visible !== 1 ? 's' : ''}`, 'info');
    }
  }

  // === View Toggle ===
  function setupViewToggle() {
    $('.pi-view-btn').on('click', function() {
      $('.pi-view-btn').removeClass('active');
      $(this).addClass('active');
      
      const view = $(this).data('view');
      if (view === 'list') {
        showToast('List view coming soon!', 'info');
      }
    });
  }

  // === Keyboard Shortcuts ===
  function setupKeyboardShortcuts() {
    $(document).on('keydown', function(e) {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        $('#pi-workspace-search').focus();
      }
      
      if (e.key === 'Escape') {
        $('#pi-workspace-search').val('').blur();
        filterPipeline('');
        $('#pi-filter-menu').removeClass('active');
        $('#pi-filter-menu-jobs').removeClass('active');
      }
    });
  }

  // === Listen for External Updates - CROSS-PAGE SYNC ===
  window.addEventListener('pi:workspace-updated', async (e) => {
    const data = e.detail;
    if (!data || !data.id) return;
    
    const found = findItem(data.id);
    if (!found) {
      await load();
    }
  });

  // Listen for lead updates from single lead page
  window.addEventListener('pi:lead-updated', async (e) => {
    const { leadId, estimatedValue, planningAppId } = e.detail || {};
    if (!leadId) return;
    
    console.log('[PI Workspace] Received lead-updated event:', { leadId, estimatedValue });
    
    // Update local data with new estimated value
    const found = findItem(parseInt(leadId));
    if (found) {
      found.item.estimated_value = estimatedValue;
      render();
    } else {
      // Lead not found, reload all data
      await load();
    }
  });

  // Listen for invoice creation/sync from single lead page
  window.addEventListener('pi:invoice-created', async (e) => {
    const { invoiceId, leadId, amount } = e.detail || {};
    console.log('[PI Workspace] Received invoice-created event:', { invoiceId, leadId, amount });
    
    // Reload invoices and update display
    await loadInvoices();
    render();
  });
  
  window.addEventListener('pi:invoice-synced', async (e) => {
    const { invoiceId, leadId, amount } = e.detail || {};
    console.log('[PI Workspace] Received invoice-synced event:', { invoiceId, leadId, amount });
    
    // Reload invoices and update display
    await loadInvoices();
    render();
  });

  // Listen for generic CRM updates
  window.addEventListener('pi:crm-update', async (e) => {
    const { type, leadId, estimatedValue, amount } = e.detail || {};
    console.log('[PI Workspace] Received crm-update event:', type, e.detail);
    
    if (type === 'lead-updated' && leadId) {
      const found = findItem(parseInt(leadId));
      if (found && estimatedValue !== undefined) {
        found.item.estimated_value = estimatedValue;
        render();
      }
    } else if (type === 'invoice-created' || type === 'invoice-synced') {
      await loadInvoices();
      render();
    }
  });

  // === Refresh on visibility change ===
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      // Re-render to pick up any localStorage changes
      render();
    }
  });

  // === Storage event for cross-tab sync ===
  window.addEventListener('storage', function(e) {
    // Refresh on task updates
    if (e.key && e.key.startsWith('pi_lead_tasks_')) {
      render();
    }
    
    // Refresh on CRM updates from other tabs
    if (e.key === 'pi_crm_last_update') {
      try {
        const update = JSON.parse(e.newValue || '{}');
        console.log('[PI Workspace] Cross-tab update received:', update);
        
        if (update.type === 'lead-updated') {
          const found = findItem(parseInt(update.leadId));
          if (found && update.estimatedValue !== undefined) {
            found.item.estimated_value = update.estimatedValue;
          }
          render();
        } else if (update.type === 'invoice-created' || update.type === 'invoice-synced') {
          loadInvoices().then(() => render());
        } else if (update.type === 'lead-deleted') {
          // Lead was deleted from another tab, reload the workspace
          console.log('[PI Workspace] Lead deleted in another tab, reloading');
          load();
        }
      } catch (err) {
        console.warn('[PI Workspace] Failed to parse cross-tab update:', err);
      }
    }
  });

  // === Pipeline switch (Leads | Jobs) ===
  function applyView(view) {
    currentView = view;
    localStorage.setItem(STORAGE_VIEW_KEY, view);
    const $wrapper = $('.pi-crm-wrapper');
    $wrapper.removeClass('pi-view-leads pi-view-jobs').addClass('pi-view-' + view);
    $('.pi-pipeline-tab').removeClass('active').attr('aria-selected', 'false');
    $('.pi-pipeline-tab[data-view="' + view + '"]').addClass('active').attr('aria-selected', 'true');
    $('#pi-workspace-search').attr('placeholder', view === 'jobs' ? 'Search jobs... (⌘K)' : 'Search leads... (⌘K)');
    if (view === 'jobs') {
      loadJobs();
      $('.pi-stats-bar').hide();
      $('#pi-filter-dropdown-leads').hide();
      $('#pi-filter-dropdown-jobs').show();
    } else {
      updateCounts();
      $('.pi-stats-bar').show();
      $('#pi-filter-dropdown-leads').show();
      $('#pi-filter-dropdown-jobs').hide();
    }
  }

  function setupPipelineSwitch() {
    $('.pi-pipeline-tab').on('click', function() {
      const view = $(this).data('view');
      if (view === currentView) return;
      applyView(view);
    });
  }

  // === Initialize ===
  async function init() {
    await load();
    setupSortable();
    setupSearch();
    setupFilter();
    setupViewToggle();
    setupKeyboardShortcuts();
    setupPipelineSwitch();

    // Apply saved view (Leads or Jobs) and show correct board
    $('.pi-crm-wrapper').addClass('pi-view-' + currentView);
    $('.pi-pipeline-tab').removeClass('active').attr('aria-selected', 'false');
    $('.pi-pipeline-tab[data-view="' + currentView + '"]').addClass('active').attr('aria-selected', 'true');
    $('#pi-workspace-search').attr('placeholder', currentView === 'jobs' ? 'Search jobs... (⌘K)' : 'Search leads... (⌘K)');
    if (currentView === 'jobs') {
      $('.pi-stats-bar').hide();
      $('#pi-filter-dropdown-leads').hide();
      $('#pi-filter-dropdown-jobs').show();
      await loadJobs();
    } else {
      $('.pi-stats-bar').show();
      $('#pi-filter-dropdown-leads').show();
      $('#pi-filter-dropdown-jobs').hide();
    }
  }

  init();
});
