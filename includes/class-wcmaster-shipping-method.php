<?php
/**
 * WCMaster Shipping Method Extender Class.
 *
 * @package Master Shipping for WooCommerce\Classes
 * @version 1.0.0
 */

use WCMaster\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCMaster Shipping Method class.
 */
class WCMaster_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Data from wp_options table.
	 */
	protected $data;

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Id of shipping method.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'wcmaster';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Master Shipping', 'wcmaster-shipping' );
		$this->method_description = __( 'Build complex deliveries, charge varying rates based on user defined conditions', 'wcmaster-shipping' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);

		$this->init();

		$this->enabled     = 'yes';
		$this->tax_status  = isset( $this->data['tax'] ) && $this->data['tax'] ? 'taxable' : 'none';
		$this->hide_method = isset( $this->data['ifFree'] ) && $this->data['ifFree'] ? true : false;
		$this->title       = isset( $this->data['title'] ) ? $this->data['title'] : 'New';
		$this->fee         = 0;
		$this->cost_item   = '';
		$this->cost_weight = '';
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_footer', array( $this, 'enqueue' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'hide_method' ) );
	}

	/**
	 * Equeue styles|scrips in admin.
	 */
	public function enqueue() {

		global $current_screen;

		if ( 'woocommerce_page_wc-settings' !== $current_screen->id ) {
			return;
		}
		if ( ! isset( $_GET['tab'] ) || 'shipping' !== $_GET['tab'] || ! isset( $_GET['instance_id'] ) ) {
			return;
		}
		if ( absint( $_GET['instance_id'] ) !== $this->instance_id ) {
			return;
		}

		// roles.
		$roles = wp_roles();
		// coupons.
		global $wpdb;
		$coupons = $wpdb->get_results( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'shop_coupon' AND post_status = 'publish' ORDER BY post_name ASC ", ARRAY_A );
		// countries.
		$country_states = array();
		foreach ( WC()->countries->get_states() as $country => $states ) {

			if ( empty( $states ) ) {
				continue;
			}
			if ( ! array_key_exists( $country, WC()->countries->get_shipping_countries() ) ) {
				continue;
			}

			foreach ( $states as $state_key => $state ) :
				$country_states[ WC()->countries->countries[ $country ] ][ $country . '_' . $state_key ] = $state;
			endforeach;

			$values['options'] = $country_states;

		}
		// categories.
		$categories   = array();
		$product_cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		foreach ( $product_cats as $key => $cat ) {
			$categories[ $key ]['label'] = $cat->name;
			$categories[ $key ]['ID']    = $cat->term_id;
		}
		// meta keys.
		$limit        = apply_filters( 'wcmaster_postmeta_form_limit', 90 );
		$sql          = "SELECT DISTINCT meta_key
			FROM $wpdb->postmeta
			WHERE meta_key NOT BETWEEN '_' AND '_z'
			HAVING meta_key NOT LIKE %s
			ORDER BY meta_key
			LIMIT %d";
		$keys         = $wpdb->get_col( $wpdb->prepare( $sql, $wpdb->esc_like( '_' ) . '%', $limit ) );
		$allowed_keys = array();
		if ( $keys ) {
			natcasesort( $keys );

			foreach ( $keys as $key ) {
				if ( is_protected_meta( $key, 'post' ) ) {
					continue;
				}

				$allowed_keys[] = $key;
			}
		}
		$script_asset_path = WCMASTERSHIPPING_PATH . 'assets/js/dist/app.bundle.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => filemtime( $script_asset_path ),
			);
		wp_enqueue_script( 'wcmaster-admin', plugins_url( 'assets/js/dist/app.bundle.js', WCMASTERSHIPPING_DIR ), $script_asset['dependencies'], $script_asset['version'], true );
		wp_localize_script(
			'wcmaster-admin',
			'WCMASTER_ADMIN',
			array(
				'id'         => $this->id,
				'key'        => $this->get_instance_option_key(),
				'admin_ajax' => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wcmaster' ),
				'currency'   => get_woocommerce_currency_symbol(),
				'options'    => $this->data,
				'user_roles' => $roles->get_names(),
				'countries'  => WC()->countries->get_allowed_countries() + WC()->countries->get_shipping_countries(),
				'states'     => $country_states,
				'coupons'    => $coupons,
				'categories' => $categories,
				'meta_keys'  => $allowed_keys,
			)
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wcmaster-admin', 'wcmaster-shipping', WCMASTERSHIPPING_PATH . 'languages' );
		}

	}

	/**
	 * Init.
	 */
	public function init() {

		$this->data = get_option( $this->get_instance_option_key(), null );

		$this->init_form_fields();

	}

	/**
	 * Init admin form fields.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(

			'title' => array(
				'title'   => __( 'Title', 'wcmaster-shipping' ),
				'type'    => 'text',
				'default' => __( 'Master Shipping', 'wcmaster-shipping' ),
			),

		);
	}

	/**
	 * Render react template.
	 */
	public function admin_options() {

		?>
		<div id="wcmaster-shipping"></div>
		<?php
	}

	/**
	 * Hide shipping rates when free shipping is available.
	 *
	 * @param array $methods Array of rates found for the package.
	 * @return array of modified shipping methods.
	 */
	public function hide_method( $methods ) {

		if ( $this->hide_method ) {
			$free_available = false;
			foreach ( $methods as $key => $method ) {
				if ( 'free_shipping' === $method->get_method_id() ) {
					$free_available = true;
					break;
				}
			}
			if ( $free_available ) {
				foreach ( $methods as $key => $method ) {
					if ( 'wcmaster:' . $this->instance_id === $key ) {
						unset( $methods[ $key ] );

					}
				}
			}
		}

		return $methods;
	}

	/**
	 * Check whether method uses volumetric weight.
	 */
	public function use_volumetric() {
		return $this->data['useVolumetric'] ?? false;
	}

	/**
	 * Check whether method contains multiple items.
	 */
	public function is_multiple_enabled() {
		return $this->data['isMultiple'] ?? false;
	}

	/**
	 * Check whether method uses shipping to address option.
	 */
	public function is_courier_enabled() {
		return $this->data['courier'] ?? false;
	}

	/**
	 * Shipping to address title.
	 */
	public function get_courier_title() {
		return $this->data['courierTitle'] ?? '';
	}

	/**
	 * Shipping to address cost.
	 */
	public function get_courier_cost() {
		return $this->data['courierCost'] ?? 0;

	}

	/**
	 * Calculate fee for method.
	 *
	 * @param string $fee Fee.
	 * @param float  $total Total.
	 */
	public function get_fee( $fee, $total ) {
		if ( strstr( $fee, '%' ) ) {
			$fee = ( $total / 100 ) * (float) str_replace( '%', '', $fee );
		} else {
			$fee = (float) preg_replace( '[^0-9\.,]', '', $fee );
		}
		if ( ! empty( $this->minimum_fee ) && $this->minimum_fee > $fee ) {
			$fee = $this->minimum_fee;
		}
		return $fee;
	}

	/**
	 * Calculate costs for method.
	 *
	 * @param array $package Shipping package.
	 */
	public function calculate_costs( $package ) {

		if ( ! isset( $this->data['item'] ) && ! isset( $this->data['items'] ) ) {
			return 0;
		}

		$shipping_item = $this->data['item'];
		$items         = isset( $this->data['items'] ) ? $this->data['items'] : array();

		if ( $this->is_multiple_enabled() ) {
			if ( count( $items ) < 1 ) {
				return 0;
			}
			$child_arr = WC()->session->get( 'wcmaster_method_choosen' );
			if ( $child_arr ) {

				$method_id = array_keys( $child_arr )[0];
				if ( $method_id !== $this->instance_id ) {
					$item = $items[0];

				} else {
					$child_id = $child_arr[ $this->instance_id ];
					if ( isset( $items[ $child_id ] ) ) {

						$item = $items[ $child_id ];
					} else {
						$item = $items[0];
					}
				}
			} else {
				$item = $items[0];
			}
		} else {
			$item = $shipping_item;
		}
			$cost            = (float) str_replace( ',', '.', $item['cost'] );
			$checked_courier = WC()->session->get( 'wcmaster_courier_choosen' );
			$chosen_methods  = (array) WC()->session->get( 'chosen_shipping_methods' );

		if ( ! empty( $item['extraCosts'] ) ) {
			foreach ( $item['extraCosts'] as $extra_cost ) {
				$this->{$extra_cost['id']} = (string) str_replace( ',', '.', $extra_cost['cost'] );
			}
		}
			$cost += (float) $this->get_fee( str_replace( ',', '.', $this->fee ), $package['contents_cost'] );
		if ( '' !== $this->cost_item || '' !== $this->cost_weight ) {
			foreach ( $package['contents'] as $item_id => $data ) {
				$product = $data['data'];
				if ( strstr( $this->cost_item, '%' ) ) {
					$cost += ( $data['line_total'] / 100 ) * (float) preg_replace( '[^0-9\.,]', '', $this->cost_item );
				} else {
					$cost += $data['quantity'] * (float) preg_replace( '[^0-9\.,]', '', $this->cost_item );
				}
				$value              = 0;
				$volumetric_divider = $this->volumetric_divider();
				$product_width      = (float) $product->get_width();
				$product_height     = (float) $product->get_height();
				$product_length     = (float) $product->get_length();
				$product_weight     = (float) $product->get_weight();

				if ( ! $product_width || ! $product_height || ! $product_length ) {
					$value += $product_weight;

				} elseif ( $volumetric_divider ) {
					$product_volume_weight = ( $product_width * $product_height * $product_length ) / $volumetric_divider;

					if ( $product_volume_weight > $product_weight ) {
						$value += $product_volume_weight;
					} else {
						$value += $product_weight;
					}
				} else {
					$value = $product_weight;
				}

				if ( strstr( $this->cost_weight, '%' ) ) {
					$cost += ( $data['line_total'] / 100 ) * (float) $value * (float) preg_replace( '[^0-9\.,]', '', $this->cost_weight );
				} else {

					$cost += $data['quantity'] * (float) $value * (float) preg_replace( '[^0-9\.,]', '', $this->cost_weight );
				}
			}
		}
		if ( $this->is_courier_enabled() && in_array( 'wcmaster:' . $this->instance_id, $chosen_methods, true ) && isset( $checked_courier[ $this->instance_id ] ) && $checked_courier[ $this->instance_id ] ) {
			$courier_cost = (float) $this->get_courier_cost();
			$cost        += $courier_cost;
		}
		return $cost;
	}

	/**
	 * Get volumetric weight divider or nothing.
	 */
	public function volumetric_divider() {
		$is_volumetric = $this->use_volumetric();
		if ( $is_volumetric ) {
			return (float) $this->data['volumetricDivider'];
		}
		return false;
	}

	/**
	 * Check conditions.
	 *
	 * @param array $rate Rate data.
	 * @param array $package Shipping package.
	 */
	public function match_conditions( &$rate, $package ) {

		if ( $this->is_multiple_enabled() ) {
			$items = $this->data['items'];
			if ( count( $items ) < 1 ) {
				return false;
			}
			$child_arr = WC()->session->get( 'wcmaster_method_choosen' );
			if ( $child_arr ) {

				$method_id = array_keys( $child_arr )[0];
				if ( $method_id !== $this->instance_id ) {
					$item = $items[0];

				} else {
					$child_id = $child_arr[ $this->instance_id ];
					if ( isset( $items[ $child_id ] ) ) {

						$item = $items[ $child_id ];
					} else {
						$item = $items[0];
					}
				}
			} else {
				$item = $items[0];
			}
			$rate['meta_data'] = array( 'wcmaster_item_title' => $item['title'] );
		}
		if ( $this->is_courier_enabled() ) {
			$rate_meta         = isset( $rate['meta_data'] ) && is_array( $rate['meta_data'] ) ? $rate['meta_data'] : array();
			$rate['meta_data'] = array_merge( $rate_meta, array( 'wcmaster_courier_title' => $this->get_courier_title() ) );
		}
		$conditions = isset( $this->data['conditions'] ) ? $this->data['conditions'] : array();
		if ( ! empty( $conditions ) ) {

			$conditions_show         = array_filter(
				$conditions,
				function( $el ) {
					return 'show' === $el['action'];
				}
			);
			$conditions_free         = array_filter(
				$conditions,
				function( $el ) {
					return 'free' === $el['action'];
				}
			);
			$conditions_add_fee      = array_filter(
				$conditions,
				function( $el ) {
					return 'add_fee' === $el['action'];
				}
			);
			$conditions_add_discount = array_filter(
				$conditions,
				function( $el ) {
					return 'add_discount' === $el['action'];
				}
			);
			$volumetric_divider      = $this->volumetric_divider();
			$match                   = true;
			if ( ! empty( $conditions_show ) ) {
				foreach ( $conditions_show as $condition ) {
					$condition_if = $condition['if'];
					$match        = Conditions::check_condition( $condition_if, $condition['statements'], $package, $volumetric_divider );
					if ( $match ) {
						break;
					}
				}
			}
			// Fee.
			if ( ! empty( $conditions_add_fee ) ) {
				foreach ( $conditions_add_fee as $condition ) {
					$condition_if = $condition['if'];
					$match_fee    = Conditions::check_condition( $condition_if, $condition['statements'], $package, $volumetric_divider );
					if ( $match_fee ) {
						if ( trim( $condition['value'] ) === '' ) {
							continue;
						}
						$fee_cost = (string) str_replace( ',', '.', $condition['value'] );
						if ( strstr( $condition['value'], '%' ) ) {

							$rate['cost'] = $rate['cost'] + ( $rate['cost'] / 100 ) * (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						} else {
							$rate['cost'] = $rate['cost'] + (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						}
					}
				}
			}
			// Discount.
			if ( ! empty( $conditions_add_discount ) ) {
				foreach ( $conditions_add_discount as $condition ) {
					$condition_if   = $condition['if'];
					$match_discount = Conditions::check_condition( $condition_if, $condition['statements'], $package, $volumetric_divider );
					if ( $match_discount ) {
						if ( trim( $condition['value'] ) === '' ) {
							continue;
						}
						$fee_cost = (string) str_replace( ',', '.', $condition['value'] );
						if ( strstr( $condition['value'], '%' ) ) {

							$rate['cost'] = $rate['cost'] - ( $rate['cost'] / 100 ) * (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						} else {
							$rate['cost'] = $rate['cost'] - (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						}
					}
				}
			}
			// Free. Make it last.
			if ( ! empty( $conditions_free ) ) {
				foreach ( $conditions_free as $condition ) {
					$condition_if = $condition['if'];
					$match_free   = Conditions::check_condition( $condition_if, $condition['statements'], $package, $volumetric_divider );
					if ( $match_free ) {
						$rate['cost'] = 0;
						break;
					}
				}
			}
			if ( $rate['cost'] < 0 ) {
				$rate['cost'] = 0;
			}
			return $match;
		}
		return true;
	}

	/**
	 * Calculate multi item cost for checkout page.
	 *
	 * @param array $package Shipping package.
	 * @param int   $child_id Item id.
	 */
	public function calculate_item_costs( $package, $child_id ) {

		$items = isset( $this->data['items'] ) ? $this->data['items'] : array();

		if ( $this->is_multiple_enabled() ) {
			if ( count( $items ) < 1 ) {
				return 0;
			}

			if ( isset( $items[ $child_id ] ) ) {

				$item = $items[ $child_id ];
			} else {
				return 0;
			}
		}
		$cost = (float) str_replace( ',', '.', $item['cost'] );

		if ( ! empty( $item['extraCosts'] ) ) {
			foreach ( $item['extraCosts'] as $extra_cost ) {
				switch ( $extra_cost['id'] ) {
					case 'fee':
						$cost += (float) $this->get_fee( str_replace( ',', '.', $extra_cost['cost'] ), $package['contents_cost'] );
						break;
					case 'cost_item':
						foreach ( $package['contents'] as $item_id => $data ) {
							$product = $data['data'];
							if ( strstr( $extra_cost['cost'], '%' ) ) {
								$cost += ( $data['line_total'] / 100 ) * (float) preg_replace( '[^0-9\.,]', '', $extra_cost['cost'] );
							} else {
								$cost += $data['quantity'] * (float) preg_replace( '[^0-9\.,]', '', $extra_cost['cost'] );
							}
						}
						break;
					case 'cost_weight':
						foreach ( $package['contents'] as $item_id => $data ) {
							$product = $data['data'];

							$value              = 0;
							$volumetric_divider = $this->volumetric_divider();
							$product_width      = (float) $product->get_width();
							$product_height     = (float) $product->get_height();
							$product_length     = (float) $product->get_length();
							$product_weight     = (float) $product->get_weight();

							if ( ! $product_width || ! $product_height || ! $product_length ) {
								$value += $product_weight;

							} elseif ( $volumetric_divider ) {
								$product_volume_weight = ( $product_width * $product_height * $product_length ) / $volumetric_divider;

								if ( $product_volume_weight > $product_weight ) {
									$value += $product_volume_weight;
								} else {
									$value += $product_weight;
								}
							} else {
								$value = $product_weight;
							}

							if ( strstr( $extra_cost['cost'], '%' ) ) {
								$cost += ( $data['line_total'] / 100 ) * (float) $value * (float) preg_replace( '[^0-9\.,]', '', $extra_cost['cost'] );
							} else {

								$cost += $data['quantity'] * (float) $value * (float) preg_replace( '[^0-9\.,]', '', $extra_cost['cost'] );
							}
						}
						break;
				}
			}
		}
		$conditions = $this->data['conditions'];

		if ( ! empty( $conditions ) ) {
			$conditions_free         = array_filter(
				$conditions,
				function( $el ) {
					return 'free' === $el['action'];
				}
			);
			$conditions_add_fee      = array_filter(
				$conditions,
				function( $el ) {
					return 'add_fee' === $el['action'];
				}
			);
			$conditions_add_discount = array_filter(
				$conditions,
				function( $el ) {
					return 'add_discount' === $el['action'];
				}
			);
			$volumetric_divider      = $this->volumetric_divider();

			// Fee.
			if ( ! empty( $conditions_add_fee ) ) {
				foreach ( $conditions_add_fee as $condition ) {
					$condition_if = $condition['if'];
					$match_fee    = Conditions::check_condition( $condition_if, $condition['statements'], $package, $volumetric_divider );
					if ( $match_fee ) {
						if ( trim( $condition['value'] ) === '' ) {
							continue;
						}
						$fee_cost = (string) str_replace( ',', '.', $condition['value'] );
						if ( strstr( $condition['value'], '%' ) ) {

							$cost += ( $cost / 100 ) * (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						} else {
							$cost += (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						}
					}
				}
			}
			// Discount.
			if ( ! empty( $conditions_add_discount ) ) {
				foreach ( $conditions_add_discount as $condition ) {
					$condition_if   = $condition['if'];
					$match_discount = Conditions::check_condition( $condition_if, $condition['statements'], $package, $volumetric_divider );
					if ( $match_discount ) {
						if ( trim( $condition['value'] ) === '' ) {
							continue;
						}
						$fee_cost = (string) str_replace( ',', '.', $condition['value'] );
						if ( strstr( $condition['value'], '%' ) ) {

							$cost -= ( $cost / 100 ) * (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						} else {
							$cost -= (float) preg_replace( '[^0-9\.,]', '', $fee_cost );
						}
					}
				}
			}
			// Free. Make it last.
			if ( ! empty( $conditions_free ) ) {
				foreach ( $conditions_free as $condition ) {
					$condition_if = $condition['if'];
					$match_free   = Conditions::check_condition( $condition_if, $condition['statements'], $package, $volumetric_divider );
					if ( $match_free ) {
						$cost = 0;
						break;
					}
				}
			}
		}
		if ( $cost < 0 ) {
			return 0;
		}
		return $cost;
	}

	/**
	 * Calculate shipping.
	 *
	 * @param array $package Shipping package.
	 */
	public function calculate_shipping( $package = array() ) {

		$rate = array(
			'id'    => $this->get_rate_id(),
			'label' => $this->title,
			'cost'  => $this->calculate_costs( $package ),
			'taxes' => 'taxable' === $this->tax_status ? '' : false,

		);

		if ( $this->match_conditions( $rate, $package ) ) {
			$this->add_rate( $rate );
		}

	}

}
