# Changelog

Todos los cambios relevantes realizados sobre `wc-dropi-integration`.

## [4.7.4] - 2026-04-05

### Corregido
- Sincronización de órdenes hacia Dropi:
  - Se corrigió el cálculo de stock para productos variables al construir el payload de la orden, tomando el stock real de `warehouse_product_variation` en vez del stock del padre.
  - Se corrigió el envío de pedidos mixtos con productos de distintos proveedores, generando un `shop_order_id` único por grupo/proveedor para evitar rechazos por orden duplicada en Dropi.
  - Se agregó protección para no reenviar órdenes ya sincronizadas cuando el pedido ya tiene `_dropi_order_id` y `_is_dropi_order = Yes`.
  - Se mejoró el manejo de reintentos parciales, conservando los IDs Dropi ya creados por proveedor y evitando reenvíos innecesarios de grupos ya procesados.
  - Se endureció el parsing de la respuesta de Dropi para extraer correctamente IDs de orden y para usar el detalle real del error cuando el API responde con `message` genérico.
  - Se agregó reconstrucción automática del `variation_id` de Dropi cuando una variación WooCommerce tiene metadatos legacy dañados, usando el producto padre, el SKU y los atributos de la variación.
  - Se empezó a guardar el mapa `_dropi_order_group_map` por proveedor para mejorar la trazabilidad de órdenes partidas en varios grupos.

### Validado
- Se validó la orden mixta `1147`, quedando sincronizada correctamente en Dropi con múltiples IDs de orden guardados localmente.
- Se validó que una segunda ejecución sobre una orden ya sincronizada no vuelve a reenviarla a Dropi.
- Se validó la reconstrucción del `variation_id` de la variación `557` del producto `652784` durante la creación de la orden.

## [4.7.3] - 2026-04-02

### Agregado
- Documento [DROPI-CO-API-CURL.md](/Users/cax/Desktop/projects/wc-dropi-integration/DROPI-CO-API-CURL.md) con ejemplos `curl` para los endpoints de Dropi CO detectados en el plugin.
- Entorno de desarrollo local con Docker usando [docker-compose.yml](/Users/cax/Desktop/projects/wc-dropi-integration/docker-compose.yml), [Makefile](/Users/cax/Desktop/projects/wc-dropi-integration/Makefile), `.env.example`, `docker/README.md`, `docker/scripts/setup-local.sh`, `docker/scripts/wp.sh` y `docker/wordpress/php.ini`.
- Flujo de importación masiva por `product id` desde la pantalla de productos:
  - Botón `Importar por IDs`.
  - Modal con selector de tienda.
  - Campo para pegar IDs separados por coma, espacios o saltos de línea.
  - Progreso por producto dentro de la modal.
  - Importación automática de variaciones cuando el producto Dropi es variable.
- Resumen visual al final de la modal con los IDs que no pudieron importarse.
- Columna `Precio Proveedor` en la lista de productos de WooCommerce para productos sincronizados con Dropi.
- Opción para limpiar variaciones existentes al sincronizar un producto variable ya vinculado, tanto en importación individual como en importación masiva.
- Opción para sobrescribir imágenes de variaciones existentes en sincronizaciones individuales y masivas.
- Confirmación explícita antes de sobrescribir imágenes de variaciones ya existentes.
- Acción `Re-sincronizar Dropi` en la lista de productos de WooCommerce para productos ya importados.
- Mejoras en la vista `Info Sincronizada`:
  - Link directo al editor real del producto WooCommerce.
  - Acción de re-sincronizar desde la misma vista.
  - Link en `ID Dropi` hacia la lista de productos Dropi filtrada por ese ID.

### Corregido
- Selector de ciudad para checkout:
  - Se agregó soporte para el checkout por bloques de WooCommerce, donde el campo `City` antes seguía como texto libre.
  - El selector de ciudad ahora también responde a cambios de país/departamento en la UI de Blocks y mantiene sincronizado el valor real del input usado por WooCommerce.
  - Se corrigió un bucle de repintado del selector en Blocks filtrando mutaciones generadas por el propio plugin y evitando reconstrucciones innecesarias del `select`.
- Importación de imágenes de productos:
  - Se reemplazó la descarga directa con `file_get_contents()` por sideload usando APIs de WordPress/WooCommerce.
  - Se normalizaron URLs de imágenes para evitar archivos vacíos o rutas inválidas.
  - Se corrigió la asignación de imagen destacada y galería.
  - Ahora los attachments de Dropi se reutilizan si la imagen ya había sido importada antes, evitando descargas y duplicados innecesarios.
  - La galería del producto se deduplica antes de guardarse en WooCommerce.
- Importación de contenido del producto:
  - Se preserva mejor la descripción HTML y los acentos.
  - Se corrigió el saneamiento de datos en el flujo AJAX para no degradar el contenido.
  - El flujo `SYNC` ahora actualiza correctamente nombre y descripción mediante `wp_update_post()`.
- Flujo no AJAX de importación desde la tabla:
  - Se corrigió el paso de la tienda seleccionada.
  - Se corrigió la interpretación del resultado de `import_product()`.
