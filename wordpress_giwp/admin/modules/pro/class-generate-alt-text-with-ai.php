<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module: génération d’attribut alt via API compatible OpenAI (option gi_toolkit_openai_key + AJAX admin).
 */
class Gi_Toolkit_Generate_Alt_Text_With_AI {

	private $disable_form = true;

	private $header_title = '';

	private $nonce_action = 'gi_toolkit_save_openai_key';

	private $page_slug = 'gi-toolkit-settings-alt-ai';

	public function __construct() {
		$this->header_title = __( 'Generate Alt Text With AI', 'gi-toolkit' );
		add_action( 'wp_ajax_gi_toolkit_alt_ai', array( $this, 'ajax' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'field' ), 10, 2 );
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
		if ( ! gi_toolkit_pro_begin_save( $this->page_slug, $this->nonce_action ) ) {
			return;
		}
		$key = isset( $_POST['gi_toolkit_openai_key'] ) ? sanitize_text_field( wp_unslash( $_POST['gi_toolkit_openai_key'] ) ) : '';
		update_option( 'gi_toolkit_openai_key', $key, false );
		gi_toolkit_pro_module_redirect_saved( $this->page_slug );
	}

	public function render_submenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		gi_toolkit_pro_module_admin_styles();
		include GI_TOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$key = get_option( 'gi_toolkit_openai_key', '' );
		if ( ! is_string( $key ) ) {
			$key = '';
		}
		?>
		<div class="gi-toolkit__body" style="padding:1rem 1.5rem 2rem;max-width:720px;">
			<?php if ( ! empty( $_GET['gi_toolkit_pro_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Enregistré.', 'gi-toolkit' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Clé API OpenAI utilisée pour le bouton « Proposer un texte alt » sur la fiche des images dans la médiathèque (modèle gpt-4o-mini).', 'gi-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . rawurlencode( $this->page_slug ) ) ); ?>" autocomplete="off">
				<?php wp_nonce_field( $this->nonce_action ); ?>
				<input type="hidden" name="gi_toolkit_pro_save" value="1"/>
				<p><label for="gi_toolkit_openai_key"><strong><?php esc_html_e( 'Clé API', 'gi-toolkit' ); ?></strong></label></p>
				<p><input type="password" class="large-text" id="gi_toolkit_openai_key" name="gi_toolkit_openai_key" value="<?php echo esc_attr( $key ); ?>" autocomplete="new-password"/></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		echo '</div>';
	}

	public function field( $fields, $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) || ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}
		$fields['gi_toolkit_alt_ai'] = array(
			'label' => __( 'Alt IA (GI-Toolkit)', 'gi-toolkit' ),
			'input' => 'html',
			'html'  => '<button type="button" class="button" id="gi-toolkit-alt-ai-btn" data-id="' . esc_attr( (string) $post->ID ) . '">' . esc_html__( 'Proposer un texte alt', 'gi-toolkit' ) . '</button> <span id="gi-toolkit-alt-ai-status"></span>
			<script>
			jQuery(function($){
				$("#gi-toolkit-alt-ai-btn").on("click",function(){
					var id=$(this).data("id"); $("#gi-toolkit-alt-ai-status").text("…");
					$.post(ajaxurl,{action:"gi_toolkit_alt_ai",_ajax_nonce:"' . esc_js( wp_create_nonce( 'gi_toolkit_alt_ai' ) ) . '",attachment_id:id},function(r){
						if(r.success&&r.data&&r.data.alt){ $("#attachment_alt").val(r.data.alt); $("#gi-toolkit-alt-ai-status").text("OK"); }
						else { $("#gi-toolkit-alt-ai-status").text(r.data&&r.data.message?r.data.message:"Erreur"); }
					});
				});
			});
			</script>',
		);
		return $fields;
	}

	public function ajax() {
		check_ajax_referer( 'gi_toolkit_alt_ai' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Droits insuffisants.', 'gi-toolkit' ) ) );
		}
		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Fichier invalide.', 'gi-toolkit' ) ) );
		}

		$key = get_option( 'gi_toolkit_openai_key', '' );
		if ( ! is_string( $key ) || '' === trim( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Clé API absente (réglages GI-Toolkit → Generate Alt Text With AI).', 'gi-toolkit' ) ) );
		}

		$url = wp_get_attachment_url( $id );
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'URL introuvable.', 'gi-toolkit' ) ) );
		}

		$body = wp_json_encode(
			array(
				'model'    => 'gpt-4o-mini',
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'type' => 'text',
								'text' => __( 'Décris brièvement cette image en une phrase pour l’attribut alt (français, sans guillemets).', 'gi-toolkit' ),
							),
							array(
								'type'      => 'image_url',
								'image_url' => array( 'url' => $url ),
							),
						),
					),
				),
			)
		);

		$res = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		$alt  = isset( $data['choices'][0]['message']['content'] ) ? sanitize_text_field( $data['choices'][0]['message']['content'] ) : '';
		if ( '' === $alt ) {
			wp_send_json_error( array( 'message' => __( 'Réponse vide.', 'gi-toolkit' ) ) );
		}

		wp_send_json_success( array( 'alt' => $alt ) );
	}
}
