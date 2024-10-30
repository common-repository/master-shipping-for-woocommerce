<?php
/**
 * WCMaster conditions
 *
 * The WCMaster consitions class handling applying actions to WCMaster Shipping Method.
 *
 * @package Master Shipping for WooCommerce\Classes
 * @version 1.0.0
 */

namespace WCMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCMaster Conditions class.
 */
class Conditions {

	/**
	 * Check if conditions are met.
	 *
	 * @param string     $if Any or All value.
	 * @param array      $statements Statements to be checked.
	 * @param array      $package Shipping package.
	 * @param bool|float $volumetric_divider Volumetric divider.
	 */
	public static function check_condition( $if, $statements, $package, $volumetric_divider ) {

		$if_container = array();
		foreach ( $statements as $statement ) {
			if ( '' === $statement['comparator'] ) {
				continue;
			}
			switch ( $statement['criteria'] ) {

				// Cart.
				case 'cart_quantity':
					$value          = WC()->cart->get_cart_contents_count();
					$if_container[] = self::check_number_statement( $statement, $package, $value );
					break;
				case 'cart_weight':
					if ( $volumetric_divider ) {
						$products = wp_list_pluck( $package['contents'], 'product_id' );

						$value = 0;
						foreach ( $products as $product_id ) {
							$product        = wc_get_product( $product_id );
							$product_width  = (float) $product->get_width();
							$product_height = (float) $product->get_height();
							$product_length = (float) $product->get_length();
							$product_weight = (float) $product->get_weight();

							if ( ! $product_width || ! $product_height || ! $product_length ) {
								$value += $product_weight;
								break;
							}
							$product_volume_weight = ( $product_width * $product_height * $product_length ) / $volumetric_divider;

							if ( $product_volume_weight > $product_weight ) {
								$value += $product_volume_weight;
							} else {
								$value += $product_weight;
							}
						}
					} else {
						$value = WC()->cart->get_cart_contents_weight();
					}
					$if_container[] = self::check_number_statement( $statement, $package, $value );
					break;
				case 'cart_tax':
					$value          = WC()->cart->get_taxes();
					$if_container[] = self::check_number_statement( $statement, $package, $value );
					break;
				case 'cart_product':
					$products       = wp_list_pluck( $package['contents'], 'product_id' );
					$if_container[] = self::check_contains_statement( $statement, $package, $products );
					break;
				case 'cart_subtotal':
					$value          = WC()->cart->get_subtotal();
					$if_container[] = self::check_number_statement( $statement, $package, $value );
					break;
				case 'cart_subtotal_taxes':
					$value          = WC()->cart->get_subtotal() + WC()->cart->get_subtotal_tax();
					$if_container[] = self::check_number_statement( $statement, $package, $value );
					break;
				case 'cart_total':
					$value          = WC()->cart->total() - WC()->cart->get_shipping_total();
					$if_container[] = self::check_number_statement( $statement, $package, $value );
					break;
				case 'cart_coupon':
					$coupons = array();

					foreach ( $package['applied_coupons'] as $code ) {
						$coupon    = new \WC_Coupon( $code );
						$coupons[] = $coupon->get_id();
					}
					$if_container[] = self::check_contains_statement( $statement, $package, $coupons );
					break;
				case 'cart_data':
					$cart_data_name  = $statement['extra'];
					$cart_data_value = $statement['value'];

					foreach ( $package['contents'] as $cart_key => $cart_item ) {
						if ( isset( $cart_item[ $cart_data_name ] ) ) {
							if ( is_numeric( $cart_data_value ) ) {
								$if_container[] = self::check_number_statement( $statement, $package, $cart_item[ $cart_data_name ] );
							} else {
								$if_container[] = self::check_string_statement( $statement, $package, $cart_item[ $cart_data_name ] );
							}
						}
					}
					break;

				// User.
				case 'user_role':
					$user = wp_get_current_user();
					if ( $user ) {
						$roles          = $user->roles;
						$if_container[] = self::check_contains_statement( $statement, $package, $roles );
					} else {
						$if_container[] = false;
					}
					break;
				case 'user_logged_in':
					$value          = '' === $statement['value'] || 0 === (int) $statement['value'] ? false : true;
					$if_container[] = is_user_logged_in() === $value;
					break;
				case 'user_city':
					$city           = $package['destination']['city'];
					$if_container[] = self::check_string_statement( $statement, $package, $city );
					break;
				case 'user_code':
					$code           = $package['destination']['postcode'];
					$if_container[] = self::check_string_statement( $statement, $package, $code );
					break;
				case 'user_country':
					$country        = $package['destination']['country'];
					$if_container[] = self::check_string_contains_statement( $statement, $package, $country );
					break;
				case 'user_state':
					$state          = $package['destination']['state'];
					$if_container[] = self::check_preg_contains_statement( $statement, $package, $state );
					break;

					// Product.
				case 'product_sku':
					$skus = array();
					foreach ( $package['contents'] as $item_id => $data ) {
						$skus[] = $data['data']->get_sku();
					}
					$if_container[] = self::check_string_contains_statement( $statement, $package, $skus );
					break;
				case 'product_width':
					$widths = array();
					foreach ( $package['contents'] as $item_id => $data ) {
						$widths[] = $data['data']->get_width();
					}
					$if_container[] = self::check_number_statement( $statement, $package, max( $widths ) );
					break;
				case 'product_height':
					$heights = array();
					foreach ( $package['contents'] as $item_id => $data ) {
							$heights[] = $data['data']->get_height();
					}
							$if_container[] = self::check_number_statement( $statement, $package, max( $heights ) );
					break;
				case 'product_length':
					$lengths = array();
					foreach ( $package['contents'] as $item_id => $data ) {
						$lengths[] = $data['data']->get_length();
					}
					$if_container[] = self::check_number_statement( $statement, $package, max( $lengths ) );
					break;
				case 'product_volume':
					$products = wp_list_pluck( $package['contents'], 'product_id' );

					$volumes = array();
					foreach ( $products as $product_id ) {
						$product        = wc_get_product( $product_id );
						$product_width  = (float) $product->get_width();
						$product_height = (float) $product->get_height();
						$product_length = (float) $product->get_length();

						$product_volume = ( $product_width * $product_height * $product_length );
						$volumes[]      = $product_volume;
					}
					$if_container[] = self::check_number_statement( $statement, $package, max( $volumes ) );
					break;
				case 'product_stock':
					$stocks = array();
					foreach ( $package['contents'] as $item_id => $data ) {
						$stocks[] = $data['data']->get_stock_quantity();
					}
					$if_container[] = self::check_number_statement( $statement, $package, min( $stocks ) );
					break;
				case 'product_category':
					$cats = array();

					foreach ( $package['contents'] as $item_id => $data ) {
						$cats[] = $data['data']->get_category_ids();
					}
					$if_container[] = self::check_contains_statement( $statement, $package, array_unique( array_merge( ...$cats ) ) );
					break;
				case 'product_meta_key':
					$products           = wp_list_pluck( $package['contents'], 'product_id' );
					$products_have_meta = get_posts(
						array(
							'post_type'   => 'product',
							'numberposts' => -1,
							'include'     => $products,
							'meta_query'  => array(
								array(
									'key'     => $statement['value'],
									'compare' => 'EXISTS',
								),
								'field' => 'ids',
							),
						)
					);
					$if_container[]     = count( $products_have_meta ) > 0;

					break;

			}
		}
		if ( 'all' === $if ) {

			return ! in_array( false, $if_container, true );
		} elseif ( 'any' === $if ) {

			return in_array( true, $if_container, true );
		}
	}

