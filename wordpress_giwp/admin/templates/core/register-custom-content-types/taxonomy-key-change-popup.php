<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>
<div class="gi-toolkit-popup">
	<div class="gi-toolkit-popup__overlay" id="JS-popup-overlay"></div>
	<div class="gi-toolkit-popup__content">
		<div class="gi-toolkit-popup__header">
			<div class="gi-toolkit-popup__header__left">
				<div class="gi-toolkit-popup__header__icon">
					<?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/square-blue.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?>
				</div>
				<div class="gi-toolkit-popup__header__title"><?php esc_html_e( 'Method of resolution following changes', 'gi-toolkit' ); ?></div>
			</div>
			<div class="gi-toolkit-popup__header__right">
				<div class="gi-toolkit-popup__header__close" id="JS-close-popup">
					<?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?>
				</div>
			</div>
		</div>
		<div class="gi-toolkit-popup__body">
			<div class="gi-toolkit-popup__body__text"><?php esc_html_e( 'Please note that you have just changed the "Taxonomy Key" of your Custom Taxonomy and we have detected existing terms for this Taxonomy. Choose how you would like to resolve this change:', 'gi-toolkit' ); ?></div>
			<div class="gi-toolkit-popup__body__content">
				<div class="gi-toolkit-popup__body__content__item">
					<label class="gi-toolkit-popup__body__content__item__label">
						<input type="radio" name="gi-toolkit-popup-choice" value="migrate">
						<span class="custom-radio"></span>
						<?php esc_html_e( 'Migrate all “terms”:', 'gi-toolkit' ); ?>
						<span class="old"></span>→<span class="new"></span>
					</label>
				</div>
				<div class="gi-toolkit-popup__body__content__item">
					<label class="gi-toolkit-popup__body__content__item__label">
						<input type="radio" name="gi-toolkit-popup-choice" value="delete">
						<span class="custom-radio"></span>
						<?php esc_html_e( 'Delete all “terms” with old Taxonomy Key:', 'gi-toolkit' ); ?>
						<span class="old"></span>
					</label>
				</div>
				<div class="gi-toolkit-popup__body__content__item">
					<label class="gi-toolkit-popup__body__content__item__label">
						<input type="radio" name="gi-toolkit-popup-choice" value="ignore" checked>
						<span class="custom-radio"></span>
						<?php esc_html_e( 'Do nothing', 'gi-toolkit' ); ?>
					</label>
				</div>
			</div>
		</div>
		<div class="gi-toolkit-popup__footer">
			<div class="gi-toolkit-popup__footer__submit">
				<button type="button" id="JS-submit-popup"><?php esc_html_e( 'Save & confirm', 'gi-toolkit' ); ?></button>
				<div class="gi-toolkit-spinner"></div>
				<div class="gi-toolkit-message" id="JS-popup-message"></div>
			</div>
		</div>
	</div>
</div>
