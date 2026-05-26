<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The credientials tap of the settings page.
 * 
 * @since      2.8.0
 */

$gi_toolkit_settings = get_option( 'gi_toolkit_credentials_tab', array() );
?>

<div class="gi-toolkit__body__sections__item hide-in-all" data-key="credentials">

	<?php foreach ( gi_toolkit_ai_modules() as $gi_toolkit_ai_module_key => $gi_toolkit_ai_module ) : 
		$gi_toolkit_api_key    = $gi_toolkit_settings[$gi_toolkit_ai_module_key] ?? '';
		$gi_toolkit_has_key    = ! empty( $gi_toolkit_api_key );
		$gi_toolkit_masked_key = $gi_toolkit_has_key && strlen( $gi_toolkit_api_key ) > 8 ? substr( $gi_toolkit_api_key, 0, 4 ) . str_repeat( '•', min( strlen( $gi_toolkit_api_key ) - 8, 40 ) ) . substr( $gi_toolkit_api_key, -4 ) : $gi_toolkit_api_key;
	?>

		<div class="gi-toolkit__body__sections__item__top">
			<div class="gi-toolkit__body__sections__item__title">
				<img class="ai-logo" src="<?php echo esc_url( GI_TOOLKIT_PLUGIN_URL . 'admin/images/' . $gi_toolkit_ai_module_key . '.png' ); ?>" alt="<?php echo esc_attr( $gi_toolkit_ai_module['name'] ?? '' ); ?>" height="20">
				<?php echo esc_html( $gi_toolkit_ai_module['name'] ?? '' ); ?>
			</div>
		</div>
		<div class="gi-toolkit__body__sections__item__bottom">
			<div class="api-key-field-wrapper">
				<input 
					type="text" 
					class="api-key-input <?php echo $gi_toolkit_has_key ? 'has-value' : ''; ?>" 
					name="gi_toolkit_credentials_tab[<?php echo esc_attr( $gi_toolkit_ai_module_key ); ?>]" 
					value="<?php echo esc_attr( $gi_toolkit_api_key ); ?>"
					data-masked="<?php echo esc_attr( $gi_toolkit_masked_key ); ?>"
					<?php echo $gi_toolkit_has_key ? 'readonly' : ''; ?>
					placeholder="<?php esc_attr_e( 'Enter your API Key', 'gi-toolkit' ); ?>">
				<?php if ( $gi_toolkit_has_key ) : ?>
					<button type="button" class="edit-api-key-btn" data-key="<?php echo esc_attr( $gi_toolkit_ai_module_key ); ?>" title="<?php esc_attr_e( 'Modifier la clé API', 'gi-toolkit' ); ?>">
						<?php echo wp_kses( file_get_contents( GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/edit.svg' ), gi_toolkit_allowed_tags_for_svg_files() ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<div class="gi-toolkit__body__sections__item__space"></div>
	<?php endforeach; ?>

</div>
