<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://pro.skydropx.com/es-MX/merchant_stores
 * @since      1.0.0
 *
 * @package    Skydropx
 * @subpackage Skydropx/includes
 */

defined( 'ABSPATH' ) || exit;

use Skydropx\Admin\Skydropx_Admin;
use Skydropx\Admin\Skydropx_Admin_Notices;
use Skydropx\Api\Skydropx_Api;
use Skydropx\Helper\Helper;
use Skydropx\Includes\Skydropx_i18n;
use Skydropx\Includes\Skydropx_Repository;
use Skydropx\Includes\Skydropx_Service;
use Skydropx\Routes\Skydropx_Routes;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Skydropx
 * @subpackage Skydropx/includes
 * @author     Skydropx <hola@skydropx.com>
 */
class Skydropx {


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Repository of persistent plugin data and configuration.
	 *
	 * Exposes accessors to read and write options required by the plugin.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Repository
	 */
	protected $repository;

	/**
	 * Admin notices manager.
	 *
	 * Handles collection and rendering of admin-facing messages.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Admin_Notices
	 */
	protected $admin_notices;

	/**
	 * API client used to communicate with remote services.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Api
	 */
	protected $api;

	/**
	 * Domain service orchestrating business logic between repository and API.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Service
	 */
	protected $service;

	/**
	 * Router that registers and handles custom endpoints and actions.
	 *
	 * @since 1.0.0
	 * @var Skydropx_Routes
	 */
	protected $router;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Construct the core plugin and bootstrap its subsystems.
	 *
	 * Sets plugin name and version, loads dependencies, configures localization,
	 * and registers admin/public hooks so the plugin is ready to run.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->version     = SKYDROPX_VERSION;
		$this->plugin_name = 'Skydropx';
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}


	/**
	 * Register the plugin shipping method with WooCommerce.
	 *
	 * Callback for the 'woocommerce_shipping_methods' filter. Adds the
	 * plugin shipping method class to WooCommerce so it becomes available
	 * during checkout and in shipping settings.
	 *
	 * @since 1.0.0
	 * @param array $shipping_methods Map of shipping method IDs to class names.
	 * @return array Updated map including the plugin shipping method.
	 */
	public function add_shipping_method( $shipping_methods ) {
		// Indicates which class needs to be loaded to check for quotation.
		$shipping_methods[ SKYDROPX_SHIPPING_METHOD_ID ] = Skydropx_Shipping_Method::class;
		return $shipping_methods;
	}

	/**
	 * Boot the plugin by wiring filters/actions and running the loader.
	 *
	 * Attaches the shipping method filter and delegates execution to the
	 * loader so all registered hooks are applied.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {

		// --- Shipment Method
		$this->loader->add_filter( 'woocommerce_shipping_methods', $this, 'add_shipping_method' );
		Helper::log_info( 'Skydropx filter added' );

		Helper::log_info( 'Skydropx loader running...' );
		$this->loader->run();

		Helper::log_info( 'Skydropx loader finished' );
	}


	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since 1.0.0
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}


	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since 1.0.0
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the i18n component to set the text domain and register the
	 * corresponding WordPress hook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_locale() {

		$plugin_i18n = new Skydropx_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Includes and initializes the classes that compose the plugin:
	 * - Loader: orchestrates actions and filters.
	 * - i18n: defines internationalization functionality.
	 * - Admin: defines hooks for the admin area.
	 * - Service, Repository, Api: core business logic and data access.
	 * - Routes: registers custom endpoints.
	 *
	 * Also hooks WooCommerce's shipping method initialization.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-skydropx-loader.php';

		// Hook to WooCommerce's shipping method initialization.
		add_action(
			'woocommerce_shipping_init',
			function () {
				require_once plugin_dir_path( __DIR__ ) . 'includes/class-skydropx-shipping-method.php';
			}
		);

		$this->loader     = new Skydropx_Loader();
		$this->repository = new Skydropx_Repository();
		$this->api        = new Skydropx_Api();
		$this->service    = new Skydropx_Service( $this->repository, $this->api );
		$this->router     = new Skydropx_Routes( $this->service, $this->repository );
	}

	/**
	 * Define the hooks for the public-facing side of the site.
	 *
	 * Currently loads legacy routes and logs initialization. Once the
	 * new routes are fully implemented, this method should be updated
	 * accordingly.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function define_public_hooks() {

		$this->load_routes();
		Helper::log_info(
			// Translators: message to indicate that the public hooks/endpoints are defined.
			__( 'Skydropx public hooks defined', 'skydropx' )
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * Enqueues admin assets, adds the settings/admin menu and registers
	 * validation and notice rendering callbacks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Skydropx_Admin(
			$this->get_plugin_name(),
			$this->get_version(),
			$this->repository,
			$this->service
		);

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'admin_menu' );
		$this->loader->add_action( 'admin_notices', Skydropx_Admin_Notices::class, 'output_notices' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'validate_necessary_settings' );
	}

	/**
	 * Register custom WooCommerce API routes handled by the router.
	 *
	 * Hooks router callbacks to legacy 'woocommerce_api_*' actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_routes() {
		$this->loader->add_action( 'woocommerce_api_skydropx-quotation-toggle', $this->router, 'quotation_toggle' );
		$this->loader->add_action( 'woocommerce_api_skydropx-quotation-status', $this->router, 'quotation_status' );
		$this->loader->add_action( 'woocommerce_api_skydropx-configs', $this->router, 'set_configs' );
		$this->loader->add_action( 'woocommerce_api_skydropx-uninstall', $this->router, 'uninstall_app' );
	}
}
