<?php
/**
 * Plugin Name: Master Shipping for WooCommerce
 * Description: Create complex Shipping Method for WooCommerce with conditions & actions
 * Version: 1.0.2
 * Author: wsjrcatarri
 * Donate link: https://www.paypal.com/donate/?hosted_button_id=K22Z8AKBAJMVS
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: wcmaster-shipping
 * WC requires at least: 5.0.0
 * WC tested up to:      8.6.0
 *
 * @package Master Shipping for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Check if WooCommerce is active.
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	exit;
}

if ( ! defined( 'WCMASTERSHIPPING_PATH' ) ) {
	define( 'WCMASTERSHIPPING_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WCMASTERSHIPPING_DIR' ) ) {
	define( 'WCMASTERSHIPPING_DIR', __FILE__ );
}
require_once WCMASTERSHIPPING_PATH . 'includes/traits/Initialisable.php';

/**
 * Class WCMaster_Shipping
 *
 * Main class to load shipping method and include all files.
 *
 * @version 1.0.0
 * @author wsjrcatarri
 */
class WCMaster_Shipping {

	use Initialisable;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'woocommerce_shipping_init', array( $this, 'wcmaster_shipping_method' ) );
		add_action( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
		$this->includes();
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_filter( 'plugin_row_meta', array( $this, 'plugin_meta_links' ), 10, 2 );
	}

	/**
	 * Load the textdomain based on WP language.
	 */
	public function load_textdomain() {

		load_plugin_textdomain( 'wcmaster-shipping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	}

	/**
	 * Include all files.
	 */
	public function includes() {
		require_once WCMASTERSHIPPING_PATH . 'includes/wcmaster-functions.php';
		require_once WCMASTERSHIPPING_PATH . 'includes/class-wcmaster-admin.php';
		require_once WCMASTERSHIPPING_PATH . 'includes/class-wcmaster-ajax.php';
		require_once WCMASTERSHIPPING_PATH . 'includes/class-wcmaster-conditions.php';
		require_once WCMASTERSHIPPING_PATH . 'includes/class-wcmaster-front.php';
	}

	/**
	 * Initialize shipping method class.
	 */
	public function wcmaster_shipping_method() {
		require_once WCMASTERSHIPPING_PATH . 'includes/class-wcmaster-shipping-method.php';
		new WCMaster_Shipping_Method();
	}

	/**
	 * Add WCMaster Shipping Method to WooCommerce.
	 *
	 * @param array $methods WooCommerce shipping methods.
	 */
	public function add_shipping_method( $methods ) {
		$methods['wcmaster'] = 'WCMaster_Shipping_Method';
		return $methods;
	}

	/**
	 * Add donate link.
	 */
	public function plugin_meta_links( $links, $file ) {
		if ( strpos( $file, 'wcmaster-shipping.php' ) !== false ) {

			$links[] = '<a target="_blank" href="https://www.paypal.com/donate/?hosted_button_id=K22Z8AKBAJMVS">' . __( 'Donate to author', 'wcmaster-shipping' ) . '</a>';

		}

		return $links;
	}

}

/**
 * Init plugin.
 */
function wcmastershipping_init_plugin() {
	WCMaster_Shipping::instance();
}
add_action( 'plugins_loaded', 'wcmastershipping_init_plugin' );

/**
 * HPOS compatible.
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
