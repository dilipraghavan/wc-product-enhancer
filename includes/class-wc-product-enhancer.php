<?php

    class WCProductEnhancer{
        public function __construct(){
            //Admin hooks
            add_filter('woocommerce_product_data_tabs', [$this, 'add_bogo_product_data_tab']);
            add_action('woocommerce_product_data_panels', [$this,'add_bogo_product_data_fields']);
            add_action('woocommerce_process_product_meta', [$this, 'save_bogo_product_data']);
            
            //Frontend hooks
            add_filter('woocommerce_product_tabs', [$this, 'add_bogo_product_tab_ui']);
       
            //Cart calculation hooks
            add_action('woocommerce_before_calculate_totals', [$this, 'bogo_before_calculate_totals'], 15); 

        }

        /* --- Admin methods --- */
        public function add_bogo_product_data_tab($tabs){
            $tabs['bogo_tab'] = [
                'label' => 'BOGO Rule',
                'target' => 'bogo-product-data',
                'class' => ['show_if_simple', 'show_if_variable', 'bogo-product-data-style'],
            ];
            return $tabs;           
        }

        public function add_bogo_product_data_fields(){
            $post_id = get_the_ID();

            $product = $post_id ? wc_get_product($post_id) : false;

            echo "<div id='bogo-product-data' class='panel woocommerce_options_panel show_if_simple show_if_variable'>";
            echo '<div class="options_group">'; 
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
                    'value' => $bogo_buy_quantity ?: 0,
                    'description' => 'Quantity customer must buy for BOGO.',
                    'desc_tip' => true,
                    'custom_attributes' => [
                        'min' => 0,
                        'step' => 1
                    ]
                ]
            );

            $bogo_get_quantity = $product ? $product->get_meta('_bogo_get_quantity', true) : get_post_meta($post_id, '_bogo_get_quantity', true);
            woocommerce_wp_text_input(
                [
                    'id' => '_bogo_get_quantity',
                    'label' => 'Get Quantity',
                    'type' => 'number',
                    'value' => $bogo_get_quantity ?: 0,
                    'description' => 'Products customer will get for discount.',
                    'desc_tip' => true,
                    'custom_attributes' => [
                        'min' => 0,
                        'step' => 1
                    ]
                ]
            );

            $bogo_discount = $product ? $product->get_meta('_bogo_discount', true) : get_post_meta($post_id, '_bogo_discount', true);
            woocommerce_wp_text_input(
                [
                    'id' => '_bogo_discount',
                    'label' => 'Discount',
                    'type' => 'number',
                    'value' => $bogo_discount ?: 0,
                    'description' => 'Discount in percentage.',
                    'desc_tip' => true,
                    'custom_attributes' => [
                        'min' => 0,
                        'step' => 0.01
                    ]
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

            $bogo_get_product_id = (int) ($product ? $product->get_meta('_bogo_get_product_id', true) : get_post_meta($post_id, '_bogo_get_product_id', true));

            echo "<p class='form-field _bogo_get_product_id_field'>";
            echo "<label for='_bogo_get_product_id'>Get Product </label>";
            echo "<select 
                    class='wc-product-search'
                    style='width:50%'
                    id='_bogo_get_product_id' 
                    name='_bogo_get_product_id' 
                    data-placeholder='Search for a product...'
                    data-action='woocommerce_json_search_products_and_variations' 
                    data-allow_clear='true'
                    data-exclude='{$post_id}'
                >";

            if ( $bogo_get_product_id ) {
                $p = wc_get_product( $bogo_get_product_id );
                if ( $p ) {
                    echo '<option value="' . esc_attr( $bogo_get_product_id ) . '" selected="selected">' . wp_kses_post( $p->get_formatted_name() ) . '</option>';
                }
            }

            echo '</select>';
            echo '</p>';

            echo "</div></div>";

        }

        public function save_bogo_product_data($post_id){

            if( !isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data'))
                return;
            if(!current_user_can('edit_post', $post_id))
                return;

            $product = wc_get_product($post_id);
            if(!is_a($product, 'WC_Product'))
                return;
            
            $bogo_enabled = isset($_POST['_bogo_rule_enabled']) ? 'yes' : 'no';
            $product->update_meta_data( '_bogo_rule_enabled', $bogo_enabled );

            if(isset($_POST['_bogo_buy_quantity']) ){
                $product->update_meta_data('_bogo_buy_quantity', max(0,absint($_POST['_bogo_buy_quantity']) ));
            }

            if(isset($_POST['_bogo_get_quantity']) ){
                $product->update_meta_data('_bogo_get_quantity', max(0,absint($_POST['_bogo_get_quantity']) ));
            }

            if(isset($_POST['_bogo_discount']) ){
                $product->update_meta_data('_bogo_discount', max(0,floatval($_POST['_bogo_discount']) ));
            }

            if(isset($_POST['_bogo_discount_type']) ){
                $type = sanitize_text_field($_POST['_bogo_discount_type']);
                if(! in_array($type, ['percentage', 'fixed', 'free'] ))
                    $type='percentage';
                $product->update_meta_data('_bogo_discount_type', $type );
            }

            if(isset($_POST['_bogo_get_product_id']) ){
                $selected = absint($_POST['_bogo_get_product_id']);
                if($selected === $post_id)
                    $selected = 0;
                if($selected && !wc_get_product($selected))
                    $selected = 0;
                $product->update_meta_data('_bogo_get_product_id', $selected );
            }

            $product->save();

        }

        /* ---Frontend methods --- */

        public function add_bogo_product_tab_ui($tabs){
            $bogo = $this->check_product_has_bogo();
            if($bogo['valid'] === true){
                $tabs['wpe_bogo'] = [
                    'title' => 'BOGO Offer',
                    'priority' => 20,
                    'callback' => [$this, 'bogo_product_tab_ui_html'],
                ];
            }
            return $tabs;
        }

        private function check_product_has_bogo(){
            global $product; 
            $ref = $product;

            if(! is_a($ref,'WC_Product')){
                $id = get_the_ID();
                $ref = wc_get_product($id);
            }

            $bogo = [
                'valid' => false,
                'enabled' => false,
                'buy_qty' => 0,
                'get_qty' => 0,
                'discount_type' => 'percentage',
                'discount' => 0.0,
                'get_product_id' => 0,
            ];

            if(!$ref){
                return $bogo;
            }

            if($ref->is_type('variation')){
                $ref = wc_get_product( $ref->get_parent_id() );
            }

            if($ref){
                $enabled = $ref->get_meta('_bogo_rule_enabled', true);
                $bogo['enabled'] = $enabled === 'yes' ?  true : false;

                $buy_qty = $ref->get_meta('_bogo_buy_quantity', true);
                $bogo['buy_qty'] = absint($buy_qty);
                
                $get_qty = $ref->get_meta('_bogo_get_quantity', true);
                $bogo['get_qty'] = absint($get_qty);

                $discount_type = $ref->get_meta('_bogo_discount_type', true);
                $possible_discounts = ['percentage', 'fixed', 'free'];
                if(in_array($discount_type, $possible_discounts, true)){
                    $bogo['discount_type'] = $discount_type;
                }else{
                    $bogo['discount_type'] = 'percentage';
                }

                
                $discount = $ref->get_meta('_bogo_discount', true);
                $bogo['discount'] = floatval($discount);
                

                $get_product_id = absint($ref->get_meta('_bogo_get_product_id', true));
                $bogo_product = wc_get_product($get_product_id);

                if( is_a($bogo_product, 'WC_Product') && $bogo_product->get_id() !== $ref->get_id() )
                    $bogo['get_product_id'] = $get_product_id;
                
            } 

            $has_enabled = $bogo['enabled'];
            $has_qtys = $bogo['buy_qty'] >= 1 && $bogo['get_qty'] >= 1;
            $has_target = $bogo['get_product_id'] > 0;
            $has_valid_discount = true;

            if($bogo['discount_type'] === 'percentage'){
                if($bogo['discount'] <= 0 || $bogo['discount'] > 100)
                    $has_valid_discount = false;
            }
            if($bogo['discount_type'] === 'fixed'){
                 if($bogo['discount'] < 0 )
                    $has_valid_discount = false;
            }

            if($has_enabled && $has_qtys && $has_target && $has_valid_discount){
                $bogo['valid'] = true;
            }else{
                $bogo['valid'] = false;
            }

            return $bogo;
        }

        public function bogo_product_tab_ui_html(){

            $bogo = $this->check_product_has_bogo();
            $message = "";
            $get_product = wc_get_product($bogo['get_product_id']);

            if($bogo['valid'] && $get_product){

                $product_name = wp_kses_post($get_product->get_formatted_name());
                $product_url = esc_url(get_permalink($bogo['get_product_id'])); 
                $product_link = "<a href='{$product_url}'>{$product_name}</a>";
                
                switch ($bogo['discount_type']) {
                    case 'percentage':
                        $message = "<p>Offer for {$product_link}. Buy " . esc_html($bogo['buy_qty']) . ", Get " . esc_html($bogo['get_qty']) . " at " . esc_html($bogo['discount'])  . "% off.</p>";
                        break;
                    
                    case 'fixed':
                        $price_html = wc_price($bogo['discount']);
                        $message = "<p>Offer for {$product_link}. Buy " . esc_html($bogo['buy_qty']) . ", Get " . esc_html($bogo['get_qty']) . " at {$price_html} each.</p>";
                        break;

                    case 'free':
                        $message = "<p>Offer for {$product_link}. Buy " . esc_html($bogo['buy_qty']) . ", Get " . esc_html($bogo['get_qty']) .  " free.</p>";
                        break;
                    default:
                        $message = "<p>No offer.</p>";
                        break;
                }
            }else{
                $message = "<p>No offer.</p>";
            }

            echo $message;
        }


        /* --- Cart calculation methods --- */

        public function bogo_before_calculate_totals($cart){
            if(is_admin() && !wp_doing_ajax() )return ;
            if(!$cart || !method_exists($cart,'get_cart')) return;
            if($cart->is_empty()) return;

            $bogo_carrier = [];
            $bogo_target = [];

            foreach ($cart->get_cart() as $cart_item_key => &$cart_item) {
                
                $product_obj = $cart_item['data'];

                unset($cart->cart_contents[$cart_item_key]['wpe_discount_applied']); 
                unset($cart->cart_contents[$cart_item_key]['wpe_discount_units']);

                // If product is not a WC Product continue.
                if( !is_a($product_obj, 'WC_Product')) continue;

                // Get original price
                if( !isset($cart->cart_contents[$cart_item_key]['wpe_original_price'])){
                    $cart->cart_contents[$cart_item_key]['wpe_original_price'] = (float)$product_obj->get_price('edit');
                }

                 // Set the actual price at the begining of each iteration
                $product_obj->set_price((float)$cart->cart_contents[$cart_item_key]['wpe_original_price']);

                // Get parent ID , if product is a variation
                $pid_for_rules = $product_obj->get_id();
                $product_obj_for_rules = $product_obj;

                if($product_obj->is_type('variation')){
                    $pid_for_rules = $product_obj->get_parent_id();
                    $product_obj_for_rules = wc_get_product($pid_for_rules);
                }
               
                $bogo_enabled = (string)$product_obj_for_rules->get_meta('_bogo_rule_enabled', true);
                $bogo_buy_quantity = (int)$product_obj_for_rules->get_meta('_bogo_buy_quantity', true);
                $bogo_get_quantity = (int)$product_obj_for_rules->get_meta('_bogo_get_quantity',true);
                $bogo_discount = floatval($product_obj_for_rules->get_meta('_bogo_discount', true));
                $bogo_discount_type = (string)$product_obj_for_rules->get_meta('_bogo_discount_type', true);
                $bogo_get_product_id = (int)$product_obj_for_rules->get_meta('_bogo_get_product_id', true);

                $bogo_enabled_valid = $bogo_enabled === 'yes' ? true : false;
                $bogo_qty_valid = $bogo_buy_quantity >=1 && $bogo_get_quantity >=1;
                $get_product = wc_get_product($bogo_get_product_id);

                $bogo_product_valid = false;
                $bogo_get_product_valid = false;
                $bogo_get_pid_for_rules = $bogo_get_product_id;
                if(is_a($get_product, 'WC_Product')){
                    $bogo_get_product_valid = true;
                    if($get_product -> is_type('variation')){
                        $bogo_get_pid_for_rules = $get_product-> get_parent_id();
                    }
                    $bogo_product_valid = $pid_for_rules !== $bogo_get_pid_for_rules;
                }

                $bogo_discount_valid = false;
                if($bogo_discount_type === 'percentage'){
                    if($bogo_discount > 0 && $bogo_discount < 100) 
                        $bogo_discount_valid = true;
                }

                if($bogo_discount_type === 'fixed'){
                    if($bogo_discount >= 0) 
                        $bogo_discount_valid = true;
                }

                if($bogo_discount_type === 'free'){
                    $bogo_discount_valid = true;
                }

                if($bogo_enabled_valid && $bogo_qty_valid && $bogo_product_valid && $bogo_get_product_valid && $bogo_discount_valid){
                    $bogo_carrier[$bogo_get_pid_for_rules]['qty'] = ($bogo_carrier[$bogo_get_pid_for_rules]['qty'] ?? 0 ) + (int)$cart_item['quantity'];
                    $bogo_carrier[$bogo_get_pid_for_rules]['buy_qty'] = $bogo_buy_quantity;
                    $bogo_carrier[$bogo_get_pid_for_rules]['get_qty'] = $bogo_get_quantity;
                    $bogo_carrier[$bogo_get_pid_for_rules]['discount'] = $bogo_discount;
                    $bogo_carrier[$bogo_get_pid_for_rules]['discount_type'] = $bogo_discount_type;
                    $bogo_carrier[$bogo_get_pid_for_rules]['get_pid'] = $bogo_get_pid_for_rules;
                }
            }

            foreach ($bogo_carrier as $bogo_cid => $bogo_data) {
                $eligible = ((int)($bogo_data['qty'] / $bogo_data['buy_qty'])) * $bogo_data['get_qty'];
                if($eligible > 0){
                    $target_pid = $bogo_data['get_pid'];
                    $bogo_target[$target_pid]['eligible_units'] = ($bogo_target[$target_pid]['eligible_units'] ?? 0) + $eligible;
                    $bogo_target[$target_pid]['discount'] = $bogo_data['discount'];
                    $bogo_target[$target_pid]['discount_type'] = $bogo_data['discount_type'];
                }
            }

            //Apply BOGO

            if(empty($bogo_target)) return;

            foreach ($cart -> get_cart() as $cart_item_key => &$cart_item) {
                $line_pid =  $cart_item['data']->is_type('variation') ? $cart_item['data']->get_parent_id() : $cart_item['data']->get_id();

                if(!isset($bogo_target[$line_pid])) 
                    continue;

                $units_to_discount = min($cart_item['quantity'], $bogo_target[$line_pid]['eligible_units']);
                if($units_to_discount <= 0)
                    continue;

                $original_price = (float) ( $cart->cart_contents[ $cart_item_key ]['wpe_original_price'] ?? $cart_item['data']->get_price('edit') );
                $bogo_price = $original_price;

                if($bogo_target[$line_pid]['discount_type'] === 'percentage'){
                    $discount = (float)$bogo_target[$line_pid]['discount']; 
                    $bogo_price = max(0, ($original_price * ( 1.0 - $discount/100.0 )));
                }

                if($bogo_target[$line_pid]['discount_type'] === 'fixed'){
                    $discount = (float)$bogo_target[$line_pid]['discount']; 
                    $bogo_price = max(0, $discount);
                }

                if($bogo_target[$line_pid]['discount_type'] === 'free'){
                    $bogo_price = 0.0;
                }

                $bogo_proportion = (float)($units_to_discount / $cart_item['quantity']);
                $new_cart_item_price = (float)($bogo_proportion * $bogo_price) + (( 1.0 - $bogo_proportion) * $original_price); 
                $cart_item['data']->set_price($new_cart_item_price);

                $cart_item['wpe_discount_applied'] = true;
                $cart_item['wpe_discount_units'] = $units_to_discount;

                $bogo_target[$line_pid]['eligible_units'] = $bogo_target[$line_pid]['eligible_units'] - $units_to_discount;
                      
                if($bogo_target[$line_pid]['eligible_units'] <= 0){
                    unset($bogo_target[$line_pid]);
                }

            }

        }
    }

