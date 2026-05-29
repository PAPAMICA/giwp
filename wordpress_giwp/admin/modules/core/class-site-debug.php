<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module : tableau de bord debug site (diagnostic complet).
 */
class Gi_Toolkit_Site_Debug {

	const PAGE_SLUG = 'gi-toolkit-site-debug';

	/** @var self|null */
	private static $instance = null;

	private $header_title = '';

	/**
	 * @return void
	 */
	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		require_once GI_TOOLKIT_PLUGIN_PATH . 'admin/helpers/core/site-debug/class-collector.php';

		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_gi_toolkit_site_debug_export', array( $this, 'ajax_export' ) );
	}

	/**
	 * @return void
	 */
	public function class_init() {
		$this->header_title = __( 'Site Debug', 'gi-toolkit' );
	}

	/**
	 * @return void
	 */
	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			null
		);
	}

	/**
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$version = defined( 'GI_TOOLKIT_VERSION' ) ? GI_TOOLKIT_VERSION : '1.0.0';

		wp_enqueue_style(
			'gi-toolkit-site-debug',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/css/site-debug.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'gi-toolkit-site-debug',
			GI_TOOLKIT_PLUGIN_URL . 'admin/assets/js/site-debug.js',
			array(),
			$version,
			true
		);
		wp_localize_script(
			'gi-toolkit-site-debug',
			'giToolkitSiteDebug',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gi_toolkit_site_debug' ),
				'i18n'    => array(
					'copied'      => __( 'Copié dans le presse-papiers', 'gi-toolkit' ),
					'copyFailed'  => __( 'Échec de la copie', 'gi-toolkit' ),
					'search'      => __( 'Filtrer les sections…', 'gi-toolkit' ),
					'exporting'   => __( 'Export en cours…', 'gi-toolkit' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function ajax_export() {
		check_ajax_referer( 'gi_toolkit_site_debug', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'gi-toolkit' ) ) );
		}

		$data = Gi_Toolkit_Site_Debug_Collector::collect();
		wp_send_json_success(
			array(
				'json'     => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'filename' => 'gi-site-debug-' . gmdate( 'Y-m-d-His' ) . '.json',
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'gi-toolkit' ) );
		}

		$this->disable_form      = true;
		$this->disable_save_form = true;

		$data = Gi_Toolkit_Site_Debug_Collector::collect();

		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->render_dashboard( $data );
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * @param array<string, mixed> $data Données.
	 * @return void
	 */
	private function render_dashboard( array $data ) {
		$sections = self::build_sections( $data );
		?>
		<div class="gi-sd" id="gi-site-debug">
			<div class="gi-sd-toolbar">
				<label class="gi-sd-search">
					<span class="screen-reader-text"><?php esc_html_e( 'Filtrer', 'gi-toolkit' ); ?></span>
					<input type="search" class="gi-sd-search__input" placeholder="<?php esc_attr_e( 'Filtrer les sections…', 'gi-toolkit' ); ?>" autocomplete="off" />
				</label>
				<div class="gi-sd-toolbar__actions">
					<button type="button" class="button gi-sd-btn-export"><?php esc_html_e( 'Exporter JSON', 'gi-toolkit' ); ?></button>
					<button type="button" class="button gi-sd-btn-copy-all"><?php esc_html_e( 'Copier tout', 'gi-toolkit' ); ?></button>
				</div>
			</div>

			<section class="gi-sd-summary">
				<h2><?php esc_html_e( 'Synthèse', 'gi-toolkit' ); ?></h2>
				<div class="gi-sd-summary__grid">
					<?php foreach ( (array) ( $data['summary'] ?? array() ) as $item ) : ?>
						<div class="gi-sd-badge gi-sd-badge--<?php echo esc_attr( $item['level'] ?? 'ok' ); ?>">
							<?php echo esc_html( $item['message'] ?? '' ); ?>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="gi-sd-meta">
					<?php
					printf(
						/* translators: 1: local datetime, 2: site URL */
						esc_html__( 'Généré le %1$s — %2$s', 'gi-toolkit' ),
						esc_html( $data['meta']['generated_at_local'] ?? '' ),
						esc_html( $data['meta']['home_url'] ?? '' )
					);
					?>
				</p>
			</section>

			<div class="gi-sd-sections">
				<?php foreach ( $sections as $section ) : ?>
					<section class="gi-sd-section" data-search="<?php echo esc_attr( $section['search'] ); ?>">
						<header class="gi-sd-section__head">
							<h3><?php echo esc_html( $section['title'] ); ?></h3>
							<button type="button" class="button button-small gi-sd-copy-section" data-copy-target="<?php echo esc_attr( $section['id'] ); ?>">
								<?php esc_html_e( 'Copier', 'gi-toolkit' ); ?>
							</button>
						</header>
						<div class="gi-sd-section__body" id="<?php echo esc_attr( $section['id'] ); ?>">
							<?php
							if ( ! empty( $section['table'] ) ) {
								self::render_table( $section['table'] );
							}
							if ( ! empty( $section['html'] ) ) {
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML généré en interne.
								echo $section['html'];
							}
							?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>

			<textarea class="gi-sd-export-source" readonly hidden aria-hidden="true"><?php echo esc_textarea( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $data Données.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_sections( array $data ) {
		$sections = array();

		$sections[] = self::section(
			'wordpress',
			__( 'WordPress', 'gi-toolkit' ),
			self::flatten_assoc( $data['wordpress'] ?? array() )
		);

		$php      = $data['php'] ?? array();
		$php_flat = $php;
		unset( $php_flat['extensions'] );
		$sections[] = self::section(
			'php',
			__( 'PHP', 'gi-toolkit' ),
			self::flatten_assoc( $php_flat ),
			self::render_list_block( __( 'Extensions PHP', 'gi-toolkit' ), $data['php']['extensions'] ?? array() )
		);

		$sections[] = self::section(
			'server',
			__( 'Serveur', 'gi-toolkit' ),
			self::flatten_assoc( array_merge( $data['server']['values'] ?? array(), array(
				'https_detected' => $data['server']['https_detected'] ?? false,
				'host_ip'        => $data['server']['host_ip'] ?? '',
			) ) )
		);

		$sections[] = self::section(
			'constants',
			__( 'Constantes wp-config', 'gi-toolkit' ),
			self::flatten_assoc( $data['constants'] ?? array(), true )
		);

		$db = $data['database'] ?? array();
		$db_rows = self::flatten_assoc( $db );
		unset( $db_rows['autoload_top'], $db_rows['tables_top_size'] );
		$sections[] = self::section(
			'database',
			__( 'Base de données', 'gi-toolkit' ),
			$db_rows,
			self::render_table_block( __( 'Options autoload les plus lourdes', 'gi-toolkit' ), array( 'option_name', 'size_bytes' ), $db['autoload_top'] ?? array() )
			. self::render_table_block( __( 'Tables les plus volumineuses', 'gi-toolkit' ), array( 'name', 'size_mb', 'row_estimate' ), $db['tables_top_size'] ?? array() )
		);

		$sections[] = self::section(
			'theme',
			__( 'Thème', 'gi-toolkit' ),
			self::flatten_assoc( $data['theme'] ?? array() )
		);

		$plugins = $data['plugins'] ?? array();
		$sections[] = self::section(
			'plugins',
			sprintf( __( 'Extensions (%1$d actives, %2$d inactives, %3$d MU)', 'gi-toolkit' ), (int) ( $plugins['active_count'] ?? 0 ), (int) ( $plugins['inactive_count'] ?? 0 ), (int) ( $plugins['mu_count'] ?? 0 ) ),
			array(),
			self::render_plugins_block( $plugins )
		);

		$users = $data['users'] ?? array();
		$role_rows = array();
		foreach ( (array) ( $users['roles'] ?? array() ) as $role => $count ) {
			$role_rows[] = array( $role, (string) $count );
		}
		$sections[] = self::section(
			'users',
			__( 'Utilisateurs', 'gi-toolkit' ),
			array(
				array( __( 'Total', 'gi-toolkit' ), (string) ( $users['total'] ?? 0 ) ),
			),
			self::render_table_simple( $role_rows )
		);

		$sections[] = self::section(
			'content',
			__( 'Contenu', 'gi-toolkit' ),
			self::flatten_assoc( $data['content'] ?? array() )
		);

		$cron = $data['cron'] ?? array();
		$sections[] = self::section(
			'cron',
			__( 'Planification (WP-Cron)', 'gi-toolkit' ),
			self::flatten_assoc( array(
				'wp_cron_disabled' => $cron['wp_cron_disabled'] ?? false,
				'alternate_cron'   => $cron['alternate_cron'] ?? false,
				'total_scheduled'  => $cron['total_scheduled'] ?? 0,
			) ),
			self::render_table_block( __( 'Prochains événements', 'gi-toolkit' ), array( 'hook', 'next_run', 'schedule', 'args' ), $cron['next_events'] ?? array() )
		);

		$sections[] = self::section(
			'cache',
			__( 'Cache', 'gi-toolkit' ),
			self::flatten_assoc( $data['cache'] ?? array() )
		);

		$sections[] = self::section(
			'filesystem',
			__( 'Système de fichiers', 'gi-toolkit' ),
			self::flatten_assoc( $data['filesystem'] ?? array() )
		);

		$sections[] = self::section(
			'dropins',
			__( 'Drop-ins', 'gi-toolkit' ),
			self::flatten_assoc( $data['dropins'] ?? array() )
		);

		$sections[] = self::section(
			'rewrite',
			__( 'Réécriture', 'gi-toolkit' ),
			self::flatten_assoc( $data['rewrite'] ?? array() )
		);

		$gi = $data['gi_toolkit'] ?? array();
		$sections[] = self::section(
			'gi-toolkit',
			__( 'GI-Toolkit', 'gi-toolkit' ),
			self::flatten_assoc( array(
				'version'        => $gi['version'] ?? '',
				'dev_mode'       => $gi['dev_mode'] ?? false,
				'safe_mode'      => $gi['safe_mode'] ?? false,
				'active_count'   => $gi['active_count'] ?? 0,
				'inactive_count' => $gi['inactive_count'] ?? 0,
			) ),
			self::render_modules_block( $gi )
		);

		$sections[] = self::section(
			'integrations',
			__( 'Intégrations', 'gi-toolkit' ),
			self::flatten_assoc( $data['integrations'] ?? array(), true )
		);

		$sections[] = self::section(
			'mail',
			__( 'E-mail', 'gi-toolkit' ),
			self::flatten_assoc( $data['mail'] ?? array() )
		);

		$sections[] = self::section(
			'security',
			__( 'Sécurité', 'gi-toolkit' ),
			self::flatten_assoc( $data['security'] ?? array() )
		);

		$sections[] = self::section(
			'rest',
			__( 'API REST', 'gi-toolkit' ),
			self::flatten_assoc( $data['rest'] ?? array() )
		);

		$log = $data['debug_log'] ?? array();
		$log_html = '';
		if ( ! empty( $log['path'] ) ) {
			$log_html .= '<p><strong>' . esc_html( $log['path'] ) . '</strong> — ' . esc_html( $log['size'] ?? '' ) . ' — ' . esc_html( $log['modified'] ?? '' ) . '</p>';
			$log_html .= '<pre class="gi-sd-log">' . esc_html( implode( "\n", (array) ( $log['tail'] ?? array() ) ) ) . '</pre>';
		} else {
			$log_html = '<p class="gi-sd-empty">' . esc_html__( 'Aucun debug.log lisible.', 'gi-toolkit' ) . '</p>';
		}
		$sections[] = self::section(
			'debug-log',
			__( 'Journal debug.log', 'gi-toolkit' ),
			array(),
			$log_html
		);

		return $sections;
	}

	/**
	 * @param string               $id     ID.
	 * @param string               $title  Titre.
	 * @param array<int, array{0:string,1:string}> $table  Lignes.
	 * @param string               $html   HTML additionnel.
	 * @return array<string, mixed>
	 */
	private static function section( $id, $title, array $table = array(), $html = '' ) {
		return array(
			'id'     => 'gi-sd-' . $id,
			'title'  => $title,
			'table'  => $table,
			'html'   => $html,
			'search' => strtolower( $title . ' ' . $id ),
		);
	}

	/**
	 * @param array<string, mixed> $data   Données.
	 * @param bool                 $nulls  Afficher null.
	 * @return array<int, array{0:string,1:string}>
	 */
	private static function flatten_assoc( array $data, $nulls = false ) {
		$rows = array();
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( null === $value ) {
				if ( ! $nulls ) {
					continue;
				}
				$value = '—';
			}
			$rows[] = array( (string) $key, (string) $value );
		}
		return $rows;
	}

	/**
	 * @param array<int, array{0:string,1:string}> $rows Lignes.
	 * @return void
	 */
	private static function render_table( array $rows ) {
		if ( empty( $rows ) ) {
			echo '<p class="gi-sd-empty">' . esc_html__( 'Aucune donnée.', 'gi-toolkit' ) . '</p>';
			return;
		}
		echo '<table class="gi-sd-table"><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td><code>' . esc_html( $row[1] ) . '</code></td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param array<int, array{0:string,1:string}> $rows Lignes.
	 * @return string
	 */
	private static function render_table_simple( array $rows ) {
		if ( empty( $rows ) ) {
			return '';
		}
		ob_start();
		self::render_table( $rows );
		return (string) ob_get_clean();
	}

	/**
	 * @param string               $title  Titre.
	 * @param array<int, string>   $cols   Colonnes.
	 * @param array<int, mixed>    $rows   Lignes.
	 * @return string
	 */
	private static function render_table_block( $title, array $cols, array $rows ) {
		if ( empty( $rows ) ) {
			return '';
		}
		ob_start();
		echo '<h4 class="gi-sd-subtitle">' . esc_html( $title ) . '</h4>';
		echo '<table class="gi-sd-table gi-sd-table--wide"><thead><tr>';
		foreach ( $cols as $col ) {
			echo '<th scope="col">' . esc_html( $col ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			echo '<tr>';
			foreach ( $cols as $col ) {
				echo '<td><code>' . esc_html( (string) ( $row[ $col ] ?? '' ) ) . '</code></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		return (string) ob_get_clean();
	}

	/**
	 * @param string               $title Titre.
	 * @param array<int, string>   $items Items.
	 * @return string
	 */
	private static function render_list_block( $title, array $items ) {
		if ( empty( $items ) ) {
			return '';
		}
		ob_start();
		echo '<h4 class="gi-sd-subtitle">' . esc_html( $title ) . '</h4>';
		echo '<p class="gi-sd-tags">';
		foreach ( $items as $item ) {
			echo '<span class="gi-sd-tag">' . esc_html( (string) $item ) . '</span>';
		}
		echo '</p>';
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $plugins Données plugins.
	 * @return string
	 */
	private static function render_plugins_block( array $plugins ) {
		ob_start();
		foreach ( array(
			'active'   => __( 'Actives', 'gi-toolkit' ),
			'inactive' => __( 'Inactives', 'gi-toolkit' ),
			'must_use' => __( 'Must-use', 'gi-toolkit' ),
		) as $key => $label ) {
			$list = $plugins[ $key ] ?? array();
			if ( empty( $list ) ) {
				continue;
			}
			echo '<h4 class="gi-sd-subtitle">' . esc_html( $label ) . '</h4>';
			echo '<table class="gi-sd-table gi-sd-table--wide"><thead><tr><th>' . esc_html__( 'Extension', 'gi-toolkit' ) . '</th><th>' . esc_html__( 'Version', 'gi-toolkit' ) . '</th><th>' . esc_html__( 'Fichier', 'gi-toolkit' ) . '</th></tr></thead><tbody>';
			foreach ( $list as $plugin ) {
				echo '<tr><td>' . esc_html( $plugin['name'] ?? '' ) . '</td><td><code>' . esc_html( $plugin['version'] ?? '' ) . '</code></td><td><code>' . esc_html( $plugin['file'] ?? '' ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
		}
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $gi Données GI-Toolkit.
	 * @return string
	 */
	private static function render_modules_block( array $gi ) {
		ob_start();
		foreach ( array(
			'active_modules'   => __( 'Modules actifs', 'gi-toolkit' ),
			'inactive_modules' => __( 'Modules inactifs', 'gi-toolkit' ),
		) as $key => $label ) {
			$list = $gi[ $key ] ?? array();
			if ( empty( $list ) ) {
				continue;
			}
			echo '<h4 class="gi-sd-subtitle">' . esc_html( $label ) . '</h4>';
			echo '<table class="gi-sd-table gi-sd-table--wide"><thead><tr><th>' . esc_html__( 'Module', 'gi-toolkit' ) . '</th><th>' . esc_html__( 'Groupe', 'gi-toolkit' ) . '</th><th>' . esc_html__( 'Classe', 'gi-toolkit' ) . '</th></tr></thead><tbody>';
			foreach ( $list as $mod ) {
				echo '<tr><td>' . esc_html( $mod['name'] ?? '' ) . '</td><td><code>' . esc_html( $mod['group'] ?? '' ) . '</code></td><td><code>' . esc_html( $mod['class'] ?? '' ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
		}
		return (string) ob_get_clean();
	}
}
