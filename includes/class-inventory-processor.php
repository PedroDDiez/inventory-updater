<?php
/**
 * Clase para procesar el archivo de inventario
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Inventory_Updater_Processor {
    /**
     * Referencia a la clase principal
     */
    private $plugin;
    
    /**
     * Constructor
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Inicializar acciones
     */
    public function init() {
        add_action('wp_ajax_inventory_updater_process', array($this, 'ajax_process_inventory_file'));
    }
    
    /**
     * Procesar archivo de inventario (AJAX)
     */
    public function ajax_process_inventory_file() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'inventory_updater_nonce')) {
            wp_send_json_error(array('message' => __('Error de seguridad. Por favor, actualiza la página e inténtalo de nuevo.', 'inventory-updater')));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tienes permisos para realizar esta acción.', 'inventory-updater')));
        }
        
        // Verificar archivo
        $inventory_file = $this->plugin->file_handler->get_inventory_file_path();
        if (!file_exists($inventory_file)) {
            wp_send_json_error(array('message' => __('No se ha encontrado el archivo articulos.txt.', 'inventory-updater')));
        }
        
        // Iniciar procesamiento
        $results = $this->process_inventory_file($inventory_file);
        
        // Responder con los resultados
        wp_send_json_success($results);
    }
    
    /**
     * Procesar archivo de inventario
     */
    public function process_inventory_file($file_path) {
        global $wpdb;
        
        // Obtener opciones de configuración
        $update_stock = $this->plugin->settings->is_enabled('update_stock');
        $update_price = $this->plugin->settings->is_enabled('update_price');
        
        // Inicializar resultados
        $results = array(
            'updated' => 0,
            'not_found' => 0,
            'errors' => 0,
            'not_found_products' => array(),
            'updated_products' => array(),
            'missing_in_file' => array(),  // Productos en la BD no encontrados en el archivo
            'processed_lines' => 0,
            'total_lines' => 0
        );
        
        // Abrir archivo
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array(
                'success' => false,
                'message' => __('No se pudo abrir el archivo de inventario.', 'inventory-updater')
            );
        }
        
        // Contar líneas totales
        $total_lines = 0;
        while (fgets($handle) !== false) {
            $total_lines++;
        }
        $results['total_lines'] = $total_lines;
        
        // Reiniciar puntero del archivo
        rewind($handle);
        
        // Obtener productos por SKU y código de barras
        $product_map = $this->get_products_map();
        $products_by_sku = $product_map['by_sku'];
        $products_by_barcode = $product_map['by_barcode'];
        $products_without_sku = $product_map['without_sku'];
        
        // Crear registro de los SKUs y códigos de barras encontrados en el archivo
        $skus_in_file = array();
        $barcodes_in_file = array();
        
        // Procesar archivo línea por línea
        while (($line = fgets($handle)) !== false) {
            $results['processed_lines']++;
            
            // Saltar líneas vacías
            if (trim($line) == '') {
                continue;
            }
            
            // Dividir la línea por el delimitador |
            $data = array_map('trim', explode('|', $line));
            
            // Verificar que tenemos suficientes campos
            if (count($data) < 15) {
                $results['errors']++;
                continue;
            }
            
            // Extraer datos relevantes
            $sku = trim($data[0]);
            $stock = intval($data[2]);
            $barcode = !empty($data[7]) ? trim($data[7]) : '';
            
            // Registrar SKU y código de barras para después detectar productos no listados
            if (!empty($sku)) {
                $skus_in_file[] = $sku;
            }
            if (!empty($barcode)) {
                $barcodes_in_file[] = $barcode;
            }
            
            // Extraer precio (en la posición 3 según el ejemplo proporcionado)
            // Asegurarnos de que el precio es un número válido y convertir comas a puntos
            $price_str = !empty($data[3]) ? trim($data[3]) : '0';
            $price_str = str_replace(',', '.', $price_str);
            $price = floatval($price_str);
            
            // Verificar que el precio sea válido y mayor que cero
            if (!is_numeric($price) || $price <= 0) {
                // Intentar con la posición 1 como fallback (estructura antigua)
                $price_str = !empty($data[1]) ? trim($data[1]) : '0';
                $price_str = str_replace(',', '.', $price_str);
                $price = floatval($price_str);
            }
            
            // Registrar los datos para depuración
            error_log("Procesando línea - SKU: $sku, Stock: $stock, Precio original: {$data[3]}, Precio convertido: $price");
            
            // Buscar producto por SKU o código de barras
            $product_id = null;
            
            if (!empty($sku) && isset($products_by_sku[$sku])) {
                $product_id = $products_by_sku[$sku]->ID;
            } elseif (!empty($barcode) && isset($products_by_barcode[$barcode])) {
                $product_id = $products_by_barcode[$barcode]->ID;
            }
            
            // Si encontramos el producto, actualizar el stock y/o precio
            if ($product_id) {
                // Debug para verificar los datos antes de actualizar
                error_log("Actualizando producto ID: $product_id, SKU: $sku, Stock: $stock, Precio: $price");
                
                $update_result = $this->plugin->product_updater->update_product($product_id, $stock, $price, $sku, $barcode);
                
                if ($update_result['updated']) {
                    $results['updated_products'][] = $update_result['data'];
                    $results['updated']++;
                    
                    // Debug para verificar los datos después de actualizar
                    error_log("Producto actualizado. Datos: " . print_r($update_result['data'], true));
                }
            } else {
                // Si no encontramos el producto, añadir a la lista de no encontrados
                $results['not_found']++;
                $results['not_found_products'][] = array(
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'title' => isset($data[9]) ? $data[9] : 'N/A',
                    'price' => $price > 0 ? number_format($price, 2, '.', '') : 'N/A'
                );
            }
        }
        
        // Cerrar archivo
        fclose($handle);
        
        // Identificar productos existentes en la BD que no estaban en el archivo
        foreach ($products_by_sku as $sku => $product) {
            if (!in_array($sku, $skus_in_file)) {
                // Verificar si el producto tampoco está en la lista por código de barras
                $barcode = get_post_meta($product->ID, '_barcode', true);
                if (empty($barcode) || !in_array($barcode, $barcodes_in_file)) {
                    $results['missing_in_file'][] = array(
                        'id' => $product->ID,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'title' => $product->post_title
                    );
                }
            }
        }
        
        // Añadir productos sin SKU a los resultados
        $results['products_without_sku'] = $products_without_sku;
        $results['products_without_sku_count'] = count($products_without_sku);
        
        // Añadir información de configuración a los resultados
        $results['update_stock'] = $update_stock;
        $results['update_price'] = $update_price;
        $results['maintain_discounts'] = $this->plugin->settings->is_enabled('maintain_discounts');
        
        // Limpiar caché de WooCommerce
        wc_delete_product_transients();
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
    
    /**
     * Obtener mapas de productos por SKU y código de barras
     */
    private function get_products_map() {
        global $wpdb;
        
        $products_by_sku = array();
        $products_by_barcode = array();
        $products_without_sku = array();
        
        // Obtener todos los productos de WooCommerce con meta_key _sku
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm_sku.meta_value as sku, pm_barcode.meta_value as barcode
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_barcode ON p.ID = pm_barcode.post_id AND pm_barcode.meta_key = '_barcode'
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
        ");
        
        foreach ($products as $product) {
            if (!empty($product->sku)) {
                $products_by_sku[$product->sku] = $product;
            } else {
                $products_without_sku[] = array(
                    'id' => $product->ID,
                    'title' => $product->post_title
                );
            }
            
            if (!empty($product->barcode)) {
                $products_by_barcode[$product->barcode] = $product;
            }
        }
        
        return array(
            'by_sku' => $products_by_sku,
            'by_barcode' => $products_by_barcode,
            'without_sku' => $products_without_sku
        );
    }
}