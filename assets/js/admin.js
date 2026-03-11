/**
 * IVB Infarma Registration - Admin JavaScript
 *
 * @package IVB_Infarma_Registration
 * @since 0.1.0
 */

(function($) {
    'use strict';

    const IVBIR_Admin = {
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
        },

        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Eliminar pack
            $(document).on('click', '.ivbir-delete-pack', this.handleDeletePack.bind(this));

            // Duplicar pack
            $(document).on('click', '.ivbir-duplicate-pack', this.handleDuplicatePack.bind(this));

            // Buscar productos
            $(document).on('input', '.ivbir-product-search', this.debounce(this.handleProductSearch.bind(this), 300));

            // Seleccionar producto de resultados
            $(document).on('click', '.ivbir-search-result-item', this.handleSelectProduct.bind(this));

            // Eliminar producto
            $(document).on('click', '.ivbir-remove-product', this.handleRemoveProduct.bind(this));

            // Actualizar cantidad
            $(document).on('change', '.ivbir-qty-input', this.handleUpdateQuantity.bind(this));

            // Cerrar resultados de búsqueda al hacer clic fuera
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.ivbir-add-product-row').length) {
                    $('.ivbir-search-results').removeClass('active');
                }
            });
        },

        /**
         * Inicializar sortable
         */
        initSortable: function() {
            // Sortable para productos
            $('.ivbir-products-list').sortable({
                handle: '.ivbir-product-row .ivbir-drag-handle',
                placeholder: 'ui-sortable-placeholder',
                items: '.ivbir-product-row',
                update: function(event, ui) {
                    IVBIR_Admin.saveOrder('products');
                }
            });
        },

        /**
         * Guardar orden
         */
        saveOrder: function(type) {
            let items = [];

            $('.ivbir-product-row').each(function() {
                items.push($(this).data('product-id'));
            });

            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_reorder_items',
                    nonce: ivbirAdmin.nonce,
                    type: 'products',
                    items: items
                },
                success: function(response) {
                    if (response.success) {
                        IVBIR_Admin.showToast(ivbirAdmin.i18n.saved, 'success');
                    }
                }
            });
        },

        /**
         * Eliminar pack
         */
        handleDeletePack: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const packId = $btn.data('pack-id');
            const packName = $btn.data('pack-name');

            if (!confirm(ivbirAdmin.i18n.confirm_delete_pack.replace('%s', packName))) {
                return;
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_delete_pack',
                    nonce: ivbirAdmin.nonce,
                    pack_id: packId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.ivbir-pack-card').fadeOut(300, function() {
                            $(this).remove();

                            // Si no quedan packs, mostrar estado vacío
                            if ($('.ivbir-pack-card').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        IVBIR_Admin.showToast(ivbirAdmin.i18n.error, 'error');
                    }
                },
                error: function() {
                    IVBIR_Admin.showToast(ivbirAdmin.i18n.error, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Duplicar pack
         */
        handleDuplicatePack: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const packId = $btn.data('pack-id');

            $btn.prop('disabled', true);

            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_duplicate_pack',
                    nonce: ivbirAdmin.nonce,
                    pack_id: packId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        IVBIR_Admin.showToast(ivbirAdmin.i18n.error, 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Buscar productos
         */
        handleProductSearch: function(e) {
            const $input = $(e.currentTarget);
            const search = $input.val();
            const $results = $input.siblings('.ivbir-search-results');

            if (search.length < 2) {
                $results.removeClass('active');
                return;
            }

            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_search_products',
                    nonce: ivbirAdmin.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '';

                        response.data.forEach(function(product) {
                            html += `
                                <div class="ivbir-search-result-item" data-product-id="${product.id}">
                                    <img src="${product.image}" alt="">
                                    <div class="product-name">
                                        ${product.name}
                                        ${product.sku ? `<span class="product-sku">(${product.sku})</span>` : ''}
                                    </div>
                                    <div class="product-price">${product.price}</div>
                                </div>
                            `;
                        });

                        $results.html(html).addClass('active');
                    } else {
                        $results.removeClass('active');
                    }
                }
            });
        },

        /**
         * Seleccionar producto
         */
        handleSelectProduct: function(e) {
            const $item = $(e.currentTarget);
            const productId = $item.data('product-id');
            const $container = $('#ivbir-products-container');
            const packId = $container.data('pack-id');

            // Verificar pack_id
            if (!packId) {
                IVBIR_Admin.showToast('ERROR: No hay pack_id. Recarga la página.', 'error');
                console.error('No pack_id found');
                return;
            }

            // Verificar si el producto ya existe
            if ($('.ivbir-product-row[data-product-id="' + productId + '"]').length) {
                IVBIR_Admin.showToast('El producto ya está en el pack', 'error');
                return;
            }

            console.log('Adding product', productId, 'to pack', packId);

            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_add_product_to_pack',
                    nonce: ivbirAdmin.nonce,
                    pack_id: packId,
                    product_id: productId,
                    quantity: 1
                },
                success: function(response) {
                    console.log('AJAX response:', response);

                    if (response.success) {
                        const product = response.data.product;

                        const productHtml = `
                            <div class="ivbir-product-row" data-product-id="${product.id}">
                                <span class="ivbir-drag-handle dashicons dashicons-menu"></span>
                                ${product.image}
                                <div class="ivbir-product-info">
                                    <strong>${product.name}</strong>
                                    <span class="ivbir-product-price">${product.price}</span>
                                </div>
                                <div class="ivbir-product-quantity">
                                    <label>Qty:</label>
                                    <input type="number" class="ivbir-qty-input" value="1" min="1" data-product-id="${product.id}">
                                </div>
                                <button type="button" class="button ivbir-remove-product" data-product-id="${product.id}">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        `;

                        $('.ivbir-add-product-row').before(productHtml);

                        // Limpiar búsqueda
                        $('.ivbir-product-search').val('');
                        $('.ivbir-search-results').removeClass('active');

                        // Reinicializar sortable
                        IVBIR_Admin.initSortable();

                        // Actualizar resumen
                        IVBIR_Admin.updateSummary();

                        IVBIR_Admin.showToast('Producto añadido correctamente', 'success');
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : 'Error desconocido';
                        IVBIR_Admin.showToast('ERROR: ' + errorMsg, 'error');
                        console.error('Error response:', response);
                    }
                },
                error: function(xhr, status, error) {
                    IVBIR_Admin.showToast('ERROR DE RED: ' + error, 'error');
                    console.error('AJAX error:', xhr, status, error);
                }
            });
        },

        /**
         * Eliminar producto
         */
        handleRemoveProduct: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const productId = $btn.data('product-id');
            const packId = $('#ivbir-products-container').data('pack-id');

            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_remove_product_from_pack',
                    nonce: ivbirAdmin.nonce,
                    pack_id: packId,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.ivbir-product-row').fadeOut(200, function() {
                            $(this).remove();
                            IVBIR_Admin.updateSummary();
                        });
                    }
                }
            });
        },

        /**
         * Actualizar cantidad
         */
        handleUpdateQuantity: function(e) {
            const $input = $(e.currentTarget);
            const productId = $input.data('product-id');
            const quantity = $input.val();
            const packId = $('#ivbir-products-container').data('pack-id');

            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_update_product_quantity',
                    nonce: ivbirAdmin.nonce,
                    pack_id: packId,
                    product_id: productId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        IVBIR_Admin.updateSummary();
                        IVBIR_Admin.showToast(ivbirAdmin.i18n.saved, 'success');
                    }
                }
            });
        },

        /**
         * Actualizar resumen del pack
         */
        updateSummary: function() {
            const $container = $('#ivbir-products-container');
            const packId = $container.data('pack-id');

            if (!packId) {
                return;
            }

            // Actualizar contadores localmente primero (para respuesta inmediata)
            let totalProducts = 0;
            let totalItems = 0;

            $('.ivbir-product-row').each(function() {
                totalProducts++;
                totalItems += parseInt($(this).find('.ivbir-qty-input').val()) || 1;
            });

            $('#ivbir-total-products').text(totalProducts);
            $('#ivbir-total-items').text(totalItems);

            // Obtener precios actualizados del servidor
            $.ajax({
                url: ivbirAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ivbir_get_pack_summary',
                    nonce: ivbirAdmin.nonce,
                    pack_id: packId
                },
                success: function(response) {
                    if (response.success) {
                        $('#ivbir-total-products').text(response.data.total_products);
                        $('#ivbir-total-items').text(response.data.total_items);
                        $('#ivbir-original-price').html(response.data.original_price);
                        $('#ivbir-discounted-price').html(response.data.discounted_price);
                    }
                }
            });
        },

        /**
         * Mostrar toast
         */
        showToast: function(message, type) {
            type = type || 'success';

            // Eliminar toasts anteriores
            $('.ivbir-toast').remove();

            const $toast = $(`<div class="ivbir-toast ${type}">${message}</div>`);
            $('body').append($toast);

            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Debounce helper
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        IVBIR_Admin.init();
    });

})(jQuery);
