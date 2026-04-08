jQuery(function ($) {
    function appendQueryParam(url, key, value) {
        const separator = url.indexOf('?') === -1 ? '?' : '&';
        const pattern = new RegExp('([?&])' + key + '=.*?(&|$)', 'i');

        if (pattern.test(url)) {
            return url.replace(pattern, '$1' + key + '=' + value + '$2');
        }

        return url + separator + key + '=' + value;
    }

    function buildResyncUrl(baseUrl, options) {
        let url = baseUrl;

        Object.keys(options).forEach(function (key) {
            url = appendQueryParam(url, key, options[key]);
        });

        return url;
    }

    function resyncModalHtml() {
        return ''
            + '<div style="text-align:left;">'
            + '<p style="margin-bottom:12px;">Selecciona qué información deseas re-sincronizar desde Dropi hacia este producto de WooCommerce.</p>'
            + '<label style="display:block;margin:8px 0;"><input type="checkbox" id="dropi-resync-stock" checked> Sincronizar stock y estado de inventario</label>'
            + '<label style="display:block;margin:8px 0;"><input type="checkbox" id="dropi-resync-name"> Sobrescribir nombre</label>'
            + '<label style="display:block;margin:8px 0;"><input type="checkbox" id="dropi-resync-description"> Sobrescribir descripción</label>'
            + '<label style="display:block;margin:8px 0;"><input type="checkbox" id="dropi-resync-price"> Sobrescribir precio</label>'
            + '<label style="display:block;margin:8px 0;"><input type="checkbox" id="dropi-resync-images"> Sincronizar imágenes</label>'
            + '<div id="dropi-resync-images-options" style="display:none;margin:8px 0 8px 24px;padding-left:12px;border-left:3px solid #dcdcde;">'
            + '<label style="display:block;margin:8px 0;"><input type="checkbox" id="dropi-resync-overwrite-variation-images"> Sobrescribir imágenes de variaciones existentes</label>'
            + '</div>'
            + '<label style="display:block;margin:8px 0;"><input type="checkbox" id="dropi-resync-clean-variations"> Limpiar y recrear variaciones existentes</label>'
            + '<p style="margin-top:12px;color:#50575e;">Usa esta última opción cuando un producto variable quedó mal estructurado y necesitas reconstruir sus variaciones desde Dropi.</p>'
            + '</div>';
    }

    $(document).on('click', '.dropi-resync-product-link', function (event) {
        event.preventDefault();

        let baseUrl = $(this).attr('href');
        if (!baseUrl) {
            return;
        }

        Swal.fire({
            title: 'Re-sincronizar producto Dropi',
            html: resyncModalHtml(),
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Re-sincronizar',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false,
            focusConfirm: false,
            didOpen: function () {
                $('#dropi-resync-images').on('change', function () {
                    if (this.checked) {
                        $('#dropi-resync-images-options').show();
                    } else {
                        $('#dropi-resync-images-options').hide();
                        $('#dropi-resync-overwrite-variation-images').prop('checked', false);
                    }
                });
            },
            preConfirm: function () {
                const options = {
                    sob_stock: $('#dropi-resync-stock').is(':checked') ? 'true' : 'false',
                    sob_nombre: $('#dropi-resync-name').is(':checked') ? 'true' : 'false',
                    sob_descripcion: $('#dropi-resync-description').is(':checked') ? 'true' : 'false',
                    sob_precio: $('#dropi-resync-price').is(':checked') ? 'true' : 'false',
                    sob_images: $('#dropi-resync-images').is(':checked') ? 'true' : 'false',
                    clean_existing_variations: $('#dropi-resync-clean-variations').is(':checked') ? 'true' : 'false',
                    overwrite_variation_images: $('#dropi-resync-overwrite-variation-images').is(':checked') ? 'true' : 'false'
                };

                const hasAnySelected = Object.keys(options).some(function (key) {
                    if (key === 'overwrite_variation_images') {
                        return false;
                    }

                    return options[key] === 'true';
                });

                if (!hasAnySelected) {
                    Swal.showValidationMessage('Selecciona al menos una opción para re-sincronizar.');
                    return false;
                }

                if (options.overwrite_variation_images === 'true' && options.sob_images !== 'true') {
                    Swal.showValidationMessage('Para sobrescribir imágenes de variaciones debes activar también la sincronización de imágenes.');
                    return false;
                }

                return options;
            }
        }).then(function (result) {
            if (result.isConfirmed && result.value) {
                window.location.href = buildResyncUrl(baseUrl, result.value);
            }
        });
    });
});
