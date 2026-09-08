<?php
/**
 * Internationalization class for the plugin.
 *
 * @package   Skydropx\Includes
 * @since     1.0.0
 */

namespace Skydropx\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Internationalization class for the plugin.
 *
 * @package   Skydropx\Includes
 * @since     1.0.0
 */
class Skydropx_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'skydropx',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
