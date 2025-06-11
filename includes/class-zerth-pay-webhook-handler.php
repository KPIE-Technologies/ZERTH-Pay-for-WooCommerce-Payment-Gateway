<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// In zerth-pay-gateway.php or includes/class-zerth-pay-webhook-handler.php
add_action('rest_api_init', 'zerth_pay_register_webhook_route');

function zerth_pay_register_webhook_route() {
    register_rest_route('zerthpay/v1', '/webhook', array(
        'methods'             => 'POST', // Webhooks typically send POST requests [8, 22]
        'callback'            => 'zerth_pay_handle_webhook',
        'permission_callback' => '__return_true', // Authenticity will be validated in the callback
   ));
}

function zerth_pay_handle_webhook(WP_REST_Request $request) {
    // Placeholder for webhook processing
    // Details will be covered in "Receiving and parsing incoming webhook data" and "Validating webhook authenticity"
    return new WP_REST_Response(array('status' => 'success'), 200); // Respond with 200 OK [22]

    // retrieve and parse this JSON data.
    // Inside zerth_pay_handle_webhook function
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
    $zerth_pay_signature = $request->get_header('X-ZerthPay-Signature'); // Retrieve signature from header
    $secret_key = $this->get_option('webhook_secret'); // Retrieve the stored secret key from plugin settings

    if (empty($zerth_pay_signature) || empty($secret_key)) {
        error_log('ZERTH Pay Webhook: Missing signature or secret key.');
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Unauthorized'), 401);
    }

    // Calculate your own HMAC signature
    $calculated_signature = hash_hmac('sha256', $body, $secret_key); // Use the raw body, not decoded $data

    // Compare signatures using a constant-time comparison to prevent timing attacks [16]
    if (! hash_equals($calculated_signature, $zerth_pay_signature)) {
        error_log('ZERTH Pay Webhook: Invalid signature.');
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Unauthorized'), 401);
    }

    // Implement idempotency: Check if this transaction_id has already been processed [22]
    $transaction_id = isset($data['id'])? sanitize_text_field($data['id']) : ''; // Check by 'id' only
    // $transaction_id = isset($data['transaction_id'])? sanitize_text_field($data['transaction_id']) : '';
    if (empty($transaction_id)) {
        error_log('ZERTH Pay Webhook: Missing transaction ID in payload for idempotency check.');
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Missing Transaction ID'), 400);
    }

    if (get_transient('zerth_pay_webhook_processed_'. $transaction_id)) {
        error_log('ZERTH Pay Webhook: Duplicate transaction ID received: '. $transaction_id);
        return new WP_REST_Response(array('status' => 'success', 'message' => 'Already processed'), 200);
    }
    set_transient('zerth_pay_webhook_processed_'. $transaction_id, true, DAY_IN_SECONDS); // Store for a day to prevent replay attacks


    // Processing ZERTH Pay Transaction Events and Updating WooCommerce Order Statuses
    // Assuming $data contains the parsed webhook payload
    $order_id = isset($data['metadata']['woocommerce_order_id'])? intval($data['metadata']['woocommerce_order_id']) : 0;
    $zerth_pay_status = isset($data['status'])? sanitize_text_field($data['status']) : '';
    $zerth_pay_transaction_id = isset($data['transaction_id'])? sanitize_text_field($data['transaction_id']) : '';

    if (! $order_id) {
        error_log('ZERTH Pay Webhook: Missing WooCommerce Order ID in payload.');
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Missing Order ID'), 400);
    }

    $order = wc_get_order($order_id);
    if (! $order) {
        error_log('ZERTH Pay Webhook: Order not found for ID '. $order_id);
        return new WP_REST_Response(array('status' => 'error', 'message' => 'Order not found'), 404);
    }

    switch ($zerth_pay_status) {
        case 'completed':
            if (! $order->is_paid()) {
                $order->payment_complete($zerth_pay_transaction_id); // Mark as paid [5, 6]
                $order->add_order_note(sprintf(__('ZERTH Pay webhook: Payment completed. Transaction ID: %s', 'zerth-pay-gateway'), $zerth_pay_transaction_id));
            }
            break;
        case 'failed':
            $order->update_status('failed', sprintf(__('ZERTH Pay webhook: Payment failed. Transaction ID: %s', 'zerth-pay-gateway'), $zerth_pay_transaction_id));
            break;
        case 'refunded':
            $order->update_status('refunded', sprintf(__('ZERTH Pay webhook: Payment refunded. Transaction ID: %s', 'zerth-pay-gateway'), $zerth_pay_transaction_id));
            // Additional logic might be required here for partial refunds or stock management.
            break;
        // Include additional cases for other ZERTH Pay statuses (e.g., 'pending', 'cancelled').
        default:
            $order->add_order_note(sprintf(__('ZERTH Pay webhook: Unknown status "%s" received for transaction ID: %s', 'zerth-pay-gateway'), $zerth_pay_status, $zerth_pay_transaction_id));
            break;
    }

    return new WP_REST_Response(array('status' => 'success'), 200); // Always respond with 200 OK [22]

}