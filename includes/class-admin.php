<?php
/**
 * Clase para gestionar la interfaz de administración
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Inventory_Updater_Admin {
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
        // Añadir menú de administración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registrar assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
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
     * Página de administración
     */
    public function admin_page() {
        // Obtener opciones
        $options = $this->plugin->settings->get_options();
        $update_stock = isset($options['update_stock']) ? $options['update_stock'] : 'yes';
        $update_price = isset($options['update_price']) ? $options['update_price'] : 'yes';
        $maintain_discounts = isset($options['maintain_discounts']) ? $options['maintain_discounts'] : 'no';
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
                    
                    <div class="inventory-updater-setting" id="maintain-discounts-container" style="<?php echo $update_price === 'yes' ? '' : 'display: none;'; ?>">
                        <label>
                            <input type="checkbox" id="maintain-discounts" <?php checked($maintain_discounts, 'yes'); ?>>
                            <?php _e('Mantener descuentos', 'inventory-updater'); ?>
                        </label>
                        <p class="description"><?php _e('Si esta opción está activada, se mantendrá el porcentaje de descuento al actualizar los precios. El precio de venta se redondeará a 5 céntimos.', 'inventory-updater'); ?></p>
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
}