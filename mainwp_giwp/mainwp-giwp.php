<?php
/**
 * Plugin Name: MainWP GI-Toolkit Manager
 * Plugin URI: https://genevois-informatique.com/
 * Description: Gérez et déployez la configuration GI-Toolkit sur tous vos sites MainWP.
 * Version: 1.5.6
 * Author: Genevois Informatique
 * Author URI: https://genevois-informatique.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mainwp-giweb
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package MainWP_GIWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MAINWP_GIWEB_PLUGIN_FILE' ) ) {
	define( 'MAINWP_GIWEB_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/mainwp-giweb-extension.php';
