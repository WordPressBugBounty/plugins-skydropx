<?php
/**
 * Admin UI for printing shipping labels (PDF) from Woocommerce orders.
 *
 * Adds:
 * - A metabox in the order edit screen to show label status and print link.
 * - An action button in the orders list table to print the label in a new tab.
 * - A server side proxy to fetch and stream the PDF.
 *
 * References:
 * - HPOS overview and migration guidance:
 *
 *   @link https://woocommerce.com/document/high-performance-order-storage/
 *
 * @package   Skydropx
 * @since     1.0.0
 */

namespace Skydropx\Admin;

defined( 'ABSPATH' ) || exit;

use Skydropx\Helper\Helper;

/**
 * Admin order label UI + PDF proxy handler.
 */
class Skydropx_Order_Label_UI {

	/**
	 * Admin post action used as the PDF proxy endpoint.
	 *
	 * IMPORTANT: must stay build safe (uses placeholders), do not hardcode brand/slug.
	 */
	private const PRINT_ACTION = 'skydropx_print_label';

	/**
	 * Orders list column key for the print action.
	 */
	private const LIST_COLUMN_KEY = 'skydropx_label';

	/**
	 * Label helper service.
	 *
	 * @var Skydropx_Order_Label_Service
	 */
	private $label_service;

	/**
	 * Constructor.
	 *
	 * @param Skydropx_Order_Label_Service|null $label_service Label service helper.
	 */
	public function __construct( Skydropx_Order_Label_Service $label_service = null ) {
		$this->label_service = $label_service ? $label_service : new Skydropx_Order_Label_Service();
	}

	/**
	 * Enqueue admin JS/CSS for order screens.
	 *
	 * @param string $hook_suffix Current admin page suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Load only on Woocommerce order screens
		if ( ! $this->is_orders_screen() ) {
			return;
		}

		$handle = 'skydropx-order-label-ui';
		wp_enqueue_script(
			$handle,
			plugin_dir_url( __FILE__ ) . 'js/skydropx-order-label-ui.js',
			array( 'jquery' ),
			defined( 'SKYDROPX_VERSION' ) ? SKYDROPX_VERSION : '1.0.0',
			true
		);
	}

	/**
	 * Register metabox for order edit screen.
	 *
	 * Hook: add_meta_boxes.
	 *
	 * HPOS requires using dynamic screen ids instead of hardcoded post types,
	 * while legacy stores still rely on the 'shop_order' post type.
	 *
	 * References:
	 * - HPOS admin screens guidance:
	 *
	 *   @link https://woocommerce.com/document/high-performance-order-storage
	 * - add_meta_boxes hook:
	 *   @link https://developer.wordpress.org/reference/hooks/add_meta_boxes/
	 *
	 * @param string   $post_type Current post type.
	 * @param \WP_Post $post     Current post object.
	 * @return void
	 */
	public function register_order_label_metabox( $post_type, $post ) {
		// Only register once on the correct order edit screen.
		$screen_id = $this->get_order_edit_screen_id();

		// In legacy, add_meta_boxes will fire for multiple post types; avoid adding to others.
		if ( 'shop_order' === $screen_id && 'shop_order' !== $post_type ) {
			return;
		}

		add_meta_box(
			'skydropx-order-label',
			sprintf(
				/* translators: %s brand name */
				__( 'Etiqueta de envío %s', 'skydropx' ),
				'Skydropx'
			),
			array( $this, 'render_order_label_metabox' ),
			$screen_id,
			'side',
			'high'
		);
	}

	/**
	 * Render metabox content.
	 *
	 * @param mixed $post_or_order Post or order object depending on HPOS.
	 * @return void
	 */
	public function render_order_label_metabox( $post_or_order ) {
		$order = $this->label_service->resolve_order( $post_or_order );
		if ( ! $order ) {
			return;
		}

		$order_id = (int) $order->get_id();

		// Security: only users that can edit this order should see label info.
		if ( ! $this->label_service->current_user_can_access_order( $order_id ) ) {
			return;
		}

		$has_label = $this->label_service->has_label_tracking_meta( $order );
		$print_url = $this->build_print_proxy_url( $order_id );

		echo '<div class="skydropx-order-label-metabox">';
		echo '<p><strong>' . esc_html__( 'Estado:', 'skydropx' ) . '</strong> ';

		if ( $has_label ) {
			echo esc_html__( 'Guía generada', 'skydropx' );
		} else {
			echo esc_html__( 'Guía no generada', 'skydropx' );
		}
		echo '</p>';

		if ( $has_label ) {
			echo '<p><a class="button button-primary" href="' . esc_url( $print_url ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'Imprimir guía', 'skydropx' )
				. '</a></p>';
		}

		echo '</div>';
	}

