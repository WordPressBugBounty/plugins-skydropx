<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://pro.skydropx.com/es-MX/merchant_stores
 * @since             1.0.0
 * @package Skydropx
 *
 * @wordpress-plugin
 * Plugin Name:       Skydropx
 * Requires Plugins:  woocommerce
 * Plugin URI:        https://pro.skydropx.com/es-MX/merchant_stores
 * Description:       Despreocúpate de toda la logística de envíos de tu negocio con una sola herramienta. Cotiza entre más de 30 paqueterías y comienza a enviar desde México y Colombia, hoy.
 * Version:           2.1.4
 * Requires at least: 5.4
 * Requires PHP:      7.0
 * Author:            Skydropx
 * Author URI:        https://pro.skydropx.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       skydropx
 * Domain Path:       /languages
 */

require_once plugin_dir_path( __FILE__ ) . 'includes/skydropx-autoloader.php';

use Skydropx\Api\Skydropx_Api;
use Skydropx\Includes\Skydropx_Repository;
use Skydropx\Includes\Skydropx_Service;

if ( ! defined( 'WPINC' ) ) {
	die;
}
defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'SKYDROPX_VERSION', '2.1.4' );
define( 'SKYDROPX_ECOMMERCE_URL', 'https://ecommerce.pro.skydropx.com' );
define( 'SKYDROPX_APP_URL', 'https://pro.skydropx.com' );
define( 'SKYDROPX_SHIPPING_METHOD_ID', 'skydropx' );
define( 'SKYDROPX_SHIPPING_ZONE_NAME', 'Skydropx' );
define( 'SKYDROPX_PLUGIN_BASE', plugin_basename( __FILE__ ) );
define( 'SKYDROPX_PATH', plugin_dir_path( __FILE__ ) );
define( 'SKYDROPX_URL', plugin_dir_url( __FILE__ ) );


/**
 * Runs on plugin activation.
 *
 * Validates environment requirements and delegates activation tasks to
 * {@see Skydropx_Activator::activate()}.
 *
 * @since 1.0.0
 * @return void
 */
function skydropx_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-skydropx-activator.php';

	$activator = new Skydropx_Activator();
	$activator->activate();
}

/**
 * Runs on plugin deactivation.
 *
 * Builds the service layer and delegates deactivation tasks to
 * {@see Skydropx_Deactivator::deactivate()}.
 *
 * @since 1.0.0
 * @return void
 */
function skydropx_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-skydropx-deactivator.php';

	$service     = new Skydropx_Service(
		new Skydropx_Repository(),
		new Skydropx_Api()
	);
	$deactivator = new Skydropx_Deactivator( $service );
	$deactivator->deactivate();
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'skydropx_activate' );
register_deactivation_hook( __FILE__, 'skydropx_deactivate' );

/**
 * Initializes the plugin.
 *
 * Loads the main plugin class if necessary and triggers its execution to register
 * all hooks and integrations.
 *
 * @since 1.0.0
 * @return void
 */
function skydropx_initializer() {
	if ( ! class_exists( 'Skydropx' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-skydropx.php';
	}

	$plugin = new Skydropx();
	$plugin->run();
}


skydropx_initializer();
