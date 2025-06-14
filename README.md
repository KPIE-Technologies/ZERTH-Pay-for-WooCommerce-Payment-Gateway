# ZERTH Pay for WooCommerce Payment Gateway
Plugin Name: ZERTH Pay for Payment Gateway
Plugin URI:  hhttps://pay.zerth.online
Description: A WooCommerce Addon payment gateway for ZERTH Pay.
Version:     1.0.0
Author:      James Idowu (James KPIE)
Author URI:  https://sharpali.com
License:     GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: zerth-pay-gateway
Domain Path: /languages
WC requires at least: 3.0
WC tested up to: 8.9


 ZERTH Pay WooCommerce Addon allows your store in Nigeria to accept secure payments via Bank transfer witthin Nigeria banks and cryptocurrency payment channels.

## Description

With ZERTH Pay, Merchants can allow customer pay at Woocommerce check using Naira, USD and cryptocurrency and the Merchant get his payment remitted in Naira or cryptocurrency. Even if you customers pay with Crypto, you as a merchant may withdraw as local currency.

With ZERTH Pay for WooCommerce, you can accept payments via:

* Local Bank Transfer at Checkout
* Cryptocurrencies in (USDT, BTC, DOGE, LTC etc)
* Many more coming soon

= Why ZERTH Pay? =

* Start receiving payments instantly—go from sign-up to your first real transaction in as little as 15 minutes
* Simple, transparent pricing—no hidden charges or fees
* Advanced fraud detection
* Understand your customers better through a simple and elegant dashboard
* Access to attentive support 24/7
* Free updates as we launch new features and payment options
* Clearly documented APIs to build your custom payment experiences



## Note =

This plugin is meant to be used by merchants Nigeria or could recieve payout with a Nigerian Bank.


## Installation ==

*   Go to __WordPress Admin__ > __Plugins__ > __Add New__ from the left-hand menu
*   In the search box type __ZERTH Pay for WooCommerce Payment Gateway__
*   Click on Install now when you see __ZERTH Pay for WooCommerce Payment Gateway__ to install the plugin
*   After installation, __activate__ the plugin.

# WEBHOOK

Your Webhook URL is:
https://yourdomain.com/wp-json/zerthpay/v1/webhook
Where "yourdomain.com" is your website base URL

If you want to change it, go to includes/class-wc-gateway-zerth.php and adjust the code below to your preference

//includes/class-zerth-pay-webhook-handler.php
add_action( 'rest_api_init', 'zerth_pay_register_webhook_route' );

function zerth_pay_register_webhook_route() {
    register_rest_route( 'zerthpay/v1', '/webhook', array(
        'methods'             => 'POST', // Webhooks typically send POST requests [8, 22]
        'callback'            => 'zerth_pay_handle_webhook',
        'permission_callback' => '__return_true', // Authenticity will be validated in the callback
    ) );
}

function zerth_pay_handle_webhook( WP_REST_Request $request ) {
    // Placeholder for webhook processing
    // Details will be covered in "Receiving and parsing incoming webhook data" and "Validating webhook authenticity"
    return new WP_REST_Response( array( 'status' => 'success' ), 200 ); // Respond with 200 OK [22]
}