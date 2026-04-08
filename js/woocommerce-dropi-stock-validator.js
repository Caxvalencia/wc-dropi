jQuery(function ($) {
    const settings = window.JPIODFW_dropi_stock_validator || null;

    if (!settings) {
        return;
    }

    const requestDelay = parseInt(settings.request_delay, 10) || 350;

    function delay(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function escapeHtml(value) {
        return $('<div />').text(value == null ? '' : String(value)).html();
    }

    function ajaxRequest(action, data) {
        return $.ajax({
            url: settings.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: Object.assign(
                {
                    action: action,
                    nonce: settings.nonce
                },
                data || {}
            )
        });
    }

    function getCandidateProductIds() {
        const selected = [];

        $('#the-list tbody th.check-column input[name="post[]"]:checked').each(function () {
            const value = parseInt($(this).val(), 10);
            if (value > 0) {
                selected.push(value);
            }
        });

        if (selected.length > 0) {
            return {
                ids: Array.from(new Set(selected)),
                scope: 'selected'
            };
        }

        const visible = [];
        $('#the-list tr[id^="post-"]').each(function () {
            const checkbox = $(this).find('th.check-column input[name="post[]"]');
            const value = parseInt(checkbox.val(), 10);
            if (value > 0) {
                visible.push(value);
            }
        });

        return {
            ids: Array.from(new Set(visible)),
            scope: 'page'
        };
    }

    function buildRowsTable(rows, errors) {
        const errorHtml = errors.length > 0
            ? '<div style="margin-bottom:12px;padding:10px 12px;background:#fff4e5;border:1px solid #f0c36d;border-radius:4px;max-height:140px;overflow:auto;text-align:left;">'
                + '<strong>Incidencias durante la validación</strong><ul style="margin:8px 0 0 18px;">'
                + errors.map(function (error) {
                    return '<li>' + escapeHtml(error) + '</li>';
                }).join('')
                + '</ul></div>'
            : '';

        const tableRows = rows.map(function (row, index) {
            const scopeLabel = row.scope === 'variation' ? 'Variación' : 'Producto';
            const statusLabel = row.status === 'faltante_en_woo' ? 'WooCommerce por debajo' : 'WooCommerce por encima';
            const checkboxId = 'dropi-stock-row-' + index;

            return ''
                + '<tr>'
                + '<td style="text-align:center;"><input type="checkbox" class="dropi-sync-stock-checkbox" id="' + checkboxId + '" data-product-id="' + escapeHtml(row.sync_product_id) + '" checked></td>'
                + '<td><label for="' + checkboxId + '">' + escapeHtml(row.item_name) + '</label></td>'
                + '<td>' + escapeHtml(scopeLabel) + '</td>'
                + '<td>' + escapeHtml(row.sku || '—') + '</td>'
                + '<td>' + escapeHtml(row.dropi_product_id) + '</td>'
                + '<td>' + escapeHtml(row.woo_stock) + '</td>'
                + '<td>' + escapeHtml(row.dropi_stock) + '</td>'
                + '<td>' + escapeHtml(statusLabel) + '</td>'
                + '</tr>';
        }).join('');

        return ''
            + errorHtml
            + '<p style="margin:0 0 12px 0;text-align:left;">Seleccionar una fila sincroniza el stock del producto WooCommerce asociado. En productos variables, una selección actualiza el stock del producto y sus variaciones.</p>'
            + '<div style="margin-bottom:8px;text-align:left;"><label><input type="checkbox" id="dropi-sync-stock-select-all" checked> Seleccionar todos</label></div>'
            + '<div style="max-height:420px;overflow:auto;border:1px solid #dcdcde;">'
            + '<table class="widefat striped" style="border:0;margin:0;">'
            + '<thead><tr>'
            + '<th style="width:40px;">Sync</th>'
            + '<th>Producto / Variación</th>'
            + '<th>Tipo</th>'
            + '<th>SKU</th>'
            + '<th>Dropi ID</th>'
            + '<th>Stock Woo</th>'
            + '<th>Stock Dropi</th>'
            + '<th>Estado</th>'
            + '</tr></thead>'
            + '<tbody>' + tableRows + '</tbody>'
            + '</table></div>';
    }

    async function validateProducts(productIds) {
        const rows = [];
        const errors = [];

        Swal.fire({
            title: 'Validando stock con Dropi',
            html: '<p id="dropi-stock-progress">Preparando validación...</p>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        for (let index = 0; index < productIds.length; index += 1) {
            const productId = productIds[index];
            const progressText = 'Validando producto ' + (index + 1) + ' de ' + productIds.length + '...';
            $('#dropi-stock-progress').text(progressText);

            try {
                const response = await ajaxRequest(settings.validate_action, {
                    product_id: productId
                });

                if (response && response.success) {
                    if (Array.isArray(response.rows) && response.rows.length > 0) {
                        response.rows.forEach(function (row) {
                            rows.push(row);
                        });
                    }
                } else if (response && response.message) {
                    errors.push('Producto ' + productId + ': ' + response.message);
                }
            } catch (error) {
                errors.push('Producto ' + productId + ': ' + (error && error.statusText ? error.statusText : 'Error de conexión'));
            }

            if (index < productIds.length - 1) {
                await delay(requestDelay);
            }
        }

        return {
            rows: rows,
            errors: errors
        };
    }

    async function syncProducts(productIds) {
        const uniqueProductIds = Array.from(new Set(productIds.map(function (id) {
            return parseInt(id, 10);
        }).filter(function (id) {
            return id > 0;
        })));

        const synced = [];
        const errors = [];

        Swal.fire({
            title: 'Sincronizando stock',
            html: '<p id="dropi-stock-sync-progress">Preparando sincronización...</p>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        for (let index = 0; index < uniqueProductIds.length; index += 1) {
            const productId = uniqueProductIds[index];
            $('#dropi-stock-sync-progress').text('Sincronizando producto ' + (index + 1) + ' de ' + uniqueProductIds.length + '...');

            try {
                const response = await ajaxRequest(settings.sync_action, {
                    product_id: productId
                });

                if (response && response.success) {
                    synced.push(productId);
                } else {
                    errors.push('Producto ' + productId + ': ' + (response && response.message ? response.message : 'No fue posible sincronizar.'));
                }
            } catch (error) {
                errors.push('Producto ' + productId + ': ' + (error && error.statusText ? error.statusText : 'Error de conexión'));
            }

            if (index < uniqueProductIds.length - 1) {
                await delay(requestDelay);
            }
        }

        return {
            synced: synced,
            errors: errors
        };
    }

    $(document).on('click', '#dropi-validate-stock-button', async function (event) {
        event.preventDefault();

        const candidateData = getCandidateProductIds();
        if (!candidateData.ids.length) {
            Swal.fire({
                title: 'Sin productos para validar',
                text: 'No se encontraron productos en la vista actual.',
                icon: 'info'
            });
            return;
        }

        const validationResult = await validateProducts(candidateData.ids);

        if (validationResult.rows.length === 0 && validationResult.errors.length === 0) {
            Swal.fire({
                title: 'Stock sincronizado',
                text: 'No se encontraron diferencias de stock con Dropi en los productos revisados.',
                icon: 'success'
            });
            return;
        }

        const reviewResult = await Swal.fire({
            title: 'Diferencias de stock detectadas',
            html: buildRowsTable(validationResult.rows, validationResult.errors),
            width: 1200,
            showCancelButton: true,
            confirmButtonText: 'Sincronizar seleccionados',
            cancelButtonText: 'Cerrar',
            focusConfirm: false,
            didOpen: function () {
                const selectAll = document.getElementById('dropi-sync-stock-select-all');
                if (!selectAll) {
                    return;
                }

                selectAll.addEventListener('change', function () {
                    $('.dropi-sync-stock-checkbox').prop('checked', !!selectAll.checked);
                });
            },
            preConfirm: function () {
                const selectedIds = [];
                $('.dropi-sync-stock-checkbox:checked').each(function () {
                    selectedIds.push($(this).data('product-id'));
                });

                if (!selectedIds.length) {
                    Swal.showValidationMessage('Selecciona al menos una fila para sincronizar.');
                    return false;
                }

                return selectedIds;
            }
        });

        if (!reviewResult.isConfirmed || !Array.isArray(reviewResult.value) || !reviewResult.value.length) {
            return;
        }

        const syncResult = await syncProducts(reviewResult.value);
        const summaryHtml = ''
            + '<p><strong>Sincronizados:</strong> ' + escapeHtml(syncResult.synced.length) + '</p>'
            + '<p><strong>Errores:</strong> ' + escapeHtml(syncResult.errors.length) + '</p>'
            + (syncResult.errors.length
                ? '<div style="margin-top:12px;padding:10px 12px;background:#fff4e5;border:1px solid #f0c36d;border-radius:4px;max-height:180px;overflow:auto;text-align:left;"><ul style="margin:0 0 0 18px;">'
                    + syncResult.errors.map(function (error) {
                        return '<li>' + escapeHtml(error) + '</li>';
                    }).join('')
                    + '</ul></div>'
                : '');

        await Swal.fire({
            title: syncResult.errors.length ? 'Sincronización completada con incidencias' : 'Stock sincronizado',
            html: summaryHtml,
            icon: syncResult.errors.length ? 'warning' : 'success',
            confirmButtonText: 'Recargar listado'
        });

        window.location.reload();
    });
});
