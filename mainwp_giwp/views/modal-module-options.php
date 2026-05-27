<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="mainwp-giweb-module-options-modal" class="mainwp-giweb-modal" aria-hidden="true" role="dialog" aria-labelledby="mainwp-giweb-module-options-title">
	<div class="mainwp-giweb-modal__backdrop" tabindex="-1"></div>
	<div class="mainwp-giweb-modal__dialog mainwp-giweb-modal__dialog--wide">
		<header class="mainwp-giweb-modal__header">
			<h2 id="mainwp-giweb-module-options-title"><?php esc_html_e( 'Réglages du module', 'mainwp-giweb' ); ?></h2>
		</header>
		<div class="mainwp-giweb-modal__body">
			<p class="description mainwp-giweb-module-options-hint">
				<?php esc_html_e( 'Aperçu des réglages importés (mots de passe masqués). Pour modifier, changez sur un site enfant puis réimportez.', 'mainwp-giweb' ); ?>
			</p>
			<div class="mainwp-giweb-module-options-css" hidden>
				<p>
					<label for="mainwp-giweb-module-options-css-field">
						<strong class="mainwp-giweb-module-options-css-label"><?php esc_html_e( 'CSS', 'mainwp-giweb' ); ?></strong>
					</label>
				</p>
				<textarea
					id="mainwp-giweb-module-options-css-field"
					class="large-text code mainwp-giweb-module-options-css-textarea"
					rows="18"
					spellcheck="false"
				></textarea>
				<p class="description mainwp-giweb-module-options-css-hint">
					<?php esc_html_e( 'Les balises HTML sont retirées à l’enregistrement. Enregistrez puis déployez la configuration pour appliquer le CSS sur les sites cibles.', 'mainwp-giweb' ); ?>
				</p>
			</div>
			<pre class="mainwp-giweb-module-options-json" aria-live="polite"></pre>
		</div>
		<footer class="mainwp-giweb-modal__footer">
			<button type="button" class="button mainwp-giweb-module-options-close">
				<?php esc_html_e( 'Fermer', 'mainwp-giweb' ); ?>
			</button>
			<button type="button" class="button button-primary mainwp-giweb-module-options-save" hidden>
				<?php esc_html_e( 'Enregistrer dans la config', 'mainwp-giweb' ); ?>
			</button>
		</footer>
	</div>
</div>
