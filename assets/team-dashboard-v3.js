/**
 * Planning Index Workspace - Team Dashboard v3.7.0
 * Professional CRM-style Dashboard with Real Data Integration
 * INDEPENDENT CREWS: Reusable groups assignable to any job
 * Full Labor Cost Analysis Implementation
 * FIXED: worker_id column alignment, Job-specific data isolation
 * 
 * @version 3.7.0 - Timesheets job filtering fix
 * @requires Chart.js 3.x
 */

(function($) {
  'use strict';

  console.log('[TeamDashboard] v3.7.0 Initializing...');
  console.log('[TeamDashboard] Independent crews system active');

  // ============================================
  // CONFIGURATION
  // ============================================
  
  const config = {
    restUrl: (window.PI_Team_Data?.restUrl || '/wp-json/pi/v1').replace(/\/$/, ''),
    nonce: window.PI_Team_Data?.nonce || '',
    currency: window.PI_Team_Data?.currency || 'GBP',
    currentJobId: window.PI_Team_Data?.currentJobId || null, // Job ID passed from PHP
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
    weeklyHours: null,
    tradeDistribution: [],
    attendanceTrends: [],
    laborCost: [],
    topPerformers: [],
    chartInstances: {},
    filters: {
      dateRange: 'week',
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
      console.log(`[TeamDashboard API] Request: ${url}`);
      
      const defaults = {
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        }
      };
      
      try {
        const response = await fetch(url, { ...defaults, ...options });
        console.log(`[TeamDashboard API] Response: ${endpoint} - Status: ${response.status}`);
        
        if (!response.ok) {
          const errorText = await response.text();
          console.error(`[TeamDashboard API] Error ${endpoint}:`, errorText);
          throw new Error(`HTTP ${response.status}: ${errorText.substring(0, 200)}`);
        }
        
        const data = await response.json();
        console.log(`[TeamDashboard API] Success: ${endpoint}`, data);
        return data;
      } catch (error) {
        console.error(`[TeamDashboard] API Error: ${endpoint}`, error);
        throw error;
      }
    },

    // Dashboard - Job-specific when job_id is provided
    getDashboardStats(params = {}) {
      const query = new URLSearchParams(params).toString();
      return this.request(query ? `team-dashboard/stats?${query}` : 'team-dashboard/stats');
    },
    
    getWeeklyHours(params = {}) {
      const query = new URLSearchParams(params).toString();
      return this.request(query ? `team-dashboard/weekly-hours?${query}` : 'team-dashboard/weekly-hours');
    },
    
    getActivity(limit = 20, jobId = null) {
      const jobParam = jobId ? `&job_id=${jobId}` : '';
      return this.request(`team-dashboard/activity?limit=${limit}${jobParam}`);
    },
    
    getTopPerformers(limit = 4, jobId = null) {
      const jobParam = jobId ? `&job_id=${jobId}` : '';
      return this.request(`team-dashboard/top-performers?limit=${limit}${jobParam}`);
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
    
    // Crews - Independent/Reusable groups
    getCrews() {
      return this.request('crews');
    },
    getCrew(id) {
      return this.request(`crews/${id}`);
    },
    createCrew(data) {
      return this.request('crews', { method: 'POST', body: JSON.stringify(data) });
    },
    updateCrew(id, data) {
      return this.request(`crews/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
    },
    // Crew assignments to jobs
    getAvailableCrews(jobId) {
      return this.request(`crews/available?job_id=${jobId}`);
    },
    getJobCrews(jobId) {
      return this.request(`crews/assigned/${jobId}`);
    },
    assignCrewToJob(crewId, jobId, options = {}) {
      return this.request('crews/assign', { 
        method: 'POST', 
        body: JSON.stringify({ crew_id: crewId, job_id: jobId, ...options })
      });
    },
    unassignCrewFromJob(assignmentId, removeMembers = false) {
      return this.request(`crews/unassign/${assignmentId}`, {
        method: 'POST',
        body: JSON.stringify({ remove_members: removeMembers })
      });
    },
    
    // Clock
    getOnSiteWorkers(jobId = null) {
      const jobParam = jobId ? `&job_id=${jobId}` : '';
      return this.request(`clock/on-site?${jobParam}`);
    },
    
    // Approvals
    getPendingApprovals(jobId = null) {
      const jobParam = jobId ? `&job_id=${jobId}` : '';
      return this.request(`approvals/pending?${jobParam}`);
    },
    
    // NEW Dashboard Chart Endpoints
    getTradeDistribution(jobId = null) {
      console.log('[TeamDashboard API] getTradeDistribution()', jobId);
      const jobParam = jobId ? `&job_id=${jobId}` : '';
      return this.request(`team-dashboard/trade-distribution?${jobParam}`);
    },
    
    getAttendanceTrends(period = 'month', jobId = null) {
      console.log('[TeamDashboard API] getAttendanceTrends()', period, jobId);
      const jobParam = jobId ? `&job_id=${jobId}` : '';
      return this.request(`team-dashboard/attendance-trends?period=${period}${jobParam}`);
    },
    
    getLaborCost(period = 'month', jobId = null) {
      console.log('[TeamDashboard API] getLaborCost()', period, jobId);
      const jobParam = jobId ? `&job_id=${jobId}` : '';
      return this.request(`team-dashboard/labor-cost?period=${period}${jobParam}`);
    }
  };

  // ============================================
  // UTILITIES
  // ============================================
  
  const Utils = {
    // Detect job_id from URL or config for job-specific dashboard views
    getJobIdFromUrl() {
      // First check if job_id is passed from PHP (most reliable)
      if (config.currentJobId && parseInt(config.currentJobId) > 0) {
        console.log('[TeamDashboard] Using job_id from PI_Team_Data:', config.currentJobId);
        return parseInt(config.currentJobId);
      }
      
      // Fallback: Check URL query parameters
      const urlParams = new URLSearchParams(window.location.search);
      const jobId = urlParams.get('job_id');
      if (jobId && parseInt(jobId) > 0) {
        console.log('[TeamDashboard] Using job_id from URL param:', jobId);
        return parseInt(jobId);
      }
      
      // Fallback: Try to extract job slug from pretty permalink URL
      // URL format: /job/job-202603-003/
      const pathMatch = window.location.pathname.match(/\/job\/([^\/]+)\//);
      if (pathMatch && pathMatch[1]) {
        const jobSlug = pathMatch[1];
        console.log('[TeamDashboard] Detected job slug from URL:', jobSlug);
        // Note: We'll need to resolve this to a numeric ID via API
        return null; // For now, return null - we'll handle slug resolution separately
      }
      
      console.log('[TeamDashboard] No job_id detected - showing global view');
      return null;
    },
    
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
  // EMPTY STATES
  // ============================================
  
  const EmptyStates = {
    chart(canvasId, message = 'No data available') {
      const canvas = document.getElementById(canvasId);
      if (!canvas) return;
      
      const container = canvas.parentElement;
      if (!container) return;
      
      // Hide canvas
      canvas.style.display = 'none';
      
      // Remove existing empty state
      const existing = container.querySelector('.pi-td-chart-empty');
      if (existing) existing.remove();
      
      // Add empty state HTML
      const emptyHtml = `
        <div class="pi-td-chart-empty">
          <div class="pi-td-empty-content">
            <i data-feather="bar-chart-2"></i>
            <p>${Utils.escapeHtml(message)}</p>
            <span class="pi-td-empty-hint">Data will appear when timesheets are logged</span>
          </div>
        </div>
      `;
      container.insertAdjacentHTML('beforeend', emptyHtml);
      
      if (window.feather) feather.replace();
    },
    
    clear(canvasId) {
      const canvas = document.getElementById(canvasId);
      if (!canvas) return;
      
      const container = canvas.parentElement;
      if (!container) return;
      
      // Show canvas
      canvas.style.display = 'block';
      
      // Remove empty state
      const existing = container.querySelector('.pi-td-chart-empty');
      if (existing) existing.remove();
    },
    
    activity() {
      return `
        <div class="pi-td-activity-empty">
          <i data-feather="activity"></i>
          <p>No recent activity</p>
          <span class="pi-td-empty-hint">Timesheet entries will appear here</span>
        </div>
      `;
    },
    
    employees() {
      return `
        <div class="pi-td-grid-empty">
          <i data-feather="award"></i>
          <p>No Top Performers Yet</p>
          <span class="pi-td-empty-hint">Employee rankings will appear once timesheets are logged</span>
        </div>
      `;
    },
    
    crews() {
      return `
        <div class="pi-td-grid-empty">
          <i data-feather="tool"></i>
          <p>No crews created</p>
          <button class="pi-td-btn pi-td-btn-primary" onclick="TeamDashboard.openAddCrewModal()">
            <i data-feather="plus"></i> Create First Crew
          </button>
        </div>
      `;
    }
  };

  // ============================================
  // CHARTS
  // ============================================
  
  const Charts = {
    init() {
      return this.loadChartJS().then(() => {
        console.log('[TeamDashboard Charts] ChartJS loaded, rendering charts');
        this.renderAll();
        return true;
      });
    },
    
    destroy(chartId) {
      if (state.chartInstances[chartId]) {
        state.chartInstances[chartId].destroy();
        delete state.chartInstances[chartId];
      }
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
      console.log('[TeamDashboard Charts] renderAll() starting...');
      
      if (!window.Chart) {
        console.error('[TeamDashboard Charts] Chart.js not loaded yet!');
        return;
      }
      
      console.log('[TeamDashboard Charts] state.tradeDistribution:', state.tradeDistribution);
      console.log('[TeamDashboard Charts] state.attendanceTrends:', state.attendanceTrends);
      console.log('[TeamDashboard Charts] state.laborCost:', state.laborCost);
      
      this.renderHoursChart();
      this.renderDepartmentChart();
      this.renderAttendanceChart();
      this.renderCostChart();
      console.log('[TeamDashboard Charts] renderAll() complete');
    },

    renderHoursChart() {
      const ctx = document.getElementById('hoursChart');
      if (!ctx) {
        console.log('[TeamDashboard Charts] hoursChart canvas not found');
        return;
      }
      
      // Destroy existing chart
      this.destroy('hoursChart');
      
      // Get real data from state
      const weeklyData = state.weeklyHours?.daily || [];
      console.log('[TeamDashboard Charts] Weekly hours data:', weeklyData);
      
      // Check if we have any data with hours
      const hasData = weeklyData.some(d => d.regular_hours > 0 || d.overtime_hours > 0);
      
      if (!hasData) {
        console.log('[TeamDashboard Charts] No hours data available');
        EmptyStates.chart('hoursChart', 'No hours data logged this week');
        return;
      }
      
      // Clear any empty state
      EmptyStates.clear('hoursChart');
      
      // Extract data for chart
      const labels = weeklyData.map(d => d.day_name);
      const regularHours = weeklyData.map(d => d.regular_hours);
      const overtimeHours = weeklyData.map(d => d.overtime_hours);
      
      console.log('[TeamDashboard Charts] Rendering with labels:', labels);
      console.log('[TeamDashboard Charts] Regular hours:', regularHours);
      console.log('[TeamDashboard Charts] Overtime hours:', overtimeHours);

      state.chartInstances.hoursChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Regular Hours',
            data: regularHours,
            backgroundColor: '#156349',
            borderColor: '#156349',
            borderWidth: 2,
            borderRadius: 4,
            tension: 0.4
          }, {
            label: 'Overtime',
            data: overtimeHours,
            backgroundColor: '#f59e0b',
            borderColor: '#f59e0b',
            borderWidth: 2,
            borderRadius: 4,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                padding: 12,
                font: { size: 11 }
              }
            },
            tooltip: {
              callbacks: {
                label: (context) => {
                  const label = context.dataset.label;
                  const value = context.parsed.y;
                  return `${label}: ${value.toFixed(1)}h`;
                }
              }
            }
          },
          scales: {
            x: { 
              stacked: true,
              grid: { display: false },
              ticks: { font: { size: 11 } }
            },
            y: { 
              stacked: true,
              beginAtZero: true,
              ticks: { stepSize: 2 },
              grid: { color: 'rgba(0,0,0,0.03)' }
            }
          }
        }
      });
    },
    
    // Trade Distribution (Pie Chart)
    renderDepartmentChart() {
      console.log('[TeamDashboard Charts] renderDepartmentChart() called');
      
      const ctx = document.getElementById('departmentChart');
      if (!ctx) {
        console.log('[TeamDashboard Charts] departmentChart canvas not found');
        return;
      }
      
      this.destroy('departmentChart');
      
      const tradeData = state.tradeDistribution || [];
      console.log('[TeamDashboard Charts] tradeDistribution data:', tradeData);
      
      if (tradeData.length === 0) {
        console.log('[TeamDashboard Charts] No trade data, showing empty state');
        EmptyStates.chart('departmentChart', 'No trade data available');
        return;
      }
      
      EmptyStates.clear('departmentChart');
      
      const labels = tradeData.map(t => t.trade || 'Unspecified');
      const data = tradeData.map(t => t.count);
      const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#84cc16', '#64748b'];
      
      state.chartInstances.departmentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: data,
            backgroundColor: colors.slice(0, data.length),
            borderWidth: 2,
            borderColor: '#ffffff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '65%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: { 
                usePointStyle: true, 
                padding: 12, 
                font: { size: 11 },
                boxWidth: 8
              }
            },
            tooltip: {
              callbacks: {
                label: (context) => {
                  const total = data.reduce((a, b) => a + b, 0);
                  const percentage = ((context.parsed / total) * 100).toFixed(1);
                  return `${context.label}: ${context.parsed} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
    },
    
    // Attendance Trends Chart
    renderAttendanceChart() {
      const ctx = document.getElementById('attendanceChart');
      if (!ctx) return;
      
      this.destroy('attendanceChart');
      
      const trendData = state.attendanceTrends || [];
      
      if (trendData.length === 0) {
        EmptyStates.chart('attendanceChart', 'No attendance data available');
        return;
      }
      
      EmptyStates.clear('attendanceChart');
      
      const labels = trendData.map(t => t.label);
      const rates = trendData.map(t => t.rate);
      
      state.chartInstances.attendanceChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Attendance Rate',
            data: rates,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { 
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (context) => `${context.parsed.y.toFixed(1)}% attendance`
              }
            }
          },
          scales: {
            y: { 
              beginAtZero: true, 
              max: 100,
              ticks: { 
                callback: v => v + '%',
                stepSize: 20
              },
              grid: { color: 'rgba(0,0,0,0.03)' }
            },
            x: { 
              grid: { display: false },
              ticks: { font: { size: 11 } }
            }
          }
        }
      });
    },
    
    // Labor Cost Analysis Chart - Full Implementation
    renderCostChart() {
      console.log('[TeamDashboard Charts] renderCostChart() called');
      
      const ctx = document.getElementById('costChart');
      if (!ctx) {
        console.log('[TeamDashboard Charts] costChart canvas not found');
        return;
      }
      
      this.destroy('costChart');
      
      const costData = state.laborCost || [];
      console.log('[TeamDashboard Charts] laborCost data:', costData);
      
      if (costData.length === 0) {
        console.log('[TeamDashboard Charts] No cost data, showing empty state');
        EmptyStates.chart('costChart', 'No labor cost data available');
        return;
      }
      
      // Check if all values are zero
      const totalCost = costData.reduce((sum, c) => sum + (c.cost || 0), 0);
      if (totalCost === 0) {
        console.log('[TeamDashboard Charts] All costs are zero, showing empty state');
        EmptyStates.chart('costChart', 'No labor costs recorded for this period');
        return;
      }
      
      EmptyStates.clear('costChart');
      
      const labels = costData.map(c => c.label);
      const costs = costData.map(c => c.cost || 0);
      
      // Calculate statistics for display
      const maxCost = Math.max(...costs);
      const avgCost = costs.reduce((a, b) => a + b, 0) / costs.length;
      const totalCosts = costs.reduce((a, b) => a + b, 0);
      
      console.log('[TeamDashboard Charts] Cost stats:', { max: maxCost, avg: avgCost, total: totalCosts });
      
      state.chartInstances.costChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Labor Cost',
            data: costs,
            borderColor: '#8b5cf6', // Purple to match KPI
            backgroundColor: (context) => {
              const chartCtx = context.chart.ctx;
              const gradient = chartCtx.createLinearGradient(0, 0, 0, 300);
              gradient.addColorStop(0, 'rgba(139, 92, 246, 0.3)');
              gradient.addColorStop(0.5, 'rgba(139, 92, 246, 0.1)');
              gradient.addColorStop(1, 'rgba(139, 92, 246, 0)');
              return gradient;
            },
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: '#8b5cf6',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointShadowBlur: 10,
            pointShadowColor: 'rgba(139, 92, 246, 0.5)'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.95)',
              titleColor: '#1e293b',
              bodyColor: '#475569',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              padding: 12,
              displayColors: false,
              callbacks: {
                title: (items) => items[0].label,
                label: (context) => `Labor Cost: ${Utils.formatCurrency(context.parsed.y)}`,
                afterLabel: (context) => {
                  const value = context.parsed.y;
                  const percentage = totalCosts > 0 ? ((value / totalCosts) * 100).toFixed(1) : 0;
                  return `(${percentage}% of total)`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: (value) => {
                  if (value >= 1000) {
                    return '£' + (value / 1000).toFixed(1) + 'k';
                  }
                  return '£' + value;
                },
                font: { size: 11, family: 'Inter, sans-serif' },
                color: '#64748b'
              },
              grid: { 
                color: 'rgba(0,0,0,0.03)',
                drawBorder: false
              },
              border: { display: false }
            },
            x: { 
              grid: { display: false },
              ticks: { 
                font: { size: 11, family: 'Inter, sans-serif' },
                color: '#64748b'
              },
              border: { display: false }
            }
          }
        }
      });
      
      console.log('[TeamDashboard Charts] Labor cost chart rendered successfully');
    }
  };
  
  // ============================================
  // DATA RENDERING
  // ============================================
  
  const Render = {
    // KPI Cards
    renderKPIs(stats) {
      const kpis = [
        {
          label: 'Total Employees',
          value: stats?.total_employees || 0,
          subtitle: `${stats?.on_site || 0} on site`,
          icon: 'users',
          type: 'success'
        },
        {
          label: 'Hours This Week',
          value: Utils.formatHours(stats?.week_hours || 0),
          subtitle: `${Utils.formatHours(stats?.week_overtime_hours || 0)} overtime`,
          icon: 'clock',
          type: 'info'
        },
        {
          label: 'Labor Cost',
          value: Utils.formatCurrency(stats?.labor_cost || 0),
          subtitle: 'This week',
          icon: 'credit-card',
          type: 'purple'
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
          <div class="pi-td-kpi-value">${kpi.value}</div>
          <div class="pi-td-kpi-subtitle">${kpi.subtitle}</div>
        </div>
      `).join('');

      $('#kpiCards').html(html);
      feather.replace();
    },
    
    // Activity Feed - Comprehensive Global Activity from ALL Jobs
    renderActivity(activities) {
      console.log('[TeamDashboard Render] renderActivity() called with:', activities?.length || 0, 'activities');
      
      if (!activities || activities.length === 0) {
        console.log('[TeamDashboard Render] No activities, showing empty state');
        $('#activityFeed').html(EmptyStates.activity());
        feather.replace();
        return;
      }
      
      // Activity type configurations for icons and colors
      const typeConfig = {
        timesheet: { icon: 'clock', color: '#3b82f6', label: 'Timesheet' },
        clock: { icon: 'watch', color: '#10b981', label: 'Attendance' },
        communication: { icon: 'message-square', color: '#8b5cf6', label: 'Communication' },
        document: { icon: 'file-text', color: '#64748b', label: 'Document' },
        safety_incident: { icon: 'alert-triangle', color: '#ef4444', label: 'Safety' },
        safety_inspection: { icon: 'clipboard', color: '#f59e0b', label: 'Inspection' },
        photo: { icon: 'camera', color: '#06b6d4', label: 'Photo' },
        change_order: { icon: 'file-plus', color: '#ec4899', label: 'Change Order' },
        rfi: { icon: 'help-circle', color: '#6366f1', label: 'RFI' },
        submittal: { icon: 'check-square', color: '#84cc16', label: 'Submittal' },
        daily_report: { icon: 'calendar', color: '#14b8a6', label: 'Report' },
        quality_snag: { icon: 'alert-circle', color: '#f97316', label: 'Quality' }
      };
      
      const html = activities.map(a => {
        const config = typeConfig[a.type] || { icon: 'activity', color: '#64748b', label: 'Activity' };
        const icon = a.icon || config.icon;
        const color = a.color || config.color;
        
        // Job badge - show job code if available
        const jobBadge = a.job_code && a.job_code !== 'Global' 
          ? `<span class="pi-td-activity-job-badge" style="background: ${color}20; color: ${color}; border: 1px solid ${color}40;">${a.job_code}</span>`
          : '<span class="pi-td-activity-job-badge pi-global">Global</span>';
        
        // Status badge
        let statusBadge = '';
        if (a.status && a.status !== 'active' && a.status !== 'completed' && a.status !== 'submitted') {
          const statusClass = a.status.toLowerCase().replace(/_/g, '-');
          statusBadge = `<span class="pi-td-activity-status ${statusClass}">${a.status}</span>`;
        }
        
        // Employee avatar or icon
        const avatar = a.avatar_url 
          ? `<img src="${a.avatar_url}" class="pi-td-activity-avatar" alt="">`
          : `<div class="pi-td-activity-icon-small" style="background: ${color}20; color: ${color};"><i data-feather="${icon}" style="width: 14px; height: 14px;"></i></div>`;
        
        return `
          <div class="pi-td-activity-item ${a.type}" data-job-id="${a.job_id || ''}" data-activity-id="${a.id}">
            ${avatar}
            <div class="pi-td-activity-content">
              <div class="pi-td-activity-header">
                <p class="pi-td-activity-desc">${a.description || 'Activity'}</p>
                ${statusBadge}
              </div>
              <div class="pi-td-activity-subtitle">
                ${jobBadge}
                <span class="pi-td-activity-user">${a.employee_name || 'System'}</span>
                <span class="pi-td-activity-time">${Utils.timeAgo(a.timestamp)}</span>
              </div>
            </div>
          </div>
        `;
      }).join('');
      
      $('#activityFeed').html(html);
      feather.replace();
      
      // Add click handlers for job links
      $('#activityFeed .pi-td-activity-item[data-job-id]').on('click', function() {
        const jobId = $(this).data('job-id');
        if (jobId && window.TeamApp?.viewJob) {
          window.TeamApp.viewJob(jobId);
        }
      });
    },
    
    // Top Performers Grid - Modern Redesign
    renderEmployees(performers) {
      if (!performers || performers.length === 0) {
        $('#employeeGrid').html(EmptyStates.employees());
        feather.replace();
        return;
      }
      
      const maxHours = Math.max(...performers.map(p => p.total_hours || 0));
      
      const html = performers.map((e, index) => {
        const hours = e.total_hours || 0;
        const percentage = maxHours > 0 ? (hours / maxHours) * 100 : 0;
        
        return `
        <div class="pi-td-employee-card" onclick="TeamDashboard.viewEmployee(${e.id})">
          <div class="pi-td-employee-avatar" style="background: ${Utils.generateAvatarColor(e.first_name + ' ' + e.last_name)}">
            ${e.first_name.charAt(0)}${e.last_name.charAt(0)}
          </div>
          <div class="pi-td-employee-info">
            <h4>${e.first_name} ${e.last_name}</h4>
            <p>${e.trade || e.role || 'Worker'}</p>
            <div class="pi-td-performance-bar">
              <div class="pi-td-performance-fill" style="width: ${percentage}%"></div>
            </div>
          </div>
          <div class="pi-td-employee-hours">
            <span class="pi-td-hours-value">${Utils.formatHours(hours)}</span>
            <span class="pi-td-hours-label">hours</span>
          </div>
        </div>
      `;
      }).join('');
      
      $('#employeeGrid').html(html);
      feather.replace();
    },
    
    // Crew Grid
    renderCrews(crews) {
      if (!crews || crews.length === 0) {
        $('#crewGrid').html(EmptyStates.crews());
        feather.replace();
        return;
      }
      
      const html = crews.map(c => `
        <div class="pi-td-crew-card" onclick="TeamDashboard.viewCrew(${c.id})">
          <div class="pi-td-crew-header">
            <h4>${c.crew_name}</h4>
            <span class="pi-td-crew-count">${c.member_count || 0} members</span>
          </div>
          <div class="pi-td-crew-meta">
            <span class="pi-td-crew-trade">${c.trade_specialty || 'General'}</span>
            <span class="pi-td-crew-status ${c.status === 'active' ? 'active' : 'inactive'}">
              ${c.status === 'active' ? 'Active' : 'Inactive'}
            </span>
          </div>
        </div>
      `).join('');
      
      $('#crewGrid').html(html);
      feather.replace();
    }
  };
  
  // ============================================
  // DATA LOADING
  // ============================================
  
  const DataLoader = {
    async loadAll() {
      console.log('[TeamDashboard] ============================================');
      console.log('[TeamDashboard] Loading all dashboard data...');
      console.log('[TeamDashboard] REST URL:', config.restUrl);
      console.log('[TeamDashboard] Nonce available:', config.nonce ? 'Yes' : 'No');
      console.log('[TeamDashboard] ============================================');
      
      try {
        // Detect job_id from URL for job-specific views
        const jobId = Utils.getJobIdFromUrl();
        state.filters.jobId = jobId;
        
        console.log('[TeamDashboard] Job ID:', jobId || 'Global (all jobs)');
        
        // Build API params with job_id when available
        const apiParams = jobId ? { job_id: jobId } : {};
        const employeeParams = jobId ? { job_id: jobId } : {};
        
        // Load all data in parallel for performance
        console.log('[TeamDashboard] Starting API calls...');
        
        const results = await Promise.allSettled([
          API.getDashboardStats(apiParams),
          API.getWeeklyHours(apiParams),
          API.getActivity(50, jobId), // Job-specific activity
          API.getTradeDistribution(jobId), // Job-specific trade distribution
          API.getAttendanceTrends(state.filters.dateRange, jobId), // Job-specific attendance
          API.getLaborCost(state.filters.dateRange, jobId), // Job-specific labor cost
          API.getTopPerformers(4, jobId), // Job-specific top performers
          API.getEmployees(employeeParams), // Job-specific employees
          API.getCrews(jobId) // Job-specific crews
        ]);
        
        console.log('[TeamDashboard] API Results:', results.map((r, i) => ({ 
          index: i, 
          status: r.status, 
          reason: r.reason?.message 
        })));
        
        const [
          stats,
          weeklyHours,
          activity,
          tradeDistribution,
          attendanceTrends,
          laborCost,
          topPerformers,
          employees,
          crews
        ] = results.map(r => r.status === 'fulfilled' ? r.value : (r.reason ? null : []));
        
        // Update state - extract activities array from response structure
        state.stats = stats;
        state.weeklyHours = weeklyHours;
        state.activity = activity?.activities || activity || []; // Handle both new {activities: [...]} and old [...] format
        state.tradeDistribution = tradeDistribution;
        state.attendanceTrends = attendanceTrends;
        state.laborCost = laborCost;
        state.topPerformers = topPerformers;
        state.employees = employees;
        state.crews = crews;
        
        console.log('[TeamDashboard] Activity loaded:', activity?.total || state.activity.length, 'items from', activity?.period_days || 'N/A', 'days');
        
        console.log('[TeamDashboard] State after loading:', {
          weeklyHours: state.weeklyHours,
          tradeDistribution: state.tradeDistribution,
          attendanceTrends: state.attendanceTrends,
          laborCost: state.laborCost,
          activity: state.activity?.length || 0,
          topPerformers: state.topPerformers?.length || 0,
          crews: state.crews?.length || 0
        });
        
        // Render all components
        this.renderAll();
        
        // Update refresh time
        $('#lastRefresh').text('Updated ' + new Date().toLocaleTimeString());
        
        console.log('[TeamDashboard] All data loaded successfully');
      } catch (error) {
        console.error('[TeamDashboard] Error loading data:', error);
        this.showError('Failed to load dashboard data. Please try refreshing.');
      }
    },
    
    renderAll() {
      console.log('[TeamDashboard] DataLoader.renderAll() called');
      
      // Render KPIs
      Render.renderKPIs(state.stats);
      
      // Render charts (ChartJS is guaranteed to be loaded by now)
      Charts.renderAll();
      
      // Render lists
      Render.renderActivity(state.activity);
      Render.renderEmployees(state.topPerformers);
      Render.renderCrews(state.crews);
    },
    
    async refresh() {
      $('#lastRefresh').text('Updating...');
      await this.loadAll();
    },
    
    showError(message) {
      $('#kpiCards').html(`
        <div class="pi-td-error-state" style="grid-column: 1/-1; text-align: center; padding: 40px;">
          <i data-feather="alert-triangle" style="width: 48px; height: 48px; color: var(--pi-danger); margin-bottom: 16px;"></i>
          <p style="color: var(--pi-text-muted); margin-bottom: 16px;">${message}</p>
          <button class="pi-td-btn pi-td-btn-primary" onclick="TeamDashboard.refresh()">
            <i data-feather="refresh-cw"></i> Try Again
          </button>
        </div>
      `);
      feather.replace();
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
      
      // Auto-refresh every 5 minutes
      setInterval(() => {
        DataLoader.refresh();
      }, 5 * 60 * 1000);
    }
  };
  
  // ============================================
  // INITIALIZATION
  // ============================================
  
  async function init() {
    if (!$('#teamDashboardV3').length) return;
    
    console.log('[TeamDashboard] Initializing v3.4.0...');
    
    // Initialize Feather icons
    if (window.feather) {
      feather.replace();
    }
    
    // Setup events
    Events.init();
    
    // Initialize charts first (loads ChartJS)
    await Charts.init();
    
    // Then load all data (will re-render charts with real data)
    await DataLoader.loadAll();
    
    console.log('[TeamDashboard] Ready');
  }

  // Initialize when DOM is ready
  $(document).ready(init);

  // Expose to global scope
  window.TeamDashboard = {
    refresh: () => DataLoader.refresh(),
    getState: () => state,
    
    // Modal proxies - properly wired to TeamApp
    openAddTimesheetModal: () => window.TeamApp?.openAddTimesheetModal?.(),
    openAddEmployeeModal: () => window.TeamApp?.openAddEmployeeModal?.(),
    openAddCrewModal: () => window.TeamApp?.openAddCrewModal?.(),
    viewEmployee: (id) => window.TeamApp?.viewEmployee?.(id),
    viewCrew: (id) => window.TeamApp?.viewCrew?.(id),
    exportTimesheets: () => window.TeamApp?.exportTimesheets?.()
  };

})(jQuery);
