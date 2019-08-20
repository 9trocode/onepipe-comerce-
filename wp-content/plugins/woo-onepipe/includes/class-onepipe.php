<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tbz_WC_onepipe_Gateway extends WC_Payment_Gateway_CC {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id		   			= 'onepipe';
		$this->method_title         = 'onepipe';
		$this->method_description   = sprintf( 'onepipe provide merchants with the tools and services needed to accept online payments from local and international customers using Mastercard, Visa, Verve Cards and Bank Accounts. <a href="%1$s" target="_blank">Sign up</a> for a onepipe account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'https://onepipe.com', 'https://dashboard.onepipe.com/#/settings/developer' );
		$this->has_fields           = true;

		$this->supports             = array(
			'products',
			'tokenization',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer'
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Get setting values
		$this->title 				= $this->get_option( 'title' );
		$this->description 			= $this->get_option( 'description' );
		$this->enabled            	= $this->get_option( 'enabled' );
		$this->testmode             = $this->get_option( 'testmode' ) === 'yes' ? true : false;

		$this->payment_page         = $this->get_option( 'payment_page' );

		$this->test_public_key  	= $this->get_option( 'test_public_key' );
		$this->test_secret_key  	= $this->get_option( 'test_secret_key' );

		$this->live_public_key  	= $this->get_option( 'live_public_key' );
		$this->live_secret_key  	= $this->get_option( 'live_secret_key' );

		$this->saved_cards         	= $this->get_option( 'saved_cards' ) === 'yes' ? true : false;

		$this->split_payment        = $this->get_option( 'split_payment' ) === 'yes' ? true : false;
		$this->subaccount_code      = $this->get_option( 'subaccount_code' );
		$this->charges_account      = $this->get_option( 'split_payment_charge_account' );
		$this->transaction_charges  = $this->get_option( 'split_payment_transaction_charge' );

		$this->custom_metadata      = $this->get_option( 'custom_metadata' ) === 'yes' ? true : false;

		$this->meta_order_id      	= $this->get_option( 'meta_order_id' ) === 'yes' ? true : false;
		$this->meta_name      		= $this->get_option( 'meta_name' ) === 'yes' ? true : false;
		$this->meta_email      		= $this->get_option( 'meta_email' ) === 'yes' ? true : false;
		$this->meta_phone      		= $this->get_option( 'meta_phone' ) === 'yes' ? true : false;
		$this->meta_billing_address = $this->get_option( 'meta_billing_address' ) === 'yes' ? true : false;
		$this->meta_shipping_address= $this->get_option( 'meta_shipping_address' ) === 'yes' ? true : false;
		$this->meta_products      	= $this->get_option( 'meta_products' ) === 'yes' ? true : false;

		$this->public_key      		= $this->testmode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key      		= $this->testmode ? $this->test_secret_key : $this->live_secret_key;

		// Hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_tbz_wc_onepipe_gateway', array( $this, 'verify_onepipe_transaction' ) );

		// Webhook listener/API hook
		add_action( 'woocommerce_api_tbz_wc_onepipe_webhook', array( $this, 'process_webhooks' ) );

		// Check if the gateway can be used
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = false;
		}

	}


	/**
	 * Check if this gateway is enabled and available in the user's country.
	 */
	public function is_valid_for_use() {

		if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_onepipe_supported_currencies', array( 'NGN', 'USD', 'GBP', 'GHS' ) ) ) ) {

			$this->msg = 'Onepipe does not support your store currency. Kindly set it to either NGN (&#8358), GHS (&#x20b5;), USD (&#36;) or GBP (&#163;) <a href="' . admin_url( 'admin.php?page=wc-settings&tab=general' ) . '">here</a>';

			return false;

		}

		return true;

	}


	/**
	 * Display onepipe payment icon
	 */
	public function get_icon() {

		$icon  = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/onepipe-wc.png' , WC_onepipe_MAIN_FILE ) ) . '" alt="cards" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

	}


	/**
	 * Check if onepipe merchant details is filled
	 */
	public function admin_notices() {

		if ( $this->enabled == 'no' ) {
			return;
		}

		// Check required fields
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			echo '<div class="error"><p>' . sprintf( 'Please enter your onepipe merchant details <a href="%s">here</a> to be able to use the onepipe WooCommerce plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=onepipe' ) ) . '</p></div>';
			return;
		}

	}


	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {

		if ( $this->enabled == "yes" ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;

	}


    /**
     * Admin Panel Options
    */
    public function admin_options() {

    	?>

    	<h2>onepipe
		<?php
			if ( function_exists( 'wc_back_link' ) ) {
				wc_back_link( 'Return to payments', admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
			}
		?>
		</h2>

        <h4>Optional: To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="https://dashboard.onepipe.co/#/settings/developer" target="_blank" rel="noopener noreferrer">here</a> to the URL below<strong style="color: red"><pre><code><?php echo WC()->api_request_url( 'Tbz_WC_onepipe_Webhook' ); ?></code></pre></strong></h4>

        <?php

		if ( $this->is_valid_for_use() ){

            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';

        }
		else {	 ?>
			<div class="inline error"><p><strong>Onepipe Payment Gateway Disabled</strong>: <?php echo $this->msg ?></p></div>

		<?php }

    }


	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$form_fields = array(
			'enabled' => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable onepipe',
				'type'        => 'checkbox',
				'description' => 'Enable onepipe as a payment option on the checkout page.',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'title' => array(
				'title' 		=> 'Title',
				'type' 			=> 'text',
				'description' 	=> 'This controls the payment method title which the user sees during checkout.',
    			'desc_tip'      => true,
				'default' 		=> 'Debit/Credit Cards'
			),
			'description' => array(
				'title' 		=> 'Description',
				'type' 			=> 'textarea',
				'description' 	=> 'This controls the payment method description which the user sees during checkout.',
    			'desc_tip'      => true,
				'default' 		=> 'Make payment using your debit and credit cards'
			),
			'testmode' => array(
				'title'       => 'Test mode',
				'label'       => 'Enable Test Mode',
				'type'        => 'checkbox',
				'description' => 'Test mode enables you to test payments before going live. <br />Once the LIVE MODE is enabled on your onepipe account uncheck this.',
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'payment_page' => array(
				'title'       => 'Payment Page',
				'type'        => 'select',
				'description' => 'Inline shows the payment popup on the page while Inline Embed shows the payment page directly on the page',
				'default'     => '',
				'desc_tip'    => false,
				'options'     => array(
					''   		=> 'Select One',
					'inline'   	=> 'Inline',
					'embed' 	=> 'Inline Embed'
				)
			),
			'test_secret_key' => array(
				'title'       => 'Test Secret Key',
				'type'        => 'text',
				'description' => 'Enter your Test Secret Key here',
				'default'     => ''
			),
			'test_public_key' => array(
				'title'       => 'Test Public Key',
				'type'        => 'text',
				'description' => 'Enter your Test Public Key here.',
				'default'     => ''
			),
			'live_secret_key' => array(
				'title'       => 'Live Secret Key',
				'type'        => 'text',
				'description' => 'Enter your Live Secret Key here.',
				'default'     => ''
			),
			'live_public_key' => array(
				'title'       => 'Live Public Key',
				'type'        => 'text',
				'description' => 'Enter your Live Public Key here.',
				'default'     => ''
			),
			'split_payment' => array(
				'title'       => 'Split Payment',
				'label'       => 'Enable Split Payment',
				'type'        => 'checkbox',
				'description' => '',
				'class'       => 'woocommerce_onepipe_split_payment',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'subaccount_code' => array(
				'title'       => 'Subaccount Code',
				'type'        => 'text',
				'description' => 'Enter the subaccount code here.',
				'class'       => 'woocommerce_onepipe_subaccount_code',
				'default'     => ''
			),
			'split_payment_transaction_charge' => array(
				'title'       => 'Split Payment Transaction Charge',
				'type'        => 'number',
				'description' => 'A flat fee to charge the subaccount for this transaction, in Naira (&#8358;). This overrides the split percentage set when the subaccount was created. Ideally, you will need to use this if you are splitting in flat rates (since subaccount creation only allows for percentage split). e.g. 100 for a &#8358;100 flat fee.',
				'class'       => 'woocommerce_onepipe_split_payment_transaction_charge',
				'default'     => '',
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 0.1,
				),
				'desc_tip'    => false
			),
			'split_payment_charge_account' => array(
				'title'       => 'onepipe Charges Bearer',
				'type'        => 'select',
				'description' => 'Who bears onepipe charges?',
				'class'       => 'woocommerce_onepipe_split_payment_charge_account',
				'default'     => '',
				'desc_tip'    => false,
				'options'     => array(
					''           => 'Select One',
					'account'    => 'Account',
					'subaccount' => 'Subaccount',
				),
			),
			'custom_gateways' => array(
				'title'       => 'Additional onepipe Gateways',
				'type'        => 'select',
				'description' => 'Create additional custom onepipe based gateways. This allows you to create additional onepipe gateways using custom filters. You can create a gateway that accepts only verve cards, a gateway that accepts only bank payment, a gateway that accepts a specific bank issued cards.',
				'default'     => '',
				'desc_tip'    => true,
				'options' => array(
					''		=> 'Select One',
					'1'  	=> '1 gateway',
					'2'		=> '2 gateways',
					'3' 	=> '3 gateways',
					'4' 	=> '4 gateways',
					'5' 	=> '5 gateways',
				),
			),
			'saved_cards' 	  => array(
				'title'       => 'Saved Cards',
				'label'       => 'Enable Payment via Saved Cards',
				'type'        => 'checkbox',
				'description' => 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on onepipe servers, not on your store.<br>Note that you need to have a valid SSL certificate installed.',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'custom_metadata' 	  => array(
				'title'       => 'Custom Metadata',
				'label'       => 'Enable Custom Metadata',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-metadata',
				'description' => 'If enabled, you will be able to send more information about the order to onepipe.',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_order_id'  => array(
				'title'       => 'Order ID',
				'label'       => 'Send Order ID',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-meta-order-id',
				'description' => 'If checked, the Order ID will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_name'  => array(
				'title'       => 'Customer Name',
				'label'       => 'Send Customer Name',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-meta-name',
				'description' => 'If checked, the customer full name will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_email'  => array(
				'title'       => 'Customer Email',
				'label'       => 'Send Customer Email',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-meta-email',
				'description' => 'If checked, the customer email address will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_phone'  => array(
				'title'       => 'Customer Phone',
				'label'       => 'Send Customer Phone',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-meta-phone',
				'description' => 'If checked, the customer phone will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_billing_address'  => array(
				'title'       => 'Order Billing Address',
				'label'       => 'Send Order Billing Address',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-meta-billing-address',
				'description' => 'If checked, the order billing address will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_shipping_address'  => array(
				'title'       => 'Order Shipping Address',
				'label'       => 'Send Order Shipping Address',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-meta-shipping-address',
				'description' => 'If checked, the order shipping address will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_products'  => array(
				'title'       => 'Product(s) Purchased',
				'label'       => 'Send Product(s) Purchased',
				'type'        => 'checkbox',
				'class'       => 'wc-onepipe-meta-products',
				'description' => 'If checked, the product(s) purchased will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
		);

		if ( 'GHS' == get_woocommerce_currency() ) {
			unset( $form_fields['custom_gateways'] );
		}

		$this->form_fields = $form_fields;

	}


	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {

		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}

		if ( ! is_ssl() ){
			return;
		}

		if ( $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards && is_user_logged_in() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		}

	}


	/**
	 * Outputs scripts used for onepipe payment
	 */
	public function payment_scripts() {

		if ( ! is_checkout_pay_page() ) {
			return;
		}

		if ( $this->enabled === 'no' ) {
			return;
		}

		$order_key 		= urldecode( $_GET['key'] );
		$order_id  		= absint( get_query_var( 'order-pay' ) );

		$order  		= wc_get_order( $order_id );

		$payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

		if( $this->id !== $payment_method ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'jquery' );

		wp_enqueue_script( 'onepipe', 'https://staging.alpha.js.onepipe.io/v1/', array( 'jquery' ), WC_onepipe_VERSION, false );

		wp_enqueue_script( 'wc_onepipe', plugins_url( 'assets/js/onepipe'. $suffix . '.js', WC_onepipe_MAIN_FILE ), array( 'jquery', 'onepipe' ), WC_onepipe_VERSION, false );

		$onepipe_params = array(
			'key'	=> $this->public_key
		);

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email  		= method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;

			$amount 		= $order->get_total() * 100;

			$txnref		 	= $order_id . '_' .time();

			$the_order_id 	= method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
	        $the_order_key 	= method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;

			if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

				$onepipe_params['email']           = $email;
				$onepipe_params['amount']          = $amount;
				$onepipe_params['txnref']          = $txnref;
				$onepipe_params['pay_page']		= $this->payment_page;
				$onepipe_params['currency']		= get_woocommerce_currency();
				$onepipe_params['bank_channel']	= 'true';
				$onepipe_params['card_channel']	= 'true';

			}

			if ( $this->split_payment ) {

				$onepipe_params['subaccount_code']     = $this->subaccount_code;
				$onepipe_params['charges_account']     = $this->charges_account;

				if ( empty( $this->transaction_charges ) ) {
					$onepipe_params['transaction_charges'] = '';
				} else {
					$onepipe_params['transaction_charges'] = $this->transaction_charges * 100;
				}

			}

			if ( $this->custom_metadata ) {

				if ( $this->meta_order_id ) {

					$onepipe_params['meta_order_id'] = $order_id;

				}

				if ( $this->meta_name ) {

					$first_name = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
					$last_name  = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;

					$onepipe_params['meta_name'] = $first_name . ' ' . $last_name;

				}

				if ( $this->meta_email ) {

					$onepipe_params['meta_email'] = $email;

				}

				if ( $this->meta_phone ) {

					$billing_phone = method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;

					$onepipe_params['meta_phone'] = $billing_phone;

				}

				if ( $this->meta_products ) {

					$line_items = $order->get_items();

					$products = '';

					foreach ( $line_items as $item_id => $item ) {
						$name     = $item['name'];
						$quantity = $item['qty'];
						$products .= $name . ' (Qty: ' . $quantity . ')';
						$products .= ' | ';
					}

					$products = rtrim( $products, ' | ' );

					$onepipe_params['meta_products'] = $products;

				}

				if ( $this->meta_billing_address ) {

					$billing_address = $order->get_formatted_billing_address();
					$billing_address = esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );

					$onepipe_params['meta_billing_address'] = $billing_address;

				}

				if ( $this->meta_shipping_address ) {

					$shipping_address = $order->get_formatted_shipping_address();
					$shipping_address = esc_html( preg_replace( '#<br\s*/?>#i', ', ', $shipping_address ) );

					if ( empty( $shipping_address ) ) {

						$billing_address = $order->get_formatted_billing_address();
						$billing_address = esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );

						$shipping_address = $billing_address;

					}

					$onepipe_params['meta_shipping_address'] = $shipping_address;

				}

			}

			update_post_meta( $order_id, '_onepipe_txn_ref', $txnref );

		}

		wp_localize_script( 'wc_onepipe', 'wc_onepipe_params', $onepipe_params );

	}

	/**
	 * Load admin scripts
	 */
	public function admin_scripts() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$onepipe_admin_params = array(
			'plugin_url'	=> WC_onepipe_URL
		);

		wp_enqueue_script( 'wc_onepipe_admin', plugins_url( 'assets/js/onepipe-admin' . $suffix . '.js', WC_onepipe_MAIN_FILE ), array(), WC_onepipe_VERSION, true );

		wp_localize_script( 'wc_onepipe_admin', 'wc_onepipe_admin_params', $onepipe_admin_params );

	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id ) {

		if ( isset( $_POST['wc-' . $this->id . '-payment-token'] ) && 'new' !== $_POST['wc-' . $this->id . '-payment-token'] ) {

			$token_id = wc_clean( $_POST['wc-'. $this->id .'-payment-token'] );
			$token    = WC_Payment_Tokens::get( $token_id );

			if ( $token->get_user_id() !== get_current_user_id() ) {

				wc_add_notice( 'Invalid token ID', 'error' );

				return;

			} else {

				$status = $this->process_token_payment( $token->get_token(), $order_id );

				if( $status ) {

					$order = wc_get_order( $order_id );

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order )
					);

				}

			}
		} else {

			if ( is_user_logged_in() && isset( $_POST['wc-'. $this->id .'-new-payment-method'] ) && true === (bool) $_POST['wc-'. $this->id .'-new-payment-method'] && $this->saved_cards ) {

				update_post_meta( $order_id, '_wc_onepipe_save_card', true );

			}

			$order = wc_get_order( $order_id );

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);

		}

	}


	/**
	 * Process a token payment
	 */
	public function process_token_payment( $token, $order_id ) {

		if ( $token && $order_id ) {

			$order            = wc_get_order( $order_id );

			$email            = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;

			$order_amount     = method_exists( $order, 'get_total' ) ? $order->get_total() : $order->order_total;
			$order_amount     = $order_amount * 100;

			$onepipe_url   = 'https://api.onepipe.co/transaction/charge_authorization';

			$headers = array(
				'Content-Type'	=> 'application/json',
				'Authorization' => 'Bearer ' . $this->secret_key
			);

			$body = array(
				'email'						=> $email,
				'amount'					=> $order_amount,
				'authorization_code'		=> $token
			);

			$args = array(
				'body'		=> json_encode( $body ),
				'headers'	=> $headers,
				'timeout'	=> 60
			);

			$request = wp_remote_post( $onepipe_url, $args );

	        if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

            	$onepipe_response = json_decode( wp_remote_retrieve_body( $request ) );

				if ( 'success' == $onepipe_response->data->status ) {

			        $order              = wc_get_order( $order_id );

			        if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

			        	wp_redirect( $this->get_return_url( $order ) );

						exit;

			        }

	        		$order_total        = $order->get_total();

					$order_currency     = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

					$currency_symbol    = get_woocommerce_currency_symbol( $order_currency );

	        		$amount_paid        = $onepipe_response->data->amount / 100;

	        		$onepipe_ref       = $onepipe_response->data->reference;

					$payment_currency   = $onepipe_response->data->currency;

        			$gateway_symbol     = get_woocommerce_currency_symbol( $payment_currency );

					// check if the amount paid is equal to the order amount.
					if ( $amount_paid < $order_total ) {

						$order->update_status( 'on-hold', '' );

						add_post_meta( $order_id, '_transaction_id', $onepipe_ref, true );

						$notice = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
						$notice_type = 'notice';

						// Add Customer Order Note
	                    $order->add_order_note( $notice, 1 );

	                    // Add Admin Order Note
	                    $order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>'. $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>'. $currency_symbol . $order_total . '</strong><br />onepipe Transaction Reference: '.$onepipe_ref );

						wc_add_notice( $notice, $notice_type );

					} else {

						if ( $payment_currency !== $order_currency ) {

							$order->update_status( 'on-hold', '' );

							update_post_meta( $order_id, '_transaction_id', $onepipe_ref );

							$notice = 'Thank you for shopping with us.<br />Your payment was successful, but the payment currency is different from the order currency.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
							$notice_type = 'notice';

							// Add Customer Order Note
		                    $order->add_order_note( $notice, 1 );

			                // Add Admin Order Note
		                	$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Order currency is different from the payment currency.<br /> Order Currency is <strong>'. $order_currency . ' ('. $currency_symbol . ')</strong> while the payment currency is <strong>'. $payment_currency . ' ('. $gateway_symbol . ')</strong><br /><strong>onepipe Transaction Reference:</strong> ' . $onepipe_ref );

							function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

							wc_add_notice( $notice, $notice_type );

						} else {

							$order->payment_complete( $onepipe_ref );

							$order->add_order_note( sprintf( 'Payment via onepipe successful (Transaction Reference: %s)', $onepipe_ref ) );

						}

					}

					$this->save_subscription_payment_token( $order_id, $onepipe_response );

					wc_empty_cart();

					return true;

				} else {

					$order_notice  = 'Payment was declined by onepipe.';
					$failed_notice = 'Payment failed using the saved card. Kindly use another payment option.';

					if ( isset( $onepipe_response->data->gateway_response ) && ! empty ( $onepipe_response->data->gateway_response ) ) {

						$order_notice  = 'Payment was declined by onepipe. Reason: ' . $onepipe_response->data->gateway_response . '.';
						$failed_notice = 'Payment failed using the saved card. Reason: ' . $onepipe_response->data->gateway_response . '. Kindly use another payment option.';

					}

					$order->update_status( 'failed', $order_notice );

					wc_add_notice( $failed_notice, 'error' );

					return false;

				}

	        }
		} else {

			wc_add_notice( 'Payment Failed.', 'error' );

		}

	}


	/**
	 * Show new card can only be added when placing an order notice
	 */
	public function add_payment_method() {

		wc_add_notice( 'You can only add a new card when placing an order.', 'error' );

		return;

	}


	/**
	 * Displays the payment page
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( 'embed' == $this->payment_page ) {

			echo '<p style="text-align: center; font-weight: bold;">Thank you for your order, please make payment below using Onepipe.</p>';

			echo '<div id="onepipeWooCommerceEmbedContainer"></div>';

			echo '<div id="onepipe_form"><form id="order_review" method="post" action="'. WC()->api_request_url( 'Tbz_WC_onepipe_Gateway' ) .'"></form>
				<a href="' . esc_url( $order->get_cancel_order_url() ) . '" style="text-align:center; color: #EF3315; display: block; outline: none;">Cancel order &amp; restore cart</a></div>
				';

		} else {

			echo '<p>Thank you for your order, please click the button below to pay with Onepipe.</p>';

			echo '<div id="onepipe_form"><form id="order_review" method="post" action="'. WC()->api_request_url( 'Tbz_WC_onepipe_Gateway' ) .'"></form><button class="button alt" id="onepipe-payment-button">Pay Now</button> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order &amp; restore cart</a></div>
				';

		}

	}


	/**
	 * Verify onepipe payment
	 */
	public function verify_onepipe_transaction() {

		@ob_clean();

		if ( isset( $_REQUEST['onepipe_txnref'] ) ){

			$onepipe_url = 'https://api.onepipe.co/transaction/verify/' . $_REQUEST['onepipe_txnref'];

			$headers = array(
				'Authorization' => 'Bearer ' . $this->secret_key
			);

			$args = array(
				'headers'	=> $headers,
				'timeout'	=> 60
			);

			$request = wp_remote_get( $onepipe_url, $args );

	        if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

            	$onepipe_response = json_decode( wp_remote_retrieve_body( $request ) );

				if ( 'success' == $onepipe_response->data->status ) {

					$order_details 	= explode( '_', $onepipe_response->data->reference );

					$order_id 		= (int) $order_details[0];

			        $order 			= wc_get_order( $order_id );

			        if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

			        	wp_redirect( $this->get_return_url( $order ) );

						exit;

			        }

	        		$order_total        = $order->get_total();

					$order_currency     = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

					$currency_symbol	= get_woocommerce_currency_symbol( $order_currency );

	        		$amount_paid        = $onepipe_response->data->amount / 100;

	        		$onepipe_ref       = $onepipe_response->data->reference;

					$payment_currency   = $onepipe_response->data->currency;

        			$gateway_symbol     = get_woocommerce_currency_symbol( $payment_currency );

					// check if the amount paid is equal to the order amount.
					if ( $amount_paid < $order_total ) {

						$order->update_status( 'on-hold', '' );

						add_post_meta( $order_id, '_transaction_id', $onepipe_ref, true );

						$notice = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
						$notice_type = 'notice';

						// Add Customer Order Note
	                    $order->add_order_note( $notice, 1 );

	                    // Add Admin Order Note
	                    $order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>&#8358;'.$amount_paid.'</strong> while the total order amount is <strong>&#8358;'.$order_total.'</strong><br />onepipe Transaction Reference: '.$onepipe_ref );

						function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

						wc_add_notice( $notice, $notice_type );

					} else {

						if( $payment_currency !== $order_currency ) {

							$order->update_status( 'on-hold', '' );

							update_post_meta( $order_id, '_transaction_id', $onepipe_ref );

							$notice = 'Thank you for shopping with us.<br />Your payment was successful, but the payment currency is different from the order currency.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
							$notice_type = 'notice';

							// Add Customer Order Note
		                    $order->add_order_note( $notice, 1 );

			                // Add Admin Order Note
		                	$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Order currency is different from the payment currency.<br /> Order Currency is <strong>'. $order_currency . ' ('. $currency_symbol . ')</strong> while the payment currency is <strong>'. $payment_currency . ' ('. $gateway_symbol . ')</strong><br /><strong>onepipe Transaction Reference:</strong> ' . $onepipe_ref );

							function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

							wc_add_notice( $notice, $notice_type );

						} else {

							$order->payment_complete( $onepipe_ref );

							$order->add_order_note( sprintf( 'Payment via onepipe successful (Transaction Reference: %s)', $onepipe_ref ) );

						}

					}

					$this->save_card_details( $onepipe_response, $order->get_user_id(), $order_id );

					wc_empty_cart();

				} else {

					$order_details 	= explode( '_', $_REQUEST['onepipe_txnref'] );

					$order_id 		= (int) $order_details[0];

			        $order 			= wc_get_order( $order_id );

					$order->update_status( 'failed', 'Payment was declined by onepipe.' );

				}

	        }

			wp_redirect( $this->get_return_url( $order ) );

			exit;
		}

		wp_redirect( wc_get_page_permalink( 'cart' ) );

		exit;

	}


	/**
	 * Process Webhook
	 */
	public function process_webhooks() {

		if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) || ! array_key_exists('HTTP_X_onepipe_SIGNATURE', $_SERVER) ) {
			exit;
		}

	    $json = file_get_contents( "php://input" );

		// validate event do all at once to avoid timing attack
		if ( $_SERVER['HTTP_X_onepipe_SIGNATURE'] !== hash_hmac( 'sha512', $json, $this->secret_key ) ) {
			exit;
		}

	    $event = json_decode( $json );

	    if ( 'charge.success' == $event->event ) {

			http_response_code( 200 );

			$order_details 		= explode( '_', $event->data->reference );

			$order_id 			= (int) $order_details[0];

	        $order 				= wc_get_order($order_id);

	        $onepipe_txn_ref 	= get_post_meta( $order_id, '_onepipe_txn_ref', true );

	        if ( $event->data->reference != $onepipe_txn_ref ) {
	        	exit;
	        }

	        if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
				exit;
	        }

			$order_currency     = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

			$currency_symbol    = get_woocommerce_currency_symbol( $order_currency );

    		$order_total        = $order->get_total();

    		$amount_paid        = $event->data->amount / 100;

    		$onepipe_ref       = $event->data->reference;

			$payment_currency   = $event->data->currency;

        	$gateway_symbol     = get_woocommerce_currency_symbol( $payment_currency );

			// check if the amount paid is equal to the order amount.
			if ( $amount_paid < $order_total ) {

				$order->update_status( 'on-hold', '' );

				add_post_meta( $order_id, '_transaction_id', $onepipe_ref, true );

				$notice = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
				$notice_type = 'notice';

				// Add Customer Order Note
                $order->add_order_note( $notice, 1 );

                // Add Admin Order Note
                $order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>'. $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>'. $currency_symbol . $order_total . '</strong><br />onepipe Transaction Reference: '.$onepipe_ref );

				function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

				wc_add_notice( $notice, $notice_type );

				wc_empty_cart();

			} else {

				if ( $payment_currency !== $order_currency ) {

					$order->update_status( 'on-hold', '' );

					update_post_meta( $order_id, '_transaction_id', $onepipe_ref );

					$notice = 'Thank you for shopping with us.<br />Your payment was successful, but the payment currency is different from the order currency.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
					$notice_type = 'notice';

					// Add Customer Order Note
                    $order->add_order_note( $notice, 1 );

	                // Add Admin Order Note
                	$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Order currency is different from the payment currency.<br /> Order Currency is <strong>'. $order_currency . ' ('. $currency_symbol . ')</strong> while the payment currency is <strong>'. $payment_currency . ' ('. $gateway_symbol . ')</strong><br /><strong>onepipe Transaction Reference:</strong> ' . $onepipe_ref );

					function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

					wc_add_notice( $notice, $notice_type );

				} else {

					$order->payment_complete( $onepipe_ref );

					$order->add_order_note( sprintf( 'Payment via onepipe successful (Transaction Reference: %s)', $onepipe_ref ) );

					wc_empty_cart();

				}

			}

			$this->save_card_details( $event, $order->get_user_id(), $order_id );

			exit;
	    }

	    exit;

	}


	/**
	 * Save Customer Card Details
	 */
	public function save_card_details( $onepipe_response, $user_id, $order_id ) {

		$this->save_subscription_payment_token( $order_id, $onepipe_response );

		$save_card = get_post_meta( $order_id, '_wc_onepipe_save_card', true );

		if ( $user_id && $this->saved_cards && $save_card && $onepipe_response->data->authorization->reusable && 'card' == $onepipe_response->data->authorization->channel ) {

			$order      = wc_get_order( $order_id );

			$gateway_id = $order->get_payment_method();

			$last4      = $onepipe_response->data->authorization->last4;
			$exp_year   = $onepipe_response->data->authorization->exp_year;
			$brand      = $onepipe_response->data->authorization->card_type;
			$exp_month  = $onepipe_response->data->authorization->exp_month;
			$auth_code  = $onepipe_response->data->authorization->authorization_code;

			$token = new WC_Payment_Token_CC();
			$token->set_token( $auth_code );
			$token->set_gateway_id( $gateway_id );
			$token->set_card_type( strtolower( $brand ) );
			$token->set_last4( $last4 );
			$token->set_expiry_month( $exp_month  );
			$token->set_expiry_year( $exp_year );
			$token->set_user_id( $user_id );
			$token->save();

			delete_post_meta( $order_id, '_wc_onepipe_save_card' );

		}

	}


	/**
	 * Save payment token to the order for automatic renewal for further subscription payment
	 */
	public function save_subscription_payment_token( $order_id, $onepipe_response ) {

		if ( ! function_exists ( 'wcs_order_contains_subscription' ) ) {

			return;

		}

		if ( $this->order_contains_subscription( $order_id ) && $onepipe_response->data->authorization->reusable && 'card' == $onepipe_response->data->authorization->channel ) {

			$auth_code 	= $onepipe_response->data->authorization->authorization_code;

			// Also store it on the subscriptions being purchased or paid for in the order
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

			} else {

				$subscriptions = array();

			}

			foreach ( $subscriptions as $subscription ) {

				$subscription_id = $subscription->get_id();

				update_post_meta( $subscription_id, '_onepipe_token', $auth_code );

			}

		}

	}


	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @since 5.4.0
	 * @param string $version Version to check against.
	 * @return bool
	 */
	public function is_wc_lt( $version ) {
		return version_compare( WC_VERSION, $version, '<' );
	}

}