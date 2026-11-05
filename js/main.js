/**
 * Main Client-Side JavaScript
 * 
 * Provides DOM event bindings, currency formatters, and utility helpers.
 */

'use strict';

/**
 * Format a number as Thai Baht currency.
 * 
 * @param {number} amount - Numeric value to format
 * @returns {string} Formatted currency string with ฿ prefix
 */
function format_currency_thb(amount) {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB',
        minimumFractionDigits: 0
    }).format(amount);
}

/**
 * Log front-end operational events.
 * 
 * @param {string} context - Source module
 * @param {string} message - Description of the action
 * @param {any} [data] - Additional payload
 */
function log_app_event(context, message, data = null) {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] [${context}] ${message}`, data ? data : '');
}

document.addEventListener('DOMContentLoaded', () => {
    log_app_event('Main', 'Document loaded and initialized');
});

