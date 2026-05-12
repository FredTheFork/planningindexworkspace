/**
 * Planning Index Workspace - Team Dashboard v3.0
 * Advanced Team Management Dashboard with Full Analytics
 * Professional CRM-style Dashboard - Monday.com / Pipedrive Level
 * 
 * @version 3.0.0
 * @requires Chart.js 3.x
 */

(function($) {
  'use strict';

  // ============================================
  // CONFIGURATION
  // ============================================
  
  const config = {
    restUrl: (window.PI_Team_Data?.restUrl || '/wp-json/pi-crm/v1').replace(/\/$/, ''),
    nonce: window.PI_Team_Data?.nonce || '',
    currency: window.PI_Team_Data?.currency || 'GBP',
    refreshInterval: 60000 // 60 seconds
  };

  // ============================================
  // STATE
  // ============================================
  
  const state = {
    employees: [],
    timesheets: [],
    crews: [],
    activity: [],
    stats: null,
    charts: {},
    filters: {
      dateRange: 'week', // week, month, quarter, year
      department: 'all',
      jobId: null
    },
    loading: false,
    lastRefresh: null
  };

  // ============================================
  // API CLIENT
  // ============================================
  
  const API = {
    async request(endpoint, options = {}) {
      const url = `${config.restUrl}/${endpoint.replace(/^\//, '')}`;
      const defaults = {
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        }
      };
      
      try {
        const response = await fetch(url, { ...defaults, ...options });
        if (!response.ok) {
          const error = await response.json().catch(() => ({}));
          throw new Error(error.message || `HTTP ${response.status}`);
        }
        return response.json();
      } catch (error) {
        console.error(`[TeamDashboard] API Error: ${endpoint}`, error);
        throw error;
      }
    },

    // Dashboard
    getDashboardStats(params = {}) {
      const query = new URLSearchParams(params).toString();
      return this.request(query ? `team-dashboard/stats?${query}` : 'team-dashboard/stats');
    },
    
    getWeeklyHours(params = {}) {
      const query = new URLSearchParams(params).toString();
      return this.request(query ? `team-dashboard/weekly-hours?${query}` : 'team-dashboard/weekly-hours');
    },
    
    getActivity(limit = 20) {
      return this.request(`team-dashboard/activity?limit=${limit}`);
    },
    
    // Employees
    getEmployees(params = {}) {
      const query = new URLSearchParams(params).toString();
      return this.request(query ? `employees?${query}` : 'employees');
    },
    
    getEmployeeStats(id) {
      return this.request(`employees/${id}/stats`);
    },
    
    // Timesheets
    getTimesheets(params = {}) {
      const query = new URLSearchParams(params).toString();
      return this.request(query ? `timesheets?${query}` : 'timesheets');
    },
    
    // Crews
    getCrews() {
      return this.request('crews');
    },
    
    // Clock
    getOnSiteWorkers() {
      return this.request('clock/on-site');
    },
    
    // Approvals
    getPendingApprovals() {
      return this.request('approvals/pending');
    }
  };

  // ============================================
  // UTILITIES
  // ============================================
  
  const Utils = {
    formatCurrency(amount) {
      if (amount === null || amount === undefined || isNaN(amount)) return '£0.00';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: config.currency,
        minimumFractionDigits: 2
      }).format(amount);
    },

    formatHours(hours) {
      if (!hours || isNaN(hours)) return '0h';
      const h = Math.floor(hours);
      const m = Math.round((hours - h) * 60);
      return m > 0 ? `${h}h ${m}m` : `${h}h`;
    },

    formatDate(dateStr, options = {}) {
      if (!dateStr) return '-';
      const date = new Date(dateStr);
      if (isNaN(date.getTime())) return dateStr;
      
      const defaultOptions = { day: 'numeric', month: 'short' };
      return date.toLocaleDateString('en-GB', { ...defaultOptions, ...options });
    },

    formatRelativeTime(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      const now = new Date();
      const diff = Math.floor((now - date) / 1000);
      
      if (diff < 60) return 'Just now';
      if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
      if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
      return this.formatDate(dateStr);
    },

    escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    getInitials(firstName, lastName) {
      const first = firstName ? firstName.charAt(0) : '';
      const last = lastName ? lastName.charAt(0) : '';
      return (first + last).toUpperCase();
    },

    generateAvatarColor(name) {
      const colors = [
        '#156349', '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b',
        '#ef4444', '#ec4899', '#06b6d4', '#84cc16', '#f97316'
      ];
      let hash = 0;
      for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
      }
      return colors[Math.abs(hash) % colors.length];
    }
  };

  // ============================================
  // NOTIFICATIONS
  // ============================================
  
  const Notifications = {
    show(message, type = 'success', duration = 4000) {
      const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
      };

      const $toast = $(`
        <div class="pi-td-notification ${type}">
          <div class="pi-td-notification-icon">${icons[type]}</div>
          <div class="pi-td-notification-content">${Utils.escapeHtml(message)}</div>
        </div>
      `).appendTo('body');

      setTimeout(() => {
        $toast.addClass('fade-out');
        setTimeout(() => $toast.remove(), 300);
      }, duration);
    }
  };

  // ============================================
  // CHARTS
  // ============================================
  
  const Charts = {
    init() {
      this.loadChartJS().then(() => {
        this.renderAll();
      });
    },

    loadChartJS() {
      return new Promise((resolve) => {
        if (window.Chart) {
          resolve();
          return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
        script.onload = resolve;
        document.head.appendChild(script);
      });
    },

    renderAll() {
      this.renderHoursChart();
      this.renderDepartmentChart();
      this.renderAttendanceChart();
      this.renderCostChart();
    },

    renderHoursChart() {
      const ctx = document.getElementById('hoursChart');
      if (!ctx) return;

      // Sample data - replace with real data from API
      const data = {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
          label: 'Regular Hours',
          data: [45, 52, 48, 55, 42, 20, 15],
          backgroundColor: '#156349',
          borderColor: '#156349',
          borderWidth: 2,
          borderRadius: 4,
          tension: 0.4
        }, {
          label: 'Overtime',
          data: [8, 12, 5, 10, 6, 2, 0],
          backgroundColor: '#f59e0b',
          borderColor: '#f59e0b',
          borderWidth: 2,
          borderRadius: 4,
          tension: 0.4
        }]
      };

      state.charts.hours = new Chart(ctx, {
        type: 'bar',
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                padding: 20,
                font: { size: 12, family: 'Inter' }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: '#f1f5f9',
                drawBorder: false
              },
              ticks: {
                font: { size: 11, family: 'Inter' },
                color: '#94a3b8'
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                font: { size: 11, family: 'Inter' },
                color: '#64748b'
              }
            }
          }
        }
      });
    },

    renderDepartmentChart() {
      const ctx = document.getElementById('departmentChart');
      if (!ctx) return;

      const data = {
        labels: ['Carpentry', 'Electrical', 'Plumbing', 'Masonry', 'General'],
        datasets: [{
          data: [35, 25, 20, 15, 5],
          backgroundColor: [
            '#156349',
            '#3b82f6',
            '#f59e0b',
            '#8b5cf6',
            '#10b981'
          ],
          borderWidth: 2,
          borderColor: '#ffffff',
          hoverOffset: 4
        }]
      };

      state.charts.department = new Chart(ctx, {
        type: 'doughnut',
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '70%',
          plugins: {
            legend: {
              position: 'right',
              labels: {
                usePointStyle: true,
                padding: 12,
                font: { size: 11, family: 'Inter' },
                boxWidth: 8
              }
            }
          }
        }
      });
    },

    renderAttendanceChart() {
      const ctx = document.getElementById('attendanceChart');
      if (!ctx) return;

      const data = {
        labels: ['W1', 'W2', 'W3', 'W4'],
        datasets: [{
          label: 'Present',
          data: [95, 92, 96, 94],
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointHoverRadius: 5
        }, {
          label: 'Target',
          data: [95, 95, 95, 95],
          borderColor: '#cbd5e1',
          borderDash: [5, 5],
          fill: false,
          tension: 0,
          pointRadius: 0
        }]
      };

      state.charts.attendance = new Chart(ctx, {
        type: 'line',
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                padding: 15,
                font: { size: 11, family: 'Inter' }
              }
            }
          },
          scales: {
            y: {
              min: 80,
              max: 100,
              grid: {
                color: '#f1f5f9',
                drawBorder: false
              },
              ticks: {
                font: { size: 10 },
                padding: 4
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                font: { size: 10 }
              }
            }
          }
        }
      });
    },

    renderCostChart() {
      const ctx = document.getElementById('costChart');
      if (!ctx) return;

      const data = {
        labels: ['Labor', 'Overtime', 'Benefits', 'Equipment', 'Training'],
        datasets: [{
          label: 'This Month',
          data: [45000, 12000, 8000, 5000, 2000],
          backgroundColor: '#156349',
          borderRadius: 4
        }, {
          label: 'Last Month',
          data: [42000, 9500, 8000, 4500, 1500],
          backgroundColor: '#cbd5e1',
          borderRadius: 4
        }]
      };

      state.charts.cost = new Chart(ctx, {
        type: 'bar',
        data: data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                padding: 20
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: '#f1f5f9',
                drawBorder: false
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        }
      });
    },

    update(data) {
      // Update charts with new data
      if (state.charts.hours && data.weeklyHours) {
        state.charts.hours.data.datasets[0].data = data.weeklyHours.regular || [];
        state.charts.hours.data.datasets[1].data = data.weeklyHours.overtime || [];
        state.charts.hours.update();
      }
    }
  };

  // ============================================
  // RENDER FUNCTIONS
  // ============================================
  
  const Render = {
    // KPI Cards
    renderKPIs(stats) {
      if (!stats) {
        // Render placeholder KPIs if no stats available
        const placeholderKpis = [
          { label: 'Total Employees', value: '0', subtitle: '0 on site', icon: 'users', type: 'success' },
          { label: 'Hours This Week', value: '0h', subtitle: '0h overtime', icon: 'clock', type: 'info' },
          { label: 'Pending Approvals', value: '0', subtitle: 'Awaiting review', icon: 'check-circle', type: 'success' },
          { label: 'Labor Cost', value: '£0.00', subtitle: 'This week', icon: 'pound-sign', type: 'purple' }
        ];

        const html = placeholderKpis.map(kpi => `
          <div class="pi-td-kpi-card ${kpi.type}">
            <div class="pi-td-kpi-header">
              <span class="pi-td-kpi-label">${kpi.label}</span>
              <div class="pi-td-kpi-icon ${kpi.type}">
                <i data-feather="${kpi.icon}"></i>
              </div>
            </div>
            <div class="pi-td-kpi-value">${kpi.value}</div>
            <div class="pi-td-kpi-subtitle">${kpi.subtitle}</div>
          </div>
        `).join('');

        $('#kpiCards').html(html);
        feather.replace();
        return;
      }

      const kpis = [
        {
          label: 'Total Employees',
          value: stats.total_employees || 0,
          subtitle: `${stats.on_site || 0} on site`,
          icon: 'users',
          type: 'success',
          trend: '+3%'
        },
        {
          label: 'Hours This Week',
          value: Utils.formatHours(stats.week_hours || 0),
          subtitle: `${Utils.formatHours(stats.week_overtime_hours || 0)} overtime`,
          icon: 'clock',
          type: 'info',
          trend: '+12%'
        },
        {
          label: 'Pending Approvals',
          value: stats.pending_approvals || 0,
          subtitle: 'Awaiting review',
          icon: 'check-circle',
          type: stats.pending_approvals > 5 ? 'warning' : 'success'
        },
        {
          label: 'Labor Cost',
          value: Utils.formatCurrency(stats.labor_cost || 0),
          subtitle: 'This week',
          icon: 'pound-sign',
          type: 'purple',
          trend: '-5%'
        }
      ];

      const html = kpis.map(kpi => `
        <div class="pi-td-kpi-card ${kpi.type}">
          <div class="pi-td-kpi-header">
            <span class="pi-td-kpi-label">${kpi.label}</span>
            <div class="pi-td-kpi-icon ${kpi.type}">
              <i data-feather="${kpi.icon}"></i>
            </div>
          </div>
          <div class="pi-td-kpi-value">
            ${kpi.value}
            ${kpi.trend ? `<span class="pi-td-kpi-trend ${kpi.trend.startsWith('+') ? 'up' : 'down'}">${kpi.trend}</span>` : ''}
          </div>
          <div class="pi-td-kpi-subtitle">${kpi.subtitle}</div>
        </div>
      `).join('');

      $('#kpiCards').html(html);
      feather.replace();
    },

    // Activity Feed
    renderActivity(activities) {
      if (!activities || activities.length === 0) {
        $('#activityFeed').html(`
          <div class="pi-td-empty">
            <i data-feather="activity"></i>
            <h3>No Recent Activity</h3>
            <p>Team activity will appear here</p>
          </div>
        `);
        feather.replace();
        return;
      }

      const html = activities.map(activity => {
        const initials = Utils.getInitials(
          activity.first_name || activity.employee_name?.split(' ')[0],
          activity.last_name || activity.employee_name?.split(' ')[1]
        );
        
        return `
          <div class="pi-td-activity-item">
            <div class="pi-td-activity-avatar">
              ${activity.avatar_url ? 
                `<img src="${activity.avatar_url}" alt="">` : 
                initials}
            </div>
            <div class="pi-td-activity-content">
              <div class="pi-td-activity-text">
                <strong>${activity.employee_name || 'Unknown'}</strong> 
                ${activity.description || 'performed an action'}
              </div>
              <div class="pi-td-activity-meta">
                <span class="pi-td-activity-type ${activity.type}">
                  ${activity.type === 'clock' ? 'Clock In/Out' : 
                    activity.type === 'timesheet' ? 'Timesheet' : 
                    activity.type}
                </span>
                <span>${Utils.formatRelativeTime(activity.timestamp || activity.created_at)}</span>
              </div>
            </div>
          </div>
        `;
      }).join('');

      $('#activityFeed').html(html);
    },

    // Employee Grid
    renderEmployees(employees) {
      if (!employees || employees.length === 0) {
        $('#employeeGrid').html(`
          <div class="pi-td-empty">
            <i data-feather="users"></i>
            <h3>No Employees</h3>
            <p>Add employees to see them here</p>
          </div>
        `);
        feather.replace();
        return;
      }

      const html = employees.slice(0, 8).map(emp => {
        const initials = Utils.getInitials(emp.first_name, emp.last_name);
        const statusClass = emp.status === 'active' ? 'active' : 'offline';
        
        return `
          <div class="pi-td-employee-card" data-employee-id="${emp.id}">
            <div class="pi-td-employee-header">
              <div class="pi-td-employee-avatar" style="background-color: ${Utils.generateAvatarColor(emp.first_name + ' ' + emp.last_name)}20; color: ${Utils.generateAvatarColor(emp.first_name + ' ' + emp.last_name)}">
                ${emp.avatar_url ? `<img src="${emp.avatar_url}" alt="">` : initials}
              </div>
              <div class="pi-td-employee-info">
                <h4>${Utils.escapeHtml(emp.first_name + ' ' + emp.last_name)}</h4>
                <div class="pi-td-employee-role">${Utils.escapeHtml(emp.role || 'No role')}</div>
              </div>
              <span class="pi-td-employee-status ${statusClass}">
                <span class="pi-status-dot"></span>
                ${emp.status === 'active' ? 'Active' : 'Offline'}
              </span>
            </div>
            <div class="pi-td-employee-stats">
              <div class="pi-td-employee-stat">
                <div class="pi-td-employee-stat-value">${emp.week_hours || 0}</div>
                <div class="pi-td-employee-stat-label">Hours</div>
              </div>
              <div class="pi-td-employee-stat">
                <div class="pi-td-employee-stat-value">${emp.ot_hours || 0}</div>
                <div class="pi-td-employee-stat-label">Overtime</div>
              </div>
              <div class="pi-td-employee-stat">
                <div class="pi-td-employee-stat-value">${emp.job_count || 0}</div>
                <div class="pi-td-employee-stat-label">Jobs</div>
              </div>
            </div>
          </div>
        `;
      }).join('');

      $('#employeeGrid').html(html);
    },

    // Crew Grid
    renderCrews(crews) {
      if (!crews || crews.length === 0) {
        $('#crewGrid').html(`
          <div class="pi-td-empty">
            <i data-feather="users"></i>
            <h3>No Crews</h3>
            <p>Create crews to organize your teams</p>
          </div>
        `);
        feather.replace();
        return;
      }

      const html = crews.map(crew => `
        <div class="pi-td-crew-card">
          <div class="pi-td-crew-header">
            <div class="pi-td-crew-name">${Utils.escapeHtml(crew.crew_name)}</div>
            <div class="pi-td-crew-trade">${Utils.escapeHtml(crew.trade_specialty || 'General')}</div>
          </div>
          <div class="pi-td-crew-body">
            <div class="pi-td-crew-foreman">
              <div class="pi-td-employee-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                ${Utils.getInitials(crew.foreman_first, crew.foreman_last)}
              </div>
              <span>Foreman: ${Utils.escapeHtml((crew.foreman_first || '') + ' ' + (crew.foreman_last || '')).trim() || 'Not assigned'}</span>
            </div>
            <div class="pi-td-crew-members">
              ${crew.member_count ? `
                <div class="pi-td-crew-member">
                  <i data-feather="users" style="width: 14px; height: 14px;"></i>
                  ${crew.member_count} members
                </div>
              ` : '<span style="color: #94a3b8; font-size: 12px;">No members yet</span>'}
            </div>
          </div>
        </div>
      `).join('');

      $('#crewGrid').html(html);
      feather.replace();
    },

    // Heatmap
    renderHeatmap(data) {
      const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      const employees = data.slice(0, 10); // Show top 10 employees
      
      let html = '<div class="pi-td-heatmap">';
      
      // Header
      html += '<div></div>';
      days.forEach(day => {
        html += `<div class="pi-td-heatmap-header">${day}</div>`;
      });
      
      // Rows
      employees.forEach(emp => {
        html += `<div class="pi-td-heatmap-label">${Utils.getInitials(emp.first_name, emp.last_name)}</div>`;
        
        for (let i = 0; i < 7; i++) {
          const hours = emp.daily_hours?.[i] || 0;
          const level = Math.min(Math.ceil(hours / 2), 5);
          html += `<div class="pi-td-heatmap-cell level-${level}" title="${hours} hours">${hours > 0 ? hours : ''}</div>`;
        }
      });
      
      html += '</div>';
      $('#attendanceHeatmap').html(html);
    }
  };

  // ============================================
  // DATA LOADING
  // ============================================
  
  const DataLoader = {
    async loadAll() {
      $('.pi-td-container').addClass('pi-td-loading');
      
      try {
        const [stats, activity, employees, crews] = await Promise.all([
          API.getDashboardStats(state.filters).catch(() => null),
          API.getActivity(15).catch(() => []),
          API.getEmployees({ status: 'active', per_page: 50 }).catch(() => []),
          API.getCrews().catch(() => [])
        ]);

        state.stats = stats;
        state.activity = activity;
        state.employees = Array.isArray(employees) ? employees : [];
        state.crews = crews;
        state.lastRefresh = new Date();

        this.updateUI();
        
        Notifications.show('Dashboard updated', 'success', 2000);
      } catch (error) {
        console.error('[TeamDashboard] Load error:', error);
        Notifications.show('Failed to load dashboard data', 'error');
      } finally {
        $('.pi-td-container').removeClass('pi-td-loading');
      }
    },

    updateUI() {
      Render.renderKPIs(state.stats);
      Render.renderActivity(state.activity);
      Render.renderEmployees(state.employees);
      Render.renderCrews(state.crews);
      
      // Initialize charts if not already done
      if (!state.chartsInitialized) {
        Charts.init();
        state.chartsInitialized = true;
      }
      
      // Update last refresh time
      $('#lastRefresh').text('Updated ' + Utils.formatRelativeTime(state.lastRefresh));
    },

    refresh() {
      this.loadAll();
    }
  };

  // ============================================
  // EVENT HANDLERS
  // ============================================
  
  const Events = {
    init() {
      // Refresh button
      $(document).on('click', '#refreshDashboard', () => {
        DataLoader.refresh();
      });

      // Date range filter
      $(document).on('change', '#dateRangeFilter', (e) => {
        state.filters.dateRange = e.target.value;
        DataLoader.refresh();
      });

      // Employee card click
      $(document).on('click', '.pi-td-employee-card', function() {
        const employeeId = $(this).data('employee-id');
        if (employeeId) {
          window.TeamApp?.viewEmployeeStats?.(employeeId);
        }
      });

      // Auto refresh
      setInterval(() => {
        DataLoader.refresh();
      }, config.refreshInterval);
    }
  };

  // ============================================
  // INITIALIZATION
  // ============================================
  
  function init() {
    if (!$('#teamDashboardV3').length) return;
    
    console.log('[TeamDashboard] Initializing v3.0...');
    
    // Load Feather icons
    if (window.feather) {
      feather.replace();
    }
    
    // Initialize
    Events.init();
    DataLoader.loadAll();
    
    console.log('[TeamDashboard] Ready');
  }

  // Initialize when DOM is ready
  $(document).ready(init);

  // Expose to global scope
  window.TeamDashboard = {
    refresh: () => DataLoader.refresh(),
    getState: () => state,
    updateFilters: (filters) => {
      Object.assign(state.filters, filters);
      DataLoader.refresh();
    }
  };

})(jQuery);
