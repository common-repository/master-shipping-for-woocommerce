<?php
/**
 * Handles ajax events
 *
 * @package Master Shipping for WooCommerce\Classes
 * @version 1.0.0
 */

namespace WCMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCMaster Ajax class
 */
class Ajax {

	use \Initialisable;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// admin.
		add_action( 'wp_ajax_wcmaster_get_product', array( $this, 'wcmaster_get_product' ) );
		add_action( 'wp_ajax_wcmaster_get_products_for_cart', array( $this, 'wcmaster_get_products_for_cart' ) );
		add_action( 'wp_ajax_wcmaster_shipping_save', array( $this, 'wcmaster_shipping_save' ) );

		// front.
		add_action( 'wp_ajax_wcmaster_select_method', array( $this, 'wcmaster_select_method' ) );
		add_action( 'wp_ajax_nopriv_wcmaster_select_method', array( $this, 'wcmaster_select_method' ) );

		add_action( 'wp_ajax_wcmaster_enable_courier', array( $this, 'wcmaster_enable_courier' ) );
		add_action( 'wp_ajax_nopriv_wcmaster_enable_courier', array( $this, 'wcmaster_enable_courier' ) );

	}

	/**
	 * Get products list via ajax.
	 */
	public function wcmaster_get_product() {

		if ( ! isset( $_POST['nonce'] ) && ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'wcmaster' ) ) {
			wp_send_json_error();
		}

		if ( ! isset( $_POST['product'] ) ) {
			wp_send_json_error();
		}
		global $wpdb;
		$product = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_POST['product'] ) ) ) . '%';
		$result  = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type IN ('product', 'product_variation') AND post_status = 'publish' AND post_title LIKE %s ", $product ), ARRAY_A );
		wp_send_json_success(
			array(
				'products' => $result,
			)
		);
	}

	/**
	 * Get products list with extra data via ajax.
	 */
	public function wcmaster_get_products_for_cart() {

		if ( ! isset( $_POST['nonce'] ) && ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'wcmaster' ) ) {
			wp_send_json_error( array() );
		}
		if ( ! isset( $_POST['product'] ) ) {
			wp_send_json_error();
		}
		global $wpdb;
		$product         = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_POST['product'] ) ) ) . '%';
		$result          = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type IN ('product', 'product_variation') AND post_status = 'publish' AND post_title LIKE %s ", $product ) );
		$products        = array();
		$product_objects = wc_get_products(
			array(
				'limit'   => -1,
				'include' => $result,
				'type'    => array( 'simple', 'variable', 'variation' ),
			)
		);
		foreach ( $product_objects  as $prod ) {
			$products[] = array(
				'ID'            => $prod->get_id(),
				'name'          => $prod->get_name(),
				'price'         => $prod->get_price(),
				'price_html'    => $prod->get_price_html(),
				'price_inc_tax' => $prod->get_price_including_tax(),
				'height'        => $prod->get_height(),
				'width'         => $prod->get_width(),
				'length'        => $prod->get_length(),
				'weight'        => $prod->get_weight(),
				'categories'    => $prod->get_category_ids(),
				'image'         => wp_get_attachment_image_url( $prod->get_image_id(), 'full' ),
				'quantity'      => 1,
				'label'         => $prod->get_name(),
				'value'         => $prod->get_id(),
				'tax_status'    => $prod->get_tax_status(),
				'tax_class'     => $prod->get_tax_class(),
				'rates'         => \WC_Tax::get_rates( $prod->get_tax_class() ),

			);
		}
		wp_send_json_success(
			array(
				'products' => $products,
				'result'   => $result,
			)
		);
	}

	/**
	 * Save shipping method data via ajax.
	 */
	public function wcmaster_shipping_save() {

		if ( ! isset( $_POST['nonce'] ) && ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'wcmaster' ) ) {
			wp_send_json_error(
				array(
					'notices' => array(
						'type'    => 'error',
						'message' => __( 'Not allowed', 'wcmaster-shipping' ),
					),
				)
			);
		}

		$instance_id = absint( wp_unslash( $_POST['instance_id'] ) );

		if ( 0 === $instance_id ) {
			wp_send_json_error(
				array(
					'notices' => array(
						'type'    => 'error',
						'message' => __( 'Shipping Id not found', 'wcmaster-shipping' ),
					),
				)
			);
		}

		$shipping_method = \WC_Shipping_Zones::get_shipping_method( $instance_id );
		$option_key      = $shipping_method->get_instance_option_key();

		// save to db.
		update_option( $option_key, wc_clean( json_decode( wp_unslash( $_POST['data'] ), true ) ) );

		wp_send_json_success(
			array(
				'error'   => json_last_error(),
				'notices' => array(
					'type'    => 'success',
					'message' => __( 'Saved!', 'wcmaster-shipping' ),
				),
			)
		);
	}

	/**
	 * Save selected shipping method from checkout page via ajax.
	 */
	public function wcmaster_select_method() {

		if ( ! isset( $_POST['security'] ) && ! wp_verify_nonce( wp_unslash( $_POST['security'] ), 'wcmaster-checkout' ) ) {
			wp_send_json_error(
				array(
					'notices' => array(
						'type'    => 'error',
						'message' => __( 'Not allowed', 'wcmaster-shipping' ),
					),
				)
			);
		}
		$method_id = absint( wp_unslash( $_POST['method_id'] ) );
		$child_id  = absint( wp_unslash( $_POST['id'] ) );

		if ( 0 === $method_id || 0 === $child_id ) {
			wp_send_json_error(
				array(
					'notices' => array(
						'type'    => 'error',
						'message' => __( 'Shipping Id not found', 'wcmaster-shipping' ),
					),
				)
			);
		}
		$is_cart = wc_string_to_bool( sanitize_text_field( wp_unslash( $_POST['is_cart'] ) ) );

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $chosen_methods ) && ! in_array( 'wcmaster:' . $method_id, $chosen_methods, true ) ) {
			wp_send_json_error();
		}

		// save to session.
		WC()->session->set( 'wcmaster_method_choosen', array( $method_id => $child_id ) );

		if ( $is_cart ) {
			wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

			$packages = WC()->cart->get_shipping_packages();
			foreach ( $packages as $package_key => $package ) {
				WC()->session->set( 'shipping_for_package_' . $package_key, false );
			}
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		ob_start();
		woocommerce_cart_totals();
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}


	/**
	 * Save selected shipping method meta from checkout page via ajax.
	 */
	public function wcmaster_enable_courier() {

		if ( ! isset( $_POST['security'] ) && ! wp_verify_nonce( wp_unslash( $_POST['security'] ), 'wcmaster-checkout' ) ) {
			wp_send_json_error(
				array(
					'notices' => array(
						'type'    => 'error',
						'message' => __( 'Not allowed', 'wcmaster-shipping' ),
					),
				)
			);
		}

		$method_id = absint( wp_unslash( $_POST['method_id'] ) );

		if ( 0 === $method_id ) {
			wp_send_json_error(
				array(
					'notices' => array(
						'type'    => 'error',
						'message' => __( 'Shipping Id not found', 'wcmaster-shipping' ),
					),
				)
			);
		}

		$courier = wc_string_to_bool( sanitize_text_field( wp_unslash( $_POST['courier'] ) ) );
		$is_cart = wc_string_to_bool( sanitize_text_field( wp_unslash( $_POST['is_cart'] ) ) );

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $chosen_methods ) && ! in_array( 'wcmaster:' . $method_id, $chosen_methods, true ) ) {
			wp_send_json_error();
		}

		// save to session.
		WC()->session->set( 'wcmaster_courier_choosen', array( $method_id => $courier ) );

		if ( $is_cart ) {
			wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

			$packages = WC()->cart->get_shipping_packages();
			foreach ( $packages as $package_key => $package ) {
				WC()->session->set( 'shipping_for_package_' . $package_key, false );
			}
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		ob_start();
		woocommerce_cart_totals();
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}


}
Ajax::instance();
