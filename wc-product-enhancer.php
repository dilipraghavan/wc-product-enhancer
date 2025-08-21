<?php
    if(!defined('ABSPATH')) exit;

    /**
     * Plugin Name: WC Product Enhancer
     * Description: Plugin to enahnce product display and provide custom pricing rules based on certain conditions.
     * Plugin URI: https://github.com/dilipraghavan/
     * Author: Dilip Raghavan
     * Author URI: https://www.wpshiftstudio.com
     * Text Domain: wc-product-enhancer
     */

    function wc_product_enhancer_load(){
        if(!class_exists('WooCommerce')) 
            return;

        require_once(plugin_dir_path(__FILE__).'includes/class-wc-product-enhancer.php');
        $wc_product_enhancer = new WCProductEnhancer();
    }

    add_action('plugins_loaded','wc_product_enhancer_load');