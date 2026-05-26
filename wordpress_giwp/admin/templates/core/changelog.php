<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin changelog of the plugin.
 * 
 * @since      2.14.0
 */

?>

<div class="gi-toolkit-changelog">
	<?php foreach ( $parsed as $gi_toolkit_item_version => $gi_toolkit_item_content ) : ?>
		<div class="gi-toolkit-changelog__item">
			<div class="gi-toolkit-changelog__item__version">
				<div class="gi-toolkit-changelog__item__version__tag"><?php echo esc_html( $gi_toolkit_item_version ); ?></div>
				<div class="gi-toolkit-changelog__item__version__line">
					<div class="gi-toolkit-changelog__item__version__line__dot first"></div>
					<div class="gi-toolkit-changelog__item__version__line__dot second"></div>
				</div>
			</div>
			<div class="gi-toolkit-changelog__item__content">
				<?php foreach ( $gi_toolkit_item_content as $gi_toolkit_item ) :
					$gi_toolkit_type_c        = strtolower( $gi_toolkit_item['type'] );
					$gi_toolkit_is_new_module = $gi_toolkit_type_c == 'add';
					$gi_toolkit_is_global     = $gi_toolkit_item['module'] == '';

					$gi_toolkit_tag_svg   = 'module';
					$gi_toolkit_tag_text  = $gi_toolkit_item['module'];
					if ( $gi_toolkit_is_new_module ) {
						$gi_toolkit_tag_svg  = 'new-module';
						$gi_toolkit_tag_text = __( 'New module', 'gi-toolkit' );
					} elseif ( $gi_toolkit_is_global ) {
						$gi_toolkit_tag_svg  = 'global';
						$gi_toolkit_tag_text = __( 'Global', 'gi-toolkit' );
					}
				?>
					<div class="gi-toolkit-changelog__item__content__item <?php echo esc_attr( $gi_toolkit_tag_svg ); ?>">
						<div class="gi-toolkit-changelog__item__content__item__type">
							<div class="gi-toolkit-changelog__item__content__item__type__dot <?php echo esc_attr( $gi_toolkit_type_c ); ?>"></div>
							<div class="gi-toolkit-changelog__item__content__item__type__icon"><?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/changelog/type/' . $gi_toolkit_type_c . '.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?></div>
							<div class="gi-toolkit-changelog__item__content__item__type__text"><?php echo esc_html( $gi_toolkit_item['type'] ); ?></div>
						</div>

						<div class="gi-toolkit-changelog__item__content__item__text">
							<?php if ( $gi_toolkit_is_new_module && ! empty( $gi_toolkit_item['module'] ) ): ?>
								<?php echo esc_html( $gi_toolkit_item['module'] ); ?>
							<?php else: ?>
								<?php echo esc_html( $gi_toolkit_item['text'] ); ?>
							<?php endif; ?>
						</div>

						<div class="gi-toolkit-changelog__item__content__item__tag <?php echo esc_attr( $gi_toolkit_tag_svg ); ?>">
							<?php if ( 'new-module' == $gi_toolkit_tag_svg ): ?>
								<div class="gi-toolkit-changelog__item__content__item__tag__icon">
									<?php
										// phpcs:ignore WordPress.Security.EscapeOutput
										echo file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/changelog/' . $gi_toolkit_tag_svg . '.svg');
									?>
								</div>
							<?php else: ?>
								<div class="gi-toolkit-changelog__item__content__item__tag__icon"><?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/changelog/' . $gi_toolkit_tag_svg . '.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?></div>
							<?php endif; ?>
							<div class="gi-toolkit-changelog__item__content__item__tag__text"><?php echo esc_html( $gi_toolkit_tag_text ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
