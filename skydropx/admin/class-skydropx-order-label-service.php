<?php
/**
 * Order label helper service.
 *
 * Responsible for order resolution, label availability checks and
 * remote URL/header construction.
 *
 * @package   Skydropx
 * @since     1.0.0
 */

namespace Skydropx\Admin;

defined( 'ABSPATH' ) || exit;

use Skydropx\Helper\Helper;

/**
 * Provides reusable helpers for order label UI/printing.
 */
class Skydropx_Order_Label_Service {

	/**
	 * Meta keys considered for a generated label.
	 */
	private const META_TRACKING_NUMBER  = 'Tracking Number';
	private const META_TRACKING_COMPANY = 'Tracking Company';
	private const META_TRACKING_URL     = 'Tracking URL';

	/**
	 * Resolve a WC_Order from either WP_Post or WC_Order depending on storage.
	 *
	 * @param mixed $post_or_order Object passed by metabox callback.
	 * @return \WC_Order|null
	 */
	public function resolve_order( $post_or_order ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		if ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
			return wc_get_order( (int) $post_or_order->ID ) ?: null;
		}

		if ( is_object( $post_or_order ) && method_exists( $post_or_order, 'get_id' ) ) {
			return $post_or_order;
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id > 0 ) {
			return wc_get_order( $id ) ?: null;
		}

		return null;
	}

	/**
	 * Fetch order by id.
	 *
	 * @param int $order_id Order ID.
	 * @return \WC_Order|null
	 */
	public function get_order_by_id( int $order_id ) {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		return wc_get_order( $order_id ) ?: null;
	}

	/**
	 * Check if current user can access the given order.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function current_user_can_access_order( int $order_id ): bool {
		if ( $order_id <= 0 ) {
			return false;
		}

		if ( current_user_can( 'edit_shop_order', $order_id ) ) {
			return true;
		}

		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Validate the order has shipping items with required tracking metadata.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public function has_label_tracking_meta( $order ): bool {
		if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return false;
		}

		$shipping_items = $order->get_items( 'shipping' );
		foreach ( (array) $shipping_items as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}

			$tracking_number  = (string) $item->get_meta( self::META_TRACKING_NUMBER, true );
			$tracking_company = (string) $item->get_meta( self::META_TRACKING_COMPANY, true );
			$tracking_url     = (string) $item->get_meta( self::META_TRACKING_URL, true );

			if (
				'' !== trim( $tracking_number )
				&& '' !== trim( $tracking_company )
				&& '' !== trim( $tracking_url )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build remote label URL for the Docs endpoint.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public function build_remote_label_url( int $order_id ): string {
		$base = defined( 'SKYDROPX_ECOMMERCE_URL' ) ? (string) SKYDROPX_ECOMMERCE_URL : '';
		$base = rtrim( $base, '/' );
		if ( '' === $base ) {
			return '';
		}

		$platform = 'woocommerce';
		$country  = $this->get_store_country_code();
		$domain   = Helper::get_store_identifier();

		if ( '' === $country || '' === $domain ) {
			return '';
		}

		$endpoint = sprintf(
			'/api/v3/order/%s/%s/labels',
			rawurlencode( $platform ),
			rawurlencode( $country )
		);

		$url = $base . $endpoint;

		return add_query_arg(
			array(
				'order_id' => $order_id,
				'domain'   => $domain,
			),
			$url
		);
	}

	/**
	 * Build headers for remote PDF request.
	 *
	 * @param string $accept Accept header value.
	 * @return array
	 */
	public function build_remote_headers( string $accept ): array {
		return array(
			'Accept'     => $accept,
			'User-Agent' => 'Skydropx/' . ( defined( 'SKYDROPX_VERSION' ) ? SKYDROPX_VERSION : 'dev' ),
		);
	}

	/**
	 * Get store country code from Woocommerce settings.
	 *
	 * @return string
	 */
	private function get_store_country_code(): string {
		$raw = (string) get_option( 'woocommerce_default_country', '' ); // e.g. "MX:CMX"
		if ( '' === $raw ) {
			return '';
		}
		$parts = explode( ':', $raw );
		$cc    = isset( $parts[0] ) ? strtoupper( trim( (string) $parts[0] ) ) : '';
		return '' !== $cc ? strtolower( $cc ) : '';
	}
}
