<?php
/**
 * Plugin Name: WooCommerce Price Add Cart Limit
 * Description: Adds a suggested-price input for products with a minimum threshold and locks attempts below minimum for 1 minute.
 * Version: 1.0.0
 * Author: Max Base
 * Text Domain: wc-price-add-cart-limit
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Price_Add_Cart_Limit {
    private $TIME_LIMIT = 60;
    private $CUSTOM_PRICE_EXPIRATION = 60;

    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));

        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_price_input'));
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);

        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_suggested_price'), 20, 1);
        add_action('woocommerce_cart_loaded_from_session', array($this, 'check_custom_price_expiration'));

        add_filter('woocommerce_cart_item_price', array($this, 'modify_cart_price_display'), 10, 3);
        add_filter('woocommerce_get_price_html', array($this, 'filter_price_html'), 10, 2);

        add_action('woocommerce_cart_item_removed', array( $this, 'lock_user_on_delete' ), 10, 2);

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function lock_user_on_delete( $cart_item_key, $cart ) {
        if (!is_user_logged_in()) {
            return;
        }

        $removed_contents = isset( $cart->removed_cart_contents )
            ? $cart->removed_cart_contents
            : array();

        if ( empty( $removed_contents[ $cart_item_key ] ) ) {
            return;
        }

        $item       = $removed_contents[ $cart_item_key ];
        $product_id = $item['product_id'];

        if ('yes' === get_post_meta( $product_id, '_enable_suggested_price', true)) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'price_lock_' . $product_id, time());
            // wc_add_notice(__( 'شما محدود شده اید و امکان خرید این محصول را تا مدتی ندارید.', 'wc-price-add-cart-limit' ), 'error');
        }
    }

    public function enqueue_scripts() {
        $js_path = plugin_dir_path(__FILE__) . 'price-timer.js';
        
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'wc-price-add-cart-limit',
                plugins_url('price-timer.js', __FILE__),
                array('jquery'),
                filemtime($js_path),
                true
            );

            wp_localize_script('wc-price-add-cart-limit', 'wc_price_add_cart_limit_vars', array('expired_text' => __('Custom price expired', 'wc-price-add-cart-limit')));
        } else {
            error_log('Price timer JS file missing: ' . $js_path);
        }
    }

    public function add_cart_item_data($data, $product_id) {
        if (get_post_meta($product_id, '_enable_suggested_price', true) !== 'yes') return $data;

        if (isset($_POST['suggested_price']) && $_POST['suggested_price'] !== "") {
            $data['suggested_price'] = floatval($_POST['suggested_price']);
            if ($data['suggested_price'] > 0) {
                $data['price_expiration'] = time() + $this->CUSTOM_PRICE_EXPIRATION;
                $data['original_price'] = (float) get_post_meta($product_id, '_price', true);
                $data['unique_key'] = md5(microtime() . rand());
            }
        }
        return $data;
    }

    public function check_custom_price_expiration($cart) {
        $needs_recalculation = false;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['price_expiration']) && time() > $cart_item['price_expiration']) {
                $cart->cart_contents[$cart_item_key]['data']->set_price($cart_item['original_price']);
                
                unset($cart->cart_contents[$cart_item_key]['suggested_price']);
                unset($cart->cart_contents[$cart_item_key]['price_expiration']);
                
                $needs_recalculation = true;
                
                wc_add_notice(__('مدت زمان مجاز شما جهت نهایی کردن خرید گذشت. قیمت محصول به قیمت معقول تغییر یافت.', 'wc-price-add-cart-limit'), 'notice');
            }
        }
        
        if ($needs_recalculation) {
            $cart->calculate_totals();
            WC()->session->set('cart_totals', null);
        }
    }

    public function apply_suggested_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        
        foreach ($cart->get_cart() as &$cart_item) {
            if (isset($cart_item['suggested_price']) && isset($cart_item['price_expiration'])) {
                if (time() < $cart_item['price_expiration']) {
                    $cart_item['data']->set_price($cart_item['suggested_price']);
                } else {
                    $cart_item['data']->set_price($cart_item['original_price']);
                    unset($cart_item['suggested_price']);
                    unset($cart_item['price_expiration']);
                }
            }
        }
    }

    public function modify_cart_price_display($price_html, $cart_item, $cart_item_key) {
        $product_id = $cart_item['product_id'];
        $parent_id = $cart_item['data']->get_parent_id();
    
        $is_enabled = get_post_meta($product_id, '_enable_suggested_price', true) === 'yes' ||
                      ($parent_id && get_post_meta($parent_id, '_enable_suggested_price', true) === 'yes');
    
        if ($is_enabled && isset($cart_item['suggested_price']) && isset($cart_item['price_expiration'])) {
            $expiration = $cart_item['price_expiration'];
            $formatted_time = $this->format_expiration_time($expiration);
            
            if (time() < $expiration) {
                return sprintf(
                    '%s<br><small class="custom-price-timer" style="color: black" data-expiration="%d">%s<span class="timer">%s</span></small>',
                    wc_price($cart_item['suggested_price']),
                    $expiration,
                    __('زمان نهایی کردن خرید: ', 'wc-price-add-cart-limit'),
                    $formatted_time
                );
            }
            return wc_price($cart_item['original_price']) . '<br><small>' . 
                __('Custom price expired', 'wc-price-add-cart-limit') . '</small>';
        }
        
        return $price_html;
    }
    
    private function format_expiration_time($expiration_timestamp) {
        $remaining = $expiration_timestamp - time();
        $minutes = floor($remaining / 60);
        $seconds = $remaining % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function add_product_fields() {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox(array(
            'id'          => '_enable_suggested_price',
            'label'       => __('فعال سازی امکان حدس قیمت برای کاربران', 'wc-price-add-cart-limit'),
            'description' => __('اجازه دهید تا مشتریان بتوانند محصول را با قیمت دلخواه خود به سبد اضافه کنند.', 'wc-price-add-cart-limit'),
        ));
        woocommerce_wp_text_input(array(
            'id'                => '_min_suggested_price',
            'label'             => __('حداقل قیمت تخمین و خرید برای کاربر', 'wc-price-add-cart-limit'),
            'type'              => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'description'       => __('حداقل قیمتی که کاربر بتواند برای خرید محصول با تخمین قیمت آن را به سبد اضافه کند.', 'wc-price-add-cart-limit'),
        ));
        echo '</div>';
    }

    public function save_product_fields($post_id) {
        $enable = isset($_POST['_enable_suggested_price']) ? 'yes' : 'no';
        update_post_meta($post_id, '_enable_suggested_price', $enable);
        if (isset($_POST['_min_suggested_price'])) {
            update_post_meta($post_id, '_min_suggested_price', wc_format_decimal($_POST['_min_suggested_price']));
        }
    }

    public function display_price_input() {
        if (!is_product()) return;

        global $product;
        if (get_post_meta($product->get_id(), '_enable_suggested_price', true) !== 'yes') return;

        foreach (WC()->cart->get_cart() as $item) {
            if ($item['product_id'] == $product->get_id()) return;
        }

        if (is_user_logged_in()) {
            $lock = get_user_meta(get_current_user_id(), 'price_lock_' . $product->get_id(), true);
            if ($lock && (time() - intval($lock)) < $this->TIME_LIMIT) {
                wc_print_notice(__('شما محدود شده اید و نمی توانید اکنون این محصول را با قیمت دلخواه خرید کنید.', 'wc-price-add-cart-limit'), 'notice');
                return;
            }
        }

        echo '<div class="suggested-price-field"><label for="suggested_price_input">'
            . '<input type="number" step="1" min="0" name="suggested_price" id="suggested_price_input" required="">'
            . '</div>';
    }

    public function validate_add_to_cart($passed, $product_id, $qty) {
        if (!isset($_POST['suggested_price'])) return $passed;
        if (get_post_meta($product_id, '_enable_suggested_price', true) !== 'yes') return $passed;

        $min = floatval(get_post_meta($product_id, '_min_suggested_price', true));
        $suggested = floatval($_POST['suggested_price']);
        if ($suggested < $min) {
            if (is_user_logged_in()) update_user_meta(get_current_user_id(), 'price_lock_' . $product_id, time());
            wc_add_notice(__('قیمت وارد شده کمتر از معقول بود با قیمت بیشتری محصول به سبد اضافه شد.', 'wc-price-add-cart-limit'), 'error');
            unset($_POST['suggested_price']);
            return true;
        }
        return $passed;
    }

    public function filter_price_html($price_html, $product) {
        if (is_admin()) return $price_html;
        
        $product_id = $product->get_id();
        $parent_id = $product->get_parent_id();
        
        $is_enabled = get_post_meta($product_id, '_enable_suggested_price', true) === 'yes' ||
                      ($parent_id && get_post_meta($parent_id, '_enable_suggested_price', true) === 'yes');

        if (!$is_enabled) return $price_html;

        if (!is_product()) return '';

        if (is_user_logged_in()) {
            $lock = get_user_meta(get_current_user_id(), 'price_lock_' . $product->get_id(), true);
            if ($lock && (time() - intval($lock)) < $this->TIME_LIMIT) {
                return $price_html;
            }

            foreach (WC()->cart->get_cart() as $item) {
                if ($item['product_id'] == $product_id && isset($item['suggested_price'])) {
                    return wc_price($item['suggested_price']);
                }
            }
            return '';
        }

        return '';
    }
}

new WC_Price_Add_Cart_Limit();
