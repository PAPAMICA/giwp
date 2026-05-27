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

		<div class="ui grid field">
			<label class="six wide column middle aligned"><?php esc_html_e( 'Installer GI-Toolkit', 'mainwp-giweb' ); ?></label>
			<div class="ten wide column">
				<div class="ui toggle checkbox not-auto-init" id="mainwp-giweb-install-toggle">
					<input type="checkbox" name="mainwp_giweb_install_gi_toolkit" value="1" <?php checked( $install_on ); ?> <?php disabled( ! $zip_ready ); ?> />
					<label><?php esc_html_e( 'Installer le plugin depuis le ZIP du monorepo (wordpress_giwp)', 'mainwp-giweb' ); ?></label>
				</div>
			</div>
		</div>

		<div class="ui grid field">
			<label class="six wide column middle aligned" for="mainwp_giweb_template_id"><?php esc_html_e( 'Profil à appliquer', 'mainwp-giweb' ); ?></label>
			<div class="ten wide column">
				<div class="ui toggle checkbox not-auto-init" id="mainwp-giweb-profile-toggle" style="margin-bottom:10px;">
					<input type="checkbox" name="mainwp_giweb_apply_profile" value="1" <?php checked( $profile_on ); ?> />
					<label><?php esc_html_e( 'Appliquer automatiquement un modèle après connexion', 'mainwp-giweb' ); ?></label>
				</div>
				<select class="ui dropdown" name="mainwp_giweb_template_id" id="mainwp_giweb_template_id">
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

		$result = self::process( $site_id, $opts );
		self::log_result( $site_id, $result );
		self::store_admin_notice( $result );
	}

	/**
	 * @return array{install:bool, apply_profile:bool, template_id:string}
	 */
	public static function read_add_site_options() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return array(
			'install'       => ! empty( $_POST['mainwp_giweb_install_gi_toolkit'] ),
			'apply_profile' => ! empty( $_POST['mainwp_giweb_apply_profile'] ),
			'template_id'   => sanitize_text_field( wp_unslash( $_POST['mainwp_giweb_template_id'] ?? '' ) ),
		);
	}

	/**
	 * @param int                             $site_id ID site.
	 * @param array{install:bool, apply_profile:bool, template_id:string} $opts Options.
	 * @return array<string, mixed>
	 */
	public static function process( $site_id, array $opts ) {
		$site_id = absint( $site_id );
		$logs    = array();
		$ok      = true;

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
					$wait = MainWP_GIWeb_Plugin_Installer::wait_for_gi_toolkit( $site_id );
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
					$logs[] = sprintf(
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

		return array(
			'success' => $ok,
			'logs'    => $logs,
			'site_id' => $site_id,
		);
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
