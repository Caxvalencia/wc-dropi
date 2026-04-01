# Changelog

Todos los cambios relevantes realizados sobre `wc-dropi-integration`.

## [4.7.2] - 2026-04-01

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

### Corregido
- Importación de imágenes de productos:
  - Se reemplazó la descarga directa con `file_get_contents()` por sideload usando APIs de WordPress/WooCommerce.
  - Se normalizaron URLs de imágenes para evitar archivos vacíos o rutas inválidas.
  - Se corrigió la asignación de imagen destacada y galería.
- Importación de contenido del producto:
  - Se preserva mejor la descripción HTML y los acentos.
  - Se corrigió el saneamiento de datos en el flujo AJAX para no degradar el contenido.
  - El flujo `SYNC` ahora actualiza correctamente nombre y descripción mediante `wp_update_post()`.
- Flujo no AJAX de importación desde la tabla:
  - Se corrigió el paso de la tienda seleccionada.
  - Se corrigió la interpretación del resultado de `import_product()`.
- Robustez del importador ante respuestas inválidas de Dropi:
  - Ya no se generan fatales cuando Dropi no devuelve un objeto de producto válido.
  - Ahora se responde con errores controlados y mensajes claros para AJAX.
- Respuesta AJAX del importador masivo:
  - Se evita contaminar el JSON con notices HTML.
  - Se devuelve `post_id` tanto en creación como en actualización.

### Mejorado
- Manejo de rate limiting de Dropi:
  - `getProduct()` ahora hace más reintentos.
  - Se detecta `Too Many Attempts` y HTTP `429`.
  - Se añadió backoff progresivo entre intentos.
- Eficiencia del importador masivo:
  - El backend reutiliza la primera respuesta de Dropi y evita pedir el mismo producto dos veces por cada ID.
  - Se aumentó la pausa entre productos en el flujo masivo para bajar la presión sobre el API.
- Trazabilidad de productos importados:
  - Se añadió una búsqueda de producto WooCommerce por `_dropi_product_id` y token para detectar importaciones existentes y sincronizar en vez de duplicar.

### Validado
- Se levantó y configuró el stack local con WordPress, WooCommerce y el plugin activo.
- Se probaron importaciones reales por AJAX contra varios `product id` que previamente fallaban.
- Se verificó que el flujo masivo ya no cae con `Internal Server Error` cuando Dropi rate-limita o devuelve respuesta inválida.
