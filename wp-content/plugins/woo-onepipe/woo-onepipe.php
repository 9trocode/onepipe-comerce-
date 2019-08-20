<?php
/*
	Plugin Name:            onepipe WooCommerce Payment Gateway
	Plugin URI:             https://onepipe.com
	Description:            WooCommerce payment gateway for onepipe
	Version:                5.4.2
	Author:                 Tunbosun Ayinla
	Author URI:             https://bosun.me
	License:                GPL-2.0+
	License URI:            http://www.gnu.org/licenses/gpl-2.0.txt
	WC requires at least:   3.0.0
	WC tested up to:        3.5.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_onepipe_MAIN_FILE', __FILE__ );
define( 'WC_onepipe_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

define( 'WC_onepipe_VERSION', '5.4.2' );

function tbz_wc_onepipe_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {

		require_once dirname( __FILE__ ) . '/includes/class-onepipe.php';

		require_once dirname( __FILE__ ) . '/includes/class-wc-subscriptions.php';

		require_once dirname( __FILE__ ) . '/includes/class-onepipe-custom-gateway.php';

		require_once dirname( __FILE__ ) . '/includes/custom-gateways/class-gateway-one.php';
		require_once dirname( __FILE__ ) . '/includes/custom-gateways/class-gateway-two.php';
		require_once dirname( __FILE__ ) . '/includes/custom-gateways/class-gateway-three.php';
		require_once dirname( __FILE__ ) . '/includes/custom-gateways/class-gateway-four.php';
		require_once dirname( __FILE__ ) . '/includes/custom-gateways/class-gateway-five.php';

	} else{

		require_once dirname( __FILE__ ) . '/includes/class-onepipe-deprecated.php';

	}

	require_once dirname( __FILE__ ) . '/includes/polyfill.php';

	add_filter( 'woocommerce_payment_gateways', 'tbz_wc_add_onepipe_gateway', 99 );

}
add_action( 'plugins_loaded', 'tbz_wc_onepipe_init', 99 );


/**
* Add Settings link to the plugin entry in the plugins menu
**/
function tbz_woo_onepipe_plugin_action_links( $links ) {

    $settings_link = array(
    	'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=onepipe' ) . '" title="View onepipe WooCommerce Settings">Settings</a>'
    );

    return array_merge( $links, $settings_link );

}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tbz_woo_onepipe_plugin_action_links' );


/**
* Add onepipe Gateway to WC
**/
function tbz_wc_add_onepipe_gateway( $methods ) {

	if ( class_exists( 'WC_Subscriptions_Order' ) && class_exists( 'WC_Payment_Gateway_CC' ) ) {
		$methods[] = 'Tbz_WC_Gateway_onepipe_Subscription';
	} else {
		$methods[] = 'Tbz_WC_onepipe_Gateway';
	}

	if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {

		if ( 'GHS' != get_woocommerce_currency() ) {

			$settings 		 = get_option( 'woocommerce_onepipe_settings', '' );
			$custom_gateways = isset( $settings['custom_gateways'] ) ? $settings['custom_gateways'] : '';

			switch ( $custom_gateways ) {
				case '5':
					$methods[] = 'Tbz_WC_onepipe_Gateway_One';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Two';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Three';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Four';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Five';
				break;
					case '4':
					$methods[] = 'Tbz_WC_onepipe_Gateway_One';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Two';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Three';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Four';
				break;
					case '3':
					$methods[] = 'Tbz_WC_onepipe_Gateway_One';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Two';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Three';
				break;
					case '2':
					$methods[] = 'Tbz_WC_onepipe_Gateway_One';
					$methods[] = 'Tbz_WC_onepipe_Gateway_Two';
					break;
				case '1':
					$methods[] = 'Tbz_WC_onepipe_Gateway_One';
					break;

				default:
					break;
			}

		}

	}

	return $methods;

}


/**
* Display the test mode notice
**/
function tbz_wc_onepipe_testmode_notice(){

	$onepipe_settings = get_option( 'woocommerce_onepipe_settings' );

	$test_mode 	= isset( $onepipe_settings['testmode'] ) ? $onepipe_settings['testmode'] : '';

	if ( 'yes' == $test_mode ) {
    ?>
	    <div class="update-nag">
	        onepipe testmode is still enabled, Click <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=onepipe' ) ?>">here</a> to disable it when you want to start accepting live payment on your site.
	    </div>
    <?php
	}
}
add_action( 'admin_notices', 'tbz_wc_onepipe_testmode_notice' );