- Flujo de re-sincronización desde listados administrativos:
  - La confirmación del re-sync deja explícito que el stock siempre se valida y actualiza, independientemente de la decisión sobre imágenes.
  - Se corrigió la actualización de `stock_status` en productos variables re-sincronizados desde WooCommerce.
  - Se corrigió la sincronización de `wc_product_meta_lookup` para que el listado admin refleje correctamente el estado de inventario.
- Robustez del importador ante respuestas inválidas de Dropi:
  - Ya no se generan fatales cuando Dropi no devuelve un objeto de producto válido.
  - Ahora se responde con errores controlados y mensajes claros para AJAX.
- Respuesta AJAX del importador masivo:
  - Se evita contaminar el JSON con notices HTML.
  - Se devuelve `post_id` tanto en creación como en actualización.
- Sincronización de productos variables:
  - Se corrigió la actualización de inventario en variaciones existentes.
  - Se corrigió la persistencia de `_stock`, `_manage_stock` y `_stock_status`.
  - Se corrigió la asignación de imagen a cada variación.
  - Las variaciones ya no reciben automáticamente la imagen principal del producto cuando Dropi no trae una imagen específica para esa variación.
  - Si una variación no tiene foto propia en Dropi, se limpia `_thumbnail_id` para evitar duplicados visuales y media redundante.
  - Las miniaturas editadas manualmente en variaciones de WooCommerce ya no se sobrescriben por defecto durante la sincronización.
  - La sobrescritura de imágenes de variaciones ahora solo ocurre si el usuario la solicita expresamente.
  - Se dejó de perder la información de `warehouse_product_variation` al guardar `_dropi_variation`.
  - Se corrigieron casos donde el nombre del atributo de variación llegaba en una estructura distinta.
  - Se evitó el uso de un `variation_id` indefinido en ciertos flujos de sincronización.
  - Al limpiar variaciones existentes, ya no se borran attachments físicos asociados a esas variaciones para no eliminar media reutilizada por error.
- Sincronización del producto `652784`:
  - Se validó y corrigió la actualización de stock para las variaciones `AR-191AZU`, `AR-191VIN` y `AR-191VER`.

### Mejorado
- Manejo de rate limiting de Dropi:
  - `getProduct()` ahora hace más reintentos.
  - Se detecta `Too Many Attempts` y HTTP `429`.
  - Se añadió backoff progresivo entre intentos.
- Estrategia de SKU para productos importados desde Dropi:
  - El producto padre ahora usa siempre el formato `DROPI-<product_id>`.
  - Las variaciones ahora usan el formato `DROPI-<product_id>-DV<variation_id>`.
  - Se deja de depender del SKU original de Dropi como identificador principal en WooCommerce.
  - Esto reduce colisiones futuras con SKUs existentes en la tienda.
- Eficiencia del importador masivo:
  - El backend reutiliza la primera respuesta de Dropi y evita pedir el mismo producto dos veces por cada ID.
  - Se aumentó la pausa entre productos en el flujo masivo para bajar la presión sobre el API.
- Trazabilidad de productos importados:
  - Se añadió una búsqueda de producto WooCommerce por `_dropi_product_id` y token para detectar importaciones existentes y sincronizar en vez de duplicar.
- Reuso de variaciones existentes:
  - Cuando no hay mapeo manual, el plugin ahora intenta vincular variaciones por SKU antes de crear nuevas.
  - Esto evita duplicados en sincronizaciones individuales y masivas.
- Persistencia de datos Dropi en WooCommerce:
  - Se guarda `_dropi_supplier_price` para facilitar la visualización del precio proveedor en el admin.

### Validado
- Se levantó y configuró el stack local con WordPress, WooCommerce y el plugin activo.
- Se validó manualmente en `checkout` con bloques que el campo `City` aparece como lista para Colombia y cambia correctamente al modificar el departamento.
- Se validó que el selector de ciudad en Blocks ya no entra en bucle visual de reapertura/repintado.
- Se probaron importaciones reales por AJAX contra varios `product id` que previamente fallaban.
- Se verificó que el flujo masivo ya no cae con `Internal Server Error` cuando Dropi rate-limita o devuelve respuesta inválida.
- Se verificó la limpieza de un producto variable con historial de duplicados, pasando de 12 variaciones a 4 variaciones correctas.
- Se verificó una segunda sincronización posterior sin limpieza y sin duplicación de variaciones.
- Se validó en local la acción de `Re-sincronizar Dropi` desde la lista de productos de WooCommerce.
- Se validó la sincronización final de stock e imagen para variaciones ya existentes.
- Se validó que dos re-sincronizaciones consecutivas del producto `2097766` no crean nuevos attachments y que sus variaciones no duplican la imagen principal.
- Se validó que dos re-sincronizaciones consecutivas del producto `652784` no crean nuevos attachments y conservan solo las miniaturas de variación que realmente existen en Dropi.
- Se validó que una variación con imagen manual en WooCommerce conserva su `_thumbnail_id` cuando la sobrescritura está desactivada.
- Se validó que la misma variación sí cambia de imagen cuando la sobrescritura está activada explícitamente.
- Se validó el producto `215731` re-sincronizado desde WooCommerce con estado final `instock` tanto en metas del producto como en `wc_product_meta_lookup`.
