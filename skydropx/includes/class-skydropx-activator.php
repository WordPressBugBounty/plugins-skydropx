<?php
/**
 * Plugin activator file.
 *
 * Handles plugin activation logic including environment validation
 * and system requirements verification.
 *
 * @package   Skydropx
 * @subpackage Skydropx/includes
 * @since     1.0.0
 */

defined( 'ABSPATH' ) || exit;

use Skydropx\Helper\Helper;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation,
 * including system requirements validation and initial setup.
 *
 * @since      1.0.0
 * @package    Skydropx
 * @subpackage Skydropx/includes
 * @author     Skydropx <hola@skydropx.com>
 */
class Skydropx_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Performs system requirements validation and handles activation process.
	 *
	 * @link https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
	 * @link https://developer.wordpress.org/reference/functions/register_activation_hook/
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activate() {
		try {
			$this->check_system_requirements();
			$this->set_permalink_structure();

			Helper::log_info( 'Plugin activated successfully: ' . SKYDROPX_PLUGIN_BASE );
		} catch ( \Throwable $th ) {
			$message = esc_html( $th->getMessage() );
			// translators: %s refers to the error message during plugin activation.
			Helper::log_error( sprintf( __( 'Error activating plugin: %s', 'skydropx' ), $message ) );

			// Re-throw to prevent activation
			throw $th;
		}
	}

	/**
	 * Set WordPress permalink structure for the current site to "/%postname%/" and flush rewrite rules.
	 *
	 * References:
	 * - Permalinks are global URLs that impact site content addressing:
	 *
	 *   @link https://wordpress.org/documentation/article/customize-permalinks/
	 * - Flushing rewrite rules rebuilds rewrite rules and is an expensive operation:
	 *   @link https://developer.wordpress.org/reference/functions/flush_rewrite_rules/
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_permalink_structure() {
		$current = get_option( 'permalink_structure', '' );

		// Accept both, canonical and missing trailing slash variants.
		if ( '/%postname%/' === $current || '/%postname%' === $current ) {
			return;
		}

		// Store previous value for debugging -> rollback purposes.
		update_option( 'skydropx_previous_permalink_structure', $current );
		update_option( 'permalink_structure', '/%postname%/' );

		// Mark one time admin notice for the next admin request.
		update_option( 'skydropx_permalinks_auto_updated', 1 );
		flush_rewrite_rules();
	}

	/**
	 * Verifies minimum system requirements.
	 *
	 * Checks the running versions of PHP, WordPress, and WooCommerce. If a requirement
	 * is not met, the plugin is deactivated and execution stops with an admin message.
	 *
	 * @since 1.0.0
	 * @global string $wp_version WordPress version.
	 * @return void
	 * @throws \Exception When system requirements are not met.
	 */
	private function check_system_requirements() {
		global $wp_version;

		// Perform version checks.
		if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
			$flag     = 'PHP';
			$required = '7.0';
			$current  = PHP_VERSION;
		} elseif ( version_compare( $wp_version, '5.4', '<' ) ) {
			$flag     = 'WordPress';
			$required = '5.4';
			$current  = $wp_version;
		} elseif ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '4.3', '<' ) ) {
			$flag     = 'WooCommerce';
			$required = '4.3';
			$current  = defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A';
		}

		// Handle failure if requirements are not met.
		if ( isset( $flag ) ) {
			deactivate_plugins( SKYDROPX_PLUGIN_BASE );
			wp_die(
				sprintf(
					// Translators: %1$s is the plugin name, %2$s is the component name, %3$s is the required version, %4$s is the current version.
					esc_html__( '%1$s requires at least %2$s version %3$s. Current version: %4$s.', 'skydropx' ),
					'Skydropx',
					esc_html( $flag ),
					esc_html( $required ),
					esc_html( $current )
				),
				esc_html__( 'Plugin Activation Error', 'skydropx' ),
				array( 'back_link' => true )
			);
		}
	}
}
