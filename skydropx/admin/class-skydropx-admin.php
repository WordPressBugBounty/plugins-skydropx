<?php
/**
 * Admin controller: menu rendering, validations, and admin UI for the plugin.
 *
 * Registers the admin menu, enqueues admin assets, validates required settings
 * (permalinks and shop id) and renders the onboarding CTA or final iframe.
 *
 * @package   Skydropx
 * @subpackage Skydropx/admin
 * @since     1.0.0
 */

namespace Skydropx\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Skydropx\Admin\Skydropx_Admin_Notices;
use Skydropx\Helper\Helper;
use Skydropx\Includes\Skydropx_Repository;
use Skydropx\Includes\Skydropx_Service;

/**
 * The admin specific functionality of the plugin.
 *
 * @link       https://ecommerce.pro.skydropx.com
 * @since      1.0.0
 *
 * @package    Skydropx
 * @subpackage Skydropx/admin
 */
class Skydropx_Admin {


	/**
	 * Unique plugin handle used for assets and logging.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Data access layer for API keys/webhooks/options.
	 *
	 * @var Skydropx_Repository
	 */
	private $repository;

	/**
	 * Domain service layer for onboarding/uninstall flows.
	 *
	 * @var Skydropx_Service
	 */
	private $service_manager;

	/**
	 * Initialize the admin controller with dependencies.
	 *
	 * Called by the core class during admin hook registration.
	 *
	 * @since 1.0.0
	 *
	 * @param string                  $plugin_name      Unique plugin handle for assets.
	 * @param string                  $version          Plugin version.
	 * @param Skydropx_Repository $repository       Repository dependency.
	 * @param Skydropx_Service    $service_manager  Service dependency.
	 */
	public function __construct(
		$plugin_name,
		$version,
		Skydropx_Repository $repository,
		Skydropx_Service $service_manager
	) {
		$this->plugin_name     = $plugin_name;
		$this->version         = $version;
		$this->repository      = $repository;
		$this->service_manager = $service_manager;
	}

	/**
	 * Enqueue admin stylesheet for the plugin page/components.
	 *
	 * Hooked into 'admin_enqueue_scripts'.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/skydropx-admin.css',
			array(),
			$this->version,
			'all'
		);
	}



	/**
	 * Register the plugin admin menu page.
	 *
	 * Adds a top level menu that renders skydropx_admin_menu_content().
	 * Hooked into 'admin_menu'.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_menu() {
		$page_title = __( 'Skydropx', 'skydropx' );
		$menu_title = __( 'Skydropx', 'skydropx' );
		$capability = 'manage_woocommerce';
		$menu_slug  = 'skydropx';
		$function   = array( $this, 'skydropx_admin_menu_content' );

		// Hide the menu when the shop is already connected.
		$api_keys = $this->repository->fetch_api_keys_by_user( get_current_user_id() );
		if ( $this->is_store_connected( $api_keys ) ) {
			return;
		}

		try {
			add_menu_page(
				$page_title,
				$menu_title,
				$capability,
				$menu_slug,
				$function,
				'dashicons-admin-generic'
			);
		} catch ( \Exception $e ) {

			Helper::log_error(
				sprintf(
				// translators: %s Error message when registering the admin menu.
					__( 'Error registering admin menu: %s', 'skydropx' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Coordinate admin environment validations.
	 *
	 * Hooked into 'admin_init'. Verifies permalink structure and shop id presence,
	 * posting the appropriate admin notices.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function validate_necessary_settings() {
		$this->show_permalink_auto_updated_notice();

		$permalink_structure                = get_option( 'permalink_structure' );
		$skydropx_visited_permalink_view = get_option( 'skydropx_visited_permalink_view', false );

		$this->validate_permalink_setting_change(
			$permalink_structure,
			$skydropx_visited_permalink_view
		);
		$this->redirect_to_connected_plugin_page();
	}

	/**
	 * Show a one time admin notice when the plugin automatically changed permalinks.
	 *
	 * The activation routine stores a flag in wp_options this method consumes it
	 * and posts a dismissible notice then clears the flag.
	 *
	 * References:
	 * - Permalink settings screen referenced by this notice:
	 *
	 *   @link https://wordpress.org/documentation/article/customize-permalinks/
	 * - Admin notices should be used sparingly and be dismissible:
	 *   @link https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function show_permalink_auto_updated_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( 'skydropx_permalinks_auto_updated' ) ) {
			return;
		}

		$permalink_page = admin_url( 'options-permalink.php' );
		$previous       = get_option( 'skydropx_previous_permalink_structure', '' );

		$details = '';
		if ( is_string( $previous ) && '' !== trim( $previous ) ) {
			$details = sprintf(
				'<br><em>%s</em> %s',
				__( 'Estructura anterior:', 'skydropx' ),
				esc_html( $previous )
			);
		}

		$message = sprintf(
			'<strong>%s</strong> %s <a href="%s">%s</a>.%s',
			__( 'Skydropx Plugin:', 'skydropx' ),
			__( 'Se actualizó automáticamente la estructura de enlaces permanentes a "Nombre de la entrada" (/%postname%/) para asegurar el correcto funcionamiento del plugin.', 'skydropx' ),
			esc_url( $permalink_page ),
			__( 'Ver configuración de enlaces permanentes', 'skydropx' ),
			$details
		);

		Skydropx_Admin_Notices::add_warning( 'permalink_structure_auto_updated', $message );
		delete_option( 'skydropx_permalinks_auto_updated' );
	}

	/**
	 * Render the plugin admin page content.
	 *
	 * Decides between showing CTAs (configure permalinks, link shop) or the final iframe to the external app.
	 * Callback registered by add_menu_page() in admin_menu().
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function skydropx_admin_menu_content() {
		$user_id               = get_current_user_id();
		$api_keys              = $this->repository->fetch_api_keys_by_user( $user_id );
		$has_permalinks_active = get_option( 'permalink_structure', false );
		$is_connected          = $this->is_store_connected( $api_keys );

		if ( ! $has_permalinks_active || ! $is_connected ) {
			return $this->render_call_to_actions_content(
				$has_permalinks_active,
				$api_keys
			);
		}

		return $this->render_iframe_content( true );
	}

	/**
	 * Determine whether the store is considered connected to the external platform.
	 *
	 * Connection signal is the presence of WooCommerce REST API keys created by
	 * this plugin for the current admin user.
	 *
	 * @since 1.0.0
	 * @param array|null $api_keys Row returned by the repository or null.
	 * @return bool
	 */
	private function is_store_connected( $api_keys ) {
		return is_array( $api_keys )
			&& ! empty( $api_keys['consumer_key'] )
			&& ! empty( $api_keys['consumer_secret'] );
	}

