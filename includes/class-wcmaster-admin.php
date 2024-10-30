<?php
/**
 * Handles enqueue styles in admin
 *
 * @package Master Shipping for WooCommerce\Classes
 */

namespace WCMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WCMaster Admin class
 */
class Admin {

	use \Initialisable;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue styles.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'wcmaster-admin', plugins_url( 'assets/js/dist/wcmaster-admin.css', WCMASTERSHIPPING_DIR ), array(), '1.0.0', 'all' );
	}
}
Admin::instance();
