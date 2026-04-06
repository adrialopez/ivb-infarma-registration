<?php
/**
 * Plugin Name: IVB Packs & POS
 * Plugin URI: https://thinkingidea.com/
 * Description: Gestión de packs con creación de pedidos (TPV/Infarma) y añadir al carrito al crear usuarios
 * Version: 0.4.4
 * Author: Thinking Idea
 * Author URI: https://thinkingidea.com/
 * Text Domain: ivb-infarma-registration
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar que WooCommerce esté activo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>IVB Infarma Registration</strong> requiere WooCommerce para funcionar.</p></div>';
    });
    return;
}

// Definir constantes del plugin
define('IVBIR_VERSION', '0.4.4');
define('IVBIR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IVBIR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IVBIR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Cargar clases principales
require_once IVBIR_PLUGIN_DIR . 'includes/class-ivbir-pack-manager.php';
require_once IVBIR_PLUGIN_DIR . 'includes/class-ivbir-admin.php';
require_once IVBIR_PLUGIN_DIR . 'includes/class-ivbir-user-form.php';
require_once IVBIR_PLUGIN_DIR . 'includes/class-ivbir-order-handler.php';
require_once IVBIR_PLUGIN_DIR . 'includes/class-ivbir-cart-handler.php';

/**
 * Clase principal del plugin
 */
class IVB_Infarma_Registration {

    private static $instance = null;

    public $pack_manager;
    public $admin;
    public $user_form;
    public $order_handler;
    public $cart_handler;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->init_classes();
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'check_database_tables_scheduled'));
    }

    private function init_classes() {
        $this->pack_manager = new IVBIR_Pack_Manager();

        if (is_admin()) {
            $this->admin = new IVBIR_Admin();
            $this->user_form = new IVBIR_User_Form();
        }

        $this->order_handler = new IVBIR_Order_Handler();
        $this->cart_handler = new IVBIR_Cart_Handler();
    }

    public function activate() {
        // Crear opciones por defecto
        $default_settings = array(
            'allowed_roles' => array('farmacia', 'nutricionista', 'clinica'),
            'default_role' => 'farmacia',
            'allowed_payment_methods' => array('bacs', 'redsys'),
            'default_payment_method' => 'bacs',
            'transfer_coupon_code' => '2DESCTF',
            'logo_url' => 'https://ivbwellness.com/cdn/shop/files/IVB_Logo_-_large_c0f1390b-277c-4872-8263-60bd417fded3.svg?v=1739374189',
            'form_title' => 'ALTA + PEDIDO INFARMA 2025',
            'user_role_required' => '',
        );

        add_option('ivbir_settings', $default_settings);
        add_option('ivbir_packs', array());

        // Crear tablas de base de datos
        $this->create_database_tables();

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Verificar tablas de forma programada (con transient para evitar consultas innecesarias)
     */
    public function check_database_tables_scheduled() {
        // Solo verificar si no hay transient activo (verificar cada hora)
        if (false === get_transient('ivbir_tables_checked')) {
            $this->check_database_tables();
            set_transient('ivbir_tables_checked', true, HOUR_IN_SECONDS);
        }
    }

    /**
     * Verificar y crear tablas si no existen
     */
    public function check_database_tables() {
        global $wpdb;

        $table_pack_categories = $wpdb->prefix . 'ivbir_pack_categories';
        $table_pack_products = $wpdb->prefix . 'ivbir_pack_products';

        // Verificar si las tablas existen
        $categories_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_pack_categories'") === $table_pack_categories;
        $products_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_pack_products'") === $table_pack_products;

        // Si alguna tabla no existe, crearlas
        if (!$categories_exists || !$products_exists) {
            $this->create_database_tables();
        }
    }

    /**
     * Crear tablas de base de datos
     */
    private function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_pack_categories = $wpdb->prefix . 'ivbir_pack_categories';
        $sql_categories = "CREATE TABLE IF NOT EXISTS $table_pack_categories (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pack_id varchar(50) NOT NULL,
            category_name varchar(100) NOT NULL,
            category_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pack_id (pack_id)
        ) $charset_collate;";

        $table_pack_products = $wpdb->prefix . 'ivbir_pack_products';
        $sql_products = "CREATE TABLE IF NOT EXISTS $table_pack_products (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pack_id varchar(50) NOT NULL,
            category_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            quantity int(11) DEFAULT 1,
            product_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pack_id (pack_id),
            KEY category_id (category_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_categories);
        dbDelta($sql_products);
    }

    public function load_textdomain() {
        load_plugin_textdomain('ivb-infarma-registration', false, dirname(IVBIR_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Caché estática para configuración
     */
    private static $settings_cache = null;

    /**
     * Obtener configuración (con caché estática)
     */
    public static function get_setting($key, $default = '') {
        if (null === self::$settings_cache) {
            self::$settings_cache = get_option('ivbir_settings', array());
        }
        return isset(self::$settings_cache[$key]) ? self::$settings_cache[$key] : $default;
    }

    /**
     * Actualizar configuración (limpia caché)
     */
    public static function update_setting($key, $value) {
        if (null === self::$settings_cache) {
            self::$settings_cache = get_option('ivbir_settings', array());
        }
        self::$settings_cache[$key] = $value;
        update_option('ivbir_settings', self::$settings_cache);
    }
}

// Inicializar el plugin
function ivbir() {
    return IVB_Infarma_Registration::get_instance();
}

// Solo inicializar después de que WooCommerce esté cargado
add_action('woocommerce_loaded', 'ivbir');
