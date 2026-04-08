<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
include_once(dirname(__DIR__) . '/Constants.php');
include_once(dirname(__DIR__) . '/models/TokenModel.php');

class JPIODFW_ProductsModel
{

    private $helper;
    private $constants;
    private $logger;
    private static $instance;
    public $TokenModel;

    /*......*/

    /*......*/
    // class constructor
    public function __construct()
    {
        $this->helper = JPIODFW_Helper::GetInstance();
        $this->constants = JPIODFW_Constants::GetInstance();
        $this->logger = wc_get_logger();
        $this->TokenModel = JPIODFW_TokenModel::GetInstance();
    }


    static function GetInstance()
    {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 
     * busca un producto en dropi
     */
    public function getProduct($id, $id_token)
    {
        $endpoint = $this->constants->API_URL . "products/v2/" . $id;
        $last_error_message = '';
        $last_status_code = 0;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $args = array(
                'timeout' => '100000',
                'redirection' => '5',
                'httpversion' => '1.0',
                'method' => 'GET',
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'dropi-integration-key' =>  $id_token,
                ),
                'cookies' => array(),
                'sslverify' => false,
            );

            $response = wp_remote_get(
                $endpoint,
                $args
            );

            if (is_wp_error($response)) {
                $last_error_message = $response->get_error_message();
            } else {
                $last_status_code = intval(wp_remote_retrieve_response_code($response));
                $response_body = json_decode($response['body']);

                if (is_object($response_body) && isset($response_body->isSuccess) && $response_body->isSuccess && isset($response_body->objects) && is_object($response_body->objects)) {
                    return $response_body->objects;
                }

                if (is_object($response_body) && isset($response_body->message) && !empty($response_body->message)) {
                    $last_error_message = $response_body->message;
                } else {
                    $last_error_message = 'Dropi no devolvio un producto valido para el ID ' . $id;
                }
            }

            if ($attempt < 5) {
                if ($last_status_code === 429 || stripos($last_error_message, 'Too Many Attempts') !== false) {
                    usleep($attempt * 2000000);
                } else {
                    usleep($attempt * 500000);
                }
            }
        }

        if (!empty($last_error_message)) {
            $this->helper->showAdminNotice($last_error_message, 'error');
            $this->logger->error('getProduct failed for ID ' . $id . ': ' . $last_error_message, array('source' => 'dropi-products'));
        }

