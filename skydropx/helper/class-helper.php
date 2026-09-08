<?php
/**
 * Helper utility facade for the plugin.
 *
 * Exposes logging helpers and WooCommerce related utilities by composing
 *
 * @package  Skydropx\Helper
 * @since    1.0.0
 */

namespace Skydropx\Helper;

/**
 * Facade class that aggregates logging and WooCommerce helpers.
 *
 * @package  Skydropx\Helper
 * @since    1.0.0
 */
class Helper {
	use WooCommerce_Trait;
	use Logger_Trait;

	/**
	 * Build a versioned asset URL using plugin base constants.
	 *
	 * - Adds a cache busting query param "ver" based on file modification time.
	 * - Falls back to plugin version when the file path is not readable.
	 *
	 * @param string $relative_path Relative path from plugin root (e.g. 'assets/images/skydropx-logo.png').
	 * @return string Versioned URL for the asset.
	 */
	public static function asset_url( string $relative_path ): string {
		$relative_path = ltrim( $relative_path, '/' );
		$base_path     = defined( 'SKYDROPX_PATH' ) ? SKYDROPX_PATH : plugin_dir_path( dirname( __DIR__ ) );
		$base_url      = defined( 'SKYDROPX_URL' ) ? SKYDROPX_URL : plugin_dir_url( dirname( __DIR__ ) );

		$file_path = $base_path . $relative_path;
		$version   = defined( 'SKYDROPX_VERSION' ) ? SKYDROPX_VERSION : (string) time();
		if ( file_exists( $file_path ) ) {
			$mtime = @filemtime( $file_path );
			if ( false !== $mtime ) {
				$version = (string) $mtime;
			}
		}

		return add_query_arg( 'ver', $version, $base_url . $relative_path );
	}

	/**
	 * Get a normalized store identifier for external services.
	 *
	 * Builds an identifier from the public site URL (home_url), preserving subdomains.
	 *
	 * @see https://developer.wordpress.org/reference/functions/home_url/
	 *
	 * Example outputs:
	 * - shop.example.com
	 * - example.com:8080
	 * - example.com/subsite
	 * - shop.example.com/app
	 *
	 * @since 1.0.0
	 * @return string Host with optional port and path
	 */
	public static function get_store_identifier(): string {
		$url_parts  = wp_parse_url( home_url() );
		$identifier = '';

		if ( isset( $url_parts['host'] ) ) {
			$identifier = (string) $url_parts['host'];
		}

		if ( isset( $url_parts['port'] ) ) {
			$identifier .= ':' . (string) $url_parts['port'];
		}

		if ( isset( $url_parts['path'] ) ) {
			$identifier .= rtrim( (string) $url_parts['path'], '/' );
		}

		return $identifier;
	}
}
