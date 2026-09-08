<?php
/**
 * Shipping zone manager for the plugin.
 *
 * Handles creation of a default shipping zone and cleanup on uninstall.
 *
 * @package   Skydropx
 * @since     1.0.0
 */

namespace Skydropx\Includes;

use Skydropx\Helper\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Ensure ABSPATH is defined, which means we are in a WordPress environment.
}

if ( ! class_exists( '\WC_Shipping_Zone' ) || ! class_exists( '\WC_Shipping_Zones' ) ) {
	exit; // Exit if any of the required WooCommerce shipping classes don't exist.
}


/**
 * Shipping zone utilities for Skydropx.
 */
class Skydropx_Shipping_Zone extends \WC_Shipping_Zone {
	/**
	 * Countries supported for default zone coverage.
	 *
	 * @var string[]
	 */
	const DEFAULT_COUNTRIES = array( 'MX', 'CA', 'CN', 'CO', 'ES', 'US', 'FR', 'GB' );

	/**
	 * Create and configure the plugin shipping zone if it does not exist.
	 *
	 * Adds supported country and the plugin shipping method to the zone.
	 *
	 * @return void
	 */
	public function create() {
		if ( ! is_null( $this->get_previous_data() ) ) {
			return;
		}

		$this->set_zone_name( SKYDROPX_SHIPPING_ZONE_NAME );

		foreach ( self::DEFAULT_COUNTRIES as $country_code ) {
			$this->add_location( $country_code, 'country' );
		}

		$this->save();
		$this->add_shipping_method( SKYDROPX_SHIPPING_METHOD_ID );
		$this->promote_zone_to_top();
	}

	/**
	 * Remove the plugin's shipping zone if present.
	 *
	 * @return void
	 */
	public static function remove() {
		$zones = \WC_Shipping_Zones::get_zones();

		foreach ( (array) $zones as $key => $zone ) {
			if ( SKYDROPX_SHIPPING_ZONE_NAME === $zone['zone_name'] ) {
				\WC_Shipping_Zones::delete_zone( $zone['zone_id'] );
			}
		}

		// Translators: message informing about zone deletion.
		Helper::log_info( esc_html__( 'WC shipping zone deleted', 'skydropx' ) );
	}

	/**
	 * Promote this zone to the top of the zones list and re order others.
	 *
	 * @return void
	 */
	private function promote_zone_to_top() {
		$my_id = (int) $this->get_id();
		if ( $my_id <= 0 ) {
			return;
		}

		$zones       = \WC_Shipping_Zones::get_zones();
		$ordered_ids = array( $my_id );

		foreach ( (array) $zones as $zone ) {
			$zone_id = (int) $zone['zone_id'];
			if ( $zone_id !== $my_id ) {
				$ordered_ids[] = $zone_id;
			}
		}

		$order = 0;
		foreach ( $ordered_ids as $zone_id ) {
			$zone = new \WC_Shipping_Zone( $zone_id );
			$zone->set_zone_order( $order++ );
			$zone->save();
		}
	}

	/**
	 * Retrieve existing shipping zone data with the configured name.
	 *
	 * @return array|null Zone data or null when not found.
	 */
	private function get_previous_data() {
		$zones = \WC_Shipping_Zones::get_zones();

		foreach ( (array) $zones as $key => $zone ) {
			if ( SKYDROPX_SHIPPING_ZONE_NAME === $zone['zone_name'] ) {
				return $zone;
			}
		}

		return null;
	}
}
