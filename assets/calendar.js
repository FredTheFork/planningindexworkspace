/**
 * Planning Index Workspace - Premium Calendar v3.0
 *
 * Comprehensive construction calendar with advanced features:
 * - Weather integration (Open-Meteo API)
 * - Crew conflict detection with availability heatmap
 * - Job-centric mode with mini Gantt
 * - Timeline/Gantt view
 * - Linked expenses integration
 * - Mileage logging
 * - Photo attachments
 * - Checklist management
 * - 3-week lookahead
 * - Premium toast notifications
 * - Keyboard shortcuts
 *
 * @package PlanningIndex
 * @version 3.0.0
 */
(function($) {
    'use strict';

    if (typeof FullCalendar === 'undefined' || !FullCalendar.Calendar || typeof PI_Calendar === 'undefined') {
        console.warn('FullCalendar or PI_Calendar not loaded');
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // APP STATE
    // ─────────────────────────────────────────────────────────────────────────
    const AppState = {
        currentView: 'dayGridMonth',
        jobCentricMode: false,
        selectedJobId: null,
        filters: {
            type: '',
            job: '',
            status: '',
            priority: '',
            crew: '',
            trade: ''
        },
        searchQuery: '',
        weatherData: {},
        eventCache: [],
        templates: [],
        savedViews: [],
        checklist: [],
        attachments: [],
        conflicts: [],
        pendingEventData: null,
        contextEventId: null,
        shortcutsOpen: false,

        init() {
            this.loadFromStorage();
            this.bindGlobalEvents();
            return this;
        },

        loadFromStorage() {
            try {
                const saved = localStorage.getItem('pi_calendar_v3_state');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    this.filters = { ...this.filters, ...(parsed.filters || {}) };
                    this.currentView = parsed.currentView || 'dayGridMonth';
                    this.jobCentricMode = parsed.jobCentricMode || false;
                }
            } catch (e) {
                console.warn('Failed to load state:', e);
            }
        },

        saveToStorage() {
            try {
                localStorage.setItem('pi_calendar_v3_state', JSON.stringify({
                    filters: this.filters,
                    currentView: this.currentView,
                    jobCentricMode: this.jobCentricMode,
                    timestamp: Date.now()
                }));
            } catch (e) {
                console.warn('Failed to save state:', e);
            }
        },

        setFilter(key, value) {
            this.filters[key] = value;
            this.saveToStorage();
        },

        bindGlobalEvents() {
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#pi-cal-context-menu, .fc-event').length) {
                    $('#pi-cal-context-menu').hide();
                }
            });
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // API CLIENT
    // ─────────────────────────────────────────────────────────────────────────
    const ApiClient = {
        fetchHeaders() {
            return {
                'X-WP-Nonce': PI_Calendar.nonce,
                'Content-Type': 'application/json'
            };
        },

        async getEvents(params = {}) {
            const query = new URLSearchParams(params).toString();
            const response = await fetch(`${PI_Calendar.rest_base}/events?${query}`, {
                method: 'GET',
                headers: this.fetchHeaders()
            });
            if (!response.ok) throw new Error('Failed to fetch events');
            return response.json();
        },

        async createEvent(data) {
            const response = await fetch(`${PI_Calendar.rest_base}/events/add`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify(data)
            });
            return response.json();
        },

        async updateEvent(data) {
            const response = await fetch(`${PI_Calendar.rest_base}/events/update`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify(data)
            });
            return response.json();
        },

        async deleteEvent(id) {
            const response = await fetch(`${PI_Calendar.rest_base}/events/remove`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify({ id })
            });
            return response.json();
        },

        async duplicateEvent(id) {
            const response = await fetch(`${PI_Calendar.rest_base}/events/duplicate`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify({ id })
            });
            return response.json();
        },

        async getTemplates() {
            const response = await fetch(`${PI_Calendar.rest_base}/templates`, {
                method: 'GET',
                headers: this.fetchHeaders()
            });
            return response.json();
        },

        async saveTemplate(data) {
            const response = await fetch(`${PI_Calendar.rest_base}/templates/add`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify(data)
            });
            return response.json();
        },

        async deleteTemplate(id) {
            const response = await fetch(`${PI_Calendar.rest_base}/templates/remove`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify({ id })
            });
            return response.json();
        },

        async getLinkedExpenses(jobId, date) {
            const params = new URLSearchParams({ job_id: jobId, date: date }).toString();
            const response = await fetch(`${PI_Calendar.rest_base}/events/linked-expenses?${params}`, {
                method: 'GET',
                headers: this.fetchHeaders()
            });
            return response.json();
        },

        async getStats() {
            const response = await fetch(`${PI_Calendar.rest_base}/stats`, {
                method: 'GET',
                headers: this.fetchHeaders()
            });
            return response.json();
        },

        async saveView(name, filters) {
            const response = await fetch(`${PI_Calendar.rest_base}/views/save`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify({ name, filters })
            });
            return response.json();
        },

        async deleteView(id) {
            const response = await fetch(`${PI_Calendar.rest_base}/views/delete`, {
                method: 'POST',
                headers: this.fetchHeaders(),
                body: JSON.stringify({ id })
            });
            return response.json();
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // WEATHER SERVICE (Open-Meteo API)
    // ─────────────────────────────────────────────────────────────────────────
    const WeatherService = {
        cache: {},
        cacheExpiry: 30 * 60 * 1000, // 30 minutes

        async fetchForecast(latitude, longitude, date) {
            const cacheKey = `${latitude},${longitude},${date}`;
            const cached = this.cache[cacheKey];

            if (cached && (Date.now() - cached.timestamp) < this.cacheExpiry) {
                return cached.data;
            }

            try {
                // Open-Meteo API - free, no API key required
                const url = `https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&daily=weather_code,temperature_2m_max,precipitation_probability_max,windspeed_10m_max&timezone=Europe/London&start_date=${date}&end_date=${date}`;

                const response = await fetch(url);
                if (!response.ok) throw new Error('Weather fetch failed');
                const data = await response.json();

                this.cache[cacheKey] = { data, timestamp: Date.now() };
                return data;
            } catch (error) {
                console.warn('Weather fetch failed:', error);
                return null;
            }
        },

        getWeatherIcon(code) {
            // Professional weather icons from weathericons directory
            // WMO Weather interpretation codes mapped to our SVG icons
            const icons = {
                0: 'sunny',                    // Clear sky
                1: 'sunny',                    // Mainly clear
                2: 'partly_cloudy',            // Partly cloudy
                3: 'mostly_sunny',             // Overcast
                45: 'cloudy',                  // Fog
                48: 'cloudy',                  // Depositing rime fog
                51: 'drizzle',                 // Light drizzle
                53: 'drizzle',                 // Moderate drizzle
                55: 'drizzle',                 // Dense drizzle
                61: 'heavy_rain',              // Slight rain
                63: 'heavy_rain',              // Moderate rain
                65: 'heavy_rain',              // Heavy rain
                71: 'snow_showers',            // Slight snow
                73: 'heavy_snow',              // Moderate snow
                75: 'blizzard',                // Heavy snow
                95: 'thunderstorms',           // Thunderstorm
                96: 'strong_thunderstorms',    // Thunderstorm with hail
                99: 'strong_thunderstorms',    // Heavy thunderstorm
                
                // Additional weather conditions
                56: 'sleet_hail',              // Freezing drizzle
                57: 'sleet_hail',              // Freezing drizzle
                66: 'sleet_hail',              // Light freezing rain
                67: 'sleet_hail',              // Heavy freezing rain
                
                // Night conditions
                'clear_night': 'clear_night',
                'mostly_clear_night': 'mostly_clear_night',
                'partly_cloudy_night': 'partly_cloudy_night',
                'mostly_cloudy_night': 'mostly_cloudy_night',
                
                // Extreme weather
                'hurricane': 'hurricane',
                'tornado': 'tornado',
                'blowing_snow': 'blowing_snow',
                'flurries': 'flurries',
                'icy': 'icy',
                'wintry_mix': 'wintry_mix',
                'windy': 'windy'
            };
            return icons[code] || 'cloud';
        },

        isBadWeather(code) {
            // Codes indicating rain, snow, or storms
            return [51, 53, 55, 61, 63, 65, 71, 73, 75, 80, 81, 82, 95, 96, 99].includes(code);
        },

        formatWeather(data) {
            if (!data || !data.daily) return null;

            const code = data.daily.weather_code[0];
            const temp = data.daily.temperature_2m_max[0];
            const precip = data.daily.precipitation_probability_max[0];
            const wind = data.daily.windspeed_10m_max[0];

            console.log(`[WeatherService] Formatting weather - Code: ${code}, Temp: ${temp}°C, Precip: ${precip}%`);

            return {
                code,
                icon: this.getWeatherIcon(code),
                svgIcon: getWeatherIconPath(this.getWeatherIcon(code)),
                temperature: temp,
                precipitation: precip,
                windSpeed: wind,
                isBad: this.isBadWeather(code)
            };
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // TOAST NOTIFICATIONS
    // ─────────────────────────────────────────────────────────────────────────
    const Toast = {
        icons: {
            success: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        },

        show(message, type = 'success', duration = 3000) {
            let container = document.querySelector('.pi-cal-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'pi-cal-toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `pi-cal-toast pi-cal-toast--${type}`;
            toast.innerHTML = `
                <span class="pi-cal-toast-icon">${this.icons[type]}</span>
                <span class="pi-cal-toast-message">${message}</span>
            `;

            container.appendChild(toast);

            // Animate in
            requestAnimationFrame(() => {
                toast.style.animation = 'piCalToastIn 0.3s ease forwards';
            });

            // Remove after duration
            setTimeout(() => {
                toast.style.animation = 'piCalToastOut 0.3s ease forwards';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, duration);
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // CALENDAR INITIALIZATION
    // ─────────────────────────────────────────────────────────────────────────
    let calendar;

    function initCalendar() {
        const calendarEl = document.getElementById('pi-workspace-calendar');
        if (!calendarEl) return;

        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: AppState.currentView,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            height: 'auto',
            nowIndicator: true,
            editable: true,
            selectable: true,
            selectMirror: true,
            eventOverlap: true,
            dayMaxEvents: 3,
            eventDisplay: 'block',
            businessHours: {
                daysOfWeek: [1, 2, 3, 4, 5],
                startTime: '08:00',
                endTime: '18:00'
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',

            // Custom day cell content for weather
            dayCellContent: function(arg) {
                const content = document.createElement('div');
                content.className = 'pi-cal-day-cell';

                // Day number
                const dayNum = document.createElement('span');
                dayNum.className = 'pi-cal-day-number';
                dayNum.textContent = arg.dayNumberText;
                content.appendChild(dayNum);

                // Weather indicator (if we have cached data)
                const dateStr = arg.date.toISOString().split('T')[0];
                if (AppState.weatherData[dateStr]) {
                    const weather = AppState.weatherData[dateStr];
                    const weatherEl = document.createElement('span');
                    weatherEl.className = `pi-cal-weather-indicator ${weather.isBad ? 'pi-cal-weather-bad' : ''}`;
                    weatherEl.innerHTML = `<i data-feather="${weather.icon}" width="14" height="14"></i>`;
                    weatherEl.title = `${weather.temperature}°C, ${weather.precipitation}% rain`;
                    content.appendChild(weatherEl);
                }

                return { domNodes: [content] };
            },

            eventSources: [{
                events: async function(fetchInfo, successCallback, failureCallback) {
                    showLoading(true);

                    try {
                        const params = {
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr,
                            type: AppState.filters.type,
                            job_id: AppState.filters.job || AppState.selectedJobId,
                            status: AppState.filters.status,
                            priority: AppState.filters.priority,
                            crew: AppState.filters.crew,
                            trade: AppState.filters.trade,
                            search: AppState.searchQuery
                        };

                        // Clean empty params (but keep valid numeric IDs)
                        Object.keys(params).forEach(key => {
                            if (params[key] === '' || params[key] === null || params[key] === undefined) delete params[key];
                        });

                        const events = await ApiClient.getEvents(params);
                        AppState.eventCache = events;

                        // Update KPIs
                        updateKPIs();

                        // Fetch weather for visible dates (simplified - would use job postcodes in real implementation)
                        fetchWeatherData(fetchInfo.start, fetchInfo.end);

                        successCallback(events);
                    } catch (error) {
                        console.error('Calendar fetch error:', error);
                        Toast.show('Failed to load events', 'error');
                        failureCallback(error);
                    } finally {
                        showLoading(false);
                    }
                }
            }],

            eventDidMount: function(info) {
                const props = info.event.extendedProps || {};
                const priority = props.priority || 'medium';
                const status = props.status || 'scheduled';
                const type = props.type || 'job';

                info.el.setAttribute('data-priority', priority);
                info.el.setAttribute('data-status', status);
                info.el.setAttribute('data-type', type);
                info.el.setAttribute('data-event-id', info.event.id);

                // Visual styling based on priority and status
                if (priority === 'critical') {
                    info.el.style.borderLeft = '3px solid #dc2626';
                } else if (priority === 'high') {
                    info.el.style.borderLeft = '3px solid #f97316';
                }

                if (status === 'completed') {
                    info.el.style.opacity = '0.6';
                    info.el.style.textDecoration = 'line-through';
                } else if (status === 'cancelled') {
                    info.el.style.opacity = '0.4';
                }

                // Add icon based on type
                const typeIcons = {
                    job: 'briefcase',
                    site_visit: 'map-pin',
                    delivery: 'truck',
                    appointment: 'calendar'
                };

                const icon = typeIcons[type] || 'calendar';
                const titleEl = info.el.querySelector('.fc-event-title');
                if (titleEl) {
                    titleEl.innerHTML = `<i data-feather="${icon}" width="12" height="12" style="margin-right:4px;vertical-align:middle;"></i> ${titleEl.textContent}`;
                }

                // Weather warning indicator
                if (props.raw && props.raw.weather_sensitive) {
                    const weatherEl = document.createElement('span');
                    weatherEl.className = 'pi-cal-event-weather-warning';
                    weatherEl.innerHTML = '<i data-feather="cloud-rain" width="12" height="12"></i>';
                    weatherEl.title = 'Weather sensitive task';
                    info.el.appendChild(weatherEl);
                }

                // Refresh feather icons
                if (window.feather) {
                    feather.replace();
                }
            },

            select: function(info) {
                openEventModal({
                    start: info.startStr + (info.allDay ? 'T09:00' : ''),
                    end: info.endStr + (info.allDay ? 'T17:00' : ''),
                    all_day: info.allDay
                });
            },

            dateClick: function(info) {
                openEventModal({
                    start: info.dateStr + 'T09:00',
                    end: info.dateStr + 'T17:00',
                    all_day: false
                });
            },

            eventClick: function(info) {
                const raw = info.event.extendedProps?.raw || {};
                openEventModal(raw);
            },

            eventDrop: async function(info) {
                const payload = {
                    id: parseInt(info.event.id),
                    start: info.event.startStr,
                    end: info.event.endStr || '',
                    all_day: info.event.allDay
                };

                info.el.style.opacity = '0.6';

                try {
                    await ApiClient.updateEvent(payload);
                    Toast.show('Event rescheduled', 'success');
                    calendar.refetchEvents();
                } catch (error) {
                    console.error('Update failed:', error);
                    Toast.show('Failed to reschedule', 'error');
                    info.revert();
                }
            },

            eventResize: async function(info) {
                const payload = {
                    id: parseInt(info.event.id),
                    start: info.event.startStr,
                    end: info.event.endStr || ''
                };

                info.el.style.opacity = '0.6';

                try {
                    await ApiClient.updateEvent(payload);
                    Toast.show('Duration updated', 'success');
                    calendar.refetchEvents();
                } catch (error) {
                    console.error('Resize failed:', error);
                    Toast.show('Failed to update duration', 'error');
                    info.revert();
                }
            }
        });

        calendar.render();

        // Store reference for global access
        window.PICalendar = calendar;
        
        // Add test weather data for demonstration with varied conditions
        setTimeout(() => {
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            AppState.weatherData[todayStr] = {
                code: 0,
                icon: 'sunny',
                svgIcon: getWeatherIconPath('sunny'),
                temperature: 22,
                precipitation: 10,
                windSpeed: 15,
                isBad: false
            };
            
            // Add varied test weather for different days
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowStr = tomorrow.toISOString().split('T')[0];
            AppState.weatherData[tomorrowStr] = {
                code: 61,
                icon: 'heavy_rain',
                svgIcon: getWeatherIconPath('heavy_rain'),
                temperature: 18,
                precipitation: 80,
                windSpeed: 25,
                isBad: true
            };
            
            // Add snowy weather
            const dayAfter = new Date();
            dayAfter.setDate(dayAfter.getDate() + 2);
            const dayAfterStr = dayAfter.toISOString().split('T')[0];
            AppState.weatherData[dayAfterStr] = {
                code: 75,
                icon: 'blizzard',
                svgIcon: getWeatherIconPath('blizzard'),
                temperature: -2,
                precipitation: 90,
                windSpeed: 35,
                isBad: true
            };
            
            // Add cloudy weather
            const dayAfter2 = new Date();
            dayAfter2.setDate(dayAfter2.getDate() + 3);
            const dayAfter2Str = dayAfter2.toISOString().split('T')[0];
            AppState.weatherData[dayAfter2Str] = {
                code: 3,
                icon: 'mostly_sunny',
                svgIcon: getWeatherIconPath('mostly_sunny'),
                temperature: 15,
                precipitation: 20,
                windSpeed: 10,
                isBad: false
            };
            
            // Add thunderstorm weather
            const dayAfter3 = new Date();
            dayAfter3.setDate(dayAfter3.getDate() + 4);
            const dayAfter3Str = dayAfter3.toISOString().split('T')[0];
            AppState.weatherData[dayAfter3Str] = {
                code: 95,
                icon: 'thunderstorms',
                svgIcon: getWeatherIconPath('thunderstorms'),
                temperature: 20,
                precipitation: 95,
                windSpeed: 30,
                isBad: true
            };
            
            console.log('[Calendar] Test weather data added:', AppState.weatherData);
            updateCalendarWeatherDisplay();
        }, 2000);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WEATHER FUNCTIONS
    // ─────────────────────────────────────────────────────────────────────────
    async function fetchWeatherData(start, end) {
        // Enhanced weather data fetching with better error handling
        const latitude = 51.5074; // London coordinates
        const longitude = -0.1278;

        const current = new Date(start);
        const endDate = new Date(end);

        while (current <= endDate) {
            const dateStr = current.toISOString().split('T')[0];
            if (!AppState.weatherData[dateStr]) {
                try {
                    const data = await WeatherService.fetchForecast(latitude, longitude, dateStr);
                    if (data) {
                        AppState.weatherData[dateStr] = WeatherService.formatWeather(data);
                        console.log(`[Calendar] Weather data fetched for ${dateStr}:`, AppState.weatherData[dateStr]);
                    }
                } catch (error) {
                    console.warn(`[Calendar] Failed to fetch weather for ${dateStr}:`, error);
                    // Set default weather data on failure
                    AppState.weatherData[dateStr] = {
                        code: 0,
                        icon: 'sun',
                        temperature: 20,
                        precipitation: 0,
                        windSpeed: 10,
                        isBad: false
                    };
                }
            }
            current.setDate(current.getDate() + 1);
        }

        // Update weather KPI and calendar display
        updateWeatherKPI();
        updateCalendarWeatherDisplay();
    }

    function updateCalendarWeatherDisplay() {
        // Update calendar day cells with weather indicators
        console.log('[Calendar] Updating weather display. Weather data:', AppState.weatherData);
        
        $('.fc-daygrid-day').each(function() {
            const dateStr = $(this).data('date');
            if (dateStr && AppState.weatherData[dateStr]) {
                const weather = AppState.weatherData[dateStr];
                const $dayCell = $(this).find('.fc-daygrid-day-number');
                
                console.log(`[Calendar] Processing weather for ${dateStr}:`, weather);
                
                // Remove existing weather indicator to update with fresh data
                $dayCell.find('.weather-indicator').remove();
                
                // Add detailed SVG weather icon under the day number with proper hover details
                const weatherIcon = getDetailedWeatherIcon(weather.code);
                const weatherType = getWeatherType(weather.code);
                const weatherDescription = getWeatherDescription(weather.code);
                const weatherHtml = `<span class="weather-indicator" data-weather="${weatherType}" title="${weatherDescription}: ${weather.temperature}°C, ${weather.precipitation}% rain, ${weather.windSpeed} km/h wind">${weatherIcon}</span>`;
                console.log(`[Calendar] Adding weather HTML for code ${weather.code}:`, weatherHtml);
                $dayCell.append(weatherHtml);
            }
        });
    }

    function getWeatherDescription(weatherCode) {
        const descriptions = {
            0: 'Clear sky',
            1: 'Mainly clear',
            2: 'Partly cloudy',
            3: 'Overcast',
            45: 'Fog',
            48: 'Depositing rime fog',
            51: 'Light drizzle',
            53: 'Moderate drizzle',
            55: 'Dense drizzle',
            56: 'Light freezing drizzle',
            57: 'Dense freezing drizzle',
            61: 'Slight rain',
            63: 'Moderate rain',
            65: 'Heavy rain',
            66: 'Light freezing rain',
            67: 'Heavy freezing rain',
            71: 'Slight snow',
            73: 'Moderate snow',
            75: 'Heavy snow',
            77: 'Snow grains',
            80: 'Slight rain showers',
            81: 'Moderate rain showers',
            82: 'Violent rain showers',
            85: 'Slight snow showers',
            86: 'Heavy snow showers',
            95: 'Thunderstorm',
            96: 'Thunderstorm with slight hail',
            99: 'Thunderstorm with heavy hail'
        };
        return descriptions[weatherCode] || 'Unknown weather';
    }

    function getWeatherType(weatherCode) {
        // Classify weather type for color coding
        if ([0, 1].includes(weatherCode)) return 'clear';
        if ([2, 3].includes(weatherCode)) return 'cloud';
        if ([45, 48].includes(weatherCode)) return 'cloud';
        if ([51, 53, 55, 61, 63, 65].includes(weatherCode)) return 'rain';
        if ([71, 73, 75].includes(weatherCode)) return 'snow';
        if ([95, 96, 99].includes(weatherCode)) return 'storm';
        return 'clear'; // Default
    }

    function getDetailedWeatherIcon(weatherCode) {
        // Professional weather icons from the weathericons directory
        // Using the exact SVG files for high-quality weather representation
        const weatherIcons = {
            // Clear weather
            0: getWeatherIconPath('sunny'),
            1: getWeatherIconPath('sunny'),
            
            // Partly cloudy
            2: getWeatherIconPath('partly_cloudy'),
            3: getWeatherIconPath('mostly_sunny'),
            
            // Cloudy
            45: getWeatherIconPath('cloudy'),
            48: getWeatherIconPath('cloudy'),
            
            // Clear night
            'clear_night': getWeatherIconPath('clear_night'),
            'mostly_clear_night': getWeatherIconPath('mostly_clear_night'),
            'partly_cloudy_night': getWeatherIconPath('partly_cloudy_night'),
            'mostly_cloudy_night': getWeatherIconPath('mostly_cloudy_night'),
            
            // Drizzle
            51: getWeatherIconPath('drizzle'),
            53: getWeatherIconPath('drizzle'),
            55: getWeatherIconPath('drizzle'),
            
            // Rain
            61: getWeatherIconPath('heavy_rain'),
            63: getWeatherIconPath('heavy_rain'),
            65: getWeatherIconPath('heavy_rain'),
            
            // Snow
            71: getWeatherIconPath('snow_showers'),
            73: getWeatherIconPath('heavy_snow'),
            75: getWeatherIconPath('blizzard'),
            
            // Sleet/Hail
            56: getWeatherIconPath('sleet_hail'),
            57: getWeatherIconPath('sleet_hail'),
            66: getWeatherIconPath('sleet_hail'),
            67: getWeatherIconPath('sleet_hail'),
            
            // Wintry mix
            'wintry_mix': getWeatherIconPath('wintry_mix'),
            
            // Thunderstorms
            95: getWeatherIconPath('thunderstorms'),
            96: getWeatherIconPath('strong_thunderstorms'),
            99: getWeatherIconPath('strong_thunderstorms'),
            
            // Windy
            'windy': getWeatherIconPath('windy'),
            
            // Extreme weather
            'hurricane': getWeatherIconPath('hurricane'),
            'tornado': getWeatherIconPath('tornado'),
            'blowing_snow': getWeatherIconPath('blowing_snow'),
            'flurries': getWeatherIconPath('flurries'),
            'icy': getWeatherIconPath('icy')
        };
        
        return weatherIcons[weatherCode] || getWeatherIconPath('sunny'); // Default to sunny
    }

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
        mostly_sunny: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18.58 4.033a8.246 8.246 0 0 1 10.844 0 8.247 8.247 0 0 0 4.863 2.014 8.246 8.246 0 0 1 7.667 7.668 8.245 8.245 0 0 0 2.015 4.862 8.246 8.246 0 0 1 1.61 8.031 14.018 14.018 0 0 0-10.064-4.604h-.038L35.2 22h-.036c-4.844 0-9.184 2.448-11.666 6.24C17.862 28.292 13 32.75 13 38.618v.04l.012.463.002.04c.05.973.239 1.902.538 2.776a8.243 8.243 0 0 1-7.502-7.653 8.246 8.246 0 0 0-2.015-4.862 8.247 8.247 0 0 1 0-10.845 8.245 8.245 0 0 0 2.015-4.862 8.246 8.246 0 0 1 7.667-7.668 8.247 8.247 0 0 0 4.863-2.014z" fill="#FCBD00"/><path d="M18.58 4.033a8.246 8.246 0 0 1 10.844 0 8.249 8.249 0 0 0 4.863 2.015 8.246 8.246 0 0 1 7.667 7.667 8.248 8.248 0 0 0 2.015 4.863 8.244 8.244 0 0 1 1.608 8.03 13.84 13.84 0 0 0-2.59-2.24 5.236 5.236 0 0 0-1.28-3.817 11.247 11.247 0 0 1-2.746-6.632 5.246 5.246 0 0 0-4.878-4.878 11.248 11.248 0 0 1-6.632-2.747 5.245 5.245 0 0 0-6.898 0 11.248 11.248 0 0 1-6.632 2.747 5.246 5.246 0 0 0-4.878 4.878 11.248 11.248 0 0 1-2.747 6.632 5.246 5.246 0 0 0 0 6.898 11.248 11.248 0 0 1 2.747 6.632 5.245 5.245 0 0 0 3.96 4.732l.009.308.002.04c.05.972.238 1.902.537 2.776a8.244 8.244 0 0 1-7.467-7.269l-.034-.383a8.248 8.248 0 0 0-2.015-4.863 8.246 8.246 0 0 1-.246-10.548l.246-.296a8.247 8.247 0 0 0 1.984-4.527l.03-.336a8.246 8.246 0 0 1 7.285-7.633l.383-.034A8.248 8.248 0 0 0 18.32 4.25l.259-.217z" fill="#DA4F00"/><path d="M35.404 26.502c5.042.124 9.096 4.1 9.096 8.997l-.005.283c-.092 2.895-1.605 5.446-3.886 7.027a9.464 9.464 0 0 1-3.311 1.45c-2.235.276-4.45.24-6.862.239h-6.838c-3.252 0-5.918-2.46-6.09-5.557l-.008-.323c0-3.25 2.733-5.88 6.098-5.88.44 0 .87.045 1.283.131l1.191.247.488-1.114c1.414-3.234 4.738-5.5 8.597-5.502l.247.002z" stroke="#70757A" stroke-width="3"/></svg>',
        mostly_cloudy_night: '<svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.689 2c-2.077 2.482-3.319 5.631-3.319 9.058 0 4.015 1.705 7.649 4.46 10.275a16.159 16.159 0 0 0-2.875 3.394l-.332-.003c-2.825 0-5.505.92-7.664 2.5C3.913 24.573 2 20.749 2 16.497 2 8.637 8.534 2.238 16.689 2z" fill="#3271EA"/><path d="M29.589 21.503c6.62.16 11.91 5.257 11.911 11.496l-.007.378c-.007.203-.02.41-.04.62l-.083.91.773.491c1.456.926 2.357 2.439 2.357 4.102 0 2.659-2.356 5-5.5 5H19.5v-.003h-4.877c-4.22 0-7.675-3.026-8.083-6.866l-.03-.374-.01-.397c0-4.07 3.4-7.421 7.704-7.627l.419-.01c.587 0 1.161.06 1.714.172l1.177.238.49-1.096c1.848-4.128 6.198-7.034 11.264-7.037l.32.003z" stroke="#70757A" stroke-width="3"/></svg>'
    };

    function getWeatherIconPath(iconName) {
        console.log(`[Weather] Loading embedded icon: ${iconName}`);
        const svgIcon = WeatherIcons[iconName] || WeatherIcons.sunny;
        return `<span class="weather-svg-icon" style="display: inline-block; width: 16px; height: 16px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">${svgIcon}</span>`;
    }

    function updateWeatherKPI() {
        const badWeatherCount = Object.values(AppState.weatherData).filter(w => w && w.isBad).length;
        const $kpi = $('#pi-cal-weather-kpi');
        const $badge = $('#pi-cal-kpi-weather');

        if (badWeatherCount > 0) {
            $kpi.show();
            $badge.text(badWeatherCount);
        } else {
            $kpi.hide();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // KPI & STATS
    // ─────────────────────────────────────────────────────────────────────────
    async function updateKPIs() {
        try {
            const stats = await ApiClient.getStats();

            animateCounter('#pi-cal-kpi-total', stats.total);
            animateCounter('#pi-cal-kpi-upcoming', stats.upcoming);
            animateCounter('#pi-cal-kpi-visits', stats.site_visits);
            animateCounter('#pi-cal-kpi-crew', stats.crew_on_site);
            animateCounter('#pi-cal-kpi-deliveries', stats.pending_deliveries);

            // Update quick link badges with correct stats
            $('#pi-cal-badge-visits').text(stats.site_visits); // Today's site visits
            $('#pi-cal-badge-deliveries').text(stats.pending_deliveries); // Future deliveries

            // Check for conflicts
            const conflicts = await checkConflicts();
            $('#pi-cal-badge-conflicts').text(conflicts.length).toggle(conflicts.length > 0);

        } catch (error) {
            console.warn('Failed to update KPIs:', error);
        }
    }

    function animateCounter(selector, target) {
        const $el = $(selector);
        if (!$el.length) return;

        const current = parseInt($el.text()) || 0;
        if (current === target) return;

        const duration = 500;
        const start = performance.now();

        function step(timestamp) {
            const progress = Math.min((timestamp - start) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            $el.text(Math.round(current + (target - current) * ease));

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        requestAnimationFrame(step);
    }

    async function checkConflicts() {
        const events = AppState.eventCache;
        const conflicts = [];

        for (let i = 0; i < events.length; i++) {
            for (let j = i + 1; j < events.length; j++) {
                const ev1 = events[i];
                const ev2 = events[j];

                const crew1 = ev1.extendedProps?.raw?.crew || [];
                const crew2 = ev2.extendedProps?.raw?.crew || [];

                if (!crew1.length || !crew2.length) continue;

                const overlap = crew1.filter(id => crew2.includes(id));
                if (!overlap.length) continue;

                // Check time overlap
                const start1 = new Date(ev1.start);
                const end1 = ev1.end ? new Date(ev1.end) : start1;
                const start2 = new Date(ev2.start);
                const end2 = ev2.end ? new Date(ev2.end) : start2;

                if (start1 <= end2 && end1 >= start2) {
                    conflicts.push({ ev1, ev2, crew: overlap });
                }
            }
        }

        return conflicts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EVENT MODAL
    // ─────────────────────────────────────────────────────────────────────────
    function openEventModal(raw = {}) {
        const isEdit = !!raw.id;

        // Reset form
        $('#pi-cal-event-form')[0].reset();
        AppState.checklist = raw.checklist || [];
        AppState.attachments = raw.attachments || [];

        // Set values
        $('#pi-cal-event-id').val(raw.id || '');
        $('#pi-cal-event-type').val(raw.type || 'job');
        $('#pi-cal-event-job').val(raw.job_id || '');
        $('#pi-cal-event-title').val(raw.title || '');
        $('#pi-cal-event-status').val(raw.status || 'scheduled');
        $('#pi-cal-event-priority').val(raw.priority || 'medium');
        $('#pi-cal-event-trade').val(raw.trade || '');
        $('#pi-cal-event-supplier').val(raw.supplier_name || '');
        $('#pi-cal-event-po').val(raw.po_number || '');
        $('#pi-cal-event-weather-sensitive').prop('checked', raw.weather_sensitive || false);
        $('#pi-cal-event-notes').val(raw.notes || '');

        // Dates
        const start = raw.start || '';
        const end = raw.end || '';

        if (raw.all_day) {
            $('#pi-cal-event-start').attr('type', 'date').val(start.substring(0, 10));
            $('#pi-cal-event-end').attr('type', 'date').val(end.substring(0, 10));
            $('#pi-cal-event-all-day').prop('checked', true);
        } else {
            $('#pi-cal-event-start').attr('type', 'datetime-local').val(start.substring(0, 16));
            $('#pi-cal-event-end').attr('type', 'datetime-local').val(end.substring(0, 16));
            $('#pi-cal-event-all-day').prop('checked', false);
        }

        // Crew
        $('#pi-cal-event-crew').val(raw.crew || []);

        // Update UI
        $('#pi-cal-modal-title').text(isEdit ? 'Edit Event' : 'New Event');
        $('#pi-cal-event-delete').toggle(isEdit);
        $('#pi-cal-event-duplicate').toggle(isEdit);

        // Render checklist
        renderChecklist();

        // Load linked expenses if editing with job
        if (isEdit && raw.job_id) {
            loadLinkedExpenses(raw.job_id, raw.start);
        } else {
            $('#pi-cal-linked-expenses').html(`
                <div class="pi-cal-empty-state--small">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    <p>Assign a job and save the event to see linked expenses</p>
                </div>
            `);
        }

        // Reset tabs
        $('.pi-cal-tab-btn').removeClass('active').first().addClass('active');
        $('.pi-cal-tab-content').removeClass('active').first().addClass('active');

        // Show modal
        $('#pi-cal-event-modal').addClass('pi-cal-modal-open');
        $('body').addClass('pi-cal-modal-scroll-lock');

        // Focus title
        setTimeout(() => $('#pi-cal-event-title').focus().select(), 200);

        // Update icon based on type
        updateModalIcon(raw.type || 'job');
    }

    function closeEventModal() {
        $('#pi-cal-event-modal').removeClass('pi-cal-modal-open');
        $('body').removeClass('pi-cal-modal-scroll-lock');
        AppState.pendingEventData = null;
        AppState.conflicts = [];
    }

    function updateModalIcon(type) {
        const icons = {
            job: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            site_visit: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
            delivery: '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
            appointment: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'
        };
        $('#pi-cal-modal-icon').html(`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${icons[type] || icons.job}</svg>`);
    }

    async function loadLinkedExpenses(jobId, date) {
        try {
            const expenses = await ApiClient.getLinkedExpenses(jobId, date);
            const $container = $('#pi-cal-linked-expenses');

            if (!expenses || !expenses.length) {
                $container.html(`
                    <div class="pi-cal-empty-state--small">
                        <p>No linked expenses for this job/date</p>
                    </div>
                `);
                return;
            }

            $container.html(expenses.map(exp => `
                <div class="pi-cal-linked-expense-item">
                    <div class="pi-cal-expense-info">
                        <span class="pi-cal-expense-supplier">${exp.supplier_name || 'Unknown'}</span>
                        <span class="pi-cal-expense-category">${exp.category || 'Other'}</span>
                    </div>
                    <span class="pi-cal-expense-amount">£${parseFloat(exp.amount || 0).toFixed(2)}</span>
                </div>
            `).join(''));
        } catch (error) {
            console.warn('Failed to load linked expenses:', error);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CHECKLIST FUNCTIONS
    // ─────────────────────────────────────────────────────────────────────────
    function renderChecklist() {
        const $container = $('#pi-cal-checklist-container');

        if (!AppState.checklist.length) {
            $container.html(`
                <div class="pi-cal-checklist-empty">
                    <p>No checklist items yet. Add tasks below.</p>
                </div>
            `);
            return;
        }

        $container.html(AppState.checklist.map((item, index) => `
            <div class="pi-cal-checklist-item ${item.done ? 'pi-cal-checklist-item--done' : ''}">
                <label class="pi-cal-checklist-checkbox">
                    <input type="checkbox" ${item.done ? 'checked' : ''} data-index="${index}">
                    <span class="pi-cal-checkbox-custom"></span>
                </label>
                <span class="pi-cal-checklist-text">${escapeHtml(item.text)}</span>
                <button type="button" class="pi-cal-checklist-delete" data-index="${index}">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        `).join(''));
    }

    function addChecklistItem(text) {
        if (!text.trim()) return;
        AppState.checklist.push({ text: text.trim(), done: false });
        renderChecklist();
        $('#pi-cal-checklist-input').val('').focus();
    }

    function toggleChecklistItem(index) {
        if (AppState.checklist[index]) {
            AppState.checklist[index].done = !AppState.checklist[index].done;
            renderChecklist();
        }
    }

    function deleteChecklistItem(index) {
        AppState.checklist.splice(index, 1);
        renderChecklist();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORM SUBMISSION
    // ─────────────────────────────────────────────────────────────────────────
    async function saveEvent() {
        const id = $('#pi-cal-event-id').val();
        const isAllDay = $('#pi-cal-event-all-day').is(':checked');

        const data = {
            id: id ? parseInt(id) : undefined,
            type: $('#pi-cal-event-type').val(),
            job_id: parseInt($('#pi-cal-event-job').val()) || 0,
            title: $('#pi-cal-event-title').val().trim(),
            start: isAllDay ? $('#pi-cal-event-start').val() : $('#pi-cal-event-start').val(),
            end: isAllDay ? $('#pi-cal-event-end').val() : $('#pi-cal-event-end').val(),
            all_day: isAllDay,
            crew: ($('#pi-cal-event-crew').val() || []).map(v => parseInt(v)),
            notes: $('#pi-cal-event-notes').val(),
            status: $('#pi-cal-event-status').val(),
            priority: $('#pi-cal-event-priority').val(),
            trade: $('#pi-cal-event-trade').val(),
            supplier_name: $('#pi-cal-event-supplier').val(),
            po_number: $('#pi-cal-event-po').val(),
            weather_sensitive: $('#pi-cal-event-weather-sensitive').is(':checked'),
            checklist: AppState.checklist,
            attachments: AppState.attachments,
            ignore_conflicts: $('#pi-cal-event-ignore-conflicts').is(':checked')
        };

        // Validation
        if (!data.title) {
            $('#pi-cal-error-title').text('Title is required').show();
            $('#pi-cal-event-title').addClass('pi-cal-input-error');
            return;
        }

        if (!data.start) {
            $('#pi-cal-error-start').text('Start date is required').show();
            $('#pi-cal-event-start').addClass('pi-cal-input-error');
            return;
        }

        // Check conflicts
        if (!data.ignore_conflicts && data.crew.length > 0) {
            const conflicts = await checkCrewConflicts(data.crew, data.start, data.end, data.id);
            if (conflicts.length > 0) {
                showConflictModal(conflicts, data);
                return;
            }
        }

        try {
            $('#pi-cal-event-save').prop('disabled', true).html('<span class="pi-cal-spinner"></span>Saving...');

            const result = id ? await ApiClient.updateEvent(data) : await ApiClient.createEvent(data);

            if (result.requires_confirmation) {
                showConflictModal(result.conflicts, data);
                $('#pi-cal-event-save').prop('disabled', false).html(`
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Event
                `);
                return;
            }

            // Save template if requested
            if ($('#pi-cal-save-template').is(':checked')) {
                const templateName = $('#pi-cal-template-name').val().trim() || data.title + ' Template';
                await ApiClient.saveTemplate({
                    name: templateName,
                    type: data.type,
                    title: data.title,
                    duration_hours: calculateDuration(data.start, data.end),
                    all_day: data.all_day,
                    notes: data.notes,
                    priority: data.priority,
                    trade: data.trade,
                    weather_sensitive: data.weather_sensitive,
                    checklist: data.checklist
                });
                Toast.show('Template saved', 'success');
            }

            closeEventModal();
            calendar.refetchEvents();
            Toast.show(id ? 'Event updated' : 'Event created', 'success');

        } catch (error) {
            console.error('Save failed:', error);
            Toast.show('Failed to save event', 'error');
        } finally {
            $('#pi-cal-event-save').prop('disabled', false).html(`
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Event
            `);
        }
    }

    async function checkCrewConflicts(crewIds, start, end, excludeId) {
        // Simplified - would use API in production
        const events = AppState.eventCache;
        const conflicts = [];

        for (const ev of events) {
            if (excludeId && ev.id == excludeId) continue;

            const evCrew = ev.extendedProps?.raw?.crew || [];
            if (!evCrew.length) continue;

            const overlap = crewIds.filter(id => evCrew.includes(id));
            if (!overlap.length) continue;

            const start1 = new Date(start);
            const end1 = end ? new Date(end) : start1;
            const start2 = new Date(ev.start);
            const end2 = ev.end ? new Date(ev.end) : start2;

            if (start1 <= end2 && end1 >= start2) {
                conflicts.push({
                    event_title: ev.title,
                    start: ev.start,
                    crew: overlap
                });
            }
        }

        return conflicts;
    }

    function showConflictModal(conflicts, eventData) {
        AppState.pendingEventData = eventData;

        $('#pi-cal-conflict-details').html(conflicts.map(c => `
            <div class="pi-cal-conflict-item">
                <strong>${escapeHtml(c.event_title)}</strong>
                <span>${formatDateTime(c.start)}</span>
            </div>
        `).join(''));

        $('#pi-cal-conflict-modal').addClass('pi-cal-modal-open');
    }

    function calculateDuration(start, end) {
        if (!end) return 8;
        const hours = (new Date(end) - new Date(start)) / (1000 * 60 * 60);
        return Math.max(1, Math.round(hours));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VIEW TOGGLE
    // ─────────────────────────────────────────────────────────────────────────
    function toggleView(viewName) {
        if (viewName === 'gantt') {
            toggleGanttView();
            return;
        }

        AppState.currentView = viewName;
        calendar.changeView(viewName);

        $('.pi-cal-view-btn').removeClass('active');
        $(`[data-view="${viewName}"]`).addClass('active');

        AppState.saveToStorage();
    }

    function toggleGanttView() {
        // Enhanced timeline view with better formatting
        AppState.currentView = 'listWeek';
        calendar.changeView('listWeek');

        $('.pi-cal-view-btn').removeClass('active');
        $('#pi-cal-gantt-btn').addClass('active');

        // Add timeline-specific styling
        setTimeout(() => {
            enhanceTimelineView();
        }, 100);

        Toast.show('Timeline view activated', 'success');
    }

    function enhanceTimelineView() {
        // Add timeline-specific classes and styling
        $('.fc-list-table').addClass('pi-cal-timeline-view');
        
        // Enhance event display in timeline
        $('.fc-list-event').each(function() {
            const $event = $(this);
            const title = $event.find('.fc-list-event-title').text();
            const time = $event.find('.fc-list-event-time').text();
            
            // Add type indicators
            if (title.toLowerCase().includes('visit')) {
                $event.addClass('timeline-visit');
            } else if (title.toLowerCase().includes('delivery')) {
                $event.addClass('timeline-delivery');
            } else if (title.toLowerCase().includes('meeting') || title.toLowerCase().includes('appointment')) {
                $event.addClass('timeline-appointment');
            } else {
                $event.addClass('timeline-job');
            }
        });

        // Add timeline header
        if (!$('.pi-cal-timeline-header').length) {
            $('.fc-list-table').before(`
                <div class="pi-cal-timeline-header">
                    <div class="timeline-header-info">
                        <h4>Project Timeline</h4>
                        <p>Chronological view of all scheduled activities</p>
                    </div>
                    <div class="timeline-legend">
                        <span class="timeline-legend-item timeline-job">Jobs</span>
                        <span class="timeline-legend-item timeline-visit">Visits</span>
                        <span class="timeline-legend-item timeline-delivery">Deliveries</span>
                        <span class="timeline-legend-item timeline-appointment">Appointments</span>
                    </div>
                </div>
            `);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOB-CENTRIC MODE
    // ─────────────────────────────────────────────────────────────────────────
    function toggleJobCentricMode() {
        AppState.jobCentricMode = !AppState.jobCentricMode;
        const $sidebar = $('#pi-cal-job-sidebar');
        const $main = $('.pi-cal-calendar-container');

        if (AppState.jobCentricMode) {
            $sidebar.show();
            $main.addClass('pi-cal-with-sidebar');
            $('#pi-cal-job-mode-toggle').addClass('active');
        } else {
            $sidebar.hide();
            $main.removeClass('pi-cal-with-sidebar');
            $('#pi-cal-job-mode-toggle').removeClass('active');
            AppState.selectedJobId = null;
            // Clear the job filter when exiting Job-Centric mode
            AppState.setFilter('job', '');
            calendar.refetchEvents();
        }

        AppState.saveToStorage();
    }

    function selectJob(jobId) {
        AppState.selectedJobId = jobId;
        
        // Also update the job filter to ensure proper filtering
        AppState.setFilter('job', jobId);

        // Filter calendar to show only this job's events
        calendar.refetchEvents();

        // Update mini Gantt
        updateMiniGantt(jobId);
    }

    function updateMiniGantt(jobId) {
        const $container = $('#pi-cal-mini-gantt');
        const events = AppState.eventCache.filter(e => e.extendedProps?.job_id == jobId);

        if (!events.length) {
            $container.html(`
                <div class="pi-cal-empty-state">
                    <p>No events for this job yet</p>
                </div>
            `);
            return;
        }

        // Sort by date
        events.sort((a, b) => new Date(a.start) - new Date(b.start));

        $container.html(events.map(ev => {
            const raw = ev.extendedProps?.raw || {};
            const statusClass = raw.status === 'completed' ? 'pi-cal-gantt-item--done' : '';
            const typeIcon = {
                job: 'briefcase',
                site_visit: 'map-pin',
                delivery: 'truck',
                appointment: 'calendar'
            }[raw.type] || 'calendar';

            return `
                <div class="pi-cal-gantt-item ${statusClass}">
                    <div class="pi-cal-gantt-icon">
                        <i data-feather="${typeIcon}" width="14" height="14"></i>
                    </div>
                    <div class="pi-cal-gantt-info">
                        <span class="pi-cal-gantt-title">${escapeHtml(raw.title)}</span>
                        <span class="pi-cal-gantt-date">${formatDateShort(raw.start)}</span>
                    </div>
                    ${raw.status === 'completed' ? '<span class="pi-cal-gantt-check">✓</span>' : ''}
                </div>
            `;
        }).join(''));

        if (window.feather) feather.replace();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOOKAHEAD
    // ─────────────────────────────────────────────────────────────────────────
    async function showLookahead() {
        const now = new Date();
        const end = new Date();
        end.setDate(end.getDate() + 21);

        // Fetch fresh events for the lookahead period
        try {
            showLoading(true);
            const events = await ApiClient.getEvents({
                start: now.toISOString().split('T')[0],
                end: end.toISOString().split('T')[0]
            });

            // Filter to only future/upcoming events
            const upcomingEvents = events.filter(e => {
                const start = new Date(e.start);
                return start >= now && start <= end;
            }).sort((a, b) => new Date(a.start) - new Date(b.start));

            const $content = $('#pi-cal-lookahead-content');

            if (!upcomingEvents.length) {
                $content.html(`
                    <div class="pi-cal-empty-state">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <p>No events scheduled for the next 3 weeks</p>
                        <span>All caught up! Time for a cuppa ☕</span>
                    </div>
                `);
            } else {
                // Group by week
                const byWeek = {};
                upcomingEvents.forEach(ev => {
                    const start = new Date(ev.start);
                    const weekStart = new Date(start);
                    weekStart.setDate(weekStart.getDate() - weekStart.getDay());
                    const weekKey = weekStart.toISOString().split('T')[0];

                    if (!byWeek[weekKey]) byWeek[weekKey] = [];
                    byWeek[weekKey].push(ev);
                });

                $content.html(Object.entries(byWeek).map(([weekKey, weekEvents]) => `
                    <div class="pi-cal-lookahead-week">
                        <h4>Week of ${formatDateLong(weekKey)}</h4>
                        ${weekEvents.map(ev => {
                            const raw = ev.raw || ev.extendedProps?.raw || ev;
                            const priorityClass = raw.priority === 'critical' ? 'pi-cal-lookahead-item--critical' :
                                                raw.priority === 'high' ? 'pi-cal-lookahead-item--high' : '';
                            const typeIcons = {
                                job: 'briefcase',
                                site_visit: 'map-pin',
                                delivery: 'truck',
                                appointment: 'calendar'
                            };
                            const icon = typeIcons[raw.type] || 'calendar';

                            return `
                                <div class="pi-cal-lookahead-item ${priorityClass}" data-event-id="${ev.id}" style="cursor:pointer;">
                                    <span class="pi-cal-lookahead-icon"><i data-feather="${icon}" width="14" height="14"></i></span>
                                    <span class="pi-cal-lookahead-date">${formatDateShort(raw.start)}</span>
                                    <span class="pi-cal-lookahead-title">${escapeHtml(raw.title)}</span>
                                    ${raw.weather_sensitive ? '<span class="pi-cal-lookahead-weather" title="Weather sensitive">☔</span>' : ''}
                                    <span class="pi-cal-lookahead-type">${raw.type || 'event'}</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `).join(''));

                // Refresh feather icons
                if (window.feather) feather.replace();

                // Make items clickable
                $('.pi-cal-lookahead-item').on('click', function() {
                    const eventId = $(this).data('event-id');
                    const event = upcomingEvents.find(e => e.id == eventId);
                    if (event) {
                        $('#pi-cal-lookahead-modal').removeClass('pi-cal-modal-open');
                        const raw = event.raw || event.extendedProps?.raw || event;
                        openEventModal(raw);
                    }
                });
            }

            $('#pi-cal-lookahead-modal').addClass('pi-cal-modal-open');
        } catch (error) {
            console.error('Failed to load lookahead:', error);
            Toast.show('Failed to load lookahead', 'error');
        } finally {
            showLoading(false);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEMPLATES
    // ─────────────────────────────────────────────────────────────────────────
    async function loadTemplates() {
        try {
            AppState.templates = await ApiClient.getTemplates();
            renderTemplates();
        } catch (error) {
            console.warn('Failed to load templates:', error);
        }
    }

    function renderTemplates() {
        const $list = $('#pi-cal-templates-list');
        const $empty = $('#pi-cal-templates-empty');

        if (!AppState.templates.length) {
            $list.empty();
            $empty.show();
            return;
        }

        $empty.hide();

        const typeLabels = {
            job: 'Job',
            site_visit: 'Site Visit',
            delivery: 'Delivery',
            appointment: 'Appointment'
        };

        $list.html(AppState.templates.map(tpl => `
            <div class="pi-cal-template-item">
                <div class="pi-cal-template-info">
                    <div class="pi-cal-template-name">${escapeHtml(tpl.name)}</div>
                    <div class="pi-cal-template-meta">
                        <span class="pi-cal-template-type">${typeLabels[tpl.type] || tpl.type}</span>
                        <span class="pi-cal-priority-badge pi-cal-priority-badge--${tpl.priority}">${tpl.priority}</span>
                        <span class="pi-cal-template-duration">${tpl.duration_hours}h</span>
                    </div>
                </div>
                <div class="pi-cal-template-actions">
                    <button type="button" class="pi-cal-btn pi-cal-btn--sm pi-cal-btn--secondary" data-action="use" data-id="${tpl.id}">Use</button>
                    ${tpl.id > 5 ? `<button type="button" class="pi-cal-btn pi-cal-btn--sm pi-cal-btn--ghost" data-action="delete" data-id="${tpl.id}">Delete</button>` : ''}
                </div>
            </div>
        `).join(''));
    }

    function useTemplate(templateId) {
        const tpl = AppState.templates.find(t => t.id === templateId);
        if (!tpl) return;

        $('#pi-cal-templates-modal').removeClass('pi-cal-modal-open');

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;

        openEventModal({
            type: tpl.type || 'job',
            title: tpl.title || '',
            start: `${dateStr}T09:00`,
            end: `${dateStr}T${String(9 + (tpl.duration_hours || 8)).padStart(2, '0')}:00`,
            all_day: tpl.all_day || false,
            notes: tpl.notes || '',
            priority: tpl.priority || 'medium',
            trade: tpl.trade || '',
            weather_sensitive: tpl.weather_sensitive || false,
            checklist: tpl.checklist || []
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTEXT MENU
    // ─────────────────────────────────────────────────────────────────────────
    function showContextMenu(event, eventId) {
        AppState.contextEventId = eventId;
        const $menu = $('#pi-cal-context-menu');

        $menu.css({
            display: 'block',
            left: event.pageX + 'px',
            top: event.pageY + 'px'
        });
    }

    async function handleContextAction(action) {
        const eventId = AppState.contextEventId;
        if (!eventId) return;

        const fcEvent = calendar.getEventById(eventId);
        if (!fcEvent) return;

        const raw = fcEvent.extendedProps?.raw || {};

        switch (action) {
            case 'edit':
                openEventModal(raw);
                break;

            case 'duplicate':
                try {
                    await ApiClient.duplicateEvent(eventId);
                    calendar.refetchEvents();
                    Toast.show('Event duplicated', 'success');
                } catch (error) {
                    Toast.show('Failed to duplicate', 'error');
                }
                break;

            case 'mark-complete':
                try {
                    const newStatus = raw.status === 'completed' ? 'scheduled' : 'completed';
                    await ApiClient.updateEvent({ id: parseInt(eventId), status: newStatus });
                    calendar.refetchEvents();
                    Toast.show(newStatus === 'completed' ? 'Marked complete' : 'Marked scheduled', 'success');
                } catch (error) {
                    Toast.show('Failed to update status', 'error');
                }
                break;

            case 'log-expense':
                // Would open expenses page or modal
                Toast.show('Opening expense form...', 'info');
                break;

            case 'mark-delivered':
                if (raw.type === 'delivery') {
                    try {
                        await ApiClient.updateEvent({ id: parseInt(eventId), status: 'completed' });
                        calendar.refetchEvents();
                        Toast.show('Marked as delivered', 'success');
                    } catch (error) {
                        Toast.show('Failed to update', 'error');
                    }
                }
                break;

            case 'add-photo':
                openEventModal(raw);
                $('.pi-cal-tab-btn[data-tab="attachments"]').click();
                break;

            case 'delete':
                if (confirm('Delete this event? This cannot be undone.')) {
                    try {
                        await ApiClient.deleteEvent(eventId);
                        calendar.refetchEvents();
                        Toast.show('Event deleted', 'success');
                    } catch (error) {
                        Toast.show('Failed to delete', 'error');
                    }
                }
                break;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // KEYBOARD SHORTCUTS
    // ─────────────────────────────────────────────────────────────────────────
    function toggleShortcuts() {
        AppState.shortcutsOpen = !AppState.shortcutsOpen;
        $('#pi-cal-shortcuts-panel').toggle(AppState.shortcutsOpen);
    }

    $(document).on('keydown', function(e) {
        const isModalOpen = $('.pi-cal-modal-open').length > 0;
        const isFocusInInput = $(document.activeElement).is('input, textarea, select');

        // Escape key
        if (e.key === 'Escape') {
            if ($('#pi-cal-conflict-modal').hasClass('pi-cal-modal-open')) {
                $('#pi-cal-conflict-modal').removeClass('pi-cal-modal-open');
                return;
            }
            if (isModalOpen) {
                closeEventModal();
                $('#pi-cal-templates-modal, #pi-cal-jump-modal, #pi-cal-lookahead-modal').removeClass('pi-cal-modal-open');
                return;
            }
            if (AppState.shortcutsOpen) {
                toggleShortcuts();
                return;
            }
        }

        if (isModalOpen || isFocusInInput) return;

        switch (e.key.toLowerCase()) {
            case 'n':
                e.preventDefault();
                openEventModal();
                break;

            case '/':
                e.preventDefault();
                // On mobile, expand search box first, then focus
                if (window.innerWidth <= 768) {
                    const $searchBox = $('.pi-cal-search-box');
                    if (!$searchBox.hasClass('pi-cal-search-expanded')) {
                        $searchBox.addClass('pi-cal-search-expanded');
                        setTimeout(() => {
                            $('#pi-cal-search-input').focus();
                        }, 150);
                    } else {
                        $('#pi-cal-search-input').focus();
                    }
                } else {
                    $('#pi-cal-search-input').focus();
                }
                break;

            case 't':
                e.preventDefault();
                calendar.today();
                Toast.show('Jumped to today', 'info');
                break;

            case 'j':
                e.preventDefault();
                $('#pi-cal-jump-modal').addClass('pi-cal-modal-open');
                break;

            case 'l':
                e.preventDefault();
                toggleJobCentricMode();
                break;

            case 'g':
                e.preventDefault();
                toggleGanttView();
                break;

            case 'arrowleft':
                e.preventDefault();
                calendar.prev();
                break;

            case 'arrowright':
                e.preventDefault();
                calendar.next();
                break;

            case '?':
                e.preventDefault();
                toggleShortcuts();
                break;
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // UTILITY FUNCTIONS
    // ─────────────────────────────────────────────────────────────────────────
    function showLoading(show) {
        $('#pi-cal-loading').toggle(show);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDateTime(isoString) {
        if (!isoString) return '';
        const date = new Date(isoString);
        return date.toLocaleString('en-GB', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatDateShort(isoString) {
        if (!isoString) return '';
        const date = new Date(isoString);
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short'
        });
    }

    function formatDateLong(isoString) {
        if (!isoString) return '';
        const date = new Date(isoString);
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EVENT BINDINGS
    // ─────────────────────────────────────────────────────────────────────────
    function bindEvents() {
        // Search - Enhanced with responsive expandable functionality
        const $searchBox = $('.pi-cal-search-box');
        const $searchInput = $('#pi-cal-search-input');
        const $searchClear = $('#pi-cal-search-clear');
        const $searchIcon = $('.pi-cal-search-icon');
        
        // Handle search box click on mobile to expand
        $searchBox.on('click', function(e) {
            // Only handle click if it's not already expanded and target is not the input
            if (window.innerWidth <= 768 && !$(this).hasClass('pi-cal-search-expanded') && e.target !== $searchInput[0]) {
                e.preventDefault();
                $(this).addClass('pi-cal-search-expanded');
                // Focus input after expansion animation
                setTimeout(() => {
                    $searchInput.focus();
                }, 150);
            }
        });

        // Search input handler
        $searchInput.on('input', function() {
            AppState.searchQuery = $(this).val();
            $searchClear.toggle(!!$(this).val());
            calendar.refetchEvents();
        });

        // Search clear handler
        $searchClear.on('click', function(e) {
            e.stopPropagation();
            $searchInput.val('');
            AppState.searchQuery = '';
            $(this).hide();
            calendar.refetchEvents();
            
            // On mobile, collapse search box when cleared
            if (window.innerWidth <= 768) {
                $searchBox.removeClass('pi-cal-search-expanded');
            }
        });

        // Handle escape key to collapse search on mobile
        $searchInput.on('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth <= 768) {
                $searchBox.removeClass('pi-cal-search-expanded');
                $(this).blur();
            }
        });

        // Handle click outside to collapse search on mobile
        $(document).on('click', function(e) {
            if (window.innerWidth <= 768 && 
                $searchBox.hasClass('pi-cal-search-expanded') && 
                !$searchBox.is(e.target) && 
                $searchBox.has(e.target).length === 0) {
                $searchBox.removeClass('pi-cal-search-expanded');
            }
        });

        // Handle window resize to reset search state
        $(window).on('resize', function() {
            if (window.innerWidth > 768) {
                $searchBox.removeClass('pi-cal-search-expanded');
            }
        });

        // Filters
        $('#pi-cal-filter-type, #pi-cal-filter-job, #pi-cal-filter-status, #pi-cal-filter-priority, #pi-cal-filter-crew, #pi-cal-filter-trade')
            .on('change', function() {
                const $el = $(this);
                AppState.setFilter($el.attr('id').replace('pi-cal-filter-', ''), $el.val());
                calendar.refetchEvents();
            });

        $('#pi-cal-clear-filters').on('click', function() {
            $('.pi-cal-filter-bar select').val('');
            AppState.filters = { type: '', job: '', status: '', priority: '', crew: '', trade: '' };
            AppState.saveToStorage();
            calendar.refetchEvents();
        });

        // View toggle
        $('.pi-cal-view-btn').on('click', function() {
            toggleView($(this).data('view'));
        });

        // New event
        $('#pi-cal-add-btn').on('click', () => openEventModal());

        // Jump to date
        $('#pi-cal-jump-btn').on('click', function() {
            $('#pi-cal-jump-modal').addClass('pi-cal-modal-open');
        });

        $('#pi-cal-jump-go').on('click', function() {
            const date = $('#pi-cal-jump-input').val();
            if (date) {
                calendar.gotoDate(date);
                $('#pi-cal-jump-modal').removeClass('pi-cal-modal-open');
                Toast.show(`Jumped to ${formatDateLong(date)}`, 'info');
            }
        });

        $('#pi-cal-jump-cancel, #pi-cal-jump-modal-close').on('click', function() {
            $('#pi-cal-jump-modal').removeClass('pi-cal-modal-open');
        });

        // Templates
        $('#pi-cal-templates-btn').on('click', function() {
            loadTemplates();
            $('#pi-cal-templates-modal').addClass('pi-cal-modal-open');
        });

        $('#pi-cal-templates-modal-close, #pi-cal-templates-modal-cancel').on('click', function() {
            $('#pi-cal-templates-modal').removeClass('pi-cal-modal-open');
        });

        $(document).on('click', '[data-action="use"]', function() {
            useTemplate(parseInt($(this).data('id')));
        });

        $(document).on('click', '[data-action="delete"]', async function() {
            const id = parseInt($(this).data('id'));
            if (confirm('Delete this template?')) {
                try {
                    await ApiClient.deleteTemplate(id);
                    loadTemplates();
                    Toast.show('Template deleted', 'success');
                } catch (error) {
                    Toast.show('Failed to delete', 'error');
                }
            }
        });

        // Lookahead
        $('#pi-cal-lookahead-btn').on('click', showLookahead);
        $('#pi-cal-lookahead-modal-close, #pi-cal-lookahead-close').on('click', function() {
            $('#pi-cal-lookahead-modal').removeClass('pi-cal-modal-open');
        });

        // Job-centric mode
        $('#pi-cal-job-mode-toggle').on('click', toggleJobCentricMode);
        $('#pi-cal-sidebar-close').on('click', toggleJobCentricMode);

        $('#pi-cal-job-select').on('change', function() {
            selectJob($(this).val());
        });

        // Modal tabs
        $('.pi-cal-tab-btn').on('click', function() {
            const tab = $(this).data('tab');
            $('.pi-cal-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.pi-cal-tab-content').removeClass('active');
            $(`.pi-cal-tab-content[data-tab="${tab}"]`).addClass('active');
        });

        // Modal actions
        $('#pi-cal-modal-close, #pi-cal-modal-cancel').on('click', closeEventModal);
        $('#pi-cal-event-save').on('click', function(e) {
            e.preventDefault();
            saveEvent();
        });

        $('#pi-cal-event-delete').on('click', async function() {
            const id = $('#pi-cal-event-id').val();
            if (!id) return;

            if (confirm('Delete this event? This cannot be undone.')) {
                try {
                    await ApiClient.deleteEvent(parseInt(id));
                    closeEventModal();
                    calendar.refetchEvents();
                    Toast.show('Event deleted', 'success');
                } catch (error) {
                    Toast.show('Failed to delete', 'error');
                }
            }
        });

        $('#pi-cal-event-duplicate').on('click', async function() {
            const id = $('#pi-cal-event-id').val();
            if (!id) return;

            try {
                await ApiClient.duplicateEvent(parseInt(id));
                closeEventModal();
                calendar.refetchEvents();
                Toast.show('Event duplicated', 'success');
            } catch (error) {
                Toast.show('Failed to duplicate', 'error');
            }
        });

        // Checklist
        $('#pi-cal-checklist-add-btn').on('click', function() {
            addChecklistItem($('#pi-cal-checklist-input').val());
        });

        $('#pi-cal-checklist-input').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addChecklistItem($(this).val());
            }
        });

        $(document).on('change', '.pi-cal-checklist-checkbox input', function() {
            toggleChecklistItem(parseInt($(this).data('index')));
        });

        $(document).on('click', '.pi-cal-checklist-delete', function() {
            deleteChecklistItem(parseInt($(this).data('index')));
        });

        // Template save toggle
        $('#pi-cal-save-template').on('change', function() {
            $('#pi-cal-template-name-field').toggle(this.checked);
        });

        // Weather sensitive toggle
        $('#pi-cal-event-weather-sensitive').on('change', function() {
            $('#pi-cal-weather-forecast').toggle(this.checked);
            if (this.checked) {
                // Could add weather fetch logic here
                $('#pi-cal-weather-preview').html('<p>Weather forecast will appear here...</p>');
            }
        });

        // Ignore conflicts toggle
        $('#pi-cal-event-ignore-conflicts').on('change', function() {
            // Could add visual feedback here
            if (this.checked) {
                Toast.show('Scheduling conflicts will be ignored', 'warning');
            }
        });

        // Mileage toggle
        $('#pi-cal-event-mileage').on('change', function() {
            $('#pi-cal-mileage-form').toggle(this.checked);
            if (this.checked) {
                const jobId = $('#pi-cal-event-job').val();
                if (jobId) {
                    const job = PI_Calendar.jobs.find(j => j.id == jobId);
                    if (job && job.postcode) {
                        $('#pi-cal-mileage-to').val(job.postcode);
                    }
                }
            }
        });

        // All-day toggle
        $('#pi-cal-event-all-day').on('change', function() {
            const $start = $('#pi-cal-event-start');
            const $end = $('#pi-cal-event-end');
            const startVal = $start.val();
            const endVal = $end.val();

            if (this.checked) {
                $start.attr('type', 'date').val(startVal.substring(0, 10));
                $end.attr('type', 'date').val(endVal ? endVal.substring(0, 10) : startVal.substring(0, 10));
            } else {
                $start.attr('type', 'datetime-local').val(startVal + 'T09:00');
                $end.attr('type', 'datetime-local').val((endVal || startVal) + 'T17:00');
            }
        });

        // Type change - update icon
        $('#pi-cal-event-type').on('change', function() {
            updateModalIcon($(this).val());
        });

        // Conflict modal
        $('#pi-cal-conflict-cancel').on('click', function() {
            $('#pi-cal-conflict-modal').removeClass('pi-cal-modal-open');
            AppState.pendingEventData = null;
        });

        $('#pi-cal-conflict-proceed').on('click', async function() {
            $('#pi-cal-conflict-modal').removeClass('pi-cal-modal-open');
            if (AppState.pendingEventData) {
                AppState.pendingEventData.ignore_conflicts = true;
                $('#pi-cal-event-ignore-conflicts').prop('checked', true);
                await saveEvent();
            }
        });

        // Shortcuts
        $('#pi-cal-shortcuts-close').on('click', toggleShortcuts);

        // Context menu
        $(document).on('contextmenu', '.fc-event', function(e) {
            e.preventDefault();
            const eventId = $(this).data('event-id');
            showContextMenu(e, eventId);
        });

        $(document).on('click', '.pi-cal-context-item', function() {
            $('#pi-cal-context-menu').hide();
            handleContextAction($(this).data('action'));
        });

        // Quick links
        $('#pi-cal-today-visits').on('click', function() {
            // Clear all other filters first
            AppState.setFilter('type', 'site_visit');
            AppState.setFilter('job', '');
            AppState.setFilter('status', '');
            AppState.setFilter('priority', '');
            AppState.setFilter('crew', '');
            AppState.setFilter('trade', '');
            AppState.searchQuery = '';
            
            // Update UI filters
            $('#pi-cal-filter-type').val('site_visit');
            $('#pi-cal-filter-job').val('');
            $('#pi-cal-filter-status').val('');
            $('#pi-cal-filter-priority').val('');
            $('#pi-cal-filter-crew').val('');
            $('#pi-cal-filter-trade').val('');
            $('#pi-cal-search').val('');
            
            // Navigate to today and refetch
            calendar.gotoDate(new Date());
            calendar.refetchEvents();
            
            Toast.show('Showing today\'s site visits', 'success');
        });

        $('#pi-cal-upcoming-deliveries').on('click', function() {
            // Clear all other filters first
            AppState.setFilter('type', 'delivery');
            AppState.setFilter('job', '');
            AppState.setFilter('status', '');
            AppState.setFilter('priority', '');
            AppState.setFilter('crew', '');
            AppState.setFilter('trade', '');
            AppState.searchQuery = '';
            
            // Update UI filters
            $('#pi-cal-filter-type').val('delivery');
            $('#pi-cal-filter-job').val('');
            $('#pi-cal-filter-status').val('');
            $('#pi-cal-filter-priority').val('');
            $('#pi-cal-filter-crew').val('');
            $('#pi-cal-filter-trade').val('');
            $('#pi-cal-search').val('');
            
            // Navigate to today and refetch to show upcoming deliveries
            calendar.gotoDate(new Date());
            calendar.refetchEvents();
            
            Toast.show('Showing upcoming deliveries', 'success');
        });

        $('#pi-cal-crew-conflicts').on('click', async function() {
            const conflicts = await checkConflicts();
            if (conflicts.length === 0) {
                Toast.show('No crew conflicts detected', 'success');
            } else {
                let msg = `Found ${conflicts.length} conflict${conflicts.length > 1 ? 's' : ''}:\n`;
                conflicts.forEach((c, i) => {
                    if (i < 3) msg += `- ${c.ev1.title} ↔ ${c.ev2.title}\n`;
                });
                alert(msg);
            }
        });

        // Toggle switches - handle change events for pi-cal-toggle components
        $(document).on('change', '.pi-cal-toggle input[type="checkbox"]', function() {
            const $toggle = $(this).closest('.pi-cal-toggle');
            const settingName = $(this).attr('name') || $(this).attr('id');
            const isChecked = $(this).is(':checked');
            
            // Trigger custom event for setting changes
            $(document).trigger('pi:toggle:changed', [settingName, isChecked, $toggle]);
        });

        // Handle clicks on the slider itself (when user clicks directly on the visual slider)
        $(document).on('click', '.pi-cal-toggle-slider', function(e) {
            // Don't prevent default - let the label's natural behavior work
            // Just ensure the event doesn't bubble strangely
            e.stopPropagation();
        });

        // Modal backdrop click
        $('.pi-cal-modal-backdrop').on('click', function(e) {
            if (e.target === this && !$(this).is('#pi-cal-conflict-modal')) {
                $(this).removeClass('pi-cal-modal-open');
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INITIALIZATION
    // ─────────────────────────────────────────────────────────────────────────
    $(function() {
        if (!$('#pi-workspace-calendar').length) return;

        AppState.init();
        initCalendar();
        bindEvents();

        // Initial load
        updateKPIs();

        // Welcome toast
        setTimeout(() => {
            Toast.show('Calendar loaded - Press ? for shortcuts', 'info', 5000);
        }, 1000);
    });

})(jQuery);

