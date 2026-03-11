# IVB Infarma Registration

Plugin de WordPress para gestionar el alta de usuarios de Infarma con creación automática de pedidos y packs configurables.

**Autor:** Thinking Idea
**Versión actual:** 0.3.2

---

## Características

- Gestión de packs con productos de WooCommerce y descuentos
- Formulario personalizado de registro con diseño premium
- Creación automática de pedidos al registrar usuario
- Campos de facturación y envío
- Métodos de pago configurables (TPV, transferencia, etc.)
- Compatible con ACF y recargo de equivalencia
- Campo "comercial" automático con el email del creador

---

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+

---

## Instalación

1. Subir la carpeta `ivb-infarma-registration` a `/wp-content/plugins/`
2. Activar el plugin desde el panel de WordPress
3. Ir a **IVB Infarma > Configuración** para configurar el plugin
4. Crear los packs en **IVB Infarma > Gestionar Packs**

---

## Uso

### Crear un Pack

1. Ir a **IVB Infarma > Gestionar Packs**
2. Clic en **Añadir Nuevo Pack**
3. Rellenar nombre, descripción, descuento y orden
4. Buscar y añadir productos con cantidades
5. Guardar

### Registrar un Usuario

1. Como usuario con el rol configurado, ir a **Usuarios > Añadir Nuevo**
2. Rellenar datos del usuario, dirección, pack y método de pago
3. Guardar. El pedido se crea automáticamente.

---

## Changelog

### 0.3.2
- Campo "comercial": guarda el email del admin que crea el usuario
- Fix fondo de pantalla con dos colores
- Fix imagen del pack demasiado grande con un solo pack
- Limpieza de código de debug

### 0.3.1
- Fix verificación de nonce que bloqueaba la creación de pedidos

### 0.3.0
- Sistema de debug con transients para sobrevivir redirecciones

### 0.2.9
- Campo renombrado de `custom_pack` a `custom_product`

### 0.2.8
- Eliminada verificación de rol que bloqueaba el hook

### 0.2.7
- Añadido sistema de logging para diagnóstico

### 0.2.6
- Añadido guardado de datos de facturación/envío como user_meta

### 0.2.5
- Optimización de rendimiento con caché estática y transients
- Validaciones de precios y descuentos

### 0.2.4
- Fix creación automática de tablas de base de datos

### 0.2.3
- Fix formatos de datos en consultas SQL

### 0.2.2
- Mejoras de debugging en consola

### 0.2.1
- Reescritura de arquitectura de packs simplificada

### 0.2.0
- Fix persistencia de productos y cálculo de precios

### 0.1.9
- Fix productos desaparecían al guardar pack

### 0.1.8
- Fix gestión de packs y precios en tiempo real

### 0.1.7
- Recargo de equivalencia mejorado

### 0.1.6
- Soporte para variaciones de productos en packs

### 0.1.5
- Mejora visual de perfiles

### 0.1.4
- Control de acceso basado en roles

### 0.1.3
- Gestión de packs simplificada, métodos de pago con interfaz TPV

### 0.1.2
- Filtrado de roles corregido

### 0.1.1
- Rediseño visual premium del formulario

### 0.1.0
- Lanzamiento inicial

---

## Estructura del Plugin

```
ivb-infarma-registration/
├── ivb-infarma-registration.php       # Archivo principal
├── includes/
│   ├── class-ivbir-pack-manager.php   # Gestión de packs
│   ├── class-ivbir-admin.php          # Panel de administración
│   ├── class-ivbir-user-form.php      # Formulario personalizado
│   └── class-ivbir-order-handler.php  # Creación de pedidos
├── assets/
│   ├── css/
│   │   └── admin.css                  # Estilos del admin
│   └── js/
│       └── admin.js                   # JavaScript del admin
└── README.md
```

---

## Créditos

Desarrollado por **Thinking Idea** (https://thinkingidea.com/)

## Licencia

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
