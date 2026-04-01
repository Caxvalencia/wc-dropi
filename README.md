# Dropify for WooCommerce

> Disclaimer
>
> Este repositorio es un fork/adaptación para uso personal. El plugin original no es de mi autoría y los cambios documentados aquí fueron realizados únicamente para cubrir mis necesidades operativas y de desarrollo.

Plugin de WordPress para importar productos desde Dropi hacia WooCommerce y enviar pedidos de WooCommerce a Dropi.

Versión actual: `4.7.2`

Repositorio: `https://github.com/Caxvalencia/wc-dropi`

## Qué hace

- Lista productos de Dropi desde el panel de WordPress.
- Importa productos simples y variables a WooCommerce.
- Permite crear un producto nuevo o vincularlo con uno existente.
- Sincroniza nombre, descripción, precio, imágenes y stock.
- Soporta múltiples tokens/tiendas de Dropi.
- Envía pedidos de WooCommerce hacia Dropi.
- Incluye importación masiva por `product id`.

## Requisitos

- WordPress
- WooCommerce activo
- PHP 7.0 o superior
- Un token válido de integración de Dropi

## Instalación del plugin

1. Copia el plugin en `wp-content/plugins/wc-dropi-integration`.
2. Actívalo desde el panel de WordPress.
3. Verifica que WooCommerce esté activo.
4. En el menú `Dropi`, configura al menos una tienda con su token.

## Configuración inicial

Una vez activado:

1. Ve a `Dropi`.
2. Registra el token de integración de tu tienda en Dropi.
3. Define si la sincronización se hará manual o automáticamente según tu flujo.
4. Abre `Dropi > Productos` para consultar e importar productos.

## Flujo de importación

### Importación individual

Desde `Dropi > Productos` puedes:

- importar un producto nuevo,
- vincularlo con un producto existente en WooCommerce,
- elegir si quieres guardar nombre, descripción, precio, imágenes y stock,
- importar variaciones cuando el producto es variable.

### Importación masiva por IDs

La pantalla de productos incluye el botón `Importar por IDs`.

Ese flujo permite:

- seleccionar la tienda/token,
- pegar muchos `product id` separados por comas, espacios o saltos de línea,
- importar productos simples y variables,
- ver el progreso de cada importación,
- ver al final un resumen con los IDs que no pudieron cargarse.

## Mejoras recientes

En el trabajo reciente sobre este repositorio se hicieron estos ajustes relevantes:

- corrección de importación de imágenes usando las APIs nativas de WordPress,
- preservación correcta de descripción HTML y caracteres especiales,
- corrección del flujo `SYNC` para actualizar contenido existente,
- manejo más robusto de rate limit de Dropi (`Too Many Attempts` / `429`),
- reutilización de la respuesta del API en el flujo masivo para evitar consultas duplicadas,
- resumen visual de IDs fallidos al final de la importación masiva,
- entorno local con Docker y empaquetado final por `Makefile`.

El detalle completo está en [CHANGELOG.md](./CHANGELOG.md).

## Desarrollo local

El repositorio incluye un entorno Docker para desarrollo.

### Levantar el stack

```bash
cp .env.example .env
make up
make setup
```

Servicios por defecto:

- WordPress: `http://localhost:8080`
- Adminer: `http://localhost:8081`
- MariaDB: `localhost:3307`

### Empaquetar el plugin

```bash
make package
```

El zip final se genera en `dist/`.

## Estructura relevante

- [wc-dropi-integration.php](./wc-dropi-integration.php): bootstrap del plugin
- [clasess/Dropi.php](./clasess/Dropi.php): inicialización general
- [clasess/Products.php](./clasess/Products.php): controlador de productos y AJAX
- [clasess/models/ProductsModel.php](./clasess/models/ProductsModel.php): lógica de importación y sincronización
- [clasess/Woocomerce.php](./clasess/Woocomerce.php): integración con pedidos WooCommerce
- [js/product-events.js](./js/product-events.js): interacciones del panel de productos

## Documentación adicional

- API Dropi CO por `curl`: [DROPI-CO-API-CURL.md](./DROPI-CO-API-CURL.md)
- Historial de cambios: [CHANGELOG.md](./CHANGELOG.md)

## Troubleshooting

### No aparecen imágenes importadas

Reimporta el producto después de haber actualizado el plugin. Los productos importados antes de la corrección pueden haber quedado con adjuntos vacíos.

### Algunos IDs fallan en importación masiva

Dropi puede responder con rate limit. El flujo actual reintenta y además muestra un resumen final con los IDs fallidos para que puedas reprocesarlos.

### El plugin no muestra productos o no importa

Revisa:

- que WooCommerce esté activo,
- que exista al menos un token configurado,
- que la tienda seleccionada corresponda al token correcto,
- y los logs de WooCommerce si necesitas diagnóstico técnico.
