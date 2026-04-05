<?php
/**
 * Gestión del carrito — poblar al hacer login y aplicar descuentos de pack
 *
 * @package IVB_Infarma_Registration
 * @since 0.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class IVBIR_Cart_Handler {

    public function __construct() {
        // Poblar el carrito cuando el usuario tenga packs pendientes
        add_action('wp', array($this, 'maybe_populate_cart'));

        // Aplicar descuentos de pack a los items del carrito
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_pack_discounts'), 10, 1);

        // No mostrar el meta de pack en el carrito (front-end)
        add_filter('woocommerce_get_item_data', array($this, 'hide_pack_item_data'), 10, 2);
    }

    /**
     * Si el usuario tiene packs pendientes, añadir sus productos al carrito.
     * Se ejecuta en el hook 'wp' para asegurar que WooCommerce y la sesión están cargados.
     * El meta se elimina antes de añadir los productos para evitar duplicados en caso de error.
     */
    public function maybe_populate_cart() {
        if (!is_user_logged_in()) {
            return;
        }

        // No ejecutar en el admin ni en peticiones AJAX/REST donde el carrito no aplica
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart instanceof WC_Cart) {
            return;
        }

        $user_id = get_current_user_id();
        $pending_packs = get_user_meta($user_id, '_ivbir_pending_cart_packs', true);

        if (empty($pending_packs) || !is_array($pending_packs)) {
            return;
        }

        // Eliminar el meta primero para evitar que se repita si algo falla a medias
        delete_user_meta($user_id, '_ivbir_pending_cart_packs');

        $added_count = 0;

        foreach ($pending_packs as $pack_id) {
            $pack = ivbir()->pack_manager->get_pack($pack_id);

            if (!$pack || ($pack['use_type'] ?? 'create_order') !== 'add_to_cart' || empty($pack['active'])) {
                continue;
            }

            $products  = ivbir()->pack_manager->get_pack_products($pack_id);
            $discount  = floatval($pack['discount_percentage'] ?? 0);

            foreach ($products as $item) {
                $product = wc_get_product($item['product_id']);

                if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                    continue;
                }

                $cart_item_data = array(
                    '_ivbir_pack_id'   => sanitize_key($pack_id),
                    '_ivbir_pack_name' => sanitize_text_field($pack['name']),
                );

                if ($discount > 0) {
                    $cart_item_data['_ivbir_pack_discount'] = $discount;
                }

                $result = WC()->cart->add_to_cart(
                    intval($item['product_id']),
                    intval($item['quantity']),
                    0,
                    array(),
                    $cart_item_data
                );

                if ($result) {
                    $added_count++;
                }
            }
        }

        if ($added_count > 0) {
            wc_add_notice(
                __('Se han añadido productos al carrito automáticamente.', 'ivb-infarma-registration'),
                'success'
            );
        }
    }

    /**
     * Aplicar el descuento del pack a los items del carrito marcados con _ivbir_pack_discount.
     * Se llama en woocommerce_before_calculate_totals, que recalcula precios antes de los totales.
     *
     * @param WC_Cart $cart
     */
    public function apply_pack_discounts($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['_ivbir_pack_discount']) || $cart_item['_ivbir_pack_discount'] <= 0) {
                continue;
            }

            $discount_rate  = floatval($cart_item['_ivbir_pack_discount']) / 100;
            $original_price = floatval($cart_item['data']->get_price('edit'));
            $new_price      = round($original_price * (1 - $discount_rate), wc_get_price_decimals());

            $cart_item['data']->set_price($new_price);
        }
    }

    /**
     * Ocultar los meta internos del pack en la vista del carrito (front-end).
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function hide_pack_item_data($item_data, $cart_item) {
        $hidden_keys = array('_ivbir_pack_id', '_ivbir_pack_name', '_ivbir_pack_discount');

        return array_filter($item_data, function($data) use ($hidden_keys) {
            return !in_array($data['key'] ?? '', $hidden_keys, true);
        });
    }
}
