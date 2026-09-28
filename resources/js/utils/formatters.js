/**
 * EASYPOS Utility Formatters
 * Standardized financial, quantity, and date formatting using Intl APIs.
 */

/**
 * Format a numeric value as currency (default: Philippine Peso PHP).
 *
 * @param {number|string} amount - The amount to format
 * @param {string} [currency='PHP'] - ISO 4217 currency code
 * @param {string} [locale='en-PH'] - BCP 47 language tag
 * @returns {string} Formatted currency string (e.g. "₱123.45")
 */
export function formatCurrency(amount, currency = 'PHP', locale = 'en-PH') {
    const numeric = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (numeric === null || numeric === undefined || isNaN(numeric)) {
        return '₱0.00';
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(numeric);
    } catch {
        // Fallback if locale or currency is unrecognized
        return `₱${numeric.toFixed(2)}`;
    }
}

/**
 * Format a numeric quantity.
 *
 * @param {number|string} value - Quantity to format
 * @param {number} [decimals=0] - Decimal places
 * @param {string} [locale='en-US'] - BCP 47 language tag
 * @returns {string} Formatted quantity
 */
export function formatQuantity(value, decimals = 0, locale = 'en-US') {
    const numeric = typeof value === 'string' ? parseFloat(value) : value;
    if (numeric === null || numeric === undefined || isNaN(numeric)) {
        return '0';
    }

    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(numeric);
}

/**
 * Format a date/time object or ISO string.
 *
 * @param {Date|string|number} date
 * @param {Intl.DateTimeFormatOptions} [options]
 * @param {string} [locale='en-PH']
 * @returns {string} Formatted date string
 */
export function formatDate(date, options = {}, locale = 'en-PH') {
    if (!date) return '';

    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d.getTime())) return '';

    const defaultOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        ...options,
    };

    return new Intl.DateTimeFormat(locale, defaultOptions).format(d);
}
