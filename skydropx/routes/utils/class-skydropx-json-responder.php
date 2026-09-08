<?php
/**
 * JSON responder for HTTP responses.
 *
 * Standardizes JSON response format and HTTP status codes across all endpoints.
 *
 * @package   Skydropx\Routes\Utils
 * @since     1.0.0
 */

namespace Skydropx\Routes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends standardized JSON responses.
 *
 * Response format:
 * - Success: { "success": true, "data": {...} }
 * - Error:   { "success": false, "error": "code", "message": "..." }
 *
 * @package Skydropx\Routes\Utils
 * @since   1.0.0
 */
class Skydropx_Json_Responder {

	/**
	 * Send a success response.
	 *
	 * @since 1.0.0
	 * @param array $data        Response payload.
	 * @param int   $status_code HTTP status code.
	 * @return void
	 */
	public function success( array $data = array(), int $status_code = 200 ): void {
		$response = array(
			'success' => true,
		);

		if ( ! empty( $data ) ) {
			$response['data'] = $data;
		}

		$this->send( $response, $status_code );
	}

	/**
	 * Send an error response.
	 *
	 * @since 1.0.0
	 * @param string $error       Error code identifier.
	 * @param string $message     Readable error message.
	 * @param int    $status_code HTTP status code.
	 * @param array  $extra       Additional error details.
	 * @return void
	 */
	public function error( string $error, string $message, int $status_code, array $extra = array() ): void {
		$response = array(
			'success' => false,
			'error'   => $error,
			'message' => $message,
		);

		if ( ! empty( $extra ) ) {
			$response = array_merge( $response, $extra );
		}

		$this->send( $response, $status_code );
	}

	/**
	 * Send a controller result as response.
	 *
	 * Handles the standardized return format from controller methods:
	 * { success: bool, status_code: int, data: array }
	 *
	 * @since 1.0.0
	 * @param array $result Controller result array.
	 * @return void
	 */
	public function from_result( array $result ): void {
		$success     = isset( $result['success'] ) ? (bool) $result['success'] : false;
		$status_code = isset( $result['status_code'] ) ? (int) $result['status_code'] : 200;
		$data        = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();

		if ( $success ) {
			$this->success( $data, $status_code );
		} else {
			$error   = isset( $data['error'] ) ? (string) $data['error'] : 'error';
			$message = isset( $data['message'] ) ? (string) $data['message'] : '';
			unset( $data['error'], $data['message'] );
			$this->error( $error, $message, $status_code, $data );
		}
	}

	/**
	 * Send JSON response with proper headers and exit.
	 *
	 * @since 1.0.0
	 * @param array $data        Response payload.
	 * @param int   $status_code HTTP status code.
	 * @return void
	 */
	private function send( array $data, int $status_code ): void {
		if ( ! headers_sent() ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( $status_code );
			}
			header( 'Content-Type: application/json; charset=utf-8' );
		}

		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}
}
