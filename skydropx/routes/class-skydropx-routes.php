<?php
/**
 * Routes adapter for Woocommerce API endpoints.
 *
 * @package   Skydropx\Routes
 * @since     1.0.0
 */

namespace Skydropx\Routes;

use Skydropx\Includes\Skydropx_Service;
use Skydropx\Includes\Skydropx_Repository;
use Skydropx\Controllers\Skydropx_Wc_Api_Endpoints_Controller;
use Skydropx\Routes\Utils\Skydropx_Request_Guard;
use Skydropx\Routes\Utils\Skydropx_Json_Body_Parser;
use Skydropx\Routes\Utils\Skydropx_Json_Responder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes adapter for Woocommerce API endpoints.
 *
 * Endpoints:
 * - /wc-api/skydropx-quotation-toggle → quotation_toggle()
 * - /wc-api/skydropx-configs → set_configs()
 * - /wc-api/skydropx-quotation-status → quotation_status()
 * - /wc-api/skydropx-uninstall → uninstall_app()
 *
 * @package Skydropx\Routes
 * @since   1.0.0
 */
class Skydropx_Routes {

	/**
	 * Endpoints controller with business logic.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Wc_Api_Endpoints_Controller
	 */
	private $controller;

	/**
	 * Request guard for authentication.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Request_Guard
	 */
	private $guard;

	/**
	 * JSON body parser.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Json_Body_Parser
	 */
	private $parser;

	/**
	 * JSON responder.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Json_Responder
	 */
	private $responder;

	/**
	 * @since 1.0.0
	 * @param Skydropx_Service    $service_manager Service for plugin operations.
	 * @param Skydropx_Repository $repository      Repository for data access.
	 */
	public function __construct(
		Skydropx_Service $service_manager,
		Skydropx_Repository $repository
	) {
		$this->responder  = new Skydropx_Json_Responder();
		$this->parser     = new Skydropx_Json_Body_Parser();
		$this->guard      = new Skydropx_Request_Guard( $repository, $this->responder );
		$this->controller = new Skydropx_Wc_Api_Endpoints_Controller( $service_manager );
	}

	/**
	 * Handle quotation toggle endpoint.
	 *
	 * Endpoint: POST /wc-api/skydropx-quotation-toggle
	 *
	 * Enables or disables quotation feature and optionally updates quotation URL override.
	 *
	 * - status (bool, required): Enable or disable quotations.
	 * - quotation_base_url (string|null, optional): Override full quotation URL for rate requests.
	 *   When present, this URL is used as-is. Send empty string or null to clear.
	 *
	 * Response (200):
	 * - success: true
	 * - data.quotation_status: Current status after update.
	 * - data.quotation_base_url: Current quotation URL override.
	 *
	 * Errors:
	 * - 400: Missing or invalid request body or required fields.
	 * - 401: Unauthorized.
	 * - 422: Invalid quotation_base_url type or value.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function quotation_toggle(): void {
		$this->handle_with_json_body(
			array( 'read_write' ),
			function ( array $body ): array {
				return $this->controller->handle_quotation_toggle( $body );
			}
		);
	}

	/**
	 * Handle set configs endpoint (no-op).
	 *
	 * Endpoint: POST /wc-api/skydropx-configs
	 *
	 * Retained for backwards compatibility with the external platform. The
	 * request body is ignored and the endpoint simply acknowledges the call.
	 *
	 * Response (200):
	 * - success: true
	 *
	 * Errors:
	 * - 401: Unauthorized.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_configs(): void {
		$this->handle_without_body(
			array( 'read_write' ),
			function (): array {
				return $this->controller->handle_set_configs();
			}
		);
	}

	/**
	 * Handle quotation status endpoint.
	 *
	 * Endpoint: GET /wc-api/skydropx-quotation-status
	 *
	 * Returns current quotation configuration and plugin version.
	 *
	 * Response (200):
	 * - success: true
	 * - data.status: Quotation enabled status.
	 * - data.plugin_version: Current plugin version.
	 *
	 * Errors:
	 * - 401: Unauthorized.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function quotation_status(): void {
		$this->handle_without_body(
			array( 'read_write' ),
			function (): array {
				return $this->controller->handle_quotation_status();
			}
		);
	}

	/**
	 * Handle uninstall endpoint.
	 *
	 * Endpoint: POST /wc-api/skydropx-uninstall
	 *
	 * Triggers local cleanup and plugin deactivation.
	 *
	 * Response (200):
	 * - success: true
	 *
	 * Errors:
	 * - 401: Unauthorized.
	 * - 500: Uninstall operation failed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function uninstall_app(): void {
		$this->handle_without_body(
			array( 'read_write' ),
			function (): array {
				return $this->controller->handle_uninstall();
			}
		);
	}

	/**
	 * Handle request that requires JSON body.
	 *
	 * Executes the standard pipeline: auth → parse JSON → execute → respond.
	 *
	 * @since 1.0.0
	 * @param array    $permissions Required permissions for auth.
	 * @param callable $handler     Handler receiving parsed body, returns result array.
	 * @return void
	 */
	private function handle_with_json_body( array $permissions, callable $handler ): void {
		$parsed = $this->parser->parse();

		if ( ! $parsed['success'] ) {
			$this->responder->error(
				$parsed['error'] ?? 'invalid_request_body',
				'The request body must be valid JSON.',
				400
			);
		}

		$result = $handler( $parsed['data'] );
		$this->responder->from_result( $result );
	}

	/**
	 * Handle request without body requirement.
	 *
	 * Executes the standard pipeline: auth → execute → respond.
	 *
	 * @since 1.0.0
	 * @param array    $permissions Required permissions for auth.
	 * @param callable $handler     Handler returning result array.
	 * @return void
	 */
	private function handle_without_body( array $permissions, callable $handler ): void {
		$result = $handler();
		$this->responder->from_result( $result );
	}
}
