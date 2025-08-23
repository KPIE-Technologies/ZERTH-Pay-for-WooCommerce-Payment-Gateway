/**
 * Zerthpay Checkout Scripts for Traditional Checkout.
 *
 * This file is for handling any client-side interactions on the
 * traditional WooCommerce checkout page, e.g., form validation,
 * tokenization, or custom field behavior.
 */

jQuery(function ($) {
  "use strict";

  var zerthpay = {
    init: function () {
      // This script only applies to the traditional checkout form.
      // It should not interfere with the block checkout.
      if (
        $("form.checkout").length &&
        !$("body").hasClass("woocommerce-block-checkout")
      ) {
        $("form.checkout").on(
          "checkout_place_order_" + zerthpay_params.gateway_id,
          this.form_submit
        );
      }
    },

    form_submit: function () {
      // Example: Perform some client-side validation or action.
      // This is where you would typically:
      // 1. Collect data from custom fields (e.g., credit card details if direct).
      // 2. Send it to Zerthpay's client-side SDK for tokenization.
      // 3. Add the returned token to a hidden field before submitting the form.

      // For this example, we'll just log a message.
      console.log(
        "ZERTH Pay Payment Gateway selected. Preparing to submit order (traditional checkout)..."
      );

      // Return true to allow form submission, or false to stop it.
      // If you need an AJAX call, you would make it here and then
      // call the checkout form submission (e.g., $('form.checkout').submit())
      // after receiving a token/response.
      return true;
    },
  };

  zerthpay.init();
});
