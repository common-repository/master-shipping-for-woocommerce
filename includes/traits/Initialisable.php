<?php
/**
 * Handles initialisable.
 *
 * @package Master Shipping for WooCommerce\Traits
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialisable trait.
 */
trait Initialisable {

	/**
	 * Instance
	 */
	protected static $instance = null;

	/**
	 * Get class instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
