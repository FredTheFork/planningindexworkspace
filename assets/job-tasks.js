/**
 * Planning Index - Job Tasks Tab
 * Professional task management for single job page
 * Version 1.0
 */

jQuery(($) => {
    'use strict';

    // Defensive checks - PI_Job may not be available due to caching
    const PI_Job = window.PI_Job || {};
    
    // Get job_id from localization or fall back to DOM
    let jobId = PI_Job.job_id;
    if (!jobId) {
        // Try to get from DOM
        const jobContainer = document.getElementById('pi-job-single');
        if (jobContainer) {
            jobId = jobContainer.dataset.jobId;
        }
    }
    
    if (!jobId) {
        console.error('[JobTasks] CRITICAL: No job_id available!');
        $('#pi-job-tasks-list').html('<div class="pi-job-tasks-error">Error: Unable to load tasks. Please refresh the page.</div>');
        return;
    }
    
    const endpoint = PI_Job.rest_base || '/wp-json/pi/v1';
    const nonce = PI_Job.nonce || '';
    
    console.log('[JobTasks] Initialized with job_id:', jobId);
    
    // Test REST API connectivity
    async function testApiConnection() {
        try {
            console.log('[JobTasks] Testing API connection...');
            const testResp = await fetch(`${endpoint}/tasks`, {
                headers: { 'X-WP-Nonce': nonce },
                credentials: 'same-origin'
            });
            console.log('[JobTasks] API test response:', testResp.status);
            if (!testResp.ok) {
                console.error('[JobTasks] API test failed - REST endpoint may not be registered');
            }
        } catch (e) {
            console.error('[JobTasks] API test error:', e);
        }
    }
    testApiConnection();

    // State
    let allTasks = [];
    let currentFilter = 'all';
    let searchQuery = '';
    let editingTaskId = null;

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

    function formatDateInput(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        const pad = n => n.toString().padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function showToast(message, type = 'success') {
        const icons = {
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };
        
        // Remove existing toasts
        $('.pi-job-tasks-toast').remove();
        
        const toast = $(`
            <div class="pi-job-tasks-toast ${type}">
                ${icons[type]}
                <span>${escapeHtml(message)}</span>
            </div>
        `);
        $('body').append(toast);
        
        // Trigger fade in
        setTimeout(() => toast.addClass('show'), 10);
        
        // Auto remove after delay
        setTimeout(() => {
            toast.fadeOut(300, function() { $(this).remove(); });
        }, 3500);
    }

    // ===========================================
    // API FUNCTIONS
    // ===========================================

    async function loadTasks() {
        try {
            console.log('[JobTasks] Loading tasks for job_id:', jobId);
            const url = `${endpoint}/tasks?job_id=${jobId}`;
            console.log('[JobTasks] API URL:', url);
            console.log('[JobTasks] Using nonce:', nonce ? 'Yes (length: ' + nonce.length + ')' : 'No');
            
            const resp = await fetch(url, {
                headers: { 
                    'X-WP-Nonce': nonce,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            console.log('[JobTasks] Response status:', resp.status, resp.statusText);
            console.log('[JobTasks] Response headers:', [...resp.headers.entries()]);
            
            if (!resp.ok) {
                const errorText = await resp.text();
                console.error('[JobTasks] API error response:', resp.status, errorText);
                throw new Error(`Failed to load tasks: ${resp.status} ${errorText}`);
            }
            
            allTasks = await resp.json();
            console.log('[JobTasks] Loaded', allTasks.length, 'tasks:', allTasks);
            updateStats();
            renderTasks();
            updateBadge();
        } catch (err) {
            console.error('[JobTasks] Error loading tasks:', err);
            showToast('Failed to load tasks', 'error');
        }
    }

    async function addTask(data) {
        try {
            const payload = { ...data, job_id: jobId };
            console.log('[JobTasks] Adding task with payload:', payload);
            
            const resp = await fetch(`${endpoint}/tasks/add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(payload)
            });
            
            if (!resp.ok) throw new Error('Failed to add task');
            
            const result = await resp.json();
            console.log('[JobTasks] Task added:', result);
            showToast('Task added successfully');
            await loadTasks();
            return result;
        } catch (err) {
            console.error('[JobTasks] Error adding task:', err);
            showToast('Failed to add task', 'error');
            throw err;
        }
    }

    async function updateTask(taskId, data) {
        try {
            const resp = await fetch(`${endpoint}/tasks/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ task_id: taskId, ...data })
            });
            
            if (!resp.ok) throw new Error('Failed to update task');
            
            const result = await resp.json();
            showToast('Task updated successfully');
            await loadTasks();
            return result;
        } catch (err) {
            console.error('[JobTasks] Error updating task:', err);
            showToast('Failed to update task', 'error');
            throw err;
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
            
            showToast('Task deleted');
            await loadTasks();
        } catch (err) {
            console.error('[JobTasks] Error deleting task:', err);
            showToast('Failed to delete task', 'error');
            throw err;
        }
    }

    async function toggleTaskComplete(taskId, completed) {
        try {
            const resp = await fetch(`${endpoint}/tasks/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ task_id: taskId, completed: completed })
            });
            
            if (!resp.ok) throw new Error('Failed to update task');
            
            showToast(completed ? 'Task completed' : 'Task marked pending');
            await loadTasks();
        } catch (err) {
            console.error('[JobTasks] Error toggling task:', err);
            showToast('Failed to update task', 'error');
        }
    }

    // ===========================================
    // RENDER FUNCTIONS
    // ===========================================

    function updateStats() {
        const total = allTasks.length;
        const completed = allTasks.filter(t => t.completed).length;
        const pending = total - completed;
        
        const overdue = allTasks.filter(t => {
            if (t.completed || !t.due) return false;
            const due = new Date(t.due);
            const now = new Date();
            now.setHours(0, 0, 0, 0);
            due.setHours(0, 0, 0, 0);
            return due < now;
        }).length;

        $('#pi-job-tasks-total').text(total);
        $('#pi-job-tasks-pending').text(pending);
        $('#pi-job-tasks-completed').text(completed);
        $('#pi-job-tasks-overdue').text(overdue);
    }

    function updateBadge() {
        const pending = allTasks.filter(t => !t.completed).length;
        const badge = $('.job-tasks-count');
        if (pending > 0) {
            badge.text(pending).show();
        } else {
            badge.hide();
        }
    }

    function renderTasks() {
        let filtered = allTasks;
        
        // Apply filter
        if (currentFilter === 'pending') {
            filtered = filtered.filter(t => !t.completed);
        } else if (currentFilter === 'completed') {
            filtered = filtered.filter(t => t.completed);
        }
        
        // Apply search
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            filtered = filtered.filter(t => 
                (t.title || '').toLowerCase().includes(q) ||
                (t.notes || '').toLowerCase().includes(q)
            );
        }
        
        // Sort: incomplete first, then by due date
        filtered.sort((a, b) => {
            if (a.completed !== b.completed) return a.completed ? 1 : -1;
            if (!a.due && !b.due) return 0;
            if (!a.due) return 1;
            if (!b.due) return -1;
            return new Date(a.due) - new Date(b.due);
        });

        const list = $('#pi-job-tasks-list');
        const empty = $('#pi-job-tasks-empty');
        
        if (filtered.length === 0) {
            list.hide();
            empty.show();
            return;
        }
        
        list.show();
        empty.hide();
        
        list.html(filtered.map(task => renderTaskItem(task)).join(''));
    }

    function renderTaskItem(task) {
        const dueInfo = formatRelativeDate(task.due);
        const dueClass = dueInfo?.overdue ? 'overdue' : (dueInfo?.days === 0 ? 'today' : '');
        
        return `
            <div class="pi-job-task-item ${task.completed ? 'completed' : ''}" data-task-id="${task.id}">
                <div class="pi-job-task-checkbox ${task.completed ? 'checked' : ''}" onclick="JobTasks.toggleComplete(${task.id}, ${!task.completed})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="pi-job-task-content">
                    <div class="pi-job-task-title">${escapeHtml(task.title)}</div>
                    <div class="pi-job-task-meta">
                        ${dueInfo ? `<span class="pi-job-task-due ${dueClass}">${dueInfo.text}</span>` : ''}
                        <span class="pi-job-task-priority ${task.priority || 'medium'}">${task.priority || 'medium'}</span>
                    </div>
                </div>
                <div class="pi-job-task-date">${task.due ? formatDate(task.due) : '—'}</div>
                <div class="pi-job-task-actions">
                    <button class="pi-job-task-btn" onclick="JobTasks.openTaskModal(${task.id})" title="Edit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="pi-job-task-btn delete" onclick="JobTasks.deleteTask(${task.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    }

    // ===========================================
    // MODAL FUNCTIONS
    // ===========================================

    function openTaskModal(taskId = null) {
        editingTaskId = taskId;
        const task = taskId ? allTasks.find(t => t.id === taskId) : null;
        
        // Get current job info for display
        const jobInfo = window.PI_Job || {};
        const jobLabel = jobInfo.job_ref || `Job #${jobId}`;
        
        const modalHtml = `
            <div class="pi-job-task-modal-overlay" id="pi-job-task-modal">
                <div class="pi-job-task-modal">
                    <div class="pi-job-task-modal-header">
                        <h3>${task ? 'Edit Task' : 'Add Task'}</h3>
                        <button class="pi-job-task-modal-close" onclick="JobTasks.closeModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <form id="pi-job-task-form">
                        <div class="pi-job-task-modal-body">
                            <div class="pi-job-task-form-row">
                                <div class="pi-job-task-linked-to">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <span>Linked to: <strong>${escapeHtml(jobLabel)}</strong></span>
                                </div>
                            </div>
                            <div class="pi-job-task-form-row">
                                <label>Task Title *</label>
                                <input type="text" name="title" value="${escapeHtml(task?.title || '')}" placeholder="Enter task title" required>
                            </div>
                            <div class="pi-job-task-form-grid">
                                <div class="pi-job-task-form-row">
                                    <label>Due Date</label>
                                    <input type="date" name="due" value="${formatDateInput(task?.due)}">
                                </div>
                                <div class="pi-job-task-form-row">
                                    <label>Priority</label>
                                    <select name="priority">
                                        <option value="low" ${task?.priority === 'low' ? 'selected' : ''}>Low</option>
                                        <option value="medium" ${(!task || task?.priority === 'medium') ? 'selected' : ''}>Medium</option>
                                        <option value="high" ${task?.priority === 'high' ? 'selected' : ''}>High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="pi-job-task-form-row">
                                <label>Notes</label>
                                <textarea name="notes" placeholder="Add notes about this task...">${escapeHtml(task?.notes || '')}</textarea>
                            </div>
                        </div>
                        <div class="pi-job-task-modal-footer">
                            <button type="button" class="pi-btn pi-btn-secondary" onclick="JobTasks.closeModal()">Cancel</button>
                            <button type="submit" class="pi-btn pi-btn-primary">${task ? 'Save Changes' : 'Add Task'}</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('body').addClass('pi-modal-open');
        
        // Focus first input
        setTimeout(() => $('#pi-job-task-form input[name="title"]').focus(), 100);
    }

    function closeModal() {
        $('#pi-job-task-modal').remove();
        $('body').removeClass('pi-modal-open');
        editingTaskId = null;
    }

    async function saveTask(e) {
        e.preventDefault();
        const form = $(e.target);
        const data = {
            title: form.find('input[name="title"]').val(),
            due: form.find('input[name="due"]').val() || null,
            priority: form.find('select[name="priority"]').val(),
            notes: form.find('textarea[name="notes"]').val()
        };
        
        if (editingTaskId) {
            await updateTask(editingTaskId, data);
        } else {
            await addTask(data);
        }
        
        closeModal();
    }

    // ===========================================
    // EVENT HANDLERS
    // ===========================================

    function init() {
        // Load tasks
        loadTasks();
        
        // Add Task button
        $(document).on('click', '#pi-job-task-add', () => openTaskModal());
        
        // Form submit
        $(document).on('submit', '#pi-job-task-form', saveTask);
        
        // Filter buttons
        $(document).on('click', '.pi-filter-btn', function() {
            $('.pi-filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            renderTasks();
        });
        
        // Search
        $(document).on('input', '#pi-job-task-search', function() {
            searchQuery = $(this).val();
            renderTasks();
        });
        
        // Close modal on overlay click
        $(document).on('click', '.pi-job-task-modal-overlay', function(e) {
            if (e.target === this) closeModal();
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
        
        // Tab visibility change - reload tasks
        $(document).on('click', '.job-nav-item[data-job-tab="tasks"]', function() {
            setTimeout(loadTasks, 100);
        });
        
        console.log('[JobTasks] Initialized');
    }

    // ===========================================
    // PUBLIC API
    // ===========================================

    window.JobTasks = {
        init,
        openTaskModal,
        closeModal,
        toggleComplete: toggleTaskComplete,
        deleteTask: async (id) => {
            if (confirm('Are you sure you want to delete this task?')) {
                await deleteTask(id);
            }
        },
        refresh: loadTasks
    };

    // Initialize when DOM ready
    $(init);
});
