<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="row">
	<div class="col-12">
		<p class="break-word">
			<b><?php echo esc_html( $view_title ); ?> "<?php echo esc_html( $this->fm_enc( $this->fm_convert_win( $file ) ) ); ?>"</b>
		</p>
		<p class="break-word">
			<strong><?php esc_html_e( 'Full Path', 'gi-toolkit' ); ?>:</strong> <?php echo esc_html( $this->fm_enc( $this->fm_convert_win( $file_path ) ) ); ?><br>
			<strong><?php esc_html_e( 'File size', 'gi-toolkit' ); ?>:</strong> <?php echo ( $filesize_raw <= 1000 ) ? esc_html( "$filesize_raw bytes" ) : esc_html( $filesize ); ?><br>
			<strong><?php esc_html_e( 'MIME-type', 'gi-toolkit' ); ?>:</strong> <?php echo esc_html( $mime_type ); ?><br>
			<?php
			// ZIP info
			if ( ( $is_zip || $is_gzip ) && $filenames !== false ) {
				$gi_toolkit_total_files  = 0;
				$gi_toolkit_total_comp   = 0;
				$gi_toolkit_total_uncomp = 0;
				foreach ( $filenames as $gi_toolkit_fn ) {
					if ( ! $gi_toolkit_fn['folder'] ) {
						$gi_toolkit_total_files++;
					}
					$gi_toolkit_total_comp   += $gi_toolkit_fn['compressed_size'];
					$gi_toolkit_total_uncomp += $gi_toolkit_fn['filesize'];
				}

				echo esc_html(
					sprintf(
						/* translators: %s: Number of files */
						__( 'Files in archive: %s', 'gi-toolkit' ),
						$gi_toolkit_total_files,
					)
				) . '<br>';
				echo esc_html(
					sprintf(
						/* translators: %s: Total size */
						__( 'Total size: %s', 'gi-toolkit' ),
						$this->fm_get_filesize( $gi_toolkit_total_uncomp ),
					)
				) . '<br>';
				echo esc_html(
					sprintf(
						/* translators: %s: Total compressed size */
						__( 'Size in archive: %s', 'gi-toolkit' ),
						$this->fm_get_filesize( $gi_toolkit_total_comp ),
					)
				) . '<br>';
				echo esc_html(
					sprintf(
						/* translators: %s: Compression percentage */
						__( 'Compression: %s%%', 'gi-toolkit' ),
						round( ( $gi_toolkit_total_comp / max( $gi_toolkit_total_uncomp , 1 ) ) * 100 ),
					)
				) . '<br>';
			}
			// Image info
			if ( $is_image ) {
				$gi_toolkit_image_size = getimagesize( $file_path );
				echo '<strong>' . esc_html__( 'Image size', 'gi-toolkit' ) . ':</strong> ' . ( isset( $gi_toolkit_image_size[0] ) ? esc_html( $gi_toolkit_image_size[0] ) : '0' ) . ' x ' . ( isset( $gi_toolkit_image_size[1] ) ? esc_html( $gi_toolkit_image_size[1] ) : '0' ) . '<br>';
			}
			// Text info
			if ( $is_text ) {
				$gi_toolkit_is_utf8 = $this->fm_is_utf8( $content );
				if ( function_exists('iconv') ) {
					if ( ! $gi_toolkit_is_utf8 ) {
						//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						$content = iconv( 'UTF-8', 'UTF-8//IGNORE', $content );
					}
				}
				echo '<strong>' . esc_html__( 'Charset', 'gi-toolkit' ) . ':</strong> ' . ( $gi_toolkit_is_utf8 ? 'utf-8' : '8 bit' ) . '<br>';
			}
			?>
		</p>
		<div class="d-flex align-items-center mb-3">
			<form method="post" class="d-inline ms-2" action="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;dl=<?php echo esc_attr( urlencode( $file ) ); ?>">
				<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
				<button type="submit" class="btn btn-link text-decoration-none fw-bold p-0"><i class="fa fa-cloud-download"></i> <?php esc_html_e( 'Download', 'gi-toolkit' ); ?></button> &nbsp;
			</form>
			<b class="ms-2"><a href="<?php echo esc_attr( $this->fm_enc($file_url) ); ?>" target="_blank"><i class="fa fa-external-link-square"></i> <?php esc_html_e( 'Open', 'gi-toolkit' ); ?></a></b>
			<?php
			// ZIP actions
			if ( ( $is_zip || $is_gzip ) && $filenames !== false ) {
				$gi_toolkit_zip_name = pathinfo( $file_path, PATHINFO_FILENAME );
				?>
				<form method="post" class="d-inline ms-2">
					<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
					<input type="hidden" name="unzip" value="<?php echo esc_attr( urlencode( $file ) ); ?>">
					<button type="submit" class="btn btn-link text-decoration-none fw-bold p-0" style="font-size: 14px;"><i class="fa fa-check-circle"></i> <?php esc_html_e( 'UnZip', 'gi-toolkit' ); ?></button>
				</form>&nbsp;
				<form method="post" class="d-inline ms-2">
					<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">
					<input type="hidden" name="unzip" value="<?php echo esc_attr( urlencode( $file ) ); ?>">
					<input type="hidden" name="tofolder" value="1">
					<button type="submit" class="btn btn-link text-decoration-none fw-bold p-0" style="font-size: 14px;" title="<?php echo esc_attr__( 'UnZip to', 'gi-toolkit' ) . ' ' . esc_attr( $this->fm_enc( $gi_toolkit_zip_name ) ); ?>"><i class="fa fa-check-circle"></i> <?php esc_html_e( 'UnZipToFolder', 'gi-toolkit' ); ?></button>
				</form>&nbsp;
				<?php
			}
			if ( $is_text ) {
				?>
				<b class="ms-2"><a href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH ) ) ); ?>&amp;edit=<?php echo esc_attr( urlencode( $file ) ); ?>" class="edit-file"><i class="fa fa-pencil-square"></i> <?php esc_html_e( 'Edit', 'gi-toolkit' ); ?></a></b> &nbsp;
				<?php
			}
			?>
			<b class="ms-2"><a href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>"><i class="fa fa-chevron-circle-left go-back"></i> <?php esc_html_e( 'Back', 'gi-toolkit' ); ?></a></b>
		</div>
		<?php
		if ( $is_onlineViewer ) {
			if ( $online_viewer == 'google' ) {
				echo '<iframe src="https://docs.google.com/viewer?embedded=true&hl=en&url=' . esc_attr( $this->fm_enc( $file_url ) ) . '" frameborder="no" style="width:100%;min-height:460px"></iframe>';
			} else if( $online_viewer == 'microsoft' ) {
				echo '<iframe src="https://view.officeapps.live.com/op/embed.aspx?src=' . esc_attr( $this->fm_enc( $file_url ) ) . '" frameborder="no" style="width:100%;min-height:460px"></iframe>';
			}
		} elseif ( $is_zip ) {
			// ZIP content
			if ( $filenames !== false ) {
				echo '<code class="maxheight">';
				foreach ( $filenames as $gi_toolkit_fn ) {
					if ( $gi_toolkit_fn['folder'] ) {
						echo '<b>' . esc_html( $this->fm_enc( $gi_toolkit_fn['name'] ) ) . '</b><br>';
					} else {
						echo esc_html( $gi_toolkit_fn['name'] ) . ' (' . esc_html( $this->fm_get_filesize( $gi_toolkit_fn['filesize'] ) ) . ')<br>';
					}
				}
				echo '</code>';
			} else {
				echo '<p>' . esc_html__( 'Error while fetching archive info', 'gi-toolkit' ) . '</p>';
			}
		} elseif ( $is_image ) {
			// Image content
			if ( in_array( $ext, array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'svg', 'webp', 'avif') ) ) {
				//phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
				echo '<p><input type="checkbox" id="preview-img-zoomCheck"><label for="preview-img-zoomCheck"><img src="' . esc_attr( $this->fm_enc( $file_url ) ) . '" alt="image" class="preview-img"></label></p>';
			}
		} elseif ( $is_audio ) {
			// Audio content
			echo '<p><audio src="' . esc_attr( $this->fm_enc( $file_url ) ) . '" controls preload="metadata"></audio></p>';
		} elseif ( $is_video ) {
			// Video content
			echo '<div class="preview-video"><video src="' . esc_attr( $this->fm_enc( $file_url ) ) . '" width="640" height="360" controls preload="metadata"></video></div>';
		} elseif ( $is_text ) {
			$gi_toolkit_hljs_classes = array(
				'shtml'    => 'xml',
				'htaccess' => 'apache',
				'phtml'    => 'php',
				'lock'     => 'json',
				'svg'      => 'xml'
			);
			$gi_toolkit_hljs_class = isset( $gi_toolkit_hljs_classes[$ext] ) ? 'lang-' . $gi_toolkit_hljs_classes[$ext] : 'lang-' . $ext;
			if ( empty( $ext ) || in_array( strtolower( $file ), $this->fm_get_text_names() ) || preg_match( '#\.min\.(css|js)$#i', $file ) ) {
				$gi_toolkit_hljs_class = 'nohighlight';
			}
			echo '<pre class="with-hljs"><code class="' . esc_attr( $gi_toolkit_hljs_class ) . '">' . esc_html( $this->fm_enc( $content ) ) . '</code></pre>';
		}
		?>
	</div>
</div>
