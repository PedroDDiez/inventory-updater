/**
 * UI Event Handlers for Inventory Updater
 * Manages all user interactions with the interface
 */
var InventoryUpdater = InventoryUpdater || {};

InventoryUpdater.UI = (function($) {
    'use strict';
    
    // Reference to DOM elements
    var elements = {};
    
    /**
     * Initialize UI handlers
     * @param {Object} elementsRef - Reference to DOM elements
     */
    function init(elementsRef) {
        elements = elementsRef;
        
        // Attach event handlers
        attachEventHandlers();
    }
    
    /**
     * Attach event handlers to UI elements
     */
    function attachEventHandlers() {
        // Show/hide maintain discounts option based on update price checkbox
        elements.updatePriceCheckbox.on('change', function() {
            if ($(this).is(':checked')) {
                elements.maintainDiscountsContainer.show();
            } else {
                elements.maintainDiscountsContainer.hide();
            }
        });
        
        // Save settings button
        elements.saveSettingsButton.on('click', function(e) {
            e.preventDefault();
            console.log('Save settings button clicked');
            
            // Get values
            var updateStock = elements.updateStockCheckbox.is(':checked');
            var updatePrice = elements.updatePriceCheckbox.is(':checked');
            var maintainDiscounts = elements.maintainDiscountsCheckbox.is(':checked');
            
            // Call AJAX service to save settings
            InventoryUpdater.Ajax.saveSettings(updateStock, updatePrice, maintainDiscounts);
        });
        
        // Download button
        elements.downloadButton.on('click', function(e) {
            e.preventDefault();
            console.log('Download button clicked');
            
            var url = elements.urlInput.val().trim();
            
            if (!url) {
                alert('Por favor, introduce una URL válida.');
                return;
            }
            
            // Call AJAX service to download file
            InventoryUpdater.Ajax.downloadFile(url);
        });
        
        // Process button
        elements.processButton.on('click', function(e) {
            e.preventDefault();
            console.log('Process button clicked');
            
            // Call AJAX service to process inventory
            InventoryUpdater.Ajax.processInventory();
        });
    }
    
    return {
        init: init
    };
    
})(jQuery);
