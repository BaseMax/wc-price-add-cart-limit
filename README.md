# WooCommerce Price Add Cart Limit

A WooCommerce extension plugin that lets customers suggest their own prices for products, within certain restrictions. If a customer enters a price below the minimum allowed, their ability to purchase that product is locked for a limited time.

## Features

- Add a **suggested price** input on product pages.
- Set a **minimum suggested price** per product.
- **Lock users** from re-attempting purchase with a low price for 1 minute.
- **Expire custom prices** after a certain time (default 60 seconds).
- Automatically **restore original price** after custom price expires.
- **Timer countdown** displayed for custom price expiration.
- Admin settings for each product to enable/disable suggested price feature.

## Installation

1. Download the plugin files.
2. Upload the folder to your WordPress `/wp-content/plugins/` directory.
3. Activate the plugin through the WordPress 'Plugins' dashboard.

Alternatively, you can upload the `.zip` file in the WordPress admin panel.

## Usage

### Enable Suggested Price for a Product

1. Edit any WooCommerce product.
2. Under **Product data > General**, you will see two new fields:
   - **Enable Suggested Price**: Allow customers to suggest their own price.
   - **Minimum Suggested Price**: Set the lowest price allowed.
3. Update or publish the product.

### How Customers Interact

- On the product page, a price input field will appear.
- Customers must enter a price greater than or equal to the minimum.
- If the custom price expires (after 60 seconds), the product reverts to its original price.
- If the user tries to cheat (e.g., by removing and re-adding products below minimum), they get locked for 1 minute.

## Settings and Limits

| Setting                      | Default Value | Description                                 |
|-------------------------------|---------------|---------------------------------------------|
| Lock Time After Wrong Price   | 60 seconds    | Time a user is blocked from re-purchasing   |
| Custom Price Expiration Timer | 60 seconds    | Time before the suggested price expires    |

## Developer Info

- **Plugin Name:** WooCommerce Price Add Cart Limit
- **Version:** 1.0.0
- **Author:** Max Base ([BaseMax](https://github.com/BaseMax))
- **Text Domain:** `wc-price-add-cart-limit`

## File Structure

- `woocommerce-price-add-cart-limit.php` - Main plugin file.
- `price-timer.js` - Handles client-side timer countdown for custom prices.

## Hooks and Actions Used

- `woocommerce_product_options_general_product_data`
- `woocommerce_process_product_meta`
- `woocommerce_before_add_to_cart_button`
- `woocommerce_add_to_cart_validation`
- `woocommerce_add_cart_item_data`
- `woocommerce_before_calculate_totals`
- `woocommerce_cart_loaded_from_session`
- `woocommerce_cart_item_price`
- `woocommerce_get_price_html`
- `woocommerce_cart_item_removed`
- `wp_enqueue_scripts`

## Contributing

Pull requests are welcome. Feel free to fork the repository and submit improvements or bug fixes.

## License

This plugin is licensed under the MIT License.

## Support

For issues, open an issue on [GitHub Issues](https://github.com/BaseMax/wc-price-add-cart-limit/issues).

## Special Thanks

- WooCommerce for the amazing framework.
- WordPress community.

## Future Plans

- Admin setting to customize lock duration and expiration timer.
- Multilingual support improvements.
- More flexible pricing rules.

Copyright 2025, Max Base
