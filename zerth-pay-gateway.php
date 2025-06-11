<?php
/**
 * Plugin Name: ZERTH Pay WooCommerce Gateway
 * Plugin URI:  https://pay.zerth.online
 * Description: A WooCommerce payment gateway for ZERTH Pay.
 * Version:     1.0.0
 * Author:      James Idowu (James KPIE)
 * Author URI:  https://sharpali.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: zerth-pay-gateway
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.9
 */

if (! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// In zerth-pay-gateway.php
register_activation_hook( __FILE__, 'zerth_pay_activate' );
function zerth_pay_activate() {
    // Example: Add default options or check for WooCommerce dependency
    if (! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( __( 'ZERTH Pay Gateway requires WooCommerce to be installed and active.', 'zerth-pay-gateway' ) );
    }
    // Add default settings if they don't already exist
    add_option( 'zerth_pay_gateway_settings', array(
        'enabled' => 'no',
        'title'   => 'ZERTH Pay',
        'description' => 'Pay securely with ZERTH Pay. Pay in Naira or Crypto.',
        'client_id' => '',
        'client_secret' => '',
        'test_mode' => 'yes',
        'webhook_secret' => '',
    ) );
}

// In zerth-pay-gateway.php
register_deactivation_hook( __FILE__, 'zerth_pay_deactivate' );
function zerth_pay_deactivate() {
    // Placeholder for deactivation tasks
    // Example: Clean up temporary options, but generally avoid deleting user-configured settings here.
    // delete_option( 'zerth_pay_gateway_temp_data' );
}


// Ensure WooCommerce is active and our gateway class is loaded
add_action( 'plugins_loaded', 'zerth_pay_gateway_init_class' );

function zerth_pay_gateway_init_class() {
    if (! class_exists( 'WC_Payment_Gateway' ) ) {
        return; // WooCommerce not active
    }
    // Include the gateway class file
    // require_once plugin_dir_path( __FILE__ ). 'includes/class-wc-gateway-zerth-pay.php';
    require_once plugin_dir_path( __FILE__ ). 'includes/class-wc-gateway-zerth.php';

    // Add the gateway to WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'add_zerth_pay_gateway_class' );
}

function add_zerth_pay_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Zerth_Pay'; // Add your class name here
    return $methods;
}
