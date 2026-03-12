<?php
/**
 * Gestión de Pedidos
 *
 * @package IVB_Infarma_Registration
 * @since 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class IVBIR_Order_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('user_register', array($this, 'create_order_on_user_register'), 10, 1);
    }

    /**
     * Crear pedido cuando se registra un usuario
     */
    public function create_order_on_user_register($user_id) {
        // Verificar que se enviaron los datos del formulario personalizado
        if (!isset($_POST['custom_product']) && !isset($_POST['billing_address'])) {
            return;
        }

        // Obtener datos del formulario
        $selected_pack = sanitize_text_field($_POST['custom_product'] ?? '');
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'bacs');

        // Datos de facturación
        $billing_data = array(
            'address' => sanitize_text_field($_POST['billing_address'] ?? ''),
            'postcode' => sanitize_text_field($_POST['billing_postcode'] ?? ''),
            'city' => sanitize_text_field($_POST['billing_city'] ?? ''),
            'phone' => sanitize_text_field($_POST['billing_phone'] ?? ''),
            'country' => sanitize_text_field($_POST['billing_country'] ?? 'ES'),
            'state' => sanitize_text_field($_POST['billing_state'] ?? ''),
        );

        // Datos de envío
        if (!empty($_POST['different_shipping']) && $_POST['different_shipping'] == '1') {
            $shipping_data = array(
                'address' => sanitize_text_field($_POST['shipping_address'] ?? ''),
                'postcode' => sanitize_text_field($_POST['shipping_postcode'] ?? ''),
                'city' => sanitize_text_field($_POST['shipping_city'] ?? ''),
                'phone' => sanitize_text_field($_POST['shipping_phone'] ?? ''),
                'country' => sanitize_text_field($_POST['shipping_country'] ?? 'ES'),
                'state' => sanitize_text_field($_POST['shipping_state'] ?? ''),
            );
        } else {
            $shipping_data = $billing_data;
        }

        // Obtener datos del usuario
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $user = get_user_by('id', $user_id);
        $email = $user->user_email;

        // Guardar datos de facturación y envío como user_meta de WooCommerce
        $this->save_user_billing_shipping_meta($user_id, $billing_data, $shipping_data, $first_name, $last_name);

        // Guardar email del usuario que creó este usuario como campo 'comercial'
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID) {
            update_user_meta($user_id, 'comercial', $current_user->user_email);
        }

        // Crear el pedido
        $order = $this->create_order($user_id, $selected_pack, $billing_data, $shipping_data, $payment_method, $first_name, $last_name, $email);

        if (is_wp_error($order)) {
            return;
        }

        // Guardar meta del pedido
        $order->update_meta_data('_ivbir_created_by', $current_user->ID);
        $order->update_meta_data('_ivbir_registration_order', 'yes');
        $order->save();
    }

    /**
     * Crear el pedido en WooCommerce
     */
    private function create_order($user_id, $pack_id, $billing, $shipping, $payment_method, $first_name, $last_name, $email) {
        if (!function_exists('wc_create_order')) {
            return new WP_Error('wc_not_available', 'WooCommerce no está disponible');
        }

        // Crear pedido
        $order = wc_create_order();

        if (is_wp_error($order)) {
            return $order;
        }

        $order->set_customer_id($user_id);

        // Obtener pack y sus productos
        $pack_manager = ivbir()->pack_manager;
        $pack = $pack_manager->get_pack($pack_id);

        if (!$pack) {
            $order->delete(true);
            return new WP_Error('pack_not_found', 'Pack no encontrado: ' . $pack_id);
        }

        $products = $pack_manager->get_pack_products($pack_id);

        if (empty($products)) {
            $order->delete(true);
            return new WP_Error('pack_empty', 'El pack no tiene productos');
        }

        // Añadir productos al pedido
        foreach ($products as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product) {
                $order->add_product($product, $item['quantity']);
            }
        }

        // Guardar meta con el nombre del pack
        $order->update_meta_data('pack_nombre', $pack['name']);
        $order->update_meta_data('_ivbir_pack_id', $pack_id);

        // Aplicar descuento si existe
        $discount_percentage = $pack['discount_percentage'] ?? 0;

        if ($discount_percentage > 0) {
            $this->apply_line_discount($order, $discount_percentage);
        }

        // Asignar método de envío
        $this->assign_shipping_method($order, $billing['postcode']);

        // Aplicar cupón si el pago es por transferencia
        if ($payment_method === 'bacs') {
            $coupon_code = IVB_Infarma_Registration::get_setting('transfer_coupon_code', '2DESCTF');
            if (!empty($coupon_code)) {
                $this->apply_coupon($order, $coupon_code);
            }
        }

        // Aplicar recargo de equivalencia si el usuario tiene el rol 're'
        $user = get_user_by('id', $user_id);
        if ($user && in_array('re', (array) $user->roles)) {
            $this->apply_recargo_equivalencia($order);
        }

        // Asignar direcciones
        $order->set_address(array(
            'first_name' => $first_name,
            'last_name' => '',
            'company' => $last_name,
            'address_1' => $billing['address'],
            'city' => $billing['city'],
            'postcode' => $billing['postcode'],
            'country' => $billing['country'],
            'state' => $billing['state'],
            'phone' => $billing['phone'],
            'email' => $email,
        ), 'billing');

        $order->set_address(array(
            'first_name' => $first_name,
            'last_name' => '',
            'address_1' => $shipping['address'],
            'city' => $shipping['city'],
            'postcode' => $shipping['postcode'],
            'country' => $shipping['country'],
            'state' => $shipping['state'],
            'phone' => $shipping['phone'],
        ), 'shipping');

        // Asignar método de pago
        $order->set_payment_method($payment_method);

        if (function_exists('WC') && isset(WC()->payment_gateways->payment_gateways()[$payment_method])) {
            $pm_title = WC()->payment_gateways->payment_gateways()[$payment_method]->get_title();
            $order->set_payment_method_title($pm_title);
        }

        // Calcular totales antes de cambiar el estado para que el pedido
        // tenga los importes correctos cuando se disparen las notificaciones
        $order->calculate_totals();
        $order->save();

        // Establecer estado según método de pago
        $processing_methods = array('redsys', 'bizumredsys', 'cod');

        if (in_array($payment_method, $processing_methods)) {
            $this->update_order_status_safely($order, 'processing', __('Pedido procesado automáticamente según el método de pago.', 'ivb-infarma-registration'));
        } elseif ($payment_method === 'bacs') {
            $this->update_order_status_safely($order, 'on-hold', __('Pedido en espera para transferencia bancaria.', 'ivb-infarma-registration'));
        }

        return $order;
    }

    /**
     * Cambiar el estado del pedido de forma segura, evitando que el plugin
     * invoice-payment-option-woocommerce falle al recibir un array en lugar
     * de un objeto WC_Order en el filtro woocommerce_email_attachments
     * (esto ocurre con los emails de backorder, que pasan $args como tercer
     * parámetro en lugar del propio pedido).
     */
    private function update_order_status_safely($order, $status, $note = '') {
        $hook_name        = 'woocommerce_email_attachments';
        $removed_callbacks = array();

        // Localizar y retirar temporalmente los callbacks de AF_IG_Admin
        // que llaman a get_id() sin comprobar si el parámetro es un objeto.
        global $wp_filter;
        if (class_exists('AF_IG_Admin') && isset($wp_filter[$hook_name])) {
            $to_remove = array();

            foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (
                        is_array($callback['function'])
                        && is_object($callback['function'][0])
                        && $callback['function'][0] instanceof AF_IG_Admin
                    ) {
                        $to_remove[] = array(
                            'callback'      => $callback['function'],
                            'priority'      => $priority,
                            'accepted_args' => $callback['accepted_args'],
                        );
                    }
                }
            }

            foreach ($to_remove as $item) {
                if (remove_filter($hook_name, $item['callback'], $item['priority'])) {
                    $removed_callbacks[] = $item;
                }
            }
        }

        $order->update_status($status, $note);

        // Restaurar los callbacks retirados
        foreach ($removed_callbacks as $item) {
            add_filter($hook_name, $item['callback'], $item['priority'], $item['accepted_args']);
        }
    }

    /**
     * Aplicar descuento a las líneas del pedido
     */
    private function apply_line_discount($order, $discount_percentage) {
        foreach ($order->get_items('line_item') as $item) {
            if (is_a($item, 'WC_Order_Item_Product')) {
                $original_total = $item->get_total();
                $new_total = $original_total * (1 - ($discount_percentage / 100));
                $item->set_total($new_total);

                $original_subtotal = $item->get_subtotal();
                $new_subtotal = $original_subtotal * (1 - ($discount_percentage / 100));
                $item->set_subtotal($new_subtotal);

                $item->save();
            }
        }

        $order->calculate_totals();
        $order->save();
    }

    /**
     * Asignar método de envío
     */
    private function assign_shipping_method($order, $postcode) {
        $shipping_method_id = '';
        $shipping_method_title = '';

        // Intentar obtener método de envío por zona
        if (!empty($postcode) && class_exists('WC_Shipping_Zones')) {
            $zone = WC_Shipping_Zones::get_zone_by('postcode', $postcode);

            if ($zone) {
                $methods = $zone->get_shipping_methods();

                foreach ($methods as $method) {
                    if (isset($method->enabled) && $method->enabled === 'yes') {
                        $shipping_method_id = $method->id;
                        $shipping_method_title = $method->get_title();
                        break;
                    }
                }
            }
        }

        // Fallback: obtener cualquier método de envío habilitado
        if (empty($shipping_method_id) && function_exists('WC')) {
            $shipping_methods = WC()->shipping->get_shipping_methods();

            foreach ($shipping_methods as $method) {
                if (isset($method->enabled) && $method->enabled === 'yes') {
                    $shipping_method_id = $method->id;
                    $shipping_method_title = $method->get_title();
                    break;
                }
            }
        }

        // Fallback final: envío gratuito
        if (empty($shipping_method_id)) {
            $shipping_method_id = 'free_shipping:1';
            $shipping_method_title = __('Envío Gratuito', 'ivb-infarma-registration');
        }

        // Añadir método de envío al pedido
        $shipping_item = new WC_Order_Item_Shipping();
        $shipping_item->set_method_id($shipping_method_id);
        $shipping_item->set_method_title($shipping_method_title);
        $shipping_item->set_total(0);

        $order->add_item($shipping_item);
        $shipping_item->save();
    }

    /**
     * Aplicar cupón al pedido
     */
    private function apply_coupon($order, $coupon_code) {
        $coupon = new WC_Coupon($coupon_code);

        if (!$coupon->get_id()) {
            return false;
        }

        if (method_exists($order, 'apply_coupon')) {
            $order->apply_coupon($coupon_code);
        } else {
            $order->add_coupon($coupon_code, $coupon->get_amount(), $coupon->get_discount_type());
        }

        $order->calculate_totals();
        $order->save();

        return true;
    }

    /**
     * Aplicar recargo de equivalencia
     */
    private function apply_recargo_equivalencia($order) {
        $recargo_taxes = get_option('woocommerce_re_taxes_dictio', array());

        if (empty($recargo_taxes)) {
            return;
        }

        foreach ($order->get_items('line_item') as $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);

            if ($product) {
                $current_tax_class = $product->get_tax_class();

                if (isset($recargo_taxes[$current_tax_class])) {
                    $new_tax_class = $recargo_taxes[$current_tax_class];
                    $item->set_tax_class($new_tax_class);
                    $item->save();
                }
            }
        }

        $order->calculate_totals();
        $order->save();
    }

    /**
     * Guardar datos de facturación y envío como user_meta
     */
    private function save_user_billing_shipping_meta($user_id, $billing, $shipping, $first_name, $last_name) {
        // Datos de facturación
        update_user_meta($user_id, 'billing_first_name', $first_name);
        update_user_meta($user_id, 'billing_last_name', ''); // Vacío como en el original
        update_user_meta($user_id, 'billing_company', $last_name); // Apellidos = empresa
        update_user_meta($user_id, 'billing_address_1', $billing['address']);
        update_user_meta($user_id, 'billing_address_2', '');
        update_user_meta($user_id, 'billing_city', $billing['city']);
        update_user_meta($user_id, 'billing_postcode', $billing['postcode']);
        update_user_meta($user_id, 'billing_country', $billing['country']);
        update_user_meta($user_id, 'billing_state', $billing['state']);
        update_user_meta($user_id, 'billing_phone', $billing['phone']);

        // Datos de envío
        update_user_meta($user_id, 'shipping_first_name', $first_name);
        update_user_meta($user_id, 'shipping_last_name', '');
        update_user_meta($user_id, 'shipping_address_1', $shipping['address']);
        update_user_meta($user_id, 'shipping_address_2', '');
        update_user_meta($user_id, 'shipping_city', $shipping['city']);
        update_user_meta($user_id, 'shipping_postcode', $shipping['postcode']);
        update_user_meta($user_id, 'shipping_country', $shipping['country']);
        update_user_meta($user_id, 'shipping_state', $shipping['state']);

        if (isset($shipping['phone'])) {
            update_user_meta($user_id, 'shipping_phone', $shipping['phone']);
        }
    }

    /**
     * Obtener pedidos creados por el plugin
     */
    public static function get_registration_orders($args = array()) {
        $default_args = array(
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_ivbir_registration_order',
            'meta_value' => 'yes',
        );

        $args = wp_parse_args($args, $default_args);

        return wc_get_orders($args);
    }

    /**
     * Obtener estadísticas de pedidos
     */
    public static function get_order_stats() {
        global $wpdb;

        $stats = array(
            'total_orders' => 0,
            'total_revenue' => 0,
            'by_pack' => array(),
            'by_status' => array(),
        );

        // Total de pedidos
        $stats['total_orders'] = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_ivbir_registration_order' AND meta_value = 'yes'
        ");

        // Ingresos totales
        $stats['total_revenue'] = $wpdb->get_var("
            SELECT SUM(pm2.meta_value)
            FROM {$wpdb->postmeta} pm1
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id AND pm2.meta_key = '_order_total'
            WHERE pm1.meta_key = '_ivbir_registration_order' AND pm1.meta_value = 'yes'
        ");

        // Por pack
        $pack_results = $wpdb->get_results("
            SELECT pm2.meta_value as pack_name, COUNT(*) as count
            FROM {$wpdb->postmeta} pm1
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id AND pm2.meta_key = 'pack_nombre'
            WHERE pm1.meta_key = '_ivbir_registration_order' AND pm1.meta_value = 'yes'
            GROUP BY pm2.meta_value
        ");

        foreach ($pack_results as $row) {
            $stats['by_pack'][$row->pack_name] = $row->count;
        }

        return $stats;
    }
}
