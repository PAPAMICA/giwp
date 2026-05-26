<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bundle_modules = $working_bundle['modules'] ?? array();
$db_options     = MainWP_GIWeb_Modules_UI::module_states_from_bundle( $working_bundle );
$gi_toolkit_url = MainWP_GIWeb_Modules_UI::gi_toolkit_assets_url();

$gi_modules = $modules;
if ( ! empty( $gi_modules ) ) {
	$gi_order = array_column( $gi_modules, 'name' );
	array_multisort( $gi_order, SORT_ASC, $gi_modules );
}
?>
<?php if ( empty( $bundle_modules ) && empty( $gi_modules ) ) : ?>
	<p><?php esc_html_e( 'Chargez d’abord une configuration depuis l’onglet Vue d’ensemble.', 'mainwp-giweb' ); ?></p>
<?php else : ?>
<p class="description">
	<?php esc_html_e( 'Utilisez les catégories à gauche (ex. e-mail / SMTP), puis « Voir les réglages » sur un module pour afficher les paramètres importés (SMTP, en-têtes, etc.).', 'mainwp-giweb' ); ?>
</p>
<div class="mainwp-giweb-modules-host">
	<div class="gi-toolkit">
		<form
			method="post"
			action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
			class="mainwp-giweb-modules-form"
		>
			<?php
			wp_nonce_field( 'mainwp_giweb_action', 'mainwp_giweb_nonce' );
			include MAINWP_GIWEB_PLUGIN_PATH . 'views/partials/form-context.php';
			?>
			<input type="hidden" name="mainwp_giweb_action" value="save_working_modules" />

			<header class="gi-toolkit__header">
				<div class="gi-toolkit__header__left">
					<div class="gi-toolkit__header__left__logo">
						<img height="51" src="<?php echo esc_url( $gi_toolkit_url . 'admin/images/icon-128x128.gif' ); ?>" alt="GI-Toolkit" />
					</div>
					<div class="gi-toolkit__header__left__title">
						<?php esc_html_e( 'Configuration de travail', 'mainwp-giweb' ); ?>
						<div class="gi-toolkit__header__left__title__version">
							<?php esc_html_e( 'MainWP GI-Toolkit Manager', 'mainwp-giweb' ); ?>
						</div>
					</div>
				</div>
				<div class="gi-toolkit__header__right">
					<div class="gi-toolkit__header__right__search">
						<input type="text" placeholder="<?php esc_attr_e( 'Rechercher', 'mainwp-giweb' ); ?>" />
					</div>
					<div class="gi-toolkit__header__right__save">
						<?php submit_button( __( 'Enregistrer', 'mainwp-giweb' ), 'primary', 'submit', false ); ?>
					</div>
				</div>
			</header>

			<div class="gi-toolkit__body">
				<div class="gi-toolkit__body__groups">
					<?php foreach ( $groups as $group_key => $group_data ) :
						if ( ! empty( $group_data['exception'] ) ) {
							continue;
						}
						$has_items = false;
						$counter   = 0;
						foreach ( $gi_modules as $class => $mod ) {
							if ( ( $mod['group'] ?? '' ) === $group_key ) {
								$has_items = true;
								++$counter;
							}
						}
						if ( ! $has_items ) {
							continue;
						}
						$logo_path = '';
						if ( ! empty( $group_data['logo'] ) ) {
							$logo_file = MAINWP_GIWEB_GI_TOOLKIT_PATH . 'admin/svg/' . $group_data['logo'];
							if ( is_readable( $logo_file ) ) {
								$logo_path = $logo_file;
							}
						}
						?>
						<div class="gi-toolkit__body__groups__item" data-key="<?php echo esc_attr( $group_key ); ?>">
							<span class="logo">
								<?php
								if ( $logo_path && function_exists( 'gi_toolkit_allowed_tags_for_svg_files' ) ) {
									echo wp_kses( file_get_contents( $logo_path ), gi_toolkit_allowed_tags_for_svg_files() );
								}
								?>
							</span>
							<?php echo esc_html( $group_data['name'] ?? $group_key ); ?>
							<span class="counter"><?php echo esc_html( (string) $counter ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="gi-toolkit__body__sections">
					<?php
					foreach ( $gi_modules as $class => $mod ) :
						$checked = isset( $db_options[ $class ] ) && '1' === $db_options[ $class ];
						$is_risk = in_array( $class, $high, true );
						$group   = $mod['group'] ?? '';
						?>
						<div
							class="gi-toolkit__body__sections__item module-item <?php echo $checked ? 'activated' : ''; ?> show"
							data-key="<?php echo esc_attr( $group . ( $checked ? ' activated' : '' ) ); ?>"
							data-title="<?php echo esc_attr( $mod['name'] ?? $class ); ?>"
							data-originaltitle="<?php echo esc_attr( $mod['original_name'] ?? '' ); ?>"
							<?php echo $is_risk ? ' data-high-risk="1"' : ''; ?>
						>
							<div class="gi-toolkit__body__sections__item__top module-header">
								<div class="gi-toolkit__body__sections__item__title">
									<span class="gi-toolkit__body__sections__item__title__text">
										<?php echo esc_html( $mod['name'] ?? $class ); ?>
										<?php if ( $is_risk ) : ?><span class="mainwp-giweb-risk-tag">⚠</span><?php endif; ?>
									</span>
									<span class="gi-toolkit__body__sections__item__title__tags">
										<?php if ( ! empty( $bundle_modules[ $class ]['options'] ) ) : ?>
											<button
												type="button"
												class="button-link mainwp-giweb-view-module-options"
												data-module-class="<?php echo esc_attr( $class ); ?>"
												data-module-name="<?php echo esc_attr( $mod['name'] ?? $class ); ?>"
											>
												<?php esc_html_e( 'Voir les réglages', 'mainwp-giweb' ); ?>
											</button>
										<?php endif; ?>
									</span>
								</div>
								<div class="gi-toolkit__body__sections__item__toggle">
									<label class="gi-toolkit__toggle">
										<input type="hidden" name="module_states[<?php echo esc_attr( $class ); ?>]" value="0" />
										<input
											type="checkbox"
											name="module_states[<?php echo esc_attr( $class ); ?>]"
											value="1"
											<?php checked( $checked ); ?>
										/>
										<span class="gi-toolkit__toggle__slider round"></span>
									</label>
								</div>
							</div>
							<?php if ( ! empty( $mod['desc'] ) ) : ?>
								<div class="gi-toolkit__body__sections__item__bottom">
									<div class="gi-toolkit__body__sections__item__description"><?php echo esc_html( $mod['desc'] ); ?></div>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="gi-toolkit__save-button">
				<button type="submit" title="<?php esc_attr_e( 'Enregistrer', 'mainwp-giweb' ); ?>">💾</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>
