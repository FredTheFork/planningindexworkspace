/**
 * Job-Specific Calendar - Minified FullCalendar Implementation
 * 
 * Features:
 * - Full month/week/day views
 * - Create, edit, delete events
 * - Job-specific only (events filtered by job_id)
 * - Links to main calendar (events appear there too)
 * - Professional weather icons integration
 * 
 * @version 1.0.0
 */
(function($) {
  'use strict';

  console.log('[JobCalendar] Script starting...');
  console.log('[JobCalendar] FullCalendar available:', typeof FullCalendar !== 'undefined');
  console.log('[JobCalendar] PI_Job available:', typeof PI_Job !== 'undefined');
  if (typeof PI_Job !== 'undefined') {
    console.log('[JobCalendar] PI_Job.job_id:', PI_Job.job_id);
    console.log('[JobCalendar] PI_Job.nonce:', PI_Job.nonce ? 'present' : 'missing');
  }

  // Check dependencies
  if (typeof FullCalendar === 'undefined' || !FullCalendar.Calendar) {
    console.error('[JobCalendar] CRITICAL: FullCalendar not loaded');
    return;
  }

  if (typeof PI_Job === 'undefined' || !PI_Job.job_id) {
    console.error('[JobCalendar] CRITICAL: PI_Job not loaded or missing job_id');
    console.error('[JobCalendar] PI_Job =', typeof PI_Job !== 'undefined' ? PI_Job : 'undefined');
    return;
  }

  // ─────────────────────────────────────────────────────────────────────────
  // CONFIGURATION
  // ─────────────────────────────────────────────────────────────────────────
  const CONFIG = {
    jobId: PI_Job.job_id,
    restBase: '/wp-json/pi/v1',
    nonce: PI_Job.nonce || '',
    container: '#pi-job-calendar',
    colors: {
      job: '#10b981',
      site_visit: '#3b82f6',
      delivery: '#f97316',
      appointment: '#8b5cf6'
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // STATE
  // ─────────────────────────────────────────────────────────────────────────
  let calendar = null;
  let currentEvent = null;
  let isModalOpen = false;

  // ─────────────────────────────────────────────────────────────────────────
  // API CLIENT
  // ─────────────────────────────────────────────────────────────────────────
  const API = {
    headers() {
      return {
        'X-WP-Nonce': CONFIG.nonce,
        'Content-Type': 'application/json'
      };
    },

    async getEvents(start, end) {
      const params = new URLSearchParams({
        start: start,
        end: end,
        job_id: CONFIG.jobId
      });
      const resp = await fetch(`${CONFIG.restBase}/schedule/events?${params}`, {
        headers: this.headers()
      });
      if (!resp.ok) throw new Error('Failed to fetch events');
      return resp.json();
    },

    async createEvent(data) {
      // Auto-assign job_id for this job calendar
      data.job_id = CONFIG.jobId;
      const resp = await fetch(`${CONFIG.restBase}/schedule/events/add`, {
        method: 'POST',
        headers: this.headers(),
        body: JSON.stringify(data)
      });
      return resp.json();
    },

    async updateEvent(data) {
      const resp = await fetch(`${CONFIG.restBase}/schedule/events/update`, {
        method: 'POST',
        headers: this.headers(),
        body: JSON.stringify(data)
      });
      return resp.json();
    },

    async deleteEvent(id) {
      const resp = await fetch(`${CONFIG.restBase}/schedule/events/remove`, {
        method: 'POST',
        headers: this.headers(),
        body: JSON.stringify({ id })
      });
      return resp.json();
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // CALENDAR INITIALIZATION
  // ─────────────────────────────────────────────────────────────────────────
  function initCalendar() {
    console.log('[JobCalendar] initCalendar() called');
    const calendarEl = document.querySelector(CONFIG.container);
    if (!calendarEl) {
      console.error('[JobCalendar] Container not found:', CONFIG.container);
      return false;
    }

    // Check if container is visible/has dimensions
    const rect = calendarEl.getBoundingClientRect();
    console.log('[JobCalendar] Container dimensions:', rect.width, 'x', rect.height);
    if (rect.width === 0 || rect.height === 0) {
      console.log('[JobCalendar] Container not visible yet (0x0), waiting...');
      return false;
    }

    console.log('[JobCalendar] Container ready, creating FullCalendar...');

    // Destroy existing calendar if any
    if (calendar) {
      console.log('[JobCalendar] Destroying existing calendar');
      calendar.destroy();
    }

    try {
      calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: '100%',
      
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },

      buttonText: {
        today: 'Today',
        month: 'Month',
        week: 'Week',
        day: 'Day',
        list: 'List'
      },

      // Event data source - filtered by job_id
      events: function(info, successCallback, failureCallback) {
        console.log('[JobCalendar] Loading events for range:', info.startStr, 'to', info.endStr);
        API.getEvents(info.startStr, info.endStr)
          .then(events => {
            console.log('[JobCalendar] Loaded', events.length, 'events');
            successCallback(events);
          })
          .catch(err => {
            console.error('[JobCalendar] Failed to load events:', err);
            failureCallback(err);
            showToast('Failed to load events', 'error');
          });
      },

      // Click on date -> create event
      select: function(info) {
        openEventModal(null, info.startStr, info.endStr, info.allDay);
        calendar.unselect();
      },

      // Click on event -> edit
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        openEventModal(info.event);
      },

      // Drag to reschedule
      editable: true,
      eventDrop: function(info) {
        handleEventDrop(info.event);
      },

      // Resize to change duration
      eventResize: function(info) {
        handleEventResize(info.event);
      },

      // Selection
      selectable: true,
      selectMirror: true,
      dayMaxEvents: true,
      weekends: true,
      nowIndicator: true,
      
      // Styling
      eventTimeFormat: {
        hour: 'numeric',
        minute: '2-digit',
        meridiem: 'short'
      }
    });

    calendar.render();
    console.log('[JobCalendar] Calendar rendered successfully');
    return true;
    } catch (err) {
      console.error('[JobCalendar] Failed to create calendar:', err);
      return false;
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // EVENT HANDLERS
  // ─────────────────────────────────────────────────────────────────────────
  async function handleEventDrop(event) {
    try {
      const data = {
        id: event.id,
        start: event.start.toISOString(),
        end: event.end ? event.end.toISOString() : null,
        all_day: event.allDay
      };

      const result = await API.updateEvent(data);
      if (result.updated) {
        showToast('Event rescheduled');
      } else if (result.conflicts && result.requires_confirmation) {
        // Handle conflicts - revert and show warning
        event.revert();
        showToast('Crew conflict detected. Use full calendar to override.', 'warning');
      }
    } catch (err) {
      console.error('[JobCalendar] Drop failed:', err);
      event.revert();
      showToast('Failed to reschedule', 'error');
    }
  }

  async function handleEventResize(event) {
    try {
      const data = {
        id: event.id,
        start: event.start.toISOString(),
        end: event.end ? event.end.toISOString() : null,
        all_day: event.allDay
      };

      const result = await API.updateEvent(data);
      if (result.updated) {
        showToast('Event duration updated');
      }
    } catch (err) {
      console.error('[JobCalendar] Resize failed:', err);
      event.revert();
      showToast('Failed to update duration', 'error');
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // MODAL FUNCTIONS
  // ─────────────────────────────────────────────────────────────────────────
  function openEventModal(event, startDate, endDate, allDay) {
    if (isModalOpen) return;
    isModalOpen = true;
    currentEvent = event;

    const isEdit = !!event;
    const eventData = isEdit ? event.extendedProps.raw : {};
    
    const modalHtml = `
      <div class="pi-job-event-modal-overlay" id="pi-job-event-modal">
        <div class="pi-job-event-modal">
          <div class="pi-job-event-modal-header">
            <h4>${isEdit ? 'Edit Event' : 'Add Event'}</h4>
            <button type="button" class="pi-job-event-modal-close" onclick="JobCalendar.closeModal()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
          <div class="pi-job-event-modal-body">
            <form id="pi-job-event-form">
              <div class="pi-job-event-form-row">
                <label>Title *</label>
                <input type="text" name="title" value="${escapeHtml(eventData.title || '')}" required placeholder="Event title">
              </div>
              
              <div class="pi-job-event-form-row">
                <label>Type</label>
                <select name="type">
                  <option value="job" ${(eventData.type || 'job') === 'job' ? 'selected' : ''}>Job Work</option>
                  <option value="site_visit" ${eventData.type === 'site_visit' ? 'selected' : ''}>Site Visit</option>
                  <option value="delivery" ${eventData.type === 'delivery' ? 'selected' : ''}>Delivery</option>
                  <option value="appointment" ${eventData.type === 'appointment' ? 'selected' : ''}>Appointment</option>
                </select>
              </div>

              <div class="pi-job-event-form-grid" id="pi-job-datetime-grid">
                <div class="pi-job-event-form-row">
                  <label>Start *</label>
                  <input type="${(eventData.all_day || allDay) ? 'date' : 'datetime-local'}" name="start" id="pi-job-start-input" value="${(eventData.all_day || allDay) ? formatDateOnly(startDate || eventData.start || new Date()) : formatDateTimeLocal(startDate || eventData.start || new Date())}" required>
                </div>
                <div class="pi-job-event-form-row">
                  <label>End</label>
                  <input type="${(eventData.all_day || allDay) ? 'date' : 'datetime-local'}" name="end" id="pi-job-end-input" value="${(eventData.all_day || allDay) ? formatDateOnly(endDate || eventData.end || '') : formatDateTimeLocal(endDate || eventData.end || '')}">
                </div>
              </div>

              <div class="pi-job-event-form-row">
                <label class="pi-job-event-form-checkbox">
                  <input type="checkbox" name="all_day" id="pi-job-all-day" ${(eventData.all_day || allDay) ? 'checked' : ''} onchange="JobCalendar.toggleAllDay(this)">
                  All day event
                </label>
              </div>

              <div class="pi-job-event-form-row">
                <label>Status</label>
                <select name="status">
                  <option value="scheduled" ${(eventData.status || 'scheduled') === 'scheduled' ? 'selected' : ''}>Scheduled</option>
                  <option value="in_progress" ${eventData.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                  <option value="completed" ${eventData.status === 'completed' ? 'selected' : ''}>Completed</option>
                  <option value="cancelled" ${eventData.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
              </div>

              <div class="pi-job-event-form-row">
                <label>Priority</label>
                <select name="priority">
                  <option value="low" ${eventData.priority === 'low' ? 'selected' : ''}>Low</option>
                  <option value="medium" ${(eventData.priority || 'medium') === 'medium' ? 'selected' : ''}>Medium</option>
                  <option value="high" ${eventData.priority === 'high' ? 'selected' : ''}>High</option>
                </select>
              </div>

              <div class="pi-job-event-form-row">
                <label>Notes</label>
                <textarea name="notes" placeholder="Event details, crew instructions, etc.">${escapeHtml(eventData.notes || '')}</textarea>
              </div>

              <input type="hidden" name="id" value="${isEdit ? event.id : ''}">
            </form>
          </div>
          <div class="pi-job-event-modal-footer">
            ${isEdit ? `<button type="button" class="pi-btn pi-btn-danger" onclick="JobCalendar.deleteCurrentEvent()">Delete</button>` : '<div></div>'}
            <div class="pi-job-event-modal-actions">
              <button type="button" class="pi-btn pi-btn-secondary" onclick="JobCalendar.closeModal()">Cancel</button>
              <button type="button" class="pi-btn pi-btn-primary" onclick="JobCalendar.saveEvent()">${isEdit ? 'Save Changes' : 'Add Event'}</button>
            </div>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    const modal = document.getElementById('pi-job-event-modal');
    if (modal) {
      modal.remove();
    }
    document.body.style.overflow = '';
    isModalOpen = false;
    currentEvent = null;
  }

  async function saveEvent() {
    const form = document.getElementById('pi-job-event-form');
    if (!form) return;

    const formData = new FormData(form);
    const data = {
      id: formData.get('id') || null,
      title: formData.get('title'),
      type: formData.get('type'),
      start: formData.get('start'),
      end: formData.get('end') || null,
      all_day: formData.has('all_day'),
      status: formData.get('status'),
      priority: formData.get('priority'),
      notes: formData.get('notes')
    };

    if (!data.title || !data.start) {
      showToast('Title and start date are required', 'error');
      return;
    }

    try {
      let result;
      if (data.id) {
        result = await API.updateEvent(data);
        if (result.updated) {
          showToast('Event updated');
          calendar.refetchEvents();
          closeModal();
        } else if (result.conflicts) {
          showToast('Crew conflict detected. Check full calendar.', 'warning');
        }
      } else {
        result = await API.createEvent(data);
        if (result.created) {
          showToast('Event created');
          calendar.refetchEvents();
          closeModal();
        } else if (result.conflicts) {
          showToast('Crew conflict detected. Check full calendar.', 'warning');
        }
      }
    } catch (err) {
      console.error('[JobCalendar] Save failed:', err);
      showToast('Failed to save event', 'error');
    }
  }

  async function deleteCurrentEvent() {
    if (!currentEvent || !confirm('Are you sure you want to delete this event?')) {
      return;
    }

    try {
      const result = await API.deleteEvent(currentEvent.id);
      if (result.removed) {
        showToast('Event deleted');
        calendar.refetchEvents();
        closeModal();
      }
    } catch (err) {
      console.error('[JobCalendar] Delete failed:', err);
      showToast('Failed to delete event', 'error');
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // UTILITIES
  // ─────────────────────────────────────────────────────────────────────────
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatDateTimeLocal(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '';
    
    // Format as YYYY-MM-DDTHH:mm for datetime-local input
    const pad = n => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }

  function formatDateOnly(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '';
    
    // Format as YYYY-MM-DD for date input
    const pad = n => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  }

  function showToast(message, type = 'success') {
    // Remove existing toast
    const existing = document.querySelector('.pi-job-cal-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `pi-job-cal-toast ${type}`;
    toast.innerHTML = `
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        ${type === 'success' 
          ? '<path d="M20 6L9 17l-5-5"/>'
          : type === 'error'
            ? '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'
            : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
        }
      </svg>
      <span class="pi-job-cal-toast-message">${escapeHtml(message)}</span>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(20px)';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // ─────────────────────────────────────────────────────────────────────────
  // BIND ADD BUTTON
  // ─────────────────────────────────────────────────────────────────────────
  function bindAddButton() {
    const addBtn = document.getElementById('pi-job-cal-add');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        const now = new Date();
        const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);
        openEventModal(null, now.toISOString(), oneHourLater.toISOString(), false);
      });
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // PUBLIC API
  // ─────────────────────────────────────────────────────────────────────────
  window.JobCalendar = {
    init: initWithRetry,
    openEventModal,
    closeModal,
    saveEvent,
    deleteCurrentEvent,
    refresh: () => {
      if (!calendar) {
        // Try to initialize if not already
        initWithRetry();
        return;
      }
      calendar.refetchEvents();
      // Also update size in case tab was switched
      setTimeout(() => calendar.updateSize(), 50);
    },
    updateSize: () => calendar && calendar.updateSize(),
    toggleAllDay: function(checkbox) {
      const startInput = document.getElementById('pi-job-start-input');
      const endInput = document.getElementById('pi-job-end-input');
      if (!startInput || !endInput) return;

      if (checkbox.checked) {
        // Switch to date only - extract date part and set to midnight
        const startVal = startInput.value;
        const endVal = endInput.value;
        
        // Change input type to date
        startInput.type = 'date';
        endInput.type = 'date';
        
        // Set values (extract date portion if datetime, or keep if already date)
        if (startVal && startVal.includes('T')) {
          startInput.value = startVal.split('T')[0];
        }
        if (endVal && endVal.includes('T')) {
          endInput.value = endVal.split('T')[0];
        }
      } else {
        // Switch back to datetime-local
        const startVal = startInput.value;
        const endVal = endInput.value;
        
        // Change input type back to datetime-local
        startInput.type = 'datetime-local';
        endInput.type = 'datetime-local';
        
        // Set default times (9:00 AM start, 10:00 AM end)
        if (startVal) {
          startInput.value = startVal + 'T09:00';
        }
        if (endVal) {
          endInput.value = endVal + 'T10:00';
        } else if (startVal) {
          endInput.value = startVal + 'T10:00';
        }
      }
    }
  };

  // Initialize when DOM is ready - with retry for hidden containers
  function initWithRetry(attempts = 0) {
    console.log('[JobCalendar] initWithRetry attempt', attempts);
    const container = document.querySelector(CONFIG.container);
    if (!container) {
      console.log('[JobCalendar] Container not found:', CONFIG.container);
      if (attempts < 50) { // Max 5 seconds
        console.log('[JobCalendar] Container not ready, retrying...');
        setTimeout(() => initWithRetry(attempts + 1), 100);
      } else {
        console.error('[JobCalendar] FAILED: Container never appeared after 5s');
      }
      return;
    }

    console.log('[JobCalendar] Container found:', container);

    // Check if container has dimensions (is visible)
    const rect = container.getBoundingClientRect();
    console.log('[JobCalendar] Container dimensions:', rect.width, 'x', rect.height);
    if (rect.width === 0 || rect.height === 0) {
      if (attempts < 50) {
        console.log('[JobCalendar] Container not visible (0 dimensions), retrying...');
        setTimeout(() => initWithRetry(attempts + 1), 100);
      } else {
        console.error('[JobCalendar] FAILED: Container never became visible after 5s');
      }
      return;
    }

    // Container is ready - initialize
    console.log('[JobCalendar] Container ready, initializing calendar...');
    if (initCalendar()) {
      bindAddButton();
      console.log('[JobCalendar] Initialized successfully');
    }
  }

  // Initialize when DOM is ready
  function init() {
    initWithRetry(0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Also listen for tab changes to resize calendar
  $(document).on('click', '.job-nav-item[data-job-tab="schedule"]', function() {
    console.log('[JobCalendar] Schedule tab clicked');
    setTimeout(() => {
      if (!calendar) {
        console.log('[JobCalendar] Calendar not initialized, starting init...');
        initWithRetry(0);
      } else {
        console.log('[JobCalendar] Updating calendar size...');
        calendar.updateSize();
        calendar.refetchEvents();
      }
    }, 150);
  });

  // Watch for tab becoming visible via class changes
  const scheduleTab = document.getElementById('job-tab-schedule');
  if (scheduleTab) {
    console.log('[JobCalendar] Setting up MutationObserver for schedule tab');
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
          const isActive = scheduleTab.classList.contains('active');
          console.log('[JobCalendar] Tab active state changed:', isActive);
          if (isActive) {
            setTimeout(() => {
              if (!calendar) {
                initWithRetry(0);
              } else {
                calendar.updateSize();
              }
            }, 100);
          }
        }
      });
    });
    observer.observe(scheduleTab, { attributes: true });
  }

  // Handle window resize
  $(window).on('resize', () => {
    if (calendar) {
      calendar.updateSize();
    }
  });

  // ─────────────────────────────────────────────────────────────────────────
  // WEATHER ICON FUNCTIONS (Shared with main calendar)
  // ─────────────────────────────────────────────────────────────────────────
  window.getDetailedWeatherIcon = function(weatherCode) {
    // Professional weather icons from the weathericons directory
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
  };

  window.getWeatherIconPath = function(iconName) {
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
    
    const svgIcon = WeatherIcons[iconName] || WeatherIcons.sunny;
    return `<span class="weather-svg-icon" style="display: inline-block; width: 20px; height: 20px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">${svgIcon}</span>`;
  };

  window.getWeatherType = function(weatherCode) {
    // Classify weather type for color coding
    if ([0, 1].includes(weatherCode)) return 'clear';
    if ([2, 3].includes(weatherCode)) return 'cloud';
    if ([45, 48].includes(weatherCode)) return 'cloud';
    if ([51, 53, 55, 61, 63, 65].includes(weatherCode)) return 'rain';
    if ([71, 73, 75].includes(weatherCode)) return 'snow';
    if ([95, 96, 99].includes(weatherCode)) return 'storm';
    return 'clear'; // Default
  };

  console.log('[JobCalendar] Loaded with weather icon support');

})(jQuery);
