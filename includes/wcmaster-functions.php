<?php

/**
 * Output header with selected method in checkout page.
 *
 * @param string|int $method_id Method ID.
 * @param array      $chosen_child Choosen method multi item.
 * @param array      $data Method data.
 */
function wcmastershipping_get_selected_item( $method_id, $chosen_child, $data ) {

	$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
	$icon           = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
	<path d="M6 9L12 15L18 9" stroke="#8DA3C6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
</svg>';

	if ( ! $chosen_child ) {
		return '<span class="wcmaster-selected-item">
		<span>' . $data[0]['title'] . '</span>
		' . $icon . '
		</span>';
	}
	$child_id = array_values( $chosen_child )[0];

	if ( ( is_array( $chosen_methods ) && ! in_array( 'wcmaster:' . $method_id, $chosen_methods, true ) ) || array_keys( $chosen_child )[0] !== $method_id ) {
		return '<span class="wcmaster-selected-item">
		<span>' . $data[0]['title'] . '</span>
		' . $icon . '
		</span>';
	}
	if ( isset( $data[ $child_id ] ) ) {
		$child = $data[ $child_id ];
		return '<span class="wcmaster-selected-item">
		<span>' . $child['title'] . '</span>
			' . $icon . '
			</span>';
	}

	return '<span class="wcmaster-selected-item">
		<span>' . $data[0]['title'] . '</span>
		' . $icon . '
		</span>';
}
