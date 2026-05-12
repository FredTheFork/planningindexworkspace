<?php
/**
 * Planning Index Workspace - Tasks Page
 * Version 2.0
 * Professional task management for construction leads
 * 
 * Shortcode: [planning_tasks]
 */

if (!defined('ABSPATH')) exit;

// Register Tasks shortcode
add_shortcode('planning_tasks', function () {
    if (!is_user_logged_in()) {
        return '<div class="pi-tasks-wrapper">
            <div class="pi-auth-required">
                <div class="pi-auth-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3>Sign In Required</h3>
                <p>Please log in to access your tasks.</p>
            </div>
        </div>';
    }

    $plugin_url = plugin_dir_url(dirname(__FILE__));

    wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null);
    wp_enqueue_style('pi-tasks-css', $plugin_url . 'assets/tasks.css', [], '2.0.0');
    wp_enqueue_script('pi-tasks-js', $plugin_url . 'assets/tasks.js', ['jquery'], '2.0.0', true);

    wp_localize_script('pi-tasks-js', 'PI_Tasks', [
        'rest_base' => rest_url('pi/v1'),
        'nonce'     => wp_create_nonce('wp_rest'),
        'workspace_url' => home_url('/workspace'),
    ]);

    ob_start(); ?>
    <div class="pi-tasks-wrapper">
        <div class="pi-tasks-main">
            <!-- Sticky white header – now contains everything: title, stats, search, filters, sort, add button -->
            <header class="pi-tasks-header">
                <!-- Left: Title + Count -->
                <div class="pi-header-left">
                    <div class="pi-tasks-title">
                        <h1>Tasks</h1>
                        <span class="pi-task-count" id="pi-total-tasks">0 tasks</span>
                    </div>
                </div>

                <!-- Center-ish: Stats -->
                <div class="pi-stats-bar">
                    <div class="pi-stat-item pending">
                        <span class="pi-stat-value" id="pi-pending-count">0</span>
                        <span class="pi-stat-label">Pending</span>
                    </div>
                    <div class="pi-stat-divider"></div>
                    <div class="pi-stat-item completed">
                        <span class="pi-stat-value" id="pi-completed-count">0</span>
                        <span class="pi-stat-label">Completed</span>
                    </div>
                    <div class="pi-stat-divider"></div>
                    <div class="pi-stat-item overdue">
                        <span class="pi-stat-value" id="pi-overdue-count">0</span>
                        <span class="pi-stat-label">Overdue</span>
                    </div>
                </div>

                <!-- Right: Controls -->
                <div class="pi-header-right">
                    <!-- Search -->
                    <div class="pi-search-container">
                        <svg class="pi-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" id="pi-tasks-search" placeholder="Search tasks..." autocomplete="off" />
                    </div>

                    <!-- Filter -->
                    <div class="pi-filter-dropdown">
                        <button class="pi-filter-btn" id="pi-filter-toggle" type="button">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                            </svg>
                            <span id="pi-filter-label">All Tasks</span>
                            <svg class="pi-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>
                        <div class="pi-filter-menu" id="pi-filter-menu">
                            <button class="pi-filter-option active" data-filter="all" type="button">All Tasks</button>
                            <button class="pi-filter-option" data-filter="pending" type="button">Pending</button>
                            <button class="pi-filter-option" data-filter="completed" type="button">Completed</button>
                            <button class="pi-filter-option" data-filter="overdue" type="button">Overdue</button>
                            <div class="pi-filter-divider"></div>
                            <button class="pi-filter-option" data-filter="high" type="button">
                                <span class="pi-priority-dot high"></span> High Priority
                            </button>
                            <button class="pi-filter-option" data-filter="medium" type="button">
                                <span class="pi-priority-dot medium"></span> Medium Priority
                            </button>
                            <button class="pi-filter-option" data-filter="low" type="button">
                                <span class="pi-priority-dot low"></span> Low Priority
                            </button>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div class="pi-sort-dropdown">
                        <button class="pi-sort-btn" id="pi-sort-toggle" type="button">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="4" y1="6" x2="16" y2="6"/><line x1="4" y1="12" x2="12" y2="12"/><line x1="4" y1="18" x2="8" y2="18"/>
                            </svg>
                            <span id="pi-sort-label">Due Date</span>
                        </button>
                        <div class="pi-sort-menu" id="pi-sort-menu">
                            <button class="pi-sort-option active" data-sort="due" type="button">Due Date</button>
                            <button class="pi-sort-option" data-sort="priority" type="button">Priority</button>
                            <button class="pi-sort-option" data-sort="created" type="button">Created</button>
                            <button class="pi-sort-option" data-sort="lead" type="button">Lead</button>
                        </div>
                    </div>

                    <!-- Add Task Button -->
                    <button class="pi-btn pi-btn-primary" id="pi-add-task-btn" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        <span>New Task</span>
                    </button>
                </div>
            </header>

            <!-- Tasks Content Area (no second header anymore) -->
            <div class="pi-tasks-content">
                <div class="pi-tasks-table-wrapper">
                    <table class="pi-tasks-table" id="pi-tasks-table">
                        <thead>
                            <tr>
                                <th class="pi-th-checkbox">
                                    <label class="pi-checkbox-wrapper">
                                        <input type="checkbox" id="pi-select-all" title="Select all" />
                                        <span class="pi-checkbox-custom"></span>
                                    </label>
                                </th>
                                <th class="pi-th-task">Task</th>
                                <th class="pi-th-lead">Lead/Job</th>
                                <th class="pi-th-priority">Priority</th>
                                <th class="pi-th-due">Due Date</th>
                                <th class="pi-th-status">Status</th>
                                <th class="pi-th-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pi-tasks-body">
                            <!-- Tasks will be rendered here -->
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div class="pi-tasks-empty" id="pi-tasks-empty" style="display: none;">
                    <div class="pi-empty-illustration">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <path d="M9 11l3 3L22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                    <h3>No tasks yet</h3>
                    <p>Create tasks from your lead pages to track work across all your projects.</p>
                    <div class="pi-empty-actions">
                        <a href="<?php echo esc_url(home_url('/workspace')); ?>" class="pi-btn pi-btn-secondary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>
                            Go to Pipeline
                        </a>
                        <button class="pi-btn pi-btn-primary" id="pi-add-task-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Create Task
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal, Bulk Actions, Toast – unchanged -->
    <div class="pi-modal-overlay" id="pi-task-modal-overlay">
        <div class="pi-modal" id="pi-task-modal">
            <div class="pi-modal-header">
                <h2 id="pi-modal-title">New Task</h2>
                <button class="pi-modal-close" id="pi-close-task-modal" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form id="pi-task-form">
                <input type="hidden" id="pi-task-id" name="task_id" value="" />
                
                <div class="pi-form-body">
                    <div class="pi-form-group">
                        <label for="pi-task-title">Task Title <span class="required">*</span></label>
                        <input type="text" id="pi-task-title" name="title" placeholder="What needs to be done?" required />
                    </div>
                    
                    <div class="pi-form-row">
                        <div class="pi-form-group">
                            <label for="pi-task-priority">Priority</label>
                            <div class="pi-select-wrapper">
                                <select id="pi-task-priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                                <svg class="pi-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                        </div>
                        <div class="pi-form-group">
                            <label for="pi-task-due">Due Date</label>
                            <input type="date" id="pi-task-due" name="due" />
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label for="pi-task-lead-select">Link to Lead</label>
                        <div class="pi-select-wrapper">
                            <select id="pi-task-lead-select" name="lead_select">
                                <option value="">Select a lead (optional)</option>
                            </select>
                            <svg class="pi-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label for="pi-task-job-select">Link to Job</label>
                        <div class="pi-select-wrapper">
                            <select id="pi-task-job-select" name="job_select">
                                <option value="">Select a job (optional)</option>
                            </select>
                            <svg class="pi-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="pi-form-group">
                        <label for="pi-task-notes">Notes</label>
                        <textarea id="pi-task-notes" name="notes" rows="3" placeholder="Add any additional details..."></textarea>
                    </div>
                </div>
                
                <div class="pi-modal-footer">
                    <button type="button" class="pi-btn pi-btn-ghost" id="pi-cancel-task">Cancel</button>
                    <button type="submit" class="pi-btn pi-btn-primary" id="pi-save-task">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Save Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="pi-bulk-actions" id="pi-bulk-actions">
        <div class="pi-bulk-info">
            <span class="pi-bulk-count"><span id="pi-selected-count">0</span> selected</span>
        </div>
        <div class="pi-bulk-btns">
            <button class="pi-bulk-btn" id="pi-bulk-complete" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Complete
            </button>
            <button class="pi-bulk-btn pi-bulk-btn-danger" id="pi-bulk-delete" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Delete
            </button>
            <button class="pi-bulk-btn" id="pi-bulk-cancel" type="button">Cancel</button>
        </div>
    </div>

    <div id="pi-toast-container"></div>

    <?php
    return ob_get_clean();
});