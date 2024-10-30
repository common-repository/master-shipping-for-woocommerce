( function ( $ ) {
	'use strict';

	const isCart = $( document.body ).find( '.woocommerce-cart-form' );
	const isCheckout = $( document.body ).find( 'form.checkout' );
	//Select method multi item.
	$( document.body ).on( 'click', 'li.wcmaster-method-item', function ( e ) {
		e.stopPropagation();
		const childId = $( this ).attr( 'data-item-id' );
		const selectedItem = $( this )
			.closest( '.wcmaster-method-block' )
			.find( '.wcmaster-selected-item' );
		const methodItems = $( this ).closest( '.wcmaster-method-items' );
		methodItems.removeClass( 'active' );
		selectedItem.addClass( 'loading' );
		$.ajax( {
			url: WCMASTER_FRONT.ajaxurl,
			type: 'POST',
			data: {
				action: 'wcmaster_select_method',
				security: WCMASTER_FRONT.nonce,
				id: childId,
				method_id: $( this )
					.parents( '.wcmaster-method-items' )
					.attr( 'data-method-id' ),
				is_cart: isCart.length,
			},
		} )
			.then( ( res ) => {
				selectedItem.removeClass( 'loading' );
				if ( res.success ) {
					if ( isCart.length ) {
						const $html = $.parseHTML( res.data.html );
						$( '.cart_totals' ).replaceWith( $html );
						$( document.body ).trigger( 'updated_cart_totals' );
					} else if ( isCheckout.length ) {
						$( document.body ).trigger( 'update_checkout' );
					}
				}
			} )
			.fail( ( err ) => {
				console.log( err );
			} );
	} );

	//Shipping to address.
	$( document.body ).on(
		'change',
		'.wcmaster-courier-checkbox',
		function ( e ) {
			if (
				$( this ).closest( '.wcmaster-form-group' ).is( '.disabled' )
			) {
				return;
			}
			const methodId = $( this )
				.closest( '.wcmaster-form-group' )
				.attr( 'data-method-id' );
			$.ajax( {
				url: WCMASTER_FRONT.ajaxurl,
				type: 'POST',
				data: {
					action: 'wcmaster_enable_courier',
					security: WCMASTER_FRONT.nonce,
					courier: e.target.checked,
					method_id: methodId,
					is_cart: isCart.length,
				},
			} )
				.then( ( res ) => {
					if ( res.success ) {
						if ( isCart.length ) {
							const $html = $.parseHTML( res.data.html );
							$( '.cart_totals' ).replaceWith( $html );
							$( document.body ).trigger( 'updated_cart_totals' );
						} else if ( isCheckout.length ) {
							$( document.body ).trigger( 'update_checkout' );
						}
					}
				} )
				.fail( ( err ) => {
					console.log( err );
				} );
		}
	);
	$( document.body ).on( 'updated_checkout', function () {
		const shippingBlocks = $( document.body ).find(
			'.wcmaster-method-block'
		);
		const shippingAdress = $( document.body ).find(
			'.wcmaster-form-group'
		);
		shippingBlocks.each( function () {
			if ( ! $( this ).is( '.active' ) ) {
				$( this ).addClass( 'disabled' );
			}
		} );
		shippingAdress.each( function () {
			if ( ! $( this ).is( '.active' ) ) {
				$( this ).addClass( 'disabled' );
			}
		} );
	} );
	$( document.body ).on( 'updated_shipping_method', function () {
		const shippingBlocks = $( document.body ).find(
			'.wcmaster-method-block'
		);
		const shippingAdress = $( document.body ).find(
			'.wcmaster-form-group'
		);
		shippingBlocks.each( function () {
			if ( ! $( this ).is( '.active' ) ) {
				$( this ).addClass( 'disabled' );
			}
		} );
		shippingAdress.each( function () {
			if ( ! $( this ).is( '.active' ) ) {
				$( this ).addClass( 'disabled' );
			}
		} );
	} );
	//style
	$( document.body ).on( 'click', '.wcmaster-method-block', function () {
		if ( $( this ).is( '.disabled' ) ) {
			return;
		}
		const menu = $( this ).find( '.wcmaster-method-items' );
		const icon = $( this ).find( 'svg' );

		menu.toggleClass( 'active' );
		icon.toggleClass( 'active' );
	} );
} )( jQuery );
