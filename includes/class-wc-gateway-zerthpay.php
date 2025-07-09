<?php
/**
 * ZERTH Pay Payment Gateway.
 *
 * @package Zerthpay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Zerthpay class.
 */
class WC_Gateway_Zerthpay extends WC_Payment_Gateway {

	public $testmode;
	public $api_key;
	public $api_secret;

	protected $cipher = 'AES-256-CBC';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'zerthpay';
		$this->icon               = ZERTHPAY_PLUGIN_URL . 'assets/images/logo.png';
		$this->has_fields         = false;
		$this->method_title       = __( 'Zerthpay', 'zerth-pay-payment-gateway' );
		$this->method_description = __( 'Accept payments via Zerthpay.', 'zerth-pay-payment-gateway' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->enabled      = $this->get_option( 'enabled' );
		$this->testmode     = 'yes' === $this->get_option( 'testmode' );
		$this->api_key      = $this->testmode ? $this->get_option( 'test_api_key' ) : $this->get_option( 'live_api_key' );
		$this->api_secret   = $this->testmode ? $this->get_option( 'test_api_secret' ) : $this->get_option( 'live_api_secret' );

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action('rest_api_init', array( $this, 'register_zerthpay_webhook_route' ) );

		if ( $this->has_fields ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		}
	}

