<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Advanced Debug Mode
 * Description: Enable WordPress debug mode with logging.
 * @since 2.14.0
 */
class Gi_Toolkit_Advanced_Debug_Mode {

    const MODULE_ID = 'Advanced Debug Mode';

    private $option_id;
    private $header_title;
    public $nonce_action;
    private $settings;
    private $default_settings;
    private $submenu_page_id;

    /**
     * Invoke the hooks
     */
    public function __construct() {

        $this->option_id       = GI_TOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode';
        $this->nonce_action    = $this->option_id . '_action';
        $this->submenu_page_id = 'gi-toolkit-settings-advanced-debug-mode';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'gi_toolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );
        add_action( 'wp_ajax_gi_toolkit_get_debug_log', array( $this, 'ajax_get_debug_log' ) );
        add_action( 'wp_ajax_gi_toolkit_delete_debug_log', array( $this, 'ajax_delete_debug_log' ) );
        add_filter( 'gi_toolkit/folders', array( $this, 'create_folders' ) );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Advanced Debug Mode', 'gi-toolkit' );
    }

    /**
     * activate
     *
     * @return void
     */
    public static function activate() {
        require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        
        // Enable WP_DEBUG
        Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG', true );
        
        // Enable WP_DEBUG_LOG
        Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', true );
        
        // Disable WP_DEBUG_DISPLAY
        Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', false );
        
        // Initialize default settings
        $default_settings = array(
            'protect_logs'      => '0',
            'display_errors'    => '0',
            'custom_log_folder' => '0',
            'daily_logs'        => '0',
        );
        update_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', $default_settings );
    }

    /**
     * deactivate
     *
     * @return void
     */
    public static function deactivate() {
        global $is_apache, $is_nginx;

        require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        
        // Disable WP_DEBUG
        Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG', false );
        
        // Disable WP_DEBUG_LOG
        Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', false );
        
        // Disable WP_DEBUG_DISPLAY
        Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', false );

        // Remove htaccess protection if Apache
        if ( $is_apache ) {
            require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            Gi_Toolkit_Htaccess::remove( self::MODULE_ID );
        }

        // Delete settings
        delete_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode' );
    }

    /**
     * create_folders
     *
     * @param  array $folders
     * @return array
     */
    public function create_folders( $folders ) {
        $folders['gi-toolkit']['debug-logs'] = array();
        return $folders;
    }

    /**
     * nginx_code_snippets
     *
     * @param  array $code_snippets
     * @return array
     */
    public function nginx_code_snippets( $code_snippets ) {
        global $is_nginx;

        if ( $is_nginx && gi_toolkit_is_pro() ) {
            $settings = $this->get_settings();
            $protect_logs = $settings['protect_logs'] ?? '0';

            if ( '1' === $protect_logs ) {
                $code_snippets[ self::MODULE_ID ] = self::get_raw_content_nginx();
            }
        }

        return $code_snippets;
    }

    /**
     * Content of the .htaccess file
     */
    private static function get_raw_content_htaccess() {
        $settings           = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', array() );
        $daily_logs         = $settings['daily_logs'] ?? '0';
        $custom_log_folder  = $settings['custom_log_folder'] ?? '0';
        
        if ( gi_toolkit_is_pro() && '1' === $daily_logs && $custom_log_folder === '1' ) {
            $content = "<FilesMatch \"^debug-[0-9]{4}-[0-9]{2}-[0-9]{2}\\.log$\">\n";
            $content .= "\tOrder allow,deny\n";
            $content .= "\tDeny from all\n";
            $content .= "</FilesMatch>";
        } else {
            $log_path = self::get_debug_log_path();
            $log_filename = basename( $log_path );
            
            $content = "<Files " . $log_filename . ">\n";
            $content .= "\tOrder allow,deny\n";
            $content .= "\tDeny from all\n";
            $content .= "</Files>";
        }

        return trim( $content );
    }

