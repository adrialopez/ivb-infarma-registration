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
        // template_redirect dispara cuando WC está completamente inicializado
        // (carrito + sesión listos para escritura).
        add_action('template_redirect', array($this, 'maybe_populate_cart'));
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

        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
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

            $products = ivbir()->pack_manager->get_pack_products($pack_id);

            foreach ($products as $item) {
                $product = wc_get_product($item['product_id']);

                if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                    continue;
                }

                $result = WC()->cart->add_to_cart(
                    intval($item['product_id']),
                    intval($item['quantity'])
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

}
