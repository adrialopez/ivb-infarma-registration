<?php
/**
 * Formulario de Usuario Personalizado
 *
 * @package IVB_Infarma_Registration
 * @since 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class IVBIR_User_Form {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'maybe_customize_user_form'));
    }

    /**
     * Verificar si debemos personalizar el formulario
     */
    public function maybe_customize_user_form() {
        global $pagenow;

        // Solo en la página de añadir usuario
        if ($pagenow !== 'user-new.php') {
            return;
        }

        $current_user = wp_get_current_user();
        $required_role = IVB_Infarma_Registration::get_setting('user_role_required', 'infarma');

        // SOLO mostrar formulario personalizado si el usuario tiene el rol configurado
        // Todos los demás usuarios (sin importar su rol) verán el formulario por defecto
        if (!in_array($required_role, $current_user->roles)) {
            return; // No tiene el rol requerido, mostrar formulario por defecto de WordPress
        }

        // El usuario tiene el rol requerido, personalizar formulario
        add_action('admin_print_styles', array($this, 'print_form_styles'));
        add_action('admin_print_footer_scripts', array($this, 'print_form_scripts'));
        add_action('user_profile_update_errors', array($this, 'validate_user_form'), 10, 3);
    }

    /**
     * Imprimir estilos del formulario
     */
    public function print_form_styles() {
        ?>
        <style>
            #adminmenumain,
            #wpadminbar,
            #wpfooter,
            #screen-meta-links {
                display: none !important;
            }

            body.wp-admin {
                margin: 0;
                padding: 0;
            }

            #wpcontent {
                margin-left: 0 !important;
                padding-left: 0 !important;
            }

            #wpbody-content {
                padding: 0 20px;
            }

            .categorydiv div.tabs-panel,
            .customlinkdiv div.tabs-panel,
            .posttypediv div.tabs-panel,
            .taxonomydiv div.tabs-panel,
            .wp-tab-panel {
                border: none !important;
            }

            .wrap {
                margin: 2rem auto !important;
                max-width: 900px;
                width: calc(100% - 40px);
                background: #fff;
                padding: 2rem;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }

            .wrap h1 {
                text-align: center;
                margin-bottom: 1.5rem;
                font-size: 1.5rem;
                color: #1d2327;
            }

            .ivbir-logo-container {
                text-align: center;
                margin-bottom: 2rem;
                animation: fadeIn 0.6s ease;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .ivbir-logo-container img {
                max-width: 250px;
                height: auto;
                filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
                transition: transform 0.3s ease;
            }

            .ivbir-logo-container img:hover {
                transform: scale(1.05);
            }

            .ivbir-form-title {
                text-align: center;
                font-size: 1.5rem;
                font-weight: 700;
                color: #1d2327;
                margin-bottom: 2.5rem;
                padding: 1.5rem;
                background: linear-gradient(135deg, #4d738a 0%, #3d5a6a 100%);
                color: white;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(77, 115, 138, 0.3);
                text-transform: uppercase;
                letter-spacing: 1px;
                animation: slideIn 0.6s ease 0.2s both;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            /* Ocultar campos no necesarios */
            .wrap > p:first-of-type,
            .user-pass1-wrap,
            .user-language-wrap,
            tr:has(> td > #send_user_notification),
            #add-new-user {
                display: none !important;
            }

            /* Ocultar campos de user_login y url */
            tr:has(#user_login),
            tr:has(#url) {
                display: none !important;
            }

            /* Estilos de formulario */
            .form-table {
                border-collapse: separate;
                width: 100%;
            }

            .form-table th {
                vertical-align: top;
                text-align: left;
                padding: 12px 10px 12px 0;
                width: 180px;
                font-weight: 600;
                color: #1d2327;
            }

            .form-table td {
                padding: 10px 0;
            }

            .form-table input[type="text"],
            .form-table input[type="email"],
            .form-table input[type="password"],
            .form-table input[type="number"],
            .form-table select,
            .form-table textarea {
                width: 100%;
                max-width: 400px;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.2s, box-shadow 0.2s;
            }

            .form-table input:focus,
            .form-table select:focus,
            .form-table textarea:focus {
                border-color: #4d738a;
                box-shadow: 0 0 0 3px rgba(77, 115, 138, 0.1);
                outline: none;
            }

            /* Secciones del formulario extra */
            .ivbir-section {
                margin-top: 2.5rem;
                padding: 2rem;
                background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                border-radius: 12px;
                border: 1px solid #e0e0e0;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                position: relative;
                overflow: hidden;
            }

            .ivbir-section::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #4d738a 0%, #3d5a6a 100%);
            }

            .ivbir-section h2 {
                font-size: 1.3rem;
                font-weight: 700;
                color: #1d2327;
                margin-bottom: 1.5rem;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .ivbir-section h2 .dashicons {
                font-size: 24px;
                color: #4d738a;
                background: rgba(77, 115, 138, 0.1);
                padding: 8px;
                border-radius: 8px;
            }

            .ivbir-form-row {
                display: flex;
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .ivbir-form-group {
                flex: 1;
            }

            .ivbir-form-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 0.95rem;
            }

            .ivbir-form-group input,
            .ivbir-form-group select {
                width: 100%;
                padding: 12px 14px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
                transition: all 0.2s ease;
                background: white;
            }

            .ivbir-form-group input:focus,
            .ivbir-form-group select:focus {
                border-color: #4d738a;
                box-shadow: 0 0 0 4px rgba(77, 115, 138, 0.1);
                outline: none;
            }

            .ivbir-form-group input:hover:not(:focus),
            .ivbir-form-group select:hover:not(:focus) {
                border-color: #b0b0b0;
            }

            .ivbir-form-group.full-width {
                flex: 0 0 100%;
            }

            /* Packs - Diseño Premium */
            .ivbir-packs-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 380px));
                gap: 1.5rem;
                margin-top: 1.5rem;
                justify-content: center;
            }

            .ivbir-pack-card {
                position: relative;
                border: 3px solid #e0e0e0;
                border-radius: 16px;
                padding: 0;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                background: #fff;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            }

            .ivbir-pack-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(77, 115, 138, 0.15);
                border-color: #4d738a;
            }

            .ivbir-pack-card.selected {
                border-color: #4d738a;
                box-shadow: 0 8px 24px rgba(77, 115, 138, 0.25);
                transform: translateY(-2px);
            }

            .ivbir-pack-card input[type="radio"] {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }

            /* Badge de descuento */
            .ivbir-pack-badge {
                position: absolute;
                top: 12px;
                right: 12px;
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
                color: white;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 700;
                z-index: 2;
                box-shadow: 0 2px 8px rgba(238, 90, 111, 0.3);
            }

            /* Imágenes de productos */
            .ivbir-pack-images {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                padding: 16px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-bottom: 1px solid #e0e0e0;
            }

            .ivbir-pack-image {
                aspect-ratio: 1;
                border-radius: 8px;
                overflow: hidden;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .ivbir-pack-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s;
            }

            .ivbir-pack-card:hover .ivbir-pack-image img {
                transform: scale(1.1);
            }

            /* Contenido del pack */
            .ivbir-pack-content {
                padding: 20px;
            }

            .ivbir-pack-name {
                font-size: 1.25rem;
                font-weight: 700;
                color: #1d2327;
                margin: 0 0 8px;
                line-height: 1.3;
            }

            .ivbir-pack-description {
                font-size: 0.9rem;
                color: #666;
                margin: 0 0 12px;
                line-height: 1.5;
            }

            .ivbir-pack-stats {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.85rem;
                color: #666;
                margin-bottom: 16px;
                padding-bottom: 16px;
                border-bottom: 1px solid #f0f0f0;
            }

            .ivbir-pack-stats strong {
                color: #4d738a;
                font-weight: 600;
            }

            .ivbir-separator {
                color: #ddd;
            }

            /* Precios */
            .ivbir-pack-pricing {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 12px 16px;
                border-radius: 8px;
                margin: -20px -20px 0;
                padding-top: 16px;
            }

            .ivbir-price-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
            }

            .ivbir-price-row:last-child {
                margin-bottom: 0;
            }

            .ivbir-price-label {
                font-size: 0.85rem;
                color: #666;
            }

            .ivbir-price-original {
                text-decoration: line-through;
                color: #999;
                font-size: 0.95rem;
            }

            .ivbir-price-final-row {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 2px solid rgba(77, 115, 138, 0.1);
            }

            .ivbir-price-final-row .ivbir-price-label {
                font-weight: 600;
                color: #1d2327;
            }

            .ivbir-price-final {
                font-size: 1.5rem;
                font-weight: 700;
                color: #4d738a;
            }

            /* Checkmark cuando está seleccionado */
            .ivbir-pack-checkmark {
                position: absolute;
                top: 12px;
                left: 12px;
                width: 32px;
                height: 32px;
                background: #4d738a;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                opacity: 0;
                transform: scale(0);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 2px 8px rgba(77, 115, 138, 0.4);
            }

            .ivbir-pack-card.selected .ivbir-pack-checkmark {
                opacity: 1;
                transform: scale(1);
            }

            .ivbir-pack-checkmark svg {
                width: 20px;
                height: 20px;
            }

            /* Checkbox de envío diferente */
            .ivbir-checkbox-group {
                margin: 1rem 0;
            }

            .ivbir-checkbox-group label {
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
            }

            .ivbir-checkbox-group input[type="checkbox"] {
                width: 18px;
                height: 18px;
            }

            /* Campos de envío */
            #ivbir-shipping-fields {
                display: none;
                margin-left: 1rem;
                padding-left: 1rem;
                border-left: 3px solid #4d738a;
            }

            /* Botón submit */
            p.submit {
                text-align: center;
                margin-top: 3rem;
                padding-top: 2rem;
                border-top: 2px solid #e0e0e0;
            }

            #createusersub {
                background: linear-gradient(135deg, #4d738a 0%, #3d5a6a 100%) !important;
                border: none !important;
                color: #fff !important;
                padding: 16px 48px !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                border-radius: 50px !important;
                cursor: pointer;
                transition: all 0.3s ease !important;
                box-shadow: 0 4px 12px rgba(77, 115, 138, 0.3);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                position: relative;
                overflow: hidden;
            }

            #createusersub::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.2);
                transform: translate(-50%, -50%);
                transition: width 0.6s, height 0.6s;
            }

            #createusersub:hover::before {
                width: 300px;
                height: 300px;
            }

            #createusersub:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(77, 115, 138, 0.4) !important;
            }

            #createusersub:active {
                transform: translateY(0);
            }

            /* Loading state */
            #createusersub.loading {
                pointer-events: none;
                opacity: 0.7;
            }

            #createusersub.loading::after {
                content: '';
                position: absolute;
                right: 20px;
                top: 50%;
                transform: translateY(-50%);
                width: 16px;
                height: 16px;
                border: 2px solid #fff;
                border-top-color: transparent;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to { transform: translateY(-50%) rotate(360deg); }
            }

            /* Notificaciones */
            .ivbir-notice {
                padding: 20px 24px;
                border-radius: 12px;
                margin-bottom: 2rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                animation: slideInDown 0.3s ease;
            }

            @keyframes slideInDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .ivbir-notice-error {
                background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
                border-left: 5px solid #dc3545;
                color: #721c24;
            }

            .ivbir-notice-error strong {
                display: block;
                font-size: 1.05rem;
                margin-bottom: 10px;
            }

            .ivbir-notice-error ul {
                margin: 0;
                padding-left: 20px;
            }

            .ivbir-notice-error li {
                margin-bottom: 6px;
                line-height: 1.5;
            }

            .ivbir-notice-success {
                background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
                border-left: 5px solid #28a745;
                color: #155724;
            }

            /* Ocultar campos ACF no necesarios */
            .form-table tr.acf-field {
                display: none !important;
            }

            .form-table tr.acf-field[data-name="tipo_identificador"],
            .form-table tr.acf-field[data-name="cif"],
            .form-table tr.acf-field[data-name="recargo_equivalencia_toggle"] {
                display: table-row !important;
            }

            /* Roles */
            .ivbir-roles-list {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                padding: 0 !important;
                margin: 0 !important;
            }

            .ivbir-roles-list li {
                list-style: none;
                margin: 0 !important;
                padding: 0 !important;
            }

            .ivbir-roles-list li label {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                background: transparent;
                cursor: pointer;
                transition: all 0.2s ease;
                font-weight: 500;
            }

            .ivbir-roles-list li label:hover {
                background: #f8f9fa;
            }

            .ivbir-roles-list li input[type="checkbox"]:checked + span {
                color: #4d738a;
                font-weight: 600;
            }

            .ivbir-roles-list li label:has(input:checked) {
                background: #e8f4f8;
            }

            .ivbir-roles-list li:not(.ivbir-visible-role) {
                display: none !important;
            }

            /* Mejorar aspecto de checkboxes de roles */
            .ivbir-roles-list input[type="checkbox"] {
                width: 18px;
                height: 18px;
                cursor: pointer;
            }

            /* Payment Method TPV Buttons */
            .ivbir-payment-buttons {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                margin-top: 1rem;
            }

            .ivbir-payment-button {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 1.5rem 1rem;
                background: white;
                border: 3px solid #e0e0e0;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                min-height: 120px;
            }

            .ivbir-payment-button:hover {
                transform: translateY(-4px);
                box-shadow: 0 8px 20px rgba(77, 115, 138, 0.15);
                border-color: #4d738a;
            }

            .ivbir-payment-button.selected {
                border-color: #4d738a;
                background: linear-gradient(135deg, #f8f9fa 0%, #e8f4f8 100%);
                box-shadow: 0 4px 16px rgba(77, 115, 138, 0.25);
            }

            .ivbir-payment-icon {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #4d738a;
                margin-bottom: 0.5rem;
            }

            .ivbir-payment-button.selected .ivbir-payment-icon {
                color: #3d5a6a;
            }

            .ivbir-payment-name {
                font-size: 1rem;
                font-weight: 600;
                color: #1d2327;
                text-align: center;
            }

            .ivbir-payment-checkmark {
                position: absolute;
                top: 10px;
                right: 10px;
                width: 28px;
                height: 28px;
                background: #4d738a;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                font-weight: 700;
                opacity: 0;
                transform: scale(0);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .ivbir-payment-button.selected .ivbir-payment-checkmark {
                opacity: 1;
                transform: scale(1);
            }

            .ivbir-payment-button:active {
                transform: translateY(-2px);
            }

            @media (max-width: 600px) {
                .ivbir-payment-buttons {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    /**
     * Imprimir scripts del formulario
     */
    public function print_form_scripts() {
        $settings = get_option('ivbir_settings', array());
        $logo_url = $settings['logo_url'] ?? '';
        $form_title = $settings['form_title'] ?? 'ALTA + PEDIDO INFARMA 2025';
        $allowed_roles = $settings['allowed_roles'] ?? array('farmacia', 'nutricionista', 'clinica');
        $default_role = $settings['default_role'] ?? 'farmacia';
        $allowed_payment_methods = $settings['allowed_payment_methods'] ?? array('bacs', 'redsys');
        $default_payment = $settings['default_payment_method'] ?? 'bacs';

        // Obtener países y estados de WooCommerce
        $countries_options = $this->get_countries_options();
        $states_json = $this->get_states_json();
        $payment_options = $this->get_payment_options($allowed_payment_methods, $default_payment);

        // Obtener packs activos
        $packs = ivbir()->pack_manager->get_active_packs();
        $packs_html = $this->get_packs_html($packs);
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Configuración
            var allowedRoles = <?php echo json_encode($allowed_roles); ?>;
            var defaultRole = <?php echo json_encode($default_role); ?>;
            var allStates = <?php echo $states_json; ?>;

            // Añadir logo y título
            $('#add-new-user').remove();
            $('.wrap').prepend(
                '<div class="ivbir-logo-container">' +
                    '<img src="<?php echo esc_url($logo_url); ?>" alt="Logo">' +
                '</div>' +
                '<div class="ivbir-form-title"><?php echo esc_js($form_title); ?></div>'
            );

            // Autocompletar user_login con email
            $('#email').on('blur change', function() {
                $('#user_login').val($(this).val());
            });

            // Cambiar label de apellidos a empresa
            $("label[for='last_name']").text("Empresa");

            // Activar notificación por defecto
            $('#send_user_notification').prop('checked', true);

            // Filtrar roles visibles y mejorar presentación
            var $rolesRow = $('#createuser').find('table.form-table tr:has(input[name="members_user_roles[]"])');
            var $rolesContainer = $rolesRow.find('ul');

            $rolesContainer.addClass('ivbir-roles-list');

            // Cambiar el label del campo de roles
            $rolesRow.find('th label').text('Perfiles Activos *');

            $rolesContainer.find('li').each(function() {
                var $li = $(this);
                var roleVal = $li.find('input[type="checkbox"][name="members_user_roles[]"]').val();

                if (allowedRoles.indexOf(roleVal) !== -1) {
                    $li.addClass('ivbir-visible-role').show();
                } else {
                    $li.removeClass('ivbir-visible-role').hide();
                }
            });

            $('input[type="checkbox"][value="' + defaultRole + '"]').prop('checked', true);

            // Añadir campos extra
            var extraFieldsHtml = `
                <div id="ivbir-extra-fields">
                    <div class="ivbir-section">
                        <h2><span class="dashicons dashicons-location"></span> Datos de Facturación</h2>

                        <div class="ivbir-form-row">
                            <div class="ivbir-form-group full-width">
                                <label for="billing_address">Dirección *</label>
                                <input type="text" name="billing_address" id="billing_address" required>
                            </div>
                        </div>

                        <div class="ivbir-form-row">
                            <div class="ivbir-form-group">
                                <label for="billing_postcode">Código Postal *</label>
                                <input type="text" name="billing_postcode" id="billing_postcode" required>
                            </div>
                            <div class="ivbir-form-group">
                                <label for="billing_city">Ciudad *</label>
                                <input type="text" name="billing_city" id="billing_city" required>
                            </div>
                        </div>

                        <div class="ivbir-form-row">
                            <div class="ivbir-form-group">
                                <label for="billing_country">País *</label>
                                <select name="billing_country" id="billing_country" required>
                                    <?php echo $countries_options; ?>
                                </select>
                            </div>
                            <div class="ivbir-form-group">
                                <label for="billing_state">Provincia *</label>
                                <select name="billing_state" id="billing_state" required>
                                    <option value="">Seleccionar...</option>
                                </select>
                            </div>
                        </div>

                        <div class="ivbir-form-row">
                            <div class="ivbir-form-group">
                                <label for="billing_phone">Teléfono *</label>
                                <input type="text" name="billing_phone" id="billing_phone" required>
                            </div>
                        </div>

                        <div class="ivbir-checkbox-group">
                            <label>
                                <input type="checkbox" name="different_shipping" id="different_shipping" value="1">
                                ¿Dirección de entrega diferente?
                            </label>
                        </div>

                        <div id="ivbir-shipping-fields">
                            <h3>Dirección de Envío</h3>

                            <div class="ivbir-form-row">
                                <div class="ivbir-form-group full-width">
                                    <label for="shipping_address">Dirección</label>
                                    <input type="text" name="shipping_address" id="shipping_address">
                                </div>
                            </div>

                            <div class="ivbir-form-row">
                                <div class="ivbir-form-group">
                                    <label for="shipping_postcode">Código Postal</label>
                                    <input type="text" name="shipping_postcode" id="shipping_postcode">
                                </div>
                                <div class="ivbir-form-group">
                                    <label for="shipping_city">Ciudad</label>
                                    <input type="text" name="shipping_city" id="shipping_city">
                                </div>
                            </div>

                            <div class="ivbir-form-row">
                                <div class="ivbir-form-group">
                                    <label for="shipping_country">País</label>
                                    <select name="shipping_country" id="shipping_country">
                                        <?php echo $countries_options; ?>
                                    </select>
                                </div>
                                <div class="ivbir-form-group">
                                    <label for="shipping_state">Provincia</label>
                                    <select name="shipping_state" id="shipping_state">
                                        <option value="">Seleccionar...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="ivbir-form-row">
                                <div class="ivbir-form-group">
                                    <label for="shipping_phone">Teléfono</label>
                                    <input type="text" name="shipping_phone" id="shipping_phone">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ivbir-section">
                        <h2><span class="dashicons dashicons-archive"></span> Selección de Pack</h2>
                        <div class="ivbir-packs-grid">
                            <?php echo $packs_html; ?>
                        </div>
                    </div>

                    <div class="ivbir-section">
                        <h2><span class="dashicons dashicons-money-alt"></span> Forma de Pago</h2>
                        <div class="ivbir-payment-buttons">
                            <?php echo $payment_options; ?>
                        </div>
                        <input type="hidden" name="payment_method" id="payment_method" value="">
                    </div>
                </div>
            `;

            $('#createuser').append(extraFieldsHtml);

            // Mover botón submit al final
            setTimeout(function() {
                var $submit = $('#createuser p.submit');
                if ($('#ivbir-extra-fields').length) {
                    $submit.insertAfter('#ivbir-extra-fields');
                }
            }, 50);

            // Cargar provincias al cambiar país
            function updateStates(countrySelect, stateSelect) {
                var country = $(countrySelect).val();
                var states = allStates[country] || {};
                var $stateSelect = $(stateSelect);

                $stateSelect.empty();

                if (Object.keys(states).length > 0) {
                    $.each(states, function(code, name) {
                        $stateSelect.append($('<option>', { value: code, text: name }));
                    });
                } else {
                    $stateSelect.append($('<option>', { value: '', text: 'Ninguna provincia' }));
                }
            }

            // Inicializar provincias
            updateStates('#billing_country', '#billing_state');
            updateStates('#shipping_country', '#shipping_state');

            $('#billing_country').on('change', function() {
                updateStates(this, '#billing_state');
            });

            $('#shipping_country').on('change', function() {
                updateStates(this, '#shipping_state');
            });

            // Toggle campos de envío
            $('#different_shipping').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ivbir-shipping-fields').slideDown();
                } else {
                    $('#ivbir-shipping-fields').slideUp();
                }
            });

            // Selección de pack con animación suave
            $('.ivbir-pack-card').on('click', function() {
                $('.ivbir-pack-card').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);

                // Animación de "pulse" al seleccionar
                $(this).css('transform', 'scale(0.98)');
                setTimeout(() => {
                    $(this).css('transform', '');
                }, 150);
            });

            // Selección de método de pago con botones TPV
            $('.ivbir-payment-button').on('click', function() {
                $('.ivbir-payment-button').removeClass('selected');
                $(this).addClass('selected');
                $('#payment_method').val($(this).data('payment'));

                // Animación de "pulse" al seleccionar
                $(this).css('transform', 'scale(0.95)');
                setTimeout(() => {
                    $(this).css('transform', '');
                }, 150);
            });

            // Inicializar valor del método de pago con el botón seleccionado
            var selectedPayment = $('.ivbir-payment-button.selected').data('payment');
            if (selectedPayment) {
                $('#payment_method').val(selectedPayment);
            }

            // Validación del formulario
            $('#createuser').on('submit', function(e) {
                var errors = [];

                if ($('#email').val().trim() === '') {
                    errors.push('El correo electrónico es obligatorio.');
                }

                if ($('#first_name').val().trim() === '') {
                    errors.push('El nombre es obligatorio.');
                }

                // Verificar rol seleccionado
                var rolesChecked = $('input[type="checkbox"][name="members_user_roles[]"]:checked').filter(function() {
                    return allowedRoles.indexOf($(this).val()) !== -1;
                });

                if (rolesChecked.length === 0) {
                    errors.push('Debes asignar al menos un perfil.');
                }

                // Campos de dirección
                var requiredBilling = ['billing_address', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_country', 'billing_state'];
                $.each(requiredBilling, function(i, field) {
                    if ($('#' + field).val().trim() === '') {
                        errors.push('El campo ' + field.replace('billing_', '') + ' es obligatorio.');
                    }
                });

                // Si envío diferente, validar campos de envío
                if ($('#different_shipping').is(':checked')) {
                    var requiredShipping = ['shipping_address', 'shipping_postcode', 'shipping_city', 'shipping_country', 'shipping_state'];
                    $.each(requiredShipping, function(i, field) {
                        if ($('#' + field).val().trim() === '') {
                            errors.push('El campo de envío ' + field.replace('shipping_', '') + ' es obligatorio.');
                        }
                    });
                }

                // Pack seleccionado
                if ($('input[name="custom_product"]:checked').length === 0) {
                    errors.push('Debes seleccionar un pack.');
                }

                // Método de pago seleccionado
                if (!$('#payment_method').val() || $('#payment_method').val().trim() === '') {
                    errors.push('Debes seleccionar un método de pago.');
                }

                if (errors.length > 0) {
                    e.preventDefault();

                    // Mostrar errores con animación
                    $('.ivbir-notice').remove();
                    var errorHtml = '<div class="ivbir-notice ivbir-notice-error" style="display:none;"><strong>⚠️ Por favor, revisa los siguientes campos:</strong><ul>';
                    $.each(errors, function(i, error) {
                        errorHtml += '<li>' + error + '</li>';
                    });
                    errorHtml += '</ul></div>';

                    $('.wrap').prepend(errorHtml);
                    $('.ivbir-notice').slideDown(300);
                    $('html, body').animate({ scrollTop: 0 }, 400);
                } else {
                    // Activar estado de carga
                    $('#createusersub').addClass('loading').prop('disabled', true);
                }
            });

            // Lógica de recargo de equivalencia (si existe el campo ACF)
            var cifInput = $('#acf-field_66d998ffe235c');

            if ($('#tipo_identificador').length) {
                $('#recargo_equivalencia_toggle').prop('disabled', true);

                $('#tipo_identificador').on('change', function() {
                    var tipo = $(this).val();

                    if (!tipo) {
                        cifInput.prop('disabled', true).removeAttr('pattern').attr('placeholder', '');
                        $('#recargo_equivalencia_row').hide();
                        $('#recargo_equivalencia_toggle').prop('checked', false);
                        $('input[name="members_user_roles[]"][value="re"]').prop('checked', false);
                        return;
                    }

                    cifInput.prop('disabled', false);

                    var patterns = {
                        'SL': { pattern: '^B\\d{8}$', placeholder: 'B12345678' },
                        'CB': { pattern: '^E\\d{8}$', placeholder: 'E12345678' },
                        'DNI': { pattern: '^\\d{8}[A-Za-z]$', placeholder: '12345678A' },
                        'SA': { pattern: '^A\\d{8}$', placeholder: 'A12345678' },
                        'ESPJ': { pattern: '^E\\d{8}$', placeholder: 'E12345678' },
                        'SJ': { pattern: '^J\\d{8}$', placeholder: 'J12345678' }
                    };

                    if (patterns[tipo]) {
                        cifInput.attr('pattern', patterns[tipo].pattern).attr('placeholder', patterns[tipo].placeholder);
                    } else {
                        cifInput.removeAttr('pattern').attr('placeholder', '');
                    }

                    var noRecargoTypes = ['SL', 'SA', 'SJ'];
                    $('#recargo_equivalencia_toggle').prop('disabled', noRecargoTypes.indexOf(tipo) !== -1);

                    if (noRecargoTypes.indexOf(tipo) !== -1) {
                        $('#recargo_equivalencia_row').hide();
                        $('#recargo_equivalencia_toggle').prop('checked', false);
                        $('input[name="members_user_roles[]"][value="re"]').prop('checked', false);
                    } else {
                        $('#recargo_equivalencia_row').show();
                    }
                });

                // Sincronizar checkbox de recargo de equivalencia con el rol 're'
                $('#recargo_equivalencia_toggle').on('change', function() {
                    var isChecked = $(this).is(':checked');
                    $('input[name="members_user_roles[]"][value="re"]').prop('checked', isChecked);
                });

                // Sincronizar rol 're' con checkbox de recargo de equivalencia
                $('input[name="members_user_roles[]"][value="re"]').on('change', function() {
                    var isChecked = $(this).is(':checked');
                    if (!$('#recargo_equivalencia_toggle').prop('disabled')) {
                        $('#recargo_equivalencia_toggle').prop('checked', isChecked);
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Obtener opciones de países
     */
    private function get_countries_options() {
        if (!function_exists('WC')) {
            return '<option value="ES" selected>España</option>';
        }

        $countries = WC()->countries->get_countries();
        $options = '';

        foreach ($countries as $code => $name) {
            $selected = ($code === 'ES') ? 'selected' : '';
            $options .= "<option value='{$code}' {$selected}>{$name}</option>";
        }

        return $options;
    }

    /**
     * Obtener estados en JSON
     */
    private function get_states_json() {
        if (!function_exists('WC')) {
            return '{}';
        }

        return json_encode(WC()->countries->get_states());
    }

    /**
     * Obtener opciones de pago como botones TPV
     */
    private function get_payment_options($allowed_methods, $default) {
        if (!function_exists('WC')) {
            $buttons = '<button type="button" class="ivbir-payment-button selected" data-payment="bacs">';
            $buttons .= '<span class="ivbir-payment-icon dashicons dashicons-money-alt"></span>';
            $buttons .= '<span class="ivbir-payment-name">Transferencia Bancaria</span>';
            $buttons .= '<span class="ivbir-payment-checkmark">✓</span>';
            $buttons .= '</button>';
            return $buttons;
        }

        $all_gateways = WC()->payment_gateways->payment_gateways();

        // Pre-check: ¿está el método por defecto disponible y habilitado?
        $default_available = false;
        foreach ($all_gateways as $chk_id => $chk_gw) {
            if ($chk_id === $default && in_array($chk_id, $allowed_methods) && $chk_gw->enabled === 'yes') {
                $default_available = true;
                break;
            }
        }

        $buttons = '';
        $is_first = true;

        foreach ($all_gateways as $id => $gateway) {
            if (in_array($id, $allowed_methods) && $gateway->enabled === 'yes') {
                $selected_class = ($id === $default || (!$default_available && $is_first)) ? 'selected' : '';
                $icon = 'dashicons-money-alt'; // Default icon

                // Customize icons based on payment method
                if (strpos($id, 'redsys') !== false || strpos($id, 'tpv') !== false) {
                    $icon = 'dashicons-credit-card';
                } elseif (strpos($id, 'paypal') !== false) {
                    $icon = 'dashicons-admin-site';
                } elseif (strpos($id, 'stripe') !== false) {
                    $icon = 'dashicons-cart';
                }

                $buttons .= '<button type="button" class="ivbir-payment-button ' . $selected_class . '" data-payment="' . esc_attr($id) . '">';
                $buttons .= '<span class="ivbir-payment-icon dashicons ' . $icon . '"></span>';
                $buttons .= '<span class="ivbir-payment-name">' . esc_html($gateway->get_title()) . '</span>';
                $buttons .= '<span class="ivbir-payment-checkmark">✓</span>';
                $buttons .= '</button>';

                $is_first = false;
            }
        }

        if (empty($buttons)) {
            $buttons = '<button type="button" class="ivbir-payment-button selected" data-payment="bacs">';
            $buttons .= '<span class="ivbir-payment-icon dashicons dashicons-money-alt"></span>';
            $buttons .= '<span class="ivbir-payment-name">Transferencia Bancaria</span>';
            $buttons .= '<span class="ivbir-payment-checkmark">✓</span>';
            $buttons .= '</button>';
        }

        return $buttons;
    }

    /**
     * Obtener HTML de packs
     */
    private function get_packs_html($packs) {
        if (empty($packs)) {
            return '<p>No hay packs configurados. <a href="' . admin_url('admin.php?page=ivbir-packs') . '">Configura packs aquí</a>.</p>';
        }

        $html = '';
        $first = true;

        foreach ($packs as $pack_id => $pack) {
            $summary = ivbir()->pack_manager->get_pack_summary($pack_id);
            $products = ivbir()->pack_manager->get_pack_products($pack_id);
            $checked = $first ? 'checked' : '';
            $selected_class = $first ? 'selected' : '';

            // Obtener primeras 4 imágenes de productos
            $product_images = array();
            foreach (array_slice($products, 0, 4) as $prod) {
                $product = wc_get_product($prod['product_id']);
                if ($product) {
                    $image_id = $product->get_image_id();
                    if ($image_id) {
                        $product_images[] = wp_get_attachment_image_url($image_id, 'thumbnail');
                    }
                }
            }

            $html .= '<label class="ivbir-pack-card ' . $selected_class . '" data-pack-id="' . esc_attr($pack_id) . '">';
            $html .= '<input type="radio" name="custom_product" value="' . esc_attr($pack_id) . '" ' . $checked . '>';

            // Badge de descuento si existe
            if ($pack['discount_percentage'] > 0) {
                $html .= '<div class="ivbir-pack-badge">-' . $pack['discount_percentage'] . '%</div>';
            }

            // Imágenes de productos
            if (!empty($product_images)) {
                $html .= '<div class="ivbir-pack-images">';
                foreach ($product_images as $img_url) {
                    $html .= '<div class="ivbir-pack-image"><img src="' . esc_url($img_url) . '" alt=""></div>';
                }
                $html .= '</div>';
            }

            $html .= '<div class="ivbir-pack-content">';
            $html .= '<h3 class="ivbir-pack-name">' . esc_html($pack['name']) . '</h3>';

            if ($pack['description']) {
                $html .= '<p class="ivbir-pack-description">' . esc_html($pack['description']) . '</p>';
            }

            $html .= '<div class="ivbir-pack-stats">';
            $html .= '<span><strong>' . $summary['total_products'] . '</strong> productos</span>';
            $html .= '<span class="ivbir-separator">·</span>';
            $html .= '<span><strong>' . $summary['total_items'] . '</strong> unidades</span>';
            $html .= '</div>';

            $html .= '<div class="ivbir-pack-pricing">';
            if ($pack['discount_percentage'] > 0) {
                $html .= '<div class="ivbir-price-row">';
                $html .= '<span class="ivbir-price-label">Precio original:</span>';
                $html .= '<span class="ivbir-price-original">' . wc_price($summary['original_price']) . '</span>';
                $html .= '</div>';
                $html .= '<div class="ivbir-price-row ivbir-price-final-row">';
                $html .= '<span class="ivbir-price-label">Precio final:</span>';
                $html .= '<span class="ivbir-price-final">' . wc_price($summary['discounted_price']) . '</span>';
                $html .= '</div>';
            } else {
                $html .= '<div class="ivbir-price-row ivbir-price-final-row">';
                $html .= '<span class="ivbir-price-label">Precio:</span>';
                $html .= '<span class="ivbir-price-final">' . wc_price($summary['original_price']) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>'; // .ivbir-pack-pricing

            $html .= '</div>'; // .ivbir-pack-content

            // Checkmark
            $html .= '<div class="ivbir-pack-checkmark">';
            $html .= '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
            $html .= '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>';
            $html .= '<path d="M8 12L11 15L16 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
            $html .= '</svg>';
            $html .= '</div>';

            $html .= '</label>';

            $first = false;
        }

        return $html;
    }

    /**
     * Validar formulario de usuario
     */
    public function validate_user_form($errors, $update, $user) {
        if ($update) {
            return $errors;
        }

        $current_user = wp_get_current_user();
        $required_role = IVB_Infarma_Registration::get_setting('user_role_required', 'infarma');

        if (!in_array($required_role, $current_user->roles)) {
            return $errors;
        }

        // Validaciones del servidor
        if (empty($_POST['email'])) {
            $errors->add('empty_email', '<strong>Error</strong>: El correo electrónico es obligatorio.');
        }

        if (empty($_POST['first_name'])) {
            $errors->add('empty_first_name', '<strong>Error</strong>: El nombre es obligatorio.');
        }

        $allowed_roles = IVB_Infarma_Registration::get_setting('allowed_roles', array('farmacia', 'nutricionista', 'clinica'));
        $roles = isset($_POST['members_user_roles']) ? $_POST['members_user_roles'] : array();

        $has_valid_role = false;
        foreach ($roles as $role) {
            if (in_array($role, $allowed_roles)) {
                $has_valid_role = true;
                break;
            }
        }

        if (!$has_valid_role) {
            $errors->add('empty_role', '<strong>Error</strong>: Debes asignar al menos un perfil de usuario válido.');
        }

        // Validar campos de dirección
        $required_address = array(
            'billing_address' => 'Dirección',
            'billing_postcode' => 'Código Postal',
            'billing_city' => 'Ciudad',
            'billing_phone' => 'Teléfono',
            'billing_country' => 'País',
            'billing_state' => 'Provincia',
        );

        foreach ($required_address as $field => $label) {
            if (empty($_POST[$field])) {
                $errors->add($field, sprintf('<strong>Error</strong>: El campo %s es obligatorio.', $label));
            }
        }

        // Validar pack
        if (empty($_POST['custom_product'])) {
            $errors->add('empty_pack', '<strong>Error</strong>: Debes seleccionar un pack.');
        }

        // Validar campos de envío si es diferente
        if (!empty($_POST['different_shipping']) && $_POST['different_shipping'] == '1') {
            $required_shipping = array(
                'shipping_address' => 'Dirección de envío',
                'shipping_postcode' => 'Código Postal de envío',
                'shipping_city' => 'Ciudad de envío',
                'shipping_country' => 'País de envío',
                'shipping_state' => 'Provincia de envío',
            );

            foreach ($required_shipping as $field => $label) {
                if (empty($_POST[$field])) {
                    $errors->add('empty_' . $field, sprintf('<strong>Error</strong>: El campo %s es obligatorio.', $label));
                }
            }
        }

        return $errors;
    }
}
