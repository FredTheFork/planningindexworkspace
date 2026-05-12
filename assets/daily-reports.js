/**
 * Planning Index CRM - Daily Reports JavaScript
 * Frontend functionality for daily reports system
 */

(function($) {
    'use strict';
    
    // Ensure global object exists for early onclick handlers
    window.PI_Daily_Reports = window.PI_Daily_Reports || {};
    
    // Add materialRequestInProgress property if it doesn't exist
    if (typeof window.PI_Daily_Reports.materialRequestInProgress === 'undefined') {
        window.PI_Daily_Reports.materialRequestInProgress = false;
    }
    
    // Add early function stubs to prevent undefined errors
    window.PI_Daily_Reports.openMaterialModal = function() {
        console.warn('PI_Daily_Reports.openMaterialModal called before initialization');
    };
    window.PI_Daily_Reports.syncMaterials = function() {
        console.warn('PI_Daily_Reports.syncMaterials called before initialization');
    };
    window.PI_Daily_Reports.editMaterial = function(id) {
        console.warn('PI_Daily_Reports.editMaterial called before initialization');
    };
    window.PI_Daily_Reports.deleteMaterial = function(id) {
        console.warn('PI_Daily_Reports.deleteMaterial called before initialization');
    };
    window.PI_Daily_Reports.recordStockMovement = function(id) {
        console.warn('PI_Daily_Reports.recordStockMovement called before initialization');
    };
    window.PI_Daily_Reports.closeModal = function() {
        console.warn('PI_Daily_Reports.closeModal called before initialization');
    };
    window.PI_Daily_Reports.saveMaterial = function() {
        console.warn('PI_Daily_Reports.saveMaterial called before initialization');
    };
    window.PI_Daily_Reports.saveStockMovement = function(id) {
        console.warn('PI_Daily_Reports.saveStockMovement called before initialization');
    };
    window.PI_Daily_Reports.showModal = function(content, title, footer) {
        console.warn('PI_Daily_Reports.showModal called before initialization');
    };
    window.PI_Daily_Reports.showLoading = function(message) {
        console.warn('PI_Daily_Reports.showLoading called before initialization');
    };
    window.PI_Daily_Reports.hideLoading = function() {
        console.warn('PI_Daily_Reports.hideLoading called before initialization');
    };
    
    const PI_Daily_Reports = {
        jobId: null,
        reportId: null,
        reportData: null,
        apiBase: null,
        nonce: null,
        clockedIn: false,
        onBreak: false,
        autoSaveInterval: null,
        activeRequests: 0,
        isCreatingReport: false,
        reportCreationAttempts: 0,
        
        init: function() {
            if (this._initialized) {
                console.log('[DailyReports] Already initialized, skipping');
                return;
            }
            this._initialized = true;

            const $app = $('#pi-daily-reports-app');
            if (!$app.length) {
                console.error('Daily Reports app container not found');
                return;
            }

            // Hide loading screen initially
            $('#pi-dr-loading').hide();

            // Validate required settings
            if (!PI_Daily_Reports_Settings || !PI_Daily_Reports_Settings.rest_base || !PI_Daily_Reports_Settings.nonce) {
                console.error('Daily Reports settings not properly loaded');
                this.showToast('Daily Reports configuration error. Please refresh the page.', 'error');
                return;
            }

            this.jobId = $app.data('job-id');
            this.apiBase = PI_Daily_Reports_Settings.rest_base;
            this.nonce = PI_Daily_Reports_Settings.nonce;

            if (!this.jobId) {
                console.error('Job ID not found');
                this.showToast('Job ID is required. Please refresh the page.', 'error');
                return;
            }

            try {
                this.bindEvents();
                this.loadTodaysReport();
                this.loadDashboardData();
                this.checkClockStatus();
                this.startAutoSave();
            } catch (error) {
                console.error('Error during Daily Reports initialization:', error);
                this.showToast('Failed to initialize Daily Reports. Please refresh the page.', 'error');
                this.hideLoadingOverlay();
            }
            
            // Safety timeout to hide loading overlay if stuck
            setTimeout(() => {
                if (this.activeRequests > 0) {
                    console.warn('Loading overlay hidden due to timeout - some requests may have failed');
                    this.activeRequests = 0;
                    this.hideLoadingOverlay();
                    this.showToast('Some features may not be available. Please refresh if needed.', 'warning');
                }
            }, 15000); // 15 second timeout
        },
        
        hideLoadingOverlay: function() {
            this.activeRequests = 0;
            $('#pi-dr-loading').hide();
        },
        
        // ============================================
        // API CALLS
        // ============================================
        
        apiCall: function(endpoint, method, data = {}) {
            const self = this;
            this.activeRequests++;
            
            if (this.activeRequests === 1) {
                $('#pi-dr-loading').show();
            }
            
            // Add timeout to prevent hanging
            const timeout = setTimeout(() => {
                if (self.activeRequests > 0) {
                    console.warn(`API call to ${endpoint} timed out`);
                    self.activeRequests = 0;
                    $('#pi-dr-loading').hide();
                    self.showToast('Request timed out. Please try again.', 'error');
                }
            }, 15000); // 15 second timeout per request
            
            return $.ajax({
                url: this.apiBase + endpoint,
                method: method,
                data: JSON.stringify(data),
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': this.nonce
                },
                timeout: 10000 // 10 second jQuery timeout
            }).always(function() {
                clearTimeout(timeout);
                self.activeRequests--;
                if (self.activeRequests <= 0) {
                    self.activeRequests = 0;
                    $('#pi-dr-loading').hide();
                }
            }).fail(function(xhr, status, error) {
                clearTimeout(timeout);
                console.error(`API call failed: ${endpoint}`, {status, error, xhr});
                self.activeRequests = Math.max(0, self.activeRequests - 1);
                if (self.activeRequests <= 0) {
                    self.activeRequests = 0;
                    $('#pi-dr-loading').hide();
                }
                
                // Show user-friendly error message
                let errorMessage = 'Request failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMessage = 'Endpoint not found. Please refresh the page.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                }
                
                self.showToast(errorMessage, 'error');
            });
        },
        
        // ============================================
        // REPORT LOADING
        // ============================================
        
        loadTodaysReport: function() {
            const self = this;

            // Prevent infinite loop
            if (this.isCreatingReport) {
                console.warn('Report creation in progress, skipping load');
                return;
            }

            this.apiCall(`/reports?job_id=${this.jobId}`, 'GET')
                .done(function(response) {
                    if (response && response.data && Array.isArray(response.data)) {
                        // Look for today's report
                        const today = new Date().toISOString().split('T')[0];
                        const todaysReport = response.data.find(report => report.report_date === today);
                        
                        if (todaysReport) {
                            self.reportData = todaysReport;
                            self.reportId = todaysReport.id;
                            self.renderReport(todaysReport);
                        } else if (response.data.length > 0) {
                            // Use the most recent report if today's doesn't exist
                            self.reportData = response.data[0];
                            self.reportId = response.data[0].id;
                            self.renderReport(response.data[0]);
                        } else {
                            console.warn('No reports found, creating new report');
                            self.createNewReport();
                        }
                    } else {
                        console.warn('Invalid response structure, creating new report');
                        self.createNewReport();
                    }
                })
                .fail(function() {
                    console.error('Failed to load today\'s report');
                    self.showToast('Failed to load report. Creating new report...', 'warning');
                    self.createNewReport();
                });
        },
        
        createNewReport: function() {
            const self = this;
            
            // Prevent infinite loop and limit attempts
            if (this.isCreatingReport) {
                console.warn('Report creation already in progress');
                return;
            }
            
            if (this.reportCreationAttempts >= 3) {
                console.error('Maximum report creation attempts reached');
                this.showToast('Unable to create report after multiple attempts. Please refresh the page.', 'error');
                this.hideLoadingOverlay();
                return;
            }
            
            this.isCreatingReport = true;
            this.reportCreationAttempts++;
            
            // Safety timeout to reset creation flag
            setTimeout(() => {
                if (this.isCreatingReport) {
                    console.warn('Report creation timeout, resetting flag');
                    this.isCreatingReport = false;
                }
            }, 10000); // 10 second timeout
            
            this.apiCall('/reports', 'POST', {
                job_id: this.jobId,
                report_date: new Date().toISOString().split('T')[0]
            })
                .done(function(response) {
                    if (response && response.id) {
                        self.reportId = response.id;
                        self.reportCreationAttempts = 0; // Reset counter on success
                        
                        // Load the newly created report directly instead of calling loadTodaysReport
                        self.loadReportById(response.id);
                    } else {
                        console.error('Invalid response from report creation:', response);
                        self.showToast('Error creating report: Invalid response', 'error');
                        self.isCreatingReport = false;
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Failed to create report:', {status, error, xhr});
                    self.showToast('Error creating report: ' + (xhr.responseJSON?.message || 'Unknown error'), 'error');
                    self.isCreatingReport = false;
                });
        },
        
        loadReportById: function(reportId) {
            const self = this;
            
            this.apiCall(`/reports/${reportId}`, 'GET')
                .done(function(response) {
                    if (response && response.data) {
                        self.reportData = response.data;
                        self.isCreatingReport = false;
                        self.renderReport(response.data);
                    } else {
                        console.error('Invalid response when loading report by ID:', response);
                        self.isCreatingReport = false;
                        self.showToast('Error loading newly created report', 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Failed to load report by ID:', {status, error, xhr});
                    self.isCreatingReport = false;
                    self.showToast('Error loading report: ' + (xhr.responseJSON?.message || 'Unknown error'), 'error');
                });
        },
        
        renderReport: function(data) {
            // Reset creation attempts on successful render
            this.reportCreationAttempts = 0;
            this.isCreatingReport = false;
            
            $('#pi-dr-report-number').text(data.report_number || '-');
            $('#pi-dr-report-date').text(data.report_date || '-');
            $('#pi-dr-report-status').text(data.report_status || 'Draft').addClass('pi-dr-status-' + (data.report_status || 'draft'));
            
            // Load related data
            this.loadReportSections();
            
            // Update submit button visibility
            if (data.report_status === 'draft') {
                $('#pi-dr-btn-submit').show();
            } else if (data.report_status === 'submitted') {
                $('#pi-dr-btn-submit').hide();
                $('#pi-dr-btn-approve').show();
            } else {
                $('#pi-dr-btn-submit').hide();
                $('#pi-dr-btn-approve').hide();
            }
            
            // Load text fields
            $('#pi-dr-general-notes').val(data.general_notes || '');
            $('#pi-dr-client-comm').val(data.client_communications || '');
            $('#pi-dr-tomorrow-blockers').val(data.tomorrow_blockers || '');
        },
        
        loadReportSections: function() {
            this.loadLabor();
            this.loadActivities();
            this.syncEquipment();
            this.loadEquipment();
            this.loadMaterials();
            this.loadSafety();
            this.loadPhotos();
            this.loadRatings();
            this.loadVisitors();
        },
        
        // ============================================
        // DASHBOARD
        // ============================================
        
        loadDashboardData: function() {
            const self = this;

            this.apiCall(`/dashboard/${this.jobId}`, 'GET')
                .done(function(response) {
                    $('#pi-dr-headcount').text(response.headcount || 0);
                    $('#pi-dr-incidents').text(response.open_incidents || 0);
                    $('#pi-dr-equipment-issues').text(response.equipment_issues || 0);
                    $('#pi-dr-score').text(response.today_score?.letter_grade || '-');
                    $('#pi-dr-status').text(response.report_status || '-');
                })
                .fail(function() {
                    $('#pi-dr-headcount').text('-');
                    $('#pi-dr-incidents').text('-');
                    $('#pi-dr-equipment-issues').text('-');
                    $('#pi-dr-score').text('-');
                    $('#pi-dr-status').text('-');
                });
            
            // Load weather data
            this.loadWeather();
        },
        
        // ============================================
        // WEATHER
        // ============================================
        
        loadWeather: function() {
            const self = this;
            const teamApiBase = (PI_Daily_Reports_Settings.team_rest_base || PI_Daily_Reports_Settings.rest_base.replace('/daily-reports/v1', '/v1')).replace(/\/+$/, '');
            
            fetch(`${teamApiBase}/weather?job_id=${this.jobId}`, {
                headers: {
                    'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(response => {
                this.weather = Array.isArray(response) ? response : (response?.data || []);
                if (response?.error) {
                    this.weather = { error: true, message: response.message, has_address: response.has_address };
                }
                this.renderWeatherWidget();
            })
            .catch(error => {
                console.error('[DailyReports] Failed to load weather:', error);
                this.weather = { error: true, message: 'Failed to load weather data' };
                this.renderWeatherWidget();
            });
        },
        
        renderWeatherWidget: function() {
            const loadingEl = $('#crm-weather-loading');
            const dataEl = $('#crm-weather-data');
            const errorEl = $('#crm-weather-error');
            
            // Check if weather data is an error response
            if (this.weather && this.weather.error) {
                loadingEl.hide();
                dataEl.hide();
                errorEl.show();
                $('#crm-weather-error-message').text(this.weather.message || 'Unable to load weather data');
                return;
            }
            
            // Check if we have weather data
            if (!this.weather || !Array.isArray(this.weather) || this.weather.length === 0) {
                loadingEl.hide();
                dataEl.hide();
                errorEl.show();
                $('#crm-weather-error-message').text('No weather data available. Set a site location to get weather forecasts.');
                return;
            }
            
            // We have data - show it
            loadingEl.hide();
            errorEl.hide();
            dataEl.show();
            
            const today = this.weather[0];
            
            // Weather icon
            const iconSvg = this.getWeatherIcon(today.weather_code, today.condition_text, 40);
            $('#crm-weather-icon').html(iconSvg);
            
            // Temperature
            const tempHigh = parseFloat(today.temperature_high) || 0;
            const tempLow = parseFloat(today.temperature_low) || 0;
            $('#crm-weather-temp').text(`${Math.round(tempHigh)}° / ${Math.round(tempLow)}°`);
            
            // Condition text
            $('#crm-weather-condition').text(today.condition_text);
            
            // Wind and precipitation
            const windSpeed = parseFloat(today.wind_speed) || 0;
            const precipAmount = parseFloat(today.precipitation_amount) || 0;
            $('#crm-weather-wind').text(`${Math.round(windSpeed)} km/h`);
            $('#crm-weather-precip').text(`${precipAmount.toFixed(1)} mm`);
            
            // Work recommendation
            $('#crm-weather-recommendation').text(today.work_recommendation);
            
            // Impact score with color (lower score = better conditions = green)
            const impact = parseInt(today.work_impact_score) || 5;
            const impactColor = impact >= 7 ? '#ef4444' : (impact >= 5 ? '#f59e0b' : '#22c55e');
            $('#crm-weather-impact').css({
                'background': impactColor,
                'color': '#fff'
            }).text(`Work Impact: ${impact}/10`);
            
            // 3-day forecast
            const forecastHtml = this.weather.slice(1, 4).map(day => {
                const date = new Date(day.forecast_date);
                const dayName = date.toLocaleDateString('en-GB', { weekday: 'short' });
                const icon = this.getWeatherIcon(day.weather_code, day.condition_text, 16);
                const tempHigh = parseFloat(day.temperature_high) || 0;
                return `
                    <div class="crm-forecast-day">
                        <span class="crm-forecast-name">${dayName}</span>
                        <span class="crm-forecast-icon">${icon}</span>
                        <span class="crm-forecast-temp">${Math.round(tempHigh)}°</span>
                    </div>
                `;
            }).join('');
            $('#crm-weather-forecast').html(forecastHtml);
        },
        
        getWeatherIcon: function(code, condition, size = 48) {
            // Embedded Weather Icons - Professional SVG icons for all weather conditions
            const WeatherIcons = {
                sunny: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19.566 5.163a6.746 6.746 0 0 1 8.872 0 9.746 9.746 0 0 0 5.747 2.38 6.747 6.747 0 0 1 6.273 6.274 9.746 9.746 0 0 0 2.38 5.747 6.746 6.746 0 0 1 0 8.871 9.746 9.746 0 0 0-2.38 5.747 6.747 6.747 0 0 1-6.273 6.274 9.746 9.746 0 0 0-5.748 2.38 6.746 6.746 0 0 1-8.87 0 9.746 9.746 0 0 0-5.748-2.38 6.747 6.747 0 0 1-6.273-6.274 9.746 9.746 0 0 0-2.381-5.747 6.746 6.746 0 0 1 0-8.871 9.746 9.746 0 0 0 2.38-5.747 6.747 6.747 0 0 1 6.274-6.274 9.746 9.746 0 0 0 5.747-2.38z" fill="#FCBD00" stroke="#DA4F00" stroke-width="3"/></svg>',
                heavy_rain: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.514 2.005C39.02 2.208 46 8.762 46 16.999l-.008.473-.022.454a14.187 14.187 0 0 1-.536 3.05l-2.54-2.453c.05-.367.085-.74.098-1.116l.007-.436c-.016-6.409-5.492-11.788-12.534-11.967L30.093 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.25 0-7.598 2.97-8.081 6.629l-2.885 2.788a9.574 9.574 0 0 1-.12-.8l-.042-.524-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path d="M20.403 36.433l.036.034a5.479 5.479 0 0 1 0 7.898c-2.242 2.18-5.876 2.18-8.117 0a5.479 5.479 0 0 1 0-7.898l4.058-3.948 4.023 3.914zm15.305 0l.035.034a5.478 5.478 0 0 1 0 7.898c-2.241 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.898l4.057-3.948 4.024 3.914zM11.794 25.84l.036.035a5.479 5.479 0 0 1 0 7.897c-2.241 2.18-5.874 2.18-8.115 0a5.479 5.479 0 0 1-.001-7.897l4.057-3.95 4.023 3.915zm16.261-1.925l.036.034a5.479 5.479 0 0 1 0 7.897c-2.241 2.18-5.875 2.18-8.117 0a5.479 5.479 0 0 1 0-7.897L24.032 20l4.023 3.915zm16.261-.001l.036.035a5.478 5.478 0 0 1 0 7.897c-2.241 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.897L40.292 20l4.023 3.914z" fill="#0B57D0"/></svg>',
                partly_cloudy: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M23.55 3.315a8.246 8.246 0 0 1 10.524 2.612 8.246 8.246 0 0 0 4.235 3.126 8.246 8.246 0 0 1 5.595 9.29 8.244 8.244 0 0 0 .069 3.222c-2.96-2.758-6.962-4.456-11.276-4.56h-.037L32.31 17h-.035c-5.982 0-11.353 3.032-14.32 7.728l-.332-.004c-6.189 0-11.693 4.406-12.517 10.538A8.226 8.226 0 0 1 4.1 29.657a8.247 8.247 0 0 0-.784-5.206 8.246 8.246 0 0 1 2.612-10.524 8.246 8.246 0 0 0 3.127-4.234 8.246 8.246 0 0 1 9.289-5.595 8.246 8.246 0 0 0 5.205-.783z" fill="#FCBD00"/><path d="M23.55 3.315a8.246 8.246 0 0 1 10.524 2.612 8.245 8.245 0 0 0 4.235 3.126 8.246 8.246 0 0 1 5.595 9.29 8.25 8.25 0 0 0 .069 3.223 16.827 16.827 0 0 0-3.183-2.331c.023-.473.076-.946.16-1.416a5.246 5.246 0 0 0-3.56-5.91 11.249 11.249 0 0 1-5.774-4.264 5.246 5.246 0 0 0-6.696-1.661 11.247 11.247 0 0 1-7.098 1.068 5.246 5.246 0 0 0-5.91 3.56 11.247 11.247 0 0 1-4.264 5.774 5.246 5.246 0 0 0-1.661 6.695 11.248 11.248 0 0 1 1.068 7.099l-.007.041a11.661 11.661 0 0 0-1.942 5.041A8.226 8.226 0 0 1 4.1 29.657a8.246 8.246 0 0 0-.636-4.902l-.147-.304a8.246 8.246 0 0 1 2.301-10.296l.311-.228a8.247 8.247 0 0 0 3.126-4.234 8.246 8.246 0 0 1 9.289-5.595 8.246 8.246 0 0 0 4.901-.636l.304-.147z" fill="#DA4F00"/><path d="M32.59 21.503c6.62.16 11.91 5.258 11.91 11.496l-.007.378c-.126 3.684-2.098 6.937-5.08 8.957a12.552 12.552 0 0 1-4.373 1.864c-2.867.347-5.707.3-8.754.3h-8.663c-4.22-.001-7.676-3.027-8.083-6.867l-.03-.374-.01-.397c0-4.202 3.623-7.636 8.123-7.636.588 0 1.162.059 1.714.17l1.177.239.49-1.096c1.848-4.128 6.198-7.034 11.264-7.037l.322.003z" stroke="#70757A" stroke-width="3"/></svg>',
                cloudy: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.1 11.5l.396.004c7.655.191 13.808 5.983 14 13.152L44.5 25l-.008.44c-.148 4.33-2.474 8.15-5.984 10.521a14.802 14.802 0 0 1-5.15 2.188c-3.345.404-6.656.35-10.184.35H13.143c-5.002-.001-9.11-3.575-9.595-8.13l-.035-.444-.013-.469c0-4.83 4.045-8.788 9.146-9.031l.497-.012c.697 0 1.377.07 2.032.202l1.175.237.49-1.092c2.177-4.848 7.3-8.256 13.26-8.259z" stroke="#70757A" stroke-width="3"/></svg>',
                drizzle: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M35.707 36.433l.035.034a5.479 5.479 0 0 1 0 7.898c-2.24 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.898l4.058-3.948 4.023 3.914z" fill="#0B57D0"/><path d="M30.1 3.5l.396.004c7.655.191 13.808 5.983 14 13.152L44.5 17l-.008.44c-.148 4.33-2.474 8.15-5.984 10.521a14.802 14.802 0 0 1-5.15 2.188c-3.345.404-6.656.35-10.184.35H13.143c-5.002-.001-9.11-3.575-9.595-8.13l-.035-.444-.013-.469c0-4.83 4.045-8.788 9.146-9.031l.497-.012c.697 0 1.377.07 2.032.202l1.175.237.49-1.092c2.177-4.85 7.3-8.257 13.26-8.26z" stroke="#70757A" stroke-width="3"/></svg>',
                snow_showers: '<svg width="20" height="20" viewBox="0 0 49 49" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.598 2.86c8.338.2 15.208 6.502 15.476 14.51l-3.125-1.214c-.866-5.647-5.98-10.133-12.4-10.297l-.372-.004c-5.387.006-9.96 3.086-11.885 7.373l-.982 2.186-2.348-.474a8.726 8.726 0 0 0-1.291-.16l-.444-.012c-4.592 0-8.13 3.465-8.143 7.521l.012.425c.217 3.88 3.686 7.138 8.13 7.139h2.145l1.888 3h-4.032c-5.693 0-10.515-4.084-11.086-9.468l-.042-.525-.015-.55c0-5.905 5.077-10.542 11.143-10.542.797 0 1.576.08 2.329.231C17.985 6.59 23.66 2.856 30.19 2.855l.407.005z" fill="#70757A"/><path d="M37.339 37.787l3.045-1.622.246-.132.896 1.432-3.274 1.743 3.274 1.747-.896 1.432-3.291-1.755v3.516h-1.752v-3.517l-3.292 1.756-.15-.238-.746-1.194 3.274-1.747-3.274-1.743.895-1.432.248.132 3.045 1.623v-3.515h1.752v3.514zM26.32 27.4l3.989-2.127.84 1.344.171.273-3.971 2.114 3.971 2.119-1.011 1.615-3.989-2.127v4.261h-1.983v-4.26l-3.988 2.126-.84-1.343-.171-.272 3.969-2.117-3.686-1.965-.283-.151 1.01-1.617.248.132 3.74 1.994v-4.26h1.984v4.26zm14.844-3.71l3.988-2.127.841 1.344.171.273-3.972 2.114 3.972 2.12-1.012 1.614-3.988-2.127v4.261h-1.983v-4.26l-3.989 2.126-.84-1.342-.17-.273 3.968-2.117-3.685-1.965-.283-.151 1.01-1.616.248.131 3.74 1.994v-4.26h1.984v4.26z" fill="#303134"/></svg>',
                heavy_snow: '<svg width="20" height="20" viewBox="0 0 49 49" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.598 2.86c8.338.2 15.208 6.502 15.476 14.51l-3.126-1.215c-.866-5.646-5.979-10.132-12.4-10.296l-.371-.004c-5.387.006-9.96 3.086-11.885 7.373l-.982 2.186-2.348-.474a8.726 8.726 0 0 0-1.291-.16l-.444-.012c-4.592 0-8.13 3.465-8.143 7.521l.012.425c.027.49.107.972.234 1.437h-.077l-1.887 3.076a10.033 10.033 0 0 1-1.225-3.842l-.042-.525-.015-.55c0-5.905 5.077-10.542 11.143-10.542.797 0 1.576.08 2.329.231C17.985 6.59 23.66 2.856 30.19 2.855l.407.005z" fill="#70757A"/><path d="M23.42 40.57l3.045-1.622.246-.132.896 1.432-3.274 1.743 3.274 1.747-.896 1.432-3.291-1.755v3.518h-1.753v-3.518l-3.044 1.623-.248.132-.895-1.432 3.273-1.747-3.273-1.743.895-1.432 3.292 1.754v-3.514h1.753v3.514zm13.917-2.783l3.045-1.622.246-.132.896 1.432-3.274 1.743 3.274 1.747-.896 1.432-3.291-1.755v3.516h-1.752v-3.517l-3.292 1.756-.15-.238-.745-1.194 3.273-1.747-3.273-1.743.894-1.432.248.132 3.045 1.623v-3.515h1.752v3.514zm-25.86-6.677l3.988-2.126.84 1.344.172.273-.284.151-3.686 1.965 3.97 2.117-.171.272-.841 1.344-3.988-2.127v4.26H9.493v-4.26l-3.741 1.995-.248.132-1.01-1.616 3.969-2.117-3.686-1.965-.283-.151 1.01-1.617.248.132 3.741 1.994v-4.26h1.984v4.26zm14.841-3.71l3.989-2.127.84 1.344.171.273-3.971 2.114 3.971 2.119-1.011 1.615-3.989-2.127v4.261h-1.983v-4.26l-3.988 2.126-.84-1.343-.171-.272 3.969-2.117-3.686-1.965-.283-.151 1.01-1.617.248.132 3.741 1.994v-4.26h1.983v4.26zm14.844-3.71l3.988-2.127.841 1.344.171.273-3.972 2.114 3.972 2.12-1.012 1.614-3.988-2.127v4.261H39.18v-4.26l-3.989 2.126-.84-1.342-.17-.273 3.968-2.117-3.685-1.965-.283-.151 1.01-1.616.248.131 3.74 1.994v-4.26h1.984v4.26z" fill="#303134"/></svg>',
                blizzard: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.049 29.535a5.881 5.881 0 0 1 5.875 5.876 5.88 5.88 0 0 1-5.875 5.875h-1.53v-3.058h1.53a2.824 2.824 0 0 0 2.817-2.817 2.823 2.823 0 0 0-2.817-2.817H4v-3.059h8.049zm26.075-7.064A5.882 5.882 0 0 1 44 28.347a5.882 5.882 0 0 1-5.876 5.875h-1.529v-3.058h1.53a2.824 2.824 0 0 0 2.816-2.817 2.823 2.823 0 0 0-2.817-2.816H4v-3.06h34.124zM21.827 6.716a5.88 5.88 0 0 1 5.875 5.875 5.88 5.88 0 0 1-5.875 5.876H4v-3.059h17.827a2.824 2.824 0 0 0 2.817-2.816 2.823 2.823 0 0 0-2.817-2.817h-1.529v-3.06h1.53z" fill="#70757A"/><path d="M28.788 28.991v4.715l.15-.087 3.922-2.275.986 1.716-4.073 2.35 4.073 2.351-.987 1.717-3.92-2.263-.151-.088v4.703h-1.973v-4.703l-.15.088-3.933 2.263-.976-1.717 4.074-2.35-4.074-2.35.986-1.717 3.923 2.264.15.086v-4.703h1.973zM38.903 6.172v4.715l.15-.087 3.922-2.275.987 1.716-4.073 2.35 4.073 2.351-.988 1.717-3.92-2.263-.15-.088v4.703H36.93v-4.703l-.15.088-3.932 2.263-.977-1.717 4.074-2.35-4.074-2.35.987-1.717 3.922 2.264.15.086V6.172h1.973z" fill="#303134"/></svg>',
                thunderstorms: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M30.514 2.005c8.06.192 14.75 6.088 15.428 13.715h-3.017c-.671-5.843-5.881-10.549-12.46-10.716L30.093 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.592 0-8.13 3.466-8.143 7.522l.006.226-1.894 1.831a8.572 8.572 0 0 0-.71.779 9.996 9.996 0 0 1-.345-1.74l-.042-.525-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path d="M21.796 36.153l.035.033a5.479 5.479 0 0 1 0 7.898c-2.241 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.898l4.058-3.948 4.023 3.915zm-8.609-10.594l.036.035a5.479 5.479 0 0 1 0 7.897c-2.242 2.18-5.875 2.18-8.116 0a5.479 5.479 0 0 1 0-7.897l4.057-3.949 4.023 3.914z" fill="#0B57D0"/><path fill-rule="evenodd" clip-rule="evenodd" d="M33.06 18.72l-6.667 13.756h6l-.744 12.243 14.744-16.037-5.335-.022 5.335-9.94H33.059z" fill="#DA4F00"/></g><defs><clipPath id="a"><path fill="#fff" d="M0 0h48v48H0z"/></clipPath></defs></svg>',
                strong_thunderstorms: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30.514 2.005c8.159.194 14.912 6.233 15.45 13.995h-3.01c-.535-5.972-5.805-10.826-12.49-10.996L30.094 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.592 0-8.13 3.466-8.143 7.522l.012.424c.12 2.154 1.243 4.114 2.977 5.434L6.624 30c-2.5-1.707-4.238-4.372-4.567-7.47l-.042-.525-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path fill-rule="evenodd" clip-rule="evenodd" d="M32.667 19L26 32.757h6L31.257 45 46 28.962l-5.335-.02L46 19H32.667zm-12 16L18 40.82h2.4L20.103 46 26 39.215l-2.134-.01L26 35h-5.333zM12 26l-4 7.937h3.6L11.154 41 20 31.748l-3.201-.013L20 26h-8z" fill="#DA4F00"/></svg>',
                sleet_hail: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M30.514 2.005C39.02 2.208 46 8.762 46 16.999l-.008.473-.022.454c-.242 3.735-1.932 7.084-4.518 9.573l-2.122-2.121c2.188-2.084 3.552-4.892 3.662-7.97L43 16.97c-.016-6.409-5.492-11.788-12.534-11.967L30.093 5c-5.387.006-9.96 3.086-11.885 7.373l-.981 2.186-2.35-.474a8.724 8.724 0 0 0-1.29-.161l-.444-.012c-4.592 0-8.13 3.466-8.143 7.522l.01.41-2.567 2.567a9.994 9.994 0 0 1-.386-1.882l-.042-.524-.015-.55c0-5.906 5.077-10.543 11.143-10.543.797 0 1.576.08 2.329.232C17.9 5.735 23.577 2 30.107 2l.407.005z" fill="#70757A"/><path fill="#303134" d="M9.523 21.575l5.44 5.44-5.44 5.439-5.44-5.44zm13.602 13.597l5.44 5.439-5.44 5.44-5.44-5.44zm5.439-16.317l2.72 2.72-16.32 16.318-2.72-2.72zm6.801 6.799l2.72 2.72-8.16 8.158-2.72-2.72z"/></g><defs><clipPath id="a"><path fill="#fff" d="M0 0h48v48H0z"/></clipPath></defs></svg>',
                hurricane: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.08 1.508c11.94.302 21.523 10.088 21.523 22.112a1 1 0 0 1-1.997 0c0-11.113-8.998-20.12-20.096-20.12a1 1 0 0 1 0-2l.57.008zM5.54 24.38c0 12.215 9.892 22.119 22.093 22.12a1 1 0 0 0 0-1.999c-11.098 0-20.096-9.008-20.096-20.121a1 1 0 0 0-.896-.994l-.102-.006-.103.006a1 1 0 0 0-.896.994z" fill="#70757A" stroke="#70757A" stroke-width=".999"/><mask id="a" fill="#fff"><path fill-rule="evenodd" clip-rule="evenodd" d="M24 8.944c9.546 0 15.965 7.334 18.978 11.778a5.817 5.817 0 0 1-.024 6.624C39.93 31.73 33.52 38.916 24 38.916c-9.52 0-15.93-7.187-18.954-11.57a5.816 5.816 0 0 1-.024-6.624C8.036 16.278 14.454 8.944 24 8.944zm0 2.997c-6.612 0-11.974 5.368-11.974 11.99 0 6.62 5.362 11.988 11.975 11.988 6.613 0 11.973-5.368 11.973-11.989 0-6.62-5.36-11.989-11.973-11.989z"/></mask><path fill-rule="evenodd" clip-rule="evenodd" d="M24 8.944c9.546 0 15.965 7.334 18.978 11.778a5.817 5.817 0 0 1-.024 6.624C39.93 31.73 33.52 38.916 24 38.916c-9.52 0-15.93-7.187-18.954-11.57a5.816 5.816 0 0 1-.024-6.624C8.036 16.278 14.454 8.944 24 8.944zm0 2.997c-6.612 0-11.974 5.368-11.974 11.99 0 6.62 5.362 11.988 11.975 11.988 6.613 0 11.973-5.368 11.973-11.989 0-6.62-5.36-11.989-11.973-11.989z" fill="#70757A"/></svg>',
                tornado: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M21.01 39h5.49c0-1.47.43-2.84 1.16-4H19c0 1.64.79 3.09 2.01 4zM44 3H7v4h33l4-4zM21 31h13l4-4H21v4zm-2.1-8H40c.87-1.16 1.5-2.52 1.8-4H14.9l4 4zM8.67 11c.62 1.43 1.41 2.77 2.33 4h30.8c-.3-1.48-.93-2.84-1.8-4H8.67z" fill="#8F8F8F"/></g><defs><clipPath id="a"><path fill="#fff" d="M0 0h48v48H0z"/></clipPath></defs></svg>',
                clear_night: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M25.761 2c-3.077 3.766-4.917 8.544-4.917 13.743 0 12.153 10.055 22.005 22.459 22.005.233 0 .465-.003.697-.01C39.884 42.774 33.556 46 26.459 46 14.055 46 4 36.148 4 23.995 4 12.07 13.68 2.36 25.761 2z" fill="#3271EA"/></svg>',
                mostly_sunny: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18.58 4.033a8.246 8.246 0 0 1 10.844 0 8.247 8.247 0 0 0 4.863 2.014 8.246 8.246 0 0 1 7.667 7.668 8.245 8.245 0 0 0 2.015 4.862 8.246 8.246 0 0 1 1.61 8.031 14.018 14.018 0 0 0-10.064-4.604h-.038L35.2 22h-.036c-4.844 0-9.184 2.448-11.666 6.24C17.862 28.292 13 32.75 13 38.618v.04l.012.463.002.04c.05.973.239 1.902.538 2.776a8.243 8.243 0 0 1-7.502-7.653 8.246 8.246 0 0 0-2.015-4.862 8.247 8.247 0 0 1 0-10.845 8.245 8.245 0 0 0 2.015-4.862 8.246 8.246 0 0 1 7.667-7.668 8.247 8.247 0 0 0 4.863-2.014z" fill="#FCBD00"/><path d="M18.58 4.033a8.246 8.246 0 0 1 10.844 0 8.249 8.249 0 0 0 4.863 2.015 8.246 8.246 0 0 1 7.667 7.667 8.248 8.248 0 0 0 2.015 4.863 8.244 8.244 0 0 1 1.608 8.03 13.84 13.84 0 0 0-2.59-2.24 5.236 5.236 0 0 0-1.28-3.817 11.247 11.247 0 0 1-2.746-6.632 5.246 5.246 0 0 0-4.878-4.878 11.248 11.248 0 0 1-6.632-2.747 5.245 5.245 0 0 0-6.898 0 11.248 11.248 0 0 1-6.632 2.747 5.246 5.246 0 0 0-4.878 4.878 11.248 11.248 0 0 1-2.747 6.632 5.246 5.246 0 0 0 0 6.898 11.248 11.248 0 0 1 2.747 6.632 5.246 5.246 0 0 0 3.96 4.732l.009.308.002.04c.05.972.238 1.902.537 2.776a8.244 8.244 0 0 1-7.467-7.269l-.034-.383a8.248 8.248 0 0 0-2.015-4.863 8.246 8.246 0 0 1-.246-10.548l.246-.296a8.247 8.247 0 0 0 1.984-4.527l.03-.336a8.246 8.246 0 0 1 7.285-7.633l.383-.034A8.248 8.248 0 0 0 18.32 4.25l.259-.217z" fill="#DA4F00"/><path d="M35.404 26.502c5.042.124 9.096 4.1 9.096 8.997l-.005.283c-.092 2.895-1.605 5.446-3.886 7.027a9.464 9.464 0 0 1-3.311 1.45c-2.235.276-4.45.24-6.862.239h-6.838c-3.252 0-5.918-2.46-6.09-5.557l-.008-.323c0-3.25 2.733-5.88 6.098-5.88.44 0 .87.045 1.283.131l1.191.247.488-1.114c1.414-3.234 4.738-5.5 8.597-5.502l.247.002z" stroke="#70757A" stroke-width="3"/></svg>',
                mostly_cloudy_night: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.689 2c-2.077 2.482-3.319 5.631-3.319 9.058 0 4.015 1.705 7.649 4.46 10.275a16.159 16.159 0 0 0-2.875 3.394l-.332-.003c-2.825 0-5.505.92-7.664 2.5C3.913 24.573 2 20.749 2 16.497 2 8.637 8.534 2.238 16.689 2z" fill="#3271EA"/><path d="M29.589 21.503c6.62.16 11.91 5.257 11.911 11.496l-.007.378c-.007.203-.02.41-.04.62l-.083.91.773.491c1.456.926 2.357 2.439 2.357 4.102 0 2.659-2.356 5-5.5 5H19.5v-.003h-4.877c-4.22 0-7.675-3.026-8.083-6.866l-.03-.374-.01-.397c0-4.07 3.4-7.421 7.704-7.627l.419-.01c.587 0 1.161.06 1.714.172l1.177.238.49-1.096c1.848-4.128 6.198-7.034 11.264-7.037l.32.003z" stroke="#70757A" stroke-width="3"/></svg>'
            };
            
            // WMO Weather interpretation codes mapped to our SVG icons
            const iconMap = {
                0: 'sunny',                    // Clear sky
                1: 'sunny',                    // Mainly clear
                2: 'partly_cloudy',            // Partly cloudy
                3: 'mostly_sunny',             // Overcast
                45: 'cloudy',                  // Fog
                48: 'cloudy',                  // Rime fog
                51: 'drizzle',                 // Light drizzle
                53: 'drizzle',                 // Moderate drizzle
                55: 'drizzle',                 // Dense drizzle
                56: 'sleet_hail',              // Freezing drizzle
                57: 'sleet_hail',              // Dense freezing drizzle
                61: 'heavy_rain',              // Slight rain
                63: 'heavy_rain',              // Moderate rain
                65: 'heavy_rain',              // Heavy rain
                66: 'sleet_hail',              // Light freezing rain
                67: 'sleet_hail',              // Heavy freezing rain
                71: 'snow_showers',            // Slight snow
                73: 'heavy_snow',              // Moderate snow
                75: 'blizzard',                // Heavy snow
                77: 'snow_showers',            // Snow grains
                80: 'heavy_rain',              // Slight rain showers
                81: 'heavy_rain',              // Moderate rain showers
                82: 'heavy_rain',              // Violent rain showers
                85: 'snow_showers',            // Slight snow showers
                86: 'heavy_snow',              // Heavy snow showers
                95: 'thunderstorms',           // Thunderstorm
                96: 'strong_thunderstorms',    // Thunderstorm with hail
                99: 'strong_thunderstorms',    // Thunderstorm with heavy hail
                
                // Additional conditions
                'hurricane': 'hurricane',
                'tornado': 'tornado',
                'blowing_snow': 'blowing_snow',
                'flurries': 'flurries',
                'icy': 'icy',
                'wintry_mix': 'wintry_mix',
                'windy': 'windy'
            };

            const iconName = iconMap[code] || 'sunny';
            const svgIcon = WeatherIcons[iconName] || WeatherIcons.sunny;
            
            // Return embedded SVG icon with proper sizing
            return `<span class="weather-svg-icon" style="display: inline-block; width: ${size}px; height: ${size}px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">${svgIcon}</span>`;
        },
        
        refreshWeather: function() {
            const self = this;
            const loadingEl = $('#crm-weather-loading');
            const dataEl = $('#crm-weather-data');
            const errorEl = $('#crm-weather-error');
            
            loadingEl.show();
            dataEl.hide();
            errorEl.hide();
            
            const teamApiBase = (PI_Daily_Reports_Settings.team_rest_base || PI_Daily_Reports_Settings.rest_base.replace('/daily-reports/v1', '/v1')).replace(/\/+$/, '');
            
            fetch(`${teamApiBase}/weather/fetch`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ job_id: this.jobId })
            })
            .then(response => response.json())
            .then(fetchResult => {
                return fetch(`${teamApiBase}/weather?job_id=${this.jobId}`, {
                    headers: {
                        'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                        'Content-Type': 'application/json'
                    }
                });
            })
            .then(response => response.json())
            .then(weather => {
                this.weather = Array.isArray(weather) ? weather : (weather?.data || []);
                if (weather?.error) {
                    this.weather = { error: true, message: weather.message, has_address: weather.has_address };
                }
                this.renderWeatherWidget();
            })
            .catch(error => {
                console.error('[DailyReports] Failed to refresh weather:', error);
                this.weather = { error: true, message: 'Failed to refresh weather data' };
                this.renderWeatherWidget();
            });
        },
        
        // ============================================
        // LABOR
        // ============================================
        
        loadLabor: function() {
            const self = this;
            
            // Use Team Timesheets API to get labor entries for this daily report
            const teamApiBase = (PI_Daily_Reports_Settings.team_rest_base || PI_Daily_Reports_Settings.rest_base.replace('/daily-reports/v1', '/v1')).replace(/\/+$/, '');
            fetch(`${teamApiBase}/timesheets?daily_report_id=${this.reportId}&job_id=${this.jobId}`, {
                headers: {
                    'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(response => {
                // Robust parsing: handle all response formats
                let workers = [];
                
                if (Array.isArray(response)) {
                    workers = response;
                } else if (response && response.code && response.message) {
                    throw new Error(response.message);
                } else if (response && response.data) {
                    if (Array.isArray(response.data)) {
                        workers = response.data;
                    } else if (response.data.code && response.data.message) {
                        throw new Error(response.data.message);
                    }
                }
                
                if (!Array.isArray(workers)) {
                    console.warn('Unexpected labor response format:', response);
                    workers = [];
                }
                
                const $tbody = $('#pi-dr-labor-tbody');
                $tbody.empty();
                
                workers.forEach(function(worker) {
                    $tbody.append(`
                        <tr data-id="${worker.id}">
                            <td>${worker.worker_name || `${worker.first_name || ''} ${worker.last_name || ''}`.trim() || 'Unknown'}</td>
                            <td>${worker.trade || '-'}</td>
                            <td>${worker.company || '-'}</td>
                            <td>${worker.start_time || '-'}</td>
                            <td>${worker.end_time || '-'}</td>
                            <td>${worker.total_hours || 0}</td>
                            <td>${worker.regular_hours || 0}</td>
                            <td>${worker.overtime_hours || 0}</td>
                            <td>${worker.location_status === 'verified' ? '✓' : '⚠'}</td>
                            <td>
                                <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn" data-action="edit-labor" data-id="${worker.id}">Edit</button>
                                <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn pi-dr-btn-danger" data-action="delete-labor" data-id="${worker.id}">Delete</button>
                            </td>
                        </tr>
                    `);
                });
                
                if (workers.length === 0) {
                    $tbody.append('<tr><td colspan="10" style="text-align: center; color: #666;">No labor entries found</td></tr>');
                }
            })
            .catch(error => {
                console.error('Failed to load labor:', error);
                const $tbody = $('#pi-dr-labor-tbody');
                $tbody.empty();
                $tbody.append('<tr><td colspan="10" style="text-align: center; color: #666;">Error loading labor entries</td></tr>');
            });
        },
        
        saveLabor: function(formData) {
            const self = this;
            const laborId = formData.id;
            
            // Use Team Timesheets API for labor entries to ensure synchronization
            const teamApiBase = (PI_Daily_Reports_Settings.team_rest_base || PI_Daily_Reports_Settings.rest_base.replace('/daily-reports/v1', '/v1')).replace(/\/+$/, '');
            const data = {
                employee_id: formData.employee_id,
                job_id: this.jobId,
                daily_report_id: this.reportId,
                work_date: new Date().toISOString().split('T')[0], // Today's date
                start_time: '09:00:00', // Default start time
                end_time: '17:00:00',   // Default end time
                total_hours: 8,          // Default hours
                cost_code: formData.cost_code,
                task_description: formData.notes,
                status: 'approved'
            };
            
            const url = laborId ? `${teamApiBase}/timesheets/${laborId}` : `${teamApiBase}/timesheets`;
            const method = laborId ? 'PUT' : 'POST';
            
            fetch(url, {
                method: method,
                headers: {
                    'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success || result.id) {
                    self.showToast(laborId ? 'Worker updated' : 'Worker added', 'success');
                    self.loadLabor();
                    self.closeModal();
                } else {
                    throw new Error(result.message || 'Failed to save labor entry');
                }
            })
            .catch(error => {
                console.error('Failed to save labor:', error);
                self.showToast('Failed to save labor entry', 'error');
            });
        },
        
        // ============================================
        // ACTIVITIES
        // ============================================
        
        loadActivities: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/activities`, 'GET')
                .done(function(response) {
                    const $tbody = $('#pi-dr-activities-tbody');
                    $tbody.empty();
                    
                    response.forEach(function(activity) {
                        $tbody.append(`
                            <tr data-id="${activity.id}">
                                <td>${activity.location_area || '-'}</td>
                                <td>${activity.trade_company || '-'}</td>
                                <td>${activity.activity_type || '-'}</td>
                                <td>${activity.activity_description || '-'}</td>
                                <td>${activity.quantity_completed || 0}</td>
                                <td>${activity.unit_of_measure || '-'}</td>
                                <td>${activity.percent_complete || 0}%</td>
                                <td>${activity.start_time || '-'}</td>
                                <td>${activity.end_time || '-'}</td>
                                <td>${activity.cost_code || '-'}</td>
                                <td>${activity.delay_reason || '-'}</td>
                                <td>
                                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn" data-action="edit-activity" data-id="${activity.id}">Edit</button>
                                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn pi-dr-btn-danger" data-action="delete-activity" data-id="${activity.id}">Delete</button>
                                </td>
                            </tr>
                        `);
                    });
                });
        },
        
        saveActivity: function(formData) {
            const self = this;
            const activityId = formData.id;
            
            const data = {
                location_area: formData.location,
                trade_company: formData.trade,
                activity_type: formData.type,
                activity_description: formData.description,
                quantity_completed: formData.quantity,
                unit_of_measure: formData.unit,
                percent_complete: formData.percent,
                start_time: formData.start ? `${new Date().toISOString().split('T')[0]} ${formData.start}` : null,
                end_time: formData.end ? `${new Date().toISOString().split('T')[0]} ${formData.end}` : null,
                cost_code: formData.costCode,
                phase_wbs: formData.phase,
                delay_reason: formData.delayReason,
                delay_hours: formData.delayHours,
                blockers_issues: formData.blockers
            };
            
            if (activityId) {
                this.apiCall(`/activities/${activityId}`, 'PUT', data)
                    .done(function() {
                        self.showToast('Activity updated', 'success');
                        self.loadActivities();
                        self.closeModal();
                    });
            } else {
                this.apiCall(`/reports/${this.reportId}/activities`, 'POST', data)
                    .done(function() {
                        self.showToast('Activity added', 'success');
                        self.loadActivities();
                        self.closeModal();
                    });
            }
        },
        
        // ============================================
        // EQUIPMENT
        // ============================================
        
        syncEquipment: function() {
            const self = this;
            if (!this.reportId) return;
            this.apiCall(`/reports/${this.reportId}/sync-equipment`, 'POST')
                .done(function(response) {
                    if (response && response.synced > 0) {
                        console.log('[DailyReports] Synced ' + response.synced + ' equipment items');
                    }
                })
                .fail(function() {
                    console.warn('[DailyReports] Equipment sync failed');
                });
        },

        loadEquipment: function() {
            const self = this;

            this.apiCall(`/reports/${this.reportId}/equipment`, 'GET')
                .done(function(response) {
                    const $tbody = $('#pi-dr-equipment-tbody');
                    $tbody.empty();

                    let items = [];
                    if (Array.isArray(response)) {
                        items = response;
                    } else if (response && response.data && Array.isArray(response.data)) {
                        items = response.data;
                    }

                    if (items.length === 0) {
                        $tbody.append('<tr><td colspan="8" style="text-align: center; color: #666;">No equipment logged for this report</td></tr>');
                        return;
                    }

                    items.forEach(function(eq) {
                        $tbody.append(`
                            <tr data-id="${eq.id}">
                                <td>${eq.equipment_name}</td>
                                <td>${eq.equipment_type || '-'}</td>
                                <td>${eq.status}</td>
                                <td>${eq.hours_used || 0}</td>
                                <td>${eq.operator_name || '-'}</td>
                                <td>${eq.fuel_notes || '-'}</td>
                                <td>${eq.downtime_hours || 0}</td>
                                <td>
                                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn" data-action="edit-equipment" data-id="${eq.id}">Edit</button>
                                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn pi-dr-btn-danger" data-action="delete-equipment" data-id="${eq.id}">Delete</button>
                                </td>
                            </tr>
                        `);
                    });
                })
                .fail(function(xhr) {
                    console.error('[DailyReports] Failed to load equipment:', xhr.status, xhr.statusText);
                });
        },

        loadJobEquipment: function() {
            const self = this;
            const $select = $('#pi-dr-equipment-select');
            $select.empty().append('<option value="">-- Select from job equipment --</option>');

            // Prevent duplicate in-flight requests
            if (this._loadingJobEquipment) return;
            this._loadingJobEquipment = true;

            fetch(`${this.apiBase}/integration/job/${this.jobId}/equipment`, {
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(data => {
                self._loadingJobEquipment = false;
                let items = Array.isArray(data) ? data : (data.data || []);
                if (!Array.isArray(items)) items = [];

                // Deduplicate by equipment ID in case API returns duplicates
                const seen = new Set();
                items = items.filter(function(eq) {
                    if (!eq || !eq.id || seen.has(eq.id)) return false;
                    seen.add(eq.id);
                    return true;
                });

                items.forEach(function(eq) {
                    const name = eq.equipment_name || eq.equipment_type || 'Unnamed Equipment';
                    $select.append(`<option value="${eq.id}" data-type="${eq.equipment_type || ''}" data-hours="${eq.hours_meter_reading || ''}" data-operator="${eq.operator_name || ''}" data-condition="${eq.current_condition || ''}" data-acquisition="${eq.acquisition_type || ''}">${name}</option>`);
                });

                if (items.length === 0) {
                    $select.append('<option value="" disabled>No job equipment found</option>');
                }
            })
            .catch(error => {
                self._loadingJobEquipment = false;
                console.error('[DailyReports] Failed to load job equipment:', error);
            });
        },

        saveEquipment: function(formData) {
            const self = this;
            const equipmentId = formData.id;

            const data = {
                equipment_id: formData.equipment_id || 0,
                equipment_name: formData.name,
                equipment_type: formData.type,
                ownership_type: formData.ownership,
                status: formData.status,
                hours_used: formData.hours,
                operator_name: formData.operator,
                fuel_notes: formData.fuel,
                maintenance_issues: formData.maintenance,
                downtime_reason: formData.downtimeReason,
                downtime_hours: formData.downtimeHours,
                meter_reading: formData.meterReading || 0
            };

            if (!this.reportId) {
                self.showToast('Report not loaded yet. Please wait a moment.', 'error');
                return;
            }

            if (equipmentId) {
                this.apiCall(`/daily-equipment/${equipmentId}`, 'PUT', data)
                    .done(function() {
                        self.showToast('Equipment updated', 'success');
                        self.loadEquipment();
                        self.closeModal();
                    })
                    .fail(function(xhr) {
                        console.error('[DailyReports] Update failed:', xhr.status, xhr.responseText);
                        self.showToast('Failed to update equipment', 'error');
                    });
            } else {
                this.apiCall(`/reports/${this.reportId}/equipment`, 'POST', data)
                    .done(function(result) {
                        if (result && result.id) {
                            self.showToast('Equipment added', 'success');
                            self.loadEquipment();
                            self.closeModal();
                        } else {
                            console.error('[DailyReports] Save succeeded but no ID returned:', result);
                            self.showToast('Equipment saved but may not appear', 'warning');
                            self.loadEquipment();
                            self.closeModal();
                        }
                    })
                    .fail(function(xhr) {
                        console.error('[DailyReports] Save failed:', xhr.status, xhr.responseText);
                        self.showToast('Failed to save equipment: ' + (xhr.statusText || 'Unknown error'), 'error');
                    });
            }
        },
        
        // ============================================
        // MATERIALS
        // ============================================
        
        loadMaterials: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/materials`, 'GET')
                .done(function(response) {
                    const $tbody = $('#pi-dr-materials-tbody');
                    $tbody.empty();
                    
                    if (response.length === 0) {
                        $tbody.append(`
                            <tr>
                                <td colspan="9" class="pi-dr-empty-state">
                                    <div class="pi-dr-empty-message">
                                        <i class="fas fa-box-open"></i>
                                        <p>No materials recorded today</p>
                                        <button class="pi-dr-btn pi-dr-btn-primary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.openMaterialModal(); else console.warn('PI_Daily_Reports not yet loaded');">Add Material</button>
                                        <button class="pi-dr-btn pi-dr-btn-secondary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.syncMaterials(); else console.warn('PI_Daily_Reports not yet loaded');">Sync from BOM</button>
                                    </div>
                                </td>
                            </tr>
                        `);
                        return;
                    }
                    
                    response.forEach(function(mat) {
                        const statusClass = self.getDeliveryStatusClass(mat.delivery_status);
                        const stockSynced = mat.stock_movement_recorded ? '<i class="fas fa-check-circle text-success" title="Stock synced"></i>' : '<i class="fas fa-exclamation-circle text-warning" title="Stock not synced"></i>';
                        
                        $tbody.append(`
                            <tr data-id="${mat.id}">
                                <td>
                                    <div class="pi-dr-material-info">
                                        <div class="pi-dr-material-name">${mat.material_name}</div>
                                        ${mat.material_sku ? `<div class="pi-dr-material-sku">SKU: ${mat.material_sku}</div>` : ''}
                                        ${mat.material_id ? `<div class="pi-dr-material-id">ID: ${mat.material_id}</div>` : ''}
                                    </div>
                                </td>
                                <td>
                                    <div class="pi-dr-supplier-info">
                                        <div class="pi-dr-supplier-name">${mat.supplier_name}</div>
                                        ${mat.po_number ? `<div class="pi-dr-po-number">PO: ${mat.po_number}</div>` : ''}
                                    </div>
                                </td>
                                <td>
                                    <div class="pi-dr-quantity-info">
                                        <div class="pi-dr-quantity-delivered">${mat.quantity_delivered || mat.quantity || 0} ${mat.unit_of_measure || 'each'}</div>
                                        ${mat.quantity_ordered && mat.quantity_ordered != (mat.quantity_delivered || mat.quantity) ? 
                                            `<div class="pi-dr-quantity-ordered">Ordered: ${mat.quantity_ordered} ${mat.unit_of_measure || 'each'}</div>` : ''}
                                        ${mat.damaged_quantity > 0 ? `<div class="pi-dr-damaged-qty">Damaged: ${mat.damaged_quantity}</div>` : ''}
                                        ${mat.missing_quantity > 0 ? `<div class="pi-dr-missing-qty">Missing: ${mat.missing_quantity}</div>` : ''}
                                    </div>
                                </td>
                                <td>
                                    <span class="pi-dr-status-badge ${statusClass}">${mat.delivery_status || 'scheduled'}</span>
                                </td>
                                <td>
                                    <div class="pi-dr-condition-info">
                                        <div class="pi-dr-condition">${mat.condition_on_arrival || 'good'}</div>
                                        ${mat.quality_notes ? `<div class="pi-dr-quality-notes" title="${mat.quality_notes}">Notes</div>` : ''}
                                    </div>
                                </td>
                                <td>
                                    <div class="pi-dr-receipt-info">
                                        <div class="pi-dr-received-by">${mat.received_by || '-'}</div>
                                        ${mat.actual_delivery_date ? `<div class="pi-dr-delivery-date">${self.formatDate(mat.actual_delivery_date)}</div>` : ''}
                                    </div>
                                </td>
                                <td>
                                    <div class="pi-dr-cost-info">
                                        <div class="pi-dr-unit-cost">£${parseFloat(mat.unit_cost || 0).toFixed(2)}</div>
                                        <div class="pi-dr-total-cost">£${parseFloat(mat.total_cost || 0).toFixed(2)}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="pi-dr-stock-sync">
                                        ${stockSynced}
                                    </div>
                                </td>
                                <td>
                                    <div class="pi-dr-actions">
                                        <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.editMaterial(${mat.id}); else console.warn('PI_Daily_Reports not yet loaded');" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        ${!mat.stock_movement_recorded ? 
                                            `<button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn pi-dr-btn-success" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.recordStockMovement(${mat.id}); else console.warn('PI_Daily_Reports not yet loaded');" title="Record Stock Movement">
                                                <i class="fas fa-arrow-down"></i>
                                            </button>` : ''}
                                        <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn pi-dr-btn-danger" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.deleteMaterial(${mat.id}); else console.warn('PI_Daily_Reports not yet loaded');" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `);
                    });
                })
                .fail(function(xhr) {
                    console.error('[DailyReports] Failed to load materials:', xhr);
                    self.showToast('Failed to load materials', 'error');
                });
        },
        
        getDeliveryStatusClass: function(status) {
            const classes = {
                'scheduled': 'pi-dr-status-scheduled',
                'in_transit': 'pi-dr-status-transit',
                'delivered': 'pi-dr-status-delivered',
                'delayed': 'pi-dr-status-delayed',
                'cancelled': 'pi-dr-status-cancelled',
                'partial': 'pi-dr-status-partial'
            };
            return classes[status] || 'pi-dr-status-scheduled';
        },
        
        openMaterialModal: function(materialId = null) {
            const self = this;
            const isEdit = !!materialId;
            
            if (isEdit) {
                // Load existing material data
                this.apiCall(`/daily-materials/${materialId}`, 'GET')
                    .done(function(material) {
                        self.renderMaterialModal(material, isEdit);
                    })
                    .fail(function(xhr) {
                        if (xhr.status === 0 || retryCount < maxRetries) {
                            retryCount++;
                            setTimeout(function() {
                                self.openMaterialModal(materialId);
                            }, 500);
                        } else {
                            self.showToast('Failed to load material data', 'error');
                        }
                    });
            } else {
                // Edge Case 10: Browser cache poisoning prevention
                const cacheBuster = Date.now();
                
                // Load available materials for selection
                this.apiCall(`/reports/${this.reportId}/available-materials?_cb=${cacheBuster}`, 'GET')
                    .done(function(materials) {
                        self.materialRequestInProgress = false;
                        
                        // Edge Case 9: Session state validation
                        if (materials && materials.session_expired) {
                            self.showToast('Session expired, refreshing...', 'warning');
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                            return;
                        }
                        
                        self.renderMaterialModal(null, false, materials);
                    })
                    .fail(function(xhr) {
                        self.materialRequestInProgress = false;
                        
                        // Edge Case 13: Enhanced error handling with specific guidance
                        let errorMessage = 'Failed to load available materials';
                        let userAction = 'Please try again';
                        
                        if (xhr.status === 401) {
                            errorMessage = 'Authentication failed';
                            userAction = 'Please refresh the page and try again';
                        } else if (xhr.status === 403) {
                            errorMessage = 'Access forbidden';
                            userAction = 'This may be due to security plugin restrictions';
                        } else if (xhr.status === 404) {
                            errorMessage = 'Materials API not found';
                            userAction = 'The materials plugin may not be activated';
                        } else if (xhr.status === 429) {
                            errorMessage = 'Too many requests';
                            userAction = 'Please wait a moment and try again';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Server error';
                            userAction = 'The server may be experiencing high load';
                        } else if (xhr.status === 0) {
                            errorMessage = 'Network connection failed';
                            userAction = 'Please check your internet connection';
                        }
                        
                        self.showToast(`${errorMessage}: ${userAction}`, 'error');
                        
                        // Edge Case 14: Show manual entry fallback when API fails
                        if (xhr.status >= 400 || xhr.status === 0) {
                            document.getElementById('pi-dr-bom-selection').style.display = 'none';
                            document.getElementById('pi-dr-manual-entry').style.display = 'block';
                        }
                    })
                    .always(function() {
                        // Hide loading indicator
                        const loadingElement = document.getElementById('pi-dr-loading');
                        if (loadingElement) {
                            loadingElement.style.display = 'none';
                        }
                    });
            }
        },
        
        renderMaterialModal: function(material = null, isEdit = false, availableMaterials = []) {
            const materialOptions = availableMaterials.length > 0 
                ? availableMaterials.map(mat => `
                    <option value="${mat.bom_item_id}" 
                            data-material-id="${mat.material_id}" 
                            data-name="${mat.material_name}" 
                            data-sku="${mat.material_sku || ''}" 
                            data-category="${mat.material_category || ''}" 
                            data-unit="${mat.unit_of_measure || 'each'}" 
                            data-cost="${mat.unit_cost || 0}"
                            data-supplier-id="${mat.supplier_id || 0}"
                            data-stock="${mat.current_stock || 0}">
                        ${mat.material_name} ${mat.material_sku ? `(${mat.material_sku})` : ''} - Stock: ${mat.current_stock}
                    </option>`).join('')
                : '<option value="">No materials available in BOM</option>';
            
            const deliveryStatusOptions = `
                <option value="scheduled">Scheduled</option>
                <option value="in_transit">In Transit</option>
                <option value="delivered">Delivered</option>
                <option value="delayed">Delayed</option>
                <option value="cancelled">Cancelled</option>
                <option value="partial">Partial Delivery</option>
            `;
            
            const conditionOptions = `
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
                <option value="damaged">Damaged</option>
            `;
            
            const modalContent = `
                <div class="pi-dr-modal-content">
                    <form id="pi-dr-material-form">
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group">
                                <label for="material-select">Material Selection *</label>
                                ${!isEdit ? `
                                    <select id="material-select" name="bom_item_id" class="pi-dr-form-control" required>
                                        <option value="">Select from BOM</option>
                                        ${materialOptions}
                                    </select>
                                ` : `
                                    <input type="text" id="material-name" name="material_name" class="pi-dr-form-control" 
                                           value="${material?.material_name || ''}" readonly>
                                    <input type="hidden" name="bom_item_id" value="${material?.bom_item_id || 0}">
                                `}
                            </div>
                        </div>
                        
                        ${!isEdit ? `
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="material-name-display">Material Name</label>
                                <input type="text" id="material-name-display" class="pi-dr-form-control" readonly>
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="material-sku-display">SKU</label>
                                <input type="text" id="material-sku-display" class="pi-dr-form-control" readonly>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="supplier-name">Supplier *</label>
                                <input type="text" id="supplier-name" name="supplier_name" class="pi-dr-form-control" 
                                       value="${material?.supplier_name || ''}" required>
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="po-number">PO Number</label>
                                <input type="text" id="po-number" name="po_number" class="pi-dr-form-control" 
                                       value="${material?.po_number || ''}">
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-third">
                                <label for="quantity-ordered">Quantity Ordered</label>
                                <input type="number" id="quantity-ordered" name="quantity_ordered" class="pi-dr-form-control" 
                                       step="0.01" min="0" value="${material?.quantity_ordered || 0}">
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-third">
                                <label for="quantity-delivered">Quantity Delivered *</label>
                                <input type="number" id="quantity-delivered" name="quantity_delivered" class="pi-dr-form-control" 
                                       step="0.01" min="0" value="${material?.quantity_delivered || material?.quantity || 0}" required>
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-third">
                                <label for="unit-of-measure-display">Unit</label>
                                <input type="text" id="unit-of-measure-display" class="pi-dr-form-control" readonly>
                                <input type="hidden" id="unit-of-measure" name="unit_of_measure" 
                                       value="${material?.unit_of_measure || 'each'}">
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="unit-cost-display">Unit Cost (£)</label>
                                <input type="number" id="unit-cost-display" class="pi-dr-form-control" readonly step="0.01" min="0">
                                <input type="hidden" id="unit-cost" name="unit_cost" 
                                       value="${material?.unit_cost || 0}">
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="delivery-status">Delivery Status</label>
                                <select id="delivery-status" name="delivery_status" class="pi-dr-form-control">
                                    ${deliveryStatusOptions}
                                </select>
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="condition-on-arrival">Condition on Arrival</label>
                                <select id="condition-on-arrival" name="condition_on_arrival" class="pi-dr-form-control">
                                    ${conditionOptions}
                                </select>
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="received-by">Received By</label>
                                <input type="text" id="received-by" name="received_by" class="pi-dr-form-control" 
                                       value="${material?.received_by || ''}">
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-third">
                                <label for="damaged-quantity">Damaged Quantity</label>
                                <input type="number" id="damaged-quantity" name="damaged_quantity" class="pi-dr-form-control" 
                                       step="0.01" min="0" value="${material?.damaged_quantity || 0}">
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-third">
                                <label for="missing-quantity">Missing Quantity</label>
                                <input type="number" id="missing-quantity" name="missing_quantity" class="pi-dr-form-control" 
                                       step="0.01" min="0" value="${material?.missing_quantity || 0}">
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-third">
                                <label for="actual-delivery-date">Actual Delivery Date</label>
                                <input type="date" id="actual-delivery-date" name="actual_delivery_date" class="pi-dr-form-control" 
                                       value="${material?.actual_delivery_date || ''}">
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-group">
                            <label for="quality-notes">Quality Notes</label>
                            <textarea id="quality-notes" name="quality_notes" class="pi-dr-form-control" rows="2" 
                                      placeholder="Any quality issues or notes...">${material?.quality_notes || ''}</textarea>
                        </div>
                        
                        <div class="pi-dr-form-group">
                            <label for="delay-reason">Delay Reason (if delayed)</label>
                            <textarea id="delay-reason" name="delay_reason" class="pi-dr-form-control" rows="2" 
                                      placeholder="Reason for delay...">${material?.delay_reason || ''}</textarea>
                        </div>
                    </form>
                </div>
            `;
            
            const footerContent = `
                <button type="button" class="pi-dr-btn pi-dr-btn-secondary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.closeModal(); else console.warn('PI_Daily_Reports not yet loaded');">Cancel</button>
                <button type="button" class="pi-dr-btn pi-dr-btn-primary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.saveMaterial(); else console.warn('PI_Daily_Reports not yet loaded');">
                    ${isEdit ? 'Update Material' : 'Add Material'}
                </button>
            `;
            
            this.showModal(modalContent, isEdit ? 'Edit Material' : 'Add Material', footerContent);
            
            // Set up material selection handler
            if (!isEdit && availableMaterials.length > 0) {
                $('#material-select').on('change', function() {
                    const selected = $(this).find('option:selected');
                    if (selected.val()) {
                        $('#material-name-display').val(selected.data('name'));
                        $('#material-sku-display').val(selected.data('sku'));
                        $('#unit-of-measure-display').val(selected.data('unit'));
                        $('#unit-of-measure').val(selected.data('unit'));
                        $('#unit-cost-display').val(selected.data('cost'));
                        $('#unit-cost').val(selected.data('cost'));
                    } else {
                        $('#material-name-display').val('');
                        $('#material-sku-display').val('');
                        $('#unit-of-measure-display').val('');
                        $('#unit-of-measure').val('each');
                        $('#unit-cost-display').val(0);
                        $('#unit-cost').val(0);
                    }
                });
            }
            
            // Auto-calculate total cost
            $('#quantity-delivered, #unit-cost').on('input', function() {
                const quantity = parseFloat($('#quantity-delivered').val()) || 0;
                const unitCost = parseFloat($('#unit-cost').val()) || 0;
                // Total cost will be calculated on server side
            });
        },
        
        saveMaterial: function() {
            const self = this;
            const $form = $('#pi-dr-material-form');
            
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }
            
            const formData = new FormData($form[0]);
            const data = {};
            
            // Convert FormData to simple object
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // Add material_id if selected from BOM
            const selectedOption = $('#material-select').find('option:selected');
            if (selectedOption.data('material-id')) {
                data.material_id = selectedOption.data('material-id');
            }
            
            // Determine if this is edit or create
            const materialId = $('input[name="material_id"]').val(); // This would be set in edit mode
            
            if (materialId) {
                // Update existing material
                this.apiCall(`/daily-materials/${materialId}`, 'PUT', data)
                    .done(function() {
                        self.showToast('Material updated successfully', 'success');
                        self.loadMaterials();
                        self.closeModal();
                    })
                    .fail(function(xhr) {
                        self.showToast('Failed to update material: ' + (xhr.responseJSON?.error || xhr.statusText), 'error');
                    });
            } else {
                // Create new material
                this.apiCall(`/reports/${this.reportId}/materials`, 'POST', data)
                    .done(function() {
                        self.showToast('Material added successfully', 'success');
                        self.loadMaterials();
                        self.closeModal();
                    })
                    .fail(function(xhr) {
                        self.showToast('Failed to add material: ' + (xhr.responseJSON?.error || xhr.statusText), 'error');
                    });
            }
        },
        
        editMaterial: function(materialId) {
            this.openMaterialModal(materialId);
        },
        
        deleteMaterial: function(materialId) {
            if (!confirm('Are you sure you want to delete this material entry?')) {
                return;
            }
            
            const self = this;
            this.apiCall(`/daily-materials/${materialId}`, 'DELETE')
                .done(function() {
                    self.showToast('Material deleted successfully', 'success');
                    self.loadMaterials();
                })
                .fail(function(xhr) {
                    self.showToast('Failed to delete material: ' + (xhr.responseJSON?.error || xhr.statusText), 'error');
                });
        },
        
        syncMaterials: function() {
            const self = this;
            
            this.showLoading('Syncing materials from BOM...');
            
            this.apiCall(`/reports/${this.reportId}/sync-materials`, 'POST')
                .done(function(response) {
                    self.hideLoading();
                    
                    if (response.success && response.synced_count > 0) {
                        let message = `Successfully synced ${response.synced_count} materials from BOM`;
                        if (response.total_available && response.total_available > response.synced_count) {
                            const remaining = response.total_available - response.synced_count;
                            message += ` (${remaining} already synced)`;
                        }
                        self.showToast(message, 'success');
                    } else if (response.success && response.synced_count === 0) {
                        self.showToast(response.message || 'No new materials to sync - all materials already synced', 'info');
                    } else {
                        self.showToast(response.message || 'No materials found to sync from BOM. Check if materials are added to the job BOM.', 'warning');
                    }
                    
                    self.loadMaterials();
                })
                .fail(function(xhr) {
                    self.hideLoading();
                    let errorMessage = 'Failed to sync materials';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage += ': ' + xhr.responseJSON.error;
                    } else if (xhr.statusText) {
                        errorMessage += ': ' + xhr.statusText;
                    }
                    self.showToast(errorMessage, 'error');
                });
        },
        
        recordStockMovement: function(materialId) {
            const self = this;
            
            // Get material details first
            this.apiCall(`/daily-materials/${materialId}`, 'GET')
                .done(function(material) {
                    self.renderStockMovementModal(material);
                })
                .fail(function() {
                    self.showToast('Failed to load material details', 'error');
                });
        },
        
        renderStockMovementModal: function(material) {
            const modalContent = `
                <div class="pi-dr-modal-content">
                    <form id="pi-dr-stock-movement-form">
                        <div class="pi-dr-form-group">
                            <label>Material</label>
                            <div class="pi-dr-form-control-static">
                                ${material.material_name} ${material.material_sku ? `(${material.material_sku})` : ''}
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="movement-type">Movement Type</label>
                                <select id="movement-type" name="movement_type" class="pi-dr-form-control">
                                    <option value="receipt">Stock In (Receipt)</option>
                                    <option value="issue">Stock Out (Issue)</option>
                                    <option value="adjustment">Adjustment</option>
                                </select>
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="location-id">Location</label>
                                <select id="location-id" name="location_id" class="pi-dr-form-control">
                                    <option value="main">Main Warehouse</option>
                                    <option value="site">Site</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-row">
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="movement-quantity">Quantity</label>
                                <input type="number" id="movement-quantity" name="quantity" class="pi-dr-form-control" 
                                       step="0.01" min="0" value="${material.quantity_delivered || 0}" required>
                            </div>
                            <div class="pi-dr-form-group pi-dr-form-group-half">
                                <label for="movement-reference">Reference</label>
                                <input type="text" id="movement-reference" name="reference" class="pi-dr-form-control" 
                                       value="${material.po_number || 'Daily Report'}" placeholder="PO number or reference">
                            </div>
                        </div>
                        
                        <div class="pi-dr-form-group">
                            <label for="movement-notes">Notes</label>
                            <textarea id="movement-notes" name="notes" class="pi-dr-form-control" rows="2" 
                                      placeholder="Reason for stock movement...">Material received via daily report</textarea>
                        </div>
                    </form>
                </div>
            `;
            
            const footerContent = `
                <button type="button" class="pi-dr-btn pi-dr-btn-secondary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.closeModal(); else console.warn('PI_Daily_Reports not yet loaded');">Cancel</button>
                <button type="button" class="pi-dr-btn pi-dr-btn-primary" onclick="if(typeof PI_Daily_Reports !== 'undefined') PI_Daily_Reports.saveStockMovement(${material.id}); else console.warn('PI_Daily_Reports not yet loaded');">Record Movement</button>
            `;
            
            this.showModal(modalContent, 'Record Stock Movement', footerContent);
        },
        
        saveStockMovement: function(materialId) {
            const self = this;
            const $form = $('#pi-dr-stock-movement-form');
            
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }
            
            const formData = new FormData($form[0]);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            this.apiCall(`/daily-materials/${materialId}/stock-movement`, 'POST', data)
                .done(function() {
                    self.showToast('Stock movement recorded successfully', 'success');
                    self.loadMaterials();
                    self.closeModal();
                })
                .fail(function(xhr) {
                    self.showToast('Failed to record stock movement: ' + (xhr.responseJSON?.error || xhr.statusText), 'error');
                });
        },
        
        // ============================================
        // SAFETY
        // ============================================
        
        loadSafety: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/safety`, 'GET')
                .done(function(response) {
                    const $incidents = $('#pi-dr-incidents-tbody');
                    $incidents.empty();
                    
                    response.filter(r => r.record_type === 'incident' || r.record_type === 'near_miss').forEach(function(incident) {
                        $incidents.append(`
                            <tr data-id="${incident.id}">
                                <td>${incident.incident_type || '-'}</td>
                                <td>${incident.severity || '-'}</td>
                                <td>${incident.description}</td>
                                <td>${incident.injured_party || '-'}</td>
                                <td>${incident.occurred_at || '-'}</td>
                                <td>
                                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn" data-action="edit-incident" data-id="${incident.id}">Edit</button>
                                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn pi-dr-btn-danger" data-action="delete-incident" data-id="${incident.id}">Delete</button>
                                </td>
                            </tr>
                        `);
                    });
                });
        },
        
        saveIncident: function(formData) {
            const self = this;
            const incidentId = formData.id;
            
            const data = {
                record_type: formData.recordType,
                incident_type: formData.type,
                severity: formData.severity,
                description: formData.description,
                location_area: formData.location,
                injured_party: formData.injured,
                witness_names: formData.witnesses,
                immediate_actions: formData.actions,
                root_cause: formData.cause
            };
            
            if (incidentId) {
                this.apiCall(`/daily-safety/${incidentId}`, 'PUT', data)
                    .done(function() {
                        self.showToast('Incident updated', 'success');
                        self.loadSafety();
                        self.closeModal();
                    });
            } else {
                this.apiCall(`/reports/${this.reportId}/safety`, 'POST', data)
                    .done(function() {
                        self.showToast('Incident reported', 'success');
                        self.loadSafety();
                        self.closeModal();
                    });
            }
        },
        
        // ============================================
        // VISITORS
        // ============================================
        
        loadVisitors: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/visitors`, 'GET')
                .done(function(response) {
                    const $tbody = $('#pi-dr-visitors-tbody');
                    $tbody.empty();
                    
                    response.forEach(function(visitor) {
                        $tbody.append(`
                            <tr data-id="${visitor.id}">
                                <td>${visitor.visitor_name}</td>
                                <td>${visitor.company || '-'}</td>
                                <td>${visitor.purpose || '-'}</td>
                                <td>${visitor.arrival_time || '-'}</td>
                                <td>${visitor.departure_time || '-'}</td>
                                <td>${visitor.host_name || '-'}</td>
                                <td>
                                    <button class="pi-dr-btn pi-dr-btn-small pi-dr-action-btn pi-dr-btn-danger" data-action="delete-visitor" data-id="${visitor.id}">Delete</button>
                                </td>
                            </tr>
                        `);
                    });
                });
        },
        
        saveVisitor: function(formData) {
            const self = this;
            
            const data = {
                visitor_name: formData.name,
                company: formData.company,
                purpose: formData.purpose,
                host_name: formData.host
            };
            
            this.apiCall(`/reports/${this.reportId}/visitors`, 'POST', data)
                .done(function() {
                    self.showToast('Visitor added', 'success');
                    self.loadVisitors();
                    self.closeModal();
                });
        },
        
        // ============================================
        // PHOTOS
        // ============================================
        
        loadPhotos: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/photos`, 'GET')
                .done(function(response) {
                    const $grid = $('#pi-dr-photo-grid');
                    $grid.empty();
                    
                    response.forEach(function(photo) {
                        $grid.append(`
                            <div class="pi-dr-photo-item" data-id="${photo.id}">
                                <img src="${photo.photo_url}" alt="${photo.file_name}">
                                <button class="pi-dr-photo-delete" data-id="${photo.id}">&times;</button>
                            </div>
                        `);
                    });
                });
        },
        
        uploadPhotos: function(files) {
            const self = this;
            
            if (!files || files.length === 0) return;
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        self.processPhotoUpload(files, {
                            gps_lat: position.coords.latitude,
                            gps_lng: position.coords.longitude,
                            gps_accuracy: position.coords.accuracy
                        });
                    },
                    function() {
                        self.processPhotoUpload(files, {});
                    }
                );
            } else {
                self.processPhotoUpload(files, {});
            }
        },
        
        processPhotoUpload: function(files, gpsData) {
            const self = this;
            
            Array.from(files).forEach(function(file) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('gps_lat', gpsData.gps_lat || 0);
                formData.append('gps_lng', gpsData.gps_lng || 0);
                formData.append('gps_accuracy', gpsData.gps_accuracy || 0);
                
                // Increment counter before starting upload
                self.activeRequests++;
                if (self.activeRequests === 1) {
                    $('#pi-dr-loading').show();
                }
                
                $.ajax({
                    url: self.apiBase + `/reports/${self.reportId}/photos`,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-WP-Nonce': self.nonce
                    },
                    success: function() {
                        self.showToast('Photo uploaded', 'success');
                        self.loadPhotos();
                    },
                    error: function() {
                        self.showToast('Error uploading photo', 'error');
                    },
                    complete: function() {
                        self.activeRequests--;
                        if (self.activeRequests <= 0) {
                            self.activeRequests = 0;
                            $('#pi-dr-loading').hide();
                        }
                    }
                });
            });
        },
        
        deletePhoto: function(photoId) {
            const self = this;
            
            this.apiCall(`/daily-photos/${photoId}`, 'DELETE')
                .done(function() {
                    self.showToast('Photo deleted', 'success');
                    self.loadPhotos();
                });
        },
        
        loadRatings: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/ratings`, 'GET')
                .done(function(response) {
                    if (response) {
                        $('#pi-dr-rating-productivity').val(response.productivity_rating || 5);
                        $('#pi-dr-rating-safety').val(response.safety_rating || 5);
                        $('#pi-dr-rating-quality').val(response.quality_rating || 5);
                        $('#pi-dr-rating-conditions').val(response.site_conditions_rating || 5);
                        
                        self.updateRatingValues();
                        $('#pi-dr-overall-score').text(response.overall_score?.toFixed(1) || '-');
                        $('#pi-dr-score-grade').text(response.letter_grade || '-');
                        $('#pi-dr-rating-justification').val(response.rating_justification || '');
                    }
                });
        },
        
        updateRatingValues: function() {
            $('#pi-dr-rating-productivity-value').text($('#pi-dr-rating-productivity').val());
            $('#pi-dr-rating-safety-value').text($('#pi-dr-rating-safety').val());
            $('#pi-dr-rating-quality-value').text($('#pi-dr-rating-quality').val());
            $('#pi-dr-rating-conditions-value').text($('#pi-dr-rating-conditions').val());
        },
        
        saveRatings: function() {
            const self = this;
            
            const data = {
                productivity_rating: parseFloat($('#pi-dr-rating-productivity').val()),
                safety_rating: parseFloat($('#pi-dr-rating-safety').val()),
                quality_rating: parseFloat($('#pi-dr-rating-quality').val()),
                site_conditions_rating: parseFloat($('#pi-dr-rating-conditions').val()),
                rating_justification: $('#pi-dr-rating-justification').val()
            };
            
            this.apiCall(`/reports/${this.reportId}/ratings`, 'POST', data)
                .done(function(response) {
                    self.showToast('Ratings saved', 'success');
                    $('#pi-dr-overall-score').text(response.overall_score?.toFixed(1) || '-');
                    $('#pi-dr-score-grade').text(response.letter_grade || '-');
                });
        },
        
        // ============================================
        // CLOCK IN/OUT
        // ============================================
        
        checkClockStatus: function() {
            const self = this;
            const teamApiBase = (PI_Daily_Reports_Settings.team_rest_base || PI_Daily_Reports_Settings.rest_base.replace('/daily-reports/v1', '/v1')).replace(/\/+$/, '');

            fetch(`${teamApiBase}/clock/on-site?job_id=${this.jobId}`, {
                headers: {
                    'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(response => {
                // Robust parsing
                let workers = [];
                if (Array.isArray(response)) {
                    workers = response;
                } else if (response && response.code && response.message) {
                    throw new Error(response.message);
                } else if (response && response.data && Array.isArray(response.data)) {
                    workers = response.data;
                }
                
                const currentWorker = workers.find(w => w.employee_id === PI_Daily_Reports_Settings.user_id);
                
                if (currentWorker && currentWorker.clock_out === null) {
                    self.clockedIn = true;
                    $('#pi-dr-checkin-status-text').text('Clocked In');
                    $('#pi-dr-checkin-time').text(`Since ${currentWorker.clock_in}`);
                    $('#pi-dr-btn-clock-in').hide();
                    $('#pi-dr-btn-clock-out').show();
                    
                    // Break functionality handled by Team & Timesheets system
                } else {
                    self.clockedIn = false;
                    $('#pi-dr-checkin-status-text').text('Not Clocked In');
                    $('#pi-dr-btn-clock-in').show();
                    $('#pi-dr-btn-clock-out').hide();
                    // Break buttons handled by Team & Timesheets system
                }
                
                self.renderOnsiteWorkers(workers);
            })
            .catch(error => {
                console.error('Failed to check clock status:', error);
                self.showToast('Failed to check clock status', 'error');
            });
        },
        
        clockAction: function(action) {
            const self = this;
            const teamApiBase = (PI_Daily_Reports_Settings.team_rest_base || PI_Daily_Reports_Settings.rest_base.replace('/daily-reports/v1', '/v1')).replace(/\/+$/, '');
            
            const data = {
                job_id: this.jobId,
                action: action === 'in' ? 'clock_in' : 'clock_out'
            };
            
            // Add GPS data if available
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        data.gps_lat = position.coords.latitude;
                        data.gps_lng = position.coords.longitude;
                        self.performClockAction(teamApiBase, data);
                    },
                    function(error) {
                        console.warn('GPS not available, proceeding without location');
                        self.performClockAction(teamApiBase, data);
                    }
                );
            } else {
                self.performClockAction(teamApiBase, data);
            }
        },
        
        performClockAction: function(teamApiBase, data) {
            const self = this;
            const endpoint = data.action === 'clock_in' ? '/clock/in' : '/clock/out';

            fetch(`${teamApiBase}${endpoint}`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const action = data.action === 'clock_in' ? 'Clocked in' : 'Clocked out';
                    self.showToast(action + ' successfully', 'success');
                    self.checkClockStatus(); // Refresh clock status
                    self.loadLabor(); // Refresh labor entries
                } else {
                    throw new Error(result.message || 'Clock action failed');
                }
            })
            .catch(error => {
                console.error('Clock action failed:', error);
                self.showToast('Failed to ' + data.action.replace('_', ' '), 'error');
            });
        },
        
        // Break functionality removed - now handled through Team & Timesheets system
        
        renderOnsiteWorkers: function(workers) {
            const $list = $('#pi-dr-onsite-list');
            $list.empty();
            
            const workersArray = Array.isArray(workers) ? workers : [];
            workersArray.forEach(function(worker) {
                const initials = worker.worker_name.split(' ').map(n => n[0]).join('').toUpperCase();
                $list.append(`
                    <div class="pi-dr-onsite-worker">
                        <div class="pi-dr-onsite-worker-info">
                            <div class="pi-dr-onsite-worker-avatar">${initials}</div>
                            <div>
                                <div>${worker.worker_name}</div>
                                <div class="pi-dr-onsite-worker-time">${worker.trade || ''} • Clocked in ${worker.clock_in}</div>
                            </div>
                        </div>
                        <div class="pi-dr-onsite-worker-status">On Site</div>
                    </div>
                `);
            });
        },
        
        loadEmployeesForLabor: function() {
            const self = this;
            
            // Use the dedicated integration endpoint for daily reports
            // This endpoint properly handles job-specific employee assignments
            fetch(`${this.apiBase}/integration/job/${this.jobId}/workers`, {
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                // Check HTTP status first
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(response => {
                // Robust parsing: handle all response formats
                let employees = [];
                
                if (Array.isArray(response)) {
                    // Direct array response (ideal)
                    employees = response;
                } else if (response && response.code && response.message) {
                    // WordPress REST API error response
                    throw new Error(response.message);
                } else if (response && response.data) {
                    if (Array.isArray(response.data)) {
                        // Wrapped array in data property
                        employees = response.data;
                    } else if (response.data.code && response.data.message) {
                        // Nested error
                        throw new Error(response.data.message);
                    }
                } else if (response && response.employees && Array.isArray(response.employees)) {
                    // Named employees property
                    employees = response.employees;
                }
                
                // Ensure employees is always an array
                if (!Array.isArray(employees)) {
                    console.warn('Unexpected response format:', response);
                    employees = [];
                }
                
                const $select = $('#pi-dr-labor-employee');
                $select.empty().append('<option value="">Select Employee...</option>');
                
                employees.forEach(employee => {
                    const displayName = `${employee.first_name || ''} ${employee.last_name || ''}`.trim() || 'Unknown';
                    $select.append(`<option value="${employee.id}" data-trade="${employee.trade || ''}" data-company="${employee.company || ''}" data-rate="${employee.hourly_rate || ''}">${displayName}</option>`);
                });
                
                // Remove any existing change handlers to prevent duplicates
                $select.off('change').on('change', function() {
                    const selected = $(this).find('option:selected');
                    $('#pi-dr-labor-trade').val(selected.data('trade') || '');
                    $('#pi-dr-labor-company').val(selected.data('company') || '');
                    $('#pi-dr-labor-rate').val(selected.data('rate') || '');
                });
                
                // Show message if no employees found
                if (employees.length === 0) {
                    self.showToast('No employees assigned to this job. Add team members via the Team & Timesheets page.', 'info');
                }
            })
            .catch(error => {
                console.error('Failed to load employees:', error);
                self.showToast('Failed to load employees: ' + (error.message || 'Unknown error'), 'error');
            });
        },
        
        // ============================================
        // SUBMIT / APPROVE
        // ============================================
        
        submitReport: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/submit`, 'POST', {})
                .done(function() {
                    self.showToast('Report submitted for review', 'success');
                    self.loadTodaysReport();
                });
        },
        
        approveReport: function() {
            const self = this;
            
            this.apiCall(`/reports/${this.reportId}/approve`, 'POST', {})
                .done(function() {
                    self.showToast('Report approved', 'success');
                    self.loadTodaysReport();
                });
        },
        
        // ============================================
        // AUTO SAVE
        // ============================================
        
        startAutoSave: function() {
            const self = this;
            
            this.autoSaveInterval = setInterval(function() {
                self.autoSave();
            }, 60000); // Auto-save every minute
        },
        
        autoSave: function() {
            if (!this.reportId) return;
            
            const data = {
                general_notes: $('#pi-dr-general-notes').val(),
                client_communications: $('#pi-dr-client-comm').val(),
                tomorrow_blockers: $('#pi-dr-tomorrow-blockers').val(),
                last_auto_save: new Date().toISOString()
            };
            
            this.apiCall(`/reports/${this.reportId}`, 'PUT', data)
                .done(function() {
                    // Silent auto-save
                });
        },
        
        // ============================================
        // MODALS
        // ============================================
        
        openModal: function(modalId) {
            $(`#${modalId}`).show();
        },
        
        closeModal: function() {
            $('.pi-dr-modal').hide();
        },
        
        // ============================================
        // TOAST NOTIFICATIONS
        // ============================================
        
        showToast: function(message, type = 'info') {
            const $container = $('#pi-dr-toast-container');
            const $toast = $(`<div class="pi-dr-toast pi-dr-toast-${type}">${message}</div>`);
            
            $container.append($toast);
            
            setTimeout(function() {
                $toast.remove();
            }, 3000);
        },
        
        // ============================================
        // MODAL SYSTEM
        // ============================================
        
        showModal: function(content, title = 'Modal', footer = null) {
            // Remove any existing modal
            $('#pi-dr-modal-overlay').remove();
            
            const modalHtml = `
                <div class="pi-dr-modal-overlay" id="pi-dr-modal-overlay">
                    <div class="pi-dr-modal-container">
                        <div class="pi-dr-modal-header">
                            <h3>${title}</h3>
                            <button type="button" class="pi-dr-modal-close" id="pi-dr-modal-close">&times;</button>
                        </div>
                        <div class="pi-dr-modal-body">
                            ${content}
                        </div>
                        ${footer ? `<div class="pi-dr-modal-footer">${footer}</div>` : ''}
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('body').addClass('pi-dr-modal-open');
            
            // Bind close events
            $('#pi-dr-modal-close, #pi-dr-modal-overlay').on('click', function(e) {
                if (e.target.id === 'pi-dr-modal-overlay' || e.target.id === 'pi-dr-modal-close') {
                    PI_Daily_Reports.closeModal();
                }
            });
            
            // Bind escape key
            $(document).on('keydown.pi-dr-modal', function(e) {
                if (e.key === 'Escape') {
                    PI_Daily_Reports.closeModal();
                }
            });
        },
        
        closeModal: function() {
            $('#pi-dr-modal-overlay').remove();
            $('body').removeClass('pi-dr-modal-open');
            $(document).off('keydown.pi-dr-modal');
        },
        
        // ============================================
        // LOADING SYSTEM
        // ============================================
        
        showLoading: function(message = 'Loading...') {
            this.hideLoading(); // Remove any existing loading
            
            const loadingHtml = `
                <div class="pi-dr-loading-overlay" id="pi-dr-loading-overlay">
                    <div class="pi-dr-loading-spinner">
                        <div class="pi-dr-spinner"></div>
                        <div class="pi-dr-loading-message">${message}</div>
                    </div>
                </div>
            `;
            
            $('body').append(loadingHtml);
        },
        
        hideLoading: function() {
            $('#pi-dr-loading-overlay').remove();
        },
        
        // ============================================
        // EVENT BINDING
        // ============================================
        
        bindEvents: function() {
            const self = this;
            
            // Tab navigation
            $('.pi-dr-nav-tab').on('click', function() {
                $('.pi-dr-nav-tab').removeClass('active');
                $(this).addClass('active');
                
                const tab = $(this).data('tab');
                $('.pi-dr-tab-content').removeClass('active');
                $(`#pi-dr-tab-${tab}`).addClass('active');
            });
            
            // Save draft
            $('#pi-dr-btn-save-draft').on('click', function() {
                self.autoSave();
                self.showToast('Draft saved', 'success');
            });
            
            // Submit report
            $('#pi-dr-btn-submit').on('click', function() {
                if (confirm('Submit this report for review?')) {
                    self.submitReport();
                }
            });
            
            // Approve report
            $('#pi-dr-btn-approve').on('click', function() {
                if (confirm('Approve this report?')) {
                    self.approveReport();
                }
            });
            
            // Clock in/out
            $('#pi-dr-btn-clock-in').on('click', function() {
                self.clockAction('in');
            });
            
            $('#pi-dr-btn-clock-out').on('click', function() {
                self.clockAction('out');
            });
            
            // Break functionality removed - buttons now handled by Team & Timesheets system
            
            // Modal buttons
            $('#pi-dr-btn-add-labor').on('click', function() {
                $('#pi-dr-form-labor')[0].reset();
                $('#pi-dr-labor-id').val('');
                self.loadEmployeesForLabor();
                self.openModal('pi-dr-modal-labor');
            });
            
            $('#pi-dr-btn-add-activity').on('click', function() {
                $('#pi-dr-form-activity')[0].reset();
                $('#pi-dr-activity-id').val('');
                self.openModal('pi-dr-modal-activity');
            });
            
            $('#pi-dr-btn-add-equipment').on('click', function() {
                $('#pi-dr-form-equipment')[0].reset();
                $('#pi-dr-equipment-id').val('');
                $('#pi-dr-equipment-linked-id').val('');
                self.loadJobEquipment();
                self.openModal('pi-dr-modal-equipment');
            });

            $('#pi-dr-equipment-select').on('change', function() {
                const $opt = $(this).find('option:selected');
                const val = $opt.val();
                if (!val) return;
                $('#pi-dr-equipment-linked-id').val(val);
                $('#pi-dr-equipment-name').val($opt.text());
                $('#pi-dr-equipment-type').val($opt.data('type') || '');
                $('#pi-dr-equipment-hours').val('');
                $('#pi-dr-equipment-operator').val($opt.data('operator') || '');
                const acquisition = ($opt.data('acquisition') || 'Owned').toLowerCase();
                $('#pi-dr-equipment-ownership').val(acquisition);
            });
            
            $('#pi-dr-btn-add-material').on('click', function() {
                $('#pi-dr-form-material')[0].reset();
                $('#pi-dr-material-id').val('');
                self.openModal('pi-dr-modal-material');
            });
            
            $('#pi-dr-btn-add-incident').on('click', function() {
                $('#pi-dr-form-incident')[0].reset();
                $('#pi-dr-incident-id').val('');
                self.openModal('pi-dr-modal-incident');
            });
            
            $('#pi-dr-btn-add-visitor').on('click', function() {
                $('#pi-dr-form-visitor')[0].reset();
                $('#pi-dr-visitor-id').val('');
                self.openModal('pi-dr-modal-visitor');
            });
            
            // Weather widget event listeners
            $(document).on('click', '#crm-weather-refresh', function() {
                self.refreshWeather();
            });
            
            $(document).on('click', '#crm-weather-set-location', function() {
                self.showToast('Please set site location in the Site Map tab', 'info');
            });
            
            // Photo upload
            $('#pi-dr-btn-upload-photos').on('click', function() {
                $('#pi-dr-photo-input').click();
            });
            
            $('#pi-dr-photo-input').on('change', function() {
                const files = this.files;
                self.uploadPhotos(files);
                this.value = '';
            });
            
            // Photo delete
            $(document).on('click', '.pi-dr-photo-delete', function() {
                const photoId = $(this).data('id');
                if (confirm('Delete this photo?')) {
                    self.deletePhoto(photoId);
                }
            });
            
            // Close modals
            $(document).on('click', '.pi-dr-modal-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeModal();
            });
            
            $(document).on('click', '.pi-dr-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // Form submissions
            $('#pi-dr-form-labor').on('submit', function(e) {
                e.preventDefault();
                const employeeId = $('#pi-dr-labor-employee').val();
                self.saveLabor({
                    id: $('#pi-dr-labor-id').val(),
                    employee_id: employeeId,
                    name: $('#pi-dr-labor-employee option:selected').text(),
                    trade: $('#pi-dr-labor-trade').val(),
                    company: $('#pi-dr-labor-company').val(),
                    hourly_rate: $('#pi-dr-labor-rate').val(),
                    cost_code: $('#pi-dr-labor-cost-code').val(),
                    notes: $('#pi-dr-labor-notes').val()
                });
            });
            
            $('#pi-dr-form-activity').on('submit', function(e) {
                e.preventDefault();
                self.saveActivity({
                    id: $('#pi-dr-activity-id').val(),
                    location: $('#pi-dr-activity-location').val(),
                    trade: $('#pi-dr-activity-trade').val(),
                    type: $('#pi-dr-activity-type').val(),
                    description: $('#pi-dr-activity-description').val(),
                    quantity: $('#pi-dr-activity-quantity').val(),
                    unit: $('#pi-dr-activity-unit').val(),
                    percent: $('#pi-dr-activity-percent').val(),
                    start: $('#pi-dr-activity-start').val(),
                    end: $('#pi-dr-activity-end').val(),
                    costCode: $('#pi-dr-activity-cost-code').val(),
                    phase: $('#pi-dr-activity-phase').val(),
                    delayReason: $('#pi-dr-activity-delay-reason').val(),
                    delayHours: $('#pi-dr-activity-delay-hours').val(),
                    blockers: $('#pi-dr-activity-blockers').val()
                });
            });
            
            $('#pi-dr-form-equipment').on('submit', function(e) {
                e.preventDefault();
                self.saveEquipment({
                    id: $('#pi-dr-equipment-id').val(),
                    equipment_id: $('#pi-dr-equipment-linked-id').val(),
                    name: $('#pi-dr-equipment-name').val(),
                    type: $('#pi-dr-equipment-type').val(),
                    ownership: $('#pi-dr-equipment-ownership').val(),
                    status: $('#pi-dr-equipment-status').val(),
                    hours: $('#pi-dr-equipment-hours').val(),
                    operator: $('#pi-dr-equipment-operator').val(),
                    fuel: $('#pi-dr-equipment-fuel').val(),
                    maintenance: $('#pi-dr-equipment-maintenance').val(),
                    downtimeReason: $('#pi-dr-equipment-downtime-reason').val(),
                    downtimeHours: $('#pi-dr-equipment-downtime-hours').val(),
                    meterReading: $('#pi-dr-equipment-meter').val()
                });
            });
            
            $('#pi-dr-form-material').on('submit', function(e) {
                e.preventDefault();
                self.saveMaterial({
                    id: $('#pi-dr-material-id').val(),
                    supplier: $('#pi-dr-material-supplier').val(),
                    po: $('#pi-dr-material-po').val(),
                    description: $('#pi-dr-material-description').val(),
                    quantity: $('#pi-dr-material-quantity').val(),
                    unit: $('#pi-dr-material-unit').val(),
                    condition: $('#pi-dr-material-condition').val(),
                    receivedBy: $('#pi-dr-material-received-by').val(),
                    missing: $('#pi-dr-material-missing').is(':checked'),
                    scheduledDate: $('#pi-dr-material-scheduled-date').val()
                });
            });
            
            $('#pi-dr-form-incident').on('submit', function(e) {
                e.preventDefault();
                self.saveIncident({
                    id: $('#pi-dr-incident-id').val(),
                    recordType: $('#pi-dr-incident-type-record').val(),
                    type: $('#pi-dr-incident-type').val(),
                    severity: $('#pi-dr-incident-severity').val(),
                    description: $('#pi-dr-incident-description').val(),
                    location: $('#pi-dr-incident-location').val(),
                    injured: $('#pi-dr-incident-injured').val(),
                    witnesses: $('#pi-dr-incident-witnesses').val(),
                    actions: $('#pi-dr-incident-actions').val(),
                    cause: $('#pi-dr-incident-cause').val()
                });
            });
            
            $('#pi-dr-form-visitor').on('submit', function(e) {
                e.preventDefault();
                self.saveVisitor({
                    id: $('#pi-dr-visitor-id').val(),
                    name: $('#pi-dr-visitor-name').val(),
                    company: $('#pi-dr-visitor-company').val(),
                    purpose: $('#pi-dr-visitor-purpose').val(),
                    host: $('#pi-dr-visitor-host').val()
                });
            });
            
            // Rating sliders
            $('.pi-dr-rating-slider').on('input', function() {
                self.updateRatingValues();
            });
            
            // Save ratings on change
            $('.pi-dr-rating-slider').on('change', function() {
                self.saveRatings();
            });
            
            // Delete actions
            $(document).on('click', '[data-action^="delete-"]', function() {
                const action = $(this).data('action');
                const id = $(this).data('id');
                
                if (confirm('Delete this item?')) {
                    if (action === 'delete-labor') {
                        // Use Team Timesheets API for labor deletion
                        const teamApiBase = (PI_Daily_Reports_Settings.team_rest_base || PI_Daily_Reports_Settings.rest_base.replace('/daily-reports/v1', '/v1')).replace(/\/+$/, '');
                        fetch(`${teamApiBase}/timesheets/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-WP-Nonce': PI_Daily_Reports_Settings.nonce,
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                self.showToast('Labor entry deleted', 'success');
                                self.loadLabor();
                            } else {
                                throw new Error(result.message || 'Failed to delete labor entry');
                            }
                        })
                        .catch(error => {
                            console.error('Failed to delete labor:', error);
                            self.showToast('Failed to delete labor entry', 'error');
                        });
                    } else {
                        // Use Daily Reports API for other deletions
                        const endpoint = action.replace('delete-', '');
                        self.apiCall(`/${endpoint}/${id}`, 'DELETE')
                            .done(function() {
                                self.showToast('Item deleted', 'success');
                                
                                if (action === 'delete-activity') self.loadActivities();
                                else if (action === 'delete-equipment') self.loadEquipment();
                                else if (action === 'delete-material') self.loadMaterials();
                                else if (action === 'delete-incident') self.loadSafety();
                                else if (action === 'delete-visitor') self.loadVisitors();
                            });
                    }
                }
            });
            
            // Material missing checkbox toggle
            $('#pi-dr-material-missing').on('change', function() {
                $('#pi-dr-material-scheduled-date-group').toggle($(this).is(':checked'));
            });
        }
    };
    
    $(document).ready(function() {
        // Assign the fully initialized object to window, overriding the stubs
        window.PI_Daily_Reports = PI_Daily_Reports;
        PI_Daily_Reports.init();

        // Refresh when Daily Reports tab is clicked
        $(document).on('click', '[data-job-tab="daily-reports"]', function() {
            $('#pi-dr-loading').hide();
            PI_Daily_Reports.activeRequests = 0; // Reset counter
            if (PI_Daily_Reports._initialized) {
                PI_Daily_Reports.loadReport();
            } else {
                setTimeout(function() {
                    PI_Daily_Reports.init();
                }, 100);
            }
        });
    });
    
})(jQuery);
