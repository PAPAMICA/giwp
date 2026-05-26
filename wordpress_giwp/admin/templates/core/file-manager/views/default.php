<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<form action="" method="post" class="pt-3">
	<input type="hidden" name="p" value="<?php echo esc_attr( $this->fm_enc( $this->FM_PATH ) ); ?>">
	<input type="hidden" name="group" value="1">
	<input type="hidden" name="token" value="<?php echo esc_attr( $this->TOKEN ); ?>">

	<div class="table-responsive">
		<table class="table table-bordered table-hover table-sm" id="main-table">
			<thead class="thead-white">
				<tr>
					<th style="width:3%" class="custom-checkbox-header">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="js-select-all-items" onclick="checkbox_toggle()">
							<label class="custom-control-label" for="js-select-all-items"></label>
						</div>
					</th>
					<th><?php esc_html_e( 'Name', 'gi-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Size', 'gi-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Modified', 'gi-toolkit' ); ?></th>
					<?php if ( ! $this->FM_IS_WIN ) : ?>
					<th><?php esc_html_e( 'Perms', 'gi-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Owner', 'gi-toolkit' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Actions', 'gi-toolkit' ); ?></th>
				</tr>
			</thead>
			<?php 
			if ( $this->PARENT_PATH !== false ) {
				?>
				<tr>
					<td class="nosort"></td>
					<td class="border-0" data-sort><a href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->PARENT_PATH ) ); ?>"><i class="fa fa-chevron-circle-left go-back"></i> ..</a></td>
					<td class="border-0" data-order></td>
					<td class="border-0" data-order></td>
					<td class="border-0"></td>
					<?php if ( ! $this->FM_IS_WIN ) : ?>
					<td class="border-0"></td>
					<td class="border-0"></td>
					<?php endif;?>
				</tr>
				<?php
			}
			$gi_toolkit_ii = 3399;
			foreach ( $folders as $gi_toolkit_f ) {
				$gi_toolkit_folder_path    = $this->PATH . '/' . $gi_toolkit_f;
				$gi_toolkit_is_link        = is_link( $gi_toolkit_folder_path );
				$gi_toolkit_img            = $gi_toolkit_is_link ? 'icon-link_folder' : 'fa fa-folder-o';
				$gi_toolkit_modif_raw      = filemtime( $gi_toolkit_folder_path );
				$gi_toolkit_modif          = wp_date( "m/d/Y g:i A", $gi_toolkit_modif_raw );
				$gi_toolkit_date_sorting   = strtotime( wp_date( "F d Y H:i:s.", $gi_toolkit_modif_raw ) );
				$gi_toolkit_filesize_raw   = "";
				$gi_toolkit_filesize       = 'Folder';
				$gi_toolkit_perms          = substr( decoct( fileperms( $gi_toolkit_folder_path ) ), -4 );
				if ( function_exists('posix_getpwuid') && function_exists('posix_getgrgid') ) {
					$gi_toolkit_owner  = posix_getpwuid( fileowner( $gi_toolkit_folder_path ) );
					$gi_toolkit_group  = posix_getgrgid( filegroup( $gi_toolkit_folder_path ) );

					if ( $gi_toolkit_owner === false ) {
						$gi_toolkit_owner = array( 'name' => '?' );
					}
					if ( $gi_toolkit_group === false ) {
						$gi_toolkit_group = array( 'name' => '?' );
					}
				} else {
					$gi_toolkit_owner = array( 'name' => '?' );
					$gi_toolkit_group = array( 'name' => '?' );
				}

				?>
				<tr>
					<td class="custom-checkbox-td">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="<?php echo esc_attr( $gi_toolkit_ii ); ?>" name="file[]" value="<?php echo esc_attr( $this->fm_enc( $gi_toolkit_f ) ); ?>">
							<label class="custom-control-label" for="<?php echo esc_attr( $gi_toolkit_ii ); ?>"></label>
						</div>
					</td>
					<td data-sort=<?php echo esc_attr( $this->fm_convert_win( $this->fm_enc( $gi_toolkit_f ) ) ); ?>>
						<div class="filename">
							<a href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $gi_toolkit_f, '/' ) ) ); ?>">
								<i class="<?php echo esc_attr( $gi_toolkit_img ); ?>"></i>
								<?php echo esc_attr( $this->fm_convert_win( $this->fm_enc( $gi_toolkit_f ) ) ); ?>
							</a>
							<?php echo ($gi_toolkit_is_link ? ' &rarr; <i>' . esc_html( readlink( $gi_toolkit_folder_path ) ) . '</i>' : '') ?>
						</div>
					</td>
					<td data-order="a-<?php echo esc_attr( str_pad( $gi_toolkit_filesize_raw, 18, "0", STR_PAD_LEFT ) );?>">
						<?php echo esc_html( $gi_toolkit_filesize ); ?>
					</td>
					<td data-order="a-<?php echo esc_attr( $gi_toolkit_date_sorting );?>"><?php echo esc_html( $gi_toolkit_modif ); ?></td>
					<?php if ( ! $this->FM_IS_WIN ) : ?>
						<td><a title="<?php esc_attr_e( 'Change Permissions', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;chmod=<?php echo urlencode($gi_toolkit_f) ?>"><?php echo esc_html( $gi_toolkit_perms ); ?></a></td>
						<td><?php echo esc_html( $gi_toolkit_owner['name'] . ':' . $gi_toolkit_group['name'] ); ?></td>
					<?php endif; ?>
					<td class="inline-actions">
						<a title="<?php esc_attr_e( 'Delete', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;del=<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>" onclick="confirmDailog(event, '1028', '<?php esc_attr_e( 'Delete Folder', 'gi-toolkit' ); ?>','<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>', this.href);"> <i class="fa fa-trash-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'Rename', 'gi-toolkit' ); ?>" href="#" onclick="rename('<?php echo esc_attr( $this->fm_enc( addslashes( $this->FM_PATH ) ) ); ?>', '<?php echo esc_attr( $this->fm_enc( addslashes( $gi_toolkit_f ) ) ); ?>');return false;"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'CopyTo', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=&amp;copy=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $gi_toolkit_f, '/' ) ) ); ?>"><i class="fa fa-files-o" aria-hidden="true"></i></a>
						<a title="<?php esc_attr_e( 'DirectLink', 'gi-toolkit' ); ?>" href="<?php echo esc_attr( $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $gi_toolkit_f . '/') ); ?>" target="_blank"><i class="fa fa-link" aria-hidden="true"></i></a>
					</td>
				</tr>
				<?php
				flush();
				$gi_toolkit_ii++;
			}
			$gi_toolkit_ik = 6070;
			foreach ( $files as $gi_toolkit_f ) {
				$gi_toolkit_file_path      = $this->PATH . '/' . $gi_toolkit_f;
				$gi_toolkit_is_link        = is_link( $gi_toolkit_file_path );
				$gi_toolkit_img            = $gi_toolkit_is_link ? 'fa fa-file-text-o' : $this->fm_get_file_icon_class( $gi_toolkit_file_path );
				$gi_toolkit_modif_raw      = filemtime( $gi_toolkit_file_path );
				$gi_toolkit_modif          = wp_date( "m/d/Y g:i A", $gi_toolkit_modif_raw );
				$gi_toolkit_date_sorting   = strtotime( wp_date( "F d Y H:i:s.", $gi_toolkit_modif_raw ) );
				$gi_toolkit_filesize_raw   = $this->fm_get_size( $gi_toolkit_file_path );
				$gi_toolkit_filesize       = $this->fm_get_filesize( $gi_toolkit_filesize_raw );
				$gi_toolkit_filelink       = '?page=gi-toolkit-settings-file-manager&token='. $this->TOKEN .'&p=' . urlencode( $this->FM_PATH ) . '&amp;view=' . urlencode( $gi_toolkit_f );
				//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				$all_files_size += $gi_toolkit_filesize_raw;
				$gi_toolkit_perms          = substr( decoct( fileperms( $gi_toolkit_file_path )), -4 );
				$gi_toolkit_ext            = strtolower( pathinfo( $gi_toolkit_file_path, PATHINFO_EXTENSION ) );
				$gi_toolkit_mime_type      = $this->fm_get_mime_type( $gi_toolkit_file_path );
				$gi_toolkit_is_text        = false;
				if ( in_array( $gi_toolkit_ext, $this->fm_get_text_exts() ) || substr( $gi_toolkit_mime_type, 0, 4 ) == 'text' || in_array( $gi_toolkit_mime_type, $this->fm_get_text_mimes() ) ) {
					$gi_toolkit_is_text = true;
				}

				if ( function_exists('posix_getpwuid') && function_exists('posix_getgrgid') ) {
					$gi_toolkit_owner = posix_getpwuid( fileowner( $gi_toolkit_file_path ) );
					$gi_toolkit_group = posix_getgrgid( filegroup( $gi_toolkit_file_path ) );
					if ( $gi_toolkit_owner === false ) {
						$gi_toolkit_owner = array( 'name' => '?' );
					}
					if ( $gi_toolkit_group === false ) {
						$gi_toolkit_group = array( 'name' => '?' );
					}
				} else {
					$gi_toolkit_owner = array( 'name' => '?' );
					$gi_toolkit_group = array( 'name' => '?' );
				}
				?>
				<tr>
					<td class="custom-checkbox-td">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="<?php echo esc_attr( $gi_toolkit_ik ); ?>" name="file[]" value="<?php echo esc_attr( $this->fm_enc( $gi_toolkit_f ) ); ?>">
							<label class="custom-control-label" for="<?php echo esc_attr( $gi_toolkit_ik ); ?>"></label>
						</div>
					</td>
					<td data-sort=<?php echo esc_attr( $this->fm_enc( $gi_toolkit_f ) ); ?>>
						<div class="filename">
							<?php if ( in_array( strtolower( pathinfo( $gi_toolkit_f, PATHINFO_EXTENSION ) ), array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'svg', 'webp', 'avif'))): ?>
								<?php $gi_toolkit_imagePreview = $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '') . '/' . $gi_toolkit_f ); ?>
								<a href="<?php echo esc_attr( $gi_toolkit_filelink ); ?>" data-preview-image="<?php echo esc_attr( $gi_toolkit_imagePreview ); ?>" title="<?php echo esc_attr( $this->fm_enc( $gi_toolkit_f ) ); ?>">
							<?php else: ?>
								<a href="<?php echo esc_attr( $gi_toolkit_filelink ); ?>" title="<?php echo esc_attr( $gi_toolkit_f ); ?>">
							<?php endif; ?>
								<i class="<?php echo esc_attr( $gi_toolkit_img ); ?>"></i> <?php echo esc_html( $this->fm_convert_win( $this->fm_enc( $gi_toolkit_f ) ) ); ?>
							</a>
							<?php echo($gi_toolkit_is_link ? ' &rarr; <i>' . esc_html( readlink( $gi_toolkit_file_path ) ) . '</i>' : '') ?>
						</div>
					</td>
					<td data-order="b-<?php echo esc_attr( str_pad( $gi_toolkit_filesize_raw, 18, "0", STR_PAD_LEFT ) ); ?>">
						<span title="<?php printf('%s bytes', esc_attr( $gi_toolkit_filesize_raw ) ); ?>"><?php echo esc_html( $gi_toolkit_filesize ); ?></span>
					</td>
					<td data-order="b-<?php echo esc_attr( $gi_toolkit_date_sorting );?>"><?php echo esc_html( $gi_toolkit_modif ); ?></td>
					<?php if ( ! $this->FM_IS_WIN ): ?>
						<td><a title="<?php esc_html_e( 'Change Permissions', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;chmod=<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>"><?php echo esc_html( $gi_toolkit_perms ); ?></a></td>
						<td><?php echo esc_html( $this->fm_enc( $gi_toolkit_owner['name'] . ':' . $gi_toolkit_group['name'] ) ); ?></td>
					<?php endif; ?>
					<td class="inline-actions">
						<a title="<?php esc_html_e( 'Delete', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;del=<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>" onclick="confirmDailog(event, 1209, '<?php esc_html_e( 'Delete File', 'gi-toolkit' ); ?>','<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>', this.href);"> <i class="fa fa-trash-o"></i></a>
						<?php if ( $gi_toolkit_is_text ): ?>
						<a title="<?php esc_html_e( 'Edit', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( trim( $this->FM_PATH ) ) ); ?>&amp;edit=<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>"><i class="fa fa-pencil-square-o"></i></a>
						<?php endif; ?>
						<a title="<?php esc_html_e( 'Rename', 'gi-toolkit' ); ?>" href="#" onclick="rename('<?php echo esc_attr( $this->fm_enc( addslashes( $this->FM_PATH ) ) ); ?>', '<?php echo esc_attr( $this->fm_enc( addslashes( $gi_toolkit_f ) ) ); ?>');return false;"><i class="fa fa-text-width"></i></a>
						<a title="<?php esc_html_e( 'CopyTo', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;copy=<?php echo esc_attr( urlencode( trim( $this->FM_PATH . '/' . $gi_toolkit_f, '/') ) ); ?>"><i class="fa fa-files-o"></i></a>
						<a title="<?php esc_html_e( 'DirectLink', 'gi-toolkit' ); ?>" href="<?php echo esc_attr( $this->fm_enc( $this->FM_ROOT_URL . ( $this->FM_PATH != '' ? '/' . $this->FM_PATH : '' ) . '/' . $gi_toolkit_f ) ); ?>" target="_blank"><i class="fa fa-link"></i></a>
						<a title="<?php esc_html_e( 'Download', 'gi-toolkit' ); ?>" href="?page=gi-toolkit-settings-file-manager&token=<?php echo esc_attr( $this->TOKEN ); ?>&p=<?php echo esc_attr( urlencode( $this->FM_PATH ) ); ?>&amp;dl=<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>" onclick="confirmDailog(event, 1211, '<?php esc_html_e( 'Download', 'gi-toolkit' ); ?>','<?php echo esc_attr( urlencode( $gi_toolkit_f ) ); ?>', this.href);"><i class="fa fa-download"></i></a>
					</td>
				</tr>
				<?php
				flush();
				$gi_toolkit_ik++;
			}

			if ( empty($folders) && empty($files) ) {
				?>
				<tfoot>
					<tr>
						<td></td>
						<td colspan="<?php echo ! $this->FM_IS_WIN ? '6' : '4' ?>"><em><?php esc_html_e( 'Folder is empty', 'gi-toolkit' ); ?></em></td>
					</tr>
				</tfoot>
				<?php
			} else {
				?>
				<tfoot>
					<tr>
						<td class="gray" colspan="<?php echo ! $this->FM_IS_WIN ? '7' : '5'; ?>">
							<?php esc_html_e( 'FullSize', 'gi-toolkit' ) ?>: <span class="badge text-bg-light border-radius-0"><?php echo esc_html( $this->fm_get_filesize( $all_files_size ) ); ?></span>
							<?php esc_html_e( 'File', 'gi-toolkit' ) ?>: <span class="badge text-bg-light border-radius-0"><?php echo esc_html( $num_files ); ?></span>
							<?php esc_html_e( 'Folder', 'gi-toolkit' ) ?>: <span class="badge text-bg-light border-radius-0"><?php echo esc_html( $num_folders ); ?></span>
						</td>
					</tr>
				</tfoot>
				<?php
			}
			?>
		</table>
	</div>

	<div class="row">
		<div class="col-xs-12 col-sm-9">
			<ul class="list-inline footer-action">
				<li class="list-inline-item">
					<a href="#/select-all" class="btn btn-small btn-outline-primary btn-2" onclick="select_all();return false;"><i class="fa fa-check-square"></i> <?php esc_html_e( 'Select All', 'gi-toolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<a href="#/unselect-all" class="btn btn-small btn-outline-primary btn-2" onclick="unselect_all();return false;"><i class="fa fa-window-close"></i> <?php esc_html_e( 'UnSelect All', 'gi-toolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<a href="#/invert-all" class="btn btn-small btn-outline-primary btn-2" onclick="invert_all();return false;"><i class="fa fa-th-list"></i> <?php esc_html_e( 'Invert Selection', 'gi-toolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="delete" id="a-delete" value="Delete" onclick="return confirm('<?php esc_html_e( 'Delete selected files and folders?', 'gi-toolkit' ); ?>');">
					<a href="javascript:document.getElementById('a-delete').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-trash"></i> <?php esc_html_e( 'Delete', 'gi-toolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="zip" id="a-zip" value="zip" onclick="return confirm('<?php esc_html_e( 'Create archive?', 'gi-toolkit' ); ?>');">
					<a href="javascript:document.getElementById('a-zip').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-file-archive-o"></i> <?php esc_html_e( 'Zip', 'gi-toolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="tar" id="a-tar" value="tar" onclick="return confirm('<?php esc_attr_e( 'Create archive?', 'gi-toolkit' ); ?>');">
					<a href="javascript:document.getElementById('a-tar').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-file-archive-o"></i> <?php esc_html_e( 'Tar', 'gi-toolkit' ); ?></a>
				</li>
				<li class="list-inline-item">
					<input type="submit" class="hidden" name="copy" id="a-copy" value="Copy">
					<a href="javascript:document.getElementById('a-copy').click();" class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-files-o"></i> <?php esc_html_e( 'Copy', 'gi-toolkit' ); ?></a>
				</li>
			</ul>
		</div>
	</div>
</form>
