/**
 * Planning Index - Lead Management Page
 * Professional CRM JavaScript for Construction
 * Version 5.0 - Integrated Ecosystem with Invoice Sync
 */

jQuery(($) => {
    'use strict';

    // ===========================================
    // CONFIGURATION
    // ===========================================
    const leadId = $('#pi-lead-single').data('lead-id');
    const planningAppId = $('#pi-lead-single').data('planning-app-id') || null;
    const endpoint = '/wp-json/pi/v1/leads/' + leadId;
    const workspaceEndpoint = '/wp-json/pi/v1/workspace';
    const invoicesEndpoint = '/wp-json/pi/v1/workspace/invoices';
    const nonce = PI_Lead.nonce;
    const workspaceUrl = PI_Lead.workspace_url;

    // Stage labels for the dropdown
    const stageLabels = {
        new_lead: 'New Lead',
        proposal_sent: 'Proposal Sent',
        contacted: 'Contacted',
        negotiation: 'Negotiation',
        won: 'Won'
    };

    // Local storage for tasks
    const TASKS_KEY = `pi_lead_tasks_${leadId}`;
    const STATS_KEY = 'pi_crm_stats';

    // Current lead data cache
    let currentLeadData = null;
    let currentInvoice = null;
    let currentStage = 'new_lead';

    // ===========================================
    // UTILITY FUNCTIONS
    // ===========================================
    
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP'
        }).format(amount || 0);
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    function daysBetween(date1, date2) {
        const oneDay = 24 * 60 * 60 * 1000;
        return Math.round(Math.abs((new Date(date1) - new Date(date2)) / oneDay));
    }

    function showToast(message, type = 'success') {
        const icons = {
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };

        const toast = $(`
            <div class="pi-toast ${type}">
                <span class="pi-toast-icon">${icons[type]}</span>
                <span class="pi-toast-message">${escapeHtml(message)}</span>
                <button class="pi-toast-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        `);

        $('#pi-toast-container').append(toast);

        toast.find('.pi-toast-close').on('click', () => {
            toast.fadeOut(200, () => toast.remove());
        });

        setTimeout(() => {
            toast.fadeOut(200, () => toast.remove());
        }, 5000);
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
    // PRICING CALCULATION HELPERS
    // ===========================================
    
    /**
     * Calculate pricing totals from an array of pricing items
     * @param {Array} pricingDetails - Array of {desc, qty, price}
     * @returns {Object} {subtotal, vat, grandTotal}
     */
    function calculatePricingTotals(pricingDetails) {
        const items = Array.isArray(pricingDetails) ? pricingDetails : [];
        const subtotal = items.reduce((sum, item) => {
            const price = parseFloat(item.price) || 0;
            const qty = parseInt(item.qty, 10) || 1;
            return sum + (price * qty);
        }, 0);
        const vat = subtotal * 0.20;
        const grandTotal = subtotal + vat;
        return { subtotal, vat, grandTotal };
    }

    /**
     * Get current pricing items from the pricing table
     * @returns {Array} Array of {desc, qty, price}
     */
    function getCurrentPricingFromTable() {
        const pricing = [];
        $('#pi-pricing-table tbody tr').each((i, row) => {
            const desc = $(row).find('.pi-desc-input').val() || '';
            const qty = parseInt($(row).find('.pi-qty-input').val(), 10) || 1;
            const price = parseFloat($(row).find('.pi-price-input').val()) || 0;
            pricing.push({ desc, qty, price });
        });
        return pricing;
    }

    /**
     * Get current totals from the pricing table
     * @returns {Object} {subtotal, vat, grandTotal}
     */
    function getCurrentTotalsFromTable() {
        const pricing = getCurrentPricingFromTable();
        return calculatePricingTotals(pricing);
    }

    // ===========================================
    // TAB NAVIGATION
    // ===========================================
    
    $('.pi-tab-btn').on('click', function() {
        const tabId = $(this).data('tab');
        
        $('.pi-tab-btn').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        
        $('.pi-tab-panel').removeClass('active');
        $(`#tab-${tabId}`).addClass('active');
    });

    // ===========================================
    // DATA LOADING
    // ===========================================
    
    async function loadData() {
        try {
            const resp = await fetch(endpoint, { 
                headers: { 'X-WP-Nonce': nonce } 
            });
            
            if (!resp.ok) throw new Error('Failed to load data');
            
            const data = await resp.json();
            currentLeadData = data;
            
            // Populate customer form fields
            $('input[name="customer_name"]').val(data.customer_name || '');
            $('input[name="customer_phone"]').val(data.customer_phone || '');
            $('input[name="customer_email"]').val(data.customer_email || '');
            $('input[name="customer_address"]').val(data.customer_address || data.address || '');
            $('textarea[name="notes"]').val(data.notes || '');
            
            // Stage dropdown - update custom dropdown
            const stage = data.stage || 'new_lead';
            updateStageDropdown(stage);
            
            // Update header
            $('#pi-lead-ref').text(data.ref || 'No Reference');
            $('#pi-lead-address').text(data.address || 'Unknown Address');
            $('#pi-date-received').text(formatDate(data.date_received));
            $('#pi-map-link').attr('href', `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(data.address || '')}`);
            $('#pi-original-link').attr('href', data.info_url || '#');
            
            // Calculate KPIs
            updateKPIs(data);
            
            // Load pricing table
            loadPricingTable(data.pricing_details || []);
            
            // Load history/timeline
            loadTimeline(data.status_history || []);
            
            // Load attachments
            loadAttachments(data.attachments || {});
            
            // Load planning overview
            loadPlanningOverview(data);
            
            // Load invoice linked to this lead
            await loadLinkedInvoice();
            
            // Handle invoice section
            loadInvoiceSection(data);
            
        } catch (err) {
            console.error('Load failed:', err);
            showToast('Failed to load lead data. Please refresh the page.', 'error');
        }
    }

    /**
     * Load invoice linked to this lead
     */
    async function loadLinkedInvoice() {
        try {
            const resp = await fetch(invoicesEndpoint, {
                headers: { 'X-WP-Nonce': nonce }
            });
            if (!resp.ok) return;
            
            const invoices = await resp.json();
            // Find invoice linked to this lead (by lead_id or planning_app_id)
            currentInvoice = invoices.find(inv => 
                inv.lead_id === leadId || 
                inv.lead_id === planningAppId ||
                inv.pi_lead_id === leadId
            ) || null;
        } catch (err) {
            console.warn('Could not load invoices:', err);
            currentInvoice = null;
        }
    }

    /**
     * Update KPI cards
     */
    function updateKPIs(data) {
        const totals = calculatePricingTotals(data.pricing_details);
        $('#pi-total-value').text(formatCurrency(totals.grandTotal));
        
        // Lead score
        let score = 0;
        if (data.customer_name) score += 20;
        if (data.customer_email) score += 20;
        if (data.customer_phone) score += 20;
        if ((data.pricing_details || []).length > 0) score += 25;
        if (data.notes) score += 15;
        
        const scoreLabel = score >= 80 ? 'Hot' : score >= 50 ? 'Warm' : 'Cold';
        $('#pi-lead-score').text(`${score}% ${scoreLabel}`);
        
        // Days in pipeline - use added_to_workspace_at (actual time in pipeline)
        if (data.added_to_workspace_at) {
            const days = daysBetween(new Date(), new Date(data.added_to_workspace_at));
            $('#pi-days-pipeline').text(days);
        } else if (data.date_received) {
            // Fallback to date_received for older leads without added_to_workspace_at
            const days = daysBetween(new Date(), new Date(data.date_received));
            $('#pi-days-pipeline').text(days);
        }
        
        // Interactions count
        $('#pi-interactions').text((data.status_history || []).length);
    }

    /**
     * Load pricing table
     */
    function loadPricingTable(items) {
        const $tbody = $('#pi-pricing-table tbody');
        $tbody.empty();
        
        if (items.length === 0) {
            addPricingRow('', 1, 0, 0);
        } else {
            items.forEach((item, idx) => addPricingRow(item.desc, item.qty, item.price, idx));
        }
        
        calculateTotals();
    }

    /**
     * Add pricing row
     */
    function addPricingRow(desc = '', qty = 1, price = 0, idx) {
        const row = `
            <tr data-idx="${idx}">
                <td>
                    <input type="text" value="${escapeHtml(desc)}" placeholder="e.g., Labour, Materials, Site Clearance..." class="pi-desc-input" />
                </td>
                <td>
                    <input type="number" value="${qty}" min="1" class="pi-qty-input" />
                </td>
                <td>
                    <input type="number" value="${price}" min="0" step="0.01" class="pi-price-input" />
                </td>
                <td class="pi-row-total">${formatCurrency(qty * price)}</td>
                <td>
                    <button type="button" class="pi-remove-row" title="Remove item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </td>
            </tr>
        `;
        $('#pi-pricing-table tbody').append(row);
    }

    /**
     * Calculate and update totals
     */
    function calculateTotals() {
        let subtotal = 0;
        
        $('#pi-pricing-table tbody tr').each((i, row) => {
            const qty = parseFloat($(row).find('.pi-qty-input').val()) || 0;
            const price = parseFloat($(row).find('.pi-price-input').val()) || 0;
            const rowTotal = qty * price;
            
            $(row).find('.pi-row-total').text(formatCurrency(rowTotal));
            subtotal += rowTotal;
        });
        
        const vat = subtotal * 0.20;
        const grandTotal = subtotal + vat;
        
        $('#pi-subtotal').text(formatCurrency(subtotal));
        $('#pi-vat').text(formatCurrency(vat));
        $('#pi-grand-total').html(`<strong>${formatCurrency(grandTotal)}</strong>`);
        $('#pi-total-value').text(formatCurrency(grandTotal));
        
        // Update invoice preview amount if visible
        if (currentInvoice) {
            $('#pi-invoice-amount').text(formatCurrency(grandTotal));
        }
    }

    // Update totals on input change and auto-sync to invoice after debounce
    let pricingSyncTimeout = null;
    
    $(document).on('input', '.pi-qty-input, .pi-price-input, .pi-desc-input', function() {
        calculateTotals();
        
        // Auto-sync to invoice after user stops typing (debounced)
        if (currentInvoice) {
            clearTimeout(pricingSyncTimeout);
            pricingSyncTimeout = setTimeout(async () => {
                console.log('[PI Auto-Sync] Debounced pricing sync triggered');
                try {
                    await syncPricingToInvoice();
                    // Update invoice section to show sync status
                    if (currentLeadData) {
                        loadInvoiceSection(currentLeadData);
                    }
                } catch (err) {
                    console.error('[PI Auto-Sync] Failed:', err);
                }
            }, 1500); // 1.5 second debounce
        }
    });

    /**
     * Format field names to be human readable (remove underscores, capitalize)
     */
    function formatFieldName(fieldName) {
        if (!fieldName) return '';
        return fieldName
            .replace(/_/g, ' ')
            .replace(/\b\w/g, char => char.toUpperCase());
    }

    /**
     * Load timeline with pagination (show last 10 by default)
     */
    let timelineExpanded = false;
    const TIMELINE_LIMIT = 10;

    function loadTimeline(history) {
        const $timeline = $('#pi-history-list');
        $timeline.empty();
        
        if (history.length === 0) {
            $timeline.html(`
                <div class="pi-empty-state" style="padding:24px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px;color:#d1d5db;margin-bottom:12px;">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    <p style="margin:0;font-size:14px;color:#6b7280;">No activity yet</p>
                </div>
            `);
            return;
        }
        
        // Format entries to remove underscores from field names
        const formattedHistory = history.map(entry => {
            // Replace common patterns like "pricing_details updated" with "Pricing Details updated"
            return entry.replace(/(\w+_\w+)/g, match => formatFieldName(match));
        });
        
        // Determine how many to show - REVERSED: latest first
        const itemsToShow = timelineExpanded ? formattedHistory : formattedHistory.slice(-TIMELINE_LIMIT);
        const hasMore = formattedHistory.length > TIMELINE_LIMIT && !timelineExpanded;
        const hiddenCount = formattedHistory.length - TIMELINE_LIMIT;

        // Reverse the array so latest items appear at the top
        const reversedItems = [...itemsToShow].reverse();

        // Render timeline items
        reversedItems.forEach(entry => {
            const isStatusChange = entry.toLowerCase().includes('moved') || entry.toLowerCase().includes('status');
            $timeline.append(`
                <div class="pi-timeline-item ${isStatusChange ? 'status-change' : 'note'}">
                    <div class="pi-timeline-dot"></div>
                    <div class="pi-timeline-content">${escapeHtml(entry)}</div>
                </div>
            `);
        });
        // Add "See More" button if there are hidden items
        if (hasMore) {
            $timeline.append(`
                <button class="pi-timeline-see-more" id="pi-timeline-expand">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 8 12 12 14 14"/>
                    </svg>
                    Show ${hiddenCount} older ${hiddenCount === 1 ? 'activity' : 'activities'}
                </button>
            `);
        }
    }

    // Handle "See More" click for timeline
    $(document).on('click', '#pi-timeline-expand', function() {
        timelineExpanded = true;
        if (currentLeadData && currentLeadData.status_history) {
            loadTimeline(currentLeadData.status_history);
        }
    });

    /**
     * Load attachments
     */
    function loadAttachments(attachments) {
        const $list = $('#pi-attachments-list');
        $list.empty();
        
        const attachArray = Object.values(attachments);
        
        if (attachArray.length === 0) {
            return;
        }
        
        attachArray.forEach(attach => {
            const isImage = attach.mime_type && attach.mime_type.startsWith('image/');
            
            if (isImage) {
                $list.append(`
                    <a href="${escapeHtml(attach.guid)}" target="_blank" class="pi-attachment-item pi-attachment-link">
                        <img src="${escapeHtml(attach.guid)}" alt="${escapeHtml(attach.post_title)}" class="pi-attachment-thumb" />
                        <span class="pi-attachment-name">${escapeHtml(attach.post_title)}</span>
                    </a>
                `);
            } else {
                $list.append(`
                    <a href="${escapeHtml(attach.guid)}" target="_blank" class="pi-attachment-item pi-attachment-link">
                        <div class="pi-attachment-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>
                            </svg>
                        </div>
                        <span class="pi-attachment-name">${escapeHtml(attach.post_title)}</span>
                    </a>
                `);
            }
        });
    }

    /**
     * Load planning overview
     */
    function loadPlanningOverview(data) {
        const $overview = $('#pi-planning-overview');
        $overview.empty();
        
        $overview.append(`
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:20px;">
                <div>
                    <strong style="display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Reference</strong>
                    <span style="font-family:'JetBrains Mono',monospace;font-size:14px;color:#111827;">${escapeHtml(data.ref || 'N/A')}</span>
                </div>
                <div>
                    <strong style="display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Date Received</strong>
                    <span style="font-size:14px;color:#111827;">${formatDate(data.date_received)}</span>
                </div>
            </div>
            
            <div style="margin-bottom:20px;">
                <strong style="display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Property Address</strong>
                <span style="font-size:14px;color:#111827;">${escapeHtml(data.address || 'Unknown')}</span>
            </div>
            
            <div>
                <strong style="display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Full Description & Details</strong>
                <div class="pi-full-content">${data.full_content || '<p style="color:#9ca3af;margin:0;">No detailed description available.</p>'}</div>
            </div>
        `);
    }

    /**
     * Load proposal section with thumbnail preview or generate option
     * This is the main Documents tab content
     */
    function loadProposalSection(data) {
        const $section = $('#pi-proposal-section');
        const totals = calculatePricingTotals(data.pricing_details);
        
        if (currentInvoice) {
            // Proposal exists - show thumbnail preview with actions
            const invoiceAmount = parseFloat(currentInvoice.amount) || 0;
            const isSynced = Math.abs(invoiceAmount - totals.grandTotal) < 0.01;
            const syncBadge = isSynced 
                ? '<span class="pi-sync-badge synced"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg> Synced</span>' 
                : '<span class="pi-sync-badge out-of-sync"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Out of sync</span>';
            
            const pdfUrl = currentInvoice.pdf_url ? `${currentInvoice.pdf_url}?t=${Date.now()}` : '';
            
            $section.html(`
                <div class="pi-proposal-preview-card">
                    <div class="pi-proposal-header">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            Proposal #${currentInvoice.id}
                        </h3>
                        ${syncBadge}
                    </div>
                    
                    <div class="pi-proposal-content">
                        <div class="pi-proposal-thumbnail-wrapper">
                            ${pdfUrl ? `
                                <div class="pi-proposal-thumbnail" id="pi-pdf-thumbnail">
                                    <canvas id="pi-pdf-canvas"></canvas>
                                    <div class="pi-thumbnail-overlay">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        <span>PDF</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="pi-proposal-thumbnail pi-thumbnail-placeholder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                    <span>Generating...</span>
                                </div>
                            `}
                        </div>
                        
                        <div class="pi-proposal-info">
                            <div class="pi-proposal-details">
                                <div class="pi-detail-row">
                                    <span class="pi-detail-label">Amount</span>
                                    <span class="pi-detail-value">${formatCurrency(invoiceAmount)}</span>
                                </div>
                                <div class="pi-detail-row">
                                    <span class="pi-detail-label">Created</span>
                                    <span class="pi-detail-value">${currentInvoice.created || 'N/A'}</span>
                                </div>
                                <div class="pi-detail-row">
                                    <span class="pi-detail-label">Valid Until</span>
                                    <span class="pi-detail-value">${currentInvoice.valid_until || 'N/A'}</span>
                                </div>
                                ${!isSynced ? `
                                    <div class="pi-detail-row pi-sync-warning">
                                        <span class="pi-detail-label">Current Quote</span>
                                        <span class="pi-detail-value">${formatCurrency(totals.grandTotal)}</span>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="pi-proposal-actions">
                                ${pdfUrl ? `
                                    <a href="${pdfUrl}" target="_blank" class="pi-btn pi-btn-secondary">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                                        </svg>
                                        View PDF
                                    </a>
                                ` : ''}
                                ${pdfUrl ? `
                                    <button id="send-pingen-letter" class="pi-btn pi-btn-primary" data-invoice-id="${currentInvoice.id}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                        </svg>
                                        Send / Print Letter
                                    </button>
                                ` : ''}
                                <button id="pi-edit-proposal" class="pi-btn pi-btn-outline">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    Edit
                                </button>
                                <button id="pi-delete-proposal" class="pi-btn pi-btn-ghost pi-btn-danger">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                            
                            ${!isSynced ? `
                                <button id="pi-sync-proposal" class="pi-btn pi-btn-primary pi-btn-full">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                                    </svg>
                                    Sync with Current Quote (${formatCurrency(totals.grandTotal)})
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `);
            
            // Render PDF thumbnail using pdf.js if available
            if (pdfUrl && typeof pdfjsLib !== 'undefined') {
                renderPdfThumbnail(pdfUrl);
            }
        } else {
            // No proposal - show generation option
            $section.html(`
                <div class="pi-proposal-generate-card">
                    <div class="pi-proposal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                    </div>
                    <h3>Ready to Create a Proposal?</h3>
                    <p>Generate a professional proposal letter with your current pricing.</p>
                    
                    <div class="pi-proposal-summary">
                        <div class="pi-summary-row">
                            <span>Subtotal</span>
                            <span>${formatCurrency(totals.subtotal)}</span>
                        </div>
                        <div class="pi-summary-row">
                            <span>VAT (20%)</span>
                            <span>${formatCurrency(totals.vat)}</span>
                        </div>
                        <div class="pi-summary-row pi-summary-total">
                            <span>Total</span>
                            <span>${formatCurrency(totals.grandTotal)}</span>
                        </div>
                    </div>
                    
                    <button id="pi-generate-proposal-doc" class="pi-btn pi-btn-primary pi-btn-lg">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        Generate Proposal
                    </button>
                </div>
            `);
        }
    }
    
    /**
     * Render PDF thumbnail using pdf.js
     */
    async function renderPdfThumbnail(pdfUrl) {
        try {
            if (typeof pdfjsLib === 'undefined') {
                console.warn('[PI] pdf.js not loaded, cannot render thumbnail');
                return;
            }
            
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
            
            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            const pdf = await loadingTask.promise;
            const page = await pdf.getPage(1);
            
            const canvas = document.getElementById('pi-pdf-canvas');
            if (!canvas) return;
            
            const context = canvas.getContext('2d');
            const viewport = page.getViewport({ scale: 0.5 });
            
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            
            await page.render({ canvasContext: context, viewport }).promise;
            
            // Hide the overlay once rendered
            const overlay = document.querySelector('.pi-thumbnail-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        } catch (err) {
            console.error('[PI] PDF thumbnail render failed:', err);
        }
    }
    
    // Keep legacy function for backwards compatibility
    function loadInvoiceSection(data) {
        loadProposalSection(data);
    }

    // ===========================================
    // SYNC FUNCTIONS - ECOSYSTEM INTEGRATION
    // ===========================================

    /**
     * Sync pricing to invoice and regenerate PDF
     * This is the core function that ensures invoice amount matches lead pricing
     * Called automatically when pricing changes and on form save
     * @returns {Promise<Object>} The sync result from the server
     */
    async function syncPricingToInvoice() {
        if (!currentInvoice) {
            console.warn('syncPricingToInvoice: No current invoice to sync');
            return null;
        }
        
        const totals = getCurrentTotalsFromTable();
        const pricing = getCurrentPricingFromTable();
        const notes = $('textarea[name="notes"]').val() || '';
        
        // Skip if no meaningful amount
        if (totals.grandTotal <= 0 && pricing.length === 0) {
            console.log('[PI Sync] Skipping sync - no pricing data');
            return null;
        }
        
        console.log('[PI Sync] Syncing pricing to invoice:', {
            invoiceId: currentInvoice.id,
            leadId: leadId,
            amount: totals.grandTotal,
            subtotal: totals.subtotal,
            vat: totals.vat,
            pricingItems: pricing.length
        });
        
        try {
            const resp = await fetch(`${invoicesEndpoint}/sync_from_lead`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    invoice_id: currentInvoice.id,
                    lead_id: leadId,
                    pi_lead_id: leadId,
                    planning_app_id: planningAppId,
                    amount: totals.grandTotal,
                    subtotal: totals.subtotal,
                    vat: totals.vat,
                    pricing_details: pricing,
                    notes: notes
                })
            });
            
            if (!resp.ok) {
                const errorText = await resp.text();
                console.error('[PI Sync] Invoice sync failed:', errorText);
                throw new Error('Sync failed: ' + errorText);
            }
            
            const result = await resp.json();
            console.log('[PI Sync] Invoice synced successfully:', result);
            
            // Update local invoice data
            currentInvoice.amount = totals.grandTotal;
            currentInvoice.subtotal = totals.subtotal;
            currentInvoice.vat = totals.vat;
            if (result.pdf_url) {
                currentInvoice.pdf_url = result.pdf_url;
            }
            
            // Dispatch event for other pages (workspace, invoices page)
            dispatchCRMUpdate('invoice-synced', {
                invoiceId: currentInvoice.id,
                leadId: leadId,
                planningAppId: planningAppId,
                amount: totals.grandTotal,
                subtotal: totals.subtotal,
                vat: totals.vat,
                pdf_regenerated: result.pdf_regenerated || false
            });
            
            return result;
        } catch (err) {
            console.error('[PI Sync] Invoice sync failed:', err);
            throw err;
        }
    }

    /**
     * Sync estimated_value to lead meta for workspace display
     * This updates the lead's estimated_value which is shown on workspace cards
     * @param {number} grandTotal - The total amount including VAT
     */
    async function syncEstimatedValueToLead(grandTotal) {
        console.log('[PI Sync] Syncing estimated value to lead:', { leadId, grandTotal });
        
        try {
            const resp = await fetch(`${endpoint}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    estimated_value: grandTotal
                })
            });
            
            if (!resp.ok) {
                throw new Error('Failed to update estimated value');
            }
            
            console.log('[PI Sync] Estimated value synced to lead');
            
            // Update local CRM stats for cross-page consistency
            updateLocalCRMStats(grandTotal);
            
            // Dispatch event for workspace page to refresh
            dispatchCRMUpdate('lead-updated', {
                leadId: leadId,
                planningAppId: planningAppId,
                estimatedValue: grandTotal
            });
        } catch (err) {
            console.error('[PI Sync] Failed to sync estimated value:', err);
        }
    }

    /**
     * Dispatch CRM update event for cross-page sync
     * Uses both CustomEvent and localStorage for cross-tab communication
     * @param {string} eventType - The type of event (lead-updated, invoice-synced, etc.)
     * @param {Object} data - The event data
     */
    function dispatchCRMUpdate(eventType, data) {
        const eventData = { 
            type: eventType, 
            ...data, 
            timestamp: Date.now(),
            source: 'lead-single'
        };
        
        // Dispatch window event for same-page listeners
        window.dispatchEvent(new CustomEvent('pi:crm-update', { detail: eventData }));
        window.dispatchEvent(new CustomEvent(`pi:${eventType}`, { detail: eventData }));
        
        // Update localStorage for cross-tab sync
        localStorage.setItem('pi_crm_last_update', JSON.stringify(eventData));
        
        console.log('[PI Sync] CRM update dispatched:', eventType, data);
    }

    /**
     * Update local CRM stats in localStorage
     * These stats are read by workspace page and invoices page for header displays
     * @param {number} currentLeadValue - The current lead's value
     */
    function updateLocalCRMStats(currentLeadValue) {
        try {
            const stats = JSON.parse(localStorage.getItem(STATS_KEY) || '{}');
            stats.lastUpdatedBy = 'lead-single';
            stats.lastUpdatedAt = Date.now();
            stats.lastLeadId = leadId;
            stats.lastLeadValue = currentLeadValue || 0;
            localStorage.setItem(STATS_KEY, JSON.stringify(stats));
        } catch (err) {
            console.warn('[PI Sync] Failed to update CRM stats:', err);
        }
    }

    // ===========================================
    // FORM HANDLERS
    // ===========================================
    
    async function updateData(data) {
        try {
            const resp = await fetch(`${endpoint}/update`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-WP-Nonce': nonce 
                },
                body: JSON.stringify(data)
            });
            
            if (!resp.ok) throw new Error('Update failed');
            
            await loadData();
            return true;
        } catch (err) {
            console.error('Update failed:', err);
            throw err;
        }
    }

    // ===========================================
    // CUSTOM STAGE DROPDOWN
    // ===========================================
    
    function initStageDropdown() {
        const $select = $('#lead-stage');
        if (!$select.length) return;
        
        // Create custom dropdown HTML
        const dropdownHtml = `
            <div class="pi-stage-dropdown" id="pi-stage-dropdown">
                <button type="button" class="pi-stage-btn" id="pi-stage-toggle">
                    <span>New Lead</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
                <div class="pi-stage-menu" id="pi-stage-menu">
                    <button type="button" class="pi-stage-option active" data-stage="new_lead">New Lead</button>
                    <button type="button" class="pi-stage-option" data-stage="proposal_sent">Proposal Sent</button>
                    <button type="button" class="pi-stage-option" data-stage="contacted">Contacted</button>
                    <button type="button" class="pi-stage-option" data-stage="negotiation">Negotiation</button>
                    <button type="button" class="pi-stage-option" data-stage="won">Won</button>
                </div>
            </div>
        `;
        
        // Replace the select with our custom dropdown
        $select.hide().after(dropdownHtml);
        
        // Toggle dropdown
        $('#pi-stage-toggle').on('click', function(e) {
            e.stopPropagation();
            $('#pi-stage-dropdown').toggleClass('active');
        });
        
        // Close on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#pi-stage-dropdown').length) {
                $('#pi-stage-dropdown').removeClass('active');
            }
        });
        
        // Option selection
        $('.pi-stage-option').on('click', function() {
            const stage = $(this).data('stage');
            const label = $(this).text();
            
            $('.pi-stage-option').removeClass('active');
            $(this).addClass('active');
            $('#pi-stage-dropdown').removeClass('active');
            
            // Update button text and hidden select
            $('#pi-stage-toggle span').text(label);
            $('#lead-stage').val(stage);
            currentStage = stage;
        });
    }
    
    function updateStageDropdown(stage) {
        currentStage = stage;
        const label = stageLabels[stage] || 'New Lead';
        
        // Update button text
        $('#pi-stage-toggle span').text(label);
        
        // Update hidden select
        $('#lead-stage').val(stage);
        
        // Update active option
        $('.pi-stage-option').removeClass('active');
        $(`.pi-stage-option[data-stage="${stage}"]`).addClass('active');
    }
    
    // Initialize the custom dropdown
    initStageDropdown();

    // Stage update
    $('#update-stage').on('click', async function() {
        const $btn = $(this);
        const newStage = currentStage;
        
        setButtonLoading($btn, true);
        
        try {
            const resp = await fetch(workspaceEndpoint, { 
                headers: { 'X-WP-Nonce': nonce } 
            });
            const currentWorkspace = await resp.json();

            for (const stage in currentWorkspace) {
                currentWorkspace[stage] = currentWorkspace[stage].filter(item => item.id !== leadId);
            }

            if (!currentWorkspace[newStage]) {
                currentWorkspace[newStage] = [];
            }
            currentWorkspace[newStage].push({ id: leadId });

            await fetch(`${workspaceEndpoint}/save`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-WP-Nonce': nonce 
                },
                body: JSON.stringify({ workspace: currentWorkspace })
            });

            showToast('Stage updated successfully!', 'success');
            await loadData();
            
            dispatchCRMUpdate('stage-changed', {
                leadId: leadId,
                newStage: newStage
            });
            
        } catch (err) {
            console.error('Stage update failed:', err);
            showToast('Failed to update stage. Please try again.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    // Customer form
    $('#pi-lead-customer-form').on('submit', async function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        
        setButtonLoading($btn, true);
        
        try {
            const formData = {};
            $(this).find('input').each((i, el) => {
                formData[el.name] = $(el).val();
            });
            
            await updateData(formData);
            showToast('Customer information saved!', 'success');
            
        } catch (err) {
            showToast('Failed to save customer info.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    // Notes form
    $('#pi-notes-form').on('submit', async function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        
        setButtonLoading($btn, true);
        
        try {
            const notes = $('textarea[name="notes"]').val();
            await updateData({ notes });
            showToast('Notes saved!', 'success');
            
        } catch (err) {
            showToast('Failed to save notes.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    // Add pricing row
    $('#pi-add-pricing-row').on('click', function() {
        const idx = $('#pi-pricing-table tbody tr').length;
        addPricingRow('', 1, 0, idx);
    });

    // Remove pricing row
    $(document).on('click', '.pi-remove-row', function() {
        const $rows = $('#pi-pricing-table tbody tr');
        if ($rows.length > 1) {
            $(this).closest('tr').remove();
            calculateTotals();
        } else {
            showToast('You need at least one pricing item.', 'info');
        }
    });

    // Pricing form - SAVE AND AUTO-SYNC TO INVOICE (AUTOMATIC PRICE SYNC)
    $('#pi-pricing-form').on('submit', async function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        
        // Cancel any pending auto-sync since we're doing a full save
        clearTimeout(pricingSyncTimeout);
        
        setButtonLoading($btn, true);
        
        try {
            const pricing = getCurrentPricingFromTable();
            const totals = calculatePricingTotals(pricing);
            
            console.log('[PI Pricing] Saving quote:', {
                items: pricing.length,
                subtotal: totals.subtotal,
                vat: totals.vat,
                grandTotal: totals.grandTotal
            });
            
            // Save pricing details and estimated_value to lead
            await updateData({ 
                pricing_details: pricing,
                estimated_value: totals.grandTotal
            });
            
            // AUTOMATIC SYNC: If there's an invoice, sync the amount to it immediately
            // This ensures the proposal ALWAYS reflects the current pricing
            if (currentInvoice) {
                console.log('[PI Auto-Sync] Syncing pricing to proposal:', totals);
                const syncResult = await syncPricingToInvoice();
                
                if (syncResult && syncResult.pdf_regenerated) {
                    console.log('[PI Auto-Sync] PDF regenerated successfully');
                }
                
                // Reload invoice section to show updated sync status
                await loadLinkedInvoice();
                loadInvoiceSection(currentLeadData);
                
                showToast(`Quote saved & proposal updated to ${formatCurrency(totals.grandTotal)}!`, 'success');
            } else {
                showToast('Quote saved successfully!', 'success');
            }
            
            // Sync estimated value to lead meta for workspace
            await syncEstimatedValueToLead(totals.grandTotal);
            
        } catch (err) {
            console.error('[PI Pricing] Save failed:', err);
            showToast('Failed to save quote.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    /**
     * CORE PROPOSAL GENERATION FUNCTION
     * This unified function handles all proposal generation with proper pricing sync
     * Called by: #pi-create-invoice, #pi-generate-proposal, #pi-generate-invoice-pricing
     */
    async function generateProposalWithPricing() {
        // Get current pricing from table - this is the source of truth
        const pricing = getCurrentPricingFromTable();
        const totals = calculatePricingTotals(pricing);
        const notes = $('textarea[name="notes"]').val() || '';
        const dueDate = currentLeadData?.due_date || '';
        const currentStage = $('#lead-stage').val() || currentLeadData?.stage || 'new_lead';
        
        console.log('[PI Invoice] Creating proposal with pricing:', {
            subtotal: totals.subtotal,
            vat: totals.vat,
            grandTotal: totals.grandTotal,
            pricingItems: pricing.length,
            currentStage: currentStage
        });
        
        // STEP 1: Save pricing to lead first - this ensures lead meta is updated
        await updateData({ 
            pricing_details: pricing,
            estimated_value: totals.grandTotal
        });
        console.log('[PI Invoice] Lead pricing saved');
        
        // STEP 2: If in "New Lead" stage, move to "Proposal Sent"
        if (currentStage === 'new_lead') {
            console.log('[PI Invoice] Moving lead from New Lead to Proposal Sent');
            try {
                const workspaceResp = await fetch(workspaceEndpoint, { 
                    headers: { 'X-WP-Nonce': nonce } 
                });
                const currentWorkspace = await workspaceResp.json();
                
                // Remove from all stages
                for (const stage in currentWorkspace) {
                    currentWorkspace[stage] = currentWorkspace[stage].filter(item => item.id !== leadId);
                }
                
                // Add to proposal_sent
                if (!currentWorkspace['proposal_sent']) {
                    currentWorkspace['proposal_sent'] = [];
                }
                currentWorkspace['proposal_sent'].push({ id: leadId });
                
                await fetch(`${workspaceEndpoint}/save`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'X-WP-Nonce': nonce 
                    },
                    body: JSON.stringify({ workspace: currentWorkspace })
                });
                
                // Update local stage selector
                $('#lead-stage').val('proposal_sent');
                
                dispatchCRMUpdate('stage-changed', {
                    leadId: leadId,
                    newStage: 'proposal_sent'
                });
            } catch (stageErr) {
                console.warn('[PI Invoice] Could not update stage:', stageErr);
            }
        }
        
        // STEP 3: Generate invoice with the EXACT calculated total
        const resp = await fetch(`${invoicesEndpoint}/add`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-WP-Nonce': nonce 
            },
            body: JSON.stringify({ 
                lead_id: leadId,
                pi_lead_id: leadId,
                planning_app_id: planningAppId,
                // CRITICAL: Pass the exact totals from pricing calculation
                est: totals.grandTotal,
                subtotal: totals.subtotal,
                vat: totals.vat,
                pricing_details: pricing,
                due: dueDate,
                notes: notes,
                // Pass current stage for invoice status sync
                lead_stage: currentStage === 'new_lead' ? 'proposal_sent' : currentStage
            })
        });
        
        if (!resp.ok) {
            const errorText = await resp.text();
            console.error('[PI Invoice] Generation failed:', errorText);
            throw new Error('Invoice generation failed: ' + errorText);
        }
        
        const result = await resp.json();
        console.log('[PI Invoice] Proposal generated:', result);
        
        // CRITICAL FIX: Immediately set currentInvoice from the response
        // This ensures the proposal preview shows up right away without waiting for a refetch
        currentInvoice = {
            id: result.id,
            lead_id: planningAppId,
            pi_lead_id: leadId,
            pdf_url: result.pdf_url,
            amount: totals.grandTotal,
            subtotal: totals.subtotal,
            vat: totals.vat,
            created: new Date().toLocaleDateString('en-GB'),
            valid_until: dueDate ? new Date(dueDate).toLocaleDateString('en-GB') : new Date(Date.now() + 30*24*60*60*1000).toLocaleDateString('en-GB'),
            status: 'draft',
            pricing_details: pricing
        };
        console.log('[PI Invoice] Set currentInvoice immediately:', currentInvoice);
        
        // Immediately update the proposal section to show the preview
        // This replaces the "Generate Proposal" button with the proposal preview card
        loadProposalSection(currentLeadData);
        
        // Then reload full data in background to update stage display and sync everything
        await loadData();
        
        // Update workspace stats
        await syncEstimatedValueToLead(totals.grandTotal);
        
        // Dispatch events for other pages
        dispatchCRMUpdate('invoice-created', {
            invoiceId: result.id,
            leadId: leadId,
            planningAppId: planningAppId,
            amount: totals.grandTotal,
            subtotal: totals.subtotal,
            vat: totals.vat,
            pdfUrl: result.pdf_url,
            stage: currentStage === 'new_lead' ? 'proposal_sent' : currentStage
        });
        
        return { result, totals };
    }

    // ===========================================
    // PROPOSAL ACTIONS (Documents Tab)
    // ===========================================
    
    // Generate Proposal - FROM DOCUMENTS TAB
    $(document).on('click', '#pi-generate-proposal-doc', async function() {
        const $btn = $(this);
        setButtonLoading($btn, true);
        
        try {
            const { result, totals } = await generateProposalWithPricing();
            showToast(`Proposal generated with total ${formatCurrency(totals.grandTotal)}!`, 'success');
        } catch (err) {
            console.error('[PI Invoice] Generation failed:', err);
            showToast('Failed to generate proposal. Please check the console for details.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });
    
    // Legacy handlers for backwards compatibility
    $(document).on('click', '#pi-create-invoice', async function() {
        const $btn = $(this);
        setButtonLoading($btn, true);
        
        try {
            const { result, totals } = await generateProposalWithPricing();
            showToast(`Proposal generated with total ${formatCurrency(totals.grandTotal)}!`, 'success');
        } catch (err) {
            console.error('[PI Invoice] Generation failed:', err);
            showToast('Failed to generate proposal. Please check the console for details.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });
    
    // Generate Invoice from PRICING TAB "Generate Invoice" button
    $(document).on('click', '#pi-generate-invoice-pricing', async function() {
        const $btn = $(this);
        setButtonLoading($btn, true);
        
        try {
            const { result, totals } = await generateProposalWithPricing();
            showToast(`Proposal generated with total ${formatCurrency(totals.grandTotal)}!`, 'success');
        } catch (err) {
            console.error('[PI Invoice] Generation failed:', err);
            showToast('Failed to generate proposal.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    // Sync Proposal button (both old and new IDs)
    $(document).on('click', '#pi-sync-invoice, #pi-sync-proposal', async function() {
        const $btn = $(this);
        
        setButtonLoading($btn, true);
        
        try {
            await syncPricingToInvoice();
            showToast('Proposal synced with current pricing!', 'success');
            
            // Reload proposal section to show updated sync status
            await loadLinkedInvoice();
            loadProposalSection(currentLeadData);
            
        } catch (err) {
            showToast('Failed to sync proposal.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });
    
    // Delete Proposal
    $(document).on('click', '#pi-delete-proposal', async function() {
        if (!currentInvoice) return;
        
        if (!confirm('Are you sure you want to delete this proposal? This action cannot be undone.')) {
            return;
        }
        
        const $btn = $(this);
        setButtonLoading($btn, true);
        
        try {
            const resp = await fetch(`${invoicesEndpoint}/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ inv_id: currentInvoice.id })
            });
            
            if (!resp.ok) {
                throw new Error('Delete failed');
            }
            
            currentInvoice = null;
            showToast('Proposal deleted successfully', 'success');
            
            // Reload proposal section to show generate option
            loadProposalSection(currentLeadData);
            
            // Dispatch event for other pages
            dispatchCRMUpdate('invoice-deleted', {
                leadId: leadId,
                planningAppId: planningAppId
            });
            
        } catch (err) {
            console.error('[PI] Delete proposal failed:', err);
            showToast('Failed to delete proposal.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });
    
    // Edit Proposal - Open full PDF editor modal
    $(document).on('click', '#pi-edit-proposal', async function() {
        if (!currentInvoice || !currentInvoice.pdf_url) {
            showToast('No proposal PDF to edit.', 'info');
            return;
        }
        
        openProposalEditorModal(currentInvoice);
    });
    
    /**
     * Open the full PDF editor modal
     * Adapted from invoices.js but integrated into the lead page
     */
    async function openProposalEditorModal(inv) {
        // Editable fields configuration
        const EDITABLE_FIELDS = {
            amount: { patterns: [/^£[\d,]+\.?\d*$/, /^[\d,]+\.?\d*$/], contextBefore: ['amount', 'investment', 'total', 'proposed'], label: 'Amount' },
            date: { patterns: [/^\d{1,2}\/\d{1,2}\/\d{2,4}$/], contextBefore: ['date:', 'date issued'], label: 'Date' },
            valid_until: { patterns: [/^\d{1,2}\/\d{1,2}\/\d{2,4}$/], contextBefore: ['valid until', 'valid until:'], label: 'Valid Until' },
            address: { patterns: [], contextBefore: ['to:', 'prepared for', 'to'], multiLine: true, label: 'Client Address' },
            re_line: { patterns: [], contextBefore: ['re:', 'subject:', 'project reference', 'reference'], label: 'Reference/Subject Line' },
            description: { patterns: [], contextBefore: ['description', 'scope of work', 'proposed works', 'to whom it may concern'], multiLine: true, label: 'Description' },
            notes: { patterns: [], contextBefore: ['notes:', 'notes', 'inclusions', 'additional'], multiLine: true, label: 'Notes' },
            terms: { patterns: [], contextBefore: ['terms & conditions', 'terms and conditions', 'terms:', 'terms of engagement'], multiLine: true, label: 'Terms & Conditions' },
            warranty: { patterns: [], contextBefore: ['warranty', 'quality assurance', 'guarantee'], multiLine: true, label: 'Warranty' }
        };
        
        const $modal = $(`
            <div class="pi-pdf-editor-overlay" id="pi-pdf-editor-modal">
                <div class="pi-pdf-editor-modal">
                    <div class="pi-pdf-editor-header">
                        <h2>Edit Proposal #${inv.id}</h2>
                        <button class="pi-pdf-editor-close" id="pi-close-pdf-editor">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="pi-pdf-editor-toolbar">
                        <button class="pi-btn pi-btn-secondary" id="pi-edit-mode-toggle">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Enable Edit Mode
                        </button>
                        <button class="pi-btn pi-btn-primary" id="pi-save-pdf-changes">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Save Changes
                        </button>
                        <span class="pi-pdf-editor-status">Click "Enable Edit Mode" to start editing</span>
                    </div>
                    
                    <div class="pi-pdf-editor-hint">
                        <strong>Editable fields:</strong> Amount, Date, Valid Until, Address, Reference, Description, Notes, Terms, Warranty
                    </div>
                    
                    <div class="pi-pdf-editor-container" id="pi-pdf-editor-container">
                        <div class="pi-pdf-loading">
                            <div class="pi-spinner"></div>
                            <p>Loading PDF...</p>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append($modal);
        $modal.addClass('active');
        
        // Close handlers
        $('#pi-close-pdf-editor, .pi-pdf-editor-overlay').on('click', function(e) {
            if (e.target === this || $(this).is('#pi-close-pdf-editor')) {
                $modal.removeClass('active');
                setTimeout(() => $modal.remove(), 300);
            }
        });
        
        $('.pi-pdf-editor-modal').on('click', function(e) {
            e.stopPropagation();
        });
        
        const container = document.getElementById('pi-pdf-editor-container');
        
        try {
            // Fetch PDF bytes
            const pdfUrl = `${invoicesEndpoint}/get-pdf?id=${inv.id}&_wpnonce=${nonce}`;
            const response = await fetch(pdfUrl, {
                method: 'GET',
                headers: { 'X-WP-Nonce': nonce }
            });
            
            if (!response.ok) throw new Error('PDF fetch failed: ' + response.statusText);
            const pdfBytes = await response.arrayBuffer();
            
            // Setup pdf.js
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
            
            const loadingTask = pdfjsLib.getDocument({ data: pdfBytes });
            const pdf = await loadingTask.promise;
            
            container.innerHTML = '';
            
            let editMode = false;
            let allTextItems = [];
            let fieldSpans = [];
            
            // Render all pages
            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                const page = await pdf.getPage(pageNum);
                const viewport = page.getViewport({ scale: 1.2 });
                
                const pageWrapper = document.createElement('div');
                pageWrapper.style.position = 'relative';
                pageWrapper.style.marginBottom = '20px';
                container.appendChild(pageWrapper);
                
                const canvas = document.createElement('canvas');
                canvas.className = 'pi-pdf-canvas';
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvas.style.display = 'block';
                canvas.style.margin = '0 auto';
                canvas.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                pageWrapper.appendChild(canvas);
                
                const context = canvas.getContext('2d');
                await page.render({ canvasContext: context, viewport }).promise;
                
                const textLayer = document.createElement('div');
                textLayer.className = 'pi-text-layer';
                textLayer.style.cssText = `
                    position:absolute; left:50%; top:0;
                    width:${viewport.width}px; height:${viewport.height}px;
                    transform:translateX(-50%); pointer-events:none;
                `;
                pageWrapper.appendChild(textLayer);
                
                const textContent = await page.getTextContent();
                
                textContent.items.forEach((item, idx) => {
                    const text = (item.str || '').trim();
                    if (!text) return;
                    
                    allTextItems.push({ text, pageNum, idx, item });
                    
                    const span = document.createElement('span');
                    span.textContent = item.str;
                    span.className = 'pi-text-overlay';
                    span.dataset.pageNum = pageNum;
                    span.dataset.itemIdx = idx;
                    span.dataset.originalText = item.str;
                    
                    const x = item.transform[4] * 1.2;
                    const y = viewport.height - item.transform[5] * 1.2 - (item.height || 12) * 1.2;
                    
                    span.style.cssText = `
                        position:absolute;
                        left:${x}px; top:${y}px;
                        font-size:${(item.height || 12) * 1.2}px;
                        white-space:pre;
                        pointer-events:none;
                        color:transparent;
                        background:transparent;
                        padding:2px 4px;
                        margin:-2px -4px;
                        border-radius:3px;
                        transition:all 0.2s;
                    `;
                    
                    textLayer.appendChild(span);
                });
            }
            
            // Smart field detection
            function detectFieldType(text, prevTexts) {
                const lowerText = text.toLowerCase().trim();
                const prevContext = prevTexts.slice(-3).join(' ').toLowerCase();
                
                for (const [fieldName, config] of Object.entries(EDITABLE_FIELDS)) {
                    for (const pattern of config.patterns || []) {
                        if (pattern.test(text.trim())) {
                            if (fieldName === 'date' || fieldName === 'valid_until') {
                                if (prevContext.includes('valid until')) return 'valid_until';
                                if (prevContext.includes('date')) return 'date';
                            }
                            return fieldName;
                        }
                    }
                    
                    for (const ctx of config.contextBefore || []) {
                        if (prevContext.includes(ctx.toLowerCase())) {
                            if (!lowerText.includes(ctx.toLowerCase().replace(':', ''))) {
                                return fieldName;
                            }
                        }
                    }
                }
                
                return null;
            }
            
            // Mark editable fields
            const textOverlays = container.querySelectorAll('.pi-text-overlay');
            const prevTexts = [];
            
            textOverlays.forEach((span) => {
                const text = span.dataset.originalText || '';
                prevTexts.push(text);
                
                const fieldType = detectFieldType(text, prevTexts);
                
                if (fieldType) {
                    span.dataset.fieldType = fieldType;
                    span.dataset.fieldLabel = EDITABLE_FIELDS[fieldType]?.label || fieldType;
                    fieldSpans.push(span);
                }
            });
            
            // Mark long text blocks as description/notes
            textOverlays.forEach((span) => {
                const text = (span.dataset.originalText || '').trim();
                if (text.length > 20 && !span.dataset.fieldType) {
                    const lowerText = text.toLowerCase();
                    if (lowerText.includes('pleased to') || lowerText.includes('proposal') || lowerText.includes('works')) {
                        span.dataset.fieldType = 'description';
                        span.dataset.fieldLabel = 'Description';
                        fieldSpans.push(span);
                    }
                }
            });
            
            // Edit mode toggle
            $('#pi-edit-mode-toggle').on('click', function() {
                editMode = !editMode;
                $(this).html(editMode 
                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> View Mode' 
                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Enable Edit Mode'
                );
                $(this).toggleClass('pi-btn-danger', editMode);
                
                $('.pi-pdf-editor-status').text(
                    editMode ? `Editing ${fieldSpans.length} editable fields` : 'Click "Enable Edit Mode" to start editing'
                );
                
                textOverlays.forEach(span => {
                    const isField = span.dataset.fieldType;
                    
                    if (editMode && isField) {
                        span.contentEditable = true;
                        span.style.pointerEvents = 'auto';
                        span.style.color = '#000';
                        span.style.background = 'rgba(59,130,246,0.2)';
                        span.style.border = '1px dashed #3b82f6';
                        span.style.cursor = 'text';
                        span.title = `Edit: ${span.dataset.fieldLabel}`;
                    } else {
                        span.contentEditable = false;
                        span.style.pointerEvents = 'none';
                        span.style.color = 'transparent';
                        span.style.background = 'transparent';
                        span.style.border = 'none';
                        span.title = '';
                    }
                });
                
                container.querySelectorAll('.pi-pdf-canvas').forEach(canvas => {
                    canvas.style.opacity = editMode ? '0.001' : '1';
                });
            });
            
            // Save handler
            $('#pi-save-pdf-changes').on('click', async function() {
                const $btn = $(this);
                setButtonLoading($btn, true);
                
                const edits = [];
                const processedFields = new Set();
                
                textOverlays.forEach(span => {
                    const originalText = span.dataset.originalText || '';
                    const currentText = span.textContent || '';
                    const fieldType = span.dataset.fieldType;
                    
                    if (currentText !== originalText) {
                        if (fieldType && !processedFields.has(fieldType)) {
                            edits.push({
                                field: fieldType,
                                text: currentText.trim(),
                                original: originalText
                            });
                            processedFields.add(fieldType);
                        } else if (!fieldType) {
                            const detected = detectFieldType(currentText, [originalText]);
                            if (detected && !processedFields.has(detected)) {
                                edits.push({
                                    field: detected,
                                    text: currentText.trim(),
                                    original: originalText
                                });
                                processedFields.add(detected);
                            }
                        }
                    }
                });
                
                if (edits.length === 0) {
                    showToast('No changes detected.', 'info');
                    setButtonLoading($btn, false);
                    return;
                }
                
                try {
                    const resp = await fetch(`${invoicesEndpoint}/save-edited-pdf`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce
                        },
                        body: JSON.stringify({ id: inv.id, edits })
                    });
                    
                    if (!resp.ok) throw new Error(await resp.text());
                    
                    const data = await resp.json();
                    
                    // Update local invoice data
                    edits.forEach(e => {
                        if (e.field === 'amount') {
                            currentInvoice.amount = parseFloat(e.text.replace(/[^\d.]/g, '')) || 0;
                        } else {
                            currentInvoice[e.field] = e.text;
                        }
                    });
                    
                    if (data.pdf_url) {
                        currentInvoice.pdf_url = data.pdf_url;
                    }
                    
                    showToast('Proposal saved successfully!', 'success');
                    
                    // Close modal and reload proposal section
                    $modal.removeClass('active');
                    setTimeout(() => {
                        $modal.remove();
                        loadProposalSection(currentLeadData);
                    }, 300);
                    
                    // Dispatch update event
                    dispatchCRMUpdate('invoice-updated', {
                        invoiceId: inv.id,
                        leadId: leadId,
                        changes: edits
                    });
                    
                } catch (err) {
                    console.error('Save failed:', err);
                    showToast('Failed to save: ' + err.message, 'error');
                } finally {
                    setButtonLoading($btn, false);
                }
            });
            
        } catch (err) {
            console.error('PDF load error:', err);
            container.innerHTML = `<div class="pi-pdf-error"><p>Failed to load PDF: ${err.message}</p></div>`;
        }
    }
    // ===========================================
    // FILE UPLOAD - REST API VERSION
    // ===========================================
    
    const $uploadZone = $('#pi-upload-zone');
    const $fileInput = $('#pi-file-input');
    let isUploading = false;

    $uploadZone.on('click', function(e) {
        if (e.target.closest('.pi-attachment-item')) return;
        $fileInput.trigger('click');
    });

    $uploadZone
        .on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!isUploading) $(this).addClass('dragover');
        })
        .on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        })
        .on('drop', function(e) {
            if (isUploading) return;
            const files = e.originalEvent.dataTransfer.files;
            if (files.length) uploadFiles(files);
        });

    $fileInput.on('change', function() {
        if (this.files.length && !isUploading) {
            uploadFiles(this.files);
        }
    });

    async function uploadFiles(files) {
        isUploading = true;
        $uploadZone.addClass('uploading');
        
        // Show progress container
        let $progress = $('#pi-upload-progress');
        if (!$progress.length) {
            $progress = $(`<div id="pi-upload-progress" class="pi-upload-progress"></div>`);
            $uploadZone.append($progress);
        }
        $progress.empty().show();

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Client-side validation
            const maxSize = 10 * 1024 * 1024; // 10MB
            if (file.size > maxSize) {
                showToast(`${file.name} exceeds 10MB limit`, 'error');
                continue;
            }
            
            const allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'application/zip'
            ];
            
            if (!allowedTypes.includes(file.type) && !file.name.match(/\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|xls|xlsx|txt|zip)$/i)) {
                showToast(`${file.name}: Invalid file type`, 'error');
                continue;
            }

            // Create progress item
            const $item = $(`
                <div class="pi-upload-item" data-filename="${escapeHtml(file.name)}">
                    <div class="pi-upload-info">
                        <span class="pi-upload-filename">${escapeHtml(file.name)}</span>
                        <span class="pi-upload-size">${formatFileSize(file.size)}</span>
                    </div>
                    <div class="pi-upload-bar">
                        <div class="pi-upload-progress-fill"></div>
                    </div>
                    <span class="pi-upload-status">Uploading...</span>
                </div>
            `);
            $progress.append($item);
            
            const $fill = $item.find('.pi-upload-progress-fill');
            const $status = $item.find('.pi-upload-status');

            try {
                const formData = new FormData();
                formData.append('file', file);
                
                // Use XMLHttpRequest for progress tracking
                const xhr = new XMLHttpRequest();
                
                const uploadPromise = new Promise((resolve, reject) => {
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            $fill.css('width', percent + '%');
                        }
                    });
                    
                    xhr.addEventListener('load', () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(JSON.parse(xhr.response));
                        } else {
                            reject(xhr.response);
                        }
                    });
                    
                    xhr.addEventListener('error', () => reject('Network error'));
                    xhr.addEventListener('abort', () => reject('Upload aborted'));
                    
                    xhr.open('POST', `${endpoint}/attachments`, true);
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                    xhr.send(formData);
                });
                
                const result = await uploadPromise;
                
                $fill.css('width', '100%').addClass('complete');
                $status.text('Complete').addClass('success');
                
                // Add to attachments list immediately
                addAttachmentToList(result);
                
                showToast(`${file.name} uploaded successfully`, 'success');
                
            } catch (err) {
                console.error('Upload error:', err);
                $fill.addClass('error');
                $status.text('Failed').addClass('error');
                let errorMsg = 'Upload failed';
                try {
                    const errorData = JSON.parse(err);
                    if (errorData.message) errorMsg = errorData.message;
                } catch(e) {}
                showToast(`${file.name}: ${errorMsg}`, 'error');
            }
        }
        
        isUploading = false;
        $uploadZone.removeClass('uploading');
        
        // Clear progress after delay
        setTimeout(() => {
            $progress.fadeOut(300, function() { $(this).empty(); });
        }, 3000);
        
        // Reload data to ensure everything is synced
        await loadData();
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function addAttachmentToList(attach) {
        const $list = $('#pi-attachments-list');
        const isImage = attach.type && attach.type.startsWith('image/');
        
        const itemHtml = isImage ? `
            <div class="pi-attachment-item" data-id="${attach.id}">
                <a href="${escapeHtml(attach.url)}" target="_blank" class="pi-attachment-link">
                    <img src="${escapeHtml(attach.url)}" alt="${escapeHtml(attach.name)}" class="pi-attachment-thumb" />
                    <span class="pi-attachment-name">${escapeHtml(attach.name)}</span>
                </a>
                <button class="pi-attachment-delete" data-id="${attach.id}" title="Delete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        ` : `
            <div class="pi-attachment-item" data-id="${attach.id}">
                <a href="${escapeHtml(attach.url)}" target="_blank" class="pi-attachment-link">
                    <div class="pi-attachment-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>
                        </svg>
                    </div>
                    <span class="pi-attachment-name">${escapeHtml(attach.name)}</span>
                </a>
                <button class="pi-attachment-delete" data-id="${attach.id}" title="Delete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        `;
        
        // Remove empty state if exists
        $list.find('.pi-empty-state').remove();
        $list.append(itemHtml);
    }

    // Delete attachment handler
    $(document).on('click', '.pi-attachment-delete', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $(this);
        const attachId = $btn.data('id');
        
        if (!confirm('Delete this file?')) return;
        
        $btn.prop('disabled', true).addClass('deleting');
        
        try {
            const resp = await fetch(`${endpoint}/attachments/${attachId}`, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': nonce }
            });
            
            if (!resp.ok) throw new Error('Delete failed');
            
            $btn.closest('.pi-attachment-item').fadeOut(300, function() { 
                $(this).remove();
                // Check if empty
                if ($('#pi-attachments-list').children().length === 0) {
                    loadAttachments({});
                }
            });
            
            showToast('File deleted', 'success');
            
        } catch (err) {
            showToast('Failed to delete file', 'error');
            $btn.prop('disabled', false).removeClass('deleting');
        }
    });

    // ===========================================
    // TASKS MANAGEMENT (Server-synced)
    // ===========================================
    
    const tasksEndpoint = '/wp-json/pi/v1/tasks';
    let cachedTasks = [];

    async function loadTasks() {
        try {
            const resp = await fetch(`${tasksEndpoint}?lead_id=${leadId}`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            if (!resp.ok) throw new Error('Failed to load tasks');
            cachedTasks = await resp.json();
            renderTasks();
        } catch (err) {
            console.error('Failed to load tasks:', err);
            cachedTasks = [];
            renderTasks();
        }
    }

    function getTasks() {
        return cachedTasks;
    }

    function getHighestPriority(tasks) {
        const pending = tasks.filter(t => !t.completed);
        if (pending.some(t => t.priority === 'high')) return 'high';
        if (pending.some(t => t.priority === 'medium')) return 'medium';
        if (pending.length > 0) return 'low';
        return null;
    }

    function dispatchTasksUpdate() {
        dispatchCRMUpdate('tasks-updated', {
            leadId: leadId,
            taskCount: cachedTasks.filter(t => !t.completed).length,
            priority: getHighestPriority(cachedTasks)
        });
    }

    function renderTasks() {
        const tasks = getTasks();
        const $list = $('#pi-tasks-list');
        
        if (tasks.length === 0) {
            $list.html(`
                <div class="pi-empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    <p>No tasks yet</p>
                    <span>Create tasks to track work for this lead</span>
                </div>
            `);
            return;
        }
        
        $list.empty();
        
        tasks.forEach((task) => {
            $list.append(`
                <div class="pi-task-item ${task.completed ? 'completed' : ''}" data-task-id="${task.id}">
                    <div class="pi-task-checkbox ${task.completed ? 'completed' : ''}" data-task-id="${task.id}">
                        ${task.completed ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>' : ''}
                    </div>
                    <div class="pi-task-content">
                        <div class="pi-task-title">${escapeHtml(task.title)}</div>
                        <div class="pi-task-meta">
                            ${task.due ? `<span class="pi-task-due">Due: ${formatDate(task.due)}</span>` : ''}
                            <span class="pi-task-priority ${task.priority}">${task.priority}</span>
                        </div>
                    </div>
                    <button class="pi-btn pi-btn-icon pi-delete-task" data-task-id="${task.id}" title="Delete task">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            `);
        });
    }

    $(document).on('click', '.pi-task-checkbox', async function() {
        const taskId = $(this).data('task-id');
        const task = cachedTasks.find(t => t.id === taskId);
        if (!task) return;
        
        const newCompleted = !task.completed;
        
        try {
            const resp = await fetch(`${tasksEndpoint}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ task_id: taskId, completed: newCompleted })
            });
            
            if (!resp.ok) throw new Error('Failed to update task');
            
            task.completed = newCompleted;
            renderTasks();
            dispatchTasksUpdate();
            
            if (newCompleted) {
                showToast('Task completed! 🎉', 'success');
            }
        } catch (err) {
            console.error('Failed to toggle task:', err);
            showToast('Failed to update task', 'error');
        }
    });

    $(document).on('click', '.pi-delete-task', async function(e) {
        e.stopPropagation();
        const taskId = $(this).data('task-id');
        
        try {
            const resp = await fetch(`${tasksEndpoint}/remove`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ task_id: taskId })
            });
            
            if (!resp.ok) throw new Error('Failed to delete task');
            
            cachedTasks = cachedTasks.filter(t => t.id !== taskId);
            renderTasks();
            dispatchTasksUpdate();
            showToast('Task deleted', 'info');
        } catch (err) {
            console.error('Failed to delete task:', err);
            showToast('Failed to delete task', 'error');
        }
    });

    $('#pi-add-task').on('click', function() {
        $('#pi-task-modal').addClass('active');
        $('#task_title').focus();
    });

    $('#pi-close-task-modal, #pi-cancel-task').on('click', function() {
        $('#pi-task-modal').removeClass('active');
        $('#pi-task-form')[0].reset();
    });

    $('#pi-task-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
            $('#pi-task-form')[0].reset();
        }
    });

    $('#pi-task-form').on('submit', async function(e) {
        e.preventDefault();
        
        const taskData = {
            title: $('#task_title').val(),
            due: $('#task_due').val() || null,
            priority: $('#task_priority').val(),
            lead_id: leadId
        };
        
        try {
            const resp = await fetch(`${tasksEndpoint}/add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(taskData)
            });
            
            if (!resp.ok) throw new Error('Failed to add task');
            
            const data = await resp.json();
            cachedTasks.push(data.task);
            renderTasks();
            dispatchTasksUpdate();
            
            $('#pi-task-modal').removeClass('active');
            this.reset();
            
            showToast('Task added!', 'success');
        } catch (err) {
            console.error('Failed to add task:', err);
            showToast('Failed to add task', 'error');
        }
    });

    // Load tasks on page init
    loadTasks();

    // ===========================================
    // QUICK ACTIONS
    // ===========================================
    
    $('#pi-quick-call').on('click', function() {
        const phone = $('input[name="customer_phone"]').val();
        if (phone) {
            window.location.href = `tel:${phone}`;
        } else {
            showToast('No phone number available. Add one in Customer tab.', 'info');
        }
    });

    $('#pi-quick-email').on('click', function() {
        const email = $('input[name="customer_email"]').val();
        const name = $('input[name="customer_name"]').val() || 'Customer';
        const address = $('input[name="customer_address"]').val() || '';
        
        if (email) {
            const subject = encodeURIComponent(`Regarding your property at ${address}`);
            const body = encodeURIComponent(`Dear ${name},\n\nI hope this email finds you well.\n\n`);
            window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
        } else {
            showToast('No email address available. Add one in Customer tab.', 'info');
        }
    });

    // Header quick-action "Generate Proposal" button (icon in top right)
    // Uses the same unified generation function
    $('#pi-generate-proposal').on('click', async function() {
        const $btn = $(this);
        
        // Check if proposal already exists
        if (currentInvoice) {
            showToast('Proposal already exists. Use Sync to update.', 'info');
            return;
        }
        
        setButtonLoading($btn, true);
        
        try {
            const { result, totals } = await generateProposalWithPricing();
            showToast(`Proposal generated with total ${formatCurrency(totals.grandTotal)}!`, 'success');
        } catch (err) {
            console.error('[PI Invoice] Generation failed:', err);
            showToast('Failed to generate proposal.', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    $('#pi-send-message').on('click', function() {
        const message = $('#pi-quick-message').val().trim();
        if (!message) {
            showToast('Please enter a message first.', 'info');
            return;
        }
        showToast('Message sent! (Demo)', 'success');
        $('#pi-quick-message').val('');
    });

    // ===========================================
    // EXPENSES INTEGRATION
    // ===========================================
    
    /**
     * Load linked job expenses for this lead
     * Called when the job tab is active
     */
    async function loadLeadExpenses() {
        const $container = $('#pi-lead-expenses');
        if (!$container.length) return;
        
        try {
            // First check if there's a linked job for this lead
            const jobResp = await fetch(`/wp-json/pi/v1/jobs?lead_id=${leadId}`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            
            if (!jobResp.ok) {
                renderLeadExpensesEmpty('expenses', null);
                return;
            }
            
            const jobs = await jobResp.json();
            if (!jobs || jobs.length === 0) {
                renderLeadExpensesEmpty('expenses', null);
                return;
            }
            
            // Load expenses for the first linked job
            const job = jobs[0];
            const expenseResp = await fetch(`/wp-json/pi/v1/expenses/job-costing?job_id=${job.ID}`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            
            if (!expenseResp.ok) {
                renderMockLeadExpenses(job);
                return;
            }
            
            const data = await expenseResp.json();
            renderLeadExpenses(data, job);
        } catch (err) {
            console.error('[PI] Failed to load lead expenses:', err);
            renderLeadExpensesEmpty('error', null);
        }
    }
    
    function renderLeadExpenses(data, job) {
        const $container = $('#pi-lead-expenses');
        if (!$container.length) return;
        
        const { expenses, total_expenses, budget, quote_value, profit_margin, budget_used_percent } = data;
        
        let html = `
            <div class="pi-lead-expense-header">
                <h4>Job Expenses: ${escapeHtml(job.post_title)}</h4>
                <a href="/workspace/expenses?job_id=${job.ID}" class="pi-btn pi-btn-primary pi-btn-sm" target="_blank">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Manage Expenses
                </a>
            </div>
            <div class="pi-lead-expense-summary">
                <div class="pi-expense-stat">
                    <span class="pi-label">Total Expenses</span>
                    <span class="pi-value">£${total_expenses.toFixed(2)}</span>
                </div>
                <div class="pi-expense-stat">
                    <span class="pi-label">Budget</span>
                    <span class="pi-value">£${budget.toFixed(2)}</span>
                </div>
                <div class="pi-expense-stat">
                    <span class="pi-label">Quote</span>
                    <span class="pi-value">£${quote_value.toFixed(2)}</span>
                </div>
                <div class="pi-expense-stat">
                    <span class="pi-label">Margin</span>
                    <span class="pi-value ${profit_margin < 20 ? 'pi-warning' : ''}">${profit_margin.toFixed(1)}%</span>
                </div>
            </div>
        `;
        
        if (expenses && expenses.length > 0) {
            html += `
                <div class="pi-lead-expense-list">
                    ${expenses.slice(0, 5).map(exp => `
                        <div class="pi-lead-expense-item">
                            <div class="pi-expense-info">
                                <span class="pi-expense-date">${formatDate(exp.expense_date)}</span>
                                <span class="pi-expense-desc">${escapeHtml(exp.description || '-').substring(0, 40)}</span>
                            </div>
                            <div class="pi-expense-amount">
                                <span>£${parseFloat(exp.amount).toFixed(2)}</span>
                                <span class="pi-badge pi-badge-${exp.approval_status === 'approved' ? 'success' : 'warning'} pi-badge-sm">${exp.approval_status || 'draft'}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
                ${expenses.length > 5 ? `<p class="pi-more-items">+ ${expenses.length - 5} more expenses</p>` : ''}
            `;
        } else {
            html += `
                <div class="pi-empty-state pi-empty-sm">
                    <p>No expenses recorded yet.</p>
                </div>
            `;
        }
        
        $container.html(html);
    }
    
    function renderMockLeadExpenses(job) {
        const mockData = {
            total_expenses: 1250.00,
            budget: 5000.00,
            quote_value: 8000.00,
            profit_margin: 68.75,
            budget_used_percent: 25.0,
            expenses: [
                { expense_date: '2024-01-15', description: 'Initial materials purchase', amount: 450.00, approval_status: 'approved' },
                { expense_date: '2024-01-12', description: 'Site survey costs', amount: 200.00, approval_status: 'approved' },
                { expense_date: '2024-01-10', description: 'Permit application fee', amount: 150.00, approval_status: 'pending' },
                { expense_date: '2024-01-08', description: 'Travel expenses', amount: 75.00, approval_status: 'approved' },
                { expense_date: '2024-01-05', description: 'Equipment hire', amount: 375.00, approval_status: 'approved' },
            ]
        };
        renderLeadExpenses(mockData, job);
    }
    
    function renderLeadExpensesEmpty(type, job) {
        const $container = $('#pi-lead-expenses');
        if (!$container.length) return;
        
        let message, action;
        if (type === 'expenses') {
            message = job ? 'No expenses recorded for this job yet.' : 'No job linked to this lead yet.';
            action = job 
                ? `<a href="/workspace/expenses?job_id=${job.ID}&action=add" class="pi-btn pi-btn-primary pi-btn-sm" target="_blank">Add First Expense</a>`
                : `<span class="pi-text-muted">Create a job from this lead to track expenses</span>`;
        } else {
            message = 'Unable to load expenses.';
            action = '<button class="pi-btn pi-btn-secondary pi-btn-sm" onclick="loadLeadExpenses()">Retry</button>';
        }
        
        $container.html(`
            <div class="pi-empty-state">
                <p>${message}</p>
                ${action}
            </div>
        `);
    }
    
    // Bind tab click to load expenses
    $(document).on('click', '.pi-tab-btn[data-tab="job"]', function() {
        loadLeadExpenses();
    });

    // ===========================================
    // KEYBOARD SHORTCUTS
    // ===========================================
    
    $(document).on('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            $('.pi-search-box input').focus();
        }
        
        if (e.key === 'Escape') {
            $('#pi-task-modal').removeClass('active');
        }
    });

    // ===========================================
    // CROSS-TAB SYNCHRONIZATION
    // ===========================================
    
    window.addEventListener('storage', function(e) {
        if (e.key === 'pi_crm_last_update') {
            try {
                const update = JSON.parse(e.newValue);
                if (update && update.leadId === leadId) {
                    // Another tab updated this lead, reload data
                    loadData();
                }
            } catch (err) {
                // Ignore parse errors
            }
        }
    });

    // ===========================================
    // SPINNER ANIMATION
    // ===========================================
    
    const spinnerStyle = document.createElement('style');
    spinnerStyle.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .pi-sync-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .pi-sync-badge.synced {
            background: #dcfce7;
            color: #166534;
        }
        .pi-sync-badge.out-of-sync {
            background: #fef3c7;
            color: #92400e;
        }
        .pi-invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .pi-invoice-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .pi-invoice-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pi-invoice-actions {
            display: flex;
            gap: 8px;
        }
        .pi-invoice-preview {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--pi-bg-tertiary, #1a1a1a);
            color: var(--pi-text-inverse, #ffffff);
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .pi-invoice-preview span {
            color: var(--pi-text-inverse, #ffffff);
            opacity: 0.8;
        }
        .pi-invoice-preview strong {
            color: var(--pi-text-inverse, #ffffff);
            font-size: 18px;
        }
    `;
    document.head.appendChild(spinnerStyle);

    // ===========================================
    // PINGEN: SEND / PRINT LETTER HANDLER
    // ===========================================

    $(document).on('click', '#send-pingen-letter', async function () {
        const $btn = $(this);
        const invoiceId = $btn.data('invoice-id');
        if (!invoiceId) {
            showToast('No proposal found to send.', 'error');
            return;
        }

        const confirmed = confirm(
            'Send this proposal by post?\n\n' +
            'You will be charged automatically (no extra steps).\n' +
            'The letter will be printed in colour and posted via Royal Mail.'
        );
        if (!confirmed) return;

        setButtonLoading($btn, true);

        try {
            const resp = await fetch('/wp-json/pi/v1/pingen/send-letter', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ invoice_id: invoiceId })
            });

            const result = await resp.json();

            if (!resp.ok) {
                const errorMsg = result.message || result.data?.message || result.error || 'Unknown error';
                console.error('[PI Pingen] Full error:', result);
                alert('Error: ' + errorMsg);           // ← shows real message now
                showToast(errorMsg, 'error');
                return;
            }

            showToast('Success! Letter queued for posting.', 'success');
            dispatchCRMUpdate('invoice-synced', { invoiceId, leadId: leadId, status: 'mailed' });
            await loadData();

        } catch (err) {
            console.error('[PI Pingen] Send failed:', err);
            alert('Network error. Please try again.');
            showToast('Network error — check console', 'error');
        } finally {
            setButtonLoading($btn, false);
        }
    });

    // ===========================================
    // INITIALIZE
    // ===========================================
    
    loadData();
    renderTasks();
});
