<?php
/**
 * Gestión del carrito — poblar al hacer login y rastrear packs en pedidos
 *
 * @package IVB_Infarma_Registration
 * @since 0.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class IVBIR_Cart_Handler {

    public function __construct() {
        // template_redirect dispara cuando WC está completamente inicializado
        add_action('template_redirect', array($this, 'maybe_populate_cart'));

        // Copiar datos del pack al line item del pedido
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'copy_pack_data_to_order_item'), 10, 4);

        // Guardar meta a nivel de pedido (facilita las consultas de informes)
        add_action('woocommerce_checkout_order_created', array($this, 'save_cart_pack_order_meta'), 10, 1);
    }

    /**
     * Si el usuario tiene packs pendientes, añadir sus productos al carrito.
     * Guarda el pack de origen en los datos del item para poder rastrearlo al pedir.
     */
    public function maybe_populate_cart() {
        if (!is_user_logged_in()) {
            return;
        }

        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $user_id      = get_current_user_id();
        $pending_packs = get_user_meta($user_id, '_ivbir_pending_cart_packs', true);

        if (empty($pending_packs) || !is_array($pending_packs)) {
            return;
        }

        // Eliminar el meta antes de añadir para evitar duplicados en caso de error parcial
        delete_user_meta($user_id, '_ivbir_pending_cart_packs');

        $added_count = 0;

        foreach ($pending_packs as $pack_id) {
            $pack = ivbir()->pack_manager->get_pack($pack_id);

            $use_for_cart = array_key_exists('use_for_cart', $pack)
                ? !empty($pack['use_for_cart'])
                : ($pack['use_type'] ?? '') === 'add_to_cart';

            if (!$pack || !$use_for_cart || empty($pack['active'])) {
                continue;
            }

            $products = ivbir()->pack_manager->get_pack_products($pack_id);

            foreach ($products as $item) {
                $product = wc_get_product($item['product_id']);

                if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                    continue;
                }

                // Las claves con _ no se muestran en el carrito ni en el pedido (WC convention)
                $cart_item_data = array(
                    '_ivbir_cart_pack_id'   => sanitize_key($pack_id),
                    '_ivbir_cart_pack_name' => sanitize_text_field($pack['name']),
                );

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
     * Copiar el pack de origen del item de carrito al line item del pedido.
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $cart_item
     * @param WC_Order              $order
     */
    public function copy_pack_data_to_order_item($item, $cart_item_key, $cart_item, $order) {
        if (empty($cart_item['_ivbir_cart_pack_id'])) {
            return;
        }

        $item->add_meta_data('_ivbir_cart_pack_id',   $cart_item['_ivbir_cart_pack_id'],   true);
        $item->add_meta_data('_ivbir_cart_pack_name', $cart_item['_ivbir_cart_pack_name'], true);
    }

    /**
     * Al crear el pedido, guardar en el meta del pedido qué packs de carrito contiene.
     * Esto facilita las consultas de informes sin tener que recorrer line items.
     *
     * @param WC_Order $order
     */
    public function save_cart_pack_order_meta($order) {
        $pack_ids   = array();
        $pack_names = array();

        foreach ($order->get_items() as $item) {
            $pack_id   = $item->get_meta('_ivbir_cart_pack_id');
            $pack_name = $item->get_meta('_ivbir_cart_pack_name');

            if ($pack_id && !in_array($pack_id, $pack_ids, true)) {
                $pack_ids[]   = $pack_id;
                $pack_names[] = $pack_name;
            }
        }

        if (empty($pack_ids)) {
            return;
        }

        $order->update_meta_data('_ivbir_cart_pack_ids',   $pack_ids);
        $order->update_meta_data('_ivbir_cart_pack_names', $pack_names);
        $order->save();
    }
}