        return null;
    }
    public function getProducts($per_page, $current_page, $search, $orderby, $order,   $onlyVerifiedUsers, $filter_have_stock, $filter_have_description, $category_filter, $warehouses_filter, $store_filter, $tokens)
    {


        $products = [];
        $endpoint = $this->constants->API_URL . "products/index";
        $data = array(
            'startData' => $current_page,
            'pageSize' => $per_page,
            'order_type' => $order,
            'order_by' => $orderby,
            'keywords' => $search,
            'active' => true,
            'no_count' => true,
            'integration' => true
        );


        if ($endpoint == "https://api.dropi.com.es/integrations/products/index") {
            // eliminar integration del array $data.
            unset($data['integration']);
        }

        if ($endpoint == "https://api.dropi.co/integrations/products/index" || $endpoint == "https://api.dropi.com.py/integrations/products/index" || $endpoint == "https://api.dropi.pe/integrations/products/index" || $endpoint == "https://api.dropi.pa/integrations/products/index") {
            $data['get_stock'] = false;
        }


        if ($onlyVerifiedUsers != null) {
            $data['userVerified'] = true;
        }
        if ($filter_have_stock != null) {
            $data['stockmayor'] = 1;
        }
        if ($filter_have_description != null) {
            $data['notNulldescription'] = true;
        }
        if ($category_filter != null) {
            $data['category'] = $category_filter;
        }
        if ($warehouses_filter != null && $warehouses_filter != 'undefined') {
            $data['warehouse_id'] = $warehouses_filter;
        }

        $token = null;


        if ($store_filter != null) {
            $token = $this->TokenModel->getTokenById(intval($store_filter));

            $token = $token[0];
        } else {

            if (sizeof($tokens) > 0) {


                $token = $tokens[0];
            }
        }



        $all_products = array();

        $final_response = array();
        $temp_response = array();


        $args = array(
            'body' => json_encode($data),
            'timeout' => '100000',
            'redirection' => '5',
            'httpversion' => '1.0',
            // 'method' => 'GET',
            'blocking' => true,
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json;charset=UTF-8',
                'dropi-integration-key' => $token->token
            ),
            'cookies' => array(),
            'sslverify' => false,

        );


        $response = wp_remote_post(
            $endpoint,
            $args
        );
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return $error_message;
            $this->helper->showAdminNotice($error_message, 'error');
            $this->logger->info(wc_print_r($response, true), array('source' => 'dropi-products'));
        }
        $temp_response = (array)json_decode($response['body']);

        if ($temp_response['isSuccess'] == true) {
            $final_response = $temp_response;
            $all_products = array_merge($all_products, $this->TokenModel->assignStoreName($temp_response['objects'], $token));
        }

        $final_response = $temp_response;


        $final_response['objects'] = $all_products;

        $this->logger->info(wc_print_r($final_response, true), array('source' => 'dropi-products'));
        if (is_wp_error($final_response)) {
            $error_message = $final_response->get_error_message();
            return $error_message;
            $this->helper->showAdminNotice($error_message, 'error');
            $this->logger->info(wc_print_r($final_response, true), array('source' => 'dropi-products'));
        } else {
            //$response_body = (array)json_decode($final_response['body']);
            $response_body = $final_response;
            $message = '';
            if ($response_body['isSuccess'] == false) {
                if (isset($response_body['message'])) {
                    $message = $response_body['message'];
                }
                if (isset($response_body['error'])) {
                    $message = $response_body['error'];
                }
                if (empty($message)) {
                    $message = $response_body['status'];
                }

                if (isset($response_body['error'])) {
                    $message .= $response_body['error'];
                }
                $this->helper->showAdminNotice($message, 'error');
                $this->logger->info(wc_print_r($response_body, true), array('source' => 'dropi-products'));

                return $message;
            } else {
                // 
                $products  =  $response_body;
            }
        }


        return $products;
    }

    /** get product by sku from woocomerce */
    function get_product_by_sku($sku)
    {

        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));


        if ($product_id) return $product_id;

        return null;
    }

    private function buildProductSku($product, $current_product_id = 0)
    {
        $dropi_product_id = isset($product->id) ? absint($product->id) : 0;
        if ($dropi_product_id > 0) {
            return 'DROPI-' . $dropi_product_id;
        }

        $raw_sku = isset($product->sku) ? trim((string) $product->sku) : '';
        $raw_sku = preg_replace('/\s+/', '', $raw_sku);
        $raw_sku = preg_replace('/[^A-Za-z0-9._-]/', '-', $raw_sku);

        return substr($raw_sku, 0, 100);
    }

    private function buildVariationSku($product, $variation, $product_id = 0)
    {
        $dropi_product_id = isset($product->id) ? absint($product->id) : 0;
        $dropi_variation_id = isset($variation->id) ? absint($variation->id) : 0;

        if ($dropi_product_id > 0 && $dropi_variation_id > 0) {
            return 'DROPI-' . $dropi_product_id . '-DV' . $dropi_variation_id;
        }

        $raw_sku = isset($variation->sku) ? trim((string) $variation->sku) : '';
        $raw_sku = preg_replace('/\s+/', '', $raw_sku);
        $raw_sku = preg_replace('/[^A-Za-z0-9._-]/', '-', $raw_sku);

        if ($raw_sku !== '') {
            return substr($raw_sku, 0, 100);
        }

        return 'DROPI-' . $dropi_product_id . '-DV-' . absint($product_id);
    }

    private function get_variant_by_dropi_variation_id($product_id, $dropi_variation_id)
    {
        $dropi_variation_id = absint($dropi_variation_id);
        if ($dropi_variation_id <= 0) {
            return null;
        }

        $variation_ids = get_posts(
            array(
                'post_type' => 'product_variation',
                'post_parent' => absint($product_id),
                'numberposts' => -1,
                'fields' => 'ids',
                'post_status' => array('publish', 'private', 'draft'),
            )
        );

        foreach ($variation_ids as $variation_id) {
            $stored_dropi_variation_id = absint(get_post_meta($variation_id, '_dropi_variation_id', true));
            if ($stored_dropi_variation_id === $dropi_variation_id) {
                return absint($variation_id);
            }

            $stored_dropi_variation = maybe_unserialize(get_post_meta($variation_id, '_dropi_variation', true));
            if (is_object($stored_dropi_variation) && isset($stored_dropi_variation->id) && absint($stored_dropi_variation->id) === $dropi_variation_id) {
                return absint($variation_id);
            }
        }

        return null;
    }

    private function get_variant_by_dropi_source_sku($product_id, $source_sku)
    {
        $source_sku = trim((string) $source_sku);

        if ($source_sku === '') {
            return null;
        }

        $variation_ids = get_posts(
            array(
                'post_type' => 'product_variation',
                'post_parent' => absint($product_id),
                'numberposts' => -1,
                'fields' => 'ids',
                'post_status' => array('publish', 'private', 'draft'),
                'meta_query' => array(
                    array(
                        'key' => '_dropi_variation_source_sku',
                        'value' => $source_sku,
                        'compare' => '=',
                    ),
                ),
            )
        );

        if (empty($variation_ids)) {
            return null;
        }

        return absint($variation_ids[0]);
    }

    private function getWooManagedStockQuantity($product_id)
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return 0;
        }

        $manage_stock = get_post_meta($product_id, '_manage_stock', true);
        if ($manage_stock !== 'yes') {
            return 0;
        }

        $stock_quantity = get_post_meta($product_id, '_stock', true);
        if ($stock_quantity === '' || $stock_quantity === null) {
            return 0;
        }

        return intval(wc_stock_amount($stock_quantity));
    }

    private function getDropiWarehouseStockTotal($warehouse_items)
    {
        if (!is_array($warehouse_items)) {
            return null;
        }

        $stock_total = 0;
        $found_stock = false;

        foreach ($warehouse_items as $warehouse_item) {
            if (is_object($warehouse_item) && isset($warehouse_item->stock)) {
                $stock_total += intval($warehouse_item->stock);
                $found_stock = true;
            } elseif (is_array($warehouse_item) && isset($warehouse_item['stock'])) {
                $stock_total += intval($warehouse_item['stock']);
                $found_stock = true;
            }
        }

        if (!$found_stock) {
            return null;
        }

        return max(0, $stock_total);
    }

    private function getDropiVariationStockQuantity($variation)
    {
        if (is_array($variation)) {
            $variation = (object) $variation;
        }

        if (!is_object($variation)) {
            return 0;
        }

        $warehouse_stock = null;
        if (isset($variation->warehouse_product_variation)) {
            $warehouse_stock = $this->getDropiWarehouseStockTotal($variation->warehouse_product_variation);
        }

        if ($warehouse_stock !== null) {
            return $warehouse_stock;
        }

        if (isset($variation->stock)) {
            return max(0, intval($variation->stock));
        }

        return 0;
    }

    private function getDropiProductStockQuantity($product)
    {
        if (is_array($product)) {
            $product = (object) $product;
        }

        if (!is_object($product)) {
            return 0;
        }

        $warehouse_stock = null;
        if (isset($product->warehouse_product)) {
            $warehouse_stock = $this->getDropiWarehouseStockTotal($product->warehouse_product);
        }

        if ($warehouse_stock !== null) {
            return $warehouse_stock;
        }

        if (isset($product->stock)) {
            return max(0, intval($product->stock));
        }

        return 0;
    }

    private function getDropiVariationDisplayName($dropi_product, $dropi_variation)
    {
        $product_name = is_object($dropi_product) && isset($dropi_product->name) ? trim((string) $dropi_product->name) : '';
        $attribute_labels = array();

        foreach ($this->extractDropiVariationAttributes($dropi_variation) as $attribute_value) {
            if (!empty($attribute_value['option'])) {
                $attribute_labels[] = trim((string) $attribute_value['option']);
            }
        }

        if (!empty($attribute_labels)) {
            return trim($product_name . ' - ' . implode(' / ', $attribute_labels));
        }

        return $product_name;
    }

    private function normalizeDropiAttributeLabel($label)
    {
        $label = trim((string) $label);
        if ($label === '') {
            return '';
        }

        $label = remove_accents($label);
        $label = strtoupper($label);
        $label = preg_replace('/\s+/', ' ', $label);

        return trim($label);
    }

    private function parseCombinedDropiAttributeValue($attribute_name, $attribute_option)
    {
        $attribute_name = trim((string) $attribute_name);
        $attribute_option = trim((string) $attribute_option);

        if ($attribute_name === '' || $attribute_option === '') {
            return array();
        }

        $attribute_labels = array_values(array_filter(array_map('trim', explode(',', $attribute_name))));
        if (count($attribute_labels) <= 1) {
            return array(
                array(
                    'name' => $attribute_name,
                    'option' => $attribute_option,
                ),
            );
        }

        $normalized_labels = array_map(array($this, 'normalizeDropiAttributeLabel'), $attribute_labels);
        $size_label_indexes = array();
        $color_label_indexes = array();

        foreach ($normalized_labels as $index => $normalized_label) {
            if (in_array($normalized_label, array('TALLA', 'TALLAS', 'SIZE', 'TAMANO', 'TAMAÑO'), true)) {
                $size_label_indexes[] = $index;
            }

            if (in_array($normalized_label, array('COLOR', 'COLORES'), true)) {
                $color_label_indexes[] = $index;
            }
        }

        if (count($attribute_labels) === 2 && !empty($size_label_indexes) && !empty($color_label_indexes)) {
            $working_value = preg_replace('/\s+/', ' ', $attribute_option);
            $size_value = '';

            if (preg_match('/\b(?:TALLA|TALLAS|SIZE|TAMA(?:N|Ñ)O)\s*([A-Z0-9]+)/iu', $working_value, $matches)) {
                $size_value = strtoupper(trim($matches[1]));
                $working_value = trim(preg_replace('/\b(?:TALLA|TALLAS|SIZE|TAMA(?:N|Ñ)O)\s*[A-Z0-9]+\b/iu', '', $working_value, 1));
            } elseif (preg_match('/\b(XXXS|XXS|XS|S|M|L|XL|XXL|XXXL|XXXXL|XXXXXL|2XL|3XL|4XL|5XL|6XL)\b/i', $working_value, $matches)) {
                $size_value = strtoupper(trim($matches[1]));
                $working_value = trim(preg_replace('/\b' . preg_quote($matches[1], '/') . '\b/i', '', $working_value, 1));
            }

            $working_value = trim(preg_replace('/\b(?:COLOR|COLORES)\b[:\-]?\s*/iu', '', $working_value));
            $color_value = trim($working_value);

            if ($size_value !== '' && $color_value !== '') {
                $parsed_attributes = array();

                foreach ($attribute_labels as $index => $label) {
                    if (in_array($index, $size_label_indexes, true)) {
                        $parsed_attributes[] = array(
                            'name' => $label,
                            'option' => $size_value,
                        );
                    } elseif (in_array($index, $color_label_indexes, true)) {
                        $parsed_attributes[] = array(
                            'name' => $label,
                            'option' => $color_value,
                        );
                    } else {
                        $parsed_attributes[] = array(
                            'name' => $label,
                            'option' => $attribute_option,
                        );
                    }
                }

                return $parsed_attributes;
            }
        }

        return array(
            array(
                'name' => $attribute_name,
                'option' => $attribute_option,
            ),
        );
    }

    private function extractDropiVariationAttributes($variation)
    {
        if (is_array($variation)) {
            $variation = (object) $variation;
        }

        if (!is_object($variation) || empty($variation->attribute_values) || !is_array($variation->attribute_values)) {
            return array();
        }

        $attributes = array();

        foreach ($variation->attribute_values as $attribute_value) {
            $attribute_value = (object) $attribute_value;
            $attribute_name = '';
            $attribute_option = isset($attribute_value->value) ? trim((string) $attribute_value->value) : '';

            if (isset($attribute_value->attribute_name) && $attribute_value->attribute_name !== '') {
                $attribute_name = trim((string) $attribute_value->attribute_name);
            } elseif (isset($attribute_value->attribute) && is_object($attribute_value->attribute) && isset($attribute_value->attribute->description)) {
                $attribute_name = trim((string) $attribute_value->attribute->description);
            }

            if ($attribute_name === '' || $attribute_option === '') {
                continue;
            }

            foreach ($this->parseCombinedDropiAttributeValue($attribute_name, $attribute_option) as $parsed_attribute) {
                if (empty($parsed_attribute['name']) || empty($parsed_attribute['option'])) {
                    continue;
                }

                $attributes[] = array(
                    'name' => trim((string) $parsed_attribute['name']),
                    'option' => trim((string) $parsed_attribute['option']),
                );
            }
        }

        return $attributes;
    }

    private function findWooVariationIdForDropiVariation($product_id, $dropi_product, $dropi_variation)
    {
        if (is_array($dropi_variation)) {
            $dropi_variation = (object) $dropi_variation;
        }

        if (!is_object($dropi_variation)) {
            return 0;
        }

        if (isset($dropi_variation->id)) {
            $variation_id = $this->get_variant_by_dropi_variation_id($product_id, $dropi_variation->id);
            if (!empty($variation_id)) {
                return absint($variation_id);
            }
        }

        $source_sku = isset($dropi_variation->sku) ? trim((string) $dropi_variation->sku) : '';
        if ($source_sku !== '') {
            $variation_id = $this->get_variant_by_dropi_source_sku($product_id, $source_sku);
            if (!empty($variation_id)) {
                return absint($variation_id);
            }
        }

        $variation_sku = $this->buildVariationSku($dropi_product, $dropi_variation, $product_id);
        if ($variation_sku !== '') {
            $variation_id = $this->get_variant_by_sku($product_id, $variation_sku);
            if (!empty($variation_id)) {
                return absint($variation_id);
            }
        }

        if ($source_sku !== '' && $source_sku !== $variation_sku) {
            $variation_id = $this->get_variant_by_sku($product_id, $source_sku);
            if (!empty($variation_id)) {
                return absint($variation_id);
            }
        }

        return 0;
    }

    public function compareDropiStockWithWoo($product_id)
    {
        $product_id = absint($product_id);
        $dropi_product_id = absint(get_post_meta($product_id, '_dropi_product_id', true));
        $dropi_token = get_post_meta($product_id, '_dropi_token', true);
        $wc_product = wc_get_product($product_id);

        if ($product_id <= 0 || !is_object($wc_product)) {
            return array(
                'success' => false,
                'message' => 'Producto WooCommerce inválido.',
            );
        }

        if ($dropi_product_id <= 0 || empty($dropi_token)) {
            return array(
                'success' => true,
                'skipped' => true,
                'rows' => array(),
                'message' => 'El producto no está sincronizado con Dropi.',
            );
        }

        $dropi_product = $this->getProduct($dropi_product_id, $dropi_token);
        if (!is_object($dropi_product)) {
            return array(
                'success' => false,
                'message' => 'Dropi no devolvió información válida para el producto ' . $dropi_product_id . '.',
            );
        }

        $rows = array();

        if (isset($dropi_product->type) && $dropi_product->type === 'VARIABLE' && !empty($dropi_product->variations) && is_array($dropi_product->variations)) {
            foreach ($dropi_product->variations as $dropi_variation) {
                if (is_array($dropi_variation)) {
                    $dropi_variation = (object) $dropi_variation;
                }

                if (!is_object($dropi_variation)) {
                    continue;
                }

                $wc_variation_id = $this->findWooVariationIdForDropiVariation($product_id, $dropi_product, $dropi_variation);
                $wc_variation = $wc_variation_id > 0 ? wc_get_product($wc_variation_id) : null;
                $woo_stock = $wc_variation_id > 0 ? $this->getWooManagedStockQuantity($wc_variation_id) : 0;
                $dropi_stock = $this->getDropiVariationStockQuantity($dropi_variation);

                if ($woo_stock === $dropi_stock) {
                    continue;
                }

                $rows[] = array(
                    'scope' => 'variation',
                    'sync_product_id' => $product_id,
                    'wc_product_id' => $product_id,
                    'wc_variation_id' => $wc_variation_id,
                    'dropi_product_id' => $dropi_product_id,
                    'dropi_variation_id' => isset($dropi_variation->id) ? absint($dropi_variation->id) : 0,
                    'product_name' => $wc_product->get_name(),
                    'item_name' => is_object($wc_variation) ? $wc_variation->get_name() : $this->getDropiVariationDisplayName($dropi_product, $dropi_variation),
                    'sku' => $wc_variation_id > 0 ? get_post_meta($wc_variation_id, '_sku', true) : $this->buildVariationSku($dropi_product, $dropi_variation, $product_id),
                    'woo_stock' => $woo_stock,
                    'dropi_stock' => $dropi_stock,
                    'status' => $dropi_stock > $woo_stock ? 'faltante_en_woo' : 'sobrante_en_woo',
                );
            }
        } else {
            $woo_stock = $this->getWooManagedStockQuantity($product_id);
            $dropi_stock = $this->getDropiProductStockQuantity($dropi_product);

            if ($woo_stock !== $dropi_stock) {
                $rows[] = array(
                    'scope' => 'product',
                    'sync_product_id' => $product_id,
                    'wc_product_id' => $product_id,
                    'wc_variation_id' => 0,
                    'dropi_product_id' => $dropi_product_id,
                    'dropi_variation_id' => 0,
                    'product_name' => $wc_product->get_name(),
                    'item_name' => $wc_product->get_name(),
                    'sku' => $wc_product->get_sku(),
                    'woo_stock' => $woo_stock,
                    'dropi_stock' => $dropi_stock,
                    'status' => $dropi_stock > $woo_stock ? 'faltante_en_woo' : 'sobrante_en_woo',
                );
            }
        }

        return array(
            'success' => true,
            'rows' => $rows,
            'product_id' => $product_id,
            'dropi_product_id' => $dropi_product_id,
            'product_name' => $wc_product->get_name(),
            'difference_count' => count($rows),
        );
    }

    private function buildAttributesFromVariations($variations)
    {
        if (!is_array($variations)) {
            return array();
        }

        $attributes_map = array();

        foreach ($variations as $variation) {
            foreach ($this->extractDropiVariationAttributes($variation) as $attribute_value) {
                $attribute_name = $attribute_value['name'];
                $attribute_option = $attribute_value['option'];

                if (!isset($attributes_map[$attribute_name])) {
                    $attributes_map[$attribute_name] = array();
                }

                if (!in_array($attribute_option, $attributes_map[$attribute_name], true)) {
                    $attributes_map[$attribute_name][] = $attribute_option;
                }
            }
        }

        $attributes = array();
        foreach ($attributes_map as $attribute_name => $attribute_options) {
            $values = array();
            foreach ($attribute_options as $attribute_option) {
                $values[] = array('value' => $attribute_option);
            }

            $attributes[] = (object) array(
                'description' => $attribute_name,
                'values' => $values,
            );
        }

        return $attributes;
    }

    public function getImportedProductByDropiId($dropi_product_id, $token = null)
    {
        $meta_query = array(
            array(
                'key' => '_dropi_product_id',
                'value' => absint($dropi_product_id),
                'compare' => '=',
            ),
        );

        if (!empty($token)) {
            $meta_query[] = array(
                'key' => '_dropi_token',
                'value' => $token,
                'compare' => '=',
            );
        }

        $query = new WP_Query(
            array(
                'post_type' => 'product',
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => 20,
                'fields' => 'ids',
                'meta_query' => $meta_query,
            )
        );

        if (!empty($query->posts)) {
            $best_product_id = null;
            $best_score = -1;

            foreach ($query->posts as $candidate_id) {
                $candidate_id = intval($candidate_id);
                $variation_ids = get_posts(
                    array(
                        'post_type' => 'product_variation',
                        'post_parent' => $candidate_id,
                        'numberposts' => -1,
                        'fields' => 'ids',
                    )
                );

                $valid_sku_variations = 0;
                foreach ($variation_ids as $variation_id) {
                    if (get_post_meta($variation_id, '_sku', true) !== '') {
                        $valid_sku_variations++;
                    }
                }

                $score = ($valid_sku_variations * 10) + count($variation_ids);
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_product_id = $candidate_id;
                }
            }

            if (!empty($best_product_id)) {
                return intval($best_product_id);
            }

            return intval($query->posts[0]);
        }

        return null;
    }



    public function import_product(
        $product,
        $product_name = null,
        $product_description = null,
        $product_price = null,
        $sob_descripcion = null,
        $sob_nombre = null,
        $sob_precio = null,
        $sob_images = null,
        $variationstoimport  = null,
        $productaction = null,
        $productselect  = null,
        $variations = null,
        $chose_variations = null,
        $attributes = null,
        $sob_stock = null,
        $store = null,
        $dropi_product = null,
        $clean_existing_variations = 'false',
        $overwrite_variation_images = 'false'
    ) {
        $success = false;
        $message = '';
        $variation_errors = array();
        $created_new_post = false;
        $overwrite_variation_images_enabled = ($overwrite_variation_images === 'true' || $overwrite_variation_images === true);

        try {
            //busco la data del producto en dropi
            $selected_store = null;
            if (is_array($store) && !empty($store)) {
                $selected_store = $store[0];
            } else {
                $tokens = $this->TokenModel->getTokens();
                if (!empty($tokens)) {
                    $selected_store = $tokens[0];
                }
            }

            if (!is_object($selected_store) || empty($selected_store->token)) {
                throw new Exception('No se encontro un token de Dropi valido para importar el producto');
            }

            $token = $selected_store->token;

            if (is_object($dropi_product)) {
                $product = $dropi_product;
            } else {
                $product = $this->getProduct($product, $token);
            }

            if (!is_object($product)) {
                return array(
                    'success' => false,
                    'message' => 'Dropi no devolvio informacion valida para este producto. Intenta nuevamente.',
                );
            }

            if ($productaction !== 'SYNC' && isset($product->id)) {
                $existing_imported_post_id = $this->getImportedProductByDropiId($product->id, $token);
                if (!empty($existing_imported_post_id)) {
                    $productaction = 'SYNC';
                    $productselect = intval($existing_imported_post_id);
                }
            }

            if ($product->description == null) {
                $product->description = '';
            }

            $name = !empty($product_name) ? $product_name : $product->name;
            $description = !empty($product_description) ? $product_description : $product->description;
            $price = !empty($product_price) ? floatval($product_price) : $product->suggested_price;

            $post = array(
                'post_status' => 'publish',
                'post_type' => "product",
            );


            if ($sob_nombre == 'true' || $sob_nombre == null) {
                $post['post_title'] = $name;
            }
            if ($sob_descripcion == 'true' || $sob_descripcion == null) {
                $post['post_content'] = $description;
            }


            //SI LA ACCION ES SINCRONIZAR CON RPODUCTO EXISTENTE
            if ($productaction === 'SYNC') {

                //post id vendria siendo el id del oprducto en woocmerce
                $post_id = intval($productselect);
                if ($post_id > 0) {
                    $post['ID'] = $post_id;
                    wp_update_post($post);
                }
            } else {
                $post_id = wp_insert_post($post);
                $created_new_post = is_int($post_id) && $post_id > 0;
            }



            if (is_int($post_id) && $post_id > 0) {
                $product_sku = $this->buildProductSku($product, $post_id);

                if ((empty($attributes) || !is_array($attributes)) && $variationstoimport != null && sizeof($variationstoimport) > 0 && is_array($variations)) {
                    $attributes = $this->buildAttributesFromVariations($variations);
                }

                if (
                    $productaction === 'SYNC' &&
                    ($clean_existing_variations === 'true' || $clean_existing_variations === true) &&
                    $variationstoimport != null &&
                    sizeof($variationstoimport) > 0
                ) {
                    $this->delete_product_variations($post_id);
                    $chose_variations = array();
                }

                //esto es pa crear los atributos si no existen
                if ($attributes != null && sizeof($attributes) > 0) {
                    $this->create_product_attributes($post_id, $attributes);
                }

                //SI TIENE VARIABLES AIMPORTAR
                if ($variationstoimport != null && sizeof($variationstoimport) > 0) {
                    wp_set_object_terms($post_id, 'variable', 'product_type');
                    $has_in_stock_variation = false;


                    // The variation data
                    foreach ($variations as $variation) {
                        $variation = (object)$variation;

                        if (in_array($variation->id, $variationstoimport)) {
                            $varianExisttId = false;
                            $variation_sku = $this->buildVariationSku($product, $variation, $post_id);

                            $existing_variation_by_dropi_id = $this->get_variant_by_dropi_variation_id($post_id, $variation->id);
                            if (!empty($existing_variation_by_dropi_id)) {
                                $varianExisttId = $existing_variation_by_dropi_id;
                            }

                            foreach ($chose_variations as $chose) {
                                if (isset($chose[$variation->id]) && $chose[$variation->id] != null) {

                                    $varianExisttId = $chose[$variation->id];
                                }
                            }

                            if ($varianExisttId === false && !empty($variation_sku)) {
                                $varianExisttId = $this->get_variant_by_sku($post_id, $variation_sku);
                            }

                            if ($varianExisttId === false && !empty($variation->sku) && $variation->sku !== $variation_sku) {
                                $varianExisttId = $this->get_variant_by_sku($post_id, $variation->sku);
                            }

                            // la variable no xiste la creo
                            $variation_data =  array(
                                'sku'           => $variation_sku,
                                'source_sku'    => isset($variation->sku) ? $variation->sku : '',
                                'regular_price' => $variation->suggested_price
                            );
                            //sobreescribir stock
                            if ($sob_stock == "true") {
                                $finalStockByWarehouse = 0;

                                if (isset($variation->stock)) {
                                    $variation_data['stock_qty'] = $variation->stock;
                                }

                                if (isset($variation->warehouse_product_variation)) {
                                    foreach ($variation->warehouse_product_variation as $ware) {
                                        if (is_array($ware) && isset($ware['stock'])) {
                                            $finalStockByWarehouse += intval($ware['stock']);
                                        } elseif (is_object($ware) && isset($ware->stock)) {
                                            $finalStockByWarehouse += intval($ware->stock);
                                        }
                                    }
                                    $variation_data['stock_qty'] = $finalStockByWarehouse;
                                }
                            }

                            if (isset($variation_data['stock_qty']) && intval($variation_data['stock_qty']) > 0) {
                                $has_in_stock_variation = true;
                            }

                            $attributes = [];
                            $attributes2 = [];
                            foreach ($this->extractDropiVariationAttributes($variation) as $attr) {
                                $attribute = array(
                                    $attr['name'] => $attr['option']
                                );
                                $attribute2 = array(
                                    'id' => 0, 'name' =>  $attr['name'], 'option' => $attr['option']
                                );
                                $attributes[] = $attribute;
                                $attributes2[] = $attribute2;
                            }
                            $variation_data['attributes'] = $attributes;
                            $variation_data['attributes2'] = $attributes2;
                            if ($sob_images == 'true' || $sob_images == null) {
                                $variation_data['image'] = $this->getVariationImageSource($product, $variation);
                            }


                            $variation_result = $this->create_product_variation($post_id, $variation_data, $variation, $varianExisttId, $overwrite_variation_images_enabled);
                            if (!empty($variation_result)) {
                                $variation_errors[] = 'Variación ' . (!empty($variation_sku) ? $variation_sku : (isset($variation->sku) ? $variation->sku : $variation->id)) . ': ' . $variation_result;
                            }
                        }
                    }

                    if (!empty($variation_errors) && $created_new_post === true) {
                        $this->delete_product_variations($post_id);
                        wp_delete_post($post_id, true);

                        return array(
                            'success' => false,
                            'message' => implode(' | ', $variation_errors),
                        );
                    }

                    if (class_exists('WC_Product_Variable')) {
                        WC_Product_Variable::sync($post_id);
                    }

                    $parent_stock_status = $has_in_stock_variation ? 'instock' : 'outofstock';
                    $parent_product = wc_get_product($post_id);

                    if (is_object($parent_product)) {
                        if ($parent_stock_status !== 'instock' && method_exists($parent_product, 'get_available_variations')) {
                            $available_variations = $parent_product->get_available_variations();
                            $parent_stock_status = !empty($available_variations) ? 'instock' : 'outofstock';
                        }

                        if ($parent_stock_status !== 'instock') {
                            foreach ($parent_product->get_children() as $child_variation_id) {
                                if (get_post_meta($child_variation_id, '_stock_status', true) === 'instock') {
                                    $parent_stock_status = 'instock';
                                    break;
                                }
                            }
                        }

                        $parent_product->set_manage_stock(false);
                        $parent_product->set_stock_quantity(null);
                        $parent_product->set_stock_status($parent_stock_status);
                        $parent_product->save();
                    }

                    update_post_meta($post_id, '_manage_stock', 'no');
                    delete_post_meta($post_id, '_stock');

                    if (function_exists('wc_update_product_stock_status')) {
                        wc_update_product_stock_status($post_id, $parent_stock_status);
                    }

                    update_post_meta($post_id, '_stock_status', $parent_stock_status);
                    $lookup_ids = array_merge(array($post_id), is_object($parent_product) ? $parent_product->get_children() : array());
                    $this->syncProductLookupStockData($lookup_ids);

                    wc_delete_product_transients($post_id);
                } else {
                    wp_set_object_terms($post_id, 'simple', 'product_type');
                }


                // Get term *objects* with name that *matches* "my_name"
                /*$terms = get_terms([
                    'taxonomy' => 'category',
                    'name' => $product->categories[0]->name,
                    'hide_empty' => false,
                ]);*/

                try {
                    $cat_name = $product->categories[0]->name;


                    $category = get_term_by('name', $cat_name, 'product_cat');
                    $category_id = (is_object($category) && isset($category->term_id)) ? $category->term_id : 0;

                    if (empty($category_id) && !empty($cat_name)) {
                        //creo la categoria si no existe
                        $term = wp_insert_term($cat_name, 'product_cat', array(
                            'description' => $cat_name, // optional
                            'parent' => 0, // optional
                            //'slug' => 'my-new-category' // optional
                        ));

                        if (is_wp_error($term)) {
                            $message .= $term->get_error_message();
                            // $this->helper->showAdminNotice($message, 'error');

                        } else {
                            $category_id = $term['term_id'];
                            wp_set_object_terms($post_id, $category_id, 'product_cat');
                        }
                    }
                } catch (Exception $e) {
                    $message .= $e->getMessage();
                    //$this->helper->showAdminNotice($message, 'error');

                }

                if ($sob_precio == 'true'  || $sob_precio == null) {
                    update_post_meta($post_id, '_price', $price);
                    update_post_meta($post_id, '_regular_price',  $price);
                }


                if (!empty($product_sku)) {
                    update_post_meta($post_id, '_sku', $product_sku);
                }
                update_post_meta($post_id, '_dropi_product_source_sku', isset($product->sku) ? $product->sku : '');


                if ($sob_stock == "true") {

                    if ($variationstoimport != null && sizeof($variationstoimport) > 0) {
                        //no hago nada si es variable para que no me ponga el stock en cero
                        //update_post_meta($post_id, '_manage_stock', false);
                    } else {

                        $stockForSimple = null;
                        $warehouseStockForSimple = 0;
                        $hasWarehouseStock = false;

                        if (isset($product->warehouse_product) && is_array($product->warehouse_product)) {
                            foreach ($product->warehouse_product as $value) {
                                if (is_object($value) && isset($value->stock)) {
                                    $warehouseStockForSimple += intval($value->stock);
                                    $hasWarehouseStock = true;
                                } elseif (is_array($value) && isset($value['stock'])) {
                                    $warehouseStockForSimple += intval($value['stock']);
                                    $hasWarehouseStock = true;
                                }
                            }
                        }

                        if ($hasWarehouseStock) {
                            $stockForSimple = $warehouseStockForSimple;
                        } elseif (isset($product->stock)) {
                            $stockForSimple = intval($product->stock);
                        }

                        if ($stockForSimple === null) {
                            $stockForSimple = 0;
                        }

                        $simple_stock_status = intval($stockForSimple) > 0 ? 'instock' : 'outofstock';

                        update_post_meta($post_id, '_stock', $stockForSimple);
                        update_post_meta($post_id, '_manage_stock', 'yes');
                        update_post_meta($post_id, '_stock_status', $simple_stock_status);

                        if (function_exists('wc_update_product_stock_status')) {
                            wc_update_product_stock_status($post_id, $simple_stock_status);
                        }

                        $simple_product = wc_get_product($post_id);
                        if (is_object($simple_product)) {
                            $simple_product->set_manage_stock(true);
                            $simple_product->set_stock_quantity($stockForSimple);
                            $simple_product->set_stock_status($simple_stock_status);
                            $simple_product->save();
                        }

                        $this->syncProductLookupStockData(array($post_id));
                    }
                }


                update_post_meta($post_id, '_dropi_product', serialize($product));
                update_post_meta($post_id, '_dropi_product_id', $product->id);
                update_post_meta($post_id, '_dropi_token_store', $selected_store->store);
                update_post_meta($post_id, '_dropi_token', $selected_store->token);
                
                if (isset($product->sale_price) && $product->sale_price !== null && $product->sale_price !== '') {
                    update_post_meta($post_id, '_dropi_supplier_price', $product->sale_price);
                }

                // update_post_meta($post_id, '_stock_status', 'instock');
                if ($sob_images == 'true' || $sob_images == null) {

                    try {
                        $images = array();
                        if (isset($product->photos) && is_array($product->photos)) {
                            $images = $product->photos;
                        } elseif (isset($product->gallery) && is_array($product->gallery)) {
                            $images = $product->gallery;
                        }

                        $this->setPostImages($post_id, $images);
                    } catch (Exception $e) {
                        $this->logger->error($e->getMessage(), array('source' => 'dropi-products-images'));
                    }
                }

                $this->setImportedOnImportLits($product, $post_id, $token);
                if (!empty($variation_errors)) {
                    $message = implode(' | ', $variation_errors);
                    $success = false;
                } else {
                    $success = true;
                }
            } else {

                if (is_wp_error($post_id)) {
                    $error_message = $post_id->get_error_message();
                    $message = $error_message;
                    //$this->helper->showAdminNotice($error_message, 'error');
                    $this->logger->error(wc_print_r($error_message, true), array('source' => 'dropi-products'));
                }

                if (empty($post['post_title'])) {
                    $message = 'El campo nombre es requerido';
                }
            }
        } catch (Exception $e) {
            //$this->helper->showAdminNotice('Error', 'error');
            $message = 'import_product Error ' . $e->getLine() . " " . $e->getMessage();
            $this->logger->error(wc_print_r($e, true), array('source' => 'dropi-products'));
        }
        return ['success' => $success, 'message' => $message];
    }

    private function syncProductLookupStockData($product_ids)
    {
        global $wpdb;

        if (empty($product_ids) || !isset($wpdb->wc_product_meta_lookup)) {
            return;
        }

        foreach (array_unique(array_map('absint', $product_ids)) as $product_id) {
            if ($product_id <= 0) {
                continue;
            }

            $manage_stock = get_post_meta($product_id, '_manage_stock', true);
            $stock_status = get_post_meta($product_id, '_stock_status', true);
            $stock_quantity = ($manage_stock === 'yes') ? get_post_meta($product_id, '_stock', true) : null;

            $updated = $wpdb->update(
                $wpdb->wc_product_meta_lookup,
                array(
                    'stock_status' => $stock_status !== '' ? $stock_status : 'outofstock',
                    'stock_quantity' => ($stock_quantity === '' || $stock_quantity === null) ? null : wc_stock_amount($stock_quantity),
                ),
                array(
                    'product_id' => $product_id,
                ),
                array(
                    '%s',
                    '%f',
                ),
                array('%d')
            );

            $lookup_row_exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->wc_product_meta_lookup} WHERE product_id = %d",
                    $product_id
                )
            );

            if ($updated === false || ($updated === 0 && $lookup_row_exists === 0)) {
                $wpdb->insert(
                    $wpdb->wc_product_meta_lookup,
                    array(
                        'product_id' => $product_id,
                        'stock_status' => $stock_status !== '' ? $stock_status : 'outofstock',
                        'stock_quantity' => ($stock_quantity === '' || $stock_quantity === null) ? null : wc_stock_amount($stock_quantity),
                    ),
                    array(
                        '%d',
                        '%s',
                        '%f',
                    )
                );
            }
        }
    }


    private function create_product_attributes($product_id, $attributes)
    {
        try {
            $product = wc_get_product($product_id);
            if (is_object($product)) {
                $attrbiutestoset = [];
                foreach ($attributes as $attr) {

                    $attr = (object)$attr;


                    //busco para ver si el atributo ya existe
                    //$label = wc_attribute_label($attr->description);

                    //otra forma de buscar el attributo con su id
                    $existattr = $product->get_attribute($attr->description);

                    //var_dump($existattr);
                    //si no existe el atributo entonces lo creo
                    //es importante validar si no existe para que no lo sobreescriba en el vincular a producto existente
                    if (empty($existattr)) {
                        $attribute = new WC_Product_Attribute();

                        $attribute->set_id(0);
                        //pa_size slug
                        $attribute->set_name($attr->description);

                        $options = [];

                        if (isset($attr->values)) {
                            foreach ($attr->values as $value) {
                                $options[] = $value['value'];
                            }
                        }

                        //Set terms slugs
                        $attribute->set_options($options);
                        // $attribute->set_position(0);

                        //If enabled
                        $attribute->set_visible(1);

                        //If we are going to use attribute in order to generate variations
                        $attribute->set_variation(1);

                        $attrbiutestoset[] = $attribute;
                    }
                }

                if (sizeof($attrbiutestoset) > 0) {
                    $product->set_attributes($attrbiutestoset);
                }

                if (is_object($product)) {
                    $product->save();
                }
            }
        } catch (Exception $e) {

            echo $e->getMessage();
            echo $e->getFile();
            echo $e->getLine();
        }
    }
    /**
     * Create a product variation for a defined variable product ID.
     *
     * @since 3.0.0
     * @param int   $product_id | Post ID of the product parent variable product.
     * @param array $variation_data | The data to insert in the product.
     */

    private function create_product_variation($product_id, $variation_data, $dropi_variation, $varianExisttId, $overwrite_variation_images = false)
    {
        $create = false;
        $message = '';
        try {
            // Get the Variable product object (parent)
            $product = wc_get_product($product_id);
            $variation_id = false;
            //si ya viene con un variation id quiere quedcir que en el selector selecciono vincular a una variabloe
            //si viene false quiere decir que selecciono crear niueva
            if ($varianExisttId === false) {
                $create = true;
                //lo comento porque en teoria no deberia permitirme modificar una variable que ya tenga ese sku. simplemente deberia botar la alerta
                // pero prmero busco si ya exiuste una variable con ese sku, porque woocomerce no me permite crear variables con sku duplicados
                // $variation_id = $this->get_variant_by_sku($product_id, $variation_data['sku']);
            } else {
                $variation_id = $varianExisttId;
            }

            $default_attributes = [];

            $variation_post = array(
                'post_title'  => $product->get_name(),
                'post_name'   => 'product-' . $product_id . '-variation',
                'post_status' => 'publish',
                'post_parent' => $product_id,
                'post_type'   => 'product_variation',
                'guid'        => $product->get_permalink(),
                //'sku' => $variation_data['sku']
                // 'attributes' =>  $default_attributes

            );

            //$variation_id = $this->get_variant_by_sku($product_id, $variation_data['sku']);

            if ($variation_id == false && !empty($variation_data['sku'])) {
                $global_sku_post_id = absint($this->get_product_by_sku($variation_data['sku']));

                if ($global_sku_post_id > 0) {
                    $global_parent_id = absint(wp_get_post_parent_id($global_sku_post_id));

                    if ($global_parent_id === absint($product_id)) {
                        $variation_id = $global_sku_post_id;
                        $create = false;
                    } else {
                        return 'Invalid or duplicated SKU.';
                    }
                }
            }

            // si no viene la variable por chosen, y no existe una variable con ese sku, la creo
            if ($variation_id == false) {
                $create = true;
                $variation_id = wp_insert_post($variation_post);
            }

            $variation =  new WC_Product_Variation($variation_id);
            $current_variation_image_id = absint(get_post_meta($variation_id, '_thumbnail_id', true));
            $should_sync_variation_image = ($create === true || $overwrite_variation_images === true || $current_variation_image_id <= 0);

            // aqui lo que hago es setar el sku a la variable bien sea nueva o bien sea que exista, pero woocomerce no permite crear variables con el mismo sku asi que explotaria 


            // SKU
            try {
                $existVariantBySku = $this->get_variant_by_sku($product_id, $variation_data['sku']);

                // valido si hay un sku que viene de dropi y si no existe ya una variable con ese sku para entocnes asignarselo
                if (!empty($variation_data['sku']) &&   $existVariantBySku == null)
                    $variation->set_sku($variation_data['sku']);
            } catch (Exception $e) {

                $message = $e->getMessage();
            }

            /**
             * ahora busco los atributos que mande por parametro y se los asigno a la variable
             * pero lo hago solo si es create true, para no sobrescriir los valores de los atributos, y le sirva a cristian trujillo
             */
            $variation_attributes = array();
            foreach ($variation_data['attributes2'] as $attr) {

                $attr = (object)$attr;

                if (!empty($attr->name) && $attr->name != '') {
                    $attribute_key = sanitize_title($attr->name);
                    $default_attributes[$attribute_key] = $attr->option;
                    $variation_attributes[$attribute_key] = $attr->option;
                    update_post_meta($variation_id, 'attribute_' . $attribute_key, $attr->option);
                }
            }

            if (!empty($variation_attributes)) {
                $variation->set_attributes($variation_attributes);
            }


            // aqui hago toda la vuelta de los precios
            if (!empty($variation_data['regular_price'])) {
                if ($create === true) {
                    $variation->set_price($variation_data['regular_price']);
                } else {

                    update_post_meta($variation_id, "_regular_price", $variation_data['regular_price']);
                }
            }
            if (!empty($variation_data['sale_price'])) {
                if ($create === true) {
                    $variation->set_price($variation_data['sale_price']);
                    $variation->set_sale_price($variation_data['sale_price']);
                } else {

                    update_post_meta($variation_id, "_price", $variation_data['sale_price']);
                    update_post_meta($variation_id, "_sale_price", $variation_data['sale_price']);
                }
            }

            $variation->set_regular_price($variation_data['regular_price']);

            // Stock
            if (array_key_exists('stock_qty', $variation_data) && $variation_data['stock_qty'] !== null && $variation_data['stock_qty'] !== '') {
                $stock_quantity = max(0, intval($variation_data['stock_qty']));
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity($stock_quantity);
                $variation->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
            } else {
                $variation->set_manage_stock(false);
                $variation->set_stock_quantity(null);
            }

            if ($should_sync_variation_image && !empty($variation_data['image'])) {
                $variation_image_id = $this->importDropiImageAttachment($variation_id, $variation_data['image']);
                if (!empty($variation_image_id)) {
                    $variation->set_image_id($variation_image_id);
                    update_post_meta($variation_id, '_thumbnail_id', $variation_image_id);
                }
            } elseif ($should_sync_variation_image && array_key_exists('image', $variation_data)) {
                $variation->set_image_id(0);
                delete_post_meta($variation_id, '_thumbnail_id');
            }

            $variation->set_weight(''); // weight (reseting)

            update_post_meta($variation_id,  '_dropi_variation', serialize($dropi_variation));
            if (isset($dropi_variation->id)) {
                update_post_meta($variation_id, '_dropi_variation_id', absint($dropi_variation->id));
            }
            if (isset($variation_data['source_sku'])) {
                update_post_meta($variation_id, '_dropi_variation_source_sku', $variation_data['source_sku']);
            }


            $this->logger->error('productvariation ' . $variation_id, array('source' => 'dropi-products'));
            $this->logger->error(wc_print_r($dropi_variation, true), array('source' => 'dropi-products'));

            $variation->apply_changes(); // Save the data
            $variation->save(); // Save the data
            $variation->save_meta_data(); // Save the data

            if (!empty($variation_data['sku'])) {
                update_post_meta($variation_id, '_sku', $variation_data['sku']);
            }

            if (array_key_exists('stock_qty', $variation_data) && $variation_data['stock_qty'] !== null && $variation_data['stock_qty'] !== '') {
                update_post_meta($variation_id, '_manage_stock', 'yes');
                update_post_meta($variation_id, '_stock', $stock_quantity);
                update_post_meta($variation_id, '_stock_status', $stock_quantity > 0 ? 'instock' : 'outofstock');
            } else {
                update_post_meta($variation_id, '_manage_stock', 'no');
                delete_post_meta($variation_id, '_stock');
                update_post_meta($variation_id, '_stock_status', 'outofstock');
            }


            $dropi_variation = get_post_meta($variation_id, '_dropi_variation', true);
            //var_dump($dropi_variation);
            // var_dump($variation->get_attributes());
            //var_dump($variation->get_default_attributes());
        } catch (Exception $e) {


            $message = $e->getMessage();
        }


        return  $message;
    }


    /** get variant by sku from woocomerce */
    function  get_variant_by_sku($product_id, $sku)
    {
        $existe = null;
        try {

            $product = new WC_Product_Variable($product_id);



            $current_variations = $product->get_available_variations();

            foreach ($current_variations as $kcurrentvariation) {

                // var_dump($kcurrentvariation);

                if ($kcurrentvariation['sku'] === $sku) {
                    $existe = $kcurrentvariation['variation_id'];
                }
            }
        } catch (Exception $e) {
            //$this->helper->showAdminNotice('Error', 'error');


            $this->logger->error(wc_print_r($e, true), array('source' => 'dropi-products'));
        }


        return $existe;
    }

    private function delete_product_variations($product_id)
    {
        $product = wc_get_product($product_id);

        if (!is_object($product) || !$product->is_type('variable')) {
            return;
        }

        foreach ($product->get_children() as $variation_id) {
            wp_delete_post($variation_id, true);
        }
    }



    private function setImportedOnImportLits($product, $post_id, $id_token)
    {
        try {


            $endpoint = $this->constants->API_URL . "importlist/importstore/1";

            $data = array(
                "products_id" => $product->id,
                "imported_to_store" =>  true,
                "woocomerse_id" =>  $post_id,
                "woocomerse_url" => get_post_field('post_name', $post_id)
            );
            //$id_token = $this->helper->getToken();

            $args = array(
                'body' => json_encode($data),
                'timeout' => '100000',
                'redirection' => '5',
                'httpversion' => '1.0',
                'method' => 'POST',
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'dropi-integration-key' =>  $id_token,
                    //'Authorization-token' => $id_token,
                ),
                'cookies' => array(),
                'sslverify' => false,
                'method'    => 'PUT'

            );

            $response = wp_remote_request(
                $endpoint,
                $args
            );
            $response_body = (array)json_decode($response['body']);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                $this->logger->error('Error updating import list ', array('source' => 'dropi-products'));
                $this->logger->error(wc_print_r($error_message, true), array('source' => 'dropi-products'));
            } else {
                if (isset($response_body['isSuccess']) && $response_body['isSuccess'] === true) {

                    $this->logger->info('SUCCESS ' . $response_body['message'], array('source' => 'dropi-products'));
                } else {
                    $this->logger->error('ERROR EN PUT IMPORT LIST SHOP' . isset($response_body['message']) ? $response_body['message'] : ' CHECKEAR EN EN EL LOG DEL BACK', array('source' => 'dropi-products'));
                }
            }
        } catch (Exception $e) {
            $this->logger->error('EXCEPTION' . wc_print_r($e, true), array('source' => 'dropi-products'));
        }
    }
    private function setPostImages($post_id, $gallery)
    {
        try {
            if (!is_array($gallery) || empty($gallery)) {
                return;
            }

            usort($gallery, function ($left, $right) {
                $left_is_main = (is_object($left) && !empty($left->main)) ? 1 : 0;
                $right_is_main = (is_object($right) && !empty($right->main)) ? 1 : 0;

                return $right_is_main <=> $left_is_main;
            });

            $attachment_ids = array();

            foreach ($gallery as $img) {
                $attach_id = $this->importDropiImageAttachment($post_id, $img);

                if (!empty($attach_id)) {
                    $attachment_ids[] = $attach_id;
                }
            }

            $attachment_ids = array_values(array_unique(array_map('intval', $attachment_ids)));

            if (empty($attachment_ids)) {
                return;
            }

            set_post_thumbnail($post_id, $attachment_ids[0]);
            update_post_meta($post_id, '_product_image_gallery', implode(',', array_slice($attachment_ids, 1)));
        } catch (Exception $e) {

            $this->logger->error('error al crear imagenes', array('source' => 'dropi-products'));
            $this->logger->error(wc_print_r($e, true), array('source' => 'dropi-products'));
        }
    }

    private function getVariationImageSource($product, $variation)
    {
        if (!is_object($product) || !is_object($variation)) {
            return null;
        }

        $photos = array();

        if (isset($product->photos) && is_array($product->photos)) {
            $photos = $product->photos;
        } elseif (isset($product->gallery) && is_array($product->gallery)) {
            $photos = $product->gallery;
        }

        if (empty($photos)) {
            return null;
        }

        foreach ($photos as $photo) {
            if (!is_object($photo) && !is_array($photo)) {
                continue;
            }

            $photo_variation_id = '';

            if (is_object($photo) && isset($photo->variation_id)) {
                $photo_variation_id = $photo->variation_id;
            } elseif (is_array($photo) && isset($photo['variation_id'])) {
                $photo_variation_id = $photo['variation_id'];
            }

            if ($photo_variation_id !== '' && intval($photo_variation_id) === intval($variation->id)) {
                return $photo;
            }
        }

        return null;
    }

    private function importDropiImageAttachment($post_id, $img)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $image_url = $this->getDropiImageUrl($img);

        if (empty($image_url)) {
            return 0;
        }

        $image_name = $this->getDropiImageFilename($img, $image_url, $post_id);
        $existing_attachment_id = $this->findExistingDropiAttachment($img, $image_url, $image_name);

        if (!empty($existing_attachment_id)) {
            $this->persistDropiAttachmentMeta($existing_attachment_id, $post_id, $img, $image_url, $image_name);
            return $existing_attachment_id;
        }

        $tmp_file = download_url($image_url, 120);

        if (is_wp_error($tmp_file)) {
            $this->logger->error('No se pudo descargar imagen ' . $image_url, array('source' => 'dropi-products-images'));
            $this->logger->error($tmp_file->get_error_message(), array('source' => 'dropi-products-images'));
            return 0;
        }

        $file_array = array(
            'name' => $image_name,
            'tmp_name' => $tmp_file,
        );

        $attach_id = media_handle_sideload(
            $file_array,
            $post_id,
            null,
            array(
                'post_parent' => $post_id,
            )
        );

        if (is_wp_error($attach_id)) {
            @unlink($tmp_file);
            $this->logger->error('No se pudo crear attachment para imagen ' . $image_url, array('source' => 'dropi-products-images'));
            $this->logger->error($attach_id->get_error_message(), array('source' => 'dropi-products-images'));
            return 0;
        }

        $this->persistDropiAttachmentMeta($attach_id, $post_id, $img, $image_url, $image_name);

        return intval($attach_id);
    }

    private function findExistingDropiAttachment($img, $image_url, $image_name)
    {
        $source_key = $this->getDropiImageSourceKey($img, $image_url);

        if ($source_key !== '') {
            $attachment_id = $this->findAttachmentByMeta('_dropi_image_source_key', $source_key);

            if (!empty($attachment_id)) {
                return $attachment_id;
            }
        }

        $source_id = $this->getDropiImageSourceId($img);

        if ($source_id !== '') {
            $attachment_id = $this->findAttachmentByMeta('_dropi_image_source_id', $source_id);

            if (!empty($attachment_id)) {
                return $attachment_id;
            }
        }

        if ($image_url !== '') {
            $attachment_id = $this->findAttachmentByMeta('_dropi_image_source_url', $image_url);

            if (!empty($attachment_id)) {
                return $attachment_id;
            }
        }

        return $this->findAttachmentByFileName($image_name);
    }

    private function findAttachmentByMeta($meta_key, $meta_value)
    {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        if (empty($attachments)) {
            return 0;
        }

        return absint($attachments[0]);
    }

    private function findAttachmentByFileName($image_name)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_wp_attached_file'
              AND (meta_value = %s OR meta_value LIKE %s)
            ORDER BY post_id ASC
            LIMIT 1",
            $image_name,
            '%/' . $wpdb->esc_like($image_name)
        );

        $attachment_id = $wpdb->get_var($query);

        if (empty($attachment_id)) {
            return 0;
        }

        return absint($attachment_id);
    }

    private function persistDropiAttachmentMeta($attachment_id, $post_id, $img, $image_url, $image_name)
    {
        if (empty($attachment_id)) {
            return;
        }

        $source_key = $this->getDropiImageSourceKey($img, $image_url);
        $source_id = $this->getDropiImageSourceId($img);

        if ($source_key !== '') {
            update_post_meta($attachment_id, '_dropi_image_source_key', $source_key);
        }

        if ($source_id !== '') {
            update_post_meta($attachment_id, '_dropi_image_source_id', $source_id);
        }

        if ($image_url !== '') {
            update_post_meta($attachment_id, '_dropi_image_source_url', $image_url);
        }

        if ($image_name !== '') {
            update_post_meta($attachment_id, '_dropi_image_source_filename', $image_name);
        }

        $this->updateDropiAttachmentSearchData($attachment_id, $post_id, $img, $image_name);
    }

    private function getDropiImageUrl($img)
    {
        if (is_array($img)) {
            $img = (object)$img;
        }

        if (!is_object($img)) {
            return '';
        }

        if (!empty($img->urlS3)) {
            return $this->normalizeRemoteUrl('https://d39ru7awumhhs2.cloudfront.net/' . ltrim($img->urlS3, '/'));
        }

        if (!empty($img->url)) {
            return $this->normalizeRemoteUrl($this->constants->IMG_URL . ltrim($img->url, '/'));
        }

        return '';
    }

    private function getDropiAttachmentContext($post_id, $img, $image_name = '')
    {
        if (is_array($img)) {
            $img = (object) $img;
        }

        $post_id = absint($post_id);
        $base_post_id = $post_id;
        $base_post = get_post($post_id);

        if (is_object($base_post) && $base_post->post_type === 'product_variation' && !empty($base_post->post_parent)) {
            $base_post_id = absint($base_post->post_parent);
        }

        $product_name = $base_post_id > 0 ? get_the_title($base_post_id) : '';
        if ($product_name === '' && is_object($base_post) && !empty($base_post->post_title)) {
            $product_name = $base_post->post_title;
        }

        $dropi_product_id = $base_post_id > 0 ? absint(get_post_meta($base_post_id, '_dropi_product_id', true)) : 0;
        if ($dropi_product_id <= 0 && is_object($img) && isset($img->product_id)) {
            $dropi_product_id = absint($img->product_id);
        }

        $image_source_id = $this->getDropiImageSourceId($img);
        if ($image_source_id === '' && $image_name !== '') {
            $image_source_id = sanitize_title(pathinfo($image_name, PATHINFO_FILENAME));
        }

        $product_slug = sanitize_title($product_name);
        if ($product_slug === '') {
            $product_slug = $dropi_product_id > 0 ? 'dropi-product-' . $dropi_product_id : 'dropi-image';
        }

        $search_slug = $product_slug;
        if ($dropi_product_id > 0) {
            $search_slug .= '-dropi-' . $dropi_product_id;
        }
        if ($image_source_id !== '') {
            $search_slug .= '-img-' . sanitize_title($image_source_id);
        }

        $human_title = $product_name !== '' ? $product_name : ucwords(str_replace('-', ' ', $product_slug));
        if ($dropi_product_id > 0) {
            $human_title .= ' - Dropi ' . $dropi_product_id;
        }
        if ($image_source_id !== '') {
            $human_title .= ' - Imagen ' . $image_source_id;
        }

        $description = 'Imagen sincronizada desde Dropi para ' . ($product_name !== '' ? $product_name : $product_slug) . '.';
        if ($dropi_product_id > 0) {
            $description .= ' Dropi product id: ' . $dropi_product_id . '.';
        }
        if ($image_source_id !== '') {
            $description .= ' Dropi image id: ' . $image_source_id . '.';
        }

        return array(
            'dropi_product_id' => $dropi_product_id,
            'title' => $human_title,
            'slug' => sanitize_title($search_slug),
            'caption' => sanitize_text_field($search_slug),
            'description' => sanitize_text_field($description),
            'alt' => sanitize_text_field($human_title),
        );
    }

    private function updateDropiAttachmentSearchData($attachment_id, $post_id, $img, $image_name = '')
    {
        if (empty($attachment_id)) {
            return;
        }

        $context = $this->getDropiAttachmentContext($post_id, $img, $image_name);

        $attachment_update = array(
            'ID' => absint($attachment_id),
            'post_parent' => absint($post_id),
            'post_title' => $context['title'],
            'post_name' => $context['slug'],
            'post_excerpt' => $context['caption'],
            'post_content' => $context['description'],
        );

        wp_update_post($attachment_update);

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $context['alt']);
        update_post_meta($attachment_id, '_dropi_attachment_search_slug', $context['slug']);

        if (!empty($context['dropi_product_id'])) {
            update_post_meta($attachment_id, '_dropi_attachment_dropi_product_id', $context['dropi_product_id']);
        }
    }

    private function getDropiImageFilename($img, $image_url, $post_id = 0)
    {
        if (is_array($img)) {
            $img = (object)$img;
        }

        $path = wp_parse_url($image_url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (empty($extension)) {
            $extension = 'jpg';
        }

        $context = $this->getDropiAttachmentContext($post_id, $img);
        $base_name = !empty($context['slug']) ? $context['slug'] : (isset($img->id) ? $img->id : uniqid('dropi_', true));

        return sanitize_file_name($base_name . '.' . strtolower($extension));
    }

    private function getDropiImageSourceId($img)
    {
        if (is_array($img)) {
            $img = (object)$img;
        }

        if (!is_object($img) || !isset($img->id) || $img->id === '') {
            return '';
        }

        return sanitize_text_field((string)$img->id);
    }

    private function getDropiImageSourceKey($img, $image_url = '')
    {
        $source_id = $this->getDropiImageSourceId($img);

        if ($source_id !== '') {
            return 'dropi-id:' . $source_id;
        }

        if ($image_url === '') {
            $image_url = $this->getDropiImageUrl($img);
        }

        if ($image_url === '') {
            return '';
        }

        return 'dropi-url:' . md5($image_url);
    }

    private function normalizeRemoteUrl($url)
    {
        $parts = wp_parse_url($url);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $normalized = $parts['scheme'] . '://';

        if (!empty($parts['user'])) {
            $normalized .= $parts['user'];
            if (!empty($parts['pass'])) {
                $normalized .= ':' . $parts['pass'];
            }
            $normalized .= '@';
        }

        $normalized .= $parts['host'];

        if (!empty($parts['port'])) {
            $normalized .= ':' . $parts['port'];
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        $segments = explode('/', ltrim($path, '/'));
        $segments = array_map(
            function ($segment) {
                return rawurlencode(rawurldecode($segment));
            },
            $segments
        );

        $normalized .= '/' . implode('/', $segments);

        if (isset($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $normalized .= '#' . $parts['fragment'];
        }

        return $normalized;
    }

    /**
     * Obtener Bodega de un producto
     */

    public function getWarehouse()
    {
        $logger = wc_get_logger();
        $endpoint = $this->constants->API_URL . "warehouses/";

        $warehouse = array();
        //$id_token = $this->helper->getToken();
        $tokens = $this->TokenModel->getTokens();
        $id_token = $tokens[0]->token;

        $args = array(
            //'body' => json_encode($data),
            'timeout' => '100000',
            'redirection' => '5',
            'httpversion' => '1.0',
            'method' => 'GET',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json;charset=UTF-8',
                'dropi-integration-key' =>  $id_token,
            ),
            'cookies' => array(),
            'sslverify' => false,
        );
        $response = wp_remote_get(
            $endpoint,
            $args
        );

        $logger->info('las bodegas ', array('source' => 'dropi-products'));
        $logger->info(wc_print_r($response, true), array('source' => 'dropi-products'));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            $this->helper->showAdminNotice($error_message, 'error');
        } else {
            $response_body = (array)json_decode($response['body']);
            if ($response_body['isSuccess'] == false) {

                $this->helper->showAdminNotice($response_body['message'], 'error');
            } else {
                $warehouse  =  $response_body['objects'];
            }
        }

        return $warehouse;
    }

    /**
     * Buscar categorias en Dropi
     */
    public function getCategories()
    {
        $endpoint = $this->constants->API_URL . "categories/";
        $list_categories = array();
        //$id_token = $this->helper->getToken();
        $tokens = $this->TokenModel->getTokens();
        $id_token = $tokens[0]->token;

        $args = array(
            'timeout' => '100000',
            'redirection' => '5',
            'httpversion' => '1.0',
            'method' => 'GET',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json;charset=UTF-8',
                'dropi-integration-key' =>  $id_token,
            ),
            'cookies' => array(),
            'sslverify' => false,
        );

        $response = wp_remote_get(
            $endpoint,
            $args
        );
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            $this->helper->showAdminNotice($error_message, 'error');
        } else {
            $response_body = (array)json_decode($response['body']);
            if ($response_body['isSuccess'] == false) {

                $this->helper->showAdminNotice($response_body['message'], 'error');
            } else {
                $list_categories  =  $response_body['objects'];
            }
        }

        return $list_categories;
    }
}
