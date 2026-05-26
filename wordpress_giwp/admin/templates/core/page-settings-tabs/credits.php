<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The credits tap of the settings page.
 * 
 * @since      1.0.0
 */
?>

<div class="gi-toolkit__body__sections__item hide-in-all" data-key="credits">
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Credits', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description">
            <?php
                wp_kses(
                	printf(
						/* translators: %s: Genevois Informatique link */
                        esc_html__( 'This plugin is proudly developed by the %s team, passionate about creating innovative solutions to improve and enrich the WordPress experience.', 'gi-toolkit' ),
						'<a href="https://genevois-informatique.ch/" target="_blank">Genevois Informatique</a>',
                    ),
					array( 'a' => array( 'href' => array() ) ),
                );
            ?>
        </div>
    </div>
    <div class="gi-toolkit__body__sections__item__space"></div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Support our work', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description">
            <?php esc_html_e( "If this plugin contributes to your success or simplifies your use of WordPress, consider supporting our work. Your contribution helps us maintain the project, develop new features, and provide ongoing support. Here's how you can contribute:", 'gi-toolkit' ); ?>
            <ul class="custom-list">
                <li>
                    <?php
                        wp_kses(
                        	printf(
								/* translators: %s: feedback link */
                                esc_html__( '%s - Your comments are valuable in improving our solutions.', 'gi-toolkit' ),
								'<a href="https://genevois-informatique.ch/" target="_blank">' . esc_html__( 'Offer your feedback', 'gi-toolkit' ) . '</a>',
                            ),
							array( 'a' => array( 'href' => array() ) ),
                        );
                    ?>
                </li>
            </ul>
        </div>
    </div>
    <div class="gi-toolkit__body__sections__item__space"></div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__title"><?php esc_html_e( 'Explore our other plugins', 'gi-toolkit' ); ?></div>
    </div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description">
            <?php
                wp_kses(
                	printf(
						/* translators: %s: wordpress.org link */
                        esc_html__( "We have a variety of plugins available to meet different needs and features on WordPress. Check them out at %s and expand your website's capabilities with reliable, easy-to-use tools.", 'gi-toolkit' ),
						'<a href="https://wordpress.org/plugins/" target="_blank">wordpress.org</a>',
                    ),
					array( 'a' => array( 'href' => array() ) ),
                );
            ?>
        </div>
    </div>
    <div class="gi-toolkit__body__sections__item__top">
        <div class="gi-toolkit__body__sections__item__description">
            <?php esc_html_e( "We are proud to contribute to the WordPress community and are committed to providing quality solutions, accessible to everyone. A big thank you to all our users and supporters!", 'gi-toolkit' ); ?>
        </div>
    </div>
</div>
