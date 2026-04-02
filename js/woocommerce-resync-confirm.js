jQuery(function ($) {
    $(document).on('click', '.dropi-resync-product-link', function (event) {
        event.preventDefault();

        let baseUrl = $(this).attr('href');
        if (!baseUrl) {
            return;
        }

        Swal.fire({
            title: 'Re-sincronizar producto Dropi',
            text: 'Esta acción siempre validará el stock y actualizará su estado en WooCommerce. ¿Deseas además sobrescribir las imágenes de las variaciones existentes con las de Dropi?',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Sí, sobrescribir',
            denyButtonText: 'No, conservar variaciones',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.href = JPIODFW_appendQueryParam(baseUrl, 'overwrite_variation_images', 'true');
                return;
            }

            if (result.isDenied) {
                window.location.href = JPIODFW_appendQueryParam(baseUrl, 'overwrite_variation_images', 'false');
            }
        });
    });
});

function JPIODFW_appendQueryParam(url, key, value) {
    let separator = url.indexOf('?') === -1 ? '?' : '&';
    let pattern = new RegExp('([?&])' + key + '=.*?(&|$)', 'i');

    if (pattern.test(url)) {
        return url.replace(pattern, '$1' + key + '=' + value + '$2');
    }

    return url + separator + key + '=' + value;
}
