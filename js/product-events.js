variations = null;
attributes = null;
shopifyPrpoducts = null;
chose_variations = [];
JPIODFW_bulkModal = null;
jQuery(document).ready(function ($) {

    jQuery('#products-select').select2({
        dropdownParent: jQuery('#edit-product-modal')
    })
    jQuery('select[name=chose-variations]').each(function () {
        //this wrapped in jQuery will give us the current .letter-q div
        jQuery(this).html('');
    });

    JPIODFW_getProducts();
    if (jQuery(".table-view-list").length) {

        if (jQuery("#search_id-search-input").length) {
            var search_string = jQuery("#search_id-search-input").val();

            if (search_string != "") {
                console.log(search_string);
                jQuery(".pagination-links a").each(function () {
                    this.href = this.href + "&s=" + search_string;
                });
            }
        }
    }

    let $sobnombre = jQuery('#sob-nombre');
    let $sobdescripcion = jQuery('#sob-descripcion');
    let $sobprecio = jQuery('#sob-precio');
    let $sobimages = jQuery('#sob-images');
    let $productsselect = jQuery('#products-select');

    let $selectAll = jQuery('#selectAll');
    let $productaction = jQuery('input[type=radio][name=product-action]');
    $sobnombre.change(function () {
        if (this.checked) {
            jQuery('#row-nombre').show();
        } else {
            jQuery('#row-nombre').hide();
        }
    });
    $sobdescripcion.change(function () {
        if (this.checked) {
            jQuery('#row-descripcion').show();
        } else {
            jQuery('#row-descripcion').hide();
        }
    });
    $sobprecio.change(function () {
        if (this.checked) {
            jQuery('#row-precio').show();
        } else {
            jQuery('#row-precio').hide();
        }
    });
    $sobimages.change(function () {
        JPIODFW_toggleOverwriteVariationImagesOption();
    });

    $sobprecio.change(function () {
        if (this.checked) {
            jQuery('#row-precio').show();
        } else {
            jQuery('#row-precio').hide();
        }
    });
    $productaction.change(function () {
        console.log(this.value);
        if (this.value == 'SYNC') {
            jQuery('#row-products-select').show();
            jQuery('#row-clean-variations').show();
            $("#sob-nombre").prop('checked', false);
            $("#sob-precio").prop('checked', false);
            $("#sob-images").prop('checked', false);
            $("#sob-stock").prop('checked', false);
            $("#sob-descripcion").prop('checked', false);
            $("#row-nombre").hide();
            $("#row-descripcion").hide();
            $("#row-precio").hide();
            JPIODFW_toggleOverwriteVariationImagesOption();
            
            jQuery('select[name=chose-variations]').each(function () {
                //this wrapped in jQuery will give us the current .letter-q div
                jQuery(this).show();

            });
        } else {
            jQuery('#row-products-select').hide();
            jQuery('#row-clean-variations').hide();
            jQuery('#row-overwrite-variation-images').hide();
            jQuery('#clean-variations').prop('checked', false);
            jQuery('#overwrite-variation-images').prop('checked', false);
            jQuery('select[name=chose-variations]').each(function () {
                //this wrapped in jQuery will give us the current .letter-q div
                jQuery(this).hide();
            });
        }
    });

    jQuery("#bulk-sob-images").change(function () {
        if (!this.checked) {
            jQuery("#bulk-overwrite-variation-images").prop('checked', false);
        }
    });

    $selectAll.change(function () {
        if (this.checked) {
            jQuery('input[type=checkbox][name=variations]').prop('checked', true);
        } else {
            jQuery('input[type=checkbox][name=variations]').prop('checked', false);
        }
    });
    $productsselect.change(function () {
        productSelected = this.value;
        let indexFound = shopifyPrpoducts.findIndex(e => e.id == productSelected);

        let options = '<option value="crear">Crear nueva</option>';
        if (indexFound != undefined) {

            if (shopifyPrpoducts[indexFound].variations != undefined) {
                console.log('shopifyPrpoducts[indexFound]', shopifyPrpoducts[indexFound]);
                for (const variation of shopifyPrpoducts[indexFound].variations) {

                    console.log('variation.attributes', variation.attributes);

                    let variationtitle = '';
                    Object.entries(variation.attributes).forEach(element => {

                        variationtitle += element[1] + ' ';
                    });

                    options += '<option value="' + variation.variation_id + '">' + variationtitle + '</option>';
                }

                jQuery('select[name=chose-variations]').each(function () {
                    //this wrapped in jQuery will give us the current .letter-q div
                    jQuery(this).html('');
                });

                jQuery('select[name=chose-variations]').each(function () {
                    //this wrapped in jQuery will give us the current .letter-q div
                    jQuery(this).append(options);
                });

                jQuery('select[name=chose-variations]').each(function () {
                    //this wrapped in jQuery will give us the current .letter-q div
                    jQuery(this).show();
                });

            } else {
                jQuery('select[name=chose-variations]').each(function () {
                    //this wrapped in jQuery will give us the current .letter-q div
                    jQuery(this).hide();
                });


            }


        }
    });

    $(".img-dropi-import").on("click", async function (e) {
        e.preventDefault();
        let img_url = jQuery(this).data('src');
        console.log(img_url);
        Swal.fire({
            showConfirmButton: false,
            showCloseButton: true,
            imageUrl: img_url,
            // imageHeight: 1500,
            imageAlt: ''
        })
    });


    $(".btn-dropi-import").on("click", async function (e) {
        e.preventDefault();
        let product_name = jQuery(this).data('name');
        let product_id = jQuery(this).data('id');
        let product_description = jQuery(this).data('description');
        let product_price = jQuery(this).data('price');
        let url = jQuery(this).attr('href');
        let item = jQuery(this).data('item');
        let store =jQuery(this).data('store');
        tr = jQuery(this).closest("tr");

        $("#variant-select").html('');
        $("#products-select").val('');
        jQuery('#row-products-select').hide();
        jQuery('#row-clean-variations').hide();
        jQuery('#row-overwrite-variation-images').hide();
        jQuery('#clean-variations').prop('checked', false);
        jQuery('#overwrite-variation-images').prop('checked', false);
        jQuery("#new-product").prop("checked", true);


        jQuery("#product-name").val(product_name);
        jQuery("#product-description").val(product_description);
        jQuery("#product-id").val(product_id);
        jQuery("#product-price").val(product_price);
        jQuery("#product-url").val(url);
        jQuery('#store').val(store);


        attributes = item.attributes;

        variations = item.variations;
        let options = ' ';
        console.log(item.type);
        if (item.type == 'VARIABLE' && item.variations != undefined && item.variations.length > 0) {


            $("#row-variations").show();

            item.variations.forEach(variation => {
                options += '<div class="row"><div class="col">' +
                    '<input id="variation" name="variations" checked type="checkbox" value="' + variation.id + '" class="focus:ring-indigo-500' +
                    'h-4 w-4 text-indigo-600 border-gray-300 rounded">' +
                    '' +
                    '<label for="candidates" class="font-medium text-gray-700">';
                variation.attribute_values.forEach(attr => {
                    options += attr.value + '/';
                });
                options += '</label></div><div class="col">';

                options += '<select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" name="chose-variations" id="chose-variations-' + variation.id + '">';
                options += '<option></option></select>';
                options += '  </div></div>';
            });
            console.log('options', options);
            $("#variant-select").append(options);

            jQuery('select[name=chose-variations]').each(function () {
                //this wrapped in jQuery will give us the current .letter-q div
                jQuery(this).hide();
            });

        } else {
            $("#row-variations").hide();

        }



        //MOSTRARMODAL DE EDICION
        JPIODFW_myModal = new bootstrap.Modal(document.getElementById('edit-product-modal'), {
            backdrop: 'static',
            keyboard: false
        })

        JPIODFW_myModal.show();

        

    });

    $("#open-bulk-import-modal").on("click", function () {
        if (JPIODFW_bulkModal == null) {
            JPIODFW_bulkModal = new bootstrap.Modal(document.getElementById('bulk-import-modal'), {
                backdrop: 'static',
                keyboard: false
            });
        }

        $("#bulk-import-results").val('');
        JPIODFW_renderBulkErrorSummary([]);
        JPIODFW_bulkModal.show();
    });

    $("#bulk-import-submit").on("click", async function () {
        let ids = JPIODFW_parseBulkProductIds($("#bulk-product-ids").val());
        let store = $("#bulk-store").val();

        if (store == undefined || store === '') {
            JPIODFW_showAlert('Error', 'Selecciona una tienda para importar el listado.', 'error', 'OK');
            return;
        }

        if (ids.length === 0) {
            JPIODFW_showAlert('Error', 'Ingresa al menos un product id valido.', 'error', 'OK');
            return;
        }

        $("#bulk-import-submit").prop('disabled', true);
        $("#bulk-import-results").val('Iniciando importacion de ' + ids.length + ' productos...\n');

        let options = {
            sob_nombre: $("#bulk-sob-nombre").is(':checked'),
            sob_descripcion: $("#bulk-sob-descripcion").is(':checked'),
            sob_precio: $("#bulk-sob-precio").is(':checked'),
            sob_images: $("#bulk-sob-images").is(':checked'),
            sob_stock: $("#bulk-sob-stock").is(':checked'),
            clean_existing_variations: $("#bulk-clean-variations").is(':checked'),
            overwrite_variation_images: $("#bulk-overwrite-variation-images").is(':checked')
        };

        if (!(await JPIODFW_confirmVariationImageOverwrite(options.overwrite_variation_images))) {
            $("#bulk-import-submit").prop('disabled', false);
            return;
        }

        let successCount = 0;
        let errorCount = 0;
        let failedProductIds = [];

        for (let i = 0; i < ids.length; i++) {
            let productId = ids[i];
            JPIODFW_appendBulkLog('[' + (i + 1) + '/' + ids.length + '] Importando product id ' + productId + '...');

            try {
                let response = await JPIODFW_importByProductId(productId, store, options);
                if (response.success === true) {
                    successCount++;
                    let mode = response.mode === 'updated' ? 'actualizado' : 'creado';
                    JPIODFW_appendBulkLog('OK ' + productId + ' (' + mode + ')');
                } else {
                    errorCount++;
                    failedProductIds.push(productId);
                    JPIODFW_appendBulkLog('ERROR ' + productId + ': ' + (response.message || 'Error desconocido'));
                }
            } catch (error) {
                errorCount++;
                failedProductIds.push(productId);
                JPIODFW_appendBulkLog('ERROR ' + productId + ': ' + error);
            }

            await new Promise(function (resolve) {
                setTimeout(resolve, 900);
            });
        }

        JPIODFW_appendBulkLog('Finalizado. Exitosos: ' + successCount + '. Errores: ' + errorCount + '.');
        JPIODFW_renderBulkErrorSummary(failedProductIds);
        $("#bulk-import-submit").prop('disabled', false);
    });
});
JPIODFW_myModal = null;

