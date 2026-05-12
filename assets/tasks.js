/**
 * Planning Index - Tasks Page
 * Professional task management for construction leads
 * Version 1.0 - Unified CRM Ecosystem
 */

jQuery(($) => {
    'use strict';

    const endpoint = PI_Tasks.rest_base;
    const nonce = PI_Tasks.nonce;
    const workspaceUrl = PI_Tasks.workspace_url;

    // State
    let allTasks = [];
    let allLeads = [];
    let allJobs = [];
    let currentFilter = 'all';
    let currentSort = 'due';
    let searchQuery = '';
    let selectedTasks = new Set();
    let editingTaskId = null;

    // Priority order for sorting
    const priorityOrder = { high: 0, medium: 1, low: 2 };

    // ===========================================
    // UTILITY FUNCTIONS
    // ===========================================

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatRelativeDate(dateStr) {
        if (!dateStr) return null;
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return null;
        
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        date.setHours(0, 0, 0, 0);
        
        const diffTime = date - now;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 0) return { text: `${Math.abs(diffDays)}d overdue`, overdue: true, days: diffDays };
        if (diffDays === 0) return { text: 'Due today', overdue: false, days: 0 };
        if (diffDays === 1) return { text: 'Due tomorrow', overdue: false, days: 1 };
        if (diffDays <= 7) return { text: `Due in ${diffDays}d`, overdue: false, days: diffDays };
        return { text: formatDate(dateStr), overdue: false, days: diffDays };
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
        $('#pi-toast-container').append(toast);
        
        setTimeout(() => {
            toast.fadeOut(300, function() { $(this).remove(); });
        }, 3500);
    }

    function setButtonLoading($btn, loading) {
        if (loading) {
            $btn.data('original-text', $btn.html());
            $btn.prop('disabled', true).html(`
                <svg class="pi-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;animation:spin 1s linear infinite;">
                    <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32"/>
                </svg>
                Loading...
            `);
        } else {
            $btn.prop('disabled', false).html($btn.data('original-text'));
        }
    }

    // ===========================================
    // DATA LOADING (Server-synced)
    // ===========================================

    async function loadAllTasks() {
        try {
            // Load leads, jobs, and tasks in parallel
            const [leadsResp, jobsResp, tasksResp] = await Promise.all([
                fetch(`${endpoint}/leads`, { headers: { 'X-WP-Nonce': nonce } }),
                fetch(`${endpoint}/jobs`, { headers: { 'X-WP-Nonce': nonce } }),
                fetch(`${endpoint}/tasks`, { headers: { 'X-WP-Nonce': nonce } })
            ]);
            
            if (!leadsResp.ok) throw new Error('Failed to load leads');
            if (!jobsResp.ok) throw new Error('Failed to load jobs');
            if (!tasksResp.ok) throw new Error('Failed to load tasks');
            
            allLeads = await leadsResp.json();
            allJobs = await jobsResp.json();
            const serverTasks = await tasksResp.json();
            
            console.log('[Tasks] Loaded', allJobs.length, 'jobs:', allJobs);
            console.log('[Tasks] Loaded', allLeads.length, 'leads');
            console.log('[Tasks] Loaded', serverTasks.length, 'tasks');
            
            // Create maps for quick lookup
            const leadMap = {};
            allLeads.forEach(lead => {
                leadMap[lead.id] = lead;
            });
            
            const jobMap = {};
            allJobs.forEach(job => {
                jobMap[job.id] = job;
            });
            
            // Enrich tasks with lead and job info
            allTasks = serverTasks.map(task => {
                const lead = leadMap[task.lead_id] || {};
                const job = jobMap[task.job_id] || {};
                return {
                    ...task,
                    lead_code: lead.lead_code || '',
                    lead_address: lead.address || lead.customer_address || '',
                    lead_ref: lead.ref || '',
                    job_code: job.code || '',
                    job_title: job.title || '',
                    job_address: job.address || ''
                };
            });
            
            populateLeadSelect();
            populateJobSelect();
            renderTasks();
            updateStats();
            
        } catch (err) {
            console.error('Failed to load tasks:', err);
            showToast('Failed to load tasks', 'error');
        }
    }

    function populateLeadSelect() {
        const $select = $('#pi-task-lead-select');
        $select.find('option:not(:first)').remove();
        
        allLeads.forEach(lead => {
            const label = lead.lead_code 
                ? `${lead.lead_code} - ${(lead.address || 'Unknown').substring(0, 40)}...`
                : (lead.address || 'Lead #' + lead.id).substring(0, 50);
            $select.append(`<option value="${lead.id}">${escapeHtml(label)}</option>`);
        });
    }

    function populateJobSelect() {
        const $select = $('#pi-task-job-select');
        $select.find('option:not(:first)').remove();
        
        allJobs.forEach(job => {
            const label = job.code 
                ? `${job.code} - ${(job.address || job.title || 'Unknown').substring(0, 40)}`
                : (job.title || 'Job #' + job.id).substring(0, 50);
            $select.append(`<option value="${job.id}">${escapeHtml(label)}</option>`);
        });
    }

    // ===========================================
    // FILTERING & SORTING
    // ===========================================

    function getFilteredTasks() {
        let filtered = [...allTasks];
        
        // Apply filter
        switch (currentFilter) {
            case 'pending':
                filtered = filtered.filter(t => !t.completed);
                break;
            case 'completed':
                filtered = filtered.filter(t => t.completed);
                break;
            case 'overdue':
                filtered = filtered.filter(t => {
                    if (t.completed) return false;
                    const rel = formatRelativeDate(t.due);
                    return rel && rel.overdue;
                });
                break;
            case 'high':
            case 'medium':
            case 'low':
                filtered = filtered.filter(t => t.priority === currentFilter && !t.completed);
                break;
        }
        
        // Apply search
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(t => 
                (t.title || '').toLowerCase().includes(query) ||
                (t.lead_address || '').toLowerCase().includes(query) ||
                (t.lead_code || '').toLowerCase().includes(query) ||
                (t.job_code || '').toLowerCase().includes(query) ||
                (t.job_title || '').toLowerCase().includes(query) ||
                (t.notes || '').toLowerCase().includes(query)
            );
        }
        
        // Apply sort
        filtered.sort((a, b) => {
            switch (currentSort) {
                case 'due':
                    // Tasks without due dates go last
                    if (!a.due && !b.due) return 0;
                    if (!a.due) return 1;
                    if (!b.due) return -1;
                    return new Date(a.due) - new Date(b.due);
                case 'priority':
                    return (priorityOrder[a.priority] || 2) - (priorityOrder[b.priority] || 2);
                case 'created':
                    if (!a.created && !b.created) return 0;
                    if (!a.created) return 1;
                    if (!b.created) return -1;
                    return new Date(b.created) - new Date(a.created);
                case 'lead':
                    return (a.lead_address || '').localeCompare(b.lead_address || '');
                default:
                    return 0;
            }
        });
        
        // Completed tasks always go to bottom
        const pending = filtered.filter(t => !t.completed);
        const completed = filtered.filter(t => t.completed);
        
        return [...pending, ...completed];
    }

    // ===========================================
    // RENDERING
    // ===========================================

    function renderTasks() {
        const tasks = getFilteredTasks();
        const $tbody = $('#pi-tasks-body');
        const $empty = $('#pi-tasks-empty');
        const $table = $('#pi-tasks-table');
        
        $tbody.empty();
        
        if (tasks.length === 0) {
            $table.hide();
            $empty.show();
            return;
        }
        
        $table.show();
        $empty.hide();
        
        tasks.forEach(task => {
            const dueDateInfo = formatRelativeDate(task.due);
            const isOverdue = dueDateInfo && dueDateInfo.overdue && !task.completed;
            const leadUrl = task.lead_code ? `/lead/${task.lead_code}` : '#';
            
            const priorityBadge = {
                high: '<span class="pi-priority-badge high">High</span>',
                medium: '<span class="pi-priority-badge medium">Medium</span>',
                low: '<span class="pi-priority-badge low">Low</span>'
            }[task.priority] || '<span class="pi-priority-badge low">Low</span>';
            
            const statusBadge = task.completed 
                ? '<span class="pi-status-badge completed">Completed</span>'
                : (isOverdue 
                    ? '<span class="pi-status-badge overdue">Overdue</span>'
                    : '<span class="pi-status-badge pending">Pending</span>');
            
            const row = `
                <tr class="pi-task-row ${task.completed ? 'completed' : ''} ${isOverdue ? 'overdue' : ''}" data-task-id="${task.id}" data-lead-id="${task.lead_id}">
                    <td class="pi-td-checkbox">
                        <input type="checkbox" class="pi-task-checkbox" data-task-id="${task.id}" ${selectedTasks.has(task.id) ? 'checked' : ''} />
                    </td>
                    <td class="pi-td-task">
                        <div class="pi-task-cell">
                            <button class="pi-task-complete-btn ${task.completed ? 'completed' : ''}" data-task-id="${task.id}" title="${task.completed ? 'Mark as incomplete' : 'Mark as complete'}">
                                ${task.completed 
                                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>' 
                                    : ''}
                            </button>
                            <div class="pi-task-info">
                                <span class="pi-task-title ${task.completed ? 'completed' : ''}">${escapeHtml(task.title)}</span>
                                ${task.notes ? `<span class="pi-task-notes">${escapeHtml(task.notes.substring(0, 60))}${task.notes.length > 60 ? '...' : ''}</span>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="pi-td-lead">
                        ${task.job_code ? `
                            <div class="pi-job-link">
                                <span class="pi-job-code">${escapeHtml(task.job_code)}</span>
                                <span class="pi-job-address">${escapeHtml((task.job_address || task.job_title || '').substring(0, 30))}${(task.job_address || task.job_title || '').length > 30 ? '...' : ''}</span>
                            </div>
                        ` : task.lead_code ? `
                            <a href="${leadUrl}" class="pi-lead-link">
                                <span class="pi-lead-code">${escapeHtml(task.lead_code)}</span>
                                <span class="pi-lead-address">${escapeHtml((task.lead_address || '').substring(0, 30))}${(task.lead_address || '').length > 30 ? '...' : ''}</span>
                            </a>
                        ` : '<span class="pi-no-lead">No lead/job linked</span>'}
                    </td>
                    <td class="pi-td-priority">
                        ${priorityBadge}
                    </td>
                    <td class="pi-td-due ${isOverdue ? 'overdue' : ''}">
                        ${dueDateInfo ? `
                            <span class="pi-due-text ${dueDateInfo.overdue ? 'overdue' : ''}">${dueDateInfo.text}</span>
                        ` : '<span class="pi-no-due">No due date</span>'}
                    </td>
                    <td class="pi-td-status">
                        ${statusBadge}
                    </td>
                    <td class="pi-td-actions">
                        <button class="pi-action-btn pi-edit-task" data-task-id="${task.id}" title="Edit task">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="pi-action-btn pi-delete-task" data-task-id="${task.id}" title="Delete task">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </td>
                </tr>
            `;
            
            $tbody.append(row);
        });
        
        updateBulkActionsBar();
    }

    function updateStats() {
        const total = allTasks.length;
        const pending = allTasks.filter(t => !t.completed).length;
        const completed = allTasks.filter(t => t.completed).length;
        const overdue = allTasks.filter(t => {
            if (t.completed) return false;
            const rel = formatRelativeDate(t.due);
            return rel && rel.overdue;
        }).length;
        
        $('#pi-total-tasks').text(`${total} task${total !== 1 ? 's' : ''}`);
        $('#pi-pending-count').text(pending);
        $('#pi-completed-count').text(completed);
        $('#pi-overdue-count').text(overdue);
    }

    // ===========================================
    // TASK OPERATIONS (Server-synced)
    // ===========================================

    async function toggleTaskComplete(taskId) {
        const task = allTasks.find(t => t.id === taskId);
        if (!task) return;
        
        const newCompleted = !task.completed;
        
        try {
            const resp = await fetch(`${endpoint}/tasks/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ task_id: taskId, completed: newCompleted })
            });
            
            if (!resp.ok) throw new Error('Failed to update task');
            
            await loadAllTasks();
            showToast(newCompleted ? 'Task completed! 🎉' : 'Task marked as pending', 'success');
        } catch (err) {
            console.error('Failed to toggle task:', err);
            showToast('Failed to update task', 'error');
        }
    }

    async function deleteTask(taskId) {
        try {
            const resp = await fetch(`${endpoint}/tasks/remove`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ task_id: taskId })
            });
            
            if (!resp.ok) throw new Error('Failed to delete task');
            
            await loadAllTasks();
            showToast('Task deleted', 'info');
        } catch (err) {
            console.error('Failed to delete task:', err);
            showToast('Failed to delete task', 'error');
        }
    }

    async function saveTask(formData) {
        const leadId = parseInt(formData.lead_select) || null;
        const jobId = parseInt(formData.job_select) || null;
        
        console.log('[Tasks] Saving task - leadId:', leadId, 'jobId:', jobId);
        
        if (!leadId && !jobId) {
            showToast('Please select a lead or job for this task', 'error');
            return;
        }
        
        const taskData = {
            title: formData.title,
            due: formData.due || null,
            priority: formData.priority || 'medium',
            notes: formData.notes || '',
            lead_id: leadId,
            job_id: jobId
        };
        
        console.log('[Tasks] Task data being sent:', taskData);
        
        try {
            if (editingTaskId) {
                // Update existing task
                const resp = await fetch(`${endpoint}/tasks/update`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ task_id: editingTaskId, ...taskData })
                });
                
                if (!resp.ok) throw new Error('Failed to update task');
            } else {
                // New task
                const resp = await fetch(`${endpoint}/tasks/add`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify(taskData)
                });
                
                if (!resp.ok) throw new Error('Failed to add task');
            }
            
            await loadAllTasks();
            showToast(editingTaskId ? 'Task updated!' : 'Task created!', 'success');
            closeModal();
        } catch (err) {
            console.error('Failed to save task:', err);
            showToast('Failed to save task', 'error');
        }
    }

    // ===========================================
    // BULK OPERATIONS (Server-synced)
    // ===========================================

    function updateBulkActionsBar() {
        const count = selectedTasks.size;
        const $bar = $('#pi-bulk-actions');
        
        if (count > 0) {
            $('#pi-selected-count').text(count);
            $bar.addClass('active');
        } else {
            $bar.removeClass('active');
        }
    }

    async function bulkComplete() {
        const taskIds = Array.from(selectedTasks);
        
        try {
            const resp = await fetch(`${endpoint}/tasks/bulk`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ action: 'complete', task_ids: taskIds })
            });
            
            if (!resp.ok) throw new Error('Failed to complete tasks');
            
            selectedTasks.clear();
            await loadAllTasks();
            showToast('Tasks marked as complete!', 'success');
        } catch (err) {
            console.error('Failed to bulk complete:', err);
            showToast('Failed to complete tasks', 'error');
        }
    }

    async function bulkDelete() {
        if (!confirm(`Delete ${selectedTasks.size} task(s)? This cannot be undone.`)) return;
        
        const taskIds = Array.from(selectedTasks);
        
        try {
            const resp = await fetch(`${endpoint}/tasks/bulk`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ action: 'delete', task_ids: taskIds })
            });
            
            if (!resp.ok) throw new Error('Failed to delete tasks');
            
            selectedTasks.clear();
            await loadAllTasks();
            showToast('Tasks deleted', 'info');
        } catch (err) {
            console.error('Failed to bulk delete:', err);
            showToast('Failed to delete tasks', 'error');
        }
    }

    // ===========================================
    // MODAL FUNCTIONS
    // ===========================================

    function openModal(taskId = null) {
        editingTaskId = taskId;
        
        // Ensure selects are populated before opening
        populateLeadSelect();
        populateJobSelect();
        
        if (taskId) {
            // Edit mode
            $('#pi-modal-title').text('Edit Task');
            const task = allTasks.find(t => t.id === taskId);
            
            if (task) {
                $('#pi-task-id').val(taskId);
                $('#pi-task-title').val(task.title || '');
                $('#pi-task-priority').val(task.priority || 'medium');
                $('#pi-task-due').val(task.due || '');
                $('#pi-task-lead-select').val(task.lead_id || '');
                $('#pi-task-job-select').val(task.job_id || '');
                $('#pi-task-notes').val(task.notes || '');
            }
        } else {
            // Add mode
            $('#pi-modal-title').text('New Task');
            $('#pi-task-form')[0].reset();
            $('#pi-task-id').val('');
        }
        
        $('#pi-task-modal-overlay').addClass('active');
        setTimeout(() => $('#pi-task-title').focus(), 100);
    }

    function closeModal() {
        $('#pi-task-modal-overlay').removeClass('active');
        $('#pi-task-form')[0].reset();
        editingTaskId = null;
    }

    // ===========================================
    // EVENT HANDLERS
    // ===========================================

    // Filter dropdown
    $('#pi-filter-toggle').on('click', function(e) {
        e.stopPropagation();
        $('#pi-filter-menu').toggleClass('active');
        $('#pi-sort-menu').removeClass('active');
    });

    $('.pi-filter-option').on('click', function() {
        currentFilter = $(this).data('filter');
        $('.pi-filter-option').removeClass('active');
        $(this).addClass('active');
        $('#pi-filter-label').text($(this).text());
        $('#pi-filter-menu').removeClass('active');
        renderTasks();
    });

    // Sort dropdown
    $('#pi-sort-toggle').on('click', function(e) {
        e.stopPropagation();
        $('#pi-sort-menu').toggleClass('active');
        $('#pi-filter-menu').removeClass('active');
    });

    $('.pi-sort-option').on('click', function() {
        currentSort = $(this).data('sort');
        $('.pi-sort-option').removeClass('active');
        $(this).addClass('active');
        $('#pi-sort-label').text($(this).text());
        $('#pi-sort-menu').removeClass('active');
        renderTasks();
    });

    // Close dropdowns on outside click
    $(document).on('click', function() {
        $('#pi-filter-menu, #pi-sort-menu').removeClass('active');
    });

    // Search
    let searchTimeout;
    $('#pi-tasks-search').on('input', function() {
        clearTimeout(searchTimeout);
        const val = $(this).val();
        searchTimeout = setTimeout(() => {
            searchQuery = val;
            renderTasks();
        }, 300);
    });

    // Add task button
    $('#pi-add-task-btn, #pi-add-task-empty').on('click', function() {
        openModal();
    });

    // Modal controls
    $('#pi-close-task-modal, #pi-cancel-task').on('click', closeModal);
    
    $('#pi-task-modal-overlay').on('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Form submission
    $('#pi-task-form').on('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            title: $('#pi-task-title').val(),
            priority: $('#pi-task-priority').val(),
            due: $('#pi-task-due').val(),
            lead_select: $('#pi-task-lead-select').val(),
            job_select: $('#pi-task-job-select').val(),
            notes: $('#pi-task-notes').val()
        };
        
        if (!formData.title) {
            showToast('Please enter a task title', 'error');
            return;
        }
        
        const $btn = $('#pi-save-task');
        setButtonLoading($btn, true);
        
        try {
            await saveTask(formData);
        } catch (err) {
            showToast('Failed to save task', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    // Task complete toggle
    $(document).on('click', '.pi-task-complete-btn', async function(e) {
        e.stopPropagation();
        const taskId = $(this).data('task-id');
        await toggleTaskComplete(taskId);
    });

    // Edit task
    $(document).on('click', '.pi-edit-task', function(e) {
        e.stopPropagation();
        const taskId = $(this).data('task-id');
        openModal(taskId);
    });

    // Delete task
    $(document).on('click', '.pi-delete-task', async function(e) {
        e.stopPropagation();
        const taskId = $(this).data('task-id');
        if (confirm('Delete this task?')) {
            await deleteTask(taskId);
        }
    });

    // Checkbox selection
    $(document).on('change', '.pi-task-checkbox', function() {
        const taskId = $(this).data('task-id');
        if ($(this).is(':checked')) {
            selectedTasks.add(taskId);
        } else {
            selectedTasks.delete(taskId);
        }
        updateBulkActionsBar();
    });

    // Select all
    $('#pi-select-all').on('change', function() {
        const checked = $(this).is(':checked');
        const visibleTasks = getFilteredTasks();
        
        if (checked) {
            visibleTasks.forEach(t => selectedTasks.add(t.id));
        } else {
            selectedTasks.clear();
        }
        
        $('.pi-task-checkbox').prop('checked', checked);
        updateBulkActionsBar();
    });

    // Bulk actions
    $('#pi-bulk-complete').on('click', bulkComplete);
    $('#pi-bulk-delete').on('click', bulkDelete);
    $('#pi-bulk-cancel').on('click', function() {
        selectedTasks.clear();
        $('.pi-task-checkbox, #pi-select-all').prop('checked', false);
        updateBulkActionsBar();
    });

    // Row click to view lead
    $(document).on('click', '.pi-task-row', function(e) {
        if ($(e.target).is('input, button, a') || $(e.target).closest('button, a').length) return;
        
        const leadId = $(this).data('lead-id');
        const lead = allLeads.find(l => l.id === leadId);
        if (lead && lead.lead_code) {
            window.location.href = `/lead/${lead.lead_code}`;
        }
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            $('#pi-tasks-search').focus();
        }
        
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Periodic refresh to catch updates from other devices (every 30 seconds)
    setInterval(() => {
        loadAllTasks();
    }, 30000);

    // ===========================================
    // INITIALIZE
    // ===========================================

    loadAllTasks();

    // Add spinner animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
});
