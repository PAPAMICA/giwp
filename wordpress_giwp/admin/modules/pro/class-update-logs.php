<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: journal des mises à jour (extensions / thème / cœur / traductions).
 */
class Gi_Toolkit_Update_Logs {

	const OPTION_KEY = 'gi_toolkit_update_logs';

	private $disable_form = true;

	private $header_title = '';

	private $page_slug = 'gi-toolkit-settings-update-logs';

	public function __construct() {
		$this->header_title = __( 'Updates Logs', 'gi-toolkit' );
		add_action( 'upgrader_process_complete', array( $this, 'log_upgrade' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
	}

	public function add_submenu() {
		Gi_Toolkit_Settings::add_submenu_page(
			'gi-toolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'render_submenu' )
		);
	}

	public function save_submenu() {
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, 'gi_toolkit_save_update_logs' ) ) {
			return;
		}
		if ( ! empty( $_POST['gi_toolkit_clear_update_logs'] ) ) {
			delete_option( self::OPTION_KEY );
		}
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>" style="margin-bottom:1rem;">
				<?php wp_nonce_field( 'gi_toolkit_save_update_logs' ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<button type="submit" name="gi_toolkit_clear_update_logs" value="1" class="button"><?php esc_html_e( 'Vider le journal', 'gi-toolkit' ); ?></button>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Type', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Action', 'gi-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Éléments', 'gi-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				if ( empty( $logs ) ) {
					echo '<tr><td colspan="4">' . esc_html__( 'Aucune entrée pour le moment.', 'gi-toolkit' ) . '</td></tr>';
				} else {
					foreach ( $logs as $row ) {
						$labels = self::resolve_entry_labels( $row );
						echo '<tr>';
						echo '<td>' . esc_html( $row['time'] ?? '' ) . '</td>';
						echo '<td>' . esc_html( $row['type'] ?? '' ) . '</td>';
						echo '<td>' . esc_html( $row['action'] ?? '' ) . '</td>';
						echo '<td>' . esc_html( implode( ', ', $labels ) ) . '</td>';
						echo '</tr>';
					}
				}
				?>
				</tbody>
			</table>
		</div>
		<?php
		echo '</div>';
	}

	/**
	 * @param WP_Upgrader $upgrader Upgrader.
	 * @param array       $data     Résultat upgrader_process_complete.
	 * @return void
	 */
	public function log_upgrade( $upgrader, $data ) {
		unset( $upgrader );
		if ( empty( $data['type'] ) || empty( $data['action'] ) ) {
			return;
		}

		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$type   = sanitize_text_field( (string) $data['type'] );
		$action = sanitize_text_field( (string) $data['action'] );
		$items  = self::extract_raw_items( $data );
		$labels = self::build_labels_for_type( $type, $items, $data );

		$entry = array(
			'time'   => current_time( 'mysql' ),
			'type'   => $type,
			'action' => $action,
			'items'  => $items,
			'labels' => $labels,
		);

		array_unshift( $logs, $entry );
		$logs = array_slice( $logs, 0, 100 );
		update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * Libellés lisibles pour une entrée (nouveaux logs + anciens).
	 *
	 * @param array<string, mixed> $row Entrée journal.
	 * @return array<int, string>
	 */
	public static function resolve_entry_labels( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}

		if ( ! empty( $row['labels'] ) && is_array( $row['labels'] ) ) {
			return array_values(
				array_filter(
					array_map( 'sanitize_text_field', $row['labels'] )
				)
			);
		}

		$type  = (string) ( $row['type'] ?? '' );
		$items = isset( $row['items'] ) && is_array( $row['items'] ) ? $row['items'] : array();

		return self::build_labels_for_type( $type, $items, $row );
	}

	/**
	 * @param array<string, mixed> $data Données upgrader.
	 * @return array<int, string>
	 */
	private static function extract_raw_items( $data ) {
		$items = array();

		if ( ! empty( $data['plugins'] ) ) {
			$plugins = is_array( $data['plugins'] ) ? $data['plugins'] : array( $data['plugins'] );
			foreach ( $plugins as $plugin ) {
				$items[] = sanitize_text_field( (string) $plugin );
			}
		}

		if ( ! empty( $data['plugin'] ) ) {
			$items[] = sanitize_text_field( (string) $data['plugin'] );
		}

		if ( ! empty( $data['themes'] ) ) {
			$themes = is_array( $data['themes'] ) ? $data['themes'] : array( $data['themes'] );
			foreach ( $themes as $theme ) {
				$items[] = sanitize_text_field( (string) $theme );
			}
		}

		if ( ! empty( $data['theme'] ) ) {
			$items[] = sanitize_text_field( (string) $data['theme'] );
		}

		if ( ! empty( $data['translations'] ) && is_array( $data['translations'] ) ) {
			foreach ( $data['translations'] as $translation ) {
				if ( ! is_array( $translation ) ) {
					continue;
				}
				$slug = sanitize_text_field( (string) ( $translation['slug'] ?? '' ) );
				$lang = sanitize_text_field( (string) ( $translation['language'] ?? '' ) );
				$ttype = sanitize_text_field( (string) ( $translation['type'] ?? '' ) );
				$items[] = trim( $ttype . ':' . $slug . ':' . $lang, ':' );
			}
		}

		return array_values( array_unique( array_filter( $items ) ) );
	}

