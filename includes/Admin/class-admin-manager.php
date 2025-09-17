<?php
namespace WCProductEnhancer\Admin;

class AdminManager
{
    public function __construct()
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_bogo_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_bogo_product_data_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_bogo_product_data']);
    }

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
 
}