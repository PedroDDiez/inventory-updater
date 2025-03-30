<?php
/**
 * Clase principal del plugin Inventory Updater
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Inventory_Updater {
    /**
     * Instancia única de la clase (patrón Singleton)
     */
    private static $instance = null;
    
    /**
     * Instancias de las clases del plugin
     */
    public $admin;
    public $file_handler;
    public $inventory_processor;
    public $product_updater;
    public $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Cargar dependencias
        $this->load_dependencies();
    }
    
    /**
     * Inicializar el plugin
     */
    public function init() {
        // Crear directorio de uploads si no existe
        if (!file_exists(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_mkdir_p(INVENTORY_UPDATER_UPLOADS_DIR);
        }
        
        // Inicializar componentes
        $this->settings->init();
        $this->admin->init();
        $this->file_handler->init();
        $this->inventory_processor->init();
        $this->product_updater->init();
        
        // Añadir enlaces en la página de plugins
        add_filter('plugin_action_links_' . plugin_basename(INVENTORY_UPDATER_PLUGIN_DIR . 'inventory-updater.php'), array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        // Incluir archivos de clases
        require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-settings.php';
        require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-file-handler.php';
        require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-inventory-processor.php';
        require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-product-updater.php';
        
        // Inicializar objetos
        $this->settings = new Inventory_Updater_Settings();
        $this->admin = new Inventory_Updater_Admin($this);
        $this->file_handler = new Inventory_Updater_File_Handler($this);
        $this->product_updater = new Inventory_Updater_Product_Updater($this);
        $this->inventory_processor = new Inventory_Updater_Processor($this);
    }
    
    /**
     * Método de activación del plugin
     */
    public static function activate() {
        // Crear directorio para archivos subidos si no existe
        if (!file_exists(INVENTORY_UPDATER_UPLOADS_DIR)) {
            wp_mkdir_p(INVENTORY_UPDATER_UPLOADS_DIR);
        }
        
        // Añadir archivo .htaccess para proteger el directorio
        $htaccess_file = INVENTORY_UPDATER_UPLOADS_DIR . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            @file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Añadir archivo index.php vacío para seguridad adicional
        $index_file = INVENTORY_UPDATER_UPLOADS_DIR . 'index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden');
        }
        
        // Configurar opciones por defecto
        $default_options = array(
            'update_stock' => 'yes',
            'update_price' => 'yes',
            'maintain_discounts' => 'no'
        );
        
        update_option('inventory_updater_options', $default_options);
    }
    
    /**
     * Método de desactivación del plugin
     */
    public static function deactivate() {
        // Limpiar datos de caché, etc.
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
     * Obtener instancia única (patrón Singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
}