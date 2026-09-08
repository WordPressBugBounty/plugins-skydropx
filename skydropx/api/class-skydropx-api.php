<?php
/**
 * API facade for plugin external services.
 *
 * @package   Skydropx
 * @since     1.0.0
 */

namespace Skydropx\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Concrete HTTP client for the plugin's external API.
 */
class Skydropx_Api extends Skydropx_api_connector {

	/**
	 * Perform a POST with default headers applied.
	 *
	 * @param string $endpoint Relative endpoint.
	 * @param array  $body     Payload.
	 * @return array Response array with 'code', 'body', 'json'.
	 */
	public function post( string $endpoint, array $body = array() ) {
		return $this->exec( 'POST', $endpoint, $body );
	}

	/**
	 * Perform a POST request using an explicit base URL.
	 *
	 * Useful for quotation destination overrides without affecting other
	 * API calls that rely on the default base URL.
	 *
	 * @param string $absolute_url Absolute destination URL.
	 * @param array  $body         Payload.
	 * @return array Response array with 'code', 'body', 'json'.
	 */
	public function post_absolute_url( string $absolute_url, array $body = array() ) {
		return $this->exec_with_absolute_url( 'POST', $absolute_url, $body );
	}
}