	/**
	 * @param string               $type  Type (plugin|theme|core|translation).
	 * @param array<int, string>   $items Identifiants bruts.
	 * @param array<string, mixed> $data  Contexte (upgrader ou entrée).
	 * @return array<int, string>
	 */
	private static function build_labels_for_type( $type, $items, $data = array() ) {
		$type = (string) $type;

		if ( 'core' === $type ) {
			global $wp_version;
			$version = isset( $wp_version ) ? (string) $wp_version : get_bloginfo( 'version' );
			return array(
				sprintf(
					/* translators: %s: WordPress version */
					__( 'WordPress %s', 'gi-toolkit' ),
					$version
				),
			);
		}

		if ( 'translation' === $type ) {
			return self::resolve_translation_labels( $items, $data );
		}

		if ( 'theme' === $type ) {
			return self::resolve_theme_labels( $items );
		}

		if ( 'plugin' === $type ) {
			return self::resolve_plugin_labels( $items );
		}

		$labels = array();
		foreach ( $items as $item ) {
			$labels[] = self::humanize_raw_item( (string) $item );
		}

		return array_values( array_filter( $labels ) );
	}

	/**
	 * @param array<int, string>   $items Items.
	 * @param array<string, mixed> $data  Contexte.
	 * @return array<int, string>
	 */
	private static function resolve_translation_labels( $items, $data ) {
		$labels = array();

		if ( ! empty( $data['translations'] ) && is_array( $data['translations'] ) ) {
			foreach ( $data['translations'] as $translation ) {
				if ( ! is_array( $translation ) ) {
					continue;
				}
				$labels[] = self::format_translation_label( $translation );
			}
		}

		if ( empty( $labels ) ) {
			foreach ( $items as $item ) {
				$parts = explode( ':', (string) $item );
				$labels[] = self::format_translation_label(
					array(
						'type'     => $parts[0] ?? '',
						'slug'     => $parts[1] ?? ( $parts[0] ?? '' ),
						'language' => $parts[2] ?? ( $parts[1] ?? '' ),
					)
				);
			}
		}

		return array_values( array_filter( $labels ) );
	}

	/**
	 * @param array<string, mixed> $translation Translation payload.
	 * @return string
	 */
	private static function format_translation_label( $translation ) {
		$ttype = sanitize_text_field( (string) ( $translation['type'] ?? '' ) );
		$slug  = sanitize_text_field( (string) ( $translation['slug'] ?? '' ) );
		$lang  = sanitize_text_field( (string) ( $translation['language'] ?? '' ) );

		$name = $slug;
		if ( 'plugin' === $ttype && '' !== $slug ) {
			$name = self::get_plugin_name_by_slug( $slug );
		} elseif ( 'theme' === $ttype && '' !== $slug ) {
			$theme = wp_get_theme( $slug );
			$name  = $theme->exists() ? $theme->get( 'Name' ) : $slug;
		} elseif ( 'core' === $ttype ) {
			$name = 'WordPress';
		}

		$bits = array( __( 'Traduction', 'gi-toolkit' ) );
		if ( '' !== $name ) {
			$bits[] = $name;
		}
		if ( '' !== $lang ) {
			$bits[] = $lang;
		}

		return implode( ' · ', $bits );
	}

	/**
	 * @param array<int, string> $items Plugin files.
	 * @return array<int, string>
	 */
	private static function resolve_plugin_labels( $items ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all     = get_plugins();
		$labels  = array();

		foreach ( $items as $file ) {
			$file = (string) $file;
			if ( isset( $all[ $file ]['Name'] ) ) {
				$labels[] = (string) $all[ $file ]['Name'];
				continue;
			}

			$slug = dirname( $file );
			if ( '.' === $slug || '' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$labels[] = self::get_plugin_name_by_slug( $slug );
		}

		return array_values( array_filter( $labels ) );
	}

	/**
	 * @param array<int, string> $items Theme slugs.
	 * @return array<int, string>
	 */
	private static function resolve_theme_labels( $items ) {
		$labels = array();
		foreach ( $items as $slug ) {
			$theme = wp_get_theme( (string) $slug );
			$labels[] = $theme->exists() ? (string) $theme->get( 'Name' ) : self::humanize_raw_item( (string) $slug );
		}
		return array_values( array_filter( $labels ) );
	}

	/**
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	private static function get_plugin_name_by_slug( $slug ) {
		$slug = sanitize_text_field( (string) $slug );
		if ( '' === $slug ) {
			return '';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $file => $data ) {
			$dir = dirname( (string) $file );
			if ( $dir === $slug || basename( (string) $file, '.php' ) === $slug ) {
				return (string) ( $data['Name'] ?? $slug );
			}
		}

		return self::humanize_raw_item( $slug );
	}

	/**
	 * @param string $item Raw item.
	 * @return string
	 */
	private static function humanize_raw_item( $item ) {
		$item = (string) $item;
		if ( false !== strpos( $item, '/' ) ) {
			$dir = basename( dirname( $item ) );
			$item = ( '.' === $dir || '' === $dir ) ? basename( $item ) : $dir;
		}
		$item = preg_replace( '/\.php$/', '', $item );
		$item = str_replace( array( '-', '_' ), ' ', (string) $item );
		return ucwords( trim( (string) $item ) );
	}
}
