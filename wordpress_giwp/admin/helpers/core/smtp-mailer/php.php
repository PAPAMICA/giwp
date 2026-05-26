<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class to handle PHP provider.
 * 
 * @since 2.14.0
 */

class Gi_Toolkit_SMTP_Mailer_Php {

	/**
	 * Render config provider php
	 * 
	 * @since 2.14.0
	 */
	public static function render_config( $active_provider, $class_smtp_mailer ) {
		?>
			<div class="gi-toolkit__section__body php <?php echo 'php' === $active_provider ? 'active' : ''; ?>">
				<div class="gi-toolkit__section__body__item">
					<div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'PHP Config', 'gi-toolkit' ); ?></div>
					<div class="gi-toolkit__section__body__item__desc"><?php esc_html_e( "You currently have the Default (none) mailer selected, which won't improve email deliverability.", 'gi-toolkit' ); ?></div>
				</div>
			</div>
		<?php
	}
}
