<?php
/**
 * JSON body parser for HTTP requests.
 *
 * Handles reading and decoding JSON from the request body with proper
 * error handling and sanitization.
 *
 * @package   Skydropx\Routes\Utils
 * @since     1.0.0
 */

namespace Skydropx\Routes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses JSON request bodies.
 *
 * @package Skydropx\Routes\Utils
 * @since   1.0.0
 */
class Skydropx_Json_Body_Parser {

	/**
	 * Read and decode JSON body from current request.
	 *
	 * @since 1.0.0
	 * @return array{success: bool, data: array|null, error: string|null}
	 */
	public function parse(): array {
		$raw = file_get_contents( 'php://input' );

		if ( false === $raw || '' === $raw ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => 'empty_body',
			);
		}

		$raw     = wp_unslash( $raw );
		$decoded = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => 'invalid_json',
			);
		}

		if ( ! is_array( $decoded ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => 'invalid_json_structure',
			);
		}

		return array(
			'success' => true,
			'data'    => $decoded,
			'error'   => null,
		);
	}
}
