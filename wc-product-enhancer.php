<?php
    if(!defined('ABSPATH')) exit;

    /**
     * Plugin Name: WC Product Enhancer
     * Description: Plugin to enahnce product display and provide custom pricing rules based on certain conditions.
     * Plugin URI: https://github.com/dilipraghavan/wc-product-enhancer
     * Author: Dilip Raghavan
     * Author URI: https://www.wpshiftstudio.com
     * Text Domain: wc-product-enhancer
     */

    require_once __DIR__ . '/vendor/autoload.php';

    function wc_product_enhancer_load_textdomain() {
        load_plugin_textdomain('wc-product-enhancer', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    add_action('plugins_loaded', 'wc_product_enhancer_load_textdomain');

    function wc_product_enhancer_load(){
        if(!class_exists('WooCommerce')) 
            return;

        $wc_product_enhancer = new \WCProductEnhancer\WCProductEnhancer();
    }
    add_action('plugins_loaded','wc_product_enhancer_load');