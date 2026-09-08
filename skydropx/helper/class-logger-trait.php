<?php
/**
 * Logger and Debug Trait
 *
 * Combines logging and debugging functionalities.
 *
 * @package  Skydropx\Helper
 */

namespace Skydropx\Helper;

trait Logger_Trait {
	/**
	 * WooCommerce logger instance.
	 *
	 * @var \WC_Logger|null
	 */
	private static $logger;

	/**
	 * Source tag used in WooCommerce logs.
	 *
	 * @var string
	 */
	private static $log_source = 'Skydropx';

	/**
	 * Inits our logger singleton
	 *
	 * @return void
	 */
	public static function init() {
		if ( function_exists( 'wc_get_logger' ) && ! isset( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}
	}

	/**
	 * Logs an info message
	 *
	 * @param mixed $msg Message to log.
	 * @return void
	 */
	public static function log_info( $msg ) {
		self::ensure_logger();
		if ( self::$logger ) {
			self::$logger->info( wc_print_r( $msg, true ), array( 'source' => self::$log_source ) );
		}
	}

	/**
	 * Logs an error message
	 *
	 * @param mixed $msg Message to log.
	 * @return void
	 */
	public static function log_error( $msg ) {
		self::ensure_logger();
		if ( self::$logger ) {
			self::$logger->error( wc_print_r( $msg, true ), array( 'source' => self::$log_source ) );
		}
	}

	/**
	 * Logs the provided data.
	 *
	 * @param mixed $log Data to log.
	 */
	public static function log( $log ) {
		self::ensure_logger();
		if ( self::$logger && self::has_logging_enabled() ) {
			$message = ( is_array( $log ) || is_object( $log ) )
				? wp_json_encode( $log, JSON_UNESCAPED_UNICODE )
				: $log;
			self::$logger->debug( wc_print_r( $message, true ), array( 'source' => self::$log_source ) );
		}
	}

	/**
	 * Ensure logger is initialized before use
	 *
	 * @return void
	 */
	private static function ensure_logger() {
		if ( ! isset( self::$logger ) ) {
			self::init();
		}
	}

	/**
	 * Check if logging is enabled
	 *
	 * @return bool
	 */
	private static function has_logging_enabled() {
		// Adjust logic based on WP_DEBUG and environment.
		// Enable logging if WP_DEBUG is true or if environment is not 'production'.
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_ENV' ) && WP_ENV !== 'production' );
	}
}
