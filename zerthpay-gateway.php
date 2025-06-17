<?php 

/**
 * Plugin Name: ZERTH Pay  Gateway
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

//  Exit if accessed directly 

if(!defined('ABSPATH')){
    exit('Cannot be accessed directly');
}

// define constants 
define('ZERTHPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZERTHPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

if(!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
    add_action('admin_notices', 'zerthpay_wc_notice');
} else {

    add_action('plugins_loaded', 'zerthpay_payment_init');
    add_filter('plugin_action_links_'. plugin_basename(__FILE__), 'zerthpay_settings_link');
    add_filter('woocommerce_payment_gateways', 'zerthpay_gateway_woocommerce_register');
}

// add action plugins 

function zerthpay_payment_init(){
   
        if(!class_exists('Zerthpay_Gateway')){
            include_once ZERTHPAY_PLUGIN_PATH . 'includes/main-file.php';
        }
    
}

function zerthpay_wc_notice(){
    ?> 
        <div class="notice notice-error is-dismissible">
            <p><?php _e('Zerthpay Gateway requires WooCommerce to be installed and active', 'zerthpay-gateway') ?></p>

        </div>
    <?php
}

function zerthpay_settings_link($links){
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=zerthpay_gateway"> Settings </a>';
    array_push($links, $settings_link);
    return $links;
}

function zerthpay_gateway_woocommerce_register($gateways){
    $gateways[] = 'Zerthpay_Gateway';
    return $gateways;
}

// if(!class_exists('Zerthpay_Gateway')){
//     include_once ZERTHPAY_PLUGIN_PATH . 'includes/main-file.php';
// }