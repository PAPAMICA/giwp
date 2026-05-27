<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="mainwp-giweb-plugin-deploy-modal" class="mainwp-giweb-modal" aria-hidden="true" role="dialog" aria-labelledby="mainwp-giweb-plugin-deploy-modal-title">
	<div class="mainwp-giweb-modal__backdrop" tabindex="-1"></div>
	<div class="mainwp-giweb-modal__dialog">
		<header class="mainwp-giweb-modal__header">
			<h2 id="mainwp-giweb-plugin-deploy-modal-title"><?php esc_html_e( 'Déploiement GI-Toolkit', 'mainwp-giweb' ); ?></h2>
		</header>
		<div class="mainwp-giweb-modal__body">
			<p class="description mainwp-giweb-plugin-deploy-url-hint" hidden></p>
			<div class="mainwp-giweb-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
				<div class="mainwp-giweb-progress__bar" style="width: 0%;"></div>
			</div>
			<p class="mainwp-giweb-progress__label">0 / 0</p>
			<div class="mainwp-giweb-sync-log mainwp-giweb-plugin-deploy-log" aria-live="polite" aria-relevant="additions"></div>
		</div>
		<footer class="mainwp-giweb-modal__footer">
			<button type="button" class="button button-primary mainwp-giweb-modal__close" disabled>
				<?php esc_html_e( 'Fermer', 'mainwp-giweb' ); ?>
			</button>
		</footer>
	</div>
</div>
