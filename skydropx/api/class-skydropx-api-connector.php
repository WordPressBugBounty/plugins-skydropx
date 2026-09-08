<?php
/**
 * HTTP connector base class for external API calls.
 *
 * Provides a thin wrapper over wp_safe_remote_request with JSON handling,
 * logging and helper methods used by the concrete API client.
 *
 * @package   Skydropx
 * @subpackage Skydropx/api
 * @since     1.0.0
 */

namespace Skydropx\Api;

use Skydropx\Helper\Helper;

/**
 * Abstract HTTP connector with common request execution helpers.
 */
abstract class Skydropx_api_connector {

	/**
	 * Default HTTP timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 15;

	/**
	 * HTTP error code for transport failures.
	 */
	private const HTTP_ERROR_CODE = 0;

	/**
	 * Execute an HTTP request.
	 *
	 * Encodes body as JSON for non GET requests, performs the request with
	 * wp_safe_remote_request, logs request/response and returns decoded JSON
	 * on 200 or an error structure otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method   HTTP method (GET, POST, PUT, DELETE).
	 * @param string $endpoint Relative endpoint (e.g., '/v3/rates/checkout').
	 * @param array  $body     Request body.
	 * @param array  $headers  Request headers.
	 * @return array|false     Decoded response array or false on WP_Error.
	 */
	protected function exec( string $method, string $endpoint, array $body = array(), array $headers = array() ) {
		$method = strtoupper( $method );
		$url    = $this->build_url( $this->get_base_url(), $endpoint );
		return $this->request( $method, $url, $body, $headers );
	}

	/**
	 * Execute an HTTP request against an explicit base URL.
	 *
	 * This method is used to override the quotation destination without
	 * changing the default base URL (which is used by other flows like uninstall).
	 *
	 * The base URL is expected to be an absolute URL (e.g. 'https://example.com/api')
	 * and the endpoint a relative path beginning with '/' (e.g. '/v3/rates/...').
	 *
	 * @since 1.0.0
	 *
	 * @param string $method       HTTP method (GET, POST, PUT, DELETE).
	 * @param string $absolute_url Absolute request URL.
	 * @param array  $body         Request body.
	 * @param array  $headers      Request headers.
	 * @return array|false         Normalized response array or false on transport errors.
	 */
	protected function exec_with_absolute_url( string $method, string $absolute_url, array $body = array(), array $headers = array() ) {
		$method = strtoupper( $method );
		$url    = esc_url_raw( trim( $absolute_url ) );
		return $this->request( $method, $url, $body, $headers );
	}

	/**
	 * Build an absolute URL from base URL + endpoint.
	 *
	 * Applies minimal normalization as a last safety net so callers do not
	 * need to duplicate slash handling.
	 *
	 * @param string $base_url Base URL.
	 * @param string $endpoint Relative endpoint.
	 * @return string
	 */
	private function build_url( string $base_url, string $endpoint ): string {
		$base = rtrim( trim( $base_url ), '/' );
		$path = '/' . ltrim( trim( $endpoint ), '/' );
		return esc_url_raw( $base . $path );
	}

	/**
	 * Execute the HTTP request and normalize response.
	 *
	 * Shared implementation for exec() and exec_with_absolute_url() to keep
	 * transport behavior consistent and DRY.
	 *
	 * @param string $method  HTTP method (already uppercased).
	 * @param string $url     Absolute URL.
	 * @param array  $body    Payload.
	 * @param array  $headers Headers.
	 * @return array Normalized response.
	 */
	private function request( string $method, string $url, array $body = array(), array $headers = array() ): array {
		$args = $this->build_request_args( $method, $body, $headers );

		Helper::log( sprintf( 'HTTP %s %s', $method, $url ) );

		$request = wp_safe_remote_request( $url, $args );
		if ( is_wp_error( $request ) ) {
			Helper::log( sprintf( 'HTTP ERROR %s %s', $request->get_error_message(), $url ) );
			return array(
				'code'  => self::HTTP_ERROR_CODE,
				'error' => $request->get_error_message(),
			);
		}

		$normalized = $this->normalize_response( $request );
		Helper::log( sprintf( 'HTTP %d %s', (int) $normalized['code'], $url ) );

		return $normalized;
	}

	/**
	 * Get plugin version for User-Agent header.
	 *
	 * @return string
	 */
	private function get_plugin_version() {
		return defined( 'SKYDROPX_VERSION' ) ? SKYDROPX_VERSION : 'dev';
	}

	/**
	 * Build default headers for transport.
	 *
	 * @param array $headers User headers.
	 * @return array
	 */
	private function build_default_headers( array $headers ) {
		$default_headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'User-Agent'   => 'Skydropx/' . $this->get_plugin_version(),
		);
		return array_merge( $default_headers, $headers );
	}

	/**
	 * Build request args for WP HTTP API.
	 *
	 * @param string $method HTTP method.
	 * @param array  $body   Payload.
	 * @param array  $headers Headers.
	 * @return array
	 */
	private function build_request_args( string $method, array $body, array $headers ) {
		$request_args = array(
			'timeout' => self::DEFAULT_TIMEOUT,
			'method'  => $method,
			'headers' => $this->build_default_headers( $headers ),
		);

		if ( 'GET' !== $method ) {
			$request_args['body'] = wp_json_encode( $body, JSON_UNESCAPED_UNICODE );
		}

		return $request_args;
	}

	/**
	 * Normalize WP HTTP response.
	 *
	 * @param array $request WP HTTP response array.
	 * @return array
	 */
	private function normalize_response( $request ) {
		$code    = (int) wp_remote_retrieve_response_code( $request );
		$body    = (string) wp_remote_retrieve_body( $request );
		$decoded = null;

		if ( $body !== '' ) {
			$json = json_decode( $body, true );
			if ( is_array( $json ) ) {
				$decoded = $json;
			}
		}

		return array(
			'code' => $code,
			'body' => $body,
			'json' => $decoded,
		);
	}

	/**
	 * Return the base API URL.
	 *
	 * Uses SKYDROPX_ECOMMERCE_URL defined by the bootstrap and appends '/api'.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_base_url() {
		return rtrim( (string) SKYDROPX_ECOMMERCE_URL, '/' ) . '/api';
	}
}
