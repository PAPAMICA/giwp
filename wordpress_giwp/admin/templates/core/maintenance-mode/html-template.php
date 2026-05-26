<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template that contains the maintenance mode HTML.
 * @since      2.9.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <title><?php echo esc_html( $gi_toolkit_title_text ); ?></title>
    <meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
    <meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, minimum-scale=1">
    <meta name="description" content="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"/>
    <meta http-equiv="X-UA-Compatible" content="<?php echo esc_attr( get_bloginfo( 'description' ) );?>"/>
    <meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">  
    <meta property="og:title" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"/>
    <meta property="og:type" content="Maintenance"/>
    <meta property="og:url" content="<?php echo esc_url( site_url() ); ?>"/>
    <meta property="og:description" content="<?php echo esc_attr( get_bloginfo( 'description' ) );?>"/>
	<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Expires" content="0">
    <?php if ( $gi_toolkit_countdown_status == '1' ): ?>
        <script>
            const gi_toolkit_maintenance_mode_countdown_end_date = "<?php echo esc_js( $gi_toolkit_countdown_end_date ); ?>";
        </script>
    <?php endif; ?>
    <style>
        :root {
            --text-color: <?php echo ! empty( $gi_toolkit_text_color ) ? esc_attr( $gi_toolkit_text_color ) : esc_attr( '#000000' ); ?>;
            --background-color: <?php echo ! empty( $gi_toolkit_background_color ) ? esc_attr( $gi_toolkit_background_color ) : esc_attr( '#FFFFFF' ); ?>;
            --logo-height: <?php echo ! empty( $gi_toolkit_logo_height ) ? esc_attr( $gi_toolkit_logo_height ) : esc_attr( '180' ); ?>px;
            --logo-width: <?php echo ! empty( $gi_toolkit_logo_width ) ? esc_attr( $gi_toolkit_logo_width ) : esc_attr( '180' ); ?>px;
            --countdown-text-color: <?php echo esc_attr( $gi_toolkit_countdown_text_color ); ?>;
            --countdown-background-color: <?php echo esc_attr( $gi_toolkit_countdown_background_color ); ?>;
        }
    </style>
	<?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
    <link rel="stylesheet" type="text/css" href="<?php echo esc_attr( GI_TOOLKIT_PLUGIN_URL . "admin/assets/build/core/maintenance-mode-template.css" ); ?>">
	<?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
    <script src="<?php echo esc_attr( GI_TOOLKIT_PLUGIN_URL . "admin/assets/build/core/maintenance-mode-template.js" ); ?>"></script>
    <link rel="pingback" href="<?php echo esc_url( get_bloginfo( 'pingback_url' ) ); ?>" />
</head>
 
<body class="gi-toolkit">

    <div class="gi-toolkit__container">

        <main class="gi-toolkit__main">

            <header class="gi-toolkit__header">

                <?php if ( ! empty( $gi_toolkit_logo ) ): ?>

                    <div class="gi-toolkit__header__logo">
						<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                        <img src="<?php echo esc_url( $gi_toolkit_logo ); ?>" alt="<?php esc_attr_e( 'site logo', 'gi-toolkit' ); ?>">
                    </div>
                    <div class="gi-toolkit__header__title screen-reader-text">
                        <h1><?php echo esc_html( $gi_toolkit_title_text ); ?></h1>
                    </div>

                <?php else: ?>

                    <div class="gi-toolkit__header__title">
                        <h1><?php echo esc_html( $gi_toolkit_title_text ); ?></h1>
                    </div>

                <?php endif; ?>

            </header>

            <div class="gi-toolkit__body">

                <h2 class="gi-toolkit__body__heading"><?php echo esc_html( $gi_toolkit_headline_text ); ?></h2>

                <div class="gi-toolkit__body__description">
                    <?php echo wp_kses_post( wpautop( stripslashes( $gi_toolkit_body_text ) ) ); ?>
                </div>
                
                <?php if ( $gi_toolkit_countdown_status == '1' ): ?>
                    <ul class="gi-toolkit__body__countdown">
                        <li><span id="days"></span>
                            <?php esc_html_e( 'Days', 'gi-toolkit' ); ?>
                        </li>
                        <li><span id="hours"></span>
                            <?php esc_html_e( 'Hours', 'gi-toolkit' ); ?>
                        </li>
                        <li><span id="minutes"></span>
                            <?php esc_html_e( 'Minutes', 'gi-toolkit' ); ?>
                        </li>
                        <li><span id="seconds"></span>
                            <?php esc_html_e( 'Seconds', 'gi-toolkit' ); ?>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>

        </main>

        <footer class="gi-toolkit__footer">
            <p><?php echo esc_html( $gi_toolkit_footer_text ); ?></p>
        </footer>

        <?php if ( ! empty( $gi_toolkit_background_image ) ): ?>

            <picture class="gi-toolkit__background">
                <source media="(max-width: 100vh)" srcset="<?php echo esc_url( $gi_toolkit_background_image ); ?>">
				<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                <img src="<?php echo esc_url( $gi_toolkit_background_image ); ?>">
            </picture>

        <?php endif; ?>

    </div>

</body>

</html>