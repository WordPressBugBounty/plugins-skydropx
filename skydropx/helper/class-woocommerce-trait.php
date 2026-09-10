<?php
/**
 * WooCommerce utilities used by the plugin for rate calculation.
 *
 * Helpers to extract standardized product dimensions and weights, collect
 * physical items from the cart and group repeated items with quantities.
 *
 * @package  Skydropx\Helper
 * @since    1.0.0
 */

namespace Skydropx\Helper;

trait WooCommerce_Trait {

	/**
	 * Get product dimensions and weight in standardized units.
	 *
	 * Returns height, width, length, weight, price, name, id, sku, and a
	 * calculated volume metric (wc-product-size). Units used: cm/kg. The price
	 * is the displayed unit price, so it follows the store tax display setting.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|false    Associative array with metrics or false when product is invalid.
	 */
	public static function get_product_dimensions( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$dimension_unit = 'cm';
		$weight_unit    = 'kg';

		$height = $product->get_height() ? wc_get_dimension( $product->get_height(), $dimension_unit ) : '0';
		$width  = $product->get_width() ? wc_get_dimension( $product->get_width(), $dimension_unit ) : '0';
		$length = $product->get_length() ? wc_get_dimension( $product->get_length(), $dimension_unit ) : '0';
		$weight = $product->has_weight() ? wc_get_weight( $product->get_weight(), $weight_unit ) : '0';

		return array(
			'height'          => $height,
			'width'           => $width,
			'length'          => $length,
			'weight'          => $weight,
			'price'           => wc_get_price_to_display( $product ),
			'description'     => $product->get_name(),
			'id'              => $product_id,
			'sku'             => $product->get_sku(),
			'wc-product-size' => $height * $width * $length,
		);
	}

	/**
	 * Extract physical items with dimensions from a WooCommerce cart.
	 *
	 * Duplicates entries per quantity to ease packing/quotation and logs an
	 * error when an item's dimensions cannot be obtained.
	 *
	 * @param \WC_Cart $cart Current WooCommerce cart instance.
	 * @return array|false   Array of items with metrics or false on invalid item.
	 */
	public static function get_items_from_cart( $cart ) {
		$products = array();
		$items    = $cart->get_cart();
		foreach ( $items as $item ) {
			$product_id = $item['data']->get_id();
			$product    = wc_get_product( $product_id );
			if ( ! ( $product->is_downloadable( 'yes' ) || $product->is_virtual( 'yes' ) ) ) {
				$new_product = self::get_product_dimensions( $product_id );
				if ( ! $new_product ) {
					self::log_error( esc_html__( 'Helper -> Error obteniendo productos del carrito, producto con malas dimensiones - ID: ', 'skydropx' ) . esc_html( $product_id ) );
					return false;
				}
				for ( $i = 0; $i < $item['quantity']; $i++ ) {
					$products[] = $new_product;
				}
			}
		}
		return $products;
	}

	/**
	 * Group identical items by product id and accumulate quantity.
	 *
	 * @param array $items Flat list of items (potentially with duplicates).
	 * @return array       Items keyed by product id with a quantity field.
	 */
	public static function group_items( array $items ) {
		$grouped_items = array();
		foreach ( $items as $item ) {
			if ( isset( $grouped_items[ $item['id'] ] ) ) {
				++$grouped_items[ $item['id'] ]['quantity'];
			} else {
				$grouped_items[ $item['id'] ]             = $item;
				$grouped_items[ $item['id'] ]['quantity'] = 1;
			}
		}
		return $grouped_items;
	}

 
	/**
	 * Total value of the cart contents as the customer sees it.
	 *
	 * Mirrors the cart subtotal line: coupons already applied, shipping and
	 * fees excluded, taxes included only when the store displays prices with
	 * taxes. Conditional rates are configured against that same figure, so any
	 * other reading would make a "free shipping over X" rule fire at the wrong
	 * threshold.
	 *
	 * @param \WC_Cart $cart Current WooCommerce cart instance.
	 * @return float         Cart contents total, 0.0 when the cart is unavailable.
	 */
	public static function get_cart_total( $cart ) {
		if ( ! $cart instanceof \WC_Cart ) {
			return 0.0;
		}

		$total = (float) $cart->get_cart_contents_total();
		if ( $cart->display_prices_including_tax() ) {
			$total += (float) $cart->get_cart_contents_tax();
		}

		return round( $total, wc_get_price_decimals() );
	}
}
