<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
include_once(dirname(__DIR__) . '/Constants.php');
include_once(dirname(__DIR__) . '/Helper.php');
include_once(dirname(__DIR__) . '/models/TokenModel.php');


class JPIODFW_OrdersModel
{
    private $helper;
    private $constants;
    public $tokenModel;

    public $logger;
    private static $instance;
    /*......*/

    /*......*/
    // class constructor
    public function __construct()
    {
        $this->helper = JPIODFW_Helper::GetInstance();
        $this->constants = JPIODFW_Constants::GetInstance();
        $this->logger = wc_get_logger();
        $this->tokenModel = JPIODFW_TokenModel::GetInstance();
    }


    static function GetInstance()
    {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function decodeDropiMeta($raw_value)
    {
        if (empty($raw_value)) {
            return null;
        }

        $decoded = maybe_unserialize($raw_value);

        if (is_object($decoded) || is_array($decoded)) {
            return $decoded;
        }

        if (is_string($raw_value)) {
            $json = json_decode($raw_value);

            if (is_object($json) || is_array($json)) {
                return $json;
            }
        }

        return null;
    }

    private function normalizeDropiValue($value)
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = wp_strip_all_tags((string) $value);
        $value = remove_accents($value);
        $value = strtoupper(trim($value));

        return $value;
    }

    private function findDropiVariationFromProduct($dropi_product, $variation_id)
    {
        if (empty($variation_id) || empty($dropi_product)) {
            return null;
        }

        if (is_array($dropi_product)) {
            $dropi_product = (object) $dropi_product;
        }

        if (!is_object($dropi_product) || empty($dropi_product->variations) || !is_array($dropi_product->variations)) {
            return null;
        }

        $stored_dropi_variation_id = absint(get_post_meta($variation_id, '_dropi_variation_id', true));
        $variation_sku = $this->normalizeDropiValue(get_post_meta($variation_id, '_sku', true));
        $variation_product = wc_get_product($variation_id);
        $variation_attributes = array();

        if ($variation_product && is_a($variation_product, 'WC_Product_Variation')) {
            foreach ($variation_product->get_variation_attributes() as $attribute_value) {
                $normalized_value = $this->normalizeDropiValue($attribute_value);
                
                if ($normalized_value !== '') {
                    $variation_attributes[] = $normalized_value;
                }
            }
        }

        sort($variation_attributes);

        foreach ($dropi_product->variations as $candidate_variation) {
            if (is_array($candidate_variation)) {
                $candidate_variation = (object) $candidate_variation;
            }

            if (!is_object($candidate_variation)) {
                continue;
            }

            if ($stored_dropi_variation_id > 0 && isset($candidate_variation->id) && absint($candidate_variation->id) === $stored_dropi_variation_id) {
                return $candidate_variation;
            }

            $candidate_sku = isset($candidate_variation->sku) ? $this->normalizeDropiValue($candidate_variation->sku) : '';
            
            if (!empty($variation_sku) && !empty($candidate_sku) && $variation_sku === $candidate_sku) {
                return $candidate_variation;
            }

            if (!empty($variation_attributes) && isset($candidate_variation->attribute_values) && is_array($candidate_variation->attribute_values)) {
                $candidate_attributes = array();

                foreach ($candidate_variation->attribute_values as $attribute_value) {
                    if (is_object($attribute_value) && isset($attribute_value->value)) {
                        $normalized_value = $this->normalizeDropiValue($attribute_value->value);
                    } elseif (is_array($attribute_value) && isset($attribute_value['value'])) {
                        $normalized_value = $this->normalizeDropiValue($attribute_value['value']);
                    } else {
                        $normalized_value = '';
                    }

                    if ($normalized_value !== '') {
                        $candidate_attributes[] = $normalized_value;
                    }
                }

                sort($candidate_attributes);

                if (!empty($candidate_attributes) && $candidate_attributes === $variation_attributes) {
                    return $candidate_variation;
                }
            }
        }

        return null;
    }

