PLUGIN_SLUG := wc-dropi-integration
PLUGIN_FILE := wc-dropi-integration.php
VERSION := $(shell sed -nE "s/^ \* Version: (.*)/\1/p" $(PLUGIN_FILE) | head -n1)
DIST_DIR := dist
BUILD_DIR := $(DIST_DIR)/$(PLUGIN_SLUG)
ZIP_FILE := $(DIST_DIR)/$(PLUGIN_SLUG)-$(VERSION).zip

.PHONY: help up down restart setup logs wp clean package validate

help:
	@echo "Tareas disponibles:"
	@echo "  make up        Levanta el stack Docker"
	@echo "  make down      Baja el stack Docker"
	@echo "  make restart   Reinicia el stack Docker"
	@echo "  make setup     Instala WordPress local, WooCommerce y activa el plugin"
	@echo "  make logs      Muestra logs de WordPress"
	@echo "  make wp        Ejecuta WP-CLI, ejemplo: make wp ARGS='plugin list --allow-root'"
	@echo "  make package   Genera el .zip final del plugin en $(DIST_DIR)/"
	@echo "  make clean     Elimina artefactos de empaquetado"

up:
	docker compose up -d

down:
	docker compose down

restart: down up

setup:
	./docker/scripts/setup-local.sh

logs:
	docker compose logs -f wordpress

wp:
	./docker/scripts/wp.sh $(ARGS)

validate:
	@test -f "$(PLUGIN_FILE)" || (echo "No se encontro el archivo principal del plugin."; exit 1)

clean:
	@rm -rf "$(BUILD_DIR)" "$(ZIP_FILE)"

package: validate clean
	@mkdir -p "$(DIST_DIR)"
	@rsync -a ./ "$(BUILD_DIR)/" \
		--exclude '.git/' \
		--exclude 'dist/' \
		--exclude 'docker/' \
		--exclude '.env' \
		--exclude '.env.*' \
		--exclude '.gitignore' \
		--exclude '.DS_Store' \
		--exclude 'Makefile' \
		--exclude 'docker-compose.yml'
	@cd "$(DIST_DIR)" && zip -rq "$(notdir $(ZIP_FILE))" "$(PLUGIN_SLUG)"
	@echo "ZIP generado: $(ZIP_FILE)"
