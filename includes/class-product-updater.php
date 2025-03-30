<?php
/**
 * Clase para actualizar productos en WooCommerce
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Inventory_Updater_Product_Updater {
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
        // Inicializar acciones si es necesario
    }
    
    /**
     * Actualizar un producto con datos de inventario
     */
    public function update_product($product_id, $stock, $price, $sku, $barcode) {
        // Obtener configuración
        $update_stock = $this->plugin->settings->is_enabled('update_stock');
        $update_price = $this->plugin->settings->is_enabled('update_price');
        $maintain_discounts = $this->plugin->settings->is_enabled('maintain_discounts');
        
        // Obtener información del producto
        $product_title = get_the_title($product_id);
        $old_stock = get_post_meta($product_id, '_stock', true);
        $old_regular_price = get_post_meta($product_id, '_regular_price', true);
        $old_sale_price = get_post_meta($product_id, '_sale_price', true);
        
        $updated_data = array(
            'id' => $product_id,
            'sku' => $sku,
            'barcode' => $barcode,
            'title' => $product_title
        );
        
        $updated = false;
        
        // Actualizar stock si está habilitado
        if ($update_stock) {
            $this->update_product_stock($product_id, $stock);
            
            // Guardar datos de stock actualizados
            $updated_data['old_stock'] = $old_stock;
            $updated_data['new_stock'] = $stock;
            $updated_data['stock_status'] = $stock > 0 ? 'instock' : 'outofstock';
            
            $updated = true;
        }
        
        // Actualizar precio si está habilitado y hay un precio válido
        if ($update_price && is_numeric($price) && $price > 0) {
            // Registrar el precio para debug
            error_log("Actualizando precio de producto ID: $product_id. Precio: $price (como número: " . floatval($price) . ")");
            
            $price_formatted = number_format(floatval($price), 2, '.', '');
            error_log("Precio formateado: $price_formatted");
            
            $result = $this->update_product_price($product_id, floatval($price));
            
            // Guardar datos de precio actualizados - Usar nombres de campos consistentes
            $updated_data['old_price'] = $old_regular_price;
            $updated_data['new_price'] = $price_formatted;
            
            // Si estamos manteniendo descuentos, guardar también esos datos
            if ($maintain_discounts && !empty($old_sale_price)) {
                $updated_data['old_sale_price'] = $old_sale_price;
                if (isset($result['sale_price']) && !empty($result['sale_price'])) {
                    $updated_data['new_sale_price'] = $result['sale_price'];
                }
            }
            
            error_log("Datos actualizados: " . print_r($updated_data, true));
            
            $updated = true;
        } elseif ($update_price) {
            // Registrar el problema para debug
            error_log("No se pudo actualizar el precio del producto ID: $product_id. Precio recibido no válido: '$price', is_numeric: " . (is_numeric($price) ? 'true' : 'false') . ", valor: " . floatval($price));
        }
        
        return array(
            'updated' => $updated,
            'data' => $updated_data
        );
    }
    
    /**
     * Actualizar el stock de un producto
     */
    private function update_product_stock($product_id, $stock) {
        // Activar la gestión de stock para este producto
        update_post_meta($product_id, '_manage_stock', 'yes');
        
        // Establecer umbral de stock bajo a 1
        update_post_meta($product_id, '_low_stock_amount', 1);
        
        // Actualizar stock
        update_post_meta($product_id, '_stock', $stock);
        
        // Actualizar estado de stock
        $stock_status = $stock > 0 ? 'instock' : 'outofstock';
        update_post_meta($product_id, '_stock_status', $stock_status);
        
        return true;
    }
    
    /**
     * Actualizar el precio de un producto
     */
    private function update_product_price($product_id, $price) {
        // Formatear precio correctamente
        $formatted_price = number_format($price, 2, '.', '');
        
        // Verificar si debemos mantener los descuentos
        $maintain_discounts = $this->plugin->settings->is_enabled('maintain_discounts');
        
        // Obtener precios actuales
        $old_regular_price = get_post_meta($product_id, '_regular_price', true);
        $old_sale_price = get_post_meta($product_id, '_sale_price', true);
        
        // Resultado para devolver
        $result = array(
            'regular_price' => $formatted_price,
            'sale_price' => null
        );
        
        // Actualizar precio regular
        update_post_meta($product_id, '_regular_price', $formatted_price);
        
        // Verificar si hay un precio de oferta activo y si debemos mantener el porcentaje de descuento
        if ($maintain_discounts && !empty($old_sale_price) && !empty($old_regular_price) && floatval($old_regular_price) > 0) {
            // Calcular porcentaje de descuento actual
            $discount_percentage = ((floatval($old_regular_price) - floatval($old_sale_price)) / floatval($old_regular_price)) * 100;
            
            if ($discount_percentage > 0) {
                // Calcular nuevo precio de oferta manteniendo el mismo porcentaje de descuento
                $new_sale_price = floatval($price) - ((floatval($price) * $discount_percentage) / 100);
                
                // Redondear al múltiplo de 0.05 más cercano (5 céntimos)
                $new_sale_price = round($new_sale_price * 20) / 20;
                
                // Formatear y guardar precio de oferta
                $formatted_sale_price = number_format($new_sale_price, 2, '.', '');
                update_post_meta($product_id, '_sale_price', $formatted_sale_price);
                
                // Establecer el precio actual como el precio de oferta
                update_post_meta($product_id, '_price', $formatted_sale_price);
                
                $result['sale_price'] = $formatted_sale_price;
            }
        } else {
            // Si no hay descuento o no debemos mantenerlo
            if (!empty($old_sale_price) && !$maintain_discounts) {
                // Si había un precio de oferta pero no mantenemos descuentos, eliminarlo
                delete_post_meta($product_id, '_sale_price');
            }
            
            // Actualizar precio (igual al regular si no hay descuento)
            update_post_meta($product_id, '_price', $formatted_price);
        }
        
        return $result;
    }
}