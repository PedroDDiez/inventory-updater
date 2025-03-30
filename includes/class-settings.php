<?php
/**
 * Clase para gestionar las configuraciones del plugin
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Inventory_Updater_Settings {
    /**
     * Opciones del plugin
     */
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->options = $this->get_options();
    }
    
    /**
     * Inicializar acciones
     */
    public function init() {
        add_action('wp_ajax_inventory_updater_save_settings', array($this, 'ajax_save_settings'));
    }
    
    /**
     * Obtener opciones de configuración
     */
    public function get_options() {
        $default_options = array(
            'update_stock' => 'yes',
            'update_price' => 'yes',
            'maintain_discounts' => 'no'
        );
        
        $options = get_option('inventory_updater_options', $default_options);
        
        return $options;
    }
    
    /**
     * Obtener una opción específica
     */
    public function get_option($key, $default = null) {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        
        return $default;
    }
    
    /**
     * Comprobar si una opción está habilitada
     */
    public function is_enabled($option_name) {
        return $this->get_option($option_name) === 'yes';
    }
    
    /**
     * Guardar configuración (AJAX)
     */
    public function ajax_save_settings() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'inventory_updater_nonce')) {
            wp_send_json_error(array('message' => __('Error de seguridad. Por favor, actualiza la página e inténtalo de nuevo.', 'inventory-updater')));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tienes permisos para realizar esta acción.', 'inventory-updater')));
        }
        
        // Obtener datos
        $update_stock = isset($_POST['update_stock']) && $_POST['update_stock'] === 'true' ? 'yes' : 'no';
        $update_price = isset($_POST['update_price']) && $_POST['update_price'] === 'true' ? 'yes' : 'no';
        $maintain_discounts = isset($_POST['maintain_discounts']) && $_POST['maintain_discounts'] === 'true' ? 'yes' : 'no';
        
        // Guardar configuración
        $options = array(
            'update_stock' => $update_stock,
            'update_price' => $update_price,
            'maintain_discounts' => $maintain_discounts
        );
        
        update_option('inventory_updater_options', $options);
        
        // Actualizar opciones locales
        $this->options = $options;
        
        wp_send_json_success(array('message' => __('Configuración guardada correctamente.', 'inventory-updater')));
    }
}