    /**
     * Content of the nginx.conf file
     */
    private static function get_raw_content_nginx() {
        $settings           = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', array() );
        $daily_logs         = $settings['daily_logs'] ?? '0';
        $custom_log_folder  = $settings['custom_log_folder'] ?? '0';
        
        // If daily logs is enabled, use regex pattern to match all dated log files
        if ( gi_toolkit_is_pro() && '1' === $daily_logs && $custom_log_folder === '1' ) {
            $base_path = str_replace( ABSPATH, '/', gi_toolkit_folders() ) . '/debug-logs';
            $base_path = str_replace( '\\', '/', $base_path );
            $escaped_path = str_replace( '/', '\\/', $base_path );
            
            $content  = "location ~* " . $escaped_path . "/debug-[0-9]{4}-[0-9]{2}-[0-9]{2}\\.log$ {";
            $content .= "\n\tdeny all;";
            $content .= "\n}";
        } else {
            $log_path = self::get_debug_log_path();
            // Convert absolute path to relative URL path
            $relative_path = str_replace( ABSPATH, '/', $log_path );
            $relative_path = str_replace( '\\', '/', $relative_path ); // Windows compatibility
            $escaped_path = preg_quote( $relative_path, '/' );
            $escaped_path = str_replace( '/', '\\/', $escaped_path );
            
            $content  = "location ~* " . $escaped_path . "$ {";
            $content .= "\n\tdeny all;";
            $content .= "\n}";
        }

        return trim( $content );
    }

