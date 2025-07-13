/**
 * Zerthpay Payment Method for WooCommerce Blocks.
 *
 * This file is responsible for registering the Zerthpay payment method with the
 * WooCommerce Blocks checkout experience.
 */

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;
const { decodeEntities } = window.wp.htmlEntities;
const { createElement } = window.wp.element;
const { __ } = window.wp.i18n; // For internationalization in JavaScript.

// Get settings data passed from the PHP class.
const settings = getSetting("zerthpay_data", {});

// Fallback label if not provided by settings.
const label =
  decodeEntities(settings.title) || __("Pay with Zerthpay", "zerth-pay-payment-gateway");

/**
 * Zerthpay Payment Method Content (displayed on checkout).
 */
const Content = () => {
  // You can render custom HTML/React components here.
  // This content will appear when Zerthpay is selected on the checkout.
  return createElement(
    "div",
    null,
    decodeEntities(settings.description || "")
    // You can add more detailed instructions or fields here.
    // For example, if you collect a custom reference on the frontend:
    // createElement(
    // 	'p',
    // 	null,
    // 	createElement(
    // 		'label',
    // 		{ htmlFor: 'zerthpay-block-custom-field' },
    // 		__( 'Zerthpay Reference:', 'zerth-pay-payment-gateway' )
    // 	),
    // 	createElement( 'input', {
    // 		type: 'text',
    // 		id: 'zerthpay-block-custom-field',
    // 		className: 'input-text',
    // 		placeholder: __( 'Optional reference', 'zerth-pay-payment-gateway' ),
    // 		// For updating the payment data, you'd typically use a context consumer
    // 		// from @woocommerce/block-data or similar.
    // 		// onChange: ( e ) => { /* update checkout data */ }
    // 	})
    // )
  );
};

/**
 * Zerthpay Payment Method Label (displayed next to radio button).
 * Includes the icon if available.
 */
const Label = () => {
  if (settings.icon) {
    return createElement(
      "span",
      null,
      label,
      createElement("img", {
        src: settings.icon,
        alt: label,
        style: { float: "right", marginRight: "5px" }, // Basic styling.
      })
    );
  }
  return label;
};

// Register the payment method.
registerPaymentMethod({
  name: settings.name, // Must match the $name in Zerthpay_Blocks_Integration.
  label: createElement(Label, null),
  content: createElement(Content, null),
  edit: createElement(Content, null), // Content for the editor, often same as frontend.
  canMakePayment: (cartData) => {
    // Add logic here to determine if Zerthpay can be used.
    // For example, based on currency, total amount, shipping address, etc.
    // Example: return cartData.cartTotals.total_amount > 1000;
    return true; // Always allow for this basic example.
  },
  ariaLabel: label,
  supports: {
    features: settings.supports, // Features supported by your gateway (e.g., products, refunds).
  },
});
