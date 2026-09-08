<?php
/**
 * Repository for plugin data operations.
 *
 * Handles WooCommerce API keys CRUD and cleanup of related webhooks.
 *
 * @package   Skydropx
 * @subpackage Skydropx/includes
 * @since     1.0.0
 */

namespace Skydropx\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Skydropx\Helper\Helper;

/**
 * Data access layer for API keys and webhooks.
 */
class Skydropx_Repository {


	/**
	 * Retrieve WooCommerce API key row by hashed consumer key.
	 *
	 * Always restricts to keys created by this plugin (description LIKE '%Skydropx%').
	 *
	 * @since 1.0.0
	 * @param string $hashed_key          Hashed consumer key (wc_api_hash(ck)).
	 * @param array  $allowed_permissions Optional list of allowed permissions to filter.
	 * @return array|null                 Array with 'permissions' and 'consumer_secret' or null.
	 */
	public function get_api_key_by_hashed_key( $hashed_key, $allowed_permissions = array() ) {
		global $wpdb;

		if ( empty( $hashed_key ) ) {
			return null;
		}

		$table  = $wpdb->prefix . 'woocommerce_api_keys';
		$where  = 'consumer_key = %s';
		$params = array( $hashed_key );

		if ( is_array( $allowed_permissions ) && ! empty( $allowed_permissions ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $allowed_permissions ), '%s' ) );
			$where       .= " AND permissions IN ( {$placeholders} )";
			$params       = array_merge( $params, array_values( $allowed_permissions ) );
		}

		$where   .= ' AND description LIKE %s';
		$params[] = '%' . $wpdb->esc_like( 'Skydropx' ) . '%';

		$query = "SELECT permissions, consumer_secret FROM {$table} WHERE {$where} LIMIT 1";

		$row = $wpdb->get_row( $wpdb->prepare( $query, $params ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch API keys created by Skydropx for a given user ID.
	 *
	 * @since 1.0.0
	 * @param int $user_id The WordPress user ID.
	 * @return array|null Array of API keys or null if not found.
	 */
	public function fetch_api_keys_by_user( $user_id ) {
		global $wpdb;

		// Add a wildcard to the description search.
		$like_condition = '%' . $wpdb->esc_like( 'Skydropx' ) . '%';

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT consumer_key, consumer_secret FROM ' . $wpdb->prefix . 'woocommerce_api_keys WHERE user_id = %d AND description LIKE %s ORDER BY key_id DESC LIMIT 1',
				$user_id,
				$like_condition
			),
			ARRAY_A
		);
	}

	/**
	 * Clean webhooks linked to this plugin services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clean_webhooks() {
		global $wpdb;

		$like_condition = '%' . $wpdb->esc_like( 'https://ecommerce.pro.skydropx.com' ) . '%';

		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $wpdb->prefix . 'wc_webhooks WHERE status = %s AND delivery_url LIKE %s',
				'active',
				$like_condition
			)
		);

		$this->log_query_results( $result );
	}

	/**
	 * Delete API keys created by this plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function delete_api_keys() {
		try {
			global $wpdb;

			$like_condition = '%' . $wpdb->esc_like( 'Skydropx' ) . '%';

			$result = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM ' . $wpdb->prefix . 'woocommerce_api_keys WHERE description LIKE %s AND permissions = %s',
					$like_condition,
					'read_write'
				)
			);

			$this->log_query_results( $result );
		} catch ( \Throwable $th ) {
			Helper::log_error(
				sprintf(
					// Translators: %s is the error message encountered during API key deletion.
					__( 'Exception occurred deleting API keys: %s', 'skydropx' ),
					esc_html( $th->getMessage() )
				)
			);
		}
	}

	/**
	 * Reset API keys by deleting existing ones and creating new ones.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error New [consumer_key, consumer_secret] or WP_Error on failure.
	 */
	public function reset_api_keys() {
		$this->delete_api_keys();
		return $this->create_wc_api_keys();
	}

	/**
	 * Create WooCommerce API keys for the current user.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error Array [consumer_key, consumer_secret] or WP_Error on failure.
	 */
	private function create_wc_api_keys() {
		global $wpdb;

		$user = wp_get_current_user();
		if ( ! $user || empty( $user->ID ) ) {
			return new \WP_Error( 'invalid_user', __( 'Invalid user', 'skydropx' ) );
		}

		// Generate API keys.
		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();
		$hashed_key      = wc_api_hash( $consumer_key );

		$data = array(
			'user_id'         => $user->ID,
			'description'     => 'Skydropx',
			'permissions'     => 'read_write',
			'consumer_key'    => $hashed_key,
			'consumer_secret' => $consumer_secret,
			'truncated_key'   => substr( $consumer_key, -7 ),
		);

		$result = $wpdb->insert( $wpdb->prefix . 'woocommerce_api_keys', $data, array( '%d', '%s', '%s', '%s', '%s', '%s' ) );
		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', __( 'Could not create the API key.', 'skydropx' ) );
		}

		return array( $consumer_key, $consumer_secret );
	}

	/**
	 * Log query execution results for diagnostics.
	 *
	 * @since 1.0.0
	 * @param mixed $result Query execution result.
	 * @return void
	 */
	private function log_query_results( $result ) {
		global $wpdb;
		if ( false === $result ) {
			// Translators: %s is the last database error message.
			Helper::log_error( sprintf( __( 'Error executing query: %s', 'skydropx' ), esc_html( $wpdb->last_error ) ) );
		} else {
			// Translators: %d is the number of affected rows after a database query.
			Helper::log_info( sprintf( __( 'Query executed successfully, rows affected: %d', 'skydropx' ), (int) $result ) );
		}
	}
}
