<?php
/**
 * Zerthpay Gateway.
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

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'zerthpay';
		$this->icon               = ZERTHPAY_PLUGIN_URL . 'assets/images/logo.png';
		$this->has_fields         = false;
		$this->method_title       = __( 'Zerthpay', 'zerthpay-gateway' );
		$this->method_description = __( 'Accept payments via Zerthpay.', 'zerthpay-gateway' );

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
		// Removed the woocommerce_api_zerthpay_webhook action as it's redundant with the REST API route.
		// add_action( 'woocommerce_api_zerthpay_webhook', array( $this, 'handle_webhook' ) ); // For processing callbacks.

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
			'callback'            => array( $this, 'handle_zerthpay_webhook' ), // Correctly reference the class method
			'permission_callback' => '__return_true', // Authenticity will be validated in the callback
	   ));
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'zerthpay-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Zerthpay Gateway', 'zerthpay-gateway' ),
				'default' => 'no',
			),
			'title'      => array(
				'title'       => __( 'Title', 'zerthpay-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'zerthpay-gateway' ),
				'default'     => __( 'Pay with Zerthpay', 'zerthpay-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'zerthpay-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'zerthpay-gateway' ),
				'default'     => __( 'Pay using secured Zerthpay channel.', 'zerthpay-gateway' ),
				'desc_tip'    => true,
			),
			'testmode'   => array(
				'title'       => __( 'Test mode', 'zerthpay-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Test Mode', 'zerthpay-gateway' ),
				'default'     => 'no',
				'description' => __( 'Place the gateway in test mode using test API keys.', 'zerthpay-gateway' ),
			),
			'live_api_key' => array(
				'title'       => __( 'Live Client ID', 'zerthpay-gateway' ),
				'type'        => 'text',
				'description' => __( 'Get your Live Client ID from your Zerthpay dashboard.', 'zerthpay-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_api_secret' => array(
				'title'       => __( 'Live Client Secret', 'zerthpay-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your Live Client Secret from your Zerthpay dashboard.', 'zerthpay-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_api_key' => array(
				'title'       => __( 'Test Client ID', 'zerthpay-gateway' ),
				'type'        => 'text',
				'description' => __( 'Get your Test Client ID from your Zerthpay dashboard.', 'zerthpay-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_api_secret' => array(
				'title'       => __( 'Test Client Secret', 'zerthpay-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your Test Client Secret from your Zerthpay dashboard.', 'zerthpay-gateway' ),
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
			<label for="zerthpay_custom_field"><?php esc_html_e( 'Zerthpay Reference (Optional)', 'zerthpay-gateway' ); ?></label>
			<input type="text" class="input-text" id="zerthpay_custom_field" name="zerthpay_custom_field" placeholder="<?php esc_attr_e( 'Enter an optional reference', 'zerthpay-gateway' ); ?>" />
			<small class="form-text text-muted"><?php esc_html_e( 'This field is just an example. A real payment gateway would integrate with a secure input.', 'zerthpay-gateway' ); ?></small>
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

		// Check if the API call was successful and payment_url exists
		if ( isset( $zerthpay_response['type'] ) && 'success' === $zerthpay_response['type'] &&
		isset( $zerthpay_response['data']['payment_url'] ) && ! empty( $zerthpay_response['data']['payment_url'] ) ) {

		$payment_url = $zerthpay_response['data']['payment_url'];

        // Mark the order as pending payment (or a custom status if preferred)
        $order->update_status( 'pending', __( 'Awaiting ZerthPay payment.', 'zerthpay-gateway' ) );
        $order->save();

        // Redirect the user to the ZerthPay payment URL
        return array(
			'result'   => 'success',
            'redirect' => $payment_url,
        );

		} else {
			// Handle API call failure or missing payment URL
			$error_message = __( 'ZerthPay payment initiation failed. Please try again.', 'zerthpay-gateway' );

			// Attempt to get a more specific error message from the API response
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
					if ( isset( $zerthpay_response['message']['success'] ) ) { // In case success message is present but type is not 'success'
						if ( is_array( $zerthpay_response['message']['success'] ) ) {
							$detailed_messages = array_merge( $detailed_messages, $zerthpay_response['message']['success'] );
						} elseif ( is_string( $zerthpay_response['message']['success'] ) ) {
							$detailed_messages[] = $zerthpay_response['message']['success'];
						}
					}
					if ( isset( $zerthpay_response['message']['code'] ) && 200 !== $zerthpay_response['message']['code'] ) {
						$detailed_messages[] = 'Code: ' . $zerthpay_response['message']['code'];
					}

					if ( ! empty( $detailed_messages ) ) {
						$error_message .= ' (' . implode( ', ', array_filter( array_unique( $detailed_messages ) ) ) . ')';
					}
				}
			}

			wc_add_notice( $error_message, 'error' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}



		try {

			if ( isset( $zerthpay_response['status'] ) && 'success' === $zerthpay_response['status'] ) {
				// Payment successful.
				$order->payment_complete( $zerthpay_response['transaction_id'] ); // Mark order as paid.
				$order->add_order_note( sprintf( __( 'Zerthpay payment successful. Transaction ID: %s', 'zerthpay-gateway' ), $zerthpay_response['transaction_id'] ) );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				// Payment failed or pending further action.
				$order->update_status( 'failed', __( 'Zerthpay payment failed or was declined.', 'zerthpay-gateway' ) );
				wc_add_notice( __( 'Payment error: Please try again or choose another payment method.', 'zerthpay-gateway' ), 'error' );
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} catch ( Exception $e ) {
			$order->update_status( 'failed', sprintf( __( 'Zerthpay payment processing error: %s', 'zerthpay-gateway' ), $e->getMessage() ) );
			wc_add_notice( sprintf( __( 'Payment processing error: %s', 'zerthpay-gateway' ), $e->getMessage() ), 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	private function prepare_zerthpay_request_data($order) {
        return array(
            'amount'      => $order->get_total(),
            'currency'    => $order->get_currency(),
            'reference'    => $order->get_order_key(),
            'order_id'    => $order->get_id(),
            'customer_id' => $order->get_customer_id(),
            'return_url'  => $this->get_return_url($order),
            'cancel_url'  => $this->get_return_url($order),
            'webhook_url' => get_rest_url(null, 'zerthpay/v1/webhook'), // Your webhook listener URL
       );
    }

	private function call_zerthpay_api( $payload) {
        $client_id = $this->api_key;
        $test_mode  = $this->get_option('test_mode') === 'yes';

        $initiate_url = $test_mode? 'https://pay.zerth.online/pay/sandbox/api/v1/authentication/token' : 'https://pay.zerth.online/pay/api/v1/authentication/token';
        $token = $this->initialize_zerthpay(initiate_url: $initiate_url);

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
            'body'      => wp_json_encode($payload), // Encode payload as JSON
            'timeout'   => 250, // Maximum time in seconds to complete the request
            'sslverify' => true, // Ensure SSL certificate is verified
       );

        $response = wp_remote_post($full_url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log(sprintf('ZERTH Pay API Error: %s', $error_message)); // Log for debugging
            wc_add_notice(sprintf(__('ZERTH Pay API Error: %s', 'zerthpay-gateway'), $error_message), 'error');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code!== 200) {
            $error_message = isset($data['message'])? $data['message'] : __('Unknown ZERTH Pay API error.', 'zerthpay-gateway');
            error_log(sprintf('ZERTH Pay API responded with status %d: %s', $http_code, $error_message));
            wc_add_notice(sprintf(__('ZERTH Pay API responded with status %d: %s', 'zerthpay-gateway'), $http_code, $error_message), 'error');
            return false;
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
		// var_dump($response);

       if ($err) {
        return ['status'=> 'failed', 'res'=> "cURL Error #:" . $err];
      } else {
        return ['status'=> 'success', 'res'=>json_decode($response, true)];
      }

        // $body = json_encode($payload);

    //     $args = array(
    //         'method'    => 'POST',
    //         'headers'   => $headers,
    //         'body'      => $body,
    //         'timeout'   => 400,
    //    );

    //     $response = wp_remote_post($initiate_url, $args);
    //     var_dump($response);
        // var_dump($response);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log(sprintf('ZERTH Pay API Error: %s', $error_message)); // Log for debugging
            wc_add_notice(sprintf(__('ZERTH Pay API Error: %s', 'zerthpay-gateway'), $error_message), 'error');
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code!== 200) {
            $error_message = isset($response['message'])? $response['message'] : __('Unknown ZERTH Pay API error.', 'zerthpay-gateway');
            wc_add_notice(sprintf(__('ZERTH Pay API responded with status %d: %s', 'zerthpay-gateway'), $http_code, $error_message), 'error');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data;

    }




	public function handle_zerthpay_webhook( WP_REST_Request $request ){ 
		// return new WP_REST_Response(array('status' => 'success'), 200); // Respond with 200 OK

		// retrieve and parse this JSON data.
		// Inside zerthpay_handle_webhook function
		$body = $request->get_body(); // Retrieve the raw POST body
		$data = json_decode($body, true); // Decode JSON into an associative array

		if (empty($data) ||! is_array($data)) {
			// Log an error for malformed JSON or empty payload
			error_log('ZERTH Pay Webhook: Received empty or malformed payload.');
			return new WP_REST_Response(array('status' => 'error', 'message' => 'Invalid payload'), 400);
		}

		// At this point, $data contains the parsed ZERTH Pay transaction information.
		// For example: $transaction_id = $data['transaction_id'];
		//              $status = $data['status'];
		//              $order_id_from_zerthpay = $data['metadata']['woocommerce_order_id']; // Assuming this metadata is sent


		// Validate Webhook Payload using HTTPS, Check for Idempotency and Validate with WebHook Secret Key or HMAC security
		// After decoding $data
		// THIS WONT WORK - IMPLEMENT THE OPENSSL DECRYPTION THAT IS USED TO ENCRYPT THE DATA ON ZERTHPAY SERVER
		// $zerthpay_signature = $request->get_header('X-ZerthPay-Signature'); // Retrieve signature from header
		
		// $secret_key = $this->get_option('webhook_secret'); // Retrieve the stored secret key from plugin settings
		// For testing, you might temporarily hardcode it or retrieve it from a constant.

		// if (empty($zerthpay_signature) || empty($secret_key)) {
		// 	error_log('ZERTH Pay Webhook: Missing signature or secret key.');
		// 	return new WP_REST_Response(array('status' => 'error', 'message' => 'Unauthorized'), 401);
		// }

		// Calculate your own HMAC signature
		// $calculated_signature = hash_hmac('sha256', $body, $secret_key); // Use the raw body, not decoded $data

		// Compare signatures using a constant-time comparison to prevent timing attacks
		// if (! hash_equals($calculated_signature, $zerthpay_signature)) {
		// 	error_log('ZERTH Pay Webhook: Invalid signature.');
		// 	return new WP_REST_Response(array('status' => 'error', 'message' => 'Unauthorized'), 401);
		// }

		// Implement idempotency: Check if this transaction_id has already been processed
		$transaction_id = isset($data['id'])? sanitize_text_field($data['id']) : ''; // Check by 'id' only
		// $transaction_id = isset($data['transaction_id'])? sanitize_text_field($data['transaction_id']) : '';
		if (empty($transaction_id)) {
			error_log('ZERTH Pay Webhook: Missing transaction ID in payload for idempotency check.');
			return new WP_REST_Response(array('status' => 'error', 'message' => 'Missing Transaction ID'), 400);
		}

		if (get_transient('zerthpay_webhook_processed_'. $transaction_id)) {
			error_log('ZERTH Pay Webhook: Duplicate transaction ID received: '. $transaction_id);
			return new WP_REST_Response(array('status' => 'success', 'message' => 'Already processed'), 200);
		}
		set_transient('zerthpay_webhook_processed_'. $transaction_id, true, DAY_IN_SECONDS); // Store for a day to prevent replay attacks


		// Processing ZERTH Pay Transaction Events and Updating WooCommerce Order Statuses
		// Assuming $data contains the parsed webhook payload
		$order_id = isset($data['metadata']['woocommerce_order_id'])? intval($data['metadata']['woocommerce_order_id']) : 0;
		$zerthpay_status = isset($data['status'])? sanitize_text_field($data['status']) : '';
		$zerthpay_transaction_id = isset($data['transaction_id'])? sanitize_text_field($data['transaction_id']) : '';

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
			case 'completed':
				if (! $order->is_paid()) {
					$order->payment_complete($zerthpay_transaction_id); // Mark as paid
					$order->add_order_note(sprintf(__('ZERTH Pay webhook: Payment completed. Transaction ID: %s', 'zerthpay-gateway'), $zerthpay_transaction_id));
				}
				break;
			case 'failed':
				$order->update_status('failed', sprintf(__('ZERTH Pay webhook: Payment failed. Transaction ID: %s', 'zerthpay-gateway'), $zerthpay_transaction_id));
				break;
			case 'refunded':
				$order->update_status('refunded', sprintf(__('ZERTH Pay webhook: Payment refunded. Transaction ID: %s', 'zerthpay-gateway'), $zerthpay_transaction_id));
				// Additional logic might be required here for partial refunds or stock management.
				break;
			// Include additional cases for other ZERTH Pay statuses (e.g., 'pending', 'cancelled').
			default:
				$order->add_order_note(sprintf(__('ZERTH Pay webhook: Unknown status "%s" received for transaction ID: %s', 'zerthpay-gateway'), $zerthpay_status, $zerthpay_transaction_id));
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