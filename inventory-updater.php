<?php
/**
 * Plugin Name: Inventory Updater
 * Plugin URI: https://didacticosmerlin.es/inventory-updater
 * Description: Actualiza el stock de productos en WooCommerce a partir de un archivo de inventario externo.
 * Version: 1.1.0
 * Author: Pedro Diez
 * Author URI: https://github.com/pedroddiez
 * Text Domain: inventory-updater
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 3.5.0
 * WC tested up to: 3.5.10
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Inventory_Updater {
    /**
     * Ruta al archivo principal del plugin
     */
    private $plugin_file;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Guardar la ruta del archivo principal
        $this->plugin_file = __FILE__;
        
        // Definir constantes
        define('INVENTORY_UPDATER_VERSION', '1.1.0');
        define('INVENTORY_UPDATER_PLUGIN_DIR', plugin_dir_path($this->plugin_file));
        define('INVENTORY_UPDATER_PLUGIN_URL', plugin_dir_url($this->plugin_file));
        define('INVENTORY_UPDATER_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/inventory-updater/');
        define('INVENTORY_UPDATER_UPLOADS_URL', wp_upload_dir()['baseurl'] . '/inventory-updater/');
        
        // Inicializar el plugin
        $this->init();
    }
    
    /**
     * Inicializar el plugin
     */
    public function init() {
        // Comprobar que WooCommerce está activo
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_not_active_notice'));
            return;
        }
        
        // Crear directorio de uploads si no existe
        if (!file_exists(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_mkdir_p(INVENTORY_UPDATER_UPLOADS_DIR);
        }
        
        // Registrar hooks de activación y desactivación
        register_activation_hook($this->plugin_file, array($this, 'activate'));
        register_deactivation_hook($this->plugin_file, array($this, 'deactivate'));
        
        // Añadir menú de administración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registrar assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Añadir AJAX handlers
        add_action('wp_ajax_inventory_updater_process', array($this, 'ajax_process_inventory_file'));
        add_action('wp_ajax_inventory_updater_download', array($this, 'ajax_download_inventory_file'));
        add_action('wp_ajax_inventory_updater_save_settings', array($this, 'ajax_save_settings'));
        
        // Añadir enlaces en la página de plugins
        add_filter('plugin_action_links_' . plugin_basename($this->plugin_file), array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Verificar si WooCommerce está activo
     */
    public function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }
    
    /**
     * Mostrar aviso si WooCommerce no está activo
     */
    public function woocommerce_not_active_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Inventory Updater requiere que WooCommerce esté instalado y activado.', 'inventory-updater'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Acciones de activación del plugin
     */
    public function activate() {
        // Crear directorio para archivos subidos si no existe
        if (!file_exists(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_mkdir_p(INVENTORY_UPDATER_UPLOADS_DIR);
        }
        
        // Añadir archivo .htaccess para proteger el directorio
        $htaccess_file = INVENTORY_UPDATER_UPLOADS_DIR . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Añadir archivo index.php vacío para seguridad adicional
        $index_file = INVENTORY_UPDATER_UPLOADS_DIR . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
        
        // Configurar opciones por defecto
        $default_options = array(
            'update_stock' => 'yes',
            'update_price' => 'yes'
        );
        
        update_option('inventory_updater_options', $default_options);
    }
    
    /**
     * Acciones de desactivación del plugin
     */
    public function deactivate() {
        // Limpiar datos de caché, etc.
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Inventory Updater', 'inventory-updater'),
            __('Inventory Updater', 'inventory-updater'),
            'manage_woocommerce',
            'inventory-updater',
            array($this, 'admin_page'),
            'dashicons-update',
            56
        );
    }
    
    /**
     * Cargar assets para la administración
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_inventory-updater') {
            return;
        }
        
        // Estilos
        wp_enqueue_style(
            'inventory-updater-admin',
            INVENTORY_UPDATER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            INVENTORY_UPDATER_VERSION
        );
        
        // Scripts
        wp_enqueue_script(
            'inventory-updater-admin',
            INVENTORY_UPDATER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            INVENTORY_UPDATER_VERSION,
            true
        );
        
        // Pasar datos al script
        wp_localize_script(
            'inventory-updater-admin',
            'inventory_updater_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('inventory_updater_nonce'),
                'processing_text' => __('Procesando...', 'inventory-updater'),
                'success_text' => __('Proceso completado correctamente', 'inventory-updater'),
                'error_text' => __('Error en el procesamiento', 'inventory-updater'),
                'downloading_text' => __('Descargando archivo...', 'inventory-updater'),
                'download_success_text' => __('Archivo descargado correctamente', 'inventory-updater'),
                'download_error_text' => __('Error al descargar el archivo', 'inventory-updater'),
                'saving_text' => __('Guardando configuración...', 'inventory-updater'),
                'save_success_text' => __('Configuración guardada correctamente', 'inventory-updater'),
                'save_error_text' => __('Error al guardar la configuración', 'inventory-updater')
            )
        );
    }
    
    /**
     * Añadir enlaces en la página de plugins
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=inventory-updater') . '">' . __('Configuración', 'inventory-updater') . '</a>'
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Obtener opciones de configuración
     */
    private function get_options() {
        $default_options = array(
            'update_stock' => 'yes',
            'update_price' => 'yes'
        );
        
        $options = get_option('inventory_updater_options', $default_options);
        
        return $options;
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
        
        // Guardar configuración
        $options = array(
            'update_stock' => $update_stock,
            'update_price' => $update_price
        );
        
        update_option('inventory_updater_options', $options);
        
        wp_send_json_success(array('message' => __('Configuración guardada correctamente.', 'inventory-updater')));
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        // Obtener opciones
        $options = $this->get_options();
        $update_stock = isset($options['update_stock']) ? $options['update_stock'] : 'yes';
        $update_price = isset($options['update_price']) ? $options['update_price'] : 'yes';
        ?>
        <div class="wrap inventory-updater-wrap">
            <h1><?php _e('Inventory Updater', 'inventory-updater'); ?></h1>
            
            <div class="inventory-updater-section">
                <h2><?php _e('Configuración', 'inventory-updater'); ?></h2>
                <p><?php _e('Selecciona qué datos quieres actualizar cuando se procese el archivo de inventario:', 'inventory-updater'); ?></p>
                
                <div class="inventory-updater-settings">
                    <div class="inventory-updater-setting">
                        <label>
                            <input type="checkbox" id="update-stock" <?php checked($update_stock, 'yes'); ?>>
                            <?php _e('Actualizar stock', 'inventory-updater'); ?>
                        </label>
                        <p class="description"><?php _e('Si esta opción está activada, se actualizará el stock de los productos.', 'inventory-updater'); ?></p>
                    </div>
                    
                    <div class="inventory-updater-setting">
                        <label>
                            <input type="checkbox" id="update-price" <?php checked($update_price, 'yes'); ?>>
                            <?php _e('Actualizar precio', 'inventory-updater'); ?>
                        </label>
                        <p class="description"><?php _e('Si esta opción está activada, se actualizará el precio de los productos.', 'inventory-updater'); ?></p>
                    </div>
                    
                    <div class="inventory-updater-setting-actions">
                        <button id="save-settings" class="button button-primary"><?php _e('Guardar configuración', 'inventory-updater'); ?></button>
                        <span id="settings-saved-message" style="display: none; color: green; margin-left: 10px;"><?php _e('Configuración guardada', 'inventory-updater'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="inventory-updater-section">
                <h2><?php _e('Instrucciones', 'inventory-updater'); ?></h2>
                <p>
                    <?php _e('Este plugin te permite actualizar el stock y/o precio de productos en WooCommerce a partir de un archivo de inventario externo.', 'inventory-updater'); ?>
                </p>
                <ol>
                    <li><?php _e('Coloca el archivo "articulos.txt" en la ubicación especificada o descárgalo desde una URL.', 'inventory-updater'); ?></li>
                    <li><?php _e('Haz clic en "Iniciar actualización" para comenzar el proceso.', 'inventory-updater'); ?></li>
                    <li><?php _e('Se mostrará un resumen con los resultados del proceso una vez finalizado.', 'inventory-updater'); ?></li>
                </ol>
            </div>
            
            <div class="inventory-updater-section">
                <h2><?php _e('Archivo de inventario', 'inventory-updater'); ?></h2>
                
                <div class="inventory-updater-remote-download">
                    <h3><?php _e('Descargar desde URL', 'inventory-updater'); ?></h3>
                    <p>
                        <?php _e('Puedes descargar el archivo de inventario desde una URL remota:', 'inventory-updater'); ?>
                    </p>
                    <div class="inventory-updater-url-input">
                        <input type="url" id="inventory-updater-url" class="regular-text" placeholder="https://ejemplo.com/articulos.txt">
                        <button id="inventory-updater-download" class="button">
                            <?php _e('Descargar archivo', 'inventory-updater'); ?>
                        </button>
                    </div>
                    <div id="inventory-updater-download-progress" style="display: none;">
                        <div class="inventory-updater-progress-bar">
                            <div class="inventory-updater-progress-bar-fill"></div>
                        </div>
                        <p class="inventory-updater-progress-text"></p>
                    </div>
                </div>
                
                <h3><?php _e('Ubicación del archivo', 'inventory-updater'); ?></h3>
                <p>
                    <?php _e('Coloca el archivo de inventario en la siguiente ruta:', 'inventory-updater'); ?>
                    <code><?php echo INVENTORY_UPDATER_UPLOADS_DIR; ?></code>
                </p>
                <p>
                    <?php _e('Nombre del archivo:', 'inventory-updater'); ?>
                    <code>articulos.txt</code>
                </p>
                
                <div class="inventory-updater-file-status">
                    <?php
                    $inventory_file = INVENTORY_UPDATER_UPLOADS_DIR . 'articulos.txt';
                    if (file_exists($inventory_file)) {
                        echo '<div class="notice notice-success inline"><p>';
                        echo sprintf(
                            __('Archivo de inventario encontrado. Última modificación: %s', 'inventory-updater'),
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($inventory_file))
                        );
                        echo '</p></div>';
                    } else {
                        echo '<div class="notice notice-warning inline"><p>';
                        echo __('No se ha encontrado el archivo articulos.txt.', 'inventory-updater');
                        echo '</p></div>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="inventory-updater-section">
                <h2><?php _e('Actualizar inventario', 'inventory-updater'); ?></h2>
                <p>
                    <?php _e('Haz clic en el botón para comenzar la actualización del inventario.', 'inventory-updater'); ?>
                </p>
                
                <button id="inventory-updater-process" class="button button-primary">
                    <?php _e('Iniciar actualización', 'inventory-updater'); ?>
                </button>
                
                <div id="inventory-updater-progress" style="display: none;">
                    <div class="inventory-updater-progress-bar">
                        <div class="inventory-updater-progress-bar-fill"></div>
                    </div>
                    <p class="inventory-updater-progress-text"></p>
                </div>
            </div>
            
            <div id="inventory-updater-results" class="inventory-updater-section" style="display: none;">
                <h2><?php _e('Resultados', 'inventory-updater'); ?></h2>
                <div class="inventory-updater-results-content"></div>
            </div>
        </div>
        <?php
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
        $download_file = INVENTORY_UPDATER_UPLOADS_DIR . 'articulos.txt';
        
        // Asegurarse de que el directorio existe y tiene permisos de escritura
        if (!file_exists(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_mkdir_p(INVENTORY_UPDATER_UPLOADS_DIR);
            // Establecer permisos
            @chmod(INVENTORY_UPDATER_UPLOADS_DIR, 0755);
        }
        
        if (!is_writable(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_send_json_error(array('message' => __('El directorio de destino no tiene permisos de escritura.', 'inventory-updater')));
        }
        
        // Método 1: Usar wp_remote_get (con fallback si falla)
        $response = wp_remote_get($url, array(
            'timeout' => 60,
            'stream' => true,
            'filename' => $download_file,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            )
        ));
        
        // Si falla, intentar con file_get_contents
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 || !file_exists($download_file) || filesize($download_file) === 0) {
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
                        wp_send_json_error(array('message' => sprintf(__('Error al descargar mediante cURL: %s', 'inventory-updater'), $error_msg)));
                    }
                    
                    curl_close($ch);
                } else {
                    wp_send_json_error(array('message' => __('No se pudo descargar el archivo. Todos los métodos de descarga fallaron.', 'inventory-updater')));
                }
            }
            
            // Guardar el contenido en un archivo
            $save_result = @file_put_contents($download_file, $file_content);
            
            if ($save_result === false) {
                error_log('Error al guardar el archivo descargado en: ' . $download_file);
                wp_send_json_error(array('message' => __('No se pudo guardar el archivo descargado.', 'inventory-updater')));
            }
        }
        
        // Verificar que se creó el archivo
        if (!file_exists($download_file) || filesize($download_file) === 0) {
            wp_send_json_error(array('message' => __('El archivo descargado está vacío o no se pudo crear.', 'inventory-updater')));
        }
        
        wp_send_json_success(array(
            'message' => __('Archivo descargado correctamente.', 'inventory-updater'),
            'file_date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($download_file))
        ));
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
        $inventory_file = INVENTORY_UPDATER_UPLOADS_DIR . 'articulos.txt';
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
        $options = $this->get_options();
        $update_stock = ($options['update_stock'] === 'yes');
        $update_price = ($options['update_price'] === 'yes');
        
        // Inicializar resultados
        $results = array(
            'updated' => 0,
            'not_found' => 0,
            'errors' => 0,
            'not_found_products' => array(),
            'updated_products' => array(),
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
        
        // Obtener todos los productos de WooCommerce con meta_key _sku
        $products_by_sku = array();
        $products_by_barcode = array();
        
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
            }
            
            if (!empty($product->barcode)) {
                $products_by_barcode[$product->barcode] = $product;
            }
        }
        
        // Productos sin SKU
        $products_without_sku = array();
        foreach ($products as $product) {
            if (empty($product->sku)) {
                $products_without_sku[] = array(
                    'id' => $product->ID,
                    'title' => $product->post_title
                );
            }
        }
        
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
            
            // Extraer precio (asumimos que está en la posición 1, ajústalo según tu estructura de datos)
            $price = !empty($data[1]) ? floatval(str_replace(',', '.', $data[1])) : 0;
            
            // Buscar producto por SKU o código de barras
            $product_id = null;
            
            if (!empty($sku) && isset($products_by_sku[$sku])) {
                $product_id = $products_by_sku[$sku]->ID;
            } elseif (!empty($barcode) && isset($products_by_barcode[$barcode])) {
                $product_id = $products_by_barcode[$barcode]->ID;
            }
            
            // Si encontramos el producto, actualizar el stock y/o precio
            if ($product_id) {
                // Obtener información del producto
                $product_title = get_the_title($product_id);
                $old_stock = get_post_meta($product_id, '_stock', true);
                $old_price = get_post_meta($product_id, '_regular_price', true);
                
                $updated_data = array();
                
                // Actualizar stock si está habilitado
                if ($update_stock) {
                    // Activar la gestión de stock para este producto
                    update_post_meta($product_id, '_manage_stock', 'yes');
                    
                    // Establecer umbral de stock bajo a 1
                    update_post_meta($product_id, '_low_stock_amount', 1);
                    
                    // Actualizar stock
                    update_post_meta($product_id, '_stock', $stock);
                    
                    // Actualizar estado de stock
                    $stock_status = $stock > 0 ? 'instock' : 'outofstock';
                    update_post_meta($product_id, '_stock_status', $stock_status);
                    
                    // Guardar datos de stock actualizados
                    $updated_data['old_stock'] = $old_stock;
                    $updated_data['new_stock'] = $stock;
                    $updated_data['stock_status'] = $stock_status;
                }
                
                // Actualizar precio si está habilitado
                if ($update_price && $price > 0) {
                    // Actualizar precio regular
                    update_post_meta($product_id, '_regular_price', number_format($price, 2, '.', ''));
                    
                    // Actualizar precio de venta (igual al regular si no hay descuento)
                    update_post_meta($product_id, '_price', number_format($price, 2, '.', ''));
                    
                    // Guardar datos de precio actualizados
                    $updated_data['old_price'] = $old_price;
                    $updated_data['new_price'] = number_format($price, 2, '.', '');
                }
                
                // Guardar información del producto actualizado
                $results['updated_products'][] = array_merge(
                    array(
                        'id' => $product_id,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'title' => $product_title
                    ),
                    $updated_data
                );
                
                // Incrementar contador de actualizados
                $results['updated']++;
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
        
        // Añadir productos sin SKU a los resultados
        $results['products_without_sku'] = $products_without_sku;
        $results['products_without_sku_count'] = count($products_without_sku);
        
        // Añadir información de configuración a los resultados
        $results['update_stock'] = $update_stock;
        $results['update_price'] = $update_price;
        
        // Limpiar caché de WooCommerce
        wc_delete_product_transients();
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
}

// Inicializar el plugin cuando WordPress esté listo
function inventory_updater_init() {
    global $inventory_updater;
    $inventory_updater = new Inventory_Updater();
}
add_action('plugins_loaded', 'inventory_updater_init');