<?php
/**
 * Plugin Name: Inventory Updater
 * Plugin URI: https://didacticosmerlin.es/inventory-updater
 * Description: Actualiza el stock de productos en WooCommerce a partir de un archivo de inventario externo.
 * Version: 1.0.0
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
     * Constructor
     */
    public function __construct() {
        // Definir constantes
        define('INVENTORY_UPDATER_VERSION', '1.0.0');
        define('INVENTORY_UPDATER_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('INVENTORY_UPDATER_PLUGIN_URL', plugin_dir_url(__FILE__));
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
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Añadir menú de administración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registrar assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Añadir AJAX handlers
        add_action('wp_ajax_inventory_updater_process', array($this, 'ajax_process_inventory_file'));
        
        // Añadir enlaces en la página de plugins
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
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
                'error_text' => __('Error en el procesamiento', 'inventory-updater')
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
     * Página de administración
     */
    public function admin_page() {
        ?>
        <div class="wrap inventory-updater-wrap">
            <h1><?php _e('Inventory Updater', 'inventory-updater'); ?></h1>
            
            <div class="inventory-updater-section">
                <h2><?php _e('Instrucciones', 'inventory-updater'); ?></h2>
                <p>
                    <?php _e('Este plugin te permite actualizar el stock de productos en WooCommerce a partir de un archivo de inventario externo.', 'inventory-updater'); ?>
                </p>
                <ol>
                    <li><?php _e('Coloca el archivo de inventario en la ubicación especificada a continuación.', 'inventory-updater'); ?></li>
                    <li><?php _e('Haz clic en "Iniciar actualización" para comenzar el proceso.', 'inventory-updater'); ?></li>
                    <li><?php _e('Se mostrará un resumen con los resultados del proceso una vez finalizado.', 'inventory-updater'); ?></li>
                </ol>
            </div>
            
            <div class="inventory-updater-section">
                <h2><?php _e('Ubicación del archivo', 'inventory-updater'); ?></h2>
                <p>
                    <?php _e('Coloca el archivo de inventario en la siguiente ruta:', 'inventory-updater'); ?>
                    <code><?php echo INVENTORY_UPDATER_UPLOADS_DIR; ?></code>
                </p>
                <p>
                    <?php _e('Nombre recomendado del archivo:', 'inventory-updater'); ?>
                    <code>inventario.txt</code>
                </p>
                
                <div class="inventory-updater-file-status">
                    <?php
                    $inventory_file = INVENTORY_UPDATER_UPLOADS_DIR . 'inventario.txt';
                    if (file_exists($inventory_file)) {
                        echo '<div class="notice notice-success inline"><p>';
                        echo sprintf(
                            __('Archivo de inventario encontrado. Última modificación: %s', 'inventory-updater'),
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($inventory_file))
                        );
                        echo '</p></div>';
                    } else {
                        echo '<div class="notice notice-warning inline"><p>';
                        echo __('No se ha encontrado el archivo de inventario.', 'inventory-updater');
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
        $inventory_file = INVENTORY_UPDATER_UPLOADS_DIR . 'inventario.txt';
        if (!file_exists($inventory_file)) {
            wp_send_json_error(array('message' => __('No se ha encontrado el archivo de inventario.', 'inventory-updater')));
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
            
            // Buscar producto por SKU o código de barras
            $product_id = null;
            
            if (!empty($sku) && isset($products_by_sku[$sku])) {
                $product_id = $products_by_sku[$sku]->ID;
            } elseif (!empty($barcode) && isset($products_by_barcode[$barcode])) {
                $product_id = $products_by_barcode[$barcode]->ID;
            }
            
            // Si encontramos el producto, actualizar el stock
            if ($product_id) {
                // Obtener información del producto
                $product_title = get_the_title($product_id);
                $old_stock = get_post_meta($product_id, '_stock', true);
                
                // Activar la gestión de stock para este producto
                update_post_meta($product_id, '_manage_stock', 'yes');
                
                // Establecer umbral de stock bajo a 1
                update_post_meta($product_id, '_low_stock_amount', 1);
                
                // Actualizar stock
                update_post_meta($product_id, '_stock', $stock);
                
                // Actualizar estado de stock
                $stock_status = $stock > 0 ? 'instock' : 'outofstock';
                update_post_meta($product_id, '_stock_status', $stock_status);
                
                // Guardar información del producto actualizado
                $results['updated_products'][] = array(
                    'id' => $product_id,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'title' => $product_title,
                    'old_stock' => $old_stock,
                    'new_stock' => $stock,
                    'stock_status' => $stock_status
                );
                
                // Incrementar contador de actualizados
                $results['updated']++;
            } else {
                // Si no encontramos el producto, añadir a la lista de no encontrados
                $results['not_found']++;
                $results['not_found_products'][] = array(
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'title' => isset($data[9]) ? $data[9] : 'N/A'
                );
            }
        }
        
        // Cerrar archivo
        fclose($handle);
        
        // Añadir productos sin SKU a los resultados
        $results['products_without_sku'] = $products_without_sku;
        $results['products_without_sku_count'] = count($products_without_sku);
        
        // Limpiar caché de WooCommerce
        wc_delete_product_transients();
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
}

// Inicializar el plugin
new Inventory_Updater();