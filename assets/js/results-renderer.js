/**
 * Results Renderer for Inventory Updater
 * Handles displaying the results of inventory processing
 */
var InventoryUpdater = InventoryUpdater || {};

InventoryUpdater.ResultsRenderer = (function($) {
    'use strict';
    
    /**
     * Display results of inventory processing
     * @param {Object} results - Results from processing
     */
    function displayResults(results) {
        console.log('Displaying results:', results);
        
        // Get reference to DOM elements
        var elements = InventoryUpdater.Core.elements;
        
        // Show results section
        elements.resultsSection.show();
        
        // Generate HTML content
        var html = '';
        
        // Add configuration summary
        html += renderConfigSummary(results);
        
        // Add results summary cards
        html += renderResultsCards(results);
        
        // Add results tables
        html += renderResultsTables(results);
        
        // Insert all HTML content
        elements.resultsContent.html(html);
    }
    
    /**
     * Render configuration summary
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderConfigSummary(results) {
        var html = '<div class="inventory-updater-config-summary">';
        html += '<p><strong>Configuración utilizada:</strong> ';
        
        var configItems = [];
        if (results.update_stock) configItems.push('Actualización de stock');
        if (results.update_price) {
            if (results.maintain_discounts) {
                configItems.push('Actualización de precio manteniendo descuentos');
            } else {
                configItems.push('Actualización de precio');
            }
        }
        
        html += configItems.join(', ') || 'No se seleccionó ninguna actualización.';
        html += '</p></div>';
        
        return html;
    }
    
    /**
     * Render summary cards
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderResultsCards(results) {
        var html = '<div class="inventory-updater-results-summary">';
        
        // Updated products card
        html += '<div class="inventory-updater-results-card updated">';
        html += '<h3>Productos actualizados</h3>';
        html += '<div class="number">' + results.updated + '</div>';
        html += '<p>con cambios efectivos de valor</p>';
        html += '</div>';
        
        // Unchanged products card
        html += '<div class="inventory-updater-results-card unchanged">';
        html += '<h3>Productos sin cambios</h3>';
        html += '<div class="number">' + (results.matched_no_changes || 0) + '</div>';
        html += '<p>encontrados pero sin modificaciones</p>';
        html += '</div>';
        
        // Not found products card
        html += '<div class="inventory-updater-results-card not-found">';
        html += '<h3>Productos no encontrados</h3>';
        html += '<div class="number">' + results.not_found + '</div>';
        html += '<p>no se pudieron asociar a productos existentes</p>';
        html += '</div>';
        
        // Products not in file card
        html += '<div class="inventory-updater-results-card missing-in-file">';
        html += '<h3>Productos no listados</h3>';
        html += '<div class="number">' + (results.missing_in_file ? results.missing_in_file.length : 0) + '</div>';
        html += '<p>productos existentes no encontrados en el archivo</p>';
        html += '</div>';
        
        // Errors card
        html += '<div class="inventory-updater-results-card errors">';
        html += '<h3>Errores</h3>';
        html += '<div class="number">' + results.errors + '</div>';
        html += '<p>errores durante el procesamiento</p>';
        html += '</div>';
        
        // Products without SKU card
        html += '<div class="inventory-updater-results-card no-sku">';
        html += '<h3>Productos sin SKU</h3>';
        html += '<div class="number">' + results.products_without_sku_count + '</div>';
        html += '<p>productos en la tienda sin SKU asignado</p>';
        html += '</div>';
        
        html += '</div>'; // End of inventory-updater-results-summary
        
        return html;
    }
    
    /**
     * Render all result tables
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderResultsTables(results) {
        var html = '<div class="inventory-updater-results-tables">';
        
        // Add updated products table
        if (results.updated_products && results.updated_products.length > 0) {
            html += renderUpdatedProductsTable(results);
        }
        
        // Add unchanged products table
        if (results.unchanged_products && results.unchanged_products.length > 0) {
            html += renderUnchangedProductsTable(results);
        }
        
        // Add products not in file table
        if (results.missing_in_file && results.missing_in_file.length > 0) {
            html += renderMissingInFileTable(results);
        }
        
        // Add products without SKU table
        if (results.products_without_sku.length > 0) {
            html += renderProductsWithoutSkuTable(results);
        }
        
        // Add not found products table (limited to 10 and at the end)
        if (results.not_found_products.length > 0) {
            html += renderNotFoundProductsTable(results);
        }
        
        html += '</div>'; // End of inventory-updater-results-tables
        
        return html;
    }
    
    /**
     * Render updated products table
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderUpdatedProductsTable(results) {
        var html = '<div class="inventory-updater-section-header">';
        html += '<h3>Productos actualizados</h3>';
        html += '<p>Los siguientes productos han sido actualizados con cambios efectivos en sus valores:</p>';
        html += '</div>';
        html += '<div class="table-responsive">';
        html += '<table class="inventory-updater-table updated-table">';
        html += '<thead><tr><th>ID</th><th>SKU</th><th>Título</th>';
        
        if (results.update_stock) {
            html += '<th>Stock Anterior</th><th>Stock Nuevo</th><th>Estado</th>';
        }
        
        if (results.update_price) {
            if (results.maintain_discounts) {
                html += '<th>Precio Regular Anterior</th><th>Precio Regular Nuevo</th><th>Precio Oferta Anterior</th><th>Precio Oferta Nuevo</th>';
            } else {
                html += '<th>Precio Anterior</th><th>Precio Nuevo</th>';
            }
        }
        
        html += '</tr></thead>';
        html += '<tbody>';
        
        // Limit to first 100 to not overload the browser
        var maxUpdatedToShow = Math.min(results.updated_products.length, 100);
        
        for (var i = 0; i < maxUpdatedToShow; i++) {
            var product = results.updated_products[i];
            html += '<tr>';
            html += '<td>' + product.id + '</td>';
            html += '<td>' + (product.sku || '-') + '</td>';
            html += '<td>' + product.title + '</td>';
            
            if (results.update_stock) {
                var stockChanged = product.stock_changed === true;
                html += '<td>' + (product.old_stock || '0') + '</td>';
                html += '<td class="' + (stockChanged ? 'value-changed' : '') + '">' + product.new_stock + '</td>';
                html += '<td>' + (product.stock_status == 'instock' ? 'En stock' : 'Agotado') + '</td>';
            }
            
            if (results.update_price) {
                if (results.maintain_discounts) {
                    var priceChanged = product.price_changed === true;
                    var salePriceChanged = product.sale_price_changed === true;
                    
                    html += '<td>' + (product.old_price && product.old_price !== '0' ? product.old_price + ' €' : '-') + '</td>';
                    html += '<td class="' + (priceChanged ? 'value-changed' : '') + '">' + (product.new_price && product.new_price !== '0' ? product.new_price + ' €' : '-') + '</td>';
                    html += '<td>' + (product.old_sale_price && product.old_sale_price !== '0' ? product.old_sale_price + ' €' : '-') + '</td>';
                    html += '<td class="' + (salePriceChanged ? 'value-changed' : '') + '">' + (product.new_sale_price && product.new_sale_price !== '0' ? product.new_sale_price + ' €' : '-') + '</td>';
                } else {
                    var priceChanged = product.price_changed === true;
                    
                    html += '<td>' + (product.old_price && product.old_price !== '0' ? product.old_price + ' €' : '-') + '</td>';
                    html += '<td class="' + (priceChanged ? 'value-changed' : '') + '">' + (product.new_price && product.new_price !== '0' ? product.new_price + ' €' : '-') + '</td>';
                }
            }
            
            html += '</tr>';
        }
        
        if (results.updated_products.length > maxUpdatedToShow) {
            // Calculate number of columns for colspan
            var colspan = 3; // ID, SKU, Title
            if (results.update_stock) colspan += 3; // Stock columns
            if (results.update_price) {
                if (results.maintain_discounts) {
                    colspan += 4; // Additional columns for discount prices
                } else {
                    colspan += 2; // Normal price columns
                }
            }
            
            html += '<tr><td colspan="' + colspan + '">... y ' + (results.updated_products.length - maxUpdatedToShow) + ' más</td></tr>';
        }
        
        html += '</tbody></table>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Render unchanged products table
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderUnchangedProductsTable(results) {
        var html = '<div class="inventory-updater-section-header">';
        html += '<h3>Productos no actualizados</h3>';
        html += '<p>Los siguientes productos fueron encontrados en el archivo pero no sufrieron cambios:</p>';
        html += '</div>';
        html += '<div class="table-responsive">';
        html += '<table class="inventory-updater-table unchanged-table">';
        html += '<thead><tr><th>ID</th><th>SKU</th><th>Título</th>';
        
        if (results.update_stock) {
            html += '<th>Stock</th><th>Estado</th>';
        }
        
        if (results.update_price) {
            if (results.maintain_discounts) {
                html += '<th>Precio Regular</th><th>Precio Oferta</th>';
            } else {
                html += '<th>Precio</th>';
            }
        }
        
        html += '</tr></thead>';
        html += '<tbody>';
        
        // Limit to first 50 to not overload the browser
        var maxUnchangedToShow = Math.min(results.unchanged_products.length, 50);
        
        for (var i = 0; i < maxUnchangedToShow; i++) {
            var product = results.unchanged_products[i];
            html += '<tr>';
            html += '<td>' + product.id + '</td>';
            html += '<td>' + (product.sku || '-') + '</td>';
            html += '<td>' + product.title + '</td>';
            
            if (results.update_stock) {
                html += '<td>' + product.new_stock + '</td>';
                html += '<td>' + (product.stock_status == 'instock' ? 'En stock' : 'Agotado') + '</td>';
            }
            
            if (results.update_price) {
                if (results.maintain_discounts) {
                    html += '<td>' + (product.new_price && product.new_price !== '0' ? product.new_price + ' €' : '-') + '</td>';
                    html += '<td>' + (product.new_sale_price && product.new_sale_price !== '0' ? product.new_sale_price + ' €' : '-') + '</td>';
                } else {
                    html += '<td>' + (product.new_price && product.new_price !== '0' ? product.new_price + ' €' : '-') + '</td>';
                }
            }
            
            html += '</tr>';
        }
        
        if (results.unchanged_products.length > maxUnchangedToShow) {
            // Calculate number of columns for colspan
            var colspan = 3; // ID, SKU, Title
            if (results.update_stock) colspan += 2; // Stock columns (without "previous" column)
            if (results.update_price) {
                if (results.maintain_discounts) {
                    colspan += 2; // Price columns (without "previous" columns)
                } else {
                    colspan += 1; // Price column (without "previous" column)
                }
            }
            
            html += '<tr><td colspan="' + colspan + '">... y ' + (results.unchanged_products.length - maxUnchangedToShow) + ' más</td></tr>';
        }
        
        html += '</tbody></table>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Render products not in file table
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderMissingInFileTable(results) {
        var html = '<div class="inventory-updater-section-header">';
        html += '<h3>Productos no encontrados en listado</h3>';
        html += '<p>Los siguientes productos en tu tienda WooCommerce no aparecen en el archivo de inventario:</p>';
        html += '</div>';
        html += '<div class="table-responsive">';
        html += '<table class="inventory-updater-table missing-in-file-table">';
        html += '<thead><tr><th>ID</th><th>SKU</th><th>Código de Barras</th><th>Título</th></tr></thead>';
        html += '<tbody>';
        
        // Limit to first 50 to not overload the browser
        var maxToShow = Math.min(results.missing_in_file.length, 50);
        
        for (var i = 0; i < maxToShow; i++) {
            var product = results.missing_in_file[i];
            html += '<tr>';
            html += '<td>' + product.id + '</td>';
            html += '<td>' + (product.sku || '-') + '</td>';
            html += '<td>' + (product.barcode || '-') + '</td>';
            html += '<td>' + product.title + '</td>';
            html += '</tr>';
        }
        
        if (results.missing_in_file.length > maxToShow) {
            html += '<tr><td colspan="4">... y ' + (results.missing_in_file.length - maxToShow) + ' más</td></tr>';
        }
        
        html += '</tbody></table>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Render products without SKU table
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderProductsWithoutSkuTable(results) {
        var html = '<div class="inventory-updater-section-header">';
        html += '<h3>Productos sin SKU en la tienda</h3>';
        html += '<p>Los siguientes productos en tu tienda WooCommerce no tienen SKU asignado:</p>';
        html += '</div>';
        html += '<div class="table-responsive">';
        html += '<table class="inventory-updater-table without-sku-table">';
        html += '<thead><tr><th>ID</th><th>Título</th></tr></thead>';
        html += '<tbody>';
        
        // Limit to first 30 to not overload the browser
        var maxToShow = Math.min(results.products_without_sku.length, 30);
        
        for (var i = 0; i < maxToShow; i++) {
            var product = results.products_without_sku[i];
            html += '<tr>';
            html += '<td>' + product.id + '</td>';
            html += '<td>' + product.title + '</td>';
            html += '</tr>';
        }
        
        if (results.products_without_sku.length > maxToShow) {
            html += '<tr><td colspan="2">... y ' + (results.products_without_sku.length - maxToShow) + ' más</td></tr>';
        }
        
        html += '</tbody></table>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Render not found products table
     * @param {Object} results - Results from processing
     * @returns {string} HTML content
     */
    function renderNotFoundProductsTable(results) {
        var html = '<div class="inventory-updater-section-header">';
        html += '<h3>Productos no encontrados en la tienda</h3>';
        html += '<p>Los siguientes productos del archivo de inventario no se pudieron encontrar en tu tienda WooCommerce:</p>';
        html += '</div>';
        html += '<div class="table-responsive">';
        html += '<table class="inventory-updater-table not-found-table">';
        html += '<thead><tr><th>SKU</th><th>Código de Barras</th><th>Título</th>';
        
        if (results.update_price) {
            html += '<th>Precio</th>';
        }
        
        html += '</tr></thead>';
        html += '<tbody>';
        
        // Limit to first 10 to not overload the browser
        var maxToShow = Math.min(results.not_found_products.length, 10);
        
        for (var i = 0; i < maxToShow; i++) {
            var product = results.not_found_products[i];
            html += '<tr>';
            html += '<td>' + (product.sku || '-') + '</td>';
            html += '<td>' + (product.barcode || '-') + '</td>';
            html += '<td>' + (product.title || '-') + '</td>';
            
            if (results.update_price) {
                html += '<td>' + (product.price && product.price !== '0' ? product.price + ' €' : '-') + '</td>';
            }
            
            html += '</tr>';
        }
        
        if (results.not_found_products.length > maxToShow) {
            var colspan = results.update_price ? 4 : 3;
            html += '<tr><td colspan="' + colspan + '">... y ' + (results.not_found_products.length - maxToShow) + ' más</td></tr>';
        }
        
        html += '</tbody></table>';
        html += '</div>';
        
        return html;
    }
    
    // Public API
    return {
        displayResults: displayResults
    };
    
})(jQuery);
