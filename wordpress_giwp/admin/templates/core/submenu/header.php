<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * En-tête des pages modules (sous-menus GI-Toolkit).
 *
 * @since 1.0.0
 */

$header_title = isset( $this->header_title ) ? (string) $this->header_title : '';
?>
<div class="wrap gi-toolkit gi-toolkit--module">

	<?php if ( ! isset( $this->disable_form ) ) : ?>
	<form action="" method="post" enctype="multipart/form-data">
	<?php endif; ?>

		<header class="gi-toolkit__header gi-toolkit__header--submenu">
			<div class="gi-toolkit__header__left">
				<div class="gi-toolkit__header__left__logo">
					<?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
					<img src="<?php echo esc_url( GI_TOOLKIT_PLUGIN_URL . 'admin/images/logo.png' ); ?>" alt="<?php esc_attr_e( 'GI-Toolkit', 'gi-toolkit' ); ?>" width="48" height="48" />
				</div>
				<div class="gi-toolkit__header__title-block">
					<span class="gi-toolkit__header__brand"><?php esc_html_e( 'GI-Toolkit', 'gi-toolkit' ); ?></span>
					<?php if ( '' !== $header_title ) : ?>
						<h1 class="gi-toolkit__header__page-title"><?php echo esc_html( $header_title ); ?></h1>
					<?php endif; ?>
				</div>
			</div>

			<div class="gi-toolkit__header__right">
				<?php if ( isset( $this->nonce_action ) && ( ! isset( $this->disable_save_form ) || empty( $this->disable_save_form ) ) ) : ?>
					<div class="gi-toolkit__header__right__save">
						<?php
						wp_nonce_field( $this->nonce_action );
						submit_button( esc_html__( 'Save', 'gi-toolkit' ) );
						?>
					</div>
				<?php endif; ?>
			</div>
		</header>