	/**
	 * Add "Print label" action to WooCommerce orders list actions column.
	 *
	 * Hook: woocommerce_admin_order_actions
	 *
	 * @param array     $actions Existing actions.
	 * @param \WC_Order $order   Order object.
	 * @return array
	 */
	public function add_orders_list_action( $actions, $order ) {
		$order_id = $this->extract_order_id( $order );
		if ( $order_id <= 0 ) {
			return $actions;
		}

		$printable_order = $this->get_printable_order( $order_id );
		if ( ! $printable_order ) {
			return $actions;
		}

		$print_url = $this->build_print_proxy_url( $order_id );

		$actions[ self::PRINT_ACTION ] = array(
			'url'    => $print_url,
			/* translators: %s brand name */
			'name'   => sprintf( __( 'Imprimir guía %s', 'skydropx' ), 'Skydropx' ),
			// This becomes a CSS class on the action button.
			'action' => 'skydropx_print_label',
		);

		return $actions;
	}

	/**
	 * Add a small column to the HPOS orders list table to display the print icon.
	 *
	 * This is a fallback for cases where the native "Actions" column is hidden/removed.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_hpos_orders_list_column( array $columns ): array {
		if ( isset( $columns[ self::LIST_COLUMN_KEY ] ) ) {
			return $columns;
		}

		$insert_before = 'origin';
		$new_columns   = array();

		foreach ( $columns as $key => $label ) {
			if ( $insert_before === $key ) {
				$new_columns[ self::LIST_COLUMN_KEY ] = '';
			}
			$new_columns[ $key ] = $label;
		}

		if ( ! isset( $new_columns[ self::LIST_COLUMN_KEY ] ) ) {
			$new_columns[ self::LIST_COLUMN_KEY ] = '';
		}

		return $new_columns;
	}

	/**
	 * Render the HPOS orders list custom column.
	 *
	 * @param string $column Column name.
	 * @param mixed  $order_id_or_order Order id or order object.
	 * @return void
	 */
	public function render_hpos_orders_list_column( string $column, $order_id_or_order ): void {
		if ( self::LIST_COLUMN_KEY !== $column ) {
			return;
		}

		$order_id = $this->extract_order_id( $order_id_or_order );
		$this->render_orders_list_print_icon( $order_id );
	}

	/**
	 * Add a column to the legacy orders list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_legacy_orders_list_column( array $columns ): array {
		if ( isset( $columns[ self::LIST_COLUMN_KEY ] ) ) {
			return $columns;
		}

		$columns[ self::LIST_COLUMN_KEY ] = '';
		return $columns;
	}

	/**
	 * Render the legacy orders list custom column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Order id.
	 * @return void
	 */
	public function render_legacy_orders_list_column( string $column, int $post_id ): void {
		if ( self::LIST_COLUMN_KEY !== $column ) {
			return;
		}

		$this->render_orders_list_print_icon( (int) $post_id );
	}

	/**
	 * Render the printer icon link.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function render_orders_list_print_icon( int $order_id ): void {
		if ( $order_id <= 0 ) {
			return;
		}

		if ( ! $this->get_printable_order( $order_id ) ) {
			return;
		}

		$url   = $this->build_print_proxy_url( $order_id );
		$title = sprintf( __( 'Imprimir guía %s', 'skydropx' ), 'Skydropx' );

		$classes = array(
			'button',
			'wc-action-button',
			'skydropx-print-label',
			self::PRINT_ACTION, // e.g. skydropx_print_label.
		);

		echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		echo ' target="_blank" rel="noopener noreferrer"';
		echo ' title="' . esc_attr( $title ) . '" aria-label="' . esc_attr( $title ) . '"';
		echo '></a>';
	}

	/**
	 * Admin post handler to stream the PDF label.
	 *
	 * @return void
	 */
	public function handle_print_label() {
		$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
		if ( $order_id <= 0 ) {
			wp_die( esc_html__( 'Orden inválida.', 'skydropx' ), '', array( 'response' => 400 ) );
		}

		// CSRF protection.
		check_admin_referer( $this->get_print_nonce_action( $order_id ) );

		// Capability check.
		if ( ! $this->label_service->current_user_can_access_order( $order_id ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta orden.', 'skydropx' ), '', array( 'response' => 403 ) );
		}

		$order = $this->label_service->get_order_by_id( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'La guía no está disponible para esta orden.', 'skydropx' ), '', array( 'response' => 404 ) );
		}

		if ( ! $this->label_service->has_label_tracking_meta( $order ) ) {
			wp_die( esc_html__( 'Guía no generada para esta orden.', 'skydropx' ), '', array( 'response' => 404 ) );
		}

		$url = $this->label_service->build_remote_label_url( $order_id );
		if ( '' === $url ) {
			Helper::log_error(
				array(
					'context'  => 'order_label_proxy_url_empty',
					'order_id' => $order_id,
				)
			);
			wp_die( esc_html__( 'No se pudo construir la URL de la guía.', 'skydropx' ), '', array( 'response' => 500 ) );
		}

