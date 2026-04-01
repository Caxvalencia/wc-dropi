<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
include_once(dirname(__DIR__) . '/models/ProductsModel.php');
include_once(dirname(__DIR__) . '/tables/Product_List.php');
include_once(dirname(__DIR__) . '/models/TokenModel.php');


class JPIODFW_ProductsView
{
    public $customers_obj;
    private static $instance;
    private $tokenModel;
    /*......*/

    static function GetInstance()
    {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    public function __construct()
    {
        $this->tokenModel = JPIODFW_TokenModel::GetInstance();
    }
    public function getProducts($product_list)
    {
        $tokens = $this->tokenModel->getTokens();


?>

        <div class="wrap">

            <?php
            if (isset($_GET['msg']) && !empty($_GET['msg'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($_GET["msg"]) . '</p> </div>';
            }
            if (isset($_GET['error']) && !empty($_GET['error'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($_GET["error"]) . '</p> </div>';
            }
            ?>



            <h1 class="wp-heading-inline">
                Productos</h1>
            <button type="button" id="open-bulk-import-modal" class="page-title-action">Importar por IDs</button>

            <hr class="wp-header-end">
            <?php $product_list->views(); ?>

            <div id="post-body" style="margin-right: 0;" class="metabox-holder columns-2">
                <div id="post-body-content">
                    <div class="meta-box-sortables ui-sortable">
                        <form method="post">
                            <?php

                            $product_list->prepare_items();
                            $product_list->search_box('search', 'search_id');
                            $product_list->display(); ?>
                        </form>
                    </div>
                </div>
            </div>
            <br class="clear">

        </div>


        <div class="bootstrap-wrapper">

            <div class="modal fade" id="edit-product-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Modificar producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="edit-product-form">
                                <input type="hidden" id="product-url">
                                <input type="hidden" id="store">
                                <div class="row">
                                    <div class="col-md-12 row flex items-start">
                                        <div class="col-md-6">
                                            <div class="flex items-center h-5">
                                                <input id="new-product" checked name="product-action" type="radio" value="NEW" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="sob-nombre" class="font-medium text-gray-700"><strong>Crear nuevo producto</strong></label>
                                                <p class="text-gray-500">Se creará un nuevo producto en tu tienda shopify</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="flex items-center h-5">
                                                <input id="vinc-product" name="product-action" type="radio" value="SYNC" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="sob-nombre" class="font-medium text-gray-700"><strong>Vincular a producto existente</strong> </label>
                                                <p class="text-gray-500">Se vinculará al producto de tu tienda shopify que selecciones</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- selecciono el producto al que deseo vincular-->


                                    <div class="col-md-12  mb-3" id="row-products-select" style="display:none;">
                                        <label for="products-select" class="font-medium text-gray-700"><strong>Producto a vincular</strong> </label>
                                        <p class="text-gray-500">El producto seleccionado se vinculará con el producto dropi</p>

                                        <select id="products-select" name="products-select" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">

                                        </select>
                                    </div>

                                    <div class="col-md-12 flex items-start" id="row-clean-variations" style="display:none;">
                                        <div class="flex items-center h-5">
                                            <input id="clean-variations" name="clean-variations" value="1" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="clean-variations" class="font-medium text-gray-700"><strong>Limpiar variaciones existentes</strong></label>
                                            <p class="text-gray-500">Si se activa al vincular un producto variable, se eliminarán primero las variaciones existentes del producto WooCommerce antes de recrearlas.</p>
                                        </div>
                                    </div>


                                    <div class="col-md-12  mb-3" id="row-variations">
                                        <label for="products-select" class="font-medium text-gray-700"><strong>Variaciones</strong> </label>
                                        <p class="text-gray-500">Selecciona las variaciones que quieres importar, si el sku ya existe, se sincronizará con la variación existente</p>

                                        <label>Selecccionar todas</label><input id="selectAll" type="checkbox" checked>
                                        <div id="variant-select" name="products-select" class="mt-4 space-y-4">

                                        </div>
                                    </div>



                                    <div class="col-md-12 flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="sob-nombre" checked name="sob-nombre" type="checkbox" value="1" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">

                                            <label for="sob-nombre" class="font-medium text-gray-700"><strong>Guardar</strong> nombre dropi</label>
                                            <p class="text-gray-500">Si desmarca esta opción, el nombre del producto no se guardará</p>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3" id="row-nombre">
                                        <label for="product-name" class="col-form-label">Nombre:</label>
                                        <input type="text" class="form-control" id="product-name">
                                    </div>
                                    <div class="col-md-12 flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="sob-descripcion" checked name="sob-descripcion" value="1" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">

                                            <label for="sob-descripcion" class="font-medium text-gray-700"><strong>Guardar</strong> descripción dropi</label>
                                            <p class="text-gray-500">Si desmarca esta opción, la descripción del producto no se guardará</p>
                                        </div>
                                    </div>

                                    <div class="mb-3 col-md-12" id="row-descripcion">
                                        <label for="product-description" class="col-form-label">Descripcion:</label>
                                        <textarea class="form-control" id="product-description"></textarea>
                                    </div>
                                    <div class="col-md-12 flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="sob-precio" checked name="sob-precio" value="1" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">

                                            <label for="sob-precio" class="font-medium text-gray-700"><strong>Guardar</strong> precio dropi</label>
                                            <p class="text-gray-500">Si desmarca esta opción, el precio del producto no se guardará. <strong>Si el SKU ya existe, el precio no se sobreescribirá.</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3" id="row-precio">
                                        <label for="product-price" class="col-form-label">Precio:</label>
                                        <input type="text" class="form-control" id="product-price">
                                    </div>
                                    <div class="col-md-12 flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="sob-images" checked name="sob-images" value="1" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="sob-images" class="font-medium text-gray-700"><strong>Guardar</strong> imagenes</label>
                                            <p class="text-gray-500">Si desmarca esta opción, las imagenes del producto no se guardarán. </p>
                                        </div>
                                    </div>
                                    <div class="col-md-12 flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="sob-stock" checked name="sob-stock" value="1" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="sob-stock" class="font-medium text-gray-700"><strong>Guardar</strong> Stock</label>
                                            <p class="text-gray-500">Si desmarca esta opción, el stock no se actualizará. </p>
                                        </div>
                                    </div>


                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" onclick="JPIODFW_proces_form()" class="btn btn-primary">Importar</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="bulk-import-modal" tabindex="-1" aria-labelledby="bulkImportModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="bulkImportModalLabel">Importar productos por IDs</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="bulk-import-form">
                                <div class="mb-3">
                                    <label for="bulk-store" class="form-label"><strong>Tienda</strong></label>
                                    <select id="bulk-store" class="form-control">
                                        <option value="">Selecciona una tienda</option>
                                        <?php foreach ($tokens as $token) : ?>
                                            <option value="<?php echo esc_attr($token->id); ?>"><?php echo esc_html($token->store); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="bulk-product-ids" class="form-label"><strong>Listado de product IDs</strong></label>
                                    <textarea id="bulk-product-ids" class="form-control" rows="8" placeholder="Pega IDs separados por coma, espacio o salto de linea"></textarea>
                                    <p class="text-gray-500 mt-2">Ejemplo: <code>96, 101, 205</code> o un ID por linea.</p>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="d-block"><input id="bulk-sob-nombre" type="checkbox" checked> Guardar nombre</label>
                                        <label class="d-block"><input id="bulk-sob-descripcion" type="checkbox" checked> Guardar descripcion</label>
                                        <label class="d-block"><input id="bulk-sob-precio" type="checkbox" checked> Guardar precio</label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="d-block"><input id="bulk-sob-images" type="checkbox" checked> Guardar imagenes</label>
                                        <label class="d-block"><input id="bulk-sob-stock" type="checkbox" checked> Guardar stock</label>
                                        <label class="d-block"><input id="bulk-clean-variations" type="checkbox"> Limpiar variaciones existentes al sincronizar</label>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label for="bulk-import-results" class="form-label"><strong>Progreso</strong></label>
                                    <textarea id="bulk-import-results" class="form-control" rows="10" readonly></textarea>
                                </div>

                                <div id="bulk-import-errors-summary" class="alert alert-warning mt-3" style="display:none;">
                                    <strong>IDs no importados:</strong>
                                    <div id="bulk-import-errors-list" class="mt-2"></div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="button" id="bulk-import-submit" class="btn btn-primary">Importar listado</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}