	/**
	 * Registers the REST API route for Zerthpay webhooks.
	 */
	public function register_zerthpay_webhook_route() {
		register_rest_route('zerthpay/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_zerthpay_webhook' ), 
			'permission_callback' => '__return_true', // Authenticity will be validated in the callback
	   ));
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'zerth-pay-payment-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable ZERTH Pay Payment Gateway', 'zerth-pay-payment-gateway' ),
				'default' => 'no',
			),
			'title'      => array(
				'title'       => __( 'Title', 'zerth-pay-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'zerth-pay-payment-gateway' ),
				'default'     => __( 'Pay with Zerthpay', 'zerth-pay-payment-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'zerth-pay-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'zerth-pay-payment-gateway' ),
				'default'     => __( 'Pay using secured Zerthpay channel.', 'zerth-pay-payment-gateway' ),
				'desc_tip'    => true,
			),
			'testmode'   => array(
				'title'       => __( 'Test mode', 'zerth-pay-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Test Mode', 'zerth-pay-payment-gateway' ),
				'default'     => 'no',
				'description' => __( 'Place the gateway in test mode using test API keys.', 'zerth-pay-payment-gateway' ),
			),
			'live_api_key' => array(
				'title'       => __( 'Live Client ID', 'zerth-pay-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Get your Live Client ID from your Zerthpay dashboard.', 'zerth-pay-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_api_secret' => array(
				'title'       => __( 'Live Client Secret', 'zerth-pay-payment-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your Live Client Secret from your Zerthpay dashboard.', 'zerth-pay-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_api_key' => array(
				'title'       => __( 'Test Client ID', 'zerth-pay-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Get your Test Client ID from your Zerthpay dashboard.', 'zerth-pay-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_api_secret' => array(
				'title'       => __( 'Test Client Secret', 'zerth-pay-payment-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your Test Client Secret from your Zerthpay dashboard.', 'zerth-pay-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
            'webhook_secret' => array(
                'title'       => __( 'Webhook Secret Key', 'zerth-pay-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Get your Webhook Secret Key from your Zerthpay dashboard.', 'zerth-pay-payment-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
		);
	}

	/**
	 * Output for the order received page.
	 * Override this method if you have custom fields.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}

		// Add custom Zerthpay specific fields here if needed (e.g., a simple text input).
		// For a real gateway, this might be where you render a secure iframe or custom card input fields.
		?>
		<p class="form-row form-row-wide">
			<label for="zerthpay_custom_field"><?php esc_html_e( 'Zerthpay Reference (Optional)', 'zerth-pay-payment-gateway' ); ?></label>
			<input type="text" class="input-text" id="zerthpay_custom_field" name="zerthpay_custom_field" placeholder="<?php esc_attr_e( 'Enter an optional reference', 'zerth-pay-payment-gateway' ); ?>" />
		</p>
		<?php
	}

	/**
	 * You can enqueue your payment scripts here.
	 * For a real payment gateway, you might load a JavaScript library from the payment provider.
	 */
	public function payment_scripts() {
		if ( ! is_checkout() || ! $this->is_available() ) {
			return;
		}

		wp_enqueue_script( 'zerthpay-checkout', ZERTHPAY_PLUGIN_URL . 'assets/js/zerthpay-checkout.js', array( 'jquery' ), null, true );
		wp_localize_script(
			'zerthpay-checkout',
			'zerthpay_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'gateway_id' => $this->id,
			)
		);
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
		// Get custom field data if any.
		$custom_field_value = isset( $_POST['zerthpay_custom_field'] ) ? sanitize_text_field( wp_unslash( $_POST['zerthpay_custom_field'] ) ) : '';
		if ( ! empty( $custom_field_value ) ) {
			$order->update_meta_data( '_zerthpay_custom_field', $custom_field_value );
			$order->save();
		}
		$payload = $this->prepare_zerthpay_request_data($order);
		$zerthpay_response = $this->call_zerthpay_api( $payload);

		if ( isset( $zerthpay_response['type'] ) && 'success' === $zerthpay_response['type'] &&
			isset( $zerthpay_response['data']['payment_url'] ) && ! empty( $zerthpay_response['data']['payment_url'] ) ) {

			$payment_url = $zerthpay_response['data']['payment_url'];

			
			$order->update_status( 'pending', __( 'Awaiting ZerthPay payment.', 'zerth-pay-payment-gateway' ) );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $payment_url,
			);

		} else {
			$error_message = __( 'ZerthPay payment initiation failed. Please try again.', 'zerth-pay-payment-gateway' );

			if ( isset( $zerthpay_response['message'] ) ) {
				if ( is_string( $zerthpay_response['message'] ) && ! empty( $zerthpay_response['message'] ) ) {
					$error_message = $zerthpay_response['message'];
				} elseif ( is_array( $zerthpay_response['message'] ) ) {
					$detailed_messages = [];
					if ( isset( $zerthpay_response['message']['error'] ) ) {
						if ( is_array( $zerthpay_response['message']['error'] ) ) {
							$detailed_messages = array_merge( $detailed_messages, $zerthpay_response['message']['error'] );
						} elseif ( is_string( $zerthpay_response['message']['error'] ) ) {
							$detailed_messages[] = $zerthpay_response['message']['error'];
						}
					}
					// Removed the 'success' message check here as it implies an error if 'type' is not 'success'.
					if ( isset( $zerthpay_response['message']['code'] ) && 200 !== $zerthpay_response['message']['code'] ) {
						$detailed_messages[] = 'Code: ' . $zerthpay_response['message']['code'];
					}

					if ( ! empty( $detailed_messages ) ) {
						$error_message .= ' (' . implode( ', ', array_filter( array_unique( $detailed_messages ) ) ) . ')';
					}
				}
			}

			wc_add_notice( $error_message, 'error' );

			$order->update_status( 'failed', $error_message );
			$order->save();

			return array(
				'result'   => 'fail',
				'redirect' => '', 
			);
		}
	}

	private function prepare_zerthpay_request_data($order) {
        error_log(sprintf('Order ID: %s', $order->get_id())); 
		
        return array(
            'amount'      => $order->get_total(),
            'currency'    => $order->get_currency(),
            'reference'    => $order->get_order_key(),
            'order_id'    => $order->get_id(),
            'customer_id' => $order->get_customer_id(),
            'return_url'  => $this->get_return_url($order),
            'cancel_url'  => $this->get_return_url($order),
            'webhook_url' => get_rest_url(null, 'zerthpay/v1/webhook'), 
       );
    }

	private function call_zerthpay_api( $payload) {
        $client_id = $this->api_key;
        $test_mode  = $this->get_option('testmode') === 'yes'; 

        $initiate_url = $test_mode? 'https://pay.zerth.online/pay/sandbox/api/v1/authentication/token' : 'https://pay.zerth.online/pay/api/v1/authentication/token';
        $token = $this->initialize_zerthpay(initiate_url: $initiate_url);

        if ( !isset($token['res']['data']['access_token']) ) {
            error_log('ZERTH Pay API Error: Failed to obtain access token.');
            wc_add_notice(__('ZERTH Pay API Error: Could not authenticate with payment gateway.', 'zerth-pay-payment-gateway'), 'error');
            return false;
        }
        $access_token  = $token['res']['data']['access_token'];


        $api_url = $test_mode? 'https://pay.zerth.online/pay/sandbox/api/v1/payment/create/' : 'https://pay.zerth.online/pay/api/v1/payment/create/';
        $full_url = trailingslashit($api_url);


        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '. $access_token,
            'client-id' =>  $client_id,
       );

        $args = array(
            'method'    => 'POST',
            'headers'   => $headers,
            'body'      => wp_json_encode($payload), 
            'timeout'   => 250, 
            'sslverify' => true, 
       );

        $response = wp_remote_post($full_url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log(sprintf('ZERTH Pay API Error: %s', $error_message));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            error_log(sprintf('ZERTH Pay API responded with status %d: %s', $http_code, (isset($data['message']) ? json_encode($data['message']) : 'No specific message')));
            return $data; 
        }

        return $data;
    }

	public function initialize_zerthpay($initiate_url){
        $headers = array(
            'Content-Type'  => 'application/x-www-form-urlencoded',
       );
            $payload = [
                'client_id' => $this->api_key,
                'secret_id' => $this->api_secret
            ];
            $body = json_encode($payload);

            $curl = curl_init();

      curl_setopt_array($curl, [
        CURLOPT_URL => $initiate_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
          "accept: application/json",
          "content-type: application/json"
        ],
      ]);

      $response = curl_exec($curl);
      $err = curl_error($curl);

      curl_close($curl);

       if ($err) {
        error_log("cURL Error #: " . $err); 
        return ['status'=> 'failed', 'res'=> "cURL Error #:" . $err];
      } else {
        return ['status'=> 'success', 'res'=>json_decode($response, true)];
      }
    }


	public function handle_zerthpay_webhook( WP_REST_Request $request ){ 
		
		$body = $request->get_body();
		$data 	= json_decode($body, true);

        if ( !isset($data['iv']) || !isset($data['payload']) ) {
            error_log('ZERTH Pay Webhook: Missing IV or payload in encrypted data.');
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Invalid encrypted payload structure'), 400);
        }

		$iv 	= base64_decode($data['iv']);
		$encrypted_data 	= base64_decode($data['payload']);

		if (empty($data) ||! is_array($data)) {
			error_log('ZERTH Pay Webhook: Received empty or malformed payload.');
			return new WP_REST_Response(array('status' => 'error', 'message' => 'Invalid payload'), 400);
		}

        $webhook_key = $this->get_option('webhook_secret');

        if (empty($webhook_key)) {
            error_log('ZERTH Pay Webhook: Webhook Secret Key is not configured in plugin settings.');
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Webhook secret key not configured'), 401);
        }

		$decrypted = openssl_decrypt(
			$encrypted_data,
			$this->cipher,
			hash('sha256', $webhook_key, true), 
			0,
			$iv
		);

		if ($decrypted === false) {
			error_log('ZERTH Pay Webhook: Decryption failed.'); 
			return new WP_REST_Response(array('status' => 'error', 'message' => 'Decryption failed'), 400); 
		}

		$payData = json_decode($decrypted, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ZERTH Pay Webhook: Invalid JSON payload after decryption: ' . json_last_error_msg()); 
			return new WP_REST_Response(array('status' => 'error', 'message' => 'Invalid JSON payload after decryption'), 400); 
		}
		
		$transaction_id= isset($payData['data']['transaction_id']) ? sanitize_text_field($payData['data']['transaction_id']) : '';

        if (empty($transaction_id)) {
            error_log('ZERTH Pay Webhook: Missing transaction ID in decrypted payload.');
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Missing Transaction ID'), 400);
        }

		if (get_transient('zerthpay_webhook_processed_'. $transaction_id)) {
			error_log('ZERTH Pay Webhook: Duplicate transaction ID received: '. $transaction_id);
			return new WP_REST_Response(array('status' => 'success', 'message' => 'Already processed'), 200);
		}
		set_transient('zerthpay_webhook_processed_'. $transaction_id, true, DAY_IN_SECONDS); // Store for a day to prevent replay attacks

		
		$order_id = isset($payData['data']['externalReference'])? intval($payData['data']['externalReference']) : 0;
		$zerthpay_status = isset($payData['data']['status'])? sanitize_text_field($payData['data']['status']) : '';
		$zerthpay_transaction_id = isset($payData['data']['transaction_id'])? sanitize_text_field($payData['data']['transaction_id']) : ''; 

		if (! $order_id) {
			error_log('ZERTH Pay Webhook: Missing WooCommerce Order ID in payload.');
			return new WP_REST_Response(array('status' => 'error', 'message' => 'Missing Order ID'), 400);
		}

		$order = wc_get_order($order_id);
		if (! $order) {
			error_log('ZERTH Pay Webhook: Order not found for ID '. $order_id);
			return new WP_REST_Response(array('status' => 'error', 'message' => 'Order not found'), 404);
		}

		switch ($zerthpay_status) {
			case 'Completed': 
				if (! $order->is_paid()) {
					$order->payment_complete($zerthpay_transaction_id); 
					$order->add_order_note(sprintf(__('ZERTH Pay webhook: Payment completed. Transaction ID: %s', 'zerth-pay-payment-gateway'), $zerthpay_transaction_id));
				}
				break;
			case 'Failed': 
				$order->update_status('failed', sprintf(__('ZERTH Pay webhook: Payment failed. Transaction ID: %s', 'zerth-pay-payment-gateway'), $zerthpay_transaction_id));
				break;
			case 'Refunded': 
				$order->update_status('refunded', sprintf(__('ZERTH Pay webhook: Payment refunded. Transaction ID: %s', 'zerth-pay-payment-gateway'), $zerthpay_transaction_id));
				// Additional logic might be required here for partial refunds or stock management.
				break;
			default:
				$order->add_order_note(sprintf(__('ZERTH Pay webhook: Unknown status "%s" received for transaction ID: %s', 'zerth-pay-payment-gateway'), $zerthpay_status, $zerthpay_transaction_id));
				break;
		}

		return new WP_REST_Response(array('status' => 'success'), 200);
	}
	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( $this->testmode ) {
			if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
				return false;
			}
		} else {
			if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
				return false;
			}
		}

		return parent::is_available();
	}
}