		$tmp = wp_tempnam( 'skydropx-label-' . $order_id . '.pdf' );
		if ( ! $tmp ) {
			wp_die( esc_html__( 'No se pudo crear un archivo temporal.', 'skydropx' ), '', array( 'response' => 500 ) );
		}

		$args = array(
			'method'      => 'GET',
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => $this->label_service->build_remote_headers( 'application/pdf' ),
			'stream'      => true,
			'filename'    => $tmp,
		);

		$response = wp_safe_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			@unlink( $tmp );
			Helper::log_error(
				array(
					'context'  => 'order_label_proxy_error',
					'order_id' => $order_id,
					'error'    => $response->get_error_message(),
				)
			);
			wp_die( esc_html( $response->get_error_message() ), '', array( 'response' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			@unlink( $tmp );
			Helper::log_error(
				array(
					'context'   => 'order_label_proxy_http_error',
					'order_id'  => $order_id,
					'http_code' => $code,
				)
			);
			if ( 404 === $code ) {
				wp_die( esc_html__( 'Guía no generada para esta orden.', 'skydropx' ), '', array( 'response' => 404 ) );
			}
			// Translators: %d is an HTTP status code.
			wp_die( esc_html( sprintf( __( 'No se pudo obtener la guía (HTTP %d).', 'skydropx' ), $code ) ), '', array( 'response' => 502 ) );
		}

		// Stream the PDF to the browser.
		$filename = 'skydropx-label-order-' . $order_id . '.pdf';

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$size = @filesize( $tmp );
		if ( false !== $size ) {
			header( 'Content-Length: ' . (int) $size );
		}

		// Flush any buffered output.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$fh = @fopen( $tmp, 'rb' );
		if ( false === $fh ) {
			@unlink( $tmp );
			wp_die( esc_html__( 'No se pudo leer la guía.', 'skydropx' ), '', array( 'response' => 500 ) );
		}

		fpassthru( $fh );
		fclose( $fh );
		@unlink( $tmp );
		exit;
	}

	/**
	 * Determine if current screen is an orders screen, legacy or HPOS.
	 *
	 * @return bool
	 */
	private function is_orders_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) ) {
			return false;
		}

		$ids = array(
			'shop_order',
			'edit-shop_order',
			'woocommerce_page_wc-orders',
		);

		// HPOS order edit screen id.
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$ids[] = wc_get_page_screen_id( 'shop-order' );
		}

		return in_array( (string) $screen->id, array_filter( $ids ), true );
	}

	/**
	 * HPOS safe screen id for order edit.
	 *
	 * HPOS may change the order screen id; legacy remains 'shop_order'. We must
	 * detect the enabled storage to register metaboxes in both modes.
	 *
	 * References:
	 * - HPOS upgrade recipe:
	 *
	 *   @link https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/
	 * @return string
	 */
	private function get_order_edit_screen_id(): string {
		// Default legacy orders are stored as shop_order posts.
		$screen = 'shop_order';

		// HPOS recommendation (WooCommerce docs): use wc_get_page_screen_id( 'shop-order' ) when COT is enabled.
		if (
			class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& function_exists( 'wc_get_container' )
			&& function_exists( 'wc_get_page_screen_id' )
		) {
			try {
				$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
				if ( is_object( $controller ) && method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) && $controller->custom_orders_table_usage_is_enabled() ) {
					$screen = (string) wc_get_page_screen_id( 'shop-order' );
				}
			} catch ( \Throwable $th ) {
				// Fallback to legacy screen.
				$screen = 'shop_order';
			}
		}

		return $screen;
	}

	/**
	 * Extract an order id from a mixed value.
	 *
	 * @param mixed $order_or_id Order id or order like object.
	 * @return int
	 */
	private function extract_order_id( $order_or_id ): int {
		if ( is_numeric( $order_or_id ) ) {
			return absint( $order_or_id );
		}

		if ( is_object( $order_or_id ) && method_exists( $order_or_id, 'get_id' ) ) {
			return (int) $order_or_id->get_id();
		}

		return 0;
	}

	/**
	 * Return order ready for printing.
	 *
	 * @param int $order_id Order ID.
	 * @return \WC_Order|null
	 */
	private function get_printable_order( int $order_id ) {
		$order = $this->label_service->get_order_by_id( $order_id );
		if ( ! $order ) {
			return null;
		}

		if ( ! $this->label_service->current_user_can_access_order( $order_id ) ) {
			return null;
		}

		if ( ! $this->label_service->has_label_tracking_meta( $order ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Build the admin post proxy URL with nonce.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function build_print_proxy_url( int $order_id ): string {
		$url = add_query_arg(
			array(
				'action'   => self::PRINT_ACTION,
				'order_id' => $order_id,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, $this->get_print_nonce_action( $order_id ) );
	}

	/**
	 * Nonce action name for printing labels.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function get_print_nonce_action( int $order_id ): string {
		return self::PRINT_ACTION . ':' . $order_id;
	}
}
