<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tbz_WC_onepipe_Gateway extends WC_Payment_Gateway {


	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id		   			= 'onepipe';
		$this->method_title 	    = 'onepipe';
		$this->has_fields 	    	= true;

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
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

		$this->public_key      		= $this->testmode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key      		= $this->testmode ? $this->test_secret_key : $this->live_secret_key;

		$this->custom_metadata      = $this->get_option( 'custom_metadata' ) === 'yes' ? true : false;

		$this->meta_order_id      	= $this->get_option( 'meta_order_id' ) === 'yes' ? true : false;
		$this->meta_name      		= $this->get_option( 'meta_name' ) === 'yes' ? true : false;
		$this->meta_email      		= $this->get_option( 'meta_email' ) === 'yes' ? true : false;
		$this->meta_phone      		= $this->get_option( 'meta_phone' ) === 'yes' ? true : false;
		$this->meta_billing_address = $this->get_option( 'meta_billing_address' ) === 'yes' ? true : false;
		$this->meta_shipping_address= $this->get_option( 'meta_shipping_address' ) === 'yes' ? true : false;
		$this->meta_products      	= $this->get_option( 'meta_products' ) === 'yes' ? true : false;

		// Hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_tbz_wc_onepipe_gateway', array( $this, 'verify_onepipe_transaction' ) );

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

		if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_onepipe_supported_currencies', array( 'NGN', 'USD', 'GBP' ) ) ) ) {

			$this->msg = 'onepipe does not support your store currency. Kindly set it to either NGN (&#8358), USD (&#36;) or GBP (&#163;) <a href="' . admin_url( 'admin.php?page=wc-settings&tab=general' ) . '">here</a>';

			return false;

		}

		return true;

	}

	/**
	 * Display onepipe payment icon
	 */
	public function get_icon() {

		$icon  = '<img src="' . WC_HTTPS::force_https_url( plugins_url( '../assets/images/onepipe-wc.png' , __FILE__ ) ) . '" alt="cards" />';

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

    	<h3>onepipe</h3>

        <h4>Optional: To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="https://dashboard.onepipe.co/#/settings/developer" target="_blank" rel="noopener noreferrer">here</a> to the URL below<strong style="color: red"><pre><code><?php echo WC()->api_request_url( 'Tbz_WC_onepipe_Webhook' ); ?></code></pre></strong></h4>

        <?php

		if ( $this->is_valid_for_use() ){

            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';

        }
		else {	 ?>
			<div class="inline error"><p><strong>onepipe Payment Gateway Disabled</strong>: <?php echo $this->msg ?></p></div>

		<?php }

    }


	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = array(
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
			'custom_metadata' 	  => array(
				'title'       => 'Custom Metadata',
				'label'       => 'Enable Custom Metadata',
				'type'        => 'checkbox',
				'description' => 'If enabled, you will be able to send more information about the order to onepipe.',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_order_id'  => array(
				'title'       => 'Order ID',
				'label'       => 'Send Order ID',
				'type'        => 'checkbox',
				'description' => 'If checked, the Order ID will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_name'  => array(
				'title'       => 'Customer Name',
				'label'       => 'Send Customer Name',
				'type'        => 'checkbox',
				'description' => 'If checked, the customer full name will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_email'  => array(
				'title'       => 'Customer Email',
				'label'       => 'Send Customer Email',
				'type'        => 'checkbox',
				'description' => 'If checked, the customer email address will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_phone'  => array(
				'title'       => 'Customer Phone',
				'label'       => 'Send Customer Phone',
				'type'        => 'checkbox',
				'description' => 'If checked, the customer phone will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_billing_address'  => array(
				'title'       => 'Order Billing Address',
				'label'       => 'Send Order Billing Address',
				'type'        => 'checkbox',
				'description' => 'If checked, the order billing address will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_shipping_address'  => array(
				'title'       => 'Order Shipping Address',
				'label'       => 'Send Order Shipping Address',
				'type'        => 'checkbox',
				'description' => 'If checked, the order shipping address will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
			'meta_products'  => array(
				'title'       => 'Product(s) Purchased',
				'label'       => 'Send Product(s) Purchased',
				'type'        => 'checkbox',
				'description' => 'If checked, the product(s) purchased will be sent to onepipe',
				'default'     => 'no',
				'desc_tip'    => true
			),
		);

	}


	/**
	 * Outputs scripts used for onepipe payment
	 */
	public function payment_scripts() {

		if ( ! is_checkout_pay_page() ) {
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

			$order_key 		= urldecode( $_GET['key'] );
			$order_id  		= absint( get_query_var( 'order-pay' ) );

			$order    		= wc_get_order( $order_id );

			$email  		= method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;

			$amount 		= $order->get_total() * 100;

			$txnref		 	= $order_id . '_' .time();

			$the_order_id 	= method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
	        $the_order_key 	= method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;

			if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

				$onepipe_params['email'] 				= $email;
				$onepipe_params['amount']  			= $amount;
				$onepipe_params['txnref']  			= $txnref;
				$onepipe_params['pay_page']  			= $this->payment_page;
				$onepipe_params['currency']  			= get_woocommerce_currency();

			}

			if( $this->custom_metadata ) {

				if( $this->meta_order_id ) {

					$onepipe_params['meta_order_id'] = $order_id;

				}

				if( $this->meta_name ) {

					$first_name  	= method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
					$last_name  	= method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;

					$onepipe_params['meta_name'] = $first_name . ' ' . $last_name;

				}

				if( $this->meta_email ) {

					$onepipe_params['meta_email'] = $email;

				}

				if( $this->meta_phone ) {

					$billing_phone  	= method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;

					$onepipe_params['meta_phone'] = $billing_phone;

				}

				if( $this->meta_products ) {

					$line_items     = $order->get_items();

					$products 		= '';

					foreach ( $line_items as $item_id => $item ) {
						$name = $item['name'];
						$quantity = $item['qty'];
						$products .= $name .' (Qty: ' . $quantity .')';
						$products .= ' | ';
					}

					$products = rtrim( $products, ' | ' );

					$onepipe_params['meta_products'] = $products;

				}

				if( $this->meta_billing_address ) {

					$billing_address 	= $order->get_formatted_billing_address();
					$billing_address 	= esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );

					$onepipe_params['meta_billing_address'] = $billing_address;

				}

				if( $this->meta_shipping_address ) {

					$shipping_address 	= $order->get_formatted_shipping_address();
					$shipping_address 	= esc_html( preg_replace( '#<br\s*/?>#i', ', ', $shipping_address ) );

					if( empty( $shipping_address ) ) {

						$billing_address 	= $order->get_formatted_billing_address();
						$billing_address 	= esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );

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

		wp_enqueue_script( 'wc_onepipe_admin', plugins_url( 'assets/js/onepipe-admin' . $suffix . '.js', WC_onepipe_MAIN_FILE ), array(), WC_onepipe_VERSION, true );

	}


	/**
	 * Process the payment
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		);

	}


	/**
	 * Displays the payment page
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );

		if( 'embed' == $this->payment_page ) {

			echo '<p style="text-align: center; font-weight: bold;">Thank you for your order, please make payment below using onepipe.</p>';

			echo '<div id="onepipeWooCommerceEmbedContainer"></div>';

			echo '<div id="onepipe_form"><form id="order_review" method="post" action="'. WC()->api_request_url( 'Tbz_WC_onepipe_Gateway' ) .'"></form>
				<a href="' . esc_url( $order->get_cancel_order_url() ) . '" style="text-align:center; color: #EF3315; display: block; outline: none;">Cancel order &amp; restore cart</a></div>
				';

		} else {

			echo '<p>Thank you for your order, please click the button below to pay with onepipe.</p>';

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

					$order_details 		= explode( '_', $onepipe_response->data->reference );

					$order_id 			= (int) $order_details[0];

			        $order 				= wc_get_order($order_id);

			        if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
			        	wp_redirect( $this->get_return_url( $order ) );
						exit;
			        }

	        		$order_total		= $order->get_total();

					$order_currency 	= method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

					$currency_symbol	= get_woocommerce_currency_symbol( $order_currency );

	        		$amount_paid		= $onepipe_response->data->amount / 100;

	        		$onepipe_ref 		= $onepipe_response->data->reference;

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

						$order->reduce_order_stock();

						wc_add_notice( $notice, $notice_type );

						wc_empty_cart();

					} else {

						$order->payment_complete( $onepipe_ref );

						$order->add_order_note( sprintf( 'onepipe Transaction Ref: %s', $onepipe_ref ) );

						wc_empty_cart();
					}

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

			http_response_code(200);

			$order_details 		= explode( '_', $event->data->reference );

			$order_id 			= (int) $order_details[0];

	        $order 				= wc_get_order( $order_id );

	        $onepipe_txn_ref 	= get_post_meta( $order_id, '_onepipe_txn_ref', true );

	        if ( $event->data->reference != $onepipe_txn_ref ) {
	        	exit;
	        }

	        if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
				exit;
	        }

			$order_currency 	= method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

			$currency_symbol	= get_woocommerce_currency_symbol( $order_currency );

    		$order_total		= $order->get_total();

    		$amount_paid		= $event->data->amount / 100;

    		$onepipe_ref 		= $event->data->reference;

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

				$order->reduce_order_stock();

				wc_add_notice( $notice, $notice_type );

				wc_empty_cart();

			} else {

				$order->payment_complete( $onepipe_ref );

				$order->add_order_note( sprintf( 'onepipe Transaction Ref: %s', $onepipe_ref ) );

				wc_empty_cart();
			}

			exit;
	    }

	    exit;
	}

}