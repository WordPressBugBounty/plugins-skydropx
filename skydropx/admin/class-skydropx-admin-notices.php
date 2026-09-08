<?php
/**
 * Admin notices manager.
 *
 * Provides a small API to queue and render admin notices in WordPress,
 * storing them temporarily in wp_options to persist across redirects.
 *
 * @package   Skydropx
 * @subpackage Skydropx/admin
 * @since     1.0.0
 */

namespace Skydropx\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles enqueueing and output of admin notices for the plugin.
 */
class Skydropx_Admin_Notices {

	const NOTICES_OPTION_KEY = 'SKYDROPX_admin_notices_key';

	/**
	 * Output stored admin notices and then clear them.
	 *
	 * Hooked from the core class via 'admin_notices'.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function output_notices() {
		$notices = self::get_notices();
		if ( empty( $notices ) || ! is_admin() ) {
			return;
		}

		foreach ( $notices as $type => $messages ) {
			foreach ( $messages as $key => $message ) {
				printf(
					'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
					esc_attr( $type ),
					wp_kses(
						$message,
						array(
							'a'      => array(
								'href'   => array(),
								'target' => array(),
								'rel'    => array(),
							),
							'strong' => array(),
							'em'     => array(),
							'br'     => array(),
						)
					)
				);
			}
		}

		self::update_notices( array() );
	}

	/**
	 * Add an error notice.
	 *
	 * @since 1.0.0
	 * @param string $key     Unique key for the notice.
	 * @param string $message The message to display.
	 */
	public static function add_error( $key, $message ) {
		self::add_notice( $key, $message, 'error' );
	}

	/**
	 * Add a warning notice.
	 *
	 * @since 1.0.0
	 * @param string $key     Unique key for the notice.
	 * @param string $message The message to display.
	 */
	public static function add_warning( $key, $message ) {
		self::add_notice( $key, $message, 'warning' );
	}

	/**
	 * Retrieve stored notices from wp_options.
	 *
	 * @since 1.0.0
	 * @return array<string,array<string,string>> Notices grouped by type and keyed by unique key.
	 */
	private static function get_notices() {
		$notices = get_option( self::NOTICES_OPTION_KEY, array() );
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Update notices in the options table.
	 *
	 * @since 1.0.0
	 * @param array<string,array<string,string>> $notices Notices grouped by type and keyed by unique key.
	 * @return void
	 */
	private static function update_notices( array $notices ) {
		update_option( self::NOTICES_OPTION_KEY, $notices );
	}

	/**
	 * Remove a stored notice by key.
	 *
	 * If $type is provided, removes only from that bucket.
	 *
	 * @since 1.0.0
	 * @param string      $key  Unique key for the notice.
	 * @param string|null $type Optional. The type bucket (error, warning). Null to search all.
	 * @return void
	 */
	public static function remove_notice( $key, $type = null ) {
		$notices = self::get_notices();
		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		if ( null !== $type ) {
			if ( isset( $notices[ $type ][ $key ] ) ) {
				unset( $notices[ $type ][ $key ] );
				if ( empty( $notices[ $type ] ) ) {
					unset( $notices[ $type ] );
				}
				self::update_notices( $notices );
			}
			return;
		}

		// Remove from any bucket where it exists.
		$updated = false;
		foreach ( $notices as $bucket => $messages ) {
			if ( isset( $messages[ $key ] ) ) {
				unset( $notices[ $bucket ][ $key ] );
				$updated = true;
				if ( empty( $notices[ $bucket ] ) ) {
					unset( $notices[ $bucket ] );
				}
			}
		}
		if ( $updated ) {
			self::update_notices( $notices );
		}
	}

	/**
	 * Add a notice with a unique key.
	 *
	 * Ensures notices do not duplicate for the same key.
	 *
	 * @since 1.0.0
	 * @param string $key     Unique key for the notice.
	 * @param string $message The message to display.
	 * @param string $type    The type of notice (error, warning).
	 * @return void
	 */
	private static function add_notice( $key, $message, $type = 'error' ) {
		$notices = self::get_notices();

		// Ensure no duplicates using the key.
		if ( ! isset( $notices[ $type ] ) || ! is_array( $notices[ $type ] ) ) {
			$notices[ $type ] = array();
		}

		if ( ! isset( $notices[ $type ][ $key ] ) ) {
			$notices[ $type ][ $key ] = $message;
			self::update_notices( $notices );
		}
	}
}
