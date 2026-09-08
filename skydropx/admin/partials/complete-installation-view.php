<?php
/**
 * Complete installation view.
 *
 * @package   Skydropx
 * @subpackage Skydropx/admin
 * @since     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$logo_url    = \Skydropx\Helper\Helper::asset_url( 'assets/images/skydropx-logo.png' );
$success_url = \Skydropx\Helper\Helper::asset_url( 'assets/images/skydropx-success.png' );
?>

<div class="center-div">
	<div class="skydropx-content">
		<div class="skydropx-logo-container">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr__( 'Skydropx logo', 'skydropx' ); ?>" decoding="async" />
		</div>
		<div class="skydropx-description-container">
			<h2 class="skydropx-title">
				<?php
				// translators: Title message after plugin activation.
				esc_html_e( 'Activación completada, solo queda un paso', 'skydropx' );
				?>
			</h2>
			<p class="skydropx-description-sub-content">
				<?php
				// translators: Message guiding the user to link their store to use the integration.
				esc_html_e( 'Para disfrutar de los beneficios de esta integración, vincula tu tienda.', 'skydropx' );
				?>
			</p>
			<p class="skydropx-description-sub-content">
				<?php
				// translators: Message advising the user to try again if the store is not visible yet.
				esc_html_e( 'Si ya lo hiciste y aún no puedes verla, vuelve a intentarlo.', 'skydropx' );
				?>
			</p>
		</div>
		<?php if ( ! empty( $button_link ) && ! empty( $button_text ) ) : ?>
			<a class="skydropx-btn" href="<?php echo esc_url( $button_link ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $button_text ); ?>">
				<?php
				// translators: Text for the integration action button.
				echo esc_html( $button_text );
				?>
			</a>
			<?php if ( ! empty( $redirect_destination ) ) : ?>
				<script>
					(function () {
						try {
							const link = document.querySelector('.skydropx-btn');
							if (!link) { return; }
							const handler = function () {
								const destination = '<?php echo esc_js( esc_url( $redirect_destination ) ); ?>';
								if (destination) {
									setTimeout(function () {
										window.location.href = destination;
									}, 3000);
								}
							};
							link.addEventListener('click', handler, { once: true });
						} catch (e) {}
					})();
				</script>
			<?php endif; ?>
		<?php endif; ?>
		<div class="skydropx-image-container" aria-hidden="true">
			<img src="<?php echo esc_url( $success_url ); ?>" alt="" loading="lazy" decoding="async" />
		</div>
	</div>
</div>
