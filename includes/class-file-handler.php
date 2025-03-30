<?php
/**
 * Clase para gestionar la descarga y manejo de archivos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Inventory_Updater_File_Handler {
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
        add_action('wp_ajax_inventory_updater_download', array($this, 'ajax_download_inventory_file'));
    }
    
    /**
     * Comprobar si existe el archivo de inventario
     */
    public function inventory_file_exists() {
        $inventory_file = INVENTORY_UPDATER_UPLOADS_DIR . 'articulos.txt';
        return file_exists($inventory_file);
    }
    
    /**
     * Obtener fecha de modificación del archivo
     */
    public function get_inventory_file_date() {
        $inventory_file = INVENTORY_UPDATER_UPLOADS_DIR . 'articulos.txt';
        if (!$this->inventory_file_exists()) {
            return false;
        }
        
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($inventory_file));
    }
    
    /**
     * Obtener ruta del archivo de inventario
     */
    public function get_inventory_file_path() {
        return INVENTORY_UPDATER_UPLOADS_DIR . 'articulos.txt';
    }
    
    /**
     * Descargar archivo de inventario desde URL (AJAX)
     */
    public function ajax_download_inventory_file() {
        // Aumentar límites para esta operación
        ini_set('max_execution_time', 300); // 5 minutos
        ini_set('memory_limit', '256M');    // 256 MB
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'inventory_updater_nonce')) {
            wp_send_json_error(array('message' => __('Error de seguridad. Por favor, actualiza la página e inténtalo de nuevo.', 'inventory-updater')));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tienes permisos para realizar esta acción.', 'inventory-updater')));
        }
        
        // Verificar URL
        if (!isset($_POST['url']) || empty($_POST['url'])) {
            wp_send_json_error(array('message' => __('URL no válida.', 'inventory-updater')));
        }
        
        $url = esc_url_raw($_POST['url']);
        $download_file = $this->get_inventory_file_path();
        
        // Asegurarse de que el directorio existe y tiene permisos de escritura
        if (!file_exists(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_mkdir_p(INVENTORY_UPDATER_UPLOADS_DIR);
            // Establecer permisos
            @chmod(INVENTORY_UPDATER_UPLOADS_DIR, 0755);
        }
        
        if (!is_writable(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_send_json_error(array('message' => __('El directorio de destino no tiene permisos de escritura.', 'inventory-updater')));
        }
        
        $result = $this->download_file($url, $download_file);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Archivo descargado correctamente.', 'inventory-updater'),
                'file_date' => $this->get_inventory_file_date()
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Descargar archivo desde URL
     */
    private function download_file($url, $destination) {
        // Método 1: Usar wp_remote_get (con fallback si falla)
        $response = wp_remote_get($url, array(
            'timeout' => 60,
            'stream' => true,
            'filename' => $destination,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            )
        ));
        
        // Si falla, intentar con file_get_contents
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 || !file_exists($destination) || filesize($destination) === 0) {
            // Registrar el error
            error_log('Error en wp_remote_get: ' . (is_wp_error($response) ? $response->get_error_message() : 'Código HTTP: ' . wp_remote_retrieve_response_code($response)));
            
            // Método 2: Usar file_get_contents como fallback
            $file_content = @file_get_contents($url);
            
            if ($file_content === false) {
                error_log('Error en file_get_contents al descargar ' . $url);
                
                // Método 3: Intentar con cURL como último recurso
                if (function_exists('curl_init')) {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    $file_content = curl_exec($ch);
                    
                    if (curl_errno($ch)) {
                        $error_msg = curl_error($ch);
                        error_log('Error en cURL: ' . $error_msg);
                        curl_close($ch);
                        return array('success' => false, 'message' => sprintf(__('Error al descargar mediante cURL: %s', 'inventory-updater'), $error_msg));
                    }
                    
                    curl_close($ch);
                } else {
                    return array('success' => false, 'message' => __('No se pudo descargar el archivo. Todos los métodos de descarga fallaron.', 'inventory-updater'));
                }
            }
            
            // Guardar el contenido en un archivo
            $save_result = @file_put_contents($destination, $file_content);
            
            if ($save_result === false) {
                error_log('Error al guardar el archivo descargado en: ' . $destination);
                return array('success' => false, 'message' => __('No se pudo guardar el archivo descargado.', 'inventory-updater'));
            }
        }
        
        // Verificar que se creó el archivo
        if (!file_exists($destination) || filesize($destination) === 0) {
            return array('success' => false, 'message' => __('El archivo descargado está vacío o no se pudo crear.', 'inventory-updater'));
        }
        
        return array('success' => true);
    }
}