	/**
	 * Render a partial template.
	 *
	 * @param string $partial_name Name of the partial file (without extension).
	 * @param array  $data Associative array of data to pass to the partial.
	 */
	public static function render_partial( $partial_name, $data = array() ) {
		$partial_path = plugin_dir_path( __FILE__ ) . '/partials/' . $partial_name . '.php';

		if ( file_exists( $partial_path ) ) {
			if ( is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					if ( is_string( $key ) && '' !== $key ) {
						${$key} = $value;
					}
				}
			}
			include $partial_path;
		} else {
			Helper::log_error(
				sprintf(
					// translators: %s is the partial template name that was not found on disk.
					__( 'Partial template not found: %s', 'skydropx' ),
					$partial_name
				)
			);
		}
	}

	/**
	 * Validate permalink structure and guide the admin to configure it.
	 *
	 * Marks a visit to the permalink settings screen and, if permalinks
	 * are missing, posts an admin error notice with a link to fix it.
	 * Called from validate_necessary_settings().
	 *
	 * @since 1.0.0
	 *
	 * @param string|false $permalink_structure               Current permalink structure or false when disabled.
	 * @param bool         $skydropx_visited_permalink_view Whether the admin has visited the permalink settings screen.
	 * @return void
	 */
	private function validate_permalink_setting_change(
		$permalink_structure,
		$skydropx_visited_permalink_view
	) {
		global $pagenow;
		if ( 'options-permalink.php' === $pagenow ) {
			update_option( 'skydropx_visited_permalink_view', true );
		}

		$has_postname_structure = $this->is_postname_permalink( (string) $permalink_structure );

		// If structure is valid, ensure notice is removed and exit early.
		if ( $has_postname_structure ) {
			Skydropx_Admin_Notices::remove_notice( 'permalink_structure', 'error' );
			return;
		}

		// Otherwise, show notice guiding to set Post name structure.
		if ( ! $skydropx_visited_permalink_view || ! $has_postname_structure ) {
			$permalink_page = admin_url( 'options-permalink.php' );
			$message        = sprintf(
				'<strong>%s</strong> %s <a href="%s">%s</a> %s',
				__( 'Skydropx Plugin:', 'skydropx' ),
				__( 'Para que el plugin Skydropx funcione correctamente, es necesario configurar los enlaces permanentes en "Nombre de la entrada".', 'skydropx' ),
				esc_url( $permalink_page ),
				__( 'Configuración de enlaces permanentes', 'skydropx' ),
				__( 'Selecciona la opción "Nombre de la entrada" (%postname%) y guarda los cambios.', 'skydropx' )
			);
			Skydropx_Admin_Notices::add_error( 'permalink_structure', $message );
		}
	}

	/**
	 * Check if the permalink structure is set to Post name.
	 *
	 * Accepts '/%postname%/' as the canonical value and tolerates missing trailing slash.
	 *
	 * @param string $structure Current permalink structure.
	 * @return bool
	 */
	private function is_postname_permalink( $structure ) {
		$structure = trim( (string) $structure );
		return $structure === '/%postname%/' || $structure === '/%postname%';
	}

	/**
	 * Prepare and render the installation completion view with the proper CTA.
	 *
	 * When permalinks are active and the store is not yet connected, API keys
	 * are generated (when missing) so the admin can finish the onboarding on
	 * the external platform.
	 * Called from skydropx_admin_menu_content().
	 *
	 * @since 1.0.0
	 *
	 * @param bool       $has_permalinks_active Whether permalinks are enabled.
	 * @param array|null $api_keys              The current user's API keys (or null).
	 * @return mixed
	 */
	private function render_call_to_actions_content(
		$has_permalinks_active,
		$api_keys
	) {

		$call_to_action_link = admin_url( 'options-permalink.php' );
		$button_text         = __( 'Configurar enlaces permanentes', 'skydropx' );

		if (
			$has_permalinks_active
			&& ! $this->is_store_connected( $api_keys )
			&& get_option( 'skydropx_visited_permalink_view' )
		) {
			[$consumer_key, $consumer_secret] = $this->repository->reset_api_keys();
			$call_to_action_link              = $this->service_manager->generate_shop_creation_url(
				$consumer_key,
				$consumer_secret
			);
			$button_text                      = __( 'Vincular mi tienda', 'skydropx' );
			$redirect_destination             = $this->get_post_connect_admin_url();
		}

		$template_data = array(
			'button_link'          => $call_to_action_link,
			'button_text'          => $button_text,
			'redirect_destination' => isset( $redirect_destination ) ? $redirect_destination : '',
		);

		return $this->render_partial(
			'complete-installation-view',
			$template_data
		);
	}

	/**
	 * Render a sanitized iframe to the external ecommerce app.
	 *
	 * Builds a URL of the form SKYDROPX_ECOMMERCE_URL/{success|error}
	 * with the current store domain and the isComplete flag.
	 * Called from skydropx_admin_menu_content().
	 *
	 * @since 1.0.0
	 *
	 * @param bool $is_success Whether the previous steps completed successfully.
	 * @return void
	 */
	private function render_iframe_content( $is_success = true ) {
		// Determine the iframe path type.
		$type_path = $is_success ? 'success' : 'error';

		// Sanitize and encode parameters.
		$store_domain = Helper::get_store_identifier();
		$is_complete  = $is_success ? 'true' : 'false'; // Convert boolean to 'true' or 'false'.
		$iframe_src   = esc_url(
			SKYDROPX_ECOMMERCE_URL
			. '/' . $type_path . '?domain='
			. $store_domain . '&isComplete=' . $is_complete
		);

		// Generate iframe HTML.
		$iframe_html = sprintf(
			'<iframe src="%s" style="width: 100%%; height: 100vh; border: none;"></iframe>',
			$iframe_src
		);

		// Define the allowed tags and attributes.
		$allowed_tags = array(
			'iframe' => array(
				'src'    => array(),
				'style'  => array(),
				'width'  => array(),
				'height' => array(),
				'border' => array(),
			),
		);

		// Log is_complete and iframe_src.
		Helper::log_info( sprintf( 'is_complete: %s, iframe_src: %s', $is_complete, $iframe_src ) );

		// Safely output the iframe.
		echo wp_kses( $iframe_html, $allowed_tags );
	}

	/**
	 * Build the post connection admin destination URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_post_connect_admin_url() {
		return admin_url( 'admin.php?page=wc-admin' );
	}

	/**
	 * Redirect to WooCommerce Shipping settings when accessing the plugin page while connected.
	 *
	 * Runs on admin_init via validate_necessary_settings() to ensure headers can be sent.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function redirect_to_connected_plugin_page() {
		if ( ! is_admin() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'skydropx' !== $page ) {
			return;
		}

		$api_keys              = $this->repository->fetch_api_keys_by_user( get_current_user_id() );
		$has_permalinks_active = get_option( 'permalink_structure', false );

		if ( $this->is_store_connected( $api_keys ) && $has_permalinks_active ) {
			$redirect_destination = $this->get_post_connect_admin_url();
			wp_safe_redirect( $redirect_destination );
			exit;
		}
	}
}
