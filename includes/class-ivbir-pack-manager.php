<?php
/**
 * Gestión de Packs
 *
 * @package IVB_Infarma_Registration
 * @since 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class IVBIR_Pack_Manager {

    /**
     * Caché estática para packs
     */
    private static $packs_cache = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Hooks si son necesarios
    }

    /**
     * Obtener todos los packs (con caché)
     */
    public function get_all_packs() {
        if (null === self::$packs_cache) {
            self::$packs_cache = get_option('ivbir_packs', array());
        }
        return self::$packs_cache;
    }

    /**
     * Limpiar caché de packs
     */
    private function clear_packs_cache() {
        self::$packs_cache = null;
    }

    /**
     * Obtener un pack por ID
     */
    public function get_pack($pack_id) {
        $packs = $this->get_all_packs();
        return isset($packs[$pack_id]) ? $packs[$pack_id] : null;
    }

    /**
     * Guardar un pack
     */
    public function save_pack($pack_id, $pack_data) {
        $packs = $this->get_all_packs();

        $use_type = $pack_data['use_type'] ?? 'create_order';
        if (!in_array($use_type, array('create_order', 'add_to_cart'))) {
            $use_type = 'create_order';
        }

        $packs[$pack_id] = array(
            'id' => $pack_id,
            'name' => sanitize_text_field($pack_data['name']),
            'description' => sanitize_textarea_field($pack_data['description'] ?? ''),
            'price' => max(0, floatval($pack_data['price'] ?? 0)), // No permitir precios negativos
            'discount_percentage' => min(100, max(0, floatval($pack_data['discount_percentage'] ?? 0))), // 0-100%
            'active' => isset($pack_data['active']) ? (bool) $pack_data['active'] : true,
            'use_type' => $use_type,
            'order' => intval($pack_data['order'] ?? 0),
            'created_at' => $pack_data['created_at'] ?? current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        update_option('ivbir_packs', $packs);
        $this->clear_packs_cache();
        delete_transient('ivbir_pack_summary_' . $pack_id); // Limpiar caché de resumen
        return true;
    }

    /**
     * Eliminar un pack
     */
    public function delete_pack($pack_id) {
        global $wpdb;

        $packs = $this->get_all_packs();

        if (!isset($packs[$pack_id])) {
            return false;
        }

        unset($packs[$pack_id]);
        update_option('ivbir_packs', $packs);
        $this->clear_packs_cache();
        delete_transient('ivbir_pack_summary_' . $pack_id);

        // Eliminar categorías y productos asociados
        $wpdb->delete($wpdb->prefix . 'ivbir_pack_categories', array('pack_id' => $pack_id));
        $wpdb->delete($wpdb->prefix . 'ivbir_pack_products', array('pack_id' => $pack_id));

        return true;
    }

    /**
     * Obtener categorías de un pack
     */
    public function get_pack_categories($pack_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ivbir_pack_categories';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE pack_id = %s ORDER BY category_order ASC",
            $pack_id
        ), ARRAY_A);
    }

    /**
     * Guardar categoría de pack
     */
    public function save_pack_category($pack_id, $category_name, $order = 0, $category_id = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'ivbir_pack_categories';

        $data = array(
            'pack_id' => $pack_id,
            'category_name' => sanitize_text_field($category_name),
            'category_order' => intval($order),
        );

        if ($category_id) {
            $wpdb->update($table, $data, array('id' => $category_id));
            return $category_id;
        } else {
            $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Eliminar categoría de pack
     */
    public function delete_pack_category($category_id) {
        global $wpdb;

        // Primero eliminar productos de esta categoría
        $wpdb->delete($wpdb->prefix . 'ivbir_pack_products', array('category_id' => $category_id));

        // Luego eliminar la categoría
        return $wpdb->delete($wpdb->prefix . 'ivbir_pack_categories', array('id' => $category_id));
    }

    /**
     * Obtener productos de un pack
     */
    public function get_pack_products($pack_id, $category_id = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'ivbir_pack_products';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE pack_id = %s ORDER BY product_order ASC",
            $pack_id
        ), ARRAY_A);
    }

    /**
     * Añadir producto a pack
     */
    public function add_product_to_pack($pack_id, $category_id = 0, $product_id, $quantity = 1, $order = 0) {
        global $wpdb;

        // Validar que el producto existe
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log('IVBIR: Intento de añadir producto inexistente (' . $product_id . ') al pack ' . $pack_id);
            return false;
        }

        // Validar cantidad
        if ($quantity < 1) {
            error_log('IVBIR: Cantidad inválida (' . $quantity . ') para producto ' . $product_id);
            return false;
        }

        $table = $wpdb->prefix . 'ivbir_pack_products';

        // Verificar si el producto ya existe en este pack
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE pack_id = %s AND product_id = %d",
            $pack_id, $product_id
        ));

        if ($existing) {
            // Actualizar cantidad con formatos correctos
            $result = $wpdb->update(
                $table,
                array(
                    'quantity' => intval($quantity),
                    'product_order' => intval($order),
                ),
                array('id' => $existing),
                array('%d', '%d'),  // Formatos para los valores a actualizar
                array('%d')         // Formato para el WHERE
            );

            if (false === $result) {
                error_log('IVBIR: Error al actualizar producto en pack - ' . $wpdb->last_error);
            } else {
                delete_transient('ivbir_pack_summary_' . $pack_id); // Limpiar caché
            }

            return $result;
        }

        // Insertar con formatos correctos
        $result = $wpdb->insert(
            $table,
            array(
                'pack_id' => $pack_id,
                'category_id' => 0,
                'product_id' => intval($product_id),
                'quantity' => intval($quantity),
                'product_order' => intval($order),
            ),
            array('%s', '%d', '%d', '%d', '%d')  // Formatos: string, int, int, int, int
        );

        if (false === $result) {
            error_log('IVBIR: Error al insertar producto en pack - ' . $wpdb->last_error);
        } else {
            delete_transient('ivbir_pack_summary_' . $pack_id); // Limpiar caché
        }

        return $result;
    }

    /**
     * Eliminar producto de pack
     */
    public function remove_product_from_pack($pack_id, $product_id) {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix . 'ivbir_pack_products',
            array(
                'pack_id' => $pack_id,
                'product_id' => $product_id,
            )
        );
    }

    /**
     * Actualizar cantidad de producto en pack
     */
    public function update_product_quantity($pack_id, $product_id, $quantity) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'ivbir_pack_products',
            array('quantity' => intval($quantity)),
            array(
                'pack_id' => $pack_id,
                'product_id' => $product_id,
            )
        );
    }

    /**
     * Obtener packs activos para el formulario
     */
    public function get_active_packs() {
        $packs = $this->get_all_packs();

        $active_packs = array_filter($packs, function($pack) {
            return isset($pack['active']) && $pack['active'];
        });

        // Ordenar por orden
        uasort($active_packs, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });

        return $active_packs;
    }

    /**
     * Obtener packs activos para añadir al carrito (use_type = add_to_cart)
     */
    public function get_cart_packs() {
        $packs = $this->get_all_packs();

        $cart_packs = array_filter($packs, function($pack) {
            return isset($pack['active']) && $pack['active']
                && ($pack['use_type'] ?? 'create_order') === 'add_to_cart';
        });

        uasort($cart_packs, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });

        return $cart_packs;
    }

    /**
     * Calcular precio total de un pack
     * @deprecated Usar get_pack_summary() que es más eficiente
     */
    public function calculate_pack_total($pack_id) {
        $products = $this->get_pack_products($pack_id);
        $total = 0;

        foreach ($products as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product) {
                $total += $product->get_price() * $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Calcular precio con descuento
     * @deprecated Usar get_pack_summary() que es más eficiente
     */
    public function calculate_pack_discounted_total($pack_id) {
        $pack = $this->get_pack($pack_id);
        if (!$pack) {
            return 0;
        }

        $total = $this->calculate_pack_total($pack_id);
        $discount = $pack['discount_percentage'] ?? 0;

        if ($discount > 0) {
            $total = $total * (1 - ($discount / 100));
        }

        return $total;
    }

    /**
     * Obtener resumen de un pack para mostrar (OPTIMIZADO - una sola iteración)
     */
    public function get_pack_summary($pack_id) {
        $pack = $this->get_pack($pack_id);
        if (!$pack) {
            return null;
        }

        $products = $this->get_pack_products($pack_id);

        // Una sola iteración para todo
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

        // Calcular descuento
        $discount = floatval($pack['discount_percentage'] ?? 0);
        $discounted_price = $original_price;
        if ($discount > 0) {
            $discounted_price = $original_price * (1 - ($discount / 100));
        }

        return array(
            'pack' => $pack,
            'total_products' => $total_products,
            'total_items' => $total_items,
            'original_price' => $original_price,
            'discounted_price' => $discounted_price,
        );
    }

    /**
     * Obtener resumen de un pack con caché (para listados)
     */
    public function get_pack_summary_cached($pack_id) {
        $cache_key = 'ivbir_pack_summary_' . $pack_id;
        $summary = get_transient($cache_key);

        if (false === $summary) {
            $summary = $this->get_pack_summary($pack_id);
            if ($summary) {
                set_transient($cache_key, $summary, 6 * HOUR_IN_SECONDS);
            }
        }

        return $summary;
    }

    /**
     * Duplicar un pack
     */
    public function duplicate_pack($pack_id, $new_name = null) {
        $pack = $this->get_pack($pack_id);
        if (!$pack) {
            return false;
        }

        // Generar nuevo ID
        $new_pack_id = sanitize_title($new_name ?? $pack['name'] . ' (copia)') . '_' . time();

        // Crear nuevo pack
        $new_pack_data = $pack;
        $new_pack_data['name'] = $new_name ?? $pack['name'] . ' (copia)';
        $new_pack_data['created_at'] = current_time('mysql');

        $this->save_pack($new_pack_id, $new_pack_data);

        // Copiar productos
        $products = $this->get_pack_products($pack_id);
        foreach ($products as $product) {
            $this->add_product_to_pack(
                $new_pack_id,
                0,
                $product['product_id'],
                $product['quantity'],
                $product['product_order']
            );
        }

        return $new_pack_id;
    }

    /**
     * Exportar pack a JSON
     */
    public function export_pack($pack_id) {
        $pack = $this->get_pack($pack_id);
        if (!$pack) {
            return null;
        }

        return array(
            'pack' => $pack,
            'products' => $this->get_pack_products($pack_id),
        );
    }

    /**
     * Importar pack desde JSON
     */
    public function import_pack($data) {
        if (!isset($data['pack']) || !is_array($data['pack'])) {
            return false;
        }

        $pack_data = $data['pack'];
        $pack_id = sanitize_title($pack_data['name']) . '_' . time();

        $this->save_pack($pack_id, $pack_data);

        // Importar productos
        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $product) {
                $this->add_product_to_pack(
                    $pack_id,
                    0,
                    $product['product_id'],
                    $product['quantity'] ?? 1,
                    $product['product_order'] ?? 0
                );
            }
        }

        return $pack_id;
    }
}