tr = null;


/**
 * manda a traer los productos de dropi
 */
function JPIODFW_getProducts() {

    let data = {};
    const url = ajax_var.url;
    jQuery.ajax({
        type: "GET",
        dataType: "json",
        url: url,
        data: "action=" + ajax_var.action + "&nonce=" + ajax_var.nonce,
        cache: false,
        method: 'GET',

        success: function (response) {
            shopifyPrpoducts = response;

            let opciones = '';

            opciones += "<option value=''>Selecciona un producto</option>"
            response.forEach(element => {
                opciones += "<option value='" + element.id + "'>" + element.name + " - " + element.id + " - " + element.sku + "</option>"
            });

            jQuery("#products-select").append(opciones);


            jQuery('#products-select').select2();

            jQuery('#products-select').select2({
                width: '100%',

                dropdownParent: jQuery('#edit-product-modal')
            });



        },
        error: function (error) {
            console.log(error);
            Swal.close();
            JPIODFW_showAlert('Error', error.statusText, 'error', 'Error al obtener productos');

        }
    });


}



/**funcion que procesa el fomrulario modal */
async function JPIODFW_proces_form() {
    chose_variations = [];
    let product_name = jQuery("#product-name").val();
    let product_description = jQuery("#product-description").val();
    let product_id = jQuery("#product-id").val();
    let product_price = jQuery("#product-price").val();
    //la url que genera el backend con el nonce
    let product_url = jQuery("#product-url").val();
    let sob_nombre = jQuery("#sob-nombre").is(':checked');
    let sob_descripcion = jQuery("#sob-descripcion").is(':checked');
    let sob_precio = jQuery("#sob-precio").is(':checked');
    let sob_images = jQuery("#sob-images").is(':checked');
    let sob_stock = jQuery("#sob-stock").is(':checked');
    let clean_existing_variations = jQuery("#clean-variations").is(':checked');
    let overwrite_variation_images = jQuery("#overwrite-variation-images").is(':checked');
    let store =  jQuery("#store").val();

    if (!(await JPIODFW_confirmVariationImageOverwrite(overwrite_variation_images))) {
        return;
    }

    let variationstoimport = jQuery.map(jQuery('input[type=checkbox][name=variations]:checked'), function (c) {
        return c.value;
    })

    jQuery('input[type=checkbox][name=variations]:checked').each(function () {
        //let item=  {}{'`${this.value}`':jQuery('#chose-variations-'+this.value).val()};
        if (jQuery('#chose-variations-' + this.value).val() != 'crear') {
            let item = {};

            item[this.value] = jQuery('#chose-variations-' + this.value).val();
            chose_variations.push(item);
        }

    })
    console.log('chose_variations', chose_variations);

    let productaction = jQuery('input[type=radio][name=product-action]:checked').val();

    let productselect = jQuery("#products-select").val();

    JPIODFW_import(product_url, product_name, product_description, product_price,
        sob_nombre, sob_descripcion, sob_precio, sob_images, variationstoimport, productaction, productselect, chose_variations, sob_stock, store, clean_existing_variations, overwrite_variation_images);
}

