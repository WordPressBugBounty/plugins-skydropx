<?php
/**
 * Quotation settings helper.
 *
 * Centralizes persistence and validation for quotation related options so
 * controllers and shipping logic remain thin and consistent.
 *
 * @package   Skydropx
 * @since     1.0.0
 */

namespace Skydropx\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Quotation settings value object helper.
 *
 * Centralizes read and write and validation rules for quotation related options.
 *
 * @since 1.0.0
 */
class Skydropx_Quotation_Settings {
	/**
	 * Option key for quotation enabled flag.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_ENABLE_QUOTATION = 'SKYDROPX_ENABLE_QUOTATION';

	/**
	 * Option key for quotation URL override.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_QUOTATION_BASE_URL = 'SKYDROPX_QUOTATION_BASE_URL';

	/**
	 * Get the persisted quotation enabled flag.
	 *
	 * Returns null when the option has not been set yet.
	 *
	 * @since 1.0.0
	 * @return bool|null
	 */
	public static function get_enabled(): ?bool {
		$value = get_option( self::OPTION_ENABLE_QUOTATION, null );
		if ( is_null( $value ) ) {
			return null;
		}
		return (bool) $value;
	}


	/**
	 * Check whether quotation feature is enabled.
	 *
	 * Uses false as a safe default when the option is missing.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return self::get_enabled() ?? false;
	}


	/**
	 * Persist quotation enabled flag.
	 *
	 * @since 1.0.0
	 * @param bool $enabled Whether quotations should be enabled.
	 * @return void
	 */
	public static function set_enabled( bool $enabled ): void {
		update_option( self::OPTION_ENABLE_QUOTATION, (bool) $enabled );
	}


	/**
	 * Remove persisted quotation enabled flag.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_enabled(): void {
		delete_option( self::OPTION_ENABLE_QUOTATION );
	}


	/**
	 * Normalize a quotation URL override for storage consistency.
	 *
	 * Trims whitespace and removes a trailing slash.
	 *
	 * @since 1.0.0
	 * @param string $url Raw URL value.
	 * @return string
	 */
	public static function normalize_base_url( string $url ): string {
		return rtrim( trim( $url ), '/' );
	}


	/**
	 * Validate and normalize a quotation URL override.
	 *
	 * Only absolute HTTPS URLs are accepted.
	 *
	 * @since 1.0.0
	 * @param string $url Raw URL value.
	 * @return string|false Normalized URL on success, false on validation failure.
	 */
	public static function validate_base_url( string $url ) {
		$raw_url = trim( $url );
		if ( '' === $raw_url ) {
			return false;
		}

		$sanitized = esc_url_raw( $raw_url );
		$validated = function_exists( 'wp_http_validate_url' ) ? wp_http_validate_url( $sanitized ) : $sanitized;
		if ( ! is_string( $validated ) || '' === $validated ) {
			return false;
		}

		$parts  = wp_parse_url( $validated );
		$scheme = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( 'https' !== $scheme ) {
			return false;
		}

		return self::normalize_base_url( $validated );
	}


	/**
	 * Apply quotation URL override from endpoint payload value.
	 *
	 * Accepted values:
	 * - null: clear stored override.
	 * - empty or blank string: clear stored override.
	 * - non empty string: validate as HTTPS URL and store.
	 * - any other type: invalid input.
	 *
	 * Possible error values:
	 * - invalid_type: value is neither string nor null.
	 * - invalid_url: value is a string but does not pass URL validation.
	 *
	 * @since 1.0.0
	 * @param mixed $value Raw payload value.
	 * @return array{
	 *   success:bool,
	 *   error:?string,
	 *   quotation_base_url:?string
	 * }
	 */
	public static function apply_base_url_from_payload( $value ): array {
		if ( null === $value ) {
			self::clear_base_url();
			return array(
				'success'            => true,
				'error'              => null,
				'quotation_base_url' => null,
			);
		}

		if ( ! is_string( $value ) ) {
			return array(
				'success'            => false,
				'error'              => 'invalid_type',
				'quotation_base_url' => self::get_base_url(),
			);
		}

		if ( '' === trim( $value ) ) {
			self::clear_base_url();
			return array(
				'success'            => true,
				'error'              => null,
				'quotation_base_url' => null,
			);
		}

		$saved = self::set_base_url( $value );
		if ( false === $saved ) {
			return array(
				'success'            => false,
				'error'              => 'invalid_url',
				'quotation_base_url' => self::get_base_url(),
			);
		}

		return array(
			'success'            => true,
			'error'              => null,
			'quotation_base_url' => $saved,
		);
	}


	/**
	 * Get stored quotation URL override.
	 *
	 * Returns null when the option is missing, invalid or empty after normalization.
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	public static function get_base_url(): ?string {
		$value = get_option( self::OPTION_QUOTATION_BASE_URL, '' );
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = self::normalize_base_url( $value );
		return '' === $value ? null : $value;
	}


	/**
	 * Validate and persist quotation URL override.
	 *
	 * @since 1.0.0
	 * @param string $url Raw URL value.
	 * @return string|false Normalized persisted URL on success, false on failure.
	 */
	public static function set_base_url( string $url ) {
		$normalized = self::validate_base_url( $url );
		if ( false === $normalized ) {
			return false;
		}
		update_option( self::OPTION_QUOTATION_BASE_URL, $normalized );
		return $normalized;
	}


	/**
	 * Delete quotation URL override.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_base_url(): void {
		delete_option( self::OPTION_QUOTATION_BASE_URL );
	}
}
