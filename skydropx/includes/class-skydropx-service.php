<?php
/**
 * Service layer for domain operations.
 *
 * Bridges the plugin with the external platform (onboarding URL, uninstall)
 * and encapsulates local cleanup logic (options, webhooks, shipping zone).
 *
 * @package   Skydropx
 * @since     1.0.0
 */

namespace Skydropx\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Skydropx\Api\Skydropx_Api;
use Skydropx\Helper\Helper;


/**
 * Domain service: external platform integration and local cleanup.
 */
class Skydropx_Service {

	/**
	 * Repository for plugin data operations.
	 *
	 * @var Skydropx_Repository
	 */
	private $repository;
	/**
	 * API client for external platform interactions.
	 *
	 * @var Skydropx_Api
	 */
	private $api;

	/**
	 * Initialize the service layer with its dependencies.
	 *
	 * @since 1.0.0
	 * @param Skydropx_Repository $repository Repository instance.
	 * @param Skydropx_Api        $api        API client instance.
	 */
	public function __construct( Skydropx_Repository $repository, Skydropx_Api $api ) {
		$this->repository = $repository;
		$this->api        = $api;
	}


	/**
	 * Generate the onboarding URL for connecting the shop in the external service.
	 *
	 * @since 1.0.0
	 * @param string $consumer_key    WooCommerce consumer key.
	 * @param string $consumer_secret WooCommerce consumer secret.
	 */
	public function generate_shop_creation_url( $consumer_key, $consumer_secret ) {
		$ecommerce_url = SKYDROPX_ECOMMERCE_URL;
		$domain        = Helper::get_store_identifier();

		return add_query_arg(
			array(
				'client_id'     => $consumer_key,
				'client_secret' => $consumer_secret,
				'domain'        => $domain,
				'scope'         => 'read_write',
			),
			$ecommerce_url . '/install/woocommerce'
		);
	}


	/**
	 * Request remote uninstall on the external platform for this site.
	 *
	 * Sends a POST request to the uninstall endpoint with the store domain.
	 *
	 * @since 1.0.0
	 * @return array|false API response array or false on transport error.
	 */
	public function remove_from_ecommerce_service() {
		$endpoint = '/v3/shop/webhook/mx/woocommerce/uninstall_app';
		$domain   = Helper::get_store_identifier();

		$res = $this->api->post(
			$endpoint,
			array(
				'domain' => $domain,
			)
		);

		// Translators: %s is the response from the API call to uninstall WooCommerce.
		Helper::log_info( sprintf( __( 'Request to V3: %s', 'skydropx' ), wp_json_encode( $res, JSON_PRETTY_PRINT ) ) );

		return $res;
	}


	/**
	 * Remove plugin data/configuration from this site.
	 *
	 * Deletes API keys and options and removes shipping zone and webhooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function remove_plugin_from_site() {
		$this->repository->delete_api_keys();
		$this->repository->clean_webhooks();

		// Delete global options.
		Skydropx_Quotation_Settings::clear_enabled();
		Skydropx_Quotation_Settings::clear_base_url();
		Skydropx_Shipping_Zone::remove();

		Helper::log_info( 'Skydropx removed from site' );
	}


	/**
	 * Uninstall plugin locally without calling WP deactivation hooks.
	 *
	 * Performs local cleanup and deactivates the plugin programmatically.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate_from_v3() {
		$this->remove_plugin_from_site();
		deactivate_plugins( SKYDROPX_PLUGIN_BASE, true );

		// Translators: message when the plugin is uninstalled locally.
		Helper::log_info( __( 'Plugin uninstalled!', 'skydropx' ) );
	}
}