    private function getDropiVariationStock($dropi_variation)
    {
        if (empty($dropi_variation) || (!is_object($dropi_variation) && !is_array($dropi_variation))) {
            return null;
        }

        $variation = is_array($dropi_variation) ? (object) $dropi_variation : $dropi_variation;
        $stock = 0;
        $found_stock = false;

        if (isset($variation->warehouse_product_variation) && is_array($variation->warehouse_product_variation)) {
            foreach ($variation->warehouse_product_variation as $warehouse_stock) {
                if (is_array($warehouse_stock) && isset($warehouse_stock['stock'])) {
                    $stock += intval($warehouse_stock['stock']);
                    $found_stock = true;
                } elseif (is_object($warehouse_stock) && isset($warehouse_stock->stock)) {
                    $stock += intval($warehouse_stock->stock);
                    $found_stock = true;
                }
            }
        }

        if ($found_stock) {
            return max(0, $stock);
        }

        if (isset($variation->stock)) {
            return max(0, intval($variation->stock));
        }

        return null;
    }

    private function getDropiGroupOrderId($order_id, $supplier_id, $group_count)
    {
        $base_order_id = (string) absint($order_id);

        if ($group_count > 1 && !empty($supplier_id)) {
            return $base_order_id . '-' . absint($supplier_id);
        }

        return $base_order_id;
    }

    private function extractDropiOrderId($response_body)
    {
        if (!is_array($response_body)) {
            return '';
        }

        if (isset($response_body['objects'])) {
            $objects = $response_body['objects'];

            if (is_object($objects) && isset($objects->id)) {
                return (string) $objects->id;
            }

            if (is_array($objects)) {
                if (isset($objects['id'])) {
                    return (string) $objects['id'];
                }

                if (isset($objects[0])) {
                    $first_object = $objects[0];
                    if (is_object($first_object) && isset($first_object->id)) {
                        return (string) $first_object->id;
                    }
                    if (is_array($first_object) && isset($first_object['id'])) {
                        return (string) $first_object['id'];
                    }
                }
            }
        }

        if (isset($response_body['object'])) {
            $object = $response_body['object'];

            if (is_object($object) && isset($object->id)) {
                return (string) $object->id;
            }

            if (is_array($object) && isset($object['id'])) {
                return (string) $object['id'];
            }
        }

        return '';
    }

    private function getStoredDropiOrderGroupMap($order)
    {
        $raw_map = $order->get_meta('_dropi_order_group_map', true);

        if (is_array($raw_map)) {
            return $raw_map;
        }

        if (is_string($raw_map) && $raw_map !== '') {
            $decoded_map = json_decode($raw_map, true);
            if (is_array($decoded_map)) {
                return $decoded_map;
            }

            $unserialized_map = maybe_unserialize($raw_map);
            if (is_array($unserialized_map)) {
                return $unserialized_map;
            }
        }

        return array();
    }

