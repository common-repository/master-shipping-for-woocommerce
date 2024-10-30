<?php
/**
 * Handles front actions.
 *
 * @package Master Shipping for WooCommerce\Classes
 * @version 1.0.0
 */

namespace WCMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCMAster Shipping Front class.
 */
class Front {

	use \Initialisable;

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action( 'woocommerce_after_shipping_rate', array( $this, 'add_multi_form' ), 10, 2 );

		add_filter( 'woocommerce_order_shipping_method', array( $this, 'maybe_rename_method' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_chackout_scripts' ) );

		add_action( 'woocommerce_before_checkout_form', array( $this, 'debug_checkout' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'checkout_update_refresh_shipping_methods' ), 10, 1 );

	}

	/**
	 * Maybe add multi shipping methods to WCMaster Shipping Method.
	 *
	 * @param WC_Shipping_Rate $method Rate object.
	 * @param int              $index method index.
	 */
	public function add_multi_form( $method, $index ) {

		if ( 'wcmaster' === $method->method_id ) {

			$option_name     = 'woocommerce_wcmaster_' . $method->instance_id . '_settings';
			$method_data     = get_option( $option_name );
			$wcmaster_method = new \WCMaster_Shipping_Method( $method->instance_id );
			$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );

			if ( isset( $method_data['isMultiple'] ) && $method_data['isMultiple'] ) {
				if ( ! empty( $method_data['items'] ) ) : ?>
					<?php
					$chosen_child = WC()->session->get( 'wcmaster_method_choosen' );

					$package        = WC()->cart->get_shipping_packages();
					$main_classes   = array( 'wcmaster-method-block' );
					$main_classes[] = in_array( 'wcmaster:' . $method->instance_id, $chosen_methods, true ) ? 'active' : 'disabled';
					?>
						<div class="<?php echo esc_attr( implode( ' ', $main_classes ) ); ?>">
							<?php
							echo wp_kses(
								wcmastershipping_get_selected_item( $method->instance_id, $chosen_child, $method_data['items'] ),
								array(
									'span' => array(
										'class' => array(),
									),
									'svg'  => array(
										'width'   => array(),
										'height'  => array(),
										'viewbox' => array(),
										'fill'    => array(),
										'xmlns'   => array(),
									),
									'path' => array(
										'd'               => array(),
										'fill'            => array(),
										'stroke'          => array(),
										'stroke-width'    => array(),
										'stroke-linecap'  => array(),
										'stroke-linejoin' => array(),
									),
								)
							);
							?>
							<ul class="wcmaster-method-items" data-method-id="<?php echo esc_attr( $method->instance_id ); ?>">
					
							<?php foreach ( $method_data['items'] as $item_id => $item ) : ?>
										<?php
										$active = false;
										$cost   = $wcmaster_method->calculate_item_costs( $package[0], $item_id );
										if ( $chosen_child && array_keys( $chosen_child )[0] === $method->instance_id && $chosen_child[ $method->instance_id ] === $item_id ) {

											$active = true;
										} elseif ( ( ! $chosen_child || ! in_array( $method->instance_id, array_keys( $chosen_child ), true ) ) && 0 === $item_id ) {

											$active = true;
										}
										$string_to_display = $item['title'] . ' <span class="wcmaster-item-price">(' . wc_price( $cost ) . ')</span>';
										$string_to_display = apply_filters( 'wcmaster_subitem_value_html', $string_to_display, $item );
										$child_classes     = array( 'wcmaster-method-item' );
										$child_classes[]   = $active ? 'active' : '';
										?>
									<li data-item-id="<?php echo esc_attr( $item_id ); ?>" class="<?php echo esc_attr( implode( ' ', $child_classes ) ); ?>" ><?php echo wp_kses_post( $string_to_display ); ?></li>
							<?php endforeach; ?>
							</ul>
						
						</div>
				
				<?php endif; ?>
				
				<?php
			}
			if ( $wcmaster_method->is_courier_enabled() ) :
				?>
				<?php
				$checked_courier   = WC()->session->get( 'wcmaster_courier_choosen' );
				$courier_title     = '<span class="wcmaster-icon"></span>' . $wcmaster_method->get_courier_title() . ', ' . wc_price( $wcmaster_method->get_courier_cost() );
				$courier_title     = apply_filters( 'wcmaster_shipping_courier_title', $courier_title, $method );
				$courier_classes   = array( 'wcmaster-form-group' );
				$courier_classes[] = in_array( 'wcmaster:' . $method->instance_id, $chosen_methods, true ) ? 'active' : '';

				$wcmaster_courier_id = 'wcmaster_courier_' . $method->instance_id;

				?>
				<div class="<?php echo esc_attr( implode( ' ', $courier_classes ) ); ?>" data-method-id="<?php echo esc_attr( $method->instance_id ); ?>">
					<input type="checkbox" class="wcmaster-courier-checkbox" id="<?php echo esc_attr( $wcmaster_courier_id ); ?>" <?php checked( isset( $checked_courier[ $method->instance_id ] ) && $checked_courier[ $method->instance_id ] && in_array( 'wcmaster:' . $method->instance_id, $chosen_methods, true ), 1 ); ?>>
					<label for="<?php echo esc_attr( $wcmaster_courier_id ); ?>"><?php echo wp_kses_post( $courier_title ); ?></label>
				</div>
				<?php
				endif;
		}

	}

	/**
	 * Rebuild name WCMaster Shipping Method string to include child method or|and shipping to address.
	 *
	 * @param array    $names Shipping method names.
	 * @param WC_Order $order Order class.
	 */
	public function maybe_rename_method( $names, $order ) {

		$names            = array();
		$shipping_methods = $order->get_shipping_methods();
		foreach ( $shipping_methods as $shipping_method ) {
			if ( $shipping_method->get_method_id() === 'wcmaster' ) {

				$wcmaster_child   = $shipping_method->get_meta( 'wcmaster_item_title' );
				$wcmaster_courier = $shipping_method->get_meta( 'wcmaster_courier_title' );
				$title            = $shipping_method->get_name();
				if ( $wcmaster_child ) {

					$title = wp_kses_post( $title . ', ' . $wcmaster_child );
				}
				if ( $wcmaster_courier ) {
					$title = wp_kses_post( $title . ', ' . $wcmaster_courier );
				}
				$names[] = $title;
			} else {
				$names[] = $shipping_method->get_name();
			}
		}
		return implode( ', ', $names );
	}

	/**
	 * Enqueue scripts|styles on checkout page.
	 */
	public function enqueue_chackout_scripts() {
		if ( is_checkout() || is_cart() ) {
			wp_enqueue_style( 'wcmaster-checkout', plugins_url( 'assets/css/checkout.css', WCMASTERSHIPPING_DIR ), array(), '1.0.0', 'all' );
			wp_enqueue_script( 'wcmaster-checkout', plugins_url( 'assets/js/front/checkout.js', WCMASTERSHIPPING_DIR ), array( 'jquery' ), '1.0.0', true );
			wp_localize_script(
				'wcmaster-checkout',
				'WCMASTER_FRONT',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wcmaster-checkout' ),
				)
			);
		}
	}

	/**
	 * Clear shipping rate cache.
	 *
	 * @param array $post_data $_POST data.
	 */
	public function checkout_update_refresh_shipping_methods( $post_data ) {
		$packages = WC()->cart->get_shipping_packages();
		foreach ( $packages as $package_key => $package ) {
			 WC()->session->set( 'shipping_for_package_' . $package_key, false );
		}
	}

	/**
	 * Debug checkout.
	 */
	public function debug_checkout() {
		// debug.
	}
}


Front::instance();
