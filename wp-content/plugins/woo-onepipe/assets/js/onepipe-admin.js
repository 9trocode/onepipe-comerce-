jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle onepipe admin functions.
	 */
	var wc_onepipe_admin = {
		/**
		 * Initialize.
		 */
		init: function() {

			// Toggle api key settings.
			$( document.body ).on( 'change', '#woocommerce_onepipe_testmode', function() {
				var test_secret_key = $( '#woocommerce_onepipe_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					test_public_key = $( '#woocommerce_onepipe_test_public_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_onepipe_live_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_public_key = $( '#woocommerce_onepipe_live_public_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					test_public_key.show();
					live_secret_key.hide();
					live_public_key.hide();
				} else {
					test_secret_key.hide();
					test_public_key.hide();
					live_secret_key.show();
					live_public_key.show();
				}
			} );

			$( '#woocommerce_onepipe_testmode' ).change();

			$( document.body ).on( 'change', '.woocommerce_onepipe_split_payment', function() {
				var subaccount_code = $( '.woocommerce_onepipe_subaccount_code' ).parents( 'tr' ).eq( 0 ),
					subaccount_charge = $( '.woocommerce_onepipe_split_payment_charge_account' ).parents( 'tr' ).eq( 0 ),
					transaction_charge = $( '.woocommerce_onepipe_split_payment_transaction_charge' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					subaccount_code.show();
					subaccount_charge.show();
					transaction_charge.show();
				} else {
					subaccount_code.hide();
					subaccount_charge.hide();
					transaction_charge.hide();
				}
			} );

			$( '#woocommerce_onepipe_split_payment' ).change();

			// Toggle Custom Metadata settings.
			$( '.wc-onepipe-metadata' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '.wc-onepipe-meta-order-id, .wc-onepipe-meta-name, .wc-onepipe-meta-email, .wc-onepipe-meta-phone, .wc-onepipe-meta-billing-address, .wc-onepipe-meta-shipping-address, .wc-onepipe-meta-products' ).closest( 'tr' ).show();
				} else {
					$( '.wc-onepipe-meta-order-id, .wc-onepipe-meta-name, .wc-onepipe-meta-email, .wc-onepipe-meta-phone, .wc-onepipe-meta-billing-address, .wc-onepipe-meta-shipping-address, .wc-onepipe-meta-products' ).closest( 'tr' ).hide();
				}
			} ).change();

			// Toggle Bank filters settings.
			$( '.wc-onepipe-payment-channels' ).on( 'change', function() {

				var channels = $( ".wc-onepipe-payment-channels" ).val();

				if ( $.inArray( 'card', channels ) != '-1' ) {
					$( '.wc-onepipe-cards-allowed' ).closest( 'tr' ).show();
					$( '.wc-onepipe-banks-allowed' ).closest( 'tr' ).show();
				}
				else {
					$( '.wc-onepipe-cards-allowed' ).closest( 'tr' ).hide();
					$( '.wc-onepipe-banks-allowed' ).closest( 'tr' ).hide();
				}

			} ).change();

			$( ".wc-onepipe-payment-icons" ).select2( {
				templateResult: formatonepipePaymentIcons,
				templateSelection: formatonepipePaymentIcons
			} );

		}
	};

	function formatonepipePaymentIcons( payment_method ) {
		if ( !payment_method.id ) {
			return payment_method.text;
		}
		var $payment_method = $(
			'<span><img src=" ' + wc_onepipe_admin_params.plugin_url + '/assets/images/' + payment_method.element.value.toLowerCase() + '.png" class="img-flag" style="height: 15px; weight:18px;" /> ' + payment_method.text + '</span>'
		);
		return $payment_method;
	};

	wc_onepipe_admin.init();

} );
