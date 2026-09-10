<?php
/**
 * WooCommerce shipping method implementation for Skydropx.
 *
 * Builds shipping quotations by calling the external platform and registers
 * WooCommerce shipping rates based on the response.
 *
 * @package   Skydropx
 * @since     1.0.0
 */

use Skydropx\Api\Skydropx_Api;
use Skydropx\Helper\Helper;
use Skydropx\Includes\Skydropx_Quotation_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Shipping method class handling quotations through Skydropx.
 */
class Skydropx_Shipping_Method extends \WC_Shipping_Method {

	/**
	 * API client used to communicate with external platform.
	 *
	 * @var Skydropx_Api
	 */
	public $api;

	/**
	 * Method identifier.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Method title shown in WooCommerce admin.
	 *
	 * @var string
	 */
	public $method_title;

	/**
	 * Method description shown in WooCommerce admin.
	 *
	 * @var string
	 */
	public $method_description;

	/**
	 * Supported features list.
	 *
	 * @var array
	 */
	public $supports;

	/**
	 * Default constructor.
	 *
	 * @param int $instance_id Shipping Method Instance from Order.
	 * @return void
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->api                = new Skydropx_Api();
		$this->id                 = SKYDROPX_SHIPPING_METHOD_ID;
		$this->method_title       = __( 'Tarifas dinámicas en carrito', 'skydropx' );
		$this->method_description = __( 'Cotiza los envíos de tu tienda con tus paqueterías preferidas. Para configuraciones adicionales, dirígete a Skydropx.', 'skydropx' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init_instance_form_fields();
		$this->init_settings();
		$this->title = $this->get_option( 'title', $this->method_title );
	}

	/**
	 * Define per instance settings with defaults.
	 *
	 * @return void
	 */
	public function init_instance_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'default'     => __( 'Tarifas dinámicas en carrito', 'skydropx' ),
				'description' => '',
			),
		);
	}

	/**
	 * @param array $package WooCommerce package data used for destination info.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		// 1) Feature flag and required configuration guard, avoid hitting the API
		// when quotations are disabled or the quotation base URL is missing.
		if ( ! $this->is_quotation_enabled() || null === Skydropx_Quotation_Settings::get_base_url() ) {
			Helper::log_error( '[QUOTATION] Quotation disabled or quotation base URL missing. Skip.' );
			return;
		}

		// 2) Skip when destination postcode is missing to avoid unnecessary work.
		$destination_postcode = isset( $package['destination']['postcode'] ) ? $package['destination']['postcode'] : '';
		if ( empty( $destination_postcode ) ) {
			Helper::log_error( '[QUOTATION] No destination postcode. Skip.' );
			return;
		}

		// 3) Compute a fingerprint and try cache first, multiple
		// front end refreshes often trigger repeated requests cache prevents
		// unnecessary API calls.
		$fingerprint  = $this->compute_quote_fingerprint( $package );
		$cache_key    = $this->get_cache_key( $fingerprint );
		$cached_rates = $this->get_valid_cached_rates( $cache_key );

		if ( is_array( $cached_rates ) ) {
			Helper::log_error( '[QUOTATION] Using cached rates (' . count( $cached_rates ) . ').' );
			$this->add_rates( $cached_rates );
			return;
		}

		// 3) In process guard, skip duplicate calculations within the same PHP
		// request lifecycle (WooCommerce triggers calculation more than once).
		static $in_process = array();
		if ( isset( $in_process[ $fingerprint ] ) ) {
			Helper::log_error( '[QUOTATION] Duplicate request in the same process. Skip.' );
			return;
		}
		$in_process[ $fingerprint ] = true;

		try {
			// 4) Build payload, call external API and map quotes to WC rates.
			$rates_for_wc = $this->quote_rates( $package );
			if ( empty( $rates_for_wc ) ) {
				Helper::log_error( __( 'No rates returned from quotation.', 'skydropx' ) );
				return;
			}

			// 5) Store a short lived cache entry, throttle re quoting for identical
			// inputs and improve perceived latency.
			$this->set_cached_rates( $cache_key, $rates_for_wc, 2 * MINUTE_IN_SECONDS );

			// 6) Register rates with WooCommerce.
			$this->add_rates( $rates_for_wc );
		} finally {
			// 7) Always release the lock.
			$this->release_request_lock( $fingerprint );
		}
	}

	/**
	 * Builds the quotation request, invokes the API and maps the response
	 * into WooCommerce rate arrays.
	 *
	 * @param array $package WooCommerce package with destination data.
	 * @return array List of mapped WC rate arrays.
	 */
	private function quote_rates( array $package ) {
		$body = $this->build_request_body( $package );
		if ( empty( $body ) ) {
			Helper::log_error( '[QUOTATION] Empty request body. Skip.' );
			return array();
		}

		$response = $this->post_rates( $body );

		$rate_list = $this->extract_rates_from_response( $response );

		if ( empty( $rate_list ) ) {
			return array();
		}

		$rates_for_wc = array();
		foreach ( $rate_list as $quote ) {
			$rate = $this->map_quote_to_rate( $quote );
			if ( ! empty( $rate ) ) {
				$rates_for_wc[] = $rate;
			}
		}

		return $rates_for_wc;
	}

	/**
	 * Post rates request to the quotation URL configured by the backend.
	 *
	 * @param array $body Payload.
	 * @return array|false
	 */
	private function post_rates( array $body ) {
		$base_url = Skydropx_Quotation_Settings::get_base_url();
		Helper::log_info( sprintf( '[QUOTATION] Using quotation URL: %s', $base_url ) );
		return $this->api->post_absolute_url( $base_url, $body );
	}

	/**
	 * Check if quotation is enabled.
	 *
	 * @return bool
	 */
	private function is_quotation_enabled() {
		return Skydropx_Quotation_Settings::is_enabled();
	}

	/**
	 * Build the request payload for quotation.
	 *
	 * @param array $package WooCommerce package.
	 * @return array Payload array or empty array when invalid.
	 */
	private function build_request_body( array $package ) {
		$address_to = $this->build_address_to( $package );
		if ( empty( $address_to ) ) {
			return array();
		}

		$address_from = $this->build_address_from();
		if ( empty( $address_from ) ) {
			return array();
		}

		$items = $this->build_items_from_cart();
		if ( empty( $items ) ) {
			return array();
		}

		$body                 = array();
		$body['address_from'] = $address_from;
		$body['address_to']   = $address_to;
		$body['items']        = $items;
		$body['total_price']  = Helper::get_cart_total( WC()->cart );

		return $body;
	}

	/**
	 * Build address to.
	 *
	 * @param array $package WooCommerce package.
	 * @return array Address to.
	 */
	private function build_address_to( array $package ) {
		$destination_postcode = isset( $package['destination']['postcode'] ) ? $package['destination']['postcode'] : '';
		if ( empty( $destination_postcode ) ) {
			Helper::log_error( __( 'No Zipcode for destination', 'skydropx' ) );
			return array();
		}

		$destination = $package['destination'];
		$address_to  = array(
			'address'   => isset( $destination['address'] ) ? (string) $destination['address'] : ( isset( $destination['address_1'] ) ? (string) $destination['address_1'] : '' ),
			'city'      => isset( $destination['city'] ) ? (string) $destination['city'] : '',
			'state'     => isset( $destination['state'] ) ? (string) $destination['state'] : '',
			'country'   => isset( $destination['country'] ) ? (string) $destination['country'] : '',
			'zip_code'  => isset( $destination['postcode'] ) ? (string) $destination['postcode'] : '',
			'address_2' => isset( $destination['address_2'] ) ? (string) $destination['address_2'] : '',
		);

		return $address_to;
	}

	/**
	 * Build address from.
	 *
	 * @return array Address from.
	 */
	private function build_address_from() {
		$origin_zip = get_option( 'woocommerce_store_postcode' );
		if ( empty( $origin_zip ) ) {
			Helper::log_error( __( 'No Zipcode for origin', 'skydropx' ) );
			return array();
		}

		$store_raw_country = (string) get_option( 'woocommerce_default_country' );
		$split_country     = explode( ':', $store_raw_country );

		$address_from = array(
			'address'   => get_option( 'woocommerce_store_address' ),
			'city'      => get_option( 'woocommerce_store_city' ),
			'country'   => isset( $split_country[0] ) ? $split_country[0] : '',
			'state'     => isset( $split_country[1] ) ? $split_country[1] : '',
			'zip_code'  => $origin_zip,
			'address_2' => (string) get_option( 'woocommerce_store_address_2', '' ),
		);

		return $address_from;
	}

	/**
	 * Build items from cart.
	 *
	 * @return array Items from cart.
	 */
	private function build_items_from_cart() {
		$items_from_cart = Helper::get_items_from_cart( WC()->cart );
		if ( false === $items_from_cart ) {
			return array();
		}

		$grouped_items = Helper::group_items( $items_from_cart );
		$items         = array();

		foreach ( $grouped_items as $item ) {
			$item_to_quote             = array();
			$item_to_quote['id']       = $item['id'];
			$item_to_quote['weight']   = (float) $item['weight'];
			$item_to_quote['quantity'] = (float) $item['quantity'];
			$item_to_quote['height']   = (float) $item['height'];
			$item_to_quote['width']    = (float) $item['width'];
			$item_to_quote['length']   = (float) $item['length'];
			$item_to_quote['price']    = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			$items[]                   = $item_to_quote;
		}

		return $items;
	}

	/**
	 * Extracts the list of quotes from the API response.
	 *
	 * @param mixed $response API raw response.
	 * @return array List of quote arrays or empty array on errors.
	 */
	private function extract_rates_from_response( $response ) {
		if ( false === $response || ! is_array( $response ) ) {
			Helper::log_error( __( 'Quotation request failed.', 'skydropx' ) );
			return array();
		}

		$code = isset( $response['code'] ) ? (int) $response['code'] : 0;
		$json = isset( $response['json'] ) ? $response['json'] : null;

		if ( 200 !== $code || ! is_array( $json ) ) {
			Helper::log_error( __( 'Unexpected quotation response.', 'skydropx' ) );
			return array();
		}

		if ( isset( $json['errors'] ) && ! empty( $json['errors'] ) ) {
			Helper::log_error( $json['errors'] );
		}

		return isset( $json['rates'] ) && is_array( $json['rates'] ) ? $json['rates'] : array();
	}

	/**
	 * Maps a quote entity from the API into a WooCommerce rate array.
	 *
	 * @param array $quote Quote from the API.
	 * @return array WooCommerce rate array.
	 */
	private function map_quote_to_rate( array $quote ) {
		$service_name = isset( $quote['service_name'] ) ? trim( wp_strip_all_tags( (string) $quote['service_name'] ) ) : '';
		$provider     = isset( $quote['service_provider'] ) ? (string) $quote['service_provider'] : '';
		$service_code = isset( $quote['service_code'] ) ? (string) $quote['service_code'] : '';
		$total_price  = isset( $quote['total_price'] ) ? (float) $quote['total_price'] : 0.0;
		$delivery     = isset( $quote['description'] ) ? $quote['description'] : null;
		$currency     = isset( $quote['currency'] ) ? (string) $quote['currency'] : '';

		$label = $service_name;
		if ( ! empty( $delivery ) ) {
			$label .= ' (' . $delivery . ')';
		}

		if ( 0.0 === $total_price ) {
			$label .= ' ' . __( '(¡Gratis!)', 'skydropx' );
		}

		$rate = array(
			'id'        => $this->get_rate_id() . '_' . esc_attr( $provider ) . '_' . esc_attr( $service_code ),
			'label'     => $label,
			'cost'      => $total_price,
			'calc_tax'  => 'per_order',
			'meta_data' => array(
				'provider'           => $provider,
				'service_level_name' => $service_name,
				'service_level_code' => $service_code,
				'days'               => $delivery,
				'total_pricing'      => $total_price,
				'currency_local'     => $currency,
			),
		);

		return $rate;
	}

	/**
	 * Get session id.
	 *
	 * @return string
	 */
	private function get_session_id() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return (string) WC()->session->get_customer_id();
		}
		return 'no-session';
	}

	/**
	 * Computes a fingerprint for caching/locking purposes.
	 *
	 * TTwo identical quotations should share cache and locks regardless of
	 * incidental differences a compact hash allows safe keying.
	 *
	 * @param array $package WooCommerce package.
	 * @return string MD5 hash over a normalized payload.
	 */
	private function compute_quote_fingerprint( array $package ) {
		$items          = $this->build_items_from_cart();
		$id_to_quantity = array();

		foreach ( $items as $item ) {
			if ( isset( $item['id'] ) ) {
				$id                    = (string) $item['id'];
				$qty                   = isset( $item['quantity'] ) ? (float) $item['quantity'] : 0.0;
				$id_to_quantity[ $id ] = ( isset( $id_to_quantity[ $id ] ) ? (float) $id_to_quantity[ $id ] : 0.0 ) + $qty;
			}
		}

		ksort( $id_to_quantity, SORT_STRING );
		$normalized_items = array();

		foreach ( $id_to_quantity as $pid => $qty ) {
			$normalized_items[] = array(
				'id'  => $pid,
				'qty' => (float) $qty,
			);
		}

		$dest = array(
			'postcode' => isset( $package['destination']['postcode'] ) ? (string) $package['destination']['postcode'] : '',
			'country'  => isset( $package['destination']['country'] ) ? (string) $package['destination']['country'] : '',
			'state'    => isset( $package['destination']['state'] ) ? (string) $package['destination']['state'] : '',
			'city'     => isset( $package['destination']['city'] ) ? (string) $package['destination']['city'] : '',
		);

		$payload = array(
			'items' => $normalized_items,
			'to'    => $dest,
			'total' => Helper::get_cart_total( WC()->cart ),
		);

		return md5( wp_json_encode( $payload ) );
	}

	/**
	 * Builds a session  and instance scoped cache key.
	 *
	 * Avoid cross customer and cross instance collisions in multi zone
	 * setups by encoding session, method id and instance id.
	 *
	 * @param string $fingerprint Deterministic request fingerprint.
	 * @return string Cache key.
	 */
	private function get_cache_key( $fingerprint ) {
		$session_id  = $this->get_session_id();
		$instance_id = (string) $this->instance_id;
		return 'SKYDROPX_QUOTE_CACHE:' . $session_id . ':' . $this->id . ':' . $instance_id . ':' . $fingerprint;
	}


	/**
	 * Get cached rates.
	 *
	 * @param string $cache_key Cache key.
	 * @return array|null Cached rates or null when missing.
	 */
	private function get_cached_rates( $cache_key ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			$data = WC()->session->get( $cache_key );
			return is_array( $data ) ? $data : null;
		}
		return null;
	}

	/**
	 * Store rates in session cache with an absolute expiry.
	 *
	 * short lived caching prevents API stampedes during checkout address
	 * edits and improves UX responsiveness.
	 *
	 * @param string $cache_key   Cache key.
	 * @param array  $rates       Rate arrays to store.
	 * @param int    $ttl_seconds Time to live in seconds.
	 * @return void
	 */
	private function set_cached_rates( $cache_key, array $rates, $ttl_seconds ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			$record = array(
				'rates'   => $rates,
				'expires' => time() + (int) $ttl_seconds,
			);
			WC()->session->set( $cache_key, $record );
		}
	}

	/**
	 * Return cached rates when present and not expired.
	 *
	 * Callers shouldn't need to reimplement TTL validation logic.
	 *
	 * @param string $cache_key Cache key.
	 * @return array|null Rates or null when missing/expired.
	 */
	private function get_valid_cached_rates( $cache_key ) {
		$record = $this->get_cached_rates( $cache_key );
		if ( is_array( $record ) && isset( $record['expires'], $record['rates'] ) && time() <= (int) $record['expires'] ) {
			return is_array( $record['rates'] ) ? $record['rates'] : null;
		}
		return null;
	}

	/**
	 * Releases the previously acquired lock.
	 *
	 * Ensure that subsequent requests can compute fresh quotations.
	 *
	 * @param string $fingerprint Deterministic request fingerprint.
	 * @return void
	 */
	private function release_request_lock( $fingerprint ) {
		$option_name = $this->get_lock_option_name( $fingerprint );
		delete_option( $option_name );
	}

	/**
	 * Builds the lock option name used in wp_options as a mutex key.
	 *
	 * Scope locks per customer session and method instance to avoid
	 * cross user interference.
	 *
	 * @param string $fingerprint Deterministic request fingerprint.
	 * @return string Option name used for locking.
	 */
	private function get_lock_option_name( $fingerprint ) {
		$session_id  = $this->get_session_id();
		$instance_id = (string) $this->instance_id;
		return 'SKYDROPX_QUOTE_LOCK:' . $session_id . ':' . $this->id . ':' . $instance_id . ':' . $fingerprint;
	}

	/**
	 * Add multiple rates to WooCommerce.
	 *
	 * @param array $rates List of rate arrays.
	 */
	private function add_rates( array $rates ) {
		foreach ( $rates as $rate ) {
			$this->add_rate( $rate );
		}
	}
}
