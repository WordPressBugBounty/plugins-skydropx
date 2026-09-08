<?php
/**
 * Request guard for authentication and authorization.
 *
 * @package   Skydropx\Routes\Utils
 * @since     1.0.0
 */

namespace Skydropx\Routes\Utils;

use Skydropx\Includes\Skydropx_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authentication guard for WC API endpoints.
 *
 * Validates Basic Auth credentials against Woocommerce API keys stored
 * in the database.
 *
 * @package Skydropx\Routes\Utils
 * @since   1.0.0
 */
class Skydropx_Request_Guard {

	/**
	 * Repository for API key lookups.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Repository
	 */
	private $repository;

	/**
	 * JSON responder for error responses.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Json_Responder
	 */
	private $responder;

	/**
	 * Initialize guard with dependencies.
	 *
	 * @since 1.0.0
	 * @param Skydropx_Repository     $repository Repository for API key validation.
	 * @param Skydropx_Json_Responder $responder  Responder for error output.
	 */
	public function __construct(
		Skydropx_Repository $repository,
		Skydropx_Json_Responder $responder
	) {
		$this->repository = $repository;
		$this->responder  = $responder;
	}

	/**
	 * Require authentication for the current request.
	 *
	 * Validates Basic Auth credentials and exits with 401 if invalid.
	 *
	 * @since 1.0.0
	 * @param array $allowed_permissions Permissions that grant access (default: ['read_write']).
	 * @return void
	 */
	public function require_auth( array $allowed_permissions = array( 'read_write' ) ): void {
		if ( ! $this->is_authorized( $allowed_permissions ) ) {
			$this->responder->error(
				'unauthorized',
				'Invalid or missing authentication credentials.',
				401
			);
		}
	}

	/**
	 * Check if current request is authorized.
	 *
	 * @since 1.0.0
	 * @param array $allowed_permissions Permissions that grant access.
	 * @return bool
	 */
	public function is_authorized( array $allowed_permissions = array( 'read_write' ) ): bool {
		$header = $this->get_authorization_header();

		if ( empty( $header ) ) {
			return false;
		}

		if ( 0 !== stripos( $header, 'Basic ' ) ) {
			return false;
		}

		$encoded = trim( substr( $header, 6 ) );
		$decoded = base64_decode( $encoded, true );

		if ( false === $decoded || strpos( $decoded, ':' ) === false ) {
			return false;
		}

		list( $consumer_key, $consumer_secret ) = array_map( 'trim', explode( ':', $decoded, 2 ) );

		if ( '' === $consumer_key || '' === $consumer_secret ) {
			return false;
		}

		$hashed_key = function_exists( 'wc_api_hash' ) ? wc_api_hash( $consumer_key ) : '';

		if ( '' === $hashed_key ) {
			return false;
		}

		$row = $this->repository->get_api_key_by_hashed_key( $hashed_key, $allowed_permissions );

		if ( empty( $row ) ) {
			return false;
		}

		$stored_secret = isset( $row['consumer_secret'] ) ? $row['consumer_secret'] : '';
		$permissions   = isset( $row['permissions'] ) ? $row['permissions'] : '';

		if ( '' === $stored_secret || '' === $permissions ) {
			return false;
		}

		if ( ! hash_equals( (string) $stored_secret, (string) $consumer_secret ) ) {
			return false;
		}

		if ( ! in_array( $permissions, $allowed_permissions, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get Authorization header with robust fallbacks.
	 *
	 * Handles different server environments Apache, Nginx, FastCGI, proxies
	 * by checking multiple sources for the Authorization header.
	 *
	 * Sources checked in order:
	 * 1. getallheaders() with case insensitive lookup
	 * 2. $_SERVER['HTTP_AUTHORIZATION']
	 * 3. $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
	 *
	 * @since 1.0.0
	 * @return string|null Authorization header value or null if not found.
	 */
	private function get_authorization_header(): ?string {
		$header = null;

		// 1. Try getallheaders() with case insensitive key lookup.
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();

			if ( is_array( $headers ) ) {
				// Normalize keys to lowercase for case insensitive matching.
				$headers_lower = array_change_key_case( $headers, CASE_LOWER );

				if ( isset( $headers_lower['authorization'] ) ) {
					$header = $headers_lower['authorization'];
				}
			}
		}

		// 2. Fallback to $_SERVER['HTTP_AUTHORIZATION'] standard CGI FastCGI
		if ( empty( $header ) && ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		// 3. Fallback to $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
		if ( empty( $header ) && ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		return is_string( $header ) && '' !== $header ? $header : null;
	}
}
