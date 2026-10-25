/* global jQuery, dwtmAdmin */
( function ( $ ) {
	'use strict';

	function run( $btn, action, dataKey, dataVal ) {
		var $msg = $btn.siblings( '.dwtm-msg' );
		var orig = $btn.text();
		$btn.prop( 'disabled', true );
		$msg.text( dwtmAdmin.syncing ).removeClass( 'dwtm-ok dwtm-bad' );

		var payload = { action: action, nonce: dwtmAdmin.nonce };
		payload[ dataKey ] = dataVal;

		$.post( dwtmAdmin.ajaxUrl, payload )
			.done( function ( res ) {
				if ( res && res.success ) {
					$msg.text( res.data.message ).addClass( 'dwtm-ok' );
				} else {
					$msg.text( ( res && res.data && res.data.message ) || 'Error' ).addClass( 'dwtm-bad' );
				}
			} )
			.fail( function () {
				$msg.text( 'Request failed' ).addClass( 'dwtm-bad' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( orig );
			} );
	}

	$( document ).on( 'click', '.dwtm-push-order', function ( e ) {
		e.preventDefault();
		run( $( this ), 'dwtm_push_order', 'order_id', $( this ).data( 'order' ) );
	} );

	$( document ).on( 'click', '.dwtm-pull-stock', function ( e ) {
		e.preventDefault();
		run( $( this ), 'dwtm_pull_stock', 'product_id', $( this ).data( 'product' ) );
	} );

	// Keep the hidden company-file name + URI in sync with the select.
	$( document ).on( 'change', '#dwtm_cf_select', function () {
		var $sel = $( this ).find( ':selected' );
		$( '#dwtm_cf_name' ).val( $sel.data( 'name' ) || '' );
		$( '#dwtm_cf_uri' ).val( $sel.data( 'uri' ) || '' );
	} );
}( jQuery ) );
