/**
 * JavaScript para la página de administración del plugin Inventory Updater
 */
jQuery(document).ready(function($) {
    
    console.log('Inventory Updater JS loaded');
    
    // Elementos DOM
    const $processButton = $('#inventory-updater-process');
    const $progressSection = $('#inventory-updater-progress');
    const $progressBar = $('.inventory-updater-progress-bar-fill');
    const $progressText = $('.inventory-updater-progress-text');
    const $resultsSection = $('#inventory-updater-results');
    const $resultsContent = $('.inventory-updater-results-content');
    
    // Verificar que los elementos existen
    console.log('Process button exists:', $processButton.length > 0);
    console.log('Progress section exists:', $progressSection.length > 0);
    
    // Manejador para el botón de procesamiento
    $processButton.on('click', function(e) {
        e.preventDefault();
        console.log('Process button clicked');
        
        // Deshabilitar botón
        $processButton.attr('disabled', true).text(inventory_updater_params.processing_text);
        
        // Mostrar barra de progreso
        $progressSection.show();
        $progressBar.css('width', '0%');
        $progressText.text(inventory_updater_params.processing_text);
        
        // Ocultar sección de resultados anteriores
        $resultsSection.hide();
        $resultsContent.empty();
        
        // Verificar que tenemos los parámetros
        console.log('AJAX URL:', inventory_updater_params.ajax_url);
        console.log('Nonce:', inventory_updater_params.nonce);
        
        // Realizar solicitud AJAX
        $.ajax({
            url: inventory_updater_params.ajax_url,
            type: 'POST',
            data: {
                action: 'inventory_updater_process',
                nonce: inventory_updater_params.nonce
            },
            success: function(response) {
                console.log('AJAX success:', response);
                
                // Actualizar progreso a 100%
                $progressBar.css('width', '100%');
                
                if (response.success) {
                    // Mostrar mensaje de éxito
                    $progressText.text(inventory_updater_params.success_text);
                    
                    // Mostrar resultados
                    displayResults(response.data.results);
                } else {
                    // Mostrar mensaje de error
                    $progressText.text(inventory_updater_params.error_text);
                    $resultsSection.show();
                    $resultsContent.html(
                        '<div class="inventory-updater-error-message">' + 
                        response.data.message + 
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Mostrar mensaje de error
                $progressText.text(inventory_updater_params.error_text);
                $resultsSection.show();
                $resultsContent.html(
                    '<div class="inventory-updater-error-message">' + 
                    'Error en la solicitud AJAX: ' + error + 
                    '</div>'
                );
            },
            complete: function() {
                // Habilitar botón
                $processButton.attr('disabled', false).text('Iniciar actualización');
                console.log('AJAX request completed');
            }
        });
    });
    
    /**
     * Mostrar resultados del procesamiento
     */
    function displayResults(results) {
        console.log('Displaying results:', results);
        
        // Mostrar sección de resultados
        $resultsSection.show();
        
        // Crear contenido HTML para los resultados
        let html = '';
        
        // Tarjetas de resumen
        html += '<div class="inventory-updater-results-summary">';
        
        // Productos actualizados
        html += '<div class="inventory-updater-results-card updated">';
        html += '<h3>Productos actualizados</h3>';
        html += '<div class="number">' + results.updated + '</div>';
        html += '<p>de ' + results.total_lines + ' líneas procesadas</p>';
        html += '</div>';
        
        // Productos no encontrados
        html += '<div class="inventory-updater-results-card not-found">';
        html += '<h3>Productos no encontrados</h3>';
        html += '<div class="number">' + results.not_found + '</div>';
        html += '<p>no se pudieron asociar a productos existentes</p>';
        html += '</div>';
        
        // Errores
        html += '<div class="inventory-updater-results-card errors">';
        html += '<h3>Errores</h3>';
        html += '<div class="number">' + results.errors + '</div>';
        html += '<p>errores durante el procesamiento</p>';
        html += '</div>';
        
        // Productos sin SKU
        html += '<div class="inventory-updater-results-card no-sku">';
        html += '<h3>Productos sin SKU</h3>';
        html += '<div class="number">' + results.products_without_sku_count + '</div>';
        html += '<p>productos en la tienda sin SKU asignado</p>';
        html += '</div>';
        
        html += '</div>'; // Fin de inventory-updater-results-summary
        
        // Sección de tablas
        html += '<div class="inventory-updater-results-tables">';
        
        // Tabla de productos no encontrados
        if (results.not_found_products.length > 0) {
            html += '<h3>Productos no encontrados en la tienda</h3>';
            html += '<p>Los siguientes productos del archivo de inventario no se pudieron encontrar en tu tienda WooCommerce:</p>';
            html += '<div class="table-responsive">';
            html += '<table class="inventory-updater-table not-found-table">';
            html += '<thead><tr><th>SKU</th><th>Código de Barras</th><th>Título</th></tr></thead>';
            html += '<tbody>';
            
            // Limitar a los primeros 100 para no sobrecargar el navegador
            const maxToShow = Math.min(results.not_found_products.length, 100);
            
            for (let i = 0; i < maxToShow; i++) {
                const product = results.not_found_products[i];
                html += '<tr>';
                html += '<td>' + (product.sku || '-') + '</td>';
                html += '<td>' + (product.barcode || '-') + '</td>';
                html += '<td>' + (product.title || '-') + '</td>';
                html += '</tr>';
            }
            
            if (results.not_found_products.length > maxToShow) {
                html += '<tr><td colspan="3">... y ' + (results.not_found_products.length - maxToShow) + ' más</td></tr>';
            }
            
            html += '</tbody></table>';
            html += '</div>';
        }
        
        // Tabla de productos actualizados
        if (results.updated_products && results.updated_products.length > 0) {
            html += '<h3>Productos actualizados</h3>';
            html += '<p>Los siguientes productos han sido actualizados:</p>';
            html += '<div class="table-responsive">';
            html += '<table class="inventory-updater-table updated-table">';
            html += '<thead><tr><th>ID</th><th>SKU</th><th>Título</th><th>Stock Anterior</th><th>Stock Nuevo</th><th>Estado</th></tr></thead>';
            html += '<tbody>';
            
            // Limitar a los primeros 100 para no sobrecargar el navegador
            const maxUpdatedToShow = Math.min(results.updated_products.length, 100);
            
            for (let i = 0; i < maxUpdatedToShow; i++) {
                const product = results.updated_products[i];
                html += '<tr>';
                html += '<td>' + product.id + '</td>';
                html += '<td>' + (product.sku || '-') + '</td>';
                html += '<td>' + product.title + '</td>';
                html += '<td>' + (product.old_stock || '0') + '</td>';
                html += '<td>' + product.new_stock + '</td>';
                html += '<td>' + (product.stock_status == 'instock' ? 'En stock' : 'Agotado') + '</td>';
                html += '</tr>';
            }
            
            if (results.updated_products.length > maxUpdatedToShow) {
                html += '<tr><td colspan="6">... y ' + (results.updated_products.length - maxUpdatedToShow) + ' más</td></tr>';
            }
            
            html += '</tbody></table>';
            html += '</div>';
        }
        
        // Productos sin SKU
        if (results.products_without_sku.length > 0) {
            html += '<h3>Productos sin SKU en la tienda</h3>';
            html += '<p>Los siguientes productos en tu tienda WooCommerce no tienen SKU asignado:</p>';
            html += '<div class="table-responsive">';
            html += '<table class="inventory-updater-table without-sku-table">';
            html += '<thead><tr><th>ID</th><th>Título</th></tr></thead>';
            html += '<tbody>';
            
            // Limitar a los primeros 100 para no sobrecargar el navegador
            const maxToShow = Math.min(results.products_without_sku.length, 100);
            
            for (let i = 0; i < maxToShow; i++) {
                const product = results.products_without_sku[i];
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
        }
        
        html += '</div>'; // Fin de inventory-updater-results-tables
        
        // Insertar todo el contenido HTML
        $resultsContent.html(html);
    }
});