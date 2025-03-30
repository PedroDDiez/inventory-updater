/**
 * JavaScript para la página de administración del plugin Inventory Updater
 */
jQuery(document).ready(function($) {
    
    console.log('Inventory Updater JS loaded');
    
    // Elementos DOM
    const $processButton = $('#inventory-updater-process');
    const $downloadButton = $('#inventory-updater-download');
    const $saveSettingsButton = $('#save-settings');
    const $updateStockCheckbox = $('#update-stock');
    const $updatePriceCheckbox = $('#update-price');
    const $maintainDiscountsCheckbox = $('#maintain-discounts');
    const $maintainDiscountsContainer = $('#maintain-discounts-container');
    const $settingsSavedMessage = $('#settings-saved-message');
    const $urlInput = $('#inventory-updater-url');
    const $progressSection = $('#inventory-updater-progress');
    const $downloadProgressSection = $('#inventory-updater-download-progress');
    const $progressBar = $('.inventory-updater-progress-bar-fill');
    const $downloadProgressBar = $('#inventory-updater-download-progress .inventory-updater-progress-bar-fill');
    const $progressText = $('.inventory-updater-progress-text');
    const $downloadProgressText = $('#inventory-updater-download-progress .inventory-updater-progress-text');
    const $resultsSection = $('#inventory-updater-results');
    const $resultsContent = $('.inventory-updater-results-content');
    const $fileStatus = $('.inventory-updater-file-status');
    
    // Verificar que los elementos existen
    console.log('Process button exists:', $processButton.length > 0);
    console.log('Download button exists:', $downloadButton.length > 0);
    console.log('Save settings button exists:', $saveSettingsButton.length > 0);
    
    // Mostrar/ocultar opción de mantener descuentos según el estado del checkbox de actualizar precio
    $updatePriceCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            $maintainDiscountsContainer.show();
        } else {
            $maintainDiscountsContainer.hide();
        }
    });
    
    // Manejador para el botón de guardar configuración
    $saveSettingsButton.on('click', function(e) {
        e.preventDefault();
        console.log('Save settings button clicked');
        
        // Deshabilitar botón
        $saveSettingsButton.attr('disabled', true).text(inventory_updater_params.saving_text);
        
        // Ocultar mensaje de guardado
        $settingsSavedMessage.hide();
        
        // Obtener valores
        const updateStock = $updateStockCheckbox.is(':checked');
        const updatePrice = $updatePriceCheckbox.is(':checked');
        const maintainDiscounts = $maintainDiscountsCheckbox.is(':checked');
        
        // Realizar solicitud AJAX para guardar configuración
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
                    // Mostrar mensaje de éxito
                    $settingsSavedMessage.fadeIn().delay(2000).fadeOut();
                } else {
                    // Mostrar mensaje de error
                    alert(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Mostrar mensaje de error
                alert('Error en la solicitud AJAX: ' + error);
            },
            complete: function() {
                // Habilitar botón
                $saveSettingsButton.attr('disabled', false).text('Guardar configuración');
                console.log('AJAX save settings request completed');
            }
        });
    });
    
    // Manejador para el botón de descargar
    $downloadButton.on('click', function(e) {
        e.preventDefault();
        console.log('Download button clicked');
        
        const url = $urlInput.val().trim();
        
        if (!url) {
            alert('Por favor, introduce una URL válida.');
            return;
        }
        
        // Deshabilitar botón
        $downloadButton.attr('disabled', true).text(inventory_updater_params.downloading_text);
        
        // Mostrar barra de progreso
        $downloadProgressSection.show();
        $downloadProgressBar.css('width', '0%');
        $downloadProgressText.text(inventory_updater_params.downloading_text);
        
        // Realizar solicitud AJAX para descargar
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
                
                // Actualizar progreso a 100%
                $downloadProgressBar.css('width', '100%');
                
                if (response.success) {
                    // Mostrar mensaje de éxito
                    $downloadProgressText.text(inventory_updater_params.download_success_text);
                    
                    // Actualizar estado del archivo
                    $fileStatus.html(
                        '<div class="notice notice-success inline"><p>' +
                        'Archivo de inventario encontrado. Última modificación: ' + response.data.file_date +
                        '</p></div>'
                    );
                } else {
                    // Mostrar mensaje de error
                    $downloadProgressText.text(inventory_updater_params.download_error_text);
                    alert(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Mostrar mensaje de error
                $downloadProgressText.text(inventory_updater_params.download_error_text);
                alert('Error en la solicitud AJAX: ' + error);
            },
            complete: function() {
                // Habilitar botón
                $downloadButton.attr('disabled', false).text('Descargar archivo');
                console.log('AJAX download request completed');
                
                // Mantener la barra de progreso visible con el resultado
                setTimeout(function() {
                    $downloadProgressSection.fadeOut(500);
                }, 3000);
            }
        });
    });
    
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
        
        // Información de configuración
        html += '<div class="inventory-updater-config-summary">';
        html += '<p><strong>Configuración utilizada:</strong> ';
        
        let configItems = [];
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
            html += '<thead><tr><th>SKU</th><th>Código de Barras</th><th>Título</th>';
            
            if (results.update_price) {
                html += '<th>Precio</th>';
            }
            
            html += '</tr></thead>';
            html += '<tbody>';
            
            // Limitar a los primeros 100 para no sobrecargar el navegador
            const maxToShow = Math.min(results.not_found_products.length, 100);
            
            for (let i = 0; i < maxToShow; i++) {
                const product = results.not_found_products[i];
                html += '<tr>';
                html += '<td>' + (product.sku || '-') + '</td>';
                html += '<td>' + (product.barcode || '-') + '</td>';
                html += '<td>' + (product.title || '-') + '</td>';
                
                if (results.update_price) {
                    html += '<td>' + (product.price || '-') + '</td>';
                }
                
                html += '</tr>';
            }
            
            if (results.not_found_products.length > maxToShow) {
                const colspan = results.update_price ? 4 : 3;
                html += '<tr><td colspan="' + colspan + '">... y ' + (results.not_found_products.length - maxToShow) + ' más</td></tr>';
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
            html += '<thead><tr><th>ID</th><th>SKU</th><th>Título</th>';
            
            if (results.update_stock) {
                html += '<th>Stock Anterior</th><th>Stock Nuevo</th><th>Estado</th>';
            }
            
            if (results.update_price) {
                if (results.maintain_discounts) {
                    html += '<th>Precio Regular Anterior</th><th>Precio Regular Nuevo</th><th>Precio Venta Anterior</th><th>Precio Venta Nuevo</th>';
                } else {
                    html += '<th>Precio Anterior</th><th>Precio Nuevo</th>';
                }
            }
            
            html += '</tr></thead>';
            html += '<tbody>';
            
            // Limitar a los primeros 100 para no sobrecargar el navegador
            const maxUpdatedToShow = Math.min(results.updated_products.length, 100);
            
            for (let i = 0; i < maxUpdatedToShow; i++) {
                const product = results.updated_products[i];
                html += '<tr>';
                html += '<td>' + product.id + '</td>';
                html += '<td>' + (product.sku || '-') + '</td>';
                html += '<td>' + product.title + '</td>';
                
                if (results.update_stock) {
                    html += '<td>' + (product.old_stock || '0') + '</td>';
                    html += '<td>' + product.new_stock + '</td>';
                    html += '<td>' + (product.stock_status == 'instock' ? 'En stock' : 'Agotado') + '</td>';
                }
                
                if (results.update_price) {
                    if (results.maintain_discounts) {
                        html += '<td>' + (product.old_price && product.old_price !== '0' ? product.old_price + ' €' : '-') + '</td>';
                        html += '<td>' + (product.new_price && product.new_price !== '0' ? product.new_price + ' €' : '-') + '</td>';
                        html += '<td>' + (product.old_sale_price && product.old_sale_price !== '0' ? product.old_sale_price + ' €' : '-') + '</td>';
                        html += '<td>' + (product.new_sale_price && product.new_sale_price !== '0' ? product.new_sale_price + ' €' : '-') + '</td>';
                    } else {
                        html += '<td>' + (product.old_price && product.old_price !== '0' ? product.old_price + ' €' : '-') + '</td>';
                        html += '<td>' + (product.new_price && product.new_price !== '0' ? product.new_price + ' €' : '-') + '</td>';
                    }
                }
                
                html += '</tr>';
            }
            
            if (results.updated_products.length > maxUpdatedToShow) {
                // Calcular el número de columnas para el colspan
                let colspan = 3; // ID, SKU, Título
                if (results.update_stock) colspan += 3; // Columnas de stock
                if (results.update_price) {
                    if (results.maintain_discounts) {
                        colspan += 4; // Columnas adicionales para precios con descuento
                    } else {
                        colspan += 2; // Columnas de precio normal
                    }
                }
                
                html += '<tr><td colspan="' + colspan + '">... y ' + (results.updated_products.length - maxUpdatedToShow) + ' más</td></tr>';
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