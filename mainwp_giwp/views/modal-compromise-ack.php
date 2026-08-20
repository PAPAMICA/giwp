<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="mainwp-giweb-cd-ack-modal" class="giweb-gw-modal" aria-hidden="true" role="dialog" aria-labelledby="mainwp-giweb-cd-ack-modal-title" aria-modal="true">
	<div class="giweb-gw-modal__backdrop" tabindex="-1"></div>
	<div class="giweb-gw-modal__dialog">
		<header class="giweb-gw-modal__header">
			<h2 id="mainwp-giweb-cd-ack-modal-title"><?php esc_html_e( 'Acquitter les alertes', 'mainwp-giweb' ); ?></h2>
		</header>
		<div class="giweb-gw-modal__body">
			<p class="giweb-gw-modal__intro" data-ack-intro></p>
			<div class="giweb-gw-modal__run" hidden>
				<div class="giweb-gw-modal__bar-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
					<div class="giweb-gw-modal__bar" style="width: 0%;"></div>
				</div>
				<p class="giweb-gw-modal__progress-label">0 / 0</p>
				<div class="giweb-gw-modal__log" aria-live="polite" aria-relevant="additions"></div>
			</div>
		</div>
		<footer class="giweb-gw-modal__footer">
			<button type="button" class="button giweb-gw-modal__cancel">
				<?php esc_html_e( 'Annuler', 'mainwp-giweb' ); ?>
			</button>
			<button type="button" class="button button-primary giweb-gw-modal__confirm">
				<?php esc_html_e( 'Confirmer', 'mainwp-giweb' ); ?>
			</button>
			<button type="button" class="button button-primary giweb-gw-modal__close" hidden disabled>
				<?php esc_html_e( 'Fermer', 'mainwp-giweb' ); ?>
			</button>
		</footer>
	</div>
</div>
