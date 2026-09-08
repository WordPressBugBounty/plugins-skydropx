<?php
/**
 * Controller for Woocommerce legacy API endpoints.
 *
 * @package   Skydropx\Controllers
 * @since     1.0.0
 */

namespace Skydropx\Controllers;

use Skydropx\Includes\Skydropx_Service;
use Skydropx\Includes\Skydropx_Quotation_Settings;
use Skydropx\Includes\Skydropx_Shipping_Zone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Business logic controller for Woocommerce API endpoints.
 *
 * Handles quotation toggle, configs, status and uninstall operations.
 * Keeps business logic separated from routing and auth concerns.
 *
 * @package Skydropx\Controllers
 * @since   1.0.0
 */
class Skydropx_Wc_Api_Endpoints_Controller {

	/**
	 * Domain service for uninstall and cleanup flows.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Service
	 */
	private $service_manager;

	/**
	 * Initialize controller with dependencies.
	 *
	 * @since 1.0.0
	 * @param Skydropx_Service $service_manager Service for plugin lifecycle operations.
	 */
	public function __construct( Skydropx_Service $service_manager ) {
		$this->service_manager = $service_manager;
	}

	/**
	 * Handle quotation toggle request.
	 *
	 * Enables or disables quotation feature and optionally updates the quotation base URL.
	 * Creates shipping zone when quotation is enabled.
	 *
	 * Expected JSON body:
	 * - status (bool, required): Enable or disable quotations.
	 * - quotation_base_url (string|null, optional): Override base URL for rate requests.
	 *   Send empty string or null to clear the stored override.
	 *
	 * @since 1.0.0
	 * @param array $data Parsed JSON request body.
	 * @return array Response with 'success', 'status_code', and 'data' keys.
	 */
	public function handle_quotation_toggle( array $data ): array {
		$status = isset( $data['status'] ) ? filter_var( $data['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;

		if ( is_null( $status ) ) {
			return array(
				'success'     => false,
				'status_code' => 400,
				'data'        => array(
					'error'   => 'missing_required_field',
					'message' => 'The "status" field is required and must be a boolean.',
				),
			);
		}

		Skydropx_Quotation_Settings::set_enabled( (bool) $status );
		$shipping_zone = new Skydropx_Shipping_Zone();
		$shipping_zone->create();

		// Optional: update quotation base URL
		if ( array_key_exists( 'quotation_base_url', $data ) ) {
			$base_url_result = Skydropx_Quotation_Settings::apply_base_url_from_payload( $data['quotation_base_url'] );
			if ( ! $base_url_result['success'] ) {
				$error   = 'invalid_quotation_base_url';
				$message = 'The provided quotation_base_url is not a valid HTTPS URL.';

				if ( 'invalid_type' === $base_url_result['error'] ) {
					$error   = 'invalid_quotation_base_url_type';
					$message = 'The "quotation_base_url" field must be a string, null, or omitted.';
				}

				return array(
					'success'     => false,
					'status_code' => 422,
					'data'        => array(
						'error'   => $error,
						'message' => $message,
					),
				);
			}
		}

		$quotation_base_url = Skydropx_Quotation_Settings::get_base_url();

		return array(
			'success'     => true,
			'status_code' => 200,
			'data'        => array(
				'quotation_status'   => $status,
				'quotation_base_url' => $quotation_base_url ?? '',
			),
		);
	}

	/**
	 * Handle set configs request (no-op).
	 *
	 * Retained for backwards compatibility with the external platform. The
	 * request payload is ignored; this handler simply acknowledges the call.
	 *
	 * @since 1.0.0
	 * @return array Response with 'success' and 'status_code' keys.
	 */
	public function handle_set_configs(): array {
		return array(
			'success'     => true,
			'status_code' => 200,
			'data'        => array(),
		);
	}

	/**
	 * Handle quotation status request.
	 *
	 * Returns current quotation configuration and plugin version.
	 *
	 * @since 1.0.0
	 * @return array Response with 'success', 'status_code', and 'data' keys.
	 */
	public function handle_quotation_status(): array {
		$plugin_version = defined( 'SKYDROPX_VERSION' ) ? SKYDROPX_VERSION : '';
		$status         = Skydropx_Quotation_Settings::get_enabled();

		return array(
			'success'     => true,
			'status_code' => 200,
			'data'        => array(
				'quotation_status'    => $status,
				'plugin_version'      => $plugin_version,
				'quotation_base_url'  => Skydropx_Quotation_Settings::get_base_url(),
				'permalink_structure' => get_option( 'permalink_structure' ),
			),
		);
	}

	/**
	 * Handle uninstall request.
	 *
	 * Triggers local cleanup and plugin deactivation.
	 *
	 * @since 1.0.0
	 * @return array Response with 'success', 'status_code', and 'data' keys.
	 */
	public function handle_uninstall(): array {
		try {
			$this->service_manager->deactivate_from_v3();

			return array(
				'success'     => true,
				'status_code' => 200,
				'data'        => array(),
			);
		} catch ( \Throwable $th ) {
			return array(
				'success'     => false,
				'status_code' => 500,
				'data'        => array(
					'error'   => 'uninstall_failed',
					'message' => 'An error occurred during uninstall.',
				),
			);
		}
	}
}
