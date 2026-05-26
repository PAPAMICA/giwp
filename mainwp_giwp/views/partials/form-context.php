<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Champs cachés requis pour les POST MainWP (sinon ?page= est perdu).
 *
 * @var string $current_page Slug page admin.
 * @var string $tab          Onglet actif.
 */
?>
<input type="hidden" name="page" value="<?php echo esc_attr( $current_page ); ?>" />
<?php if ( ! empty( $tab ) ) : ?>
	<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
<?php endif; ?>