function JPIODFW_showAlert(title, text, incon, confirmButtonText) {
    Swal.fire({
        title: title,
        text: text,
        icon: incon,
        confirmButtonText: confirmButtonText,
        allowOutsideClick: false
    })
}

function JPIODFW_toggleOverwriteVariationImagesOption() {
    let isSync = jQuery('input[type=radio][name=product-action]:checked').val() === 'SYNC';
    let saveImages = jQuery("#sob-images").is(':checked');

    if (isSync && saveImages) {
        jQuery('#row-overwrite-variation-images').show();
        return;
    }

    jQuery('#row-overwrite-variation-images').hide();
    jQuery('#overwrite-variation-images').prop('checked', false);
}

async function JPIODFW_confirmVariationImageOverwrite(shouldOverwrite) {
    if (shouldOverwrite !== true) {
        return true;
    }

    let result = await Swal.fire({
        title: 'Sobrescribir imagenes de variaciones',
        text: 'Esto reemplazará las imagenes actuales de las variaciones existentes con las imagenes que devuelva Dropi. ¿Deseas continuar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, sobrescribir',
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false
    });

    return result.isConfirmed === true;
}

function JPIODFW_parseBulkProductIds(rawValue) {
    if (rawValue == undefined) {
        return [];
    }

    let ids = rawValue
        .split(/[\s,;]+/)
        .map(function (value) {
            return value.trim();
        })
        .filter(function (value) {
            return value !== '' && /^[0-9]+$/.test(value);
        })
        .map(function (value) {
            return parseInt(value, 10);
        });

    return [...new Set(ids)];
}