    private function makeProductsArray($order)
    {
        $create_product_if_not_exist = sanitize_text_field(get_option('dropi-woocomerce-create_product_if_no_exist'));
        $notes = '';
        $products = [];
        $total = 0;
        $logger = wc_get_logger();
        $shipping = $order->get_total_shipping();

        $taxes = $order->get_items('tax');
        $total_taxes = 0;

        foreach ($taxes as $tax) {
            $total_taxes += $tax->get_tax_total();
        }

        $fee = 0; // extra fee onpayuments methods

        $paymentMethod = $order->get_payment_method();
        $payment_gateway = WC()->payment_gateways->payment_gateways()[$paymentMethod];

        global $wpdb;
        $table_name = $wpdb->base_prefix . 'dropi_tokens';
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));

        if (!$wpdb->get_var($query) == $table_name) {

            JPIODFW_DropiTokensTable::create_dropi_tokens_table();
            $token = sanitize_text_field(get_option('dropi-woocomerce-token'));
            $sync = get_option('dropi-woocomerce-autosync_orders');
            $create_prod = get_option('dropi-woocomerce-create_product_if_no_exist');
            $sync_token = '';

            if ($sync == 1) {
                $sync_token = $this->constants->SINC_AUTOM;
            } else {
                $sync_token = $this->constants->SINC_MANUAL;
            }
            $this->tokenModel->setNewToken($token, 'Tienda 1', $sync_token, $create_prod);
            $imported_products = $this->tokenModel->getOldImportedProducts();
            foreach ($imported_products as $product) {
                update_post_meta($product->post_id, '_dropi_token_store', 'Tienda 1');
                update_post_meta($product->post_id, '_dropi_token', $token);
            }
        }

        if (is_object($payment_gateway) && isset($payment_gateway->settings) && isset($payment_gateway->settings['fee'])) {
            $fee = $payment_gateway->settings['fee'];
            $shipping = $shipping + $fee;
            $logger->info('new shipping + fee' . $shipping, array('source' => 'dropi-orders'));
        }

        $order_items = $order->get_items();


        $contadordeproductosdropi = 0;
        foreach ($order_items as $item_key => $item) {
            $item = (object) $item;
            $item_data = $item->get_data();
            $product_id = $item_data['product_id'];
            $dropi_product = get_post_meta($product_id, '_dropi_product', true);


            $logger->info('_dropi_product ', array('source' => 'dropi-orders'));
            $logger->info(print_r($dropi_product, true), array('source' => 'dropi-orders'));


            if (!empty($dropi_product)) {
                $dropi_product = $this->decodeDropiMeta($dropi_product);
            }

            if ((!empty($dropi_product) && (is_object($dropi_product) || is_array($dropi_product))) || $create_product_if_not_exist === '1') {
                $contadordeproductosdropi++;
            }
        }

        $logger->info('contador de productos: ', array('source' => 'dropi-orders'));
        $logger->info($contadordeproductosdropi, array('source' => 'dropi-orders'));

        if ($contadordeproductosdropi > 0) {

            $amountToAdd = (floatval($shipping) + floatval($total_taxes)) / $contadordeproductosdropi;
            $logger->info('amountToAdd', array('source' => 'dropi-orders'));
            $logger->info(print_r($amountToAdd, true), array('source' => 'dropi-orders'));
        }


        foreach ($order_items as $item_key => $item) {

            $item_name = $item->get_name(); // Name of the product*/
            $quantity = $item->get_quantity();
            $item_data = $item->get_data();
            $product_id = $item_data['product_id'];
            $variation_id = $item_data['variation_id'];
            $item_total = $item->get_total();
            $logger->info(print_r($item_data, true), array('source' => 'dropi-orders'));
            //var_dump( $product_id);
            $dropi_product = get_post_meta($product_id, '_dropi_product', true);
            $token_product = get_post_meta($product_id, '_dropi_token', true);

            //var_dump( $dropi_product);

            if (empty($dropi_product) && $create_product_if_not_exist === '1') {
                /**entonces creo un objecto por defecto */
                $dropi_product = (object) ['name' => $item_name];
                $dropi_product->name = $item_name;
                $token_product = $this->tokenModel->getTokens()[0]->token; // here I have to bring a token to create the product in Dropi... how to know if its supplier and i can create product?
            } else {

                $dropi_product = $this->decodeDropiMeta($dropi_product);
            }

            if (!empty($variation_id)) {
                $notes .= ' -- ' . $item_name . ": " . $this->get_variation_data_from_variation_id($variation_id);
            }


            if ((!empty($dropi_product) && (is_object($dropi_product) || is_array($dropi_product))) || $create_product_if_not_exist === '1') {

                $subtotalpreciolinea = $amountToAdd;
                $item_total = $item_total + $subtotalpreciolinea;

                $dropi_variation = null;
                if (!empty($variation_id)) {
                    $dropi_variation = $this->decodeDropiMeta(get_post_meta($variation_id, '_dropi_variation', true));
                    
                    if (empty($dropi_variation) && !empty($dropi_product) && isset($dropi_product->type) && $dropi_product->type == 'VARIABLE') {
                        $dropi_variation = $this->findDropiVariationFromProduct($dropi_product, $variation_id);

                        if (!empty($dropi_variation)) {
                            update_post_meta($variation_id, '_dropi_variation', serialize($dropi_variation));
                            
                            if (isset($dropi_variation->id)) {
                                update_post_meta($variation_id, '_dropi_variation_id', absint($dropi_variation->id));
                            }
                        }
                    }
                }

                $dropi_product->name = $item_name;
                $dropi_product->quantity = intval($quantity);
                $dropi_product->stock = isset($dropi_product->stock) ? intval($dropi_product->stock) : 0;
                $dropi_product->price = floatval($item_total / $quantity);
                $dropi_product->token = $token_product;

                if (
                    isset($dropi_product->type) &&
                    $dropi_product->type == 'VARIABLE' &&
                    !empty($dropi_variation) &&
                    (is_object($dropi_variation) || is_array($dropi_variation))
                ) {
                    $variation_stock = $this->getDropiVariationStock($dropi_variation);

                    if ($variation_stock !== null) {
                        $dropi_product->stock = $variation_stock;
                    }

                    if (isset($dropi_variation->id)) {
                        $dropi_product->variation_id = $dropi_variation->id;
                    }
                }

                $total = $total + ($dropi_product->price * $dropi_product->quantity);

                $logger->info('1 - variacion: ' . $variation_id . " " . $dropi_product->name, array('source' => 'dropi-orders'));
                $logger->info(print_r($dropi_variation, true), array('source' => 'dropi-orders'));
                $logger->info("Serialized data: " . var_export($dropi_variation, true), array('source' => 'dropi-orders'));

                if ($dropi_product->type == 'VARIABLE' && !empty($dropi_variation) && (is_object($dropi_variation) || is_array($dropi_variation))) {
                    $dropi_product->variation_id = $dropi_variation->id;
                }




                $products[] = $dropi_product;
                $logger->info($total, array('source' => 'dropi-orders'));
            }
        }

        return ['products' => $products, 'notes' => $notes, 'total' => $total];
    }



    private function getVariationData($id)
    {
        $dropi_product = get_post_meta($id, '_dropi_variation', true);
        return $dropi_product;
    }


    /**obtener datos de un porducto variable */
    private function get_variation_data_from_variation_id($item_id)
    {
        $_product = new WC_Product_Variation($item_id);
        $variation_data = $_product->get_variation_attributes();
        $variation_detail = woocommerce_get_formatted_variation($variation_data, true); // this will give all variation detail in one line
        // $variation_detail = woocommerce_get_formatted_variation( $variation_data, false);  // this will give all variation detail one by one
        return $variation_detail; // $variation_detail will return string containing variation detail which can be used to print on website
        // return $variation_data; // $variation_data will return only the data which can be used to store variation data
    }
    /**
     * Creo una orden
     */
    public function save($order)
    {
        $result = false;
        try {
            $existing_dropi_order_id = trim((string) $order->get_meta('_dropi_order_id', true));
            $existing_dropi_status = trim((string) $order->get_meta('_is_dropi_order', true));
            $existing_group_map = $this->getStoredDropiOrderGroupMap($order);
            $existing_dropi_order_ids = array_values(array_filter(array_map('trim', explode(',', $existing_dropi_order_id))));
            $consumed_existing_dropi_order_ids = array();

            if (!empty($existing_dropi_order_id) && $existing_dropi_status === 'Yes') {
                return true;
            }

            $makeProductsArray = $this->makeProductsArray($order);

            //solo si tengo productos que sean de dropi
            if (sizeof($makeProductsArray['products']) > 0) {
                $listProducts = $makeProductsArray['products'];
                $grouped_products = array();

                foreach ($listProducts as $item) {
                    $item_token = isset($item->token) ? $item->token : '';
                    $supplier_id = isset($item->user_id) ? absint($item->user_id) : 0;
                    $group_key = md5($item_token . '|' . $supplier_id);

                    if (!isset($grouped_products[$group_key])) {
                        $grouped_products[$group_key] = array(
                            'token' => $item_token,
                            'supplier_id' => $supplier_id,
                            'products' => array(),
                            'total' => 0,
                        );
                    }

                    $grouped_products[$group_key]['products'][] = $item;
                    $grouped_products[$group_key]['total'] += (floatval(isset($item->price) ? $item->price : 0) * intval(isset($item->quantity) ? $item->quantity : 0));
                }

                $order_data = $order->get_data(); // The Order data

                $logger = wc_get_logger();
                // LOG ORDER TO CUSTOM "dropi-orders" LOG
                // $logger->info(wc_print_r($makeProductsArray, true), array('source' => 'dropi-orders'));

                $endpoint = $this->constants->API_URL . "orders/myorders";

                $order_type = $this->constants->SIN_RECAUDO;
                $paymentMethod = $order->get_payment_method();
                //VALIDO SI EL METODO DE PAGO ES CONTRA ENTREGA
                if (in_array($paymentMethod, array('cod'))) {
                    $order_type = $this->constants->CON_RECAUDO;
                }
                $this->logger->info('estado', array('source' => 'dropi-orders'));

                $logger->info(wc_print_r($order_data['shipping']['state'], true), array('source' => 'dropi-orders'));
                $create_product_if_not_exist = sanitize_text_field(get_option('dropi-woocomerce-create_product_if_no_exist'));
                $shop_country = wc_get_base_location()['country'];

                $address = !empty($order_data['shipping']['address_1']) ? $order_data['shipping']['address_1'] : $order_data['billing']['address_1'];
                $address .= ' ';
                $address .= !empty($order_data['shipping']['address_2']) ? $order_data['shipping']['address_2'] : $order_data['billing']['address_2'];

                $data = array(
                    //'id' => 'idorden',
                    "total_order" => $makeProductsArray['total'],
                    "notes" => $order_data['customer_note'] . $makeProductsArray['notes'],
                    "name" => !empty($order_data['shipping']['first_name']) ? $order_data['shipping']['first_name'] : $order_data['billing']['first_name'],
                    "surname" => !empty($order_data['shipping']['last_name']) ? $order_data['shipping']['last_name'] : $order_data['billing']['last_name'],
                    "dir" => $address,
                    "country" => !empty($order_data['shipping']['country']) ? $order_data['shipping']['country'] : $order_data['billing']['country'],
                    //todo traer 
                    "state" => !empty($order_data['shipping']['state']) ? $order_data['shipping']['state'] : $order_data['billing']['state'],
                    //todo traer 
                    "city" => !empty($order_data['shipping']['city']) ? $order_data['shipping']['city'] : $order_data['billing']['city'],
                    //todo traer 
                    "phone" => !empty($order_data['shipping']['phone']) ? $order_data['shipping']['phone'] : $order_data['billing']['phone'],
                    "client_email" => !empty($order_data['shipping']['email']) ? $order_data['shipping']['email'] : $order_data['billing']['email'],
                    "payment_method_id" => 1,
                    "status" => $this->constants->STATUS_BORRADOR,
                    "type" => "FINAL_ORDER",
                    "rate_type" => $order_type,
                    "products" => $makeProductsArray['products'],
                    "calculate_costs_and_shiping" => true,
                    "supplier_id" => $makeProductsArray['products'][0]->user_id,
                    'shop_order_id' => $order->get_id(),
                    "create_product_if_not_exist" => $create_product_if_not_exist === '1' ? true : false,
                );

                if ($shop_country == 'MX') {
                    $data['zip_code'] = !empty($order_data['shipping']['postcode']) ? $order_data['shipping']['postcode'] : $order_data['billing']['postcode'];
                }

                $logger->info(wc_print_r('Creating dropi order ' . $order->get_id(), true), array('source' => 'dropi-orders'));
                $logger->info(print_r($grouped_products, true), array('source' => 'dropi-orders'));

                $created_dropi_orders = array();
                $group_errors = array();
                $group_count = count($grouped_products);

                foreach ($grouped_products as $group_key => $group) {
                    $id_token = $group['token'];
                    $supplier_id = $group['supplier_id'];

                    if (!empty($supplier_id) && !empty($existing_group_map[$supplier_id])) {
                        $created_dropi_orders[] = $existing_group_map[$supplier_id];
                        continue;
                    }

                    if (empty($id_token) || empty($supplier_id)) {
                        $group_errors[] = 'No se encontro token o supplier_id valido para uno de los grupos del pedido.';
                        continue;
                    }

                    $data['total_order'] = $group['total'];
                    $data['products'] = $group['products'];
                    $data['supplier_id'] = $supplier_id;
                    $data['shop_order_id'] = $this->getDropiGroupOrderId($order->get_id(), $supplier_id, $group_count);

                    $logger->info(print_r($data, true), array('source' => 'dropi-orders'));
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
                        $group_errors[] = $error_message;
                        $logger->error(wc_print_r('Error creating dropi order - wp_error ' . $order->get_id(), true), array('source' => 'dropi-orders'));
                        $logger->error(wc_print_r($error_message, true), array('source' => 'dropi-orders'));
                        continue;
                    }

                    $response_body = (array) json_decode($response['body']);
                    $logger->info('Dropi response for group ' . $data['shop_order_id'], array('source' => 'dropi-orders'));
                    $logger->info(print_r($response_body, true), array('source' => 'dropi-orders'));

                    if (!isset($response_body['isSuccess']) || $response_body['isSuccess'] == false) {
                        $message = isset($response_body['message']) ? $response_body['message'] : '';
                        $error_detail = isset($response_body['error']) ? $response_body['error'] : '';
                        if (empty($message) && isset($response_body['status'])) {
                            $message = $response_body['status'];
                        }
                        if (empty($message) && !empty($error_detail)) {
                            $message = $error_detail;
                        }

                        if (!empty($error_detail) && stripos($message, 'Error al crear la orden') !== false) {
                            $message = $error_detail;
                        }

                        $is_duplicate_order = stripos($message . ' ' . $error_detail, 'ya fue enviada') !== false;
                        if ($is_duplicate_order && !empty($existing_dropi_order_ids)) {
                            $mapped_dropi_order_ids = array_values(array_filter($existing_group_map));
                            foreach ($existing_dropi_order_ids as $existing_id) {
                                if (
                                    !in_array($existing_id, $consumed_existing_dropi_order_ids, true) &&
                                    !in_array($existing_id, $mapped_dropi_order_ids, true)
                                ) {
                                    $existing_group_map[$supplier_id] = $existing_id;
                                    $created_dropi_orders[] = $existing_id;
                                    $consumed_existing_dropi_order_ids[] = $existing_id;
                                    continue 2;
                                }
                            }
                        }

                        $group_errors[] = $message;
                        $logger->error(wc_print_r('Error creating dropi order ' . $order->get_id() . " " . $message, true), array('source' => 'dropi-orders'));
                        $logger->error(wc_print_r((array) $order, true), array('source' => 'dropi-orders'));
                        $logger->error(wc_print_r($response_body, true), array('source' => 'dropi-orders'));
                        if (isset($response_body['file'])) {
                            $logger->error(wc_print_r($response_body['file'], true), array('source' => 'dropi-orders'));
                        }
                        if (isset($response_body['line'])) {
                            $logger->error(wc_print_r($response_body['line'], true), array('source' => 'dropi-orders'));
                        }
                        continue;
                    }

                    $created_dropi_order_id = $this->extractDropiOrderId($response_body);
                    if (!empty($created_dropi_order_id)) {
                        $created_dropi_orders[] = $created_dropi_order_id;
                        $existing_group_map[$supplier_id] = $created_dropi_order_id;
                        $consumed_existing_dropi_order_ids[] = $created_dropi_order_id;
                    }
                }

                if (!empty($existing_group_map)) {
                    $order->update_meta_data('_dropi_order_group_map', wp_json_encode($existing_group_map));
                }

                $all_dropi_order_ids = array_unique(array_filter(array_merge($existing_dropi_order_ids, $created_dropi_orders)));

                if (!empty($group_errors)) {
                    $error_message = implode(' | ', array_filter($group_errors));
                    $result = __('Error creating order, ' . $error_message);
                    $order->update_meta_data('_is_dropi_order', __('Dropi sync error: ' . $error_message, 'dropi'));

                    if (!empty($all_dropi_order_ids)) {
                        $order->update_meta_data('_dropi_order_id', implode(',', $all_dropi_order_ids));
                    }
                } else {
                    $order->update_meta_data('_is_dropi_order', 'Yes');
                    if (!empty($all_dropi_order_ids)) {
                        $order->update_meta_data('_dropi_order_id', implode(',', $all_dropi_order_ids));
                    }

                    $result = true;
                    $logger->info(wc_print_r(__('Dropi order created ', 'wc-dropi-integration') . " " . $order->get_id(), true), array('source' => 'dropi-orders'));
                }
            } else {
                $order->update_meta_data('_is_dropi_order', __('This order do not have dropi products', 'wc-dropi-integration'));

                $result = __('This order do not have dropi products', 'wc-dropi-integration');

                // $this->helper->showAdminNotice(__('This order do not have dropi products', 'wc-dropi-integration') , 'warning'); 
            }
            $order->save();
        } catch (Exception $e) {

            $result = 'Error';

            echo $e->getMessage();
            $this->logger->error(wc_print_r($e, true), array('source' => 'dropi-orders'));
        }

        return $result;
    }
}
