<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Maintenance Mode
 * Description: 
 * @since 1.3.0
 */
class Gi_Toolkit_Maintenance_Mode {

    private $option_id;
    private $header_title;
    public $nonce_action;
    private $settings;
    private $default_settings;
    private $bypass_param;
    public $preview_param;
	private $submenu_page_id;

    /**
     * Invoke the hooks
     */
    public function __construct() {

        $this->option_id       = GI_TOOLKIT_PLUGIN_SETTINGS . '_maintenance_mode';
        $this->nonce_action    = $this->option_id . '_action';
		$this->submenu_page_id = 'gi-toolkit-settings-maintenance-mode';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'template_include', array( $this, 'template_include' ), PHP_INT_MAX );
        add_action( 'admin_bar_menu', array( $this, 'render_toggle_button' ) );
        add_action( 'wp_ajax_gi_toolkit_maintenance_mode_adminbar_toggle', array( $this, 'change_maintenance_mode' ) );

        /**
         * Filter the disposable email domains.
         *
         * @since 2.3.0
         *
         * @param string   $bypass_param The bypass parameter.
         */
        $this->bypass_param = apply_filters( 'gi_toolkit/maintenance-mode/bypass-param', 'gi-toolkit-maintenance-bypass' );

		/**
		 * Filter the preview parameter.
		 *
		 * @since 2.9.0
		 *
		 * @param string   $preview_param The preview parameter.
		 */
        $this->preview_param = apply_filters( 'gi_toolkit/maintenance-mode/preview-param', 'gi_toolkit_maintenance_mode_preview' );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Maintenance Mode', 'gi-toolkit' );
    }

    /**
     * Template include
     */
    public function template_include( $template ) {
		
		$preview_nonce = sanitize_text_field( wp_unslash( $_GET[ $this->preview_param ] ?? '' ) );
		if ( gi_toolkit_is_pro() && wp_verify_nonce( $preview_nonce, $this->nonce_action ) ) {
			if ( file_exists( GI_TOOLKIT_PLUGIN_PATH . '/admin/templates/core/maintenance-mode/preview.php' ) ) {
				header( 'HTTP/1.1 503 Service Unavailable', true, 503 );
				return GI_TOOLKIT_PLUGIN_PATH . '/admin/templates/core/maintenance-mode/preview.php';
        	}
		}

		$this->settings = $this->get_settings();

		$enabled = $this->settings['enabled'] ?? '1';
		if ( '1' !== $enabled ) {
			return $template;
		}

		if ( gi_toolkit_is_pro() ) {
			$current_url         = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			$current_path        = wp_parse_url( $current_url, PHP_URL_PATH );
			$excluded_urls       = stripslashes( $this->settings['excluded_urls'] ?? '' );
			$excluded_urls_array = !empty($excluded_urls) ? explode( "\n", $excluded_urls ) : array();

			foreach ( $excluded_urls_array as $excluded_url ) {
				$excluded_url      = trim( $excluded_url );
				$excluded_url      = trailingslashit( wp_unslash( $excluded_url ) );
				$excluded_url_path = wp_parse_url( $excluded_url, PHP_URL_PATH );
	
				if ( ! $excluded_url_path ) {
					continue;
				}
	
				if ( $excluded_url_path === $current_path ) {
					return $template;
				}
			}
		}
        
        if( $this->settings['bypass_link_status'] === '1' ){
			
			$cookie = sanitize_text_field( wp_unslash( $_COOKIE['gi_toolkit_maintenance_bypass'] ?? '' ) );
            if( !empty($cookie) && $cookie === $this->settings['bypass_link_token'] ){
                return $template;
            }
    
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if( isset($_GET[$this->bypass_param]) && $_GET[$this->bypass_param] === $this->settings['bypass_link_token'] ){
                /**
                 * Filter the bypass cookie validity.
                 *
                 * @since 2.3.0
                 *
                 * @param int   $cookie_validity The bypass cookie validity.
                 */
                $cookie_validity = apply_filters( 'gi_toolkit/maintenance-mode/bypass-cookie-validity', DAY_IN_SECONDS );
                
                setcookie( 
                    'gi_toolkit_maintenance_bypass', 
					//phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    sanitize_text_field( wp_unslash( $_GET[$this->bypass_param] ) ), 
                    time() + $cookie_validity, 
                    COOKIEPATH, 
                    COOKIE_DOMAIN 
                );
                
                return $template;
            }
        }

        if ( ! is_user_logged_in() && ! is_admin() ) {

            if( gi_toolkit_is_pro() && !empty($this->settings['countdown_status']) ) {
                $countdown_end_date = $this->settings['countdown_end_date'] ?? time();
                if( time() >= $countdown_end_date ){
                    $this->settings['enabled']          = '0';
                    $this->settings['countdown_status'] = '0';
                    $this->save_settings( $this->settings );
                    return $template;
                }
            }

            if ( file_exists( GI_TOOLKIT_PLUGIN_PATH . '/admin/templates/core/maintenance-mode/index.php' ) ) {
				header( 'HTTP/1.1 503 Service Unavailable', true, 503 );
                $template =  GI_TOOLKIT_PLUGIN_PATH . '/admin/templates/core/maintenance-mode/index.php';
            }
        }

        return $template;
    }

	/**
	 * Render toggle button
	 */
	public function render_toggle_button( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$enabled          = $this->settings['enabled'] ?? $this->default_settings['enabled'];
		$show_in_adminbar = $this->settings['show_in_adminbar']['value'] ?? $this->default_settings['show_in_adminbar']['value'];

		if ( 'hide' === $show_in_adminbar ) {
			return;
		}

		$adminbar_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/maintenance-mode-adminbar.asset.php' );
        wp_enqueue_style( 'Gi_Toolkit_maintenance_mode_adminbar', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/maintenance-mode-adminbar.css', array(), $adminbar_assets['version'], 'all' );
        wp_enqueue_script( 'Gi_Toolkit_maintenance_mode_adminbar', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/maintenance-mode-adminbar.js', $adminbar_assets['dependencies'], $adminbar_assets['version'], true );
		wp_localize_script( 'Gi_Toolkit_maintenance_mode_adminbar', 'gi_toolkit_maintenance_mode_adminbar', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( $this->nonce_action ),
			'i18n'    => array(
				'turnoff' => esc_html__( 'Are you sure you want to turn off maintenance mode?', 'gi-toolkit' ),
				'turnon'  => esc_html__( 'Are you sure you want to turn on maintenance mode?', 'gi-toolkit' ),
			),
		));

		$is_pro = gi_toolkit_is_pro();
		if ( $is_pro ) {
			$bypass_link_status = $this->settings['bypass_link_status'] ?? '0';
        	$bypass_link_token  = $this->settings['bypass_link_token'] ?? md5(time());

			if ( $enabled === '1' && $bypass_link_status === '1' ){
				$wp_admin_bar->add_menu( array(
					'id'     => 'gi-toolkit-maintenance-mode-bypass',
					'parent' => 'top-secondary',
					'title'  => $this->copy_bypass_link( $bypass_link_token ),
				) );
			}
		}

		$content = '';
		if ( 'show_normal' === $show_in_adminbar ) {
			$content = $this->normal_toggle( $enabled );
		} elseif ( 'show_compact' === $show_in_adminbar ) {
			$content = $this->compact_toggle( $enabled );
		}

		$wp_admin_bar->add_menu( array(
			'id'     => 'gi-toolkit-maintenance-mode',
			'parent' => 'top-secondary',
			'title'  => $content,
		) );
	}

	/**
	 * Change maintenance mode
	 */
	public function change_maintenance_mode() {
		gi_toolkit_ajax_require_cap( 'manage_options', $this->nonce_action );

		$status   = sanitize_text_field( wp_unslash( $_POST['status'] ?? '0' ) );
		$settings = $this->get_settings();
		$settings['enabled'] = $status;

		$this->clear_cache();
		$this->save_settings( $settings );

		wp_send_json_success( array( 'message' => __( 'Maintenance mode has been turned on.', 'gi-toolkit' ) ) );
	}

	/**
	 * Copy bypass link HTML
	 */
	private function copy_bypass_link( $bypass_link_token ) {

        if( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) === false ) {
            $current_url = home_url();
        } else {
            $current_url = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
        }
        $url = esc_url_raw( add_query_arg( $this->bypass_param, $bypass_link_token, $current_url ) );

		ob_start();
		?>
			<div class="gi_toolkit-maintenance-mode-bypass-link">
				<span class="gi_toolkit-maintenance-mode-bypass-link-btn" data-content="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Copy bypass link', 'gi-toolkit' ); ?></span>
				<span class="gi_toolkit-maintenance-mode-bypass-link-icon"><?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/check.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?></span>
			</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Normal toggle HTML
	 */
	private function normal_toggle( $enabled ) {
		ob_start();

		?>
			<div class="gi_toolkit-maintenance-mode-normal <?php echo esc_attr( $enabled ? 'active' : '' ); ?>" data-enabled="<?php echo esc_attr( $enabled ); ?>">
				<span class="gi_toolkit-maintenance-mode-normal-title"><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->submenu_page_id ) ); ?>"><?php esc_html_e( 'Maintenance Mode', 'gi-toolkit' ); ?>:</a></span>
				<span class="gi_toolkit-maintenance-mode-normal-btn off"><?php esc_html_e( 'OFF', 'gi-toolkit' ); ?></span>
				<span class="gi_toolkit-maintenance-mode-normal-btn on"><?php esc_html_e( 'ON', 'gi-toolkit' ); ?></span>
			</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Compact toggle HTML
	 */
	private function compact_toggle( $enabled ) {
		ob_start();

		?>
			<div class="gi_toolkit-maintenance-mode-compact <?php echo esc_attr( $enabled ? 'active' : '' ); ?>" data-enabled="<?php echo esc_attr( $enabled ); ?>">
				<?php if ( $enabled ) : ?>
					<span class="gi_toolkit-maintenance-mode-compact-btn on"><?php esc_html_e( 'Maintenance', 'gi-toolkit' ); ?></span>
				<?php else: ?>
					<span class="gi_toolkit-maintenance-mode-compact-btn off"><?php esc_html_e( 'Live', 'gi-toolkit' ); ?></span>
				<?php endif; ?>
			</div>
		<?php

		return ob_get_clean();
	}

    /**
     * get_settings
     */
    public function get_settings(){
        $this->default_settings = $this->get_default_settings();
        $settings = get_option( $this->option_id, $this->default_settings );
        $settings = wp_parse_args( $settings, $this->default_settings );
        return $settings;
    }

    /**
     * Save settings
     */
    public function save_settings( $new_settings ) {

		update_option( $this->option_id, $new_settings );
    }

    /**
     * Add a submenu
     */
    public function add_submenu(){

        Gi_Toolkit_Settings::add_submenu_page(
            'gi-toolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            $this->submenu_page_id, 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     */
    public function render_submenu() {

        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        $submenu_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/maintenance-mode.asset.php' );
        wp_enqueue_style( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/maintenance-mode.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/maintenance-mode.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'Gi_Toolkit_submenu', 'gi_toolkit_maintenance_mode', array(
			'nonce'        => wp_create_nonce( $this->nonce_action ),
			'previewParam' => $this->preview_param,
			'siteUrl'      => site_url(),
		));

        include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[$this->option_id] ?? array() ) );

			$this->clear_cache();
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

    /**
     * sanitize_settings
     * 
     * @return array
     */
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {

                case 'enabled':
                case 'title_text':
                case 'headline_text':
                case 'footer_text':
                case 'logo_height':
                case 'logo_width':
                    $sanitized_settings[$settings_key] = wp_unslash( sanitize_text_field( $new_settings[$settings_key] ?? '' ) );
                    break;
                case 'body_text':
                    $sanitized_settings[$settings_key] = sanitize_textarea_field( $new_settings[$settings_key] ?? '' );
                    break;
                case 'background_color':
                case 'text_color':
                case 'countdown_text_color':
                case 'countdown_background_color':
                    $sanitized_settings[$settings_key] = sanitize_hex_color( $new_settings[$settings_key] ?? '' );
                    break;
                case 'logo':
                case 'background_image':
                    $sanitized_settings[$settings_key] = esc_url_raw( $new_settings[$settings_key] ?? '' );
                break;
				case 'show_in_adminbar':
					$sanitized_settings[$settings_key]['value'] = sanitize_text_field( $new_settings[$settings_key]['value'] ?? $this->default_settings[$settings_key]['value'] );
				break;
                case 'countdown_status':
                    $is_pro = gi_toolkit_is_pro();
                    $sanitized_settings[$settings_key] = $is_pro ? sanitize_text_field( $new_settings[$settings_key] ?? '0' ) : '0';
                break;
                case 'countdown_end_date':
                    $date_string = sanitize_text_field( $new_settings[$settings_key] ?? '' );
                    $date        = new DateTime( $date_string,  wp_timezone() );
                    $timestamp   = $date->getTimestamp() ?? '';
                    $sanitized_settings[$settings_key] = $timestamp;
                break;
                case 'bypass_link_status':
                    $is_pro = gi_toolkit_is_pro();
                    $sanitized_settings[$settings_key] = $is_pro ? sanitize_text_field( $new_settings[$settings_key] ?? '0' ) : '0';
                break;
                case 'bypass_link_token':
                    $is_pro = gi_toolkit_is_pro();
                    $sanitized_settings[$settings_key] = $is_pro ? sanitize_text_field( $new_settings[$settings_key] ?? md5(time()) ) : md5(time());
                break;               
				case 'excluded_urls':
					$sanitized_settings[ $settings_key ] = sanitize_textarea_field( stripslashes( $new_settings[ $settings_key ] ?? $settings_value ) );
				break; 
            }
        }

        return $sanitized_settings;
    }

	/**
	 * Clear the cache after changing the settings
	 */
	public function clear_cache() {

		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// LiteSpeed Cache
		if ( function_exists( 'litespeed_purge_all' ) ) {
			//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'litespeed_purge_all' );
		}

		// SiteGround Optimizer
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'sg_cachepress_purge_cache' );
		}

		// Clear any other known cache plugins
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
			'enabled'          => '1',
			'show_in_adminbar' => array(
				'value'   => 'show_normal',
				'options' => array(
					'hide'         => __( 'Don\'t see in admin bar', 'gi-toolkit' ),
					'show_normal'  => __( 'Show OFF / ON in admin bar', 'gi-toolkit' ),
					'show_compact' => __( 'Show compact toggle in admin bar', 'gi-toolkit' ),
				),
			),
            'title_text'                 => __( 'Site is undergoing maintenance', 'gi-toolkit' ),
            'headline_text'              => __( 'Maintenance Mode', 'gi-toolkit' ),
            'body_text'                  => __( 'Site will be available soon. Thank you for your patience!', 'gi-toolkit' ),
            'footer_text'                => sprintf( '&copy; %s %s', get_bloginfo('name'), wp_date('Y') ),
            'background_color'           => '#000000',
            'text_color'                 => '#ffffff',
            'logo'                       => '',
            'background_image'           => '',
            'logo_height'                => '180',
            'logo_width'                 => '180',
            'countdown_status'           => '0',
            'countdown_end_date'         => strtotime( '+1 hour' ),
            'countdown_text_color'       => '#000000',
            'countdown_background_color' => '#ffffff',
            'bypass_link_status'         => '0',
            'bypass_link_token'          => md5(time()),
			'excluded_urls'              => '',
        );
    }

    /**
     * Add the submenu content
     */
    private function submenu_content() {
        $this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();
        
        $is_pro = gi_toolkit_is_pro();
        
		$show_in_adminbar_options = $this->default_settings['show_in_adminbar']['options'];
        
        $url                        = home_url() . '/?' . $this->bypass_param . '=';
        $image_placeholder          = GI_TOOLKIT_PLUGIN_URL . 'admin/images/placeholder.svg';
        $enabled                    = $this->settings['enabled'] ?? $this->default_settings['enabled'];
		$show_in_adminbar           = $this->settings['show_in_adminbar']['value'] ?? $this->default_settings['show_in_adminbar']['value'];
        $title_text                 = $this->settings['title_text'] ?? '';
        $headline_text              = $this->settings['headline_text'] ?? '';
        $body_text                  = $this->settings['body_text'] ?? '';
        $footer_text                = $this->settings['footer_text'] ?? '';
        $background_color           = $this->settings['background_color'] ?? '';
        $text_color                 = $this->settings['text_color'] ?? '';
        $logo                       = $this->settings['logo'] ?? '';
        $background_image           = $this->settings['background_image'] ?? '';
        $logo_height                = $this->settings['logo_height'] ?? '';
        $logo_width                 = $this->settings['logo_width'] ?? '';
        $countdown_status           = $is_pro ? $this->settings['countdown_status'] ?? '0' : '0';
        $countdown_end_date         = !empty($this->settings['countdown_end_date']) ? wp_date( 'Y-m-d H:i', $this->settings['countdown_end_date'] ) : '';
        $countdown_min_date         = wp_date( 'Y-m-d H:i', strtotime( '+1 minute' ) );
        $countdown_text_color       = $this->settings['countdown_text_color'] ?? '';
        $countdown_background_color = $this->settings['countdown_background_color'] ?? '';
        $bypass_link_status         = $is_pro ? $this->settings['bypass_link_status'] ?? '0' : '0';
        $bypass_link_token          = $is_pro ? $this->settings['bypass_link_token'] ?? md5(time()) : '';

        ?>
            <div class="gi-toolkit__section">
                <div class="gi-toolkit__section__desc"><?php esc_html_e('Show a customizable maintenance page on the frontend while performing a brief maintenance to your site. Logged-in administrators can still view the site as usual.', 'gi-toolkit'); ?></div>
                <div class="gi-toolkit__section__body">

					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e("Maintenance mode status", 'gi-toolkit'); ?></div>
						<div class="gi-toolkit__section__body__item__content">
							<label class="gi-toolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[enabled]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[enabled]' ); ?>" value="1" <?php checked( $enabled, '1' ); ?>>
								<span class="gi-toolkit__toggle__slider round"></span>
							</label> 
						</div>
                    </div>

					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e("Show in admin bar", 'gi-toolkit'); ?></div>
						<div class="gi-toolkit__section__body__item__content">
							<div class="gi-toolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[show_in_adminbar][value]' ); ?>">
                                    <?php foreach ( $show_in_adminbar_options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $show_in_adminbar, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
						</div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Page Title', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[title_text]' ); ?>" value="<?php echo esc_attr( $title_text ); ?>" style="width: 400px;">
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Headline', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[headline_text]' ); ?>" value="<?php echo esc_attr( $headline_text ); ?>" style="width: 400px;">
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Body Text', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__wysiwyg">
								<?php
									wp_editor( $body_text, $this->option_id . '_body_text', array(
										'wpautop'       => true,
										'media_buttons' => false,
										'textarea_name' => $this->option_id . '[body_text]',
										'textarea_rows' => 3,
									) );
								?>
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Footer Text', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[footer_text]' ); ?>" value="<?php echo esc_attr( $footer_text ); ?>" style="width: 400px;">
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Background Color', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[background_color]' ); ?>" value="<?php echo esc_attr( $background_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Text Color', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[text_color]' ); ?>" value="<?php echo esc_attr( $text_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Logo', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__upload-image">
								<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                                <img class="gi-toolkit__upload-image__preview" src="<?php echo empty( $logo ) ? esc_url( $image_placeholder ) : esc_url( $logo ); ?>" data-default="<?php echo esc_attr( $image_placeholder ); ?>">
                                <div class="gi-toolkit__upload-image__actions">
                                    <a class="gi-toolkit__upload-image__upload" href="javascript:void(0);"><?php esc_html_e( "Upload", 'gi-toolkit' ); ?></a>
                                    <a class="gi-toolkit__upload-image__reset <?php echo esc_attr( empty( $logo ) ? '' : 'show' ); ?>" href="javascript:void(0);">X</a>
                                </div>
                                <input class="gi-toolkit__upload-image__input" type="hidden" name="<?php echo esc_attr( $this->option_id . '[logo]' ); ?>" value="<?php echo esc_attr( $logo ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Background Image', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__upload-image">
								<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                                <img class="gi-toolkit__upload-image__preview" src="<?php echo empty( $background_image ) ? esc_url( $image_placeholder ) : esc_url( $background_image ); ?>" data-default="<?php echo esc_attr( $image_placeholder ); ?>">
                                <div class="gi-toolkit__upload-image__actions">
                                    <a class="gi-toolkit__upload-image__upload" href="javascript:void(0);"><?php esc_html_e( "Upload", 'gi-toolkit' ); ?></a>
                                    <a class="gi-toolkit__upload-image__reset <?php echo empty( $background_image ) ? '' : 'show'; ?>" href="javascript:void(0);">X</a>
                                </div>
                                <input class="gi-toolkit__upload-image__input" type="hidden" name="<?php echo esc_attr( $this->option_id . '[background_image]' ); ?>" value="<?php echo esc_attr( $background_image ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Logo Size', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="number" name="<?php echo esc_attr( $this->option_id . '[logo_height]' ); ?>" value="<?php echo esc_attr( $logo_height ); ?>">
                                <input type="number" name="<?php echo esc_attr( $this->option_id . '[logo_width]' ); ?>" value="<?php echo esc_attr( $logo_width ); ?>">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="gi-toolkit__section">
                <div class="gi-toolkit__section__desc">
                    <?php esc_html_e("Show a countdown timer on the maintenance page. When the countdown ends, the maintenance page will be automatically turned off.", 'gi-toolkit'); ?>
                </div>
                <div class="gi-toolkit__section__body">
                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e("Countdown status", 'gi-toolkit'); ?></div>
						<div class="gi-toolkit__section__body__item__content">
							<label class="gi-toolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[countdown_status]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[countdown_status]' ); ?>" value="1" <?php checked( $countdown_status, '1' ); ?>>
								<span class="gi-toolkit__toggle__slider round"></span>
							</label> 
						</div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e("End date", 'gi-toolkit'); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="datetime-local" name="<?php echo esc_attr( $this->option_id . '[countdown_end_date]' ); ?>" value="<?php echo esc_attr( $countdown_end_date ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Countdown Text Color', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[countdown_text_color]' ); ?>" value="<?php echo esc_attr( $countdown_text_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>
                    
                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Countdown Background Color', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text">
                                <input type="text" name="<?php echo esc_attr( $this->option_id . '[countdown_background_color]' ); ?>" value="<?php echo esc_attr( $countdown_background_color ); ?>" class="wp-color-picker"/>
                            </div>
                        </div>
                    </div>

                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Preview', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__button">
								<button id="JS-Maintenance-Mode-Preview" class="secondary" type="button"><?php esc_html_e( 'Preview', 'gi-toolkit' ); ?></button>
								<a id="JS-Maintenance-Mode-Preview-Link" href="javascript:void(0);" target="_blank"></a>
							</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="gi-toolkit__section">
                <div class="gi-toolkit__section__desc">
                    <?php esc_html_e("The bypass link allows access to the website even if the site is in maintenance mode.", 'gi-toolkit'); ?>
                </div>
                <div class="gi-toolkit__section__body">
                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e("Bypass link status", 'gi-toolkit'); ?></div>
						<div class="gi-toolkit__section__body__item__content">
							<label class="gi-toolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[bypass_link_status]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[bypass_link_status]' ); ?>" value="1" <?php checked( $bypass_link_status, '1' ); ?>>
								<span class="gi-toolkit__toggle__slider round"></span>
							</label> 
						</div>
                    </div>
                    
                    <div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Bypass link', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                            <div class="gi-toolkit__input-text slug-url">
                                <div>
									<code>
                                    	<?php echo esc_url( $url ); ?>
									</code>
                                </div>
                                <div>
									<input type="hidden" class="home-url" value="<?php echo esc_url( $url ); ?>">
                                    <input class="slug-input" type="text" name="<?php echo esc_attr( $this->option_id . '[bypass_link_token]' ); ?>" value="<?php echo esc_attr( $bypass_link_token ); ?>">
                                </div>
                                <button class="copy-button">
									<?php echo wp_kses( file_get_contents(GI_TOOLKIT_PLUGIN_PATH . 'admin/svg/copy.svg'), gi_toolkit_allowed_tags_for_svg_files() ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="gi-toolkit__section">
                <div class="gi-toolkit__section__desc">
                    <?php esc_html_e("Enter URLs to exclude from maintenance mode, one per line.", 'gi-toolkit'); ?>
                </div>
                <div class="gi-toolkit__section__body">
					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'The Exluded URLs', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
							<div class="gi-toolkit__textarea">
                                <textarea name="<?php echo esc_attr( $this->option_id ); ?>[excluded_urls]" cols="50" rows="3" style="width: 400px;"><?php echo esc_textarea( stripslashes( $this->settings['excluded_urls'] ?? '') ); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }
}