function JPIODFW_appendBulkLog(message) {
    let $results = jQuery("#bulk-import-results");
    let currentValue = $results.val();
    $results.val(currentValue + message + "\n");
    $results.scrollTop($results[0].scrollHeight);
}

function JPIODFW_renderBulkErrorSummary(failedProductIds) {
    let uniqueFailedIds = [...new Set(failedProductIds)];
    let $summary = jQuery("#bulk-import-errors-summary");
    let $list = jQuery("#bulk-import-errors-list");

    if (uniqueFailedIds.length === 0) {
        $list.text('');
        $summary.hide();
        return;
    }

    $list.text(uniqueFailedIds.join(', '));
    $summary.show();
}

function JPIODFW_importByProductId(productId, store, options) {
    return new Promise(function (resolve, reject) {
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: ajax_var.url,
            cache: false,
            method: 'POST',
            data: {
                action: ajax_var.bulk_import_action,
                _wpnonce: ajax_var.import_nonce,
                product: productId,
                store: store,
                sob_nombre: options.sob_nombre,
                sob_descripcion: options.sob_descripcion,
                sob_precio: options.sob_precio,
                sob_images: options.sob_images,
                sob_stock: options.sob_stock,
                clean_existing_variations: options.clean_existing_variations,
                overwrite_variation_images: options.overwrite_variation_images
            },
            success: function (response) {
                resolve(response);
            },
            error: function (error) {
                reject(error.statusText || 'Error');
            }
        });
    });
}


