<?php
namespace WCProductEnhancer\Cart;

class CartManager
{
    public function __construct(){
        add_action('woocommerce_before_calculate_totals', [$this, 'bogo_before_calculate_totals'], 15); 
    }

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