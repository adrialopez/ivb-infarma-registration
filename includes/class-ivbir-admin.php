<?php
/**
 * Panel de Administración
 *
 * @package IVB_Infarma_Registration
 * @since 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class IVBIR_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_ivbir_save_pack', array($this, 'ajax_save_pack'));
        add_action('wp_ajax_ivbir_delete_pack', array($this, 'ajax_delete_pack'));
        add_action('wp_ajax_ivbir_save_pack_category', array($this, 'ajax_save_pack_category'));
        add_action('wp_ajax_ivbir_delete_pack_category', array($this, 'ajax_delete_pack_category'));
        add_action('wp_ajax_ivbir_add_product_to_pack', array($this, 'ajax_add_product_to_pack'));
        add_action('wp_ajax_ivbir_remove_product_from_pack', array($this, 'ajax_remove_product_from_pack'));
        add_action('wp_ajax_ivbir_update_product_quantity', array($this, 'ajax_update_product_quantity'));
        add_action('wp_ajax_ivbir_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_ivbir_reorder_items', array($this, 'ajax_reorder_items'));
        add_action('wp_ajax_ivbir_get_pack_summary', array($this, 'ajax_get_pack_summary'));
    }

    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('IVB Infarma', 'ivb-infarma-registration'),
            __('IVB Infarma', 'ivb-infarma-registration'),
            'manage_woocommerce',
            'ivbir-settings',
            array($this, 'render_settings_page'),
            'dashicons-groups',
            56
        );

        add_submenu_page(
            'ivbir-settings',
            __('Configuración', 'ivb-infarma-registration'),
            __('Configuración', 'ivb-infarma-registration'),
            'manage_woocommerce',
            'ivbir-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ivbir-settings',
            __('Gestionar Packs', 'ivb-infarma-registration'),
            __('Gestionar Packs', 'ivb-infarma-registration'),
            'manage_woocommerce',
            'ivbir-packs',
            array($this, 'render_packs_page')
        );

        add_submenu_page(
            'ivbir-settings',
            __('Editar Pack', 'ivb-infarma-registration'),
            null, // No mostrar en menú
            'manage_woocommerce',
            'ivbir-edit-pack',
            array($this, 'render_edit_pack_page')
        );
    }

    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('ivbir_settings_group', 'ivbir_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'ivbir_general_section',
            __('Configuración General', 'ivb-infarma-registration'),
            null,
            'ivbir-settings'
        );

        add_settings_field(
            'logo_url',
            __('URL del Logo', 'ivb-infarma-registration'),
            array($this, 'render_text_field'),
            'ivbir-settings',
            'ivbir_general_section',
            array('field' => 'logo_url', 'description' => __('URL de la imagen del logo para el formulario', 'ivb-infarma-registration'))
        );

        add_settings_field(
            'form_title',
            __('Título del Formulario', 'ivb-infarma-registration'),
            array($this, 'render_text_field'),
            'ivbir-settings',
            'ivbir_general_section',
            array('field' => 'form_title', 'placeholder' => 'ALTA + PEDIDO INFARMA 2025')
        );

        add_settings_field(
            'user_role_required',
            __('Rol requerido para usar formulario', 'ivb-infarma-registration'),
            array($this, 'render_text_field'),
            'ivbir-settings',
            'ivbir_general_section',
            array('field' => 'user_role_required', 'placeholder' => 'infarma', 'description' => __('Solo usuarios con este rol verán el formulario personalizado', 'ivb-infarma-registration'))
        );

        add_settings_section(
            'ivbir_roles_section',
            __('Roles de Usuario', 'ivb-infarma-registration'),
            null,
            'ivbir-settings'
        );

        add_settings_field(
            'allowed_roles',
            __('Roles permitidos', 'ivb-infarma-registration'),
            array($this, 'render_roles_field'),
            'ivbir-settings',
            'ivbir_roles_section'
        );

        add_settings_field(
            'default_role',
            __('Rol por defecto', 'ivb-infarma-registration'),
            array($this, 'render_default_role_field'),
            'ivbir-settings',
            'ivbir_roles_section'
        );

        add_settings_section(
            'ivbir_payment_section',
            __('Métodos de Pago', 'ivb-infarma-registration'),
            null,
            'ivbir-settings'
        );

        add_settings_field(
            'allowed_payment_methods',
            __('Métodos de pago permitidos', 'ivb-infarma-registration'),
            array($this, 'render_payment_methods_field'),
            'ivbir-settings',
            'ivbir_payment_section'
        );

        add_settings_field(
            'transfer_coupon_code',
            __('Cupón para transferencia', 'ivb-infarma-registration'),
            array($this, 'render_text_field'),
            'ivbir-settings',
            'ivbir_payment_section',
            array('field' => 'transfer_coupon_code', 'placeholder' => '2DESCTF', 'description' => __('Cupón que se aplica automáticamente al pagar por transferencia', 'ivb-infarma-registration'))
        );
    }

    /**
     * Sanitizar configuraciones
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['logo_url'])) {
            $sanitized['logo_url'] = esc_url_raw($input['logo_url']);
        }

        if (isset($input['form_title'])) {
            $sanitized['form_title'] = sanitize_text_field($input['form_title']);
        }

        if (isset($input['user_role_required'])) {
            $sanitized['user_role_required'] = sanitize_text_field($input['user_role_required']);
        }

        if (isset($input['allowed_roles']) && is_array($input['allowed_roles'])) {
            $sanitized['allowed_roles'] = array_map('sanitize_text_field', $input['allowed_roles']);
        }

        if (isset($input['default_role'])) {
            $sanitized['default_role'] = sanitize_text_field($input['default_role']);
        }

        if (isset($input['allowed_payment_methods']) && is_array($input['allowed_payment_methods'])) {
            $sanitized['allowed_payment_methods'] = array_map('sanitize_text_field', $input['allowed_payment_methods']);
        }

        if (isset($input['default_payment_method'])) {
            $sanitized['default_payment_method'] = sanitize_text_field($input['default_payment_method']);
        }

        if (isset($input['transfer_coupon_code'])) {
            $sanitized['transfer_coupon_code'] = sanitize_text_field($input['transfer_coupon_code']);
        }

        return $sanitized;
    }

    /**
     * Renderizar campo de texto
     */
    public function render_text_field($args) {
        $settings = get_option('ivbir_settings', array());
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? $args['description'] : '';

        echo '<input type="text" name="ivbir_settings[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" style="width: 100%; max-width: 500px;">';

        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Renderizar campo de roles
     */
    public function render_roles_field() {
        $settings = get_option('ivbir_settings', array());
        $allowed_roles = isset($settings['allowed_roles']) ? $settings['allowed_roles'] : array('farmacia', 'nutricionista', 'clinica');

        $all_roles = wp_roles()->get_names();

        echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
        foreach ($all_roles as $role_key => $role_name) {
            $checked = in_array($role_key, $allowed_roles) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="ivbir_settings[allowed_roles][]" value="' . esc_attr($role_key) . '" ' . $checked . '> ';
            echo esc_html($role_name);
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . __('Roles que se mostrarán en el formulario de registro', 'ivb-infarma-registration') . '</p>';
    }

    /**
     * Renderizar campo de rol por defecto
     */
    public function render_default_role_field() {
        $settings = get_option('ivbir_settings', array());
        $default_role = isset($settings['default_role']) ? $settings['default_role'] : 'farmacia';
        $allowed_roles = isset($settings['allowed_roles']) ? $settings['allowed_roles'] : array('farmacia', 'nutricionista', 'clinica');

        $all_roles = wp_roles()->get_names();

        echo '<select name="ivbir_settings[default_role]">';
        foreach ($all_roles as $role_key => $role_name) {
            if (in_array($role_key, $allowed_roles)) {
                $selected = ($role_key === $default_role) ? 'selected' : '';
                echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role_name) . '</option>';
            }
        }
        echo '</select>';
    }

    /**
     * Renderizar campo de métodos de pago
     */
    public function render_payment_methods_field() {
        $settings = get_option('ivbir_settings', array());
        $allowed_methods = isset($settings['allowed_payment_methods']) ? $settings['allowed_payment_methods'] : array('bacs', 'redsys');

        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

        echo '<div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
        foreach ($available_gateways as $gateway_id => $gateway) {
            $checked = in_array($gateway_id, $allowed_methods) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="ivbir_settings[allowed_payment_methods][]" value="' . esc_attr($gateway_id) . '" ' . $checked . '> ';
            echo esc_html($gateway->get_title());
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . __('Métodos de pago disponibles en el formulario', 'ivb-infarma-registration') . '</p>';
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        ?>
        <div class="wrap ivbir-wrap">
            <h1>
                <span class="dashicons dashicons-groups" style="font-size: 30px; margin-right: 10px;"></span>
                <?php _e('IVB Infarma Registration', 'ivb-infarma-registration'); ?>
            </h1>

            <div class="ivbir-version-badge">
                <?php printf(__('Versión %s (Beta)', 'ivb-infarma-registration'), IVBIR_VERSION); ?>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('ivbir_settings_group');
                do_settings_sections('ivbir-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renderizar página de packs
     */
    public function render_packs_page() {
        $pack_manager = ivbir()->pack_manager;
        $packs = $pack_manager->get_all_packs();
        ?>
        <div class="wrap ivbir-wrap">
            <h1>
                <span class="dashicons dashicons-archive" style="font-size: 30px; margin-right: 10px;"></span>
                <?php _e('Gestionar Packs', 'ivb-infarma-registration'); ?>
                <a href="<?php echo admin_url('admin.php?page=ivbir-edit-pack&action=new'); ?>" class="page-title-action">
                    <?php _e('Añadir Nuevo Pack', 'ivb-infarma-registration'); ?>
                </a>
            </h1>

            <?php if (empty($packs)): ?>
                <div class="ivbir-empty-state">
                    <span class="dashicons dashicons-archive" style="font-size: 60px; color: #ccc;"></span>
                    <h2><?php _e('No hay packs configurados', 'ivb-infarma-registration'); ?></h2>
                    <p><?php _e('Crea tu primer pack para comenzar a usarlo en el formulario de registro.', 'ivb-infarma-registration'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=ivbir-edit-pack&action=new'); ?>" class="button button-primary button-hero">
                        <?php _e('Crear Primer Pack', 'ivb-infarma-registration'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="ivbir-packs-grid">
                    <?php foreach ($packs as $pack_id => $pack):
                        $summary = $pack_manager->get_pack_summary($pack_id);
                    ?>
                        <div class="ivbir-pack-card <?php echo $pack['active'] ? '' : 'ivbir-pack-inactive'; ?>">
                            <div class="ivbir-pack-header">
                                <h3><?php echo esc_html($pack['name']); ?></h3>
                                <span class="ivbir-pack-status <?php echo $pack['active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $pack['active'] ? __('Activo', 'ivb-infarma-registration') : __('Inactivo', 'ivb-infarma-registration'); ?>
                                </span>
                            </div>

                            <div class="ivbir-pack-body">
                                <?php if ($pack['description']): ?>
                                    <p class="ivbir-pack-description"><?php echo esc_html($pack['description']); ?></p>
                                <?php endif; ?>

                                <div class="ivbir-pack-stats">
                                    <div class="ivbir-stat">
                                        <span class="ivbir-stat-number"><?php echo $summary['total_products']; ?></span>
                                        <span class="ivbir-stat-label"><?php _e('Productos', 'ivb-infarma-registration'); ?></span>
                                    </div>
                                    <div class="ivbir-stat">
                                        <span class="ivbir-stat-number"><?php echo $summary['total_items']; ?></span>
                                        <span class="ivbir-stat-label"><?php _e('Unidades', 'ivb-infarma-registration'); ?></span>
                                    </div>
                                </div>

                                <div class="ivbir-pack-pricing">
                                    <?php if ($pack['discount_percentage'] > 0): ?>
                                        <span class="ivbir-price-original"><?php echo wc_price($summary['original_price']); ?></span>
                                        <span class="ivbir-price-discounted"><?php echo wc_price($summary['discounted_price']); ?></span>
                                        <span class="ivbir-discount-badge">-<?php echo $pack['discount_percentage']; ?>%</span>
                                    <?php else: ?>
                                        <span class="ivbir-price-final"><?php echo wc_price($summary['original_price']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ivbir-pack-footer">
                                <a href="<?php echo admin_url('admin.php?page=ivbir-edit-pack&pack_id=' . $pack_id); ?>" class="button button-primary">
                                    <span class="dashicons dashicons-edit"></span> <?php _e('Editar', 'ivb-infarma-registration'); ?>
                                </a>
                                <button type="button" class="button ivbir-duplicate-pack" data-pack-id="<?php echo esc_attr($pack_id); ?>">
                                    <span class="dashicons dashicons-admin-page"></span>
                                </button>
                                <button type="button" class="button ivbir-delete-pack" data-pack-id="<?php echo esc_attr($pack_id); ?>" data-pack-name="<?php echo esc_attr($pack['name']); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderizar página de edición de pack
     */
    public function render_edit_pack_page() {
        $pack_manager = ivbir()->pack_manager;
        $pack_id = isset($_GET['pack_id']) ? sanitize_text_field($_GET['pack_id']) : '';
        $is_new = isset($_GET['action']) && $_GET['action'] === 'new';

        // Si es nuevo, crear el pack inmediatamente con datos por defecto
        if ($is_new && empty($pack_id)) {
            $pack_id = 'nuevo_pack_' . time();
            $default_data = array(
                'name' => '',
                'description' => '',
                'discount_percentage' => 0,
                'active' => true,
                'order' => 0,
            );
            $pack_manager->save_pack($pack_id, $default_data);

            // Redirigir para limpiar la URL
            wp_redirect(admin_url('admin.php?page=ivbir-edit-pack&pack_id=' . $pack_id));
            exit;
        }

        $pack = $pack_manager->get_pack($pack_id);
        $categories = $pack_id ? $pack_manager->get_pack_categories($pack_id) : array();
        $products = $pack_id ? $pack_manager->get_pack_products($pack_id) : array();

        // Procesar guardado si es POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ivbir_save_pack_nonce'])) {
            if (wp_verify_nonce($_POST['ivbir_save_pack_nonce'], 'ivbir_save_pack')) {
                $pack_data = array(
                    'name' => sanitize_text_field($_POST['pack_name']),
                    'description' => sanitize_textarea_field($_POST['pack_description'] ?? ''),
                    'discount_percentage' => floatval($_POST['pack_discount'] ?? 0),
                    'active' => isset($_POST['pack_active']),
                    'order' => intval($_POST['pack_order'] ?? 0),
                );

                // Guardar pack con el mismo ID
                $pack_manager->save_pack($pack_id, $pack_data);

                // Mensaje de éxito
                echo '<div class="notice notice-success is-dismissible"><p>Pack guardado correctamente.</p></div>';

                // Recargar datos del pack
                $pack = $pack_manager->get_pack($pack_id);
            }
        }
        ?>
        <div class="wrap ivbir-wrap ivbir-edit-pack">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=ivbir-packs'); ?>" class="ivbir-back-link">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                </a>
                <?php
                    // Mostrar "Nuevo Pack" solo si el nombre está vacío
                    $is_empty_pack = empty($pack['name']);
                    echo $is_empty_pack ? __('Nuevo Pack', 'ivb-infarma-registration') : __('Editar Pack', 'ivb-infarma-registration');
                ?>
            </h1>

            <div class="ivbir-edit-pack-layout">
                <!-- Columna izquierda: Datos del pack -->
                <div class="ivbir-pack-details">
                    <form method="post" class="ivbir-pack-form">
                        <?php wp_nonce_field('ivbir_save_pack', 'ivbir_save_pack_nonce'); ?>
                        <input type="hidden" name="old_pack_id" value="<?php echo esc_attr($pack_id); ?>">

                        <div class="ivbir-form-section">
                            <h2><?php _e('Información del Pack', 'ivb-infarma-registration'); ?></h2>

                            <div class="ivbir-form-field">
                                <label for="pack_name"><?php _e('Nombre del Pack', 'ivb-infarma-registration'); ?> <span class="required">*</span></label>
                                <input type="text" id="pack_name" name="pack_name" value="<?php echo esc_attr($pack['name'] ?? ''); ?>" required>
                            </div>

                            <div class="ivbir-form-field">
                                <label for="pack_description"><?php _e('Descripción', 'ivb-infarma-registration'); ?></label>
                                <textarea id="pack_description" name="pack_description" rows="3"><?php echo esc_textarea($pack['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="ivbir-form-row">
                                <div class="ivbir-form-field">
                                    <label for="pack_discount"><?php _e('Descuento (%)', 'ivb-infarma-registration'); ?></label>
                                    <input type="number" id="pack_discount" name="pack_discount" value="<?php echo esc_attr($pack['discount_percentage'] ?? 0); ?>" min="0" max="100" step="any">
                                </div>

                                <div class="ivbir-form-field">
                                    <label for="pack_order"><?php _e('Orden', 'ivb-infarma-registration'); ?></label>
                                    <input type="number" id="pack_order" name="pack_order" value="<?php echo esc_attr($pack['order'] ?? 0); ?>" min="0">
                                </div>
                            </div>

                            <div class="ivbir-form-field ivbir-checkbox-field">
                                <label>
                                    <input type="checkbox" name="pack_active" <?php checked($pack['active'] ?? true, true); ?>>
                                    <?php _e('Pack activo (visible en formulario)', 'ivb-infarma-registration'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="ivbir-form-actions">
                            <button type="submit" class="button button-primary button-large">
                                <?php _e('Guardar Pack', 'ivb-infarma-registration'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Columna derecha: Productos del pack -->
                <?php if ($pack_id): ?>
                <div class="ivbir-pack-products">
                    <div class="ivbir-form-section">
                        <h2><?php _e('Productos del Pack', 'ivb-infarma-registration'); ?></h2>

                        <div id="ivbir-products-container" data-pack-id="<?php echo esc_attr($pack_id); ?>">
                            <div class="ivbir-products-list">
                                <?php foreach ($products as $prod):
                                    $product = wc_get_product($prod['product_id']);
                                    if (!$product) continue;
                                ?>
                                    <div class="ivbir-product-row" data-product-id="<?php echo esc_attr($prod['product_id']); ?>">
                                        <span class="ivbir-drag-handle dashicons dashicons-menu"></span>
                                        <?php echo $product->get_image(array(40, 40)); ?>
                                        <div class="ivbir-product-info">
                                            <strong><?php echo esc_html($product->get_name()); ?></strong>
                                            <span class="ivbir-product-price"><?php echo wc_price($product->get_price()); ?></span>
                                        </div>
                                        <div class="ivbir-product-quantity">
                                            <label>Qty:</label>
                                            <input type="number" class="ivbir-qty-input" value="<?php echo esc_attr($prod['quantity']); ?>" min="1" data-product-id="<?php echo esc_attr($prod['product_id']); ?>">
                                        </div>
                                        <button type="button" class="button ivbir-remove-product" data-product-id="<?php echo esc_attr($prod['product_id']); ?>">
                                            <span class="dashicons dashicons-no-alt"></span>
                                        </button>
                                    </div>
                                <?php endforeach; ?>

                                <div class="ivbir-add-product-row">
                                    <input type="text" class="ivbir-product-search" placeholder="<?php _e('Buscar y añadir producto...', 'ivb-infarma-registration'); ?>">
                                    <div class="ivbir-search-results"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen del pack -->
                        <div class="ivbir-pack-summary">
                            <h3><?php _e('Resumen', 'ivb-infarma-registration'); ?></h3>
                            <?php
                            // Calcular resumen directamente para soportar packs temporales
                            $total_products = 0;
                            $total_items = 0;
                            $original_price = 0;

                            foreach ($products as $item) {
                                $product = wc_get_product($item['product_id']);
                                if ($product) {
                                    $total_products++;
                                    $total_items += intval($item['quantity']);
                                    $original_price += $product->get_price() * intval($item['quantity']);
                                }
                            }

                            $discount_percentage = isset($pack['discount_percentage']) ? floatval($pack['discount_percentage']) : 0;
                            $discounted_price = $original_price;
                            if ($discount_percentage > 0) {
                                $discounted_price = $original_price * (1 - ($discount_percentage / 100));
                            }
                            ?>
                            <div class="ivbir-summary-row">
                                <span><?php _e('Total productos:', 'ivb-infarma-registration'); ?></span>
                                <strong id="ivbir-total-products"><?php echo $total_products; ?></strong>
                            </div>
                            <div class="ivbir-summary-row">
                                <span><?php _e('Total unidades:', 'ivb-infarma-registration'); ?></span>
                                <strong id="ivbir-total-items"><?php echo $total_items; ?></strong>
                            </div>
                            <div class="ivbir-summary-row">
                                <span><?php _e('Precio original:', 'ivb-infarma-registration'); ?></span>
                                <strong id="ivbir-original-price"><?php echo wc_price($original_price); ?></strong>
                            </div>
                            <?php if ($discount_percentage > 0): ?>
                            <div class="ivbir-summary-row ivbir-discount-row">
                                <span><?php _e('Precio con descuento:', 'ivb-infarma-registration'); ?></span>
                                <strong id="ivbir-discounted-price"><?php echo wc_price($discounted_price); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Cargar scripts de administración
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'ivbir') === false) {
            return;
        }

        wp_enqueue_style('ivbir-admin-css', IVBIR_PLUGIN_URL . 'assets/css/admin.css', array(), IVBIR_VERSION);
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('ivbir-admin-js', IVBIR_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), IVBIR_VERSION, true);

        wp_localize_script('ivbir-admin-js', 'ivbirAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ivbir_admin_nonce'),
            'i18n' => array(
                'confirm_delete_pack' => __('¿Estás seguro de que quieres eliminar el pack "%s"? Esta acción no se puede deshacer.', 'ivb-infarma-registration'),
                'confirm_delete_category' => __('¿Eliminar esta categoría y todos sus productos?', 'ivb-infarma-registration'),
                'new_category_name' => __('Nueva categoría', 'ivb-infarma-registration'),
                'saving' => __('Guardando...', 'ivb-infarma-registration'),
                'saved' => __('Guardado', 'ivb-infarma-registration'),
                'error' => __('Error al guardar', 'ivb-infarma-registration'),
            ),
        ));
    }

    // ========================
    // AJAX Handlers
    // ========================

    /**
     * AJAX: Guardar pack
     */
    public function ajax_save_pack() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $pack_id = sanitize_text_field($_POST['pack_id']);
        $pack_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'discount_percentage' => floatval($_POST['discount_percentage'] ?? 0),
            'active' => isset($_POST['active']) && $_POST['active'] === 'true',
            'order' => intval($_POST['order'] ?? 0),
        );

        ivbir()->pack_manager->save_pack($pack_id, $pack_data);

        wp_send_json_success(array('message' => __('Pack guardado', 'ivb-infarma-registration')));
    }

    /**
     * AJAX: Eliminar pack
     */
    public function ajax_delete_pack() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $pack_id = sanitize_text_field($_POST['pack_id']);
        ivbir()->pack_manager->delete_pack($pack_id);

        wp_send_json_success(array('message' => __('Pack eliminado', 'ivb-infarma-registration')));
    }

    /**
     * AJAX: Guardar categoría
     */
    public function ajax_save_pack_category() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $pack_id = sanitize_text_field($_POST['pack_id']);
        $category_name = sanitize_text_field($_POST['category_name']);
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $order = isset($_POST['order']) ? intval($_POST['order']) : 0;

        $new_id = ivbir()->pack_manager->save_pack_category($pack_id, $category_name, $order, $category_id);

        wp_send_json_success(array(
            'message' => __('Categoría guardada', 'ivb-infarma-registration'),
            'category_id' => $new_id,
        ));
    }

    /**
     * AJAX: Eliminar categoría
     */
    public function ajax_delete_pack_category() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $category_id = intval($_POST['category_id']);
        ivbir()->pack_manager->delete_pack_category($category_id);

        wp_send_json_success(array('message' => __('Categoría eliminada', 'ivb-infarma-registration')));
    }

    /**
     * AJAX: Añadir producto a pack
     */
    public function ajax_add_product_to_pack() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $pack_id = sanitize_text_field($_POST['pack_id']);
        $product_id = intval($_POST['product_id']);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        // Verificar que el pack existe
        $pack = ivbir()->pack_manager->get_pack($pack_id);
        if (!$pack) {
            wp_send_json_error(array('message' => __('El pack no existe. Guarda el pack primero.', 'ivb-infarma-registration')));
        }

        // Intentar añadir el producto
        global $wpdb;
        $result = ivbir()->pack_manager->add_product_to_pack($pack_id, 0, $product_id, $quantity);

        // Verificar si hubo error
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al guardar el producto', 'ivb-infarma-registration'),
                'db_error' => $wpdb->last_error,
                'pack_id' => $pack_id
            ));
        }

        $product = wc_get_product($product_id);

        wp_send_json_success(array(
            'message' => __('Producto añadido', 'ivb-infarma-registration'),
            'product' => array(
                'id' => $product_id,
                'name' => $product->get_name(),
                'price' => wc_price($product->get_price()),
                'image' => $product->get_image(array(40, 40)),
            ),
            'debug' => array(
                'pack_id' => $pack_id,
                'result' => $result
            )
        ));
    }

    /**
     * AJAX: Eliminar producto de pack
     */
    public function ajax_remove_product_from_pack() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $pack_id = sanitize_text_field($_POST['pack_id']);
        $product_id = intval($_POST['product_id']);

        ivbir()->pack_manager->remove_product_from_pack($pack_id, $product_id);

        wp_send_json_success(array('message' => __('Producto eliminado', 'ivb-infarma-registration')));
    }

    /**
     * AJAX: Actualizar cantidad de producto
     */
    public function ajax_update_product_quantity() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $pack_id = sanitize_text_field($_POST['pack_id']);
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);

        ivbir()->pack_manager->update_product_quantity($pack_id, $product_id, $quantity);

        wp_send_json_success(array('message' => __('Cantidad actualizada', 'ivb-infarma-registration')));
    }

    /**
     * AJAX: Buscar productos
     */
    public function ajax_search_products() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $search = sanitize_text_field($_POST['search']);
        $results = array();

        // Si el término de búsqueda es numérico, buscar por ID
        if (is_numeric($search)) {
            $product = wc_get_product(intval($search));
            if ($product && $product->get_status() === 'publish') {
                $results[] = $this->format_product_result($product);
            }
        }

        // Búsqueda normal por nombre/SKU
        $products = wc_get_products(array(
            's' => $search,
            'limit' => 10,
            'status' => 'publish',
            'type' => array('simple', 'variable'),
        ));

        foreach ($products as $product) {
            // Si es un producto simple, añadirlo directamente
            if ($product->is_type('simple')) {
                $results[] = $this->format_product_result($product);
            }

            // Si es un producto variable, añadir el producto padre y todas sus variaciones
            if ($product->is_type('variable')) {
                // Añadir producto padre
                $results[] = $this->format_product_result($product);

                // Obtener y añadir variaciones
                $variations = $product->get_available_variations();
                foreach ($variations as $variation_data) {
                    $variation = wc_get_product($variation_data['variation_id']);
                    if ($variation) {
                        $results[] = $this->format_product_result($variation, $product);
                    }
                }
            }
        }

        // Eliminar duplicados basados en ID
        $unique_results = array();
        $seen_ids = array();
        foreach ($results as $result) {
            if (!in_array($result['id'], $seen_ids)) {
                $unique_results[] = $result;
                $seen_ids[] = $result['id'];
            }
        }

        wp_send_json_success($unique_results);
    }

    /**
     * Formatear resultado de producto para búsqueda
     */
    private function format_product_result($product, $parent = null) {
        $name = $product->get_name();

        // Si es una variación, mostrar con atributos
        if ($product->is_type('variation')) {
            $attributes = array();
            foreach ($product->get_variation_attributes() as $attr_name => $attr_value) {
                $attr_label = wc_attribute_label(str_replace('attribute_', '', $attr_name));
                $attributes[] = $attr_label . ': ' . $attr_value;
            }
            if (!empty($attributes)) {
                $name = ($parent ? $parent->get_name() : '') . ' - ' . implode(', ', $attributes);
            }
        }

        return array(
            'id' => $product->get_id(),
            'name' => $name,
            'sku' => $product->get_sku(),
            'price' => wc_price($product->get_price()),
            'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
        );
    }

    /**
     * AJAX: Reordenar items
     */
    public function ajax_reorder_items() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        global $wpdb;

        $type = sanitize_text_field($_POST['type']);
        $items = isset($_POST['items']) ? array_map('intval', $_POST['items']) : array();

        if ($type === 'categories') {
            $table = $wpdb->prefix . 'ivbir_pack_categories';
            foreach ($items as $order => $id) {
                $wpdb->update($table, array('category_order' => $order), array('id' => $id));
            }
        } elseif ($type === 'products') {
            $table = $wpdb->prefix . 'ivbir_pack_products';
            foreach ($items as $order => $id) {
                $wpdb->update($table, array('product_order' => $order), array('product_id' => $id));
            }
        }

        wp_send_json_success(array('message' => __('Orden actualizado', 'ivb-infarma-registration')));
    }

    /**
     * AJAX: Obtener resumen del pack
     */
    public function ajax_get_pack_summary() {
        check_ajax_referer('ivbir_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'ivb-infarma-registration')));
        }

        $pack_id = sanitize_text_field($_POST['pack_id']);

        // Obtener productos directamente
        $products = ivbir()->pack_manager->get_pack_products($pack_id);

        // Calcular totales
        $total_products = 0;
        $total_items = 0;
        $original_price = 0;

        foreach ($products as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product) {
                $total_products++;
                $total_items += intval($item['quantity']);
                $original_price += $product->get_price() * intval($item['quantity']);
            }
        }

        // Obtener descuento del pack (puede no existir si es temporal)
        $pack = ivbir()->pack_manager->get_pack($pack_id);
        $discount_percentage = $pack ? floatval($pack['discount_percentage'] ?? 0) : 0;

        // Calcular precio con descuento
        $discounted_price = $original_price;
        if ($discount_percentage > 0) {
            $discounted_price = $original_price * (1 - ($discount_percentage / 100));
        }

        wp_send_json_success(array(
            'total_products' => $total_products,
            'total_items' => $total_items,
            'original_price' => wc_price($original_price),
            'discounted_price' => wc_price($discounted_price),
            'discount_percentage' => $discount_percentage,
        ));
    }

}
