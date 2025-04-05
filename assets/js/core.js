/**
 * Core functionality for Inventory Updater
 * Contains initialization, global variables and main setup
 */
var InventoryUpdater = InventoryUpdater || {};

InventoryUpdater.Core = (function($) {
    'use strict';
    
    // DOM elements cache
    var elements = {
        processButton: $('#inventory-updater-process'),
        downloadButton: $('#inventory-updater-download'),
        saveSettingsButton: $('#save-settings'),
        updateStockCheckbox: $('#update-stock'),
        updatePriceCheckbox: $('#update-price'),
        maintainDiscountsCheckbox: $('#maintain-discounts'),
        maintainDiscountsContainer: $('#maintain-discounts-container'),
        settingsSavedMessage: $('#settings-saved-message'),
        urlInput: $('#inventory-updater-url'),
        progressSection: $('#inventory-updater-progress'),
        downloadProgressSection: $('#inventory-updater-download-progress'),
        progressBar: $('.inventory-updater-progress-bar-fill'),
        downloadProgressBar: $('#inventory-updater-download-progress .inventory-updater-progress-bar-fill'),
        progressText: $('.inventory-updater-progress-text'),
        downloadProgressText: $('#inventory-updater-download-progress .inventory-updater-progress-text'),
        resultsSection: $('#inventory-updater-results'),
        resultsContent: $('.inventory-updater-results-content'),
        fileStatus: $('.inventory-updater-file-status')
    };
    
    /**
     * Initialize the plugin
     */
    function init() {
        console.log('Inventory Updater JS loaded');
        
        // Verify that important elements exist
        console.log('Process button exists:', elements.processButton.length > 0);
        console.log('Download button exists:', elements.downloadButton.length > 0);
        console.log('Save settings button exists:', elements.saveSettingsButton.length > 0);
        
        // Initialize UI handlers
        InventoryUpdater.UI.init(elements);
        
        // Initialize AJAX services
        InventoryUpdater.Ajax.init(elements);
    }
    
    return {
        init: init,
        elements: elements
    };
    
})(jQuery);

// Initialize when document is ready
jQuery(document).ready(function() {
    InventoryUpdater.Core.init();
});