	/**
	 * Check statement with returning number type.
	 *
	 * @param array        $statement Statement.
	 * @param array        $package Shipping package.
	 * @param string|float $value Value from user.
	 */
	public static function check_number_statement( $statement, $package, $value ) {

		$comparator = self::escape_comparator( $statement['comparator'] );

		switch ( $comparator ) {
			case '=':
				return (float) $value === (float) $statement['value'];
			case '!=':
				return (float) $value !== (float) $statement['value'];
			case '>=':
				return (float) $value >= (float) $statement['value'];
			case '<=':
				return (float) $value <= (float) $statement['value'];
			case '>':
				return (float) $value > (float) $statement['value'];
			case '<':
				return (float) $value < (float) $statement['value'];
		}
	}

	/**
	 * Check statement, that contains in or not_in comparator.
	 *
	 * @param array $statement Statement.
	 * @param array $package Shipping package.
	 * @param array $value Value from user.
	 */
	public static function check_contains_statement( $statement, $package, $value ) {

		switch ( $statement['comparator'] ) {
			case 'in':
				$values = wp_list_pluck( $statement['value'], 'value' );
				return count( array_intersect( $value, $values ) ) > 0;
			case 'not_in':
				$values = wp_list_pluck( $statement['value'], 'value' );
				return count( array_intersect( $value, $values ) ) === 0;
		}
	}

	/**
	 * Check statement, that have only string values.
	 *
	 * @param array  $statement Statement.
	 * @param array  $package Shipping package.
	 * @param string $value Value from user.
	 */
	public static function check_string_statement( $statement, $package, $value ) {

		switch ( $statement['comparator'] ) {
			case '=':
				return (string) $value === (string) $statement['value'];
			case '!=':
				return (string) $value !== (string) $statement['value'];
		}
	}

	/**
	 * Check statement, that have string or Array<string> values.
	 *
	 * @param array        $statement Statement.
	 * @param array        $package Shipping package.
	 * @param string|array $value Value from user.
	 */
	public static function check_string_contains_statement( $statement, $package, $value ) {

		switch ( $statement['comparator'] ) {
			case 'in':
				return in_array( $value, wp_list_pluck( $statement['value'], 'value' ), true );
			case 'not_in':
				return ! in_array( $value, wp_list_pluck( $statement['value'], 'value' ), true );
			case '=':
				return in_array( $statement['value'], $value, true );
			case '!=':
				return ! in_array( $statement['value'], $value, true );
		}
	}

	/**
	 * Check statement, that have regex values.
	 *
	 * @param array  $statement Statement.
	 * @param array  $package Shipping package.
	 * @param string $value Value from user.
	 */
	public static function check_preg_contains_statement( $statement, $package, $value ) {

		$preg_values = array_map(
			function( $el ) {
				preg_match( '/[^_]*$/', $el, $matches );
				return $matches[0];
			},
			wp_list_pluck( $statement['value'], 'value' )
		);
		switch ( $statement['comparator'] ) {

			case 'in':
				return in_array( $value, $preg_values, true );
			case 'not_in':
				return ! in_array( $value, $preg_values, true );
		}
	}

	/**
	 * Escape greater and less characters.
	 *
	 * @param string $value Comparator.
	 */
	public static function escape_comparator( $value ) {

		$value = str_replace( '&gt;', '>', $value );
		$value = str_replace( '&lt;', '<', $value );
		return $value;
	}
}
