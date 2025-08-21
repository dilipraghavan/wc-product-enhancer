<?php

    class WCProductEnhancer{
        public function __construct(){
            add_filter('woocommerce_product_data_tabs', [$this, 'add_bogo_product_data_tab']);
            add_action('woocommerce_product_data_panels', [$this,'add_bogo_product_data_fields']);
            add_action('woocommerce_process_product_meta', [$this, 'save_bogo_product_data']);
            add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts'] );
            
        }

        public function add_bogo_product_data_tab($tabs){
            $tabs['bogo_tab'] = [
                'label' => 'BOGO Rule',
                'target' => 'bogo-product-data',
                'class' => ['bogo-product-data-style'],
            ];
            return $tabs;           
        }

        public function add_bogo_product_data_fields($post_id){

            $product = $post_id ? wc_get_product($post_id) : false;

            echo "<div id='bogo-product-data' class='panel woocommerce_options_panel'>";

            $bogo_rule_enabled = $product ? $product->get_meta('_bogo_rule_enabled', true) : get_post_meta($post_id, '_bogo_rule_enabled', true);
            woocommerce_wp_checkbox(
                [
                    'id' => '_bogo_rule_enabled',
                    'label' => 'Enabled Bogo Rule',
                    'value' => $bogo_rule_enabled === 'yes' ? 'yes' : 'no',
                    'cbvalue' => 'yes',
                    'wrapper_class' => 'bogo-checkbox'
                ]
            );

            $bogo_buy_quantity = $product ? $product->get_meta('_bogo_buy_quantity', true) : get_post_meta($post_id, '_bogo_buy_quantity', true);
            woocommerce_wp_text_input(
                [
                    'id' => '_bogo_buy_quantity',
                    'label' => 'Buy Quantity',
                    'type' => 'number',
                    'value' => $bogo_buy_quantity ?? 0,
                    'description' => 'Quantity customer must buy for BOGO.',
                    'desc_tip' => true,
                ]
            );

            $bogo_get_quantity = $product ? $product->get_meta('_bogo_get_quantity', true) : get_post_meta($post_id, '_bogo_get_quantity', true);
            woocommerce_wp_text_input(
                [
                    'id' => '_bogo_get_quantity',
                    'label' => 'Get Quantity',
                    'type' => 'number',
                    'value' => $bogo_get_quantity ?? 0,
                    'description' => 'Products customer will get for discount.',
                    'desc_tip' => true
                ]
            );

            $bogo_discount = $product ? $product->get_meta('_bogo_discount', true) : get_post_meta($post_id, '_bogo_discount', true);
            woocommerce_wp_text_input(
                [
                    'id' => '_bogo_discount',
                    'label' => 'Discount',
                    'type' => 'number',
                    'value' => $bogo_discount ?? 0,
                    'description' => 'Discount in percentage.',
                    'desc_tip' => true
                ]
            );

            $bogo_discount_type = $product ? $product->get_meta('_bogo_discount_type', true) : get_post_meta($post_id, '_bogo_discount_type', true);
            woocommerce_wp_select(
                [
                    'id' => '_bogo_discount_type',
                    'label' => 'Discount Type',
                    'options' => [
                        'percentage' => 'Percentage Discount',
                        'fixed' => 'Fixed Price',
                        'free' => 'Free Product',
                    ],
                    'value' => $bogo_discount_type,
                ]
            );

            $bogo_get_product_id = $product ? $product -> get_meta('_bogo_get_product_id', true) : get_post_meta($post_id, '_bogo_get_product_id', true);
            woocommerce_wp_hidden_input(
                [
                    'id' => '_bogo_get_product_id',
                    'value' => $bogo_get_product_id,
                ]
            );
            
            echo "<p>";
            echo "<label for = '_bogo_get_product_id'>Get Product</label>";
            echo "<span class = 'bogo-get-product-name'>"; 
            $bogo_get_product = $bogo_get_product_id ? wc_get_product($bogo_get_product_id) : false;
            if($bogo_get_product){
                echo esc_html($bogo_get_product->get_formatted_name());
            }
            echo "</span>";
            echo "<button class='bogo-get-product-button'>Select Product</button>";
            echo "</p>";

            echo "</div>";

        }

        public function save_bogo_product_data($post_id){

            if( !isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce( sanitize_text_field($_POST['woocommerce_meta_nonce']), 'woocommerce_save_data'))
                return;
            if(!current_user_can('edit_product', $post_id))
                return;

            $product = wc_get_product($post_id);
            if(!is_a($product, 'WC_Product'))
                return;
            
            $bogo_enabled = isset($_POST['_bogo_rule_enabled']) ? 'yes' : 'no';
            $product->update_meta_data( '_bogo_rule_enabled', $bogo_enabled );

            if(isset($_POST['_bogo_buy_quantity']) ){
                $product->update_meta_data('_bogo_buy_quantity', intval($_POST['_bogo_buy_quantity']) );
            }

            if(isset($_POST['_bogo_get_quantity']) ){
                $product->update_meta_data('_bogo_get_quantity', intval($_POST['_bogo_get_quantity']) );
            }

            if(isset($_POST['_bogo_discount']) ){
                $product->update_meta_data('_bogo_discount', floatval($_POST['_bogo_discount']) );
            }

            if(isset($_POST['_bogo_discount_type']) ){
                $product->update_meta_data('_bogo_discount_type', sanitize_text_field($_POST['_bogo_discount_type']) );
            }

            if(isset($_POST['_bogo_get_product_id']) ){
                $product->update_meta_data('_bogo_get_product_id', intval($_POST['_bogo_get_product_id']) );
            }

            $product->save();

        }

        public function load_admin_scripts(){

            $screen = get_current_screen();
            if($screen && $screen -> id === 'product'){

                $plugin_base_url = plugin_dir_url( dirname(__FILE__) );
                $js_file_url = $plugin_base_url . 'assets/js/bogo-admin.js';

                $plugin_base_path = plugin_dir_path( dirname(__FILE__) );
                $js_file_path = $plugin_base_path . 'assets/js/bogo-admin.js';

                wp_enqueue_script( 
                                'bogo_admin_script',
                                $js_file_url,
                                ['wc-enhanced-select'],
                                filemtime($js_file_path),
                                true 
                            );
            }
        }

    }

