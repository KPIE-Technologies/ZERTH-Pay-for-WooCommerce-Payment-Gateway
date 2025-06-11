<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_Zerth_Pay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'zerth_pay';
        $this->icon               = plugins_url('assets/images/logo.png', dirname(__FILE__)); // Path to your icon
        $this->has_fields         = false; // Set to true if you need custom fields on checkout
        $this->method_title       = __('ZERTH Pay Gateway', 'zerth-pay-gateway');
        $this->method_description = __('Accept payments securely in Naira, USD and Crypto via ZERTH Pay.', 'zerth-pay-gateway');

        // Define supported features
        $this->supports = array(
            'products',
            'refunds', // If ZERTH Pay API supports refunds
            'pre-orders', // If applicable
       );

        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled');

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_'. $this->id, array($this, 'process_admin_options'));
        // Additional hooks can be added here for frontend display or validation.
    }


// Inside WC_Gateway_Zerth_Pay class
public function init_form_fields() {
    $this->form_fields = array(
        'enabled' => array(
            'title'   => __('Enable/Disable', 'zerth-pay-gateway'),
            'type'    => 'checkbox',
            'label'   => __('Enable ZERTH Pay Gateway', 'zerth-pay-gateway'),
            'default' => 'yes'
       ),
        'title' => array(
            'title'       => __('Title', 'zerth-pay-gateway'),
            'type'        => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'zerth-pay-gateway'),
            'default'     => __('ZERTH Pay', 'zerth-pay-gateway'),
            'desc_tip'    => true,
       ),
        'description' => array(
            'title'       => __('Description', 'zerth-pay-gateway'),
            'type'        => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'zerth-pay-gateway'),
            'default'     => __('Pay securely Naira, UDS and Crypto with ZERTH Pay.', 'zerth-pay-gateway'),
       ),
        'client_id' => array(
            'title'       => __('ZERTH Pay Client Id', 'zerth-pay-gateway'),
            'type'        => 'password', // Using password type for sensitive keys
            'description' => __('Enter your ZERTH Pay Merchant Client Id. This can be found on your API settings page on the ZERTH Pay Merchant Dashboard.', 'zerth-pay-gateway'),
            'default'     => '',
            'desc_tip'    => true,
       ),
        'client_secret' => array(
            'title'       => __('ZERTH Pay Client Secret Key', 'zerth-pay-gateway'),
            'type'        => 'password', // Using password type for sensitive keys
            'description' => __('Enter your ZERTH Pay Merchant Client Secret Key. This can be found on your API settings page on the ZERTH Pay Merchant Dashboard.', 'zerth-pay-gateway'),
            'default'     => '',
            'desc_tip'    => true,
       ),
        'test_mode' => array(
            'title'   => __('Test Mode', 'zerth-pay-gateway'),
            'type'    => 'checkbox',
            'label'   => __('Enable Test Mode for ZERTH Pay transactions.', 'zerth-pay-gateway'),
            'default' => 'yes',
            'description' => __('If enabled, transactions will be processed in test mode. Disbale this section when you are ready to Go LIVE', 'zerth-pay-gateway'),
       ),
        'webhook_secret' => array(
            'title'       => __('Webhook Secret', 'zerth-pay-gateway'),
            'type'        => 'password',
            'description' => __('Enter the secret key you provided in your ZERTH Pay Merchant Dashboard for webhook validation. This is found in the API settings page.', 'zerth-pay-gateway'),
            'default'     => '',
            'desc_tip'    => true,
       ),
   );
}

// Inside WC_Gateway_Zerth_Pay class
public function payment_fields() {
    if ($this->description) {
        echo wpautop(wp_kses_post($this->description));
    }
    // If ZERTH Pay requires an iframe or specific fields, add their HTML here.
    // Example: echo '<div id="zerth-pay-iframe-container"></div>';
}

