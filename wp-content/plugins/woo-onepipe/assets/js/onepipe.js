jQuery( function( $ ) {

	var onepipe_submit = false;

	if ( 'embed' === wc_onepipe_params.pay_page ) {

		wconepipeEmbedFormHandler();

	} else {

		jQuery( '#onepipe-payment-button' ).click( function() {
			return wconepipeFormHandler();
		} );

		jQuery( '#onepipe_form form#order_review' ).submit( function() {
			return wconepipeFormHandler();
		} );

	}

	function wconepipeCustomFields() {

		var custom_fields = [];

		if ( wc_onepipe_params.meta_order_id ) {

			custom_fields.push( {
				display_name: "Order ID",
				variable_name: "order_id",
				value: wc_onepipe_params.meta_order_id
			} );

		}

		if ( wc_onepipe_params.meta_name ) {

			custom_fields.push( {
				display_name: "Customer Name",
				variable_name: "customer_name",
				value: wc_onepipe_params.meta_name
			} );
		}

		if ( wc_onepipe_params.meta_email ) {

			custom_fields.push( {
				display_name: "Customer Email",
				variable_name: "customer_email",
				value: wc_onepipe_params.meta_email
			} );
		}

		if ( wc_onepipe_params.meta_phone ) {

			custom_fields.push( {
				display_name: "Customer Phone",
				variable_name: "customer_phone",
				value: wc_onepipe_params.meta_phone
			} );
		}

		if ( wc_onepipe_params.meta_billing_address ) {

			custom_fields.push( {
				display_name: "Billing Address",
				variable_name: "billing_address",
				value: wc_onepipe_params.meta_billing_address
			} );
		}

		if ( wc_onepipe_params.meta_shipping_address ) {

			custom_fields.push( {
				display_name: "Shipping Address",
				variable_name: "shipping_address",
				value: wc_onepipe_params.meta_shipping_address
			} );
		}

		if ( wc_onepipe_params.meta_products ) {

			custom_fields.push( {
				display_name: "Products",
				variable_name: "products",
				value: wc_onepipe_params.meta_products
			} );
		}

		return custom_fields;
	}

	function generateRandom(){
		return (Math.floor((Math.random() * 1000000000000) + 1)).toString();
	}

	function wconepipeCustomFilters() {

		var custom_filters = new Object();

		if ( wc_onepipe_params.banks_allowed ) {

			custom_filters[ 'banks' ] = wc_onepipe_params.banks_allowed;

		}

		if ( wc_onepipe_params.cards_allowed ) {

			custom_filters[ 'card_brands' ] = wc_onepipe_params.cards_allowed;
		}

		return custom_filters;
	}

	function wconepipeFormHandler() {

		if ( onepipe_submit ) {
			onepipe_submit = false;
			return true;
		}

		var $form = $( 'form#payment-form, form#order_review' ),
			onepipe_txnref = $form.find( 'input.onepipe_txnref' ),
			bank = "false",
			card = "false",
			subaccount_code = '',
			charges_account = '',
			transaction_charges = '';

		onepipe_txnref.val( '' );

		if ( wc_onepipe_params.bank_channel ) {
			bank = "true";
		}

		if ( wc_onepipe_params.card_channel ) {
			card = "true";
		}

		if ( wc_onepipe_params.subaccount_code ) {
			subaccount_code = wc_onepipe_params.subaccount_code;
		}

		if ( wc_onepipe_params.charges_account ) {
			charges_account = wc_onepipe_params.charges_account;
		}

		if ( wc_onepipe_params.transaction_charges ) {
			transaction_charges = Number( wc_onepipe_params.transaction_charges );
		}

		var amount = Number( wc_onepipe_params.amount );

		var onepipe_callback = function( response ) {
			$form.append( '<input type="hidden" class="onepipe_txnref" name="onepipe_txnref" value="' + response.trxref + '"/>' );
			onepipe_submit = true;

			$form.submit();

			$( 'body' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				},
				css: {
					cursor: "wait"
				}
			} );
		};


		var handler = OnePipePopup.setup({
			requestData : {
				request_ref: generateRandom(),
				request_type: 'charge',
				api_key: wc_onepipe_params.key,
				auth_provider: "PAYSTACK",
				transaction: {
					amount: amount.toString(),
					currency: wc_onepipe_params.currency,
					transaction_ref: generateRandom(),
					transaction_desc: "Payment For" + '' + wc_onepipe_params.meta_products,
					customer: {
						customer_ref: '2348166480703',
						firstname: wc_onepipe_params.meta_name,
						surname:wc_onepipe_params.meta_name,
						email: wc_onepipe_params.meta_email,
						mobile_no: "2348166480703"
					}
				},
				options: {
					validation_url :"http://onepipe.io",
					notification_url:"http://onepipe.io",
					redirect_url: "http://onepipe.io",
					follow_up: "true",
					follow_up_reminders: [
						{
							minutes_overdue: 10,
							message: "Complete your payment for item on OnePipe Demo Store.",
							message_channel:"pwa",
							action: "http://onepipe.io"
						},
						{
							minutes_overdue: 15,
							message: "Complete your payment for item on OnePipe Demo Store.",
							message_channel:"pwa",
							action: "http://onepipe.io"
						}],
					meta: [//COMPLETELY OPTIONAL
						{"location": "Lagos"},
						{"age": "below 18"},
						{"product-category": "data bundle"}
					]

				},
			},
			callback: onepipe_callback,
			onClose: function(){
				$( this.el ).unblock();
			}
		});

		handler.execute();
		return false;

}



