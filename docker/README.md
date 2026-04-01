# Desarrollo local con Docker

## Servicios

- `wordpress`: WordPress + Apache en `http://localhost:8080`
- `db`: MariaDB en `localhost:3307`
- `adminer`: Adminer en `http://localhost:8081`
- `wpcli`: utilitario para instalar WordPress, instalar WooCommerce, activar el plugin y ejecutar comandos `wp`

## Arranque rapido

1. Copia variables de entorno:

   ```bash
   cp .env.example .env
   ```

2. Levanta los servicios:

   ```bash
   docker compose up -d
   ```

3. Instala WordPress local, WooCommerce y activa el plugin:

   ```bash
   ./docker/scripts/setup-local.sh
   ```

4. Accede a:

- Sitio: `http://localhost:8080`
- Admin: `http://localhost:8080/wp-admin`
- Adminer: `http://localhost:8081`

## Comandos utiles

Ejecutar WP-CLI:

```bash
./docker/scripts/wp.sh plugin list --allow-root
```

Usar Makefile:

```bash
make up
make setup
make wp ARGS='plugin list --allow-root'
make package
```

Activar el plugin manualmente:

```bash
./docker/scripts/wp.sh plugin activate wc-dropi-integration --allow-root
```

Activar WooCommerce manualmente:

```bash
./docker/scripts/wp.sh plugin activate woocommerce --allow-root
```

## Notas

- El plugin se monta desde este repo directamente en `wp-content/plugins/wc-dropi-integration`, por lo que los cambios locales se reflejan sin rebuild.
- El bootstrap local instala WooCommerce desde WordPress.org si no existe ya en la instancia.
- Este plugin depende de WooCommerce. Si la instalacion de WooCommerce falla, la activacion del plugin tambien fallara.