// Inside WC_Gateway_Zerth_Pay class
public function process_payment($order_id) {
    $order = wc_get_order($order_id);

    // 1. Prepare data for ZERTH Pay API
    $payload = $this->prepare_zerth_pay_request_data($order);

    // 2. Make API call to ZERTH Pay (details in Chapter 3)
    $response_data = $this->call_zerth_pay_api('payments/initiate', $payload);

    if ($response_data && isset($response_data['status']) && $response_data['status'] === 'success') {
        // Assuming ZERTH Pay provides a redirect URL for offsite payment
        $redirect_url = isset($response_data['redirect_url'])? $response_data['redirect_url'] : $this->get_return_url($order);

        // Mark order status as 'pending' initially, actual completion via webhook
        $order->update_status('pending-payment', __('Awaiting ZERTH Pay confirmation via webhook.', 'zerth-pay-gateway'));
        $order->add_order_note(sprintf(__('ZERTH Pay payment initiated. Transaction ID: %s. Awaiting webhook confirmation.', 'zerth-pay-gateway'), $response_data['transaction_id']));
        $order->set_transaction_id($response_data['transaction_id']);
        $order->save();

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);
        // Empty cart
        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $redirect_url
       );
    } else {
        // Payment initiation failed, handle errors
        $error_message = isset($response_data['message'])? $response_data['message'] : __('ZERTH Pay payment initiation failed. Please try again.', 'zerth-pay-gateway');
        wc_add_notice($error_message, 'error'); 
        $order->update_status('failed', $error_message); 
        return array(
            'result' => 'failure',
       );
    }
}

// Helper function to prepare data (example)
private function prepare_zerth_pay_request_data($order) {
    return array(
        'amount'      => $order->get_total(),
        'currency'    => $order->get_currency(),
        'order_id'    => $order->get_id(),
        'customer_id' => $order->get_customer_id(),
        'return_url'  => $this->get_return_url($order),
        'webhook_url' => get_rest_url(null, 'zerthpay/v1/webhook'), // Your webhook listener URL
        // Add more data as required by ZERTH Pay API
   );
}


// Here is where we make API call to ZERTH Pay's External API
// Inside WC_Gateway_Zerth_Pay class (or a private helper method)
private function call_zerth_pay_api($endpoint, $payload) {
    $client_id = $this->get_option('client_id');
    $test_mode  = $this->get_option('test_mode') === 'yes';

    $initiate_url = $test_mode? 'https://pay.zerth.online/pay/sandbox/api/v1/authentication/token' : 'https://pay.zerth.online/pay/api/v1/authentication/token';
    $token = $this->initialize_zerth_pay($initiate_url);
    $access_token  = $token['data']['access_token'];

    // Determine API URL based on test mode
    // $api_url = $test_mode? 'https://test-api.zerthpay.com/' : 'https://api.zerthpay.com/';
    $api_url = $test_mode? 'https://pay.zerth.online/pay/sandbox/api/v1/payment/create/' : 'https://pay.zerth.online/pay/api/v1/payment/create/';
    $full_url = trailingslashit($api_url);

    $headers = array(
        'Content-Type'  => 'application/json',
        // 'Authorization' => 'Bearer '. $api_key, // Or whatever ZERTH Pay uses (e.g., Basic Auth, custom header)
        'Authorization' => 'Bearer '. $access_token, // ZERTH Pay uses Bearer token gotten from the exchange API Key for token endpoint
        'X-Client-Id' =>  $client_id, 
        // A signature might need to be generated using the secret_key here, depending on ZERTH Pay's requirements.
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
        wc_add_notice(sprintf(__('ZERTH Pay API Error: %s', 'zerth-pay-gateway'), $error_message), 'error');
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check HTTP status code (e.g., 200 OK, 400 Bad Request, 500 Server Error)
    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code!== 200) {
        $error_message = isset($data['message'])? $data['message'] : __('Unknown ZERTH Pay API error.', 'zerth-pay-gateway');
        error_log(sprintf('ZERTH Pay API responded with status %d: %s', $http_code, $error_message)); // Log for debugging
        wc_add_notice(sprintf(__('ZERTH Pay API responded with status %d: %s', 'zerth-pay-gateway'), $http_code, $error_message), 'error');
        return false;
    }

    return $data; // Return decoded API response
}

public function initialize_zerth_pay($initiate_url){
    $headers = array(
        'Content-Type'  => 'application/x-www-form-urlencoded',
   );
        $payload = [
            'client_id' => $this->get_option('client_id'),
            'client_secret' => $this->get_option('client_secret')
        ];
    $args = array(
        'method'    => 'POST',
        'headers'   => $headers,
        'body'      => wp_json_encode($payload), 
        'timeout'   => 400, 
   );

    $response = wp_remote_post($initiate_url, $args); 

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log(sprintf('ZERTH Pay API Error: %s', $error_message)); // Log for debugging
        wc_add_notice(sprintf(__('ZERTH Pay API Error: %s', 'zerth-pay-gateway'), $error_message), 'error');
        return false;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code!== 200) {
        $error_message = isset($data['message'])? $data['message'] : __('Unknown ZERTH Pay API error.', 'zerth-pay-gateway');
        wc_add_notice(sprintf(__('ZERTH Pay API responded with status %d: %s', 'zerth-pay-gateway'), $http_code, $error_message), 'error');
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return $data;

}
}