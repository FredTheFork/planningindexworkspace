/**
 * Planning Index - Job Communications System
 * Full email communication management for single job page
 * Version 1.0
 */

jQuery(($) => {
    'use strict';

    // CRITICAL: Extract job_id from URL FIRST (most reliable)
    let jobId = null;
    let jobRef = null;
    
    const urlMatch = window.location.pathname.match(/\/job\/(\d+)\//);
    if (urlMatch) {
        jobId = parseInt(urlMatch[1]);
        console.log('[Communications] Extracted jobId from URL:', jobId);
    }
    
    // Check for PI_Job_Communications (set by inline script)
    if (typeof PI_Job_Communications !== 'undefined' && PI_Job_Communications.job_id) {
        jobId = jobId || PI_Job_Communications.job_id;
        jobRef = jobRef || PI_Job_Communications.job_ref;
        console.log('[Communications] Got jobId from PI_Job_Communications:', jobId);
    }
    
    // Ensure PI_Job exists with defaults
    if (typeof PI_Job === 'undefined') {
        window.PI_Job = {};
        console.warn('[Communications] PI_Job was undefined, created empty object');
    }
    
    // Merge PI_Job_Communications into PI_Job if available
    if (typeof PI_Job_Communications !== 'undefined') {
        Object.assign(PI_Job, PI_Job_Communications);
    }
    
    // Ensure required properties exist
    if (!PI_Job.rest_base) {
        PI_Job.rest_base = window.location.origin + '/wp-json/pi/v1';
        console.warn('[Communications] PI_Job.rest_base missing, using fallback:', PI_Job.rest_base);
    }
    if (!PI_Job.nonce) {
        PI_Job.nonce = '';
        console.warn('[Communications] PI_Job.nonce missing');
    }
    // Force job_id from URL if not set
    if (!PI_Job.job_id && jobId) {
        PI_Job.job_id = jobId;
    }

    // Get job data - prefer URL extraction, fallback to DOM/PI_Job
    jobId = jobId || $('#job-single-page').data('job-id') || PI_Job?.job_id;
    jobRef = jobRef || $('#job-sidebar-ref').text().trim() || PI_Job?.job_ref;
    
    // DEBUG: Log what we found
    console.log('[Communications] URL job-id:', urlMatch?.[1]);
    console.log('[Communications] DOM job-id:', $('#job-single-page').data('job-id'));
    console.log('[Communications] PI_Job_Communications:', typeof PI_Job_Communications !== 'undefined' ? PI_Job_Communications?.job_id : 'UNDEFINED');
    console.log('[Communications] PI_Job.job_id:', PI_Job?.job_id);
    console.log('[Communications] Final jobId:', jobId);
    
    // If still no jobId, show error immediately
    if (!jobId) {
        console.error('[Communications] CRITICAL: jobId is still undefined after all fallbacks!');
        alert('DEBUG: jobId is undefined. Check console for details.');
    }

    // State
    let emailHistory = [];
    let currentDraft = null;
    
    // SMTP Configuration State
    let userSmtpSettings = { enabled: false, from_email: '', from_name: '' };
    let piSmtpModalLocked = false;
    let piSmtpModalOpenTime = 0;

    console.log('[Communications] Initialized for job:', jobId, jobRef);
    console.log('[Communications] Full PI_Job object:', JSON.parse(JSON.stringify(PI_Job || {})));

    // ===========================================
    // UI RENDERING
    // ===========================================

    function renderCommunicationsInterface() {
        const container = $('#job-tab-communications');
        if (!container.length || container.find('.pi-communications-wrapper').length) return;

        const html = `
            <div class="pi-communications-wrapper">
                <div class="pi-communications-header">
                    <h3>Email Communications</h3>
                    <div class="pi-communications-actions">
                        <button class="pi-btn pi-btn-primary" id="pi-new-email-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            Compose Email
                        </button>
                    </div>
                </div>

                <div class="pi-communications-content">
                    <!-- Email List Sidebar -->
                    <div class="pi-communications-sidebar">
                        <div class="pi-communications-filters">
                            <button class="pi-filter-btn active" data-filter="all">All</button>
                            <button class="pi-filter-btn" data-filter="sent">Sent</button>
                            <button class="pi-filter-btn" data-filter="received">Received</button>
                        </div>
                        <div class="pi-communications-list" id="pi-email-list">
                            <div class="pi-communications-empty">No emails yet</div>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="pi-communications-main">
                        <div class="pi-email-composer" id="pi-email-composer">
                            <div class="pi-composer-header">
                                <h4>New Message</h4>
                                <div class="pi-composer-actions">
                                    <button class="pi-btn pi-btn-secondary" id="pi-save-draft-btn">Save Draft</button>
                                    <button class="pi-btn pi-btn-primary" id="pi-send-email-btn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="22" y1="2" x2="11" y2="13"/>
                                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                        </svg>
                                        Send
                                    </button>
                                </div>
                            </div>
                            <form class="pi-composer-form" id="pi-email-form">
                                <div class="pi-form-row pi-form-row-from">
                                    <label>From:</label>
                                    <div class="pi-from-fields">
                                        <input type="text" id="pi-email-from-name" 
                                               placeholder="Your Name" 
                                               value="${getDefaultFromName()}">
                                        <input type="email" id="pi-email-from" required 
                                               placeholder="your@email.com" 
                                               value="${getDefaultFromEmail()}">
                                    </div>
                                    <div class="pi-from-status" id="pi-from-status">
                                        <span class="pi-status-text pi-status-default">via hello@planningindex.co.uk</span>
                                        <button type="button" class="pi-btn-smtp-config" id="pi-configure-smtp-btn">Use My Email</button>
                                    </div>
                                </div>
                                <div class="pi-form-row">
                                    <label>To:</label>
                                    <input type="email" id="pi-email-to" required 
                                           placeholder="customer@email.com">
                                </div>
                                <div class="pi-form-row">
                                    <label>Cc:</label>
                                    <input type="email" id="pi-email-cc" 
                                           placeholder="cc@email.com">
                                </div>
                                <div class="pi-form-row">
                                    <label>Subject:</label>
                                    <input type="text" id="pi-email-subject" required 
                                           placeholder="Email subject...">
                                </div>
                                <div class="pi-form-row">
                                    <label>Template:</label>
                                    <select id="pi-email-template">
                                        <option value="">-- Select a template --</option>
                                        <option value="project_start">Project Start Notification</option>
                                        <option value="progress_update">Progress Update</option>
                                        <option value="schedule_change">Schedule Change</option>
                                        <option value="invoice">Invoice Attached</option>
                                        <option value="payment_reminder">Payment Reminder</option>
                                        <option value="project_complete">Project Complete</option>
                                        <option value="custom">Custom Message</option>
                                    </select>
                                </div>
                                <div class="pi-form-row pi-form-row-full">
                                    <label>Message:</label>
                                    <textarea id="pi-email-body" rows="12" required 
                                              placeholder="Type your message here..."></textarea>
                                </div>
                                <div class="pi-form-row">
                                    <label>Attachments:</label>
                                    <div class="pi-attachments-area">
                                        <input type="file" id="pi-email-attachments" multiple 
                                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                                        <div class="pi-attachments-list" id="pi-attachments-list"></div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Email View (hidden by default) -->
                        <div class="pi-email-view" id="pi-email-view" style="display:none">
                            <div class="pi-email-view-header">
                                <button class="pi-btn pi-btn-secondary" id="pi-back-to-list">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="19" y1="12" x2="5" y2="12"/>
                                        <polyline points="12 19 5 12 12 5"/>
                                    </svg>
                                    Back
                                </button>
                                <div class="pi-email-view-actions">
                                    <button class="pi-btn pi-btn-secondary" id="pi-reply-btn">Reply</button>
                                    <button class="pi-btn pi-btn-secondary" id="pi-forward-btn">Forward</button>
                                </div>
                            </div>
                            <div class="pi-email-view-content" id="pi-email-view-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.html(html);
        bindEvents();
        loadEmailHistory();
    }

    function getDefaultFromEmail() {
        // Get from WordPress settings or current user
        if (PI_Job.site_email) return PI_Job.site_email;
        if (PI_Job.user_email) return PI_Job.user_email;
        return '';
    }

    function getDefaultFromName() {
        // Get user's display name or fallback to PlanningIndex
        if (PI_Job.user_name) return PI_Job.user_name;
        return 'PlanningIndex';
    }

    // ===========================================
    // EMAIL TEMPLATES
    // ===========================================

    const emailTemplates = {
        project_start: {
            subject: `Project ${jobRef} - Project Start Notification`,
            body: `Dear Client,

We're pleased to inform you that work on your project ${jobRef} has officially begun.

PROJECT DETAILS:
Job Reference: ${jobRef}
Start Date: ${new Date().toLocaleDateString()}

WHAT HAPPENS NEXT:
- Our team will arrive on site as scheduled
- We'll keep you updated on progress throughout
- Please don't hesitate to reach out with any questions

Thank you for choosing us for your project.

Best regards,
[Your Name]
[Your Company]`
        },
        progress_update: {
            subject: `Project ${jobRef} - Progress Update`,
            body: `Dear Client,

We wanted to update you on the progress of your project ${jobRef}.

CURRENT STATUS:
Work is proceeding as planned. We're making good progress on the scheduled tasks.

NEXT STEPS:
- Continue with remaining work items
- Quality checks and inspections
- Final cleanup

If you have any questions about the project, please don't hesitate to contact us.

Best regards,
[Your Name]
[Your Company]`
        },
        schedule_change: {
            subject: `Project ${jobRef} - Schedule Update`,
            body: `Dear Client,

We need to inform you of a schedule change for project ${jobRef}.

UPDATED SCHEDULE:
[Please specify the new dates/times]

We apologize for any inconvenience this may cause. This change is necessary due to [reason].

Please let us know if you have any concerns or questions about this change.

Best regards,
[Your Name]
[Your Company]`
        },
        invoice: {
            subject: `Project ${jobRef} - Invoice`,
            body: `Dear Client,

Please find attached the invoice for project ${jobRef}.

INVOICE DETAILS:
Job Reference: ${jobRef}
Invoice Date: ${new Date().toLocaleDateString()}

Payment terms: 30 days from invoice date

If you have any questions about this invoice, please don't hesitate to contact us.

Thank you for your business.

Best regards,
[Your Name]
[Your Company]`
        },
        payment_reminder: {
            subject: `Project ${jobRef} - Payment Reminder`,
            body: `Dear Client,

This is a friendly reminder regarding payment for project ${jobRef}.

OUTSTANDING BALANCE:
[Amount]
Due Date: [Date]

If payment has already been sent, please disregard this message.

If you have any questions about your account, please don't hesitate to contact us.

Best regards,
[Your Name]
[Your Company]`
        },
        project_complete: {
            subject: `Project ${jobRef} - Project Complete`,
            body: `Dear Client,

We're delighted to inform you that your project ${jobRef} has been completed successfully.

COMPLETION DETAILS:
- All work has been finished to specification
- Final quality checks completed
- Site has been cleaned and tidied

We're grateful for the opportunity to work with you. If you're satisfied with our work, we'd appreciate a review or referral.

For any warranty issues or follow-up questions, please contact us anytime.

Thank you for choosing us!

Best regards,
[Your Name]
[Your Company]`
        }
    };

    // ===========================================
    // EVENT BINDING
    // ===========================================

    function bindEvents() {
        // New email button
        $(document).on('click', '#pi-new-email-btn', () => {
            showComposer();
        });

        // Template selector
        $(document).on('change', '#pi-email-template', function() {
            const template = $(this).val();
            if (template && emailTemplates[template]) {
                $('#pi-email-subject').val(emailTemplates[template].subject);
                $('#pi-email-body').val(emailTemplates[template].body);
            }
        });

        // Send email
        $(document).on('click', '#pi-send-email-btn', async (e) => {
            e.preventDefault();
            await sendEmail();
        });

        // Save draft
        $(document).on('click', '#pi-save-draft-btn', async () => {
            await saveDraft();
        });

        // Filter buttons
        $(document).on('click', '.pi-filter-btn', function() {
            $('.pi-filter-btn').removeClass('active');
            $(this).addClass('active');
            const filter = $(this).data('filter');
            renderEmailList(filter);
        });

        // Email item click
        $(document).on('click', '.pi-email-item', function() {
            const emailId = $(this).data('email-id');
            showEmailView(emailId);
        });

        // Back to list
        $(document).on('click', '#pi-back-to-list', () => {
            $('#pi-email-view').hide();
            $('#pi-email-composer').show();
        });

        // File attachment
        $(document).on('change', '#pi-email-attachments', function() {
            const files = Array.from(this.files);
            const list = $('#pi-attachments-list');
            list.empty();
            files.forEach(file => {
                list.append(`<div class="pi-attachment-item">${escapeHtml(file.name)} (${formatFileSize(file.size)})</div>`);
            });
        });

        // Reply button
        $(document).on('click', '#pi-reply-btn', function() {
            const emailId = $(this).closest('.pi-email-view').data('email-id');
            const email = emailHistory.find(e => e.id === emailId);
            if (email) {
                $('#pi-email-to').val(email.from);
                $('#pi-email-subject').val(`Re: ${email.subject}`);
                $('#pi-email-body').val(`\n\n--- Original Message ---\nFrom: ${email.from}\nDate: ${email.date}\nSubject: ${email.subject}\n\n${email.body}`);
                showComposer();
            }
        });
    }

    function showComposer() {
        $('#pi-email-view').hide();
        $('#pi-email-composer').show();
        $('#pi-email-form')[0].reset();
        $('#pi-attachments-list').empty();
    }

    // ===========================================
    // API FUNCTIONS
    // ===========================================

    async function loadEmailHistory() {
        try {
            const url = `${PI_Job.rest_base}/communications?job_id=${jobId}`;
            console.log('[Communications] Loading emails from:', url);

            const resp = await fetch(url, {
                headers: { 'X-WP-Nonce': PI_Job.nonce }
            });

            if (!resp.ok) {
                const error = await resp.text();
                throw new Error(`Failed to load emails: ${resp.status} ${error}`);
            }

            emailHistory = await resp.json();
            console.log('[Communications] Loaded', emailHistory.length, 'emails');
            renderEmailList('all');
        } catch (err) {
            console.error('[Communications] Error loading emails:', err);
            $('#pi-email-list').html('<div class="pi-communications-error">Failed to load email history</div>');
        }
    }

    async function sendEmail() {
        const fromName = $('#pi-email-from-name').val().trim();
        const from = $('#pi-email-from').val().trim();
        const to = $('#pi-email-to').val().trim();
        const cc = $('#pi-email-cc').val().trim();
        const subject = $('#pi-email-subject').val().trim();
        const body = $('#pi-email-body').val().trim();
        const template = $('#pi-email-template').val();

        // Validation
        if (!from || !to || !subject || !body) {
            showToast('Please fill in all required fields', 'error');
            return;
        }

        if (!isValidEmail(from) || !isValidEmail(to)) {
            showToast('Please enter valid email addresses', 'error');
            return;
        }

        // Collect attachments
        const files = document.getElementById('pi-email-attachments').files;
        const attachments = await Promise.all(
            Array.from(files).map(async file => ({
                name: file.name,
                type: file.type,
                size: file.size,
                data: await fileToBase64(file)
            }))
        );

        // Show loading state FIRST (before any validation)
        const $btn = $('#pi-send-email-btn');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="pi-spinner"></span> Sending...');

        // Emergency fallback: extract job_id from URL if still undefined
        let finalJobId = jobId;
        if (!finalJobId) {
            const urlMatch = window.location.pathname.match(/\/job\/(\d+)\//);
            if (urlMatch) {
                finalJobId = parseInt(urlMatch[1]);
                console.warn('[Communications] Emergency job_id extraction:', finalJobId);
            }
        }
        
        if (!finalJobId) {
            alert('Error: Could not determine job ID. Please refresh the page.');
            $btn.prop('disabled', false).html(originalText);
            return;
        }

        const emailData = {
            job_id: finalJobId,
            from_name: fromName || getDefaultFromName(),
            from: from,
            to: to,
            cc: cc || null,
            subject: subject,
            body: body,
            template: template || null,
            attachments: attachments
        };

        console.log('[Communications] Sending email:', emailData);

        try {
            const resp = await fetch(`${PI_Job.rest_base}/communications/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': PI_Job.nonce
                },
                body: JSON.stringify(emailData)
            });

            if (!resp.ok) {
                const error = await resp.text();
                throw new Error(`Failed to send email: ${resp.status} ${error}`);
            }

            const result = await resp.json();
            console.log('[Communications] Email sent:', result);

            showToast('Email sent successfully!', 'success');
            
            // Clear form
            $('#pi-email-form')[0].reset();
            $('#pi-attachments-list').empty();
            
            // Reload email list
            await loadEmailHistory();

        } catch (err) {
            console.error('[Communications] Error sending email:', err);
            showToast('Failed to send email: ' + err.message, 'error');
        } finally {
            $btn.prop('disabled', false).html(originalText);
        }
    }

    async function saveDraft() {
        const draftData = {
            job_id: jobId,
            from_name: $('#pi-email-from-name').val(),
            from: $('#pi-email-from').val(),
            to: $('#pi-email-to').val(),
            cc: $('#pi-email-cc').val(),
            subject: $('#pi-email-subject').val(),
            body: $('#pi-email-body').val(),
            template: $('#pi-email-template').val()
        };

        try {
            const resp = await fetch(`${PI_Job.rest_base}/communications/draft`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': PI_Job.nonce
                },
                body: JSON.stringify(draftData)
            });

            if (!resp.ok) throw new Error('Failed to save draft');

            showToast('Draft saved', 'success');
        } catch (err) {
            console.error('[Communications] Error saving draft:', err);
            showToast('Failed to save draft', 'error');
        }
    }

    // ===========================================
    // UI RENDERING FUNCTIONS
    // ===========================================

    function renderEmailList(filter = 'all') {
        const $list = $('#pi-email-list');
        
        if (!emailHistory.length) {
            $list.html('<div class="pi-communications-empty">No emails yet</div>');
            return;
        }

        let filtered = emailHistory;
        if (filter === 'sent') {
            filtered = emailHistory.filter(e => e.type === 'sent');
        } else if (filter === 'received') {
            filtered = emailHistory.filter(e => e.type === 'received');
        }

        const html = filtered.map(email => `
            <div class="pi-email-item ${email.type}" data-email-id="${email.id}">
                <div class="pi-email-item-icon">
                    ${email.type === 'sent' ? 
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>' :
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>'
                    }
                </div>
                <div class="pi-email-item-content">
                    <div class="pi-email-item-header">
                        <span class="pi-email-from">${escapeHtml(email.type === 'sent' ? 'To: ' + email.to : 'From: ' + (email.from_name ? email.from_name + ' <' + email.from + '>' : email.from))}</span>
                        <span class="pi-email-date">${formatDate(email.date)}</span>
                    </div>
                    <div class="pi-email-subject">${escapeHtml(email.subject)}</div>
                    <div class="pi-email-preview">${escapeHtml(email.body.substring(0, 100))}...</div>
                </div>
                ${email.has_attachments ? '<div class="pi-email-attachment-icon">📎</div>' : ''}
            </div>
        `).join('');

        $list.html(html);
    }

    function showEmailView(emailId) {
        const email = emailHistory.find(e => e.id === emailId);
        if (!email) return;

        const viewHtml = `
            <div class="pi-email-detail" data-email-id="${emailId}">
                <div class="pi-email-detail-header">
                    <h4>${escapeHtml(email.subject)}</h4>
                    <div class="pi-email-meta">
                        <div><strong>From:</strong> ${escapeHtml(email.from_name ? email.from_name + ' <' + email.from + '>' : email.from)}</div>
                        <div><strong>To:</strong> ${escapeHtml(email.to)}</div>
                        ${email.cc ? `<div><strong>Cc:</strong> ${escapeHtml(email.cc)}</div>` : ''}
                        <div><strong>Date:</strong> ${formatDateTime(email.date)}</div>
                    </div>
                </div>
                <div class="pi-email-detail-body">
                    <pre>${escapeHtml(email.body)}</pre>
                </div>
                ${email.has_attachments ? `
                    <div class="pi-email-attachments">
                        <strong>Attachments:</strong>
                        <div class="pi-email-attachment-list">
                            ${email.attachments.map(att => `
                                <a href="${att.url}" class="pi-attachment-link" download target="_blank">${escapeHtml(att.name)}</a>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;

        $('#pi-email-view-content').html(viewHtml);
        $('#pi-email-composer').hide();
        $('#pi-email-view').show();
    }

    // ===========================================
    // UTILITY FUNCTIONS
    // ===========================================

    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="pi-toast pi-toast-${type}">
                <span>${escapeHtml(message)}</span>
            </div>
        `);
        $('body').append(toast);
        toast.addClass('show');
        setTimeout(() => toast.remove(), 3000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function formatDateTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleString('en-GB', { 
            day: 'numeric', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    }

    async function loadSmtpSettings() {
        console.log('[SMTP] Loading SMTP settings from API...');
        
        try {
            const response = await fetch(`${PI_Job.rest_base}/user/smtp`, {
                headers: { 'X-WP-Nonce': PI_Job.nonce }
            });
            
            if (response.ok) {
                const data = await response.json();
                console.log('[SMTP] Settings loaded:', data);
                userSmtpSettings = data;
                updateSmtpStatusUI();
                return userSmtpSettings;
            } else {
                console.error('[SMTP] API returned error:', response.status);
                // Set empty settings so UI shows "not configured" instead of "loading"
                userSmtpSettings = { enabled: false };
                updateSmtpStatusUI();
                return null;
            }
        } catch (error) {
            console.error('[SMTP] Failed to load settings:', error);
            // Set empty settings so UI shows "not configured" instead of "loading"
            userSmtpSettings = { enabled: false };
            updateSmtpStatusUI();
            return null;
        }
    }

    function updateSmtpStatusUI() {
        const $status = $('#pi-from-status');
        const $fromField = $('#pi-email-from');
        
        // Check if elements exist (UI might not be rendered yet)
        if ($status.length === 0) {
            console.log('[SMTP] Status element not found, UI not rendered yet');
            return;
        }
        
        console.log('[SMTP] Updating UI with settings:', userSmtpSettings);
        
        // Determine the status to show
        let statusHtml = '';
        let defaultEmail = 'hello@planningindex.co.uk';
        let buttonText = 'Configure Email';
        
        if (!userSmtpSettings || !userSmtpSettings.enabled) {
            // No custom SMTP configured (either default state or API returned no settings)
            statusHtml = `
                <span class="pi-status-text pi-status-default">via hello@planningindex.co.uk</span>
                <button type="button" class="pi-btn-smtp-config" id="pi-configure-smtp-btn">Use My Email</button>
            `;
        } else {
            // Custom SMTP configured
            statusHtml = `
                <span class="pi-status-text pi-status-custom">✓ Using ${escapeHtml(userSmtpSettings.from_email || 'your email')}</span>
                <button type="button" class="pi-btn-smtp-config" id="pi-configure-smtp-btn">Email Settings</button>
            `;
            defaultEmail = userSmtpSettings.from_email || 'hello@planningindex.co.uk';
        }
        
        // Update the status HTML
        $status.html(statusHtml);
        
        // Pre-fill from email if not already set
        if ($fromField.length > 0 && (!$fromField.val() || $fromField.val() === 'hello@planningindex.co.uk')) {
            $fromField.val(defaultEmail);
        }
    }
    
    function renderSmtpModal() {
        console.log('[SMTP] ==================================================');
        console.log('[SMTP] renderSmtpModal() called - Opening modal');
        console.log('[SMTP] ==================================================');
        
        // Set lock immediately - prevents ANY closing for 1 second
        piSmtpModalLocked = true;
        piSmtpModalOpenTime = Date.now();
        console.log('[SMTP] Modal LOCKED at', piSmtpModalOpenTime);
        
        // Release lock after 1 second
        setTimeout(() => {
            piSmtpModalLocked = false;
            console.log('[SMTP] Modal UNLOCKED - can now be closed via overlay click');
        }, 1000);
        
        // Close any existing modal first
        const existingModal = $('#pi-smtp-modal');
        if (existingModal.length) {
            console.log('[SMTP] Removing existing modal');
            existingModal.remove();
        }
        
        const settings = userSmtpSettings || {};
        console.log('[SMTP] Current settings:', JSON.stringify(settings));
        
        const modalHtml = `
            <div class="pi-modal-overlay" id="pi-smtp-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;">
                <div class="pi-modal" style="background:#fff;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);width:100%;max-width:520px;max-height:90vh;overflow:hidden;">
                    <div class="pi-modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #e5e7eb;background:#ffffff;">
                        <h3 style="margin:0;font-size:18px;font-weight:600;color:#111827;">${settings.enabled ? 'Update' : 'Configure'} Your Email Settings</h3>
                        <button type="button" class="pi-modal-close" id="pi-smtp-close" style="width:32px;height:32px;border:none;background:transparent;font-size:24px;color:#6b7280;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:6px;">&times;</button>
                    </div>
                    <div class="pi-modal-body" style="padding:24px;overflow-y:auto;max-height:calc(90vh - 80px);">
                        <div class="pi-smtp-intro" style="margin-bottom:20px;padding:12px;background:#f0f9ff;border-left:4px solid #0ea5e9;border-radius:4px;">
                            <p style="margin:0 0 8px 0;color:#0369a1;">Configure your own email to send directly from your address (e.g., john@goodbuilders.com).</p>
                            <div class="pi-smtp-note" style="font-size:12px;color:#0c4a6e;">
                                <strong>Note:</strong> You need your email provider's SMTP settings. 
                                <a href="https://fluentsmtp.com/docs/set-up-fluent-smtp-with-any-host-or-mailer/" target="_blank" style="color:#0284c7;text-decoration:underline;">Learn more about SMTP settings</a>
                            </div>
                        </div>
                        
                        <form id="pi-smtp-form">
                            <div class="pi-form-row" style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;font-weight:500;color:#374151;">From Name:</label>
                                <input type="text" id="pi-smtp-from-name" value="${escapeHtml(settings.from_name || PI_Job.user_name || '')}" placeholder="Your Name" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                            </div>
                            
                            <div class="pi-form-row" style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;font-weight:500;color:#374151;">From Email:</label>
                                <input type="email" id="pi-smtp-from-email" value="${escapeHtml(settings.from_email || PI_Job.user_email || '')}" placeholder="you@yourcompany.com" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                            </div>
                            
                            <div class="pi-form-row" style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;font-weight:500;color:#374151;">SMTP Host:</label>
                                <input type="text" id="pi-smtp-host" value="${escapeHtml(settings.host || '')}" placeholder="smtp.gmail.com or mail.yourhost.com" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                                <span class="pi-field-hint" style="display:block;margin-top:4px;font-size:11px;color:#6b7280;">e.g., smtp.gmail.com, smtp.office365.com, mail.yourdomain.com</span>
                            </div>
                            
                            <div class="pi-form-row" style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;font-weight:500;color:#374151;">SMTP Port:</label>
                                <input type="number" id="pi-smtp-port" value="${settings.port || 587}" placeholder="587" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                                <span class="pi-field-hint" style="display:block;margin-top:4px;font-size:11px;color:#6b7280;">Usually 587 (TLS) or 465 (SSL)</span>
                            </div>
                            
                            <div class="pi-form-row" style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;font-weight:500;color:#374151;">Encryption:</label>
                                <select id="pi-smtp-encryption" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;background:#fff;">
                                    <option value="tls" ${settings.encryption === 'tls' ? 'selected' : ''}>TLS (Recommended)</option>
                                    <option value="ssl" ${settings.encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                                    <option value="" ${!settings.encryption ? 'selected' : ''}>None</option>
                                </select>
                            </div>
                            
                            <div class="pi-form-row" style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;font-weight:500;color:#374151;">SMTP Username:</label>
                                <input type="text" id="pi-smtp-username" value="${escapeHtml(settings.username || '')}" placeholder="Usually your email address" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                            </div>
                            
                            <div class="pi-form-row" style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;font-weight:500;color:#374151;">SMTP Password:</label>
                                <input type="password" id="pi-smtp-password" placeholder="${settings.enabled ? 'Leave blank to keep existing password' : 'Your email password or app password'}" ${settings.enabled ? '' : 'required'} style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                                <span class="pi-field-hint" style="display:block;margin-top:4px;font-size:11px;color:#6b7280;">For Gmail/Office365, use an App Password. <a href="https://support.google.com/accounts/answer/185833" target="_blank" style="color:#0284c7;">Learn how</a></span>
                            </div>
                            
                            <div class="pi-form-actions" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:20px;padding-top:15px;border-top:1px solid #e5e7eb;justify-content:flex-start;align-items:center;">
                                ${settings.enabled ? '<button type="button" class="pi-btn pi-btn-danger" id="pi-smtp-remove" style="padding:6px 12px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;cursor:pointer;font-size:13px;white-space:nowrap;margin-left:auto;">Remove My Email</button>' : ''}
                                <button type="button" class="pi-btn pi-btn-secondary" id="pi-smtp-test" style="padding:6px 12px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:13px;white-space:nowrap;">Test Connection</button>
                                <button type="button" class="pi-btn pi-btn-primary" id="pi-smtp-save" style="padding:6px 12px;background:#0ea5e9;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;white-space:nowrap;">Save Settings</button>
                            
                            </div>
                        </form>
                        
                        <div class="pi-smtp-test-results" id="pi-smtp-test-results" style="display:none;margin-top:15px;padding:12px;border-radius:6px;font-size:13px;"></div>
                    </div>
                </div>
            </div>
        `;
        
        // Add to document.body directly
        $(document.body).append(modalHtml);
        
        // Force the modal overlay to be visible
        const $modal = $('#pi-smtp-modal');
        $modal.css({
            'display': 'flex',
            'visibility': 'visible',
            'opacity': '1',
            'position': 'fixed',
            'top': '0',
            'left': '0',
            'right': '0',
            'bottom': '0',
            'z-index': '100000'
        });
        
        // Force the inner modal container to be visible
        const $modalInner = $modal.find('.pi-modal');
        $modalInner.css({
            'display': 'block',
            'visibility': 'visible',
            'opacity': '1',
            'background': '#ffffff',
            'position': 'relative',
            'z-index': '100001'
        });
        
        console.log('[SMTP] Modal HTML appended, length:', modalHtml.length);
        console.log('[SMTP] Modal element exists:', $modal.length > 0);
        console.log('[SMTP] Modal inner exists:', $modalInner.length > 0);
        console.log('[SMTP] Modal display:', $modal.css('display'));
        console.log('[SMTP] Modal inner display:', $modalInner.css('display'));
        console.log('[SMTP] Modal inner visibility:', $modalInner.css('visibility'));
        console.log('[SMTP] Modal inner opacity:', $modalInner.css('opacity'));
        
        // Unbind any existing handlers first to prevent duplicates
        $(document).off('click.pi-smtp-close').off('click.pi-smtp-overlay').off('click.pi-smtp-save').off('click.pi-smtp-test').off('click.pi-smtp-remove');
        
        // Event handlers with namespaced events
        $(document).on('click.pi-smtp-close', '#pi-smtp-close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#pi-smtp-modal').remove();
            console.log('[SMTP] Modal closed via X button');
        });
        
        $(document).on('click.pi-smtp-overlay', '#pi-smtp-modal', function(e) {
            // Only close if clicking the overlay itself AND lock has expired
            const lockElapsed = Date.now() - piSmtpModalOpenTime;
            const isLocked = piSmtpModalLocked && lockElapsed < 1000;
            
            if (e.target.id === 'pi-smtp-modal' && !isLocked) {
                $('#pi-smtp-modal').remove();
                console.log('[SMTP] Modal closed via overlay click (lock elapsed:', lockElapsed + 'ms)');
            } else if (isLocked) {
                console.log('[SMTP] BLOCKED: Modal is locked (elapsed:', lockElapsed + 'ms)');
            }
        });
        
        $(document).on('click', '#pi-smtp-save', function(e) {
            e.preventDefault();
            saveSmtpSettings();
        });
        
        $(document).on('click', '#pi-smtp-test', function(e) {
            e.preventDefault();
            testSmtpConnection();
        });
        
        $(document).on('click', '#pi-smtp-remove', function(e) {
            e.preventDefault();
            removeSmtpSettings();
        });
    }

    async function saveSmtpSettings() {
        const $btn = $('#pi-smtp-save');
        const settings = {
            from_name: $('#pi-smtp-from-name').val(),
            from_email: $('#pi-smtp-from-email').val(),
            host: $('#pi-smtp-host').val(),
            port: parseInt($('#pi-smtp-port').val()) || 587,
            encryption: $('#pi-smtp-encryption').val(),
            username: $('#pi-smtp-username').val(),
            password: $('#pi-smtp-password').val(),
            enabled: true
        };
        
        // Validate
        if (!settings.from_email || !settings.host || !settings.username || !settings.password) {
            alert('Please fill in all required fields');
            return;
        }
        
        $btn.prop('disabled', true).text('Saving...');
        
        const url = `${PI_Job.rest_base}/user/smtp`;
        console.log('[SMTP] Saving to URL:', url);
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': PI_Job.nonce
                },
                body: JSON.stringify(settings)
            });
            
            console.log('[SMTP] Save response status:', response.status);
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error('[SMTP] Server returned HTML (not JSON):', text.substring(0, 200));
                alert('Server error. Check console for details.');
                return;
            }
            
            if (response.ok) {
                alert('Email settings saved successfully!');
                $('#pi-smtp-modal').remove();
                await loadSmtpSettings();
                // Update the from field in composer
                $('#pi-email-from').val(settings.from_email);
                $('#pi-email-from-name').val(settings.from_name);
            } else {
                const error = await response.json();
                alert(error.message || 'Failed to save settings');
            }
        } catch (error) {
            console.error('[SMTP] Save failed:', error);
            alert('Failed to save settings');
        } finally {
            $btn.prop('disabled', false).text('Save Settings');
        }
    }

    async function testSmtpConnection() {
        const $btn = $('#pi-smtp-test');
        const $results = $('#pi-smtp-test-results');
        
        // Get current form values
        const testSettings = {
            from_email: $('#pi-smtp-from-email').val(),
            from_name: $('#pi-smtp-from-name').val(),
            host: $('#pi-smtp-host').val(),
            port: parseInt($('#pi-smtp-port').val()) || 587,
            encryption: $('#pi-smtp-encryption').val(),
            username: $('#pi-smtp-username').val(),
            password: $('#pi-smtp-password').val()
        };
        
        // Validate required fields
        if (!testSettings.host || !testSettings.username || !testSettings.password) {
            $results.addClass('error').html('✗ Please fill in Host, Username, and Password first.').show();
            return;
        }
        
        $btn.prop('disabled', true).text('Testing...');
        $results.hide().removeClass('success error').html('');
        
        const url = `${PI_Job.rest_base}/user/smtp/test`;
        console.log('[SMTP] Testing connection to URL:', url);
        console.log('[SMTP] Testing with settings:', { ...testSettings, password: '***' });
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': PI_Job.nonce
                },
                body: JSON.stringify(testSettings)
            });
            
            console.log('[SMTP] Test response status:', response.status);
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error('[SMTP] Server returned HTML (not JSON):', text.substring(0, 200));
                $results.addClass('error').html('✗ Server error. Check console for details.').show();
                return;
            }
            
            const result = await response.json();
            
            if (response.ok) {
                $results.addClass('success').html(`✓ ${result.message || 'Connection successful!'}`).show();
            } else {
                $results.addClass('error').html(`✗ ${result.message || 'Connection failed. Check your settings.'}`).show();
            }
        } catch (error) {
            console.error('[SMTP] Test failed:', error);
            $results.addClass('error').html('✗ Network error. Please try again.').show();
        } finally {
            $btn.prop('disabled', false).text('Test Connection');
        }
    }
    
    async function removeSmtpSettings() {
        if (!confirm('Are you sure you want to remove your custom email settings? Emails will be sent from hello@planningindex.co.uk instead.')) {
            return;
        }
        
        try {
            const response = await fetch(`${PI_Job.rest_base}/user/smtp`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': PI_Job.nonce
                },
                body: JSON.stringify({ host: '', password: '' })
            });
            
            if (response.ok) {
                showToast('Custom email settings removed', 'success');
                $('#pi-smtp-modal').remove();
                userSmtpSettings = null;
                updateSmtpStatusUI();
                $('#pi-email-from').val('hello@planningindex.co.uk');
            }
        } catch (error) {
            console.error('[SMTP] Remove failed:', error);
            showToast('Failed to remove settings', 'error');
        }
    }

    // ===========================================
    // INITIALIZATION
    // ===========================================

    function initCommunicationsTab() {
        console.log('[Communications] Initializing tab...');
        renderCommunicationsInterface();
        
        // Show default status immediately (will show "via hello@planningindex.co.uk")
        updateSmtpStatusUI();
        
        // Then load actual settings from API and update UI
        loadSmtpSettings().then(() => {
            console.log('[Communications] SMTP settings loaded, UI updated');
        });
    }

    // Render when tab is activated
    $(document).on('click', '[data-job-tab="communications"]', () => {
        console.log('[Communications] Tab activated');
        setTimeout(initCommunicationsTab, 100);
    });
    
    // Handle SMTP config button click - use standard event delegation
    $(document).off('click', '#pi-configure-smtp-btn').on('click', '#pi-configure-smtp-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        console.log('[SMTP] Configure button clicked - opening modal');
        console.log('[SMTP] Event propagation stopped');
        
        // Small delay to ensure click event fully processed before opening modal
        setTimeout(() => {
            renderSmtpModal();
        }, 10);
        
        return false;
    });

    // Also try to render if we're already on the communications tab
    if ($('[data-job-tab="communications"]').hasClass('active')) {
        initCommunicationsTab();
    }

});
