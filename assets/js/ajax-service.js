/**
 * AJAX Services for Inventory Updater
 * Handles all AJAX calls to the server
 */
var InventoryUpdater = InventoryUpdater || {};

InventoryUpdater.Ajax = (function($) {
    'use strict';
    
    // Reference to DOM elements
    var elements = {};
    
    /**
     * Initialize AJAX services
     * @param {Object} elementsRef - Reference to DOM elements
     */
    function init(elementsRef) {
        elements = elementsRef;
    }
    
    /**
     * Save plugin settings via AJAX
     * @param {boolean} updateStock - Whether to update stock
     * @param {boolean} updatePrice - Whether to update price
     * @param {boolean} maintainDiscounts - Whether to maintain discounts
     */
    function saveSettings(updateStock, updatePrice, maintainDiscounts) {
        // Disable button
        elements.saveSettingsButton.attr('disabled', true).text(inventory_updater_params.saving_text);
        
        // Hide saved message
        elements.settingsSavedMessage.hide();
        
        // Make AJAX request
        $.ajax({
            url: inventory_updater_params.ajax_url,
            type: 'POST',
            data: {
                action: 'inventory_updater_save_settings',
                nonce: inventory_updater_params.nonce,
                update_stock: updateStock,
                update_price: updatePrice,
                maintain_discounts: maintainDiscounts
            },
            success: function(response) {
                console.log('AJAX save settings success:', response);
                
                if (response.success) {
                    // Show success message
                    elements.settingsSavedMessage.fadeIn().delay(2000).fadeOut();
                } else {
                    // Show error message
                    alert(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Show error message
                alert('Error en la solicitud AJAX: ' + error);
            },
            complete: function() {
                // Enable button
                elements.saveSettingsButton.attr('disabled', false).text('Guardar configuración');
                console.log('AJAX save settings request completed');
            }
        });
    }
    
    /**
     * Download inventory file from URL via AJAX
     * @param {string} url - URL to download file from
     */
    function downloadFile(url) {
        // Disable button
        elements.downloadButton.attr('disabled', true).text(inventory_updater_params.downloading_text);
        
        // Show progress bar
        elements.downloadProgressSection.show();
        elements.downloadProgressBar.css('width', '0%');
        elements.downloadProgressText.text(inventory_updater_params.downloading_text);
        
        // Make AJAX request
        $.ajax({
            url: inventory_updater_params.ajax_url,
            type: 'POST',
            data: {
                action: 'inventory_updater_download',
                nonce: inventory_updater_params.nonce,
                url: url
            },
            success: function(response) {
                console.log('AJAX download success:', response);
                
                // Update progress to 100%
                elements.downloadProgressBar.css('width', '100%');
                
                if (response.success) {
                    // Show success message
                    elements.downloadProgressText.text(inventory_updater_params.download_success_text);
                    
                    // Update file status
                    elements.fileStatus.html(
                        '<div class="notice notice-success inline"><p>' +
                        'Archivo de inventario encontrado. Última modificación: ' + response.data.file_date +
                        '</p></div>'
                    );
                } else {
                    // Show error message
                    elements.downloadProgressText.text(inventory_updater_params.download_error_text);
                    alert(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Show error message
                elements.downloadProgressText.text(inventory_updater_params.download_error_text);
                alert('Error en la solicitud AJAX: ' + error);
            },
            complete: function() {
                // Enable button
                elements.downloadButton.attr('disabled', false).text('Descargar archivo');
                console.log('AJAX download request completed');
                
                // Keep progress bar visible with result for a while
                setTimeout(function() {
                    elements.downloadProgressSection.fadeOut(500);
                }, 3000);
            }
        });
    }
    
    /**
     * Process inventory file via AJAX
     */
    function processInventory() {
        // Disable button
        elements.processButton.attr('disabled', true).text(inventory_updater_params.processing_text);
        
        // Show progress bar
        elements.progressSection.show();
        elements.progressBar.css('width', '0%');
        elements.progressText.text(inventory_updater_params.processing_text);
        
        // Hide previous results section
        elements.resultsSection.hide();
        elements.resultsContent.empty();
        
        // Verify we have parameters
        console.log('AJAX URL:', inventory_updater_params.ajax_url);
        console.log('Nonce:', inventory_updater_params.nonce);
        
        // Make AJAX request
        $.ajax({
            url: inventory_updater_params.ajax_url,
            type: 'POST',
            data: {
                action: 'inventory_updater_process',
                nonce: inventory_updater_params.nonce
            },
            success: function(response) {
                console.log('AJAX success:', response);
                
                // Update progress to 100%
                elements.progressBar.css('width', '100%');
                
                if (response.success) {
                    // Show success message
                    elements.progressText.text(inventory_updater_params.success_text);
                    
                    // Display results
                    InventoryUpdater.ResultsRenderer.displayResults(response.data.results);
                } else {
                    // Show error message
                    elements.progressText.text(inventory_updater_params.error_text);
                    elements.resultsSection.show();
                    elements.resultsContent.html(
                        '<div class="inventory-updater-error-message">' + 
                        response.data.message + 
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Show error message
                elements.progressText.text(inventory_updater_params.error_text);
                elements.resultsSection.show();
                elements.resultsContent.html(
                    '<div class="inventory-updater-error-message">' + 
                    'Error en la solicitud AJAX: ' + error + 
                    '</div>'
                );
            },
            complete: function() {
                // Enable button
                elements.processButton.attr('disabled', false).text('Iniciar actualización');
                console.log('AJAX request completed');
            }
        });
    }
    
    return {
        init: init,
        saveSettings: saveSettings,
        downloadFile: downloadFile,
        processInventory: processInventory
    };
    
})(jQuery);
