# Dropi CO API: llamados `curl`

Este documento resume los llamados al API de Dropi para Colombia (`CO`) que actualmente usa este plugin.

## Base URL CO

```bash
https://api.dropi.co/integrations/
```

## Autenticación

El plugin no hace login contra un endpoint de autenticación. La integración envía el token directamente en el header:

```http
dropi-integration-key: TU_TOKEN_DROPI
```

Header completo usado por el plugin:

```http
Content-Type: application/json;charset=UTF-8
dropi-integration-key: TU_TOKEN_DROPI
```

## Variables sugeridas

```bash
export DROPI_CO_BASE_URL="https://api.dropi.co/integrations"
export DROPI_TOKEN="TU_TOKEN_DROPI"
```

## 1. Obtener un producto por ID

Endpoint:

```bash
GET /products/v2/{id}
```

Ejemplo:

```bash
curl --request GET "$DROPI_CO_BASE_URL/products/v2/12345" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN"
```

## 2. Listar productos

Endpoint:

```bash
POST /products/index
```

Notas:

- Para `CO`, el plugin envía `get_stock: false`.
- También envía `integration: true`.

Payload base:

```json
{
  "startData": 1,
  "pageSize": 20,
  "order_type": "DESC",
  "order_by": "id",
  "keywords": "",
  "active": true,
  "no_count": true,
  "integration": true,
  "get_stock": false
}
```

Ejemplo:

```bash
curl --request POST "$DROPI_CO_BASE_URL/products/index" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN" \
  --data '{
    "startData": 1,
    "pageSize": 20,
    "order_type": "DESC",
    "order_by": "id",
    "keywords": "",
    "active": true,
    "no_count": true,
    "integration": true,
    "get_stock": false
  }'
```

Ejemplo con filtros:

```bash
curl --request POST "$DROPI_CO_BASE_URL/products/index" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN" \
  --data '{
    "startData": 1,
    "pageSize": 50,
    "order_type": "ASC",
    "order_by": "name",
    "keywords": "reloj",
    "active": true,
    "no_count": true,
    "integration": true,
    "get_stock": false,
    "userVerified": true,
    "stockmayor": 1,
    "notNulldescription": true,
    "category": 10,
    "warehouse_id": 3
  }'
```

## 3. Obtener bodegas

Endpoint:

```bash
GET /warehouses/
```

Ejemplo:

```bash
curl --request GET "$DROPI_CO_BASE_URL/warehouses/" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN"
```

## 4. Obtener categorías

Endpoint:

```bash
GET /categories/
```

Ejemplo:

```bash
curl --request GET "$DROPI_CO_BASE_URL/categories/" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN"
```

## 5. Marcar producto como importado en la tienda

Endpoint:

```bash
PUT /importlist/importstore/1
```

Payload usado por el plugin:

```json
{
  "products_id": 12345,
  "imported_to_store": true,
  "woocomerse_id": 987,
  "woocomerse_url": "slug-del-producto"
}
```

Ejemplo:

```bash
curl --request PUT "$DROPI_CO_BASE_URL/importlist/importstore/1" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN" \
  --data '{
    "products_id": 12345,
    "imported_to_store": true,
    "woocomerse_id": 987,
    "woocomerse_url": "slug-del-producto"
  }'
```

## 6. Crear orden en Dropi

Endpoint:

```bash
POST /orders/myorders
```

Notas:

- El plugin usa `status: "PENDIENTE CONFIRMACION"`.
- Si el método de pago es contraentrega (`cod`), usa `rate_type: "CON RECAUDO"`.
- En otros casos usa `rate_type: "SIN RECAUDO"`.
- `type` se envía como `FINAL_ORDER`.
- `payment_method_id` se envía fijo en `1`.

Payload representativo:

```json
{
  "total_order": 159900,
  "notes": "Pedido desde WooCommerce",
  "name": "Juan",
  "surname": "Perez",
  "dir": "Calle 123 #45-67 Apto 201",
  "country": "CO",
  "state": "ANT",
  "city": "Medellin",
  "phone": "3001234567",
  "client_email": "juan@example.com",
  "payment_method_id": 1,
  "status": "PENDIENTE CONFIRMACION",
  "type": "FINAL_ORDER",
  "rate_type": "SIN RECAUDO",
  "products": [
    {
      "id": 12345,
      "user_id": 678,
      "name": "Producto de prueba",
      "quantity": 2,
      "stock": 10,
      "price": 79950
    }
  ],
  "calculate_costs_and_shiping": true,
  "supplier_id": 678,
  "shop_order_id": 1001,
  "create_product_if_not_exist": false
}
```

Ejemplo:

```bash
curl --request POST "$DROPI_CO_BASE_URL/orders/myorders" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN" \
  --data '{
    "total_order": 159900,
    "notes": "Pedido desde WooCommerce",
    "name": "Juan",
    "surname": "Perez",
    "dir": "Calle 123 #45-67 Apto 201",
    "country": "CO",
    "state": "ANT",
    "city": "Medellin",
    "phone": "3001234567",
    "client_email": "juan@example.com",
    "payment_method_id": 1,
    "status": "PENDIENTE CONFIRMACION",
    "type": "FINAL_ORDER",
    "rate_type": "SIN RECAUDO",
    "products": [
      {
        "id": 12345,
        "user_id": 678,
        "name": "Producto de prueba",
        "quantity": 2,
        "stock": 10,
        "price": 79950
      }
    ],
    "calculate_costs_and_shiping": true,
    "supplier_id": 678,
    "shop_order_id": 1001,
    "create_product_if_not_exist": false
  }'
```

### Variación de producto

Cuando el producto es variable, el plugin agrega `variation_id` dentro del item de `products`.

Ejemplo:

```json
{
  "id": 12345,
  "user_id": 678,
  "name": "Camiseta Azul Talla M",
  "quantity": 1,
  "stock": 5,
  "price": 69900,
  "variation_id": 555
}
```

## 7. Llamado legacy detectado en el código

También aparece este endpoint en una rutina de actualización de stock:

```bash
GET /products/{id}
```

Ejemplo:

```bash
curl --request GET "$DROPI_CO_BASE_URL/products/12345" \
  --header "Content-Type: application/json;charset=UTF-8" \
  --header "dropi-integration-key: $DROPI_TOKEN"
```

Nota:

- En el código esa rutina usa `wp_remote_post(...)` con `method => GET`, por lo que parece una implementación legacy o inconsistente.
- Los flujos principales del plugin usan `GET /products/v2/{id}`.

## Respuesta esperada

Los endpoints se consumen esperando una estructura similar a esta:

```json
{
  "isSuccess": true,
  "message": "OK",
  "objects": {},
  "status": 200
}
```

Cuando el endpoint devuelve listas, `objects` suele contener un arreglo. Cuando devuelve un detalle, `objects` suele ser un objeto.

## Resumen rápido de endpoints CO

```bash
GET  https://api.dropi.co/integrations/products/v2/{id}
POST https://api.dropi.co/integrations/products/index
GET  https://api.dropi.co/integrations/warehouses/
GET  https://api.dropi.co/integrations/categories/
PUT  https://api.dropi.co/integrations/importlist/importstore/1
POST https://api.dropi.co/integrations/orders/myorders
GET  https://api.dropi.co/integrations/products/{id}   # legacy en el código
```