    /**
     * get_settings
     */
    public function get_settings() {
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
    public function add_submenu() {
        Gi_Toolkit_Settings::add_submenu_page(
            'gi-toolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            $this->submenu_page_id,
            array( $this, 'render_submenu' ),
            null
        );
    }

    /**
     * Render the submenu
     */
    public function render_submenu() {
        $this->settings = $this->get_settings();

        $submenu_assets = include( GI_TOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/advanced-debug-mode.asset.php' );
        wp_enqueue_style( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/advanced-debug-mode.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'Gi_Toolkit_submenu', GI_TOOLKIT_PLUGIN_URL . 'admin/assets/build/core/advanced-debug-mode.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
        
        // Pass variables to JavaScript
        wp_localize_script( 'Gi_Toolkit_submenu', 'giToolkitDebugMode', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( $this->nonce_action ),
            'isPro' => gi_toolkit_is_pro(),
            'i18n' => array(
                'loadingLogs' => __( 'Loading logs...', 'gi-toolkit' ),
                'displayCleared' => __( 'Display cleared. Start streaming to view logs...', 'gi-toolkit' ),
                'confirmDelete' => __( 'Are you sure you want to permanently delete the debug.log file?', 'gi-toolkit' ),
                'logDeleted' => __( 'Log file deleted successfully.', 'gi-toolkit' ),
                'error' => __( 'Error: ', 'gi-toolkit' ),
                'unknownError' => __( 'Unknown error', 'gi-toolkit' ),
                'deleteFailed' => __( 'Failed to delete log file.', 'gi-toolkit' ),
                'fetchError' => __( '[ERROR] Failed to fetch logs', 'gi-toolkit' ),
            )
        ) );

        include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     */
    public function save_submenu() {
        global $is_apache;

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

        if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );

            require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
            $display_errors = $new_settings['display_errors'] ?? '0';
            $custom_log_folder = $new_settings['custom_log_folder'] ?? '0';
            $daily_logs = $new_settings['daily_logs'] ?? '0';
            
            Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG', true );
            
            // Handle custom log folder
            if ( gi_toolkit_is_pro() && '1' === $custom_log_folder ) {
                $base_path = gi_toolkit_folders() . '/debug-logs';
                
                // Handle daily logs (only works with custom log folder)
                if ( '1' === $daily_logs ) {
                    $custom_log_path = "'" . $base_path . "/debug-' . date( 'Y-m-d' ) . '.log'";
                    Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', $custom_log_path, null, true );
                } else {
                    $custom_log_path = $base_path . '/debug.log';
                    Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', $custom_log_path );
                }
                
            } else {
                Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_LOG', true );
            }
            
            Gi_Toolkit_WP_Config::replace_or_add_constant( 'WP_DEBUG_DISPLAY', ( '1' === $display_errors ) );

            // Handle log protection for Apache
            if ( gi_toolkit_is_pro() && $is_apache ) {
                $protect_logs = $new_settings['protect_logs'] ?? '0';
                require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';

                if ( '1' === $protect_logs ) {
                    Gi_Toolkit_Htaccess::add( self::get_raw_content_htaccess(), self::MODULE_ID );
                } else {
                    Gi_Toolkit_Htaccess::remove( self::MODULE_ID );
                }
            }

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
    public function sanitize_settings( $new_settings ) {
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        $sanitized_settings['protect_logs']      = sanitize_text_field( $new_settings['protect_logs'] ?? '0' );
        $sanitized_settings['display_errors']    = sanitize_text_field( $new_settings['display_errors'] ?? '0' );
        $sanitized_settings['custom_log_folder'] = sanitize_text_field( $new_settings['custom_log_folder'] ?? '0' );
        $sanitized_settings['daily_logs']       = sanitize_text_field( $new_settings['daily_logs'] ?? '0' );

        return $sanitized_settings;
    }

    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings() {
        if ( $this->default_settings !== null ) {
            return $this->default_settings;
        }

        return array(
            'protect_logs'      => '0',
            'display_errors'    => '0',
            'custom_log_folder' => '0',
            'daily_logs'        => '0',
        );
    }

    /**
     * Submenu content
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();

        ?>
            <div class="gi-toolkit__section">
                <div class="gi-toolkit__section__desc">
					<?php esc_html_e( "Enable WordPress debug mode with logging. WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY constants are managed automatically.", 'gi-toolkit'); ?>
				</div>
                <div class="gi-toolkit__section__body">

					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Debug Mode Status', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
                                <?php
								// Get the actual log file path
								$log_file_path = self::get_debug_log_path();
								?>
								<div class="gi-toolkit-debug-status">
								<p class="gi-toolkit-debug-status__title"><?php esc_html_e( 'Current configuration:', 'gi-toolkit' ); ?></p>
								<ul class="gi-toolkit-debug-status__list">
									<li class="gi-toolkit-debug-status__item">
										<span class="gi-toolkit-debug-status__label">WP_DEBUG:</span>
										<span class="gi-toolkit-debug-status__value <?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? 'is-enabled' : 'is-disabled'; ?>">
											<?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? esc_html__( 'Enabled', 'gi-toolkit' ) : esc_html__( 'Disabled', 'gi-toolkit' ); ?>
										</span>
                                        <?php if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) : ?>
                                            <span class="gi-toolkit-debug-status__note">
                                                <?php esc_html_e( 'Note: Saving settings will enable debug mode.', 'gi-toolkit' ); ?>
                                            </span>
                                        <?php endif; ?>
									</li>
									<li class="gi-toolkit-debug-status__item">
										<span class="gi-toolkit-debug-status__label">WP_DEBUG_LOG:</span>
										<span class="gi-toolkit-debug-status__value <?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? 'is-enabled' : 'is-disabled'; ?>">
											<?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? esc_html__( 'Enabled', 'gi-toolkit' ) : esc_html__( 'Disabled', 'gi-toolkit' ); ?>
										</span>
                                        <?php if ( ! ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) : ?>
                                            <span class="gi-toolkit-debug-status__note">
                                                <?php esc_html_e( 'Note: Saving settings will enable debug logging.', 'gi-toolkit' ); ?>
                                            </span>
                                        <?php endif; ?>
									</li>
									<li class="gi-toolkit-debug-status__item">
										<span class="gi-toolkit-debug-status__label">WP_DEBUG_DISPLAY:</span>
										<span class="gi-toolkit-debug-status__value <?php echo defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? 'is-enabled' : 'is-disabled'; ?>">
											<?php echo defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? esc_html__( 'Enabled', 'gi-toolkit' ) : esc_html__( 'Disabled', 'gi-toolkit' ); ?>
										</span>
									</li>
								</ul>
								<p class="gi-toolkit-debug-status__path">
									<span class="gi-toolkit-debug-status__label"><?php esc_html_e( 'Logs are saved to:', 'gi-toolkit' ); ?></span>
									<code><?php echo esc_html( str_replace( ABSPATH, '/', $log_file_path ) ); ?></code>
								</p>
							</div>
                        </div>
                    </div>

					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title activable">
                            <div>
                                <label class="gi-toolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[display_errors]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[display_errors]" value="1" <?php checked( $this->settings['display_errors'] ?? '0', '1' ); ?>>
									<span class="gi-toolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Display Errors', 'gi-toolkit' ); ?>
                            </div>
						</div>
                        <div class="gi-toolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Enable WP_DEBUG_DISPLAY to show errors on screen. Not recommended for production sites.', 'gi-toolkit' ); ?></div>
                        </div>
                    </div>

					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title activable">
                            <div>
                                <label class="gi-toolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[protect_logs]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[protect_logs]" value="1" <?php checked( $this->settings['protect_logs'] ?? '0', '1' ); ?>>
									<span class="gi-toolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Protect Debug Logs', 'gi-toolkit' ); ?>
                            </div>
						</div>
                        <div class="gi-toolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Prevent direct HTTP access to debug.log file. Adds rules to .htaccess (Apache) or provides nginx configuration snippet to block access.', 'gi-toolkit' ); ?></div>
                        </div>
                    </div>

					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title activable">
                            <div>
                                <label class="gi-toolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[custom_log_folder]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[custom_log_folder]" value="1" <?php checked( $this->settings['custom_log_folder'] ?? '0', '1' ); ?>>
									<span class="gi-toolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Use Custom Log Folder', 'gi-toolkit' ); ?>
                            </div>
						</div>
                        <div class="gi-toolkit__section__body__item__content">
							<div class="description">
								<?php 
								$custom_folder = str_replace( ABSPATH, '/', gi_toolkit_folders() ) . '/debug-logs/';
								/* translators: %s: custom log folder path */
								echo sprintf( esc_html__( 'Move debug logs to a custom protected folder: %s', 'gi-toolkit' ), '<code>' . esc_html( $custom_folder ) . '</code>' );
								?>
							</div>
                        </div>
                    </div>

					<div class="gi-toolkit__section__body__item" data-show-if="<?php echo esc_attr( $this->option_id ); ?>[custom_log_folder]=1">
                        <div class="gi-toolkit__section__body__item__title activable">
                            <div>
                                <label class="gi-toolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[daily_logs]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[daily_logs]" value="1" <?php checked( $this->settings['daily_logs'] ?? '0', '1' ); ?>>
									<span class="gi-toolkit__toggle__slider round"></span>
								</label>
							</div>
                            <div>
                                <?php esc_html_e( 'Daily Log Files', 'gi-toolkit' ); ?>
                            </div>
						</div>
                        <div class="gi-toolkit__section__body__item__content">
							<div class="description">
								<?php 
								esc_html_e( 'Create daily log files with date suffix (debug-YYYY-MM-DD.log). Only works with Custom Log Folder enabled.', 'gi-toolkit' );
								?>
							</div>
                        </div>
                    </div>

                </div>
            </div>

			<div class="gi-toolkit__section">
                <div class="gi-toolkit__section__body">

					<div class="gi-toolkit__section__body__item">
                        <div class="gi-toolkit__section__body__item__title"><?php esc_html_e( 'Live Log Viewer', 'gi-toolkit' ); ?></div>
                        <div class="gi-toolkit__section__body__item__content">
							<div class="description"><?php esc_html_e( 'Stream debug logs in real-time:', 'gi-toolkit' ); ?></div>
							
                            <div class="gi-toolkit-log-buttons">
								<button type="button" id="gi-toolkit-start-log-stream" class="gi-toolkit-btn-start"><?php esc_html_e( 'Start Streaming', 'gi-toolkit' ); ?></button>
								<button type="button" id="gi-toolkit-stop-log-stream" class="gi-toolkit-btn-stop" style="display:none;"><?php esc_html_e( 'Stop Streaming', 'gi-toolkit' ); ?></button>
								<button type="button" id="gi-toolkit-clear-log-display" class="gi-toolkit-btn-clear"><?php esc_html_e( 'Clear Display', 'gi-toolkit' ); ?></button>
								<button type="button" id="gi-toolkit-download-log" class="gi-toolkit-btn-download"><?php esc_html_e( 'Download Log', 'gi-toolkit' ); ?></button>
								<button type="button" id="gi-toolkit-delete-log" class="gi-toolkit-btn-delete"><?php esc_html_e( 'Delete Log File', 'gi-toolkit' ); ?></button>
                                <div id="gi-toolkit-wrap-logs-container" class="gi-toolkit__checkbox">
                                    <label class="gi-toolkit__checkbox__label">
                                        <input type="checkbox" id="gi-toolkit-wrap-logs" checked>
                                        <span class="mark"></span>
                                        <span class="gi-toolkit__checkbox__label__text"><?php esc_html_e( 'Wrap lines', 'gi-toolkit' ); ?></span>
                                    </label>
                                </div>
							</div>
							
							
                            <div id="gi-toolkit-log-viewer" class="gi-toolkit-log-wrap">
								<pre id="gi-toolkit-log-content"><?php esc_html_e( 'Click "Start Streaming" to view live logs...', 'gi-toolkit' ); ?></pre>
							</div>
                        </div>
                    </div>

                </div>
            </div>
        <?php
    }

    /**
     * Get the debug log file path
     *
     * @return string
     */
    private static function get_debug_log_path() {
        // Check if custom folder is enabled
        $settings = get_option( GI_TOOLKIT_PLUGIN_SETTINGS . '_advanced_debug_mode', array() );
        $custom_log_folder = $settings['custom_log_folder'] ?? '0';
        $daily_logs = $settings['daily_logs'] ?? '0';
        
        // Check if WP_DEBUG_LOG is a custom path
        if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && WP_DEBUG_LOG !== true && WP_DEBUG_LOG !== false ) {
            // If custom log folder with daily logs is enabled
            if ( gi_toolkit_is_pro() && '1' === $custom_log_folder && '1' === $daily_logs ) {
                $base_path = dirname( WP_DEBUG_LOG );
                $date_suffix = wp_date( 'Y-m-d' );
                return $base_path . '/debug-' . $date_suffix . '.log';
            }
            return WP_DEBUG_LOG;
        }
        
        // Default path
        return WP_CONTENT_DIR . '/debug.log';
    }

    /**
     * AJAX handler to get debug log content
     */
    public function ajax_get_debug_log() {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gi-toolkit' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gi-toolkit' ) ) );
        }

        $log_file = self::get_debug_log_path();
        
        if ( ! file_exists( $log_file ) ) {
            wp_send_json_success( array( 
                'content' => esc_html__( 'No debug.log file found. Logs will appear here once errors are logged.', 'gi-toolkit' ),
                'size'    => 0
            ) );
        }

        $last_size = intval( $_POST['last_size'] ?? 0 );
        $current_size = filesize( $log_file );

        if ( $current_size === $last_size ) {
            wp_send_json_success( array( 
                'content' => '',
                'size'    => $current_size
            ) );
        }

        // Check if file was truncated or deleted during streaming
        if ( $current_size < $last_size ) {
            // File was truncated, reset and reload all content
            wp_send_json_success( array( 
                'content' => esc_html__( '[LOG FILE WAS TRUNCATED OR MODIFIED - RELOADING]', 'gi-toolkit' ),
                'size'    => 0,
                'reset'   => true
            ) );
        }

        // Read new content only
        $content = '';
        if ( $last_size === 0 ) {
            // First load - get last 100KB
			// phpcs:disable
            $handle = fopen( $log_file, 'r' );
            if ( $handle === false ) {
                wp_send_json_error( array( 'message' => __( 'Failed to open log file.', 'gi-toolkit' ) ) );
            }
            $content = '';
            if ( $current_size > 10240 ) {
                fseek( $handle, -10240, SEEK_END );
                fgets( $handle ); // Skip partial line
                $content .= "...\n";
            }
            $content .= fread( $handle, $current_size );
            fclose( $handle );
			// phpcs:enable
        } else {
            // Subsequent loads - get only new content
            $bytes_to_read = $current_size - $last_size;
            
            if ( $bytes_to_read > 0 ) {
				// phpcs:disable
                $handle = fopen( $log_file, 'r' );
                if ( $handle === false ) {
                    wp_send_json_error( array( 'message' => __( 'Failed to open log file.', 'gi-toolkit' ) ) );
                }
                
                fseek( $handle, $last_size );
                $content = fread( $handle, $bytes_to_read );
                fclose( $handle );
				// phpcs:enable
            }
        }

        wp_send_json_success( array( 
            'content' => $content,
            'size'    => $current_size
        ) );
    }

    /**
     * AJAX handler to delete debug log file
     */
    public function ajax_delete_debug_log() {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gi-toolkit' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gi-toolkit' ) ) );
        }

        $log_file = self::get_debug_log_path();
        
        if ( ! file_exists( $log_file ) ) {
            wp_send_json_error( array( 'message' => __( 'Log file does not exist.', 'gi-toolkit' ) ) );
        }

		// phpcs:disable
        if ( ! is_writable( $log_file ) ) {
            wp_send_json_error( array( 'message' => __( 'Log file is not writable.', 'gi-toolkit' ) ) );
        }

        if ( unlink( $log_file ) ) {
            wp_send_json_success( array( 'message' => __( 'Log file deleted successfully.', 'gi-toolkit' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete log file.', 'gi-toolkit' ) ) );
        }
		// phpcs:enable
    }
}