function wconepipeEmbedFormHandler() {

	if ( onepipe_submit ) {
		onepipe_submit = false;
		return true;
	}

	var $form = $( 'form#payment-form, form#order_review' ),
		onepipe_txnref = $form.find( 'input.onepipe_txnref' ),
		bank = "false",
		card = "false",
		subaccount_code = '',
		charges_account = '',
		transaction_charges = '';

	onepipe_txnref.val( '' );

	if ( wc_onepipe_params.bank_channel ) {
		bank = "true";
	}

	if ( wc_onepipe_params.card_channel ) {
		card = "true";
	}

	if ( wc_onepipe_params.subaccount_code ) {
		subaccount_code = wc_onepipe_params.subaccount_code;
	}

	if ( wc_onepipe_params.charges_account ) {
		charges_account = wc_onepipe_params.charges_account;
	}

	if ( wc_onepipe_params.transaction_charges ) {
		transaction_charges = Number( wc_onepipe_params.transaction_charges );
	}

	var amount = Number( wc_onepipe_params.amount );

	var onepipe_callback = function( response ) {

		$form.append( '<input type="hidden" class="onepipe_txnref" name="onepipe_txnref" value="' + response.trxref + '"/>' );

		$( '#onepipe_form a' ).hide();

		onepipe_submit = true;

		$form.submit();

		$( 'body' ).block( {
			message: null,
			overlayCSS: {
				background: "#fff",
				opacity: 0.8
			},
			css: {
				cursor: "wait"
			}
		} );

	};

	var handler = OnePipePopup.setup({
		requestData : {
			request_ref: generateRandom(),
			request_type: 'charge',
			api_key: wc_onepipe_params.key,
			auth_provider: "PAYSTACK",
			transaction: {
				amount: amount.toString(),
				currency: wc_onepipe_params.currency,
				transaction_ref: generateRandom(),
				transaction_desc: "Payment For" + '' + wc_onepipe_params.meta_products,
				customer: {
					customer_ref: '2348166480703',
					firstname: wc_onepipe_params.meta_name,
					surname:wc_onepipe_params.meta_name,
					email: wc_onepipe_params.meta_email,
					mobile_no: "2348166480703"
				}
			},
			options: {
				validation_url :"http://onepipe.io",
				notification_url:"http://onepipe.io",
				redirect_url: "http://onepipe.io",
				follow_up: "true",
				follow_up_reminders: [
					{
						minutes_overdue: 10,
						message: "Complete your payment for item on OnePipe Demo Store.",
						message_channel:"pwa",
						action: "http://onepipe.io"
					},
					{
						minutes_overdue: 15,
						message: "Complete your payment for item on OnePipe Demo Store.",
						message_channel:"pwa",
						action: "http://onepipe.io"
					}],
				meta: [//COMPLETELY OPTIONAL
					{"location": "Lagos"},
					{"age": "below 18"},
					{"product-category": "data bundle"}
				]

			},
		},
		callback: onepipe_callback,
		onClose: function(){
			$( this.el ).unblock();
		}
	});

	handler.execute();
	return false;

}

});