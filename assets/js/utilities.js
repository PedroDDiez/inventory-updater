/**
 * Utilities and helper functions for Inventory Updater
 */
var InventoryUpdater = InventoryUpdater || {};

InventoryUpdater.Utilities = (function($) {
    'use strict';
    
    /**
     * Format a currency value with euro symbol
     * @param {string|number} value - The value to format
     * @returns {string} Formatted value
     */
    function formatCurrency(value) {
        if (!value || value === '0') {
            return '-';
        }
        return value + ' €';
    }
    
    /**
     * Format a stock status
     * @param {string} status - The stock status
     * @returns {string} Formatted status
     */
    function formatStockStatus(status) {
        return status === 'instock' ? 'En stock' : 'Agotado';
    }
    
    /**
     * Calculate number of columns for a table based on options
     * @param {Object} options - Options object
     * @returns {number} Number of columns
     */
    function calculateTableColumns(options) {
        var baseColumns = 3; // ID, SKU, Title
        var stockColumns = options.update_stock ? (options.include_previous ? 3 : 2) : 0;
        var priceColumns = 0;
        
        if (options.update_price) {
            if (options.maintain_discounts) {
                priceColumns = options.include_previous ? 4 : 2;
            } else {
                priceColumns = options.include_previous ? 2 : 1;
            }
        }
        
        return baseColumns + stockColumns + priceColumns;
    }
    
    /**
     * Format a "show more" row for tables
     * @param {number} colspan - Number of columns to span
     * @param {number} total - Total number of items
     * @param {number} shown - Number of items shown
     * @returns {string} HTML for "show more" row
     */
    function formatShowMoreRow(colspan, total, shown) {
        if (total <= shown) {
            return '';
        }
        return '<tr><td colspan="' + colspan + '">... y ' + (total - shown) + ' más</td></tr>';
    }

    /**
     * Format seconds into a readable time string
     * @param {number} totalSeconds - Number of seconds
     * @returns {string} Formatted time string (e.g., "2 min 30 seg")
     */
    function formatTimeFromSeconds(totalSeconds) {
        if (totalSeconds < 0) return '';
        
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = Math.floor(totalSeconds % 60);
        
        var timeString = '';
        
        if (minutes > 0) {
            timeString += minutes + ' min ';
        }
        
        timeString += seconds + ' seg';
        
        return timeString;
    }

    // Añadir a la API pública en el return
    return {
        formatCurrency: formatCurrency,
        formatStockStatus: formatStockStatus,
        calculateTableColumns: calculateTableColumns,
        formatShowMoreRow: formatShowMoreRow,
        formatTimeFromSeconds: formatTimeFromSeconds
    };
    
})(jQuery);
