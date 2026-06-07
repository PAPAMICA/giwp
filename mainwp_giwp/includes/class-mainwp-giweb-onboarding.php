<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onboarding : installation GI-Toolkit + profil lors de l’ajout d’un site MainWP.
 */
class MainWP_GIWeb_Onboarding {

	const LOG_OPTION = 'mainwp_giweb_onboarding_logs';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'mainwp_manage_sites_edit', array( __CLASS__, 'render_add_site_fields' ), 20, 1 );
		add_action( 'mainwp_site_added', array( __CLASS__, 'handle_site_added' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_add_site_assets' ) );
	}

	/**
	 * Styles sur la page « Ajouter un site » MainWP.
	 *
	 * @param string $hook Hook admin.
	 */
	public static function enqueue_add_site_assets( $hook ) {
		if ( false === strpos( $hook, 'managesites' ) && false === strpos( $hook, 'page-mainwp-manage-sites' ) ) {
			return;
		}
		wp_enqueue_style(
			'mainwp-giweb-onboarding',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MAINWP_GIWEB_VERSION
		);
		wp_enqueue_script(
			'mainwp-giweb-onboarding',
			MAINWP_GIWEB_PLUGIN_URL . 'assets/js/onboarding.js',
			array( 'jquery' ),
			MAINWP_GIWEB_VERSION,
			true
		);
	}

	/**
	 * Champs sur le formulaire MainWP « Ajouter un site ».
	 *
	 * @param object|false $website Site en édition ou false = ajout.
	 */
	public static function render_add_site_fields( $website ) {
		if ( false !== $website ) {
			return;
		}

		$settings     = MainWP_GIWeb_Settings::get();
		$templates    = MainWP_GIWeb_Templates::all();
		$default_id   = MainWP_GIWeb_Templates::get_default_template_id();
		$zip_ready    = (bool) MainWP_GIWeb_Zip::get_install_url();
		$install_on   = '1' === ( $settings['install_checked_by_default'] ?? '1' );
		$profile_on   = '1' === ( $settings['apply_profile_by_default'] ?? '1' );
		?>
		<h2 class="ui dividing header mainwp-giweb-onboarding-header">
			<?php esc_html_e( 'GI-Toolkit', 'mainwp-giweb' ); ?>
			<div class="sub header"><?php esc_html_e( 'Installation et profil de configuration sur le nouveau site.', 'mainwp-giweb' ); ?></div>
		</h2>

		<?php if ( ! $zip_ready ) : ?>
			<div class="ui yellow message">
				<?php esc_html_e( 'Le ZIP GI-Toolkit n’a pas pu être généré. Vérifiez que wordpress_giwp est présent et que ZipArchive est activé.', 'mainwp-giweb' ); ?>
			</div>
		<?php endif; ?>

		<div class="mainwp_addition_fields_addsite" id="mainwp-giweb-install-hidden-wrap">
			<input type="hidden" name="mainwp_giweb_install_gi_toolkit" id="mainwp_giweb_install_hidden" value="<?php echo $install_on ? '1' : '0'; ?>" />
			<input type="hidden" name="mainwp_giweb_apply_profile" id="mainwp_giweb_apply_hidden" value="<?php echo $profile_on ? '1' : '0'; ?>" />
			<input type="hidden" name="mainwp_giweb_template_id" id="mainwp_giweb_template_hidden" value="<?php echo esc_attr( $default_id ); ?>" />
		</div>

		<div class="ui grid field">
			<label class="six wide column middle aligned"><?php esc_html_e( 'Installer GI-Toolkit', 'mainwp-giweb' ); ?></label>
			<div class="ten wide column">
				<div class="ui toggle checkbox not-auto-init" id="mainwp-giweb-install-toggle">
					<input type="checkbox" value="1" <?php checked( $install_on ); ?> <?php disabled( ! $zip_ready ); ?> />
					<label><?php esc_html_e( 'Installer le plugin depuis le ZIP du monorepo (wordpress_giwp)', 'mainwp-giweb' ); ?></label>
				</div>
			</div>
		</div>

		<div class="ui grid field">
			<label class="six wide column middle aligned" for="mainwp_giweb_template_id"><?php esc_html_e( 'Profil à appliquer', 'mainwp-giweb' ); ?></label>
			<div class="ten wide column">
				<div class="ui toggle checkbox not-auto-init" id="mainwp-giweb-profile-toggle" style="margin-bottom:10px;">
					<input type="checkbox" value="1" <?php checked( $profile_on ); ?> />
					<label><?php esc_html_e( 'Appliquer automatiquement un modèle après connexion', 'mainwp-giweb' ); ?></label>
				</div>
				<select class="ui dropdown" id="mainwp_giweb_template_id">
					<option value=""><?php esc_html_e( 'Profil par défaut (Default)', 'mainwp-giweb' ); ?></option>
					<?php foreach ( $templates as $id => $tpl ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $default_id, $id ); ?>>
							<?php
							echo esc_html( $tpl['name'] ?? $id );
							if ( ! empty( $tpl['is_default'] ) || $default_id === $id ) {
								echo ' (' . esc_html__( 'défaut', 'mainwp-giweb' ) . ')';
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description" style="margin-top:8px;">
					<?php esc_html_e( 'Si aucun modèle « Default » n’existe, créez-le dans l’onglet Modèles de l’extension GI-Toolkit Manager.', 'mainwp-giweb' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * @param object               $website     Site MainWP.
	 * @param array<string, mixed> $information Infos connexion.
	 */
	public static function handle_site_added( $website, $information ) {
		unset( $information );

		if ( ! is_object( $website ) || empty( $website->id ) ) {
			return;
		}

		$site_id = absint( $website->id );
		$opts    = self::read_add_site_options();
		if ( empty( $opts['install'] ) && empty( $opts['apply_profile'] ) ) {
			return;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}

		$result = self::process( $site_id, $opts, $website );
		self::log_result( $site_id, $result );
		self::store_admin_notice( $result );
	}

	/**
	 * @return array{install:bool, apply_profile:bool, template_id:string}
	 */
	public static function read_add_site_options() {
		$settings = MainWP_GIWeb_Settings::get();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$install_default = '1' === ( $settings['install_checked_by_default'] ?? '1' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$profile_default = '1' === ( $settings['apply_profile_by_default'] ?? '1' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( array_key_exists( 'mainwp_giweb_install_gi_toolkit', $_POST ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$install = ! empty( $_POST['mainwp_giweb_install_gi_toolkit'] ) && '0' !== (string) wp_unslash( $_POST['mainwp_giweb_install_gi_toolkit'] );
		} else {
			$install = $install_default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( array_key_exists( 'mainwp_giweb_apply_profile', $_POST ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$apply_profile = ! empty( $_POST['mainwp_giweb_apply_profile'] ) && '0' !== (string) wp_unslash( $_POST['mainwp_giweb_apply_profile'] );
		} else {
			$apply_profile = $profile_default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$template_id = array_key_exists( 'mainwp_giweb_template_id', $_POST )
			? sanitize_text_field( wp_unslash( $_POST['mainwp_giweb_template_id'] ) )
			: (string) MainWP_GIWeb_Templates::get_default_template_id();

		return array(
			'install'       => $install,
			'apply_profile' => $apply_profile,
			'template_id'   => $template_id,
		);
	}

	/**
	 * @param int                             $site_id ID site.
	 * @param array{install:bool, apply_profile:bool, template_id:string} $opts Options.
	 * @return array<string, mixed>
	 */
	public static function process( $site_id, array $opts, $website = null ) {
		$site_id = absint( $site_id );
		$logs    = array();
		$ok      = true;
		$profile_ok = false;

		if ( ! empty( $opts['install'] ) ) {
			$status = MainWP_GIWeb_API::get_status( $site_id );
			if ( ! empty( $status['success'] ) ) {
				$logs[] = __( 'GI-Toolkit déjà présent sur le site.', 'mainwp-giweb' );
			} else {
				$install = MainWP_GIWeb_Plugin_Installer::install_gi_toolkit( $site_id );
				$logs[]  = $install['message'];
				if ( empty( $install['success'] ) ) {
					$ok = false;
				} else {
					$wait = MainWP_GIWeb_Plugin_Installer::wait_for_gi_toolkit( $site_id, 8 );
					if ( empty( $wait['success'] ) ) {
						$logs[] = __( 'GI-Toolkit installé mais non détecté immédiatement — le déploiement du profil peut échouer.', 'mainwp-giweb' );
					}
				}
			}
		}

		if ( $ok && ! empty( $opts['apply_profile'] ) ) {
			$template_id   = (string) $opts['template_id'];
			$bundle        = MainWP_GIWeb_Templates::resolve_bundle( $template_id );
			$template_name = '';

			if ( null === $bundle ) {
				$logs[] = __( 'Aucun profil « Default » ou modèle sélectionné — déploiement ignoré.', 'mainwp-giweb' );
				$ok     = false;
			} else {
				if ( '' === $template_id ) {
					$template_id = MainWP_GIWeb_Templates::get_default_template_id();
				}
				$tpl = MainWP_GIWeb_Templates::get( $template_id );
				$template_name = (string) ( $tpl['name'] ?? 'Default' );

				$deploy = MainWP_GIWeb_Deploy::push_to_sites( $bundle, array( $site_id ), $template_id, $template_name );
				$row    = $deploy['results'][ $site_id ] ?? array();
				if ( ! empty( $row['success'] ) ) {
					$profile_ok = true;
					$logs[]     = sprintf(
						/* translators: %s: template name */
						__( 'Profil « %s » appliqué.', 'mainwp-giweb' ),
						$template_name
					);
				} else {
					$err    = ! empty( $row['errors'][0] ) ? (string) $row['errors'][0] : __( 'Échec du déploiement du profil.', 'mainwp-giweb' );
					$logs[] = $err;
					$ok     = false;
				}
			}
		}

		$ftp_note = self::maybe_ensure_ftp_folder( $site_id, $website, $ok, $profile_ok, $opts );
		if ( '' !== $ftp_note ) {
			$logs[] = $ftp_note;
		}

		return array(
			'success' => $ok,
			'logs'    => $logs,
			'site_id' => $site_id,
		);
	}

	/**
	 * @param int                  $site_id    ID site.
	 * @param object|null          $website    Site MainWP fraîchement ajouté.
	 * @param bool                 $ok         Succès global onboarding.
	 * @param bool                 $profile_ok Profil appliqué.
	 * @param array<string, mixed> $opts       Options onboarding.
	 * @return string
	 */
	private static function maybe_ensure_ftp_folder( $site_id, $website, $ok, $profile_ok, array $opts ) {
		if ( empty( $opts['install'] ) && empty( $opts['apply_profile'] ) ) {
			return '';
		}
		if ( ! empty( $opts['apply_profile'] ) && $profile_ok ) {
			return '';
		}
		if ( ! $ok && empty( $opts['install'] ) ) {
			return '';
		}

		$row = self::site_row( $site_id, $website );
		$result = MainWP_GIWeb_Ftp_Backup::ensure_for_site_row( $row );
		return MainWP_GIWeb_Ftp_Backup::format_result_note( $result );
	}

	/**
	 * @param int         $site_id ID site.
	 * @param object|null $website Site MainWP.
	 * @return array{id:int, name:string, url:string}
	 */
	private static function site_row( $site_id, $website = null ) {
		if ( is_object( $website ) && ! empty( $website->id ) ) {
			return array(
				'id'   => (int) $website->id,
				'name' => (string) ( $website->name ?? '' ),
				'url'  => (string) ( $website->url ?? '' ),
			);
		}

		global $mainwp_giweb_activator;
		return MainWP_GIWeb_Sites::find_by_id( absint( $site_id ), $mainwp_giweb_activator ?? null );
	}

	/**
	 * @param int                  $site_id ID site.
	 * @param array<string, mixed> $result  Résultat.
	 */
	private static function log_result( $site_id, $result ) {
		$logs = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		array_unshift(
			$logs,
			array(
				'site_id' => $site_id,
				'time'    => gmdate( 'c' ),
				'success' => ! empty( $result['success'] ),
				'logs'    => $result['logs'] ?? array(),
			)
		);
		$logs = array_slice( $logs, 0, 50 );
		update_option( self::LOG_OPTION, $logs, false );
	}

	/**
	 * @param array<string, mixed> $result Résultat onboarding.
	 */
	private static function store_admin_notice( $result ) {
		$message = implode( ' ', array_map( 'wp_strip_all_tags', (array) ( $result['logs'] ?? array() ) ) );
		set_transient(
			'mainwp_giweb_onboarding_notice',
			array(
				'type'    => ! empty( $result['success'] ) ? 'success' : 'warning',
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 10
		);
	}

	/**
	 * Affiche la notice après redirection MainWP.
	 */
	public static function maybe_render_notice() {
		$notice = get_transient( 'mainwp_giweb_onboarding_notice' );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( 'mainwp_giweb_onboarding_notice' );
		$type = in_array( $notice['type'], array( 'success', 'warning', 'error' ), true ) ? $notice['type'] : 'info';
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p><strong>GI-Toolkit :</strong> %2$s</p></div>',
			esc_attr( $type ),
			esc_html( (string) $notice['message'] )
		);
	}
}