function JPIODFW_import(product_url, product_name, product_description, product_price, sob_nombre,
    sob_descripcion, sob_precio, sob_images, variationstoimport, productaction, productselect, chose_variations, sob_stock, store, clean_existing_variations, overwrite_variation_images) {

    let data = {};
    if (product_name != undefined) {
        data.product_name = product_name;
        data.product_description = product_description;
        data.product_price = product_price;
        data.sob_nombre = sob_nombre;
        data.sob_descripcion = sob_descripcion;
        data.sob_precio = sob_precio;
        data.sob_images = sob_images;
        data.sob_stock = sob_stock;
        data.variationstoimport = variationstoimport;
        data.productaction = productaction;
        data.productselect = productselect;
        data.variations = variations;
        data.attributes = attributes;
        data.store = store;
        data.clean_existing_variations = clean_existing_variations;
        data.overwrite_variation_images = overwrite_variation_images;

    }

    if (chose_variations != undefined) {
        data.chose_variations = chose_variations
    }


    JPIODFW_showAlert('Importing product', 'Please wait...', 'info', '');
    Swal.showLoading();


    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajax_var.url + product_url,
        cache: false,
        method: 'POST',
        data: data,

        success: function (response) {

            Swal.close();
            if (response.success == true) {
                let form = jQuery("edit-product-form");
                form.trigger("reset");
                //MOSTRARMODAL DE EDICION


                if (JPIODFW_myModal != null && JPIODFW_myModal != undefined) {
                    JPIODFW_myModal.hide();
                }

                // col7 = tr.find("td:eq(8)").html('<span class="dashicons dashicons-yes" style="color:#2bee2b"></span>'); // get 
                JPIODFW_showAlert('Felicidades!', 'El producto ha sido sincronizado con dropi exitosamente', 'success', 'Ok');

            } else {
                console.log(response);
                JPIODFW_showAlert('Error', response.message, 'error', 'OK');

            }


        },
        error: function (error) {
            console.log(error);
            Swal.close();
            JPIODFW_showAlert('Error', error.statusText, 'error', 'Error');

        }
    });
}
