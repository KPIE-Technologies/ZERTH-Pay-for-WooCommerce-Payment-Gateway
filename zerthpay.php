<?php
/**
 * Plugin Name: ZERTH Pay Payment Gateway
 * Plugin URI:  https://pay.zerth.online
 * Description: A WooCommerce payment gateway for ZERTH Pay.
 * Version:     1.0.0
 * Author:      James Idowu (James KPIE)
 * Author URI:  https://sharpali.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: zerth-pay-payment-gateway
 * Domain Path: /languages
 * WC requires at least: 6.2
 * WC tested up to: 8.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define ZERTHPAY_VERSION for script caching.
if ( ! defined( 'ZERTHPAY_VERSION' ) ) {
    define( 'ZERTHPAY_VERSION', '1.0.2' ); // Increment this when you make JS changes.
}

/**
 * ZERTH Pay Payment Gateway main class.
 */
class Zerthpay_Gateway_Loader {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->hooks();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'ZERTHPAY_PLUGIN_FILE', __FILE__ );
		define( 'ZERTHPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'ZERTHPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Setup plugin hooks.
	 */
	private function hooks() {
		// Load the main gateway class and add it to WooCommerce.
		add_action( 'plugins_loaded', array( $this, 'load_and_register_gateway_class' ), 0 );

		// Declare compatibility with Cart & Checkout Blocks early.
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_blocks_compatibility' ) );

		// Load plugin textdomain.
		add_action( 'plugins_loaded', array( $this, 'load_zerthpay_textdomain' ) );

		// Register Zerthpay Blocks Integration for block checkout.
		// This hook ensures that WooCommerce Blocks are loaded and ready.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_zerthpay_blocks_integration' ) );
	}

	/**
	 * Loads the main WC_Gateway_Zerthpay class and registers it with WooCommerce.
	 */
	public function load_and_register_gateway_class() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return; // WooCommerce is not active.
		}

		require_once ZERTHPAY_PLUGIN_DIR . 'includes/class-wc-gateway-zerthpay.php';
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_zerthpay_gateway' ) );
	}

	/**
	 * Add the ZERTH Pay Payment Gateway to WooCommerce.
	 *
	 * @param array $methods Payment methods.
	 * @return array
	 */
	public function add_zerthpay_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Zerthpay';
		return $methods;
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_zerthpay_textdomain() {
		load_plugin_textdomain( 'zerth-pay-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Declare compatibility with WooCommerce Cart & Checkout Blocks.
	 */
	public function declare_woocommerce_blocks_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true // true means that this plugin supports the feature.
			);
		}
	}

	/**
	 * Register Zerthpay Blocks Integration with the PaymentMethodRegistry.
	 * This method is hooked to `woocommerce_blocks_loaded`.
	 */
	public function register_zerthpay_blocks_integration() {
		// Ensure the AbstractPaymentMethodType class exists before proceeding.
		if ( class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
			require_once ZERTHPAY_PLUGIN_DIR . 'includes/class-zerthpay-blocks-integration.php';

			// Hook into the payment method type registration.
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new \Zerthpay\Blocks\Zerthpay_Blocks_Integration() );
				}
			);
		} else {
            // Optional: Log a message if Blocks classes are not found, indicating blocks integration is skipped.
            error_log( 'ZERTH Pay Payment Gateway: WooCommerce Blocks AbstractPaymentMethodType class not found. Blocks integration skipped.' );
        }
	}
}

// Initialize the plugin loader.
new Zerthpay_Gateway_Loader();
