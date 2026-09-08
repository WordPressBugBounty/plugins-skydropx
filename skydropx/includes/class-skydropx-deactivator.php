<?php
/**
 * Fired during plugin deactivation.
 *
 * Defines code necessary to run on plugin deactivation: remote uninstall
 * request, local cleanup, and logging.
 *
 * @package   Skydropx
 * @subpackage Skydropx/includes
 * @since     1.0.0
 */

defined( 'ABSPATH' ) || exit;


use Skydropx\Helper\Helper;
use Skydropx\Includes\Skydropx_Service;


/**
 * Deactivation handler class.
 */
class Skydropx_Deactivator {

	/**
	 * Domain service used to remove remote data and clean local state.
	 *
	 * @var Skydropx_Service
	 */
	private $service;

	/**
	 * Inject dependencies.
	 *
	 * @since 1.0.0
	 * @param Skydropx_Service $service Service instance.
	 */
	public function __construct( Skydropx_Service $service ) {
		$this->service = $service;
	}

	/**
	 * Deactivate the plugin and clean remote/local state.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate() {
		try {
			Helper::log_info(
				// Translators: Deactivating plugin...
				__( 'Desactivando plugin...', 'skydropx' )
			);

			// Send uninstall request to external service to clean remote state.
			$res = $this->service->remove_from_ecommerce_service();

			if ( $res && ! isset( $res['errors'] ) ) {
				$this->service->remove_plugin_from_site();
				Helper::log_info(
					// Translators: Plugin deactivated successfully.
					__( 'Plugin desactivado correctamente.', 'skydropx' )
				);
			} else {
				// Translators: %s is the response indicating that WooCommerce uninstallation failed.
				Helper::log_error( sprintf( __( 'WC uninstallation failed... %s', 'skydropx' ), wp_json_encode( $res, JSON_PRETTY_PRINT ) ) );
			}
		} catch ( \Throwable $th ) {
			$message = esc_html( $th->getMessage() );
			// Translators: %s is the error message encountered during plugin deactivation.
			Helper::log_error( sprintf( __( 'Error deactivating plugin: %s', 'skydropx' ), $message ) );
		}
	}
}
