<?php
/**
 * Plugin Name: Inventory Updater
 * Plugin URI: https://didacticosmerlin.es/inventory-updater
 * Description: Actualiza el stock de productos en WooCommerce a partir de un archivo de inventario externo.
 * Version: 1.2.0
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

// Definir constantes
define('INVENTORY_UPDATER_VERSION', '1.2.0');
define('INVENTORY_UPDATER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INVENTORY_UPDATER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INVENTORY_UPDATER_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/inventory-updater/');
define('INVENTORY_UPDATER_UPLOADS_URL', wp_upload_dir()['baseurl'] . '/inventory-updater/');

// Cargar archivos principales
require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-inventory-updater.php';

// Inicializar el plugin cuando WordPress esté listo
function inventory_updater_init() {
    // Comprobar que WooCommerce está activo
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', 'inventory_updater_woocommerce_not_active_notice');
        return;
    }
    
    global $inventory_updater;
    $inventory_updater = new Inventory_Updater();
    $inventory_updater->init();
}

function inventory_updater_woocommerce_not_active_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('Inventory Updater requiere que WooCommerce esté instalado y activado.', 'inventory-updater'); ?></p>
    </div>
    <?php
}

// Registro de activación y desactivación
register_activation_hook(__FILE__, 'inventory_updater_activate');
register_deactivation_hook(__FILE__, 'inventory_updater_deactivate');

function inventory_updater_activate() {
    require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-inventory-updater.php';
    Inventory_Updater::activate();
}

function inventory_updater_deactivate() {
    require_once INVENTORY_UPDATER_PLUGIN_DIR . 'includes/class-inventory-updater.php';
    Inventory_Updater::deactivate();
}

add_action('plugins_loaded', 'inventory_updater_init');
