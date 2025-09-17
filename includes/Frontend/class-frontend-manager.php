<?php
namespace WCProductEnhancer\Frontend;

class FrontendManager
{
    public function __construct(){
        add_filter('woocommerce_product_tabs', [$this, 'add_bogo_product_tab_ui']);
    }

    public function add_bogo_product_tab_ui($tabs){
        $bogo = $this->check_product_has_bogo();
        if($bogo['valid'] === true){
            $tabs['wpe_bogo'] = [
                'title' => __('BOGO Offer', 'wc-product-enhancer'),
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

            $product_name = wp_kses_post($get_product->get_name());
            $product_url = esc_url(get_permalink($bogo['get_product_id'])); 
            $product_link = "<a href='{$product_url}'>{$product_name}</a>";
            
            switch ($bogo['discount_type']) {
                case 'percentage':
                    $message = sprintf(
                        __('Offer for %1$s. Buy %2$s, Get %3$s at %4$s%% off.', 'wc-product-enhancer'),
                        $product_link,
                        esc_html($bogo['buy_qty']),
                        esc_html($bogo['get_qty']),
                        esc_html($bogo['discount'])
                    );
                    break;
                
                case 'fixed':
                    $price_html = wc_price($bogo['discount']); 
                    $message = sprintf(
                        __('Offer for %1$s. Buy %2$s, Get %3$s at %4$s each.', 'wc-product-enhancer'),
                        $product_link,
                        esc_html($bogo['buy_qty']),
                        esc_html($bogo['get_qty']),
                        $price_html
                    );
                break;

                case 'free':
                    $message = sprintf(
                        __('Offer for %1$s. Buy %2$s, Get %3$s free.', 'wc-product-enhancer'),
                        $product_link,
                        esc_html($bogo['buy_qty']),
                        esc_html($bogo['get_qty'])
                    );
                    break;
                default:
                    $message = esc_html__('No offer','wc-product-enhancer');
                    break;
            }
        }else{
            $message = esc_html__('No offer','wc-product-enhancer');
        }

        echo wp_kses_post("<p>{$message}</p>");
    }
}