<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once(dirname(__DIR__) . '/clasess/views/syncedProdsView.php' );
include_once(dirname(__DIR__) . '/clasess/tables/SyncedProdTable.php' );


class JPIODFW_SyncedInfo
{
    private static $instance;
    private $helper;
    private $synced_info_view;
    private $synced_prods_table;

    // class constructor
    public function __construct()
    {
        $this->helper = JPIODFW_Helper::GetInstance();
    }

    static function GetInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_sub_menues()
    {
        $hook = add_submenu_page(
            'dropi',
            'SyncedInfo',
            'Info Sincronizada',
            'manage_options',
            'synced-prods-dropi',
            array($this, 'synced_prod_render'),
        );


        add_action("load-$hook", [$this, 'synced_info_screen_option']);
    }

    public function synced_prod_render()
    {
        $this->helper->checkRequirementes();
        $this->synced_info_view = new JPIODFW_SyncedProdsView();
        $this->synced_info_view->getSyncedProdsView($this->synced_prods_table);
    }

    public function synced_info_screen_option(){
        $option = 'per_page';
        $args = [
            'label' => 'Info Sincronizada',
            'default' => 5,
            'option' => 'settings_per_page'
        ];

        add_screen_option($option, $args);
        $this->synced_prods_table = new JPIODFW_SyncedProds();
    }

    function my_load_scripts()
    {
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if ($current_page !== 'synced-prods-dropi') {
            return;
        }

        wp_enqueue_script('dropi-sweetalert2', plugin_dir_url(__DIR__) . 'js/sweetalert2@11.js', array('jquery'), date('YmdHis'));
        wp_enqueue_script('dropi-woo-resync-confirm', plugin_dir_url(__DIR__) . 'js/woocommerce-resync-confirm.js', array('jquery', 'dropi-sweetalert2'), date('YmdHis'), true);

        $path = plugins_url('wc-dropi-integration/css/bootstrap.css'); //use your path of course
        $dependencies = array(); //add any depencdencies in array
        $version = false; //or use a version int or string
        wp_enqueue_style('dropi-bootstrap', $path, $dependencies, $version);

        $path = plugins_url('wc-dropi-integration/js/bootstrap.min.js'); //use your path of course
        $dependencies = array(); //add any depencdencies in array
        $version = false; //or use a version int or string
        wp_enqueue_script('dropi-bootstrap', $path, $dependencies, $version);
    }

    public function init(){
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);
        //EL MENU LATERAL
        add_action('admin_menu', array(&$this, 'register_sub_menues'));
        //LOS SCRIPTS
        add_action('admin_enqueue_scripts', array(&$this, 'my_load_scripts'));
    }
}
