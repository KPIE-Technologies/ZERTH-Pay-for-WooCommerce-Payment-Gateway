<?php
/**
 * Zerthpay Blocks Integration.
 *
 * @package Zerthpay
 */

namespace Zerthpay\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Zerthpay Blocks Integration class.
 *
 * @since 1.0.0
 */
final class Zerthpay_Blocks_Integration extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id.
	 *
	 * @var string
	 */
	protected $name = 'zerthpay'; // This must match the ID in WC_Gateway_Zerthpay.

	/**
	 * The main ZERTH Pay Payment Gateway instance.
	 *
	 * @var \WC_Gateway_Zerthpay
	 */
	private $gateway;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		// Ensure the main gateway class is loaded.
		if ( ! class_exists( '\WC_Gateway_Zerthpay' ) ) {
			return;
		}
		$this->settings = get_option( 'woocommerce_zerthpay_settings', array() );
		$this->gateway  = new \WC_Gateway_Zerthpay(); // Instantiate your main gateway class.
	}

	/**
	 * Returns the name of the payment method.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		// Ensure gateway is initialized before calling its method.
		if ( ! $this->gateway ) {
			$this->initialize();
		}
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts to enqueue for the payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'zerthpay-blocks-integration',
			ZERTHPAY_PLUGIN_URL . 'assets/js/zerthpay-checkout-block.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			defined( 'ZERTHPAY_VERSION' ) ? ZERTHPAY_VERSION : null, // Use defined version for cache busting.
			true
		);
		return array( 'zerthpay-blocks-integration' );
	}

	/**
	 * Returns an array of key-value pairs of data to be sent to the payment method's JS.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		// Ensure the gateway is initialized.
		if ( ! $this->gateway ) {
			$this->initialize();
		}
		return array(
			'title'       => $this->gateway->get_title(),
			'description' => $this->gateway->get_description(),
			'icon'        => $this->gateway->get_icon(),
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'name'        => $this->get_name(), // Ensure the name is passed to JS.
		);
	}
}