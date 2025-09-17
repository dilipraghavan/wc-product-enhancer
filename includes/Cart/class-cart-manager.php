<?php
namespace WCProductEnhancer\Cart;

class CartManager
{
    public function __construct(){
        add_action('woocommerce_before_calculate_totals', [$this, 'bogo_before_calculate_totals'], 15); 
        add_filter('woocommerce_cart_item_name', [$this, 'display_bogo_discount_label_on_cart'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'display_bogo_discount_label_on_checkout'], 10, 4);
    }

    public function bogo_before_calculate_totals($cart){
        if(is_admin() && !wp_doing_ajax() )return ;
        if(!$cart || !method_exists($cart,'get_cart')) return;
        if($cart->is_empty()) return;

        $bogo_carrier = [];
        $bogo_target = [];

        $cart_contents = &$cart->cart_contents;

        //First loop: Reset all items before applying rules
        foreach ($cart_contents as $cart_item_key => &$cart_item) {
            // Unset previous discount flags
            unset($cart_item['wpe_discount_applied']); 
            unset($cart_item['wpe_discount_units']);

            // Reset the price to its original value to avoid stacking discounts
            if (isset($cart_item['wpe_original_price'])) {
                $cart_item['data']->set_price((float)$cart_item['wpe_original_price']);
            } else {
                // Store the original price if not already set
                $cart_item['wpe_original_price'] = (float)$cart_item['data']->get_price('edit');
            }
        }

        // Second loop: Collect bogo rules 
        foreach ($cart_contents as $cart_item_key => &$cart_item) {
            
            $product_obj = $cart_item['data'];

            // If product is not a WC Product continue.
            if( !is_a($product_obj, 'WC_Product')) continue;

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

        if(empty($bogo_target)) return;
        
        //Third Loop : Apply BOGO
        foreach ($cart_contents as $cart_item_key => &$cart_item) {
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

    public function display_bogo_discount_label_on_cart($item_name, $cart_item, $cart_item_key){
        if (isset($cart_item['wpe_discount_applied']) && $cart_item['wpe_discount_applied']) {
            $item_name .= '<div class="bogo-applied-label" style="font-size: 0.8em; color: green; margin-top: 5px;">' . __('BOGO Discount Applied!', 'wc-product-enhancer') . '</div>';
        }
        return $item_name;
    }

    public function display_bogo_discount_label_on_checkout($item, $cart_item_key, $values, $order){
        if (isset($values['wpe_discount_applied']) && $values['wpe_discount_applied']) {
            $item->add_meta_data(
                __('BOGO Discount', 'wc-product-enhancer'),
                __('Applied', 'wc-product-enhancer')
            );
        }
    }
}